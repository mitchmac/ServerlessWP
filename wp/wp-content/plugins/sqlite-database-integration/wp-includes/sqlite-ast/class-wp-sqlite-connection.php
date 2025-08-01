<?php declare(strict_types = 1);

/*
 * The SQLite connection uses PDO. Enable PDO function calls:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 */

/**
 * SQLite connection.
 *
 * This class configures and encapsulates the connection to an SQLite database.
 * It requires PDO with the SQLite driver, and currently, it is only a simple
 * wrapper that leaks some of the PDO APIs (returns PDOStatement values, etc.).
 * In the future, we may abstract it away from PDO and support SQLite3 as well.
 */
class WP_SQLite_Connection {
	/**
	 * The default timeout in seconds for SQLite to wait for a writable lock.
	 */
	const DEFAULT_SQLITE_TIMEOUT = 10;

	/**
	 * The supported SQLite journal modes.
	 *
	 * See: https://www.sqlite.org/pragma.html#pragma_journal_mode
	 */
	const SQLITE_JOURNAL_MODES = array(
		'DELETE',
		'TRUNCATE',
		'PERSIST',
		'MEMORY',
		'WAL',
		'OFF',
	);

	/**
	 * The PDO connection for SQLite.
	 *
	 * @var PDO
	 */
	private $pdo;

	/**
	 * A query logger callback.
	 *
	 * @var callable(string, array): void
	 */
	private $query_logger;

	/**
	 * Constructor.
	 *
	 * Set up an SQLite connection.
	 *
	 * @param array $options {
	 *     An array of options.
	 *
	 *     @type string|null $path         Optional. SQLite database path.
	 *                                     For in-memory database, use ':memory:'.
	 *                                     Must be set when PDO instance is not provided.
	 *     @type PDO|null    $pdo          Optional. PDO instance with SQLite connection.
	 *                                     If not provided, a new PDO instance will be created.
	 *     @type int|null    $timeout      Optional. SQLite timeout in seconds.
	 *                                     The time to wait for a writable lock.
	 *     @type string|null $journal_mode Optional. SQLite journal mode.
	 * }
	 *
	 * @throws InvalidArgumentException When some connection options are invalid.
	 * @throws PDOException             When the driver initialization fails.
	 */
	public function __construct( array $options ) {
		// Setup PDO connection.
		if ( isset( $options['pdo'] ) && $options['pdo'] instanceof PDO ) {
			$this->pdo = $options['pdo'];
		} else {
			if ( ! isset( $options['path'] ) || ! is_string( $options['path'] ) ) {
				throw new InvalidArgumentException( 'Option "path" is required when "connection" is not provided.' );
			}
			$this->pdo = new PDO( 'sqlite:' . $options['path'] );
		}

		// Throw exceptions on error.
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		// Configure SQLite timeout.
		if ( isset( $options['timeout'] ) && is_int( $options['timeout'] ) ) {
			$timeout = $options['timeout'];
		} else {
			$timeout = self::DEFAULT_SQLITE_TIMEOUT;
		}
		$this->pdo->setAttribute( PDO::ATTR_TIMEOUT, $timeout );

		// Return all values (except null) as strings.
		$this->pdo->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );

		// Configure SQLite journal mode.
		$journal_mode = $options['journal_mode'] ?? null;
		if ( $journal_mode && in_array( $journal_mode, self::SQLITE_JOURNAL_MODES, true ) ) {
			$this->query( 'PRAGMA journal_mode = ' . $journal_mode );
		}
	}

	/**
	 * Execute a query in SQLite.
	 *
	 * @param  string $sql   The query to execute.
	 * @param  array $params The query parameters.
	 * @throws PDOException  When the query execution fails.
	 * @return PDOStatement  The PDO statement object.
	 */
	public function query( string $sql, array $params = array() ): PDOStatement {
		if ( $this->query_logger ) {
			( $this->query_logger )( $sql, $params );
		}
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( $params );
		return $stmt;
	}

	/**
	 * Returns the ID of the last inserted row.
	 *
	 * @return string The ID of the last inserted row.
	 */
	public function get_last_insert_id(): string {
		return $this->pdo->lastInsertId();
	}

	/**
	 * Quote a value for use in a query.
	 *
	 * @param  mixed  $value The value to quote.
	 * @param  int    $type  The type of the value.
	 * @return string        The quoted value.
	 */
	public function quote( $value, int $type = PDO::PARAM_STR ): string {
		return $this->pdo->quote( $value, $type );
	}

	/**
	 * Quote an SQLite identifier.
	 *
	 * Wraps the identifier in backticks and escapes backtick characters within.
	 *
	 * ---
	 *
	 * Quoted identifiers in SQLite are represented by string constants:
	 *
	 *   A string constant is formed by enclosing the string in single quotes (').
	 *   A single quote within the string can be encoded by putting two single
	 *   quotes in a row - as in Pascal. C-style escapes using the backslash
	 *   character are not supported because they are not standard SQL.
	 *
	 * See: https://www.sqlite.org/lang_expr.html#literal_values_constants_
	 *
	 * Although sparsely documented, this applies to backtick and double quoted
	 * string constants as well, so only the quote character needs to be escaped.
	 *
	 * For more details, see the grammar for SQLite table and column names:
	 *
	 *   - https://github.com/sqlite/sqlite/blob/873fc5dff2a781251f2c9bd2c791a5fac45b7a2b/src/tokenize.c#L395-L419
	 *   - https://github.com/sqlite/sqlite/blob/873fc5dff2a781251f2c9bd2c791a5fac45b7a2b/src/parse.y#L321-L338
	 *
	 * ---
	 *
	 * We use backtick quotes instead of the SQL standard double quotes, due to
	 * an SQLite quirk causing double-quoted strings to be accepted as literals:
	 *
	 *   This misfeature means that a misspelled double-quoted identifier will
	 *   be interpreted as a string literal, rather than generating an error.
	 *
	 * See: https://www.sqlite.org/quirks.html#double_quoted_string_literals_are_accepted
	 *
	 * @param  string $unquoted_identifier The unquoted identifier value.
	 * @return string                      The quoted identifier value.
	 */
	public function quote_identifier( string $unquoted_identifier ): string {
		return '`' . str_replace( '`', '``', $unquoted_identifier ) . '`';
	}

	/**
	 * Get the PDO object.
	 *
	 * @return PDO
	 */
	public function get_pdo(): PDO {
		return $this->pdo;
	}

	/**
	 * Set a logger for the queries.
	 *
	 * @param callable(string, array): void $logger A query logger callback.
	 */
	public function set_query_logger( callable $logger ): void {
		$this->query_logger = $logger;
	}
}
