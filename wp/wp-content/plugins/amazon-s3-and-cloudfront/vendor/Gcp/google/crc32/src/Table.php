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

final class Table
{
    private static $tables = [];
    /**
     * Echos the given table. Useful for building a static table to include in source code.
     *
     * @param      array  $table  The table
     */
    public static function output(array $table)
    {
        foreach ($table as $i => $value) {
            echo "0x" . int2hex($value) . ",";
            if ($i % 4 == 3) {
                echo "\n";
            } else {
                echo " ";
            }
        }
        echo "\n\n";
    }
    /**
     * Gets a CRC table, by creating it, or using a previously cached result.
     *
     * @param  integer  $polynomial  The polynomial
     *
     * @return  array  The table
     */
    public static function get($polynomial)
    {
        if (isset(self::$tables[$polynomial])) {
            return self::$tables[$polynomial];
        }
        self::$tables[$polynomial] = self::create($polynomial);
        return self::$tables[$polynomial];
    }
    /**
     * Create a CRC table.
     *
     * @param  integer  $polynomial  The polynomial.
     *
     * @return  array  The table.
     */
    public static function create($polynomial)
    {
        $table = \array_fill(0, 256, 0);
        for ($i = 0; $i < 256; $i++) {
            $crc = $i;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 1 == 1) {
                    $crc = $crc >> 1 ^ $polynomial;
                } else {
                    $crc >>= 1;
                }
            }
            $table[$i] = $crc;
        }
        return $table;
    }
    /**
     * Create a CRC table sliced by 4.
     *
     * @param  integer  $polynomial  The polynomial.
     *
     * @return  array  The table.
     */
    public static function create4($polynomial)
    {
        $table = \array_fill(0, 4, \array_fill(0, 256, 0));
        $table[0] = self::create($polynomial);
        for ($i = 0; $i < 256; $i++) {
            // for Slicing-by-4 and Slicing-by-8
            $table[1][$i] = $table[0][$i] >> 8 ^ $table[0][$table[0][$i] & 0xff];
            $table[2][$i] = $table[1][$i] >> 8 ^ $table[0][$table[1][$i] & 0xff];
            $table[3][$i] = $table[2][$i] >> 8 ^ $table[0][$table[2][$i] & 0xff];
            /*
            // only Slicing-by-8
            $table[4][$i] = ($table[3][$i] >> 8) ^ $table[0][$table[3][$i] & 0xFF];
            $table[5][$i] = ($table[4][$i] >> 8) ^ $table[0][$table[4][$i] & 0xFF];
            $table[6][$i] = ($table[5][$i] >> 8) ^ $table[0][$table[5][$i] & 0xFF];
            $table[7][$i] = ($table[6][$i] >> 8) ^ $table[0][$table[6][$i] & 0xFF];
            */
        }
        return $table;
    }
}
