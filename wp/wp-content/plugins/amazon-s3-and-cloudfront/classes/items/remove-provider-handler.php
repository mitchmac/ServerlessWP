<?php

namespace DeliciousBrains\WP_Offload_Media\Items;

use Exception;
use WP_Error;

class Remove_Provider_Handler extends Item_Handler {
	/**
	 * @var string
	 */
	protected static $item_handler_key = 'remove-provider';

	/**
	 * The default options that should be used if none supplied.
	 *
	 * @return array
	 */
	public static function default_options() {
		return array(
			'object_keys'     => array(),
			'offloaded_files' => array(),
		);
	}

	/**
	 * Create manifest for removal from provider.
	 *
	 * @param Item  $as3cf_item
	 * @param array $options
	 *
	 * @return Manifest|WP_Error
	 */
	protected function pre_handle( Item $as3cf_item, array $options ) {
		$manifest = new Manifest();
		$paths    = array();

		if ( ! empty( $options['object_keys'] ) && ! is_array( $options['object_keys'] ) ) {
			return $this->return_handler_error( __( 'Invalid object_keys option provided.', 'amazon-s3-and-cloudfront' ) );
		}

		if ( ! empty( $options['offloaded_files'] ) && ! is_array( $options['offloaded_files'] ) ) {
			return $this->return_handler_error( __( 'Invalid offloaded_files option provided.', 'amazon-s3-and-cloudfront' ) );
		}

		if ( ! empty( $options['object_keys'] ) && ! empty( $options['offloaded_files'] ) ) {
			return $this->return_handler_error( __( 'Providing both object_keys and offloaded_files options is not supported.', 'amazon-s3-and-cloudfront' ) );
		}

		if ( empty( $options['offloaded_files'] ) ) {
			foreach ( $as3cf_item->objects() as $object_key => $object ) {
				if ( 0 < count( $options['object_keys'] ) && ! in_array( $object_key, $options['object_keys'] ) ) {
					continue;
				}
				$paths[ $object_key ] = $as3cf_item->full_source_path( $object_key );
			}
		} else {
			foreach ( $options['offloaded_files'] as $filename => $object ) {
				$paths[ $filename ] = $as3cf_item->full_source_path_for_filename( $filename );
			}
		}

		/**
		 * Filters array of source files before being removed from provider.
		 *
		 * @param array $paths       Array of local paths to be removed from provider
		 * @param Item  $as3cf_item  The Item object
		 * @param array $item_source The item source descriptor array
		 */
		$paths = apply_filters( 'as3cf_remove_source_files_from_provider', $paths, $as3cf_item, $as3cf_item->get_item_source_array() );
		$paths = array_unique( $paths );

		// Remove local source paths that other items may have offloaded.
		$paths = $as3cf_item->remove_duplicate_paths( $as3cf_item, $paths );

		// Nothing to do, shortcut out.
		if ( empty( $paths ) ) {
			return $manifest;
		}

		if ( empty( $options['offloaded_files'] ) ) {
			foreach ( $paths as $object_key => $path ) {
				$manifest->objects[] = array(
					'Key' => $as3cf_item->provider_key( $object_key ),
				);
			}
		} else {
			foreach ( $paths as $filename => $path ) {
				$manifest->objects[] = array(
					'Key' => $as3cf_item->provider_key_for_filename( $filename, $options['offloaded_files'][ $filename ]['is_private'] ),
				);
			}
		}

		return $manifest;
	}

	/**
	 * Delete provider objects described in the manifest object array
	 *
	 * @param Item     $as3cf_item
	 * @param Manifest $manifest
	 * @param array    $options
	 *
	 * @return bool|WP_Error
	 */
	protected function handle_item( Item $as3cf_item, Manifest $manifest, array $options ) {
		// This test is "late" so that we don't raise the error if there is nothing to remove.
		// If the provider of this item is different from what's currently configured,
		// we'll return an error.
		$current_provider = $this->as3cf->get_storage_provider();
		if ( ! is_null( $current_provider ) && $current_provider::get_provider_key_name() !== $as3cf_item->provider() ) {
			$error_msg = sprintf(
				__( '%1$s with ID %2$d is offloaded to a different provider than currently configured', 'amazon-s3-and-cloudfront' ),
				$this->as3cf->get_source_type_name( $as3cf_item->source_type() ),
				$as3cf_item->source_id()
			);

			return $this->return_handler_error( $error_msg );
		}

		$chunks = array_chunk( $manifest->objects, 1000 );
		$region = $as3cf_item->region();
		$bucket = $as3cf_item->bucket();

		try {
			foreach ( $chunks as $chunk ) {
				$this->as3cf->get_provider_client( $region )->delete_objects( array(
					'Bucket'  => $bucket,
					'Objects' => $chunk,
				) );
			}
		} catch ( Exception $e ) {
			$error_msg = sprintf( __( 'Error removing files from bucket: %s', 'amazon-s3-and-cloudfront' ), $e->getMessage() );

			return $this->return_handler_error( $error_msg );
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
		return true;
	}
}
