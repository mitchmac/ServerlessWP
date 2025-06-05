<?php
/*
 * Plugin Name:       WordPress TiDB Compatibility
 * Description:       Optimize slow queries in WordPress.
 * Version:           1.0.1
 * Requires at least: 4.7
 * Requires PHP:      6.8.1
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
                /**
                 * WP_Query
                 */
                add_filter( 'found_posts_query', [ __CLASS__, 'wtc_add_found_rows_query' ], 999, 2 );
                add_filter( 'posts_request_ids', [ __CLASS__, 'wtc_remove_found_rows_query' ], 999 );
                add_filter( 'posts_pre_query', function ( $posts, \WP_Query $query ) {
                        $query->request = self::wtc_remove_found_rows_query( $query->request );
                        return $posts;
                }, 999, 2 );
                add_filter( 'posts_clauses', function ( $clauses, \WP_Query $wp_query ) {
                        $wp_query->fw_clauses = $clauses;
                        return $clauses;
                }, 999, 2 );
        }
        public static function wtc_remove_found_rows_query( $sql ) {
                return str_replace( ' SQL_CALC_FOUND_ROWS ', '', $sql );
        }
        public static function wtc_add_found_rows_query( $sql, WP_Query $query ) {
                global $wpdb;
                $distinct = $query->fw_clauses['distinct'] ?? '';
                $join     = $query->fw_clauses['join'] ?? '';
                $where    = $query->fw_clauses['where'] ?? '';
                $groupby  = $query->fw_clauses['groupby'] ?? '';
                $count = 'COUNT(*)';
                if ( ! empty( $groupby ) ) {
                        $count = "COUNT( distinct $groupby )";
                }
                return "
                        SELECT $distinct $count
                        FROM {$wpdb->posts} $join
                        WHERE 1=1 $where
                ";
        }
}
WTC_FIX_WP_SLOW_QUERY::wtc_init();
