<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Display the turnstile field on the login form.
 */
function cfturnstile_field_login() {
	cfturnstile_clear_verified( 'cfturnstile_login_checked' );
	if(get_option('cfturnstile_login_only', 0)) {
		$login_url_path = wp_parse_url(wp_login_url(), PHP_URL_PATH);
		$current_url_path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		if ($current_url_path !== $login_url_path) {
			return;
		}
	}
	cfturnstile_field_show('#wp-submit', 'turnstileWPCallback', 'wordpress-login', '-' . wp_rand());
}

/**
 * Function to display the turnstile field on the registration form.
 */
function cfturnstile_field_register() {
	cfturnstile_field_show('#wp-submit', 'turnstileWPCallback', 'wordpress-register', '-' . wp_rand());
}

/**
 * Function to display the turnstile field on the password reset form.
 */
function cfturnstile_field_reset() {
	cfturnstile_field_show('#wp-submit', 'turnstileWPCallback', 'wordpress-reset', '-' . wp_rand());
}

/*
 * WP Login Check
 */
if(get_option('cfturnstile_login')) {
	add_action('login_form','cfturnstile_field_login');
	add_filter('authenticate', 'cfturnstile_wp_login_check', 21, 1);
	function cfturnstile_wp_login_check($user) {

		// Check skip
		if(defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST) { return $user; } // Skip XMLRPC
		if(defined( 'REST_REQUEST' ) && REST_REQUEST) { return $user; } // Skip REST API
		if(isset($_POST['edd_login_nonce']) && wp_verify_nonce( sanitize_text_field($_POST['edd_login_nonce']), 'edd-login-nonce')) { return $user; } // Skip EDD
		if(is_wp_error($user) && isset($user->errors['empty_username']) && isset($user->errors['empty_password']) ) {return $user; } // Skip Errors

		// Skip if not on login page
		if(get_option('cfturnstile_login_only', 0)) {
			$login_url_path = wp_parse_url(wp_login_url(), PHP_URL_PATH);
			$current_url_path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
			if ($current_url_path !== $login_url_path) {
				return $user;
			}
		}

		// Custom skip filter (integrations can return true to bypass global WP login check)
		if (apply_filters('cfturnstile_wp_login_checks', false) === true) {
			return $user;
		}

		// Bind the verification flag to the user being authenticated so a token validated for one account can't be replayed against another.
		$verified_key = isset($user->ID) ? 'cfturnstile_login_checked_' . $user->ID : 'cfturnstile_login_checked';

		// Check if already validated
		if(isset($user->ID) && cfturnstile_get_verified( $verified_key ) ) {
			return $user;
		}

		// Check Turnstile
		$check = cfturnstile_check();
		$success = $check['success'];
		if($success != true) {
			$user = new WP_Error( 'cfturnstile_error', cfturnstile_failed_message() );
			do_action('cfturnstile_wp_login_failed');
		} else {
			if (isset($user->ID)) {
				cfturnstile_set_verified( 'cfturnstile_login_checked_' . $user->ID, '', 300 );
			}
		}
		
		return $user;
		
	}
	/* Hook into wp_login_form() to add the Turnstile field */
	function cfturnstile_wp_login_form_field( $content = '' ) {
		if (
			false !== strpos( $content, 'cf-turnstile' )
			|| false !== strpos( $content, 'cfturnstile_failsafe' )
			|| false !== strpos( $content, 'g-recaptcha' )
		) {
			return $content;
		}

		ob_start();
		cfturnstile_field_show( '#wp-submit', 'turnstileWPCallback', 'wordpress-login', '-' . wp_rand() );
		$field = ob_get_clean();
		return $content . $field;
	}
	add_filter( 'login_form_middle', 'cfturnstile_wp_login_form_field', 20, 1 );

	/*
	 * Refresh the Turnstile token for a retry after a FAILED login attempt (the token
	 * is single-use and has already been consumed). Triggered only when a login error
	 * is shown - never on a submit click - so it does not disturb a successful first
	 * factor that is waiting on a 2FA prompt (e.g. Wordfence / FluentAuth), where the
	 * consumed token must survive to the final POST.
	 */
	function cfturnstile_login_rerender_script() {
		if ( ! wp_script_is( 'cfturnstile', 'enqueued' ) ) {
			return;
		}
		$script = <<<'JS'
document.addEventListener("DOMContentLoaded", function () {
	var SELECTOR = "#login_error, .wfls-login-message, .error.text-danger";
	var lastReset = 0, hasReset = false;
	function twoFactorPromptOpen() {
		// Wordfence 2FA overlay or FluentAuth 2FA form: first factor succeeded, keep the token.
		return !!document.querySelector("#wfls-prompt-overlay, #wfls-token, #fls_2fa_form, .fls_2fs");
	}
	function resetWidget() {
		if (typeof turnstile === "undefined" || twoFactorPromptOpen()) return;
		var w = document.querySelector("#loginform .cf-turnstile");
		if (!w) return;
		// A single failure can produce several mutations. Resetting once per second
		// avoids throwing away a fresh challenge we just asked Turnstile for.
		var now = new Date().getTime();
		if (hasReset && now - lastReset < 1000) return;
		hasReset = true;
		lastReset = now;
		try { turnstile.reset(w); } catch (e) {}
	}
	function isLoginError(node) {
		if (!node) return false;
		// An error element was inserted, or one was inserted inside a wrapper.
		if (node.nodeType === 1) {
			return node.matches(SELECTOR) || !!node.querySelector(SELECTOR);
		}
		// Text was written into an error container that was already in the DOM. Login
		// forms that keep a permanent (empty) error node and only swap its message
		// never add an element, so the check above alone would miss the failure.
		if (node.nodeType === 3 && node.nodeValue && node.nodeValue.trim() && node.parentNode && node.parentNode.closest) {
			return !!node.parentNode.closest(SELECTOR);
		}
		return false;
	}
	// Full-reload failures (e.g. plain wp-login.php wrong password): error is already in the DOM.
	if (document.getElementById("login_error")) {
		resetWidget();
	}
	// AJAX failures (e.g. Wordfence inline wrong password): error injected without a reload.
	if (window.MutationObserver) {
		new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var m = mutations[i];
				if (m.type === "characterData") {
					if (isLoginError(m.target)) { resetWidget(); return; }
					continue;
				}
				for (var j = 0; j < m.addedNodes.length; j++) {
					if (isLoginError(m.addedNodes[j])) { resetWidget(); return; }
				}
			}
		}).observe(document.body, { childList: true, subtree: true, characterData: true });
	}
});
JS;
		wp_add_inline_script( 'cfturnstile', $script );
	}
	add_action( 'login_enqueue_scripts', 'cfturnstile_login_rerender_script', 20 );

}

/* 
 * WP Register Check
 */
if(get_option('cfturnstile_register')) {
	add_action('register_form','cfturnstile_field_register');
	add_action('registration_errors', 'cfturnstile_wp_register_check', 10, 3);
	function cfturnstile_wp_register_check($errors, $sanitized_user_login, $user_email) {

		// Check skip
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) { return $errors; } // Skip XMLRPC
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return $errors; } // Skip REST API
		if ( isset($_POST['woocommerce-register-nonce']) && wp_verify_nonce( sanitize_text_field($_POST['woocommerce-register-nonce']), 'woocommerce-register' ) ) { return $errors; } // Skip Woo
		if ( isset($_POST['edd_register_nonce']) && wp_verify_nonce( sanitize_text_field($_POST['edd_register_nonce']), 'edd-register-nonce' ) ) { return $errors; } // Skip EDD

		// Skip if not on login page
		if(get_option('cfturnstile_register_only', 0)) {
			$login_url_path = wp_parse_url(wp_login_url(), PHP_URL_PATH);
			$current_url_path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
			if ($current_url_path !== $login_url_path) {
				return $errors;
			}
		}

		// Custom skip filter
		if (apply_filters('cfturnstile_wp_register_checks', false) === true) {
			return $errors;
		}

		if(is_user_logged_in() && current_user_can('manage_options')) { return $errors; } // Skip Logged In Admins

		$check = cfturnstile_check();
		$success = $check['success'];
		if($success != true) {
			$errors->add( 'cfturnstile_error', sprintf('<strong>%s</strong>: %s',__( 'ERROR', 'simple-cloudflare-turnstile' ), cfturnstile_failed_message() ) );
		}
		return $errors;
	}
}

/*
 * WP Password Reset Check
 */
if(get_option('cfturnstile_reset')) {
  if(!is_admin()) {
  	add_action('lostpassword_form','cfturnstile_field_reset');
  	add_action('lostpassword_post','cfturnstile_wp_reset_check', 10, 1);
  	function cfturnstile_wp_reset_check($validation_errors) {

		if(isset($_POST['woocommerce-lost-password-nonce'])) { return; } // Skip Woo

		if(stripos($_SERVER["SCRIPT_NAME"], strrchr(wp_login_url(), '/')) !== false) { // Check if WP login page
  			$check = cfturnstile_check();
  			$success = $check['success'];
  			if($success != true) {
  				$validation_errors->add( 'cfturnstile_error', cfturnstile_failed_message() );
  			}
  		}
  	}
  }
}

/*
 * WP Comment Check
 */
if(get_option('cfturnstile_comment') && !cft_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php')) {
  if( !is_admin() || wp_doing_ajax() ) {
	add_action("comment_form_after", "cfturnstile_comment_form_after");
	function cfturnstile_comment_form_after() {
		if ( wp_doing_ajax() ) {
			wp_print_scripts('cfturnstile');
			wp_print_styles('cfturnstile-css');
		}
	}
  	add_action('comment_form_submit_button','cfturnstile_field_comment', 100, 2);
  	// Create and display the turnstile field for comments.
  	function cfturnstile_field_comment( $submit_button, $args ) {
		if(!cfturnstile_whitelisted()) {
			do_action("cfturnstile_enqueue_scripts");
			$unique_id = wp_rand();
			$key = esc_attr( get_option('cfturnstile_key') );
			$theme = esc_attr( get_option('cfturnstile_theme') );
			$language = esc_attr(get_option('cfturnstile_language'));
			$appearance = esc_attr(get_option('cfturnstile_appearance', 'always'));
			$cfturnstile_size = esc_attr(get_option('cfturnstile_size'), 'normal');
			if(!$language) { $language = 'auto'; }
			$submit_before = '';
			$submit_after = '';
			$callback = '';
			if(get_option('cfturnstile_disable_button')) { $callback = 'turnstileCommentCallback'; }
			if ( get_option('cfturnstile_widget_label_enable', 0) ) {
				$label_text = get_option('cfturnstile_widget_label_text');
				$label_text = is_string($label_text) ? trim($label_text) : '';
				if ($label_text === '') {
					$label_text = __('Let us know you are human:', 'simple-cloudflare-turnstile');
				} else {
					$label_text = wp_strip_all_tags($label_text);
				}
				$submit_before .= '<p class="cfturnstile-widget-label' . ( $appearance === 'interaction-only' ? ' cfturnstile-widget-label-interaction' : '' ) . '" style="font-size: 14px; margin: 0 0 6px 0;' . ( $appearance === 'interaction-only' ? ' display: none;' : '' ) . '"><small>' . esc_html($label_text) . '</small></p>';
			}
			$submit_before .= '<span id="cf-turnstile-c-'.$unique_id.'" class="cf-turnstile cf-turnstile-comments" data-action="wordpress-comment" data-callback="'.$callback.'" data-sitekey="'.sanitize_text_field($key).'" data-theme="'.sanitize_text_field($theme).'" data-language="'.sanitize_text_field($language).'" data-appearance="'.sanitize_text_field($appearance).'" data-size="'.sanitize_text_field($cfturnstile_size).'" data-retry="auto" data-retry-interval="1000"></span>';
			$submit_before .= '<br class="cf-turnstile-br cf-turnstile-br-comments' . ( $appearance === 'interaction-only' ? ' cfturnstile-widget-spacer-interaction' : '' ) . '"' . ( $appearance === 'interaction-only' ? ' style="display: none;"' : '' ) . '>';
			if(get_option('cfturnstile_disable_button')) {
				$submit_before .= '<span class="cf-turnstile-comment" style="pointer-events: none; opacity: 0.5;">';
				$submit_after .= "</span>";
			}
			// force_render() echoes its script when there is no footer to enqueue into (an AJAX
			// comment form), so capture it and keep it after the widget markup.
			ob_start();
			cfturnstile_force_render("-c-" . $unique_id);
			$submit_after .= ob_get_clean();
			// Script to render turnstile when clicking reply
			$script = '<script type="text/javascript">document.addEventListener("DOMContentLoaded", function() { document.body.addEventListener("click", function(event) { if (event.target.matches(".comment-reply-link, #cancel-comment-reply-link")) { turnstile.reset(".comment-form .cf-turnstile"); } }); });</script>';
			// If ajax comments are enabled, re-render the turnstile after the comment is submitted.
			// Guarded: turnstile.remove() throws on an element it never rendered - the normal state
			// for a comment form that arrived over AJAX - which would skip the render() after it.
			if(cft_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php') || cft_is_plugin_active('wp-ajaxify-comments/wp-ajaxify-comments.php') || get_option('cfturnstile_ajax_comments')) {
				$script .= '<script type="text/javascript">jQuery(document).ajaxComplete(function() { setTimeout(function() { if (typeof turnstile === "undefined") { return; } var el = document.getElementById("cf-turnstile-c-'.$unique_id.'"); if (!el) { return; } try { turnstile.remove(el); } catch (e) {} try { turnstile.render(el, (typeof window.cfturnstileOpts === "function" ? window.cfturnstileOpts(el) : {})); } catch (e) {} }, 1000); });</script>';
			}
			// Return button
			return $submit_before . $submit_button . $submit_after . $script;
		} else {
			return $submit_button;
		}
  	}
  	// Comment Validation
  	add_action('pre_comment_on_post','cfturnstile_wp_comment_check', 10, 1);
  	function cfturnstile_wp_comment_check($commentdata) {
		if(is_admin()) { return $commentdata; }
		if(!empty($_POST)) {
			$check = cfturnstile_check('', 'wordpress-comment');
			$success = $check['success'];
			if($success != true) {
				wp_die( '<p><strong>' . esc_html__( 'ERROR:', 'simple-cloudflare-turnstile' ) . '</strong> ' . cfturnstile_failed_message() . '</p>', 'simple-cloudflare-turnstile', array( 'response'  => 403, 'back_link' => 1, ) );
			}
			return $commentdata;
      	}
  	}
  }
}