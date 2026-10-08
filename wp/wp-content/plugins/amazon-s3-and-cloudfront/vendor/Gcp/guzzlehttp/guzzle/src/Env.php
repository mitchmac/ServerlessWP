<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp;

/**
 * Reads configuration from the process environment.
 *
 * Intentionally separate from Handler\ProxyEnv, which has different
 * lookup semantics.
 *
 * @internal
 */
final class Env
{
    private function __construct()
    {
    }
    public static function get(string $name) : ?string
    {
        if (isset($_SERVER[$name])) {
            return (string) $_SERVER[$name];
        }
        if (\PHP_SAPI === 'cli' && ($value = \getenv($name)) !== \false && $value !== null) {
            return (string) $value;
        }
        return null;
    }
}
