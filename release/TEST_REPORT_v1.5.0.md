# Bridgistic v1.5.0 test report

Date: 2026-10-05

## Automated gates

All local gates passed on the `codex/v1.5.0-free-only` branch:

- MCP contract tests: 54 tools, 567 assertions
- MCP signed integration tests: 18 calls, 74 assertions
- PHP behavioural tests: 456 checks across 8 suites
- PHP lint: 83 files clean
- Cloud tests: 194 tests across 43 suites
- Bundled MCP smoke test: 54 tools, 122 assertions
- Marketplace/version validation: 14 version sources aligned at `1.5.0`; secret scan clean across 215 files
- Package verification: WordPress ZIP 79 files; OpenAI package 4 files; Claude package 29 files; desktop bundle manifest `1.5.0`
- Release package and desktop package builds completed successfully

The package verifier also confirmed that the WordPress ZIP contains no tests, licensing SDK, license UI, or remote activation surface, and that all seven premium feature identifiers remain locked by the free-only policy.

## Staging and live checks

- `https://dev-test.wordpressistic.com/` responded with HTTP 200.
- Its WordPress REST index responded with HTTP 200.
- `/wp-json/bridgistic/v1/` responded with HTTP 200 and the REST index exposed the Bridgistic route namespace, confirming that the plugin is active.
- Protected probes including `/wp-json/bridgistic/v1/site-info`, `/plugins`, `/options`, `/usage`, `/posts`, `/playbooks`, `/schedules`, and `/snapshot` returned HTTP 401 without a key, confirming the unauthenticated boundary.
- No staging connector alias or test credentials were available, so authenticated REST calls, HMAC/key handling, write workflows, and browser admin workflows are not certified by this report.
- `https://mcp.bridgistic.app/mcp` returned an authentication challenge as expected for the protected MCP endpoint. This is not proof of a complete OAuth or WordPress consent flow.

## Release status

The source and artifacts are locally release-ready. GitHub Pages is configured for
the tag-driven workflow. GitHub tag/release publication and authenticated staging
installation remain separate live-environment gates.

