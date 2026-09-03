#!/usr/bin/env node
/**
 * `php -l` across the WordPress plugin.
 *
 * A shell one-liner with find/xargs was fine until it needed to live in a
 * package.json script, where the quoting has to survive both JSON escaping and
 * whatever shell npm picks — and where a non-zero exit from xargs is easy to
 * swallow. This does the same job portably and reports every failing file
 * rather than stopping at the first.
 *
 * Run: npm run lint:php
 */

import { execFileSync } from "node:child_process";
import { readdirSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(fileURLToPath(import.meta.url), "..", "..");
const TARGET = join(ROOT, "wordpress-plugin");

function phpFiles(dir) {
  const found = [];
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) {
      found.push(...phpFiles(path));
    } else if (name.endsWith(".php")) {
      found.push(path);
    }
  }
  return found;
}

const files = phpFiles(TARGET);
const failures = [];

for (const file of files) {
  try {
    execFileSync("php", ["-l", file], { stdio: "pipe" });
  } catch (err) {
    failures.push({ file: relative(ROOT, file), output: String(err.stdout || err.message).trim() });
  }
}

if (failures.length) {
  for (const failure of failures) {
    console.error(`✗ ${failure.file}\n  ${failure.output}`);
  }
  console.error(`\nFAILED — ${failures.length} of ${files.length} PHP files have syntax errors.`);
  process.exit(1);
}

console.log(`PASS  php -l — ${files.length} files lint clean`);
