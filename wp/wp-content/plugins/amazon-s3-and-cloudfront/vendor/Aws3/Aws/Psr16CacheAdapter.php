<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws;

use DeliciousBrains\WP_Offload_Media\Aws3\Psr\SimpleCache\CacheInterface as SimpleCacheInterface;
class Psr16CacheAdapter implements CacheInterface
{
    /** @var SimpleCacheInterface */
    private $cache;
    public function __construct(SimpleCacheInterface $cache)
    {
        $this->cache = $cache;
    }
    public function get($key)
    {
        return $this->cache->get($key);
    }
    public function set($key, $value, $ttl = 0)
    {
        $this->cache->set($key, $value, $ttl);
    }
    public function remove($key)
    {
        $this->cache->delete($key);
    }
}
