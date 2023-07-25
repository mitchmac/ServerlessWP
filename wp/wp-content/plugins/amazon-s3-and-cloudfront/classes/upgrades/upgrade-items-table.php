<?php
/**
 * Upgrade Metadata to use custom objects table.
 *
 * @package     amazon-s3-and-cloudfront
 * @subpackage  Classes/Upgrades/Upgrade_Items_Table
 * @copyright   Copyright (c) 2014, Delicious Brains
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.3.0
 */

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;

/**
 * Upgrade_Items_Table Class
 *
 * This class handles updating the offload metadata for attachments to use a custom table.
 *
 * @since 2.3.0
 */
class Upgrade_Items_Table extends Upgrade {

	/**
	 * @var int
	 */
	protected $upgrade_id = 8;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'as3cf_items_table';

	/**
	 * @var string 'metadata', 'attachment'
	 */
	protected $upgrade_type = 'metadata';

	/**
	 * Get running update text.
	 *
	 * @return string
	 */
	protected function get_running_update_text() {
		return __( 'and updating the plugin\'s metadata to use a faster storage method. During the update the site\'s total offloaded media count may be inaccurate but will settle down shortly after completing.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Move an attachment's provider object data from the postmeta table to the custom as3cf_objects table.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	protected function upgrade_item( $item ) {
		$provider_object = AS3CF_Utils::maybe_fix_serialized_string( $item->provider_object );
		$fixed           = $item->provider_object !== $provider_object;

		// Make sure legacy metadata isn't broken.
		$provider_object = unserialize( $provider_object );

		if ( false === $provider_object ) {
			AS3CF_Error::log( 'Failed to unserialize legacy offload metadata for attachment ' . $item->ID . ': ' . $item->provider_object );
			$this->error_count++;

			return false;
		}

		if ( empty( $item->source_path ) ) {
			AS3CF_Error::log( 'Attachment with ID ' . $item->ID . ' with legacy offload metadata has no local file path.' );
			$this->error_count++;

			return false;
		}

		if ( $fixed ) {
			if ( update_post_meta( $item->ID, 'amazonS3_info', $provider_object ) ) {
				$msg = sprintf( __( 'Fixed legacy amazonS3_info metadata when moved to %1$s table, please check bucket and path for attachment ID %2$s', 'amazon-s3-and-cloudfront' ), Media_Library_Item::items_table(), $item->ID );
				AS3CF_Error::log( $msg );
			} else {
				AS3CF_Error::log( 'Failed to fix broken serialized legacy offload metadata for attachment ' . $item->ID . ': ' . $item->provider_object );
				$this->error_count++;

				return false;
			}
		}

		// Using Media_Library_Item::get_by_source_id falls back to legacy metadata and substitutes in defaults and potentially missing values.
		// If we're here we already know there's legacy metadata and that there isn't a new items table record yet,
		// or there's legacy metadata and an existing items table record that we can just re-save without issue before deleting legacy metadata.
		// An existing items table entry takes precedence over legacy metadata to avoid accidental overrides from migrations, custom code or other plugins.
		$as3cf_item = Media_Library_Item::get_by_source_id( $item->ID );

		if ( ! $as3cf_item ) {
			AS3CF_Error::log( 'Could not construct item for attachment with ID ' . $item->ID . ' from legacy offload metadata.' );
			$this->error_count++;

			return false;
		}

		$result = $as3cf_item->save();

		if ( is_wp_error( $result ) ) {
			AS3CF_Error::log( 'Error saving item: ' . $result->get_error_message() );
			$this->error_count++;

			return false;
		}

		// Delete old metadata.
		return delete_post_meta( $item->ID, 'amazonS3_info' );
	}

	/**
	 * Get a count of all attachments to be processed.
	 * for the whole site
	 *
	 * @return int
	 */
	protected function count_items_to_process() {
		return $this->count_attachments_with_legacy_metadata( $this->blog_prefix );
	}

	/**
	 * Get all attachments to be processed.
	 *
	 * @param string     $prefix Table prefix for blog.
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	protected function get_items_to_process( $prefix, $limit, $offset = false ) {
		$attachments = $this->get_attachments_with_legacy_metadata( $prefix, false, $limit );

		return $attachments;
	}

	/**
	 * Get a count of attachments that have legacy metadata.
	 *
	 * @param string $prefix Table prefix for blog.
	 *
	 * @return int
	 */
	protected function count_attachments_with_legacy_metadata( $prefix ) {
		$count = $this->get_attachments_with_legacy_metadata( $prefix, true );

		return $count;
	}

	/**
	 * Wrapper for database call to get attachments with legacy metadata.
	 *
	 * @param string   $prefix Table prefix for blog.
	 * @param bool     $count  return count of attachments
	 * @param null|int $limit
	 *
	 * @return mixed
	 */
	protected function get_attachments_with_legacy_metadata( $prefix, $count = false, $limit = null ) {
		global $wpdb;

		$sql = "
			FROM {$prefix}postmeta AS a, {$prefix}postmeta AS p
			WHERE a.meta_key = '_wp_attached_file'
			AND p.meta_key = 'amazonS3_info'
			AND a.post_id = p.post_id
		";

		if ( $count ) {
			$sql = 'SELECT COUNT(DISTINCT p.post_id)' . $sql;

			return $wpdb->get_var( $sql );
		}

		$sql = 'SELECT a.post_id AS ID, p.meta_id AS po_id, a.meta_value AS source_path, p.meta_value AS provider_object' . $sql;
		$sql .= ' ORDER BY ID, po_id';

		if ( $limit && $limit > 0 ) {
			$sql .= sprintf( ' LIMIT %d', (int) $limit );
		}

		return $wpdb->get_results( $sql, OBJECT );
	}
}
