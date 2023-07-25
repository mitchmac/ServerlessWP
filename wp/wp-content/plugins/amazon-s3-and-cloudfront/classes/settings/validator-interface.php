<?php

namespace DeliciousBrains\WP_Offload_Media\Settings;

use WP_Error as AS3CF_Result;

interface Validator_Interface {
	const AS3CF_STATUS_MESSAGE_ERROR   = 'error';
	const AS3CF_STATUS_MESSAGE_WARNING = 'warning';
	const AS3CF_STATUS_MESSAGE_INFO    = 'info';
	const AS3CF_STATUS_MESSAGE_SUCCESS = 'success';
	const AS3CF_STATUS_MESSAGE_UNKNOWN = 'unknown';
	const AS3CF_STATUS_MESSAGE_SKIPPED = 'skipped';

	/**
	 * Validate settings. Set the force flag to true to allow the validators to run
	 * checks that are time-consuming or affects the global state of the plugin.
	 *
	 * @param bool $force A potentially time resource consuming tests to run.
	 *
	 * @return AS3CF_Result
	 */
	public function validate_settings( bool $force = false ): AS3CF_Result;

	/**
	 * Get the name of the actions that are fired when the settings that the validator
	 * is responsible for are saved.
	 *
	 * @return array
	 */
	public function post_save_settings_actions(): array;

	/**
	 * Get the validator priority.
	 *
	 * @return int
	 */
	public function get_validator_priority(): int;
}
