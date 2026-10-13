# Bridgistic v1.5.2 security checklist

- [x] Retry is limited to the known dynamic-client KV consistency error.
- [x] Invalid authorization requests are not redirected using an untrusted redirect URI.
- [x] OAuth client parameters are still validated by `@cloudflare/workers-oauth-provider`.
- [x] No OAuth secrets, client secrets, or product identifiers were added to source.
- [x] Existing PKCE, site URL SSRF protection, scoped keys, approvals, and tenant encryption remain unchanged.
- [ ] Complete a fresh authenticated marketplace connection on the dev WordPress site after deployment.
