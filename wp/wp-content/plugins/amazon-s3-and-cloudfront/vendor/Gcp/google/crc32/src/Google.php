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
 * A CRC32 implementation using hardware acceleration.
 *
 * This uses the C++ https://github.com/google/crc32c library, thus depends on
 * the `crc32c` PHP extension.
 */
final class Google implements CRCInterface
{
    private $crc;
    public static function supports($algo)
    {
        return $algo === CRC32::CASTAGNOLI;
    }
    public function __construct()
    {
        if (!\function_exists('DeliciousBrains\\WP_Offload_Media\\Gcp\\crc32c')) {
            throw new \InvalidArgumentException("crc32c function not found. Please load the 'crc32c' extension.");
        }
        $this->reset();
    }
    public function reset()
    {
        $this->crc = \hex2bin('00000000');
    }
    public function update($data)
    {
        $this->crc = crc32c($data, $this->crc);
    }
    public function hash($raw_output = \false)
    {
        if ($raw_output === \true) {
            return $this->crc;
        }
        return \bin2hex($this->crc);
    }
    public function version()
    {
        return 'Hardware accelerated (https://github.com/google/crc32c)';
    }
}
