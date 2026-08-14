=== SQLite Database Integration ===

Contributors:      wordpressdotorg, aristath, janjakes, zieladam, berislav.grgicak, bpayton, zaerl
Requires at least: 6.4
Tested up to:      7.0
Requires PHP:      7.2
Stable tag:        3.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              sqlite, database

Run WordPress on SQLite instead of MySQL or MariaDB.

== Description ==

**Run WordPress on SQLite.**

The SQLite plugin is a community, feature plugin. The intent is to allow testing an SQLite integration with WordPress and gather feedback, with the goal of eventually landing it in WordPress core.

The plugin replaces the default MySQL database layer with an SQLite-backed implementation. WordPress continues to use its standard `wpdb` API while the plugin translates and emulates MySQL queries for SQLite.

= What the plugin provides =

* **No database server.** Your site's data is stored in a local SQLite database.
* **Guided setup.** The installation screen checks for the required PHP extension, write access, and conflicting database drop-ins before enabling SQLite.
* **Purpose-built compatibility.** A MySQL parser and emulation layer adapt MySQL syntax and behavior for SQLite.
* **Useful diagnostics.** SQLite details appear in Site Health, and Query Monitor is supported.

**Important:** Enabling SQLite creates a separate, empty database; it does not migrate an existing site. If you enable it on a site that uses MySQL or MariaDB, WordPress will ask you to set up the site again. Disabling the plugin reconnects WordPress to the previous database with its data unchanged. Changes made while using SQLite are not transferred.

The plugin requires the PDO SQLite PHP extension and SQLite 3.37.0 or newer.

== Frequently Asked Questions ==

= What is the purpose of this plugin? =

The primary purpose of the plugin is to test WordPress with SQLite and gather feedback, with the goal of eventually including the integration in WordPress core.

Read the original proposal on the [Make WordPress Core blog](https://make.wordpress.org/core/2022/09/12/lets-make-wordpress-officially-support-sqlite/) and the [call for testing](https://make.wordpress.org/core/2022/12/20/help-us-test-the-sqlite-implementation/) for more context.

= Can I use this plugin on my production site? =

Yes, but keep reliable backups and make sure SQLite is a good fit for your site's traffic. SQLite supports concurrent reads but allows only one writer at a time, so MySQL or MariaDB may be a better fit for sites with heavy concurrent write traffic. Test your workload, themes, and plugins before switching.

= Will this plugin migrate my existing site to SQLite? =

No. Enabling SQLite starts a fresh WordPress installation in a separate database. Your existing MySQL or MariaDB database remains unchanged, but its content is not copied to SQLite.

Disabling the plugin reconnects WordPress to the previous database. Content created while using SQLite is not transferred back.

= What does the plugin require? =

In addition to the WordPress and PHP versions listed above, the plugin requires the PDO SQLite PHP extension and SQLite 3.37.0 or newer. The setup screen also needs write access to the `wp-content` directory and will detect conflicting database drop-ins.

= Where can I submit my plugin feedback? =

Feedback helps improve the integration. For troubleshooting, questions, suggestions, or feature requests, [open an issue in the SQLite GitHub repository](https://github.com/wordpress/sqlite-database-integration/issues/new).

= How can I contribute to the plugin? =

Contributions are welcome through the [SQLite Database Integration repository on GitHub](https://github.com/WordPress/sqlite-database-integration).

= Does this plugin change how WordPress queries are executed? =

Yes. The plugin replaces the default MySQL-based database layer with an SQLite-backed implementation. WordPress continues to use the `wpdb` API, while queries are internally adapted to SQLite syntax and behavior.

== Changelog ==

= 3.0.0 =

**SQLite Database Integration 3 is here! 🎉**

This release introduces an **all-new SQLite database driver for WordPress**, rebuilt from the ground up. Its purpose-built MySQL lexer, parser, and emulation layer deliver broader compatibility, more accurate behavior, and a stronger foundation for future improvements.

The 3.0 release spans nearly two years of work, featuring [160 pull requests](https://github.com/WordPress/sqlite-database-integration/milestone/3?closed=1) and 1,075 commits from 15 contributors.

**What's new**

The new driver advances SQLite support for WordPress, plugins, database tools, and other MySQL-based applications. These improvements include:

* **New SQL engine:** The pure-PHP lexer and parser provide extensive coverage of the official MySQL grammar.
* **Broad query support:** Complex joins, subqueries, CTEs, unions, and other advanced queries are now supported.
* **Schema emulation:** WordPress and database tools can query emulated MySQL `INFORMATION_SCHEMA` tables.
* **Schema introspection:** `SHOW` and `DESCRIBE` statements provide accurate MySQL-like metadata.
* **Improved data handling:** Types, casts, defaults, auto-increment, values, and escaping better match MySQL.
* **Refined MySQL semantics:** Better emulation of SQL modes, variables, functions, transactions, and errors.
* **Better concurrency:** Write-ahead logging and fewer locks reduce blocking between readers and writers.
* **PDO API:** The new driver implements the PDO MySQL API, supporting many MySQL-based tools and applications.
* **Extensive testing:** Test suites cover parsing, translation, metadata, concurrency, PDO, and end-to-end workflows.

For more information about the new driver and its architecture, read the [driver announcement](https://make.wordpress.org/playground/2025/06/13/introducing-a-new-sqlite-driver-for-wordpress/).

**Modular design**

The project was redesigned as a set of focused packages, separating the core driver from its WordPress integration:

* [SQLite Database Integration](https://github.com/WordPress/sqlite-database-integration/tree/v3.0.0/packages/plugin-sqlite-database-integration): The **WordPress plugin** powered by MySQL on SQLite.
* [MySQL on SQLite](https://github.com/WordPress/sqlite-database-integration/tree/v3.0.0/packages/mysql-on-sqlite): A standalone **PDO MySQL drop-in** for running MySQL-based PHP applications on SQLite.
* [MySQL proxy](https://github.com/WordPress/sqlite-database-integration/tree/v3.0.0/packages/mysql-proxy) (experimental): A **MySQL wire protocol bridge** to PDO-compatible drivers for clients outside PHP.

This architecture opens the driver to new integrations, applications, and development tools beyond WordPress.

**Upgrading to 3.0**

Upgrading an existing SQLite site is straightforward:

1. **Back up** your SQLite database.
2. **Update the plugin** to version 3.0.

On first connection, the new driver automatically initializes its metadata without changing your tables or content.

If you used the new driver preview, you can now delete the `WP_SQLITE_AST_DRIVER` flag.

**Breaking changes**

Most WordPress sites need no changes. Review the following if you use a custom setup:

* **New driver:**
    * The new driver is always used. The legacy driver was removed.
    * The `WP_SQLITE_AST_DRIVER` feature flag was removed.
* **Updated SQLite version requirements:**
    * SQLite `3.37.0` or newer is required.
    * The `WP_SQLITE_UNSAFE_ENABLE_UNSUPPORTED_VERSIONS` opt-in enables limited SQLite `3.27.0`–`3.36.x` support.
* **`DB_NAME` is required:**
    * It must be defined and non-empty.
    * It is used dynamically, independently of the SQLite file name and stored metadata.
* **SQLite defaults have changed:**
    * Journal mode now defaults to [`WAL`](https://sqlite.org/wal.html). Account for `-wal` and `-shm` sidecar files.
    * Synchronous mode now defaults to [`NORMAL`](https://sqlite.org/pragma.html#pragma_synchronous) in `WAL` mode.
* **Updated configuration constants:**
    * `DATABASE_ENGINE` was removed. Use `DB_ENGINE`.
    * `DATABASE_TYPE` is deprecated. Use `DB_ENGINE`.
    * `FQDBDIR` is deprecated. Use `DB_DIR`.
    * `FQDB` is deprecated. Use `DB_DIR` and `DB_FILE`.
* **Updated driver classes:**
    * `WP_SQLite_Driver` is deprecated. Use `WP_MySQL_On_SQLite`.
    * `WP_PDO_MySQL_On_SQLite` was replaced by `WP_MySQL_On_SQLite`.
    * `WP_SQLite_Driver_Exception` was replaced by `WP_MySQL_On_SQLite_Exception`.
    * `WP_PDO_Proxy_Statement` was replaced by `WP_MySQL_On_SQLite_Statement`.
* **Sunsetting `$GLOBALS['@pdo']`:**
    * Injecting a PDO connection through `$GLOBALS['@pdo']` is no longer supported.
    * Reading `$GLOBALS['@pdo']` is deprecated. Use `$wpdb->get_driver()->get_sqlite_pdo()`.
* **Renamed driver constructor options:**
    * `pdo` → `sqlite_pdo`.
    * `journal_mode` → `sqlite_journal_mode`.
    * `synchronous` → `sqlite_synchronous`.

**Thank you**

Thank you to everyone who helped build, test, review, and improve the new driver. Your work made this possible.

**3.0 milestone:** [160 pull requests](https://github.com/WordPress/sqlite-database-integration/milestone/3?closed=1)

**Changes since 2.2.23:** [`v2.2.23...v3.0.0`](https://github.com/WordPress/sqlite-database-integration/compare/v2.2.23...v3.0.0)

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

== Upgrade Notice ==

= 3.0.0 =

Major release with an all-new SQLite driver. Requires SQLite 3.37.0 or newer. Back up your SQLite database before updating.
