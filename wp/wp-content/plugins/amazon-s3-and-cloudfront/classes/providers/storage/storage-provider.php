<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Storage;

use Amazon_S3_And_CloudFront;
use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Settings_Validator_Trait;
use DeliciousBrains\WP_Offload_Media\Providers\Provider;
use DeliciousBrains\WP_Offload_Media\Settings\Validator_Interface;
use Exception;
use WP_Error;
use WP_Error as AS3CF_Result;

abstract class Storage_Provider extends Provider implements Validator_Interface {
	use Settings_Validator_Trait;

	const VALIDATOR_KEY = 'storage';

	/**
	 * @var mixed
	 */
	private $client;

	/**
	 * @var string
	 */
	protected static $access_key_id_setting_name = 'access-key-id';

	/**
	 * @var string
	 */
	protected static $secret_access_key_setting_name = 'secret-access-key';

	/**
	 * @var string
	 */
	protected static $use_server_roles_setting_name = 'use-server-roles';

	/**
	 * @var string
	 */
	protected static $key_file_setting_name = 'key-file';

	/**
	 * @var string
	 */
	protected static $key_file_path_setting_name = 'key-file-path';

	/**
	 * If left empty, access keys not allowed.
	 *
	 * @var array
	 */
	protected static $access_key_id_constants = array();

	/**
	 * @var array
	 */
	protected static $secret_access_key_constants = array();

	/**
	 * If left empty, server roles not allowed.
	 *
	 * @var array
	 */
	protected static $use_server_roles_constants = array();

	/**
	 * If left empty, key file not allowed.
	 *
	 * @var array
	 */
	protected static $key_file_path_constants = array();

	/**
	 * @var array
	 */
	protected static $regions = array();

	/**
	 * @var bool
	 */
	protected static $region_required = false;

	/**
	 * @var string
	 */
	protected static $default_region = '';

	/**
	 * @var int
	 */
	private $validator_priority = 5;

	/**
	 * Is the provider able to use access keys?
	 *
	 * @return bool
	 */
	public static function use_access_keys_allowed() {
		return ! empty( static::$access_key_id_constants );
	}

	/**
	 * Whether or not access keys are needed.
	 *
	 * Keys are needed if we are not using server roles or not defined/set yet.
	 *
	 * @return bool
	 */
	public function needs_access_keys() {
		if ( $this->use_server_roles() ) {
			return false;
		}

		if ( $this->use_key_file() ) {
			return false;
		}

		if ( $this->are_access_keys_set() ) {
			return false;
		}

		$this->add_validation_issue( 'miss_access_key_id', empty( $this->get_access_key_id() ) );
		$this->add_validation_issue( 'miss_secret_access_key', empty( $this->get_secret_access_key() ) );
		$this->add_validation_issue( 'miss_both_access_keys', empty( $this->get_access_key_id() ) && empty( $this->get_secret_access_key() ) );

		return true;
	}

	/**
	 * Check if both access key id & secret are present.
	 *
	 * @return bool
	 */
	public function are_access_keys_set() {
		if ( ! static::use_access_keys_allowed() ) {
			return false;
		}

		return $this->get_access_key_id() && $this->get_secret_access_key();
	}

	/**
	 * Get the access key from a constant or the settings.
	 *
	 * Falls back to settings only if neither constant is defined.
	 *
	 * @return string
	 */
	public function get_access_key_id() {
		if ( static::is_any_access_key_constant_defined() ) {
			$constant = static::access_key_id_constant();

			return $constant ? constant( $constant ) : '';
		}

		return $this->as3cf->get_core_setting( static::$access_key_id_setting_name );
	}

	/**
	 * Get the secret access key from a constant or the settings.
	 *
	 * Falls back to settings only if neither constant is defined.
	 *
	 * @return string
	 */
	public function get_secret_access_key() {
		if ( static::is_any_access_key_constant_defined() ) {
			$constant = static::secret_access_key_constant();

			return $constant ? constant( $constant ) : '';
		}

		return $this->as3cf->get_core_setting( static::$secret_access_key_setting_name );
	}

	/**
	 * Check if any access key (id or secret, prefixed or not) is defined.
	 *
	 * @return bool
	 */
	public static function is_any_access_key_constant_defined() {
		return static::access_key_id_constant() || static::secret_access_key_constant();
	}

	/**
	 * Get the constant used to define the access key id.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function access_key_id_constant() {
		return AS3CF_Utils::get_first_defined_constant( static::$access_key_id_constants );
	}

	/**
	 * Get the constant used to define the secret access key.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function secret_access_key_constant() {
		return AS3CF_Utils::get_first_defined_constant( static::$secret_access_key_constants );
	}

	/**
	 * Is the provider able to use server roles?
	 *
	 * @return bool
	 */
	public static function use_server_roles_allowed() {
		return ! empty( static::$use_server_roles_constants );
	}

	/**
	 * If server roles allowed, returns first (preferred) constant that should be defined, otherwise blank.
	 *
	 * @return string
	 */
	public static function preferred_use_server_roles_constant() {
		if ( static::use_server_roles_allowed() ) {
			return static::$use_server_roles_constants[0];
		} else {
			return '';
		}
	}

	/**
	 * Allows the provider's client factory to use server roles instead of key/secret for credentials.
	 *
	 * @see http://docs.aws.amazon.com/aws-sdk-php/guide/latest/credentials.html#instance-profile-credentials
	 *
	 * @return bool
	 */
	public function use_server_roles() {
		if ( ! static::use_server_roles_allowed() ) {
			return false;
		}

		if ( static::is_use_server_roles_constant_defined() ) {
			$constant = static::use_server_roles_constant();

			return $constant ? constant( $constant ) : false;
		}

		return $this->as3cf->get_core_setting( static::$use_server_roles_setting_name, false );
	}

	/**
	 * Check if use server roles constant is defined.
	 *
	 * @return bool
	 */
	public static function is_use_server_roles_constant_defined() {
		$constant = static::use_server_roles_constant();

		return $constant && constant( $constant );
	}

	/**
	 * Get the constant used to enable the use of IAM roles.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function use_server_roles_constant() {
		return AS3CF_Utils::get_first_defined_constant( static::$use_server_roles_constants );
	}

	/**
	 * Is the provider able to use a key file?
	 *
	 * @return bool
	 */
	public static function use_key_file_allowed() {
		return ! empty( static::$key_file_path_constants );
	}

	/**
	 * If key file allowed, returns first (preferred) constant that should be defined, otherwise blank.
	 *
	 * @return string
	 */
	public static function preferred_key_file_path_constant() {
		if ( static::use_key_file_allowed() ) {
			return static::$key_file_path_constants[0];
		} else {
			return '';
		}
	}

	/**
	 * Check if either key file path or key file set.
	 *
	 * @return bool
	 */
	public function use_key_file() {
		if ( ! static::use_key_file_allowed() ) {
			return false;
		}

		return $this->get_key_file_path() || $this->get_key_file();
	}

	/**
	 * Get the key file contents from settings.
	 *
	 * @return mixed
	 */
	public function get_key_file() {
		$key_file_content = $this->as3cf->get_core_setting( static::$key_file_setting_name, false );

		if ( ! $key_file_content ) {
			return false;
		}

		$this->validate_key_file_content( $key_file_content );

		return $key_file_content;
	}

	/**
	 * Get the key file path from a constant or the settings.
	 *
	 * Falls back to settings if constant is not defined.
	 *
	 * @return string|bool
	 */
	public function get_key_file_path() {
		if ( static::is_key_file_path_constant_defined() ) {
			$constant = static::key_file_path_constant();
			$path     = constant( $constant );
		} else {
			$path = $this->as3cf->get_core_setting( static::$key_file_path_setting_name, false );
		}

		if ( ! $this->validate_key_file_path( $path ) ) {
			return false;
		}

		// At this point, we know that the file is safe to read.
		$key_file_content = $this->get_key_file_path_contents( $path );
		$this->validate_key_file_content( $key_file_content );

		return $path;
	}

	/**
	 * Validate a key file path to ensure it exists, is readable, and contains JSON.
	 *
	 * @param string $key_file_path
	 *
	 * @return bool|string
	 */
	public function validate_key_file_path( $key_file_path ) {
		$notice_id   = 'validate-key-file-path';
		$notice_args = array(
			'type'                  => 'error',
			'only_show_in_settings' => true,
			'only_show_on_tab'      => 'media',
			'hide_on_parent'        => true,
			'custom_id'             => $notice_id,
		);

		$this->as3cf->notices->remove_notice_by_id( $notice_id );

		if ( empty( $key_file_path ) ) {
			return false;
		}

		if ( ! file_exists( $key_file_path ) ) {
			$this->as3cf->notices->add_notice( __( 'Media cannot be offloaded due to an invalid key file path.', 'amazon-s3-and-cloudfront' ), $notice_args );

			return false;
		}

		try {
			$value = file_get_contents( $key_file_path );

			// An exception isn't always thrown, so check value instead.
			if ( empty( $value ) ) {
				$this->as3cf->notices->add_notice( __( 'Media cannot be offloaded due to an unreadable key file.', 'amazon-s3-and-cloudfront' ), $notice_args );

				return false;
			}
		} catch ( Exception $e ) {
			$this->as3cf->notices->add_notice( __( 'Media cannot be offloaded due to an unreadable key file.', 'amazon-s3-and-cloudfront' ), $notice_args );

			return false;
		}

		// File exists and is readable.
		return $key_file_path;
	}

	/**
	 * Read key file contents from path.
	 *
	 * @param string $path
	 *
	 * @return mixed
	 */
	protected function get_key_file_path_contents( string $path ) {
		return file_get_contents( $path );
	}

	/**
	 * Validate key file contents.
	 *
	 * @param mixed $key_file_content
	 *
	 * @return bool
	 */
	public function validate_key_file_content( $key_file_content ): bool {
		return true;
	}

	/**
	 * Check if key file path constant is defined, for speed, does not check validity of file path.
	 *
	 * @return bool
	 */
	public static function is_key_file_path_constant_defined() {
		$constant = static::key_file_path_constant();

		return $constant && constant( $constant );
	}

	/**
	 * Get the constant used to define the key file path.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function key_file_path_constant() {
		return AS3CF_Utils::get_first_defined_constant( static::$key_file_path_constants );
	}

	/**
	 * Get default "canned" ACL string.
	 *
	 * @return string|null
	 */
	public function get_default_acl() {
		return apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_default_acl', $this->get_public_acl() );
	}

	/**
	 * Is public access to the given bucket prohibited?
	 *
	 * @param string $bucket
	 *
	 * @return bool|null
	 */
	public function public_access_blocked( $bucket ) {
		if ( static::block_public_access_supported() ) {
			return null; // Unknown until sub class overrides this function.
		} else {
			return false;
		}
	}

	/**
	 * Update the block public access setting for the given bucket.
	 *
	 * @param string $bucket
	 * @param bool   $block
	 *
	 * Note: Should be overridden and implemented as required.
	 */
	public function block_public_access( string $bucket, bool $block ) {
	}

	/**
	 * Is object ownership enforced (and therefore ACLs disabled)?
	 *
	 * @param string $bucket
	 *
	 * @return bool|null
	 */
	public function object_ownership_enforced( string $bucket ): ?bool {
		if ( static::object_ownership_supported() ) {
			return null; // Unknown until sub class overrides this function.
		} else {
			return false;
		}
	}

	/**
	 * Update the object ownership enforced setting for the given bucket.
	 *
	 * @param string $bucket
	 * @param bool   $enforce
	 *
	 * Note: Should be overridden and implemented as required.
	 */
	public function enforce_object_ownership( string $bucket, bool $enforce ) {
	}

	/**
	 * Does the provider require a region be specified for all endpoint actions?
	 *
	 * @return bool
	 */
	public static function region_required() {
		return static::$region_required;
	}

	/**
	 * Returns the Provider's default region slug.
	 *
	 * @return string
	 */
	public static function get_default_region() {
		return static::$default_region;
	}

	/**
	 * Returns an array of valid region slugs and names.
	 *
	 * @return array Keys are region slug, values their name
	 */
	public static function get_regions() {
		$regions = apply_filters( static::$provider_key_name . '_get_regions', static::$regions ); // Backwards compatibility, e.g. 'aws_get_regions'.
		$regions = apply_filters( 'as3cf_' . static::$provider_key_name . '_get_regions', $regions );

		natsort( $regions );

		return $regions;
	}

	/**
	 * Returns readable region name.
	 *
	 * @param string $region
	 * @param bool   $with_key
	 *
	 * @return string
	 */
	public function get_region_name( $region = '', $with_key = false ) {
		if ( is_wp_error( $region ) ) {
			return '';
		}
		if ( empty( $region ) && ! static::region_required() ) {
			$region = static::get_default_region();
		}

		$regions = static::get_regions();

		$region_name = empty( $regions[ $region ] ) ? '' : $regions[ $region ];

		if ( $with_key ) {
			$region_name .= empty( $region_name ) ? $region : ' (' . $region . ')';
		}

		return $region_name;
	}

	/**
	 * Is given region key valid for provider?
	 *
	 * @param string $region
	 *
	 * @return bool
	 */
	public function is_region_valid( $region ) {
		return in_array( trim( $region ), array_keys( static::get_regions() ) );
	}

	/**
	 * Instantiate a new service client for the provider.
	 *
	 * @param array $args Options for required region/endpoint
	 *
	 * @throws Exception
	 *
	 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
	 */
	private function _init_client( array $args ) {
		if ( $this->needs_access_keys() ) {
			throw new Exception( static::get_needs_access_keys_desc() );
		}

		if ( is_null( $this->client ) ) {
			// There's no extra client authentication config required when using server roles.
			// So if neither Access Keys nor Key File are set as overrides, can safely fall through
			// as top of function confirmed that one of Access Keys, Key File or Server Roles configured.

			// Order of precedence, first match wins...
			// 1. Access Keys define then setting.
			// 2. Key File Path define then setting.
			// 3. Key File contents define then setting.
			// 4. Server Roles define then setting.

			if ( $this->are_access_keys_set() ) {
				$args = array_merge( array(
					'credentials' => array(
						'key'    => $this->get_access_key_id(),
						'secret' => $this->get_secret_access_key(),
					),
				), $args );
			} elseif ( $this->use_key_file() ) {
				// Key File Path takes precedence over Key File contents.
				if ( $this->get_key_file_path() ) {
					$args = array_merge( array(
						'keyFilePath' => $this->get_key_file_path(),
					), $args );
				} else {
					$args = array_merge( array(
						'keyFile' => $this->get_key_file(),
					), $args );
				}
			}

			// Add credentials and given args to default client args and then let user override.
			$args = array_merge( $this->default_client_args(), $args );
			$args = apply_filters( 'as3cf_' . static::$provider_key_name . '_init_client_args', $this->init_client_args( $args ) );
			$args = apply_filters( static::$provider_key_name . '_get_client_args', $args ); // Backwards compatibility, e.g. 'aws_get_client_args'.

			$this->client = $this->init_client( $args );
		}
	}

	/**
	 * Get the service client instance.
	 *
	 * @param array $args Options for required region/endpoint
	 * @param bool  $force
	 *
	 * @return Storage_Provider
	 * @throws Exception
	 */
	public function get_client( array $args, $force = false ) {
		if ( true === $force ) {
			$this->client = null;
		}

		$this->_init_client( $args );

		$args = apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_client_args', $this->init_service_client_args( $args ) );

		$this->client = $this->init_service_client( $args );

		return $this;
	}

	/**
	 * Get object keys from multiple clients.
	 *
	 * @param array  $regions
	 * @param string $source_type
	 *
	 * @return array
	 */
	public static function get_keys_from_regions( array $regions, $source_type ) {
		$keys = array();

		foreach ( $regions as $region ) {
			try {
				/* @var $client Storage_Provider */
				$client      = $region['provider_client'];
				$region_keys = $client->list_keys( $region['locations'] );
			} catch ( Exception $e ) {
				AS3CF_Error::log( get_class( $e ) . ' exception caught when executing list_keys: ' . $e->getMessage() );
				continue;
			}

			if ( ! empty( $region_keys ) ) {
				foreach ( $region_keys as $attachment_id => $found_keys ) {
					$keys[ $attachment_id ] = AS3CF_Utils::validate_attachment_keys( $attachment_id, $found_keys, $source_type );
				}
			}
		}

		return $keys;
	}

	/**
	 * Generate a stream wrapper compatible URL
	 *
	 * @param string $region
	 * @param string $bucket
	 * @param string $key
	 *
	 * @return string
	 */
	public function prepare_stream_wrapper_file( $region, $bucket, $key ) {
		$protocol = $this->get_stream_wrapper_protocol( $region );

		return $protocol . '://' . $bucket . '/' . $key;
	}

	/**
	 * Get the region specific prefix for URL
	 *
	 * @param string   $region
	 * @param null|int $expires
	 *
	 * @return string
	 */
	public function get_url_prefix( $region = '', $expires = null ) {
		/**
		 * Region specific prefix for raw URL
		 *
		 * @param string   $prefix
		 * @param string   $region
		 * @param null|int $expires
		 *
		 * @return string
		 */
		return apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_url_prefix', $this->url_prefix( $region, $expires ), $region, $expires );
	}

	/**
	 * Get the url domain for the files
	 *
	 * @param string $bucket
	 * @param string $region
	 * @param int    $expires
	 *
	 * @return string
	 */
	public function get_url_domain( $bucket, $region = '', $expires = null ) {
		global $as3cf;

		$args = array(
			'delivery-provider'      => $this->as3cf->get_setting( 'delivery-provider' ),
			'enable-delivery-domain' => $this->as3cf->get_setting( 'enable-delivery-domain' ),
			'delivery-domain'        => $this->as3cf->get_setting( 'delivery-domain' ),
			'force-https'            => $this->as3cf->use_ssl( $this->as3cf->get_setting( 'force-https' ) ),
		);

		// Backwards Compat.
		$args['domain']     = $args['enable-delivery-domain'] && $as3cf::get_default_delivery_provider() !== $args['delivery-provider'] ? 'cloudfront' : 'path';
		$args['cloudfront'] = $args['delivery-domain'];

		$prefix = $this->url_prefix( $region, $expires );
		$domain = $this->get_domain();
		$domain = empty( $prefix ) ? $domain : $prefix . '.' . $domain;

		return apply_filters(
			'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_url_domain',
			$this->url_domain( $domain, $bucket, $region, $expires, $args ),
			$bucket,
			$region,
			$expires,
			$args
		);
	}

	/**
	 * Returns notice text for when access credentials still need to be set.
	 *
	 * @return string
	 */
	public static function get_needs_access_keys_desc() {
		global $as3cf;

		return sprintf( __( 'Media cannot be offloaded due to missing access keys. <a href="%s">Update access keys</a>', 'amazon-s3-and-cloudfront' ), $as3cf::get_plugin_page_url() . '#/storage/provider' );
	}

	/**
	 * Returns notice text for when access key id is missing.
	 *
	 * @return string
	 */
	private static function get_needs_access_key_id_desc(): string {
		global $as3cf;

		return sprintf( __( 'Media cannot be offloaded due to a missing Access Key ID. <a href="%s">Update access keys</a>', 'amazon-s3-and-cloudfront' ), $as3cf::get_plugin_page_url() . '#/storage/provider' );
	}

	/**
	 * Returns notice text for when secret access key is missing.
	 *
	 * @return string
	 */
	private static function get_needs_secret_access_key_desc(): string {
		global $as3cf;

		return sprintf( __( 'Media cannot be offloaded due to a missing Secret Access Key. <a href="%s">Update access keys</a>', 'amazon-s3-and-cloudfront' ), $as3cf::get_plugin_page_url() . '#/storage/provider' );
	}

	/**
	 * Notification strings for when Block All Public Access enabled on storage provider but delivery provider does not support it.
	 *
	 * @return array Keys are heading and message
	 *
	 * This function should be overridden by providers that do support Block All Public Access to give more specific message.
	 */
	public static function get_block_public_access_warning() {
		return array(
			'heading' => _x( 'Block All Public Access is Enabled', 'warning heading', 'amazon-s3-and-cloudfront' ),
			'message' => __( 'The current Delivery Provider does not support Block All Public Access.', 'amazon-s3-and-cloudfront' ),
		);
	}

	/**
	 * Notification strings for when Object Ownership enforced on storage provider but delivery provider does not support it.
	 *
	 * @return array Keys are heading and message
	 *
	 * This function should be overridden by providers that do support enforced Object Ownership to give more specific message.
	 */
	public static function get_object_ownership_enforced_warning() {
		return array(
			'heading' => _x( 'Object Ownership is Enforced', 'warning heading', 'amazon-s3-and-cloudfront' ),
			'message' => __( 'The current Delivery Provider does not support Object Ownership enforcement.', 'amazon-s3-and-cloudfront' ),
		);
	}

	/**
	 * Notification strings for when Storage Provider is about to be changed but media has already been offloaded.
	 *
	 * @param int $offloaded
	 *
	 * @return array Keys are heading and message
	 *
	 * Note: Because we don't grab all the Storage Provider information into the UI after bring-up
	 * unless settings have changed, there's an edge case whereby nothing was offloaded, offloads happen,
	 * then user tries to change provider. At that point UI does know that there have been offloads,
	 * but there has not been a reason to grab storage providers from server. That's why there's a fall-back
	 * message without the count of offloads included.
	 */
	public static function get_media_already_offloaded_warning( $offloaded = 0 ) {
		global $as3cf;

		$message = __( 'You should remove them from the bucket before changing storage provider.', 'amazon-s3-and-cloudfront' );

		if ( 1 === $offloaded ) {
			$heading = sprintf( __( '<strong>Warning:</strong> You have %d offloaded media item.', 'amazon-s3-and-cloudfront' ), $offloaded );
			$message = __( 'You should remove it from the bucket before changing storage provider.', 'amazon-s3-and-cloudfront' );
		} elseif ( 1 < $offloaded ) {
			$heading = sprintf( __( '<strong>Warning:</strong> You have %d offloaded media items.', 'amazon-s3-and-cloudfront' ), $offloaded );
		} else {
			$heading = __( '<strong>Warning:</strong> You have offloaded media items.', 'amazon-s3-and-cloudfront' );
		}
		$message .= '&nbsp;' . $as3cf::more_info_link( '/wp-offload-media/doc/how-to-change-storage-provider/#mixed-provider' );

		return array(
			'heading' => $heading,
			'message' => $message,
		);
	}

	/**
	 * The string to be used for the Use Server Roles option in the UI.
	 *
	 * @return string
	 */
	public static function get_use_server_roles_title() {
		return sprintf( __( 'My server is on %s and I\'d like to use IAM Roles', 'amazon-s3-and-cloudfront' ), static::get_provider_name() );
	}

	/**
	 * The string to be used for the define access keys description.
	 *
	 * @return string
	 */
	public static function get_define_access_keys_desc() {
		global $as3cf;

		$mesg = __( 'Copy the following snippet to <strong>near the top</strong> of your wp-config.php and replace the stars with the keys.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::more_info_link( '/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() . '/#save-access-keys' );

		return $mesg;
	}

	/**
	 * The string to be used for the defined access keys example.
	 *
	 * @return string
	 */
	public static function get_define_access_keys_example() {
		global $as3cf;

		// NOTE: This string is specifically formatted to flush left.
		return "define( '" . $as3cf::preferred_settings_constant() . "', serialize( array(
	'provider' => '" . static::get_provider_key_name() . "',
	'access-key-id' => '********************',
	'secret-access-key' => '**************************************',
) ) );";
	}

	/**
	 * The string to be used for the entered access keys description.
	 *
	 * @return string
	 */
	public static function get_enter_access_keys_desc() {
		global $as3cf;

		$mesg = __( 'Storing your access keys in the database is less secure than the other options, but if you\'re ok with that, go ahead and enter your keys in the form below.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::more_info_link( '/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() . '/#save-access-keys' );

		return $mesg;
	}

	/**
	 * The string to be used for the get access keys help notice.
	 *
	 * @return string
	 */
	public static function get_access_keys_help() {
		global $as3cf;

		return sprintf(
			__( 'Need help configuring your chosen storage provider? <a href="%s" target="_blank">View the Quick Start Guide</a>', 'amazon-s3-and-cloudfront' ),
			$as3cf::dbrains_url(
				'/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug(),
				array(
					'utm_campaign' => 'support+docs',
				)
			)
		);
	}

	/**
	 * The string to be used for the define key file description.
	 *
	 * @return string
	 */
	public static function get_define_key_file_desc() {
		global $as3cf;

		$mesg = __( 'Copy the following snippet to <strong>near the top</strong> of your wp-config.php and replace "<strong>/path/to/key/file.json</strong>".', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::more_info_link( '/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() . '/#save-key-file' );

		return $mesg;
	}

	/**
	 * The string to be used for the define key file example.
	 *
	 * @return string
	 */
	public static function get_define_key_file_example() {
		global $as3cf;

		// NOTE: This string is specifically formatted to flush left.
		return "define( '" . $as3cf::preferred_settings_constant() . "', serialize( array(
	'provider' => '" . static::get_provider_key_name() . "',
	'key-file-path' => '/path/to/key/file.json',
) ) );";
	}

	/**
	 * The string to be used for the enter key file description.
	 *
	 * @return string
	 */
	public static function get_enter_key_file_desc() {
		global $as3cf;

		$mesg = __( 'Storing your key file\'s contents in the database is less secure than the other options, but if you\'re ok with that, go ahead and enter your key file\'s JSON data in the field below.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::more_info_link( '/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() . '/#save-key-file' );

		return $mesg;
	}

	/**
	 * The string to be used for the use server roles description.
	 *
	 * @return string
	 */
	public static function get_use_server_roles_desc() {
		global $as3cf;

		$mesg = sprintf(
			__( 'If you host your WordPress site on %s, click the <strong>Next</strong> button to make use of IAM Roles.', 'amazon-s3-and-cloudfront' ),
			static::get_provider_name()
		);
		$mesg .= '<br><br>';
		$mesg .= __( 'Optionally, copy the following snippet to <strong>near the top</strong> of your wp-config.php.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::more_info_link(
			'/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() . '/#iam-roles'
		);

		return $mesg;
	}

	/**
	 * Get an array of access key related constants that are currently defined.
	 *
	 * @return array
	 */
	public static function used_access_keys_constants(): array {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		$defines = array();

		// Access Keys defined in dedicated constant.
		if ( static::is_any_access_key_constant_defined() ) {
			$constant = static::access_key_id_constant();

			if ( $constant ) {
				$defines[] = $constant;
			}

			$constant = static::secret_access_key_constant();

			if ( $constant ) {
				$defines[] = $constant;
			}
		}

		if ( $as3cf->using_legacy_defines() ) {
			return $defines;
		}

		// Access Keys defined in standard settings constant.
		if ( in_array( 'access-key-id', $as3cf->get_non_legacy_defined_settings_keys() ) || in_array( 'secret-access-key', $as3cf->get_non_legacy_defined_settings_keys() ) ) {
			$defines[] = $as3cf::settings_constant();
		}

		return $defines;
	}

	/**
	 * Get an array of key file path related constants that are currently defined.
	 *
	 * @return array
	 */
	public static function used_key_file_path_constants() {
		global $as3cf;

		$defined_settings = $as3cf->get_defined_settings();
		$defines          = array();

		// Key File Path defined in dedicated constant.
		if ( static::is_key_file_path_constant_defined() ) {
			$constant = static::key_file_path_constant();

			if ( $constant ) {
				$defines[] = $constant;
			}
		}

		// Key File Path defined in standard settings constant.
		if ( ! empty( $defined_settings['key-file-path'] ) ) {
			$defines[] = $as3cf::settings_constant();
		}

		return $defines;
	}

	/**
	 * Get an array of server roles related constants that are currently defined.
	 *
	 * @return array
	 */
	public static function used_server_roles_constants() {
		global $as3cf;

		$defined_settings = $as3cf->get_defined_settings();
		$defines          = array();

		// Use Server Roles defined in dedicated constant.
		if ( static::is_use_server_roles_constant_defined() ) {
			$constant = static::use_server_roles_constant();

			if ( $constant ) {
				$defines[] = $constant;
			}
		}

		// Use Server Roles defined in standard settings constant.
		if ( ! empty( $defined_settings['use-server-roles'] ) ) {
			$defines[] = $as3cf::settings_constant();
		}

		return $defines;
	}

	/**
	 * Returns a string describing how the provider's authentication method has been defined, if it has.
	 *
	 * @return string
	 *
	 * TODO: The collection of used constants could be useful elsewhere and could be refactored out.
	 */
	public static function get_defined_auth_desc() {
		global $as3cf;

		$mesg = '';

		if ( static::use_access_keys_allowed() ) {
			$defines = static::used_access_keys_constants();

			if ( ! empty( $defines ) ) {
				$mesg = __( 'You\'ve defined your access keys in your wp-config.php.', 'amazon-s3-and-cloudfront' );
				$mesg .= '<br>';

				if ( count( $defines ) > 1 ) {
					$mesg .= _x( 'To select a different option here, simply comment out or remove the "%1$s" defines in your wp-config.php.', 'Access Keys defined in multiple defines.', 'amazon-s3-and-cloudfront' );
				} else {
					$mesg .= _x( 'To select a different option here, simply comment out or remove the "%1$s" define in your wp-config.php.', 'Access Keys defined in single define.', 'amazon-s3-and-cloudfront' );
				}

				$multiple_defines_glue = _x( '" & "', 'joins multiple define keys in notice', 'amazon-s3-and-cloudfront' );
				$defined_constants_str = join( $multiple_defines_glue, $defines );
				$mesg                  = sprintf( $mesg, $defined_constants_str );
				$mesg                  .= '&nbsp;';
				$mesg                  .= $as3cf::more_info_link( '/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() . '/#save-access-keys' );

				return $mesg;
			}
		} elseif ( static::use_key_file_allowed() ) {
			$defines = static::used_key_file_path_constants();

			if ( ! empty( $defines ) ) {
				$mesg = __( 'You\'ve defined your key file path in your wp-config.php.', 'amazon-s3-and-cloudfront' );
				$mesg .= '<br>';

				if ( count( $defines ) > 1 ) {
					$mesg .= _x( 'To select a different option here, simply comment out or remove the "%1$s" defines in your wp-config.php.', 'Key File Path defined in multiple defines.', 'amazon-s3-and-cloudfront' );
				} else {
					$mesg .= _x( 'To select a different option here, simply comment out or remove the "%1$s" define in your wp-config.php.', 'Key File Path defined in single define.', 'amazon-s3-and-cloudfront' );
				}

				$multiple_defines_glue = _x( '" & "', 'joins multiple define keys in notice', 'amazon-s3-and-cloudfront' );
				$defined_constants_str = join( $multiple_defines_glue, $defines );
				$mesg                  = sprintf( $mesg, $defined_constants_str );
				$mesg                  .= '&nbsp;';
				$mesg                  .= $as3cf::more_info_link( '/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() . '/#save-key-file' );

				return $mesg;
			}
		}

		if ( static::use_server_roles_allowed() ) {
			$defines = static::used_server_roles_constants();

			if ( ! empty( $defines ) ) {
				$mesg = __( 'You\'ve chosen this option in your wp-config.php.', 'amazon-s3-and-cloudfront' );
				$mesg .= '<br>';

				if ( count( $defines ) > 1 ) {
					$mesg .= _x( 'To select a different option here, simply comment out or remove the "%1$s" defines in your wp-config.php.', 'Key File Path defined in multiple defines.', 'amazon-s3-and-cloudfront' );
				} else {
					$mesg .= _x( 'To select a different option here, simply comment out or remove the "%1$s" define in your wp-config.php.', 'Key File Path defined in single define.', 'amazon-s3-and-cloudfront' );
				}

				$multiple_defines_glue = _x( '" & "', 'joins multiple define keys in notice', 'amazon-s3-and-cloudfront' );
				$defined_constants_str = join( $multiple_defines_glue, $defines );
				$mesg                  = sprintf( $mesg, $defined_constants_str );
				$mesg                  .= '&nbsp;';
				$mesg                  .= $as3cf::more_info_link( '/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() . '/#iam-roles' );

				return $mesg;
			}
		}

		return $mesg;
	}

	/**
	 * The string to be used for the use server roles example.
	 *
	 * Note: The seemingly weird out-dented formatting here is correct for intended usage.
	 *
	 * @return string
	 */
	public static function get_use_server_roles_example() {
		global $as3cf;

		// NOTE: This string is specifically formatted to flush left.
		return "define( '" . $as3cf::preferred_settings_constant() . "', serialize( array(
	'provider' => '" . static::get_provider_key_name() . "',
	'use-server-roles' => true,
) ) );";
	}

	/**
	 * Prepare the bucket error.
	 *
	 * @param WP_Error $object
	 * @param bool     $single Are we dealing with a single bucket?
	 *
	 * @return string
	 */
	public function prepare_bucket_error( WP_Error $object, bool $single = true ): string {
		if ( 'Access Denied' === $object->get_error_message() ) {
			// If the bucket error is access denied, show our notice message.
			$out = $this->get_access_denied_notice_message( $single );
		} else {
			$out = $object->get_error_message();
		}

		return $out;
	}

	/**
	 * Get the access denied bucket error notice message.
	 *
	 * @param bool $single
	 *
	 * @return string
	 */
	private function get_access_denied_notice_message( bool $single = true ): string {
		if ( $this->needs_access_keys() ) {
			return $this->get_needs_access_keys_desc();
		}

		$url = $this->as3cf->dbrains_url( '/wp-offload-media/doc/quick-start-guide/', array(
			'utm_campaign' => 'error+messages',
		), 'bucket-restrictions' );

		$quick_start = sprintf( '<a class="js-link" href="%s" target="_blank">%s</a>', $url, __( 'How to set permissions', 'amazon-s3-and-cloudfront' ) );

		$message = sprintf( __( 'Media cannot be offloaded due to access restrictions on the provided bucket. The provided credentials may not have the correct permissions. %s', 'amazon-s3-and-cloudfront' ), $quick_start );
		if ( ! $single ) {
			$message = sprintf( __( 'Media cannot be offloaded due to access restrictions on the provided buckets. The provided credentials may not have the correct permissions. %s', 'amazon-s3-and-cloudfront' ), $quick_start );
		}

		return $message;
	}

	/**
	 * Validate storage settings for the configured provider for the storage status indicator.
	 *
	 * @param bool $force Force time resource consuming or state altering tests to run.
	 *
	 * @return AS3CF_Result
	 */
	public function validate_settings( bool $force = false ): AS3CF_Result {
		// Verify that the provider key provided in settings is valid.
		$valid_providers = array_keys( $this->as3cf->get_provider_classes( 'storage' ) );
		if ( ! in_array( $this->as3cf->get_core_setting( 'provider' ), $valid_providers ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				sprintf(
					__( 'An invalid storage provider has been defined. Please use %s.', 'amazon-s3-and-cloudfront' ),
					"<code>" . AS3CF_Utils::human_readable_join( "</code>, <code>", "</code> or <code>", $valid_providers ) . "</code>"
				)
			);
		}

		// If the current provider uses access keys, we may have recorded validation issues along the way.
		if ( $this->has_validation_issue( 'miss_both_access_keys' ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				$this->get_needs_access_keys_desc()
			);
		}

		if ( $this->has_validation_issue( 'miss_access_key_id' ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				$this->get_needs_access_key_id_desc()
			);
		}

		if ( $this->has_validation_issue( 'miss_secret_access_key' ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				$this->get_needs_secret_access_key_desc()
			);
		}

		// Force checking if a key file is in use and is valid.
		$this->use_key_file();

		// Did the key file path validation trigger any issues?
		$key_file_notice = $this->as3cf->notices->find_notice_by_id( 'validate-key-file-path' );
		if ( ! empty( $key_file_notice ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				ucfirst( $this->as3cf->notices->get_short_message( $key_file_notice ) )
			);
		}

		// Did the key file content validation trigger any issues?
		$key_file_content_notice = $this->as3cf->notices->find_notice_by_id( 'validate-key-file-content' );
		if ( ! empty( $key_file_content_notice ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				ucfirst( $this->as3cf->notices->get_short_message( $key_file_content_notice ) )
			);
		}

		// Do we have a bucket name?
		if ( empty( $this->as3cf->get_core_setting( 'bucket' ) ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				__( 'Media cannot be offloaded due to a missing bucket name.', 'amazon-s3-and-cloudfront' )
			);
		}

		if ( $force ) {
			$this->as3cf->get_bucket_region( $this->as3cf->get_core_setting( 'bucket' ), false );
		}

		// Did the last bucket validation trigger any notices?
		$bucket_error_notice = $this->as3cf->notices->find_notice_by_id( 'bucket-error' );
		if ( ! empty( $bucket_error_notice ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				ucfirst( $this->as3cf->notices->get_short_message( $bucket_error_notice ) )
			);
		}
		if ( ! $this->as3cf->bucket_writable( null, null, $force ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
				$this->get_access_denied_notice_message()
			);
		}

		// Test if offloading is enabled in settings.
		if ( ! $this->as3cf->get_core_setting( 'copy-to-s3', false ) ) {
			return new AS3CF_Result(
				Validator_Interface::AS3CF_STATUS_MESSAGE_WARNING,
				__( 'Storage provider is successfully connected, but new media will not be offloaded until <strong>Offload Media</strong> is enabled.', 'amazon-s3-and-cloudfront' )
			);
		}

		return new AS3CF_Result(
			Validator_Interface::AS3CF_STATUS_MESSAGE_SUCCESS,
			__( 'Storage provider is successfully connected and ready to offload new media.', 'amazon-s3-and-cloudfront' )
		);
	}

	/**
	 * Get the name of the actions that are fired when the settings that the validator
	 * is responsible for are saved.
	 *
	 * @return array
	 */
	public function post_save_settings_actions(): array {
		return array( 'as3cf_post_save_settings' );
	}

	/**
	 * Returns default args array for the client.
	 *
	 * @return array
	 */
	abstract protected function default_client_args();

	/**
	 * Process the args before instantiating a new client for the provider's SDK.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	abstract protected function init_client_args( array $args );

	/**
	 * Instantiate a new client for the provider's SDK.
	 *
	 * @param array $args
	 */
	abstract protected function init_client( array $args );

	/**
	 * Process the args before instantiating a new service specific client.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	abstract protected function init_service_client_args( array $args );

	/**
	 * Instantiate a new service specific client.
	 * Depending on SDK, may simply return client instantiated with `init_client`.
	 *
	 * @param array $args
	 *
	 * @return mixed
	 */
	abstract protected function init_service_client( array $args );

	/**
	 * Make sure region "slug" fits expected format.
	 *
	 * @param string $region
	 *
	 * @return string
	 */
	abstract public function sanitize_region( $region );

	/**
	 * Create bucket.
	 *
	 * @param array $args
	 */
	abstract public function create_bucket( array $args );

	/**
	 * Check whether bucket exists.
	 *
	 * @param string $bucket
	 *
	 * @return bool
	 */
	abstract public function does_bucket_exist( $bucket );

	/**
	 * Returns region for bucket.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	abstract public function get_bucket_location( array $args );

	/**
	 * List buckets.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	abstract public function list_buckets( array $args = array() );

	/**
	 * Check whether key exists in bucket.
	 *
	 * @param string $bucket
	 * @param string $key
	 * @param array  $options
	 *
	 * @return bool
	 */
	abstract public function does_object_exist( $bucket, $key, array $options = array() );

	/**
	 * Get public "canned" ACL string.
	 *
	 * @return string
	 */
	abstract public function get_public_acl();

	/**
	 * Get private "canned" ACL string.
	 *
	 * @return string
	 */
	abstract public function get_private_acl();

	/**
	 * Download object, destination specified in args.
	 *
	 * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#getobject
	 *
	 * @param array $args
	 */
	abstract public function get_object( array $args );

	/**
	 * Get object's URL.
	 *
	 * @param string $bucket
	 * @param string $key
	 * @param int    $timestamp
	 * @param array  $args
	 *
	 * @return string
	 */
	abstract public function get_object_url( $bucket, $key, $timestamp, array $args = array() );

	/**
	 * List objects.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	abstract public function list_objects( array $args = array() );

	/**
	 * Update the ACL for an object.
	 *
	 * @param array $args
	 */
	abstract public function update_object_acl( array $args );

	/**
	 * Update the ACL for multiple objects.
	 *
	 * @param array $items
	 *
	 * @return array Failures with elements Key and Message
	 */
	abstract public function update_object_acls( array $items );

	/**
	 * Upload file to bucket.
	 *
	 * @param array $args
	 */
	abstract public function upload_object( array $args );

	/**
	 * Delete object from bucket.
	 *
	 * @param array $args
	 */
	abstract public function delete_object( array $args );

	/**
	 * Delete multiple objects from bucket.
	 *
	 * @param array $args
	 */
	abstract public function delete_objects( array $args );

	/**
	 * Returns arrays of found keys for given bucket and prefix locations, retaining given array's integer based index.
	 *
	 * @param array $locations Array with attachment ID as key and Bucket and Prefix in an associative array as values.
	 *
	 * @return array
	 */
	abstract public function list_keys( array $locations );

	/**
	 * Copies objects into current bucket from another bucket hosted with provider.
	 *
	 * @param array $items
	 *
	 * @return array Failures with elements Key and Message
	 */
	abstract public function copy_objects( array $items );

	/**
	 * Generate the stream wrapper protocol
	 *
	 * @param string $region
	 *
	 * @return string
	 */
	abstract protected function get_stream_wrapper_protocol( $region );

	/**
	 * Register a stream wrapper for specific region.
	 *
	 * @param string $region
	 *
	 * @return bool
	 */
	abstract public function register_stream_wrapper( $region );

	/**
	 * Check that a bucket and key can be written to.
	 *
	 * @param string $bucket
	 * @param string $key
	 * @param string $file_contents
	 *
	 * @return bool|string Error message on unexpected exception
	 */
	abstract public function can_write( $bucket, $key, $file_contents );

	/**
	 * Get the region specific prefix for raw URL
	 *
	 * @param string   $region
	 * @param null|int $expires
	 *
	 * @return string
	 */
	abstract protected function url_prefix( $region = '', $expires = null );

	/**
	 * Get the url domain for the files
	 *
	 * @param string $domain Likely prefixed with region
	 * @param string $bucket
	 * @param string $region
	 * @param int    $expires
	 * @param array  $args   Allows you to specify custom URL settings
	 *
	 * @return string
	 */
	abstract protected function url_domain( $domain, $bucket, $region = '', $expires = null, $args = array() );
}
