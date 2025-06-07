<?php

/**
 * SQLite database configurator.
 *
 * This class initializes and configures the SQLite database, so that it can be
 * used by the SQLite driver to translate and emulate MySQL queries in SQLite.
 *
 * The configurator ensures that tables required for emulating MySQL behaviors
 * are created and populated with necessary data. It is also able to partially
 * repair and update these tables and metadata in case of database corruption.
 */
class WP_SQLite_Configurator {
	/**
	 * The SQLite driver instance.
	 *
	 * @var WP_SQLite_Driver
	 */
	private $driver;

	/**
	 * A service for managing MySQL INFORMATION_SCHEMA tables in SQLite.
	 *
	 * @var WP_SQLite_Information_Schema_Builder
	 */
	private $schema_builder;

	/**
	 * A service for reconstructing the MySQL INFORMATION_SCHEMA tables in SQLite.
	 *
	 * @var WP_SQLite_Information_Schema_Reconstructor
	 */
	private $schema_reconstructor;

	/**
	 * Constructor.
	 *
	 * @param WP_SQLite_Driver                     $driver         The SQLite driver instance.
	 * @param WP_SQLite_Information_Schema_Builder $schema_builder The information schema builder instance.
	 */
	public function __construct(
		WP_SQLite_Driver $driver,
		WP_SQLite_Information_Schema_Builder $schema_builder
	) {
		$this->driver               = $driver;
		$this->schema_builder       = $schema_builder;
		$this->schema_reconstructor = new WP_SQLite_Information_Schema_Reconstructor(
			$driver,
			$schema_builder
		);
	}

	/**
	 * Ensure that the SQLite database is configured.
	 *
	 * This method checks if the database is configured for the latest SQLite
	 * driver version, and if it is not, it will configure the database.
	 */
	public function ensure_database_configured(): void {
		$version    = SQLITE_DRIVER_VERSION;
		$db_version = $this->driver->get_saved_driver_version();
		if ( version_compare( $version, $db_version ) > 0 ) {
			$this->configure_database();
		}
	}

	/**
	 * Configure the SQLite database.
	 *
	 * This method creates tables used for emulating MySQL behaviors in SQLite,
	 * and populates them with necessary data. When it is used with an already
	 * configured database, it will update the configuration as per the current
	 * SQLite driver version and attempt to repair any configuration corruption.
	 */
	public function configure_database(): void {
		// Use an EXCLUSIVE transaction to prevent multiple connections
		// from attempting to configure the database at the same time.
		$this->driver->execute_sqlite_query( 'BEGIN EXCLUSIVE TRANSACTION' );
		try {
			$this->ensure_global_variables_table();
			$this->schema_builder->ensure_information_schema_tables();
			$this->schema_reconstructor->ensure_correct_information_schema();
			$this->save_current_driver_version();
		} catch ( Throwable $e ) {
			$this->driver->execute_sqlite_query( 'ROLLBACK' );
			throw $e;
		}
		$this->driver->execute_sqlite_query( 'COMMIT' );
	}

	/**
	 * Ensure that the global variables table exists.
	 *
	 * This method configures a database table to store MySQL global variables
	 * and other internal configuration values.
	 */
	private function ensure_global_variables_table(): void {
		$this->driver->execute_sqlite_query(
			sprintf(
				'CREATE TABLE IF NOT EXISTS %s (name TEXT PRIMARY KEY, value TEXT)',
				WP_SQLite_Driver::GLOBAL_VARIABLES_TABLE_NAME
			)
		);
	}

	/**
	 * Save the current SQLite driver version.
	 *
	 * This method saves the current SQLite driver version to the database.
	 */
	private function save_current_driver_version(): void {
		$this->driver->execute_sqlite_query(
			sprintf(
				'INSERT INTO %s (name, value) VALUES (?, ?) ON CONFLICT(name) DO UPDATE SET value = ?',
				WP_SQLite_Driver::GLOBAL_VARIABLES_TABLE_NAME
			),
			array(
				WP_SQLite_Driver::DRIVER_VERSION_VARIABLE_NAME,
				SQLITE_DRIVER_VERSION,
				SQLITE_DRIVER_VERSION,
			)
		);
	}
}
