<?php

use DeliciousBrains\WP_Offload_Media\Items\Item;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;

abstract class AS3CF_Filter {

	/**
	 * The key used for storing the URL cache.
	 */
	const CACHE_KEY = 'amazonS3_cache';

	/**
	 * The cache group used by an external object cache for posts.
	 */
	const POST_CACHE_GROUP = 'post_amazonS3_cache';

	/**
	 * The cache group used by an external object cache for options.
	 */
	const OPTION_CACHE_GROUP = 'option_amazonS3_cache';

	/**
	 * @var array IDs which have already been purged this request.
	 */
	private $purged_ids = array();

	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	protected $as3cf;
	/**
	 * @var array
	 */
	protected $query_cache = array();

	/**
	 * Constructor
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf
	 */
	public function __construct( $as3cf ) {
		$this->as3cf = $as3cf;

		$this->init();
	}

	/**
	 * Initialize the filter handler.
	 */
	protected function init() {
		add_action( 'as3cf_setup', array( $this, 'setup' ) );
	}

	/**
	 * Set up the filter handler.
	 */
	public function setup() {
		// Purge on attachment delete.
		add_action( 'delete_attachment', array( $this, 'purge_cache_on_attachment_delete' ) );
	}

	/**
	 * Filter EDD download files.
	 *
	 * @param array $value
	 *
	 * @return array
	 */
	public function filter_edd_download_files( $value ) {
		if ( ! $this->should_filter_content() ) {
			// Not filtering content, return
			return $value;
		}

		if ( empty( $value ) ) {
			// Nothing to filter, return
			return $value;
		}

		foreach ( $value as $key => $attachment ) {
			$item_source = array(
				'id'          => $attachment['attachment_id'],
				'source_type' => Media_Library_Item::source_type(),
			);
			$url         = $this->get_url( $item_source );

			if ( $url ) {
				$value[ $key ]['file'] = $url;
			}
		}

		return $value;
	}

	/**
	 * Filter customizer image.
	 *
	 * @param string      $value
	 * @param bool|string $old_value
	 *
	 * @return string
	 */
	public function filter_customizer_image( $value, $old_value = false ) {
		if ( empty( $value ) || is_a( $value, 'stdClass' ) ) {
			return $value;
		}

		$cache    = $this->get_option_cache();
		$to_cache = array();
		$value    = $this->process_content( $value, $cache, $to_cache );

		$this->maybe_update_option_cache( $to_cache );

		return $value;
	}

	/**
	 * Filter header image data.
	 *
	 * @param stdClass      $value
	 * @param bool|stdClass $old_value
	 *
	 * @return stdClass
	 */
	public function filter_header_image_data( $value, $old_value = false ) {
		$item_source = array(
			'id'          => $value->attachment_id,
			'source_type' => Media_Library_Item::source_type(),
		);
		$url         = $this->get_url( $item_source );

		if ( $url ) {
			$value->url           = $url;
			$value->thumbnail_url = $url;
		}

		return $value;
	}

	/**
	 * Filter post.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function filter_post( $content ) {
		if ( empty( $content ) ) {
			// Nothing to filter, continue
			return $content;
		}

		$cache    = $this->get_post_cache();
		$to_cache = array();
		$content  = $this->process_content( $content, $cache, $to_cache );

		$this->maybe_update_post_cache( $to_cache );

		return $content;
	}

	/**
	 * Handle widget instances.
	 *
	 * @param array $instance
	 *
	 * @return array
	 */
	protected function handle_widget( $instance ) {
		if ( empty( $instance ) || ! is_array( $instance ) ) {
			return $instance;
		}

		$cache        = $this->get_option_cache();
		$to_cache     = array();
		$update_cache = true;

		// Editing widgets in Customizer throws an error if more than one option record is updated.
		// Therefore cache updating has to wait until render or edit via Appearance menu.
		if ( isset( $_POST['wp_customize'] ) && 'on' === $_POST['wp_customize'] ) {
			$update_cache = false;
		}

		foreach ( $instance as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			if ( in_array( $key, array( 'text', 'content' ) ) || AS3CF_Utils::is_url( $value ) ) {
				$instance[ $key ] = $this->process_content( $value, $cache, $to_cache );
			}
		}

		if ( $update_cache ) {
			$this->maybe_update_option_cache( $to_cache );
		}

		return $instance;
	}

	/**
	 * Process content.
	 *
	 * @param string $content
	 * @param array  $cache
	 * @param array  $to_cache
	 *
	 * @return mixed
	 */
	protected function process_content( $content, $cache, &$to_cache ) {
		if ( empty( $content ) ) {
			// Nothing to filter, return
			return $content;
		}

		if ( ! $this->should_filter_content() ) {
			// Not filtering content, return
			return $content;
		}

		$content = $this->pre_replace_content( $content );

		// Find URLs from img src
		$url_pairs = $this->get_urls_from_img_src( $content, $to_cache );
		$content   = $this->replace_urls( $content, $url_pairs );

		// Find leftover URLs
		$content = $this->find_urls_and_replace( $content, $cache, $to_cache );

		// Perform post processing if required
		$content = $this->post_process_content( $content );

		return $content;
	}

	/**
	 * Find URLs and replace.
	 *
	 * @param string $value
	 * @param array  $cache
	 * @param array  $to_cache
	 *
	 * @return string
	 */
	protected function find_urls_and_replace( $value, $cache, &$to_cache ) {
		if ( ! $this->should_filter_content() ) {
			// Not filtering content, return
			return $value;
		}

		$url_pairs = $this->get_urls_from_content( $value, $cache, $to_cache );
		$value     = $this->replace_urls( $value, $url_pairs );

		return $value;
	}

	/**
	 * Get URLs from img src.
	 *
	 * @param string $content
	 * @param array  $to_cache
	 *
	 * @return array
	 */
	protected function get_urls_from_img_src( $content, &$to_cache ) {
		$url_pairs = array();

		if ( ! is_string( $content ) ) {
			return $url_pairs;
		}

		if ( ! preg_match_all( '/<img [^>]+>/', $content, $matches ) || ! isset( $matches[0] ) ) {
			// No img tags found, return
			return $url_pairs;
		}

		$matches      = array_unique( $matches[0] );
		$item_sources = array();

		foreach ( $matches as $image ) {
			if ( ! preg_match( '/wp-image-([0-9]+)/i', $image, $class_id ) || ! isset( $class_id[1] ) ) {
				// Can't determine ID from class, skip
				continue;
			}

			if ( ! preg_match( '/src=\\\?["\']+([^"\'\\\]+)/', $image, $src ) || ! isset( $src[1] ) ) {
				// Can't determine URL, skip
				continue;
			}

			$url = $src[1];

			if ( ! AS3CF_Utils::usable_url( $url ) ) {
				continue;
			}

			if ( ! $this->url_needs_replacing( $url ) ) {
				// URL already correct, skip
				continue;
			}

			$bare_url = AS3CF_Utils::reduce_url( $url );

			$item_sources[ $bare_url ] = array(
				'id'          => absint( $class_id[1] ),
				'source_type' => Media_Library_Item::source_type(),
			);
		}

		if ( count( $item_sources ) > 1 ) {
			/*
			 * Warm object cache for use with 'get_post_meta()'.
			 *
			 * To avoid making a database call for each image, a single query
			 * warms the object cache with the meta information for all images.
			 */
			update_meta_cache( 'post', array_unique( array_column( $item_sources, 'id' ) ) );
		}

		foreach ( $item_sources as $url => $item_source ) {
			if ( ! $this->item_matches_src( $item_source, $url ) ) {
				// Path doesn't match attachment, skip
				continue;
			}

			$this->push_to_url_pairs( $url_pairs, $item_source, $url, $to_cache );
		}

		return $url_pairs;
	}

	/**
	 * Get URLs from content.
	 *
	 * @param string $content
	 * @param array  $cache
	 * @param array  $to_cache
	 *
	 * @return array
	 */
	protected function get_urls_from_content( $content, $cache, &$to_cache ) {
		$url_pairs = array();

		if ( ! is_string( $content ) ) {
			return $url_pairs;
		}

		if ( ! preg_match_all( '/(http|https)?:?\/\/[^"\'\s<>()\\\]*/', $content, $matches ) || ! isset( $matches[0] ) ) {
			// No URLs found, return
			return $url_pairs;
		}

		$matches = array_unique( $matches[0] );
		$urls    = array();

		foreach ( $matches as $url ) {
			$url = preg_replace( '/[^a-zA-Z0-9]$/', '', $url );

			if ( ! AS3CF_Utils::usable_url( $url ) ) {
				continue;
			}

			if ( ! $this->url_needs_replacing( $url ) ) {
				// URL already correct, skip
				continue;
			}

			$item_source = null;
			$bare_url    = AS3CF_Utils::reduce_url( $url );

			// If attachment ID recently or previously cached, skip full search.
			if ( isset( $to_cache[ $bare_url ] ) ) {
				$item_source = $to_cache[ $bare_url ];

				if ( $this->is_failure( $item_source ) ) {
					// Attachment ID failure, continue
					continue;
				}
			} elseif ( isset( $cache[ $bare_url ] ) ) {
				$item_source = $cache[ $bare_url ];

				if ( $this->is_failure( $item_source ) ) {
					// Attachment ID failure, continue
					continue;
				}
			}

			if ( is_null( $item_source ) || ( is_array( $item_source ) && ! empty( $item_source['timestamp'] ) ) ) {
				// Attachment ID not cached, need to search by URL.
				$urls[] = $bare_url;
			} else {
				$this->push_to_url_pairs( $url_pairs, $item_source, $bare_url, $to_cache );
			}
		}

		if ( ! empty( $urls ) ) {
			$item_sources = $this->get_item_sources_from_urls( $urls );

			foreach ( $item_sources as $url => $item_source ) {
				if ( ! $item_source ) {
					// Can't determine item ID, continue
					$this->url_cache_failure( $url, $to_cache );

					continue;
				}

				$this->push_to_url_pairs( $url_pairs, $item_source, $url, $to_cache );
			}
		}

		return $url_pairs;
	}

	/**
	 * Is failure?
	 *
	 * @param array $value
	 *
	 * @return bool
	 */
	protected function is_failure( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['timestamp'] ) ) {
			return false;
		}

		$expires = time() - ( 15 * MINUTE_IN_SECONDS );

		if ( $expires >= $value['timestamp'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Does attachment ID match src?
	 *
	 * @param array  $item_source
	 * @param string $url
	 *
	 * @return bool
	 */
	public function item_matches_src( array $item_source, string $url ): bool {
		if (
			Item::is_empty_item_source( $item_source ) ||
			Media_Library_Item::source_type() !== $item_source['source_type'] ||
			get_post_type( $item_source['id'] ) !== 'attachment'
		) {
			return false;
		}
		$meta = get_post_meta( $item_source['id'], '_wp_attachment_metadata', true );

		if ( ! isset( $meta['sizes'] ) ) {
			// No sizes found, return
			return false;
		}

		$base_url = AS3CF_Utils::encode_filename_in_path( AS3CF_Utils::reduce_url( $this->get_base_url( $item_source ) ) );
		$basename = wp_basename( $base_url );

		// Add full size URL
		$base_urls[] = $base_url;

		// Add additional image size URLs
		foreach ( $meta['sizes'] as $size ) {
			$base_urls[] = str_replace( $basename, AS3CF_Utils::encode_filename_in_path( $size['file'] ), $base_url );
		}

		$url = AS3CF_Utils::encode_filename_in_path( AS3CF_Utils::reduce_url( $url ) );

		if ( in_array( $url, $base_urls ) ) {
			// Match found, return true
			return true;
		}

		return false;
	}

	/**
	 * Push to URL pairs.
	 *
	 * @param array  $url_pairs
	 * @param array  $item_source
	 * @param string $find
	 * @param array  $to_cache
	 */
	protected function push_to_url_pairs( &$url_pairs, $item_source, $find, &$to_cache ) {
		$find_full = AS3CF_Utils::remove_size_from_filename( $find );
		$find_full = $this->normalize_find_value( $this->as3cf->maybe_remove_query_string( $find_full ) );
		$find_size = $this->normalize_find_value( $this->as3cf->maybe_remove_query_string( $find ) );

		// Cache find URLs even if no replacement.
		$to_cache[ $find_full ] = $item_source;

		if ( wp_basename( $find_full ) !== wp_basename( $find_size ) ) {
			$to_cache[ $find_size ] = $item_source;
		}

		$replace_full = $this->get_url( $item_source );

		// Replacement URL can't be found.
		if ( ! $replace_full ) {
			return;
		}

		$size         = $this->get_size_string_from_url( $item_source, $find );
		$replace_size = $this->get_url( $item_source, $size );
		$parts        = parse_url( $find );

		if ( ! isset( $parts['scheme'] ) ) {
			$replace_full = AS3CF_Utils::remove_scheme( $replace_full );
			$replace_size = AS3CF_Utils::remove_scheme( $replace_size );
		}

		// Find and replace full version
		$url_pairs[ $find_full ] = $replace_full;

		// Find and replace sized version
		if ( wp_basename( $find_full ) !== wp_basename( $find_size ) ) {
			$url_pairs[ $find_size ] = $replace_size;
		}

		// Prime cache, when filtering the opposite way
		$replace_full = $this->as3cf->maybe_remove_query_string( $replace_full );
		$replace_size = $this->as3cf->maybe_remove_query_string( $replace_size );

		$to_cache[ $this->normalize_find_value( $replace_full ) ] = $item_source;
		$to_cache[ $this->normalize_find_value( $replace_size ) ] = $item_source;
	}

	/**
	 * Get size string from URL.
	 *
	 * @param array  $item_source
	 * @param string $url
	 *
	 * @return null|string
	 */
	public function get_size_string_from_url( $item_source, $url ) {
		if ( Item::is_empty_item_source( $item_source ) ) {
			return false;
		}

		return apply_filters( 'as3cf_get_size_string_from_url_for_item_source', Item::primary_object_key(), $url, $item_source );
	}

	/**
	 * URL cache failure.
	 *
	 * @param string $url
	 * @param array  $to_cache
	 */
	protected function url_cache_failure( $url, &$to_cache ) {
		$full    = AS3CF_Utils::remove_size_from_filename( $url );
		$failure = array(
			'timestamp' => time(),
		);

		$to_cache[ $full ] = $failure;

		if ( $full !== $url ) {
			$to_cache[ $url ] = $failure;
		}
	}

	/**
	 * Replace URLs.
	 *
	 * @param string $content
	 * @param array  $url_pairs
	 *
	 * @return string
	 */
	protected function replace_urls( $content, $url_pairs ) {
		if ( empty( $url_pairs ) ) {
			// No URLs to replace return
			return $content;
		}

		foreach ( $url_pairs as $find => $replace ) {
			$replace = $this->normalize_replace_value( $replace );
			$content = str_replace( $find, $replace, $content );
			$content = $this->url_replaced( $find, $replace, $content );
		}

		return $content;
	}

	/**
	 * Each time a URL is replaced this function is called to allow for logging or further updates etc.
	 *
	 * @param string $find    URL with no scheme.
	 * @param string $replace URL with no scheme.
	 * @param string $content
	 *
	 * @return string
	 */
	protected function url_replaced( $find, $replace, $content ) {
		return $content;
	}

	/**
	 * Get post cache
	 *
	 * @param null|int|WP_Post $post           Optional. Post ID or post object. Defaults to current post.
	 * @param bool             $transform_ints Optional. If true (default), convert integer hits to array with id and source_type keys. If false, return integer hits as integers
	 *
	 * @return array|int
	 */
	public function get_post_cache( $post = null, $transform_ints = true ) {
		$post_id = AS3CF_Utils::get_post_id( $post );

		if ( ! $post_id ) {
			return array();
		}

		if ( wp_using_ext_object_cache() ) {
			$cache = wp_cache_get( $post_id, self::POST_CACHE_GROUP );
		} else {
			$cache = get_post_meta( $post_id, self::CACHE_KEY, true );
		}

		if ( empty( $cache ) ) {
			$cache = array();
		}

		if ( ! $transform_ints ) {
			return $cache;
		}

		// Handle old cache items that are stored as plain integers
		foreach ( $cache as &$cache_item ) {
			if ( ! is_array( $cache_item ) && is_numeric( $cache_item ) ) {
				$id         = $cache_item;
				$cache_item = array(
					'id'          => $id,
					'source_type' => Media_Library_Item::source_type(),
				);
			}
		}

		return $cache;
	}

	/**
	 * Set the cache for the given post.
	 *
	 * @param null|int|WP_Post $post Optional. Post ID or post object. Defaults to current post.
	 * @param mixed            $data
	 */
	protected function set_post_cache( $post, $data ) {
		$post_id = AS3CF_Utils::get_post_id( $post );

		if ( ! $post_id ) {
			return;
		}

		if ( wp_using_ext_object_cache() ) {
			$expires = apply_filters( 'as3cf_' . self::POST_CACHE_GROUP . '_expires', DAY_IN_SECONDS, $post_id, $data );
			wp_cache_set( $post_id, $data, self::POST_CACHE_GROUP, $expires );
		} else {
			update_post_meta( $post_id, self::CACHE_KEY, $data );
		}
	}

	/**
	 * Set the option cache with the given data.
	 *
	 * @param mixed $data
	 */
	protected function set_option_cache( $data ) {
		if ( wp_using_ext_object_cache() ) {
			$expires = apply_filters( 'as3cf_' . self::OPTION_CACHE_GROUP . '_expires', DAY_IN_SECONDS, self::CACHE_KEY, $data );
			wp_cache_set( self::CACHE_KEY, $data, self::OPTION_CACHE_GROUP, $expires );
		} else {
			update_option( self::CACHE_KEY, $data );
		}
	}

	/**
	 * Maybe update post cache
	 *
	 * @param array    $to_cache
	 * @param bool|int $post_id
	 */
	protected function maybe_update_post_cache( $to_cache, $post_id = false ) {
		$post_id = AS3CF_Utils::get_post_id( $post_id );

		if ( ! $post_id || empty( $to_cache ) ) {
			return;
		}

		$cached = $this->get_post_cache( $post_id, false );
		$urls   = static::merge_cache( $cached, $to_cache );

		if ( $urls !== $cached ) {
			$this->set_post_cache( $post_id, $urls );
		}
	}

	/**
	 * Get option cache.
	 *
	 * @return array
	 */
	protected function get_option_cache() {
		if ( wp_using_ext_object_cache() ) {
			$cache = wp_cache_get( self::CACHE_KEY, self::OPTION_CACHE_GROUP );
		} else {
			$cache = get_option( self::CACHE_KEY, array() );
		}

		if ( empty( $cache ) ) {
			$cache = array();
		}

		return $cache;
	}

	/**
	 * Maybe update option cache.
	 *
	 * @param array $to_cache
	 */
	protected function maybe_update_option_cache( $to_cache ) {
		if ( empty( $to_cache ) ) {
			return;
		}

		$cached = $this->get_option_cache();
		$urls   = static::merge_cache( $cached, $to_cache );

		if ( $urls !== $cached ) {
			$this->set_option_cache( $urls );
		}
	}

	/**
	 * Purge items from cache on delete.
	 *
	 * @param int $post_id
	 */
	public function purge_cache_on_attachment_delete( $post_id ) {
		if ( ! in_array( $post_id, $this->purged_ids ) ) {
			$item_source = array(
				'id'          => $post_id,
				'source_type' => Media_Library_Item::source_type(),
			);
			$this->purge_from_cache( $this->get_url( $item_source ) );
			$this->purged_ids[] = $post_id;
		}
	}

	/**
	 * Purge URL from cache.
	 *
	 * Currently does nothing for purging from an external object cache.
	 * Values are left to expire using the expiration time provided when set.
	 *
	 * @param string   $url
	 * @param bool|int $blog_id
	 */
	public function purge_from_cache( $url, $blog_id = false ) {
		global $wpdb;

		if ( false !== $blog_id ) {
			$this->as3cf->switch_to_blog( $blog_id );
		}

		// Purge postmeta cache
		$sql = $wpdb->prepare( "
 			DELETE FROM {$wpdb->postmeta}
 			WHERE meta_key = %s
 			AND meta_value LIKE %s;
 		", self::CACHE_KEY, '%"' . $url . '"%' );

		$wpdb->query( $sql );

		// Purge option cache
		$sql = $wpdb->prepare( "
 			DELETE FROM {$wpdb->options}
 			WHERE option_name = %s
 			AND option_value LIKE %s;
 		", self::CACHE_KEY, '%"' . $url . '"%' );

		$wpdb->query( $sql );

		if ( false !== $blog_id ) {
			$this->as3cf->restore_current_blog();
		}
	}

	/**
	 * Should filter content.
	 *
	 * @return bool
	 */
	protected function should_filter_content() {
		if ( $this->as3cf->is_plugin_setup() && $this->as3cf->get_setting( 'serve-from-s3' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Remove AWS query strings.
	 *
	 * @param string $content
	 * @param string $base_url Optional base URL that must exist within URL for Amazon query strings to be removed.
	 *
	 * @return string
	 */
	public static function remove_aws_query_strings( $content, $base_url = '' ) {
		$pattern = '\?[^\s"<\?]*(?:X-Amz-Algorithm|AWSAccessKeyId|Key-Pair-Id|GoogleAccessId)=[^\s"<\?]+';
		$group   = 0;

		if ( ! is_string( $content ) ) {
			return $content;
		}

		if ( ! empty( $base_url ) ) {
			$pattern = preg_quote( $base_url, '/' ) . '[^\s"<\?]+(' . $pattern . ')';
			$group   = 1;
		}

		if ( ! preg_match_all( '/' . $pattern . '/', $content, $matches ) || ! isset( $matches[ $group ] ) ) {
			// No query strings found, return
			return $content;
		}

		$matches = array_unique( $matches[ $group ] );

		foreach ( $matches as $match ) {
			$content = str_replace( $match, '', $content );
		}

		return $content;
	}

	/**
	 * Filter custom CSS.
	 *
	 * @param string $css
	 * @param string $stylesheet
	 *
	 * @return string
	 */
	protected function filter_custom_css( $css, $stylesheet ) {
		if ( empty( $css ) ) {
			return $css;
		}

		$post_id  = $this->get_custom_css_post_id( $stylesheet );
		$cache    = $this->get_post_cache( $post_id );
		$to_cache = array();
		$css      = $this->process_content( $css, $cache, $to_cache );

		$this->maybe_update_post_cache( $to_cache, $post_id );

		return $css;
	}

	/**
	 * Merge content filtering cache arrays.
	 *
	 * @param array $existing_cache
	 * @param array $merge_cache
	 *
	 * @return array
	 */
	public static function merge_cache( $existing_cache, $merge_cache ) {
		if ( ! empty( $existing_cache ) ) {
			$post_cache_keys = array_map( 'AS3CF_Utils::reduce_url', array_keys( $existing_cache ) );
			$existing_cache  = array_combine( $post_cache_keys, $existing_cache );
		}

		if ( ! empty( $merge_cache ) ) {
			$add_cache_keys = array_map( 'AS3CF_Utils::reduce_url', array_keys( $merge_cache ) );
			$merge_cache    = array_combine( $add_cache_keys, $merge_cache );
		}

		return array_merge( $existing_cache, $merge_cache );
	}

	/**
	 * Get custom CSS post ID.
	 *
	 * @param string $stylesheet
	 *
	 * @return int
	 */
	protected function get_custom_css_post_id( $stylesheet ) {
		$post = wp_get_custom_css_post( $stylesheet );

		if ( ! $post ) {
			return 0;
		}

		return $post->ID;
	}

	/**
	 * Get an array of bare base_urls that can be used for uploaded items.
	 *
	 * @param bool $refresh Refresh cached domains, default false.
	 *
	 * @return array
	 */
	public function get_bare_upload_base_urls( $refresh = false ) {
		static $base_urls = array();

		if ( $refresh || empty( $base_urls ) ) {
			$domains = array();

			// Original domain and path.
			$uploads     = wp_upload_dir();
			$base_url    = AS3CF_Utils::remove_scheme( $uploads['baseurl'] );
			$orig_domain = AS3CF_Utils::parse_url( $base_url, PHP_URL_HOST );
			$port        = AS3CF_Utils::parse_url( $base_url, PHP_URL_PORT );
			if ( ! empty( $port ) ) {
				$orig_domain .= ':' . $port;
			}

			$domains[] = $orig_domain;
			$base_urls = array( $base_url );

			// Current domain and path after potential domain mapping.
			$base_url    = $this->as3cf->maybe_fix_local_subsite_url( $uploads['baseurl'] );
			$base_url    = AS3CF_Utils::remove_scheme( $base_url );
			$curr_domain = AS3CF_Utils::parse_url( $base_url, PHP_URL_HOST );
			$port        = AS3CF_Utils::parse_url( $base_url, PHP_URL_PORT );
			if ( ! empty( $port ) ) {
				$curr_domain .= ':' . $port;
			}

			if ( $curr_domain !== $orig_domain ) {
				$domains[] = $curr_domain;
			}

			/**
			 * Allow alteration of the local domains that can be matched on.
			 *
			 * @param array $domains
			 */
			$domains = apply_filters( 'as3cf_local_domains', $domains );

			if ( ! empty( $domains ) ) {
				foreach ( array_unique( $domains ) as $match_domain ) {
					$base_urls[] = substr_replace( $base_url, $match_domain, 2, strlen( $curr_domain ) );
				}
			}
		}

		return array_unique( $base_urls );
	}

	/**
	 * Get an array of domain names that can be used for remote items.
	 *
	 * @param bool $refresh Refresh cached domains, default false.
	 *
	 * @return array
	 */
	public function get_remote_domains( $refresh = false ) {
		static $domains = array();

		if ( $refresh || empty( $domains ) ) {
			// Storage Provider's default domain.
			$domains = array(
				$this->as3cf->get_storage_provider()->get_domain(),
			);

			// Delivery Provider's default domain.
			$delivery_provider = $this->as3cf->get_delivery_provider();
			$domains[]         = $delivery_provider->get_domain();

			// Delivery Provider's custom domain.
			if ( $delivery_provider->delivery_domain_allowed() && $this->as3cf->get_setting( 'enable-delivery-domain' ) ) {
				$delivery_domain = $this->as3cf->get_setting( 'delivery-domain' );

				if ( ! empty( $delivery_domain ) ) {
					$domains[] = trim( $delivery_domain );
				}
			}

			/**
			 * Allow alteration of the remote domains that can be matched on.
			 *
			 * @param array $domains
			 */
			$domains = array_unique( apply_filters( 'as3cf_remote_domains', $domains ) );
		}

		return $domains;
	}

	/**
	 * Does URL need replacing?
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	abstract protected function url_needs_replacing( $url );

	/**
	 * Get URL.
	 *
	 * @param int|array   $item_source
	 * @param null|string $object_key
	 *
	 * @return bool|string
	 */
	abstract protected function get_url( $item_source, $object_key = null );

	/**
	 * Get base URL.
	 *
	 * @param int|array $item_source
	 *
	 * @return string|false
	 */
	abstract protected function get_base_url( $item_source );

	/**
	 * Get attachment ID from URL.
	 *
	 * @param string $url
	 *
	 * @return array
	 */
	abstract public function get_item_source_from_url( $url );

	/**
	 * Get attachment IDs from URLs.
	 *
	 * @param array $urls
	 *
	 * @return array url => attachment ID (or false)
	 */
	abstract protected function get_item_sources_from_urls( $urls );

	/**
	 * Normalize find value.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	abstract protected function normalize_find_value( $url );

	/**
	 * Normalize replace value.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	abstract protected function normalize_replace_value( $url );

	/**
	 * Post process content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	abstract protected function post_process_content( $content );

	/**
	 * Pre replace content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	abstract protected function pre_replace_content( $content );
}
