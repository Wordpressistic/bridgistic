# Bridgistic v1.3.1 Security Checklist

Verified locally on 2026-09-04. PASS means the stated check ran successfully.

## Authentication and authorization

- HMAC request contract remains unchanged — PASS (430 PHP checks and 74 MCP
  integration assertions).
- OAuth and tenant-isolation behavior remains green — PASS (194 cloud tests).
- Scope enforcement and approval paths remain covered — PASS (PHP and MCP
  contract suites).

## Plugin interoperability

- Shared WPistic SDK loading is guarded before including shared classes — PASS.
- License keys remain restricted to the existing administrator capability and
  encrypted activation storage — PASS (code-path review and licensing tests in
  the central WPistic staging lifecycle).

## Dependencies and supply chain

- MCP dependency audit — PASS, 0 vulnerabilities.
- Cloud Worker dependency audit — PASS, 0 vulnerabilities.
- Repository secret scan — PASS, 216 files and no secrets found.
- Release ZIPs are reproducibly built by the repository packaging scripts — PASS.

## Production boundary

- Public 1.3.1 release, hosted CI, and post-deploy OAuth flow — NOT YET VERIFIED.
  These require the release-candidate branch to be pushed and approved.
