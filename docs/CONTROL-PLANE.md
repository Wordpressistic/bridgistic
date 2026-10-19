# Bridgistic control plane

The free plugin is the bridge on a WordPress site. The control plane is the
product around it: an account dashboard for one workspace, and a platform
admin for the whole system. They are separate screens, the same way a
customer dashboard and an owner console should be.

This file describes the control plane as built. It is not a claim that
`dashboard.bridgistic.app` or `admin.bridgistic.app` already serve this
build. Deploy that yourself. Do not turn GitHub Actions back on to do it.

## Two surfaces

| Surface | Who it is for | What it holds |
|---|---|---|
| Account dashboard | A customer workspace | Sites, keys, approvals, content, agents, marketplace, connectors |
| Platform admin | The Bridgistic owner | Organizations, users, licenses, plans, billing notes, team, AI routing |

Switch between them with **Platform admin** and **Open dashboard**. A viewer,
operator, or approver does not see the admin portal.

## Account dashboard

- Workspace: overview, sites, keys and scopes, approvals, audit, snapshots, usage
- Content and site: posts, media, users, plugins, options, files, database
- Automation: playbooks, schedules, memory, WooCommerce
- Agents: hub, SEO, management, updates, audit, content writer, social, operations
- Marketplace: public directory, member Skills Hub, plugins, publishers, your listings
- Connectors: MCP servers, social drafts, WordPress bridge
- Agency: team, bulk, license, roadmap
- Settings, security, billing, profile, installed skills

Social does not publish. Skills are instruction text, not executed code.
WordPress writes still pass Plugin Guard and the approval queue.

## Platform admin

- Overview across every organization
- Organizations: add, edit name, email, plan and status, open that dashboard, suspend
- Users: add, edit, impersonate, promote, disable
- Sites: add, edit domain, organization, status and WordPress version, remove
- Plans: edit price, site cap, seats and billing. This does not charge a card
- Billing ops and subscriptions on the sample ledger
- Licenses: issue, edit the site limit, regenerate the suffix, suspend, disconnect, revoke. The full key is never shown
- Sync logs, reports, usage, system health, observability
- Settings: support contact, timezone, 2FA flag, session length, billing and sync mail, maintenance mode, default site limit, platform team
- AI configuration: fixed provider order, OpenAI-compatible base URL and model, a custom model, OpenRouter, local RAGBrain, website agents, shared skills metadata. API keys are not stored in the browser

## Website agents

The agents that work on a connected site use the routing saved in AI
configuration:

1. OpenAI-compatible endpoint
2. OpenRouter
3. Local RAGBrain
4. A fixed safety rule if the others are empty

SEO, content, updates, audit and operations can propose a change. Social
stays a draft unless that switch is turned off. Approval stays on unless
you turn it off. Plugin Guard still blocks executable SQL comments,
unsigned parameters and sandbox lock-drops.

## Skills

- Public directory: free skills under `@wordpressistic`
- Install without an account: `bridgistic skills install @wordpressistic/{slug}` or `npx skills add https://marketplace.bridgistic.app/{handle}/{slug}`
- A prompt tab writes the skill into the assistant without an account
- Downloading the zip asks for a free directory account
- The member pack stays in Skills Hub and is not listed on the public directory

## Documents in this repository

| File | What it covers |
|---|---|
| [README.md](../README.md) | Product entry and install |
| [FEATURE-MATRIX.md](FEATURE-MATRIX.md) | Free plugin vs Pro, from code |
| [FREE_VS_PAID.md](FREE_VS_PAID.md) | Narrative companion to the matrix |
| [CONTROL-PLANE.md](CONTROL-PLANE.md) | This file |
| [INSTALL.md](INSTALL.md) | All components |
| [WORDPRESS_SETUP.md](WORDPRESS_SETUP.md) | Plugin on a site |
| [CONNECT_BRIDGISTIC.md](CONNECT_BRIDGISTIC.md) | Full connection walkthrough |
| [CLAUDE_DESKTOP.md](CLAUDE_DESKTOP.md) | Claude Desktop |
| [CLAUDE_CODE.md](CLAUDE_CODE.md) | Claude Code |
| [CODEX_SETUP.md](CODEX_SETUP.md) | Codex CLI |
| [GEMINI_SETUP.md](GEMINI_SETUP.md) | Gemini CLI |
| [CHATGPT_SETUP.md](CHATGPT_SETUP.md) | ChatGPT |
| [CONNECT_OTHER_AI.md](CONNECT_OTHER_AI.md) | Other MCP clients |
| [CLOUD_CONNECTOR.md](CLOUD_CONNECTOR.md) | Bridgistic Cloud beta |
| [SECURITY.md](SECURITY.md) | Security model |
| [PRIVACY.md](PRIVACY.md) | Privacy |
| [TESTING.md](TESTING.md) | How the suite runs |
| [RELEASING.md](RELEASING.md) | How a release is cut |
| [PAGES_RELEASES.md](PAGES_RELEASES.md) | How the site is published |
| [ROADMAP.md](ROADMAP.md) | What is still ahead |
| [REPO-STRUCTURE.md](REPO-STRUCTURE.md) | Where each folder lives |
| [BRANDING.md](BRANDING.md) | Name and marks |
| [CHANGELOG.md](../CHANGELOG.md) | Plugin history |

Site copy for the feature grid is in `index.html`. It goes live only when
you run the site deploy yourself.
