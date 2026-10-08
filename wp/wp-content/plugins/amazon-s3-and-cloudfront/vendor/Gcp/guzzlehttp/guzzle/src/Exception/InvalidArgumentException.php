<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\Exception;

/**
 * Exception thrown when an invalid argument is supplied to Guzzle.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements GuzzleException
{
}
