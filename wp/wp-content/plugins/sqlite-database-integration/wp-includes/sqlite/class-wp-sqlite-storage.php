<?php

/**
 * Manages the storage for the WordPress SQLite database.
 *
 * The storage handles the path of the SQLite database:
 *   - An explicit database path is used as is.
 *   - A managed database is stored under a randomized protected path.
 *   - A database in a legacy path is migrated to the managed storage.
 *
 * The storage implements a locking mechanism for initialization and maintenance.
 * Locking uses a dedicated empty SQLite database and a maintenance file:
 *   1. An exclusive lock is acquired on the locking database at ".ht.sqlite.lock".
 *   2. A maintenance marker file is created at ".ht.sqlite.maintenance":
 *       - When requests see the file, they use the locking database.
 *       - Its absence provides a fast path for normal traffic.
 *   3. When the lock is released, the maintenance file is automatically removed.
 *
 * Database locking uses a PDO SQLite connection:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 *
 * Filesystem warnings are suppressed to avoid exposing database paths:
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class WP_SQLite_Storage {
	/**
	 * Filename of a script that returns the path to the database file.
	 */
	private const DATABASE_PATH_FILENAME = 'db-path.php';

	/**
	 * Filename of the database file.
	 */
	private const DATABASE_FILENAME = '.ht.sqlite';

	/**
	 * Filename of the SQLite locking database.
	 */
	private const LOCK_FILENAME = '.ht.sqlite.lock';

	/**
	 * Filename of the marker that makes requests check the storage lock.
	 */
	private const MAINTENANCE_FILENAME = '.ht.sqlite.maintenance';

	/**
	 * Number of bytes in a generated random token.
	 */
	private const TOKEN_BYTE_LENGTH = 16;

	/**
	 * Managed database root directory.
	 *
	 * @var string
	 */
	private $database_root;

	/**
	 * Explicitly configured database path, if any.
	 *
	 * @var string|null
	 */
	private $database_path;

	/**
	 * Absolute path of the database path file.
	 *
	 * @var string
	 */
	private $database_path_file;

	/**
	 * Absolute path of the SQLite locking database.
	 *
	 * @var string
	 */
	private $lock_path;

	/**
	 * Absolute path of the storage maintenance marker.
	 *
	 * @var string
	 */
	private $maintenance_path;

	/**
	 * Connection holding the exclusive transaction on the SQLite locking database.
	 *
	 * @var PDO|null
	 */
	private $storage_lock_connection;

	/**
	 * Connection holding the exclusive transaction on the WordPress SQLite database.
	 *
	 * @var PDO|null
	 */
	private $database_lock_connection;

	/**
	 * Time to wait for existing database connections, in milliseconds.
	 *
	 * @var int
	 */
	private $database_lock_timeout = 10000;

	/**
	 * Create a SQLite storage manager.
	 *
	 * @param string|null $database_root Managed database root. Defaults to FQDBDIR.
	 * @param string|null $database_path Explicit database path or ":memory:". Managed storage is used when null.
	 * @throws RuntimeException When the database path is invalid.
	 */
	public function __construct( ?string $database_root = null, ?string $database_path = null ) {
		if ( '' === $database_path ) {
			throw new RuntimeException( 'The SQLite database path is invalid.' );
		}
		$this->database_root      = rtrim( $database_root ?? FQDBDIR, '/\\' ) . '/';
		$this->database_path      = $database_path;
		$this->database_path_file = $this->database_root . self::DATABASE_PATH_FILENAME;
		$this->lock_path          = $this->database_root . self::LOCK_FILENAME;
		$this->maintenance_path   = $this->database_root . self::MAINTENANCE_FILENAME;
	}

	/**
	 * Initialize the SQLite database storage.
	 *
	 * Uses an explicit file path or ":memory:" as configured. Otherwise, initializes
	 * managed storage with a randomized path and migrates legacy databases as needed.
	 *
	 * @return string Absolute path to the SQLite database file, or ":memory:".
	 * @throws RuntimeException When the storage cannot be initialized.
	 */
	public function initialize(): string {
		// An in-memory database has no files.
		if ( ':memory:' === $this->database_path ) {
			return $this->database_path;
		}

		// Never resolve a database path while storage maintenance is in progress.
		$this->wait_until_unlocked();

		// Check if we're already holding a lock.
		$was_locked = null !== $this->storage_lock_connection;

		// Explicitly configured database path.
		if ( null !== $this->database_path ) {
			$this->ensure_database( $this->database_path );
			if ( $was_locked ) {
				// If the storage was locked, ensure the database is locked as well.
				$this->lock();
			}
			return $this->database_path;
		}

		// Initialized managed database path.
		$database_path = $this->read_recorded_database_path();
		if ( null !== $database_path && @is_file( $database_path ) ) {
			return $database_path;
		}

		// Initialize or repair the managed storage under the storage lock.
		// Preserve a lock already held by this instance for a larger operation.
		$this->lock();
		try {
			// Another process may have completed the initialization meanwhile.
			$database_path = $this->read_recorded_database_path();
			if ( null !== $database_path && @is_file( $database_path ) ) {
				return $database_path;
			}

			if ( null === $database_path ) {
				$database_path = $this->publish_database_path();
			}

			// Migrate legacy database paths.
			$legacy_path = $this->database_root . self::DATABASE_FILENAME;
			if ( ! @is_file( $legacy_path ) ) {
				$legacy_path = $this->database_root . self::DATABASE_FILENAME . '.php';
			}
			if ( @is_file( $legacy_path ) ) {
				$this->move_legacy_database( $legacy_path, $database_path );
			} else {
				$this->ensure_database( $database_path );
				// The earlier lock() call only locked the storage because the database
				// did not exist yet. Lock the new database too, so callers that already
				// held the storage lock retain both locks after initialization.
				$this->lock();
			}
			return $database_path;
		} finally {
			if ( ! $was_locked ) {
				$this->unlock();
			}
		}
	}

	/**
	 * Lock the database storage for maintenance.
	 *
	 * The locking mechanism does the following:
	 *   - Waits for a lock held by another process, up to the database lock timeout.
	 *   - Marks maintenance as active so new requests wait.
	 *   - When a database exists, also acquires an exclusive database lock.
	 *
	 * Existing database connections must be closed first to avoid blocking the lock.
	 *
	 * The lock is released when unlock() is called or the process ends.
	 *
	 * @throws RuntimeException When the lock cannot be acquired.
	 */
	public function lock(): void {
		$was_locked = null !== $this->storage_lock_connection;

		// Serialize maintenance through the dedicated locking database.
		if ( ! $was_locked ) {
			try {
				$this->ensure_database( $this->lock_path );
				$connection = new PDO( 'sqlite:' . $this->lock_path );
				$connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
				$connection->exec( 'PRAGMA busy_timeout = ' . $this->database_lock_timeout );
				$connection->exec( 'BEGIN EXCLUSIVE' );
				$this->storage_lock_connection = $connection;
			} catch ( Throwable $exception ) {
				throw new RuntimeException( 'Failed to acquire the SQLite storage lock.', 0, $exception );
			}
		}

		try {
			// Ensure the maintenance marker exists so new requests use the locking database.
			if ( ! @is_file( $this->maintenance_path ) ) {
				if ( false === @file_put_contents( $this->maintenance_path, '' ) ) {
					throw new RuntimeException( 'Failed to create the SQLite storage maintenance marker.' );
				}
				@chmod( $this->maintenance_path, 0600 );
			}

			// Reuse a database lock already held by this instance.
			if ( null !== $this->database_lock_connection ) {
				return;
			}

			// Look for an existing database so that it can be locked as well.
			$database_path = $this->database_path;
			if ( null === $database_path ) {
				$database_path = $this->read_recorded_database_path();

				// The managed database may still be at a legacy path.
				if ( null === $database_path || ! @is_file( $database_path ) ) {
					$database_path = $this->database_root . self::DATABASE_FILENAME;
				}
				if ( ! @is_file( $database_path ) ) {
					$database_path = $this->database_root . self::DATABASE_FILENAME . '.php';
				}
			}

			// Lock the existing database when found.
			if ( @is_file( $database_path ) ) {
				try {
					$this->database_lock_connection = $this->lock_database( $database_path );
				} catch ( Throwable $exception ) {
					throw new RuntimeException( 'Failed to lock the SQLite database.', 0, $exception );
				}
			}
		} catch ( Throwable $exception ) {
			if ( ! $was_locked ) {
				$this->unlock();
			}
			throw $exception;
		}
	}

	/**
	 * Unlock the storage.
	 */
	public function unlock(): void {
		$this->database_lock_connection = null;
		if ( null !== $this->storage_lock_connection ) {
			// Remove the marker before releasing the lock that protects it.
			@unlink( $this->maintenance_path );
			$this->storage_lock_connection = null;
		}
	}

	/**
	 * Wait until no maintenance process holds an active storage lock.
	 */
	private function wait_until_unlocked(): void {
		// A lock held by this instance must not block its own work.
		if ( null !== $this->storage_lock_connection || ! @is_file( $this->maintenance_path ) ) {
			return;
		}

		try {
			$this->ensure_database( $this->lock_path );
			$connection = new PDO( 'sqlite:' . $this->lock_path );
			$connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$connection->exec( 'PRAGMA busy_timeout = ' . $this->database_lock_timeout );
			$connection->exec( 'BEGIN EXCLUSIVE' );

			// Clear a stale marker while the transaction prevents new maintenance from starting.
			@unlink( $this->maintenance_path );
		} catch ( Throwable $exception ) {
			throw new RuntimeException( 'Failed to check the SQLite storage lock.', 0, $exception );
		}
	}

	/**
	 * Reads the recorded database path.
	 *
	 * @return string|null Absolute database path, or null when none is recorded.
	 */
	private function read_recorded_database_path(): ?string {
		if ( ! @is_file( $this->database_path_file ) ) {
			return null;
		}

		try {
			// Use include so an unreadable file can be handled without terminating on PHP 7.
			$database_path = @include $this->database_path_file;
		} catch ( Throwable $exception ) {
			throw new RuntimeException( 'Failed to read the SQLite database path file.', 0, $exception );
		}

		if ( false === $database_path ) {
			throw new RuntimeException( 'Failed to read the SQLite database path file.' );
		}

		if ( ! is_string( $database_path ) || '' === $database_path ) {
			throw new RuntimeException( 'The SQLite database path file is invalid.' );
		}

		return $database_path;
	}

	/**
	 * Generate a randomized database path and publish it atomically.
	 *
	 * @return string Absolute path to the published SQLite database file.
	 */
	private function publish_database_path(): string {
		$directory_name = '.ht.' . bin2hex( random_bytes( self::TOKEN_BYTE_LENGTH ) );
		$temporary_path = $this->database_root . 'tmp.' . self::DATABASE_PATH_FILENAME;

		$database_path_contents = sprintf(
			"<?php\n\n/**\n * SQLite database path.\n *\n * IMPORTANT: Keep this path secret. When possible, point it outside the document root.\n */\nreturn __DIR__ . '/%s/%s';\n",
			$directory_name,
			self::DATABASE_FILENAME
		);

		// Publish the complete file atomically.
		if ( false === @file_put_contents( $temporary_path, $database_path_contents ) ) {
			throw new RuntimeException( 'Failed to write the SQLite database path file.' );
		}
		@chmod( $temporary_path, 0600 );

		if ( ! @rename( $temporary_path, $this->database_path_file ) ) {
			@unlink( $temporary_path );
			throw new RuntimeException( 'Failed to publish the SQLite database path file.' );
		}

		// This runs before wp_opcache_invalidate() is available.
		$opcache_restrict_api = ini_get( 'opcache.restrict_api' );
		$script_filename      = isset( $_SERVER['SCRIPT_FILENAME'] ) ? realpath( $_SERVER['SCRIPT_FILENAME'] ) : false;
		if (
			function_exists( 'opcache_invalidate' )
			&& ( ! $opcache_restrict_api || ( $script_filename && 0 === stripos( $script_filename, $opcache_restrict_api ) ) )
		) {
			opcache_invalidate( $this->database_path_file, true );
		}

		return $this->database_root . $directory_name . '/' . self::DATABASE_FILENAME;
	}

	/**
	 * Move a legacy database into a managed path.
	 *
	 * @param string $legacy_path   Absolute path of the legacy database file.
	 * @param string $database_path Absolute destination path.
	 */
	private function move_legacy_database( string $legacy_path, string $database_path ): void {
		$this->ensure_protected_directory( dirname( $database_path ) );

		/*
		 * Close the connection just before moving the database. SQLite considers
		 * renaming an open database undefined, and Windows generally prevents it.
		 * We cannot fully prevent race conditions, but this makes them unlikely.
		 *
		 * See: https://www.sqlite.org/howtocorrupt.html#unlink
		 */
		$this->database_lock_connection = null;
		if ( ! @rename( $legacy_path, $database_path ) ) {
			throw new RuntimeException( 'Failed to move the SQLite database file.' );
		}
		@chmod( $database_path, 0600 );

		try {
			$this->database_lock_connection = $this->lock_database( $database_path );
		} catch ( Throwable $exception ) {
			throw new RuntimeException( 'Failed to lock the migrated SQLite database.', 0, $exception );
		}
	}

	/**
	 * Ensure that a database and its protected directory exist.
	 *
	 * @param string $database_path Absolute database path.
	 */
	private function ensure_database( string $database_path ): void {
		$this->ensure_protected_directory( dirname( $database_path ) );

		if ( ! @is_file( $database_path ) ) {
			// Create an empty database file with restricted permissions.
			$database_handle = @fopen( $database_path, 'c' );
			if ( false === $database_handle ) {
				throw new RuntimeException( 'Failed to create the SQLite database file.' );
			}
			fclose( $database_handle );
			@chmod( $database_path, 0600 );
		}
	}

	/**
	 * Ensure that a database directory exists and deny direct access.
	 *
	 * @param string $directory Absolute directory path.
	 */
	private function ensure_protected_directory( string $directory ): void {
		if ( ! @is_dir( $directory ) ) {
			// Create the path one directory at a time to avoid changing the process-wide umask.
			$missing_directories = array();
			for ( $path = rtrim( $directory, '/\\' ); ! @is_dir( $path ); $path = dirname( $path ) ) {
				$missing_directories[] = $path;
				if ( dirname( $path ) === $path ) {
					break;
				}
			}

			foreach ( array_reverse( $missing_directories ) as $path ) {
				if ( ! @mkdir( $path, 0700 ) && ! @is_dir( $path ) ) {
					throw new RuntimeException( 'Failed to create the SQLite database directory.' );
				}
				@chmod( $path, 0700 );
			}
		}

		$this->ensure_file( rtrim( $directory, '/\\' ) . '/.htaccess', 'DENY FROM ALL' );
		$this->ensure_file( rtrim( $directory, '/\\' ) . '/index.php', '<?php // Silence is golden.' );
	}

	/**
	 * Create a protected file when it does not already exist.
	 *
	 * @param string $path     Absolute file path.
	 * @param string $contents File contents.
	 */
	private function ensure_file( string $path, string $contents ): void {
		if ( @is_file( $path ) ) {
			return;
		}
		if ( false === @file_put_contents( $path, $contents ) ) {
			throw new RuntimeException( 'Failed to create SQLite database protection file.' );
		}
		@chmod( $path, 0600 );
	}

	/**
	 * Acquire an exclusive transaction on an SQLite database.
	 *
	 * @param string $database_path Absolute database path.
	 * @return PDO Connection holding the transaction.
	 */
	private function lock_database( string $database_path ): PDO {
		// Do not silently create an empty database if the expected file is missing.
		$pdo_options = array();
		if ( defined( 'Pdo\Sqlite::ATTR_OPEN_FLAGS' ) ) {
			$pdo_options[ Pdo\Sqlite::ATTR_OPEN_FLAGS ] = Pdo\Sqlite::OPEN_READWRITE;
		} elseif ( defined( 'PDO::SQLITE_ATTR_OPEN_FLAGS' ) ) {
			$pdo_options[ PDO::SQLITE_ATTR_OPEN_FLAGS ] = PDO::SQLITE_OPEN_READWRITE;
		}

		$connection = null;
		$deadline   = microtime( true ) + ( $this->database_lock_timeout / 1000 );
		do {
			if ( microtime( true ) >= $deadline ) {
				throw new RuntimeException( 'Failed to acquire an exclusive SQLite database lock.' );
			}

			if ( null === $connection ) {
				$connection = new PDO( 'sqlite:' . $database_path, null, null, $pdo_options );
				$connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
				$connection->exec( 'PRAGMA busy_timeout = ' . $this->database_lock_timeout );
			}

			try {
				// Disable WAL so we can acquire a truly exclusive READ/WRITE lock.
				$journal_mode = $connection->query( 'PRAGMA journal_mode = DELETE' )->fetchColumn();
				if ( 'delete' === strtolower( (string) $journal_mode ) ) {
					// Acquire exclusive lock.
					$connection->exec( 'BEGIN EXCLUSIVE' );

					// Another connection may have restored WAL before the transaction started.
					// If that's the case, release the lock and retry in the next iteration.
					$journal_mode = $connection->query( 'PRAGMA journal_mode' )->fetchColumn();
					if ( 'delete' !== strtolower( (string) $journal_mode ) ) {
						$connection->exec( 'ROLLBACK' );
					} else {
						// Connection lock successfully acquired.
						return $connection;
					}
				}
			} catch ( PDOException $exception ) {
				$error_info    = $connection->errorInfo();
				$sqlite_busy   = 5;
				$database_busy = isset( $error_info[1] ) && ( (int) $error_info[1] & 0xff ) === $sqlite_busy;
				if ( ! $database_busy ) {
					throw $exception;
				}

				// Close the connection so any raw transaction is rolled back before retrying.
				$connection = null;
			}

			usleep( 100000 ); // 100 milliseconds.
		} while ( true );
	}
}
