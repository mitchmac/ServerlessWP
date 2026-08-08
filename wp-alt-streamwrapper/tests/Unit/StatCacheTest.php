<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAltStreamWrapper\StatCache;

class StatCacheTest extends TestCase
{
    protected function setUp(): void
    {
        StatCache::flush();
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->assertNull(StatCache::get('uploads/nonexistent.jpg'));
    }

    public function testSetAndGet(): void
    {
        $entry = ['size' => 1024, 'mtime' => 1700000000, 'type' => 'file'];
        StatCache::set('uploads/photo.jpg', $entry);
        $this->assertSame($entry, StatCache::get('uploads/photo.jpg'));
    }

    public function testInvalidateRemovesEntry(): void
    {
        StatCache::set('uploads/photo.jpg', ['size' => 1, 'mtime' => 1, 'type' => 'file']);
        StatCache::invalidate('uploads/photo.jpg');
        $this->assertNull(StatCache::get('uploads/photo.jpg'));
    }

    public function testInvalidateDoesNotAffectOtherKeys(): void
    {
        StatCache::set('uploads/a.jpg', ['size' => 1, 'mtime' => 1, 'type' => 'file']);
        StatCache::set('uploads/b.jpg', ['size' => 2, 'mtime' => 2, 'type' => 'file']);
        StatCache::invalidate('uploads/a.jpg');
        $this->assertNotNull(StatCache::get('uploads/b.jpg'));
    }

    public function testInvalidatePrefixRemovesMatchingEntries(): void
    {
        StatCache::set('uploads/2024/photo.jpg', ['size' => 1, 'mtime' => 1, 'type' => 'file']);
        StatCache::set('uploads/2024/thumb.jpg', ['size' => 1, 'mtime' => 1, 'type' => 'file']);
        StatCache::set('uploads/2025/other.jpg', ['size' => 1, 'mtime' => 1, 'type' => 'file']);
        StatCache::set('cache/page.html', ['size' => 1, 'mtime' => 1, 'type' => 'file']);

        StatCache::invalidatePrefix('uploads/2024');

        $this->assertNull(StatCache::get('uploads/2024/photo.jpg'));
        $this->assertNull(StatCache::get('uploads/2024/thumb.jpg'));
        $this->assertNotNull(StatCache::get('uploads/2025/other.jpg'));
        $this->assertNotNull(StatCache::get('cache/page.html'));
    }

    public function testInvalidatePrefixAlsoRemovesExactMatch(): void
    {
        StatCache::set('uploads/2024', ['type' => 'dir', 'size' => 0, 'mtime' => 1]);
        StatCache::invalidatePrefix('uploads/2024');
        $this->assertNull(StatCache::get('uploads/2024'));
    }

    public function testFlushClearsEverything(): void
    {
        StatCache::set('uploads/a.jpg', ['size' => 1, 'mtime' => 1, 'type' => 'file']);
        StatCache::set('cache/b.html', ['size' => 1, 'mtime' => 1, 'type' => 'file']);
        StatCache::flush();
        $this->assertNull(StatCache::get('uploads/a.jpg'));
        $this->assertNull(StatCache::get('cache/b.html'));
    }

    // -------- buildStatArray --------

    public function testBuildStatArrayForFile(): void
    {
        $stat = StatCache::buildStatArray(['size' => 2048, 'mtime' => 1700000000, 'type' => 'file']);

        // Both numeric and named keys must exist.
        $this->assertSame(2048, $stat[7]);
        $this->assertSame(2048, $stat['size']);
        $this->assertSame(1700000000, $stat[9]);
        $this->assertSame(1700000000, $stat['mtime']);

        // File mode: regular file with 0644 permissions = 0100644
        $this->assertSame(0100644, $stat[2]);
        $this->assertSame(0100644, $stat['mode']);
    }

    public function testBuildStatArrayForDirectory(): void
    {
        $stat = StatCache::buildStatArray(['size' => 0, 'mtime' => 1700000000, 'type' => 'dir']);

        // Directory mode: 0040755
        $this->assertSame(0040755, $stat[2]);
        $this->assertSame(0040755, $stat['mode']);
    }

    public function testBuildStatArrayHas26Elements(): void
    {
        $stat = StatCache::buildStatArray(['size' => 0, 'mtime' => 0, 'type' => 'file']);
        // 13 numeric + 13 named
        $this->assertCount(26, $stat);
    }

    // -------- missing entry TTL --------

    public function testMissingEntryIsReturnedBeforeExpiry(): void
    {
        StatCache::set('uploads/gone.jpg', ['type' => 'missing', 'size' => 0, 'mtime' => 0]);
        // Should still be in cache immediately after setting.
        $entry = StatCache::get('uploads/gone.jpg');
        $this->assertNotNull($entry);
        $this->assertSame('missing', $entry['type']);
    }

    public function testMissingEntryIsEvictedAfterExpiry(): void
    {
        // Inject a missing entry with an already-expired timestamp.
        StatCache::set('uploads/gone.jpg', ['type' => 'missing', 'size' => 0, 'mtime' => 0]);

        // Reach into the cache and backdate the expiry to force expiration.
        $reflection = new \ReflectionClass(StatCache::class);
        $prop       = $reflection->getProperty('cache');
        $prop->setAccessible(true);
        $cache                       = $prop->getValue();
        $cache['uploads/gone.jpg']['expires'] = time() - 1;
        $prop->setValue(null, $cache);

        $this->assertNull(StatCache::get('uploads/gone.jpg'));
    }

    public function testNonMissingEntriesHaveNoExpiry(): void
    {
        StatCache::set('uploads/photo.jpg', ['size' => 100, 'mtime' => time(), 'type' => 'file']);
        $entry = StatCache::get('uploads/photo.jpg');
        $this->assertArrayNotHasKey('expires', $entry);
    }
}
