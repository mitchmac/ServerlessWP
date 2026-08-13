<?php
if (!defined('ABSPATH')) {
	exit;
}

// Create Topic
if (get_option('cfturnstile_bbpress_create')) {

	// Get field
	add_action('bbp_theme_before_topic_form_submit_wrapper', 'cfturnstile_field_bbpress_create');
	function cfturnstile_field_bbpress_create() {
		$guest_only = get_option('cfturnstile_bbpress_guest_only');
		$align = get_option('cfturnstile_bbpress_align');
		if (!$guest_only || ($guest_only && !is_user_logged_in())) {
			cfturnstile_field_show('#bbp_topic_submit', 'turnstileBBPressCreateCallback', 'bbpress-create', '-bb-create');
			if ($align == "right") {
				echo "<style>";
				echo "#bbpress-forums .cf-turnstile, .bbp-form .cf-turnstile, .bbp-topic-form .cf-turnstile, .bbp-reply-form .cf-turnstile, #cf-turnstile-bb-create { display: inline-block; float: right; margin-left: 14px; margin-bottom: 10px; }";
				echo "#bbpress-forums .cf-turnstile::after, .bbp-form .cf-turnstile::after, .bbp-topic-form .cf-turnstile::after, .bbp-reply-form .cf-turnstile::after, #cf-turnstile-bb-create::after { content: ''; display: block; clear: both; }";
				echo "</style>";
			}
		}
	}

	// Validate
	add_action('bbp_new_topic_pre_extras', 'cfturnstile_bbpress_register_check');
}

// Create Topic
if (get_option('cfturnstile_bbpress_reply')) {

	// Get field
	add_action('bbp_theme_before_reply_form_submit_wrapper', 'cfturnstile_field_bbpress_reply');
	function cfturnstile_field_bbpress_reply() {
		$guest_only = get_option('cfturnstile_bbpress_guest_only');
		$align = get_option('cfturnstile_bbpress_align');
		if (!$guest_only || ($guest_only && !is_user_logged_in())) {
			cfturnstile_field_show('#bbp_reply_submit', 'turnstileBBPressReplyCallback', 'bbpress-reply', '-bb-reply');
			if ($align == "right") {
				echo "<style>";
				echo "#bbpress-forums .cf-turnstile, .bbp-form .cf-turnstile, .bbp-topic-form .cf-turnstile, .bbp-reply-form .cf-turnstile, #cf-turnstile-bb-create { display: inline-block; float: right; margin-left: 14px; margin-bottom: 10px; }";
				echo "#bbpress-forums .cf-turnstile::after, .bbp-form .cf-turnstile::after, .bbp-topic-form .cf-turnstile::after, .bbp-reply-form .cf-turnstile::after, #cf-turnstile-bb-create::after { content: ''; display: block; clear: both; }";
				echo "</style>";
			}
		}
	}

	// Validate
	add_action('bbp_new_reply_pre_extras', 'cfturnstile_bbpress_register_check');
}

// Validate Function
function cfturnstile_bbpress_register_check() {
	if(!cfturnstile_whitelisted()) {
		$guest_only = get_option('cfturnstile_bbpress_guest_only');
		if (!$guest_only || ($guest_only && !is_user_logged_in())) {
			if ('POST' === $_SERVER['REQUEST_METHOD']) {
				$check = cfturnstile_check();
				$success = $check['success'];
				if ($success != true) {
					bbp_add_error('bbp_throw_error', cfturnstile_failed_message());
				}
			} else {
				bbp_add_error('bbp_throw_error', cfturnstile_failed_message());
			}
		}
	}
}
