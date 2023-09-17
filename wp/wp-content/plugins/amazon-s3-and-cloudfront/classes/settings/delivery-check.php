<?php

namespace DeliciousBrains\WP_Offload_Media\Settings;

use DeliciousBrains\WP_Offload_Media\Items\Provider_Test_Item;
use DeliciousBrains\WP_Offload_Media\Items\Upload_Handler;
use DeliciousBrains\WP_Offload_Media\Items\Remove_Provider_Handler;
use Amazon_S3_And_CloudFront;
use AS3CF_Utils;
use Exception;
use WP_Error as AS3CF_Result;

class Delivery_Check extends Domain_Check {
	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	private $as3cf;

	/**
	 * @var string
	 */
	private $local_file_path = '';

	/**
	 * @var string
	 */
	private $test_file_name = '';

	/**
	 * @var Provider_Test_Item|null
	 */
	private $as3cf_item;

	/**
	 * Class constructor.
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf
	 */
	public function __construct( Amazon_S3_And_CloudFront $as3cf ) {
		parent::__construct( '' );
		$this->as3cf = $as3cf;
	}

	/**
	 * Class destructor.
	 */
	public function __destruct() {
		$this->remove_test_files();
	}

	/**
	 * Create a test file & to upload to the storage provider.
	 *
	 * @param bool $is_private
	 *
	 * @return AS3CF_Result
	 */
	public function setup_test_file( bool $is_private ): AS3CF_Result {
		if ( empty( $this->as3cf_item ) || $this->as3cf_item->is_private() !== (bool) $is_private ) {
			$mode = $is_private ? __( 'Private', 'amazon-s3-and-cloudfront' ) : __( 'Public', 'amazon-s3-and-cloudfront' );

			if ( ! $this->create_local_file( $is_private ) ) {
				return new AS3CF_Result(
					Validator_Interface::AS3CF_STATUS_MESSAGE_WARNING,
					sprintf(
						_x(
							'Delivery provider status cannot be determined. An error was encountered while attempting to create a temporary file for %1$s delivery.',
							'amazon-s3-and-cloudfront'
						),
						$mode
					)
				);
			}

			if ( ! $this->upload_file( $is_private ) ) {
				$this->remove_test_files();

				return new AS3CF_Result(
					Validator_Interface::AS3CF_STATUS_MESSAGE_WARNING,
					sprintf(
						_x(
							'Delivery provider status cannot be determined. An error was encountered while attempting to offload a temporary file for %1$s delivery.',
							'amazon-s3-and-cloudfront'
						),
						$mode
					)
				);
			}
		}

		return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
	}

	/**
	 * Access a public file.
	 *
	 * @return AS3CF_Result
	 */
	public function test_public_file_access(): AS3CF_Result {
		// Protect against improper use of this method.
		if ( empty( $this->as3cf_item ) || false !== $this->as3cf_item->is_private() ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				__( 'Internal error', 'amazon-s3-and-cloudfront' )
			);
		}

		$url          = $this->as3cf_item->get_provider_url();
		$this->domain = AS3CF_Utils::parse_url( $url, PHP_URL_HOST );

		try {
			$this->check_dns_resolution();
			$response = $this->dispatch_request( $url );
			$this->check_response_code( wp_remote_retrieve_response_code( $response ) );
		} catch ( Exception $e ) {
			return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR, $e->getMessage() );
		}

		return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
	}

	/**
	 * Access a private file.
	 *
	 * @return AS3CF_Result
	 */
	public function test_private_file_access(): AS3CF_Result {
		// Protect against improper use of this method.
		if ( empty( $this->as3cf_item ) || false === $this->as3cf_item->is_private() ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				__( 'Internal error', 'amazon-s3-and-cloudfront' )
			);
		}

		// Attempt to access the file with the standard (signed) URL.
		$url = $this->as3cf_item->get_provider_url();

		if ( is_wp_error( $url ) ) {
			return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR, $url->get_error_message() );
		}

		$this->domain = AS3CF_Utils::parse_url( $url, PHP_URL_HOST );

		try {
			$this->check_dns_resolution();
			$response = wp_remote_get( $url );

			$this->check_response_code( (int) wp_remote_retrieve_response_code( $response ) );
		} catch ( Exception $e ) {
			return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR, $e->getMessage() );
		}

		return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
	}

	/**
	 * Access a private file using an unsigned URL.
	 *
	 * @return AS3CF_Result
	 */
	public function test_private_file_access_unsigned(): AS3CF_Result {
		// Protect against improper use of this method.
		if ( empty( $this->as3cf_item ) || false === $this->as3cf_item->is_private() ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				__( 'Internal error', 'amazon-s3-and-cloudfront' )
			);
		}

		// Remove the signing parameters from the URL.
		$url = $this->as3cf->maybe_remove_query_string( $this->as3cf_item->get_provider_url() );

		try {
			$response = wp_remote_get( $url );

			// This should throw in an exception.
			$this->check_response_code( (int) wp_remote_retrieve_response_code( $response ) );

			// If we're still here it's no good.
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_WARNING,
				__( 'Private file accessible using an unsigned URL.', 'amazon-s3-and-cloudfront' )
			);
		} catch ( Exception $e ) {
			// Accessing a private file using an unsigned URL failed, this is actually success.
			return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
		}
	}

	/**
	 * Upload a file to the storage provider.
	 *
	 * @param bool $is_private
	 *
	 * @return bool
	 */
	private function upload_file( bool $is_private ): bool {
		// Use a bucket key with no dynamic parts.
		$bucket_path = $this->as3cf->get_object_prefix() . $this->test_file_name;

		$extra_info = array(
			'objects' => array(
				Provider_Test_Item::primary_object_key() => array(
					'source_file' => basename( $this->local_file_path ),
					'is_private'  => $is_private,
				),
			),
		);

		$this->as3cf_item = new Provider_Test_Item(
			$this->as3cf->get_storage_provider()->get_provider_key_name(),
			$this->as3cf->get_setting( 'region' ),
			$this->as3cf->get_setting( 'bucket' ),
			$bucket_path,
			$is_private,
			0,
			$this->local_file_path,
			null,
			$extra_info
		);

		$upload_handler = new Upload_Handler( $this->as3cf );

		add_filter( 'upload_mimes', array( $this, 'allow_txt_offload' ) );
		$upload_result = $upload_handler->handle( $this->as3cf_item );
		remove_filter( 'upload_mimes', array( $this, 'allow_txt_offload' ) );

		if ( true !== $upload_result ) {
			$this->remove_test_files();

			return false;
		}

		return true;
	}

	/**
	 * Ensure txt files can be offloaded.
	 *
	 * @handles upload_mimes
	 *
	 * @param array $mime_types
	 *
	 * @return array
	 */
	public function allow_txt_offload( array $mime_types ): array {
		if ( empty( $mime_types['txt'] ) ) {
			$mime_types['txt'] = 'text/plain';
		}

		return $mime_types;
	}

	/**
	 * Remove created test files both locally and on the storage provider.
	 */
	public function remove_test_files() {
		if ( file_exists( $this->local_file_path ) ) {
			unlink( $this->local_file_path );
		}

		if ( ! is_null( $this->as3cf_item ) ) {
			$item_remover = new Remove_Provider_Handler( $this->as3cf );
			$item_remover->handle( $this->as3cf_item );
			$this->as3cf_item = null;
		}
	}

	/**
	 * Create local file with unique file name.
	 *
	 * @param bool $is_private
	 *
	 * @return bool
	 */
	private function create_local_file( bool $is_private ): bool {
		if ( ! empty( $this->local_file_path ) ) {
			$this->remove_test_files();
		}

		$uploads_dir = wp_get_upload_dir();
		$visibility  = $is_private ? 'private' : 'public';

		$this->test_file_name  = "as3cf-delivery-check-$visibility-" . time() . '.txt';
		$this->local_file_path = $uploads_dir['basedir'] . '/' . $this->test_file_name;
		$file_contents         = __( 'This is a test file to check delivery. Delete me if found.', 'amazon-s3-and-cloudfront' );

		return (bool) file_put_contents( $this->local_file_path, $file_contents );
	}
}
