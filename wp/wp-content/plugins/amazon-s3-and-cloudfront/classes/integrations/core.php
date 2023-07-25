<?php

namespace DeliciousBrains\WP_Offload_Media\Integrations;

use DeliciousBrains\WP_Offload_Media\Items\Item;
use DeliciousBrains\WP_Offload_Media\Items\Remove_Local_Handler;
use DeliciousBrains\WP_Offload_Media\Items\Upload_Handler;
use WP_Error;

class Core extends Integration {
	/**
	 * Is installed?
	 *
	 * @return bool
	 */
	public static function is_installed(): bool {
		return true;
	}

	/**
	 * Init integration.
	 */
	public function init() {
		// Nothing to do.
	}

	/**
	 * @inheritDoc
	 */
	public function setup() {
		add_action( 'as3cf_post_handle_item_' . Upload_Handler::get_item_handler_key_name(), array( $this, 'maybe_remove_local_files' ), 10, 3 );
	}

	/**
	 * After an upload completes, maybe remove local files.
	 *
	 * @handles as3cf_post_handle_item_upload
	 *
	 * @param bool|WP_Error $result     Result for the action, either handled (true/false), or an error.
	 * @param Item          $as3cf_item The item that the action was being handled for.
	 * @param array         $options    Handler dependent options that may have been set for the action.
	 */
	public function maybe_remove_local_files( $result, Item $as3cf_item, array $options ) {
		if ( ! is_wp_error( $result ) && $as3cf_item->id() && $this->as3cf->get_setting( 'remove-local-file', false ) && $as3cf_item->exists_locally() ) {
			$remove_local_handler = $this->as3cf->get_item_handler( Remove_Local_Handler::get_item_handler_key_name() );

			$remove_local_handler->handle( $as3cf_item );
		}
	}
}
