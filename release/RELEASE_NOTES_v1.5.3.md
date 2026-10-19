# Bridgistic v1.5.3

Bridgistic 1.5.3 is a security release. It closes five findings from the October 2026 security audit (S1–S5), updates the MCP SDK, and adds the plugin endpoints used by the hosted Bridgistic dashboard. We recommend that every site running 1.5.2 or older update.

## Highlights

- **SQL dry runs are now classify-only.** A `db_query` dry run no longer executes the statement inside a rolled-back transaction. MySQL DDL commits implicitly, so that transaction could not protect the site. The Guard now reports `requires_approval` without running anything.
- **PHP execution goes through the Guard.** `php:execute` honours `dry_run`, which never evaluates the code, and the per-key `require_approval` setting. Each approval is bound to the SHA-256 of the exact code. The `bridgistic_execute_php` MCP tool gains `dry_run` and `approval_id`.
- **Dashboard support in the plugin.** New read-only routes return the audit log (new scope `audit:read`) and the calling key's own approvals. An optional license link connects to the WPistic license server (new scope `license:manage`), and its responses are HMAC-verified. The raw license key is never stored. No plugin feature is gated on a license.

## Security fixes

- **S1:** a write-SQL dry run never executes.
- **S2:** MySQL/MariaDB executable comments (`/*! … */`, `/*!50000 … */`, `/*M! … */`) are now rejected as `forbidden`.
- **S3:** the bridge can no longer write or delete the sandbox protection files (`.htaccess`, `.user.ini`, `php.ini`, `web.config`, the sandbox `index.php`) at any scope. The protection files are re-asserted, and an IIS `web.config` is added. Sandbox PHP now starts with an `ABSPATH` guard, so a direct web request exits even on nginx.
- **S4:** `php:execute` is routed through the Guard (dry run and code-bound approval).
- **S5:** the query string is not covered by the HMAC signature, so control parameters (`dry_run`, `force`, `approval_id`, `__ctx`, `__internal_token`) are rejected there. POST and DELETE requests accept no query parameters (`rest_route` excepted).

## Fixes

- Approval polling: `GET /bridgistic/v1/approvals/status?approval_id=…` was blocked by the new S5 check. `approval_id` is now allowed in the query string for that read-only route only. `POST /approvals/status` with the id in the signed body also works.
- Hosted dashboard CORS: the plugin now sends `Access-Control-Allow-Origin` itself for `https://dashboard.bridgistic.app` (and the legacy `https://app.bridgistic.app`), only on the `bridgistic/v1` namespace and without credentials. Some hosts strip the header that WordPress core normally sends. The origin list can be changed with the `bridgistic_dashboard_origins` filter.
- The `X-Bridgistic-*` headers are added to the allowed CORS headers so a browser dashboard can send signed requests.
- License link: `validate` now sends the domain and environment that were bound at activation, which fixes 403s on local and staging domains. Each activation attempt uses a fresh idempotency key, which fixes a revoked token being replayed after a deactivate.

## Dependencies

- `@modelcontextprotocol/sdk` 1.30 → 1.32.1 (GHSA-6qxp-vccf-f47h) in the MCP server and the cloud connector. In the cloud connector, `agents` goes to 0.28.1 and `wrangler` to 4.151. `npm audit` reports 0 vulnerabilities in both lockfiles.

## Upgrade notes

- **Unsigned query parameters now return HTTP 400 `bridgistic_unsigned_param`.** This applies to clients that put `dry_run`, `force` or `approval_id` in the URL query string, or that send any query parameter on POST or DELETE. The official MCP server, the Claude Desktop extension and Bridgistic Cloud already send these values in the signed body, so no change is needed if you use them. Custom integrations should move these values into the signed JSON body.
- Update the WordPress plugin and the MCP client package together: reinstall `bridgistic.mcpb` in Claude Desktop, or update the Claude Code plugin.
- No database migration is needed. Existing keys, approvals, snapshots and audit history are kept. The new scopes `audit:read` and `license:manage` are opt-in per key.
- Compatibility: WordPress 6.4+ (tested up to 7.1), PHP 8.0+, Node.js 20+ for the npm/stdio server.

## Release assets

| File | What it is |
| --- | --- |
| `bridgistic-wordpress-plugin.zip` | The WordPress plugin. Upload it under Plugins → Add New → Upload Plugin. |
| `bridgistic.mcpb` | One-click Claude Desktop extension (no terminal, no Node.js). |
| `bridgistic-claude-package.zip` | Claude Code / manual setup package with the bundled MCP server and example configs. |
| `bridgistic-openai-plugin.zip` | OpenAI / ChatGPT agent-plugin package pointing at Bridgistic Cloud. |
| `bridgistic-mcp-server-1.5.3.tgz` | The `bridgistic-mcp-server` npm package tarball, attached for verification. |
| `SHA256SUMS.txt` | Checksums for every file (`sha256sum -c SHA256SUMS.txt`). |
| `TEST_REPORT_v1.5.3.md`, `SECURITY_CHECKLIST_v1.5.3.md` | Test report and security checklist for this release. |

## Links

- Website and pricing: https://bridgistic.app
- Connection guide: https://github.com/Wordpressistic/bridgistic/blob/main/docs/CONNECT_BRIDGISTIC.md
- Install guide: https://github.com/Wordpressistic/bridgistic/blob/main/docs/INSTALL.md
- Security model: https://github.com/Wordpressistic/bridgistic/blob/main/docs/SECURITY.md
- Full changelog: https://github.com/Wordpressistic/bridgistic/blob/main/CHANGELOG.md
- Compare: https://github.com/Wordpressistic/bridgistic/compare/029554f6eb16b6682ad2c4baf982a450f3a87b15...v1.5.3 (v1.5.2 was tagged in `Wordpressistic/bridgistic-claude-marketplace`)

Bridgistic is built by WordPressistic LLC. To report a security issue, email hello@insightistic.com.
