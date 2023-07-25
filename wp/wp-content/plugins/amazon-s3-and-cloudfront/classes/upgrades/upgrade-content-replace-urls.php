<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

/**
 * Upgrade_Content_Replace_URLs Class
 *
 * This class handles replacing all S3 URLs in post
 * content with the local URL.
 *
 * @since 1.2
 */
class Upgrade_Content_Replace_URLs extends Upgrade_Filter_Post {

	/**
	 * @var int
	 */
	protected $upgrade_id = 4;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'replace_provider_urls';

	/**
	 * @var string
	 */
	protected $column_name = 'post_content';

	/**
	 * Get running update text.
	 *
	 * @return string
	 */
	protected function get_running_update_text() {
		return __( 'and ensuring that only the local URL exists in post content.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Get running message.
	 *
	 * @return string
	 */
	protected function get_running_message() {
		return sprintf( __( '<strong>Running Content Upgrade%1$s</strong><br>A find &amp; replace is running in the background to update URLs in your post content. %2$s', 'amazon-s3-and-cloudfront' ), $this->get_progress_text(), $this->get_generic_message() );
	}

	/**
	 * Switch to a new blog for processing.
	 *
	 * @return bool
	 */
	protected function upgrade_blog() {
		$this->upgrade_theme_mods();

		return parent::upgrade_blog();
	}

	/**
	 * Upgrade theme mods. Ensures background and header images have local URLs saved to the database.
	 */
	protected function upgrade_theme_mods() {
		global $wpdb;

		$mods = $wpdb->get_results( "SELECT * FROM `{$wpdb->options}` WHERE option_name LIKE 'theme_mods_%'" );

		foreach ( $mods as $mod ) {
			$value = maybe_unserialize( $mod->option_value );

			if ( isset( $value['background_image'] ) ) {
				$value['background_image'] = $this->as3cf->filter_provider->filter_customizer_image( $value['background_image'] );
			}

			if ( isset( $value['header_image'] ) ) {
				$value['header_image'] = $this->as3cf->filter_provider->filter_customizer_image( $value['header_image'] );
			}

			if ( isset( $value['header_image_data'] ) ) {
				$value['header_image_data'] = $this->as3cf->filter_provider->filter_header_image_data( $value['header_image_data'] );
			}

			$value = maybe_serialize( $value );

			if ( $value !== $mod->option_value ) {
				$wpdb->query( "UPDATE `{$wpdb->options}` SET option_value = '{$value}' WHERE option_id = '{$mod->option_id}'" );
			}
		}
	}

}