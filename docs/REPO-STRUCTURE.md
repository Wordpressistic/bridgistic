# Bridgistic Repository Structure — which repo is the real one?

**Decided 2026-10-13.** Bridgistic previously lived in two public GitHub repositories at the
same time. That confused visitors and it confused us. This document is the single source of
truth for which repo does what, and where every kind of update goes.

## The answer, in one line

> **All Bridgistic development — free AND paid — happens in
> [Wordpressistic/bridgistic](https://github.com/Wordpressistic/bridgistic).**
> `Wordpressistic/bridgistic-claude-marketplace` is a **frozen release mirror**: historical
> releases (v1.5.2 and older), the npm/MCP-registry provenance record, and nothing new.

## The two repos and their roles

| | [Wordpressistic/bridgistic](https://github.com/Wordpressistic/bridgistic) | [Wordpressistic/bridgistic-claude-marketplace](https://github.com/Wordpressistic/bridgistic-claude-marketplace) |
|---|---|---|
| Role | **Canonical.** All development, all future updates, free and paid | Frozen mirror. No new development |
| Landing page | **Yes — serves https://bridgistic.app** via GitHub Pages | No (has a legacy page, not deployed) |
| Product code | **Yes — merged v1.5.2 product work landed here** (see history below) | Yes, as of v1.5.2 — now frozen |
| Releases / tags | **New releases are cut here** (tag `vX.Y.Z`, Actions → Release) | Historical v1.5.2 and older remain, read-only |
| npm package `bridgistic-mcp-server` | Publishes **from here** going forward | Published 1.5.1 / 1.5.2 from here (history) |
| MCP Registry entry | Points here after the next release (see note below) | Current registry `repository.url` (transitional) |

## How the unification happened (2026-10-13)

Neither repo was a superset of the other, so the fix was a real merge, not a pointer swap:

- **canonical (`bridgistic`)** had the live landing page, the `pages-release.yml` site deploy
  workflow, and the 2026-10-13 pricing UI fix.
- **mirror (`bridgistic-claude-marketplace`)** had the actual v1.5.1 + v1.5.2 release train:
  npm OIDC/trusted-publishing fixes, MCP Registry publication under the
  `io.github.Wordpressistic` namespace, the cloud OAuth client-lookup race fix, and version
  bumps across every manifest.

The merge commit on canonical `main` (f683219) took all of the marketplace repo's product
work and kept the canonical repo's landing page + Pages workflow. Nothing was lost; nothing
was force-pushed anywhere. The marketplace repo gets a README banner and frozen description.

## Where each kind of update goes

| You are updating… | Repo | How |
|---|---|---|
| Free plugin / MCP server / cloud / docs / landing page | `Wordpressistic/bridgistic` | PR or direct commit to `main` |
| A new product release (all artifacts: zips, .mcpb, npm, registry) | `Wordpressistic/bridgistic` | Tag `vX.Y.Z` on `main` → Release workflow builds and publishes everything |
| Pro / paid features | `Wordpressistic/bridgistic` | Pro ships as the WPistic license layer on top of the same codebase (see [FEATURE-MATRIX.md](FEATURE-MATRIX.md)) — no separate repo |
| The old marketplace repo | Nobody. It is frozen | Do not commit there; it exists for historical release provenance only |

## One open item (needs a release to complete)

> 1.5.3 (PR `fix/audit-2026-10`) re-points `repository.url` in `package.json`,
> `mcp-server/package.json`, `server.json`, `mcpb/manifest.json` and
> `openai-plugin/plugin.json` to this repo. It completes when 1.5.3 is released from here
> (after npm trusted publishing is moved — see [NPM_TRUSTED_PUBLISHER.md](NPM_TRUSTED_PUBLISHER.md)).

The npm package metadata and the MCP Registry entry still point their `repository.url` at the
mirror repo, because those registries are immutable per version — they can only be re-pointed
by publishing a **new version**. The first release cut from canonical (v1.5.3 or v1.6.0)
will:

- publish `bridgistic-mcp-server` with `repository.url` = canonical repo,
- update the MCP Registry `io.github.Wordpressistic/bridgistic` entry likewise,
- and make the canonical repo the provable source for every artifact.

Until then, npm 1.5.2 and the registry entry legitimately reference the mirror — that is
expected and documented here.

## For visitors

The landing page at [bridgistic.app](https://bridgistic.app) is generated from the canonical
repo. Every "GitHub" link on the site points at
[Wordpressistic/bridgistic](https://github.com/Wordpressistic/bridgistic). If you arrived at
the marketplace repo from an old npm or registry link: everything there is preserved, and all
new work — including the free plugin you can download today — lives in the canonical repo.
