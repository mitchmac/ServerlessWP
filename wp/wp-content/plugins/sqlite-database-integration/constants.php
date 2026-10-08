<?php
/**
 * Define constants for the SQLite implementation.
 */

// Temporary - This will be in wp-config.php once SQLite is merged in Core.
if ( ! defined( 'DB_ENGINE' ) ) {
	if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
		define( 'DB_ENGINE', 'sqlite' );
	} else {
		define( 'DB_ENGINE', 'mysql' );
	}
}

/**
 * DB_PATH is the absolute database file path, or ":memory:" for an in-memory database.
 *
 * Conflicting deprecated constants trigger a warning. DB_PATH takes precedence.
 * The database directory is also used for storage locks and must be writable by PHP.
 * When not configured, the drop-in defines DB_PATH after initializing the storage.
 *
 * Example: define( 'DB_PATH', '/private/wordpress/database.sqlite' );
 */
if ( 'sqlite' === DB_ENGINE && defined( 'DB_PATH' ) ) {
	if ( ! is_string( DB_PATH ) ) {
		throw new RuntimeException( 'DB_PATH must be a string.' );
	}

	$database_directory = ':memory:' === DB_PATH ? null : rtrim( dirname( DB_PATH ), '/' . DIRECTORY_SEPARATOR );

	if ( defined( 'DB_DIR' ) && rtrim( (string) DB_DIR, '/' . DIRECTORY_SEPARATOR ) !== $database_directory ) {
		trigger_error( 'DB_DIR conflicts with DB_PATH.', E_USER_WARNING );
	}
	if ( defined( 'DB_FILE' ) && ( ':memory:' === DB_PATH || DB_FILE !== basename( DB_PATH ) ) ) {
		trigger_error( 'DB_FILE conflicts with DB_PATH.', E_USER_WARNING );
	}
	if ( defined( 'FQDBDIR' ) && rtrim( (string) FQDBDIR, '/' . DIRECTORY_SEPARATOR ) !== $database_directory ) {
		trigger_error( 'FQDBDIR conflicts with DB_PATH.', E_USER_WARNING );
	}
	if ( defined( 'FQDB' ) && FQDB !== DB_PATH ) {
		trigger_error( 'FQDB conflicts with DB_PATH.', E_USER_WARNING );
	}
	unset( $database_directory );
}

/**
 * DB_DIR selects the database directory when DB_PATH is not configured.
 *
 * @deprecated 3.1.0 Define DB_PATH instead.
 */

/**
 * DB_FILE selects a filename inside DB_DIR or FQDBDIR.
 *
 * @deprecated 3.1.0 Define DB_PATH instead.
 */

/**
 * FQDBDIR is a directory where the sqlite database file is placed.
 * Defaults to the directory containing DB_PATH, or the legacy directory setting.
 *
 * @deprecated 3.0.0 Define DB_PATH instead of overriding FQDBDIR.
 */
if ( ! defined( 'FQDBDIR' ) ) {
	if ( defined( 'DB_PATH' ) && is_string( DB_PATH ) && ':memory:' !== DB_PATH ) {
		define( 'FQDBDIR', rtrim( dirname( DB_PATH ), '/\\' ) . '/' );
	} elseif ( ! defined( 'DB_PATH' ) && defined( 'DB_DIR' ) ) {
		define( 'FQDBDIR', rtrim( DB_DIR, '/\\' ) . '/' );
	} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
		define( 'FQDBDIR', WP_CONTENT_DIR . '/database/' );
	} else {
		define( 'FQDBDIR', ABSPATH . 'wp-content/database/' );
	}
}

/**
 * FQDB is the absolute path to the SQLite database file.
 *
 * Defaults to DB_PATH, or FQDBDIR combined with DB_FILE. Otherwise, the drop-in
 * defines FQDB after resolving the secret database path.
 *
 * @deprecated 3.0.0 Define DB_PATH instead of overriding FQDB.
 */
if ( ! defined( 'FQDB' ) ) {
	if ( defined( 'DB_PATH' ) ) {
		define( 'FQDB', DB_PATH );
	} elseif ( defined( 'DB_FILE' ) ) {
		// Avoid compile-time warnings for invalid constants on PHP 7.2.
		define( 'FQDB', constant( 'FQDBDIR' ) . constant( 'DB_FILE' ) );
	}
}
