# ChatGPT setup (public beta)

**Status: live, public beta.** ChatGPT only connects to **remote** MCP servers over
HTTPS — unlike Claude, Codex, and Gemini CLI, it cannot launch a local server process, so it can
only reach Bridgistic through the hosted cloud connector (`mcp.wpistic.cloud`). That connector is
deployed and linked from WP Admin (**Bridgistic → Bridgistic Cloud**), but it has not yet had an
independent third-party security review — see [CLOUD_CONNECTOR.md](CLOUD_CONNECTOR.md) for exactly
what "public beta" means here before connecting a site you can't afford to risk.

## What the flow looks like

ChatGPT calls this **Developer Mode** (Settings → Connectors → Advanced → enable Developer Mode →
"Create," then paste a server URL). It requires a **paid ChatGPT plan** (Plus, Pro, Team, Business,
or Enterprise) — it is not available on the free tier.

1. In ChatGPT: **Settings → Connectors → Advanced settings → Developer mode** → toggle on.
2. **Create connector** → paste `https://mcp.wpistic.cloud/mcp` as the server URL, leave transport
   as Streamable HTTP, and leave the OAuth client ID/secret fields blank (Bridgistic's server
   registers itself with ChatGPT automatically the first time — see "Technical notes" below).
3. Approve the connection — this opens the same WordPress consent screen Claude's remote connector
   uses (**WP Admin → pick the site → approve → choose a permission preset**). Approving mints a
   normal scoped Bridgistic key behind the scenes; nothing new to configure on the WordPress side.
4. Verify: ask ChatGPT to run `bridgistic_get_site_info`.

## Multiple WordPress sites

Each WordPress site is its own separate connector entry — repeat "Create connector" for each site
and approve it against that site's WP admin. Rename each connector after adding it (e.g. "Bridgistic
— blog.example.com") since they'll otherwise share the same URL and be hard to tell apart. See
[CONNECT_OTHER_AI.md](CONNECT_OTHER_AI.md) for the equivalent local-server approach used by other
clients.

## Technical notes (for maintainers, not end users)

Bridgistic's cloud connector implements OAuth 2.1 with PKCE (S256) and RFC 7591 dynamic client
registration via `@cloudflare/workers-oauth-provider`, with no pre-shared client secret — ChatGPT's
Developer Mode supports DCR for custom connectors (a static client ID/secret is optional, not
required), so this should work without changes. Not yet confirmed against a real ChatGPT Developer
Mode connection — if you try this and hit a snag, please open an issue, and check these first:

- The `401 + WWW-Authenticate` → `/.well-known/oauth-protected-resource` (RFC 9728) discovery chain
  resolves correctly.
- `/register` accepts whatever `redirect_uris` ChatGPT's client posts (its callback host isn't
  publicly documented as a fixed literal — don't hardcode a redirect allowlist that could reject
  it).
- `code_challenge_methods_supported` advertises `["S256"]`.

If any of those don't hold up, ChatGPT will fail to add the connector even though Claude, Codex,
and Gemini CLI work fine against the same server — this is the most likely source of
client-specific breakage since ChatGPT's registration flow is the least forgiving of the four.
