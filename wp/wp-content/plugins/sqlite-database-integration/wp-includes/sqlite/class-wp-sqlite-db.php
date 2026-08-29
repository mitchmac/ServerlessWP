<?php
/**
 * Extend and replace the wpdb class.
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
	 * @var WP_MySQL_On_SQLite
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
	 * Returns the active MySQL-on-SQLite driver.
	 *
	 * @return WP_MySQL_On_SQLite The active driver.
	 * @throws RuntimeException When there is no active database connection.
	 */
	public function get_driver(): WP_MySQL_On_SQLite {
		if ( ! $this->dbh ) {
			throw new RuntimeException( 'Cannot access the driver without an active database connection.' );
		}

		return $this->dbh;
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
	 * Retrieves the character set for the given column.
	 *
	 * This overrides wpdb::get_col_charset() to enable the parent's implementation
	 * for SQLite by temporarily setting the is_mysql flag.
	 *
	 * @see wpdb::get_col_charset()
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return string|false|WP_Error Column character set as a string. False if the column has
	 *                               no character set. WP_Error object on failure.
	 */
	public function get_col_charset( $table, $column ) {
		$original_is_mysql = $this->is_mysql ?? null;

		/*
		 * The parent method returns early when `$this->is_mysql` is falsy.
		 * Since SQLite doesn't set this flag, we enable it temporarily so
		 * the parent can run its full logic — querying column metadata via
		 * SHOW FULL COLUMNS (which the SQLite driver translates) and
		 * populating the `$this->col_meta` cache.
		 */
		try {
			$this->is_mysql = true;
			return parent::get_col_charset( $table, $column );
		} finally {
			$this->is_mysql = $original_is_mysql;
		}
	}

	/**
	 * Retrieves the maximum string length allowed in a given column.
	 *
	 * This overrides wpdb::get_col_length() to enable the parent's implementation
	 * for SQLite by temporarily setting the is_mysql flag.
	 *
	 * @see wpdb::get_col_length()
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return array|false|WP_Error Column length information, false if the column has
	 *                              no length. WP_Error object on failure.
	 */
	public function get_col_length( $table, $column ) {
		$original_is_mysql = $this->is_mysql ?? null;

		// See get_col_charset() for an explanation of the is_mysql flag.
		try {
			$this->is_mysql = true;
			return parent::get_col_length( $table, $column );
		} finally {
			$this->is_mysql = $original_is_mysql;
		}
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
		if ( empty( $modes ) ) {
			$result = $this->dbh->query( 'SELECT @@SESSION.sql_mode' )->fetchAll( PDO::FETCH_OBJ ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
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
	 *
	 * This overrides wpdb::close() while closely mirroring its implementation.
	 *
	 * @see wpdb::close()
	 *
	 * @return bool True if the connection was successfully closed,
	 *              false if it wasn't, or if the connection doesn't exist.
	 */
	public function close() {
		if ( ! $this->dbh ) {
			return false;
		}

		$connection = $this->dbh->get_connection();
		$pdo        = $connection->get_pdo();

		try {
			if ( $this->dbh->inTransaction() ) {
				$this->dbh->rollBack();
			} elseif ( $pdo->inTransaction() ) {
				$pdo->rollBack();
			} else {
				/*
				 * On PHP < 8.4, PDO cannot detect transactions started via SQL.
				 * A savepoint ensures ROLLBACK succeeds with or without one.
				 */
				$pdo->exec( 'SAVEPOINT wp_sqlite_db_close' );
				$pdo->exec( 'ROLLBACK' );
			}
		} catch ( Throwable $e ) {
			return false;
		}

		if (
			isset( $GLOBALS['@pdo'] )
			&& $GLOBALS['@pdo'] === $pdo
		) {
			unset( $GLOBALS['@pdo'] );
		}

		$connection->set_query_logger( null );
		$this->result        = null;
		$this->dbh           = null;
		$this->ready         = false;
		$this->has_connected = false;

		return true;
	}

	/**
	 * Determines the best charset and collation to use given a charset and collation.
	 *
	 * For example, when able, utf8mb4 should be used instead of utf8.
	 *
	 * This overrides wpdb::determine_charset() while closely mirroring its implementation.
	 * The override is needed because the parent checks for a mysqli connection object.
	 *
	 * @param string $charset The character set to check.
	 * @param string $collate The collation to check.
	 * @return array {
	 *     The most appropriate character set and collation to use.
	 *
	 *     @type string $charset Character set.
	 *     @type string $collate Collation.
	 * }
	 */
	public function determine_charset( $charset, $collate ) {
		if ( ! $this->dbh ) {
			return compact( 'charset', 'collate' );
		}

		if ( 'utf8' === $charset ) {
			$charset = 'utf8mb4';
		}

		if ( 'utf8mb4' === $charset ) {
			// _general_ is outdated, so we can upgrade it to _unicode_, instead.
			if ( ! $collate || 'utf8_general_ci' === $collate ) {
				$collate = 'utf8mb4_unicode_ci';
			} else {
				$collate = str_replace( 'utf8_', 'utf8mb4_', $collate );
			}
		}

		// _unicode_520_ is a better collation, we should use that when it's available.
		if ( $this->has_cap( 'utf8mb4_520' ) && 'utf8mb4_unicode_ci' === $collate ) {
			$collate = 'utf8mb4_unicode_520_ci';
		}

		return compact( 'charset', 'collate' );
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
	 * @throws RuntimeException When the database connection is not initialized.
	 */
	public function _real_escape( $data ) {
		if ( ! is_scalar( $data ) ) {
			return '';
		}

		if ( ! $this->dbh ) {
			throw new RuntimeException( 'Cannot escape data without an active database connection.' );
		}

		// Escape the string without bounding quotes to mirror mysqli_real_escape_string().
		$quoted  = $this->dbh->quote( (string) $data );
		$escaped = substr( $quoted, 1, -1 );
		return $this->add_placeholder_escape( $escaped );
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
	 * @return bool True on a successful connection, false on failure.
	 */
	public function db_connect( $allow_bail = true ) {
		if ( $this->dbh ) {
			return $this->ready;
		}

		$this->last_error = '';
		if ( isset( $GLOBALS['@pdo'] ) ) {
			trigger_error(
				'PDO injection via $GLOBALS[\'@pdo\'] is no longer supported. The existing PDO will be ignored and a new connection will be created.',
				E_USER_WARNING
			);
		}

		if ( ! isset( $this->charset ) ) {
			$this->init_charset();
		}

		// Migrate the database file from a legacy path, if it exists.
		if ( ! defined( 'DB_FILE' ) && ! file_exists( FQDB ) ) {
			$old_db_path = FQDBDIR . '.ht.sqlite.php';

			if ( file_exists( $old_db_path ) ) {
				if ( ! rename( $old_db_path, FQDB ) ) {
					wp_die( 'Failed to rename database file.', 'Error!' );
				}

				foreach ( array( '-wal', '-shm', '-journal' ) as $suffix ) {
					if ( file_exists( $old_db_path . $suffix ) ) {
						if ( ! rename( $old_db_path . $suffix, FQDB . $suffix ) ) {
							wp_die( 'Failed to rename database file.', 'Error!' );
						}
					}
				}
			}
		}

		if ( null === $this->dbname || '' === $this->dbname ) {
			$this->bail(
				'The database name was not set. The SQLite driver requires a database name to be set to emulate MySQL information schema tables.',
				'db_connect_fail'
			);
			return false;
		}

		$this->ensure_database_directory( FQDB );

		try {
			$options = array(
				'sqlite_journal_mode' => defined( 'SQLITE_JOURNAL_MODE' ) ? SQLITE_JOURNAL_MODE : null,
			);
			$dbh     = new WP_MySQL_On_SQLite(
				sprintf(
					'mysql-on-sqlite:path=%s;dbname=%s',
					str_replace( ';', ';;', FQDB ),
					str_replace( ';', ';;', $this->dbname )
				),
				null,
				null,
				$options
			);
			$dbh->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
			$this->dbh = $dbh;

			/**
			 * Exposes the underlying PDO SQLite connection for backward compatibility.
			 *
			 * @deprecated 3.0.0 Use WP_SQLite_DB::get_driver() with
			 *                   WP_MySQL_On_SQLite::get_sqlite_pdo() instead.
			 */
			$GLOBALS['@pdo'] = $dbh->get_sqlite_pdo();
		} catch ( Throwable $e ) {
			$this->last_error = $this->format_error_message( $e );
		}
		if ( $this->last_error ) {
			return false;
		}

		$this->has_connected = true;
		$this->set_charset( $this->dbh );

		$this->ready = true;
		$this->set_sql_mode();
		return true;
	}

	/**
	 * Checks that the database connection is available.
	 *
	 * @param bool $allow_bail Not used.
	 *
	 * @return bool True when the connection is available, false otherwise.
	 */
	public function check_connection( $allow_bail = true ) {
		if ( $this->dbh ) {
			return true;
		}

		return $this->db_connect( $allow_bail );
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
		// Query Monitor integration:
		$query_monitor_active = defined( 'SQLITE_QUERY_MONITOR_LOADED' ) && SQLITE_QUERY_MONITOR_LOADED;
		if ( $query_monitor_active && $this->show_errors ) {
			$this->hide_errors();
		}

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

		// Save the query count before running another query.
		$last_query_count = count( $this->queries ?? array() );

		/*
		 * @TODO: wpdb uses "$this->check_current_query" and table metadata to
		 * reject queries containing invalid text. Implement equivalent handling
		 * for SQLite without relying on the MySQL-specific conversion pipeline.
		 *
		 * PCRE's "u" modifier can validate UTF-8 without constructing a converted
		 * query copy: 1 === preg_match( '//u', $query ). The implementation must
		 * preserve wpdb's exemptions for prevalidated and binary data.
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
			$this->rows_affected = $this->result->rowCount();

			// Take note of the insert_id.
			if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
				$this->insert_id = (int) $this->dbh->lastInsertId();
			}

			// Return number of rows affected.
			$return_val = $this->rows_affected;
		} else {
			$num_rows = 0;

			if ( $this->result->columnCount() > 0 ) {
				$this->last_result = $this->result->fetchAll();
				$num_rows          = count( $this->last_result );
			}

			// Log and return the number of rows selected.
			$this->num_rows = $num_rows;
			$return_val     = $num_rows;
		}

		// Query monitor integration:
		if ( $query_monitor_active && class_exists( 'QM_Backtrace' ) ) {
			if ( did_action( 'qm/cease' ) ) {
				$this->queries = array();
			}

			$i = $last_query_count;
			if ( ! isset( $this->queries[ $i ] ) ) {
				return $return_val;
			}

			$this->queries[ $i ]['trace'] = new QM_Backtrace();
			if ( ! isset( $this->queries[ $i ][3] ) ) {
				$this->queries[ $i ][3] = $this->time_start;
			}

			if ( $this->last_error && ! $this->suppress_errors ) {
				$this->queries[ $i ]['result'] = new WP_Error( 'qmdb', $this->last_error );
			} else {
				$this->queries[ $i ]['result'] = (int) $return_val;
			}

			// Add SQLite query data.
			$this->queries[ $i ]['sqlite_queries'] = $this->dbh->get_last_sqlite_queries();
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
			$this->result = $this->dbh->query( $query, PDO::FETCH_OBJ ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
		} catch ( Throwable $e ) {
			$this->last_error = $this->format_error_message( $e );
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
		$this->col_info = array();
		if ( null === $this->result ) {
			return;
		}
		for ( $i = 0; $i < $this->result->columnCount(); $i++ ) {
			$column           = $this->result->getColumnMeta( $i );
			$this->col_info[] = (object) array(
				'name'       => $column['name'],
				'orgname'    => $column['mysqli:orgname'],
				'table'      => $column['table'],
				'orgtable'   => $column['mysqli:orgtable'],
				'def'        => '',    // Unused, always ''.
				'db'         => $column['mysqli:db'],
				'catalog'    => 'def', // Unused, always 'def'.
				'max_length' => 0,     // As of PHP 8.1, this is always 0.
				'length'     => $column['len'],
				'charsetnr'  => $column['mysqli:charsetnr'],
				'flags'      => $column['mysqli:flags'],
				'type'       => $column['mysqli:type'],
				'decimals'   => $column['precision'],
			);
		}
	}

	/**
	 * Determines whether the database supports a given feature.
	 *
	 * The utf8mb4 check is handled here because older WordPress versions inspect
	 * the MySQL client library. All other capabilities use the parent logic.
	 *
	 * @see wpdb::has_cap()
	 *
	 * @param string $db_cap The feature to check for.
	 * @return bool True when the database feature is supported, false otherwise.
	 */
	public function has_cap( $db_cap ) {
		if ( 'utf8mb4' === strtolower( $db_cap ) ) {
			return true;
		}

		return parent::has_cap( $db_cap );
	}

	/**
	 * Retrieves the emulated database server version number.
	 *
	 * This mirrors wpdb::db_version(), but must also be defined here because
	 * WordPress 5.4 and older fetch server information directly from the MySQL
	 * extension instead of delegating to wpdb::db_server_info().
	 *
	 * @see wpdb::db_version()
	 *
	 * @return string Version number on success, or an empty string while disconnected.
	 */
	public function db_version() {
		return preg_replace( '/[^0-9.].*/', '', $this->db_server_info() );
	}

	/**
	 * Returns the raw version string of the emulated MySQL server.
	 *
	 * @see wpdb::db_server_info()
	 *
	 * @return string Emulated MySQL server version, or an empty string while disconnected.
	 */
	public function db_server_info() {
		if ( ! $this->dbh ) {
			return '';
		}

		return $this->dbh->getAttribute( PDO::ATTR_SERVER_VERSION ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
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
	 * Format MySQL-on-SQLite driver error message.
	 *
	 * @return string
	 */
	private function format_error_message( Throwable $e ) {
		$output = '<div style="clear:both">&nbsp;</div>' . PHP_EOL;

		// Queries.
		if ( $e instanceof WP_MySQL_On_SQLite_Exception ) {
			$driver = $e->get_driver();

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
