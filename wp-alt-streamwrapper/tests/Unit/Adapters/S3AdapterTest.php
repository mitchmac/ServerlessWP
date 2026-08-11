<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit\Adapters;

use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use WpAltStreamWrapper\Adapters\Precondition;
use WpAltStreamWrapper\Adapters\PreconditionFailedException;
use WpAltStreamWrapper\Adapters\S3Adapter;

class S3AdapterTest extends TestCase
{
    private function makeAdapter(MockHandler $mock): S3Adapter
    {
        $client = new S3Client([
            'version'     => 'latest',
            'region'      => 'us-east-1',
            'handler'     => $mock,
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            // Without this the SDK retries a 5xx and drains the mock queue, so a
            // test asserting a single response sees "Mock queue is empty".
            'retries'     => 0,
        ]);

        return new S3Adapter(
            bucket: 'test-bucket',
            client: $client,
        );
    }

    public function testGetReturnsBodyOnSuccess(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result(['Body' => 'file contents', '@metadata' => ['statusCode' => 200]]));

        $adapter = $this->makeAdapter($mock);
        $result  = $adapter->get('uploads/photo.jpg');

        $this->assertSame('file contents', $result);
    }

    public function testGetReturnsFalseOnFailure(): void
    {
        $mock = new MockHandler();
        $mock->append(function () {
            throw new S3Exception('Not found', new \Aws\Command('GetObject'), ['code' => 'NoSuchKey']);
        });

        $adapter = $this->makeAdapter($mock);
        $this->assertFalse($adapter->get('uploads/missing.jpg'));
    }

    public function testPutReturnsTrueOnSuccess(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result(['@metadata' => ['statusCode' => 200]]));

        $adapter = $this->makeAdapter($mock);
        $this->assertTrue($adapter->put('uploads/new.jpg', 'binary data'));
    }

    public function testPutReturnsFalseOnFailure(): void
    {
        $mock = new MockHandler();
        $mock->append(function () {
            throw new S3Exception('Error', new \Aws\Command('PutObject'), ['code' => 'AccessDenied']);
        });

        $adapter = $this->makeAdapter($mock);
        $this->assertFalse($adapter->put('uploads/new.jpg', 'binary data'));
    }

    // -------- conditional writes --------

    public function testFetchReturnsBodyAndEtag(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result([
            'Body'      => 'file contents',
            'ETag'      => '"d41d8cd98f00b204e9800998ecf8427e"',
            '@metadata' => ['statusCode' => 200],
        ]));

        $result = $this->makeAdapter($mock)->fetch('uploads/photo.jpg');

        $this->assertSame(S3Adapter::FETCH_FOUND, $result['status']);
        $this->assertSame('file contents', $result['contents']);
        // Quoted on the way in and on the way back out: If-Match wants the
        // quotes S3 sends.
        $this->assertSame('"d41d8cd98f00b204e9800998ecf8427e"', $result['etag']);
    }

    public function testFetchReturnsNullEtagWhenAbsent(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result(['Body' => 'data', '@metadata' => ['statusCode' => 200]]));

        $result = $this->makeAdapter($mock)->fetch('uploads/photo.jpg');
        $this->assertNull($result['etag']);
    }

    public function testFetchSeparatesNotFoundFromError(): void
    {
        // The difference decides whether a write creates or would truncate, so a
        // missing key and a broken request must not look alike.
        $missing = new MockHandler();
        $missing->append(function () {
            throw new S3Exception('Not found', new \Aws\Command('GetObject'), [
                'code'     => 'NoSuchKey',
                'response' => new Response(404),
            ]);
        });
        $this->assertSame(
            S3Adapter::FETCH_NOT_FOUND,
            $this->makeAdapter($missing)->fetch('uploads/missing.jpg')['status'],
        );

        $broken = new MockHandler();
        $broken->append(function () {
            throw new S3Exception('Boom', new \Aws\Command('GetObject'), [
                'code'     => 'InternalError',
                'response' => new Response(500),
            ]);
        });
        $this->assertSame(
            S3Adapter::FETCH_ERROR,
            $this->makeAdapter($broken)->fetch('uploads/photo.jpg')['status'],
        );
    }

    public function testPutSendsIfMatchWhenGiven(): void
    {
        $captured = null;
        $mock     = new MockHandler();
        $mock->append(function ($command) use (&$captured) {
            $captured = $command;
            return new Result(['@metadata' => ['statusCode' => 200]]);
        });

        $adapter = $this->makeAdapter($mock);
        $this->assertTrue(
            $adapter->put('uploads/log.txt', 'data', Precondition::matches('"abc"')),
        );
        $this->assertSame('"abc"', $captured['IfMatch']);
    }

    public function testPutSendsIfNoneMatchForACreateOnlyWrite(): void
    {
        $captured = null;
        $mock     = new MockHandler();
        $mock->append(function ($command) use (&$captured) {
            $captured = $command;
            return new Result(['@metadata' => ['statusCode' => 200]]);
        });

        $this->makeAdapter($mock)->put('uploads/new.txt', 'data', Precondition::absent());

        $this->assertSame('*', $captured['IfNoneMatch']);
        $this->assertArrayNotHasKey('IfMatch', $captured->toArray());
    }

    public function testPutOmitsConditionsByDefault(): void
    {
        $captured = null;
        $mock     = new MockHandler();
        $mock->append(function ($command) use (&$captured) {
            $captured = $command;
            return new Result(['@metadata' => ['statusCode' => 200]]);
        });

        $this->makeAdapter($mock)->put('uploads/photo.jpg', 'data');

        $this->assertArrayNotHasKey('IfMatch', $captured->toArray());
        $this->assertArrayNotHasKey('IfNoneMatch', $captured->toArray());
    }

    public function testConditionalPutThrowsOnPreconditionFailure(): void
    {
        $mock = new MockHandler();
        $mock->append(function () {
            throw new S3Exception('Precondition Failed', new \Aws\Command('PutObject'), [
                'code'     => 'PreconditionFailed',
                'response' => new Response(412),
            ]);
        });

        $this->expectException(PreconditionFailedException::class);
        $this->makeAdapter($mock)->put('uploads/log.txt', 'data', Precondition::matches('"stale"'));
    }

    public function testLostCreateThrowsPreconditionFailed(): void
    {
        $mock = new MockHandler();
        $mock->append(function () {
            throw new S3Exception('Precondition Failed', new \Aws\Command('PutObject'), [
                'code'     => 'PreconditionFailed',
                'response' => new Response(412),
            ]);
        });

        $this->expectException(PreconditionFailedException::class);
        $this->makeAdapter($mock)->put('uploads/new.txt', 'data', Precondition::absent());
    }

    public function testUnconditionalPutDoesNotThrowOnPreconditionFailure(): void
    {
        // Without an ifMatch there is no conflict to recover from, so a 412 is
        // just a failed write.
        $mock = new MockHandler();
        $mock->append(function () {
            throw new S3Exception('Precondition Failed', new \Aws\Command('PutObject'), [
                'code'     => 'PreconditionFailed',
                'response' => new Response(412),
            ]);
        });

        $this->assertFalse($this->makeAdapter($mock)->put('uploads/log.txt', 'data'));
    }

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result(['@metadata' => ['statusCode' => 204]]));

        $adapter = $this->makeAdapter($mock);
        $this->assertTrue($adapter->delete('uploads/old.jpg'));
    }

    public function testStatReturnsArrayOnSuccess(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result([
            'ContentLength' => 2048,
            'LastModified'  => new \DateTimeImmutable('2024-01-15T12:00:00Z'),
            '@metadata'     => ['statusCode' => 200],
        ]));

        $adapter = $this->makeAdapter($mock);
        $stat    = $adapter->stat('uploads/photo.jpg');

        $this->assertIsArray($stat);
        $this->assertSame(2048, $stat['size']);
        $this->assertSame('file', $stat['type']);
        $this->assertIsInt($stat['mtime']);
    }

    public function testStatReturnsFalseWhenNotFound(): void
    {
        $mock = new MockHandler();
        $mock->append(function () {
            throw new S3Exception('Not found', new \Aws\Command('HeadObject'), ['code' => 'NotFound']);
        });

        $adapter = $this->makeAdapter($mock);
        $this->assertFalse($adapter->stat('uploads/missing.jpg'));
    }

    public function testListPrefixReturnsKeys(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result([
            'Contents'     => [
                ['Key' => 'uploads/2024/a.jpg'],
                ['Key' => 'uploads/2024/b.jpg'],
            ],
            'IsTruncated'  => false,
            '@metadata'    => ['statusCode' => 200],
        ]));

        $adapter = $this->makeAdapter($mock);
        $keys    = $adapter->listPrefix('uploads/2024');

        $this->assertSame(['uploads/2024/a.jpg', 'uploads/2024/b.jpg'], $keys);
    }

    public function testListPrefixStripsConfiguredPrefix(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result([
            'Contents'    => [['Key' => 'myprefix/uploads/photo.jpg']],
            'IsTruncated' => false,
            '@metadata'   => ['statusCode' => 200],
        ]));

        $client = new S3Client([
            'version'     => 'latest',
            'region'      => 'us-east-1',
            'handler'     => $mock,
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            // Without this the SDK retries a 5xx and drains the mock queue, so a
            // test asserting a single response sees "Mock queue is empty".
            'retries'     => 0,
        ]);
        $adapter = new S3Adapter(
            bucket: 'test-bucket',
            prefix: 'myprefix',
            client: $client,
        );

        $keys = $adapter->listPrefix('uploads');
        $this->assertSame(['uploads/photo.jpg'], $keys);
    }

    public function testRenameCopiesToNewKeyThenDeletes(): void
    {
        $mock = new MockHandler();
        // CopyObject
        $mock->append(new Result(['@metadata' => ['statusCode' => 200]]));
        // DeleteObject
        $mock->append(new Result(['@metadata' => ['statusCode' => 204]]));

        $adapter = $this->makeAdapter($mock);
        $result  = $adapter->rename('uploads/old.jpg', 'uploads/new.jpg');

        $this->assertTrue($result);
        $this->assertSame(0, $mock->count()); // both queued results were consumed
    }
}
