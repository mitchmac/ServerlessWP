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

    protected function listStorageKeys(string $prefix = ''): array
    {
        return $this->storage->listPrefix($prefix);
    }

    protected function assertStorageKeyExists(string $key): void
    {
        $this->assertTrue(
            $this->storage->exists($key),
            "Expected storage key '{$key}' to exist in MinIO.",
        );
    }

    protected function assertNotOnLocalFilesystem(string $absolutePath): void
    {
        $this->assertFalse(
            is_file($absolutePath),
            "File '{$absolutePath}' should not exist on the test-runner filesystem.",
        );
    }

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
