<?php

namespace DeliciousBrains\WP_Offload_Media\API;

use Amazon_S3_And_CloudFront;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

abstract class API {
	const NAMESPACE_BASE = 'wp-offload-media';

	/**
	 * API Version that class is part of.
	 *
	 * @var int
	 */
	protected static $version = 0;

	/**
	 * The endpoint name.
	 *
	 * This name will be used in the route, and where possible should be plural.
	 *
	 * @var string
	 */
	protected static $name = '';

	/** @var Amazon_S3_And_CloudFront */
	protected $as3cf;

	/**
	 * Initiate the API
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf
	 */
	public function __construct( $as3cf ) {
		$this->as3cf = $as3cf;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Get API version.
	 *
	 * @return int
	 */
	public static function version() {
		return static::$version;
	}

	/**
	 * Get name for endpoint.
	 *
	 * @return string
	 */
	public static function name() {
		return static::$name;
	}

	/**
	 * Get API namespace for endpoint.
	 *
	 * @return string
	 */
	public static function api_namespace() {
		return self::NAMESPACE_BASE . '/v' . static::version();
	}

	/**
	 * Get route to be appended to namespace in endpoint.
	 *
	 * @return string
	 */
	public static function route() {
		return '/' . static::name() . '/';
	}

	/**
	 * Get complete API endpoint path.
	 *
	 * @return string
	 */
	public static function endpoint() {
		return static::api_namespace() . static::route();
	}

	/**
	 * Get common response values for named API endpoint.
	 *
	 * @param string $name
	 *
	 * @return array
	 */
	public function endpoint_common_response( $name ) {
		$endpoint = $this->as3cf->get_api_manager()->get_api_endpoint( $name );

		if ( ! empty( $endpoint ) ) {
			return $endpoint->common_response();
		}

		return array();
	}

	/**
	 * Common response values for this API endpoint.
	 *
	 * @return array
	 */
	public function common_response() {
		return array();
	}

	/**
	 * All API requests must be from administrator or user granted "manage_options" capability.
	 *
	 * This function is used as the "permission_callback" for all routes.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool
	 */
	public function check_permissions( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * A simple wrapper for rest_ensure_response to allow filters to fire before the response is sent.
	 *
	 * Ensures a REST response is a response object (for consistency).
	 *
	 * @param string                          $method   Case-insensitive HTTP method, e.g. "get" or "post".
	 * @param string                          $endpoint Endpoint name, e.g. "settings".
	 * @param WP_Error|WP_HTTP_Response|mixed $response Response to check.
	 * @param string                          $prefix   Optional plugin prefix to be used for filter.
	 *
	 * @return WP_REST_Response|mixed If response generated an error, WP_Error, if response
	 *                                is already an instance, WP_HTTP_Response, otherwise
	 *                                returns a new WP_REST_Response instance.
	 */
	protected function rest_ensure_response( $method, $endpoint, $response, $prefix = '' ) {
		$method   = empty( $method ) ? 'method' : strtolower( trim( $method ) );
		$endpoint = empty( $endpoint ) ? 'endpoint' : strtolower( trim( $endpoint ) );
		$prefix   = empty( $prefix ) ? $this->as3cf->get_plugin_prefix() : strtolower( trim( $prefix ) );

		// A specific filter is fed through a general filter to allow for granular and general filtering of responses.
		return rest_ensure_response(
			apply_filters(
				$prefix . '_api_response',
				apply_filters( $prefix . '_api_response_' . $method . '_' . $endpoint, $response )
			)
		);
	}

	/**
	 * Register REST API routes.
	 */
	abstract public function register_routes();
}
