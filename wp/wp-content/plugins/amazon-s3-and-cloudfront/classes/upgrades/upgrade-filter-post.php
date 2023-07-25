<?php

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Items\Item;
use DeliciousBrains\WP_Offload_Media\Upgrades\Exceptions\Batch_Limits_Exceeded_Exception;
use DeliciousBrains\WP_Offload_Media\Upgrades\Exceptions\Too_Many_Errors_Exception;

/**
 * Upgrade_Filter_Post Class
 *
 * The base upgrade class for handling find and replace
 * on the posts tables for content filtering.
 *
 * @since 1.3
 */
abstract class Upgrade_Filter_Post extends Upgrade {

	/**
	 * @var int Time limit in seconds.
	 */
	protected $time_limit = 10;

	/**
	 * @var int Batch size limit for this request session.
	 */
	protected $size_limit = 50;

	/**
	 * @var string 'metadata', 'attachment'
	 */
	protected $upgrade_type = 'posts';

	/**
	 * @var string
	 */
	protected $column_name;

	/**
	 * @var int The last post ID used for the bottom range of the item upgrade.
	 */
	protected $last_post_id;

	/**
	 * Get highest post ID.
	 *
	 * @return int
	 */
	protected function get_highest_post_id() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
	}

	/**
	 * Get items to process.
	 *
	 * @param string     $prefix
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	protected function get_items_to_process( $prefix, $limit, $offset = false ) {
		global $wpdb;

		$sql = "SELECT posts.ID FROM `{$prefix}posts` AS posts
		        INNER JOIN `{$prefix}postmeta` AS postmeta
		        ON posts.ID = postmeta.post_id
		        WHERE posts.post_type = 'attachment'
		        AND postmeta.meta_key = 'amazonS3_info'";

		if ( ! empty( $offset ) ) {
			$sql .= " AND posts.ID < '{$offset}'";
		}

		$sql .= " ORDER BY posts.ID DESC";

		if ( $limit && $limit > 0 ) {
			$sql .= sprintf( ' LIMIT %d', (int) $limit );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Switch to the given blog, and update blog-specific state.
	 *
	 * @param int $blog_id
	 */
	protected function switch_to_blog( $blog_id ) {
		parent::switch_to_blog( $blog_id );
		$this->last_post_id = $this->load_last_post_id();
	}

	/**
	 * Mark the current blog upgrade as complete.
	 */
	protected function blog_upgrade_completed() {
		parent::blog_upgrade_completed();
		$this->last_post_id = null;
	}

	/**
	 * Prepare the session to be persisted.
	 */
	protected function close_session() {
		parent::close_session();
		$this->session['last_post_id'] = $this->last_post_id;
	}

	/**
	 * Upgrade item.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 * @throws Batch_Limits_Exceeded_Exception
	 * @throws Too_Many_Errors_Exception
	 */
	protected function upgrade_item( $item ) {
		$limit            = apply_filters( 'as3cf_update_' . $this->upgrade_name . '_sql_limit', 100000 );
		$where_highest_id = $this->last_post_id;
		$where_lowest_id  = max( $where_highest_id - $limit, 0 );

		while ( true ) {
			$this->find_and_replace_attachment_urls( $item->ID, $where_lowest_id, $where_highest_id );

			if ( $where_lowest_id <= 0 ) {
				// Batch completed
				return true;
			}

			$where_highest_id   = $where_lowest_id;
			$where_lowest_id    = max( $where_lowest_id - $limit, 0 );
			$this->last_post_id = $where_lowest_id;

			$this->check_batch_limits();
		}
	}

	/**
	 * Perform any actions necessary after the given item is completed.
	 *
	 * @param mixed $item
	 */
	protected function item_upgrade_completed( $item ) {
		parent::item_upgrade_completed( $item );
		$this->last_item = $item->ID;
	}

	/**
	 * Find and replace embedded URLs for an attachment.
	 *
	 * @param int $attachment_id
	 * @param int $where_lowest_id
	 * @param int $where_highest_id
	 */
	protected function find_and_replace_attachment_urls( $attachment_id, $where_lowest_id, $where_highest_id ) {
		$meta       = wp_get_attachment_metadata( $attachment_id, true );
		$backups    = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$file_path  = get_attached_file( $attachment_id, true );
		$as3cf_item = Item::get_by_source_id( $attachment_id );

		if ( empty( $as3cf_item ) ) {
			return;
		}

		$new_url = $as3cf_item->get_local_url();
		$old_url = $this->as3cf->maybe_remove_query_string( $as3cf_item->get_provider_url() );

		if ( empty( $old_url ) || empty( $new_url ) ) {
			return;
		}

		$urls = $this->get_find_and_replace_urls( $file_path, $old_url, $new_url, $meta, $backups );

		$this->process_pair_replacement( $urls, $where_lowest_id, $where_highest_id );
	}

	/**
	 * Get find and replace URLs.
	 *
	 * @param string       $file_path
	 * @param string       $old_url
	 * @param string       $new_url
	 * @param array        $meta
	 * @param array|string $backups
	 *
	 * @return array
	 */
	protected function get_find_and_replace_urls( $file_path, $old_url, $new_url, $meta, $backups = '' ) {
		$url_pairs     = array();
		$file_name     = wp_basename( $file_path );
		$old_file_name = wp_basename( $old_url );
		$new_file_name = wp_basename( $new_url );

		// Full size image
		$url_pairs[] = $this->add_url_pair( $file_path, $file_name, $old_url, $old_file_name, $new_url, $new_file_name );

		if ( isset( $meta['thumb'] ) && $meta['thumb'] ) {
			// Replace URLs for legacy thumbnail of image
			$url_pairs[] = $this->add_url_pair( $file_path, $file_name, $old_url, $old_file_name, $new_url, $new_file_name, $meta['thumb'] );
		}

		if ( ! empty( $meta['sizes'] ) ) {
			// Replace URLs for intermediate sizes of image
			foreach ( $meta['sizes'] as $key => $size ) {
				if ( ! isset( $size['file'] ) ) {
					continue;
				}

				$url_pairs[] = $this->add_url_pair( $file_path, $file_name, $old_url, $old_file_name, $new_url, $new_file_name, $size['file'] );
			}
		}

		if ( ! empty( $backups ) ) {
			// Replace URLs for backup images
			foreach ( $backups as $backup ) {
				if ( ! isset( $backup['file'] ) ) {
					continue;
				}

				$url_pairs[] = $this->add_url_pair( $file_path, $file_name, $old_url, $old_file_name, $new_url, $new_file_name, $backup['file'] );
			}
		}

		// Also find encoded file names
		$url_pairs = $this->maybe_add_encoded_url_pairs( $url_pairs );

		// Remove URL protocols
		foreach ( $url_pairs as $key => $url_pair ) {
			$url_pairs[ $key ]['old_url'] = AS3CF_Utils::remove_scheme( $url_pair['old_url'] );
			$url_pairs[ $key ]['new_url'] = AS3CF_Utils::remove_scheme( $url_pair['new_url'] );
		}

		return apply_filters( 'as3cf_update_' . $this->upgrade_name . '_url_pairs', $url_pairs, $file_path, $old_url, $new_url, $meta );
	}

	/**
	 * Add URL pair.
	 *
	 * @param string      $file_path
	 * @param string      $file_name
	 * @param string      $old_url
	 * @param string      $old_file_name
	 * @param string      $new_url
	 * @param string      $new_file_name
	 * @param string|bool $size_file_name
	 *
	 * @return array
	 */
	protected function add_url_pair( $file_path, $file_name, $old_url, $old_file_name, $new_url, $new_file_name, $size_file_name = false ) {
		if ( ! $size_file_name ) {
			return array(
				'old_path' => $file_path,
				'old_url'  => str_replace( $old_file_name, $file_name, $old_url ),
				'new_url'  => $new_url,
			);
		}

		return array(
			'old_path' => str_replace( $file_name, $size_file_name, $file_path ),
			'old_url'  => str_replace( $old_file_name, $size_file_name, $old_url ),
			'new_url'  => str_replace( $new_file_name, $size_file_name, $new_url ),
		);
	}

	/**
	 * Maybe add encoded URL pairs.
	 *
	 * @param array $url_pairs
	 *
	 * @return array
	 */
	protected function maybe_add_encoded_url_pairs( $url_pairs ) {
		foreach ( $url_pairs as $url_pair ) {
			$file_name         = wp_basename( $url_pair['old_url'] );
			$encoded_file_name = AS3CF_Utils::encode_filename_in_path( $file_name );

			if ( $file_name !== $encoded_file_name ) {
				$url_pair['old_url'] = str_replace( $file_name, $encoded_file_name, $url_pair['old_url'] );
				$url_pairs[]         = $url_pair;
			}
		}

		return $url_pairs;
	}

	/**
	 * Perform the find and replace in the database of old and new URLs.
	 *
	 * @param array $url_pairs
	 * @param int   $where_lowest_id
	 * @param int   $where_highest_id
	 */
	protected function process_pair_replacement( $url_pairs, $where_lowest_id, $where_highest_id ) {
		global $wpdb;

		$posts = $wpdb->get_results( $this->generate_select_sql( $url_pairs, $where_lowest_id, $where_highest_id ) );

		if ( empty( $posts ) ) {
			// Nothing to process, move on
			return;
		}

		// Limit REPLACE statements to 10 per query and INTO to 100 per query
		$url_pairs = array_chunk( $url_pairs, 10 );
		$ids       = array_chunk( wp_list_pluck( $posts, 'ID' ), 100 );

		foreach ( $url_pairs as $url_pairs_chunk ) {
			foreach ( $ids as $ids_chunk ) {
				$wpdb->query( $this->generate_update_sql( $url_pairs_chunk, $ids_chunk ) );
			}
		}
	}

	/**
	 * Generate select SQL.
	 *
	 * @param array $url_pairs
	 * @param int   $where_lowest_id
	 * @param int   $where_highest_id
	 *
	 * @return string
	 */
	protected function generate_select_sql( $url_pairs, $where_lowest_id, $where_highest_id ) {
		global $wpdb;

		$paths = array();

		// Get unique URLs without size string and extension
		foreach ( $url_pairs as $url_pair ) {
			$paths[] = AS3CF_Utils::remove_size_from_filename( $url_pair['old_url'], true );
		}

		$paths = array_unique( $paths );
		$sql   = '';

		foreach ( $paths as $path ) {
			if ( ! empty( $sql ) ) {
				$sql .= " OR ";
			}

			$sql .= "{$this->column_name} LIKE '%{$path}%'";
		}

		return "SELECT ID FROM {$wpdb->posts} WHERE ID > {$where_lowest_id} AND ID <= {$where_highest_id} AND ({$sql})";
	}

	/**
	 * Generate update SQL.
	 *
	 * @param array $url_pairs
	 * @param array $ids
	 *
	 * @return string
	 */
	protected function generate_update_sql( $url_pairs, $ids ) {
		global $wpdb;

		$ids = implode( ',', $ids );
		$sql = '';

		foreach ( $url_pairs as $pair ) {
			if ( ! isset( $pair['old_url'] ) || ! isset( $pair['new_url'] ) ) {
				// We need both URLs for the find and replace
				continue;
			}

			if ( empty( $sql ) ) {
				// First replace statement
				$sql = "REPLACE({$this->column_name}, '{$pair['old_url']}', '{$pair['new_url']}')";
			} else {
				// Nested replace statement
				$sql = "REPLACE({$sql}, '{$pair['old_url']}', '{$pair['new_url']}')";
			}
		}

		return "UPDATE {$wpdb->posts} SET `{$this->column_name}` = {$sql} WHERE `ID` IN({$ids})";
	}

	/**
	 * Get paused message.
	 *
	 * @return string
	 */
	protected function get_paused_message() {
		return sprintf( __( '<strong>Paused Upgrade</strong><br>The find &amp; replace to update URLs has been paused. %s', 'amazon-s3-and-cloudfront' ), $this->get_generic_message() );
	}

	/**
	 * Get notice message.
	 *
	 * @return string
	 */
	protected function get_generic_message() {
		$link_text = __( 'See our documentation', 'amazon-s3-and-cloudfront' );
		$url       = $this->as3cf->dbrains_url( '/wp-offload-media/doc/content-filtering-upgrade', array(
			'utm_campaign' => 'support+docs',
		) );
		$link      = AS3CF_Utils::dbrains_link( $url, $link_text );

		return sprintf( __( '%s for details on why we&#8217;re doing this, why it runs slowly, and how to make it run faster.', 'amazon-s3-and-cloudfront' ), $link );
	}

	/**
	 * Load the last blog ID from the session.
	 *
	 * If the ID is found using the standard session key, use that.
	 * Otherwise if it is an older session, derive the ID from the blogs in the session.
	 *
	 * @return bool|int|mixed
	 */
	protected function load_last_blog_id() {
		if ( $blog_id = parent::load_last_blog_id() ) {
			return $blog_id;
		}

		$blog_ids = $this->load_processesed_blog_ids();

		return end( $blog_ids );
	}

	/**
	 * Get all of the processed blog IDs from the session.
	 *
	 * @return array
	 */
	protected function load_processesed_blog_ids() {
		if ( $ids = parent::load_processesed_blog_ids() ) {
			return $ids;
		}

		if ( isset( $this->session['blogs'] ) && is_array( $this->session['blogs'] ) ) {
			return array_keys( $this->session['blogs'] );
		}

		return array();
	}

	/**
	 * Populate the last post ID.
	 *
	 * The last post ID is set from the session if set,
	 * otherwise it defaults to the highest post ID on the site.
	 *
	 * @return int Post ID.
	 */
	protected function load_last_post_id() {
		if ( isset( $this->session['last_post_id'] ) ) {
			return (int) $this->session['last_post_id'];
		}

		return $this->get_highest_post_id();
	}
}
