# Gemini CLI setup

Gemini CLI runs Bridgistic as a local MCP server, the same way Claude Code does. No cloud connector
needed — this works today, for free.

**This is specifically the Gemini CLI (terminal tool).** The consumer Gemini app (web/mobile) does
not support adding a custom MCP server yourself — its connectors are Google-managed partner
integrations only, so Bridgistic can't be self-onboarded there. If your organization uses **Gemini
Enterprise**, it does support a custom remote MCP server, but requires an admin to manually
register an OAuth client in the Cloud console (no self-serve, one-time-URL flow) — contact us if
you need that path.

> Google's CLI tooling in this space has been moving fast; if the file/flag names below have
> changed, check Gemini CLI's own docs and let us know so this page can be updated.

## Requirements

- [Gemini CLI](https://github.com/google-gemini/gemini-cli) installed and signed in.
- Node.js 20+ (for `npx` to fetch `bridgistic-mcp-server`).
- A Bridgistic key from **WP Admin → Bridgistic → AI / MCP Connections** (pick **Gemini CLI** as the
  connection type on step 1, which fills the values below for you).

## Config

Add this to `~/.gemini/settings.json` (user-wide) or a project-local `.gemini/settings.json`:

```json
{
  "mcpServers": {
    "bridgistic": {
      "command": "npx",
      "args": ["-y", "bridgistic-mcp-server"],
      "env": {
        "BRIDGISTIC_SITE_URL": "https://example.com",
        "BRIDGISTIC_KEY_ID": "wpk_...",
        "BRIDGISTIC_KEY_SECRET": "wps_..."
      }
    }
  }
}
```

Restart Gemini CLI, then verify:

> run bridgistic_get_site_info

## Multiple WordPress sites

Replace the `env` block with `"env": { "BRIDGISTIC_CONNECTIONS": "/absolute/path/to/connections.json" }`
pointing at a multi-site registry — see [CONNECT_OTHER_AI.md](CONNECT_OTHER_AI.md).

## Remote (cloud connector)

Gemini CLI also supports remote Streamable-HTTP MCP servers with OAuth discovery — add an entry
with `"httpUrl": "https://mcp.wpistic.cloud/mcp"` instead of `command`/`args`/`env`, and Gemini CLI
handles the OAuth flow itself. Bridgistic's hosted connector is deployed but currently a private
beta — see [CLOUD_CONNECTOR.md](CLOUD_CONNECTOR.md) for status. The local config above is the
supported path until that opens up.

## Working safely with Gemini CLI

Same rules as [Claude Code](CLAUDE_CODE.md#working-safely-with-claude-code): start with a
read-only key, keep approval on for writes, and only mint a Developer-scope key
(`bridgistic_execute_php`, `bridgistic_db_query`, `bridgistic_fs_write`) on sites you control.
Everything Gemini does is visible in **WP Admin → Bridgistic → Logs**.

Problems? **Bridgistic → Health Check** first, then
[TROUBLESHOOTING.md](../plugins/bridgistic/package/TROUBLESHOOTING.md) — the HMAC/permalink/WAF
error table there applies to every client, not just Claude.
