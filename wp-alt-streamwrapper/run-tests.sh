#!/usr/bin/env bash
# Usage: ./run-tests.sh [unit|e2e|browser|all]

set -euo pipefail

SUITE="${1:-unit}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_IMAGE="php:8.3-cli"

# Tear down on every exit.
trap 'docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" down -v 2>/dev/null || true' EXIT

run_unit() {
    echo "==> Running unit tests"
    docker run --rm \
        -v "${SCRIPT_DIR}:/app" \
        -w /app \
        "${PHP_IMAGE}" \
        php vendor/bin/phpunit --testsuite unit
}

e2e_network() {
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" \
        ps -q wordpress \
        | xargs docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}' \
        | head -1
}

run_e2e() {
    echo "==> Starting E2E environment"
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" down -v 2>/dev/null || true
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" up -d --wait

    echo "==> Waiting for WordPress to be ready"
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" \
        exec -T wordpress \
        bash /var/www/html/wp-content/mu-plugins/wp-alt-streamwrapper/tests/E2E/setup/install.sh

    echo "==> Running PHP E2E tests"
    NETWORK=$(e2e_network)
    docker run --rm \
        --network "${NETWORK}" \
        -v "${SCRIPT_DIR}:/app" \
        -w /app \
        -e WP_URL=http://wordpress \
        -e WP_STREAM_S3_BUCKET=wp-uploads \
        -e WP_STREAM_S3_ENDPOINT=http://minio:9000 \
        "${PHP_IMAGE}" \
        php vendor/bin/phpunit --testsuite e2e

    echo "==> Stopping E2E environment"
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" down -v
}

run_browser() {
    echo "==> Starting E2E environment"
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" down -v 2>/dev/null || true
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" up -d --wait

    echo "==> Waiting for WordPress to be ready"
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" \
        exec -T wordpress \
        bash /var/www/html/wp-content/mu-plugins/wp-alt-streamwrapper/tests/E2E/setup/install.sh

    echo "==> Running Playwright browser tests"
    NETWORK=$(e2e_network)
    docker run --rm \
        --network "${NETWORK}" \
        -v "${SCRIPT_DIR}/tests/E2E/Browser:/app" \
        -w /app \
        -e WP_URL=http://wordpress \
        mcr.microsoft.com/playwright:v1.44.0-jammy \
        bash -c "npm ci --silent && npx playwright test --reporter=list"

    echo "==> Stopping E2E environment"
    docker compose -f "${SCRIPT_DIR}/tests/E2E/docker-compose.yml" down -v
}

case "${SUITE}" in
    unit)
        run_unit
        ;;
    e2e)
        run_e2e
        ;;
    browser)
        run_browser
        ;;
    all)
        run_unit
        run_e2e
        run_browser
        ;;
    *)
        echo "Unknown suite '${SUITE}'. Use: unit | e2e | browser | all" >&2
        exit 1
        ;;
esac
