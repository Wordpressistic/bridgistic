<?php
/**
 * OAuth 2.1 / PKCE authorization server.
 *
 * The token endpoint is the only route in the plugin that is not behind HMAC,
 * because no HMAC key exists yet at that point in the flow — the entire
 * security boundary is the OAuth/PKCE contract. Every clause of that contract
 * gets a test here, including the ones that only matter when someone is
 * actively attacking the flow.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

use Bridgistic\Oauth;
use Bridgistic\Admin\Presets;
use Bridgistic\Security\Scopes;

bridgistic_suite( 'oauth' );

const VALID_REDIRECT = 'https://mcp.wpistic.cloud/wp-callback';

// ---- 1. redirect_uri allowlist ---------------------------------------------

check( Oauth::redirect_uri_allowed( VALID_REDIRECT ), 'the official connector callback is allowed' );

$rejected_redirects = array(
	'http://mcp.wpistic.cloud/wp-callback'            => 'plain http',
	'https://evil.example/wp-callback'                => 'a different host',
	'https://mcp.wpistic.cloud.evil.example/wp-callback' => 'a suffix-extended host',
	'https://evil.example/?x=https://mcp.wpistic.cloud/wp-callback' => 'the allowed host in a query string',
	'https://user:pass@mcp.wpistic.cloud/wp-callback' => 'embedded credentials',
	'https://mcp.wpistic.cloud:8443/wp-callback'      => 'a non-default port',
	'https://mcp.wpistic.cloud/somewhere-else'        => 'a different path on the allowed host',
	'https://mcp.wpistic.cloud'                       => 'no callback path',
	'//mcp.wpistic.cloud/wp-callback'                 => 'a scheme-relative URL',
	'javascript:alert(1)'                             => 'a javascript: URL',
	''                                                => 'an empty string',
	'not a url'                                       => 'garbage',
);
foreach ( $rejected_redirects as $uri => $why ) {
	check( ! Oauth::redirect_uri_allowed( $uri ), "redirect_uri is refused: {$why}" );
}

// Subdomain wildcards must not be inferred from the allowed host.
check( ! Oauth::redirect_uri_allowed( 'https://sub.mcp.wpistic.cloud/wp-callback' ), 'a subdomain of the allowed host is refused' );

// ---- 2. PKCE parameter validation ------------------------------------------

$verifier  = 'test-verifier-' . str_repeat( 'a', 40 );
$challenge = bridgistic_pkce_challenge( $verifier );

check( Oauth::valid_code_challenge( $challenge ), 'a real S256 challenge is accepted' );
check_equals( 43, strlen( $challenge ), 'a base64url SHA-256 challenge is 43 characters' );

check( ! Oauth::valid_code_challenge( '' ), 'an empty challenge is refused' );
check( ! Oauth::valid_code_challenge( str_repeat( 'a', 42 ) ), 'a too-short challenge is refused' );
check( ! Oauth::valid_code_challenge( str_repeat( 'a', 44 ) ), 'a too-long challenge is refused' );
check( ! Oauth::valid_code_challenge( str_repeat( 'a', 42 ) . '+' ), 'standard base64 (not base64url) is refused' );
check( ! Oauth::valid_code_challenge( str_repeat( 'a', 42 ) . '=' ), 'a padded challenge is refused' );

check( Oauth::valid_code_verifier( $verifier ), 'a well-formed verifier is accepted' );
check( Oauth::valid_code_verifier( str_repeat( 'a', 43 ) ), 'a 43-character verifier is accepted (RFC 7636 minimum)' );
check( Oauth::valid_code_verifier( str_repeat( 'a', 128 ) ), 'a 128-character verifier is accepted (RFC 7636 maximum)' );
check( ! Oauth::valid_code_verifier( str_repeat( 'a', 42 ) ), 'a 42-character verifier is refused' );
check( ! Oauth::valid_code_verifier( str_repeat( 'a', 129 ) ), 'a 129-character verifier is refused' );
check( ! Oauth::valid_code_verifier( str_repeat( 'a', 40 ) . '!@#$' ), 'a verifier with reserved characters is refused' );
check( ! Oauth::valid_code_verifier( '' ), 'an empty verifier is refused' );

check_equals( 'S256', Oauth::CODE_CHALLENGE_METHOD, 'S256 is the only advertised challenge method' );

// ---- 3. Successful exchange ------------------------------------------------

$code = Oauth::issue_code( VALID_REDIRECT, $challenge, array( Scopes::SITE_READ, Scopes::POSTS_READ ), false );
check( 1 === preg_match( '/^[0-9a-f]{64}$/', $code ), 'an authorization code is 256 bits of hex' );

$result = Oauth::redeem_code( $code, Oauth::CLIENT_ID, VALID_REDIRECT, $verifier );
check( ! is_wp_error( $result ), 'a correct exchange succeeds' );
check( is_array( $result ) && isset( $result['key_id'], $result['key_secret'] ), 'the exchange returns a real Bridgistic key' );
check( is_array( $result ) && 1 === preg_match( '/^wpk_/', (string) $result['key_id'] ), 'the issued key uses the normal key format' );
check(
	is_array( $result ) && array( Scopes::SITE_READ, Scopes::POSTS_READ ) === array_values( (array) $result['scopes'] ),
	'the issued key carries exactly the approved scopes'
);

// ---- 4. Single use ---------------------------------------------------------

$reused = Oauth::redeem_code( $code, Oauth::CLIENT_ID, VALID_REDIRECT, $verifier );
check( is_wp_error( $reused ), 'an authorization code cannot be redeemed twice' );
check( is_wp_error( $reused ) && 'bridgistic_oauth_grant' === $reused->get_error_code(), 'a reused code reports a grant error' );

// A failed attempt must also burn the code, or an attacker gets unlimited
// verifier guesses against a code they hold.
$burn_code = Oauth::issue_code( VALID_REDIRECT, $challenge, array( Scopes::SITE_READ ), false );
$wrong     = Oauth::redeem_code( $burn_code, Oauth::CLIENT_ID, VALID_REDIRECT, str_repeat( 'b', 50 ) );
check( is_wp_error( $wrong ), 'a wrong verifier fails' );
$retry_correct = Oauth::redeem_code( $burn_code, Oauth::CLIENT_ID, VALID_REDIRECT, $verifier );
check( is_wp_error( $retry_correct ), 'a code is consumed even by a failed attempt, so the verifier cannot be brute-forced' );

// ---- 5. Contract violations ------------------------------------------------

$cases = array(
	'wrong client_id'      => static fn( string $c ) => Oauth::redeem_code( $c, 'some-other-client', VALID_REDIRECT, $verifier ),
	'empty client_id'      => static fn( string $c ) => Oauth::redeem_code( $c, '', VALID_REDIRECT, $verifier ),
	'mismatched redirect'  => static fn( string $c ) => Oauth::redeem_code( $c, Oauth::CLIENT_ID, 'https://mcp.wpistic.cloud/other', $verifier ),
	'missing verifier'     => static fn( string $c ) => Oauth::redeem_code( $c, Oauth::CLIENT_ID, VALID_REDIRECT, '' ),
	'short verifier'       => static fn( string $c ) => Oauth::redeem_code( $c, Oauth::CLIENT_ID, VALID_REDIRECT, 'short' ),
	'unrelated verifier'   => static fn( string $c ) => Oauth::redeem_code( $c, Oauth::CLIENT_ID, VALID_REDIRECT, str_repeat( 'z', 50 ) ),
);

foreach ( $cases as $label => $attempt ) {
	$fresh  = Oauth::issue_code( VALID_REDIRECT, $challenge, array( Scopes::SITE_READ ), false );
	$outcome = $attempt( $fresh );
	check( is_wp_error( $outcome ), "the exchange is refused: {$label}" );
}

$unknown_code = Oauth::redeem_code( str_repeat( 'f', 64 ), Oauth::CLIENT_ID, VALID_REDIRECT, $verifier );
check( is_wp_error( $unknown_code ), 'a code this site never issued is refused' );

// ---- 6. Expiry -------------------------------------------------------------

$expiring  = Oauth::issue_code( VALID_REDIRECT, $challenge, array( Scopes::SITE_READ ), false );
$transient = 'bridgistic_oauth_code_' . hash( 'sha256', $expiring );
bridgistic_test_age_transient( $transient, 400 ); // TTL is 300s
$expired = Oauth::redeem_code( $expiring, Oauth::CLIENT_ID, VALID_REDIRECT, $verifier );
check( is_wp_error( $expired ), 'an expired authorization code is refused' );

// ---- 7. issue_code refuses to mint an unusable grant -----------------------

check_equals( '', Oauth::issue_code( 'https://evil.example/wp-callback', $challenge, array( Scopes::SITE_READ ), false ), 'no code is minted for a disallowed redirect_uri' );
check_equals( '', Oauth::issue_code( VALID_REDIRECT, 'not-a-valid-challenge', array( Scopes::SITE_READ ), false ), 'no code is minted for a malformed challenge' );

// ---- 8. Preset resolution fails safe ---------------------------------------

$read_only = Presets::get_or_safest( 'read_only' );
foreach ( array( 'does_not_exist', '', 'full_trust', 'DEVELOPER', '../developer', 'developer ' ) as $forged ) {
	$resolved = Presets::get_or_safest( $forged );
	if ( 'read_only' === $forged ) {
		continue;
	}
	check_equals(
		$read_only['scopes'],
		$resolved['scopes'],
		"an unknown preset id resolves to Read-only, never something broader: " . var_export( $forged, true )
	);
}

// The narrowest preset must genuinely be narrow.
check( ! in_array( Scopes::PHP_EXECUTE, $read_only['scopes'], true ), 'the fallback preset does not grant php:execute' );
check( ! in_array( Scopes::DB_WRITE, $read_only['scopes'], true ), 'the fallback preset does not grant db:write' );
check( ! in_array( Scopes::FS_WRITE, $read_only['scopes'], true ), 'the fallback preset does not grant fs:write' );
check( ! in_array( Scopes::POSTS_WRITE, $read_only['scopes'], true ), 'the fallback preset does not grant posts:write' );

// A real preset id must still resolve to itself.
check_equals( Presets::get( 'developer' )['scopes'], Presets::get_or_safest( 'developer' )['scopes'], 'a known preset id resolves to that preset' );

// ---- 9. Scopes are sanitised on the way in ---------------------------------

$sanitised = Scopes::sanitize( array( Scopes::SITE_READ, 'not:a:real:scope', Scopes::PHP_EXECUTE, Scopes::SITE_READ ) );
check( in_array( Scopes::SITE_READ, $sanitised, true ), 'a valid scope survives sanitisation' );
check( ! in_array( 'not:a:real:scope', $sanitised, true ), 'an invented scope is dropped' );
check_equals( count( $sanitised ), count( array_unique( $sanitised ) ), 'duplicate scopes are collapsed' );

// An OAuth grant carrying a forged scope list must not mint a key with it.
$forged_code   = Oauth::issue_code( VALID_REDIRECT, $challenge, array( Scopes::SITE_READ, 'woo:everything', 'php:execute:always' ), false );
$forged_result = Oauth::redeem_code( $forged_code, Oauth::CLIENT_ID, VALID_REDIRECT, $verifier );
check( is_array( $forged_result ), 'a grant with some invalid scopes still redeems' );
check(
	is_array( $forged_result ) && array( Scopes::SITE_READ ) === array_values( (array) $forged_result['scopes'] ),
	'invented scopes in a grant are stripped before the key is minted'
);
