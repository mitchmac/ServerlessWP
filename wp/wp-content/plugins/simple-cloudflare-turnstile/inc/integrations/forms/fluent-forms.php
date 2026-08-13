<?php
if (!defined('ABSPATH')) {
	exit;
}

if (get_option('cfturnstile_fluent')) {

	// Get turnstile field: Fluent Forms
	if(has_action('fluentform/render_item_submit_button')) {
		add_action('fluentform/render_item_submit_button', 'cfturnstile_field_fluent_form', 10, 2);
	} else {
		// If newer hook is not available, fallback to the deprecated one
		add_action('fluentform_render_item_submit_button', 'cfturnstile_field_fluent_form', 10, 2);
	}
	function cfturnstile_field_fluent_form($item, $form)
	{
		if (!cfturnstile_form_disable($form->id, 'cfturnstile_fluent_disable')) {
			$unique_id = wp_rand();
			cfturnstile_field_show('.fluentform .ff-btn-submit', 'turnstileFluentCallback', 'fluent-form-' . $form->id, '-fluent-' . $unique_id);
		}
	}

	// Fluent Forms Check
	add_action('fluentform/before_insert_submission', 'cfturnstile_fluent_check', 10, 3);
	function cfturnstile_fluent_check($insertData, $data, $form) {
		if (cfturnstile_whitelisted() || cfturnstile_form_disable($form->id, 'cfturnstile_fluent_disable')) {
            		return;
        	}
		
		$error_message = cfturnstile_failed_message();
		$token = isset($data['cf-turnstile-response']) ? sanitize_text_field($data['cf-turnstile-response']) : '';

		$_post_backup = null;
		if ( get_option('cfturnstile_failover') ) {
			$sync_keys = array(
				'cf-turnstile-response',
				'cfturnstile_failsafe',
				'g-recaptcha-response',
			);
			$_post_backup = array();
			foreach ($sync_keys as $sync_key) {
				$_post_backup[$sync_key] = array_key_exists($sync_key, $_POST) ? $_POST[$sync_key] : null;
				if (isset($data[$sync_key]) && !is_array($data[$sync_key])) {
					$_POST[$sync_key] = sanitize_text_field($data[$sync_key]);
				}
			}
		}

		$check = cfturnstile_check($token);
		if ( is_array($_post_backup) ) {
			foreach ($_post_backup as $sync_key => $old_val) {
				if ($old_val === null) {
					unset($_POST[$sync_key]);
				} else {
					$_POST[$sync_key] = $old_val;
				}
			}
		}

		$success = (is_array($check) && isset($check['success'])) ? $check['success'] : false;
		if ($success != true) {
			wp_die($error_message, 'simple-cloudflare-turnstile');
		}

	}
	
}