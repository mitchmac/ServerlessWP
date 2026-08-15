<?php

// Runs before WordPress.
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {

    return;
}
require_once $autoload;

use ServerlessWpStreamWrapper\Adapters\S3Adapter;
use ServerlessWpStreamWrapper\Adapters\VercelBlobAdapter;
use ServerlessWpStreamWrapper\Bootstrap;
use ServerlessWpStreamWrapper\Config;
use ServerlessWpStreamWrapper\PathRouter;
use ServerlessWpStreamWrapper\StreamWrapper;

$config   = new Config();
$provider = $config->provider();

if ($provider === '') {

    return;
}

$resolved = Bootstrap::resolveWpContentDir(
    $config->wpContentDir(),
    dirname(__DIR__, 3),
    $_SERVER['DOCUMENT_ROOT'] ?? null,
);

if ($resolved['warning'] !== null) {
    trigger_error('serverlesswp-stream-wrapper: ' . $resolved['warning'], E_USER_WARNING);
}

if ($resolved['dir'] === null) {
    return;
}

$router = new PathRouter(
    $resolved['dir'],
    $config->targetPaths(),
    $config->excludePatterns(),
    $config->excludePaths(),
);

$adapter = match ($provider) {
    's3' => new S3Adapter(
        bucket:         $config->s3Bucket() ?? '',
        region:         $config->s3Region(),
        prefix:         $config->s3Prefix(),
        endpoint:       $config->s3Endpoint(),
        key:            $config->s3Key(),
        secret:         $config->s3Secret(),
        forcePathStyle: $config->s3ForcePathStyle(),
        acl:            $config->s3Acl(),
    ),
    'vercel-blob' => new VercelBlobAdapter(
        token:        $config->vercelToken() ?? '',
        storeId:      $config->vercelStoreId() ?? '',
        access:       $config->vercelAccess(),
        apiBase:      $config->vercelApiBase(),
        downloadBase: $config->vercelDownloadBase(),
    ),
    default => null,
};

if ($adapter === null) {
    trigger_error("serverlesswp-stream-wrapper: unknown provider '{$provider}'", E_USER_WARNING);
    return;
}

StreamWrapper::register($adapter, $router);
