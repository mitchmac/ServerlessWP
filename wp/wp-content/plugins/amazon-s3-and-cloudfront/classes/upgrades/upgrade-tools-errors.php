<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use DeliciousBrains\WP_Offload_Media\Pro\Tool;
use DeliciousBrains\WP_Offload_Media\Pro\Tools_Manager;

/**
 * Upgrade_Tools_Errors Class
 *
 * This class handles updating internal error info from previous tools executions
 *
 * @since 2.6.0
 */
class Upgrade_Tools_Errors extends Upgrade {

	/**
	 * @var int
	 */
	protected $upgrade_id = 9;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'tools_error';

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
		return __( 'and reformatting internal data about previous errors from tools.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Get an array of tool names that may have saved error info
	 *
	 * @param string     $prefix
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	protected function get_items_to_process( $prefix, $limit, $offset = false ) {
		global $as3cf;

		if ( get_class( $as3cf ) !== 'Amazon_S3_And_CloudFront_Pro' ) {
			return array();
		}

		$tools_manager = Tools_Manager::get_instance( $as3cf );
		$tools         = $tools_manager->get_tools();

		return array_keys( $tools );
	}

	/**
	 * Update saved errors for a tool.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	protected function upgrade_item( $item ) {
		global $as3cf;

		if ( empty( $item ) || ! is_string( $item ) ) {
			// We really don't want to this upgrade to fail,
			// broken notices can still be dismissed, so just move on.
			return true;
		}

		$tools_manager = Tools_Manager::get_instance( $as3cf );
		$tools         = $tools_manager->get_tools();

		if ( ! empty( $tools[ $item ] ) ) {
			/** @var Tool $tool */
			$tool = $tools[ $item ];

			$errors     = $tool->get_errors();
			$new_errors = array();

			if ( ! empty( $errors ) ) {
				foreach ( $errors as $blog_id => $blog ) {
					foreach ( $blog as $attachment_id => $messages ) {
						$new_errors[] = (object) array(
							'blog_id'     => $blog_id,
							'source_type' => 'media-library',
							'source_id'   => $attachment_id,
							'messages'    => (array) $messages,
						);
					}
				}

				$tool->update_errors( $new_errors );
			}
		}

		return true;
	}
}
