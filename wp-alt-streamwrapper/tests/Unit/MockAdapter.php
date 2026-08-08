<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit;

use WpAltStreamWrapper\Adapters\StorageAdapterInterface;

/**
 * In-memory adapter used by unit tests.
 */
class MockAdapter implements StorageAdapterInterface
{
    /** @var array<string, string> key => contents */
    private array $files = [];

    private bool $failNextPut = false;

    public function get(string $key): string|false
    {
        return array_key_exists($key, $this->files) ? $this->files[$key] : false;
    }

    public function put(string $key, string $contents): bool
    {
        if ($this->failNextPut) {
            $this->failNextPut = false;
            return false;
        }
        $this->files[$key] = $contents;
        return true;
    }

    /** Test helper: make the next put() call return false. */
    public function failOnNextPut(): void
    {
        $this->failNextPut = true;
    }

    public function delete(string $key): bool
    {
        unset($this->files[$key]);
        return true;
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->files);
    }

    public function stat(string $key): array|false
    {
        if (!array_key_exists($key, $this->files)) {
            return false;
        }
        return [
            'size'  => strlen($this->files[$key]),
            'mtime' => 1700000000,
            'type'  => 'file',
        ];
    }

    public function listPrefix(string $prefix): array
    {
        $prefix = rtrim($prefix, '/') . '/';
        return array_keys(array_filter(
            $this->files,
            fn($k) => str_starts_with($k, $prefix),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    public function rename(string $from, string $to): bool
    {
        if (!array_key_exists($from, $this->files)) {
            return false;
        }
        $this->files[$to] = $this->files[$from];
        unset($this->files[$from]);
        return true;
    }

    /** Test helper: get raw stored content by key. */
    public function getContent(string $key): string|false
    {
        return $this->get($key);
    }

    /** Test helper: seed content before a test. */
    public function seed(string $key, string $contents): void
    {
        $this->files[$key] = $contents;
    }

    /** Test helper: list all stored keys. */
    public function keys(): array
    {
        return array_keys($this->files);
    }
}
