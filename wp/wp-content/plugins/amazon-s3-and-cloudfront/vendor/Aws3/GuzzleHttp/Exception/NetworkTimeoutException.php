<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Exception;

/**
 * Exception thrown when a transfer times out before response headers are
 * received.
 */
class NetworkTimeoutException extends NetworkException
{
}
