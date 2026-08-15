<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Tests\E2E\Tests;

class FileUploadTest extends E2ETestCase
{
    public function testUploadedFileExistsInMinIO(): void
    {
        $fixture    = $this->fixturesDir() . '/test-image.jpg';
        $attachment = $this->uploadMedia($fixture, 'e2e-upload-test.jpg');

        $this->assertArrayHasKey('id', $attachment);
        $this->assertGreaterThan(0, $attachment['id']);

        $keys = $this->listStorageKeys('uploads');
        $found = array_filter($keys, fn($k) => str_contains($k, 'e2e-upload-test'));
        $this->assertNotEmpty($found, 'Uploaded file not found in MinIO storage.');
    }

    public function testUploadedFileIsServableAtWordPressUrl(): void
    {
        $fixture    = $this->fixturesDir() . '/test-image.jpg';
        $attachment = $this->uploadMedia($fixture, 'e2e-url-test.jpg');

        $sourceUrl = $attachment['source_url'] ?? '';
        $this->assertStringContainsString('/wp-content/', $sourceUrl, "source_url must be a wp-content URL, got: {$sourceUrl}");

        $ch = curl_init($sourceUrl);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(200, $status, "Attachment URL must be reachable via WordPress proxy: {$sourceUrl}");
    }

    public function testServedFileHasEdgeCacheableHeaders(): void
    {
        $fixture    = $this->fixturesDir() . '/test-image.jpg';
        $attachment = $this->uploadMedia($fixture, 'e2e-cache-hit-test.jpg');

        [$status, $headers] = $this->fetchHeaders($attachment['source_url'] ?? '');

        $this->assertSame(200, $status);
        $cacheControl = $headers['cache-control'] ?? '';
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertStringContainsString('s-maxage=86400', $cacheControl, 'Hits must be edge-cacheable via s-maxage.');
    }

    public function testMissingFileFourOhFourIsNotCacheable(): void
    {
        $url = rtrim($this->wpUrl, '/') . '/wp-content/uploads/e2e-definitely-missing-' . getmypid() . '.png';

        [$status, $headers] = $this->fetchHeaders($url);

        $this->assertSame(404, $status);
        $cacheControl = $headers['cache-control'] ?? '';
        $this->assertStringContainsString('no-cache', $cacheControl, "404 for a remote-routed path must not be cacheable, got: '{$cacheControl}'");
    }

    public function testUploadedFileIsReadableFromStorage(): void
    {
        $fixture    = $this->fixturesDir() . '/test-image.jpg';
        $attachment = $this->uploadMedia($fixture, 'e2e-readable-test.jpg');

        $keys = $this->listStorageKeys('uploads');
        $key  = '';
        foreach ($keys as $k) {
            if (str_contains($k, 'e2e-readable-test') && !str_contains($k, '-e_')) {
                $key = $k;
                break;
            }
        }

        $this->assertNotEmpty($key, 'Could not find uploaded file key in MinIO.');

        $contents = $this->storage->get($key);
        $this->assertNotFalse($contents, 'Could not retrieve file from MinIO.');

        $this->assertSame("\xFF\xD8\xFF", substr($contents, 0, 3));
    }
}
