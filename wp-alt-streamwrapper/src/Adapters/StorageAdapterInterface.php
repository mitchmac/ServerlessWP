<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Adapters;

interface StorageAdapterInterface
{
    /** fetch() outcomes. */
    public const FETCH_FOUND = 'found';
    public const FETCH_NOT_FOUND = 'not-found';
    public const FETCH_ERROR = 'error';

    /** Download and return the full contents of a stored object. */
    public function get(string $key): string|false;

    /**
     * Download an object, distinguishing absence from a failed read so callers
     * cannot mistake unknown contents for a safe create.
     *
     * @return array{status: self::FETCH_*, contents: ?string, etag: ?string}
     */
    public function fetch(string $key): array;

    /**
     * Upload contents under the given key, overwriting any existing object
     * unless $condition says otherwise.
     *
     * @throws PreconditionFailedException if $condition is not satisfied.
     */
    public function put(string $key, string $contents, ?Precondition $condition = null): bool;

    /** Delete the object at the given key. Returns true even if not found. */
    public function delete(string $key): bool;

    /** Return true if an object exists at the given key. */
    public function exists(string $key): bool;

    /**
     * Return metadata for an object, or false if not found.
     * Result keys: 'size' (int bytes), 'mtime' (int unix timestamp), 'type' ('file'|'dir')
     */
    public function stat(string $key): array|false;

    /**
     * List all object keys whose names begin with the given prefix.
     * Keys are relative to wp-content (e.g. 'uploads/2024/01/photo.jpg').
     *
     * @return string[]
     */
    public function listPrefix(string $prefix): array;

    /** Copy an object from one key to another, then delete the source. */
    public function rename(string $from, string $to): bool;
}
