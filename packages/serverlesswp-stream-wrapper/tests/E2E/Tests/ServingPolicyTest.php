<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Tests\E2E\Tests;

class ServingPolicyTest extends E2ETestCase
{
    private array $seeded = [];
    protected function tearDown(): void
    {
        foreach ($this->seeded as $key) {
            $this->storage->delete($key);
        }
    }

    private function seed(string $key, string $contents): void
    {
        $this->assertTrue($this->storage->put($key, $contents), "Failed to seed '{$key}'.");
        $this->seeded[] = $key;
    }

    private function contentUrl(string $key): string
    {
        return rtrim($this->wpUrl, '/') . '/wp-content/' . $key;
    }

    public function testUploadsAreServedWithoutOptIn(): void
    {
        $key = 'uploads/e2e-public-' . getmypid() . '.txt';
        $this->seed($key, 'public media');

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertSame(200, $status);
        $this->assertSame('public media', $body);
    }

    public function testCacheIsServedForAssetFilenames(): void
    {
        $key = 'cache/e2e-generated-' . getmypid() . '.css';
        $this->seed($key, 'body{color:red}');

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertSame(200, $status);
        $this->assertSame('body{color:red}', $body);
    }

    public function testCacheIsNotServedForPageContent(): void
    {
        $secret = '<html><body>members-only page</body></html>';
        $key    = 'cache/e2e-cached-page-' . getmypid() . '.html';
        $this->seed($key, $secret);

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertNotSame(200, $status, 'Cached HTML must not be served.');
        $this->assertStringNotContainsString('members-only', $body);
        $this->assertStorageKeyExists($key);
    }

    public function testUploadsAreServedWhateverTheFileType(): void
    {
        $key = 'uploads/e2e-document-' . getmypid() . '.html';
        $this->seed($key, '<html><body>uploaded document</body></html>');

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertSame(200, $status);
        $this->assertStringContainsString('uploaded document', $body);
    }

    public function testDirectoryOutsideThePublicSetIsNotServed(): void
    {
        $secret = 'top secret database dump';
        $key    = 'backups/e2e-dump-' . getmypid() . '.txt';
        $this->seed($key, $secret);

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertNotSame(200, $status, 'A non-public stored path must not be served.');
        $this->assertStringNotContainsString($secret, $body);

        $this->assertStorageKeyExists($key);
    }

    private const NOTICE_HELPER =
        '/wp-content/mu-plugins/serverlesswp-stream-wrapper/tests/E2E/setup/blocked-notice.php';

    private function reportingState(string $action = 'state'): array
    {
        [$status, $body] = $this->fetchBody(
            rtrim($this->wpUrl, '/') . self::NOTICE_HELPER . '?action=' . $action,
        );
        $this->assertSame(200, $status, "blocked-notice helper failed: {$body}");

        $state = json_decode($body, true);
        $this->assertIsArray($state, "blocked-notice helper returned non-JSON: {$body}");

        return $state;
    }

    public function testRefusingAPathThatDoesNotExistReportsNothing(): void
    {
        $this->reportingState('reset');

        $absent = 'backups/e2e-invented-' . getmypid() . '.css';
        [$status] = $this->fetchBody($this->contentUrl($absent));
        $this->assertNotSame(200, $status);

        $state = $this->reportingState();
        $this->assertNull($state['notice_path'], 'A path with no object behind it must not be reported.');

        $this->assertTrue($state['cooldown_held']);
    }

    public function testRefusingAStoredAssetIsReported(): void
    {
        $this->reportingState('reset');

        $key = 'backups/e2e-real-asset-' . getmypid() . '.css';
        $this->seed($key, 'body{color:blue}');

        [$status] = $this->fetchBody($this->contentUrl($key));
        $this->assertNotSame(200, $status);

        $state = $this->reportingState();
        $this->assertNotNull($state['notice_path'], 'A stored asset refused by policy must be reported.');
        $this->assertStringEndsWith(basename($key), (string) $state['notice_path']);
    }

    public function testReportingIsRateLimitedAcrossRequests(): void
    {
        $this->reportingState('reset');

        $first = 'backups/e2e-first-' . getmypid() . '.css';
        $this->seed($first, 'body{}');
        $this->fetchBody($this->contentUrl($first));

        $second = 'backups/e2e-second-' . getmypid() . '.css';
        $this->seed($second, 'body{}');
        $this->fetchBody($this->contentUrl($second));

        $state = $this->reportingState();
        $this->assertStringEndsWith(basename($first), (string) $state['notice_path']);
    }

    public function testPublicPathFilterCanOptInADirectory(): void
    {
        $key = 'e2e-opt-in/e2e-file-' . getmypid() . '.txt';
        $this->seed($key, 'opted in');

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertSame(200, $status);
        $this->assertSame('opted in', $body);
    }
}
