<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(get_option('cfturnstile_bp_register')) {

	// Get turnstile field: BuddyPress
	add_action('bp_before_registration_submit_buttons','cfturnstile_field_bp_register');
	function cfturnstile_field_bp_register() {
    	cfturnstile_field_show('#buddypress #signup-form .submit', 'turnstileBPCallback', 'buddypress-register', '-bp-register');
  	}

	// BuddyPress Register Check
	add_action('bp_signup_validate', 'cfturnstile_bp_register_check', 10, 1);
	function cfturnstile_bp_register_check(){
		if(!cfturnstile_whitelisted()) {
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				$check = cfturnstile_check();
				$success = $check['success'];
				if($success != true) {
					wp_die( '<p><strong>' . esc_html__( 'ERROR:', 'simple-cloudflare-turnstile' ) . '</strong> ' . cfturnstile_failed_message() . '</p>', 'simple-cloudflare-turnstile', array( 'response'  => 403, 'back_link' => 1, ) );
				}
			} else {
				wp_die( '<p><strong>' . esc_html__( 'ERROR:', 'simple-cloudflare-turnstile' ) . '</strong> ' . cfturnstile_failed_message() . '</p>', 'simple-cloudflare-turnstile', array( 'response'  => 403, 'back_link' => 1, ) );
			}
		}
	}

}