<?php
/**
 * Get Stats ability for Akismet.
 *
 * @package Akismet
 * @since 5.7
 */

declare( strict_types = 1 );

/**
 * Class Akismet_Ability_Get_Stats
 *
 * Registers and handles the ability to retrieve Akismet statistics.
 */
class Akismet_Ability_Get_Stats extends Akismet_Ability implements Akismet_Ability_Interface {

	/**
	 * Get the ability name.
	 *
	 * @return string The ability name.
	 */
	protected function get_ability_name(): string {
		return 'akismet/get-stats';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string The label.
	 */
	protected function get_label(): string {
		return __( 'Get Akismet statistics', 'akismet' );
	}

	/**
	 * Get the ability description.
	 *
	 * @return string The description.
	 */
	protected function get_description(): string {
		return __( 'Retrieves Akismet spam protection statistics including spam blocked count, accuracy percentage, and other key metrics.', 'akismet' );
	}

	/**
	 * Get the input schema.
	 *
	 * @return array The input schema.
	 */
	protected function get_input_schema(): array {
		return array(
			'type'                 => array( 'object', 'null' ),
			'properties'           => array(
				'interval' => array(
					'type'        => 'string',
					'description' => __( 'The time interval for stats. Options: "6-months", "all", or "60-days".', 'akismet' ),
					'enum'        => array( '6-months', 'all', '60-days' ),
					'default'     => '6-months',
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Get the output schema.
	 *
	 * @return array The output schema.
	 */
	protected function get_output_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'success'         => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the stats were successfully retrieved.', 'akismet' ),
				),
				'spam'            => array(
					'type'        => 'integer',
					'description' => __( 'Total number of spam comments blocked.', 'akismet' ),
				),
				'ham'             => array(
					'type'        => 'integer',
					'description' => __( 'Total number of legitimate comments approved.', 'akismet' ),
				),
				'missed_spam'     => array(
					'type'        => 'integer',
					'description' => __( 'Number of spam comments that were missed.', 'akismet' ),
				),
				'false_positives' => array(
					'type'        => 'integer',
					'description' => __( 'Number of legitimate comments incorrectly marked as spam.', 'akismet' ),
				),
				'accuracy'        => array(
					'type'        => 'number',
					'description' => __( 'Accuracy percentage of spam detection.', 'akismet' ),
				),
				'time_saved'      => array(
					'type'        => 'integer',
					'description' => __( 'Estimated time saved by Akismet blocking spam, in seconds.', 'akismet' ),
				),
				'breakdown'       => array(
					'type'                 => 'object',
					'description'          => __( 'Monthly breakdown of statistics.', 'akismet' ),
					'additionalProperties' => array(
						'type'       => 'object',
						'properties' => array(
							'spam'            => array(
								'type'        => 'integer',
								'description' => __( 'Total number of spam comments blocked in this period.', 'akismet' ),
							),
							'ham'             => array(
								'type'        => 'integer',
								'description' => __( 'Total number of legitimate comments approved in this period.', 'akismet' ),
							),
							'missed_spam'     => array(
								'type'        => 'integer',
								'description' => __( 'Number of spam comments that were missed in this period.', 'akismet' ),
							),
							'false_positives' => array(
								'type'        => 'integer',
								'description' => __( 'Number of legitimate comments incorrectly marked as spam in this period.', 'akismet' ),
							),
							'da'              => array(
								'type'        => 'string',
								'description' => __( 'Date for this period.', 'akismet' ),
							),
						),
					),
				),
				'interval'        => array(
					'type'        => 'string',
					'description' => __( 'The time interval for these stats.', 'akismet' ),
				),
				'error'           => array(
					'type'        => 'string',
					'description' => __( 'Error message if the operation could not be completed.', 'akismet' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Get the ability configuration.
	 *
	 * @return array The ability configuration.
	 */
	public function get_config(): array {
		return array(
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'category'            => Akismet_Abilities::CATEGORY_SLUG,
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'current_user_has_permission' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'mcp'          => array(
					'public' => ( get_option( 'akismet_enable_mcp_access' ) === '1' ),
					'type'   => 'tool',
				),
				'show_in_rest' => true,
			),
		);
	}

	/**
	 * Execute callback for the get-stats ability.
	 *
	 * @param array|null $input The input parameters with optional interval.
	 * @return array|WP_Error The stats data or error.
	 */
	public function execute( ?array $input = null ) {
		// Get interval from input or use default.
		$interval = isset( $input['interval'] ) ? $input['interval'] : '6-months';

		// Fetch stats from Akismet API.
		$data = Akismet::get_stats( $interval );

		if ( ! $data ) {
			return new WP_Error(
				'stats_fetch_failed',
				__( 'Failed to retrieve stats from Akismet API.', 'akismet' )
			);
		}

		// Build response with data from API (already properly typed by get_stats).
		return array_merge(
			array(
				'success'  => true,
				'interval' => $interval,
			),
			(array) $data
		);
	}
}
