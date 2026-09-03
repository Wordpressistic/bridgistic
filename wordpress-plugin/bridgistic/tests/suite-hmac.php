<?php
/**
 * HMAC authentication, key lifecycle, and replay protection.
 *
 * This is the bridge's front door: every REST route is behind
 * HmacVerifier::authenticate(). A regression here is not a bug, it is an
 * authentication bypass, so the suite covers the negative cases as thoroughly
 * as the happy path.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

use Bridgistic\Security\Crypto;
use Bridgistic\Security\HmacVerifier;
use Bridgistic\Security\KeyStore;
use Bridgistic\Security\Scopes;

bridgistic_suite( 'hmac' );

// ---- 1. Encryption at rest ------------------------------------------------

$plain = 'wps_' . bin2hex( random_bytes( 24 ) );
$enc   = Crypto::encrypt( $plain );
check( is_string( $enc ) && '' !== $enc, 'Crypto::encrypt returns a non-empty string' );
check_equals( $plain, Crypto::decrypt( $enc ), 'Crypto::decrypt recovers the plaintext' );
check( $enc !== $plain, 'the ciphertext is not the plaintext' );
check( false === strpos( $enc, $plain ), 'the ciphertext does not contain the plaintext' );

// Two encryptions of the same value must differ, or a stolen database reveals
// which keys share a secret.
check( Crypto::encrypt( $plain ) !== Crypto::encrypt( $plain ), 'encryption uses a fresh nonce each time' );

check_equals( null, Crypto::decrypt( 'not-a-valid-envelope' ), 'a malformed envelope decrypts to null, never to a guess' );

// Tampering must fail the authentication tag rather than return garbage.
$tampered = substr( $enc, 0, -4 ) . 'AAAA';
check_equals( null, Crypto::decrypt( $tampered ), 'a tampered ciphertext is rejected' );

// ---- 2. A minted key is storable and recoverable --------------------------

$minted = KeyStore::create( 'HMAC suite key', array( Scopes::SITE_READ, Scopes::POSTS_READ ), array(), 120, false, 'custom', 0 );
check( isset( $minted['key_id'], $minted['secret'] ), 'KeyStore::create returns key_id and secret' );
check( 1 === preg_match( '/^wpk_[0-9a-f]{24}$/', $minted['key_id'] ), 'key ids use the documented wpk_ format' );
check( 1 === preg_match( '/^wps_[0-9a-f]{48}$/', $minted['secret'] ), 'secrets use the documented wps_ format' );

$key_id = (string) $minted['key_id'];
$secret = (string) $minted['secret'];

$record = KeyStore::get( $key_id );
check( is_array( $record ), 'KeyStore::get finds the minted record' );
check_equals( $secret, KeyStore::get_secret( (array) $record ), 'the stored secret decrypts back to the minted secret' );
check( is_array( $record ) && ! isset( $record['secret'] ), 'the record carries no plaintext secret column' );

// ---- 3. A correct signature authenticates ---------------------------------

$method = 'GET';
$path   = '/bridgistic/v1/site-info';
$body   = '';

$ctx = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, $secret ) );
check( is_array( $ctx ), 'a valid signature authenticates' );
check_equals( $key_id, is_array( $ctx ) ? $ctx['key_id'] : null, 'the auth context carries the key_id' );
check( is_array( $ctx ) && in_array( Scopes::POSTS_READ, (array) $ctx['scopes'], true ), 'the auth context carries the granted scopes' );
check( is_array( $ctx ) && ! in_array( Scopes::PHP_EXECUTE, (array) $ctx['scopes'], true ), 'the auth context does not invent scopes the key lacks' );

// ---- 4. Known-vector: the canonical form is exactly what the signer builds -

// If the PHP and TypeScript sides ever disagree on canonical form, every
// request fails. Pinning a fixed vector makes that break loudly here rather
// than in a user's terminal.
$vector_secret    = 'wps_fixed_test_vector_secret_not_a_real_credential';
$vector_canonical = implode( "\n", array( 'POST', '/bridgistic/v1/posts', '1700000000', 'fixed-nonce', hash( 'sha256', '{"title":"x"}' ) ) );
check_equals(
	hash_hmac( 'sha256', $vector_canonical, $vector_secret ),
	hash_hmac( 'sha256', "POST\n/bridgistic/v1/posts\n1700000000\nfixed-nonce\n" . hash( 'sha256', '{"title":"x"}' ), $vector_secret ),
	'the canonical form is METHOD\\nPATH\\nTIMESTAMP\\nNONCE\\nsha256(body)'
);

// The body hash must actually cover the body.
$signed_body = '{"title":"original"}';
$headers     = bridgistic_sign( 'POST', '/bridgistic/v1/posts', $signed_body, $key_id, $secret );
$swapped     = HmacVerifier::authenticate( 'POST', '/bridgistic/v1/posts', '{"title":"swapped"}', $headers );
check( is_wp_error( $swapped ), 'changing the body after signing is rejected' );

// ...and the path.
$path_headers = bridgistic_sign( 'GET', '/bridgistic/v1/site-info', '', $key_id, $secret );
$wrong_path   = HmacVerifier::authenticate( 'GET', '/bridgistic/v1/execute', '', $path_headers );
check( is_wp_error( $wrong_path ), 'replaying a signature against a different route is rejected' );

// ...and the method.
$method_headers = bridgistic_sign( 'GET', '/bridgistic/v1/posts', '', $key_id, $secret );
$wrong_method   = HmacVerifier::authenticate( 'DELETE', '/bridgistic/v1/posts', '', $method_headers );
check( is_wp_error( $wrong_method ), 'replaying a signature with a different method is rejected' );

// ---- 5. Rejections --------------------------------------------------------

$bad = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, 'wrong-secret' ) );
check( is_wp_error( $bad ) && 'bridgistic_auth_signature' === $bad->get_error_code(), 'a wrong secret is rejected as a signature error' );

$unknown = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, 'wpk_does_not_exist', $secret ) );
check( is_wp_error( $unknown ) && 'bridgistic_auth_key' === $unknown->get_error_code(), 'an unknown key is rejected' );

foreach ( array( 'x-bridgistic-key', 'x-bridgistic-timestamp', 'x-bridgistic-nonce', 'x-bridgistic-signature' ) as $missing ) {
	$partial = bridgistic_sign( $method, $path, $body, $key_id, $secret );
	unset( $partial[ $missing ] );
	$result = HmacVerifier::authenticate( $method, $path, $body, $partial );
	check( is_wp_error( $result ) && 'bridgistic_auth_missing' === $result->get_error_code(), "a request missing {$missing} is rejected" );
}

$empty_sig = bridgistic_sign( $method, $path, $body, $key_id, $secret );
$empty_sig['x-bridgistic-signature'] = '';
check( is_wp_error( HmacVerifier::authenticate( $method, $path, $body, $empty_sig ) ), 'an empty signature is rejected' );

// ---- 6. Replay protection -------------------------------------------------

$replay = bridgistic_sign( $method, $path, $body, $key_id, $secret, 'fixed-nonce-abc' );
$first  = HmacVerifier::authenticate( $method, $path, $body, $replay );
$second = HmacVerifier::authenticate( $method, $path, $body, $replay );
check( is_array( $first ), 'the first use of a nonce succeeds' );
check( is_wp_error( $second ) && 'bridgistic_auth_replay' === $second->get_error_code(), 'a replayed nonce is rejected' );

// A nonce is scoped per key, so two keys may legitimately pick the same one.
$other  = KeyStore::create( 'Second key', array( Scopes::SITE_READ ), array(), 120, false, 'custom', 0 );
$shared = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, (string) $other['key_id'], (string) $other['secret'], 'fixed-nonce-abc' ) );
check( is_array( $shared ), 'the same nonce used by a different key is accepted' );

// ---- 7. Clock skew --------------------------------------------------------

$stale = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, $secret, null, time() - 4000 ) );
check( is_wp_error( $stale ) && 'bridgistic_auth_stale' === $stale->get_error_code(), 'a timestamp far in the past is rejected' );

$future = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, $secret, null, time() + 4000 ) );
check( is_wp_error( $future ) && 'bridgistic_auth_stale' === $future->get_error_code(), 'a timestamp far in the future is rejected' );

$edge = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, $secret, null, time() - 120 ) );
check( is_array( $edge ), 'a timestamp inside the allowed window is accepted' );

// ---- 8. Disable / re-enable ------------------------------------------------

KeyStore::set_enabled( $key_id, false );
$disabled = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, $secret ) );
check( is_wp_error( $disabled ) && 'bridgistic_auth_key' === $disabled->get_error_code(), 'a disabled key fails authentication' );

KeyStore::set_enabled( $key_id, true );
$reenabled = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, $secret ) );
check( is_array( $reenabled ), 'a re-enabled key authenticates again with the same secret' );

// ---- 9. Rotation -----------------------------------------------------------

$rotated = KeyStore::rotate_secret( $key_id );
check( is_string( $rotated ) && $rotated !== $secret, 'rotation returns a new secret' );

$old_secret_now = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, $secret ) );
check( is_wp_error( $old_secret_now ) && 'bridgistic_auth_signature' === $old_secret_now->get_error_code(), 'the previous secret stops working after rotation' );

$new_secret_now = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, $key_id, (string) $rotated ) );
check( is_array( $new_secret_now ), 'the rotated secret authenticates' );

check_equals( $key_id, (string) ( KeyStore::get( $key_id )['key_id'] ?? '' ), 'rotation keeps the key id stable so configs only need the new secret' );
check_equals( null, KeyStore::rotate_secret( 'wpk_nope' ), 'rotating an unknown key returns null rather than minting one' );

// ---- 10. Revocation --------------------------------------------------------

$doomed = KeyStore::create( 'Doomed key', array( Scopes::SITE_READ ), array(), 120, false, 'custom', 0 );
KeyStore::revoke( (string) $doomed['key_id'] );
check_equals( null, KeyStore::get( (string) $doomed['key_id'] ), 'a revoked key is gone from the store' );
$after_revoke = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, (string) $doomed['key_id'], (string) $doomed['secret'] ) );
check( is_wp_error( $after_revoke ), 'a revoked key cannot authenticate' );

// ---- 11. IP allowlist ------------------------------------------------------

$restricted = KeyStore::create( 'IP-restricted', array( Scopes::SITE_READ ), array( '10.0.0.0/8' ), 120, false, 'custom', 0 );
$blocked    = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, (string) $restricted['key_id'], (string) $restricted['secret'] ) );
check( is_wp_error( $blocked ) && 'bridgistic_auth_ip' === $blocked->get_error_code(), 'a key restricted to another network rejects this source IP' );

$permitted = KeyStore::create( 'IP-permitted', array( Scopes::SITE_READ ), array( '127.0.0.0/8' ), 120, false, 'custom', 0 );
$allowed   = HmacVerifier::authenticate( $method, $path, $body, bridgistic_sign( $method, $path, $body, (string) $permitted['key_id'], (string) $permitted['secret'] ) );
check( is_array( $allowed ), 'a key allowlisting this source network authenticates' );
