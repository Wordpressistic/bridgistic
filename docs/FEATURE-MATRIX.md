# Free vs Pro — feature matrix (from code, not marketing)

Every row below is grounded in the shipping plugin code
(`wordpress-plugin/bridgistic/`). Rows marked **verify** could not be pinned to a
specific code gate on 2026-10-13 and must not be promised in marketing until
verified.

## Code sources

- `includes/class-license.php` — `License::FREE_ONLY = true`; `PAID_FEATURES` =
  `scheduled_playbooks_unlimited, snapshots_advanced, audit_export, skills_marketplace,
  agency_dashboard, team_permissions, white_label`; `gate()` returns **true** for any
  feature NOT in `PAID_FEATURES` (security/bridge behavior is never paywalled).
- `includes/class-scheduler.php` — `Scheduler::FREE_MAX_ACTIVE = 3` enforced in
  `check_cap()` before create/enable of a schedule.
- `includes/class-audit-log.php` — `RETENTION_DAYS = 90`, prune on write.
- `includes/class-snapshot.php` — auto + manual snapshots, `TABLE_ROW_CAP = 50000`,
  `list_recent()` default limit 50.
- REST error strings in `rest/class-schedule-controller.php` state the cap message verbatim:
  "3 enabled schedules are included in the free plugin."

## The matrix

| Feature | Free plugin (public, GPL-2.0-or-later) | Pro ($12/mo · $99/yr via pay.wpistic.com) |
|---|---|---|
| Local MCP bridge (Claude Desktop / Claude Code / Codex / Gemini / generic MCP) | ✅ full — never gated (`gate()` = true by default) | ✅ same |
| HMAC-signed requests, scoped keys, approvals | ✅ full | ✅ same |
| WooCommerce structured tools | ✅ (scope-gated by key, not by plan) | ✅ same |
| Manual playbooks | ✅ | ✅ same |
| Scheduled playbooks | ✅ up to **3 active** (`FREE_MAX_ACTIVE = 3`, code-enforced) | ✅ **unlimited** (`scheduled_playbooks_unlimited`) |
| Snapshots | ✅ auto pre-destructive + manual, ~50 recent listed (`list_recent` default 50 — **verify** whether 50 is the hard store cap) | ✅ `snapshots_advanced`: full history, retention policies, rollback analytics |
| Audit logs | ✅ every request logged, **90-day retention** (`RETENTION_DAYS = 90`) | ✅ `audit_export`: long-term history + **export** |
| Skills marketplace / SEO-AIO-schema skills | ❌ (`skills_marketplace`) | ✅ |
| Agency multi-site dashboard | ❌ (`agency_dashboard`) | ✅ |
| Team permissions | ❌ (`team_permissions`) | ✅ |
| White-label | ❌ (`white_label`) | ✅ |
| License key activation in the free plugin | ❌ none — `FREE_ONLY = true`, `activate()` refuses keys by design | ✅ WPistic license issued at purchase; Pro features delivered by the licensed/SaaS layer, not by patching the free zip |
| Plugin updates | ✅ latest GitHub release zip (manual) | ✅ secure updates via WPistic license |
| Support | ✅ community / GitHub issues | ✅ priority product support path |
| Usage billing | ❌ | ✅ (SaaS layer) — **verify** (docs/FREE_VS_PAID.md lists it; no gate found in plugin code — lives in SaaS) |
| Remote MCP connector (Bridgistic Cloud, mcp.bridgistic.app) | ✅ **free public beta** — no independent security review yet (docs/CLOUD_CONNECTOR.md) | ✅ same connector + SaaS management on top |

## Honest notes

1. **The free plugin never unlocks.** There is no license activation path, billing code, or
   entitlement refresh in the public zip (class-license.php header states this explicitly).
   "Pro" is the WPistic-licensed/SaaS layer on top — which is why the free-vs-Pro boundary in
   the plugin code is expressed as display-only `PAID_FEATURES` markers plus the scheduler cap.
2. **The only hard numeric gate in plugin code today** is `FREE_MAX_ACTIVE = 3` scheduled
   playbooks. Audit retention (90d) and snapshot listing (50) are the free-shape defaults that
   Pro's advanced tier extends; treat their exact Pro-side numbers as **verify** until the SaaS
   layer defines them.
3. `docs/FREE_VS_PAID.md` is the narrative companion to this matrix. Where the two disagree,
   this file (built from code) wins.
