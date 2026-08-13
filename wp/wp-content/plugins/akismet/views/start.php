<?php

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.
?>
<div id="akismet-plugin-container">
	<?php if ( has_action( 'akismet_header' ) ) : ?>
		<?php do_action( 'akismet_header' ); ?>
	<?php else : ?>
		<div class="akismet-masthead">
			<div class="akismet-masthead__inside-container">
				<?php Akismet::view( 'logo' ); ?>
			</div>
		</div>
	<?php endif; ?>
	<div class="akismet-lower">
		<?php Akismet_Admin::display_status(); ?>
		<div class="akismet-boxes">
			<?php
			if ( Akismet::predefined_api_key() ) {
				Akismet::view( 'predefined' );
			} elseif ( $akismet_user && in_array( $akismet_user->status, array( Akismet::USER_STATUS_ACTIVE, Akismet::USER_STATUS_NO_SUB, Akismet::USER_STATUS_MISSING, Akismet::USER_STATUS_CANCELLED, Akismet::USER_STATUS_SUSPENDED ) ) ) {
				Akismet::view( 'connect-jp', compact( 'akismet_user' ) );
			} else {
				Akismet::view( 'activate' );
			}
			?>
		</div>
	</div>
	<?php Akismet::view( 'footer' ); ?>
</div>