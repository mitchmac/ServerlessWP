<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Exception;

/**
 * Exception thrown when a handler is closed before a transfer completes.
 */
class HandlerClosedException extends TransferException
{
}
