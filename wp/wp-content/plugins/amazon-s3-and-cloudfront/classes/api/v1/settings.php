<?php

namespace DeliciousBrains\WP_Offload_Media\API\V1;

use DeliciousBrains\WP_Offload_Media\API\API;
use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Settings extends API {
	/** @var int */
	protected static $version = 1;

	/** @var string */
	protected static $name = 'settings';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'put_settings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Processes a REST GET request and returns the current settings and defined settings keys.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function get_settings( WP_REST_Request $request ) {
		return $this->rest_ensure_response( 'get', static::name(), $this->common_response() );
	}

	/**
	 * Common response values for this API endpoint.
	 *
	 * @return array
	 */
	public function common_response(): array {
		return array(
			'settings'                         => $this->as3cf->obfuscate_sensitive_settings( $this->as3cf->get_all_settings() ),
			'defined_settings'                 => array_keys( $this->as3cf->get_defined_settings() ),
			'storage_providers'                => $this->as3cf->get_available_storage_provider_details(),
			'delivery_providers'               => $this->as3cf->get_available_delivery_provider_details(),
			'is_plugin_setup'                  => $this->as3cf->is_plugin_setup(),
			'is_plugin_setup_with_credentials' => $this->as3cf->is_plugin_setup( true ),
			'needs_access_keys'                => $this->as3cf->get_storage_provider()->needs_access_keys(),
			'bucket_writable'                  => $this->as3cf->bucket_writable(),
			'urls'                             => $this->as3cf->get_js_urls(),
		);
	}

	/**
	 * Processes a REST PUT request to save supplied settings
	 * and returns saved status, current settings, defined settings keys,
	 * and various config data that may be altered by a change in settings.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function put_settings( WP_REST_Request $request ) {
		$saved = true;
		$data  = $request->get_json_params();

		try {
			$changed = $this->save_settings( $data );
		} catch ( Exception $e ) {
			$changed = false;
		}

		if ( $changed === false ) {
			$saved   = false;
			$changed = array();
		}

		return $this->rest_ensure_response(
			'put',
			static::name(),
			array_merge(
				$this->common_response(),
				array(
					'saved'            => $saved,
					'changed_settings' => $changed,
				)
			)
		);
	}

	/**
	 * Handle saving settings submitted by user.
	 *
	 * @param array $new_settings
	 *
	 * @return array|bool
	 * @throws Exception
	 */
	protected function save_settings( array $new_settings ) {
		$changed_keys = array();
		$warning_args = array(
			'type'                  => 'warning',
			'only_show_in_settings' => true,
			'only_show_on_tab'      => 'media',
		);

		do_action( 'as3cf_pre_save_settings' );

		$allowed      = $this->as3cf->get_allowed_settings_keys();
		$old_settings = $this->as3cf->get_all_settings( false );

		// Merge in defined settings as they take precedence and must overwrite anything supplied.
		// Only needed to allow for validation, during save defined settings are removed from array anyway.
		$new_settings = array_merge( $new_settings, $this->as3cf->get_defined_settings() );

		// Keep track of whether the delivery provider has changed and signed url settings need to be checked.
		$check_signed_urls_settings = $this->check_signed_urls_settings( $new_settings, $old_settings );

		foreach ( $allowed as $key ) {
			// Special case for when Secret Access Key is not changed.
			if (
				'secret-access-key' === $key &&
				! empty( $new_settings['secret-access-key'] ) &&
				_x( '-- not shown --', 'placeholder for hidden secret access key, 39 char max', 'amazon-s3-and-cloudfront' ) === $new_settings['secret-access-key']
			) {
				continue;
			}

			// Whether defined or not, get rid of old database setting for key.
			$this->as3cf->remove_setting( $key );

			if ( ! isset( $new_settings[ $key ] ) ) {
				continue;
			}

			$value = $this->as3cf->sanitize_setting( $key, $new_settings[ $key ] );

			if ( 'key-file' === $key && is_string( $value ) && ! empty( $value ) ) {
				// Guard against empty JSON.
				if ( '""' === $value ) {
					continue;
				}

				$value = json_decode( $value, true );

				if ( empty( $value ) ) {
					return $this->return_with_error( __( 'Key File not valid JSON.', 'amazon-s3-and-cloudfront' ) );
				}
			}

			if ( 'bucket' === $key && $this->setting_changed( $old_settings, $key, $value ) ) {
				$value = $this->as3cf->check_bucket( $value );

				// Front end validation should have caught this, it's all gone Pete Tong!
				if ( ! $value ) {
					return $this->return_with_error( __( 'Bucket name not valid.', 'amazon-s3-and-cloudfront' ) );
				}
			}

			if ( 'signed-urls-key-file-path' === $key && is_string( $value ) && ! empty( $value ) ) {
				// Can be a Windows path with backslashes, so need to undo what PUT does to them.
				$value = stripslashes( $value );
			}

			if ( $check_signed_urls_settings ) {
				if ( 'signed-urls-key-id' === $key && empty( $value ) ) {
					return $this->return_with_error( $this->as3cf->get_delivery_provider()->signed_urls_key_id_name() . _x( ' not provided.', 'missing form field', 'amazon-s3-and-cloudfront' ) );
				}

				if ( 'signed-urls-key-file-path' === $key ) {
					if ( empty( $value ) ) {
						return $this->return_with_error( $this->as3cf->get_delivery_provider()->signed_urls_key_file_path_name() . _x( ' not provided.', 'missing form field', 'amazon-s3-and-cloudfront' ) );
					}

					if ( ! $this->as3cf->get_delivery_provider()->validate_signed_urls_key_file_path( $value ) ) {
						// A notice is created by the validation function, we just want the rollback.
						return $this->return_with_error();
					}
				}

				if ( 'signed-urls-object-prefix' === $key && empty( $value ) ) {
					return $this->return_with_error( $this->as3cf->get_delivery_provider()->signed_urls_object_prefix_name() . _x( ' not provided.', 'missing form field', 'amazon-s3-and-cloudfront' ) );
				}
			}

			$this->as3cf->set_setting( $key, $value );

			if ( $this->setting_changed( $old_settings, $key, $value ) ) {
				$changed_keys[] = $key;
			}
		}

		// Before checking that settings are all properly aligned,
		// ensure storage and delivery providers are in sync in core class.
		$this->as3cf->set_storage_provider();
		$this->as3cf->set_delivery_provider();

		// If Storage Provider has changed, reset Delivery Provider to Storage if no longer compatible.
		// Also reset region name if no longer compatible.
		if ( ! empty( $changed_keys ) && in_array( 'provider', $changed_keys ) ) {
			$storage_provider  = $this->as3cf->get_storage_provider();
			$storage_supported = $this->as3cf->get_delivery_provider()->supports_storage( $storage_provider->get_provider_key_name() );

			if ( ! $storage_supported ) {
				$this->as3cf->set_setting( 'delivery-provider', $this->as3cf->get_default_delivery_provider() );
			}

			$region = $this->as3cf->get_setting( 'region' );

			if (
				( empty( $region ) && $storage_provider->region_required() ) ||
				( ! empty( $region ) && ! in_array( $region, array_keys( $storage_provider->get_regions() ) ) )
			) {
				// Note: We don't trigger changed keys when resetting the region as we do not want to stop
				// the storage provider change because the bucket is not usable.
				// The provider/region/bucket combination will be checked in due course.
				$this->as3cf->set_setting( 'region', $storage_provider->get_default_region() );
			}
		}

		// Ensure newly selected provider/region/bucket combination is usable and bucket's extra data is up-to-date.
		if ( ! empty( $changed_keys ) && ( in_array( 'bucket', $changed_keys ) || in_array( 'region', $changed_keys ) ) ) {
			$bucket_error = $this->check_set_bucket_for_error();

			if ( ! empty( $bucket_error ) ) {
				return $this->return_with_error( $bucket_error );
			}

			// Has a side effect of saving settings, but that should be ok if bucket changed and validated,
			// as changing bucket is usually a separate process from changing other settings.
			$this->as3cf->bucket_changed();
		}

		// If delivery provider has been changed, but not intentionally, we need to warn the user.
		if (
			! empty( $changed_keys ) &&
			! in_array( 'delivery-provider', $changed_keys ) &&
			$this->setting_changed( $old_settings, 'delivery-provider', $this->as3cf->get_setting( 'delivery-provider' ) )
		) {
			$changed_keys     = array_unique( array_merge( $changed_keys, array( 'delivery-provider' ) ) );
			$storage_provider = $this->as3cf->get_storage_provider();
			$warnings[]       = sprintf( __( 'Delivery Provider has been reset to the default for %s', 'amazon-s3-and-cloudfront' ), $storage_provider->get_provider_service_name() );
		}

		// None of the settings produced an error of their own.
		// However, side effects may re-instate the notice after this.
		$this->as3cf->notices->dismiss_notice( 'save-settings-error' );

		// Check provider/region/bucket to see whether any error notice or warning necessary.
		// Note: As this is a side effect of another change such as switching provider, settings save is not prevented.
		if ( ! in_array( 'bucket', $changed_keys ) && ! in_array( 'region', $changed_keys ) && ! empty( $this->as3cf->get_setting( 'bucket' ) ) ) {
			$bucket_error = $this->check_set_bucket_for_error();

			if ( empty( $bucket_error ) && $this->setting_changed( $old_settings, 'region', $this->as3cf->get_setting( 'region' ) ) ) {
				// If region has been changed, but not intentionally, we may need to warn user.
				$changed_keys = array_unique( array_merge( $changed_keys, array( 'region' ) ) );
				$region_name  = $this->as3cf->get_storage_provider()->get_region_name( $this->as3cf->get_setting( 'region' ) );
				$warnings[]   = sprintf( __( 'Region has been changed to %s', 'amazon-s3-and-cloudfront' ), $region_name );
			}
		}

		// Great success ...
		$this->as3cf->save_settings();

		// ... but there may be warnings.
		if ( ! empty( $warnings ) ) {
			foreach ( $warnings as $warning ) {
				$this->as3cf->notices->add_notice( $warning, $warning_args );
			}
		}

		do_action( 'as3cf_post_save_settings', true );

		return $changed_keys;
	}

	/**
	 * Has the given setting changed?
	 *
	 * @param array  $old_settings
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public function setting_changed( array $old_settings, string $key, $value ): bool {
		if (
			( empty( $old_settings[ $key ] ) !== empty( $value ) ) ||
			( isset( $old_settings[ $key ] ) && $old_settings[ $key ] !== $value )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Check that provider/region/bucket combination is usable.
	 *
	 * @return string|WP_Error|null Error/string if problems, null otherwise.
	 */
	private function check_set_bucket_for_error() {
		$bucket = $this->as3cf->get_setting( 'bucket' );

		if ( empty( $bucket ) ) {
			return __( 'No bucket provided.', 'amazon-s3-and-cloudfront' );
		}

		// If region not required as a parameter, and not already defined, get it from bucket.
		if ( $this->as3cf->get_storage_provider()->region_required() || $this->as3cf->get_defined_setting( 'region', false ) ) {
			$region = $this->as3cf->get_setting( 'region', false );
		} else {
			$region = $this->as3cf->get_bucket_region( $bucket, false );
		}

		// When region is force checked and has an error, a notice is already created.
		if ( is_wp_error( $region ) ) {
			return $region;
		}

		if ( ! $region ) {
			return __( 'No region provided.', 'amazon-s3-and-cloudfront' );
		}

		// Region may have been updated.
		$this->as3cf->set_setting( 'region', $region );

		$can_write = $this->as3cf->check_write_permission( $bucket, $region );

		if ( is_wp_error( $can_write ) ) {
			return $this->as3cf->get_storage_provider()->prepare_bucket_error( $can_write );
		}

		if ( ! $can_write ) {
			return __( 'Access Denied to Bucket.', 'amazon-s3-and-cloudfront' );
		}

		return null;
	}

	/**
	 * Check if we need to test signed URL settings and potentially prevent the save operation. We only need to test
	 * this if:
	 * - The delivery provider has not changed.
	 * - And signed URLs are enabled in the new settings.
	 * - And the delivery provider allows the use of signed URLs with a key file.
	 *
	 * @param array $new_settings
	 * @param array $old_settings
	 *
	 * @return bool
	 */
	private function check_signed_urls_settings( array $new_settings, array $old_settings ): bool {
		$delivery_provider_changed = ! empty( $new_settings['delivery-provider'] ) && $this->setting_changed( $old_settings, 'delivery-provider', $new_settings['delivery-provider'] );
		$signed_urls_enabled       = ! empty( $new_settings['enable-delivery-domain'] ) && ! empty( $new_settings['enable-signed-urls'] );

		return ! $delivery_provider_changed && $signed_urls_enabled ? $this->as3cf->get_delivery_provider()->use_signed_urls_key_file_allowed() : false;
	}

	/**
	 * Logs an error notice if string given, resets settings and then returns false.
	 *
	 * @param string|WP_Error|null $error Message for new notice, or WP_Error or null if new notice not needed.
	 *
	 * @return false
	 * @throws Exception
	 */
	private function return_with_error( $error = null ): bool {
		if ( ! is_wp_error( $error ) && ! empty( $error ) ) {
			$this->as3cf->notices->add_notice(
				$error,
				array(
					'type'                  => 'error',
					'only_show_in_settings' => true,
					'only_show_on_tab'      => 'media',
					'custom_id'             => 'save-settings-error',
				)
			);
		}

		// Revert all changes.
		$this->as3cf->get_settings( true );
		$this->as3cf->set_storage_provider();
		$this->as3cf->set_delivery_provider();

		do_action( 'as3cf_post_save_settings', false );

		return false;
	}
}
