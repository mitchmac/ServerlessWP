<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\Exception;

/**
 * Exception thrown for HTTP responses with 5xx status codes.
 */
class ServerException extends BadResponseException
{
}
