<?php

namespace DeliciousBrains\WP_Offload_Media\API;

class API_Manager {

	/**
	 * @var API_Manager
	 */
	protected static $instance;

	/**
	 * @var array
	 */
	private $api_endpoints;

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * class via the `new` operator from outside this class.
	 */
	protected function __construct() {
		$this->api_endpoints = array();
	}

	/**
	 * Make this class a singleton.
	 *
	 * Use this instead of __construct().
	 *
	 * @return API_Manager
	 */
	public static function get_instance(): API_Manager {
		if ( ! isset( static::$instance ) && ! ( self::$instance instanceof API_Manager ) ) {
			static::$instance = new API_Manager();
		}

		return static::$instance;
	}

	/**
	 * Get a named API Endpoint handler instance.
	 *
	 * @param string $name
	 *
	 * @return API|null
	 */
	public function get_api_endpoint( string $name ): ?API {
		if ( ! empty( $name ) && array_key_exists( $name, $this->api_endpoints ) ) {
			return $this->api_endpoints[ $name ];
		}

		return null;
	}

	/**
	 * Returns a list of APi Endpoint paths keyed by their name.
	 *
	 * @return array
	 */
	public function api_endpoints(): array {
		return array_map( function ( API $api_endpoint ) {
			return $api_endpoint->endpoint();
		}, $this->api_endpoints );
	}

	/**
	 * Register API Endpoint handler instance.
	 *
	 * @param string $name
	 * @param API    $api
	 */
	public function register_api_endpoint( string $name, API $api ) {
		$this->api_endpoints[ $name ] = $api;
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
