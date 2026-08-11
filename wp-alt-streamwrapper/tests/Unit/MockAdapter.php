<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Tests\Unit;

use WpAltStreamWrapper\Adapters\Precondition;
use WpAltStreamWrapper\Adapters\PreconditionFailedException;
use WpAltStreamWrapper\Adapters\StorageAdapterInterface;

/**
 * In-memory adapter used by unit tests.
 */
class MockAdapter implements StorageAdapterInterface
{
    /** @var array<string, string> key => contents */
    private array $files = [];

    private bool $failNextPut = false;

    private bool $failNextFetch = false;

    /** @var array<string, true> keys whose delete() must fail */
    private array $undeletable = [];

    /** @var array<string, true> keys mutated on every put() to force conflicts */
    private array $churn = [];

    /** Every put() this adapter has seen, as ['key' => ..., 'ifMatch' => ...]. */
    private array $putLog = [];

    public function get(string $key): string|false
    {
        $result = $this->fetch($key);
        return $result['status'] === self::FETCH_FOUND ? (string) $result['contents'] : false;
    }

    public function fetch(string $key): array
    {
        if ($this->failNextFetch) {
            $this->failNextFetch = false;
            return ['status' => self::FETCH_ERROR, 'contents' => null, 'etag' => null];
        }

        if (!array_key_exists($key, $this->files)) {
            return ['status' => self::FETCH_NOT_FOUND, 'contents' => null, 'etag' => null];
        }

        return [
            'status'   => self::FETCH_FOUND,
            'contents' => $this->files[$key],
            'etag'     => $this->etag($key),
        ];
    }

    public function put(string $key, string $contents, ?Precondition $condition = null): bool
    {
        $this->putLog[] = [
            'key'           => $key,
            'ifMatch'       => $condition?->ifMatch,
            'requireAbsent' => (bool) $condition?->requireAbsent,
        ];

        if (isset($this->churn[$key])) {
            // Stand in for a writer that commits between every read and write,
            // so no conditional put can ever match.
            $this->files[$key] = 'churn-' . count($this->putLog);
        }

        if ($condition?->ifMatch !== null && $condition->ifMatch !== $this->etag($key)) {
            throw new PreconditionFailedException("mock conflict on '{$key}'");
        }

        if ($condition?->requireAbsent && array_key_exists($key, $this->files)) {
            throw new PreconditionFailedException("mock key '{$key}' already exists");
        }

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

    /** Test helper: make the next fetch() report a transport error, not absence. */
    public function failOnNextFetch(): void
    {
        $this->failNextFetch = true;
    }

    /** Test helper: make this key change on every put(), so no ifMatch ever matches. */
    public function changeOnEveryPut(string $key): void
    {
        $this->churn[$key] = true;
    }

    /** Test helper: make delete() fail for this key, as a storage error would. */
    public function failOnDelete(string $key): void
    {
        $this->undeletable[$key] = true;
    }

    /** Test helper: the ifMatch value passed with each put(), in order. */
    public function putLog(): array
    {
        return $this->putLog;
    }

    private function etag(string $key): ?string
    {
        return array_key_exists($key, $this->files) ? '"' . md5($this->files[$key]) . '"' : null;
    }

    public function delete(string $key): bool
    {
        if (isset($this->undeletable[$key])) {
            return false;
        }
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
