<?php

use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
class FetchObjectTestClass {
	public $col1;
	public $col2;
	public $col3;
	public $arg1;
	public $arg2;

	public function __construct( $arg1 = null, $arg2 = null ) {
		$this->arg1 = $arg1;
		$this->arg2 = $arg2;
	}
}

class WP_PDO_MySQL_On_SQLite_PDO_API_Tests extends TestCase {
	/** @var WP_PDO_MySQL_On_SQLite */
	private $driver;

	public function setUp(): void {
		$this->driver = new WP_PDO_MySQL_On_SQLite( 'mysql-on-sqlite:path=:memory:;dbname=wp;' );

		// Run all tests with stringified fetch mode results, so we can use
		// assertions that are consistent across all tested PHP versions.
		// The "PDO::ATTR_STRINGIFY_FETCHES" mode is tested separately.
		$this->driver->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );
	}

	public function test_connection(): void {
		$driver = new WP_PDO_MySQL_On_SQLite( 'mysql-on-sqlite:path=:memory:;dbname=WordPress;' );
		$this->assertInstanceOf( PDO::class, $driver );
	}

	public function test_dsn_parsing(): void {
		// Standard DSN.
		$driver = new WP_PDO_MySQL_On_SQLite( 'mysql-on-sqlite:path=:memory:;dbname=wp' );
		$this->assertSame( 'wp', $driver->query( 'SELECT DATABASE()' )->fetch()[0] );

		// DSN with trailing semicolon.
		$driver = new WP_PDO_MySQL_On_SQLite( 'mysql-on-sqlite:path=:memory:;dbname=wp;' );
		$this->assertSame( 'wp', $driver->query( 'SELECT DATABASE()' )->fetch()[0] );

		// DSN with whitespace before argument names.
		$driver = new WP_PDO_MySQL_On_SQLite( "mysql-on-sqlite:  path=:memory:; \n\r\t\v\fdbname=wp" );
		$this->assertSame( 'wp', $driver->query( 'SELECT DATABASE()' )->fetch()[0] );

		// DSN with whitespace in the database name.
		$driver = new WP_PDO_MySQL_On_SQLite( 'mysql-on-sqlite:path=:memory:;dbname= w p ' );
		$this->assertSame( ' w p ', $driver->query( 'SELECT DATABASE()' )->fetch()[0] );

		// DSN with semicolon in the database name.
		$driver = new WP_PDO_MySQL_On_SQLite( 'mysql-on-sqlite:path=:memory:;dbname=wp;dbname=w;;p;' );
		$this->assertSame( 'w;p', $driver->query( 'SELECT DATABASE()' )->fetch()[0] );

		// DSN with semicolon in the database name and a terminating semicolon.
		$driver = new WP_PDO_MySQL_On_SQLite( 'mysql-on-sqlite:path=:memory:;dbname=w;;;p' );
		$this->assertSame( 'w;', $driver->query( 'SELECT DATABASE()' )->fetch()[0] );

		// DSN with two semicolons in the database name.
		$driver = new WP_PDO_MySQL_On_SQLite( 'mysql-on-sqlite:path=:memory:;dbname=w;;;;p' );
		$this->assertSame( 'w;;p', $driver->query( 'SELECT DATABASE()' )->fetch()[0] );

		// DSN with a "\0" byte (always terminates the DSN string).
		$driver = new WP_PDO_MySQL_On_SQLite( "mysql-on-sqlite:path=:memory:;dbname=w\0p;" );
		$this->assertSame( 'w', $driver->query( 'SELECT DATABASE()' )->fetch()[0] );
	}

	public function test_query(): void {
		$result = $this->driver->query( "SELECT 1, 'abc'" );
		$this->assertInstanceOf( PDOStatement::class, $result );
		if ( PHP_VERSION_ID < 80000 ) {
			$this->assertSame(
				array(
					1     => '1',
					2     => '1',
					'abc' => 'abc',
					3     => 'abc',
				),
				$result->fetch()
			);
		} else {
			$this->assertSame(
				array(
					1     => '1',
					0     => '1',
					'abc' => 'abc',
				),
				$result->fetch()
			);
		}
	}

	/**
	 * @dataProvider data_pdo_fetch_methods
	 */
	public function test_query_with_fetch_mode( $query, $mode, $expected ): void {
		$stmt   = $this->driver->query( $query, $mode );
		$result = $stmt->fetch();

		if ( is_object( $expected ) ) {
			$this->assertInstanceOf( get_class( $expected ), $result );
			$this->assertSame( (array) $expected, (array) $result );
		} elseif ( PDO::FETCH_NAMED === $mode ) {
			// PDO::FETCH_NAMED returns all array keys as strings, even numeric
			// ones. This is not possible in plain PHP and might be a PDO bug.
			$this->assertSame( array_map( 'strval', array_keys( $expected ) ), array_keys( $result ) );
			$this->assertSame( array_values( $expected ), array_values( $result ) );
		} else {
			$this->assertSame( $expected, $result );
		}

		$this->assertFalse( $stmt->fetch() );
	}

	public function test_query_fetch_mode_not_set(): void {
		$result = $this->driver->query( 'SELECT 1' );
		if ( PHP_VERSION_ID < 80000 ) {
			$this->assertSame(
				array(
					1 => '1',
					2 => '1',
				),
				$result->fetch()
			);
		} else {
			$this->assertSame(
				array(
					1 => '1',
					0 => '1',
				),
				$result->fetch()
			);
		}
		$this->assertFalse( $result->fetch() );
	}

	public function test_query_fetch_mode_invalid_arg_count(): void {
		$this->expectException( ArgumentCountError::class );
		$this->expectExceptionMessage( 'PDO::query() expects exactly 2 arguments for the fetch mode provided, 3 given' );
		$this->driver->query( 'SELECT 1', PDO::FETCH_ASSOC, 0 );
	}

	public function test_query_fetch_default_mode_allow_any_args(): void {
		if ( PHP_VERSION_ID < 80100 ) {
			// On PHP < 8.1, fetch mode value of NULL is not allowed.
			$result = @$this->driver->query( 'SELECT 1', null, 1, 2, 'abc', array(), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->assertFalse( $result );
			$this->assertSame( 'PDO::query(): SQLSTATE[HY000]: General error: mode must be an integer', error_get_last()['message'] );
			return;
		}

		// On PHP >= 8.1, NULL fetch mode is allowed to use the default fetch mode.
		// In such cases, any additional arguments are ignored and not validated.
		$expected_result = array(
			array(
				1 => '1',
				0 => '1',
			),
		);

		$result = $this->driver->query( 'SELECT 1' );
		$this->assertSame( $expected_result, $result->fetchAll() );

		$result = $this->driver->query( 'SELECT 1', null );
		$this->assertSame( $expected_result, $result->fetchAll() );

		$result = $this->driver->query( 'SELECT 1', null, 1 );
		$this->assertSame( $expected_result, $result->fetchAll() );

		$result = $this->driver->query( 'SELECT 1', null, 'abc' );
		$this->assertSame( $expected_result, $result->fetchAll() );

		$result = $this->driver->query( 'SELECT 1', null, 1, 2, 'abc', array(), true );
		$this->assertSame( $expected_result, $result->fetchAll() );
	}

	public function test_query_fetch_class_not_enough_args(): void {
		$this->expectException( ArgumentCountError::class );
		$this->expectExceptionMessage( 'PDO::query() expects at least 3 arguments for the fetch mode provided, 2 given' );
		$this->driver->query( 'SELECT 1', PDO::FETCH_CLASS );
	}

	public function test_query_fetch_class_too_many_args(): void {
		$this->expectException( ArgumentCountError::class );
		$this->expectExceptionMessage( 'PDO::query() expects at most 4 arguments for the fetch mode provided, 5 given' );
		$this->driver->query( 'SELECT 1', PDO::FETCH_CLASS, '\stdClass', array(), array() );
	}

	public function test_query_fetch_class_invalid_class_type(): void {
		$this->expectException( TypeError::class );
		$this->expectExceptionMessage( 'PDO::query(): Argument #3 must be of type string, int given' );
		$this->driver->query( 'SELECT 1', PDO::FETCH_CLASS, 1 );
	}

	public function test_query_fetch_class_invalid_class_name(): void {
		$this->expectException( TypeError::class );
		$this->expectExceptionMessage( 'PDO::query(): Argument #3 must be a valid class' );
		$this->driver->query( 'SELECT 1', PDO::FETCH_CLASS, 'non-existent-class' );
	}

	public function test_query_fetch_class_invalid_constructor_args_type(): void {
		$this->expectException( TypeError::class );
		$this->expectExceptionMessage( 'PDO::query(): Argument #4 must be of type ?array, int given' );
		$this->driver->query( 'SELECT 1', PDO::FETCH_CLASS, 'stdClass', 1 );
	}

	public function test_query_fetch_into_invalid_arg_count(): void {
		$this->expectException( ArgumentCountError::class );
		$this->expectExceptionMessage( 'PDO::query() expects exactly 3 arguments for the fetch mode provided, 2 given' );
		$this->driver->query( 'SELECT 1', PDO::FETCH_INTO );
	}

	public function test_query_fetch_into_invalid_object_type(): void {
		$this->expectException( TypeError::class );
		$this->expectExceptionMessage( 'PDO::query(): Argument #3 must be of type object, int given' );
		$this->driver->query( 'SELECT 1', PDO::FETCH_INTO, 1 );
	}

	public function test_exec(): void {
		$result = $this->driver->exec( 'SELECT 1' );
		$this->assertEquals( 0, $result );

		$result = $this->driver->exec( 'CREATE TABLE t (id INT)' );
		$this->assertEquals( 0, $result );

		$result = $this->driver->exec( 'INSERT INTO t (id) VALUES (1)' );
		$this->assertEquals( 1, $result );

		$result = $this->driver->exec( 'INSERT INTO t (id) VALUES (2), (3)' );
		$this->assertEquals( 2, $result );

		$result = $this->driver->exec( 'UPDATE t SET id = 10 + id WHERE id = 0' );
		$this->assertEquals( 0, $result );

		$result = $this->driver->exec( 'UPDATE t SET id = 10 + id WHERE id = 1' );
		$this->assertEquals( 1, $result );

		$result = $this->driver->exec( 'UPDATE t SET id = 10 + id WHERE id < 10' );
		$this->assertEquals( 2, $result );

		$result = $this->driver->exec( 'DELETE FROM t WHERE id = 11' );
		$this->assertEquals( 1, $result );

		$result = $this->driver->exec( 'DELETE FROM t' );
		$this->assertEquals( 2, $result );

		$result = $this->driver->exec( 'DROP TABLE t' );
		$this->assertEquals( 0, $result );
	}

	public function test_begin_transaction(): void {
		$result = $this->driver->beginTransaction();
		$this->assertTrue( $result );
	}

	public function test_begin_transaction_already_active(): void {
		$this->driver->beginTransaction();

		$this->expectException( PDOException::class );
		$this->expectExceptionMessage( 'There is already an active transaction' );
		$this->expectExceptionCode( 0 );
		$this->driver->beginTransaction();
	}

	public function test_commit(): void {
		$this->driver->beginTransaction();
		$result = $this->driver->commit();
		$this->assertTrue( $result );
	}

	public function test_commit_no_active_transaction(): void {
		$this->expectException( PDOException::class );
		$this->expectExceptionMessage( 'There is no active transaction' );
		$this->expectExceptionCode( 0 );
		$this->driver->commit();
	}

	public function test_rollback(): void {
		$this->driver->beginTransaction();
		$result = $this->driver->rollBack();
		$this->assertTrue( $result );
	}

	public function test_rollback_no_active_transaction(): void {
		$this->expectException( PDOException::class );
		$this->expectExceptionMessage( 'There is no active transaction' );
		$this->expectExceptionCode( 0 );
		$this->driver->rollBack();
	}

	public function test_fetch_default(): void {
		// Default fetch mode is PDO::FETCH_BOTH.
		$result = $this->driver->query( "SELECT 1, 'abc', 2" );
		if ( PHP_VERSION_ID < 80000 ) {
			$this->assertSame(
				array(
					1     => '1',
					2     => '2',
					'abc' => 'abc',
					3     => 'abc',
					4     => '2',
				),
				$result->fetch()
			);
		} else {
			$this->assertSame(
				array(
					1     => '1',
					0     => '1',
					'abc' => 'abc',
					'2'   => '2',
				),
				$result->fetch()
			);
		}
	}

	/**
	 * @dataProvider data_pdo_fetch_methods
	 */
	public function test_fetch( $query, $mode, $expected ): void {
		$stmt   = $this->driver->query( $query );
		$result = $stmt->fetch( $mode );

		if ( is_object( $expected ) ) {
			$this->assertInstanceOf( get_class( $expected ), $result );
			$this->assertEquals( $expected, $result );
		} elseif ( PDO::FETCH_NAMED === $mode ) {
			// PDO::FETCH_NAMED returns all array keys as strings, even numeric
			// ones. This is not possible in plain PHP and might be a PDO bug.
			$this->assertSame( array_map( 'strval', array_keys( $expected ) ), array_keys( $result ) );
			$this->assertSame( array_values( $expected ), array_values( $result ) );
		} else {
			$this->assertSame( $expected, $result );
		}
	}

	public function test_fetch_column(): void {
		$query = "
			SELECT 1, 'abc', true
			UNION ALL
			SELECT 2, 'xyz', false
			UNION ALL
			SELECT 3, null, null
		";

		// Fetch first column (default).
		$stmt = $this->driver->query( $query );
		$this->assertSame( '1', $stmt->fetchColumn() );
		$this->assertSame( '2', $stmt->fetchColumn() );
		$this->assertSame( '3', $stmt->fetchColumn() );
		$this->assertFalse( $stmt->fetchColumn() );

		// Fetch second column.
		$stmt = $this->driver->query( $query );
		$this->assertSame( 'abc', $stmt->fetchColumn( 1 ) );
		$this->assertSame( 'xyz', $stmt->fetchColumn( 1 ) );
		$this->assertNull( $stmt->fetchColumn( 1 ) );
		$this->assertFalse( $stmt->fetchColumn( 1 ) );

		// Fetch third column.
		$stmt = $this->driver->query( $query );
		$this->assertSame( '1', $stmt->fetchColumn( 2 ) );
		$this->assertSame( '0', $stmt->fetchColumn( 2 ) );
		$this->assertNull( $stmt->fetchColumn( 2 ) );
		$this->assertFalse( $stmt->fetchColumn( 2 ) );

		// Fetch different columns across rows.
		$stmt = $this->driver->query( $query );
		$this->assertSame( '1', $stmt->fetchColumn( 0 ) );
		$this->assertSame( 'xyz', $stmt->fetchColumn( 1 ) );
		$this->assertNull( $stmt->fetchColumn( 2 ) );
		$this->assertFalse( $stmt->fetchColumn() );
	}

	public function test_fetch_column_invalid_index(): void {
		$stmt = $this->driver->query( "SELECT 1, 'abc', true" );

		if ( PHP_VERSION_ID < 80000 ) {
			$this->expectException( PDOException::class );
			$this->expectExceptionMessage( 'Invalid column index' );
		} else {
			$this->expectException( ValueError::class );
			$this->expectExceptionMessage( 'Invalid column index' );
		}
		$stmt->fetchColumn( 3 );
	}

	public function test_fetch_column_negative_index(): void {
		$stmt = $this->driver->query( "SELECT 1, 'abc', true" );

		if ( PHP_VERSION_ID < 80000 ) {
			$this->expectException( PDOException::class );
			$this->expectExceptionMessage( 'Invalid column index' );
		} else {
			$this->expectException( ValueError::class );
			$this->expectExceptionMessage( 'Column index must be greater than or equal to 0' );
		}
		$stmt->fetchColumn( -1 );
	}

	public function test_fetch_obj(): void {
		// No arguments (stdClass).
		$stmt = $this->driver->query( "SELECT 1, 'abc', true" );
		$this->assertEquals(
			(object) array(
				1      => '1',
				'abc'  => 'abc',
				'true' => true,
			),
			$stmt->fetchObject()
		);
		$this->assertFalse( $stmt->fetchObject() );

		// Custom class.
		$stmt   = $this->driver->query( "SELECT 1 AS col1, 'abc' AS col2, true AS col3" );
		$result = $stmt->fetchObject( FetchObjectTestClass::class );
		$this->assertInstanceOf( FetchObjectTestClass::class, $result );
		$this->assertSame( '1', $result->col1 );
		$this->assertSame( 'abc', $result->col2 );
		$this->assertSame( '1', $result->col3 );
		$this->assertNull( $result->arg1 );
		$this->assertNull( $result->arg2 );
		$this->assertFalse( $stmt->fetchObject( FetchObjectTestClass::class ) );

		// Custom class with constructor arguments.
		$stmt   = $this->driver->query( "SELECT 1 AS col1, 'abc' AS col2, true AS col3" );
		$result = $stmt->fetchObject( FetchObjectTestClass::class, array( 'val1', 'val2' ) );
		$this->assertInstanceOf( FetchObjectTestClass::class, $result );
		$this->assertSame( '1', $result->col1 );
		$this->assertSame( 'abc', $result->col2 );
		$this->assertSame( '1', $result->col3 );
		$this->assertSame( 'val1', $result->arg1 );
		$this->assertSame( 'val2', $result->arg2 );
		$this->assertFalse( $stmt->fetchObject( FetchObjectTestClass::class, array( 'val1', 'val2' ) ) );
	}

	public function test_attr_default_fetch_mode(): void {
		$this->driver->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM );
		$result = $this->driver->query( "SELECT 'a', 'b', 'c'" );
		$this->assertSame(
			array( 'a', 'b', 'c' ),
			$result->fetch()
		);

		$this->driver->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
		$result = $this->driver->query( "SELECT 'a', 'b', 'c'" );
		$this->assertSame(
			array(
				'a' => 'a',
				'b' => 'b',
				'c' => 'c',
			),
			$result->fetch()
		);
	}

	public function test_attr_stringify_fetches(): void {
		$this->driver->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );
		$result = $this->driver->query( "SELECT 123, 1.23, 'abc', true, false" );
		$this->assertSame(
			array( '123', '1.23', 'abc', '1', '0' ),
			$result->fetch( PDO::FETCH_NUM )
		);

		$this->driver->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, false );
		$result = $this->driver->query( "SELECT 123, 1.23, 'abc', true, false" );
		$this->assertSame(
			/*
			 * On PHP < 8.1, "PDO::ATTR_STRINGIFY_FETCHES" set to "false" has no
			 * effect when "PDO::ATTR_EMULATE_PREPARES" is "true" (the default).
			 *
			 * TODO: Consider supporting non-string values on PHP < 8.1 when both
			 *       "PDO::ATTR_STRINGIFY_FETCHES" and "PDO::ATTR_EMULATE_PREPARES"
			 *       are set to "false". This would require emulating the behavior,
			 *       as PDO SQLite on PHP < 8.1 seems to always return strings.
			 */
			PHP_VERSION_ID < 80100
				? array( '123', '1.23', 'abc', '1', '0' )
				: array( 123, 1.23, 'abc', 1, 0 ),
			$result->fetch( PDO::FETCH_NUM )
		);
	}

	public function data_pdo_fetch_methods(): Generator {
		// PDO::FETCH_BOTH
		yield 'PDO::FETCH_BOTH' => array(
			"SELECT 1, 'abc', 2, 'two' as `2`",
			PDO::FETCH_BOTH,
			PHP_VERSION_ID < 80000
				? array(
					1     => '1',
					2     => 'two',
					'abc' => 'abc',
					3     => 'abc',
					4     => '2',
					5     => 'two',
				)
				: array(
					1     => '1',
					0     => '1',
					'abc' => 'abc',
					2     => 'two',
					3     => 'two',
				),
		);

		// PDO::FETCH_NUM
		yield 'PDO::FETCH_NUM' => array(
			"SELECT 1, 'abc', 2, 'two' as `2`",
			PDO::FETCH_NUM,
			array( '1', 'abc', '2', 'two' ),
		);

		// PDO::FETCH_ASSOC
		yield 'PDO::FETCH_ASSOC' => array(
			"SELECT 1, 'abc', 2, 'two' as `2`",
			PDO::FETCH_ASSOC,
			array(
				1     => '1',
				'abc' => 'abc',
				2     => 'two',
			),
		);

		// PDO::FETCH_NAMED
		yield 'PDO::FETCH_NAMED' => array(
			"SELECT 1, 'abc', 2, 'two' as `2`",
			PDO::FETCH_NAMED,
			array(
				1     => '1',
				'abc' => 'abc',
				2     => array( '2', 'two' ),
			),
		);

		// PDO::FETCH_OBJ
		yield 'PDO::FETCH_OBJ' => array(
			"SELECT 1, 'abc', 2, 'two' as `2`",
			PDO::FETCH_OBJ,
			(object) array(
				1     => '1',
				'abc' => 'abc',
				2     => 'two',
			),
		);
	}
}
