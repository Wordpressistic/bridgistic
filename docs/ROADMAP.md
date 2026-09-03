# Roadmap — free public version

What's planned for this repository. SaaS plans live elsewhere; this roadmap only covers the free local bridge.

## Shipped (1.0.0)

- Claude Code plugin marketplace with pre-built server bundle
- Local MCP server: 43 tools, stdio (+ loopback-only HTTP for dev), env validation, leveled logging
- WordPress plugin: HMAC auth, scoped keys, approvals, snapshots, audit log, playbooks, schedules
- Admin dashboard: guided AI / MCP Connections wizard, Keys & Scopes, Multi-Site, Health Check (23 diagnostics), Logs, Snapshots, Playbooks, Export Package, Settings
- Export package zip with configs + install scripts
- Validation/packaging tooling and secret scanning

## Shipped (1.1.0)

- **Claude Desktop Extension (`.mcpb`)** — one-click install, validated against the official MCPB schema; site URL / key ID / secret collected via `user_config` (secret stored by Claude Desktop)
- **npm-publishable server** (`npx bridgistic-mcp-server`) with `prepublishOnly` build
- **MCP Registry listing** (`server.json`, `io.github.wordpressistic/bridgistic`) published automatically from CI via GitHub OIDC
- **Release automation** — one tag push builds, tests, validates, creates the GitHub release with all assets, publishes npm + registry
- CI on every PR: build, tests, marketplace validation + secret scan, PHP lint

## Shipped in v1.2.0 (see CHANGELOG.md for the full list)

- **Bridgistic Cloud page** (WP Admin, tagged Beta) — the previously code-only, undiscoverable cloud OAuth connector now has a real entry point, with an explicit no-security-review-yet warning shown both there and on the OAuth consent screen
- **Worker-level rate limiting** for the cloud connector (`cloud/src/rate-limit.ts`) — 120 req/min/IP on `/mcp`, 20 req/min/IP on the OAuth handshake routes
- **Multi-Site page** — a guided `connections.json` builder in WP Admin (pre-fills this site, add other sites' alias/URL/key ID/secret, live JSON preview, copy/download), replacing the previously fully hand-edited file workflow
- **Live dashboard connection status** — polls every 15s and toasts when a request actually lands, instead of a value computed once at page load
- **Categorized AJAX error messages** — a WAF/firewall/proxy blocking a request (common on managed hosts) now says so instead of surfacing a raw JSON-parse error
- **AI / MCP Connections step 5 split into "server check" / "client check"**, the latter polling until the AI client's first real request lands
- **First-activation redirect** to AI / MCP Connections
- **End-to-end test coverage for the cloud connector's OAuth handshake** (`/authorize` → `/wp-callback` → tenant upserted → tools registered) and a CI check that `cloud/src/tools/*.ts` hasn't drifted from `mcp-server/src/tools/*.ts`
- Per-field copy buttons on the Desktop Extension config panel
- Admin dashboard now defaults to light theme; OAuth consent screen re-themed to match

## Next (1.2)

- Submission to Anthropic's Claude Desktop extension directory (in-app browsing) — needs a published privacy policy (`docs/PRIVACY.md`, done) plus the interest-form submission itself
- Extension signing (`mcpb sign`) with a project certificate
- Health Check: PHP zip extension check, object-cache nonce-storage warning, downloadable report file
- i18n: complete translator-ready strings and a `languages/` refresh
- A Claude Code onboarding skill that collects site URL / key / secret conversationally instead of requiring hand-edited shell env vars (closes the GUI gap with the Desktop `.mcpb` path)

## Cloud connector (`cloud/`) — deployed, public beta, linked in WP Admin

`mcp.wpistic.cloud`: a hosted, multi-tenant MCP relay so connecting is "paste
one URL, approve in your own WP admin" — no local server, no Node.js, no
copy-pasted secrets. The WordPress side (a small OAuth 2.1 authorization
server, `includes/class-oauth.php` + the consent screen) and the
Cloudflare Worker (`cloud/`, built on `agents/mcp` +
`@cloudflare/workers-oauth-provider`) are both written, have an automated
test suite (`cloud/test/`, 77 tests including per-IP rate limiting), and the
Worker + its D1 database + KV namespace are provisioned and deployed. It is
now **linked from WP Admin** (a "Bridgistic Cloud" nav item, tagged Beta) and
free to use — see [`docs/CLOUD_CONNECTOR.md`](CLOUD_CONNECTOR.md) for exactly
what's confirmed live vs. what still needs a manual check (the
`TENANT_ENC_KEY` secret, the DNS route). The free-vs-paid decision in
`docs/FREE_VS_PAID.md` is resolved (free, beta) and Worker-level rate
limiting shipped; what's left before this is a fully vetted, generally
announced feature is a real end-to-end test against a live client and an
independent security review of the OAuth relay + D1 tenant store — both the
in-plugin UI and the docs say so explicitly in the meantime.

## Multi-client MCP support — local clients shipped, remote-only clients in beta

Bridgistic speaks standard MCP, so it isn't Claude-only. **Shipped**: the
The AI / MCP Connections wizard now generates ready-to-paste configs for **OpenAI Codex
CLI** and **Gemini CLI** (both run Bridgistic locally, same as Claude
Desktop/Code — see `docs/CODEX_SETUP.md` / `docs/GEMINI_SETUP.md`) in
addition to Claude. **Beta**: **ChatGPT** only supports remote MCP
connectors, so it needs the cloud connector above — see
`docs/CHATGPT_SETUP.md` for the now-live flow. The consumer
Gemini app and Gemini Enterprise have their own, more restrictive
connector models (see `docs/GEMINI_SETUP.md`) and aren't currently
self-serve targets.

## Later (1.x)

- WordPress.org plugin directory submission
- Multisite network admin support
- More built-in manual playbooks (community-suggested, safety-reviewed)
- Optional webhook notifications for pending approvals (email/Slack)
- Independent security review of the cloud connector's OAuth relay and D1
  tenant store (see `docs/CLOUD_CONNECTOR.md`) — the last item blocking it
  from moving past public beta

## Explicit non-goals for this repo

- AI skills marketplace and SEO/AIO/Schema skills (SaaS)
- Billing, team permissions, white-label, agency dashboards (SaaS)

Suggestions welcome via [GitHub issues](https://github.com/wordpressistic/bridgistic/issues).
