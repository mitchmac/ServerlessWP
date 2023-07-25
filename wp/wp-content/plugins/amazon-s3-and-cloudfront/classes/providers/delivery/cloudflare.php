<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Delivery;

class Cloudflare extends Delivery_Provider {

	/**
	 * Which storage providers does the delivery provider support, empty means all.
	 *
	 * @var array
	 */
	protected static $supported_storage_providers = array(
		// TODO: Add 'do' after testing and documenting.
		'aws',
	);

	/**
	 * @var string
	 */
	protected static $provider_name = 'Cloudflare';

	/**
	 * @var string
	 */
	protected static $provider_short_name = 'Cloudflare';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = 'cloudflare';

	/**
	 * @var string
	 */
	protected static $service_name = 'CDN';

	/**
	 * @var string
	 */
	protected static $service_short_name = 'CDN';

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
	protected static $provider_service_name = 'Cloudflare';

	/**
	 * @var string
	 */
	protected static $provider_service_quick_start_slug = 'cloudflare-setup';

	/**
	 * @var string
	 */
	protected $console_url = 'https://dash.cloudflare.com';

	/**
	 * Title to be shown for provider's console link.
	 *
	 * @return string
	 */
	public static function get_console_title(): string {
		return _x( 'Cloudflare Dashboard', 'Provider console link text', 'amazon-s3-and-cloudfront' );
	}
}
