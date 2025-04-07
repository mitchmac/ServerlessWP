<?php

use PHPUnit\Framework\TestCase;

/**
 * Lexer tests using the full MySQL server test suite.
 */
class WP_MySQL_Server_Suite_Lexer_Tests extends TestCase {
	/**
	 * Tokenize all queries from the MySQL server test suite and make sure
	 * it produces some tokens and doesn't throw any exceptions.
	 *
	 * The queries need to be run in a single test in a loop, since the data set
	 * is too large for PHPUnit to run a test per query, causing memory errors.
	 */
	public function test_tokenize_mysql_test_suite(): void {
		$path   = __DIR__ . '/data/mysql-server-tests-queries.csv';
		$handle = @fopen( $path, 'r' );
		if ( false === $handle ) {
			$this->fail( "Failed to open file '$path'." );
		}

		try {
			while ( ( $record = fgetcsv( $handle ) ) !== false ) {
				$query  = $record[0];
				$lexer  = new WP_MySQL_Lexer( $query );
				$tokens = $lexer->remaining_tokens();
				$this->assertNotEmpty( $tokens, "Failed to tokenize query: $query" );
			}
		} finally {
			fclose( $handle );
		}
	}
}
