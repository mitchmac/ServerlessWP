<?php declare( strict_types = 1 );

namespace WP_MySQL_Proxy;

use Throwable;
use WP_MySQL_Proxy\Adapter\Adapter;

/**
 * MySQL server session handling a single client connection.
 */
class MySQL_Session {
	/**
	 * Client capabilites that are supported by the server.
	 */
	const CAPABILITIES = (
		MySQL_Protocol::CLIENT_PROTOCOL_41
		| MySQL_Protocol::CLIENT_DEPRECATE_EOF
		| MySQL_Protocol::CLIENT_SECURE_CONNECTION
		| MySQL_Protocol::CLIENT_PLUGIN_AUTH
		| MySQL_Protocol::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA
		| MySQL_Protocol::CLIENT_CONNECT_WITH_DB
	);

	/**
	 * MySQL server version.
	 *
	 * @var string
	 */
	private $server_version = '8.0.38-php-mysql-server';

	/**
	 * Character set that is used by the server.
	 *
	 * @var int
	 */
	private $character_set = MySQL_Protocol::CHARSET_UTF8MB4;

	/**
	 * Status flags representing the server state.
	 *
	 * @var int
	 */
	private $status_flags = MySQL_Protocol::SERVER_STATUS_AUTOCOMMIT;

	/**
	 * An adapter instance to execute MySQL queries.
	 *
	 * @var Adapter
	 */
	private $adapter;

	/**
	 * Connection ID.
	 *
	 * @var int
	 */
	private $connection_id;

	/**
	 * Client capabilities.
	 *
	 * @var int
	 */
	private $client_capabilities = 0;

	/**
	 * Authentication plugin data (a random 20-byte salt/scramble).
	 *
	 * @var string
	 */
	private $auth_plugin_data;

	/**
	 * Whether the client is authenticated.
	 *
	 * @var bool
	 */
	private $is_authenticated = false;

	/**
	 * Packet sequence ID.
	 *
	 * @var int
	 */
	private $packet_id;

	/**
	 * Buffer to store incoming data from the client.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * Constructor.
	 *
	 * @param Adapter $adapter       The MySQL query adapter instance.
	 * @param int     $connection_id The connection ID.
	 */
	public function __construct( Adapter $adapter, int $connection_id ) {
		$this->adapter          = $adapter;
		$this->connection_id    = $connection_id;
		$this->auth_plugin_data = '';
		$this->packet_id        = 0;

		// Generate random auth plugin data (20-byte salt)
		$this->auth_plugin_data = random_bytes( 20 );
	}

	/**
	 * Check if there's any buffered data that hasn't been processed yet
	 *
	 * @return bool True if there's data in the buffer
	 */
	public function has_buffered_data(): bool {
		return strlen( $this->buffer ) > 0;
	}

	/**
	 * Get the number of bytes currently in the buffer
	 *
	 * @return int Number of bytes in buffer
	 */
	public function get_buffer_size(): int {
		return strlen( $this->buffer );
	}

	/**
	 * Get the initial handshake packet to send to the client.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_connection_phase.html#sect_protocol_connection_phase_initial_handshake
	 *
	 * @return string The initial handshake packet.
	 */
	public function get_initial_handshake(): string {
		return MySQL_Protocol::build_handshake_packet(
			0,
			$this->server_version,
			$this->character_set,
			$this->connection_id,
			$this->auth_plugin_data,
			self::CAPABILITIES,
			$this->status_flags
		);
	}

	/**
	 * Process bytes received from the client.
	 *
	 * @param  string $data Binary data received from client.
	 * @return string|null  Response to send back to client, or null if no response needed.
	 * @throws Incomplete_Input_Exception When more data is needed to complete a packet.
	 */
	public function receive_bytes( string $data ): ?string {
		// Append new data to the existing buffer.
		$this->buffer .= $data;

		// Check if we have enough data for a packet header.
		if ( strlen( $this->buffer ) < 4 ) {
			throw new Incomplete_Input_Exception( 'Incomplete packet header, need more bytes' );
		}

		// Parse packet header.
		$payload_length       = unpack( 'V', substr( $this->buffer, 0, 3 ) . "\x00" )[1];
		$received_sequence_id = ord( $this->buffer[3] );
		$this->packet_id      = $received_sequence_id + 1;

		// Check if we have the complete packet.
		$packet_length = 4 + $payload_length;
		if ( strlen( $this->buffer ) < $packet_length ) {
			throw new Incomplete_Input_Exception(
				sprintf(
					'Incomplete packet payload, have %d bytes, but need %d bytes',
					strlen( $this->buffer ),
					$packet_length
				)
			);
		}

		// Extract the packet payload.
		$payload = substr( $this->buffer, 4, $payload_length );

		// Remove the whole packet from the buffer.
		$this->buffer = substr( $this->buffer, $packet_length );

		/*
		 * Process the packet.
		 *
		 * Depending on the lifecycle phase, handle authentication or a command.
		 *
		 * @see: https://dev.mysql.com/doc/dev/mysql-server/9.5.0/page_protocol_connection_lifecycle.html
		 */

		// Authentication phase.
		if ( ! $this->is_authenticated ) {
			return $this->process_authentication( $payload );
		}

		// Command phase.
		$command = ord( $payload[0] );
		switch ( $command ) {
			case MySQL_Protocol::COM_QUIT:
				return '';
			case MySQL_Protocol::COM_INIT_DB:
				return $this->process_query( 'USE ' . substr( $payload, 1 ) );
			case MySQL_Protocol::COM_QUERY:
				return $this->process_query( substr( $payload, 1 ) );
			case MySQL_Protocol::COM_PING:
				return MySQL_Protocol::build_ok_packet( $this->packet_id++, $this->status_flags );
			default:
				return MySQL_Protocol::build_err_packet(
					$this->packet_id++,
					0x04D2,
					'HY000',
					sprintf( 'Unsupported command: %d', $command )
				);
		}
	}

	/**
	 * Process authentication payload from the client.
	 *
	 * @param  string  $payload The authentication payload.
	 * @return string           The authentication response packet.
	 */
	private function process_authentication( string $payload ): string {
		$payload_length = strlen( $payload );

		// Decode the first 5 fields.
		$data = unpack(
			'Vclient_flags/Vmax_packet_size/Ccharacter_set/x23filler/Z*username',
			$payload
		);

		// Calculate the offset of the authentication response.
		$offset = 32 + strlen( $data['username'] ) + 1;

		$client_flags              = $data['client_flags'];
		$this->client_capabilities = $client_flags;

		// Decode the authentication response.
		$auth_response = '';
		if ( $client_flags & MySQL_Protocol::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA ) {
			$auth_response = MySQL_Protocol::read_length_encoded_string( $payload, $offset );
		} else {
			$length        = ord( $payload[ $offset++ ] );
			$auth_response = substr( $payload, $offset, $length );
			$offset       += $length;
		}

		// Get the database name.
		if ( $client_flags & MySQL_Protocol::CLIENT_CONNECT_WITH_DB ) {
			$database = MySQL_Protocol::read_null_terminated_string( $payload, $offset );
			if ( '' !== $database ) {
				$result = $this->adapter->handle_query( 'USE ' . $database );
				if ( $result->error_info ) {
					return MySQL_Protocol::build_err_packet(
						$this->packet_id++,
						1049,
						'42000',
						sprintf( "Unknown database: '%s'", $database )
					);
				}
			}
		}

		// Get the authentication plugin name.
		$auth_plugin_name = '';
		if ( $client_flags & MySQL_Protocol::CLIENT_PLUGIN_AUTH ) {
			$auth_plugin_name = MySQL_Protocol::read_null_terminated_string( $payload, $offset );
		}

		// Get the connection attributes.
		if ( $client_flags & MySQL_Protocol::CLIENT_CONNECT_ATTRS ) {
			$attrs_length = MySQL_Protocol::read_length_encoded_int( $payload, $offset );
			$offset       = min( $payload_length, $offset + $attrs_length );
			// TODO: Process connection attributes.
		}

		/**
		 * Authentication flow.
		 *
		 * @see https://dev.mysql.com/doc/dev/mysql-server/8.4.6/page_caching_sha2_authentication_exchanges.html
		 */
		if ( MySQL_Protocol::AUTH_PLUGIN_CACHING_SHA2_PASSWORD === $auth_plugin_name ) {
			// TODO: Implement authentication.
			$this->is_authenticated = true;
			if ( "\0" === $auth_response || '' === $auth_response ) {
				/*
				 * Fast path for empty password.
				 *
				 * With the "caching_sha2_password" and "sha256_password" plugins,
				 * an empty password is represented as a single "\0" character.
				 *
				 * @see https://github.com/mysql/mysql-server/blob/aa461240270d809bcac336483b886b3d1789d4d9/sql/auth/sha2_password.cc#L1017-L1022
				 */
				return MySQL_Protocol::build_ok_packet( $this->packet_id++, $this->status_flags );
			}
			$fast_auth_payload = pack( 'CC', MySQL_Protocol::AUTH_MORE_DATA_HEADER, MySQL_Protocol::CACHING_SHA2_FAST_AUTH );
			$fast_auth_packet  = MySQL_Protocol::build_packet( $this->packet_id++, $fast_auth_payload );
			return $fast_auth_packet . MySQL_Protocol::build_ok_packet( $this->packet_id++, $this->status_flags );
		} elseif ( MySQL_Protocol::AUTH_PLUGIN_MYSQL_NATIVE_PASSWORD === $auth_plugin_name ) {
			// TODO: Implement authentication.
			$this->is_authenticated = true;
			return MySQL_Protocol::build_ok_packet( $this->packet_id++, $this->status_flags );
		}

		// Unsupported authentication plugin.
		return MySQL_Protocol::build_err_packet(
			$this->packet_id++,
			0x04D2,
			'HY000',
			'Unsupported authentication plugin: ' . $auth_plugin_name
		);
	}

	/**
	 * Process a MySQL query from the client.
	 *
	 * @param  string $query The query to process.
	 * @return string        The query response packet.
	 */
	private function process_query( string $query ): string {
		$query = trim( $query );

		try {
			$result = $this->adapter->handle_query( $query );
			if ( $result->error_info ) {
				return MySQL_Protocol::build_err_packet(
					$this->packet_id++,
					$result->error_info[1],
					$result->error_info[0],
					$result->error_info[2]
				);
			}

			if ( count( $result->columns ) > 0 ) {
				return $this->build_result_set_packets(
					$result->columns,
					$result->rows,
					$result->affected_rows,
					$result->last_insert_id
				);
			}

			return MySQL_Protocol::build_ok_packet(
				$this->packet_id++,
				$this->status_flags,
				$result->affected_rows,
				$result->last_insert_id
			);
		} catch ( Throwable $e ) {
			return MySQL_Protocol::build_err_packet(
				$this->packet_id++,
				0,
				'HY000',
				'Unknown error: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Build the result set packets for a MySQL query.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset.html
	 *
	 * @param  array  $columns The columns of the result set.
	 * @param  array  $rows    The rows of the result set.
	 * @return string          The result set packets.
	 */
	private function build_result_set_packets( array $columns, array $rows, int $affected_rows, int $last_insert_id ): string {
		// Columns.
		$packets = MySQL_Protocol::build_column_count_packet( $this->packet_id++, count( $columns ) );
		foreach ( $columns as $column ) {
			$packets .= MySQL_Protocol::build_column_definition_packet( $this->packet_id++, $column );
		}

		// EOF packet, if CLIENT_DEPRECATE_EOF is not supported.
		if ( ! ( $this->client_capabilities & MySQL_Protocol::CLIENT_DEPRECATE_EOF ) ) {
			$packets .= MySQL_Protocol::build_eof_packet( $this->packet_id++, $this->status_flags );
		}

		// Rows.
		foreach ( $rows as $row ) {
			$packets .= MySQL_Protocol::build_row_packet( $this->packet_id++, $columns, $row );
		}

		// OK or EOF packet, based on the CLIENT_DEPRECATE_EOF capability.
		if ( $this->client_capabilities & MySQL_Protocol::CLIENT_DEPRECATE_EOF ) {
			$packets .= MySQL_Protocol::build_ok_packet_as_eof(
				$this->packet_id++,
				$this->status_flags,
				$affected_rows,
				$last_insert_id
			);
		} else {
			$packets .= MySQL_Protocol::build_eof_packet( $this->packet_id++, $this->status_flags );
		}
		return $packets;
	}
}
