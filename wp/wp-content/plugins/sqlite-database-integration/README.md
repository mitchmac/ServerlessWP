# SQLite Database Integration

Run WordPress on SQLite.

## Overview

The plugin provides a `wp-content/db.php` database drop-in powered by
[MySQL on SQLite](../mysql-on-sqlite/). WordPress and plugins continue to use the
`wpdb` API while the driver translates and emulates MySQL queries for SQLite.

## Usage

To set up SQLite, do the following steps in your WordPress admin:

1. Install and activate the plugin.
2. Open **Settings > SQLite integration** and select **Install SQLite database**.

On multisite installs, use Network Admin.

> [!IMPORTANT]
> Enabling SQLite creates a separate SQLite database. It does not migrate data
> from MySQL or MariaDB. Disabling it reconnects WordPress to the original database.

See [readme.txt](readme.txt) for the plugin FAQ and changelog.

## Database storage

By default, the SQLite database is stored in a **randomized path** under
`WP_CONTENT_DIR . '/database'`. The full path is recorded in `db-path.php`
and exposed by the `DB_PATH` constant at runtime.

For example:

1. Database path: `WP_CONTENT_DIR . '/database/.ht.020a33c5d9e5407e8e93b43e55abf62e/.ht.sqlite'`
2. Recorded in: `WP_CONTENT_DIR . '/database/db-path.php'` as `return __DIR__ . '/...';`
3. Exposed by: `DB_PATH`

The random path makes the database location difficult to guess when it is not
otherwise protected.

To properly **secure the SQLite database**, define the `DB_PATH` constant to an
**explicit path** that is protected from public web access. An explicit `DB_PATH`
value is used as-is without randomization. Define it in `wp-config.php`:

```php
// Use protected DB path that is not exposed by the web server.
define( 'DB_PATH', '/private/wordpress/database.sqlite' );
```

> [!CAUTION]
> An explicit `DB_PATH` must point to a **protected** location that your web server
> does not expose. Store the database outside the web root and make sure it's protected.

The value of `DB_PATH` must be an absolute path to the SQLite database file, or `:memory:`
for an in-memory SQLite database. The database directory must be writable by PHP. Changing
`DB_PATH` selects a different database without moving an existing one.

Integrations should always read `DB_PATH` after WordPress loads instead of assuming
a fixed database path.

## Development

Run these commands from the repository root:

```bash
composer install
composer run check-cs
composer run wp-test-sqlite-plugin-php
composer run wp-test-sqlite-plugin-php-multisite
```

See the [repository README](../../README.md#quick-start) for the WordPress test
environment commands.

Build an installable plugin ZIP with:

```bash
composer run build-sqlite-plugin-zip
```

The archive is written to `build/plugin-sqlite-database-integration.zip`.

## Requirements

- **WordPress:** 6.4+
- **PHP:** 7.2+
- **PHP extensions:** `pdo`, `pdo_sqlite`
- **SQLite:** 3.37.0+

## License

SQLite Database Integration is licensed under the
[GNU General Public License v2 or later](LICENSE).
