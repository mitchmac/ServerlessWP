<?php

use DeliciousBrains\WP_Offload_Media\Items\Item;

class AS3CF_S3_To_Local extends AS3CF_Filter {
	/**
	 * @inheritDoc
	 */
	public function setup() {
		parent::setup();

		// EDD
		add_filter( 'edd_metabox_save_edd_download_files', array( $this, 'filter_edd_download_files' ) );
		// Customizer
		add_filter( 'pre_set_theme_mod_background_image', array( $this, 'filter_customizer_image' ), 10, 2 );
		add_filter( 'pre_set_theme_mod_header_image', array( $this, 'filter_customizer_image' ), 10, 2 );
		add_filter( 'pre_set_theme_mod_header_image_data', array( $this, 'filter_header_image_data' ), 10, 2 );
		add_filter( 'update_custom_css_data', array( $this, 'filter_update_custom_css_data' ), 10, 2 );
		// Posts
		add_filter( 'content_save_pre', array( $this, 'filter_post' ) );
		add_filter( 'excerpt_save_pre', array( $this, 'filter_post' ) );
		add_filter( 'as3cf_filter_post_s3_to_local', array( $this, 'filter_post' ) ); // Backwards compatibility
		add_filter( 'as3cf_filter_post_provider_to_local', array( $this, 'filter_post' ) );
		// Widgets
		add_filter( 'widget_update_callback', array( $this, 'filter_widget_save' ) );
		add_filter( 'pre_update_option_widget_block', array( $this, 'filter_widget_block_save' ) );
	}

	/**
	 * Filter update custom CSS data.
	 *
	 * @param array $data
	 * @param array $args
	 *
	 * @return array
	 */
	public function filter_update_custom_css_data( $data, $args ) {
		$data['css'] = $this->filter_custom_css( $data['css'], $args['stylesheet'] );

		return $data;
	}

	/**
	 * Filter widget on save.
	 *
	 * @param array $instance
	 *
	 * @return array
	 */
	public function filter_widget_save( $instance ) {
		return $this->handle_widget( $instance );
	}

	/**
	 * Filter widget block on save.
	 *
	 * @param array $value The new, unserialized option value.
	 *
	 * @return array
	 */
	public function filter_widget_block_save( $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $idx => $section ) {
			$value[ $idx ] = $this->handle_widget( $section );
		}

		return $value;
	}

	/**
	 * Should filter content.
	 *
	 * @return bool
	 */
	protected function should_filter_content() {
		return true;
	}

	/**
	 * Does URL need replacing?
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public function url_needs_replacing( $url ) {
		if ( str_replace( $this->get_bare_upload_base_urls(), '', $url ) !== $url ) {
			// Local URL, no replacement needed.
			return false;
		}

		if ( str_replace( $this->get_remote_domains(), '', $url ) === $url ) {
			// Not a known remote URL, no replacement needed.
			return false;
		}

		// Remote URL, perform replacement
		return true;
	}

	/**
	 * Get URL
	 *
	 * @param array       $item_source
	 * @param null|string $object_key
	 *
	 * @return bool|string
	 */
	protected function get_url( $item_source, $object_key = null ) {
		if ( Item::is_empty_item_source( $item_source ) ) {
			return false;
		}

		/**
		 * Return the local URL for an item
		 *
		 * @param string|false $url         Url for the item, false if no URL can be determined
		 * @param array        $item_source Associative array describing the item, guaranteed keys:-
		 *                                  id: source item's unique integer id
		 *                                  source_type: source item's string type identifier
		 * @param string|null  $object_key  Object key (size) describing what sub file of an item to return url for
		 */
		return apply_filters( 'as3cf_get_local_url_for_item_source', false, $item_source, $object_key );
	}

	/**
	 * Get base URL.
	 *
	 * @param array $item_source
	 *
	 * @return string|false
	 */
	protected function get_base_url( $item_source ) {
		if ( Item::is_empty_item_source( $item_source ) ) {
			return false;
		}

		/**
		 * Return the provider URL for an item
		 *
		 * @param string|false $url         Url for the item, false if no URL can be determined
		 * @param array        $item_source Associative array describing the item, guaranteed keys:-
		 *                                  id: source item's unique integer id
		 *                                  source_type: source item's string type identifier
		 * @param string|null  $object_key  Object key (size) describing what sub file of an item to return url for
		 */
		return apply_filters( 'as3cf_get_provider_url_for_item_source', false, $item_source, null );
	}

	/**
	 * Get item source descriptor from URL.
	 *
	 * @param string $url
	 *
	 * @return bool|array
	 */
	public function get_item_source_from_url( $url ) {
		// Result for sized URL already cached in request, return it.
		if ( isset( $this->query_cache[ $url ] ) ) {
			return $this->query_cache[ $url ];
		}

		$item_source = Item::get_item_source_by_remote_url( $url );

		if ( ! Item::is_empty_item_source( $item_source ) ) {
			$this->query_cache[ $url ] = $item_source;

			return $item_source;
		}

		$full_url = AS3CF_Utils::remove_size_from_filename( $url );

		// If we've already tried to find this URL above because it didn't have a size suffix, cache and return.
		if ( $url === $full_url ) {
			$this->query_cache[ $url ] = $item_source;

			return $item_source;
		}

		// Result for URL already cached in request whether found or not, return it.
		if ( isset( $this->query_cache[ $full_url ] ) ) {
			return $this->query_cache[ $full_url ];
		}

		$item_source = Item::get_item_source_by_remote_url( $full_url );

		$this->query_cache[ $full_url ] = ! Item::is_empty_item_source( $item_source ) ? $item_source : false;

		return $this->query_cache[ $full_url ];
	}

	/**
	 * Get item source descriptors from URLs.
	 *
	 * @param array $urls
	 *
	 * @return array url => item source descriptor (or false)
	 */
	protected function get_item_sources_from_urls( $urls ) {
		$results = array();

		if ( empty( $urls ) ) {
			return $results;
		}

		if ( ! is_array( $urls ) ) {
			$urls = array( $urls );
		}

		foreach ( $urls as $url ) {
			$results[ $url ] = $this->get_item_source_from_url( $url );
		}

		return $results;
	}

	/**
	 * Normalize find value.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	protected function normalize_find_value( $url ) {
		return AS3CF_Utils::encode_filename_in_path( $url );
	}

	/**
	 * Normalize replace value.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	protected function normalize_replace_value( $url ) {
		return AS3CF_Utils::decode_filename_in_path( $url );
	}

	/**
	 * Post process content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	protected function post_process_content( $content ) {
		$content = $this->remove_aws_query_strings( $content );
		$content = AS3CF_Utils::maybe_fix_serialized_string( $content );

		return $content;
	}

	/**
	 * Pre replace content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	protected function pre_replace_content( $content ) {
		return $content;
	}
}
