#!/usr/bin/env node
/**
 * Bridgistic MCP — integration test (no WordPress required).
 *
 * Stands up a mock "bridge" HTTP server that re-verifies the HMAC signature
 * exactly like the PHP plugin, records the method/route/body it received, and
 * returns a canned envelope. The real MCP server is pointed at it and driven
 * through genuine `tools/call` requests. This proves end-to-end that tools sign
 * correctly, hit the right route + method, forward guard params, and that
 * DELETE carries a signed body.
 *
 * Usage: node evals/integration.test.mjs
 */

import { spawn } from "node:child_process";
import http from "node:http";
import crypto from "node:crypto";
import assert from "node:assert/strict";
import { fileURLToPath } from "node:url";
import path from "node:path";

const here = path.dirname(fileURLToPath(import.meta.url));
const serverEntry = path.join(here, "..", "dist", "index.js");
const KEY_ID = "k_test";
const SECRET = "s_test_secret_value_1234567890";

// ---- mock bridge (verifies HMAC like the PHP plugin) ----------------------
const received = [];
function verify(method, urlPath, headers, body) {
  const ts = headers["x-bridgistic-timestamp"];
  const nonce = headers["x-bridgistic-nonce"];
  const given = headers["x-bridgistic-signature"];
  const key = headers["x-bridgistic-key"];
  if (key !== KEY_ID || !ts || !nonce || !given) return false;
  const bodyHash = crypto.createHash("sha256").update(body, "utf8").digest("hex");
  const canonical = [method, urlPath, ts, nonce, bodyHash].join("\n");
  const expect = crypto.createHmac("sha256", SECRET).update(canonical, "utf8").digest("hex");
  return expect.length === given.length && crypto.timingSafeEqual(Buffer.from(expect), Buffer.from(given));
}

const mock = http.createServer((req, res) => {
  let body = "";
  req.on("data", (c) => (body += c));
  req.on("end", () => {
    const u = new URL(req.url, "http://localhost");
    const routePath = u.pathname; // e.g. /wp-json/bridgistic/v1/posts
    const signedPath = routePath.replace("/wp-json", ""); // /bridgistic/v1/posts
    const headers = req.headers;
    const ok = verify(req.method, signedPath, headers, body);
    received.push({
      method: req.method,
      route: signedPath.replace("/bridgistic/v1/", ""),
      query: Object.fromEntries(u.searchParams),
      body: body ? JSON.parse(body) : null,
      signed: ok,
    });
    res.setHeader("Content-Type", "application/json");
    if (!ok) {
      res.statusCode = 401;
      res.end(JSON.stringify({ code: "auth", message: "bad signature" }));
      return;
    }
    res.statusCode = 200;
    res.end(JSON.stringify({ ok: true, data: { echo: signedPath, method: req.method } }));
  });
});

const port = await new Promise((r) => mock.listen(0, () => r(mock.address().port)));

// ---- MCP server over stdio ------------------------------------------------
const env = {
  ...process.env,
  WP_SITE_URL: `http://127.0.0.1:${port}`,
  BRIDGISTIC_KEY_ID: KEY_ID,
  BRIDGISTIC_KEY_SECRET: SECRET,
};
const child = spawn("node", [serverEntry], { env, stdio: ["pipe", "pipe", "pipe"] });
const pending = new Map();
let buf = "";
child.stdout.on("data", (d) => {
  buf += d.toString();
  let i;
  while ((i = buf.indexOf("\n")) >= 0) {
    const line = buf.slice(0, i).trim(); buf = buf.slice(i + 1);
    if (!line) continue;
    let m; try { m = JSON.parse(line); } catch { continue; }
    if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
  }
});
child.stderr.on("data", () => {});

let id = 1;
const send = (o) => child.stdin.write(JSON.stringify(o) + "\n");
const rpc = (method, params) =>
  new Promise((resolve, reject) => {
    const myId = ++id;
    pending.set(myId, resolve);
    send({ jsonrpc: "2.0", id: myId, method, params });
    setTimeout(() => reject(new Error(`rpc timeout: ${method}`)), 5000);
  });

send({ jsonrpc: "2.0", id: 1, method: "initialize", params: { protocolVersion: "2024-11-05", capabilities: {}, clientInfo: { name: "it", version: "0" } } });
await new Promise((r) => setTimeout(r, 250));
send({ jsonrpc: "2.0", method: "notifications/initialized" });
await new Promise((r) => setTimeout(r, 150));

const call = (name, args) => rpc("tools/call", { name, arguments: args });

let checks = 0;
function expectCall(idx, { method, route }) {
  const got = received[idx];
  assert.ok(got, `no request recorded at #${idx}`);
  assert.ok(got.signed, `request #${idx} (${got.route}) failed HMAC verification`);
  assert.equal(got.method, method, `#${idx} method ${got.method} != ${method}`);
  assert.equal(got.route.split("?")[0], route, `#${idx} route ${got.route} != ${route}`);
  checks += 3;
}

// 1. GET read → correct route + method, signed.
await call("bridgistic_get_site_info", {});
expectCall(0, { method: "GET", route: "site-info" });

// 2. POST create → body forwarded, guard param forwarded.
await call("bridgistic_create_post", { title: "Hello", status: "draft", dry_run: true });
expectCall(1, { method: "POST", route: "posts" });
assert.equal(received[1].body.title, "Hello", "create_post title not forwarded"); checks++;
assert.equal(received[1].body.dry_run, true, "dry_run guard param not forwarded"); checks++;

// 3. POST update with id in route.
await call("bridgistic_update_post", { id: 42, content: "x" });
expectCall(2, { method: "POST", route: "posts/42" });

// 4. DELETE carries a signed body (the hard case).
await call("bridgistic_delete_post", { id: 7, permanent: true });
expectCall(3, { method: "DELETE", route: "posts/7" });
assert.equal(received[3].body.permanent, true, "DELETE body not forwarded/signed"); checks++;

// 5. GET option with query string.
await call("bridgistic_get_option", { name: "blogname" });
expectCall(4, { method: "GET", route: "options" });
assert.equal(received[4].query.name, "blogname", "option name query not forwarded"); checks++;

// 6. Playbook run routes to playbooks/run.
await call("bridgistic_playbook_run", { slug: "demo", vars: { title: "Z" } });
expectCall(5, { method: "POST", route: "playbooks/run" });

// 7. Schedule create routes to schedules.
await call("bridgistic_schedule_create", { playbook: "demo", recurrence: "daily" });
expectCall(6, { method: "POST", route: "schedules" });

// ---- WooCommerce -----------------------------------------------------------
// These route to woo/* rather than being expressed as SQL, which is the whole
// point of the toolset: a store question must never need db:read.

// 8. Product list filters travel as query params, not as a body.
await call("bridgistic_woo_list_products", { stock_status: "outofstock", per_page: 5, search: "mug" });
expectCall(7, { method: "GET", route: "woo/products" });
assert.equal(received[7].query.stock_status, "outofstock", "product stock filter not forwarded"); checks++;
assert.equal(received[7].query.per_page, "5", "product per_page not forwarded"); checks++;
assert.equal(received[7].query.search, "mug", "product search not forwarded"); checks++;

// 9. Undefined optional params must be omitted entirely — sending `status=undefined`
// would be read by WordPress as a real filter and return nothing.
assert.equal("status" in received[7].query, false, "undefined optional param leaked into the query string"); checks++;

// 10. Single product by id.
await call("bridgistic_woo_get_product", { id: 99 });
expectCall(8, { method: "GET", route: "woo/products/99" });

// 11. Product create forwards fields and guard params.
await call("bridgistic_woo_create_product", { name: "Test Mug", regular_price: "19.99", dry_run: true });
expectCall(9, { method: "POST", route: "woo/products" });
assert.equal(received[9].body.name, "Test Mug", "product name not forwarded"); checks++;
assert.equal(received[9].body.regular_price, "19.99", "product price not forwarded"); checks++;
assert.equal(received[9].body.dry_run, true, "product create dry_run not forwarded"); checks++;

// 12. Product update targets the id route and does not resend it in the body.
await call("bridgistic_woo_update_product", { id: 12, stock_quantity: 3 });
expectCall(10, { method: "POST", route: "woo/products/12" });
assert.equal(received[10].body.stock_quantity, 3, "stock_quantity not forwarded"); checks++;
assert.equal("id" in received[10].body, false, "id belongs in the route, not the body"); checks++;

// 13. Order list.
await call("bridgistic_woo_list_orders", { status: "processing,completed", after: "2026-01-01" });
expectCall(11, { method: "GET", route: "woo/orders" });
assert.equal(received[11].query.status, "processing,completed", "order status filter not forwarded"); checks++;
assert.equal(received[11].query.after, "2026-01-01", "order after filter not forwarded"); checks++;

// 14. Single order.
await call("bridgistic_woo_get_order", { id: 501 });
expectCall(12, { method: "GET", route: "woo/orders/501" });

// 15. Order status change hits the dedicated sub-route and carries guard params.
await call("bridgistic_woo_update_order_status", { id: 501, status: "completed", note: "shipped", dry_run: true });
expectCall(13, { method: "POST", route: "woo/orders/501/status" });
assert.equal(received[13].body.status, "completed", "order status not forwarded"); checks++;
assert.equal(received[13].body.note, "shipped", "order note not forwarded"); checks++;
assert.equal(received[13].body.dry_run, true, "order status dry_run not forwarded"); checks++;

// 16. Customers.
await call("bridgistic_woo_list_customers", { search: "ada@example.com" });
expectCall(14, { method: "GET", route: "woo/customers" });
await call("bridgistic_woo_get_customer", { id: 3 });
expectCall(15, { method: "GET", route: "woo/customers/3" });

// 17. Analytics.
await call("bridgistic_woo_inventory_status", { low_stock_threshold: 4 });
expectCall(16, { method: "GET", route: "woo/inventory" });
assert.equal(received[16].query.low_stock_threshold, "4", "low stock threshold not forwarded"); checks++;

await call("bridgistic_woo_sales_summary", { days: 7 });
expectCall(17, { method: "GET", route: "woo/sales-summary" });
assert.equal(received[17].query.days, "7", "sales summary window not forwarded"); checks++;

child.kill();
mock.close();
console.log(`PASS  integration — ${received.length} signed calls, ${checks} assertions`);
