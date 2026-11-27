<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp;

/**
 * @internal
 */
trait NonSerializableTrait
{
    public function __serialize() : array
    {
        throw new \LogicException(static::class . ' should never be serialized');
    }
    public function __unserialize(array $data) : void
    {
        throw new \LogicException(static::class . ' should never be unserialized');
    }
}
