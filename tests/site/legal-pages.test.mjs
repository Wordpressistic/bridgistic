// node --test tests/site
import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const PAGES = ["terms.html", "privacy.html", "refund-policy.html", "how-purchases-work.html"];
const read = (f) => readFileSync(new URL(`../../${f}`, import.meta.url), "utf8");
const visible = (html) => html.replace(/<script[\s\S]*?<\/script>/g, "").replace(/<!--[\s\S]*?-->/g, "").replace(/<[^>]+>/g, " ");

test("footer links to every legal page", () => {
  const index = read("index.html");
  for (const href of ["/terms", "/privacy", "/refund-policy", "/how-purchases-work"]) {
    assert.ok(index.includes(`href="${href}"`), `footer links ${href}`);
  }
  assert.ok(!index.includes("Publishing soon"), "placeholder text removed");
});

for (const f of PAGES) {
  test(`${f}: company, WPistic links, no hype, no secrets`, () => {
    const html = read(f);
    const text = visible(html);
    assert.match(text, /WordPressistic LLC/);
    assert.match(text, /1209 Mountain Road PL NE, STE N/);
    assert.match(text, /Albuquerque, NM 87110, US/);
    assert.match(html, /mailto:hello@insightistic\.com/);
    assert.ok(/https:\/\/wpistic\.com\/(terms|privacy|refund-policy)/.test(html), "links a WPistic legal page");
    assert.ok(!text.includes("!"), "no exclamation marks");
    assert.ok(!/pdl_|live_[A-Za-z0-9]{8}|apikey/i.test(html), "no tokens or keys");
    assert.ok(html.includes('href="/assets/legal.css"'));
    // Every "Pending Shuvo review" badge has a matching source comment.
    const badges = (html.match(/class="pending"/g) || []).length;
    const notes = (html.match(/PENDING SHUVO REVIEW:/g) || []).length;
    assert.equal(badges, notes);
  });
}

test("no invented legal terms", () => {
  const all = PAGES.map((f) => visible(read(f))).join("\n");
  assert.ok(!/New Mexico law|laws of the State|governed by the laws of New Mexico/i.test(all), "no governing law invented");
  assert.ok(!/\b(14|60|90)[- ]day (refund|money[- ]back)/i.test(all), "no invented refund window");
  assert.match(all, /30 days of a new annual billing cycle/, "annual refund window taken from the WPistic policy");
});
