<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use Amazon_S3_And_CloudFront;

abstract class Network_Upgrade {

	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	protected $as3cf;

	/**
	 * @var string
	 */
	protected $version;

	/**
	 * Network_Upgrade constructor.
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf
	 * @param string                   $version
	 */
	public function __construct( $as3cf, $version ) {
		$this->as3cf   = $as3cf;
		$this->version = $version;

		add_action( 'admin_init', array( $this, 'init' ) );
	}

	/**
	 * Init upgrade.
	 */
	public function init() {
		if ( ! $this->maybe_upgrade() ) {
			return;
		}

		$this->do_upgrade();
		$this->save_version();
	}

	/**
	 * Maybe perform upgrade?
	 *
	 * @return bool
	 */
	protected function maybe_upgrade() {
		$stored_version = $this->get_stored_version();

		if ( version_compare( $stored_version, $this->version, '<' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get stored version.
	 *
	 * @return string
	 */
	protected function get_stored_version() {
		static $version;

		if ( is_null( $version ) ) {
			$version = get_site_option( $this->get_option_name(), '0.0' );
		}

		return $version;
	}

	/**
	 * Get option name.
	 *
	 * @return string
	 */
	protected function get_option_name() {
		return strtolower( get_class( $this->as3cf ) ) . '_version';
	}

	/**
	 * Save version to options table.
	 */
	protected function save_version() {
		update_site_option( $this->get_option_name(), $this->version );
	}

	/**
	 * Perform upgrade logic.
	 */
	abstract protected function do_upgrade();

}