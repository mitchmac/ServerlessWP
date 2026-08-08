<?php

declare(strict_types=1);

namespace WpAltStreamWrapper;

use WpAltStreamWrapper\Adapters\StorageAdapterInterface;

/**
 * Custom stream wrapper that overrides the built-in file:// protocol.
 *
 * Targeted paths (under configured wp-content subdirectories) are transparently
 * routed to the configured remote storage adapter. All other paths pass through
 * to the native filesystem.
 *
 * Registration via StreamWrapper::register(); the static adapter/router are
 * shared across all instances (required by PHP's stream_wrapper_register API).
 */
class StreamWrapper
{
    /** Required by PHP — stream context resource set automatically. */
    public $context;

    // -------- Static state (shared across all instances) --------

    private static ?StorageAdapterInterface $adapter = null;
    private static ?PathRouter $router = null;

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

    // -------- Per-opendir state --------

    /** Buffered directory entries for remote opendir. */
    private array $dirEntries = [];
    private int $dirIndex = 0;

    /** Native dir handle for passthrough opendir. */
    private mixed $realDirHandle = null;

    // -------- Registration --------

    public static function register(StorageAdapterInterface $adapter, PathRouter $router): void
    {
        self::$adapter = $adapter;
        self::$router  = $router;

        if (in_array('file', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('file');
        }
        stream_wrapper_register('file', static::class);
    }

    /**
     * Push a file that was placed on the local filesystem by move_uploaded_file()
     * (which bypasses PHP stream wrappers) up to remote storage, then delete
     * the local copy.
     *
     * Called from the WordPress wp_handle_upload / wp_handle_sideload filters.
     */
    public static function pushLocalFile(string $absolutePath, bool $deleteLocal = true): bool
    {
        if (self::$adapter === null || self::$router === null) {
            return false;
        }
        if (!self::$router->isRemote($absolutePath)) {
            return false;
        }

        $key = self::$router->toStorageKey($absolutePath);

        // Read from the real filesystem — our wrapper is active so we must bypass it.
        stream_wrapper_restore('file');
        $contents = @file_get_contents($absolutePath);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', static::class);

        if ($contents === false) {
            return false;
        }

        if (!self::$adapter->put($key, $contents)) {
            trigger_error(
                "wp-alt-streamwrapper: failed to push uploaded file '{$key}' to remote storage",
                E_USER_WARNING,
            );
            return false;
        }

        StatCache::set($key, ['size' => strlen($contents), 'mtime' => time(), 'type' => 'file']);

        if ($deleteLocal) {
            stream_wrapper_restore('file');
            @unlink($absolutePath);
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', static::class);
        }

        return true;
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
     * Record a file that was just written to the real local filesystem so that
     * url_stat() / file_exists() return true before the file is pushed to MinIO.
     *
     * Without this, wp_generate_attachment_metadata()'s file_exists() check fails
     * (file is on disk but not in MinIO yet) and WordPress skips thumbnail generation.
     */
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
        }

        // w/w+ truncate: mark dirty so closing without writing still creates/empties the object.
        if ($baseMode === 'w' || $baseMode === 'w+') {
            $this->isDirty = true;
        }

        if ($baseMode === 'r' || $baseMode === 'r+') {
            $contents = self::$adapter->get($this->storageKey);
            if ($contents === false) {
                fclose($this->buffer);
                $this->buffer = null;
                if ($options & STREAM_REPORT_ERRORS) {
                    trigger_error("wp-alt-streamwrapper: cannot open remote file '{$this->storageKey}'", E_USER_WARNING);
                }
                return false;
            }
            fwrite($this->buffer, $contents);
            rewind($this->buffer);
            if ($baseMode === 'r+') {
                $this->isDirty = false; // will be set true on first write
            }
        }

        if ($baseMode === 'a' || $baseMode === 'a+') {
            $existing = self::$adapter->get($this->storageKey);
            if ($existing !== false) {
                fwrite($this->buffer, $existing);
            }
            fseek($this->buffer, 0, SEEK_END);
        }

        // c: write without truncation, position at start (unlike w which truncates).
        // c+: same but also readable.
        if ($baseMode === 'c' || $baseMode === 'c+') {
            $existing = self::$adapter->get($this->storageKey);
            if ($existing !== false) {
                fwrite($this->buffer, $existing);
            }
            rewind($this->buffer);
        }

        return true;
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
            $contents = stream_get_contents($this->buffer);
            if (self::$adapter->put($this->storageKey, $contents)) {
                StatCache::set($this->storageKey, [
                    'size'  => strlen($contents),
                    'mtime' => time(),
                    'type'  => 'file',
                ]);
            } else {
                trigger_error(
                    "wp-alt-streamwrapper: failed to upload '{$this->storageKey}' to remote storage",
                    E_USER_WARNING,
                );
            }
        }

        if ($this->buffer !== null) {
            fclose($this->buffer);
            $this->buffer = null;
        }
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
            // PHP calls this with 0 when a stream is closed while holding no
            // lock, and flock() raises a ValueError on anything that isn't
            // LOCK_SH/LOCK_EX/LOCK_UN. Passing it straight through turns an
            // ordinary file_put_contents(..., LOCK_EX) on a local path into a
            // fatal error for the whole request.
            $base = $operation & ~LOCK_NB;
            if (!in_array($base, [LOCK_SH, LOCK_EX, LOCK_UN], true)) {
                return true;
            }
            return flock($this->realHandle, $operation);
        }
        // No advisory locks on remote storage. Report success so callers that
        // treat a false return as a hard failure (WP_Filesystem, caching
        // plugins) proceed instead of aborting.
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

        // Refuse to recursively delete an entire top-level target (e.g. 'uploads', 'cache').
        // A key with no slash means it is the root of a configured target path.
        if (!str_contains($key, '/')) {
            trigger_error(
                "wp-alt-streamwrapper: refusing rmdir on root storage target '{$key}'",
                E_USER_WARNING,
            );
            return false;
        }

        $children = self::$adapter->listPrefix($key);
        foreach ($children as $childKey) {
            self::$adapter->delete($childKey);
        }
        self::$adapter->delete(rtrim($key, '/') . '/'); // empty-dir marker
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
