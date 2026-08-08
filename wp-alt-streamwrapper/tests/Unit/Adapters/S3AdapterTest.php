<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit\Adapters;

use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
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
