<?php

namespace DeliciousBrains\WP_Offload_Media\Items;

use AS3CF_Error;

class Remove_Local_Handler extends Item_Handler {
	/**
	 * @var string
	 */
	protected static $item_handler_key = 'remove-local';

	/**
	 * Keep track of individual files we've already attempted to remove.
	 *
	 * @var array
	 */
	private $remove_blocked = array();

	/**
	 * Keep track of size of individual files we've already attempted to remove.
	 *
	 * @var array
	 */
	private $removed_size = array();

	/**
	 * If remove the primary file, we want to update the 'filesize'.
	 *
	 * @var int
	 */
	private $removed_primary_size = array();

	/**
	 * The default options that should be used if none supplied.
	 *
	 * @return array
	 */
	public static function default_options() {
		return array(
			'verify_exists_on_provider' => false,
			'provider_keys'             => array(),
			'files_to_remove'           => array(),
		);
	}

	/**
	 * Create manifest for local removal.
	 *
	 * @param Item  $as3cf_item
	 * @param array $options
	 *
	 * @return Manifest
	 */
	protected function pre_handle( Item $as3cf_item, array $options ) {
		$manifest        = new Manifest();
		$source_id       = $as3cf_item->source_id();
		$primary_file    = '';
		$files_to_remove = array();

		// Note: Unable to use Item::full_size_paths() here
		// as source item's metadata may not be up-to-date yet.
		foreach ( $as3cf_item->objects() as $object_key => $object ) {
			$file = $as3cf_item->full_source_path( $object_key );

			if ( in_array( $file, $this->remove_blocked ) ) {
				continue;
			}

			if ( 0 < count( $options['files_to_remove'] ) && ! in_array( $file, $options['files_to_remove'] ) ) {
				continue;
			}

			// If needed, make sure this item exists among the provider keys.
			if ( true === $options['verify_exists_on_provider'] ) {
				if ( empty( $options['provider_keys'][ $source_id ] ) ) {
					continue;
				}

				if ( ! in_array( $as3cf_item->provider_key( $object_key ), $options['provider_keys'][ $source_id ] ) ) {
					continue;
				}
			}

			if ( file_exists( $file ) ) {
				$files_to_remove[] = $file;

				if ( Item::primary_object_key() === $object_key ) {
					$primary_file = $file;
				}
			}
		}

		/**
		 * Filters array of local files before being removed from server.
		 *
		 * @param array $files_to_remove Array of paths to be removed
		 * @param Item  $as3cf_item      The Item object
		 * @param array $item_source     Item source descriptor array
		 */
		$filtered_files_to_remove = apply_filters( 'as3cf_remove_local_files', $files_to_remove, $as3cf_item, $as3cf_item->get_item_source_array() );

		// Ensure fileset is unique and does not contain files already blocked.
		$filtered_files_to_remove = array_unique( array_diff( $filtered_files_to_remove, $this->remove_blocked ) );

		// If filter removes files from list, block attempts to remove them in later calls.
		$this->remove_blocked = array_merge( $this->remove_blocked, array_diff( $files_to_remove, $filtered_files_to_remove ) );

		foreach ( $filtered_files_to_remove as $file ) {
			// Filter may have added some files to check for existence.
			if ( ! in_array( $file, $files_to_remove ) ) {
				if ( ! file_exists( $file ) ) {
					continue;
				}
			}

			/**
			 * Filter individual files that might still be kept local.
			 *
			 * @param bool   $preserve Should the file be kept on the server?
			 * @param string $file     Full path to the local file
			 */
			if ( false !== apply_filters( 'as3cf_preserve_file_from_local_removal', false, $file ) ) {
				$this->remove_blocked[] = $file;
				continue;
			}

			$manifest->objects[] = array(
				'file'       => $file,
				'size'       => filesize( $file ),
				'is_primary' => $file === $primary_file,
			);
		}

		return $manifest;
	}

	/**
	 * Delete local files described in the manifest object array.
	 *
	 * @param Item     $as3cf_item
	 * @param Manifest $manifest
	 * @param array    $options
	 *
	 * @return bool
	 */
	protected function handle_item( Item $as3cf_item, Manifest $manifest, array $options ) {
		foreach ( $manifest->objects as &$file_to_remove ) {
			$file = $file_to_remove['file'];

			$file_to_remove['remove_result'] = array( 'status' => self::STATUS_OK );

			//phpcs:ignore
			if ( ! @unlink( $file ) ) {
				$this->remove_blocked[] = $file;

				$file_to_remove['remove_result']['status']  = self::STATUS_FAILED;
				$file_to_remove['remove_result']['message'] = "Error removing local file at $file";

				if ( ! file_exists( $file ) ) {
					$file_to_remove['remove_result']['message'] = "Error removing local file. Couldn't find the file at $file";
				} elseif ( ! is_writable( $file ) ) {
					$file_to_remove['remove_result']['message'] = "Error removing local file. Ownership or permissions are mis-configured for $file";
				}
			}
		}

		return true;
	}

	/**
	 * Perform post handle tasks.
	 *
	 * @param Item     $as3cf_item
	 * @param Manifest $manifest
	 * @param array    $options
	 *
	 * @return bool
	 */
	protected function post_handle( Item $as3cf_item, Manifest $manifest, array $options ) {
		if ( empty( $manifest->objects ) ) {
			return true;
		}

		// Assume we didn't touch the primary file.
		$this->removed_primary_size[ $as3cf_item->source_id() ] = 0;

		foreach ( $manifest->objects as $file_to_remove ) {
			if ( $file_to_remove['remove_result']['status'] !== self::STATUS_OK ) {
				AS3CF_Error::log( $file_to_remove['remove_result']['message'] );
				continue;
			}

			if ( empty( $this->removed_size[ $as3cf_item->source_id() ] ) ) {
				$this->removed_size[ $as3cf_item->source_id() ] = $file_to_remove['size'];
			} else {
				$this->removed_size[ $as3cf_item->source_id() ] += $file_to_remove['size'];
			}

			if ( $file_to_remove['is_primary'] ) {
				$this->removed_primary_size[ $as3cf_item->source_id() ] = $file_to_remove['size'];
			}
		}

		$as3cf_item->update_filesize_after_remove_local( $this->removed_primary_size[ $as3cf_item->source_id() ], $this->removed_size[ $as3cf_item->source_id() ] );

		return true;
	}
}
