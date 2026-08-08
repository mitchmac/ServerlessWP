<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\E2E\Tests;

use PHPUnit\Framework\TestCase;
use WpAltStreamWrapper\Adapters\VercelBlobAdapter;

/**
 * Runs the real VercelBlobAdapter (actual curl requests) against the blob
 * emulator container, which implements the wire protocol of the official
 * @vercel/blob SDK. This is the closest we get to the live API without a
 * store token, and it validates the request shapes the unit tests only mock:
 * pathname-as-query uploads, x-allow-overwrite, POST /delete, ?url= metadata,
 * and cache-bypassed downloads.
 */
class VercelBlobEmulatorTest extends TestCase
{
    private VercelBlobAdapter $adapter;

    protected function setUp(): void
    {
        $base = getenv('BLOB_EMULATOR_URL') ?: 'http://blob:7000';

        $this->adapter = new VercelBlobAdapter(
            token:        'tok_test',
            storeId:      'teststore',
            access:       'public',
            apiBase:      $base,
            downloadBase: $base,
        );
    }

    public function testPutGetRoundTrip(): void
    {
        $key = 'uploads/e2e/roundtrip-' . getmypid() . '.txt';

        $this->assertTrue($this->adapter->put($key, 'hello blob'));
        $this->assertSame('hello blob', $this->adapter->get($key));
    }

    public function testOverwriteExistingKey(): void
    {
        $key = 'uploads/e2e/overwrite-' . getmypid() . '.css';

        $this->assertTrue($this->adapter->put($key, 'body { color: red }'));
        $this->assertTrue(
            $this->adapter->put($key, 'body { color: blue }'),
            'Re-writing an existing key must succeed (x-allow-overwrite).',
        );
        $this->assertSame('body { color: blue }', $this->adapter->get($key));
    }

    public function testStatReportsSizeAndMtime(): void
    {
        $key = 'uploads/e2e/stat-' . getmypid() . '.txt';
        $this->adapter->put($key, '12345678');

        $stat = $this->adapter->stat($key);

        $this->assertIsArray($stat);
        $this->assertSame(8, $stat['size']);
        $this->assertSame('file', $stat['type']);
        $this->assertEqualsWithDelta(time(), $stat['mtime'], 60);
    }

    public function testStatAndGetReturnFalseForMissingKey(): void
    {
        $this->assertFalse($this->adapter->stat('uploads/e2e/never-written.txt'));
        $this->assertFalse($this->adapter->get('uploads/e2e/never-written.txt'));
    }

    public function testDeleteRemovesBlob(): void
    {
        $key = 'uploads/e2e/delete-' . getmypid() . '.txt';
        $this->adapter->put($key, 'temporary');

        $this->assertTrue($this->adapter->delete($key));
        $this->assertFalse($this->adapter->get($key));
        $this->assertFalse($this->adapter->exists($key));
    }

    public function testRenameMovesContent(): void
    {
        $from = 'uploads/e2e/rename-from-' . getmypid() . '.txt';
        $to   = 'uploads/e2e/rename-to-' . getmypid() . '.txt';
        $this->adapter->put($from, 'moving');

        $this->assertTrue($this->adapter->rename($from, $to));
        $this->assertSame('moving', $this->adapter->get($to));
        $this->assertFalse($this->adapter->get($from));
    }

    public function testKeysWithSpecialCharacters(): void
    {
        $key = 'uploads/e2e/my file & more-' . getmypid() . '.txt';

        $this->assertTrue($this->adapter->put($key, 'encoded ok'));
        $this->assertSame('encoded ok', $this->adapter->get($key));
        $this->assertTrue($this->adapter->delete($key));
        $this->assertFalse($this->adapter->get($key));
    }
}
