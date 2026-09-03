#!/usr/bin/env bash
#
# Bridgistic — Claude Desktop setup helper (macOS / Linux)
#
# Merges a Bridgistic MCP server entry into your Claude Desktop config.
# Safe by design: backs up the existing config first, never touches
# anything outside the "bridgistic" server entry, and never phones home.
#
# Usage:
#   ./install-macos-linux.sh /absolute/path/to/mcp-server/dist/index.js
#
set -euo pipefail

SERVER_JS="${1:-}"

if [ -z "$SERVER_JS" ]; then
  echo "Usage: $0 /absolute/path/to/mcp-server/dist/index.js"
  echo "Tip: from the repo root that path is \$(pwd)/mcp-server/dist/index.js"
  exit 1
fi

if ! command -v node >/dev/null 2>&1; then
  echo "ERROR: Node.js is not installed (need Node 20+). Get it from https://nodejs.org/"
  exit 1
fi

NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]')"
if [ "$NODE_MAJOR" -lt 20 ]; then
  echo "ERROR: Node.js 20+ required, found $(node --version)."
  exit 1
fi

if [ ! -f "$SERVER_JS" ]; then
  echo "ERROR: MCP server not found at: $SERVER_JS"
  echo "Build it first: from the repo root run 'npm run build'."
  exit 1
fi

case "$(uname -s)" in
  Darwin) CONFIG_DIR="$HOME/Library/Application Support/Claude" ;;
  *)      CONFIG_DIR="${XDG_CONFIG_HOME:-$HOME/.config}/Claude" ;;
esac
CONFIG_FILE="$CONFIG_DIR/claude_desktop_config.json"

mkdir -p "$CONFIG_DIR"

if [ -f "$CONFIG_FILE" ]; then
  cp "$CONFIG_FILE" "$CONFIG_FILE.backup.$(date +%Y%m%d%H%M%S)"
  echo "Backed up existing config."
else
  echo '{}' > "$CONFIG_FILE"
fi

# Merge the bridgistic entry with Node so we do not clobber other servers.
SERVER_JS="$SERVER_JS" CONFIG_FILE="$CONFIG_FILE" node <<'EOF'
const fs = require("fs");
const file = process.env.CONFIG_FILE;
const serverJs = process.env.SERVER_JS;
let config = {};
try { config = JSON.parse(fs.readFileSync(file, "utf8")); } catch (e) {
  console.error("ERROR: existing config is not valid JSON: " + file);
  process.exit(1);
}
config.mcpServers = config.mcpServers || {};
config.mcpServers.bridgistic = {
  command: "node",
  args: [serverJs],
  env: {
    BRIDGISTIC_SITE_URL: config.mcpServers.bridgistic?.env?.BRIDGISTIC_SITE_URL || "https://example.com",
    BRIDGISTIC_KEY_ID: config.mcpServers.bridgistic?.env?.BRIDGISTIC_KEY_ID || "your_key_id",
    BRIDGISTIC_KEY_SECRET: config.mcpServers.bridgistic?.env?.BRIDGISTIC_KEY_SECRET || "your_key_secret",
  },
};
fs.writeFileSync(file, JSON.stringify(config, null, 2) + "\n");
console.log("Wrote " + file);
EOF

echo ""
echo "Done. Next steps:"
echo "  1. Open the config and fill in your real site URL, key ID and secret"
echo "     (generate them in WP Admin -> Bridgistic -> Claude Setup):"
echo "       $CONFIG_FILE"
echo "  2. Restart Claude Desktop."
echo "  3. Ask Claude: 'run bridgistic_get_site_info' to verify the connection."
