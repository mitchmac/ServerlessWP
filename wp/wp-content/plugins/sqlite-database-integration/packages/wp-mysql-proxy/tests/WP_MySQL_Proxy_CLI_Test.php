<?php

use Symfony\Component\Process\Process;

class WP_MySQL_Proxy_CLI_Test extends WP_MySQL_Proxy_Test {
	public function test_auth_without_password(): void {
		$process = Process::fromShellCommandline(
			"mysql -h 127.0.0.1 -P {$this->port} -u root -e 'SELECT 123'"
		);
		$process->run();

		$this->assertEquals( 0, $process->getExitCode() );
		$this->assertStringContainsString( '123', $process->getOutput() );
	}

	public function test_auth_with_password(): void {
		$process = Process::fromShellCommandline(
			"mysql -h 127.0.0.1 -P {$this->port} -u root -proot -e 'SELECT 123'"
		);
		$process->run();

		$this->assertEquals( 0, $process->getExitCode() );
		$this->assertStringContainsString( '123', $process->getOutput() );
	}

	public function test_auth_with_database(): void {
		$process = Process::fromShellCommandline(
			"mysql -h 127.0.0.1 -P {$this->port} -u root -proot -D sqlite_database -e 'SELECT 123'"
		);
		$process->run();

		$this->assertEquals( 0, $process->getExitCode() );
		$this->assertStringContainsString( '123', $process->getOutput() );
	}


	public function test_auth_with_unknown_database(): void {
		$process = Process::fromShellCommandline(
			"mysql -h 127.0.0.1 -P {$this->port} -u root -proot -D unknown_database -e 'SELECT 123'"
		);
		$process->run();

		$this->assertEquals( 1, $process->getExitCode() );
		$this->assertStringContainsString( "Unknown database: 'unknown_database'", $process->getErrorOutput() );
	}

	public function test_query(): void {
		$query   = 'CREATE TABLE t (id INT PRIMARY KEY, name TEXT)';
		$process = Process::fromShellCommandline(
			"mysql -h 127.0.0.1 -P {$this->port} -u root -proot -e " . escapeshellarg( $query )
		);
		$process->run();
		$this->assertEquals( 0, $process->getExitCode() );

		$query   = 'INSERT INTO t (id, name) VALUES (123, "abc"), (456, "def")';
		$process = Process::fromShellCommandline(
			"mysql -h 127.0.0.1 -P {$this->port} -u root -proot -e " . escapeshellarg( $query )
		);
		$process->run();
		$this->assertEquals( 0, $process->getExitCode() );

		$query   = 'SELECT * FROM t';
		$process = Process::fromShellCommandline(
			"mysql -h 127.0.0.1 -P {$this->port} -u root -proot -e " . escapeshellarg( $query )
		);
		$process->run();
		$this->assertEquals( 0, $process->getExitCode() );
		$this->assertSame(
			"id\tname\n123\tabc\n456\tdef\n",
			$process->getOutput()
		);
	}
}
