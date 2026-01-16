<?php
/**
 * Manages the MySQL server as a subprocess for tests
 */

use Symfony\Component\Process\Process;

class MySQL_Server_Process {
	/** @var Process */
	private $process;

	public function __construct( array $options = array() ) {
		$port          = $options['port'] ?? 3306;
		$env           = array_merge(
			$_ENV,
			array(
				'PORT'    => $port,
				'DB_PATH' => $options['db_path'] ?? ':memory:',
			)
		);
		$this->process = new Process(
			array( PHP_BINARY, __DIR__ . '/run-server.php' ),
			null,
			$env
		);
		$this->process->start();

		// Wait for the server to be ready.
		for ( $i = 0; $i < 20; $i++ ) {
			$connection = @fsockopen( '127.0.0.1', $port ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $connection ) {
				fclose( $connection );
				return;
			}
			usleep( 100000 );
		}

		// Connection timed out.
		$this->stop();
		$error = $this->process->getErrorOutput();
		throw new Exception(
			sprintf( 'Server failed to start on port %d: %s', $port, $error )
		);
	}

	public function stop(): void {
		if ( isset( $this->process ) ) {
			$this->process->stop();
		}
	}

	public function get_exit_code(): ?int {
		if ( ! isset( $this->process ) ) {
			return null;
		}
		return $this->process->getExitCode() ?? null;
	}

	public function get_stdout(): string {
		if ( ! isset( $this->process ) ) {
			return '';
		}
		return $this->process->getOutput();
	}

	public function get_stderr(): string {
		if ( ! isset( $this->process ) ) {
			return '';
		}
		return $this->process->getErrorOutput();
	}
}
