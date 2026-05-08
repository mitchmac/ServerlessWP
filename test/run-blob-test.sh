#!/bin/bash
set -euo pipefail

cd ..
docker build -t serverlesswp-blob-test -f test/Dockerfile-blob .
cd test

# Clean up any leftovers from a previous run
pkill -f "node proxy.js" 2>/dev/null || true
pkill -f "node vercel-blob-emulator/server.js" 2>/dev/null || true
docker stop serverlesswp-test serverlesswp-test-readonly 2>/dev/null || true
docker rm serverlesswp-test serverlesswp-test-readonly 2>/dev/null || true

VERCEL=${VERCEL:-1}
VERCEL_GIT_COMMIT_REF=${VERCEL_GIT_COMMIT_REF:-test_branch}

# Token format: vercel_blob_rw_<storeId>_<secret>. The mock derives the storeId
# and rebuilds the hardcoded blob download URL from it. Must match STORE_ID.
STORE_ID=test
BLOB_TOKEN="vercel_blob_rw_${STORE_ID}_testsecret"
FAKE_BLOB_PORT=7000

PORT=$FAKE_BLOB_PORT STORE_ID=$STORE_ID ACCESS=private \
    node vercel-blob-emulator/server.js > /dev/null 2>&1 &
FAKE_BLOB_PID=$!

# Wait for the emulator to be ready
until curl -s -o /dev/null -w "%{http_code}" http://localhost:$FAKE_BLOB_PORT/does-not-exist | grep -q 404; do sleep 1; done

# host-gateway lets the container reach the host-side blob emulator via
# http://host.docker.internal. Works on Docker Desktop and Docker Engine >= 20.10.
docker run \
    --add-host=host.docker.internal:host-gateway \
    -e BLOB_READ_WRITE_TOKEN=$BLOB_TOKEN \
    -e VERCEL_BLOB_API_URL=http://host.docker.internal:$FAKE_BLOB_PORT \
    -e VERCEL_BLOB_MOCK_URL=http://host.docker.internal:$FAKE_BLOB_PORT \
    -e VERCEL=$VERCEL -e VERCEL_GIT_COMMIT_REF=$VERCEL_GIT_COMMIT_REF \
    -e SERVERLESSWP_TESTING=1 \
    -e SERVERLESSWP_READ_ONLY_MODE=false \
    -p 9000:8080 \
    -d --name serverlesswp-test serverlesswp-blob-test

node proxy.js > /dev/null 2>&1 &
PROXY_PID=$!

cleanup() {
    kill $PROXY_PID 2>/dev/null || true
    kill $FAKE_BLOB_PID 2>/dev/null || true
    docker stop serverlesswp-test 2>/dev/null || true
    docker rm serverlesswp-test 2>/dev/null || true
    docker stop serverlesswp-test-readonly 2>/dev/null || true
    docker rm serverlesswp-test-readonly 2>/dev/null || true
}
trap cleanup EXIT

until curl -sfko /dev/null https://localhost:3000/; do sleep 1; done

echo "Testing static file serving..."
static_check=$(curl -sk -o /dev/null -w "%{http_code} %{content_type}" https://localhost:3000/wp-includes/css/classic-themes.css)
http_code=${static_check%% *}
content_type=${static_check#* }
[[ "$http_code" == "200" ]] || { echo "Static file test FAILED: expected 200, got $http_code"; exit 1; }
[[ "$content_type" == *"text/css"* ]] || { echo "Static file content-type FAILED: expected text/css, got $content_type"; exit 1; }
echo "Static file test passed."

npm install
npx playwright install chromium
ldconfig -p | grep -q libnspr4 || sudo env PATH="$PATH" node_modules/.bin/playwright install-deps chromium
SCREENSHOTS=${SCREENSHOTS:-} npx playwright test e2e.spec.js "$@"

# Read-only mode tests — reuse the populated emulator state from above.
echo "Starting read-only mode tests..."
docker stop serverlesswp-test
docker rm serverlesswp-test

docker run \
    --add-host=host.docker.internal:host-gateway \
    -e BLOB_READ_WRITE_TOKEN=$BLOB_TOKEN \
    -e VERCEL_BLOB_API_URL=http://host.docker.internal:$FAKE_BLOB_PORT \
    -e VERCEL_BLOB_MOCK_URL=http://host.docker.internal:$FAKE_BLOB_PORT \
    -e VERCEL=$VERCEL -e VERCEL_GIT_COMMIT_REF=$VERCEL_GIT_COMMIT_REF \
    -e SERVERLESSWP_TESTING=1 \
    -e SERVERLESSWP_READ_ONLY_MODE=1 \
    -e SERVERLESSWP_READ_ONLY_CACHE_MAX_AGE=3600 \
    -p 9000:8080 \
    -d --name serverlesswp-test-readonly serverlesswp-blob-test

until curl -sfko /dev/null https://localhost:3000/; do sleep 1; done

SKIP_AUTH=1 SCREENSHOTS=${SCREENSHOTS:-} npx playwright test e2e-read-only.spec.js "$@"
