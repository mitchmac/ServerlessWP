<?php

$wpRoot   = dirname(__DIR__, 6);
$filePath = $wpRoot . '/wp-content/themes/e2e-out-of-scope.txt';

$action = $_GET['action'] ?? '';

if ($action === 'create') {
    file_put_contents($filePath, "out-of-scope content\n");
    $host = $_SERVER['HTTP_HOST'] ?? 'wordpress';
    header('Content-Type: text/plain');
    echo "http://{$host}/wp-content/themes/e2e-out-of-scope.txt\n";
    exit;
}

if ($action === 'delete') {
    shell_exec('rm -f ' . escapeshellarg($filePath));
    header('Content-Type: text/plain');
    echo "deleted\n";
    exit;
}

http_response_code(400);
echo "missing action param\n";
