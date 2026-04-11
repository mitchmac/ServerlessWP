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
	 * @var WP_PDO_MySQL_On_SQLite
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
	 * @param WP_PDO_MySQL_On_SQLite               $driver         The SQLite driver instance.
	 * @param WP_SQLite_Information_Schema_Builder $schema_builder The information schema builder instance.
	 */
	public function __construct(
		WP_PDO_MySQL_On_SQLite $driver,
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
			$this->ensure_database_data();
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
					WP_PDO_MySQL_On_SQLite::GLOBAL_VARIABLES_TABLE_NAME
				)
			)
		);
	}

	/**
	 * Ensure that the database data is correctly populated.
	 *
	 * This method ensures that the "INFORMATION_SCHEMA.SCHEMATA" table contains
	 * records for both the "INFORMATION_SCHEMA" database and the user database.
	 * At the moment, only a single user database is supported.
	 *
	 * Additionally, this method ensures that the user database name is stored
	 * correctly in all the information schema tables.
	 */
	public function ensure_database_data(): void {
		// Get all databases from the "SCHEMATA" table.
		$schemata_table = $this->schema_builder->get_table_name( false, 'schemata' );
		$databases      = $this->driver->execute_sqlite_query(
			sprintf(
				'SELECT SCHEMA_NAME FROM %s',
				$this->driver->get_connection()->quote_identifier( $schemata_table )
			)
		)->fetchAll( PDO::FETCH_COLUMN ); // phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO

		// Ensure that the "INFORMATION_SCHEMA" database record exists.
		if ( ! in_array( 'information_schema', $databases, true ) ) {
			$this->driver->execute_sqlite_query(
				sprintf(
					'INSERT INTO %s (SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME) VALUES (?, ?, ?)',
					$this->driver->get_connection()->quote_identifier( $schemata_table )
				),
				// The "INFORMATION_SCHEMA" database stays on "utf8mb3" even in MySQL 8 and 9.
				array( 'information_schema', 'utf8mb3', 'utf8mb3_general_ci' )
			);
		}

		// Get the existing user database name.
		$existing_user_db_name = null;
		foreach ( $databases as $database ) {
			if ( 'information_schema' !== strtolower( $database ) ) {
				$existing_user_db_name = $database;
				break;
			}
		}

		// Ensure that the user database record exists.
		if ( null === $existing_user_db_name ) {
			$existing_user_db_name = WP_SQLite_Information_Schema_Builder::SAVED_DATABASE_NAME;
			$this->driver->execute_sqlite_query(
				sprintf(
					'INSERT INTO %s (SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME) VALUES (?, ?, ?)',
					$this->driver->get_connection()->quote_identifier( $schemata_table )
				),
				// @TODO: This should probably be version-dependent.
				//        Before MySQL 8, the default was different.
				array( $existing_user_db_name, 'utf8mb4', 'utf8mb4_0900_ai_ci' )
			);
		}

		// Migrate from older versions without dynamic database names.
		$saved_database_name = WP_SQLite_Information_Schema_Builder::SAVED_DATABASE_NAME;
		if ( $saved_database_name !== $existing_user_db_name ) {
			// INFORMATION_SCHEMA.SCHEMATA
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET SCHEMA_NAME = ? WHERE SCHEMA_NAME != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $schemata_table )
				),
				array( $saved_database_name )
			);

			// INFORMATION_SCHEMA.TABLES
			$tables_table = $this->schema_builder->get_table_name( false, 'tables' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET TABLE_SCHEMA = ? WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $tables_table )
				),
				array( $saved_database_name )
			);

			// INFORMATION_SCHEMA.COLUMNS
			$columns_table = $this->schema_builder->get_table_name( false, 'columns' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET TABLE_SCHEMA = ? WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $columns_table )
				),
				array( $saved_database_name )
			);

			// INFORMATION_SCHEMA.STATISTICS
			$statistics_table = $this->schema_builder->get_table_name( false, 'statistics' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET TABLE_SCHEMA = ?, INDEX_SCHEMA = ? WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $statistics_table )
				),
				array( $saved_database_name, $saved_database_name )
			);

			// INFORMATION_SCHEMA.TABLE_CONSTRAINTS
			$table_constraints_table = $this->schema_builder->get_table_name( false, 'table_constraints' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET TABLE_SCHEMA = ?, CONSTRAINT_SCHEMA = ? WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $table_constraints_table )
				),
				array( $saved_database_name, $saved_database_name )
			);

			// INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
			$referential_constraints_table = $this->schema_builder->get_table_name( false, 'referential_constraints' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET CONSTRAINT_SCHEMA = ?, UNIQUE_CONSTRAINT_SCHEMA = ? WHERE CONSTRAINT_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $referential_constraints_table )
				),
				array( $saved_database_name, $saved_database_name )
			);

			// INFORMATION_SCHEMA.KEY_COLUMN_USAGE
			$key_column_usage_table = $this->schema_builder->get_table_name( false, 'key_column_usage' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s
					SET
					  TABLE_SCHEMA = ?,
					  CONSTRAINT_SCHEMA = ?,
					  REFERENCED_TABLE_SCHEMA = CASE WHEN REFERENCED_TABLE_SCHEMA IS NULL THEN NULL ELSE ? END
					WHERE TABLE_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $key_column_usage_table )
				),
				array( $saved_database_name, $saved_database_name, $saved_database_name )
			);

			// INFORMATION_SCHEMA.CHECK_CONSTRAINTS
			$check_constraints_table = $this->schema_builder->get_table_name( false, 'check_constraints' );
			$this->driver->execute_sqlite_query(
				sprintf(
					"UPDATE %s SET CONSTRAINT_SCHEMA = ? WHERE CONSTRAINT_SCHEMA != 'information_schema'",
					$this->driver->get_connection()->quote_identifier( $check_constraints_table )
				),
				array( $saved_database_name )
			);
		}
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
					WP_PDO_MySQL_On_SQLite::GLOBAL_VARIABLES_TABLE_NAME
				)
			),
			array(
				WP_PDO_MySQL_On_SQLite::DRIVER_VERSION_VARIABLE_NAME,
				SQLITE_DRIVER_VERSION,
				SQLITE_DRIVER_VERSION,
			)
		);
	}
}
