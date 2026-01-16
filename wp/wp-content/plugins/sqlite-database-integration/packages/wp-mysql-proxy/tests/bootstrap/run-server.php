<?php

use WP_MySQL_Proxy\MySQL_Proxy;
use WP_MySQL_Proxy\Adapter\SQLite_Adapter;

require_once __DIR__ . '/../../vendor/autoload.php';

// Configuration.
$port    = (int) ( $_ENV['PORT'] ?? 3306 );
$db_path = $_ENV['DB_PATH'] ?? ':memory:';

// Server.
$proxy = new MySQL_Proxy(
	new SQLite_Adapter( $db_path ),
	array( 'port' => $port )
);
$proxy->start();
