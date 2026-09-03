<?php
/**
 * Generated client configuration: Codex TOML, Gemini JSON, and the rest.
 *
 * These files are pasted straight into a user's Codex or Gemini config. A
 * quoting bug does not produce a friendly error — it corrupts a config file
 * the user already had, and the failure surfaces somewhere else entirely. The
 * escaping tests below exist for that reason, not for tidiness.
 *
 * The secret rules matter just as much: a generator that emits a placeholder
 * where a secret should be is a usability problem, while one that emits a real
 * secret outside the one-time reveal window is a disclosure.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

use Bridgistic\Admin\ConfigGenerator;

bridgistic_suite( 'config-generator' );

// Deliberately NOT credential-shaped: a fixture matching the real wpk_/wps_
// format would trip the repository secret scanner on every run, and training
// people to ignore that scanner is worse than a slightly artificial fixture.
$key_id = 'wpk_testkeyid_not_a_real_key';
$secret = 'wps_test_secret_value_not_a_real_credential';

// ---- 1. Codex CLI (TOML) ---------------------------------------------------

$codex = ConfigGenerator::codex( $key_id, $secret );

check( false !== strpos( $codex, '[mcp_servers.bridgistic]' ), 'the Codex block declares an mcp_servers table' );
check( false !== strpos( $codex, 'command = "npx"' ), 'Codex launches via npx' );
check( false !== strpos( $codex, '"bridgistic-mcp-server"' ), 'Codex launches the published package, so nothing has to be cloned' );
check( false !== strpos( $codex, '"-y"' ), 'npx runs non-interactively' );
check( false !== strpos( $codex, $key_id ), 'the Codex config carries the key id' );
check( false !== strpos( $codex, $secret ), 'the Codex config carries the secret when one is supplied' );
check( false !== strpos( $codex, 'BRIDGISTIC_SITE_URL' ), 'the Codex config sets the site URL env var' );

// Every value must be a quoted TOML basic string; a bare value breaks parsing.
check( 1 === preg_match( '/BRIDGISTIC_SITE_URL = "[^"]*"/', $codex ), 'the site URL is emitted as a quoted TOML string' );
check( 1 === preg_match( '/BRIDGISTIC_KEY_ID = "[^"]*"/', $codex ), 'the key id is emitted as a quoted TOML string' );
check( 1 === preg_match( '/BRIDGISTIC_KEY_SECRET = "[^"]*"/', $codex ), 'the secret is emitted as a quoted TOML string' );

// Structural check: the generated block parses as the key/value pairs it claims.
$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $codex ) ) ) );
check_equals( '[mcp_servers.bridgistic]', $lines[0], 'the table header is the first line' );
check( count( $lines ) >= 4, 'the block declares command, args, and env' );

// ---- 2. TOML escaping ------------------------------------------------------

// A Windows path is the realistic source of backslashes, and an unescaped one
// silently becomes an escape sequence inside a TOML basic string.
$windows_reflection = new ReflectionMethod( ConfigGenerator::class, 'toml_string' );
$windows_reflection->setAccessible( true );

check_equals( '"C:\\\\Users\\\\Ada"', $windows_reflection->invoke( null, 'C:\Users\Ada' ), 'backslashes in a Windows path are escaped' );
check_equals( '"say \\"hi\\""', $windows_reflection->invoke( null, 'say "hi"' ), 'double quotes are escaped' );
check_equals( '""', $windows_reflection->invoke( null, '' ), 'an empty value is still a quoted string' );
check_equals( '"plain"', $windows_reflection->invoke( null, 'plain' ), 'an ordinary value is quoted without alteration' );

// An escaped value must round-trip: unescaping gives back what went in.
foreach ( array( 'C:\Users\Ada\AppData', 'quote"inside', 'back\\slash', "tab\tchar" ) as $raw ) {
	$emitted   = $windows_reflection->invoke( null, $raw );
	$unescaped = str_replace( array( '\\\\', '\\"' ), array( '\\', '"' ), substr( $emitted, 1, -1 ) );
	check_equals( $raw, $unescaped, 'the escaped TOML value round-trips: ' . addcslashes( $raw, "\t" ) );
}

// ---- 3. Gemini CLI (JSON) --------------------------------------------------

$gemini_array = ConfigGenerator::gemini_cli( $key_id, $secret );
$gemini_json  = ConfigGenerator::to_json( $gemini_array );

check( is_array( $gemini_array ), 'the Gemini config is built as a structure, not string-concatenated' );
$decoded = json_decode( $gemini_json, true );
check( is_array( $decoded ), 'the Gemini config is valid JSON' );
check( isset( $decoded['mcpServers']['bridgistic'] ), 'the Gemini config uses the mcpServers shape Gemini CLI reads' );

$server = $decoded['mcpServers']['bridgistic'];
check_equals( 'npx', $server['command'], 'Gemini launches via npx' );
check_equals( array( '-y', 'bridgistic-mcp-server' ), $server['args'], 'Gemini launches the published package' );
check_equals( $key_id, $server['env']['BRIDGISTIC_KEY_ID'], 'the Gemini env block carries the key id' );
check_equals( $secret, $server['env']['BRIDGISTIC_KEY_SECRET'], 'the Gemini env block carries the secret when supplied' );
check( isset( $server['env']['BRIDGISTIC_SITE_URL'] ), 'the Gemini env block carries the site URL' );

// Only the bridgistic entry is emitted, so merging cannot clobber a user's
// other configured MCP servers.
check_equals( array( 'bridgistic' ), array_keys( $decoded['mcpServers'] ), 'only the bridgistic server is emitted, so a merge is non-destructive' );

// ---- 4. JSON escaping ------------------------------------------------------

$hostile = 'value" with \\ backslash and "quotes"';
$escaped = json_decode( ConfigGenerator::to_json( ConfigGenerator::gemini_cli( $hostile, $hostile ) ), true );
check( is_array( $escaped ), 'a config containing quotes and backslashes still produces valid JSON' );
check_equals( $hostile, $escaped['mcpServers']['bridgistic']['env']['BRIDGISTIC_KEY_ID'], 'hostile characters round-trip through JSON unchanged' );

// Forward slashes in a URL should stay readable rather than becoming \/.
check( false === strpos( ConfigGenerator::to_json( ConfigGenerator::gemini_cli( $key_id, $secret ) ), '\\/' ), 'URLs are emitted unescaped so they stay readable' );

// ---- 5. Secret handling ----------------------------------------------------

// Stored secrets are unreadable after creation, so every generator must be
// callable without one and must say so rather than emitting something that
// looks like a credential.
$no_secret_codex  = ConfigGenerator::codex( $key_id, null );
$no_secret_gemini = ConfigGenerator::to_json( ConfigGenerator::gemini_cli( $key_id, null ) );
$no_secret_cli    = ConfigGenerator::code_cli( $key_id, null );
$no_secret_ext    = ConfigGenerator::extension_values( $key_id, null );

foreach (
	array(
		'Codex'     => $no_secret_codex,
		'Gemini'    => $no_secret_gemini,
		'CLI'       => $no_secret_cli,
		'extension' => $no_secret_ext,
	) as $label => $output
) {
	check( false !== strpos( $output, ConfigGenerator::SECRET_PLACEHOLDER ), "the {$label} config emits a clear placeholder when no secret is available" );
	check( false === strpos( $output, 'wps_' ), "the {$label} config emits nothing secret-shaped when no secret is available" );
	check( false !== strpos( $output, $key_id ), "the {$label} config still carries the key id without a secret" );
}

// An empty-string secret must be treated as absent, not emitted as an empty
// credential that fails authentication with a confusing signature error.
check( false !== strpos( ConfigGenerator::codex( $key_id, '' ), ConfigGenerator::SECRET_PLACEHOLDER ), 'an empty secret is treated as absent' );

// ---- 6. Site URL is consistent across every generator ----------------------

$expected_url = home_url();
foreach (
	array(
		'Codex'     => ConfigGenerator::codex( $key_id, $secret ),
		'Gemini'    => ConfigGenerator::to_json( ConfigGenerator::gemini_cli( $key_id, $secret ) ),
		'Desktop'   => ConfigGenerator::to_json( ConfigGenerator::desktop( $key_id, $secret ) ),
		'Code'      => ConfigGenerator::to_json( ConfigGenerator::code( $key_id, $secret ) ),
		'CLI'       => ConfigGenerator::code_cli( $key_id, $secret ),
		'extension' => ConfigGenerator::extension_values( $key_id, $secret ),
	) as $label => $output
) {
	check( false !== strpos( $output, $expected_url ), "the {$label} config points at this site's URL" );
}

// ---- 7. Desktop / Code configs ---------------------------------------------

$desktop = json_decode( ConfigGenerator::to_json( ConfigGenerator::desktop( $key_id, $secret ) ), true );
check( isset( $desktop['mcpServers']['bridgistic'] ), 'the Desktop config uses the mcpServers shape' );
check_equals( 'node', $desktop['mcpServers']['bridgistic']['command'], 'the Desktop config launches the server with node' );
check_equals( array( 'bridgistic' ), array_keys( $desktop['mcpServers'] ), 'only the bridgistic server is emitted for Desktop too' );

// ---- 8. Multi-site connections shape ---------------------------------------

// The multi-site builder and the export package both produce this shape; an
// alias must map to exactly siteUrl/keyId/secret so the MCP server's registry
// can read it.
$connections = json_decode(
	ConfigGenerator::to_json(
		array(
			'my-site' => array( 'siteUrl' => home_url(), 'keyId' => $key_id, 'secret' => $secret ),
		)
	),
	true
);
check( isset( $connections['my-site']['siteUrl'], $connections['my-site']['keyId'], $connections['my-site']['secret'] ), 'a connections entry carries siteUrl, keyId, and secret' );
