<?php

use PHPUnit\Framework\TestCase;

class WP_SQLite_Driver_Translation_Tests extends TestCase {
	const GRAMMAR_PATH = __DIR__ . '/../wp-includes/mysql/mysql-grammar.php';

	/**
	 * @var WP_Parser_Grammar
	 */
	private static $grammar;

	/**
	 * @var WP_SQLite_Driver
	 */
	private $driver;

	/**
	 * @var string
	 */
	private $strict_suffix;

	public static function setUpBeforeClass(): void {
		self::$grammar = new WP_Parser_Grammar( include self::GRAMMAR_PATH );
	}

	public function setUp(): void {
		$this->driver = new WP_SQLite_Driver(
			new WP_SQLite_Connection( array( 'path' => ':memory:' ) ),
			'wp'
		);

		$supports_strict_tables = version_compare( $this->driver->get_sqlite_version(), '3.37.0', '>=' );
		$this->strict_suffix    = $supports_strict_tables ? ' STRICT' : '';
	}

	public function testSelect(): void {
		$this->assertQuery(
			'SELECT 1',
			'SELECT 1'
		);

		$this->assertQuery(
			'SELECT * FROM `t`',
			'SELECT * FROM t'
		);

		$this->assertQuery(
			'SELECT `c` FROM `t`',
			'SELECT c FROM t'
		);

		$this->assertQuery(
			'SELECT ALL `c` FROM `t`',
			'SELECT ALL c FROM t'
		);

		$this->assertQuery(
			'SELECT DISTINCT `c` FROM `t`',
			'SELECT DISTINCT c FROM t'
		);

		$this->assertQuery(
			'SELECT `c1` , `c2` FROM `t`',
			'SELECT c1, c2 FROM t'
		);

		$this->assertQuery(
			'SELECT `t`.`c` FROM `t`',
			'SELECT t.c FROM t'
		);

		$this->assertQuery(
			'SELECT `c1` FROM `t` WHERE `c2` = \'abc\'',
			"SELECT c1 FROM t WHERE c2 = 'abc'"
		);

		$this->assertQuery(
			'SELECT `c` FROM `t` GROUP BY `c`',
			'SELECT c FROM t GROUP BY c'
		);

		$this->assertQuery(
			'SELECT `c` FROM `t` ORDER BY `c` ASC',
			'SELECT c FROM t ORDER BY c ASC'
		);

		$this->assertQuery(
			'SELECT `c` FROM `t` LIMIT 10',
			'SELECT c FROM t LIMIT 10'
		);

		$this->assertQuery(
			'SELECT `c` FROM `t` GROUP BY `c` HAVING COUNT ( `c` ) > 1',
			'SELECT c FROM t GROUP BY c HAVING COUNT(c) > 1'
		);

		$this->assertQuery(
			'SELECT * FROM `t1` LEFT JOIN `t2` ON `t1`.`id` = `t2`.`t1_id` WHERE `t1`.`name` = \'abc\'',
			"SELECT * FROM t1 LEFT JOIN t2 ON t1.id = t2.t1_id WHERE t1.name = 'abc'"
		);
	}

	public function testInsert(): void {
		$this->driver->query( 'CREATE TABLE t (c INT, c1 INT, c2 INT)' );
		$this->driver->query( 'CREATE TABLE t1 (c1 INT, c2 INT)' );
		$this->driver->query( 'CREATE TABLE t2 (c1 INT, c2 INT)' );
		$this->driver->query( 'INSERT INTO t2 VALUES (1, 2)' );

		$is_values_naming_supported = version_compare( $this->driver->get_sqlite_version(), '3.33.0', '>=' );

		$this->assertQuery(
			$is_values_naming_supported
				? 'INSERT INTO `t` (`c`) SELECT `column1` FROM (VALUES ( 1 )) WHERE true'
				: 'INSERT INTO `t` (`c`) SELECT `column1` FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 )) WHERE true',
			'INSERT INTO t (c) VALUES (1)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'INSERT INTO `t` (`c`) SELECT `column1` FROM (VALUES ( 1 )) WHERE true'
				: 'INSERT INTO `t` (`c`) SELECT `column1` FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 )) WHERE true',
			'INSERT INTO wp.t (c) VALUES (1)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'INSERT INTO `t` (`c1`, `c2`) SELECT `column1`, `column2` FROM (VALUES ( 1 , 2 )) WHERE true'
				: 'INSERT INTO `t` (`c1`, `c2`) SELECT `column1`, `column2` FROM (SELECT NULL AS `column1`, NULL AS `column2` WHERE FALSE UNION ALL VALUES ( 1 , 2 )) WHERE true',
			'INSERT INTO t (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'INSERT INTO `t` (`c`) SELECT `column1` FROM (VALUES ( 1 ) , ( 2 )) WHERE true'
				: 'INSERT INTO `t` (`c`) SELECT `column1` FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 ) , ( 2 )) WHERE true',
			'INSERT INTO t (c) VALUES (1), (2)'
		);

		$this->assertQuery(
			array(
				'SELECT * FROM (SELECT * FROM `t2`) LIMIT 1',
				'INSERT INTO `t1` (`c1`, `c2`) SELECT `c1`, `c2` FROM (SELECT * FROM `t2`) WHERE true',
			),
			'INSERT INTO t1 SELECT * FROM t2'
		);
	}

	public function testInsertWithTypeCasting(): void {
		$this->driver->query( 'CREATE TABLE t1 (c1 TEXT, c2 TEXT)' );
		$this->driver->query( 'CREATE TABLE t2 (c1 TEXT, c2 TEXT)' );
		$this->driver->query( 'INSERT INTO t2 VALUES (1, 2)' );

		$is_values_naming_supported = version_compare( $this->driver->get_sqlite_version(), '3.33.0', '>=' );

		$this->assertQuery(
			$is_values_naming_supported
				? 'INSERT INTO `t1` (`c1`) SELECT CAST(`column1` AS TEXT) FROM (VALUES ( 1 )) WHERE true'
				: 'INSERT INTO `t1` (`c1`) SELECT CAST(`column1` AS TEXT) FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 )) WHERE true',
			'INSERT INTO t1 (c1) VALUES (1)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'INSERT INTO `t1` (`c1`, `c2`) SELECT CAST(`column1` AS TEXT), CAST(`column2` AS TEXT) FROM (VALUES ( 1 , 2 )) WHERE true'
				: 'INSERT INTO `t1` (`c1`, `c2`) SELECT CAST(`column1` AS TEXT), CAST(`column2` AS TEXT) FROM (SELECT NULL AS `column1`, NULL AS `column2` WHERE FALSE UNION ALL VALUES ( 1 , 2 )) WHERE true',
			'INSERT INTO t1 (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'INSERT INTO `t1` (`c1`) SELECT CAST(`column1` AS TEXT) FROM (VALUES ( 1 ) , ( 2 )) WHERE true'
				: 'INSERT INTO `t1` (`c1`) SELECT CAST(`column1` AS TEXT) FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 ) , ( 2 )) WHERE true',
			'INSERT INTO t1 (c1) VALUES (1), (2)'
		);

		$this->assertQuery(
			array(
				'SELECT * FROM (SELECT * FROM `t2`) LIMIT 1',
				'INSERT INTO `t1` (`c1`, `c2`) SELECT CAST(`c1` AS TEXT), CAST(`c2` AS TEXT) FROM (SELECT * FROM `t2`) WHERE true',
			),
			'INSERT INTO t1 SELECT * FROM t2'
		);
	}

	public function testReplace(): void {
		$this->driver->query( 'CREATE TABLE t (c INT, c1 INT, c2 INT)' );
		$this->driver->query( 'CREATE TABLE t1 (c1 INT, c2 INT)' );
		$this->driver->query( 'CREATE TABLE t2 (c1 INT, c2 INT)' );
		$this->driver->query( 'INSERT INTO t2 VALUES (1, 2)' );

		$is_values_naming_supported = version_compare( $this->driver->get_sqlite_version(), '3.33.0', '>=' );

		$this->assertQuery(
			$is_values_naming_supported
				? 'REPLACE INTO `t` (`c`) SELECT `column1` FROM (VALUES ( 1 )) WHERE true'
				: 'REPLACE INTO `t` (`c`) SELECT `column1` FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 )) WHERE true',
			'REPLACE INTO t (c) VALUES (1)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'REPLACE INTO `t` (`c`) SELECT `column1` FROM (VALUES ( 1 )) WHERE true'
				: 'REPLACE INTO `t` (`c`) SELECT `column1` FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 )) WHERE true',
			'REPLACE INTO wp.t (c) VALUES (1)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'REPLACE INTO `t` (`c1`, `c2`) SELECT `column1`, `column2` FROM (VALUES ( 1 , 2 )) WHERE true'
				: 'REPLACE INTO `t` (`c1`, `c2`) SELECT `column1`, `column2` FROM (SELECT NULL AS `column1`, NULL AS `column2` WHERE FALSE UNION ALL VALUES ( 1 , 2 )) WHERE true',
			'REPLACE INTO t (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'REPLACE INTO `t` (`c`) SELECT `column1` FROM (VALUES ( 1 ) , ( 2 )) WHERE true'
				: 'REPLACE INTO `t` (`c`) SELECT `column1` FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 ) , ( 2 )) WHERE true',
			'REPLACE INTO t (c) VALUES (1), (2)'
		);

		$this->assertQuery(
			array(
				'SELECT * FROM (SELECT * FROM `t2`) LIMIT 1',
				'REPLACE INTO `t1` (`c1`, `c2`) SELECT `c1`, `c2` FROM (SELECT * FROM `t2`) WHERE true',
			),
			'REPLACE INTO t1 SELECT * FROM t2'
		);
	}

	public function testReplaceWithTypeCasting(): void {
		$this->driver->query( 'CREATE TABLE t1 (c1 TEXT, c2 TEXT)' );
		$this->driver->query( 'CREATE TABLE t2 (c1 TEXT, c2 TEXT)' );
		$this->driver->query( 'INSERT INTO t2 VALUES (1, 2)' );

		$is_values_naming_supported = version_compare( $this->driver->get_sqlite_version(), '3.33.0', '>=' );

		$this->assertQuery(
			$is_values_naming_supported
				? 'REPLACE INTO `t1` (`c1`) SELECT CAST(`column1` AS TEXT) FROM (VALUES ( 1 )) WHERE true'
				: 'REPLACE INTO `t1` (`c1`) SELECT CAST(`column1` AS TEXT) FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 )) WHERE true',
			'REPLACE INTO t1 (c1) VALUES (1)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'REPLACE INTO `t1` (`c1`, `c2`) SELECT CAST(`column1` AS TEXT), CAST(`column2` AS TEXT) FROM (VALUES ( 1 , 2 )) WHERE true'
				: 'REPLACE INTO `t1` (`c1`, `c2`) SELECT CAST(`column1` AS TEXT), CAST(`column2` AS TEXT) FROM (SELECT NULL AS `column1`, NULL AS `column2` WHERE FALSE UNION ALL VALUES ( 1 , 2 )) WHERE true',
			'REPLACE INTO t1 (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			$is_values_naming_supported
				? 'REPLACE INTO `t1` (`c1`) SELECT CAST(`column1` AS TEXT) FROM (VALUES ( 1 ) , ( 2 )) WHERE true'
				: 'REPLACE INTO `t1` (`c1`) SELECT CAST(`column1` AS TEXT) FROM (SELECT NULL AS `column1` WHERE FALSE UNION ALL VALUES ( 1 ) , ( 2 )) WHERE true',
			'REPLACE INTO t1 (c1) VALUES (1), (2)'
		);

		$this->assertQuery(
			array(
				'SELECT * FROM (SELECT * FROM `t2`) LIMIT 1',
				'REPLACE INTO `t1` (`c1`, `c2`) SELECT CAST(`c1` AS TEXT), CAST(`c2` AS TEXT) FROM (SELECT * FROM `t2`) WHERE true',
			),
			'REPLACE INTO t1 SELECT * FROM t2'
		);
	}

	public function testUpdate(): void {
		$this->driver->query( 'CREATE TABLE t (c INT, c1 INT, c2 INT)' );
		$this->driver->query( 'CREATE TABLE t1 (id INT, c1 INT, c2 INT)' );
		$this->driver->query( 'CREATE TABLE t2 (id INT, c1 INT, c2 INT)' );

		$this->assertQuery(
			'UPDATE `t` SET `c` = 1',
			'UPDATE t SET c = 1'
		);

		$this->assertQuery(
			'UPDATE `t` SET `c` = 1',
			'UPDATE wp.t SET c = 1'
		);

		$this->assertQuery(
			'UPDATE `t` SET `c1` = 1, `c2` = 2',
			'UPDATE t SET c1 = 1, c2 = 2'
		);

		$this->assertQuery(
			'UPDATE `t` SET `c` = 1 WHERE `c` = 2',
			'UPDATE t SET c = 1 WHERE c = 2'
		);

		// UPDATE with a table alias.
		$this->assertQuery(
			'UPDATE `t` AS `a` SET `c` = 1 WHERE `a`.`c` = 2',
			'UPDATE t AS a SET c = 1 WHERE a.c = 2'
		);

		$this->assertQuery(
			'UPDATE `t` AS `a` SET `c` = 1 WHERE `a`.`c` = 2',
			'UPDATE t AS a SET a.c = 1 WHERE a.c = 2'
		);

		// UPDATE with LIMIT.
		$this->assertQuery(
			'UPDATE `t` SET `c` = 1 WHERE rowid IN ( SELECT rowid FROM `t` LIMIT 1 )',
			'UPDATE t SET c = 1 LIMIT 1'
		);

		// UPDATE with ORDER BY and LIMIT.
		$this->assertQuery(
			'UPDATE `t` SET `c` = 1 WHERE rowid IN ( SELECT rowid FROM `t` ORDER BY `c` ASC LIMIT 1 )',
			'UPDATE t SET c = 1 ORDER BY c ASC LIMIT 1'
		);

		// UPDATE with multiple tables.
		$this->assertQuery(
			'UPDATE `t1` SET `id` = 1 FROM `t2` WHERE `t1`.`c` = `t2`.`c`',
			'UPDATE t1, t2 SET t1.id = 1 WHERE t1.c = t2.c'
		);

		// UPDATE with JOIN.
		$this->assertQuery(
			'UPDATE `t1` SET `id` = 1 FROM `t2` WHERE `t1`.`c` = 2 AND `t1`.`c` = `t2`.`c`',
			'UPDATE t1 JOIN t2 ON t1.c = t2.c SET t1.id = 1 WHERE t1.c = 2'
		);

		// UPDATE with JOIN using a derived table.
		$this->assertQuery(
			'UPDATE `t1` SET `id` = 1 FROM ( SELECT * FROM `t2` ) AS `t2` WHERE `t1`.`c` = 2 AND `t1`.`c` = `t2`.`c`',
			'UPDATE t1 JOIN ( SELECT * FROM t2 ) AS t2 ON t1.c = t2.c SET t1.id = 1 WHERE t1.c = 2'
		);
	}

	public function testDelete(): void {
		$this->assertQuery(
			'DELETE FROM `t`',
			'DELETE FROM t'
		);

		$this->assertQuery(
			'DELETE FROM `t`',
			'DELETE FROM wp.t'
		);

		$this->assertQuery(
			'DELETE FROM `t` WHERE `c` = 1',
			'DELETE FROM t WHERE c = 1'
		);
	}

	public function testCreateTable(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER )' . $this->strict_suffix,
			'CREATE TABLE t (id INT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCreateTableWithMultipleColumns(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER, `name` TEXT COLLATE NOCASE, `score` REAL DEFAULT \'0.0\' )' . $this->strict_suffix,
			'CREATE TABLE t (id INT, name TEXT, score FLOAT DEFAULT 0.0)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'name', 2, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'text', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'score', 3, '0.0', 'YES', 'float', null, null, 12, null, null, null, null, 'float', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCreateTableWithBasicConstraints(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )' . $this->strict_suffix,
			'CREATE TABLE t (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't', 0, 'sqlite_database', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't', 'sqlite_database', 'PRIMARY', 'PRIMARY KEY', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'PRIMARY', 'sqlite_database', 't', 'id', 1, null, null, null, null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCreateTableWithEngine(): void {
		// ENGINE is not supported in SQLite, we save it in information schema.
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER )' . $this->strict_suffix,
			'CREATE TABLE t (id INT) ENGINE=MyISAM'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'MyISAM', 'Fixed', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCreateTableWithCollate(): void {
		// COLLATE is not supported in SQLite, we save it in information schema.
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER )' . $this->strict_suffix,
			'CREATE TABLE t (id INT) COLLATE utf8mb4_czech_ci'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_czech_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCreateTableWithPrimaryKey(): void {
		/*
		 * PRIMARY KEY without AUTOINCREMENT:
		 * In this case, integer must be represented as INT, not INTEGER. SQLite
		 * treats "INTEGER PRIMARY KEY" as an alias for ROWID, causing unintended
		 * auto-increment-like behavior for a non-autoincrement column.
		 *
		 * See:
		 *  https://www.sqlite.org/lang_createtable.html#rowids_and_the_integer_primary_key
		 */
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INT NOT NULL, PRIMARY KEY (`id`) )' . $this->strict_suffix,
			'CREATE TABLE t (id INT PRIMARY KEY)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't', 0, 'sqlite_database', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't', 'sqlite_database', 'PRIMARY', 'PRIMARY KEY', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'PRIMARY', 'sqlite_database', 't', 'id', 1, null, null, null, null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCreateTableWithPrimaryKeyAndAutoincrement(): void {
		// With AUTOINCREMENT, we expect "INTEGER".
		$this->assertQuery(
			'CREATE TABLE `t1` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )' . $this->strict_suffix,
			'CREATE TABLE t1 (id INT PRIMARY KEY AUTO_INCREMENT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't1', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't1', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't1', 0, 'sqlite_database', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't1', 'sqlite_database', 'PRIMARY', 'PRIMARY KEY', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'PRIMARY', 'sqlite_database', 't1', 'id', 1, null, null, null, null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't1'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't1' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't1' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't1' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't1' ORDER BY tc.constraint_name",
			)
		);

		// In SQLite, PRIMARY KEY must come before AUTOINCREMENT.
		$this->assertQuery(
			'CREATE TABLE `t2` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )' . $this->strict_suffix,
			'CREATE TABLE t2 (id INT AUTO_INCREMENT PRIMARY KEY)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't2', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't2', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't2', 0, 'sqlite_database', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't2', 'sqlite_database', 'PRIMARY', 'PRIMARY KEY', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'PRIMARY', 'sqlite_database', 't2', 'id', 1, null, null, null, null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't2'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't2' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't2' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't2' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't2' ORDER BY tc.constraint_name",
			)
		);

		// In SQLite, AUTOINCREMENT cannot be specified separately from PRIMARY KEY.
		$this->assertQuery(
			'CREATE TABLE `t3` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )' . $this->strict_suffix,
			'CREATE TABLE t3 (id INT AUTO_INCREMENT, PRIMARY KEY(id))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't3', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't3', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', 'auto_increment', 'select,insert,update,references', '', '', null)",
				"SELECT column_name, data_type, is_nullable, character_maximum_length FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't3' AND column_name IN ('id')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't3', 0, 'sqlite_database', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				"UPDATE `_wp_sqlite_mysql_information_schema_columns` AS c SET (column_key, is_nullable) = ( SELECT CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI' WHEN MAX(s.seq_in_index = 1) THEN 'MUL' ELSE '' END, CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO' ELSE c.is_nullable END FROM `_wp_sqlite_mysql_information_schema_statistics` AS s WHERE s.table_schema = c.table_schema AND s.table_name = c.table_name AND s.column_name = c.column_name ) WHERE c.table_schema = 'sqlite_database' AND c.table_name = 't3'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't3', 'sqlite_database', 'PRIMARY', 'PRIMARY KEY', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'PRIMARY', 'sqlite_database', 't3', 'id', 1, null, null, null, null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't3'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't3' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't3' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't3' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't3' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCreateTableWithInlineUniqueIndexes(): void {
		$this->assertQuery(
			array(
				'CREATE TABLE `t` ( `id` INTEGER, `name` TEXT COLLATE NOCASE )' . $this->strict_suffix,
				'CREATE UNIQUE INDEX `t__id` ON `t` (`id`)',
				'CREATE UNIQUE INDEX `t__name` ON `t` (`name`)',
			),
			'CREATE TABLE t (id INT UNIQUE, name TEXT UNIQUE)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', 'UNI', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't', 0, 'sqlite_database', 'id', 1, 'id', 'A', 0, null, null, 'YES', 'BTREE', '', '', 'YES', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't', 'sqlite_database', 'id', 'UNIQUE', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'id', 'sqlite_database', 't', 'id', 1, null, null, null, null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'name', 2, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'text', 'UNI', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't', 0, 'sqlite_database', 'name', 1, 'name', 'A', 0, null, null, 'YES', 'BTREE', '', '', 'YES', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't', 'sqlite_database', 'name', 'UNIQUE', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'name', 'sqlite_database', 't', 'name', 1, null, null, null, null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCreateTableWithStandaloneUniqueIndexes(): void {
		$this->assertQuery(
			array(
				'CREATE TABLE `t` ( `id` INTEGER, `name` TEXT COLLATE NOCASE )' . $this->strict_suffix,
				'CREATE UNIQUE INDEX `t__id` ON `t` (`id`)',
				'CREATE UNIQUE INDEX `t__name` ON `t` (`name`)',
			),
			'CREATE TABLE t (id INT, name VARCHAR(100), UNIQUE (id), UNIQUE (name))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'name', 2, null, 'YES', 'varchar', 100, 400, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'varchar(100)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT column_name, data_type, is_nullable, character_maximum_length FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND column_name IN ('id')",
				"SELECT DISTINCT index_name FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND (index_name = 'id' OR index_name LIKE 'id\_%' ESCAPE '\\')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't', 0, 'sqlite_database', 'id', 1, 'id', 'A', 0, null, null, 'YES', 'BTREE', '', '', 'YES', null)",
				"UPDATE `_wp_sqlite_mysql_information_schema_columns` AS c SET (column_key, is_nullable) = ( SELECT CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI' WHEN MAX(s.seq_in_index = 1) THEN 'MUL' ELSE '' END, CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO' ELSE c.is_nullable END FROM `_wp_sqlite_mysql_information_schema_statistics` AS s WHERE s.table_schema = c.table_schema AND s.table_name = c.table_name AND s.column_name = c.column_name ) WHERE c.table_schema = 'sqlite_database' AND c.table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't', 'sqlite_database', 'id', 'UNIQUE', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'id', 'sqlite_database', 't', 'id', 1, null, null, null, null)",
				"SELECT column_name, data_type, is_nullable, character_maximum_length FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND column_name IN ('name')",
				"SELECT DISTINCT index_name FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND (index_name = 'name' OR index_name LIKE 'name\_%' ESCAPE '\\')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_statistics` (`table_schema`, `table_name`, `non_unique`, `index_schema`, `index_name`, `seq_in_index`, `column_name`, `collation`, `cardinality`, `sub_part`, `packed`, `nullable`, `index_type`, `comment`, `index_comment`, `is_visible`, `expression`)'
					. " VALUES ('sqlite_database', 't', 0, 'sqlite_database', 'name', 1, 'name', 'A', 0, null, null, 'YES', 'BTREE', '', '', 'YES', null)",
				"UPDATE `_wp_sqlite_mysql_information_schema_columns` AS c SET (column_key, is_nullable) = ( SELECT CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI' WHEN MAX(s.seq_in_index = 1) THEN 'MUL' ELSE '' END, CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO' ELSE c.is_nullable END FROM `_wp_sqlite_mysql_information_schema_statistics` AS s WHERE s.table_schema = c.table_schema AND s.table_name = c.table_name AND s.column_name = c.column_name ) WHERE c.table_schema = 'sqlite_database' AND c.table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_table_constraints` (`table_schema`, `table_name`, `constraint_schema`, `constraint_name`, `constraint_type`, `enforced`)'
					. " VALUES ('sqlite_database', 't', 'sqlite_database', 'name', 'UNIQUE', 'YES')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_key_column_usage` (`constraint_schema`, `constraint_name`, `table_schema`, `table_name`, `column_name`, `ordinal_position`, `position_in_unique_constraint`, `referenced_table_schema`, `referenced_table_name`, `referenced_column_name`)'
					. " VALUES ('sqlite_database', 'name', 'sqlite_database', 't', 'name', 1, null, null, null, null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	// @TODO: Implement information schema support for CREATE TABLE ... AS SELECT.
	/*public function testCreateTableFromSelectQuery(): void {
		// CREATE TABLE AS SELECT ...
		$this->assertQuery(
			'CREATE TABLE `t1` AS SELECT * FROM `t2` STRICT',
			'CREATE TABLE t1 AS SELECT * FROM t2'
		);

		// CREATE TABLE SELECT ...
		// The "AS" keyword is optional in MySQL, but required in SQLite.
		$this->assertQuery(
			'CREATE TABLE `t1` AS SELECT * FROM `t2` STRICT',
			'CREATE TABLE t1 SELECT * FROM t2'
		);
	}*/

	public function testCreateTemporaryTable(): void {
		$this->assertQuery(
			'CREATE TEMPORARY TABLE `t` ( `id` INTEGER )' . $this->strict_suffix,
			'CREATE TEMPORARY TABLE t (id INT)'
		);
	}

	public function testDropTemporaryTable(): void {
		// Create a temporary table first so DROP doesn't fail.
		$this->driver->query( 'CREATE TEMPORARY TABLE t (id INT)' );

		$this->assertQuery(
			'DROP TABLE `temp`.`t`',
			'DROP TEMPORARY TABLE t'
		);

		// With IF NOT EXISTS.
		$this->assertQuery(
			'DROP TABLE IF EXISTS `temp`.`t`',
			'DROP TEMPORARY TABLE IF EXISTS t'
		);
	}

	public function testAlterTableAddColumn(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'a', 2, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testAlterTableAddColumnWithNotNull(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER NOT NULL )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT NOT NULL'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'a', 2, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testAlterTableAddColumnWithDefault(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER DEFAULT \'0\' )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT DEFAULT 0'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'a', 2, '0', 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testAlterTableAddColumnWithNotNullAndDefault(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER NOT NULL DEFAULT \'0\' )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT NOT NULL DEFAULT 0'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'a', 2, '0', 'NO', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testAlterTableAddMultipleColumns(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER, `b` TEXT COLLATE NOCASE, `c` INTEGER )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT, ADD b TEXT, ADD c BOOL'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'a', 2, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'b', 3, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'text', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c', 4, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint(1)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testAlterTableDropColumn(): void {
		$this->driver->query( 'CREATE TABLE t (id INT, a TEXT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t DROP a'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'a'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'a'",
				"WITH renumbered AS ( SELECT rowid, row_number() OVER (PARTITION BY index_name ORDER BY seq_in_index) AS seq_in_index FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ) UPDATE `_wp_sqlite_mysql_information_schema_statistics` AS statistics SET seq_in_index = (SELECT seq_in_index FROM renumbered WHERE rowid = statistics.rowid) WHERE statistics.rowid IN (SELECT rowid FROM renumbered)",
				"UPDATE `_wp_sqlite_mysql_information_schema_columns` AS c SET (column_key, is_nullable) = ( SELECT CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI' WHEN MAX(s.seq_in_index = 1) THEN 'MUL' ELSE '' END, CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO' ELSE c.is_nullable END FROM `_wp_sqlite_mysql_information_schema_statistics` AS s WHERE s.table_schema = c.table_schema AND s.table_name = c.table_name AND s.column_name = c.column_name ) WHERE c.table_schema = 'sqlite_database' AND c.table_name = 't'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_table_constraints` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND constraint_type IN ('PRIMARY KEY', 'UNIQUE') AND constraint_name NOT IN ( SELECT DISTINCT index_name FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' )",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);  }

	public function testAlterTableDropMultipleColumns(): void {
		$this->driver->query( 'CREATE TABLE t (id INT, a INT, b TEXT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t DROP a, DROP b'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'a'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'a'",
				"WITH renumbered AS ( SELECT rowid, row_number() OVER (PARTITION BY index_name ORDER BY seq_in_index) AS seq_in_index FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ) UPDATE `_wp_sqlite_mysql_information_schema_statistics` AS statistics SET seq_in_index = (SELECT seq_in_index FROM renumbered WHERE rowid = statistics.rowid) WHERE statistics.rowid IN (SELECT rowid FROM renumbered)",
				"UPDATE `_wp_sqlite_mysql_information_schema_columns` AS c SET (column_key, is_nullable) = ( SELECT CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI' WHEN MAX(s.seq_in_index = 1) THEN 'MUL' ELSE '' END, CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO' ELSE c.is_nullable END FROM `_wp_sqlite_mysql_information_schema_statistics` AS s WHERE s.table_schema = c.table_schema AND s.table_name = c.table_name AND s.column_name = c.column_name ) WHERE c.table_schema = 'sqlite_database' AND c.table_name = 't'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_table_constraints` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND constraint_type IN ('PRIMARY KEY', 'UNIQUE') AND constraint_name NOT IN ( SELECT DISTINCT index_name FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' )",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'b'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'b'",
				"WITH renumbered AS ( SELECT rowid, row_number() OVER (PARTITION BY index_name ORDER BY seq_in_index) AS seq_in_index FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ) UPDATE `_wp_sqlite_mysql_information_schema_statistics` AS statistics SET seq_in_index = (SELECT seq_in_index FROM renumbered WHERE rowid = statistics.rowid) WHERE statistics.rowid IN (SELECT rowid FROM renumbered)",
				"UPDATE `_wp_sqlite_mysql_information_schema_columns` AS c SET (column_key, is_nullable) = ( SELECT CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI' WHEN MAX(s.seq_in_index = 1) THEN 'MUL' ELSE '' END, CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO' ELSE c.is_nullable END FROM `_wp_sqlite_mysql_information_schema_statistics` AS s WHERE s.table_schema = c.table_schema AND s.table_name = c.table_name AND s.column_name = c.column_name ) WHERE c.table_schema = 'sqlite_database' AND c.table_name = 't'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_table_constraints` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND constraint_type IN ('PRIMARY KEY', 'UNIQUE') AND constraint_name NOT IN ( SELECT DISTINCT index_name FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' )",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testAlterTableAddAndDropColumns(): void {
		$this->driver->query( 'CREATE TABLE t (a INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `b` INTEGER )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`) SELECT `rowid` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD b INT, DROP a'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'b', 2, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'a'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'a'",
				"WITH renumbered AS ( SELECT rowid, row_number() OVER (PARTITION BY index_name ORDER BY seq_in_index) AS seq_in_index FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ) UPDATE `_wp_sqlite_mysql_information_schema_statistics` AS statistics SET seq_in_index = (SELECT seq_in_index FROM renumbered WHERE rowid = statistics.rowid) WHERE statistics.rowid IN (SELECT rowid FROM renumbered)",
				"UPDATE `_wp_sqlite_mysql_information_schema_columns` AS c SET (column_key, is_nullable) = ( SELECT CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI' WHEN MAX(s.seq_in_index = 1) THEN 'MUL' ELSE '' END, CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO' ELSE c.is_nullable END FROM `_wp_sqlite_mysql_information_schema_statistics` AS s WHERE s.table_schema = c.table_schema AND s.table_name = c.table_name AND s.column_name = c.column_name ) WHERE c.table_schema = 'sqlite_database' AND c.table_name = 't'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_table_constraints` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND constraint_type IN ('PRIMARY KEY', 'UNIQUE') AND constraint_name NOT IN ( SELECT DISTINCT index_name FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' )",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testAlterTableDropAndAddSingleColumn(): void {
		$this->driver->query( 'CREATE TABLE t (a INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `a` INTEGER )' . $this->strict_suffix,
				'INSERT INTO `<tmp-table>` (`rowid`) SELECT `rowid` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t DROP a, ADD a INT'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME, LOWER(COLUMN_NAME) AS COLUMN_NAME_LOWERCASE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_columns` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'a'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE `table_schema` = 'sqlite_database' AND `table_name` = 't' AND `column_name` = 'a'",
				"WITH renumbered AS ( SELECT rowid, row_number() OVER (PARTITION BY index_name ORDER BY seq_in_index) AS seq_in_index FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ) UPDATE `_wp_sqlite_mysql_information_schema_statistics` AS statistics SET seq_in_index = (SELECT seq_in_index FROM renumbered WHERE rowid = statistics.rowid) WHERE statistics.rowid IN (SELECT rowid FROM renumbered)",
				"UPDATE `_wp_sqlite_mysql_information_schema_columns` AS c SET (column_key, is_nullable) = ( SELECT CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(s.non_unique = 0 AND s.seq_in_index = 1) THEN 'UNI' WHEN MAX(s.seq_in_index = 1) THEN 'MUL' ELSE '' END, CASE WHEN MAX(s.index_name = 'PRIMARY') THEN 'NO' ELSE c.is_nullable END FROM `_wp_sqlite_mysql_information_schema_statistics` AS s WHERE s.table_schema = c.table_schema AND s.table_name = c.table_name AND s.column_name = c.column_name ) WHERE c.table_schema = 'sqlite_database' AND c.table_name = 't'",
				"DELETE FROM `_wp_sqlite_mysql_information_schema_table_constraints` WHERE table_schema = 'sqlite_database' AND table_name = 't' AND constraint_type IN ('PRIMARY KEY', 'UNIQUE') AND constraint_name NOT IN ( SELECT DISTINCT index_name FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' )",
				"SELECT MAX(ordinal_position) FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't'",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'a', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testBitDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `i1` INTEGER, `i2` INTEGER )' . $this->strict_suffix,
			'CREATE TABLE t (i1 BIT, i2 BIT(10))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i1', 1, null, 'YES', 'bit', null, null, 1, null, null, null, null, 'bit(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i2', 2, null, 'YES', 'bit', null, null, 10, null, null, null, null, 'bit(10)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testBooleanDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `i1` INTEGER, `i2` INTEGER )' . $this->strict_suffix,
			'CREATE TABLE t (i1 BOOL, i2 BOOLEAN)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i1', 1, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i2', 2, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint(1)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testIntegerDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `i1` INTEGER, `i2` INTEGER, `i3` INTEGER, `i4` INTEGER, `i5` INTEGER, `i6` INTEGER )' . $this->strict_suffix,
			'CREATE TABLE t (i1 TINYINT, i2 SMALLINT, i3 MEDIUMINT, i4 INT, i5 INTEGER, i6 BIGINT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i1', 1, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i2', 2, null, 'YES', 'smallint', null, null, 5, 0, null, null, null, 'smallint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i3', 3, null, 'YES', 'mediumint', null, null, 7, 0, null, null, null, 'mediumint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i4', 4, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i5', 5, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'i6', 6, null, 'YES', 'bigint', null, null, 19, 0, null, null, null, 'bigint', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testFloatDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `f1` REAL, `f2` REAL, `f3` REAL, `f4` REAL )' . $this->strict_suffix,
			'CREATE TABLE t (f1 FLOAT, f2 DOUBLE, f3 DOUBLE PRECISION, f4 REAL)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'f1', 1, null, 'YES', 'float', null, null, 12, null, null, null, null, 'float', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'f2', 2, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'f3', 3, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'f4', 4, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testDecimalTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `f1` REAL, `f2` REAL, `f3` REAL, `f4` REAL )' . $this->strict_suffix,
			'CREATE TABLE t (f1 DECIMAL, f2 DEC, f3 FIXED, f4 NUMERIC)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'f1', 1, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'f2', 2, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'f3', 3, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'f4', 4, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testCharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (c1 CHAR, c2 CHAR(10))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c1', 1, null, 'YES', 'char', 1, 4, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'char(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c2', 2, null, 'YES', 'char', 10, 40, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'char(10)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE, `c3` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (c1 VARCHAR(255), c2 CHAR VARYING(255), c3 CHARACTER VARYING(255))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c1', 1, null, 'YES', 'varchar', 255, 1020, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c2', 2, null, 'YES', 'varchar', 255, 1020, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c3', 3, null, 'YES', 'varchar', 255, 1020, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testNationalCharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE, `c3` TEXT COLLATE NOCASE, `c4` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (c1 NATIONAL CHAR, c2 NCHAR, c3 NATIONAL CHAR (10), c4 NCHAR(10))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c1', 1, null, 'YES', 'char', 1, 3, null, null, null, 'utf8', 'utf8_general_ci', 'char(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c2', 2, null, 'YES', 'char', 1, 3, null, null, null, 'utf8', 'utf8_general_ci', 'char(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c3', 3, null, 'YES', 'char', 10, 30, null, null, null, 'utf8', 'utf8_general_ci', 'char(10)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c4', 4, null, 'YES', 'char', 10, 30, null, null, null, 'utf8', 'utf8_general_ci', 'char(10)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testNcharVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE, `c3` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (c1 NCHAR VARCHAR(255), c2 NCHAR VARYING(255), c3 NVARCHAR(255))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c1', 1, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c2', 2, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c3', 3, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testNationalVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE, `c3` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (c1 NATIONAL VARCHAR(255), c2 NATIONAL CHAR VARYING(255), c3 NATIONAL CHARACTER VARYING(255))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c1', 1, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c2', 2, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'c3', 3, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testTextDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `t1` TEXT COLLATE NOCASE, `t2` TEXT COLLATE NOCASE, `t3` TEXT COLLATE NOCASE, `t4` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (t1 TINYTEXT, t2 TEXT, t3 MEDIUMTEXT, t4 LONGTEXT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 't1', 1, null, 'YES', 'tinytext', 255, 255, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'tinytext', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 't2', 2, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'text', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 't3', 3, null, 'YES', 'mediumtext', 16777215, 16777215, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'mediumtext', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 't4', 4, null, 'YES', 'longtext', 4294967295, 4294967295, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'longtext', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testEnumDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `e` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (e ENUM("a", "b", "c"))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'e', 1, null, 'YES', 'enum', 1, 4, null, null, null, 'utf8mb4', 'utf8mb4_0900_ai_ci', 'enum(''a'',''b'',''c'')', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testDateAndTimeDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `d` TEXT COLLATE NOCASE, `t` TEXT COLLATE NOCASE, `dt` TEXT COLLATE NOCASE, `ts` TEXT COLLATE NOCASE, `y` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (d DATE, t TIME, dt DATETIME, ts TIMESTAMP, y YEAR)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'd', 1, null, 'YES', 'date', null, null, null, null, null, null, null, 'date', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 't', 2, null, 'YES', 'time', null, null, null, null, 0, null, null, 'time', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'dt', 3, null, 'YES', 'datetime', null, null, null, null, 0, null, null, 'datetime', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'ts', 4, null, 'YES', 'timestamp', null, null, null, null, 0, null, null, 'timestamp', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'y', 5, null, 'YES', 'year', null, null, null, null, null, null, null, 'year', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testBinaryDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `b` BLOB, `v` BLOB )' . $this->strict_suffix,
			'CREATE TABLE t (b BINARY, v VARBINARY(255))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'b', 1, null, 'YES', 'binary', 1, 1, null, null, null, null, null, 'binary(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'v', 2, null, 'YES', 'varbinary', 255, 255, null, null, null, null, null, 'varbinary(255)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testBlobDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `b1` BLOB, `b2` BLOB, `b3` BLOB, `b4` BLOB )' . $this->strict_suffix,
			'CREATE TABLE t (b1 TINYBLOB, b2 BLOB, b3 MEDIUMBLOB, b4 LONGBLOB)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'b1', 1, null, 'YES', 'tinyblob', 255, 255, null, null, null, null, null, 'tinyblob', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'b2', 2, null, 'YES', 'blob', 65535, 65535, null, null, null, null, null, 'blob', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'b3', 3, null, 'YES', 'mediumblob', 16777215, 16777215, null, null, null, null, null, 'mediumblob', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'b4', 4, null, 'YES', 'longblob', 4294967295, 4294967295, null, null, null, null, null, 'longblob', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testBasicSpatialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `g1` TEXT COLLATE NOCASE, `g2` TEXT COLLATE NOCASE, `g3` TEXT COLLATE NOCASE, `g4` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (g1 GEOMETRY, g2 POINT, g3 LINESTRING, g4 POLYGON)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g1', 1, null, 'YES', 'geometry', null, null, null, null, null, null, null, 'geometry', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g2', 2, null, 'YES', 'point', null, null, null, null, null, null, null, 'point', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g3', 3, null, 'YES', 'linestring', null, null, null, null, null, null, null, 'linestring', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g4', 4, null, 'YES', 'polygon', null, null, null, null, null, null, null, 'polygon', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testMultiObjectSpatialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `g1` TEXT COLLATE NOCASE, `g2` TEXT COLLATE NOCASE, `g3` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (g1 MULTIPOINT, g2 MULTILINESTRING, g3 MULTIPOLYGON)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g1', 1, null, 'YES', 'multipoint', null, null, null, null, null, null, null, 'multipoint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g2', 2, null, 'YES', 'multilinestring', null, null, null, null, null, null, null, 'multilinestring', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g3', 3, null, 'YES', 'multipolygon', null, null, null, null, null, null, null, 'multipolygon', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testGeometryCollectionDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `g1` TEXT COLLATE NOCASE, `g2` TEXT COLLATE NOCASE )' . $this->strict_suffix,
			'CREATE TABLE t (g1 GEOMCOLLECTION, g2 GEOMETRYCOLLECTION)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g1', 1, null, 'YES', 'geomcollection', null, null, null, null, null, null, null, 'geomcollection', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'g2', 2, null, 'YES', 'geomcollection', null, null, null, null, null, null, null, 'geomcollection', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testSerialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )' . $this->strict_suffix,
			'CREATE TABLE t (id SERIAL)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO `_wp_sqlite_mysql_information_schema_tables` (`table_schema`, `table_name`, `table_type`, `engine`, `row_format`, `table_collation`, `table_comment`)'
					. " VALUES ('sqlite_database', 't', 'BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb4_0900_ai_ci', '')",
				'INSERT INTO `_wp_sqlite_mysql_information_schema_columns` (`table_schema`, `table_name`, `column_name`, `ordinal_position`, `column_default`, `is_nullable`, `data_type`, `character_maximum_length`, `character_octet_length`, `numeric_precision`, `numeric_scale`, `datetime_precision`, `character_set_name`, `collation_name`, `column_type`, `column_key`, `extra`, `privileges`, `column_comment`, `generation_expression`, `srs_id`)'
					. " VALUES ('sqlite_database', 't', 'id', 1, null, 'NO', 'bigint', null, null, 20, 0, null, null, null, 'bigint unsigned', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_tables` WHERE table_type = 'BASE TABLE' AND table_schema = 'sqlite_database' AND table_name = 't'",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_columns` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY ordinal_position",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_statistics` WHERE table_schema = 'sqlite_database' AND table_name = 't' ORDER BY INDEX_NAME = 'PRIMARY' DESC, NON_UNIQUE = '0' DESC, INDEX_TYPE = 'SPATIAL' DESC, INDEX_TYPE = 'BTREE' DESC, INDEX_TYPE = 'FULLTEXT' DESC, ROWID, SEQ_IN_INDEX",
				"SELECT * FROM `_wp_sqlite_mysql_information_schema_referential_constraints` WHERE constraint_schema = 'sqlite_database' AND table_name = 't' ORDER BY constraint_name",
				"SELECT tc.*, cc.check_clause FROM `_wp_sqlite_mysql_information_schema_table_constraints` tc JOIN `_wp_sqlite_mysql_information_schema_check_constraints` cc ON cc.constraint_name = tc.constraint_name WHERE tc.constraint_schema = 'sqlite_database' AND tc.table_name = 't' ORDER BY tc.constraint_name",
			)
		);
	}

	public function testBinaryLiterals(): void {
		// All binary literal syntaxes need to be converted to HEX strings.
		$this->assertQuery(
			"SELECT x'417a' AS `b'0100000101111010'`",
			"SELECT b'0100000101111010'"
		);
		$this->assertQuery(
			"SELECT x'417a' AS `B'0100000101111010'`",
			"SELECT B'0100000101111010'"
		);
		$this->assertQuery(
			"SELECT x'417a' AS `0b0100000101111010`",
			'SELECT 0b0100000101111010'
		);
	}

	public function testHexadecimalLiterals(): void {
		// The x'...' and X'...' syntax should be preserved as is.
		$this->assertQuery(
			"SELECT x'417a'",
			"SELECT x'417a'"
		);
		$this->assertQuery(
			"SELECT X'417a'",
			"SELECT X'417a'"
		);

		// The 0x... syntax needs to be translated to x'...'.
		$this->assertQuery(
			"SELECT x'417a' AS `0x417a`",
			'SELECT 0x417a'
		);
	}

	public function testSystemVariables(): void {
		$this->assertQuery(
			"SELECT 'ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES' AS `@@sql_mode`",
			'SELECT @@sql_mode'
		);

		$this->assertQuery(
			"SELECT 'ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES' AS `@@SESSION.sql_mode`",
			'SELECT @@SESSION.sql_mode'
		);

		$this->assertQuery(
			"SELECT 'ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES' AS `@@GLOBAL.sql_mode`",
			'SELECT @@GLOBAL.sql_mode'
		);
	}

	public function testConcatFunction(): void {
		$this->assertQuery(
			"SELECT ('a' || 'b' || 'c') AS `CONCAT(\"a\", \"b\", \"c\")`",
			'SELECT CONCAT("a", "b", "c")'
		);
	}

	public function testIndexHints(): void {
		// USE INDEX
		$this->assertQuery(
			'SELECT * FROM `t`',
			'SELECT * FROM t USE INDEX (i)'
		);

		// USE KEY
		$this->assertQuery(
			'SELECT * FROM `t`',
			'SELECT * FROM t USE KEY (k)'
		);

		// FORCE INDEX
		$this->assertQuery(
			'SELECT * FROM `t`',
			'SELECT * FROM t FORCE INDEX (i)'
		);

		// FORCE KEY
		$this->assertQuery(
			'SELECT * FROM `t`',
			'SELECT * FROM t FORCE KEY (k)'
		);

		// IGNORE INDEX
		$this->assertQuery(
			'SELECT * FROM `t`',
			'SELECT * FROM t IGNORE INDEX (i)'
		);

		// IGNORE KEY
		$this->assertQuery(
			'SELECT * FROM `t`',
			'SELECT * FROM t IGNORE KEY (k)'
		);

		// FOR JOIN
		$this->assertQuery(
			'SELECT * FROM `t` JOIN `j` ON `t`.`id` = `j`.`t_id`',
			'SELECT * FROM t USE INDEX FOR JOIN (i) JOIN j ON t.id = j.t_id'
		);

		// FOR ORDER BY
		$this->assertQuery(
			'SELECT * FROM `t` ORDER BY `id` DESC',
			'SELECT * FROM t USE INDEX FOR ORDER BY (i) ORDER BY id DESC'
		);

		// FOR GROUP BY
		$this->assertQuery(
			'SELECT * FROM `t` GROUP BY `id` HAVING `id` = 1',
			'SELECT * FROM t USE INDEX FOR GROUP BY (i) GROUP BY id HAVING id = 1'
		);

		// A complex query with multiple hints and conditions.
		$this->assertQuery(
			'SELECT * FROM `t` JOIN `j` ON `t`.`id` = `j`.`t_id` WHERE `id` = 1 GROUP BY `id` HAVING `id` = 1 ORDER BY `id` DESC',
			'SELECT * FROM `t` USE INDEX (i) USE INDEX FOR JOIN (j) USE KEY FOR ORDER BY (o) IGNORE INDEX FOR GROUP BY (g) JOIN j ON t.id = j.t_id WHERE id = 1 GROUP BY id HAVING id = 1 ORDER BY id DESC'
		);
	}

	public function testSelectOrderByAmbiguousColumnResolution(): void {
		$this->driver->query( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->driver->query( 'CREATE TABLE t2 (id INT, name TEXT)' );

		// Ambiguous column in ORDER BY clause is disambiguated by the SELECT item list.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `t1`.`name`',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name'
		);

		// The ORDER BY direction is preserved when a column is disambiguated.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `t1`.`name` DESC',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name DESC'
		);

		// Multiple ambiguous columns in ORDER BY clause are also disambiguated.
		$this->assertQuery(
			'SELECT `t1`.`id` , `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `t1`.`id` DESC, `t1`.`name` ASC',
			'SELECT t1.id, t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY id DESC, name ASC'
		);

		// The disambiguation works with subqueries.
		$this->assertQuery(
			'SELECT `name` FROM ( SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `t1`.`name` ) ORDER BY `name`',
			'SELECT name FROM (SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name) ORDER BY name'
		);

		// The disambiguation works in both root and subquery contexts at the same time.
		$this->assertQuery(
			'SELECT `ta`.`name` FROM ( SELECT `t2`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `t2`.`name` ) `ta` ORDER BY `ta`.`name`',
			'SELECT ta.name FROM (SELECT t2.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name) ta ORDER BY name'
		);

		// When the SELECT item is nested in a simple parentheses expression, the disambiguation still works.
		$this->assertQuery(
			'SELECT ( ( ( `t1`.`name` ) ) ) AS `(((t1.name)))` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `t1`.`name`',
			'SELECT (((t1.name))) FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name'
		);

		// When the ORDER BY item is nested in a simple parentheses expression, the disambiguation still works.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `t1`.`name`',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY (((name)))'
		);

		// When the SELECT item is nested in a complex expression, the column is not disambiguated (like in MySQL).
		$this->assertQuery(
			"SELECT (`t1`.`name` || 'test') AS `CONCAT(t1.name, 'test')` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `name`",
			"SELECT CONCAT(t1.name, 'test') FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name"
		);

		// When the ORDER BY item is nested in a complex expression, the column is not disambiguated (like in MySQL).
		$this->assertQuery(
			"SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY ( `name` || 'test' )",
			"SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY (name || 'test')"
		);

		// When the SELECT list item uses an alias, the column is not disambiguated (like in MySQL).
		$this->assertQuery(
			'SELECT `t1`.`name` AS `t1_name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `name` DESC',
			'SELECT t1.name AS t1_name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name DESC'
		);

		// When the SELECT item list is ambiguous, the ORDER BY column is not disambiguated (like in MySQL).
		$this->assertQuery(
			'SELECT `t1`.`name` , `t2`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `name` DESC',
			'SELECT t1.name, t2.name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name DESC'
		);

		// When the SELECT item list is ambiguous with an alias, the ORDER BY column is not disambiguated (like in MySQL).
		$this->assertQuery(
			"SELECT `t1`.`name` , 'test' AS `name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `name` DESC",
			"SELECT t1.name, 'test' AS `name` FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY name DESC"
		);

		// When the ORDER BY item uses an alias, there is no ambiguity.
		$this->assertQuery(
			'SELECT `t1`.`name` AS `t1_name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` ORDER BY `t1_name` DESC',
			'SELECT t1.name AS t1_name FROM t1 JOIN t2 ON t2.id = t1.id ORDER BY `t1_name` DESC'
		);

		// With a parenthesized query body, the column should be disambiguated.
		$this->assertQuery(
			'( ( ( SELECT `t1`.`name` FROM `t1` ) ) ) ORDER BY `t1`.`name`',
			'(((SELECT t1.name FROM t1))) ORDER BY name'
		);

		// The root query should not disambiguate from a nested SELECT item.
		$this->assertQuery(
			'SELECT ( SELECT `t1`.`name` FROM `t1` ) AS `t1_name` ORDER BY `name`',
			'SELECT (SELECT t1.name FROM t1) AS t1_name ORDER BY name'
		);

		// With UNION, the column should not be disambiguated.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` UNION ALL SELECT `t2`.`name` FROM `t2` ORDER BY `name`',
			'SELECT t1.name FROM t1 UNION ALL SELECT t2.name FROM t2 ORDER BY name'
		);

		// With EXCEPT, the column should not be disambiguated.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` EXCEPT SELECT `t2`.`name` FROM `t2` ORDER BY `name`',
			'SELECT t1.name FROM t1 EXCEPT SELECT t2.name FROM t2 ORDER BY name'
		);

		// With INTERSECT, the column should not be disambiguated.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` INTERSECT SELECT `t2`.`name` FROM `t2` ORDER BY `name`',
			'SELECT t1.name FROM t1 INTERSECT SELECT t2.name FROM t2 ORDER BY name'
		);

		// Test a complex query with CTEs.
		$this->assertQuery(
			'WITH'
				. " `cte1` ( `name` ) AS ( SELECT 'a' AS `a` UNION ALL SELECT 'b' AS `b` ) ,"
				. ' `cte2` ( `name` ) AS ( SELECT `t2`.`name` FROM `t2` JOIN `t1` ON `t1`.`id` = `t2`.`id` ORDER BY `t2`.`name` )'
				. ' SELECT `t1`.`name` , ( SELECT `name` FROM `cte1` WHERE `id` = 1 ) AS `cte1_name` , ( SELECT `name` FROM `cte2` WHERE `id` = 2 ) AS `cte2_name`'
				. ' FROM `t1`'
				. ' ORDER BY `t1`.`name`',
			"
				WITH
					cte1(name) AS (SELECT 'a' UNION ALL SELECT 'b'),
					cte2(name) AS (SELECT t2.name FROM t2 JOIN t1 ON t1.id = t2.id ORDER BY name)
				SELECT
					t1.name,
					(SELECT name FROM cte1 WHERE id = 1) AS cte1_name,
					(SELECT name FROM cte2 WHERE id = 2) AS cte2_name
				FROM t1
				ORDER BY name
			"
		);
	}

	public function testSelectGroupByAmbiguousColumnResolution(): void {
		$this->driver->query( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->driver->query( 'CREATE TABLE t2 (id INT, name TEXT)' );

		// Ambiguous column in GROUP BY clause is disambiguated by the SELECT item list.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `t1`.`name`',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name'
		);

		// Multiple ambiguous columns in GROUP BY clause are also disambiguated.
		$this->assertQuery(
			'SELECT `t1`.`id` , `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `t1`.`id`, `t1`.`name`',
			'SELECT t1.id, t1.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY id, name'
		);

		// The disambiguation works with subqueries.
		$this->assertQuery(
			'SELECT `name` FROM ( SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `t1`.`name` ) GROUP BY `name`',
			'SELECT name FROM (SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name) GROUP BY name'
		);

		// The disambiguation works in both root and subquery contexts at the same time.
		$this->assertQuery(
			'SELECT `ta`.`name` FROM ( SELECT `t2`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `t2`.`name` ) `ta` GROUP BY `ta`.`name`',
			'SELECT ta.name FROM (SELECT t2.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name) ta GROUP BY name'
		);

		// When the SELECT item is nested in a simple parentheses expression, the disambiguation still works.
		$this->assertQuery(
			'SELECT ( ( ( `t1`.`name` ) ) ) AS `(((t1.name)))` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `t1`.`name`',
			'SELECT (((t1.name))) FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name'
		);

		// When the GROUP BY item is nested in a simple parentheses expression, the disambiguation still works.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `t1`.`name`',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY (((name)))'
		);

		// When the SELECT item is nested in a complex expression, the column is not disambiguated (like in MySQL).
		$this->assertQuery(
			"SELECT (`t1`.`name` || 'test') AS `CONCAT(t1.name, 'test')` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `name`",
			"SELECT CONCAT(t1.name, 'test') FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name"
		);

		// When the GROUP BY item is nested in a complex expression, the column is not disambiguated (like in MySQL).
		$this->assertQuery(
			"SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY ( `name` || 'test' )",
			"SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY (name || 'test')"
		);

		// When the SELECT list item uses an alias, the column is not disambiguated (like in MySQL).
		$this->assertQuery(
			'SELECT `t1`.`name` AS `t1_name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `name`',
			'SELECT t1.name AS t1_name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name'
		);

		// When the SELECT item list is ambiguous, the GROUP BY column is not disambiguated (like in MySQL).
		$this->assertQuery(
			'SELECT `t1`.`name` , `t2`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `name`',
			'SELECT t1.name, t2.name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY name'
		);

		/*
		 * The following edge case behaves differently than in MySQL.
		 * This seems to be due to a quirk in SQLite, where the behavior of the
		 * GROUP BY clause is different from the ORDER BY clause:
		 *   - ORDER BY: SQLite will pick the first column or alias for sorting.
		 *   - GROUP BY: SQLite will fail with an "ambiguous column name" error.
		 *
		 * @TODO: We can consider fixing this more correctly.
		 */
		$this->assertQuery(
			"SELECT `t1`.`name` , 'test' AS `name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `t1`.`name`, `name`",
			"SELECT t1.name, 'test' AS `name` FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY t1.name, name"
		);

		// When the GROUP BY item uses an alias, there is no ambiguity.
		$this->assertQuery(
			'SELECT `t1`.`name` AS `t1_name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY `t1_name`',
			'SELECT t1.name AS t1_name FROM t1 JOIN t2 ON t2.id = t1.id GROUP BY `t1_name`'
		);

		// With UNION, the column should be disambiguated in its subquery (differs from ORDER BY).
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` UNION ALL SELECT `t2`.`name` FROM `t2` GROUP BY `t2`.`name`',
			'SELECT t1.name FROM t1 UNION ALL SELECT t2.name FROM t2 GROUP BY name'
		);

		// With EXCEPT, the column should be disambiguated in its subquery (differs from ORDER BY).
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` EXCEPT SELECT `t2`.`name` FROM `t2` GROUP BY `t2`.`name`',
			'SELECT t1.name FROM t1 EXCEPT SELECT t2.name FROM t2 GROUP BY name'
		);

		// With INTERSECT, the column should be disambiguated in its subquery (differs from ORDER BY).
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` INTERSECT SELECT `t2`.`name` FROM `t2` GROUP BY `t2`.`name`',
			'SELECT t1.name FROM t1 INTERSECT SELECT t2.name FROM t2 GROUP BY name'
		);
	}

	public function testSelectHavingAmbiguousColumnResolution(): void {
		$this->driver->query( 'CREATE TABLE t1 (id INT, name TEXT)' );
		$this->driver->query( 'CREATE TABLE t2 (id INT, name TEXT)' );

		// Ambiguous column in HAVING clause is disambiguated by the SELECT item list.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `t1`.`name`',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING name'
		);

		// Multiple ambiguous columns in HAVING clause are also disambiguated (AND).
		$this->assertQuery(
			'SELECT `t1`.`id` , `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `t1`.`id` AND `t1`.`name`',
			'SELECT t1.id, t1.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING id AND name'
		);

		// Multiple ambiguous columns in HAVING clause are also disambiguated (OR).
		$this->assertQuery(
			'SELECT `t1`.`id` , `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `t1`.`id` OR `t1`.`name`',
			'SELECT t1.id, t1.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING id OR name'
		);

		// The disambiguation works with subqueries.
		$this->assertQuery(
			'SELECT `name` FROM ( SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `t1`.`name` ) GROUP BY 1 HAVING `name`',
			'SELECT name FROM (SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING name) HAVING name'
		);

		// The disambiguation works in both root and subquery contexts at the same time.
		$this->assertQuery(
			'SELECT `ta`.`name` FROM ( SELECT `t2`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `t2`.`name` ) `ta` GROUP BY 1 HAVING `ta`.`name`',
			'SELECT ta.name FROM (SELECT t2.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING name) ta HAVING name'
		);

		// When the HAVING item is nested in a simple parentheses expression, the disambiguation still works.
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `t1`.`name`',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING (((name)))'
		);

		// When the SELECT item is nested in a complex expression, the column is not disambiguated (like in MySQL).
		$this->assertQuery(
			"SELECT (`t1`.`name` || 'test') AS `CONCAT(t1.name, 'test')` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `name`",
			"SELECT CONCAT(t1.name, 'test') FROM t1 JOIN t2 ON t2.id = t1.id HAVING name"
		);

		// When the HAVING item is used in an aggregate function, the column is not disambiguated (like in MySQL).
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING COUNT ( `name` ) > 1',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING COUNT(name) > 1'
		);

		/*
		 * The following edge case behaves differently than in MySQL.
		 * This seems to be due to a quirk in SQLite, where the behavior of the
		 * HAVING clause is different from the ORDER BY clause:
		 *   - ORDER BY: SQLite will pick the first column or alias for sorting.
		 *   - HAVING:   SQLite will fail with an "ambiguous column name" error.
		 *
		 * @TODO: We can consider fixing this more correctly.
		 */
		$this->assertQuery(
			"SELECT `t1`.`name` , 'test' AS `name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `name`",
			"SELECT t1.name, 'test' AS `name` FROM t1 JOIN t2 ON t2.id = t1.id HAVING name"
		);

		// When the HAVING item uses an alias, there is no ambiguity.
		$this->assertQuery(
			'SELECT `t1`.`name` AS `t1_name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `t1_name`',
			'SELECT t1.name AS t1_name FROM t1 JOIN t2 ON t2.id = t1.id HAVING `t1_name`'
		);

		/*
		 * The following edge case should actually be disambiguated, as it is in MySQL.
		 * THe HAVING clause behaves strangely in MySQL:
		 *   - HAVING COUNT(name), HAVING SUM(name), etc. are not disambiguated.
		 *   - HAVING name = 1, HAVING (name = (name + 1)), etc. are disambiguated.
		 *   - With HAVING, MySQL seems to only recognize the columns listed in the SELECT clause.
		 *
		 * @TODO: We can consider fixing this more correctly.
		 */
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` JOIN `t2` ON `t2`.`id` = `t1`.`id` GROUP BY 1 HAVING `name` = 1',
			'SELECT t1.name FROM t1 JOIN t2 ON t2.id = t1.id HAVING name = 1'
		);

		// With UNION, the column should be disambiguated in its subquery (differs from ORDER BY).
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` UNION ALL SELECT `t2`.`name` FROM `t2` GROUP BY 1 HAVING `t2`.`name`',
			'SELECT t1.name FROM t1 UNION ALL SELECT t2.name FROM t2 HAVING name'
		);

		// With EXCEPT, the column should be disambiguated in its subquery (differs from ORDER BY).
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` EXCEPT SELECT `t2`.`name` FROM `t2` GROUP BY 1 HAVING `t2`.`name`',
			'SELECT t1.name FROM t1 EXCEPT SELECT t2.name FROM t2 HAVING name'
		);

		// With INTERSECT, the column should be disambiguated in its subquery (differs from ORDER BY).
		$this->assertQuery(
			'SELECT `t1`.`name` FROM `t1` INTERSECT SELECT `t2`.`name` FROM `t2` GROUP BY 1 HAVING `t2`.`name`',
			'SELECT t1.name FROM t1 INTERSECT SELECT t2.name FROM t2 HAVING name'
		);
	}

	private function assertQuery( $expected, string $query ): void {
		$error = null;
		try {
			$this->driver->query( $query );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		}

		// Check for SQLite syntax errors.
		// This ensures that invalid SQLite syntax will always fail, even if it
		// was the expected result. It prevents us from using wrong assertions.
		if ( $error && preg_match( '/(SQLSTATE\[HY000].+syntax error\.)/i', $error, $matches ) ) {
			$this->fail(
				sprintf( "SQLite syntax error: %s\nMySQL query: %s", $matches[1], $query )
			);
		}

		$executed_queries = array_column( $this->driver->get_last_sqlite_queries(), 'sql' );

		// Remove BEGIN and COMMIT/ROLLBACK queries.
		if ( count( $executed_queries ) > 2 ) {
			$executed_queries = array_values( array_slice( $executed_queries, 1, -1, true ) );
		}

		// Remove temporary table existence checks.
		$executed_queries = array_values(
			array_filter(
				$executed_queries,
				function ( $query ) {
					return "SELECT 1 FROM sqlite_temp_master WHERE type = 'table' AND name = ?" !== $query;
				}
			)
		);

		// Remove "information_schema" queries.
		$executed_queries = array_values(
			array_filter(
				$executed_queries,
				function ( $query ) {
					return ! str_contains( $query, '_wp_sqlite_mysql_information_schema_' );
				}
			)
		);

		// Remove "select changes()" executed after some queries.
		if (
			count( $executed_queries ) > 1
			&& 'SELECT CHANGES()' === $executed_queries[ count( $executed_queries ) - 1 ] ) {
			array_pop( $executed_queries );
		}

		if ( ! is_array( $expected ) ) {
			$expected = array( $expected );
		}

		// Normalize whitespace.
		foreach ( $executed_queries as $key => $executed_query ) {
			$executed_queries[ $key ] = trim( preg_replace( '/\s+/', ' ', $executed_query ) );
		}

		// Normalize temporary table names.
		foreach ( $executed_queries as $key => $executed_query ) {
			$executed_queries[ $key ] = preg_replace( '/`_wp_sqlite_tmp_[^`]+`/', '`<tmp-table>`', $executed_query );
		}

		$this->assertSame( $expected, $executed_queries );
	}

	private function assertExecutedInformationSchemaQueries( array $expected ): void {
		// Collect and normalize "information_schema" queries.
		$queries = array();
		foreach ( $this->driver->get_last_sqlite_queries() as $query ) {
			if ( ! str_contains( $query['sql'], '_wp_sqlite_mysql_information_schema_' ) ) {
				continue;
			}

			// Normalize whitespace.
			$sql = trim( preg_replace( '/\s+/', ' ', $query['sql'] ) );

			// Inline parameters.
			$sql       = str_replace( '?', '%s', $sql );
			$queries[] = sprintf(
				$sql,
				...array_map(
					function ( $param ) {
						if ( null === $param ) {
							return 'null';
						}
						if ( is_string( $param ) ) {
							return $this->driver->get_connection()->quote( $param );
						}
						return $param;
					},
					$query['params']
				)
			);
		}
		$this->assertSame( $expected, $queries );
	}
}
