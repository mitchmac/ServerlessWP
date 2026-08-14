<?php

add_filter('wp_alt_streamwrapper_is_public_path', function ($public, $path) {
    return $public || str_contains($path, '/wp-content/e2e-opt-in/');
}, 10, 2);
