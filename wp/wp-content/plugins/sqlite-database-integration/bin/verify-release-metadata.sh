#!/usr/bin/env bash
#
# Verify that plugin release metadata is internally consistent.
#
# Usage:
#   verify-release-metadata.sh <plugin-dir>                     # Check version consistency.
#   verify-release-metadata.sh <plugin-dir> <expected-version>  # Also verify changelog entry.

set -euo pipefail

fail() {
	echo "::error::$1"
	exit 1
}

PLUGIN_DIR="${1:?Usage: verify-release-metadata.sh <plugin-dir> [<expected-version>]}"
EXPECTED_VERSION="${2:-}"

# Extract versions from the three source-of-truth files.
PLUGIN_VERSION="$(sed -n 's/^ \* Version: \(.*\)$/\1/p' "$PLUGIN_DIR/load.php" | head -n 1 | tr -d '\r')"
STABLE_TAG="$(sed -n 's/^Stable tag:[[:space:]]*//p' "$PLUGIN_DIR/readme.txt" | head -n 1 | tr -d '\r')"
CONSTANT_VERSION="$(php -r "require '$PLUGIN_DIR/wp-includes/database/version.php'; echo SQLITE_DRIVER_VERSION;")"

[ -n "$PLUGIN_VERSION" ] || fail "Could not extract the plugin version from load.php."
[ -n "$STABLE_TAG" ] || fail "Could not extract the Stable tag from readme.txt."
[ -n "$CONSTANT_VERSION" ] || fail "Could not extract SQLITE_DRIVER_VERSION from version.php."

# All three must agree.
[ "$PLUGIN_VERSION" = "$CONSTANT_VERSION" ] || fail "Version mismatch: load.php=$PLUGIN_VERSION, version.php=$CONSTANT_VERSION."
[ "$PLUGIN_VERSION" = "$STABLE_TAG" ] || fail "Version mismatch: load.php=$PLUGIN_VERSION, readme.txt Stable tag=$STABLE_TAG."

# When an expected version is given, also verify it matches and that
# the changelog contains a non-empty entry for that version.
if [ -n "$EXPECTED_VERSION" ]; then
	[ "$PLUGIN_VERSION" = "$EXPECTED_VERSION" ] || fail "Version mismatch: expected $EXPECTED_VERSION, found $PLUGIN_VERSION."

	grep -Fxq '== Changelog ==' "$PLUGIN_DIR/readme.txt" \
		|| fail "readme.txt is missing a == Changelog == section."

	if ! awk -v version="$EXPECTED_VERSION" '
		BEGIN { in_changelog = 0; in_entry = 0; has_content = 0 }
		$0 == "== Changelog ==" { in_changelog = 1; next }
		in_changelog && $0 == "= " version " =" { in_entry = 1; next }
		in_entry && $0 ~ /^= / { exit has_content ? 0 : 1 }
		in_entry && $0 !~ /^[[:space:]]*$/ { has_content = 1 }
		END { if (!in_changelog || !in_entry || !has_content) exit 1 }
	' "$PLUGIN_DIR/readme.txt"; then
		fail "readme.txt must contain a changelog entry with content for version $EXPECTED_VERSION."
	fi
fi

echo "Verified release metadata for version $PLUGIN_VERSION."
