# Publishing the Bridgistic MCP server 1.5.9 (npm + MCP Registry)

Status 2026-10-26: **not published. Nothing in this patch publishes anything.**

## What exists today

| Thing | State |
|---|---|
| npm `bridgistic-mcp-server` | latest published: **1.5.2** |
| `mcp-server/` source | removed from this repo in `a911a2a` (Oct 23). Last commit with it: `505df28`, `package.json` version **1.5.4**. Not present in Shubochandrosarker/bridgistic either (it has `legacy/mcp-server-1.0.0` and the hosted `apps/mcp`, a different Worker). |
| MCP Registry | no `server.json` and no `mcpName` in `package.json` at any ref. Template: `release/mcp/server.json` (name `io.github.wordpressistic/bridgistic`). |
| Bundled server in `plugins/bridgistic/server/index.js` | reports `VERSION = "1.5.4"` |

So there is **no 1.5.9 MCP server source to publish yet.** Decision for Shuvo: restore `mcp-server/` (from `505df28`) into this repo, bump to 1.5.9, add `"mcpName": "io.github.wordpressistic/bridgistic"` to its `package.json`, commit, then run the workflow with `source_ref` = that commit. The workflow refuses to run if the version in `package.json` is not the requested one; it never rewrites versions.

## GitHub Actions billing

Actions are blocked: the last five `Release` runs on Wordpressistic/bridgistic (latest 25 Oct 19:13 UTC = 26 Oct 01:13 Dhaka) ended with *"The job was not started because your account is locked due to a billing issue."* This repo is public, but the lock is on the account, so **this workflow will not start either** until the billing issue on the Wordpressistic org is fixed (Settings → Billing and plans). A dry run is also a job, so it is blocked too. Until then, use the manual commands below.

## Workflow (`.github/workflows/publish-mcp.yml`)

- Trigger: `workflow_dispatch` only. No push/tag trigger.
- `dry_run` defaults to **true** → build, test, `npm publish --dry-run`, `mcp-publisher validate`. Uploads nothing.
- Real publish needs `dry_run=false` and the `mcp-publish` environment (add required reviewers on it so Shuvo approves each run), the `NPM_TOKEN` secret, and GitHub OIDC (`id-token: write`) for the Registry.
- Refuses if the version is already on npm.

## Manual commands (run only with Shuvo's OK)

```bash
# 0. Source at 1.5.9 (after the restore commit above)
git clone https://github.com/Wordpressistic/bridgistic && cd bridgistic
git checkout <restore-commit>
cd mcp-server
node -p "require('./package.json').version"   # must print 1.5.9
node -p "require('./package.json').mcpName"   # must print io.github.wordpressistic/bridgistic

# 1. Build + test + dry run
npm ci && npm run build && npm run validate && npm test
npm publish --dry-run --access public          # review the file list

# 2. npm (interactive login as an owner of bridgistic-mcp-server, 2FA)
npm login
npm publish --access public

# 3. MCP Registry
curl -fsSL https://github.com/modelcontextprotocol/registry/releases/latest/download/mcp-publisher_$(uname -s | tr A-Z a-z)_amd64.tar.gz | tar xz mcp-publisher
cp ../release/mcp/server.json ./server.json    # versions must say 1.5.9
./mcp-publisher login github                   # device flow, as a Wordpressistic org member
./mcp-publisher publish server.json

# 4. Verify
npm view bridgistic-mcp-server version
curl -s "https://registry.modelcontextprotocol.io/v0/servers?search=io.github.wordpressistic/bridgistic"
```
