<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

/**
 * Upgrade_Clear_Postmeta_Cache_340 Class
 *
 * This class clears the postmeta cache after upgrade to 3.4.0.
 *
 * This class was originally used for upgrade ID 11, and has been moved to re-apply
 * for this later version of the plugin.
 *
 * Since fixing up the parsing of URLs to better handle CJK characters in filenames,
 * existing amazonS3_cache records need to be cleared so that properly encoded URLs
 * can be used when repopulating the cache records, otherwise some filtering breaks
 * as it'll use the old broken bare URLs.
 *
 * @since 3.4.0
 */
class Upgrade_Clear_Postmeta_Cache_340 extends Upgrade {

	/**
	 * @var int
	 */
	protected int $upgrade_id = 14;

	/**
	 * @var string
	 */
	protected string $upgrade_name = 'clear_postmeta_cache_340';

	/**
	 * @var string 'metadata', 'attachment'
	 */
	protected string $upgrade_type = 'metadata';

	/**
	 * @var int
	 */
	private int $batch_limit = 1000;

	/**
	 * Get running update text.
	 *
	 * @return string
	 */
	protected function get_running_update_text(): string {
		return __( 'and clear old post meta cache items.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Remove one chunk of post meta cache records.
	 *
	 * @param mixed $item Table prefix for the current blog.
	 *
	 * @return bool
	 */
	protected function upgrade_item( mixed $item ): bool {
		global $wpdb;

		if ( empty( $item ) || ! is_string( $item ) || empty( $this->session[ $item ] ) || ! is_int( $this->session[ $item ] ) ) {
			return false;
		}

		$meta_id = $this->session[ $item ];

		$sql = "DELETE FROM {$item}postmeta WHERE meta_key = 'amazonS3_cache' AND meta_id <= %d LIMIT $this->batch_limit";
		// phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( $wpdb->prepare( $sql, $meta_id ) );

		return true;
	}

	/**
	 * Count items left to process for the current blog.
	 *
	 * @return int
	 */
	protected function count_items_to_process(): int {
		global $wpdb;

		// Store the highest known meta_id at the time we begin processing.
		if ( empty( $this->session[ $this->blog_prefix ] ) ) {
			$sql = "SELECT meta_id FROM {$this->blog_prefix}postmeta WHERE meta_key = 'amazonS3_cache' ORDER BY meta_id DESC LIMIT 0, 1;";
			// phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB -- safe query, must not be cached
			$last = $wpdb->get_var( $sql );

			$this->session[ $this->blog_prefix ] = (int) $last;
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
	protected function get_items_to_process( string $prefix, int $limit, $offset = false ): array {
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
	private function get_real_count( string $prefix ): int {
		global $wpdb;

		if ( empty( $prefix ) || empty( $this->session[ $prefix ] ) || ! is_int( $this->session[ $prefix ] ) ) {
			return 0;
		}

		$meta_id = $this->session[ $prefix ];

		$sql = "SELECT count(meta_id) FROM {$prefix}postmeta WHERE meta_key = 'amazonS3_cache' AND meta_id <= %d";
		// phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB -- safe query, must not be cached
		$count = $wpdb->get_var( $wpdb->prepare( $sql, $meta_id ) );

		return (int) $count;
	}
}
