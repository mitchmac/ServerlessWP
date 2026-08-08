<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\E2E\Tests;

class DirectoryListingTest extends E2ETestCase
{
    public function testUploadDirectoryIsListableAfterUpload(): void
    {
        $fixture = $this->fixturesDir() . '/test-image.jpg';
        $this->uploadMedia($fixture, 'e2e-dirlist-test.jpg');

        // Verify MinIO has an uploads/ prefix with at least one object.
        $keys = $this->listStorageKeys('uploads');
        $this->assertNotEmpty($keys, 'MinIO uploads/ prefix should have objects after an upload.');
    }

    public function testYearMonthSubdirectoryExistsInStorage(): void
    {
        $fixture = $this->fixturesDir() . '/test-image.jpg';
        $this->uploadMedia($fixture, 'e2e-yearmonth-test.jpg');

        $keys = $this->listStorageKeys('uploads');

        // WordPress stores uploads in uploads/YYYY/MM/ — verify that structure appears.
        $hasYearDir = false;
        foreach ($keys as $key) {
            // Key should look like: uploads/2024/01/filename.jpg
            if (preg_match('#uploads/\d{4}/\d{2}/#', $key)) {
                $hasYearDir = true;
                break;
            }
        }

        $this->assertTrue($hasYearDir, 'Expected uploads/YYYY/MM/ directory structure in MinIO storage.');
    }
}
