<?php declare( strict_types = 1 );

/*
 * Allow silencing errors for socket functions. We check the return values.
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

namespace WP_MySQL_Proxy;

use Socket;
use Throwable;
use WP_MySQL_Proxy\Adapter\Adapter;

/**
 * A MySQL proxy.
 *
 * This class manages MySQL client connections and uses an adapter instance to
 * execute MySQL queries and return their results.
 */
class MySQL_Proxy {
	/**
	 * The socket to listen on.
	 *
	 * @var Socket|resource
	 */
	private $socket;

	/**
	 * The port to listen on.
	 *
	 * @var int
	 */
	private $port;

	/**
	 * The adapter to use to execute queries.
	 *
	 * @var Adapter
	 */
	private $adapter;

	/**
	 * A map of connected clients.
	 *
	 * Maps client IDs to their associated socket and session instances.
	 *
	 * @var array<int, array{socket: Socket|resource, session: MySQL_Session}>
	 */
	private $clients = array();

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Adapter $adapter The adapter to use to execute MySQL queries.
	 * @param array   $options {
	 *     Optional. An associative array of options. Default empty array.
	 *
	 *     @type int    $port      The port to listen on. Default: 3306
	 *     @type string $log_level The log level to use. One of 'error', 'warning', 'info', 'debug'.
	 *                             Default: 'warning'
	 * }
	 */
	public function __construct( Adapter $adapter, $options = array() ) {
		$this->adapter = $adapter;
		$this->port    = $options['port'] ?? 3306;
		$this->logger  = new Logger( $options['log_level'] ?? Logger::LEVEL_WARNING );
	}

	/**
	 * Start the MySQL proxy.
	 *
	 * This method creates a socket, binds it to a port, and handles connections.
	 */
	public function start(): void {
		// Create a socket.
		$socket = @socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
		if ( false === $socket ) {
			throw $this->new_socket_error();
		}
		$this->socket = $socket;

		// Set socket options.
		if ( false === @socket_set_option( $this->socket, SOL_SOCKET, SO_REUSEADDR, 1 ) ) {
			throw $this->new_socket_error();
		}

		// Bind the socket to a port.
		if ( false === @socket_bind( $this->socket, '0.0.0.0', $this->port ) ) {
			throw $this->new_socket_error();
		}

		// Listen for connections.
		if ( false === @socket_listen( $this->socket ) ) {
			throw $this->new_socket_error();
		}

		$this->logger->info( 'MySQL proxy listening on port {port}', array( 'port' => $this->port ) );

		// Start the main proxy loop.
		while ( true ) {
			try {
				// Wait for activity on any socket.
				$read     = array_merge( array( $this->socket ), array_column( $this->clients, 'socket' ) );
				$write    = null;
				$except   = null;
				$activity = @socket_select( $read, $write, $except, null );
				if ( false === $activity ) {
					throw $this->new_socket_error();
				}

				// No activity on any socket.
				if ( $activity <= 0 ) {
					continue;
				}

				// New client connection.
				if ( in_array( $this->socket, $read, true ) ) {
					$this->handle_new_client();
					unset( $read[ array_search( $this->socket, $read, true ) ] );
				}

				// Handle client activity.
				foreach ( $read as $socket ) {
					$this->handle_client_activity( $this->get_client_id( $socket ) );
				}
			} catch ( Throwable $e ) {
				$this->logger->error( $e->getMessage() );
			}
		}
	}

	/**
	 * Handle a new MySQL client connection.
	 */
	private function handle_new_client(): void {
		$this->logger->info( 'Connecting a new client' );

		// Accept the new client connection.
		$socket = @socket_accept( $this->socket );
		if ( false === $socket ) {
			throw $this->new_socket_error();
		}

		// Create a new session for the client.
		$client_id                   = $this->get_client_id( $socket );
		$session                     = new MySQL_Session( $this->adapter, $client_id );
		$this->clients[ $client_id ] = array(
			'socket'  => $socket,
			'session' => $session,
		);
		$this->logger->info( 'Client [{client_id}]: connected', array( 'client_id' => $client_id ) );

		// Handle the initial handshake.
		$this->logger->info( 'Client [{client_id}]: initial handshake', array( 'client_id' => $client_id ) );
		$handshake = $session->get_initial_handshake();
		if ( false === @socket_write( $socket, $handshake ) ) {
			throw $this->new_socket_error();
		}
	}

	/**
	 * Handle client activity.
	 *
	 * @param int $client_id The numeric ID of the client.
	 */
	private function handle_client_activity( int $client_id ): void {
		$this->logger->info( 'Client [{client_id}]: reading data from client', array( 'client_id' => $client_id ) );

		// Read data from the client.
		$socket = $this->clients[ $client_id ]['socket'];
		$data   = @socket_read( $socket, 4096 );
		if ( false === $data ) {
			throw $this->new_socket_error();
		}

		// When debugging, display the data in a readable format.
		if ( $this->logger->is_log_level_enabled( Logger::LEVEL_DEBUG ) ) {
			$this->logger->debug(
				'Client [{client_id}] request data: {data}',
				array(
					'client_id' => $client_id,
					'data'      => $this->format_data( $data ),
				)
			);
		}

		// Handle client disconnection.
		if ( false === $data || '' === $data ) {
			$this->logger->info( 'Client [{client_id}]: disconnected', array( 'client_id' => $client_id ) );
			unset( $this->clients[ $client_id ] );
			@socket_close( $socket );
			return;
		}

		// Process client data.
		$this->logger->info( 'Client [{client_id}]: processing data', array( 'client_id' => $client_id ) );
		$session  = $this->clients[ $client_id ]['session'];
		$response = $session->receive_bytes( $data );
		if ( $response ) {
			$this->logger->info( 'Client [{client_id}]: writing response', array( 'client_id' => $client_id ) );
			if ( $this->logger->is_log_level_enabled( Logger::LEVEL_DEBUG ) ) {
				$this->logger->debug(
					'Client [{client_id}] response data: {response}',
					array(
						'client_id' => $client_id,
						'response'  => $this->format_data( $response ),
					)
				);
			}
			if ( false === @socket_write( $socket, $response ) ) {
				throw $this->new_socket_error();
			}
		}

		// Process buffered data.
		while ( $session->has_buffered_data() ) {
			$this->logger->info( 'Client [{client_id}]: processing buffered data', array( 'client_id' => $client_id ) );
			try {
				$response = $session->receive_bytes( '' );
				if ( $response ) {
					$this->logger->info( 'Client [{client_id}]: writing response', array( 'client_id' => $client_id ) );
					if ( $this->logger->is_log_level_enabled( Logger::LEVEL_DEBUG ) ) {
						$this->logger->debug(
							'Client [{client_id}] response data: {response}',
							array(
								'client_id' => $client_id,
								'response'  => $this->format_data( $response ),
							)
						);
						if ( false === @socket_write( $socket, $response ) ) {
							throw $this->new_socket_error();
						}
					}
				}
			} catch ( Incomplete_Input_Exception $e ) {
				break;
			}
		}
	}

	/**
	 * Get a numeric ID for a client connected to the proxy.
	 *
	 * @param  resource|object $socket The client Socket object or resource.
	 * @return int                     The numeric ID of the client.
	 */
	private function get_client_id( $socket ): int {
		if ( is_resource( $socket ) ) {
			return get_resource_id( $socket );
		} else {
			return spl_object_id( $socket );
		}
	}

	/**
	 * Create a new MySQL proxy exception for the last socket error.
	 *
	 * @return MySQL_Proxy_Exception
	 */
	private function new_socket_error(): MySQL_Proxy_Exception {
		$error_code    = socket_last_error();
		$error_message = socket_strerror( $error_code );
		@socket_clear_error();
		return new MySQL_Proxy_Exception( sprintf( 'Socket error: %s', $error_message ) );
	}

	/**
	 * Format MySQL protocol data for display in debug logs.
	 *
	 * @param string  $data The binary data to format.
	 * @return string       The formatted data.
	 */
	private function format_data( string $data ): string {
		$display = '';
		for ( $i = 0; $i < strlen( $data ); $i++ ) {
			$byte = ord( $data[ $i ] );
			if ( $byte >= 32 && $byte <= 126 ) {
				// Printable ASCII character
				$display .= $data[ $i ];
			} else {
				// Non-printable, show as hex
				$display .= sprintf( '%02x ', $byte );
			}
		}
		return $display;
	}
}
