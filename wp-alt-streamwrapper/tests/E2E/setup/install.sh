#!/usr/bin/env bash
# Run inside the wordpress container to install WordPress for the E2E suite.
# Usage: docker compose exec wordpress bash /var/www/html/wp-content/mu-plugins/wp-alt-streamwrapper/tests/E2E/setup/install.sh

set -euo pipefail

if ! command -v wp &>/dev/null; then
    curl -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp
    chmod +x /usr/local/bin/wp
fi

WP="wp --allow-root --path=/var/www/html"

echo "Waiting for WordPress to be reachable..."
until curl -sf http://localhost/wp-login.php > /dev/null; do sleep 2; done

if ! $WP core is-installed 2>/dev/null; then
    echo "Installing WordPress..."
    $WP core install \
        --url="http://wordpress" \
        --title="Stream Wrapper E2E" \
        --admin_user="admin" \
        --admin_password="password" \
        --admin_email="admin@example.com" \
        --skip-email
fi

echo "Installing Basic Auth plugin for REST API testing..."
$WP plugin install https://github.com/WP-API/Basic-Auth/archive/refs/heads/master.zip --activate

# No activation step: the plugin ships as a must-use plugin, so it loads
# automatically. See build-plugin.sh.

echo "Installing the public-path filter fixture..."
cp "$(dirname "${BASH_SOURCE[0]}")/public-path-filter.php" \
    /var/www/html/wp-content/mu-plugins/e2e-public-path-filter.php

echo "Flushing rewrite rules..."
$WP rewrite flush --hard

echo "Creating test user..."
if ! $WP user get testuser --field=ID 2>/dev/null; then
    $WP user create testuser test@example.com \
        --role=administrator \
        --user_pass=testpassword
fi

echo "Done."
