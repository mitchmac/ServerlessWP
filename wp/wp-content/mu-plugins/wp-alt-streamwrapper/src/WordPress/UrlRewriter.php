<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\WordPress;

use WpAltStreamWrapper\Config;

class UrlRewriter
{
    public function __construct(private readonly Config $config) {}

    public function register(): void
    {
        // URL rewriting to point directly at the storage backend is intentionally
        // not the default.  Files in targeted paths are served transparently by the
        // template_redirect handler in Plugin::serveRemoteFile(), so WordPress
        // attachment URLs remain normal /wp-content/... URLs.
        //
        // If a CDN base URL is configured (WP_STREAM_CDN_BASE_URL), we rewrite
        // attachment upload URLs to point directly at the CDN as a performance
        // optimisation — skipping the PHP passthrough for every image request.
        $base = $this->config->cdnBaseUrl();
        if ($base) {
            add_filter('upload_dir', [$this, 'rewriteUploadDir']);
        }
    }

    public function rewriteUploadDir(array $dirs): array
    {
        $base = rtrim((string) $this->config->cdnBaseUrl(), '/');
        $dirs['baseurl'] = $base . '/uploads';
        $dirs['url']     = $base . '/uploads' . $dirs['subdir'];
        return $dirs;
    }
}
