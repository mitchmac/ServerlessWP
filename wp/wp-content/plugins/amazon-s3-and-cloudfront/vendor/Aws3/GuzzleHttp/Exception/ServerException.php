<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Exception;

/**
 * Exception thrown for HTTP responses with 5xx status codes.
 */
class ServerException extends BadResponseException
{
}
