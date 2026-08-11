<?php
/**
 * E2E fixture: opt a directory outside WP_STREAM_PUBLIC_PATHS into being served.
 *
 * install.sh copies this into wp-content/mu-plugins/ so it loads on every
 * request. It is also the documented shape of the filter — a site that keeps a
 * public directory somewhere other than uploads or cache adds exactly this.
 */

add_filter('wp_alt_streamwrapper_is_public_path', function ($public, $path) {
    return $public || str_contains($path, '/wp-content/e2e-opt-in/');
}, 10, 2);
