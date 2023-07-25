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
 * PHP implementation of the CRC32 sliced-by-4 algorithm.
 *
 * This is typically faster, but the PHP implementation seems slower than the
 * simple implementation.
 */
final class PHPSlicedBy4 implements CRCInterface
{
    use CRCTrait;
    private $polynomial;
    private $crc;
    private $table;
    public static function supports($algo)
    {
        return \true;
    }
    public function __construct($polynomial)
    {
        $this->polynomial = $polynomial;
        $this->table = Table::create4($polynomial);
        $this->reset();
    }
    public function reset()
    {
        $this->crc = ~0;
    }
    public function update($data)
    {
        $crc = $this->crc;
        $table0 = $this->table[0];
        $table1 = $this->table[1];
        $table2 = $this->table[2];
        $table3 = $this->table[3];
        $len = \strlen($data);
        $remain = $len % 4;
        $len1 = $len - $remain;
        for ($i = 0; $i < $len1; $i += 4) {
            $b = \ord($data[$i + 3]) << 24 | \ord($data[$i + 2]) << 16 | \ord($data[$i + 1]) << 8 | \ord($data[$i]);
            $crc = ($crc ^ $b) & 0xffffffff;
            $crc = $table3[$crc & 0xff] ^ $table2[$crc >> 8 & 0xff] ^ $table1[$crc >> 16 & 0xff] ^ $table0[$crc >> 24 & 0xff];
        }
        switch ($remain) {
            case 3:
                $crc = $crc >> 8 & 0xffffff ^ $table0[($crc ^ \ord($data[$i])) & 0xff];
                $crc = $crc >> 8 & 0xffffff ^ $table0[($crc ^ \ord($data[$i + 1])) & 0xff];
                $crc = $crc >> 8 & 0xffffff ^ $table0[($crc ^ \ord($data[$i + 2])) & 0xff];
                break;
            case 2:
                $crc = $crc >> 8 & 0xffffff ^ $table0[($crc ^ \ord($data[$i])) & 0xff];
                $crc = $crc >> 8 & 0xffffff ^ $table0[($crc ^ \ord($data[$i + 1])) & 0xff];
                break;
            case 1:
                $crc = $crc >> 8 & 0xffffff ^ $table0[($crc ^ \ord($data[$i])) & 0xff];
                break;
            case 0:
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
