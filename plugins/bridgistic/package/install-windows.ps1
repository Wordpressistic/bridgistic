# Bridgistic - Claude Desktop setup helper (Windows)
#
# Merges a Bridgistic MCP server entry into your Claude Desktop config.
# Safe by design: backs up the existing config first, only touches the
# "bridgistic" server entry, and never phones home.
#
# Usage (PowerShell):
#   .\install-windows.ps1 -ServerJs "C:\path\to\bridgistic-claude-marketplace\mcp-server\dist\index.js"

param(
    [Parameter(Mandatory = $true)]
    [string]$ServerJs
)

$ErrorActionPreference = "Stop"

# --- Preconditions -----------------------------------------------------------
$node = Get-Command node -ErrorAction SilentlyContinue
if (-not $node) {
    Write-Error "Node.js is not installed (need Node 20+). Get it from https://nodejs.org/"
}

$nodeMajor = [int](node -p 'process.versions.node.split(".")[0]')
if ($nodeMajor -lt 20) {
    Write-Error "Node.js 20+ required, found $(node --version)."
}

if (-not (Test-Path $ServerJs)) {
    Write-Error "MCP server not found at: $ServerJs`nBuild it first: from the repo root run 'npm run build'."
}

# --- Locate Claude Desktop config ---------------------------------------------
$configDir  = Join-Path $env:APPDATA "Claude"
$configFile = Join-Path $configDir "claude_desktop_config.json"

New-Item -ItemType Directory -Force -Path $configDir | Out-Null

if (Test-Path $configFile) {
    $stamp = Get-Date -Format "yyyyMMddHHmmss"
    Copy-Item $configFile "$configFile.backup.$stamp"
    Write-Host "Backed up existing config."
    $config = Get-Content $configFile -Raw | ConvertFrom-Json
} else {
    $config = [pscustomobject]@{}
}

# --- Merge the bridgistic entry (keep other servers untouched) -----------------
if (-not ($config.PSObject.Properties.Name -contains "mcpServers")) {
    $config | Add-Member -NotePropertyName mcpServers -NotePropertyValue ([pscustomobject]@{})
}

$existingEnv = $null
if ($config.mcpServers.PSObject.Properties.Name -contains "bridgistic") {
    $existingEnv = $config.mcpServers.bridgistic.env
}

$entry = [pscustomobject]@{
    command = "node"
    args    = @($ServerJs)
    env     = [pscustomobject]@{
        BRIDGISTIC_SITE_URL   = if ($existingEnv -and $existingEnv.BRIDGISTIC_SITE_URL)   { $existingEnv.BRIDGISTIC_SITE_URL }   else { "https://example.com" }
        BRIDGISTIC_KEY_ID     = if ($existingEnv -and $existingEnv.BRIDGISTIC_KEY_ID)     { $existingEnv.BRIDGISTIC_KEY_ID }     else { "your_key_id" }
        BRIDGISTIC_KEY_SECRET = if ($existingEnv -and $existingEnv.BRIDGISTIC_KEY_SECRET) { $existingEnv.BRIDGISTIC_KEY_SECRET } else { "your_key_secret" }
    }
}

if ($config.mcpServers.PSObject.Properties.Name -contains "bridgistic") {
    $config.mcpServers.bridgistic = $entry
} else {
    $config.mcpServers | Add-Member -NotePropertyName bridgistic -NotePropertyValue $entry
}

$config | ConvertTo-Json -Depth 10 | Set-Content -Path $configFile -Encoding UTF8
Write-Host "Wrote $configFile"

Write-Host ""
Write-Host "Done. Next steps:"
Write-Host "  1. Open the config and fill in your real site URL, key ID and secret"
Write-Host "     (generate them in WP Admin -> Bridgistic -> Claude Setup):"
Write-Host "       $configFile"
Write-Host "  2. Restart Claude Desktop."
Write-Host "  3. Ask Claude: 'run bridgistic_get_site_info' to verify the connection."
