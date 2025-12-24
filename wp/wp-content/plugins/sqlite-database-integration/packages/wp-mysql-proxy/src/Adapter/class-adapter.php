<?php declare( strict_types = 1 );

namespace WP_MySQL_Proxy\Adapter;

use WP_MySQL_Proxy\MySQL_Result;

interface Adapter {
	public function handle_query( string $query ): MySQL_Result;
}
