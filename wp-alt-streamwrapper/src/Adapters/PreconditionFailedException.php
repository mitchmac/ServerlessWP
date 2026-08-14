<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Adapters;

use RuntimeException;

/** A recoverable conditional-write conflict, distinct from other write errors. */
class PreconditionFailedException extends RuntimeException
{
}
