<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit\Adapters;

use PHPUnit\Framework\TestCase;
use WpAltStreamWrapper\Adapters\Precondition;
use WpAltStreamWrapper\Adapters\PreconditionFailedException;
use WpAltStreamWrapper\Adapters\VercelBlobAdapter;

/**
 * Uses a testable subclass that overrides the protected request() method,
 * avoiding real HTTP calls. The asserted request shapes mirror what the
 * official @vercel/blob SDK sends (see the emulator in the ServerlessWP repo,
 * test/vercel-blob-emulator/server.js).
 */
class VercelBlobAdapterTest extends TestCase
{
    private TestableVercelBlobAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new TestableVercelBlobAdapter(
            token:   'tok_test',
            storeId: 'store_abc123',
        );
    }

    // -------- get (download) --------

    public function testGetReturnsBodyOnSuccess(): void
    {
        $this->adapter->enqueue(200, 'image data');

        $result = $this->adapter->get('uploads/photo.jpg');
        $this->assertSame('image data', $result);
    }

    public function testGetReturnsFalseOnNotFound(): void
    {
        $this->adapter->enqueue(404, '');

        $result = $this->adapter->get('uploads/missing.jpg');
        $this->assertFalse($result);
    }

    public function testGetBypassesCdnCache(): void
    {
        // cache=0 = the SDK's useCache:false. Without it a read can serve the
        // previous version of an overwritten blob for up to 60 seconds.
        $this->adapter->enqueue(200, 'data');
        $this->adapter->get('uploads/photo.jpg');

        $url = $this->adapter->lastRequest()['url'];
        $this->assertSame(
            'https://store_abc123.public.blob.vercel-storage.com/uploads/photo.jpg?cache=0',
            $url,
        );
    }

    // -------- put (upload) --------

    public function testPutReturnsTrueOnSuccess(): void
    {
        $this->adapter->enqueue(200, '{"url":"https://store_abc123.public.blob.vercel-storage.com/uploads/photo.jpg"}');

        $result = $this->adapter->put('uploads/photo.jpg', 'binary content');
        $this->assertTrue($result);
    }

    public function testPutSendsPathnameAsQueryParam(): void
    {
        $this->adapter->enqueue(200, '{}');
        $this->adapter->put('uploads/photo.jpg', 'data');

        $lastRequest = $this->adapter->lastRequest();
        $this->assertSame('PUT', $lastRequest['method']);
        $this->assertStringStartsWith('https://blob.vercel-storage.com/?pathname=', $lastRequest['url']);
        $this->assertStringContainsString('pathname=uploads%2Fphoto.jpg', $lastRequest['url']);
    }

    public function testPutEncodesSpacesAsPercentTwenty(): void
    {
        // Not '+': that only decodes back to a space under form-encoding
        // rules, so a server reading the parameter as a raw path would store a
        // literal '+' and the file could never be read back.
        $this->adapter->enqueue(200, '{}');
        $this->adapter->put('uploads/my file.jpg', 'data');

        $url = $this->adapter->lastRequest()['url'];
        $this->assertStringContainsString('my%20file.jpg', $url);
        $this->assertStringNotContainsString('+', $url);
    }

    public function testPutAllowsOverwrite(): void
    {
        // wp-content files are rewritten in place; without x-allow-overwrite
        // the API rejects a write to an existing key with 400.
        $this->adapter->enqueue(200, '{}');
        $this->adapter->put('uploads/photo.jpg', 'data');

        $headers = $this->adapter->lastRequest()['headers'];
        $this->assertSame('1', $headers['x-allow-overwrite']);
        $this->assertSame('store_abc123', $headers['x-vercel-blob-store-id']);
        $this->assertSame('image/jpeg', $headers['x-content-type']);
    }

    // -------- conditional writes --------

    public function testFetchReturnsContentsAndEtag(): void
    {
        $this->adapter->enqueue(200, 'image data', ['etag' => '"abc123"']);

        $result = $this->adapter->fetch('uploads/photo.jpg');

        $this->assertSame(VercelBlobAdapter::FETCH_FOUND, $result['status']);
        $this->assertSame('image data', $result['contents']);
        $this->assertSame('"abc123"', $result['etag']);
    }

    public function testFetchStripsWeakValidatorPrefix(): void
    {
        // A download can answer with a weak validator; x-if-match only accepts
        // the strong form.
        $this->adapter->enqueue(200, 'image data', ['etag' => 'W/"abc123"']);

        $result = $this->adapter->fetch('uploads/photo.jpg');
        $this->assertSame('"abc123"', $result['etag']);
    }

    public function testFetchReturnsNullEtagWhenAbsent(): void
    {
        $this->adapter->enqueue(200, 'image data');

        $result = $this->adapter->fetch('uploads/photo.jpg');
        $this->assertNull($result['etag']);
    }

    public function testFetchSeparatesNotFoundFromError(): void
    {
        // The difference decides whether a write creates or would truncate.
        $this->adapter->enqueue(404, '');
        $this->assertSame(
            VercelBlobAdapter::FETCH_NOT_FOUND,
            $this->adapter->fetch('uploads/missing.jpg')['status'],
        );

        $this->adapter->enqueue(500, 'upstream exploded');
        $this->assertSame(
            VercelBlobAdapter::FETCH_ERROR,
            $this->adapter->fetch('uploads/photo.jpg')['status'],
        );
    }

    public function testPutSendsIfMatchHeaderWhenGiven(): void
    {
        $this->adapter->enqueue(200, '{}');
        $this->adapter->put('uploads/log.txt', 'data', Precondition::matches('"abc123"'));

        // Same header name the @vercel/blob SDK sends for its ifMatch option.
        $this->assertSame('"abc123"', $this->adapter->lastRequest()['headers']['x-if-match']);
    }

    public function testPutOmitsIfMatchHeaderByDefault(): void
    {
        $this->adapter->enqueue(200, '{}');
        $this->adapter->put('uploads/photo.jpg', 'data');

        $this->assertArrayNotHasKey('x-if-match', $this->adapter->lastRequest()['headers']);
    }

    public function testCreateOnlyWriteWithholdsAllowOverwrite(): void
    {
        // Vercel Blob has no put-side ifNoneMatch — its SDK option of that name
        // is a conditional read. Withholding x-allow-overwrite is the create-only
        // precondition: the API rejects an existing key without it.
        $this->adapter->enqueue(200, '{}');
        $this->adapter->put('uploads/new.txt', 'data', Precondition::absent());

        $headers = $this->adapter->lastRequest()['headers'];
        $this->assertArrayNotHasKey('x-allow-overwrite', $headers);
        $this->assertArrayNotHasKey('x-if-match', $headers);
    }

    public function testOrdinaryWriteStillAllowsOverwrite(): void
    {
        $this->adapter->enqueue(200, '{}');
        $this->adapter->put('uploads/photo.jpg', 'data');

        $this->assertSame('1', $this->adapter->lastRequest()['headers']['x-allow-overwrite']);
    }

    public function testConditionalPutThrowsOnPreconditionFailure(): void
    {
        $this->adapter->enqueue(412, '{"error":{"code":"precondition_failed"}}');

        $this->expectException(PreconditionFailedException::class);
        $this->adapter->put('uploads/log.txt', 'data', Precondition::matches('"stale"'));
    }

    public function testLostCreateMapsRefusedOverwriteToPreconditionFailed(): void
    {
        // The API answers 400 "Blob exists and overwrite is not allowed", which
        // for a create-only write is precisely the condition failing.
        $this->adapter->enqueue(400, '{"error":{"code":"bad_request"}}');

        $this->expectException(PreconditionFailedException::class);
        $this->adapter->put('uploads/new.txt', 'data', Precondition::absent());
    }

    public function testUnconditionalPutDoesNotThrowOnRefusedOverwrite(): void
    {
        // Without a create condition a 400 is just a failed write.
        $this->adapter->enqueue(400, '{}');

        $this->assertFalse($this->adapter->put('uploads/photo.jpg', 'data'));
    }

    public function testUnconditionalPutDoesNotThrowOnPreconditionFailure(): void
    {
        // Without an ifMatch there is no conflict to recover from, so a 412 is
        // just a failed write.
        $this->adapter->enqueue(412, '{}');

        $this->assertFalse($this->adapter->put('uploads/log.txt', 'data'));
    }

    // -------- delete --------

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $this->adapter->enqueue(200, '{}');

        $result = $this->adapter->delete('uploads/old.jpg');
        $this->assertTrue($result);
    }

    public function testDeletePostsUrlsToDeleteEndpoint(): void
    {
        $this->adapter->enqueue(200, '{}');
        $this->adapter->delete('uploads/old.jpg');

        $lastRequest = $this->adapter->lastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertSame('https://blob.vercel-storage.com/delete', $lastRequest['url']);
        $this->assertSame(
            ['urls' => ['https://store_abc123.public.blob.vercel-storage.com/uploads/old.jpg']],
            json_decode($lastRequest['body'], true),
        );
    }

    // -------- stat (metadata) --------

    public function testStatReturnsFalseOnNotFound(): void
    {
        $this->adapter->enqueue(404, '');

        $result = $this->adapter->stat('uploads/missing.jpg');
        $this->assertFalse($result);
    }

    public function testStatQueriesMetadataEndpoint(): void
    {
        $this->adapter->enqueue(200, json_encode([
            'size'       => 4096,
            'uploadedAt' => '2026-01-15T12:00:00.000Z',
            'etag'       => '"abc"',
        ]));

        $stat = $this->adapter->stat('uploads/photo.jpg');

        $this->assertIsArray($stat);
        $this->assertSame(4096, $stat['size']);
        $this->assertSame('file', $stat['type']);
        $this->assertSame(strtotime('2026-01-15T12:00:00.000Z'), $stat['mtime']);

        $url = $this->adapter->lastRequest()['url'];
        $this->assertStringStartsWith('https://blob.vercel-storage.com/?url=', $url);
        $this->assertStringContainsString(rawurlencode('uploads/photo.jpg'), $url);
    }

    // -------- listPrefix --------

    public function testListPrefixReturnsPathnames(): void
    {
        $this->adapter->enqueue(200, json_encode([
            'blobs' => [
                ['pathname' => 'uploads/2024/a.jpg'],
                ['pathname' => 'uploads/2024/b.jpg'],
            ],
        ]));

        $keys = $this->adapter->listPrefix('uploads/2024');
        $this->assertSame(['uploads/2024/a.jpg', 'uploads/2024/b.jpg'], $keys);
    }

    public function testListPrefixReturnsEmptyOnError(): void
    {
        $this->adapter->enqueue(500, '');

        $keys = $this->adapter->listPrefix('uploads/2024');
        $this->assertSame([], $keys);
    }

    // -------- URL construction --------

    public function testBlobUrlEncodesSpecialCharacters(): void
    {
        $this->adapter->enqueue(200, 'data');
        $this->adapter->get('uploads/my file & more.jpg');

        $url = $this->adapter->lastRequest()['url'];
        $this->assertStringContainsString('my%20file%20%26%20more.jpg', $url);
        $this->assertStringNotContainsString('my file', $url);
    }

    public function testBlobUrlPreservesSlashes(): void
    {
        $this->adapter->enqueue(200, 'data');
        $this->adapter->get('uploads/2024/01/photo.jpg');

        $url = $this->adapter->lastRequest()['url'];
        $this->assertStringContainsString(
            'store_abc123.public.blob.vercel-storage.com/uploads/2024/01/photo.jpg',
            $url,
        );
    }

    public function testAccessAndBaseUrlsAreConfigurable(): void
    {
        $adapter = new TestableVercelBlobAdapter(
            token:        'tok_test',
            storeId:      'store_abc123',
            access:       'private',
            apiBase:      'http://blob:7000',
            downloadBase: 'http://blob:7000',
        );

        $adapter->enqueue(200, 'data');
        $adapter->get('uploads/photo.jpg');
        $this->assertSame('http://blob:7000/uploads/photo.jpg?cache=0', $adapter->lastRequest()['url']);

        $adapter->enqueue(200, '{}');
        $adapter->put('uploads/photo.jpg', 'data');
        $this->assertStringStartsWith('http://blob:7000/?pathname=', $adapter->lastRequest()['url']);
    }

    public function testPrivateAccessChangesDefaultDownloadHost(): void
    {
        $adapter = new TestableVercelBlobAdapter(
            token:   'tok_test',
            storeId: 'store_abc123',
            access:  'private',
        );

        $adapter->enqueue(200, 'data');
        $adapter->get('uploads/photo.jpg');
        $this->assertStringContainsString(
            'store_abc123.private.blob.vercel-storage.com/uploads/photo.jpg',
            $adapter->lastRequest()['url'],
        );
    }

    // -------- rename --------

    public function testRenameGetsThenPutsThenDeletes(): void
    {
        $this->adapter->enqueue(200, 'original content');   // get (from)
        $this->adapter->enqueue(200, '{}');                 // put (to)
        $this->adapter->enqueue(200, '{}');                 // delete (from)

        $result = $this->adapter->rename('uploads/old.jpg', 'uploads/new.jpg');
        $this->assertTrue($result);
        $this->assertCount(3, $this->adapter->allRequests());
    }
}

/**
 * Testable subclass that replaces the protected request() method with a queue.
 */
class TestableVercelBlobAdapter extends VercelBlobAdapter
{
    private array $queue    = [];
    private array $requests = [];

    public function enqueue(int $status, string $body, array $headers = []): void
    {
        $this->queue[] = ['status' => $status, 'headers' => $headers, 'body' => $body];
    }

    public function lastRequest(): array
    {
        return end($this->requests) ?: [];
    }

    public function allRequests(): array
    {
        return $this->requests;
    }

    protected function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        if (empty($this->queue)) {
            return ['status' => 500, 'headers' => [], 'body' => ''];
        }

        return array_shift($this->queue);
    }
}
