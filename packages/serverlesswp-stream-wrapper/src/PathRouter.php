<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper;

class PathRouter
{
    /** @var string[] */
    private array $targetPrefixes;
    /** @var string[] */
    private array $excludePrefixes;
    /** @var string[] */
    private array $excludePatterns;
    private string $wpContentDir;
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

        if (function_exists('apply_filters')) {
            return (bool) apply_filters('serverlesswp_stream_wrapper_use_remote', true, $path);
        }

        return true;
    }

    public function toStorageKey(string $absolutePath): string
    {
        $path = $this->normalize($absolutePath);
        $prefix = $this->wpContentDir . '/';
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return ltrim($path, '/');
    }

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
