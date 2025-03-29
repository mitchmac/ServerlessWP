<?php

use PHPUnit\Framework\TestCase;

class WP_SQLite_Translator_Tests extends TestCase {

	private $engine;
	private $sqlite;

	public static function setUpBeforeClass(): void {
		// if ( ! defined( 'PDO_DEBUG' )) {
		// define( 'PDO_DEBUG', true );
		// }
		if ( ! defined( 'FQDB' ) ) {
			define( 'FQDB', ':memory:' );
			define( 'FQDBDIR', __DIR__ . '/../testdb' );
		}
		error_reporting( E_ALL & ~E_DEPRECATED );
		if ( ! isset( $GLOBALS['table_prefix'] ) ) {
			$GLOBALS['table_prefix'] = 'wptests_';
		}
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			$GLOBALS['wpdb']                  = new stdClass();
			$GLOBALS['wpdb']->suppress_errors = false;
			$GLOBALS['wpdb']->show_errors     = true;
		}
		return;
	}

	// Before each test, we create a new database
	public function setUp(): void {
		$this->sqlite = new PDO( 'sqlite::memory:' );

		$this->engine = new WP_SQLite_Translator( $this->sqlite );
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
					option_value DATE NOT NULL
				);"
		);
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
			"INSERT INTO _options (option_name, option_value) VALUES ('User 0000019', 'second');"
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
		$this->assertEquals( 'second', $result1[0]->option_value );
		$this->assertEquals( 'second', $result2[0]->option_value );
	}

	public function testUpdateWithoutWhereButWithLimit() {
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('first', '2003-05-27 10:08:48');"
		);
		$this->assertQuery(
			"INSERT INTO _dates (option_name, option_value) VALUES ('second', '2003-05-27 10:08:48');"
		);
		$return = $this->assertQuery(
			"UPDATE _dates SET option_value = 'second' LIMIT 1"
		);
		$this->assertSame( 1, $return, 'UPDATE query did not return 2 when two row were changed' );

		$result1 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='first'" );
		$result2 = $this->engine->query( "SELECT option_value FROM _dates WHERE option_name='second'" );
		$this->assertEquals( 'second', $result1[0]->option_value );
		$this->assertEquals( '2003-05-27 10:08:48', $result2[0]->option_value );
	}

	public function testCastAsBinary() {
		$this->assertQuery(
			// Use a confusing alias to make sure it replaces only the correct token
			"SELECT CAST('ABC' AS BINARY) as binary;"
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
				UNIQUE KEY option_name (option_name(100)),
				KEY composite (option_name(100), option_value(100))
			);"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp_table;'
		);
		$results = $this->engine->get_query_results();
		# TODO: Should we fix mismatch with original `option_value` text NOT NULL,` without default?
		$this->assertEquals(
			"CREATE TABLE `_tmp_table` (
	`ID` bigint NOT NULL AUTO_INCREMENT,
	`option_name` varchar(255) DEFAULT '',
	`option_value` text NOT NULL DEFAULT '',
	PRIMARY KEY (`ID`),
	KEY `composite` (`option_name`(100), `option_value`(100)),
	UNIQUE KEY `option_name` (`option_name`(100))
);",
			$results[0]->{'Create Table'}
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
				notempty5 timestamp DEFAULT '1734539165',
			);"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp_table;'
		);
		$results = $this->engine->get_query_results();

		$this->assertEquals(
			"CREATE TABLE `_tmp_table` (
	`ID` bigint AUTO_INCREMENT,
	`timestamp1` datetime NOT NULL,
	`timestamp2` date NOT NULL,
	`timestamp3` time NOT NULL,
	`timestamp4` timestamp NOT NULL,
	`timestamp5` year NOT NULL,
	`notempty1` datetime DEFAULT '1999-12-12 12:12:12',
	`notempty2` date DEFAULT '1999-12-12',
	`notempty3` time DEFAULT '12:12:12',
	`notempty4` year DEFAULT '2024',
	`notempty5` timestamp DEFAULT '1734539165',
	PRIMARY KEY (`ID`)
);",
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTableQuoted() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
				option_name VARCHAR(255) default '',
				option_value TEXT NOT NULL,
				UNIQUE KEY option_name (option_name(100)),
				KEY composite (option_name, option_value(100))
			);"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE `_tmp_table`;'
		);
		$results = $this->engine->get_query_results();
		# TODO: Should we fix mismatch with original `option_value` text NOT NULL,` without default?
		$this->assertEquals(
			"CREATE TABLE `_tmp_table` (
	`ID` bigint NOT NULL AUTO_INCREMENT,
	`option_name` varchar(255) DEFAULT '',
	`option_value` text NOT NULL DEFAULT '',
	PRIMARY KEY (`ID`),
	KEY `composite` (`option_name`(100), `option_value`(100)),
	UNIQUE KEY `option_name` (`option_name`(100))
);",
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
	`ID` bigint NOT NULL DEFAULT 0
);',
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
	`option_name` smallint NOT NULL DEFAULT 14,
	`option_value` text NOT NULL DEFAULT \'\',
	PRIMARY KEY (`ID`),
	KEY `option_name` (`option_name`)
);',
			$results[0]->{'Create Table'}
		);
	}

	public function testCreateTablseWithIdenticalIndexNames() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table_a (
					ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name VARCHAR(255) default '',
					option_value TEXT NOT NULL,
					KEY `option_name` (`option_name`(100)),
					KEY `double__underscores` (`option_name`(100), `ID`)
				);"
		);

		$this->assertQuery(
			"CREATE TABLE _tmp_table_b (
					ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name VARCHAR(255) default '',
					option_value TEXT NOT NULL,
					KEY `option_name` (`option_name`(100)),
					KEY `double__underscores` (`option_name`(100), `ID`)
				);"
		);
	}

	public function testShowCreateTablePreservesDoubleUnderscoreKeyNames() {
		$this->assertQuery(
			"CREATE TABLE _tmp__table (
					ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
					option_name VARCHAR(255) default '',
					option_value TEXT NOT NULL,
					KEY `option_name` (`option_name`(100)),
					KEY `double__underscores` (`option_name`(100), `ID`)
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
	`option_value` text NOT NULL DEFAULT \'\',
	PRIMARY KEY (`ID`),
	KEY `double__underscores` (`option_name`(100), `ID`),
	KEY `option_name` (`option_name`(100))
);',
			$results[0]->{'Create Table'}
		);
	}

	public function testShowCreateTableLimitsKeyLengths() {
		$this->assertQuery(
			'CREATE TABLE _tmp__table (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`order_id` bigint(20) unsigned DEFAULT NULL,
					`meta_key` varchar(20) DEFAULT NULL,
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
	`meta_key` varchar(20) DEFAULT NULL,
	`meta_value` text DEFAULT NULL,
	`meta_data` mediumblob DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `order_id_meta_key_meta_data` (`order_id`, `meta_key`(20), `meta_data`(100)),
	KEY `order_id_meta_key_meta_value` (`order_id`, `meta_key`(20), `meta_value`(100)),
	KEY `meta_key_value` (`meta_key`(20), `meta_value`(100))
);',
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
	`ID_A` bigint NOT NULL DEFAULT 0,
	`ID_B` bigint NOT NULL DEFAULT 0,
	`ID_C` bigint NOT NULL DEFAULT 0,
	PRIMARY KEY (`ID_B`, `ID_A`, `ID_C`)
);',
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

	public function testShowCreateTableWithCorrectDefaultValues() {
		$this->assertQuery(
			"CREATE TABLE _tmp__table (
					ID BIGINT PRIMARY KEY AUTO_INCREMENT NOT NULL,
					default_empty_string VARCHAR(255) default '',
					null_no_default VARCHAR(255),
				);"
		);

		$this->assertQuery(
			'SHOW CREATE TABLE _tmp__table;'
		);
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			'CREATE TABLE `_tmp__table` (
	`ID` bigint NOT NULL AUTO_INCREMENT,
	`default_empty_string` varchar(255) DEFAULT \'\',
	`null_no_default` varchar(255),
	PRIMARY KEY (`ID`)
);',
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
					'Tables_in_db' => '_tmp_table',
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
			"SHOW TABLE STATUS FROM 'mydb';"
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
			"SHOW TABLE STATUS IN 'mydb';"
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
			"SHOW TABLE STATUS IN 'mydb';"
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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$this->assertQuery( 'DESCRIBE wptests_users;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'ID',
					'Type'    => 'bigint(20) unsigned',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => '0',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_login',
					'Type'    => 'varchar(60)',
					'Null'    => 'NO',
					'Key'     => '',
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
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'user_email',
					'Type'    => 'varchar(100)',
					'Null'    => 'NO',
					'Key'     => '',
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
				PRIMARY KEY  (ID),
			) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );
	}

	public function testCreateTableSpatialIndex() {
		$result = $this->assertQuery(
			'CREATE TABLE wptests_users (
				ID bigint(20) unsigned NOT NULL auto_increment,
				UNIQUE KEY (ID),
			)'
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );
	}

	public function testCreateTableWithMultiValueColumnTypeModifiers() {
		$result = $this->assertQuery(
			"CREATE TABLE wptests_users (
				ID bigint(20) unsigned NOT NULL auto_increment,
				decimal_column DECIMAL(10,2) NOT NULL DEFAULT 0,
				float_column FLOAT(10,2) NOT NULL DEFAULT 0,
				enum_column ENUM('a', 'b', 'c') NOT NULL DEFAULT 'a',
				PRIMARY KEY  (ID),
			)"
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$this->assertQuery( 'DESCRIBE wptests_users;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'ID',
					'Type'    => 'bigint(20) unsigned',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => '0',
					'Extra'   => '',
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

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table ADD COLUMN `column` int;' );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

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
					'Default' => '0',
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
					'Default' => '0',
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

		// assert ON UPDATE triggers
		$results = $this->assertQuery( "SELECT * FROM sqlite_master WHERE type = 'trigger'" );
		$this->assertEquals(
			array(
				(object) array(
					'type'     => 'trigger',
					'name'     => '___tmp_table_created_at_on_update__',
					'tbl_name' => '_tmp_table',
					'rootpage' => '0',
					'sql'      => "CREATE TRIGGER \"___tmp_table_created_at_on_update__\"\n\t\t\tAFTER UPDATE ON \"_tmp_table\"\n\t\t\tFOR EACH ROW\n\t\t\tBEGIN\n\t\t\t  UPDATE \"_tmp_table\" SET \"created_at\" = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid;\n\t\t\tEND",
				),
				(object) array(
					'type'     => 'trigger',
					'name'     => '___tmp_table_updated_at_on_update__',
					'tbl_name' => '_tmp_table',
					'rootpage' => '0',
					'sql'      => "CREATE TRIGGER \"___tmp_table_updated_at_on_update__\"\n\t\t\tAFTER UPDATE ON \"_tmp_table\"\n\t\t\tFOR EACH ROW\n\t\t\tBEGIN\n\t\t\t  UPDATE \"_tmp_table\" SET \"updated_at\" = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid;\n\t\t\tEND",
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
					'Default' => '0',
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

	public function testColumnWithOnUpdateAndAutoincrementPrimaryKey() {
		// CREATE TABLE with ON UPDATE, AUTO_INCREMENT, and PRIMARY KEY
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				id int(11) NOT NULL AUTO_INCREMENT,
				created_at timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			);'
		);

		// on INSERT, no timestamps are expected
		$this->assertQuery( 'INSERT INTO _tmp_table (id) VALUES (1)' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE id = 1' );
		$this->assertNull( $result[0]->created_at );

		// on UPDATE, we expect timestamps in form YYYY-MM-DD HH:MM:SS
		$this->assertQuery( 'UPDATE _tmp_table SET id = 2 WHERE id = 1' );
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE id = 2' );
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
					'Default' => '0',
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
					'Default' => '0',
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
					'Default' => '0',
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
					'Default' => '0',
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
					'Default' => '0',
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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$this->assertQuery( 'SHOW INDEX FROM _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Table'         => '_tmp_table',
					'Non_unique'    => '1',
					'Key_name'      => 'name',
					'Seq_in_index'  => '0',
					'Column_name'   => 'name',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
			),
			$results
		);
	}

	public function testAlterTableAddUniqueIndex() {
		$result = $this->assertQuery(
			"CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL default ''
			);"
		);

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table ADD UNIQUE INDEX name (name(20));' );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$this->assertQuery( 'SHOW INDEX FROM _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Table'         => '_tmp_table',
					'Non_unique'    => '0',
					'Key_name'      => 'name',
					'Seq_in_index'  => '0',
					'Column_name'   => 'name',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
			),
			$results
		);
	}

	public function testAlterTableAddFulltextIndex() {
		$result = $this->assertQuery(
			"CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL default ''
			);"
		);

		$result = $this->assertQuery( 'ALTER TABLE _tmp_table ADD FULLTEXT INDEX name (name);' );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$this->assertQuery( 'SHOW INDEX FROM _tmp_table;' );
		$results = $this->engine->get_query_results();
		$this->assertEquals(
			array(
				(object) array(
					'Table'         => '_tmp_table',
					'Non_unique'    => '1',
					'Key_name'      => 'name',
					'Seq_in_index'  => '0',
					'Column_name'   => 'name',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'FULLTEXT',
					'Comment'       => '',
					'Index_comment' => '',
				),
			),
			$results
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
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (1, 'Mike', 'Pearseed');" );
		$this->assertEquals( false, $result );

		// Unique constraint violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (2, 'Johnny', 'Appleseed');" );
		$this->assertEquals( false, $result );

		// Rename the "name" field to "firstname":
		$result = $this->engine->query( "ALTER TABLE _tmp_table CHANGE column name firstname varchar(50) NOT NULL default 'mark';" );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		// Confirm the original data is still there:
		$result = $this->engine->query( 'SELECT * FROM _tmp_table;' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 1, $result[0]->ID );
		$this->assertEquals( 'Johnny', $result[0]->firstname );
		$this->assertEquals( 'Appleseed', $result[0]->lastname );

		// Confirm the primary key is intact:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname) VALUES (1, 'Mike', 'Pearseed');" );
		$this->assertEquals( false, $result );

		// Confirm the unique key is intact:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname) VALUES (2, 'Johnny', 'Appleseed');" );
		$this->assertEquals( false, $result );

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
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (1, 'Mike', 'Pearseed');" );
		$this->assertEquals( false, $result );

		// Unique constraint violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (2, 'Johnny', 'Appleseed');" );
		$this->assertEquals( false, $result );

		// Rename the "name" field to "firstname":
		$result = $this->engine->query( "ALTER TABLE _tmp_table CHANGE name firstname varchar(50) NOT NULL default 'mark';" );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		// Confirm the original data is still there:
		$result = $this->engine->query( 'SELECT * FROM _tmp_table;' );
		$this->assertCount( 1, $result );
		$this->assertEquals( 1, $result[0]->ID );
		$this->assertEquals( 'Johnny', $result[0]->firstname );
		$this->assertEquals( 'Appleseed', $result[0]->lastname );

		// Confirm the primary key is intact:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname) VALUES (1, 'Mike', 'Pearseed');" );
		$this->assertEquals( false, $result );

		// Confirm the unique key is intact:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname) VALUES (2, 'Johnny', 'Appleseed');" );
		$this->assertEquals( false, $result );

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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$result = $this->assertQuery(
			'ALTER TABLE wptests_dbdelta_test2 CHANGE COLUMN `foo-bar` `foo-bar` text DEFAULT NULL'
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$result = $this->assertQuery( 'DESCRIBE wptests_dbdelta_test2;' );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertNotFalse( $result );
		$this->assertEquals(
			array(
				(object) array(
					'Field'   => 'foo-bar',
					'Type'    => 'text',
					'Null'    => 'YES',
					'Key'     => '',
					'Default' => 'NULL',
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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		// Add a unique index
		$result = $this->assertQuery(
			'ALTER TABLE _tmp_table ADD UNIQUE INDEX "test_unique_composite" (name, lastname);'
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		// Add a regular index
		$result = $this->assertQuery(
			'ALTER TABLE _tmp_table ADD INDEX "test_regular" (lastname);'
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

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
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, name) VALUES (1, 'Johnny');" );
		$this->assertEquals( false, $result );

		// Unique constraint violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (5, 'Kate', 'Bar');" );
		$this->assertEquals( false, $result );

		// No constraint violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, name, lastname) VALUES (5, 'Joanna', 'Bar');" );
		$this->assertEquals( 1, $result );

		// Now – let's change a few columns:
		$result = $this->engine->query( 'ALTER TABLE _tmp_table CHANGE COLUMN name firstname varchar(20)' );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$result = $this->engine->query( 'ALTER TABLE _tmp_table CHANGE COLUMN date_as_string datetime datetime NOT NULL' );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		// Finally, let's confirm our data is intact and the table is still well-behaved:
		$result = $this->engine->query( 'SELECT * FROM _tmp_table ORDER BY ID;' );
		$this->assertCount( 5, $result );
		$this->assertEquals( 1, $result[0]->ID );
		$this->assertEquals( 'Johnny', $result[0]->firstname );
		$this->assertEquals( 'Appleseed', $result[0]->lastname );
		$this->assertEquals( '2002-01-01 12:53:13', $result[0]->datetime );

		// Primary key violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, firstname, datetime) VALUES (1, 'Johnny', '2010-01-01 12:53:13');" );
		$this->assertEquals( false, $result );

		// Unique constraint violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname, datetime) VALUES (6, 'Kate', 'Bar', '2010-01-01 12:53:13');" );
		$this->assertEquals( false, $result );

		// No constraint violation:
		$result = $this->engine->query( "INSERT INTO _tmp_table (ID, firstname, lastname, datetime) VALUES (6, 'Sophie', 'Bar', '2010-01-01 12:53:13');" );
		$this->assertEquals( '', $this->engine->get_error_message() );
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
		$this->assertEquals( 1, $result );

		$result1 = $this->engine->query( "INSERT INTO _tmp_table (name, lastname) VALUES ('first', 'last');" );
		$this->assertEquals( 1, $result1 );

		$result1 = $this->engine->query( 'SELECT COUNT(*) num FROM _tmp_table;' );
		$this->assertEquals( 1, $result1[0]->num );

		// Unique keys should be case-insensitive:
		$result2 = $this->assertQuery(
			"INSERT INTO _tmp_table (name, lastname) VALUES ('FIRST', 'LAST' );",
			'UNIQUE constraint failed'
		);

		$this->assertEquals( false, $result2 );

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

		// $result1 = $this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('first');" );
		// $this->assertEquals( '', $this->engine->get_error_message() );
		// $this->assertEquals( 1, $result1 );

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
		$this->assertEquals( '', $this->engine->get_error_message() );
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

	public function testNestedTransactionWork() {
		$this->assertQuery( 'BEGIN' );
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('first');" );
		$this->assertQuery( 'START TRANSACTION' );
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('second');" );
		$this->assertQuery( 'START TRANSACTION' );
		$this->assertQuery( "INSERT INTO _options (option_name) VALUES ('third');" );
		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 3, $this->engine->get_query_results() );

		$this->assertQuery( 'ROLLBACK' );
		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 2, $this->engine->get_query_results() );

		$this->assertQuery( 'ROLLBACK' );
		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 1, $this->engine->get_query_results() );

		$this->assertQuery( 'COMMIT' );
		$this->assertQuery( 'SELECT * FROM _options;' );
		$this->assertCount( 1, $this->engine->get_query_results() );
	}

	public function testNestedTransactionWorkComplexModify() {
		$this->assertQuery( 'BEGIN' );
		// Create a complex ALTER Table query where the first
		// column is added successfully, but the second fails.
		// Behind the scenes, this single MySQL query is split
		// into multiple SQLite queries – some of them will
		// succeed, some will fail.
		$success = $this->engine->query(
			'
		ALTER TABLE _options
			ADD COLUMN test varchar(20),
			ADD COLUMN test varchar(20)
		'
		);
		$this->assertFalse( $success );
		// Commit the transaction.
		$this->assertQuery( 'COMMIT' );

		// Confirm the entire query failed atomically and no column was
		// added to the table.
		$this->assertQuery( 'DESCRIBE _options;' );
		$fields = $this->engine->get_query_results();

		$this->assertEquals(
			$fields,
			array(
				(object) array(
					'Field'   => 'ID',
					'Type'    => 'integer',
					'Null'    => 'NO',
					'Key'     => 'PRI',
					'Default' => '0',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'option_name',
					'Type'    => 'text',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
				(object) array(
					'Field'   => 'option_value',
					'Type'    => 'text',
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => '',
					'Extra'   => '',
				),
			)
		);
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

		$result2 = $this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('FIRST') ON DUPLICATE KEY SET name=VALUES(`name`);" );
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
		$result1 = $this->engine->query( 'INSERT INTO wptests_term_relationships VALUES (1,2,1),(1,3,2);' );
		$this->assertEquals( 2, $result1 );

		$result2 = $this->engine->query( 'INSERT INTO wptests_term_relationships VALUES (1,2,2),(1,3,1);' );
		$this->assertEquals( false, $result2 );
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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertNotFalse( $result );

		$result = $this->assertQuery( 'DESCRIBE wptests_term_relationships;' );
		$this->assertEquals( '', $this->engine->get_error_message() );
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
					'Key'     => '',
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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertNotFalse( $result );

		$result = $this->assertQuery( "ALTER TABLE `_test` ADD COLUMN object_name varchar(255) NOT NULL DEFAULT 'adb';" );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertNotFalse( $result );

		$result = $this->assertQuery( 'DESCRIBE _test;' );
		$this->assertEquals( '', $this->engine->get_error_message() );
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
					'Grants for root@localhost' => 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, RELOAD, SHUTDOWN, PROCESS, FILE, REFERENCES, INDEX, ALTER, SHOW DATABASES, SUPER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, REPLICATION SLAVE, REPLICATION CLIENT, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, CREATE USER, EVENT, TRIGGER, CREATE TABLESPACE, CREATE ROLE, DROP ROLE ON *.* TO `root`@`localhost` WITH GRANT OPTION',
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
				FULLTEXT KEY term_name_fulltext (term_name),
				FULLTEXT INDEX term_name_fulltext2 (`term_name`),
				SPATIAL KEY term_name_spatial (term_name),
				PRIMARY KEY  (object_id,term_taxonomy_id),
				KEY term_taxonomy_id (term_taxonomy_id),
				KEY compound_key (object_id(20),term_taxonomy_id(20))
			) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertNotFalse( $result );

		$result = $this->assertQuery( 'SHOW INDEX FROM wptests_term_relationships;' );
		$this->assertNotFalse( $result );

		$this->assertEquals(
			array(
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '0',
					'Key_name'      => 'PRIMARY',
					'Seq_in_index'  => '0',
					'Column_name'   => 'object_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '0',
					'Key_name'      => 'PRIMARY',
					'Seq_in_index'  => '0',
					'Column_name'   => 'term_taxonomy_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'compound_key',
					'Seq_in_index'  => '0',
					'Column_name'   => 'object_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'compound_key',
					'Seq_in_index'  => '0',
					'Column_name'   => 'term_taxonomy_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'term_taxonomy_id',
					'Seq_in_index'  => '0',
					'Column_name'   => 'term_taxonomy_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'term_name_spatial',
					'Seq_in_index'  => '0',
					'Column_name'   => 'term_name',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'SPATIAL',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'term_name_fulltext2',
					'Seq_in_index'  => '0',
					'Column_name'   => 'term_name',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'FULLTEXT',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '1',
					'Key_name'      => 'term_name_fulltext',
					'Seq_in_index'  => '0',
					'Column_name'   => 'term_name',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'FULLTEXT',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '0',
					'Key_name'      => 'wptests_term_relationships',
					'Seq_in_index'  => '0',
					'Column_name'   => 'object_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
				(object) array(
					'Table'         => 'wptests_term_relationships',
					'Non_unique'    => '0',
					'Key_name'      => 'wptests_term_relationships',
					'Seq_in_index'  => '0',
					'Column_name'   => 'term_taxonomy_id',
					'Collation'     => 'A',
					'Cardinality'   => '0',
					'Sub_part'      => null,
					'Packed'        => null,
					'Null'          => '',
					'Index_type'    => 'BTREE',
					'Comment'       => '',
					'Index_comment' => '',
				),
			),
			$this->engine->get_query_results()
		);
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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertNotFalse( $result );

		$result1 = $this->assertQuery( 'INSERT INTO wptests_term_relationships VALUES (1,2,1),(1,3,2);' );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 2, $result1 );

		$result2 = $this->assertQuery( 'INSERT INTO wptests_term_relationships VALUES (1,2,2),(1,3,1) ON DUPLICATE KEY SET term_order = VALUES(term_order);' );
		$this->assertEquals( '', $this->engine->get_error_message() );
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
				ID INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
				user_login TEXT NOT NULL default ''
			);"
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertNotFalse( $result );

		$result = $this->assertQuery(
			"INSERT INTO wptests_dummy (user_login) VALUES ('test');"
		);
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 1, $result );

		$result = $this->assertQuery(
			'SELECT SQL_CALC_FOUND_ROWS * FROM wptests_dummy'
		);
		$this->assertNotFalse( $result );
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals( 'test', $result[0]->user_login );
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
		$this->assertEquals( '', $this->engine->get_error_message() );
		$this->assertEquals(
			1,
			$result
		);
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

	public function testTranslateLikeBinaryAndGlob() {
		// Create a temporary table for testing
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
            ID INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name varchar(20) NOT NULL default ''
        );"
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
		$this->assertQuery( "INSERT INTO _tmp_table (name) VALUES ('special\\chars');" );

		// Test case-sensitive LIKE BINARY
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'first'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test case-sensitive LIKE BINARY with wildcard %
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'f%'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test case-sensitive LIKE BINARY with wildcard _
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'f_rst'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test case-insensitive LIKE
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE 'FIRST'" );
		$this->assertCount( 2, $result ); // Should match both 'first' and 'FIRST'

		// Test mixed case with LIKE BINARY
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'First'" );
		$this->assertCount( 0, $result );

		// Test no matches with LIKE BINARY
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'third'" );
		$this->assertCount( 0, $result );

		// Test GLOB equivalent for case-sensitive matching with wildcard
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name GLOB 'f*'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test GLOB with single character wildcard
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name GLOB 'f?rst'" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		// Test GLOB with no matches
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name GLOB 'S*'" );
		$this->assertCount( 0, $result );

		// Test GLOB case sensitivity with LIKE and GLOB
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name GLOB 'first';" );
		$this->assertCount( 1, $result ); // Should only match 'first'

		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name GLOB 'FIRST';" );
		$this->assertCount( 1, $result ); // Should only match 'FIRST'

		// Test NULL comparison with LIKE BINARY
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY 'first';" );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'first', $result[0]->name );

		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE name LIKE BINARY NULL;' );
		$this->assertCount( 0, $result );  // NULL comparison should return no results

		// Test pattern with special characters using LIKE BINARY
		$result = $this->assertQuery( "SELECT * FROM _tmp_table WHERE name LIKE BINARY '%special%';" );
		$this->assertCount( 4, $result );
		$this->assertEquals( '%special%', $result[0]->name );
		$this->assertEquals( 'special%chars', $result[1]->name );
		$this->assertEquals( 'special_chars', $result[2]->name );
		$this->assertEquals( 'specialchars', $result[3]->name );
	}

	public function testOnConflictReplace() {
		$this->assertQuery(
			"CREATE TABLE _tmp_table (
				ID INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
				name varchar(20) NOT NULL default 'default-value',
				unique_name varchar(20) NOT NULL default 'unique-default-value',
				inline_unique_name varchar(20) NOT NULL default 'inline-unique-default-value',
				no_default varchar(20) NOT NULL,
				UNIQUE KEY unique_name (unique_name)
			);"
		);

		$this->assertQuery(
			"INSERT INTO _tmp_table VALUES (1, null, null, null, '');"
		);
		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE ID = 1' );
		$this->assertEquals(
			array(
				(object) array(
					'ID'                 => '1',
					'name'               => 'default-value',
					'unique_name'        => 'unique-default-value',
					'inline_unique_name' => 'inline-unique-default-value',
					'no_default'         => '',
				),
			),
			$result
		);

		$this->assertQuery(
			"INSERT INTO _tmp_table VALUES (2, '1', '2', '3', '4');"
		);
		$this->assertQuery(
			'UPDATE _tmp_table SET name = null WHERE ID = 2;'
		);

		$result = $this->assertQuery( 'SELECT name FROM _tmp_table WHERE ID = 2' );
		$this->assertEquals(
			array(
				(object) array(
					'name' => 'default-value',
				),
			),
			$result
		);

		// This should fail because of the UNIQUE constraint
		$this->assertQuery(
			'UPDATE _tmp_table SET unique_name = NULL WHERE ID = 2;',
			'UNIQUE constraint failed: _tmp_table.unique_name'
		);

		// Inline unique constraint aren't supported currently, so this should pass
		$this->assertQuery(
			'UPDATE _tmp_table SET inline_unique_name = NULL WHERE ID = 2;',
			''
		);

		// WPDB allows for NULL values in columns that don't have a default value and a NOT NULL constraint
		$this->assertQuery(
			'UPDATE _tmp_table SET no_default = NULL WHERE ID = 2;',
			''
		);

		$result = $this->assertQuery( 'SELECT * FROM _tmp_table WHERE ID = 2' );
		$this->assertEquals(
			array(
				(object) array(
					'ID'                 => '2',
					'name'               => 'default-value',
					'unique_name'        => '2',
					'inline_unique_name' => 'inline-unique-default-value',
					'no_default'         => '',
				),
			),
			$result
		);
	}

	public function testDefaultNullValue() {
		$this->assertQuery(
			'CREATE TABLE _tmp_table (
				name varchar(20) NOT NULL default NULL,
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
					'Null'    => 'NO',
					'Key'     => '',
					'Default' => 'NULL',
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
		$this->assertQuery( 'UPDATE _dates SET option_value = NULL' );
		$results = $this->assertQuery( 'SELECT option_value AS t FROM _dates' );
		$this->assertCount( 1, $results );
		$this->assertEmpty( $results[0]->t );

		$this->assertQuery( 'UPDATE _dates SET option_value = CURRENT_TIMESTAMP()' );
		$results = $this->assertQuery( 'SELECT option_value AS t FROM _dates' );
		$this->assertCount( 1, $results );
		$this->assertRegExp( '/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $results[0]->t );

		// DELETE
		// We can only assert that the query passes. It is not guaranteed that we'll actually
		// delete the existing record, as the delete query could fall into a different second.
		$this->assertQuery( 'DELETE FROM _dates WHERE option_value = CURRENT_TIMESTAMP()' );
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
					':param0' => 'T',
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
}
