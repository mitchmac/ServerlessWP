<?php

namespace DeliciousBrains\WP_Offload_Media\API\V1;

use DeliciousBrains\WP_Offload_Media\API\API;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Notifications extends API {
	/** @var int */
	protected static $version = 1;

	/** @var string */
	protected static $name = 'notifications';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_notifications' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			static::api_namespace(),
			static::route(),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_notifications' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Processes a REST GET request and returns the current notifications.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function get_notifications( WP_REST_Request $request ) {
		$data     = $request->get_query_params();
		$tab      = empty( $data['tab'] ) ? '' : $data['tab'];
		$all_tabs = ! empty( $data['all_tabs'] );

		return $this->rest_ensure_response( 'get', static::name(), array(
			'notifications' => $this->as3cf->get_notifications( $tab, $all_tabs ),
		) );
	}

	/**
	 * Processes a REST DELETE request and returns the current notifications.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|mixed
	 */
	public function delete_notifications( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		if ( empty( $data['id'] ) ) {
			return $this->rest_ensure_response( 'delete', static::name(),
				new WP_Error( 'missing-notification-id', __( 'Notification ID not supplied.', 'amazon-s3-and-cloudfront' ) )
			);
		}

		$this->as3cf->dismiss_notification( $data['id'] );

		$tab      = empty( $data['tab'] ) ? '' : $data['tab'];
		$all_tabs = ! empty( $data['all_tabs'] );

		return $this->rest_ensure_response( 'delete', static::name(), array(
			'notifications' => $this->as3cf->get_notifications( $tab, $all_tabs ),
		) );
	}
}
