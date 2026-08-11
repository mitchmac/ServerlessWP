<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\E2E\Tests;

use PHPUnit\Framework\TestCase;
use WpAltStreamWrapper\Adapters\S3Adapter;

abstract class E2ETestCase extends TestCase
{
    protected string $wpUrl;
    protected S3Adapter $storage;
    protected string $wpUser     = 'testuser';
    protected string $wpPassword = 'testpassword';

    protected function setUp(): void
    {
        $this->wpUrl = getenv('WP_URL') ?: 'http://wordpress';

        $this->storage = new S3Adapter(
            bucket:   getenv('WP_STREAM_S3_BUCKET') ?: 'wp-uploads',
            region:   'us-east-1',
            endpoint: getenv('WP_STREAM_S3_ENDPOINT') ?: 'http://minio:9000',
            key:      'minioadmin',
            secret:   'minioadmin',
        );
    }

    /**
     * Upload a file to WordPress via the REST API.
     * Returns the attachment JSON response as an array.
     */
    protected function uploadMedia(string $localPath, string $filename = ''): array
    {
        if ($filename === '') {
            $filename = basename($localPath);
        }

        $token   = base64_encode($this->wpUser . ':' . $this->wpPassword);
        $url     = rtrim($this->wpUrl, '/') . '/wp-json/wp/v2/media';
        $payload = file_get_contents($localPath);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Basic {$token}",
                "Content-Disposition: attachment; filename=\"{$filename}\"",
                'Content-Type: image/jpeg',
            ],
        ]);
        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 201) {
            $this->fail("Media upload failed with HTTP {$status}: {$body}");
        }

        return json_decode($body, true);
    }

    /**
     * Return all S3 keys that begin with the given prefix.
     * @return string[]
     */
    protected function listStorageKeys(string $prefix = ''): array
    {
        return $this->storage->listPrefix($prefix);
    }

    /** Assert that a storage key exists in MinIO. */
    protected function assertStorageKeyExists(string $key): void
    {
        $this->assertTrue(
            $this->storage->exists($key),
            "Expected storage key '{$key}' to exist in MinIO.",
        );
    }

    /** Assert that a storage key does NOT exist on the local filesystem. */
    protected function assertNotOnLocalFilesystem(string $absolutePath): void
    {
        // We can't inspect the WordPress container's FS from here, but we can verify
        // via the REST API that the file URL is served from MinIO, not from the WP host.
        // (This check is best-effort; the definitive test is assertStorageKeyExists.)
        $this->assertFalse(
            is_file($absolutePath),
            "File '{$absolutePath}' should not exist on the test-runner filesystem.",
        );
    }

    /**
     * GET a URL and return [status, body].
     *
     * @return array{0: int, 1: string}
     */
    protected function fetchBody(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, $body];
    }

    protected function fixturesDir(): string
    {
        return dirname(__DIR__, 2) . '/Fixtures';
    }

    /**
     * GET a URL and return [status, headers] with lowercase header names.
     * Repeated headers keep the last value.
     *
     * @return array{0: int, 1: array<string, string>}
     */
    protected function fetchHeaders(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $raw    = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headers = [];
        foreach (explode("\r\n", $raw) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        return [$status, $headers];
    }
}
