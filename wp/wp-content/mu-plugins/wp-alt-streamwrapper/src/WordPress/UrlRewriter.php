<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\WordPress;

use WpAltStreamWrapper\Config;

class UrlRewriter
{
    public function __construct(private readonly Config $config) {}

    public function register(): void
    {
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
