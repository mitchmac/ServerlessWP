<?php
declare( strict_types = 1 );

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.

$tick_icon = '<svg class="akismet-setup-instructions__icon" width="48" height="48" viewBox="0 0 48 48" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
  <circle cx="24" cy="24" r="22" fill="#2E7D32"/>
  <path d="M16 24l6 6 12-14" fill="none" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
</svg>';
?>
<section class="akismet-setup-instructions">
	<h2 class="akismet-setup-instructions__heading"><?php esc_html_e( 'Eliminate spam from your site', 'akismet' ); ?></h2>

	<h3 class="akismet-setup-instructions__subheading">
		<?php echo esc_html__( 'Protect your site from comment spam and contact form spam — automatically.', 'akismet' ); ?>
	</h3>

	<ul class="akismet-setup-instructions__feature-list">
		<li class="akismet-setup-instructions__feature">
			<?php echo $tick_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="akismet-setup-instructions__body">
				<h4 class="akismet-setup-instructions__title">
					<?php echo esc_html__( 'Machine learning accuracy', 'akismet' ); ?>
				</h4>
				<p class="akismet-setup-instructions__text">
					<?php echo esc_html__( 'Learns from billions of spam signals across the web to stop junk before it reaches you.', 'akismet' ); ?>
				</p>
			</div>
		</li>
		<li class="akismet-setup-instructions__feature">
			<?php echo $tick_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="akismet-setup-instructions__body">
				<h4 class="akismet-setup-instructions__title">
					<?php echo esc_html__( 'Zero effort', 'akismet' ); ?>
				</h4>
				<p class="akismet-setup-instructions__text">
					<?php echo esc_html__( 'Akismet runs quietly in the background, saving you hours of manual moderation.', 'akismet' ); ?>
				</p>
			</div>
		</li>
		<li class="akismet-setup-instructions__feature">
			<?php echo $tick_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="akismet-setup-instructions__body">
				<h4 class="akismet-setup-instructions__title">
					<?php echo esc_html__( 'Works with popular contact forms', 'akismet' ); ?>
				</h4>
				<p class="akismet-setup-instructions__text">
					<?php echo esc_html__( 'Seamlessly integrates with plugins like Elementor, Contact Form 7, Jetpack and WPForms.', 'akismet' ); ?>
				</p>
			</div>
		</li>
		<li class="akismet-setup-instructions__feature">
			<?php echo $tick_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="akismet-setup-instructions__body">
				<h4 class="akismet-setup-instructions__title">
					<?php echo esc_html__( 'Flexible pricing', 'akismet' ); ?>
				</h4>
				<p class="akismet-setup-instructions__text">
					<?php echo esc_html__( 'Name your own price for personal sites. Businesses start on a paid plan.', 'akismet' ); ?>
				</p>
			</div>
		</li>
	</ul>

	<?php
	if ( empty( $use_jetpack_connection ) ) :
		Akismet::view(
			'get',
			array(
				'text'    => __( 'Get started', 'akismet' ),
				'classes' => array( 'akismet-button', 'akismet-is-primary', 'akismet-setup-instructions__button' ),
				'utm_content' => 'setup_instructions',
			)
		);
	endif;
	?>
</section>
