<?php
/**
 * Polyfills for php 7 & 8 functions
 *
 * @package wp-sqlite-integration
 */

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Check if a string starts with a specific substring.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The string to search for.
	 *
	 * @see https://www.php.net/manual/en/function.str-starts-with
	 *
	 * @return bool
	 */
	function str_starts_with( string $haystack, string $needle ) {
		return empty( $needle ) || 0 === strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Check if a string contains a specific substring.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The string to search for.
	 *
	 * @see https://www.php.net/manual/en/function.str-contains
	 *
	 * @return bool
	 */
	function str_contains( string $haystack, string $needle ) {
		return empty( $needle ) || false !== strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Check if a string ends with a specific substring.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The string to search for.
	 *
	 * @see https://www.php.net/manual/en/function.str-ends-with
	 *
	 * @return bool
	 */
	function str_ends_with( string $haystack, string $needle ) {
		return empty( $needle ) || substr( $haystack, -strlen( $needle ) === $needle );
	}
}
