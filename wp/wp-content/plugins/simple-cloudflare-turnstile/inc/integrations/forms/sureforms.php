<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( get_option( 'cfturnstile_sureforms' ) ) {

	// Get Turnstile Field: SureForms
	add_action( 'srfm_before_submit_button', 'cfturnstile_field_sureforms', 10, 1 );
	function cfturnstile_field_sureforms( $id ) {
		if ( cfturnstile_form_disable( $id, 'cfturnstile_sureforms_disable' ) ) {
			return;
		}

		$unique_id = wp_rand();
		cfturnstile_field_show( '.srfm-submit-container .srfm-submit-button', 'turnstilesureformsCallback', 'sureforms-' . $id, '-sureforms-' . $unique_id );

		// Output the captcha error element expected by SureForms' frontend JS.
		$error_msg = cfturnstile_failed_message();
		printf(
			'<div class="srfm-validation-error" id="captcha-error" style="display: none; margin-bottom: 20px;">%s</div>',
			esc_html( $error_msg )
		);

		// Patch turnstile.getResponse() to return '' instead of undefined.
		wp_add_inline_script( 'cfturnstile', '(function(){if(window._cftSureformsPatched)return;window._cftSureformsPatched=true;var a=0,i=setInterval(function(){if(++a>50){clearInterval(i);return}if(typeof turnstile==="undefined"||!turnstile.getResponse)return;clearInterval(i);var o=turnstile.getResponse;turnstile.getResponse=function(){return o.apply(turnstile,arguments)||""};},100);})();' );
	}

	// SureForms Check
	add_filter( 'srfm_additional_restriction_check', 'cfturnstile_sureforms_check', 10, 3 );
	function cfturnstile_sureforms_check( $restricted, $form_id, $form_data ) {
		global $cfturnstile_sureforms_failed;
		$cfturnstile_sureforms_failed = false;

		if ( cfturnstile_whitelisted() || cfturnstile_form_disable( $form_id, 'cfturnstile_sureforms_disable' ) ) {
			return $restricted;
		}

		// Disable SureForms' built-in captcha so it doesn't consume the single-use token.
		add_filter( 'get_post_metadata', 'cfturnstile_sureforms_disable_builtin_captcha', 10, 4 );

		$token = isset( $form_data['cf-turnstile-response'] ) ? sanitize_text_field( $form_data['cf-turnstile-response'] ) : '';

		// Sync REST API form data into $_POST for cfturnstile_check() failover logic.
		foreach ( array( 'cf-turnstile-response', 'cfturnstile_failsafe', 'g-recaptcha-response' ) as $key ) {
			if ( isset( $form_data[ $key ] ) && ! is_array( $form_data[ $key ] ) ) {
				$_POST[ $key ] = sanitize_text_field( $form_data[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		$check = cfturnstile_check( $token );

		// Clean up.
		unset( $_POST['cf-turnstile-response'], $_POST['cfturnstile_failsafe'], $_POST['g-recaptcha-response'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$success = ( is_array( $check ) && isset( $check['success'] ) ) ? $check['success'] : false;

		if ( true !== $success ) {
			$cfturnstile_sureforms_failed = true;
			return true;
		}

		return $restricted;
	}

	// SureForms Error Message
	add_filter( 'srfm_additional_restriction_message', 'cfturnstile_sureforms_error_message', 10, 3 );
	function cfturnstile_sureforms_error_message( $message, $form_id, $form_data ) {
		global $cfturnstile_sureforms_failed;

		if ( ! empty( $cfturnstile_sureforms_failed ) ) {
			return cfturnstile_failed_message();
		}

		return $message;
	}

	// Disable SureForms' Built-in Captcha During Submission
	function cfturnstile_sureforms_disable_builtin_captcha( $value, $object_id, $meta_key, $single ) {
		if ( '_srfm_captcha_security_type' === $meta_key ) {
			return $single ? 'none' : array( 'none' );
		}
		return $value;
	}
}
