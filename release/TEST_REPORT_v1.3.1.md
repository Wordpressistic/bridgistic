# Bridgistic v1.3.1 Test Report

Verified locally on 2026-09-04; re-audited on 2026-09-07. The candidate is now
published in PR #1. Hosted CI and public release remain separate gates.

September 7 follow-up: fixed snapshot/restore path traversal and sibling-prefix
containment, and corrected the filesystem test fixture's Windows realpath
normalization. PHP now passes 438 checks across seven suites. Production
dependency audits for both cloud and MCP remain clean.

## Gates

| Gate | Result | Evidence |
|---|---|---|
| Version and manifest validation | PASS | All 13 version sources at 1.3.1; 0 problems and 0 warnings |
| Secret scan | PASS | No secrets found in 216 scanned files |
| PHP lint | PASS | 97 files |
| PHP behavioural suites | PASS | 430 checks across 6 suites on PHP 8.2 |
| MCP contract suite | PASS | 54 tools and 567 assertions |
| MCP integration suite | PASS | 18 signed calls and 74 assertions |
| Cloud Worker suites | PASS | 194 tests |
| Shipped-bundle smoke | PASS | 54 tools through a real stdio handshake and 122 assertions |
| Cloud/local tool drift | PASS | 8 shared tool files match |
| Full release dry run | PASS | 8 artifacts built, unpacked, structurally verified, and checksummed |
| Dependency audit | PASS | 0 vulnerabilities in both MCP and cloud dependency trees |

## Live boundary

The existing production MCP endpoint responds with the expected unauthenticated
401 at `/mcp`. This report does not claim that the 1.3.1 code has been deployed;
deployment and post-deploy OAuth validation are separate release gates.
