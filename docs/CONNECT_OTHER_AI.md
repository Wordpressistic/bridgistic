# Connecting more than one site, or a client other than Claude

Bridgistic is not tied to a single WordPress site or a single AI assistant. This page covers both.

## Multiple WordPress sites (agencies, multi-site owners)

Each AI client config (`claude_desktop_config.json`, Claude Code's `.mcp.json`, Codex's
`config.toml`, Gemini CLI's `settings.json`, …) normally points at **one** WordPress site via
three env vars (`BRIDGISTIC_SITE_URL` / `BRIDGISTIC_KEY_ID` / `BRIDGISTIC_KEY_SECRET`). To manage
several sites from the same client, set `BRIDGISTIC_CONNECTIONS` instead of the three single-site
vars, pointing at a JSON registry file:

```json
{
  "my-blog": {
    "siteUrl": "https://blog.example.com",
    "keyId": "wpk_xxxxxxxxxxxxxxxxxxxxxxxx",
    "secret": "wps_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
  },
  "my-shop": {
    "siteUrl": "https://shop.example.com",
    "keyId": "wpk_yyyyyyyyyyyyyyyyyyyyyyyy",
    "secret": "wps_yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy"
  }
}
```

**Easiest path:** WP Admin → **Bridgistic → Multi-Site** is a guided builder for this exact file —
it pre-fills the site you're on, lets you add other sites' alias/URL/key ID/secret one at a time,
shows a live JSON preview, and downloads the finished file. Nothing is sent to this site's server;
alias/URL/key ID are remembered in your browser between visits (not the secrets — paste those
again each time).

To build it by hand instead:

1. Run the **AI / MCP Connections** wizard (WP Admin → Bridgistic → AI / MCP Connections) once per site to mint a
   key for each, then copy each site's key ID/secret into a shared `connections.json` like the one
   above. A starter template with your current site pre-filled is included in every
   **Export Package** download (WP Admin → Bridgistic → Export Package) as
   `connections.example.json` — merge the sites you export from each install into one file.
2. Point `BRIDGISTIC_CONNECTIONS` at the absolute path of that file instead of setting the three
   single-site env vars (see `mcp-server/connections.example.json` for the exact shape any tooling
   expects).
3. Ask the AI assistant to target a specific site with the `site` parameter (the alias key, e.g.
   `"my-blog"`), or call `bridgistic_list_sites` to see everything configured.

This works with every **local** connection type below (Claude Desktop manual config, Claude Code,
Codex CLI, Gemini CLI). It does not apply to the Desktop Extension (`.mcpb`) install, which is
single-site by design, or to the hosted cloud connector, where each site is its own separate
connector entry — see [CLOUD_CONNECTOR.md](CLOUD_CONNECTOR.md).

## Other AI assistants

Bridgistic's MCP server speaks the standard [Model Context Protocol](https://modelcontextprotocol.io/),
so any MCP-capable client can use it, not just Claude. Per-client guides:

| Client | Guide | Works today |
|---|---|---|
| Claude Desktop / Claude Code | [CLAUDE_DESKTOP.md](CLAUDE_DESKTOP.md) / [CLAUDE_CODE.md](CLAUDE_CODE.md) | Yes — local |
| OpenAI Codex CLI | [CODEX_SETUP.md](CODEX_SETUP.md) | Yes — local |
| Gemini CLI | [GEMINI_SETUP.md](GEMINI_SETUP.md) | Yes — local |
| ChatGPT | [CHATGPT_SETUP.md](CHATGPT_SETUP.md) | Public beta — remote only |

The AI / MCP Connections wizard (WP Admin → Bridgistic → AI / MCP Connections) generates ready-to-paste configs
for Claude, Codex, and Gemini CLI directly — the "Manual MCP JSON" option there also works for any
other local MCP-compatible client not listed by name.

**Why ChatGPT is different:** ChatGPT does not run a local server process the way Claude
Desktop/Code, Codex, and Gemini CLI do — it only connects to a **remote** MCP server over HTTPS.
Bridgistic's remote server (`mcp.wpistic.cloud`) is deployed and linked from WP Admin
(**Bridgistic → Bridgistic Cloud**), free to use, but without an independent security review yet.
See [CLOUD_CONNECTOR.md](CLOUD_CONNECTOR.md) for status, and
[CHATGPT_SETUP.md](CHATGPT_SETUP.md) for the flow.
