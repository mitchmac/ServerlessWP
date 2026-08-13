<?php
/**
 * Interface for Akismet abilities.
 *
 * @package Akismet
 * @since 5.7
 */

declare( strict_types = 1 );

/**
 * Interface Akismet_Ability_Interface
 */
interface Akismet_Ability_Interface {

	/**
	 * Get the ability configuration array.
	 *
	 * Returns the configuration array used to register the ability with wp_register_ability().
	 *
	 * @return array {
	 *     The ability configuration array.
	 *
	 *     @type string   $label               A human-readable name for the ability. Used for display purposes. Should be translatable.
	 *     @type string   $description         A detailed description of what the ability does, its purpose, and its parameters or return values.
	 *                                         This is crucial for AI agents to understand how and when to use the ability.
	 *     @type string   $category            The slug of the category this ability belongs to. The category must be registered before
	 *                                         registering the ability.
	 *     @type array    $output_schema       A JSON Schema definition describing the expected format of the data returned by the ability.
	 *                                         Used for validation and documentation.
	 *     @type callable $execute_callback    The PHP function or method to execute when this ability is called. Receives optional input
	 *                                         argument matching the input schema type.
	 *     @type callable $permission_callback A callback function to check if the current user has permission to execute this ability.
	 *                                         Returns boolean or WP_Error.
	 *     @type array    $input_schema        Optional. JSON Schema defining expected input parameters. Required when the ability accepts inputs.
	 *     @type array    $meta                Optional. An associative array for storing arbitrary additional metadata about the ability,
	 *                                         including 'annotations' (readonly, destructive, idempotent flags) and 'show_in_rest'.
	 *     @type string   $ability_class       Optional. Custom class name extending WP_Ability for behavior customization.
	 * }
	 */
	public function get_config(): array;

	/**
	 * Execute callback for the ability.
	 *
	 * Runs the main functionality of the ability.
	 *
	 * @param array|null $input The input parameters for the ability. Null when no input provided.
	 * @return array|WP_Error The result of the execution or a WP_Error on failure.
	 */
	public function execute( ?array $input = null );

	/**
	 * Permission callback for the ability.
	 *
	 * Checks if the current user has permission to execute the ability.
	 *
	 * @param array|null $input The input parameters for the ability. Null when no input provided.
	 * @return bool Whether the current user has permission.
	 */
	public function current_user_has_permission( ?array $input = null ): bool;
}
