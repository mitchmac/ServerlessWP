<?php
/**
 * E2E test helper: verifies path-traversal protection in the stream wrapper.
 *
 * action=read-check  — writes a sentinel to /tmp/ (local, bypasses stream wrapper),
 *                      then reads it back via a traversal path that starts inside
 *                      wp-content/uploads/.  PathRouter::resolveDots() must collapse
 *                      the ".." before checking target prefixes; otherwise the
 *                      uploads/ prefix match routes the read to MinIO, which has no
 *                      such key, so file_get_contents returns false.
 *
 * action=write       — writes a sentinel file via a traversal path that starts
 *                      inside uploads/ but resolves to cache/.  Returns the
 *                      expected normalized URL.  With dot-resolution the file lands
 *                      in MinIO under key "cache/e2e-traversal-write.txt"; without
 *                      it the key contains literal ".." and is unreachable at the
 *                      normalized URL.
 *
 * action=cleanup     — removes the file written by action=write from both local
 *                      disk (via shell) and remote storage (via stream-wrapper unlink).
 */

// dirname levels: setup → E2E → tests → wp-alt-streamwrapper → plugins → wp-content
$wpContent = dirname(__DIR__, 5);
$action    = $_GET['action'] ?? '';

header('Content-Type: text/plain');

// ── read-check ────────────────────────────────────────────────────────────────
if ($action === 'read-check') {
    $sentinel      = '/tmp/e2e-path-traversal-sentinel.txt';
    $sentinelValue = 'path-traversal-sentinel-content';

    // Write to /tmp/ — outside any targeted path, so the stream wrapper passes
    // directly to the local filesystem without touching MinIO.
    file_put_contents($sentinel, $sentinelValue);

    // Read back via a traversal path beginning inside uploads/ but escaping via
    // enough ".." to reach the filesystem root, then descending to /tmp/.
    // Extra ".." past the root are no-ops in resolveDots().
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

// ── write ─────────────────────────────────────────────────────────────────────
if ($action === 'write') {
    // Ensure the resolved target directory exists.
    $resolvedDir = $wpContent . '/cache';
    if (!is_dir($resolvedDir)) {
        mkdir($resolvedDir, 0755, true);
    }

    // Traversal path starting in uploads/ that resolves to cache/ via "..".
    // With dot-resolution: stored in MinIO under key "cache/e2e-traversal-write.txt".
    // Without it: stored under a raw key like "uploads/../cache/..." which the
    // proxy cannot map to the expected normalized URL.
    $traversalPath = $wpContent . '/uploads/../cache/e2e-traversal-write.txt';
    $result        = file_put_contents($traversalPath, "traversal-write-sentinel\n");

    if ($result === false) {
        http_response_code(500);
        echo "FAIL: file_put_contents returned false\n";
        exit;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'wordpress';
    echo "http://{$host}/wp-content/cache/e2e-traversal-write.txt\n";
    exit;
}

// ── cleanup ───────────────────────────────────────────────────────────────────
if ($action === 'cleanup') {
    $normalizedPath = $wpContent . '/cache/e2e-traversal-write.txt';
    @unlink($normalizedPath);                               // removes from MinIO via stream wrapper
    shell_exec('rm -f ' . escapeshellarg($normalizedPath)); // removes local copy
    echo "cleaned\n";
    exit;
}

http_response_code(400);
echo "missing or invalid action param\n";
