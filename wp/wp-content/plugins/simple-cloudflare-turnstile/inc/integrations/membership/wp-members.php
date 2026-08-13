<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register Check
if(get_option('cfturnstile_register')) {
	add_filter( 'wpmem_pre_validate_form', 'cfturnstile_wpmem_register_check', 10, 2 );
	function cfturnstile_wpmem_register_check($fields, $tag) {
		$check = cfturnstile_check();
		$success = $check['success'];
		if($success != true) {
            wp_die( '<p><strong>' . esc_html__( 'ERROR:', 'simple-cloudflare-turnstile' ) . '</strong> ' . cfturnstile_failed_message() . '</p>', 'simple-cloudflare-turnstile', array( 'response'  => 403, 'back_link' => 1, ) );
		}
        return $fields;
	}
}