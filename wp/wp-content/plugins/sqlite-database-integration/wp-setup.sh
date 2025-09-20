#!/bin/bash

##
# This script prepares the WordPress repository for tests and development.
# It clones the WordPress repository and makes sure that the SQLite plugin
# is used in the development and testing environment instead of MySQL.
##

set -e

WP_VERSION="6.7.2"

DIR="$(dirname "$0")"
WP_DIR="$DIR/wordpress"

# 1. Ensure that Git is installed.
echo "Checking if Git is installed..."
if ! command -v git &> /dev/null; then
	echo 'Error: Git is not installed.' >&2
	exit 1
fi

# 2. Clone the WordPress repository, if it doesn't exist.
echo "Cleaning up the WordPress repository..."
rm -rf "$WP_DIR"
echo "Cloning the WordPress repository..."
git clone --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/wordpress-develop.git "$WP_DIR"

# 3. Add "docker-compose.override.yml" to the WordPress repository.
echo "Adding 'docker-compose.override.yml' to the WordPress repository..."
cat << EOF > "$WP_DIR/docker-compose.override.yml"
services:
  wordpress-develop:
    environment:
      WP_SQLITE_AST_DRIVER: true
    volumes:
      - ../:/var/www/src/wp-content/plugins/sqlite-database-integration

  php:
    # PHP temporarily pinned to 8.3.10, see: https://github.com/WordPress/wordpress-develop/pull/9602
    image: wordpressdevelop/php@sha256:c0ba85936a9d1ac2c98bf3da2d62ceb0e5787a6b11e383630df0c5a5bf2534b5
    environment:
      WP_SQLITE_AST_DRIVER: true
    volumes:
      - ../:/var/www/src/wp-content/plugins/sqlite-database-integration

  cli:
    # PHP temporarily pinned to 8.3.10, see: https://github.com/WordPress/wordpress-develop/pull/9602
    image: wordpressdevelop/cli@sha256:85ad7d7a9c3bd9a8775fc83aea7f7dfc0aad25b2bc4f7d740696b28cd2a0ef89
    environment:
      WP_SQLITE_AST_DRIVER: true
    volumes:
      - ../:/var/www/src/wp-content/plugins/sqlite-database-integration
EOF

# 4. Add "db.php" to the "wp-content" directory.
echo "Adding 'db.php' to the 'wp-content' directory..."
rm -f "$WP_DIR"/src/wp-content/db.php
cp "$DIR"/db.copy "$WP_DIR"/src/wp-content/db.php
sed -i.bak "s#'{SQLITE_IMPLEMENTATION_FOLDER_PATH}'#__DIR__.'/plugins/sqlite-database-integration'#g" "$WP_DIR"/src/wp-content/db.php
sed -i.bak "s#{SQLITE_PLUGIN}#$WP_DIR/src/wp-content/plugins/sqlite-database-integration/load.php#g" "$WP_DIR"/src/wp-content/db.php

# 5. Rewrite helper class WpdbExposedMethodsForTesting to extend WP_SQLite_DB.
echo "Rewriting helper class 'WpdbExposedMethodsForTesting' to extend WP_SQLite_DB..."
sed -i.bak "s#class WpdbExposedMethodsForTesting extends wpdb {#class WpdbExposedMethodsForTesting extends WP_SQLite_DB {#g" "$WP_DIR"/tests/phpunit/includes/utils.php

# 6. Install dependencies.
echo "Installing dependencies..."
npm --prefix "$WP_DIR" install
npm --prefix "$WP_DIR" run build:dev
