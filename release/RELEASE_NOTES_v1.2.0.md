# Bridgistic v1.2.0

Bridgistic started as a way to connect Claude to WordPress safely. This release
is where it stops being Claude-only: Codex CLI, Gemini CLI, ChatGPT, and any
other MCP client now have a first-class path in, and WooCommerce stores get
real tools instead of raw SQL.

**Upgrade is safe in place.** Your keys, scopes, logs, snapshots, playbooks, and
schedules are preserved. No credential reset is required.

---

## New

### Connect any AI client, not just Claude

The setup wizard is now **AI / MCP Connections**, and it generates a working,
ready-to-paste configuration for each client rather than pointing you at docs:

- **Claude Desktop** — one-click `.mcpb` extension, or a manual config
- **Claude Code** — plugin marketplace install or `.mcp.json`
- **OpenAI Codex CLI** — a `~/.codex/config.toml` block
- **Gemini CLI** — a `~/.gemini/settings.json` entry
- **ChatGPT / remote MCP** — the hosted endpoint plus the OAuth flow
- **Other MCP clients** — raw MCP JSON for anything that launches a local server

Codex and Gemini launch the published `bridgistic-mcp-server` npm package via
`npx`, so there is nothing to clone and nothing to build. Generated snippets
add only the `bridgistic` entry, so merging one into a config you already have
will not clobber your other MCP servers.

### ChatGPT and other remote-only clients

ChatGPT cannot launch a local MCP server, so it connects through
**Bridgistic Cloud** — a hosted relay at `https://mcp.wpistic.cloud/mcp`. You
add the URL in your AI client, approve the connection in your own WordPress
admin, and the connector mints a scoped key for itself. Your credentials never
pass through the AI client.

Remote MCP support varies by client, plan, and workspace. The plugin says so
rather than promising a menu that upstream keeps moving, and points you at your
client's own current documentation for adding a remote server.

### First-class WooCommerce

Eleven structured tools covering products, orders, customers, inventory, and
sales — so an assistant answering "how did we do last month" no longer needs
`db:read` or `php:execute`:

```
bridgistic_woo_list_products      bridgistic_woo_list_orders
bridgistic_woo_get_product        bridgistic_woo_get_order
bridgistic_woo_create_product     bridgistic_woo_update_order_status
bridgistic_woo_update_product     bridgistic_woo_list_customers
bridgistic_woo_inventory_status   bridgistic_woo_get_customer
bridgistic_woo_sales_summary
```

Six new least-privilege scopes (`woo:products:read`, `woo:products:write`,
`woo:orders:read`, `woo:orders:write`, `woo:customers:read`,
`woo:analytics:read`) and a **WooCommerce Manager** preset that grants store
access and nothing else — no plugin management, no database, no filesystem, no
PHP.

Everything goes through WooCommerce's own API, so it works identically whether
or not your store has migrated to High-Performance Order Storage. Order status
changes are treated as destructive — they trigger customer emails and stock
movements that setting the status back does not undo — so they snapshot first
and go through the approval queue. Payment tokens, transaction ids, password
hashes, and customer street addresses are never returned.

If WooCommerce is not active, the store tools report a clear "WooCommerce
unavailable" message and everything else works normally.

### Multi-site builder

Build a `connections.json` for several WordPress sites from one screen: add,
edit, and remove sites with live validation and a live JSON preview, import an
existing file, catch duplicate aliases and duplicate sites, then copy or
download the result. Structural fields persist between visits in your browser;
secrets never do.

### Live connection monitoring

The dashboard now distinguishes four things that used to be one badge:
whether the WordPress bridge is healthy, whether the credential is valid,
whether the MCP server is ready, and whether a real AI client has actually made
a request. A server-side self-test no longer reports "connected" when nothing
has ever connected.

Polling pauses while the browser tab is hidden, backs off when the site is
struggling, stops on a failure that will not resolve itself, and never stacks
overlapping requests against `admin-ajax.php`.

### Diagnostics that name the cause

Requests blocked by Cloudflare, Wordfence, Sucuri, a hosting WAF, ModSecurity,
or a caching layer used to surface as `Unexpected token '<'`. Failures are now
classified — network, timeout, 401/403, 404, 429, 5xx, and non-JSON — and each
explains its likely causes and points at Health Check. Returned HTML is never
inserted into the page.

---

## Improved

- **Security.** Four privilege-escalation paths closed in the WordPress bridge
  and one request-forgery primitive closed in the cloud relay. Details in
  `SECURITY_CHECKLIST_v1.2.0.md`.
- **Health Check** grew from 16 to 23 diagnostics, including `admin-ajax.php`
  reachability, transient behaviour under an object cache, WP-Cron, outbound
  HTTPS, cloud OAuth prerequisites, and WooCommerce availability. The
  downloadable diagnostic report carries more environment detail and is
  redacted on the way out.
- **Permission presets** are ordered safest-first, so the narrowest option is
  the default in both the wizard and the OAuth consent screen. An unknown
  preset id always resolves to Read-only. The consent screen now names the
  destructive scopes a preset would grant instead of only styling it as risky.
- **Test coverage.** MCP contract 251 → 567 assertions; MCP integration 25 → 74;
  cloud 93 → 194 tests; WordPress PHP 0 → 430 behavioural checks running in CI.
  Plus a smoke test against the exact bundle users receive, and structural
  verification of every release archive.
- **Release reliability.** All artifacts are built once from one commit,
  verified unpacked, and checksummed before anything is published.

---

## Upgrade notes

- **No key loss.** Existing keys, scopes, audit logs, snapshots, playbooks, and
  schedules carry over untouched. No schema migration is required.
- **No forced credential reset.** Nothing in this release invalidates an
  existing key. Rotate only if you want to.
- **New WooCommerce scopes are opt-in.** Existing keys keep exactly the scopes
  they already had. To let an assistant work with your store, create a key with
  the WooCommerce Manager preset or add the `woo:*` scopes you need.
- **"Claude Setup" is now "AI / MCP Connections".** The page URL is unchanged,
  so existing bookmarks and links keep working.
- **Bridgistic Cloud remains a public beta.** It has automated coverage for
  tenant isolation, credential encryption, request-forgery protection, and rate
  limiting — but **no independent third-party security review has taken place**,
  and no live end-to-end test has been run against the deployed relay. For sites
  that cannot tolerate that, use the local connection, which never sends
  credentials anywhere except between your own computer and your site.
- **Minimum supported versions are unchanged:** WordPress 6.4+, PHP 8.0+,
  Node.js 20+ for the local MCP server.

### If you use a persistent object cache

Replay protection and one-time secret display both rely on WordPress
transients. Health Check now checks that transients round-trip and warns when
an external object cache is active, because a cache that evicts entries early
weakens replay protection. Worth a look after upgrading.
