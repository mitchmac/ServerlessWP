<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Tests\Unit;

use ServerlessWpStreamWrapper\Adapters\Precondition;
use ServerlessWpStreamWrapper\Adapters\PreconditionFailedException;
use ServerlessWpStreamWrapper\Adapters\StorageAdapterInterface;

class MockAdapter implements StorageAdapterInterface
{
    private array $files = [];
    private bool $failNextPut = false;
    private bool $failNextFetch = false;
    private array $undeletable = [];
    private array $churn = [];
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

    public function failOnNextPut(): void
    {
        $this->failNextPut = true;
    }

    public function failOnNextFetch(): void
    {
        $this->failNextFetch = true;
    }

    public function changeOnEveryPut(string $key): void
    {
        $this->churn[$key] = true;
    }

    public function failOnDelete(string $key): void
    {
        $this->undeletable[$key] = true;
    }

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

    public function getContent(string $key): string|false
    {
        return $this->get($key);
    }

    public function seed(string $key, string $contents): void
    {
        $this->files[$key] = $contents;
    }

    public function keys(): array
    {
        return array_keys($this->files);
    }
}
