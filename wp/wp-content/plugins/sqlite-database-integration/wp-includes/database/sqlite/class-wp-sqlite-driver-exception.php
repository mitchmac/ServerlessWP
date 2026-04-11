<?php

class WP_SQLite_Driver_Exception extends PDOException {
	/**
	 * The SQLite driver that originated the exception.
	 *
	 * @var WP_PDO_MySQL_On_SQLite
	 */
	private $driver;

	/**
	 * Constructor.
	 *
	 * @param WP_PDO_MySQL_On_SQLite $driver The SQLite driver that originated the exception.
	 * @param string                 $message  The exception message.
	 * @param int|string             $code     The exception code. In PDO, it can be a string with value of SQLSTATE.
	 * @param Throwable|null         $previous The previous throwable used for the exception chaining.
	 */
	public function __construct(
		WP_PDO_MySQL_On_SQLite $driver,
		string $message,
		$code = 0,
		?Throwable $previous = null
	) {
		parent::__construct( $message, 0, $previous );
		$this->code   = $code;
		$this->driver = $driver;
	}

	public function getDriver(): WP_PDO_MySQL_On_SQLite {
		return $this->driver;
	}
}
