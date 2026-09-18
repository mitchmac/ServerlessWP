<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Exception;

use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Client\NetworkExceptionInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\RequestInterface;
/**
 * Base exception for transfer failures without a response.
 */
class NetworkException extends TransferException implements NetworkExceptionInterface
{
    public function __construct(string $message, RequestInterface $request, ?\Throwable $previous = null)
    {
        parent::__construct($message, $request, 0, $previous);
    }
}
