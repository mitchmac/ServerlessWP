<?php declare( strict_types = 1 );

namespace WP_MySQL_Proxy;

use InvalidArgumentException;

/**
 * A simple logger for the MySQL proxy.
 */
class Logger {
	// Log levels.
	const LEVEL_ERROR   = 'error';
	const LEVEL_WARNING = 'warning';
	const LEVEL_INFO    = 'info';
	const LEVEL_DEBUG   = 'debug';

	/**
	 * Log levels in order of severity.
	 *
	 * @var array
	 */
	const LEVELS = array(
		self::LEVEL_ERROR,
		self::LEVEL_WARNING,
		self::LEVEL_INFO,
		self::LEVEL_DEBUG,
	);

	/**
	 * The current log level.
	 *
	 * @var string
	 */
	private $log_level;

	/**
	 * Constructor.
	 *
	 * @param string $log_level The log level to use. Default: Logger::LEVEL_WARNING
	 */
	public function __construct( string $log_level = self::LEVEL_WARNING ) {
		$this->set_log_level( $log_level );
	}

	/**
	 * Get the current log level.
	 *
	 * @return string
	 */
	public function get_log_level(): string {
		return $this->log_level;
	}

	/**
	 * Set the current log level.
	 *
	 * @param string $level The log level to use.
	 */
	public function set_log_level( string $level ): void {
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			throw new InvalidArgumentException( 'Invalid log level: ' . $level );
		}
		$this->log_level = $level;
	}

	/**
	 * Check if a log level is enabled.
	 *
	 * @param string $level The log level to check.
	 * @return bool
	 */
	public function is_log_level_enabled( string $level ): bool {
		$level_index = array_search( $level, self::LEVELS, true );
		if ( false === $level_index ) {
			return false;
		}
		return $level_index <= array_search( $this->log_level, self::LEVELS, true );
	}

	/**
	 * Log a message.
	 *
	 * @param string $level   The log level.
	 * @param string $message The message to log.
	 * @param array  $context The context to log.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		// Check log level.
		if ( ! $this->is_log_level_enabled( $level ) ) {
			return;
		}

		// Handle PSR-3 placeholder syntax.
		$replacements = array();
		foreach ( $context as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = $value;
		}
		$message = str_replace( array_keys( $replacements ), $replacements, $message );

		// Format and log the message.
		fprintf( STDERR, '%s [%s] %s' . PHP_EOL, gmdate( 'Y-m-d H:i:s' ), $level, $message );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context to log.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context to log.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context to log.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context to log.
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}
}
