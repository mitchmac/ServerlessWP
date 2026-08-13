<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple Membership (WP Simple Membership) compatibility.
 *
 * @see https://wordpress.org/support/topic/breaks-is_user_logged_in-conditional-logic/
 */

/**
 * Track whether Simple Membership is currently mirroring a verified member login into the
 * WordPress user account via wp_signon().
 *
 * @param bool|null $set Pass true/false to set the flag, or null to read it.
 * @return bool
 */
function cfturnstile_swpm_in_signon( $set = null ) {
	static $in_signon = false;
	if ( null !== $set ) {
		$in_signon = (bool) $set;
	}
	return $in_signon;
}

/**
 * Skip the global WordPress login Turnstile check while Simple Membership mirrors a verified
 * member login into the corresponding WordPress user account.
 *
 * @param bool $skip
 * @return bool
 */
function cfturnstile_swpm_skip_wp_login_check( $skip ) {
	if ( true === $skip ) {
		return $skip;
	}
	return cfturnstile_swpm_in_signon() ? true : $skip;
}
add_filter( 'cfturnstile_wp_login_checks', 'cfturnstile_swpm_skip_wp_login_check', 10, 1 );

/**
 * Raise the flag just before Simple Membership's handler (priority 10) calls wp_signon().
 */
function cfturnstile_swpm_signon_start() {
	cfturnstile_swpm_in_signon( true );
}
add_action( 'swpm_after_login_authentication', 'cfturnstile_swpm_signon_start', 1 );

/**
 * Lower the flag immediately after, so it only ever covers that single wp_signon() call.
 */
function cfturnstile_swpm_signon_end() {
	cfturnstile_swpm_in_signon( false );
}
add_action( 'swpm_after_login_authentication', 'cfturnstile_swpm_signon_end', 100 );
