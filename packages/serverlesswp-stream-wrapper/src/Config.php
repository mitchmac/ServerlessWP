<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper;

class Config
{
    private string $provider;
    private string $targetPaths;
    private string $publicPaths;
    private string $publicAssetPaths;
    private string $excludePaths;
    private string $excludePatterns;
    private ?string $wpContentDir;
    private ?string $s3Bucket;
    private string $s3Region;
    private string $s3Prefix;
    private ?string $s3Endpoint;
    private ?string $s3Key;
    private ?string $s3Secret;
    private bool $s3ForcePathStyle;
    private ?string $s3Acl;
    private string $cacheControl;
    private ?string $vercelToken;
    private ?string $vercelStoreId;
    private string $vercelAccess;
    private ?string $vercelApiBase;
    private ?string $vercelDownloadBase;
    private ?string $cdnBaseUrl;
    public function __construct(?string $requestOidcToken = null)
    {
        $this->provider        = $this->read('SERVERLESSWP_STREAM_PROVIDER', '');
        $this->targetPaths     = $this->read('SERVERLESSWP_STREAM_TARGET_PATHS', 'wp-content');

        $this->publicPaths     = $this->read('SERVERLESSWP_STREAM_PUBLIC_PATHS', 'wp-content/uploads');

        $this->publicAssetPaths = $this->read('SERVERLESSWP_STREAM_PUBLIC_ASSET_PATHS', 'wp-content/cache');
        $this->excludePaths    = $this->read('SERVERLESSWP_STREAM_EXCLUDE_PATHS', 'wp-content/plugins,wp-content/themes,wp-content/mu-plugins,wp-content/languages,wp-content/upgrade');

        $this->excludePatterns = $this->read('SERVERLESSWP_STREAM_EXCLUDE_PATTERNS', '*.sqlite,*.db,*.php,*.log,.htaccess');
        $this->wpContentDir    = $this->readNullable('SERVERLESSWP_STREAM_WP_CONTENT_DIR');

        $this->s3Bucket         = $this->readFirst(['SERVERLESSWP_STREAM_S3_BUCKET', 'SQLITE_S3_BUCKET', 'S3_OFFLOAD_BUCKET']);
        $this->s3Region         = $this->readFirst(['SERVERLESSWP_STREAM_S3_REGION', 'SQLITE_S3_REGION']) ?? 'us-east-1';
        $this->s3Prefix         = $this->read('SERVERLESSWP_STREAM_S3_PREFIX', '');
        $this->s3Endpoint       = $this->readFirst(['SERVERLESSWP_STREAM_S3_ENDPOINT', 'SQLITE_S3_ENDPOINT']);
        $this->s3Key            = $this->readFirst(['SERVERLESSWP_STREAM_S3_KEY', 'SQLITE_S3_API_KEY', 'S3_KEY_ID']);
        $this->s3Secret         = $this->readFirst(['SERVERLESSWP_STREAM_S3_SECRET', 'SQLITE_S3_API_SECRET', 'S3_ACCESS_KEY']);
        $this->s3ForcePathStyle = $this->truthy($this->readFirst(['SERVERLESSWP_STREAM_S3_FORCE_PATH_STYLE', 'SQLITE_S3_FORCE_PATH_STYLE']));
        $this->s3Acl            = $this->readNullable('SERVERLESSWP_STREAM_S3_ACL');

        $this->cacheControl = $this->read('SERVERLESSWP_STREAM_CACHE_CONTROL', 'public, max-age=3600, s-maxage=86400');

        $this->vercelToken = $this->readFirst([
            'SERVERLESSWP_STREAM_VERCEL_TOKEN',
            'BLOB_READ_WRITE_TOKEN',
        ]);
        if ($this->vercelToken === null && $requestOidcToken !== null && $requestOidcToken !== '') {
            $this->vercelToken = $requestOidcToken;
        }
        $this->vercelToken ??= $this->readFirst(['VERCEL_OIDC_TOKEN']);

        $this->vercelStoreId = $this->readFirst([
            'SERVERLESSWP_STREAM_VERCEL_STORE_ID',
            'BLOB_STORE_ID',
            'SQLITE_BLOB_STORE_ID',
        ]);
        $this->vercelAccess       = $this->read('SERVERLESSWP_STREAM_VERCEL_ACCESS', 'public');
        $this->vercelApiBase      = $this->readNullable('SERVERLESSWP_STREAM_VERCEL_API_BASE');
        $this->vercelDownloadBase = $this->readNullable('SERVERLESSWP_STREAM_VERCEL_DOWNLOAD_BASE');
        $this->cdnBaseUrl      = $this->readNullable('SERVERLESSWP_STREAM_CDN_BASE_URL');
    }

    private function read(string $name, string $default): string
    {
        if (defined($name)) {
            return (string) constant($name);
        }
        $env = getenv($name);
        return $env !== false ? $env : $default;
    }

    private function readNullable(string $name): ?string
    {
        if (defined($name)) {
            return (string) constant($name);
        }
        $env = getenv($name);
        return $env !== false ? $env : null;
    }

    private function readFirst(array $names): ?string
    {
        foreach ($names as $name) {
            if (defined($name)) {
                return (string) constant($name);
            }
            $env = getenv($name);
            if ($env !== false && $env !== '') {
                return $env;
            }
        }
        return null;
    }

    private function truthy(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        return !in_array(strtolower($value), ['', '0', 'false', 'no', 'off'], true);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    /** @return string[] */
    public function targetPaths(): array
    {
        if ($this->targetPaths === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->targetPaths)));
    }

    public const PUBLIC_ASSET_EXTENSIONS = [
        'css', 'js', 'mjs',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
    ];

    /** @return string[] */
    public function publicPaths(): array
    {
        if ($this->publicPaths === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->publicPaths)));
    }

    /** @return string[] */
    public function publicAssetPaths(): array
    {
        if ($this->publicAssetPaths === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->publicAssetPaths)));
    }

    /** @return string[] */
    public function excludePaths(): array
    {
        if ($this->excludePaths === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->excludePaths)));
    }

    /** @return string[] */
    public function excludePatterns(): array
    {
        if ($this->excludePatterns === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->excludePatterns)));
    }

    public function wpContentDir(): ?string
    {
        return $this->wpContentDir;
    }

    public function s3Bucket(): ?string
    {
        return $this->s3Bucket;
    }

    public function s3Region(): string
    {
        return $this->s3Region;
    }

    public function s3Prefix(): string
    {
        return rtrim($this->s3Prefix, '/');
    }

    public function s3Endpoint(): ?string
    {
        return $this->s3Endpoint;
    }

    public function s3Key(): ?string
    {
        return $this->s3Key;
    }

    public function s3Secret(): ?string
    {
        return $this->s3Secret;
    }

    public function s3ForcePathStyle(): bool
    {
        return $this->s3ForcePathStyle;
    }

    public function s3Acl(): ?string
    {
        return $this->s3Acl;
    }

    public function cacheControl(): string
    {
        return $this->cacheControl;
    }

    public function vercelToken(): ?string
    {
        return $this->vercelToken;
    }

    public function vercelStoreId(): ?string
    {
        return $this->vercelStoreId;
    }

    public function vercelAccess(): string
    {
        return $this->vercelAccess;
    }

    public function vercelApiBase(): ?string
    {
        return $this->vercelApiBase;
    }

    public function vercelDownloadBase(): ?string
    {
        return $this->vercelDownloadBase;
    }

    public function cdnBaseUrl(): ?string
    {
        return $this->cdnBaseUrl ? rtrim($this->cdnBaseUrl, '/') : null;
    }
}
