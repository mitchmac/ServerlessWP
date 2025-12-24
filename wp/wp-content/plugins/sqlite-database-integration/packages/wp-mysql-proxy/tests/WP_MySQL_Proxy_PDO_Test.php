<?php

class WP_MySQL_Proxy_PDO_Test extends WP_MySQL_Proxy_Test {
	/** @var PDO */
	private $pdo;

	public function setUp(): void {
		parent::setUp();

		$pdo_class = PHP_VERSION_ID >= 80400 ? PDO\SQLite::class : PDO::class;
		$this->pdo = new $pdo_class(
			sprintf( 'mysql:host=127.0.0.1;port=%d', $this->port ),
			'user',
			'password'
		);
	}

	public function test_exec(): void {
		$result = $this->pdo->exec( 'CREATE TABLE t (id INT PRIMARY KEY, name TEXT)' );
		$this->assertEquals( 0, $result );

		$result = $this->pdo->exec( 'INSERT INTO t (id, name) VALUES (123, "abc"), (456, "def")' );
		$this->assertEquals( 2, $result );
	}

	public function test_query(): void {
		$this->pdo->exec( 'CREATE TABLE t (id INT PRIMARY KEY, name TEXT)' );
		$this->pdo->exec( 'INSERT INTO t (id, name) VALUES (123, "abc"), (456, "def")' );

		$result = $this->pdo->query( "SELECT 'test'" );
		$this->assertEquals( 'test', $result->fetchColumn() );

		$result = $this->pdo->query( 'SELECT * FROM t' );
		$this->assertEquals( 2, $result->rowCount() );
		$this->assertEquals(
			array(
				array(
					'id'   => 123,
					'name' => 'abc',
				),
				array(
					'id'   => 456,
					'name' => 'def',
				),
			),
			$result->fetchAll( PDO::FETCH_ASSOC )
		);
	}

	public function test_prepared_statement(): void {
		$this->pdo->exec( 'CREATE TABLE t (id INT PRIMARY KEY, name TEXT)' );
		$this->pdo->exec( 'INSERT INTO t (id, name) VALUES (123, "abc"), (456, "def")' );

		$stmt = $this->pdo->prepare( 'SELECT * FROM t WHERE id = ?' );
		$stmt->execute( array( 123 ) );
		$this->assertEquals(
			array(
				array(
					'id'   => 123,
					'name' => 'abc',
				),
			),
			$stmt->fetchAll( PDO::FETCH_ASSOC )
		);
	}
}
