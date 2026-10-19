# Bridgistic repositories

Bridgistic is published from two public GitHub repositories. Each has a single, distinct job.

| | [Wordpressistic/bridgistic](https://github.com/Wordpressistic/bridgistic) | [Wordpressistic/bridgistic-claude-marketplace](https://github.com/Wordpressistic/bridgistic-claude-marketplace) |
|---|---|---|
| Role | **Canonical source.** All development: plugin, MCP server, Claude integrations, cloud relay, website | **Official marketplace listing repo.** The repo submitted to Claude and other plugin marketplaces |
| Changes land | Pull requests to `main` | Only as a version sync from a canonical release (no independent development) |
| Releases | Tag `vX.Y.Z` on `main` with every artifact attached | The same tag and the same artifacts (identical checksums), synced after each canonical release |
| Website `bridgistic.app` | Source (`index.html`, `functions/`, legal pages, `scripts/build-site.sh`) | Not served from here |
| Website download links | — | The site links its version pill, release notes and download buttons to this repo's release |
| npm `bridgistic-mcp-server` / MCP Registry | Published from here (manual release) | Historical publications of 1.5.1 and 1.5.2 |

## Where each kind of update goes

| You are updating… | Repo | How |
|---|---|---|
| Plugin, MCP server, cloud, docs, website | `Wordpressistic/bridgistic` | Pull request to `main` |
| A product release | `Wordpressistic/bridgistic` | Follow [RELEASING.md](RELEASING.md) |
| The marketplace listing | `Wordpressistic/bridgistic-claude-marketplace` | Version-sync pull request after the canonical release, then the same tag and assets (see [RELEASING.md](RELEASING.md#4-sync-the-marketplace-listing-repo)) |
| Pro / paid features | `Wordpressistic/bridgistic` | Pro is the WPistic license and dashboard layer on top of the same codebase; see [FEATURE-MATRIX.md](FEATURE-MATRIX.md) |

## History

- Until v1.5.2 (2026-10-05), product releases were cut in `bridgistic-claude-marketplace`.
- On 2026-10-13 that product work was merged into this repo (merge commit `f683219`), which became the canonical source.
- v1.5.3 (2026-10-19) was the first release cut here and synced to the marketplace listing repo with identical assets.

## Registry metadata

npm and MCP Registry entries can't be changed for a version that is already published. npm `bridgistic-mcp-server@1.5.2` and its registry entry still reference the marketplace repo. From 1.5.3, `repository.url` in `package.json`, `mcp-server/package.json`, `server.json`, `mcpb/manifest.json` and `openai-plugin/plugin.json` points at this repo. The next npm and MCP Registry publication carries it (see [NPM_TRUSTED_PUBLISHER.md](NPM_TRUSTED_PUBLISHER.md)).
