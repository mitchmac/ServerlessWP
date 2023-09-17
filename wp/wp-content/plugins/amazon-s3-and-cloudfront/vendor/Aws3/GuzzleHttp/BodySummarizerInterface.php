<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp;

use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message) : ?string;
}
