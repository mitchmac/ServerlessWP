<?php

/**
 * This script runs the MySQL lexer & parser on a single query and dumps its AST.
 * It is useful for testing and testing the lexer and parser functionality.
 */

// throw exception if anything fails
set_error_handler(
	function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-token.php';
require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-lexer.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-grammar.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-node.php';
require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-parser.php';

$grammar_data = include __DIR__ . '/../../wp-includes/mysql/mysql-grammar.php';
$grammar      = new WP_Parser_Grammar( $grammar_data );

// Edit the query below to test different inputs:
$lexer  = new WP_MySQL_Lexer( 'SELECT 1' );
$tokens = $lexer->remaining_tokens();

echo "Tokens:\n";
foreach ( $tokens as $token ) {
	echo $token, "\n";
}
$parser = new WP_MySQL_Parser( $grammar, $tokens );
$ast    = $parser->parse();

echo "\n\n";
echo "AST:\n";
var_dump( $ast );
