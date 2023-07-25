<?php

namespace DeliciousBrains\WP_Offload_Media\Providers;

use Amazon_S3_And_CloudFront;
use AS3CF_Plugin_Base;

abstract class Provider {

	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	protected $as3cf;

	/**
	 * Can the displayed provider service name be overridden by the user?
	 *
	 * @var bool
	 */
	protected static $provider_service_name_override_allowed = false;

	/**
	 * @var string
	 */
	protected static $provider_service_name_setting_name = 'provider-service-name';

	/**
	 * @var string
	 */
	protected static $provider_name = '';

	/**
	 * @var string
	 */
	protected static $provider_short_name = '';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = '';

	/**
	 * @var string
	 */
	protected static $service_name = '';

	/**
	 * @var string
	 */
	protected static $service_short_name = '';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $service_key_name = '';

	/**
	 * Optional override of "Provider Name" + "Service Name" for friendly name for service.
	 *
	 * @var string
	 */
	protected static $provider_service_name = '';

	/**
	 * The slug for the service's quick start guide doc.
	 *
	 * @var string
	 */
	protected static $provider_service_quick_start_slug = 'quick-start-guide';

	/**
	 * @var string
	 */
	protected $default_domain = '';

	/**
	 * @var string
	 */
	protected $console_url = '';

	/**
	 * @var string
	 */
	protected $console_url_prefix_param = '';

	/**
	 * @var bool
	 */
	protected static $block_public_access_supported = false;

	/**
	 * @var bool
	 */
	protected static $object_ownership_supported = false;

	/**
	 * Provider constructor.
	 *
	 * @param AS3CF_Plugin_Base $as3cf
	 */
	public function __construct( AS3CF_Plugin_Base $as3cf ) {
		$this->as3cf = $as3cf;
	}

	/**
	 * Returns the full name for the provider.
	 *
	 * @return string
	 */
	public static function get_provider_name() {
		return static::$provider_name;
	}

	/**
	 * Returns the key friendly name for the provider.
	 *
	 * @return string
	 */
	public static function get_provider_key_name() {
		return static::$provider_key_name;
	}

	/**
	 * Returns the full name for the service.
	 *
	 * @return string
	 */
	public static function get_service_name() {
		return static::$service_name;
	}

	/**
	 * Returns the key friendly name for the service.
	 *
	 * @return string
	 */
	public static function get_service_key_name() {
		return static::$service_key_name;
	}

	/**
	 * Returns the full name for the provider and service for display.
	 *
	 * @param bool $override_allowed Use override if available? Defaults to true.
	 *
	 * @return string
	 */
	public static function get_provider_service_name( $override_allowed = true ) {
		if ( ! empty( static::$provider_service_name ) ) {
			$result = static::$provider_service_name;
		} else {
			$result = static::$provider_name . ' ' . static::$service_name;
		}

		if ( false === $override_allowed || false === static::provider_service_name_override_allowed() ) {
			return $result;
		} else {
			/** @var Amazon_S3_And_CloudFront $as3cf */
			global $as3cf;

			$override = stripslashes( $as3cf->get_setting( static::$provider_service_name_setting_name ) );

			if ( empty( $override ) ) {
				return $result;
			} else {
				return $override;
			}
		}
	}

	/**
	 * Returns the full name for the provider and service for use as logo's alt text.
	 *
	 * @param bool $override_allowed Use override if available? Defaults to true.
	 *
	 * @return string
	 *
	 * Note: For accessibility reasons, returned string should differ from `get_provider_service_name`.
	 */
	public static function get_icon_desc( $override_allowed = true ) {
		return sprintf( _x( '%s logo', 'Provider icon\'s alt text', 'amazon-s3-and-cloudfront' ), static::get_provider_service_name( $override_allowed ) );
	}

	/**
	 * Can the displayed provider service name be overridden by the user?
	 *
	 * @return bool
	 */
	public static function provider_service_name_override_allowed() {
		return static::$provider_service_name_override_allowed;
	}

	/**
	 * Returns the slug for the service's quick start guide doc.
	 *
	 * @return string
	 */
	public static function get_provider_service_quick_start_slug() {
		return static::$provider_service_quick_start_slug;
	}

	/**
	 * Returns URL for quick start guide.
	 *
	 * @return string
	 */
	public static function get_provider_service_quick_start_url() {
		global $as3cf;

		return $as3cf::dbrains_url( '/wp-offload-media/doc/' . static::get_provider_service_quick_start_slug() );
	}

	/**
	 * Returns the Provider's base domain.
	 *
	 * Does not include region prefix or bucket path etc.
	 *
	 * @return string
	 */
	public function get_domain() {
		return apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_domain', $this->default_domain );
	}

	/**
	 * Get the link to the provider's console.
	 *
	 * @param string $bucket
	 * @param string $prefix
	 * @param string $region
	 *
	 * @return string
	 */
	public function get_console_url( string $bucket = '', string $prefix = '', string $region = '' ): string {
		if ( '' !== $prefix ) {
			$prefix = $this->get_console_url_prefix_param() . apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_console_url_prefix_value', $prefix );
		}

		$suffix = apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_console_url_suffix_param', $this->get_console_url_suffix_param( $bucket, $prefix, $region ) );

		return apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_console_url', $this->console_url ) . $bucket . $prefix . $suffix;
	}

	/**
	 * Get the prefix param to append to the link to the provider's console.
	 *
	 * @return string
	 */
	public function get_console_url_prefix_param(): string {
		return apply_filters( 'as3cf_' . static::$provider_key_name . '_' . static::$service_key_name . '_console_url_prefix_param', $this->console_url_prefix_param );
	}

	/**
	 * Title to be shown for provider's console link.
	 *
	 * @return string
	 */
	public static function get_console_title(): string {
		return _x( 'Provider Console', 'Default provider console link text', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Does provider support blocking direct public access to bucket?
	 *
	 * @return bool
	 */
	public static function block_public_access_supported() {
		return static::$block_public_access_supported;
	}

	/**
	 * Description for when Block All Public Access is enabled and Delivery Provider supports it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_enabled_supported_desc() {
		return '';
	}

	/**
	 * Description for when Block All Public Access is enabled and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_enabled_unsupported_desc() {
		return '';
	}

	/**
	 * Description for when Block All Public Access is enabled during initial setup.
	 *
	 * @return string
	 */
	public static function get_block_public_access_enabled_unsupported_setup_desc() {
		return '';
	}

	/**
	 * Description for when Block All Public Access is disabled and Delivery Provider supports it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_disabled_supported_desc() {
		return '';
	}

	/**
	 * Description for when Block All Public Access is disabled and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_disabled_unsupported_desc() {
		return '';
	}

	/**
	 * Does provider support object ownership controls?
	 *
	 * @return bool
	 */
	public static function object_ownership_supported(): bool {
		return static::$object_ownership_supported;
	}

	/**
	 * Description for when Object Ownership is enforced and Delivery Provider supports it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_enforced_supported_desc(): string {
		return '';
	}

	/**
	 * Description for when Object Ownership is enforced and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_enforced_unsupported_desc(): string {
		return '';
	}

	/**
	 * Description for when Object Ownership is enforced during initial setup.
	 *
	 * @return string
	 */
	public static function get_object_ownership_enforced_unsupported_setup_desc(): string {
		return '';
	}

	/**
	 * Description for when Object Ownership is not enforced and Delivery Provider supports it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_not_enforced_supported_desc(): string {
		return '';
	}

	/**
	 * Description for when Object Ownership is not enforced and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_not_enforced_unsupported_desc(): string {
		return '';
	}

	/**
	 * Does the provider require ACLs to be used?
	 *
	 * @return bool
	 */
	public static function requires_acls(): bool {
		if ( static::block_public_access_supported() || static::object_ownership_supported() ) {
			return false;
		}

		return true;
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
	abstract protected function get_console_url_suffix_param( string $bucket = '', string $prefix = '', string $region = '' ): string;
}
