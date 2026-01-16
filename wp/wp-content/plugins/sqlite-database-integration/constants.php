<?php
/**
 * Define constants for the SQLite implementation.
 *
 * @since 1.0.0
 * @package wp-sqlite-integration
 */

// Temporary - This will be in wp-config.php once SQLite is merged in Core.
if ( ! defined( 'DB_ENGINE' ) ) {
	if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
		define( 'DB_ENGINE', 'sqlite' );
	} elseif ( defined( 'DATABASE_ENGINE' ) ) {
		// backwards compatibility with previous versions of the plugin.
		define( 'DB_ENGINE', DATABASE_ENGINE );
	} else {
		define( 'DB_ENGINE', 'mysql' );
	}
}

/**
 * Notice:
 * Your scripts have the permission to create directories or files on your server.
 * If you write in your wp-config.php like below, we take these definitions.
 * define('DB_DIR', '/full_path_to_the_database_directory/');
 * define('DB_FILE', 'database_file_name');
 */

/**
 * FQDBDIR is a directory where the sqlite database file is placed.
 * If DB_DIR is defined, it is used as FQDBDIR.
 */
if ( ! defined( 'FQDBDIR' ) ) {
	if ( defined( 'DB_DIR' ) ) {
		define( 'FQDBDIR', trailingslashit( DB_DIR ) );
	} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
		define( 'FQDBDIR', WP_CONTENT_DIR . '/database/' );
	} else {
		define( 'FQDBDIR', ABSPATH . 'wp-content/database/' );
	}
}

/**
 * FQDB is a database file name. If DB_FILE is defined, it is used
 * as FQDB.
 */
if ( ! defined( 'FQDB' ) ) {
	if ( defined( 'DB_FILE' ) ) {
		define( 'FQDB', FQDBDIR . DB_FILE );
	} else {
		define( 'FQDB', FQDBDIR . '.ht.sqlite' );
	}
}

// Allow enabling the SQLite AST driver via environment variable.
if ( ! defined( 'WP_SQLITE_AST_DRIVER' ) && isset( $_ENV['WP_SQLITE_AST_DRIVER'] ) && 'true' === $_ENV['WP_SQLITE_AST_DRIVER'] ) {
	define( 'WP_SQLITE_AST_DRIVER', true );
}
