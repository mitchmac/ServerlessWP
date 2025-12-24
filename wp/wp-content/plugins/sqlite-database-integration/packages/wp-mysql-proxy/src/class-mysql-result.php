<?php declare( strict_types = 1 );

namespace WP_MySQL_Proxy;

class MySQL_Result {
	public $affected_rows  = 0;
	public $last_insert_id = null;
	public $columns        = array();
	public $rows           = array();

	public $error_info = null;

	public static function from_data( int $affected_rows, int $last_insert_id, array $columns, array $rows ): self {
		$result                 = new self();
		$result->affected_rows  = $affected_rows;
		$result->last_insert_id = $last_insert_id;
		$result->columns        = $columns;
		$result->rows           = $rows;
		return $result;
	}

	public static function from_error( string $sql_state, int $code, string $message ): self {
		$result             = new self();
		$result->error_info = array( $sql_state, $code, $message );
		return $result;
	}
}
