# Bridgistic MCP Server

mcp-name: io.github.wordpressistic/bridgistic

Connect Claude (Desktop, Code, or any MCP client) to WordPress **safely**: every request is HMAC-SHA256-signed with a scoped, least-privilege key minted by the free [Bridgistic WordPress plugin](https://github.com/wordpressistic/bridgistic). Destructive operations support dry-run, human approval queues, and automatic snapshots with one-call rollback — and everything is audit-logged on the WordPress side.

## Quick start

1. Install the [Bridgistic WordPress plugin](https://github.com/wordpressistic/bridgistic) on your site and activate it.
2. In **WP Admin → Bridgistic → Claude Setup**, create a key (start with the Read-only preset). The secret is shown **once**.
3. Run the server with your connection in the environment:

```bash
npx bridgistic-mcp-server
```

```json
{
  "mcpServers": {
    "bridgistic": {
      "command": "npx",
      "args": ["-y", "bridgistic-mcp-server"],
      "env": {
        "BRIDGISTIC_SITE_URL": "https://example.com",
        "BRIDGISTIC_KEY_ID": "your_key_id",
        "BRIDGISTIC_KEY_SECRET": "your_key_secret"
      }
    }
  }
}
```

4. Verify: ask Claude to run `bridgistic_get_site_info` (read-only), then check **WP Admin → Bridgistic → Logs**.

Prefer zero-terminal setup? Grab the one-click Claude Desktop extension (`bridgistic.mcpb`) from the [releases page](https://github.com/wordpressistic/bridgistic/releases).

## Configuration

| Variable | Required | Purpose |
|---|---|---|
| `BRIDGISTIC_SITE_URL` | yes* | Site address from WP Admin → Settings → General |
| `BRIDGISTIC_KEY_ID` | yes* | Public key id (`wpk_…`) |
| `BRIDGISTIC_KEY_SECRET` | yes* | Key secret (`wps_…`), shown once at creation |
| `BRIDGISTIC_CONNECTIONS` | no | Absolute path to a multi-site registry JSON (alias → siteUrl/keyId/secret) |
| `BRIDGISTIC_TRANSPORT` | no | `stdio` (default) or `http` (local dev only, loopback-guarded) |
| `BRIDGISTIC_LOG_LEVEL` | no | `error` \| `warn` \| `info` \| `debug` — stderr only, never logs secrets |

\* Either the three single-site variables **or** `BRIDGISTIC_CONNECTIONS` (both can be combined; the agent targets sites by alias).

## What's inside

43 tools across content (posts, media, users), site administration (options with allowlists, plugins, files), safety (snapshots, approvals, dry-run), intelligence (site memory, usage), and automation (playbooks, schedules) — each gated server-side by the key's scopes. The WordPress plugin is the enforcement point; a leaked tool call without a valid signature is useless.

## Security model

- Secrets never travel on the wire — requests carry an HMAC-SHA256 signature over method/path/timestamp/nonce/body-hash.
- ±300 s timestamp window plus single-use nonces block replays.
- Scopes are checked before every operation; denials are audit-logged.
- Keys can require human approval: destructive calls pause until someone decides in WP Admin.

Full docs: [SECURITY.md](https://github.com/wordpressistic/bridgistic/blob/main/docs/SECURITY.md) · [Troubleshooting](https://github.com/wordpressistic/bridgistic/blob/main/plugins/bridgistic/package/TROUBLESHOOTING.md)

## License

GPL-2.0-or-later © [WordPressistic](https://wordpressistic.com)
