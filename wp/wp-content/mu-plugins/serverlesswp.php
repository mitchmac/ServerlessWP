<?php

/*
Plugin Name: ServerlessWP
Plugin URI: https://github.com/mitchmac/serverlesswp
Description: Plugin that makes sure your site runs smoothly on Vercel or Netlify.
Author: Mitch MacKenzie
Version: 1.0.1
*/

// WordPress doesn't know that serverlesswp-node emulates typical mod_rewrite behaviour.
add_filter( 'got_url_rewrite', '__return_true' );


// A notice to let people know they can't directly install themes.
function serverlesswp_theme_install_notice() {
    global $pagenow;
    $pages = ['themes.php'];
    if (is_admin() && in_array($pagenow, $pages)) {
        ?>
            <div class="notice notice-info">
                <h2><?php _e( 'Want to add a theme?', 'serverlesswp' ); ?></h2>
                <p><?php _e( 'Direct theme installation is not currently possible with serverless functions. You can still add themes to your site though!', 'serverlesswp' ); ?></p>
                <p><?php _e( '<a href="https://github.com/mitchmac/ServerlessWP/discussions/35">Learn how to add themes to your site\'s git repository.</a>', 'serverlesswp' ); ?></p>
            </div>
        <?php
    }
}

// Register the notice action.
add_action('admin_notices', 'serverlesswp_theme_install_notice');

// A notice to let people know they can't directly install themes.
function serverlesswp_plugin_install_notice() {
    global $pagenow;
    $pages = ['plugin-install.php', 'plugins.php'];
    if (is_admin() && in_array($pagenow, $pages)) {
        ?>
            <div class="notice notice-info">
                <h2><?php _e( 'Want to add a plugin?', 'serverlesswp' ); ?></h2>
                <p><?php _e( 'Direct plugin installation is not currently possible with serverless functions. You can still add plugins to your site though!', 'serverlesswp' ); ?></p>
                <p><?php _e( '<a href="https://github.com/mitchmac/ServerlessWP/discussions/35">Learn how to add plugins to your site\'s git repository.</a>', 'serverlesswp' ); ?></p>
            </div>
        <?php
    }
}

// Register the notice action.
add_action('admin_notices', 'serverlesswp_plugin_install_notice');
