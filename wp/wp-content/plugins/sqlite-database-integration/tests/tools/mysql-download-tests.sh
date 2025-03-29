#!/usr/bin/env bash

##
# This script downloads the MySQL tests from the MySQL server repository:
#   https://github.com/mysql/mysql-server.git
#
# The script requires Git to be installed on the system. It uses shallow
# cloning and sparse checkout to reduce the download size. This method is
# faster than downloading the full ZIP file, and it will enable us to easily
# download multiple tags to create test suites for multiple MySQL versions.
#
# USAGE:
#   bash tests/tools/mysql-download-tests.sh
#
# The tests are stored in "./tmp" and need to be further processed in order
# to extract the SQL queries from them (see "mysql-extract-queries.php").
##

set -e

MYSQL_VERSION="8.0.38"

DIR="$(dirname "$0")"
TMP="$DIR/tmp"

# 1. Ensure that Git is installed.
echo "Checking if Git is installed..."
if ! command -v git &> /dev/null; then
  echo 'Error: Git is not installed.' >&2
  exit 1
fi

# 2. Cleanup.
echo "Cleaning up..."
rm -rf "$TMP"

# 3. Shallow clone the MySQL repository.
echo "Cloning the MySQL repository..."
git clone --depth 1 --no-checkout https://github.com/mysql/mysql-server.git "$TMP"

# 4. Use sparse checkout to only download the "mysql-test" directory.
echo "Downloading the 'mysql-test' directory..."
cd "$TMP"
git config core.sparseCheckout true
touch .git/info/sparse-checkout
echo "mysql-test/" >> .git/info/sparse-checkout
git fetch --depth 1 origin tag "mysql-$MYSQL_VERSION"
git checkout "tags/mysql-$MYSQL_VERSION"
