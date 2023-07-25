<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp;

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
include __DIR__ . '/vendor/autoload.php';
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\Builtin;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\CRC32;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\Google;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\PHP;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\CRC32\PHPSlicedBy4;
\define('DeliciousBrains\\WP_Offload_Media\\Gcp\\min_duration', 5);
// Min duration of test in seconds.
\define('DeliciousBrains\\WP_Offload_Media\\Gcp\\max_duration', 30);
// Max duration of test in seconds.
\define('DeliciousBrains\\WP_Offload_Media\\Gcp\\min_iterations', 10000);
// Min number of iterations.
/*
Tested on my 2018 MacBook Pro (12 X 2900 MHz CPU s)

Google CRC Benchmarks
ext/crc32c/build$ ./crc32c_bench

CRC32CBenchmark/Public/256               126 ns          125 ns      5578000 bytes_per_second=1.90185G/s
CRC32CBenchmark/Public/4096             1469 ns         1463 ns       466275 bytes_per_second=2.60714G/s
CRC32CBenchmark/Public/65536           22504 ns        22492 ns        30160 bytes_per_second=2.71366G/s
CRC32CBenchmark/Public/1048576        360615 ns       360225 ns         1831 bytes_per_second=2.71098G/s
CRC32CBenchmark/Public/16777216      6020873 ns      6014408 ns          103 bytes_per_second=2.59793G/s
CRC32CBenchmark/Portable/256             240 ns          239 ns      2965172 bytes_per_second=1019.9M/s
CRC32CBenchmark/Portable/4096           2940 ns         2937 ns       225872 bytes_per_second=1.29904G/s
CRC32CBenchmark/Portable/65536         45557 ns        45519 ns        15170 bytes_per_second=1.34087G/s
CRC32CBenchmark/Portable/1048576      742592 ns       741764 ns          943 bytes_per_second=1.31654G/s
CRC32CBenchmark/Portable/16777216   11921962 ns     11895712 ns           59 bytes_per_second=1.3135G/s
CRC32CBenchmark/Sse42/256                123 ns          123 ns      5506348 bytes_per_second=1.93846G/s
CRC32CBenchmark/Sse42/4096              1412 ns         1411 ns       487560 bytes_per_second=2.70391G/s
CRC32CBenchmark/Sse42/65536            23721 ns        23688 ns        31011 bytes_per_second=2.57664G/s
CRC32CBenchmark/Sse42/1048576         366351 ns       366043 ns         1903 bytes_per_second=2.66789G/s
CRC32CBenchmark/Sse42/16777216       6071023 ns      6064640 ns          114 bytes_per_second=2.57641G/s

$ make benchmark
Google\CRC32\PHP           256    441318      22.60 MB/s
Google\CRC32\Builtin       256  33575674    1719.07 MB/s

# This is actually slower :( due to some regression that I've not debugged.
Google\CRC32\Google        256  20126340    1030.47 MB/s
Google\CRC32\PHP          4096     27775      22.75 MB/s
Google\CRC32\Builtin      4096  14879237   12189.07 MB/s
Google\CRC32\Google       4096   3137860    2570.53 MB/s
Google\CRC32\PHP       1048576       653      22.81 MB/s
Google\CRC32\Builtin   1048576    102606   21517.87 MB/s
Google\CRC32\Google    1048576     13566    2844.80 MB/s
Google\CRC32\PHP      16777216        41      22.87 MB/s
Google\CRC32\Builtin  16777216     10000   11265.23 MB/s
Google\CRC32\Google   16777216      4903    2741.83 MB/s
*/
function test($crc, $chunk_size)
{
    //xdebug_start_trace();
    $name = \get_class($crc);
    $chunk = \random_bytes($chunk_size);
    // TODO for php 5 use https://github.com/paragonie/random_compat
    $i = 0;
    $now = \microtime(\true);
    $start = $now;
    $duration = 0;
    while (\true) {
        $crc->update($chunk);
        $i++;
        $now = \microtime(\true);
        $duration = $now - $start;
        if ($duration >= \DeliciousBrains\WP_Offload_Media\Gcp\max_duration) {
            break;
        }
        if ($duration >= \DeliciousBrains\WP_Offload_Media\Gcp\min_duration && $i >= \DeliciousBrains\WP_Offload_Media\Gcp\min_iterations) {
            break;
        }
    }
    // Very quick sanity check
    if ($crc->hash() == '00000000') {
        exit($name . ' crc check failed');
    }
    $bytes = $i * $chunk_size;
    echo \sprintf("%s\t%10d\t%5d\t%8.2f MB/s\n", $name, $chunk_size, $i, $bytes / ($now - $start) / 1000000);
}
foreach (array(256, 4096, 1048576, 16777216) as $chunk_size) {
    test(new PHP(CRC32::CASTAGNOLI), $chunk_size);
    //test(new PHPSlicedBy4(CRC32::CASTAGNOLI), $chunk_size);
    test(new Builtin(CRC32::CASTAGNOLI), $chunk_size);
    test(new Google(), $chunk_size);
}
