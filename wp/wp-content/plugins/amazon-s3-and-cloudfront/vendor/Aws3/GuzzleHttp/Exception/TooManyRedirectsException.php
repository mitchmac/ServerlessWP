<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Exception;

/**
 * Exception thrown when redirect middleware reaches the redirect limit.
 */
class TooManyRedirectsException extends ResponseException
{
}
