# Installing Bridgistic (free version)

End-to-end setup in five steps. Time: about 10 minutes.

## Prerequisites

| Requirement | Minimum | Check with |
|---|---|---|
| WordPress | 6.4 | WP Admin → Dashboard → Updates |
| PHP | 8.0 | WP Admin → Tools → Site Health → Info |
| Node.js (your computer) | 20 | `node --version` |
| HTTPS on the site | strongly recommended | address bar |

## Step 1 — Install the WordPress plugin

1. Get `bridgistic-wordpress-plugin.zip`:
   - from [Releases](https://github.com/wordpressistic/bridgistic/releases), **or**
   - build it yourself: `npm install && npm run build && npm run package` → `dist/bridgistic-wordpress-plugin.zip`
2. **WP Admin → Plugins → Add New → Upload Plugin** → choose the zip → Install → **Activate**.
3. A new **Bridgistic** menu appears in the sidebar. Open **Bridgistic → Health Check** and confirm the score looks healthy.

Full plugin guide: [WORDPRESS_SETUP.md](WORDPRESS_SETUP.md)

## Step 2 — Create a connection key

1. Open **Bridgistic → AI / MCP Connections**.
2. Pick your client (Claude Desktop / Claude Code / Manual JSON).
3. Pick a permission preset — **start with Read-only**; you can always create a wider key later.
4. Click **Create key**. Copy the **Key ID** and the **Secret** — the secret is shown **once** and stored encrypted.

## Step 3 — Get the MCP server

**Option A — Claude Desktop one-click extension (no build, no terminal):**

Download [`bridgistic.mcpb`](https://github.com/wordpressistic/bridgistic/releases/latest/download/bridgistic.mcpb), double-click it, paste the three values from Step 2 when Claude Desktop prompts, and skip straight to Step 5.

**Option B — Claude Code marketplace (no build needed):**

```
/plugin marketplace add wordpressistic/bridgistic
/plugin install bridgistic@bridgistic-marketplace
```

The plugin ships a pre-built server; you only set environment variables.

**Option C — clone and build (manual config):**

```bash
git clone https://github.com/wordpressistic/bridgistic.git
cd bridgistic-claude-marketplace
npm install
npm run build          # produces mcp-server/dist/index.js
```

## Step 4 — Connect your client

- **Claude Desktop** → [CLAUDE_DESKTOP.md](CLAUDE_DESKTOP.md)
- **Claude Code** → [CLAUDE_CODE.md](CLAUDE_CODE.md)

Either way you'll provide three values: `BRIDGISTIC_SITE_URL`, `BRIDGISTIC_KEY_ID`, `BRIDGISTIC_KEY_SECRET`. The **AI / MCP Connections** page generates finished configs, and **Bridgistic → Export Package** bundles everything into a zip.

## Step 5 — Verify

1. WP Admin: **AI / MCP Connections → Step 5 → Run test** (signed round-trip through the real auth pipeline).
2. Claude: ask *"run bridgistic_get_site_info"* — you should get your site's stack summary.
3. WP Admin: **Bridgistic → Logs** — the `site-info` request appears with status `ok`.

## If something fails

Open **Bridgistic → Health Check**: 16 diagnostics, each failure with a fix. Then see [the troubleshooting guide](../plugins/bridgistic/package/TROUBLESHOOTING.md).

## Updating

- **Plugin:** upload the new zip (WordPress replaces it in place; keys, logs and snapshots survive).
- **Server:** `git pull && npm run build`. Claude Code marketplace users: `/plugin update bridgistic`.
