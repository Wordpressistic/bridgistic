=== Bridgistic ===
Contributors: shuvoskr
Tags: mcp, ai, claude, chatgpt, gemini, automation, rest-api
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.3.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to any AI model — Claude, ChatGPT, OpenAI Codex, Gemini, Cursor, or any MCP client — with signed requests, scoped keys, approvals, audit logs, and snapshots. Free core, paid plans under the WPistic licensing system.

== Description ==

Bridgistic is the WordPress side of an MCP (Model Context Protocol) bridge. It lets an AI agent — Claude, Claude Cowork, OpenAI Codex, Gemini CLI, or any other MCP client — operate a real WordPress site safely, instead of handing over a full-admin Application Password and hoping for the best.

Every request is HMAC-signed and tied to a least-privilege key. Destructive actions can be previewed (dry-run), held for human approval, and are snapshotted first so any change is one call away from a rollback. Usage is metered per key, and playbooks can run unattended on a schedule.

**Free plan, forever:** the complete secure bridge — connect Claude, ChatGPT, Codex, Gemini, Cursor or any MCP client, with every security feature included. **Paid plans** (Starter / Pro / Agency) add unlimited scheduled playbooks, advanced snapshots, audit export, the AI skills marketplace, agency dashboard, team permissions and white-label — activated with a WPistic license key under Bridgistic → License.

= What you get =

* HMAC-signed REST API with scoped, least-privilege keys (no Application Password).
* Structured tools: posts, media, users, options (allowlisted), plugins, files.
* Dry-run + human approval queue for destructive operations.
* Automatic snapshot before every destructive write, with one-call rollback.
* Full audit log of every request.
* Per-key rate limiting and basic usage metering.
* Per-site memory and reusable, parameterised playbooks.
* Scheduled playbooks that run unattended via cron.
* A server-side PHP sandbox: executable PHP can only be written to one quarantined directory.

= Part of the WordPressistic Galaxy =

Bridgistic is one of the WordPressistic ecosystem products. It works standalone on any WordPress 6.4+ / PHP 8.0+ site.

== Installation ==

1. Upload the `bridgistic` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload.
2. Activate the plugin.
3. Go to **Bridgistic → Claude Setup** and follow the 5-step wizard to mint a scoped key. Copy the secret — it is shown once.
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

== Changelog ==

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

= 1.2.0 =
Safe in-place upgrade. Existing keys, scopes, logs, snapshots, playbooks, and schedules are preserved; no credential reset is required. New WooCommerce scopes are opt-in — existing keys keep exactly the scopes they already had. Bridgistic Cloud remains a clearly-labelled public beta.

= 1.1.1 =
Namespace casing patch only — safe to update any time.

= 1.0.0 =
First public release.
