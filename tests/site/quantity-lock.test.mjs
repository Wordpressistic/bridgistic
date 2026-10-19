import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
const html = readFileSync(new URL("../../index.html", import.meta.url), "utf8");
test("every Paddle checkout opens with quantity 1 and resets any change back to 1", () => {
  const opens = html.match(/Checkout\.open\(\{[\s\S]*?\}\);/g) || [];
  assert.ok(opens.length >= 1);
  for (const o of opens) assert.match(o, /items: \[\{ priceId: priceId, quantity: 1 \}\]/);
  assert.match(html, /Initialize\(\{ token: CFG\.token, eventCallback: lockQuantity \}\)/);
  assert.match(html, /Checkout\.updateItems\(\[\{ priceId: openPriceId, quantity: 1 \}\]\)/);
});
test("lockQuantity resets a changed quantity", () => {
  const src = html.match(/function lockQuantity\(ev\) \{[\s\S]*?\n    \}/)[0];
  const calls = [];
  const window = { Paddle: { Checkout: { updateItems: (i) => calls.push(i) } } };
  const fn = new Function("window", "openPriceId", `${src}; return lockQuantity;`)(window, "pri_x");
  fn({ name: "checkout.items.updated", data: { items: [{ price_id: "pri_x", quantity: 3 }] } });
  fn({ name: "checkout.items.updated", data: { items: [{ price_id: "pri_x", quantity: 1 }] } });
  fn({ name: "checkout.updated", data: { items: [{ price_id: "pri_x", quantity: 1 }, { price_id: "pri_y", quantity: 1 }] } });
  assert.deepEqual(calls, [[{ priceId: "pri_x", quantity: 1 }], [{ priceId: "pri_x", quantity: 1 }]]);
});
