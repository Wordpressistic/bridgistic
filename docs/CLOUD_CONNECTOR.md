# Cloud connector status (`mcp.bridgistic.app`)

**Status: deployed, public beta. Linked from the plugin UI (WP Admin → Bridgistic Cloud) as of
this page's last update. No independent security review yet — see the checklist below.**

This page is the source of truth for where the hosted, multi-tenant MCP relay actually stands —
`cloud/README.md` and `docs/ROADMAP.md` describe the design; this page tracks the live deployment.

## What's actually live

- Cloudflare Worker `bridgistic-cloud` is deployed and running the code in `cloud/src/`.
- D1 database `bridgistic-cloud` exists, with the `tenants` table already migrated
  (`migrations/0001_init.sql` applied).
- KV namespace `OAUTH_KV` exists (used by `@cloudflare/workers-oauth-provider` for its own
  grant/token/client storage, and by the Worker's own rate limiter — see below).
- `cloud/wrangler.toml` has the real resource IDs for both of the above — no more
  `REPLACE_WITH_REAL_*` placeholders.
- A manual-dispatch CI workflow (`.github/workflows/deploy-cloud.yml`) can redeploy Worker code
  changes once a repo admin adds the `CLOUDFLARE_API_TOKEN` / `CLOUDFLARE_ACCOUNT_ID` secrets
  (Settings → Secrets and variables → Actions). Until those secrets exist it's a safe no-op.
- `cloud/` has an automated test suite (`cloud/test/`, run via `npm test`) — **194 tests across 43
  suites** covering tenant isolation, credential encryption, PKCE, tenant storage, request
  signing, SSRF/URL validation, rate limiting, observability redaction, and the full OAuth
  handshake. Wired into CI.
- **SSRF protection** (`cloud/src/url-guard.ts`, 59 tests). The connect form previously accepted
  any `https://` URL and the Worker then made server-side requests to it. Now refused: non-HTTPS,
  embedded credentials, non-default ports, IP literals in every notation (dotted, decimal, hex,
  octal, short form), loopback / RFC1918 / CGNAT / link-local / multicast, IPv6 loopback and
  unique-local plus IPv4-mapped forms, cloud metadata hostnames, and single-label intranet names.
  Validation runs at connect time and again at use time, so a row written before the guard existed
  is not trusted for being in the database.
  **Not mitigated:** DNS rebinding. A hostname that resolves to a private address at fetch time
  cannot be caught, because Workers cannot resolve a name before fetching it.
- **Layered rate limiting** (`cloud/src/rate-limit.ts`), checked before a request reaches the
  OAuth provider or the Durable Object: per-IP (120/min on `/mcp`, 20/min on the handshake
  routes), per-tenant (300/min on `/mcp`), and a global ceiling per route class. Layers
  short-circuit, so a refused request cannot drain the ceilings protecting everyone else.
  Refusals answer with a JSON `429` carrying `Retry-After` and a correlation id.
  **Known limitation:** KV has no atomic increment, so this is read-then-write and approximate —
  roughly the configured limit *per colo*, not an exact quota. Adequate for blunting a hammering
  token or a scripted flood; inadequate as a billing-grade counter. Moving to a Durable Object
  counter or Cloudflare's native rate-limiting binding is the follow-up; neither changes the call
  sites.
- **Privacy-safe structured logging** (`cloud/src/observability.ts`): fixed-shape JSON lines with a
  request id, route, one-way tenant handle, latency, status, and a stable error category. The HMAC
  secret, OAuth codes, PKCE verifiers, decrypted tenant keys, and request/response bodies are
  never logged. Every response carries an `X-Bridgistic-Request-Id` header so a user reporting a
  failure can hand over something that maps to exactly one log line.
- **Versioned credential envelope**: tenant secrets are stored as `v2.aes256gcm.<iv>.<ciphertext>`
  with a fresh random IV per encryption; pre-v2 rows still decrypt.
  **Rotation caveat:** replacing `TENANT_ENC_KEY` does *not* migrate existing rows — every secret
  encrypted under the old key becomes undecryptable and those tenants must reconnect. Rotation
  needs a decrypt-all/re-encrypt migration step before the secret is swapped.
- **Linked in WP Admin**: a "Bridgistic Cloud" nav item (tagged Beta) links to an informational
  page explaining the connector, the fixed connector URL to paste into an AI client, and an
  explicit no-security-review-yet warning. It doesn't perform the OAuth handshake itself — that's
  still `Bridgistic\Oauth` + the consent screen at `admin.php?page=bridgistic-oauth-authorize`,
  which also now carries the same beta warning directly on the Allow/Deny screen.
- `docs/FREE_VS_PAID.md` and the in-plugin Premium Features page have been updated: the remote
  connector is no longer listed as SaaS-exclusive.

## What still needs a human to confirm (not checkable from this environment)

Two things could not be verified programmatically — this sandbox's network egress policy blocks
direct requests to `mcp.bridgistic.app`, and secret *values* are never readable via the Cloudflare
API, only whether a binding exists:

1. **`TENANT_ENC_KEY` secret is actually set on the Worker.** If it isn't, every request that
   touches a tenant row will throw (`crypto.ts` requires a 32-byte key). Check with:
   ```bash
   cd cloud && wrangler secret list
   ```
   If `TENANT_ENC_KEY` isn't listed: `wrangler secret put TENANT_ENC_KEY` and paste the output of
   `openssl rand -base64 32`. **If a key already exists and you're not sure it's the same one used
   originally, do not overwrite it** — every already-connected tenant's `key_secret_enc` was
   encrypted with the original key, and overwriting it locks those tenants out permanently (they'd
   need to reconnect). Only set a fresh one if this is truly a first-time setup.

2. **The `mcp.bridgistic.app` DNS route is actually resolving to this Worker.** `wrangler.toml`
   declares the route (`mcp.bridgistic.app/*` on zone `wpistic.cloud`), which requires the
   `wpistic.cloud` zone to be on this Cloudflare account with the route attached. Confirm from a
   machine that isn't behind this sandbox's egress policy:
   ```bash
   curl -i https://mcp.bridgistic.app/mcp
   ```
   A `401`/`426`/JSON-RPC-shaped response means it's reachable and answering as an MCP endpoint. A
   TLS/DNS failure means the route or zone isn't attached yet — check
   **Cloudflare dashboard → Workers & Pages → bridgistic-cloud → Settings → Domains & Routes**.

## Before this goes from "public beta" to a fully vetted, generally-announced feature

Done since the original pre-launch checklist: rate limiting (now layered), the free-vs-paid call
(resolved as free/beta), SSRF protection, tenant-isolation test coverage, and privacy-safe
operational logging. What's left:

1. A real end-to-end test: WordPress OAuth consent → tenant provisioned in D1 → a real MCP client
   (Claude's remote connector is the easiest to test with today) successfully calls a tool. Still
   not done from this environment (network egress is blocked here) — a maintainer with real access
   needs to run this once and record the result here.
2. **An independent security review of the OAuth relay and the D1 tenant store** — this Worker
   holds a live Bridgistic credential for every connected WordPress site. This is the biggest
   remaining gap; the in-plugin UI (nav badge, both consent-adjacent warnings) says so explicitly
   so users can make an informed call rather than assuming "linked in the dashboard" means
   "audited."
3. Load/abuse testing against a deployed staging Worker — confirm the chosen limits hold up under
   real traffic patterns without blocking legitimate polling or leaving room for meaningful abuse,
   and measure how far KV's per-colo approximation drifts from the configured ceiling in practice.
   The limiter's thresholds, window reset, and per-dimension isolation are unit-tested; its
   behaviour under real concurrency is not.
4. A DNS-rebinding mitigation, if one turns out to be workable inside the Workers runtime.

## Trying it

WP Admin → **Bridgistic Cloud** shows the connector URL and the same steps as below. To try it
with Claude's remote connector directly: Settings → Connectors → Add custom connector →
`https://mcp.bridgistic.app/mcp` → approve in your own WP admin when prompted. See
[CHATGPT_SETUP.md](CHATGPT_SETUP.md) for the ChatGPT-specific flow.
