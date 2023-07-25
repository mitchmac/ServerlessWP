<?php

namespace DeliciousBrains\WP_Offload_Media\Integrations;

use Amazon_S3_And_CloudFront;

abstract class Integration {

	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	protected $as3cf;

	/**
	 * Integration constructor.
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf
	 */
	public function __construct( Amazon_S3_And_CloudFront $as3cf ) {
		$this->as3cf = $as3cf;
	}

	/**
	 * Is installed?
	 *
	 * @return bool
	 */
	public static function is_installed(): bool {
		return false;
	}

	/**
	 * Is this integration enabled?
	 *
	 * While the integration's dependencies may be installed,
	 * it is possible that the integration is disabled for other reasons.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return static::is_installed();
	}

	/**
	 * Init integration.
	 */
	abstract public function init();

	/**
	 * Set up the integration.
	 */
	abstract public function setup();
}
