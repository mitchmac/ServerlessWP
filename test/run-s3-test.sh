#!/bin/bash
set -euo pipefail

./build-test.sh

# Clean up any leftovers from a previous run
pkill -f "node proxy.js" 2>/dev/null || true
docker stop serverlesswp-test serverlesswp-test-readonly minio 2>/dev/null || true
docker rm serverlesswp-test serverlesswp-test-readonly minio 2>/dev/null || true
docker network rm serverlesswp-test-network 2>/dev/null || true

VERCEL=${VERCEL:-1}
VERCEL_GIT_COMMIT_REF=${VERCEL_GIT_COMMIT_REF:-test_branch}

if ! command -v mc &> /dev/null; then
    wget https://dl.min.io/client/mc/release/linux-amd64/mc -O /usr/local/bin/mc
    chmod +x /usr/local/bin/mc
fi

docker network create serverlesswp-test-network

docker run -d --name minio \
    --network serverlesswp-test-network \
    -p 9010:9000 -p 9011:9011 \
    -e "MINIO_ROOT_USER=minioadmin" -e "MINIO_ROOT_PASSWORD=minioadmin" \
    minio/minio server /data --console-address ":9011"

sleep 5

mc alias set local-minio http://localhost:9010 minioadmin minioadmin
mc mb local-minio/test-bucket
mc admin user add local-minio testuser testpass
mc admin policy attach local-minio readwrite --user testuser
mc anonymous set download local-minio/test-bucket

docker run \
    -e SQLITE_S3_BUCKET=test-bucket \
    -e SQLITE_S3_API_KEY=testuser -e SQLITE_S3_API_SECRET=testpass \
    -e SQLITE_S3_REGION=us-east-1 -e SQLITE_S3_ENDPOINT=http://minio:9000 -e SQLITE_S3_FORCE_PATH_STYLE=1 \
    -e VERCEL=$VERCEL -e VERCEL_GIT_COMMIT_REF=$VERCEL_GIT_COMMIT_REF \
    -e SERVERLESSWP_TESTING=1 \
    -e SERVERLESSWP_READ_ONLY_MODE=false \
    -e S3_KEY_ID=testuser -e S3_ACCESS_KEY=testpass \
    -e S3_OFFLOAD_BUCKET=test-bucket \
    -e S3_OFFLOAD_REGION=us-east-2 \
    -e S3_OFFLOAD_ENDPOINT=http://minio:9000 \
    -e S3_OFFLOAD_PUBLIC_DOMAIN=localhost:9010 \
    -p 9000:8080 \
    --network serverlesswp-test-network \
    -d --name serverlesswp-test serverlesswp-test

node proxy.js > /dev/null 2>&1 &
PROXY_PID=$!

cleanup() {
    kill $PROXY_PID 2>/dev/null || true
    docker stop serverlesswp-test 2>/dev/null || true
    docker rm serverlesswp-test 2>/dev/null || true
    docker stop serverlesswp-test-readonly 2>/dev/null || true
    docker rm serverlesswp-test-readonly 2>/dev/null || true
    docker stop minio 2>/dev/null || true
    docker rm minio 2>/dev/null || true
    docker network rm serverlesswp-test-network 2>/dev/null || true
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
SCREENSHOTS=${SCREENSHOTS:-} npx playwright test e2e.spec.js e2e-s3-offload.spec.js "$@"

# Read-only mode tests — reuse the same populated S3 bucket from above.
echo "Starting read-only mode tests..."
docker stop serverlesswp-test
docker rm serverlesswp-test

docker run \
    -e SQLITE_S3_BUCKET=test-bucket \
    -e SQLITE_S3_API_KEY=testuser -e SQLITE_S3_API_SECRET=testpass \
    -e SQLITE_S3_REGION=us-east-1 -e SQLITE_S3_ENDPOINT=http://minio:9000 -e SQLITE_S3_FORCE_PATH_STYLE=1 \
    -e VERCEL=$VERCEL -e VERCEL_GIT_COMMIT_REF=$VERCEL_GIT_COMMIT_REF \
    -e SERVERLESSWP_TESTING=1 \
    -e SERVERLESSWP_READ_ONLY_MODE=1 \
    -e SERVERLESSWP_READ_ONLY_CACHE_MAX_AGE=3600 \
    -p 9000:8080 \
    --network serverlesswp-test-network \
    -d --name serverlesswp-test-readonly serverlesswp-test

until curl -sfko /dev/null https://localhost:3000/; do sleep 1; done

SKIP_AUTH=1 SCREENSHOTS=${SCREENSHOTS:-} npx playwright test e2e-read-only.spec.js "$@"
