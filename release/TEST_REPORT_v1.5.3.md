# Bridgistic v1.5.3 test report

Run locally on 2026-10-14 (Asia/Dhaka) on the `fix/audit-2026-10` branch — Node 20+, PHP 8.4 CLI (pdo_sqlite, mbstring).

## Automated checks (all passed)

- MCP server contract tests: 54 tools, 567 assertions.
- MCP server integration tests: 18 signed calls, 74 assertions.
- Shipped-bundle smoke test: 54 tools over a real stdio handshake, 122 assertions.
- Cloud Worker typecheck: passed. Cloud test suite: 196 tests, 43 suites, 0 failures (on agents 0.28.1 / MCP SDK 1.32.1).
- Cloud/MCP tool drift check: in sync.
- `npm run validate` (manifests, versions, secret scan): 0 problems.
- PHP lint: 85 files clean. PHP behavioural tests: 517 checks across 9 suites, including the new `suite-security-regressions` (61 checks covering S1–S5).
- Plugin ZIP, desktop package and `verify-packages.js`: every artifact unpacks to the expected structure.
- `npm audit`: 0 vulnerabilities in `mcp-server` and `cloud`.

## Not covered here (must run in CI / staging)

- WordPress install/activate matrix (`latest` = 7.1.3, `6.7`) and MariaDB-backed behaviour run in CI.
- Live Apache/nginx check that sandbox PHP is not web-executable.
- Cloud Worker deploy and an authenticated marketplace OAuth connection.
