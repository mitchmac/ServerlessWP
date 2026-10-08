<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\Exception;

/**
 * Exception thrown when redirect middleware reaches the redirect limit.
 */
class TooManyRedirectsException extends ResponseException
{
}
