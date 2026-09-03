#!/usr/bin/env node
/**
 * Unpacks each built artifact and checks it is fit to hand a user.
 *
 * Building a zip proves the zip writer works, not that the contents are
 * correct. The failures this catches are the ones that only show up after
 * publication: a WordPress ZIP with a nested `bridgistic/bridgistic/` root
 * that installs to the wrong path, a stray `.env` or `node_modules` swept in
 * by a directory walk, a plugin header still reporting the previous version,
 * or a bundle that never got rebuilt.
 *
 * Run: node scripts/verify-packages.js [--dir dist]
 */

import { execFileSync } from "node:child_process";
import { existsSync, mkdtempSync, readFileSync, readdirSync, rmSync, statSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(fileURLToPath(import.meta.url), "..", "..");

const dirFlag = process.argv.indexOf("--dir");
const DIST = join(ROOT, dirFlag > -1 ? process.argv[dirFlag + 1] : "dist");

const EXPECTED_VERSION = JSON.parse(readFileSync(join(ROOT, "package.json"), "utf8")).version;

let failures = 0;
const ok = (msg) => console.log(`  ✓ ${msg}`);
const fail = (msg) => {
  failures++;
  console.error(`  ✗ ${msg}`);
};

/**
 * Files and directories that must never reach a user: build inputs, caches,
 * local environment, and anything credential-shaped.
 */
const FORBIDDEN_ENTRIES = [
  "node_modules",
  ".env",
  ".env.local",
  ".env.production",
  ".git",
  ".github",
  ".DS_Store",
  "Thumbs.db",
  ".idea",
  ".vscode",
  "coverage",
  ".nyc_output",
  ".turbo",
  "package-lock.json",
  "tsconfig.tsbuildinfo",
];

const FORBIDDEN_PATTERNS = [
  { re: /(^|\/)\.env($|\.)/, label: "an environment file" },
  { re: /(^|\/)node_modules\//, label: "bundled node_modules" },
  { re: /\.(pem|key|p12|pfx)$/i, label: "a private key" },
  { re: /(^|\/)\.git\//, label: "git metadata" },
  { re: /\.(log|tmp|swp|orig|rej)$/i, label: "a scratch file" },
  { re: /(^|\/)__MACOSX\//, label: "macOS archive cruft" },
];

/** Credential-shaped strings that must never appear inside a shipped file. */
const SECRET_PATTERNS = [
  { re: /wps_[0-9a-f]{48}/, label: "a Bridgistic key secret" },
  { re: /-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----/, label: "a private key" },
  { re: /ghp_[A-Za-z0-9]{36}/, label: "a GitHub token" },
  { re: /npm_[A-Za-z0-9]{36}/, label: "an npm token" },
  { re: /sk-ant-[A-Za-z0-9-_]{20,}/, label: "an Anthropic API key" },
];

function unzip(archive) {
  const dir = mkdtempSync(join(tmpdir(), "bridgistic-verify-"));
  execFileSync("unzip", ["-q", archive, "-d", dir]);
  return dir;
}

function walk(dir, prefix = "") {
  const out = [];
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    const rel = `${prefix}${name}`;
    if (statSync(path).isDirectory()) {
      out.push({ rel: `${rel}/`, path, dir: true });
      out.push(...walk(path, `${rel}/`));
    } else {
      out.push({ rel, path, dir: false });
    }
  }
  return out;
}

function checkNoJunk(label, entries) {
  for (const entry of entries) {
    const base = entry.rel.replace(/\/$/, "").split("/").pop();
    if (FORBIDDEN_ENTRIES.includes(base)) {
      fail(`${label}: contains ${entry.rel}`);
    }
    for (const { re, pattern, label: why } of FORBIDDEN_PATTERNS) {
      if ((re ?? pattern).test(entry.rel)) fail(`${label}: contains ${why} (${entry.rel})`);
    }
  }
}

function checkNoSecrets(label, entries) {
  const textLike = /\.(js|mjs|cjs|ts|json|php|md|txt|sh|ps1|ya?ml|css|html|toml|example)$/i;
  for (const entry of entries) {
    if (entry.dir || !textLike.test(entry.rel)) continue;
    if (statSync(entry.path).size > 8 * 1024 * 1024) continue;
    const content = readFileSync(entry.path, "utf8");
    for (const { re, label: why } of SECRET_PATTERNS) {
      if (re.test(content)) fail(`${label}: ${why} appears in ${entry.rel}`);
    }
  }
}

function withArchive(name, run) {
  const archive = join(DIST, name);
  if (!existsSync(archive)) {
    fail(`missing artifact: ${relative(ROOT, archive)}`);
    return;
  }
  const dir = unzip(archive);
  try {
    run(walk(dir), dir);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

// ---- 1. WordPress plugin ZIP ------------------------------------------------

console.log("\nbridgistic-wordpress-plugin.zip");
withArchive("bridgistic-wordpress-plugin.zip", (entries, dir) => {
  const roots = readdirSync(dir);
  if (roots.length === 1 && roots[0] === "bridgistic") {
    ok("exactly one root folder, named bridgistic/");
  } else {
    fail(`root should be exactly ["bridgistic"], got ${JSON.stringify(roots)}`);
  }

  // WordPress installs the ZIP's root folder into wp-content/plugins/. A
  // nested bridgistic/bridgistic/ produces a plugin nobody can activate.
  if (existsSync(join(dir, "bridgistic", "bridgistic"))) {
    fail("nested bridgistic/bridgistic/ folder — WordPress would install this to the wrong path");
  } else {
    ok("no nested duplicate plugin folder");
  }

  for (const required of [
    "bridgistic/bridgistic.php",
    "bridgistic/readme.txt",
    "bridgistic/uninstall.php",
    "bridgistic/includes/class-plugin.php",
    "bridgistic/includes/rest/class-router.php",
    "bridgistic/includes/rest/class-woo-controller.php",
    "bridgistic/includes/security/class-hmac-verifier.php",
    "bridgistic/includes/security/class-sql-classifier.php",
    "bridgistic/admin/class-bridgistic-admin.php",
    "bridgistic/assets/admin/css/bridgistic-admin.css",
    "bridgistic/assets/admin/js/bridgistic-admin.js",
  ]) {
    if (existsSync(join(dir, required))) ok(`contains ${required}`);
    else fail(`missing ${required}`);
  }

  // Tests are development-only; the prompt's rule is that test-only files do
  // not belong in the public plugin ZIP.
  if (entries.some((e) => e.rel.startsWith("bridgistic/tests/"))) {
    fail("the test suite was shipped in the plugin ZIP");
  } else {
    ok("no test files shipped");
  }

  if (entries.some((e) => e.rel.endsWith("composer.json") && e.rel.includes("vendor/"))) {
    fail("vendor/ directory shipped");
  }

  const header = readFileSync(join(dir, "bridgistic/bridgistic.php"), "utf8");
  const headerVersion = header.match(/^\s*\*\s*Version:\s*(\S+)\s*$/m)?.[1];
  const constVersion = header.match(/define\(\s*'BRIDGISTIC_VERSION',\s*'([^']+)'\s*\)/)?.[1];
  if (headerVersion === EXPECTED_VERSION && constVersion === EXPECTED_VERSION) {
    ok(`plugin header and BRIDGISTIC_VERSION both report ${EXPECTED_VERSION}`);
  } else {
    fail(`version mismatch in the shipped plugin: header=${headerVersion}, constant=${constVersion}, expected ${EXPECTED_VERSION}`);
  }

  const readme = readFileSync(join(dir, "bridgistic/readme.txt"), "utf8");
  if (new RegExp(`^Stable tag:\\s*${EXPECTED_VERSION.replace(/\./g, "\\.")}\\s*$`, "m").test(readme)) {
    ok(`readme.txt Stable tag is ${EXPECTED_VERSION}`);
  } else {
    fail(`readme.txt Stable tag is not ${EXPECTED_VERSION}`);
  }

  checkNoJunk("wordpress-plugin.zip", entries);
  checkNoSecrets("wordpress-plugin.zip", entries);
  ok(`${entries.filter((e) => !e.dir).length} files, no dev junk or secrets`);
});

// ---- 2. Claude setup package ------------------------------------------------

console.log("\nbridgistic-claude-package.zip");
withArchive("bridgistic-claude-package.zip", (entries, dir) => {
  for (const required of ["server/index.js", "TROUBLESHOOTING.md", "README.md", "LICENSE", "docs/INSTALL.md"]) {
    if (existsSync(join(dir, required))) ok(`contains ${required}`);
    else fail(`missing ${required}`);
  }

  const bundle = join(dir, "server/index.js");
  if (existsSync(bundle)) {
    const size = statSync(bundle).size;
    if (size > 50_000) ok(`bundled server present (${Math.round(size / 1024)} KB, dependencies inlined)`);
    else fail(`bundled server is only ${size} bytes — dependencies were not inlined`);
  }

  checkNoJunk("claude-package.zip", entries);
  checkNoSecrets("claude-package.zip", entries);
  ok(`${entries.filter((e) => !e.dir).length} files, no dev junk or secrets`);
});

// ---- 3. Claude Desktop extension --------------------------------------------

console.log("\nbridgistic.mcpb");
withArchive("bridgistic.mcpb", (entries, dir) => {
  const manifestPath = join(dir, "manifest.json");
  if (!existsSync(manifestPath)) {
    fail("missing manifest.json");
    return;
  }
  const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));
  if (manifest.version === EXPECTED_VERSION) ok(`manifest reports ${EXPECTED_VERSION}`);
  else fail(`manifest reports ${manifest.version}, expected ${EXPECTED_VERSION}`);

  if (existsSync(join(dir, manifest.server?.entry_point ?? ""))) {
    ok(`entry point present at ${manifest.server.entry_point}`);
  } else {
    fail(`entry point ${manifest.server?.entry_point} missing from the extension`);
  }

  if (manifest.user_config?.key_secret?.sensitive === true) {
    ok("the key secret field is marked sensitive, so Claude Desktop stores it securely");
  } else {
    fail("user_config.key_secret must be sensitive: true");
  }

  checkNoJunk("bridgistic.mcpb", entries);
  checkNoSecrets("bridgistic.mcpb", entries);
  ok(`${entries.filter((e) => !e.dir).length} files, no dev junk or secrets`);
});

// ---- verdict -----------------------------------------------------------------

console.log("");
if (failures) {
  console.error(`FAILED — ${failures} package problem(s).\n`);
  process.exit(1);
}
console.log("PASSED — every artifact unpacks to the expected structure.\n");
