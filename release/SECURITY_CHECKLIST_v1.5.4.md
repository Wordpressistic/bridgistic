# Bridgistic v1.5.4 security checklist

- [x] License activation is scoped to the site domain, environment and installation UUID.
- [x] Raw license keys are not stored; only a masked key, server token and verification material are retained in a non-autoloaded option.
- [x] License responses are rejected unless the HMAC signature verifies and the product is Bridgistic.
- [x] The three shipped gates open only for active, trialing or grace license status.
- [x] Skills marketplace, agency dashboard, team permissions and white-label remain closed with or without a license.
- [x] Audit export continues to require the `audit:read` scope and a live license.
- [x] No Cloud Agent credit entitlement is added to the plugin gate.
- [x] `npm audit` reports 0 vulnerabilities in `mcp-server` and `cloud`.
- [x] Secret scan (`npm run validate`) is clean.
- [ ] Manual: activate a real test license on a staging WordPress site, validate, exercise all three gates, then deactivate and confirm the free caps return.
- [ ] Manual: confirm the dashboard is protected by Cloudflare Access or offline before LTD traffic.
- [ ] Manual: run the WordPress activation matrix and a `wrangler dev` smoke test before provider deployments.
