=== TiDB Compatibility ===
Contributors: it2911
Tags: tidb, database, sql
Requires at least: 4.7
Tested up to: 6.8.1
Stable tag: 1.0.3
Requires PHP: 7.0
License: GPL-3.0 license
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin is an official tool provided by PingCAP, designed to solve compatibility issues between TiDB and WordPress.

== Description ==

TiDB is a high-performance database that is compatible with the MySQL protocol. Since MySQL has deprecated the SQL_CALC_FOUND_ROWS function, TiDB also has no intention of offering the SQL_CALC_FOUND_ROWS function. This leads to an error in WordPress when using TiDB, indicating that SQL_CALC_FOUND_ROWS is not supported, and submissions cannot be displayed correctly.

WordPress is also currently working on this issue, but it seems that more time is needed. #47280 Remove usage of deprecated MySQL SQL_CALC_FOUND_ROWS from WP_Query

This plugin solves the issue of TiDB not providing the SQL_CALC_FOUND_ROWS function. Once this plugin is activated, parts of WP_Query that use SQL_CALC_FOUND_ROWS will be replaced with the COUNT(*) function.

This plugin is entirely based on the method mentioned by @akramipro in the article, and this solution works perfectly and addresses the issue. I've turned this method into a plugin so that those using TiDB can easily resolve this problem. Many thanks to @akramipro for the excellent work, and I hope the official WordPress team can address this issue sooner.

== Configuration ==

The plugin only rewrites queries when the database server reports itself as TiDB.

* Define `WTC_DISABLE` as `true` in `wp-config.php` to switch the rewrite off.
* Use the `tidb_compatibility_enabled` filter (from a plugin or must-use plugin, not a theme) to override the TiDB detection.
* With `WP_DEBUG` enabled, the plugin logs to the PHP error log when it is inactive, when it keeps the core count query, and when its count query fails. Activation and deactivation are always logged.

== Contribute ==

Contribute to this plugin on [github.com/pingcap/wordpress-tidb-plugin](https://github.com/pingcap/wordpress-tidb-plugin)

== Changelog ==

= 1.0.3 =

* Security: count found posts from the executed query, so pagination totals no longer include posts hidden by query filters or suppress_filters
* Security: only strip the leading SQL_CALC_FOUND_ROWS keyword, so string literals in queries are never modified
* Only activate on TiDB, add the WTC_DISABLE constant and tidb_compatibility_enabled filter
* Log activation, deactivation and count query fallbacks or failures

= 1.0.2 =

* Update README, tags

= 1.0.1 =

* Update README, tags and compatibility info
