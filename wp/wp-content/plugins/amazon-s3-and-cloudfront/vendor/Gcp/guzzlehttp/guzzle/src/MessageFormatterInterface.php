<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp;

use DeliciousBrains\WP_Offload_Media\Gcp\Psr\Http\Message\RequestInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Psr\Http\Message\ResponseInterface;
interface MessageFormatterInterface
{
    /**
     * Returns a formatted message string.
     *
     * @param RequestInterface       $request  Request that was sent
     * @param ResponseInterface|null $response Response that was received
     * @param \Throwable|null        $error    Exception that was received
     */
    public function format(RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $error = null) : string;
}
