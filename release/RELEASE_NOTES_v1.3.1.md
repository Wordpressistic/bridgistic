# Bridgistic v1.3.1

Bridgistic v1.3.1 is a compatibility and security maintenance release for the
WPistic licensing rollout.

## Fixed

- Fixed snapshot and restore path containment for new files and sibling folders;
  traversal now fails closed and has dedicated regression checks.
- Guarded the shared WPistic SDK loader so Bridgistic can run beside other
  WPistic-licensed plugins without redeclaring SDK classes.
- Made the shipped-bundle and release pipelines invoke npm, MCPB packaging,
  archive extraction, and tarball verification portably on Windows and Unix.
- Updated the local MCP server and Cloudflare Worker dependency trees to clear
  current package-security advisories.
- Kept every marketplace, MCP, cloud, package, and WordPress version source in
  sync at 1.3.1.

## Compatibility

- No database migration is required.
- Existing Bridgistic keys, scopes, OAuth sessions, snapshots, schedules, and
  WPistic license activation state are preserved.
- The public connector remains `https://mcp.bridgistic.app/mcp`.

## Verification

See `TEST_REPORT_v1.3.1.md` and `SECURITY_CHECKLIST_v1.3.1.md` in the release
assets. Checksums are published in `SHA256SUMS.txt`.
