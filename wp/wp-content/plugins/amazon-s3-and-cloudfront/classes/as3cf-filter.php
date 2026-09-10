<?php

use DeliciousBrains\WP_Offload_Media\Items\Item;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AS3CF_Filter {

	/**
	 * The cache group used by an external object cache for posts.
	 */
	const POST_CACHE_GROUP = 'post_as3cf_url_cache';

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
	 * URL caches handled in current process where post isn't known.
	 * e.g. handling widgets and other theme related components.
	 *
	 * @var array
	 */
	protected $url_cache = array();

	/**
	 * URL caches handled in current process, keyed by post ID.
	 *
	 * @var array
	 */
	protected $url_cache_by_post_id = array();

	/**
	 * The key used for storing the URL cache.
	 *
	 * @var string
	 */
	protected static $cache_key = 'as3cf_url_cache';

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
		// Nothing to see here, move along!
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

		return $this->process_content( $value, array(), $this->url_cache );
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

		$post_id = AS3CF_Utils::get_post_id();

		list( $cached, $to_cache ) = $this->maybe_init_post_cache( $post_id );

		$content = $this->process_content( $content, $cached, $to_cache );

		$this->maybe_update_post_cache( $post_id, $cached, $to_cache );

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

		foreach ( $instance as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			if ( in_array( $key, array( 'text', 'content' ) ) || AS3CF_Utils::is_url( $value ) ) {
				$instance[ $key ] = $this->process_content( $value, array(), $this->url_cache );
			}
		}

		return $instance;
	}

	/**
	 * Process content.
	 *
	 * @param mixed $content
	 * @param array $cache
	 * @param array $to_cache
	 *
	 * @return mixed
	 */
	protected function process_content( mixed $content, array $cache, array &$to_cache ): mixed {
		if ( empty( $content ) || ! is_string( $content ) ) {
			// Nothing to filter, return.
			return $content;
		}

		if ( ! $this->should_filter_content() ) {
			// Not filtering content, return.
			return $content;
		}

		// Perform pre-processing if required.
		$content = $this->pre_replace_content( $content );

		// Actually process the content.
		$content = $this->find_urls_and_replace( $content, $cache, $to_cache );

		// Perform post-processing if required.
		return $this->post_process_content( $content );
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
	protected function find_urls_and_replace( string $value, array $cache, array &$to_cache ): string {
		if ( ! $this->should_filter_content() ) {
			// Not filtering content, return
			return $value;
		}

		$url_pairs = $this->get_urls_from_content( $value, $cache, $to_cache );

		return $this->replace_urls( $value, $url_pairs );
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
	protected function get_urls_from_content( string $content, array $cache, array &$to_cache ): array {
		$url_pairs = array();

		if ( empty( $content ) ) {
			return $url_pairs;
		}

		// Decode any html encoded quotes that might still be surrounding URLs.
		// We're using ENT_HTML5 here as &apos; isn't caught by the default ENT_HTML401.
		$content = htmlspecialchars_decode( $content, ENT_QUOTES | ENT_HTML5 );

		if (
			! preg_match_all( '/(http|https)?:?\/\/[^"\'\s<>()\\\]*/', $content, $matches ) ||
			! isset( $matches[0] )
		) {
			// No URLs found, return
			return $url_pairs;
		}

		$matches = array_unique( $matches[0] );
		$urls    = array();

		foreach ( $matches as $url ) {
			// Remove trailing punctuation, quotes, etc.
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
					// Attachment ID failure, continue.
					continue;
				}
			} elseif ( isset( $cache[ $bare_url ] ) ) {
				$item_source = $cache[ $bare_url ];

				if ( $this->is_failure( $item_source ) ) {
					// Attachment ID failure, add to in-flight cache and continue.
					$to_cache[ $bare_url ] = $item_source;

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
	 * @param array|mixed $value
	 *
	 * @return bool
	 */
	protected function is_failure( mixed $value ): bool {
		if ( ! is_array( $value ) || ! isset( $value['timestamp'] ) ) {
			return false;
		}

		static $expires = 0;

		if ( empty( $expires ) ) {
			$timeout = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 1 : 15 * MINUTE_IN_SECONDS;

			/**
			 * Filter how long we wait to recheck a URL that we failed to find
			 * a matching offload for.
			 *
			 * Min: 1 second
			 * Max: 1 day
			 * Default: 15 mins, 1 sec if SCRIPT_DEBUG defined and true
			 *
			 * @param int $timeout Seconds to wait until recheck.
			 */
			$timeout = min(
				max( 1, (int) apply_filters( 'as3cf_url_cache_failure_timeout', $timeout ) ),
				DAY_IN_SECONDS
			);
			$expires = time() - $timeout;
		}

		if ( $expires >= (int) $value['timestamp'] ) {
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
	protected function push_to_url_pairs(
		array &$url_pairs,
		array $item_source,
		string $find,
		array &$to_cache
	): void {
		$find_size = $this->normalize_find_value( $this->as3cf->maybe_remove_query_string( $find ) );

		// The found size might be a fallback to full size, which is a valid
		// replacement as long as all other criteria are met for getting the URL.
		$size         = $this->get_size_string_from_url( $item_source, $find );
		$replace_size = $this->get_url( $item_source, $size );

		// A previously cached URL might have been re-added and its ID changed.
		if ( empty( $replace_size ) ) {
			$this->url_cache_failure( $find, $to_cache );

			return;
		}

		// If here, we're ok to add or keep item in cache.
		$to_cache[ $find_size ] = $item_source;

		$parts = wp_parse_url( $find );

		if ( ! isset( $parts['scheme'] ) ) {
			$replace_size = AS3CF_Utils::remove_scheme( $replace_size );
		}

		// Find and replace sized version.
		$url_pairs[ $find_size ] = $replace_size;
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

		return apply_filters(
			'as3cf_get_size_string_from_url_for_item_source',
			Item::primary_object_key(),
			$url,
			$item_source
		);
	}

	/**
	 * URL cache failure.
	 *
	 * @param string $url
	 * @param array  $to_cache
	 */
	protected function url_cache_failure( string $url, array &$to_cache ): void {
		static $failure = array();

		if ( empty( $failure ) ) {
			$failure = array(
				'timestamp' => time(),
			);
		}

		$to_cache[ $url ] = $failure;
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
	 * Get post cache.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	public function get_post_cache( int $post_id ): array {
		if ( empty( $post_id ) ) {
			return array();
		}

		if ( wp_using_ext_object_cache() ) {
			$cache = wp_cache_get( $post_id, self::POST_CACHE_GROUP );
		} else {
			$cache = get_post_meta( $post_id, static::$cache_key, true );
		}

		// Data's not what we expected, reset.
		if ( empty( $cache ) || ! is_array( $cache ) ) {
			return array();
		}

		return $cache;
	}

	/**
	 * Set the cache for the given post.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $data
	 */
	protected function set_post_cache( int $post_id, mixed $data ): void {
		if ( empty( $post_id ) ) {
			return;
		}

		if ( wp_using_ext_object_cache() ) {
			$expires = apply_filters( 'as3cf_' . self::POST_CACHE_GROUP . '_expires', DAY_IN_SECONDS, $post_id, $data );
			wp_cache_set( $post_id, $data, self::POST_CACHE_GROUP, $expires );
		} else {
			update_post_meta( $post_id, static::$cache_key, $data );
		}
	}

	/**
	 * Get array of currently cached URLs and array to be cached for given Post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Cached and To Cache arrays.
	 */
	protected function maybe_init_post_cache( int $post_id ): array {
		$cached   = array();
		$to_cache = array();

		if ( ! empty( $post_id ) ) {
			$cached   = $this->get_post_cache( $post_id );
			$to_cache = empty( $this->url_cache_by_post_id[ $post_id ] ) ? array() : $this->url_cache_by_post_id[ $post_id ];
		}

		return array( $cached, $to_cache );
	}

	/**
	 * Maybe update post cache.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $cached   Old cache.
	 * @param array $to_cache New cache.
	 */
	protected function maybe_update_post_cache( int $post_id, array $cached, array $to_cache ): void {
		if ( empty( $post_id ) ) {
			return;
		}

		// There's some weird redirects that the templating system does while
		// retaining the 1st post_id, which can clear out the new cache.
		// So we never save an empty cache, which is fine as if post actually
		// has no URLs, it'll not process anything, and if URLs churn, they'll
		// result in a new non-empty cache.
		if ( empty( $to_cache ) ) {
			return;
		}

		// Keep cache for any further in-process use, stops reset if follow-on
		// processing only finds already rewritten URLs and so doesn't add
		// anything to cache, or only adds failed URLs.
		$this->url_cache_by_post_id[ $post_id ] = $to_cache;

		// Before comparing cached to the new cache items, potentially add any
		// previously cached failures to the new cache that are missing, and sort
		// it. This is so that we don't get into a position where failed URLs
		// that haven't yet been parsed cause the saved cache to flip-flop between
		// having and not having the failed URLs.
		$cached_failures = array_filter( $cached, array( $this, 'is_failure' ) );
		$to_cache        = array_merge( $cached_failures, $to_cache );

		ksort( $to_cache );

		if ( $cached !== $to_cache ) {
			$this->set_post_cache( $post_id, $to_cache );
		}
	}

	/**
	 * Purge items from cache on delete.
	 *
	 * NOTE: Only used for unit tests, no longer used on attachment delete as
	 *       the cache is self-healing and will automatically drop redundant
	 *       URLs when post updated, with redundant URLs otherwise just not
	 *       being referenced during URL rewriting.
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
		$sql = $wpdb->prepare(
			"
 			DELETE FROM {$wpdb->postmeta}
 			WHERE meta_key = %s
 			AND meta_value LIKE %s;
			",
			static::$cache_key,
			'%"' . $url . '"%'
		);

		// phpcs:ignore WordPress.DB -- safe query, must not be cached
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
		if ( $this->as3cf->get_setting( 'serve-from-s3' ) ) {
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

		$post_id = $this->get_custom_css_post_id( $stylesheet );

		list( $cached, $to_cache ) = $this->maybe_init_post_cache( $post_id );

		$css = $this->process_content( $css, $cached, $to_cache );

		$this->maybe_update_post_cache( $post_id, $cached, $to_cache );

		return $css;
	}

	/**
	 * Get custom CSS post ID.
	 *
	 * @param string $stylesheet
	 *
	 * @return int
	 */
	protected function get_custom_css_post_id( string $stylesheet ): int {
		$post = wp_get_custom_css_post( $stylesheet );

		if ( empty( $post ) ) {
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
