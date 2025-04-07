<?php

use PHPUnit\Framework\TestCase;

/**
 * Parser tests using the full MySQL server test suite.
 */
class WP_MySQL_Server_Suite_Parser_Tests extends TestCase {
	const TEST_DATA_PATH = __DIR__ . '/data/mysql-server-tests-queries.csv';
	const GRAMMAR_PATH   = __DIR__ . '/../../wp-includes/mysql/mysql-grammar.php';

	/**
	 * Some of the queries in the test suite are known to fail parsing.
	 * We'll skip them in the tests now, gradually fixing these cases.
	 */
	const KNOWN_FAILURES = array(
		'SELECT 1 /*!99999 /* */ */'                       => true,
		'select 1ea10.1a20,1e+ 1e+10 from 1ea10'           => true,
		"聠聡聢聣聤聬聭聮聯聰聲聽隆垄拢陇楼卤潞禄录陆戮 聶職聳聴\n0聲5\n1聲5\n2聲5\n3聲5\n4\n\nSET NAMES gb18030" => true,
		'CREATE TABLE t1 (g GEOMCOLLECTION)'               => true,
		"alter user mysqltest_7@ identified by 'systpass'" => true,
		"SELECT 'a%' LIKE 'a!%' ESCAPE '!', 'a%' LIKE 'a!' || '%' ESCAPE '!'" => true,
		"SELECT 'a%' NOT LIKE 'a!%' ESCAPE '!', 'a%' NOT LIKE 'a!' || '%' ESCAPE '!'" => true,
		"SELECT 'a%' LIKE 'a!%' ESCAPE '$', 'a%' LIKE 'a!' || '%' ESCAPE '$'" => true,
		"SELECT 'a%' NOT LIKE 'a!%' ESCAPE '$', 'a%' NOT LIKE 'a!' || '%' ESCAPE '$'" => true,
		'ALTER SCHEMA s1 READ ONLY DEFAULT'                => true,
	);

	/**
	 * @var WP_Parser_Grammar
	 */
	private static $grammar;

	public static function setUpBeforeClass(): void {
		self::$grammar = new WP_Parser_Grammar( include self::GRAMMAR_PATH );
	}

	/**
	 * Parse all queries from the MySQL server test suite and make sure
	 * it produces some AST and doesn't throw any exceptions.
	 *
	 * The queries need to be batched and parsed in a loop, since the data set
	 * is too large for PHPUnit to run a test per query, causing memory errors.
	 *
	 * @dataProvider data_parse_mysql_test_suite
	 */
	public function test_parse_mysql_test_suite( array $batch ): void {
		foreach ( $batch as $record ) {
			$query = $record[0];

			$lexer  = new WP_MySQL_Lexer( $query );
			$tokens = $lexer->remaining_tokens();
			$this->assertNotEmpty( $tokens, "Failed to tokenize query: $query" );

			$parser = new WP_MySQL_Parser( self::$grammar, $tokens );
			$ast    = $parser->parse();

			if ( self::KNOWN_FAILURES[ $query ] ?? false ) {
				if ( null !== $ast ) {
					$this->assertNull( $ast, "Parsing succeeded, but was expected to fail for query: $query" );
				}
				continue;
			}

			$this->assertNotNull( $ast, "Failed to parse query: $query" );
		}
	}

	public function data_parse_mysql_test_suite(): Generator {
		$path   = __DIR__ . '/data/mysql-server-tests-queries.csv';
		$handle = @fopen( $path, 'r' );
		if ( false === $handle ) {
			$this->fail( "Failed to open file '$path'." );
		}

		try {
			$data  = array();
			$batch = 1;
			while ( ( $record = fgetcsv( $handle ) ) !== false ) {
				$data[] = $record;
				if ( count( $data ) === 1000 ) {
					yield "batch-$batch" => array( $data );
					$batch += 1;
					$data   = array();
				}
			}
			if ( count( $data ) > 0 ) {
				yield "batch-$batch" => array( $data );
			}
		} finally {
			fclose( $handle );
		}
	}

	/**
	 * By default, PHPUnit will dump the whole data set in the error message
	 * when a data provider is used. However, here we are working with a lot
	 * of data, and therefore we suppress the data dump for message clarity.
	 */
	public function getDataSetAsString( bool $include_data = true ): string {
		return parent::getDataSetAsString( false );
	}
}
