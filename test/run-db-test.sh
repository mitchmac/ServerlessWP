#!/bin/bash
set -euo pipefail

./build-test.sh

# Clean up any leftovers from a previous run
pkill -f "node proxy.js" 2>/dev/null || true
docker stop serverlesswp-test mariadb 2>/dev/null || true
docker rm serverlesswp-test mariadb 2>/dev/null || true
docker network rm serverlesswp-test-network 2>/dev/null || true

docker network create serverlesswp-test-network

# Start MariaDB container
docker run -d --name mariadb \
    --network serverlesswp-test-network \
    -p 3306:3306 \
    -e "MYSQL_ROOT_PASSWORD=rootpassword" \
    -e "MYSQL_DATABASE=testdb" \
    -e "MYSQL_USER=testuser" \
    -e "MYSQL_PASSWORD=testpass" \
    mariadb:latest

# Wait for MariaDB to be ready
echo "Waiting for MariaDB to be ready..."
until docker exec mariadb mariadb -u testuser -ptestpass testdb -e "SELECT 1" >/dev/null 2>&1; do sleep 1; done

# Run the application container with MariaDB environment variables
docker run \
    -e DATABASE=testdb \
    -e USERNAME=testuser \
    -e PASSWORD=testpass \
    -e HOST=mariadb \
    -e SKIP_MYSQL_SSL=1 \
    -e SERVERLESSWP_TESTING=1 \
    -p 9000:8080 \
    --network serverlesswp-test-network \
    -d --name serverlesswp-test serverlesswp-test

node proxy.js > /dev/null 2>&1 &
PROXY_PID=$!

cleanup() {
    kill $PROXY_PID 2>/dev/null || true
    docker stop serverlesswp-test 2>/dev/null || true
    docker rm serverlesswp-test 2>/dev/null || true
    docker stop mariadb 2>/dev/null || true
    docker rm mariadb 2>/dev/null || true
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
SCREENSHOTS=${SCREENSHOTS:-} npx playwright test e2e.spec.js "$@"
