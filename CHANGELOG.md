# Changelog

All notable changes to the Bridgistic free public distribution.
Format: [Keep a Changelog](https://keepachangelog.com/) · Versioning: [SemVer](https://semver.org/).

## [Unreleased]

Nothing yet.

## [1.3.2] — 2026-09-11

### Fixed

- HMAC authentication on LiteSpeed/Hostinger hosting: incoming `X-Bridgistic-*` headers are
  rewritten to underscored (`X_Bridgistic_*`) variants, so signature verification saw no
  credentials and every API call failed with `401 bridgistic_auth_missing`. Header names are
  now normalized (`_` → `-`, lowercased) before verification. Affects Hostinger, LiteSpeed
  Enterprise and any stack that underscores custom header names.

## [1.3.1] — 2026-09-03

### Fixed

- Confined snapshot and restore paths by resolved parent and complete directory segments, rejecting traversal and sibling-prefix escapes.
- Prevented a fatal PHP class redeclaration when Bridgistic and another WPistic-licensed plugin are active together; the first compatible shared SDK copy is reused.
- Made the exact shipped-bundle smoke test invoke npm portably on Windows as well as Linux/macOS.

## [1.3.0] — 2026-09-03

WPistic licensing, free/paid tiers, and the WordPressistic org move.

### Added

- **Official WPistic licensing integration** — the plugin now vendors the WPistic WordPress SDK
  (`wordpress-plugin/bridgistic/includes/sdk/`): license activation with encrypted at-rest state
  (AES-256-GCM), HMAC-signed validation responses, activation-token rotation, a 7-day offline
  grace window, entitlement-driven feature gating, and secure in-dashboard updates served from
  the WPistic platform (`api.wpistic.com`). Activate under **Bridgistic → License** or via the
  Connect-to-WPistic onboarding wizard.
- **Free vs paid plan tiers, clearly drawn.** Connecting any AI client (Claude, Claude Desktop,
  ChatGPT remote MCP, OpenAI Codex CLI, Gemini CLI, Cursor, any MCP client), HMAC keys, scopes,
  approvals, audit, snapshots, and manual playbooks are free forever. Starter/Pro/Agency plans
  unlock unlimited scheduled playbooks, advanced snapshots, audit export, the AI skills
  marketplace, the agency dashboard, team permissions, and white-label.
- **License admin screen** — activate/deactivate/re-validate, plan display, feature matrix, and
  grace-window status.

### Changed

- **Repositioned as the any-AI bridge**: plugin description, readme, and admin copy now lead with
  "connect your WordPress site to any AI model" instead of Claude-first framing.
- **Repository moved to the WordPressistic GitHub org** (`github.com/wordpressistic/bridgistic`);
  all plugin/docs/package URLs updated.

## [1.2.0] — 2026-08-12

Multi-client connections, first-class WooCommerce operations, and a hardened cloud connector.

### Added

- **Codex CLI and Gemini CLI as first-class connection types** in the Claude Setup wizard (step 1
  choices + step 4 config tabs), generating `~/.codex/config.toml` and `~/.gemini/settings.json`
  snippets that launch the published `bridgistic-mcp-server` npm package via `npx` — no clone or
  build step. `ConfigGenerator::codex()` / `ConfigGenerator::gemini_cli()`.
- **Docs for connecting other AI clients**: `docs/CODEX_SETUP.md`, `docs/GEMINI_SETUP.md`,
  `docs/CHATGPT_SETUP.md` (public beta, remote-only), and `docs/CONNECT_OTHER_AI.md` (a hub
  covering both multi-client and multi-WordPress-site setups). Linked from the README and the
  Claude Setup wizard.
- **`cloud/` test suite** — 73 tests (`node --test`, zero new test-framework dependency besides
  `tsx` for running TypeScript directly) covering tenant credential encryption, PKCE, the tenant
  registry, D1 tenant storage, HMAC request signing, and the WordPress OAuth client. Wired into CI.
- **`docs/CLOUD_CONNECTOR.md`** — live-status tracking for the `mcp.wpistic.cloud` deployment
  (what's actually provisioned vs. what still needs manual confirmation) and the checklist before
  it moves from public beta to generally available.
- **`.github/workflows/deploy-cloud.yml`** — manual-dispatch CI workflow to redeploy the `cloud/`
  Worker via `wrangler deploy`, gated on `CLOUDFLARE_API_TOKEN`/`CLOUDFLARE_ACCOUNT_ID` repo
  secrets (safe no-op until a repo admin adds them). Independent of `ci.yml`/`release.yml`.

- **Per-IP rate limiting on the cloud Worker** (`cloud/src/rate-limit.ts`, wired in `index.ts`) —
  120 req/min/IP on `/mcp`, 20 req/min/IP on the OAuth handshake routes, backed by a KV
  fixed-window counter. Closes the "no rate limiting at the Worker level" gap tracked in
  `docs/CLOUD_CONNECTOR.md`. 4 new tests (`cloud/test/rate-limit.test.ts`, suite now 77 tests).
- **"Bridgistic Cloud" page** (WP Admin, tagged Beta in the nav) — the cloud connector's OAuth flow
  was previously live in code but reachable only via a deep link initiated from the Worker; this
  page makes it discoverable, shows the connector URL to paste into an AI client, and states its
  beta/no-security-review-yet status plainly. The OAuth consent screen itself carries the same
  warning directly on the Allow/Deny screen.
- **First-activation redirect** — activating the plugin now sends the admin straight to Claude
  Setup on their next page load (`Plugin::activate()` sets a short-lived transient,
  `Admin\Controller::maybe_redirect_after_activation()` consumes it), skipped for bulk activations.
- **Live "client connected" check in Claude Setup step 5** — previously "Run test" only confirmed
  the server-side pipeline; step 5 now also polls (`bridgistic_poll_client_connected`) until the
  AI client makes its first real request with the new key, and flips to a connected state
  automatically instead of requiring a manual trip to the Logs page.
- **Per-field copy buttons for the Desktop Extension panel** — Site URL, Key ID, and Secret each
  get their own one-click copy button instead of one plain-text block to select from by hand.
- **`docs/CONNECT_BRIDGISTIC.md`** — a single non-technical, step-by-step connect guide
  (install → key → connect → verify → troubleshoot → FAQ) consolidating what was previously spread
  across `INSTALL.md`, `WORDPRESS_SETUP.md`, `CLAUDE_DESKTOP.md`, `CONNECT_OTHER_AI.md`, and
  `CLOUD_CONNECTOR.md`. Linked from the README.
- **Live dashboard connection status** — the "Connected" badge, activity count, and latest-log line
  now poll every 15s (`bridgistic_dashboard_status`) instead of only reflecting the state at page
  load, and toast when a request lands while the tab is open (`DashboardPage::live_stats()`).
- **Categorized AJAX error messages** — the shared `post()` JS helper now distinguishes a network
  failure, a non-JSON response (the common signature of a WAF/security plugin/proxy blocking
  `admin-ajax.php`), and an HTTP error status, instead of surfacing whatever the browser's JSON
  parser happened to throw. Every AJAX call across the dashboard benefits automatically.
- **Claude Setup step 5 split into "1. Server check" / "2. Client check"** — the client check is new:
  it polls until the AI client makes its first real request, instead of a static wall of text.
- **Multi-client naming clarity** — the nav now tags "Claude Setup" with a small "all clients" hint,
  and the wizard's own header explains it also configures Codex/Gemini/other MCP clients and points
  at Bridgistic Cloud for remote-only clients.
- **`cloud/src/tenant-session.ts`** — `resolveTenantRegistry()`, extracted from `agent.ts`'s
  `init()` purely so it's unit-testable (`agent.ts` imports `agents/mcp`, which pulls in
  Cloudflare-Workers-only globals that make it unimportable under plain `node:test`). Same
  behavior, same error messages.
- **`cloud/test/oauth-flow.test.ts`** and **`cloud/test/tenant-session.test.ts`** — end-to-end
  coverage for the full `/authorize` → `POST /authorize` → `/wp-callback` → tenant upserted in D1 →
  `completeAuthorization()` handshake, and for tenantId → registry → registered, callable tools
  (16 new tests; suite is now 93). Previously only pure/isolable pieces were tested (PKCE, crypto,
  `cleanSiteUrl()`) — this is the first coverage of the actual request flow end to end.
- **`scripts/check-cloud-tools-drift.js`** (`npm run check:cloud-drift`, wired into CI) — fails the
  build if `cloud/src/tools/*.ts` and `mcp-server/src/tools/*.ts` (hand-synced copies, since the
  Worker can't import from `mcp-server` directly) diverge, instead of relying on a maintainer to
  remember to keep them in sync.
- **Multi-Site page** (WP Admin) — a guided `connections.json` builder: pre-fills this site's alias/
  URL/key, lets you add other sites' alias/URL/key ID/secret with a live JSON preview, and downloads
  the finished file. Structural fields (not secrets) persist in the browser's `localStorage` between
  visits. Replaces the fully-hand-edited-file-only workflow docs previously pointed to.

- **First-class WooCommerce tools** — eleven structured tools (`bridgistic_woo_*`) covering
  products, orders, customers, inventory, and sales summaries, behind six new least-privilege
  scopes (`woo:products:read|write`, `woo:orders:read|write`, `woo:customers:read`,
  `woo:analytics:read`) and a **WooCommerce Manager** preset. Everything goes through
  WooCommerce's own API (`wc_get_products`, `wc_get_orders`, `WC_Order`, `WC_Customer`), so it
  behaves identically with or without High-Performance Order Storage — previously a store
  question needed `db:read` or `php:execute`. Order status changes are treated as destructive
  (they fire customer emails, stock movements, and gateway actions that reverting the field does
  not undo), so they snapshot first and honour the approval queue. Customer and order shapes are
  built from an allowlist, so payment tokens, password hashes, and street addresses cannot leak
  by omission. With WooCommerce inactive, the routes answer with a structured
  "WooCommerce unavailable" error and nothing fatals.
- **ChatGPT / remote MCP as a first-class connection type** — a dedicated panel showing the hosted
  endpoint, why a local server is not an option for that client, links to the cloud page and
  diagnostics, and capability-based wording about plan availability rather than a fixed set of
  clicks upstream keeps moving.
- **SSRF protection in the cloud relay** (`cloud/src/url-guard.ts`) — `/authorize` previously
  accepted any `https://` URL and then made server-side requests to it, so `https://127.0.0.1`,
  `https://169.254.169.254`, and `https://10.0.0.1` were each accepted and fetched. Now refused:
  non-HTTPS, embedded credentials, non-default ports, IP literals in every notation, loopback /
  RFC1918 / CGNAT / link-local / multicast, IPv6 loopback and unique-local plus IPv4-mapped
  forms, cloud metadata hostnames, and single-label intranet names. Re-checked at use time, not
  only at connect time. 59 tests.
- **Layered rate limiting** — per-IP, per-tenant, and global-per-route-class, short-circuiting so
  a refused request cannot drain the ceilings protecting everyone else, with a JSON `429`
  carrying `Retry-After` and a correlation id. KV's read-then-write inexactness is documented in
  the module rather than left to be discovered.
- **Privacy-safe cloud observability** (`cloud/src/observability.ts`) — fixed-shape JSON request
  logs with one-way tenant handles, stable error categories, and an `X-Bridgistic-Request-Id`
  header on every response.
- **Versioned tenant-secret envelope** — `v2.aes256gcm.` prefix with pre-v2 rows still
  decrypting, and a wrong-length IV rejected before it reaches WebCrypto.
- **WordPress behavioural test suite** — 430 checks across six suites (HMAC, OAuth/PKCE, SQL
  classification, filesystem containment, scopes/presets, config generation) running against an
  SQLite-backed `$wpdb`, wired into CI on PHP 8.0 and 8.3. Previously the one existing PHP test
  was never executed by CI.
- **Shipped-bundle smoke test** (`npm run test:bundle`) — spawns the exact artifact users receive,
  completes a real MCP handshake, and fails if the committed bundle differs from a clean rebuild.
- **Package structure verification** (`scripts/verify-packages.js`) and a **staged release
  builder** (`npm run release`) producing `dist/release-v1.2.0/` with SHA256 checksums.
- **WordPress activation CI job** — installs the built ZIP via WP-CLI on two WordPress versions
  and asserts activation creates its tables, registers its REST routes, reports the right
  version, and emits no PHP errors.
- **Health Check** grew from 16 to 23 diagnostics: `admin-ajax.php` reachability, PHP zip
  extension, writable temp directory, transient round-trip (replay protection rides on
  transients, so an object cache that drops them is a security signal), WP-Cron, outbound HTTPS,
  cloud OAuth prerequisites, WooCommerce tool availability, and the key/snapshot tables. The
  downloadable diagnostic report gains environment detail and a redaction pass on the way out.
- **`docs/TESTING.md`** — what is covered, what runs where, and what is not covered. Replaces
  `TEST_COVERAGE_ANALYSIS.md`, which described a state that no longer exists.

### Changed

- **"Claude Setup" is now "AI / MCP Connections"** — labels, headings, and cross-links only. The
  `bridgistic-setup` slug, view name, and class name are unchanged, so bookmarks, the
  first-activation redirect, and shipped documentation links keep working.
- **AJAX failures are classified** — network, timeout (via `AbortController`, so a proxy holding
  a connection is distinguishable from an unreachable site), 401/403, 404, 429, 5xx, and
  non-JSON, each naming its likely causes with the HTTP status shown. No response body ever
  reaches the DOM.
- **Polling is production-friendly** — the dashboard poll self-reschedules instead of using
  `setInterval` (so slow responses cannot stack overlapping requests), pauses while the tab is
  hidden, backs off exponentially to a two-minute cap on failure, resets on recovery, and stops
  entirely on a terminal failure instead of emitting an identical error every 15 seconds. Both
  poll loops guard against duplicate timers and clear on unload.
- **Presets are declared narrowest-first**, so the safest option is the default in both the
  wizard and the OAuth consent screen without either surface hard-coding which that is.
  `Presets::get_or_safest()` makes downgrade-on-unknown-id the only way request-supplied preset
  ids resolve, and the consent screen now names the destructive scopes a preset would grant.
- **Version drift is a hard CI failure** — `validate-marketplace.js` now reads all 13
  version-bearing sources, including the ones no JSON parser reaches (the plugin header comment,
  `BRIDGISTIC_VERSION`, `readme.txt`'s Stable tag, both `SERVER_VERSION` constants), and requires
  matching CHANGELOG and `readme.txt` entries.
- **CI uses `npm ci` throughout** and runs all fifteen release gates.
- **Admin dashboard now defaults to light theme** (was dark-by-default); dark now requires an
  explicit toggle or an OS dark preference. Same toggle, same tokens, direction inverted.

### Security

- **SQL write classification no longer relies on prefix matching.** `WITH t AS (…) DELETE FROM
  wp_posts …` classified as a read, so a `db:read` key could execute it — skipping the approval
  queue and the pre-write snapshot. Stacked statements and leading comments moved the real
  keyword off position zero the same way. New `Security\SqlClassifier` strips comments and
  literal contents first, rejects multi-statement input and file-access SQL outright, resolves
  what a CTE actually feeds, and treats unrecognised statements as destructive writes.
- **Filesystem containment is segment-aware.** `strpos($real, $base) === 0` also accepted a
  sibling directory sharing the prefix (`/var/www/html-backup` for a `/var/www/html` install).
  Both `confine()` and `in_sandbox()` now compare whole path segments.
- **Credential files are protected at any scope.** `fs:read` could read `wp-config.php`, which
  carries `AUTH_KEY` and `SECURE_AUTH_KEY` — two of the three inputs to `Security\Crypto`'s key
  derivation — so `fs:read` plus `db:read` was enough to decrypt every stored key secret.
  `wp-config.php`, `.env`, private keys, and similar are now refused for read, write, and delete.
- **`php:execute` is bounded.** Captured output, error entries, and the serialised return value
  are capped at 256 KB with a truncation flag, and the call takes a best-effort 30s wall-clock
  budget, so a runaway script fails the request rather than exhausting PHP memory.
- **OAuth/PKCE validation tightened.** `redirect_uri` must be the exact callback path with no
  userinfo and no port; `code_challenge` must be well-formed base64url S256;
  `code_challenge_method` is carried through the consent form and revalidated server-side;
  verifiers are checked against RFC 7636 before a single-use code is consumed; codes carry an
  issue timestamp so a TTL-ignoring object cache cannot extend their life; and the token endpoint
  is rate-limited per IP.
- **The cloud connector is free, public beta** — `docs/FREE_VS_PAID.md`'s "Remote MCP connector"
  row changed from "No" (free) to "Public beta"; the in-plugin Premium Features page no longer
  lists it as an SaaS-exclusive locked feature.
- `cloud/wrangler.toml` now has real Cloudflare resource IDs (D1 database, KV namespace) instead
  of placeholders — the Worker, database, and KV namespace were already provisioned outside of
  this repo's history; the committed config now matches the live deployment.
- Fixed a stale `Plugin URI` in `bridgistic.php` that still pointed at the deprecated `bridgistic`
  repo instead of `bridgistic-claude-marketplace`.

### Notes

- The `mcp.wpistic.cloud` cloud connector is now a **public beta**, linked from WP Admin
  (Bridgistic Cloud) and free to use. It has **not** had an independent third-party security
  review yet — this is stated explicitly on the Bridgistic Cloud page and the OAuth consent screen
  so users can make an informed call. See `docs/CLOUD_CONNECTOR.md` for full status and the
  remaining checklist (end-to-end test against a live client, the security review).
- Multi-site support (`BRIDGISTIC_CONNECTIONS`) is unchanged in behavior; it's now documented in
  one place (`docs/CONNECT_OTHER_AI.md`) instead of being scattered across per-client docs.

## [1.1.1] — 2026-07-04

Patch: corrected MCP Registry namespace casing (io.github.Shubochandrosarker) in the npm package metadata so registry ownership validation passes. No functional changes.

## [1.1.0] — 2026-07-03

One-click connection and publishing release.

### Added

- **Claude Desktop Extension (`bridgistic.mcpb`)** — real MCPB bundle validated against the official schema (`@anthropic-ai/mcpb`). Double-click to install; Claude Desktop prompts for site URL, key ID, and secret via `user_config` (the secret is stored by the app, marked `sensitive`). Built by `npm run desktop:package`; branded 512px icon included.
- **npm publishing** — `bridgistic-mcp-server` is publish-ready (`files` allowlist, `prepublishOnly` build+typecheck, `mcpName` field, npm-facing README with the registry ownership marker).
- **MCP Registry listing** — `server.json` (`io.github.wordpressistic/bridgistic`, 2025-09-29 schema) so the server appears in the official registry Claude clients can browse.
- **Release automation** (`.github/workflows/release.yml`) — pushing a `v*` tag: verifies all manifests match the tag, builds, tests, validates, creates the GitHub release with `bridgistic.mcpb` + both zips, publishes to npm (when `NPM_TOKEN` is configured) and then to the MCP Registry via GitHub OIDC (no secret needed).
- **CI** (`.github/workflows/ci.yml`) — build, MCP tests, marketplace validation + secret scan, package builds, PHP lint on every PR.
- Claude Setup wizard: **Desktop Extension** connection type is now live — download button for the latest `.mcpb` plus paste-ready values panel.
- Validator: checks `mcpb/manifest.json` (user_config wiring, sensitive secret), `server.json` (name format, npm identifier match, README `mcp-name` marker), and version consistency across all six manifests.

### Changed

- Version 1.1.0 across marketplace, plugin, server, extension, and registry manifests; `docs/CLAUDE_DESKTOP.md` now leads with the one-click path; roadmap updated.

## [1.0.0] — 2026-07-03

First public release of the free Bridgistic distribution.

### Added

**Claude Code marketplace**
- `.claude-plugin/marketplace.json` + `plugins/bridgistic` plugin (manifest, `mcp.json`, README)
- Pre-built self-contained MCP server bundle (`plugins/bridgistic/server/index.js`) so `/plugin install` needs no build step
- Setup package: example Claude Desktop/Code configs, multi-site `connections.example.json`, Windows/macOS/Linux install helpers, troubleshooting guide

**MCP server** (`mcp-server/`)
- 43 WordPress tools over stdio (loopback-guarded HTTP for local dev)
- `BRIDGISTIC_SITE_URL` / `BRIDGISTIC_TRANSPORT` / `BRIDGISTIC_LOG_LEVEL` env names (legacy names still accepted)
- Startup configuration validation with actionable stderr messages; `.env.example`
- esbuild bundling (`npm run bundle`), type-check validation, contract + integration tests

**WordPress plugin** (`wordpress-plugin/bridgistic/`)
- New WordPressistic-branded admin dashboard (modular `admin/` layer, scoped assets, dark/light themes, reduced-motion support)
- Claude Setup: 5-step wizard (client → permission preset → key → config → live pipeline test)
- Keys & Scopes: scope badges, rotate-secret-in-place, soft revoke + re-enable, per-scope advanced creation
- Health Check: 16 diagnostics (REST, namespace, WAF, HMAC self-test, SSL, permalinks, versions, sandbox, tables, scopes, config, time drift) with score and secret-free debug report
- Logs: filterable audit trail (read/write/approval/failed/security/developer)
- Snapshots: manual creation, restore with warning, free-tier cap (50)
- Playbooks: 4 built-in manual routines, saved-playbook runner (dry-run supported), schedule management
- Export Package: zip with configs + install scripts; secrets embedded only within the 2-minute post-creation window and with explicit confirmation
- Premium Features screen: display-only preview of Bridgistic SaaS (no billing/unlock code)
- Additive security-layer methods: `KeyStore::set_enabled()`, `KeyStore::rotate_secret()`, `AuditLog::query()/count()/latest()`

**Tooling & docs**
- `npm run validate` (structure, manifests, secret scan), `npm run package`, `npm run desktop:package` (`.mcpb`-ready layout, honestly labeled as draft)
- Docs: INSTALL, CLAUDE_DESKTOP, CLAUDE_CODE, WORDPRESS_SETUP, SECURITY, FREE_VS_PAID, ROADMAP

### Changed
- Key creation UI no longer exposes billing tiers (free version keeps plain rate limits); readme repositioned for the free local version
- Example connection files genericized (`example.com` hosts only)

### Security
- No SaaS/remote-connector code paths included; HMAC, scope, approval, and snapshot logic unchanged from the audited core
