# WordPress plugin setup

The Bridgistic plugin is the WordPress side of the bridge: it verifies signed requests, enforces scopes, queues approvals, snapshots before risky writes, and records the audit log.

## Requirements

- WordPress 6.4+, PHP 8.0+
- HTTPS strongly recommended (plain HTTP is acceptable only for local dev sites)
- Pretty permalinks enabled (Settings → Permalinks — anything except "Plain")

## Install

1. Get `bridgistic-wordpress-plugin.zip` (Releases, or `npm run package` → `dist/`).
2. **WP Admin → Plugins → Add New → Upload Plugin** → select the zip → Install → **Activate**.

Activation creates the plugin's tables (keys, audit log, snapshots, approvals, usage, memory, playbooks, schedules), a protected sandbox directory under uploads, and a per-site signing pepper. No content is modified. The plugin is theme-independent and compatible with Elementor and WooCommerce sites.

## The admin screens

| Screen | What it does |
|---|---|
| **Dashboard** | Connection status, key/log/snapshot stats, quick actions |
| **AI / MCP Connections** | 5-step wizard: client → permission preset → key → config → test |
| **Keys & Scopes** | Key cards with scope badges, rotate/revoke/delete, advanced per-scope creation, IP allowlists |
| **Approvals** | Destructive operations waiting for your decision |
| **Health Check** | 16 diagnostics with pass/warn/fail, fixes, score, copyable no-secrets debug report |
| **Logs** | Filterable audit trail (read / write / approval / failed / security / developer) |
| **Snapshots** | Manual + automatic reversible captures, restore/delete, free-tier limit |
| **Playbooks** | Built-in manual playbooks, saved (Claude-created) playbooks, limited schedules |
| **Export Package** | Downloads a ready-made Claude setup zip |
| **Premium Features** | Locked preview of Bridgistic SaaS — display only, nothing to buy in the plugin |
| **Settings** | Options allowlist and hardening tips |

## Permission presets

| Preset | Can | Approval |
|---|---|---|
| **Read-only** | read site info, posts, users, options | – |
| **Content Manager** | + create/update posts & media, site memory | – |
| **Safe Admin** | + options writes, plugin activation, snapshots, playbooks | every write |
| **Developer Mode** | everything incl. DB, filesystem, PHP execution | destructive ops |

Developer Mode is intentionally loud in the UI — use it only on sites you control.

## Keys: lifecycle and safety

- **Create** in AI / MCP Connections (preset) or Keys & Scopes (individual scopes).
- The **secret is shown once** and stored encrypted (libsodium / AES-256-GCM). Nobody — including the plugin — can display it again.
- **Rotate** replaces the secret in place (same key ID). **Revoke** disables the key instantly but keeps it listed; **Delete** removes it permanently.
- Optional per-key **IP allowlist** and **rate limit**.
- For hardened encryption, add to `wp-config.php`:
  `define( 'BRIDGISTIC_ENC_KEY', 'a-random-string-of-at-least-32-chars' );`

## Scheduled playbooks (limited in free)

Schedules run saved playbooks unattended via WP-Cron. For dependable timing, disable WP-Cron and use a real cron job:

```
define( 'DISABLE_WP_CRON', true );   // wp-config.php
*/5 * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

## Uninstall

Deactivating stops all cron hooks. Deleting the plugin runs `uninstall.php`. Revoke keys first if you're decommissioning a Claude connection.
