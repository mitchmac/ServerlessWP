<?php

namespace DeliciousBrains\WP_Offload_Media\Items;

use Amazon_S3_And_CloudFront;
use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Custom_Table_Trait;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for working with the as3cf_files custom table.
 */
class File {
	use Custom_Table_Trait;

	/**
	 * Custom table name.
	 *
	 * @var string
	 */
	protected static string $base_table_name = 'as3cf_files';

	private ?int $id = null;

	private string $path;

	private bool $is_private;

	private string $private_prefix;

	private string $source_path;
	private string $size;

	/**
	 * The Item associated with this File.
	 *
	 * @var Item
	 */
	private Item $as3cf_item;

	/**
	 * File constructor.
	 *
	 * Not for public use, use a create_* function instead.
	 *
	 * @param Item     $as3cf_item     Associated Item.
	 * @param string   $path           Path in bucket for object, a.k.a. key.
	 * @param bool     $is_private     Is the object private in the bucket?
	 * @param string   $private_prefix Private prefix that might have been prepended to the path.
	 * @param string   $source_path    Relative source path on the server.
	 * @param string   $size           Size used to identify file within Item.
	 * @param int|null $id             Optional ID for the File, if already saved.
	 */
	protected function __construct(
		Item $as3cf_item,
		string $path,
		bool $is_private,
		string $private_prefix,
		string $source_path,
		string $size,
		?int $id = null
	) {
		$this->as3cf_item = $as3cf_item;

		$this->path           = $path;
		$this->is_private     = $is_private;
		$this->private_prefix = $private_prefix;
		$this->source_path    = $source_path;
		$this->size           = $size;

		if ( ! empty( $id ) ) {
			$this->id = $id;
		}
	}

	/**
	 * Get an array of key -> value pairs where each value should be an array of fields
	 * that are considered unique to the object when combined.
	 *
	 * @return array Keys with array of fields that can be used for cache lookups.
	 */
	protected static function get_cache_keys(): array {
		return array(
			'id'               => array( 'id' ),
			'path_size'        => array( 'path', 'size' ),
			'source_path_size' => array( 'source_path', 'size' ),
			'item_size'        => array( 'item_id', 'size' ),
			'bucket_path'      => array( 'bucket', 'path' ),
		);
	}

	/**
	 * (Re)initialize the static cache used for speeding up queries.
	 */
	public static function init_cache(): void {
		self::$checked_table_exists = array();
	}

	/**
	 * Get the custom table's base (unprefixed) name.
	 *
	 * @return string
	 */
	public static function get_base_table_name(): string {
		return static::$base_table_name;
	}

	/**
	 * Create the table needed by this class with given name (for current site).
	 *
	 * @param string $table_name           Full table name to install.
	 * @param string $plugin_version       Current plugin version.
	 * @param int    $last_upgrade_routine Last completed upgrade routine.
	 */
	protected static function install_table(
		string $table_name,
		string $plugin_version,
		int $last_upgrade_routine
	): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$wpdb->hide_errors();

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "
			CREATE TABLE $table_name (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				item_id BIGINT(20) UNSIGNED NOT NULL,
				path VARCHAR(1024) NOT NULL,
				is_private BOOLEAN NOT NULL DEFAULT 0,
				private_prefix VARCHAR(1024) NOT NULL DEFAULT '',
				source_path VARCHAR(1024) NOT NULL,
				size VARCHAR(255) NOT NULL,
				last_upgrade_routine INT NOT NULL DEFAULT $last_upgrade_routine,
				PRIMARY KEY (id),
				UNIQUE KEY uidx_item_id (item_id, id),
				UNIQUE KEY uidx_path (path(190), size(190), item_id),
				UNIQUE KEY uidx_source_path (source_path(190), size(190), item_id),
				UNIQUE KEY uidx_size (size(190), item_id),
				UNIQUE KEY uidx_last_upgrade_routine (last_upgrade_routine, id)
			) $charset_collate;
			";
		dbDelta( $sql );
	}

	/**
	 * Get file's data as an associative array, optionally with id if available.
	 *
	 * @param bool $include_id Default false.
	 *
	 * @return array
	 */
	public function key_values( bool $include_id = false ): array {
		$key_values = array(
			'item_id'              => $this->item_id(),
			'path'                 => $this->path(),
			'is_private'           => $this->is_private(),
			'private_prefix'       => $this->private_prefix(),
			'source_path'          => $this->source_path(),
			'size'                 => $this->size(),
			'last_upgrade_routine' => $this->last_upgrade_routine(),
		);

		if ( $include_id && ! empty( $this->id() ) ) {
			$key_values['id'] = $this->id();
		}

		ksort( $key_values );

		return $key_values;
	}

	/**
	 * Get file's column formats as an associative array, optionally with id if available.
	 *
	 * @param bool $include_id Default false.
	 *
	 * @return array
	 */
	private function key_formats( bool $include_id = false ): array {
		$key_values = array(
			'item_id'              => '%d',
			'path'                 => '%s',
			'is_private'           => '%d',
			'private_prefix'       => '%s',
			'source_path'          => '%s',
			'size'                 => '%s',
			'last_upgrade_routine' => '%d',
		);

		if ( $include_id && ! empty( $this->id() ) ) {
			$key_values['id'] = '%d';
		}

		ksort( $key_values );

		return $key_values;
	}

	/**
	 * All the file's column formats in an indexed array, optionally with id if available.
	 *
	 * @param bool $include_id Default false.
	 *
	 * @return array
	 */
	private function formats( bool $include_id = false ): array {
		return array_values( $this->key_formats( $include_id ) );
	}

	/**
	 * Save the file's current data.
	 *
	 * @return int|WP_Error
	 */
	public function save(): int|WP_Error {
		global $wpdb;

		if ( empty( $this->id() ) ) {
			// phpcs:ignore WordPress.DB -- safe query, must not be cached
			$result = $wpdb->insert( static::get_table_name(), $this->key_values(), $this->formats() );

			if ( $result ) {
				$this->id = $wpdb->insert_id;
			}
		} else {
			// Make sure object cache does not have stale objects.
			$old_obj = static::get_from_object_cache( 'id', $this->id() );

			if ( is_object( $old_obj ) ) {
				static::remove_from_object_cache( $old_obj );
				unset( $old_obj );
			}

			// phpcs:ignore WordPress.DB -- safe query, must not be cached
			$result = $wpdb->update(
				static::get_table_name(),
				$this->key_values(),
				array( 'id' => $this->id() ),
				$this->formats(),
				array( '%d' )
			);
		}

		if ( false !== $result ) {
			// Now that the item has an ID it should be (re)cached.
			static::add_to_object_cache( $this );
		} else {
			return new WP_Error( 'as3cf_file_record_save', 'Error saving file record:- ' . $wpdb->last_error );
		}

		return $this->id();
	}

	/**
	 * Delete the current file.
	 *
	 * @return bool|WP_Error
	 */
	public function delete(): bool|WP_Error {
		global $wpdb;

		static::remove_from_object_cache( $this );

		if ( empty( $this->id() ) ) {
			return new WP_Error(
				'as3cf_file_record_delete_missing_id',
				'Error trying to delete file record with no id.'
			);
		} else {
			// phpcs:ignore WordPress.DB -- safe query, must not be cached
			$result = $wpdb->delete( static::get_table_name(), array( 'id' => $this->id() ), array( '%d' ) );
		}

		if ( ! $result ) {
			return new WP_Error(
				'as3cf_file_record_delete_error',
				'Error deleting file record:- ' . $wpdb->last_error
			);
		}

		return true;
	}

	/**
	 * Delete all File records associated with the given Item.
	 *
	 * @param Item $as3cf_item Associated Item.
	 *
	 * @return true|WP_Error
	 */
	public static function delete_all_for_item( Item $as3cf_item ): bool|WP_Error {
		global $wpdb;

		if ( empty( $as3cf_item->id() ) ) {
			return new WP_Error(
				'as3cf_delete_all_file_records_for_item_missing_item_id',
				'Supplied Item does not have an ID.'
			);
		}

		// Evacuate all the Files from object cache.
		static::remove_multiple_from_object_cache( $as3cf_item->files() );

		// phpcs:ignore WordPress.DB -- safe query, must not be cached
		$result = $wpdb->delete( static::get_table_name(), array( 'item_id' => $as3cf_item->id() ), array( '%d' ) );

		if ( ! $result ) {
			return new WP_Error(
				'as3cf_delete_all_file_records_for_item_error',
				'Error deleting file records for item ' . $as3cf_item->id() . ':- ' . $wpdb->last_error
			);
		}

		return true;
	}

	/**
	 * Creates an instance based on object from database.
	 *
	 * @param object    $object
	 * @param bool      $add_to_object_cache Should this object be added to the object cache too?
	 * @param Item|null $as3cf_item          Optional, instance of Item to use.
	 *
	 * @return File|bool
	 */
	protected static function create(
		object $object,
		bool $add_to_object_cache = false,
		?Item $as3cf_item = null
	): File|bool {
		if ( empty( $as3cf_item ) ) {
			$as3cf_item = Item::get_by_id( $object->item_id );
		}

		if ( empty( $as3cf_item ) || ! is_a( $as3cf_item, Item::class ) ) {
			AS3CF_Error::log(
				sprintf(
					'File record with ID %1$d has invalid item_id of %2$d.',
					$object->id,
					$object->item_id
				)
			);

			return false;
		}

		$as3cf_file = new static(
			$as3cf_item,
			$object->path,
			$object->is_private,
			$object->private_prefix,
			$object->source_path,
			$object->size,
			$object->id,
		);

		// We don't want last_upgrade_routine to be in the constructor.
		// It should only be set from DB (here), or explicitly during upgrades.
		$as3cf_file->set_last_upgrade_routine( $object->last_upgrade_routine );

		if ( $add_to_object_cache ) {
			self::add_to_object_cache( $as3cf_file );
		}

		return $as3cf_file;
	}

	/**
	 * Create an unsaved File for given Item and file details.
	 *
	 * @param Item   $as3cf_item     Associated Item.
	 * @param string $path           Path in bucket for object, a.k.a. key.
	 * @param bool   $is_private     Is the object private in the bucket?
	 * @param string $private_prefix Private prefix that might have been prepended to the path.
	 * @param string $source_path    Relative source path on the server.
	 * @param string $size           Size used to identify file within Item.
	 *
	 * @return File|bool
	 */
	public static function create_for_item(
		Item $as3cf_item,
		string $path,
		bool $is_private,
		string $private_prefix,
		string $source_path,
		string $size
	): File|bool {
		return new static( $as3cf_item, $path, $is_private, $private_prefix, $source_path, $size );
	}

	/**
	 * Clone File for given instance of Item.
	 *
	 * Useful when cloning Item to avoid circular ref while updating Item ref.
	 *
	 * @param Item $as3cf_item Associated item.
	 *
	 * @return File|bool
	 */
	public function clone_for_item( Item $as3cf_item ): File|bool {
		return new static(
			$as3cf_item,
			$this->path,
			$this->is_private,
			$this->private_prefix,
			$this->source_path,
			$this->size,
			$this->id
		);
	}

	/**
	 * Get an instance by its ID.
	 *
	 * @param integer $id
	 *
	 * @return bool|File
	 */
	public static function get_by_id( int $id ): bool|File {
		global $wpdb;

		if ( empty( $id ) ) {
			return false;
		}

		/** @var File|bool $as3cf_file */
		$as3cf_file = static::get_from_object_cache( 'id', $id );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file;
		}

		$sql = "SELECT * FROM " . static::get_table_name() . " WHERE id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $id );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$object = $wpdb->get_row( $sql );

		if ( empty( $object ) ) {
			return false;
		}

		return static::create( $object, true );
	}

	/**
	 * Get all instances by their item ID.
	 *
	 * @param integer   $item_id             Item's ID to get Files for.
	 * @param bool      $add_to_object_cache Optional, default true.
	 * @param Item|null $as3cf_item          Optional, instance of Item to use.
	 *
	 * @return array<string,File> Files for Item ID, keyed by size.
	 */
	public static function get_by_item_id(
		int $item_id,
		bool $add_to_object_cache = true,
		?Item $as3cf_item = null
	): array {
		global $wpdb;

		$as3cf_files = array();

		if ( empty( $item_id ) ) {
			return $as3cf_files;
		}

		$sql = "SELECT * FROM " . static::get_table_name() . " WHERE item_id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $item_id );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$results = $wpdb->get_results( $sql );

		// Nothing found, shortcut out.
		if ( 0 === count( $results ) ) {
			return $as3cf_files;
		}

		foreach ( $results as $result ) {
			$as3cf_file = static::create( $result, $add_to_object_cache, $as3cf_item );

			if ( $as3cf_file ) {
				$as3cf_files[ $as3cf_file->size() ] = $as3cf_file;
			}
		}

		return $as3cf_files;
	}

	/**
	 * Get the first instance for the given source path.
	 *
	 * @param string $source_path Relative source path.
	 * @param string $size        Size used to identify file within Item.
	 *
	 * @return File|bool
	 */
	public static function get_by_source_path_and_size( string $source_path, string $size ): File|bool {
		global $wpdb;

		if ( empty( $source_path ) || empty( $size ) ) {
			return false;
		}

		/** @var File|bool $as3cf_file */
		$as3cf_file = static::get_from_object_cache( 'source_path', array( $source_path, $size ) );

		if ( ! empty( $as3cf_file ) && ! empty( $as3cf_file->id() ) ) {
			return $as3cf_file;
		}

		$sql = '
			SELECT * FROM ' . static::get_table_name() . '
			WHERE source_path = %s
			AND size = %s
			ORDER BY id LIMIT 1
		';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $source_path, $size );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$object = $wpdb->get_row( $sql );

		if ( empty( $object ) ) {
			return false;
		}

		return static::create( $object, true );
	}

	/**
	 * Does the given source path exist as an offloaded file?
	 *
	 * @param string $source_path
	 *
	 * @return bool
	 */
	public static function source_path_offloaded( string $source_path ): bool {
		global $wpdb;

		if ( empty( $source_path ) ) {
			return false;
		}

		$sql = '
			SELECT id FROM ' . static::get_table_name() . '
			WHERE source_path = %s
			LIMIT 1
		';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $source_path );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$id = $wpdb->get_var( $sql );

		return ! empty( $id );
	}

	/**
	 * Get all local source paths in given (relative) directory.
	 *
	 * @param string $dir Relative local dir to check against.
	 *
	 * @return array
	 */
	public static function get_source_paths_for_dir( string $dir ): array {
		global $wpdb;

		$sql = 'SELECT source_path FROM ' . static::get_table_name();

		if ( ! empty( $dir ) ) {
			$sql .= ' WHERE source_path LIKE %s';
			$dir = AS3CF_Utils::trailingslash_prefix( $dir ) . '%';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $dir );
		}

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$results = $wpdb->get_results( $sql, OBJECT_K );

		if ( ! empty( $results ) && is_array( $results ) ) {
			return array_keys( $results );
		}

		return array();
	}

	/**
	 * Get the first instance for the given bucket and path.
	 *
	 * If multiple items have files with the same path, the file for the item
	 * with the lowest item ID will be returned, as it is assumed to be the
	 * original with latter item IDs treated as duplicates.
	 *
	 * @param string $bucket
	 * @param string $path
	 *
	 * @return File|bool
	 */
	public static function get_by_bucket_and_path( string $bucket, string $path ): File|bool {
		global $wpdb;

		if ( empty( $bucket ) || empty( $path ) ) {
			return false;
		}

		/** @var File|bool $as3cf_file */
		$as3cf_file = static::get_from_object_cache( 'bucket_path', array( $bucket, $path ) );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file;
		}

		$sql = '
			SELECT f.* FROM ' . static::get_table_name() . ' AS f
			INNER JOIN ' . Item::get_table_name() . ' AS i ON f.item_id = i.id
			WHERE f.path = %s
			AND i.bucket = %s
			ORDER BY i.source_id, f.id LIMIT 1
		';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $path, $bucket );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$object = $wpdb->get_row( $sql );

		if ( empty( $object ) ) {
			return false;
		}

		return static::create( $object, true );
	}

	/**
	 * Get instance for given remote URL.
	 *
	 * @param string $url
	 *
	 * @return File|bool
	 */
	public static function get_by_remote_url( string $url ): File|bool {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $wpdb, $as3cf;

		if ( ! AS3CF_Utils::usable_url( $url ) ) {
			return false;
		}

		$sql_bits = array();
		$sql_args = array();

		$parts = AS3CF_Utils::parse_url( $url );
		$path  = AS3CF_Utils::decode_filename_in_path( ltrim( $parts['path'], '/' ) );

		$sql_bits[] = '
			SELECT f.*, "A" AS match_order FROM ' . static::get_table_name() . ' AS f
			WHERE f.path = %s
		';

		$sql_args[] = $path;

		// Remove the first directory to cater for bucket in path domain settings.
		if ( str_contains( $path, '/' ) ) {
			$path_parts          = explode( '/', $path );
			$maybe_bucket        = array_shift( $path_parts );
			$path_without_bucket = implode( '/', $path_parts );

			$sql_bits[] = '
				SELECT f.*, "B" AS match_order FROM ' . static::get_table_name() . ' AS f
				INNER JOIN ' . Item::get_table_name() . ' AS i ON f.item_id = i.id
				WHERE f.path = %s
				AND i.bucket = %s
			';

			$sql_args[] = $path_without_bucket;
			$sql_args[] = $maybe_bucket;
		}

		// It's possible that the URL has a path with CDN path offset removed.
		// If the URL path doesn't start with the current path prefix setting,
		// try and guess what the offset might be, and re-introduce the remainder
		// of the path prefix setting for possible match on bucket path.
		// We do this to avoid needing to use a LIKE match that can't use an index.
		// TODO: In the future we should enable specifying the CDN path offset for
		// TODO: each delivery provider, and just loop over them to create alternate
		// TODO: matches, with providers potentially filtered down by URL's domain.
		$object_prefix = AS3CF_Utils::trailingslash_prefix( $as3cf->get_object_prefix() );

		if ( ! str_starts_with( $path, $object_prefix ) ) {
			$prefix_parts       = explode( '/', $object_prefix );
			$prefix_parts_count = count( $prefix_parts );
			$prefix_start_parts = array();

			// Pop off and keep beginning segments of object prefix until remainder
			// matches, then prepend popped-off parts to match on.
			while ( 0 < $prefix_parts_count ) {
				$prefix_start_parts[] = array_shift( $prefix_parts );
				$prefix_parts_count   = count( $prefix_parts );

				if ( str_starts_with( $path, implode( '/', $prefix_parts ) ) ) {
					break;
				}
			}

			// As it's possible that the entire path prefix is the CDN offset path,
			// we don't care whether the prefix start parts are some or all of the
			// path prefix segments.
			$sql_bits[] = '
				SELECT f.*, "C" AS match_order FROM ' . static::get_table_name() . ' AS f
				WHERE f.path = %s
			';

			$sql_args[] = trailingslashit( implode( '/', $prefix_start_parts ) ) . $path;
		}

		// We're ordering by confidence in match method, then earliest Item ID,
		// and finally with File ID for reproducability.
		$order_by = ' ORDER BY match_order, item_id, id LIMIT 1';

		$sql = implode( ' UNION ', $sql_bits ) . $order_by;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $sql_args );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$object = $wpdb->get_row( $sql );

		if ( empty( $object ) ) {
			return false;
		}

		return static::create( $object, true );
	}

	/**
	 * Getter for file's id value.
	 *
	 * @return int|null
	 */
	public function id(): int|null {
		return $this->id;
	}

	/**
	 * Getter for file's item_id value.
	 *
	 * @return int
	 */
	public function item_id(): int {
		return $this->item()->id();
	}

	/**
	 * Getter for file's path value.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Setter for file's path value.
	 *
	 * @param string $path
	 */
	public function set_path( string $path ): void {
		$this->path = $path;
	}

	/**
	 * Getter for file's public path value, even if currently private.
	 *
	 * @return string
	 */
	public function public_path(): string {
		$public_path    = $this->path();
		$private_prefix = $this->private_prefix();

		if (
			$this->is_private() &&
			! empty( $private_prefix ) &&
			str_starts_with( $public_path, $private_prefix )
		) {
			$public_path = substr( $public_path, strlen( $private_prefix ) );
		}

		return $public_path;
	}

	/**
	 * Update public path prefix.
	 *
	 * @param string $new_prefix
	 */
	public function update_public_path_prefix( string $new_prefix ): void {
		$new_prefix = AS3CF_Utils::trailingslash_prefix( $new_prefix );

		$new_path = $new_prefix . wp_basename( $this->path() );

		if ( $this->is_private() && ! empty( $this->private_prefix() ) ) {
			$new_path = AS3CF_Utils::trailingslash_prefix( $this->private_prefix() ) . $new_path;
		}

		$this->set_path( $new_path );
	}

	/**
	 * Getter for file's is_private value.
	 *
	 * @return bool
	 */
	public function is_private(): bool {
		return $this->is_private;
	}

	/**
	 * Setter for file's is_private value.
	 *
	 * @param bool $private
	 */
	public function set_is_private( bool $private ): void {
		$public_path = $this->public_path();

		$this->is_private = $private;

		if ( $this->is_private() ) {
			$this->set_path( $this->private_prefix() . $public_path );
		} else {
			$this->set_path( $public_path );
		}
	}

	/**
	 * Getter for file's private_prefix value.
	 *
	 * @return string
	 */
	public function private_prefix(): string {
		return empty( $this->private_prefix ) ? '' : AS3CF_Utils::trailingslash_prefix( $this->private_prefix );
	}

	/**
	 * Setter for file's private_prefix value.
	 *
	 * @param string $private_prefix
	 */
	public function set_private_prefix( string $private_prefix ): void {
		$public_path = $this->public_path();

		$this->private_prefix = empty( $private_prefix ) ? '' : AS3CF_Utils::trailingslash_prefix( $private_prefix );

		if ( $this->is_private() ) {
			$this->set_path( $this->private_prefix() . $public_path );
		}
	}

	/**
	 * Getter for file's source_path value.
	 *
	 * @return string
	 */
	public function source_path(): string {
		return $this->source_path;
	}

	/**
	 * Setter for file's source_path value.
	 *
	 * @param string $source_path
	 */
	public function set_source_path( string $source_path ): void {
		$this->source_path = $source_path;
	}

	/**
	 * Getter for file's size value.
	 *
	 * @return string
	 */
	public function size(): string {
		return $this->size;
	}

	/**
	 * Setter for file's size value.
	 *
	 * @param string $size
	 */
	public function set_size( string $size ): void {
		$this->size = $size;
	}

	/**
	 * Getter for file's associated Item.
	 *
	 * @return Item
	 */
	public function item(): Item {
		return $this->as3cf_item;
	}

	/**
	 * Getter for file's source type.
	 *
	 * @return string
	 */
	public function source_type(): string {
		return $this->item()::source_type();
	}

	/**
	 * Getter for file's bucket.
	 *
	 * @return string
	 */
	public function bucket(): string {
		return $this->item()->bucket();
	}

	/**
	 * Getter for file's source_id.
	 *
	 * @return int
	 */
	public function source_id(): int {
		return $this->item()->source_id();
	}

	/**
	 * Getter for file's associated item source array.
	 *
	 * @return array
	 */
	public function item_source_array(): array {
		return $this->item()->get_item_source_array();
	}
}
