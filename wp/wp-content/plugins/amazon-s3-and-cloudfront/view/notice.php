<?php
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited

/** @var string $message */

$as3cf_auto_p        = ( isset( $auto_p ) ) ? $auto_p : 'true';
$as3cf_show_callback = ( isset( $show_callback ) && false !== $show_callback )
	? array( $GLOBALS[ $show_callback[0] ], $show_callback[1] )
	: false;
$as3cf_callback_args = ( isset( $callback_args ) ) ? $callback_args : array();
?>

<div
	<?php echo empty( $id ) ? '' : 'id="' . esc_attr( $id ) . '"'; ?>
	class="notice <?php echo empty( $type ) ? 'notice-info' : esc_attr( $type ); ?><?php echo empty( $dismissible ) ? '' : ' is-dismissible'; ?> as3cf-notice <?php echo empty( $inline ) ? '' : ' inline'; ?><?php echo empty( $class ) ? '' : ' ' . esc_attr( $class ); ?>"<?php echo empty( $style ) ? '' : ' style="' . esc_attr( $style ) . '"'; ?>>
	<?php if ( $as3cf_auto_p ) : ?>
	<p>
		<?php endif; ?>
		<?php echo wp_kses_post( $message ); // xss ok ?>
		<?php if ( false !== $as3cf_show_callback && is_callable( $as3cf_show_callback ) ) : ?>
			<a
				href="#"
				class="as3cf-notice-toggle"
				data-hide="<?php esc_attr_e( 'Hide', 'amazon-s3-and-cloudfront' ); ?>"
			>
				<?php esc_html_e( 'Show', 'amazon-s3-and-cloudfront' ); ?>
			</a>
		<?php endif; ?>
		<?php if ( $as3cf_auto_p ) : ?>
	</p>
<?php endif; ?>
	<?php if ( false !== $as3cf_show_callback && is_callable( $as3cf_show_callback ) ) : ?>
		<div class="as3cf-notice-toggle-content" style="display: none;">
			<?php call_user_func_array( $as3cf_show_callback, $as3cf_callback_args ); ?>
		</div>
	<?php endif; ?>
</div>
