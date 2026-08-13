<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resource hints (preconnect/dns-prefetch) for Cloudflare Turnstile
 * Controlled by Advanced option: cfturnstile_preconnect (disabled by default)
 */
add_filter( 'wp_resource_hints', 'cfturnstile_resource_hints', 10, 2 );
function cfturnstile_resource_hints( $urls, $relation_type ) {
    // Front-end only, only when enabled
    if ( is_admin() || ! get_option( 'cfturnstile_preconnect', 0 ) ) {
        return $urls;
    }

    $origin = 'https://challenges.cloudflare.com';

    if ( 'preconnect' === $relation_type ) {
        // Avoid duplicate hints
        foreach ( $urls as $hint ) {
            $href = is_array( $hint ) ? ( $hint['href'] ?? '' ) : $hint;
            if ( 0 === strpos( (string) $href, $origin ) ) {
                return $urls;
            }
        }
        $urls[] = array( 'href' => $origin, 'crossorigin' => true );
    }

    if ( 'dns-prefetch' === $relation_type ) {
        if ( ! in_array( $origin, $urls, true ) ) {
            $urls[] = $origin;
        }
    }

    return $urls;
}

/**
 * Login screen doesn't render; output the preconnect directly.
 */
add_action( 'login_head', 'cfturnstile_login_preconnect', 1 );
function cfturnstile_login_preconnect() {
    if ( ! get_option( 'cfturnstile_preconnect', 0 ) ) {
        return;
    }
    echo '<link rel="preconnect" href="' . esc_url( 'https://challenges.cloudflare.com' ) . '" crossorigin>' . "\n";
}
