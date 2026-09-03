# Testing

What is covered, what runs where, and — just as importantly — what is *not*
covered. This replaces the old `TEST_COVERAGE_ANALYSIS.md`, which described a
state that no longer exists (it opened with "`cloud/` — zero tests today").

## Current coverage

| Component | Tests | Runs in CI |
|---|---|---|
| `mcp-server/` | Contract (54 tools, 567 assertions) + integration (18 signed calls, 74 assertions) | Yes |
| Shipped bundle | Real stdio handshake against `plugins/bridgistic/server/index.js`, 122 assertions | Yes |
| `cloud/` | 194 tests across 43 suites | Yes |
| `wordpress-plugin/` | 430 behavioural checks across 6 suites, plus `php -l` | Yes, on PHP 8.0 and 8.3 |
| Plugin ZIP | Installed and activated in real WordPress via WP-CLI | Yes, on two WordPress versions |
| Release archives | Structure, junk, secrets, and version verification | Yes |

## Running everything locally

```bash
npm run bootstrap      # npm ci in mcp-server/ and cloud/
npm run build          # compile + bundle
npm run test:all       # MCP, PHP, cloud, and the shipped-bundle smoke test
npm run lint:php       # php -l across the plugin
npm run validate       # manifests, version consistency, secret scan
npm run check:cloud-drift
```

Individually:

```bash
npm test               # MCP server contract + integration
npm run test:php       # WordPress behavioural suites
npm run test:cloud     # Cloudflare Worker suites
npm run test:bundle    # the exact artifact users receive
```

## MCP server

`mcp-server/evals/contract.test.mjs` boots the server over stdio and asserts the
tool surface: every expected tool exists, names are namespaced, descriptions are
substantive, write tools expose the guard parameters, and **annotations are
honest** — a tool that sends customer emails may not advertise `readOnlyHint`.

`mcp-server/evals/integration.test.mjs` stands up a mock bridge that re-verifies
the HMAC signature with an independent implementation, then drives real
`tools/call` requests through it. This is what proves tools sign correctly, hit
the right route and method, and forward guard parameters.

### Why the bundle is tested separately

`npm test` drives `mcp-server/dist/index.js` — the `tsc` output. Nobody is
shipped that file. The Claude Code plugin, the `.mcpb` extension, and the
release package all ship `plugins/bridgistic/server/index.js`, an esbuild CJS
bundle. Those are different files from different tools, and a bundler can break
things `tsc` cannot: a dropped dynamic require, an ESM/CJS interop mismatch, a
dependency that resolves at runtime but not at bundle time.

`npm run test:bundle` spawns the bundle, completes a real MCP handshake, checks
the same tool surface, and fails if the committed bundle differs from a clean
rebuild — so a stale artifact cannot ship alongside newer source.

## WordPress plugin

`wordpress-plugin/bridgistic/tests/` runs the real plugin classes against a stub
of the WordPress surface they touch, with an **SQLite-backed `$wpdb`** so
storage is genuinely exercised. That distinction matters: the previous harness
returned canned values from `update()`, which meant a test could not tell a
working key rotation from a no-op.

| Suite | Covers |
|---|---|
| `suite-hmac` | Canonical form (pinned vector), tamper rejection, replay, clock skew, disable/re-enable, rotation, revocation, IP allowlists |
| `suite-oauth` | Redirect allowlist, PKCE validation, single use, expiry, preset downgrade, scope stripping |
| `suite-sql-classifier` | Read/write classification, CTEs, stacked statements, comments, file-access SQL |
| `suite-filesystem` | Path containment, sandbox boundary, credential-file protection |
| `suite-scopes` | Scope catalogue, preset composition, `php:execute` containment |
| `suite-config-generator` | Codex TOML, Gemini JSON, escaping round-trips, secret handling |

### What this harness is not

There is no WordPress core, no hook system, and no REST dispatcher. Anything
whose behaviour depends on core — rewrite rules, capability mapping, the REST
dispatcher itself — is out of scope here and is covered by the CI activation
job instead, which installs the built ZIP into a real WordPress via WP-CLI and
asserts the plugin activates, creates its tables, registers its REST routes,
and emits no PHP errors.

## Cloud Worker

`cloud/test/` runs under `node:test` via `tsx`, against hand-rolled fakes for
KV, D1, and the OAuth provider. No Miniflare or `workerd`, so anything that
depends on the real Workers runtime (Durable Object lifecycle, actual KV
consistency behaviour) is not covered here.

Notable suites: `tenant-isolation` (13 tests — cross-tenant reads, alias escape,
fail-closed resolution), `url-guard` (59 SSRF cases), `rate-limit` (18 tests
including layered isolation), `crypto` (round-trip, tamper, IV uniqueness,
legacy envelope), `observability` (redaction), and `oauth-flow` (the full
`/authorize` → `/wp-callback` handshake).

`cloud/src/tools/*.ts` is a hand-synced copy of `mcp-server/src/tools/*.ts`;
`npm run check:cloud-drift` byte-compares them and fails the build on
divergence.

## Not covered

Stated plainly, because a gap you know about is manageable and a gap you assume
away is not.

- **Live WooCommerce behaviour.** The Woo tools' request/response contract is
  fully covered from the MCP side; their interaction with WooCommerce's own API
  is not. This is the largest untested surface in the project.
- **Live cloud end-to-end** against the deployed Worker: real D1, a real remote
  MCP client, a real staging WordPress site. The handshake logic is covered
  in-process; the deployment is not.
- **Load and abuse testing** against a deployed staging Worker. The rate
  limiter's logic is unit-tested; its behaviour under real concurrency across
  Cloudflare colos is not, and KV's read-then-write inexactness means the
  effective ceiling is approximate by design.
- **Independent third-party security review.** None has occurred, which is why
  Bridgistic Cloud ships as a public beta.
- **Browser-level JS behaviour.** The admin JavaScript has no test runner; its
  error classification and polling behaviour are reviewed, not tested.

## Adding a test

WordPress: drop a `suite-*.php` into `wordpress-plugin/bridgistic/tests/`. It is
picked up automatically by `run-all.php`. Use `check()` / `check_equals()` /
`check_throws()` from the bootstrap. Suites share one database and one transient
store, so mint your own fixtures rather than depending on another suite's.

Cloud: add a `*.test.ts` under `cloud/test/`; `npm test` globs them.

MCP server: extend the contract or integration eval. If you add a tool, update
`EXPECTED` in `contract.test.mjs` and the expected count in
`scripts/smoke-test-bundle.js` — both are deliberately exact, so a tool added
without a test is a build failure rather than a silent gap.
