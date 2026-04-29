=== SQLite Database Integration ===

Contributors:      wordpressdotorg, aristath, janjakes, zieladam, berislav.grgicak, bpayton, zaerl
Requires at least: 6.4
Tested up to:      6.9
Requires PHP:      7.2
Stable tag:        3.0.0-rc.3
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, database

SQLite integration plugin by the WordPress Team.

== Description ==

The SQLite plugin is a community, feature plugin. The intent is to allow testing an SQLite integration with WordPress and gather feedback, with the goal of eventually landing it in WordPress core.

== Frequently Asked Questions ==

= What is the purpose of this plugin? =

The primary purpose of the SQLite plugin is to allow testing the use of an SQLite database, with the goal to eventually land in WordPress core.

You can read the original proposal on the [Make blog](https://make.wordpress.org/core/2022/09/12/lets-make-wordpress-officially-support-sqlite/), as well as the [call for testing](https://make.wordpress.org/core/2022/12/20/help-us-test-the-sqlite-implementation/) for more context and useful information.

= Can I use this plugin on my production site? =

Per the primary purpose of the plugin (see above), it can mostly be considered a beta testing plugin. To a degree, it should be okay to use it in production. However, as with every plugin, you are doing so at your own risk.

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin is a future WordPress core feature. If you need help with troubleshooting or have a question, suggestions, or requests, you can [submit them as an issue in the SQLite GitHub repository](https://github.com/wordpress/sqlite-database-integration/issues/new).

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

= Does this plugin change how WordPress queries are executed? =

The plugin replaces the default MySQL-based database layer with an
SQLite-backed implementation. Core WordPress code continues to use
the wpdb API, while queries are internally adapted to be compatible
with SQLite syntax and behavior.

== Changelog ==

= 3.0.0-rc.3 =

* Lexer: Fix possible OOB read in quoted strings ([#374](https://github.com/WordPress/sqlite-database-integration/pull/374))
* Add support for `NO_AUTO_VALUE_ON_ZERO` SQL mode ([#366](https://github.com/WordPress/sqlite-database-integration/pull/366))

= 3.0.0-rc.2 =

* Support MySQL `BINARY` operator ([#369](https://github.com/WordPress/sqlite-database-integration/pull/369))
* Add support for `AUTO_INCREMENT` value management ([#367](https://github.com/WordPress/sqlite-database-integration/pull/367))
* Add support for `DELETE` with `LIMIT` and `ORDER BY` ([#365](https://github.com/WordPress/sqlite-database-integration/pull/365))

= 3.0.0-rc.1 =

* Improve concurrent database access ([#361](https://github.com/WordPress/sqlite-database-integration/pull/361))
* Remove legacy SQLite driver ([#358](https://github.com/WordPress/sqlite-database-integration/pull/358))

= 2.2.23 =

* Add Query Monitor 4.0 support ([#357](https://github.com/WordPress/sqlite-database-integration/pull/357))
* Translate MySQL CONVERT() expressions to SQLite ([#356](https://github.com/WordPress/sqlite-database-integration/pull/356))

= 2.2.22 =

* Support INSERT without INTO keyword ([#354](https://github.com/WordPress/sqlite-database-integration/pull/354))
* Add tests for MySQL row-level locking clauses ([#342](https://github.com/WordPress/sqlite-database-integration/pull/342))
* Improve automated deploy setup.

= 2.2.21 =

* Monorepo setup + release automation ([#334](https://github.com/WordPress/sqlite-database-integration/pull/334))
* Rework release workflow ([#350](https://github.com/WordPress/sqlite-database-integration/pull/350))
* Fix incorrect PHP polyfill implementations ([#338](https://github.com/WordPress/sqlite-database-integration/pull/338))
