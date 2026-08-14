<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\E2E\Tests;

class ImageResizeTest extends E2ETestCase
{
    public function testImageVariantsAreStoredInMinIO(): void
    {
        $fixture    = $this->fixturesDir() . '/test-image.jpg';
        $attachment = $this->uploadMedia($fixture, 'e2e-resize-test.jpg');

        $this->assertArrayHasKey('id', $attachment);

        $keys    = $this->listStorageKeys('uploads');
        $related = array_filter($keys, fn($k) => str_contains($k, 'e2e-resize-test'));

        $this->assertGreaterThanOrEqual(
            2,
            count($related),
            'Expected at least the original image and one resized variant in MinIO. Found: ' . implode(', ', $related),
        );
    }

    public function testSizesInAttachmentMediaDetailsAreAccessible(): void
    {
        $fixture    = $this->fixturesDir() . '/test-image.jpg';
        $attachment = $this->uploadMedia($fixture, 'e2e-sizes-test.jpg');

        $sizes = $attachment['media_details']['sizes'] ?? [];
        $this->assertNotEmpty($sizes, 'Attachment should have media size details.');

        foreach ($sizes as $sizeName => $sizeData) {
            $sizeUrl = $sizeData['source_url'] ?? '';
            if ($sizeUrl === '') {
                continue;
            }

            $ch = curl_init($sizeUrl);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->assertSame(
                200,
                $status,
                "Size '{$sizeName}' URL is not reachable: {$sizeUrl} (HTTP {$status})",
            );
        }
    }
}
