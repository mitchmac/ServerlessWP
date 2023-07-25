<?php

namespace DeliciousBrains\WP_Offload_Media\Items;

use AS3CF_Error;
use AS3CF_Utils;
use Exception;
use WP_Error;

class Upload_Handler extends Item_Handler {
	/**
	 * @var string
	 */
	protected static $item_handler_key = 'upload';

	/**
	 * Keep track of individual files we've already attempted to upload
	 *
	 * @var array
	 */
	protected $attempted_upload = array();

	/**
	 * The default options that should be used if none supplied.
	 *
	 * @return array
	 */
	public static function default_options() {
		return array(
			'offloaded_files' => array(),
		);
	}

	/**
	 * Prepare item for uploading by running filters, updating
	 *
	 * @param Item  $as3cf_item
	 * @param array $options
	 *
	 * @return Manifest|WP_Error
	 */
	protected function pre_handle( Item $as3cf_item, array $options ) {
		$manifest         = new Manifest();
		$source_type_name = $this->as3cf->get_source_type_name( $as3cf_item->source_type() );
		$primary_key      = Item::primary_object_key();

		// Check for valid file path before attempting upload
		if ( empty( $as3cf_item->source_path() ) ) {
			$error_msg = sprintf( __( '%1$s with id %2$d does not have a valid file path', 'amazon-s3-and-cloudfront' ), $source_type_name, $as3cf_item->source_id() );

			return $this->return_handler_error( $error_msg );
		}

		// Ensure path is a string
		if ( ! is_string( $as3cf_item->source_path() ) ) {
			$error_msg = sprintf( __( '%1$s with id %2$d. Provided path is not a string', 'amazon-s3-and-cloudfront' ), $source_type_name, $as3cf_item->source_id() );

			return $this->return_handler_error( $error_msg );
		}

		// Ensure primary source file exists for new offload.
		if ( empty( $as3cf_item->id() ) && ! file_exists( $as3cf_item->full_source_path( $primary_key ) ) ) {
			$error_msg = sprintf( __( 'Primary file %1$s for %2$s with id %3$s does not exist', 'amazon-s3-and-cloudfront' ), $as3cf_item->full_source_path( $primary_key ), $source_type_name, $as3cf_item->source_id() );

			return $this->return_handler_error( $error_msg );
		}

		// Get primary file's stats.
		$file_name     = wp_basename( $as3cf_item->source_path() );
		$file_type     = wp_check_filetype_and_ext( $as3cf_item->full_source_path(), $file_name );
		$allowed_types = $this->as3cf->get_allowed_mime_types();

		// check mime type of file is in allowed provider mime types
		if ( ! in_array( $file_type['type'], $allowed_types, true ) ) {
			$error_msg = sprintf( __( 'Mime type "%1$s" is not allowed (%2$s with id %3$s)', 'amazon-s3-and-cloudfront' ), $file_type['type'], $source_type_name, $as3cf_item->source_id() );

			return $this->return_handler_error( $error_msg );
		}

		$default_acl = $this->as3cf->get_storage_provider()->get_default_acl();
		$private_acl = $this->as3cf->get_storage_provider()->get_private_acl();

		foreach ( $as3cf_item->objects() as $object_key => $object ) {
			// Avoid attempting uploading to an item that doesn't have the primary file in place.
			if ( $primary_key !== $object_key && empty( $as3cf_item->id() ) && ! isset( $manifest->objects[ $primary_key ] ) ) {
				continue;
			}

			$source_path = $as3cf_item->full_source_path( $object_key );

			// If the file has already been offloaded,
			// don't try and (fail to) re-offload if the file isn't available.
			if ( $this->in_offloaded_files( $object['source_file'], $options ) && ! file_exists( $source_path ) ) {
				continue;
			}

			/**
			 * This filter allows you to change the public/private status of an individual file associated
			 * with an uploaded item before it's uploaded to the provider.
			 *
			 * @param bool   $is_private Should the object be private?
			 * @param string $object_key A unique file identifier for a composite item, e.g. image's "size" such as full, small, medium, large.
			 * @param Item   $as3cf_item The item being uploaded.
			 *
			 * @return bool
			 */
			$is_private = apply_filters( 'as3cf_upload_object_key_as_private', $as3cf_item->is_private( $object_key ), $object_key, $as3cf_item );
			$as3cf_item->set_is_private( $is_private, $object_key );

			$object_acl = $as3cf_item->is_private( $object_key ) ? $private_acl : $default_acl;

			$args = array(
				'Bucket'       => $as3cf_item->bucket(),
				'Key'          => $as3cf_item->path( $object_key ),
				'SourceFile'   => $source_path,
				'ContentType'  => AS3CF_Utils::get_mime_type( $object['source_file'] ),
				'CacheControl' => 'max-age=31536000',
			);

			// Only set ACL if actually required, some storage provider and bucket settings disable changing ACL.
			if ( ! empty( $object_acl ) && $this->as3cf->use_acl_for_intermediate_size( 0, $object_key, $as3cf_item->bucket(), $as3cf_item ) ) {
				$args['ACL'] = $object_acl;
			}

			// TODO: Remove GZIP functionality.
			// Handle gzip on supported items
			if (
				$this->should_gzip_file( $source_path, $as3cf_item->source_type() ) &&
				false !== ( $gzip_body = gzencode( file_get_contents( $source_path ) ) )
			) {
				unset( $args['SourceFile'] );

				$args['Body']            = $gzip_body;
				$args['ContentEncoding'] = 'gzip';
			}

			/**
			 * This filter allows you to change the arguments passed to the cloud storage SDK client when
			 * offloading a file to the bucket.
			 *
			 * Note: It is possible to change the destination 'Bucket' only while processing the primary object_key.
			 *       All other object_keys will use the same bucket as the item's primary object.
			 *       The 'Key' should be the "public" Key path. If a private prefix is configured
			 *       for use with signed CloudFront URLs or similar, that prefix will be added later.
			 *       A change to the 'Key' will only be handled when processing the primary object key.
			 *
			 * @param array  $args        Information to be sent to storage provider during offload (e.g. PutObject)
			 * @param int    $source_id   Original file's unique ID for its source type
			 * @param string $object_key  A unique file identifier for a composite item, e.g. image's "size" such as full, small, medium, large
			 * @param bool   $copy        True if the object is being copied between buckets
			 * @param array  $item_source Item source array containing source type and id
			 *
			 * @return array
			 */
			$args = apply_filters( 'as3cf_object_meta', $args, $as3cf_item->source_id(), $object_key, false, $as3cf_item->get_item_source_array() );

			// If the bucket is changed by the filter while processing the primary object,
			// we should try and use that bucket for the item.
			// If the bucket name is invalid, revert to configured bucket but log it.
			// We don't abort here as ephemeral filesystems need to be accounted for,
			// and the configured bucket is at least known to work.
			if ( $primary_key === $object_key && $as3cf_item->bucket() !== $args['Bucket'] && empty( $as3cf_item->id() ) ) {
				$bucket = $this->as3cf->check_bucket( $args['Bucket'] );

				if ( $bucket ) {
					$region = $this->as3cf->get_bucket_region( $bucket );

					if ( is_wp_error( $region ) ) {
						unset( $region );
					}
				}

				if ( empty( $bucket ) || empty( $region ) ) {
					$mesg = sprintf(
						__( 'Bucket name "%1$s" is invalid, using "%2$s" instead.', 'amazon-s3-and-cloudfront' ),
						$args['Bucket'],
						$as3cf_item->bucket()
					);
					AS3CF_Error::log( $mesg );
					$args['Bucket'] = $as3cf_item->bucket();
				} else {
					$args['Bucket'] = $bucket;
					$as3cf_item->set_bucket( $bucket );
					$as3cf_item->set_region( $region );
				}

				unset( $bucket, $region );
			} elseif ( $primary_key === $object_key && $as3cf_item->bucket() !== $args['Bucket'] && ! empty( $as3cf_item->id() ) ) {
				$args['Bucket'] = $as3cf_item->bucket();
				AS3CF_Error::log( __( 'The bucket may not be changed via filters for a previously offloaded item.', 'amazon-s3-and-cloudfront' ) );
			} elseif ( $primary_key !== $object_key && $as3cf_item->bucket() !== $args['Bucket'] ) {
				$args['Bucket'] = $as3cf_item->bucket();
			}

			// If the Key has been changed for the primary object key, then that should be reflected in the item.
			if ( $primary_key === $object_key && $as3cf_item->path( $object_key ) !== $args['Key'] && empty( $as3cf_item->id() ) ) {
				$prefix = AS3CF_Utils::trailingslash_prefix( dirname( $args['Key'] ) );

				if ( $prefix === '.' ) {
					$prefix = '';
				}

				$as3cf_item->update_path_prefix( $prefix );

				// If the filter tried to use a different filename too, log it.
				if ( wp_basename( $args['Key'] ) !== wp_basename( $as3cf_item->path( $object_key ) ) ) {
					$mesg = sprintf(
						__( 'The offloaded filename must not be changed, "%1$s" has been used instead of "%2$s".', 'amazon-s3-and-cloudfront' ),
						wp_basename( $as3cf_item->path( $object_key ) ),
						wp_basename( $args['Key'] )
					);
					AS3CF_Error::log( $mesg );
				}
			} elseif ( $primary_key === $object_key && $as3cf_item->path( $object_key ) !== $args['Key'] && ! empty( $as3cf_item->id() ) ) {
				$args['Key'] = $as3cf_item->path( $object_key );
				AS3CF_Error::log( __( 'The key may not be changed via filters for a previously offloaded item.', 'amazon-s3-and-cloudfront' ) );
			} elseif ( $primary_key !== $object_key && $as3cf_item->path( $object_key ) !== $args['Key'] ) {
				$args['Key'] = $as3cf_item->path( $object_key );
			}

			// If ACL has been set, does the object's is_private need updating?
			$is_private = ! empty( $args['ACL'] ) && $private_acl === $args['ACL'] || $as3cf_item->is_private( $object_key );
			$as3cf_item->set_is_private( $is_private, $object_key );

			// Protect against filter use and only set ACL if actually required, some storage provider and bucket settings disable changing ACL.
			if ( isset( $args['ACL'] ) && ! $this->as3cf->use_acl_for_intermediate_size( 0, $object_key, $as3cf_item->bucket(), $as3cf_item ) ) {
				unset( $args['ACL'] );
			}

			// Adjust the actual Key to add the private prefix before uploading.
			if ( $as3cf_item->is_private( $object_key ) ) {
				$args['Key'] = $as3cf_item->provider_key( $object_key );
			}

			// If we've already attempted to offload this source file, leave it out of the manifest.
			if ( in_array( md5( serialize( $args ) ), $this->attempted_upload ) ) {
				continue;
			}

			if ( $primary_key === $object_key ) {
				/**
				 * Actions fires when an Item's primary file might be offloaded.
				 *
				 * This action gives notice that an Item is being processed for upload to a bucket,
				 * and the given arguments represent the primary file's potential offload location.
				 * However, if the current process is for picking up extra files associated with the item,
				 * the indicated primary file may not actually be offloaded if it does not exist
				 * on the server but has already been offloaded.
				 *
				 * @param Item  $as3cf_item The Item whose files are being offloaded.
				 * @param array $args       The arguments that could be used to offload the primary file.
				 */
				do_action( 'as3cf_pre_upload_object', $as3cf_item, $args );
			}

			$manifest->objects[ $object_key ]['args'] = $args;
		}

		return $manifest;
	}

	/**
	 * Upload item files to remote storage provider
	 *
	 * @param Item     $as3cf_item
	 * @param Manifest $manifest
	 * @param array    $options
	 *
	 * @return bool|WP_Error
	 */
	protected function handle_item( Item $as3cf_item, Manifest $manifest, array $options ) {
		$source_type_name = $this->as3cf->get_source_type_name( $as3cf_item->source_type() );

		try {
			$provider_client = $this->as3cf->get_provider_client( $as3cf_item->region() );
		} catch ( Exception $e ) {
			return $this->return_handler_error( $e->getMessage() );
		}

		foreach ( $manifest->objects as $object_key => &$object ) {
			$args = $object['args'];

			$object['upload_result'] = array(
				'status'  => null,
				'message' => null,
			);

			if ( ! file_exists( $args['SourceFile'] ) ) {
				$error_msg = sprintf( __( 'File %1$s does not exist (%2$s with id %3$s)', 'amazon-s3-and-cloudfront' ), $args['SourceFile'], $source_type_name, $as3cf_item->source_id() );

				$object['upload_result']['status']  = self::STATUS_FAILED;
				$object['upload_result']['message'] = $error_msg;

				// If the missing source file is the primary file, abort the whole process.
				if ( Item::primary_object_key() === $object_key ) {
					return false;
				}

				continue;
			}

			$this->attempted_upload[] = md5( serialize( $args ) );

			// Try to do the upload
			try {
				$provider_client->upload_object( $args );

				$object['upload_result']['status'] = self::STATUS_OK;
			} catch ( Exception $e ) {
				$error_msg = sprintf( __( 'Error offloading %1$s to provider: %2$s (%3$s with id %4$s)', 'amazon-s3-and-cloudfront' ), $args['SourceFile'], $e->getMessage(), $source_type_name, $as3cf_item->source_id() );

				$object['upload_result']['status']  = self::STATUS_FAILED;
				$object['upload_result']['message'] = $error_msg;
			}
		}

		return true;
	}

	/**
	 * Handle local housekeeping after uploads.
	 *
	 * @param Item     $as3cf_item
	 * @param Manifest $manifest
	 * @param array    $options
	 *
	 * @return bool|WP_Error
	 */
	protected function post_handle( Item $as3cf_item, Manifest $manifest, array $options ) {
		$item_objects = $as3cf_item->objects();
		$errors       = new WP_Error();
		$i            = 1;

		// Reconcile the Item's objects with their manifest status.
		foreach ( $item_objects as $object_key => $object ) {
			// If there was no attempt made to offload the file,
			// then remove it from list of offloaded objects.
			// However, if the source file has previously been offloaded,
			// we should just skip any further processing of it
			// as the associated objects are still offloaded.
			if ( ! isset( $manifest->objects[ $object_key ]['upload_result']['status'] ) ) {
				if ( empty( $options['offloaded_files'][ $object['source_file'] ] ) ) {
					unset( $item_objects[ $object_key ] );
				}
				continue;
			}

			// If the upload didn't succeed, we need to remove the object/size from the item.
			// However, if the source file has previously been offloaded, we should just log the error.
			if ( $manifest->objects[ $object_key ]['upload_result']['status'] !== self::STATUS_OK ) {
				if ( empty( $options['offloaded_files'][ $object['source_file'] ] ) ) {
					unset( $item_objects[ $object_key ] );
				}
				$errors->add( 'upload-object-' . ( $i++ ), $manifest->objects[ $object_key ]['upload_result']['message'] );
			}
		}

		// Set the potentially changed list of offloaded objects.
		$as3cf_item->set_objects( $item_objects );

		// Only save if we have the primary file uploaded.
		if ( isset( $item_objects[ Item::primary_object_key() ] ) ) {
			$as3cf_item->save();
		}

		/**
		 * Fires action after uploading finishes
		 *
		 * @param Item $as3cf_item The item that was just uploaded
		 */
		do_action( 'as3cf_post_upload_item', $as3cf_item );

		if ( count( $errors->get_error_codes() ) ) {
			return $errors;
		}

		return true;
	}

	/**
	 * Should gzip file
	 *
	 * @param string $file_path
	 * @param string $source_type
	 *
	 * @return bool
	 */
	protected function should_gzip_file( $file_path, $source_type ) {
		$file_type = wp_check_filetype_and_ext( $file_path, $file_path );
		$mimes     = $this->get_mime_types_to_gzip( $source_type );

		if ( in_array( $file_type, $mimes ) && is_readable( $file_path ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get mime types to gzip
	 *
	 * @param string $source_type
	 *
	 * @return array
	 */
	protected function get_mime_types_to_gzip( $source_type ) {
		/**
		 * Return array of mime types that needs to be gzipped before upload
		 *
		 * @param array  $mime_types    The array of mime types
		 * @param bool   $media_library If the uploaded file is part of the media library
		 * @param string $source_type   The source type of the uploaded item
		 */
		return apply_filters(
			'as3cf_gzip_mime_types',
			array(
				'css'   => 'text/css',
				'eot'   => 'application/vnd.ms-fontobject',
				'html'  => 'text/html',
				'ico'   => 'image/x-icon',
				'js'    => 'application/javascript',
				'json'  => 'application/json',
				'otf'   => 'application/x-font-opentype',
				'rss'   => 'application/rss+xml',
				'svg'   => 'image/svg+xml',
				'ttf'   => 'application/x-font-ttf',
				'woff'  => 'application/font-woff',
				'woff2' => 'application/font-woff2',
				'xml'   => 'application/xml',
			),
			'media_library' === $source_type,
			$source_type
		);
	}

	/**
	 * Has the given file name already been offloaded?
	 *
	 * @param string $filename
	 * @param array  $options
	 *
	 * @return bool
	 */
	private function in_offloaded_files( $filename, $options ) {
		if ( empty( $options['offloaded_files'] ) ) {
			return false;
		}

		return array_key_exists( $filename, $options['offloaded_files'] );
	}
}
