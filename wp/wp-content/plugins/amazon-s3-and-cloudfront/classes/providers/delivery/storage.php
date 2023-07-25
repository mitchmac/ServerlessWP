<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Delivery;

use DeliciousBrains\WP_Offload_Media\Items\Item;

class Storage extends Delivery_Provider {

	/**
	 * @var bool
	 */
	protected static $delivery_domain_allowed = false;

	/**
	 * @var string
	 */
	protected static $provider_name = 'Storage Provider';

	/**
	 * @var string
	 */
	protected static $provider_short_name = 'Storage';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = 'storage';

	/**
	 * @var string
	 */
	protected static $service_name = 'Bucket';

	/**
	 * @var string
	 */
	protected static $service_short_name = 'Bucket';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $service_key_name = 'bucket';

	/**
	 * Returns the full name for the provider.
	 *
	 * @return string
	 */
	public static function get_provider_name() {
		/** @var \Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		return $as3cf->get_storage_provider()->get_provider_name();
	}

	/**
	 * Returns the full name for the service.
	 *
	 * @return string
	 */
	public static function get_service_name() {
		/** @var \Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		return $as3cf->get_storage_provider()->get_service_name();
	}

	/**
	 * Returns the full name for the provider and service for display.
	 *
	 * @param bool $override_allowed Not used.
	 *
	 * @return string
	 */
	public static function get_provider_service_name( $override_allowed = true ) {
		/** @var \Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		return $as3cf->get_storage_provider()->get_provider_service_name();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function edge_server_support_desc() {
		return __( 'Slow', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function signed_urls_support_desc() {
		return __( 'Private Media Supported', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_url( Item $as3cf_item, $path, $domain, $scheme, $headers = array() ) {
		$lookup_domain = $this->as3cf->get_storage_provider_instance( $as3cf_item->provider() )->get_url_domain( $as3cf_item->bucket(), $as3cf_item->region() );

		return parent::get_url( $as3cf_item, $path, $lookup_domain, $scheme, $headers );
	}

	/**
	 * Description for when Block All Public Access is enabled and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_enabled_unsupported_desc() {
		return __( 'You need to disable Block All Public Access so that your bucket is accessible for delivery.', 'amazon-s3-and-cloudfront' );
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
			__( 'You need to edit the bucket\'s Object Ownership setting and <a href="%1$s">enable ACLs</a> or add a <a href="%2$s">Bucket Policy</a> so that objects can be made available for delivery.', 'amazon-s3-and-cloudfront' ),
			$object_ownership_doc . '#acls',
			$object_ownership_doc . '#bucket-policy'
		);
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
		return $this->as3cf->get_storage_provider()->get_console_url( $bucket, $prefix, $region );
	}

	/**
	 * Title to be shown for provider's console link.
	 *
	 * @return string
	 */
	public static function get_console_title(): string {
		global $as3cf;

		return $as3cf->get_storage_provider()->get_console_title();
	}
}
