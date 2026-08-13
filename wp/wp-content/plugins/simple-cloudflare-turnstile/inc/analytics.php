<?php
if (!defined('ABSPATH')) {
	exit;
}

/*
 * Add Turnstile check to a "cfturnstile_log" option
 */
add_action('cfturnstile_after_check', 'cfturnstile_log', 10, 2);
function cfturnstile_log($response, $results) {
	if(cfturnstile_is_checkbox_enabled(get_option('cfturnstile_log_enable'))) {
		// Get log
		$cfturnstile_log = get_option('cfturnstile_log');
		if(!is_array($cfturnstile_log)) {
			$cfturnstile_log = array();
		}
		$results = is_array($results) ? $results : array();
		// Get Values
		$error_code = isset($results['error_code']) ? cfturnstile_normalize_debug_log_error($results['error_code']) : '';
		// Success Yes or No
		if(is_object($response) && !empty($response->success)) {
			$success = true;
		} else {
			$success = false;
		}
		// Add to log
		$cfturnstile_log[] = array(
			'date' => current_time('mysql'),
			'success' => $success,
			'error' => $error_code,
			'ip' => cfturnstile_normalize_debug_log_value(cfturnstile_get_ip(), 100),
			'page' => cfturnstile_normalize_debug_log_value(isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', 250),
		);
		// Max 50
		if(count($cfturnstile_log) > 50) {
			$cfturnstile_log = array_slice($cfturnstile_log, -50);
		}
		// Update log
		update_option('cfturnstile_log', $cfturnstile_log, false);
	}
}

function cfturnstile_normalize_debug_log_value($value, $max_length = 250) {
	if ( ! is_scalar($value) ) {
		return '';
	}

	$value = sanitize_text_field((string) $value);
	if ( function_exists('mb_substr') ) {
		return mb_substr($value, 0, absint($max_length));
	}

	return substr($value, 0, absint($max_length));
}

function cfturnstile_normalize_debug_log_error($error_code) {
	if ( is_array($error_code) ) {
		$error_codes = array();
		foreach ( $error_code as $code ) {
			$code = cfturnstile_normalize_debug_log_value($code, 80);
			if ( '' !== $code ) {
				$error_codes[] = $code;
			}
			if ( count($error_codes) >= 5 ) {
				break;
			}
		}

		return implode(', ', $error_codes);
	}

	return cfturnstile_normalize_debug_log_value($error_code, 250);
}

add_action('cfturnstile_after_check', 'cfturnstile_track_advanced_analytics', 20, 3);
function cfturnstile_track_advanced_analytics($response, $results, $form_action = '') {
	if ( ! cfturnstile_is_checkbox_enabled(get_option('cfturnstile_advanced_analytics')) ) {
		return;
	}
	$results = is_array($results) ? $results : array();

	$analytics = get_option('cfturnstile_analytics');
	if ( ! is_array($analytics) ) {
		$analytics = array();
	}

	$analytics = wp_parse_args($analytics, array(
		'version' => 1,
		'started' => current_time('mysql'),
		'updated' => '',
		'total' => 0,
		'verified' => 0,
		'blocked' => 0,
		'retries' => 0,
		'forms' => array(),
		'errors' => array(),
	));
	if ( ! is_array($analytics['forms']) ) {
		$analytics['forms'] = array();
	}
	if ( ! is_array($analytics['errors']) ) {
		$analytics['errors'] = array();
	}
	foreach ( $analytics['forms'] as $form_key_existing => $form_existing ) {
		if ( ! is_array($form_existing) ) {
			unset($analytics['forms'][$form_key_existing]);
			continue;
		}

		$analytics['forms'][$form_key_existing] = wp_parse_args($form_existing, array(
			'label' => cfturnstile_normalize_analytics_label($form_key_existing),
			'total' => 0,
			'verified' => 0,
			'blocked' => 0,
			'retries' => 0,
			'last_checked' => '',
		));
	}
	foreach ( $analytics['errors'] as $error_code_existing => $error_count_existing ) {
		$normalized_error_code = cfturnstile_normalize_analytics_error_code($error_code_existing);
		if ( '' === $normalized_error_code ) {
			unset($analytics['errors'][$error_code_existing]);
			continue;
		}
		if ( $normalized_error_code !== $error_code_existing ) {
			unset($analytics['errors'][$error_code_existing]);
			$analytics['errors'][$normalized_error_code] = ( isset($analytics['errors'][$normalized_error_code]) ? absint($analytics['errors'][$normalized_error_code]) : 0 ) + absint($error_count_existing);
		} else {
			$analytics['errors'][$normalized_error_code] = absint($error_count_existing);
		}
	}
	$now = current_time('mysql');

	$success = ! empty($results['success']);
	$error_code = isset($results['error_code']) ? cfturnstile_normalize_analytics_error_code($results['error_code']) : '';
	$is_retry = ( 'timeout-or-duplicate' === $error_code );
	$form_label = cfturnstile_get_analytics_form_label($response, $form_action);
	$form_key = sanitize_key($form_label);
	if ( '' === $form_key ) {
		$form_key = 'unknown';
	}

	$analytics['updated'] = $now;
	$analytics['total'] = absint($analytics['total']) + 1;
	$analytics['verified'] = absint($analytics['verified']) + ( $success ? 1 : 0 );
	$analytics['blocked'] = absint($analytics['blocked']) + ( $success ? 0 : 1 );
	$analytics['retries'] = absint($analytics['retries']) + ( $is_retry ? 1 : 0 );

	if ( ! isset($analytics['forms'][$form_key]) || ! is_array($analytics['forms'][$form_key]) ) {
		$analytics['forms'][$form_key] = array();
	}
	$analytics['forms'][$form_key] = wp_parse_args($analytics['forms'][$form_key], array(
		'label' => $form_label,
		'total' => 0,
		'verified' => 0,
		'blocked' => 0,
		'retries' => 0,
		'last_checked' => '',
	));

	$analytics['forms'][$form_key]['label'] = $form_label;
	$analytics['forms'][$form_key]['last_checked'] = $now;
	$analytics['forms'][$form_key]['total'] = absint($analytics['forms'][$form_key]['total']) + 1;
	$analytics['forms'][$form_key]['verified'] = absint($analytics['forms'][$form_key]['verified']) + ( $success ? 1 : 0 );
	$analytics['forms'][$form_key]['blocked'] = absint($analytics['forms'][$form_key]['blocked']) + ( $success ? 0 : 1 );
	$analytics['forms'][$form_key]['retries'] = absint($analytics['forms'][$form_key]['retries']) + ( $is_retry ? 1 : 0 );

	if ( ! $success && $error_code ) {
		$analytics['errors'][$error_code] = isset($analytics['errors'][$error_code]) ? absint($analytics['errors'][$error_code]) + 1 : 1;
	}
	if ( count($analytics['errors']) > 20 ) {
		arsort($analytics['errors']);
		$analytics['errors'] = array_slice($analytics['errors'], 0, 20, true);
	}

	if ( count($analytics['forms']) > 40 ) {
		uasort($analytics['forms'], 'cfturnstile_sort_analytics_forms');
		$analytics['forms'] = array_slice($analytics['forms'], 0, 40, true);
	}

	update_option('cfturnstile_analytics', $analytics, false);
}

function cfturnstile_get_analytics_form_label($response, $form_action = '') {
	$action = '';
	if ( is_object($response) && isset($response->action) && is_scalar($response->action) ) {
		$action = cfturnstile_normalize_analytics_label($response->action);
	}

	// Cloudflare only returns an action when a valid token was generated by the widget.
	// For tokenless/blocked submissions (e.g. comment spam) fall back to the server-side
	// form context so the submission is attributed to the correct form.
	if ( '' === $action && is_scalar($form_action) ) {
		$action = cfturnstile_normalize_analytics_label($form_action);
	}

	if ( '' === $action ) {
		return __('Unknown form', 'simple-cloudflare-turnstile');
	}

	$randomized_prefixes = array(
		'woocommerce-login',
		'woocommerce-register',
		'woocommerce-reset',
		'woocommerce-account',
		'mailpoet',
	);

	foreach ( $randomized_prefixes as $prefix ) {
		if ( $action === $prefix || strpos($action, $prefix . '-') === 0 ) {
			return $prefix;
		}
	}

	return $action;
}

function cfturnstile_normalize_analytics_label($label) {
	$label = sanitize_text_field((string) $label);
	$label = trim(preg_replace('/\s+/', ' ', $label));
	if ( '' === $label ) {
		return '';
	}

	if ( function_exists('mb_substr') ) {
		return mb_substr($label, 0, 80);
	}

	return substr($label, 0, 80);
}

function cfturnstile_normalize_analytics_error_code($error_code) {
	if ( is_array($error_code) ) {
		$error_code = reset($error_code);
	}
	if ( ! is_scalar($error_code) ) {
		return '';
	}

	$error_code = sanitize_key((string) $error_code);

	return substr($error_code, 0, 80);
}

function cfturnstile_sort_analytics_forms($a, $b) {
	$a_total = isset($a['total']) ? absint($a['total']) : 0;
	$b_total = isset($b['total']) ? absint($b['total']) : 0;

	if ( $a_total === $b_total ) {
		return 0;
	}

	return ( $a_total < $b_total ) ? 1 : -1;
}
