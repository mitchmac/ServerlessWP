<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAltStreamWrapper\PathRouter;
use WpAltStreamWrapper\StatCache;
use WpAltStreamWrapper\StreamWrapper;

/**
 * StreamWrapper integration-style unit tests.
 *
 * These tests register the stream wrapper with a MockAdapter and exercise it through
 * actual PHP file functions (fopen, file_get_contents, etc.) so that the wrapper's
 * interception logic is tested end-to-end without a real storage backend.
 *
 * The "wp-content" root used here is /tmp/wp-streamwrapper-test, which is a path
 * that does not exist on the real filesystem — ensuring all operations inside
 * /tmp/wp-streamwrapper-test/uploads/ go through the mock adapter.
 */
class StreamWrapperTest extends TestCase
{
    private MockAdapter $adapter;
    private PathRouter $router;

    private const WP_CONTENT = '/tmp/wp-streamwrapper-test/wp-content';
    private const UPLOADS     = self::WP_CONTENT . '/uploads';

    protected function setUp(): void
    {
        StatCache::flush();
        $this->adapter = new MockAdapter();
        $this->router  = new PathRouter(
            self::WP_CONTENT,
            ['wp-content/uploads'],
            ['*.sqlite', '*.db', '*.php', '.htaccess'],
        );
        StreamWrapper::register($this->adapter, $this->router);
    }

    protected function tearDown(): void
    {
        StreamWrapper::unregister();
        StatCache::flush();
    }

    // -------- write / read round-trip --------

    public function testWriteAndReadBack(): void
    {
        $path = self::UPLOADS . '/hello.txt';
        file_put_contents($path, 'hello world');

        $this->assertSame('hello world', $this->adapter->getContent('uploads/hello.txt'));
        $this->assertSame('hello world', file_get_contents($path));
    }

    public function testFopenWriteMode(): void
    {
        $path = self::UPLOADS . '/fopen.txt';
        $fh   = fopen($path, 'w');
        fwrite($fh, 'chunk1');
        fwrite($fh, 'chunk2');
        fclose($fh);

        $this->assertSame('chunk1chunk2', $this->adapter->getContent('uploads/fopen.txt'));
    }

    public function testFopenReadMode(): void
    {
        $this->adapter->seed('uploads/existing.txt', 'pre-seeded content');

        $fh      = fopen(self::UPLOADS . '/existing.txt', 'r');
        $content = fread($fh, 4096);
        fclose($fh);

        $this->assertSame('pre-seeded content', $content);
    }

    public function testReadNonExistentFileReturnsFalse(): void
    {
        $result = @file_get_contents(self::UPLOADS . '/missing.txt');
        $this->assertFalse($result);
    }

    public function testAppendMode(): void
    {
        $this->adapter->seed('uploads/log.txt', 'line1\n');
        $fh = fopen(self::UPLOADS . '/log.txt', 'a');
        fwrite($fh, 'line2\n');
        fclose($fh);

        $this->assertSame('line1\nline2\n', $this->adapter->getContent('uploads/log.txt'));
    }

    // -------- stat / file_exists --------

    public function testFileExistsForStoredFile(): void
    {
        $this->adapter->seed('uploads/exists.txt', 'data');
        $this->assertTrue(file_exists(self::UPLOADS . '/exists.txt'));
    }

    public function testFileExistsReturnsFalseForMissing(): void
    {
        $this->assertFalse(file_exists(self::UPLOADS . '/ghost.txt'));
    }

    public function testStatCacheIsPopulatedAfterWrite(): void
    {
        file_put_contents(self::UPLOADS . '/cached.txt', 'abc');
        $cached = StatCache::get('uploads/cached.txt');
        $this->assertNotNull($cached);
        $this->assertSame(3, $cached['size']);
        $this->assertSame('file', $cached['type']);
    }

    // -------- unlink --------

    public function testUnlink(): void
    {
        $this->adapter->seed('uploads/delete-me.txt', 'bye');
        $path   = self::UPLOADS . '/delete-me.txt';
        $result = unlink($path);

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->exists('uploads/delete-me.txt'));
    }

    // -------- rename --------

    public function testRenameRemoteToRemote(): void
    {
        $this->adapter->seed('uploads/old.txt', 'content');
        $result = rename(self::UPLOADS . '/old.txt', self::UPLOADS . '/new.txt');

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->exists('uploads/old.txt'));
        $this->assertSame('content', $this->adapter->getContent('uploads/new.txt'));
    }

    // -------- mkdir / rmdir --------

    public function testMkdirSetsStatCacheDirectory(): void
    {
        mkdir(self::UPLOADS . '/2024', 0755, true);
        $stat = StatCache::get('uploads/2024');
        $this->assertNotNull($stat);
        $this->assertSame('dir', $stat['type']);
    }

    public function testRmdirDeletesChildren(): void
    {
        $this->adapter->seed('uploads/2024/photo.jpg', 'jpg data');
        $this->adapter->seed('uploads/2024/thumb.jpg', 'thumb data');

        rmdir(self::UPLOADS . '/2024');

        $this->assertFalse($this->adapter->exists('uploads/2024/photo.jpg'));
        $this->assertFalse($this->adapter->exists('uploads/2024/thumb.jpg'));
    }

    // -------- opendir / readdir --------

    public function testOpendirListsImmediateChildren(): void
    {
        $this->adapter->seed('uploads/2024/a.jpg', 'a');
        $this->adapter->seed('uploads/2024/b.jpg', 'b');
        $this->adapter->seed('uploads/2024/sub/c.jpg', 'c');

        $dir     = opendir(self::UPLOADS . '/2024');
        $entries = [];
        while (($entry = readdir($dir)) !== false) {
            $entries[] = $entry;
        }
        closedir($dir);

        $this->assertContains('a.jpg', $entries);
        $this->assertContains('b.jpg', $entries);
        $this->assertContains('sub', $entries);    // directory, not c.jpg
        $this->assertNotContains('c.jpg', $entries);
        $this->assertContains('.', $entries);
        $this->assertContains('..', $entries);
    }

    // -------- exclusion patterns --------

    public function testSqliteFileIsNotIntercepted(): void
    {
        // SQLite files in uploads should pass through to real FS, not mock adapter.
        // We just verify the router does not route them — adapter gets nothing.
        $this->assertFalse($this->router->isRemote(self::UPLOADS . '/database.sqlite'));
    }

    // -------- c mode --------

    public function testCModePreservesExistingContent(): void
    {
        $this->adapter->seed('uploads/existing.txt', 'original');

        $fh = fopen(self::UPLOADS . '/existing.txt', 'c');
        fwrite($fh, 'UPDATED');
        fclose($fh);

        // 'c' positions at start and writes over — result is 'UPDATEDl' (original tail preserved)
        // but since we only write 7 bytes over 8, result should be 'UPDATEDl'.
        // Actually fwrite replaces from position 0 up to len(data), rest is preserved.
        $stored = $this->adapter->getContent('uploads/existing.txt');
        $this->assertStringStartsWith('UPDATED', $stored);
        // The original byte at position 7 ('l') should still be there.
        $this->assertSame('UPDATEDl', $stored);
    }

    public function testCModeSilentlyCreatesIfNotExists(): void
    {
        $fh = fopen(self::UPLOADS . '/new-c-mode.txt', 'c');
        fwrite($fh, 'hello');
        fclose($fh);

        $this->assertSame('hello', $this->adapter->getContent('uploads/new-c-mode.txt'));
    }

    // -------- stream_lock --------

    public function testStreamLockDelegatedForPassthroughFiles(): void
    {
        $realPath = sys_get_temp_dir() . '/wp-streamwrapper-lock-' . getmypid() . '.txt';
        file_put_contents($realPath, 'lockable');

        $fh     = fopen($realPath, 'r+');
        $result = flock($fh, LOCK_EX | LOCK_NB);
        flock($fh, LOCK_UN);
        fclose($fh);

        unlink($realPath);

        $this->assertTrue($result, 'flock() should work on passthrough file handles.');
    }

    public function testStreamLockReportsSuccessForRemoteFiles(): void
    {
        $this->adapter->seed('uploads/remote.txt', 'data');
        $fh     = fopen(self::UPLOADS . '/remote.txt', 'r');
        $result = flock($fh, LOCK_EX | LOCK_NB);
        fclose($fh);

        $this->assertTrue($result, 'flock() must report success for remote-backed files so callers proceed.');
    }

    public function testWritingLocalFileWithLockExDoesNotFatal(): void
    {
        // PHP calls stream_lock(0) when closing a stream that holds no lock.
        // flock() raises a ValueError on anything but LOCK_SH/LOCK_EX/LOCK_UN,
        // so forwarding it turned any locked write to a passthrough path into
        // a fatal error — which took down every request on a SQLite site,
        // because the SQLite plugin writes /tmp/.htaccess with LOCK_EX.
        $realPath = sys_get_temp_dir() . '/wp-streamwrapper-lockex-' . getmypid() . '.txt';

        $bytes = file_put_contents($realPath, 'DENY FROM ALL', LOCK_EX);

        $this->assertSame(13, $bytes);
        $this->assertSame('DENY FROM ALL', file_get_contents($realPath));

        unlink($realPath);
    }

    // -------- stream_close warning on put failure --------

    public function testStreamCloseEmitsWarningOnPutFailure(): void
    {
        $this->adapter->failOnNextPut();

        $warned = false;
        set_error_handler(static function (int $errno, string $errstr) use (&$warned): bool {
            if ($errno === E_USER_WARNING) {
                $warned = true;
            }
            return true; // suppress so PHPUnit's failOnWarning does not fire
        });

        $fh = fopen(self::UPLOADS . '/fail.txt', 'w');
        fwrite($fh, 'data');
        fclose($fh);

        restore_error_handler();

        $this->assertTrue($warned, 'stream_close() must emit E_USER_WARNING when put() fails.');
    }

    // -------- pushLocalFile --------

    public function testPushLocalFileUploadsAndRemovesLocalCopy(): void
    {
        $path = self::UPLOADS . '/pushed.txt';
        $this->createRealFile($path, 'pushed content');
        $this->assertTrue($this->realFileExists($path));

        $result = StreamWrapper::pushLocalFile($path);

        $this->assertTrue($result);
        $this->assertSame('pushed content', $this->adapter->getContent('uploads/pushed.txt'));
        $this->assertFalse($this->realFileExists($path));
    }

    public function testPushLocalFileKeepsLocalCopyWhenDeleteLocalFalse(): void
    {
        $path = self::UPLOADS . '/pushed-keep.txt';
        $this->createRealFile($path, 'keep content');

        $result = StreamWrapper::pushLocalFile($path, deleteLocal: false);

        $this->assertTrue($result);
        $this->assertSame('keep content', $this->adapter->getContent('uploads/pushed-keep.txt'));
        $this->assertTrue($this->realFileExists($path), 'Local copy must be preserved when deleteLocal is false.');
    }

    public function testPushLocalFileReturnsFalseForNonTargetedPath(): void
    {
        $result = StreamWrapper::pushLocalFile('/tmp/not-in-uploads.txt');
        $this->assertFalse($result);
    }

    public function testPushLocalFileReturnsFalseIfFileNotFound(): void
    {
        $result = StreamWrapper::pushLocalFile(self::UPLOADS . '/nonexistent-for-push.txt');
        $this->assertFalse($result);
    }

    // -------- rmdir root protection --------

    public function testRmdirOnRootTargetIsRefused(): void
    {
        $this->adapter->seed('uploads/photo.jpg', 'data');

        // Attempting to rmdir the 'uploads' root key should be refused.
        $result = @rmdir(self::UPLOADS);
        $this->assertFalse($result);

        // File should still exist.
        $this->assertTrue($this->adapter->exists('uploads/photo.jpg'));
    }

    public function testRmdirOnSubdirectorySucceeds(): void
    {
        $this->adapter->seed('uploads/2024/photo.jpg', 'data');
        $this->adapter->seed('uploads/2024/thumb.jpg', 'thumb');

        $result = rmdir(self::UPLOADS . '/2024');
        $this->assertTrue($result);
        $this->assertFalse($this->adapter->exists('uploads/2024/photo.jpg'));
    }

    // -------- path traversal --------

    public function testPathTraversalEscapingUploadsGoesToPassthrough(): void
    {
        // /tmp/wp-streamwrapper-test/wp-content/uploads/../../etc/passwd
        // resolves to /tmp/wp-streamwrapper-test/etc/passwd — not in uploads.
        // The router should NOT treat it as remote.
        $resolved = self::UPLOADS . '/../../etc/passwd';
        $this->assertFalse($this->router->isRemote($resolved));
    }

    // -------- passthrough (non-targeted paths) --------

    public function testPassthroughReadRealFile(): void
    {
        // Write a real file to /tmp and read it back through the wrapper.
        $realPath = sys_get_temp_dir() . '/wp-streamwrapper-passthrough-' . getmypid() . '.txt';
        file_put_contents($realPath, 'real content');

        $content = file_get_contents($realPath);
        $this->assertSame('real content', $content);

        unlink($realPath);
    }

    public function testPassthroughWriteRealFile(): void
    {
        $realPath = sys_get_temp_dir() . '/wp-streamwrapper-passthrough-write-' . getmypid() . '.txt';

        file_put_contents($realPath, 'written via wrapper');
        $this->assertStringEqualsFile($realPath, 'written via wrapper');

        unlink($realPath);
    }

    // -------- x mode --------

    public function testXModeFailsWhenRemoteFileExists(): void
    {
        $this->adapter->seed('uploads/taken.txt', 'here first');

        $fh = @fopen(self::UPLOADS . '/taken.txt', 'x');

        $this->assertFalse($fh);
        $this->assertSame('here first', $this->adapter->getContent('uploads/taken.txt'));
    }

    public function testXModeCreatesFileEvenWithoutWrite(): void
    {
        $fh = fopen(self::UPLOADS . '/exclusive.txt', 'x');
        fclose($fh);

        $this->assertSame('', $this->adapter->getContent('uploads/exclusive.txt'));
    }

    // -------- w mode truncation --------

    public function testWModeWithoutWriteTruncatesExistingObject(): void
    {
        $this->adapter->seed('uploads/truncate-me.txt', 'old content');

        $fh = fopen(self::UPLOADS . '/truncate-me.txt', 'w');
        fclose($fh);

        $this->assertSame('', $this->adapter->getContent('uploads/truncate-me.txt'));
    }

    // -------- touch / chmod (stream_metadata) --------

    public function testTouchCreatesMissingRemoteFile(): void
    {
        $result = touch(self::UPLOADS . '/touched.txt');

        $this->assertTrue($result);
        $this->assertSame('', $this->adapter->getContent('uploads/touched.txt'));
    }

    public function testTouchSucceedsOnExistingRemoteFile(): void
    {
        $this->adapter->seed('uploads/already.txt', 'content');

        $this->assertTrue(touch(self::UPLOADS . '/already.txt'));
        $this->assertSame('content', $this->adapter->getContent('uploads/already.txt'));
    }

    public function testChmodReportsSuccessOnRemoteFile(): void
    {
        $this->adapter->seed('uploads/perms.txt', 'content');

        $this->assertTrue(chmod(self::UPLOADS . '/perms.txt', 0644));
    }

    // -------- directory existence across invocations --------

    public function testIsDirTrueForPrefixWithChildren(): void
    {
        // Simulates a later invocation: objects exist in storage but the
        // StatCache (fresh per process) knows nothing about the directory.
        $this->adapter->seed('uploads/2026/08/photo.jpg', 'jpg');
        StatCache::flush();

        $this->assertTrue(is_dir(self::UPLOADS . '/2026/08'));
        $this->assertTrue(is_dir(self::UPLOADS . '/2026'));
    }

    public function testIsDirTrueForEmptyDirMarker(): void
    {
        $this->adapter->seed('uploads/empty-dir/', '');
        StatCache::flush();

        $this->assertTrue(is_dir(self::UPLOADS . '/empty-dir'));
    }

    public function testIsDirFalseForMissingDirectory(): void
    {
        $this->assertFalse(is_dir(self::UPLOADS . '/nope'));
    }

    public function testMkdirPersistsAcrossStatCacheFlush(): void
    {
        mkdir(self::UPLOADS . '/2027', 0755, true);

        $this->assertContains('uploads/2027/', $this->adapter->keys());

        StatCache::flush(); // next invocation
        $this->assertTrue(is_dir(self::UPLOADS . '/2027'));
    }

    public function testRmdirRemovesEmptyDirMarker(): void
    {
        mkdir(self::UPLOADS . '/gone-soon');
        rmdir(self::UPLOADS . '/gone-soon');
        StatCache::flush();

        $this->assertFalse($this->adapter->exists('uploads/gone-soon/'));
        $this->assertFalse(is_dir(self::UPLOADS . '/gone-soon'));
    }

    // -------- cross-boundary operations (harvested from the mu-plugin suite) --------

    public function testRenameLocalToRemote(): void
    {
        $localPath = sys_get_temp_dir() . '/wp-streamwrapper-xdomain-' . getmypid() . '.txt';
        file_put_contents($localPath, 'moving to storage');

        $result = rename($localPath, self::UPLOADS . '/moved-in.txt');

        $this->assertTrue($result);
        $this->assertSame('moving to storage', $this->adapter->getContent('uploads/moved-in.txt'));
        $this->assertFalse($this->realFileExists($localPath), 'Source must be removed after cross-boundary rename.');
    }

    public function testRenameRemoteToLocal(): void
    {
        $this->adapter->seed('uploads/leaving.txt', 'moving to disk');
        $localPath = sys_get_temp_dir() . '/wp-streamwrapper-xdomain-out-' . getmypid() . '.txt';

        $result = rename(self::UPLOADS . '/leaving.txt', $localPath);

        $this->assertTrue($result);
        $this->assertStringEqualsFile($localPath, 'moving to disk');
        $this->assertFalse($this->adapter->exists('uploads/leaving.txt'));

        unlink($localPath);
    }

    public function testCopyLocalToRemote(): void
    {
        $localPath = sys_get_temp_dir() . '/wp-streamwrapper-copy-' . getmypid() . '.txt';
        file_put_contents($localPath, 'copied bytes');

        $result = copy($localPath, self::UPLOADS . '/copied.txt');

        $this->assertTrue($result);
        $this->assertSame('copied bytes', $this->adapter->getContent('uploads/copied.txt'));
        $this->assertTrue($this->realFileExists($localPath), 'copy() must leave the source in place.');

        unlink($localPath);
    }

    public function testIncludeOfLocalCodeExecutesUnderActiveWrapper(): void
    {
        $codePath = sys_get_temp_dir() . '/wp-streamwrapper-code-' . getmypid() . '.php';
        file_put_contents($codePath, '<?php return "included-" . (1 + 1);');

        $value = include $codePath;

        $this->assertSame('included-2', $value);

        unlink($codePath);
    }

    // -------- helpers --------

    private function createRealFile(string $path, string $content): void
    {
        stream_wrapper_restore('file');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', StreamWrapper::class);
    }

    private function realFileExists(string $path): bool
    {
        stream_wrapper_restore('file');
        $exists = file_exists($path);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', StreamWrapper::class);
        return $exists;
    }
}
