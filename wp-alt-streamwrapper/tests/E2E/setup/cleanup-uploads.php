<?php
/**
 * E2E test helper: deletes every local file under wp-content/uploads and
 * wp-content/cache so subsequent requests MUST be served from remote storage.
 *
 * Uses shell_exec to bypass the active PHP stream wrapper — we want to delete
 * from the local filesystem, not from remote storage.
 *
 * Never deploy this file to production.
 */
$wpContent = dirname(__DIR__, 5);

$deleted = 0;
foreach (['uploads', 'cache'] as $dir) {
    $path     = escapeshellarg("{$wpContent}/{$dir}");
    $deleted += (int) shell_exec("find {$path} -type f -delete -printf '.' 2>/dev/null | wc -c");
}

header('Content-Type: text/plain');
echo "deleted {$deleted} files\n";
