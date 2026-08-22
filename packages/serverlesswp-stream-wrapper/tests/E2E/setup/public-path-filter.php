<?php

add_filter('serverlesswp_stream_wrapper_is_public_path', function ($public, $path) {
    return $public || str_contains($path, '/wp-content/e2e-opt-in/');
}, 10, 2);
