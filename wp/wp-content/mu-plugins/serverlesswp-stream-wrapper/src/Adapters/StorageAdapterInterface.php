<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Adapters;

interface StorageAdapterInterface
{
    public const FETCH_FOUND = 'found';
    public const FETCH_NOT_FOUND = 'not-found';
    public const FETCH_ERROR = 'error';
    public function get(string $key): string|false;
    /** @return array{status:string, contents:?string, etag:?string} */
    public function fetch(string $key): array;
    /** @throws PreconditionFailedException */
    public function put(string $key, string $contents, ?Precondition $condition = null): bool;
    public function delete(string $key): bool;
    public function exists(string $key): bool;
    public function stat(string $key): array|false;
    /** @return string[] */
    public function listPrefix(string $prefix): array;
    public function rename(string $from, string $to): bool;
}
