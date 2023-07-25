<?php

namespace DeliciousBrains\WP_Offload_Media\Settings\Exceptions;

use Exception;
use ReflectionClass;

class Domain_Check_Exception extends Exception {

	/**
	 * @var string Relative path for dbrains link
	 */
	protected $more_info = '/wp-offload-media/doc/assets-pull-domain-check-errors/';

	/**
	 * Get the exception name in key form.
	 */
	public function get_key(): string {
		$class = new ReflectionClass( $this );

		return strtolower( $class->getShortName() );
	}

	/**
	 * Get the relative URL to a help document for this exception.
	 *
	 * @return string
	 */
	public function more_info(): string {
		return $this->more_info;
	}
}
