<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Generic.Files.OneObjectStructurePerFile.MultipleFound

if ( ! class_exists( 'QM_Collector' ) ) {
	return;
}

/**
 * Data transfer object for SQLite query data.
 */
class SQLite_QM_Data extends QM_Data {
	/**
	 * SQLite queries indexed by normalized MySQL SQL text.
	 *
	 * @var array<string, list<string>>
	 */
	public $queries = array();
}

/**
 * Collector for SQLite query data.
 *
 * Extracts SQLite queries from $wpdb->queries and stores them
 * indexed by SQL text for the QM 4.0+ JS integration.
 */
class SQLite_QM_Collector extends QM_Collector {
	/** @var string */
	public $id = 'sqlite';

	public function get_storage(): QM_Data {
		return new SQLite_QM_Data();
	}

	public function process(): void {
		global $wpdb;

		if ( empty( $wpdb->queries ) ) {
			return;
		}

		// Index by SQL rather than row position — robust to filtering, sorting, etc.
		$mapped = array();
		foreach ( $wpdb->queries as $query ) {
			// Query Monitor skips queries with 'wp_admin_bar' in the stack.
			if ( false !== strpos( $query[2] ?? '', 'wp_admin_bar' ) ) {
				continue;
			}
			if ( ! empty( $query['sqlite_queries'] ) ) {
				$sql            = trim( preg_replace( '/\s+/', ' ', $query[0] ) );
				$mapped[ $sql ] = array_column( $query['sqlite_queries'], 'sql' );
			}
		}
		$this->data->queries = $mapped;
	}
}

/**
 * HTML outputter for SQLite query data.
 *
 * With $client_side_rendered = true, QM auto-serializes the collector data
 * into "window.QueryMonitorData.data.sqlite". This outputter's only job is to
 * emit the inline JS module that reads that data and injects SQLite query
 * details into QM 4.0's shadow DOM DB queries panel.
 */
class SQLite_QM_Output_Html extends QM_Output_Html {
	/** @var bool */
	public static $client_side_rendered = true;

	public function name(): string {
		return 'SQLite';
	}

	public function output(): void {
		if ( empty( $this->get_collector()->get_data()->queries ) ) {
			return;
		}

		$js_path = __DIR__ . '/query-monitor-sqlite.js';
		if ( is_readable( $js_path ) ) {
			echo '<script type="module">';
			include $js_path;
			echo '</script>';
		}
	}
}

/**
 * Register the SQLite collector.
 */
function register_sqlite_qm_collector( array $collectors ): array {
	$collectors['sqlite'] = new SQLite_QM_Collector();
	return $collectors;
}

add_filter( 'qm/collectors', 'register_sqlite_qm_collector', 20 );

/**
 * Register the SQLite HTML outputter.
 */
function register_sqlite_qm_output_html( array $output, QM_Collectors $collectors ): array {
	$collector = QM_Collectors::get( 'sqlite' );
	if ( $collector ) {
		$output['sqlite'] = new SQLite_QM_Output_Html( $collector );
	}
	return $output;
}

add_filter( 'qm/outputter/html', 'register_sqlite_qm_output_html', 30, 2 );
