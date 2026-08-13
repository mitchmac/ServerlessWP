<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if the current page is the Paid Memberships Pro login page.
function cfturnstile_is_pmp_login_page() {
	if ( ! function_exists( 'pmpro_getOption' ) ) {
		return false;
	}

	$page_id = absint( pmpro_getOption( 'login_page_id' ) );
	if ( ! $page_id ) {
		return false;
	}

	if ( is_page( $page_id ) || absint( get_queried_object_id() ) === $page_id ) {
		return true;
	}

	return absint( get_the_ID() ) === $page_id;
}

// Get turnstile field: PMP Login
function cfturnstile_field_pmp_login( $string, $args ) {
	$form_id = is_array( $args ) && isset( $args['form_id'] ) ? (string) $args['form_id'] : '';

	if ( 'loginform' !== $form_id || ! cfturnstile_is_pmp_login_page() ) {
		return $string;
	}

	ob_start();
	cfturnstile_field_show( '#wp-submit', 'turnstilePMPLoginCallback', 'pmp-login', '-pmp-login' );
	$cfturnstile = ob_get_clean();

	return $string . $cfturnstile;
}

// Get turnstile field: PMP Register
function cfturnstile_field_pmp_register() { cfturnstile_field_show('#pmp_register_form .pmp-submit', 'turnstilePMPRegisterCallback', 'pmp-register', '-pmp-register'); }

// Get turnstile field: PMP Checkout
function cfturnstile_field_pmp_checkout() {
    $guest = esc_attr( get_option('cfturnstile_pmp_guest_only') );
	if( !$guest || ( $guest && !is_user_logged_in() ) ) {
        cfturnstile_field_show('', '', 'pmp-checkout', '-pmp-checkout');
    }
}

// PMP Checkout Check
if(get_option('cfturnstile_pmp_checkout')) {
	add_action('pmpro_checkout_before_submit_button', 'cfturnstile_field_pmp_checkout', 10);
	add_filter('pmpro_registration_checks', 'cfturnstile_pmp_checkout_check');
	function cfturnstile_pmp_checkout_check() {
		// Get guest only
		$guest = esc_attr( get_option('cfturnstile_pmp_guest_only') );
		// Check
		if( !$guest || ( $guest && !is_user_logged_in() ) ) {
            $check = cfturnstile_check();
            $success = $check['success'];
            if($success != true) {
                pmpro_setMessage( cfturnstile_failed_message(), 'pmpro_error' );
                return false;
            }
		}
        return true;
	}
}

// PMP Login Check
if(get_option('cfturnstile_login')) {
	if(empty(get_option('cfturnstile_tested')) || get_option('cfturnstile_tested') == 'yes') {
		add_filter('login_form_middle', 'cfturnstile_field_pmp_login', 10, 2);
        add_action('cfturnstile_wp_login_failed', 'cfturnstile_pmp_error', 21, 1);
        function cfturnstile_pmp_error() {
            if(isset($_POST['pmpro_login_form_used'])) {
                wp_die( '<p><strong>' . esc_html__( 'ERROR:', 'simple-cloudflare-turnstile' ) . '</strong> ' . cfturnstile_failed_message() . '</p>', 'simple-cloudflare-turnstile', array( 'response'  => 403, 'back_link' => 1, ) );
            }
        }
	}
}

// PMP Remove Lost Password Check
remove_action('lostpassword_post','cfturnstile_wp_reset_check', 10, 1);