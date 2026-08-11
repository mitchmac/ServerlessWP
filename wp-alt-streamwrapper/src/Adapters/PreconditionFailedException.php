<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Adapters;

use RuntimeException;

/**
 * Thrown by put() when a conditional write loses: the object's ETag no longer
 * matches the one the caller read, so another writer committed in between.
 *
 * Distinct from a plain `false` return, which means the write failed for a
 * reason retrying cannot fix (credentials, network, quota). A conflict is
 * recoverable — re-read and re-apply — so the wrapper needs to tell them apart.
 */
class PreconditionFailedException extends RuntimeException
{
}
