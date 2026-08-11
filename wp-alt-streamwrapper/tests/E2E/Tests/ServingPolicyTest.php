<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\E2E\Tests;

/**
 * What the HTTP proxy will and will not hand back.
 *
 * Storage routing covers all of wp-content, which is right for persistence and
 * too broad for serving: everything routed there would otherwise be downloadable
 * by anyone who guesses the URL. WP_STREAM_PUBLIC_PATHS is the separate,
 * narrower policy, and these tests pin both directions of it.
 */
class ServingPolicyTest extends E2ETestCase
{
    /** @var string[] */
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
        // No opt-in: a bundler's CSS under wp-content/cache has to resolve or
        // the site renders unstyled, and nobody would know why.
        $key = 'cache/e2e-generated-' . getmypid() . '.css';
        $this->seed($key, 'body{color:red}');

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertSame(200, $status);
        $this->assertSame('body{color:red}', $body);
    }

    public function testCacheIsNotServedForPageContent(): void
    {
        // The same directory holds rendered HTML from page caches, which can be
        // a page only some users are meant to see. The extension is what
        // separates it from the bundle above.
        $secret = '<html><body>members-only page</body></html>';
        $key    = 'cache/e2e-cached-page-' . getmypid() . '.html';
        $this->seed($key, $secret);

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertNotSame(200, $status, 'Cached HTML must not be served.');
        $this->assertStringNotContainsString('members-only', $body);
        $this->assertStorageKeyExists($key); // stored, just not downloadable
    }

    public function testUploadsAreServedWhateverTheFileType(): void
    {
        // The extension gate applies to asset paths only. Uploads is media by
        // definition, and WordPress serves whatever is in it on any host.
        $key = 'uploads/e2e-document-' . getmypid() . '.html';
        $this->seed($key, '<html><body>uploaded document</body></html>');

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertSame(200, $status);
        $this->assertStringContainsString('uploaded document', $body);
    }

    public function testDirectoryOutsideThePublicSetIsNotServed(): void
    {
        // A backup directory is stored (so it survives the invocation) but must
        // not be downloadable: there is no .htaccess to stop it on serverless,
        // and this proxy answers before any such rule would apply.
        $secret = 'top secret database dump';
        $key    = 'backups/e2e-dump-' . getmypid() . '.txt';
        $this->seed($key, $secret);

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertNotSame(200, $status, 'A non-public stored path must not be served.');
        $this->assertStringNotContainsString($secret, $body);

        // Still stored — the policy governs serving, not persistence.
        $this->assertStorageKeyExists($key);
    }

    // -------- reporting a refusal --------

    private const NOTICE_HELPER =
        '/wp-content/mu-plugins/wp-alt-streamwrapper/tests/E2E/setup/blocked-notice.php';

    /** @return array{notice_path: ?string, cooldown_held: bool} */
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

        // Nothing was ever written here. An unauthenticated request for an
        // invented path must not produce a log line calling it stored, nor put
        // an attacker's path in front of an admin as if it were real.
        $absent = 'backups/e2e-invented-' . getmypid() . '.css';
        [$status] = $this->fetchBody($this->contentUrl($absent));
        $this->assertNotSame(200, $status);

        $state = $this->reportingState();
        $this->assertNull($state['notice_path'], 'A path with no object behind it must not be reported.');
        // The cooldown is still taken: it is claimed before the existence check
        // so a flood of invented paths cannot become a flood of storage requests.
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

        // The window is held, so the second refusal neither logs nor overwrites
        // the notice — one report per cooldown however many requests arrive.
        $state = $this->reportingState();
        $this->assertStringEndsWith(basename($first), (string) $state['notice_path']);
    }

    public function testPublicPathFilterCanOptInADirectory(): void
    {
        // The filter is the documented way to widen the policy.
        // tests/E2E/setup/public-path-filter.php opts 'wp-content/e2e-opt-in'
        // in; install.sh copies it into mu-plugins.
        $key = 'e2e-opt-in/e2e-file-' . getmypid() . '.txt';
        $this->seed($key, 'opted in');

        [$status, $body] = $this->fetchBody($this->contentUrl($key));

        $this->assertSame(200, $status);
        $this->assertSame('opted in', $body);
    }
}
