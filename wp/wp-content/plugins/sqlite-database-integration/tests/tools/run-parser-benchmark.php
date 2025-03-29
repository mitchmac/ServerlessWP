<?php

/**
 * This script runs the MySQL parser on all queries from the MySQL server suite.
 * It tracks parsing failures and exceptions and measures parsing performance.
 * This is an end-to-end benchmark that includes lexing time in the results.
 */

// Throw exception if anything fails.
set_error_handler(
	function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-token.php';
require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-lexer.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-grammar.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-node.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser.php';
require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-parser.php';

function getStats( $total, $failures, $exceptions ) {
	return sprintf(
		'Total: %5d  |  Failures: %4d / %2d%%  |  Exceptions: %4d / %2d%%',
		$total,
		$failures,
		$failures / $total * 100,
		$exceptions,
		$exceptions / $total * 100
	);
}

// Load the MySQL grammar.
$grammar_data = include __DIR__ . '/../../wp-includes/mysql/mysql-grammar.php';
$grammar      = new WP_Parser_Grammar( $grammar_data );

// Load the queries.
$data_dir = __DIR__ . '/../mysql/data';
$handle   = fopen( "$data_dir/mysql-server-tests-queries.csv", 'r' );
$records  = array();
while ( ( $record = fgetcsv( $handle ) ) !== false ) {
	$records[] = $record;
}

// Run the parser.
$failures   = array();
$exceptions = array();
$start      = microtime( true );
for ( $i = 1; $i < count( $records ); $i += 1 ) {
	$query = $records[ $i ][0];
	if ( null === $query ) {
		continue;
	}

	try {
		$lexer  = new WP_MySQL_Lexer( $query );
		$tokens = $lexer->remaining_tokens();
		if ( count( $tokens ) === 0 ) {
			throw new Exception( 'Failed to tokenize query: ' . $query );
		}

		$parser = new WP_MySQL_Parser( $grammar, $tokens );
		$ast    = $parser->parse();
		if ( null === $ast ) {
			$failures[] = $query;
		}
	} catch ( Exception $e ) {
		$exceptions[] = $query;
	}

	if ( $i > 0 && 0 === $i % 1000 ) {
		echo getStats( $i, count( $failures ), count( $exceptions ) ), "\n";
	}
}
$duration = microtime( true ) - $start;

echo getStats( $i, count( $failures ), count( $exceptions ) ), "\n";

// Print the results.
printf( "\nParsed %d queries in %.5fs @ %d QPS.\n", $i, $duration, $i / $duration );
