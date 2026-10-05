# OpenAI plugin directory submission

Bridgistic now includes an official Agent Plugins package at
dist/bridgistic-openai-plugin.zip. It contains the listing manifest, the
remote MCP configuration for https://mcp.bridgistic.app/mcp, the onboarding
skill, and the square product icon. See [BRANDING.md](BRANDING.md) for the
asset source and why the OpenAI package uses the icon rather than the full
rectangular lockup.

This package is designed for the official ChatGPT/Codex Plugins Directory.
GitHub releases, the Claude marketplace, and the MCP Registry do not create
an OpenAI directory listing automatically.

## One-time publisher flow

1. Confirm the WordPressistic organization and developer identity in the
   OpenAI developer dashboard.
2. Upload bridgistic-openai-plugin.zip as a new plugin with MCP.
3. Complete the domain-verification challenge at the exact
   /.well-known/openai-apps-challenge URL shown by the dashboard.
4. Connect and scan the MCP server, then resolve all required findings.
5. Add five positive and three negative review cases, reviewer test access,
   and an accessible walkthrough video in the dashboard.
6. Submit the draft for review and publish it after approval.

The package intentionally does not contain credentials or a fabricated demo
URL. Use a dedicated staging WordPress account and sample data for review.
After initial publication, eligible hosted MCP changes can be picked up by
OpenAI's scheduled scans; listing metadata or bundled skill changes require a
new package upload.

See the official OpenAI submission guide at
https://developers.openai.com/plugins/deploy/submission for current
requirements.
