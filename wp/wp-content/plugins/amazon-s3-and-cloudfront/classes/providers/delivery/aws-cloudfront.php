<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Delivery;

use AS3CF_Plugin_Base;

class AWS_CloudFront extends Delivery_Provider {

	/**
	 * Which storage providers does the delivery provider support, empty means all.
	 *
	 * @var array
	 */
	protected static $supported_storage_providers = array(
		'aws',
	);

	/**
	 * @var string
	 */
	protected static $provider_name = 'Amazon Web Services';

	/**
	 * @var string
	 */
	protected static $provider_short_name = 'AWS';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = 'aws';

	/**
	 * @var string
	 */
	protected static $service_name = 'CloudFront';

	/**
	 * @var string
	 */
	protected static $service_short_name = 'CloudFront';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $service_key_name = 'cloudfront';

	/**
	 * Optional override of "Provider Name" + "Service Name" for friendly name for service.
	 *
	 * @var string
	 */
	protected static $provider_service_name = 'Amazon CloudFront';

	/**
	 * The slug for the service's quick start guide doc.
	 *
	 * @var string
	 */
	protected static $provider_service_quick_start_slug = 'cloudfront-setup';

	/**
	 * @var string
	 */
	protected $default_domain = 'cloudfront.net';

	/**
	 * @var string
	 */
	protected $console_url = 'https://console.aws.amazon.com/cloudfront/home';

	/**
	 * @var bool
	 */
	protected static $block_public_access_supported = true;

	/**
	 * @var bool
	 */
	protected static $object_ownership_supported = true;

	/**
	 * AWS_CloudFront constructor.
	 *
	 * @param AS3CF_Plugin_Base $as3cf
	 */
	public function __construct( AS3CF_Plugin_Base $as3cf ) {
		parent::__construct( $as3cf );

		// Autoloader.
		require_once $as3cf->get_plugin_sdks_dir_path() . '/Aws3/aws-autoloader.php';
	}

	/**
	 * @inheritDoc
	 */
	public static function signed_urls_support_desc() {
		global $as3cf;

		return sprintf(
			__( 'Private Media Supported with <a href="%s" target="_blank">upgrade</a>', 'amazon-s3-and-cloudfront' ),
			$as3cf::dbrains_url( '/wp-offload-media/upgrade/', array(
				'utm_campaign' => 'WP+Offload+S3',
			) )
		);
	}

	/**
	 * Description for when Block All Public Access is enabled and Delivery Provider supports it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_enabled_supported_desc() {
		global $as3cf;

		$mesg = __( 'Since you\'re using Amazon CloudFront for delivery we recommend you keep Block All Public Access enabled.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::settings_more_info_link( 'bucket', '', 'change+bucket+access' );

		return $mesg;
	}

	/**
	 * Description for when Block All Public Access is disabled and Delivery Provider supports it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_disabled_supported_desc() {
		global $as3cf;

		$mesg = __( 'Since you\'re using Amazon CloudFront for delivery we recommend you enable Block All Public Access once you have set up the required Origin Access Identity and bucket policy.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::settings_more_info_link( 'bucket', '', 'change+bucket+access' );

		return $mesg;
	}

	/**
	 * Prompt text to confirm that everything is in place to enable Block All Public Access.
	 *
	 * @return string
	 */
	public static function get_block_public_access_confirm_setup_prompt() {
		global $as3cf;

		$bucket_settings_doc = $as3cf::dbrains_url(
			'/wp-offload-media/doc/settings/',
			array( 'utm_campaign' => 'support+docs', 'utm_content' => 'change+bucket+access' ),
			'bucket'
		);

		return sprintf(
			__( 'I have set up the required <a href="%1$s">Origin Access Identity and bucket policy</a>', 'amazon-s3-and-cloudfront' ),
			$bucket_settings_doc
		);
	}

	/**
	 * Description for when Object Ownership is enforced and Delivery Provider supports it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_enforced_supported_desc(): string {
		global $as3cf;

		$mesg = __( 'Since you\'re using Amazon CloudFront for delivery we recommend you keep Object Ownership enforced.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::settings_more_info_link( 'bucket', '', 'change+bucket+access' );

		return $mesg;
	}

	/**
	 * Description for when Object Ownership is not enforced and Delivery Provider supports it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_not_enforced_supported_desc(): string {
		global $as3cf;

		$mesg = __( 'Since you\'re using Amazon CloudFront for delivery we recommend you enforce Object Ownership once you have set up the required Origin Access Identity and bucket policy.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::settings_more_info_link( 'bucket', '', 'change+bucket+access' );

		return $mesg;
	}

	/**
	 * Title to be shown for provider's console link.
	 *
	 * @return string
	 */
	public static function get_console_title(): string {
		return _x( 'CloudFront Distributions', 'Provider console link text', 'amazon-s3-and-cloudfront' );
	}
}
