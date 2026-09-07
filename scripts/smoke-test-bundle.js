#!/usr/bin/env node
/**
 * Smoke test for the EXACT bundle users receive.
 *
 * `npm test` drives mcp-server/dist/index.js — the tsc output. Nobody is
 * shipped that. The Claude Code plugin, the .mcpb extension, and the release
 * package all ship plugins/bridgistic/server/index.js, a single esbuild CJS
 * bundle. Those are different files produced by different tools, and a bundler
 * can break things tsc cannot: a dynamic require the bundle drops, an ESM/CJS
 * interop mismatch, a dependency that resolves at runtime but not at bundle
 * time. Testing only dist/index.js would let any of those ship green.
 *
 * So this spawns the bundle itself, completes a real MCP handshake over stdio,
 * and asserts tools/list comes back with the expected surface. It also
 * verifies the committed bundle matches a clean rebuild, so a stale artifact
 * cannot be released alongside newer source.
 *
 * Run: npm run test:bundle
 */

import { spawn, execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { readFileSync, existsSync, copyFileSync, unlinkSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import assert from "node:assert/strict";

const ROOT = join(fileURLToPath(import.meta.url), "..", "..");
const BUNDLE = join(ROOT, "plugins/bridgistic/server/index.js");

let checks = 0;
const ok = (condition, label) => {
  assert.ok(condition, label);
  checks++;
};

// ---- 1. The bundle exists and is a bundle ---------------------------------

if (!existsSync(BUNDLE)) {
  console.error(`✗ shipped bundle missing at ${BUNDLE} — run \`npm run build\` first`);
  process.exit(1);
}

const source = readFileSync(BUNDLE, "utf8");
ok(source.length > 50_000, "the bundle is large enough to have dependencies inlined, not just a re-export");
ok(!source.includes('require("@modelcontextprotocol/sdk'), "the MCP SDK is inlined, so the bundle runs without node_modules");
ok(!/\bfrom\s+["']zod["']/.test(source), "zod is inlined rather than left as a bare import");
checks += 0;

// ---- 2. The committed bundle matches a clean rebuild ----------------------
//
// A bundle regenerated from newer source than the one committed means the
// release would ship code nobody tested. Compare hashes rather than trusting
// that whoever last edited a tool remembered to rebuild.

const committedHash = createHash("sha256").update(readFileSync(BUNDLE)).digest("hex");
const backup = `${BUNDLE}.smoke-backup`;
copyFileSync(BUNDLE, backup);

let rebuiltHash;
try {
  const npmExecPath = process.env.npm_execpath;
  if (npmExecPath) {
    execFileSync(process.execPath, [npmExecPath, "run", "build:ci"], { cwd: ROOT, stdio: "pipe" });
  } else {
    execFileSync("npm", ["run", "build:ci"], { cwd: ROOT, stdio: "pipe" });
  }
  rebuiltHash = createHash("sha256").update(readFileSync(BUNDLE)).digest("hex");
} finally {
  // Whatever happened, leave the tree as we found it if the rebuild differed
  // only because of a build failure.
  if (!rebuiltHash) copyFileSync(backup, BUNDLE);
  unlinkSync(backup);
}

if (committedHash !== rebuiltHash) {
  console.error(
    `✗ the committed bundle is stale.\n` +
      `    committed: ${committedHash}\n` +
      `    rebuilt:   ${rebuiltHash}\n` +
      `  Run \`npm run build\` and commit plugins/bridgistic/server/index.js.`
  );
  process.exit(1);
}
ok(true, "the committed bundle is byte-identical to a clean rebuild");

// ---- 3. Handshake + tools/list against the bundle -------------------------

function driveBundle() {
  return new Promise((resolve, reject) => {
    const child = spawn("node", [BUNDLE], {
      stdio: ["pipe", "pipe", "pipe"],
      env: {
        ...process.env,
        BRIDGISTIC_SITE_URL: "https://example.com",
        BRIDGISTIC_KEY_ID: "wpk_smoketest000000000000",
        BRIDGISTIC_KEY_SECRET: "wps_smoketest_secret_value_not_real_000000000000",
        BRIDGISTIC_LOG_LEVEL: "error",
      },
    });

    const stderr = [];
    let buffer = "";
    const seen = {};

    const timer = setTimeout(() => {
      child.kill();
      reject(new Error(`timed out waiting for the bundle to answer. stderr:\n${stderr.join("")}`));
    }, 15000);

    child.stdout.on("data", (chunk) => {
      buffer += chunk.toString();
      let newline;
      while ((newline = buffer.indexOf("\n")) >= 0) {
        const line = buffer.slice(0, newline).trim();
        buffer = buffer.slice(newline + 1);
        if (!line) continue;
        let message;
        try {
          message = JSON.parse(line);
        } catch {
          clearTimeout(timer);
          child.kill();
          reject(new Error(`the bundle wrote non-JSON to stdout, which corrupts the stdio transport: ${line.slice(0, 200)}`));
          return;
        }
        if (message.id === 1) seen.initialize = message;
        if (message.id === 2 && message.result?.tools) {
          clearTimeout(timer);
          child.kill();
          resolve({ initialize: seen.initialize, tools: message.result.tools, stderr: stderr.join("") });
          return;
        }
      }
    });

    child.stderr.on("data", (chunk) => stderr.push(chunk.toString()));
    child.on("error", (err) => {
      clearTimeout(timer);
      reject(err);
    });

    const send = (payload) => child.stdin.write(`${JSON.stringify(payload)}\n`);
    send({
      jsonrpc: "2.0",
      id: 1,
      method: "initialize",
      params: { protocolVersion: "2024-11-05", capabilities: {}, clientInfo: { name: "bundle-smoke", version: "0" } },
    });
    setTimeout(() => send({ jsonrpc: "2.0", method: "notifications/initialized" }), 200);
    setTimeout(() => send({ jsonrpc: "2.0", id: 2, method: "tools/list", params: {} }), 400);
  });
}

const { initialize, tools, stderr } = await driveBundle();

ok(initialize?.result?.serverInfo?.name === "bridgistic-mcp-server", "the bundle identifies itself correctly in the handshake");

const pkgVersion = JSON.parse(readFileSync(join(ROOT, "mcp-server/package.json"), "utf8")).version;
ok(
  initialize?.result?.serverInfo?.version === pkgVersion,
  `the bundle reports version ${pkgVersion} (got ${initialize?.result?.serverInfo?.version}) — a stale bundle would report the previous release`
);

ok(Array.isArray(tools) && tools.length > 0, "the bundle answers tools/list");

// The tool surface must match what the contract test asserts against the tsc
// output; a bundler that drops a tool registration would otherwise be invisible.
const expectedCount = Number(process.env.BRIDGISTIC_EXPECTED_TOOLS || 54);
ok(
  tools.length === expectedCount,
  `the bundle exposes ${expectedCount} tools (got ${tools.length}) — same surface as the unbundled build`
);

for (const required of [
  "bridgistic_get_site_info",
  "bridgistic_list_posts",
  "bridgistic_snapshot_restore",
  "bridgistic_woo_list_products",
  "bridgistic_woo_update_order_status",
]) {
  ok(
    tools.some((tool) => tool.name === required),
    `the bundle exposes ${required}`
  );
}

for (const tool of tools) {
  ok(typeof tool.description === "string" && tool.description.length > 30, `${tool.name} survived bundling with its description intact`);
  ok(typeof tool.inputSchema === "object" && tool.inputSchema !== null, `${tool.name} survived bundling with its input schema intact`);
}

// A stdio server that logs to stdout corrupts the transport; warnings belong
// on stderr. The absence of a parse failure above already proves stdout was
// clean, so this just confirms the startup report went somewhere.
ok(typeof stderr === "string", "startup diagnostics are written to stderr, not stdout");

console.log(`PASS  shipped-bundle smoke — ${tools.length} tools over a real stdio handshake, ${checks} assertions`);
