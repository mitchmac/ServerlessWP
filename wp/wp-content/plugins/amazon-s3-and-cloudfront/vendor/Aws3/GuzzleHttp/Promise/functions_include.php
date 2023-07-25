<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3;

// Don't redefine the functions if included multiple times.
if (!\function_exists('DeliciousBrains\\WP_Offload_Media\\Aws3\\GuzzleHttp\\Promise\\promise_for')) {
    require __DIR__ . '/functions.php';
}
