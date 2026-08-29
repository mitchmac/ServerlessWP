<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Tests\E2E\Tests;

use ServerlessWpStreamWrapper\Adapters\Precondition;
use ServerlessWpStreamWrapper\Adapters\PreconditionFailedException;
use ServerlessWpStreamWrapper\Adapters\S3Adapter;

class ConditionalWriteTest extends E2ETestCase
{
    private string $key;
    protected function setUp(): void
    {
        parent::setUp();
        $this->key = 'uploads/e2e-conditional-' . getmypid() . '.txt';
    }

    protected function tearDown(): void
    {
        $this->storage->delete($this->key);
    }

    public function testFetchReturnsAnEtagTheBackendWillMatch(): void
    {
        $this->assertTrue($this->storage->put($this->key, 'version one'));

        $read = $this->storage->fetch($this->key);
        $this->assertSame(S3Adapter::FETCH_FOUND, $read['status']);
        $this->assertSame('version one', $read['contents']);
        $this->assertNotNull($read['etag'], 'S3 must return an ETag for a stored object.');

        $this->assertTrue(
            $this->storage->put($this->key, 'version two', Precondition::matches($read['etag'])),
        );
        $this->assertSame('version two', $this->storage->get($this->key));
    }

    public function testFetchReportsAMissingObjectAsNotFound(): void
    {
        $result = $this->storage->fetch('uploads/e2e-definitely-absent-' . getmypid() . '.txt');

        $this->assertSame(S3Adapter::FETCH_NOT_FOUND, $result['status']);
        $this->assertNull($result['contents']);
    }

    public function testConditionalCreateSucceedsOnAFreeKey(): void
    {
        $this->assertTrue(
            $this->storage->put($this->key, 'created', Precondition::absent()),
            'A create-only write to a key nobody holds must succeed.',
        );
        $this->assertSame('created', $this->storage->get($this->key));
    }

    public function testConditionalCreateLosesToAWriterThatGotThereFirst(): void
    {
        $this->storage->put($this->key, 'written first');

        try {
            $this->storage->put($this->key, 'written second', Precondition::absent());
            $this->fail(
                'Conditional PutObject with If-None-Match: * overwrote an existing object. The '
                . 'backend does not enforce create-only writes, so two invocations creating the '
                . 'same key can still lose one side.',
            );
        } catch (PreconditionFailedException) {
        }

        $this->assertSame('written first', $this->storage->get($this->key));
    }

    public function testConditionalWriteWithStaleEtagIsRejected(): void
    {
        $this->storage->put($this->key, 'version one');
        $stale = $this->storage->fetch($this->key)['etag'];

        $this->storage->put($this->key, 'written by someone else');

        try {
            $this->storage->put($this->key, 'built on a stale read', Precondition::matches($stale));
            $this->fail(
                'Conditional PutObject with a stale If-Match was accepted. The backend does not '
                . 'enforce conditional writes, so concurrent read-modify-write cycles can still '
                . 'lose data.',
            );
        } catch (PreconditionFailedException) {
        }

        $this->assertSame('written by someone else', $this->storage->get($this->key));
    }
}
