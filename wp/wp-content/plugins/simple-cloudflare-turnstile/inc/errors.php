<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Display error notice if Turnstile is not showing on forms
 */
add_action('admin_notices', 'cfturnstile_tested_notice');
function cfturnstile_tested_notice() {
	if(!isset($_GET['page']) || $_GET['page'] != 'cfturnstile') {
		if (!empty(get_option('cfturnstile_key')) && !empty(get_option('cfturnstile_secret'))) {
			// Get the option from the database
			$cfturnstile_tested = get_option('cfturnstile_tested');
			
			// If the option is 'no', display the error notice
			if ($cfturnstile_tested === 'no') {
				echo '<div class="notice notice-error is-dismissible">';
				echo sprintf(
					'<p>' . wp_kses_post(__('Cloudflare Turnstile is not currently showing on your forms. Please test the API response on the <a href="%s">settings page</a>.', 'simple-cloudflare-turnstile')) .
					'</p>',	admin_url('options-general.php?page=cfturnstile')
				);
				echo '</div>';
			}
		}
	}
}

/**
 * Display persistent admin warning if an invalid secret key was detected.
 * Dismissible via AJAX - stays until the admin clicks to dismiss.
 */
add_action( 'admin_notices', 'cfturnstile_invalid_secret_notice' );
function cfturnstile_invalid_secret_notice() {
	if ( '1' !== get_option( 'cfturnstile_invalid_secret_notice' ) ) {
		return;
	}
	$settings_url = admin_url( 'options-general.php?page=cfturnstile' );
	$ajax_url     = esc_url( admin_url( 'admin-ajax.php' ) );
	$nonce        = wp_create_nonce( 'cfturnstile_dismiss_invalid_secret' );
	?>
	<div class="notice notice-warning" id="cfturnstile-invalid-secret-notice">
		<p>
			<strong><?php esc_html_e( 'Cloudflare Turnstile:', 'simple-cloudflare-turnstile' ); ?></strong>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: URL to the plugin settings page. */
					__( 'Your Turnstile secret key was rejected by Cloudflare (<code>invalid-input-secret</code>). Please verify your API keys on the <a href="%s">settings page</a>. Turnstile will continue to protect your forms, but verifications may fail until the key is corrected.', 'simple-cloudflare-turnstile' ),
					esc_url( $settings_url )
				)
			);
			?>
		</p>
		<p>
			<a href="#" id="cfturnstile-dismiss-invalid-secret" class="button button-small">
				<?php esc_html_e( 'Dismiss', 'simple-cloudflare-turnstile' ); ?>
			</a>
		</p>
	</div>
	<script>
	document.getElementById( 'cfturnstile-dismiss-invalid-secret' ).addEventListener( 'click', function( e ) {
		e.preventDefault();
		var notice = document.getElementById( 'cfturnstile-invalid-secret-notice' );
		notice.style.display = 'none';
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', '<?php echo esc_url( $ajax_url ); ?>', true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.send( 'action=cfturnstile_dismiss_invalid_secret&_wpnonce=<?php echo esc_attr( $nonce ); ?>' );
	});
	</script>
	<?php
}

/**
 * AJAX handler to dismiss the invalid secret notice.
 */
add_action( 'wp_ajax_cfturnstile_dismiss_invalid_secret', 'cfturnstile_dismiss_invalid_secret_handler' );
function cfturnstile_dismiss_invalid_secret_handler() {
	check_ajax_referer( 'cfturnstile_dismiss_invalid_secret', '_wpnonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}
	delete_option( 'cfturnstile_invalid_secret_notice' );
	wp_send_json_success();
}

/**
 * Gets the custom Turnstile failed message
 */
function cfturnstile_failed_message($default = "") {
	if (!$default && !empty(get_option('cfturnstile_error_message')) && get_option('cfturnstile_error_message')) {
		return sanitize_text_field(wp_kses_post(get_option('cfturnstile_error_message')));
	} else {
		return esc_html__('Please verify that you are human.', 'simple-cloudflare-turnstile');
	}
}

/**
 * Gets the official Turnstile error message
 *
 * @param string $code
 * @return string
 */
function cfturnstile_error_message($code) {
	switch ($code) {
		case 'missing-input-secret':
			return esc_html__('The secret parameter was not passed.', 'simple-cloudflare-turnstile');
		case 'invalid-input-secret':
			return esc_html__('The secret parameter was invalid or did not exist.', 'simple-cloudflare-turnstile');
		case 'missing-input-response':
			return esc_html__('The response parameter was not passed.', 'simple-cloudflare-turnstile');
		case 'invalid-input-response':
			return esc_html__('The response parameter is invalid or has expired.', 'simple-cloudflare-turnstile');
		case 'bad-request':
			return esc_html__('The request was rejected because it was malformed.', 'simple-cloudflare-turnstile');
		case 'timeout-or-duplicate':
			return esc_html__('The response parameter has already been validated before.', 'simple-cloudflare-turnstile');
		case 'internal-error':
			return esc_html__('An internal error happened while validating the response. The request can be retried.', 'simple-cloudflare-turnstile');
		default:
			return esc_html__('There was an error with Turnstile response. Please check your keys are correct.', 'simple-cloudflare-turnstile');
	}
}