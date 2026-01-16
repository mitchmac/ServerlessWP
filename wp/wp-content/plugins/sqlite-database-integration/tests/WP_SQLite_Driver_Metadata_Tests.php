<?php

use PHPUnit\Framework\TestCase;

class WP_SQLite_Driver_Metadata_Tests extends TestCase {
	/** @var WP_SQLite_Driver */
	private $engine;

	/** @var PDO */
	private $sqlite;

	// Before each test, we create a new database
	public function setUp(): void {
		$pdo_class    = PHP_VERSION_ID >= 80400 ? PDO\SQLite::class : PDO::class;
		$this->sqlite = new $pdo_class( 'sqlite::memory:' );
		$this->engine = new WP_SQLite_Driver(
			new WP_SQLite_Connection( array( 'pdo' => $this->sqlite ) ),
			'wp'
		);
	}

	public function testCountTables() {
		$this->assertQuery( 'CREATE TABLE t1 (id INT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT)' );

		$result = $this->assertQuery( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'wp'" );
		$this->assertEquals( array( (object) array( 'COUNT(*)' => '2' ) ), $result );

		$result = $this->assertQuery( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'other'" );
		$this->assertEquals( array( (object) array( 'COUNT(*)' => '0' ) ), $result );
	}

	public function testInformationSchemaTables() {
		$this->assertQuery(
			'
			CREATE TABLE t (id INT PRIMARY KEY, name TEXT, age INT)
		'
		);

		$result = $this->assertQuery( "SELECT * FROM information_schema.tables WHERE TABLE_NAME = 't'" );
		$this->assertCount( 1, $result );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $result[0]->CREATE_TIME );
		$this->assertEquals(
			array(
				'TABLE_CATALOG'   => 'def',
				'TABLE_SCHEMA'    => 'wp',
				'TABLE_NAME'      => 't',
				'TABLE_TYPE'      => 'BASE TABLE',
				'ENGINE'          => 'InnoDB',
				'VERSION'         => '10',
				'ROW_FORMAT'      => 'Dynamic',
				'TABLE_ROWS'      => '0',
				'AVG_ROW_LENGTH'  => '0',
				'DATA_LENGTH'     => '0',
				'MAX_DATA_LENGTH' => '0',
				'INDEX_LENGTH'    => '0',
				'DATA_FREE'       => '0',
				'AUTO_INCREMENT'  => null,
				'CREATE_TIME'     => $result[0]->CREATE_TIME,
				'UPDATE_TIME'     => null,
				'CHECK_TIME'      => null,
				'TABLE_COLLATION' => 'utf8mb4_0900_ai_ci',
				'CHECKSUM'        => null,
				'CREATE_OPTIONS'  => '',
				'TABLE_COMMENT'   => '',
			),
			(array) $result[0]
		);

		$result = $this->assertQuery(
			"SELECT
				table_name as 'name',
				engine AS 'engine',
				CAST( data_length / 1024 / 1024 AS UNSIGNED ) AS 'data'
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_NAME = 't'
			ORDER BY name ASC"
		);

		$this->assertEquals(
			array(
				'name'   => 't',
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
	}

	public function testInfromationSchemaCharacterSets(): void {
		$result = $this->assertQuery( 'SELECT * FROM INFORMATION_SCHEMA.CHARACTER_SETS ORDER BY CHARACTER_SET_NAME' );
		$this->assertEquals(
			array(
				(object) array(
					'CHARACTER_SET_NAME'   => 'binary',
					'DEFAULT_COLLATE_NAME' => 'binary',
					'DESCRIPTION'          => 'Binary pseudo charset',
					'MAXLEN'               => '1',
				),
				(object) array(
					'CHARACTER_SET_NAME'   => 'utf8',
					'DEFAULT_COLLATE_NAME' => 'utf8_general_ci',
					'DESCRIPTION'          => 'UTF-8 Unicode',
					'MAXLEN'               => '3',
				),
				(object) array(
					'CHARACTER_SET_NAME'   => 'utf8mb4',
					'DEFAULT_COLLATE_NAME' => 'utf8mb4_0900_ai_ci',
					'DESCRIPTION'          => 'UTF-8 Unicode',
					'MAXLEN'               => '4',
				),
			),
			$result
		);
	}

	public function testInfromationSchemaCollations(): void {
		$result = $this->assertQuery( 'SELECT * FROM INFORMATION_SCHEMA.COLLATIONS ORDER BY COLLATION_NAME' );
		$this->assertEquals(
			array(
				(object) array(
					'COLLATION_NAME'     => 'binary',
					'CHARACTER_SET_NAME' => 'binary',
					'ID'                 => '63',
					'IS_DEFAULT'         => 'Yes',
					'IS_COMPILED'        => 'Yes',
					'SORTLEN'            => '1',
					'PAD_ATTRIBUTE'      => 'NO PAD',
				),
				(object) array(
					'COLLATION_NAME'     => 'utf8_bin',
					'CHARACTER_SET_NAME' => 'utf8',
					'ID'                 => '83',
					'IS_DEFAULT'         => '',
					'IS_COMPILED'        => 'Yes',
					'SORTLEN'            => '1',
					'PAD_ATTRIBUTE'      => 'PAD SPACE',
				),
				(object) array(
					'COLLATION_NAME'     => 'utf8_general_ci',
					'CHARACTER_SET_NAME' => 'utf8',
					'ID'                 => '33',
					'IS_DEFAULT'         => 'Yes',
					'IS_COMPILED'        => 'Yes',
					'SORTLEN'            => '1',
					'PAD_ATTRIBUTE'      => 'PAD SPACE',
				),
				(object) array(
					'COLLATION_NAME'     => 'utf8_unicode_ci',
					'CHARACTER_SET_NAME' => 'utf8',
					'ID'                 => '192',
					'IS_DEFAULT'         => '',
					'IS_COMPILED'        => 'Yes',
					'SORTLEN'            => '8',
					'PAD_ATTRIBUTE'      => 'PAD SPACE',
				),
				(object) array(
					'COLLATION_NAME'     => 'utf8mb4_0900_ai_ci',
					'CHARACTER_SET_NAME' => 'utf8mb4',
					'ID'                 => '255',
					'IS_DEFAULT'         => 'Yes',
					'IS_COMPILED'        => 'Yes',
					'SORTLEN'            => '0',
					'PAD_ATTRIBUTE'      => 'NO PAD',
				),
				(object) array(
					'COLLATION_NAME'     => 'utf8mb4_bin',
					'CHARACTER_SET_NAME' => 'utf8mb4',
					'ID'                 => '46',
					'IS_DEFAULT'         => '',
					'IS_COMPILED'        => 'Yes',
					'SORTLEN'            => '1',
					'PAD_ATTRIBUTE'      => 'PAD SPACE',
				),
				(object) array(
					'COLLATION_NAME'     => 'utf8mb4_unicode_ci',
					'CHARACTER_SET_NAME' => 'utf8mb4',
					'ID'                 => '224',
					'IS_DEFAULT'         => '',
					'IS_COMPILED'        => 'Yes',
					'SORTLEN'            => '8',
					'PAD_ATTRIBUTE'      => 'PAD SPACE',
				),
			),
			$result
		);
	}

	public function testUseStatement() {
		$this->assertQuery( 'CREATE TABLE tables (ENGINE TEXT)' );
		$this->assertQuery( "INSERT INTO tables (ENGINE) VALUES ('test')" );

		$this->assertQuery( 'USE wp' );
		$result = $this->assertQuery( 'SELECT * FROM tables' );
		$this->assertSame( 'test', $result[0]->ENGINE );

		$this->assertQuery( 'USE information_schema' );
		$result = $this->assertQuery( 'SELECT * FROM tables' );
		$this->assertSame( 'InnoDB', $result[0]->ENGINE );
	}

	private function assertQuery( $sql ) {
		$retval = $this->engine->query( $sql );
		$this->assertNotFalse( $retval );
		return $retval;
	}

	public function testCheckTable() {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		// A good table.
		$result = $this->assertQuery( 'CHECK TABLE t1' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'check',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			),
			$result
		);

		// Multiple tables.
		$result = $this->assertQuery( 'CHECK TABLE t1, t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'check',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
				(object) array(
					'Table'    => 'wp.t2',
					'Op'       => 'check',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			),
			$result
		);

		/**
		 * With SQLite < 3.33.0, the integrity check operation doesn't throw
		 * an error for missing tables. Let's reflect this in the assertions.
		 */
		$is_strict_integrity_check_supported = version_compare( $this->engine->get_sqlite_version(), '3.33.0', '>=' );

		// A missing table.
		$result   = $this->assertQuery( 'CHECK TABLE missing' );
		$expected = array(
			(object) array(
				'Table'    => 'wp.missing',
				'Op'       => 'check',
				'Msg_type' => 'Error',
				'Msg_text' => "Table 'missing' doesn't exist",
			),
			(object) array(
				'Table'    => 'wp.missing',
				'Op'       => 'check',
				'Msg_type' => 'status',
				'Msg_text' => 'Operation failed',
			),
		);

		if ( ! $is_strict_integrity_check_supported ) {
			$expected = array(
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'check',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			);
		}

		$this->assertEquals( $expected, $result );

		// One good and one missing table.
		$result   = $this->assertQuery( 'CHECK TABLE t1, missing' );
		$expected = array(
			(object) array(
				'Table'    => 'wp.t1',
				'Op'       => 'check',
				'Msg_type' => 'status',
				'Msg_text' => 'OK',
			),
			(object) array(
				'Table'    => 'wp.missing',
				'Op'       => 'check',
				'Msg_type' => 'Error',
				'Msg_text' => "Table 'missing' doesn't exist",
			),
			(object) array(
				'Table'    => 'wp.missing',
				'Op'       => 'check',
				'Msg_type' => 'status',
				'Msg_text' => 'Operation failed',
			),
		);

		if ( ! $is_strict_integrity_check_supported ) {
			$expected = array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'check',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'check',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			);
		}

		$this->assertEquals( $expected, $result );
	}

	public function testOptimizeTable() {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		// A good table.
		$result = $this->assertQuery( 'OPTIMIZE TABLE t1' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'optimize',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			),
			$result
		);

		// Multiple tables.
		$result = $this->assertQuery( 'OPTIMIZE TABLE t1, t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'optimize',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
				(object) array(
					'Table'    => 'wp.t2',
					'Op'       => 'optimize',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			),
			$result
		);

		// A missing table.
		$this->assertQuery( 'OPTIMIZE TABLE missing' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'optimize',
					'Msg_type' => 'Error',
					'Msg_text' => "Table 'missing' doesn't exist",
				),
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'optimize',
					'Msg_type' => 'status',
					'Msg_text' => 'Operation failed',
				),
			),
			$this->engine->get_query_results()
		);

		// One good and one missing table.
		$result = $this->assertQuery( 'OPTIMIZE TABLE t1, missing' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'optimize',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'optimize',
					'Msg_type' => 'Error',
					'Msg_text' => "Table 'missing' doesn't exist",
				),
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'optimize',
					'Msg_type' => 'status',
					'Msg_text' => 'Operation failed',
				),
			),
			$result
		);
	}

	public function testRepairTable() {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		// A good table.
		$result = $this->assertQuery( 'REPAIR TABLE t1' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'repair',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			),
			$result
		);

		// Multiple tables.
		$result = $this->assertQuery( 'REPAIR TABLE t1, t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'repair',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
				(object) array(
					'Table'    => 'wp.t2',
					'Op'       => 'repair',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			),
			$result
		);

		// A missing table.
		$result = $this->assertQuery( 'REPAIR TABLE missing' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'repair',
					'Msg_type' => 'Error',
					'Msg_text' => "Table 'missing' doesn't exist",
				),
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'repair',
					'Msg_type' => 'status',
					'Msg_text' => 'Operation failed',
				),
			),
			$result
		);

		// One good and one missing table.
		$result = $this->assertQuery( 'REPAIR TABLE t1, missing' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'    => 'wp.t1',
					'Op'       => 'repair',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'repair',
					'Msg_type' => 'Error',
					'Msg_text' => "Table 'missing' doesn't exist",
				),
				(object) array(
					'Table'    => 'wp.missing',
					'Op'       => 'repair',
					'Msg_type' => 'status',
					'Msg_text' => 'Operation failed',
				),
			),
			$result
		);
	}

	public function testShowCollation(): void {
		// Simple.
		$this->assertQuery( 'SHOW COLLATION' );
		$actual = $this->engine->get_query_results();
		$this->assertCount( 7, $actual );
		$this->assertEquals( 'binary', $actual[0]->Collation );
		$this->assertEquals( 'utf8_bin', $actual[1]->Collation );
		$this->assertEquals( 'utf8_general_ci', $actual[2]->Collation );
		$this->assertEquals( 'utf8_unicode_ci', $actual[3]->Collation );
		$this->assertEquals( 'utf8mb4_bin', $actual[4]->Collation );
		$this->assertEquals( 'utf8mb4_unicode_ci', $actual[5]->Collation );
		$this->assertEquals( 'utf8mb4_0900_ai_ci', $actual[6]->Collation );

		// With LIKE clause.
		$this->assertQuery( "SHOW COLLATION LIKE 'utf8%'" );
		$actual = $this->engine->get_query_results();
		$this->assertCount( 6, $actual );
		$this->assertEquals( 'utf8_bin', $actual[0]->Collation );
		$this->assertEquals( 'utf8_general_ci', $actual[1]->Collation );
		$this->assertEquals( 'utf8_unicode_ci', $actual[2]->Collation );
		$this->assertEquals( 'utf8mb4_bin', $actual[3]->Collation );

		// With WHERE clause.
		$this->assertQuery( "SHOW COLLATION WHERE Collation = 'utf8_bin'" );
		$actual = $this->engine->get_query_results();
		$this->assertCount( 1, $actual );
		$this->assertEquals( 'utf8_bin', $actual[0]->Collation );
	}

	public function testShowDatabases(): void {
		// Simple.
		$this->assertQuery( 'SHOW DATABASES' );
		$actual = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array( 'Database' => 'information_schema' ),
				(object) array( 'Database' => 'wp' ),
			),
			$actual
		);

		// With LIKE clause.
		$this->assertQuery( 'SHOW DATABASES LIKE "w%"' );
		$actual = $this->engine->get_query_results();
		$this->assertEquals(
			array( (object) array( 'Database' => 'wp' ) ),
			$actual
		);

		// With WHERE clause.
		$this->assertQuery( 'SHOW DATABASES WHERE `Database` = "wp"' );
		$actual = $this->engine->get_query_results();
		$this->assertEquals(
			array( (object) array( 'Database' => 'wp' ) ),
			$actual
		);
	}

	public function testShowTableSchemas(): void {
		$this->assertQuery( 'SHOW SCHEMAS' );

		$actual = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array( 'Database' => 'information_schema' ),
				(object) array( 'Database' => 'wp' ),
			),
			$actual
		);

		// With LIKE clause.
		$this->assertQuery( 'SHOW DATABASES LIKE "inf%"' );
		$actual = $this->engine->get_query_results();
		$this->assertEquals(
			array( (object) array( 'Database' => 'information_schema' ) ),
			$actual
		);

		// With WHERE clause.
		$this->assertQuery( 'SHOW DATABASES WHERE `Database` = "information_schema"' );
		$actual = $this->engine->get_query_results();
		$this->assertEquals(
			array( (object) array( 'Database' => 'information_schema' ) ),
			$actual
		);
	}

	public function testShowTableStatus() {
		$this->assertQuery( 'CREATE TABLE t ( comment_author TEXT, comment_content TEXT )' );

		$this->assertQuery(
			"INSERT INTO t ( comment_author, comment_content ) VALUES ( 'PhpUnit', 'Testing' )"
		);

		$this->assertQuery(
			"INSERT INTO t ( comment_author, comment_content ) VALUES  ( 'PhpUnit0', 'Testing0' ), ( 'PhpUnit1', 'Testing1' ), ( 'PhpUnit2', 'Testing2' )"
		);

		$this->assertTableEmpty( 't', false );
		$results = $this->assertQuery( 'SHOW TABLE STATUS FROM wp' );

		$this->assertEquals(
			array(
				(object) array(
					'Name'            => 't',
					'Engine'          => 'InnoDB',
					'Version'         => '10',
					'Row_format'      => 'Dynamic',
					'Rows'            => '0',
					'Avg_row_length'  => '0',
					'Data_length'     => '0',
					'Max_data_length' => '0',
					'Index_length'    => '0',
					'Data_free'       => '0',
					'Auto_increment'  => null,
					'Create_time'     => $results[0]->Create_time,
					'Update_time'     => null,
					'Check_time'      => null,
					'Collation'       => 'utf8mb4_0900_ai_ci',
					'Checksum'        => null,
					'Create_options'  => '',
					'Comment'         => '',
				),
			),
			$results
		);
	}

	public function testShowColumnsLike(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, val1 INT, val2 INT, name TEXT)' );
		$result = $this->assertQuery( "SHOW COLUMNS FROM t LIKE 'val%'" );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'val1',
					'Type'    => 'int',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'val2',
					'Type'    => 'int',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$result
		);
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
			'CREATE TABLE wp_comments ( comment_author TEXT, comment_content TEXT )'
		);

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
		$this->expectExceptionMessage( 'no such table: bogus' );
		$this->assertQuery(
			'SELECT 1, BOGUS(1) FROM bogus;'
		);
	}

	public function testInformationSchemaTableConstraintsCreateTable(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				a INT PRIMARY KEY,
				b INT UNIQUE,
				c INT,
				d INT,
				CONSTRAINT unique_b_c UNIQUE (b, c),
				INDEX inex_c_d (c, d)
			)'
		);

		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'b',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'unique_b_c',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);
	}

	public function testInformationSchemaTableConstraintsDropTable(): void {
		$this->assertQuery( 'CREATE TABLE t (a INT PRIMARY KEY, b INT UNIQUE)' );
		$this->assertQuery( 'DROP TABLE t' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals( array(), $result );
	}

	public function testInformationSchemaTableConstraintsAddColumn(): void {
		$this->assertQuery(
			'CREATE TABLE t ( a INT )'
		);

		// Add a column with a primary key constraint.
		$this->assertQuery( 'ALTER TABLE t ADD COLUMN b INT PRIMARY KEY' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		$this->assertQuery( 'ALTER TABLE t ADD COLUMN c INT UNIQUE' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);
	}

	public function testInformationSchemaTableConstraintsChangeColumn(): void {
		$this->assertQuery( 'CREATE TABLE t (a INT, b INT)' );

		// Add a primary key constraint.
		$this->assertQuery( 'ALTER TABLE t CHANGE COLUMN a a INT PRIMARY KEY' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// Add a unique constraint.
		$this->assertQuery( 'ALTER TABLE t MODIFY COLUMN b INT UNIQUE' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'b',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);
	}

	public function testInformationSchemaTableConstraintsDropColumn(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				id INT,
				a INT,
				b INT,
				c INT,
				CONSTRAINT c_primary PRIMARY KEY (a, b),
				CONSTRAINT c_unique UNIQUE (b, c),
				INDEX id (a, b, c)
			)'
		);

		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c_unique',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// Drop column "b" - all constraints will remain.
		$this->assertQuery( 'ALTER TABLE t DROP COLUMN b' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c_unique',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// Drop column "c" - the unique constraint will be removed.
		$this->assertQuery( 'ALTER TABLE t DROP COLUMN c' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// Drop column "a" - the primary key will be removed.
		$this->assertQuery( 'ALTER TABLE t DROP COLUMN a' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals( array(), $result );
	}

	public function testInformationSchemaTableConstraintsAddConstraint(): void {
		$this->assertQuery( 'CREATE TABLE t (a INT, b INT)' );

		// Add a primary key constraint.
		$this->assertQuery( 'ALTER TABLE t ADD CONSTRAINT primary_key_a PRIMARY KEY (a)' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// Add a unique constraint.
		$this->assertQuery( 'ALTER TABLE t ADD CONSTRAINT unique_b UNIQUE (b)' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'unique_b',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// Add a unique constraint with a composite key.
		$this->assertQuery( 'ALTER TABLE t ADD CONSTRAINT unique_a_b UNIQUE (a, b)' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'PRIMARY',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'PRIMARY KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'unique_b',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'unique_a_b',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);
	}

	public function testInformationSchemaTableConstraintsDropIndex(): void {
		$this->assertQuery( 'CREATE TABLE t (a INT PRIMARY KEY, b INT UNIQUE)' );

		// Drop the primary key index.
		$this->assertQuery( 'ALTER TABLE t DROP INDEX `PRIMARY`' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'b',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'UNIQUE',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// Drop the unique index.
		$this->assertQuery( 'ALTER TABLE t DROP INDEX b' );
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertEquals( array(), $result );
	}

	public function testInformationSchemaForeignKeys(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery(
			'CREATE TABLE t2 (
				id INT,
				FOREIGN KEY (id) REFERENCES t1 (id),
				FOREIGN KEY idx_name (id) REFERENCES t1 (id),
				CONSTRAINT fk1 FOREIGN KEY (id) REFERENCES t1 (id),
				CONSTRAINT fk2 FOREIGN KEY idx_name (id) REFERENCES t1 (id),
				CONSTRAINT fk3 FOREIGN KEY (id) REFERENCES t1 (id) ON DELETE CASCADE,
				CONSTRAINT fk4 FOREIGN KEY (id) REFERENCES t1 (id) ON UPDATE CASCADE,
				CONSTRAINT fk5 FOREIGN KEY (id) REFERENCES t1 (id) ON DELETE CASCADE ON UPDATE CASCADE
			)'
		);

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't2_ibfk_1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't2_ibfk_2',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'fk1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'fk2',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'fk3',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'fk4',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'fk5',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 't2_ibfk_1',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'NO ACTION',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 't2_ibfk_2',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'NO ACTION',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 'fk1',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'NO ACTION',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 'fk2',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'NO ACTION',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 'fk3',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'CASCADE',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 'fk4',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'CASCADE',
					'DELETE_RULE'               => 'NO ACTION',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 'fk5',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'CASCADE',
					'DELETE_RULE'               => 'CASCADE',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.KEY_COLUMN_USAGE
		$result = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 't2_ibfk_1',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 't2_ibfk_2',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 'fk1',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 'fk2',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 'fk3',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 'fk4',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 'fk5',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
			),
			$result
		);

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't2',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t2` (',
							'  `id` int DEFAULT NULL,',
							'  CONSTRAINT `fk1` FOREIGN KEY (`id`) REFERENCES `t1` (`id`),',
							'  CONSTRAINT `fk2` FOREIGN KEY (`id`) REFERENCES `t1` (`id`),',
							'  CONSTRAINT `fk3` FOREIGN KEY (`id`) REFERENCES `t1` (`id`) ON DELETE CASCADE,',
							'  CONSTRAINT `fk4` FOREIGN KEY (`id`) REFERENCES `t1` (`id`) ON UPDATE CASCADE,',
							'  CONSTRAINT `fk5` FOREIGN KEY (`id`) REFERENCES `t1` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,',
							'  CONSTRAINT `t2_ibfk_1` FOREIGN KEY (`id`) REFERENCES `t1` (`id`),',
							'  CONSTRAINT `t2_ibfk_2` FOREIGN KEY (`id`) REFERENCES `t1` (`id`)',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);
	}

	public function testInformationSchemaInlineForeignKeys(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name VARCHAR(255))' );
		$this->assertQuery(
			'CREATE TABLE t2 (
				id INT,
				t1_id INT REFERENCES t1 (id),
				t1_name VARCHAR(255) REFERENCES t1 (name) ON DELETE CASCADE
			)'
		);

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't2_ibfk_1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't2_ibfk_2',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 't2_ibfk_1',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => null,
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'NO ACTION',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 't2_ibfk_2',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => null,
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'CASCADE',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.KEY_COLUMN_USAGE
		$result = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 't2_ibfk_1',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 't1_id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 't2_ibfk_2',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 't1_name',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'name',
				),
			),
			$result
		);

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't2',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t2` (',
							'  `id` int DEFAULT NULL,',
							'  `t1_id` int DEFAULT NULL,',
							'  `t1_name` varchar(255) DEFAULT NULL,',
							'  CONSTRAINT `t2_ibfk_1` FOREIGN KEY (`t1_id`) REFERENCES `t1` (`id`),',
							'  CONSTRAINT `t2_ibfk_2` FOREIGN KEY (`t1_name`) REFERENCES `t1` (`name`) ON DELETE CASCADE',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);
	}

	public function testInformationSchemaForeignKeysWithMultipleColumns(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name VARCHAR(255))' );
		$this->assertQuery(
			'CREATE TABLE t2 (
				id INT,
				name VARCHAR(255),
				FOREIGN KEY (id, name) REFERENCES t1 (id, name)
			)'
		);

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't2_ibfk_1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 't2_ibfk_1',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => null,
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'NO ACTION',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.KEY_COLUMN_USAGE
		$result = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 't2_ibfk_1',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 't2_ibfk_1',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'name',
					'ORDINAL_POSITION'              => '2',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '2',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'name',
				),
			),
			$result
		);

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't2',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t2` (',
							'  `id` int DEFAULT NULL,',
							'  `name` varchar(255) DEFAULT NULL,',
							'  CONSTRAINT `t2_ibfk_1` FOREIGN KEY (`id`, `name`) REFERENCES `t1` (`id`, `name`)',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);
	}

	public function testInformationSchemaAlterTableAddForeignKeys(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY, name VARCHAR(255))' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT)' );
		$this->assertQuery( 'ALTER TABLE t2 ADD FOREIGN KEY (id) REFERENCES t1 (id)' );
		$this->assertQuery( 'ALTER TABLE t2 ADD CONSTRAINT fk1 FOREIGN KEY (id) REFERENCES t1 (id) ON DELETE CASCADE' );

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't2_ibfk_1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'fk1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't2',
					'CONSTRAINT_TYPE'    => 'FOREIGN KEY',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 't2_ibfk_1',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'NO ACTION',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
				(object) array(
					'CONSTRAINT_CATALOG'        => 'def',
					'CONSTRAINT_SCHEMA'         => 'wp',
					'CONSTRAINT_NAME'           => 'fk1',
					'UNIQUE_CONSTRAINT_CATALOG' => 'def',
					'UNIQUE_CONSTRAINT_SCHEMA'  => 'wp',
					'UNIQUE_CONSTRAINT_NAME'    => 'PRIMARY',
					'MATCH_OPTION'              => 'NONE',
					'UPDATE_RULE'               => 'NO ACTION',
					'DELETE_RULE'               => 'CASCADE',
					'TABLE_NAME'                => 't2',
					'REFERENCED_TABLE_NAME'     => 't1',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.KEY_COLUMN_USAGE
		$result = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 't2_ibfk_1',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
				(object) array(
					'CONSTRAINT_CATALOG'            => 'def',
					'CONSTRAINT_SCHEMA'             => 'wp',
					'CONSTRAINT_NAME'               => 'fk1',
					'TABLE_CATALOG'                 => 'def',
					'TABLE_SCHEMA'                  => 'wp',
					'TABLE_NAME'                    => 't2',
					'COLUMN_NAME'                   => 'id',
					'ORDINAL_POSITION'              => '1',
					'POSITION_IN_UNIQUE_CONSTRAINT' => '1',
					'REFERENCED_TABLE_SCHEMA'       => 'wp',
					'REFERENCED_TABLE_NAME'         => 't1',
					'REFERENCED_COLUMN_NAME'        => 'id',
				),
			),
			$result
		);

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't2',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t2` (',
							'  `id` int DEFAULT NULL,',
							'  CONSTRAINT `fk1` FOREIGN KEY (`id`) REFERENCES `t1` (`id`) ON DELETE CASCADE,',
							'  CONSTRAINT `t2_ibfk_1` FOREIGN KEY (`id`) REFERENCES `t1` (`id`)',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);
	}

	public function testInformationSchemaAlterTableDropForeignKeys(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY, name VARCHAR(255))' );
		$this->assertQuery(
			'CREATE TABLE t2 (
				id INT,
				t1_id INT REFERENCES t1 (id),
				FOREIGN KEY (t1_id) REFERENCES t1 (id),
				CONSTRAINT fk1 FOREIGN KEY (t1_id) REFERENCES t1 (id) ON DELETE CASCADE
			)'
		);

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$this->assertCount( 3, $result );

		// INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$this->assertCount( 3, $result );

		// INFORMATION_SCHEMA.KEY_COLUMN_USAGE
		$result = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$this->assertCount( 3, $result );

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't2',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t2` (',
							'  `id` int DEFAULT NULL,',
							'  `t1_id` int DEFAULT NULL,',
							'  CONSTRAINT `fk1` FOREIGN KEY (`t1_id`) REFERENCES `t1` (`id`) ON DELETE CASCADE,',
							'  CONSTRAINT `t2_ibfk_1` FOREIGN KEY (`t1_id`) REFERENCES `t1` (`id`),',
							'  CONSTRAINT `t2_ibfk_2` FOREIGN KEY (`t1_id`) REFERENCES `t1` (`id`)',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);

		// DROP the first foreign key.
		$this->assertQuery( 'ALTER TABLE t2 DROP FOREIGN KEY t2_ibfk_1' );

		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$this->assertCount( 2, $result );
		$this->assertEquals( 't2_ibfk_2', $result[0]->CONSTRAINT_NAME );
		$this->assertEquals( 'fk1', $result[1]->CONSTRAINT_NAME );

		$result = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$this->assertCount( 2, $result );
		$this->assertEquals( 't2_ibfk_2', $result[0]->CONSTRAINT_NAME );
		$this->assertEquals( 'fk1', $result[1]->CONSTRAINT_NAME );

		$result = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$this->assertCount( 2, $result );
		$this->assertEquals( 't2_ibfk_2', $result[0]->CONSTRAINT_NAME );
		$this->assertEquals( 'fk1', $result[1]->CONSTRAINT_NAME );

		$result = $this->assertQuery( 'SHOW CREATE TABLE t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't2',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t2` (',
							'  `id` int DEFAULT NULL,',
							'  `t1_id` int DEFAULT NULL,',
							'  CONSTRAINT `fk1` FOREIGN KEY (`t1_id`) REFERENCES `t1` (`id`) ON DELETE CASCADE,',
							'  CONSTRAINT `t2_ibfk_2` FOREIGN KEY (`t1_id`) REFERENCES `t1` (`id`)',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);

		// DROP the second foreign key.
		$this->assertQuery( 'ALTER TABLE t2 DROP FOREIGN KEY t2_ibfk_2' );

		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'fk1', $result[0]->CONSTRAINT_NAME );

		$result = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'fk1', $result[0]->CONSTRAINT_NAME );

		$result = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'fk1', $result[0]->CONSTRAINT_NAME );

		$result = $this->assertQuery( 'SHOW CREATE TABLE t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't2',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t2` (',
							'  `id` int DEFAULT NULL,',
							'  `t1_id` int DEFAULT NULL,',
							'  CONSTRAINT `fk1` FOREIGN KEY (`t1_id`) REFERENCES `t1` (`id`) ON DELETE CASCADE',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);

		// DROP the third foreign key.
		$this->assertQuery( 'ALTER TABLE t2 DROP FOREIGN KEY fk1' );

		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$this->assertCount( 0, $result );

		$result = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$this->assertCount( 0, $result );

		$result = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$this->assertCount( 0, $result );

		$result = $this->assertQuery( 'SHOW CREATE TABLE t2' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't2',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t2` (',
							'  `id` int DEFAULT NULL,',
							'  `t1_id` int DEFAULT NULL',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);
	}

	public function testInformationSchemaAlterTableDropPrimaryKey(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))' );

		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t` (',
							'  `id` int NOT NULL,',
							'  `name` varchar(255) DEFAULT NULL,',
							'  PRIMARY KEY (`id`)',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);

		$this->assertQuery( 'ALTER TABLE t DROP PRIMARY KEY' );

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertCount( 0, $result );

		// INFORMATION_SCHEMA.STATISTICS
		$result = $this->assertQuery( "SELECT * FROM information_schema.statistics WHERE table_name = 't'" );
		$this->assertCount( 0, $result );

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t` (',
							'  `id` int NOT NULL,',
							'  `name` varchar(255) DEFAULT NULL',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);
	}

	public function testInformationSchemaAlterTableDropUniqueKey(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, name TEXT, CONSTRAINT c UNIQUE (name))' );
		$this->assertQuery( 'ALTER TABLE t DROP INDEX c' );

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );

		// INFORMATION_SCHEMA.STATISTICS
		$result = $this->assertQuery( "SELECT * FROM information_schema.statistics WHERE table_name = 't'" );
		$this->assertCount( 0, $result );

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t` (',
							'  `id` int DEFAULT NULL,',
							'  `name` text DEFAULT NULL',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);
	}

	public function testInformationSchemaAlterTableDropConstraint(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY, name VARCHAR(255))' );
		$this->assertQuery(
			'CREATE TABLE t2 (
				id INT PRIMARY KEY,
				name VARCHAR(255),
				CONSTRAINT fk FOREIGN KEY (id) REFERENCES t1 (id),
				CONSTRAINT name_unique UNIQUE (name)
			)'
		);

		// Check the constraint records.
		$table_constraints       = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$referential_constraints = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$key_column_usage        = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$statistics              = $this->assertQuery( "SELECT * FROM information_schema.statistics WHERE table_name = 't2'" );
		$this->assertCount( 3, $table_constraints );
		$this->assertCount( 1, $referential_constraints );
		$this->assertCount( 3, $key_column_usage );
		$this->assertCount( 2, $statistics );

		// Drop the primary key constraint.
		$this->assertQuery( 'ALTER TABLE t2 DROP PRIMARY KEY' );
		$table_constraints       = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$referential_constraints = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$key_column_usage        = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$statistics              = $this->assertQuery( "SELECT * FROM information_schema.statistics WHERE table_name = 't2'" );
		$this->assertCount( 2, $table_constraints );
		$this->assertCount( 1, $referential_constraints );
		$this->assertCount( 2, $key_column_usage );
		$this->assertCount( 1, $statistics );

		// Drop the unique key constraint.
		$this->assertQuery( 'ALTER TABLE t2 DROP CONSTRAINT name_unique' );
		$table_constraints       = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$referential_constraints = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$key_column_usage        = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$statistics              = $this->assertQuery( "SELECT * FROM information_schema.statistics WHERE table_name = 't2'" );
		$this->assertCount( 1, $table_constraints );
		$this->assertCount( 1, $referential_constraints );
		$this->assertCount( 1, $key_column_usage );
		$this->assertCount( 0, $statistics );

		// Drop the foreign key constraint.
		$this->assertQuery( 'ALTER TABLE t2 DROP FOREIGN KEY fk' );
		$table_constraints       = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't2'" );
		$referential_constraints = $this->assertQuery( "SELECT * FROM information_schema.referential_constraints WHERE table_name = 't2'" );
		$key_column_usage        = $this->assertQuery( "SELECT * FROM information_schema.key_column_usage WHERE table_name = 't2'" );
		$statistics              = $this->assertQuery( "SELECT * FROM information_schema.statistics WHERE table_name = 't2'" );
		$this->assertCount( 0, $table_constraints );
		$this->assertCount( 0, $referential_constraints );
		$this->assertCount( 0, $key_column_usage );
		$this->assertCount( 0, $statistics );
	}

	public function testInformationSchemaAlterTableDropMissingConstraint(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );

		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( "SQLSTATE[HY000]: General error: 3940 Constraint 'cnst' does not exist." );
		$this->expectExceptionCode( 'HY000' );
		$this->assertQuery( 'ALTER TABLE t2 DROP CONSTRAINT cnst' );
	}

	public function testInformationSchemaAlterTableDropConstraintWithAmbiguousName(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY, name VARCHAR(255))' );
		$this->assertQuery(
			'CREATE TABLE t2 (
				id INT,
				CONSTRAINT cnst UNIQUE (id),
				CONSTRAINT cnst FOREIGN KEY (id) REFERENCES t1 (id)
			)'
		);

		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( "SQLSTATE[HY000]: General error: 3939 Table has multiple constraints with the name 'cnst'. Please use constraint specific 'DROP' clause." );
		$this->expectExceptionCode( 'HY000' );
		$this->assertQuery( 'ALTER TABLE t2 DROP CONSTRAINT cnst' );
	}

	public function testInformationSchemaCheckConstraints(): void {
		$this->assertQuery(
			"CREATE TABLE t (
				id INT NOT NULL CHECK (id > 0),
				name VARCHAR(255) NOT NULL CHECK (name != ''),
				score DOUBLE NOT NULL CHECK (score > 0 AND score < 100),
				data JSON CHECK (json_valid(data)),
				start_timestamp TIMESTAMP NOT NULL,
				end_timestamp TIMESTAMP NOT NULL,
				CONSTRAINT c1 CHECK (id < 10),
				CONSTRAINT c2 CHECK (start_timestamp < end_timestamp),
				CONSTRAINT c3 CHECK (length(data) < 20)
			)"
		);

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't' ORDER BY CONSTRAINT_NAME" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c2',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c3',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_2',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_3',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_4',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.CHECK_CONSTRAINTS
		$result = $this->assertQuery( 'SELECT * FROM information_schema.check_constraints ORDER BY CONSTRAINT_NAME' );
		$this->assertCount( 7, $result );

		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c1',
					'CHECK_CLAUSE'       => '( id < 10 )',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c2',
					'CHECK_CLAUSE'       => '( start_timestamp < end_timestamp )',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c3',
					'CHECK_CLAUSE'       => '( length ( data ) < 20 )',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_1',
					'CHECK_CLAUSE'       => '( id > 0 )',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_2',
					'CHECK_CLAUSE'       => "( name != '' )",
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_3',
					'CHECK_CLAUSE'       => '( score > 0 AND score < 100 )',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_4',
					'CHECK_CLAUSE'       => '( json_valid ( data ) )',
				),
			),
			$result
		);
	}

	public function testInformationSchemaAlterTableAddCheckConstraint(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT)' );

		// ADD CONSTRAINT syntax.
		$this->assertQuery( 'ALTER TABLE t ADD CONSTRAINT c CHECK (id > 0)' );

		// ADD CHECK syntax.
		$this->assertQuery( 'ALTER TABLE t ADD CHECK (id < 10)' );

		// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't' ORDER BY CONSTRAINT_NAME" );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_1',
					'TABLE_SCHEMA'       => 'wp',
					'TABLE_NAME'         => 't',
					'CONSTRAINT_TYPE'    => 'CHECK',
					'ENFORCED'           => 'YES',
				),
			),
			$result
		);

		// INFORMATION_SCHEMA.CHECK_CONSTRAINTS
		$result = $this->assertQuery( 'SELECT * FROM information_schema.check_constraints ORDER BY CONSTRAINT_NAME' );
		$this->assertEquals(
			array(
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 'c',
					'CHECK_CLAUSE'       => '( id > 0 )',
				),
				(object) array(
					'CONSTRAINT_CATALOG' => 'def',
					'CONSTRAINT_SCHEMA'  => 'wp',
					'CONSTRAINT_NAME'    => 't_chk_1',
					'CHECK_CLAUSE'       => '( id < 10 )',
				),
			),
			$result
		);
	}

	public function testInformationSchemaAlterTableDropCheckConstraint(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, CONSTRAINT c1 CHECK (id > 0), CONSTRAINT c2 CHECK (id < 10))' );

		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertCount( 2, $result );

		$result = $this->assertQuery( 'SELECT * FROM information_schema.check_constraints' );
		$this->assertCount( 2, $result );

		// DROP CONSTRAINT syntax.
		$this->assertQuery( 'ALTER TABLE t DROP CONSTRAINT c1' );

		// DROP CHECK syntax.
		$this->assertQuery( 'ALTER TABLE t DROP CHECK c2' );

		$result = $this->assertQuery( "SELECT * FROM information_schema.table_constraints WHERE table_name = 't'" );
		$this->assertCount( 0, $result );

		$result = $this->assertQuery( 'SELECT * FROM information_schema.check_constraints' );
		$this->assertCount( 0, $result );
	}
}
