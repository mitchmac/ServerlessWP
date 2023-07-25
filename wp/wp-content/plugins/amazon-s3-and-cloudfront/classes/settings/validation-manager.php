<?php

namespace DeliciousBrains\WP_Offload_Media\Settings;

use Amazon_S3_And_CloudFront;
use WP_Error;

class Validation_Manager {
	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	protected $as3cf;

	/**
	 * @var array
	 */
	protected $validation_result = array();

	/**
	 * The threshold in seconds for the relative time to be considered "just now".
	 *
	 * @var int
	 */
	protected static $threshold_just_now = 60;

	/**
	 * @var Validator_Interface[]
	 */
	private $settings_validators = array();

	/**
	 * @var string
	 */
	private $base_last_validation_result_key = 'as3cf_last_settings_validation_result';

	/**
	 * @param Amazon_S3_And_CloudFront $as3cf
	 */
	public function __construct( Amazon_S3_And_CloudFront $as3cf ) {
		$this->as3cf = $as3cf;
	}

	/**
	 * Register a validator class for a section key.
	 *
	 * @param string              $section
	 * @param Validator_Interface $settings_validator
	 */
	public function register_validator( string $section, Validator_Interface $settings_validator ) {
		// Only one instance can be responsible for a section, so we just overwrite if called twice.
		$this->settings_validators[ $section ] = $settings_validator;

		// The validator may know about one or more actions that are fired when its settings are saved.
		foreach ( $settings_validator->post_save_settings_actions() as $action ) {
			add_action( $action, array( $this, 'action_post_save_settings' ) );
		}
	}

	/**
	 * Run all registered validator classes and return the result in an array. If the
	 * force flag is set to true, the validators may run checks that are time-consuming
	 * or affects the global plugin state (notices).
	 *
	 * @param bool $force
	 *
	 * @return array
	 */
	public function validate( bool $force = false ): array {
		// Sort the validators by priority.
		uasort(
			$this->settings_validators,
			function ( Validator_Interface $a, Validator_Interface $b ) {
				return $a->get_validator_priority() <=> $b->get_validator_priority();
			}
		);

		$validation_start = time();

		// Run all registered settings validators.
		foreach ( $this->settings_validators as $section => $settings_validator ) {
			$transient_key                       = $this->last_validation_result_key( $section );
			$this->validation_result[ $section ] = get_site_transient( $transient_key );

			if ( ! $force && is_array( $this->validation_result[ $section ] ) ) {
				continue;
			}

			// Ensure only one instance of a validator is processing at once unless forced.
			$this->validation_result[ $section ] = array(
				'type'      => Validator_Interface::AS3CF_STATUS_MESSAGE_INFO,
				'message'   => array( _x( 'Processing…', 'Validation in progress', 'amazon-s3-and-cloudfront' ) ),
				'timestamp' => $validation_start,
			);
			set_site_transient( $transient_key, $this->validation_result[ $section ], MINUTE_IN_SECONDS );

			$result = $settings_validator->validate_settings( $force );

			$this->validation_result[ $section ]['type']    = $result->get_error_code();
			$this->validation_result[ $section ]['message'] = array( $result->get_error_message() );

			$timeout = Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR === $result->get_error_code() ? 5 * MINUTE_IN_SECONDS : DAY_IN_SECONDS;

			/**
			 * Adjust the transient timeout for the cache of a setting section's validation results.
			 *
			 * Min: 3 seconds
			 * Max: 7 days
			 *
			 * The default is 1 day unless the result is an error, in which case it is then 5 minutes.
			 *
			 * @param int      $timeout Time until transient expires in seconds.
			 * @param string   $section The setting section, e.g. storage, delivery or assets.
			 * @param WP_Error $result  The result to be cached.
			 */
			$timeout = min( max( 3, (int) apply_filters( $this->base_last_validation_result_key . '_timeout', $timeout, $section, $result ) ), 7 * DAY_IN_SECONDS );

			set_site_transient( $transient_key, $this->validation_result[ $section ], $timeout );
		}

		$this->update_relative_time();

		return $this->validation_result;
	}

	/**
	 * Get a specific or all registered sections and status messages.
	 *
	 * If all sections to be returned, they're in an associative array
	 * with the sections as the keys.
	 *
	 * @param string $section Optional specific section's result only.
	 *
	 * @return array|WP_Error
	 */
	public function get_validation_result( string $section = '' ) {
		if ( empty( $this->validation_result ) ) {
			$this->validate();
		}

		if ( empty( $section ) ) {
			return $this->validation_result;
		} elseif ( ! empty( $this->validation_result[ $section ] ) ) {
			return $this->validation_result[ $section ];
		}

		return array();
	}

	/**
	 * Return the validation status for a section.
	 *
	 * @param string $section
	 *
	 * @return string
	 */
	public function get_validation_status( string $section ): string {
		$result = $this->get_validation_result( $section );

		if ( ! isset( $result['type'] ) ) {
			return Validator_Interface::AS3CF_STATUS_MESSAGE_UNKNOWN;
		}

		return $result['type'];
	}

	/**
	 * Clear the last validation timestamp to force validation on next check.
	 *
	 * @param bool $saved Whether settings were successfully saved or not
	 */
	public function action_post_save_settings( bool $saved ) {
		if ( empty( $this->settings_validators ) ) {
			return;
		}

		static $deleted = array();

		foreach ( $this->settings_validators as $section => $validator ) {
			if ( in_array( $section, $deleted ) || ! in_array( current_action(), $validator->post_save_settings_actions() ) ) {
				continue;
			}

			delete_site_transient( $this->last_validation_result_key( $section ) );
			$deleted[] = $section;
		}
	}

	/**
	 * Does the given section currently have a validation error?
	 *
	 * @param string $section
	 *
	 * @return bool
	 */
	public function section_has_error( string $section ): bool {
		if ( empty( $section ) ) {
			return false;
		}

		return Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR === $this->get_validation_status( $section );
	}

	/**
	 * Get a correctly formatted validation result transient key.
	 *
	 * @param string $section
	 *
	 * @return string
	 */
	public function last_validation_result_key( string $section ): string {
		return $this->base_last_validation_result_key . '_' . $section;
	}

	/**
	 * Update the relative time for each section's last validation.
	 */
	protected function update_relative_time() {
		foreach ( array_keys( $this->validation_result ) as $key ) {
			if ( ! isset( $this->validation_result[ $key ]['timestamp'] ) ) {
				$this->validation_result[ $key ]['last_update'] = _x( 'Unknown', 'Relative time in settings notice', 'amazon-s3-and-cloudfront' );
				continue;
			}

			$this->validation_result[ $key ]['last_update'] = $this->get_relative_time( $this->validation_result[ $key ]['timestamp'] );
		}
	}

	/**
	 * Get the relative time display string.
	 *
	 * @param int $timestamp
	 *
	 * @return string
	 */
	protected function get_relative_time( int $timestamp ): string {
		if ( time() - $timestamp <= static::$threshold_just_now ) {
			return _x( 'Just now', 'Relative time in settings notice', 'amazon-s3-and-cloudfront' );
		}

		return sprintf( __( '%s ago', 'amazon-s3-and-cloudfront' ), human_time_diff( $timestamp ) );
	}
}
