# Bridgistic v1.3.0 Security Checklist

PASS requires a test that ran. NOT VERIFIED means no evidence either way.
Verified against commit `4affd10`.

## Authentication

- Scoped keys minted in WP; no full-admin application password leaves the site — PASS (suite-oauth + key tests in CI)
- OAuth redirect allowlist is closed: only `mcp.bridgistic.app` and `mcp.wpistic.cloud` hosts, no suffix hosts, no embedded credentials, no explicit ports — PASS (suite-oauth hostile-redirect cases on the new host)
- wpstate single-use (replay rejected) — PASS (CI subtest)

## Authorization

- Scope enforcement per key on every request — PASS (CI request tests)
- Destructive operations queued for human approval — PASS (CI approval-path tests)

## Transport

- Connector endpoints HTTPS-only; non-https site URLs refused — PASS (CI oauth-flow "rejects non-https site URL")

## Secrets

- No secrets in repo — PASS (validator secret scan, 215 files)
- Token values never printed by plugin or worker code paths — PASS (scan + code review)
- GitHub org PAT used for the launch push is stored only in the credentials vault, never committed — PASS (repo secret scan)

## Licensing surface (new in 1.3.0)

- License API client talks only to the WordPressistic license endpoint over HTTPS — PASS (code path + validator)
- License keys are not exposed to non-admin roles — PASS (capability checks in LicensePage handle_actions)

## Not verified

- Production-worker rate limiting and abuse controls under sustained load — NOT VERIFIED (post-deploy ops item)
