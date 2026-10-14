# Security policy

Bridgistic lets AI clients operate WordPress sites, so we treat security
reports as the highest-priority work we have.

## Supported versions

| Component | Supported |
|---|---|
| WordPress plugin `bridgistic` | latest release (1.5.x) |
| npm `bridgistic-mcp-server` | latest release (1.5.x) |
| Hosted relay `mcp.bridgistic.app` | public beta, best effort |

Older versions do not receive fixes; please upgrade first and re-test.

## Reporting a vulnerability

**Please do not open a public issue, pull request or discussion for a
security problem.**

- Email **support@wordpressistic.com** with the subject `SECURITY: <short summary>`.
- Or, where enabled, use GitHub's **Report a vulnerability** button on the
  repository's *Security* tab (private vulnerability reporting).

Include the affected component and version, reproduction steps or a proof of
concept, the impact you believe it has, and whether you want credit.

We aim to acknowledge reports within **3 business days**, give an initial
assessment within **10 business days**, and ship a fix or mitigation for
confirmed high-severity issues as quickly as we can. We will keep you updated
and agree a disclosure date with you; please give us a reasonable window
(normally up to 90 days) before public disclosure.

## Scope

In scope: the WordPress plugin, the MCP server, the Claude Desktop extension,
the Claude Code plugin, the OpenAI plugin manifest, and the hosted relay.
Out of scope: social engineering, volumetric DoS, findings that need a
compromised WordPress administrator account or a leaked key secret, and
issues in third-party dependencies that are already publicly tracked
(report those upstream; tell us if Bridgistic is exploitable through them).

Please test only against sites you own. Never access data that is not yours.

## How Bridgistic is designed

See [docs/SECURITY.md](docs/SECURITY.md) for the security model (HMAC request
signing, scopes, approvals, snapshots, SQL classification, filesystem sandbox).
