#!/bin/bash
set -euo pipefail

# Boots the app in SQLite+S3 mode against a local MinIO and runs the
# version-agnostic smoke tests (smoke.test.js) against it -- no Playwright, no
# owner guard. This is the check that survives a WordPress bump: the e2e suite's
# selectors break on a new release, but a green here means WordPress still boots
# and serves. The boot mirrors run-s3-test.sh; only the tests differ.

./build-test.sh

# Clean up any leftovers from a previous run
pkill -f "node proxy.js" 2>/dev/null || true
docker stop serverlesswp-test minio 2>/dev/null || true
docker rm serverlesswp-test minio 2>/dev/null || true
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
    -p 9000:8080 \
    --network serverlesswp-test-network \
    -d --name serverlesswp-test serverlesswp-test

node proxy.js > /dev/null 2>&1 &
PROXY_PID=$!

cleanup() {
    kill $PROXY_PID 2>/dev/null || true
    docker stop serverlesswp-test 2>/dev/null || true
    docker rm serverlesswp-test 2>/dev/null || true
    docker stop minio 2>/dev/null || true
    docker rm minio 2>/dev/null || true
    docker network rm serverlesswp-test-network 2>/dev/null || true
}
trap cleanup EXIT

until curl -sfko /dev/null https://localhost:3000/; do sleep 1; done

# The fresh SQLite database has no WordPress tables yet, so every request
# redirects to the installer. installer.php (baked into the test image, gated by
# SERVERLESSWP_TESTING) creates them, the same way the Playwright setup does.
echo "Installing WordPress..."
curl -sfk https://localhost:3000/installer.php > /dev/null

echo "Running smoke tests..."
SMOKE_INSECURE=1 node --test smoke.test.js
