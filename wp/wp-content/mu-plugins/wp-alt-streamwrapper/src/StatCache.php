<?php

declare(strict_types=1);

namespace WpAltStreamWrapper;

/**
 * In-process cache for remote file stat results.
 *
 * Keyed by storage key (e.g. 'uploads/2024/01/photo.jpg').
 * Entries: ['size' => int, 'mtime' => int, 'type' => 'file'|'dir'|'missing']
 *
 * 'missing' entries carry a short TTL so that warm Lambda containers (where the
 * same PHP process may handle multiple requests) don't serve stale negatives if
 * another invocation writes the file after we cached it as absent.
 */
class StatCache
{
    private const MISSING_TTL = 5; // seconds

    private static array $cache = [];

    public static function get(string $key): ?array
    {
        $entry = self::$cache[$key] ?? null;
        if ($entry === null) {
            return null;
        }
        if (($entry['type'] ?? '') === 'missing') {
            if (time() > ($entry['expires'] ?? 0)) {
                unset(self::$cache[$key]);
                return null;
            }
        }
        return $entry;
    }

    public static function set(string $key, array $entry): void
    {
        if (($entry['type'] ?? '') === 'missing') {
            $entry['expires'] = time() + self::MISSING_TTL;
        }
        self::$cache[$key] = $entry;
    }

    public static function invalidate(string $key): void
    {
        unset(self::$cache[$key]);
    }

    public static function invalidatePrefix(string $prefix): void
    {
        $prefix = rtrim($prefix, '/');
        foreach (array_keys(self::$cache) as $key) {
            if ($key === $prefix || str_starts_with($key, $prefix . '/')) {
                unset(self::$cache[$key]);
            }
        }
    }

    /** Reset the entire cache (used in tests). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Build the 26-element PHP stat array expected by stream wrappers.
     * File mode bits: regular file = 0100644, directory = 040755.
     */
    public static function buildStatArray(array $entry): array
    {
        $isDir = ($entry['type'] ?? 'file') === 'dir';
        $size  = (int) ($entry['size'] ?? 0);
        $mtime = (int) ($entry['mtime'] ?? time());
        $mode  = $isDir ? 0040755 : 0100644;

        return [
            0  => 0,     'dev'     => 0,
            1  => 0,     'ino'     => 0,
            2  => $mode, 'mode'    => $mode,
            3  => 1,     'nlink'   => 1,
            4  => 0,     'uid'     => 0,
            5  => 0,     'gid'     => 0,
            6  => 0,     'rdev'    => 0,
            7  => $size, 'size'    => $size,
            8  => $mtime,'atime'   => $mtime,
            9  => $mtime,'mtime'   => $mtime,
            10 => $mtime,'ctime'   => $mtime,
            11 => -1,    'blksize' => -1,
            12 => -1,    'blocks'  => -1,
        ];
    }
}
