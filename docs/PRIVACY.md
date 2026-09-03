# Privacy Policy — Bridgistic (free, local version)

**Last updated:** 2026-07-05

This policy covers the free Bridgistic products: the Bridgistic WordPress plugin, the `bridgistic-mcp-server` (npm package and Claude Code plugin), and the `bridgistic.mcpb` Claude Desktop Extension. It does not cover Claude itself — Anthropic's own privacy policy governs what Claude does with your conversation.

## The short version

Bridgistic is local-first. There is no Bridgistic-operated server in this version. Your WordPress credentials and site data travel in exactly one path: **your MCP client (Claude) → the MCP server running on your own computer → your own WordPress site**, over an HMAC-signed HTTPS request. Nothing is sent to WordPressistic, and there is no analytics or telemetry call anywhere in this codebase.

## What data exists, and where it lives

| Data | Where it's stored | Who can see it |
|---|---|---|
| Connection key ID | Your WordPress database (plugin's own tables) | You, as the WP site admin |
| Connection key secret | Encrypted at rest in your WordPress database (libsodium/AES-256-GCM); shown once at creation, never displayed again | You (once, at creation) |
| Site URL / key ID / key secret used to connect | Your local machine only — a local environment variable, a local `connections.json` file, or Claude Desktop's own secure extension storage (for the `.mcpb` path) | You, on your own device |
| Request audit log (who, what, when, status, source IP) | Your WordPress database (plugin's own tables) | You, as the WP site admin |
| Snapshots (pre-change backups) | Your WordPress database / files | You, as the WP site admin |
| Tool call contents (the posts, options, files, or SQL your AI assistant reads or writes) | Your WordPress site, as normal | You, and whatever the AI client (e.g., Claude) does with the response — governed by that client's own privacy policy |

## What we do NOT do

- No telemetry, analytics, or usage tracking of any kind is built into the plugin or the MCP server.
- No data is sent to WordPressistic, Bridgistic, or any third-party server operated by us.
- No advertising, no data resale, no cross-site tracking.
- We never see your key secret, your site's content, or your audit logs — there is nowhere for them to go, since there is no Bridgistic-operated backend in this version.

## Security measures

- Every request between the local MCP server and your WordPress site is HMAC-SHA256 signed, with a ±300-second replay window and single-use nonces.
- Key secrets are encrypted at rest (libsodium `secretbox`, falling back to AES-256-GCM), never stored or logged in plaintext.
- Destructive operations can require human approval and are snapshotted first, so a mistake (yours or the AI's) is reversible.
- The optional PHP execution tool is sandboxed to a single, non-autoloaded directory with direct web execution blocked.

## Data retention

Everything this policy covers — keys, audit logs, snapshots — lives in your own WordPress database and is retained (or deleted) entirely under your control, using the plugin's own admin screens (Keys & Scopes, Logs, Snapshots). Deactivating or uninstalling the plugin removes its data per the standard WordPress uninstall process (see `uninstall.php`).

## Children's privacy

Bridgistic is a developer/site-administration tool. It is not directed at children and does not knowingly collect data from them.

## Changes to this policy

**Bridgistic Cloud** (`mcp.wpistic.cloud`), shipped as a free public beta in v1.2.0, is different, because a hosted relay necessarily sees connection traffic in transit. If you use it: your site URL and a scoped Bridgistic key are stored in the connector's database, with the key secret encrypted at rest under AES-256-GCM; tool requests and responses pass through the relay in memory and are not stored; and the relay's operational logs record a request id, route, an opaque tenant handle, latency, and a status — never the key secret, never an OAuth code or PKCE verifier, and never request or response bodies. The cloud connector is entirely optional: the local connection described above sends nothing to any third party.

## Contact

Questions or a security report: open an issue at [github.com/wordpressistic/bridgistic](https://github.com/wordpressistic/bridgistic/issues), or see [SECURITY.md](SECURITY.md) for responsible disclosure.
