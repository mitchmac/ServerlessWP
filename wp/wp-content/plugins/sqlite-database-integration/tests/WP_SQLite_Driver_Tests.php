<?php

use PHPUnit\Framework\TestCase;

class WP_SQLite_Driver_Tests extends TestCase {
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
		$this->engine->query(
			"CREATE TABLE _options (
					ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name TEXT NOT NULL default '',
					option_value TEXT NOT NULL default ''
				);"
		);
		$this->engine->query(
			"CREATE TABLE _dates (
					ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name TEXT NOT NULL default '',
					option_value DATETIME NOT NULL
				);"
		);
	}

	private function assertQuery( $sql ) {
		$retval = $this->engine->query( $sql );
		$this->assertNotFalse( $retval );
		return $retval;
	}

	private function assertQueryError( $sql, $error_message ) {
		$exception = null;
		try {
			$this->engine->query( $sql );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception, 'An exception was expected, but none was thrown.' );
		$this->assertSame( $error_message, $exception->getMessage() );
	}

	public function testRegexp() {
		$this->assertQuery(
			"INSERT INTO _options (option_name, option_value) VALUES ('rss_0123456789abcdef0123456789abcdef', '1');"
		);
		$this->assertQuery(
			"INSERT INTO _options (option_name, option_value) VALUES ('transient', '1');"
		);

		$this->assertQuery( "DELETE FROM _options WHERE option_name  REGEXP '^rss_.+$'" );
		$this->assertQuery( 'SELECT * FROM _options' );
		$this->assertCount( 1, $this->engine->get_query_results() );
	}

	/**
	 * @dataProvider regexpOperators
	 */
	public function testRegexps( $operator, $regexp, $expected_result ) {
		$this->assertQuery(
			"INSERT INTO _options (option_name) VALUES ('rss_123'), ('RSS_123'), ('transient');"
		);

		$this->assertQuery( "SELECT ID, option_name FROM _options WHERE option_name $operator '$regexp' ORDER BY id LIMIT 1" );
		$this->assertEquals(
			array( $expected_result ),
			$this->engine->get_query_results()
		);
	}

	public static function regexpOperators() {
		$lowercase_rss       = (object) array(
			'ID'          => '1',
			'option_name' => 'rss_123',
		);
		$uppercase_rss       = (object) array(
			'ID'          => '2',
			'option_name' => 'RSS_123',
		);
		$lowercase_transient = (object) array(
			'ID'          => '3',
			'option_name' => 'transient',
		);
		return array(
			array( 'REGEXP', '^RSS_.+$', $lowercase_rss ),
			array( 'RLIKE', '^RSS_.+$', $lowercase_rss ),
			array( 'REGEXP BINARY', '^RSS_.+$', $uppercase_rss ),
			array( 'RLIKE BINARY', '^RSS_.+$', $uppercase_rss ),
			array( 'NOT REGEXP', '^RSS_.+$', $lowercase_transient ),
			array( 'NOT RLIKE', '^RSS_.+$', $lowercase_transient ),
			array( 'NOT REGEXP BINARY', '^RSS_.+$', $lowercase_rss ),
			array( 'NOT RLIKE BINARY', '^RSS_.+$', $lowercase_rss ),
		);
	}

	public function testInsertDateNow() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', now());"
		);

		$this->assertQuery( 'SELECT YEAR(option_value) as y FROM _dates' );

		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( gmdate( 'Y' ), $results[0]->y );
	}

	public function testUpdateWithLimit() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 00:00:45');"
		);
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('second', '2003-05-28 00:00:45');"
		);

		$this->assertQuery(
			"UPDATE _dates SET option_value = '2001-05-27 10:08:48' WHERE option_name = 'first' ORDER BY option_name LIMIT 1;"
		);

		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first';" );
		$result2 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='second';" );

		$this->assertEquals( '2001-05-27 10:08:48', $result1[0]->option_value );
		$this->assertEquals( '2003-05-28 00:00:45', $result2[0]->option_value );

		$this->assertQuery(
			"UPDATE _dates SET option_value = '2001-05-27 10:08:49' WHERE option_name = 'first';"
		);
		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first';" );
		$this->assertEquals( '2001-05-27 10:08:49', $result1[0]->option_value );

		$this->assertQuery(
			"UPDATE _dates SET option_value = '2001-05-12 10:00:40' WHERE option_name in ( SELECT option_name from _dates );"
		);
		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first';" );
		$result2 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='second';" );
		$this->assertEquals( '2001-05-12 10:00:40', $result1[0]->option_value );
		$this->assertEquals( '2001-05-12 10:00:40', $result2[0]->option_value );
	}

	public function testUpdateWithLimitNoEndToken() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 00:00:45')"
		);
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('second', '2003-05-28 00:00:45')"
		);

		$this->assertQuery(
			"UPDATE _dates SET option_value = '2001-05-27 10:08:48' WHERE option_name = 'first' ORDER BY option_name LIMIT 1"
		);
		$results = $this->engine->get_query_results();

		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first'" );
		$result2 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='second'" );

		$this->assertEquals( '2001-05-27 10:08:48', $result1[0]->option_value );
		$this->assertEquals( '2003-05-28 00:00:45', $result2[0]->option_value );

		$this->assertQuery(
			"UPDATE _dates SET option_value = '2001-05-27 10:08:49' WHERE option_name = 'first'"
		);
		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first'" );
		$this->assertEquals( '2001-05-27 10:08:49', $result1[0]->option_value );

		$this->assertQuery(
			"UPDATE _dates SET option_value = '2001-05-12 10:00:40' WHERE option_name in ( SELECT option_name from _dates )"
		);
		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first'" );
		$result2 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='second'" );
		$this->assertEquals( '2001-05-12 10:00:40', $result1[0]->option_value );
		$this->assertEquals( '2001-05-12 10:00:40', $result2[0]->option_value );
	}

	public function testUpdateWithoutWhereButWithSubSelect() {
		$this->assertQuery(
			"INSERT INTO _options (option_name, option_value) VALUES ('User 0000019', '2025-10-29 13:57:21');"
		);
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 10:08:48');"
		);
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('second', '2003-05-27 10:08:48');"
		);
		$return = $this->assertQuery(
			"UPDATE _dates SET option_value = (SELECT option_value from _options WHERE option_name = 'User 0000019')"
		);
		$this->assertSame( 2, $return, 'UPDATE query did not return 2 when two row were changed' );

		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first'" );
		$result2 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='second'" );
		$this->assertEquals( '2025-10-29 13:57:21', $result1[0]->option_value );
		$this->assertEquals( '2025-10-29 13:57:21', $result2[0]->option_value );
	}

	public function testUpdateWithoutWhereButWithLimit() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 10:08:48');"
		);
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('second', '2003-05-27 10:08:48');"
		);
		$return = $this->assertQuery(
			"UPDATE _dates SET option_value = '2025-10-29 13:57:21' LIMIT 1"
		);
		$this->assertSame( 1, $return, 'UPDATE query did not return 2 when two row were changed' );

		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first'" );
		$result2 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='second'" );
		$this->assertEquals( '2025-10-29 13:57:21', $result1[0]->option_value );
		$this->assertEquals( '2003-05-27 10:08:48', $result2[0]->option_value );
	}

	public function testCastAsBinary() {
		$this->assertQuery(
			// Use a confusing alias to make sure it replaces only the correct token
			"SELECT CAST('ABC' AS BINARY) as `binary`;"
		);
		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( 'ABC', $results[0]->binary );
	}

	public function testSelectFromDual() {
		$result = $this->assertQuery(
			'SELECT 1 as output FROM DUAL'
		);
		$this->assertEquals( 1, $result[0]->output );
	}

	public function testShowCreateTableNotFound() {
		$this->assertQuery(
			'SHOW CREATE TABLE _no_such_table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertCount( 0, $results );
	}

	public function testShowCreateTable1() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name VARCHAR(255) default '',
				option_value TEXT NOT NULL,
				UNIQUE KEY option_name (option_name),
				KEY composite (option_name, option_value)
			);"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp_table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			"CREATE TABLE `_tmp_table` (
  `ID` bigint NOT NULL AUTO_INCREMENT,
  `option_name` varchar(255) DEFAULT '',
  `option_value` text NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `option_name` (`option_name`),
  KEY `composite` (`option_name`, `option_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTableQuoted() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name VARCHAR(255) default '',
				option_value TEXT NOT NULL,
				UNIQUE KEY option_name (option_name),
				KEY composite (option_name, option_value)
			);"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE `_tmp_table`;'
		);
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			"CREATE TABLE `_tmp_table` (
  `ID` bigint NOT NULL AUTO_INCREMENT,
  `option_name` varchar(255) DEFAULT '',
  `option_value` text NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `option_name` (`option_name`),
  KEY `composite` (`option_name`, `option_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTableSimpleTable() {
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				ID BIGINT NOT NULL
			);'
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp_table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			'CREATE TABLE `_tmp_table` (
  `ID` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTableWithAlterAndCreateIndex() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
					ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name VARCHAR(255) default '',
					option_value TEXT NOT NULL
				);"
		);

		$this->assertQuery(
			'ALTER TABLE _tmp_table CHANGE COLUMN option_name option_name SMALLINT NOT NULL default 14'
		);

		$this->assertQuery(
			'ALTER TABLE _tmp_table ADD INDEX option_name (option_name);'
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp_table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			'CREATE TABLE `_tmp_table` (
  `ID` bigint NOT NULL AUTO_INCREMENT,
  `option_name` smallint NOT NULL DEFAULT \'14\',
  `option_value` text NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `option_name` (`option_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTableWithComments(): void {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				id INT NOT NULL COMMENT 'Column 1 comment',
				name VARCHAR(255) NULL DEFAULT 'test' COMMENT 'Column 2 comment',
				special_chars_1 TEXT NOT NULL COMMENT '\'',
				special_chars_2 TEXT NOT NULL COMMENT '''',
				special_chars_3 TEXT NOT NULL COMMENT '\"',
				special_chars_4 TEXT NOT NULL COMMENT '\\\"',
				special_chars_5 TEXT NOT NULL COMMENT '`',
				special_chars_6 TEXT NOT NULL COMMENT '\0',
				special_chars_7 TEXT NOT NULL COMMENT '\n',
				special_chars_8 TEXT NOT NULL COMMENT '\r',
				special_chars_9 TEXT NOT NULL COMMENT '\t',
				special_chars_10 TEXT NOT NULL COMMENT '\032',
				special_chars_11 TEXT NOT NULL COMMENT '\\\\',
				special_chars_12 TEXT NOT NULL COMMENT '🙂',
				special_chars_13 TEXT NOT NULL COMMENT '\🙂',
				INDEX idx_id (id) COMMENT 'Index comment'
			) COMMENT='Table comment'"
		);

		$results = $this->assertQuery(
			'SHOW CREATE TABLE _tmp_table;'
		);
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `_tmp_table` (',
					"  `id` int NOT NULL COMMENT 'Column 1 comment',",
					"  `name` varchar(255) DEFAULT 'test' COMMENT 'Column 2 comment',",
					"  `special_chars_1` text NOT NULL COMMENT '''',",
					"  `special_chars_2` text NOT NULL COMMENT '''',",
					"  `special_chars_3` text NOT NULL COMMENT '\"',",
					"  `special_chars_4` text NOT NULL COMMENT '\"',",
					"  `special_chars_5` text NOT NULL COMMENT '`',",
					"  `special_chars_6` text NOT NULL COMMENT '\\0',",
					"  `special_chars_7` text NOT NULL COMMENT '\\n',",
					"  `special_chars_8` text NOT NULL COMMENT '\\r',",
					"  `special_chars_9` text NOT NULL COMMENT '	',",
					"  `special_chars_10` text NOT NULL COMMENT '" . chr( 26 ) . "',",
					"  `special_chars_11` text NOT NULL COMMENT '\\\\',",
					"  `special_chars_12` text NOT NULL COMMENT '🙂',",
					"  `special_chars_13` text NOT NULL COMMENT '🙂',",
					"  KEY `idx_id` (`id`) COMMENT 'Index comment'",
					") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table comment'",
				)
			),
			$results[0]->{'Create Table'}
		);
	}

	public function testCreateTablesWithIdenticalIndexNames() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table_a (
					ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name VARCHAR(255) default '',
					option_value TEXT NOT NULL,
					KEY `option_name` (`option_name`),
					KEY `double__underscores` (`option_name`, `ID`)
				);"
		);

		$this->assertQuery(
			"CREATE TABLE _tmp_table_b (
					ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name VARCHAR(255) default '',
					option_value TEXT NOT NULL,
					KEY `option_name` (`option_name`),
					KEY `double__underscores` (`option_name`, `ID`)
				);"
		);
	}

	public function testShowCreateTablePreservesDoubleUnderscoreKeyNames() {
		$this->assertQuery(
			"CREATE TABLE _tmp__table (
					ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name VARCHAR(255) default '',
					option_value TEXT NOT NULL,
					KEY `option_name` (`option_name`),
					KEY `double__underscores` (`option_name`, `ID`)
				);"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp__table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			'CREATE TABLE `_tmp__table` (
  `ID` bigint NOT NULL AUTO_INCREMENT,
  `option_name` varchar(255) DEFAULT \'\',
  `option_value` text NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `option_name` (`option_name`),
  KEY `double__underscores` (`option_name`, `ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTableWithPrimaryKeyColumnsReverseOrdered() {
		$this->assertQuery(
			'CREATE TABLE `_tmp_table` (
				`ID_A` BIGINT NOT NULL,
				`ID_B` BIGINT NOT NULL,
				`ID_C` BIGINT NOT NULL,
				PRIMARY KEY (`ID_B`, `ID_A`, `ID_C`)
			);'
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp_table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			'CREATE TABLE `_tmp_table` (
  `ID_A` bigint NOT NULL,
  `ID_B` bigint NOT NULL,
  `ID_C` bigint NOT NULL,
  PRIMARY KEY (`ID_B`, `ID_A`, `ID_C`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTableWithColumnKeys() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
	`ID` bigint PRIMARY KEY AUTO_INCREMENT NOT NULL,
	`option_name` varchar(255) DEFAULT '',
	`option_value` text NOT NULL DEFAULT '',
	KEY _tmp_table__composite (option_name, option_value),
	UNIQUE KEY _tmp_table__option_name (option_name) );"
		);
	}

	public function testShowCreateTableWithDefaultValues(): void {
		$this->assertQuery(
			"CREATE TABLE _tmp__table (
				ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
				no_default VARCHAR(255),
				default_zero INT DEFAULT 0,
				default_true INT DEFAULT TRUE,
				default_false INT DEFAULT FALSE,
				default_empty_string VARCHAR(255) DEFAULT '',
				special_chars_1 TEXT NOT NULL COMMENT '\'',
				special_chars_2 TEXT NOT NULL COMMENT '''',
				special_chars_3 TEXT NOT NULL COMMENT '\"',
				special_chars_4 TEXT NOT NULL COMMENT '\\\"',
				special_chars_5 TEXT NOT NULL COMMENT '`',
				special_chars_6 TEXT NOT NULL COMMENT '\0',
				special_chars_7 TEXT NOT NULL COMMENT '\n',
				special_chars_8 TEXT NOT NULL COMMENT '\r',
				special_chars_9 TEXT NOT NULL COMMENT '\t',
				special_chars_10 TEXT NOT NULL COMMENT '\032',
				special_chars_11 TEXT NOT NULL COMMENT '\\\\',
				special_chars_12 TEXT NOT NULL COMMENT '🙂',
				special_chars_13 TEXT NOT NULL COMMENT '\🙂'
			)"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp__table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `_tmp__table` (',
					'  `ID` bigint NOT NULL AUTO_INCREMENT,',
					'  `no_default` varchar(255) DEFAULT NULL,',
					"  `default_zero` int DEFAULT '0',",
					"  `default_true` int DEFAULT '1',",
					"  `default_false` int DEFAULT '0',",
					"  `default_empty_string` varchar(255) DEFAULT '',",
					"  `special_chars_1` text NOT NULL COMMENT '''',",
					"  `special_chars_2` text NOT NULL COMMENT '''',",
					"  `special_chars_3` text NOT NULL COMMENT '\"',",
					"  `special_chars_4` text NOT NULL COMMENT '\"',",
					"  `special_chars_5` text NOT NULL COMMENT '`',",
					"  `special_chars_6` text NOT NULL COMMENT '\\0',",
					"  `special_chars_7` text NOT NULL COMMENT '\\n',",
					"  `special_chars_8` text NOT NULL COMMENT '\\r',",
					"  `special_chars_9` text NOT NULL COMMENT '	',",
					"  `special_chars_10` text NOT NULL COMMENT '" . chr( 26 ) . "',",
					"  `special_chars_11` text NOT NULL COMMENT '\\\\',",
					"  `special_chars_12` text NOT NULL COMMENT '🙂',",
					"  `special_chars_13` text NOT NULL COMMENT '🙂',",
					'  PRIMARY KEY (`ID`)',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$results[0]->{'Create Table'}
		);
	}

	public function testSelectIndexHintForce() {
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('first');" );
		$result = $this->assertQuery(
			'SELECT 1 as output FROM _options FORCE INDEX (PRIMARY, post_parent) WHERE 1=1'
		);
		$this->assertEquals( 1, $result[0]->output );
	}

	public function testSelectIndexHintUseGroup() {
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('first');" );
		$result = $this->assertQuery(
			'SELECT 1 as output FROM _options USE KEY FOR GROUP BY (PRIMARY, post_parent) WHERE 1=1'
		);
		$this->assertEquals( 1, $result[0]->output );
	}

	public function testDateAddFunction() {
		// second
		$result = $this->assertQuery(
			'SELECT DATE_ADD("2008-01-02 13:29:17", INTERVAL 1 SECOND) as output'
		);
		$this->assertEquals( '2008-01-02 13:29:18', $result[0]->output );

		// minute
		$result = $this->assertQuery(
			'SELECT DATE_ADD("2008-01-02 13:29:17", INTERVAL 1 MINUTE) as output'
		);
		$this->assertEquals( '2008-01-02 13:30:17', $result[0]->output );

		// hour
		$result = $this->assertQuery(
			'SELECT DATE_ADD("2008-01-02 13:29:17", INTERVAL 1 HOUR) as output'
		);
		$this->assertEquals( '2008-01-02 14:29:17', $result[0]->output );

		// day
		$result = $this->assertQuery(
			'SELECT DATE_ADD("2008-01-02 13:29:17", INTERVAL 1 DAY) as output'
		);
		$this->assertEquals( '2008-01-03 13:29:17', $result[0]->output );

		// week
		$result = $this->assertQuery(
			'SELECT DATE_ADD("2008-01-02 13:29:17", INTERVAL 1 WEEK) as output'
		);
		$this->assertEquals( '2008-01-09 13:29:17', $result[0]->output );

		// month
		$result = $this->assertQuery(
			'SELECT DATE_ADD("2008-01-02 13:29:17", INTERVAL 1 MONTH) as output'
		);
		$this->assertEquals( '2008-02-02 13:29:17', $result[0]->output );

		// year
		$result = $this->assertQuery(
			'SELECT DATE_ADD("2008-01-02 13:29:17", INTERVAL 1 YEAR) as output'
		);
		$this->assertEquals( '2009-01-02 13:29:17', $result[0]->output );
	}

	public function testDateSubFunction() {
		// second
		$result = $this->assertQuery(
			'SELECT DATE_SUB("2008-01-02 13:29:17", INTERVAL 1 SECOND) as output'
		);
		$this->assertEquals( '2008-01-02 13:29:16', $result[0]->output );

		// minute
		$result = $this->assertQuery(
			'SELECT DATE_SUB("2008-01-02 13:29:17", INTERVAL 1 MINUTE) as output'
		);
		$this->assertEquals( '2008-01-02 13:28:17', $result[0]->output );

		// hour
		$result = $this->assertQuery(
			'SELECT DATE_SUB("2008-01-02 13:29:17", INTERVAL 1 HOUR) as output'
		);
		$this->assertEquals( '2008-01-02 12:29:17', $result[0]->output );

		// day
		$result = $this->assertQuery(
			'SELECT DATE_SUB("2008-01-02 13:29:17", INTERVAL 1 DAY) as output'
		);
		$this->assertEquals( '2008-01-01 13:29:17', $result[0]->output );

		// week
		$result = $this->assertQuery(
			'SELECT DATE_SUB("2008-01-02 13:29:17", INTERVAL 1 WEEK) as output'
		);
		$this->assertEquals( '2007-12-26 13:29:17', $result[0]->output );

		// month
		$result = $this->assertQuery(
			'SELECT DATE_SUB("2008-01-02 13:29:17", INTERVAL 1 MONTH) as output'
		);
		$this->assertEquals( '2007-12-02 13:29:17', $result[0]->output );

		// year
		$result = $this->assertQuery(
			'SELECT DATE_SUB("2008-01-02 13:29:17", INTERVAL 1 YEAR) as output'
		);
		$this->assertEquals( '2007-01-02 13:29:17', $result[0]->output );
	}

	public function testLeftFunction1Char() {
		$result = $this->assertQuery(
			'SELECT LEFT("abc", 1) as output'
		);
		$this->assertEquals( 'a', $result[0]->output );
	}

	public function testLeftFunction5Chars() {
		$result = $this->assertQuery(
			'SELECT LEFT("Lorem ipsum", 5) as output'
		);
		$this->assertEquals( 'Lorem', $result[0]->output );
	}

	public function testLeftFunctionNullString() {
		$result = $this->assertQuery(
			'SELECT LEFT(NULL, 5) as output'
		);
		$this->assertEquals( null, $result[0]->output );
	}

	public function testLeftFunctionNullLength() {
		$result = $this->assertQuery(
			'SELECT LEFT("Test", NULL) as output'
		);
		$this->assertEquals( null, $result[0]->output );
	}

	public function testInsertSelectFromDual() {
		$result = $this->assertQuery(
			'INSERT INTO _options (option_name, option_value) SELECT "A", "b" FROM DUAL WHERE ( SELECT NULL FROM DUAL ) IS NULL'
		);
		$this->assertEquals( 1, $result );
	}

	public function testCreateTemporaryTable() {
		$this->assertQuery(
			"CREATE TEMPORARY TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);
		$this->assertQuery(
			'DROP TEMPORARY TABLE _tmp_table;'
		);
	}

	public function testShowTablesLike() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);
		$this->assertQuery(
			"CREATE TABLE _tmp_table_2 (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			"SHOW TABLES LIKE '_tmp_table';"
		);
		$this->assertEquals(
			array(
				(object) array(
					'Tables_in_wp' => '_tmp_table',
				),
			),
			$this->engine->get_query_results()
		);

		$this->assertQuery(
			"SHOW FULL TABLES LIKE '_tmp_table';"
		);
		$this->assertEquals(
			array(
				(object) array(
					'Tables_in_wp' => '_tmp_table',
					'Table_type'   => 'BASE TABLE',
				),
			),
			$this->engine->get_query_results()
		);
	}

	public function testShowTableStatusFrom() {
		// Created in setUp() function
		$this->assertQuery( 'DROP TABLE _options' );
		$this->assertQuery( 'DROP TABLE _dates' );

		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			'SHOW TABLE STATUS FROM wp;'
		);

		$this->assertCount(
			1,
			$this->engine->get_query_results()
		);
	}

	public function testShowTableStatusIn() {
		// Created in setUp() function
		$this->assertQuery( 'DROP TABLE _options' );
		$this->assertQuery( 'DROP TABLE _dates' );

		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			'SHOW TABLE STATUS IN wp;'
		);

		$this->assertCount(
			1,
			$this->engine->get_query_results()
		);
	}

	public function testShowTableStatusInTwoTables() {
		// Created in setUp() function
		$this->assertQuery( 'DROP TABLE _options' );
		$this->assertQuery( 'DROP TABLE _dates' );

		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			"CREATE TABLE _tmp_table2 (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);
		$this->assertQuery(
			'SHOW TABLE STATUS IN wp;'
		);

		$this->assertCount(
			2,
			$this->engine->get_query_results()
		);
	}

	public function testShowTableStatusLike() {
		// Created in setUp() function
		$this->assertQuery( 'DROP TABLE _options' );
		$this->assertQuery( 'DROP TABLE _dates' );

		$this->assertQuery(
			"CREATE TABLE _tmp_table1 (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			"CREATE TABLE _tmp_table2 (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			"CREATE TABLE _another_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			"SHOW TABLE STATUS LIKE '_tmp_table%';"
		);
		$this->assertCount(
			2,
			$this->engine->get_query_results()
		);
		$this->assertEquals(
			'_tmp_table1',
			$this->engine->get_query_results()[0]->Name
		);
	}

	public function testShowTableStatusWhere() {
		// Created in setUp() function
		$this->assertQuery( 'DROP TABLE _options' );
		$this->assertQuery( 'DROP TABLE _dates' );

		$this->assertQuery(
			"CREATE TABLE _tmp_table1 (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			"CREATE TABLE _tmp_table2 (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);

		$this->assertQuery(
			"SHOW TABLE STATUS WHERE SUBSTR(table_name, 11, 1) = '1'"
		);
		$this->assertCount(
			1,
			$this->engine->get_query_results()
		);
		$this->assertEquals(
			'_tmp_table1',
			$this->engine->get_query_results()[0]->Name
		);
	}

	public function testCreateTable() {
		$result = $this->assertQuery(
			"CREATE TABLE wptests_users (
				ID bigint(20) unsigned NOT NULL auto_increment,
				user_login varchar(60) NOT NULL default '',
				user_pass varchar(255) NOT NULL default '',
				user_nicename varchar(50) NOT NULL default '',
				user_email varchar(100) NOT NULL default '',
				user_url varchar(100) NOT NULL default '',
				user_registered datetime NOT NULL default '0000-00-00 00:00:00',
				user_activation_key varchar(255) NOT NULL default '',
				user_status int(11) NOT NULL default '0',
				display_name varchar(250) NOT NULL default '',
				PRIMARY KEY  (ID),
				KEY user_login_key (user_login),
				KEY user_nicename (user_nicename),
				KEY user_email (user_email)
			) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci"
		);
		$this->assertNull( $result );

		$this->assertQuery( 'DESCRIBE wptests_users;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'ID',
					'Type'    => 'bigint(20) unsigned',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => null,
					'Extra'   => 'auto_increment',
				),
				(object) array(
					'Field'   => 'user_login',
					'Type'    => 'varchar(60)',
					'Null'    => 'NO',
					'Key'     => 'MUL',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_pass',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_nicename',
					'Type'    => 'varchar(50)',
					'Null'    => 'NO',
					'Key'     => 'MUL',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_email',
					'Type'    => 'varchar(100)',
					'Null'    => 'NO',
					'Key'     => 'MUL',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_url',
					'Type'    => 'varchar(100)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_registered',
					'Type'    => 'datetime',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '0000-00-00 00:00:00',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_activation_key',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_status',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '0',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'display_name',
					'Type'    => 'varchar(250)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			),
			$results
		);
	}

	public function testCreateTableWithTrailingComma() {
		$result = $this->assertQuery(
			'CREATE TABLE wptests_users (
				ID bigint(20) unsigned NOT NULL auto_increment,
				PRIMARY KEY  (ID)
			) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
		);
		$this->assertNull( $result );
	}

	public function testCreateTableSpatialIndex() {
		$result = $this->assertQuery(
			'CREATE TABLE wptests_users (
				ID bigint(20) unsigned NOT NULL auto_increment,
				UNIQUE KEY (ID)
			)'
		);
		$this->assertNull( $result );
	}

	public function testCreateTableWithMultiValueColumnTypeModifiers() {
		$result = $this->assertQuery(
			"CREATE TABLE wptests_users (
				ID bigint(20) unsigned NOT NULL auto_increment,
				decimal_column DECIMAL(10,2) NOT NULL DEFAULT 0,
				float_column FLOAT(10,2) NOT NULL DEFAULT 0,
				enum_column ENUM('a', 'b', 'c') NOT NULL DEFAULT 'a',
				PRIMARY KEY  (ID)
			)"
		);
		$this->assertNull( $result );

		$this->assertQuery( 'DESCRIBE wptests_users;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'ID',
					'Type'    => 'bigint(20) unsigned',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => null,
					'Extra'   => 'auto_increment',
				),
				(object) array(
					'Field'   => 'decimal_column',
					'Type'    => 'decimal(10,2)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => 0,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'float_column',
					'Type'    => 'float(10,2)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => 0,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'enum_column',
					'Type'    => "enum('a','b','c')",
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => 'a',
					'Extra'   => '',
				),
			),
			$results
		);
	}

	public function testAlterTableAddAndDropColumn() {
		$result = $this->assertQuery(
			"CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL default ''
			);"
		);
		$this->assertNull( $result );

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table ADD COLUMN `column` int;' );
		$this->assertNull( $result );

		$this->assertQuery( 'DESCRIBE _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'column',
					'Type'    => 'int',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$results
		);

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table ADD `column2` int;' );
		$this->assertNull( $result );

		$this->assertQuery( 'DESCRIBE _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'column',
					'Type'    => 'int',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'column2',
					'Type'    => 'int',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$results
		);

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table DROP COLUMN `column`;' );
		$this->assertNull( $result );

		$this->assertQuery( 'DESCRIBE _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'column2',
					'Type'    => 'int',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$results
		);

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table DROP `column2`;' );
		$this->assertNull( $result );

		$this->assertQuery( 'DESCRIBE _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			),
			$results
		);
	}

	public function testAlterTableAddNotNullVarcharColumn() {
		$result = $this->assertQuery(
			"CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL default ''
			);"
		);

		$result = $this->assertQuery( "ALTER TABLE _tmp_table ADD COLUMN `column` VARCHAR(20) NOT NULL DEFAULT 'foo';" );
		$this->assertNull( $result );

		$this->assertQuery( 'DESCRIBE _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'column',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => 'foo',
					'Extra'   => '',
				),
			),
			$results
		);
	}

	public function testColumnWithOnUpdate() {
		// CREATE TABLE with ON UPDATE
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				id int(11) NOT NULL,
				created_at timestamp NULL ON UPDATE CURRENT_TIMESTAMP
			);'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'created_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => 'on update CURRENT_TIMESTAMP',
				),
			),
			$results
		);

		// ADD COLUMN with ON UPDATE
		$this->assertQuery(
			'ALTER TABLE _tmp_table ADD COLUMN updated_at timestamp NULL ON UPDATE CURRENT_TIMESTAMP'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'created_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => 'on update CURRENT_TIMESTAMP',
				),
				(object) array(
					'Field'   => 'updated_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => 'on update CURRENT_TIMESTAMP',
				),
			),
			$results
		);

		// assert ON UPDATE triggers
		$results = $this->assertQuery( "SELECT * FROM sqlite_master WHERE type = 'trigger'" );
		$this->assertEquals(
			array(
				(object) array(
					'type'     => 'trigger',
					'name'     => '_wp_sqlite__tmp_table_created_at_on_update',
					'tbl_name' => '_tmp_table',
					'rootpage' => '0',
					'sql'      => implode(
						"\n\t\t\t\t",
						array(
							'CREATE TRIGGER `_wp_sqlite__tmp_table_created_at_on_update`',
							'AFTER UPDATE ON `_tmp_table`',
							'FOR EACH ROW',
							'BEGIN',
							'  UPDATE `_tmp_table` SET `created_at` = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid;',
							'END',
						)
					),
				),
				(object) array(
					'type'     => 'trigger',
					'name'     => '_wp_sqlite__tmp_table_updated_at_on_update',
					'tbl_name' => '_tmp_table',
					'rootpage' => '0',
					'sql'      => implode(
						"\n\t\t\t\t",
						array(
							'CREATE TRIGGER `_wp_sqlite__tmp_table_updated_at_on_update`',
							'AFTER UPDATE ON `_tmp_table`',
							'FOR EACH ROW',
							'BEGIN',
							'  UPDATE `_tmp_table` SET `updated_at` = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid;',
							'END',
						)
					),
				),
			),
			$results
		);

		// on INSERT, no timestamps are expected
		$this->assertQuery( 'INSERT INTO _tmp_table (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE id = 1' );
		$this->assertNull( $result[0]->created_at );
		$this->assertNull( $result[0]->updated_at );

		// on UPDATE, we expect timestamps in form YYYY-MM-DD HH:MM:SS
		$this->assertQuery( 'UPDATE _tmp_table SET id = 2 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE id = 2' );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $result[0]->created_at );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $result[0]->updated_at );

		// drop ON UPDATE
		$this->assertQuery(
			'ALTER TABLE _tmp_table
			CHANGE created_at created_at timestamp NULL,
			CHANGE COLUMN updated_at updated_at timestamp NULL'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'created_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'updated_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$results
		);

		// assert ON UPDATE triggers are removed
		$results = $this->assertQuery( "SELECT * FROM sqlite_master WHERE type = 'trigger'" );
		$this->assertEquals( array(), $results );

		// now, no timestamps are expected
		$this->assertQuery( 'INSERT INTO _tmp_table (id) VALUES (10)' );
		$this->assertQuery( 'UPDATE _tmp_table SET id = 11 WHERE id = 10' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE id = 11' );
		$this->assertNull( $result[0]->created_at );
		$this->assertNull( $result[0]->updated_at );
	}

	public function testColumnWithOnUpdateAndNoIdField() {
		// CREATE TABLE with ON UPDATE
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL,
				created_at timestamp NULL ON UPDATE CURRENT_TIMESTAMP
			);'
		);

		// on INSERT, no timestamps are expected
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('aaa')" );
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name = 'aaa'" );
		$this->assertNull( $result[0]->created_at );

		// on UPDATE, we expect timestamps in form YYYY-MM-DD HH:MM:SS
		$this->assertQuery( "UPDATE _tmp_table SET name = 'bbb' WHERE name = 'aaa'" );
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name = 'bbb'" );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $result[0]->created_at );
	}

	public function testChangeColumnWithOnUpdate() {
		// CREATE TABLE with ON UPDATE
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				id int(11) NOT NULL,
				created_at timestamp NULL
			);'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'created_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$results
		);

		// no ON UPDATE is set
		$this->assertQuery( 'INSERT INTO _tmp_table (id) VALUES (1)' );
		$this->assertQuery( 'UPDATE _tmp_table SET id = 1 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE id = 1' );
		$this->assertNull( $result[0]->created_at );

		// CHANGE COLUMN to add ON UPDATE
		$this->assertQuery(
			'ALTER TABLE _tmp_table CHANGE COLUMN created_at created_at timestamp NULL ON UPDATE CURRENT_TIMESTAMP'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'created_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => 'on update CURRENT_TIMESTAMP',
				),
			),
			$results
		);

		// now, ON UPDATE SHOULD BE SET
		$this->assertQuery( 'UPDATE _tmp_table SET id = 1 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE id = 1' );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $result[0]->created_at );

		// change column to remove ON UPDATE
		$this->assertQuery(
			'ALTER TABLE _tmp_table CHANGE COLUMN created_at created_at timestamp NULL'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'created_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$results
		);

		// now, no timestamp is expected
		$this->assertQuery( 'INSERT INTO _tmp_table (id) VALUES (2)' );
		$this->assertQuery( 'UPDATE _tmp_table SET id = 2 WHERE id = 2' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE id = 2' );
		$this->assertNull( $result[0]->created_at );
	}

	public function testAlterTableWithColumnFirstAndAfter() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				id int(11) NOT NULL,
				name varchar(20) NOT NULL default ''
			);"
		);

		// ADD COLUMN with FIRST
		$this->assertQuery(
			"ALTER TABLE _tmp_table ADD COLUMN new_first_column VARCHAR(255) NOT NULL DEFAULT '' FIRST"
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new_first_column',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			),
			$results
		);

		// ADD COLUMN with AFTER
		$this->assertQuery(
			"ALTER TABLE _tmp_table ADD COLUMN new_column VARCHAR(255) NOT NULL DEFAULT '' AFTER id"
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new_first_column',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new_column',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			),
			$results
		);

		// CHANGE with FIRST
		$this->assertQuery(
			"ALTER TABLE _tmp_table CHANGE id id int(11) NOT NULL DEFAULT '0' FIRST"
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '0',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new_first_column',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new_column',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			),
			$results
		);

		// CHANGE with AFTER
		$this->assertQuery(
			"ALTER TABLE _tmp_table CHANGE id id int(11) NOT NULL DEFAULT '0' AFTER name"
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '0',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new_first_column',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new_column',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			),
			$results
		);
	}

	public function testAlterTableWithMultiColumnFirstAndAfter() {
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				id int(11) NOT NULL
			);'
		);

		// ADD COLUMN
		$this->assertQuery(
			'ALTER TABLE _tmp_table
			ADD COLUMN new1 varchar(255) NOT NULL,
			ADD COLUMN new2 varchar(255) NOT NULL FIRST,
			ADD COLUMN new3 varchar(255) NOT NULL AFTER new1'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new1',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new2',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new3',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			),
			$results
		);

		// CHANGE
		$this->assertQuery(
			'ALTER TABLE _tmp_table
			CHANGE new1 new1 int(11) NOT NULL FIRST,
			CHANGE new2 new2 int(11) NOT NULL,
			CHANGE new3 new3 int(11) NOT NULL AFTER new2'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new1',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new2',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'new3',
					'Type'    => 'int(11)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			),
			$results
		);
	}

	public function testAlterTableAddIndex() {
		$result = $this->assertQuery(
			"CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL default ''
			);"
		);

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table ADD INDEX name (name);' );
		$this->assertNull( $result );

		// Verify that the index was created in the information schema.
		$this->assertQuery( 'SHOW INDEX FROM _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Table'         => '_tmp_table',
					'Non_unique'    => '1',
					'Key_name'      => 'name',
					'Seq_in_index'  => '1',
					'Column_name'   => 'name',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
			),
			$results
		);

		// Verify that the index is defined in the SQLite.
		$result = $this->engine
			->execute_sqlite_query( "PRAGMA index_list('_tmp_table')" )
			->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => '_tmp_table__name',
				'unique'  => '0',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	public function testAlterTableAddUniqueIndex() {
		$result = $this->assertQuery(
			"CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL default ''
			);"
		);

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table ADD UNIQUE INDEX name (name(20));' );
		$this->assertNull( $result );

		// Verify that the index was created in the information schema.
		$this->assertQuery( 'SHOW INDEX FROM _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Table'         => '_tmp_table',
					'Non_unique'    => '0',
					'Key_name'      => 'name',
					'Seq_in_index'  => '1',
					'Column_name'   => 'name',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => '20',
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
			),
			$results
		);

		// Verify that the index is defined in the SQLite.
		$result = $this->engine
			->execute_sqlite_query( "PRAGMA index_list('_tmp_table')" )
			->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => '_tmp_table__name',
				'unique'  => '1',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	public function testAlterTableAddFulltextIndex() {
		$result = $this->assertQuery(
			"CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL default ''
			);"
		);

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table ADD FULLTEXT INDEX name (name);' );
		$this->assertNull( $result );

		// Verify that the index was created in the information schema.
		$this->assertQuery( 'SHOW INDEX FROM _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Table'         => '_tmp_table',
					'Non_unique'    => '1',
					'Key_name'      => 'name',
					'Seq_in_index'  => '1',
					'Column_name'   => 'name',
					'Collation'     => null,
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'FULLTEXT',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
			),
			$results
		);

		// Verify that the index is defined in the SQLite.
		$result = $this->engine
			->execute_sqlite_query( "PRAGMA index_list('_tmp_table')" )
			->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => '_tmp_table__name',
				'unique'  => '0',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	public function testAlterTableAddIndexWithOrder(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, value VARCHAR(255))' );
		$this->assertQuery( 'ALTER TABLE t ADD INDEX idx_value (value DESC)' );

		// Verify that the order was saved in the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'D', $result[0]->Collation );

		// Verify that the order is included in the CREATE TABLE statement.
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertCount( 1, $result );
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int DEFAULT NULL,',
					'  `value` varchar(255) DEFAULT NULL,',
					'  KEY `idx_value` (`value` DESC)',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);

		// Verify that the order is defined in the SQLite index.
		$result = $this->engine
			->execute_sqlite_query( "SELECT * FROM pragma_index_xinfo('t__idx_value') WHERE cid != -1" )
			->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seqno' => '0',
				'cid'   => '1',
				'name'  => 'value',
				'desc'  => '1',
				'coll'  => 'NOCASE',
				'key'   => '1',
			),
			$result[0]
		);
	}

	public function testAlterTableModifyColumn() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				name varchar(20) NOT NULL default '',
				lastname varchar(20) NOT NULL default '',
				KEY composite (name, lastname),
				UNIQUE KEY name (name)
			);"
		);
		// Insert a record
		$result = $this->assertQuery( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (1, 'Johnny', 'Appleseed');" );
		$this->assertEquals( 1, $result );

		// Primary key violation:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (1, 'Mike', 'Pearseed')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.ID', $error );

		// Unique constraint violation:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (2, 'Johnny', 'Appleseed')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.name', $error );

		// Rename the "name" field to "firstname":
		$result = $this->engine->query( "ALTER TABLE _tmp_table CHANGE column name firstname varchar(50) NOT NULL default 'mark';" );
		$this->assertNull( $result );

		// Confirm the original data is still there:
		$result = $this->engine->query( 'SELECT * FROM _tmp_table;' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 1, $result[0]->ID );
		$this->assertEquals( 'Johnny', $result[0]->firstname );
		$this->assertEquals( 'Appleseed', $result[0]->lastname );

		// Confirm the primary key is intact:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname) VALUES (1, 'Mike', 'Pearseed')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.ID', $error );

		// Confirm the unique key is intact:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname) VALUES (2, 'Johnny', 'Appleseed')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.firstname', $error );

		// Confirm the autoincrement still works:
		$result = $this->engine->query( "INSERT INTO _tmp_table (firstname, lastname) VALUES ('John', 'Doe');" );
		$this->assertEquals( true, $result );
		$result = $this->engine->query( "SELECT * FROM _tmp_table WHERE firstname='John';" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 2, $result[0]->ID );
	}


	public function testAlterTableModifyColumnWithSkippedColumnKeyword() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				name varchar(20) NOT NULL default '',
				lastname varchar(20) NOT NULL default '',
				KEY composite (name, lastname),
				UNIQUE KEY name (name)
			);"
		);
		// Insert a record
		$result = $this->assertQuery( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (1, 'Johnny', 'Appleseed');" );
		$this->assertEquals( 1, $result );

		// Primary key violation:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (1, 'Mike', 'Pearseed')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.ID', $error );

		// Unique constraint violation:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (2, 'Johnny', 'Appleseed')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.name', $error );

		// Rename the "name" field to "firstname":
		$result = $this->engine->query( "ALTER TABLE _tmp_table CHANGE name firstname varchar(50) NOT NULL default 'mark';" );
		$this->assertNull( $result );

		// Confirm the original data is still there:
		$result = $this->engine->query( 'SELECT * FROM _tmp_table;' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 1, $result[0]->ID );
		$this->assertEquals( 'Johnny', $result[0]->firstname );
		$this->assertEquals( 'Appleseed', $result[0]->lastname );

		// Confirm the primary key is intact:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname) VALUES (1, 'Mike', 'Pearseed')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.ID', $error );

		// Confirm the unique key is intact:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname) VALUES (2, 'Johnny', 'Appleseed')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.firstname', $error );

		// Confirm the autoincrement still works:
		$result = $this->engine->query( "INSERT INTO _tmp_table (firstname, lastname) VALUES ('John', 'Doe');" );
		$this->assertEquals( true, $result );
		$result = $this->engine->query( "SELECT * FROM _tmp_table WHERE firstname='John';" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 2, $result[0]->ID );
	}

	public function testAlterTableModifyColumnWithHyphens() {
		$result = $this->assertQuery(
			'CREATE TABLE wptests_dbdelta_test2 (
				`foo-bar` varchar(255) DEFAULT NULL
			)'
		);
		$this->assertNull( $result );

		$result = $this->assertQuery(
			'ALTER TABLE wptests_dbdelta_test2 CHANGE COLUMN `foo-bar` `foo-bar` text DEFAULT NULL'
		);
		$this->assertNull( $result );

		$result = $this->assertQuery( 'DESCRIBE wptests_dbdelta_test2;' );
		$this->assertNotFalse( $result );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'foo-bar',
					'Type'    => 'text',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$result
		);
	}

	public function testAlterTableModifyColumnComplexChange() {
		$result = $this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER NOT NULL,
				name varchar(20) NOT NULL default '',
				lastname varchar(20) default '',
				date_as_string varchar(20) default '',
				PRIMARY KEY (ID, name)
			);"
		);
		$this->assertNull( $result );

		// Add a unique index
		$result = $this->assertQuery(
			'ALTER TABLE _tmp_table ADD UNIQUE INDEX "test_unique_composite" (name, lastname);'
		);
		$this->assertNull( $result );

		// Add a regular index
		$result = $this->assertQuery(
			'ALTER TABLE _tmp_table ADD INDEX "test_regular" (lastname);'
		);
		$this->assertNull( $result );

		// Confirm the table is well-behaved so far:

		// Insert a few records
		$result = $this->assertQuery(
			"
			INSERT INTO _tmp_table (ID, name, lastname, date_as_string)
			VALUES
				(1, 'Johnny', 'Appleseed', '2002-01-01 12:53:13'),
				(2, 'Mike', 'Foo', '2003-01-01 12:53:13'),
				(3, 'Kate', 'Bar', '2004-01-01 12:53:13'),
				(4, 'Anna', 'Pear', '2005-01-01 12:53:13')
			;"
		);
		$this->assertEquals( 4, $result );

		// Primary key violation:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, name) VALUES (1, 'Johnny')" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.ID, _tmp_table.name', $error );

		// Unique constraint violation:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (5, 'Kate', 'Bar');" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.name, _tmp_table.lastname', $error );

		// No constraint violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (5, 'Joanna', 'Bar');" );
		$this->assertEquals( 1, $result );

		// Now – let's change a few columns:
		$result = $this->engine->query( 'ALTER TABLE _tmp_table CHANGE COLUMN name firstname varchar(20)' );
		$this->assertNull( $result );

		$result = $this->engine->query( 'ALTER TABLE _tmp_table CHANGE COLUMN date_as_string datetime datetime NOT NULL' );
		$this->assertNull( $result );

		// Finally, let's confirm our data is intact and the table is still well-behaved:
		$result = $this->engine->query( 'SELECT * FROM _tmp_table ORDER BY ID;' );
		$this->assertCount( 5, $result );
		$this->assertEquals( 1, $result[0]->ID );
		$this->assertEquals( 'Johnny', $result[0]->firstname );
		$this->assertEquals( 'Appleseed', $result[0]->lastname );
		$this->assertEquals( '2002-01-01 12:53:13', $result[0]->datetime );

		// Primary key violation:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, firstname, datetime) VALUES (1, 'Johnny', '2010-01-01 12:53:13');" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.ID, _tmp_table.firstname', $error );

		// Unique constraint violation:
		$error = '';
		try {
			$this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname, datetime) VALUES (6, 'Kate', 'Bar', '2010-01-01 12:53:13');" );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.firstname, _tmp_table.lastname', $error );

		// No constraint violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname, datetime) VALUES (6, 'Sophie', 'Bar', '2010-01-01 12:53:13');" );
		$this->assertEquals( 1, $result );
	}

	public function testCaseInsensitiveUniqueIndex() {
		$result = $this->engine->query(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				name varchar(20) NOT NULL default '',
				lastname varchar(20) NOT NULL default '',
				KEY name (name),
				UNIQUE KEY uname (name),
				UNIQUE KEY last (lastname)
			);"
		);
		$this->assertNull( $result );

		$result1 = $this->engine->query( "INSERT INTO _tmp_table (name, lastname) VALUES ('first', 'last');" );
		$this->assertEquals( 1, $result1 );

		$result1 = $this->engine->query( 'SELECT COUNT(*) num FROM _tmp_table;' );
		$this->assertEquals( 1, $result1[0]->num );

		// Unique keys should be case-insensitive:
		$error = '';
		try {
			$this->assertQuery(
				"INSERT INTO _tmp_table (name, lastname) VALUES ('FIRST', 'LAST' )"
			);
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed', $error );

		$result1 = $this->engine->query( 'SELECT COUNT(*) num FROM _tmp_table;' );
		$this->assertEquals( 1, $result1[0]->num );

		// Unique keys should be case-insensitive:
		$result1 = $this->assertQuery(
			"INSERT IGNORE INTO _tmp_table (name) VALUES ('FIRST');"
		);

		self::assertEquals( 0, $result1 );

		$result2 = $this->engine->get_query_results();
		$this->assertEquals( 0, $result2 );

		$result1 = $this->engine->query( 'SELECT COUNT(*)num FROM _tmp_table;' );
		$this->assertEquals( 1, $result1[0]->num );

		// Unique keys should be case-insensitive:
		$result2 = $this->assertQuery(
			"INSERT INTO _tmp_table (name, lastname) VALUES ('FIRSTname', 'LASTname' );"
		);

		$this->assertEquals( 1, $result2 );

		$result1 = $this->engine->query( 'SELECT COUNT(*) num FROM _tmp_table;' );
		$this->assertEquals( 2, $result1[0]->num );
	}

	public function testOnDuplicateUpdate() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				name varchar(20) NOT NULL default '',
				UNIQUE KEY myname (name)
			);"
		);

		$result1 = $this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('first');" );
		$this->assertEquals( 1, $result1 );

		$result2 = $this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('FIRST') ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);" );
		$this->assertEquals( 1, $result2 );

		$this->assertQuery( 'SELECT * FROM _tmp_table;' );
		$this->assertCount( 1, $this->engine->get_query_results() );
		$this->assertEquals(
			array(
				(object) array(
					'name' => 'FIRST',
					'ID'   => 1,
				),
			),
			$this->engine->get_query_results()
		);
	}

	public function testTruncatesInvalidDates() {
		$this->assertQuery( "SET sql_mode = ''" );

		$this->assertQuery( "INSERT INTO _dates (option_value) VALUES ('2022-01-01 14:24:12');" );
		$this->assertQuery( "INSERT INTO _dates (option_value) VALUES ('2022-31-01 14:24:12');" );

		$this->assertQuery( 'SELECT * FROM _dates;' );
		$results = $this->engine->get_query_results();
		$this->assertCount( 2, $results );
		$this->assertEquals( '2022-01-01 14:24:12', $results[0]->option_value );
		$this->assertEquals( '0000-00-00 00:00:00', $results[1]->option_value );
	}

	public function testCaseInsensitiveSelect() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				name varchar(20) NOT NULL default ''
			);"
		);
		$this->assertQuery(
			"INSERT INTO _tmp_table (name) VALUES ('first');"
		);
		$this->assertQuery( "SELECT name FROM _tmp_table WHERE name = 'FIRST';" );
		$this->assertCount( 1, $this->engine->get_query_results() );
		$this->assertEquals(
			array(
				(object) array(
					'name' => 'first',
				),
			),
			$this->engine->get_query_results()
		);
	}

	public function testSelectBetweenDates() {
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('first', '2016-01-15T00:00:00Z');" );
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('second', '2016-01-16T00:00:00Z');" );
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('third', '2016-01-17T00:00:00Z');" );
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('fourth', '2016-01-18T00:00:00Z');" );

		$this->assertQuery( "SELECT * FROM _dates WHERE option_value BETWEEN '2016-01-15T00:00:00Z' AND '2016-01-17T00:00:00Z' ORDER BY ID;" );
		$results = $this->engine->get_query_results();
		$this->assertCount( 3, $results );
		$this->assertEquals( 'first', $results[0]->option_name );
		$this->assertEquals( 'second', $results[1]->option_name );
		$this->assertEquals( 'third', $results[2]->option_name );
	}

	public function testSelectFilterByDatesGtLt() {
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('first', '2016-01-15T00:00:00Z');" );
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('second', '2016-01-16T00:00:00Z');" );
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('third', '2016-01-17T00:00:00Z');" );
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('fourth', '2016-01-18T00:00:00Z');" );

		$this->assertQuery(
			"
			SELECT * FROM _dates
			WHERE option_value > '2016-01-15 00:00:00'
			AND   option_value < '2016-01-17 00:00:00'
			ORDER BY ID
		"
		);
		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( 'second', $results[0]->option_name );
	}

	public function testSelectFilterByDatesZeroHour() {
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('first', '2014-10-21 00:42:29');" );
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('second', '2014-10-21 01:42:29');" );

		$this->assertQuery(
			'
			SELECT * FROM _dates
			WHERE YEAR(option_value) = 2014
			AND   MONTHNUM(option_value) = 10
			AND   DAY(option_value) = 21
			AND   HOUR(option_value) = 0
			AND   MINUTE(option_value) = 42
		'
		);
		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( 'first', $results[0]->option_name );
	}

	public function testCorrectlyInsertsDatesAndStrings() {
		$this->assertQuery( "INSERT INTO _dates (option_name, option_value) VALUES ('2016-01-15T00:00:00Z', '2016-01-15T00:00:00Z');" );

		$this->assertQuery( 'SELECT * FROM _dates' );
		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( '2016-01-15 00:00:00', $results[0]->option_value );
		if ( '2016-01-15T00:00:00Z' !== $results[0]->option_name ) {
			$this->markTestSkipped( 'A datetime-like string was rewritten to an SQLite format even though it was used as a text and not as a datetime.' );
		}
		$this->assertEquals( '2016-01-15T00:00:00Z', $results[0]->option_name );
	}

	public function testTransactionRollback() {
		$this->assertQuery( 'BEGIN' );
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('first');" );
		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 1, $this->engine->get_query_results() );
		$this->assertQuery( 'ROLLBACK' );

		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 0, $this->engine->get_query_results() );
	}

	public function testTransactionCommit() {
		$this->assertQuery( 'BEGIN' );
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('first');" );
		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 1, $this->engine->get_query_results() );
		$this->assertQuery( 'COMMIT' );

		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 1, $this->engine->get_query_results() );
	}

	public function testStartTransactionCommand() {
		$this->assertQuery( 'START TRANSACTION' );
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('first');" );
		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 1, $this->engine->get_query_results() );
		$this->assertQuery( 'ROLLBACK' );

		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 0, $this->engine->get_query_results() );
	}

	public function testRepeatedTransactionCommands(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT)' );

		// 1st BEGIN starts a transaction.
		$this->assertQuery( 'BEGIN' );
		$this->assertQuery( 'INSERT INTO t (id) VALUES (1);' );

		// 2nd BEGIN commits the previous transaction and starts a new one.
		$this->assertQuery( 'BEGIN' );
		$this->assertQuery( 'INSERT INTO t (id) VALUES (2);' );

		// ROLLBACK rolls back the 2nd transaction.
		$this->assertQuery( 'ROLLBACK' );
		$results = $this->assertQuery( 'SELECT * FROM t;' );
		$this->assertEquals( array( (object) array( 'id' => '1' ) ), $results );

		// Repeated ROLLBACK should do nothing.
		$this->assertQuery( 'ROLLBACK' );
		$results = $this->assertQuery( 'SELECT * FROM t;' );
		$this->assertEquals( array( (object) array( 'id' => '1' ) ), $results );
	}

	public function testCount() {
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('first');" );
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('second');" );
		$this->assertQuery( 'SELECT COUNT(*) as count FROM _options;' );

		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertSame( '2', $results[0]->count );
	}

	public function testUpdateDate() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 10:08:48');"
		);

		$this->assertQuery( 'SELECT option_value FROM _dates' );

		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( '2003-05-27 10:08:48', $results[0]->option_value );

		$this->assertQuery(
			"UPDATE _dates SET option_value = DATE_SUB(option_value, INTERVAL '2' YEAR);"
		);

		$this->assertQuery( 'SELECT option_value FROM _dates' );

		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( '2001-05-27 10:08:48', $results[0]->option_value );
	}

	public function testInsertDateLiteral() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 10:08:48');"
		);

		$this->assertQuery( 'SELECT option_value FROM _dates' );

		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( '2003-05-27 10:08:48', $results[0]->option_value );
	}

	public function testSelectDate1() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2000-05-27 10:08:48');"
		);

		$this->assertQuery(
			'SELECT
			YEAR( _dates.option_value ) as year,
			MONTH( _dates.option_value ) as month,
			DAYOFMONTH( _dates.option_value ) as dayofmonth,
			MONTHNUM( _dates.option_value ) as monthnum,
			WEEKDAY( _dates.option_value ) as weekday,
			WEEK( _dates.option_value, 1 ) as week1,
			HOUR( _dates.option_value ) as hour,
			MINUTE( _dates.option_value ) as minute,
			SECOND( _dates.option_value ) as second
		FROM _dates'
		);

		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( '2000', $results[0]->year );
		$this->assertEquals( '5', $results[0]->month );
		$this->assertEquals( '27', $results[0]->dayofmonth );
		$this->assertEquals( '5', $results[0]->weekday );
		$this->assertEquals( '21', $results[0]->week1 );
		$this->assertEquals( '5', $results[0]->monthnum );
		$this->assertEquals( '10', $results[0]->hour );
		$this->assertEquals( '8', $results[0]->minute );
		$this->assertEquals( '48', $results[0]->second );
	}

	public function testSelectDate24HourFormat() {
		$this->assertQuery(
			"
			INSERT INTO _dates (option_name, option_value)
			VALUES
				('second', '2003-05-27 14:08:48'),
				('first', '2003-05-27 00:08:48');
		"
		);

		// HOUR(14:08) should yield 14 in the 24 hour format
		$this->assertQuery( "SELECT  HOUR( _dates.option_value ) as hour FROM _dates WHERE option_name = 'second'" );
		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( '14', $results[0]->hour );

		// HOUR(00:08) should yield 0 in the 24 hour format
		$this->assertQuery( "SELECT  HOUR( _dates.option_value ) as hour FROM _dates WHERE option_name = 'first'" );
		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( '0', $results[0]->hour );

		// Lookup by HOUR(00:08) = 0 should yield the right record
		$this->assertQuery(
			'SELECT  HOUR( _dates.option_value ) as hour FROM _dates
			WHERE HOUR(_dates.option_value) = 0 '
		);

		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
		$this->assertEquals( '0', $results[0]->hour );
	}

	public function testSelectByDateFunctions() {
		$this->assertQuery(
			"
			INSERT INTO _dates (option_name, option_value)
			VALUES ('second', '2014-10-21 00:42:29');
		"
		);

		// HOUR(14:08) should yield 14 in the 24 hour format
		$this->assertQuery(
			'
			SELECT * FROM _dates WHERE
              year(option_value) = 2014
              AND monthnum(option_value) = 10
              AND day(option_value) = 21
              AND hour(option_value) = 0
              AND minute(option_value) = 42
		'
		);
		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
	}

	public function testSelectByDateFormat() {
		$this->assertQuery(
			"
			INSERT INTO _dates (option_name, option_value)
			VALUES ('second', '2014-10-21 00:42:29');
		"
		);

		// HOUR(14:08) should yield 14 in the 24 hour format
		$this->assertQuery(
			"
			SELECT * FROM _dates WHERE DATE_FORMAT(option_value, '%H.%i') = 0.42
		"
		);
		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
	}

	public function testInsertOnDuplicateKey() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				name varchar(20) NOT NULL default '',
				UNIQUE KEY name (name)
			);"
		);
		$result1 = $this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('first');" );
		$this->assertEquals( 1, $result1 );

		$result2 = $this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('FIRST') ON DUPLICATE KEY UPDATE name=VALUES(`name`);" );
		$this->assertEquals( 1, $result2 );

		$this->assertQuery( 'SELECT COUNT(*) as cnt FROM _tmp_table' );
		$results = $this->engine->get_query_results();
		$this->assertEquals( 1, $results[0]->cnt );
	}

	public function testCreateTableCompositePk() {
		$this->assertQuery(
			'CREATE TABLE wptests_term_relationships (
				object_id bigint(20) unsigned NOT NULL default 0,
				term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
				term_order int(11) NOT NULL default 0,
				PRIMARY KEY  (object_id,term_taxonomy_id),
				KEY term_taxonomy_id (term_taxonomy_id)
			   ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
		);
		$result = $this->engine->query( 'INSERT INTO wptests_term_relationships VALUES (1,2,1),(1,3,2);' );
		$this->assertEquals( 2, $result );

		$this->expectExceptionMessage( 'UNIQUE constraint failed: wptests_term_relationships.object_id, wptests_term_relationships.term_taxonomy_id' );
		$this->engine->query( 'INSERT INTO wptests_term_relationships VALUES (1,2,2),(1,3,1);' );
	}

	public function testDescribeAccurate() {
		$result = $this->assertQuery(
			'CREATE TABLE wptests_term_relationships (
				object_id bigint(20) unsigned NOT NULL default 0,
				term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
				term_name varchar(11) NOT NULL default 0,
				PRIMARY KEY  (object_id,term_taxonomy_id),
				KEY term_taxonomy_id (term_taxonomy_id),
				KEY compound_key (object_id(20),term_taxonomy_id(20)),
				FULLTEXT KEY term_name (term_name)
			   ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
		);
		$this->assertNotFalse( $result );

		$result = $this->assertQuery( 'DESCRIBE wptests_term_relationships;' );
		$this->assertNotFalse( $result );

		$fields = $this->engine->get_query_results();

		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'object_id',
					'Type'    => 'bigint(20) unsigned',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => '0',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'term_taxonomy_id',
					'Type'    => 'bigint(20) unsigned',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => '0',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'term_name',
					'Type'    => 'varchar(11)',
					'Null'    => 'NO',
					'Key'     => 'MUL',
					'Default' => '0',
					'Extra'   => '',
				),
			),
			$fields
		);
	}

	public function testAlterTableAddColumnChangesMySQLDataType() {
		$result = $this->assertQuery(
			'CREATE TABLE _test (
				object_id bigint(20) unsigned NOT NULL default 0
			)'
		);
		$this->assertNotFalse( $result );

		$result = $this->assertQuery( "ALTER TABLE `_test` ADD COLUMN object_name varchar(255) NOT NULL DEFAULT 'adb';" );
		$this->assertNotFalse( $result );

		$result = $this->assertQuery( 'DESCRIBE _test;' );
		$this->assertNotFalse( $result );
		$fields = $this->engine->get_query_results();

		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'object_id',
					'Type'    => 'bigint(20) unsigned',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '0',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'object_name',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => 'adb',
					'Extra'   => '',
				),
			),
			$fields
		);
	}
	public function testShowGrantsFor() {
		$result = $this->assertQuery( 'SHOW GRANTS FOR current_user();' );
		$this->assertEquals(
			$result,
			array(
				(object) array(
					'Grants for root@%' => 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, RELOAD, SHUTDOWN, PROCESS, FILE, REFERENCES, INDEX, ALTER, SHOW DATABASES, SUPER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, REPLICATION SLAVE, REPLICATION CLIENT, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, CREATE USER, EVENT, TRIGGER, CREATE TABLESPACE, CREATE ROLE, DROP ROLE ON *.* TO `root`@`localhost` WITH GRANT OPTION',
				),
			)
		);
	}

	public function testShowIndex() {
		$result = $this->assertQuery(
			'CREATE TABLE wptests_term_relationships (
				object_id bigint(20) unsigned NOT NULL default 0,
				term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
				term_name varchar(11) NOT NULL default 0,
				geom_col geometry NOT NULL,
				FULLTEXT KEY term_name_fulltext1 (term_name),
				FULLTEXT INDEX term_name_fulltext2 (`term_name`),
				SPATIAL KEY geom_col_spatial (geom_col),
				PRIMARY KEY  (object_id,term_taxonomy_id),
				KEY term_taxonomy_id (term_taxonomy_id),
				KEY compound_key (object_id,term_taxonomy_id)
			) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
		);
		$this->assertNotFalse( $result );

		$result = $this->assertQuery( 'SHOW INDEX FROM wptests_term_relationships;' );
		$this->assertNotFalse( $result );

		$this->assertEquals(
			array(
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '0',
					'Key_name'      => 'PRIMARY',
					'Seq_in_index'  => '1',
					'Column_name'   => 'object_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '0',
					'Key_name'      => 'PRIMARY',
					'Seq_in_index'  => '2',
					'Column_name'   => 'term_taxonomy_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'geom_col_spatial',
					'Seq_in_index'  => '1',
					'Column_name'   => 'geom_col',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => 32,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'SPATIAL',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'term_taxonomy_id',
					'Seq_in_index'  => '1',
					'Column_name'   => 'term_taxonomy_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'compound_key',
					'Seq_in_index'  => '1',
					'Column_name'   => 'object_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'compound_key',
					'Seq_in_index'  => '2',
					'Column_name'   => 'term_taxonomy_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'term_name_fulltext1',
					'Seq_in_index'  => '1',
					'Column_name'   => 'term_name',
					'Collation'     => null,
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'FULLTEXT',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'term_name_fulltext2',
					'Seq_in_index'  => '1',
					'Column_name'   => 'term_name',
					'Collation'     => null,
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'FULLTEXT',
					'Comment'       => '',
					'Index_comment' => '',
					'Visible'       => 'YES',
					'Expression'    => null,
				),
			),
			$this->engine->get_query_results()
		);

		// With WHERE clause.
		$this->assertQuery( "SHOW INDEX FROM wptests_term_relationships WHERE Key_name = 'PRIMARY'" );
		$actual = $this->engine->get_query_results();
		$this->assertCount( 2, $actual );

		$this->assertQuery( 'SHOW INDEX FROM wptests_term_relationships WHERE Non_unique = 0' );
		$actual = $this->engine->get_query_results();
		$this->assertCount( 2, $actual );

		$this->assertQuery( "SHOW INDEX FROM wptests_term_relationships WHERE Index_type = 'FULLTEXT'" );
		$actual = $this->engine->get_query_results();
		$this->assertCount( 2, $actual );
	}

	public function testShowVarianles(): void {
		$this->assertQuery( 'SHOW VARIABLES' );
		$this->assertQuery( "SHOW VARIABLES LIKE 'version'" );
		$this->assertQuery( "SHOW VARIABLES WHERE Variable_name = 'version'" );
		$this->assertQuery( 'SHOW GLOBAL VARIABLES' );
		$this->assertQuery( 'SHOW SESSION VARIABLES' );
	}

	public function testInsertOnDuplicateKeyCompositePk() {
		$result = $this->assertQuery(
			'CREATE TABLE wptests_term_relationships (
				object_id bigint(20) unsigned NOT NULL default 0,
				term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
				term_order int(11) NOT NULL default 0,
				PRIMARY KEY  (object_id,term_taxonomy_id),
				KEY term_taxonomy_id (term_taxonomy_id)
			   ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
		);
		$this->assertNotFalse( $result );

		$result1 = $this->assertQuery( 'INSERT INTO wptests_term_relationships VALUES (1,2,1),(1,3,2);' );
		$this->assertEquals( 2, $result1 );

		$result2 = $this->assertQuery( 'INSERT INTO wptests_term_relationships VALUES (1,2,2),(1,3,1) ON DUPLICATE KEY UPDATE term_order = VALUES(term_order);' );
		$this->assertEquals( 2, $result2 );

		$this->assertQuery( 'SELECT COUNT(*) as cnt FROM wptests_term_relationships' );
		$results = $this->engine->get_query_results();
		$this->assertEquals( 2, $results[0]->cnt );
	}

	public function testStringToFloatComparison() {
		$this->assertQuery( "SELECT ('00.42' = 0.4200) as cmp;" );
		$results = $this->engine->get_query_results();
		if ( 1 !== $results[0]->cmp ) {
			$this->markTestSkipped( 'Comparing a string and a float returns true in MySQL. In SQLite, they\'re different. Skipping. ' );
		}
		$this->assertEquals( '1', $results[0]->cmp );

		$this->assertQuery( "SELECT (0+'00.42' = 0.4200) as cmp;" );
		$results = $this->engine->get_query_results();
		$this->assertEquals( '1', $results[0]->cmp );
	}

	public function testZeroPlusStringToFloatComparison() {

		$this->assertQuery( "SELECT (0+'00.42' = 0.4200) as cmp;" );
		$results = $this->engine->get_query_results();
		$this->assertEquals( '1', $results[0]->cmp );

		$this->assertQuery( "SELECT 0+'1234abcd' = 1234 as cmp;" );
		$results = $this->engine->get_query_results();
		$this->assertEquals( '1', $results[0]->cmp );
	}

	public function testCalcFoundRows() {
		$result = $this->assertQuery(
			"CREATE TABLE wptests_dummy (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				user_login TEXT NOT NULL default ''
			);"
		);
		$this->assertNotFalse( $result );

		$result = $this->assertQuery(
			"INSERT INTO wptests_dummy (user_login) VALUES ('test1');"
		);
		$this->assertEquals( 1, $result );

		$result = $this->assertQuery(
			"INSERT INTO wptests_dummy (user_login) VALUES ('test2');"
		);
		$this->assertEquals( 1, $result );

		$result = $this->assertQuery(
			'SELECT SQL_CALC_FOUND_ROWS * FROM wptests_dummy ORDER BY ID LIMIT 1'
		);
		$this->assertNotFalse( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'test1', $result[0]->user_login );

		$result = $this->assertQuery(
			'SELECT FOUND_ROWS()'
		);
		$this->assertEquals(
			array(
				(object) array(
					'FOUND_ROWS()' => '2',
				),
			),
			$result
		);
	}

	public function testComplexSelectBasedOnDates() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 10:08:48');"
		);

		$this->assertQuery(
			'SELECT SQL_CALC_FOUND_ROWS  _dates.ID
		FROM _dates
		WHERE YEAR( _dates.option_value ) = 2003 AND MONTH( _dates.option_value ) = 5 AND DAYOFMONTH( _dates.option_value ) = 27
		ORDER BY _dates.option_value DESC
		LIMIT 0, 10'
		);

		$results = $this->engine->get_query_results();
		$this->assertCount( 1, $results );
	}

	public function testUpdateReturnValue() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 10:08:48');"
		);

		$return = $this->assertQuery(
			"UPDATE _dates SET option_value = '2001-05-27 10:08:48'"
		);
		$this->assertSame( 1, $return, 'UPDATE query did not return 1 when one row was changed' );

		$return = $this->assertQuery(
			"UPDATE _dates SET option_value = '2001-05-27 10:08:48'"
		);
		if ( 1 === $return ) {
			$this->markTestIncomplete(
				'SQLite UPDATE query returned 1 when no rows were changed. ' .
				'This is a database compatibility issue – MySQL would return 0 ' .
				'in the same scenario.'
			);
		}
		$this->assertSame( 0, $return, 'UPDATE query did not return 0 when no rows were changed' );
	}

	public function testOrderByField() {
		$this->assertQuery(
			"INSERT INTO _options (option_name, option_value) VALUES ('User 0000019', 'second');"
		);
		$this->assertQuery(
			"INSERT INTO _options (option_name, option_value) VALUES ('User 0000020', 'third');"
		);
		$this->assertQuery(
			"INSERT INTO _options (option_name, option_value) VALUES ('User 0000018', 'first');"
		);

		$this->assertQuery( 'SELECT FIELD(option_name, "User 0000018", "User 0000019", "User 0000020") as sorting_order FROM _options ORDER BY FIELD(option_name, "User 0000018", "User 0000019", "User 0000020")' );

		$this->assertEquals(
			array(
				(object) array(
					'sorting_order' => '1',
				),
				(object) array(
					'sorting_order' => '2',
				),
				(object) array(
					'sorting_order' => '3',
				),
			),
			$this->engine->get_query_results()
		);

		$this->assertQuery( 'SELECT option_value FROM _options ORDER BY FIELD(option_name, "User 0000018", "User 0000019", "User 0000020")' );

		$this->assertEquals(
			array(
				(object) array(
					'option_value' => 'first',
				),
				(object) array(
					'option_value' => 'second',
				),
				(object) array(
					'option_value' => 'third',
				),
			),
			$this->engine->get_query_results()
		);
	}

	public function testFetchedDataIsStringified() {
		$this->assertQuery(
			"INSERT INTO _options (option_name, option_value) VALUES ('rss_0123456789abcdef0123456789abcdef', '1');"
		);

		$this->assertQuery( 'SELECT ID FROM _options' );

		$this->assertEquals(
			array(
				(object) array(
					'ID' => '1',
				),
			),
			$this->engine->get_query_results()
		);
	}

	public function testCreateTableQuery() {
		$this->assertQuery(
			<<<'QUERY'
            CREATE TABLE IF NOT EXISTS wptests_users (
                ID bigint(20) unsigned NOT NULL auto_increment,
                user_login varchar(60) NOT NULL default '',
                user_pass varchar(255) NOT NULL default '',
                user_nicename varchar(50) NOT NULL default '',
                user_email varchar(100) NOT NULL default '',
                user_url varchar(100) NOT NULL default '',
                user_registered datetime NOT NULL default '0000-00-00 00:00:00',
                user_activation_key varchar(255) NOT NULL default '',
                user_status int(11) NOT NULL default '0',
                display_name varchar(250) NOT NULL default '',
                PRIMARY KEY  (ID),
                KEY user_login_key (user_login),
                KEY user_nicename (user_nicename),
                KEY user_email (user_email)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci
QUERY
		);
		$this->assertQuery(
			<<<'QUERY'
            INSERT INTO wptests_users VALUES (1,'admin','$P$B5ZQZ5ZQZ5ZQZ5ZQZ5ZQZ5ZQZ5ZQZ5','admin','admin@localhost', '', '2019-01-01 00:00:00', '', 0, 'admin');
QUERY
		);
		$rows = $this->assertQuery( 'SELECT * FROM wptests_users' );
		$this->assertCount( 1, $rows );

		$this->assertQuery( 'SELECT SQL_CALC_FOUND_ROWS * FROM wptests_users' );
		$result = $this->assertQuery( 'SELECT FOUND_ROWS()' );
		$this->assertEquals(
			array(
				(object) array(
					'FOUND_ROWS()' => '1',
				),
			),
			$result
		);
	}

	public function testCreateTableIfNotExists(): void {
		$this->assertQuery(
			'CREATE TABLE t (ID INTEGER, name TEXT)'
		);
		$this->assertQuery(
			'CREATE TABLE IF NOT EXISTS t (ID INTEGER, name TEXT)'
		);

		$this->expectExceptionMessage( "Table 't' already exists" );
		$this->assertQuery(
			'CREATE TABLE t (ID INTEGER, name TEXT)'
		);
	}

	public function testCreateTemporaryTableIfNotExists(): void {
		$this->assertQuery(
			'CREATE TEMPORARY TABLE t (ID INTEGER, name TEXT)'
		);
		$this->assertQuery(
			'CREATE TEMPORARY TABLE IF NOT EXISTS t (ID INTEGER, name TEXT)'
		);

		$this->expectExceptionMessage( "Table 't' already exists" );
		$this->assertQuery(
			'CREATE TEMPORARY TABLE t (ID INTEGER, name TEXT)'
		);
	}

	public function testTranslatesComplexDelete() {
		$this->sqlite->query(
			"CREATE TABLE wptests_dummy (
				ID INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
				user_login TEXT NOT NULL default '',
				option_name TEXT NOT NULL default '',
				option_value TEXT NOT NULL default ''
			);"
		);
		$this->sqlite->query(
			"INSERT INTO wptests_dummy (user_login, option_name, option_value) VALUES ('admin', '_transient_timeout_test', '1675963960');"
		);
		$this->sqlite->query(
			"INSERT INTO wptests_dummy (user_login, option_name, option_value) VALUES ('admin', '_transient_test', '1675963960');"
		);

		$result = $this->assertQuery(
			"DELETE a, b FROM wptests_dummy a, wptests_dummy b
				WHERE a.option_name LIKE '\_transient\_%'
				AND a.option_name NOT LIKE '\_transient\_timeout_%'
				AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) );"
		);
		$this->assertEquals(
			2,
			$result
		);
	}

	public function testTranslatesDoubleAlterTable() {
		$result = $this->assertQuery(
			'ALTER TABLE _options
				ADD INDEX test_index(option_name(140),option_value(51)),
				DROP INDEX test_index,
				ADD INDEX test_index2(option_name(140),option_value(51))
			'
		);
		$this->assertNull( $result );

		$result = $this->assertQuery(
			'SHOW INDEX FROM _options'
		);
		$this->assertCount( 3, $result );
		$this->assertEquals( 'PRIMARY', $result[0]->Key_name );
		$this->assertEquals( 'test_index2', $result[1]->Key_name );
		$this->assertEquals( 'test_index2', $result[2]->Key_name );
	}

	public function testTranslatesComplexSelect() {
		$this->assertQuery(
			"CREATE TABLE wptests_postmeta (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				post_id bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY post_id (post_id),
				KEY meta_key (meta_key(191))
			) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci"
		);
		$this->assertQuery(
			"CREATE TABLE wptests_posts (
				ID bigint(20) unsigned NOT NULL auto_increment,
				post_status varchar(20) NOT NULL default 'open',
				post_type varchar(20) NOT NULL default 'post',
				post_date varchar(20) NOT NULL default 'post',
				PRIMARY KEY  (ID)
			) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci"
		);
		$result = $this->assertQuery(
			"SELECT SQL_CALC_FOUND_ROWS  wptests_posts.ID
				FROM wptests_posts  INNER JOIN wptests_postmeta ON ( wptests_posts.ID = wptests_postmeta.post_id )
				WHERE 1=1
				AND (
					NOT EXISTS (
						SELECT 1 FROM wptests_postmeta mt1
						WHERE mt1.post_ID = wptests_postmeta.post_ID
						LIMIT 1
					)
				)
				 AND (
					(wptests_posts.post_type = 'post' AND (wptests_posts.post_status = 'publish'))
				)
			GROUP BY wptests_posts.ID
			ORDER BY wptests_posts.post_date DESC
			LIMIT 0, 10"
		);

		// No exception is good enough of a test for now
		$this->assertTrue( true );
	}

	public function testTranslatesUtf8Insert() {
		$this->assertQuery(
			"INSERT INTO _options VALUES(1,'ąłółźćę†','ąłółźćę†')"
		);
		$this->assertCount(
			1,
			$this->assertQuery( 'SELECT * FROM _options' )
		);
		$this->assertQuery( 'DELETE FROM _options' );
	}

	public function testTranslatesRandom() {
		$this->assertIsNumeric(
			$this->sqlite->query( 'SELECT RAND() AS rand' )->fetchColumn()
		);

		$this->assertIsNumeric(
			$this->sqlite->query( 'SELECT RAND(5) AS rand' )->fetchColumn()
		);
	}

	public function testTranslatesUtf8SELECT() {
		$this->assertQuery(
			"INSERT INTO _options VALUES(1,'ąłółźćę†','ąłółźćę†')"
		);
		$this->assertCount(
			1,
			$this->assertQuery( 'SELECT * FROM _options' )
		);

		$this->assertQuery(
			"SELECT option_name as 'ą' FROM _options WHERE option_name='ąłółźćę†' AND option_value='ąłółźćę†'"
		);

		$this->assertEquals(
			array( (object) array( 'ą' => 'ąłółźćę†' ) ),
			$this->engine->get_query_results()
		);

		$this->assertQuery(
			"SELECT option_name as 'ą' FROM _options WHERE option_name LIKE '%ółźć%'"
		);

		$this->assertEquals(
			array( (object) array( 'ą' => 'ąłółźćę†' ) ),
			$this->engine->get_query_results()
		);

		$this->assertQuery( 'DELETE FROM _options' );
	}

	public function testTranslateLikeBinary() {
		// Create a temporary table for testing
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
              ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
              name varchar(20)
       	    )'
		);

		// Insert data into the table
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('first');" );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('FIRST');" );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('second');" );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('');" );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('%special%');" );
		$this->assertQuery( 'INSERT INTO _tmp_table (name) VALUES (NULL);' );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('special%chars');" );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('special_chars');" );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('special\\\\chars');" );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('aste*risk');" );
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('question?mark');" );

		// Test exact string
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'first'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test exact string with no matches
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'third'" );
		$this->assertCount( 0, $result );

		// Test mixed case
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'First'" );
		$this->assertCount( 0, $result );

		// Test % wildcard
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'f%'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test % wildcard with no matches
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'x%'" );
		$this->assertCount( 0, $result );

		// Test "%" character (not a wildcard)
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'special\\%chars'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'special%chars', $result[0]->name );

		// Test _ wildcard
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'f_rst'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test _ wildcard with no matches
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'x_yz'" );
		$this->assertCount( 0, $result );

		// Test "_" character (not a wildcard)
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'special\\_chars'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'special_chars', $result[0]->name );

		// Test escaping of "*"
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'aste*risk'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'aste*risk', $result[0]->name );

		// Test escaping of "*" with no matches
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'f*'" );
		$this->assertCount( 0, $result );

		// Test escaping of "?"
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'question?mark'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'question?mark', $result[0]->name );

		// Test escaping of "?" with no matches
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'f?rst'" );
		$this->assertCount( 0, $result );

		// Test escaping of character class
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY '[f]irst'" );
		$this->assertCount( 0, $result );

		// Test NULL
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE name LIKE BINARY NULL' );
		$this->assertCount( 0, $result );

		// Test pattern with special characters using LIKE BINARY
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY '%special%'" );
		$this->assertCount( 4, $result );
		$this->assertEquals( '%special%', $result[0]->name );
		$this->assertEquals( 'special%chars', $result[1]->name );
		$this->assertEquals( 'special_chars', $result[2]->name );
		$this->assertEquals( 'special\chars', $result[3]->name );

		// Test escaping - "\t" is a tab character
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'firs\\t'" );
		$this->assertCount( 0, $result );

		// Test escaping - "\\t" is "t" (input resolves to "\t", which LIKE resolves to "t")
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'firs\\\\t'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test escaping - "\%" is a "%" literal
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'special\\%chars'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'special%chars', $result[0]->name );

		// Test escaping - "\\%" is also a "%" literal
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'special\\\\%chars'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'special%chars', $result[0]->name );

		// Test escaping - "\\\%" is "\" and a wildcard
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'special\\\\\\%chars'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'special\\chars', $result[0]->name );

		// Test LIKE without BINARY
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE 'FIRST'" );
		$this->assertCount( 2, $result ); // Should match both 'first' and 'FIRST'
	}

	public function testUniqueConstraints() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
				name varchar(20) NOT NULL default 'default-value',
				unique_name varchar(20) NOT NULL default 'unique-default-value',
				inline_unique_name varchar(30) NOT NULL default 'inline-unique-default-value' UNIQUE,
				UNIQUE KEY unique_name (unique_name),
				UNIQUE KEY compound_name (name, unique_name)
			);"
		);

		// Insert a row with default values.
		$this->assertQuery( 'INSERT INTO _tmp_table (ID) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE ID = 1' );
		$this->assertEquals(
			array(
				(object) array(
					'ID'                 => '1',
					'name'               => 'default-value',
					'unique_name'        => 'unique-default-value',
					'inline_unique_name' => 'inline-unique-default-value',
				),
			),
			$result
		);

		// Insert another row.
		$this->assertQuery(
			"INSERT INTO _tmp_table VALUES (2, 'ANOTHER-VALUE', 'ANOTHER-UNIQUE-VALUE', 'ANOTHER-INLINE-UNIQUE-VALUE')"
		);

		// This should fail because of the UNIQUE constraints.
		$error = '';
		try {
			$this->assertQuery(
				"UPDATE _tmp_table SET unique_name = 'unique-default-value' WHERE ID = 2"
			);
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.unique_name', $error );

		$error = '';
		try {
			$this->assertQuery(
				"UPDATE _tmp_table SET inline_unique_name = 'inline-unique-default-value' WHERE ID = 2"
			);
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.inline_unique_name', $error );

		// Updating "name" to the same value as the first row should pass.
		$this->assertQuery(
			"UPDATE _tmp_table SET name = 'default-value' WHERE ID = 2"
		);
		$this->assertEquals(
			array(
				(object) array(
					'ID'                 => '2',
					'name'               => 'default-value',
					'unique_name'        => 'ANOTHER-UNIQUE-VALUE',
					'inline_unique_name' => 'ANOTHER-INLINE-UNIQUE-VALUE',
				),
			),
			$this->assertQuery( 'SELECT * FROM _tmp_table WHERE ID = 2' )
		);

		// Updating also "unique_name" should fail on the compound UNIQUE key.
		$error = '';
		try {
			$this->assertQuery(
				"UPDATE _tmp_table SET inline_unique_name = 'inline-unique-default-value' WHERE ID = 2"
			);
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}
		$this->assertStringContainsString( 'UNIQUE constraint failed: _tmp_table.inline_unique_name', $error );

		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE ID = 2' );
		$this->assertEquals(
			array(
				(object) array(
					'ID'                 => '2',
					'name'               => 'default-value',
					'unique_name'        => 'ANOTHER-UNIQUE-VALUE',
					'inline_unique_name' => 'ANOTHER-INLINE-UNIQUE-VALUE',
				),
			),
			$result
		);
	}

	public function testDefaultNullValue() {
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				name varchar(20) default NULL,
				no_default varchar(20) NOT NULL
			);'
		);

		$result = $this->assertQuery(
			'DESCRIBE _tmp_table;'
		);
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(20)',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'no_default',
					'Type'    => 'varchar(20)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$result
		);
	}

	public function testCurrentTimestamp() {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// SELECT
		$results = $this->assertQuery(
			'SELECT
				current_timestamp AS t1,
				CURRENT_TIMESTAMP AS t2,
				current_timestamp() AS t3,
				CURRENT_TIMESTAMP() AS t4'
		);
		$this->assertIsArray( $results );
		$this->assertCount( 1, $results );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $results[0]->t1 );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $results[0]->t2 );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $results[0]->t3 );

		// INSERT
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', CURRENT_TIMESTAMP())"
		);
		$results = $this->assertQuery( 'SELECT option_value AS t FROM _dates' );
		$this->assertCount( 1, $results );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $results[0]->t );

		// UPDATE
		$this->assertQuery( "UPDATE _dates SET option_value = '0000-00-00 00:00:00'" );
		$results = $this->assertQuery( 'SELECT option_value AS t FROM _dates' );
		$this->assertCount( 1, $results );
		$this->assertEquals( '0000-00-00 00:00:00', $results[0]->t );

		$this->assertQuery( 'UPDATE _dates SET option_value = CURRENT_TIMESTAMP()' );
		$results = $this->assertQuery( 'SELECT option_value AS t FROM _dates' );
		$this->assertCount( 1, $results );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $results[0]->t );
		$this->assertNotEquals( '0000-00-00 00:00:00', $results[0]->t );

		// DELETE
		// We can only assert that the query passes. It is not guaranteed that we'll actually
		// delete the existing record, as the delete query could fall into a different second.
		$this->assertQuery( 'DELETE FROM _dates WHERE option_value = CURRENT_TIMESTAMP()' );
	}

	public function testDatabaseFunction(): void {
		$this->assertQuery( "SELECT DATABASE(), CONCAT('test-', (SELECT DATABASE()))" );
		$this->assertEquals(
			array(
				(object) array(
					'DATABASE()'                           => 'wp',
					"CONCAT('test-', (SELECT DATABASE()))" => 'test-wp',
				),
			),
			$this->engine->get_query_results()
		);
	}

	public function testGroupByHaving() {
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				name varchar(20)
			);'
		);

		$this->assertQuery(
			"INSERT INTO _tmp_table VALUES ('a'), ('b'), ('b'), ('c'), ('c'), ('c')"
		);

		$result = $this->assertQuery(
			'SELECT name, COUNT(*) as count FROM _tmp_table GROUP BY name HAVING COUNT(*) > 1'
		);
		$this->assertEquals(
			array(
				(object) array(
					'name'  => 'b',
					'count' => '2',
				),
				(object) array(
					'name'  => 'c',
					'count' => '3',
				),
			),
			$result
		);
	}

	public function testHavingWithoutGroupBy() {
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				name varchar(20)
			);'
		);

		$this->assertQuery(
			"INSERT INTO _tmp_table VALUES ('a'), ('b'), ('b'), ('c'), ('c'), ('c')"
		);

		// HAVING condition satisfied
		$result = $this->assertQuery(
			"SELECT 'T' FROM _tmp_table HAVING COUNT(*) > 1"
		);
		$this->assertEquals(
			array(
				(object) array(
					'T' => 'T',
				),
			),
			$result
		);

		// HAVING condition not satisfied
		$result = $this->assertQuery(
			"SELECT 'T' FROM _tmp_table HAVING COUNT(*) > 100"
		);
		$this->assertEquals(
			array(),
			$result
		);

		// DISTINCT ... HAVING, where only some results meet the HAVING condition
		$result = $this->assertQuery(
			'SELECT DISTINCT name FROM _tmp_table HAVING COUNT(*) > 1'
		);
		$this->assertEquals(
			array(
				(object) array(
					'name' => 'b',
				),
				(object) array(
					'name' => 'c',
				),
			),
			$result
		);
	}

	/**
	 * @dataProvider mysqlVariablesToTest
	 */
	public function testSelectVariable( $variable_name ) {
		// Make sure the query does not error
		$this->assertQuery( "SELECT $variable_name;" );
	}

	public static function mysqlVariablesToTest() {
		return array(
			// NOTE: This list was derived from the variables used by the UpdraftPlus plugin.
			// We will start here and plan to expand supported variables over time.
			array( '@@character_set_client' ),
			array( '@@character_set_results' ),
			array( '@@collation_connection' ),
			array( '@@GLOBAL.gtid_purged' ),
			array( '@@GLOBAL.log_bin' ),
			array( '@@GLOBAL.log_bin_trust_function_creators' ),
			array( '@@GLOBAL.sql_mode' ),
			array( '@@SESSION.max_allowed_packet' ),
			array( '@@SESSION.sql_mode' ),

			// Intentionally mix letter casing to help demonstrate case-insensitivity
			array( '@@cHarActer_Set_cLient' ),
			array( '@@gLoBAL.gTiD_purGed' ),
			array( '@@sEssIOn.sqL_moDe' ),
		);
	}

	public function testLastInsertId(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT,
				name VARCHAR(20)
			);'
		);

		$this->assertQuery( "INSERT INTO t (name) VALUES ('a')" );
		$this->assertEquals( 1, $this->engine->get_insert_id() );

		$this->assertQuery( "INSERT INTO t (name) VALUES ('b')" );
		$this->assertEquals( 2, $this->engine->get_insert_id() );
	}

	public function testCharLength(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				ID INTEGER PRIMARY KEY AUTO_INCREMENT,
				name VARCHAR(20)
			);'
		);

		$this->assertQuery( "INSERT INTO t (name) VALUES ('a')" );
		$this->assertQuery( "INSERT INTO t (name) VALUES ('ab')" );
		$this->assertQuery( "INSERT INTO t (name) VALUES ('abc')" );

		$this->assertQuery( 'SELECT CHAR_LENGTH(name) AS len FROM t' );
		$this->assertEquals(
			array(
				(object) array( 'len' => '1' ),
				(object) array( 'len' => '2' ),
				(object) array( 'len' => '3' ),
			),
			$this->engine->get_query_results()
		);
	}

	public function testNullCharactersInStrings(): void {
		$this->assertQuery(
			'CREATE TABLE t (id INT, name VARCHAR(20))'
		);
		$this->assertQuery(
			"INSERT INTO t (name) VALUES ('a\0b')"
		);
		$this->assertQuery(
			'SELECT name FROM t'
		);
		$this->assertEquals(
			array(
				(object) array( 'name' => "a\0b" ),
			),
			$this->engine->get_query_results()
		);
	}

	public function testLargeNumberOfNullCharactersInStrings(): void {
		$this->assertQuery( 'CREATE TABLE t (value TEXT)' );

		$long_string_with_null_bytes = str_repeat( "abcdef\0xyz", 1000 );

		$this->assertQuery(
			sprintf(
				"INSERT INTO t (value) VALUES ('%s')",
				$long_string_with_null_bytes
			)
		);

		$result = $this->assertQuery( 'SELECT value FROM t' );
		$this->assertSame( $long_string_with_null_bytes, $result[0]->value );
	}

	public function testColumnDefaults(): void {
		$this->assertQuery(
			"
			CREATE TABLE t (
				name varchar(255) DEFAULT 'CURRENT_TIMESTAMP',
				type varchar(255) NOT NULL DEFAULT 'DEFAULT',
				description varchar(250) NOT NULL DEFAULT '',
				created_at timestamp DEFAULT CURRENT_TIMESTAMP,
				updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			)
		"
		);

		$result = $this->assertQuery( 'DESCRIBE t' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'name',
					'Type'    => 'varchar(255)',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => 'CURRENT_TIMESTAMP',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'type',
					'Type'    => 'varchar(255)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => 'DEFAULT',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'description',
					'Type'    => 'varchar(250)',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'created_at',
					'Type'    => 'timestamp',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => 'CURRENT_TIMESTAMP',
					'Extra'   => 'DEFAULT_GENERATED',
				),
				(object) array(
					'Field'   => 'updated_at',
					'Type'    => 'timestamp',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => 'CURRENT_TIMESTAMP',
					'Extra'   => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP',
				),
			),
			$result
		);

		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertEquals(
			"CREATE TABLE `t` (\n"
				. "  `name` varchar(255) DEFAULT 'CURRENT_TIMESTAMP',\n"
				. "  `type` varchar(255) NOT NULL DEFAULT 'DEFAULT',\n"
				. "  `description` varchar(250) NOT NULL DEFAULT '',\n"
				. "  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,\n"
				. "  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n"
				. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
			$result[0]->{'Create Table'}
		);
	}

	public function testSelectNonExistentColumn(): void {
		$this->assertQuery(
			'CREATE TABLE t (id INT)'
		);

		/*
		 * Here, we're basically testing that identifiers are escaped using
		 * backticks instead of double quotes. In SQLite, double quotes may
		 * fallback to a string literal and thus produce no error.
		 *
		 * See:
		 *   https://www.sqlite.org/quirks.html#double_quoted_string_literals_are_accepted
		 */
		$this->expectExceptionMessage( 'no such column: non_existent_column' );
		$this->assertQuery( 'SELECT non_existent_column FROM t LIMIT 0' );
	}

	public function testUnion(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, name VARCHAR(20))' );
		$this->assertQuery( "INSERT INTO t (id, name) VALUES (1, 'a')" );
		$this->assertQuery( "INSERT INTO t (id, name) VALUES (2, 'b')" );

		$this->assertQuery(
			'SELECT name FROM t WHERE id = 1 UNION SELECT name FROM t WHERE id = 2'
		);
		$this->assertEquals(
			array(
				(object) array( 'name' => 'a' ),
				(object) array( 'name' => 'b' ),
			),
			$this->engine->get_query_results()
		);

		$this->assertQuery(
			'SELECT name FROM t WHERE id = 1 UNION SELECT name FROM t WHERE id = 1'
		);
		$this->assertEquals(
			array(
				(object) array( 'name' => 'a' ),
			),
			$this->engine->get_query_results()
		);
	}

	public function testUnionAll(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, name VARCHAR(20))' );
		$this->assertQuery( "INSERT INTO t (id, name) VALUES (1, 'a')" );
		$this->assertQuery( "INSERT INTO t (id, name) VALUES (2, 'b')" );

		$this->assertQuery(
			'SELECT name FROM t WHERE id = 1 UNION SELECT name FROM t WHERE id = 2'
		);
		$this->assertEquals(
			array(
				(object) array( 'name' => 'a' ),
				(object) array( 'name' => 'b' ),
			),
			$this->engine->get_query_results()
		);

		$this->assertQuery(
			'SELECT name FROM t WHERE id = 1 UNION ALL SELECT name FROM t WHERE id = 1'
		);
		$this->assertEquals(
			array(
				(object) array( 'name' => 'a' ),
				(object) array( 'name' => 'a' ),
			),
			$this->engine->get_query_results()
		);
	}

	public function testShowCreateTableWithEmptyDatetimeDefault() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID BIGINT PRIMARY KEY AUTO_INCREMENT,
				timestamp1 datetime NOT NULL,
				timestamp2 date NOT NULL,
				timestamp3 time NOT NULL,
				timestamp4 timestamp NOT NULL,
				timestamp5 year NOT NULL,
				notempty1 datetime DEFAULT '1999-12-12 12:12:12',
				notempty2 date DEFAULT '1999-12-12',
				notempty3 time DEFAULT '12:12:12',
				notempty4 year DEFAULT '2024',
				notempty5 timestamp DEFAULT '1999-12-12 12:12:12'
			);"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp_table;'
		);
		$results = $this->engine->get_query_results();

		$this->assertEquals(
			"CREATE TABLE `_tmp_table` (
  `ID` bigint NOT NULL AUTO_INCREMENT,
  `timestamp1` datetime NOT NULL,
  `timestamp2` date NOT NULL,
  `timestamp3` time NOT NULL,
  `timestamp4` timestamp NOT NULL,
  `timestamp5` year NOT NULL,
  `notempty1` datetime DEFAULT '1999-12-12 12:12:12',
  `notempty2` date DEFAULT '1999-12-12',
  `notempty3` time DEFAULT '12:12:12',
  `notempty4` year DEFAULT '2024',
  `notempty5` timestamp NULL DEFAULT '1999-12-12 12:12:12',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTablePreservesKeyLengths() {
		$this->assertQuery(
			'CREATE TABLE _tmp__table (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`order_id` bigint(20) unsigned DEFAULT NULL,
					`meta_key` varchar(255) DEFAULT NULL,
					`meta_value` text DEFAULT NULL,
					`meta_data` mediumblob DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `meta_key_value` (`meta_key`(20),`meta_value`(82)),
					KEY `order_id_meta_key_meta_value` (`order_id`,`meta_key`(100),`meta_value`(82)),
					KEY `order_id_meta_key_meta_data` (`order_id`,`meta_key`(100),`meta_data`(100))
				);'
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp__table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			'CREATE TABLE `_tmp__table` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `meta_key` varchar(255) DEFAULT NULL,
  `meta_value` text DEFAULT NULL,
  `meta_data` mediumblob DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meta_key_value` (`meta_key`(20), `meta_value`(82)),
  KEY `order_id_meta_key_meta_value` (`order_id`, `meta_key`(100), `meta_value`(82)),
  KEY `order_id_meta_key_meta_data` (`order_id`, `meta_key`(100), `meta_data`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
			$results[0]->{'Create Table'}
		);
	}

	public function testTimestampColumnNamedTimestamp() {
		$this->assertQuery(
			'CREATE TABLE `_tmp_table` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`timestamp` datetime NOT NULL,
				PRIMARY KEY (`id`),
				KEY timestamp (timestamp)
			);'
		);
		$results = $this->assertQuery( 'DESCRIBE _tmp_table;' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'bigint(20) unsigned',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => null,
					'Extra'   => 'auto_increment',
				),
				(object) array(
					'Field'   => 'timestamp',
					'Type'    => 'datetime',
					'Null'    => 'NO',
					'Key'     => 'MUL',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$results
		);
	}

	public function testCompoundPrimaryKeyWithAutoincrement(): void {
		$this->assertQuery(
			'CREATE TABLE t1 (id INT AUTO_INCREMENT, name VARCHAR(32), PRIMARY KEY(id, name))'
		);

		// Ensure auto-increment is working.
		$this->assertQuery( "INSERT INTO t1 (name) VALUES ('A'), ('B'), ('C')" );
		$results = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertEquals(
			array(
				(object) array(
					'id'   => 1,
					'name' => 'A',
				),
				(object) array(
					'id'   => 2,
					'name' => 'B',
				),
				(object) array(
					'id'   => 3,
					'name' => 'C',
				),
			),
			$results
		);

		// Verify the table schema.
		$results = $this->assertQuery( 'SHOW CREATE TABLE t1' );
		$this->assertEquals(
			implode(
				"\n",
				array(
					'CREATE TABLE `t1` (',
					'  `id` int NOT NULL AUTO_INCREMENT,',
					'  `name` varchar(32) NOT NULL,',
					'  PRIMARY KEY (`id`, `name`)',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$results[0]->{'Create Table'}
		);

		// Ensure an SQLite index was created for the compound key columns.
		$result = $this->engine
			->execute_sqlite_query( "SELECT * FROM pragma_index_list('t1')" )
			->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => '_wp_sqlite_t1__primary',
				'unique'  => '1',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	/**
	 * @dataProvider getReservedPrefixTestData
	 */
	public function testReservedPrefix( string $query, string $error ): void {
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( $error );
		$this->assertQuery( $query );
	}

	public function getReservedPrefixTestData(): array {
		return array(
			array(
				'SELECT * FROM _wp_sqlite_t',
				"Invalid identifier '_wp_sqlite_t', prefix '_wp_sqlite_' is reserved",
			),
			array(
				'SELECT _wp_sqlite_t FROM t',
				"Invalid identifier '_wp_sqlite_t', prefix '_wp_sqlite_' is reserved",
			),
			array(
				'SELECT t._wp_sqlite_t FROM t',
				"Invalid identifier '_wp_sqlite_t', prefix '_wp_sqlite_' is reserved",
			),
			array(
				'CREATE TABLE _wp_sqlite_t (id INT)',
				"Invalid identifier '_wp_sqlite_t', prefix '_wp_sqlite_' is reserved",
			),
			array(
				'ALTER TABLE _wp_sqlite_t ADD COLUMN name TEXT',
				"Invalid identifier '_wp_sqlite_t', prefix '_wp_sqlite_' is reserved",
			),
			array(
				'DROP TABLE _wp_sqlite_t',
				"Invalid identifier '_wp_sqlite_t', prefix '_wp_sqlite_' is reserved",
			),
		);
	}

	/**
	 * @dataProvider getInformationSchemaIsReadonlyTestData
	 */
	public function testInformationSchemaIsReadonly( string $query ): void {
		$this->assertQuery( 'CREATE TABLE tables (id INT)' );
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( "Access denied for user 'root'@'%' to database 'information_schema'" );
		$this->assertQuery( $query );
	}

	public function getInformationSchemaIsReadonlyTestData(): array {
		return array(
			array( 'INSERT INTO information_schema.tables (table_name) VALUES ("t")' ),
			array( 'REPLACE INTO information_schema.tables (table_name) VALUES ("t")' ),
			array( 'UPDATE information_schema.tables SET table_name = "new_t" WHERE table_name = "t"' ),
			array( 'UPDATE information_schema.tables, information_schema.columns SET table_name = "new_t" WHERE table_name = "t"' ),
			array( 'DELETE FROM information_schema.tables WHERE table_name = "t"' ),
			array( 'DELETE it FROM t, information_schema.tables it WHERE table_name = "t"' ),
			array( 'TRUNCATE information_schema.tables' ),
			array( 'CREATE TABLE information_schema.new_table (id INT)' ),
			array( 'ALTER TABLE information_schema.tables ADD COLUMN new_column INT' ),
			array( 'DROP TABLE information_schema.tables' ),
			array( 'LOCK TABLES information_schema.tables READ' ),
			array( 'CREATE INDEX idx_name ON information_schema.tables (name)' ),
			array( 'DROP INDEX `PRIMARY` ON information_schema.tables' ),
			array( 'ANALYZE TABLE information_schema.tables' ),
			array( 'CHECK TABLE information_schema.tables' ),
			array( 'OPTIMIZE TABLE information_schema.tables' ),
			array( 'REPAIR TABLE information_schema.tables' ),
		);
	}

	/**
	 * @dataProvider getInformationSchemaIsReadonlyWithUseTestData
	 */
	public function testInformationSchemaIsReadonlyWithUse( string $query ): void {
		$this->assertQuery( 'CREATE TABLE tables (id INT)' );
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( "Access denied for user 'root'@'%' to database 'information_schema'" );
		$this->assertQuery( 'USE information_schema' );
		$this->assertQuery( $query );
	}

	public function getInformationSchemaIsReadonlyWithUseTestData(): array {
		return array(
			array( 'INSERT INTO tables (table_name) VALUES ("t")' ),
			array( 'REPLACE INTO tables (table_name) VALUES ("t")' ),
			array( 'UPDATE tables SET table_name = "new_t" WHERE table_name = "t"' ),
			array( 'UPDATE tables, columns SET table_name = "new_t" WHERE table_name = "t"' ),
			array( 'DELETE FROM tables WHERE table_name = "t"' ),
			array( 'DELETE it FROM t, tables it WHERE table_name = "t"' ),
			array( 'TRUNCATE tables' ),
			array( 'CREATE TABLE new_table (id INT)' ),
			array( 'ALTER TABLE tables ADD COLUMN new_column INT' ),
			array( 'DROP TABLE tables' ),
			array( 'LOCK TABLES tables READ' ),
			array( 'CREATE INDEX idx_name ON tables (name)' ),
			array( 'DROP INDEX `PRIMARY` ON tables' ),
			array( 'ANALYZE TABLE tables' ),
			array( 'CHECK TABLE tables' ),
			array( 'OPTIMIZE TABLE tables' ),
			array( 'REPAIR TABLE tables' ),
		);
	}

	public function testTemporaryTableHasPriorityOverStandardTable(): void {
		// Create a standard and a temporary table with the same name.
		$this->assertQuery( 'CREATE TABLE t (a INT, INDEX ia(a))' );
		$this->assertQuery( 'CREATE TEMPORARY TABLE t (b INT, INDEX ib(b))' );

		// SHOW CREATE TABLE will show the temporary table.
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertEquals(
			"CREATE TEMPORARY TABLE `t` (\n"
				. "  `b` int DEFAULT NULL,\n"
				. "  KEY `ib` (`b`)\n"
				. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
			$result[0]->{'Create Table'}
		);

		// SHOW COLUMNS FROM will show the temporary table.
		$result = $this->assertQuery( 'SHOW COLUMNS FROM t' );
		$this->assertEquals( 'b', $result[0]->Field );

		// DESCRIBE will show the temporary table.
		$result = $this->assertQuery( 'DESCRIBE t' );
		$this->assertEquals( 'b', $result[0]->Field );

		// SHOW INDEXES FROM will show the temporary table.
		$result = $this->assertQuery( 'SHOW INDEXES FROM t' );
		$this->assertEquals( 'ib', $result[0]->Key_name );

		// ALTER TABLE will use the temporary table.
		$this->assertQuery( 'ALTER TABLE t ADD COLUMN c INT' );
		$result = $this->assertQuery( 'SHOW COLUMNS FROM t' );
		$this->assertEquals( 'b', $result[0]->Field );
		$this->assertEquals( 'c', $result[1]->Field );

		// The temporary table doesn't show up in information schema.
		$result = $this->assertQuery( 'SELECT * FROM information_schema.columns WHERE table_name = "t"' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'a', $result[0]->COLUMN_NAME );

		// First DROP TABLE removes the temporary table.
		$this->assertQuery( 'DROP TABLE t' );
		$result = $this->assertQuery( 'SHOW COLUMNS FROM t' );
		$this->assertEquals( 'a', $result[0]->Field );

		// Second DROP TABLE removes the standard table.
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( "Table 'wp.t' doesn't exist" );
		$this->assertQuery( 'DROP TABLE t' );
		$result = $this->assertQuery( 'SHOW COLUMNS FROM t' );
	}

	public function testStrictSqlModeNullWithoutDefault(): void {
		$this->assertQuery( "SET SESSION sql_mode = 'STRICT_ALL_TABLES'" );

		// No value saves NULL:
		$this->assertQuery( 'CREATE TABLE t1 (id INT, value TEXT NULL)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );

		// NULL value saves NULL on INSERT:
		$this->assertQuery( 'CREATE TABLE t2 (id INT, value TEXT NULL)' );
		$this->assertQuery( 'INSERT INTO t2 (id, value) VALUES (1, NULL)' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );

		// NULL value saves NULL on UPDATE:
		$this->assertQuery( 'CREATE TABLE t3 (id INT, value TEXT NULL)' );
		$this->assertQuery( "INSERT INTO t3 (id, value) VALUES (1, 'initial-value')" );
		$this->assertQuery( 'UPDATE t3 SET value = NULL WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t3' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );
	}

	public function testStrictSqlModeNullWithDefault(): void {
		$this->assertQuery( "SET SESSION sql_mode = 'STRICT_ALL_TABLES'" );

		// No value saves DEFAULT:
		$this->assertQuery( "CREATE TABLE t1 (id INT, value TEXT NULL DEFAULT 'd')" );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'd', $result[0]->value );

		// NULL value saves NULL on INSERT:
		$this->assertQuery( "CREATE TABLE t2 (id INT, value TEXT NULL DEFAULT 'd')" );
		$this->assertQuery( 'INSERT INTO t2 (id, value) VALUES (1, NULL)' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );

		// NULL value saves NULL on UPDATE:
		$this->assertQuery( "CREATE TABLE t3 (id INT, value TEXT NULL DEFAULT 'd')" );
		$this->assertQuery( "INSERT INTO t3 (id, value) VALUES (1, 'initial-value')" );
		$this->assertQuery( 'UPDATE t3 SET value = NULL WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t3' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );
	}

	public function testStrictSqlModeNotNullWithoutDefault(): void {
		$this->assertQuery( "SET SESSION sql_mode = 'STRICT_ALL_TABLES'" );

		// No value is rejected:
		$this->assertQuery( 'CREATE TABLE t1 (id INT, value TEXT NOT NULL)' );
		$exception = null;
		try {
			$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t1.value',
			$exception->getMessage()
		);

		// NULL value is rejected on INSERT.
		$this->assertQuery( 'CREATE TABLE t2 (id INT, value TEXT NOT NULL)' );
		$exception = null;
		try {
			$this->assertQuery( 'INSERT INTO t2 (id, value) VALUES (1, NULL)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t2.value',
			$exception->getMessage()
		);

		// NULL value is rejected on UPDATE:
		$this->assertQuery( 'CREATE TABLE t3 (id INT, value TEXT NOT NULL)' );
		$exception = null;
		try {
			$this->assertQuery( "INSERT INTO t3 (id, value) VALUES (1, 'initial-value')" );
			$this->assertQuery( 'UPDATE t3 SET value = NULL WHERE id = 1' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t3.value',
			$exception->getMessage()
		);
	}

	public function testStrictSqlModeNotNullWithDefault(): void {
		$this->assertQuery( "SET SESSION sql_mode = 'STRICT_ALL_TABLES'" );

		// No value saves DEFAULT:
		$this->assertQuery( "CREATE TABLE t1 (id INT, value TEXT NOT NULL DEFAULT 'd')" );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'd', $result[0]->value );

		// NULL value is rejected on INSERT.
		$this->assertQuery( "CREATE TABLE t2 (id INT, value TEXT NOT NULL DEFAULT 'd')" );
		$exception = null;
		try {
			$this->assertQuery( 'INSERT INTO t2 (id, value) VALUES (1, NULL)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t2.value',
			$exception->getMessage()
		);

		// NULL value is rejected on UPDATE:
		$this->assertQuery( 'CREATE TABLE t3 (id INT, value TEXT NOT NULL DEFAULT "d")' );
		$exception = null;
		try {
			$this->assertQuery( "INSERT INTO t3 (id, value) VALUES (1, 'initial-value')" );
			$this->assertQuery( 'UPDATE t3 SET value = NULL WHERE id = 1' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t3.value',
			$exception->getMessage()
		);
	}

	public function testNonStrictSqlModeNullWithoutDefault(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// No value saves NULL:
		$this->assertQuery( 'CREATE TABLE t1 (id INT, value TEXT NULL)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );

		// NULL value saves NULL on INSERT:
		$this->assertQuery( 'CREATE TABLE t2 (id INT, value TEXT NULL)' );
		$this->assertQuery( 'INSERT INTO t2 (id, value) VALUES (1, NULL)' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );

		// NULL value saves NULL on UPDATE:
		$this->assertQuery( 'CREATE TABLE t3 (id INT, value TEXT NULL)' );
		$this->assertQuery( "INSERT INTO t3 (id, value) VALUES (1, 'initial-value')" );
		$this->assertQuery( 'UPDATE t3 SET value = NULL WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t3' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );
	}

	public function testNonStrictSqlModeNullWithDefault(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// No value saves DEFAULT:
		$this->assertQuery( "CREATE TABLE t1 (id INT, value TEXT NULL DEFAULT 'd')" );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'd', $result[0]->value );

		// NULL value saves NULL on INSERT:
		$this->assertQuery( "CREATE TABLE t2 (id INT, value TEXT NULL DEFAULT 'd')" );
		$this->assertQuery( 'INSERT INTO t2 (id, value) VALUES (1, NULL)' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );

		// NULL value saves NULL on UPDATE:
		$this->assertQuery( "CREATE TABLE t3 (id INT, value TEXT NULL DEFAULT 'd')" );
		$this->assertQuery( "INSERT INTO t3 (id, value) VALUES (1, 'initial-value')" );
		$this->assertQuery( 'UPDATE t3 SET value = NULL WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t3' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );
	}

	public function testNonStrictSqlModeNotNullWithoutDefault(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// No value saves IMPLICIT DEFAULT:
		$this->assertQuery( 'CREATE TABLE t1 (id INT, value TEXT NOT NULL)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertSame( '', $result[0]->value );

		// NULL value is rejected on INSERT.
		$this->assertQuery( 'CREATE TABLE t2 (id INT, value TEXT NOT NULL)' );
		$exception = null;
		try {
			$this->assertQuery( 'INSERT INTO t2 (id, value) VALUES (1, NULL)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t2.value',
			$exception->getMessage()
		);

		// NULL value saves IMPLICIT DEFAULT on UPDATE:
		$this->assertQuery( 'CREATE TABLE t3 (id INT, value TEXT NOT NULL)' );
		$this->assertQuery( "INSERT INTO t3 (id, value) VALUES (1, 'initial-value')" );
		$this->assertQuery( 'UPDATE t3 SET value = NULL WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t3' );
		$this->assertCount( 1, $result );
		$this->assertSame( '', $result[0]->value );
	}

	public function testNonStrictSqlModeNotNullWithDefault(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// No value saves DEFAULT:
		$this->assertQuery( "CREATE TABLE t1 (id INT, value TEXT NOT NULL DEFAULT 'd')" );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'd', $result[0]->value );

		// NULL value is rejected on INSERT.
		$this->assertQuery( "CREATE TABLE t2 (id INT, value TEXT NOT NULL DEFAULT 'd')" );
		$exception = null;
		try {
			$this->assertQuery( 'INSERT INTO t2 (id, value) VALUES (1, NULL)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t2.value',
			$exception->getMessage()
		);

		// NULL value saves IMPLICIT DEFAULT on UPDATE:
		$this->assertQuery( 'CREATE TABLE t3 (id INT, value TEXT NOT NULL DEFAULT "d")' );
		$this->assertQuery( "INSERT INTO t3 (id, value) VALUES (1, 'initial-value')" );
		$this->assertQuery( 'UPDATE t3 SET value = NULL WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t3' );
		$this->assertCount( 1, $result );
		$this->assertSame( '', $result[0]->value );
	}

	public function testNonStrictModeWithDefaultCurrentTimestamp(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$this->assertQuery( 'CREATE TABLE t (id INT, value TIMESTAMP DEFAULT CURRENT_TIMESTAMP)' );

		// INSERT without a value saves CURRENT_TIMESTAMP:
		$this->assertQuery( 'INSERT INTO t (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );
		$this->assertRegExp( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result[0]->value );

		// UPDATE with NULL saves NULL:
		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );
		$this->assertNull( $result[0]->value );
	}

	public function testNonStrictModeWithDefaultCurrentTimestampNotNull(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$this->assertQuery( 'CREATE TABLE t (id INT, value TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)' );

		// INSERT without a value saves CURRENT_TIMESTAMP:
		$this->assertQuery( 'INSERT INTO t (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );
		$this->assertRegExp( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result[0]->value );

		// UPDATE with NULL saves IMPLICIT DEFAULT:
		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );
		$this->assertSame( '0000-00-00 00:00:00', $result[0]->value );
	}

	public function testNonStrictSqlModeWithNoListedColumns(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// From VALUES() statement:
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT NOT NULL, size INT DEFAULT 123, color TEXT)' );
		$this->assertQuery( "INSERT INTO t1 VALUES (1, 'A', 10, 'red')" );
		$this->assertQuery( "INSERT INTO t1 VALUES (2, 'B', NULL, NULL)" );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 2, $result );
		$this->assertSame( '1', $result[0]->id );
		$this->assertSame( 'A', $result[0]->name );
		$this->assertSame( '10', $result[0]->size );
		$this->assertSame( 'red', $result[0]->color );
		$this->assertSame( '2', $result[1]->id );
		$this->assertSame( 'B', $result[1]->name );
		$this->assertNull( $result[1]->size );
		$this->assertNull( $result[1]->color );

		// From SELECT statement:
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT NOT NULL, size INT DEFAULT 999, color TEXT)' );
		$this->assertQuery( 'INSERT INTO t2 SELECT * FROM t1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertCount( 2, $result );
		$this->assertSame( '1', $result[0]->id );
		$this->assertSame( 'A', $result[0]->name );
		$this->assertSame( '10', $result[0]->size );
		$this->assertSame( 'red', $result[0]->color );
		$this->assertSame( '2', $result[1]->id );
		$this->assertSame( 'B', $result[1]->name );
		$this->assertNull( $result[1]->size );
		$this->assertNull( $result[1]->color );
	}

	public function testNonStrictSqlModeWithReorderedColumns(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// From VALUES() statement:
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT NOT NULL, size INT DEFAULT 123, color TEXT)' );
		$this->assertQuery( "INSERT INTO t1 (name, color, id, size) VALUES ('A', 'red', 1, 10)" );
		$this->assertQuery( "INSERT INTO t1 (name, id) VALUES ('B', 2)" );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 2, $result );
		$this->assertSame( '1', $result[0]->id );
		$this->assertSame( 'A', $result[0]->name );
		$this->assertSame( '10', $result[0]->size );
		$this->assertSame( 'red', $result[0]->color );
		$this->assertSame( '2', $result[1]->id );
		$this->assertSame( 'B', $result[1]->name );
		$this->assertSame( '123', $result[1]->size );
		$this->assertNull( $result[1]->color );

		// From SELECT statement:
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT NOT NULL, size INT DEFAULT 999, color TEXT)' );
		$this->assertQuery( 'INSERT INTO t2 (name, color, id, size) SELECT name, color, id, size FROM t1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertCount( 2, $result );
		$this->assertSame( '1', $result[0]->id );
		$this->assertSame( 'A', $result[0]->name );
		$this->assertSame( '10', $result[0]->size );
		$this->assertSame( 'red', $result[0]->color );
		$this->assertSame( '2', $result[1]->id );
		$this->assertSame( 'B', $result[1]->name );
		$this->assertSame( '123', $result[1]->size );
		$this->assertNull( $result[1]->color );
	}

	public function testNonStrictModeWithTemporaryTable(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// Create a non-temporary table with the same name, but different columns.
		// This should not be touched at all as temporary tables are prioritized.
		$this->assertQuery( 'CREATE TABLE t1 (value TEXT)' );

		// From VALUES() statement:
		$this->assertQuery( 'CREATE TEMPORARY TABLE t1 (id INT, name TEXT NOT NULL, size INT DEFAULT 123, color TEXT)' );
		$this->assertQuery( "INSERT INTO t1 VALUES (1, 'A', 10, 'red')" );
		$this->assertQuery( "INSERT INTO t1 (name, color, id, size) VALUES ('B', 'blue', 2, 20)" );
		$this->assertQuery( "INSERT INTO t1 (name, id) VALUES ('C', 3)" );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 3, $result );
		$this->assertSame( '1', $result[0]->id );
		$this->assertSame( 'A', $result[0]->name );
		$this->assertSame( '10', $result[0]->size );
		$this->assertSame( 'red', $result[0]->color );
		$this->assertSame( '2', $result[1]->id );
		$this->assertSame( 'B', $result[1]->name );
		$this->assertSame( '20', $result[1]->size );
		$this->assertSame( 'blue', $result[1]->color );
		$this->assertSame( '3', $result[2]->id );
		$this->assertSame( 'C', $result[2]->name );
		$this->assertSame( '123', $result[2]->size );
		$this->assertNull( $result[2]->color );

		// From SELECT statement:
		$this->assertQuery( 'CREATE TEMPORARY TABLE t2 (id INT, name TEXT NOT NULL, size INT DEFAULT 999, color TEXT)' );
		$this->assertQuery( 'INSERT INTO t2 (name, color, id, size) SELECT name, color, id, size FROM t1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertCount( 3, $result );
		$this->assertSame( '1', $result[0]->id );
		$this->assertSame( 'A', $result[0]->name );
		$this->assertSame( '10', $result[0]->size );
		$this->assertSame( 'red', $result[0]->color );
		$this->assertSame( '2', $result[1]->id );
		$this->assertSame( 'B', $result[1]->name );
		$this->assertSame( '20', $result[1]->size );
		$this->assertSame( 'blue', $result[1]->color );
		$this->assertSame( '3', $result[2]->id );
		$this->assertSame( 'C', $result[2]->name );
		$this->assertSame( '123', $result[2]->size );
		$this->assertNull( $result[2]->color );
	}

	public function testNonStrictModeWithOnDuplicateKeyUpdate(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// Create table and insert a row:
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY, name TEXT NOT NULL, size INT DEFAULT 123, color TEXT)' );
		$this->assertQuery( "INSERT INTO t1 VALUES (1, 'A', 10, 'red')" );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'A', $result[0]->name );
		$this->assertSame( '10', $result[0]->size );
		$this->assertSame( 'red', $result[0]->color );

		// Ensure ON DUPLICATE KEY UPDATE works:
		$this->assertQuery( "INSERT INTO t1 VALUES (1, 'B', 20, 'blue') ON DUPLICATE KEY UPDATE name = 'B'" );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'B', $result[0]->name );
		$this->assertSame( '10', $result[0]->size );
		$this->assertSame( 'red', $result[0]->color );

		// In MySQL, ON DUPLICATE KEY UPDATE ignores non-strict mode UPDATE behavior:
		$this->expectException( PDOException::class );
		$this->expectExceptionMessage( 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t1.name' );
		$this->assertQuery( "INSERT INTO t1 VALUES (1, 'C', 30, 'green') ON DUPLICATE KEY UPDATE name = NULL" );
	}

	public function testNonStrictModeWithReplaceStatement(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// From VALUES() statement:
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY, name TEXT NOT NULL, size INT DEFAULT 123, color TEXT)' );
		$this->assertQuery( "REPLACE INTO t1 VALUES (1, 'A', 10, 'red')" );
		$this->assertQuery( "REPLACE INTO t1 (color, id) VALUES ('blue', 1)" );
		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertCount( 1, $result );
		$this->assertSame( '1', $result[0]->id );
		$this->assertSame( '', $result[0]->name ); // implicit default
		$this->assertSame( '123', $result[0]->size );
		$this->assertSame( 'blue', $result[0]->color );

		// From SELECT statement:
		$this->assertQuery( 'CREATE TABLE t2 (id INT PRIMARY KEY, name TEXT NOT NULL, size INT DEFAULT 999, color TEXT)' );
		$this->assertQuery( "REPLACE INTO t2 VALUES (1, 'A', 10, 'red')" );
		$this->assertQuery( 'REPLACE INTO t2 (color, id, size) SELECT color, id, size FROM t1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertCount( 1, $result );
		$this->assertSame( '1', $result[0]->id );
		$this->assertSame( '', $result[0]->name ); // implicit default
		$this->assertSame( '123', $result[0]->size );
		$this->assertSame( 'blue', $result[0]->color );
	}

	public function testNonStrictModeTypeCasting(): void {
		$this->assertQuery(
			"CREATE TABLE t (
				col_int INT,
				col_float FLOAT,
				col_double DOUBLE,
				col_decimal DECIMAL,
				col_char CHAR(255),
				col_varchar VARCHAR(255),
				col_text TEXT,
				col_bool BOOL,
				col_bit BIT,
				col_binary BINARY(255),
				col_varbinary VARBINARY(255),
				col_blob BLOB,
				col_date DATE,
				col_time TIME,
				col_datetime DATETIME,
				col_timestamp TIMESTAMP,
				col_year YEAR,
				col_enum ENUM('a', 'b', 'c'),
				col_set SET('a', 'b', 'c'),
				col_json JSON
			)"
		);

		// Set non-strict mode.
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// INSERT.
		$this->assertQuery(
			"INSERT INTO t VALUES ('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '')"
		);

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );
		$this->assertSame( '0', $result[0]->col_int );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[0]->col_float );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[0]->col_double );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[0]->col_decimal );
		$this->assertSame( '', $result[0]->col_char );
		$this->assertSame( '', $result[0]->col_varchar );
		$this->assertSame( '', $result[0]->col_text );
		$this->assertSame( '0', $result[0]->col_bool );
		$this->assertSame( '0', $result[0]->col_bit );
		$this->assertSame( PHP_VERSION_ID < 80100 ? null : '', $result[0]->col_binary );
		$this->assertSame( PHP_VERSION_ID < 80100 ? null : '', $result[0]->col_varbinary );
		$this->assertSame( PHP_VERSION_ID < 80100 ? null : '', $result[0]->col_blob );
		$this->assertSame( '0000-00-00', $result[0]->col_date );
		$this->assertSame( '00:00:00', $result[0]->col_time );
		$this->assertSame( '0000-00-00 00:00:00', $result[0]->col_datetime );
		$this->assertSame( '0000-00-00 00:00:00', $result[0]->col_timestamp );
		$this->assertSame( '0000', $result[0]->col_year );
		$this->assertSame( '', $result[0]->col_enum );
		$this->assertSame( '', $result[0]->col_set );
		$this->assertSame( '', $result[0]->col_json ); // TODO: This should not be allowed.

		// UPDATE.
		$this->assertQuery(
			"UPDATE t SET
				col_int = '',
				col_float = '',
				col_double = '',
				col_decimal = '',
				col_char = '',
				col_varchar = '',
				col_text = '',
				col_bool = '',
				col_bit = '',
				col_binary = '',
				col_varbinary = '',
				col_blob = '',
				col_date = '',
				col_time = '',
				col_datetime = '',
				col_timestamp = '',
				col_year = '',
				col_enum = '',
				col_set = '',
				col_json = ''
			"
		);

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );
		$this->assertSame( '0', $result[0]->col_int );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[0]->col_float );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[0]->col_double );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[0]->col_decimal );
		$this->assertSame( '', $result[0]->col_char );
		$this->assertSame( '', $result[0]->col_varchar );
		$this->assertSame( '', $result[0]->col_text );
		$this->assertSame( '0', $result[0]->col_bool );
		$this->assertSame( '0', $result[0]->col_bit );
		$this->assertSame( PHP_VERSION_ID < 80100 ? null : '', $result[0]->col_binary );
		$this->assertSame( PHP_VERSION_ID < 80100 ? null : '', $result[0]->col_varbinary );
		$this->assertSame( PHP_VERSION_ID < 80100 ? null : '', $result[0]->col_blob );
		$this->assertSame( '0000-00-00', $result[0]->col_date );
		$this->assertSame( '00:00:00', $result[0]->col_time );
		$this->assertSame( '0000-00-00 00:00:00', $result[0]->col_datetime );
		$this->assertSame( '0000-00-00 00:00:00', $result[0]->col_timestamp );
		$this->assertSame( '0000', $result[0]->col_year );
		$this->assertSame( '', $result[0]->col_enum );
		$this->assertSame( '', $result[0]->col_set );
		$this->assertSame( '', $result[0]->col_json ); // TODO: This should not be allowed.
	}

	public function testSessionSqlModes(): void {
		// Syntax: "sql_mode" ("@@sql_mode" for SELECT)
		$this->assertQuery( 'SET sql_mode = "ERROR_FOR_DIVISION_BY_ZERO"' );
		$result = $this->assertQuery( 'SELECT @@sql_mode' );
		$this->assertSame( 'ERROR_FOR_DIVISION_BY_ZERO', $result[0]->{'@@sql_mode'} );

		// Syntax: "@@sql_mode"
		$this->assertQuery( 'SET @@sql_mode = "NO_ENGINE_SUBSTITUTION"' );
		$result = $this->assertQuery( 'SELECT @@sql_mode' );
		$this->assertSame( 'NO_ENGINE_SUBSTITUTION', $result[0]->{'@@sql_mode'} );

		// Syntax: "SESSION sql_mode" ("@@sql_mode" for SELECT)
		$this->assertQuery( 'SET SESSION sql_mode = "NO_ZERO_DATE"' );
		$result = $this->assertQuery( 'SELECT @@sql_mode' );
		$this->assertSame( 'NO_ZERO_DATE', $result[0]->{'@@sql_mode'} );

		// Syntax: "@@SESSION.sql_mode"
		$this->assertQuery( 'SET @@SESSION.sql_mode = "NO_ZERO_IN_DATE"' );
		$result = $this->assertQuery( 'SELECT @@SESSION.sql_mode' );
		$this->assertSame( 'NO_ZERO_IN_DATE', $result[0]->{'@@SESSION.sql_mode'} );

		// Mixed case
		$this->assertQuery( 'SET @@session.SQL_mode = "only_full_group_by"' );
		$result = $this->assertQuery( 'SELECT @@session.SQL_mode' );
		$this->assertSame( 'ONLY_FULL_GROUP_BY', $result[0]->{'@@session.SQL_mode'} );
	}

	public function testMultiQueryNotSupported(): void {
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( 'Multi-query is not supported.' );
		$this->assertQuery( 'SELECT 1; SELECT 2' );
	}

	public function testCreateTableDuplicateTableName(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (id INT)' );
			$this->assertQuery( 'CREATE TABLE t (id INT)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42S01]: Base table or view already exists: 1050 Table 't' already exists", $exception->getMessage() );
		$this->assertSame( '42S01', $exception->getCode() );
	}

	public function testCreateTableDuplicateColumnName(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (col INT, col INT)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'col'", $exception->getMessage() );
		$this->assertSame( '42S21', $exception->getCode() );
	}

	public function testCreateTableDuplicateKeyName(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (id1 INT, id2 INT, INDEX idx (id1), INDEX idx (id2))' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'idx'", $exception->getMessage() );
		$this->assertSame( '42S21', $exception->getCode() );
	}

	public function testCreateTableDuplicateKeyNameWithUnique(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (id1 INT, id2 INT, INDEX idx (id1), UNIQUE idx (id2))' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'idx'", $exception->getMessage() );
		$this->assertSame( '42S21', $exception->getCode() );
	}

	public function testCreateTableDuplicateKeyNameWithPrimaryKey(): void {
		$this->assertQuery( 'CREATE TABLE t (id1 INT, id2 INT, PRIMARY KEY idx (id1), INDEX idx (id2))' );
		// No exception. In MySQL, PRIMARY KEY names are ignored.
	}

	public function testAlterTableDuplicateColumnName(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (col INT)' );
			$this->assertQuery( 'ALTER TABLE t ADD COLUMN col INT' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'col'", $exception->getMessage() );
		$this->assertSame( '42S21', $exception->getCode() );
	}

	public function testAlterTableDuplicateColumnNameWithMultipleOperations(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (id INT)' );
			$this->assertQuery( 'ALTER TABLE t ADD COLUMN col INT, ADD COLUMN col INT' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'col'", $exception->getMessage() );
		$this->assertSame( '42S21', $exception->getCode() );
	}

	public function testAlterTableDuplicateKeyName(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (id INT, INDEX idx (id))' );
			$this->assertQuery( 'ALTER TABLE t ADD INDEX idx (id)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'idx'", $exception->getMessage() );
		$this->assertSame( '42S21', $exception->getCode() );
	}

	public function testAlterTableDuplicateKeyNameWithMultipleOperations(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (id INT)' );
			$this->assertQuery( 'ALTER TABLE t ADD INDEX idx (id), ADD INDEX idx (id)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'idx'", $exception->getMessage() );
		$this->assertSame( '42S21', $exception->getCode() );
	}

	public function testAlterTableDuplicateKeyNameWithUnique(): void {
		$exception = null;
		try {
			$this->assertQuery( 'CREATE TABLE t (id INT, INDEX idx (id))' );
			$this->assertQuery( 'ALTER TABLE t ADD UNIQUE idx (id)' );
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( WP_SQLite_Driver_Exception::class, $exception );
		$this->assertSame( "SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'idx'", $exception->getMessage() );
		$this->assertSame( '42S21', $exception->getCode() );
	}

	public function testConstraintName(): void {
		$this->assertQuery(
			'CREATE TABLE t ( id INT, CONSTRAINT cst_id UNIQUE (id) )'
		);

		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'cst_id', $result[0]->Key_name );
	}

	public function testIndexNamePrecedesConstraintName(): void {
		$this->assertQuery(
			'CREATE TABLE t ( id INT, CONSTRAINT cst_id UNIQUE idx_id (id) )'
		);

		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'idx_id', $result[0]->Key_name );

		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertCount( 1, $result );
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int DEFAULT NULL,',
					'  UNIQUE KEY `idx_id` (`id`)',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);
	}

	public function testImplicitIndexNames(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				id INT UNIQUE,
				id_2 INT UNIQUE,
				value INT,
				UNIQUE (id),
				UNIQUE (id, value)
			)'
		);

		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 5, $result );

		$this->assertSame( 'id', $result[0]->Key_name );
		$this->assertSame( 'id', $result[0]->Column_name );

		$this->assertSame( 'id_2', $result[1]->Key_name );
		$this->assertSame( 'id_2', $result[1]->Column_name );

		$this->assertSame( 'id_3', $result[2]->Key_name );
		$this->assertSame( 'id', $result[2]->Column_name );

		$this->assertSame( 'id_4', $result[3]->Key_name );
		$this->assertSame( 'id', $result[3]->Column_name );

		$this->assertSame( 'id_4', $result[4]->Key_name );
		$this->assertSame( 'value', $result[4]->Column_name );
	}

	public function testValidDuplicateConstraintNames(): void {
		$this->assertQuery(
			'CREATE TABLE t (
			id INT,
			CONSTRAINT cid PRIMARY KEY (id),
			CONSTRAINT cid UNIQUE (id)
			-- Not yet supported: CONSTRAINT cid CHECK (id > 0),
			-- Not yet supported: CONSTRAINT cid FOREIGN KEY (id) REFERENCES t (id)
		)'
		);

		// No exception. This table definition is valid in MySQL.
		// Constraint names must be unique per constraint type, not per table.
	}

	public function testMultipleTablesWithSameConstraintNames(): void {
		$this->assertQuery(
			'CREATE TABLE t1 (
				id INT,
				CONSTRAINT c_primary PRIMARY KEY (id),
				CONSTRAINT c_unique UNIQUE (id)
			)'
		);

		$this->assertQuery(
			'CREATE TABLE t2 (
				id INT,
				CONSTRAINT c_primary PRIMARY KEY (id),
				CONSTRAINT c_unique UNIQUE (id)
			)'
		);

		// No exception. This is valid in MySQL.
		// Primary and unique key names must be unique per table, not per schema.
	}

	public function testNoBackslashEscapesSqlMode(): void {
		$backslash = chr( 92 );

		$query = "SELECT
			''''                       AS value_1,
			'{$backslash}\"'           AS value_2,
			'{$backslash}0'            AS value_3,
			'{$backslash}n'            AS value_4,
			'{$backslash}r'            AS value_5,
			'{$backslash}t'            AS value_6,
			'{$backslash}b'            AS value_7,
			'{$backslash}{$backslash}' AS value_8,
			'🙂'                        AS value_9,
			'{$backslash}🙂'            AS value_10,
			'{$backslash}%'            AS value_11,
			'{$backslash}_'            AS value_12
		";

		// With NO_BACKSLASH_ESCAPES disabled:
		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$result = $this->assertQuery( $query );
		$this->assertSame( chr( 39 ), $result[0]->value_1 ); // single quote
		$this->assertSame( chr( 34 ), $result[0]->value_2 ); // double quote
		$this->assertSame( chr( 0 ), $result[0]->value_3 );  // ASCII NULL
		$this->assertSame( chr( 10 ), $result[0]->value_4 ); // newline
		$this->assertSame( chr( 13 ), $result[0]->value_5 ); // carriage return
		$this->assertSame( chr( 9 ), $result[0]->value_6 );  // tab
		$this->assertSame( chr( 8 ), $result[0]->value_7 );  // backspace
		$this->assertSame( chr( 92 ), $result[0]->value_8 ); // backslash
		$this->assertSame( '🙂', $result[0]->value_9 );
		$this->assertSame( '🙂', $result[0]->value_10 );

		// Characters "%" and "_" follow special escaping rules. Escape sequences
		// "\%" and "\_" preserve the backslash so it can be used in some contexts.
		$this->assertSame( $backslash . '%', $result[0]->value_11 );
		$this->assertSame( $backslash . '_', $result[0]->value_12 );

		// With NO_BACKSLASH_ESCAPES enabled:
		$this->assertQuery( "SET SESSION sql_mode = 'NO_BACKSLASH_ESCAPES'" );
		$result = $this->assertQuery( $query );
		$this->assertSame( "'", $result[0]->value_1 );
		$this->assertSame( $backslash . '"', $result[0]->value_2 );
		$this->assertSame( $backslash . '0', $result[0]->value_3 );
		$this->assertSame( $backslash . 'n', $result[0]->value_4 );
		$this->assertSame( $backslash . 'r', $result[0]->value_5 );
		$this->assertSame( $backslash . 't', $result[0]->value_6 );
		$this->assertSame( $backslash . 'b', $result[0]->value_7 );
		$this->assertSame( $backslash . $backslash, $result[0]->value_8 );
		$this->assertSame( '🙂', $result[0]->value_9 );
		$this->assertSame( $backslash . '🙂', $result[0]->value_10 );
		$this->assertSame( $backslash . '%', $result[0]->value_11 );
		$this->assertSame( $backslash . '_', $result[0]->value_12 );
	}

	public function testNoBackslashEscapesSqlModeWithPatternMatching(): void {
		$backslash = chr( 92 );

		$this->assertQuery( 'CREATE TABLE t (id INT PRIMARY KEY AUTO_INCREMENT, value TEXT)' );
		$this->assertQuery( "INSERT INTO t (value) VALUES ('abc')" );
		$this->assertQuery( "INSERT INTO t (value) VALUES ('abc_')" );
		$this->assertQuery( "INSERT INTO t (value) VALUES ('abc%')" );
		$this->assertQuery( "INSERT INTO t (value) VALUES ('abc{$backslash}{$backslash}x')" ); // abc\x

		/*
		 * 1. With NO_BACKSLASH_ESCAPES disabled:
		 *
		 * Backslashes serve as special escape characters on two levels:
		 *
		 *   1. In MySQL string literals.
		 *   2. In LIKE patterns.
		 *
		 * Additionally, "\_" and "\%" sequences preserve the backslash in MySQL
		 * string literals, making them equivalent to "\\_" and "\\%" sequences.
		 *
		 * Here's what that does to some escape sequences:
		 *
		 *   "\_"
		 *      1) String literal resolves to:   "\_" sequence
		 *      2) Pattern matching resolves to: "_" character
		 *
		 *   "\\_"
		 *      1) String literal resolves to:   "\_" sequence
		 *      2) Pattern matching resolves to: "_" character
		 *
		 *   "\\\_"
		 *      1) String literal resolves to:   "\\_" sequence
		 *      2) Pattern matching resolves to: "\" character + "_" wildcard
		 *
		 *   "\\\\_"
		 *      1) String literal resolves to:   "\\_" sequence
		 *      2) Pattern matching resolves to: "\" character + "_" wildcard
		 *
		 * The same rules applies to the "%" character.
		 */
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// A "_" = a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc_' ORDER BY id" );
		$this->assertCount( 2, $result );
		$this->assertSame( 'abc_', $result[0]->value );
		$this->assertSame( 'abc%', $result[1]->value );

		// A "\_" sequence = the "_" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}_'" );
		$this->assertCount( 1, $result );
		$this->assertSame( 'abc_', $result[0]->value );

		// A "\\_" sequence = the "_" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}_'" );
		$this->assertCount( 1, $result );
		$this->assertSame( 'abc_', $result[0]->value );

		// A "\\\_" sequence = the "\" character and a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}{$backslash}_'" );
		$this->assertCount( 1, $result );
		$this->assertSame( "abc{$backslash}x", $result[0]->value );

		// A "\\\\_" sequence = the "\" character and a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}{$backslash}{$backslash}_'" );
		$this->assertCount( 1, $result );
		$this->assertSame( "abc{$backslash}x", $result[0]->value );

		// A "\\\\\_" sequence = the "\" character and the "_" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}{$backslash}{$backslash}{$backslash}_'" );
		$this->assertCount( 0, $result );

		// A "%" = a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc%' ORDER BY id" );
		$this->assertCount( 4, $result );
		$this->assertSame( 'abc', $result[0]->value );
		$this->assertSame( 'abc_', $result[1]->value );
		$this->assertSame( 'abc%', $result[2]->value );
		$this->assertSame( "abc{$backslash}x", $result[3]->value );

		// A "\%" sequence = the "%" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}%'" );
		$this->assertCount( 1, $result );
		$this->assertSame( 'abc%', $result[0]->value );

		// A "\\%" sequence = the "%" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}%'" );
		$this->assertCount( 1, $result );
		$this->assertSame( 'abc%', $result[0]->value );

		// A "\\\%" sequence = the "\" character and a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}{$backslash}%'" );
		$this->assertCount( 1, $result );
		$this->assertSame( "abc{$backslash}x", $result[0]->value );

		// A "\\\\%" sequence = the "\" character and a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}{$backslash}{$backslash}%'" );
		$this->assertCount( 1, $result );
		$this->assertSame( "abc{$backslash}x", $result[0]->value );

		// A "\\\\\%" sequence = the "\" character and the "%" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}{$backslash}{$backslash}{$backslash}%'" );
		$this->assertCount( 0, $result );

		// A "\x" sequence = the "x" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}x'" );
		$this->assertCount( 0, $result );

		// A "\\x" sequence = the "x" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}x'" );
		$this->assertCount( 0, $result );

		// A "\\\x" sequence = the "x" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}{$backslash}x'" );
		$this->assertCount( 0, $result );

		// A "\\\\x" sequence = the "\" character and the "x" character:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}{$backslash}{$backslash}x'" );
		$this->assertCount( 1, $result );
		$this->assertSame( "abc{$backslash}x", $result[0]->value );

		/*
		 * 2. With NO_BACKSLASH_ESCAPES enabled:
		 *
		 * Backslashes don't serve as special escape characters at all:
		 *
		 *   1. No special meaning in MySQL string literals.
		 *   2. No special meaning in LIKE patterns.
		 *      This can be overriden using the "ESCAPE ..." clause of the LIKE
		 *      expression. This is not implemented in the SQLite driver yet.
		 */
		$this->assertQuery( "SET SESSION sql_mode = 'NO_BACKSLASH_ESCAPES'" );

		// A "_" = a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc_' ORDER BY id" );
		$this->assertCount( 2, $result );
		$this->assertSame( 'abc_', $result[0]->value );
		$this->assertSame( 'abc%', $result[1]->value );

		// A "\_" sequence = the "\" character and a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}_'" );
		$this->assertCount( 1, $result );
		$this->assertSame( "abc{$backslash}x", $result[0]->value );

		// A "\\_" sequence = two "\" characters and a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}_'" );
		$this->assertCount( 0, $result );

		// A "%" = a wildcard:
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc%' ORDER BY id" );
		$this->assertCount( 4, $result );
		$this->assertSame( 'abc', $result[0]->value );
		$this->assertSame( 'abc_', $result[1]->value );
		$this->assertSame( 'abc%', $result[2]->value );
		$this->assertSame( "abc{$backslash}x", $result[3]->value );

		// A "\%" sequence = the "\" character and a wildcard.
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}%'" );
		$this->assertCount( 1, $result );
		$this->assertSame( "abc{$backslash}x", $result[0]->value );

		// A "\\%" sequence = two "\" characters and a wildcard.
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}%'" );
		$this->assertCount( 0, $result );

		// A "\x" sequence = the "\" and the "x" character.
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}x'" );
		$this->assertCount( 1, $result );
		$this->assertSame( "abc{$backslash}x", $result[0]->value );

		// A "\\x" sequence = two "\" characters and the "x" character.
		$result = $this->assertQuery( "SELECT value FROM t WHERE value LIKE 'abc{$backslash}{$backslash}x'" );
		$this->assertCount( 0, $result );
	}

	public function testQuoteMysqlUtf8StringLiteral(): void {
		// WP_SQLite_Driver::quote_mysql_utf8_string_literal() is a private method.
		// Let's use a closure bound to the driver instance to access it for tests.
		$quote = Closure::bind(
			function ( string $utf8_literal ) {
				return $this->quote_mysql_utf8_string_literal( $utf8_literal );
			},
			$this->engine,
			WP_SQLite_Driver::class
		);

		$backslash = chr( 92 );

		// The formatted string must be enclosed in single quotes.
		$this->assertSame( "'abc'", $quote( 'abc' ) );

		// Single quotes must be escaped by being doubled.
		$this->assertSame( "''''", $quote( chr( 39 ) ) );
		$this->assertSame( "'abc''xyz'", $quote( "abc'xyz" ) );

		// Backslashes must be escaped by being doubled.
		$this->assertSame( "'{$backslash}{$backslash}'", $quote( $backslash ) );
		$this->assertSame( "'abc{$backslash}{$backslash}xyz'", $quote( "abc{$backslash}xyz" ) );

		// ASCII NULL, newline, and carriage return must be escaped with a backslash.
		$this->assertSame( "'{$backslash}0'", $quote( chr( 0 ) ) );  // ASCII NULL (\0)
		$this->assertSame( "'{$backslash}n'", $quote( chr( 10 ) ) ); // newline (\n)
		$this->assertSame( "'{$backslash}r'", $quote( chr( 13 ) ) ); // carriage return (\r)

		// Other valid UTF-8 characters must be preserved.
		$this->assertSame( "'" . chr( 34 ) . "'", $quote( chr( 34 ) ) ); // double quote
		$this->assertSame( "'" . chr( 96 ) . "'", $quote( chr( 96 ) ) ); // backtick
		$this->assertSame( "'" . chr( 8 ) . "'", $quote( chr( 8 ) ) );   // backspace
		$this->assertSame( "'" . chr( 9 ) . "'", $quote( chr( 9 ) ) );   // tab
		$this->assertSame( "'" . chr( 26 ) . "'", $quote( chr( 26 ) ) ); // Control+Z
		$this->assertSame( "'🙂'", $quote( '🙂' ) );
		$this->assertSame( "'👪'", $quote( '👪' ) );
		$this->assertSame( "'Ʈềʂᴛӏń𝒈 𝙨𝑜ɱê Ū𝐓Ϝ-8 𝒄𝒽ȃᵲ𝛼çṱ𝘦ᴦ𐑈.'", $quote( 'Ʈềʂᴛӏń𝒈 𝙨𝑜ɱê Ū𝐓Ϝ-8 𝒄𝒽ȃᵲ𝛼çṱ𝘦ᴦ𐑈.' ) );

		// Invalid UTF-8: An incomplete 2-byte sequence is left unchanged.
		$this->assertSame(
			"'" . chr( 0xC0 ) . "'",
			$quote( chr( 0xC0 ) )
		);

		// Invalid UTF-8: A surrogate pair is left unchanged.
		$this->assertSame(
			"'" . chr( 0xED ) . chr( 0xA0 ) . chr( 0x80 ) . "'",
			$quote( chr( 0xED ) . chr( 0xA0 ) . chr( 0x80 ) )
		);

		// Invalid UTF-8: Overlong encoding of ASCII NULL is left unchanged.
		$this->assertSame(
			"'" . chr( 0xE0 ) . chr( 0x80 ) . chr( 0x80 ) . "'",
			$quote( chr( 0xE0 ) . chr( 0x80 ) . chr( 0x80 ) )
		);

		// Invalid UTF-8: A 2-byte sequence prefix, followed by an ASCII NULL.
		// The NULL is escaped, leaving the C0 prefix an incomplete sequence.
		$this->assertSame(
			"'" . chr( 0xC0 ) . "{$backslash}0" . "'",
			$quote( chr( 0xC0 ) . chr( 0 ) )
		);

		// Invalid UTF-8: A 2-byte sequence prefix, followed by a single quote.
		// The single quote is escaped, leaving the C0 prefix an incomplete sequence.
		$this->assertSame(
			"'" . chr( 0xC0 ) . chr( 39 ) . chr( 39 ) . "'",
			$quote( chr( 0xC0 ) . chr( 39 ) )
		);
	}

	public function testColumnNamesAreNotCaseSensitive(): void {
		$this->assertQuery( 'CREATE TABLE t (value TEXT)' );

		// INSERT.
		$this->assertQuery( "INSERT INTO t (value) VALUES ('one')" );
		$this->assertQuery( "INSERT INTO t (VaLuE) VALUES ('two')" );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 2, $result );

		// SELECT.
		$result = $this->assertQuery( "SELECT * FROM t WHERE value = 'one'" );
		$this->assertCount( 1, $result );
		$this->assertSame( 'one', $result[0]->value );

		$result = $this->assertQuery( "SELECT * FROM t WHERE VaLuE = 'two'" );
		$this->assertCount( 1, $result );
		$this->assertSame( 'two', $result[0]->value );

		// UPDATE.
		$this->assertQuery( "UPDATE t SET value = 'one-updated' WHERE value = 'one'" );
		$result = $this->assertQuery( "SELECT * FROM t WHERE value = 'one-updated'" );
		$this->assertCount( 1, $result );
		$this->assertSame( 'one-updated', $result[0]->value );

		$this->assertQuery( "UPDATE t SET VALUE = 'two-updated' WHERE VaLuE = 'two'" );
		$result = $this->assertQuery( "SELECT * FROM t WHERE value = 'two-updated'" );
		$this->assertCount( 1, $result );
		$this->assertSame( 'two-updated', $result[0]->value );

		// DELETE.
		$this->assertQuery( "DELETE FROM t WHERE value = 'one-updated'" );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'two-updated', $result[0]->value );

		$this->assertQuery( "DELETE FROM t WHERE VaLuE = 'two-updated'" );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 0, $result );

		// ALTER TABLE.
		$this->assertQuery( 'ALTER TABLE t CHANGE COLUMN VaLuE value_changed TEXT' );
		$this->assertQuery( 'ALTER TABLE t CHANGE COLUMN value_changed value TEXT' );

		// ADD COLUMN.
		$this->assertQuery( 'ALTER TABLE t ADD COLUMN added TEXT' );
		$exception = null;
		try {
			$this->assertQuery( 'ALTER TABLE t ADD COLUMN AdDeD TEXT' );
		} catch ( Throwable $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertStringContainsString(
			"Column already exists: 1060 Duplicate column name 'AdDeD'",
			$exception->getMessage()
		);

		// DROP COLUMN.
		$this->assertQuery( 'ALTER TABLE t DROP COLUMN added' );
		$result = $this->assertQuery( 'SHOW COLUMNS FROM t' );
		$this->assertCount( 1, $result );
		$this->assertSame( 'value', $result[0]->Field );
	}

	public function testAliasesMustBeAscii(): void {
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( 'The SQLite driver only supports ASCII characters in identifiers.' );
		$this->assertQuery( 'SELECT 123 AS `ńôñ-ášçíì`' );
	}

	public function testTableNamesMustBeAscii(): void {
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( 'The SQLite driver only supports ASCII characters in identifiers.' );
		$this->assertQuery( 'CREATE TABLE `ńôñ-ášçíì` (id INT)' );
	}

	public function testColumnNamesMustBeAscii(): void {
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( 'The SQLite driver only supports ASCII characters in identifiers.' );
		$this->assertQuery( 'CREATE TABLE t (`ńôñ-ášçíì` INT)' );
	}

	public function testCreateIndex(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, value TEXT)' );
		$this->assertQuery( 'CREATE INDEX idx_value ON t (value(16))' );

		// Verify that the index was saved in the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			(object) array(
				'Table'         => 't',
				'Non_unique'    => '1',
				'Key_name'      => 'idx_value',
				'Seq_in_index'  => '1',
				'Column_name'   => 'value',
				'Collation'     => 'A',
				'Cardinality'   => '0',
				'Sub_part'      => '16',
				'Packed'        => null,
				'Null'          => 'YES',
				'Index_type'    => 'BTREE',
				'Comment'       => '',
				'Index_comment' => '',
				'Visible'       => 'YES',
				'Expression'    => null,
			),
			$result[0]
		);

		// Verify that the index exists in the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => 't__idx_value',
				'unique'  => '0',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	public function testCreateUniqueIndex(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, value TEXT)' );
		$this->assertQuery( 'CREATE UNIQUE INDEX idx_value ON t (value(16))' );

		// Verify that the index was saved in the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			(object) array(
				'Table'         => 't',
				'Non_unique'    => '0',
				'Key_name'      => 'idx_value',
				'Seq_in_index'  => '1',
				'Column_name'   => 'value',
				'Collation'     => 'A',
				'Cardinality'   => '0',
				'Sub_part'      => '16',
				'Packed'        => null,
				'Null'          => 'YES',
				'Index_type'    => 'BTREE',
				'Comment'       => '',
				'Index_comment' => '',
				'Visible'       => 'YES',
				'Expression'    => null,
			),
			$result[0]
		);

		// Verify that the UNIQUE constraint was saved in the information schema.
		$result = $this->assertQuery( 'SELECT * FROM information_schema.table_constraints WHERE table_name = "t"' );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			(object) array(
				'CONSTRAINT_CATALOG' => 'def',
				'CONSTRAINT_SCHEMA'  => 'wp',
				'CONSTRAINT_NAME'    => 'idx_value',
				'TABLE_SCHEMA'       => 'wp',
				'TABLE_NAME'         => 't',
				'CONSTRAINT_TYPE'    => 'UNIQUE',
				'ENFORCED'           => 'YES',
			),
			$result[0]
		);

		// Verify that the index exists in the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => 't__idx_value',
				'unique'  => '1',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	public function testCreateFulltextIndex(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, value TEXT)' );
		$this->assertQuery( 'CREATE FULLTEXT INDEX idx_value ON t (value)' );

		// Verify that the index was saved in the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'FULLTEXT', $result[0]->Index_type );

		// Verify that the index exists in the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => 't__idx_value',
				'unique'  => '0',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	public function testCreateSpatialIndex(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, value POINT NOT NULL)' );
		$this->assertQuery( 'CREATE SPATIAL INDEX idx_value ON t (value)' );

		// Verify that the index was saved in the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'SPATIAL', $result[0]->Index_type );

		// Verify that the index exists in the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => 't__idx_value',
				'unique'  => '0',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	public function testCreateIndexWithOrder(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, value VARCHAR(255))' );
		$this->assertQuery( 'CREATE INDEX idx_value ON t (value DESC)' );

		// Verify that the order was saved in the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'D', $result[0]->Collation );

		// Verify that the order is included in the CREATE TABLE statement.
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertCount( 1, $result );
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int DEFAULT NULL,',
					'  `value` varchar(255) DEFAULT NULL,',
					'  KEY `idx_value` (`value` DESC)',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);

		// Verify that the order is defined in the SQLite index.
		$result = $this->engine
			->execute_sqlite_query( "SELECT * FROM pragma_index_xinfo('t__idx_value') WHERE cid != -1" )
			->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seqno' => '0',
				'cid'   => '1',
				'name'  => 'value',
				'desc'  => '1',
				'coll'  => 'NOCASE',
				'key'   => '1',
			),
			$result[0]
		);
	}

	public function testCreateIndexWithComment(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, value INT)' );
		$this->assertQuery( 'CREATE INDEX idx_value ON t (value) COMMENT "Test comment"' );

		// Verify that the index was saved in the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'Test comment', $result[0]->Index_comment );

		// Verify that the index exists in the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				'seq'     => '0',
				'name'    => 't__idx_value',
				'unique'  => '0',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);
	}

	public function testCreateIndexWithDuplicateName(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, val1 INT, val2 INT)' );
		$this->assertQuery( 'CREATE INDEX idx_value ON t (val1)' );

		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( "1061 Duplicate key name 'idx_value'" );

		$this->assertQuery( 'CREATE INDEX idx_value ON t (val2)' );
	}

	public function testCreateIndexOnNonExistentColumn(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT)' );

		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( "SQLSTATE[42000]: Syntax error or access violation: 1072 Key column 'val' doesn't exist in table" );

		$this->assertQuery( 'CREATE INDEX idx_value ON t (val)' );
	}

	public function testCreateComplexIndex(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				id INT PRIMARY KEY,
				name TEXT,
				score INT,
				created_at DATETIME
			)'
		);

		$this->assertQuery(
			'CREATE UNIQUE INDEX idx_complex
			ON t (score ASC, name(16) DESC, created_at DESC)
			USING BTREE
			COMMENT "Test comment"
			ALGORITHM INPLACE
			LOCK SHARED'
		);

		// Verify that the index was saved in the information schema.
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int NOT NULL,',
					'  `name` text DEFAULT NULL,',
					'  `score` int DEFAULT NULL,',
					'  `created_at` datetime DEFAULT NULL,',
					'  PRIMARY KEY (`id`),',
					"  UNIQUE KEY `idx_complex` (`score`, `name`(16) DESC, `created_at` DESC) COMMENT 'Test comment'",
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);

		// Verify that the index exists in the SQLite database.
		$result = $this->engine
			->execute_sqlite_query( "SELECT * FROM pragma_index_list('t') WHERE origin != 'pk'" )
			->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertSame(
			array(
				'seq'     => '0',
				'name'    => 't__idx_complex',
				'unique'  => '1',
				'origin'  => 'c',
				'partial' => '0',
			),
			$result[0]
		);

		$result = $this->engine
			->execute_sqlite_query( "SELECT * FROM pragma_index_xinfo('t__idx_complex') WHERE cid != -1" )
			->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 3, $result );
		$this->assertEquals(
			array(
				'seqno' => '0',
				'cid'   => '2',
				'name'  => 'score',
				'desc'  => '0',
				'coll'  => 'BINARY',
				'key'   => '1',
			),
			$result[0]
		);
		$this->assertEquals(
			array(
				'seqno' => '1',
				'cid'   => '1',
				'name'  => 'name',
				'desc'  => '1',
				'coll'  => 'NOCASE',
				'key'   => '1',
			),
			$result[1]
		);
		$this->assertEquals(
			array(
				'seqno' => '2',
				'cid'   => '3',
				'name'  => 'created_at',
				'desc'  => '1',
				'coll'  => 'NOCASE',
				'key'   => '1',
			),
			$result[2]
		);
	}

	public function testDropIndex(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT PRIMARY KEY, val_unique INT UNIQUE, val_index INT)' );
		$this->assertQuery( 'CREATE INDEX idx_val_index ON t (val_index)' );

		// Verify that the indexes were saved in the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 3, $result );
		$this->assertEquals( 'PRIMARY', $result[0]->Key_name );
		$this->assertEquals( 'val_unique', $result[1]->Key_name );
		$this->assertEquals( 'idx_val_index', $result[2]->Key_name );

		// Verify that the indexes exist in the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 3, $result );
		$this->assertEquals( 't__idx_val_index', $result[0]['name'] );
		$this->assertEquals( 't__val_unique', $result[1]['name'] );
		$this->assertEquals( 'sqlite_autoindex_t_1', $result[2]['name'] );

		// DROP the explicitly named index.
		$this->assertQuery( 'DROP INDEX idx_val_index ON t' );

		// Verify that the index was removed from the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 2, $result );
		$this->assertEquals( 'PRIMARY', $result[0]->Key_name );
		$this->assertEquals( 'val_unique', $result[1]->Key_name );

		// Verify that the index was removed from the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 2, $result );
		$this->assertEquals( 't__val_unique', $result[0]['name'] );
		$this->assertEquals( 'sqlite_autoindex_t_1', $result[1]['name'] );

		// DROP the UNIQUE index.
		$this->assertQuery( 'DROP INDEX val_unique ON t' );

		// Verify that the index was removed from the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'PRIMARY', $result[0]->Key_name );

		// Verify that the index was removed from the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'sqlite_autoindex_t_1', $result[0]['name'] );

		// DROP the PRIMARY KEY index.
		$this->assertQuery( 'DROP INDEX `PRIMARY` ON t' );

		// Verify that the index was removed from the information schema.
		$result = $this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertCount( 0, $result );

		// Verify that the index was removed from the SQLite database.
		$result = $this->engine->execute_sqlite_query( "PRAGMA index_list('t')" )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 0, $result );
	}

	public function testComplexInformationSchemaQueries(): void {
		$create_table_query = <<<END
CREATE TABLE `wp_users` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) NOT NULL DEFAULT '',
  `user_pass` varchar(255) NOT NULL DEFAULT '',
  `user_nicename` varchar(50) NOT NULL DEFAULT '',
  `user_email` varchar(100) NOT NULL DEFAULT '',
  `user_url` varchar(100) NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) NOT NULL DEFAULT '',
  `user_status` int(11) NOT NULL DEFAULT '0',
  `display_name` varchar(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
END;

		$this->assertQuery( $create_table_query );

		// 1) JOIN multiple information schema tables.
		$result = $this->assertQuery(
			"SELECT
				cols.DATA_TYPE,
				stats.INDEX_NAME,
				stats.COLUMN_NAME
			FROM INFORMATION_SCHEMA.COLUMNS AS cols
			JOIN INFORMATION_SCHEMA.STATISTICS AS stats
				ON cols.TABLE_SCHEMA = stats.TABLE_SCHEMA
				AND cols.TABLE_NAME = stats.TABLE_NAME
				AND cols.COLUMN_NAME = stats.COLUMN_NAME
			WHERE
				cols.TABLE_SCHEMA = 'wp'
				AND cols.TABLE_NAME = 'wp_users'
			ORDER BY INDEX_NAME ASC"
		);

		$this->assertCount( 4, $result );
		$this->assertEquals(
			(object) array(
				'DATA_TYPE'   => 'bigint',
				'INDEX_NAME'  => 'PRIMARY',
				'COLUMN_NAME' => 'ID',
			),
			$result[0]
		);
		$this->assertEquals(
			(object) array(
				'DATA_TYPE'   => 'varchar',
				'INDEX_NAME'  => 'user_email',
				'COLUMN_NAME' => 'user_email',
			),
			$result[1]
		);
		$this->assertEquals(
			(object) array(
				'DATA_TYPE'   => 'varchar',
				'INDEX_NAME'  => 'user_login_key',
				'COLUMN_NAME' => 'user_login',
			),
			$result[2]
		);
		$this->assertEquals(
			(object) array(
				'DATA_TYPE'   => 'varchar',
				'INDEX_NAME'  => 'user_nicename',
				'COLUMN_NAME' => 'user_nicename',
			),
			$result[3]
		);

		// 2) UNION, DISTINCT, and CTEs with information schema tables.
		$result = $this->assertQuery(
			"WITH
				cols AS (
					SELECT COLUMN_NAME AS column_name
					FROM INFORMATION_SCHEMA.COLUMNS
					WHERE TABLE_SCHEMA = 'wp' AND TABLE_NAME = 'wp_users'
				),
				indexes AS (
					SELECT DISTINCT INDEX_NAME AS index_name
					FROM INFORMATION_SCHEMA.STATISTICS
					WHERE TABLE_SCHEMA = 'wp' AND TABLE_NAME = 'wp_users'
				)
			SELECT CONCAT(column_name, ' (column)') AS name
			FROM cols
			UNION ALL
			SELECT CONCAT(index_name, ' (index)') AS name
			FROM indexes
			ORDER BY name"
		);

		$this->assertCount( 14, $result );
		$this->assertEquals( 'ID (column)', $result[0]->name );
		$this->assertEquals( 'PRIMARY (index)', $result[1]->name );
		$this->assertEquals( 'display_name (column)', $result[2]->name );
		$this->assertEquals( 'user_activation_key (column)', $result[3]->name );
		$this->assertEquals( 'user_email (column)', $result[4]->name );
		$this->assertEquals( 'user_email (index)', $result[5]->name );
		$this->assertEquals( 'user_login (column)', $result[6]->name );
		$this->assertEquals( 'user_login_key (index)', $result[7]->name );
		$this->assertEquals( 'user_nicename (column)', $result[8]->name );
		$this->assertEquals( 'user_nicename (index)', $result[9]->name );
		$this->assertEquals( 'user_pass (column)', $result[10]->name );
		$this->assertEquals( 'user_registered (column)', $result[11]->name );
		$this->assertEquals( 'user_status (column)', $result[12]->name );
		$this->assertEquals( 'user_url (column)', $result[13]->name );

		// 3) SHOW CREATE TABLE should preserve all the CREATE TABLE metadata.
		$result = $this->assertQuery( 'SHOW CREATE TABLE wp_users' );
		$this->assertCount( 1, $result );
		$this->assertEquals( $create_table_query, $result[0]->{'Create Table'} );
	}

	public function testDatabaseNameEmpty(): void {
		$pdo_class  = PHP_VERSION_ID >= 80400 ? PDO\SQLite::class : PDO::class;
		$pdo        = new $pdo_class( 'sqlite::memory:' );
		$connection = new WP_SQLite_Connection( array( 'pdo' => $pdo ) );

		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( 'The database name cannot be empty.' );
		new WP_SQLite_Driver( $connection, '' );
	}

	public function testSelectColumnNames(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, name VARCHAR(255))' );
		$this->assertQuery( 'INSERT INTO t (id, name) VALUES (1, "John"), (2, "Jane")' );

		// Columns (no explicit alias).
		$result = $this->assertQuery( 'SELECT id, name FROM t' );
		$this->assertSame( array( 'id', 'name' ), array_keys( (array) $result[0] ) );

		// Columns with an explicit alias.
		$result = $this->assertQuery( 'SELECT id AS alias_id, name AS alias_name FROM t' );
		$this->assertSame( array( 'alias_id', 'alias_name' ), array_keys( (array) $result[0] ) );

		// Expressions (no explicit alias).
		$result = $this->assertQuery( 'SELECT id + 1, (2 + 3) FROM t' );
		$this->assertSame( array( 'id + 1', '(2 + 3)' ), array_keys( (array) $result[0] ) );

		// Expressions with an explicit alias.
		$result = $this->assertQuery( 'SELECT id + 1 AS alias_id, (2 + 3) AS alias_numbers FROM t' );
		$this->assertSame( array( 'alias_id', 'alias_numbers' ), array_keys( (array) $result[0] ) );

		// Function calls (no explicit alias).
		$result = $this->assertQuery( "SELECT CONCAT('a', 'b')" );
		$this->assertSame( array( "CONCAT('a', 'b')" ), array_keys( (array) $result[0] ) );

		// Function calls with an explicit alias.
		$result = $this->assertQuery( "SELECT CONCAT('a', 'b') AS alias_concat" );
		$this->assertSame( array( 'alias_concat' ), array_keys( (array) $result[0] ) );
	}

	public function testSetStatement(): void {
		$this->assertQuery( 'SET NAMES utf8mb4' );
		$this->assertQuery( 'SET CHARSET utf8mb4' );
		$this->assertQuery( 'SET CHARACTER SET utf8mb4' );
	}

	public function testBuiltInSystemVariables(): void {
		$result = $this->assertQuery( 'SELECT @@version' );
		$this->assertSame( '8.0.38', $result[0]->{'@@version'} );

		$result = $this->assertQuery( 'SELECT @@version_comment' );
		$this->assertSame( 'MySQL Community Server - GPL', $result[0]->{'@@version_comment'} );
	}

	public function testSessionSystemVariables(): void {
		$this->assertQuery( "SET character_set_client = 'latin1'" );
		$result = $this->assertQuery( 'SELECT @@character_set_client' );
		$this->assertSame( 'latin1', $result[0]->{'@@character_set_client'} );

		$this->assertQuery( "SET @@character_set_client = 'utf8mb3'" );
		$result = $this->assertQuery( 'SELECT @@character_set_client' );
		$this->assertSame( 'utf8mb3', $result[0]->{'@@character_set_client'} );

		$this->assertQuery( "SET @@session.character_set_client = 'utf8mb4'" );
		$result = $this->assertQuery( 'SELECT @@session.character_set_client' );
		$this->assertSame( 'utf8mb4', $result[0]->{'@@session.character_set_client'} );
	}

	public function testSystemVariablesWithKeywords(): void {
		$this->assertQuery( 'SET default_storage_engine = InnoDB' );
		$result = $this->assertQuery( 'SELECT @@default_storage_engine' );
		$this->assertSame( 'InnoDB', $result[0]->{'@@default_storage_engine'} );

		$this->assertQuery( 'SET default_collation_for_utf8mb4 = utf8mb4_0900_ai_ci' );
		$result = $this->assertQuery( 'SELECT @@default_collation_for_utf8mb4' );
		$this->assertSame( 'utf8mb4_0900_ai_ci', $result[0]->{'@@default_collation_for_utf8mb4'} );

		$this->assertQuery( 'SET resultset_metadata = FULL' );
		$result = $this->assertQuery( 'SELECT @@resultset_metadata' );
		$this->assertSame( 'FULL', $result[0]->{'@@resultset_metadata'} );

		$this->assertQuery( 'SET session_track_gtids = OWN_GTID' );
		$result = $this->assertQuery( 'SELECT @@session_track_gtids' );
		$this->assertSame( 'OWN_GTID', $result[0]->{'@@session_track_gtids'} );

		$this->assertQuery( 'SET session_track_transaction_info = STATE' );
		$result = $this->assertQuery( 'SELECT @@session_track_transaction_info' );
		$this->assertSame( 'STATE', $result[0]->{'@@session_track_transaction_info'} );

		$this->assertQuery( 'SET transaction_isolation = SERIALIZABLE' );
		$result = $this->assertQuery( 'SELECT @@transaction_isolation' );
		$this->assertSame( 'SERIALIZABLE', $result[0]->{'@@transaction_isolation'} );

		$this->assertQuery( 'SET use_secondary_engine = FORCED' );
		$result = $this->assertQuery( 'SELECT @@use_secondary_engine' );
		$this->assertSame( 'FORCED', $result[0]->{'@@use_secondary_engine'} );
	}

	public function testSystemVariablesWithBooleanValues(): void {
		$this->assertQuery( 'SET autocommit = ON, big_tables = OFF' );
		$result = $this->assertQuery( 'SELECT @@autocommit, @@big_tables' );
		$this->assertSame( '1', $result[0]->{'@@autocommit'} );
		$this->assertSame( '0', $result[0]->{'@@big_tables'} );

		$this->assertQuery( 'SET autocommit = on, big_tables = off' );
		$result = $this->assertQuery( 'SELECT @@autocommit, @@big_tables' );
		$this->assertSame( '1', $result[0]->{'@@autocommit'} );
		$this->assertSame( '0', $result[0]->{'@@big_tables'} );

		$this->assertQuery( "SET autocommit = 'ON', big_tables = 'OFF'" );
		$result = $this->assertQuery( 'SELECT @@autocommit, @@big_tables' );
		$this->assertSame( '1', $result[0]->{'@@autocommit'} );
		$this->assertSame( '0', $result[0]->{'@@big_tables'} );

		$this->assertQuery( "SET autocommit = 'on', big_tables = 'off'" );
		$result = $this->assertQuery( 'SELECT @@autocommit, @@big_tables' );
		$this->assertSame( '1', $result[0]->{'@@autocommit'} );
		$this->assertSame( '0', $result[0]->{'@@big_tables'} );

		$this->assertQuery( 'SET autocommit = TRUE, big_tables = FALSE' );
		$result = $this->assertQuery( 'SELECT @@autocommit, @@big_tables' );
		$this->assertSame( '1', $result[0]->{'@@autocommit'} );
		$this->assertSame( '0', $result[0]->{'@@big_tables'} );

		$this->assertQuery( 'SET autocommit = true, big_tables = false' );
		$result = $this->assertQuery( 'SELECT @@autocommit, @@big_tables' );
		$this->assertSame( '1', $result[0]->{'@@autocommit'} );
		$this->assertSame( '0', $result[0]->{'@@big_tables'} );

		$this->assertQuery( 'SET autocommit = 1, big_tables = 0' );
		$result = $this->assertQuery( 'SELECT @@autocommit, @@big_tables' );
		$this->assertSame( '1', $result[0]->{'@@autocommit'} );
		$this->assertSame( '0', $result[0]->{'@@big_tables'} );
	}

	public function testSystemVariablesWithOnOffValues(): void {
		$this->assertQuery( 'SET autocommit = ON' );
		$result = $this->assertQuery( 'SELECT @@autocommit' );
		$this->assertSame( '1', $result[0]->{'@@autocommit'} );

		$this->assertQuery( 'SET big_tables = OFF' );
		$result = $this->assertQuery( 'SELECT @@big_tables' );
		$this->assertSame( '0', $result[0]->{'@@big_tables'} );

		$this->assertQuery( 'SET end_markers_in_json = ON' );
		$result = $this->assertQuery( 'SELECT @@end_markers_in_json' );
		$this->assertSame( '1', $result[0]->{'@@end_markers_in_json'} );

		$this->assertQuery( 'SET explicit_defaults_for_timestamp = OFF' );
		$result = $this->assertQuery( 'SELECT @@explicit_defaults_for_timestamp' );
		$this->assertSame( '0', $result[0]->{'@@explicit_defaults_for_timestamp'} );

		$this->assertQuery( 'SET keep_files_on_create = ON' );
		$result = $this->assertQuery( 'SELECT @@keep_files_on_create' );
		$this->assertSame( '1', $result[0]->{'@@keep_files_on_create'} );

		$this->assertQuery( 'SET old_alter_table = OFF' );
		$result = $this->assertQuery( 'SELECT @@old_alter_table' );
		$this->assertSame( '0', $result[0]->{'@@old_alter_table'} );

		$this->assertQuery( 'SET print_identified_with_as_hex = ON' );
		$result = $this->assertQuery( 'SELECT @@print_identified_with_as_hex' );
		$this->assertSame( '1', $result[0]->{'@@print_identified_with_as_hex'} );

		$this->assertQuery( 'SET require_row_format = OFF' );
		$result = $this->assertQuery( 'SELECT @@require_row_format' );
		$this->assertSame( '0', $result[0]->{'@@require_row_format'} );

		$this->assertQuery( 'SET select_into_disk_sync = ON' );
		$result = $this->assertQuery( 'SELECT @@select_into_disk_sync' );
		$this->assertSame( '1', $result[0]->{'@@select_into_disk_sync'} );

		$this->assertQuery( 'SET session_track_gtids = OFF' );
		$result = $this->assertQuery( 'SELECT @@session_track_gtids' );
		// @TODO: For session_track_gtids, the value should be OFF, not 0.
		//$this->assertSame( 'OFF', $result[0]->{'@@session_track_gtids'} );

		$this->assertQuery( 'SET session_track_schema = ON' );
		$result = $this->assertQuery( 'SELECT @@session_track_schema' );
		$this->assertSame( '1', $result[0]->{'@@session_track_schema'} );

		$this->assertQuery( 'SET session_track_state_change = OFF' );
		$result = $this->assertQuery( 'SELECT @@session_track_state_change' );
		$this->assertSame( '0', $result[0]->{'@@session_track_state_change'} );

		$this->assertQuery( 'SET session_track_transaction_info = OFF' );
		$result = $this->assertQuery( 'SELECT @@session_track_transaction_info' );
		// @TODO: For session_track_transaction_info, the value should be OFF, not 0.
		//$this->assertSame( 'OFF', $result[0]->{'@@session_track_transaction_info'} );

		$this->assertQuery( 'SET show_create_table_skip_secondary_engine = ON' );
		$result = $this->assertQuery( 'SELECT @@show_create_table_skip_secondary_engine' );
		$this->assertSame( '1', $result[0]->{'@@show_create_table_skip_secondary_engine'} );

		$this->assertQuery( 'SET show_create_table_verbosity = OFF' );
		$result = $this->assertQuery( 'SELECT @@show_create_table_verbosity' );
		$this->assertSame( '0', $result[0]->{'@@show_create_table_verbosity'} );

		$this->assertQuery( 'SET sql_auto_is_null = ON' );
		$result = $this->assertQuery( 'SELECT @@sql_auto_is_null' );
		$this->assertSame( '1', $result[0]->{'@@sql_auto_is_null'} );

		$this->assertQuery( 'SET sql_big_selects = OFF' );
		$result = $this->assertQuery( 'SELECT @@sql_big_selects' );
		$this->assertSame( '0', $result[0]->{'@@sql_big_selects'} );

		$this->assertQuery( 'SET sql_buffer_result = ON' );
		$result = $this->assertQuery( 'SELECT @@sql_buffer_result' );
		$this->assertSame( '1', $result[0]->{'@@sql_buffer_result'} );

		$this->assertQuery( 'SET sql_safe_updates = OFF' );
		$result = $this->assertQuery( 'SELECT @@sql_safe_updates' );
		$this->assertSame( '0', $result[0]->{'@@sql_safe_updates'} );

		$this->assertQuery( 'SET sql_warnings = ON' );
		$result = $this->assertQuery( 'SELECT @@sql_warnings' );
		$this->assertSame( '1', $result[0]->{'@@sql_warnings'} );

		$this->assertQuery( 'SET transaction_read_only = OFF' );
		$result = $this->assertQuery( 'SELECT @@transaction_read_only' );
		$this->assertSame( '0', $result[0]->{'@@transaction_read_only'} );
	}

	public function testUserVariables(): void {
		$this->assertQuery( 'SET @my_var = 1' );
		$result = $this->assertQuery( 'SELECT @my_var' );
		$this->assertEquals( 1, $result[0]->{'@my_var'} );

		$this->assertQuery( 'SET @my_var = @my_var + 1' );
		$result = $this->assertQuery( 'SELECT @my_var' );
		$this->assertEquals( 2, $result[0]->{'@my_var'} );

		$this->assertQuery( 'SET @my_var = @my_var + 1' );
		$result = $this->assertQuery( 'SELECT @my_var' );
		$this->assertEquals( 3, $result[0]->{'@my_var'} );
	}

	public function testVariableBackupAndRestoreForDumps(): void {
		// Set and backup variables.
		$this->assertQuery( '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;' );
		$this->assertQuery( '/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;' );
		$this->assertQuery( '/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;' );
		$this->assertQuery( '/*!50503 SET NAMES utf8mb4 */;' );
		$this->assertQuery( '/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;' );
		$this->assertQuery( "/*!40103 SET TIME_ZONE='+00:00' */;" );
		$this->assertQuery( '/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;' );
		$this->assertQuery( '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;' );
		$this->assertQuery( "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;" );
		$this->assertQuery( '/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;' );
		$this->assertQuery( '/*!40101 SET @saved_cs_client = @@character_set_client */; ' );
		$this->assertQuery( '/*!50503 SET character_set_client = utf8mb4 */;' );

		// Restore variables.
		$this->assertQuery( '/*!40101 SET character_set_client = @saved_cs_client */;' );
		$this->assertQuery( '/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;' );
		$this->assertQuery( '/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;' );
		$this->assertQuery( '/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;' );
		$this->assertQuery( '/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;' );
		$this->assertQuery( '/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;' );
		$this->assertQuery( '/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;' );
		$this->assertQuery( '/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;' );
		$this->assertQuery( '/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;' );
	}

	public function testLockingStatements(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT)' );

		// When there is no lock, UNLOCK statement shouldn't fail.
		$this->assertQuery( 'UNLOCK TABLES' );

		// READ LOCK.
		$this->assertQuery( 'LOCK TABLES t READ' );
		$this->assertQuery( 'UNLOCK TABLES' );

		// WRITE LOCK.
		$this->assertQuery( 'LOCK TABLES t WRITE' );
		$this->assertQuery( 'UNLOCK TABLES' );

		// LOCK inside a transaction.
		$this->assertQuery( 'BEGIN' );
		$this->assertQuery( 'LOCK TABLES t WRITE' );
		$this->assertQuery( 'UNLOCK TABLES' );
		$this->assertQuery( 'COMMIT' );

		// Transaction inside LOCK statements.
		$this->assertQuery( 'LOCK TABLES t WRITE' );
		$this->assertQuery( 'BEGIN' );
		$this->assertQuery( 'COMMIT' );
		$this->assertQuery( 'UNLOCK TABLES' );
	}

	public function testLockNonExistentTableForRead(): void {
		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( "Table 'wp.t' doesn't exist" );
		$this->assertQuery( 'LOCK TABLES t READ' );
	}

	public function testLockNonExistentTableForWrite(): void {
		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( "Table 'wp.t' doesn't exist" );
		$this->assertQuery( 'LOCK TABLES t WRITE' );
	}

	public function testLockMultipleWithNonExistentTable(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT)' );
		$this->assertQuery( 'CREATE TABLE t3 (id INT)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( "Table 'wp.t2' doesn't exist" );
		$this->assertQuery( 'LOCK TABLES t1 READ, t2 READ, t3 WRITE' );
	}

	public function testLockTemporaryTables(): void {
		$this->assertQuery( 'CREATE TEMPORARY TABLE t1 (id INT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT)' );
		$this->assertQuery( 'CREATE TEMPORARY TABLE t3 (id INT)' );
		$this->assertQuery( 'LOCK TABLES t1 READ, t2 READ, t3 WRITE' );
		$this->assertQuery( 'UNLOCK TABLES' );
	}

	public function testTransactionSavepoints(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT)' );

		$this->assertQuery( 'BEGIN' );
		$this->assertQuery( 'INSERT INTO t (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( array( '1' ), (array) array_column( $result, 'id' ) );

		$this->assertQuery( 'SAVEPOINT sp1' );
		$this->assertQuery( 'INSERT INTO t (id) VALUES (2)' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( array( '1', '2' ), (array) array_column( $result, 'id' ) );

		$this->assertQuery( 'SAVEPOINT sp2' );
		$this->assertQuery( 'INSERT INTO t (id) VALUES (3)' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( array( '1', '2', '3' ), (array) array_column( $result, 'id' ) );

		$this->assertQuery( 'ROLLBACK TO SAVEPOINT sp1' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( array( '1' ), (array) array_column( $result, 'id' ) );

		$this->assertQuery( 'RELEASE SAVEPOINT sp1' );
		$this->assertQuery( 'ROLLBACK' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( array(), (array) array_column( $result, 'id' ) );
	}

	public function testSelectOrderByAmbiguousColumnResolution(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );
		$this->assertQuery( 'INSERT INTO t1 (id, name) VALUES (1, "A1"), (2, "A2")' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (1, "B2"), (2, "B1")' );

		// The "name" column will be resolved to "t1.name" as per the SELECT item.
		$result = $this->assertQuery( 'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name DESC' );
		$this->assertEquals(
			array(
				(object) array( 'name' => 'A2' ),
				(object) array( 'name' => 'A1' ),
			),
			$result
		);

		// The "name" column will be resolved to "t2.name" as per the SELECT item.
		$result = $this->assertQuery( 'SELECT t2.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name DESC' );
		$this->assertEquals(
			array(
				(object) array( 'name' => 'B2' ),
				(object) array( 'name' => 'B1' ),
			),
			$result
		);

		// The "name" column will be resolved to "t1.name", the "id" column will be resolved to "t2.id".
		$this->assertQuery( 'INSERT INTO t1 (id, name) VALUES (3, "A2")' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (3, "A2")' );
		$result = $this->assertQuery( 'SELECT t2.id, t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name, id DESC' );
		$this->assertEquals(
			array(
				(object) array(
					'id'   => '1',
					'name' => 'A1',
				),
				(object) array(
					'id'   => '3',
					'name' => 'A2',
				),
				(object) array(
					'id'   => '2',
					'name' => 'A2',
				),
			),
			$result
		);

		// The "name" column will be resolved to "t1.name" in the subquery and to "t2.name" in the root query.
		$result = $this->assertQuery(
			'
			SELECT t2.name, s.name AS subquery_name
			FROM (SELECT t1.id, t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name DESC LIMIT 1) s
			JOIN t2 ON true
			ORDER BY name DESC
		'
		);
		$this->assertEquals(
			array(
				(object) array(
					'name'          => 'B2',
					'subquery_name' => 'A2',
				),
				(object) array(
					'name'          => 'B1',
					'subquery_name' => 'A2',
				),
				(object) array(
					'name'          => 'A2',
					'subquery_name' => 'A2',
				),
			),
			$result
		);

		// Parenthesized column reference can be used in both SELECT and ORDER BY lists.
		$result = $this->assertQuery( 'SELECT (t1.name) FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY (((name))) DESC' );
		$this->assertEquals(
			array(
				(object) array( '(t1.name)' => 'A2' ),
				(object) array( '(t1.name)' => 'A2' ),
				(object) array( '(t1.name)' => 'A1' ),
			),
			$result
		);

		/*
		 * With multiple identical aliases and no ambiguous column references,
		 * it just works, although sometimes the order may differ from MySQL.
		 * It may be nondeterministic, but it seems like MySQL picks the first
		 * non-column alias, while SQLite sorts by the first alias in the list.
		 *
		 * When we replace "SELECT t1.name" with "SELECT t2.name" in the query
		 * below, the SQLite order will differ from MySQL.
		 */
		$result = $this->assertQuery(
			"
			SELECT t1.name AS name, CONCAT(t1.name, '-one') AS name, CONCAT(t2.name, '-two') AS name
			FROM t1 JOIN t2 ON t2.id = t1.id
			ORDER BY name DESC
		"
		);
		$this->assertEquals(
			array(
				(object) array( 'name' => 'B1-two' ),
				(object) array( 'name' => 'A2-two' ),
				(object) array( 'name' => 'B2-two' ),
			),
			$result
		);

		/*
		 * The following query fails with "ambiguous column name" in MySQL, but
		 * in SQLite, it works. It's OK to keep this difference as MySQL behaves
		 * rather strangely in this case:
		 *
		 *   1) This is OK in MySQL:
		 *        SELECT t1.name AS col, 123 AS col ... ORDER BY col
		 *   2) This fails in MySQL:
		 *        SELECT t1.name AS col, t2.name AS col ... ORDER BY col
		 */
		$this->assertQuery( 'SELECT t1.name AS col, t2.name AS col FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY col' );
	}

	public function testSelectOrderByAmbiguousColumnError(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'ambiguous column name: name' );
		$this->assertQuery( 'SELECT t1.name, t2.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name DESC' );
	}


	public function testSelectOrderByAmbiguousColumnErrorWithoutSelectList(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'ambiguous column name: name' );
		$this->assertQuery( 'SELECT 1 FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name' );
	}

	public function testSelectGroupByAmbiguousColumnResolution(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );
		$this->assertQuery( 'INSERT INTO t1 (id, name) VALUES (1, "A"), (2, "A")' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (1, "B1"), (2, "B2")' );

		// The "name" column will be resolved to "t1.name" as per the SELECT item.
		$result = $this->assertQuery( 'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name' );
		$this->assertEquals(
			array( (object) array( 'name' => 'A' ) ),
			$result
		);

		// The "name" column will be resolved to "t2.name" as per the SELECT item.
		$result = $this->assertQuery( 'SELECT t2.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name' );
		$this->assertEquals(
			array(
				(object) array( 'name' => 'B1' ),
				(object) array( 'name' => 'B2' ),
			),
			$result
		);

		// Parenthesized column reference can be used in both SELECT and GROUP BY lists.
		$result = $this->assertQuery( 'SELECT (t1.name) FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY (((name)))' );
		$this->assertEquals(
			array( (object) array( '(t1.name)' => 'A' ) ),
			$result
		);

		/*
		 * The following query fails with "ambiguous column name" in MySQL, but
		 * in SQLite, it works. It's OK to keep this difference as MySQL behaves
		 * rather strangely in this case:
		 *
		 *   1) This is OK in MySQL:
		 *        SELECT t1.name AS col, 123 AS col ... GROUP BY col
		 *   2) This fails in MySQL:
		 *        SELECT t1.name AS col, t2.name AS col ... GROUP BY col
		 */
		$this->assertQuery( 'SELECT t1.name AS col, t2.name AS col FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY col' );
	}

	public function testSelectGroupByAmbiguousColumnError(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'ambiguous column name: name' );
		$this->assertQuery( 'SELECT t1.name, t2.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name' );
	}

	public function testSelectGroupByAmbiguousColumnErrorWithoutSelectList(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'ambiguous column name: name' );
		$this->assertQuery( 'SELECT 1 FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name' );
	}

	public function testSelectHavingAmbiguousColumnResolution(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );
		$this->assertQuery( 'INSERT INTO t1 (id, name) VALUES (1, "A"), (2, "A")' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (1, "B1"), (2, "B2")' );

		// The "name" column will be resolved to "t1.name" as per the SELECT item.
		$result = $this->assertQuery( 'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING name' );
		$this->assertEquals( array(), $result );

		// The "name" column will be resolved to "t2.name" as per the SELECT item.
		$result = $this->assertQuery( 'SELECT t2.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING name' );
		$this->assertEquals( array(), $result );

		// Parenthesized column reference can be used in both SELECT and GROUP BY lists.
		$result = $this->assertQuery( 'SELECT (t1.name) FROM t1 JOIN t2 ON t2.id = t1.id HAVING (((name)))' );
		$this->assertEquals( array(), $result );

		/*
		 * The following query fails with "ambiguous column name" in MySQL, but
		 * in SQLite, it works. It's OK to keep this difference as MySQL behaves
		 * rather strangely in this case:
		 *
		 *   1) This is OK in MySQL:
		 *        SELECT t1.name AS col, 123 AS col ... HAVING col
		 *   2) This fails in MySQL:
		 *        SELECT t1.name AS col, t2.name AS col ... HAVING col
		 */
		$this->assertQuery( 'SELECT t1.name AS col, t2.name AS col FROM t1 JOIN t2 ON t2.id = t1.id HAVING col' );
	}

	public function testSelectHavingAmbiguousColumnError(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'ambiguous column name: name' );
		$this->assertQuery( 'SELECT t1.name, t2.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING name' );
	}

	public function testSelectHavingAmbiguousColumnErrorWithoutSelectList(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'ambiguous column name: name' );
		$this->assertQuery( 'SELECT 1 FROM t1 JOIN t2 ON t2.id = t1.id HAVING name' );
	}

	public function testRollbackNonExistentTransactionSavepoint(): void {
		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'no such savepoint: sp1' );
		$this->assertQuery( 'ROLLBACK TO SAVEPOINT sp1' );
	}

	public function testForeignKeyOnUpdateNoAction(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, FOREIGN KEY (id) REFERENCES t1 (id) ON UPDATE NO ACTION)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed' );
		$this->assertQuery( 'UPDATE t1 SET id = 2 WHERE id = 1' );
	}

	public function testForeignKeyOnUpdateRestrict(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, FOREIGN KEY (id) REFERENCES t1 (id) ON UPDATE RESTRICT)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed' );
		$this->assertQuery( 'UPDATE t1 SET id = 2 WHERE id = 1' );
	}

	public function testForeignKeyOnUpdateCascade(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, FOREIGN KEY (id) REFERENCES t1 (id) ON UPDATE CASCADE)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->assertQuery( 'UPDATE t1 SET id = 2 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertEquals( array( (object) array( 'id' => '2' ) ), $result );
	}

	public function testForeignKeyOnUpdateSetNull(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, FOREIGN KEY (id) REFERENCES t1 (id) ON UPDATE SET NULL)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->assertQuery( 'UPDATE t1 SET id = 2 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertEquals( array( (object) array( 'id' => null ) ), $result );
	}

	public function testForeignKeyOnUpdateSetDefault(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT DEFAULT 0, FOREIGN KEY (id) REFERENCES t1 (id) ON UPDATE SET DEFAULT)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->assertQuery( 'UPDATE t1 SET id = 2 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertEquals( array( (object) array( 'id' => '0' ) ), $result );
	}

	public function testForeignKeyOnDeleteNoAction(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, FOREIGN KEY (id) REFERENCES t1 (id) ON DELETE NO ACTION)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed' );
		$this->assertQuery( 'DELETE FROM t1 WHERE id = 1' );
	}

	public function testForeignKeyOnDeleteRestrict(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, FOREIGN KEY (id) REFERENCES t1 (id) ON DELETE RESTRICT)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->expectException( 'WP_SQLite_Driver_Exception' );
		$this->expectExceptionMessage( 'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed' );
		$this->assertQuery( 'DELETE FROM t1 WHERE id = 1' );
	}

	public function testForeignKeyOnDeleteCascade(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, FOREIGN KEY (id) REFERENCES t1 (id) ON DELETE CASCADE)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->assertQuery( 'DELETE FROM t1 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertEquals( array(), $result );
	}

	public function testForeignKeyOnDeleteSetNull(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, FOREIGN KEY (id) REFERENCES t1 (id) ON DELETE SET NULL)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->assertQuery( 'DELETE FROM t1 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertEquals( array( (object) array( 'id' => null ) ), $result );
	}

	public function testForeignKeyOnDeleteSetDefault(): void {
		$this->assertQuery( 'CREATE TABLE t1 (id INT PRIMARY KEY)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT DEFAULT 0, FOREIGN KEY (id) REFERENCES t1 (id) ON DELETE SET DEFAULT)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t2 (id) VALUES (1)' );

		$this->assertQuery( 'DELETE FROM t1 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM t2' );
		$this->assertEquals( array( (object) array( 'id' => '0' ) ), $result );
	}

	public function testUpdateWithJoinedTables(): void {
		$sqlite_version = $this->engine->get_sqlite_version();
		if ( version_compare( $sqlite_version, '3.33.0', '<' ) ) {
			$this->markTestSkipped(
				sprintf( "SQLite version %s doesn't support UPDATE with FROM clause.", $sqlite_version )
			);
			return;
		}

		$this->assertQuery( 'CREATE TABLE t1 (id INT, comment TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t3 (id INT, name TEXT)' );

		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1), (2), (3)' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (1, "update")' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (2, "do-not-update")' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (3, "update")' );
		$this->assertQuery( 'INSERT INTO t3 (id, name) VALUES (1, "do-not-update")' );
		$this->assertQuery( 'INSERT INTO t3 (id, name) VALUES (2, "update")' );
		$this->assertQuery( 'INSERT INTO t3 (id, name) VALUES (3, "update")' );

		// Fully qualified column reference in SET.
		$this->assertQuery(
			"UPDATE t1, t2
			JOIN t3 ON t3.id = t1.id
			SET t1.id = 0
			WHERE t2.id = t1.id
			AND t2.name = 'update'
			AND t3.name = 'update'"
		);

		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertEquals(
			array(
				(object) array(
					'id'      => '1',
					'comment' => null,
				),
				(object) array(
					'id'      => '2',
					'comment' => null,
				),
				(object) array(
					'id'      => '0',
					'comment' => null,
				),
			),
			$result
		);

		// Unqualified column reference in SET.
		$this->assertQuery( 'UPDATE t1 SET id = 3 WHERE id = 0' );
		$this->assertQuery(
			"UPDATE t1, t2
			JOIN t3 ON t3.id = t1.id
			SET comment = 'updated'
			WHERE t2.id = t1.id
			AND t2.name = 'update'
			AND t3.name = 'update'"
		);

		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertEquals(
			array(
				(object) array(
					'id'      => '1',
					'comment' => null,
				),
				(object) array(
					'id'      => '2',
					'comment' => null,
				),
				(object) array(
					'id'      => '3',
					'comment' => 'updated',
				),
			),
			$result
		);
	}

	public function testUpdateWithJoinedTablesInNonStrictMode(): void {
		$sqlite_version = $this->engine->get_sqlite_version();
		if ( version_compare( $sqlite_version, '3.33.0', '<' ) ) {
			$this->markTestSkipped(
				sprintf( "SQLite version %s doesn't support UPDATE with FROM clause.", $sqlite_version )
			);
			return;
		}

		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$this->assertQuery( 'CREATE TABLE t1 (id INT, comment TEXT)' );
		$this->assertQuery( 'CREATE TABLE t2 (id INT, name TEXT)' );
		$this->assertQuery( 'CREATE TABLE t3 (id INT, name TEXT)' );

		$this->assertQuery( 'INSERT INTO t1 (id) VALUES (1), (2), (3)' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (1, "update")' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (2, "do-not-update")' );
		$this->assertQuery( 'INSERT INTO t2 (id, name) VALUES (3, "update")' );
		$this->assertQuery( 'INSERT INTO t3 (id, name) VALUES (1, "do-not-update")' );
		$this->assertQuery( 'INSERT INTO t3 (id, name) VALUES (2, "update")' );
		$this->assertQuery( 'INSERT INTO t3 (id, name) VALUES (3, "update")' );

		// Fully qualified column reference in SET.
		$this->assertQuery(
			"UPDATE t1, t2
			JOIN t3 ON t3.id = t1.id
			SET t1.id = 0
			WHERE t2.id = t1.id
			AND t2.name = 'update'
			AND t3.name = 'update'"
		);

		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertEquals(
			array(
				(object) array(
					'id'      => '1',
					'comment' => null,
				),
				(object) array(
					'id'      => '2',
					'comment' => null,
				),
				(object) array(
					'id'      => '0',
					'comment' => null,
				),
			),
			$result
		);

		// Unqualified column reference in SET.
		$this->assertQuery( 'UPDATE t1 SET id = 3 WHERE id = 0' );
		$this->assertQuery(
			"UPDATE t1, t2
			JOIN t3 ON t3.id = t1.id
			SET comment = 'updated'
			WHERE t2.id = t1.id
			AND t2.name = 'update'
			AND t3.name = 'update'"
		);

		$result = $this->assertQuery( 'SELECT * FROM t1' );
		$this->assertEquals(
			array(
				(object) array(
					'id'      => '1',
					'comment' => null,
				),
				(object) array(
					'id'      => '2',
					'comment' => null,
				),
				(object) array(
					'id'      => '3',
					'comment' => 'updated',
				),
			),
			$result
		);
	}

	public function testUpdateWithJoinComplexQuery(): void {
		$sqlite_version = $this->engine->get_sqlite_version();
		if ( version_compare( $sqlite_version, '3.33.0', '<' ) ) {
			$this->markTestSkipped(
				sprintf( "SQLite version %s doesn't support UPDATE with FROM clause.", $sqlite_version )
			);
			return;
		}

		$this->assertQuery( "SET SESSION sql_mode = ''" );

		$default_date = '0000-00-00 00:00:00';
		$this->assertQuery(
			"CREATE TABLE wp_actionscheduler_actions (
				action_id bigint(20) unsigned NOT NULL auto_increment,
				status varchar(20) NOT NULL,
				scheduled_date_gmt datetime NULL default '{$default_date}',
				scheduled_date_local datetime NULL default '{$default_date}',
				priority tinyint unsigned NOT NULL default '10',
				attempts int(11) NOT NULL default '0',
				last_attempt_gmt datetime NULL default '{$default_date}',
				last_attempt_local datetime NULL default '{$default_date}',
				claim_id bigint(20) unsigned NOT NULL default '0',
				PRIMARY KEY  (action_id)
			)"
		);

		$this->assertQuery(
			"UPDATE wp_actionscheduler_actions t1
			JOIN (
				SELECT action_id
				FROM wp_actionscheduler_actions
				WHERE claim_id = 0 AND scheduled_date_gmt <= '2025-09-03 12:23:55' AND status = 'pending'
				ORDER BY priority ASC, attempts ASC, scheduled_date_gmt ASC, action_id ASC
				LIMIT 25
				FOR UPDATE
			) t2 ON t1.action_id = t2.action_id
			SET claim_id = 37, last_attempt_gmt = '2025-09-03 12:23:55', last_attempt_local = '2025-09-03 12:23:55'"
		);
	}

	public function testBinaryLiterals(): void {
		$result = $this->assertQuery( 'SELECT 0b0100000101111010' );
		$this->assertEquals( array( (object) array( '0b0100000101111010' => 'Az' ) ), $result );

		$result = $this->assertQuery( "SELECT b'0100000101111010'" );
		$this->assertEquals( array( (object) array( "b'0100000101111010'" => 'Az' ) ), $result );

		$result = $this->assertQuery( "SELECT B'0100000101111010'" );
		$this->assertEquals( array( (object) array( "B'0100000101111010'" => 'Az' ) ), $result );

		// Verify correct padding (0b1 === 0b01 === 0b001 ... === 0x00000001).
		$result = $this->assertQuery( 'SELECT 0b1' );
		$this->assertEquals( array( (object) array( '0b1' => pack( 'H*', '01' ) ) ), $result );

		$result = $this->assertQuery( 'SELECT 0b01' );
		$this->assertEquals( array( (object) array( '0b01' => pack( 'H*', '01' ) ) ), $result );

		$result = $this->assertQuery( 'SELECT 0b001' );
		$this->assertEquals( array( (object) array( '0b001' => pack( 'H*', '01' ) ) ), $result );

		$result = $this->assertQuery( 'SELECT 0b00000001' );
		$this->assertEquals( array( (object) array( '0b00000001' => pack( 'H*', '01' ) ) ), $result );

		$result = $this->assertQuery( 'SELECT 0b000000001' );
		$this->assertEquals( array( (object) array( '0b000000001' => pack( 'H*', '0001' ) ) ), $result );
	}

	public function testHexadecimalLiterals(): void {
		$result = $this->assertQuery( 'SELECT 0x417a' );
		$this->assertEquals( array( (object) array( '0x417a' => 'Az' ) ), $result );

		$result = $this->assertQuery( "SELECT x'417a'" );
		$this->assertEquals( array( (object) array( "x'417a'" => 'Az' ) ), $result );

		$result = $this->assertQuery( "SELECT X'417a'" );
		$this->assertEquals( array( (object) array( "X'417a'" => 'Az' ) ), $result );
	}

	public function testColumnInfo(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				id INT,
				name TEXT,
				score DOUBLE,
				data BLOB,
				PRIMARY KEY (id),
				UNIQUE KEY (name(64))
			)'
		);

		$this->assertQuery( "INSERT INTO t VALUES (1, 'name', 1.1, B'01101001')" );

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 4, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();
		$this->assertCount( 4, $column_info );

		$this->assertSame(
			array(
				'native_type'      => 'LONG',
				'pdo_type'         => PDO::PARAM_INT,
				'flags'            => array( 'not_null', 'primary_key' ),
				'table'            => 't',
				'name'             => 'id',
				'len'              => 11,
				'precision'        => 0,
				'sqlite:decl_type' => 'INT',

				// Additional MySQLi metadata.
				'mysqli:orgname'   => 'id',
				'mysqli:orgtable'  => 't',
				'mysqli:db'        => 'wp',
				'mysqli:charsetnr' => 63,
				'mysqli:flags'     => 0, // 53251 in MySQL.
				'mysqli:type'      => 3,
			),
			$column_info[0]
		);

		$this->assertSame(
			array(
				'native_type'      => 'BLOB',
				'pdo_type'         => PDO::PARAM_STR,
				'flags'            => array( 'unique_key', 'blob' ),
				'table'            => 't',
				'name'             => 'name',
				'len'              => 262140,
				'precision'        => 0,
				'sqlite:decl_type' => 'TEXT',

				// Additional MySQLi metadata.
				'mysqli:orgname'   => 'name',
				'mysqli:orgtable'  => 't',
				'mysqli:db'        => 'wp',
				'mysqli:charsetnr' => 255,
				'mysqli:flags'     => 0, // 16404 in MySQL.
				'mysqli:type'      => 252,
			),
			$column_info[1]
		);

		$this->assertSame(
			array(
				'native_type'      => 'DOUBLE',
				'pdo_type'         => PDO::PARAM_STR,
				'flags'            => array(),
				'table'            => 't',
				'name'             => 'score',
				'len'              => 22,
				'precision'        => 31,
				'sqlite:decl_type' => 'REAL',

				// Additional MySQLi metadata.
				'mysqli:orgname'   => 'score',
				'mysqli:orgtable'  => 't',
				'mysqli:db'        => 'wp',
				'mysqli:charsetnr' => 63,
				'mysqli:flags'     => 0, // 32768 in MySQL.
				'mysqli:type'      => 5,
			),
			$column_info[2]
		);

		$this->assertSame(
			array(
				'native_type'      => 'BLOB',
				'pdo_type'         => PDO::PARAM_STR,
				'flags'            => array( 'blob' ),
				'table'            => 't',
				'name'             => 'data',
				'len'              => 65535,
				'precision'        => 0,
				'sqlite:decl_type' => 'BLOB',

				// Additional MySQLi metadata.
				'mysqli:orgname'   => 'data',
				'mysqli:orgtable'  => 't',
				'mysqli:db'        => 'wp',
				'mysqli:charsetnr' => 63,
				'mysqli:flags'     => 0, // 144 in MySQL.
				'mysqli:type'      => 252,
			),
			$column_info[3]
		);
	}

	public function testColumnInfoWithConstraints(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				id INT PRIMARY KEY,
				slug VARCHAR(255) UNIQUE,
				parent_id INT,
				CONSTRAINT parent_id_fk FOREIGN KEY (parent_id) REFERENCES t (id)
			)'
		);

		$this->assertQuery( 'INSERT INTO t VALUES (1, "slug", 1)' );

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 3, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'LONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null', 'primary_key' ),
					'table'            => 't',
					'name'             => 'id',
					'len'              => 11,
					'precision'        => 0,
					'sqlite:decl_type' => 'INT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'id',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 53251 in MySQL.
					'mysqli:type'      => 3,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'unique_key' ),
					'table'            => 't',
					'name'             => 'slug',
					'len'              => 1020,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'slug',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 16388 in MySQL.
					'mysqli:type'      => 253,
				),
				array(
					// TODO: MySQL seems to automatically create indexes for foreign key columns.
					//       We should mirror this behavior to both information schema and SQLite.
					'native_type'      => 'LONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(), // Has "multiple_key" in MySQL.
					'table'            => 't',
					'name'             => 'parent_id',
					'len'              => 11,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'parent_id',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 49160 in MySQL.
					'mysqli:type'      => 3,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoForIntegerDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				col_bit BIT,
				col_bool BOOL,
				col_tinyint TINYINT,
				col_smallint SMALLINT,
				col_mediumint MEDIUMINT,
				col_int INT,
				col_bigint BIGINT
			)'
		);

		$this->assertQuery( 'INSERT INTO t VALUES (0, 1, 2, 3, 4, 5, 6)' );

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 7, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'BIT',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_bit',
					'len'              => 1,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_bit',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32 in MySQL.
					'mysqli:type'      => 16,
				),
				array(
					'native_type'      => 'TINY',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_bool',
					'len'              => 1,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_bool',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 1,
				),
				array(
					'native_type'      => 'TINY',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_tinyint',
					'len'              => 4,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_tinyint',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 1,
				),
				array(
					'native_type'      => 'SHORT',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_smallint',
					'len'              => 6,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_smallint',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 2,
				),
				array(
					'native_type'      => 'INT24',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_mediumint',
					'len'              => 9,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_mediumint',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 9,
				),
				array(
					'native_type'      => 'LONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_int',
					'len'              => 11,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_int',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 3,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_bigint',
					'len'              => 20,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_bigint',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 8,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoForUnsignedIntegerDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				col_tinyint_unsigned TINYINT UNSIGNED,
				col_smallint_unsigned SMALLINT UNSIGNED,
				col_mediumint_unsigned MEDIUMINT UNSIGNED,
				col_int_unsigned INT UNSIGNED,
				col_bigint_unsigned BIGINT UNSIGNED
			)'
		);

		$this->assertQuery( 'INSERT INTO t VALUES (1, 2, 3, 4, 5)' );

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 5, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'TINY',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_tinyint_unsigned',
					'len'              => 3,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_tinyint_unsigned',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32800 in MySQL.
					'mysqli:type'      => 1,
				),
				array(
					'native_type'      => 'SHORT',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_smallint_unsigned',
					'len'              => 5,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_smallint_unsigned',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32800 in MySQL.
					'mysqli:type'      => 2,
				),
				array(
					'native_type'      => 'INT24',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_mediumint_unsigned',
					'len'              => 8,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_mediumint_unsigned',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32800 in MySQL.
					'mysqli:type'      => 9,
				),
				array(
					'native_type'      => 'LONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_int_unsigned',
					'len'              => 10,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_int_unsigned',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32800 in MySQL.
					'mysqli:type'      => 3,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_bigint_unsigned',
					'len'              => 20,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_bigint_unsigned',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32800 in MySQL.
					'mysqli:type'      => 8,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoForFloatingPointDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				col_float FLOAT,
				col_double DOUBLE,
				col_real REAL,
				col_decimal DECIMAL(10,2),
				col_dec DEC(10,2),
				col_fixed FIXED(10,2),
				col_numeric NUMERIC(10,2)
			)'
		);

		$this->assertQuery( 'INSERT INTO t VALUES (1.1, 2.2, 3.3, 4.4, 5.5, 6.6, 7.7)' );

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 7, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'FLOAT',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_float',
					'len'              => 12,
					'precision'        => 31,
					'sqlite:decl_type' => 'REAL',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_float',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 4,
				),
				array(
					'native_type'      => 'DOUBLE',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_double',
					'len'              => 22,
					'precision'        => 31,
					'sqlite:decl_type' => 'REAL',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_double',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 5,
				),
				array(
					'native_type'      => 'DOUBLE',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_real',
					'len'              => 22, // PDO reports 22 while MySQLi 12.
					'precision'        => 31,
					'sqlite:decl_type' => 'REAL',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_real',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 5, // 4 in MySQL.
				),
				array(
					'native_type'      => 'NEWDECIMAL',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_decimal',
					'len'              => 12,
					'precision'        => 2,
					'sqlite:decl_type' => 'REAL',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_decimal',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 246,
				),
				array(
					'native_type'      => 'NEWDECIMAL',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_dec',
					'len'              => 12,
					'precision'        => 2,
					'sqlite:decl_type' => 'REAL',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_dec',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 246,
				),
				array(
					'native_type'      => 'NEWDECIMAL',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_fixed',
					'len'              => 12,
					'precision'        => 2,
					'sqlite:decl_type' => 'REAL',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_fixed',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 246,
				),
				array(
					'native_type'      => 'NEWDECIMAL',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_numeric',
					'len'              => 12,
					'precision'        => 2,
					'sqlite:decl_type' => 'REAL',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_numeric',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 246,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoForStringDataTypes(): void {
		$this->assertQuery(
			"CREATE TABLE t (
				col_char CHAR(10),
				col_varchar VARCHAR(10),
				col_nchar NCHAR(10),
				col_nvarchar NVARCHAR(10),
				col_tinytext TINYTEXT,
				col_text TEXT,
				col_mediumtext MEDIUMTEXT,
				col_longtext LONGTEXT,
				col_enum ENUM('a', 'b', 'c'),
				col_set SET('a', 'b', 'c'),
				col_json JSON
			)"
		);

		$this->assertQuery( 'INSERT INTO t VALUES ("a", "b", "c", "d", "e", "f", "g", "h", "a", "b", "{}")' );

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 11, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_char',
					'len'              => 40,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_char',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 254,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_varchar',
					'len'              => 40,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_varchar',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_nchar',
					'len'              => 40,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_nchar',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 254,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_nvarchar',
					'len'              => 40,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_nvarchar',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_tinytext',
					'len'              => 1020,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_tinytext',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 16 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_text',
					'len'              => 262140,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_text',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 16 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_mediumtext',
					'len'              => 67108860,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_mediumtext',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 16 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_longtext',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_longtext',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 16 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_enum',
					'len'              => 4,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_enum',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 256 in MySQL.
					'mysqli:type'      => 254,
				),
				array(
					'native_type'      => 'STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_set',
					'len'              => 20,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_set',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 2048 in MySQL.
					'mysqli:type'      => 254,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_json',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_json',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255, // 63 in MySQL.
					'mysqli:flags'     => 0,   // 144 in MySQL.
					'mysqli:type'      => 245,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoForDateAndTimeDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				col_date DATE,
				col_time TIME,
				col_datetime DATETIME,
				col_timestamp TIMESTAMP,
				col_year YEAR
			)'
		);

		$this->assertQuery( 'INSERT INTO t VALUES ("2024-01-01", "12:00:00", "2024-01-01 12:00:00", "2024-01-01 12:00:00", 2024)' );

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 5, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'DATE',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_date',
					'len'              => 10,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_date',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 10,
				),
				array(
					'native_type'      => 'TIME',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_time',
					'len'              => 10,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_time',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 11,
				),
				array(
					'native_type'      => 'DATETIME',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_datetime',
					'len'              => 19,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_datetime',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 12,
				),
				array(
					'native_type'      => 'TIMESTAMP',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_timestamp',
					'len'              => 19,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_timestamp',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 7,
				),
				array(
					'native_type'      => 'YEAR',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_year',
					'len'              => 4,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_year',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32864 in MySQL.
					'mysqli:type'      => 13,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoForBinaryDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				col_binary BINARY(10),
				col_varbinary VARBINARY(10),
				col_tinyblob TINYBLOB,
				col_blob BLOB,
				col_mediumblob MEDIUMBLOB,
				col_longblob LONGBLOB
			)'
		);

		$this->assertQuery( "INSERT INTO t VALUES (B'01000001', B'01101001', B'10101010', B'01010101', B'10000000', B'11111111')" );

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 6, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'BLOB',          // STRING in MySQL.
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ), // No flags in MySQL.
					'table'            => 't',
					'name'             => 'col_binary',
					'len'              => 10,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_binary',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 254,
				),
				array(
					'native_type'      => 'BLOB',          // VAR_STRING in MySQL.
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ), // No flags in MySQL.
					'table'            => 't',
					'name'             => 'col_varbinary',
					'len'              => 10,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_varbinary',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_tinyblob',
					'len'              => 255,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_tinyblob',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_blob',
					'len'              => 65535,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_blob',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_mediumblob',
					'len'              => 16777215,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_mediumblob',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_longblob',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_longblob',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 252,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoForSpatialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				col_geometry GEOMETRY,
				col_point POINT,
				col_linestring LINESTRING,
				col_polygon POLYGON,
				col_multipoint MULTIPOINT,
				col_multilinestring MULTILINESTRING,
				col_multipolygon MULTIPOLYGON,
				col_geomcollection GEOMCOLLECTION,
				col_geometrycollection GEOMETRYCOLLECTION
			)'
		);

		$this->assertQuery(
			"INSERT INTO t VALUES (
				'POINT(1 1)',
				'POINT(1 1)',
				'LINESTRING(0 0, 1 1)',
				'POLYGON((0 0, 1 0, 0 1, 0 0))',
				'MULTIPOINT(1 1, 2 2)',
				'MULTILINESTRING((0 0, 1 1), (2 2, 3 3))',
				'MULTIPOLYGON(((0 0, 1 0, 0 1, 0 0)))',
				'GEOMCOLLECTION(POINT(1 1), LINESTRING(0 0, 1 1))',
				'GEOMETRYCOLLECTION(POINT(1 1), LINESTRING(0 0, 1 1))'
			)"
		);

		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 9, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_geometry',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_geometry',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_point',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_point',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_linestring',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_linestring',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_polygon',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_polygon',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_multipoint',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_multipoint',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_multilinestring',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_multilinestring',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_multipolygon',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_multipolygon',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_geomcollection',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_geomcollection',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_geometrycollection',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_geometrycollection',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoForExpressions(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );
		$this->assertQuery(
			"SELECT
				NULL AS col_expr_1,
				TRUE AS col_expr_2,
				FALSE AS col_expr_3,
				1 AS col_expr_4,
				(1 + 1) AS col_expr_5,
				'abc' AS col_expr_6,
				COUNT(*) AS col_expr_7,
				SUM(id) AS col_expr_8,
				CONCAT('a', 'b') AS col_expr_9,
				YEAR('2025-01-01') AS col_expr_10,
				CAST('2024-01-01' AS DATE) AS col_expr_11,
				CAST(X'68656C6C6F' AS BINARY) AS col_expr_12,
				CAST('123' AS CHAR) AS col_expr_13,
				CAST(42 AS SIGNED) AS col_expr_14,
				COALESCE(NULL, 'fallback') AS col_expr_15,
				CASE WHEN id > 5 THEN 'yes' ELSE 'no' END AS col_expr_16,
				CASE WHEN id < 5 THEN 'string' ELSE 123 END AS col_expr_17,
				ABS(-7) AS col_expr_18,
				RAND() AS col_expr_19,
				(SELECT 1) AS col_expr_20
			FROM t"
		);
		$this->assertEquals( 20, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();

		$this->assertSame(
			array(
				array(
					'native_type'      => 'NULL',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => '',
					'name'             => 'col_expr_1',
					'len'              => 0,
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_1',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32896 in MySQL.
					'mysqli:type'      => 6,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_2',
					'len'              => 21, // 1 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_2',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32897 in MySQL.
					'mysqli:type'      => 8,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_3',
					'len'              => 21, // 1 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_3',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32897 in MySQL.
					'mysqli:type'      => 8,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_4',
					'len'              => 21, // 2 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_4',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32897 in MySQL.
					'mysqli:type'      => 8,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_5',
					'len'              => 21, // 3 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_5',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32897 in MySQL.
					'mysqli:type'      => 8,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_6',
					'len'              => 65535, // 12 in MySQL.
					'precision'        => 31,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_6',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 1 in MySQL.
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_7',
					'len'              => 21,
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_7',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32769 in MySQL.
					'mysqli:type'      => 8,
				),
				array(
					'native_type'      => 'LONGLONG',     // NEWDECIMAL in MySQL.
					'pdo_type'         => PDO::PARAM_INT, // PARAM_STR in MySQL.
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_8',
					'len'              => 21,             // 33 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_8',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 8, // 246 in MySQL.
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_9',
					'len'              => 65535, // 8 in MySQL.
					'precision'        => 31,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_9',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'LONGLONG',          // "YEAR" in MySQL.
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ), // Empty in MySQL.
					'table'            => '',
					'name'             => 'col_expr_10',
					'len'              => 21,                  // 4 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_10',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32928 in MySQL.
					'mysqli:type'      => 8, // 13 in MySQL.
				),
				array(
					// "CAST('2024-01-01' AS DATE)" seems to behave differently in SQLite.
					'native_type'      => 'VAR_STRING',        // "DATE" in MySQL.
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ), // Empty in MySQL.
					'table'            => '',
					'name'             => 'col_expr_11',
					'len'              => 65535,               // 10 in MySQL.
					'precision'        => 31,                  // 0 in MySQL.
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_11',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255, // 63 in MySQL.
					'mysqli:flags'     => 0,   // 128 in MySQL.
					'mysqli:type'      => 253, // 10 in MySQL.
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_12',
					'len'              => 65535, // 5 in MySQL.
					'precision'        => 31,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_12',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255, // 63 in MySQL.
					'mysqli:flags'     => 0,   // 128 in MySQL.
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_13',
					'len'              => 65535, // 12 in MySQL.
					'precision'        => 31,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_13',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_14',
					'len'              => 21,
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_14',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32897 in MySQL.
					'mysqli:type'      => 8,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_15',
					'len'              => 65535, // 32 in MySQL.
					'precision'        => 31,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_15',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 1 in MySQL.
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_16',
					'len'              => 65535, // 12 in MySQL.
					'precision'        => 31,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_16',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 1 in MySQL.
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_17',
					'len'              => 65535, // 24 in MySQL.
					'precision'        => 31,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_17',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 1 in MySQL.
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_18',
					'len'              => 21, // 2 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_18',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32897 in MySQL.
					'mysqli:type'      => 8,
				),
				array(
					// TODO: Fix custom "RAND()" function to behave like in MySQL.
					'native_type'      => 'LONGLONG',          // DOUBLE in MySQL.
					'pdo_type'         => PDO::PARAM_INT,      // PARAM_STR in MySQL.
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_19',
					'len'              => 21,                  // 23 in MySQL.
					'precision'        => 0,                   // 31 in MySQL.
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_19',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32769 in MySQL.
					'mysqli:type'      => 8, // 5 in MySQL.
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_20',
					'len'              => 21, // 2 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_20',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32897 in MySQL.
					'mysqli:type'      => 8,
				),
			),
			$column_info
		);
	}

	public function testColumnInfoWithZeroRows(): void {
		$this->assertQuery(
			'CREATE TABLE t (
				col_int INT,
				col_float FLOAT,
				col_char CHAR(10),
				col_varchar VARCHAR(10),
				col_text TEXT,
				col_json JSON,
				col_binary BINARY(10),
				col_varbinary VARBINARY(10),
				col_blob BLOB,
				col_date DATE,
				col_timestamp TIMESTAMP,
				col_geometry GEOMETRY
			)'
		);

		$this->assertQuery(
			"SELECT
				*,
				COUNT(*) AS col_expr_1,
				SUM(col_int) AS col_expr_2,
				CASE WHEN col_int > 5 THEN 'yes' ELSE 'no' END AS col_expr_3,
				CASE WHEN col_int < 5 THEN 'string' ELSE 123 END AS col_expr_4
			FROM t"
		);
		$this->assertEquals( 16, $this->engine->get_last_column_count() );

		$column_info = $this->engine->get_last_column_meta();
		$this->assertCount( 16, $column_info );

		$this->assertSame(
			array(
				array(
					'native_type'      => 'LONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_int',
					'len'              => 11,
					'precision'        => 0,
					'sqlite:decl_type' => 'INTEGER',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_int',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 3,
				),
				array(
					'native_type'      => 'FLOAT',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_float',
					'len'              => 12,
					'precision'        => 31,
					'sqlite:decl_type' => 'REAL',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_float',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 32768 in MySQL.
					'mysqli:type'      => 4,
				),
				array(
					'native_type'      => 'STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_char',
					'len'              => 40,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_char',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 254,
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_varchar',
					'len'              => 40,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_varchar',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_text',
					'len'              => 262140,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_text',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255,
					'mysqli:flags'     => 0, // 16 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'BLOB', // Missing in MySQL.
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_json',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_json',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255, // 63 in MySQL.
					'mysqli:flags'     => 0,   // 144 in MySQL.
					'mysqli:type'      => 245,
				),
				array(
					'native_type'      => 'BLOB', // STRING in MySQL.
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_binary',
					'len'              => 10,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_binary',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 254,
				),
				array(
					'native_type'      => 'BLOB', // VAR_STRING in MySQL.
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_varbinary',
					'len'              => 10,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_varbinary',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'BLOB',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_blob',
					'len'              => 65535,
					'precision'        => 0,
					'sqlite:decl_type' => 'BLOB',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_blob',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 252,
				),
				array(
					'native_type'      => 'DATE',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_date',
					'len'              => 10,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_date',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 10,
				),
				array(
					'native_type'      => 'TIMESTAMP',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => 't',
					'name'             => 'col_timestamp',
					'len'              => 19,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_timestamp',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 7,
				),
				array(
					'native_type'      => 'GEOMETRY',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'blob' ),
					'table'            => 't',
					'name'             => 'col_geometry',
					'len'              => 4294967295,
					'precision'        => 0,
					'sqlite:decl_type' => 'TEXT',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_geometry',
					'mysqli:orgtable'  => 't',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 144 in MySQL.
					'mysqli:type'      => 255,
				),
				array(
					'native_type'      => 'LONGLONG',
					'pdo_type'         => PDO::PARAM_INT,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_1',
					'len'              => 21,
					'precision'        => 0, // 32897 in MySQL.
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_1',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0,
					'mysqli:type'      => 8,
				),
				array(
					// For "SUM(*)" without rows, SQLite fails to provide a type.
					'native_type'      => 'NULL', // NEWDECIMAL in MySQL.
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array(),
					'table'            => '',
					'name'             => 'col_expr_2',
					'len'              => 0,      // 33 in MySQL.
					'precision'        => 0,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_2',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63,
					'mysqli:flags'     => 0, // 128 in MySQL.
					'mysqli:type'      => 6, // 246 in MySQL.
				),
				array(
					'native_type'      => 'VAR_STRING',
					'pdo_type'         => PDO::PARAM_STR,
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_3',
					'len'              => 65535, // 12 in MySQL.
					'precision'        => 31,
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_3',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 255, // 63 in MySQL.
					'mysqli:flags'     => 0,
					'mysqli:type'      => 253,
				),
				array(
					'native_type'      => 'LONGLONG',     // VAR_STRING in MySQL.
					'pdo_type'         => PDO::PARAM_INT, // PARAM_STR in MySQL.
					'flags'            => array( 'not_null' ),
					'table'            => '',
					'name'             => 'col_expr_4',
					'len'              => 21, // 24 in MySQL.
					'precision'        => 0,  // 31 in MySQL.
					'sqlite:decl_type' => '',

					// Additional MySQLi metadata.
					'mysqli:orgname'   => 'col_expr_4',
					'mysqli:orgtable'  => '',
					'mysqli:db'        => 'wp',
					'mysqli:charsetnr' => 63, // 255 in MySQL.
					'mysqli:flags'     => 0,  // 1 in MySQL.
					'mysqli:type'      => 8,  // 253 in MySQL.
				),
			),
			$column_info
		);
	}

	public function testColumnInfoWithZeroRowsPhpBug(): void {
		if ( PHP_VERSION_ID < 70300 ) {
			$this->markTestSkipped( 'Skipping due to PHP bug (#79664)' );
		}

		$this->assertQuery( 'CREATE TABLE t ( id INT )' );
		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals( 1, $this->engine->get_last_column_count() );
		$column_info = $this->engine->get_last_column_meta();
		$this->assertCount( 1, $column_info );
		$this->assertSame(
			array(
				'native_type'      => 'LONG',
				'pdo_type'         => PDO::PARAM_INT,
				'flags'            => array(),
				'table'            => 't',
				'name'             => 'id',
				'len'              => 11,
				'precision'        => 0,
				'sqlite:decl_type' => 'INTEGER',

				// Additional MySQLi metadata.
				'mysqli:orgname'   => 'id',
				'mysqli:orgtable'  => 't',
				'mysqli:db'        => 'wp',
				'mysqli:charsetnr' => 63,
				'mysqli:flags'     => 0,
				'mysqli:type'      => 3,
			),
			$column_info[0]
		);
	}

	public function testCheckConstraints(): void {
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

		// Valid data.
		$this->assertQuery(
			"INSERT INTO t (id, name, score, start_timestamp, end_timestamp, data)
			VALUES (1, 'test', 50, '2025-01-01 12:00:00', '2025-01-02 12:00:00', '{\"key\":\"value\"}')
		"
		);

		// Invalid ID.
		$exception = null;
		try {
			$this->assertQuery(
				"INSERT INTO t (id, name, score, start_timestamp, end_timestamp, data)
				VALUES (0, 'test', 50, '2025-01-01 12:00:00', '2025-01-02 12:00:00', '{\"key\":\"value\"}')
			"
			);
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: t_chk_1',
			$exception->getMessage()
		);

		// Invalid name.
		$exception = null;
		try {
			$this->assertQuery(
				"INSERT INTO t (id, name, score, start_timestamp, end_timestamp, data)
				VALUES (1, '', 50, '2025-01-01 12:00:00', '2025-01-02 12:00:00', '{\"key\":\"value\"}')
			"
			);
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: t_chk_2',
			$exception->getMessage()
		);

		// Invalid score.
		$exception = null;
		try {
			$this->assertQuery(
				"INSERT INTO t (id, name, score, start_timestamp, end_timestamp, data)
				VALUES (1, 'test', 100, '2025-01-01 12:00:00', '2025-01-02 12:00:00', '{\"key\":\"value\"}')
			"
			);
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: t_chk_3',
			$exception->getMessage()
		);

		// Invalid data.
		$exception = null;
		try {
			$this->assertQuery(
				"INSERT INTO t (id, name, score, start_timestamp, end_timestamp, data)
				VALUES (1, 'test', 50, '2025-01-01 12:00:00', '2025-01-02 12:00:00', 'invalid JSON')
			"
			);
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: t_chk_4',
			$exception->getMessage()
		);

		// Invalid c1.
		$exception = null;
		try {
			$this->assertQuery(
				"INSERT INTO t (id, name, score, start_timestamp, end_timestamp, data)
				VALUES (11, 'test', 50, '2025-01-01 12:00:00', '2025-01-02 12:00:00', '{\"key\":\"value\"}')
			"
			);
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: c1',
			$exception->getMessage()
		);

		// Invalid c2.
		$exception = null;
		try {
			$this->assertQuery(
				"INSERT INTO t (id, name, score, start_timestamp, end_timestamp, data)
				VALUES (1, 'test', 50, '2025-01-02 12:00:00', '2025-01-01 12:00:00', '{\"key\":\"value\"}')
			"
			);
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: c2',
			$exception->getMessage()
		);

		// Invalid c3.
		$exception = null;
		try {
			$this->assertQuery(
				"INSERT INTO t (id, name, score, start_timestamp, end_timestamp, data)
				VALUES (1, 'test', 50, '2025-01-01 12:00:00', '2025-01-02 12:00:00', '{\"key\":\"a-very-long-value\"}')
			"
			);
		} catch ( WP_SQLite_Driver_Exception $e ) {
			$exception = $e;
		}
		$this->assertNotNull( $exception );
		$this->assertSame(
			'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: c3',
			$exception->getMessage()
		);

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int NOT NULL,',
					'  `name` varchar(255) NOT NULL,',
					'  `score` double NOT NULL,',
					'  `data` json DEFAULT NULL,',
					'  `start_timestamp` timestamp NOT NULL,',
					'  `end_timestamp` timestamp NOT NULL,',

					// The of the check expressions below is not 100% matching MySQL,
					// because in MySQL the expressions are parsed and normalized.
					'  CONSTRAINT `c1` CHECK ( id < 10 ),',
					'  CONSTRAINT `c2` CHECK ( start_timestamp < end_timestamp ),',
					'  CONSTRAINT `c3` CHECK ( length ( data ) < 20 ),',
					'  CONSTRAINT `t_chk_1` CHECK ( id > 0 ),',
					"  CONSTRAINT `t_chk_2` CHECK ( name != '' ),",
					'  CONSTRAINT `t_chk_3` CHECK ( score > 0 AND score < 100 ),',
					'  CONSTRAINT `t_chk_4` CHECK ( json_valid ( data ) )',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);
	}

	public function testAlterTableAddCheckConstraint(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT)' );

		// ADD CONSTRAINT syntax.
		$this->assertQuery( 'ALTER TABLE t ADD CONSTRAINT c CHECK (id > 0)' );

		// ADD CHECK syntax.
		$this->assertQuery( 'ALTER TABLE t ADD CHECK (id < 10)' );

		// SHOW CREATE TABLE
		$this->assertQuery( 'SHOW CREATE TABLE t' );
		$result = $this->engine->get_query_results();
		$this->assertEquals(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int DEFAULT NULL,',
					'  CONSTRAINT `c` CHECK ( id > 0 ),',
					'  CONSTRAINT `t_chk_1` CHECK ( id < 10 )',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);

		// Insert valid data.
		$this->assertQuery( 'INSERT INTO t (id) VALUES (1)' );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );

		// Insert invalid data.
		$this->expectException( WP_SQLite_Driver_Exception::class );
		$this->expectExceptionMessage( 'SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: c' );
		$this->assertQuery( 'INSERT INTO t (id) VALUES (0)' );
	}

	public function testAlterTableDropCheckConstraint(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, CONSTRAINT c1 CHECK (id > 0), CONSTRAINT c2 CHECK (id < 10))' );

		// DROP CONSTRAINT syntax.
		$this->assertQuery( 'ALTER TABLE t DROP CONSTRAINT c1' );

		// DROP CHECK syntax.
		$this->assertQuery( 'ALTER TABLE t DROP CHECK c2' );

		// SHOW CREATE TABLE
		$this->assertQuery( 'SHOW CREATE TABLE t' );
		$result = $this->engine->get_query_results();
		$this->assertEquals(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int DEFAULT NULL',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);

		// Insert data that would violate the constraints.
		$this->assertQuery( 'INSERT INTO t (id) VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t (id) VALUES (100)' );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 2, $result );
	}

	public function testCheckConstraintNotEnforced(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT, CONSTRAINT c CHECK (id > 0) NOT ENFORCED)' );

		// Insert data that would violate the constraints.
		$this->assertQuery( 'INSERT INTO t (id) VALUES (0)' );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );

		// SHOW CREATE TABLE
		$this->assertQuery( 'SHOW CREATE TABLE t' );
		$result = $this->engine->get_query_results();
		$this->assertEquals(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int DEFAULT NULL,',
					'  CONSTRAINT `c` CHECK ( id > 0 ) /*!80016 NOT ENFORCED */',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);
	}

	public function testDynamicDatabaseName(): void {
		// Create a setter for the private property "$db_name".
		$set_db_name = Closure::bind(
			function ( $name ) {
				$this->main_db_name = $name;
			},
			$this->engine,
			WP_SQLite_Driver::class
		);

		// Default database name.
		$result = $this->assertQuery( 'SELECT schema_name FROM information_schema.schemata ORDER BY schema_name' );
		$this->assertEquals(
			array(
				(object) array( 'SCHEMA_NAME' => 'information_schema' ),
				(object) array( 'SCHEMA_NAME' => 'wp' ),
			),
			$result
		);

		// Change the database name.
		$set_db_name( 'wp_test_new' );
		$result = $this->assertQuery( 'SELECT schema_name FROM information_schema.schemata ORDER BY schema_name' );
		$this->assertEquals(
			array(
				(object) array( 'SCHEMA_NAME' => 'information_schema' ),
				(object) array( 'SCHEMA_NAME' => 'wp_test_new' ),
			),
			$result
		);

		// Ensure it works with table aliases.
		$result = $this->assertQuery( 'SELECT s.schema_name FROM information_schema.schemata AS s' );
		$this->assertEquals(
			array(
				(object) array( 'SCHEMA_NAME' => 'information_schema' ),
				(object) array( 'SCHEMA_NAME' => 'wp_test_new' ),
			),
			$result
		);
	}

	public function testDynamicDatabaseNameComplexScenario(): void {
		// Create a setter for the private property "$db_name".
		$set_db_name = Closure::bind(
			function ( $name ) {
				$this->main_db_name = $name;
			},
			$this->engine,
			WP_SQLite_Driver::class
		);

		$this->assertQuery( 'CREATE TABLE t (id INT, db_name TEXT)' );
		$this->assertQuery( 'INSERT INTO t (id, db_name) VALUES (1, "wp")' );
		$this->assertQuery( 'INSERT INTO t (id, db_name) VALUES (2, "wp_test_new")' );
		$this->assertQuery( 'INSERT INTO t (id, db_name) VALUES (3, "other")' );

		$set_db_name( 'wp_test_new' );

		$result = $this->assertQuery(
			"SELECT sub.id, sub.table_schema, sub.table_name, sub.column_name
			FROM (
				SELECT * FROM information_schema.columns c
				JOIN t ON t.db_name = CONCAT(COALESCE(c.table_schema, 'default'), '')
				JOIN information_schema.schemata s ON s.schema_name = c.table_schema
				WHERE c.table_name = 't'
			) sub
			ORDER BY ordinal_position"
		);
		$this->assertEquals(
			array(
				(object) array(
					'id'           => '2',
					'TABLE_SCHEMA' => 'wp_test_new',
					'TABLE_NAME'   => 't',
					'COLUMN_NAME'  => 'id',
				),
				(object) array(
					'id'           => '2',
					'TABLE_SCHEMA' => 'wp_test_new',
					'TABLE_NAME'   => 't',
					'COLUMN_NAME'  => 'db_name',
				),
			),
			$result
		);
	}

	public function testDynamicDatabaseNameWithWildcards(): void {
		// Create a setter for the private property "$db_name".
		$set_db_name = Closure::bind(
			function ( $name ) {
				$this->main_db_name = $name;
			},
			$this->engine,
			WP_SQLite_Driver::class
		);

		// Default database name.
		$result = $this->assertQuery(
			'SELECT * FROM information_schema.schemata s'
		);
		$this->assertEquals( 'information_schema', $result[0]->SCHEMA_NAME );
		$this->assertEquals( 'wp', $result[1]->SCHEMA_NAME );

		// Default database name.
		$set_db_name( 'wp_test_new' );
		$result = $this->assertQuery(
			'SELECT s.*
			FROM information_schema.schemata s
			LEFT JOIN information_schema.tables t ON t.table_schema = s.schema_name
			ORDER BY s.schema_name'
		);
		$this->assertEquals( 'information_schema', $result[0]->SCHEMA_NAME );
		$this->assertEquals( 'wp_test_new', $result[1]->SCHEMA_NAME );
	}

	public function testDynamicDatabaseNameWithUseStatement(): void {
		// Ensure "information_schema.tables" is empty.
		$this->assertQuery( 'DROP TABLE _options, _dates' );
		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables' );
		$this->assertCount( 0, $result );

		// Create a "tables" table in the "wp" database.
		$this->assertQuery( 'CREATE TABLE tables (id INT)' );
		$this->assertQuery( 'INSERT INTO tables (id) VALUES (1), (2)' );

		// Now, unqualified "tables" refers to the "wp.tables".
		$result = $this->assertQuery( 'SELECT * FROM tables' );
		$this->assertCount( 2, $result );
		$this->assertEquals( array( (object) array( 'id' => '1' ), (object) array( 'id' => '2' ) ), $result );

		// Qualified references should work as well.
		$result = $this->assertQuery( 'SELECT * FROM wp.tables' );
		$this->assertCount( 2, $result );
		$this->assertEquals( array( (object) array( 'id' => '1' ), (object) array( 'id' => '2' ) ), $result );

		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'tables', $result[0]->TABLE_NAME );

		// Switch to the "information_schema" database.
		$this->assertQuery( 'USE information_schema' );

		// Now, unqualified "tables" refers to the "information_schema.tables".
		$result = $this->assertQuery( 'SELECT * FROM tables' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'tables', $result[0]->TABLE_NAME );

		// Qualified references should still work.
		$result = $this->assertQuery( 'SELECT * FROM wp.tables' );
		$this->assertCount( 2, $result );
		$this->assertEquals( array( (object) array( 'id' => '1' ), (object) array( 'id' => '2' ) ), $result );

		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'tables', $result[0]->TABLE_NAME );

		// Switch back to the "wp" database.
		$this->assertQuery( 'USE wp' );

		// Now, unqualified "tables" refers to the "wp.tables".
		$result = $this->assertQuery( 'SELECT * FROM tables' );
		$this->assertCount( 2, $result );
		$this->assertEquals( array( (object) array( 'id' => '1' ), (object) array( 'id' => '2' ) ), $result );

		// Qualified references should still work.
		$result = $this->assertQuery( 'SELECT * FROM wp.tables' );
		$this->assertCount( 2, $result );
		$this->assertEquals( array( (object) array( 'id' => '1' ), (object) array( 'id' => '2' ) ), $result );

		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'tables', $result[0]->TABLE_NAME );
	}

	public function testCastExpression(): void {
		$result = $this->assertQuery(
			"SELECT
				CAST('abc' AS BINARY) AS expr_1,
				CAST('abc' AS BINARY(2)) AS expr_2,
				CAST('abc' AS CHAR)  AS expr_3,
				CAST('abc' AS CHAR(2) CHARACTER SET utf8 BINARY)AS expr_4,
				CAST('abc' AS NCHAR)AS expr_5,
				CAST('abc' AS NATIONAL CHAR (2)) AS expr_6,
				CAST('-10' AS SIGNED) AS expr_7,
				CAST('-10' AS UNSIGNED INT) AS expr_8,
				CAST('2025-10-05 14:05:28' AS DATE) AS expr_9,
				CAST('2025-10-05 14:05:28' AS TIME) AS expr_10,
				CAST('2025-10-05 14:05:28' AS DATETIME) AS expr_11,
				CAST('123.456' AS DECIMAL(10,1)) AS expr_12,
				CAST('123.456' AS FLOAT) AS expr_13,
				CAST('123.456' AS REAL) AS expr_14,
				CAST('123.456' AS DOUBLE) AS expr_15,
				CAST('{\"name\":\"value\"}' AS JSON) AS expr_16
			"
		);

		$this->assertEquals(
			array(
				(object) array(
					'expr_1'  => 'abc',
					'expr_2'  => 'abc',                  // 'ab' In MySQL
					'expr_3'  => 'abc',
					'expr_4'  => 'abc',                 // 'ab' In MySQL
					'expr_5'  => 'abc',
					'expr_6'  => 'abc',                 // 'ab' In MySQL
					'expr_7'  => '-10',
					'expr_8'  => '-10',                 // 18446744073709551606 in MySQL
					'expr_9'  => '2025-10-05 14:05:28', // 2025-10-05 in MySQL
					'expr_10' => '2025-10-05 14:05:28', // 14:05:28 in MySQL
					'expr_11' => '2025-10-05 14:05:28',
					'expr_12' => '123.456',             // 123.5 in MySQL
					'expr_13' => '123.456',
					'expr_14' => '123.456',
					'expr_15' => '123.456',
					'expr_16' => '{"name":"value"}',
				),
			),
			$result
		);
	}

	public function testInsertIntoSetSyntax(): void {
		$this->assertQuery(
			'CREATE TABLE t (
			  id INT PRIMARY KEY AUTO_INCREMENT,
			  name VARCHAR(255) NOT NULL,
			  value TEXT
			)'
		);

		$this->assertQuery( "INSERT INTO t SET name = 'one'" );
		$this->assertQuery( "INSERT INTO t SET name = 'two', value = 'two-value'" );
		$this->assertQuery( "INSERT INTO t SET value = 'three-value', name = 'three'" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals(
			array(
				(object) array(
					'id'    => '1',
					'name'  => 'one',
					'value' => null,
				),
				(object) array(
					'id'    => '2',
					'name'  => 'two',
					'value' => 'two-value',
				),
				(object) array(
					'id'    => '3',
					'name'  => 'three',
					'value' => 'three-value',
				),
			),
			$result
		);
	}

	public function testInsertIntoSetSyntaxInNonStrictMode(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		$this->assertQuery(
			'CREATE TABLE t (
			  id INT PRIMARY KEY AUTO_INCREMENT,
			  created_at DATETIME NOT NULL,
			  name VARCHAR(255) NOT NULL,
			  value TEXT,
			  score INT NOT NULL
			)'
		);

		$this->assertQuery( "INSERT INTO t SET name = 'one'" );
		$this->assertQuery( "INSERT INTO t SET name = 'two', value = 'two-value'" );
		$this->assertQuery( "INSERT INTO t SET value = 'three-value', name = 'three'" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals(
			array(
				(object) array(
					'id'         => '1',
					'created_at' => '0000-00-00 00:00:00',
					'name'       => 'one',
					'value'      => null,
					'score'      => '0',
				),
				(object) array(
					'id'         => '2',
					'created_at' => '0000-00-00 00:00:00',
					'name'       => 'two',
					'value'      => 'two-value',
					'score'      => '0',
				),
				(object) array(
					'id'         => '3',
					'created_at' => '0000-00-00 00:00:00',
					'name'       => 'three',
					'value'      => 'three-value',
					'score'      => '0',
				),
			),
			$result
		);
	}

	public function testFullyQualifiedTableName(): void {
		// Ensure "information_schema.tables" is empty.
		$this->assertQuery( 'DROP TABLE _options, _dates' );
		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables' );
		$this->assertCount( 0, $result );

		// Switch to the "information_schema" database.
		$this->assertQuery( 'USE information_schema' );

		// CREATE TABLE
		$this->assertQuery( 'CREATE TABLE wp.t (id INT PRIMARY KEY)' );
		$result = $this->assertQuery( 'SHOW TABLES FROM wp' );
		$this->assertCount( 1, $result );

		// INSERT
		$this->assertQuery( 'INSERT INTO wp.t (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM wp.t' );
		$this->assertEquals( array( (object) array( 'id' => '1' ) ), $result );

		// SELECT
		$result = $this->assertQuery( 'SELECT * FROM wp.t' );
		$this->assertEquals( array( (object) array( 'id' => '1' ) ), $result );

		// UPDATE
		$this->assertQuery( 'UPDATE wp.t SET id = 2' );
		$result = $this->assertQuery( 'SELECT * FROM wp.t' );
		$this->assertEquals( array( (object) array( 'id' => '2' ) ), $result );

		// DELETE
		$this->assertQuery( 'DELETE FROM wp.t' );
		$result = $this->assertQuery( 'SELECT * FROM wp.t' );
		$this->assertCount( 0, $result );

		// TRUNCATE TABLE
		$this->assertQuery( 'INSERT INTO wp.t (id) VALUES (1)' );
		$this->assertQuery( 'TRUNCATE TABLE wp.t' );
		$result = $this->assertQuery( 'SELECT * FROM wp.t' );
		$this->assertCount( 0, $result );

		// SHOW CREATE TABLE
		$result = $this->assertQuery( 'SHOW CREATE TABLE wp.t' );
		$this->assertEquals(
			array(
				(object) array(
					'Table'        => 't',
					'Create Table' => implode(
						"\n",
						array(
							'CREATE TABLE `t` (',
							'  `id` int NOT NULL,',
							'  PRIMARY KEY (`id`)',
							') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
						)
					),
				),
			),
			$result
		);

		// SHOW COLUMNS
		$result = $this->assertQuery( 'SHOW COLUMNS FROM wp.t' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$result
		);

		// SHOW COLUMNS with both qualified table name and "FROM database" clause.
		// In case both are present, the "FROM database" clause takes precedence.
		$result = $this->assertQuery( 'SHOW COLUMNS FROM information_schema.t FROM wp' );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'id',
					'Type'    => 'int',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => null,
					'Extra'   => '',
				),
			),
			$result
		);

		// SHOW INDEXES
		$result = $this->assertQuery( 'SHOW INDEXES FROM wp.t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'PRIMARY', $result[0]->Key_name );

		// SHOW INDEXES with both qualified table name and "FROM database" clause.
		// In case both are present, the "FROM database" clause takes precedence.
		$result = $this->assertQuery( 'SHOW INDEXES FROM information_schema.t FROM wp' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'PRIMARY', $result[0]->Key_name );

		// DESCRIBE
		$result = $this->assertQuery( 'DESCRIBE wp.t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'id', $result[0]->Field );
		$this->assertEquals( 'int', $result[0]->Type );
		$this->assertEquals( 'NO', $result[0]->Null );
		$this->assertEquals( 'PRI', $result[0]->Key );
		$this->assertEquals( null, $result[0]->Default );
		$this->assertEquals( '', $result[0]->Extra );

		// SHOW TABLES
		$result = $this->assertQuery( 'SHOW TABLES FROM wp' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 't', $result[0]->Tables_in_wp );

		// SHOW TABLE STATUS
		$result = $this->assertQuery( 'SHOW TABLE STATUS FROM wp' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 't', $result[0]->Name );

		// ALTER TABLE
		$this->assertQuery( 'ALTER TABLE wp.t ADD COLUMN name VARCHAR(255)' );
		$result = $this->assertQuery( 'SHOW COLUMNS FROM wp.t' );
		$this->assertCount( 2, $result );

		// CREATE INDEX
		$this->assertQuery( 'CREATE INDEX idx_name ON wp.t (name)' );
		$result = $this->assertQuery( 'SHOW INDEXES FROM wp.t' );
		$this->assertCount( 2, $result );
		$this->assertEquals( 'idx_name', $result[1]->Key_name );

		// DROP INDEX
		$this->assertQuery( 'DROP INDEX idx_name ON wp.t' );
		$result = $this->assertQuery( 'SHOW INDEXES FROM wp.t' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'PRIMARY', $result[0]->Key_name );

		// LOCK TABLE
		$this->assertQuery( 'LOCK TABLES wp.t READ' );

		// UNLOCK TABLE
		$this->assertQuery( 'UNLOCK TABLES' );

		// ANALYZE TABLE
		$this->assertQuery( 'ANALYZE TABLE wp.t' );

		// CHECK TABLE
		$this->assertQuery( 'CHECK TABLE wp.t' );

		// OPTIMIZE TABLE
		$this->assertQuery( 'OPTIMIZE TABLE wp.t' );

		// REPAIR TABLE
		$this->assertQuery( 'REPAIR TABLE wp.t' );

		// DROP TABLE
		$this->assertQuery( 'DROP TABLE wp.t' );
		$result = $this->assertQuery( 'SHOW TABLES FROM wp' );
		$this->assertCount( 0, $result );
	}

	public function testWriteWithUsageOfInformationSchemaTables(): void {
		// Ensure "information_schema.tables" is empty.
		$this->assertQuery( 'DROP TABLE _options, _dates' );
		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables' );
		$this->assertCount( 0, $result );

		// Create a table.
		$this->assertQuery( 'CREATE TABLE t (id INT, value VARCHAR(255))' );

		// INSERT with SELECT from information schema.
		$this->assertQuery( 'INSERT INTO t (id, value) SELECT 1, table_name FROM information_schema.tables' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 1, $result );
		$this->assertEquals(
			array(
				(object) array(
					'id'    => '1',
					'value' => 't',
				),
			),
			$result
		);

		// INSERT with subselect from information schema.
		$this->assertQuery( 'INSERT INTO t (id, value) SELECT 2, table_name FROM (SELECT table_name FROM information_schema.tables)' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 2, $result );
		$this->assertEquals(
			array(
				(object) array(
					'id'    => '1',
					'value' => 't',
				),
				(object) array(
					'id'    => '2',
					'value' => 't',
				),
			),
			$result
		);

		// INSERT with JOIN on information schema.
		$this->assertQuery(
			'INSERT INTO t (id, value)
			SELECT 3, it.table_name
			FROM information_schema.schemata s
			JOIN information_schema.tables it ON s.schema_name = it.table_schema'
		);
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertCount( 3, $result );
		$this->assertEquals(
			array(
				(object) array(
					'id'    => '1',
					'value' => 't',
				),
				(object) array(
					'id'    => '2',
					'value' => 't',
				),
				(object) array(
					'id'    => '3',
					'value' => 't',
				),
			),
			$result
		);

		// TODO: UPDATE with JOIN on information schema is not supported yet.

		// DELETE with JOIN on information schema.
		$this->assertQuery( 'UPDATE t SET value = "other" WHERE id > 1' );
		$this->assertQuery( 'DELETE t FROM t JOIN information_schema.tables it ON t.value = it.table_name' );
		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertEquals(
			array(
				(object) array(
					'id'    => '2',
					'value' => 'other',
				),
				(object) array(
					'id'    => '3',
					'value' => 'other',
				),
			),
			$result
		);
	}

	public function testNonEmptyColumnMeta(): void {
		$this->assertQuery( 'CREATE TABLE t (id INT PRIMARY KEY)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );

		// SELECT
		$this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( 1, $this->engine->get_last_column_count() );
		$this->assertSame( 'id', $this->engine->get_last_column_meta()[0]['name'] );

		// SHOW COLLATION
		$this->assertQuery( 'SHOW COLLATION' );
		$this->assertSame( 7, $this->engine->get_last_column_count() );
		$this->assertSame( 'Collation', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Charset', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Id', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Default', $this->engine->get_last_column_meta()[3]['name'] );
		$this->assertSame( 'Compiled', $this->engine->get_last_column_meta()[4]['name'] );
		$this->assertSame( 'Sortlen', $this->engine->get_last_column_meta()[5]['name'] );
		$this->assertSame( 'Pad_attribute', $this->engine->get_last_column_meta()[6]['name'] );

		// SHOW DATABASES
		$this->assertQuery( 'SHOW DATABASES' );
		$this->assertSame( 1, $this->engine->get_last_column_count() );
		$this->assertSame( 'Database', $this->engine->get_last_column_meta()[0]['name'] );

		// SHOW CREATE TABLE
		$this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertSame( 2, $this->engine->get_last_column_count() );
		$this->assertSame( 'Table', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Create Table', $this->engine->get_last_column_meta()[1]['name'] );

		// SHOW TABLE STATUS
		$this->assertQuery( 'SHOW TABLE STATUS' );
		$this->assertSame( 18, $this->engine->get_last_column_count() );
		$this->assertSame( 'Name', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Engine', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Version', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Row_format', $this->engine->get_last_column_meta()[3]['name'] );
		$this->assertSame( 'Rows', $this->engine->get_last_column_meta()[4]['name'] );
		$this->assertSame( 'Avg_row_length', $this->engine->get_last_column_meta()[5]['name'] );
		$this->assertSame( 'Data_length', $this->engine->get_last_column_meta()[6]['name'] );
		$this->assertSame( 'Max_data_length', $this->engine->get_last_column_meta()[7]['name'] );
		$this->assertSame( 'Index_length', $this->engine->get_last_column_meta()[8]['name'] );
		$this->assertSame( 'Data_free', $this->engine->get_last_column_meta()[9]['name'] );
		$this->assertSame( 'Auto_increment', $this->engine->get_last_column_meta()[10]['name'] );
		$this->assertSame( 'Create_time', $this->engine->get_last_column_meta()[11]['name'] );
		$this->assertSame( 'Update_time', $this->engine->get_last_column_meta()[12]['name'] );
		$this->assertSame( 'Check_time', $this->engine->get_last_column_meta()[13]['name'] );
		$this->assertSame( 'Collation', $this->engine->get_last_column_meta()[14]['name'] );
		$this->assertSame( 'Checksum', $this->engine->get_last_column_meta()[15]['name'] );
		$this->assertSame( 'Create_options', $this->engine->get_last_column_meta()[16]['name'] );
		$this->assertSame( 'Comment', $this->engine->get_last_column_meta()[17]['name'] );

		// SHOW TABLES
		$this->assertQuery( 'SHOW TABLES' );
		$this->assertSame( 1, $this->engine->get_last_column_count() );
		$this->assertSame( 'Tables_in_wp', $this->engine->get_last_column_meta()[0]['name'] );

		// SHOW FULL TABLES
		$this->assertQuery( 'SHOW FULL TABLES' );
		$this->assertSame( 2, $this->engine->get_last_column_count() );
		$this->assertSame( 'Tables_in_wp', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Table_type', $this->engine->get_last_column_meta()[1]['name'] );

		// SHOW COLUMNS
		$this->assertQuery( 'SHOW COLUMNS FROM t' );
		$this->assertSame( 6, $this->engine->get_last_column_count() );
		$this->assertSame( 'Field', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Type', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Null', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Key', $this->engine->get_last_column_meta()[3]['name'] );
		$this->assertSame( 'Default', $this->engine->get_last_column_meta()[4]['name'] );
		$this->assertSame( 'Extra', $this->engine->get_last_column_meta()[5]['name'] );

		// SHOW INDEX
		$this->assertQuery( 'SHOW INDEX FROM t' );
		$this->assertSame( 15, $this->engine->get_last_column_count() );
		$this->assertSame( 'Table', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Non_unique', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Key_name', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Seq_in_index', $this->engine->get_last_column_meta()[3]['name'] );
		$this->assertSame( 'Column_name', $this->engine->get_last_column_meta()[4]['name'] );
		$this->assertSame( 'Collation', $this->engine->get_last_column_meta()[5]['name'] );
		$this->assertSame( 'Cardinality', $this->engine->get_last_column_meta()[6]['name'] );
		$this->assertSame( 'Sub_part', $this->engine->get_last_column_meta()[7]['name'] );
		$this->assertSame( 'Packed', $this->engine->get_last_column_meta()[8]['name'] );
		$this->assertSame( 'Null', $this->engine->get_last_column_meta()[9]['name'] );
		$this->assertSame( 'Index_type', $this->engine->get_last_column_meta()[10]['name'] );
		$this->assertSame( 'Comment', $this->engine->get_last_column_meta()[11]['name'] );
		$this->assertSame( 'Index_comment', $this->engine->get_last_column_meta()[12]['name'] );
		$this->assertSame( 'Visible', $this->engine->get_last_column_meta()[13]['name'] );
		$this->assertSame( 'Expression', $this->engine->get_last_column_meta()[14]['name'] );

		// SHOW GRANTS
		$this->assertQuery( 'SHOW GRANTS' );
		$this->assertSame( 1, $this->engine->get_last_column_count() );
		$this->assertSame( 'Grants for root@%', $this->engine->get_last_column_meta()[0]['name'] );

		// SHOW VARIABLES
		$this->assertQuery( 'SHOW VARIABLES' );
		$this->assertSame( 2, $this->engine->get_last_column_count() );
		$this->assertSame( 'Variable_name', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Value', $this->engine->get_last_column_meta()[1]['name'] );

		// DESCRIBE/EXPLAIN
		$this->assertQuery( 'DESCRIBE t' );
		$this->assertSame( 6, $this->engine->get_last_column_count() );
		$this->assertSame( 'Field', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Type', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Null', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Key', $this->engine->get_last_column_meta()[3]['name'] );
		$this->assertSame( 'Default', $this->engine->get_last_column_meta()[4]['name'] );
		$this->assertSame( 'Extra', $this->engine->get_last_column_meta()[5]['name'] );

		// ANALYZE TABLE
		$this->assertQuery( 'ANALYZE TABLE t' );
		$this->assertSame( 4, $this->engine->get_last_column_count() );
		$this->assertSame( 'Table', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Op', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Msg_type', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Msg_text', $this->engine->get_last_column_meta()[3]['name'] );

		// CHECK TABLE
		$this->assertQuery( 'CHECK TABLE t' );
		$this->assertSame( 4, $this->engine->get_last_column_count() );
		$this->assertSame( 'Table', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Op', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Msg_type', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Msg_text', $this->engine->get_last_column_meta()[3]['name'] );

		// OPTIMIZE TABLE
		$this->assertQuery( 'OPTIMIZE TABLE t' );
		$this->assertSame( 4, $this->engine->get_last_column_count() );
		$this->assertSame( 'Table', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Op', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Msg_type', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Msg_text', $this->engine->get_last_column_meta()[3]['name'] );

		// REPAIR TABLE
		$this->assertQuery( 'REPAIR TABLE t' );
		$this->assertSame( 4, $this->engine->get_last_column_count() );
		$this->assertSame( 'Table', $this->engine->get_last_column_meta()[0]['name'] );
		$this->assertSame( 'Op', $this->engine->get_last_column_meta()[1]['name'] );
		$this->assertSame( 'Msg_type', $this->engine->get_last_column_meta()[2]['name'] );
		$this->assertSame( 'Msg_text', $this->engine->get_last_column_meta()[3]['name'] );
	}

	public function testEmptyColumnMeta(): void {
		// CREATE TABLE
		$this->assertQuery( 'CREATE TABLE t (id INT)' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// INSERT
		$this->assertQuery( 'INSERT INTO t (id) VALUES (1)' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// REPLACE
		$this->assertQuery( 'UPDATE t SET id = 1' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// DELETE
		$this->assertQuery( 'DELETE FROM t' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// TRUNCATE TABLE
		$this->assertQuery( 'TRUNCATE TABLE t' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// START TRANSACTION
		$this->assertQuery( 'START TRANSACTION' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// COMMIT
		$this->assertQuery( 'COMMIT' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// ROLLBACK
		$this->assertQuery( 'ROLLBACK' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// SAVEPOINT
		$this->assertQuery( 'SAVEPOINT s1' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// ROLLBACK TO SAVEPOINT
		$this->assertQuery( 'ROLLBACK TO SAVEPOINT s1' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// RELEASE SAVEPOINT
		$this->assertQuery( 'RELEASE SAVEPOINT s1' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// LOCK TABLE
		$this->assertQuery( 'LOCK TABLES t READ' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// UNLOCK TABLE
		$this->assertQuery( 'UNLOCK TABLES' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// ALTER TABLE
		$this->assertQuery( 'ALTER TABLE t ADD COLUMN name VARCHAR(255)' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// CREATE INDEX
		$this->assertQuery( 'CREATE INDEX idx_name ON t (name)' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// DROP INDEX
		$this->assertQuery( 'DROP INDEX idx_name ON t' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// DROP TABLE
		$this->assertQuery( 'DROP TABLE t' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// USE
		$this->assertQuery( 'USE wp' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );

		// SET
		$this->assertQuery( 'SET @my_var = 1' );
		$this->assertSame( 0, $this->engine->get_last_column_count() );
		$this->assertSame( array(), $this->engine->get_last_column_meta() );
	}

	public function testCastValuesOnInsert(): void {
		// INTEGER
		$this->assertQuery( 'CREATE TABLE t (value INT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2')" );
		$this->assertQuery( "INSERT INTO t VALUES ('3.0')" );

		$is_legacy_sqlite = version_compare( $this->engine->get_sqlite_version(), WP_PDO_MySQL_On_SQLite::MINIMUM_SQLITE_VERSION, '<' );
		if ( $is_legacy_sqlite ) {
			$this->assertQuery( "INSERT INTO t VALUES ('4.5')" );
			$this->assertQuery( 'INSERT INTO t VALUES (0x05)' );
			$this->assertQuery( "INSERT INTO t VALUES (x'06')" );
		} else {
			// TODO: These are supported in MySQL:
			$this->assertQueryError( "INSERT INTO t VALUES ('4.5')", 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store REAL value in INTEGER column t.value' );
			$this->assertQueryError( 'INSERT INTO t VALUES (0x05)', 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store BLOB value in INTEGER column t.value' );
			$this->assertQueryError( "INSERT INTO t VALUES (x'06')", 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store BLOB value in INTEGER column t.value' );
		}

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0', $result[1]->value );
		$this->assertSame( '1', $result[2]->value );
		$this->assertSame( '0', $result[3]->value );
		$this->assertSame( '1', $result[4]->value );
		$this->assertSame( '2', $result[5]->value );
		$this->assertSame( '3', $result[6]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// FLOAT
		$this->assertQuery( 'CREATE TABLE t (value FLOAT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t VALUES (2.34)' );
		$this->assertQuery( "INSERT INTO t VALUES ('3.45')" );
		$this->assertQuery( 'INSERT INTO t VALUES (4)' );
		$this->assertQuery( "INSERT INTO t VALUES ('5')" );

		// TODO: These are supported in MySQL:
		if ( $is_legacy_sqlite ) {
			$this->assertQuery( 'INSERT INTO t VALUES (0x06)' );
			$this->assertQuery( "INSERT INTO t VALUES (x'07')" );
		} else {
			$this->assertQueryError( 'INSERT INTO t VALUES (0x06)', 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store BLOB value in REAL column t.value' );
			$this->assertQueryError( "INSERT INTO t VALUES (x'07')", 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store BLOB value in REAL column t.value' );
		}

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[1]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '1.0' : '1', $result[2]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[3]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '1.0' : '1', $result[4]->value );
		$this->assertSame( '2.34', $result[5]->value );
		$this->assertSame( '3.45', $result[6]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '4.0' : '4', $result[7]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '5.0' : '5', $result[8]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// STRING
		$this->assertQuery( 'CREATE TABLE t (value TEXT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123.456)' );
		$this->assertQuery( "INSERT INTO t VALUES ('a')" );
		$this->assertQuery( 'INSERT INTO t VALUES (0x62)' );
		$this->assertQuery( "INSERT INTO t VALUES (x'63')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0', $result[1]->value );
		$this->assertSame( '1', $result[2]->value );
		$this->assertSame( '0', $result[3]->value );
		$this->assertSame( '123', $result[4]->value );
		$this->assertSame( '123.456', $result[5]->value );
		$this->assertSame( 'a', $result[6]->value );
		$this->assertSame( 'b', $result[7]->value );
		$this->assertSame( 'c', $result[8]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// BLOB
		$this->assertQuery( 'CREATE TABLE t (value BLOB)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123.456)' );
		$this->assertQuery( "INSERT INTO t VALUES ('a')" );
		$this->assertQuery( 'INSERT INTO t VALUES (0x62)' );
		$this->assertQuery( "INSERT INTO t VALUES (x'63')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0', $result[1]->value );
		$this->assertSame( '1', $result[2]->value );
		$this->assertSame( '0', $result[3]->value );
		$this->assertSame( '123', $result[4]->value );
		$this->assertSame( '123.456', $result[5]->value );
		$this->assertSame( 'a', $result[6]->value );
		$this->assertSame( 'b', $result[7]->value );
		$this->assertSame( 'c', $result[8]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// DATE
		$this->assertQuery( 'CREATE TABLE t (value DATE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQueryError( 'INSERT INTO t VALUES (TRUE)', "Incorrect date value: '1'" );
		$this->assertQueryError( 'INSERT INTO t VALUES (FALSE)', "Incorrect date value: '0'" );
		$this->assertQueryError( 'INSERT INTO t VALUES (0)', "Incorrect date value: '0'" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00.123456')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '2025-10-23', $result[1]->value );
		$this->assertSame( '2025-10-23', $result[2]->value );
		$this->assertSame( '2025-10-23', $result[3]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// TIME
		$this->assertQuery( 'CREATE TABLE t (value TIME)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123)' );
		$this->assertQuery( "INSERT INTO t VALUES ('18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('18:30:00.123456')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '12:00:00', $result[1]->value ); // TODO: 00:00:00 in MySQL
		$this->assertSame( '12:00:00', $result[2]->value ); // TODO: 00:00:01 in MySQL
		$this->assertSame( '12:00:00', $result[3]->value ); // TODO: 00:00:01 in MySQL
		$this->assertSame( '12:00:00', $result[4]->value ); // TODO: 00:01:23 in MySQL
		$this->assertSame( '18:30:00', $result[5]->value );
		$this->assertSame( '18:30:00', $result[6]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// DATETIME
		$this->assertQuery( 'CREATE TABLE t (value DATETIME)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQueryError( 'INSERT INTO t VALUES (FALSE)', "Incorrect datetime value: '0'" );
		$this->assertQueryError( 'INSERT INTO t VALUES (TRUE)', "Incorrect datetime value: '1'" );
		$this->assertQueryError( 'INSERT INTO t VALUES (0)', "Incorrect datetime value: '0'" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00.123456')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '2025-10-23 00:00:00', $result[1]->value );
		$this->assertSame( '2025-10-23 18:30:00', $result[2]->value );
		$this->assertSame( '2025-10-23 18:30:00', $result[3]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// TIMESTAMP
		$this->assertQuery( 'CREATE TABLE t (value TIMESTAMP)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQueryError( 'INSERT INTO t VALUES (FALSE)', "Incorrect timestamp value: '0'" );
		$this->assertQueryError( 'INSERT INTO t VALUES (TRUE)', "Incorrect timestamp value: '1'" );
		$this->assertQueryError( 'INSERT INTO t VALUES (0)', "Incorrect timestamp value: '0'" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00.123456')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '2025-10-23 00:00:00', $result[1]->value );
		$this->assertSame( '2025-10-23 18:30:00', $result[2]->value );
		$this->assertSame( '2025-10-23 18:30:00', $result[3]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// YEAR
		$this->assertQuery( 'CREATE TABLE t (value YEAR)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00.123456')" );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t VALUES (50)' );
		$this->assertQuery( 'INSERT INTO t VALUES (70)' );
		$this->assertQuery( 'INSERT INTO t VALUES (99)' );
		$this->assertQueryError( 'INSERT INTO t VALUES (-1)', "Out of range value: '-1'" );
		$this->assertQueryError( 'INSERT INTO t VALUES (1900)', "Out of range value: '1900'" );
		$this->assertQueryError( 'INSERT INTO t VALUES (2156)', "Out of range value: '2156'" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0000', $result[1]->value );
		$this->assertSame( '2001', $result[2]->value );
		$this->assertSame( '2025', $result[3]->value );
		$this->assertSame( '2025', $result[4]->value );
		$this->assertSame( '2025', $result[5]->value );
		$this->assertSame( '2025', $result[6]->value );
		$this->assertSame( '2001', $result[7]->value );
		$this->assertSame( '2050', $result[8]->value );
		$this->assertSame( '1970', $result[9]->value );
		$this->assertSame( '1999', $result[10]->value );
		$this->assertQuery( 'DROP TABLE t' );
	}

	public function testCastValuesOnInsertInNonStrictMode(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// INTEGER
		$this->assertQuery( 'CREATE TABLE t (value INT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2')" );
		$this->assertQuery( "INSERT INTO t VALUES ('3.0')" );
		$this->assertQuery( "INSERT INTO t VALUES ('4.5')" );
		$this->assertQuery( 'INSERT INTO t VALUES (0x05)' );
		$this->assertQuery( "INSERT INTO t VALUES (x'06')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0', $result[1]->value );
		$this->assertSame( '1', $result[2]->value );
		$this->assertSame( '0', $result[3]->value );
		$this->assertSame( '1', $result[4]->value );
		$this->assertSame( '2', $result[5]->value );
		$this->assertSame( '3', $result[6]->value );
		$this->assertSame( '4', $result[7]->value ); // TODO: 5 in MySQL
		$this->assertSame( '0', $result[8]->value ); // TODO: 5 in MySQL
		$this->assertSame( '0', $result[9]->value ); // TODO: 6 in MySQL
		$this->assertQuery( 'DROP TABLE t' );

		// FLOAT
		$this->assertQuery( 'CREATE TABLE t (value FLOAT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t VALUES (2.34)' );
		$this->assertQuery( "INSERT INTO t VALUES ('3.45')" );
		$this->assertQuery( 'INSERT INTO t VALUES (4)' );
		$this->assertQuery( "INSERT INTO t VALUES ('5')" );
		$this->assertQuery( 'INSERT INTO t VALUES (0x06)' );
		$this->assertQuery( "INSERT INTO t VALUES (x'07')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[1]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '1.0' : '1', $result[2]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[3]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '1.0' : '1', $result[4]->value );
		$this->assertSame( '2.34', $result[5]->value );
		$this->assertSame( '3.45', $result[6]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '4.0' : '4', $result[7]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '5.0' : '5', $result[8]->value );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[9]->value );  // TODO: 6 in MySQL
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $result[10]->value ); // TODO: 7 in MySQL
		$this->assertQuery( 'DROP TABLE t' );

		// STRING
		$this->assertQuery( 'CREATE TABLE t (value TEXT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123.456)' );
		$this->assertQuery( "INSERT INTO t VALUES ('a')" );
		$this->assertQuery( 'INSERT INTO t VALUES (0x62)' );
		$this->assertQuery( "INSERT INTO t VALUES (x'63')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0', $result[1]->value );
		$this->assertSame( '1', $result[2]->value );
		$this->assertSame( '0', $result[3]->value );
		$this->assertSame( '123', $result[4]->value );
		$this->assertSame( '123.456', $result[5]->value );
		$this->assertSame( 'a', $result[6]->value );
		$this->assertSame( 'b', $result[7]->value );
		$this->assertSame( 'c', $result[8]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// BLOB
		$this->assertQuery( 'CREATE TABLE t (value BLOB)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123.456)' );
		$this->assertQuery( "INSERT INTO t VALUES ('a')" );
		$this->assertQuery( 'INSERT INTO t VALUES (0x62)' );
		$this->assertQuery( "INSERT INTO t VALUES (x'63')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0', $result[1]->value );
		$this->assertSame( '1', $result[2]->value );
		$this->assertSame( '0', $result[3]->value );
		$this->assertSame( '123', $result[4]->value );
		$this->assertSame( '123.456', $result[5]->value );
		$this->assertSame( 'a', $result[6]->value );
		$this->assertSame( 'b', $result[7]->value );
		$this->assertSame( 'c', $result[8]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// DATE
		$this->assertQuery( 'CREATE TABLE t (value DATE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00.123456')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0000-00-00', $result[1]->value );
		$this->assertSame( '0000-00-00', $result[2]->value );
		$this->assertSame( '0000-00-00', $result[3]->value );
		$this->assertSame( '2025-10-23', $result[4]->value );
		$this->assertSame( '2025-10-23', $result[5]->value );
		$this->assertSame( '2025-10-23', $result[6]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// TIME
		$this->assertQuery( 'CREATE TABLE t (value TIME)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123)' );
		$this->assertQuery( "INSERT INTO t VALUES ('18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('18:30:00.123456')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '12:00:00', $result[1]->value ); // TODO: 00:00:00 in MySQL
		$this->assertSame( '12:00:00', $result[2]->value ); // TODO: 00:00:01 in MySQL
		$this->assertSame( '12:00:00', $result[3]->value ); // TODO: 00:00:00 in MySQL
		$this->assertSame( '12:00:00', $result[4]->value ); // TODO: 00:01:23 in MySQL
		$this->assertSame( '18:30:00', $result[5]->value );
		$this->assertSame( '18:30:00', $result[6]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// DATETIME
		$this->assertQuery( 'CREATE TABLE t (value DATETIME)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00.123456')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0000-00-00 00:00:00', $result[1]->value );
		$this->assertSame( '0000-00-00 00:00:00', $result[2]->value );
		$this->assertSame( '0000-00-00 00:00:00', $result[3]->value );
		$this->assertSame( '2025-10-23 00:00:00', $result[4]->value );
		$this->assertSame( '2025-10-23 18:30:00', $result[5]->value );
		$this->assertSame( '2025-10-23 18:30:00', $result[6]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// TIMESTAMP
		$this->assertQuery( 'CREATE TABLE t (value TIMESTAMP)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (0)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00.123456')" );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0000-00-00 00:00:00', $result[1]->value );
		$this->assertSame( '0000-00-00 00:00:00', $result[2]->value );
		$this->assertSame( '0000-00-00 00:00:00', $result[3]->value );
		$this->assertSame( '2025-10-23 00:00:00', $result[4]->value );
		$this->assertSame( '2025-10-23 18:30:00', $result[5]->value );
		$this->assertSame( '2025-10-23 18:30:00', $result[6]->value );
		$this->assertQuery( 'DROP TABLE t' );

		// YEAR
		$this->assertQuery( 'CREATE TABLE t (value YEAR)' );
		$this->assertQuery( 'INSERT INTO t VALUES (NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (FALSE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (TRUE)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00')" );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-10-23 18:30:00.123456')" );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );
		$this->assertQuery( 'INSERT INTO t VALUES (50)' );
		$this->assertQuery( 'INSERT INTO t VALUES (70)' );
		$this->assertQuery( 'INSERT INTO t VALUES (99)' );
		$this->assertQuery( 'INSERT INTO t VALUES (-1)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1900)' );
		$this->assertQuery( 'INSERT INTO t VALUES (2156)' );

		$result = $this->assertQuery( 'SELECT * FROM t' );
		$this->assertSame( null, $result[0]->value );
		$this->assertSame( '0000', $result[1]->value );
		$this->assertSame( '2001', $result[2]->value );
		$this->assertSame( '2025', $result[3]->value );
		$this->assertSame( '2025', $result[4]->value );
		$this->assertSame( '2025', $result[5]->value );
		$this->assertSame( '2025', $result[6]->value );
		$this->assertSame( '2001', $result[7]->value );
		$this->assertSame( '2050', $result[8]->value );
		$this->assertSame( '1970', $result[9]->value );
		$this->assertSame( '1999', $result[10]->value );
		$this->assertSame( '0000', $result[11]->value );
		$this->assertSame( '0000', $result[12]->value );
		$this->assertSame( '0000', $result[13]->value );
		$this->assertQuery( 'DROP TABLE t' );
	}

	public function testCastNotNullValuesOnInsert(): void {
		$this->assertQuery( 'CREATE TABLE t (value INT NOT NULL)' );

		// Strict mode:
		$this->assertQueryError( 'INSERT INTO t VALUES (NULL)', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );
		$this->assertQueryError( 'INSERT INTO t SET value = NULL', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );
		$this->assertQueryError( 'INSERT INTO t SELECT NULL', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );
		$this->assertQueryError( 'INSERT INTO t VALUES ((SELECT NULL))', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );

		// Non-strict mode:
		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$this->assertQueryError( 'INSERT INTO t VALUES (NULL)', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );
		$this->assertQueryError( 'INSERT INTO t SET value = NULL', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );
		$this->assertQuery( 'INSERT INTO t SELECT NULL' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );
		$this->assertQueryError( 'INSERT INTO t VALUES ((SELECT NULL))', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );
	}

	public function testCastNotNullValuesOnUpdate(): void {
		$this->assertQuery( 'CREATE TABLE t (value INT NOT NULL)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );

		// Strict mode:
		$this->assertQueryError( 'UPDATE t SET value = NULL', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );
		$this->assertQueryError( 'UPDATE t SET value = (SELECT NULL)', 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: t.value' );

		// Non-strict mode:
		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = (SELECT NULL)' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );
		$this->assertQuery( 'DROP TABLE t' );
	}

	public function testCastValuesOnDuplicateKeyUpdate(): void {
		$this->assertQuery( 'CREATE TABLE t (value TEXT UNIQUE)' );
		$this->assertQuery( "INSERT INTO t VALUES ('test')" );

		// Ensure that type casting is applied to ON DUPLICATE KEY UPDATE clause.
		$this->assertQuery( "INSERT INTO t VALUES ('test') ON DUPLICATE KEY UPDATE value = 0x61" );
		$this->assertSame( 'a', $this->assertQuery( 'SELECT * FROM t' )[0]->value );
	}

	public function testCastValuesOnDuplicateKeyUpdateInNonStrictMode(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$this->assertQuery( 'CREATE TABLE t (value INT UNIQUE)' );
		$this->assertQuery( 'INSERT INTO t VALUES (123)' );

		// Ensure that type casting is applied to ON DUPLICATE KEY UPDATE clause.
		$this->assertQuery( "INSERT INTO t VALUES (123) ON DUPLICATE KEY UPDATE value = 'test'" );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );
	}

	public function testCastValuesOnUpdate(): void {
		// INTEGER
		$this->assertQuery( 'CREATE TABLE t (value INT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 1' );
		$this->assertSame( '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2'" );
		$this->assertSame( '2', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '3.0'" );
		$this->assertSame( '3', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$is_legacy_sqlite = version_compare( $this->engine->get_sqlite_version(), WP_PDO_MySQL_On_SQLite::MINIMUM_SQLITE_VERSION, '<' );
		if ( $is_legacy_sqlite ) {
			$this->assertQuery( "UPDATE t SET value = '4.5'" );
			$this->assertQuery( 'UPDATE t SET value = 0x05' );
			$this->assertQuery( "UPDATE t SET value = x'06'" );
		} else {
			// TODO: These are supported in MySQL:
			$this->assertQueryError( "UPDATE t SET value = '4.5'", 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store REAL value in INTEGER column t.value' );
			$this->assertQueryError( 'UPDATE t SET value = 0x05', 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store BLOB value in INTEGER column t.value' );
			$this->assertQueryError( "UPDATE t SET value = x'06'", 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store BLOB value in INTEGER column t.value' );
		}

		$this->assertQuery( 'DROP TABLE t' );

		// FLOAT
		$this->assertQuery( 'CREATE TABLE t (value FLOAT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1.0)' );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '1.0' : '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 1' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '1.0' : '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 2.34' );
		$this->assertSame( '2.34', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '3.45'" );
		$this->assertSame( '3.45', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 4' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '4.0' : '4', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '5'" );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '5.0' : '5', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		// TODO: These are supported in MySQL:
		if ( $is_legacy_sqlite ) {
			$this->assertQuery( 'UPDATE t SET value = 0x06' );
			$this->assertQuery( "UPDATE t SET value = x'07'" );
		} else {
			$this->assertQueryError( 'UPDATE t SET value = 0x06', 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store BLOB value in REAL column t.value' );
			$this->assertQueryError( "UPDATE t SET value = x'07'", 'SQLSTATE[23000]: Integrity constraint violation: 19 cannot store BLOB value in REAL column t.value' );
		}

		$this->assertQuery( 'DROP TABLE t' );

		// STRING
		$this->assertQuery( 'CREATE TABLE t (value TEXT)' );
		$this->assertQuery( "INSERT INTO t VALUES ('')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 123' );
		$this->assertSame( '123', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 123.456' );
		$this->assertSame( '123.456', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = 'a'" );
		$this->assertSame( 'a', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0x62' );
		$this->assertSame( 'b', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = x'63'" );
		$this->assertSame( 'c', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// BLOB
		$this->assertQuery( 'CREATE TABLE t (value BLOB)' );
		$this->assertQuery( "INSERT INTO t VALUES ('')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 123' );
		$this->assertSame( '123', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 123.456' );
		$this->assertSame( '123.456', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = 'a'" );
		$this->assertSame( 'a', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0x62' );
		$this->assertSame( 'b', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = x'63'" );
		$this->assertSame( 'c', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// DATE
		$this->assertQuery( 'CREATE TABLE t (value DATE)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-01-01')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQueryError( 'UPDATE t SET value = TRUE', "Incorrect date value: '1'" );
		$this->assertQueryError( 'UPDATE t SET value = FALSE', "Incorrect date value: '0'" );
		$this->assertQueryError( 'UPDATE t SET value = 0', "Incorrect date value: '0'" );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23'" );
		$this->assertSame( '2025-10-23', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00'" );
		$this->assertSame( '2025-10-23', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00.123456'" );
		$this->assertSame( '2025-10-23', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// TIME
		$this->assertQuery( 'CREATE TABLE t (value TIME)' );
		$this->assertQuery( "INSERT INTO t VALUES ('18:30:00')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '12:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 00:00:00 in MySQL

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '12:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 00:00:01 in MySQL

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '12:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 00:00:00 in MySQL

		$this->assertQuery( 'UPDATE t SET value = 123' );
		$this->assertSame( '12:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 00:01:23 in MySQL

		$this->assertQuery( "UPDATE t SET value = '18:30:00'" );
		$this->assertSame( '18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '18:30:00.123456'" );
		$this->assertSame( '18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// DATETIME
		$this->assertQuery( 'CREATE TABLE t (value DATETIME)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-01-01')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQueryError( 'UPDATE t SET value = TRUE', "Incorrect datetime value: '1'" );
		$this->assertQueryError( 'UPDATE t SET value = FALSE', "Incorrect datetime value: '0'" );
		$this->assertQueryError( 'UPDATE t SET value = 0', "Incorrect datetime value: '0'" );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23'" );
		$this->assertSame( '2025-10-23 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00'" );
		$this->assertSame( '2025-10-23 18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00.123456'" );
		$this->assertSame( '2025-10-23 18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// TIMESTAMP
		$this->assertQuery( 'CREATE TABLE t (value TIMESTAMP)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-01-01')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQueryError( 'UPDATE t SET value = TRUE', "Incorrect timestamp value: '1'" );
		$this->assertQueryError( 'UPDATE t SET value = FALSE', "Incorrect timestamp value: '0'" );
		$this->assertQueryError( 'UPDATE t SET value = 0', "Incorrect timestamp value: '0'" );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23'" );
		$this->assertSame( '2025-10-23 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00'" );
		$this->assertSame( '2025-10-23 18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00.123456'" );
		$this->assertSame( '2025-10-23 18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// YEAR
		$this->assertQuery( 'CREATE TABLE t (value YEAR)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0000', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '2001', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025'" );
		$this->assertSame( '2025', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23'" );
		$this->assertSame( '2025', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00'" );
		$this->assertSame( '2025', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00.123456'" );
		$this->assertSame( '2025', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 1' );
		$this->assertSame( '2001', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 50' );
		$this->assertSame( '2050', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 70' );
		$this->assertSame( '1970', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 99' );
		$this->assertSame( '1999', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQueryError( 'UPDATE t SET value = -1', "Out of range value: '-1'" );
		$this->assertQueryError( 'UPDATE t SET value = 1900', "Out of range value: '1900'" );
		$this->assertQueryError( 'UPDATE t SET value = 2156', "Out of range value: '2156'" );

		$this->assertQuery( 'DROP TABLE t' );
	}

	public function testCastValuesOnUpdateInNonStrictMode(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );

		// INTEGER
		$this->assertQuery( 'CREATE TABLE t (value INT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1)' );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 1' );
		$this->assertSame( '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2'" );
		$this->assertSame( '2', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '3.0'" );
		$this->assertSame( '3', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '4.5'" );
		$this->assertSame( '4', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 5 in MySQL

		$this->assertQuery( 'UPDATE t SET value = 0x05' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 5 in MySQL

		$this->assertQuery( "UPDATE t SET value = x'06'" );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 6 in MySQL

		$this->assertQuery( 'DROP TABLE t' );

		// FLOAT
		$this->assertQuery( 'CREATE TABLE t (value FLOAT)' );
		$this->assertQuery( 'INSERT INTO t VALUES (1.0)' );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '1.0' : '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 1' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '1.0' : '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 2.34' );
		$this->assertSame( '2.34', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '3.45'" );
		$this->assertSame( '3.45', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 4' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '4.0' : '4', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '5'" );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '5.0' : '5', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0x06' );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 6 in MySQL

		$this->assertQuery( "UPDATE t SET value = x'07'" );
		$this->assertSame( PHP_VERSION_ID < 80100 ? '0.0' : '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 7 in MySQL

		$this->assertQuery( 'DROP TABLE t' );

		// STRING
		$this->assertQuery( 'CREATE TABLE t (value TEXT)' );
		$this->assertQuery( "INSERT INTO t VALUES ('')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 123' );
		$this->assertSame( '123', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 123.456' );
		$this->assertSame( '123.456', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = 'a'" );
		$this->assertSame( 'a', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0x62' );
		$this->assertSame( 'b', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = x'63'" );
		$this->assertSame( 'c', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// BLOB
		$this->assertQuery( 'CREATE TABLE t (value BLOB)' );
		$this->assertQuery( "INSERT INTO t VALUES ('')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '1', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 123' );
		$this->assertSame( '123', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 123.456' );
		$this->assertSame( '123.456', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = 'a'" );
		$this->assertSame( 'a', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0x62' );
		$this->assertSame( 'b', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = x'63'" );
		$this->assertSame( 'c', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// DATE
		$this->assertQuery( 'CREATE TABLE t (value DATE)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-01-01')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '0000-00-00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0000-00-00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0000-00-00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23'" );
		$this->assertSame( '2025-10-23', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00'" );
		$this->assertSame( '2025-10-23', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00.123456'" );
		$this->assertSame( '2025-10-23', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// TIME
		$this->assertQuery( 'CREATE TABLE t (value TIME)' );
		$this->assertQuery( "INSERT INTO t VALUES ('18:30:00')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '12:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 00:00:00 in MySQL

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '12:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 00:00:01 in MySQL

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '12:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 00:00:00 in MySQL

		$this->assertQuery( 'UPDATE t SET value = 123' );
		$this->assertSame( '12:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value ); // TODO: 00:01:23 in MySQL

		$this->assertQuery( "UPDATE t SET value = '18:30:00'" );
		$this->assertSame( '18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '18:30:00.123456'" );
		$this->assertSame( '18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// DATETIME
		$this->assertQuery( 'CREATE TABLE t (value DATETIME)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-01-01')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '0000-00-00 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0000-00-00 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0000-00-00 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23'" );
		$this->assertSame( '2025-10-23 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00'" );
		$this->assertSame( '2025-10-23 18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00.123456'" );
		$this->assertSame( '2025-10-23 18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// TIMESTAMP
		$this->assertQuery( 'CREATE TABLE t (value TIMESTAMP)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025-01-01')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '0000-00-00 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0000-00-00 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 0' );
		$this->assertSame( '0000-00-00 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23'" );
		$this->assertSame( '2025-10-23 00:00:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00'" );
		$this->assertSame( '2025-10-23 18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00.123456'" );
		$this->assertSame( '2025-10-23 18:30:00', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );

		// YEAR
		$this->assertQuery( 'CREATE TABLE t (value YEAR)' );
		$this->assertQuery( "INSERT INTO t VALUES ('2025')" );

		$this->assertQuery( 'UPDATE t SET value = NULL' );
		$this->assertSame( null, $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = FALSE' );
		$this->assertSame( '0000', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = TRUE' );
		$this->assertSame( '2001', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025'" );
		$this->assertSame( '2025', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23'" );
		$this->assertSame( '2025', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00'" );
		$this->assertSame( '2025', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( "UPDATE t SET value = '2025-10-23 18:30:00.123456'" );
		$this->assertSame( '2025', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 1' );
		$this->assertSame( '2001', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 50' );
		$this->assertSame( '2050', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 70' );
		$this->assertSame( '1970', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 99' );
		$this->assertSame( '1999', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = -1' );
		$this->assertSame( '0000', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 1900' );
		$this->assertSame( '0000', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'UPDATE t SET value = 2156' );
		$this->assertSame( '0000', $this->assertQuery( 'SELECT * FROM t' )[0]->value );

		$this->assertQuery( 'DROP TABLE t' );
	}

	public function testInsertErrors(): void {
		$this->assertQuery( 'CREATE TABLE t (value INT)' );

		// Missing table.
		$this->assertQueryError(
			'INSERT INTO missing_table VALUES (1)',
			"SQLSTATE[42S02]: Base table or view not found: 1146 Table 'missing_table' doesn't exist"
		);

		// Missing column.
		$this->assertQueryError(
			'INSERT INTO t (missing_column) VALUES (1)',
			"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'missing_column' in 'field list'"
		);
	}

	public function testInsertErrorsInNonStrictMode(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$this->assertQuery( 'CREATE TABLE t (value INT)' );

		// Missing table.
		$this->assertQueryError(
			'INSERT INTO missing_table VALUES (1)',
			"SQLSTATE[42S02]: Base table or view not found: 1146 Table 'missing_table' doesn't exist"
		);

		// Missing column.
		$this->assertQueryError(
			'INSERT INTO t (missing_column) VALUES (1)',
			"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'missing_column' in 'field list'"
		);
	}

	public function testUpdateErrors(): void {
		$this->assertQuery( 'CREATE TABLE t (value INT)' );

		// Missing table.
		$this->assertQueryError(
			'UPDATE missing_table SET value = 1',
			"SQLSTATE[42S02]: Base table or view not found: 1146 Table 'missing_table' doesn't exist"
		);

		// Missing column.
		$this->assertQueryError(
			'UPDATE t SET missing_column = 1',
			"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'missing_column' in 'field list'"
		);
	}

	public function testUpdateErrorsInNonStrictMode(): void {
		$this->assertQuery( "SET SESSION sql_mode = ''" );
		$this->assertQuery( 'CREATE TABLE t (value INT)' );

		// Missing table.
		$this->assertQueryError(
			'UPDATE missing_table SET value = 1',
			"SQLSTATE[42S02]: Base table or view not found: 1146 Table 'missing_table' doesn't exist"
		);

		// Missing column.
		$this->assertQueryError(
			'UPDATE t SET missing_column = 1',
			"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'missing_column' in 'field list'"
		);
	}

	public function testVersionFunction(): void {
		$result = $this->engine->query( 'SELECT VERSION()' );
		$this->assertSame( '8.0.38', $result[0]->{'VERSION()'} );
	}

	public function testSubstringFunction(): void {
		$result = $this->assertQuery( "SELECT SUBSTRING('abcdef', 1, 3) AS s" );
		$this->assertSame( 'abc', $result[0]->s );

		$result = $this->assertQuery( "SELECT SUBSTRING('abcdef', 4) AS s" );
		$this->assertSame( 'def', $result[0]->s );

		$result = $this->assertQuery( "SELECT SUBSTRING('abcdef' FROM 1 FOR 3) AS s" );
		$this->assertSame( 'abc', $result[0]->s );

		$result = $this->assertQuery( "SELECT SUBSTRING('abcdef' FROM 4) AS s" );
		$this->assertSame( 'def', $result[0]->s );
	}
}
