<?php
/**
 * Represents a Base Ability.
 *
 * This class holds a default constructor to register the ability and a default permission.
 *
 * @package Akismet
 * @since 5.7
 */

declare( strict_types = 1 );

/**
 * Base class for Akismet abilities.
 *
 * @package Akismet
 * @since 5.7
 */
abstract class Akismet_Ability implements Akismet_Ability_Interface {

	/**
	 * Get the ability name.
	 *
	 * Classes extending this must implement this method to provide the ability name into the registration.
	 *
	 * @return string The ability name.
	 */
	abstract protected function get_ability_name(): string;

	/**
	 * Get the config.
	 *
	 * Classes extending this must implement this method to provide the ability configuration into the registration.
	 *
	 * @return array The ability configuration array.
	 */
	abstract public function get_config(): array;

	/**
	 * Constructor - registers the ability.
	 */
	public function __construct() {
		wp_register_ability(
			$this->get_ability_name(),
			$this->get_config()
		);
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Base class default, subclasses use $input.
	/**
	 * Permission callback for any ability that uses this trait.
	 *
	 * @param array|null $input The input parameters (unused).
	 * @return bool Whether the current user can use this ability.
	 */
	public function current_user_has_permission( ?array $input = null ): bool {
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return current_user_can( 'moderate_comments' );
	}
}
