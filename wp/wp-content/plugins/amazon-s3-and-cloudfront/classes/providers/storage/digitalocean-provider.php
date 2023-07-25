<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Storage;

use Exception;

class DigitalOcean_Provider extends AWS_Provider {

	/**
	 * @var string
	 */
	protected static $provider_name = 'DigitalOcean';

	/**
	 * @var string
	 */
	protected static $provider_short_name = 'DigitalOcean';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = 'do';

	/**
	 * @var string
	 */
	protected static $service_name = 'Spaces';

	/**
	 * @var string
	 */
	protected static $service_short_name = 'Spaces';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $service_key_name = 'spaces';

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
	protected static $provider_service_quick_start_slug = 'digitalocean-spaces-quick-start-guide';

	/**
	 * @var array
	 */
	protected static $access_key_id_constants = array(
		'AS3CF_DO_ACCESS_KEY_ID',
	);

	/**
	 * @var array
	 */
	protected static $secret_access_key_constants = array(
		'AS3CF_DO_SECRET_ACCESS_KEY',
	);

	/**
	 * @var array
	 */
	protected static $use_server_roles_constants = array();

	/**
	 * @var bool
	 */
	protected static $block_public_access_supported = false;

	/**
	 * @var bool
	 */
	protected static $object_ownership_supported = false;

	/**
	 * @var array
	 */
	protected static $regions = array(
		'nyc3' => 'New York',
		'ams3' => 'Amsterdam',
		'sgp1' => 'Singapore',
		'sfo2' => 'San Francisco 2',
		'sfo3' => 'San Francisco 3',
		'fra1' => 'Frankfurt',
		'syd1' => 'Sydney',
	);

	/**
	 * @var bool
	 */
	protected static $region_required = true;

	/**
	 * @var string
	 */
	protected static $default_region = 'nyc3';

	/**
	 * @var string
	 */
	protected $default_domain = 'digitaloceanspaces.com';

	/**
	 * @var string
	 */
	protected $console_url = 'https://cloud.digitalocean.com/spaces/';

	/**
	 * @var string
	 */
	protected $console_url_prefix_param = '?path=';

	/**
	 * @var array
	 */
	private $client_args = array();

	/**
	 * Process the args before instantiating a new client for the provider's SDK.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	protected function init_client_args( array $args ) {
		if ( empty( $args['endpoint'] ) ) {
			// DigitalOcean endpoints always require a region.
			$args['region'] = empty( $args['region'] ) ? static::get_default_region() : $args['region'];

			$args['endpoint'] = 'https://' . $args['region'] . '.' . $this->get_domain();
		}

		$this->client_args = $args;

		return $this->client_args;
	}

	/**
	 * Process the args before instantiating a new service specific client.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	protected function init_service_client_args( array $args ) {
		return $args;
	}

	/**
	 * Update the block public access setting for the given bucket.
	 *
	 * @param string $bucket
	 * @param bool   $block
	 */
	public function block_public_access( string $bucket, bool $block ) {
		// DigitalOcean doesn't support this, so do nothing.
	}

	/**
	 * Update the object ownership enforced setting for the given bucket.
	 *
	 * @param string $bucket
	 * @param bool   $enforce
	 */
	public function enforce_object_ownership( string $bucket, bool $enforce ) {
		// DigitalOcean doesn't support this, so do nothing.
	}

	/**
	 * Create bucket.
	 *
	 * @param array $args
	 *
	 * @throws Exception
	 */
	public function create_bucket( array $args ) {
		// DigitalOcean requests always require a region, and it must be an AWS S3 one.
		// The region in the endpoint is all that matters to DigitalOcean Spaces.
		// @see https://docs.digitalocean.com/products/spaces/reference/s3-sdk-examples/#configure-a-client
		if ( ! empty( $this->client_args['region'] ) && 'us-east-1' === $this->client_args['region'] ) {
			parent::create_bucket( $args );
		} else {
			$client_args           = $this->client_args;
			$client_args['region'] = 'us-east-1';
			unset( $args['LocationConstraint'] ); // Not needed and breaks signature.
			$this->get_client( $client_args, true )->create_bucket( $args );
		}
	}

	/**
	 * Returns region for bucket.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function get_bucket_location( array $args ) {
		// For some reason DigitalOcean Spaces returns an XML LocationConstraint segment prepended to the region key.
		return strip_tags( parent::get_bucket_location( $args ) );
	}

	/**
	 * Get the region specific prefix for raw URL
	 *
	 * @param string   $region
	 * @param null|int $expires
	 *
	 * @return string
	 */
	protected function url_prefix( $region = '', $expires = null ) {
		return $region;
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
	 * Title to be shown for provider's console link.
	 *
	 * @return string
	 */
	public static function get_console_title(): string {
		return _x( 'Control Panel', 'Provider console link text', 'amazon-s3-and-cloudfront' );
	}
}
