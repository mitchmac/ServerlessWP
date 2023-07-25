<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws;

use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\ResponseInterface;
interface ResponseContainerInterface
{
    /**
     * Get the received HTTP response if any.
     *
     * @return ResponseInterface|null
     */
    public function getResponse();
}
