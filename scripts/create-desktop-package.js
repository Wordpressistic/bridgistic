#!/usr/bin/env node
/**
 * Builds dist/bridgistic.mcpb — the Claude Desktop extension (MCP Bundle).
 *
 * One-click connection: users double-click the .mcpb, Claude Desktop shows
 * the three user_config fields from mcpb/manifest.json (site URL, key id,
 * key secret — the secret is stored in the OS keychain), and the bundled
 * self-contained server runs locally. No terminal, no config files.
 *
 * Pipeline: stage manifest + icon + pre-built server bundle → validate the
 * manifest against the official schema → `mcpb pack` → verify the output.
 * Requires the server bundle (run `npm run build` first).
 */

import { execFileSync } from "node:child_process";
import { cpSync, existsSync, mkdirSync, rmSync, statSync, writeFileSync } from "node:fs";
import { join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(fileURLToPath(import.meta.url), "..", "..");
const DIST = join(ROOT, "dist");
const STAGE = join(DIST, "mcpb-staging");
const OUT = join(DIST, "bridgistic.mcpb");

const bundle = join(ROOT, "plugins/bridgistic/server/index.js");
if (!existsSync(bundle)) {
  console.error("✗ server bundle missing — run `npm run build` first.");
  process.exit(1);
}

const mcpb = (args, opts = {}) =>
  execFileSync("npx", ["--yes", "@anthropic-ai/mcpb@latest", ...args], {
    stdio: "inherit",
    cwd: ROOT,
    ...opts,
  });

// ---- stage ------------------------------------------------------------------

rmSync(STAGE, { recursive: true, force: true });
mkdirSync(join(STAGE, "server"), { recursive: true });

cpSync(join(ROOT, "mcpb/manifest.json"), join(STAGE, "manifest.json"));
cpSync(join(ROOT, "mcpb/icon.png"), join(STAGE, "icon.png"));
cpSync(bundle, join(STAGE, "server/index.js"));
// The bundle is CommonJS; pin the module type so Node never guesses wrong.
writeFileSync(
  join(STAGE, "server/package.json"),
  JSON.stringify({ name: "bridgistic-mcp-server-bundle", private: true, type: "commonjs" }, null, 2) + "\n"
);

// ---- validate + pack -----------------------------------------------------------

mcpb(["validate", join(STAGE, "manifest.json")]);
rmSync(OUT, { force: true });
mkdirSync(DIST, { recursive: true });
mcpb(["pack", STAGE, OUT]);

if (!existsSync(OUT)) {
  console.error("✗ mcpb pack did not produce an output file.");
  process.exit(1);
}
mcpb(["info", OUT]);

rmSync(STAGE, { recursive: true, force: true });
console.log(`\n✓ ${relative(ROOT, OUT)} (${(statSync(OUT).size / 1024).toFixed(0)} KB)`);
console.log("  Users install it by double-clicking (Claude Desktop → Settings → Extensions).");
