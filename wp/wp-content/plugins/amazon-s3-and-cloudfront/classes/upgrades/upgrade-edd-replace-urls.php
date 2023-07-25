<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;

/**
 * Upgrade_EDD_Replace_URLs Class
 *
 * This class handles replacing all S3 URLs in EDD
 * downloads with the local URL.
 *
 * @since 1.2
 */
class Upgrade_EDD_Replace_URLs extends Upgrade {

	/**
	 * @var int
	 */
	protected $upgrade_id = 5;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'replace_edd_urls';

	/**
	 * @var string 'metadata', 'attachment'
	 */
	protected $upgrade_type = 'post meta';

	/**
	 * Get running update text.
	 *
	 * @return string
	 */
	protected function get_running_update_text() {
		return __( 'and ensuring that only the local URL exists in EDD post meta.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Get items to process.
	 *
	 * @param string     $prefix
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	protected function get_items_to_process( $prefix, $limit, $offset = false ) {
		global $wpdb;

		$sql = "SELECT * FROM `{$prefix}postmeta` WHERE meta_key = 'edd_download_files'";

		if ( false !== $offset ) {
			$sql .= " AND meta_id > {$offset->meta_id}";
		}

		if ( $limit && $limit > 0 ) {
			$sql .= sprintf( ' LIMIT %d', (int) $limit );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Upgrade item.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	protected function upgrade_item( $item ) {
		$attachments = maybe_unserialize( $item->meta_value );

		if ( ! is_array( $attachments ) || empty( $attachments ) ) {
			// No attachments to process, return
			return false;
		}

		foreach ( $attachments as $key => $attachment ) {
			if ( ! isset( $attachment['attachment_id'] ) || ! isset( $attachment['file'] ) ) {
				// Can't determine ID or file, continue
				continue;
			}

			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment['attachment_id'] );
			if ( empty( $as3cf_item ) ) {
				continue;
			}

			if ( $url = $as3cf_item->get_local_url() ) {
				$attachments[ $key ]['file'] = $url;
			}
		}

		update_post_meta( $item->post_id, 'edd_download_files', $attachments );

		return true;
	}

}