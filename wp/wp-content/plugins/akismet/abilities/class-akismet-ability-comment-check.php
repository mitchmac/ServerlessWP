<?php
/**
 * Comment Check ability for Akismet.
 *
 * @package Akismet
 * @since 5.7
 */

declare( strict_types = 1 );

/**
 * Class Akismet_Ability_Comment_Check
 *
 * Registers and handles the ability to check comments for spam.
 */
class Akismet_Ability_Comment_Check extends Akismet_Ability implements Akismet_Ability_Interface {

	/**
	 * Get the ability name.
	 *
	 * @return string The ability name.
	 */
	protected function get_ability_name(): string {
		return 'akismet/comment-check';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string The label.
	 */
	protected function get_label(): string {
		return __( 'Check comment for spam', 'akismet' );
	}

	/**
	 * Get the ability description.
	 *
	 * @return string The description.
	 */
	protected function get_description(): string {
		return __( 'Checks a comment against the Akismet spam filter to determine if it is spam or legitimate content.', 'akismet' );
	}

	/**
	 * Get the input schema.
	 *
	 * @return array The input schema.
	 */
	protected function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'comment_author'       => array(
					'type'        => 'string',
					'description' => __( 'Name of the comment author.', 'akismet' ),
				),
				'comment_author_email' => array(
					'type'        => 'string',
					'description' => __( 'Email address of the comment author.', 'akismet' ),
					'format'      => 'email',
				),
				'comment_author_url'   => array(
					'type'        => 'string',
					'description' => __( 'URL/website of the comment author.', 'akismet' ),
					'format'      => 'uri',
				),
				'comment_content'      => array(
					'type'        => 'string',
					'description' => __( 'The comment content/text.', 'akismet' ),
				),
				'comment_type'         => array(
					'type'        => 'string',
					'description' => __( 'The comment type (e.g., "comment", "trackback", "pingback").', 'akismet' ),
					'default'     => 'comment',
				),
				'comment_post_ID'      => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the post the comment is being submitted to.', 'akismet' ),
				),
				'permalink'            => array(
					'type'        => 'string',
					'description' => __( 'The permanent link to the post or page.', 'akismet' ),
					'format'      => 'uri',
				),
				'user_ip'              => array(
					'type'        => 'string',
					'description' => __( 'IP address of the commenter.', 'akismet' ),
				),
				'user_agent'           => array(
					'type'        => 'string',
					'description' => __( 'User agent string of the web browser submitting the comment.', 'akismet' ),
				),
				'referrer'             => array(
					'type'        => 'string',
					'description' => __( 'The HTTP_REFERER header.', 'akismet' ),
				),
				'user_role'            => array(
					'type'        => 'string',
					'description' => __( 'The user role of the comment author if logged in.', 'akismet' ),
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
				'success'    => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the check was successfully performed.', 'akismet' ),
				),
				'is_spam'    => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the comment is identified as spam.', 'akismet' ),
				),
				'pro_tip'    => array(
					'type'        => 'string',
					'description' => __( 'Optional recommendation from Akismet (e.g., "discard" for obvious spam).', 'akismet' ),
				),
				'guid'       => array(
					'type'        => 'string',
					'description' => __( 'Unique identifier for this check, used for webhooks and updates.', 'akismet' ),
				),
				'error'      => array(
					'type'        => 'string',
					'description' => __( 'Error message if the check could not be completed.', 'akismet' ),
				),
				'debug_help' => array(
					'type'        => 'string',
					'description' => __( 'Debug information to help troubleshoot issues.', 'akismet' ),
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
					'idempotent'  => false,
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
	 * Execute callback for the comment-check ability.
	 *
	 * @param array|null $input The comment data to check.
	 * @return array|WP_Error The spam check result or error.
	 */
	public function execute( ?array $input = null ) {
		// Check for required API key.
		if ( ! Akismet::get_api_key() ) {
			return new WP_Error(
				'akismet_not_configured',
				__( 'Akismet is not configured. Please enter an API key.', 'akismet' )
			);
		}

		// Perform the comment check.
		$result = Akismet::comment_check( $input );

		if ( ! $result ) {
			return new WP_Error(
				'comment_check_failed',
				__( 'Failed to check comment with Akismet API.', 'akismet' )
			);
		}

		// Build response array.
		$response = array(
			'success' => true,
			'is_spam' => $result->is_spam,
		);

		// Include optional fields if present.
		if ( isset( $result->pro_tip ) ) {
			$response['pro_tip'] = $result->pro_tip;
		}

		if ( isset( $result->guid ) ) {
			$response['guid'] = $result->guid;
		}

		if ( isset( $result->error ) ) {
			$response['error'] = $result->error;
		}

		if ( isset( $result->debug_help ) ) {
			$response['debug_help'] = $result->debug_help;
		}

		return $response;
	}
}
