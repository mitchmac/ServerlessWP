<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\E2E\Tests;

/**
 * wp_handle_sideload() — imports, "add from URL", plugin-fetched media.
 *
 * These arrive at pre_move_uploaded_file with a tmp_name PHP never recorded in
 * $_FILES, so move_uploaded_file() cannot move them and the handler has to fall
 * back to rename(). REST uploads (every other test here) are real HTTP uploads
 * and never take that branch.
 */
class SideloadTest extends E2ETestCase
{
    private const HELPER = '/wp-content/mu-plugins/wp-alt-streamwrapper/tests/E2E/setup/sideload.php';

    private string $storageKey = '';

    protected function tearDown(): void
    {
        if ($this->storageKey !== '') {
            $this->storage->delete($this->storageKey);
        }
    }

    public function testSideloadedFileReachesRemoteStorage(): void
    {
        $tag = getmypid();
        [$status, $body] = $this->fetchBody(rtrim($this->wpUrl, '/') . self::HELPER . '?tag=' . $tag);

        $this->assertSame(200, $status, "Sideload helper failed: {$body}");

        $payload = json_decode($body, true);
        $this->assertIsArray($payload, "Sideload helper returned non-JSON: {$body}");

        $result = $payload['result'] ?? [];
        $this->assertArrayNotHasKey('error', $result, 'wp_handle_sideload() reported an error: ' . $body);
        $this->assertNotEmpty($result['file'] ?? '', 'No destination path in the sideload result.');

        // The destination must be a normal uploads path, and the bytes must be
        // in object storage rather than only on the container's disk.
        $this->assertStringContainsString('/wp-content/uploads/', $result['file']);

        $relative         = substr($result['file'], strpos($result['file'], '/wp-content/') + strlen('/wp-content/'));
        $this->storageKey = $relative;
        $this->assertStorageKeyExists($relative);

        $this->assertSame(
            filesize($this->fixturesDir() . '/test-image.jpg'),
            strlen((string) $this->storage->get($relative)),
            'Stored object does not match the sideloaded file size.',
        );
    }

    public function testSideloadDoesNotLeaveTheTempFileBehind(): void
    {
        [$status, $body] = $this->fetchBody(
            rtrim($this->wpUrl, '/') . self::HELPER . '?tag=' . (getmypid() + 1),
        );

        $this->assertSame(200, $status, "Sideload helper failed: {$body}");

        $payload = json_decode($body, true);
        $result  = $payload['result'] ?? [];

        if (!empty($result['file'])) {
            $this->storageKey = substr(
                $result['file'],
                strpos($result['file'], '/wp-content/') + strlen('/wp-content/'),
            );
        }

        $this->assertFalse(
            $payload['tmp_left_behind'] ?? true,
            'The staged tmp file survived the sideload: ' . ($payload['tmp_path'] ?? '?'),
        );
    }
}
