<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

/**
 * Upgrade_Filter_Post_Excerpt Class
 *
 * This class handles replacing all S3 URLs in post
 * excerpts with the local URL.
 *
 * @since 1.3
 */
class Upgrade_Filter_Post_Excerpt extends Upgrade_Filter_Post {

	/**
	 * @var int
	 */
	protected $upgrade_id = 6;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'filter_post_excerpt';

	/**
	 * @var string
	 */
	protected $column_name = 'post_excerpt';

	/**
	 * Get running update text.
	 *
	 * @return string
	 */
	protected function get_running_update_text() {
		return __( 'and ensuring that only the local URL exists in post excerpts.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Get running message.
	 *
	 * @return string
	 */
	protected function get_running_message() {
		return sprintf( __( '<strong>Running Excerpts Upgrade%1$s</strong><br>A find &amp; replace is running in the background to update URLs in your post excerpts. %2$s', 'amazon-s3-and-cloudfront' ), $this->get_progress_text(), $this->get_generic_message() );
	}
}