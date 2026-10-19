import test from "node:test";
import assert from "node:assert/strict";
import { onRequest } from "../../functions/_middleware.js";

const run = (u) => onRequest({ request: new Request(u), next: () => new Response("next", { status: 200 }) });

test("www 301s to the apex with path and query", async () => {
  const r = await run("https://www.bridgistic.app/terms?x=1");
  assert.equal(r.status, 301);
  assert.equal(r.headers.get("location"), "https://bridgistic.app/terms?x=1");
});
test("apex and previews pass through", async () => {
  for (const u of ["https://bridgistic.app/", "https://staging.bridgistic-site.pages.dev/terms"]) assert.equal((await run(u)).status, 200);
});
