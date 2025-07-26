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
	 * The name of the database.
	 *
	 * @var string
	 */
	private $db_name;

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
	 * @param string                               $db_name        The name of the database.
	 * @param WP_SQLite_Driver                     $driver         The SQLite driver instance.
	 * @param WP_SQLite_Information_Schema_Builder $schema_builder The information schema builder instance.
	 */
	public function __construct(
		string $db_name,
		WP_SQLite_Driver $driver,
		WP_SQLite_Information_Schema_Builder $schema_builder
	) {
		$this->db_name              = $db_name;
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

		// Ensure that the database name used in the current session corresponds
		// to the database name that is stored in the information schema tables.
		$db_name = $this->driver->get_saved_database_name();
		if ( $this->db_name !== $db_name ) {
			throw new WP_SQLite_Driver_Exception(
				$this->driver,
				sprintf(
					"Incorrect database name. The database was created with name '%s', but '%s' is used in the current session.",
					$db_name,
					$this->db_name
				)
			);
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
			$this->ensure_schemata_data();
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
				$this->driver->get_connection()->quote_identifier(
					WP_SQLite_Driver::GLOBAL_VARIABLES_TABLE_NAME
				)
			)
		);
	}

	/**
	 * Ensure that the "SCHEMATA" table data is correctly populated.
	 *
	 * This method ensures that the "INFORMATION_SCHEMA.SCHEMATA" table contains
	 * records for both the "INFORMATION_SCHEMA" database and the user database.
	 * At the moment, only a single user database is supported.
	 */
	public function ensure_schemata_data(): void {
		$schemata_table = $this->schema_builder->get_table_name( false, 'schemata' );

		// 1. Ensure that the "INFORMATION_SCHEMA" database record exists.
		$this->driver->execute_sqlite_query(
			sprintf(
				'INSERT INTO %s (SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME)
				VALUES (?, ?, ?) ON CONFLICT(SCHEMA_NAME) DO NOTHING',
				$this->driver->get_connection()->quote_identifier( $schemata_table )
			),
			// The "INFORMATION_SCHEMA" database stays on "utf8mb3" even in MySQL 8 and 9.
			array( 'information_schema', 'utf8mb3', 'utf8mb3_general_ci' )
		);

		// 2. Bail out if a user database record already exists.
		$user_db_record_exists = $this->driver->execute_sqlite_query(
			sprintf(
				"SELECT COUNT(*) FROM %s WHERE SCHEMA_NAME != 'information_schema'",
				$this->driver->get_connection()->quote_identifier( $schemata_table )
			)
		)->fetchColumn() > 0;

		if ( $user_db_record_exists ) {
			return;
		}

		/*
		 * 3. Migrate from older driver versions without the "SCHEMATA" table.
		 *
		 * If a record with an existing database name value is already stored in
		 * "INFORMATION_SCHEMA.TABLES", we need to use that value. This ensures
		 * migration from older driver versions without the "SCHEMATA" table.
		 */
		$information_schema_db_name = $this->driver->execute_sqlite_query(
			sprintf(
				'SELECT table_schema FROM %s LIMIT 1',
				$this->driver->get_connection()->quote_identifier(
					$this->schema_builder->get_table_name( false, 'tables' )
				)
			)
		)->fetchColumn();

		if ( false !== $information_schema_db_name ) {
			$db_name = $information_schema_db_name;
		} else {
			$db_name = $this->db_name;
		}

		// 4. Create a user database record.
		$this->driver->execute_sqlite_query(
			sprintf(
				'INSERT INTO %s (SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME) VALUES (?, ?, ?)',
				$this->driver->get_connection()->quote_identifier( $schemata_table )
			),
			// @TODO: This should probably be version-dependent.
			//        Before MySQL 8, the default was different.
			array( $db_name, 'utf8mb4', 'utf8mb4_0900_ai_ci' )
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
				$this->driver->get_connection()->quote_identifier(
					WP_SQLite_Driver::GLOBAL_VARIABLES_TABLE_NAME
				)
			),
			array(
				WP_SQLite_Driver::DRIVER_VERSION_VARIABLE_NAME,
				SQLITE_DRIVER_VERSION,
				SQLITE_DRIVER_VERSION,
			)
		);
	}
}
