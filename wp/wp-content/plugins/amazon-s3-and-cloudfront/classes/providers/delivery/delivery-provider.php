<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Delivery;

use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Settings_Validator_Trait;
use DeliciousBrains\WP_Offload_Media\Items\Item;
use DeliciousBrains\WP_Offload_Media\Providers\Provider;
use DeliciousBrains\WP_Offload_Media\Settings\Delivery_Check;
use DeliciousBrains\WP_Offload_Media\Settings\Domain_Check;
use DeliciousBrains\WP_Offload_Media\Settings\Validator_Interface;
use Exception;
use WP_Error as AS3CF_Result;

abstract class Delivery_Provider extends Provider implements Validator_Interface {
	use Settings_Validator_Trait;

	const VALIDATOR_KEY = 'delivery';

	/**
	 * @var string
	 */
	protected static $provider_service_name_setting_name = 'delivery-provider-service-name';

	/**
	 * @var string
	 */
	protected $console_url_prefix_param = '/';

	/**
	 * @var bool
	 */
	protected static $delivery_domain_allowed = true;

	/**
	 * Which storage providers does the delivery provider support, empty means all.
	 *
	 * @var array
	 */
	protected static $supported_storage_providers = array();

	/**
	 * @var string
	 */
	protected static $signed_urls_key_id_setting_name = 'signed-urls-key-id';

	/**
	 * @var string
	 */
	protected static $signed_urls_key_file_path_setting_name = 'signed-urls-key-file-path';

	/**
	 * @var string
	 */
	protected static $signed_urls_object_prefix_setting_name = 'signed-urls-object-prefix';

	/**
	 * If left empty, private signing key file not allowed.
	 *
	 * @var array
	 */
	protected static $signed_urls_key_id_constants = array();

	/**
	 * If left empty, private signing key file not allowed.
	 *
	 * @var array
	 */
	protected static $signed_urls_key_file_path_constants = array();

	/**
	 * If left empty, private signing key file not allowed.
	 *
	 * @var array
	 */
	protected static $signed_urls_object_prefix_constants = array();

	/**
	 * @var int
	 */
	private $validator_priority = 10;

	/**
	 * A description for the Rewrite Media URLs setting.
	 *
	 * @return string
	 */
	public static function get_rewrite_media_urls_desc(): string {
		global $as3cf;

		$mesg = sprintf(
			_x( 'Serves offloaded media files by rewriting local URLs so that they point to %s.', 'Setting description', 'amazon-s3-and-cloudfront' ),
			static::get_provider_service_name()
		);
		$mesg .= ' ' . $as3cf::settings_more_info_link( 'serve-from-s3', 'How URL rewriting works' );

		return $mesg;
	}

	/**
	 * Does the delivery provider allow for setting a custom delivery domain?
	 *
	 * @return bool
	 */
	public static function delivery_domain_allowed() {
		return static::$delivery_domain_allowed;
	}

	/**
	 * A description for the delivery domain settings.
	 *
	 * @return string
	 */
	public static function get_delivery_domain_desc(): string {
		return sprintf(
			__( 'Serves media from a custom domain that has been pointed to %1$s. <a href="%2$s" target="_blank">How to set a custom domain name</a>', 'amazon-s3-and-cloudfront' ),
			static::get_provider_service_name(),
			static::get_provider_service_quick_start_url() . '#create-cname'
		);
	}

	/**
	 * Returns array of supported storage provider key names, empty array means all supported.
	 *
	 * @return array
	 */
	public static function get_supported_storage_providers() {
		return static::$supported_storage_providers;
	}

	/**
	 * Does provider support given storage provider?
	 *
	 * @param string $storage_provider_key
	 *
	 * @return bool
	 */
	public static function supports_storage( $storage_provider_key ) {
		if ( empty( static::$supported_storage_providers ) || in_array( $storage_provider_key, static::$supported_storage_providers ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Title used in various places for enabling Signed URLs.
	 *
	 * @return string
	 */
	public static function signed_urls_option_name() {
		return __( 'Serve Private Media from Delivery Provider', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Description used in various places for enabling Signed URLs.
	 *
	 * @return string
	 */
	public static function signed_urls_option_description() {
		return '';
	}

	/**
	 * Title used in various places for the Signed URLs Key ID.
	 *
	 * @return string
	 */
	public static function signed_urls_key_id_name() {
		return __( 'Signing Key ID', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Description used in various places for the Signed URLs Key ID.
	 *
	 * @return string
	 */
	public static function signed_urls_key_id_description() {
		return '';
	}

	/**
	 * Title used in various places for the Signed URLs Key File Path.
	 *
	 * @return string
	 */
	public static function signed_urls_key_file_path_name() {
		return __( 'Signing Key File Path', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Description used in various places for the Signed URLs Key File Path.
	 *
	 * @return string
	 */
	public static function signed_urls_key_file_path_description() {
		return '';
	}

	/**
	 * Title used in various places for the Signed URLs Private Object Prefix.
	 *
	 * @return string
	 */
	public static function signed_urls_object_prefix_name() {
		return __( 'Private Bucket Path', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Description used in various places for the Signed URLs Private Object Prefix.
	 *
	 * @return string
	 */
	public static function signed_urls_object_prefix_description() {
		return '';
	}

	/**
	 * Description used in settings notice when all tests pass.
	 *
	 * @param array $recommendations Array of hints/recommendation to add to the success message.
	 *
	 * @return string
	 */
	protected static function delivery_tests_pass_desc( array $recommendations = array() ): string {
		$message = __( 'Delivery provider is successfully connected and serving offloaded media.', 'amazon-s3-and-cloudfront' );

		if ( ! empty( $recommendations ) ) {
			$message = __( 'Delivery settings validated.', 'amazon-s3-and-cloudfront' );
			$message .= '<br><br>';
			$message .= join( '<br><br>', $recommendations );
		}

		return $message;
	}

	/**
	 * Is the provider able to use a private signing key file?
	 *
	 * @return bool
	 */
	public static function use_signed_urls_key_file_allowed() {
		return ! empty( static::$signed_urls_key_id_constants ) && ! empty( static::$signed_urls_key_file_path_constants ) && ! empty( static::$signed_urls_object_prefix_constants );
	}

	/**
	 * If private signing key file allowed, returns first (preferred) key id constant that should be defined, otherwise blank.
	 *
	 * @return string
	 */
	public static function preferred_signed_urls_key_id_constant() {
		if ( static::use_signed_urls_key_file_allowed() ) {
			return static::$signed_urls_key_id_constants[0];
		} else {
			return '';
		}
	}

	/**
	 * If private signing key file allowed, returns first (preferred) key file path constant that should be defined, otherwise blank.
	 *
	 * @return string
	 */
	public static function preferred_signed_urls_key_file_path_constant() {
		if ( static::use_signed_urls_key_file_allowed() ) {
			return static::$signed_urls_key_file_path_constants[0];
		} else {
			return '';
		}
	}

	/**
	 * If private signing key file allowed, returns first (preferred) object prefix constant that should be defined, otherwise blank.
	 *
	 * @return string
	 */
	public static function preferred_signed_urls_object_prefix_constant() {
		if ( static::use_signed_urls_key_file_allowed() ) {
			return static::$signed_urls_object_prefix_constants[0];
		} else {
			return '';
		}
	}

	/**
	 * Check if private signing key id and file path set.
	 *
	 * @return bool
	 */
	public function use_signed_urls_key_file() {
		if ( ! static::use_signed_urls_key_file_allowed() ) {
			return false;
		}

		return $this->get_signed_urls_key_id() && $this->get_signed_urls_key_file_path() && $this->get_signed_urls_object_prefix();
	}

	/**
	 * Get the private signing key id from a constant or the settings.
	 *
	 * Falls back to settings if constant is not defined.
	 *
	 * @return string
	 */
	public function get_signed_urls_key_id() {
		if ( static::is_signed_urls_key_id_constant_defined() ) {
			$constant = static::signed_urls_key_id_constant();

			return $constant ? constant( $constant ) : '';
		}

		return $this->as3cf->get_core_setting( static::$signed_urls_key_id_setting_name );
	}

	/**
	 * Get the private signing key file path from a constant or the settings.
	 *
	 * Falls back to settings if constant is not defined.
	 *
	 * @return string|bool
	 */
	public function get_signed_urls_key_file_path() {
		if ( static::is_signed_urls_key_file_path_constant_defined() ) {
			$constant = static::signed_urls_key_file_path_constant();

			if ( $constant ) {
				return $this->validate_signed_urls_key_file_path( constant( $constant ) );
			} else {
				// Constant defined but value is not a non-empty string.
				return false;
			}
		}

		return $this->validate_signed_urls_key_file_path( $this->as3cf->get_core_setting( static::$signed_urls_key_file_path_setting_name, false ) );
	}

	/**
	 * Get the private signing object prefix from a constant or the settings.
	 *
	 * Falls back to settings if constant is not defined.
	 *
	 * @return string
	 */
	public function get_signed_urls_object_prefix() {
		if ( static::is_signed_urls_object_prefix_constant_defined() ) {
			$constant = static::signed_urls_object_prefix_constant();

			return $constant ? constant( $constant ) : '';
		}

		return $this->as3cf->get_core_setting( static::$signed_urls_object_prefix_setting_name );
	}

	/**
	 * Validate a private signing key file path to ensure it exists, is readable, and contains something.
	 *
	 * @param string $signed_urls_key_file_path
	 *
	 * @return bool|string
	 */
	public function validate_signed_urls_key_file_path( $signed_urls_key_file_path ) {
		$notice_id = ( $this->as3cf->saving_settings() ? 'temp-' : '' ) . 'validate-signed-urls-key-file-path';
		$this->as3cf->notices->remove_notice_by_id( $notice_id );

		$notice_settings = array(
			'type'                  => 'error',
			'only_show_in_settings' => true,
			'only_show_on_tab'      => 'media',
			'custom_id'             => $notice_id,
			'hide_on_parent'        => ! $this->as3cf->saving_settings(),
		);

		if ( empty( $signed_urls_key_file_path ) ) {
			return false;
		}

		if ( ! file_exists( $signed_urls_key_file_path ) ) {
			$this->as3cf->notices->add_notice( __( 'Given Signing Key File Path is invalid or could not be accessed.', 'amazon-s3-and-cloudfront' ), $notice_settings );

			return false;
		}

		try {
			$value = file_get_contents( $signed_urls_key_file_path );

			// An exception isn't always thrown, so check value instead.
			if ( empty( $value ) ) {
				$this->as3cf->notices->add_notice( __( 'Could not read Signing Key File Path\'s contents.', 'amazon-s3-and-cloudfront' ), $notice_settings );

				return false;
			}
		} catch ( Exception $e ) {
			$this->as3cf->notices->add_notice( __( 'Could not read Signing Key File Path\'s contents.', 'amazon-s3-and-cloudfront' ), $notice_settings );

			return false;
		}

		// File exists and has contents.
		return $signed_urls_key_file_path;
	}

	/**
	 * Check if private signing key id constant is defined.
	 *
	 * @return bool
	 */
	public static function is_signed_urls_key_id_constant_defined() {
		$constant = static::signed_urls_key_id_constant();

		return $constant && constant( $constant );
	}

	/**
	 * Check if private signing key file path constant is defined, for speed, does not check validity of file path.
	 *
	 * @return bool
	 */
	public static function is_signed_urls_key_file_path_constant_defined() {
		$constant = static::signed_urls_key_file_path_constant();

		return $constant && constant( $constant );
	}

	/**
	 * Check if private signing object prefix constant is defined.
	 *
	 * @return bool
	 */
	public static function is_signed_urls_object_prefix_constant_defined() {
		$constant = static::signed_urls_object_prefix_constant();

		return $constant && constant( $constant );
	}

	/**
	 * Get the constant used to define the private signing key id.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function signed_urls_key_id_constant() {
		return AS3CF_Utils::get_first_defined_constant( static::$signed_urls_key_id_constants );
	}

	/**
	 * Get the constant used to define the private signing key file path.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function signed_urls_key_file_path_constant() {
		return AS3CF_Utils::get_first_defined_constant( static::$signed_urls_key_file_path_constants );
	}

	/**
	 * Get the constant used to define the private signing object prefix.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function signed_urls_object_prefix_constant() {
		return AS3CF_Utils::get_first_defined_constant( static::$signed_urls_object_prefix_constants );
	}

	/**
	 * Get a site specific file path example for a signed URL key file.
	 *
	 * @return string
	 */
	public static function signed_urls_key_file_path_placeholder() {
		$filename = 'pk-12345678ABCDE.pem';

		return dirname( ABSPATH ) . DIRECTORY_SEPARATOR . $filename;
	}

	/**
	 * Return a fully formed and potentially expiring signed URL for the given Item.
	 *
	 * @param Item       $as3cf_item
	 * @param string     $path    The required bucket path, may differ from Item's path if image subsize etc.
	 * @param string     $domain  The domain to use for the URL if at all possible.
	 * @param string     $scheme  The scheme to be used if possible.
	 * @param array|null $headers Optional array of headers to be passed along to underlying requests.
	 *
	 * @return string
	 */
	public function get_url( Item $as3cf_item, $path, $domain, $scheme, $headers = array() ) {
		$item_path = $this->as3cf->maybe_update_delivery_path( $path, $domain );
		$item_path = AS3CF_Utils::encode_filename_in_path( $item_path );

		return $scheme . '://' . $domain . '/' . $item_path;
	}

	/**
	 * Return a fully formed and expiring signed URL for the given Item.
	 *
	 * @param Item       $as3cf_item
	 * @param string     $path      The required bucket path, may differ from Item's path if image subsize etc.
	 * @param string     $domain    The domain to use for the URL if at all possible.
	 * @param string     $scheme    The scheme to be used if possible.
	 * @param int        $timestamp URL expires at the given time.
	 * @param array|null $headers   Optional array of headers to be passed along to underlying requests.
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_signed_url( Item $as3cf_item, $path, $domain, $scheme, $timestamp, $headers = array() ) {
		/**
		 * This default implementation defers to the storage provider's signed URLs.
		 * Therefore, we need to use a storage provider client instance for the item's region.
		 */
		if ( ! empty( $as3cf_item->region() ) && ( $this->as3cf->get_storage_provider()->region_required() || $this->as3cf->get_storage_provider()->get_default_region() !== $as3cf_item->region() ) ) {
			$region = $this->as3cf->get_storage_provider()->sanitize_region( $as3cf_item->region() );
		} else {
			$region = '';
		}

		// Storage Provider may support signing custom domain, e.g. GCP.
		if ( $this->as3cf->get_storage_provider()->get_domain() !== $domain ) {
			$headers['BaseURL'] = $scheme . '://' . $domain;
		}

		return $this->as3cf->get_provider_client( $region )->get_object_url( $as3cf_item->bucket(), $path, $timestamp, $headers );
	}

	/**
	 * A short description of whether delivery is fast (distributed) or not.
	 *
	 * @return string
	 */
	public static function edge_server_support_desc() {
		return __( 'Fast', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * A short description of whether signed URLs for private media is supported or not.
	 *
	 * @return string
	 */
	public static function signed_urls_support_desc() {
		return __( 'No Private Media', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Description for when Block All Public Access is enabled and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_enabled_unsupported_desc() {
		return sprintf(
			__( 'You need to disable Block All Public Access so that %1$s can access your bucket for delivery.', 'amazon-s3-and-cloudfront' ),
			static::get_provider_name()
		);
	}

	/**
	 * Prompt text to confirm that everything is in place to enable Block All Public Access without issues for Delivery Provider.
	 *
	 * @return string
	 */
	public static function get_block_public_access_confirm_setup_prompt() {
		return '';
	}

	/**
	 * Description for when Object Ownership is enforced and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_enforced_unsupported_desc(): string {
		global $as3cf;

		$object_ownership_doc = $as3cf::dbrains_url(
			'/wp-offload-media/doc/amazon-s3-bucket-object-ownership/',
			array( 'utm_campaign' => 'support+docs', 'utm_content' => 'change+bucket+access' )
		);

		return sprintf(
			__( 'You need to edit the bucket\'s Object Ownership setting and <a href="%1$s">enable ACLs</a> so that %2$s can access your bucket for delivery.', 'amazon-s3-and-cloudfront' ),
			$object_ownership_doc,
			static::get_provider_name()
		);
	}

	/**
	 * Notice text for when a public file can't be accessed.
	 *
	 * @param string $error_message
	 *
	 * @return string
	 */
	public static function get_cannot_access_public_file_desc( string $error_message ): string {
		return sprintf(
			__(
				'Offloaded media URLs may be broken. %1$s <a href="%2$s" target="_blank">Read more</a>',
				'amazon-s3-and-cloudfront'
			),
			$error_message,
			static::get_provider_service_quick_start_url()
		);
	}

	/**
	 * Notice text for when a private file can't be accessed using a signed private URL.
	 *
	 * @param string $error_message
	 *
	 * @return string
	 */
	public static function get_cannot_access_private_file_desc( string $error_message ): string {
		return sprintf(
			__(
				'Private offloaded media URLs may be broken. %1$s <a href="%2$s" target="_blank">Read more</a>',
				'amazon-s3-and-cloudfront'
			),
			$error_message,
			static::get_provider_service_quick_start_url()
		);
	}

	/**
	 * Notice text for when a private file can be accessed using an unsigned URL.
	 *
	 * @return string
	 */
	public static function get_unsigned_url_can_access_private_file_desc(): string {
		return sprintf(
			__(
				'Private media is currently exposed through unsigned URLs. Restore privacy by verifying the configuration of private media settings. <a href="%1$s" target="_blank">Read more</a>',
				'amazon-s3-and-cloudfront'
			),
			static::get_provider_service_quick_start_url()
		);
	}

	/**
	 * Prompt text to confirm that everything is in place to enforce Object Ownership without issues for Delivery Provider.
	 *
	 * @return string
	 */
	public static function get_object_ownership_confirm_setup_prompt(): string {
		// Using the same text as for the Block Public Access prompt.
		return static::get_block_public_access_confirm_setup_prompt();
	}

	/**
	 * Get the link to the provider's console.
	 *
	 * @param string $bucket
	 * @param string $prefix
	 * @param string $region
	 *
	 * @return string
	 *
	 * NOTE: By default delivery providers append the suffix to the base console URL only.
	 *       The usual bucket, prefix and region params are still processed and the suffix
	 *       may end up being either a path, params or both that get deeper into the console.
	 */
	public function get_console_url( string $bucket = '', string $prefix = '', string $region = '' ): string {
		if ( '' !== $prefix ) {
			$prefix = $this->get_console_url_prefix_param() . apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_console_url_prefix_value', $prefix );
		}

		$suffix = apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_console_url_suffix_param', $this->get_console_url_suffix_param( $bucket, $prefix, $region ) );

		return apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_console_url', $this->console_url ) . $suffix;
	}

	/**
	 * Get the suffix param to append to the link to the provider's console.
	 *
	 * @param string $bucket
	 * @param string $prefix
	 * @param string $region
	 *
	 * @return string
	 */
	protected function get_console_url_suffix_param( string $bucket = '', string $prefix = '', string $region = '' ): string {
		return '';
	}

	/**
	 * Validate delivery settings for the configured provider for the delivery status indicator.
	 *
	 * @param bool $force Force time resource consuming or state altering tests to run.
	 *
	 * @return AS3CF_Result
	 */
	public function validate_settings( bool $force = false ): AS3CF_Result {
		$storage_provider = $this->as3cf->get_storage_provider();
		$bucket           = $this->as3cf->get_setting( 'bucket' );
		$region           = $this->as3cf->get_setting( 'region' );
		$recommendations  = array();

		// Validate the delivery provider key.
		$valid_delivery_provider_key = $this->validate_delivery_provider_key();
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $valid_delivery_provider_key->get_error_code() ) {
			return $valid_delivery_provider_key;
		}

		// The storage provider has lower priority and runs before delivery, so we should always have a fresh result.
		if ( $this->is_result_code_unknown_or_error( $this->as3cf->validation_manager->get_validation_status( 'storage' ) ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_WARNING,
				__( 'Delivery of offloaded media cannot be tested until the storage provider is successfully connected. See "Storage Settings" for more information.', 'amazon-s3-and-cloudfront' )
			);
		}

		// Ensure the storage provider client is initiated before BAPA/OOE tests.
		$storage_provider->get_client( array( 'region' => $region ) );

		// If storage BAPA setting is enabled, validate that it's supported by delivery provider.
		if ( ! static::block_public_access_supported() && $storage_provider->block_public_access_supported() && $storage_provider->public_access_blocked( $bucket ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				sprintf(
					_x(
						'Offloaded media cannot be delivered because <strong>Block All Public Access</strong> is enabled. <a href="%1$s">Edit bucket security</a>',
						'Delivery setting notice for issue with BAPA enabled on Storage Provider',
						'amazon-s3-and-cloudfront'
					),
					'#/storage/security'
				)
			);
		}

		// Object Ownership Policies enabled?
		if ( ! static::object_ownership_supported() && $storage_provider->object_ownership_supported() && $storage_provider->object_ownership_enforced( $bucket ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				sprintf(
					_x(
						'Offloaded media cannot be delivered due to the current <strong>Object Ownership</strong> configuration. <a href="%1$s">Edit bucket security</a>',
						'Delivery setting notice for issue with Object Ownership enforced on Storage Provider',
						'amazon-s3-and-cloudfront'
					),
					'#/storage/security'
				)
			);
		}

		$delivery_domain_settings = $this->validate_delivery_domain();
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $delivery_domain_settings->get_error_code() ) {
			return $delivery_domain_settings;
		}

		// Are settings for delivering signed URLs valid?
		$signed_url_settings = $this->validate_signed_url_settings();
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $signed_url_settings->get_error_code() ) {
			return $signed_url_settings;
		}

		// Test accessing files via provider.
		$connection_test = $this->provider_connection_test();
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $connection_test->get_error_code() ) {
			return $connection_test;
		}

		// Is Deliver Offloaded Media enabled?
		$deliver_media = $this->validate_deliver_offloaded_media_enabled();
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $deliver_media->get_error_code() ) {
			return $deliver_media;
		}

		// All good.
		return new AS3CF_Result(
			count( $recommendations ) === 0 ? Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS : Validator_Interface::AS3CF_STATUS_MESSAGE_WARNING,
			static::delivery_tests_pass_desc( $recommendations )
		);
	}

	/**
	 * Validate that the delivery provider key provided in settings is valid for the storage provider. This should
	 * only happen if the user is using defines statements or has manually edited settings in the db.
	 *
	 * @return AS3CF_Result
	 */
	protected function validate_delivery_provider_key(): AS3CF_Result {
		$storage_provider      = $this->as3cf->get_storage_provider();
		$storage_provider_key  = $storage_provider->get_provider_key_name();
		$delivery_provider_key = $this->as3cf->get_core_setting( 'delivery-provider' );

		$valid_providers = array_keys( $this->as3cf->get_available_delivery_provider_details( $storage_provider_key ) );
		if ( ! in_array( $delivery_provider_key, $valid_providers ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				sprintf(
					__( 'An invalid delivery provider has been defined for the active storage provider. Please use %1$s.', 'amazon-s3-and-cloudfront' ),
					"<code>" . AS3CF_Utils::human_readable_join( "</code>, <code>", "</code> or <code>", $valid_providers ) . "</code>"
				)
			);
		}

		return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
	}

	/**
	 * Validate settings for serving signed URLs.
	 *
	 * @return AS3CF_Result
	 */
	protected function validate_signed_url_settings(): AS3CF_Result {
		return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
	}

	/**
	 * Validate Deliver Offloaded Media is enabled.
	 *
	 * @return AS3CF_Result
	 */
	protected function validate_deliver_offloaded_media_enabled(): AS3CF_Result {
		if ( ! $this->as3cf->get_setting( 'serve-from-s3' ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_WARNING,
				__(
					'Delivery provider is successfully connected, but offloaded media will not be served until <strong>Deliver Offloaded Media</strong> is enabled. In the meantime, local media is being served if available.',
					'amazon-s3-and-cloudfront'
				)
			);
		}

		return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
	}

	/**
	 * Test settings for custom delivery domain.
	 *
	 * @return AS3CF_Result
	 */
	protected function validate_delivery_domain(): AS3CF_Result {
		$delivery_domain        = $this->as3cf->get_setting( 'delivery-domain' );
		$enable_delivery_domain = $this->as3cf->get_setting( 'enable-delivery-domain' );

		if ( ! static::delivery_domain_allowed() ) {
			return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
		}

		// Custom domain enabled?
		if ( ! $enable_delivery_domain || empty( $delivery_domain ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_WARNING,
				sprintf(
					__(
						'Offloaded media cannot be delivered from the CDN until a delivery domain is set. <a href="%1$s" target="_blank">Read more</a>',
						'amazon-s3-and-cloudfront'
					),
					static::get_provider_service_quick_start_url() . '#configure-plugin'
				)
			);
		}

		// Is the custom domain name valid?
		$domain_check = new Domain_Check( $delivery_domain );
		$domain_issue = $domain_check->get_validation_issue();
		if ( ! empty( $domain_issue ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				sprintf(
					__(
						'Offloaded media URLs may be broken due to an invalid delivery domain. %1$s <a href="%2$s">How to set a delivery domain</a>',
						'amazon-s3-and-cloudfront'
					),
					$domain_issue,
					static::get_provider_service_quick_start_url() . '#configure-plugin'
				)
			);
		}

		return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
	}

	/**
	 * Test public and private delivery from bucket using test files.
	 *
	 * @return AS3CF_Result
	 */
	protected function provider_connection_test(): AS3CF_Result {
		$delivery_check = new Delivery_Check( $this->as3cf );

		$setup_files = $delivery_check->setup_test_file( false );
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $setup_files->get_error_code() ) {
			return new AS3CF_Result( $setup_files->get_error_code(), $setup_files->get_error_message() );
		}

		// Verify that the public file is accessible.
		$delivery_issue = $delivery_check->test_public_file_access();
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $delivery_issue->get_error_code() ) {
			$delivery_check->remove_test_files();

			return new AS3CF_Result(
				$delivery_issue->get_error_code(),
				static::get_cannot_access_public_file_desc( $delivery_issue->get_error_message() )
			);
		}

		$setup_files = $delivery_check->setup_test_file( true );
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $setup_files->get_error_code() ) {
			return new AS3CF_Result( $setup_files->get_error_code(), $setup_files->get_error_message() );
		}

		// Verify that the private file is accessible.
		$delivery_issue = $delivery_check->test_private_file_access();
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $delivery_issue->get_error_code() ) {
			$delivery_check->remove_test_files();

			return new AS3CF_Result(
				$delivery_issue->get_error_code(),
				static::get_cannot_access_private_file_desc( $delivery_issue->get_error_message() )
			);
		}

		// Verify that the private file can't be accessed with unsigned URL.
		$delivery_issue = $delivery_check->test_private_file_access_unsigned();
		if ( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS !== $delivery_issue->get_error_code() ) {
			$delivery_check->remove_test_files();

			return new AS3CF_Result(
				$delivery_issue->get_error_code(),
				static::get_unsigned_url_can_access_private_file_desc()
			);
		}

		// Ensure all test files are removed.
		$delivery_check->remove_test_files();

		return new AS3CF_Result( Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS );
	}

	/**
	 * Get the name of the actions that are fired when the settings that the validator
	 * is responsible for are saved.
	 *
	 * @return array
	 */
	public function post_save_settings_actions(): array {
		return array( 'as3cf_post_save_settings', 'as3cf_post_update_bucket' );
	}
}
