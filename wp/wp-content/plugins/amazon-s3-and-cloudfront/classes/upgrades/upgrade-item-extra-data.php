<?php
/**
 * Upgrade extra info in custom objects table.
 *
 * This upgrade is redundant, superseded by upgrade 12,
 * but needs to be kept for sequence continuity.
 *
 * @package     amazon-s3-and-cloudfront
 * @subpackage  Classes/Upgrades/Upgrade_Item_Extra_Data
 * @copyright   Copyright (c) 2021, Delicious Brains
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.6.0
 */

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

/**
 * Upgrade_Item_Extra_Data Class
 *
 * This class handles updating extra info in the custom objects table.
 *
 * @since 2.6.0
 */
class Upgrade_Item_Extra_Data extends Upgrade {

	/**
	 * @var int
	 */
	protected int $upgrade_id = 10;

	/**
	 * @var string
	 */
	protected string $upgrade_name = 'item_extra_data';

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
		return __( 'and updating metadata about offloaded items to new format.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Update extra_info in items table.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	protected function upgrade_item( mixed $item ): bool {
		return true;
	}

	/**
	 * Get all items to be processed.
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
