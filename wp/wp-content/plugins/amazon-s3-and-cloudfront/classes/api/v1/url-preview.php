<?php

namespace DeliciousBrains\WP_Offload_Media\API\V1;

use DeliciousBrains\WP_Offload_Media\API\API;
use WP_REST_Request;
use WP_REST_Response;

class URL_Preview extends API {
	/** @var int */
	protected static $version = 1;

	protected static $name = 'url-preview';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_url_preview' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'post_url_preview' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Processes a REST GET request and returns the current url_preview.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function get_url_preview( WP_REST_Request $request ) {
		return $this->rest_ensure_response( 'get', static::name(), array(
			'url_example' => $this->as3cf->get_url_preview(),
			'url_parts'   => $this->as3cf->get_url_preview( true ),
		) );
	}

	/**
	 * Processes a REST POST request and returns the url_preview for given settings.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function post_url_preview( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		// If no settings provided, don't provide any urls to signify a soft error.
		if ( empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return $this->rest_ensure_response( 'post', static::name(), array() );
		}

		return $this->rest_ensure_response( 'post', static::name(), array(
			'url_example' => $this->as3cf->get_url_preview( false, null, $data['settings'] ),
			'url_parts'   => $this->as3cf->get_url_preview( true, null, $data['settings'] ),
		) );
	}
}
