<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build a transient key from the Turnstile token in the current POST.
 *
 * @param string $key   Verification key, e.g. 'cfturnstile_checkout_checked'.
 * @param string $token Optional. Explicit token value (e.g. from block checkout extensions data). Falls back to $_POST['cf-turnstile-response'].
 * @return string|false Transient key, or false if no token is present.
 */
function cfturnstile_transient_key( $key, $token = '' ) {
	if ( empty( $token ) ) {
		$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( $_POST['cf-turnstile-response'] ) : '';
	}
	if ( $token ) {
		return 'cft_' . substr( md5( $key . '_t' . $token ), 0, 20 );
	}

	return false;
}

/**
 * Store a verification flag tied to the current Turnstile token.
 *
 * Uses a short-lived transient keyed to the token so each token can only
 * be used once. Turnstile tokens are single-use by design.
 *
 * @param string $key    Verification key, e.g. 'cfturnstile_checkout_checked'.
 * @param string $token  Optional. Explicit token value for contexts where the token is not in $_POST (e.g. block checkout).
 * @param int    $expire Optional. Transient TTL in seconds. Default 10.
 */
function cfturnstile_set_verified( $key, $token = '', $expire = 10 ) {
	$transient_key = cfturnstile_transient_key( $key, $token );
	if ( $transient_key ) {
		set_transient( $transient_key, 1, $expire );
	}
}

/**
 * Check whether a verification flag is set for the current Turnstile token.
 *
 * @param string $key   Verification key, e.g. 'cfturnstile_checkout_checked'.
 * @param string $token Optional. Explicit token value for contexts where the token is not in $_POST (e.g. block checkout).
 * @return bool
 */
function cfturnstile_get_verified( $key, $token = '' ) {
	$transient_key = cfturnstile_transient_key( $key, $token );
	if ( $transient_key ) {
		return (bool) get_transient( $transient_key );
	}
	return false;
}

/**
 * Clear a verification flag.
 *
 * @param string $key   Verification key.
 * @param string $token Optional. Explicit token value for contexts where the token is not in $_POST (e.g. block checkout).
 */
function cfturnstile_clear_verified( $key, $token = '' ) {
	$transient_key = cfturnstile_transient_key( $key, $token );
	if ( $transient_key ) {
		delete_transient( $transient_key );
	}
}
