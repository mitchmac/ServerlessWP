<?php

/**
 * auto_prepend_file bootstrap. Runs before WordPress and must not depend on its
 * database, functions or constants.
 */

declare(strict_types=1);

// Load classes via Composer autoloader. __DIR__ is {plugin}/bootstrap.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    // Plugin not installed — skip silently rather than crashing the entire site.
    return;
}
require_once $autoload;

use WpAltStreamWrapper\Adapters\S3Adapter;
use WpAltStreamWrapper\Adapters\VercelBlobAdapter;
use WpAltStreamWrapper\Bootstrap;
use WpAltStreamWrapper\Config;
use WpAltStreamWrapper\PathRouter;
use WpAltStreamWrapper\StreamWrapper;

$config   = new Config();
$provider = $config->provider();

if ($provider === '') {
    // No provider configured — run without stream wrapper interception.
    return;
}

// Determine the wp-content directory.
// Priority: WP_STREAM_WP_CONTENT_DIR env var, then this file's own location —
// it lives at {wp-content}/mu-plugins/wp-alt-streamwrapper/bootstrap/prepend.php,
// so wp-content is three levels up.
$resolved = Bootstrap::resolveWpContentDir(
    $config->wpContentDir(),
    dirname(__DIR__, 3),
    $_SERVER['DOCUMENT_ROOT'] ?? null,
);

if ($resolved['warning'] !== null) {
    trigger_error('wp-alt-streamwrapper: ' . $resolved['warning'], E_USER_WARNING);
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
    trigger_error("wp-alt-streamwrapper: unknown provider '{$provider}'", E_USER_WARNING);
    return;
}

StreamWrapper::register($adapter, $router);
