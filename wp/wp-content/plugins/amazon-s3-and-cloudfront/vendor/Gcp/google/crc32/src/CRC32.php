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

use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\Builtin;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\Google;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\PHP;
/**
 * Various CRC32 implementations.
 *
 * ```
 * use Google\CRC32\CRC32;
 *
 * $crc = CRC32::create(CRC32::CASTAGNOLI);
 * $crc->update('hello');
 *
 * echo $crc->hash();
 * ```
 */
class CRC32
{
    use CRCTrait;
    /**
     * IEEE polynomial as used by ethernet (IEEE 802.3), v.42, fddi, gzip,
     * zip, png, ...
     */
    public const IEEE = 0xedb88320;
    /**
     * Castagnoli's polynomial, used in iSCSI, SCTP, Google Cloud Storage,
     * Apache Kafka, and has hardware-accelerated in modern intel CPUs.
     * https://doi.org/10.1109/26.231911
     */
    public const CASTAGNOLI = 0x82f63b78;
    /**
     * Koopman's polynomial.
     * https://doi.org/10.1109/DSN.2002.1028931
     */
    public const KOOPMAN = 0xeb31d82e;
    /**
     * The size of the checksum in bytes.
     */
    public const SIZE = 4;
    private static $mapping = [self::IEEE => 'IEEE', self::CASTAGNOLI => 'Castagnoli', self::KOOPMAN => 'Koopman'];
    private function __construct()
    {
        // Prevent instantiation.
    }
    /**
     * Returns the best CRC implementation available on this machine.
     *
     * @param  integer  $polynomial  The CRC polynomial. Use a 32-bit number,
     *                               or one of the supplied constants, CRC32::IEEE,
     *                               CRC32::CASTAGNOLI, or CRC32::KOOPMAN.
     *
     * @return  CRC32Interface
     */
    public static function create($polynomial)
    {
        if (Google::supports($polynomial) && \function_exists('DeliciousBrains\\WP_Offload_Media\\Gcp\\crc32c')) {
            return new Google();
        }
        if (Builtin::supports($polynomial)) {
            return new Builtin($polynomial);
        }
        // Fallback to the pure PHP version
        return new PHP($polynomial);
    }
    /**
     * Prints the human friendly name for this polynomial.
     *
     * @param  integer  $polynomial  The CRC polynomial.
     *
     * @return  string
     */
    public static function string($polynomial)
    {
        if (isset(self::$mapping[$polynomial])) {
            return self::$mapping[$polynomial];
        }
        return '0x' . self::int2hex($polynomial);
    }
}
