<?php

namespace DeliciousBrains\WP_Offload_Media\API\V1;

use AS3CF_Error;
use DeliciousBrains\WP_Offload_Media\API\API;
use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Buckets extends API {
	/** @var int */
	protected static $version = 1;

	/** @var string */
	protected static $name = 'buckets';

	/** @var array */
	private $error_args = array(
		'type'                  => 'error',
		'only_show_in_settings' => true,
		'only_show_on_tab'      => 'media',
		'custom_id'             => 'bucket-error',
	);

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_buckets' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'post_buckets' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'put_buckets' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Processes a REST GET request and returns the current buckets, optionally for given region.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function get_buckets( WP_REST_Request $request ) {
		$data   = $request->get_query_params();
		$region = empty( $data['region'] ) ? false : $data['region'];

		$buckets = $this->as3cf->get_buckets( $region );

		if ( is_wp_error( $buckets ) ) {
			$this->as3cf->notices->add_notice(
				$this->as3cf->get_storage_provider()->prepare_bucket_error( $buckets ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'get', static::name(), array() );
		}

		$this->as3cf->notices->dismiss_notice( $this->error_args['custom_id'] );

		return $this->rest_ensure_response( 'get', static::name(), array(
			'buckets' => $buckets,
		) );
	}

	/**
	 * Processes a REST POST request to create a bucket and returns saved status.
	 *
	 * NOTE: This does not update settings.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function post_buckets( WP_REST_Request $request ) {
		$data   = $request->get_json_params();
		$bucket = empty( $data['bucket'] ) ? false : $data['bucket'];

		// Front end validation should have caught this, it's all gone Pete Tong!
		if ( ! $bucket ) {
			$this->as3cf->notices->add_notice(
				__( 'No bucket name provided.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'post', static::name(), array( 'saved' => false ) );
		}

		$bucket = $this->as3cf->check_bucket( $bucket );

		// Front end validation should have caught this, it's all gone Pete Tong!
		if ( ! $bucket ) {
			$this->as3cf->notices->add_notice(
				__( 'Bucket name not valid.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'post', static::name(), array( 'saved' => false ) );
		}

		$region = empty( $data['region'] ) ? false : $data['region'];

		// Front end validation should have caught this, it's all gone Pete Tong!
		if ( ! $region ) {
			$this->as3cf->notices->add_notice(
				__( 'No region provided.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'post', static::name(), array( 'saved' => false ) );
		}

		// Make sure defines agree with given params.
		$defines = $this->as3cf->get_defined_settings();

		if ( ! empty( $defines['bucket'] ) && $defines['bucket'] !== $bucket ) {
			$this->as3cf->notices->add_notice(
				__( 'Bucket name does not match defined value.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'post', static::name(), array( 'saved' => false ) );
		}

		if ( ! empty( $defines['region'] ) && $defines['region'] !== $region ) {
			$this->as3cf->notices->add_notice(
				__( 'Region does not match defined value.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'post', static::name(), array( 'saved' => false ) );
		}

		$result = $this->as3cf->create_bucket( $bucket, $region );

		if ( is_wp_error( $result ) ) {
			$this->as3cf->notices->add_notice(
				$this->as3cf->get_storage_provider()->prepare_bucket_error( $result ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'post', static::name(), array( 'saved' => false ) );
		}

		$this->as3cf->notices->dismiss_notice( $this->error_args['custom_id'] );

		return $this->rest_ensure_response( 'post', static::name(), array( 'saved' => $result ) );
	}

	/**
	 * Processes a REST PUT request to update a bucket's properties and returns saved status.
	 *
	 * NOTE: This does not directly update settings.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function put_buckets( WP_REST_Request $request ) {
		$data   = $request->get_json_params();
		$bucket = empty( $data['bucket'] ) ? false : $data['bucket'];

		do_action( 'as3cf_pre_update_bucket' );

		add_filter( 'as3cf_api_response_put_buckets', function ( $response ) {
			do_action( 'as3cf_post_update_bucket', $response['saved'] );

			return $response;
		} );

		// Front end validation should have caught this, it's all gone Pete Tong!
		if ( ! $bucket ) {
			$this->as3cf->notices->add_notice(
				__( 'No bucket name provided.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'put', static::name(), array( 'saved' => false ) );
		}

		$bucket = $this->as3cf->check_bucket( $bucket );

		// Front end validation should have caught this, it's all gone Pete Tong!
		if ( ! $bucket ) {
			$this->as3cf->notices->add_notice(
				__( 'Bucket name not valid.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'put', static::name(), array( 'saved' => false ) );
		}

		/*
		 * At present the only reason to call this endpoint is to change the
		 * Block All Public Access or Object Ownership status of an S3 bucket.
		 * As such, `blockPublicAccess` and `enforceObjectOwnership are required properties.
		 *
		 * In the future this endpoint may change to allow updating of
		 * other bucket properties, and may therefore be altered to have
		 * a differing set of required and optional properties depending
		 * on storage provider, including region.
		 */

		if ( ! isset( $data['blockPublicAccess'] ) ) {
			$this->as3cf->notices->add_notice(
				__( 'No Block All Public Access status provided.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'put', static::name(), array( 'saved' => false ) );
		}

		if ( ! isset( $data['objectOwnershipEnforced'] ) ) {
			$this->as3cf->notices->add_notice(
				__( 'No Enforce Object Ownership status provided.', 'amazon-s3-and-cloudfront' ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'put', static::name(), array( 'saved' => false ) );
		}

		$block = ! empty( $data['blockPublicAccess'] );

		$result = $this->block_public_access( $bucket, $block );

		if ( is_wp_error( $result ) ) {
			$this->as3cf->notices->add_notice(
				$this->as3cf->get_storage_provider()->prepare_bucket_error( $result, true ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'put', static::name(), array( 'saved' => false ) );
		}

		$enforce = ! empty( $data['objectOwnershipEnforced'] );

		$result = $this->enforce_object_ownership( $bucket, $enforce );

		if ( is_wp_error( $result ) ) {
			$this->as3cf->notices->add_notice(
				$this->as3cf->get_storage_provider()->prepare_bucket_error( $result, true ),
				$this->error_args
			);

			return $this->rest_ensure_response( 'put', static::name(), array( 'saved' => false ) );
		}

		$this->as3cf->notices->dismiss_notice( $this->error_args['custom_id'] );
		$this->as3cf->bucket_changed();

		return $this->rest_ensure_response( 'put', static::name(), array( 'saved' => true ) );
	}

	/**
	 * Update Block All Public Access status of given bucket.
	 *
	 * @param string  $bucket
	 * @param boolean $block
	 *
	 * @return WP_Error|bool
	 *
	 * There's no actual setting for this, the state of public access to the bucket is checked as required.
	 */
	public function block_public_access( string $bucket, bool $block ) {
		if ( false === $this->as3cf->get_storage_provider()->block_public_access_supported() ) {
			return new WP_Error(
				'exception',
				sprintf(
					_x(
						"Can't change Block All Public Access setting for %s buckets.",
						"Trying to change public access setting for given provider's bucket.",
						'amazon-s3-and-cloudfront'
					),
					$this->as3cf->get_storage_provider()->get_provider_service_name()
				)
			);
		}

		if ( $this->as3cf->get_storage_provider()->needs_access_keys() ) {
			return new WP_Error(
				'exception',
				__( 'Storage Provider not configured with access credentials.', 'amazon-s3-and-cloudfront' )
			);
		}

		if ( empty( $bucket ) ) {
			return new WP_Error(
				'exception',
				__( 'No bucket name provided.', 'amazon-s3-and-cloudfront' )
			);
		}

		// Make sure given bucket name matches currently set bucket.
		$settings_bucket = $this->as3cf->get_setting( 'bucket' );

		if ( $settings_bucket !== $bucket ) {
			return new WP_Error(
				'exception',
				__( 'Bucket name does not match currently set bucket.', 'amazon-s3-and-cloudfront' )
			);
		}

		$region = $this->as3cf->get_setting( 'region' );
		$block  = ! empty( $block );

		try {
			$public_access_blocked = $this->as3cf->get_provider_client( $region )->public_access_blocked( $bucket );
		} catch ( Exception $e ) {
			$public_access_blocked = null;
			AS3CF_Error::log( $e->getMessage() );
		}

		if ( empty( $block ) !== empty( $public_access_blocked ) ) {
			try {
				$this->as3cf->get_provider_client( $region )->block_public_access( $bucket, $block );
			} catch ( Exception $e ) {
				AS3CF_Error::log( $e->getMessage() );

				return new WP_Error(
					'exception',
					__( 'Could not change Block All Public Access status for bucket.', 'amazon-s3-and-cloudfront' )
				);
			}

			// The bucket level request may succeed, but account level overrides may negate the change or the change simply silently failed.
			// So check that all is as expected as we can't change the account level settings.
			try {
				$public_access_blocked = $this->as3cf->get_provider_client( $region )->public_access_blocked( $bucket );
			} catch ( Exception $e ) {
				$public_access_blocked = null;
				AS3CF_Error::log( $e->getMessage() );
			}

			if ( empty( $block ) !== empty( $public_access_blocked ) ) {
				if ( $block ) {
					$notice_message = __( '<strong>Failed to Enable Block All Public Access</strong> &mdash; We could not enable Block All Public Access. You will need to log in to the AWS Console and do it manually.', 'amazon-s3-and-cloudfront' );
				} else {
					$notice_message = __( '<strong>Failed to Disable Block All Public Access</strong> &mdash; We could not disable Block All Public Access. You will need to log in to the AWS Console and do it manually.', 'amazon-s3-and-cloudfront' );
				}
				$notice_message .= ' ' . $this->as3cf->settings_more_info_link( 'bucket' );

				return new WP_Error( 'exception', $notice_message );
			}

			// Successfully changed Block All Public Access status.
			return true;
		}

		// All good, but nothing to do.
		return false;
	}

	/**
	 * Update Object Ownership status of given bucket.
	 *
	 * @param string  $bucket
	 * @param boolean $enforce
	 *
	 * @return WP_Error|bool
	 *
	 * There's no actual setting for this, the state of object ownership controls in the bucket is checked as required.
	 */
	public function enforce_object_ownership( string $bucket, bool $enforce ) {
		if ( false === $this->as3cf->get_storage_provider()->object_ownership_supported() ) {
			return new WP_Error(
				'exception',
				sprintf(
					_x(
						"Can't change Object Ownership setting for %s buckets.",
						"Trying to change object ownership setting for given provider's bucket.",
						'amazon-s3-and-cloudfront'
					),
					$this->as3cf->get_storage_provider()->get_provider_service_name()
				)
			);
		}

		if ( $this->as3cf->get_storage_provider()->needs_access_keys() ) {
			return new WP_Error(
				'exception',
				__( 'Storage Provider not configured with access credentials.', 'amazon-s3-and-cloudfront' )
			);
		}

		if ( empty( $bucket ) ) {
			return new WP_Error(
				'exception',
				__( 'No bucket name provided.', 'amazon-s3-and-cloudfront' )
			);
		}

		// Make sure given bucket name matches currently set bucket.
		$settings_bucket = $this->as3cf->get_setting( 'bucket' );

		if ( $settings_bucket !== $bucket ) {
			return new WP_Error(
				'exception',
				__( 'Bucket name does not match currently set bucket.', 'amazon-s3-and-cloudfront' )
			);
		}

		$region  = $this->as3cf->get_setting( 'region' );
		$enforce = ! empty( $enforce );

		try {
			$object_ownership_enforced = $this->as3cf->get_provider_client( $region )->object_ownership_enforced( $bucket );
		} catch ( Exception $e ) {
			$object_ownership_enforced = null;
			AS3CF_Error::log( $e->getMessage() );
		}

		if ( empty( $enforce ) !== empty( $object_ownership_enforced ) ) {
			try {
				$this->as3cf->get_provider_client( $region )->enforce_object_ownership( $bucket, $enforce );
			} catch ( Exception $e ) {
				AS3CF_Error::log( $e->getMessage() );

				return new WP_Error(
					'exception',
					__( 'Could not change Object Ownership status for bucket.', 'amazon-s3-and-cloudfront' )
				);
			}

			// The bucket level request may succeed, but as it does not return a status from the API, we don't know for sure.
			try {
				$object_ownership_enforced = $this->as3cf->get_provider_client( $region )->object_ownership_enforced( $bucket );
			} catch ( Exception $e ) {
				$object_ownership_enforced = null;
				AS3CF_Error::log( $e->getMessage() );
			}

			if ( empty( $enforce ) !== empty( $object_ownership_enforced ) ) {
				if ( $enforce ) {
					$notice_message = __( '<strong>Failed to Enforce Object Ownership</strong> &mdash; We could not enforce Object Ownership. You will need to log in to the AWS Console and do it manually.', 'amazon-s3-and-cloudfront' );
				} else {
					$notice_message = __( '<strong>Failed to turn off Object Ownership Enforcement</strong> &mdash; We could not turn off Object Ownership enforcement. You will need to log in to the AWS Console and do it manually.', 'amazon-s3-and-cloudfront' );
				}
				$notice_message .= ' ' . $this->as3cf->settings_more_info_link( 'bucket' );

				return new WP_Error( 'exception', $notice_message );
			}

			// Successfully changed Object Ownership status.
			return true;
		}

		// All good, but nothing to do.
		return false;
	}
}
