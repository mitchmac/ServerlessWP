<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\E2E\Tests;

class DirectoryListingTest extends E2ETestCase
{
    public function testUploadDirectoryIsListableAfterUpload(): void
    {
        $fixture = $this->fixturesDir() . '/test-image.jpg';
        $this->uploadMedia($fixture, 'e2e-dirlist-test.jpg');

        $keys = $this->listStorageKeys('uploads');
        $this->assertNotEmpty($keys, 'MinIO uploads/ prefix should have objects after an upload.');
    }

    public function testYearMonthSubdirectoryExistsInStorage(): void
    {
        $fixture = $this->fixturesDir() . '/test-image.jpg';
        $this->uploadMedia($fixture, 'e2e-yearmonth-test.jpg');

        $keys = $this->listStorageKeys('uploads');

        $hasYearDir = false;
        foreach ($keys as $key) {
            if (preg_match('#uploads/\d{4}/\d{2}/#', $key)) {
                $hasYearDir = true;
                break;
            }
        }

        $this->assertTrue($hasYearDir, 'Expected uploads/YYYY/MM/ directory structure in MinIO storage.');
    }
}
