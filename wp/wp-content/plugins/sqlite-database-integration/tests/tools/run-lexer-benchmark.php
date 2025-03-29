<?php

/**
 * This script runs the MySQL lexer on all queries from the MySQL server suite.
 * It ensures the lexer tokenizes all queries and measures lexing performance.
 */

// Throw exception if anything fails.
set_error_handler(
	function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-token.php';
require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-lexer.php';

// Load the queries.
$handle  = fopen( __DIR__ . '/../mysql/data/mysql-server-tests-queries.csv', 'r' );
$records = array();
while ( ( $record = fgetcsv( $handle ) ) !== false ) {
	$records[] = $record;
}

// Run the lexer.
$start = microtime( true );
for ( $i = 0; $i < count( $records ); $i += 1 ) {
	$query  = $records[ $i ][0];
	$lexer  = new WP_MySQL_Lexer( $query );
	$tokens = $lexer->remaining_tokens();
	if ( count( $tokens ) === 0 ) {
		throw new Exception( 'Failed to tokenize query: ' . $query );
	}
}
$duration = microtime( true ) - $start;

// Print the results.
printf( "\nTokenized %d queries in %.5fs @ %d QPS.\n", $i + 1, $duration, ( $i + 1 ) / $duration );
