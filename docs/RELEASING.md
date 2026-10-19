# Releasing Bridgistic

Releases are cut by hand from `Wordpressistic/bridgistic`. Every GitHub Actions workflow here is manual-only (`workflow_dispatch`), so pushing a tag or publishing a release doesn't start any workflow.

## 1. Prepare `main`

1. Bump the version everywhere. `npm run validate` checks all 14 version sources: the root, `mcp-server` and `cloud` `package.json` files, `server.json` (twice), `mcpb/manifest.json`, `openai-plugin/plugin.json`, the Claude Code `plugin.json` and `marketplace.json`, both `SERVER_VERSION` constants, the `bridgistic.php` header and `BRIDGISTIC_VERSION`, and the `readme.txt` Stable tag. Also bump `wordpress-plugin/bridgistic/composer.json` and `tests/bootstrap.php`, which `validate` doesn't check.
2. Move the `## [Unreleased]` entries in `CHANGELOG.md` to `## [X.Y.Z] — YYYY-MM-DD`, and add a `= X.Y.Z =` entry to the `readme.txt` changelog.
3. Write `release/RELEASE_NOTES_vX.Y.Z.md`, `release/TEST_REPORT_vX.Y.Z.md` and `release/SECURITY_CHECKLIST_vX.Y.Z.md`.
4. Merge these changes to `main`.

## 2. Build and verify (from a clean checkout of `main`)

```bash
npm run bootstrap
npm run build:ci
npm run test:all && npm run lint:php && npm run validate
npm --prefix mcp-server run validate && npm --prefix cloud run typecheck
npm run release        # refuses a dirty tree; writes dist/release-vX.Y.Z/ with SHA256SUMS.txt
```

`npm run release` builds `bridgistic-wordpress-plugin.zip`, `bridgistic-claude-package.zip`, `bridgistic.mcpb`, `bridgistic-openai-plugin.zip` and `bridgistic-mcp-server-X.Y.Z.tgz`. It verifies each artifact, copies in the three release documents and writes the checksums. The zips and the tarball are byte-reproducible. The `.mcpb` isn't, because the mcpb CLI stamps the build time.

Recommended: install the built plugin zip with WP-CLI on the current WordPress release and the previous branch, then confirm that it activates, reports the new version, creates its tables and registers its REST routes. This mirrors the `wordpress-activation` job in `ci.yml`.

## 3. Tag and publish the GitHub release

```bash
git tag -a vX.Y.Z -m "Bridgistic vX.Y.Z" <main commit> && git push origin vX.Y.Z
cd dist/release-vX.Y.Z
gh release create vX.Y.Z --verify-tag --latest --title "Bridgistic vX.Y.Z" \
  --notes-file RELEASE_NOTES_vX.Y.Z.md ./*
```

Never move or re-create a tag that is already public.

## 4. Sync the marketplace listing repo

`Wordpressistic/bridgistic-claude-marketplace` is the official listing repo for Claude and other plugin marketplaces. After each release:

1. On a branch `sync/vX.Y.Z` there, copy these paths from the canonical tag: `.claude-plugin`, `.github/workflows` (the manual-only versions), `CHANGELOG.md`, `cloud`, `mcp-server`, `mcpb`, `openai-plugin`, `plugins`, `wordpress-plugin`, `package.json`, `server.json`, `release`, `scripts` (except `build-site.sh`) and the client docs. Leave its README, SECURITY.md and legacy site files alone.
2. Run `npm run validate` and `npm run test:all` there. Open a pull request, then merge it with a **merge commit** so the tagged commit stays in `main`'s history.
3. Tag the same version, and create a release with the **same files** from `dist/release-vX.Y.Z/`. `sha256sum -c SHA256SUMS.txt` must pass.

## 5. Update the website

The site's version pill and download links come from the marketplace listing release. Rebuild and deploy as described in [PAGES_RELEASES.md](PAGES_RELEASES.md).

## 6. Publishing beyond GitHub (only when explicitly decided)

Steps 1 to 5 don't touch these registries:

- **npm** `bridgistic-mcp-server`: publish `dist/release-vX.Y.Z/bridgistic-mcp-server-X.Y.Z.tgz`. The `release.yml` workflow does this with provenance when it is run manually with `dry_run` unticked. npm versions are immutable. See [NPM_TRUSTED_PUBLISHER.md](NPM_TRUSTED_PUBLISHER.md).
- **MCP Registry** `io.github.Wordpressistic/bridgistic`: run `mcp-publisher publish` with `server.json`, after the npm version exists.
- **Bridgistic Cloud** (`mcp.bridgistic.app`): run `wrangler deploy` from `cloud/` after a `wrangler dev` smoke test.
- **WordPress.org**: the plugin isn't listed there today.
