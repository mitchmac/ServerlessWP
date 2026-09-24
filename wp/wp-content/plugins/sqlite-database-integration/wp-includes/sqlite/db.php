<?php
/**
 * Main integration file.
 */

/**
 * Load the "SQLITE_DRIVER_VERSION" constant.
 */
require_once __DIR__ . '/../database/version.php';

// Require the constants file.
require_once __DIR__ . '/../../constants.php';

// Bail early if DB_ENGINE is not defined as sqlite.
if ( ! defined( 'DB_ENGINE' ) || 'sqlite' !== DB_ENGINE ) {
	return;
}

if ( ! extension_loaded( 'pdo' ) ) {
	wp_die(
		new WP_Error(
			'pdo_not_loaded',
			sprintf(
				'<h1>%1$s</h1><p>%2$s</p>',
				'PHP PDO Extension is not loaded',
				'Your PHP installation appears to be missing the PDO extension which is required for this version of WordPress and the type of database you have specified.'
			)
		),
		'PHP PDO Extension is not loaded.'
	);
}

if ( ! extension_loaded( 'pdo_sqlite' ) ) {
	wp_die(
		new WP_Error(
			'pdo_driver_not_loaded',
			sprintf(
				'<h1>%1$s</h1><p>%2$s</p>',
				'PDO Driver for SQLite is missing',
				'Your PHP installation appears not to have the right PDO drivers loaded. These are required for this version of WordPress and the type of database you have specified.'
			)
		),
		'PDO Driver for SQLite is missing.'
	);
}

require_once __DIR__ . '/class-wp-sqlite-storage.php';

try {
	$database_storage = new WP_SQLite_Storage( FQDBDIR, defined( 'FQDB' ) ? FQDB : null );
	$database_path    = $database_storage->initialize();
} catch ( Throwable $exception ) {
	error_log( 'SQLite database error: ' . (string) $exception );

	// WP-CLI can load the drop-in before wp_die() dependencies are available.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::error( $exception->getMessage() );
	}

	// Use htmlspecialchars() with an explicit charset because esc_html() reads the
	// blog_charset option, but the database and object cache are not initialized yet.
	wp_die( htmlspecialchars( $exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ), 'SQLite database error', array( 'response' => 503 ) );
}

if ( ! defined( 'FQDB' ) ) {
	define( 'FQDB', $database_path );
}

require_once __DIR__ . '/../database/load.php';
require_once __DIR__ . '/class-wp-sqlite-db.php';
require_once __DIR__ . '/install-functions.php';

$db_name         = defined( 'DB_NAME' ) ? DB_NAME : '';
$GLOBALS['wpdb'] = new WP_SQLite_DB( $db_name );

// Boot the Query Monitor plugin if it is active.
require_once __DIR__ . '/../../integrations/query-monitor/boot.php';
