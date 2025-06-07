<?php

/*
 * The SQLite driver uses PDO. Enable PDO function calls:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 */

/**
 * SQLite driver for MySQL.
 *
 * This class emulates a MySQL database server on top of an SQLite database.
 * It translates queries written in MySQL SQL dialect to an SQLite SQL dialect,
 * maintains necessary metadata, and executes the translated queries in SQLite.
 *
 * The driver requires PDO with the SQLite driver, and the PCRE engine.
 */
class WP_SQLite_Driver {
	/**
	 * The path to the MySQL SQL grammar file.
	 */
	const MYSQL_GRAMMAR_PATH = __DIR__ . '/../../wp-includes/mysql/mysql-grammar.php';

	/**
	 * The minimum required version of SQLite.
	 *
	 * Currently, we require SQLite >= 3.37.0 due to the STRICT table support:
	 *   https://www.sqlite.org/stricttables.html
	 */
	const MINIMUM_SQLITE_VERSION = '3.37.0';

	/**
	 * An identifier prefix for internal database objects.
	 *
	 * @TODO: Do not allow accessing objects with this prefix.
	 */
	const RESERVED_PREFIX = '_wp_sqlite_';

	/**
	 * The name of a global variables table.
	 *
	 * This special table is used to emulate MySQL global variables and to store
	 * some internal configuration values.
	 */
	const GLOBAL_VARIABLES_TABLE_NAME = self::RESERVED_PREFIX . 'global_variables';

	/**
	 * The name of the SQLite driver version variable.
	 *
	 * This internal variable is used to store the latest version of the SQLite
	 * driver that was used to initialize and configure the SQLite database.
	 */
	const DRIVER_VERSION_VARIABLE_NAME = self::RESERVED_PREFIX . 'driver_version';

	/**
	 * A map of MySQL tokens to SQLite data types.
	 *
	 * This is used to translate a MySQL data type to an SQLite data type.
	 */
	const DATA_TYPE_MAP = array(
		// Numeric data types:
		WP_MySQL_Lexer::BIT_SYMBOL                => 'INTEGER',
		WP_MySQL_Lexer::BOOL_SYMBOL               => 'INTEGER',
		WP_MySQL_Lexer::BOOLEAN_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::TINYINT_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::SMALLINT_SYMBOL           => 'INTEGER',
		WP_MySQL_Lexer::MEDIUMINT_SYMBOL          => 'INTEGER',
		WP_MySQL_Lexer::INT_SYMBOL                => 'INTEGER',
		WP_MySQL_Lexer::INTEGER_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::BIGINT_SYMBOL             => 'INTEGER',
		WP_MySQL_Lexer::FLOAT_SYMBOL              => 'REAL',
		WP_MySQL_Lexer::DOUBLE_SYMBOL             => 'REAL',
		WP_MySQL_Lexer::REAL_SYMBOL               => 'REAL',
		WP_MySQL_Lexer::DECIMAL_SYMBOL            => 'REAL',
		WP_MySQL_Lexer::DEC_SYMBOL                => 'REAL',
		WP_MySQL_Lexer::FIXED_SYMBOL              => 'REAL',
		WP_MySQL_Lexer::NUMERIC_SYMBOL            => 'REAL',

		// String data types:
		WP_MySQL_Lexer::CHAR_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::VARCHAR_SYMBOL            => 'TEXT',
		WP_MySQL_Lexer::NCHAR_SYMBOL              => 'TEXT',
		WP_MySQL_Lexer::NVARCHAR_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TINYTEXT_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TEXT_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::MEDIUMTEXT_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::LONGTEXT_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::ENUM_SYMBOL               => 'TEXT',

		// Date and time data types:
		WP_MySQL_Lexer::DATE_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::TIME_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::DATETIME_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TIMESTAMP_SYMBOL          => 'TEXT',
		WP_MySQL_Lexer::YEAR_SYMBOL               => 'TEXT',

		// Binary data types:
		WP_MySQL_Lexer::BINARY_SYMBOL             => 'BLOB',
		WP_MySQL_Lexer::VARBINARY_SYMBOL          => 'BLOB',
		WP_MySQL_Lexer::TINYBLOB_SYMBOL           => 'BLOB',
		WP_MySQL_Lexer::BLOB_SYMBOL               => 'BLOB',
		WP_MySQL_Lexer::MEDIUMBLOB_SYMBOL         => 'BLOB',
		WP_MySQL_Lexer::LONGBLOB_SYMBOL           => 'BLOB',

		// Spatial data types:
		WP_MySQL_Lexer::GEOMETRY_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::POINT_SYMBOL              => 'TEXT',
		WP_MySQL_Lexer::LINESTRING_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::POLYGON_SYMBOL            => 'TEXT',
		WP_MySQL_Lexer::MULTIPOINT_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::MULTILINESTRING_SYMBOL    => 'TEXT',
		WP_MySQL_Lexer::MULTIPOLYGON_SYMBOL       => 'TEXT',
		WP_MySQL_Lexer::GEOMCOLLECTION_SYMBOL     => 'TEXT',
		WP_MySQL_Lexer::GEOMETRYCOLLECTION_SYMBOL => 'TEXT',

		// SERIAL, SET, and JSON types are handled in the translation process.
	);

	/**
	 * A map of normalized MySQL data types to SQLite data types.
	 *
	 * This is used to generate SQLite CREATE TABLE statements from the MySQL
	 * INFORMATION_SCHEMA tables. They keys are MySQL data types normalized
	 * as they appear in the INFORMATION_SCHEMA. Values are SQLite data types.
	 */
	const DATA_TYPE_STRING_MAP = array(
		// Numeric data types:
		'bit'                => 'INTEGER',
		'bool'               => 'INTEGER',
		'boolean'            => 'INTEGER',
		'tinyint'            => 'INTEGER',
		'smallint'           => 'INTEGER',
		'mediumint'          => 'INTEGER',
		'int'                => 'INTEGER',
		'integer'            => 'INTEGER',
		'bigint'             => 'INTEGER',
		'float'              => 'REAL',
		'double'             => 'REAL',
		'real'               => 'REAL',
		'decimal'            => 'REAL',
		'dec'                => 'REAL',
		'fixed'              => 'REAL',
		'numeric'            => 'REAL',

		// String data types:
		'char'               => 'TEXT',
		'varchar'            => 'TEXT',
		'nchar'              => 'TEXT',
		'nvarchar'           => 'TEXT',
		'tinytext'           => 'TEXT',
		'text'               => 'TEXT',
		'mediumtext'         => 'TEXT',
		'longtext'           => 'TEXT',
		'enum'               => 'TEXT',
		'set'                => 'TEXT',
		'json'               => 'TEXT',

		// Date and time data types:
		'date'               => 'TEXT',
		'time'               => 'TEXT',
		'datetime'           => 'TEXT',
		'timestamp'          => 'TEXT',
		'year'               => 'TEXT',

		// Binary data types:
		'binary'             => 'BLOB',
		'varbinary'          => 'BLOB',
		'tinyblob'           => 'BLOB',
		'blob'               => 'BLOB',
		'mediumblob'         => 'BLOB',
		'longblob'           => 'BLOB',

		// Spatial data types:
		'geometry'           => 'TEXT',
		'point'              => 'TEXT',
		'linestring'         => 'TEXT',
		'polygon'            => 'TEXT',
		'multipoint'         => 'TEXT',
		'multilinestring'    => 'TEXT',
		'multipolygon'       => 'TEXT',
		'geomcollection'     => 'TEXT',
		'geometrycollection' => 'TEXT',
	);

	/**
	 * A map of MySQL to SQLite date format translation.
	 *
	 * It maps MySQL DATE_FORMAT() formats to SQLite STRFTIME() formats.
	 *
	 * For MySQL formats, see:
	 *   https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-format
	 *
	 * For SQLite formats, see:
	 *   https://www.sqlite.org/lang_datefunc.html
	 *   https://strftime.org/
	 */
	const MYSQL_DATE_FORMAT_TO_SQLITE_STRFTIME_MAP = array(
		'%a' => '%D',
		'%b' => '%M',
		'%c' => '%n',
		'%D' => '%jS',
		'%d' => '%d',
		'%e' => '%j',
		'%H' => '%H',
		'%h' => '%h',
		'%I' => '%h',
		'%i' => '%M',
		'%j' => '%z',
		'%k' => '%G',
		'%l' => '%g',
		'%M' => '%F',
		'%m' => '%m',
		'%p' => '%A',
		'%r' => '%h:%i:%s %A',
		'%S' => '%s',
		'%s' => '%s',
		'%T' => '%H:%i:%s',
		'%U' => '%W',
		'%u' => '%W',
		'%V' => '%W',
		'%v' => '%W',
		'%W' => '%l',
		'%w' => '%w',
		'%X' => '%Y',
		'%x' => '%o',
		'%Y' => '%Y',
		'%y' => '%y',
	);

	/**
	 * A map of MySQL data types to implicit default values for non-strict mode.
	 *
	 * In MySQL, when STRICT_TRANS_TABLES and STRICT_ALL_TABLES modes are disabled,
	 * columns get IMPLICIT DEFAULT values that are used under some circumstances.
	 *
	 * See:
	 *   https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html#data-type-defaults-implicit
	 */
	const DATA_TYPE_IMPLICIT_DEFAULT_MAP = array(
		// Numeric data types:
		'bit'                => '0',
		'bool'               => '0',
		'boolean'            => '0',
		'tinyint'            => '0',
		'smallint'           => '0',
		'mediumint'          => '0',
		'int'                => '0',
		'integer'            => '0',
		'bigint'             => '0',
		'float'              => '0',
		'double'             => '0',
		'real'               => '0',
		'decimal'            => '0',
		'dec'                => '0',
		'fixed'              => '0',
		'numeric'            => '0',

		// String data types:
		'char'               => '',
		'varchar'            => '',
		'nchar'              => '',
		'nvarchar'           => '',
		'tinytext'           => '',
		'text'               => '',
		'mediumtext'         => '',
		'longtext'           => '',
		'enum'               => '',     // TODO: Implement (first enum value).
		'set'                => '',
		'json'               => 'null', // String value 'null' (valid JSON)

		// Date and time data types:
		'date'               => '0000-00-00',
		'time'               => '00:00:00',
		'datetime'           => '0000-00-00 00:00:00',
		'timestamp'          => '0000-00-00 00:00:00',
		'year'               => '0000',

		// Binary data types:
		'binary'             => '',
		'varbinary'          => '',
		'tinyblob'           => '',
		'blob'               => '',
		'mediumblob'         => '',
		'longblob'           => '',

		// Spatial data types (no implicit defaults):
		'geometry'           => null,
		'point'              => null,
		'linestring'         => null,
		'polygon'            => null,
		'multipoint'         => null,
		'multilinestring'    => null,
		'multipolygon'       => null,
		'geomcollection'     => null,
		'geometrycollection' => null,
	);

	/**
	 * The SQLite engine version.
	 *
	 * This is a mysqli-like property that is needed to avoid a PHP warning in
	 * the WordPress health info. The "WP_Debug_Data::get_wp_database()" method
	 * calls "$wpdb->dbh->client_info" - a mysqli-specific abstraction leak.
	 *
	 * @TODO: This should be fixed in WordPress core.
	 *
	 * See:
	 *   https://github.com/WordPress/wordpress-develop/blob/bcdca3f9925f1d3eca7b78d231837c0caf0c8c24/src/wp-admin/includes/class-wp-debug-data.php#L1579
	 *
	 * @var string
	 */
	public $client_info;

	/**
	 * A MySQL query parser grammar.
	 *
	 * @var WP_Parser_Grammar
	 */
	private static $mysql_grammar;

	/**
	 * The main database name.
	 *
	 * The name of the main database that is used by the driver.
	 *
	 * @var string|null
	 */
	private $main_db_name;

	/**
	 * The name of the current database in use.
	 *
	 * This can be set with the USE statement. At the moment, we support only
	 * the main driver database and the INFORMATION_SCHEMA database.
	 *
	 * @var string
	 */
	private $db_name;

	/**
	 * An instance of the SQLite connection.
	 *
	 * @var WP_SQLite_Connection
	 */
	private $connection;

	/**
	 * A service for managing MySQL INFORMATION_SCHEMA tables in SQLite.
	 *
	 * @var WP_SQLite_Information_Schema_Builder
	 */
	private $information_schema_builder;

	/**
	 * Last executed MySQL query.
	 *
	 * @var string
	 */
	private $last_mysql_query;

	/**
	 * A list of SQLite queries executed for the last MySQL query.
	 *
	 * @var array{ sql: string, params: array }[]
	 */
	private $last_sqlite_queries = array();

	/**
	 * Results of the last emulated query.
	 *
	 * @var array|null
	 */
	private $last_result;

	/**
	 * Return value of the last emulated query.
	 *
	 * @var mixed
	 */
	private $last_return_value;

	/**
	 * Number of rows found by the last SQL_CALC_FOUND_ROW query.
	 *
	 * @var int
	 */
	private $last_sql_calc_found_rows = null;

	/**
	 * Whether the current MySQL query is read-only.
	 *
	 * @var bool
	 */
	private $is_readonly;

	/**
	 * Transaction nesting level of the executed SQLite queries.
	 *
	 * @var int
	 */
	private $transaction_level = 0;

	/**
	 * The PDO fetch mode used for the emulated query.
	 *
	 * @var mixed
	 */
	private $pdo_fetch_mode;

	/**
	 * The currently active MySQL SQL modes.
	 *
	 * The default value reflects the default SQL modes for MySQL 8.0.
	 *
	 * TODO: This may be represented using a temporary table in the future,
	 *       together with GLOBAL SQL mode (a non-temporary table).
	 *
	 * @var string[]
	 */
	private $active_sql_modes = array(
		'ERROR_FOR_DIVISION_BY_ZERO',
		'NO_ENGINE_SUBSTITUTION',
		'NO_ZERO_DATE',
		'NO_ZERO_IN_DATE',
		'ONLY_FULL_GROUP_BY',
		'STRICT_TRANS_TABLES',
	);

	/**
	 * Constructor.
	 *
	 * Set up an SQLite connection and the MySQL-on-SQLite driver.
	 *
	 * @param WP_SQLite_Connection $connection A SQLite database connection.
	 * @param string               $database   The database name.
	 *
	 * @throws WP_SQLite_Driver_Exception When the driver initialization fails.
	 */
	public function __construct( WP_SQLite_Connection $connection, string $database ) {
		$this->connection   = $connection;
		$this->main_db_name = $database;
		$this->db_name      = $database;

		// Check the SQLite version.
		$sqlite_version = $this->get_sqlite_version();
		if ( version_compare( $sqlite_version, self::MINIMUM_SQLITE_VERSION, '<' ) ) {
			throw $this->new_driver_exception(
				sprintf(
					'The SQLite version %s is not supported. Minimum required version is %s.',
					$sqlite_version,
					self::MINIMUM_SQLITE_VERSION
				)
			);
		}

		// Load SQLite version to a property used by WordPress health info.
		$this->client_info = $sqlite_version;

		// Enable foreign keys. By default, they are off.
		$this->connection->query( 'PRAGMA foreign_keys = ON' );

		// Register SQLite functions.
		WP_SQLite_PDO_User_Defined_Functions::register_for( $this->connection->get_pdo() );

		// Load MySQL grammar.
		if ( null === self::$mysql_grammar ) {
			self::$mysql_grammar = new WP_Parser_Grammar( require self::MYSQL_GRAMMAR_PATH );
		}

		// Initialize information schema builder.
		$this->information_schema_builder = new WP_SQLite_Information_Schema_Builder(
			$this->main_db_name,
			self::RESERVED_PREFIX,
			$this->connection
		);

		// Ensure that the database is configured.
		$migrator = new WP_SQLite_Configurator( $this, $this->information_schema_builder );
		$migrator->ensure_database_configured();

		$this->connection->set_query_logger(
			function ( string $sql, array $params ) {
				$this->last_sqlite_queries[] = array(
					'sql'    => $sql,
					'params' => $params,
				);
			}
		);
	}

	/**
	 * Get the SQLite connection instance.
	 *
	 * @return WP_SQLite_Connection
	 */
	public function get_connection(): WP_SQLite_Connection {
		return $this->connection;
	}

	/**
	 * Get the version of the SQLite engine.
	 *
	 * @return string SQLite engine version as a string.
	 */
	public function get_sqlite_version(): string {
		return $this->connection->query( 'SELECT SQLITE_VERSION()' )->fetchColumn();
	}

	/**
	 * Get the SQLite driver version saved in the database.
	 *
	 * The saved driver version corresponds to the latest version of the SQLite
	 * driver that was used to initialize and configure the SQLite database.
	 *
	 * @return string       SQLite driver version as a string.
	 * @throws PDOException When the query execution fails.
	 */
	public function get_saved_driver_version(): string {
		$default_version = '0.0.0';
		try {
			$stmt = $this->execute_sqlite_query(
				sprintf(
					'SELECT value FROM %s WHERE name = ?',
					$this->quote_sqlite_identifier( self::GLOBAL_VARIABLES_TABLE_NAME )
				),
				array( self::DRIVER_VERSION_VARIABLE_NAME )
			);
			return $stmt->fetchColumn() ?? $default_version;
		} catch ( PDOException $e ) {
			if ( str_contains( $e->getMessage(), 'no such table' ) ) {
				return $default_version;
			}
			throw $e;
		}
	}

	/**
	 * Check if a specific SQL mode is active.
	 *
	 * @param  string $mode The SQL mode to check.
	 * @return bool         True if the SQL mode is active, false otherwise.
	 */
	public function is_sql_mode_active( string $mode ): bool {
		return in_array( strtoupper( $mode ), $this->active_sql_modes, true );
	}

	/**
	 * Get the last executed MySQL query.
	 *
	 * @return string|null
	 */
	public function get_last_mysql_query(): ?string {
		return $this->last_mysql_query;
	}

	/**
	 * Get SQLite queries executed for the last MySQL query.
	 *
	 * @return array{ sql: string, params: array }[]
	 */
	public function get_last_sqlite_queries(): array {
		return $this->last_sqlite_queries;
	}

	/**
	 * Get the auto-increment value generated for the last query.
	 *
	 * @return int|string
	 */
	public function get_insert_id() {
		$last_insert_id = $this->connection->get_last_insert_id();
		if ( is_numeric( $last_insert_id ) ) {
			$last_insert_id = (int) $last_insert_id;
		}
		return $last_insert_id;
	}

	/**
	 * Translate and execute a MySQL query in SQLite.
	 *
	 * A single MySQL query can be translated into zero or more SQLite queries.
	 *
	 * @param string $query              Full SQL statement string.
	 * @param int    $fetch_mode         PDO fetch mode. Default is PDO::FETCH_OBJ.
	 * @param array  ...$fetch_mode_args Additional fetch mode arguments.
	 *
	 * @return mixed Return value, depending on the query type.
	 *
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 *
	 * TODO:
	 *   The API of this function is not final.
	 *   We should also add support for parametrized queries.
	 *   See: https://github.com/Automattic/sqlite-database-integration/issues/7
	 */
	public function query( string $query, $fetch_mode = PDO::FETCH_OBJ, ...$fetch_mode_args ) {
		$this->flush();
		$this->pdo_fetch_mode   = $fetch_mode;
		$this->last_mysql_query = $query;

		try {
			// Parse the MySQL query.
			$parser = $this->create_parser( $query );
			$parser->next_query();
			$ast = $parser->get_query_ast();
			if ( null === $ast ) {
				throw $this->new_driver_exception( 'Failed to parse the MySQL query.' );
			}

			if ( $parser->next_query() ) {
				throw $this->new_driver_exception( 'Multi-query is not supported.' );
			}

			// Handle transaction commands.

			/*
			 * [GRAMMAR]
			 * beginWork: BEGIN_SYMBOL WORK_SYMBOL?
			 */
			$child = $ast->get_first_child();
			if ( $child instanceof WP_Parser_Node && 'beginWork' === $child->rule_name ) {
				$this->begin_transaction();
				return true;
			}

			if ( $child instanceof WP_Parser_Node && 'simpleStatement' === $child->rule_name ) {
				/*
				 * [GRAMMAR]
				 * transactionOrLockingStatement:
				 *   transactionStatement | savepointStatement | lockStatement | xaStatement
				 */
				$subchild = $child->get_first_child_node( 'transactionOrLockingStatement' );
				if ( null !== $subchild ) {
					$tokens = $subchild->get_descendant_tokens();
					$token1 = $tokens[0];
					$token2 = $tokens[1] ?? null;
					if (
						WP_MySQL_Lexer::START_SYMBOL === $token1->id
						&& WP_MySQL_Lexer::TRANSACTION_SYMBOL === $token2->id
					) {
						$this->begin_transaction();
						return true;
					}

					if (
						WP_MySQL_Lexer::BEGIN_SYMBOL === $token1->id
					) {
						$this->begin_transaction();
						return true;
					}

					if (
						WP_MySQL_Lexer::COMMIT_SYMBOL === $token1->id
					) {
						$this->commit();
						return true;
					}

					if (
						WP_MySQL_Lexer::ROLLBACK_SYMBOL === $token1->id
					) {
						$this->rollback();
						return true;
					}
				}
			}

			// Perform all the queries in a nested transaction.
			$this->begin_transaction();
			$this->execute_mysql_query( $ast );
			$this->commit();
			return $this->last_return_value;
		} catch ( Throwable $e ) {
			try {
				$this->rollback();
			} catch ( Throwable $rollback_exception ) {
				// Ignore rollback errors.
			}
			if ( $e instanceof WP_SQLite_Driver_Exception ) {
				throw $e;
			} elseif ( $e instanceof WP_SQLite_Information_Schema_Exception ) {
				throw $this->convert_information_schema_exception( $e );
			}
			throw $this->new_driver_exception( $e->getMessage(), $e->getCode(), $e );
		}
	}

	/**
	 * Tokenize a MySQL query and initialize a parser.
	 *
	 * @param  string          $query The MySQL query to parse.
	 * @return WP_MySQL_Parser        A parser initialized for the MySQL query.
	 */
	public function create_parser( string $query ): WP_MySQL_Parser {
		$lexer  = new WP_MySQL_Lexer(
			$query,
			80038,
			$this->active_sql_modes
		);
		$tokens = $lexer->remaining_tokens();
		return new WP_MySQL_Parser( self::$mysql_grammar, $tokens );
	}

	/**
	 * Get results of the last query.
	 *
	 * @return mixed
	 */
	public function get_query_results() {
		return $this->last_result;
	}

	/**
	 * Get return value of the last query() function call.
	 *
	 * @return mixed
	 */
	public function get_last_return_value() {
		return $this->last_return_value;
	}

	/**
	 * Execute a query in SQLite.
	 *
	 * @param string $sql   The query to execute.
	 * @param array $params The query parameters.
	 * @throws PDOException When the query execution fails.
	 * @return PDOStatement The PDO statement object.
	 */
	public function execute_sqlite_query( string $sql, array $params = array() ): PDOStatement {
		return $this->connection->query( $sql, $params );
	}

	/**
	 * Begin a new transaction or nested transaction.
	 */
	public function begin_transaction(): void {
		if ( 0 === $this->transaction_level ) {
			/*
			 * When we're executing a statement that will write to the database,
			 * we need to use "BEGIN IMMEDIATE" to open a write transaction.
			 *
			 * This is needed to avoid the "database is locked" error (SQLITE_BUSY)
			 * when SQLite can't upgrade a read transaction to a write transaction,
			 * because another connection is modifying the database.
			 *
			 * From the SQLite documentation:
			 *
			 *   ## Read transactions versus write transactions
			 *
			 *   If a write statement occurs while a read transaction is active,
			 *   then the read transaction is upgraded to a write transaction if
			 *   possible. If some other database connection has already modified
			 *   the database or is already in the process of modifying the database,
			 *   then upgrading to a write transaction is not possible and the write
			 *   statement will fail with SQLITE_BUSY.
			 *
			 *   ## DEFERRED, IMMEDIATE, and EXCLUSIVE transactions
			 *
			 *   Transactions can be DEFERRED, IMMEDIATE, or EXCLUSIVE. The default
			 *   transaction behavior is DEFERRED.
			 *
			 *   DEFERRED means that the transaction does not actually start until
			 *   the database is first accessed.
			 *
			 *   IMMEDIATE causes the database connection to start a new write
			 *   immediately, without waiting for a write statement. The BEGIN
			 *   IMMEDIATE might fail with SQLITE_BUSY if another write transaction
			 *   is already active on another database connection.
			 *
			 * See:
			 *   - https://www.sqlite.org/lang_transaction.html
			 *   - https://www.sqlite.org/rescode.html#busy
			 *
			 * For better performance, we could also consider opening the write
			 * transaction later in the session - just before the first write.
			 */
			$this->execute_sqlite_query( $this->is_readonly ? 'BEGIN' : 'BEGIN IMMEDIATE' );
		} else {
			$this->execute_sqlite_query( 'SAVEPOINT LEVEL' . $this->transaction_level );
		}
		++$this->transaction_level;
	}

	/**
	 * Commit the current transaction or nested transaction.
	 */
	public function commit(): void {
		if ( 0 === $this->transaction_level ) {
			return;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'COMMIT' );
		} else {
			$this->execute_sqlite_query( 'RELEASE SAVEPOINT LEVEL' . $this->transaction_level );
		}
	}

	/**
	 * Rollback the current transaction or nested transaction.
	 */
	public function rollback(): void {
		if ( 0 === $this->transaction_level ) {
			return;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'ROLLBACK' );
		} else {
			$this->execute_sqlite_query( 'ROLLBACK TO SAVEPOINT LEVEL' . $this->transaction_level );
		}
	}

	/**
	 * Translate and execute a MySQL query in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "query" AST node with "simpleStatement" child.
	 * @throws WP_SQLite_Driver_Exception When the query is not supported.
	 */
	private function execute_mysql_query( WP_Parser_Node $node ): void {
		if ( 'query' !== $node->rule_name ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected "query" node, got: "%s"', $node->rule_name )
			);
		}

		/*
		 * [GRAMMAR]
		 * query:
		 *   EOF
		 *   | (simpleStatement | beginWork) (SEMICOLON_SYMBOL EOF? | EOF)
		 */
		$children = $node->get_child_nodes();
		if ( count( $children ) !== 1 ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected 1 child node, got: %d', count( $children ) )
			);
		}

		if ( 'simpleStatement' !== $children[0]->rule_name ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected "simpleStatement" node, got: "%s"', $children[0]->rule_name )
			);
		}

		// Process the "simpleStatement" AST node.
		$node = $children[0]->get_first_child_node();
		switch ( $node->rule_name ) {
			case 'selectStatement':
				$this->is_readonly = true;
				$this->execute_select_statement( $node );
				break;
			case 'insertStatement':
			case 'replaceStatement':
				$this->execute_insert_or_replace_statement( $node );
				break;
			case 'updateStatement':
				$this->execute_update_statement( $node );
				break;
			case 'deleteStatement':
				$this->execute_delete_statement( $node );
				break;
			case 'createStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'createDatabase':
						/*
						 * TODO:
						 * We could support this by creating a new SQLite database
						 * file (e.g., $slugified_db_name.sqlite).
						 *
						 * Alternatively, it could be a no-op, in combination with
						 * DROP DATABASE deleting the data file and recreating it.
						 */
					case 'createTable':
						$this->execute_create_table_statement( $node );
						break;
					case 'createIndex':
						$this->execute_create_index_statement( $node );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'alterStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'alterTable':
						$this->execute_alter_table_statement( $node );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'dropStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'dropTable':
						$this->execute_drop_table_statement( $node );
						break;
					case 'dropIndex':
						$this->execute_drop_index_statement( $node );
						break;
					default:
						$query = $this->translate( $node );
						$this->execute_sqlite_query( $query );
						$this->set_result_from_affected_rows();
				}
				break;
			case 'truncateTableStatement':
				$this->execute_truncate_table_statement( $node );
				break;
			case 'setStatement':
				$this->execute_set_statement( $node );
				break;
			case 'showStatement':
				$this->is_readonly = true;
				$this->execute_show_statement( $node );
				break;
			case 'utilityStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'describeStatement':
						$this->is_readonly = true;
						$this->execute_describe_statement( $subtree );
						break;
					case 'useCommand':
						$this->execute_use_statement( $subtree );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'tableAdministrationStatement':
				$this->execute_administration_statement( $node );
				break;
			default:
				throw $this->new_not_supported_exception(
					sprintf( 'statement type: "%s"', $node->rule_name )
				);
		}
	}

	/**
	 * Translate and execute a MySQL SELECT statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "selectStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_select_statement( WP_Parser_Node $node ): void {
		/*
		 * [GRAMMAR]
		 * selectStatement:
		 *   queryExpression lockingClauseList?
		 *   | selectStatementWithInto
		 */

		// First, translate the query, before we modify last found rows count.
		$query = $this->translate( $node->get_first_child() );

		$has_sql_calc_found_rows = null !== $node->get_first_descendant_token(
			WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL
		);

		// Handle SQL_CALC_FOUND_ROWS.
		if ( true === $has_sql_calc_found_rows ) {
			// Recursively find a query expression with the first LIMIT or SELECT.
			$query_expr = $node->get_first_descendant_node( 'queryExpression' );
			while ( true ) {
				if ( $query_expr->has_child_node( 'limitClause' ) ) {
					break;
				}

				$query_expr_parens = $query_expr->get_first_child_node( 'queryExpressionParens' );
				if ( null !== $query_expr_parens ) {
					$query_expr = $query_expr_parens->get_first_child_node( 'queryExpression' );
					continue;
				}

				$query_expr_body = $query_expr->get_first_child_node( 'queryExpressionBody' );
				if ( count( $query_expr_body->get_children() ) > 1 ) {
					break;
				}

				$query_term = $query_expr_body->get_first_child_node( 'queryTerm' );
				if (
					count( $query_term->get_children() ) === 1
					&& $query_term->has_child_node( 'queryExpressionParens' )
				) {
					$query_expr = $query_term->get_first_child_node( 'queryExpressionParens' )->get_first_child_node( 'queryExpression' );
					continue;
				}

				break;
			}

			// Exclude the limit clause from the expression.
			$count_expr = new WP_Parser_Node( $query_expr->rule_id, $query_expr->rule_name );
			foreach ( $query_expr->get_children() as $child ) {
				if ( ! ( $child instanceof WP_Parser_Node && 'limitClause' === $child->rule_name ) ) {
					$count_expr->append_child( $child );
				}
			}

			// Get count of all the rows.
			$result = $this->execute_sqlite_query(
				'SELECT COUNT(*) AS cnt FROM (' . $this->translate( $count_expr ) . ')'
			);

			$this->last_sql_calc_found_rows = $result->fetchColumn();
		} else {
			$this->last_sql_calc_found_rows = null;
		}

		// Execute the query.
		$stmt = $this->execute_sqlite_query( $query );
		$this->set_results_from_fetched_data(
			$stmt->fetchAll( $this->pdo_fetch_mode )
		);
	}

	/**
	 * Translate and execute a MySQL INSERT or REPLACE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "insertStatement" or "replaceStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_insert_or_replace_statement( WP_Parser_Node $node ): void {
		// Check if strict mode is disabled.
		$is_non_strict_mode = (
			! $this->is_sql_mode_active( 'STRICT_TRANS_TABLES' )
			&& ! $this->is_sql_mode_active( 'STRICT_ALL_TABLES' )
		);

		$parts = array();
		foreach ( $node->get_children() as $child ) {
			if ( $child instanceof WP_MySQL_Token && WP_MySQL_Lexer::IGNORE_SYMBOL === $child->id ) {
				// Translate "UPDATE IGNORE" to "UPDATE OR IGNORE".
				$parts[] = 'OR IGNORE';
			} elseif (
				$is_non_strict_mode
				&& $child instanceof WP_Parser_Node
				&& ( 'insertFromConstructor' === $child->rule_name || 'insertQueryExpression' === $child->rule_name )
			) {
				$table_ref  = $node->get_first_child_node( 'tableRef' );
				$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
				$parts[]    = $this->translate_insert_or_replace_body_in_non_strict_mode( $table_name, $child );
			} else {
				$parts[] = $this->translate( $child );
			}
		}
		$query = implode( ' ', $parts );
		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows();
	}

	/**
	 * Translate and execute a MySQL UPDATE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "updateStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_update_statement( WP_Parser_Node $node ): void {
		// @TODO: Add support for UPDATE with multiple tables and JOINs.
		//        SQLite supports them in the FROM clause.

		$has_order = $node->has_child_node( 'orderClause' );
		$has_limit = $node->has_child_node( 'simpleLimitClause' );

		/*
		 * SQLite doesn't support UPDATE with ORDER BY/LIMIT.
		 * We need to use a subquery to emulate this behavior.
		 *
		 * For instance, the following query:
		 *   UPDATE t SET c = 1 WHERE c = 2 LIMIT 1;
		 * Will be rewritten to:
		 *   UPDATE t SET c = 1 WHERE rowid IN ( SELECT rowid FROM t WHERE c = 2 LIMIT 1 );
		 */
		$where_subquery = null;
		if ( $has_order || $has_limit ) {
			$where_subquery = 'SELECT rowid FROM ' . $this->translate_sequence(
				array(
					$node->get_first_child_node( 'tableReferenceList' ),
					$node->get_first_child_node( 'whereClause' ),
					$node->get_first_child_node( 'orderClause' ),
					$node->get_first_child_node( 'simpleLimitClause' ),
				)
			);
		}

		// Check if strict mode is disabled.
		$is_non_strict_mode = (
			! $this->is_sql_mode_active( 'STRICT_TRANS_TABLES' )
			&& ! $this->is_sql_mode_active( 'STRICT_ALL_TABLES' )
		);

		// Iterate and translate the update statement children.
		$parts = array();
		foreach ( $node->get_children() as $child ) {
			if ( $child instanceof WP_MySQL_Token && WP_MySQL_Lexer::IGNORE_SYMBOL === $child->id ) {
				// Translate "UPDATE IGNORE" to "UPDATE OR IGNORE".
				$parts[] = 'OR IGNORE';
			} elseif (
				$is_non_strict_mode
				&& $child instanceof WP_Parser_Node
				&& 'updateList' === $child->rule_name
			) {
				$table_ref  = $node->get_first_child_node( 'tableReferenceList' )->get_first_child_node( 'tableReference' );
				$table_name = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
				$parts[]    = $this->translate_update_list_in_non_strict_mode( $table_name, $child );
			} else {
				$parts[] = $this->translate( $child );
			}

			// When using a subquery, skip WHERE, ORDER BY, and LIMIT.
			if (
				null !== $where_subquery
				&& $child instanceof WP_Parser_Node
				&& 'updateList' === $child->rule_name
			) {
				// We can stop here, as the update statement grammar is:
				//   ... updateList whereClause? orderClause? simpleLimitClause?
				break;
			}
		}

		// Compose the update query.
		$query = implode( ' ', $parts );
		if ( null !== $where_subquery ) {
			$query .= ' WHERE rowid IN ( ' . $where_subquery . ' )';
		}

		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows();
	}

	/**
	 * Translate and execute a MySQL DELETE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "deleteStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_delete_statement( WP_Parser_Node $node ): void {
		/*
		 * Multi-table DELETE.
		 *
		 * MySQL supports multi-table DELETE statements that don't work in SQLite.
		 * These statements can have the following two flavours:
		 *  1. "DELETE t1, t2 FROM ... JOIN ... WHERE ..."
		 *  2. "DELETE FROM t1, t2 USING ... JOIN ... WHERE ..."
		 *
		 * We will rewrite such statements into a SELECT to fetch the ROWIDs of
		 * the rows to delete and then execute a DELETE statement for each table.
		 */
		$alias_ref_list = $node->get_first_child_node( 'tableAliasRefList' );
		if ( null !== $alias_ref_list ) {
			// 1. Get table aliases targeted by the DELETE statement.
			$table_aliases = array();
			foreach ( $alias_ref_list->get_child_nodes() as $alias_ref ) {
				$table_aliases[] = $this->unquote_sqlite_identifier(
					$this->translate( $alias_ref )
				);
			}

			// 2. Create an alias to table name map.
			$alias_map      = array();
			$table_ref_list = $node->get_first_child_node( 'tableReferenceList' );
			foreach ( $table_ref_list->get_descendant_nodes( 'singleTable' ) as $single_table ) {
				$alias = $this->unquote_sqlite_identifier(
					$this->translate( $single_table->get_first_child_node( 'tableAlias' ) )
				);
				$ref   = $this->unquote_sqlite_identifier(
					$this->translate( $single_table->get_first_child_node( 'tableRef' ) )
				);

				$alias_map[ $alias ] = $ref;
			}

			// 3. Compose the SELECT query to fetch ROWIDs to delete.
			$where_clause = $node->get_first_child_node( 'whereClause' );
			if ( null !== $where_clause ) {
				$where = $this->translate( $where_clause->get_first_child_node( 'expr' ) );
			}

			$select_list = array();
			foreach ( $table_aliases as $table ) {
				$select_list[] = sprintf(
					'%s.rowid AS %s',
					$this->quote_sqlite_identifier( $table ),
					$this->quote_sqlite_identifier( $table . '_rowid' )
				);
			}

			$ids = $this->execute_sqlite_query(
				sprintf(
					'SELECT %s FROM %s %s',
					implode( ', ', $select_list ),
					$this->translate( $table_ref_list ),
					isset( $where ) ? "WHERE $where" : ''
				)
			)->fetchAll( PDO::FETCH_ASSOC );

			// 4. Execute DELETE statements for each table.
			$rows = 0;
			if ( count( $ids ) > 0 ) {
				foreach ( $table_aliases as $table ) {
					$this->execute_sqlite_query(
						sprintf(
							'DELETE FROM %s AS %s WHERE rowid IN ( %s )',
							$this->quote_sqlite_identifier( $alias_map[ $table ] ),
							$this->quote_sqlite_identifier( $table ),
							implode( ', ', array_column( $ids, "{$table}_rowid" ) )
						)
					);
					$this->set_result_from_affected_rows();
					$rows += $this->last_result;
				}
			}

			$this->set_result_from_affected_rows( $rows );
			return;
		}

		// @TODO: Translate DELETE with JOIN to use a subquery.

		$query = $this->translate( $node );
		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows();
	}

	/**
	 * Translate and execute a MySQL CREATE TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "createStatement" AST node with "createTable" child.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_create_table_statement( WP_Parser_Node $node ): void {
		$subnode = $node->get_first_child_node();

		// Handle TEMPORARY keyword.
		$table_is_temporary = $subnode->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL );

		// Handle CREATE TABLE ... [AS] SELECT.
		$element_list = $subnode->get_first_child_node( 'tableElementList' );
		if ( null === $element_list ) {
			/*
			 * While SQLite supports CREATE TABLE ... AS SELECT statements,
			 * we need to somehow implement information schema support for
			 * the tables created in this way.
			 *
			 * TODO: Implement information schema support for CREATE TABLE ... AS SELECT.
			 */
			throw $this->new_not_supported_exception(
				'CREATE TABLE ... [AS] SELECT is currently not supported'
			);
		}

		// Get table name.
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $subnode->get_first_child_node( 'tableName' ) )
		);

		// Handle IF NOT EXISTS.
		if ( $subnode->has_child_node( 'ifNotExists' ) ) {
			$tables_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'tables' );
			$table_exists = $this->execute_sqlite_query(
				sprintf(
					'SELECT 1 FROM %s WHERE table_schema = ? AND table_name = ?',
					$this->quote_sqlite_identifier( $tables_table )
				),
				array( $this->db_name, $table_name )
			)->fetchColumn();

			if ( $table_exists ) {
				$this->set_result_from_affected_rows( 0 );
				return;
			}
		}

		// Save information to information schema tables.
		$this->information_schema_builder->record_create_table( $node );

		// Generate CREATE TABLE statement from the information schema tables.
		$queries            = $this->get_sqlite_create_table_statement( $table_is_temporary, $table_name );
		$create_table_query = $queries[0];
		$constraint_queries = array_slice( $queries, 1 );

		$this->execute_sqlite_query( $create_table_query );

		foreach ( $constraint_queries as $query ) {
			$this->execute_sqlite_query( $query );
		}
	}

	/**
	 * Translate and execute a MySQL ALTER TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "alterStatement" AST node with "alterTable" child.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_alter_table_statement( WP_Parser_Node $node ): void {
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_first_descendant_node( 'tableRef' ) )
		);

		$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

		// Save all column names from the original table.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_names  = $this->execute_sqlite_query(
			sprintf(
				'SELECT
					COLUMN_NAME,
					LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE
				FROM %s WHERE table_schema = ? AND table_name = ?',
				$this->quote_sqlite_identifier( $columns_table )
			),
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// Track column renames and removals.
		$column_map = array_combine(
			array_column( $column_names, 'COLUMN_NAME_LOWERCASE' ),
			array_column( $column_names, 'COLUMN_NAME' )
		);
		foreach ( $node->get_descendant_nodes( 'alterListItem' ) as $action ) {
			$first_token = $action->get_first_child_token();

			switch ( $first_token->id ) {
				case WP_MySQL_Lexer::DROP_SYMBOL:
					$name = $this->translate( $action->get_first_child_node( 'fieldIdentifier' ) );
					if ( null !== $name ) {
						$name = $this->unquote_sqlite_identifier( $name );
						unset( $column_map[ strtolower( $name ) ] );
					}
					break;
				case WP_MySQL_Lexer::CHANGE_SYMBOL:
					$old_name = $this->unquote_sqlite_identifier(
						$this->translate( $action->get_first_child_node( 'fieldIdentifier' ) )
					);
					$new_name = $this->unquote_sqlite_identifier(
						$this->translate( $action->get_first_child_node( 'identifier' ) )
					);

					$column_map[ strtolower( $old_name ) ] = $new_name;
					break;
				case WP_MySQL_Lexer::RENAME_SYMBOL:
					$column_ref = $action->get_first_child_node( 'fieldIdentifier' );
					if ( null !== $column_ref ) {
						$old_name = $this->unquote_sqlite_identifier(
							$this->translate( $column_ref )
						);
						$new_name = $this->unquote_sqlite_identifier(
							$this->translate( $action->get_first_child_node( 'identifier' ) )
						);

						$column_map[ strtolower( $old_name ) ] = $new_name;
					}
					break;
			}
		}

		$this->information_schema_builder->record_alter_table( $node );
		$this->recreate_table_from_information_schema( $table_is_temporary, $table_name, $column_map );

		// @TODO: Consider using a "fast path" for ALTER TABLE statements that
		//        consist only of operations that SQLite's ALTER TABLE supports.
	}

	/**
	 * Translate and execute a MySQL DROP TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "dropStatement" AST node with "dropTable" child.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_drop_table_statement( WP_Parser_Node $node ): void {
		// Record the changes in the information schema.
		$this->information_schema_builder->record_drop_table( $node );

		// MySQL supports removing multiple tables in a single query DROP query.
		// In SQLite, we need to execute each DROP TABLE statement separately.
		$child_node         = $node->get_first_child_node();
		$table_refs         = $child_node->get_first_child_node( 'tableRefList' )->get_child_nodes();
		$table_is_temporary = $child_node->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL );
		$queries            = array();
		foreach ( $table_refs as $table_ref ) {
			$parts = array();
			foreach ( $child_node->get_children() as $child ) {
				$is_token = $child instanceof WP_MySQL_Token;

				// Skip the TEMPORARY keyword.
				if ( $is_token && WP_MySQL_Lexer::TEMPORARY_SYMBOL === $child->id ) {
					continue;
				}

				// Replace table list with the current table reference.
				if ( ! $is_token && 'tableRefList' === $child->rule_name ) {
					// Add a "temp." schema prefix for temporary tables.
					$prefix = $table_is_temporary ? '`temp`.' : '';
					$part   = $prefix . $this->translate( $table_ref );
				} else {
					$part = $this->translate( $child );
				}

				if ( null !== $part ) {
					$parts[] = $part;
				}
			}
			$queries[] = 'DROP ' . implode( ' ', $parts );
		}

		foreach ( $queries as $query ) {
			$this->execute_sqlite_query( $query );
		}
	}

	/**
	 * Translate and execute a MySQL TRUNCATE TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "truncateTableStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_truncate_table_statement( WP_Parser_Node $node ): void {
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_first_child_node( 'tableRef' ) )
		);

		$this->execute_sqlite_query(
			sprintf( 'DELETE FROM %s', $this->quote_sqlite_identifier( $table_name ) )
		);
		try {
			$this->execute_sqlite_query( 'DELETE FROM sqlite_sequence WHERE name = ?', array( $table_name ) );
		} catch ( PDOException $e ) {
			if ( str_contains( $e->getMessage(), 'no such table' ) ) {
				// The table might not exist if no sequences are used in the DB.
			} else {
				throw $e;
			}
		}
		$this->set_result_from_affected_rows();
	}

	/**
	 * Translate and execute a MySQL CREATE INDEX statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "createStatement" AST node with "createIndex" child.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_create_index_statement( WP_Parser_Node $node ): void {
		$this->information_schema_builder->record_create_index( $node );

		$create_index = $node->get_first_child_node( 'createIndex' );
		$target       = $create_index->get_first_child_node( 'createIndexTarget' );

		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $target->get_first_child_node( 'tableRef' ) )
		);
		$index_name = $this->unquote_sqlite_identifier(
			$this->translate( $create_index->get_first_child_node( 'indexName' ) )
		);
		$is_unique  = $create_index->has_child_token( WP_MySQL_Lexer::UNIQUE_SYMBOL );

		// Get the key parts.
		$key_list_variants = $target->get_first_child_node( 'keyListVariants' );
		$key_list_nodes    = $key_list_variants->get_first_child_node()->get_child_nodes();
		foreach ( $key_list_nodes as $key_list_node ) {
			if ( 'keyPartOrExpression' === $key_list_node->rule_name ) {
				$key_part_node = $key_list_node->get_first_child();
			} else {
				$key_part_node = $key_list_node;
			}

			if ( 'keyPart' === $key_part_node->rule_name ) {
				$key_part  = $this->translate( $key_part_node->get_first_child_node( 'identifier' ) );
				$direction = $key_part_node->get_first_child_node( 'direction' );
				if ( null !== $direction ) {
					$key_part .= ' ' . $this->translate( $direction );
				}
			} else {
				$key_part = $this->translate( $key_part_node );
			}
			$key_parts[] = $key_part;
		}

		$sqlite_index_name = $this->get_sqlite_index_name( $table_name, $index_name );
		$this->execute_sqlite_query(
			sprintf(
				'CREATE %sINDEX %s ON %s (%s)',
				$is_unique ? 'UNIQUE ' : '',
				$this->quote_sqlite_identifier( $sqlite_index_name ),
				$this->translate( $target->get_first_child_node( 'tableRef' ) ),
				implode( ', ', $key_parts )
			)
		);
	}

	/**
	 * Translate and execute a MySQL DROP INDEX statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "dropStatement" AST node with "dropIndex" child.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_drop_index_statement( WP_Parser_Node $node ): void {
		$this->information_schema_builder->record_drop_index( $node );

		$drop_index = $node->get_first_child_node( 'dropIndex' );
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $drop_index->get_first_child_node( 'tableRef' ) )
		);
		$index_name = $this->unquote_sqlite_identifier(
			$this->translate( $drop_index->get_first_child_node( 'indexRef' ) )
		);

		/*
		 * In MySQL, "DROP INDEX `PRIMARY` ON <table>" removes the PRIMARY KEY.
		 * This is not supported in SQLite, so in such cases, we need to recreate
		 * the table without the PRIMARY KEY using the updated information schema.
		 */
		if ( 'PRIMARY' === strtoupper( $index_name ) ) {
			$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );
			$this->recreate_table_from_information_schema( $table_is_temporary, $table_name );
			return;
		}

		$sqlite_index_name = $this->get_sqlite_index_name( $table_name, $index_name );
		$this->execute_sqlite_query(
			sprintf(
				'DROP INDEX %s',
				$this->quote_sqlite_identifier( $sqlite_index_name )
			)
		);
	}

	/**
	 * Translate and execute a MySQL SHOW statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_show_statement( WP_Parser_Node $node ): void {
		$tokens   = $node->get_child_tokens();
		$keyword1 = $tokens[1];
		$keyword2 = $tokens[2] ?? null;

		switch ( $keyword1->id ) {
			case WP_MySQL_Lexer::COLUMNS_SYMBOL:
			case WP_MySQL_Lexer::FIELDS_SYMBOL:
				$this->execute_show_columns_statement( $node );
				break;
			case WP_MySQL_Lexer::CREATE_SYMBOL:
				if ( WP_MySQL_Lexer::TABLE_SYMBOL === $keyword2->id ) {
					$table_name = $this->unquote_sqlite_identifier(
						$this->translate( $node->get_first_child_node( 'tableRef' ) )
					);

					$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

					$sql = $this->get_mysql_create_table_statement( $table_is_temporary, $table_name );
					if ( null === $sql ) {
						$this->set_results_from_fetched_data( array() );
					} else {
						$this->set_results_from_fetched_data(
							array(
								(object) array(
									'Create Table' => $sql,
								),
							)
						);
					}
					return;
				}
				// Fall through to default.
			case WP_MySQL_Lexer::INDEX_SYMBOL:
			case WP_MySQL_Lexer::INDEXES_SYMBOL:
			case WP_MySQL_Lexer::KEYS_SYMBOL:
				$table_name = $this->unquote_sqlite_identifier(
					$this->translate( $node->get_first_child_node( 'tableRef' ) )
				);
				$this->execute_show_index_statement( $table_name );
				break;
			case WP_MySQL_Lexer::GRANTS_SYMBOL:
				$this->set_results_from_fetched_data(
					array(
						(object) array(
							'Grants for root@localhost' => 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, RELOAD, SHUTDOWN, PROCESS, FILE, REFERENCES, INDEX, ALTER, SHOW DATABASES, SUPER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, REPLICATION SLAVE, REPLICATION CLIENT, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, CREATE USER, EVENT, TRIGGER, CREATE TABLESPACE, CREATE ROLE, DROP ROLE ON *.* TO `root`@`localhost` WITH GRANT OPTION',
						),
					)
				);
				return;
			case WP_MySQL_Lexer::TABLE_SYMBOL:
				$this->execute_show_table_status_statement( $node );
				break;
			case WP_MySQL_Lexer::TABLES_SYMBOL:
				$this->execute_show_tables_statement( $node );
				break;
			case WP_MySQL_Lexer::VARIABLES_SYMBOL:
				$this->last_result = true;
				return;
			default:
				throw $this->new_not_supported_exception(
					sprintf(
						'statement type: "%s" > "%s"',
						$node->rule_name,
						$keyword1->get_value()
					)
				);
		}
	}

	/**
	 * Translate and execute a MySQL SHOW INDEX statement in SQLite.
	 *
	 * @param string $table_name The table name to show indexes for.
	 */
	private function execute_show_index_statement( string $table_name ): void {
		// TODO: FROM/IN (multiple)
		// TODO: WHERE

		$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

		/*
		 * TODO: Index naming.
		 *
		 * From the old driver:
		 *
		 * SQLite automatically assigns names to some indexes.
		 * However, dbDelta in WordPress expects the name to be
		 * the same as in the original CREATE TABLE. Let's
		 * translate the name back.
		 *
		 * The old driver does the two following conversions:
		 *   1)
		 *       $mysql_key_name = substr( $mysql_key_name, strlen( 'sqlite_autoindex_' ) );
		 *       $mysql_key_name = preg_replace( '/_[0-9]+$/', '', $mysql_key_name );
		 *   2)
		 *       $mysql_key_name = substr( $mysql_key_name, strlen( "{$table_name}__" ) );
		 */

		$statistics_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'statistics' );
		$index_info       = $this->execute_sqlite_query(
			'
				SELECT
					TABLE_NAME AS `Table`,
					NON_UNIQUE AS `Non_unique`,
					INDEX_NAME AS `Key_name`,
					SEQ_IN_INDEX AS `Seq_in_index`,
					COLUMN_NAME AS `Column_name`,
					COLLATION AS `Collation`,
					CARDINALITY AS `Cardinality`,
					SUB_PART AS `Sub_part`,
					PACKED AS `Packed`,
					NULLABLE AS `Null`,
					INDEX_TYPE AS `Index_type`,
					COMMENT AS `Comment`,
					INDEX_COMMENT AS `Index_comment`,
					IS_VISIBLE AS `Visible`,
					EXPRESSION AS `Expression`
				FROM ' . $this->quote_sqlite_identifier( $statistics_table ) . "
				WHERE table_schema = ?
				AND table_name = ?
				ORDER BY
					INDEX_NAME = 'PRIMARY' DESC,
					NON_UNIQUE = '0' DESC,
					INDEX_TYPE = 'SPATIAL' DESC,
					INDEX_TYPE = 'BTREE' DESC,
					INDEX_TYPE = 'FULLTEXT' DESC,
					ROWID,
					SEQ_IN_INDEX
			",
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_OBJ );

		$this->set_results_from_fetched_data( $index_info );
	}

	/**
	 * Translate and execute a MySQL SHOW TABLE STATUS statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_show_table_status_statement( WP_Parser_Node $node ): void {
		// FROM/IN database.
		$in_db = $node->get_first_child_node( 'inDb' );
		if ( null === $in_db ) {
			$database = $this->db_name;
		} else {
			$database = $this->unquote_sqlite_identifier(
				$this->translate( $in_db->get_first_child_node( 'identifier' ) )
			);
		}

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where );
		}

		// Fetch table information.
		$tables_tables = $this->information_schema_builder->get_table_name(
			false, // SHOW TABLE STATUS lists only non-temporary tables.
			'tables'
		);
		$table_info    = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM %s WHERE table_schema = ? %s ORDER BY table_name',
				$this->quote_sqlite_identifier( $tables_tables ),
				$condition ?? ''
			),
			array( $database )
		)->fetchAll( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			$this->set_results_from_fetched_data( array() );
		}

		// Format the results.
		$tables = array();
		foreach ( $table_info as $value ) {
			$tables[] = (object) array(
				'Name'            => $value['TABLE_NAME'],
				'Engine'          => $value['ENGINE'],
				'Version'         => $value['VERSION'],
				'Row_format'      => $value['ROW_FORMAT'],
				'Rows'            => $value['TABLE_ROWS'],
				'Avg_row_length'  => $value['AVG_ROW_LENGTH'],
				'Data_length'     => $value['DATA_LENGTH'],
				'Max_data_length' => $value['MAX_DATA_LENGTH'],
				'Index_length'    => $value['INDEX_LENGTH'],
				'Data_free'       => $value['DATA_FREE'],
				'Auto_increment'  => $value['AUTO_INCREMENT'],
				'Create_time'     => $value['CREATE_TIME'],
				'Update_time'     => $value['UPDATE_TIME'],
				'Check_time'      => $value['CHECK_TIME'],
				'Collation'       => $value['TABLE_COLLATION'],
				'Checksum'        => $value['CHECKSUM'],
				'Create_options'  => $value['CREATE_OPTIONS'],
				'Comment'         => $value['TABLE_COMMENT'],
			);
		}

		$this->set_results_from_fetched_data( $tables );
	}

	/**
	 * Translate and execute a MySQL SHOW TABLES statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_show_tables_statement( WP_Parser_Node $node ): void {
		// FROM/IN database.
		$in_db = $node->get_first_child_node( 'inDb' );
		if ( null === $in_db ) {
			$database = $this->db_name;
		} else {
			$database = $this->unquote_sqlite_identifier(
				$this->translate( $in_db->get_first_child_node( 'identifier' ) )
			);
		}

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where );
		}

		// Fetch table information.
		$table_tables = $this->information_schema_builder->get_table_name(
			false, // SHOW TABLES lists only non-temporary tables.
			'tables'
		);
		$table_info   = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM %s WHERE table_schema = ? %s ORDER BY table_name',
				$this->quote_sqlite_identifier( $table_tables ),
				$condition ?? ''
			),
			array( $database )
		)->fetchAll( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			$this->set_results_from_fetched_data( array() );
		}

		// Handle the FULL keyword.
		$command_type = $node->get_first_child_node( 'showCommandType' );
		$is_full      = $command_type && $command_type->has_child_token( WP_MySQL_Lexer::FULL_SYMBOL );

		// Format the results.
		$tables = array();
		foreach ( $table_info as $value ) {
			$table = array(
				"Tables_in_$database" => $value['TABLE_NAME'],
			);
			if ( true === $is_full ) {
				$table['Table_type'] = $value['TABLE_TYPE'];
			}
			$tables[] = (object) $table;
		}

		$this->set_results_from_fetched_data( $tables );
	}

	/**
	 * Translate and execute a MySQL SHOW COLUMNS statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 * @throws PDOException               When given table doesn't exist.
	 */
	private function execute_show_columns_statement( WP_Parser_Node $node ): void {
		// TODO: EXTENDED, FULL
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_first_child_node( 'tableRef' ) )
		);

		// FROM/IN database.
		$in_db = $node->get_first_child_node( 'inDb' );
		if ( null === $in_db ) {
			$database = $this->db_name;
		} else {
			$database = $this->unquote_sqlite_identifier(
				$this->translate( $in_db->get_first_child_node( 'identifier' ) )
			);
		}

		$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

		// Check if the table exists.
		$tables_tables = $this->information_schema_builder->get_table_name( $table_is_temporary, 'tables' );
		$table_exists  = $this->execute_sqlite_query(
			sprintf(
				'SELECT 1 FROM %s WHERE table_schema = ? AND table_name = ?',
				$this->quote_sqlite_identifier( $tables_tables )
			),
			array( $this->db_name, $table_name )
		)->fetchColumn();

		if ( ! $table_exists ) {
			throw $this->new_driver_exception(
				sprintf( "Table '%s.%s' doesn't exist", $database, $table_name ),
				'42S02'
			);
		}

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where );
		}

		// Fetch column information.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_info   = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM %s WHERE table_schema = ? AND table_name = ? %s ORDER BY ordinal_position',
				$this->quote_sqlite_identifier( $columns_table ),
				$condition ?? ''
			),
			array( $database, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		if ( false === $column_info ) {
			$this->set_results_from_fetched_data( array() );
		}

		// Format the results.
		$columns = array();
		foreach ( $column_info as $value ) {
			$column    = array(
				'Field'   => $value['COLUMN_NAME'],
				'Type'    => $value['COLUMN_TYPE'],
				'Null'    => $value['IS_NULLABLE'],
				'Key'     => $value['COLUMN_KEY'],
				'Default' => $value['COLUMN_DEFAULT'],
				'Extra'   => $value['EXTRA'],
			);
			$columns[] = (object) $column;
		}

		$this->set_results_from_fetched_data( $columns );
	}

	/**
	 * Translate and execute a MySQL DESCRIBE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "describeStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_describe_statement( WP_Parser_Node $node ): void {
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_first_child_node( 'tableRef' ) )
		);

		$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );

		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_info   = $this->execute_sqlite_query(
			'
				SELECT
					column_name AS `Field`,
					column_type AS `Type`,
					is_nullable AS `Null`,
					column_key AS `Key`,
					column_default AS `Default`,
					extra AS Extra
				FROM ' . $this->quote_sqlite_identifier( $columns_table ) . '
				WHERE table_schema = ?
				AND table_name = ?
				ORDER BY ordinal_position
			',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_OBJ );

		$this->set_results_from_fetched_data( $column_info );
	}

	/**
	 * Translate and execute a MySQL USE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "useStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_use_statement( WP_Parser_Node $node ): void {
		$database_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_first_child_node( 'identifier' ) )
		);

		if ( 'information_schema' === strtolower( $database_name ) ) {
			$this->db_name = 'information_schema';
		} elseif ( $this->db_name === $database_name ) {
			$this->db_name = $database_name;
		} else {
			throw $this->new_not_supported_exception(
				sprintf(
					"can't use schema '%s', only '%s' and 'information_schema' are supported",
					$database_name,
					$this->db_name
				)
			);
		}
	}

	/**
	 * Translate and execute a MySQL SET statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "setStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_set_statement( WP_Parser_Node $node ): void {
		/*
		 * 1. Flatten the SET statement into a single array of definitions.
		 *
		 * The grammar is non-trivial, and supports multi-statements like:
		 *   SET @var = '...', SESSION sql_mode = '...', @@GLOBAL.time_zone = '...', @@debug = '...', ...
		 *
		 * This will be flattened into a single array of grammar node lists:
		 *   [
		 *     [ <userVariable>, <equal>, <expr> ],
		 *     [ <optionType>, <internalVariableName>, <equal>, <setExprOrDefault> ],
		 *     [ <setSystemVariable>, <equal>, <setExprOrDefault> ],
		 *     [ <setSystemVariable>, <equal>, <setExprOrDefault> ],
		 *   ]
		 */
		$subnode = $node->get_first_child_node();
		if ( $subnode->has_child_node( 'optionValueNoOptionType' ) ) {
			$start_node  = $subnode->get_first_child_node( 'optionValueNoOptionType' );
			$definitions = array( $start_node->get_children() );
		} elseif ( $subnode->has_child_node( 'startOptionValueListFollowingOptionType' ) ) {
			$start_node  = $subnode
				->get_first_child_node( 'startOptionValueListFollowingOptionType' )
				->get_first_child_node( 'optionValueFollowingOptionType' ) ?? $node;
			$definitions = array(
				array_merge(
					array( $subnode->get_first_child_node( 'optionType' ) ),
					$start_node->get_children()
				),
			);
		} else {
			$definitions = array( $subnode->get_children() );
		}

		$continue_node = $subnode->get_first_child_node( 'optionValueListContinued' );
		if ( $continue_node ) {
			foreach ( $continue_node->get_child_nodes( 'optionValue' ) as $child ) {
				$node          = $child->get_first_child_node( 'optionValueNoOptionType' ) ?? $child;
				$definitions[] = $node->get_child_nodes();
			}
		}

		/*
		 * 2. Iterate and process the SET definitions.
		 *
		 * When an "optionType" node is encountered (such as "SESSION var = ..."),
		 * it's value is used for all following system variable assignments that
		 * have no type keyword specified, until the next "optionType" is found.
		 *
		 * This doesn't apply to "@@" type prefixes (such as "@@SESSION.var_name"),
		 * which always impact only the immediately following system variable.
		 */
		$default_type = WP_MySQL_Lexer::SESSION_SYMBOL;
		foreach ( $definitions as $definition ) {
			// Check if the definition starts with an "optionType" node with
			// one of the SESSION, GLOBAL, PERSIST, or PERSIST_ONLY tokens.
			$part = array_shift( $definition );
			if ( $part instanceof WP_Parser_Node && 'optionType' === $part->rule_name ) {
				$default_type = $part->get_first_child_token()->id;
				$part         = array_shift( $definition );
			}

			if (
				$part instanceof WP_Parser_Node
				&& (
					'internalVariableName' === $part->rule_name
					|| 'setSystemVariable' === $part->rule_name
				)
			) {
				array_shift( $definition ); // Remove the '='.
				$value = array_shift( $definition );
				$this->execute_set_system_variable_statement( $part, $value, $default_type );
			} else {
				// TODO: Support user variables (in-memory or a temporary table).
				throw $this->new_not_supported_exception(
					sprintf( 'SET statement: %s', $node->rule_name )
				);
			}
		}

		$this->last_result = 0;
	}

	/**
	 * Translate and execute a MySQL SET statement for system variables.
	 *
	 * @param  WP_Parser_Node $set_var_node  The "internalVariableName" or "setSystemVariable" AST node.
	 * @param  WP_Parser_Node $value_node    The "setExprOrDefault" AST node.
	 * @param  int            $default_type  The currently active default variable type.
	 *                                       One of the SESSION, GLOBAL, PERSIST, PERSIST_ONLY tokens.
	 * @throws WP_SQLite_Driver_Exception    When the query execution fails.
	 */
	private function execute_set_system_variable_statement(
		WP_Parser_Node $set_var_node,
		WP_Parser_Node $value_node,
		int $default_type
	): void {
		// Get the variable name.
		$internal_variable_name = 'setSystemVariable' === $set_var_node->rule_name
			? $set_var_node->get_first_child_node( 'internalVariableName' )
			: $set_var_node;

		$name = strtolower(
			$this->unquote_sqlite_identifier(
				$this->translate( $internal_variable_name )
			)
		);

		// Get the type attribute (one of SESSION, GLOBAL, PERSIST, PERSIST_ONLY).
		$type = $default_type;
		if ( $set_var_node->has_child_node( 'setVarIdentType' ) ) {
			$var_ident_type = $set_var_node->get_first_child_node( 'setVarIdentType' );
			$type           = $var_ident_type->get_first_child_token()->id;
		}

		// Get the variable value.
		$value = $this->translate( $value_node );
		$value = str_replace( "''", "'", $value );
		$value = substr( $value, 1, -1 );

		if ( WP_MySQL_Lexer::SESSION_SYMBOL === $type ) {
			if ( 'sql_mode' === $name ) {
				$modes                  = explode( ',', strtoupper( $value ) );
				$this->active_sql_modes = $modes;
			}
		} elseif ( WP_MySQL_Lexer::GLOBAL_SYMBOL === $type ) {
			throw $this->new_not_supported_exception( "SET statement type: 'GLOBAL'" );
		} elseif ( WP_MySQL_Lexer::PERSIST_SYMBOL === $type ) {
			throw $this->new_not_supported_exception( "SET statement type: 'PERSIST'" );
		} elseif ( WP_MySQL_Lexer::PERSIST_ONLY_SYMBOL === $type ) {
			throw $this->new_not_supported_exception( "SET statement type: 'PERSIST_ONLY'" );
		}

		// TODO: Handle GLOBAL, PERSIST, and PERSIST_ONLY types.
	}

	/**
	 * Translate and execute a MySQL administration statement in SQLite.
	 *
	 * This emulates the following MySQL statements:
	 *  - ANALYZE TABLE
	 *  - CHECK TABLE
	 *  - OPTIMIZE TABLE
	 *  - REPAIR TABLE
	 *
	 * @param  WP_Parser_Node $node       A "tableAdministrationStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_administration_statement( WP_Parser_Node $node ): void {
		$first_token    = $node->get_first_child_token();
		$table_ref_list = $node->get_first_child_node( 'tableRefList' );
		$results        = array();
		foreach ( $table_ref_list->get_child_nodes( 'tableRef' ) as $table_ref ) {
			$table_name        = $this->unquote_sqlite_identifier( $this->translate( $table_ref ) );
			$quoted_table_name = $this->quote_sqlite_identifier( $table_name );
			try {
				switch ( $first_token->id ) {
					case WP_MySQL_Lexer::ANALYZE_SYMBOL:
						$stmt   = $this->execute_sqlite_query( sprintf( 'ANALYZE %s', $quoted_table_name ) );
						$errors = $stmt->fetchAll( PDO::FETCH_COLUMN );
						break;
					case WP_MySQL_Lexer::CHECK_SYMBOL:
						$stmt   = $this->execute_sqlite_query(
							sprintf( 'PRAGMA integrity_check(%s)', $quoted_table_name )
						);
						$errors = $stmt->fetchAll( PDO::FETCH_COLUMN );
						if ( 'ok' === $errors[0] ) {
							array_shift( $errors );
						}
						break;
					case WP_MySQL_Lexer::OPTIMIZE_SYMBOL:
					case WP_MySQL_Lexer::REPAIR_SYMBOL:
						/*
						 * SQLite doesn't support OPTIMIZE and REPAIR TABLE commands.
						 * We will recreate the table and copy the data instead.
						 * This corresponds to older MySQL OPTIMIZE TABLE behavior
						 * and still applies to some storage engines in some cases.
						 */
						$table_is_temporary = $this->information_schema_builder->temporary_table_exists( $table_name );
						$this->recreate_table_from_information_schema( $table_is_temporary, $table_name );
						$errors = array();
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$first_token->get_value()
							)
						);
				}
			} catch ( PDOException $e ) {
				if ( 'HY000' === $e->getCode() ) {
					$errors = array( "Table '$table_name' doesn't exist" );
				} else {
					$errors = array( $e->getMessage() );
				}
			}

			$operation = strtolower( $first_token->get_value() );
			foreach ( $errors as $error ) {
				$results[] = (object) array(
					'Table'    => $this->db_name . '.' . $table_name,
					'Op'       => $operation,
					'Msg_type' => 'Error',
					'Msg_text' => $error,
				);
			}
			$results[] = (object) array(
				'Table'    => $this->db_name . '.' . $table_name,
				'Op'       => $operation,
				'Msg_type' => 'status',
				'Msg_text' => count( $errors ) > 0 ? 'Operation failed' : 'OK',
			);
		}
		$this->set_results_from_fetched_data( $results );
	}

	/**
	 * Translate a MySQL AST node or token to an SQLite query fragment.
	 *
	 * @param  WP_Parser_Node|WP_MySQL_Token $node The AST node to translate.
	 * @return string|null                         The translated query fragment.
	 * @throws WP_SQLite_Driver_Exception          When the translation fails.
	 */
	private function translate( $node ): ?string {
		if ( null === $node ) {
			return null;
		}

		if ( $node instanceof WP_MySQL_Token ) {
			return $this->translate_token( $node );
		}

		if ( ! $node instanceof WP_Parser_Node ) {
			throw $this->new_driver_exception(
				sprintf(
					'Expected a WP_Parser_Node or WP_MySQL_Token instance, got: %s',
					gettype( $node )
				)
			);
		}

		$rule_name = $node->rule_name;
		switch ( $rule_name ) {
			case 'querySpecification':
				// Translate "HAVING ..." without "GROUP BY ..." to "GROUP BY 1 HAVING ...".
				if ( $node->has_child_node( 'havingClause' ) && ! $node->has_child_node( 'groupByClause' ) ) {
					$parts = array();
					foreach ( $node->get_children() as $child ) {
						if ( $child instanceof WP_Parser_Node && 'havingClause' === $child->rule_name ) {
							$parts[] = 'GROUP BY 1';
						}
						$part = $this->translate( $child );
						if ( null !== $part ) {
							$parts[] = $part;
						}
					}
					return implode( ' ', $parts );
				}
				return $this->translate_sequence( $node->get_children() );
			case 'qualifiedIdentifier':
			case 'tableRefWithWildcard':
				$parts = $node->get_descendant_nodes( 'identifier' );
				if ( count( $parts ) === 2 ) {
					return $this->translate_qualified_identifier( $parts[0], $parts[1] );
				}
				return $this->translate_qualified_identifier( null, $parts[0] );
			case 'fieldIdentifier':
			case 'simpleIdentifier':
				$parts = $node->get_descendant_nodes( 'identifier' );
				if ( count( $parts ) === 3 ) {
					return $this->translate_qualified_identifier( $parts[0], $parts[1], $parts[2] );
				} elseif ( count( $parts ) === 2 ) {
					return $this->translate_qualified_identifier( null, $parts[0], $parts[1] );
				}
				return $this->translate_qualified_identifier( null, null, $parts[0] );
			case 'tableWild':
				$parts = $node->get_descendant_nodes( 'identifier' );
				if ( count( $parts ) === 2 ) {
					return $this->translate_qualified_identifier( $parts[0], $parts[1] ) . '.*';
				}
				return $this->translate_qualified_identifier( null, $parts[0] ) . '.*';
			case 'dotIdentifier':
				return $this->translate_sequence( $node->get_children(), '' );
			case 'identifierKeyword':
				return '`' . $this->translate( $node->get_first_child() ) . '`';
			case 'pureIdentifier':
				$value = $this->translate_pure_identifier( $node );

				/*
				 * At the moment, we only support ASCII bytes in all identifiers.
				 * This is because SQLite doesn't support case-insensitive Unicode
				 * character matching: https://sqlite.org/faq.html#q18
				 */
				for ( $i = 0; $i < strlen( $value ); $i++ ) {
					if ( ord( $value[ $i ] ) > 127 ) {
						throw $this->new_driver_exception(
							'The SQLite driver only supports ASCII characters in identifiers.'
						);
					}
				}
				return $value;
			case 'textStringLiteral':
				return $this->translate_string_literal( $node );
			case 'dataType':
			case 'nchar':
				$child = $node->get_first_child();
				if ( $child instanceof WP_Parser_Node ) {
					return $this->translate( $child );
				}

				// Handle optional prefixes (data type is the second token):
				//  1. LONG VARCHAR, LONG CHAR(ACTER) VARYING, LONG VARBINARY.
				//  2. NATIONAL CHAR, NATIONAL VARCHAR, NATIONAL CHAR(ACTER) VARYING.
				if ( WP_MySQL_Lexer::LONG_SYMBOL === $child->id ) {
					$child = $node->get_child_tokens()[1] ?? null;
				} elseif ( WP_MySQL_Lexer::NATIONAL_SYMBOL === $child->id ) {
					$child = $node->get_child_tokens()[1] ?? null;
				}

				if ( null === $child ) {
					throw $this->new_invalid_input_exception();
				}

				$type_token = self::DATA_TYPE_MAP[ $child->id ] ?? null;
				if ( null !== $type_token ) {
					return $type_token;
				}

				// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
				if ( WP_MySQL_Lexer::SERIAL_SYMBOL === $child->id ) {
					return 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE';
				}

				// @TODO: Handle SET and JSON.
				throw $this->new_not_supported_exception(
					sprintf( 'data type: %s', $child->get_value() )
				);
			case 'fromClause':
				// FROM DUAL is MySQL-specific syntax that means "FROM no tables"
				// and it is equivalent to omitting the FROM clause entirely.
				if ( $node->has_child_token( WP_MySQL_Lexer::DUAL_SYMBOL ) ) {
					return null;
				}
				return $this->translate_sequence( $node->get_children() );
			case 'insertUpdateList':
				// Translate "ON DUPLICATE KEY UPDATE" to "ON CONFLICT DO UPDATE SET".
				return sprintf(
					'ON CONFLICT DO UPDATE SET %s',
					$this->translate( $node->get_first_child_node( 'updateList' ) )
				);
			case 'simpleExpr':
				return $this->translate_simple_expr( $node );
			case 'predicateOperations':
				$token = $node->get_first_child_token();
				if ( WP_MySQL_Lexer::LIKE_SYMBOL === $token->id ) {
					return $this->translate_like( $node );
				} elseif ( WP_MySQL_Lexer::REGEXP_SYMBOL === $token->id ) {
					return $this->translate_regexp_functions( $node );
				}
				return $this->translate_sequence( $node->get_children() );
			case 'runtimeFunctionCall':
				return $this->translate_runtime_function_call( $node );
			case 'functionCall':
				return $this->translate_function_call( $node );
			case 'systemVariable':
				$var_ident_type = $node->get_first_child_node( 'varIdentType' );
				$type_token     = $var_ident_type ? $var_ident_type->get_first_child_token() : null;
				$original_name  = $this->unquote_sqlite_identifier(
					$this->translate( $node->get_first_child_node( 'textOrIdentifier' ) )
				);

				$name = strtolower( $original_name );
				$type = $type_token ? $type_token->id : WP_MySQL_Lexer::SESSION_SYMBOL;
				if ( 'sql_mode' === $name ) {
					$value = $this->connection->quote( implode( ',', $this->active_sql_modes ) );
				} else {
					// When we have no value, it's reasonable to use NULL.
					$value = 'NULL';
				}

				// @TODO: Emulate more system variables, or use reasonable defaults.
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variable-reference.html
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html

				// TODO: Original name should come from the original MySQL input,
				//       exactly as it was written by the user, and not translated.

				// TODO: The '% AS %' syntax is compatible with SELECT lists only.
				//       We need to translate it differently when used as a value.
				return sprintf(
					'%s AS %s',
					$value,
					$this->quote_sqlite_identifier(
						'@@' . ( $type_token ? "{$type_token->get_value()}." : '' ) . $original_name
					)
				);
			case 'castType':
				// Translate "CAST(... AS BINARY)" to "CAST(... AS BLOB)".
				if ( $node->has_child_token( WP_MySQL_Lexer::BINARY_SYMBOL ) ) {
					return 'BLOB';
				}
				return $this->translate_sequence( $node->get_children() );
			case 'defaultCollation':
				// @TODO: Check and save in information schema.
				return null;
			case 'duplicateAsQueryExpression':
				// @TODO: How to handle IGNORE/REPLACE?

				// The "AS" keyword is optional in MySQL, but required in SQLite.
				return 'AS ' . $this->translate( $node->get_first_child_node() );
			case 'indexHint':
			case 'indexHintList':
				return null;
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Translate a MySQL token to SQLite.
	 *
	 * @param  WP_MySQL_Token $token The MySQL token to translate.
	 * @return string|null           The translated value.
	 */
	private function translate_token( WP_MySQL_Token $token ): ?string {
		switch ( $token->id ) {
			case WP_MySQL_Lexer::EOF:
				return null;
			case WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL:
				return 'AUTOINCREMENT';
			case WP_MySQL_Lexer::BINARY_SYMBOL:
				/*
				 * There is no "BINARY expr" equivalent in SQLite. We look for the
				 * keyword from a higher level to respect it in particular cases
				 * (REGEXP, LIKE, etc.) and then remove it from the output here.
				 */
				return null;
			case WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL:
				/*
				 * The "SQL_CALC_FOUND_ROWS" keyword is implemented in the select
				 * statement translation and then removed from the output here.
				 */
				return null;
			default:
				return $token->get_value();
		}
	}

	/**
	 * Translate a sequence of MySQL AST nodes to SQLite.
	 *
	 * @param  array<WP_Parser_Node|WP_MySQL_Token> $nodes     The MySQL token to translate.
	 * @param  string                               $separator The separator to use between fragments.
	 * @return string|null                                     The translated value.
	 * @throws WP_SQLite_Driver_Exception                      When the translation fails.
	 */
	private function translate_sequence( array $nodes, string $separator = ' ' ): ?string {
		$parts = array();
		foreach ( $nodes as $node ) {
			if ( null === $node ) {
				continue;
			}

			$translated = $this->translate( $node );
			if ( null === $translated ) {
				continue;
			}
			$parts[] = $translated;
		}
		if ( 0 === count( $parts ) ) {
			return null;
		}
		return implode( $separator, $parts );
	}

	/**
	 * Translate a MySQL string literal to SQLite.
	 *
	 * @param  WP_Parser_Node $node The "textStringLiteral" AST node.
	 * @return string               The translated value.
	 */
	private function translate_string_literal( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();
		$value = $token->get_value();

		/*
		 * 5. Translate datetime literals.
		 *
		 * Process only strings that could possibly represent a datetime
		 * literal ("YYYY-MM-DDTHH:MM:SS", "YYYY-MM-DDTHH:MM:SSZ", etc.).
		 */
		if ( strlen( $value ) >= 19 && is_numeric( $value[0] ) ) {
			$value = $this->translate_datetime_literal( $value );
		}

		/*
		 * 6. Handle null characters.
		 *
		 * SQLite doesn't fully support null characters (\u0000) in strings.
		 * However, it can store them and read them, with some limitations.
		 *
		 * In PHP, null bytes are often produced by the serialize() function.
		 * Removing them would damage the serialized data.
		 *
		 * There is no way to store null bytes using a string literal, so we
		 * need to split the string and concatenate null bytes with its parts.
		 * This will convert literals will null bytes to expressions.
		 *
		 * Alternatively, we could replace string literals with parameters and
		 * pass them using prepared statements. However, that's not universally
		 * applicable for all string literals (e.g., in default column values).
		 *
		 * See:
		 *   https://www.sqlite.org/nulinstr.html
		 */
		$parts = array();
		foreach ( explode( "\0", $value ) as $segment ) {
			// Escape and quote each segment.
			$parts[] = "'" . str_replace( "'", "''", $segment ) . "'";
		}
		if ( count( $parts ) > 1 ) {
			return '(' . implode( ' || CHAR(0) || ', $parts ) . ')';
		}
		return $parts[0];
	}

	/**
	 * Translate a MySQL pure identifier to SQLite.
	 *
	 * @param  WP_Parser_Node $node The "pureIdentifier" AST node.
	 * @return string               The translated value.
	 */
	private function translate_pure_identifier( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();
		$value = $token->get_value();
		return '`' . str_replace( '`', '``', $value ) . '`';
	}

	/**
	 * Translate a qualified MySQL identifier to SQLite.
	 *
	 * The identifier can be composed of 1 to 3 parts (schema, object, child).
	 *
	 * @param  WP_Parser_Node|null $schema_node An identifier node representing a schema name (database).
	 * @param  WP_Parser_Node|null $object_node An identifier node representing a database-level object name
	 *                                          (table, view, procedure, trigger, etc.).
	 * @param  WP_Parser_Node|null $child_node  An identifier node representing an object child name (column, index, etc.).
	 * @return string                           The translated value.
	 * @throws WP_SQLite_Driver_Exception       When the translation fails.
	 */
	private function translate_qualified_identifier(
		?WP_Parser_Node $schema_node,
		?WP_Parser_Node $object_node = null,
		?WP_Parser_Node $child_node = null
	): string {
		$parts                = array();
		$uses_reserved_prefix = false;

		// Database name.
		$is_information_schema = 'information_schema' === $this->db_name;
		if ( null !== $schema_node ) {
			$schema_name = $this->unquote_sqlite_identifier(
				$this->translate_sequence( $schema_node->get_children() )
			);
			if ( 'information_schema' === strtolower( $schema_name ) ) {
				$is_information_schema = true;
			} elseif ( $this->db_name === $schema_name ) {
				$is_information_schema = false;
			} else {
				throw $this->new_not_supported_exception(
					sprintf(
						"can't use schema '%s', only '%s' and 'information_schema' are supported",
						$schema_name,
						$this->db_name
					)
				);
			}
		}

		/*
		 * Make the 'information_schema' database read-only.
		 *
		 * This basic approach is rather restrictive, as it blocks the usage
		 * of information schema tables in all data-modifying statements.
		 *
		 * Some of these statements can be valid, when the schema is only read:
		 *   DELETE t FROM t JOIN information_schema.columns c ON ...
		 *
		 * If needed, a more granular approach can be implemented in the future.
		 */
		if ( true === $is_information_schema && false === $this->is_readonly ) {
			throw $this->new_driver_exception(
				"Access denied for user 'sqlite'@'%' to database 'information_schema'",
				'42000'
			);
		}

		// Database-level object name (table, view, procedure, trigger, etc.).
		if ( null !== $object_node ) {
			if ( $is_information_schema ) {
				$object_name = $this->unquote_sqlite_identifier(
					$this->translate_sequence( $object_node->get_children() )
				);
				$parts[]     = $this->information_schema_builder->get_table_name( false, $object_name );
			} else {
				$quoted_object_name = $this->translate( $object_node );
				$object_name        = $this->unquote_sqlite_identifier( $quoted_object_name );
				if ( str_starts_with( $object_name, self::RESERVED_PREFIX ) ) {
					$uses_reserved_prefix = true;
				}
				$parts[] = $quoted_object_name;
			}
		}

		// Object child name (column, index, etc.).
		if ( null !== $child_node ) {
			$quoted_object_name = $this->translate( $child_node );
			$object_name        = $this->unquote_sqlite_identifier( $quoted_object_name );
			if ( str_starts_with( $object_name, self::RESERVED_PREFIX ) ) {
				$uses_reserved_prefix = true;
			}
			$parts[] = $quoted_object_name;
		}

		$identifier = implode( '.', $parts );

		if ( true === $uses_reserved_prefix ) {
			throw $this->new_driver_exception(
				sprintf(
					"Invalid identifier %s, prefix '%s' is reserved",
					$identifier,
					self::RESERVED_PREFIX
				)
			);
		}

		return $identifier;
	}

	/**
	 * Translate a MySQL simple expression to SQLite.
	 *
	 * @param WP_Parser_Node $node        The "simpleExpr" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_simple_expr( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();

		// Translate "VALUES(col)" to "excluded.col" in ON DUPLICATE KEY UPDATE.
		if ( null !== $token && WP_MySQL_Lexer::VALUES_SYMBOL === $token->id ) {
			return sprintf(
				'`excluded`.%s',
				$this->translate( $node->get_first_child_node( 'simpleIdentifier' ) )
			);
		}

		return $this->translate_sequence( $node->get_children() );
	}

	/**
	 * Translate a MySQL LIKE expression to SQLite.
	 *
	 * @param WP_Parser_Node $node        The "predicateOperations" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_like( WP_Parser_Node $node ): string {
		$tokens    = $node->get_descendant_tokens();
		$is_binary = isset( $tokens[1] ) && WP_MySQL_Lexer::BINARY_SYMBOL === $tokens[1]->id;

		if ( true === $is_binary ) {
			$children = $node->get_children();
			return sprintf(
				'GLOB _helper_like_to_glob_pattern(%s)',
				$this->translate( $children[1] )
			);
		}

		/*
		 * @TODO: Implement the ESCAPE '...' clause.
		 */

		/*
		 * @TODO: Implement more correct LIKE behavior.
		 *
		 * While SQLite supports the LIKE operator, it seems to differ from the
		 * MySQL behavior in some ways:
		 *
		 *  1. In SQLite, LIKE is case-insensitive only for ASCII characters
		 *     ('a' LIKE 'A' is TRUE but 'æ' LIKE 'Æ' is FALSE)
		 *  2. In MySQL, LIKE interprets some escape sequences. See the contents
		 *     of the "_helper_like_to_glob_pattern" function.
		 *
		 * We'll probably need to overload the like() function:
		 *   https://www.sqlite.org/lang_corefunc.html#like
		 */
		$statement = $this->translate_sequence( $node->get_children() );
		if ( $this->is_sql_mode_active( 'NO_BACKSLASH_ESCAPES' ) ) {
			return $statement;
		}
		return $statement . " ESCAPE '\\'";
	}

	/**
	 * Translate MySQL REGEXP expression to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "predicateOperations" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_regexp_functions( WP_Parser_Node $node ): string {
		$tokens    = $node->get_descendant_tokens();
		$is_binary = isset( $tokens[1] ) && WP_MySQL_Lexer::BINARY_SYMBOL === $tokens[1]->id;

		/*
		 * If the query says REGEXP BINARY, the comparison is byte-by-byte
		 * and letter casing matters – lowercase and uppercase letters are
		 * represented using different byte codes.
		 *
		 * The REGEXP function can't be easily made to accept two
		 * parameters, so we'll have to use a hack to get around this.
		 *
		 * If the first character of the pattern is a null byte, we'll
		 * remove it and make the comparison case-sensitive. This should
		 * be reasonably safe since PHP does not allow null bytes in
		 * regular expressions anyway.
		 */
		if ( true === $is_binary ) {
			return 'REGEXP CHAR(0) || ' . $this->translate( $node->get_first_child_node() );
		}
		return 'REGEXP ' . $this->translate( $node->get_first_child_node() );
	}

	/**
	 * Translate a MySQL runtime function call to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "runtimeFunctionCall" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_runtime_function_call( WP_Parser_Node $node ): string {
		$child = $node->get_first_child();
		if ( $child instanceof WP_Parser_Node ) {
			return $this->translate( $child );
		}

		switch ( $child->id ) {
			case WP_MySQL_Lexer::CURRENT_TIMESTAMP_SYMBOL:
			case WP_MySQL_Lexer::NOW_SYMBOL:
				/*
				 * 1) SQLite doesn't support CURRENT_TIMESTAMP() with parentheses.
				 * 2) In MySQL, CURRENT_TIMESTAMP and CURRENT_TIMESTAMP() are an
				 *    alias of NOW(). In SQLite, there is no NOW() function.
				 */
				return 'CURRENT_TIMESTAMP';
			case WP_MySQL_Lexer::DATE_ADD_SYMBOL:
			case WP_MySQL_Lexer::DATE_SUB_SYMBOL:
				$nodes = $node->get_child_nodes();
				$value = $this->translate( $nodes[1] );
				$unit  = $this->translate( $nodes[2] );
				if ( 'WEEK' === $unit ) {
					$unit  = 'DAY';
					$value = 7 * $value;
				}
				return sprintf(
					"DATETIME(%s, '%s' || %s || ' %s')",
					$this->translate( $nodes[0] ),
					WP_MySQL_Lexer::DATE_SUB_SYMBOL === $child->id ? '-' : '+',
					$value,
					$unit
				);
			case WP_MySQL_Lexer::LEFT_SYMBOL:
				$nodes = $node->get_child_nodes();
				return sprintf(
					'SUBSTRING(%s, 1, %s)',
					$this->translate( $nodes[0] ),
					$this->translate( $nodes[1] )
				);
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Translate a MySQL function call to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "functionCall" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_function_call( WP_Parser_Node $node ): string {
		$nodes = $node->get_child_nodes();
		$name  = strtoupper(
			$this->unquote_sqlite_identifier( $this->translate( $nodes[0] ) )
		);

		$args = array();
		if ( isset( $nodes[1] ) ) {
			foreach ( $nodes[1]->get_child_nodes() as $child ) {
				$args[] = $this->translate( $child );
			}
		}

		switch ( $name ) {
			case 'DATE_FORMAT':
				list ( $date, $mysql_format ) = $args;

				$format = strtr( $mysql_format, self::MYSQL_DATE_FORMAT_TO_SQLITE_STRFTIME_MAP );
				if ( ! $format ) {
					throw $this->new_driver_exception(
						sprintf(
							'Could not translate a DATE_FORMAT() format to STRFTIME format (%s)',
							$mysql_format
						)
					);
				}

				/*
				 * MySQL supports comparing strings and floats, e.g.
				 *
				 * > SELECT '00.42' = 0.4200
				 * 1
				 *
				 * SQLite does not support that. At the same time,
				 * WordPress likes to filter dates by comparing numeric
				 * outputs of DATE_FORMAT() to floats, e.g.:
				 *
				 *     -- Filter by hour and minutes
				 *     DATE_FORMAT(
				 *         STR_TO_DATE('2014-10-21 00:42:29', '%Y-%m-%d %H:%i:%s'),
				 *         '%H.%i'
				 *     ) = 0.4200;
				 *
				 * Let's cast the STRFTIME() output to a float if
				 * the date format is typically used for string
				 * to float comparisons.
				 *
				 * In the future, let's update WordPress to avoid comparing
				 * strings and floats.
				 */
				$cast_to_float = "'%H.%i'" === $mysql_format;
				if ( true === $cast_to_float ) {
					return sprintf( 'CAST(STRFTIME(%s, %s) AS FLOAT)', $format, $date );
				}
				return sprintf( 'STRFTIME(%s, %s)', $format, $date );
			case 'CHAR_LENGTH':
				// @TODO LENGTH and CHAR_LENGTH aren't always the same in MySQL for utf8 characters.
				return 'LENGTH(' . $args[0] . ')';
			case 'CONCAT':
				return '(' . implode( ' || ', $args ) . ')';
			case 'FOUND_ROWS':
				// @TODO: The following implementation with an alias assumes
				//        that the function is used in the SELECT field list.
				//        For compatibility with more complex use cases, it may
				//        be better to register it as a custom SQLite function.
				$found_rows = $this->last_sql_calc_found_rows;
				if ( null === $found_rows && is_array( $this->last_result ) ) {
					$found_rows = count( $this->last_result );
				}
				return sprintf( "(SELECT %d) AS 'FOUND_ROWS()'", $found_rows );
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Translate a MySQL datetime literal to SQLite.
	 *
	 * @param  string $value The MySQL datetime literal.
	 * @return string        The translated value.
	 */
	private function translate_datetime_literal( string $value ): string {
		/*
		 * The code below converts the date format to one preferred by SQLite.
		 *
		 * MySQL accepts ISO 8601 date strings:        'YYYY-MM-DDTHH:MM:SSZ'
		 * SQLite prefers a slightly different format: 'YYYY-MM-DD HH:MM:SS'
		 *
		 * SQLite date and time functions can understand the ISO 8601 notation, but
		 * lookups don't. To keep the lookups working, we need to store all dates
		 * in UTC without the "T" and "Z" characters.
		 *
		 * Caveat: It will adjust every string that matches the pattern, not just dates.
		 *
		 * In theory, we could only adjust semantic dates, e.g. the data inserted
		 * to a date column or compared against a date column.
		 *
		 * In practice, this is hard because dates are just text – SQLite has no separate
		 * datetime field. We'd need to cache the MySQL data type from the original
		 * CREATE TABLE query and then keep refreshing the cache after each ALTER TABLE query.
		 *
		 * That's a lot of complexity that's perhaps not worth it. Let's just convert
		 * everything for now. The regexp assumes "Z" is always at the end of the string,
		 * which is true in the unit test suite, but there could also be a timezone offset
		 * like "+00:00" or "+01:00". We could add support for that later if needed.
		 */
		if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})Z$/', $value, $matches ) ) {
			$value = $matches[1] . ' ' . $matches[2];
		}

		/*
		 * Mimic MySQL's behavior and truncate invalid dates.
		 *
		 * "2020-12-41 14:15:27" becomes "0000-00-00 00:00:00"
		 *
		 * WARNING: We have no idea whether the truncated value should
		 * be treated as a date in the first place.
		 * In SQLite dates are just strings. This could be a perfectly
		 * valid string that just happens to contain a date-like value.
		 *
		 * At the same time, WordPress seems to rely on MySQL's behavior
		 * and even tests for it in Tests_Post_wpInsertPost::test_insert_empty_post_date.
		 * Let's truncate the dates for now.
		 *
		 * In the future, let's update WordPress to do its own date validation
		 * and stop relying on this MySQL feature,
		 */
		if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})$/', $value, $matches ) ) {
			/*
			 * Calling strtotime("0000-00-00 00:00:00") in 32-bit environments triggers
			 * an "out of integer range" warning – let's avoid that call for the popular
			 * case of "zero" dates.
			 */
			if ( '0000-00-00 00:00:00' !== $value && false === strtotime( $value ) ) {
				$value = '0000-00-00 00:00:00';
			}
		}
		return $value;
	}

	/**
	 * Recreate an existing table using data in the information schema.
	 *
	 * This is used for a generic support of ALTER TABLE queries, as well as
	 * for some other statements like OPTIMIZE TABLE and REPAIR TABLE.
	 *
	 * See:
	 *   https://www.sqlite.org/lang_altertable.html#making_other_kinds_of_table_schema_changes
	 *
	 * @param  bool   $table_is_temporary Whether the table is temporary.
	 * @param  string $table_name         The name of the table to recreate.
	 * @param  array  $column_map         Optional. A map of column names (old name -> new name)
	 *                                    to use when copying data from the original table.
	 *                                    When not provided, all columns are copied without renaming.
	 * @throws WP_SQLite_Driver_Exception
	 */
	private function recreate_table_from_information_schema(
		bool $table_is_temporary,
		string $table_name,
		?array $column_map = null
	): void {
		if ( null === $column_map ) {
			$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
			$column_names  = $this->execute_sqlite_query(
				sprintf(
					'SELECT COLUMN_NAME FROM %s WHERE table_schema = ? AND table_name = ?',
					$this->quote_sqlite_identifier( $columns_table )
				),
				array( $this->db_name, $table_name )
			)->fetchAll( PDO::FETCH_COLUMN );
			$column_map    = array_combine( $column_names, $column_names );
		}

		// Preserve ROWIDs.
		// This also addresses a special case when all original columns are dropped
		// and there is nothing to copy. We'll always have at least the ROWID column.
		$column_map = array( 'rowid' => 'rowid' ) + $column_map;

		/*
		 * See:
		 *   https://www.sqlite.org/lang_altertable.html#making_other_kinds_of_table_schema_changes
		 */

		// 1. If foreign key constraints are enabled, disable them.
		$pragma_foreign_keys = $this->execute_sqlite_query( 'PRAGMA foreign_keys' )->fetchColumn();
		$this->execute_sqlite_query( 'PRAGMA foreign_keys = OFF' );

		// 2. Create a new table with the new schema.
		$tmp_table_name        = self::RESERVED_PREFIX . "tmp_{$table_name}_" . uniqid();
		$quoted_table_name     = $this->quote_sqlite_identifier( $table_name );
		$quoted_tmp_table_name = $this->quote_sqlite_identifier( $tmp_table_name );
		$queries               = $this->get_sqlite_create_table_statement( $table_is_temporary, $table_name, $tmp_table_name );
		$create_table_query    = $queries[0];
		$constraint_queries    = array_slice( $queries, 1 );
		$this->execute_sqlite_query( $create_table_query );

		// 3. Copy data from the original table to the new table.
		$this->execute_sqlite_query(
			sprintf(
				'INSERT INTO %s (%s) SELECT %s FROM %s',
				$quoted_tmp_table_name,
				implode(
					', ',
					array_map( array( $this, 'quote_sqlite_identifier' ), $column_map )
				),
				implode(
					', ',
					array_map( array( $this, 'quote_sqlite_identifier' ), array_keys( $column_map ) )
				),
				$quoted_table_name
			)
		);

		// 4. Drop the original table.
		$this->execute_sqlite_query( sprintf( 'DROP TABLE %s', $quoted_table_name ) );

		// 5. Rename the new table to the original table name.
		$this->execute_sqlite_query(
			sprintf(
				'ALTER TABLE %s RENAME TO %s',
				$quoted_tmp_table_name,
				$quoted_table_name
			)
		);

		// 6. Reconstruct indexes, triggers, and views.
		foreach ( $constraint_queries as $query ) {
			$this->execute_sqlite_query( $query );
		}

		// 7. If foreign key constraints were enabled, verify and enable them.
		if ( '1' === $pragma_foreign_keys ) {
			$this->execute_sqlite_query( 'PRAGMA foreign_key_check' );
			$this->execute_sqlite_query( 'PRAGMA foreign_keys = ON' );
		}

		// @TODO: Triggers and views.
	}

	/**
	 * Translate a MySQL SHOW LIKE ... or SHOW WHERE ... condition to SQLite.
	 *
	 * @param  WP_Parser_Node $like_or_where The "likeOrWhere" AST node.
	 * @return string                        The translated value.
	 * @throws WP_SQLite_Driver_Exception    When the translation fails.
	 */
	private function translate_show_like_or_where_condition( WP_Parser_Node $like_or_where ): string {
		$like_clause = $like_or_where->get_first_child_node( 'likeClause' );
		if ( null !== $like_clause ) {
			$value = $this->translate(
				$like_clause->get_first_child_node( 'textStringLiteral' )
			);
			return sprintf( "AND table_name LIKE %s ESCAPE '\\'", $value );
		}

		$where_clause = $like_or_where->get_first_child_node( 'whereClause' );
		if ( null !== $where_clause ) {
			$value = $this->translate(
				$where_clause->get_first_child_node( 'expr' )
			);
			return sprintf( 'AND %s', $value );
		}

		return '';
	}

	/**
	 * Translate INSERT or REPLACE statement body to SQLite, while emulating
	 * the behavior of MySQL implicit default values in non-strict mode.
	 *
	 * Rewrites a statement body in the following form:
	 *   INSERT INTO table (optionally some columns) <select-or-values>
	 * To a statement body with the following structure:
	 *   INSERT INTO table (all table columns)
	 *   SELECT <non-strict-mode-adjusted-values> FROM (<select-or-values>) WHERE true
	 *
	 * In MySQL, the behavior of INSERT and UPDATE statements depends on whether
	 * the STRICT_TRANS_TABLES (InnoDB) or STRICT_ALL_TABLES SQL mode is enabled.
	 *
	 * By default, STRICT_TRANS_TABLES is enabled, which makes the InnoDB table
	 * behavior correspond to the natural behavior of SQLite tables. However,
	 * some applications, including WordPress, disable strict mode altogether.
	 *
	 * The strict SQL modes can be set per session, and can be changed at runtime.
	 * In SQLite, we can emulate this using the knowledge of the table structure:
	 *   1. Explicitly passed INSERT statement values are used without change.
	 *   2. Values omitted from the INSERT statement are replaced with the column
	 *      DEFAULT or an IMPLICIT DEFAULT value based on their data type.
	 *
	 * Here's a summary of the strict vs. non-strict behaviors in MySQL:
	 *
	 * When STRICT_TRANS_TABLES or STRICT_ALL_TABLES is enabled:
	 *   1. NULL + NO DEFAULT:     No value saves NULL, NULL saves NULL, DEFAULT saves NULL.
	 *   2. NULL + DEFAULT:        No value saves DEFAULT, NULL saves NULL, DEFAULT saves DEFAULT.
	 *   3. NOT NULL + NO DEFAULT: No value is rejected, NULL is rejected, DEFAULT is rejected.
	 *   4. NOT NULL + DEFAULT:    No value saves DEFAULT, NULL is rejected, DEFAULT saves DEFAULT.
	 *
	 * When STRICT_TRANS_TABLES and STRICT_ALL_TABLES are disabled:
	 *   1. NULL + NO DEFAULT:     No value saves NULL, NULL saves NULL, DEFAULT saves NULL.
	 *   2. NULL + DEFAULT:        No value saves DEFAULT, NULL saves NULL, DEFAULT saves DEFAULT.
	 *   3. NOT NULL + NO DEFAULT: No value saves IMPLICIT DEFAULT.
	 *                             NULL is rejected on INSERT, but saves IMPLICIT DEFAULT on UPDATE.
	 *                             DEFAULT saves IMPLICIT DEFAULT.
	 *   4. NOT NULL + DEFAULT:    No value saves DEFAULT.
	 *                             NULL is rejected on INSERT, but saves IMPLICIT DEFAULT on UPDATE.
	 *                             DEFAULT saves DEFAULT.
	 *
	 * For more information about IMPLICIT DEFAULT values in MySQL, see:
	 *   https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html#data-type-defaults-implicit
	 *
	 * @param  string         $table_name The name of the target table.
	 * @param  WP_Parser_Node $node       The "insertQueryExpression" or "insertValues" AST node.
	 * @return string                     The translated INSERT query body.
	 */
	private function translate_insert_or_replace_body_in_non_strict_mode(
		string $table_name,
		WP_Parser_Node $node
	): string {
		// 1. Get column metadata from information schema.
		$is_temporary  = $this->information_schema_builder->temporary_table_exists( $table_name );
		$columns_table = $this->information_schema_builder->get_table_name( $is_temporary, 'columns' );
		$columns       = $this->execute_sqlite_query(
			'
				SELECT column_name, is_nullable, column_default, data_type, extra
				FROM ' . $this->quote_sqlite_identifier( $columns_table ) . '
				WHERE table_schema = ?
				AND table_name = ?
				ORDER BY ordinal_position
			',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 2. Get the list of fields explicitly defined in the INSERT statement.
		$insert_list = array();
		$fields_node = $node->get_first_child_node( 'fields' );
		if ( $fields_node ) {
			// This is the optional "INSERT INTO ... (field1, field2, ...)" list.
			foreach ( $fields_node->get_child_nodes() as $field ) {
				$insert_list[] = $this->unquote_sqlite_identifier( $this->translate( $field ) );
			}
		} else {
			// When no explicit field list is provided, all columns are required.
			foreach ( array_column( $columns, 'COLUMN_NAME' ) as $column_name ) {
				$insert_list[] = $column_name;
			}
		}

		// 3. Filter out omitted columns that will get a value from the SQLite engine.
		//    That is, nullable columns, columns with defaults, and generated columns.
		$columns = array_values(
			array_filter(
				$columns,
				function ( $column ) use ( $insert_list ) {
					$is_omitted = ! in_array( $column['COLUMN_NAME'], $insert_list, true );
					if ( ! $is_omitted ) {
						return true;
					}
					$is_nullable  = 'YES' === $column['IS_NULLABLE'];
					$has_default  = $column['COLUMN_DEFAULT'];
					$is_generated = str_contains( $column['EXTRA'], 'auto_increment' );
					return ! ( $is_nullable || $has_default || $is_generated );
				}
			)
		);

		// 4. Get the list of column names returned by VALUES or SELECT clause.
		$select_list = array();
		if ( 'insertQueryExpression' === $node->rule_name ) {
			// When inserting from a SELECT query, we don't know the column names.
			// Let's wrap the query with a SELECT (...) LIMIT 0 to get obtain them.
			$expr = $node->get_first_child_node( 'queryExpressionOrParens' );
			$stmt = $this->execute_sqlite_query(
				'SELECT * FROM (' . $this->translate( $expr ) . ') LIMIT 1'
			);
			$stmt->execute();

			for ( $i = 0; $i < $stmt->columnCount(); $i++ ) {
				$select_list[] = $stmt->getColumnMeta( $i )['name'];
			}
		} else {
			// When inserting from a VALUES list, SQLite uses "columnN" naming.
			foreach ( array_keys( $insert_list ) as $position ) {
				$select_list[] = 'column' . ( $position + 1 );
			}
		}

		// 5. Compose a new INSERT field list with all columns from the table.
		$fragment = '(';
		foreach ( $columns as $i => $column ) {
			$fragment .= $i > 0 ? ', ' : '';
			$fragment .= $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );
		}
		$fragment .= ')';

		// 6. Compose a wrapper SELECT statement emulating IMPLICIT DEFAULT values.
		$fragment .= ' SELECT ';
		foreach ( $columns as $i => $column ) {
			$is_omitted = ! in_array( $column['COLUMN_NAME'], $insert_list, true );
			$fragment  .= $i > 0 ? ', ' : '';
			if ( $is_omitted ) {
				/*
				 * When a column is omitted from the INSERT list, we need to use
				 * an IMPLICIT DEFAULT value. Note that at this point, all omitted
				 * columns that will not get an implicit default are filtered out.
				 * (That is, nullable, generated, and columns with true defaults.)
				 */
				$default   = self::DATA_TYPE_IMPLICIT_DEFAULT_MAP[ $column['DATA_TYPE'] ] ?? null;
				$fragment .= null === $default ? 'NULL' : $this->connection->quote( $default );
			} else {
				// When a column value is included, we need to apply type casting.
				$position   = array_search( $column['COLUMN_NAME'], $insert_list, true );
				$identifier = $this->quote_sqlite_identifier( $select_list[ $position ] );
				$fragment  .= sprintf(
					'%s AS %s',
					$this->cast_value_in_non_strict_mode( $column['DATA_TYPE'], $identifier ),
					$identifier
				);
			}
		}

		// 6. Wrap the original insert VALUES or SELECT expression in a FROM clause.
		$values = 'insertFromConstructor' === $node->rule_name
			? $node->get_first_child_node( 'insertValues' )
			: $node->get_first_child_node( 'queryExpressionOrParens' );

		/*
		 * The "WHERE true" suffix is used to avoid parsing ambiguity in SQLite.
		 * When an "ON CONFLICT" clause is used and there is no "WHERE", SQLite
		 * doesn't know if "ON" belongs to a "JOIN" or an "ON CONFLICT" clause.
		 *
		 * See: https://www.sqlite.org/lang_insert.html
		 */
		$fragment .= ' FROM (' . $this->translate( $values ) . ') WHERE true';

		return $fragment;
	}

	/**
	 * Translate UPDATE list, emulating MySQL implicit defaults in non-strict mode.
	 *
	 * Rewrites an UPDATE statement list in the following form:
	 *   UPDATE table SET <non-null-column> = <value>
	 * To a list with the following structure:
	 *   UPDATE table SET <non-null-column> = COALESCE(<value>, <implicit-default>)
	 *
	 * In MySQL, the behavior of INSERT and UPDATE statements depends on whether
	 * the STRICT_TRANS_TABLES (InnoDB) or STRICT_ALL_TABLES SQL mode is enabled.
	 *
	 * When the strict mode is not enabled, executing an UPDATE statement that
	 * sets a NOT NULL column value to NULL saves an IMPLICIT DEFAULT instead.
	 *
	 * @param  string         $table_name The name of the target table.
	 * @param  WP_Parser_Node $node       The "updateList" AST node.
	 * @return string                     The translated UPDATE list.
	 */
	private function translate_update_list_in_non_strict_mode( string $table_name, WP_Parser_Node $node ): string {
		// 1. Get column metadata from information schema.
		$is_temporary  = $this->information_schema_builder->temporary_table_exists( $table_name );
		$columns_table = $this->information_schema_builder->get_table_name( $is_temporary, 'columns' );
		$columns       = $this->execute_sqlite_query(
			'
				SELECT LOWER(column_name) AS COLUMN_NAME, is_nullable, data_type, column_default
				FROM ' . $this->quote_sqlite_identifier( $columns_table ) . '
				WHERE table_schema = ?
				AND table_name = ?
			',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );
		$column_map    = array_combine( array_column( $columns, 'COLUMN_NAME' ), $columns );

		// 2. Translate UPDATE list, emulating implicit defaults for NULLs values.
		$fragment = '';
		foreach ( $node->get_child_nodes() as $i => $update_element ) {
			$column_ref = $update_element->get_first_child_node( 'columnRef' );
			$expr       = $update_element->get_first_child_node( 'expr' );

			// Get column info.
			$column_name = $this->unquote_sqlite_identifier( $this->translate( $column_ref ) );
			$column_info = $column_map[ strtolower( $column_name ) ];
			$data_type   = $column_info['DATA_TYPE'];
			$is_nullable = 'YES' === $column_info['IS_NULLABLE'];
			$default     = $column_info['COLUMN_DEFAULT'];

			// Get the UPDATE value. It's either an expression or a DEFAULT keyword.
			if ( null === $expr ) {
				// Emulate "column = DEFAULT".
				$value = null === $default ? 'NULL' : $this->connection->quote( $default );
			} else {
				$value = $this->translate( $expr );
			}

			// Apply type casting.
			$value = $this->cast_value_in_non_strict_mode( $data_type, $value );

			// If the column is NOT NULL, a NULL value resolves to implicit default.
			$implicit_default = self::DATA_TYPE_IMPLICIT_DEFAULT_MAP[ $data_type ] ?? null;
			if ( ! $is_nullable && null !== $implicit_default ) {
				$value = sprintf( 'COALESCE(%s, %s)', $value, $this->connection->quote( $implicit_default ) );
			}

			// Compose the UPDATE list item.
			$fragment .= $i > 0 ? ', ' : '';
			$fragment .= $this->translate( $column_ref );
			$fragment .= ' = ';
			$fragment .= $value;
		}
		return $fragment;
	}

	/**
	 * Emulate MySQL type casting for INSERT or UPDATE value in non-strict mode.
	 *
	 * @param  string $mysql_data_type  The MySQL data type.
	 * @param  string $translated_value The original translated value.
	 * @return string                   The translated value.
	 */
	private function cast_value_in_non_strict_mode(
		string $mysql_data_type,
		string $translated_value
	): string {
		$sqlite_data_type = self::DATA_TYPE_STRING_MAP[ $mysql_data_type ];

		// Get and quote the IMPLICIT DEFAULT value.
		$implicit_default        = self::DATA_TYPE_IMPLICIT_DEFAULT_MAP[ $mysql_data_type ] ?? null;
		$quoted_implicit_default = null === $implicit_default
			? 'NULL'
			: $this->connection->quote( $implicit_default );

		/*
		 * In MySQL, when saving a value via INSERT or UPDATE in non-strict mode,
		 *   1. MySQL attempts to cast the value to the target column data type.
		 *   2. When casting can't be done, MySQL saves an IMPLICIT DEFAULT.
		 */
		switch ( $mysql_data_type ) {
			case 'date':
			case 'time':
			case 'datetime':
			case 'timestamp':
			case 'year':
				/*
				 * MySQL supports date and time components without a zero padding,
				 * but that doesn't work with date and time functions in SQLite.
				 * E.g.: "2025-3-7 9:5:2" is a valid datetime/timestamp value in
				 * in MySQL, but SQLite requires it to be "2025-03-07 09:05:02".
				 *
				 * A solution to this would need to be done on the SQL level to
				 * address computed values, and it should be done for the strict
				 * mode as well. This may require a user-defined function.
				 *
				 * TODO: Handle zero padding for date and time functions, while
				 *       supporting both strict and non-strict modes.
				 */

				if ( 'date' === $mysql_data_type ) {
					$function_call = sprintf( 'DATE(%s)', $translated_value );
				} elseif ( 'time' === $mysql_data_type ) {
					$function_call = sprintf( 'TIME(%s)', $translated_value );
				} elseif ( 'datetime' === $mysql_data_type || 'timestamp' === $mysql_data_type ) {
					$function_call = sprintf( 'DATETIME(%s)', $translated_value );
				} elseif ( 'year' === $mysql_data_type ) {
					$function_call = sprintf( "STRFTIME('%%Y', %s)", $translated_value );
				}

				// When the function call evaluates to NULL (invalid date/time),
				// we need to fallback to the IMPLICIT DEFAULT value.
				return sprintf(
					'IIF(%s IS NULL, NULL, COALESCE(%s, %s))',
					$translated_value,
					$function_call,
					$quoted_implicit_default
				);
			default:
				// For all other data types, use SQLite-native CAST expression.
				$mysql_data_type = strtolower( $mysql_data_type );
				return sprintf( 'CAST(%s AS %s)', $translated_value, $sqlite_data_type );
		}
	}

	/**
	 * Generate a SQLite CREATE TABLE statement from information schema data.
	 *
	 * @param  bool        $table_is_temporary Whether the table is temporary.
	 * @param  string      $table_name         The name of the table to create.
	 * @param  string|null $new_table_name     Override the original table name for ALTER TABLE emulation.
	 * @return string[]                        Queries to create the table, indexes, and constraints.
	 * @throws WP_SQLite_Driver_Exception      When the table information is missing.
	 */
	private function get_sqlite_create_table_statement(
		bool $table_is_temporary,
		string $table_name,
		?string $new_table_name = null
	): array {
		// 1. Get table info.
		$tables_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'tables' );
		$table_info   = $this->execute_sqlite_query(
			'
				SELECT *
				FROM ' . $this->quote_sqlite_identifier( $tables_table ) . "
				WHERE table_type = 'BASE TABLE'
				AND table_schema = ?
				AND table_name = ?
			",
			array( $this->db_name, $table_name )
		)->fetch( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			throw $this->new_driver_exception(
				sprintf( "Table '%s' doesn't exist", $table_name ),
				'42S02'
			);
		}

		// 2. Get column info.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_info   = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM %s WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position',
				$this->quote_sqlite_identifier( $columns_table )
			),
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 3. Get index info, grouped by index name.
		$statistics_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'statistics' );
		$constraint_info  = $this->execute_sqlite_query(
			sprintf(
				"
					SELECT *
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
					ORDER BY
						INDEX_NAME = 'PRIMARY' DESC,
						NON_UNIQUE = '0' DESC,
						INDEX_TYPE = 'SPATIAL' DESC,
						INDEX_TYPE = 'BTREE' DESC,
						INDEX_TYPE = 'FULLTEXT' DESC,
						ROWID,
						SEQ_IN_INDEX
				",
				$this->quote_sqlite_identifier( $statistics_table )
			),
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		$grouped_constraints = array();
		foreach ( $constraint_info as $constraint ) {
			$name                                 = $constraint['INDEX_NAME'];
			$seq                                  = $constraint['SEQ_IN_INDEX'];
			$grouped_constraints[ $name ][ $seq ] = $constraint;
		}

		// 4. Generate CREATE TABLE statement columns.
		$rows              = array();
		$on_update_queries = array();
		$has_autoincrement = false;
		foreach ( $column_info as $column ) {
			$query  = '  ';
			$query .= $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );

			$type = self::DATA_TYPE_STRING_MAP[ $column['DATA_TYPE'] ];

			/*
			 * In SQLite, there is a PRIMARY KEY quirk for backward compatibility.
			 * This applies to ROWID tables and single-column primary keys only:
			 *  1. "INTEGER PRIMARY KEY" creates an alias of ROWID.
			 *  2. "INT PRIMARY KEY" will not alias of ROWID.
			 *
			 * Therefore, we want to:
			 *  1. Use "INT PRIMARY KEY" when we have a single-column integer
			 *     PRIMARY KEY without AUTOINCREMENT (to avoid the ROWID alias).
			 *  2. Use "INTEGER PRIMARY KEY" otherwise.
			 *
			 * In SQLite, "AUTOINCREMENT" is only allowed on "INTEGER PRIMARY KEY",
			 * and setting it changes the automatic ROWID assignment algorithm to
			 * prevent the reuse of ROWIDs. Using "INT PRIMARY KEY" is not allowed.
			 *
			 * See:
			 *   - https://www.sqlite.org/autoinc.html
			 *   - https://www.sqlite.org/lang_createtable.html
			 */
			if (
				'INTEGER' === $type
				&& 'PRI' === $column['COLUMN_KEY']
				&& 'auto_increment' !== $column['EXTRA']
				&& count( $grouped_constraints['PRIMARY'] ) === 1
			) {
				$type = 'INT';
			}

			$query .= ' ' . $type;

			// In MySQL, text fields are case-insensitive by default.
			// COLLATE NOCASE emulates the same behavior in SQLite.
			// @TODO: Respect the actual column and index collation.
			if ( 'TEXT' === $type ) {
				$query .= ' COLLATE NOCASE';
			}
			if ( 'NO' === $column['IS_NULLABLE'] ) {
				$query .= ' NOT NULL';
			}
			if ( 'auto_increment' === $column['EXTRA'] ) {
				$has_autoincrement = true;
				$query            .= ' PRIMARY KEY AUTOINCREMENT';
			}
			if ( null !== $column['COLUMN_DEFAULT'] ) {
				// @TODO: Handle defaults with expression values (DEFAULT_GENERATED).

				// Handle DEFAULT CURRENT_TIMESTAMP. This works only with timestamp
				// and datetime columns. For other column types, it's just a string.
				if (
					'CURRENT_TIMESTAMP' === $column['COLUMN_DEFAULT']
					&& ( 'timestamp' === $column['DATA_TYPE'] || 'datetime' === $column['DATA_TYPE'] )
				) {
					$query .= ' DEFAULT CURRENT_TIMESTAMP';
				} else {
					$query .= ' DEFAULT ' . $this->connection->quote( $column['COLUMN_DEFAULT'] );
				}
			}
			$rows[] = $query;

			if ( 'on update CURRENT_TIMESTAMP' === $column['EXTRA'] ) {
				$on_update_queries[] = $this->get_column_on_update_trigger_query(
					$table_name,
					$column['COLUMN_NAME']
				);
			}
		}

		// 5. Generate CREATE TABLE statement constraints, collect indexes.
		$create_index_queries = array();
		foreach ( $grouped_constraints as $constraint ) {
			ksort( $constraint );
			$info = $constraint[1];

			if ( 'PRIMARY' === $info['INDEX_NAME'] ) {
				if ( $has_autoincrement ) {
					if ( count( $constraint ) > 1 ) {
						throw $this->new_driver_exception(
							'Cannot combine AUTOINCREMENT and multiple primary keys in SQLite'
						);
					}
					continue;
				}
				$query  = '  PRIMARY KEY (';
				$query .= implode(
					', ',
					array_map(
						function ( $column ) {
							return $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );
						},
						$constraint
					)
				);
				$query .= ')';
				$rows[] = $query;
			} else {
				$is_unique = '0' === $info['NON_UNIQUE'];

				// Prefix the original index name with the table name.
				// This is to avoid conflicting index names in SQLite.
				$sqlite_index_name = $this->get_sqlite_index_name( $table_name, $info['INDEX_NAME'] );

				$query  = sprintf(
					'CREATE %sINDEX %s ON %s (',
					$is_unique ? 'UNIQUE ' : '',
					$this->quote_sqlite_identifier( $sqlite_index_name ),
					$this->quote_sqlite_identifier( $table_name )
				);
				$query .= implode(
					', ',
					array_map(
						function ( $column ) {
							$fragment = $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );
							if ( 'D' === $column['COLLATION'] ) {
								$fragment .= ' DESC';
							}
							return $fragment;
						},
						$constraint
					)
				);
				$query .= ')';

				$create_index_queries[] = $query;
			}
		}

		// 6. Compose the CREATE TABLE statement.
		$create_table_query  = sprintf(
			"CREATE %sTABLE %s (\n",
			$table_is_temporary ? 'TEMPORARY ' : '',
			$this->quote_sqlite_identifier( $new_table_name ?? $table_name )
		);
		$create_table_query .= implode( ",\n", $rows );
		$create_table_query .= "\n) STRICT";
		return array_merge( array( $create_table_query ), $create_index_queries, $on_update_queries );
	}

	/**
	 * Generate a MySQL CREATE TABLE statement from information schema data.
	 *
	 * @param  bool   $table_is_temporary Whether the table is temporary.
	 * @param  string $table_name         The name of the table to create.
	 * @return string                     The CREATE TABLE statement.
	 */
	private function get_mysql_create_table_statement( bool $table_is_temporary, string $table_name ): ?string {
		// 1. Get table info.
		$tables_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'tables' );
		$table_info   = $this->execute_sqlite_query(
			'
				SELECT *
				FROM ' . $this->quote_sqlite_identifier( $tables_table ) . "
				WHERE table_type = 'BASE TABLE'
				AND table_schema = ?
				AND table_name = ?
			",
			array( $this->db_name, $table_name )
		)->fetch( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			return null;
		}

		// 2. Get column info.
		$columns_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'columns' );
		$column_info   = $this->execute_sqlite_query(
			sprintf(
				'
					SELECT *
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
					ORDER BY ordinal_position
				',
				$this->quote_sqlite_identifier( $columns_table )
			),
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 3. Get index info, grouped by index name.
		$statistics_table = $this->information_schema_builder->get_table_name( $table_is_temporary, 'statistics' );
		$constraint_info  = $this->execute_sqlite_query(
			sprintf(
				"
					SELECT *
					FROM %s
					WHERE table_schema = ?
					AND table_name = ?
					ORDER BY
						INDEX_NAME = 'PRIMARY' DESC,
						NON_UNIQUE = '0' DESC,
						INDEX_TYPE = 'SPATIAL' DESC,
						INDEX_TYPE = 'BTREE' DESC,
						INDEX_TYPE = 'FULLTEXT' DESC,
						ROWID,
						SEQ_IN_INDEX
				",
				$this->quote_sqlite_identifier( $statistics_table )
			),
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		$grouped_constraints = array();
		foreach ( $constraint_info as $constraint ) {
			$name                                 = $constraint['INDEX_NAME'];
			$seq                                  = $constraint['SEQ_IN_INDEX'];
			$grouped_constraints[ $name ][ $seq ] = $constraint;
		}

		// 4. Generate CREATE TABLE statement columns.
		$rows = array();
		foreach ( $column_info as $column ) {
			$sql  = '  ';
			$sql .= $this->quote_mysql_identifier( $column['COLUMN_NAME'] );
			$sql .= ' ' . $column['COLUMN_TYPE'];
			if ( 'NO' === $column['IS_NULLABLE'] ) {
				$sql .= ' NOT NULL';
			} elseif ( 'timestamp' === $column['COLUMN_TYPE'] ) {
				// Nullable "timestamp" columns dump NULL explicitly.
				$sql .= ' NULL';
			}
			if ( 'auto_increment' === $column['EXTRA'] ) {
				$sql .= ' AUTO_INCREMENT';
			}

			// Handle DEFAULT CURRENT_TIMESTAMP. This works only with timestamp
			// and datetime columns. For other column types, it's just a string.
			if (
				'CURRENT_TIMESTAMP' === $column['COLUMN_DEFAULT']
				&& ( 'timestamp' === $column['DATA_TYPE'] || 'datetime' === $column['DATA_TYPE'] )
			) {
				$sql .= ' DEFAULT CURRENT_TIMESTAMP';
			} elseif ( null !== $column['COLUMN_DEFAULT'] ) {
				$sql .= ' DEFAULT ' . $this->quote_mysql_utf8_string_literal( $column['COLUMN_DEFAULT'] );
			} elseif ( 'YES' === $column['IS_NULLABLE'] ) {
				$sql .= ' DEFAULT NULL';
			}

			// Handle ON UPDATE CURRENT_TIMESTAMP.
			if ( str_contains( $column['EXTRA'], 'on update CURRENT_TIMESTAMP' ) ) {
				$sql .= ' ON UPDATE CURRENT_TIMESTAMP';
			}

			if ( '' !== $column['COLUMN_COMMENT'] ) {
				$sql .= sprintf(
					' COMMENT %s',
					$this->quote_mysql_utf8_string_literal( $column['COLUMN_COMMENT'] )
				);
			}

			$rows[] = $sql;
		}

		// 4. Generate CREATE TABLE statement constraints, collect indexes.
		foreach ( $grouped_constraints as $constraint ) {
			ksort( $constraint );
			$info = $constraint[1];

			if ( 'PRIMARY' === $info['INDEX_NAME'] ) {
				$sql  = '  PRIMARY KEY (';
				$sql .= implode(
					', ',
					array_map(
						function ( $column ) {
							return $this->quote_mysql_identifier( $column['COLUMN_NAME'] );
						},
						$constraint
					)
				);
				$sql .= ')';
			} else {
				$is_unique = '0' === $info['NON_UNIQUE'];

				$sql  = sprintf(
					'  %s%s%sKEY ',
					$is_unique ? 'UNIQUE ' : '',
					'FULLTEXT' === $info['INDEX_TYPE'] ? 'FULLTEXT ' : '',
					'SPATIAL' === $info['INDEX_TYPE'] ? 'SPATIAL ' : ''
				);
				$sql .= $this->quote_mysql_identifier( $info['INDEX_NAME'] );
				$sql .= ' (';
				$sql .= implode(
					', ',
					array_map(
						function ( $column ) {
							$definition = $this->quote_mysql_identifier( $column['COLUMN_NAME'] );
							if ( null !== $column['SUB_PART'] ) {
								$definition .= sprintf( '(%d)', $column['SUB_PART'] );
							}
							if ( 'D' === $column['COLLATION'] ) {
								$definition .= ' DESC';
							}
							return $definition;
						},
						$constraint
					)
				);
				$sql .= ')';
			}

			if ( '' !== $info['INDEX_COMMENT'] ) {
				$sql .= sprintf(
					' COMMENT %s',
					$this->quote_mysql_utf8_string_literal( $info['INDEX_COMMENT'] )
				);
			}

			$rows[] = $sql;
		}

		// 5. Compose the CREATE TABLE statement.
		$collation = $table_info['TABLE_COLLATION'];
		$charset   = substr( $collation, 0, strpos( $collation, '_' ) );

		$sql  = sprintf(
			"CREATE %sTABLE %s (\n",
			$table_is_temporary ? 'TEMPORARY ' : '',
			$this->quote_mysql_identifier( $table_name )
		);
		$sql .= implode( ",\n", $rows );
		$sql .= "\n)";
		$sql .= sprintf( ' ENGINE=%s', $table_info['ENGINE'] );
		$sql .= sprintf( ' DEFAULT CHARSET=%s', $charset );
		$sql .= sprintf( ' COLLATE=%s', $collation );
		if ( '' !== $table_info['TABLE_COMMENT'] ) {
			$sql .= sprintf(
				' COMMENT=%s',
				$this->quote_mysql_utf8_string_literal( $table_info['TABLE_COMMENT'] )
			);
		}
		return $sql;
	}

	/**
	 * Get an unique SQLite index name from a MySQL table name and index name.
	 *
	 * @param string $table_name The MySQL table name.
	 * @param string $index_name The MySQL index name.
	 * @return string            The SQLite index name.
	 */
	private function get_sqlite_index_name( string $mysql_table_name, string $mysql_index_name ): string {
		// Prefix the original index name with the table name.
		// This is to avoid conflicting index names in SQLite.
		return $mysql_table_name . '__' . $mysql_index_name;
	}

	/**
	 * Get an SQLite query to emulate MySQL "ON UPDATE CURRENT_TIMESTAMP".
	 *
	 * In SQLite, "ON UPDATE CURRENT_TIMESTAMP" is not supported. We need to
	 * create a trigger to emulate this behavior.
	 *
	 * @param string $table  The table name.
	 * @param string $column The column name.
	 */
	private function get_column_on_update_trigger_query( string $table, string $column ): string {
		// The trigger wouldn't work for virtual and "WITHOUT ROWID" tables,
		// but currently that can't happen as we're not creating such tables.
		// See: https://www.sqlite.org/rowidtable.html
		$trigger_name = self::RESERVED_PREFIX . "{$table}_{$column}_on_update";
		return sprintf(
			'
				CREATE TRIGGER %s
				AFTER UPDATE ON %s
				FOR EACH ROW
				BEGIN
				  UPDATE %s SET %s = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid;
				END
			',
			$this->quote_sqlite_identifier( $trigger_name ),
			$this->quote_sqlite_identifier( $table ),
			$this->quote_sqlite_identifier( $table ),
			$this->quote_sqlite_identifier( $column )
		);
	}

	/**
	 * Unquote a quoted SQLite identifier.
	 *
	 * Remove bounding quotes and replace escaped quotes with their values.
	 *
	 * @param  string $quoted_identifier The quoted identifier value.
	 * @return string                    The unquoted identifier value.
	 */
	private function unquote_sqlite_identifier( string $quoted_identifier ): string {
		$first_byte = $quoted_identifier[0] ?? null;
		if ( '"' === $first_byte || '`' === $first_byte ) {
			$unquoted = substr( $quoted_identifier, 1, -1 );
			return str_replace( $first_byte . $first_byte, $first_byte, $unquoted );
		}
		return $quoted_identifier;
	}

	/**
	 * Quote an SQLite identifier.
	 *
	 * @param  string $unquoted_identifier The unquoted identifier value.
	 * @return string                      The quoted identifier value.
	 */
	private function quote_sqlite_identifier( string $unquoted_identifier ): string {
		return $this->connection->quote_identifier( $unquoted_identifier );
	}

	/**
	 * Quote a MySQL identifier.
	 *
	 * Wrap the identifier in backticks and escape backtick values within.
	 *
	 * @param  string $unquoted_identifier The unquoted identifier value.
	 * @return string                      The quoted identifier value.
	 */
	private function quote_mysql_identifier( string $unquoted_identifier ): string {
		return '`' . str_replace( '`', '``', $unquoted_identifier ) . '`';
	}

	/**
	 * Format a MySQL UTF-8 string literal for output in a CREATE TABLE statement.
	 *
	 * We expect UTF-8 strings coming from SQLite. The only characters that must
	 * be escaped in a single-quoted string for a UTF-8 MySQL dump are ' and \.
	 *
	 * MySQL SHOW CREATE TABLE command additionally escapes "\0", "\n", and "\r",
	 * for the mysql CLI, logs, and better readability. This applies to column
	 * default values, and table, column, and index comments. Other values, such
	 * as identifiers, don't have these extra characters escaped in the output.
	 *
	 * See:
	 *  - https://github.com/mysql/mysql-server/blob/ff05628a530696bc6851ba6540ac250c7a059aa7/sql/sql_show.cc#L1799
	 *  - https://github.com/mysql/mysql-server/blob/ff05628a530696bc6851ba6540ac250c7a059aa7/sql/table.cc#L3525
	 *
	 * Unfortunately, SQLite doesn't validate the UTF-8 encoding, so other byte
	 * sequences may come from SQLite as well: https://www.sqlite.org/invalidutf.html
	 *
	 * TODO: We may consider stripping invalid UTF-8 characters, but that's likely
	 *       to be a bigger project, as these can appear also in other contexts.
	 *
	 * @param  string $utf8_literal The UTF-8 string literal to escape.
	 * @return string               The escaped string literal.
	 */
	private function quote_mysql_utf8_string_literal( string $utf8_literal ): string {
		/*
		 * We can't use "addcslashes()" here, because it has an unusual handling
		 * of the ASCII NULL character, escaping it to "\000" instead of "\0".
		 *
		 * It is important to use "strtr()" and not "str_replace()", because
		 * "str_replace()" applies replacements one after another, modifying
		 * intermediate changes rather than just the original string:
		 *
		 *   - str_replace( [ 'a', 'b' ], [ 'b', 'c' ], 'ab' ); // 'cc' (bad)
		 *   - strtr( 'ab', [ 'a' => 'b', 'b' => 'c' ] );       // 'bc' (good)
		 */
		$backslash    = chr( 92 );
		$replacements = array(
			"'"        => "''",                    // A single quote character (').
			$backslash => $backslash . $backslash, // A backslash character (\).
			chr( 0 )   => $backslash . '0',        // An ASCII NULL character (\0).
			chr( 10 )  => $backslash . 'n',        // A newline (linefeed) character (\n).
			chr( 13 )  => $backslash . 'r',        // A carriage return character (\r).
		);
		return "'" . strtr( $utf8_literal, $replacements ) . "'";
	}

	/**
	 * Clear the state of the driver.
	 */
	private function flush(): void {
		$this->last_mysql_query    = '';
		$this->last_sqlite_queries = array();
		$this->last_result         = null;
		$this->last_return_value   = null;
		$this->is_readonly         = false;
	}

	/**
	 * Set results of a query() call using fetched data.
	 *
	 * @param array $data The data to set.
	 */
	private function set_results_from_fetched_data( array $data ): void {
		$this->last_result       = $data;
		$this->last_return_value = $this->last_result;
	}

	/**
	 * Set results of a query() call using the number of affected rows.
	 *
	 * @param int|null $override Override the affected rows.
	 */
	private function set_result_from_affected_rows( ?int $override = null ): void {
		/*
		 * SELECT CHANGES() is a workaround for the fact that $stmt->rowCount()
		 * returns "0" (zero) with the SQLite driver at all times.
		 * See: https://www.php.net/manual/en/pdostatement.rowcount.php
		 */
		if ( null === $override ) {
			$affected_rows = (int) $this->execute_sqlite_query( 'SELECT CHANGES()' )->fetch()[0];
		} else {
			$affected_rows = $override;
		}
		$this->last_result       = $affected_rows;
		$this->last_return_value = $affected_rows;
	}

	/**
	 * Create a new SQLite driver exception.
	 *
	 * @param string         $message  The exception message.
	 * @param int|string     $code     The exception code. For PDO errors, a string representing SQLSTATE.
	 * @param Throwable|null $previous The previous exception.
	 * @return WP_SQLite_Driver_Exception
	 */
	private function new_driver_exception(
		string $message,
		$code = 0,
		?Throwable $previous = null
	): WP_SQLite_Driver_Exception {
		return new WP_SQLite_Driver_Exception( $this, $message, $code, $previous );
	}

	/**
	 * Create a new invalid input exception.
	 *
	 * This exception can be used to mark cases that should never occur according
	 * to the MySQL grammar. It may serve as an assertion that should never fail.
	 *
	 * @return WP_SQLite_Driver_Exception
	 */
	private function new_invalid_input_exception(): WP_SQLite_Driver_Exception {
		return new WP_SQLite_Driver_Exception( $this, 'MySQL query syntax error.' );
	}

	/**
	 * Create a new not supported exception.
	 *
	 * This exception can be used to mark MySQL constructs that are not supported.
	 *
	 * @param string $cause The cause, indicating which construct is not supported.
	 * @return WP_SQLite_Driver_Exception
	 */
	private function new_not_supported_exception( string $cause ): WP_SQLite_Driver_Exception {
		return new WP_SQLite_Driver_Exception(
			$this,
			sprintf( 'MySQL query not supported. Cause: %s', $cause )
		);
	}

	/**
	 * Convert an information schema exception to a MySQL-like driver exception.
	 *
	 * This method is used to convert some information schema exceptions to the
	 * corresponding MySQL exceptions, as they would be generated by PDO MySQL.
	 * This conversion mirrors PDO's error messages and SQLSTATE codes.
	 *
	 * @param  WP_SQLite_Information_Schema_Exception $e The information schema exception.
	 * @return Throwable                                 The converted exception, or the original
	 *                                                   exception if no conversion was done.
	 */
	private function convert_information_schema_exception( WP_SQLite_Information_Schema_Exception $e ): Throwable {
		switch ( $e->get_type() ) {
			case WP_SQLite_Information_Schema_Exception::TYPE_DUPLICATE_TABLE_NAME:
				return $this->new_driver_exception(
					sprintf(
						"SQLSTATE[42S01]: Base table or view already exists: 1050 Table '%s' already exists",
						$e->get_data()['table_name']
					),
					'42S01'
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_DUPLICATE_COLUMN_NAME:
				return $this->new_driver_exception(
					sprintf(
						"SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name '%s'",
						$e->get_data()['column_name']
					),
					'42S21'
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_DUPLICATE_KEY_NAME:
				return $this->new_driver_exception(
					sprintf(
						"SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name '%s'",
						$e->get_data()['key_name']
					),
					'42S21'
				);
			case WP_SQLite_Information_Schema_Exception::TYPE_KEY_COLUMN_NOT_FOUND:
				return $this->new_driver_exception(
					sprintf(
						"SQLSTATE[42000]: Syntax error or access violation: 1072 Key column '%s' doesn't exist in table",
						$e->get_data()['column_name']
					),
					'42000'
				);
			default:
				return $e;
		}
	}
}
