<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAltStreamWrapper\PathRouter;

class PathRouterTest extends TestCase
{
    private PathRouter $router;
    protected function setUp(): void
    {
        $this->router = new PathRouter(
            wpContentDir:    '/srv/www/wp-content',
            targetRelPaths:  ['wp-content/uploads', 'wp-content/cache', 'wp-content/wflogs'],
            excludePatterns: ['*.sqlite', '*.db', '*.php', '*.log', '.htaccess'],
        );
    }

    public function testUploadPathIsRemote(): void
    {
        $this->assertTrue($this->router->isRemote('/srv/www/wp-content/uploads/2024/01/photo.jpg'));
    }

    public function testCachePathIsRemote(): void
    {
        $this->assertTrue($this->router->isRemote('/srv/www/wp-content/cache/page.html'));
    }

    public function testDirectoryOtherThanUploadsOrCacheIsRemote(): void
    {
        $this->assertTrue($this->router->isRemote('/srv/www/wp-content/wflogs/attack-data.bin'));
    }

    public function testNestedPathIsRemote(): void
    {
        $this->assertTrue($this->router->isRemote('/srv/www/wp-content/uploads/2024/01/subdir/image.png'));
    }

    public function testExactTargetDirIsRemote(): void
    {
        $this->assertTrue($this->router->isRemote('/srv/www/wp-content/uploads'));
    }

    public function testPluginPathIsLocal(): void
    {
        $this->assertFalse($this->router->isRemote('/srv/www/wp-content/plugins/my-plugin/my-plugin.php'));
    }

    public function testThemePathIsLocal(): void
    {
        $this->assertFalse($this->router->isRemote('/srv/www/wp-content/themes/twentytwenty/style.css'));
    }

    public function testWpIncludesIsLocal(): void
    {
        $this->assertFalse($this->router->isRemote('/srv/www/wp-includes/functions.php'));
    }

    public function testUnrelatedPathIsLocal(): void
    {
        $this->assertFalse($this->router->isRemote('/etc/passwd'));
    }

    public function testSqliteFileIsExcluded(): void
    {
        $this->assertFalse($this->router->isRemote('/srv/www/wp-content/uploads/database.sqlite'));
    }

    public function testDbFileIsExcluded(): void
    {
        $this->assertFalse($this->router->isRemote('/srv/www/wp-content/uploads/data.db'));
    }

    public function testPhpFileInUploadsIsExcluded(): void
    {
        $this->assertFalse($this->router->isRemote('/srv/www/wp-content/uploads/malware.php'));
    }

    public function testHtaccessIsExcluded(): void
    {
        $this->assertFalse($this->router->isRemote('/srv/www/wp-content/uploads/.htaccess'));
    }

    public function testLogFileIsExcluded(): void
    {
        $this->assertFalse($this->router->isRemote('/srv/www/wp-content/debug.log'));
        $this->assertFalse($this->router->isRemote('/srv/www/wp-content/uploads/plugin-errors.log'));
    }

    public function testJpegIsNotExcluded(): void
    {
        $this->assertTrue($this->router->isRemote('/srv/www/wp-content/uploads/photo.jpeg'));
    }

    public function testFileProtocolStripped(): void
    {
        $this->assertTrue($this->router->isRemote('file:///srv/www/wp-content/uploads/photo.jpg'));
    }

    public function testStorageKeyStripsWpContentPrefix(): void
    {
        $key = $this->router->toStorageKey('/srv/www/wp-content/uploads/2024/photo.jpg');
        $this->assertSame('uploads/2024/photo.jpg', $key);
    }

    public function testStorageKeyForCachePath(): void
    {
        $key = $this->router->toStorageKey('/srv/www/wp-content/cache/index.html');
        $this->assertSame('cache/index.html', $key);
    }

    public function testAbsolutePathRoundTrip(): void
    {
        $original = '/srv/www/wp-content/uploads/2024/photo.jpg';
        $key      = $this->router->toStorageKey($original);
        $restored = $this->router->toAbsolutePath($key);
        $this->assertSame($original, $restored);
    }

    public function testWpContentDir(): void
    {
        $this->assertSame('/srv/www/wp-content', $this->router->wpContentDir());
    }

    public function testPathTraversalEscapingUploadsIsNotRemote(): void
    {
        $this->assertFalse(
            $this->router->isRemote('/srv/www/wp-content/uploads/../../../etc/passwd'),
        );
    }

    public function testPathTraversalEscapingToCacheIsNotRemoteForUploadsOnly(): void
    {
        $this->assertTrue(
            $this->router->isRemote('/srv/www/wp-content/uploads/../cache/foo.html'),
        );
    }

    public function testPathTraversalEscapingToPluginsIsNotRemote(): void
    {
        $this->assertFalse(
            $this->router->isRemote('/srv/www/wp-content/uploads/../plugins/foo.php'),
        );
    }

    public function testDotSegmentsAreNormalized(): void
    {
        $this->assertTrue(
            $this->router->isRemote('/srv/www/wp-content/uploads/./2024/photo.jpg'),
        );
    }

    public function testStorageKeyWithDotsResolvesCorrectly(): void
    {
        $key = $this->router->toStorageKey('/srv/www/wp-content/uploads/./2024/../2024/photo.jpg');
        $this->assertSame('uploads/2024/photo.jpg', $key);
    }
}
