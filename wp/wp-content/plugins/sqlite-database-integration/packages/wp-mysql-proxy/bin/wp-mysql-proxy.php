<?php declare( strict_types = 1 );

use WP_MySQL_Proxy\MySQL_Proxy;
use WP_MySQL_Proxy\Adapter\SQLite_Adapter;
use WP_MySQL_Proxy\Logger;

require_once __DIR__ . '/../vendor/autoload.php';

define( 'WP_SQLITE_AST_DRIVER', true );

// Process CLI arguments:
$shortopts = 'h:d:p:l:';
$longopts  = array( 'help', 'database:', 'port:', 'log-level:' );
$opts      = getopt( $shortopts, $longopts );

$help = <<<USAGE
Usage: php bin/wp-mysql-proxy.php [--port <port>] [--database <path/to/db.sqlite>] [--log-level <log_level>]

Options:
  -h, --help              Show this help message and exit.
  -p, --port=<port>       The port to listen on. Default: 3306
  -d, --database=<path>   The path to the SQLite database file. Default: :memory:
  -l, --log-level=<level> The log level to use. One of 'error', 'warning', 'info', 'debug'. Default: info

USAGE;

// Help.
if ( isset( $opts['h'] ) || isset( $opts['help'] ) ) {
	fwrite( STDERR, $help );
	exit( 0 );
}

// Database path.
$db_path = $opts['d'] ?? $opts['database'] ?? ':memory:';

// Port.
$port = (int) ( $opts['p'] ?? $opts['port'] ?? 3306 );
if ( $port < 1 || $port > 65535 ) {
	fwrite( STDERR, "Error: --port must be an integer between 1 and 65535. Use --help for more information.\n" );
	exit( 1 );
}

// Log level.
$log_level = $opts['l'] ?? $opts['log-level'] ?? 'info';
if ( ! in_array( $log_level, Logger::LEVELS, true ) ) {
	fwrite( STDERR, 'Error: --log-level must be one of: ' . implode( ', ', Logger::LEVELS ) . ". Use --help for more information.\n" );
	exit( 1 );
}

// Start the MySQL proxy.
$proxy = new MySQL_Proxy(
	new SQLite_Adapter( $db_path ),
	array(
		'port'      => $port,
		'log_level' => $log_level,
	)
);
$proxy->start();
