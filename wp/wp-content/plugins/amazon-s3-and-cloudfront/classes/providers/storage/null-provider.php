<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Storage;

use AS3CF_Error;
use Exception;

class Null_Provider {

	/**
	 * Log and fail calls to instance methods.
	 *
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @throws Exception
	 */
	public function __call( $name, $arguments ) {
		AS3CF_Error::log( $arguments, __CLASS__ . "->$name()" );
		throw new Exception( 'Failed to instantiate the provider client. Check your error log. Function called:- ' . __CLASS__ . "->$name()" );
	}

	/**
	 * Log and fail calls to static methods.
	 *
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @throws Exception
	 */
	public static function __callStatic( $name, $arguments ) {
		AS3CF_Error::log( $arguments, __CLASS__ . "::$name()" );
		throw new Exception( 'Failed to instantiate the provider client. Check your error log. Function called:- ' . __CLASS__ . "->$name()" );
	}
}
