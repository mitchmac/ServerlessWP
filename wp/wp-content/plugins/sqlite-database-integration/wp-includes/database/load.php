<?php

define( 'WP_MYSQL_ON_SQLITE_LOADER_PATH', __FILE__ );

/**
 * Load the PDO MySQL-on-SQLite driver and its dependencies.
 */
require_once __DIR__ . '/php-polyfills.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/parser/class-wp-parser-grammar.php';
require_once __DIR__ . '/parser/class-wp-parser.php';
require_once __DIR__ . '/parser/class-wp-parser-node.php';
require_once __DIR__ . '/parser/class-wp-parser-token.php';
require_once __DIR__ . '/mysql/class-wp-mysql-token.php';
require_once __DIR__ . '/mysql/class-wp-mysql-lexer.php';
require_once __DIR__ . '/mysql/class-wp-mysql-parser.php';
require_once __DIR__ . '/sqlite/class-wp-sqlite-connection.php';
require_once __DIR__ . '/sqlite/class-wp-sqlite-configurator.php';
require_once __DIR__ . '/sqlite/class-wp-sqlite-driver.php';
require_once __DIR__ . '/sqlite/class-wp-sqlite-driver-exception.php';
require_once __DIR__ . '/sqlite/class-wp-sqlite-information-schema-builder.php';
require_once __DIR__ . '/sqlite/class-wp-sqlite-information-schema-exception.php';
require_once __DIR__ . '/sqlite/class-wp-sqlite-information-schema-reconstructor.php';
require_once __DIR__ . '/sqlite/class-wp-sqlite-pdo-user-defined-functions.php';
require_once __DIR__ . '/sqlite/class-wp-pdo-mysql-on-sqlite.php';
require_once __DIR__ . '/sqlite/class-wp-pdo-proxy-statement.php';
