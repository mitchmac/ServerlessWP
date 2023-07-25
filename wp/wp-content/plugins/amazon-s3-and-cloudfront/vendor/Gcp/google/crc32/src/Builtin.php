<?php

/**
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32;

use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\CRC32;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\CRCInterface;
/**
 * A CRC32 implementation based on the PHP hash functions.
 */
final class Builtin implements CRCInterface
{
    private $hc;
    private $algo;
    private static $mapping = [CRC32::IEEE => 'crc32b', CRC32::CASTAGNOLI => 'crc32c'];
    /**
     * Returns true if this $polynomial is supported by the builtin PHP hash function.
     *
     * @param  integer  $polynomial  The polynomial
     *
     * @return  boolean
     */
    public static function supports($polynomial)
    {
        if (!isset(self::$mapping[$polynomial])) {
            return \false;
        }
        $algo = self::$mapping[$polynomial];
        return \in_array($algo, \hash_algos());
    }
    public function __construct($polynomial)
    {
        if (!self::supports($polynomial)) {
            throw new \InvalidArgumentException("hash_algos() does not list this polynomial.");
        }
        $this->algo = self::$mapping[$polynomial];
        $this->reset();
    }
    public function reset()
    {
        $this->hc = \hash_init($this->algo);
    }
    public function update($data)
    {
        \hash_update($this->hc, $data);
    }
    public function hash($raw_output = \false)
    {
        // hash_final will destory the Hash Context resource, so operate on a copy.
        $hc = \hash_copy($this->hc);
        return \hash_final($hc, $raw_output);
    }
    public function version()
    {
        return $this->algo . ' PHP HASH';
    }
    public function __clone()
    {
        $this->hc = \hash_copy($this->hc);
    }
}
