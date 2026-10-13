# Bridgistic v1.5.1 security checklist

## Corrective patch

- [x] npm package metadata uses the exact WordPressistic-owned MCP namespace.
- [x] The package is published from the organization marketplace repository through the configured release workflow.
- [x] The free-only premium lock from v1.5.0 remains in force; no paid activation path was added.
- [x] The release workflow retains checksum verification before publishing.
- [x] The release workflow retains npm and MCP Registry preflight checks to prevent duplicate immutable versions.

## External gates

- [ ] Final MCP Registry publication and public API verification are completed by the v1.5.1 release workflow.
- [ ] ChatGPT/OpenAI directory review remains subject to the official publisher submission process.
