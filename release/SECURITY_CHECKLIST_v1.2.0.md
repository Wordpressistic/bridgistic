# Bridgistic v1.2.0 Security Checklist

Each item is PASS, FAIL, or NOT VERIFIED. **NOT VERIFIED means nobody has
evidence** — it does not mean "probably fine". Nothing here is upgraded to PASS
on the strength of code reading alone; PASS requires a test that ran.

Verified against commit `b1fdc77`.

---

## Authentication

### HMAC — **PASS**

Canonical form is `METHOD \n PATH \n TIMESTAMP \n NONCE \n sha256(body)`,
verified with `hash_equals` (constant time). Covered by a pinned known-vector
test so the PHP verifier and the TypeScript signer cannot drift apart silently,
plus tamper tests proving the signature actually covers the body, the path, and
the method. Missing-header, empty-signature, wrong-secret, and unknown-key
cases all reject with distinct error codes.

*Evidence:* `suite-hmac` (45 checks); `mcp-server/evals/integration.test.mjs`
re-verifies signatures with an independent implementation.

### Replay protection — **PASS**

Two independent controls: a ±300s timestamp window and a single-use nonce
stored for the window plus 60s. Tested for reuse rejection, per-key nonce
scoping (two keys may legitimately pick the same nonce), and both past and
future skew.

**Caveat, and it is a real one:** the nonce store is a WordPress transient. On
a site with a persistent object cache that evicts entries early under memory
pressure, an evicted nonce becomes replayable inside the 300s window. Health
Check now flags an active external object cache with exactly this explanation
rather than treating it as trivia.

*Evidence:* `suite-hmac`; `HealthCheck::check_object_cache()`.

### Key rotation — **PASS**

Rotation replaces the encrypted secret in place, keeping the key id stable.
Tested that the previous secret stops authenticating and the new one starts,
that the id does not change, and that rotating an unknown key returns null
rather than minting one.

*Evidence:* `suite-hmac`.

### Scopes — **PASS**

Every privileged route calls `require_scope()` before doing work, denials are
audited, and scopes are sanitised against the catalogue on the way in so an
invented scope cannot be granted. The new `woo:*` scopes are deliberately
separate from `posts:*`, so a content-writing key cannot change order status or
pricing.

*Evidence:* `suite-scopes` (137 checks); `suite-oauth` verifies forged scopes in
an OAuth grant are stripped before a key is minted.

**Documented exception:** `POST /oauth/token` is intentionally unauthenticated
by HMAC, because no HMAC key exists at that point in the flow. It is protected
by the full OAuth/PKCE contract plus a per-IP rate limit. It is the only
`__return_true` permission callback in the plugin.

### Approval enforcement — **PASS (logic)** / **NOT VERIFIED (end to end)**

`Guard::run()` gates every mutating operation: dry-run, approval queue,
auto-snapshot, execute, audit. Raw write SQL, file deletion, and WooCommerce
order-status changes always force approval regardless of key policy.

PASS covers the classification feeding the guard — which operations are
destructive and therefore snapshot-and-approve. The queue-then-approve-then-
execute round trip needs a live WordPress with real requests flowing through
it, and has **not** been executed. The CI activation job (run `31568527115`,
WordPress latest and 6.7) proves the approval table is created and the REST
routes register, but does not drive a request through the queue.

---

## OAuth 2.1 / PKCE

### Redirect validation — **PASS**

HTTPS only, exact host match, exact callback path, no embedded credentials, no
non-default port, no subdomain wildcards. Thirteen rejection cases tested,
including the allowed host appearing in a query string and as a userinfo
segment.

*Evidence:* `suite-oauth`.

### PKCE — **PASS**

S256 only. The challenge is validated as 43-character base64url at both
authorize and consent time; the verifier is validated against RFC 7636's
length and character rules *before* the stored grant is touched, so a scan of
malformed verifiers cannot burn legitimate single-use codes.
`code_challenge_method` is now carried through the consent form and revalidated
server-side.

*Evidence:* `suite-oauth`; `cloud/test/pkce.test.ts`.

### Authorization code reuse — **PASS**

Codes are 256 bits of CSPRNG hex, single-use, bound to the redirect URI and the
PKCE challenge, and deleted on *any* redemption attempt — a wrong verifier
consumes the code, so it cannot be brute-forced. Expiry is enforced both by the
transient TTL and by a stored issue timestamp, so a TTL-ignoring object cache
cannot extend a code's life.

*Evidence:* `suite-oauth`.

### Consent screen — **PASS**

Requires an authenticated `manage_options` administrator, verifies a nonce,
revalidates every hidden field server-side, defaults to the narrowest preset,
names the destructive scopes each preset would grant, offers explicit Allow and
Deny, and audits both outcomes plus rejections of malformed requests. An
unknown preset id resolves to Read-only, never to something broader.

*Evidence:* `suite-oauth`, `suite-scopes`.

---

## Cloud relay

### Tenant isolation — **PASS**

A session resolves exactly one tenant. Tested that another tenant's site URL,
key id, secret, and scopes are unreachable; that no alias — including another
tenant's, `default`, empty, or `../`-prefixed — escapes the bound site; that
only the session's own row is ever queried; and that resolution fails closed on
a missing, empty, unknown, or revoked tenant id with no fallback tenant.

*Evidence:* `cloud/test/tenant-isolation.test.ts` (13 tests).

### Tenant encryption — **PASS**

AES-256-GCM with a fresh random 12-byte IV per encryption, in a versioned
`v2.aes256gcm.` envelope; pre-v2 rows still decrypt. Tampered ciphertext,
tampered IV, truncated ciphertext, wrong key, and wrong-length IV all reject.
No plaintext secret is stored, and two tenants sharing a secret still get
distinct ciphertext.

**Documented limitation:** replacing `TENANT_ENC_KEY` does **not** migrate
existing rows — those tenants must reconnect. Stated in `cloud/src/crypto.ts`;
no claim is made that existing ciphertext survives a key replacement.

*Evidence:* `cloud/test/crypto.test.ts`, `cloud/test/tenant-isolation.test.ts`.

### SSRF protection — **PASS**

The connect form accepted any `https://` URL before this release and then made
server-side requests to it, so `https://127.0.0.1`, `https://169.254.169.254`,
and `https://10.0.0.1` were all accepted and fetched. Now refused: non-HTTPS,
embedded credentials, non-default ports, IP literals in every notation
(dotted, bare decimal, bare hex, dotted octal, dotted hex, short form),
loopback / RFC1918 / CGNAT / link-local / multicast / broadcast, IPv6 loopback
and unique-local plus IPv4-mapped forms, cloud metadata hostnames and the
`.internal` / `.local` families, and single-label intranet names. Validation
runs at connect time *and* again at use time, so a row written before the guard
existed is not trusted for being in the database.

**Documented limitation:** DNS rebinding is not mitigated. A hostname that
resolves to a private address at fetch time cannot be caught, because Workers
cannot resolve a name before fetching it.

*Evidence:* `cloud/test/url-guard.test.ts` (59 tests).

### Rate limiting — **PASS (behaviour)** with a documented precision limit

Layered per-IP, per-tenant, and global-per-route-class, short-circuiting so a
refused request cannot drain the ceilings protecting everyone else. Returns
JSON `429` with `Retry-After` and a correlation id. Tested for thresholds,
window reset, per-dimension isolation, a source rotating IPs meeting the tenant
ceiling, and a distributed flood meeting the global ceiling. WordPress
additionally rate-limits the OAuth token endpoint per IP.

**Documented limitation:** KV has no atomic increment, so this is read-then-
write and approximate — "roughly the limit, per colo", not an exact quota.
Stated at the top of `cloud/src/rate-limit.ts`.

*Evidence:* `cloud/test/rate-limit.test.ts` (18 tests).

### Audit log / observability redaction — **PASS**

Worker logs are fixed-shape JSON with one-way tenant handles and stable error
categories; the exception message, which can carry a site URL or an upstream
body, is never logged. The WordPress diagnostic report is built from an
allowlist and then passed through a name- and pattern-based redaction step,
because a debug report is pasted into public issue trackers by definition.

*Evidence:* `cloud/test/observability.test.ts` (13 tests);
`HealthCheck::redact()`.

### Cloud live E2E — **NOT VERIFIED**

The full flow (`/authorize` → wp-admin consent → code → PKCE exchange → tenant
in D1 → remote client `tools/list` → read tool → approved write → audit entry)
has **not** been run against the deployed Worker. It requires a live D1
database, a staging WordPress site, a remote MCP client, and outbound network
access; none are available in the release environment. The handshake logic is
covered in-process against fake bindings, which proves the logic and not the
deployment.

### Independent security audit — **NOT VERIFIED**

**No independent third-party security review has been performed.** Bridgistic
Cloud ships labelled **Public Beta**. No artifact, admin page, or document in
this release states or implies otherwise, and the consent screen carries the
warning at the point of decision.

---

## WordPress bridge internals

### SQL write classification — **PASS**

Comments and string-literal contents are stripped before classification.
Multi-statement input and file-access SQL (`INTO OUTFILE` / `DUMPFILE` /
`LOAD_FILE` / `LOAD DATA`) are refused outright. A CTE is resolved to what it
actually feeds, so `WITH … DELETE` is a write. Unrecognised statements fail
closed as destructive writes.

**This closed a live privilege-escalation path.** The previous prefix regex
classified `WITH t AS (…) DELETE FROM wp_posts …` as a read, so a `db:read` key
could execute it — skipping the approval queue and the pre-write snapshot.

*Evidence:* `suite-sql-classifier` (71 checks).

### Filesystem sandbox — **PASS**

Containment is compared on whole path segments. The previous
`strpos($real, $base) === 0` also accepted a sibling directory sharing the
prefix (`/var/www/html-backup` for a `/var/www/html` install). `realpath()`
resolves traversal and symlinks before the check. Executable PHP and
execution-control files (`.htaccess`, `.user.ini`, `php.ini`, `web.config`) may
only be written inside the sandbox.

*Evidence:* `suite-filesystem` (58 checks).

### Credential-file protection — **PASS**

`wp-config.php`, `.env`, `.htpasswd`, `.my.cnf`, `.netrc`, `.npmrc`, `.pgpass`,
and SSH private keys are refused for read, write, and delete at any scope.

**This closed a live credential-disclosure path.** `wp-config.php` carries
`AUTH_KEY` and `SECURE_AUTH_KEY`, two of the three inputs to `Security\Crypto`'s
key derivation, so `fs:read` plus `db:read` was enough to decrypt every stored
key secret on the site.

*Evidence:* `suite-filesystem`.

### PHP execution controls — **PASS**

`php:execute` is offered by exactly one preset (Developer Mode), which is
flagged risky and requires approval — asserted by test, so a future preset
cannot quietly acquire it. The tool is annotated destructive, every call is
audited with a truncated code excerpt, exceptions and errors are captured
rather than fatal, output/errors/return value are capped at 256 KB with an
explicit truncation flag, and the call takes a best-effort 30s wall-clock
budget.

**Honest bound:** `set_time_limit()` is a no-op under some SAPIs, so the time
budget is best-effort. The output cap is the hard guarantee that a runaway
script fails the request instead of exhausting PHP memory.

*Evidence:* `suite-scopes`; `ExecuteController`.

### Secret management — **PASS**

No secrets committed (repository scan over 192 files, in CI). No secrets in
generated debug reports (allowlist plus redaction). No secrets in logs. No
secrets in `localStorage` — the multi-site builder persists structural fields
only, and secrets live in memory for the page session unless the user
explicitly downloads them. No secrets in URLs. One-time display only: stored
secrets are encrypted and unreadable after creation, and every config generator
emits a labelled placeholder when no secret is available. All three shipped
archives are scanned for credential-shaped strings before release.

*Evidence:* `npm run validate`; `scripts/verify-packages.js`;
`suite-config-generator`.

---

## Summary

| Item | Status |
|---|---|
| HMAC | PASS |
| Replay protection | PASS (object-cache caveat documented) |
| Scopes | PASS |
| Approval enforcement | PASS (logic) / NOT VERIFIED (live round trip) |
| Key rotation | PASS |
| OAuth redirect validation | PASS |
| PKCE | PASS |
| Authorization code reuse | PASS |
| Tenant isolation | PASS |
| Tenant encryption | PASS (key rotation is not migration-safe — documented) |
| SSRF protection | PASS (DNS rebinding not mitigated — documented) |
| Rate limiting | PASS (approximate under KV — documented) |
| Secret scan | PASS |
| Filesystem sandbox | PASS |
| SQL write classification | PASS |
| PHP execution controls | PASS |
| Audit log redaction | PASS |
| Cloud live E2E | **NOT VERIFIED** |
| Independent security audit | **NOT VERIFIED** |

No P0 issue is outstanding. The two NOT VERIFIED items are both about
Bridgistic Cloud, which ships as a clearly labelled Public Beta for exactly
that reason.
