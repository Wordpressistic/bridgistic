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
    assert.match(html, /mailto:support@wpistic\.com/, "support and refunds go to support@wpistic.com");
    assert.ok(/https:\/\/wpistic\.com\/(terms|privacy|refund-policy)/.test(html), "links a WPistic legal page");
    assert.ok(!text.includes("!"), "no exclamation marks");
    assert.ok(!/pdl_|live_[A-Za-z0-9]{8}|apikey/i.test(html), "no tokens or keys");
    assert.ok(html.includes('href="/assets/legal.css"'));
    assert.ok(!/Pending Shuvo review|class="pending"|PENDING SHUVO/i.test(html), "all review markers resolved");
  });
}

test("legal terms match Shuvo's answers (19 Oct 2026)", () => {
  const terms = visible(read("terms.html"));
  const refund = visible(read("refund-policy.html"));
  const privacy = visible(read("privacy.html"));
  const all = PAGES.map((f) => visible(read(f))).join("\n");
  assert.match(terms, /governed by the laws of the United States and the State of New Mexico/);
  assert.ok(!/arbitrat|venue|exclusive jurisdiction|courts of/i.test(all), "no venue or arbitration clause");
  assert.match(terms, /WPistic Terms prevail/);
  assert.ok(read("terms.html").includes('href="https://www.paddle.com/legal/invoiced-consumer-terms"'), "links Paddle Buyer Terms");
  assert.match(terms, /Lifetime Deal: 1 license · 1 site\. Monthly and Yearly: 1 license · 5 sites\./);
  assert.match(terms, /available on GitHub/);
  assert.match(refund, /7-day refund guarantee/);
  assert.match(refund, /within 7 days of your purchase/);
  assert.match(refund, /not always immediate/, "revocation is not promised as instant");
  assert.ok(!/instant(ly)? revo/i.test(all));
  assert.match(all, /30 days of a new annual billing cycle/, "annual window from the WPistic policy kept");
  assert.match(privacy, /about 60 days/);
  assert.match(privacy, /Snapshots are stored only in your own WordPress database/);
  for (const v of ["Paddle", "Cloudflare", "Hostinger"]) assert.match(privacy, new RegExp(v));
  assert.ok((read("privacy.html").match(/class="verified"/g) || []).length >= 3, "providers marked verified");
});

test("license lines are word for word the same on pricing, terms and how-purchases-work", () => {
  const index = read("index.html");
  assert.equal((index.match(/<strong>1 license · 1 site<\/strong>/g) || []).length, 1, "LTD card");
  assert.equal((index.match(/<strong>1 license · 5 sites<\/strong>/g) || []).length, 2, "monthly and yearly cards");
  for (const f of ["terms.html", "how-purchases-work.html"]) {
    const t = visible(read(f));
    assert.match(t, /1 license · 1 site/, f);
    assert.match(t, /1 license · 5 sites/, f);
  }
  const ltd = index.slice(index.indexOf('id="welcome-offer"'), index.indexOf('class="offer-side"'));
  assert.match(ltd, /All future paid features/);
  assert.equal((index.match(/class="soon">Coming soon</g) || []).length, 6, "white-label and Cloud Agent on each of 3 paid cards");
  assert.match(visible(index), /7-day refund guarantee/);
  assert.ok(!/website connection/.test(visible(index)), "old wording removed");
});

test("refund guarantee is 7 days everywhere (Shuvo, 19 Oct 2026 09:07 Asia/Dhaka)", () => {
  for (const f of ["index.html", ...PAGES]) {
    const t = visible(read(f));
    assert.ok(!/14[- ]day|14 days|fourteen/i.test(t), `${f}: no 14-day wording`);
  }
  assert.match(visible(read("index.html")), /Every purchase has a 7-day refund guarantee\./);
  assert.match(visible(read("how-purchases-work.html")), /Every purchase has a 7-day refund guarantee\./);
  assert.match(visible(read("refund-policy.html")), /within 7 days of your purchase/);
});
