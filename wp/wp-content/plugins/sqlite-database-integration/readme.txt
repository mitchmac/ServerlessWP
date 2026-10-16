=== SQLite Database Integration ===

Contributors:      wordpressdotorg, aristath, janjakes, zieladam, berislav.grgicak, bpayton, zaerl
Requires at least: 6.4
Tested up to:      7.1
Requires PHP:      7.2
Stable tag:        3.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              sqlite, database

Run WordPress on SQLite.

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

= Where is the SQLite database stored? =

By default, the SQLite database is stored in a **randomized path** under `WP_CONTENT_DIR . '/database'`. The full path is recorded in `db-path.php` and exposed by the `DB_PATH` constant at runtime.

For example:

1. Database path: `WP_CONTENT_DIR . '/database/.ht.020a33c5d9e5407e8e93b43e55abf62e/.ht.sqlite'`
2. Recorded in: `WP_CONTENT_DIR . '/database/db-path.php'` as `return __DIR__ . '/...';`
3. Exposed by: `DB_PATH`

The random path makes the database location difficult to guess when it is not otherwise protected. We recommend setting `DB_PATH` to an **explicit, protected path** outside the web root that your web server does not expose.

Integrations should always read `DB_PATH` after WordPress loads instead of assuming a fixed database path.

= How can I configure the database location? =

To properly **secure the SQLite database**, define the `DB_PATH` constant to an **explicit path** that is protected from public web access. An explicit `DB_PATH` value is used as-is without randomization. Define it in `wp-config.php`:

    // Use protected DB path that is not exposed by the web server.
    define( 'DB_PATH', '/private/wordpress/database.sqlite' );

**Caution:** An explicit `DB_PATH` must point to a **protected** location that your web server does not expose. Store the database outside the web root and make sure it's protected.

The value of `DB_PATH` must be an absolute path to the SQLite database file, or `:memory:` for an in-memory SQLite database. The database directory must be writable by PHP. Changing `DB_PATH` selects a different database without moving an existing one.

= What does the plugin require? =

In addition to the WordPress and PHP versions listed above, the plugin requires the PDO SQLite PHP extension and SQLite 3.37.0 or newer. The setup screen also needs write access to the `wp-content` directory and will detect conflicting database drop-ins.

= Where can I submit my plugin feedback? =

Feedback helps improve the integration. For troubleshooting, questions, suggestions, or feature requests, [open an issue in the SQLite GitHub repository](https://github.com/wordpress/sqlite-database-integration/issues/new).

= How can I contribute to the plugin? =

Contributions are welcome through the [SQLite Database Integration repository on GitHub](https://github.com/WordPress/sqlite-database-integration).

= Does this plugin change how WordPress queries are executed? =

Yes. The plugin replaces the default MySQL-based database layer with an SQLite-backed implementation. WordPress continues to use the `wpdb` API, while queries are internally adapted to SQLite syntax and behavior.

== Changelog ==

= 3.1.0 =

**SQLite Database Integration 3.1 is here! 🎉**

This release improves **database storage and configuration** and fixes several MySQL compatibility issues.

**What's new**

Version 3.1 improves how WordPress sites store, locate, and protect their SQLite databases. It also fixes SQL behavior and export compatibility:

* **Randomized database paths:** The default database location is now a randomized directory under `wp-content/database/`, recorded in `wp-content/database/db-path.php`. ([#502](https://github.com/WordPress/sqlite-database-integration/pull/502))
* **`DB_PATH`:** Configure the database with one full-path constant, also available at runtime for integrations. ([#512](https://github.com/WordPress/sqlite-database-integration/pull/512))
* **SQL compatibility:** Fix `IF()` condition evaluation and make `TRADITIONAL` enable its component SQL modes. ([#518](https://github.com/WordPress/sqlite-database-integration/pull/518), [#509](https://github.com/WordPress/sqlite-database-integration/pull/509))
* **WordPress table collations:** Default to `utf8mb4_unicode_520_ci` for new tables created with WordPress's charset settings, improving exports to MariaDB. Existing tables keep their recorded collation. ([#514](https://github.com/WordPress/sqlite-database-integration/pull/514))
* **Documentation:** A new plugin README and expanded FAQ explain database storage and secure configuration. ([#520](https://github.com/WordPress/sqlite-database-integration/pull/520))

For more information about database paths and secure configuration, read the [database storage guide](https://github.com/WordPress/sqlite-database-integration/blob/trunk/packages/plugin-sqlite-database-integration/README.md#database-storage).

**Upgrading to 3.1**

Upgrading an existing SQLite site is straightforward:

1. **Back up** your SQLite database.
2. **Update the plugin** to version 3.1.

Existing `.ht.sqlite` and `.ht.sqlite.php` databases move to the randomized layout automatically unless a database file path is explicitly configured.

To properly **secure the database**, set `DB_PATH` in `wp-config.php` to an absolute file path outside the web root that your web server does not expose. Its directory must be writable by PHP. Changing `DB_PATH` does not move an existing database.

**Breaking changes**

Review these changes if you use custom database settings or integrations:

* **Database paths:** Default database files now move to randomized paths under `wp-content/database/`. Explicitly configured file paths stay unchanged. Integrations, including backup and migration tools, must read `DB_PATH` after WordPress loads instead of assuming a fixed filename.
* **Legacy constants:** `DB_DIR` and `DB_FILE` are now deprecated. They and the previously deprecated `FQDB` and `FQDBDIR` remain supported, but `DB_PATH` takes precedence. Conflicting values trigger warnings.
* **Absolute paths:** Relative database file and directory paths are now rejected. `:memory:` remains available for in-memory databases.

**Thank you**

Thank you to everyone who contributed, tested, and helped update integrations.

**Changes since 3.0.2:** [`v3.0.2...v3.1.0`](https://github.com/WordPress/sqlite-database-integration/compare/v3.0.2...v3.1.0)

= 3.0.2 =

* Fix schema reconstruction with native numeric results ([#506](https://github.com/WordPress/sqlite-database-integration/pull/506))
* Fix lexer edge cases and string handling ([#505](https://github.com/WordPress/sqlite-database-integration/pull/505))
* Preserve index prefix lengths and order in `SHOW CREATE TABLE` primary keys ([#500](https://github.com/WordPress/sqlite-database-integration/pull/500))
* Align savepoint handling with MySQL semantics ([#496](https://github.com/WordPress/sqlite-database-integration/pull/496))

= 3.0.1 =

* Mark the plugin as compatible with WordPress 7.1 ([#497](https://github.com/WordPress/sqlite-database-integration/pull/497))
* Fix fresh multisite database setup ([#492](https://github.com/WordPress/sqlite-database-integration/pull/492))

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
