# Bridgistic v1.5.4

Bridgistic 1.5.4 connects the public plugin's three paid feature gates to a verified WPistic license on the site. It also makes the $49 Lifetime Deal and the monthly/yearly choices explicit on the public site. The secure bridge remains free, and product lines that are not shipped remain closed.

## Highlights

- **A live license opens three shipped paid lines.** Unlimited schedules, snapshots past the free cap of 50, and `GET /wp-json/bridgistic/v1/audit/export` open only after a live license is activated on that WordPress site.
- **Dashboard license link.** Scoped `GET /license`, `POST /license/activate`, `POST /license/validate` and `POST /license/deactivate` routes support the hosted dashboard. Responses are HMAC-verified, the raw key is never stored, and the activation token and verification key never leave the server over REST.
- **Plan choice is clear.** The public pricing page presents Choose Lifetime · $49, Choose monthly · $12 and Choose yearly · $99. Paddle collects the checkout email; the site no longer asks for it before opening checkout.
- **Closed lines stay closed.** Skills marketplace, agency dashboard, team permissions and white-label are not opened by this release. Cloud Agent credits are not part of the license.

## Upgrade notes

- Update the plugin before activating a license. Without a live license, the free limits remain 3 enabled schedules, 50 snapshots and an on-screen audit log.
- License activation is scoped to the site domain and installation. The key is sent only for activation, then discarded; later validation uses the server-issued activation token.
- No database migration is required. Existing keys, logs, snapshots, playbooks and schedules are preserved.
- Compatibility: WordPress 6.4+, PHP 8.0+, Node.js 20+ for the npm/stdio server.

## Release assets

| File | What it is |
| --- | --- |
| `bridgistic-wordpress-plugin.zip` | The WordPress plugin. Upload it under Plugins → Add New → Upload Plugin. |
| `bridgistic.mcpb` | One-click Claude Desktop extension. |
| `bridgistic-claude-package.zip` | Claude Code / manual setup package with the bundled MCP server and example configs. |
| `bridgistic-openai-plugin.zip` | OpenAI / ChatGPT agent-plugin package pointing at Bridgistic Cloud. |
| `bridgistic-mcp-server-1.5.4.tgz` | The `bridgistic-mcp-server` npm package tarball, attached for verification. |
| `SHA256SUMS.txt` | Checksums for every file. |
| `TEST_REPORT_v1.5.4.md`, `SECURITY_CHECKLIST_v1.5.4.md` | Test report and security checklist for this release. |

## Links

- Website and pricing: https://bridgistic.app
- Connection guide: https://github.com/Wordpressistic/bridgistic/blob/main/docs/CONNECT_BRIDGISTIC.md
- Install guide: https://github.com/Wordpressistic/bridgistic/blob/main/docs/INSTALL.md
- Security model: https://github.com/Wordpressistic/bridgistic/blob/main/docs/SECURITY.md
- Full changelog: https://github.com/Wordpressistic/bridgistic/blob/main/CHANGELOG.md
- Compare: https://github.com/Wordpressistic/bridgistic/compare/v1.5.3...v1.5.4

Bridgistic is built by WordPressistic LLC. To report a security issue, email hello@insightistic.com.
