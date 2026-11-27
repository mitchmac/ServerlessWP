<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\Exception;

/**
 * Exception thrown when a handler is closed before a transfer completes.
 */
class HandlerClosedException extends TransferException
{
}
