<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\Handler;

/**
 * @internal
 */
final class Clock
{
    private function __construct()
    {
    }
    /**
     * Returns the current monotonic clock reading in seconds.
     */
    public static function now() : float
    {
        return \hrtime(\true) / 1000000000.0;
    }
}
