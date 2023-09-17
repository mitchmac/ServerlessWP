<?php
/**
 * Plugin Utilities
 *
 * @package     amazon-s3-and-cloudfront
 * @subpackage  Classes/Utils
 * @copyright   Copyright (c) 2015, Delicious Brains
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

use DeliciousBrains\WP_Offload_Media\Items\Item;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AS3CF_Utils' ) ) {

	/**
	 * AS3CF_Utils Class
	 *
	 * This class contains utility functions that need to be available
	 * across the Pro plugin codebase
	 */
	class AS3CF_Utils {

		/**
		 * Get post ID.
		 *
		 * @param null|int|WP_Post $post Optional. Post ID or post object. Defaults to current post.
		 *
		 * @return int
		 */
		public static function get_post_id( $post = null ) {
			return (int) get_post_field( 'ID', $post );
		}

		/**
		 * Trailing slash prefix string ensuring no leading slashes.
		 *
		 * @param string $string
		 *
		 * @return string
		 */
		public static function trailingslash_prefix( $string ) {
			return static::unleadingslashit( trailingslashit( trim( $string ) ) );
		}

		/**
		 * Ensure string has a leading slash, like in absolute paths.
		 *
		 * @param string $string
		 *
		 * @return string
		 */
		public static function leadingslashit( $string ) {
			return '/' . static::unleadingslashit( $string );
		}

		/**
		 * Ensure string has no leading slash, like in relative paths.
		 *
		 * @param string $string
		 *
		 * @return string
		 */
		public static function unleadingslashit( $string ) {
			return ltrim( trim( $string ), '/\\' );
		}

		/**
		 * Ensure string has a leading and trailing slash, like in absolute directory paths.
		 *
		 * @param string $string
		 *
		 * @return string
		 */
		public static function leadingtrailingslashit( $string ) {
			return static::leadingslashit( trailingslashit( trim( $string ) ) );
		}

		/**
		 * Remove scheme from URL.
		 *
		 * @param string $url
		 *
		 * @return string
		 */
		public static function remove_scheme( $url ) {
			return preg_replace( '/^(?:http|https):/', '', $url );
		}

		/**
		 * Remove size from filename (image[-100x100].jpeg).
		 *
		 * @param string $url
		 * @param bool   $remove_extension
		 *
		 * @return string
		 */
		public static function remove_size_from_filename( $url, $remove_extension = false ) {
			$url = preg_replace( '/^(\S+)-[0-9]{1,4}x[0-9]{1,4}(\.[a-zA-Z0-9\.]{2,})?/', '$1$2', $url );

			$url = apply_filters( 'as3cf_remove_size_from_filename', $url );

			if ( $remove_extension ) {
				$ext = pathinfo( $url, PATHINFO_EXTENSION );
				$url = str_replace( ".$ext", '', $url );
			}

			return $url;
		}

		/**
		 * Is the given size recognized as the full sized image?
		 *
		 * @param string|null $size
		 *
		 * @return bool
		 */
		public static function is_full_size( $size ) {
			if ( empty( $size ) || in_array( $size, array( 'full', Item::primary_object_key() ) ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Reduce the given URL down to the simplest version of itself.
		 *
		 * Useful for matching against the full version of the URL in a full-text search
		 * or saving as a key for dictionary type lookup.
		 *
		 * @param string $url
		 *
		 * @return string
		 */
		public static function reduce_url( $url ) {
			$parts = static::parse_url( $url );
			$host  = isset( $parts['host'] ) ? $parts['host'] : '';
			$port  = isset( $parts['port'] ) ? ":{$parts['port']}" : '';
			$path  = isset( $parts['path'] ) ? $parts['path'] : '';

			return '//' . $host . $port . $path;
		}

		/**
		 * Parses a URL into its components. Compatible with PHP < 5.4.7.
		 *
		 * @param string $url       The URL to parse.
		 *
		 * @param int    $component PHP_URL_ constant for URL component to return.
		 *
		 * @return mixed An array of the parsed components, mixed for a requested component, or false on error.
		 */
		public static function parse_url( $url, $component = -1 ) {
			$url       = trim( $url );
			$no_scheme = 0 === strpos( $url, '//' );

			if ( $no_scheme ) {
				$url = 'http:' . $url;
			}

			$parts = parse_url( $url, $component );

			if ( 0 < $component ) {
				return $parts;
			}

			if ( $no_scheme && is_array( $parts ) ) {
				unset( $parts['scheme'] );
			}

			return $parts;
		}

		/**
		 * Is the string a URL?
		 *
		 * @param mixed $string
		 *
		 * @return bool
		 */
		public static function is_url( $string ): bool {
			if ( empty( $string ) || ! is_string( $string ) ) {
				return false;
			}

			if ( preg_match( '@^(?:https?:)?//[a-zA-Z0-9\-]+@', $string ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Is the string a relative URL?
		 *
		 * @param string $string
		 *
		 * @return bool
		 */
		public static function is_relative_url( $string ) {
			if ( empty( $string ) || ! is_string( $string ) ) {
				return false;
			}

			$url = static::parse_url( $string );

			return ( empty( $url['scheme'] ) && empty( $url['host'] ) );
		}

		/**
		 * Get file paths for all attachment versions.
		 *
		 * @param int        $attachment_id
		 * @param bool       $exists_locally
		 * @param array|bool $meta
		 * @param bool       $include_backups
		 *
		 * @return array
		 */
		public static function get_attachment_file_paths( $attachment_id, $exists_locally = true, $meta = false, $include_backups = true ) {
			$file_path = get_attached_file( $attachment_id, true );
			$paths     = array(
				Item::primary_object_key() => $file_path,
			);

			if ( ! $meta ) {
				$meta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
			}

			if ( is_wp_error( $meta ) ) {
				return $paths;
			}

			$file_name = wp_basename( $file_path );

			// If file edited, current file name might be different.
			if ( isset( $meta['file'] ) ) {
				$paths['file'] = str_replace( $file_name, wp_basename( $meta['file'] ), $file_path );

				// However, if this file path turns out to be exactly the same as the primary objet key, we don't need it.
				if ( $paths[ Item::primary_object_key() ] === $paths['file'] ) {
					unset( $paths['file'] );
				}
			}

			// Thumb
			if ( isset( $meta['thumb'] ) ) {
				$paths['thumb'] = str_replace( $file_name, $meta['thumb'], $file_path );
			}

			// Original Image (when large image scaled down to threshold size and used as "full").
			if ( isset( $meta['original_image'] ) ) {
				$paths['original_image'] = str_replace( $file_name, $meta['original_image'], $file_path );
			}

			// Sizes
			if ( isset( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size => $file ) {
					if ( isset( $file['file'] ) ) {
						$paths[ $size ] = str_replace( $file_name, $file['file'], $file_path );
					}
				}
			}

			$backups = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

			// Backups
			if ( $include_backups && is_array( $backups ) ) {
				foreach ( $backups as $size => $file ) {
					if ( isset( $file['file'] ) ) {
						$paths[ $size ] = str_replace( $file_name, $file['file'], $file_path );
					}
				}
			}

			// Allow other processes to add files to be uploaded
			$paths = apply_filters( 'as3cf_attachment_file_paths', $paths, $attachment_id, $meta );

			// Remove paths that don't exist
			if ( $exists_locally ) {
				foreach ( $paths as $key => $path ) {
					if ( ! file_exists( $path ) ) {
						unset( $paths[ $key ] );
					}
				}
			}

			return $paths;
		}

		/**
		 * Get intermediate size from attachment filename.
		 * If multiple sizes exist with same filename, only the first size found will be returned.
		 *
		 * @param int    $attachment_id
		 * @param string $filename
		 *
		 * @return string
		 */
		public static function get_intermediate_size_from_filename( $attachment_id, $filename ) {
			$sizes = self::get_attachment_file_paths( $attachment_id, false );

			foreach ( $sizes as $size => $file ) {
				if ( wp_basename( $file ) === $filename ) {
					return $size;
				}
			}

			return '';
		}

		/**
		 * Strip edited image suffix and extension from path.
		 *
		 * @param string $path
		 * @param string $source_type
		 *
		 * @return string
		 */
		public static function strip_image_edit_suffix_and_extension( $path, $source_type = 'media-library' ) {
			$parts    = pathinfo( $path );
			$filename = preg_replace( '/-e[0-9]{13}/', '', $parts['filename'] );
			$result   = str_replace( $parts['basename'], $filename, $path );

			/**
			 * Allow source type specific cleanup
			 *
			 * @param string $path
			 * @param string $source_type
			 *
			 * @return string
			 */
			return apply_filters( 'as3cf_strip_image_edit_suffix_and_extension', $result, $source_type );
		}

		/**
		 * Create a site link for given URL.
		 *
		 * @param string $url   URL for the link
		 * @param string $text  Text for the link
		 * @param string $class Optional class to add to link
		 *
		 * @return string
		 */
		public static function dbrains_link( $url, $text, $class = '' ) {
			$class = empty( $class ) ? '' : ' class="' . trim( $class ) . '"';

			return sprintf( '<a href="%s"%s target="_blank">%s</a>', esc_url( $url ), $class, esc_html( $text ) );
		}

		/**
		 * Check whether two URLs share the same domain.
		 *
		 * @param string $url_a
		 * @param string $url_b
		 *
		 * @return bool
		 */
		public static function url_domains_match( $url_a, $url_b ) {
			if ( ! static::is_url( $url_a ) || ! static::is_url( $url_b ) ) {
				return false;
			}

			return static::parse_url( $url_a, PHP_URL_HOST ) === static::parse_url( $url_b, PHP_URL_HOST );
		}

		/**
		 * Get the current domain.
		 *
		 * @return string|false
		 */
		public static function current_domain() {
			return parse_url( home_url(), PHP_URL_HOST );
		}

		/**
		 * Get the base domain of the current domain.
		 *
		 * @return string
		 */
		public static function current_base_domain() {
			return static::base_domain( static::current_domain() );
		}

		/**
		 * Get the base domain of the supplied domain.
		 *
		 * @param string $domain
		 *
		 * @return string
		 */
		public static function base_domain( $domain ) {
			if ( WP_Http::is_ip_address( $domain ) ) {
				return $domain;
			}

			$parts = explode( '.', $domain );

			// localhost etc.
			if ( is_string( $parts ) ) {
				return $domain;
			}

			if ( count( $parts ) < 3 ) {
				return $domain;
			}

			// Just knock off the first segment.
			unset( $parts[0] );

			return implode( '.', $parts );
		}

		/**
		 * Very basic check of whether domain is real.
		 *
		 * @param string $domain
		 *
		 * @return bool
		 *
		 * Note: Very early version, may extend with further "local" domain checks if relevant.
		 */
		public static function is_public_domain( $domain ) {
			// We're not going to test SEO etc. for ip addresses.
			if ( WP_Http::is_ip_address( $domain ) ) {
				return false;
			}

			$parts = explode( '.', $domain );

			// localhost etc.
			if ( is_string( $parts ) ) {
				return false;
			}

			// TODO: Maybe check domain TLD.

			return true;
		}

		/**
		 * Is given URL considered SEO friendly?
		 *
		 * @param string $url
		 *
		 * @return bool
		 */
		public static function seo_friendly_url( $url ) {
			$domain      = static::base_domain( parse_url( $url, PHP_URL_HOST ) );
			$base_domain = static::current_base_domain();

			// If either domain is not a public domain then skip checks.
			if ( ! static::is_public_domain( $domain ) || ! static::is_public_domain( $base_domain ) ) {
				return true;
			}

			if ( substr( $domain, -strlen( $base_domain ) ) === $base_domain ) {
				return true;
			}

			return false;
		}

		/**
		 * A safe wrapper for deactivate_plugins()
		 */
		public static function deactivate_plugins() {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			call_user_func_array( 'deactivate_plugins', func_get_args() );
		}

		/**
		 * Get the first defined constant from the given list of constant names.
		 *
		 * @param array $constants
		 *
		 * @return string|false string constant name if defined, otherwise false if none are defined
		 */
		public static function get_first_defined_constant( $constants ) {
			if ( ! empty( $constants ) ) {
				foreach ( (array) $constants as $constant ) {
					if ( defined( $constant ) ) {
						return $constant;
					}
				}
			}

			return false;
		}

		/**
		 * Ensure returned keys are for correct attachment.
		 *
		 * @param int    $source_id
		 * @param array  $keys
		 * @param string $source_type
		 *
		 * @return array
		 */
		public static function validate_attachment_keys( $source_id, $keys, $source_type ) {
			if ( Media_Library_Item::source_type() === $source_type ) {
				$paths     = self::get_attachment_file_paths( $source_id, false );
				$filenames = array_map( 'wp_basename', $paths );

				foreach ( $keys as $key => $value ) {
					$filename = wp_basename( $value );

					if ( ! in_array( $filename, $filenames ) ) {
						unset( $keys[ $key ] );
					}
				}
			}

			return $keys;
		}

		/**
		 * Sanitize custom domain
		 *
		 * @param string $domain
		 *
		 * @return string
		 */
		public static function sanitize_custom_domain( $domain ) {
			$domain = preg_replace( '@^[a-zA-Z]*:\/\/@', '', $domain );
			$domain = preg_replace( '@[^a-zA-Z0-9\.\-]@', '', $domain );

			return $domain;
		}

		/**
		 * Decode file name in potentially URL encoded URL path.
		 *
		 * @param string $file
		 *
		 * @return string
		 */
		public static function decode_filename_in_path( $file ) {
			$url = parse_url( $file );

			if ( ! isset( $url['path'] ) ) {
				// Can't determine path, return original
				return $file;
			}

			$file_name = wp_basename( $url['path'] );

			if ( false === strpos( $file_name, '%' ) ) {
				// File name not encoded, return original
				return $file;
			}

			$decoded_file_name = rawurldecode( $file_name );

			return str_replace( $file_name, $decoded_file_name, $file );
		}

		/**
		 * Returns indexed array of full size paths, e.g. orig and edited.
		 *
		 * @param array $paths Associative array of sizes and relative paths
		 *
		 * @return array
		 *
		 * @see get_attachment_file_paths
		 */
		public static function fullsize_paths( $paths ) {
			if ( is_array( $paths ) && ! empty( $paths ) ) {
				return array_values( array_unique( array_intersect_key( $paths, array_flip( array( Item::primary_object_key(), 'file', 'full-orig', 'original_image' ) ) ) ) );
			} else {
				return array();
			}
		}

		/**
		 * Converts an array of upload file paths to all be relative paths.
		 * If any path is not absolute or does begin with current uploads base dir it will not be altered.
		 *
		 * @param array $paths Array of upload file paths, absolute or relative.
		 *
		 * @return array Input array with values switched to relative upload file paths.
		 */
		public static function make_upload_file_paths_relative( $paths ) {
			if ( empty( $paths ) ) {
				return array();
			}

			if ( ! is_array( $paths ) ) {
				$paths = array( $paths );
			}

			$uploads = wp_upload_dir();
			$basedir = trailingslashit( $uploads['basedir'] );
			$offset  = strlen( $basedir );

			foreach ( $paths as $key => $path ) {
				if ( 0 === strpos( $path, $basedir ) ) {
					$paths[ $key ] = substr( $path, $offset );
				}
			}

			return $paths;
		}

		/**
		 * Encode file names according to RFC 3986 when generating urls
		 * As per Amazon https://forums.aws.amazon.com/thread.jspa?threadID=55746#jive-message-244233
		 *
		 * @param string $file
		 *
		 * @return string Encoded filename
		 */
		public static function encode_filename_in_path( $file ) {
			$url = parse_url( $file );

			if ( ! isset( $url['path'] ) ) {
				// Can't determine path, return original
				return $file;
			}

			if ( isset( $url['query'] ) ) {
				// Manually strip query string, as passing $url['path'] to basename results in corrupt � characters
				$file_name = wp_basename( str_replace( '?' . $url['query'], '', $file ) );
			} else {
				$file_name = wp_basename( $file );
			}

			if ( false !== strpos( $file_name, '%' ) ) {
				// File name already encoded, return original
				return $file;
			}

			$encoded_file_name = rawurlencode( $file_name );

			if ( $file_name === $encoded_file_name ) {
				// File name doesn't need encoding, return original
				return $file;
			}

			return str_replace( $file_name, $encoded_file_name, $file );
		}

		/**
		 * Get a file's real mime type
		 *
		 * @param string $file_path
		 *
		 * @return string
		 */
		public static function get_mime_type( $file_path ) {
			$file_type = wp_check_filetype_and_ext( $file_path, wp_basename( $file_path ) );

			return $file_type['type'];
		}

		/**
		 * Is this an AJAX process?
		 *
		 * @return bool
		 */
		public static function is_ajax() {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return true;
			}

			return false;
		}

		/**
		 * Is this a REST-API process?
		 *
		 * @return bool
		 */
		public static function is_rest_api() {
			if (
				( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
				( function_exists( 'wp_is_json_request' ) && wp_is_json_request() )
			) {
				return true;
			}

			return false;
		}

		/**
		 * Recursive version of the standard array_diff_assoc function.
		 *
		 * @see https://www.php.net/manual/en/function.array-diff-assoc.php#111675
		 *
		 * @param array $array1
		 * @param array $array2
		 *
		 * @return array
		 */
		public static function array_diff_assoc_recursive( $array1, $array2 ) {
			$difference = array();
			foreach ( $array1 as $key => $value ) {
				if ( is_array( $value ) ) {
					if ( ! isset( $array2[ $key ] ) || ! is_array( $array2[ $key ] ) ) {
						$difference[ $key ] = $value;
					} else {
						$new_diff = static::array_diff_assoc_recursive( $value, $array2[ $key ] );
						if ( ! empty( $new_diff ) ) {
							$difference[ $key ] = $new_diff;
						}
					}
				} elseif ( ! array_key_exists( $key, $array2 ) || $array2[ $key ] !== $value ) {
					$difference[ $key ] = $value;
				}
			}

			return $difference;
		}

		/**
		 * Renders an HTML select element.
		 *
		 * @param array $args
		 *
		 * @return string
		 */
		public static function make_dropdown( array $args ): string {
			if (
				empty( $args['id'] ) ||
				empty( $args['name'] ) ||
				empty( $args['label'] ) ||
				empty( $args['options'] ) ||
				! is_array( $args['options'] )
			) {
				return '';
			}

			$selected = isset( $args['selected'] ) && ! empty( $args['selected'] ) ? $args['selected'] : false;

			$html = sprintf(
				'<label for="%1$s" class="screen-reader-text">%2$s</label><select name="%3$s" id="%1$s">',
				$args['id'],
				$args['label'],
				$args['name']
			);

			foreach ( $args['options'] as $key => $option_value ) {
				if ( is_string( $option_value ) ) {
					$html .= sprintf(
						'<option value="%s" %s>%s</option>',
						$key,
						$selected !== false && $key === $selected ? 'selected' : '',
						$option_value
					);
				}

				if ( is_array( $option_value ) ) {
					$html .= sprintf(
						'<optgroup label="%s">',
						$key
					);

					foreach ( $option_value as $sub_key => $sub_option_value ) {
						$html .= sprintf(
							'<option value="%s" %s>%s</option>',
							$sub_key,
							$selected !== false && $sub_key === $selected ? 'selected' : '',
							$sub_option_value
						);
					}

					$html .= '</optgroup>';
				}
			}

			$html .= '</select> ';

			return $html;
		}

		/**
		 * If the given string is broken (optionally base64 encoded) serialized data,
		 * fix it,otherwise return untouched.
		 *
		 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		 *
		 * @param mixed $input
		 *
		 * @return mixed
		 */
		public static function maybe_fix_serialized_string( $input ) {
			if ( ! is_string( $input ) ) {
				return $input;
			}

			$output = $input;

			$base64 = false;
			if ( self::is_base64( $output ) ) {
				$base64 = true;
				$output = base64_decode( $output, true );
			}

			$is_slashed = false;
			if ( self::is_slashed_serialized( $output ) ) {
				$is_slashed = true;
				$output     = wp_unslash( $output );
			}

			if ( ! self::is_broken_serialized( $output ) ) {
				return $input;
			}

			$output = preg_replace_callback( '/s:(\d+):"(.*?)"\s?;/',
				array( __CLASS__, 'fix_serialized_matches' ),
				$output
			);

			if ( ! is_serialized( $output ) || self::is_broken_serialized( $output ) ) {
				return $input;
			}

			// If the data was slashed before then re-slash it.
			if ( $is_slashed ) {
				$output = wp_slash( $output );
			}

			// If it was base64 encoded then re-encode.
			if ( $base64 ) {
				$output = base64_encode( $output );
			}

			return $output;
		}

		/**
		 * Is the given string a usable URL?
		 *
		 * We need URLs that include at least a domain and filename with extension
		 * for URL rewriting in either direction.
		 *
		 * @param mixed $url
		 *
		 * @return bool
		 */
		public static function usable_url( $url ): bool {
			if ( ! static::is_url( $url ) ) {
				return false;
			}

			$parts = static::parse_url( $url );

			if (
				empty( $parts['host'] ) ||
				empty( $parts['path'] ) ||
				! pathinfo( $parts['path'], PATHINFO_EXTENSION )
			) {
				return false;
			}

			return true;
		}

		/**
		 * Join array elements with a string separator, using a separate separator
		 * for the last element
		 *
		 * @param string $separator
		 * @param string $last_separator
		 * @param array  $arr
		 *
		 * @return string
		 */
		public static function human_readable_join( string $separator, string $last_separator, array $arr ): string {
			if ( count( $arr ) < 2 ) {
				return join( $separator, $arr );
			}

			$last = array_pop( $arr );

			return join( $separator, $arr ) . $last_separator . $last;
		}

		/**
		 * Get all the blog IDs for the multisite network used for table prefixes.
		 *
		 * @return false|array
		 */
		public static function get_blog_ids() {
			if ( ! is_multisite() ) {
				return false;
			}

			$args = array(
				'limit'    => false, // Deprecated
				'number'   => false, // WordPress 4.6+
				'spam'     => 0,
				'deleted'  => 0,
				'archived' => 0,
			);

			$blogs    = get_sites( $args );
			$blog_ids = array();

			foreach ( $blogs as $blog ) {
				$blog       = (array) $blog;
				$blog_ids[] = (int) $blog['blog_id'];
			}

			return $blog_ids;
		}

		/**
		 * Get all the table prefixes for the blogs in the site. MS compatible.
		 *
		 * @param array $exclude_blog_ids blog ids to exclude
		 *
		 * @return array associative array with blog ID as key, prefix as value
		 */
		public static function get_all_blog_table_prefixes( array $exclude_blog_ids = array() ): array {
			global $wpdb;
			$prefix = $wpdb->prefix;

			$table_prefixes = array();

			if ( ! in_array( 1, $exclude_blog_ids ) ) {
				$table_prefixes[1] = $prefix;
			}

			if ( is_multisite() ) {
				$blog_ids = static::get_blog_ids();
				foreach ( $blog_ids as $blog_id ) {
					if ( in_array( $blog_id, $exclude_blog_ids ) ) {
						continue;
					}
					$table_prefixes[ $blog_id ] = $wpdb->get_blog_prefix( $blog_id );
				}
			}

			return $table_prefixes;
		}

		/**
		 * Fixes the broken string segments of a serialized data string.
		 *
		 * @param array $matches array of matched parts.
		 *
		 * @return string
		 */
		private static function fix_serialized_matches( array $matches ): string {
			return 's:' . strlen( $matches[2] ) . ':"' . $matches[2] . '";';
		}

		/**
		 * Tests whether given serialized data is broken or not.
		 *
		 * @param string $data Serialized data string.
		 *
		 * @return bool
		 */
		private static function is_broken_serialized( string $data ): bool {
			$broken = false;

			if ( is_serialized( $data ) ) {
				$value = @unserialize( $data ); // @phpcs:ignore

				if ( false === $value && serialize( false ) !== $data ) {
					$broken = true;
				}
			}

			return $broken;
		}

		/**
		 * Determine if a string is a slashed serialized string.
		 *
		 * @param string $data
		 *
		 * @return bool
		 */
		private static function is_slashed_serialized( string $data ): bool {
			if ( ! is_serialized( $data ) ) {
				return false;
			}

			$pattern      = '/s:\d+:\\\"/';
			$start_quotes = preg_match_all( $pattern, $data, $matches );
			if ( $start_quotes == 0 ) {
				return false;
			}

			$pattern    = '/\\\";(s|a|o|i|b|d|})./';
			$end_quotes = preg_match_all( $pattern, $data, $matches );
			if ( $end_quotes == 0 ) {
				return false;
			}

			$pattern    = '/\\\";}$/';
			$end_quotes += preg_match_all( $pattern, $data, $matches );
			if ( $start_quotes !== $end_quotes ) {
				return false;
			}

			return true;
		}

		/**
		 * Is the data a base64 encoded serialized string, object or JSON string?
		 *
		 * @param string $data
		 *
		 * @return bool
		 */
		private static function is_base64( string $data ): bool {
			if ( ! empty( $data ) && base64_encode( base64_decode( $data, true ) ) === $data ) {
				$data = base64_decode( $data, true );

				if ( is_serialized( $data ) || is_object( $data ) || self::is_json( $data ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Can the supplied string be treated as JSON?
		 *
		 * @param string $value
		 *
		 * @return bool
		 */
		private static function is_json( string $value ): bool {
			$is_json = false;

			if ( 0 < strlen( trim( $value ) ) && ! is_numeric( $value ) && null !== json_decode( $value ) ) {
				$is_json = true;
			}

			return $is_json;
		}
	}
}
