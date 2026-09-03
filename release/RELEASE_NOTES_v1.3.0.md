# Bridgistic v1.3.0

Bridgistic v1.3.0 is the WordPressistic-org launch release: the plugin is now a
licensed product with free and paid tiers under the WPistic licensing system,
and the cloud connector moves to its own home at **mcp.bridgistic.app**.

**Upgrade is safe in place.** Keys, scopes, logs, snapshots, playbooks, and
schedules are preserved. Existing cloud-connector sessions on the legacy
`mcp.wpistic.cloud` endpoint keep working during migration.

---

## New

### WPistic licensing — free and paid tiers

- License activation from the plugin's own **License** tab (admin.php?page=bridgistic-license)
- Free tier works without a license key; Pro features gate on a valid license
- Update channel control (stable) via the licensing client
- Licenses are issued from the WordPressistic platform

### Cloud connector: mcp.bridgistic.app

- Dashboard default connector URL is now `https://mcp.bridgistic.app/mcp`
- OAuth redirect allowlist accepts both hosts during migration
- Cloud connector keys are labeled host-neutral ("Cloud connector")
- Worker route serves both mcp.bridgistic.app (primary) and mcp.wpistic.cloud (legacy)

### Simpler admin

- Duplicate "Settings → Bridgistic License" menu entry removed — the plugin's
  own License tab is the single activation surface (SDK filter
  `wpistic_sdk_show_settings_menu`; admin-post handlers keep working for
  bookmarked URLs)

---

## Fixed

- Cloud worker version constants synced to 1.3.0 (version-drift CI gate)
- All worker tests, docs, and READMEs updated to the new connector host

## Upgrade notes

- If you previously saved the legacy connector URL, reconnecting from the
  dashboard picks the new default automatically.
- After upgrading, deactivate/reactivate your license only if activation
  status looks stale — activation state carries over.

## SHA-256

See `SHA256SUMS.txt` in the release assets for per-artifact checksums.
