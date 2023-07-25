<?php

namespace DeliciousBrains\WP_Offload_Media;

use DeliciousBrains\WP_Offload_Media\Settings\Validator_Interface;

trait Settings_Validator_Trait {
	/**
	 * @var array
	 */
	private $validation_issues = array();

	/**
	 * Get the validator priority.
	 *
	 * @return int
	 */
	public function get_validator_priority(): int {
		return isset( $this->validator_priority ) ? (int) $this->validator_priority : 10;
	}

	/**
	 * Has a validation issue been detected?
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	protected function has_validation_issue( string $key ): bool {
		return isset( $this->validation_issues[ $key ] ) && false !== $this->validation_issues[ $key ];
	}

	/**
	 * Return validation issue content.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	protected function get_validation_issue( string $key ): string {
		return $this->has_validation_issue( $key ) ? (string) $this->validation_issues[ $key ] : '';
	}

	/**
	 * Add a validation issue.
	 *
	 * @param string      $key
	 * @param string|bool $value
	 */
	protected function add_validation_issue( string $key, $value ) {
		$this->validation_issues[ $key ] = $value;
	}

	/**
	 * Return true if $result is an error or unknown.
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	protected function is_result_code_unknown_or_error( string $code ): bool {
		$codes = array(
			Validator_Interface::AS3CF_STATUS_MESSAGE_ERROR,
			Validator_Interface::AS3CF_STATUS_MESSAGE_UNKNOWN,
		);

		return in_array( $code, $codes, true );
	}
}
