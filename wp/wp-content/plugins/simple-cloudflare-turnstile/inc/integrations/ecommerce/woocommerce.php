<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get turnstile field: Woo Login
function cfturnstile_field_woo_login() {
	if(empty(get_option('cfturnstile_tested')) || get_option('cfturnstile_tested') == 'yes') {
		$unique_id = wp_rand();
		cfturnstile_field_show('.woocommerce-form-login__submit', 'turnstileWooLoginCallback', 'woocommerce-login-' . $unique_id, '-woo-login-' . $unique_id, 'sct-woocommerce-login');
	}
}

// Get turnstile field: Woo Register
function cfturnstile_field_woo_register() {
	$unique_id = wp_rand();
	cfturnstile_field_show('.woocommerce-form-register__submit', 'turnstileWooRegisterCallback', 'woocommerce-register-' . $unique_id, '-woo-register-' . $unique_id, 'sct-woocommerce-register');
}

// Get turnstile field: Woo Reset
function cfturnstile_field_woo_reset() {
	$unique_id = wp_rand();
	cfturnstile_field_show('.woocommerce-ResetPassword .button', 'turnstileWooResetCallback', 'woocommerce-reset-' . $unique_id, '-woo-reset-' . $unique_id, 'sct-woocommerce-reset');
}

// Get turnstile field: Woo Account Details
function cfturnstile_field_woo_account() {
	$unique_id = wp_rand();
	cfturnstile_field_show('.woocommerce-EditAccountForm button[name=save_account_details], .woocommerce-EditAccountForm input[name=save_account_details]', 'turnstileWooAccountCallback', 'woocommerce-account-' . $unique_id, '-woo-account-' . $unique_id, 'sct-woocommerce-account');
}

// Get turnstile field: Woo Checkout
function cfturnstile_field_checkout() {
	if(is_wc_endpoint_url('order-received')) {
		return;
	}

	static $already_rendered_checkout = false;
	if ( $already_rendered_checkout ) {
		return;
	}
	$already_rendered_checkout = true;

	$guest_only = esc_attr( get_option('cfturnstile_guest_only') );
	if( !$guest_only || ($guest_only && !is_user_logged_in()) ) {
		if(get_option('cfturnstile_woo_checkout_pos') == "afterpay") {
			echo "<br/>";
		}
		cfturnstile_field_show('', '', 'woocommerce-checkout', '-woo-checkout');
		?>
		<?php
	}
}

// Render after checkout block
function cfturnstile_render_post_block($block_content) {
	if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) {
		return $block_content;
	}
	ob_start();
	cfturnstile_field_checkout();
	$block_content = ob_get_contents();
	ob_end_clean();
	return $block_content;
}

// Render before checkout block
function cfturnstile_render_pre_block($block_content) {
	if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) {
		return $block_content;
	}
	$already_ran_turnstile_block = false;
	if ( ! $already_ran_turnstile_block ) {
		$already_ran_turnstile_block = true;
	} else {
		return $block_content;
	}
	ob_start();
	cfturnstile_field_checkout();
	echo $block_content;
	$block_content = ob_get_contents();
	ob_end_clean();
	return $block_content;
}

/**
 * Check if the current request is a non-checkout WooCommerce AJAX call.
 *
 * @return bool True if the request is a wc-ajax call that is NOT the actual checkout.
 */
function cfturnstile_is_non_checkout_ajax() {
	$wc_ajax = isset( $_GET['wc-ajax'] ) ? sanitize_text_field( $_GET['wc-ajax'] ) : '';
	if ( $wc_ajax && $wc_ajax !== 'checkout' ) {
		return true;
	}
	return false;
}

/**
 * Check whether a payment gateway has halted this checkout request for its own two-phase flow.
 *
 * Some gateways run a two-stage checkout: they let WooCommerce validate the order, abort the
 * request with a marker error notice, perform tokenisation and 3DS in the browser, then resubmit
 * the very same form. Both stages post to wc-ajax=checkout, so they are not caught by
 * cfturnstile_is_non_checkout_ajax(). The Turnstile token is unchanged on the resubmission, and
 * Turnstile tokens are single-use, so the verification flag has to survive the first stage.
 *
 * @return bool True if the current request was halted for a gateway resubmission.
 */
function cfturnstile_woo_checkout_deferred_by_gateway() {
	// Marker notices added by gateways that abort checkout and resubmit the same form.
	$markers = apply_filters( 'cfturnstile_woo_deferred_checkout_markers', array(
		'globalpayments_gpapi_checkout_validated', // GlobalPayments GPAPI, 3DS enabled.
	) );

	if ( ! is_array( $markers ) || empty( $markers ) || ! function_exists( 'wc_get_notices' ) || ! function_exists( 'WC' ) || ! WC()->session ) {
		return false;
	}

	$notices = wc_get_notices( 'error' );
	if ( empty( $notices ) ) {
		return false;
	}

	foreach ( $notices as $notice ) {
		// WooCommerce 3.9+ stores notices as arrays, older versions as plain strings.
		$message = ( is_array( $notice ) && isset( $notice['notice'] ) ) ? $notice['notice'] : $notice;
		if ( ! is_string( $message ) ) {
			continue;
		}
		foreach ( $markers as $marker ) {
			if ( $marker && false !== strpos( $message, $marker ) ) {
				return true;
			}
		}
	}

	return false;
}

// Woo Checkout Check
if(get_option('cfturnstile_woo_checkout')) {
	// WooCommerce Checkout
	// CheckoutWC: Only hook when CheckoutWC templates are enabled
	if(function_exists( 'cfw_templates_disabled' ) && ! cfw_templates_disabled()) {
		add_action('cfw_checkout_before_payment_method_tab_nav', 'cfturnstile_field_checkout', 10);
	} elseif(empty(get_option('cfturnstile_woo_checkout_pos')) || get_option('cfturnstile_woo_checkout_pos') == "beforepay") {
		add_action('woocommerce_review_order_before_payment', 'cfturnstile_field_checkout', 10);
		add_filter('render_block_woocommerce/checkout-payment-block', 'cfturnstile_render_pre_block', 999, 1); // Before Payment block.
	} elseif(get_option('cfturnstile_woo_checkout_pos') == "afterpay") {
		add_action('woocommerce_review_order_after_payment', 'cfturnstile_field_checkout', 10);
		add_filter('render_block_woocommerce/checkout-payment-block', 'cfturnstile_render_post_block', 999, 1); // After Payment block.
	} elseif(get_option('cfturnstile_woo_checkout_pos') == "beforebilling") {
		add_action('woocommerce_before_checkout_billing_form', 'cfturnstile_field_checkout', 10);
		add_filter('render_block_woocommerce/checkout-contact-information-block', 'cfturnstile_render_pre_block', 999, 1); // Before Contact Information block.
	} elseif(get_option('cfturnstile_woo_checkout_pos') == "afterbilling") {
		add_action('woocommerce_after_checkout_billing_form', 'cfturnstile_field_checkout', 10);
		add_filter('render_block_woocommerce/checkout-shipping-methods-block', 'cfturnstile_render_pre_block', 999, 1); // Before Shipping Methods block.
	} elseif(get_option('cfturnstile_woo_checkout_pos') == "beforesubmit") {
		add_action('woocommerce_review_order_before_submit', 'cfturnstile_field_checkout', 10);
		add_filter('render_block_woocommerce/checkout-actions-block', 'cfturnstile_render_pre_block', 999, 1); // Before Actions block, not sure if this option is still supported.
	}

	// Check Turnstile
	add_action('woocommerce_checkout_process', 'cfturnstile_woo_checkout_check');
	add_action('woocommerce_after_checkout_validation', 'cfturnstile_woo_checkout_check');
	function cfturnstile_woo_checkout_check() {

		// Prevent duplicate execution within a single request.
		static $cfturnstile_wc_checkout_ran = false;
		if ( $cfturnstile_wc_checkout_ran ) {
			return;
		}

		// Skip non-checkout wc-ajax requests (e.g. payment gateway pre-validation) to preserve the token.
		if ( cfturnstile_is_non_checkout_ajax() ) {
			return;
		}

		// Skip if Turnstile disabled for payment method
		$skip = 0;
		if ( isset( $_POST['payment_method'] ) ) {
			$chosen_payment_method = sanitize_text_field( $_POST['payment_method'] );
			// Retrieve the selected payment methods from the cfturnstile_selected_payment_methods option
			$selected_payment_methods = get_option('cfturnstile_selected_payment_methods', array());
			if(is_array($selected_payment_methods)) {
				// Check if the chosen payment method is in the selected payment methods array
				if ( in_array( $chosen_payment_method, $selected_payment_methods, true ) ) {
					$skip = 1;
				}
			}
		}

		// Check if guest only enabled
		$guest = esc_attr( get_option('cfturnstile_guest_only') );
		// Check — always require a fresh Turnstile token (tokens are single-use).
		if( !$skip && (!$guest || ( $guest && !is_user_logged_in() )) ) {
			// If this token already passed verification, skip re-check.
			if ( cfturnstile_get_verified( 'cfturnstile_checkout_checked' ) ) {
				$cfturnstile_wc_checkout_ran = true;
				return;
			}
			$check = cfturnstile_check();
			$success = $check['success'];
			if($success != true) {
				wc_add_notice( cfturnstile_failed_message(), 'error');
			} else {
				cfturnstile_set_verified( 'cfturnstile_checkout_checked', '', 120 );
			}
			$cfturnstile_wc_checkout_ran = true;
		}
	}
	add_action('woocommerce_store_api_checkout_update_order_from_request', 'cfturnstile_woo_checkout_block_check', 10, 2);
	function cfturnstile_woo_checkout_block_check($order, $request) {
		// Prevent duplicate execution within a single request.
		static $cfturnstile_wc_block_checkout_ran = false;
		if ( $cfturnstile_wc_block_checkout_ran ) {
			return;
		}

		// Skip non-checkout wc-ajax requests (e.g. payment gateway pre-validation) to preserve the token.
		if ( cfturnstile_is_non_checkout_ajax() ) {
			return;
		}

		// Skip if Turnstile disabled for payment method
		$skip = 0;
		if ( $request->get_method() === 'POST' ) {
			if ( $request->get_param('payment_method') !== null ) {
				$chosen_payment_method = sanitize_text_field( $request->get_param('payment_method') );
				// Retrieve the selected payment methods from the cfturnstile_selected_payment_methods option
			$selected_payment_methods = get_option('cfturnstile_selected_payment_methods', array());
			if(is_array($selected_payment_methods)) {
				// Check if the chosen payment method is in the selected payment methods array
					if ( in_array( $chosen_payment_method, $selected_payment_methods, true ) ) {
						$skip = 1;
					}
				}
			}

			// Additional skip: WooPayments Express or Stripe Express (Apple Pay / Google Pay / Link) on block checkout.
			if ( ! $skip ) {
				$payment_method = $request->get_param( 'payment_method' );
				$payment_data   = $request->get_param( 'payment_data' );
				$express_detected = false;
				if ( is_array( $payment_data ) ) {
					foreach ( $payment_data as $pd_item ) {
						if ( is_array( $pd_item ) && isset( $pd_item['key'] ) ) {
							$key   = $pd_item['key'];
							$value = isset( $pd_item['value'] ) ? $pd_item['value'] : '';
							if ( in_array( $key, array( 'express_payment_type', 'payment_request_type' ), true )
								&& ! empty( $value ) ) {
								$express_detected = true;
								break;
							}
						}
					}
					// Allow customization via filter, defaults to skip when WooPayments or Stripe express is detected.
					$skip_on_express = apply_filters( 'cfturnstile_skip_on_express_pay', ( ($payment_method === 'woocommerce_payments' || $payment_method === 'stripe') && $express_detected ), $payment_method, $payment_data, $request );
					if ( $skip_on_express ) {
						$skip = 1;
					}
				}
			}

			// Check if guest only enabled
			$guest = esc_attr( get_option('cfturnstile_guest_only') );
			// Check — always require a fresh Turnstile token (tokens are single-use).
			if( !$skip && (!$guest || ( $guest && !is_user_logged_in() )) ) {
				$extensions = $request->get_param( 'extensions' );
				$token = ( is_array( $extensions ) && isset( $extensions['simple-cloudflare-turnstile']['token'] ) ) ? $extensions['simple-cloudflare-turnstile']['token'] : '';

				if ( empty( $token ) ) {
					throw new \Exception( cfturnstile_failed_message() );
				}

				// Store token so the cleanup callback can access it.
				global $cfturnstile_block_checkout_token;
				$cfturnstile_block_checkout_token = $token;

				// If this token already passed verification, skip re-check.
				if ( cfturnstile_get_verified( 'cfturnstile_block_checkout_checked', $token ) ) {
					$cfturnstile_wc_block_checkout_ran = true;
					return;
				}
				
				$check = cfturnstile_check( $token );
				$success = $check['success'];
				$cfturnstile_wc_block_checkout_ran = true;
				if($success != true) {
					throw new \Exception( cfturnstile_failed_message() );
				} else {
					cfturnstile_set_verified( 'cfturnstile_block_checkout_checked', $token, 120 );
				}
			}
		}
	}

	// Clear checkout verification transients after all validation hooks have run
	add_action('woocommerce_after_checkout_validation', 'cfturnstile_woo_checkout_clear_transient', 9999);
	function cfturnstile_woo_checkout_clear_transient() {
		$deadline_key = cfturnstile_transient_key( 'cfturnstile_checkout_deferred_until' );

		// A gateway may have halted this request to run 3DS before resubmitting the same form with
		// the same token, so keep the pass alive for the resubmission. Checking verified first
		// matters: the marker is added even when the challenge failed, so extending an existing
		// pass is safe, but granting one here would be a bypass.
		if ( cfturnstile_get_verified( 'cfturnstile_checkout_checked' ) && cfturnstile_woo_checkout_deferred_by_gateway() ) {
			// 3DS can keep the customer busy well past the 120 seconds a single request needs.
			$expire = (int) apply_filters( 'cfturnstile_woo_deferred_checkout_expiry', 900 );
			if ( $expire > 0 && $deadline_key ) {
				// Fixed on the first deferral, so repeated markers cannot extend the pass forever.
				$deadline = (int) get_transient( $deadline_key );
				if ( ! $deadline ) {
					$deadline = time() + $expire;
					set_transient( $deadline_key, $deadline, $expire );
				}
				$remaining = $deadline - time();
				if ( $remaining > 0 ) {
					cfturnstile_set_verified( 'cfturnstile_checkout_checked', '', $remaining );
					return;
				}
			}
		}

		// Either the gateway resubmitted and the flow is over, or the deadline has passed.
		if ( $deadline_key ) {
			delete_transient( $deadline_key );
		}
		cfturnstile_clear_verified( 'cfturnstile_checkout_checked' );
	}

	// Block checkout: clear the transient after the order is processed
	add_action('woocommerce_store_api_checkout_order_processed', 'cfturnstile_woo_block_checkout_clear_transient', 9999);
	function cfturnstile_woo_block_checkout_clear_transient() {
		global $cfturnstile_block_checkout_token;
		if ( ! empty( $cfturnstile_block_checkout_token ) ) {
			cfturnstile_clear_verified( 'cfturnstile_block_checkout_checked', $cfturnstile_block_checkout_token );
		}
	}

	add_action('woocommerce_loaded', 'cfturnstile_register_endpoint_data', 20);
	function cfturnstile_register_endpoint_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => 'simple-cloudflare-turnstile',
				'schema_callback' => function() {
					return array(
						'token' => array(
							'description'       => __( 'Turnstile token.', 'simple-cloudflare-turnstile' ),
							'type'              => 'string',
							'context'           => array( 'view', 'edit' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					);
				},
			)
		);
	}
}


// Woo Checkout Pay Order Check
if(get_option('cfturnstile_woo_checkout_pay')) {
	add_action('woocommerce_pay_order_before_submit', 'cfturnstile_field_checkout', 10);
	add_action('woocommerce_before_pay_action', 'cfturnstile_woo_checkout_pay_check', 10, 2);
	function cfturnstile_woo_checkout_pay_check($order) {
		$check = cfturnstile_check();
		$success = $check['success'];
		if($success != true) {
			wc_add_notice( cfturnstile_failed_message(), 'error');
		}
	}
}

// Woo Login Check
if(get_option('cfturnstile_woo_login')) {
	add_action('woocommerce_login_form','cfturnstile_field_woo_login');
	if(!get_option('cfturnstile_login')) {
		add_action('authenticate', 'cfturnstile_woo_login_check', 21, 1);
		function cfturnstile_woo_login_check($user) {

			// Check skip
			if(!isset($user->ID)) { return $user; }
			if(!isset($_POST['woocommerce-login-nonce'])) { return $user; } // Skip if not WooCommerce login
			if(defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST) { return $user; } // Skip XMLRPC
			if(defined( 'REST_REQUEST' ) && REST_REQUEST) { return $user; } // Skip REST API
			if(is_wp_error($user) && isset($user->errors['empty_username']) && isset($user->errors['empty_password']) ) {return $user; } // Skip Errors

			// Check if already validated (cache-friendly, no PHP session)
			if( cfturnstile_get_verified( 'cfturnstile_login_checked_' . $user->ID ) ) {
				return $user;
			}

			// Check Turnstile
			$check = cfturnstile_check();
			$success = $check['success'];
			if($success != true) {
				$user = new WP_Error( 'cfturnstile_error', cfturnstile_failed_message() );
			} else {
				cfturnstile_set_verified( 'cfturnstile_login_checked_' . $user->ID, '', 300 );
			}
			
			return $user;
			
		}
	}
}

// WP login check to skip when Woo login is disabled
add_filter( 'cfturnstile_wp_login_checks', 'cfturnstile_woo_skip_wp_login_check', 10, 1 );
function cfturnstile_woo_skip_wp_login_check( $skip ) {
	// If the WooCommerce login integration is disabled but a Woo login form is submitted,
	// skip the global WordPress login Turnstile check.
	if ( ! get_option( 'cfturnstile_woo_login' ) && isset( $_POST['woocommerce-login-nonce'] ) ) {
		return true;
	}
	return $skip;
}

// Woo Register Check
if(get_option('cfturnstile_woo_register')) {
	add_action('woocommerce_register_form','cfturnstile_field_woo_register');
	if(!is_admin()) { // Prevents admin registration from failing
		add_action('woocommerce_register_post', 'cfturnstile_woo_register_check', 10, 3);
	}
	function cfturnstile_woo_register_check($username, $email, $validation_errors) {
		if(defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST) { return; } // Skip XMLRPC
		if(defined( 'REST_REQUEST' ) && REST_REQUEST) { return; } // Skip REST API
		if(function_exists('is_checkout') && is_checkout()) { return; } // Skip if on checkout page, to avoid conflicts with the checkout integration
		$check = cfturnstile_check();
		$success = isset( $check['success'] ) ? $check['success'] : false;
		if($success != true) {
			$validation_errors->add( 'cfturnstile_error', cfturnstile_failed_message() );
		}
	}
}

// Woo Reset Check
if(get_option('cfturnstile_woo_reset')) {
	add_action('woocommerce_lostpassword_form','cfturnstile_field_woo_reset');
	add_action('lostpassword_post','cfturnstile_woo_reset_check', 10, 1);
	function cfturnstile_woo_reset_check($validation_errors) {
		if(isset($_POST['woocommerce-lost-password-nonce'])) {
			$check = cfturnstile_check();
			$success = isset( $check['success'] ) ? $check['success'] : false;
			if($success != true) {
				$validation_errors->add( 'cfturnstile_error', cfturnstile_failed_message() );
			}
		}
	}
}

// Woo Account Details Check
if(get_option('cfturnstile_woo_account')) {
	add_action('woocommerce_edit_account_form','cfturnstile_field_woo_account');
	add_action('woocommerce_save_account_details_errors', 'cfturnstile_woo_account_check', 10, 1);
	function cfturnstile_woo_account_check($validation_errors) {
		if ( ! is_wp_error( $validation_errors ) ) {
			return;
		}

		if ( empty( $_POST['save-account-details-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save-account-details-nonce'] ) ), 'save_account_details' ) ) {
			return;
		}

		$check = cfturnstile_check();
		$success = isset( $check['success'] ) ? $check['success'] : false;
		if($success != true) {
			$validation_errors->add( 'cfturnstile_error', cfturnstile_failed_message() );
		}
	}
}

// Check if WooCommerce block checkout page
function cfturnstile_is_block_based_checkout() {
    if ( function_exists('is_checkout') && is_checkout() && !isset($_GET['pay_for_order']) ) {
        $checkout_page_id = wc_get_page_id( 'checkout' );
        if ( $checkout_page_id && has_block( 'woocommerce/checkout', get_post( $checkout_page_id )->post_content ) ) {
            return true;
        }
    }
    return false;
}