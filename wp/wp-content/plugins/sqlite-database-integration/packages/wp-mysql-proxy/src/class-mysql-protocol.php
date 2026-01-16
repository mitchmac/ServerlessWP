<?php declare( strict_types = 1 );

namespace WP_MySQL_Proxy;

/**
 * MySQL wire protocol constants and helper functions.
 */
class MySQL_Protocol {
	/**
	 * MySQL protocol version.
	 *
	 * The current version 10 is used since MySQL 3.21.0.
	 */
	const PROTOCOL_VERSION = 10;

	/**
	 * MySQL client capability flags.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/group__group__cs__capabilities__flags.html
	 * @see https://github.com/mysql/mysql-server/blob/056a391cdc1af9b17b5415aee243483d1bac532d/include/mysql_com.h#L260
	 */
	const CLIENT_LONG_PASSWORD                  = 1 << 0;  // [NOT USED] Use improved version of old authentication.
	const CLIENT_FOUND_ROWS                     = 1 << 1;  // Send found rows instead of affected rows in EOF packet.
	const CLIENT_LONG_FLAG                      = 1 << 2;  // Get all column flags.
	const CLIENT_CONNECT_WITH_DB                = 1 << 3;  // Database can be specified in handshake reponse packet.
	const CLIENT_NO_SCHEMA                      = 1 << 4;  // [DEPRECATED] Don't allow "database.table.column".
	const CLIENT_COMPRESS                       = 1 << 5;  // Compression protocol supported.
	const CLIENT_ODBC                           = 1 << 6;  // Special handling of ODBC behavior. None since 3.22.
	const CLIENT_LOCAL_FILES                    = 1 << 7;  // Can use LOAD DATA LOCAL.
	const CLIENT_IGNORE_SPACE                   = 1 << 8;  // Ignore spaces before "(" (function names).
	const CLIENT_PROTOCOL_41                    = 1 << 9;  // New 4.1 protocol.
	const CLIENT_INTERACTIVE                    = 1 << 10; // This is an interactive client.
	const CLIENT_SSL                            = 1 << 11; // Use SSL encryption for the session.
	const CLIENT_IGNORE_SIGPIPE                 = 1 << 12; // Do not issue SIGPIPE if network failures occur.
	const CLIENT_TRANSACTIONS                   = 1 << 13; // Client knows about transactions.
	const CLIENT_RESERVED                       = 1 << 14; // [DEPRECATED] Old flag for the 4.1 protocol.
	const CLIENT_SECURE_CONNECTION              = 1 << 15; // [DEPRECATED] Old flag for 4.1 authentication.
	const CLIENT_MULTI_STATEMENTS               = 1 << 16; // Multi-statement support.
	const CLIENT_MULTI_RESULTS                  = 1 << 17; // Multi-result support.
	const CLIENT_PS_MULTI_RESULTS               = 1 << 18; // Multi-results and OUT parameters in PS-protocol.
	const CLIENT_PLUGIN_AUTH                    = 1 << 19; // Plugin authentication.
	const CLIENT_CONNECT_ATTRS                  = 1 << 20; // Permits connection attributes in 4.1 protocol.
	const CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA = 1 << 21; // Enable auth response packet to be larger than 255 bytes.
	const CLIENT_CAN_HANDLE_EXPIRED_PASSWORDS   = 1 << 22; // Support for expired password extension.
	const CLIENT_SESSION_TRACK                  = 1 << 23; // Capable of handling server state change information.
	const CLIENT_DEPRECATE_EOF                  = 1 << 24; // Client no longer needs EOF packet.
	const CLIENT_OPTIONAL_RESULTSET_METADATA    = 1 << 25; // The client can handle optional metadata information in the resultset.
	const CLIENT_ZSTD_COMPRESSION_ALGORITHM     = 1 << 26; // Compression protocol extended to support zstd.
	const CLIENT_QUERY_ATTRIBUTES               = 1 << 27; // Support optional extension for query parameters in query and execute commands.
	const CLIENT_MULTI_FACTOR_AUTHENTICATION    = 1 << 28; // Support multi-factor authentication.
	const CLIENT_CAPABILITY_EXTENSIONS          = 1 << 29; // Reserved to extend the 32bit capabilities structure to 64bits.
	const CLIENT_SSL_VERIFY_SERVER_CERT         = 1 << 30; // Verify server certificate.
	const CLIENT_REMEMBER_OPTIONS               = 1 << 31; // Remember options between reconnects.

	/**
	 * MySQL server status flags.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/mysql__com_8h.html#a1d854e841086925be1883e4d7b4e8cad
	 * @see https://github.com/mysql/mysql-server/blob/056a391cdc1af9b17b5415aee243483d1bac532d/include/mysql_com.h#L810
	 */
	const SERVER_STATUS_IN_TRANS          = 1 << 0;  // A multi-statement transaction has been started.
	const SERVER_STATUS_AUTOCOMMIT        = 1 << 1;  // Server in autocommit mode.
	const SERVER_STATUS_UNUSED_2          = 1 << 2;  // [UNUSED]
	const SERVER_MORE_RESULTS_EXISTS      = 1 << 3;  // Multi query - next query exists.
	const SERVER_QUERY_NO_GOOD_INDEX_USED = 1 << 4;  // No good index was used for the query.
	const SERVER_QUERY_NO_INDEX_USED      = 1 << 5;  // No index was used for the query.
	const SERVER_STATUS_CURSOR_EXISTS     = 1 << 6;  // A cursor exists for a query. FETCH must be used to get data.
	const SERVER_STATUS_LAST_ROW_SENT     = 1 << 7;  // A cursor has been exhausted. Sent in reply to FETCH command.
	const SERVER_STATUS_DB_DROPPED        = 1 << 8;  // A database was dropped.
	const SERVER_STATUS_METADATA_CHANGED  = 1 << 9;  // A set of columns changed after a prepared statement was reprepared.
	const SERVER_QUERY_WAS_SLOW           = 1 << 10; // A query was slow.
	const SERVER_PS_OUT_PARAMS            = 1 << 11; // Mark ResultSet containing output parameter values.
	const SERVER_STATUS_IN_TRANS_READONLY = 1 << 12; // Set together with SERVER_STATUS_IN_TRANS for read-only transactions.
	const SERVER_SESSION_STATE_CHANGED    = 1 << 13; // One of the server state information has changed during last statement.

	/**
	 * MySQL command types.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_command_phase.html
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/my__command_8h.html#ae2ff1badf13d2b8099af8b47831281e1
	 * @see https://github.com/mysql/mysql-server/blob/056a391cdc1af9b17b5415aee243483d1bac532d/include/my_command.h#L48
	 */
	const COM_SLEEP               = 0;  // Tells the server to sleep for the given number of seconds.
	const COM_QUIT                = 1;  // Tells the server that the client wants it to close the connection.
	const COM_INIT_DB             = 2;  // Change the default schema of the connection.
	const COM_QUERY               = 3;  // Tells the server to execute a query.
	const COM_FIELD_LIST          = 4;  // [DEPRECATED] Returns the list of fields for the given table.
	const COM_CREATE_DB           = 5;  // Currently refused by the server.
	const COM_DROP_DB             = 6;  // Currently refused by the server.
	const COM_UNUSED_2            = 7;  // [UNUSED] Used to be COM_REFRESH.
	const COM_UNUSED_1            = 8;  // [UNUSED] Used to be COM_SHUTDOWN.
	const COM_STATISTICS          = 9;  // Get a human readable string of some internal status vars.
	const COM_UNUSED_4            = 10; // [UNUSED] Used to be COM_PROCESS_INFO.
	const COM_CONNECT             = 11; // Currently refused by the server.
	const COM_UNUSED_5            = 12; // [UNUSED] Used to be COM_PROCESS_KILL.
	const COM_DEBUG               = 13; // Dump debug info to server's stdout.
	const COM_PING                = 14; // Check if the server is alive.
	const COM_TIME                = 15; // Currently refused by the server.
	const COM_DELAYED_INSERT      = 16; // Functionality removed.
	const COM_CHANGE_USER         = 17; // Change the user of the connection.
	const COM_BINLOG_DUMP         = 18; // Tells the server to send the binlog dump.
	const COM_TABLE_DUMP          = 19; // Tells the server to send the table dump.
	const COM_CONNECT_OUT         = 20; // Currently refused by the server.
	const COM_REGISTER_SLAVE      = 21; // Tells the server to register a slave.
	const COM_STMT_PREPARE        = 22; // Tells the server to prepare a statement.
	const COM_STMT_EXECUTE        = 23; // Tells the server to execute a prepared statement.
	const COM_STMT_SEND_LONG_DATA = 24; // Tells the server to send long data for a prepared statement.
	const COM_STMT_CLOSE          = 25; // Tells the server to close a prepared statement.
	const COM_STMT_RESET          = 26; // Tells the server to reset a prepared statement.
	const COM_SET_OPTION          = 27; // Tells the server to set an option.
	const COM_STMT_FETCH          = 28; // Tells the server to fetch a result from a prepared statement.
	const COM_DAEMON              = 29; // Currently refused by the server.
	const COM_BINLOG_DUMP_GTID    = 30; // Tells the server to send the binlog dump in GTID mode.
	const COM_RESET_CONNECTION    = 31; // Tells the server to reset the connection.
	const COM_CLONE               = 32; // Tells the server to clone a server.

	/**
	 * MySQL field types.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/field__types_8h.html#a69e798807026a0f7e12b1d6c72374854
	 * @see https://github.com/mysql/mysql-server/blob/056a391cdc1af9b17b5415aee243483d1bac532d/include/field_types.h#L55
	 *
	 */
	const FIELD_TYPE_DECIMAL     = 0;
	const FIELD_TYPE_TINY        = 1;
	const FIELD_TYPE_SHORT       = 2;
	const FIELD_TYPE_LONG        = 3;
	const FIELD_TYPE_FLOAT       = 4;
	const FIELD_TYPE_DOUBLE      = 5;
	const FIELD_TYPE_NULL        = 6;
	const FIELD_TYPE_TIMESTAMP   = 7;
	const FIELD_TYPE_LONGLONG    = 8;
	const FIELD_TYPE_INT24       = 9;
	const FIELD_TYPE_DATE        = 10;
	const FIELD_TYPE_TIME        = 11;
	const FIELD_TYPE_DATETIME    = 12;
	const FIELD_TYPE_YEAR        = 13;
	const FIELD_TYPE_NEWDATE     = 14;
	const FIELD_TYPE_VARCHAR     = 15;
	const FIELD_TYPE_BIT         = 16;
	const FIELD_TYPE_NEWDECIMAL  = 246;
	const FIELD_TYPE_ENUM        = 247;
	const FIELD_TYPE_SET         = 248;
	const FIELD_TYPE_TINY_BLOB   = 249;
	const FIELD_TYPE_MEDIUM_BLOB = 250;
	const FIELD_TYPE_LONG_BLOB   = 251;
	const FIELD_TYPE_BLOB        = 252;
	const FIELD_TYPE_VAR_STRING  = 253;
	const FIELD_TYPE_STRING      = 254;
	const FIELD_TYPE_GEOMETRY    = 255;

	/**
	 * MySQL field flags.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/group__group__cs__column__definition__flags.html
	 * @see https://github.com/mysql/mysql-server/blob/056a391cdc1af9b17b5415aee243483d1bac532d/include/mysql_com.h#L154
	 */
	const FIELD_NOT_NULL_FLAG            = 1 << 0;  // Field can't be NULL.
	const FIELD_PRI_KEY_FLAG             = 1 << 1;  // Field is part of a primary key.
	const FIELD_UNIQUE_KEY_FLAG          = 1 << 2;  // Field is part of a unique key.
	const FIELD_MULTIPLE_KEY_FLAG        = 1 << 3;  // Field is part of a key.
	const FIELD_BLOB_FLAG                = 1 << 4;  // Field is a blob.
	const FIELD_UNSIGNED_FLAG            = 1 << 5;  // Field is an unsigned integer.
	const FIELD_ZEROFILL_FLAG            = 1 << 6;  // Field is a zero-filled integer.
	const FIELD_BINARY_FLAG              = 1 << 7;  // Field is binary.
	const FIELD_ENUM_FLAG                = 1 << 8;  // Field is an enum.
	const FIELD_AUTO_INCREMENT_FLAG      = 1 << 9;  // Field is an auto-increment field.
	const FIELD_TIMESTAMP_FLAG           = 1 << 10; // Field is a timestamp.
	const FIELD_SET_FLAG                 = 1 << 11; // Field is a set.
	const FIELD_NO_DEFAULT_VALUE_FLAG    = 1 << 12; // Field doesn't have default value.
	const FIELD_ON_UPDATE_NOW_FLAG       = 1 << 13; // Field is set to NOW on UPDATE.
	const FIELD_PART_KEY_FLAG            = 1 << 14; // [INTERNAL] Field is part of a key.
	const FIELD_NUM_FLAG                 = 1 << 15; // Field is a number.
	const FIELD_UNIQUE_FLAG              = 1 << 16; // [INTERNAL]
	const FIELD_BINCMP_FLAG              = 1 << 17; // [INTERNAL]
	const FIELD_GET_FIXED_FIELDS_FLAG    = 1 << 18; // Used to get fields in item tree.
	const FIELD_IN_PART_FUNC_FLAG        = 1 << 19; // Field part of partition function.
	const FIELD_IN_ADD_INDEX_FLAG        = 1 << 20; // [INTERNAL]
	const FIELD_IS_RENAMED_FLAG          = 1 << 21; // [INTERNAL]
	const FIELD_FLAGS_STORAGE_MEDIA_FLAG = 1 << 22; // Field storage media, bit 22-23.
	const FIELD_FLAGS_STORAGE_MEDIA_MASK = 3 << self::FIELD_FLAGS_STORAGE_MEDIA_FLAG;
	const FIELD_FLAGS_COLUMN_FORMAT_FLAG = 1 << 24; // Field column format, bit 24-25.
	const FIELD_FLAGS_COLUMN_FORMAT_MASK = 3 << self::FIELD_FLAGS_COLUMN_FORMAT_FLAG;
	const FIELD_IS_DROPPED_FLAG          = 1 << 26; // [INTERNAL]
	const FIELD_EXPLICIT_NULL_FLAG       = 1 << 27; // Field is explicitly specified as NULL by user.
	const FIELD_GROUP_FLAG               = 1 << 28; // [INTERNAL]
	const FIELD_NOT_SECONDARY_FLAG       = 1 << 29; // Field will not be loaded in secondary engine.
	const FIELD_IS_INVISIBLE_FLAG        = 1 << 30; // Field is explicitly marked as invisible by user.

	/**
	 * Special packet headers.
	 *
	 * @see https://github.com/mysql/mysql-server/blob/056a391cdc1af9b17b5415aee243483d1bac532d/extra/boost/boost_1_87_0/boost/mysql/impl/internal/protocol/deserialization.hpp#L257
	 */
	const OK_PACKET_HEADER  = 0x00;
	const EOF_PACKET_HEADER = 0xfe;
	const ERR_PACKET_HEADER = 0xff;

	/**
	 * MySQL server-side authentication plugins.
	 *
	 * This list includes only server-side plugins for MySQL Standard Edition.
	 * MySQL Enterprise Edition has additional plugins that are not listed here.
	 *
	 * @see https://dev.mysql.com/doc/refman/8.4/en/authentication-plugins.html
	 * @see https://dev.mysql.com/doc/refman/8.4/en/pluggable-authentication.html
	 */
	const DEFAULT_AUTH_PLUGIN               = self::AUTH_PLUGIN_CACHING_SHA2_PASSWORD;
	const AUTH_PLUGIN_MYSQL_NATIVE_PASSWORD = 'mysql_native_password'; // [DEPRECATED] Old built-in authentication. Default in MySQL < 8.0.
	const AUTH_PLUGIN_CACHING_SHA2_PASSWORD = 'caching_sha2_password'; // Pluggable SHA-2 authentication. Default in MySQL >= 8.0.
	const AUTH_PLUGIN_SHA256_PASSWORD       = 'sha256_password';       // [DEPRECATED] Basic SHA-256 authentication.
	const AUTH_PLUGIN_NO_LOGIN              = 'no_login_plugin';       // Disable client connection for specific accounts.
	const AUTH_PLUGIN_SOCKET                = 'auth_socket';           // Authenticate local Unix socket connections.

	// Auth specific markers for caching_sha2_password
	const AUTH_MORE_DATA_HEADER  = 0x01;  // followed by 1 byte (caching_sha2_password specific)
	const CACHING_SHA2_FAST_AUTH = 3;
	const CACHING_SHA2_FULL_AUTH = 4;

	// Character set and collation constants
	const CHARSET_UTF8MB4 = 0xff;

	// Max packet length constant
	const MAX_PACKET_LENGTH = 0x00ffffff;

	/**
	 * Build the OK packet.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_ok_packet.html
	 *
	 * @param  int $sequence_id    The sequence ID of the packet.
	 * @param  int $server_status  The status flags representing the server state.
	 * @param  int $affected_rows  Number of rows affected by the query.
	 * @param  int $last_insert_id The last insert ID.
	 * @param  int $warning_count  The warning count.
	 * @param  int $packet_header  The packet header, indicating an OK or EOF semantic.
	 * @return string              The OK packet.
	 */
	public static function build_ok_packet(
		int $sequence_id,
		int $server_status,
		int $affected_rows = 0,
		int $last_insert_id = 0,
		int $warning_count = 0,
		int $packet_header = self::OK_PACKET_HEADER
	): string {
		/**
		 * Assemble the OK packet payload.
		 *
		 * Use a single pack() function call for maximum efficiency.
		 *
		 * C  = 8-bit unsigned integer
		 * v  = 16-bit unsigned integer (little-endian byte order)
		 * a* = string
		 *
		 * @see https://www.php.net/manual/en/function.pack.php
		 */
		$payload = pack(
			'Ca*a*vv',
			$packet_header,                                     // (C)  OK packet header.
			self::encode_length_encoded_int( $affected_rows ),  // (a*) Affected rows.
			self::encode_length_encoded_int( $last_insert_id ), // (a*) Last insert ID.
			$server_status,                                     // (v)  Server status flags.
			$warning_count,                                     // (v)  Server status flags.
		);
		return self::build_packet( $sequence_id, $payload );
	}

	/**
	 * Build the OK packet with an EOF header.
	 *
	 * When the CLIENT_DEPRECATE_EOF capability is supported, an OK packet with
	 * an EOF header is used to mark EOF, instead of the deprecated EOF packet.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_ok_packet.html
	 *
	 * @param  int $sequence_id    The sequence ID of the packet.
	 * @param  int $server_status  The status flags representing the server state.
	 * @param  int $affected_rows  Number of rows affected by the query.
	 * @param  int $last_insert_id The last insert ID.
	 * @param  int $warning_count  The warning count.
	 * @return string              The OK packet.
	 */
	public static function build_ok_packet_as_eof(
		int $sequence_id,
		int $server_status,
		int $affected_rows = 0,
		int $last_insert_id = 0,
		int $warning_count = 0
	): string {
		return self::build_ok_packet(
			$sequence_id,
			$server_status,
			$affected_rows,
			$last_insert_id,
			$warning_count,
			self::EOF_PACKET_HEADER
		);
	}

	/**
	 * Build the ERR packet.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_err_packet.html
	 *
	 * @param  int    $sequence_id The sequence ID of the packet.
	 * @param  int    $error_code  The error code.
	 * @param  string $sql_state   The SQLSTATE value.
	 * @param  string $message     The error message.
	 * @return string              The ERR packet.
	 */
	public static function build_err_packet(
		int $sequence_id,
		int $error_code,
		string $sql_state,
		string $message
	): string {
		/**
		 * Assemble the ERR packet payload.
		 *
		 * Use a single pack() function call for maximum efficiency.
		 *
		 * C  = 8-bit unsigned integer
		 * v  = 16-bit unsigned integer (little-endian byte order)
		 * a* = string
		 *
		 * @see https://www.php.net/manual/en/function.pack.php
		 */
		$payload = pack(
			'Cva*a*',
			self::ERR_PACKET_HEADER,        // (C)  ERR packet header.
			$error_code,                    // (v)  Error code.
			'#' . strtoupper( $sql_state ), // (a*) SQL state.
			$message,                       // (a*) Message.
		);
		return self::build_packet( $sequence_id, $payload );
	}

	/**
	 * Build the EOF packet.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_eof_packet.html
	 *
	 * @param  int $sequence_id   The sequence ID of the packet.
	 * @param  int $server_status The status flags representing the server state.
	 * @param  int $warning_count The warning count.
	 * @return string             The EOF packet.
	 */
	public static function build_eof_packet(
		int $sequence_id,
		int $server_status,
		int $warning_count = 0
	): string {
		/**
		 * Assemble the EOF packet payload.
		 *
		 * Use a single pack() function call for maximum efficiency.
		 *
		 * C  = 8-bit unsigned integer
		 * v  = 16-bit unsigned integer (little-endian byte order)
		 * a* = string
		 *
		 * @see https://www.php.net/manual/en/function.pack.php
		 */
		$payload = pack(
			'Cvv',
			self::EOF_PACKET_HEADER, // (C)  EOF packet header.
			$warning_count,          // (v)  Warning count.
			$server_status,          // (v)  Status flags.
		);
		return self::build_packet( $sequence_id, $payload );
	}

	/**
	 * Build a handshake packet for the initial handshake.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_connection_phase_packets_protocol_handshake_v10.html
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_connection_phase.html#sect_protocol_connection_phase_initial_handshake
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_caching_sha2_authentication_exchanges.html
	 *
	 * @param  int    $sequence_id      The sequence ID of the packet.
	 * @param  string $server_version   The version of the MySQL server.
	 * @param  int    $charset          The character set that is used by the server.
	 * @param  int    $connection_id    The connection ID.
	 * @param  string $auth_plugin_data The authentication plugin data (scramble).
	 * @param  int    $capabilities     The capabilities that are supported by the server.
	 * @param  int    $status_flags     The status flags representing the server state.
	 * @return string                   The handshake packet.
	 */
	public static function build_handshake_packet(
		int $sequence_id,
		string $server_version,
		int $charset,
		int $connection_id,
		string $auth_plugin_data,
		int $capabilities,
		int $status_flags
	): string {
		$cap_flags_lower = $capabilities & 0xffff;
		$cap_flags_upper = $capabilities >> 16;
		$scramble1       = substr( $auth_plugin_data, 0, 8 );
		$scramble2       = substr( $auth_plugin_data, 8 );

		if ( $capabilities & MySQL_Protocol::CLIENT_PLUGIN_AUTH ) {
			$auth_plugin_data_length = strlen( $auth_plugin_data ) + 1;
			$auth_plugin_name        = self::DEFAULT_AUTH_PLUGIN . "\0";
		} else {
			$auth_plugin_data_length = 0;
			$auth_plugin_name        = '';
		}

		/**
		 * Assemble the handshake packet payload.
		 *
		 * Use a single pack() function call for maximum efficiency.
		 *
		 * C  = 8-bit unsigned integer
		 * v  = 16-bit unsigned integer (little-endian byte order)
		 * V  = 32-bit unsigned integer (little-endian byte order)
		 * a* = string
		 * Z* = NULL-terminated string
		 *
		 * @see https://www.php.net/manual/en/function.pack.php
		 */
		$payload = pack(
			'CZ*Va*CvCvvCa*a*Ca*',
			self::PROTOCOL_VERSION,   // (C)  Protocol version.
			$server_version,          // (Z*) MySQL server version.
			$connection_id,           // (V)  Connection ID.
			$scramble1,               // (a*) First 8 bytes of auth plugin data (scramble).
			0,                        // (C)  Filler. Always 0x00.
			$cap_flags_lower,         // (v)  Lower 2 bytes of capability flags.
			$charset,                 // (C)  Default server character set.
			$status_flags,            // (v)  Server status flags.
			$cap_flags_upper,         // (v)  Upper 2 bytes of capability flags.
			$auth_plugin_data_length, // (C)  Auth plugin data length.
			str_repeat( "\0", 10 ),   // (a*) Filler. 10 bytes of 0x00.
			$scramble2,               // (a*) Remainder of auth plugin data (scramble).
			0,                        // (C)  Filler. Always 0x00.
			$auth_plugin_name,        // (a*) Auth plugin name.
		);
		return self::build_packet( $sequence_id, $payload );
	}

	/**
	 * Build the column count packet.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset.html
	 *
	 * @param  int $sequence_id  The sequence ID of the packet.
	 * @param  int $column_count The number of columns.
	 * @return string            The column count packet.
	 */
	public static function build_column_count_packet( int $sequence_id, int $column_count ): string {
		$payload = self::encode_length_encoded_int( $column_count );
		return self::build_packet( $sequence_id, $payload );
	}

	/**
	 * Build the column definition packet.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_column_definition.html
	 *
	 * @param  int    $sequence_id The sequence ID of the packet.
	 * @param  array  $column      The column definition.
	 * @return string              The column definition packet.
	 */
	public static function build_column_definition_packet( int $sequence_id, array $column ): string {
		$payload = pack(
			'a*a*a*a*a*a*a*vVCvCC',
			self::encode_length_encoded_string( $column['catalog'] ?? 'def' ),
			self::encode_length_encoded_string( $column['schema'] ?? '' ),
			self::encode_length_encoded_string( $column['table'] ?? '' ),
			self::encode_length_encoded_string( $column['orgTable'] ?? '' ),
			self::encode_length_encoded_string( $column['name'] ?? '' ),
			self::encode_length_encoded_string( $column['orgName'] ?? '' ),
			self::encode_length_encoded_int( $column['fixedLen'] ?? 0x0c ),
			$column['charset'] ?? MySQL_Protocol::CHARSET_UTF8MB4, // (v) Character set.
			$column['length'],                                     // (V)  Length.
			$column['type'],                                       // (C)  Type.
			$column['flags'],                                      // (v)  Flags.
			$column['decimals'],                                   // (C)  Decimals.
			0,                                                     // (C)  Filler. Always 0x00.
		);
		return self::build_packet( $sequence_id, $payload );
	}

	/**
	 * Build the row packet.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_row.html
	 *
	 * @param  int    $sequence_id The sequence ID of the packet.
	 * @param  array  $columns     The columns.
	 * @param  object $row         The row.
	 * @return string              The row packet.
	 */
	public static function build_row_packet( int $sequence_id, array $columns, object $row ): string {
		$payload = '';
		foreach ( $columns as $column ) {
			$value = $row->{$column['name']} ?? null;
			if ( null === $value ) {
				$payload .= "\xfb"; // NULL value
			} else {
				$payload .= self::encode_length_encoded_string( (string) $value );
			}
		}
		return self::build_packet( $sequence_id, $payload );
	}

	/**
	 * Build a MySQL packet.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_packets.html
	 *
	 * @param  int    $sequence_id The sequence ID of the packet.
	 * @param  string $payload     The payload of the packet.
	 * @return string              The packet data.
	 */
	public static function build_packet( int $sequence_id, string $payload ): string {
		/**
		 * Assemble the packet.
		 *
		 * Use a single pack() function call for maximum efficiency.
		 *
		 * C  = 8-bit unsigned integer
		 * VX = 24-bit unsigned integer (little-endian byte order)
		 *      (V = 32-bit little-endian, X drops the last byte, making it 24-bit)
		 * a* = string
		 */
		return pack(
			'VXCa*',
			strlen( $payload ), // (VX) Payload length.
			$sequence_id,       // (C)  Sequence ID.
			$payload,           // (a*) Payload.
		);
	}

	/**
	 * Encode an integer in MySQL's length-encoded format.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_integers.html
	 *
	 * @param  int $value The value to encode.
	 * @return string     The encoded value.
	 */
	public static function encode_length_encoded_int( int $value ): string {
		if ( $value < 0xfb ) {
			return chr( $value );
		} elseif ( $value <= 0xffff ) {
			return "\xfc" . pack( 'v', $value );
		} elseif ( $value <= 0xffffff ) {
			return "\xfd" . pack( 'VX', $value );
		} else {
			return "\xfe" . pack( 'P', $value );
		}
	}

	/**
	 * Encode a string in MySQL's length-encoded format.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_strings.html
	 *
	 * @param  string $value The value to encode.
	 * @return string        The encoded value.
	 */
	public static function encode_length_encoded_string( string $value ): string {
		return self::encode_length_encoded_int( strlen( $value ) ) . $value;
	}

	/**
	 * Read MySQL length-encoded integer from a payload and advance the offset.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_integers.html
	 *
	 * @param  string $payload A payload of bytes to read from.
	 * @param  int    $offset  And offset to start reading from within the payload.
	 *                         The value will be advanced by the number of bytes read.
	 * @return int             The decoded integer value.
	 */
	public static function read_length_encoded_int( string $payload, int &$offset ): int {
		$first_byte = ord( $payload[ $offset ] ?? "\0" );
		$offset    += 1;

		if ( $first_byte < 0xfb ) {
			$value = $first_byte;
		} elseif ( 0xfb === $first_byte ) {
			$value = 0;
		} elseif ( 0xfc === $first_byte ) {
			$value   = unpack( 'v', $payload, $offset )[1];
			$offset += 2;
		} elseif ( 0xfd === $first_byte ) {
			$value   = unpack( 'VX', $payload, $offset )[1];
			$offset += 3;
		} else {
			$value   = unpack( 'P', $payload, $offset )[1];
			$offset += 8;
		}
		return $value;
	}

	/**
	 * Read MySQL length-encoded string from a payload and advance the offset.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_strings.html
	 *
	 * @param  string $payload A payload of bytes to read from.
	 * @param  int    $offset  And offset to start reading from within the payload.
	 *                         The value will be advanced by the number of bytes read.
	 * @return string          The decoded string value.
	 */
	public static function read_length_encoded_string( string $payload, int &$offset ): string {
		$length  = self::read_length_encoded_int( $payload, $offset );
		$value   = substr( $payload, $offset, $length );
		$offset += $length;
		return $value;
	}

	/**
	 * Read MySQL null-terminated string from a payload and advance the offset.
	 *
	 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_strings.html
	 *
	 * @param  string $payload A payload of bytes to read from.
	 * @param  int    $offset  And offset to start reading from within the payload.
	 *                         The value will be advanced by the number of bytes read.
	 * @return string          The decoded string value.
	 */
	public static function read_null_terminated_string( string $payload, int &$offset ): string {
		$value   = unpack( 'Z*', $payload, $offset )[1];
		$offset += strlen( $value ) + 1;
		return $value;
	}
}
