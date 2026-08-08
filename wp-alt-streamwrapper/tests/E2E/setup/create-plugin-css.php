<?php
/**
 * E2E test helper: simulates a plugin writing a CSS file to wp-content/cache.
 * file_put_contents goes through the active PHP stream wrapper, so the file
 * should land in remote storage (MinIO/S3) rather than on local disk.
 */

// dirname(__DIR__, 6): setup → E2E → tests → wp-alt-streamwrapper → mu-plugins → wp-content → html
$wpRoot   = dirname(__DIR__, 6);
$cacheDir = $wpRoot . '/wp-content/cache/e2e-plugin-test';
$cssFile  = $cacheDir . '/styles.css';
$cssBody  = ".e2e-generated { color: red; font-size: 16px; }\n";

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

if (file_put_contents($cssFile, $cssBody) === false) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "failed to write {$cssFile}\n";
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? 'wordpress';
$url  = "http://{$host}/wp-content/cache/e2e-plugin-test/styles.css";

header('Content-Type: text/plain');
echo $url . "\n";
