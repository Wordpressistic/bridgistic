# Security model

Bridgistic connects AI tooling to production websites, so the security posture is deliberately strict. This document describes the mechanisms and the reasoning.

## Threat model

The bridge defends against:

1. **Credential theft in transit** — secrets never travel on the wire.
2. **Replay of captured requests** — timestamp window + single-use nonces.
3. **Over-privileged automation** — least-privilege scopes per key.
4. **AI mistakes on destructive operations** — dry-run, approvals, automatic snapshots, rollback.
5. **Database compromise of the WordPress site** — secrets encrypted at rest.
6. **Unaccountable changes** — tamper-evident audit log.

It does *not* replace transport security (use HTTPS) or WordPress core hardening.

## Request authentication (HMAC)

Every request from the MCP server carries four headers:

```
X-Bridgistic-Key         public key id
X-Bridgistic-Timestamp   unix seconds
X-Bridgistic-Nonce       random per-request id
X-Bridgistic-Signature   hex HMAC-SHA256
```

The signature covers `METHOD \n PATH \n TIMESTAMP \n NONCE \n sha256(body)`. The plugin recomputes it with the key's secret and compares in constant time (`hash_equals`). Rejections:

| Condition | Error |
|---|---|
| Timestamp outside ±300 s | `bridgistic_auth_stale` |
| Unknown/disabled key | `bridgistic_auth_key` |
| Source IP not in key's allowlist | `bridgistic_auth_ip` |
| Signature mismatch | `bridgistic_auth_signature` |
| Nonce already used | `bridgistic_auth_replay` |

This is strictly stronger than a bearer Application Password: a captured request cannot be replayed and never contains the secret.

## Why secrets are encrypted, not hashed

A common question. Password-style hashing is impossible here: **HMAC verification requires the live secret** on the server to recompute the signature. So the plugin:

- stores secrets **encrypted at rest** — libsodium `secretbox` when available, otherwise AES-256-GCM (both authenticated encryption);
- derives the encryption key from a per-site pepper + WordPress auth salts, or from `BRIDGISTIC_ENC_KEY` in `wp-config.php` if you define it (recommended — it moves key material out of the database entirely);
- shows the plaintext secret **exactly once** at creation/rotation, in the response to the admin who created it, and never again;
- never writes secrets to logs, exports (without explicit opt-in inside a 2-minute post-creation window), page source, or debug reports.

## Scopes: least privilege

Every key carries an explicit scope list (19 scopes, e.g. `posts:read`, `options:write`, `php:execute`). Every REST controller checks the scope **before** the operation; denials are audit-logged. The admin UI presets map to increasing trust: Read-only → Content Manager → Safe Admin (approval on all writes) → Developer Mode (everything, approval on destructive ops, loudly labeled).

## The Guard: dry-run → approval → snapshot → execute

Destructive operations pass through a pipeline:

1. **Dry-run** available for previewing effects (rolled-back transaction for SQL).
2. **Approval** — if the key requires it, the operation is queued; a human decides in **Bridgistic → Approvals**; the agent retries with the approval id.
3. **Automatic snapshot** of the target (post, option, tables, file) before the write.
4. **Execute**, with the result audit-logged.

Rollback is one call (`bridgistic_snapshot_restore`) or one click in WP Admin.

## Dangerous tools

`bridgistic_execute_php`, `bridgistic_db_query` (writes), and `bridgistic_fs_write` are the highest-risk surface:

- gated behind `php:execute` / `db:write` / `fs:write` scopes — no preset short of Developer Mode grants them;
- destructive SQL auto-snapshots affected tables; PHP writes are confined to a web-execution-blocked sandbox directory (`.htaccess` deny + `index.php`), never plugins/themes/mu-plugins;
- combined with `require_approval`, every such operation waits for a human.

**Recommendation:** never mint a Developer Mode key for a site you don't fully control.

## Audit log

Every operation records: key id, action, status, source IP, timestamp, a **SHA-256 hash of the parameters** (not the raw values), and a short human summary. Retention 90 days (pruned daily). The Logs screen and health debug report expose no secrets by design.

## Admin UI security

- Every screen and handler requires `manage_options`.
- Every state-changing request carries a WordPress nonce (`check_admin_referer` / `check_ajax_referer`).
- All input sanitized (`sanitize_text_field`, `sanitize_key`, `absint`, …), all output escaped (`esc_html`, `esc_attr`, `esc_url`).
- Admin assets load only on Bridgistic screens.

## MCP server side

- stdio transport by default (local, no listening port).
- The optional HTTP transport binds to `127.0.0.1` and refuses non-loopback requests unless you explicitly set `BRIDGISTIC_HTTP_TOKEN` (constant-time bearer check). Do not expose it publicly; the remote connector is a SaaS feature for a reason.
- Logs go to stderr only and never include secrets. `connections.json` should be readable only by your user.

## Hardening checklist

- [ ] HTTPS on the site
- [ ] `BRIDGISTIC_ENC_KEY` defined in `wp-config.php`
- [ ] Read-only or Content Manager keys for daily use
- [ ] `require approval` on any writing key for a production site
- [ ] IP allowlist on keys when your machine has a stable address
- [ ] Real system cron (not WP-Cron) if you use schedules
- [ ] Health Check score reviewed after any hosting change

## Reporting a vulnerability

Email **support@wordpressistic.com** with details. Please do not open public issues for security reports; we'll credit you in the changelog once fixed.
