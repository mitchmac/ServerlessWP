<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FluentAuth (Fluent Security) compatibility.
 *
 * FluentAuth re-runs the WordPress authenticate chain via wp_signon() for logins it has
 * already verified through its own second step - email two-factor and magic login. Those
 * requests never carry a Turnstile token (the human check happened on the first-factor
 * login), so the global WordPress login check would reject them with "missing-input-response".
 * Skip the Turnstile login check for those specific, already-verified FluentAuth flows only.
 *
 * @see FluentAuth\App\Hooks\Handlers\TwoFaHandler::verify2FaEmailCode()
 * @see FluentAuth\App\Hooks\Handlers\MagicLoginHandler
 */
add_filter( 'cfturnstile_wp_login_checks', 'cfturnstile_fluentauth_skip_wp_login_check', 10, 1 );
function cfturnstile_fluentauth_skip_wp_login_check( $skip ) {

	if ( true === $skip ) {
		return $skip;
	}

	// Email two-factor: FluentAuth verifies the emailed code, then re-authenticates via
	// wp_signon() over admin-ajax. Only trust this action during an AJAX request, so it
	// cannot be appended to a wp-login.php credential POST to bypass the Turnstile check.
	if (
		wp_doing_ajax()
		&& isset( $_REQUEST['action'] )
		&& 'fluent_auth_2fa_email' === sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )
	) {
		return true;
	}

	// Magic login: the login-by-hash link is a GET request handled on 'init'. Requiring GET
	// means there is no credential POST to bypass (wp-login.php only processes logins on POST).
	if (
		isset( $_GET['fls_al'] )
		&& isset( $_SERVER['REQUEST_METHOD'] )
		&& 'GET' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
	) {
		return true;
	}

	return $skip;
}
