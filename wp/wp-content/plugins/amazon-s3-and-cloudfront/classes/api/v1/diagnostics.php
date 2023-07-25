<?php

namespace DeliciousBrains\WP_Offload_Media\API\V1;

use DeliciousBrains\WP_Offload_Media\API\API;
use WP_REST_Request;
use WP_REST_Response;

class Diagnostics extends API {
	/** @var int */
	protected static $version = 1;

	protected static $name = 'diagnostics';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_diagnostics' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Processes a REST GET request and returns the current diagnostics.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function get_diagnostics( WP_REST_Request $request ) {
		return $this->rest_ensure_response( 'get', static::name(), array(
			'diagnostics' => $this->as3cf->output_diagnostic_info(),
		) );
	}
}
