<?php
/**
 * Extend and replace the wpdb class.
 *
 * @package wp-sqlite-integration
 * @since 1.0.0
 */

/**
 * This class extends wpdb and replaces it.
 *
 * It also rewrites some methods that use mysql specific functions.
 */
class WP_SQLite_DB extends wpdb {

	/**
	 * Database Handle
	 *
	 * @var WP_SQLite_Translator
	 */
	protected $dbh;

	/**
	 * Backward compatibility, see wpdb::$allow_unsafe_unquoted_parameters.
	 *
	 * This property is mirroring "wpdb::$allow_unsafe_unquoted_parameters",
	 * because some tests are accessing it externally using PHP reflection.
	 *
	 * @var
	 */
	private $allow_unsafe_unquoted_parameters = true;

	/**
	 * Connects to the SQLite database.
	 *
	 * Unlike for MySQL, no credentials and host are needed.
	 *
	 * @param string $dbname Database name.
	 */
	public function __construct( $dbname ) {
		/**
		 * We need to initialize the "$wpdb" global early, so that the SQLite
		 * driver can configure the database. The call stack goes like this:
		 *
		 *   1. The "parent::__construct()" call executes "$this->db_connect()".
		 *   2. The database connection call initializes the SQLite driver.
		 *   3. The SQLite driver initializes and runs "WP_SQLite_Configurator".
		 *   4. The configurator uses "WP_SQLite_Information_Schema_Reconstructor",
		 *      which requires "wp-admin/includes/schema.php" when in WordPress.
		 *   5. The "wp-admin/includes/schema.php" requires the "$wpdb" global,
		 *      which creates a circular dependency.
		 */
		$GLOBALS['wpdb'] = $this;

		parent::__construct( '', '', $dbname, '' );
		$this->charset = 'utf8mb4';
	}

	/**
	 * Method to set character set for the database.
	 *
	 * This overrides wpdb::set_charset(), only to dummy out the MySQL function.
	 *
	 * @see wpdb::set_charset()
	 *
	 * @param resource $dbh The resource given by mysql_connect.
	 * @param string   $charset Optional. The character set. Default null.
	 * @param string   $collate Optional. The collation. Default null.
	 */
	public function set_charset( $dbh, $charset = null, $collate = null ) {
	}

	/**
	 * Method to get the character set for the database.
	 * Hardcoded to utf8mb4 for now.
	 *
	 * @param string $table  The table name.
	 * @param string $column The column name.
	 *
	 * @return string The character set.
	 */
	public function get_col_charset( $table, $column ) {
		// Hardcoded for now.
		return 'utf8mb4';
	}

	/**
	 * Changes the current SQL mode, and ensures its WordPress compatibility.
	 *
	 * If no modes are passed, it will ensure the current MySQL server modes are compatible.
	 *
	 * This overrides wpdb::set_sql_mode() while closely mirroring its implementation.
	 *
	 * @param array $modes Optional. A list of SQL modes to set. Default empty array.
	 */
	public function set_sql_mode( $modes = array() ) {
		if ( ! $this->dbh instanceof WP_SQLite_Driver ) {
			return;
		}

		if ( empty( $modes ) ) {
			$result = $this->dbh->query( 'SELECT @@SESSION.sql_mode' );
			if ( ! isset( $result[0] ) ) {
				return;
			}

			$modes_str = $result[0]->{'@@SESSION.sql_mode'};
			if ( empty( $modes_str ) ) {
				return;
			}
			$modes = explode( ',', $modes_str );
		}

		$modes = array_change_key_case( $modes, CASE_UPPER );

		/**
		 * Filters the list of incompatible SQL modes to exclude.
		 *
		 * @since 3.9.0
		 *
		 * @param array $incompatible_modes An array of incompatible modes.
		 */
		$incompatible_modes = (array) apply_filters( 'incompatible_sql_modes', $this->incompatible_modes );

		foreach ( $modes as $i => $mode ) {
			if ( in_array( $mode, $incompatible_modes, true ) ) {
				unset( $modes[ $i ] );
			}
		}
		$modes_str = implode( ',', $modes );

		$this->dbh->query( "SET SESSION sql_mode='$modes_str'" );
	}

	/**
	 * Closes the current database connection.
	 * Noop in SQLite.
	 *
	 * @return bool True to indicate the connection was successfully closed.
	 */
	public function close() {
		return true;
	}

	/**
	 * Method to select the database connection.
	 *
	 * This overrides wpdb::select(), only to dummy out the MySQL function.
	 *
	 * @see wpdb::select()
	 *
	 * @param string        $db  MySQL database name. Not used.
	 * @param resource|null $dbh Optional link identifier.
	 */
	public function select( $db, $dbh = null ) {
		$this->ready = true;
	}

	/**
	 * Method to escape characters.
	 *
	 * This overrides wpdb::_real_escape() to avoid using mysql_real_escape_string().
	 *
	 * @see wpdb::_real_escape()
	 *
	 * @param string $data The string to escape.
	 *
	 * @return string escaped
	 */
	public function _real_escape( $data ) {
		if ( ! is_scalar( $data ) ) {
			return '';
		}
		$escaped = addslashes( $data );
		return $this->add_placeholder_escape( $escaped );
	}

	/**
	 * Method to dummy out wpdb::esc_like() function.
	 *
	 * WordPress 4.0.0 introduced esc_like() function that adds backslashes to %,
	 * underscore and backslash, which is not interpreted as escape character
	 * by SQLite. So we override it and dummy out this function.
	 *
	 * @param string $text The raw text to be escaped. The input typed by the user should have no
	 *                     extra or deleted slashes.
	 *
	 * @return string Text in the form of a LIKE phrase. The output is not SQL safe. Call $wpdb::prepare()
	 *                or real_escape next.
	 */
	public function esc_like( $text ) {
		// The new driver adds "ESCAPE '\\'" to every LIKE expression by default.
		// We only need to overload this function to a no-op for the old driver.
		if ( $this->dbh instanceof WP_SQLite_Driver ) {
			return parent::esc_like( $text );
		}
		return $text;
	}

	/**
	 * Prints SQL/DB error.
	 *
	 * This overrides wpdb::print_error() while closely mirroring its implementation.
	 *
	 * @global array $EZSQL_ERROR Stores error information of query and error string.
	 *
	 * @param string $str The error to display.
	 * @return void|false Void if the showing of errors is enabled, false if disabled.
	 */
	public function print_error( $str = '' ) {
		global $EZSQL_ERROR;

		if ( ! $str ) {
			$str = $this->last_error;
		}

		$EZSQL_ERROR[] = array(
			'query'     => $this->last_query,
			'error_str' => $str,
		);

		if ( $this->suppress_errors ) {
			return false;
		}

		$caller = $this->get_caller();
		if ( $caller ) {
			// Not translated, as this will only appear in the error log.
			$error_str = sprintf( 'WordPress database error %1$s for query %2$s made by %3$s', $str, $this->last_query, $caller );
		} else {
			$error_str = sprintf( 'WordPress database error %1$s for query %2$s', $str, $this->last_query );
		}

		error_log( $error_str );

		// Are we showing errors?
		if ( ! $this->show_errors ) {
			return false;
		}

		wp_load_translations_early();

		// If there is an error then take note of it.
		if ( is_multisite() ) {
			$msg = sprintf(
				"%s [%s]\n%s\n",
				__( 'WordPress database error:' ),
				$str,
				$this->last_query
			);

			if ( defined( 'ERRORLOGFILE' ) ) {
				error_log( $msg, 3, ERRORLOGFILE );
			}
			if ( defined( 'DIEONDBERROR' ) ) {
				wp_die( $msg );
			}
		} else {
			$str   = htmlspecialchars( $str, ENT_QUOTES );
			$query = htmlspecialchars( $this->last_query, ENT_QUOTES );

			printf(
				'<div id="error"><p class="wpdberror"><strong>%s</strong> [%s]<br /><code>%s</code></p></div>',
				__( 'WordPress database error:' ),
				$str,
				$query
			);
		}
	}

	/**
	 * Method to flush cached data.
	 *
	 * This overrides wpdb::flush(). This is not necessarily overridden, because
	 * $result will never be resource.
	 *
	 * @see wpdb::flush
	 */
	public function flush() {
		$this->last_result   = array();
		$this->col_info      = null;
		$this->last_query    = null;
		$this->rows_affected = 0;
		$this->num_rows      = 0;
		$this->last_error    = '';
		$this->result        = null;
	}

	/**
	 * Method to do the database connection.
	 *
	 * This overrides wpdb::db_connect() to avoid using MySQL function.
	 *
	 * @see wpdb::db_connect()
	 *
	 * @param bool $allow_bail Not used.
	 * @return void
	 */
	public function db_connect( $allow_bail = true ) {
		if ( $this->dbh ) {
			return;
		}
		$this->init_charset();

		$pdo = null;
		if ( isset( $GLOBALS['@pdo'] ) ) {
			$pdo = $GLOBALS['@pdo'];
		}
		if ( defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER ) {
			if ( null === $this->dbname || '' === $this->dbname ) {
				$this->bail(
					'The database name was not set. The SQLite driver requires a database name to be set to emulate MySQL information schema tables.',
					'db_connect_fail'
				);
				return false;
			}

			require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-grammar.php';
			require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser.php';
			require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-node.php';
			require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-token.php';
			require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-token.php';
			require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-lexer.php';
			require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-parser.php';
			require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-connection.php';
			require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-configurator.php';
			require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-driver.php';
			require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-driver-exception.php';
			require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-information-schema-builder.php';
			require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-information-schema-exception.php';
			require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-information-schema-reconstructor.php';
			$this->ensure_database_directory( FQDB );

			try {
				$connection      = new WP_SQLite_Connection(
					array(
						'pdo'          => $pdo,
						'path'         => FQDB,
						'journal_mode' => defined( 'SQLITE_JOURNAL_MODE' ) ? SQLITE_JOURNAL_MODE : null,
					)
				);
				$this->dbh       = new WP_SQLite_Driver( $connection, $this->dbname );
				$GLOBALS['@pdo'] = $this->dbh->get_connection()->get_pdo();
			} catch ( Throwable $e ) {
				$this->last_error = $this->format_error_message( $e );
			}
		} else {
			$this->dbh        = new WP_SQLite_Translator( $pdo );
			$this->last_error = $this->dbh->get_error_message();
			$GLOBALS['@pdo']  = $this->dbh->get_pdo();
		}
		if ( $this->last_error ) {
			return false;
		}
		$this->ready = true;
		$this->set_sql_mode();
	}

	/**
	 * Method to dummy out wpdb::check_connection()
	 *
	 * @param bool $allow_bail Not used.
	 *
	 * @return bool
	 */
	public function check_connection( $allow_bail = true ) {
		return true;
	}

	/**
	 * Prepares a SQL query for safe execution.
	 *
	 * See "wpdb::prepare()". This override only fixes a WPDB test issue.
	 *
	 * @param string      $query   Query statement with `sprintf()`-like placeholders.
	 * @param array|mixed $args    The array of variables or the first variable to substitute.
	 * @param mixed       ...$args Further variables to substitute when using individual arguments.
	 * @return string|void         Sanitized query string, if there is a query to prepare.
	 */
	public function prepare( $query, ...$args ) {
		/*
		 * Sync "$allow_unsafe_unquoted_parameters" with the WPDB parent property.
		 * This is only needed because some WPDB tests are accessing the private
		 * property externally via PHP reflection. This should be fixed WP tests.
		 */
		$wpdb_allow_unsafe_unquoted_parameters = $this->__get( 'allow_unsafe_unquoted_parameters' );
		if ( $wpdb_allow_unsafe_unquoted_parameters !== $this->allow_unsafe_unquoted_parameters ) {
			$property = new ReflectionProperty( 'wpdb', 'allow_unsafe_unquoted_parameters' );
			$property->setAccessible( true );
			$property->setValue( $this, $this->allow_unsafe_unquoted_parameters );
			$property->setAccessible( false );
		}

		return parent::prepare( $query, ...$args );
	}

	/**
	 * Performs a database query.
	 *
	 * This overrides wpdb::query() while closely mirroring its implementation.
	 *
	 * @see wpdb::query()
	 *
	 * @param string $query Database query.
	 *
	 * @param string $query Database query.
	 * @return int|bool Boolean true for CREATE, ALTER, TRUNCATE and DROP queries. Number of rows
	 *                  affected/selected for all other queries. Boolean false on error.
	 */
	public function query( $query ) {
		if ( ! $this->ready ) {
			return false;
		}

		$query = apply_filters( 'query', $query );

		if ( ! $query ) {
			$this->insert_id = 0;
			return false;
		}

		$this->flush();

		// Log how the function was called.
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug.
		$this->last_query = $query;

		/*
		 * @TODO: WPDB uses "$this->check_current_query" to check table/column
		 *        charset and strip all invalid characters from the query.
		 *        This is an involved process that we can bypass for SQLite,
		 *        if we simply strip all invalid UTF-8 characters from the query.
		 *
		 *        To do so, mb_convert_encoding can be used with an optional
		 *        fallback to a htmlspecialchars method. E.g.:
		 *          https://github.com/nette/utils/blob/be534713c227aeef57ce1883fc17bc9f9e29eca2/src/Utils/Strings.php#L42
		 */
		$this->_do_query( $query );

		if ( $this->last_error ) {
			// Clear insert_id on a subsequent failed insert.
			if ( $this->insert_id && preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
				$this->insert_id = 0;
			}

			$this->print_error();
			return false;
		}

		if ( preg_match( '/^\s*(create|alter|truncate|drop)\s/i', $query ) ) {
			$return_val = true;
		} elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $query ) ) {
			if ( $this->dbh instanceof WP_SQLite_Driver ) {
				$this->rows_affected = $this->dbh->get_last_return_value();
			} else {
				$this->rows_affected = $this->dbh->get_affected_rows();
			}

			// Take note of the insert_id.
			if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
				$this->insert_id = $this->dbh->get_insert_id();
			}

			// Return number of rows affected.
			$return_val = $this->rows_affected;
		} else {
			$num_rows = 0;

			if ( is_array( $this->result ) ) {
				$this->last_result = $this->result;
				$num_rows          = count( $this->result );
			}

			// Log and return the number of rows selected.
			$this->num_rows = $num_rows;
			$return_val     = $num_rows;
		}

		return $return_val;
	}

	/**
	 * Internal function to perform the SQLite query call.
	 *
	 * This closely mirrors wpdb::_do_query().
	 *
	 * @see wpdb::_do_query()
	 *
	 * @param string $query The query to run.
	 */
	private function _do_query( $query ) {
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$this->timer_start();
		}

		try {
			$this->result = $this->dbh->query( $query );
		} catch ( Throwable $e ) {
			$this->last_error = $this->format_error_message( $e );
		}

		if ( $this->dbh instanceof WP_SQLite_Translator ) {
			$this->last_error = $this->dbh->get_error_message();
		}

		++$this->num_queries;

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$this->log_query(
				$query,
				$this->timer_stop(),
				$this->get_caller(),
				$this->time_start,
				array()
			);
		}
	}

	/**
	 * Method to set the class variable $col_info.
	 *
	 * This overrides wpdb::load_col_info(), which uses a mysql function.
	 *
	 * @see    wpdb::load_col_info()
	 */
	protected function load_col_info() {
		if ( $this->col_info ) {
			return;
		}
		$this->col_info = $this->dbh->get_columns();
	}

	/**
	 * Method to return what the database can do.
	 *
	 * This overrides wpdb::has_cap() to avoid using MySQL functions.
	 * SQLite supports subqueries, but not support collation, group_concat and set_charset.
	 *
	 * @see wpdb::has_cap()
	 *
	 * @param string $db_cap The feature to check for. Accepts 'collation',
	 *                       'group_concat', 'subqueries', 'set_charset',
	 *                       'utf8mb4', or 'utf8mb4_520'.
	 *
	 * @return bool Whether the database feature is supported, false otherwise.
	 */
	public function has_cap( $db_cap ) {
		return 'subqueries' === strtolower( $db_cap );
	}

	/**
	 * Method to return database version number.
	 *
	 * This overrides wpdb::db_version() to avoid using MySQL function.
	 * It returns mysql version number, but it means nothing for SQLite.
	 * So it return the newest mysql version.
	 *
	 * @see wpdb::db_version()
	 */
	public function db_version() {
		return '8.0';
	}

	/**
	 * Returns the version of the SQLite engine.
	 *
	 * @return string SQLite engine version as a string.
	 */
	public function db_server_info() {
		return $this->dbh->get_sqlite_version();
	}

	/**
	 * Make sure the SQLite database directory exists and is writable.
	 * Create .htaccess and index.php files to prevent direct access.
	 *
	 * @param string $database_path The path to the SQLite database file.
	 */
	private function ensure_database_directory( string $database_path ) {
		$dir = dirname( $database_path );

		// Set the umask to 0000 to apply permissions exactly as specified.
		// A non-zero umask affects new file and directory permissions.
		$umask = umask( 0 );

		// Ensure database directory.
		if ( ! is_dir( $dir ) ) {
			if ( ! @mkdir( $dir, 0700, true ) ) {
				wp_die( sprintf( 'Failed to create database directory: %s', $dir ), 'Error!' );
			}
		}
		if ( ! is_writable( $dir ) ) {
			wp_die( sprintf( 'Database directory is not writable: %s', $dir ), 'Error!' );
		}

		// Ensure .htaccess file to prevent direct access.
		$path = $dir . DIRECTORY_SEPARATOR . '.htaccess';
		if ( ! is_file( $path ) ) {
			$result = file_put_contents( $path, 'DENY FROM ALL', LOCK_EX );
			if ( false === $result ) {
				wp_die( sprintf( 'Failed to create file: %s', $path ), 'Error!' );
			}
			chmod( $path, 0600 );
		}

		// Ensure index.php file to prevent direct access.
		$path = $dir . DIRECTORY_SEPARATOR . 'index.php';
		if ( ! is_file( $path ) ) {
			$result = file_put_contents( $path, '<?php // Silence is gold. ?>', LOCK_EX );
			if ( false === $result ) {
				wp_die( sprintf( 'Failed to create file: %s', $path ), 'Error!' );
			}
			chmod( $path, 0600 );
		}

		// Restore the original umask value.
		umask( $umask );
	}


	/**
	 * Format SQLite driver error message.
	 *
	 * @return string
	 */
	private function format_error_message( Throwable $e ) {
		$output = '<div style="clear:both">&nbsp;</div>' . PHP_EOL;

		// Queries.
		if ( $e instanceof WP_SQLite_Driver_Exception ) {
			$driver = $e->getDriver();

			$output .= '<div class="queries" style="clear:both;margin-bottom:2px;border:red dotted thin;">' . PHP_EOL;
			$output .= '<p>MySQL query:</p>' . PHP_EOL;
			$output .= '<p>' . $driver->get_last_mysql_query() . '</p>' . PHP_EOL;
			$output .= '<p>Queries made or created this session were:</p>' . PHP_EOL;
			$output .= '<ol>' . PHP_EOL;
			foreach ( $driver->get_last_sqlite_queries() as $q ) {
				$message = "Executing: {$q['sql']} | " . ( $q['params'] ? 'parameters: ' . implode( ', ', $q['params'] ) : '(no parameters)' );
				$output .= '<li>' . htmlspecialchars( $message ) . '</li>' . PHP_EOL;
			}
			$output .= '</ol>' . PHP_EOL;
			$output .= '</div>' . PHP_EOL;
		}

		// Message.
		$output .= '<div style="clear:both;margin-bottom:2px;border:red dotted thin;" class="error_message" style="border-bottom:dotted blue thin;">' . PHP_EOL;
		$output .= $e->getMessage() . PHP_EOL;
		$output .= '</div>' . PHP_EOL;

		// Backtrace.
		$output .= '<p>Backtrace:</p>' . PHP_EOL;
		$output .= '<pre>' . $e->getTraceAsString() . '</pre>' . PHP_EOL;
		return $output;
	}
}
