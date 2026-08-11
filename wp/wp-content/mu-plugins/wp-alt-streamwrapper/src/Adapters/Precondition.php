<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Adapters;

/**
 * A condition a write must satisfy, or it fails with
 * PreconditionFailedException instead of overwriting someone else's object.
 *
 * Two forms, and they are mutually exclusive — hence a value object rather than
 * two optional put() arguments that could contradict each other:
 *
 *  - matches($etag): the object must still be the version the caller read.
 *  - absent():       the object must not exist yet.
 *
 * Providers express the second one differently: S3 takes `If-None-Match: *`,
 * while Vercel Blob has no put-side equivalent and instead rejects a write to an
 * existing key when `x-allow-overwrite` is absent. Both end up in the same
 * exception.
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
