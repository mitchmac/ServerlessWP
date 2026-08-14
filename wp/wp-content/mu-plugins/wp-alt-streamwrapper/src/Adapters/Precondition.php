<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Adapters;

/**
 * Mutually exclusive conditions for matching an object version or requiring
 * an absent key. Failed conditions throw PreconditionFailedException.
 */
final class Precondition
{
    private function __construct(
        public readonly ?string $ifMatch,
        public readonly bool $requireAbsent,
    ) {
    }

    /** The stored object must still have this ETag. */
    public static function matches(string $etag): self
    {
        return new self($etag, false);
    }

    /** No object may exist at this key yet. */
    public static function absent(): self
    {
        return new self(null, true);
    }
}
