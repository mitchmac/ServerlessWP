<?php

namespace DeliciousBrains\WP_Offload_Media\Integrations;

class Integration_Manager {

	/**
	 * @var Integration_Manager
	 */
	protected static $instance;

	/**
	 * @var Integration[]
	 */
	private $integrations;

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * class via the `new` operator from outside this class.
	 */
	protected function __construct() {
		$this->integrations = array();

		add_action( 'as3cf_setup', array( $this, 'setup' ) );
	}

	/**
	 * Make this class a singleton.
	 *
	 * Use this instead of __construct().
	 *
	 * @return Integration_Manager
	 */
	public static function get_instance() {
		if ( ! isset( static::$instance ) && ! ( self::$instance instanceof Integration_Manager ) ) {
			static::$instance = new Integration_Manager();
		}

		return static::$instance;
	}

	/**
	 * Getter for integration class instance
	 *
	 * @param string $integration_key
	 *
	 * @return Integration|null
	 */
	public function get_integration( $integration_key ) {
		if ( ! empty( $this->integrations[ $integration_key ] ) ) {
			return $this->integrations[ $integration_key ];
		}

		return null;
	}

	/**
	 * Register integration.
	 *
	 * @param string      $integration_key
	 * @param Integration $integration
	 */
	public function register_integration( $integration_key, Integration $integration ) {
		if ( $integration::is_installed() ) {
			$integration->init();

			$this->integrations[ $integration_key ] = $integration;
		}
	}

	/**
	 * Set up the registered integrations.
	 *
	 * @return void
	 */
	public function setup() {
		foreach ( $this->integrations as $integration ) {
			$integration->setup();
		}
	}

	/**
	 * As this class is a singleton it should not be clone-able.
	 */
	protected function __clone() {
	}

	/**
	 * As this class is a singleton it should not be able to be unserialized.
	 */
	public function __wakeup() {
	}

}
