<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Delivery;

class GCP_CDN extends Delivery_Provider {

	/**
	 * Which storage providers does the delivery provider support, empty means all.
	 *
	 * @var array
	 */
	protected static $supported_storage_providers = array(
		'gcp',
	);

	/**
	 * @var string
	 */
	protected static $provider_name = 'Google Cloud Platform';

	/**
	 * @var string
	 */
	protected static $provider_short_name = 'GCP';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = 'gcp';

	/**
	 * @var string
	 */
	protected static $service_name = 'Cloud CDN';

	/**
	 * @var string
	 */
	protected static $service_short_name = 'Cloud CDN';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $service_key_name = 'cdn';

	/**
	 * Optional override of "Provider Name" + "Service Name" for friendly name for service.
	 *
	 * @var string
	 */
	protected static $provider_service_name = 'GCP Cloud CDN';

	/**
	 * The slug for the service's quick start guide doc.
	 *
	 * @var string
	 */
	protected static $provider_service_quick_start_slug = 'how-to-set-up-a-custom-domain-cdn-for-google-cloud-storage';

	/**
	 * @var string
	 */
	protected $console_url = 'https://console.cloud.google.com/net-services/cdn/list';

	/**
	 * @inheritDoc
	 */
	public static function signed_urls_support_desc() {
		return __( 'Private Media Supported', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Title to be shown for provider's console link.
	 *
	 * @return string
	 */
	public static function get_console_title(): string {
		return _x( 'Google Cloud Console', 'Provider console link text', 'amazon-s3-and-cloudfront' );
	}
}
