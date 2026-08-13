<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get turnstile field: EDD Login
function cfturnstile_field_edd_login() { cfturnstile_field_show('#edd_login_submit', 'turnstileEDDLoginCallback', 'edd-login', '-edd-login'); }

// Get turnstile field: EDD Register
function cfturnstile_field_edd_register() { cfturnstile_field_show('#edd_register_form .edd-submit', 'turnstileEDDRegisterCallback', 'edd-register', '-edd-register'); }

// Get turnstile field: EDD Checkout
function cfturnstile_field_edd_checkout() {
    $guest = esc_attr( get_option('cfturnstile_edd_guest_only') );
	if( !$guest || ( $guest && !is_user_logged_in() ) ) {
        cfturnstile_field_show('', '', 'edd-checkout', '-edd-checkout');
    }
}

// EDD Checkout Check
if(get_option('cfturnstile_edd_checkout')) {
	add_action('edd_purchase_form_before_submit', 'cfturnstile_field_edd_checkout', 10);
	add_action('edd_pre_process_purchase', 'cfturnstile_edd_checkout_check');
	function cfturnstile_edd_checkout_check() {
		// Check if already validated (cache-friendly, no PHP session)
		if( cfturnstile_get_verified( 'cfturnstile_edd_checkout_checked' ) ) {
			cfturnstile_clear_verified( 'cfturnstile_edd_checkout_checked' );
			return;
		}
		// Get guest only
		$guest = esc_attr( get_option('cfturnstile_edd_guest_only') );
		// Check
		if( !$guest || ( $guest && !is_user_logged_in() ) ) {
			if(isset( $_POST['edd-process-checkout-nonce'] ) && wp_verify_nonce( sanitize_text_field($_POST['edd-process-checkout-nonce']), 'edd-process-checkout' )) {
				$check = cfturnstile_check();
				$success = $check['success'];
				if($success != true) {
					edd_set_error( 'cfturnstile_error', cfturnstile_failed_message() );
				} else {
					cfturnstile_set_verified( 'cfturnstile_edd_checkout_checked' );
				}
			}
		}
	}
}

// EDD Login Check
if(get_option('cfturnstile_edd_login')) {
	if(empty(get_option('cfturnstile_tested')) || get_option('cfturnstile_tested') == 'yes') {
		add_action('edd_login_fields_after','cfturnstile_field_edd_login');
		add_action('authenticate', 'cfturnstile_edd_login_check', 21, 1);
		function cfturnstile_edd_login_check($user){
			if(isset($_POST['edd_login_nonce']) && !edd_is_checkout()) {
				$check = cfturnstile_check();
				$success = $check['success'];
				if($success != true) {
					wp_die( '<p><strong>' . esc_html__( 'ERROR:', 'simple-cloudflare-turnstile' ) . '</strong> ' . cfturnstile_failed_message() . '</p>', 'simple-cloudflare-turnstile', array( 'response'  => 403, 'back_link' => 1, ) );
				}
			}
			return $user;
		}
	}
}

// EDD Default Login Check
add_filter('cfturnstile_wp_login_checks','cfturnstile_edd_default_login_check');
function cfturnstile_edd_default_login_check() {
    // Return true if any EDD purchase process has occurred.
    return (
        function_exists( 'did_action' ) && (
            did_action( 'edd_purchase' ) ||
            did_action( 'edd_straight_to_gateway' ) ||
            did_action( 'edd_free_download_process' )
        )
    );
}

// EDD Register Check
if(get_option('cfturnstile_edd_register')) {
	add_action('edd_register_form_fields_before_submit','cfturnstile_field_edd_register');
	add_action('edd_process_register_form', 'cfturnstile_edd_register_check', 10);
	function cfturnstile_edd_register_check() {
		if(!edd_is_checkout()) {
			$check = cfturnstile_check();
			$success = $check['success'];
			if($success != true) {
				edd_set_error( 'cfturnstile_error', cfturnstile_failed_message() );
			}
		}
	}
}