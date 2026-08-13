<?php
if (!defined('ABSPATH')) {
	exit;
}

// Create custom plugin settings menu
add_action('admin_menu', 'cfturnstile_create_menu');
function cfturnstile_create_menu() {
    add_submenu_page(
        'options-general.php', // Parent slug
        'Cloudflare Turnstile', // Page title
        'Cloudflare Turnstile', // Menu title
        'manage_options', // Capability
        'cfturnstile', // Menu slug
        'cfturnstile_settings_page' // Callback function
    );
}

/**
 * Checks a Turnstile secret key with Cloudflare, without needing a solved challenge.
 *
 * Sends a deliberately invalid response token. Cloudflare only reports an "input-secret" error
 * once the secret itself is rejected, so being told the response is the problem means the secret
 * was accepted.
 *
 * @param string $secret Optional. Secret key to check. Defaults to the saved key.
 * @return bool|null True if Cloudflare accepted the secret, false if it is empty or Cloudflare
 *                   rejected it, null if Cloudflare could not be reached or gave an answer we
 *                   can not interpret.
 */
function cfturnstile_verify_secret( $secret = '' ) {

	if ( empty( $secret ) ) {
		$secret = sanitize_text_field( get_option('cfturnstile_secret') );
	}
	if ( empty( $secret ) ) {
		return false;
	}

	$verify = wp_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $secret,
				'response' => 'cfturnstile-key-check',
			),
		)
	);

	if ( is_wp_error( $verify ) || 200 !== wp_remote_retrieve_response_code( $verify ) ) {
		return null;
	}

	$response = json_decode( wp_remote_retrieve_body( $verify ), true );
	if ( ! is_array( $response ) ) {
		return null;
	}

	// Cloudflare's "always passes" testing secret accepts the dummy response outright.
	if ( ! empty( $response['success'] ) ) {
		return true;
	}

	$errors = ( isset( $response['error-codes'] ) && is_array( $response['error-codes'] ) ) ? $response['error-codes'] : array();

	// Checked before the response codes below, so that a reply carrying both is still read as
	// the secret being the problem.
	if ( array_intersect( array( 'invalid-input-secret', 'missing-input-secret' ), $errors ) ) {
		return false;
	}

	// Cloudflare got past the secret and only objected to the dummy response.
	if ( array_intersect( array( 'invalid-input-response', 'missing-input-response', 'timeout-or-duplicate' ), $errors ) ) {
		return true;
	}

	return null;

}

// Keys Updated
add_action('update_option_cfturnstile_key', 'cfturnstile_keys_updated', 10);
add_action('update_option_cfturnstile_secret', 'cfturnstile_keys_updated', 10);
function cfturnstile_keys_updated() {

	// Saving both keys together fires this twice.
	static $handled = false;
	if ( $handled ) {
		return;
	}
	$handled = true;

	delete_option( 'cfturnstile_invalid_secret_notice' );
	delete_option( 'cfturnstile_soft_tested' );
	delete_transient( 'cfturnstile_invalid_secret_throttle' );

	// Changed by an admin in wp-admin, either by saving the settings page or by importing
	// settings. Both land them back on the settings page with the manual test in front of them,
	// and that test proves the site key renders a working widget as well as the secret being
	// accepted, so hold the integrations back until they run it.
	// WP-CLI is excluded explicitly: "wp --user=1 option update" runs as an administrator, but
	// there is still no settings page in front of anyone.
	$by_admin = ! ( defined( 'WP_CLI' ) && WP_CLI ) && is_admin() && current_user_can( 'manage_options' );

	if ( $by_admin ) {
		update_option( 'cfturnstile_tested', 'no' );
		return;
	}

	// Changed programmatically instead (WP-CLI, REST, a migration or provisioning script). Nobody
	// is going to see the manual test, so resetting the flag here would switch every integration
	// off and leave the site unprotected until somebody happened to log in. Ask Cloudflare about
	// the new secret at the end of the request instead, once both key options have been written.
	add_action( 'shutdown', 'cfturnstile_keys_updated_verify' );

}

// Verify programmatically changed keys, rather than disabling Turnstile until a manual test
function cfturnstile_keys_updated_verify() {

	// A key was removed rather than rotated. Turnstile is inert without both anyway.
	if ( empty( get_option( 'cfturnstile_key' ) ) || empty( get_option( 'cfturnstile_secret' ) ) ) {
		update_option( 'cfturnstile_tested', 'no' );
		return;
	}

	$valid = cfturnstile_verify_secret();

	// Cloudflare accepted the new secret, so keep protecting the site.
	if ( true === $valid ) {
		update_option( 'cfturnstile_tested', 'yes' );
		return;
	}

	// Cloudflare could not be reached, or answered with something we can not read. Leave the
	// current state alone rather than dropping protection over a temporary network problem: a
	// genuinely bad secret is still caught on the first real verification, which raises the
	// invalid secret notice and emails the admin.
	if ( null === $valid ) {
		return;
	}

	// Cloudflare rejected the secret. Turning Turnstile off is right here, but say so loudly,
	// since there is no settings page in front of anyone to self-heal this.
	// An empty option counts as active, matching how the integrations decide whether to load.
	$tested     = get_option( 'cfturnstile_tested' );
	$was_active = ( 'yes' === $tested || empty( $tested ) );

	update_option( 'cfturnstile_tested', 'no' );
	update_option( 'cfturnstile_invalid_secret_notice', '1' );

	if ( ! $was_active ) {
		return;
	}

	$site_name    = get_bloginfo( 'name' );
	$settings_url = admin_url( 'options-general.php?page=cfturnstile' );
	$subject      = sprintf(
		/* translators: %s: Site name. */
		__( '[%s] Cloudflare Turnstile: Invalid Secret Key Detected', 'simple-cloudflare-turnstile' ),
		$site_name
	);
	$message = sprintf(
		/* translators: 1: Site name, 2: Settings page URL. */
		__( "The Turnstile API keys on %1\$s have been changed, and Cloudflare has rejected the new secret key (error: invalid-input-secret).\n\nTurnstile has been switched off on your forms until the keys are corrected and tested, so your forms are not currently protected.\n\nPlease check your API keys on the settings page:\n%2\$s", 'simple-cloudflare-turnstile' ),
		$site_name,
		$settings_url
	);
	wp_mail( get_option( 'admin_email' ), $subject, $message );

}

// Admin test form to check Turnstile response
function cfturnstile_admin_test( $soft = false ) {
?>
	<form action="" method="POST" class="cfturnstile-settings">
		<?php
		if (!empty(get_option('cfturnstile_key')) && !empty(get_option('cfturnstile_secret'))) {
			$check = cfturnstile_check();
			$success = '';
			$error = '';
			if (isset($check['success'])) $success = $check['success'];
			if (isset($check['error_code'])) $error = $check['error_code'];
			if ( $success != true && ! $soft ) {
				echo '<div style="padding: 20px 20px 25px 20px; margin: 20px 0 28px 0; background: #fff; border-radius: 20px; max-width: 500px; border: 2px solid #d5d5d5;">';
				echo '<p style="font-weight: 600; font-size: 19px; margin-top: 0; margin-bottom: 0;">' . esc_html__('Almost done...', 'simple-cloudflare-turnstile') . '</p>';
			}
			if ( ! isset($_POST['cf-turnstile-response']) ) {
				if(! $soft ) {
				echo '<p>'
					. '<span style="color: red; font-weight: bold;">' . esc_html__('API keys have been updated. Please test the Turnstile API response below.', 'simple-cloudflare-turnstile') . '</span>'
					. '<br/>'
					. esc_html__('Turnstile will not be added to any forms until the test is successfully complete.', 'simple-cloudflare-turnstile')
					. '</p>';
				}
			} else {
				if ($success == true) {
					update_option( 'cfturnstile_tested', 'yes' );
					delete_option( 'cfturnstile_invalid_secret_notice' );
					delete_option( 'cfturnstile_soft_tested' );
					delete_transient( 'cfturnstile_invalid_secret_throttle' );
				} else {
					if ($error == "missing-input-response") {
						echo '<p style="font-weight: bold; color: red;">' . cfturnstile_failed_message() . '</p>';
					} else {
						echo '<p style="font-weight: bold; color: red;">' . esc_html__('Failed! There is an error with your API settings. Please check & update them.', 'simple-cloudflare-turnstile') . '</p>';
					}
				}
				if ($error) {
					echo '<p style="font-weight: bold;">' . esc_html__('Error message:', 'simple-cloudflare-turnstile') . " " . cfturnstile_error_message($error) . '</p>';
				}
			}
			if ($success != true) {
				echo '<div style="margin-left: 0px;">';
				echo cfturnstile_field_show('', '', 'admin-test', 'admin-test');
				echo '</div><div style="margin-bottom: -20px;"></div>';
				echo '<button type="submit" style="margin-top: 10px; padding: 7px 10px; background: #1c781c; color: #fff; font-weight: bold; border: 1px solid #176017; border-radius: 4px; cursor: pointer;">
				' . esc_html__('TEST RESPONSE', 'simple-cloudflare-turnstile') . ' <span class="dashicons dashicons-arrow-right-alt"></span>
				</button>';
				if ( ! $soft ) {
					echo '</div>';
				}
			}
		}
		?>
	</form>
<?php
}

function cfturnstile_get_admin_tab() {
	$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings';
	$allowed_tabs = array('settings', 'analytics', 'export');

	return in_array($tab, $allowed_tabs, true) ? $tab : 'settings';
}

function cfturnstile_render_admin_tabs($active_tab) {
	$tabs = array(
		'settings' => __('Settings', 'simple-cloudflare-turnstile'),
		'analytics' => __('Analytics', 'simple-cloudflare-turnstile'),
		'export' => __('Import', 'simple-cloudflare-turnstile'),
	);

	echo '<nav class="nav-tab-wrapper sct-tabs">';
	foreach ($tabs as $tab => $label) {
		$url = add_query_arg(array('page' => 'cfturnstile', 'tab' => $tab), admin_url('options-general.php'));
		$class = 'nav-tab';
		if ($active_tab === $tab) {
			$class .= ' nav-tab-active';
		}
		echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
	}
	echo '</nav>';
}

// Show Settings Page
function cfturnstile_settings_page() {
	
?>
	<div class="sct-wrap wrap">

		<h1 style="font-weight: bold;"><?php echo esc_html__('Simple CAPTCHA with Cloudflare Turnstile', 'simple-cloudflare-turnstile'); ?></h1>

		<?php
		// Check Cloudflare Status (cached for 2 minutes to avoid an HTTP request on every settings page load)
		$cfturnstile_failover = get_option('cfturnstile_failover');
		$cf_status_transient  = get_transient( 'cfturnstile_admin_cf_status' );
		if ( $cf_status_transient === false ) {
			$cf_status_check     = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array( 'timeout' => 5 ) );
			$cf_admin_is_down    = is_wp_error( $cf_status_check ) || wp_remote_retrieve_response_code( $cf_status_check ) >= 500;
			$cf_admin_error_msg  = is_wp_error( $cf_status_check ) ? $cf_status_check->get_error_message() : '';
			set_transient( 'cfturnstile_admin_cf_status', array( 'down' => $cf_admin_is_down, 'error' => $cf_admin_error_msg ), 2 * MINUTE_IN_SECONDS );
		} else {
			$cf_admin_is_down   = $cf_status_transient['down'];
			$cf_admin_error_msg = $cf_status_transient['error'];
		}
		if ( $cf_admin_is_down ) {
			?>
			<div class="notice notice-error inline" style="margin: 10px 0 20px 0;">
				<p>
					<strong><?php echo esc_html__('Cloudflare Turnstile API Error:', 'simple-cloudflare-turnstile'); ?></strong>
					<?php echo esc_html__('The Cloudflare Turnstile API seems to be down or unreachable.', 'simple-cloudflare-turnstile'); ?>
					<?php if ( $cfturnstile_failover ) {
						echo esc_html__('However, since you have Cloudflare Failover enabled, Turnstile will allow users to pass while the API is down.', 'simple-cloudflare-turnstile');
					} else {
						echo esc_html__('Turnstile will not function until the API is reachable again. To avoid this issue in the future, consider enabling Cloudflare Failover in the settings below.', 'simple-cloudflare-turnstile');
					} ?>
					<?php if ( ! empty( $cf_admin_error_msg ) ) { echo ' (' . esc_html( $cf_admin_error_msg ) . ')'; } ?>
				</p>
			</div>
			<?php
		}
		?>

		<p style="margin-bottom: 0;"><?php echo esc_html__('Easily add the free CAPTCHA service called "Cloudflare Turnstile" to your WordPress forms to help prevent spam.', 'simple-cloudflare-turnstile'); ?> <a href="https://www.cloudflare.com/en-gb/products/turnstile/" target="_blank"><?php echo esc_html__('Learn more.', 'simple-cloudflare-turnstile'); ?></a>

		<div class="sct-admin-promo-top">

			<p>
				<a href="https://elliotsowersby.com/blog/setup-guide-turnstile/?utm_source=simplecloudflareturnstile&utm_medium=settings-guide" title="View our Turnstile plugin setup guide." target="_blank"><?php echo esc_html__('View setup guide', 'simple-cloudflare-turnstile'); ?><span class="dashicons dashicons-external" style="margin-left: 2px; text-decoration: none;"></span></a> &nbsp;&#x2022;&nbsp; <?php echo esc_html__('Like this plugin?', 'simple-cloudflare-turnstile'); ?> <a href="https://wordpress.org/support/plugin/simple-cloudflare-turnstile/reviews/#new-post" target="_blank" title="<?php echo esc_html__('Review on WordPress.org', 'simple-cloudflare-turnstile'); ?>"><?php echo esc_html__('Please submit a review', 'simple-cloudflare-turnstile'); ?></a> <a href="https://wordpress.org/support/plugin/simple-cloudflare-turnstile/reviews/#new-post" target="_blank" title="<?php echo esc_html__('Review on WordPress.org', 'simple-cloudflare-turnstile'); ?>" style="text-decoration: none;">
					<span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span>
				</a>
			</p>

		</div>

		<?php
		$cfturnstile_active_tab = cfturnstile_get_admin_tab();
		cfturnstile_render_admin_tabs($cfturnstile_active_tab);
		?>

		<?php if ('settings' === $cfturnstile_active_tab) { ?>

		<?php
		if ( empty( get_option( 'cfturnstile_tested' ) ) || get_option( 'cfturnstile_tested' ) != 'yes' ) {
			echo cfturnstile_admin_test();
		} elseif ( 'no' === get_option( 'cfturnstile_soft_tested' ) ) {
			// Buffer the test form output so we can process the POST before deciding whether to show the wrapper.
			ob_start();
			cfturnstile_admin_test( true );
			$soft_test_output = ob_get_clean();
			// Re-check after processing — if the test passed, the flag will have been deleted.
			if ( 'no' === get_option( 'cfturnstile_soft_tested' ) ) {
				echo '<div style="padding: 20px 20px 25px 20px; margin: 20px 0 28px 0; background: #fff; border-radius: 20px; max-width: 500px; border: 2px solid #f0c33c;">';
				echo '<p style="font-weight: 600; font-size: 19px; margin-top: 0; margin-bottom: 0;"><span class="dashicons dashicons-warning" style="color: #f0c33c; font-size: 28px; margin-right: 5px;"></span> ' . esc_html__( 'Re-test Recommended', 'simple-cloudflare-turnstile' ) . '</p>';
				echo '<p>' . esc_html__( 'Cloudflare reported an invalid secret key error. Turnstile is still active on your forms, but verifications may be failing. Please re-test your API keys below.', 'simple-cloudflare-turnstile' ) . '</p>';
				echo $soft_test_output;
				echo '</div>';
			}
		}
		?>

		<form method="post" action="options.php" class="cfturnstile-settings">

			<?php settings_fields('cfturnstile-settings-group'); ?>
			<?php do_settings_sections('cfturnstile-settings-group'); ?>

			<hr style="margin: 20px 0 0 0;">

			<table class="form-table">

				<tr valign="top">
					<th scope="row" style="padding-bottom: 0;">

						<p style="font-size: 15px; font-size: 19px; margin-top: 0;"><?php echo esc_html__('API Key Settings:', 'simple-cloudflare-turnstile'); ?></p>

						<?php
						// wp-config.php override info
						$cf_const_site   = ( defined('CF_TURNSTILE_SITE_KEY') && CF_TURNSTILE_SITE_KEY );
						$cf_const_secret = ( defined('CF_TURNSTILE_SECRET_KEY') && CF_TURNSTILE_SECRET_KEY );
						?>

						<?php
						if ( !$cf_const_site && !$cf_const_secret ) {
							if ( get_option('cfturnstile_tested') == 'yes' && 'no' !== get_option( 'cfturnstile_soft_tested' ) ) {
								echo '<p style=" font-weight: bold; color: #1e8c1e;"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__('Success! Turnstile is working correctly with your API keys.', 'simple-cloudflare-turnstile') . '</p>';
							}
						}
						?>

						<p style="margin-bottom: 2px;"><?php echo esc_html__('You can get your site key and secret key from here:', 'simple-cloudflare-turnstile'); ?> <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">https://dash.cloudflare.com/?to=/:account/turnstile</a></p>

						<?php
						if ( $cf_const_site || $cf_const_secret ) {
							$which = array();
							if ( $cf_const_site ) { $which[] = esc_html__('Site Key', 'simple-cloudflare-turnstile'); }
							if ( $cf_const_secret ) { $which[] = esc_html__('Secret Key', 'simple-cloudflare-turnstile'); }
							$which_text = implode(' & ', $which);
							printf(
								'<p style="margin: 15px 0 0 0; font-weight:600; color:#1e8c1e;"><span class="dashicons dashicons-lock"></span> %s</p>',
								esc_html__('Using keys defined in wp-config.php. Be sure to test your forms to confirm they are working.', 'simple-cloudflare-turnstile')
							);
						}
						?>



					</th>
				</tr>

			</table>

			<table class="form-table">

				<tr valign="top">
					<th scope="row"><?php echo esc_html__('Site Key', 'simple-cloudflare-turnstile'); ?></th>
					<td>
						<?php $cf_const_site = ( defined('CF_TURNSTILE_SITE_KEY') && CF_TURNSTILE_SITE_KEY ); ?>
						<?php if ($cf_const_site) : ?>
							<?php
							$cf_key_placeholder = esc_attr(get_option('cfturnstile_key'));
							?>
							<p>
								<?php echo esc_html($cf_key_placeholder); ?>
							</p>
							<input type="hidden" name="cfturnstile_key" value="" />
						<?php else : ?>
							<input type="text" style="width: 240px;" name="cfturnstile_key" value="<?php echo esc_attr(get_option('cfturnstile_key')); ?>" />
						<?php endif; ?>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo esc_html__('Secret Key', 'simple-cloudflare-turnstile'); ?></th>
					<td>
						<?php $cf_const_secret = ( defined('CF_TURNSTILE_SECRET_KEY') && CF_TURNSTILE_SECRET_KEY ); ?>
						<?php if ($cf_const_secret) : ?>
							<?php // Replace the last 20 characters of key with 20 asterisks
							$cf_secret = esc_attr(get_option('cfturnstile_secret'));
							$cf_secret_placeholder = substr($cf_secret, 0, 20) . '********************';
							?>
							<p>
								<?php echo esc_html($cf_secret_placeholder); ?>
							</p>
							<input type="hidden" name="cfturnstile_secret" value="" />
						<?php else : ?>
							<input type="password" id="cfturnstile_secret" style="width: 240px;" name="cfturnstile_secret" value="<?php echo esc_attr(get_option('cfturnstile_secret')); ?>" />
							<button type="button" class="button sct-toggle-secret" data-target="cfturnstile_secret" aria-pressed="false" style="margin-left: 6px;"><?php echo esc_html__('View', 'simple-cloudflare-turnstile'); ?></button>
						<?php endif; ?>
					</td>
				</tr>

			</table>
			
			<?php if(!$cf_const_site || !$cf_const_secret) { ?>
			<?php
			$cf_site_opt = get_option('cfturnstile_key');
			$site_snippet_val = $cf_site_opt ? $cf_site_opt : 'your-site-key';
			$cf_secret_opt = get_option('cfturnstile_secret');
			$secret_snippet_val = $cf_secret_opt ? $cf_secret_opt : 'your-secret-key';
			?>
			<div style="max-width: 760px; margin: 6px 0 24px 0;">
				<a href="#" class="sct-wpconfig-toggle" style="color:#2271b1; text-decoration:none; font-size:13px;">
					<?php echo esc_html__('Optional: Define keys in wp-config.php', 'simple-cloudflare-turnstile'); ?>
					<span class="dashicons dashicons-arrow-down-alt2"
					style="vertical-align: text-bottom; font-size: 12px; display: inline-block; margin-bottom: -7px; width: 15px;"></span>
				</a>
				<div class="sct-wpconfig-details" style="display:none; margin-top:6px;">
					<p>
						<?php echo esc_html__('You can optionally define your API keys in your wp-config.php file so the keys are not stored in the database.', 'simple-cloudflare-turnstile'); ?>
					</p>
					<p>
						<?php echo esc_html__('To do this, add the following lines to your wp-config.php file before the line that says "/* That\'s all, stop editing! Happy publishing. */":', 'simple-cloudflare-turnstile'); ?>
					</p>
					<span style="background: #ffffff; border:1px solid #e1e1e1; padding:10px; display:inline-block;">
					define('CF_TURNSTILE_SITE_KEY', '<?php echo esc_html($site_snippet_val); ?>');
					<br/>
					define('CF_TURNSTILE_SECRET_KEY', '<?php echo esc_html($secret_snippet_val); ?>');
					</span>
					<p>
						<?php echo esc_html__('Warning: This is not required. Only do this if you are comfortable editing wp-config.php. If you define the keys here, they will override the settings above.', 'simple-cloudflare-turnstile'); ?>
					</p>
				</div>
			</div>
			<?php } ?>

			<hr style="margin: 20px 0 10px 0;">

			<table class="form-table">

				<tr valign="top">
					<th scope="row" style="font-size: 19px; padding-bottom: 5px;"><?php echo esc_html__('General Settings:', 'simple-cloudflare-turnstile'); ?></th>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo esc_html__('Theme', 'simple-cloudflare-turnstile'); ?></th>
					<td>
						<select name="cfturnstile_theme">
							<option value="light" <?php if (!get_option('cfturnstile_theme') || get_option('cfturnstile_theme') == "light") { ?>selected<?php } ?>>
								<?php esc_html_e('Light', 'simple-cloudflare-turnstile'); ?>
							</option>
							<option value="dark" <?php if (get_option('cfturnstile_theme') == "dark") { ?>selected<?php } ?>>
								<?php esc_html_e('Dark', 'simple-cloudflare-turnstile'); ?>
							</option>
							<option value="auto" <?php if (get_option('cfturnstile_theme') == "auto") { ?>selected<?php } ?>>
								<?php esc_html_e('Auto', 'simple-cloudflare-turnstile'); ?>
							</option>
						</select>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php echo esc_html__('Language', 'simple-cloudflare-turnstile'); ?></th>
					<td>
						<select name="cfturnstile_language">
						<?php
						$languages = array(
							'auto'   => esc_html__( 'Auto Detect', 'simple-cloudflare-turnstile' ),
							'ar-eg'  => esc_html__( 'Arabic (Egypt)', 'simple-cloudflare-turnstile' ),
							'bg-bg'  => esc_html__( 'Bulgarian (Bulgaria)', 'simple-cloudflare-turnstile' ),
							'zh-cn'  => esc_html__( 'Chinese (Simplified, China)', 'simple-cloudflare-turnstile' ),
							'zh-tw'  => esc_html__( 'Chinese (Traditional, Taiwan)', 'simple-cloudflare-turnstile' ),
							'hr-hr'  => esc_html__( 'Croatian (Croatia)', 'simple-cloudflare-turnstile' ),
							'cs-cz'  => esc_html__( 'Czech (Czech Republic)', 'simple-cloudflare-turnstile' ),
							'da-dk'  => esc_html__( 'Danish (Denmark)', 'simple-cloudflare-turnstile' ),
							'nl-nl'  => esc_html__( 'Dutch (Netherlands)', 'simple-cloudflare-turnstile' ),
							'en-us'  => esc_html__( 'English (United States)', 'simple-cloudflare-turnstile' ),
							'fa-ir'  => esc_html__( 'Farsi (Iran)', 'simple-cloudflare-turnstile' ),
							'fi-fi'  => esc_html__( 'Finnish (Finland)', 'simple-cloudflare-turnstile' ),
							'fr-fr'  => esc_html__( 'French (France)', 'simple-cloudflare-turnstile' ),
							'de-de'  => esc_html__( 'German (Germany)', 'simple-cloudflare-turnstile' ),
							'el-gr'  => esc_html__( 'Greek (Greece)', 'simple-cloudflare-turnstile' ),
							'he-il'  => esc_html__( 'Hebrew (Israel)', 'simple-cloudflare-turnstile' ),
							'hi-in'  => esc_html__( 'Hindi (India)', 'simple-cloudflare-turnstile' ),
							'hu-hu'  => esc_html__( 'Hungarian (Hungary)', 'simple-cloudflare-turnstile' ),
							'id-id'  => esc_html__( 'Indonesian (Indonesia)', 'simple-cloudflare-turnstile' ),
							'it-it'  => esc_html__( 'Italian (Italy)', 'simple-cloudflare-turnstile' ),
							'ja-jp'  => esc_html__( 'Japanese (Japan)', 'simple-cloudflare-turnstile' ),
							'tlh'    => esc_html__( 'Klingon (Qo’noS)', 'simple-cloudflare-turnstile' ),
							'ko-kr'  => esc_html__( 'Korean (Korea)', 'simple-cloudflare-turnstile' ),
							'lt-lt'  => esc_html__( 'Lithuanian (Lithuania)', 'simple-cloudflare-turnstile' ),
							'ms-my'  => esc_html__( 'Malay (Malaysia)', 'simple-cloudflare-turnstile' ),
							'nb-no'  => esc_html__( 'Norwegian Bokmål (Norway)', 'simple-cloudflare-turnstile' ),
							'pl-pl'  => esc_html__( 'Polish (Poland)', 'simple-cloudflare-turnstile' ),
							'pt-br'  => esc_html__( 'Portuguese (Brazil)', 'simple-cloudflare-turnstile' ),
							'ro-ro'  => esc_html__( 'Romanian (Romania)', 'simple-cloudflare-turnstile' ),
							'ru-ru'  => esc_html__( 'Russian (Russia)', 'simple-cloudflare-turnstile' ),
							'sr-ba'  => esc_html__( 'Serbian (Bosnia and Herzegovina)', 'simple-cloudflare-turnstile' ),
							'sk-sk'  => esc_html__( 'Slovak (Slovakia)', 'simple-cloudflare-turnstile' ),
							'sl-si'  => esc_html__( 'Slovenian (Slovenia)', 'simple-cloudflare-turnstile' ),
							'es-es'  => esc_html__( 'Spanish (Spain)', 'simple-cloudflare-turnstile' ),
							'sv-se'  => esc_html__( 'Swedish (Sweden)', 'simple-cloudflare-turnstile' ),
							'tl-ph'  => esc_html__( 'Tagalog (Philippines)', 'simple-cloudflare-turnstile' ),
							'th-th'  => esc_html__( 'Thai (Thailand)', 'simple-cloudflare-turnstile' ),
							'tr-tr'  => esc_html__( 'Turkish (Turkey)', 'simple-cloudflare-turnstile' ),
							'uk-ua'  => esc_html__( 'Ukrainian (Ukraine)', 'simple-cloudflare-turnstile' ),
							'vi-vn'  => esc_html__( 'Vietnamese (Vietnam)', 'simple-cloudflare-turnstile' ),
						);
						$auto = $languages['auto'];
						unset($languages['auto']);
						asort($languages);
						$languages = array_merge(array('auto' => $auto), $languages);
						foreach ($languages as $code => $name) {
							$selected = '';
							if(get_option('cfturnstile_language') == $code) { $selected = 'selected'; }
							?>
								<option value="<?php echo esc_attr($code); ?>" <?php echo esc_attr($selected); ?>>
									<?php echo esc_html($name); ?>
								</option>
							<?php
						}
						?>
						</select>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">
						<?php echo esc_html__('Disable Submit Button', 'simple-cloudflare-turnstile'); ?>
					</th>
					<td><input type="checkbox" name="cfturnstile_disable_button" <?php if (get_option('cfturnstile_disable_button')) { ?>checked<?php } ?>>
						<i style="font-size: 10px;"><?php echo esc_html__('When enabled, the user will not be able to click submit until the Turnstile challenge is completed.', 'simple-cloudflare-turnstile'); ?></i>
					</td>
				</tr>

			</table>

			<button type="button" class="sct-accordion" id="sct-accordion-whitelist"><?php echo esc_html__('Advanced Settings', 'simple-cloudflare-turnstile'); ?></button>
			<div class="sct-panel">

				<p style="margin: 0 0 15px 0; padding-bottom: 20px; border-bottom: 1px solid #f3f3f3;">
					<?php echo esc_html__('These settings are for more advanced customisation. If you are not sure about these, they do not need to be changed.', 'simple-cloudflare-turnstile'); ?>
				</p>

				<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

					<tr>
						<th scope="row" colspan="2" style="text-align: center; color: #8c8c8c;">
							<?php echo esc_html__('Widget Customization', 'simple-cloudflare-turnstile'); ?>
						</th>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Widget Size', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<select name="cfturnstile_size" style="width: 100%;">
								<option value="normal" <?php if (!get_option('cfturnstile_size') || get_option('cfturnstile_size') == "normal") { ?>selected<?php } ?>>
									<?php esc_html_e('Normal (300px)', 'simple-cloudflare-turnstile'); ?>
								</option>
								<option value="flexible" <?php if (get_option('cfturnstile_size') == "flexible") { ?>selected<?php } ?>>
									<?php esc_html_e('Flexible (100%)', 'simple-cloudflare-turnstile'); ?>
								</option>
								<option value="compact" <?php if (get_option('cfturnstile_size') == "compact") { ?>selected<?php } ?>>
									<?php esc_html_e('Compact (150px)', 'simple-cloudflare-turnstile'); ?>
								</option>
							</select>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Appearance Mode', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<select name="cfturnstile_appearance" style="width: 100%;">
							<?php
							$appearances = array(
								'always' => esc_html__( 'Always', 'simple-cloudflare-turnstile' ),
								// 'execute' => esc_html__( 'Execute', 'simple-cloudflare-turnstile' ), // Not really needed
								'interaction-only' => esc_html__( 'Interaction Only', 'simple-cloudflare-turnstile' ),
							);
							foreach ($appearances as $code => $name) {
								$selected = '';
								if(get_option('cfturnstile_appearance') == $code) { $selected = 'selected'; }
								?>
									<option value="<?php echo esc_attr($code); ?>" <?php echo esc_attr($selected); ?>>
										<?php echo esc_html($name); ?>
									</option>
								<?php
							}
							?>
							</select>
							<br/><br/>
							<div class="wcu-appearance-always" style="display: none;"><i style="font-size: 10px;"><?php echo esc_html__( 'Turnstile Widget is always displayed for all visitors.', 'simple-cloudflare-turnstile' ); ?></i></div>
							<div class="wcu-appearance-execute" style="display: none;"><i style="font-size: 10px;"><?php echo esc_html__( 'Turnstile Widget is only displayed after the challenge begins.', 'simple-cloudflare-turnstile' ); ?></i></div>
							<div class="wcu-appearance-interaction-only" style="display: none;"><i style="font-size: 10px;"><?php echo esc_html__( 'Turnstile Widget is only displayed in cases where an interaction is required. This essentially makes it "invisible" for most valid users.', 'simple-cloudflare-turnstile' ); ?></i></div>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Refresh Timeout', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<select name="cfturnstile_refresh_timeout" style="width: 100%;">
								<option value="auto" <?php if ( ! get_option('cfturnstile_refresh_timeout') || get_option('cfturnstile_refresh_timeout') === 'auto' ) { ?>selected<?php } ?>>
									<?php esc_html_e('Auto (default)', 'simple-cloudflare-turnstile'); ?>
								</option>
								<option value="manual" <?php if ( get_option('cfturnstile_refresh_timeout') === 'manual' ) { ?>selected<?php } ?>>
									<?php esc_html_e('Manual', 'simple-cloudflare-turnstile'); ?>
								</option>
								<option value="never" <?php if ( get_option('cfturnstile_refresh_timeout') === 'never' ) { ?>selected<?php } ?>>
									<?php esc_html_e('Never', 'simple-cloudflare-turnstile'); ?>
								</option>
							</select>
							<br/><br/>
							<div class="wcu-refresh-timeout-auto" style="display: none;"><i style="font-size: 10px;"><?php echo esc_html__( 'The widget automatically refreshes when an interactive challenge times out. Recommended for most use cases.', 'simple-cloudflare-turnstile' ); ?></i></div>
							<div class="wcu-refresh-timeout-manual" style="display: none;"><i style="font-size: 10px;"><?php echo esc_html__( 'The visitor is prompted to manually refresh the widget after a timeout.', 'simple-cloudflare-turnstile' ); ?></i></div>
							<div class="wcu-refresh-timeout-never" style="display: none;"><i style="font-size: 10px;"><?php echo esc_html__( 'The widget shows a timeout message and will not refresh until the page is reloaded.', 'simple-cloudflare-turnstile' ); ?></i></div>
						</td>
					</tr>

					<tr>
						<th scope="row" colspan="2" style="text-align: center; color: #8c8c8c;">
							<?php echo esc_html__('Widget Label', 'simple-cloudflare-turnstile'); ?>
						</th>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Show Widget Label Text', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<input type="checkbox" name="cfturnstile_widget_label_enable" <?php if ( get_option('cfturnstile_widget_label_enable', 0) ) { ?>checked<?php } ?>>
							<i style="font-size: 10px;"><?php echo esc_html__('Display small text above the widget.', 'simple-cloudflare-turnstile'); ?></i>
						</td>
					</tr>
					<tr valign="top" class="cfturnstile-widget-label-text" style="border: 0;">
						<th scope="row" style="padding-top: 0px;">
							<i style="font-size: 10px;">
								<?php echo esc_html__('Leave blank to use the default (localized).', 'simple-cloudflare-turnstile'); ?>
							</i>
						</th>
						<td style="padding-top: 0px;">
							<input type="text" style="width: 100%;" name="cfturnstile_widget_label_text"
							value="<?php echo esc_attr( get_option('cfturnstile_widget_label_text') ); ?>"
							placeholder="<?php echo esc_attr__('Let us know you are human:', 'simple-cloudflare-turnstile'); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row" colspan="2" style="text-align: center; color: #8c8c8c;">
							<?php echo esc_html__('Custom Messages', 'simple-cloudflare-turnstile'); ?>
						</th>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo esc_html__('Custom Error Message', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<textarea type="text" style="width: 202px; margin-bottom: 5px;" name="cfturnstile_error_message"
							placeholder="<?php echo cfturnstile_failed_message(1); ?>"
							/><?php if(get_option('cfturnstile_error_message')) { echo esc_html(get_option('cfturnstile_error_message')); } ?></textarea>
							<br /><i style="font-size: 10px;"><?php echo esc_html__('Shown if the form is submitted without completing the Turnstile challenge. Leave blank to use the default message (localized):', 'simple-cloudflare-turnstile') . ' "' . cfturnstile_failed_message(1) . '"'; ?></i>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('Extra Failure Message', 'simple-cloudflare-turnstile'); ?>
						</th>
						<td>
							<input type="checkbox" name="cfturnstile_failure_message_enable" <?php if (get_option('cfturnstile_failure_message_enable', 0)) { ?>checked<?php } ?>>
						</td>
					</tr>
					<tr valign="top" class="cfturnstile-failure-message" style="border: 0;">
						<th scope="row" style="padding-top: 0px;">
							<i style="font-size: 10px;">
								<?php echo esc_html__('HTML Markup Allowed.', 'simple-cloudflare-turnstile'); ?>
							</i>
						</th>
						<td style="padding-top: 0px;">
							<textarea type="text" style="width: 202px; margin-bottom: 5px;" name="cfturnstile_failure_message" rows="3"
							placeholder="<?php echo esc_html__('Failed to verify you are human. Please contact us if you are having issues.', 'simple-cloudflare-turnstile'); ?>"/><?php if(get_option('cfturnstile_failure_message')) { echo esc_html(get_option('cfturnstile_failure_message')); } ?></textarea>
							<i style="font-size: 10px;"><?php echo esc_html__('This will show a message below the Turnstile widget if they receive the "Failure!" response. Useful to give instructions in the *very rare* case a valid user is being flagged as spam.', 'simple-cloudflare-turnstile'); ?></i>
							<br/><br/>
							<i style="font-size: 10px;"><?php echo esc_html__('Currently it is not possible to edit the actual "Failure!" message shown on the widget.', 'simple-cloudflare-turnstile'); ?></i>
						</td>
					</tr>

					<tr>
						<th scope="row" colspan="2" style="text-align: center; color: #8c8c8c;">
							<?php echo esc_html__('Performance', 'simple-cloudflare-turnstile'); ?>
						</th>
					</tr>

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('Defer Scripts', 'simple-cloudflare-turnstile'); ?>
						</th>
						<td><input style="margin: 5px 0 20px 10px;" type="checkbox" name="cfturnstile_defer_scripts" <?php if (get_option('cfturnstile_defer_scripts', 1)) { ?>checked<?php } ?>>
						<i style="font-size: 10px;"><?php echo esc_html__('When enabled, some javascript files loaded by the plugin will be deferred. You can disable this if it causes any issues with your other optimisations.', 'simple-cloudflare-turnstile'); ?></i>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('Performance Plugin Compatibility', 'simple-cloudflare-turnstile'); ?>
						</th>
						<td>
							<input style="margin: 5px 0 20px 10px;" type="checkbox" name="cfturnstile_perf_compat" <?php if ( get_option('cfturnstile_perf_compat', 1) ) { ?>checked<?php } ?>>
							<i style="font-size: 10px;"><?php echo esc_html__('Adds better compatibility with popular performance/optimization plugins (e.g. WP Rocket, LiteSpeed Cache, Autoptimize, Perfmatters, SG Optimizer) to prevent their JS optimizations from breaking Turnstile. Disable only if this causes issues.', 'simple-cloudflare-turnstile'); ?></i>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('Resource Hint (Preconnect)', 'simple-cloudflare-turnstile'); ?>
						</th>
						<td>
							<input style="margin: 5px 0 20px 10px;" type="checkbox" name="cfturnstile_preconnect" <?php if ( get_option('cfturnstile_preconnect', 0) ) { ?>checked<?php } ?>>
							<i style="font-size: 10px;"><?php echo esc_html__('Adds a preconnect (and DNS prefetch) hint for challenges.cloudflare.com to establish an early connection and improve initial load time. Recommended on high-latency networks or when scripts are deferred. May open a connection even on pages without Turnstile.', 'simple-cloudflare-turnstile'); ?></i>
						</td>
					</tr>
				</table>

			</div>

			<button type="button" class="sct-accordion" id="sct-accordion-whitelist"><?php echo esc_html__('Whitelist Settings', 'simple-cloudflare-turnstile'); ?></button>
			<div class="sct-panel">

				<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('Logged In Users', 'simple-cloudflare-turnstile'); ?>
						</th>
						<td><input style="margin-top: 5px;" type="checkbox" name="cfturnstile_whitelist_users" <?php if (get_option('cfturnstile_whitelist_users')) { ?>checked<?php } ?>>
							<i style="font-size: 10px;"><?php echo esc_html__('When enabled, logged in users will not see the Turnstile challenge. Warning: This can make it possible for attackers to bypass Turnstile if they simply access a logged-in account.', 'simple-cloudflare-turnstile'); ?></i>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><?php echo esc_html__('IP Addresses', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<textarea style="width: 240px;" name="cfturnstile_whitelist_ips"><?php echo sanitize_textarea_field(get_option('cfturnstile_whitelist_ips')); ?></textarea>
							<br /><i style="font-size: 10px;"><?php echo esc_html__('One per line. Enter individual IP addresses (IPv4 or IPv6) or CIDR ranges (e.g. 203.0.113.0/24 or 2001:db8::/32). Use a range if visitors have a dynamic or dual-stack (IPv4/IPv6) address that changes. All visitors with matching IP addresses will not see the Turnstile challenge. Warning: If an attacker knows one of the whitelisted IP addresses, they might be able to spoof that address to bypass Turnstile.', 'simple-cloudflare-turnstile'); ?></i>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><?php echo esc_html__('User Agents', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<textarea style="width: 240px;" name="cfturnstile_whitelist_agents"><?php echo sanitize_textarea_field(get_option('cfturnstile_whitelist_agents')); ?></textarea>
							<br /><i style="font-size: 10px;"><?php echo esc_html__('One per line.  All visitors with listed User Agents will not see the Turnstile challenge. Warning: If an attacker knows one of the whitelisted User Agents, they might be able to spoof that User Agent to bypass Turnstile.', 'simple-cloudflare-turnstile'); ?></i>
						</td>
					</tr>

				</table>

			</div>

			<button type="button" class="sct-accordion" id="sct-accordion-failsafe"><?php echo esc_html__('Failsafe Settings', 'simple-cloudflare-turnstile'); ?></button>
			<div class="sct-panel">

				<p style="margin: 0 0 15px 0;">
					<?php echo esc_html__('Failsafe settings allow form submissions to continue if the Cloudflare Turnstile API is down or unreachable from your server.', 'simple-cloudflare-turnstile'); ?>
				</p>

				<p style="margin: 0 0 15px 0; padding-bottom: 20px; border-bottom: 1px solid #f3f3f3;">
					<?php echo esc_html__('Warning: Enabling failsafe mode may allow spam submissions to get through your forms if Turnstile is down.', 'simple-cloudflare-turnstile'); ?>
				</p>
				
				<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('Enable Failsafe Mode', 'simple-cloudflare-turnstile'); ?>
						</th>
						<td>
							<input type="checkbox" name="cfturnstile_failover" id="cfturnstile_failover" <?php if (get_option('cfturnstile_failover')) { ?>checked<?php } ?>>
						</td>
					</tr>

					<tr valign="top" class="sct-failsafe-options">
						<th scope="row">
							<?php echo esc_html__('Failsafe Type', 'simple-cloudflare-turnstile'); ?>
						</th>
						<td>
							<select name="cfturnstile_failsafe_type" id="cfturnstile_failsafe_type" style="width: 100%;">
								<?php $failsafe_type = get_option('cfturnstile_failsafe_type'); ?>
								<option value="allow" <?php if (!$failsafe_type || $failsafe_type == 'allow') { ?>selected<?php } ?>>
									<?php echo esc_html__('Allow submissions (skip verification)', 'simple-cloudflare-turnstile'); ?>
								</option>
								<option value="recaptcha" <?php if ($failsafe_type == 'recaptcha') { ?>selected<?php } ?>>
									<?php echo esc_html__('Fallback to reCAPTCHA', 'simple-cloudflare-turnstile'); ?>
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<td scope="row" class="sct-failsafe-recaptcha" colspan="2" style="text-align: center; padding-top: 20px;">
							<strong>
							<?php echo esc_html__('You can get reCAPTCHA keys from here:', 'simple-cloudflare-turnstile'); ?> <a href="https://www.google.com/recaptcha/about/" target="_blank"><?php echo esc_html__('https://www.google.com/recaptcha/about/', 'simple-cloudflare-turnstile'); ?></a>
							</strong>
						</td>
					</tr>

					<tr valign="top" class="sct-failsafe-recaptcha">
						<th scope="row"><?php echo esc_html__('reCAPTCHA Site Key (v2)', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<input type="text" style="width: 240px;" name="cfturnstile_recaptcha_site_key" value="<?php echo esc_attr(get_option('cfturnstile_recaptcha_site_key')); ?>" />
						</td>
					</tr>

					<tr valign="top" class="sct-failsafe-recaptcha">
						<th scope="row"><?php echo esc_html__('reCAPTCHA Secret Key (v2)', 'simple-cloudflare-turnstile'); ?></th>
						<td>
							<input type="text" style="width: 240px;" name="cfturnstile_recaptcha_secret_key" value="<?php echo esc_attr(get_option('cfturnstile_recaptcha_secret_key')); ?>" />
						</td>
					</tr>

				</table>

			</div>

			<hr style="margin: 40px 0 10px 0;">

			<div class="sct-integrations">

			<table class="form-table" style="margin-bottom: -35px;">

				<tr valign="top">
					<th scope="row">
						<span style="font-size: 19px;"><?php echo esc_html__('Enable Turnstile on your forms:', 'simple-cloudflare-turnstile'); ?></span>
						<p><?php echo esc_html__('Select the dropdown for each integration, and choose when specific forms you want to enable Turnstile on.', 'simple-cloudflare-turnstile'); ?></p>
					</th>
				</tr>

			</table>

			
			<button type="button" class="sct-accordion" id="sct-accordion-wordpress"><?php echo esc_html__('Default WordPress Forms', 'simple-cloudflare-turnstile'); ?></button>
			<div class="sct-panel">

				<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('WordPress Login', 'simple-cloudflare-turnstile'); ?> <a href="#" class="cfturnstile_toggle_login" style="font-size: 10px; text-decoration: none; color: #333;">&#9660;</a>
							<span id="cfturnstile_login_only_option" style="display: none;" title="<?php echo esc_html__('Enable this option to only enable on default WordPress login form at wp-login.php', 'simple-cloudflare-turnstile'); ?>">
							<br/><br/>
								<label style="float: left; margin: -5px 10px 0px 0; font-weight: 600; font-size: 10px;" for="cfturnstile_login_only"><?php echo esc_html__('Only enable on default wp-login.php page', 'simple-cloudflare-turnstile'); ?></label>
								<input style="float: left; transform: scale(0.75); margin-top: -7px; margin-left: -5px;"
								type="checkbox" name="cfturnstile_login_only" <?php if (get_option('cfturnstile_login_only')) { ?>checked<?php } ?>>
							</span>
						</th>
						<td><input type="checkbox" name="cfturnstile_login" <?php if (get_option('cfturnstile_login')) { ?>checked<?php } ?>></td>
					</tr>
					<script>
					jQuery(document).ready(function() {
						jQuery('.cfturnstile_toggle_login').click(function(e) {
							e.preventDefault();
							jQuery('#cfturnstile_login_only_option').toggle();
						});
					});
					</script>

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('WordPress Register', 'simple-cloudflare-turnstile'); ?> <a href="#" class="cfturnstile_toggle_register" style="font-size: 10px; text-decoration: none; color: #333;">&#9660;</a>
							<span id="cfturnstile_register_only_option" style="display: none;" title="<?php echo esc_html__('Enable this option to only enable on default WordPress register form at wp-login.php?action=register', 'simple-cloudflare-turnstile'); ?>">
							<br/><br/>
								<label style="float: left; margin: -5px 10px 0px 0; font-weight: 600; font-size: 10px;" for="cfturnstile_register_only"><?php echo esc_html__('Only enable on default wp-login.php page', 'simple-cloudflare-turnstile'); ?></label>
								<input style="float: left; transform: scale(0.75); margin-top: -7px; margin-left: -5px;"
								type="checkbox" name="cfturnstile_register_only" <?php if (get_option('cfturnstile_register_only')) { ?>checked<?php } ?>>
							</span>
						</th>
						<td><input type="checkbox" name="cfturnstile_register" <?php if (get_option('cfturnstile_register')) { ?>checked<?php } ?>></td>
					</tr>
					<script>
					jQuery(document).ready(function() {
						jQuery('.cfturnstile_toggle_register').click(function(e) {
							e.preventDefault();
							jQuery('#cfturnstile_register_only_option').toggle();
						});
					});
					</script>

					<tr valign="top">
						<th scope="row">
							<?php echo esc_html__('WordPress Reset Password', 'simple-cloudflare-turnstile'); ?>
						</th>
						<td><input type="checkbox" name="cfturnstile_reset" <?php if (get_option('cfturnstile_reset')) { ?>checked<?php } ?>></td>
					</tr>

					<tr valign="top" style="border: 0;">
						<th scope="row">
							<?php echo esc_html__('WordPress Comment', 'simple-cloudflare-turnstile'); ?> <a href="#" class="cfturnstile_toggle_comments" style="font-size: 10px; text-decoration: none; color: #333;">&#9660;</a>
							<span id="cfturnstile_ajax_comments_option" style="display: none;" title="<?php echo esc_html__('Enable this if you are using an AJAX based comments form plugin or theme.', 'simple-cloudflare-turnstile'); ?>">
							<br/><br/>
								<label style="float: left; margin: -5px 10px 0px 0; font-weight: 600; font-size: 10px;" for="cfturnstile_ajax_comments"><?php echo esc_html__('AJAX comments form?', 'simple-cloudflare-turnstile'); ?></label>
								<input style="float: left; transform: scale(0.75); margin-top: -7px; margin-left: -5px;"
								type="checkbox" name="cfturnstile_ajax_comments"
								<?php if(!cft_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php') && !cft_is_plugin_active('wp-ajaxify-comments/wp-ajaxify-comments.php')) { ?>
								<?php if (get_option('cfturnstile_ajax_comments')) { ?>checked<?php } ?>>
								<?php } else { ?>checked disabled<?php } ?>
							</span>
						</th>
						<td>
							<input type="checkbox" name="cfturnstile_comment" <?php if (get_option('cfturnstile_comment')) { ?>checked<?php } ?>>
							<?php if (cft_is_plugin_active('jetpack/jetpack.php')) { ?>
								<br /><i style="font-size: 10px;"><?php echo esc_html__('Due to Jetpack limitations, this does NOT currently work with Jetpack comments form enabled.', 'simple-cloudflare-turnstile'); ?></i>
							<?php } ?>
							<?php if (cft_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php')) { ?>
								<i style="font-size: 11px;"><?php echo esc_html__('Compatible with wpDiscuz!', 'simple-cloudflare-turnstile'); ?></i>
							<?php } ?>
						</td>
					</tr>
					<script>
						jQuery(document).ready(function() {
							jQuery('.cfturnstile_toggle_comments').click(function(e) {
								e.preventDefault();
								jQuery('#cfturnstile_ajax_comments_option').toggle();
							});
						});
					</script>

				</table>

			</div>

			<?php $not_installed = array(); ?>

			<?php // WooCommerce
			if (cft_is_plugin_active('woocommerce/woocommerce.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('WooCommerce Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('WooCommerce Login', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_woo_login" <?php if (get_option('cfturnstile_woo_login')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('WooCommerce Register', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_woo_register" <?php if (get_option('cfturnstile_woo_register')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('WooCommerce Reset Password', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_woo_reset" <?php if (get_option('cfturnstile_woo_reset')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('WooCommerce Account Details', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_woo_account" <?php if (get_option('cfturnstile_woo_account')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('WooCommerce Checkout', 'simple-cloudflare-turnstile'); ?>
								<br /><br />
								- <?php echo esc_html__('Guest Checkout Only', 'simple-cloudflare-turnstile'); ?>
								<br /><br />
								- <?php echo esc_html__('Widget Location', 'simple-cloudflare-turnstile'); ?>
								<br/><br/>
							</th>
							<td>
								<input style="margin-top: 5px;" type="checkbox" name="cfturnstile_woo_checkout" <?php if (get_option('cfturnstile_woo_checkout')) { ?>checked<?php } ?>>
								<br /><br />
								<input style="margin-top: 5px;" type="checkbox" name="cfturnstile_guest_only" <?php if (get_option('cfturnstile_guest_only')) { ?>checked<?php } ?>>
								<br /><br />
								<select name="cfturnstile_woo_checkout_pos">
									<option value="beforepay" <?php if (!get_option('cfturnstile_woo_checkout_pos') || get_option('cfturnstile_woo_checkout_pos') == "beforepay") { ?>selected<?php } ?>>
										<?php esc_html_e('Before Payment', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="afterpay" <?php if (get_option('cfturnstile_woo_checkout_pos') == "afterpay") { ?>selected<?php } ?>>
										<?php esc_html_e('After Payment', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="beforesubmit" <?php if (get_option('cfturnstile_woo_checkout_pos') == "beforesubmit") { ?>selected<?php } ?>>
										<?php esc_html_e('Before Pay Button', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="beforebilling" <?php if (get_option('cfturnstile_woo_checkout_pos') == "beforebilling") { ?>selected<?php } ?>>
										<?php esc_html_e('Before Billing', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="afterbilling" <?php if (get_option('cfturnstile_woo_checkout_pos') == "afterbilling") { ?>selected<?php } ?>>
										<?php esc_html_e('After Billing', 'simple-cloudflare-turnstile'); ?>
									</option>
								</select>
							</td>
						</tr>

						<tr valign="top" style="border-bottom: 1px solid #f3f3f3;">
							<th scope="row">
								<?php echo esc_html__('WooCommerce Pay for Order', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_woo_checkout_pay" <?php if (get_option('cfturnstile_woo_checkout_pay')) { ?>checked<?php } ?>></td>
						</tr>

						<?php
						// Visual-only WooPayments Express indicators (not saved).
						$wcpay_active   = function_exists('cft_is_plugin_active') ? cft_is_plugin_active('woocommerce-payments/woocommerce-payments.php') : false;
						$wcstripe_active = function_exists('cft_is_plugin_active') ? cft_is_plugin_active('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php') : false;
						if ( $wcpay_active ) {
						?>
						<tr valign="top">
						<td colspan="2"
						style="padding: 20px 0 0 0;">
							<i style="font-size: 10px;">
								<?php echo esc_html__('Note: Currently Turnstile is not able to perform spam checks for some "Express Checkout" payment methods (e.g. PayPal, Google Pay, Apple Pay, Amazon Pay) and will skip them automatically.', 'simple-cloudflare-turnstile'); ?>
							</i>
							<br/>
						</td>
						<?php } ?>

					</table>

					<?php if ( class_exists( 'WooCommerce' ) ) { ?>

						<?php $available_gateways = WC()->payment_gateways->get_available_payment_gateways(); ?>

						<?php if(!empty($available_gateways)) { ?>

							<br/>

							<p style="font-weight: 600;">
								<?php echo esc_html__('Payment Methods to Skip', 'simple-cloudflare-turnstile'); ?> <a href="#" class="cfturnstile_toggle_skip_methods" style="font-size: 10px; text-decoration: none; color: #333;">&#9660;</a>
							</p>
							<script>
								jQuery(document).ready(function() {
									jQuery('.cfturnstile_toggle_skip_methods').click(function(e) {
										e.preventDefault();
										jQuery('#toggleContentSkipMethods').toggle();
									});
								});
							</script>

							<div id="toggleContentSkipMethods" style="display: none;"> <!-- Initially hidden -->
							
								<i style="font-size: 10px;">
									<?php echo esc_html__("If selected below, Turnstile check will not be run for that specific payment method.", 'simple-cloudflare-turnstile'); ?>
								</i>

								<?php
								$selected_payment_methods = get_option('cfturnstile_selected_payment_methods', array());
								if(!$selected_payment_methods) $selected_payment_methods = array();
								if(!empty($available_gateways)) { ?>
								<div style="margin-top: 10px; max-width: 200px;">
								<?php foreach ( $available_gateways as $gateway ) : ?>
								<p>
									<input type="checkbox" name="cfturnstile_selected_payment_methods[]" style="float: none; margin-top: 2px;"
									value="<?php echo esc_attr( $gateway->id ); ?>" <?php echo in_array( $gateway->id, $selected_payment_methods, true ) ? 'checked' : ''; ?> >
									<label><?php echo esc_html( $gateway->get_title() ); ?></label>
								</p>
								<?php endforeach; ?>
								</div>
								<?php } ?>

								<?php
								// Visual-only WooPayments Express indicators (not saved).
								$wcpay_active   = function_exists('cft_is_plugin_active') ? cft_is_plugin_active('woocommerce-payments/woocommerce-payments.php') : false;
								if ( $wcpay_active ) {
									$wcpay_settings = get_option( 'woocommerce_woocommerce_payments_settings', array() );
									$payment_request_enabled = false;
									// Older/newer keys that may indicate payment request buttons are enabled.
									if ( isset( $wcpay_settings['payment_request'] ) ) {
										$payment_request_enabled = ( 'yes' === $wcpay_settings['payment_request'] );
									} elseif ( isset( $wcpay_settings['payment_request_enabled'] ) ) {
										$payment_request_enabled = ( 'yes' === $wcpay_settings['payment_request_enabled'] );
									}
									$upe_ids = array();
									if ( isset( $wcpay_settings['enabled_payment_method_ids'] ) ) {
										$upe_ids = (array) $wcpay_settings['enabled_payment_method_ids'];
									} elseif ( isset( $wcpay_settings['upe_enabled_payment_method_ids'] ) ) {
										$upe_ids = (array) $wcpay_settings['upe_enabled_payment_method_ids'];
									}
									$link_enabled = in_array( 'link', $upe_ids, true );
									?>
									<hr style="margin: 12px 0 12px 0; border: 0; border-top: 1px solid #f3f3f3;" />
									<p style="font-weight: 600; margin: 8px 0 4px 0;">
										<?php echo esc_html__( 'WooPayments Express', 'simple-cloudflare-turnstile' ); ?>
									</p>
									<div style="max-width: 200px;">
										<p>
											<input type="checkbox" disabled <?php checked( $payment_request_enabled ); ?>
											style="float: none; margin-top: 2px;" />
											<label><?php echo esc_html__( 'Apple Pay', 'simple-cloudflare-turnstile' ); ?></label>
										</p>
										<p>
											<input type="checkbox" disabled <?php checked( $payment_request_enabled ); ?>
											style="float: none; margin-top: 2px;" />
											<label><?php echo esc_html__( 'Google Pay', 'simple-cloudflare-turnstile' ); ?></label>
										</p>
										<p>
											<input type="checkbox" disabled <?php checked( $link_enabled ); ?>
											style="float: none; margin-top: 2px;" />
											<label><?php echo esc_html__( 'Link by Stripe', 'simple-cloudflare-turnstile' ); ?></label>
										</p>
									</div>
								<?php } ?>

							</div>

					<?php } ?>

				<?php } ?>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">' . esc_html__('WooCommerce', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Sunshine Photo Cart
			if (cft_is_plugin_active('sunshine-photo-cart/sunshine-photo-cart.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Sunshine Photo Cart', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Sunshine Login', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_sunshine_login" <?php if (get_option('cfturnstile_sunshine_login')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Sunshine Register', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_sunshine_register" <?php if (get_option('cfturnstile_sunshine_register')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Sunshine Reset Password', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_sunshine_reset" <?php if (get_option('cfturnstile_sunshine_reset')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Sunshine Checkout', 'simple-cloudflare-turnstile'); ?>
								<br /><br />
								- <?php echo esc_html__('Guest Checkout Only', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td>
								<input style="margin-top: 5px;" type="checkbox" name="cfturnstile_sunshine_checkout" <?php if (get_option('cfturnstile_sunshine_checkout')) { ?>checked<?php } ?>>
								<br /><br />
								<input style="margin-top: 5px;" type="checkbox" name="cfturnstile_sunshine_guest_only" <?php if (get_option('cfturnstile_sunshine_guest_only')) { ?>checked<?php } ?>>
							</td>
						</tr>

					</table>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://www.sunshinephotocart.com/" target="_blank">' . esc_html__('Sunshine Photo Cart', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // EDD
			if (cft_is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') || cft_is_plugin_active('easy-digital-downloads-pro/easy-digital-downloads.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Easy Digital Downloads', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('EDD Checkout', 'simple-cloudflare-turnstile'); ?>
								<br /><br />
								- <?php echo esc_html__('Guest Checkout Only', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td>
								<input type="checkbox" name="cfturnstile_edd_checkout" <?php if (get_option('cfturnstile_edd_checkout')) { ?>checked<?php } ?>>
								<br /><br />
								<input type="checkbox" name="cfturnstile_edd_guest_only" <?php if (get_option('cfturnstile_edd_guest_only')) { ?>checked<?php } ?>>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('EDD Login', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_edd_login" <?php if (get_option('cfturnstile_edd_login')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('EDD Register', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_edd_register" <?php if (get_option('cfturnstile_edd_register')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/easy-digital-downloads/" target="_blank">' . esc_html__('Easy Digital Downloads', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Paid Memberships PRO
			if (cft_is_plugin_active('paid-memberships-pro/paid-memberships-pro.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Paid Memberships Pro', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Checkout / Registration', 'simple-cloudflare-turnstile'); ?>
								<br /><br />
								- <?php echo esc_html__('Guest Checkout Only', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td>
								<input type="checkbox" name="cfturnstile_pmp_checkout" <?php if (get_option('cfturnstile_pmp_checkout')) { ?>checked<?php } ?>>
								<br /><br />
								<input type="checkbox" name="cfturnstile_pmp_guest_only" <?php if (get_option('cfturnstile_pmp_guest_only')) { ?>checked<?php } ?>>
							</td>
						</tr>

						<script>
						jQuery(document).ready(function(){
							jQuery("input[name='cfturnstile_login']").change(function(){
								if(jQuery("input[name='cfturnstile_login']").is(':checked')){
									jQuery('#cfturnstile_pmp_login').prop('checked', true);
								} else {
									jQuery('#cfturnstile_pmp_login').prop('checked', false);
								}
							});
						});
						</script>
						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Login Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type='checkbox' name='cfturnstile_pmp_login' id='cfturnstile_pmp_login' <?php if (get_option('cfturnstile_login')) { ?>checked<?php } ?>
							title='<?php echo esc_html__('Edit via "WordPress Login" option in the "Default WordPress Forms" settings.', 'simple-cloudflare-turnstile'); ?>' disabled></td>
						</tr>

						<!-- Lost Password -->
						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Lost Password Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type='checkbox' name='cfturnstile_wpuf_reset' id='cfturnstile_wpuf_reset'
							title='<?php echo esc_html__('Currently Turnstile can not be implemented on the lost password form when PMP is installed.', 'simple-cloudflare-turnstile'); ?>'
							disabled></td>
						</tr>
						<!-- Set name="cfturnstile_reset" to disabled and unchecked -->
						<script>
						jQuery(document).ready(function(){
							jQuery("input[name='cfturnstile_reset']").prop('disabled', true);
							jQuery("input[name='cfturnstile_reset']").prop('checked', false);
							jQuery("input[name='cfturnstile_reset']").attr('title', '<?php echo esc_html__('Currently Turnstile can not be implemented on the lost password form when PMP is installed.', 'simple-cloudflare-turnstile'); ?>');
						});
						</script>
						<!-- Show X inside checkbox -->
						<style>
						#cfturnstile_wpuf_reset:after, input[name='cfturnstile_reset']:after {
							content: "X";
							color: #333;
							font-weight: bold;
							font-size: 15px;
							position: absolute;
							margin-left: -5px;
							margin-top: 7px;
						}
						</style>
						
					</table>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://en-gb.wordpress.org/plugins/paid-memberships-pro/" target="_blank">' . esc_html__('Paid Memberships PRO', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Contact Form 7
			if (cft_is_plugin_active('contact-form-7/wp-contact-form-7.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Contact Form 7', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all CF7 Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_cf7_all" <?php if (get_option('cfturnstile_cf7_all')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<br />

					<?php echo esc_html__('To add Turnstile to individual Contact Form 7 forms, simply add this shortcode to any of your forms (in the form editor):', 'simple-cloudflare-turnstile'); ?>
					<br /><span style="color: red; font-weight: bold;">[cf7-simple-turnstile]</span>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">' . esc_html__('Contact Form 7', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // WPForms
			if (cft_is_plugin_active('wpforms-lite/wpforms.php') || cft_is_plugin_active('wpforms/wpforms.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('WPForms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all WPForms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_wpforms" <?php if (get_option('cfturnstile_wpforms')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added before/after the submit button, on ALL your forms created with WPForms.', 'simple-cloudflare-turnstile'); ?>
					<?php echo esc_html__('Note: WPForms has an option to configure Turnstile on its own Settings page "CAPTCHA" tab. You should only enable it in one place, either here -OR- in those settings.', 'simple-cloudflare-turnstile'); ?>

					<table class="form-table" style="margin-bottom: -15px;">

						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Widget Location', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<select name="cfturnstile_wpforms_pos">
									<option value="before" <?php if (!get_option('cfturnstile_wpforms_pos') || get_option('cfturnstile_wpforms_pos') == "before") { ?>selected<?php } ?>>
										<?php esc_html_e('Before Button', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="after" <?php if (get_option('cfturnstile_wpforms_pos') == "after") { ?>selected<?php } ?>>
										<?php esc_html_e('After Button', 'simple-cloudflare-turnstile'); ?>
									</option>
								</select>
							</td>
						</tr>

					</table>

					<table class="form-table" style="margin-bottom: -10px;">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Disabled Form IDs', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<input type="text" name="cfturnstile_wpforms_disable" value="<?php echo esc_attr(get_option('cfturnstile_wpforms_disable')); ?>" />
							</td>
						</tr>
					</table>
					<i style="font-size: 10px;">
						<?php echo sprintf(__('If you want to DISABLE the Turnstile widget on certain forms, enter the %s ID in this field.', 'simple-cloudflare-turnstile'), 'WPForms Form'); ?>
						<?php echo esc_html__('Separate each ID with a comma, for example: 5,10', 'simple-cloudflare-turnstile'); ?>
					</i>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/wpforms-lite/" target="_blank">' . esc_html__('WPForms', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Gravity Forms
			if (cft_is_plugin_active('gravityforms/gravityforms.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Gravity Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all Gravity Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_gravity" <?php if (get_option('cfturnstile_gravity')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added before/after the submit button, on ALL your forms created with Gravity Forms.', 'simple-cloudflare-turnstile'); ?>

					<table class="form-table" style="margin-bottom: -15px;">

						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Widget Location', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<select name="cfturnstile_gravity_pos">
									<option value="before" <?php if (!get_option('cfturnstile_gravity_pos') || get_option('cfturnstile_gravity_pos') == "before") { ?>selected<?php } ?>>
										<?php esc_html_e('Before Button', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="after" <?php if (get_option('cfturnstile_gravity_pos') == "after") { ?>selected<?php } ?>>
										<?php esc_html_e('After Button', 'simple-cloudflare-turnstile'); ?>
									</option>
								</select>
							</td>
						</tr>

					</table>

					<table class="form-table" style="margin-bottom: -10px;">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Disabled Form IDs', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<input type="text" name="cfturnstile_gravity_disable" value="<?php echo esc_attr(get_option('cfturnstile_gravity_disable')); ?>" />
							</td>
						</tr>
					</table>
					<i style="font-size: 10px;">
						<?php echo sprintf(__('If you want to DISABLE the Turnstile widget on certain forms, enter the %s ID in this field.', 'simple-cloudflare-turnstile'), 'Gravity Form'); ?>
						<?php echo esc_html__('Separate each ID with a comma, for example: 5,10', 'simple-cloudflare-turnstile'); ?>
					</i>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://www.gravityforms.com/?utm_source=simplecloudflareturnstile" target="_blank">' . esc_html__('Gravity Forms', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Fluent Forms
			if (cft_is_plugin_active('fluentform/fluentform.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Fluent Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all Fluent Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_fluent" <?php if (get_option('cfturnstile_fluent')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added above the submit button, on ALL your forms created with Fluent Forms.', 'simple-cloudflare-turnstile'); ?>

					<table class="form-table" style="margin-bottom: -10px;">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Disabled Form IDs', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<input type="text" name="cfturnstile_fluent_disable" value="<?php echo esc_attr(get_option('cfturnstile_fluent_disable')); ?>" />
							</td>
						</tr>
					</table>
					<i style="font-size: 10px;">
						<?php echo sprintf(__('If you want to DISABLE the Turnstile widget on certain forms, enter the %s ID in this field.', 'simple-cloudflare-turnstile'), 'Fluent Form'); ?>
						<?php echo esc_html__('Separate each ID with a comma, for example: 5,10', 'simple-cloudflare-turnstile'); ?>
					</i>

				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/fluentform/" target="_blank">' . esc_html__('Fluent Forms', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // SureForms
			if (cft_is_plugin_active('sureforms/sureforms.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('SureForms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all SureForms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_sureforms" <?php if (get_option('cfturnstile_sureforms')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added above the submit button, on ALL your forms created with SureForms.', 'simple-cloudflare-turnstile'); ?>

					<table class="form-table" style="margin-bottom: -10px;">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Disabled Form IDs', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<input type="text" name="cfturnstile_sureforms_disable" value="<?php echo esc_attr(get_option('cfturnstile_sureforms_disable')); ?>" />
							</td>
						</tr>
					</table>
					<i style="font-size: 10px;">
						<?php echo sprintf(__('If you want to DISABLE the Turnstile widget on certain forms, enter the %s ID in this field.', 'simple-cloudflare-turnstile'), 'SureForms'); ?>
						<?php echo esc_html__('Separate each ID with a comma, for example: 5,10', 'simple-cloudflare-turnstile'); ?>
					</i>

				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/sureforms/" target="_blank">' . esc_html__('SureForms', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Jetpack Forms
			if (cft_is_plugin_active('jetpack/jetpack.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Jetpack Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all Jetpack Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_jetpack" <?php if (get_option('cfturnstile_jetpack')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added after the submit button, on ALL your forms created with Jetpack Forms.', 'simple-cloudflare-turnstile'); ?>
				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/jetpack/" target="_blank">' . esc_html__('Jetpack Forms', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Formidable Forms
			if (cft_is_plugin_active('formidable/formidable.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Formidable Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all Formidable Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_formidable" <?php if (get_option('cfturnstile_formidable')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added above the submit button, on ALL your forms created with Formidable Forms.', 'simple-cloudflare-turnstile'); ?>

					<table class="form-table" style="margin-bottom: -15px;">

						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Widget Location', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<select name="cfturnstile_formidable_pos">
									<option value="before" <?php if (!get_option('cfturnstile_formidable_pos') || get_option('cfturnstile_formidable_pos') == "before") { ?>selected<?php } ?>>
										<?php esc_html_e('Before Button', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="after" <?php if (get_option('cfturnstile_formidable_pos') == "after") { ?>selected<?php } ?>>
										<?php esc_html_e('After Button', 'simple-cloudflare-turnstile'); ?>
									</option>
								</select>
							</td>
						</tr>

					</table>
				
					<table class="form-table" style="margin-bottom: -10px;">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Disabled Form IDs', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<input type="text" name="cfturnstile_formidable_disable" value="<?php echo esc_html(get_option('cfturnstile_formidable_disable')); ?>" />
							</td>
						</tr>
					</table>
					<i style="font-size: 10px;">
						<?php echo sprintf(__('If you want to DISABLE the Turnstile widget on certain forms, enter the %s ID in this field.', 'simple-cloudflare-turnstile'), 'Formidable Form'); ?>
						<?php echo esc_html__('Separate each ID with a comma, for example: 5,10', 'simple-cloudflare-turnstile'); ?>
					</i>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/formidable/" target="_blank">' . esc_html__('Formidable', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>
			
			<?php // Forminator Forms
			if (cft_is_plugin_active('forminator/forminator.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Forminator Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all Forminator Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_forminator" <?php if (get_option('cfturnstile_forminator')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added above the submit button, on ALL your forms created with Forminator Forms.', 'simple-cloudflare-turnstile'); ?>

					<table class="form-table" style="margin-bottom: -15px;">

						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Widget Location', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<select name="cfturnstile_forminator_pos">
									<option value="before" <?php if (!get_option('cfturnstile_forminator_pos') || get_option('cfturnstile_forminator_pos') == "before") { ?>selected<?php } ?>>
										<?php esc_html_e('Before Button', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="after" <?php if (get_option('cfturnstile_forminator_pos') == "after") { ?>selected<?php } ?>>
										<?php esc_html_e('After Button', 'simple-cloudflare-turnstile'); ?>
									</option>
								</select>
							</td>
						</tr>

					</table>

					<table class="form-table" style="margin-bottom: -10px;">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Disabled Form IDs', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<input type="text" name="cfturnstile_forminator_disable" value="<?php echo esc_attr(get_option('cfturnstile_forminator_disable')); ?>" />
							</td>
						</tr>
					</table>
					<i style="font-size: 10px;">
						<?php echo sprintf(__('If you want to DISABLE the Turnstile widget on certain forms, enter the %s ID in this field.', 'simple-cloudflare-turnstile'), 'Forminator Form'); ?>
						<?php echo esc_html__('Separate each ID with a comma, for example: 5,10', 'simple-cloudflare-turnstile'); ?>
					</i>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/forminator/" target="_blank">' . esc_html__('Forminator', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // WS Form
			if (cft_is_plugin_active('ws-form/ws-form.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('WS Form', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<p>
						<?php echo esc_html__('Currently WS Form is not supported by this plugin, however their plugin does have its own Turnstile addon.', 'simple-cloudflare-turnstile'); ?>
						<a href="https://wsform.com/knowledgebase/turnstile/?utm_source=simplecloudflareturnstile" target="_blank"><?php echo esc_html__('Click here for more information.', 'simple-cloudflare-turnstile'); ?></a>
					</p>

				</div>
			<?php
			}
			?>

			<?php // Ninja Forms
			if (cft_is_plugin_active('ninja-forms/ninja-forms.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Ninja Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<p>
						<?php echo esc_html__('Currently Ninja Forms is not supported by this plugin.', 'simple-cloudflare-turnstile'); ?>
					</p>

				</div>
			<?php
			}
			?>

			<?php // Elementor Forms
			if ( cft_is_plugin_active('elementor-pro/elementor-pro.php') || cft_is_plugin_active('pro-elements/pro-elements.php') ) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Elementor Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable Elementor Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_elementor" <?php if (get_option('cfturnstile_elementor')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added to your Elementor Pro forms. Use the options below to control where scripts are loaded and where the widget appears.', 'simple-cloudflare-turnstile'); ?>

					<table class="form-table" style="margin-bottom: -15px;">

						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Widget Location', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<select name="cfturnstile_elementor_pos">
									<option value="before" <?php if (!get_option('cfturnstile_elementor_pos') || get_option('cfturnstile_elementor_pos') == "before") { ?>selected<?php } ?>>
										<?php esc_html_e('Before Button', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="after" <?php if (get_option('cfturnstile_elementor_pos') == "after") { ?>selected<?php } ?>>
										<?php esc_html_e('After Button', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="afterform" <?php if (get_option('cfturnstile_elementor_pos') == "afterform") { ?>selected<?php } ?>>
										<?php esc_html_e('After Form', 'simple-cloudflare-turnstile'); ?>
									</option>
								</select>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Alignment', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<select name="cfturnstile_elementor_align">
									<option value="left" <?php if (!get_option('cfturnstile_elementor_align') || get_option('cfturnstile_elementor_align') == "left") { ?>selected<?php } ?>>
										<?php esc_html_e('Left', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="center" <?php if (get_option('cfturnstile_elementor_align') == "center") { ?>selected<?php } ?>>
										<?php esc_html_e('Center', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="right" <?php if (get_option('cfturnstile_elementor_align') == "right") { ?>selected<?php } ?>>
										<?php esc_html_e('Right', 'simple-cloudflare-turnstile'); ?>
									</option>
								</select>
							</td>
						</tr>

					</table>

					<table class="form-table" style="margin-bottom: -10px;">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Load Scripts', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<!-- Scope options -->
								<div id="cfturnstile-elementor-global-controls" style="margin-top:5px;">
									<?php 
									$scope = get_option('cfturnstile_elementor_global_scope', '');
									if ($scope === '') { $scope = 'all'; }
									?>
									<label for="cfturnstile_elementor_global_scope" style="display:block; margin-bottom:6px;">
										<?php echo esc_html__('Load scripts on:', 'simple-cloudflare-turnstile'); ?>
									</label>
									<select name="cfturnstile_elementor_global_scope" id="cfturnstile_elementor_global_scope"
									style="width:100%; max-width:400px; margin-bottom: 5px;">
										<option value="all" <?php if ($scope === 'all') { ?>selected<?php } ?>><?php echo esc_html__('All pages', 'simple-cloudflare-turnstile'); ?></option>
										<option value="autodetect" <?php if ($scope === 'autodetect') { ?>selected<?php } ?>><?php echo esc_html__('Autodetect pages with forms', 'simple-cloudflare-turnstile'); ?></option>
										<option value="specific" <?php if ($scope === 'specific') { ?>selected<?php } ?>><?php echo esc_html__('Enter specific page IDs', 'simple-cloudflare-turnstile'); ?></option>
									</select>
									<div class="cfturnstile-elementor-scope-description" id="cfturnstile-elementor-scope-all" style="margin-top:6px; font-size:10px; font-style: italic; <?php if ($scope !== 'all') { ?>display:none;<?php } ?>">
										<?php echo esc_html__('Loads scripts on all pages. Use this if you use Elementor popups across your site.', 'simple-cloudflare-turnstile'); ?>
									</div>
									<div class="cfturnstile-elementor-scope-description" id="cfturnstile-elementor-scope-autodetect" style="margin-top:6px; font-size:10px; font-style: italic; <?php if ($scope !== 'autodetect') { ?>display:none;<?php } ?>">
										<?php echo esc_html__('Loads scripts only on pages that include an Elementor Form or Login widget. Popups or global templates may not be detected.', 'simple-cloudflare-turnstile'); ?>
									</div>
									<div class="cfturnstile-elementor-scope-description" id="cfturnstile-elementor-scope-specific" style="margin-top:6px; font-size:10px; font-style: italic; <?php if ($scope !== 'specific') { ?>display:none;<?php } ?>">
										<?php echo esc_html__('Loads scripts only on the page IDs you enter below. Leave the list empty to load nowhere.', 'simple-cloudflare-turnstile'); ?>
									</div>

									<div id="cfturnstile-elementor-global-pages-wrap" class="cfturnstile-elementor-global-pages" style="<?php if ($scope !== 'specific') { ?>display:none;<?php } ?>">
										<label for="cfturnstile_elementor_global_pages" style="display:block; margin-bottom:4px; font-size:12px;">
											<?php echo esc_html__('Page IDs (comma-separated)', 'simple-cloudflare-turnstile'); ?>:
										</label>
										<input type="text" name="cfturnstile_elementor_global_pages" id="cfturnstile_elementor_global_pages" value="<?php echo esc_attr( get_option('cfturnstile_elementor_global_pages', '') ); ?>" placeholder="e.g. 12,34,56" style="width:100%; max-width:400px;">
									</div>
								</div>
							</td>
						</tr>
					</table>

					<!-- Notice to disable element caching -->
					<div>
						<p style="font-style: italic;">
							<span class="dashicons dashicons-warning" style="margin-top: 2px;"></span> <?php echo wp_kses_post( sprintf( 
								__('If the Turnstile widget is not showing, disable <a href="%s" target="_blank">Element Caching</a> for the Elementor form on your page, or set "Load scripts on" to "All pages".', 'simple-cloudflare-turnstile'),
								'https://elementor.com/help/element-caching-help/' ) ); ?>
							</p>
					</div>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://elementor.com/features/form-builder/?utm_source=simplecloudflareturnstile" target="_blank">' . esc_html__('Elementor Forms', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>
	
			<?php // Mailchimp for WordPress
			if (cft_is_plugin_active('mailchimp-for-wp/mailchimp-for-wp.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('MC4WP: Mailchimp for WordPress', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<?php echo esc_html__('To add Turnstile to Mailchimp for WordPress, simply add this shortcode to any of your forms (in the form editor):', 'simple-cloudflare-turnstile'); ?>
					<br /><span style="color: red; font-weight: bold;">[mc4wp-simple-turnstile]</span>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/mailchimp-for-wp/" target="_blank">' . esc_html__('Mailchimp for WordPress', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // MailPoet
			if (cft_is_plugin_active('mailpoet/mailpoet.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('MailPoet', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all MailPoet Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_mailpoet" <?php if (get_option('cfturnstile_mailpoet')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added above the submit button, on ALL your forms created with MailPoet.', 'simple-cloudflare-turnstile'); ?>

				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/mailpoet/" target="_blank">' . esc_html__('MailPoet', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Kadence Forms
			if (cft_is_plugin_active('kadence-blocks/kadence-blocks.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Kadence Forms', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Enable on all Kadence Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_kadence" <?php if (get_option('cfturnstile_kadence')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

					<?php echo esc_html__('When enabled, Turnstile will be added above the submit button, on ALL your forms created with Kadence Forms.', 'simple-cloudflare-turnstile'); ?>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/kadence-blocks/" target="_blank">' . esc_html__('Kadence Forms', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // BuddyPress
			if (cft_is_plugin_active('buddypress/bp-loader.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('BuddyPress', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('BuddyPress Register', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_bp_register" <?php if (get_option('cfturnstile_bp_register')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/buddypress/" target="_blank">' . esc_html__('BuddyPress', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // bbPress
			if (cft_is_plugin_active('bbpress/bbpress.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('bbPress', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('bbPress Create Topic', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_bbpress_create" <?php if (get_option('cfturnstile_bbpress_create')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('bbPress Reply', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_bbpress_reply" <?php if (get_option('cfturnstile_bbpress_reply')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row"><?php echo esc_html__('Alignment', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<select name="cfturnstile_bbpress_align">
									<option value="left" <?php if (!get_option('cfturnstile_bbpress_align') || get_option('cfturnstile_bbpress_align') == "left") { ?>selected<?php } ?>>
										<?php esc_html_e('Left', 'simple-cloudflare-turnstile'); ?>
									</option>
									<option value="right" <?php if (get_option('cfturnstile_bbpress_align') == "right") { ?>selected<?php } ?>>
										<?php esc_html_e('Right', 'simple-cloudflare-turnstile'); ?>
									</option>
								</select>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Guest Users Only', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_bbpress_guest_only" <?php if (get_option('cfturnstile_bbpress_guest_only')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/bbpress/" target="_blank">' . esc_html__('bbPress', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // Ultimate Member
			if (cft_is_plugin_active('ultimate-member/ultimate-member.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('Ultimate Member', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('UM Login Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_um_login" <?php if (get_option('cfturnstile_um_login')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('UM Register Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_um_register" <?php if (get_option('cfturnstile_um_register')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('UM Password Reset Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_um_password" <?php if (get_option('cfturnstile_um_password')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/ultimate-member/" target="_blank">' . esc_html__('Ultimate Member', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // MemberPress
			if (cft_is_plugin_active('memberpress/memberpress.php')) { 

				if(get_option('cfturnstile_mepr_product_ids')) {
				  $LimitedToProductIDs = get_option('cfturnstile_mepr_product_ids');
				  $ProductsNeedingCaptcha = explode("\n", str_replace("\r", "", $LimitedToProductIDs));
				}
				?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('MemberPress', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<script>
						jQuery(document).ready(function(){
							jQuery("input[name='cfturnstile_login']").change(function(){
								if(jQuery("input[name='cfturnstile_login']").is(':checked')){
									jQuery('#cfturnstile_mepr_login').prop('checked', true);
								} else {
									jQuery('#cfturnstile_mepr_login').prop('checked', false);
								}
							});
						});
						</script>
						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Login Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type='checkbox' name='cfturnstile_mepr_login' id='cfturnstile_mepr_login' <?php if (get_option('cfturnstile_login')) { ?>checked<?php } ?>
							title='<?php echo esc_html__('Edit via "WordPress Login" option in the "Default WordPress Forms" settings.', 'simple-cloudflare-turnstile'); ?>' disabled></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Registration/Checkout Forms', 'simple-cloudflare-turnstile'); 
								if(get_option('cfturnstile_mepr_product_ids')) {
									?>
								<br><span style="font-weight:400;font-size:12px;"><span style="color:#d1242f;"><?php echo esc_html__('Limited to:', 'simple-cloudflare-turnstile'); ?></span> <?php echo implode(', ' , $ProductsNeedingCaptcha); ?></span>
								<?php
								}
								?>
							</th>
							<td><input type='checkbox' name='cfturnstile_mepr_register' id='cfturnstile_mepr_register' <?php if (get_option('cfturnstile_mepr_register')) { ?>checked<?php } ?>></td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('ONLY enable for these Membership IDs:', 'simple-cloudflare-turnstile'); ?></th>
							<td>
								<textarea style="width: 240px;" name="cfturnstile_mepr_product_ids"><?php echo sanitize_textarea_field(get_option('cfturnstile_mepr_product_ids')); ?></textarea>
								<br /><i style="font-size: 10px;"><?php echo esc_html__('(Optional) One per line. For Membership products that are not on this list, no Turnstile challenge will be loaded or enforced.', 'simple-cloudflare-turnstile'); ?></i>
							</td>
						</tr>

					</table>

				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://memberpress.com/?utm_source=simplecloudflareturnstile" target="_blank">' . esc_html__('MemberPress', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // WP-Members
			if (cft_is_plugin_active('wp-members/wp-members.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('WP-Members', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<p>
							<?php echo esc_html__('Turnstile is supported for WP-Members Login and Registration forms. Enable for these forms in the "Default WordPress Forms" settings.', 'simple-cloudflare-turnstile'); ?>
						</p><br/>

					</table>

				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/wp-members/" target="_blank">' . esc_html__('WP-Members', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // WP User Frontend
			if (cft_is_plugin_active('wp-user-frontend/wpuf.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('WP User Frontend', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

						<script>
						jQuery(document).ready(function(){
							jQuery("input[name='cfturnstile_login']").change(function(){
								if(jQuery("input[name='cfturnstile_login']").is(':checked')){
									jQuery('#cfturnstile_wpuf_login').prop('checked', true);
								} else {
									jQuery('#cfturnstile_wpuf_login').prop('checked', false);
								}
							});
						});
						</script>
						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Login Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type='checkbox' name='cfturnstile_wpuf_login' id='cfturnstile_wpuf_login' <?php if (get_option('cfturnstile_login')) { ?>checked<?php } ?>
							title='<?php echo esc_html__('Edit via "WordPress Login" option in the "Default WordPress Forms" settings.', 'simple-cloudflare-turnstile'); ?>' disabled></td>
						</tr>

						<script>
						jQuery(document).ready(function(){
							jQuery("input[name='cfturnstile_reset']").change(function(){
								if(jQuery("input[name='cfturnstile_reset']").is(':checked')){
									jQuery('#cfturnstile_wpuf_reset').prop('checked', true);
								} else {
									jQuery('#cfturnstile_wpuf_reset').prop('checked', false);
								}
							});
						});
						</script>
						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Reset Password Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type='checkbox' name='cfturnstile_wpuf_reset' id='cfturnstile_wpuf_reset' <?php if (get_option('cfturnstile_reset')) { ?>checked<?php } ?>
							title='<?php echo esc_html__('Edit via "WordPress Reset Password" option in the "Default WordPress Forms" settings.', 'simple-cloudflare-turnstile'); ?>' disabled></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Register Form', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_wpuf_register" <?php if (get_option('cfturnstile_wpuf_register')) { ?>checked<?php } ?>></td>
						</tr>

						<tr valign="top">
							<th scope="row">
								<?php echo esc_html__('Post Forms', 'simple-cloudflare-turnstile'); ?>
							</th>
							<td><input type="checkbox" name="cfturnstile_wpuf_forms" <?php if (get_option('cfturnstile_wpuf_forms')) { ?>checked<?php } ?>></td>
						</tr>

					</table>

				</div>

			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/wp-user-frontend/" target="_blank">' . esc_html__('WP User Frontend', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // WP User Manager
			if (cft_is_plugin_active('wp-user-manager/wp-user-manager.php')) { ?>
				<button type="button" class="sct-accordion"><?php echo esc_html__('WP User Manager', 'simple-cloudflare-turnstile'); ?></button>
				<div class="sct-panel">

					<p>
						<?php echo esc_html__('Turnstile is supported for WP User Manager Login, Registration and Reset Password forms. Enable for these forms in the "Default WordPress Forms" settings.', 'simple-cloudflare-turnstile'); ?>
					</p>

				</div>
			<?php
			} else {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/wp-user-manager/" target="_blank">' . esc_html__('WP User Manager', 'simple-cloudflare-turnstile') . '</a>');
			}
			?>

			<?php // wpDiscuz
			if (!cft_is_plugin_active('wpdiscuz/class.WpdiscuzCore.php')) {
				array_push($not_installed, '<a href="https://wordpress.org/plugins/wpdiscuz/" target="_blank">' . esc_html__('wpDiscuz', 'simple-cloudflare-turnstile') . '</a>');
			} ?>

			<?php
			// Output Custom Settings
			do_action('cfturnstile-settings-section');
			$not_installed = apply_filters('cfturnstile-settings-not-installed', $not_installed);
			?>

			<?php // List of plugins not installed
			if (!empty($not_installed)) { ?>
				<br />

				<table class="form-table" style="margin-top: -15px; margin-bottom: -10px;">

					<tr valign="top">
						<th scope="row">
							<span style="font-size: 19px;"><?php echo esc_html__('Other Integrations', 'simple-cloudflare-turnstile'); ?></span>
							<p>
								
								<?php echo esc_html__('You can also enable Turnstile on', 'simple-cloudflare-turnstile') . " ";
								$last_plugin = end($not_installed);
								foreach ($not_installed as $not_plugin) {
									if ($not_plugin == $last_plugin && count($not_installed) > 1) echo 'and ';
									echo wp_kses( $not_plugin, array( 'a' => array( 'href' => array(), 'target' => array() ) ) );
									if ($not_plugin != $last_plugin) {
										echo ', ';
									} else {
										echo '.';
									}
								}
								?>

								<?php echo esc_html__('Simply install the plugin and new settings will appear above.', 'simple-cloudflare-turnstile'); ?>

							</p>
						</th>
					</tr>

				</table>

			<?php } ?>

			</div>

			<?php submit_button(); ?>

			<div style="font-size: 10px; margin-top: 15px;">
				<!-- Delete Options on Uninstall (Always keep this option last) -->
				<input type="checkbox" name="cfturnstile_uninstall_remove" <?php if (get_option('cfturnstile_uninstall_remove')) { ?>checked<?php } ?> style="transform: scale(0.7); margin: -2px 0 0 0;">
				<?php echo esc_html__('Delete all of this plugins saved options when the plugin is deleted via plugins page.', 'simple-cloudflare-turnstile'); ?>
			</div>

		</form>

		<?php } elseif ('analytics' === $cfturnstile_active_tab) { ?>
			<?php cfturnstile_render_analytics_tab(); ?>
		<?php } elseif ('export' === $cfturnstile_active_tab) { ?>
			<?php cfturnstile_render_export_tab(); ?>
		<?php } ?>

	</div>

		<div class="sct-admin-promo-area" style="margin-top: 15px;">

			<div class="sct-admin-promo">

				<p style="font-weight: bold;">
					<?php echo esc_html__('Help and Resources:', 'simple-cloudflare-turnstile'); ?>
				</p>

				<div class="sct-support-item" style="border-top: 1px solid #f1f1f1;">

					<p>
						- <?php echo esc_html__('Not sure how to setup this plugin?', 'simple-cloudflare-turnstile'); ?>
						<a href="https://elliotsowersby.com/blog/setup-guide-turnstile/?utm_source=simplecloudflareturnstile&utm_medium=settings-sidebar-guide" title="View our Turnstile plugin setup guide." target="_blank"><?php echo esc_html__('View setup guide', 'simple-cloudflare-turnstile'); ?></a>
					</p>

					<p>
						- <?php echo esc_html__('Want to learn more about the plugin features?', 'simple-cloudflare-turnstile'); ?>
						<a href="https://simpleturnstile.com/documentation/" target="_blank" title="View our Turnstile plugin documentation."><?php echo esc_html__('View documentation', 'simple-cloudflare-turnstile'); ?></a>
					</p>

					<p>- <?php echo esc_html__('Need help? Have a suggestion?', 'simple-cloudflare-turnstile'); ?> <a href="https://wordpress.org/support/plugin/simple-cloudflare-turnstile/#new-topic-0" target="_blank" title="<?php echo esc_html__('Create a support topic', 'simple-cloudflare-turnstile'); ?>."><?php echo esc_html__('Create a support topic', 'simple-cloudflare-turnstile'); ?></a></p>

					<p style="font-size: 12px;">
						<a href="https://translate.wordpress.org/projects/wp-plugins/simple-cloudflare-turnstile/" target="_blank"><?php echo esc_html__('Translate into your language', 'simple-cloudflare-turnstile'); ?></a>
						-
						<a href="https://github.com/ElliotSowersby/simple-cloudflare-turnstile" target="_blank"><?php echo esc_html__('View on GitHub', 'simple-cloudflare-turnstile'); ?></a>
					</p>

				</div>

			</div>

			<div class="sct-admin-promo sct-support" style="margin-top: 15px;">

				<p style="font-weight: bold;">
					<?php echo esc_html__('Support The Plugin', 'simple-cloudflare-turnstile'); ?>:
				</p>

				<div class="sct-support-item">
					<div class="sct-support-text">
						<strong style="color: #057322;"><?php echo esc_html__('Leave a Review:', 'simple-cloudflare-turnstile'); ?></strong>
						<?php echo sprintf( wp_kses_post( __( 'If you found this plugin useful, please submit a positive review on <a href="%s" target="_blank">WordPress.org</a>.', 'simple-cloudflare-turnstile' ) ), 'https://wordpress.org/support/plugin/simple-cloudflare-turnstile/reviews/?filter=5#new-post' ); ?>
						<span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span>
					</div>
					<a class="button button-primary sct-support-btn" href="https://wordpress.org/support/plugin/simple-cloudflare-turnstile/reviews/?filter=5#new-post"
					style="background-color: #d49332ff; border-color: #d49332ff;"
					target="_blank">
						<?php echo esc_html__('Review', 'simple-cloudflare-turnstile'); ?>
						<i class="dashicons dashicons-external" style="font-size: 14px; line-height: 22px;"></i>
					</a>
				</div>

				<div class="sct-support-item">
					<div class="sct-support-text">
						<strong style="color: #057322;"><?php echo esc_html__('Donate:', 'simple-cloudflare-turnstile'); ?></strong>
						<?php echo esc_html__('You can help keep this plugin 100% free by making a small donation to support its ongoing development and maintenance.', 'simple-cloudflare-turnstile'); ?>
					</div>
					<a class="button button-primary sct-support-btn" href="https://www.elliotsowersby.com/donate/"
					style="background-color: #057322; border-color: #057322;"
					target="_blank">
						<?php echo esc_html__('Donate', 'simple-cloudflare-turnstile'); ?>
						<i class="dashicons dashicons-external" style="font-size: 14px; line-height: 22px;"></i>
					</a>
				</div>

			</div>

			<div class="sct-admin-promo" style="margin-top: 15px; padding: 15px 20px;">

				<p style="margin: 0; display: flex; align-items: center; justify-content: space-between;">
					<span><?php echo esc_html__('100% free plugin by', 'simple-cloudflare-turnstile'); ?> <a href="https://elliotsowersby.com/?utm_source=simplecloudflareturnstile&utm_medium=promo" target="_blank">Elliot Sowersby</a></span>
					<a href="https://relywp.com/?utm_campaign=simple-turnstile-plugin&utm_source=plugin-settings&utm_medium=promo" target="_blank" style="margin-left: 8px; flex-shrink: 0;">
						<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . '../../inc/images/relywp.png' ); ?>" alt="RelyWP" style="height: 42px; width: auto; vertical-align: middle; display: inline-block; margin-right: 5px;" />
					</a>
				</p>

			</div>

		</div>

<?php } ?>