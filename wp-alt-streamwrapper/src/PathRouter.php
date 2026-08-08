<?php

declare(strict_types=1);

namespace WpAltStreamWrapper;

class PathRouter
{
    /** @var string[] absolute paths that are routed to remote storage */
    private array $targetPrefixes;

    /** @var string[] absolute path prefixes that are forced local (override target prefixes) */
    private array $excludePrefixes;

    /** @var string[] glob patterns matched against basename to force local */
    private array $excludePatterns;

    private string $wpContentDir;

    /**
     * @param string   $wpContentDir    absolute path to wp-content directory
     * @param string[] $targetRelPaths  paths relative to the WordPress root, e.g. 'wp-content'
     * @param string[] $excludePatterns glob patterns for basenames that must stay local
     * @param string[] $excludeRelPaths paths relative to the WordPress root that must stay local, e.g. 'wp-content/plugins'
     */
    public function __construct(string $wpContentDir, array $targetRelPaths, array $excludePatterns, array $excludeRelPaths = [])
    {
        $this->wpContentDir    = rtrim($wpContentDir, '/');
        $this->excludePatterns = $excludePatterns;

        $wpRoot = dirname($this->wpContentDir);

        $this->targetPrefixes = array_map(
            fn(string $rel) => $wpRoot . '/' . ltrim($rel, '/'),
            $targetRelPaths,
        );

        $this->excludePrefixes = array_map(
            fn(string $rel) => $wpRoot . '/' . ltrim($rel, '/'),
            $excludeRelPaths,
        );
    }

    public function isRemote(string $path): bool
    {
        $path = $this->normalize($path);

        if (!$this->matchesTargetPrefix($path)) {
            return false;
        }

        if ($this->matchesExcludePrefix($path)) {
            return false;
        }

        if ($this->isExcluded($path)) {
            return false;
        }

        // Allow WordPress plugins to override routing after WP is loaded.
        if (function_exists('apply_filters')) {
            return (bool) apply_filters('wp_alt_streamwrapper_use_remote', true, $path);
        }

        return true;
    }

    /** Convert an absolute local path to a storage key relative to wp-content. */
    public function toStorageKey(string $absolutePath): string
    {
        $path = $this->normalize($absolutePath);
        $prefix = $this->wpContentDir . '/';
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }
        // Fallback: strip leading slash
        return ltrim($path, '/');
    }

    /** Convert a storage key (relative to wp-content) back to an absolute local path. */
    public function toAbsolutePath(string $key): string
    {
        return $this->wpContentDir . '/' . ltrim($key, '/');
    }

    public function wpContentDir(): string
    {
        return $this->wpContentDir;
    }

    private function matchesTargetPrefix(string $path): bool
    {
        foreach ($this->targetPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    private function matchesExcludePrefix(string $path): bool
    {
        foreach ($this->excludePrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    private function isExcluded(string $path): bool
    {
        $basename = basename($path);
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $basename)) {
                return true;
            }
        }
        return false;
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
     * Required so that paths like /wp-content/uploads/../../../etc/passwd
     * cannot trick the prefix matching into routing them as remote.
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
}
