#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"

BUILD_ARGS=()
rm -rf local-pkg
mkdir -p local-pkg
touch local-pkg/.gitkeep

# Build against a local checkout of the serverlesswp package instead of the
# published one. Needed to test changes to it before they are released.
#   SERVERLESSWP_LOCAL=/path/to/serverlesswp-node ./build-test.sh
if [ -n "${SERVERLESSWP_LOCAL:-}" ]; then
    if [ ! -f "${SERVERLESSWP_LOCAL}/package.json" ]; then
        echo "SERVERLESSWP_LOCAL=${SERVERLESSWP_LOCAL} is not a package directory." >&2
        exit 1
    fi
    echo "Packing local serverlesswp from ${SERVERLESSWP_LOCAL}..."
    DEST="$PWD/local-pkg"
    TARBALL="$(cd "$SERVERLESSWP_LOCAL" && npm pack --silent --pack-destination "$DEST")"
    echo "  -> ${TARBALL}"
    BUILD_ARGS+=(--build-arg "LOCAL_SERVERLESSWP=${TARBALL}")
fi

cd ..
docker build -t serverlesswp-test -f test/Dockerfile "${BUILD_ARGS[@]}" .
