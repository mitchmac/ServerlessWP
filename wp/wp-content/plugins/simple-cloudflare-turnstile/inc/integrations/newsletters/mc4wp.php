<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Create shortcode
add_shortcode('mc4wp-simple-turnstile', 'cfturnstile_mc4wp_shortcode');
function cfturnstile_mc4wp_shortcode() {
	ob_start();
	echo cfturnstile_field_show('.mc4wp-form-fields input[type=submit]', 'turnstileMC4WPCallback', 'mc4wp', '-mc4wp');
	$thecontent = ob_get_contents();
	ob_end_clean();
	wp_reset_postdata();
	$thecontent = trim(preg_replace('/\s+/', ' ', $thecontent));
	return $thecontent;
}

// MC4WP Register Check
add_action('mc4wp_form_errors', 'cfturnstile_mc4wp_register_check', 10, 2);
function cfturnstile_mc4wp_register_check( $errors, $form ) {

	if(!cfturnstile_whitelisted()) {

		$post = get_post($form->ID);

		$mc4wp_text = do_shortcode( '[mc4wp_form id="' . $form->ID . '"]' );
		$cfturnstile_key = sanitize_text_field( get_option( 'cfturnstile_key' ) );
		if ( !has_shortcode( $post->post_content, 'mc4wp-simple-turnstile') ) { return $errors; }

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$check = cfturnstile_check();
			$success = $check['success'];
			if($success != true) {
				$errors[] = 'cf_turnstile_error';
			}
		} else {
			$errors[] = 'cf_turnstile_error';
		}

	}

	return $errors;

}

// MC4WP Error Message
function cfturnstile_mc4wp_error_message($messages) {
  $messages['cf_turnstile_error'] = cfturnstile_failed_message();
  return $messages;
}
add_filter('mc4wp_form_messages', 'cfturnstile_mc4wp_error_message');
