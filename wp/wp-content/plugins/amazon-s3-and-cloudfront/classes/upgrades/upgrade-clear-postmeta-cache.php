<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

/**
 * Clear_Postmeta_Cache Class
 *
 * This class clears the postmeta cache after upgrade to 2.6.1
 *
 * @since 2.6.1
 */
class Upgrade_Clear_Postmeta_Cache extends Upgrade {

	/**
	 * @var int
	 */
	protected $upgrade_id = 11;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'clear_postmeta_cache';

	/**
	 * @var string 'metadata', 'attachment'
	 */
	protected $upgrade_type = 'metadata';

	/**
	 * @var int
	 */
	private $batch_limit = 1000;

	/**
	 * Get running update text.
	 *
	 * @return string
	 */
	protected function get_running_update_text() {
		return __( 'and clear old post meta cache items.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Remove one chunk of post meta cache records.
	 *
	 * @param string $item Table prefix for the current blog.
	 *
	 * @return bool
	 */
	protected function upgrade_item( $item ) {
		global $wpdb;

		$sql = "DELETE FROM {$item}postmeta WHERE meta_key = 'amazonS3_cache' AND meta_id <= %d LIMIT {$this->batch_limit}";
		$wpdb->query( $wpdb->prepare( $sql, array( $this->session[ $item ] ) ) );

		return true;
	}

	/**
	 * Count items left to process for the current blog.
	 *
	 * @return int
	 */
	protected function count_items_to_process() {
		global $wpdb;

		// Store the highest known meta_id at the time we begin processing.
		if ( empty( $this->session[ $this->blog_prefix ] ) ) {
			$sql  = "SELECT meta_id FROM {$this->blog_prefix}postmeta WHERE meta_key = 'amazonS3_cache' ORDER BY meta_id DESC LIMIT 0, 1;";
			$last = $wpdb->get_var( $sql );

			$this->session[ $this->blog_prefix ] = $last;
		}

		return count( $this->get_items_to_process( $this->blog_prefix, 0 ) );
	}

	/**
	 * Get array of items that each represent one chunk to be cleared.
	 *
	 * @param string     $prefix Table prefix for blog.
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	protected function get_items_to_process( $prefix, $limit, $offset = false ) {
		$count = $this->get_real_count( $prefix );
		if ( 0 === $count ) {
			return array();
		}

		$chunks = ceil( $count / $this->batch_limit );

		return array_fill( 0, $chunks, $prefix );
	}

	/**
	 * Return the real number of remaining amazonS3_cache items to clear out.
	 *
	 * @param string $prefix
	 *
	 * @return int
	 */
	private function get_real_count( $prefix ) {
		global $wpdb;

		$sql   = "SELECT count(meta_id) FROM {$prefix}postmeta WHERE meta_key = 'amazonS3_cache' AND meta_id <= %d";
		$count = $wpdb->get_var( $wpdb->prepare( $sql, $this->session[ $prefix ] ) );

		return (int) $count;
	}
}
