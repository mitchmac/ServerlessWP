<?php

namespace DeliciousBrains\WP_Offload_Media\Items;

use Amazon_S3_And_CloudFront;
use AS3CF_Error;
use Exception;
use WP_Error;

/**
 * Class Item_Handler
 *
 * Base class for item handler classes.
 *
 * @package DeliciousBrains\WP_Offload_Media\Items
 */
abstract class Item_Handler {
	/**
	 * Status codes
	 */
	const STATUS_OK     = 'ok';
	const STATUS_FAILED = 'failed';

	/**
	 * @var string
	 */
	protected static $item_handler_key;

	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	protected $as3cf;

	/**
	 * AS3CF_Item_Handler constructor.
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf
	 */
	public function __construct( Amazon_S3_And_CloudFront $as3cf ) {
		$this->as3cf = $as3cf;
	}

	/**
	 * Get the item handler key name.
	 *
	 * @return string
	 */
	public static function get_item_handler_key_name() {
		return static::$item_handler_key;
	}

	/**
	 * The default options that should be used if none supplied.
	 *
	 * @return array
	 */
	public static function default_options() {
		return array();
	}

	/**
	 * Main entrypoint for handling an item.
	 *
	 * @param Item  $as3cf_item
	 * @param array $options
	 *
	 * @return boolean|WP_Error
	 */
	public function handle( Item $as3cf_item, array $options = array() ) {
		// Merge supplied option values into the defaults as long as supplied options are recognised.
		if ( empty( $options ) || ! is_array( $options ) ) {
			$options = array();
		}
		$options = array_merge( $this->default_options(), array_intersect_key( $options, $this->default_options() ) );

		try {
			/**
			 * Filter fires before handling an action on an item, allows action to be cancelled.
			 *
			 * This is a generic handler filter that includes the handler's key name as the last param.
			 *
			 * @param bool  $cancel           Should the action on the item be cancelled?
			 * @param Item  $as3cf_item       The item that the action is being handled for.
			 * @param array $options          Handler dependent options that may have been set for the action.
			 * @param array $handler_key_name The handler's key name as per `Item_Handler::get_item_handler_key_name()`.
			 *
			 * @see Item_Handler::get_item_handler_key_name()
			 */
			$cancel = apply_filters(
				'as3cf_pre_handle_item',
				/**
				 * Filter fires before handling an action on an item, allows action to be cancelled.
				 *
				 * This is a handler specific filter whose name ends with the handler's key name.
				 * Format is `as3cf_pre_handle_item_{item-handler-key-name}`.
				 *
				 * Example filter names:
				 *
				 * as3cf_pre_handle_item_upload
				 * as3cf_pre_handle_item_download
				 * as3cf_pre_handle_item_remove-local
				 * as3cf_pre_handle_item_remove-provider
				 * as3cf_pre_handle_item_update-acl
				 *
				 * For a more generic filter, use `as3cf_pre_handle_item`.
				 *
				 * @param bool  $cancel     Should the action on the item be cancelled?
				 * @param Item  $as3cf_item The item that the action is being handled for.
				 * @param array $options    Handler dependent options that may have been set for the action.
				 *
				 * @see Item_Handler::get_item_handler_key_name()
				 */
				apply_filters( 'as3cf_pre_handle_item_' . static::get_item_handler_key_name(), false, $as3cf_item, $options ),
				$as3cf_item,
				$options,
				static::get_item_handler_key_name()
			);
		} catch ( Exception $e ) {
			return $this->return_result( new WP_Error( $e->getMessage() ), $as3cf_item, $options );
		}

		// Cancelled, let caller know that request was not handled.
		if ( false !== $cancel ) {
			// If something unexpected happened, let the caller know.
			if ( is_wp_error( $cancel ) ) {
				return $this->return_result( $cancel, $as3cf_item, $options );
			}

			return $this->return_result( false, $as3cf_item, $options );
		}

		$manifest = $this->pre_handle( $as3cf_item, $options );
		if ( is_wp_error( $manifest ) ) {
			return $this->return_result( $manifest, $as3cf_item, $options );
		}

		// Nothing to do, let caller know that request was not handled.
		if ( empty( $manifest ) || empty( $manifest->objects ) ) {
			return $this->return_result( false, $as3cf_item, $options );
		}

		$result = $this->handle_item( $as3cf_item, $manifest, $options );
		if ( is_wp_error( $result ) ) {
			return $this->return_result( $result, $as3cf_item, $options );
		}

		$result = $this->post_handle( $as3cf_item, $manifest, $options );

		return $this->return_result( $result, $as3cf_item, $options );
	}

	/**
	 * Process an Item and options to generate a Manifest for `handle_item`.
	 *
	 * @param Item  $as3cf_item
	 * @param array $options
	 *
	 * @return Manifest|WP_Error
	 */
	abstract protected function pre_handle( Item $as3cf_item, array $options );

	/**
	 * Perform action for Item using given Manifest.
	 *
	 * @param Item     $as3cf_item
	 * @param Manifest $manifest
	 * @param array    $options
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function handle_item( Item $as3cf_item, Manifest $manifest, array $options );

	/**
	 * Process results of `handle_item` as appropriate.
	 *
	 * @param Item     $as3cf_item
	 * @param Manifest $manifest
	 * @param array    $options
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function post_handle( Item $as3cf_item, Manifest $manifest, array $options );

	/**
	 * Helper to record errors and return them or optional supplied value.
	 *
	 * @param string|WP_Error $error_msg An error message or already constructed WP_Error.
	 * @param mixed|null      $return    Optional return value instead of WP_Error.
	 *
	 * @return mixed|WP_Error
	 */
	protected function return_handler_error( $error_msg, $return = null ) {
		if ( is_wp_error( $error_msg ) ) {
			foreach ( $error_msg->get_error_messages() as $msg ) {
				AS3CF_Error::Log( $msg );
			}
		} else {
			AS3CF_Error::log( $error_msg );
		}

		if ( is_null( $return ) ) {
			return is_wp_error( $error_msg ) ? $error_msg : new WP_Error( 'exception', $error_msg );
		}

		return $return;
	}

	/**
	 * Fires a couple of actions to let interested parties know that a handler has returned a result.
	 *
	 * @param bool|WP_Error $result     Result for the action, either handled (true/false), or an error.
	 * @param Item          $as3cf_item The item that the action was being handled for.
	 * @param array         $options    Handler dependent options that may have been set for the action.
	 *
	 * @return bool|WP_Error
	 */
	private function return_result( $result, Item $as3cf_item, array $options ) {
		/**
		 * Action fires after attempting to handle an action on an item.
		 *
		 * This is a handler specific action whose name ends with the handler's key name.
		 * Format is `as3cf_post_handle_item_{item-handler-key-name}`.
		 *
		 * Example filter names:
		 *
		 * as3cf_post_handle_item_upload
		 * as3cf_post_handle_item_download
		 * as3cf_post_handle_item_remove-local
		 * as3cf_post_handle_item_remove-provider
		 * as3cf_post_handle_item_update-acl
		 *
		 * For a more generic filter, use `as3cf_post_handle_item`.
		 *
		 * @param bool|WP_Error $result     Result for the action, either handled (true/false), or an error.
		 * @param Item          $as3cf_item The item that the action was being handled for.
		 * @param array         $options    Handler dependent options that may have been set for the action.
		 *
		 * @see Item_Handler::get_item_handler_key_name()
		 */
		do_action( 'as3cf_post_handle_item_' . static::get_item_handler_key_name(), $result, $as3cf_item, $options );

		/**
		 * Action fires after attempting to handle an action on an item.
		 *
		 * This is a generic handler action that includes the handler's key name as the last param.
		 *
		 * @param bool|WP_Error $result           Result for the action, either handled (true/false), or an error.
		 * @param Item          $as3cf_item       The item that the action was being handled for.
		 * @param array         $options          Handler dependent options that may have been set for the action.
		 * @param array         $handler_key_name The handler's key name as per `Item_Handler::get_item_handler_key_name()`.
		 *
		 * @see Item_Handler::get_item_handler_key_name()
		 */
		do_action( 'as3cf_post_handle_item', $result, $as3cf_item, $options, static::get_item_handler_key_name() );

		return $result;
	}
}