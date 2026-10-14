# Bridgistic v1.5.3 security checklist

- [x] S1 write-SQL dry run never executes (regression: `suite-security-regressions`).
- [x] S2 `/*! */`, `/*!NNNNN */`, `/*M! */` forbidden; plain comments and literals unaffected.
- [x] S3 exec-control and sandbox guard files unwritable/undeletable; guard re-asserted; sandbox PHP prefixed with ABSPATH guard (verified to exit when run directly).
- [x] S4 `php:execute` routed through Guard; approval bound to code hash; dry run never evaluates.
- [x] S5 control params rejected from query string; mutating requests reject query params.
- [x] `npm audit` 0 vulnerabilities (mcp-server, cloud).
- [x] gitleaks-style secret scan (`npm run validate`) clean.
- [ ] Manual: on a staging WP site (Apache and nginx), confirm a sandbox `.php` URL returns nothing and `.htaccess` writes return 403.
- [ ] Manual: `wrangler dev` smoke test of the cloud worker on agents 0.28.1 before any deploy.
- [ ] Move npm trusted publishing to `Wordpressistic/bridgistic` before tagging.
