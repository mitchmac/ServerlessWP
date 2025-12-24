<?php

/**
 * Load the PDO MySQL-on-SQLite driver and its dependencies.
 */
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/wp-includes/parser/class-wp-parser-grammar.php';
require_once __DIR__ . '/wp-includes/parser/class-wp-parser.php';
require_once __DIR__ . '/wp-includes/parser/class-wp-parser-node.php';
require_once __DIR__ . '/wp-includes/parser/class-wp-parser-token.php';
require_once __DIR__ . '/wp-includes/mysql/class-wp-mysql-token.php';
require_once __DIR__ . '/wp-includes/mysql/class-wp-mysql-lexer.php';
require_once __DIR__ . '/wp-includes/mysql/class-wp-mysql-parser.php';
require_once __DIR__ . '/wp-includes/sqlite/class-wp-sqlite-pdo-user-defined-functions.php';
require_once __DIR__ . '/wp-includes/sqlite-ast/class-wp-sqlite-connection.php';
require_once __DIR__ . '/wp-includes/sqlite-ast/class-wp-sqlite-configurator.php';
require_once __DIR__ . '/wp-includes/sqlite-ast/class-wp-sqlite-driver.php';
require_once __DIR__ . '/wp-includes/sqlite-ast/class-wp-sqlite-driver-exception.php';
require_once __DIR__ . '/wp-includes/sqlite-ast/class-wp-sqlite-information-schema-builder.php';
require_once __DIR__ . '/wp-includes/sqlite-ast/class-wp-sqlite-information-schema-exception.php';
require_once __DIR__ . '/wp-includes/sqlite-ast/class-wp-sqlite-information-schema-reconstructor.php';
require_once __DIR__ . '/wp-includes/sqlite-ast/class-wp-pdo-mysql-on-sqlite.php';
