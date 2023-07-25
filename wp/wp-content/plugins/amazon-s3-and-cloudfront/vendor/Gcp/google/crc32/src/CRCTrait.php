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

trait CRCTrait
{
    /**
     * Converts a integer into a 8 character hex string in lower case.
     *
     * @param  integer  $i  Integer to convert.
     *
     * @return  string 8 character hex string in lower case.
     */
    private static function int2hex($i)
    {
        return \str_pad(\dechex($i), 8, '0', \STR_PAD_LEFT);
    }
    /**
     * { function_description }
     *
     * @param  integer  $crc  The CRC hash
     * @param  boolean  $raw_output  When set to TRUE, outputs raw binary data.
     *                               FALSE outputs lowercase hexits.
     *
     * @return string  Returns a string containing the calculated CRC as
     *                 lowercase hexits unless raw_output is set to true in
     *                 which case the raw binary representation of the CRC is
     *                 returned.
     */
    private static function crcHash($crc, $raw_output)
    {
        $crc = $crc & 0xffffffff;
        if ($raw_output) {
            return \pack('N', $crc);
        }
        return self::int2hex($crc);
    }
}
