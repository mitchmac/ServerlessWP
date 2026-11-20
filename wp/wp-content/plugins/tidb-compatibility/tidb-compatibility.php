<?php
/*
 * Plugin Name:       TiDB Compatibility
 * Description:       Optimize slow queries in WordPress.
 * Version:           1.0.3
 * Requires at least: 4.7
 * Requires PHP:      7.0
 * Author:            it2911
 * Author URI:        https://github.com/it2911
 * License:           GPL-3.0 license
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tidb-compatibility
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WTC_FIX_WP_SLOW_QUERY {
        public static function wtc_init() {
                if ( ! self::wtc_is_enabled() ) {
                        return;
                }
                /**
                 * WP_Query
                 */
                add_filter( 'found_posts_query', [ __CLASS__, 'wtc_add_found_rows_query' ], 999, 2 );
                add_filter( 'found_posts', [ __CLASS__, 'wtc_check_found_rows_query' ], 999, 2 );
                add_filter( 'posts_request_ids', [ __CLASS__, 'wtc_remove_found_rows_query' ], 999 );
                add_filter( 'posts_pre_query', function ( $posts, \WP_Query $query ) {
                        $query->request = self::wtc_remove_found_rows_query( $query->request );
                        return $posts;
                }, 999, 2 );
        }
        /**
         * Only rewrite queries when WordPress runs on TiDB. Define WTC_DISABLE
         * as true in wp-config.php to switch the rewrite off, or use the
         * 'tidb_compatibility_enabled' filter to override detection.
         */
        public static function wtc_is_enabled() {
                if ( defined( 'WTC_DISABLE' ) && WTC_DISABLE ) {
                        self::wtc_log( 'disabled by WTC_DISABLE.' );
                        return false;
                }
                global $wpdb;
                $version = method_exists( $wpdb, 'db_server_info' ) ? $wpdb->db_server_info() : $wpdb->get_var( 'SELECT VERSION()' );
                $is_tidb = false !== stripos( (string) $version, 'TiDB' );
                $enabled = (bool) apply_filters( 'tidb_compatibility_enabled', $is_tidb, $version );
                if ( ! $enabled ) {
                        self::wtc_log( "inactive, database server is '$version'." );
                }
                return $enabled;
        }
        /**
         * Strip only the SQL_CALC_FOUND_ROWS keyword that WP_Query puts right
         * after the leading SELECT, so string literals such as search terms
         * are never touched.
         */
        public static function wtc_remove_found_rows_query( $sql ) {
                return preg_replace( '/^(\s*SELECT\s+)SQL_CALC_FOUND_ROWS\b\s*/i', '$1', (string) $sql, 1 );
        }
        /**
         * Count the rows of the query that was actually executed, instead of
         * rebuilding it from clauses captured by a filter. Rebuilt clauses can
         * be missing (suppress_filters skips 'posts_clauses') or incomplete
         * ('posts_where_request', 'posts_clauses_request' and 'posts_request'
         * run later), which made found_posts count posts the user cannot see.
         */
        public static function wtc_add_found_rows_query( $sql, WP_Query $query ) {
                $request = self::wtc_remove_found_rows_query( (string) $query->request );
                $request = preg_replace(
                        '/\s+LIMIT\s+\d+(?:\s*,\s*\d+|\s+OFFSET\s+\d+)?\s*;?\s*$/i',
                        '',
                        $request,
                        1,
                        $replaced
                );
                if ( 1 !== $replaced ) {
                        // Unknown request shape: keep the core query rather than guess.
                        self::wtc_log( 'no trailing LIMIT found, kept core found_posts_query for: ' . $query->request );
                        return $sql;
                }
                return "SELECT COUNT(*) FROM ( $request ) AS wtc_found_rows";
        }
        public static function wtc_check_found_rows_query( $found_posts, $query ) {
                global $wpdb;
                if ( '' !== $wpdb->last_error && false !== strpos( (string) $wpdb->last_query, 'AS wtc_found_rows' ) ) {
                        self::wtc_log( 'count query failed: ' . $wpdb->last_error );
                }
                return $found_posts;
        }
        public static function wtc_log( $message ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( '[TiDB Compatibility] ' . $message );
                }
        }
}
register_activation_hook( __FILE__, function () {
        error_log( '[TiDB Compatibility] activated by user ' . get_current_user_id() . '.' );
} );
register_deactivation_hook( __FILE__, function () {
        error_log( '[TiDB Compatibility] deactivated by user ' . get_current_user_id() . '.' );
} );
add_action( 'plugins_loaded', [ 'WTC_FIX_WP_SLOW_QUERY', 'wtc_init' ] );
