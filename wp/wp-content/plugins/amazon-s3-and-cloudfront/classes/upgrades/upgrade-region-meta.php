<?php
/**
 * Upgrade Region in Meta
 *
 * @package     amazon-s3-and-cloudfront
 * @subpackage  Classes/Upgrades/Region-Meta
 * @copyright   Copyright (c) 2014, Delicious Brains
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.6.2
 */

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;

/**
 * Upgrade_Region_Meta Class
 *
 * This class handles updating the region of the attachment's bucket in the meta data
 *
 * @since 0.6.2
 */
class Upgrade_Region_Meta extends Upgrade {

	/**
	 * @var int
	 */
	protected $upgrade_id = 1;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'meta_with_region';

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
		return __( 'and updating the metadata with the bucket region it is served from. This will allow us to serve your files from the proper region subdomain <span style="white-space:nowrap;">(e.g. s3-us-west-2.amazonaws.com)</span>.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Get the region for the bucket where an attachment is located, update the S3 meta.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	protected function upgrade_item( $item ) {
		$provider_object = AS3CF_Utils::maybe_fix_serialized_string( $item->provider_object );
		$fixed           = $item->provider_object !== $provider_object;

		$provider_object = unserialize( $provider_object );

		if ( false === $provider_object ) {
			AS3CF_Error::log( 'Failed to unserialize offload meta for attachment ' . $item->ID . ': ' . $item->provider_object );
			$this->error_count++;

			return false;
		}

		if ( $fixed ) {
			if ( update_post_meta( $item->ID, 'amazonS3_info', $provider_object ) ) {
				$msg = sprintf( __( 'Fixed legacy amazonS3_info metadata when updating its region, please check bucket and path for attachment ID %1$s', 'amazon-s3-and-cloudfront' ), $item->ID );
				AS3CF_Error::log( $msg );
			} else {
				AS3CF_Error::log( 'Failed to fix broken serialized legacy offload metadata for attachment ' . $item->ID . ': ' . $item->provider_object );
				$this->error_count++;

				return false;
			}
		}

		// Using Media_Library_Item::get_by_source_id falls back to legacy metadata and substitutes in defaults and potentially missing values.
		$as3cf_item = Media_Library_Item::get_by_source_id( $item->ID );

		if ( ! $as3cf_item ) {
			AS3CF_Error::log( 'Could not construct item for attachment with ID ' . $item->ID . ' from legacy offload metadata.' );
			$this->error_count++;

			return false;
		}

		// Update legacy amazonS3_info record with region required for subsequent upgrades.
		$provider_object['region'] = $as3cf_item->region();

		$result = update_post_meta( $item->ID, 'amazonS3_info', $provider_object );

		if ( false === $result ) {
			AS3CF_Error::log( 'Error updating region in legacy offload metadata for attachment ' . $item->ID );
			$this->error_count++;

			return false;
		}

		return true;
	}

	/**
	 * Get a count of all attachments without region in their S3 metadata
	 * for the whole site
	 *
	 * @return int
	 */
	protected function count_items_to_process() {
		return $this->count_attachments_without_region( $this->blog_prefix );
	}

	/**
	 * Get all attachments that don't have region in their S3 meta data for a blog
	 *
	 * @param string     $prefix
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	protected function get_items_to_process( $prefix, $limit, $offset = false ) {
		$attachments = $this->get_attachments_without_region_results( $prefix, false, $limit );

		return $attachments;
	}

	/**
	 * Get a count of attachments that don't have region in their S3 meta data for a blog
	 *
	 * @param string $prefix
	 *
	 * @return int
	 */
	protected function count_attachments_without_region( $prefix ) {
		$count = $this->get_attachments_without_region_results( $prefix, true );

		return $count;
	}

	/**
	 * Wrapper for database call to get attachments without region
	 *
	 * @param string   $prefix
	 * @param bool     $count return count of attachments
	 * @param null|int $limit
	 *
	 * @return mixed
	 */
	protected function get_attachments_without_region_results( $prefix, $count = false, $limit = null ) {
		global $wpdb;

		$sql = " FROM `{$prefix}postmeta`
				WHERE `meta_key` = 'amazonS3_info'
				AND `meta_value` NOT LIKE '%%\"region\"%%'";

		if ( $count ) {
			$sql = 'SELECT COUNT(*)' . $sql;

			return $wpdb->get_var( $sql );
		}

		$sql = "SELECT `post_id` as `ID`, `meta_value` AS 'provider_object'" . $sql;

		if ( $limit && $limit > 0 ) {
			$sql .= sprintf( ' LIMIT %d', (int) $limit );
		}

		return $wpdb->get_results( $sql, OBJECT );
	}
}
