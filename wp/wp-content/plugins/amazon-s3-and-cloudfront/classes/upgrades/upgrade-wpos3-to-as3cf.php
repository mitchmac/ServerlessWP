<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use AS3CF_Error;
use Exception;

/**
 * Upgrade_WPOS3_To_AS3CF Class
 *
 * This class handles updating records to use as3cf prefixed keys instead of wpos3 prefixed keys.
 */
class Upgrade_WPOS3_To_AS3CF extends Upgrade {

	/**
	 * @var int
	 */
	protected $upgrade_id = 7;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'wpos3_to_as3cf';

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
		return __( 'and updating the metadata to use key names compatible with the current version.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Update record key to use as3cf prefix instead of wpos3.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	protected function upgrade_item( $item ) {
		global $wpdb;

		$old = $item->the_value;
		$new = substr_replace( $old, 'as3cf_', 0, strlen( 'wpos3_' ) );

		try {
			$result = $wpdb->update( $wpdb->{$item->the_table}, array( $item->the_field => $new ), array( $item->the_field => $old ) );
		} catch ( Exception $e ) {
			AS3CF_Error::log( 'Error updating ' . $item->the_table . ' records with key ' . $old . ' to use key ' . $new . ': ' . $e->getMessage() );
			$this->error_count++;

			return false;
		}

		if ( false === $result || 1 > $result ) {
			AS3CF_Error::log( 'Incorrect number of ' . $item->the_table . ' records with key ' . $old . ' updated to use key ' . $new . '.' );
			$this->error_count++;

			return false;
		}

		return true;
	}

	/**
	 * Get all record keys that need changing.
	 *
	 * @param string     $prefix
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	protected function get_items_to_process( $prefix, $limit, $offset = false ) {
		global $wpdb;

		$sql = "SELECT DISTINCT 'postmeta' AS the_table, 'meta_key' AS the_field, pm.`meta_key` AS the_value
				FROM `{$prefix}postmeta` pm
				WHERE pm.`meta_key` LIKE 'wpos3_%'
				UNION
				SELECT DISTINCT 'options' AS the_table, 'option_name' AS the_field, o.`option_name` AS the_value
				FROM `{$prefix}options` o
				WHERE o.`option_name` LIKE 'wpos3_%'
				";

		if ( is_multisite() ) {
			$sql .= "
				UNION
				SELECT DISTINCT 'sitemeta' AS the_table, 'meta_key' AS the_field, sm.`meta_key` AS the_value
				FROM `{$wpdb->sitemeta}` sm
				WHERE sm.`meta_key` LIKE 'wpos3_%'
				";
		}

		if ( $limit && $limit > 0 ) {
			$sql .= sprintf( ' LIMIT %d', (int) $limit );
		}

		return $wpdb->get_results( $sql, OBJECT );
	}
}
