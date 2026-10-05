# Bridgistic v1.5.0 security checklist

## Free distribution

- [x] Premium feature list is centrally locked by a deterministic free-only policy.
- [x] Remote license activation, entitlement lookup, billing, and account code are removed from the shipped WordPress plugin.
- [x] Licensing SDK source and the license admin page are excluded from release archives.
- [x] Capability checks, nonces, sanitization, escaping, prepared SQL, HMAC verification, scoped permissions, approvals, and audit logging remain covered by the existing test suites.
- [x] Package verifier rejects SDK/license UI files and rejects a non-free-only license policy.
- [x] Repository secret scan passed.

## Hosted services and marketplace

- [x] The OpenAI package contains only the portable manifest, MCP configuration, icon, and read-only onboarding skill.
- [x] The package points to the canonical hosted endpoint `https://mcp.bridgistic.app/mcp`.
- [ ] OpenAI/ChatGPT directory review and publication are not complete; publisher identity, domain challenge, reviewer credentials, positive/negative cases, and demo video must be supplied through the official submission flow.
- [ ] The hosted MCP endpoint has not received an independent third-party security review.

## Environment gates

- [ ] Install and activate the 1.5.0 ZIP on `dev-test.wordpressistic.com` through an explicitly mapped staging connector or approved test credentials.
- [ ] Verify authenticated REST calls, HMAC/key handling, premium-lock behaviour, scheduler/snapshot limits, and browser admin workflows on staging.
- [x] GitHub Pages source is now set to the GitHub Actions workflow; the repository API reports `build_type: workflow`.
- [ ] Confirm the release URL and artifact checksums after publishing the GitHub release.

