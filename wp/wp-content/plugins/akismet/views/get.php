<?php

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.

$submit_classes_attr = 'akismet-button';

if ( isset( $classes ) && ( is_countable( $classes ) ? count( $classes ) : 0 ) > 0 ) {
	$submit_classes_attr = implode( ' ', $classes );
}

$query_args = array(
	'passback_url' => Akismet_Admin::get_page_url(),
	'redirect'     => isset( $redirect ) ? $redirect : 'plugin-signup',
);

// Set default UTM parameters, overriding with any provided values.
$utm_args = array(
	'utm_source'   => isset( $utm_source ) ? $utm_source : 'akismet_plugin',
	'utm_medium'   => isset( $utm_medium ) ? $utm_medium : 'in_plugin',
	'utm_campaign' => isset( $utm_campaign ) ? $utm_campaign : 'plugin_static_link',
	'utm_content'  => isset( $utm_content ) ? $utm_content : 'get_view_link',
);

$query_args = array_merge( $query_args, $utm_args );

$url = add_query_arg( $query_args, 'https://akismet.com/get/' );
?>
<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $submit_classes_attr ); ?>" target="_blank">
	<?php echo esc_html( is_string( $text ) ? $text : '' ); ?>
	<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'akismet' ); ?></span>
</a>
