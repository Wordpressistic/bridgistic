#!/usr/bin/env node
/**
 * Build every release artifact ONCE, from one commit, into one directory.
 *
 * The failure this exists to prevent: publishing a GitHub release, then
 * discovering npm or the MCP Registry cannot accept the same version, and
 * rebuilding to retry — which produces different bytes under a version that
 * is already public. So everything is built here, verified here, and
 * checksummed here; publication only ever uploads what this produced.
 *
 * Output: dist/release-v<version>/
 *   bridgistic-wordpress-plugin.zip
 *   bridgistic-claude-package.zip
 *   bridgistic.mcpb
 *   bridgistic-mcp-server-<version>.tgz
 *   SHA256SUMS.txt
 *
 * The three markdown reports (release notes, test report, security checklist)
 * are authored, not generated, and are copied in from release/ alongside these.
 *
 * REPRODUCIBILITY. The two zips and the npm tarball are byte-reproducible:
 * rebuilding the same commit produces the same SHA256, so anyone can verify a
 * published artifact against the checksums here. `bridgistic.mcpb` is not — it
 * is packed by the third-party `@anthropic-ai/mcpb` CLI, which stamps build
 * time — so its digest is recorded for integrity but cannot be independently
 * reproduced. That is stated here and in the test report rather than left for
 * someone to discover when their rebuild does not match.
 *
 * Run: npm run release
 */

import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { copyFileSync, existsSync, mkdirSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from "node:fs";
import { join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(fileURLToPath(import.meta.url), "..", "..");
const VERSION = JSON.parse(readFileSync(join(ROOT, "package.json"), "utf8")).version;
const DIST = join(ROOT, "dist");
const OUT = join(DIST, `release-v${VERSION}`);

const run = (cmd, args, opts = {}) =>
  execFileSync(cmd, args, { cwd: ROOT, stdio: "inherit", ...opts });

const runNpm = (args, opts = {}) => {
  const npmExecPath = process.env.npm_execpath;
  if (npmExecPath) {
    return run(process.execPath, [npmExecPath, ...args], opts);
  }

  return run(process.platform === "win32" ? "npm.cmd" : "npm", args, opts);
};

const step = (msg) => console.log(`\n\x1b[1m▸ ${msg}\x1b[0m`);

// ---- 0. Refuse to build a release from a dirty tree -------------------------
//
// An artifact built from uncommitted changes cannot be reproduced from the tag
// it will be published under, which makes the checksums below meaningless.

let commit = "unknown";
let dirty = false;
try {
  commit = execFileSync("git", ["rev-parse", "HEAD"], { cwd: ROOT, encoding: "utf8" }).trim();
  dirty = execFileSync("git", ["status", "--porcelain"], { cwd: ROOT, encoding: "utf8" }).trim().length > 0;
} catch {
  console.warn("⚠ not a git checkout — skipping the clean-tree check");
}

if (dirty && !process.argv.includes("--allow-dirty")) {
  console.error(
    "✗ the working tree has uncommitted changes.\n" +
      "  Release artifacts must be reproducible from the commit they are published under.\n" +
      "  Commit first, or pass --allow-dirty for a local dry run."
  );
  process.exit(1);
}

console.log(`Building Bridgistic v${VERSION} release artifacts`);
console.log(`Commit: ${commit}${dirty ? " (DIRTY — dry run only)" : ""}`);

// ---- 1. Clean output --------------------------------------------------------

if (existsSync(OUT)) rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });

// ---- 2. Build ---------------------------------------------------------------

step("Building the MCP server and shipped bundle");
runNpm(["run", "build:ci"]);

step("Building distribution archives");
runNpm(["run", "package"]);

step("Building the Claude Desktop extension");
runNpm(["run", "desktop:package"]);

step("Packing the npm tarball");
// `npm pack` writes bridgistic-mcp-server-<version>.tgz into its cwd. Packing
// from the real package directory (not the private repo root) is what makes
// this the same tarball `npm publish` would upload.
runNpm(["pack", "--silent", "--pack-destination", OUT], { cwd: join(ROOT, "mcp-server") });

// ---- 3. Collect -------------------------------------------------------------

step("Collecting artifacts");

const ARCHIVES = ["bridgistic-wordpress-plugin.zip", "bridgistic-claude-package.zip", "bridgistic.mcpb"];
for (const name of ARCHIVES) {
  const from = join(DIST, name);
  if (!existsSync(from)) {
    console.error(`✗ expected artifact missing after build: ${relative(ROOT, from)}`);
    process.exit(1);
  }
  copyFileSync(from, join(OUT, name));
  console.log(`  ✓ ${name} (${(statSync(join(OUT, name)).size / 1024).toFixed(0)} KB)`);
}

const tarball = `bridgistic-mcp-server-${VERSION}.tgz`;
if (!existsSync(join(OUT, tarball))) {
  console.error(`✗ npm pack did not produce ${tarball}`);
  process.exit(1);
}
console.log(`  ✓ ${tarball} (${(statSync(join(OUT, tarball)).size / 1024).toFixed(0)} KB)`);

// ---- 4. Verify --------------------------------------------------------------
//
// Verify the copies in the release directory, not the originals in dist/ —
// these are the bytes that get published.

step("Verifying artifact structure");
run("node", ["scripts/verify-packages.js", "--dir", `dist/release-v${VERSION}`]);

step("Verifying the npm tarball");
{
  const listing = execFileSync("tar", ["-tzf", join(OUT, tarball)], { encoding: "utf8" });
  const entries = listing.split(/\r?\n/).filter(Boolean);

  const required = ["package/package.json", "package/dist/index.js", "package/README.md"];
  for (const entry of required) {
    if (entries.includes(entry)) console.log(`  ✓ contains ${entry}`);
    else {
      console.error(`  ✗ npm tarball is missing ${entry}`);
      process.exit(1);
    }
  }

  const forbidden = entries.filter((e) => /(^|\/)(node_modules|\.env|src|evals)\//.test(e));
  if (forbidden.length) {
    console.error(`  ✗ npm tarball ships files it should not: ${forbidden.slice(0, 5).join(", ")}`);
    process.exit(1);
  }
  console.log(`  ✓ ${entries.length} entries, no sources, tests, or node_modules`);
}

// ---- 5. Copy the authored reports -------------------------------------------

step("Adding release documents");
const RELEASE_DOCS = [
  `RELEASE_NOTES_v${VERSION}.md`,
  `TEST_REPORT_v${VERSION}.md`,
  `SECURITY_CHECKLIST_v${VERSION}.md`,
];
for (const name of RELEASE_DOCS) {
  const from = join(ROOT, "release", name);
  if (existsSync(from)) {
    copyFileSync(from, join(OUT, name));
    console.log(`  ✓ ${name}`);
  } else {
    console.warn(`  ⚠ ${name} not found in release/ — the release directory will be incomplete`);
  }
}

// ---- 6. Checksums -----------------------------------------------------------
//
// Written last so every file above is covered, and excluding itself.

step("Generating SHA256SUMS.txt");
const lines = [];
for (const name of readdirSync(OUT).sort()) {
  if (name === "SHA256SUMS.txt") continue;
  const digest = createHash("sha256").update(readFileSync(join(OUT, name))).digest("hex");
  lines.push(`${digest}  ${name}`);
  console.log(`  ${digest.slice(0, 16)}…  ${name}`);
}

writeFileSync(
  join(OUT, "SHA256SUMS.txt"),
  `# Bridgistic v${VERSION} release artifacts\n` +
    `# Commit: ${commit}\n` +
    `# Verify with: sha256sum -c SHA256SUMS.txt\n\n` +
    `${lines.join("\n")}\n`
);

// ---- 7. Summary --------------------------------------------------------------

console.log(`\n\x1b[1mdist/release-v${VERSION}/\x1b[0m`);
for (const name of readdirSync(OUT).sort()) {
  console.log(`  ${name.padEnd(40)} ${(statSync(join(OUT, name)).size / 1024).toFixed(0)} KB`);
}
console.log(`\nPASSED — ${readdirSync(OUT).length} artifacts built and verified from ${commit.slice(0, 12)}.\n`);
