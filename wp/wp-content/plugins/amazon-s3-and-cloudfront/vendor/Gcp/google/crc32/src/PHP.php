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

use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\CRCInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\CRCTrait;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\Table;
/**
 * PHP implementation of the CRC32 algorithm.
 *
 * Uses a simple lookup table to improve the performances.
 */
final class PHP implements CRCInterface
{
    use CRCTrait;
    private $polynomial;
    private $crc;
    private $table = [];
    public static function supports($algo)
    {
        return \true;
    }
    /**
     * Creates a new instance for this polynomial.
     *
     * @param  integer  $polynomial  The polynomial
     */
    public function __construct($polynomial)
    {
        $this->polynomial = $polynomial;
        $this->table = Table::get($polynomial);
        $this->reset();
    }
    public function reset()
    {
        $this->crc = ~0;
    }
    public function update($data)
    {
        $crc = $this->crc;
        $table = $this->table;
        $len = \strlen($data);
        for ($i = 0; $i < $len; ++$i) {
            $crc = $crc >> 8 & 0xffffff ^ $table[($crc ^ \ord($data[$i])) & 0xff];
        }
        $this->crc = $crc;
    }
    public function hash($raw_output = \false)
    {
        return $this->crcHash(~$this->crc, $raw_output);
    }
    public function version()
    {
        return 'crc32(' . $this->int2hex($this->polynomial) . ') software version';
    }
}
