<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\Exception;

/**
 * Exception thrown when connection establishment exceeds the time limit.
 */
class ConnectTimeoutException extends ConnectException
{
}
