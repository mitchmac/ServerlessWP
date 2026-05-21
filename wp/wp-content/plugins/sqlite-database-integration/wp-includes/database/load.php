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

/*
 * The MySQL lexer and parser have an optional native (e.g. Rust) implementation.
 * When the native extension is loaded, it pre-declares WP_MySQL_Native_Lexer /
 * WP_MySQL_Native_Parser; otherwise we fall back to the pure-PHP classes shipped
 * here. WP_MySQL_Lexer / WP_MySQL_Parser is the public entrypoint either way.
 */
if ( class_exists( 'WP_MySQL_Native_Lexer', false ) ) {
	require_once __DIR__ . '/mysql/native/class-wp-mysql-lexer.php';
} else {
	require_once __DIR__ . '/mysql/class-wp-mysql-lexer.php';
}

if ( class_exists( 'WP_MySQL_Native_Parser', false ) ) {
	require_once __DIR__ . '/mysql/native/mysql-rust-bridge.php';
	require_once __DIR__ . '/mysql/native/class-wp-mysql-native-parser-node.php';
	require_once __DIR__ . '/mysql/native/trait-wp-mysql-native-parser-impl.php';
	require_once __DIR__ . '/mysql/native/class-wp-mysql-parser.php';
} else {
	require_once __DIR__ . '/mysql/class-wp-mysql-parser.php';
}
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
