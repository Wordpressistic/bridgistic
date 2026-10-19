# Contributing to Bridgistic

Thanks for helping improve Bridgistic. This repository holds the free WordPress plugin, the MCP server, the Claude integrations, the Bridgistic Cloud relay and the bridgistic.app website.

## Before you start

- **Security issues:** don't open an issue or pull request. Follow [SECURITY.md](SECURITY.md).
- **Bugs and feature ideas:** open an [issue](https://github.com/Wordpressistic/bridgistic/issues/new/choose) using one of the templates. For larger changes, please agree on the approach in an issue before you write code.
- **Questions about a purchase or license:** email support@wpistic.com. GitHub issues aren't the right place for billing questions.

## Development setup

Requirements: Node.js 20+ (wrangler for the `cloud/` worker needs Node.js 22+), PHP 8.0+ CLI, and git.

```bash
git clone https://github.com/Wordpressistic/bridgistic.git
cd bridgistic
npm run bootstrap   # mcp-server + cloud dependencies
npm run build       # compile the MCP server and refresh plugins/bridgistic/server/index.js
```

## Checks to run before a pull request

```bash
npm run test:all    # MCP contract and integration tests, PHP suites, cloud tests, bundle smoke test
npm run lint:php    # php -l on every plugin file
npm run validate    # manifests, version consistency and secret scan
npm --prefix mcp-server run validate && npm --prefix cloud run typecheck
for f in tests/site/*.test.mjs; do node --test "$f"; done   # website tests
```

GitHub Actions workflows in this repository run manually (`workflow_dispatch`) only, so run these checks locally and paste a summary into your pull request.

## Guidelines

- **Keep pull requests small and focused.** Each one should be a single fix or feature.
- **Security behaviour must not regress.** Changes to signing, scopes, the Guard, the SQL classifier, the filesystem sandbox or approvals need a test in `wordpress-plugin/bridgistic/tests/` (see `suite-security-regressions.php`).
- **MCP tools stay in sync.** If you add or change a tool in `mcp-server/src/tools/`, update the matching cloud tool and run `npm run check:cloud-drift`.
- **Use the version sources.** Don't bump version numbers by hand in one file only. `npm run validate` checks all 14 version sources and the changelog.
- **Add a changelog entry.** User-visible changes go under `## [Unreleased]` in [CHANGELOG.md](CHANGELOG.md).
- **WordPress coding standards apply** to PHP (see `wordpress-plugin/bridgistic/composer.json`). TypeScript must pass `tsc --noEmit`.
- **Never commit secrets** such as real key IDs, secrets, tokens or `connections.json` files.

## License

By contributing, you agree that your contributions are licensed under the [GPL-2.0-or-later](LICENSE) license that covers the project.

Please follow our [Code of Conduct](CODE_OF_CONDUCT.md) in all project spaces.
