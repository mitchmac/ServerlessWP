<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\Exception;

/**
 * Exception thrown when a transfer times out after response headers are
 * received.
 */
class ResponseTimeoutException extends ResponseTransferException
{
}
