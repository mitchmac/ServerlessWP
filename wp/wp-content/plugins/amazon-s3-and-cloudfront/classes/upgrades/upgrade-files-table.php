<?php
/**
 * Upgrade object metadata to use new as3cf_files table.
 *
 * @package     amazon-s3-and-cloudfront
 * @subpackage  Classes/Upgrades/Upgrade_Files_Table
 * @copyright   Copyright (c) 2026, Delicious Brains
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.4.0
 */

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use AS3CF_Error;
use DeliciousBrains\WP_Offload_Media\Items\Item;

/**
 * Upgrade_Files_Table Class
 *
 * This class handles updating the object metadata to use the new as3cf_files table.
 *
 * @since 3.4.0
 */
class Upgrade_Files_Table extends Upgrade {

	/**
	 * @var int
	 */
	protected int $upgrade_id = 13;

	/**
	 * @var string
	 */
	protected string $upgrade_name = 'as3cf_files_table';

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
		return __(
			'and updating the plugin\'s metadata to use a faster storage method.',
			'amazon-s3-and-cloudfront'
		);
	}

	/**
	 * Retrieve each Item and re-save so that Files created if not already.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	protected function upgrade_item( mixed $item ): bool {
		// This should never happen, but if it does, we need to know about it ...
		if ( empty( $item->id ) ) {
			AS3CF_Error::log( 'Critical issue for upgrade routine ' . __METHOD__ . ':' );
			AS3CF_Error::log( 'ID property missing for item:' );
			AS3CF_Error::log( $item );
			$this->error_count++;

			return false;
		}

		if ( empty( $item->source_type ) ) {
			AS3CF_Error::log( 'Source Type property missing for item with ID ' . $item->id . '.' );
			$this->error_count++;

			return false;
		}

		/** @var Item $class */
		$class = $this->as3cf->get_source_type_class( $item->source_type );

		if ( ! $class ) {
			AS3CF_Error::log( 'Could not map Source Type to Item class for item with ID ' . $item->id . '.' );
			$this->error_count++;

			return false;
		}

		Item::disable_cache();
		$as3cf_item = $class::get_by_id( $item->id );
		Item::enable_cache();

		if ( ! $as3cf_item ) {
			AS3CF_Error::log( 'Could not construct item with ID ' . $item->id . '.' );
			$this->error_count++;

			return false;
		}

		// Make sure last_upgrade_routine updated.
		$as3cf_item->set_last_upgrade_routine( $this->upgrade_id );

		// Saving the item will upgrade it to the latest schema version.
		$result = $as3cf_item->save();

		if ( is_wp_error( $result ) ) {
			AS3CF_Error::log( 'Error saving item: ' . $result->get_error_message() );
			$this->error_count++;

			return false;
		}

		return true;
	}

	/**
	 * Count items left to process for the current blog.
	 *
	 * @return int
	 */
	protected function count_items_to_process(): int {
		return $this->count_items_with_old_last_upgrade_routine( $this->blog_prefix );
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
		return $this->get_items_with_old_last_upgrade_routine( $prefix, false, $limit );
	}

	/**
	 * Get a count of items that have legacy extra info.
	 *
	 * @param string $prefix Table prefix for blog.
	 *
	 * @return int
	 */
	protected function count_items_with_old_last_upgrade_routine( string $prefix ): int {
		return $this->get_items_with_old_last_upgrade_routine( $prefix, true );
	}

	/**
	 * Wrapper for database call to get items not yet upgraded.
	 *
	 * @param string   $prefix Table prefix for blog.
	 * @param bool     $count  Return count of items.
	 * @param int|null $limit
	 *
	 * @return mixed
	 */
	protected function get_items_with_old_last_upgrade_routine(
		string $prefix,
		bool $count = false,
		?int $limit = null
	): mixed {
		global $wpdb;

		// This will initialize or upgrade the table in the current blog if need be.
		$table = Item::get_table_name();

		// Check that things haven't gone horribly wrong and prefix is out of sync with current blog.
		$prefixed_table = $prefix . Item::get_base_table_name();

		// Stop the upgrade dead if table name is not as expected.
		if ( $prefixed_table !== $table ) {
			AS3CF_Error::log( 'Critical issue for upgrade routine ' . __METHOD__ . ':' );
			AS3CF_Error::log( 'Expected table name ' . $prefixed_table . ' but got ' . $table . '.' );
			$this->error_count = $this->error_threshold + 1;

			return array();
		}

		/**
		 * Find items with legacy or broken extra_info data.
		 */
		$sql = ' FROM ' . $table . ' WHERE last_upgrade_routine < ' . $this->upgrade_id;

		if ( $count ) {
			$sql = 'SELECT COUNT(id)' . $sql;

			// phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter -- safe query, must not be cached
			return $wpdb->get_var( $sql );
		}

		$sql = 'SELECT id, source_type' . $sql;
		$sql .= ' ORDER BY id';

		if ( $limit && $limit > 0 ) {
			$sql .= sprintf( ' LIMIT %d', $limit );
		}

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- safe query, must not be cached
		return $wpdb->get_results( $sql, OBJECT );
	}
}
