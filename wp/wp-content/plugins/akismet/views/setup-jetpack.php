<?php
declare( strict_types = 1 );

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.

$user_status = $akismet_user->status ?? null;
?>
<div class="akismet-setup__connection">
	<?php if ( ! empty( $akismet_user->user_email ) && ! empty( $akismet_user->user_login ) ) : ?>
	<div class="akismet-setup__connection-user">
		<div class="akismet-setup__connection-avatar">
			<?php
			// Decorative avatar; empty alt for screen readers.
			echo get_avatar(
				$akismet_user->user_email,
				48,
				'',
				'',
				array(
					'class' => 'akismet-setup__connection-avatar-image',
					'alt'   => '',
				)
			);
			?>
			<div class="akismet-setup__connection-account">
				<div class="akismet-setup__connection-account-name">
					<?php
					printf(
						/* translators: %s is the WordPress.com username */
						esc_html__( 'Signed in as %s', 'akismet' ),
						'<strong>' . esc_html( $akismet_user->user_login ) . '</strong>'
					);
					?>
				</div>
				<div class="akismet-setup__connection-account-email"><?php echo esc_html( $akismet_user->user_email ); ?></div>
			</div>
		</div>
	</div>
	<?php endif; ?>
	<div class="akismet-setup__connection-action">
		<?php if ( in_array( $user_status, array( Akismet::USER_STATUS_CANCELLED, Akismet::USER_STATUS_MISSING, Akismet::USER_STATUS_NO_SUB ) ) ) : ?>

			<p class="akismet-setup__connection-action-intro">
				<?php esc_html_e( "Your Jetpack account is connected, but it doesn't have an active Akismet subscription yet. To continue, please choose a plan on Akismet.com.", 'akismet' ); ?>
			</p>

			<a href="https://akismet.com/get?utm_source=akismet_plugin&amp;utm_campaign=plugin_static_link&amp;utm_medium=in_plugin&amp;utm_content=jetpack_flow_<?php echo esc_attr( str_replace( '-', '_', $user_status ) ); ?>" class="akismet-setup__connection-button akismet-button">
				<?php esc_html_e( 'Choose a plan on Akismet.com', 'akismet' ); ?>
			</a>

			<p class="akismet-setup__connection-action-description">
				<?php esc_html_e( "Once you've chosen a plan, return here to complete your setup.", 'akismet' ); ?>
			</p>

		<?php elseif ( $user_status === Akismet::USER_STATUS_SUSPENDED ) : ?>
			<p class="akismet-setup__connection-action-intro">
				<?php esc_html_e( "Your Akismet account appears to be suspended. This sometimes happens if there's a billing or verification issue. Please contact our support team so we can help you get it sorted.", 'akismet' ); ?>
			</p>

			<a href="https://akismet.com/contact?utm_source=akismet_plugin&amp;utm_campaign=plugin_static_link&amp;utm_medium=in_plugin&amp;utm_content=jetpack_flow_suspended" class="akismet-setup__connection-button akismet-button">
				<?php esc_html_e( 'Contact support', 'akismet' ); ?>
			</a>
		<?php else : ?>
			<form name="akismet_use_wpcom_key" action="<?php echo esc_url( Akismet_Admin::get_page_url() ); ?>" method="post" id="akismet-activate">
				<input type="hidden" name="key" value="<?php echo esc_attr( $akismet_user->api_key ); ?>"/>
				<input type="hidden" name="action" value="enter-key">
				<?php wp_nonce_field( Akismet_Admin::NONCE ); ?>
				<input type="submit" class="akismet-setup__connection-button akismet-button" value="<?php esc_attr_e( 'Connect with Jetpack', 'akismet' ); ?>"/>
			</form>

			<p class="akismet-setup__connection-action-description">
				<?php esc_html_e( "By connecting, we'll use your Jetpack account to activate Akismet on this site.", 'akismet' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! in_array( $user_status, array( Akismet::USER_STATUS_CANCELLED, Akismet::USER_STATUS_MISSING, Akismet::USER_STATUS_NO_SUB ) ) ) : ?>
			<p class="akismet-setup__connection-action-description">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: The placeholder is a URL. */
						__( 'Want to use a different account? <a href="%s" class="akismet-external-link">Visit akismet.com</a> to set it up and get your API key.', 'akismet' ),
						esc_url( 'https://akismet.com/get?utm_source=akismet_plugin&utm_campaign=plugin_static_link&utm_medium=in_plugin&utm_content=jetpack_flow_different_account' )
					),
					array(
						'a' => array(
							'href'  => array(),
							'class' => array(),
						),
					)
				);
				?>
			</p>
		<?php endif; ?>
	</div>
</div>
