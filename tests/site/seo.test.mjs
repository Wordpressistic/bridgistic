import test from "node:test";
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { mkdtempSync, readFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const root = new URL("../../", import.meta.url);
const read = (file) => readFileSync(new URL(file, root), "utf8");

test("feature guide and docs cite the site and do not invent an unlock", () => {
  const index = read("index.html");
  assert.match(index, /href="\/features"/);
  assert.match(index, /href="\/docs"/);
  assert.equal((index.match(/class="soon">Coming soon</g) || []).length, 12);
  for (const file of ["features.html", "docs.html"]) {
    const html = read(file);
    const text = html.replace(/<script[\s\S]*?<\/script>/g, "").replace(/<style[\s\S]*?<\/style>/g, "").replace(/<[^>]+>/g, " ");
    assert.equal(/!/.test(text), false, file);
    assert.match(html, /WordPressistic LLC/);
    assert.match(html, /https:\/\/bridgistic\.app\//);
    assert.match(html, /modelcontextprotocol\.io/);
  }
  assert.match(read("features.html"), /Coming soon/);
  assert.match(read("features.html"), /up to 3 enabled schedules|3 enabled schedules/);
  assert.match(read("docs.html"), /href="\/features"/);
});

test("homepage points at the sitemap and names the publisher", () => {
  const html = read("index.html");
  assert.match(html, /rel="sitemap"/);
  assert.match(html, /href="https:\/\/bridgistic\.app\/llms\.txt"/);
  assert.match(html, /id="publisher"/);
  assert.match(html, /WordPressistic LLC/);
  assert.match(html, /1209 Mountain Road PL NE, STE N/);
  assert.equal((html.match(/href="\/sitemap\.html"/g) || []).length >= 2, true);
  const body = html.slice(html.indexOf("<body"));
  const text = body.replace(/<script[\s\S]*?<\/script>/g, "").replace(/<style[\s\S]*?<\/style>/g, "").replace(/<!--[\s\S]*?-->/g, "").replace(/<[^>]+>/g, " ");
  assert.equal(/!/.test(text), false);
});

test("legal pages link the HTML sitemap", () => {
  for (const file of ["terms.html", "privacy.html", "refund-policy.html", "how-purchases-work.html"]) {
    assert.match(read(file), /href="\/sitemap\.html"/);
  }
});

test("site build regenerates the crawl files", () => {
  assert.match(read("scripts/build-site.sh"), /node scripts\/build-seo\.mjs/);
  const out = mkdtempSync(join(tmpdir(), "bridgistic-seo-"));
  execFileSync("node", ["scripts/build-seo.mjs", "--out", out], { cwd: new URL(".", root) });
  const again = mkdtempSync(join(tmpdir(), "bridgistic-seo-"));
  execFileSync("node", ["scripts/build-seo.mjs", "--out", again], { cwd: new URL(".", root) });
  for (const file of ["sitemap.xml", "sitemap.html", "robots.txt", "llms.txt", "llms-full.txt"]) {
    const a = readFileSync(join(out, file), "utf8");
    assert.equal(readFileSync(join(again, file), "utf8"), a, `${file} is deterministic`);
    assert.equal(read(file), a, `${file} in the repo matches the generator`);
  }
  const xml = read("sitemap.xml");
  for (const loc of ["https://bridgistic.app/", "https://bridgistic.app/features", "https://bridgistic.app/docs", "https://bridgistic.app/terms", "https://bridgistic.app/privacy", "https://bridgistic.app/refund-policy", "https://bridgistic.app/how-purchases-work", "https://bridgistic.app/sitemap.html"]) {
    assert.ok(xml.includes(`<loc>${loc}</loc>`), loc);
  }
  assert.equal(xml.includes("/#"), false, "XML sitemap has no fragment URLs");
  const robots = read("robots.txt");
  assert.match(robots, /Sitemap: https:\/\/bridgistic\.app\/sitemap\.xml/);
  for (const bot of ["Googlebot", "Google-Extended", "Bingbot", "GPTBot", "OAI-SearchBot", "ClaudeBot", "PerplexityBot"]) {
    assert.match(robots, new RegExp(`User-agent: ${bot}`));
  }
  assert.match(robots, /Disallow: \/api\//);
  const llms = read("llms.txt");
  assert.match(llms, /\$49/);
  assert.match(llms, /\$12 USD per month/);
  assert.match(llms, /\$99 USD per year/);
  assert.match(llms, /7-day refund guarantee/);
  assert.match(read("llms-full.txt"), /What exactly does Bridgistic do\?/);
  const visible = read("sitemap.html").replace(/<script[\s\S]*?<\/script>/g, "").replace(/<[^>]+>/g, " ");
  assert.equal(/!/.test(visible), false);
  assert.equal(/!/.test(llms), false);
});
