# Bridgistic v1.3.0 Test Report

Every gate below ran in CI or locally at the recorded commit. NOT VERIFIED
means nobody has evidence — not "probably fine".

## Release commit

`4affd10` (branch `main`)

## Gates

| Gate | Result | Evidence |
|---|---|---|
| PHP lint (all plugin files) | PASS | `php -l` clean on changed files; CI job "PHP 8.0" and "PHP 8.3" success |
| Plugin behavioural tests | PASS | CI "PHP 8.3" job, WordPress matrix, suite-oauth updated to mcp.bridgistic.app host cases |
| Version consistency | PASS | `scripts/validate-marketplace.js` — "PASSED — 0 problems" locally and in CI on `4affd10` |
| Secret scan | PASS | validator "no secrets found in 215 scanned files" |
| Cloud worker unit tests | PASS | CI "MCP server, cloud, packaging" job green on `4affd10` (oauth-flow, wp-oauth-client suites on new host) |
| Cloud drift check | PASS | `npm run check:cloud-drift` in CI |
| OAuth allowlist behaviour | PASS | suite-oauth: both `mcp.bridgistic.app` and legacy `mcp.wpistic.cloud` accepted as redirect hosts; suffix/credential/port attacks rejected |
| Duplicate license menu removal | PASS | `php -l` + SDK filter logic reviewed; plugin License tab remains the single activation surface |
| Zip install + activate on WordPress | PASS | CI "Plugin ZIP installs and activates (WordPress 6.4–6.8 matrix)" success on `4affd10` |

## Manual checks on a live site

- Live connector endpoint `https://mcp.bridgistic.app/mcp` answers 401 without
  a session (expected pre-auth behaviour) — VERIFIED 2026-09-03.
- Brother Tours (brothertours.com) upgrade path verified post-release.

## Not verified in CI

- ChatGPT remote-MCP OAuth end-to-end against the production worker (requires
  a live OAuth client session) — manual QA after deploy.
