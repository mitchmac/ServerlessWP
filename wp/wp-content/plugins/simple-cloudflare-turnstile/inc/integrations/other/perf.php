<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SG Optimizer Compatibility
*/
add_filter( 'sgo_javascript_combine_exclude', 'cfturnstile_sg_js_combine_exclude' );
add_filter( 'sgo_javascript_combine_excluded_external_paths', 'cfturnstile_sg_js_combine_exclude' );
function cfturnstile_sg_js_combine_exclude( $exclude_list ) {
    $exclude_list[] = 'turnstile';
    $exclude_list[] = 'cfturnstile';
    return $exclude_list;
}

/**
 * LiteSpeed Cache Compatibility
*/
add_filter( 'litespeed_optimize_js_excludes', 'cfturnstile_ls_js_combine_exclude' );
function cfturnstile_ls_js_combine_exclude( $exclude_list ) {
    $exclude_list[] = 'turnstile';
    $exclude_list[] = 'cfturnstile';
    return $exclude_list;
}

/**
 * Autoptimize Compatibility
 */
add_filter( 'autoptimize_filter_js_exclude', 'cfturnstile_autoptimize_js_exclude' );
function cfturnstile_autoptimize_js_exclude( $exclude ) {
    $patterns = array(
        'challenges.cloudflare.com/turnstile',
        'turnstile',
        'cfturnstile',
        'wpdiscuzEditorOptions',
        'wpdiscuz-combo',
        'simple-cloudflare-turnstile/js/integrations/elementor-forms.js',
        'simple-cloudflare-turnstile/js/disable-submit.js',
    );
    foreach ( $patterns as $p ) {
        if ( false === strpos( $exclude, $p ) ) {
            $exclude .= ( $exclude ? ',' : '' ) . $p;
        }
    }
    return $exclude;
}

/**
 * Perfmatters Compatibility (Delay JS)
 */
add_filter( 'perfmatters_delay_js_exclusions', 'cfturnstile_perfmatters_delay_exclude' );
function cfturnstile_perfmatters_delay_exclude( $list ) {
    $list[] = 'challenges.cloudflare.com/turnstile';
    $list[] = 'turnstile';
    $list[] = 'cfturnstile';
    $list[] = 'wpdiscuzEditorOptions';
    $list[] = 'wpdiscuz-combo';
    return $list;
}

/**
 * WP Rocket Compatibility
 * - Prevent minify/combine/defer/delay from breaking Turnstile
 */
add_filter( 'rocket_minify_excluded_external_js', 'cfturnstile_wprocket_exclude_external' );
add_filter( 'rocket_minify_excluded_js', 'cfturnstile_wprocket_exclude' );
add_filter( 'rocket_delay_js_exclusions', 'cfturnstile_wprocket_exclude' );
add_filter( 'rocket_exclude_js', 'cfturnstile_wprocket_exclude' );
add_filter( 'rocket_defer_js_exclusions', 'cfturnstile_wprocket_exclude' );
function cfturnstile_wprocket_exclude_external( $list ) {
    $list[] = 'challenges.cloudflare.com/turnstile';
    return $list;
}
function cfturnstile_wprocket_exclude( $list ) {
    $list[] = 'turnstile';
    $list[] = 'cfturnstile'; 
    $list[] = 'wpdiscuzEditorOptions';
    $list[] = 'wpdiscuz-combo';
    $list[] = 'simple-cloudflare-turnstile/js/integrations/elementor-forms.js';
    $list[] = 'simple-cloudflare-turnstile/js/disable-submit.js';
    return $list;
}