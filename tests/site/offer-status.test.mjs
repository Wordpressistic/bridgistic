// node --test tests/site
import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { offerStatus, countFromTransactions } from "../../functions/api/offer-status.js";

const LTD = "pri_01m58k6epsqzbr3qbab84t6p90";
const BEFORE = Date.parse("2026-10-19T00:00:00Z");
const END_DHAKA = Date.parse("2026-12-31T17:59:59Z"); // 23:59:59 Asia/Dhaka
const txn = (q, { status = "completed", price = LTD, refunded = false } = {}) => ({
  status,
  details: {
    totals: { grand_total: String(2999 * q) },
    adjusted_totals: { grand_total: refunded ? "0" : String(2999 * q) },
    line_items: [{ price_id: price, quantity: q }],
  },
});
const paddle = (pages) => {
  let i = 0;
  return async (url, opts) => {
    assert.match(url, /^https:\/\/api\.paddle\.com\/transactions\?/);
    assert.match(opts.headers.Authorization, /^Bearer /);
    const data = pages[i++] || [];
    return new Response(JSON.stringify({ data, meta: { pagination: { has_more: i < pages.length, next: `https://api.paddle.com/transactions?after=p${i}` } } }));
  };
};

test("no API key: open by date only, no invented count", async () => {
  const s = await offerStatus({}, BEFORE);
  assert.equal(s.open, true);
  assert.equal(s.sold, null);
  assert.equal(s.remaining, null);
  assert.equal(s.source, "none");
});

test("closes after 31 Dec 2026 23:59:59 Asia/Dhaka", async () => {
  assert.equal((await offerStatus({}, END_DHAKA - 1000)).open, true);
  const s = await offerStatus({}, END_DHAKA + 1000);
  assert.equal(s.open, false);
  assert.equal(s.reason, "ended");
});

test("counts real LTD sales across pages, skipping refunds and other prices", async () => {
  const s = await offerStatus({ PADDLE_API_KEY: "k" }, BEFORE, paddle([[txn(1), txn(2), txn(1, { refunded: true })], [txn(1, { price: "pri_01m1ykar8aq033865h68a4b9hh" }), txn(3, { status: "paid" })]]));
  assert.equal(s.source, "paddle");
  assert.equal(s.sold, 6);
  assert.equal(s.remaining, 94);
  assert.equal(s.open, true);
});

test("closes when 100 are sold", async () => {
  const s = await offerStatus({ PADDLE_API_KEY: "k" }, BEFORE, paddle([[txn(60), txn(40)]]));
  assert.equal(s.open, false);
  assert.equal(s.reason, "sold_out");
  assert.equal(s.remaining, 0);
});

test("Paddle error: no number shown, falls back to date", async () => {
  const s = await offerStatus({ PADDLE_API_KEY: "k" }, BEFORE, async () => new Response("x", { status: 403 }));
  assert.equal(s.source, "error");
  assert.equal(s.remaining, null);
  assert.equal(s.open, true);
});

test("manual close switch", async () => {
  const s = await offerStatus({ OFFER_FORCE_CLOSED: "1" }, BEFORE);
  assert.equal(s.open, false);
});

test("countFromTransactions ignores unpaid", () => {
  assert.equal(countFromTransactions([txn(5, { status: "draft" }), txn(1)], LTD), 1);
});

test("landing page: real Paddle prices, offer terms, no exclamation marks, no secrets", () => {
  const html = readFileSync(new URL("../../index.html", import.meta.url), "utf8");
  for (const id of ["pri_01m58k6epsqzbr3qbab84t6p90", "pri_01m1ykar8aq033865h68a4b9hh", "pri_01m1ykaqyaaeewsbah07akmpka"]) assert.ok(html.includes(id), id);
  for (const t of ["$49", "$12", "$99", "31 December 2026", "100 licenses only"]) assert.ok(html.includes(t), t);
  const body = html.slice(html.indexOf("<body"));
  const text = body.replace(/<script[\s\S]*?<\/script>/g, "").replace(/<style[\s\S]*?<\/style>/g, "").replace(/<!--[\s\S]*?-->/g, "").replace(/<[^>]+>/g, " ");
  assert.equal(/!/.test(text), false, "visible text contains an exclamation mark");
  assert.equal(/pdl_(live|sdbx)_apikey_/.test(html), false);
  assert.equal(/["'](live|test)_[A-Za-z0-9]{10,}["']/.test(html), false, "client token must be injected at build time, not committed");
});
