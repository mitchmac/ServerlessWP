<?php

namespace DeliciousBrains\WP_Offload_Media\Items;

use Amazon_S3_And_CloudFront;
use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Custom_Table_Trait;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\Storage_Provider;
use Exception;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Item {
	use Custom_Table_Trait;

	/**
	 * @deprecated 3.4.0 Please use method "get_base_table_name" instead.
	 */
	const ITEMS_TABLE = 'as3cf_items';
	const ORIGINATORS = array(
		'standard'      => 0,
		'metadata-tool' => 1,
	);

	const CAN_USE_OBJECT_VERSIONING = true;

	/**
	 * Custom table name.
	 *
	 * @var string
	 */
	protected static string $base_table_name = 'as3cf_items';

	protected static string $source_type_name  = 'Item';
	protected static string $source_type       = '';
	protected static string $source_table      = '';
	protected static string $source_fk         = '';
	protected static string $summary_type_name = '';
	protected static string $summary_type      = '';

	protected static bool $can_use_yearmonth = true;

	protected static array $item_counts      = array();
	protected static array $item_count_skips = array();

	private ?int $id = null;

	private string $provider;
	private string $region;
	private string $bucket;
	private string $path;
	private string $original_path;

	private bool $is_private;

	private int $source_id;

	private string $source_path;
	private string $original_source_path;

	private ?array $extra_info = null;

	private int $originator;

	private bool $is_verified;

	/**
	 * Files associated with Item.
	 *
	 * Keyed by File's size.
	 *
	 * @var array<string,File>
	 */
	private array $files = array();

	/**
	 * Item constructor.
	 *
	 * @param string|null $provider              Storage provider key name, e.g. "aws".
	 * @param string|null $region                Region for item's bucket.
	 * @param string|null $bucket                Bucket for item.
	 * @param string|null $path                  Key path for item (full sized if type has thumbnails etc).
	 * @param bool        $is_private            Is the object private in the bucket.
	 * @param int         $source_id             ID that source has.
	 * @param string      $source_path           Path that source uses, could be relative or absolute depending on source.
	 * @param string|null $original_filename     An optional filename with no path that was previously used for the item.
	 * @param array|null  $extra_info            An optional array of extra data specific to the source type.
	 * @param int|null    $id                    Optional Item record ID.
	 * @param int         $originator            Optional originator of record from ORIGINATORS const.
	 * @param bool        $is_verified           Optional flag as to whether Item's objects are known to exist.
	 * @param bool        $use_object_versioning Optional flag as to whether path prefix should use Object Versioning if type allows it.
	 */
	public function __construct(
		?string $provider,
		?string $region,
		?string $bucket,
		?string $path,
		bool $is_private,
		int $source_id,
		string $source_path,
		?string $original_filename = null,
		?array $extra_info = array(),
		?int $id = null,
		int $originator = 0,
		bool $is_verified = true,
		bool $use_object_versioning = self::CAN_USE_OBJECT_VERSIONING
	) {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		// Set source vars before checking for existing duplicate by source.
		$this->source_id = $source_id;

		if ( empty( $original_filename ) ) {
			$original_source_path = $source_path;
		} else {
			$original_source_path = str_replace( wp_basename( $source_path ), $original_filename, $source_path );
		}

		// Set offload data from previous duplicate if exact match by source path exists.
		if ( empty( $path ) ) {
			$prev_items = static::get_by_source_path(
				array( $source_path, $original_source_path ),
				$this->source_id(),
				true,
				true
			);

			if (
				! is_wp_error( $prev_items ) &&
				! empty( $prev_items[0] ) &&
				is_a( $prev_items[0], get_class( $this ) )
			) {
				/** @var Item $prev_item */
				$prev_item  = $prev_items[0];
				$provider   = $prev_item->provider();
				$region     = $prev_item->region();
				$bucket     = $prev_item->bucket();
				$path       = $prev_item->path();
				$is_private = $prev_item->is_private();
				$extra_info = $prev_item->extra_info();
			}
		}

		// Not a duplicate, create a new path to offload to.
		if ( empty( $path ) ) {
			$prefix = $this->get_new_item_prefix( $use_object_versioning );
			$path   = $prefix . wp_basename( $source_path );
		}

		if ( empty( $provider ) ) {
			$provider = $as3cf->get_storage_provider()->get_provider_key_name();
		}

		if ( empty( $region ) ) {
			$region = $as3cf->get_setting( 'region' );
			if ( is_wp_error( $region ) ) {
				$region = '';
			}
		}

		if ( empty( $bucket ) ) {
			$bucket = $as3cf->get_setting( 'bucket' );
		}

		$this->set_provider( $provider );
		$this->set_region( $region );
		$this->set_bucket( $bucket );

		// Basics in place to start setting File related data.
		if ( ! empty( $id ) ) {
			$this->id = $id;

			$this->files = File::get_by_item_id( $this->id(), true, $this );
		}

		$this->set_source_path( $source_path );
		$this->set_original_source_path( $original_source_path );

		$this->set_path( $path );

		if ( empty( $original_filename ) ) {
			$this->set_original_path( $path );
		} else {
			$this->set_original_path( str_replace( wp_basename( $path ), $original_filename, $path ) );
		}

		if ( ! is_array( $extra_info ) ) {
			$extra_info = array();
		}

		if ( ! isset( $extra_info['private_prefix'] ) ) {
			$extra_info['private_prefix'] = '';
			if ( $as3cf->private_prefix_enabled() ) {
				$extra_info['private_prefix'] = AS3CF_Utils::trailingslash_prefix(
					$as3cf->get_setting( 'signed-urls-object-prefix' )
				);
			}
		}

		$this->set_private_prefix( $extra_info['private_prefix'] );

		if ( ! empty( $extra_info['objects'] ) && is_array( $extra_info['objects'] ) ) {
			$this->set_objects( $extra_info['objects'] );
		}

		$this->set_is_private( $is_private );

		$this->set_originator( $originator );
		$this->set_is_verified( $is_verified );
	}

	/**
	 * On clone, make sure object refs are also cloned.
	 *
	 * @return void
	 */
	function __clone() {
		foreach ( $this->files as $idx => $file ) {
			$this->files[ $idx ] = $file->clone_for_item( $this );
		}
	}

	/**
	 * Returns the standard object key for an item's primary object.
	 *
	 * @return string
	 */
	public static function primary_object_key(): string {
		return '__as3cf_primary';
	}

	/**
	 * Returns the standard object key for an item's original object.
	 *
	 * The original object is the one uploaded and then potentially scaled
	 * down for the primary object.
	 *
	 * @return string
	 *
	 * TODO: Should probably use unique __as3cf_original_image name and migrate current extra_info entries.
	 */
	public static function original_image_object_key(): string {
		return 'original_image';
	}

	/**
	 * Returns the standard object key for an item's source image object.
	 *
	 * The source image object is the one uploaded and then converted to another format.
	 *
	 * @return string
	 */
	public static function source_image_object_key(): string {
		return '__as3cf_source_image';
	}

	/**
	 * Returns the standard object key for an item's animated video object.
	 *
	 * The animated video object is the video file created from the original gif.
	 *
	 * @return string
	 */
	public static function animated_video_object_key(): string {
		return '__as3cf_animated_video';
	}

	/**
	 * Returns the standard object key for an item's animated video poster object.
	 *
	 * The animated video poster object is the poster image file created for the video file created from the original gif.
	 *
	 * @return string
	 */
	public static function animated_video_poster_object_key(): string {
		return '__as3cf_animated_video_poster';
	}

	/**
	 * Get an array of key -> value pairs where each value should be an array of fields
	 * that are considered unique to the object when combined.
	 *
	 * @return array Keys with array of fields that can be used for cache lookups.
	 */
	protected static function get_cache_keys(): array {
		return array(
			'id'          => array( 'id' ),
			'source_id'   => array( 'source_type', 'source_id' ),
			'path'        => array( 'path' ),
			'source_path' => array( 'source_type', 'source_path' ),
			'bucket_path' => array( 'bucket', 'path' ),
		);
	}

	/**
	 * (Re)initialize the static cache used for speeding up queries.
	 */
	public static function init_cache(): void {
		self::$checked_table_exists = array();

		static::$item_counts      = array();
		static::$item_count_skips = array();

		// Make sure the dependent File cache is also initialized.
		File::init_cache();
	}

	/**
	 * The full items table name for current blog.
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use method "get_table_name" instead.
	 */
	public static function items_table(): string {
		return static::get_table_name();
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
				provider VARCHAR(18) NOT NULL,
				region VARCHAR(255) NOT NULL,
				bucket VARCHAR(255) NOT NULL,
				path VARCHAR(1024) NOT NULL COMMENT 'Deprecated',
				original_path VARCHAR(1024) NOT NULL COMMENT 'Deprecated',
				is_private BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Deprecated',
				source_type VARCHAR(18) NOT NULL,
				source_id BIGINT(20) UNSIGNED NOT NULL,
				source_path VARCHAR(1024) NOT NULL COMMENT 'Deprecated',
				original_source_path VARCHAR(1024) NOT NULL COMMENT 'Deprecated',
				extra_info LONGTEXT COMMENT 'Deprecated',
				originator TINYINT UNSIGNED NOT NULL DEFAULT 0,
				is_verified BOOLEAN NOT NULL DEFAULT 1,
				last_upgrade_routine INT NOT NULL DEFAULT $last_upgrade_routine,
				PRIMARY KEY (id),
				UNIQUE KEY uidx_path (path(190), id),
				UNIQUE KEY uidx_original_path (original_path(190), id),
				UNIQUE KEY uidx_source_path (source_path(190), id),
				UNIQUE KEY uidx_original_source_path (original_source_path(190), id),
				UNIQUE KEY uidx_source (source_type, source_id),
				UNIQUE KEY uidx_provider_bucket (provider, bucket(190), id),
				UNIQUE KEY uidx_is_verified_originator (is_verified, originator, id),
				UNIQUE KEY uidx_last_upgrade_routine (last_upgrade_routine, id)
			) $charset_collate;
			";

		dbDelta( $sql );
	}

	/**
	 * Get item's data as an array, optionally with id if available.
	 *
	 * @param bool $include_id Default false.
	 *
	 * @return array
	 */
	public function key_values( bool $include_id = false ): array {
		$key_values = array(
			'provider'             => $this->provider(),
			'region'               => $this->region(),
			'bucket'               => $this->bucket(),
			'path'                 => $this->path(),
			'original_path'        => $this->original_path(),
			'is_private'           => $this->is_private(),
			'source_type'          => static::$source_type,
			'source_id'            => $this->source_id(),
			'source_path'          => $this->source_path(),
			'original_source_path' => $this->original_source_path(),
			'extra_info'           => serialize( $this->extra_info() ),
			'originator'           => $this->originator(),
			'is_verified'          => $this->is_verified(),
			'last_upgrade_routine' => $this->last_upgrade_routine(),
		);

		if ( $include_id && ! empty( $this->id() ) ) {
			$key_values['id'] = $this->id();
		}

		ksort( $key_values );

		return $key_values;
	}

	/**
	 * Get item's column formats as an associative array, optionally with id if available.
	 *
	 * @param bool $include_id Default false.
	 *
	 * @return array
	 */
	private function key_formats( bool $include_id = false ): array {
		$key_values = array(
			'provider'             => '%s',
			'region'               => '%s',
			'bucket'               => '%s',
			'path'                 => '%s',
			'original_path'        => '%s',
			'is_private'           => '%d',
			'source_type'          => '%s',
			'source_id'            => '%d',
			'source_path'          => '%s',
			'original_source_path' => '%s',
			'extra_info'           => '%s',
			'originator'           => '%d',
			'is_verified'          => '%d',
			'last_upgrade_routine' => '%d',
		);

		if ( $include_id && ! empty( $this->id() ) ) {
			$key_values['id'] = '%d';
		}

		ksort( $key_values );

		return $key_values;
	}

	/**
	 * All the item's column formats in an indexed array, optionally with id if available.
	 *
	 * @param bool $include_id Default false.
	 *
	 * @return array
	 */
	private function formats( bool $include_id = false ): array {
		return array_values( $this->key_formats( $include_id ) );
	}

	/**
	 * Save the item's current data.
	 *
	 * @param bool $update_duplicates If updating, also update records for duplicated source, defaults to true.
	 *
	 * @return int|WP_Error
	 */
	public function save( bool $update_duplicates = true ): int|WP_Error {
		global $wpdb;

		$update = false;

		if ( empty( $this->id() ) ) {
			// phpcs:ignore WordPress.DB -- safe query, must not be cached
			$result = $wpdb->insert( static::get_table_name(), $this->key_values(), $this->formats() );

			if ( $result ) {
				$this->id = $wpdb->insert_id;
			}
		} else {
			$update = true;

			// Make sure object cache does not have stale items.
			$old_item = static::get_from_object_cache( 'id', $this->id() );

			if ( is_object( $old_item ) ) {
				static::remove_from_object_cache( $old_item );
				unset( $old_item );
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

		if ( false === $result ) {
			return new WP_Error( 'item_save', 'Error saving item:- ' . $wpdb->last_error );
		}

		// Get previously saved Files so they can be updated or deleted.
		$old_as3cf_files = array();
		if ( $update ) {
			$old_as3cf_files = File::get_by_item_id( $this->id(), false, $this );
		}

		// Reconcile current Files with previous, and save.
		foreach ( $this->files() as $size => $as3cf_file ) {
			if ( ! empty( $old_as3cf_files[ $size ] ) ) {
				unset( $old_as3cf_files[ $size ] );
			}

			// Make sure last_upgrade_routine matches Item's.
			$as3cf_file->set_last_upgrade_routine( $this->last_upgrade_routine() );

			$result = $as3cf_file->save();

			if ( is_wp_error( $result ) ) {
				break;
			}
		}

		// TODO: Start transaction above and rollback / commit here?
		if ( is_wp_error( $result ) ) {
			return $result;
		} else {
			// Now that the item has an ID and so do its Files, it should be (re)cached.
			static::add_to_object_cache( $this );
		}

		// Remove old Files that are now orphaned.
		// Ideally these records should have been removed before save is called,
		// maybe by remover tool, this is just a cleanup.
		foreach ( $old_as3cf_files as $old_as3cf_file ) {
			$old_as3cf_file->delete();
		}

		// If one or more duplicate exists that still has the same source paths, keep them in step.
		if ( $update && $update_duplicates ) {
			$duplicates = static::get_by_source_path(
				array( $this->source_path(), $this->original_source_path() ),
				$this->source_id()
			);

			if ( ! empty( $duplicates ) && ! is_wp_error( $duplicates ) ) {
				/** @var Item $duplicate */
				foreach ( $duplicates as $duplicate ) {
					if (
						! is_wp_error( $duplicate ) &&
						$duplicate->source_type() === $this->source_type() &&
						$duplicate->source_path() === $this->source_path() &&
						$duplicate->original_source_path() === $this->original_source_path()
					) {
						$duplicate->set_provider( $this->provider() );
						$duplicate->set_region( $this->region() );
						$duplicate->set_bucket( $this->bucket() );
						$duplicate->set_is_private( $this->is_private() );
						$duplicate->set_private_prefix( $this->private_prefix() );
						$duplicate->set_path( $this->path() );
						$duplicate->set_original_path( $this->original_path() );
						$duplicate->set_objects( $this->objects() );
						$duplicate->set_originator( $this->originator() );
						$duplicate->set_is_verified( $this->is_verified() );
						$duplicate->set_last_upgrade_routine( $this->last_upgrade_routine() );
						$duplicate->save( false );
					}
				}
			}
		}

		return $this->id();
	}

	/**
	 * Delete the current item.
	 *
	 * @return bool|WP_Error
	 */
	public function delete(): WP_Error|bool {
		global $wpdb;

		static::remove_from_object_cache( $this );

		if ( empty( $this->id() ) ) {
			return new WP_Error( 'item_delete', 'Error trying to delete item with no id.' );
		} else {
			// phpcs:ignore WordPress.DB -- safe query, must not be cached
			$result = $wpdb->delete( static::get_table_name(), array( 'id' => $this->id() ), array( '%d' ) );
		}

		if ( ! $result ) {
			return new WP_Error( 'item_delete', 'Error deleting item:- ' . $wpdb->last_error );
		}

		// Delete all the associated File records.
		$result = File::delete_all_for_item( $this );

		// TODO: Start transaction above and rollback / commit here?
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Creates an item based on object from database.
	 *
	 * @param object $object
	 * @param bool   $add_to_object_cache Should this object be added to the object cache too?
	 *
	 * @return Item
	 */
	protected static function create( object $object, bool $add_to_object_cache = false ): Item {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		$extra_info = array();

		if ( ! empty( $object->extra_info ) ) {
			$extra_info = AS3CF_Utils::maybe_unserialize( $object->extra_info );
			static::maybe_update_extra_info( $extra_info, $object->source_id, $object->is_private );
		}

		if ( ! empty( static::$source_type ) && static::$source_type !== $object->source_type ) {
			AS3CF_Error::log(
				sprintf(
					'Doing it wrong! Trying to create a %s class instance with data representing a %s',
					__CLASS__,
					$object->source_type
				)
			);
		}

		if ( empty( static::$source_type ) ) {
			/** @var Item $class */
			$class = $as3cf->get_source_type_class( $object->source_type );
		} else {
			/** @var Item $class */
			$class = $as3cf->get_source_type_class( static::$source_type );
		}

		$item = new $class(
			$object->provider,
			$object->region,
			$object->bucket,
			$object->path,
			$object->is_private,
			$object->source_id,
			$object->source_path,
			wp_basename( $object->original_source_path ),
			$extra_info,
			$object->id,
			$object->originator,
			$object->is_verified
		);

		// We don't want last_upgrade_routine to be in the constructor.
		// It should only be set from DB (here), or explicitly during upgrades.
		$item->set_last_upgrade_routine( $object->last_upgrade_routine );

		if ( $add_to_object_cache ) {
			$class::add_to_object_cache( $item );
		}

		return $item;
	}

	/**
	 * Get an item by its id.
	 *
	 * @param integer $id
	 *
	 * @return bool|Item
	 */
	public static function get_by_id( int $id ): Item|bool {
		global $wpdb;

		if ( empty( $id ) ) {
			return false;
		}

		/** @var Item|bool $item */
		$item = static::get_from_object_cache( 'id', $id );

		if ( ! empty( $item ) ) {
			return $item;
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
	 * Get an item by its source id.
	 *
	 * While source id isn't strictly unique, it is by source type, which is always used in queries based on called class.
	 *
	 * @param int $source_id
	 *
	 * @return Item|bool
	 */
	public static function get_by_source_id( int $source_id ): Item|bool {
		global $wpdb;

		if ( ! is_numeric( $source_id ) ) {
			return false;
		}

		$source_id = (int) $source_id;

		if ( $source_id < 0 ) {
			return false;
		}

		/** @var Item|bool $item */
		$item = static::get_from_object_cache( 'source_id', array( static::$source_type, $source_id ) );

		if ( ! empty( $item ) && ! empty( $item->id() ) ) {
			return $item;
		}

		$sql = "SELECT * FROM " . static::get_table_name() . " WHERE source_id = %d AND source_type = %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $source_id, static::$source_type );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$object = $wpdb->get_row( $sql );

		if ( empty( $object ) ) {
			return false;
		}

		return static::create( $object, true );
	}

	/**
	 * Getter for item's source type.
	 *
	 * @return string
	 */
	public static function source_type(): string {
		return static::$source_type;
	}

	/**
	 * Getter for item's source type name.
	 *
	 * @return string
	 */
	public static function source_type_name(): string {
		return static::$source_type_name;
	}

	/**
	 * Getter for item's summary type.
	 *
	 * @return string
	 */
	public static function summary_type(): string {
		return static::$summary_type;
	}

	/**
	 * Getter for item's summary type name.
	 *
	 * @return string
	 */
	public static function summary_type_name(): string {
		return static::$summary_type_name;
	}

	/**
	 * Is the item able to be included in a summary?
	 *
	 * @return bool
	 */
	public static function summary_enabled(): bool {
		return ! empty( static::summary_type() ) && ! empty( static::summary_type_name() );
	}

	/**
	 * Getter for item's id value.
	 *
	 * @return int|null
	 */
	public function id(): int|null {
		return $this->id;
	}

	/**
	 * Getter for item's provider value.
	 *
	 * @return string
	 */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * Setter for item's provider value.
	 *
	 * @param string $provider
	 */
	public function set_provider( string $provider ): void {
		$this->provider = $provider;
	}

	/**
	 * Getter for item's region value.
	 *
	 * @return string
	 */
	public function region(): string {
		return $this->region;
	}

	/**
	 * Setter for item's region value.
	 *
	 * @param string $region
	 */
	public function set_region( string $region ): void {
		$this->region = $region;
	}

	/**
	 * Getter for item's bucket value.
	 *
	 * @return string
	 */
	public function bucket(): string {
		return $this->bucket;
	}

	/**
	 * Setter for item's bucket value.
	 *
	 * @param string $bucket
	 */
	public function set_bucket( string $bucket ): void {
		$this->bucket = $bucket;
	}

	/**
	 * Getter for item's path value.
	 *
	 * The path is always the public representation,
	 * see provider_key() and provider_keys() for realised versions.
	 *
	 * @param string|null $object_key
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function path( ?string $object_key = null ): string {
		$as3cf_file = $this->file( $object_key );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->public_path();
		}

		return $this->legacy_path( $object_key );
	}

	/**
	 * Getter for item's legacy path value.
	 *
	 * The path is always the public representation,
	 * see provider_key() and provider_keys() for realised versions.
	 *
	 * @param string|null $object_key
	 *
	 * @return string
	 */
	private function legacy_path( ?string $object_key = null ): string {
		$path = $this->path;

		if ( ! empty( $object_key ) ) {
			$objects = $this->objects();
			if ( isset( $objects[ $object_key ]['source_file'] ) ) {
				$path = $this->prefix() . $objects[ $object_key ]['source_file'];
			}
		}

		return $path;
	}

	/**
	 * Setter for item's path value.
	 *
	 * @param string $path
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function set_path( string $path ): void {
		$as3cf_file = $this->file( self::primary_object_key() );

		if ( ! empty( $as3cf_file ) ) {
			$as3cf_file->set_path( $path );
		}

		$this->legacy_set_path( $path );
	}

	/**
	 * Setter for item's legacy path value.
	 *
	 * @param string $path
	 */
	private function legacy_set_path( string $path ): void {
		$this->path = $path;
	}

	/**
	 * Getter for item's original_path value.
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function original_path(): string {
		$as3cf_file = $this->file( self::original_image_object_key() );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->path();
		}

		return $this->legacy_original_path();
	}

	/**
	 * Getter for item's legacy original_path value.
	 *
	 * @return string
	 */
	private function legacy_original_path(): string {
		return $this->original_path;
	}

	/**
	 * Setter for item's original path value.
	 *
	 * @param string $path
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function set_original_path( string $path ): void {
		$as3cf_file = $this->file( self::original_image_object_key() );

		if ( ! empty( $as3cf_file ) ) {
			$as3cf_file->set_path( $path );
		}

		$this->legacy_set_original_path( $path );
	}

	/**
	 * Setter for item's original path value.
	 *
	 * @param string $path
	 */
	private function legacy_set_original_path( string $path ): void {
		$this->original_path = $path;
	}

	/**
	 * Getter for item's is_private value.
	 *
	 * @param string|null $object_key
	 *
	 * @return bool
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function is_private( ?string $object_key = null ): bool {
		$as3cf_file = $this->file( $object_key );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->is_private();
		}

		return $this->legacy_is_private( $object_key );
	}

	/**
	 * Getter for item's is_private value.
	 *
	 * @param string|null $object_key
	 *
	 * @return bool
	 */
	private function legacy_is_private( ?string $object_key = null ): bool {
		if ( ! empty( $object_key ) ) {
			$objects = $this->objects();
			if ( isset( $objects[ $object_key ]['is_private'] ) ) {
				return (bool) $objects[ $object_key ]['is_private'];
			}

			return false;
		}

		return $this->is_private;
	}

	/**
	 * Setter for item's is_private value.
	 *
	 * @param bool        $private
	 * @param string|null $object_key
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function set_is_private( bool $private, ?string $object_key = null ): void {
		$as3cf_file = $this->file( $object_key );

		if ( ! empty( $as3cf_file ) ) {
			$as3cf_file->set_is_private( $private );
		}

		$this->legacy_set_is_private( $private, $object_key );
	}

	/**
	 * Setter for item's legacy is_private value.
	 *
	 * @param bool        $private
	 * @param string|null $object_key
	 */
	private function legacy_set_is_private( bool $private, ?string $object_key = null ): void {
		if ( ! empty( $object_key ) ) {
			$objects = $this->objects();
			if ( isset( $objects[ $object_key ] ) ) {
				$objects[ $object_key ]['is_private'] = $private;
				$this->set_objects( $objects );
			}

			if ( $object_key === self::primary_object_key() ) {
				$this->is_private = $private;
			}

			return;
		}

		$this->legacy_set_is_private( $private, self::primary_object_key() );
	}

	/**
	 * Any private objects in this item?
	 *
	 * @return bool
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function has_private_objects(): bool {
		return $this->has_private_files() || $this->legacy_has_private_objects();
	}

	/**
	 * Any private legacy object data in this item?
	 *
	 * @return bool
	 */
	private function legacy_has_private_objects(): bool {
		foreach ( $this->objects() as $object ) {
			if ( $object['is_private'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Getter for the item prefix.
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function prefix(): string {
		$dirname = dirname( $this->path() );
		$dirname = $dirname === '.' ? '' : $dirname;

		return AS3CF_Utils::trailingslash_prefix( $dirname );
	}

	/**
	 * Get the private prefix for an item's primary file.
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function private_prefix(): string {
		$as3cf_file = $this->file();

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->private_prefix();
		}

		return $this->legacy_private_prefix();
	}

	/**
	 * Get the private prefix for item's private objects.
	 *
	 * @return string
	 */
	private function legacy_private_prefix(): string {
		$extra_info = $this->extra_info();

		if ( ! empty( $extra_info['private_prefix'] ) ) {
			return AS3CF_Utils::trailingslash_prefix( $extra_info['private_prefix'] );
		}

		return '';
	}

	/**
	 * Setter for the private prefix.
	 *
	 * @param string $new_private_prefix
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function set_private_prefix( string $new_private_prefix ): void {
		$this->set_private_prefix_for_all_files( $new_private_prefix );

		$this->legacy_set_private_prefix( $new_private_prefix );
	}

	/**
	 * Setter for the legacy private prefix.
	 *
	 * @param string $new_private_prefix
	 */
	private function legacy_set_private_prefix( string $new_private_prefix ): void {
		$extra_info                   = $this->extra_info();
		$extra_info['private_prefix'] = AS3CF_Utils::trailingslash_prefix( $new_private_prefix );
		$this->set_extra_info( $extra_info );
	}

	/**
	 * Get the full remote key for this item including private prefix when needed.
	 *
	 * @param string|null $object_key
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function provider_key( ?string $object_key = null ): string {
		$as3cf_file = $this->file( $object_key );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->path();
		}

		return $this->legacy_provider_key( $object_key );
	}

	/**
	 * Get the legacy full remote key for this item including private prefix when needed.
	 *
	 * @param string|null $object_key
	 *
	 * @return string
	 */
	private function legacy_provider_key( ?string $object_key = null ): string {
		$path = $this->path( $object_key );
		if ( $this->is_private( $object_key ) ) {
			$path = $this->private_prefix() . $path;
		}

		return $path;
	}

	/**
	 * Returns an associative array of provider keys by their size.
	 *
	 * NOTE: There may be duplicate keys if sizes reference same source file/object.
	 *
	 * @return array
	 *
	 * @deprecated 3.4.0 Please use paths function instead.
	 */
	public function provider_keys(): array {
		$keys = $this->paths();

		if ( ! empty( $keys ) ) {
			return $keys;
		}

		return $this->legacy_provider_keys();
	}

	/**
	 * Returns an associative array of provider keys by their object_key.
	 *
	 * NOTE: There may be duplicate keys if object_keys reference same source file/object.
	 *
	 * @return array
	 */
	private function legacy_provider_keys(): array {
		$keys = array();

		foreach ( array_keys( $this->objects() ) as $object_key ) {
			$keys[ $object_key ] = $this->provider_key( $object_key );
		}

		return $keys;
	}

	/**
	 * Creates a provider key for a given filename using the item's prefix settings.
	 *
	 * This function can be used to create ad-hoc custom provider keys.
	 * There are no tests to see if the filename is known to be associated with the item.
	 *
	 * @param string $filename   Just a filename without any path.
	 * @param bool   $is_private Should a private prefixed provider key be created if appropriate?
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function provider_key_for_filename( string $filename, bool $is_private ): string {
		$provider_key = '';

		if ( ! empty( $filename ) ) {
			$provider_key = $this->prefix() . wp_basename( trim( $filename ) );

			if ( $is_private ) {
				$provider_key = $this->private_prefix() . $provider_key;
			}
		}

		return $provider_key;
	}

	/**
	 * Getter for item's source_id value.
	 *
	 * @return integer
	 */
	public function source_id(): int {
		return $this->source_id;
	}

	/**
	 * Getter for item's source_path value.
	 *
	 * @param string|null $object_key
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function source_path( ?string $object_key = null ): string {
		$as3cf_file = $this->file( $object_key );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->source_path();
		}

		return $this->legacy_source_path( $object_key );
	}

	/**
	 * Getter for item's legacy source_path value.
	 *
	 * @param string|null $object_key
	 *
	 * @return string
	 */
	private function legacy_source_path( ?string $object_key = null ): string {
		if ( ! empty( $object_key ) ) {
			$objects = $this->objects();
			if ( isset( $objects[ $object_key ] ) ) {
				$object_file = $objects[ $object_key ]['source_file'];

				return str_replace( wp_basename( $this->source_path ), $object_file, $this->source_path );
			}
		}

		return $this->source_path;
	}

	/**
	 * Setter for item's source_path value.
	 *
	 * @param string $new_path
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function set_source_path( string $new_path ): void {
		$as3cf_file = $this->file( self::primary_object_key() );

		if ( ! empty( $as3cf_file ) ) {
			$as3cf_file->set_source_path( $new_path );
		}

		$this->legacy_set_source_path( $new_path );
	}

	/**
	 * Setter for item's legacy source_path value.
	 *
	 * @param string $new_path
	 */
	private function legacy_set_source_path( string $new_path ): void {
		$this->source_path = $new_path;
	}

	/**
	 * Getter for item's original_source_path value.
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function original_source_path(): string {
		$as3cf_file = $this->file( self::original_image_object_key() );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->source_path();
		}

		return $this->legacy_original_source_path();
	}

	/**
	 * Getter for item's legacy original_source_path value.
	 *
	 * @return string
	 */
	private function legacy_original_source_path(): string {
		return $this->original_source_path;
	}

	/**
	 * Setter for item's original_source_path value
	 *
	 * @param string $new_path
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function set_original_source_path( string $new_path ): void {
		$as3cf_file = $this->file( self::original_image_object_key() );

		if ( ! empty( $as3cf_file ) ) {
			$as3cf_file->set_source_path( $new_path );
		}

		$this->legacy_set_original_source_path( $new_path );
	}

	/**
	 * Setter for item's legacy original_source_path value
	 *
	 * @param string $new_path
	 */
	private function legacy_set_original_source_path( string $new_path ): void {
		$this->original_source_path = $new_path;
	}

	/**
	 * Get an absolute source path.
	 *
	 * Default it is based on the WordPress uploads folder.
	 *
	 * @param string|null $object_key Optional, by default the original file's source path is used.
	 *
	 * @return string
	 */
	public function full_source_path( ?string $object_key = null ): string {
		/**
		 * Filter the absolute directory path prefix for an item's source files.
		 *
		 * @param string $basedir    Default is WordPress uploads folder.
		 * @param Item   $as3cf_item The Item whose full source path is being accessed.
		 */
		$basedir = trailingslashit( apply_filters( 'as3cf_item_basedir', wp_upload_dir()['basedir'], $this ) );

		$as3cf_file = $this->file( $object_key );

		if ( ! empty( $as3cf_file ) ) {
			return $basedir . $as3cf_file->source_path();
		}

		return $basedir . $this->legacy_source_path( $object_key );
	}

	/**
	 * Creates an absolute source path for a given filename using the item's source path settings.
	 *
	 * This function can be used to create ad-hoc custom source file paths.
	 * There are no tests to see if the filename is known to be associated with the item.
	 *
	 * Default it is based on the WordPress uploads folder.
	 *
	 * @param string $filename Just a filename without any path.
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 *
	 * phpcs:disable PEAR.Functions.FunctionCallSignature.Indent
	 */
	public function full_source_path_for_filename( string $filename ): string {
		if ( empty( $filename ) ) {
			return '';
		}

		/**
		 * Filter the absolute directory path prefix for an item's source files.
		 *
		 * @param string $basedir    Default is WordPress uploads folder.
		 * @param Item   $as3cf_item The Item whose full source path is being accessed.
		 */
		$basedir = trailingslashit( apply_filters( 'as3cf_item_basedir', wp_upload_dir()['basedir'], $this ) );

		return $basedir . str_replace(
				wp_basename( $this->source_path() ),
				wp_basename( trim( $filename ) ),
				$this->source_path()
			);
	}

	/**
	 * Getter for item's extra_info value.
	 *
	 * @return array|null
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function extra_info(): array|null {
		return $this->extra_info;
	}

	/**
	 * Setter for extra_info value.
	 *
	 * @param array|null $extra_info
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function set_extra_info( ?array $extra_info ): void {
		$this->extra_info = $extra_info;
	}

	/**
	 * Getter for item's originator value.
	 *
	 * @return int
	 */
	public function originator(): int {
		return $this->originator;
	}

	/**
	 * Setter for item's originator value.
	 *
	 * @param int $originator
	 */
	public function set_originator( int $originator ): void {
		$this->originator = $originator;
	}

	/**
	 * Getter for item's is_verified value.
	 *
	 * @return bool
	 */
	public function is_verified(): bool {
		return $this->is_verified;
	}

	/**
	 * Setter for item's is_verified value.
	 *
	 * @param bool $is_verified
	 */
	public function set_is_verified( bool $is_verified ): void {
		$this->is_verified = $is_verified;
	}

	/**
	 * Does this item type use object versioning?
	 *
	 * @return bool
	 */
	public static function can_use_object_versioning(): bool {
		return static::CAN_USE_OBJECT_VERSIONING;
	}

	/**
	 * Get normalized object path dir.
	 *
	 * @return string
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function normalized_path_dir(): string {
		$directory = dirname( $this->path() );

		return ( '.' === $directory ) ? '' : AS3CF_Utils::trailingslash_prefix( $directory );
	}

	/**
	 * Get the first source id for a bucket and path.
	 *
	 * @param string $bucket
	 * @param string $path
	 *
	 * @return int|bool
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public static function get_source_id_by_bucket_and_path( string $bucket, string $path ): int|bool {
		if ( empty( $bucket ) || empty( $path ) ) {
			return false;
		}

		/** @var Item|bool $item */
		$item = static::get_from_object_cache( 'bucket_path', array( $bucket, $path ) );

		if ( ! empty( $item ) ) {
			return $item->source_id();
		}

		$as3cf_file = File::get_by_bucket_and_path( $bucket, $path );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->source_id();
		}

		return self::legacy_get_source_id_by_bucket_and_path( $bucket, $path );
	}

	/**
	 * Get the first source id for a bucket and path.
	 *
	 * @param string $bucket
	 * @param string $path
	 *
	 * @return int|bool
	 */
	private static function legacy_get_source_id_by_bucket_and_path( string $bucket, string $path ): int|bool {
		global $wpdb;

		if ( empty( $bucket ) || empty( $path ) ) {
			return false;
		}

		/** @var Item|bool $item */
		$item = static::get_from_object_cache( 'bucket_path', array( $bucket, $path ) );

		if ( ! empty( $item ) ) {
			return $item->source_id();
		}

		$sql = "
			SELECT source_id FROM " . static::get_table_name() . "
			WHERE source_type = %s
			AND bucket = %s
			AND (path = %s OR original_path = %s)
			ORDER BY source_id LIMIT 1
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, static::$source_type, $bucket, $path, $path );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$result = $wpdb->get_var( $sql );

		return empty( $result ) ? false : (int) $result;
	}

	/**
	 * Get the item source array for a given remote URL.
	 *
	 * @param string $url
	 *
	 * @return array|bool
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public static function get_item_source_by_remote_url( string $url ): array|bool {
		$as3cf_file = File::get_by_remote_url( $url );

		if ( ! empty( $as3cf_file ) ) {
			return $as3cf_file->item_source_array();
		}

		return self::legacy_get_item_source_by_remote_url( $url );
	}

	/**
	 * Get the item source array for a given remote URL.
	 *
	 * @param string $url
	 *
	 * @return array|bool
	 */
	private static function legacy_get_item_source_by_remote_url( string $url ): array|bool {
		global $wpdb;

		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		if ( ! AS3CF_Utils::usable_url( $url ) ) {
			return false;
		}

		$parts = AS3CF_Utils::parse_url( $url );
		$path  = AS3CF_Utils::decode_filename_in_path( ltrim( $parts['path'], '/' ) );

		// Remove the first directory to cater for bucket in path domain settings.
		if ( str_contains( $path, '/' ) ) {
			$path = explode( '/', $path );
			array_shift( $path );

			// If private prefix enabled, check if first segment and remove it as path/original_path do not include it.
			// We can't check every possible private prefix as each item may have a unique private prefix.
			// The only way to do that is with some fancy SQL, but that's not feasible as this particular
			// SQL query is already troublesome on some sites with badly behaved themes/plugins.
			if ( count( $path ) && $as3cf->get_delivery_provider()->use_signed_urls_key_file() ) {
				// We have to be able to handle multi-segment private prefixes such as "private/downloads/".
				$private_prefixes = explode(
					'/',
					untrailingslashit( $as3cf->get_setting( 'signed-urls-object-prefix' ) )
				);

				foreach ( $private_prefixes as $private_prefix ) {
					if ( $private_prefix === $path[0] ) {
						array_shift( $path );
					} else {
						// As soon as we don't have a match stop looking.
						break;
					}
				}
			}

			$path = implode( '/', $path );
		}

		$sql = "SELECT * FROM " . static::get_table_name() . " WHERE (path LIKE %s OR original_path LIKE %s);";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, '%' . $path, '%' . $path );

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
		$results = $wpdb->get_results( $sql );

		// Nothing found, shortcut out.
		if ( 0 === count( $results ) ) {
			// TODO: If upgrade in progress, fallback to legacy version of this function.
			return false;
		}

		// Regardless of whether 1 or many items found, must validate match.
		$path = AS3CF_Utils::decode_filename_in_path( ltrim( $parts['path'], '/' ) );

		foreach ( $results as $result ) {
			/** @var Item $class */
			$class      = $as3cf->get_source_type_class( $result->source_type );
			$as3cf_item = $class::create( $result );

			// If item's bucket matches first segment of URL path, remove it from URL path before checking match.
			if ( str_starts_with( $path, trailingslashit( $as3cf_item->bucket() ) ) ) {
				$match_path = ltrim( substr_replace( $path, '', 0, strlen( $as3cf_item->bucket() ) ), '/' );
			} else {
				$match_path = $path;
			}

			// If item's private prefix matches first segment of URL path, remove it from URL path before checking match.
			if (
				! empty( $as3cf_item->private_prefix() ) &&
				str_starts_with( $match_path, $as3cf_item->private_prefix() )
			) {
				$match_path = ltrim(
					substr_replace( $match_path, '', 0, strlen( $as3cf_item->private_prefix() ) ),
					'/'
				);
			}

			// Exact match, return ID.
			if ( $as3cf_item->path() === $match_path || $as3cf_item->original_path() === $match_path ) {
				return $as3cf_item->get_item_source_array();
			}
		}

		return false;
	}

	/**
	 * Get an array of managed source_ids in descending order.
	 *
	 * While source id isn't strictly unique, it is by source type, which is always used in queries based on called class.
	 *
	 * @param int|null  $upper_bound Returned source_ids should be lower than this, use null for no upper bound.
	 * @param int|null  $limit       Maximum number of source_ids to return. Required if not counting.
	 * @param bool      $count       Just return a count of matching source_ids? Negates $limit, default false.
	 * @param int|null  $originator  Optionally restrict to only records with given originator type from ORIGINATORS const.
	 * @param bool|null $is_verified Optionally restrict to only records that either are or are not verified.
	 *
	 * @return array|int
	 */
	public static function get_source_ids(
		?int $upper_bound,
		?int $limit,
		bool $count = false,
		?int $originator = null,
		?bool $is_verified = null
	): array|int {
		global $wpdb;

		if ( $count ) {
			$sql = 'SELECT COUNT(DISTINCT source_id)';
		} else {
			$sql = 'SELECT DISTINCT source_id';
		}

		$sql  .= ' FROM ' . static::get_table_name() . ' WHERE source_type = %s';
		$args = array( static::$source_type );

		if ( is_numeric( $upper_bound ) ) {
			$sql    .= ' AND source_id < %d';
			$args[] = $upper_bound;
		}

		// If an originator type given, check that it is valid before continuing and using.
		if ( null !== $originator ) {
			if ( in_array( $originator, self::ORIGINATORS ) ) {
				$sql    .= ' AND originator = %d';
				$args[] = $originator;
			} else {
				AS3CF_Error::log( __METHOD__ . ' called with invalid originator: ' . $originator );

				return $count ? 0 : array();
			}
		}

		// Has an is_verified value been given?
		if ( null !== $is_verified ) {
			$sql    .= ' AND is_verified = %d';
			$args[] = (int) $is_verified;
		}

		if ( ! $count ) {
			$sql    .= ' ORDER BY source_id DESC LIMIT %d';
			$args[] = $limit;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $args );

		if ( $count ) {
			// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
			return $wpdb->get_var( $sql );
		} else {
			// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- already prepared, must not be cached
			return array_map( 'intval', $wpdb->get_col( $sql ) );
		}
	}

	/**
	 * Get an array of un-managed source_ids in descending order.
	 *
	 * While source id isn't strictly unique, it is by source type, which is always used in queries based on called class.
	 *
	 * @param int  $upper_bound Returned source_ids should be lower than this, use null/0 for no upper bound.
	 * @param int  $limit       Maximum number of source_ids to return. Required if not counting.
	 * @param bool $count       Just return a count of matching source_ids? Negates $limit, default false.
	 *
	 * @return array|int
	 *
	 * NOTE: Must be overridden by subclass, only reason this is not abstract is because static is preferred.
	 */
	public static function get_missing_source_ids( $upper_bound, $limit, $count = false ) {
		if ( $count ) {
			return 0;
		} else {
			return array();
		}
	}

	/**
	 * Get array of objects (i.e. different sizes of same attachment item).
	 *
	 * @return array Keyed by size name, with (pathless) source_file and is_private values.
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function objects(): array {
		$objects = array();

		if ( ! empty( $this->files ) ) {
			$objects = array_map( function ( $as3cf_file ) {
				return array(
					'source_file' => wp_basename( $as3cf_file->source_path() ),
					'is_private'  => $as3cf_file->is_private(),
				);
			}, $this->files() );
		}

		return array_merge( $this->legacy_objects(), $objects );
	}

	/**
	 * Get array of objects (i.e. different sizes of same attachment item).
	 *
	 * @return array
	 */
	private function legacy_objects(): array {
		$extra_info = $this->extra_info();
		if ( isset( $extra_info['objects'] ) && is_array( $extra_info['objects'] ) ) {
			// Make sure that the primary object key, if exists, comes first
			$array_keys  = array_keys( $extra_info['objects'] );
			$primary_key = self::primary_object_key();
			if ( in_array( $primary_key, $array_keys ) && $primary_key !== $array_keys[0] ) {
				$extra_info['objects'] = array_merge( array( $primary_key => null ), $extra_info['objects'] );
			}

			return $extra_info['objects'];
		}

		return array();
	}

	/**
	 * Set array of objects (i.e. different sizes of same attachment item).
	 *
	 * NOTE: Calling this before path, source_path and private_prefix are set could cause problems.
	 *
	 * @param array $objects Keyed by size name, with (pathless) source_file and is_private values.
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function set_objects( array $objects ): void {
		// In this scenario, we need the legacy object data set as fallback for
		// the deprecated functions to pull from.
		$this->legacy_set_objects( $objects );

		foreach ( $objects as $size => $object ) {
			$as3cf_file = $this->file( $size );

			// Create or update File for size.
			if ( empty( $as3cf_file ) ) {
				$as3cf_file = File::create_for_item(
					$this,
					$this->provider_key_for_filename( $object['source_file'], $object['is_private'] ),
					$object['is_private'],
					$this->private_prefix(),
					$this->source_path( $size ),
					$size
				);
			} else {
				$as3cf_file->set_path(
					$this->provider_key_for_filename( $object['source_file'], $object['is_private'] )
				);
				$as3cf_file->set_is_private( $object['is_private'] );
				$as3cf_file->set_private_prefix( $this->private_prefix() );
				$as3cf_file->set_source_path(
					str_replace( wp_basename( $this->source_path() ), $object['source_file'], $this->source_path() )
				);
			}

			if ( ! empty( $as3cf_file ) ) {
				$this->set_file( $as3cf_file, $size );
			}
		}
	}

	/**
	 * Set array of objects (i.e. different sizes of same attachment item).
	 *
	 * @param array $objects
	 */
	private function legacy_set_objects( array $objects ): void {
		$extra_info = $this->extra_info();

		$extra_info['objects'] = $objects;
		$this->set_extra_info( $extra_info );
	}

	/**
	 * Synthesize a data struct to be used when passing information
	 * about the current item to filters that assume the item is a
	 * media library item.
	 *
	 * @return array
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function item_data_for_acl_filter(): array {
		return array(
			'source_type' => $this->source_type(),
			'file'        => $this->path( self::primary_object_key() ),
			'sizes'       => array_keys( $this->objects() ),
		);
	}

	/**
	 * Get absolute source file paths for offloaded files.
	 *
	 * @return array Associative array of object_key => path
	 */
	abstract public function full_source_paths();

	/**
	 * Get size name from file name.
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	abstract public function get_object_key_from_filename( $filename );

	/**
	 * Get the provider URL for an item
	 *
	 * @param string|null $object_key
	 *
	 * @return string|false
	 */
	abstract public function get_local_url( $object_key = null );

	/**
	 * Create a new item from the source id.
	 *
	 * @param int   $source_id
	 * @param array $options
	 *
	 * @return Item|WP_Error
	 */
	public static function create_from_source_id( int $source_id, array $options = array() ): Item|WP_Error {
		return new WP_Error(
			'exception',
			sprintf(
				'Doing it wrong! Trying to create a base %s class instance from source ID %d',
				__CLASS__,
				$source_id
			)
		);
	}

	/**
	 * Return a year/month string for the item
	 *
	 * @return string|null
	 */
	protected function get_item_time(): ?string {
		return null;
	}

	/**
	 * Get item's new public prefix path for current settings.
	 *
	 * @param bool $use_object_versioning
	 *
	 * @return string
	 */
	public function get_new_item_prefix( bool $use_object_versioning = true ): string {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		$prefix = $as3cf->get_object_prefix();

		$time   = $this->get_item_time();
		$prefix .= AS3CF_Utils::trailingslash_prefix( $as3cf->get_dynamic_prefix( $time, static::$can_use_yearmonth ) );

		if ( $use_object_versioning && static::can_use_object_versioning() && $as3cf->get_setting( 'object-versioning' ) ) {
			$prefix .= AS3CF_Utils::trailingslash_prefix( $as3cf->get_object_version_string() );
		}

		return AS3CF_Utils::trailingslash_prefix( $prefix );
	}

	/**
	 * Get ACL for object key
	 *
	 * @param string      $object_key Object key
	 * @param string|null $bucket     Optional bucket that ACL is potentially to be used with.
	 *
	 * @return string|null
	 */
	public function get_acl_for_object_key( string $object_key, ?string $bucket = null ): ?string {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		$acl     = null;
		$use_acl = $as3cf->use_acl_for_intermediate_size( 0, $object_key, $bucket, $this );

		if ( $use_acl ) {
			$acl = $this->is_private( $object_key ) ? $as3cf->get_storage_provider_instance( $this->provider() )->get_private_acl() : $as3cf->get_storage_provider_instance( $this->provider() )->get_default_acl();
		}

		return $acl;
	}

	/**
	 * Search for all items that have the source path(s).
	 *
	 * @param array|string $paths              Array of relative source paths.
	 * @param int|array    $exclude_source_ids Array of source_ids to exclude from search. Default, none.
	 * @param bool         $exact_match        Use paths as supplied (true, default), or greedy match on path without extension (e.g. find edited too).
	 * @param bool         $first_only         Only return first matched item sorted by source_id. Default false.
	 *
	 * @return array
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public static function get_by_source_path(
		array|string $paths,
		int|array $exclude_source_ids = array(),
		bool $exact_match = true,
		bool $first_only = false
	): array {
		global $wpdb;

		if ( is_string( $paths ) && ! empty( $paths ) ) {
			$paths = array( $paths );
		}

		if ( ! is_array( $paths ) || empty( $paths ) ) {
			return array();
		}

		$paths = array_map( 'esc_sql', $paths );

		$paths = AS3CF_Utils::make_upload_file_paths_relative( array_unique( $paths ) );

		$sql = '
			SELECT DISTINCT items.*
			FROM ' . static::get_table_name() . ' AS items
			INNER JOIN ' . File::get_table_name() . ' AS files ON files.item_id = items.id
			WHERE 1=1
		';

		if ( ! empty( $exclude_source_ids ) ) {
			if ( ! is_array( $exclude_source_ids ) ) {
				$exclude_source_ids = array( $exclude_source_ids );
			}

			$exclude_source_ids = array_map( 'intval', $exclude_source_ids );

			$sql .= ' AND items.source_id NOT IN (' . join( ',', $exclude_source_ids ) . ')';
		}

		if ( $exact_match ) {
			$sql .= " AND files.source_path IN ('" . join( "','", $paths ) . "')";
		} else {
			$likes = array_map( function ( $path ) {
				$ext  = '.' . pathinfo( $path, PATHINFO_EXTENSION );
				$path = substr_replace( $path, '%', -strlen( $ext ) );

				return "files.source_path LIKE '" . $path . "'";
			}, $paths );

			$sql .= ' AND (' . join( ' OR ', $likes ) . ')';
		}

		if ( $first_only ) {
			$sql .= ' ORDER BY items.source_id LIMIT 1';
		}

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- safe query, must not be cached
		$as3cf_items = array_map( static::class . '::create', $wpdb->get_results( $sql ) );

		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		if ( $as3cf->is_upgraded() ) {
			return $as3cf_items;
		}

		// We may still have non-migrated results, but we can skip source IDs we've already got.
		if ( ! empty( $as3cf_items ) ) {
			$matched_source_ids = array();

			foreach ( $as3cf_items as $as3cf_item ) {
				$matched_source_ids[] = $as3cf_item->source_id();
			}

			$exclude_source_ids = array_merge( $exclude_source_ids, $matched_source_ids );
		}

		$legacy_as3cf_items = self::legacy_get_by_source_path( $paths, $exclude_source_ids, $exact_match, $first_only );

		// We're good with new schema anyway if legacy turned up nothing.
		if ( empty( $legacy_as3cf_items ) ) {
			return $as3cf_items;
		}

		// If it turns out we didn't get anything from new schema, then legacy it is.
		if ( empty( $as3cf_items ) ) {
			return $legacy_as3cf_items;
		}

		// For first only, just check whether first legacy has source ID less that previously matched.
		if ( $first_only && $as3cf_items[0]->source_id() < $legacy_as3cf_items[0]->source_id() ) {
			return $as3cf_items;
		} elseif ( $first_only ) {
			return $legacy_as3cf_items;
		}

		// Should be able to whack the two sets together and return them.
		return array_merge( $as3cf_items, $legacy_as3cf_items );
	}

	/**
	 * Search for all items that have the source path(s).
	 *
	 * @param array|string $paths              Array of relative source paths.
	 * @param int|array    $exclude_source_ids Array of source_ids to exclude from search. Default, none.
	 * @param bool         $exact_match        Use paths as supplied (true, default), or greedy match on path without extension (e.g. find edited too).
	 * @param bool         $first_only         Only return first matched item sorted by source_id. Default false.
	 *
	 * @return array
	 */
	private static function legacy_get_by_source_path(
		array|string $paths,
		int|array $exclude_source_ids = array(),
		bool $exact_match = true,
		bool $first_only = false
	): array {
		global $wpdb;

		if ( is_string( $paths ) && ! empty( $paths ) ) {
			$paths = array( $paths );
		}

		if ( ! is_array( $paths ) || empty( $paths ) ) {
			return array();
		}

		$paths = array_map( 'esc_sql', $paths );

		$paths = AS3CF_Utils::make_upload_file_paths_relative( array_unique( $paths ) );

		$sql = '
			SELECT DISTINCT items.*
			FROM ' . static::get_table_name() . ' AS items USE INDEX (uidx_source_path, uidx_original_source_path)
			WHERE 1=1
		';

		if ( ! empty( $exclude_source_ids ) ) {
			if ( ! is_array( $exclude_source_ids ) ) {
				$exclude_source_ids = array( $exclude_source_ids );
			}

			$exclude_source_ids = array_map( 'intval', $exclude_source_ids );

			$sql .= ' AND items.source_id NOT IN (' . join( ',', $exclude_source_ids ) . ')';
		}

		if ( $exact_match ) {
			$sql .= " AND (items.source_path IN ('" . join( "','", $paths ) . "')";
			$sql .= " OR items.original_source_path IN ('" . join( "','", $paths ) . "'))";
		} else {
			$likes = array_map( function ( $path ) {
				$ext  = '.' . pathinfo( $path, PATHINFO_EXTENSION );
				$path = substr_replace( $path, '%', -strlen( $ext ) );

				return "items.source_path LIKE '" . $path . "' OR items.original_source_path LIKE '" . $path . "'";
			}, $paths );

			$sql .= ' AND (' . join( ' OR ', $likes ) . ')';
		}

		if ( $first_only ) {
			$sql .= ' ORDER BY items.source_id LIMIT 1';
		}

		// phpcs:ignore WordPress.DB,PluginCheck.Security.DirectDB.UnescapedDBParameter -- safe query, must not be cached
		return array_map( static::class . '::create', $wpdb->get_results( $sql ) );
	}

	/**
	 * Update public path prefix for all objects.
	 *
	 * NOTE: Should be called after `set_private_prefix`.
	 *
	 * @param string $new_prefix
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function update_path_prefix( string $new_prefix ): void {
		foreach ( $this->files() as $as3cf_file ) {
			$as3cf_file->update_public_path_prefix( $new_prefix );
		}

		$this->legacy_update_path_prefix( $new_prefix );
	}

	/**
	 * Update path and original path with a new prefix.
	 *
	 * @param string $new_prefix
	 */
	private function legacy_update_path_prefix( string $new_prefix ): void {
		$new_prefix          = AS3CF_Utils::trailingslash_prefix( $new_prefix );
		$this->path          = $new_prefix . wp_basename( $this->path() );
		$this->original_path = $new_prefix . wp_basename( $this->original_path() );
	}

	/**
	 * Returns a link to the items edit page in WordPress.
	 *
	 * @param object $error
	 *
	 * @return object|null Null or object containing properties 'url' and 'text'
	 */
	public static function admin_link( object $error ): ?object {
		return null;
	}

	/**
	 * Is the item served by provider.
	 *
	 * @param bool                  $skip_rewrite_check          Still check if offloaded even if not currently rewriting URLs? Default: false
	 * @param bool                  $skip_current_provider_check Skip checking if offloaded to current provider. Default: false, negated if $provider supplied
	 * @param Storage_Provider|null $provider                    Provider where item is expected to be offloaded to. Default: currently configured provider
	 * @param bool                  $check_is_verified           Check that metadata is verified, has no effect if $skip_rewrite_check is true. Default: false
	 *
	 * @return bool
	 */
	public function served_by_provider(
		bool $skip_rewrite_check = false,
		bool $skip_current_provider_check = false,
		?Storage_Provider $provider = null,
		bool $check_is_verified = false
	): bool {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		if ( ! $skip_rewrite_check && ! $as3cf->get_setting( 'serve-from-s3' ) ) {
			// Not serving provider URLs.
			return false;
		}

		if ( ! $skip_rewrite_check && ! empty( $check_is_verified ) && ! $this->is_verified() ) {
			// Offload not verified, treat as not offloaded.
			return false;
		}

		if ( ! $skip_current_provider_check && empty( $provider ) ) {
			$provider = $as3cf->get_storage_provider();
		}

		if ( ! empty( $provider ) && $provider::get_provider_key_name() !== $this->provider() ) {
			// File not uploaded to required provider.
			return false;
		}

		return true;
	}

	/**
	 * Does the item's files exist locally?
	 *
	 * @return bool
	 */
	public function exists_locally(): bool {
		foreach ( $this->full_source_paths() as $path ) {
			if ( file_exists( $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the provider URL for an item.
	 *
	 * @param string|null $object_key
	 * @param int|null    $expires
	 * @param array       $headers
	 *
	 * @return string|WP_Error|bool
	 */
	public function get_provider_url(
		?string $object_key = null,
		?int $expires = null,
		array $headers = array()
	): WP_Error|bool|string {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		if ( is_null( $object_key ) ) {
			$object_key = self::primary_object_key();
		}

		// Is a signed expiring URL required for the requested object?
		if ( is_null( $expires ) ) {
			$expires = $this->is_private( $object_key ) ? Amazon_S3_And_CloudFront::DEFAULT_EXPIRES : null;
		} else {
			$expires = $this->is_private( $object_key ) ? $expires : null;
		}

		$scheme                 = $as3cf->get_url_scheme();
		$enable_delivery_domain = $as3cf->get_delivery_provider()->delivery_domain_allowed() ? $as3cf->get_setting( 'enable-delivery-domain' ) : false;
		$delivery_domain        = $as3cf->get_setting( 'delivery-domain' );
		$item_path              = $this->path( $object_key );

		if ( ! $enable_delivery_domain || empty( $delivery_domain ) ) {
			$region = $this->region();

			if ( is_wp_error( $region ) ) {
				return $region;
			}

			$delivery_domain = $as3cf->get_storage_provider_instance(
				$this->provider()
			)->get_url_domain(
				$this->bucket(),
				$region,
				$expires
			);
		} else {
			$delivery_domain = AS3CF_Utils::sanitize_custom_domain( $delivery_domain );
		}

		if ( ! is_null( $expires ) && ! $as3cf->get_storage_provider()->needs_access_keys() ) {
			try {
				/**
				 * Filters the expires time for private content.
				 *
				 * @param int $expires The expires time in seconds.
				 */
				$timestamp = time() + apply_filters( 'as3cf_expires', $expires );
				$url       = $as3cf->get_delivery_provider()->get_signed_url(
					$this,
					$item_path,
					$delivery_domain,
					$scheme,
					$timestamp,
					$headers
				);

				/**
				 * Filters the secure URL for private content.
				 *
				 * @param string $url         The URL.
				 * @param Item   $item        The Item object.
				 * @param array  $item_source The item source descriptor array.
				 * @param int    $timestamp   Expiry timestamp.
				 * @param array  $headers     Optional extra http headers.
				 */
				return apply_filters(
					'as3cf_get_item_secure_url',
					$url,
					$this,
					$this->get_item_source_array(),
					$timestamp,
					$headers
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'exception', $e->getMessage() );
			}
		} else {
			try {
				$url = $as3cf->get_delivery_provider()->get_url(
					$this,
					$item_path,
					$delivery_domain,
					$scheme,
					$headers
				);

				/**
				 * Filters the URL for public content.
				 *
				 * @param string $url         The URL.
				 * @param Item   $item        The Item object.
				 * @param array  $item_source The item source descriptor array.
				 * @param int    $source_id   The source ID of the object.
				 * @param int    $timestamp   Expiry timestamp.
				 * @param array  $headers     Optional extra http headers.
				 */
				return apply_filters(
					'as3cf_get_item_url',
					$url,
					$this,
					$this->get_item_source_array(),
					$expires,
					$headers
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'exception', $e->getMessage() );
			}
		}
	}

	/**
	 * Update file sizes after removing local files for an item.
	 *
	 * @param int $original_size
	 * @param int $total_size
	 */
	public function update_filesize_after_remove_local( int $original_size, int $total_size ): void {
	}

	/**
	 * Cleanup file sizes after getting item files back from the bucket.
	 */
	public function update_filesize_after_download_local() {
	}

	/**
	 * If another item in current site shares full size *local* paths, only remove remote files not referenced by duplicates.
	 * We reference local paths as they should be reflected one way or another remotely, including backups.
	 *
	 * @param Item  $as3cf_item
	 * @param array $paths
	 *
	 * @return array
	 */
	public function remove_duplicate_paths( Item $as3cf_item, array $paths ): array {
		return $paths;
	}

	/**
	 * Verify that the extra info uses the new format set in plugin version 2.6.0,
	 * update if needed.
	 *
	 * @param mixed|array $extra_info
	 * @param int         $source_id
	 * @param bool        $is_private
	 *
	 * @since 2.6.0
	 */
	protected static function maybe_update_extra_info( mixed &$extra_info, int $source_id, bool $is_private ): void {
		if ( ! is_array( $extra_info ) ) {
			$extra_info = array();
		}

		// Compatibility fallback for if just an array of private sizes is supplied.
		$private_sizes = array();
		if ( ! isset( $extra_info['private_sizes'] ) && ! isset( $extra_info['private_prefix'] ) && ! isset( $extra_info['objects'] ) ) {
			$private_sizes = $extra_info;
		}

		// Compatibility fallback for old broken format.
		if ( isset( $extra_info['private_sizes']['private_sizes'] ) ) {
			$extra_info['private_sizes'] = $extra_info['private_sizes']['private_sizes'];
		}

		// Extra info must be an array with at least one element, if not it's broken.
		if ( isset( $extra_info['objects'] ) && ( ! is_array( $extra_info['objects'] ) || empty( $extra_info['objects'] ) ) ) {
			unset( $extra_info['objects'] );
		}

		if ( ! isset( $extra_info['objects'] ) ) {
			$private_sizes         = isset( $extra_info['private_sizes'] ) && is_array( $extra_info['private_sizes'] ) ? $extra_info['private_sizes'] : $private_sizes;
			$extra_info['objects'] = array();

			$files = AS3CF_Utils::get_attachment_file_paths( $source_id, false );
			foreach ( $files as $object_key => $file ) {
				if ( 'file' === $object_key ) {
					continue;
				}

				$new_object = array(
					'source_file' => wp_basename( $file ),
					'is_private'  => self::primary_object_key() === $object_key
						? $is_private
						: in_array( $object_key, $private_sizes ),
				);

				$extra_info['objects'][ $object_key ] = $new_object;
			}
		}

		if ( isset( $extra_info['private_sizes'] ) ) {
			unset( $extra_info['private_sizes'] );
		}
	}

	/**
	 * Returns the item source description array for this item.
	 *
	 * @return array Array with the format:
	 *               array(
	 *                  'id'          => 1,
	 *                  'source_type' => 'foo-type',
	 *               )
	 */
	public function get_item_source_array(): array {
		return array(
			'id'          => $this->source_id(),
			'source_type' => $this->source_type(),
		);
	}

	/**
	 * Returns an array keyed by offloaded source file name.
	 *
	 * Each entry is as per objects, but also includes an array of object_keys.
	 *
	 * @return array
	 *
	 * @deprecated 3.4.0 Please use file functions instead.
	 */
	public function offloaded_files(): array {
		$offloaded_files = array();

		foreach ( $this->files() as $size => $file ) {
			$source_file = wp_basename( $file->source_path() );

			if ( isset( $offloaded_files[ $source_file ] ) ) {
				$offloaded_files[ $source_file ]['object_keys'][] = $size;
			} else {
				$object = array(
					'source_file' => $source_file,
					'object_keys' => array( $size ),
				);

				$offloaded_files[ $source_file ] = $object;
			}
		}

		if ( ! empty( $offloaded_files ) ) {
			return $offloaded_files;
		}

		return self::legacy_offloaded_files();
	}

	/**
	 * Returns an array keyed by offloaded source file name.
	 *
	 * Each entry is as per objects, but also includes an array of object_keys.
	 *
	 * @return array
	 */
	private function legacy_offloaded_files(): array {
		$offloaded_files = array();

		foreach ( $this->objects() as $object_key => $object ) {
			if ( isset( $offloaded_files[ $object['source_file'] ] ) ) {
				$offloaded_files[ $object['source_file'] ]['object_keys'][] = $object_key;
			} else {
				$object['object_keys']                     = array( $object_key );
				$offloaded_files[ $object['source_file'] ] = $object;
			}
		}

		return $offloaded_files;
	}

	/**
	 * Is the supplied item_source considered to be empty?
	 *
	 * @param array|mixed $item_source
	 *
	 * @return bool
	 */
	public static function is_empty_item_source( mixed $item_source ): bool {
		if (
			! is_array( $item_source ) ||
			empty( $item_source['source_type'] ) ||
			! isset( $item_source['id'] ) ||
			! is_numeric( $item_source['id'] ) ||
			$item_source['id'] < 0
		) {
			return true;
		}

		return false;
	}

	/**
	 * Count items on current site.
	 *
	 * @param bool $skip_transient Whether to force database query and skip transient, default false
	 * @param bool $force          Whether to force database query and skip static cache, implies $skip_transient, default false
	 * @param int  $blog_id        Optional, the blog ID to count media items for
	 *
	 * @return array Keys:
	 *               total: Total media count for site (current blog id)
	 *               offloaded: Count of offloaded media for site (current blog id)
	 *               not_offloaded: Difference between total and offloaded
	 */
	public static function count_items( bool $skip_transient = false, bool $force = false, int $blog_id = 0 ): array {
		if ( empty( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}

		$transient_key = static::transient_key_for_item_counts( $blog_id );

		// Been here, done it, won't do it again!
		// Well, unless this is the first transient skip for the prefix, then we need to do it.
		if ( ! $force && ! empty( static::$item_counts[ $transient_key ] ) && ( false === $skip_transient || ! empty( static::$item_count_skips[ $transient_key ] ) ) ) {
			return static::$item_counts[ $transient_key ];
		}

		static $sites_count;

		if ( $force || $skip_transient || false === ( $result = get_site_transient( $transient_key ) ) ) {
			$result = static::get_item_counts();

			ksort( $result );

			// Timeout is randomised to ensure multisite subsites don't all try and update at the same time.
			// Large site default of 15 - 120 minutes range gives us 6300 possible timeouts, checked every 5 minutes,
			// with each subsite getting at least 15 mins breather before records counted again.
			$min = 15;
			$max = 120;

			if ( empty( $sites_count ) ) {
				$sites_count = is_multisite() ? count( AS3CF_Utils::get_blog_ids() ) : 1;
			}

			// For smaller media counts we can reduce the timeout to make changes more responsive
			// without noticeably impacting performance.
			if ( 5000 > $result['total'] && 50 > $sites_count ) {
				$min = 0;
				$max = 0;
			} elseif ( 50000 > $result['total'] && 500 > $sites_count ) {
				$min = 5;
				$max = 15;
			}

			/**
			 * How many minutes minimum should a subsite's media counts be cached?
			 *
			 * Min: 0 minutes.
			 * Max: 1 day (1440 minutes).
			 *
			 * Default 0 for small media counts, 5 for medium (5k <= X < 50k), 15 for larger (>= 50k).
			 * However, on a multisite, 0 is only set for < 50 subsites, 5 for < 500 subsites, otherwise it's 15.
			 *
			 * @param int    $minutes
			 * @param int    $blog_id
			 * @param string $source_type The source type currently being counted, e.g. 'media-library'.
			 *
			 * @retun int
			 */
			$min = min(
				max(
					0,
					(int) apply_filters( 'as3cf_blog_media_counts_timeout_min', $min, $blog_id, static::source_type() )
				),
				1440
			);
			$max = max( $min, $max );

			/**
			 * How many minutes maximum should a subsite's media counts be cached?
			 *
			 * Min: 0 minutes (or minimum set by as3cf_blog_media_counts_timeout_min filter for same blog id and source type).
			 * Max: 1 day (1440 minutes).
			 *
			 * Default 0 for small media counts, 15 for medium (5k <= X < 50k), 120 for larger (>= 50k).
			 * However, on a multisite, 0 is only set for < 50 subsites, 15 for < 500 subsites, otherwise it's 120.
			 *
			 * @param int    $minutes     Default or larger minimum set by as3cf_blog_media_counts_timeout_min filter for same blog id and source type.
			 * @param int    $blog_id
			 * @param string $source_type The source type currently being counted, e.g. 'media-library'.
			 *
			 * @retun int
			 */
			$max = min(
				max(
					$min,
					(int) apply_filters( 'as3cf_blog_media_counts_timeout_max', $max, $blog_id, static::source_type() )
				),
				1440
			);

			// We lied, our real minimums are min 3 and max 15 seconds
			// to ensure there's at least a tiny bit of caching,
			// which helps combat some potential race conditions,
			// and makes sure the transient has a timeout.
			$min = max( $min, 0.05 );
			$max = max( $max, 0.25 );

			set_site_transient(
				$transient_key,
				$result,
				wp_rand( $min * MINUTE_IN_SECONDS, $max * MINUTE_IN_SECONDS )
			);

			// One way or another we've skipped the transient.
			static::$item_count_skips[ $transient_key ] = true;
		}

		static::$item_counts[ $transient_key ] = $result;

		return $result;
	}

	/**
	 * Returns the transient key to be used for storing blog specific item counts.
	 *
	 * @param int $blog_id
	 *
	 * @return string
	 */
	public static function transient_key_for_item_counts( int $blog_id ): string {
		return 'as3cf_' . absint( $blog_id ) . '_attachment_counts_' . static::$source_type;
	}

	/**
	 * Get all Files associated with item.
	 *
	 * If the primary object is set, it'll be first, the order of the rest is undefined.
	 *
	 * @return File[]
	 */
	public function files(): array {
		if (
			! empty( $this->files ) &&
			in_array( self::primary_object_key(), array_keys( $this->files ) ) &&
			self::primary_object_key() !== array_key_first( $this->files )
		) {
			$this->files = array_merge( array( self::primary_object_key() => null ), $this->files );
		}

		return $this->files;
	}

	/**
	 * Get File for size associated with Item.
	 *
	 * @param string|null $size Optional File size key, default primary size.
	 *
	 * @return false|File
	 */
	public function file( ?string $size = null ): bool|File {
		if ( empty( $size ) ) {
			return $this->file( self::primary_object_key() );
		}

		if ( ! empty( $this->files[ $size ] ) ) {
			return $this->files[ $size ];
		}

		return false;
	}

	/**
	 * Add/update a File for the given size key.
	 *
	 * @param File        $as3cf_file The File instance to add/update on the Item.
	 * @param string|null $size       Optional File size key, default primary size.
	 *
	 * @return void
	 */
	public function set_file( File $as3cf_file, ?string $size = null ): void {
		if ( empty( $size ) ) {
			$size = self::primary_object_key();
		}

		$this->files[ $size ] = $as3cf_file;
	}

	/**
	 * Any private files in this item?
	 *
	 * @return bool
	 */
	private function has_private_files(): bool {
		foreach ( $this->files() as $as3cf_file ) {
			if ( $as3cf_file->is_private() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Update the private prefix for all the item's files.
	 *
	 * @param string $private_prefix
	 */
	private function set_private_prefix_for_all_files( string $private_prefix ): void {
		foreach ( $this->files() as $as3cf_file ) {
			$as3cf_file->set_private_prefix( $private_prefix );
		}
	}

	/**
	 * Returns an associative array of provider paths by their size.
	 *
	 * NOTE: There may be duplicate keys if sizes reference same source file/object.
	 *
	 * @return array
	 */
	public function paths(): array {
		return array_map( function ( $as3cf_file ) {
			return $as3cf_file->path();
		}, $this->files() );
	}

	/**
	 * Count total, offloaded and not offloaded items on current site.
	 *
	 * @return array Keys:
	 *               total: Total media count for site (current blog id)
	 *               offloaded: Count of offloaded media for site (current blog id)
	 *               not_offloaded: Difference between total and offloaded
	 */
	abstract protected static function get_item_counts(): array;
}
