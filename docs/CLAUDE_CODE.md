# Claude Code setup

Two ways to connect Claude Code to WordPress through Bridgistic: the plugin marketplace (recommended) or a manual MCP config.

## Option A — plugin marketplace (recommended)

Inside Claude Code:

```
/plugin marketplace add wordpressistic/bridgistic
/plugin install bridgistic@bridgistic-marketplace
```

The plugin ships a **pre-built, self-contained server** — no npm install, no build step. It reads your connection from environment variables, so set these in the shell where you launch `claude` (add to `~/.zshrc` / `~/.bashrc` to persist):

```bash
export BRIDGISTIC_SITE_URL="https://example.com"
export BRIDGISTIC_KEY_ID="your_key_id"
export BRIDGISTIC_KEY_SECRET="your_key_secret"
```

Get the values from **WP Admin → Bridgistic → AI / MCP Connections** (the secret is shown once at creation). Restart Claude Code, then verify:

> run bridgistic_get_site_info

To update later: `/plugin update bridgistic`. To remove: `/plugin uninstall bridgistic`.

## Option B — manual MCP config

If you prefer explicit configs, clone + build, then register the server.

```bash
git clone https://github.com/wordpressistic/bridgistic.git
cd bridgistic-claude-marketplace
npm install && npm run build
```

**Per project** — create `.mcp.json` in your project root (never commit real values; prefer `${VAR}` expansion):

```json
{
  "mcpServers": {
    "bridgistic": {
      "command": "node",
      "args": [
        "/absolute/path/to/bridgistic-claude-marketplace/mcp-server/dist/index.js"
      ],
      "env": {
        "BRIDGISTIC_SITE_URL": "${BRIDGISTIC_SITE_URL}",
        "BRIDGISTIC_KEY_ID": "${BRIDGISTIC_KEY_ID}",
        "BRIDGISTIC_KEY_SECRET": "${BRIDGISTIC_KEY_SECRET}"
      }
    }
  }
}
```

**Or via CLI:**

```bash
claude mcp add bridgistic \
  --env BRIDGISTIC_SITE_URL=https://example.com \
  --env BRIDGISTIC_KEY_ID=your_key_id \
  --env BRIDGISTIC_KEY_SECRET=your_key_secret \
  -- node /absolute/path/to/bridgistic-claude-marketplace/mcp-server/dist/index.js
```

The **Bridgistic → AI / MCP Connections** page generates both forms with your real values.

## Multiple sites

Set `BRIDGISTIC_CONNECTIONS=/absolute/path/to/connections.json` (format in `mcp-server/connections.example.json`). Claude targets sites by alias via the `site` tool parameter; `bridgistic_list_sites` shows what's configured.

## Working safely with Claude Code

- Start read-only. Ask Claude to *"inspect the site with bridgistic_get_site_info and summarize"* before granting write scopes.
- Keep `require approval` on for writing keys — Claude's destructive calls pause in **Bridgistic → Approvals** until you decide.
- Dangerous tools (`bridgistic_execute_php`, `bridgistic_db_query`, `bridgistic_fs_write`) need Developer-scope keys — mint one only on sites you control, and prefer `dry_run: true` first.
- Everything Claude does is in **Bridgistic → Logs**.

Problems? **Bridgistic → Health Check** first, then [TROUBLESHOOTING.md](../plugins/bridgistic/package/TROUBLESHOOTING.md).
