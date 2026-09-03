<?php
/**
 * Generates ready-to-paste Claude configs and the export package contents.
 *
 * Secrets policy: every generator takes an optional $secret. When absent, a
 * clearly-marked placeholder is emitted. Callers may only pass a real secret
 * immediately after key creation/rotation (it is never readable later).
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConfigGenerator {

	public const SECRET_PLACEHOLDER = 'PASTE_YOUR_SECRET_HERE';

	/** Marker option read by the Health Check ("MCP config generated"). */
	public const GENERATED_FLAG = 'bridgistic_config_generated';

	/**
	 * Claude Desktop config (claude_desktop_config.json shape).
	 *
	 * @return array<string,mixed>
	 */
	public static function desktop( string $key_id, ?string $secret = null ): array {
		return array(
			'mcpServers' => array(
				'bridgistic' => array(
					'command' => 'node',
					'args'    => array( '/absolute/path/to/bridgistic-claude-marketplace/mcp-server/dist/index.js' ),
					'env'     => self::env_block( $key_id, $secret ),
				),
			),
		);
	}

	/**
	 * Claude Code project config (.mcp.json shape).
	 *
	 * @return array<string,mixed>
	 */
	public static function code( string $key_id, ?string $secret = null ): array {
		// Same JSON shape; kept separate so the two downloads can diverge later.
		return self::desktop( $key_id, $secret );
	}

	/**
	 * One-liner for `claude mcp add` users.
	 */
	public static function code_cli( string $key_id, ?string $secret = null ): string {
		$secret = $secret ?: self::SECRET_PLACEHOLDER;
		return sprintf(
			'claude mcp add bridgistic --env BRIDGISTIC_SITE_URL=%s --env BRIDGISTIC_KEY_ID=%s --env BRIDGISTIC_KEY_SECRET=%s -- node /absolute/path/to/bridgistic-claude-marketplace/mcp-server/dist/index.js',
			home_url(),
			$key_id,
			$secret
		);
	}

	/**
	 * Paste-ready values for the Claude Desktop extension (.mcpb) prompt.
	 * Plain text, not JSON — users copy these into the extension's settings UI.
	 */
	public static function extension_values( string $key_id, ?string $secret = null ): string {
		return implode(
			"\n",
			array(
				'WordPress Site URL:    ' . home_url(),
				'Bridgistic Key ID:     ' . $key_id,
				'Bridgistic Key Secret: ' . ( $secret ?: self::SECRET_PLACEHOLDER ),
			)
		);
	}

	/**
	 * OpenAI Codex CLI config — `[mcp_servers.bridgistic]` block for
	 * `~/.codex/config.toml` (project-local `.codex/config.toml` also works
	 * for trusted projects). Uses the published `bridgistic-mcp-server` npm
	 * package via `npx`, so there's nothing to clone or build first.
	 */
	public static function codex( string $key_id, ?string $secret = null ): string {
		$secret = $secret ?: self::SECRET_PLACEHOLDER;
		return sprintf(
			"[mcp_servers.bridgistic]\ncommand = \"npx\"\nargs = [\"-y\", \"bridgistic-mcp-server\"]\nenv = { BRIDGISTIC_SITE_URL = %s, BRIDGISTIC_KEY_ID = %s, BRIDGISTIC_KEY_SECRET = %s }\n",
			self::toml_string( home_url() ),
			self::toml_string( $key_id ),
			self::toml_string( $secret )
		);
	}

	/**
	 * Gemini CLI config — `mcpServers` entry for `~/.gemini/settings.json`
	 * (or a project-local `.gemini/settings.json`). Same npx-based launch
	 * as the Codex config.
	 *
	 * @return array<string,mixed>
	 */
	public static function gemini_cli( string $key_id, ?string $secret = null ): array {
		return array(
			'mcpServers' => array(
				'bridgistic' => array(
					'command' => 'npx',
					'args'    => array( '-y', 'bridgistic-mcp-server' ),
					'env'     => self::env_block( $key_id, $secret ),
				),
			),
		);
	}

	/** Quote + escape a value for a TOML basic string. */
	private static function toml_string( string $value ): string {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value ) . '"';
	}

	/**
	 * @return array<string,string>
	 */
	private static function env_block( string $key_id, ?string $secret ): array {
		return array(
			'BRIDGISTIC_SITE_URL'   => home_url(),
			'BRIDGISTIC_KEY_ID'     => $key_id,
			'BRIDGISTIC_KEY_SECRET' => $secret ?: self::SECRET_PLACEHOLDER,
		);
	}

	/**
	 * Pretty JSON for display / download.
	 *
	 * @param array<string,mixed> $config Config array.
	 */
	public static function to_json( array $config ): string {
		return (string) wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/** Record that the user generated a config (Health Check reads this). */
	public static function mark_generated(): void {
		update_option( self::GENERATED_FLAG, time(), false );
	}

	// ---- Export package -----------------------------------------------------

	/**
	 * Build the export zip. Returns the tmp file path or WP_Error.
	 *
	 * @param array<string,bool> $include  Sections to include.
	 * @param string             $key_id   Key id to embed in configs.
	 * @param string|null        $secret   Only ever the just-created secret, never a stored one.
	 * @return string|\WP_Error
	 */
	public static function build_package( array $include, string $key_id, ?string $secret = null ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'bridgistic_zip', __( 'The PHP zip extension is not available on this server. Copy the configs from the AI / MCP Connections page instead.', 'bridgistic' ) );
		}

		$tmp = wp_tempnam( 'bridgistic-claude-package.zip' );
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp, \ZipArchive::OVERWRITE ) ) {
			return new \WP_Error( 'bridgistic_zip_open', __( 'Could not create the zip file (temp directory not writable?).', 'bridgistic' ) );
		}

		$zip->addFromString( 'README.md', self::package_readme( $key_id, null !== $secret ) );

		if ( ! empty( $include['desktop'] ) ) {
			$zip->addFromString( 'claude_desktop_config.json', self::to_json( self::desktop( $key_id, $secret ) ) );
		}
		if ( ! empty( $include['code'] ) ) {
			$zip->addFromString( 'claude_code_config.json', self::to_json( self::code( $key_id, $secret ) ) );
		}

		$zip->addFromString( 'connections.example.json', self::to_json( array(
			'my-site' => array(
				'siteUrl' => home_url(),
				'keyId'   => $key_id,
				'secret'  => $secret ?: self::SECRET_PLACEHOLDER,
			),
		) ) );

		if ( ! empty( $include['scripts'] ) ) {
			$zip->addFromString( 'install-macos-linux.sh', self::install_sh() );
			$zip->addFromString( 'install-windows.ps1', self::install_ps1() );
		}
		if ( ! empty( $include['troubleshooting'] ) ) {
			$zip->addFromString( 'TROUBLESHOOTING.md', self::troubleshooting_md() );
		}

		$zip->close();
		return $tmp;
	}

	private static function package_readme( string $key_id, bool $has_secret ): string {
		$secret_note = $has_secret
			? "**This package CONTAINS your key secret.** Treat it like a password: do not share it, email it, or commit it to a repository. If it leaks, rotate the key in WP Admin -> Bridgistic -> Keys & Scopes."
			: "This package does NOT contain your key secret (it is shown only once at creation). Replace `" . self::SECRET_PLACEHOLDER . "` in the config files with the secret you copied, or rotate the key to get a new one.";

		$site = home_url();

		return <<<MD
# Bridgistic — Claude Setup Package

Generated by the Bridgistic WordPress plugin for:

- Site: {$site}
- Key ID: {$key_id}

{$secret_note}

## Quick start

1. Get the Bridgistic MCP server (once):
   `git clone https://github.com/wordpressistic/bridgistic.git`
   then `cd bridgistic-claude-marketplace && npm run build`
2. **Claude Desktop:** run the install script for your OS (below), or merge
   `claude_desktop_config.json` into your Claude Desktop config manually.
   Update the `args` path to point at `mcp-server/dist/index.js` inside the
   cloned repo. Restart Claude Desktop.
3. **Claude Code:** easiest path is the plugin marketplace:
   `/plugin marketplace add wordpressistic/bridgistic`
   `/plugin install bridgistic@bridgistic-marketplace`
   then export the three BRIDGISTIC_* environment variables from `claude_code_config.json`.
4. Verify: ask Claude to run `bridgistic_get_site_info`.

Problems? See TROUBLESHOOTING.md, or open WP Admin -> Bridgistic -> Health Check.
MD;
	}

	private static function install_sh(): string {
		return <<<'SH'
#!/usr/bin/env bash
# Bridgistic — merge claude_desktop_config.json into Claude Desktop (macOS/Linux).
# Backs up your existing config first. Run from inside the unzipped package.
set -euo pipefail

case "$(uname -s)" in
  Darwin) DIR="$HOME/Library/Application Support/Claude" ;;
  *)      DIR="${XDG_CONFIG_HOME:-$HOME/.config}/Claude" ;;
esac
FILE="$DIR/claude_desktop_config.json"
mkdir -p "$DIR"
[ -f "$FILE" ] && cp "$FILE" "$FILE.backup.$(date +%Y%m%d%H%M%S)" || echo '{}' > "$FILE"

FILE="$FILE" node <<'EOF'
const fs = require("fs");
const target = process.env.FILE;
const incoming = JSON.parse(fs.readFileSync("claude_desktop_config.json", "utf8"));
const config = JSON.parse(fs.readFileSync(target, "utf8"));
config.mcpServers = Object.assign({}, config.mcpServers, incoming.mcpServers);
fs.writeFileSync(target, JSON.stringify(config, null, 2) + "\n");
console.log("Merged bridgistic into " + target);
EOF

echo "Now edit the file and set the real path to mcp-server/dist/index.js, then restart Claude Desktop."
SH;
	}

	private static function install_ps1(): string {
		return <<<'PS1'
# Bridgistic — merge claude_desktop_config.json into Claude Desktop (Windows).
# Backs up your existing config first. Run from inside the unzipped package.
$ErrorActionPreference = "Stop"
$dir  = Join-Path $env:APPDATA "Claude"
$file = Join-Path $dir "claude_desktop_config.json"
New-Item -ItemType Directory -Force -Path $dir | Out-Null
if (Test-Path $file) {
    Copy-Item $file "$file.backup.$(Get-Date -Format yyyyMMddHHmmss)"
    $config = Get-Content $file -Raw | ConvertFrom-Json
} else {
    $config = [pscustomobject]@{}
}
$incoming = Get-Content "claude_desktop_config.json" -Raw | ConvertFrom-Json
if (-not ($config.PSObject.Properties.Name -contains "mcpServers")) {
    $config | Add-Member -NotePropertyName mcpServers -NotePropertyValue ([pscustomobject]@{})
}
foreach ($p in $incoming.mcpServers.PSObject.Properties) {
    if ($config.mcpServers.PSObject.Properties.Name -contains $p.Name) {
        $config.mcpServers.($p.Name) = $p.Value
    } else {
        $config.mcpServers | Add-Member -NotePropertyName $p.Name -NotePropertyValue $p.Value
    }
}
$config | ConvertTo-Json -Depth 10 | Set-Content -Path $file -Encoding UTF8
Write-Host "Merged bridgistic into $file"
Write-Host "Now edit the file and set the real path to mcp-server\dist\index.js, then restart Claude Desktop."
PS1;
	}

	private static function troubleshooting_md(): string {
		return <<<'MD'
# Troubleshooting

1. **Server missing in Claude Desktop** — check Node 20+ (`node --version`),
   check the `args` path exists, fully restart Claude Desktop.
2. **"No WordPress connections configured"** — the BRIDGISTIC_* env values
   did not reach the server. Re-check the `env` block of your config.
3. **401 bridgistic_auth_stale** — clock drift. Enable NTP on your computer
   or ask your host to fix server time (window is ±300 seconds).
4. **401 bridgistic_auth_signature** — wrong or stale secret. Rotate the key
   in WP Admin -> Bridgistic -> Keys & Scopes and paste the new secret.
5. **403 / HTML instead of JSON** — a security plugin or WAF is blocking
   `/wp-json/bridgistic/v1/`. Allowlist the namespace.
6. **404 on /wp-json/** — enable pretty permalinks (Settings -> Permalinks).

The full guide lives at:
https://github.com/wordpressistic/bridgistic/blob/main/plugins/bridgistic/package/TROUBLESHOOTING.md

Run WP Admin -> Bridgistic -> Health Check for automated diagnostics.
MD;
	}
}
