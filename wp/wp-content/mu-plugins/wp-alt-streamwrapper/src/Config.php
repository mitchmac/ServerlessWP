<?php

declare(strict_types=1);

namespace WpAltStreamWrapper;

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

    public function __construct()
    {
        $this->provider        = $this->read('WP_STREAM_PROVIDER', '');
        $this->targetPaths     = $this->read('WP_STREAM_TARGET_PATHS', 'wp-content');

        // Which stored paths the proxy will hand back over HTTP. Narrower than
        // the routed set on purpose: routing decides what persists, this decides
        // what the public can download. Uploads by default — the one tree whose
        // entire purpose is to be fetched by URL.
        $this->publicPaths     = $this->read('WP_STREAM_PUBLIC_PATHS', 'wp-content/uploads');

        // Paths served only when the filename has a web-asset extension.
        //
        // wp-content/cache holds two unrelated kinds of file. Asset bundlers
        // (Autoptimize and friends) put CSS and JS there that the browser must
        // fetch or the site renders unstyled. Page caches put rendered HTML
        // there, including pages only some users are meant to see. The extension
        // is what separates them, and gating on it means the bundler case works
        // untouched while the HTML stays unreachable — neither outcome depending
        // on an admin knowing to set a variable.
        $this->publicAssetPaths = $this->read('WP_STREAM_PUBLIC_ASSET_PATHS', 'wp-content/cache');
        $this->excludePaths    = $this->read('WP_STREAM_EXCLUDE_PATHS', 'wp-content/plugins,wp-content/themes,wp-content/mu-plugins,wp-content/languages,wp-content/upgrade');
        // *.log stays local: WordPress points error_log at
        // WP_CONTENT_DIR/debug.log when WP_DEBUG_LOG is set, and routing that
        // would turn every logged line into an append — a GET plus a conditional
        // PUT of an object that only grows. The same lines already reach the
        // platform's function logs via stderr, so persisting them is not worth a
        // pair of storage requests per line.
        $this->excludePatterns = $this->read('WP_STREAM_EXCLUDE_PATTERNS', '*.sqlite,*.db,*.php,*.log,.htaccess');
        $this->wpContentDir    = $this->readNullable('WP_STREAM_WP_CONTENT_DIR');

        // S3 settings fall back to the SQLITE_S3_* / S3_* variables ServerlessWP
        // users already configure, so a typical SQLite+S3 site only needs to set
        // WP_STREAM_PROVIDER=s3.
        $this->s3Bucket         = $this->readFirst(['WP_STREAM_S3_BUCKET', 'SQLITE_S3_BUCKET', 'S3_OFFLOAD_BUCKET']);
        $this->s3Region         = $this->readFirst(['WP_STREAM_S3_REGION', 'SQLITE_S3_REGION']) ?? 'us-east-1';
        $this->s3Prefix         = $this->read('WP_STREAM_S3_PREFIX', '');
        $this->s3Endpoint       = $this->readFirst(['WP_STREAM_S3_ENDPOINT', 'SQLITE_S3_ENDPOINT']);
        $this->s3Key            = $this->readFirst(['WP_STREAM_S3_KEY', 'SQLITE_S3_API_KEY', 'S3_KEY_ID']);
        $this->s3Secret         = $this->readFirst(['WP_STREAM_S3_SECRET', 'SQLITE_S3_API_SECRET', 'S3_ACCESS_KEY']);
        $this->s3ForcePathStyle = $this->truthy($this->readFirst(['WP_STREAM_S3_FORCE_PATH_STYLE', 'SQLITE_S3_FORCE_PATH_STYLE']));
        $this->s3Acl            = $this->readNullable('WP_STREAM_S3_ACL');

        // Cache-Control for files served by the template_redirect proxy.
        // Deliberately moderate: uploads are usually immutable but replacement
        // plugins overwrite in place, and generated CSS/cache files change
        // under the same URL. Browsers revalidate hourly; the edge keeps a
        // copy for a day. Raise it via env if your media never changes.
        $this->cacheControl = $this->read('WP_STREAM_CACHE_CONTROL', 'public, max-age=3600, s-maxage=86400');

        $this->vercelToken        = $this->readNullable('WP_STREAM_VERCEL_TOKEN');
        $this->vercelStoreId      = $this->readNullable('WP_STREAM_VERCEL_STORE_ID');
        $this->vercelAccess       = $this->read('WP_STREAM_VERCEL_ACCESS', 'public');
        $this->vercelApiBase      = $this->readNullable('WP_STREAM_VERCEL_API_BASE');
        $this->vercelDownloadBase = $this->readNullable('WP_STREAM_VERCEL_DOWNLOAD_BASE');
        $this->cdnBaseUrl      = $this->readNullable('WP_STREAM_CDN_BASE_URL');
    }

    // Priority: PHP constant > environment variable > default
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

    /** First non-empty value across a list of names, each constant-then-env. */
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

    /** @return string[] relative paths like 'wp-content/uploads' */
    public function targetPaths(): array
    {
        if ($this->targetPaths === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->targetPaths)));
    }

    /**
     * Extensions treated as web assets under publicAssetPaths().
     *
     * Deliberately excludes anything that can carry page content or data —
     * html, htm, json, xml, txt, log, sql, php — because that is what a page
     * cache writes into the same directories as bundled CSS and JS.
     */
    public const PUBLIC_ASSET_EXTENSIONS = [
        'css', 'js', 'mjs',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
    ];

    /** @return string[] relative paths the HTTP proxy may serve, e.g. 'wp-content/uploads' */
    public function publicPaths(): array
    {
        if ($this->publicPaths === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->publicPaths)));
    }

    /** @return string[] relative paths served only for PUBLIC_ASSET_EXTENSIONS files */
    public function publicAssetPaths(): array
    {
        if ($this->publicAssetPaths === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->publicAssetPaths)));
    }

    /** @return string[] relative path prefixes that must stay on local disk, e.g. 'wp-content/plugins' */
    public function excludePaths(): array
    {
        if ($this->excludePaths === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->excludePaths)));
    }

    /** @return string[] glob patterns matched against basename */
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

    /** Canned ACL applied to writes (e.g. 'public-read'), or null for bucket default. */
    public function s3Acl(): ?string
    {
        return $this->s3Acl;
    }

    /** Cache-Control header value for proxy-served remote files. */
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

    /** Blob store access mode: 'public' or 'private'. Shapes the download host. */
    public function vercelAccess(): string
    {
        return $this->vercelAccess;
    }

    /** Override for the Blob API base URL (tests/emulator). */
    public function vercelApiBase(): ?string
    {
        return $this->vercelApiBase;
    }

    /** Override for the blob download base URL (tests/emulator). */
    public function vercelDownloadBase(): ?string
    {
        return $this->vercelDownloadBase;
    }

    public function cdnBaseUrl(): ?string
    {
        return $this->cdnBaseUrl ? rtrim($this->cdnBaseUrl, '/') : null;
    }
}
