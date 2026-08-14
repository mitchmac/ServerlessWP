#!/bin/bash
set -euo pipefail

# Integration test for the wp-alt-streamwrapper wiring in api/index.js:
# api/index.js -> serverlesswp autoPrependFile -> bootstrap/prepend.php -> S3.
#
# The plugin's own suites can't cover this. They run under Apache with mod_php
# and load the prepend from a php.ini in conf.d, so the npm package and
# api/index.js never execute there.
#
# By default this tests the published serverlesswp version locked by the root
# package-lock.json. To test an unpublished package change against this project,
# build against a local checkout instead:
#   SERVERLESSWP_LOCAL=/path/to/serverlesswp-node ./run-streamwrapper-test.sh

cd "$(dirname "$0")"

if [ -z "${SERVERLESSWP_LOCAL:-}" ] && ! node -e "process.exit(typeof require('../node_modules/serverlesswp').buildPhpArgs === 'function' ? 0 : 1)" 2>/dev/null; then
    echo "The installed serverlesswp package has no autoPrependFile support."
    echo "Re-run with SERVERLESSWP_LOCAL=/path/to/serverlesswp-node, or upgrade the package."
    exit 1
fi

./build-test.sh

# Clean up any leftovers from a previous run
pkill -f "node proxy.js" 2>/dev/null || true
docker stop serverlesswp-streamwrapper minio-streamwrapper 2>/dev/null || true
docker rm serverlesswp-streamwrapper minio-streamwrapper 2>/dev/null || true
docker network rm serverlesswp-streamwrapper-network 2>/dev/null || true

BUCKET=stream-test-bucket

if ! command -v mc &> /dev/null; then
    wget https://dl.min.io/client/mc/release/linux-amd64/mc -O /usr/local/bin/mc
    chmod +x /usr/local/bin/mc
fi

docker network create serverlesswp-streamwrapper-network

docker run -d --name minio-streamwrapper \
    --network serverlesswp-streamwrapper-network \
    --network-alias minio \
    -p 9020:9000 \
    -e "MINIO_ROOT_USER=minioadmin" -e "MINIO_ROOT_PASSWORD=minioadmin" \
    minio/minio server /data

until mc alias set stream-minio http://localhost:9020 minioadmin minioadmin >/dev/null 2>&1; do sleep 1; done
mc mb "stream-minio/${BUCKET}"
mc anonymous set download "stream-minio/${BUCKET}"

# SQLite on the same MinIO keeps WordPress bootable; the stream wrapper reads
# the same credentials through the SQLITE_S3_* fallbacks in src/Config.php, so
# WP_STREAM_PROVIDER is the only stream-wrapper variable that has to be set.
docker run \
    -e SQLITE_S3_BUCKET="${BUCKET}" \
    -e SQLITE_S3_API_KEY=minioadmin -e SQLITE_S3_API_SECRET=minioadmin \
    -e SQLITE_S3_REGION=us-east-1 -e SQLITE_S3_ENDPOINT=http://minio:9000 \
    -e SQLITE_S3_FORCE_PATH_STYLE=1 \
    -e WP_STREAM_PROVIDER=s3 \
    -e WP_STREAM_DEBUG=1 \
    -e VERCEL=1 -e VERCEL_GIT_COMMIT_REF=streamwrapper_test \
    -e SERVERLESSWP_TESTING=1 \
    -p 9000:8080 \
    --network serverlesswp-streamwrapper-network \
    -d --name serverlesswp-streamwrapper serverlesswp-test

node proxy.js > /dev/null 2>&1 &
PROXY_PID=$!

cleanup() {
    kill $PROXY_PID 2>/dev/null || true
    # Full container log for post-mortem without a rerun; console gets the tail.
    docker logs serverlesswp-streamwrapper > /tmp/streamwrapper-container.log 2>&1 || true
    echo "Full container log: /tmp/streamwrapper-container.log"
    docker logs serverlesswp-streamwrapper 2>&1 | tail -40 || true
    docker stop serverlesswp-streamwrapper minio-streamwrapper 2>/dev/null || true
    docker rm serverlesswp-streamwrapper minio-streamwrapper 2>/dev/null || true
    docker network rm serverlesswp-streamwrapper-network 2>/dev/null || true
}
trap cleanup EXIT

until curl -sfko /dev/null https://localhost:3000/; do sleep 1; done

npm ci
npx playwright install chromium
ldconfig -p | grep -q libnspr4 || sudo env PATH="$PATH" node_modules/.bin/playwright install-deps chromium

# No SKIP_AUTH: global-setup.js installs WordPress via installer.php and logs
# in, so the media upload test can drive wp-admin.
npx playwright test e2e-stream-wrapper.spec.js "$@"

# The spec proves the bytes round-trip through the wrapper. This proves they
# actually reached the bucket rather than the container's local disk.
echo "Checking the bucket for probe objects..."
if ! mc ls --recursive "stream-minio/${BUCKET}" | grep -q "probe-.*\.txt"; then
    echo "FAILED: no probe object in ${BUCKET} — writes did not reach S3."
    mc ls --recursive "stream-minio/${BUCKET}" || true
    exit 1
fi
echo "Probe object found in ${BUCKET}."
