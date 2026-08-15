<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ServerlessWpStreamWrapper\Config;

class ConfigTest extends TestCase
{
    private array $originalEnv = [];
    protected function setUp(): void
    {
        foreach ($this->envKeys() as $k) {
            $this->originalEnv[$k] = getenv($k);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $k => $v) {
            if ($v === false) {
                putenv($k);
            } else {
                putenv("{$k}={$v}");
            }
        }
    }

    public function testDefaultProvider(): void
    {
        $this->unsetEnv('WP_STREAM_PROVIDER');
        $config = new Config();
        $this->assertSame('', $config->provider());
    }

    public function testEnvVarProvider(): void
    {
        putenv('WP_STREAM_PROVIDER=s3');
        $config = new Config();
        $this->assertSame('s3', $config->provider());
    }

    public function testDefaultTargetPaths(): void
    {
        $this->unsetEnv('WP_STREAM_TARGET_PATHS');
        $config = new Config();
        $this->assertSame(['wp-content'], $config->targetPaths());
    }

    public function testDefaultPublicPathsAreUploadsOnly(): void
    {
        $this->unsetEnv('WP_STREAM_PUBLIC_PATHS');
        $config = new Config();
        $this->assertSame(['wp-content/uploads'], $config->publicPaths());
    }

    public function testCustomPublicPaths(): void
    {
        putenv('WP_STREAM_PUBLIC_PATHS=wp-content/uploads,wp-content/cache');
        $config = new Config();
        $this->assertSame(['wp-content/uploads', 'wp-content/cache'], $config->publicPaths());
    }

    public function testEmptyPublicPathsServesNothing(): void
    {
        putenv('WP_STREAM_PUBLIC_PATHS=');
        $config = new Config();
        $this->assertSame([], $config->publicPaths());
    }

    public function testDefaultPublicAssetPathsIsCache(): void
    {
        $this->unsetEnv('WP_STREAM_PUBLIC_ASSET_PATHS');
        $config = new Config();
        $this->assertSame(['wp-content/cache'], $config->publicAssetPaths());
    }

    public function testCustomPublicAssetPaths(): void
    {
        putenv('WP_STREAM_PUBLIC_ASSET_PATHS=wp-content/cache/autoptimize,wp-content/bundles');
        $config = new Config();
        $this->assertSame(
            ['wp-content/cache/autoptimize', 'wp-content/bundles'],
            $config->publicAssetPaths(),
        );
    }

    public function testAssetExtensionsExcludeContentTypes(): void
    {
        foreach (['css', 'js', 'svg', 'woff2'] as $asset) {
            $this->assertContains($asset, Config::PUBLIC_ASSET_EXTENSIONS);
        }
        foreach (['html', 'htm', 'json', 'xml', 'txt', 'log', 'sql', 'php'] as $content) {
            $this->assertNotContains($content, Config::PUBLIC_ASSET_EXTENSIONS);
        }
    }

    public function testCustomTargetPaths(): void
    {
        putenv('WP_STREAM_TARGET_PATHS=wp-content/uploads,wp-content/custom');
        $config = new Config();
        $this->assertSame(['wp-content/uploads', 'wp-content/custom'], $config->targetPaths());
    }

    public function testTargetPathsTrimsWhitespace(): void
    {
        putenv('WP_STREAM_TARGET_PATHS= wp-content/uploads , wp-content/cache ');
        $config = new Config();
        $this->assertSame(['wp-content/uploads', 'wp-content/cache'], $config->targetPaths());
    }

    public function testDefaultExcludePatterns(): void
    {
        $this->unsetEnv('WP_STREAM_EXCLUDE_PATTERNS');
        $config   = new Config();
        $patterns = $config->excludePatterns();
        $this->assertContains('*.sqlite', $patterns);
        $this->assertContains('*.db', $patterns);
        $this->assertContains('*.php', $patterns);
        $this->assertContains('.htaccess', $patterns);

        $this->assertContains('*.log', $patterns);
    }

    public function testS3Config(): void
    {
        putenv('WP_STREAM_S3_BUCKET=my-bucket');
        putenv('WP_STREAM_S3_REGION=eu-west-1');
        putenv('WP_STREAM_S3_PREFIX=wp/files');
        putenv('WP_STREAM_S3_ENDPOINT=http://localhost:9000');
        putenv('WP_STREAM_S3_KEY=AKID');
        putenv('WP_STREAM_S3_SECRET=secret');

        $config = new Config();
        $this->assertSame('my-bucket', $config->s3Bucket());
        $this->assertSame('eu-west-1', $config->s3Region());
        $this->assertSame('wp/files', $config->s3Prefix());
        $this->assertSame('http://localhost:9000', $config->s3Endpoint());
        $this->assertSame('AKID', $config->s3Key());
        $this->assertSame('secret', $config->s3Secret());
    }

    public function testS3PrefixStripsTrailingSlash(): void
    {
        putenv('WP_STREAM_S3_PREFIX=my/prefix/');
        $config = new Config();
        $this->assertSame('my/prefix', $config->s3Prefix());
    }

    public function testVercelConfig(): void
    {
        putenv('WP_STREAM_VERCEL_TOKEN=tok_abc');
        putenv('WP_STREAM_VERCEL_STORE_ID=store123');

        $config = new Config();
        $this->assertSame('tok_abc', $config->vercelToken());
        $this->assertSame('store123', $config->vercelStoreId());
    }

    public function testNullableReturnsNullWhenUnset(): void
    {
        $this->unsetEnv('WP_STREAM_S3_BUCKET');
        $config = new Config();
        $this->assertNull($config->s3Bucket());
    }

    public function testCdnBaseUrlStripsTrailingSlash(): void
    {
        putenv('WP_STREAM_CDN_BASE_URL=https://cdn.example.com/');
        $config = new Config();
        $this->assertSame('https://cdn.example.com', $config->cdnBaseUrl());
    }

    public function testS3SettingsFallBackToSqliteS3Vars(): void
    {
        $this->unsetEnv('WP_STREAM_S3_BUCKET');
        $this->unsetEnv('WP_STREAM_S3_KEY');
        $this->unsetEnv('WP_STREAM_S3_SECRET');
        $this->unsetEnv('WP_STREAM_S3_REGION');
        $this->unsetEnv('WP_STREAM_S3_ENDPOINT');
        putenv('SQLITE_S3_BUCKET=slswp-bucket');
        putenv('SQLITE_S3_API_KEY=slswp-key');
        putenv('SQLITE_S3_API_SECRET=slswp-secret');
        putenv('SQLITE_S3_REGION=eu-central-1');
        putenv('SQLITE_S3_ENDPOINT=https://r2.example.com');

        $config = new Config();
        $this->assertSame('slswp-bucket', $config->s3Bucket());
        $this->assertSame('slswp-key', $config->s3Key());
        $this->assertSame('slswp-secret', $config->s3Secret());
        $this->assertSame('eu-central-1', $config->s3Region());
        $this->assertSame('https://r2.example.com', $config->s3Endpoint());
    }

    public function testWpStreamVarsTakePrecedenceOverFallbacks(): void
    {
        putenv('WP_STREAM_S3_BUCKET=primary-bucket');
        putenv('SQLITE_S3_BUCKET=fallback-bucket');

        $config = new Config();
        $this->assertSame('primary-bucket', $config->s3Bucket());
    }

    public function testS3OffloadBucketIsLastFallback(): void
    {
        $this->unsetEnv('WP_STREAM_S3_BUCKET');
        $this->unsetEnv('SQLITE_S3_BUCKET');
        putenv('S3_OFFLOAD_BUCKET=offload-bucket');

        $config = new Config();
        $this->assertSame('offload-bucket', $config->s3Bucket());
    }

    public function testForcePathStyleDefaultsToFalse(): void
    {
        $this->unsetEnv('WP_STREAM_S3_FORCE_PATH_STYLE');
        $this->unsetEnv('SQLITE_S3_FORCE_PATH_STYLE');
        $config = new Config();
        $this->assertFalse($config->s3ForcePathStyle());
    }

    public function testForcePathStyleTruthyValues(): void
    {
        putenv('WP_STREAM_S3_FORCE_PATH_STYLE=1');
        $this->assertTrue((new Config())->s3ForcePathStyle());

        putenv('WP_STREAM_S3_FORCE_PATH_STYLE=false');
        $this->assertFalse((new Config())->s3ForcePathStyle());
    }

    public function testS3Acl(): void
    {
        $this->unsetEnv('WP_STREAM_S3_ACL');
        $this->assertNull((new Config())->s3Acl());

        putenv('WP_STREAM_S3_ACL=public-read');
        $this->assertSame('public-read', (new Config())->s3Acl());
    }

    public function testCacheControlDefault(): void
    {
        $this->unsetEnv('WP_STREAM_CACHE_CONTROL');
        $this->assertSame('public, max-age=3600, s-maxage=86400', (new Config())->cacheControl());
    }

    public function testCacheControlOverride(): void
    {
        putenv('WP_STREAM_CACHE_CONTROL=public, max-age=31536000, immutable');
        $this->assertSame('public, max-age=31536000, immutable', (new Config())->cacheControl());
    }

    private function unsetEnv(string $key): void
    {
        putenv($key);
    }

    private function envKeys(): array
    {
        return [
            'WP_STREAM_PROVIDER',
            'WP_STREAM_TARGET_PATHS',
            'WP_STREAM_PUBLIC_PATHS',
            'WP_STREAM_PUBLIC_ASSET_PATHS',
            'WP_STREAM_EXCLUDE_PATTERNS',
            'WP_STREAM_WP_CONTENT_DIR',
            'WP_STREAM_S3_BUCKET',
            'WP_STREAM_S3_REGION',
            'WP_STREAM_S3_PREFIX',
            'WP_STREAM_S3_ENDPOINT',
            'WP_STREAM_S3_KEY',
            'WP_STREAM_S3_SECRET',
            'WP_STREAM_VERCEL_TOKEN',
            'WP_STREAM_VERCEL_STORE_ID',
            'WP_STREAM_CDN_BASE_URL',
            'WP_STREAM_S3_FORCE_PATH_STYLE',
            'WP_STREAM_S3_ACL',
            'WP_STREAM_CACHE_CONTROL',
            'SQLITE_S3_BUCKET',
            'SQLITE_S3_API_KEY',
            'SQLITE_S3_API_SECRET',
            'SQLITE_S3_REGION',
            'SQLITE_S3_ENDPOINT',
            'SQLITE_S3_FORCE_PATH_STYLE',
            'S3_OFFLOAD_BUCKET',
            'S3_KEY_ID',
            'S3_ACCESS_KEY',
        ];
    }
}
