<?php

$wpContent = dirname(__DIR__, 5);

$deleted = 0;
foreach (['uploads', 'cache'] as $dir) {
    $path     = escapeshellarg("{$wpContent}/{$dir}");
    $deleted += (int) shell_exec("find {$path} -type f -delete -printf '.' 2>/dev/null | wc -c");
}

header('Content-Type: text/plain');
echo "deleted {$deleted} files\n";
