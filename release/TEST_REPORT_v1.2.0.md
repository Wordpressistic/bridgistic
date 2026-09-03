# Bridgistic v1.2.0 Test Report

Every result below was produced by running the named command in the release
environment. Anything that was not run says so, and says why — a gate marked
NOT VERIFIED is a gate nobody has evidence for, not a gate that probably passes.

## Release commit

`b1fdc77` (branch `claude/release-v1.2.0-artifacts-jn39s4`)

Artifacts were built from the commit recorded in `SHA256SUMS.txt`.

## Environment

| | |
|---|---|
| Node | v22.22.2 |
| npm | 10.9.7 |
| PHP | 8.4.19 (cli, NTS) |
| WordPress | Not installed locally; **latest and 6.7 exercised in CI** via WP-CLI |
| WooCommerce | **Not installed in any environment** — see *Not verified here* |
| Cloud test runtime | `node:test` via `tsx` 4.x (no Miniflare / `workerd`) |
| OS | Linux 6.18.5 x86_64 |

The release environment has no MySQL server and no outbound network access.
That bounds what could be executed here, and the bounds are stated explicitly
below rather than being papered over with an untested "PASS".

## MCP Server

Command: `npm test`, `npm run test:bundle`

| Test | Result | Evidence |
|---|---|---|
| TypeScript build | PASS | `tsc` clean, then esbuild bundle |
| Contract (tool surface) | PASS | 54 tools, 567 assertions |
| Contract: annotation honesty | PASS | included above — destructive tools may not claim `readOnlyHint` |
| Integration (signed calls) | PASS | 18 signed calls, 74 assertions |
| Shipped bundle smoke | PASS | 54 tools over a real stdio handshake, 122 assertions |
| Committed bundle is current | PASS | byte-identical to a clean rebuild |
| Cloud/MCP tool drift | PASS | `npm run check:cloud-drift` |

The bundle smoke test drives `plugins/bridgistic/server/index.js` — the file
the Claude Code plugin, the `.mcpb` extension, and the release package all
ship — not `mcp-server/dist/index.js`, which is what `npm test` drives and
which no user receives.

## WordPress

Command: `npm run lint:php`, `npm run test:php`

| Test | Result | Evidence |
|---|---|---|
| PHP lint | PASS | 80 files, `php -l` clean |
| Crypto round-trip + tamper rejection | PASS | suite-hmac |
| HMAC round-trip | PASS | suite-hmac |
| HMAC canonical-form vector | PASS | suite-hmac — pinned so PHP/TypeScript cannot drift apart silently |
| Body / path / method tamper rejection | PASS | suite-hmac |
| Replay prevention (nonce single-use) | PASS | suite-hmac |
| Clock-skew window | PASS | suite-hmac — past, future, and in-window |
| Key disable / re-enable | PASS | suite-hmac |
| Key rotation invalidates the old secret | PASS | suite-hmac |
| Key revocation | PASS | suite-hmac |
| IP allowlist enforcement | PASS | suite-hmac |
| OAuth redirect allowlist | PASS | suite-oauth — 13 rejection cases |
| OAuth PKCE (S256) | PASS | suite-oauth |
| Authorization-code single use | PASS | suite-oauth — including consumed-on-failure |
| Authorization-code expiry | PASS | suite-oauth |
| Unknown preset downgrades to Read-only | PASS | suite-oauth |
| Forged scopes stripped before minting | PASS | suite-oauth |
| SQL write classification | PASS | suite-sql-classifier — 71 checks |
| Filesystem containment | PASS | suite-filesystem |
| Credential-file protection | PASS | suite-filesystem |
| Scope / preset wiring | PASS | suite-scopes — 137 checks |
| Codex TOML generation + escaping | PASS | suite-config-generator |
| Gemini JSON generation + escaping | PASS | suite-config-generator |
| Plugin ZIP structure | PASS | `node scripts/verify-packages.js` |
| Plugin ZIP installs and activates (WordPress latest) | PASS | CI run 31568527115, WP-CLI |
| Plugin ZIP installs and activates (WordPress 6.7) | PASS | CI run 31568527115, WP-CLI |
| Activation creates all four custom tables | PASS | CI — `wp db query` per table |
| REST namespace registers ≥10 routes | PASS | CI — `wp eval` over `rest_get_server()` |
| No PHP errors during boot | PASS | CI — grep for fatal/parse/warning/deprecated |

**Total: 430 checks across 6 suites, 0 failures.**

These run the real plugin classes against an SQLite-backed `$wpdb`, so storage
behaviour is genuinely exercised — key rotation really invalidates the previous
secret rather than being asserted against a mock that always agrees. They are
not a WordPress integration harness: there is no core, no hook system, and no
REST dispatcher.

### Two real bugs were found and fixed by writing these tests

1. **`WITH … DELETE` misclassified as a read.** The CTE walker read the tail
   after the *last* parenthesis group closed. For
   `WITH t AS (…) DELETE FROM p WHERE id IN (SELECT …)`, the trailing subquery
   closes a second group, so the walker found no tail and fell through to
   "read" — meaning a `db:read` key could have executed a `DELETE`, skipping
   both the approval queue and the pre-write snapshot. Fixed to take the first
   group closing at depth zero, walking past `, name AS (…)` chains.

2. **Tamper-detection tests that tested nothing.** Three cloud crypto tests
   split the (now four-part) envelope into two variables and reassembled it,
   which meant they asserted "the envelope is malformed" instead of "the
   AES-GCM auth tag caught the edit" — passing while proving nothing. Repaired
   to keep the version prefix intact.

## Cloud

Command: `npm --prefix cloud run typecheck`, `npm run test:cloud`

| Test | Result | Evidence |
|---|---|---|
| TypeScript typecheck | PASS | `tsc --noEmit` clean |
| Unit suite (total) | PASS | **194 tests, 43 suites, 0 failures** |
| Tenant isolation | PASS | 13 tests — cross-tenant reads, alias escape, fail-closed resolution |
| Crypto round-trip | PASS | crypto suite |
| Crypto tamper rejection | PASS | ciphertext, IV, and truncation cases |
| Unique IV per encryption | PASS | crypto suite |
| Legacy (pre-v2) envelope still decrypts | PASS | crypto suite |
| PKCE vectors | PASS | pkce suite |
| OAuth state handling | PASS | oauth-flow suite |
| Authorization-code single use | PASS | oauth-flow suite |
| Tenant D1 storage | PASS | tenants-db suite |
| Missing tenant fails closed | PASS | tenant-isolation suite |
| HMAC signing vector | PASS | signer suite |
| URL validation / SSRF rejection | PASS | **59 tests** — loopback, RFC1918, CGNAT, link-local, IPv6, metadata, obfuscated IPv4, credentials, ports |
| Rate limiting (layered) | PASS | 18 tests — thresholds, window reset, per-IP/tenant/global separation |
| Observability redaction | PASS | 13 tests — no token, message, or site URL reaches a log line |
| WordPress 401/403/429/5xx mapping | PASS | wp-client suite |
| Timeout handling | PASS | wp-client suite |
| Malformed upstream response | PASS | wp-client suite |
| Full OAuth flow | PASS | oauth-flow suite |
| **Live remote E2E** | **NOT VERIFIED** | see below |
| **Load / abuse testing** | **NOT VERIFIED** | see below |

Cloud tests grew from 93 to 194 in this release.

## WooCommerce

| Test | Result | Evidence |
|---|---|---|
| Tool registration + schemas | PASS | contract test, 11 `bridgistic_woo_*` tools |
| Tool annotations (destructive vs read-only) | PASS | contract test |
| Route + method + payload forwarding | PASS | integration test, 11 signed Woo calls |
| Scope separation from `posts:*` | PASS | suite-scopes |
| WooCommerce Manager preset containment | PASS | suite-scopes — no PHP/DB/FS/plugin scopes |
| Graceful degradation without WooCommerce | PASS by construction | `WooController::is_available()` gates every route; routes register unconditionally so a non-store site gets a structured error, not a 404 |
| **Product read against a live store** | **NOT VERIFIED** | no WooCommerce install available |
| **Product write against a live store** | **NOT VERIFIED** | no WooCommerce install available |
| **Order read against a live store** | **NOT VERIFIED** | no WooCommerce install available |
| **Order status update against a live store** | **NOT VERIFIED** | no WooCommerce install available |
| **Analytics read against a live store** | **NOT VERIFIED** | no WooCommerce install available |

The WooCommerce controller's request/response contract is fully covered from
the MCP side. Its interaction with WooCommerce's own API is not, because
WooCommerce cannot be installed here. This is the single largest untested
surface in the release and is called out again in the security checklist.

## Packaging

Command: `npm run release`

| Artifact | SHA256 | Reproducible |
|---|---|---|
| `bridgistic-wordpress-plugin.zip` | `2f8d8f75b2389ac4ceaa6bbb50d7d8a99e670f0c505c1f7e833e2ba2be01329c` | Yes |
| `bridgistic-claude-package.zip` | `13aa799e59aba69e6c1306e6778ec7078e1f50edbc8d6a820eb8387149114bfb` | Yes |
| `bridgistic-mcp-server-1.2.0.tgz` | `da7164db218a531c900018fb1c1d4327cd395645410c66c38e6900dda677847d` | Yes |
| `bridgistic.mcpb` | *varies per build* | No — see below |

The three reproducible digests hold across rebuilds of the same source. The
exact commit each release directory was built from is recorded in the header of
its own `SHA256SUMS.txt`.

The three markdown documents in the release directory are checksummed too;
their digests are in `SHA256SUMS.txt`, which is regenerated on every build (so
this file's own digest necessarily differs from the run that produced the table
above). Verify the set with `sha256sum -c SHA256SUMS.txt`.

### Reproducibility

`bridgistic-wordpress-plugin.zip`, `bridgistic-claude-package.zip`, and
`bridgistic-mcp-server-1.2.0.tgz` are **byte-reproducible**: rebuilding the same
commit produces identical SHA256 digests, verified by building twice. The zip
writer stamps a fixed timestamp (honouring `SOURCE_DATE_EPOCH`) using UTC
fields and normalises file modes, so the archive does not depend on when or
where it was built.

This was a real defect found during release preparation. The zip writer stamped
`new Date()` on every entry, so two builds of the same commit produced different
bytes — which makes published checksums unverifiable and "never republish
different bytes under the same version" impossible to honour rather than merely
discouraged.

`bridgistic.mcpb` is **not** reproducible. It is packed by the third-party
`@anthropic-ai/mcpb` CLI, which embeds build time; two consecutive builds of an
unchanged tree produce different digests. Its checksum is recorded for
integrity — it still detects tampering in transit — but it cannot be
independently reproduced from source.

| Check | Result |
|---|---|
| Exactly one `bridgistic/` root, no nested duplicate | PASS |
| No `node_modules`, `.env`, git metadata, or scratch files | PASS |
| No test files in the plugin ZIP | PASS |
| No credential-shaped strings in any artifact | PASS |
| Plugin header + `BRIDGISTIC_VERSION` + `readme.txt` Stable tag all 1.2.0 | PASS |
| `.mcpb` manifest version and entry point | PASS |
| `key_secret` marked sensitive in the `.mcpb` manifest | PASS |
| npm tarball ships `dist/`, not `src/` or `evals/` | PASS |
| Marketplace validation + version consistency (13 sources) | PASS |
| Repository secret scan (192 files) | PASS |

## Not verified here, and why

These are infrastructure limits of the release environment, evidenced rather
than assumed. Each has a CI job or a documented manual step that covers it
where the infrastructure exists.

**Resolved since first drafting.** Plugin ZIP installation and activation could
not be run in the release environment — no MySQL server binary is present
(`which mysqld mariadbd` returns nothing) and there is no outbound network to
fetch WordPress (`curl https://api.wordpress.org/…` returns exit status 000).
It has since been verified by the `wordpress-activation` job in CI run
`31568527115`, which installed this exact ZIP via WP-CLI on WordPress latest
and 6.7 and asserted activation created all four tables, registered ≥10 REST
routes, reported version 1.2.0, and emitted no PHP errors. All five CI jobs on
that run passed. The table above reflects that.

Still outstanding:

1. **Live WooCommerce behaviour.** No WooCommerce install is available in this
   environment, and the CI activation job installs WordPress without it. The
   Woo tools' request/response contract is fully covered from the MCP side;
   their interaction with WooCommerce's own API is not. This is the largest
   untested surface in the release.

2. **Live cloud end-to-end** (`/authorize` → wp-admin consent → code → PKCE
   exchange → tenant in D1 → remote client `tools/list` → read tool → approved
   write → audit entry). Requires a deployed Worker, a live D1 database, a
   staging WordPress site, and a remote MCP client. No outbound network here.
   The same flow is covered in-process by `cloud/test/oauth-flow.test.ts`
   against fake KV/D1 bindings, which proves the handshake logic but not the
   deployment.

3. **Load / abuse testing** against `/mcp` and the OAuth endpoints. Requires a
   deployed staging Worker. The rate limiter's threshold, window-reset, and
   per-dimension isolation behaviour is unit-tested; its behaviour under real
   concurrency across Cloudflare colos is not, and the KV limiter's
   read-then-write inexactness is documented in `cloud/src/rate-limit.ts`
   rather than claimed away.

4. **Independent third-party security review.** Has not happened. Bridgistic
   Cloud therefore ships labelled **Public Beta**, and no artifact, page, or
   document in this release claims otherwise.

## Remaining known limitations

- **Bridgistic Cloud is a public beta.** Automated coverage is substantial
  (194 tests including tenant isolation and 59 SSRF cases) but no independent
  audit has occurred and no live E2E has been run against the deployed Worker.
- **DNS rebinding is not mitigated in the cloud relay.** The SSRF guard
  validates the hostname it is given; a hostname that resolves to a private
  address at fetch time cannot be caught, because Workers cannot resolve a name
  before fetching it. Documented in `cloud/src/url-guard.ts`.
- **Cloud rate limiting is approximate.** KV has no atomic increment, so the
  effective ceiling is "roughly the configured limit, per colo". Adequate for
  blunting abuse, inadequate as a quota. Moving to a Durable Object or
  Cloudflare's native binding is recorded as a follow-up.
- **`TENANT_ENC_KEY` rotation is not migration-safe.** Replacing the secret
  makes every existing tenant row undecryptable; those tenants must reconnect.
  Stated in `cloud/src/crypto.ts` rather than implied to be safe.
- **The WordPress test harness is not WordPress.** Logic that depends on core
  (rewrite rules, capability mapping, the REST dispatcher) is covered by the CI
  activation job — which passed on WordPress latest and 6.7 — not by the
  430-check suite.
- **Live WooCommerce behaviour is untested.** The CI activation job installs
  WordPress without WooCommerce, so the store tools' interaction with
  WooCommerce's own API has no coverage on any environment.
