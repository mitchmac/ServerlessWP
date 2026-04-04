#!/bin/bash

##
# Build the SQLite Database Integration plugin zip.
#
# This script copies the plugin package into ./build/plugin-sqlite-database-integration/,
# resolves the driver symlink, removes dev-only files, and creates a zip archive.
##

set -e

DIR="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$DIR/build"
PLUGIN_DIR="$BUILD_DIR/plugin-sqlite-database-integration"
ZIP_FILE="$BUILD_DIR/plugin-sqlite-database-integration.zip"

# Clean previous build.
rm -rf "$PLUGIN_DIR"
rm -f "$ZIP_FILE"
mkdir -p "$BUILD_DIR"

# Copy the plugin package.
cp -R "$DIR/packages/plugin-sqlite-database-integration" "$PLUGIN_DIR"

# Resolve the database symlink — replace it with a real copy of the driver.
rm "$PLUGIN_DIR/wp-includes/database"
cp -R "$DIR/packages/mysql-on-sqlite/src" "$PLUGIN_DIR/wp-includes/database"

# Remove dev-only files.
rm -rf "$PLUGIN_DIR/composer.json"
rm -rf "$PLUGIN_DIR/vendor"
rm -rf "$PLUGIN_DIR/node_modules"

# Verify release metadata in the built plugin.
bash "$DIR/bin/verify-release-metadata.sh" "$PLUGIN_DIR"

# Create the zip archive.
cd "$BUILD_DIR"
zip -r "$ZIP_FILE" "$(basename "$PLUGIN_DIR")/" -x "*.DS_Store"

echo "Built: $ZIP_FILE"
