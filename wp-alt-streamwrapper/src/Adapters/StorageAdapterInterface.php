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
     * Download an object, reporting absence separately from failure.
     *
     * The three outcomes have to be distinguishable or a caller cannot write
     * safely: "not there" means a create, while "the request failed" means the
     * current contents are unknown, and treating the second as the first turns a
     * transient error into a truncation.
     *
     * On FETCH_FOUND, 'etag' is the version read — it arrives on the same
     * response as the body, so conditioning a later write on it costs no extra
     * round trip. It is null if the provider sent none, and the caller must then
     * treat the write as unconditional.
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
