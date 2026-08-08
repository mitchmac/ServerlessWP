<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAltStreamWrapper\Bootstrap;

class BootstrapTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wasw-bootstrap-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/docroot/wp-content', 0777, true);
        mkdir($this->root . '/readonly/wp-content', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (['/docroot/wp-content', '/docroot', '/readonly/wp-content', '/readonly', ''] as $dir) {
            @rmdir($this->root . $dir);
        }
    }

    public function testConfiguredDirectoryIsUsed(): void
    {
        $configured = $this->root . '/docroot/wp-content';

        $result = Bootstrap::resolveWpContentDir($configured, $this->root . '/readonly/wp-content');

        $this->assertSame($configured, $result['dir']);
        $this->assertNull($result['warning']);
    }

    public function testConfiguredDirectoryTakesPrecedenceOverDocumentRootCheck(): void
    {
        // An explicit setting outside the document root is the whole point of
        // the /tmp layout, so it must not warn.
        $configured = $this->root . '/readonly/wp-content';

        $result = Bootstrap::resolveWpContentDir($configured, $this->root . '/docroot/wp-content', $this->root . '/docroot');

        $this->assertSame($configured, $result['dir']);
        $this->assertNull($result['warning']);
    }

    public function testMissingConfiguredDirectoryRefusesToRegister(): void
    {
        $result = Bootstrap::resolveWpContentDir($this->root . '/nope', $this->root . '/docroot/wp-content');

        $this->assertNull($result['dir']);
        $this->assertStringContainsString('WP_STREAM_WP_CONTENT_DIR', (string) $result['warning']);
    }

    public function testEmptyConfiguredValueFallsBackToInference(): void
    {
        $inferred = $this->root . '/docroot/wp-content';

        $result = Bootstrap::resolveWpContentDir('', $inferred);

        $this->assertSame($inferred, $result['dir']);
        $this->assertNull($result['warning']);
    }

    public function testInferredDirectoryIsUsedWhenUnconfigured(): void
    {
        $inferred = $this->root . '/docroot/wp-content';

        $result = Bootstrap::resolveWpContentDir(null, $inferred, $this->root . '/docroot');

        $this->assertSame($inferred, $result['dir']);
        $this->assertNull($result['warning']);
    }

    public function testMissingInferredDirectoryRefusesToRegister(): void
    {
        $result = Bootstrap::resolveWpContentDir(null, $this->root . '/nope/wp-content');

        $this->assertNull($result['dir']);
        $this->assertStringContainsString('not a directory', (string) $result['warning']);
    }

    public function testInferredDirectoryOutsideDocumentRootWarnsButStillRegisters(): void
    {
        // The serverless case: the bootstrap is loaded from the read-only
        // bundle while WordPress serves from a writable copy.
        $inferred = $this->root . '/readonly/wp-content';

        $result = Bootstrap::resolveWpContentDir(null, $inferred, $this->root . '/docroot');

        $this->assertSame($inferred, $result['dir']);
        $this->assertStringContainsString('outside the document root', (string) $result['warning']);
    }

    public function testUnknownDocumentRootSkipsTheMismatchCheck(): void
    {
        $inferred = $this->root . '/readonly/wp-content';

        foreach ([null, ''] as $documentRoot) {
            $result = Bootstrap::resolveWpContentDir(null, $inferred, $documentRoot);

            $this->assertSame($inferred, $result['dir']);
            $this->assertNull($result['warning']);
        }
    }

    public function testTrailingSlashesDoNotTriggerAFalseMismatch(): void
    {
        $result = Bootstrap::resolveWpContentDir(null, $this->root . '/docroot/wp-content', $this->root . '/docroot/');

        $this->assertNull($result['warning']);
    }
}
