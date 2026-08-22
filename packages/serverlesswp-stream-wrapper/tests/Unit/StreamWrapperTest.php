<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ServerlessWpStreamWrapper\PathRouter;
use ServerlessWpStreamWrapper\StatCache;
use ServerlessWpStreamWrapper\StreamWrapper;

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

    public function testUnlink(): void
    {
        $this->adapter->seed('uploads/delete-me.txt', 'bye');
        $path   = self::UPLOADS . '/delete-me.txt';
        $result = unlink($path);

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->exists('uploads/delete-me.txt'));
    }

    public function testRenameRemoteToRemote(): void
    {
        $this->adapter->seed('uploads/old.txt', 'content');
        $result = rename(self::UPLOADS . '/old.txt', self::UPLOADS . '/new.txt');

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->exists('uploads/old.txt'));
        $this->assertSame('content', $this->adapter->getContent('uploads/new.txt'));
    }

    public function testMkdirSetsStatCacheDirectory(): void
    {
        mkdir(self::UPLOADS . '/2024', 0755, true);
        $stat = StatCache::get('uploads/2024');
        $this->assertNotNull($stat);
        $this->assertSame('dir', $stat['type']);
    }

    public function testRmdirRefusesNonEmptyDirectory(): void
    {
        $this->adapter->seed('uploads/2024/photo.jpg', 'jpg data');
        $this->adapter->seed('uploads/2024/thumb.jpg', 'thumb data');

        $this->assertFalse(@rmdir(self::UPLOADS . '/2024'));
        $this->assertTrue($this->adapter->exists('uploads/2024/photo.jpg'));
        $this->assertTrue($this->adapter->exists('uploads/2024/thumb.jpg'));
    }

    public function testRmdirRefusesDirectoryContainingOnlyASubdirectory(): void
    {
        $this->adapter->seed('uploads/2024/', '');
        $this->adapter->seed('uploads/2024/01/', '');

        $this->assertFalse(@rmdir(self::UPLOADS . '/2024'));
        $this->assertTrue($this->adapter->exists('uploads/2024/'));
    }

    public function testRmdirRemovesEmptyDirectory(): void
    {
        mkdir(self::UPLOADS . '/2024', 0755, true);
        $this->assertTrue($this->adapter->exists('uploads/2024/'));

        $this->assertTrue(rmdir(self::UPLOADS . '/2024'));
        $this->assertFalse($this->adapter->exists('uploads/2024/'));
        $this->assertNull(StatCache::get('uploads/2024'));
    }

    public function testRmdirReportsFailureWhenMarkerDeleteFails(): void
    {
        mkdir(self::UPLOADS . '/2024', 0755, true);
        $this->adapter->failOnDelete('uploads/2024/');

        $this->assertFalse(@rmdir(self::UPLOADS . '/2024'));
        $this->assertTrue($this->adapter->exists('uploads/2024/'));
    }

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
        $this->assertContains('sub', $entries);
        $this->assertNotContains('c.jpg', $entries);
        $this->assertContains('.', $entries);
        $this->assertContains('..', $entries);
    }

    public function testSqliteFileIsNotIntercepted(): void
    {
        $this->assertFalse($this->router->isRemote(self::UPLOADS . '/database.sqlite'));
    }

    public function testCModePreservesExistingContent(): void
    {
        $this->adapter->seed('uploads/existing.txt', 'original');

        $fh = fopen(self::UPLOADS . '/existing.txt', 'c');
        fwrite($fh, 'UPDATED');
        fclose($fh);

        $stored = $this->adapter->getContent('uploads/existing.txt');
        $this->assertStringStartsWith('UPDATED', $stored);

        $this->assertSame('UPDATEDl', $stored);
    }

    public function testCModeSilentlyCreatesIfNotExists(): void
    {
        $fh = fopen(self::UPLOADS . '/new-c-mode.txt', 'c');
        fwrite($fh, 'hello');
        fclose($fh);

        $this->assertSame('hello', $this->adapter->getContent('uploads/new-c-mode.txt'));
    }

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
        $realPath = sys_get_temp_dir() . '/wp-streamwrapper-lockex-' . getmypid() . '.txt';

        $bytes = file_put_contents($realPath, 'DENY FROM ALL', LOCK_EX);

        $this->assertSame(13, $bytes);
        $this->assertSame('DENY FROM ALL', file_get_contents($realPath));

        unlink($realPath);
    }

    public function testStreamCloseEmitsWarningOnPutFailure(): void
    {
        $this->adapter->failOnNextPut();

        $warned = false;
        set_error_handler(static function (int $errno, string $errstr) use (&$warned): bool {
            if ($errno === E_USER_WARNING) {
                $warned = true;
            }
            return true;
        });

        $fh = fopen(self::UPLOADS . '/fail.txt', 'w');
        fwrite($fh, 'data');
        fclose($fh);

        restore_error_handler();

        $this->assertTrue($warned, 'stream_close() must emit E_USER_WARNING when put() fails.');
    }

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

    public function testRmdirOnRootTargetIsRefused(): void
    {
        $this->adapter->seed('uploads/photo.jpg', 'data');

        $result = @rmdir(self::UPLOADS);
        $this->assertFalse($result);

        $this->assertTrue($this->adapter->exists('uploads/photo.jpg'));
    }

    public function testRmdirOnEmptySubdirectorySucceeds(): void
    {
        $this->adapter->seed('uploads/2024/', '');

        $this->assertTrue(rmdir(self::UPLOADS . '/2024'));
        $this->assertFalse($this->adapter->exists('uploads/2024/'));
    }

    public function testWholeFileWriteIsUnconditional(): void
    {
        $this->adapter->seed('uploads/style.css', 'old');

        file_put_contents(self::UPLOADS . '/style.css', 'new');

        $this->assertSame('new', $this->adapter->getContent('uploads/style.css'));
        $this->assertSame([null], array_column($this->adapter->putLog(), 'ifMatch'));
        $this->assertSame([false], array_column($this->adapter->putLog(), 'requireAbsent'));
    }

    public function testWholeFileWriteToANewKeyIsAlsoUnconditional(): void
    {
        file_put_contents(self::UPLOADS . '/fresh.css', 'body{}');

        $this->assertSame([false], array_column($this->adapter->putLog(), 'requireAbsent'));
    }

    public function testAppendWritesConditionallyOnTheVersionItRead(): void
    {
        $this->adapter->seed('uploads/log.txt', 'line1');
        $expectedIfMatch = '"' . md5('line1') . '"';

        $fh = fopen(self::UPLOADS . '/log.txt', 'a');
        fwrite($fh, 'line2');
        fclose($fh);

        $this->assertSame('line1line2', $this->adapter->getContent('uploads/log.txt'));
        $this->assertSame([$expectedIfMatch], array_column($this->adapter->putLog(), 'ifMatch'));
    }

    public function testInPlaceEditDoesNotOverwriteAConcurrentWriter(): void
    {
        $this->adapter->seed('uploads/counter.txt', 'aaaa');

        $fh = fopen(self::UPLOADS . '/counter.txt', 'r+');
        fwrite($fh, 'b');

        $this->adapter->seed('uploads/counter.txt', 'zzzz');

        @fclose($fh);

        $this->assertSame('zzzz', $this->adapter->getContent('uploads/counter.txt'));
    }

    public function testAppendIsReplayedOnTopOfTheWinningVersion(): void
    {
        $this->adapter->seed('uploads/log.txt', 'first\n');

        $fh = fopen(self::UPLOADS . '/log.txt', 'a');
        fwrite($fh, 'mine\n');

        $this->adapter->seed('uploads/log.txt', 'first\ntheirs\n');

        fclose($fh);

        $this->assertSame('first\ntheirs\nmine\n', $this->adapter->getContent('uploads/log.txt'));
    }

    public function testAppendGivesUpAfterRepeatedConflicts(): void
    {
        $this->adapter->seed('uploads/log.txt', 'start');
        $this->adapter->changeOnEveryPut('uploads/log.txt');

        $fh = fopen(self::UPLOADS . '/log.txt', 'a');
        fwrite($fh, 'mine');
        @fclose($fh);

        $this->assertStringNotContainsString('mine', (string) $this->adapter->getContent('uploads/log.txt'));
        $this->assertCount(4, $this->adapter->putLog());
    }

    public function testAppendToAMissingKeyRequiresTheKeyToStillBeFree(): void
    {
        $fh = fopen(self::UPLOADS . '/new-log.txt', 'a');
        fwrite($fh, 'first line');
        fclose($fh);

        $this->assertSame('first line', $this->adapter->getContent('uploads/new-log.txt'));
        $this->assertSame([true], array_column($this->adapter->putLog(), 'requireAbsent'));
    }

    public function testAppendToAMissingKeyMergesWithAWriterThatGotThereFirst(): void
    {
        $path = self::UPLOADS . '/race-log.txt';

        $fh = fopen($path, 'a');
        fwrite($fh, 'mine\n');

        $this->adapter->seed('uploads/race-log.txt', 'theirs\n');

        fclose($fh);

        $this->assertSame('theirs\nmine\n', $this->adapter->getContent('uploads/race-log.txt'));
    }

    public function testExclusiveCreateDoesNotOverwriteAKeyThatAppearedAfterOpen(): void
    {
        $path = self::UPLOADS . '/exclusive.txt';

        $fh = fopen($path, 'x');
        fwrite($fh, 'mine');

        $this->adapter->seed('uploads/exclusive.txt', 'theirs');

        @fclose($fh);

        $this->assertSame('theirs', $this->adapter->getContent('uploads/exclusive.txt'));
        $this->assertTrue(StreamWrapper::writeFailed($path));
    }

    public function testCreatingWithCModeDoesNotOverwriteAConcurrentCreate(): void
    {
        $path = self::UPLOADS . '/c-mode-race.txt';

        $fh = fopen($path, 'c');
        fwrite($fh, 'mine');

        $this->adapter->seed('uploads/c-mode-race.txt', 'theirs');

        @fclose($fh);

        $this->assertSame('theirs', $this->adapter->getContent('uploads/c-mode-race.txt'));
    }

    public function testOpenForAppendFailsWhenTheCurrentContentsCannotBeRead(): void
    {
        $this->adapter->seed('uploads/log.txt', 'existing data');
        $this->adapter->failOnNextFetch();

        $fh = @fopen(self::UPLOADS . '/log.txt', 'a');

        $this->assertFalse($fh);
        $this->assertSame('existing data', $this->adapter->getContent('uploads/log.txt'));
    }

    public function testFailedWriteIsRecorded(): void
    {
        $path = self::UPLOADS . '/never-lands.css';
        $this->adapter->failOnNextPut();

        $written = @file_put_contents($path, 'body{}');

        $this->assertSame(6, $written);
        $this->assertFalse($this->adapter->exists('uploads/never-lands.css'));
        $this->assertTrue(
            StreamWrapper::writeFailed($path),
            'A discarded remote write must leave a record; nothing else can distinguish it '
            . 'from a file that was written straight to storage.',
        );
    }

    public function testSuccessfulWriteLeavesNoFailureRecord(): void
    {
        $path = self::UPLOADS . '/lands.css';
        file_put_contents($path, 'body{}');

        $this->assertFalse(StreamWrapper::writeFailed($path));
    }

    public function testLaterSuccessfulWriteClearsTheFailureRecord(): void
    {
        $path = self::UPLOADS . '/retried.css';

        $this->adapter->failOnNextPut();
        @file_put_contents($path, 'first try');
        $this->assertTrue(StreamWrapper::writeFailed($path));

        file_put_contents($path, 'second try');

        $this->assertFalse(StreamWrapper::writeFailed($path));
        $this->assertSame('second try', $this->adapter->getContent('uploads/retried.css'));
    }

    public function testDroppedConflictingWriteIsRecordedAsFailed(): void
    {
        $this->adapter->seed('uploads/counter.txt', 'aaaa');
        $path = self::UPLOADS . '/counter.txt';

        $fh = fopen($path, 'r+');
        fwrite($fh, 'b');
        $this->adapter->seed('uploads/counter.txt', 'zzzz');
        @fclose($fh);

        $this->assertTrue(StreamWrapper::writeFailed($path));
    }

    public function testWriteFailedIsFalseForPathsNeverWritten(): void
    {
        $this->assertFalse(StreamWrapper::writeFailed(self::UPLOADS . '/untouched.txt'));
    }

    public function testPathTraversalEscapingUploadsGoesToPassthrough(): void
    {
        $resolved = self::UPLOADS . '/../../etc/passwd';
        $this->assertFalse($this->router->isRemote($resolved));
    }

    public function testPassthroughReadRealFile(): void
    {
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

    public function testWModeWithoutWriteTruncatesExistingObject(): void
    {
        $this->adapter->seed('uploads/truncate-me.txt', 'old content');

        $fh = fopen(self::UPLOADS . '/truncate-me.txt', 'w');
        fclose($fh);

        $this->assertSame('', $this->adapter->getContent('uploads/truncate-me.txt'));
    }

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

    public function testIsDirTrueForPrefixWithChildren(): void
    {
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

        StatCache::flush();
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
