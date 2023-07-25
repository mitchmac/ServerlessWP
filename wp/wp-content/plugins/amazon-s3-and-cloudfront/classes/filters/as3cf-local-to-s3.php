<?php

use DeliciousBrains\WP_Offload_Media\Items\Item;

class AS3CF_Local_To_S3 extends AS3CF_Filter {
	/**
	 * @inheritDoc
	 */
	public function setup() {
		parent::setup();

		// EDD
		add_filter( 'edd_download_files', array( $this, 'filter_edd_download_files' ) );
		// Customizer
		add_filter( 'theme_mod_background_image', array( $this, 'filter_customizer_image' ) );
		add_filter( 'theme_mod_header_image', array( $this, 'filter_customizer_image' ) );
		add_filter( 'customize_value_custom_css', array( $this, 'filter_customize_value_custom_css' ), 10, 2 );
		add_filter( 'wp_get_custom_css', array( $this, 'filter_wp_get_custom_css' ), 10, 2 );
		// Posts
		add_action( 'the_post', array( $this, 'filter_post_data' ) );
		add_filter( 'content_pagination', array( $this, 'filter_content_pagination' ) );
		add_filter( 'the_content', array( $this, 'filter_post' ), 100 );
		add_filter( 'the_excerpt', array( $this, 'filter_post' ), 100 );
		add_filter( 'rss_enclosure', array( $this, 'filter_post' ), 100 );
		add_filter( 'content_edit_pre', array( $this, 'filter_post' ) );
		add_filter( 'excerpt_edit_pre', array( $this, 'filter_post' ) );
		add_filter( 'as3cf_filter_post_local_to_s3', array( $this, 'filter_post' ) ); // Backwards compatibility
		add_filter( 'as3cf_filter_post_local_to_provider', array( $this, 'filter_post' ) );
		// Widgets
		add_filter( 'widget_form_callback', array( $this, 'filter_widget_display' ) );
		add_filter( 'widget_display_callback', array( $this, 'filter_widget_display' ) );
		if ( function_exists( 'is_wp_version_compatible' ) && is_wp_version_compatible( '5.8' ) ) {
			add_filter( 'customize_value_widget_block', array( $this, 'filter_customize_value_widget_block' ) );
			add_filter( 'widget_block_content', array( $this, 'filter_widget_block_content' ) );
		}
		// Edit Media page
		add_filter( 'set_url_scheme', array( $this, 'set_url_scheme' ), 10, 3 );
		// Blocks
		if ( function_exists( 'is_wp_version_compatible' ) && is_wp_version_compatible( '5.9' ) ) {
			add_filter( 'render_block', array( $this, 'filter_post' ), 100 );
			add_filter( 'get_block_templates', array( $this, 'filter_get_block_templates' ), 100, 3 );
			add_filter( 'get_block_template', array( $this, 'filter_get_block_template' ), 100, 3 );
		}
	}

	/**
	 * Filter customize value custom CSS.
	 *
	 * @param mixed                           $value
	 * @param WP_Customize_Custom_CSS_Setting $setting
	 *
	 * @return string
	 */
	public function filter_customize_value_custom_css( $value, $setting ) {
		return $this->filter_custom_css( $value, $setting->stylesheet );
	}

	/**
	 * Filter `wp_get_custom_css`.
	 *
	 * @param string $css
	 * @param string $stylesheet
	 *
	 * @return string
	 */
	public function filter_wp_get_custom_css( $css, $stylesheet ) {
		return $this->filter_custom_css( $css, $stylesheet );
	}

	/**
	 * Filter post data.
	 *
	 * @param WP_Post $post
	 */
	public function filter_post_data( $post ) {
		global $pages;

		$cache    = $this->get_post_cache( $post->ID );
		$to_cache = array();

		if ( is_array( $pages ) && 1 === count( $pages ) && ! empty( $pages[0] ) ) {
			// Post already filtered and available on global $page array, continue
			$post->post_content = $pages[0];
		} else {
			$post->post_content = $this->process_content( $post->post_content, $cache, $to_cache );
		}

		$post->post_excerpt = $this->process_content( $post->post_excerpt, $cache, $to_cache );

		$this->maybe_update_post_cache( $to_cache );
	}

	/**
	 * Filter content pagination.
	 *
	 * @param array $pages
	 *
	 * @return array
	 */
	public function filter_content_pagination( $pages ) {
		$cache    = $this->get_post_cache();
		$to_cache = array();

		foreach ( $pages as $key => $page ) {
			$pages[ $key ] = $this->process_content( $page, $cache, $to_cache );
		}

		$this->maybe_update_post_cache( $to_cache );

		return $pages;
	}

	/**
	 * Filter widget display.
	 *
	 * @param array $instance
	 *
	 * @return array
	 */
	public function filter_widget_display( $instance ) {
		return $this->handle_widget( $instance );
	}

	/**
	 * Filters the content of the block widget during initial load of the customizer.
	 *
	 * @param array $value The widget block.
	 *
	 * @return array
	 */
	public function filter_customize_value_widget_block( $value ) {
		return $this->handle_widget( $value );
	}

	/**
	 * Filters the content of the block widget before output.
	 *
	 * @param string $content The widget content.
	 *
	 * @return string
	 */
	public function filter_widget_block_content( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		$cache    = $this->get_option_cache();
		$to_cache = array();

		$changed_content = $this->process_content( $content, $cache, $to_cache );

		if ( ! empty( $changed_content ) && $changed_content !== $content ) {
			$content = $changed_content;
		}

		$this->maybe_update_option_cache( $to_cache );

		return $content;
	}

	/**
	 * Does URL need replacing?
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public function url_needs_replacing( $url ) {
		if ( str_replace( $this->get_bare_upload_base_urls(), '', $url ) === $url ) {
			// Remote URL, no replacement needed
			return false;
		}

		// Local URL, perform replacement
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
		 * Return the provider URL for an item
		 *
		 * @param string|false $url         Url for the item, false if no URL can be determined
		 * @param array        $item_source Associative array describing the item, guaranteed keys:-
		 *                                  id: source item's unique integer id
		 *                                  source_type: source item's string type identifier
		 * @param string|null  $object_key  Object key (size) describing what sub file of an item to return url for
		 */
		return apply_filters( 'as3cf_get_provider_url_for_item_source', false, $item_source, $object_key );
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
		 * Return the local URL for an item
		 *
		 * @param string|false $url         Url for the item, false if no URL can be determined
		 * @param array        $item_source Associative array describing the item, guaranteed keys:-
		 *                                  id: source item's unique integer id
		 *                                  source_type: source item's string type identifier
		 * @param string|null  $object_key  Object key (size) describing what sub file of an item to return url for
		 */
		return apply_filters( 'as3cf_get_local_url_for_item_source', false, $item_source, null );
	}

	/**
	 * Get item source descriptor from URL.
	 *
	 * @param string $url
	 *
	 * @return bool|array
	 */
	public function get_item_source_from_url( $url ) {
		$results = $this->get_item_sources_from_urls( array( $url ) );

		if ( empty( $results ) ) {
			return false;
		}

		foreach ( $results as $result ) {
			if ( $result ) {
				return $result;
			}
		}

		return false;
	}

	/**
	 * Get item source descriptors from URLs.
	 *
	 * @param array $urls
	 *
	 * @return array url => item source descriptor array (or false)
	 */
	protected function get_item_sources_from_urls( $urls ) {
		$results = array();

		if ( empty( $urls ) ) {
			return $results;
		}

		if ( ! is_array( $urls ) ) {
			$urls = array( $urls );
		}

		$query_set = array();
		$paths     = array();
		$full_urls = array();

		// Quickly parse given URLs to add versions without size as we should lookup with size info first as that could be the "full" size.
		foreach ( $urls as $url ) {
			$query_set[]  = $url;
			$size_removed = AS3CF_Utils::remove_size_from_filename( $url );

			if ( $url !== $size_removed ) {
				$query_set[] = $size_removed;
			}
		}

		foreach ( $query_set as $url ) {
			// Path to search for in query set should be based on bare URL.
			$bare_url = AS3CF_Utils::remove_scheme( $url );
			// There can be multiple URLs in the query set that belong to the same full URL for the Media Library item.
			$full_url = AS3CF_Utils::remove_size_from_filename( $bare_url );

			if ( isset( $this->query_cache[ $full_url ] ) ) {
				// ID already cached, use it.
				$results[ $url ] = $this->query_cache[ $full_url ];

				continue;
			}

			$path = AS3CF_Utils::decode_filename_in_path( ltrim( str_replace( $this->get_bare_upload_base_urls(), '', $bare_url ), '/' ) );

			$paths[ $path ]           = $full_url;
			$full_urls[ $full_url ][] = $url;
		}

		if ( ! empty( $paths ) ) {
			$as3cf_items = Item::get_by_source_path( array_keys( $paths ) );

			if ( ! empty( $as3cf_items ) ) {
				/* @var Item $as3cf_item */
				foreach ( $as3cf_items as $as3cf_item ) {
					// Each returned item may have matched on either the source_path or original_source_path.
					// Because the base image file name of a thumbnail might match the primary rather scaled or rotated full image
					// it's possible that both source paths are used by separate URLs.
					foreach ( array( $as3cf_item->source_path(), $as3cf_item->original_source_path() ) as $source_path ) {
						if ( ! empty( $paths[ $source_path ] ) ) {
							$matched_full_url = $paths[ $source_path ];

							if ( ! empty( $full_urls[ $matched_full_url ] ) ) {
								$item_source = array(
									'id'          => $as3cf_item->source_id(),
									'source_type' => $as3cf_item->source_type(),
								);

								$this->query_cache[ $matched_full_url ] = $item_source;

								foreach ( $full_urls[ $matched_full_url ] as $url ) {
									$results[ $url ] = $item_source;
								}
								unset( $full_urls[ $matched_full_url ] );
							}
						}
					}
				}
			}

			// No more item IDs found, set remaining results as false.
			if ( count( $query_set ) !== count( $results ) ) {
				foreach ( $full_urls as $full_url => $schema_urls ) {
					foreach ( $schema_urls as $url ) {
						if ( ! array_key_exists( $url, $results ) ) {
							$this->query_cache[ $full_url ] = false;
							$results[ $url ]                = false;
						}
					}
				}
			}
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
		return AS3CF_Utils::decode_filename_in_path( $url );
	}

	/**
	 * Normalize replace value.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	protected function normalize_replace_value( $url ) {
		return AS3CF_Utils::encode_filename_in_path( $url );
	}

	/**
	 * Post process content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	protected function post_process_content( $content ) {
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
		$uploads  = wp_upload_dir();
		$base_url = AS3CF_Utils::remove_scheme( $uploads['baseurl'] );

		return $this->remove_aws_query_strings( $content, $base_url );
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
		if ( (bool) $this->as3cf->get_setting( 'force-https' ) ) {
			$content = str_replace( 'http:' . $replace, 'https:' . $replace, $content );
		}

		return $content;
	}

	/**
	 * Might need to re-fix remote URL's schema if WordPress core has substituted in HTTP but HTTPS is required.
	 *
	 * @param string $url
	 * @param string $scheme
	 * @param string $orig_scheme
	 *
	 * @return string
	 */
	public function set_url_scheme( $url, $scheme, $orig_scheme ) {
		if (
			'http' === $scheme && empty( $orig_scheme ) &&
			$this->as3cf->get_setting( 'force-https' ) &&
			$this->should_filter_content() &&
			! $this->url_needs_replacing( $url )
		) {
			// Check that it's one of ours and not external.
			$parts = AS3CF_Utils::parse_url( $url );

			if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || 'http' !== $parts['scheme'] ) {
				return $url;
			}

			$ours = false;
			if ( $this->as3cf->get_setting( 'enable-delivery-domain' ) && $this->as3cf->get_setting( 'delivery-domain', '' ) === $parts['host'] ) {
				$ours = true;
			} elseif ( false !== strpos( $parts['host'], $this->as3cf->get_storage_provider()->get_domain() ) ) {
				$ours = true;
			}

			if ( $ours ) {
				return substr_replace( $url, 'https', 0, 4 );
			}
		}

		return $url;
	}

	/**
	 * Filters the array of queried block templates array after they've been fetched.
	 *
	 * @param WP_Block_Template[] $query_result  Array of found block templates.
	 * @param array               $query         Arguments to retrieve templates.
	 * @param string              $template_type wp_template or wp_template_part.
	 *
	 * @return WP_Block_Template[]
	 */
	public function filter_get_block_templates( $query_result, $query, $template_type ) {
		if ( empty( $query_result ) ) {
			return $query_result;
		}

		foreach ( $query_result as $block_template ) {
			$block_template = $this->filter_get_block_template( $block_template, $block_template->id, $template_type );
		}

		return $query_result;
	}

	/**
	 * Filters the queried block template object after it's been fetched.
	 *
	 * @param WP_Block_Template|null $block_template The found block template, or null if there isn't one.
	 * @param string                 $id             Template unique identifier (example: theme_slug//template_slug).
	 * @param string                 $template_type  Template type: `'wp_template'` or '`wp_template_part'`.
	 *
	 * @return WP_Block_Template|null
	 */
	public function filter_get_block_template( $block_template, $id, $template_type ) {
		if ( empty( $block_template ) ) {
			return $block_template;
		}

		$content = $block_template->content;

		if ( empty( $content ) ) {
			return $block_template;
		}

		$content = $this->filter_post( $content );

		if ( ! empty( $content ) && $content !== $block_template->content ) {
			$block_template->content = $content;
		}

		return $block_template;
	}
}
