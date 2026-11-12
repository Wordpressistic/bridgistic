#!/usr/bin/env node
/**
 * Builds the user-facing distribution zips into dist/:
 *
 *   dist/bridgistic-claude-package.zip     — docs, example configs, install
 *                                            scripts, bundled MCP server
 *   dist/bridgistic-wordpress-plugin.zip   — the WordPress plugin, ready to
 *                                            upload via Plugins → Add New
 *   dist/bridgistic-openai-plugin.zip      — official ChatGPT/Codex directory
 *                                            package with the remote MCP setup
 *
 * openai-plugin/ is optional: when it is absent (the repo refocused on the
 * WordPress plugin) the OpenAI package is skipped with a note.
 *
 * Run `npm run build` first so the server bundle exists (the package is
 * still produced without it, with a warning).
 */

import { existsSync, mkdirSync, readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { ZipWriter } from "./lib/zip.js";

const ROOT = join(fileURLToPath(import.meta.url), "..", "..");
const DIST = join(ROOT, "dist");
mkdirSync(DIST, { recursive: true });

function addTree(zip, dir, prefix, skip = new Set()) {
  for (const name of readdirSync(dir)) {
    if (skip.has(name) || name.startsWith(".")) continue; // hidden files are not permitted in a distribution zip
    const path = join(dir, name);
    if (statSync(path).isDirectory()) {
      addTree(zip, path, `${prefix}${name}/`, skip);
    } else {
      zip.addFile(`${prefix}${name}`, path);
    }
  }
}

function assertNoPhpBom(dir) {
  for (const name of readdirSync(dir)) {
    if (name.startsWith(".")) continue;
    const path = join(dir, name);
    if (statSync(path).isDirectory()) {
      assertNoPhpBom(path);
      continue;
    }
    if (!name.endsWith(".php")) continue;
    const bytes = readFileSync(path);
    if (bytes.length >= 3 && bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf) {
      throw new Error(`PHP file contains a UTF-8 BOM: ${relative(ROOT, path)}`);
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
  // The public ZIP is the free edition. Keep the premium showcase out of the
  // executable package: it must not carry the WPistic licensing SDK or an
  // activation screen that could be mistaken for a supported unlock path.
  const pluginDir = join(ROOT, "wordpress-plugin/bridgistic");
  assertNoPhpBom(pluginDir);
  addTree(
    zip,
    pluginDir,
    "bridgistic/",
    new Set(["tests", "sdk", "class-bridgistic-license-page.php", "license.php"])
  );
  const out = zip.write(join(DIST, "bridgistic-wordpress-plugin.zip"));
  console.log(`✓ ${relative(ROOT, out)} (${(statSync(out).size / 1024).toFixed(0)} KB)`);
}

// ---- 3. OpenAI plugin directory package -----------------------------------------

{
  const openaiPlugin = join(ROOT, "openai-plugin");
  if (existsSync(openaiPlugin)) {
    const zip = new ZipWriter();
    addTree(zip, openaiPlugin, "");
    const out = zip.write(join(DIST, "bridgistic-openai-plugin.zip"));
    console.log(`✓ ${relative(ROOT, out)} (${(statSync(out).size / 1024).toFixed(0)} KB)`);
  } else {
    console.log("openai-plugin/ not present, skipping — repo refocused on the WordPress plugin");
  }
}

console.log("Done.");
