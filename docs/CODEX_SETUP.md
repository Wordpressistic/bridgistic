# OpenAI Codex CLI setup

Codex CLI runs Bridgistic as a local MCP server, the same way Claude Code does. No cloud connector
needed — this works today, for free.

## Requirements

- [Codex CLI](https://developers.openai.com/codex/cli) installed and signed in.
- Node.js 20+ (for `npx` to fetch `bridgistic-mcp-server`).
- A Bridgistic key from **WP Admin → Bridgistic → AI / MCP Connections** (it works
  for any MCP client — pick **OpenAI Codex CLI** as the connection type on step 1, which fills the
  values below for you).

## Config

Add this to `~/.codex/config.toml` (or a project-local `.codex/config.toml`, for trusted projects
only). The section name is `mcp_servers` with an underscore — `mcp-servers` is silently ignored by
Codex.

```toml
[mcp_servers.bridgistic]
command = "npx"
args = ["-y", "bridgistic-mcp-server"]
env = { BRIDGISTIC_SITE_URL = "https://example.com", BRIDGISTIC_KEY_ID = "wpk_...", BRIDGISTIC_KEY_SECRET = "wps_..." }
```

Restart Codex, then verify:

> run bridgistic_get_site_info

If Codex doesn't pick up the server, check `startup_timeout_sec` (older Codex builds may also need
`[features] experimental_use_rmcp_client = true` to load MCP servers at all — see Codex's own
release notes if the section above is silently ignored).

## Multiple WordPress sites

Replace the three `BRIDGISTIC_*` env vars with `BRIDGISTIC_CONNECTIONS = "/absolute/path/to/connections.json"`
pointing at a multi-site registry — see [CONNECT_OTHER_AI.md](CONNECT_OTHER_AI.md).

## Remote (cloud connector)

Codex also supports remote Streamable-HTTP MCP servers with OAuth (`codex mcp add bridgistic --url
https://mcp.wpistic.cloud/mcp` followed by `codex mcp login bridgistic`). Bridgistic's hosted
connector is deployed and is a free public beta — see [CLOUD_CONNECTOR.md](CLOUD_CONNECTOR.md)
for status. The local config above is the supported path until that opens up.

## Working safely with Codex

Same rules as [Claude Code](CLAUDE_CODE.md#working-safely-with-claude-code): start with a
read-only key, keep approval on for writes, and only mint a Developer-scope key
(`bridgistic_execute_php`, `bridgistic_db_query`, `bridgistic_fs_write`) on sites you control.
Everything Codex does is visible in **WP Admin → Bridgistic → Logs**.

Problems? **Bridgistic → Health Check** first, then
[TROUBLESHOOTING.md](../plugins/bridgistic/package/TROUBLESHOOTING.md) — the HMAC/permalink/WAF
error table there applies to every client, not just Claude.
