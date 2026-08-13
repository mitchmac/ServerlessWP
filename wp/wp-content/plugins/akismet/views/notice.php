<?php
//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.
$kses_allow_link   = array(
	'a' => array(
		'href'   => true,
		'target' => true,
		'class'  => true,
	),
);
$kses_allow_strong = array( 'strong' => true );

if ( ! isset( $type ) ) {
	$type = false; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}

/*
 * Some notices (plugin, spam-check, spam-check-cron-disabled, alert and usage-limit) are also shown elsewhere in wp-admin, so have different classes applied so that they match the standard WordPress notice format.
 */
?>
<?php if ( $type === 'plugin' ) : ?>
	<?php // Displayed on edit-comments.php to users who have not set up Akismet yet. ?>
	<div class="updated" id="akismet-setup-prompt">
		<form name="akismet_activate" action="<?php echo esc_url( Akismet_Admin::get_page_url() ); ?>" method="post">
			<div class="akismet-activate">
				<input type="submit" class="akismet-activate__button akismet-button" value="<?php esc_attr_e( 'Set up your Akismet account', 'akismet' ); ?>" />
				<div class="akismet-activate__description">
					<?php esc_html_e( 'Almost done! Configure Akismet and say goodbye to spam', 'akismet' ); ?>
				</div>
			</div>
		</form>
	</div>
<?php elseif ( $type === 'spam-check' ) : ?>
	<?php // This notice is only displayed on edit-comments.php. ?>
	<div class="notice notice-warning">
		<p><strong><?php esc_html_e( 'Akismet has detected a problem.', 'akismet' ); ?></strong></p>
		<p><?php esc_html_e( 'Some comments have not yet been checked for spam by Akismet. They have been temporarily held for moderation and will automatically be rechecked later.', 'akismet' ); ?></p>
		<?php if ( ! empty( $link_text ) ) : ?>
			<p><?php echo wp_kses( $link_text, $kses_allow_link ); ?></p>
		<?php endif; ?>
	</div>

<?php elseif ( $type === 'spam-check-cron-disabled' ) : ?>
	<?php // This notice is only displayed on edit-comments.php. ?>
	<div class="notice notice-warning">
		<p><strong><?php esc_html_e( 'Akismet has detected a problem.', 'akismet' ); ?></strong></p>
		<p><?php esc_html_e( 'WP-Cron has been disabled using the DISABLE_WP_CRON constant. Comment rechecks may not work properly.', 'akismet' ); ?></p>
	</div>

<?php elseif ( $type === 'alert' && $code === Akismet::ALERT_CODE_COMMERCIAL && isset( $parent_view ) && $parent_view === 'config' ) : ?>
	<?php // Display a different commercial warning alert on the config page ?>
	<div class="akismet-card akismet-alert is-commercial">
		<div>
			<h3 class="akismet-alert-header"><?php esc_html_e( 'We detected commercial activity on your site', 'akismet' ); ?></h3>
			<p class="akismet-alert-info">
				<?php
					/* translators: The placeholder is a URL. */
					echo wp_kses( sprintf( __( 'Your current subscription is for <a class="akismet-external-link" href="%s">personal, non-commercial use</a>. Please upgrade your plan to continue using Akismet.', 'akismet' ), esc_url( 'https://akismet.com/support/getting-started/free-or-paid/?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=commercial_support' ) ), $kses_allow_link );
				?>
			</p>
			<p class="akismet-alert-info">
				<?php
					/* translators: The placeholder is a URL to the contact form. */
					echo wp_kses( sprintf( __( 'If you believe your site should not be classified as commercial, <a class="akismet-external-link" href="%s">please get in touch</a>', 'akismet' ), esc_url( 'https://akismet.com/contact/?purpose=commercial&utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=commercial_contact' ) ), $kses_allow_link );
				?>
			</p>
		</div>
		<div class="akismet-alert-button-wrapper">
			<?php
			Akismet::view(
				'get',
				array(
					'text'         => __( 'Upgrade plan', 'akismet' ),
					'classes'      => array( 'akismet-alert-button', 'akismet-button' ),
					'redirect'     => 'upgrade',
					'utm_content'  => 'commercial_upgrade',
				)
			);
			?>
		</div>
	</div>

<?php elseif ( $type === 'alert' ) : ?>
<div class="<?php echo isset( $parent_view ) && $parent_view === 'config' ? 'akismet-alert is-bad' : 'error'; ?>">
	<?php /* translators: The placeholder is an error code returned by Akismet. */ ?>
	<p><strong><?php printf( esc_html__( 'Akismet error code: %s', 'akismet' ), esc_html( $code ) ); ?></strong></p>
	<p><?php echo isset( $msg ) ? esc_html( $msg ) : ''; ?></p>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: the placeholder is a clickable URL that leads to more information regarding an error code. */
				__( 'For more information, see the <a class="akismet-external-link" href="%s">error documentation on akismet.com</a>', 'akismet' ),
				esc_url( 'https://akismet.com/developers/detailed-docs/errors/akismet-error-' . absint( $code ) . '?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=error_info' )
			),
			$kses_allow_link
		);
		?>
	</p>
</div>

<?php elseif ( $type === 'notice' ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php echo wp_kses( $notice_header, Akismet_Admin::get_notice_kses_allowed_elements() ); ?></h3>
	<p>
		<?php echo wp_kses( $notice_text, Akismet_Admin::get_notice_kses_allowed_elements() ); ?>
	</p>
</div>

<?php elseif ( $type === 'missing-functions' ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php esc_html_e( 'Network functions are disabled.', 'akismet' ); ?></h3>
	<p>
		<?php
		echo wp_kses( __( 'Your web host or server administrator has disabled PHP&#8217;s <code>gethostbynamel</code> function.', 'akismet' ), array_merge( $kses_allow_link, $kses_allow_strong, array( 'code' => true ) ) );
		?>
	</p>
	<p>
		<?php
		/* translators: The placeholder is a URL. */
		echo wp_kses( sprintf( __( 'Please contact your web host or firewall administrator and give them <a class="akismet-external-link" href="%s" target="_blank">this information about Akismet&#8217;s system requirements</a>', 'akismet' ), esc_url( 'https://akismet.com/akismet-hosting-faq/?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=hosting_faq_php' ) ), array_merge( $kses_allow_link, $kses_allow_strong, array( 'code' => true ) ) );
		?>
	</p>
</div>

<?php elseif ( $type === 'servers-be-down' ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php esc_html_e( 'Your site can&#8217;t connect to the Akismet servers.', 'akismet' ); ?></h3>
	<p>
	<?php
		/* translators: The placeholder is a URL. */
		echo wp_kses( sprintf( __( 'Your firewall may be blocking Akismet from connecting to its API. Please contact your host and refer to <a class="akismet-external-link" href="%s" target="_blank">our guide about firewalls</a>', 'akismet' ), esc_url( 'https://akismet.com/akismet-hosting-faq/?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=hosting_faq_firewall' ) ), $kses_allow_link );
	?>
	</p>
</div>

<?php elseif ( $type === 'cancelled' ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php esc_html_e( 'Your Akismet plan has been cancelled.', 'akismet' ); ?></h3>
	<p>
		<?php
		/* translators: The placeholder is a URL. */
		echo wp_kses( sprintf( __( 'Please visit <a class="akismet-external-link" href="%s" target="_blank">Akismet.com</a> to purchase a new subscription.', 'akismet' ), esc_url( 'https://akismet.com/pricing/?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=pricing_cancelled' ) ), $kses_allow_link );
		?>
	</p>
</div>

<?php elseif ( $type === 'suspended' ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php esc_html_e( 'Your Akismet subscription is suspended.', 'akismet' ); ?></h3>
	<p>
		<?php
		/* translators: The placeholder is a URL. */
		echo wp_kses( sprintf( __( 'Please contact <a class="akismet-external-link" href="%s" target="_blank">Akismet support</a> for assistance.', 'akismet' ), esc_url( 'https://akismet.com/contact/?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=support_suspended' ) ), $kses_allow_link );
		?>
	</p>
</div>

<?php elseif ( $type === 'active-notice' && $time_saved ) : ?>
<div class="akismet-alert is-neutral">
	<h3 class="akismet-alert__heading"><?php echo esc_html( $time_saved ); ?></h3>
	<p>
		<?php
		/* translators: the placeholder is a clickable URL to the Akismet account upgrade page. */
		echo wp_kses( sprintf( __( 'You can help us fight spam and upgrade your account by <a class="akismet-external-link" href="%s" target="_blank">contributing a token amount</a>', 'akismet' ), esc_url( 'https://akismet.com/pricing?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=upgrade_contribution' ) ), $kses_allow_link );
		?>
	</p>
</div>

<?php elseif ( $type === 'missing' ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php esc_html_e( 'There is a problem with your API key.', 'akismet' ); ?></h3>
	<p>
		<?php
		/* translators: The placeholder is a URL to the Akismet contact form. */
		echo wp_kses( sprintf( __( 'Please contact <a class="akismet-external-link" href="%s" target="_blank">Akismet support</a> for assistance.', 'akismet' ), esc_url( 'https://akismet.com/contact/?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=support_missing' ) ), $kses_allow_link );
		?>
	</p>
</div>

<?php elseif ( $type === 'no-sub' ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php esc_html_e( 'You don&#8217;t have an Akismet plan.', 'akismet' ); ?></h3>
	<p>
		<?php
		/* translators: the placeholder is the URL to the Akismet pricing page. */
		echo wp_kses( sprintf( __( 'Please <a class="akismet-external-link" href="%s" target="_blank">choose a free or paid plan</a> so Akismet can protect your site from spam.', 'akismet' ), esc_url( 'https://akismet.com/pricing?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=choose_plan' ) ), $kses_allow_link );
		?>
	</p>
	<p><?php echo esc_html__( 'Once you\'ve chosen a plan, return here to complete your setup.', 'akismet' ); ?></p>
</div>

<?php elseif ( $type === 'new-key-valid' ) : ?>
	<?php
	global $wpdb;

	$check_pending_link = false;

	$at_least_one_comment_in_moderation = ! ! $wpdb->get_var( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = '0' LIMIT 1" );

	if ( $at_least_one_comment_in_moderation ) {
		$check_pending_link = 'edit-comments.php?akismet_recheck=' . wp_create_nonce( 'akismet_recheck' );
	}
	?>
	<div class="akismet-alert is-good">
		<p><?php esc_html_e( 'Akismet is now protecting your site from spam.', 'akismet' ); ?></p>
		<?php if ( $check_pending_link ) : ?>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: The placeholder is a URL for checking pending comments. */
						__( 'Would you like to <a href="%s">scan pending comments for spam</a>?', 'akismet' ),
						esc_url( $check_pending_link )
					),
					$kses_allow_link
				);
				?>
			</p>
		<?php endif; ?>
	</div>

<?php elseif ( $type === 'new-key-invalid' ) : ?>
<div class="akismet-alert is-bad">
	<p><?php esc_html_e( 'The key you entered is invalid. Please double-check it.', 'akismet' ); ?></p>
</div>

<?php elseif ( $type === Akismet_Admin::NOTICE_EXISTING_KEY_INVALID ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php echo esc_html( __( 'Your API key is no longer valid.', 'akismet' ) ); ?></h3>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: The placeholder is a URL to the Akismet contact form. */
				__( 'Please enter a new key or <a class="akismet-external-link" href="%s" target="_blank">contact Akismet support</a>', 'akismet' ),
				'https://akismet.com/contact/?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=support_invalid_key'
			),
			$kses_allow_link
		);
		?>
	</p>
</div>

<?php elseif ( $type === 'new-key-failed' ) : ?>
<div class="akismet-alert is-bad">
	<h3 class="akismet-alert__heading"><?php esc_html_e( 'The API key you entered could not be verified.', 'akismet' ); ?></h3>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: The placeholder is a URL. */
				__( 'The connection to akismet.com could not be established. Please refer to <a class="akismet-external-link" href="%s" target="_blank">our guide about firewalls</a> and check your server configuration.', 'akismet' ),
				'https://akismet.com/akismet-hosting-faq/?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=hosting_faq'
			),
			$kses_allow_link
		);
		?>
	</p>
</div>

<?php elseif ( $type === 'usage-limit' && isset( Akismet::$limit_notices[ $code ] ) ) : ?>
<div class="error akismet-usage-limit-alert">
	<div class="akismet-usage-limit-logo">
		<img src="<?php echo esc_url( plugins_url( '../_inc/img/logo-a-2x.png', __FILE__ ) ); ?>" alt="Akismet logo" />
	</div>
	<div class="akismet-usage-limit-text">
		<h3>
		<?php
		switch ( Akismet::$limit_notices[ $code ] ) {
			case 'FIRST_MONTH_OVER_LIMIT':
			case 'SECOND_MONTH_OVER_LIMIT':
				esc_html_e( 'Your Akismet account usage is over your plan&#8217;s limit', 'akismet' );
				break;
			case 'THIRD_MONTH_APPROACHING_LIMIT':
				esc_html_e( 'Your Akismet account usage is approaching your plan&#8217;s limit', 'akismet' );
				break;
			case 'THIRD_MONTH_OVER_LIMIT':
			case 'FOUR_PLUS_MONTHS_OVER_LIMIT':
				esc_html_e( 'Your account has been restricted', 'akismet' );
				break;
			default:
		}
		?>
		</h3>
		<p>
		<?php
		switch ( Akismet::$limit_notices[ $code ] ) {
			case 'FIRST_MONTH_OVER_LIMIT':
				echo esc_html(
					sprintf(
						/* translators: The first placeholder is a date, the second is a (formatted) number, the third is another formatted number. */
						__( 'Since %1$s, your account made %2$s API calls, compared to your plan&#8217;s limit of %3$s.', 'akismet' ),
						esc_html( gmdate( 'F' ) . ' 1' ),
						number_format( $api_calls ),
						number_format( $usage_limit )
					)
				);
				echo '&nbsp;';
				echo '<a class="akismet-external-link" href="https://akismet.com/support/general/akismet-api-usage-limits/?utm_source=akismet_plugin&amp;utm_campaign=plugin_static_link&amp;utm_medium=in_plugin&amp;utm_content=usage_limit_docs" target="_blank">';
				echo esc_html( __( 'Learn more about usage limits', 'akismet' ) );
				echo '</a>';

				break;
			case 'SECOND_MONTH_OVER_LIMIT':
				echo esc_html( __( 'Your Akismet usage has been over your plan&#8217;s limit for two consecutive months. Next month, we will restrict your account after you reach the limit. Increase your limit to make sure your site stays protected from spam.', 'akismet' ) );
				echo '&nbsp;';
				echo '<a class="akismet-external-link" href="https://akismet.com/support/general/akismet-api-usage-limits/?utm_source=akismet_plugin&amp;utm_campaign=plugin_static_link&amp;utm_medium=in_plugin&amp;utm_content=usage_limit_docs" target="_blank">';
				echo esc_html( __( 'Learn more about usage limits', 'akismet' ) );
				echo '</a>';

				break;
			case 'THIRD_MONTH_APPROACHING_LIMIT':
				echo esc_html( __( 'Your Akismet usage is nearing your plan&#8217;s limit for the third consecutive month. We will restrict your account after you reach the limit. Increase your limit to make sure your site stays protected from spam.', 'akismet' ) );
				echo '&nbsp;';
				echo '<a class="akismet-external-link" href="https://akismet.com/support/general/akismet-api-usage-limits/?utm_source=akismet_plugin&amp;utm_campaign=plugin_static_link&amp;utm_medium=in_plugin&amp;utm_content=usage_limit_docs" target="_blank">';
				echo esc_html( __( 'Learn more about usage limits', 'akismet' ) );
				echo '</a>';

				break;
			case 'THIRD_MONTH_OVER_LIMIT':
			case 'FOUR_PLUS_MONTHS_OVER_LIMIT':
				echo esc_html( __( 'Your Akismet usage has been over your plan&#8217;s limit for three consecutive months. We have restricted your account for the rest of the month. Increase your limit to make sure your site stays protected from spam.', 'akismet' ) );
				echo '&nbsp;';
				echo '<a class="akismet-external-link" href="https://akismet.com/support/general/akismet-api-usage-limits/?utm_source=akismet_plugin&amp;utm_campaign=plugin_static_link&amp;utm_medium=in_plugin&amp;utm_content=usage_limit_docs" target="_blank">';
				echo esc_html( __( 'Learn more about usage limits', 'akismet' ) );
				echo '</a>';

				break;

			default:
		}
		?>
		</p>
	</div>
	<div class="akismet-usage-limit-cta">
		<a href="<?php echo esc_url( $upgrade_url . ( strpos( $upgrade_url, '?' ) !== false ? '&' : '?' ) . 'utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=usage_limit_upgrade' ); ?>" class="button" target="_blank">
			<?php
			if ( isset( $upgrade_via_support ) && $upgrade_via_support ) {
				// Direct user to contact support.
				esc_html_e( 'Contact Akismet support', 'akismet' );
			} elseif ( ! empty( $upgrade_type ) && 'qty' === $upgrade_type ) {
				// If a qty upgrade is required, use recommended plan name if available.
				if ( ! empty( $recommended_plan_name ) && is_string( $recommended_plan_name ) ) {
					echo esc_html(
						sprintf(
							/* translators: The placeholder is the name of an Akismet subscription plan, like "Akismet Pro" or "Akismet Business" . */
							__( 'Add an %s subscription', 'akismet' ),
							$recommended_plan_name
						)
					);
				} else {
					esc_html_e( 'Increase your limit', 'akismet' );
				}
			} else {
				echo esc_html(
					sprintf(
						/* translators: The placeholder is the name of a subscription level, like "Akismet Business" or "Akismet Enterprise" . */
						__( 'Upgrade to %s', 'akismet' ),
						$upgrade_plan
					)
				);
			}
			?>
		</a>
	</div>
</div>
<?php endif; ?>
