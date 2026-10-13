# npm publishing and repository ownership

`bridgistic-mcp-server` is published from the WordPressistic organization repository:

- Repository: <https://github.com/Wordpressistic/bridgistic-claude-marketplace>
- Workflow: `.github/workflows/release.yml`
- npm package: <https://www.npmjs.com/package/bridgistic-mcp-server>
- MCP Registry name: `io.github.Wordpressistic/bridgistic`

## Trusted publishing

The workflow prefers GitHub Actions OIDC trusted publishing. However, GitHub repositories
created after July 15, 2026 receive immutable OIDC subject claims, and npm's registry
currently rejects those claims during token exchange. The workflow therefore includes a
repository-scoped `NPM_TOKEN` fallback until npm supports the immutable subject format.

The npm package trusted-publisher connection must match these values exactly:

| npm field | Value |
| --- | --- |
| Provider | GitHub Actions |
| Organization or user | `Wordpressistic` |
| Repository | `bridgistic-claude-marketplace` |
| Workflow filename | `release.yml` |
| Environment name | Leave blank |
| Allow npm publish | Enabled |

The workflow grants `id-token: write`, uses Node.js 24 (with an npm version that supports
trusted publishing), builds the package once, and publishes the verified tarball. The package
metadata, MCP manifest, OpenAI plugin manifest, and Claude Desktop manifest all point to the
organization repository.

For the current token fallback, add `NPM_TOKEN` to the **marketplace repository** (not only
`Wordpressistic/bridgistic`) and use an npm token permitted by the package's publishing-access
policy. Do not put the token in source control, documentation, or chat. When npm fixes support
for immutable OIDC subjects, remove the fallback and return the package to the most restrictive
2FA setting.

## Release procedure

1. Update the version-bearing manifests and release documents together.
2. Run `npm run validate` and the local test suite.
3. Merge the change into `main`.
4. Run the **Release** workflow with the exact semver version, or push a matching `v*` tag.
5. Confirm the GitHub release, npm version, and MCP Registry entry before announcing the release.

The former personal repository is not a release source for this package.
