<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

/**
 * Upgrade_Clear_Postmeta_Cache Class
 *
 * This upgrade is redundant, superseded by upgrade 14,
 * but needs to be kept for sequence continuity.
 *
 * @since 2.6.1
 */
class Upgrade_Clear_Postmeta_Cache extends Upgrade {

	/**
	 * @var int
	 */
	protected int $upgrade_id = 11;

	/**
	 * @var string
	 */
	protected string $upgrade_name = 'clear_postmeta_cache';

	/**
	 * @var string 'metadata', 'attachment'
	 */
	protected string $upgrade_type = 'metadata';

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
		return true;
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
		return array();
	}
}
