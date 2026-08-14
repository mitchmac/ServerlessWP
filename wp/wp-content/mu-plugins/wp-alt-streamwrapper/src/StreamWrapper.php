<?php

declare(strict_types=1);

namespace WpAltStreamWrapper;

use WpAltStreamWrapper\Adapters\Precondition;
use WpAltStreamWrapper\Adapters\PreconditionFailedException;
use WpAltStreamWrapper\Adapters\StorageAdapterInterface;

/**
 * Overrides file:// to route configured wp-content paths to remote storage and
 * pass all other paths to the native filesystem.
 */
class StreamWrapper
{
    /** Required by PHP — stream context resource set automatically. */
    public $context;

    // -------- Static state (shared across all instances) --------

    private static ?StorageAdapterInterface $adapter = null;
    private static ?PathRouter $router = null;

    /**
     * Request-local failures that fclose() cannot report. This distinguishes a
     * successful direct write from a failed write when neither has a local copy.
     *
     * @var array<string, true>
     */
    private static array $failedWrites = [];

    // -------- Per-handle state --------

    /** Whether the current open path is a remote (adapter-backed) target. */
    private bool $isRemote = false;

    /** Storage key for the open remote file. */
    private string $storageKey = '';

    /** php://temp handle used as a buffer for remote reads and writes. */
    private mixed $buffer = null;

    /** Native file handle used for passthrough (non-targeted) paths. */
    private mixed $realHandle = null;

    /** True if data was written to the buffer and must be uploaded on close. */
    private bool $isDirty = false;

    /** ETag downloaded by this handle for a conditional write on close. */
    private ?string $openEtag = null;

    /** True for modes that download first and then write back (r+, a, a+, c, c+). */
    private bool $isReadModifyWrite = false;

    /** True when no object existed at open, so the write on close creates it. */
    private bool $absentAtOpen = false;

    /** True for modes whose write may be conditioned on the object not existing yet. */
    private bool $canConditionOnAbsence = false;

    /** True for append modes, the only ones whose write can be replayed after a conflict. */
    private bool $isAppend = false;

    /** Length of the downloaded content in append mode; bytes past it are this handle's. */
    private int $appendBaseLength = 0;

    /** Re-read-and-reapply attempts before an append write is abandoned. */
    private const CONFLICT_RETRIES = 3;

    // -------- Per-opendir state --------

    /** Buffered directory entries for remote opendir. */
    private array $dirEntries = [];
    private int $dirIndex = 0;

    /** Native dir handle for passthrough opendir. */
    private mixed $realDirHandle = null;

    // -------- Registration --------

    public static function register(StorageAdapterInterface $adapter, PathRouter $router): void
    {
        self::$adapter      = $adapter;
        self::$router       = $router;
        self::$failedWrites = [];

        if (in_array('file', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('file');
        }
        stream_wrapper_register('file', static::class);
    }

    /** pushLocalFileStatus() outcomes. */
    public const PUSH_OK = 'pushed';
    public const PUSH_NOT_REMOTE = 'not-remote';
    public const PUSH_NO_LOCAL_COPY = 'no-local-copy';
    public const PUSH_FAILED = 'failed';

    /** Push a local upload that bypassed stream wrappers to remote storage. */
    public static function pushLocalFile(string $absolutePath, bool $deleteLocal = true): bool
    {
        return self::pushLocalFileStatus($absolutePath, $deleteLocal) === self::PUSH_OK;
    }

    /**
     * Push a local file and distinguish failure from a direct wrapper write that
     * left no local copy.
     *
     * @return self::PUSH_* one of the outcome constants above
     */
    public static function pushLocalFileStatus(string $absolutePath, bool $deleteLocal = true): string
    {
        if (self::$adapter === null || self::$router === null) {
            return self::PUSH_NOT_REMOTE;
        }
        if (!self::$router->isRemote($absolutePath)) {
            return self::PUSH_NOT_REMOTE;
        }

        $key = self::$router->toStorageKey($absolutePath);

        // Read from the real filesystem — our wrapper is active so we must bypass it.
        stream_wrapper_restore('file');
        $contents = @file_get_contents($absolutePath);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', static::class);

        if ($contents === false) {
            return self::PUSH_NO_LOCAL_COPY;
        }

        if (!self::$adapter->put($key, $contents)) {
            self::$failedWrites[$key] = true;
            trigger_error(
                "wp-alt-streamwrapper: failed to push uploaded file '{$key}' to remote storage",
                E_USER_WARNING,
            );
            return self::PUSH_FAILED;
        }

        unset(self::$failedWrites[$key]);
        StatCache::set($key, ['size' => strlen($contents), 'mtime' => time(), 'type' => 'file']);

        if ($deleteLocal) {
            stream_wrapper_restore('file');
            @unlink($absolutePath);
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', static::class);
        }

        return self::PUSH_OK;
    }

    /** Remove an unsuccessful upload from local disk and the stat cache. */
    public static function discardLocalFile(string $absolutePath): void
    {
        if (self::$router === null || !self::$router->isRemote($absolutePath)) {
            return;
        }

        StatCache::invalidate(self::$router->toStorageKey($absolutePath));

        stream_wrapper_restore('file');
        @unlink($absolutePath);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', static::class);
    }

    public static function unregister(): void
    {
        if (in_array('file', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('file');
        }
        stream_wrapper_restore('file');
    }

    public static function isRegistered(): bool
    {
        return self::$adapter !== null && self::$router !== null;
    }

    public static function isRemotePath(string $path): bool
    {
        return self::$router !== null && self::$router->isRemote($path);
    }

    /**
     * Check storage directly, avoiding url_stat()'s additional directory probes.
     */
    public static function existsInStorage(string $absolutePath): bool
    {
        if (self::$adapter === null || self::$router === null) {
            return false;
        }
        if (!self::$router->isRemote($absolutePath)) {
            return false;
        }

        $key    = self::$router->toStorageKey($absolutePath);
        $cached = StatCache::get($key);
        if ($cached !== null) {
            return $cached['type'] !== 'missing';
        }

        $stat = self::$adapter->stat($key);
        if ($stat === false) {
            StatCache::set($key, ['type' => 'missing', 'size' => 0, 'mtime' => 0]);
            return false;
        }

        StatCache::set($key, $stat);
        return true;
    }

    /** Whether this request recorded a failed remote write for the path. */
    public static function writeFailed(string $absolutePath): bool
    {
        if (self::$router === null || !self::$router->isRemote($absolutePath)) {
            return false;
        }
        return isset(self::$failedWrites[self::$router->toStorageKey($absolutePath)]);
    }

    /** Cache a local upload so WordPress sees it before it reaches storage. */
    public static function preCacheLocalFile(string $absolutePath): void
    {
        if (self::$router === null || !self::$router->isRemote($absolutePath)) {
            return;
        }

        $key = self::$router->toStorageKey($absolutePath);

        stream_wrapper_restore('file');
        $stat = @stat($absolutePath);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', static::class);

        if ($stat !== false) {
            StatCache::set($key, [
                'size'  => $stat['size'],
                'mtime' => $stat['mtime'],
                'type'  => 'file',
            ]);
        }
    }


    // -------- stream_open / stream_* --------

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $resolved = $this->normalize($path);

        if (!self::$router->isRemote($resolved)) {
            return $this->openPassthrough($resolved, $mode, $options, $opened_path);
        }

        $this->isRemote    = true;
        $this->storageKey  = self::$router->toStorageKey($resolved);
        $this->buffer      = fopen('php://temp', 'r+b');
        $this->isDirty     = false;

        if ($options & STREAM_USE_PATH) {
            $opened_path = $resolved;
        }

        $baseMode = $this->parseBaseMode($mode);

        // x/x+ require that the file does not already exist.
        if ($baseMode === 'x' || $baseMode === 'x+') {
            if (self::$adapter->exists($this->storageKey)) {
                fclose($this->buffer);
                $this->buffer = null;
                if ($options & STREAM_REPORT_ERRORS) {
                    trigger_error("wp-alt-streamwrapper: remote file '{$this->storageKey}' already exists", E_USER_WARNING);
                }
                return false;
            }
            $this->isDirty = true; // create the (possibly empty) object on close
            // Recheck absence conditionally when the buffered write closes.
            $this->absentAtOpen          = true;
            $this->canConditionOnAbsence = true;
        }

        // w/w+ truncate: mark dirty so closing without writing still creates/empties the object.
        if ($baseMode === 'w' || $baseMode === 'w+') {
            $this->isDirty = true;
        }

        // Modes below read the object before writing it back, so the write on
        // close is conditional on the version they read.
        $this->isReadModifyWrite = in_array($baseMode, ['r+', 'a', 'a+', 'c', 'c+'], true);

        if ($baseMode === 'r' || $baseMode === 'r+') {
            $existing = $this->download();
            if ($existing === null) {
                fclose($this->buffer);
                $this->buffer = null;
                if ($options & STREAM_REPORT_ERRORS) {
                    trigger_error("wp-alt-streamwrapper: cannot open remote file '{$this->storageKey}'", E_USER_WARNING);
                }
                return false;
            }
            fwrite($this->buffer, $existing);
            rewind($this->buffer);
            if ($baseMode === 'r+') {
                $this->isDirty = false; // will be set true on first write
            }
        }

        // These modes create missing objects, but a failed read leaves unknown
        // contents that must not be overwritten.
        if (in_array($baseMode, ['a', 'a+', 'c', 'c+'], true)) {
            $existing = $this->download();

            if ($existing === null && !$this->absentAtOpen) {
                fclose($this->buffer);
                $this->buffer = null;
                if ($options & STREAM_REPORT_ERRORS) {
                    trigger_error(
                        "wp-alt-streamwrapper: cannot read '{$this->storageKey}' to open it for "
                        . 'writing; refusing to overwrite contents that could not be read',
                        E_USER_WARNING,
                    );
                }
                return false;
            }

            // Absent at open means the write creates the object, so it can be
            // conditioned on the key still being free.
            $this->canConditionOnAbsence = $this->absentAtOpen;

            if ($existing !== null) {
                fwrite($this->buffer, $existing);
            }

            if ($baseMode === 'a' || $baseMode === 'a+') {
                $this->isAppend         = true;
                $this->appendBaseLength = $existing === null ? 0 : strlen($existing);
                fseek($this->buffer, 0, SEEK_END);
            } else {
                // c/c+: write without truncation, positioned at the start
                // (unlike w, which truncates).
                rewind($this->buffer);
            }
        }

        return true;
    }

    /**
     * Fetch contents and their ETag. $absentAtOpen distinguishes a missing object
     * from a failed read when null is returned.
     */
    private function download(): ?string
    {
        $result = self::$adapter->fetch($this->storageKey);

        if ($result['status'] === StorageAdapterInterface::FETCH_NOT_FOUND) {
            $this->absentAtOpen = true;
            return null;
        }

        if ($result['status'] !== StorageAdapterInterface::FETCH_FOUND) {
            return null;
        }

        $this->openEtag = $result['etag'];
        return (string) $result['contents'];
    }

    public function stream_read(int $count): string|false
    {
        if ($this->realHandle !== null) {
            return fread($this->realHandle, $count);
        }
        return fread($this->buffer, $count);
    }

    public function stream_write(string $data): int
    {
        if ($this->realHandle !== null) {
            return (int) fwrite($this->realHandle, $data);
        }
        $this->isDirty = true;
        return (int) fwrite($this->buffer, $data);
    }

    public function stream_close(): void
    {
        if ($this->realHandle !== null) {
            fclose($this->realHandle);
            $this->realHandle = null;
            return;
        }

        if ($this->isRemote && $this->isDirty && $this->buffer !== null) {
            rewind($this->buffer);
            $this->commit(stream_get_contents($this->buffer));
        }

        if ($this->buffer !== null) {
            fclose($this->buffer);
            $this->buffer = null;
        }
    }

    /**
     * Upload the buffer conditionally when this handle read or created the key.
     * Only w/w+ replace unconditionally. Since fclose() cannot report failure,
     * conflicts are recorded and warned instead of overwriting newer data.
     */
    private function commit(string $contents): void
    {
        try {
            if (self::$adapter->put($this->storageKey, $contents, $this->writeCondition())) {
                $this->cacheWritten($contents);
                return;
            }
            $this->recordFailedWrite();
            trigger_error(
                "wp-alt-streamwrapper: failed to upload '{$this->storageKey}' to remote storage",
                E_USER_WARNING,
            );
            return;
        } catch (PreconditionFailedException) {
            // Fall through to the conflict handling below.
        }

        if (!$this->isAppend) {
            $this->recordFailedWrite();
            trigger_error(
                $this->absentAtOpen
                    ? "wp-alt-streamwrapper: dropped write to '{$this->storageKey}' — another "
                        . 'writer created it first, and this handle has nothing of theirs to '
                        . 'merge with'
                    : "wp-alt-streamwrapper: dropped write to '{$this->storageKey}' — another "
                        . 'writer changed it after this handle read it, and replaying an '
                        . 'in-place edit would discard that change',
                E_USER_WARNING,
            );
            return;
        }

        // Appends are the one case that can be replayed: the bytes this handle
        // added are known, and adding them to the version that won produces the
        // same result as if the two writes had been ordered.
        $replayed = $this->replayAppend(substr($contents, $this->appendBaseLength));
        if ($replayed === false) {
            $this->recordFailedWrite();
            trigger_error(
                "wp-alt-streamwrapper: dropped append to '{$this->storageKey}' after "
                . self::CONFLICT_RETRIES . ' conflicting writes',
                E_USER_WARNING,
            );
            return;
        }

        $this->cacheWritten($replayed);
    }

    private function recordFailedWrite(): void
    {
        self::$failedWrites[$this->storageKey] = true;
    }

    /**
     * Match the version read, require a newly created key to remain absent, or
     * return null for an intentional w/w+ replacement.
     */
    private function writeCondition(): ?Precondition
    {
        if ($this->isReadModifyWrite && $this->openEtag !== null) {
            return Precondition::matches($this->openEtag);
        }

        if ($this->canConditionOnAbsence && $this->absentAtOpen) {
            return Precondition::absent();
        }

        return null;
    }

    /** @return string|false the committed contents, or false if every attempt lost */
    private function replayAppend(string $appended): string|false
    {
        for ($attempt = 0; $attempt < self::CONFLICT_RETRIES; $attempt++) {
            $current = self::$adapter->fetch($this->storageKey);
            if ($current['status'] !== StorageAdapterInterface::FETCH_FOUND) {
                // Gone again, or unreadable. Either way there is no known base
                // to append to, and guessing would risk replacing content.
                return false;
            }

            $merged   = (string) $current['contents'] . $appended;
            $etag     = $current['etag'];
            $condition = $etag !== null ? Precondition::matches($etag) : null;

            try {
                return self::$adapter->put($this->storageKey, $merged, $condition)
                    ? $merged
                    : false;
            } catch (PreconditionFailedException) {
                continue; // lost again — re-read and re-append
            }
        }

        return false;
    }

    private function cacheWritten(string $contents): void
    {
        // A later successful write clears an earlier failure for the same key.
        unset(self::$failedWrites[$this->storageKey]);

        StatCache::set($this->storageKey, [
            'size'  => strlen($contents),
            'mtime' => time(),
            'type'  => 'file',
        ]);
    }

    public function stream_flush(): bool
    {
        if ($this->realHandle !== null) {
            return fflush($this->realHandle);
        }
        // Remote writes are fully buffered and uploaded atomically on stream_close().
        // PHP calls stream_flush() internally as part of fclose(), so we must NOT
        // upload here — doing so would consume the adapter call before stream_close()
        // gets to perform its own upload and warning logic.
        return true;
    }

    public function stream_eof(): bool
    {
        if ($this->realHandle !== null) {
            return feof($this->realHandle);
        }
        return feof($this->buffer);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if ($this->realHandle !== null) {
            return fseek($this->realHandle, $offset, $whence) === 0;
        }
        return fseek($this->buffer, $offset, $whence) === 0;
    }

    public function stream_tell(): int|false
    {
        if ($this->realHandle !== null) {
            return ftell($this->realHandle);
        }
        return ftell($this->buffer);
    }

    public function stream_truncate(int $size): bool
    {
        if ($this->realHandle !== null) {
            return ftruncate($this->realHandle, $size);
        }
        if ($this->buffer !== null) {
            $this->isDirty = true;
            return ftruncate($this->buffer, $size);
        }
        return false;
    }

    public function stream_stat(): array|false
    {
        if ($this->realHandle !== null) {
            return fstat($this->realHandle) ?: false;
        }

        if (!$this->isRemote || $this->storageKey === '') {
            return false;
        }

        $cached = StatCache::get($this->storageKey);
        if ($cached !== null && $cached['type'] !== 'missing') {
            return StatCache::buildStatArray($cached);
        }

        $stat = self::$adapter->stat($this->storageKey);
        if ($stat !== false) {
            StatCache::set($this->storageKey, $stat);
            return StatCache::buildStatArray($stat);
        }

        return false;
    }

    public function stream_lock(int $operation): bool
    {
        if ($this->realHandle !== null) {
            // PHP may call this with 0 on close; flock() rejects that value.
            $base = $operation & ~LOCK_NB;
            if (!in_array($base, [LOCK_SH, LOCK_EX, LOCK_UN], true)) {
                return true;
            }
            return flock($this->realHandle, $operation);
        }
        // Object storage has no advisory locks; report success for compatibility.
        return true;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    public function stream_cast(int $castAs): mixed
    {
        return false;
    }

    // -------- URL operations (called on a fresh instance without stream_open) --------

    public function url_stat(string $path, int $flags): array|false
    {
        $resolved = $this->normalize($path);

        if (!self::$router->isRemote($resolved)) {
            return $this->withRealFs(function () use ($resolved, $flags) {
                return ($flags & STREAM_URL_STAT_LINK) ? @lstat($resolved) : @stat($resolved);
            });
        }

        $key    = self::$router->toStorageKey($resolved);
        $cached = StatCache::get($key);

        if ($cached !== null) {
            if ($cached['type'] === 'missing') {
                return false;
            }
            return StatCache::buildStatArray($cached);
        }

        $stat = self::$adapter->stat($key);
        if ($stat !== false) {
            StatCache::set($key, $stat);
            return StatCache::buildStatArray($stat);
        }

        // Not an object — it may still be a directory: either an empty-dir
        // marker ('key/') or a prefix with children from a previous invocation.
        // Object storage has no real directories, so is_dir()/wp_mkdir_p()
        // would otherwise break across requests.
        if (self::$adapter->exists($key . '/') || self::$adapter->listPrefix($key) !== []) {
            $entry = ['type' => 'dir', 'size' => 0, 'mtime' => time()];
            StatCache::set($key, $entry);
            return StatCache::buildStatArray($entry);
        }

        // Mark as missing to avoid repeated adapter calls in the same request.
        StatCache::set($key, ['type' => 'missing', 'size' => 0, 'mtime' => 0]);
        return false;
    }

    public function unlink(string $path): bool
    {
        $resolved = $this->normalize($path);

        if (!self::$router->isRemote($resolved)) {
            return (bool) $this->withRealFs(fn() => unlink($resolved));
        }

        $key    = self::$router->toStorageKey($resolved);
        $result = self::$adapter->delete($key);
        StatCache::invalidate($key);
        return $result;
    }

    public function rename(string $from, string $to): bool
    {
        $fromResolved = $this->normalize($from);
        $toResolved   = $this->normalize($to);
        $fromRemote   = self::$router->isRemote($fromResolved);
        $toRemote     = self::$router->isRemote($toResolved);

        if (!$fromRemote && !$toRemote) {
            return (bool) $this->withRealFs(fn() => rename($fromResolved, $toResolved));
        }

        if ($fromRemote && $toRemote) {
            $fromKey = self::$router->toStorageKey($fromResolved);
            $toKey   = self::$router->toStorageKey($toResolved);
            $result  = self::$adapter->rename($fromKey, $toKey);
            if ($result) {
                StatCache::invalidate($fromKey);
                StatCache::invalidate($toKey);
            }
            return $result;
        }

        // Cross-boundary: one side is remote, the other is local.
        if ($fromRemote) {
            $fromKey  = self::$router->toStorageKey($fromResolved);
            $contents = self::$adapter->get($fromKey);
            if ($contents === false) {
                return false;
            }
            $written = $this->withRealFs(fn() => file_put_contents($toResolved, $contents));
            if ($written === false) {
                return false;
            }
            self::$adapter->delete($fromKey);
            StatCache::invalidate($fromKey);
            return true;
        }

        // $toRemote
        $contents = $this->withRealFs(fn() => file_get_contents($fromResolved));
        if ($contents === false) {
            return false;
        }
        $toKey  = self::$router->toStorageKey($toResolved);
        $result = self::$adapter->put($toKey, $contents);
        if ($result) {
            $this->withRealFs(fn() => unlink($fromResolved));
            StatCache::set($toKey, ['size' => strlen($contents), 'mtime' => time(), 'type' => 'file']);
        }
        return $result;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        $resolved = $this->normalize($path);

        if (!self::$router->isRemote($resolved)) {
            $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
            return (bool) $this->withRealFs(fn() => mkdir($resolved, $mode, $recursive));
        }

        $key   = self::$router->toStorageKey($resolved);
        $entry = ['type' => 'dir', 'size' => 0, 'mtime' => time()];
        StatCache::set($key, $entry);

        if ($options & STREAM_MKDIR_RECURSIVE) {
            $parts      = explode('/', trim($key, '/'));
            $cumulative = '';
            foreach ($parts as $part) {
                $cumulative = $cumulative === '' ? $part : $cumulative . '/' . $part;
                StatCache::set($cumulative, $entry);
            }
        }

        // Persist a zero-byte 'key/' marker so the directory still exists for
        // the next invocation even if no file is ever written into it. Parents
        // become implicit directories via the url_stat() prefix check.
        return self::$adapter->put(rtrim($key, '/') . '/', '');
    }

    public function rmdir(string $path, int $options): bool
    {
        $resolved = $this->normalize($path);

        if (!self::$router->isRemote($resolved)) {
            return (bool) $this->withRealFs(fn() => rmdir($resolved));
        }

        $key = self::$router->toStorageKey($resolved);

        // Refuse to delete an entire top-level target (e.g. 'uploads', 'cache').
        // A key with no slash means it is the root of a configured target path.
        if (!str_contains($key, '/')) {
            trigger_error(
                "wp-alt-streamwrapper: refusing rmdir on root storage target '{$key}'",
                E_USER_WARNING,
            );
            return false;
        }

        $marker = rtrim($key, '/') . '/';

        // Match native rmdir(): never delete unnamed descendants. In object
        // storage, empty means no keys except the directory marker.
        $children = array_filter(
            self::$adapter->listPrefix($key),
            fn(string $childKey) => $childKey !== $marker,
        );

        if ($children !== []) {
            trigger_error(
                "wp-alt-streamwrapper: rmdir('{$resolved}') failed — directory not empty",
                E_USER_WARNING,
            );
            return false;
        }

        if (!self::$adapter->delete($marker)) {
            trigger_error(
                "wp-alt-streamwrapper: failed to delete directory marker '{$marker}'",
                E_USER_WARNING,
            );
            return false;
        }

        StatCache::invalidatePrefix($key);
        return true;
    }

    /**
     * touch()/chmod()/chown()/chgrp(). Without this method every one of those
     * calls on a remote path returns false; WordPress uses touch() in
     * WP_Filesystem_Direct and during upgrades.
     */
    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        $resolved = $this->normalize($path);

        if (!self::$router->isRemote($resolved)) {
            return (bool) $this->withRealFs(function () use ($resolved, $option, $value) {
                return match ($option) {
                    STREAM_META_TOUCH => empty($value)
                        ? @touch($resolved)
                        : @touch($resolved, $value[0] ?? time(), $value[1] ?? $value[0] ?? time()),
                    STREAM_META_OWNER, STREAM_META_OWNER_NAME => @chown($resolved, $value),
                    STREAM_META_GROUP, STREAM_META_GROUP_NAME => @chgrp($resolved, $value),
                    STREAM_META_ACCESS => @chmod($resolved, $value),
                    default => false,
                };
            });
        }

        $key = self::$router->toStorageKey($resolved);

        if ($option === STREAM_META_TOUCH) {
            if (self::$adapter->exists($key)) {
                return true;
            }
            $result = self::$adapter->put($key, '');
            if ($result) {
                StatCache::set($key, ['size' => 0, 'mtime' => time(), 'type' => 'file']);
            }
            return $result;
        }

        // Ownership/permissions are meaningless on object storage; report success.
        return true;
    }

    // -------- Directory iteration --------

    public function dir_opendir(string $path, int $options): bool
    {
        $resolved = $this->normalize($path);

        if (!self::$router->isRemote($resolved)) {
            $handle = $this->withRealFs(fn() => opendir($resolved));
            if ($handle === false) {
                return false;
            }
            $this->realDirHandle = $handle;
            return true;
        }

        $key     = self::$router->toStorageKey($resolved);
        $allKeys = self::$adapter->listPrefix($key);

        // Collect immediate children only (one path segment deep).
        $prefixWithSlash = rtrim($key, '/') . '/';
        $seen            = [];
        foreach ($allKeys as $k) {
            $relative = substr($k, strlen($prefixWithSlash));
            $segment  = explode('/', $relative)[0];
            if ($segment !== '' && !in_array($segment, $seen, true)) {
                $seen[] = $segment;
            }
        }

        $this->dirEntries = array_merge(['.', '..'], $seen);
        $this->dirIndex   = 0;
        return true;
    }

    public function dir_readdir(): string|false
    {
        if ($this->realDirHandle !== null) {
            return readdir($this->realDirHandle);
        }

        if ($this->dirIndex >= count($this->dirEntries)) {
            return false;
        }
        return $this->dirEntries[$this->dirIndex++];
    }

    public function dir_rewinddir(): bool
    {
        if ($this->realDirHandle !== null) {
            rewinddir($this->realDirHandle);
            return true;
        }
        $this->dirIndex = 0;
        return true;
    }

    public function dir_closedir(): bool
    {
        if ($this->realDirHandle !== null) {
            closedir($this->realDirHandle);
            $this->realDirHandle = null;
            return true;
        }
        $this->dirEntries = [];
        $this->dirIndex   = 0;
        return true;
    }

    // -------- Passthrough helpers --------

    /**
     * Open a passthrough handle to the real filesystem.
     * The real file:// wrapper is temporarily restored for the duration of fopen().
     */
    private function openPassthrough(string $resolved, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->isRemote = false;

        $handle = $this->withRealFs(fn() => @fopen($resolved, $mode));
        if ($handle === false) {
            if ($options & STREAM_REPORT_ERRORS) {
                trigger_error("wp-alt-streamwrapper: failed to open '{$resolved}' with mode '{$mode}'", E_USER_WARNING);
            }
            return false;
        }

        $this->realHandle = $handle;

        if ($options & STREAM_USE_PATH) {
            $opened_path = $resolved;
        }

        return true;
    }

    /**
     * Temporarily restore the native file:// wrapper, run $fn, then re-register ours.
     * Safe because PHP is single-threaded per request.
     */
    private function withRealFs(callable $fn): mixed
    {
        stream_wrapper_restore('file');
        try {
            return $fn();
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', static::class);
        }
    }

    private function normalize(string $path): string
    {
        if (str_starts_with($path, 'file://')) {
            $path = substr($path, 7);
        }
        return $this->resolveDots($path);
    }

    /**
     * Resolve . and .. components without touching the filesystem.
     * Prevents paths like /wp-content/uploads/../../../etc/passwd from
     * matching the uploads prefix and being routed as remote.
     */
    private function resolveDots(string $path): string
    {
        $parts    = explode('/', $path);
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }
        return '/' . implode('/', $resolved);
    }

    /**
     * Extract the base mode character from a mode string like 'r+b', 'wb', 'a+'.
     * Returns one of: r, r+, w, w+, a, a+, x, x+, c, c+
     */
    private function parseBaseMode(string $mode): string
    {
        // Strip binary/text flags
        $stripped = str_replace(['b', 't'], '', $mode);
        return $stripped;
    }
}
