<?php

require_once __DIR__ . '/wp-sqlite-schema.php';
require_once __DIR__ . '/../wp-includes/mysql/class-wp-mysql-token.php';
require_once __DIR__ . '/../wp-includes/mysql/class-wp-mysql-lexer.php';
require_once __DIR__ . '/../wp-includes/parser/class-wp-parser-grammar.php';
require_once __DIR__ . '/../wp-includes/parser/class-wp-parser.php';
require_once __DIR__ . '/../wp-includes/parser/class-wp-parser-node.php';
require_once __DIR__ . '/../wp-includes/mysql/class-wp-mysql-parser.php';
require_once __DIR__ . '/../wp-includes/sqlite/class-wp-sqlite-query-rewriter.php';
require_once __DIR__ . '/../wp-includes/sqlite/class-wp-sqlite-lexer.php';
require_once __DIR__ . '/../wp-includes/sqlite/class-wp-sqlite-token.php';
require_once __DIR__ . '/../wp-includes/sqlite/class-wp-sqlite-pdo-user-defined-functions.php';
require_once __DIR__ . '/../wp-includes/sqlite/class-wp-sqlite-translator.php';

/**
 * Polyfills for WordPress functions
 */
if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Polyfill the do_action function.
	 */
	function do_action() {}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Polyfill the apply_filters function.
	 *
	 * @param string $tag The filter name.
	 * @param mixed  $value The value to filter.
	 * @param mixed  ...$args Additional arguments to pass to the filter.
	 *
	 * @return mixed Returns $value.
	 */
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

/**
 * Polyfills for php 7 & 8 functions
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
		return empty( $needle ) || substr( $haystack, -strlen( $needle ) ) === $needle;
	}
}
if ( extension_loaded( 'mbstring' ) ) {

	if ( ! function_exists( 'mb_str_starts_with' ) ) {
		/**
		 * Polyfill for mb_str_starts_with.
		 *
		 * @param string $haystack The string to search in.
		 * @param string $needle   The string to search for.
		 *
		 * @return bool
		 */
		function mb_str_starts_with( string $haystack, string $needle ) {
			return empty( $needle ) || 0 === mb_strpos( $haystack, $needle );
		}
	}

	if ( ! function_exists( 'mb_str_contains' ) ) {
		/**
		 * Polyfill for mb_str_contains.
		 *
		 * @param string $haystack The string to search in.
		 * @param string $needle   The string to search for.
		 *
		 * @return bool
		 */
		function mb_str_contains( string $haystack, string $needle ) {
			return empty( $needle ) || false !== mb_strpos( $haystack, $needle );
		}
	}

	if ( ! function_exists( 'mb_str_ends_with' ) ) {
		/**
		 * Polyfill for mb_str_ends_with.
		 *
		 * @param string $haystack The string to search in.
		 * @param string $needle   The string to search for.
		 *
		 * @return bool
		 */
		function mb_str_ends_with( string $haystack, string $needle ) {
			// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			return empty( $needle ) || $needle = mb_substr( $haystack, - mb_strlen( $needle ) );
		}
	}
}
