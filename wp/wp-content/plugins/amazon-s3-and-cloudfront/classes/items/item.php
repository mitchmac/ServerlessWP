<?php

namespace DeliciousBrains\WP_Offload_Media\Items;

use Amazon_S3_And_CloudFront;
use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\Storage_Provider;
use Exception;
use WP_Error;

abstract class Item {
	const ITEMS_TABLE               = 'as3cf_items';
	const ORIGINATORS               = array(
		'standard'      => 0,
		'metadata-tool' => 1,
	);
	const CAN_USE_OBJECT_VERSIONING = true;

	protected static $source_type_name  = 'Item';
	protected static $source_type       = '';
	protected static $source_table      = '';
	protected static $source_fk         = '';
	protected static $summary_type_name = '';
	protected static $summary_type      = '';

	protected static $can_use_yearmonth = true;

	protected static $items_cache_by_id          = array();
	protected static $items_cache_by_source_id   = array();
	protected static $items_cache_by_path        = array();
	protected static $items_cache_by_source_path = array();

	protected static $item_counts      = array();
	protected static $item_count_skips = array();

	/**
	 * @var array Keys with array of fields that can be used for cache lookups.
	 */
	protected static $cache_keys = array(
		'id'          => array( 'id' ),
		'source_id'   => array( 'source_id' ),
		'path'        => array( 'path', 'original_path' ),
		'source_path' => array( 'source_path', 'original_source_path' ),
	);

	private static $checked_table_exists = array();
	private static $enable_cache         = true;

	private $id;
	private $provider;
	private $region;
	private $bucket;
	private $path;
	private $original_path;
	private $is_private;
	private $source_id;
	private $source_path;
	private $original_source_path;
	private $extra_info;
	private $originator;
	private $is_verified;

	/**
	 * Item constructor.
	 *
	 * @param string $provider              Storage provider key name, e.g. "aws".
	 * @param string $region                Region for item's bucket.
	 * @param string $bucket                Bucket for item.
	 * @param string $path                  Key path for item (full sized if type has thumbnails etc).
	 * @param bool   $is_private            Is the object private in the bucket.
	 * @param int    $source_id             ID that source has.
	 * @param string $source_path           Path that source uses, could be relative or absolute depending on source.
	 * @param string $original_filename     An optional filename with no path that was previously used for the item.
	 * @param array  $extra_info            An optional array of extra data specific to the source type.
	 * @param int    $id                    Optional Item record ID.
	 * @param int    $originator            Optional originator of record from ORIGINATORS const.
	 * @param bool   $is_verified           Optional flag as to whether Item's objects are known to exist.
	 * @param bool   $use_object_versioning Optional flag as to whether path prefix should use Object Versioning if type allows it.
	 */
	public function __construct(
		$provider,
		$region,
		$bucket,
		$path,
		$is_private,
		$source_id,
		$source_path,
		$original_filename = null,
		$extra_info = array(),
		$id = null,
		$originator = 0,
		$is_verified = true,
		$use_object_versioning = self::CAN_USE_OBJECT_VERSIONING
	) {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		$this->source_id   = $source_id;
		$this->source_path = $source_path;

		if ( empty( $original_filename ) ) {
			$this->original_source_path = $source_path;
		} else {
			$this->original_source_path = str_replace( wp_basename( $source_path ), $original_filename, $source_path );
		}

		// Set offload data from previous duplicate if exact match by source path exists.
		if ( empty( $path ) ) {
			$prev_items = static::get_by_source_path( array( $this->source_path, $this->original_source_path ), $this->source_id, true, true );

			if ( ! is_wp_error( $prev_items ) && ! empty( $prev_items[0] ) && is_a( $prev_items[0], get_class( $this ) ) ) {
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

		if ( ! is_array( $extra_info ) ) {
			$extra_info = array();
		}

		if ( ! isset( $extra_info['private_prefix'] ) || is_null( $extra_info['private_prefix'] ) ) {
			$extra_info['private_prefix'] = '';
			if ( $as3cf->private_prefix_enabled() ) {
				$extra_info['private_prefix'] = AS3CF_Utils::trailingslash_prefix( $as3cf->get_setting( 'signed-urls-object-prefix', '' ) );
			}
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

		$this->provider    = $provider;
		$this->region      = $region;
		$this->bucket      = $bucket;
		$this->path        = $path;
		$this->extra_info  = $extra_info;
		$this->originator  = $originator;
		$this->is_verified = $is_verified;

		if ( empty( $original_filename ) ) {
			$this->original_path = $path;
		} else {
			$this->original_path = str_replace( wp_basename( $path ), $original_filename, $path );
		}

		if ( ! empty( $id ) ) {
			$this->id = $id;
		}

		$this->set_is_private( (bool) $is_private );

		static::add_to_items_cache( $this );
	}

	/**
	 * Returns the standard object key for an items primary object
	 *
	 * @return string
	 */
	public static function primary_object_key() {
		return '__as3cf_primary';
	}

	/**
	 * Enable the built-in Item cache.
	 */
	public static function enable_cache() {
		self::$enable_cache = true;
	}

	/**
	 * Disable the built-in Item cache.
	 */
	public static function disable_cache() {
		self::$enable_cache = false;
	}

	/**
	 * Returns the string used to group all keys in the object cache by.
	 *
	 * @return string
	 */
	protected static function get_object_cache_group() {
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
	 * @return string
	 */
	protected static function get_object_cache_base_key() {
		$blog_id = get_current_blog_id();

		return static::items_table() . '-' . $blog_id . '-' . static::$source_type;
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
	protected static function get_object_cache_full_key( $base_key, $key, $field ) {
		return sanitize_text_field( $base_key . '-' . $key . '-' . $field );
	}

	/**
	 * Add the given item to the object cache.
	 *
	 * @param Item $item
	 */
	protected static function add_to_object_cache( $item ) {
		if ( empty( $item ) || empty( static::$cache_keys ) ) {
			return;
		}

		$base_key = static::get_object_cache_base_key();
		$group    = static::get_object_cache_group();

		$keys = array();

		foreach ( static::$cache_keys as $key => $fields ) {
			foreach ( $fields as $field ) {
				$full_key = static::get_object_cache_full_key( $base_key, $key, $item->{$field}() );

				if ( in_array( $full_key, $keys ) ) {
					continue;
				}

				wp_cache_set( $full_key, $item, $group );

				$keys[] = $full_key;
			}
		}
	}

	/**
	 * Delete the given item from the object cache.
	 *
	 * @param Item $item
	 */
	protected static function remove_from_object_cache( $item ) {
		if ( empty( $item ) || empty( static::$cache_keys ) ) {
			return;
		}

		$base_key = static::get_object_cache_base_key();
		$group    = static::get_object_cache_group();

		$keys = array();

		foreach ( static::$cache_keys as $key => $fields ) {
			foreach ( $fields as $field ) {
				$full_key = static::get_object_cache_full_key( $base_key, $key, $item->{$field}() );

				if ( in_array( $full_key, $keys ) ) {
					continue;
				}

				wp_cache_delete( $full_key, $group );

				$keys[] = $full_key;
			}
		}
	}

	/**
	 * Try and get Item from object cache by known key and value.
	 *
	 * Note: Actual lookup is scoped by blog and item's source_type, so example key may be 'source_id'.
	 *
	 * @param string $key   The base of the key that makes up the lookup, e.g. field for given value.
	 * @param mixed  $value Will be coerced to string for lookup.
	 *
	 * @return bool|Item
	 */
	protected static function get_from_object_cache( $key, $value ) {
		if ( ! array_key_exists( $key, static::$cache_keys ) ) {
			return false;
		}

		$base_key = static::get_object_cache_base_key();
		$full_key = static::get_object_cache_full_key( $base_key, $key, $value );
		$group    = static::get_object_cache_group();
		$force    = false;
		$found    = false;
		$result   = wp_cache_get( $full_key, $group, $force, $found );

		if ( $found ) {
			return $result;
		}

		return false;
	}

	/**
	 * (Re)initialize the static cache used for speeding up queries.
	 */
	public static function init_cache() {
		self::$checked_table_exists = array();

		static::$items_cache_by_id          = array();
		static::$items_cache_by_source_id   = array();
		static::$items_cache_by_path        = array();
		static::$items_cache_by_source_path = array();

		static::$item_counts      = array();
		static::$item_count_skips = array();
	}

	/**
	 * Add an item to the static cache to allow fast retrieval via get_from_items_cache_by_* functions.
	 *
	 * @param Item $item
	 */
	protected static function add_to_items_cache( $item ) {
		$blog_id = get_current_blog_id();

		if ( ! empty( $item->id() ) ) {
			static::$items_cache_by_id[ $blog_id ][ $item->id() ] = $item;
		}

		if ( ! empty( $item->source_id() ) ) {
			static::$items_cache_by_source_id[ $blog_id ][ static::$source_type ][ $item->source_id() ] = $item;
		}

		if ( ! empty( $item->path() ) ) {
			static::$items_cache_by_path[ $blog_id ][ static::$source_type ][ $item->original_path() ] = $item;
			static::$items_cache_by_path[ $blog_id ][ static::$source_type ][ $item->path() ]          = $item;
		}

		if ( ! empty( $item->source_path() ) ) {
			static::$items_cache_by_source_path[ $blog_id ][ static::$source_type ][ $item->original_source_path() ] = $item;
			static::$items_cache_by_source_path[ $blog_id ][ static::$source_type ][ $item->source_path() ]          = $item;
		}
	}

	/**
	 * Remove an item from the static cache that allows fast retrieval via get_from_items_cache_by_* functions.
	 *
	 * @param Item $item
	 */
	protected static function remove_from_items_cache( $item ) {
		$blog_id = get_current_blog_id();

		if ( ! empty( $item->id() ) ) {
			unset( static::$items_cache_by_id[ $blog_id ][ $item->id() ] );
		}

		if ( ! empty( $item->source_id() ) ) {
			unset( static::$items_cache_by_source_id[ $blog_id ][ static::$source_type ][ $item->source_id() ] );
		}

		if ( ! empty( $item->path() ) ) {
			unset( static::$items_cache_by_path[ $blog_id ][ static::$source_type ][ $item->original_path() ] );
			unset( static::$items_cache_by_path[ $blog_id ][ static::$source_type ][ $item->path() ] );
		}

		if ( ! empty( $item->source_path() ) ) {
			unset( static::$items_cache_by_source_path[ $blog_id ][ static::$source_type ][ $item->original_source_path() ] );
			unset( static::$items_cache_by_source_path[ $blog_id ][ static::$source_type ][ $item->source_path() ] );
		}
	}

	/**
	 * Try and get Item from cache by known id.
	 *
	 * @param int $id
	 *
	 * @return bool|Item
	 */
	private static function get_from_items_cache_by_id( $id ) {
		if ( false === self::$enable_cache ) {
			return false;
		}

		$blog_id = get_current_blog_id();

		if ( ! empty( static::$items_cache_by_id[ $blog_id ][ $id ] ) ) {
			return static::$items_cache_by_id[ $blog_id ][ $id ];
		}

		$item = static::get_from_object_cache( 'id', $id );

		if ( $item ) {
			static::add_to_items_cache( $item );

			return $item;
		}

		return false;
	}

	/**
	 * Try and get Item from cache by known source_id.
	 *
	 * @param int $source_id
	 *
	 * @return bool|Item
	 */
	private static function get_from_items_cache_by_source_id( $source_id ) {
		if ( false === self::$enable_cache ) {
			return false;
		}

		$blog_id = get_current_blog_id();

		if ( ! empty( static::$items_cache_by_source_id[ $blog_id ][ static::$source_type ][ $source_id ] ) ) {
			return static::$items_cache_by_source_id[ $blog_id ][ static::$source_type ][ $source_id ];
		}

		$item = static::get_from_object_cache( 'source_id', $source_id );

		if ( $item ) {
			static::add_to_items_cache( $item );

			return $item;
		}

		return false;
	}

	/**
	 * Try and get Item from cache by known bucket and path.
	 *
	 * @param string $bucket
	 * @param string $path
	 *
	 * @return bool|Item
	 */
	private static function get_from_items_cache_by_bucket_and_path( $bucket, $path ) {
		if ( false === self::$enable_cache ) {
			return false;
		}

		$blog_id = get_current_blog_id();

		if ( ! empty( static::$items_cache_by_path[ $blog_id ][ static::$source_type ][ $path ] ) ) {
			/** @var Item $item */
			$item = static::$items_cache_by_path[ $blog_id ][ static::$source_type ][ $path ];

			if ( $item->bucket() === $bucket ) {
				return $item;
			}
		}

		return false;
	}

	/**
	 * The full items table name for current blog.
	 *
	 * @return string
	 */
	public static function items_table(): string {
		global $wpdb;

		/* @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		$table_name = $wpdb->get_blog_prefix() . static::ITEMS_TABLE;

		if ( empty( self::$checked_table_exists[ $table_name ] ) ) {
			self::$checked_table_exists[ $table_name ] = true;

			$schema_version = get_option( $as3cf->get_plugin_prefix() . '_schema_version', '0.0.0' );

			if ( version_compare( $schema_version, $as3cf->get_plugin_version(), '<' ) ) {
				self::install_table( $table_name );

				update_option( $as3cf->get_plugin_prefix() . '_schema_version', $as3cf->get_plugin_version() );
			}
		}

		return $table_name;
	}

	/**
	 * Create the table needed by this class with given name (for current site).
	 *
	 * @param string $table_name
	 */
	private static function install_table( $table_name ) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$wpdb->hide_errors();

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "
				CREATE TABLE {$table_name} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				provider VARCHAR(18) NOT NULL,
				region VARCHAR(255) NOT NULL,
				bucket VARCHAR(255) NOT NULL,
				path VARCHAR(1024) NOT NULL,
				original_path VARCHAR(1024) NOT NULL,
				is_private BOOLEAN NOT NULL DEFAULT 0,
				source_type VARCHAR(18) NOT NULL,
				source_id BIGINT(20) UNSIGNED NOT NULL,
				source_path VARCHAR(1024) NOT NULL,
				original_source_path VARCHAR(1024) NOT NULL,
				extra_info LONGTEXT,
				originator TINYINT UNSIGNED NOT NULL DEFAULT 0,
				is_verified BOOLEAN NOT NULL DEFAULT 1,
				PRIMARY KEY  (id),
				UNIQUE KEY uidx_path (path(190), id),
				UNIQUE KEY uidx_original_path (original_path(190), id),
				UNIQUE KEY uidx_source_path (source_path(190), id),
				UNIQUE KEY uidx_original_source_path (original_source_path(190), id),
				UNIQUE KEY uidx_source (source_type, source_id),
				UNIQUE KEY uidx_provider_bucket (provider, bucket(190), id),
				UNIQUE KEY uidx_is_verified_originator (is_verified, originator, id)
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
	public function key_values( $include_id = false ) {
		$key_values = array(
			'provider'             => $this->provider,
			'region'               => $this->region,
			'bucket'               => $this->bucket,
			'path'                 => $this->path,
			'original_path'        => $this->original_path,
			'is_private'           => $this->is_private,
			'source_type'          => static::$source_type,
			'source_id'            => $this->source_id,
			'source_path'          => $this->source_path,
			'original_source_path' => $this->original_source_path,
			'extra_info'           => serialize( $this->extra_info ),
			'originator'           => $this->originator,
			'is_verified'          => $this->is_verified,
		);

		if ( $include_id && ! empty( $this->id ) ) {
			$key_values['id'] = $this->id;
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
	private function key_formats( $include_id = false ) {
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
		);

		if ( $include_id && ! empty( $this->id ) ) {
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
	private function formats( $include_id = false ) {
		return array_values( $this->key_formats( $include_id ) );
	}

	/**
	 * Save the item's current data.
	 *
	 * @param bool $update_duplicates If updating, also update records for duplicated source, defaults to true.
	 *
	 * @return int|WP_Error
	 */
	public function save( $update_duplicates = true ) {
		global $wpdb;

		$update = false;

		if ( empty( $this->id ) ) {
			$result = $wpdb->insert( static::items_table(), $this->key_values(), $this->formats() );

			if ( $result ) {
				$this->id = $wpdb->insert_id;

				// Now that the item has an ID it should be (re)cached.
				static::add_to_items_cache( $this );
			}
		} else {
			$update = true;

			// Make sure object cache does not have stale items.
			$old_item = static::get_from_object_cache( 'id', $this->id() );
			static::remove_from_object_cache( $old_item );
			unset( $old_item );

			$result = $wpdb->update( static::items_table(), $this->key_values(), array( 'id' => $this->id ), $this->formats(), array( '%d' ) );
		}

		if ( false !== $result ) {
			// Now that the item has an ID it should be (re)cached.
			static::add_to_object_cache( $this );
		} else {
			static::remove_from_items_cache( $this );

			return new WP_Error( 'item_save', 'Error saving item:- ' . $wpdb->last_error );
		}

		// If one or more duplicate exists that still has the same source paths, keep them in step.
		if ( $update && $update_duplicates ) {
			$duplicates = static::get_by_source_path( array( $this->source_path, $this->original_source_path ), $this->source_id );

			if ( ! empty( $duplicates ) && ! is_wp_error( $duplicates ) ) {
				/** @var Item $duplicate */
				foreach ( $duplicates as $duplicate ) {
					if (
						! is_wp_error( $duplicate ) &&
						$duplicate->source_type() === $this->source_type() &&
						$duplicate->source_path() === $this->source_path() &&
						$duplicate->original_source_path() === $this->original_source_path()
					) {
						$duplicate->provider      = $this->provider;
						$duplicate->region        = $this->region;
						$duplicate->bucket        = $this->bucket;
						$duplicate->path          = $this->path;
						$duplicate->original_path = $this->original_path;
						$duplicate->is_private    = $this->is_private;
						$duplicate->extra_info    = $this->extra_info;
						$duplicate->originator    = $this->originator;
						$duplicate->is_verified   = $this->is_verified;
						$duplicate->save( false );
					}
				}
			}
		}

		return $this->id;
	}

	/**
	 * Delete the current item.
	 *
	 * @return bool|WP_Error
	 */
	public function delete() {
		global $wpdb;

		static::remove_from_items_cache( $this );
		static::remove_from_object_cache( $this );

		if ( empty( $this->id ) ) {
			return new WP_Error( 'item_delete', 'Error trying to delete item with no id.' );
		} else {
			$result = $wpdb->delete( static::items_table(), array( 'id' => $this->id ), array( '%d' ) );
		}

		if ( ! $result ) {
			return new WP_Error( 'item_delete', 'Error deleting item:- ' . $wpdb->last_error );
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
	protected static function create( $object, $add_to_object_cache = false ) {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		$extra_info = array();

		if ( ! empty( $object->extra_info ) ) {
			$extra_info = unserialize( $object->extra_info );
			static::maybe_update_extra_info( $extra_info, $object->source_id, $object->is_private );
		}

		if ( ! empty( static::$source_type ) && static::$source_type !== $object->source_type ) {
			AS3CF_Error::log( sprintf( 'Doing it wrong! Trying to create a %s class instance with data representing a %s', __CLASS__, $object->source_type ) );
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
	public static function get_by_id( $id ) {
		global $wpdb;

		if ( empty( $id ) ) {
			return false;
		}

		$item = static::get_from_items_cache_by_id( $id );

		if ( ! empty( $item ) ) {
			return $item;
		}

		$sql = $wpdb->prepare( "SELECT * FROM " . static::items_table() . " WHERE source_type = %s AND id = %d", static::$source_type, $id );

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
	 * @return bool|Item
	 */
	public static function get_by_source_id( $source_id ) {
		global $wpdb;

		if ( ! is_numeric( $source_id ) ) {
			return false;
		}

		$source_id = (int) $source_id;

		if ( $source_id < 0 ) {
			return false;
		}

		$item = static::get_from_items_cache_by_source_id( $source_id );

		if ( ! empty( $item ) && ! empty( $item->id() ) ) {
			return $item;
		}

		$sql = $wpdb->prepare( "SELECT * FROM " . static::items_table() . " WHERE source_id = %d AND source_type = %s", $source_id, static::$source_type );

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
	public static function source_type() {
		return static::$source_type;
	}

	/**
	 * Getter for item's source type name.
	 *
	 * @return string
	 */
	public static function source_type_name() {
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
	 * @return integer
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Getter for item's provider value.
	 *
	 * @return string
	 */
	public function provider() {
		return $this->provider;
	}

	/**
	 * Getter for item's region value.
	 *
	 * @return string
	 */
	public function region() {
		return $this->region;
	}

	/**
	 * Setter for item's region value.
	 *
	 * @param string $region
	 */
	public function set_region( $region ) {
		$this->region = $region;
	}

	/**
	 * Getter for item's bucket value.
	 *
	 * @return string
	 */
	public function bucket() {
		return $this->bucket;
	}

	/**
	 * Setter for item's bucket value.
	 *
	 * @param string $bucket
	 */
	public function set_bucket( $bucket ) {
		$this->bucket = $bucket;
	}

	/**
	 * Getter for item's path value.
	 *
	 * The path is always the public representation,
	 * see provider_key() and provider_keys() for realised versions.
	 *
	 * @param string $object_key
	 *
	 * @return string
	 */
	public function path( $object_key = null ) {
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
	 */
	public function set_path( $path ) {
		$this->path = $path;
	}

	/**
	 * Getter for item's original_path value.
	 *
	 * @return string
	 */
	public function original_path() {
		return $this->original_path;
	}

	/**
	 * Setter for item's original path value.
	 *
	 * @param string $path
	 */
	public function set_original_path( $path ) {
		$this->original_path = $path;
	}

	/**
	 * Getter for item's is_private value.
	 *
	 * @param string|null $object_key
	 *
	 * @return bool
	 */
	public function is_private( $object_key = null ) {
		if ( ! empty( $object_key ) ) {
			$objects = $this->objects();
			if ( isset( $objects[ $object_key ]['is_private'] ) ) {
				return (bool) $objects[ $object_key ]['is_private'];
			}

			return false;
		}

		return (bool) $this->is_private;
	}

	/**
	 * Setter for item's is_private value
	 *
	 * @param bool        $private
	 * @param string|null $object_key
	 */
	public function set_is_private( $private, $object_key = null ) {
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

		$this->set_is_private( $private, self::primary_object_key() );
	}

	/**
	 * Any private objects in this item
	 *
	 * @return bool
	 */
	public function has_private_objects() {
		foreach ( $this->objects() as $object ) {
			if ( $object['is_private'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Getter for the item prefix
	 *
	 * @return string
	 */
	public function prefix() {
		$dirname = dirname( $this->path );
		$dirname = $dirname === '.' ? '' : $dirname;

		return AS3CF_Utils::trailingslash_prefix( $dirname );
	}

	/**
	 * Get the private prefix for item's private objects.
	 *
	 * @return string
	 */
	public function private_prefix() {
		$extra_info = $this->extra_info();

		if ( ! empty( $extra_info['private_prefix'] ) ) {
			return AS3CF_Utils::trailingslash_prefix( $extra_info['private_prefix'] );
		}

		return '';
	}

	/**
	 * Setter for the private prefix
	 *
	 * @param string $new_private_prefix
	 */
	public function set_private_prefix( $new_private_prefix ) {
		$extra_info                   = $this->extra_info();
		$extra_info['private_prefix'] = AS3CF_Utils::trailingslash_prefix( $new_private_prefix );
		$this->set_extra_info( $extra_info );
	}

	/**
	 * Get the full remote key for this item including private prefix when needed
	 *
	 * @param string|null $object_key
	 *
	 * @return string
	 */
	public function provider_key( $object_key = null ) {
		$path = $this->path( $object_key );
		if ( $this->is_private( $object_key ) ) {
			$path = $this->private_prefix() . $path;
		}

		return $path;
	}

	/**
	 * Returns an associative array of provider keys by their object_key.
	 *
	 * NOTE: There may be duplicate keys if object_keys reference same source file/object.
	 *
	 * @return array
	 */
	public function provider_keys() {
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
	 */
	public function provider_key_for_filename( $filename, $is_private ) {
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
	public function source_id() {
		return $this->source_id;
	}

	/**
	 * Getter for item's source_path value.
	 *
	 * @param string|null $object_key
	 *
	 * @return string
	 */
	public function source_path( $object_key = null ) {
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
	 * Setter for item's source_path value
	 *
	 * @param string $new_path
	 */
	public function set_source_path( $new_path ) {
		$this->source_path = $new_path;
	}

	/**
	 * Getter for item's original_source_path value.
	 *
	 * @return string
	 */
	public function original_source_path() {
		return $this->original_source_path;
	}

	/**
	 * Setter for item's original_source_path value
	 *
	 * @param string $new_path
	 */
	public function set_original_source_path( $new_path ) {
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
	public function full_source_path( $object_key = null ) {
		/**
		 * Filter the absolute directory path prefix for an item's source files.
		 *
		 * @param string $basedir    Default is WordPress uploads folder.
		 * @param Item   $as3cf_item The Item whose full source path is being accessed.
		 */
		$basedir = trailingslashit( apply_filters( 'as3cf_item_basedir', wp_upload_dir()['basedir'], $this ) );

		return $basedir . $this->source_path( $object_key );
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
	 */
	public function full_source_path_for_filename( $filename ) {
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

		return $basedir . str_replace( wp_basename( $this->source_path ), wp_basename( trim( $filename ) ), $this->source_path );
	}

	/**
	 * Getter for item's extra_info value.
	 *
	 * @return array
	 */
	public function extra_info() {
		return $this->extra_info;
	}

	/**
	 * Setter for extra_info value.
	 *
	 * @param array $extra_info
	 */
	public function set_extra_info( $extra_info ) {
		$this->extra_info = $extra_info;
	}

	/**
	 * Getter for item's originator value.
	 *
	 * @return integer
	 */
	public function originator() {
		return $this->originator;
	}

	/**
	 * Setter for item's originator value.
	 *
	 * @param int $originator
	 */
	public function set_originator( $originator ) {
		$this->originator = $originator;
	}

	/**
	 * Getter for item's is_verified value.
	 *
	 * @return bool
	 */
	public function is_verified() {
		return (bool) $this->is_verified;
	}

	/**
	 * Setter for item's is_verified value.
	 *
	 * @param bool $is_verified
	 */
	public function set_is_verified( $is_verified ) {
		$this->is_verified = (bool) $is_verified;
	}

	/**
	 * Does this item type use object versioning?
	 *
	 * @return bool
	 */
	public static function can_use_object_versioning() {
		return static::CAN_USE_OBJECT_VERSIONING;
	}

	/**
	 * Get normalized object path dir.
	 *
	 * @return string
	 */
	public function normalized_path_dir() {
		$directory = dirname( $this->path );

		return ( '.' === $directory ) ? '' : AS3CF_Utils::trailingslash_prefix( $directory );
	}

	/**
	 * Get the first source id for a bucket and path.
	 *
	 * @param string $bucket
	 * @param string $path
	 *
	 * @return int|bool
	 */
	public static function get_source_id_by_bucket_and_path( $bucket, $path ) {
		global $wpdb;

		if ( empty( $bucket ) || empty( $path ) ) {
			return false;
		}

		$item = static::get_from_items_cache_by_bucket_and_path( $bucket, $path );

		if ( ! empty( $item ) ) {
			return $item->source_id();
		}

		$sql = $wpdb->prepare(
			"
				SELECT source_id FROM " . static::items_table() . "
				WHERE source_type = %s
				AND bucket = %s
				AND (path = %s OR original_path = %s)
				ORDER BY source_id LIMIT 1
			",
			static::$source_type,
			$bucket,
			$path,
			$path
		);

		$result = $wpdb->get_var( $sql );

		return empty( $result ) ? false : (int) $result;
	}

	/**
	 * Get the source id for a given remote URL.
	 *
	 * @param string $url
	 *
	 * @return array|bool
	 */
	public static function get_item_source_by_remote_url( $url ) {
		global $wpdb;

		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		if ( ! AS3CF_Utils::usable_url( $url ) ) {
			return false;
		}

		$parts = AS3CF_Utils::parse_url( $url );
		$path  = AS3CF_Utils::decode_filename_in_path( ltrim( $parts['path'], '/' ) );

		// Remove the first directory to cater for bucket in path domain settings.
		if ( false !== strpos( $path, '/' ) ) {
			$path = explode( '/', $path );
			array_shift( $path );

			// If private prefix enabled, check if first segment and remove it as path/original_path do not include it.
			// We can't check every possible private prefix as each item may have a unique private prefix.
			// The only way to do that is with some fancy SQL, but that's not feasible as this particular
			// SQL query is already troublesome on some sites with badly behaved themes/plugins.
			if ( count( $path ) && $as3cf->get_delivery_provider()->use_signed_urls_key_file() ) {
				// We have to be able to handle multi-segment private prefixes such as "private/downloads/".
				$private_prefixes = explode( '/', untrailingslashit( $as3cf->get_setting( 'signed-urls-object-prefix' ) ) );

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

		$sql = $wpdb->prepare(
			"SELECT * FROM " . static::items_table() . " WHERE (path LIKE %s OR original_path LIKE %s);",
			'%' . $path,
			'%' . $path
		);

		$results = $wpdb->get_results( $sql );

		// Nothing found, shortcut out.
		if ( 0 === count( $results ) ) {
			// TODO: If upgrade in progress, fallback to 'amazonS3_info' in Media_Library_Item override of this function.
			return false;
		}

		// Regardless of whether 1 or many items found, must validate match.
		$path = AS3CF_Utils::decode_filename_in_path( ltrim( $parts['path'], '/' ) );

		foreach ( $results as $result ) {
			/** @var Item $class */
			$class      = $as3cf->get_source_type_class( $result->source_type );
			$as3cf_item = $class::create( $result );

			// If item's bucket matches first segment of URL path, remove it from URL path before checking match.
			if ( 0 === strpos( $path, trailingslashit( $as3cf_item->bucket() ) ) ) {
				$match_path = ltrim( substr_replace( $path, '', 0, strlen( $as3cf_item->bucket() ) ), '/' );
			} else {
				$match_path = $path;
			}

			// If item's private prefix matches first segment of URL path, remove it from URL path before checking match.
			if ( ! empty( $as3cf_item->private_prefix() ) && 0 === strpos( $match_path, $as3cf_item->private_prefix() ) ) {
				$match_path = ltrim( substr_replace( $match_path, '', 0, strlen( $as3cf_item->private_prefix() ) ), '/' );
			}

			// Exact match, return ID.
			if ( $as3cf_item->path() === $match_path || $as3cf_item->original_path() === $match_path ) {
				return array(
					'id'          => $as3cf_item->source_id(),
					'source_type' => $as3cf_item->source_type(),
				);
			}
		}

		return false;
	}

	/**
	 * Get an array of managed source_ids in descending order.
	 *
	 * While source id isn't strictly unique, it is by source type, which is always used in queries based on called class.
	 *
	 * @param int  $upper_bound Returned source_ids should be lower than this, use null for no upper bound.
	 * @param int  $limit       Maximum number of source_ids to return. Required if not counting.
	 * @param bool $count       Just return a count of matching source_ids? Negates $limit, default false.
	 * @param int  $originator  Optionally restrict to only records with given originator type from ORIGINATORS const.
	 * @param bool $is_verified Optionally restrict to only records that either are or are not verified.
	 *
	 * @return array|int
	 */
	public static function get_source_ids( $upper_bound, $limit, $count = false, $originator = null, $is_verified = null ) {
		global $wpdb;

		if ( $count ) {
			$sql = 'SELECT COUNT(DISTINCT source_id)';
		} else {
			$sql = 'SELECT DISTINCT source_id';
		}

		$sql  .= ' FROM ' . static::items_table() . ' WHERE source_type = %s';
		$args = array( static::$source_type );

		if ( is_numeric( $upper_bound ) ) {
			$sql    .= ' AND source_id < %d';
			$args[] = $upper_bound;
		}

		// If an originator type given, check that it is valid before continuing and using.
		if ( null !== $originator ) {
			if ( is_int( $originator ) && in_array( $originator, self::ORIGINATORS ) ) {
				$sql    .= ' AND originator = %d';
				$args[] = $originator;
			} else {
				AS3CF_Error::log( __METHOD__ . ' called with invalid originator: ' . $originator );

				return $count ? 0 : array();
			}
		}

		// If an is_verified value given, check that it is valid before continuing and using.
		if ( null !== $is_verified ) {
			if ( is_bool( $is_verified ) ) {
				$sql    .= ' AND is_verified = %d';
				$args[] = (int) $is_verified;
			} else {
				AS3CF_Error::log( __METHOD__ . ' called with invalid is_verified: ' . $is_verified );

				return $count ? 0 : array();
			}
		}

		if ( ! $count ) {
			$sql    .= ' ORDER BY source_id DESC LIMIT %d';
			$args[] = $limit;
		}

		$sql = $wpdb->prepare( $sql, $args );

		if ( $count ) {
			return $wpdb->get_var( $sql );
		} else {
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
	 * Get array of objects (i.e. different sizes of same attachment item)
	 *
	 * @return array
	 */
	public function objects() {
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
	 * Set array of objects (i.e. different sizes of same attachment item)
	 *
	 * @param array $objects
	 */
	public function set_objects( $objects ) {
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
	 */
	public function item_data_for_acl_filter() {
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
	public static function create_from_source_id( $source_id, $options = array() ) {
		return new WP_Error(
			'exception',
			sprintf( 'Doing it wrong! Trying to create a base %s class instance from source ID %d', __CLASS__, $source_id )
		);
	}

	/**
	 * Return a year/month string for the item
	 *
	 * @return string
	 */
	protected function get_item_time() {
		return null;
	}

	/**
	 * Return an additional 'internal' prefix used by some item types
	 *
	 * @return string
	 */
	protected function get_internal_prefix() {
		return '';
	}

	/**
	 * Get item's new public prefix path for current settings.
	 *
	 * @param bool $use_object_versioning
	 *
	 * @return string
	 */
	public function get_new_item_prefix( $use_object_versioning = true ) {
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
	public function get_acl_for_object_key( $object_key, $bucket = null ) {
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
	 * @param array|int    $exclude_source_ids Array of source_ids to exclude from search. Default, none.
	 * @param bool         $exact_match        Use paths as supplied (true, default), or greedy match on path without extension (e.g. find edited too).
	 * @param bool         $first_only         Only return first matched item sorted by source_id. Default false.
	 *
	 * @return array
	 */
	public static function get_by_source_path( $paths, $exclude_source_ids = array(), $exact_match = true, $first_only = false ) {
		global $wpdb;

		if ( ! is_array( $paths ) && is_string( $paths ) && ! empty( $paths ) ) {
			$paths = array( $paths );
		}

		if ( ! is_array( $paths ) || empty( $paths ) ) {
			return array();
		}

		$paths = AS3CF_Utils::make_upload_file_paths_relative( array_unique( $paths ) );

		$sql = '
			SELECT DISTINCT items.*
			FROM ' . static::items_table() . ' AS items USE INDEX (uidx_source_path, uidx_original_source_path)
			WHERE 1=1
		';

		if ( ! empty( $exclude_source_ids ) ) {
			if ( ! is_array( $exclude_source_ids ) ) {
				$exclude_source_ids = array( $exclude_source_ids );
			}

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

		return array_map( static::class . '::create', $wpdb->get_results( $sql ) );
	}

	/**
	 * Update path and original path with a new prefix
	 *
	 * @param string $new_prefix
	 */
	public function update_path_prefix( $new_prefix ) {
		$this->set_path( $new_prefix . wp_basename( $this->path() ) );
		$this->set_original_path( $new_prefix . wp_basename( $this->original_path() ) );
	}

	/**
	 * Returns a link to the items edit page in WordPress
	 *
	 * @param object $error
	 *
	 * @return object|null Null or object containing properties 'url' and 'text'
	 */
	public static function admin_link( $error ) {
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
	public function served_by_provider( $skip_rewrite_check = false, $skip_current_provider_check = false, Storage_Provider $provider = null, $check_is_verified = false ) {
		/** @var Amazon_S3_And_CloudFront $as3cf */
		global $as3cf;

		if ( ! $skip_rewrite_check && ! $as3cf->get_setting( 'serve-from-s3' ) ) {
			// Not serving provider URLs
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
			// File not uploaded to required provider
			return false;
		}

		return true;
	}

	/**
	 * Does the item's files exist locally?
	 *
	 * @return bool
	 */
	public function exists_locally() {
		foreach ( $this->full_source_paths() as $path ) {
			if ( file_exists( $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the provider URL for an item
	 *
	 * @param string|null $object_key
	 * @param int|null    $expires
	 * @param array       $headers
	 *
	 * @return string|WP_Error|bool
	 */
	public function get_provider_url( string $object_key = null, int $expires = null, array $headers = array() ) {
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

			$delivery_domain = $as3cf->get_storage_provider_instance( $this->provider() )->get_url_domain( $this->bucket(), $region, $expires );
		} else {
			$delivery_domain = AS3CF_Utils::sanitize_custom_domain( $delivery_domain );
		}

		if ( ! is_null( $expires ) && $as3cf->is_plugin_setup( true ) ) {
			try {
				/**
				 * Filters the expires time for private content
				 *
				 * @param int $expires The expires time in seconds
				 */
				$timestamp = time() + apply_filters( 'as3cf_expires', $expires );
				$url       = $as3cf->get_delivery_provider()->get_signed_url( $this, $item_path, $delivery_domain, $scheme, $timestamp, $headers );

				/**
				 * Filters the secure URL for private content
				 *
				 * @param string $url         The URL
				 * @param Item   $item        The Item object
				 * @param array  $item_source The item source descriptor array
				 * @param int    $timestamp   Expiry timestamp
				 * @param array  $headers     Optional extra http headers
				 */
				return apply_filters( 'as3cf_get_item_secure_url', $url, $this, $this->get_item_source_array(), $timestamp, $headers );
			} catch ( Exception $e ) {
				return new WP_Error( 'exception', $e->getMessage() );
			}
		} else {
			try {
				$url = $as3cf->get_delivery_provider()->get_url( $this, $item_path, $delivery_domain, $scheme, $headers );

				/**
				 * Filters the URL for public content
				 *
				 * @param string $url         The URL
				 * @param Item   $item        The Item object
				 * @param array  $item_source The item source descriptor array
				 * @param int    $source_id   The source ID of the object
				 * @param int    $timestamp   Expiry timestamp
				 * @param array  $headers     Optional extra http headers
				 */
				return apply_filters( 'as3cf_get_item_url', $url, $this, $this->get_item_source_array(), $expires, $headers );
			} catch ( Exception $e ) {
				return new WP_Error( 'exception', $e->getMessage() );
			}
		}
	}

	/**
	 * Update file sizes after removing local files for an item
	 *
	 * @param int $original_size
	 * @param int $total_size
	 */
	public function update_filesize_after_remove_local( $original_size, $total_size ) {
	}

	/**
	 * Cleanup file sizes after getting item files back from the bucket
	 */
	public function update_filesize_after_download_local() {
	}

	/**
	 * If another item in current site shares full size *local* paths, only remove remote files not referenced by duplicates.
	 * We reference local paths as they should be reflected one way or another remotely, including backups.
	 *
	 * @param Item  $as3cf_item
	 * @param array $paths
	 */
	public function remove_duplicate_paths( Item $as3cf_item, $paths ) {
		return $paths;
	}

	/**
	 * Verify that the extra info uses the new format set in plugin version 2.6.0
	 * Update if needed
	 *
	 * @param array $extra_info
	 * @param int   $source_id
	 * @param bool  $is_private
	 *
	 * @since 2.6.0
	 */
	protected static function maybe_update_extra_info( &$extra_info, $source_id, $is_private ) {
		if ( ! is_array( $extra_info ) ) {
			$extra_info = array();
		}

		// Compatibility fallback for if just an array of private sizes is supplied.
		$private_sizes = array();
		if ( ! isset( $extra_info['private_sizes'] ) && ! isset( $extra_info['private_prefix'] ) && ! isset( $extra_info['objects'] ) ) {
			$private_sizes = $extra_info;
		}

		// Compatibility fallback for old broken format.
		if ( isset( $extra_info['private_sizes'] ) && isset( $extra_info['private_sizes']['private_sizes'] ) ) {
			$extra_info['private_sizes'] = $extra_info['private_sizes']['private_sizes'];
		}

		// Extra info must have at least one element, if not it's broken.
		if ( isset( $extra_info['objects'] ) && 0 === count( $extra_info['objects'] ) ) {
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
					'is_private'  => self::primary_object_key() === $object_key ? $is_private : in_array( $object_key, $private_sizes ),
				);

				$extra_info['objects'][ $object_key ] = $new_object;
			}
		}

		if ( isset( $extra_info['private_sizes'] ) ) {
			unset( $extra_info['private_sizes'] );
		}
	}

	/**
	 * Returns the item source description array for this item
	 *
	 * @return array Array with the format:
	 *               array(
	 *                  'id'          => 1,
	 *                  'source_type' => 'foo-type',
	 *               )
	 */
	public function get_item_source_array() {
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
	 */
	public function offloaded_files() {
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
	 * @param array $item_source
	 *
	 * @return bool
	 */
	public static function is_empty_item_source( $item_source ) {
		if (
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
			$min = min( max( 0, (int) apply_filters( 'as3cf_blog_media_counts_timeout_min', $min, $blog_id, static::source_type() ) ), 1440 );
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
			$max = min( max( $min, (int) apply_filters( 'as3cf_blog_media_counts_timeout_max', $max, $blog_id, static::source_type() ) ), 1440 );

			// We lied, our real minimums are min 3 and max 15 seconds
			// to ensure there's at least a tiny bit of caching,
			// which helps combat some potential race conditions,
			// and makes sure the transient has a timeout.
			$min = max( $min, 0.05 );
			$max = max( $max, 0.25 );

			set_site_transient( $transient_key, $result, rand( $min * MINUTE_IN_SECONDS, $max * MINUTE_IN_SECONDS ) );

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
	 * Count total, offloaded and not offloaded items on current site.
	 *
	 * @return array Keys:
	 *               total: Total media count for site (current blog id)
	 *               offloaded: Count of offloaded media for site (current blog id)
	 *               not_offloaded: Difference between total and offloaded
	 */
	abstract protected static function get_item_counts(): array;
}
