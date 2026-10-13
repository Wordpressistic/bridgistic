# Bridgistic v1.5.1 test report

Date: 2026-10-05

## Automated gates

The release workflow reruns the complete preflight and build gates from the 1.5.0 free-only release, including MCP contract and integration tests, PHP lint and behavioural tests, cloud tests, bundled MCP smoke tests, package verification, version consistency, and secret scanning.

## Corrective scope

- All 14 version sources are aligned at `1.5.1`.
- `mcp-server/package.json` declares the exact MCP name `io.github.Wordpressistic/bridgistic`.
- `server.json` and the npm registry package entry use the same namespace and version.
- The free-only premium lock remains unchanged.
