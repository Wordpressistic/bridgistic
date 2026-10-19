# Bridgistic v1.5.3 test report

Re-run locally on 2026-10-19 (Asia/Dhaka) on `main` after PR #10 (approvals/status + dashboard CORS) — Node 20.19, npm 11.21, PHP 8.4 CLI. First run on 2026-10-14 on the `fix/audit-2026-10` branch.

## Automated checks (all passed)

- MCP server contract tests: 54 tools, 567 assertions.
- MCP server integration tests: 18 signed calls, 74 assertions.
- Shipped-bundle smoke test: 54 tools over a real stdio handshake, 122 assertions.
- Cloud Worker typecheck: passed. Cloud test suite: 196 tests, 43 suites, 0 failures (on agents 0.28.1 / MCP SDK 1.32.1).
- Cloud/MCP tool drift check: in sync.
- `npm run validate` (manifests, versions, secret scan): 0 problems.
- PHP lint: 88 files clean. PHP behavioural tests: 547 checks across 10 suites, including `suite-security-regressions` (S1–S5 and the approvals/status query exception) and `suite-license-link`.
- Site tests (`tests/site/*.test.mjs`): 20 tests, 0 failures.
- `tsc --noEmit` for `mcp-server` and `cloud`: clean.
- Plugin ZIP, desktop package and `verify-packages.js`: every artifact unpacks to the expected structure.
- `npm audit`: 0 vulnerabilities in `mcp-server` and `cloud`.

## Not covered here (must run in CI / staging)

- WordPress install/activate matrix (`latest` = 7.1.3, `6.7`) and MariaDB-backed behaviour (the CI `wordpress-activation` job; GitHub Actions is currently manual-only, so run it by hand or on staging).
- Live Apache/nginx check that sandbox PHP is not web-executable.
- Cloud Worker deploy and an authenticated marketplace OAuth connection.
