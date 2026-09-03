# Bridgistic — Troubleshooting

Work through these in order. Each section lists the symptom, the likely cause, and the fix. The **Bridgistic → Health Check** page in WP Admin automates most of these checks — run it first.

---

## 1. The Bridgistic server doesn't appear in Claude Desktop / Claude Code

**Check Node.js.** Run `node --version`. You need Node **20 or newer**. If the command isn't found, install Node.js from https://nodejs.org/ and restart Claude Desktop.

**Check the server path.** The `args` path in your config must point at a file that actually exists. Test it directly:

```bash
node /absolute/path/to/mcp-server/dist/index.js
```

You should see `bridgistic-mcp-server ... running on stdio` on stderr. If you see `Cannot find module`, the server isn't built — run `npm run build` at the repo root, or use the pre-built bundle at `plugins/bridgistic/server/index.js` instead.

**Restart the app.** Claude Desktop only reads its config at startup. Fully quit (not just close the window) and reopen.

**Check the config file location.**

| OS | Path |
|---|---|
| macOS | `~/Library/Application Support/Claude/claude_desktop_config.json` |
| Windows | `%APPDATA%\Claude\claude_desktop_config.json` |
| Linux | `~/.config/Claude/claude_desktop_config.json` |

**Check the JSON is valid.** A single trailing comma breaks the whole file. Validate with `node -e "JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'))" <path>`.

---

## 2. "No WordPress connections configured"

The server starts but tools fail with this error. It means none of the connection environment variables reached the server.

- Set `BRIDGISTIC_SITE_URL`, `BRIDGISTIC_KEY_ID`, and `BRIDGISTIC_KEY_SECRET` in the `env` block of your config (Claude Desktop) or your shell (Claude Code plugin).
- For multiple sites, set `BRIDGISTIC_CONNECTIONS` to the absolute path of a registry JSON file (see `connections.example.json`).
- In Claude Code, environment variables must be set **before** launching `claude` — `export` them in your shell profile.

---

## 3. Authentication errors (401 / 403 / 409)

The error code from the WordPress plugin tells you exactly what failed:

| Error | Meaning | Fix |
|---|---|---|
| `bridgistic_auth_missing` | Signed headers never arrived | A proxy/WAF is stripping `X-Bridgistic-*` headers — see §5 |
| `bridgistic_auth_stale` | Timestamp outside the ±300s window | Fix the clock on your computer **or** the server (enable NTP). The Health Check page shows server time drift. |
| `bridgistic_auth_key` | Unknown or disabled key | The key was revoked or you copied the wrong `key_id`. Generate a fresh key in **Bridgistic → Claude Setup**. |
| `bridgistic_auth_ip` | Source IP not in the key's allowlist | Edit the key's IP allowlist, or mint a key without one. |
| `bridgistic_auth_signature` | Signature mismatch | The secret is wrong (typo, stale copy, or extra whitespace). Rotate the key and paste the new secret carefully. |
| `bridgistic_auth_replay` | Nonce already used | Almost always a retried request through a caching proxy. Try again; if persistent, exclude the REST API from page caching. |
| `bridgistic_scope` | Key lacks the needed scope | Mint a key with a wider preset, or approve the operation from **Bridgistic → Approvals**. |

---

## 4. "Site URL invalid" or connection refused

- Use the exact site URL shown in **WP Admin → Settings → General** (including `https://` and any subdirectory, e.g. `https://example.com/blog`).
- No trailing slash needed — the server strips it.
- If the site redirects `http → https` or `www → apex`, use the **final** URL. Signed requests do not survive redirects.
- Local sites: `.local` / `.test` domains only resolve on your machine — that's fine for a local MCP server, but the WordPress site must be reachable from where the server runs.

---

## 5. A security plugin or WAF is blocking the REST API

Symptoms: HTML instead of JSON, generic 403s, or `bridgistic_auth_missing` even though your config is correct.

- Allowlist the `/wp-json/bridgistic/v1/` namespace in your security plugin (Wordfence, iThemes, Cloudflare rules, etc.).
- Make sure your host/CDN does not strip custom `X-Bridgistic-*` request headers.
- Exclude `/wp-json/` from full-page caching.
- The **Health Check** page detects this case and names the blocker when it can.

---

## 6. Permalink problems (404 on `/wp-json/...`)

If `https://your-site.com/wp-json/` returns 404, permalinks are set to "Plain". Go to **Settings → Permalinks**, choose any pretty structure, and save (this also flushes rewrite rules).

---

## 7. Time drift (`bridgistic_auth_stale` keeps happening)

Signed requests carry a timestamp valid for ±300 seconds. If either clock is off by more than that, every request fails.

- On your computer: enable automatic time sync in OS settings.
- On the server: ask your host to confirm NTP is running. The Health Check page compares PHP time and database time to help spot this.

---

## 8. Secret lost

Secrets are shown **once** at creation and stored encrypted — nobody can show it again, by design. Open **Bridgistic → Keys & Scopes** and either **rotate** the key (same key ID, new secret) or revoke it and mint a fresh one.

---

## Still stuck?

1. Run **Bridgistic → Health Check** and use **Copy Debug Report** (it contains no secrets).
2. Open an issue: https://github.com/wordpressistic/bridgistic/issues — include the debug report and the exact error message.
