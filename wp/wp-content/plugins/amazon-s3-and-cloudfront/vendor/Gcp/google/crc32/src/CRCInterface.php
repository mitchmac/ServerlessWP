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

/**
 * CRC calculation interface.
 *
 * Lots of great info on the different algorithms used:
 * https://create.stephan-brumme.com/crc32/
 */
interface CRCInterface
{
    /**
     * Updates the CRC calculation with the supplied data.
     *
     * @param  string  $data  The data
     */
    public function update($data);
    /**
     * Resets the CRC calculation.
     */
    public function reset();
    /**
     * Return the current calculated CRC hash.
     *
     * @param boolean  $raw_output  When set to TRUE, outputs raw binary data.
     *                              FALSE outputs lowercase hexits.
     *
     * @return string  Returns a string containing the calculated CRC as
     *                 lowercase hexits unless raw_output is set to true in
     *                 which case the raw binary representation of the CRC is
     *                 returned.
     */
    public function hash($raw_output = \false);
    /**
     * Returns information about the CRC implementation and polynomial.
     *
     * @return  string
     */
    public function version();
}
