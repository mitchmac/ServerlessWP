<?php

namespace DeliciousBrains\WP_Offload_Media;

use Amazon_S3_And_CloudFront;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for custom tables.
 */
trait Custom_Table_Trait {
	/**
	 * Keeps track of whether custom table check has been done in current process for each blog.
	 *
	 * @var array
	 */
	private static array $checked_table_exists = array();

	/**
	 * Is retrieving from object cache enabled?
	 *
	 * @var bool
	 */
	private static bool $cache_enabled = true;

	/**
	 * Every record must have a last upgrade routine value.
	 *
	 * @var int
	 */
	private int $last_upgrade_routine = 0;

	/**
	 * Get the full table name for current blog.
	 *
	 * If the table does not exist, it's created.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		/* @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		$table_name = self::get_prefixed_table_name();

		if ( empty( self::$checked_table_exists[ $table_name ] ) ) {
			self::$checked_table_exists[ $table_name ] = true;

			// Schema may differ by plugin version.
			$plugin_version = $as3cf->get_plugin_version();
			$schema_version = get_option( $table_name . '_schema_version', '0.0.0' );

			// Schema may differ by upgrade routine.
			$upgrade_routine        = $as3cf->get_setting( 'post_meta_version', 0 );
			$schema_upgrade_routine = get_option( $table_name . '_schema_upgrade_routine', 0 );

			if (
				version_compare( $schema_version, $plugin_version, '<' ) ||
				$schema_upgrade_routine < $upgrade_routine ||
				! self::table_exists()
			) {
				static::install_table( $table_name, $plugin_version, $upgrade_routine );

				// We've potentially changed format of stored cached data or their keys, so flush cache.
				// We don't care whether it was succesfull or not, at least we tried.
				wp_cache_flush();

				update_option( $table_name . '_schema_version', $plugin_version );
				update_option( $table_name . '_schema_upgrade_routine', $upgrade_routine );
			}
		}

		return $table_name;
	}

	/**
	 * Get the custom table's expected full prefixed name.
	 *
	 * @return string
	 */
	private static function get_prefixed_table_name(): string {
		global $wpdb;

		return $wpdb->get_blog_prefix() . static::get_base_table_name();
	}

	/**
	 * Does the custom table exist?
	 *
	 * @return bool
	 */
	private static function table_exists(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::get_prefixed_table_name() ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Getter for record's last_upgrade_routine value.
	 *
	 * @return int
	 */
	public function last_upgrade_routine(): int {
		if ( empty( $this->last_upgrade_routine ) ) {
			global $as3cf;

			$this->last_upgrade_routine = $as3cf->get_setting( 'post_meta_version', 0 );
		}

		return $this->last_upgrade_routine;
	}

	/**
	 * Setter for record's last_upgrade_routine value.
	 *
	 * @param int $last_upgrade_routine
	 */
	public function set_last_upgrade_routine( int $last_upgrade_routine ): void {
		$this->last_upgrade_routine = $last_upgrade_routine;
	}

	/**
	 * Enable retrieving from the object cache.
	 *
	 * When disabled, the get_from_object_cache function will simply return false.
	 */
	public static function enable_cache(): void {
		self::$cache_enabled = true;
	}

	/**
	 * Disable retrieving from the object cache.
	 *
	 * When disabled, the get_from_object_cache function will simply return false.
	 */
	public static function disable_cache(): void {
		self::$cache_enabled = false;
	}

	/**
	 * Returns the string used to group all keys in the object cache by.
	 *
	 * @return string
	 */
	protected static function get_object_cache_group(): string {
		static $group;

		if ( empty( $group ) ) {
			/** @var Amazon_S3_And_CloudFront $as3cf */
			global $as3cf;

			/**
			 * Filters the object cache group name.
			 *
			 * @param string $group Defaults to 'as3cf'
			 */
			$group = trim( '' . apply_filters( 'as3cf_object_cache_group', $as3cf->get_plugin_prefix() ) );
		}

		return $group;
	}

	/**
	 * Get base string for all of current blog's object cache keys.
	 *
	 * This is basically just the full custom table name as that's unique enough.
	 *
	 * @return string
	 */
	protected static function get_object_cache_base_key(): string {
		return static::get_table_name();
	}

	/**
	 * Get full object cache key.
	 *
	 * @param string $base_key
	 * @param string $key
	 * @param string $field
	 *
	 * @return string
	 */
	protected static function get_object_cache_full_key( string $base_key, string $key, string $field ): string {
		return sanitize_text_field( $base_key . '-' . $key . '-' . $field );
	}

	/**
	 * Get all derived cache keys for given object.
	 *
	 * @param object $object
	 *
	 * @return array
	 */
	protected static function get_cache_keys_for_object( object $object ): array {
		$keys = array();

		if ( empty( static::get_cache_keys() ) ) {
			return $keys;
		}

		$base_key = static::get_object_cache_base_key();

		// For each key, extend with values from fields separated by ':'.
		foreach ( static::get_cache_keys() as $key => $fields ) {
			$values = array();
			foreach ( $fields as $field ) {
				$value = $object->{$field}();

				if ( empty( $value ) ) {
					continue;
				}

				$values[] = $value;
			}

			if ( empty( $values ) ) {
				continue;
			}

			$full_key = static::get_object_cache_full_key( $base_key, $key, join( ':', $values ) );

			if ( in_array( $full_key, $keys ) ) {
				continue;
			}

			$keys[] = $full_key;
		}

		return $keys;
	}

	/**
	 * Add the given object to the object cache.
	 *
	 * @param object $object
	 */
	protected static function add_to_object_cache( object $object ): void {
		$keys = static::get_cache_keys_for_object( $object );

		if ( empty( $keys ) ) {
			return;
		}

		$items = array_fill_keys( $keys, $object );
		$group = static::get_object_cache_group();

		// TODO: Switch to wp_cache_set_multiple( $items, $group ) when WP 6.0+ is min required.
		foreach ( $items as $key => $value ) {
			wp_cache_set( $key, $value, $group );
		}
	}

	/**
	 * Delete the given object from the object cache.
	 *
	 * @param object $object
	 */
	protected static function remove_from_object_cache( object $object ): void {
		$keys = static::get_cache_keys_for_object( $object );

		if ( empty( $keys ) ) {
			return;
		}

		$group = static::get_object_cache_group();

		// TODO: Switch to wp_cache_delete_multiple( $keys, $group ) when WP 6.0+ is min required.
		foreach ( $keys as $key ) {
			wp_cache_delete( $key, $group );
		}
	}

	/**
	 * Delete all the given objects from the object cache.
	 *
	 * @param array $objects
	 *
	 * @return void
	 */
	protected static function remove_multiple_from_object_cache( array $objects ): void {
		if ( empty( $objects ) ) {
			return;
		}

		$keys = array();

		foreach ( $objects as $object ) {
			$keys = array_merge( $keys, static::get_cache_keys_for_object( $object ) );
		}

		if ( empty( $keys ) ) {
			return;
		}

		$group = static::get_object_cache_group();

		// TODO: Switch to wp_cache_delete_multiple( $keys, $group ) when WP 6.0+ is min required.
		foreach ( $keys as $key ) {
			wp_cache_delete( $key, $group );
		}
	}

	/**
	 * Try and get object from object cache by known key and value.
	 *
	 * @param string $key   The base of the key that makes up the lookup, e.g. field for given value.
	 * @param mixed  $value Will be coerced to string for lookup.
	 *
	 * @return object|bool
	 */
	protected static function get_from_object_cache( string $key, mixed $value ): object|bool {
		if ( ! self::$cache_enabled || ! array_key_exists( $key, static::get_cache_keys() ) ) {
			return false;
		}

		if ( is_array( $value ) ) {
			$value = join( ':', $value );
		}

		$base_key = static::get_object_cache_base_key();
		$full_key = static::get_object_cache_full_key( $base_key, $key, $value );
		$group    = static::get_object_cache_group();
		$found    = false;
		$result   = wp_cache_get( $full_key, $group, false, $found );

		if ( $found ) {
			return $result;
		}

		return false;
	}

	/**
	 * Get an array of key -> value pairs where each value should be an array of fields
	 * that are in themselves considered unique to the object.
	 *
	 * @return array Keys with array of fields that can be used for cache lookups.
	 */
	abstract protected static function get_cache_keys(): array;

	/**
	 * Get the custom table's base (unprefixed) name.
	 *
	 * @return string
	 */
	abstract public static function get_base_table_name(): string;

	/**
	 * Create the table needed by this class with given name (for current site).
	 *
	 * @param string $table_name           Full table name to install.
	 * @param string $plugin_version       Current plugin version.
	 * @param int    $last_upgrade_routine Last completed upgrade routine.
	 */
	abstract protected static function install_table(
		string $table_name,
		string $plugin_version,
		int $last_upgrade_routine
	): void;
}
