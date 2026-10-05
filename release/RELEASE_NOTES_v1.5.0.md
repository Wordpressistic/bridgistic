# Bridgistic v1.5.0

## Free-only release

Bridgistic v1.5.0 is the final free plugin/MCP distribution. The shipped WordPress plugin and local MCP packages no longer contain a licensing SDK, remote activation flow, billing/account integration, or runtime premium unlock path.

The following paid/SaaS features remain documented as display-only and are locked in the free distribution:

- Unlimited scheduled playbooks
- Advanced snapshots
- Audit export
- Skills marketplace
- Agency dashboard
- Team permissions
- White-label controls

The local bridge, scoped tools, HMAC signing, approvals, audit logging, manual playbooks, snapshots within the free limit, and site-read workflows remain available.

## Distribution and deployment

- All version-bearing manifests and package metadata are aligned at `1.5.0`.
- The WordPress release archive is `bridgistic-wordpress-plugin.zip`.
- The portable OpenAI Agent Plugin package is `bridgistic-openai-plugin.zip` and points to `https://mcp.bridgistic.app/mcp`.
- The Claude package and desktop `.mcpb` are rebuilt from the same source version.
- GitHub Pages deployment is tag-driven through `.github/workflows/pages-release.yml`; the one-time Pages source must be set to GitHub Actions by a repository administrator.

## Compatibility

- WordPress 6.4+
- PHP 8.0+
- Claude Desktop, Claude Code, Codex CLI, Gemini CLI, and remote MCP clients that support Streamable HTTP, subject to each client’s own requirements.

