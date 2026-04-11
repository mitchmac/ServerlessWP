<?php

use PHPUnit\Framework\TestCase;

abstract class WP_MySQL_Proxy_Test extends TestCase {
	/** @var int */
	protected $port = 3306;

	/** @var MySQL_Server_Process */
	protected $server;

	public function setUp(): void {
		$this->server = new MySQL_Server_Process(
			array(
				'port'    => $this->port,
				'db_path' => ':memory:',
			)
		);
	}

	public function tearDown(): void {
		$this->server->stop();
		$exit_code = $this->server->get_exit_code();
		if ( $this->hasFailed() || ( $exit_code > 0 && 143 !== $exit_code ) ) {
			$hr = str_repeat( '-', 80 );
			fprintf(
				STDERR,
				"\n\n$hr\nSERVER OUTPUT:\n$hr\n[RETURN CODE]: %d\n\n[STDOUT]:\n%s\n\n[STDERR]:\n%s\n$hr\n",
				$this->server->get_exit_code(),
				$this->server->get_stdout(),
				$this->server->get_stderr()
			);
		}
	}
}
