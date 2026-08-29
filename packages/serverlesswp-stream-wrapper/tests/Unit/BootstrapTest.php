<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ServerlessWpStreamWrapper\Bootstrap;

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

    public function testVercelBlobCredentialsAreAccepted(): void
    {
        $this->assertNull(Bootstrap::validateVercelBlob('oidc.token', 'store_abc123'));
    }

    public function testVercelOidcTokenIsConsumedBeforeWordPressRuns(): void
    {
        $server = [
            'HTTP_X_VERCEL_OIDC_TOKEN' => 'request.oidc.token',
            'DOCUMENT_ROOT'            => '/tmp/wp',
        ];

        $token = Bootstrap::consumeVercelOidcToken($server);

        $this->assertSame('request.oidc.token', $token);
        $this->assertArrayNotHasKey('HTTP_X_VERCEL_OIDC_TOKEN', $server);
        $this->assertSame('/tmp/wp', $server['DOCUMENT_ROOT']);
    }

    public function testVercelBlobMissingCredentialsRefuseRegistration(): void
    {
        $warning = Bootstrap::validateVercelBlob(null, null);

        $this->assertStringContainsString('OIDC or read-write token', (string) $warning);
        $this->assertStringContainsString('Blob store id', (string) $warning);
        $this->assertStringContainsString('Not registering', (string) $warning);
    }

    public function testVercelBlobMissingStoreRefusesRegistration(): void
    {
        $warning = Bootstrap::validateVercelBlob('oidc.token', null);

        $this->assertStringNotContainsString('OIDC or read-write token', (string) $warning);
        $this->assertStringContainsString('Blob store id', (string) $warning);
    }

    public function testConfiguredDirectoryTakesPrecedenceOverDocumentRootCheck(): void
    {
        $configured = $this->root . '/readonly/wp-content';

        $result = Bootstrap::resolveWpContentDir($configured, $this->root . '/docroot/wp-content', $this->root . '/docroot');

        $this->assertSame($configured, $result['dir']);
        $this->assertNull($result['warning']);
    }

    public function testMissingConfiguredDirectoryRefusesToRegister(): void
    {
        $result = Bootstrap::resolveWpContentDir($this->root . '/nope', $this->root . '/docroot/wp-content');

        $this->assertNull($result['dir']);
        $this->assertStringContainsString('SERVERLESSWP_STREAM_WP_CONTENT_DIR', (string) $result['warning']);
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
