<?php

use PHPUnit\Framework\TestCase;

class WP_SQLite_Metadata_Tests extends TestCase {
	/** @var WP_SQLite_Translator */
	private $engine;

	/** @var PDO */
	private $sqlite;

	// Before each test, we create a new database
	public function setUp(): void {
		global $blog_tables;
		$queries = explode( ';', $blog_tables );

		$this->sqlite = new PDO( 'sqlite::memory:' );
		$this->engine = new WP_SQLite_Translator( $this->sqlite );

		$translator = $this->engine;

		try {
			$translator->begin_transaction();
			foreach ( $queries as $query ) {
				$query = trim( $query );
				if ( empty( $query ) ) {
					continue;
				}

				$result = $translator->execute_sqlite_query( $query );
				if ( false === $result ) {
					throw new PDOException( $translator->get_error_message() );
				}
			}
			$translator->commit();
		} catch ( PDOException $err ) {
			$err_data =
				$err->errorInfo; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$err_code = $err_data[1];
			$translator->rollback();
			$message  = sprintf(
				'Error occurred while creating tables or indexes...<br />Query was: %s<br />',
				var_export( $query, true )
			);
			$message .= sprintf( 'Error message is: %s', $err_data[2] );
			wp_die( $message, 'Database Error!' );
		}
	}

	public function testCountTables() {
		$this->assertQuery( "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'wpdata'" );

		$actual = $this->engine->get_query_results();
		$count  = array_values( get_object_vars( $actual[0] ) )[0];
		self::assertIsNumeric( $count );
	}

	public function testInformationSchemaTables() {
		$result = $this->assertQuery( "SELECT * FROM information_schema.tables WHERE TABLE_NAME = 'wp_options'" );
		$this->assertEquals(
			array(
				'TABLE_CATALOG'   => 'def',
				'TABLE_SCHEMA'    => '',
				'TABLE_NAME'      => 'wp_options',
				'TABLE_TYPE'      => 'BASE TABLE',
				'ENGINE'          => 'InnoDB',
				'ROW_FORMAT'      => 'Dynamic',
				'TABLE_COLLATION' => 'utf8mb4_general_ci',
				'AUTO_INCREMENT'  => null,
				'CREATE_TIME'     => null,
				'UPDATE_TIME'     => null,
				'CHECK_TIME'      => null,
				'TABLE_ROWS'      => '0',
				'AVG_ROW_LENGTH'  => '0',
				'DATA_LENGTH'     => '0',
				'MAX_DATA_LENGTH' => '0',
				'INDEX_LENGTH'    => '0',
				'DATA_FREE'       => '0',
				'CHECKSUM'        => null,
				'CREATE_OPTIONS'  => '',
				'VERSION'         => '10',
				'TABLE_COMMENT'   => '',
			),
			(array) $result[0]
		);

		$result = $this->assertQuery(
			"SELECT
				table_name as 'name',
				engine AS 'engine',
				FLOOR( data_length / 1024 / 1024 ) 'data'
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_NAME = 'wp_posts'
			ORDER BY name ASC;"
		);

		$this->assertEquals(
			array(
				'name'   => 'wp_posts',
				'engine' => 'InnoDB',
				'data'   => '0',
			),
			(array) $result[0]
		);
	}

	public function testInformationSchemaQueryHidesSqliteSystemTables() {
		/**
		 * By default, system tables are not returned.
		 */
		$result = $this->assertQuery( "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'sqlite_sequence'" );
		$this->assertEquals( 0, count( $result ) );

		/**
		 * If we use a custom name for the table_name column, system tables are returned.
		 */
		$result = $this->assertQuery( "SELECT TABLE_NAME as custom_name FROM INFORMATION_SCHEMA.TABLES WHERE custom_name = 'sqlite_sequence'" );
		$this->assertEquals( 1, count( $result ) );
	}

	private function assertQuery( $sql, $error_substring = null ) {
		$retval = $this->engine->query( $sql );
		if ( null === $error_substring ) {
			$this->assertEquals(
				'',
				$this->engine->get_error_message()
			);
			$this->assertNotFalse(
				$retval
			);
		} else {
			$this->assertStringContainsStringIgnoringCase( $error_substring, $this->engine->get_error_message() );
		}

		return $retval;
	}

	public function testCheckTable() {

		/* a good table */
		$table_name      = 'wp_options';
		$expected_result = array(
			(object) array(
				'Table'    => $table_name,
				'Op'       => 'check',
				'Msg_type' => 'status',
				'Msg_text' => 'OK',
			),
		);

		$this->assertQuery(
			"CHECK TABLE $table_name;"
		);

		$this->assertEquals(
			$expected_result,
			$this->engine->get_query_results()
		);

		/* a different good table */
		$table_name      = 'wp_postmeta';
		$expected_result = array(
			(object) array(
				'Table'    => $table_name,
				'Op'       => 'check',
				'Msg_type' => 'status',
				'Msg_text' => 'OK',
			),
		);

		$this->assertQuery(
			"CHECK TABLE $table_name;"
		);
		$this->assertEquals(
			$expected_result,
			$this->engine->get_query_results()
		);

		/* a bogus, missing, table */
		$table_name      = 'wp_sqlite_rocks';
		$expected_result = array(
			(object) array(
				'Table'    => $table_name,
				'Op'       => 'check',
				'Msg_type' => 'Error',
				'Msg_text' => "Table '$table_name' doesn't exist",
			),
			(object) array(
				'Table'    => $table_name,
				'Op'       => 'check',
				'Msg_type' => 'status',
				'Msg_text' => 'Operation failed',
			),
		);

		$this->assertQuery(
			"CHECK TABLE $table_name;"
		);

		$this->assertEquals(
			$expected_result,
			$this->engine->get_query_results()
		);
	}

	public function testOptimizeTable() {

		/* a good table */
		$table_name = 'wp_options';

		$this->assertQuery(
			"OPTIMIZE TABLE $table_name;"
		);

		$actual = $this->engine->get_query_results();

		array_map(
			function ( $row ) {
				$this->assertIsObject( $row );
				$row = (array) $row;
				$this->assertIsString( $row['Table'] );
				$this->assertIsString( $row['Op'] );
				$this->assertIsString( $row['Msg_type'] );
				$this->assertIsString( $row['Msg_text'] );
			},
			$actual
		);

		$ok = array_filter(
			$actual,
			function ( $row ) {
				$row = (array) $row;

				return strtolower( $row['Msg_type'] ) === 'status' && strtolower( $row['Msg_text'] ) === 'ok';
			}
		);
		$this->assertIsArray( $ok );
		$this->assertGreaterThan( 0, count( $ok ) );
	}

	public function testRepairTable() {

		/* a good table */
		$table_name = 'wp_options';

		$this->assertQuery(
			"REPAIR TABLE $table_name;"
		);

		$actual = $this->engine->get_query_results();

		array_map(
			function ( $r ) {
				$this->assertIsObject( $r );
				$row = $r;
				$row = (array) $row;
				$this->assertIsString( $row['Table'] );
				$this->assertIsString( $row['Op'] );
				$this->assertIsString( $row['Msg_type'] );
				$this->assertIsString( $row['Msg_text'] );
			},
			$actual
		);

		$ok = array_filter(
			$actual,
			function ( $row ) {
				return strtolower( $row->Msg_type ) === 'status' && strtolower( $row->Msg_text ) === 'ok';
			}
		);
		$this->assertIsArray( $ok );
		$this->assertGreaterThan( 0, count( $ok ) );
	}

	// this tests for successful rejection of a bad query

	public function testShowTableStatus() {

		$this->assertQuery(
			"INSERT INTO wp_comments ( comment_author, comment_content ) VALUES ( 'PhpUnit', 'Testing' )"
		);

		$this->assertQuery(
			"INSERT INTO wp_comments ( comment_author, comment_content ) VALUES  ( 'PhpUnit0', 'Testing0' ), ( 'PhpUnit1', 'Testing1' ), ( 'PhpUnit2', 'Testing2' )"
		);

		$this->assertTableEmpty( 'wp_comments', false );

		$this->assertQuery(
			'SHOW TABLE STATUS FROM wp;'
		);

		$actual = $this->engine->get_query_results();

		$this->assertIsArray( $actual );
		$this->assertGreaterThanOrEqual(
			1,
			count( $actual )
		);
		$this->assertIsObject( $actual[0] );

		$rows = array_values(
			array_filter(
				$actual,
				function ( $row ) {
					$this->assertIsObject( $row );
					$this->assertIsString( $row->Name );
					$this->assertIsNumeric( $row->Rows );

					return str_ends_with( $row->Name, 'comments' );
				}
			)
		);
		$this->assertEquals( 'wp_comments', $rows[0]->Name );
		$this->assertEquals( 4, $rows[0]->Rows );
	}

	private function assertTableEmpty( $table_name, $empty_var ) {

		$this->assertQuery(
			"SELECT COUNT(*) num FROM $table_name"
		);

		$actual = $this->engine->get_query_results();
		if ( $empty_var ) {
			$this->assertEquals( 0, $actual[0]->num, "$table_name is not empty" );
		} else {
			$this->assertGreaterThan( 0, $actual[0]->num, "$table_name is empty" );
		}
	}

	public function testTruncateTable() {

		$this->assertQuery(
			"INSERT INTO wp_comments ( comment_author, comment_content ) VALUES ( 'PhpUnit', 'Testing' )"
		);

		$this->assertQuery(
			"INSERT INTO wp_comments ( comment_author, comment_content ) VALUES  ( 'PhpUnit0', 'Testing0' ), ( 'PhpUnit1', 'Testing1' ), ( 'PhpUnit2', 'Testing2' )"
		);

		$this->assertTableEmpty( 'wp_comments', false );

		$this->assertQuery(
			'TRUNCATE TABLE wp_comments;'
		);
		$actual = $this->engine->get_query_results();
		$this->assertEquals(
			true,
			$actual
		);
		$this->assertTableEmpty( 'wp_comments', true );
	}

	public function testBogusQuery() {

		$this->assertQuery(
			'SELECT 1, BOGUS(1) FROM bogus;',
			'no such table: bogus'
		);
		$actual = $this->engine->get_query_results();
		$this->assertEquals(
			null,
			$actual
		);
	}
}
