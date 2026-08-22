<?php

$wpContent = dirname(__DIR__, 5);
$action    = $_GET['action'] ?? '';

header('Content-Type: text/plain');

if ($action === 'read-check') {
    $sentinel      = '/tmp/e2e-path-traversal-sentinel.txt';
    $sentinelValue = 'path-traversal-sentinel-content';

    file_put_contents($sentinel, $sentinelValue);

    $traversalPath = $wpContent . '/uploads/' . str_repeat('../', 10) . 'tmp/e2e-path-traversal-sentinel.txt';
    $contents      = @file_get_contents($traversalPath);

    @unlink($sentinel);

    if ($contents === false) {
        http_response_code(500);
        echo "FAIL: file_get_contents returned false — path may have been routed to remote storage\n";
        exit;
    }
    if (trim($contents) !== $sentinelValue) {
        http_response_code(500);
        echo "FAIL: unexpected content: " . json_encode($contents) . "\n";
        exit;
    }
    echo "OK\n";
    exit;
}

if ($action === 'write') {
    $resolvedDir = $wpContent . '/cache';
    if (!is_dir($resolvedDir)) {
        mkdir($resolvedDir, 0755, true);
    }

    $traversalPath = $wpContent . '/uploads/../cache/e2e-traversal-write.css';
    $result        = file_put_contents($traversalPath, "traversal-write-sentinel\n");

    if ($result === false) {
        http_response_code(500);
        echo "FAIL: file_put_contents returned false\n";
        exit;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'wordpress';
    echo "http://{$host}/wp-content/cache/e2e-traversal-write.css\n";
    exit;
}

if ($action === 'cleanup') {
    $normalizedPath = $wpContent . '/cache/e2e-traversal-write.css';
    @unlink($normalizedPath);
    shell_exec('rm -f ' . escapeshellarg($normalizedPath));
    echo "cleaned\n";
    exit;
}

http_response_code(400);
echo "missing or invalid action param\n";
