=== Bridgistic ===
Contributors: shuvoskr
Tags: mcp, ai, claude, chatgpt, automation
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.5.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Claude, ChatGPT, Codex, Gemini or any MCP client to WordPress with signed requests, scoped keys, approvals, audit logs and snapshots.

== Description ==

Bridgistic is the WordPress side of an MCP (Model Context Protocol) bridge. It lets an AI agent work on a real WordPress site safely, without being handed a full-admin Application Password. The agent can be Claude, ChatGPT, OpenAI Codex, Gemini CLI, Cursor or any other MCP client.

Every request is HMAC-signed and tied to a least-privilege key. Destructive actions can be previewed (dry-run), held for human approval, and are snapshotted first so any change is one call away from a rollback. Usage is metered per key, and playbooks can run unattended on a schedule.

**Free, with every security feature included.** The plugin is the complete secure bridge, and no bridge or security feature is ever gated on a license. Bridgistic Pro (https://bridgistic.app) adds a WPistic license and the hosted Bridgistic dashboard at dashboard.bridgistic.app. A live license on the site opens unlimited schedules, snapshots past 50 and audit export. The skills marketplace, agency dashboard, team permissions and white-label remain planned and are not part of this release.

= What you get =

* HMAC-signed REST API with scoped, least-privilege keys (no Application Password).
* 54 MCP tools: posts, media, users, options (allowlisted), plugins, files, database, WooCommerce, memory, playbooks, schedules, snapshots and approvals.
* Dry-run + human approval queue for destructive operations.
* Automatic snapshot before every destructive write, with one-call rollback.
* Full audit log of every request.
* Per-key rate limiting and basic usage metering.
* Per-site memory and reusable, parameterised playbooks.
* Scheduled playbooks that run unattended via cron (up to 3 active schedules without a live license; unlimited with one).
* A server-side PHP sandbox: executable PHP can only be written to one quarantined directory.

= Part of the WordPressistic Galaxy =

Bridgistic is one of the WordPressistic ecosystem products. It works standalone on any WordPress 6.4+ / PHP 8.0+ site.

== Installation ==

1. Upload the `bridgistic` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload.
2. Activate the plugin.
3. Go to **Bridgistic → AI / MCP Connections** and follow the 5-step wizard to create a scoped key. Copy the secret, because it is shown only once.
4. Connect an MCP client to the key. Two paths, pick one:
   * **Claude Desktop, no install needed:** download the `bridgistic.mcpb` extension from the wizard, double-click it, and paste in the site URL, key id, and secret when prompted. No terminal, no Node.js.
   * **Claude Code, or a manual setup:** requires Node.js 20+ to build/run the MCP server, plus editing your client's MCP config with the site URL, key id, and secret. The wizard generates the exact config and commands for you.

For reliable scheduled playbooks, disable WP-Cron and run a real system cron against `wp-cron.php` (the Schedules screen shows the exact line).

== Frequently Asked Questions ==

= Is this safe to run on a live site? =

That is the entire design goal. Keys are least-privilege, destructive ops can require approval and are snapshotted first, and you have a full audit log plus one-call rollback. Start with a read-only key and widen scopes as you build trust.

= Does it need an Application Password? =

No. Bridgistic uses its own HMAC-signed, scoped keys instead of a full-admin Application Password.

= Where can the agent write PHP? =

Only inside a single quarantined sandbox directory under uploads, with direct web execution blocked. PHP cannot be written anywhere WordPress autoloads from.

== External services ==

The plugin makes no outbound requests on its own. It contacts the external services below only when a site administrator or a connected key uses the related feature.

* **WPistic license server** (`https://api.wpistic.com/api/v1/licenses`). This is used only when a key with the `license:manage` scope calls the license activate, validate or deactivate endpoints, for example from the Bridgistic dashboard. The request contains the license key (on activation only; it is never stored), the site domain, site and home URLs, the WordPress environment type, an installation ID, and the plugin, WordPress and PHP versions. Terms: https://bridgistic.app/terms. Privacy: https://bridgistic.app/privacy.
* **Bridgistic Cloud** (`https://mcp.bridgistic.app`). This is used only when an administrator connects a remote MCP client through **Bridgistic → Bridgistic Cloud** and approves the consent screen. The relay then calls this site's Bridgistic REST API with a scoped key, and every call goes through the same scopes, approvals and audit log as a local connection. Terms: https://bridgistic.app/terms. Privacy: https://bridgistic.app/privacy.
* **Bridgistic dashboard** (`https://dashboard.bridgistic.app`). The plugin sends no data to the dashboard. It only allows that origin to call the `bridgistic/v1` REST namespace from a browser with a signed key (CORS). The origin list can be changed with the `bridgistic_dashboard_origins` filter.

The local MCP server and the Claude Desktop extension run on your own computer and talk directly to your site.

== Changelog ==

= 1.5.4 =
* Added: a live WPistic license on the site opens unlimited schedules, snapshots past 50 and audit export.
* Added: scoped license activate, validate and deactivate routes for the hosted dashboard; responses are signature-verified and raw license keys are never stored.
* Changed: pricing and setup copy now explains the three license gates and the $49 Lifetime Deal, while the skills marketplace, agency/team lines, white-label and Cloud Agent credits stay closed.
* Fixed: feature-gate policy no longer treats coming-soon marketplace, agency or team lines as license-unlocked.

= 1.5.3 =
* Security: SQL dry run no longer executes the statement (MySQL DDL auto-commits); it classifies only and reports whether approval is required.
* Security: MySQL executable comments (/*! ... */) are rejected by the SQL classifier.
* Security: sandbox protective files (.htaccess, web.config, index.php) and all execution-control files can no longer be written or deleted through the bridge; sandbox PHP carries a direct-access guard so it is safe on nginx too.
* Security: PHP execute now goes through the Guard (dry run, per-key approval bound to the exact code).
* Security: control parameters (dry_run, force, approval_id) are rejected from the unsigned query string, and mutating requests accept no query parameters.
* Updated MCP SDK to 1.32.1 and patched vulnerable npm dependencies.
* Fixed: approval polling (GET approvals/status?approval_id=...) was rejected by the unsigned-parameter check; approval_id is now allowed in the query string for that read-only route only.
* Fixed: the plugin sends Access-Control-Allow-Origin itself for https://dashboard.bridgistic.app on the bridgistic/v1 namespace (filter: bridgistic_dashboard_origins), so the hosted dashboard works on hosts that strip the header.
* Fixed: license validate sends the domain and environment bound at activation; activation uses a fresh idempotency key per attempt.
* Added: read-only dashboard routes (audit log with the new audit:read scope, the key's own approvals) and an optional license link to the WPistic license server (license:manage scope). No feature is gated on a license.

= 1.5.2 =
* Hardened hosted OAuth authorization against the short dynamic-client KV propagation window.
* Replaced unhandled authorization exceptions with a safe, readable error response.
* Updated the public Free/Pro pricing presentation and removed internal pricing identifiers from visible copy.

= 1.5.1 =
* Corrective patch release: the MCP Registry namespace is now aligned with the WordPressistic organization ownership and the npm package metadata.
* No functional feature changes from 1.5.0; the free-only premium lock remains enforced.

= 1.5.0 =
* Free-only public build: removed remote license activation, entitlement refresh, billing hooks, and the License admin screen.
* Premium features remain display-only; scheduled playbooks and snapshots always enforce the free edition limits.
* The shipped ZIP excludes the unused WPistic licensing SDK and premium activation UI.
* Synchronized the plugin and package manifests to 1.5.0.

= 1.3.1 =
* Prevented shared WPistic SDK class redeclarations when multiple WPistic plugins are active together.
* Made the packaged MCP bundle smoke test portable across Windows, Linux, and macOS.

= 1.3.0 =
* WPistic licensing system: the plugin now ships with the official WPistic WordPress SDK — activate your license key (Bridgistic → License) to unlock paid plans, with encrypted at-rest state, HMAC-signed validation, token rotation, a 7-day offline grace window, and secure in-dashboard updates from the WPistic platform.
* Free vs paid, clearly drawn: connecting any AI (Claude, ChatGPT, Codex, Gemini, Cursor, any MCP client), HMAC keys, scopes, approvals, audit, snapshots and manual playbooks stay free forever; unlimited scheduled playbooks, advanced snapshots, audit export, the AI skills marketplace, agency dashboard, team permissions and white-label unlock with Starter / Pro / Agency plans.
* Repositioned: "AI / MCP Connections" is the headline — one bridge, any AI model.
* Moved to the WordPressistic GitHub org.

= 1.2.0 =
* AI / MCP Connections: Claude Desktop, Claude Code, OpenAI Codex CLI, Gemini CLI, ChatGPT (remote MCP), other MCP clients, and Bridgistic Cloud each get a first-class setup path with generated, ready-to-paste configuration.
* First-class WooCommerce tools: products, orders, customers, inventory, and sales summaries, behind dedicated least-privilege `woo:*` scopes instead of raw SQL or arbitrary PHP.
* New WooCommerce Manager permission preset; presets are now ordered safest-first and an unknown preset id always resolves to Read-only.
* Multi-Site connections.json builder with import, validation, duplicate detection, and download.
* Live connection monitoring that distinguishes bridge health, credential validity, and real AI-client activity, with hidden-tab pausing and failure backoff.
* Actionable WAF / proxy / security-plugin diagnostics: blocked, timed-out, rate-limited, and non-JSON responses are each explained instead of surfacing a raw parse error.
* Expanded Health Check (23 diagnostics) plus a downloadable, secret-free diagnostic report.
* Security hardening: stricter SQL write classification (stacked statements and mutating CTEs rejected), tightened filesystem sandbox boundary, protected credential files, bounded PHP execution output, and stricter OAuth 2.1 / PKCE validation.

= 1.1.1 =
* Patch: corrected MCP Registry namespace casing. No functional changes.

= 1.1.0 =
* Claude Desktop Extension (`bridgistic.mcpb`) — one-click install, no Node.js required.
* npm publishing and an MCP Registry listing so Claude clients can discover the server.
* Claude Setup wizard: Desktop Extension is now a live connection option.

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.5.4 =
Update the plugin before activating a new license. Without a live license, the free caps remain: 3 enabled schedules, 50 snapshots and an on-screen audit log. A live license opens only the three shipped paid lines; the skills marketplace, agency/team lines and white-label remain closed.

= 1.5.3 =
Security release that fixes five audit findings. Update the plugin and your MCP client together. Custom integrations must now send `dry_run`, `force` and `approval_id` in the signed request body, not in the query string.

= 1.2.0 =
Safe in-place upgrade. Existing keys, scopes, logs, snapshots, playbooks, and schedules are preserved; no credential reset is required. New WooCommerce scopes are opt-in — existing keys keep exactly the scopes they already had. Bridgistic Cloud remains a clearly-labelled public beta.

= 1.1.1 =
Namespace casing patch only — safe to update any time.

= 1.0.0 =
First public release.
