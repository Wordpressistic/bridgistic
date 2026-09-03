#!/usr/bin/env node
/**
 * Builds the user-facing distribution zips into dist/:
 *
 *   dist/bridgistic-claude-package.zip     — docs, example configs, install
 *                                            scripts, bundled MCP server
 *   dist/bridgistic-wordpress-plugin.zip   — the WordPress plugin, ready to
 *                                            upload via Plugins → Add New
 *
 * Run `npm run build` first so the server bundle exists (the package is
 * still produced without it, with a warning).
 */

import { existsSync, mkdirSync, readdirSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { ZipWriter } from "./lib/zip.js";

const ROOT = join(fileURLToPath(import.meta.url), "..", "..");
const DIST = join(ROOT, "dist");
mkdirSync(DIST, { recursive: true });

function addTree(zip, dir, prefix, skip = new Set()) {
  for (const name of readdirSync(dir)) {
    if (skip.has(name)) continue;
    const path = join(dir, name);
    if (statSync(path).isDirectory()) {
      addTree(zip, path, `${prefix}${name}/`, skip);
    } else {
      zip.addFile(`${prefix}${name}`, path);
    }
  }
}

// ---- 1. Claude setup package -----------------------------------------------------

{
  const zip = new ZipWriter();

  // Setup package files (configs, install scripts, troubleshooting).
  addTree(zip, join(ROOT, "plugins/bridgistic/package"), "");

  // Docs.
  addTree(zip, join(ROOT, "docs"), "docs/");
  zip.addFile("README.md", join(ROOT, "README.md"));
  zip.addFile("LICENSE", join(ROOT, "LICENSE"));

  // Pre-built MCP server (single file, no npm install needed).
  const bundle = join(ROOT, "plugins/bridgistic/server/index.js");
  if (existsSync(bundle)) {
    zip.addFile("server/index.js", bundle);
    zip.addFile("server/package.json", join(ROOT, "plugins/bridgistic/server/package.json"));
    zip.addString(
      "server/README.md",
      "# Bridgistic MCP server (pre-built)\n\nSelf-contained bundle — run with Node.js 20+:\n\n    node server/index.js\n\nPoint your Claude Desktop / Claude Code config at this file and set\nBRIDGISTIC_SITE_URL, BRIDGISTIC_KEY_ID, BRIDGISTIC_KEY_SECRET in its env.\nSource: https://github.com/wordpressistic/bridgistic\n"
    );
  } else {
    console.warn("⚠ server bundle missing (run `npm run build`) — package built without it");
  }

  const out = zip.write(join(DIST, "bridgistic-claude-package.zip"));
  console.log(`✓ ${relative(ROOT, out)} (${(statSync(out).size / 1024).toFixed(0)} KB)`);
}

// ---- 2. WordPress plugin zip --------------------------------------------------------

{
  const zip = new ZipWriter();
  addTree(zip, join(ROOT, "wordpress-plugin/bridgistic"), "bridgistic/", new Set(["tests"]));
  const out = zip.write(join(DIST, "bridgistic-wordpress-plugin.zip"));
  console.log(`✓ ${relative(ROOT, out)} (${(statSync(out).size / 1024).toFixed(0)} KB)`);
}

console.log("Done.");
