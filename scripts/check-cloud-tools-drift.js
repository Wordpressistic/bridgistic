#!/usr/bin/env node
/**
 * cloud/src/tools/*.ts is a hand-synced copy of mcp-server/src/tools/*.ts
 * (see cloud/README.md's "Architecture" section) - the cloud Worker can't
 * import from mcp-server directly (different runtime, different bundler),
 * so the tool definitions are duplicated instead. Nothing enforced that the
 * copies stayed in sync; this script does, by byte-comparing every file
 * that exists on either side.
 *
 * Exits non-zero if any pair differs or either side has a file the other
 * doesn't. Run via `npm run check:cloud-drift`.
 */

import { readFileSync, readdirSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(fileURLToPath(import.meta.url), "..", "..");
const CLOUD_DIR = join(ROOT, "cloud", "src", "tools");
const MCP_DIR = join(ROOT, "mcp-server", "src", "tools");

function listTsFiles(dir) {
  return readdirSync(dir)
    .filter((f) => f.endsWith(".ts"))
    .sort();
}

const cloudFiles = new Set(listTsFiles(CLOUD_DIR));
const mcpFiles = new Set(listTsFiles(MCP_DIR));

let failures = 0;
const fail = (msg) => {
  failures++;
  console.error(`  ✗ ${msg}`);
};
const ok = (msg) => console.log(`  ✓ ${msg}`);

console.log("\nCloud/mcp-server tool file drift check");

for (const file of cloudFiles) {
  if (!mcpFiles.has(file)) {
    fail(`cloud/src/tools/${file} has no counterpart at mcp-server/src/tools/${file}`);
  }
}
for (const file of mcpFiles) {
  if (!cloudFiles.has(file)) {
    fail(`mcp-server/src/tools/${file} has no counterpart at cloud/src/tools/${file}`);
  }
}

for (const file of cloudFiles) {
  if (!mcpFiles.has(file)) continue;
  const cloudContent = readFileSync(join(CLOUD_DIR, file), "utf8");
  const mcpContent = readFileSync(join(MCP_DIR, file), "utf8");
  if (cloudContent === mcpContent) {
    ok(`${file} matches`);
  } else {
    fail(
      `${file} has diverged between cloud/src/tools/ and mcp-server/src/tools/ — ` +
        `copy whichever side has the intended change over the other (diff cloud/src/tools/${file} mcp-server/src/tools/${file})`
    );
  }
}

console.log("");
if (failures > 0) {
  console.error(`FAILED — ${failures} problem(s).\n`);
  process.exit(1);
} else {
  console.log(`PASSED — cloud and mcp-server tool files are in sync.\n`);
}
