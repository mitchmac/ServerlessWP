<?php
/**
 * Plugin Name: SQLite Database Integration
 * Description: SQLite database driver drop-in.
 * Author: The WordPress Team
 * Version: 2.2.2
 * Requires PHP: 7.2
 * Textdomain: sqlite-database-integration
 *
 * This feature plugin allows WordPress to use SQLite instead of MySQL as its database.
 *
 * @package wp-sqlite-integration
 */

/**
 * Load the "SQLITE_DRIVER_VERSION" constant.
 * This constant needs to be updated on plugin release!
 */
require_once __DIR__ . '/version.php';

define( 'SQLITE_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/php-polyfills.php';
require_once __DIR__ . '/admin-page.php';
require_once __DIR__ . '/activate.php';
require_once __DIR__ . '/deactivate.php';
require_once __DIR__ . '/admin-notices.php';
require_once __DIR__ . '/health-check.php';
