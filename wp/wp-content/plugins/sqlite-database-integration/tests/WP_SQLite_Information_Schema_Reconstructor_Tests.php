<?php

use PHPUnit\Framework\TestCase;

class WP_SQLite_Information_Schema_Reconstructor_Tests extends TestCase {
	const CREATE_DATA_TYPES_CACHE_TABLE_SQL = '
		CREATE TABLE _mysql_data_types_cache (
			`table` TEXT NOT NULL,
			`column_or_index` TEXT NOT NULL,
			`mysql_type` TEXT NOT NULL,
			PRIMARY KEY(`table`, `column_or_index`)
	)';

	/** @var WP_SQLite_Driver */
	private $engine;

	/** @var WP_SQLite_Information_Schema_Reconstructor */
	private $reconstructor;

	/** @var PDO */
	private $sqlite;

	public static function setUpBeforeClass(): void {
		// Mock symbols that are used for WordPress table reconstruction.
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ );
		}
		if ( ! function_exists( 'is_multisite' ) ) {
			function is_multisite() {
				return false;
			}
		}
		if ( ! function_exists( 'wp_get_db_schema' ) ) {
			function wp_get_db_schema() {
				// Output from "wp_get_db_schema" as of WordPress 6.8.0.
				// See: https://github.com/WordPress/wordpress-develop/blob/6.8.0/src/wp-admin/includes/schema.php#L36
				return "CREATE TABLE wp_users ( ID bigint(20) unsigned NOT NULL auto_increment, user_login varchar(60) NOT NULL default '', user_pass varchar(255) NOT NULL default '', user_nicename varchar(50) NOT NULL default '', user_email varchar(100) NOT NULL default '', user_url varchar(100) NOT NULL default '', user_registered datetime NOT NULL default '0000-00-00 00:00:00', user_activation_key varchar(255) NOT NULL default '', user_status int(11) NOT NULL default '0', display_name varchar(250) NOT NULL default '', PRIMARY KEY (ID), KEY user_login_key (user_login), KEY user_nicename (user_nicename), KEY user_email (user_email) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_usermeta ( umeta_id bigint(20) unsigned NOT NULL auto_increment, user_id bigint(20) unsigned NOT NULL default '0', meta_key varchar(255) default NULL, meta_value longtext, PRIMARY KEY (umeta_id), KEY user_id (user_id), KEY meta_key (meta_key(191)) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_termmeta ( meta_id bigint(20) unsigned NOT NULL auto_increment, term_id bigint(20) unsigned NOT NULL default '0', meta_key varchar(255) default NULL, meta_value longtext, PRIMARY KEY (meta_id), KEY term_id (term_id), KEY meta_key (meta_key(191)) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_terms ( term_id bigint(20) unsigned NOT NULL auto_increment, name varchar(200) NOT NULL default '', slug varchar(200) NOT NULL default '', term_group bigint(10) NOT NULL default 0, PRIMARY KEY (term_id), KEY slug (slug(191)), KEY name (name(191)) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_term_taxonomy ( term_taxonomy_id bigint(20) unsigned NOT NULL auto_increment, term_id bigint(20) unsigned NOT NULL default 0, taxonomy varchar(32) NOT NULL default '', description longtext NOT NULL, parent bigint(20) unsigned NOT NULL default 0, count bigint(20) NOT NULL default 0, PRIMARY KEY (term_taxonomy_id), UNIQUE KEY term_id_taxonomy (term_id,taxonomy), KEY taxonomy (taxonomy) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_term_relationships ( object_id bigint(20) unsigned NOT NULL default 0, term_taxonomy_id bigint(20) unsigned NOT NULL default 0, term_order int(11) NOT NULL default 0, PRIMARY KEY (object_id,term_taxonomy_id), KEY term_taxonomy_id (term_taxonomy_id) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_commentmeta ( meta_id bigint(20) unsigned NOT NULL auto_increment, comment_id bigint(20) unsigned NOT NULL default '0', meta_key varchar(255) default NULL, meta_value longtext, PRIMARY KEY (meta_id), KEY comment_id (comment_id), KEY meta_key (meta_key(191)) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_comments ( comment_ID bigint(20) unsigned NOT NULL auto_increment, comment_post_ID bigint(20) unsigned NOT NULL default '0', comment_author tinytext NOT NULL, comment_author_email varchar(100) NOT NULL default '', comment_author_url varchar(200) NOT NULL default '', comment_author_IP varchar(100) NOT NULL default '', comment_date datetime NOT NULL default '0000-00-00 00:00:00', comment_date_gmt datetime NOT NULL default '0000-00-00 00:00:00', comment_content text NOT NULL, comment_karma int(11) NOT NULL default '0', comment_approved varchar(20) NOT NULL default '1', comment_agent varchar(255) NOT NULL default '', comment_type varchar(20) NOT NULL default 'comment', comment_parent bigint(20) unsigned NOT NULL default '0', user_id bigint(20) unsigned NOT NULL default '0', PRIMARY KEY (comment_ID), KEY comment_post_ID (comment_post_ID), KEY comment_approved_date_gmt (comment_approved,comment_date_gmt), KEY comment_date_gmt (comment_date_gmt), KEY comment_parent (comment_parent), KEY comment_author_email (comment_author_email(10)) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_links ( link_id bigint(20) unsigned NOT NULL auto_increment, link_url varchar(255) NOT NULL default '', link_name varchar(255) NOT NULL default '', link_image varchar(255) NOT NULL default '', link_target varchar(25) NOT NULL default '', link_description varchar(255) NOT NULL default '', link_visible varchar(20) NOT NULL default 'Y', link_owner bigint(20) unsigned NOT NULL default '1', link_rating int(11) NOT NULL default '0', link_updated datetime NOT NULL default '0000-00-00 00:00:00', link_rel varchar(255) NOT NULL default '', link_notes mediumtext NOT NULL, link_rss varchar(255) NOT NULL default '', PRIMARY KEY (link_id), KEY link_visible (link_visible) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_options ( option_id bigint(20) unsigned NOT NULL auto_increment, option_name varchar(191) NOT NULL default '', option_value longtext NOT NULL, autoload varchar(20) NOT NULL default 'yes', PRIMARY KEY (option_id), UNIQUE KEY option_name (option_name), KEY autoload (autoload) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_postmeta ( meta_id bigint(20) unsigned NOT NULL auto_increment, post_id bigint(20) unsigned NOT NULL default '0', meta_key varchar(255) default NULL, meta_value longtext, PRIMARY KEY (meta_id), KEY post_id (post_id), KEY meta_key (meta_key(191)) ) DEFAULT CHARACTER SET utf8mb4; CREATE TABLE wp_posts ( ID bigint(20) unsigned NOT NULL auto_increment, post_author bigint(20) unsigned NOT NULL default '0', post_date datetime NOT NULL default '0000-00-00 00:00:00', post_date_gmt datetime NOT NULL default '0000-00-00 00:00:00', post_content longtext NOT NULL, post_title text NOT NULL, post_excerpt text NOT NULL, post_status varchar(20) NOT NULL default 'publish', comment_status varchar(20) NOT NULL default 'open', ping_status varchar(20) NOT NULL default 'open', post_password varchar(255) NOT NULL default '', post_name varchar(200) NOT NULL default '', to_ping text NOT NULL, pinged text NOT NULL, post_modified datetime NOT NULL default '0000-00-00 00:00:00', post_modified_gmt datetime NOT NULL default '0000-00-00 00:00:00', post_content_filtered longtext NOT NULL, post_parent bigint(20) unsigned NOT NULL default '0', guid varchar(255) NOT NULL default '', menu_order int(11) NOT NULL default '0', post_type varchar(20) NOT NULL default 'post', post_mime_type varchar(100) NOT NULL default '', comment_count bigint(20) NOT NULL default '0', PRIMARY KEY (ID), KEY post_name (post_name(191)), KEY type_status_date (post_type,post_status,post_date,ID), KEY post_parent (post_parent), KEY post_author (post_author) ) DEFAULT CHARACTER SET utf8mb4;";
			}
		}
	}

	// Before each test, we create a new database
	public function setUp(): void {
		$this->sqlite = new PDO( 'sqlite::memory:' );
		$this->engine = new WP_SQLite_Driver(
			new WP_SQLite_Connection( array( 'pdo' => $this->sqlite ) ),
			'wp'
		);

		$builder = new WP_SQLite_Information_Schema_Builder(
			'wp',
			WP_SQLite_Driver::RESERVED_PREFIX,
			$this->engine->get_connection()
		);

		$this->reconstructor = new WP_SQLite_Information_Schema_Reconstructor(
			$this->engine,
			$builder
		);
	}

	public function testReconstructTable(): void {
		$this->engine->get_connection()->query(
			'
			CREATE TABLE t (
			  id INTEGER PRIMARY KEY AUTOINCREMENT,
			  email TEXT NOT NULL UNIQUE,
			  name TEXT NOT NULL,
			  role TEXT,
			  score REAL,
			  priority INTEGER DEFAULT 0,
			  data BLOB,
			  UNIQUE (name)
			)
		'
		);
		$this->engine->get_connection()->query( 'CREATE INDEX idx_score ON t (score)' );
		$this->engine->get_connection()->query( 'CREATE INDEX idx_role_score ON t (role, priority)' );
		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables WHERE table_name = "t"' );
		$this->assertEquals( 0, count( $result ) );

		$this->reconstructor->ensure_correct_information_schema();
		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables WHERE table_name = "t"' );
		$this->assertEquals( 1, count( $result ) );

		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int NOT NULL AUTO_INCREMENT,',
					'  `email` text NOT NULL,',
					'  `name` text NOT NULL,',
					'  `role` text DEFAULT NULL,',
					'  `score` float DEFAULT NULL,',
					"  `priority` int DEFAULT '0',",
					'  `data` blob DEFAULT NULL,',
					'  PRIMARY KEY (`id`),',
					'  UNIQUE KEY `name` (`name`(100)),',
					'  UNIQUE KEY `email` (`email`(100)),',
					'  KEY `idx_role_score` (`role`(100), `priority`),',
					'  KEY `idx_score` (`score`)',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);
	}

	public function testReconstructWpTable(): void {
		// Create a WP table with any columns.
		$this->engine->get_connection()->query( 'CREATE TABLE wp_posts ( id INTEGER )' );

		// Reconstruct the information schema.
		$this->reconstructor->ensure_correct_information_schema();
		$result = $this->assertQuery( 'SELECT * FROM information_schema.tables WHERE table_name = "wp_posts"' );
		$this->assertEquals( 1, count( $result ) );

		// The reconstructed schema should correspond to the original WP table definition.
		$result = $this->assertQuery( 'SHOW CREATE TABLE wp_posts' );
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `wp_posts` (',
					'  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,',
					"  `post_author` bigint(20) unsigned NOT NULL DEFAULT '0',",
					"  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',",
					"  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',",
					'  `post_content` longtext NOT NULL,',
					'  `post_title` text NOT NULL,',
					'  `post_excerpt` text NOT NULL,',
					"  `post_status` varchar(20) NOT NULL DEFAULT 'publish',",
					"  `comment_status` varchar(20) NOT NULL DEFAULT 'open',",
					"  `ping_status` varchar(20) NOT NULL DEFAULT 'open',",
					"  `post_password` varchar(255) NOT NULL DEFAULT '',",
					"  `post_name` varchar(200) NOT NULL DEFAULT '',",
					'  `to_ping` text NOT NULL,',
					'  `pinged` text NOT NULL,',
					"  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',",
					"  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',",
					'  `post_content_filtered` longtext NOT NULL,',
					"  `post_parent` bigint(20) unsigned NOT NULL DEFAULT '0',",
					"  `guid` varchar(255) NOT NULL DEFAULT '',",
					"  `menu_order` int(11) NOT NULL DEFAULT '0',",
					"  `post_type` varchar(20) NOT NULL DEFAULT 'post',",
					"  `post_mime_type` varchar(100) NOT NULL DEFAULT '',",
					"  `comment_count` bigint(20) NOT NULL DEFAULT '0',",
					'  PRIMARY KEY (`ID`),',
					'  KEY `post_name` (`post_name`(191)),',
					'  KEY `type_status_date` (`post_type`, `post_status`, `post_date`, `ID`),',
					'  KEY `post_parent` (`post_parent`),',
					'  KEY `post_author` (`post_author`)',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);
	}

	public function testReconstructTableFromMysqlDataTypesCache(): void {
		$connection = $this->engine->get_connection();

		$connection->query( self::CREATE_DATA_TYPES_CACHE_TABLE_SQL );
		$connection->query( "INSERT INTO _mysql_data_types_cache (`table`, column_or_index, mysql_type) VALUES ('t', 'id', 'int unsigned')" );
		$connection->query( "INSERT INTO _mysql_data_types_cache (`table`, column_or_index, mysql_type) VALUES ('t', 'name', 'varchar(255)')" );
		$connection->query( "INSERT INTO _mysql_data_types_cache (`table`, column_or_index, mysql_type) VALUES ('t', 'description', 'text')" );
		$connection->query( "INSERT INTO _mysql_data_types_cache (`table`, column_or_index, mysql_type) VALUES ('t', 'shape', 'geomcollection')" );
		$connection->query( "INSERT INTO _mysql_data_types_cache (`table`, column_or_index, mysql_type) VALUES ('t', 't__idx_name', 'KEY')" );
		$connection->query( "INSERT INTO _mysql_data_types_cache (`table`, column_or_index, mysql_type) VALUES ('t', 't__idx_description', 'FULLTEXT')" );
		$connection->query( "INSERT INTO _mysql_data_types_cache (`table`, column_or_index, mysql_type) VALUES ('t', 't__idx_shape', 'SPATIAL')" );

		$connection->query(
			'
			CREATE TABLE t (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name TEXT,
				description TEXT,
				shape TEXT NOT NULL
			)
		'
		);
		$connection->query( 'CREATE INDEX t__idx_name ON t (name)' );
		$connection->query( 'CREATE INDEX t__idx_description ON t (description)' );
		$connection->query( 'CREATE INDEX t__idx_shape ON t (shape)' );

		$this->reconstructor->ensure_correct_information_schema();
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					'  `id` int unsigned NOT NULL AUTO_INCREMENT,',
					'  `name` varchar(255) DEFAULT NULL,',
					'  `description` text DEFAULT NULL,',
					'  `shape` geomcollection NOT NULL,',
					'  PRIMARY KEY (`id`),',
					'  SPATIAL KEY `idx_shape` (`shape`(32)),',
					'  KEY `idx_name` (`name`(100)),',
					'  FULLTEXT KEY `idx_description` (`description`(100))',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);
	}

	public function testDefaultValues(): void {
		$this->engine->get_connection()->query(
			"
			CREATE TABLE t (
				col1 text DEFAULT abc,
				col2 text DEFAULT 'abc',
				col3 text DEFAULT \"abc\",
				col4 text DEFAULT NULL,
				col5 int DEFAULT TRUE,
				col6 int DEFAULT FALSE,
				col7 int DEFAULT 123,
				col8 real DEFAULT 1.23,
				col9 real DEFAULT -1.23,
				col10 real DEFAULT 1e3,
				col11 real DEFAULT 1.2e-3,
				col12 int DEFAULT 0x1a2f,
				col13 int DEFAULT 0X1A2f,
				col14 blob DEFAULT x'4142432E',
				col15 blob DEFAULT x'4142432E',
				col16 text DEFAULT CURRENT_TIMESTAMP,
				col17 text DEFAULT CURRENT_DATE,
				col18 text DEFAULT CURRENT_TIME
			)
		"
		);

		$this->reconstructor->ensure_correct_information_schema();
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					"  `col1` varchar(65535) DEFAULT 'abc',",
					"  `col2` varchar(65535) DEFAULT 'abc',",
					"  `col3` varchar(65535) DEFAULT 'abc',",
					'  `col4` text DEFAULT NULL,',
					"  `col5` int DEFAULT '1',",
					"  `col6` int DEFAULT '0',",
					"  `col7` int DEFAULT '123',",
					"  `col8` float DEFAULT '1.23',",
					"  `col9` float DEFAULT '-1.23',",
					"  `col10` float DEFAULT '1e3',",
					"  `col11` float DEFAULT '1.2e-3',",
					"  `col12` int DEFAULT '6703',",
					"  `col13` int DEFAULT '6703',",
					"  `col14` varbinary(65535) DEFAULT 'ABC.',",
					"  `col15` varbinary(65535) DEFAULT 'ABC.',",
					'  `col16` datetime DEFAULT CURRENT_TIMESTAMP,',
					'  `col17` text DEFAULT NULL,',
					'  `col18` text DEFAULT NULL',
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);
	}

	public function testDefaultValueEscaping(): void {
		//$this->assertSame("abc". chr( 8 ) . "xyz", "" );
		$this->engine->get_connection()->query(
			"
			CREATE TABLE t (
				col1 text DEFAULT 'abc''xyz',
				col2 text DEFAULT 'abc\"xyz',
				col3 text DEFAULT 'abc`xyz',
				col4 text DEFAULT 'abc\\xyz',
				col5 text DEFAULT 'abc\nxyz',
				col6 text DEFAULT 'abc\rxyz',
				col7 text DEFAULT 'abc\txyz',
				col8 text DEFAULT 'abc" . chr( 8 ) . "xyz', -- backspace
				col9 text DEFAULT 'abc" . chr( 26 ) . "xyz' -- control-Z
			)
		"
		);

		$this->reconstructor->ensure_correct_information_schema();
		$result = $this->assertQuery( 'SHOW CREATE TABLE t' );
		$this->assertSame(
			implode(
				"\n",
				array(
					'CREATE TABLE `t` (',
					"  `col1` varchar(65535) DEFAULT 'abc''xyz',",
					"  `col2` varchar(65535) DEFAULT 'abc\"xyz',",
					"  `col3` varchar(65535) DEFAULT 'abc`xyz',",
					"  `col4` varchar(65535) DEFAULT 'abc\\\\xyz',",
					"  `col5` varchar(65535) DEFAULT 'abc\\nxyz',",
					"  `col6` varchar(65535) DEFAULT 'abc\\rxyz',",
					"  `col7` varchar(65535) DEFAULT 'abc	xyz',",              // tab is preserved
					"  `col8` varchar(65535) DEFAULT 'abc" . chr( 8 ) . "xyz',", // backspace is preserved
					"  `col9` varchar(65535) DEFAULT 'abc" . chr( 26 ) . "xyz'", // control-Z is preserved
					') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
				)
			),
			$result[0]->{'Create Table'}
		);
	}

	private function assertQuery( $sql ) {
		$retval = $this->engine->query( $sql );
		$this->assertNotFalse( $retval );
		return $retval;
	}
}
