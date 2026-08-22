<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\Adapters;

final class Precondition
{
    private function __construct(
        public readonly ?string $ifMatch,
        public readonly bool $requireAbsent,
    ) {
    }

    public static function matches(string $etag): self
    {
        return new self($etag, false);
    }

    public static function absent(): self
    {
        return new self(null, true);
    }
}
