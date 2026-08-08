<?php

/**
 * Test helper for e2e-stream-wrapper.spec.js.
 *
 * Runs inside the Lambda container, where WordPress lives at /tmp/wp, so
 * __DIR__ is the WordPress root. It deliberately does not load WordPress:
 * auto_prepend_file runs for every request, so if the wiring in api/index.js
 * works the stream wrapper is already registered by the time this executes.
 *
 * Actions:
 *   (none)                   report wrapper state
 *   ?action=write            write a file under uploads, return its key
 *   ?action=read&key=KEY     read that file back
 *   ?action=css              write CSS to wp-content/cache, return its URL
 *   ?action=clear-local      delete local uploads/cache, bypassing the wrapper
 *   ?action=scoped-create    write to an excluded path (wp-content/themes)
 *   ?action=scoped-delete    remove that local file
 *
 * Never deploy this file to production. Guarded by SERVERLESSWP_TESTING.
 */

declare(strict_types=1);

if (getenv('SERVERLESSWP_TESTING') !== '1') {
    http_response_code(403);
    exit('Not allowed.');
}

$wrapperClass = 'WpAltStreamWrapper\\StreamWrapper';

// If the prepend never ran, its Composer autoloader was never registered and
// the class cannot be found — the silent-failure mode this exists to catch.
$prependRan = class_exists($wrapperClass);

$wpContent  = rtrim((string) (getenv('WP_STREAM_WP_CONTENT_DIR') ?: __DIR__ . '/wp-content'), '/');
$uploadsDir = $wpContent . '/uploads';
$action     = (string) ($_GET['action'] ?? '');
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';

/** Run a callback with the native file wrapper restored, so it touches local disk. */
function withLocalFilesystem(bool $prependRan, string $wrapperClass, callable $fn): mixed
{
    if (!$prependRan) {
        return $fn();
    }
    stream_wrapper_restore('file');
    try {
        return $fn();
    } finally {
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', $wrapperClass);
    }
}

function deleteTree(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $deleted  = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        if ($item->isFile() || $item->isLink()) {
            $deleted += (int) @unlink($item->getPathname());
        }
    }
    return $deleted;
}

function jsonOut(array $payload): never
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// ---------------------------------------------------------------------------

if ($action === 'clear-local') {
    // Bypass the wrapper: we want the local copies gone, not the stored ones,
    // so that anything still served afterwards must come from object storage.
    $deleted = withLocalFilesystem($prependRan, $wrapperClass, function () use ($wpContent) {
        return deleteTree($wpContent . '/uploads') + deleteTree($wpContent . '/cache');
    });

    jsonOut(['deleted' => $deleted]);
}

if ($action === 'css') {
    // Simulates a plugin generating a CSS file with plain file_put_contents.
    $dir  = $wpContent . '/cache/e2e-plugin-test';
    $file = $dir . '/styles.css';
    $body = ".e2e-generated { color: red; font-size: 16px; }\n";

    if (!@is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    jsonOut([
        'bytes_written' => @file_put_contents($file, $body),
        'is_remote'     => $prependRan && $wrapperClass::isRemotePath($file),
        'url'           => '/wp-content/cache/e2e-plugin-test/styles.css',
    ]);
}

if ($action === 'read-css') {
    // Separates "the write never reached storage" from "WordPress failed to
    // serve what is in storage".
    $file = $wpContent . '/cache/e2e-plugin-test/styles.css';

    jsonOut([
        'is_remote' => $prependRan && $wrapperClass::isRemotePath($file),
        'exists'    => @file_exists($file),
        'contents'  => @file_get_contents($file),
    ]);
}

if ($action === 'scoped-create') {
    // wp-content/themes is an excluded path, so this must stay on local disk
    // and never reach storage.
    $file = $wpContent . '/themes/e2e-out-of-scope.txt';

    jsonOut([
        'bytes_written' => @file_put_contents($file, "out-of-scope content\n"),
        'is_remote'     => $prependRan && $wrapperClass::isRemotePath($file),
        'url'           => '/wp-content/themes/e2e-out-of-scope.txt',
    ]);
}

if ($action === 'scoped-delete') {
    $file = $wpContent . '/themes/e2e-out-of-scope.txt';

    [$deleted, $existsAfter] = withLocalFilesystem($prependRan, $wrapperClass, function () use ($file) {
        $deleted = (int) @unlink($file);
        clearstatcache(true, $file);
        return [$deleted, file_exists($file)];
    });

    jsonOut(['deleted' => $deleted, 'exists_after' => $existsAfter, 'path' => $file]);
}

if ($action === 'read') {
    $key  = basename((string) ($_GET['key'] ?? ''));
    $path = $uploadsDir . '/' . $key;

    jsonOut([
        'read_key'  => $key,
        'is_remote' => $prependRan && $wrapperClass::isRemotePath($path),
        'exists'    => @file_exists($path),
        'contents'  => @file_get_contents($path),
    ]);
}

if ($action === 'write') {
    $key     = 'probe-' . bin2hex(random_bytes(6)) . '.txt';
    $path    = $uploadsDir . '/' . $key;
    $payload = 'stream-wrapper-probe-' . bin2hex(random_bytes(8));

    if (!@is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0777, true);
    }

    jsonOut([
        'write_key'     => $key,
        'payload'       => $payload,
        'is_remote'     => $prependRan && $wrapperClass::isRemotePath($path),
        'bytes_written' => @file_put_contents($path, $payload),
    ]);
}

jsonOut([
    'prepend_ran'    => $prependRan,
    'registered'     => $prependRan && $wrapperClass::isRegistered(),
    'wp_content_dir' => getenv('WP_STREAM_WP_CONTENT_DIR') ?: null,
    'provider'       => getenv('WP_STREAM_PROVIDER') ?: null,
    'uploads_remote' => $prependRan && $wrapperClass::isRemotePath($uploadsDir . '/x.txt'),
]);
