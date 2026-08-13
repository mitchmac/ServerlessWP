<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

// Include the file containing the cfturnstile_settings_list() function
include(plugin_dir_path(__FILE__) . 'inc/admin/register-settings.php');

// Check if the "cfturnstile_uninstall_remove" option is true
if (get_option('cfturnstile_uninstall_remove')) {
    // Get registered settings
    $settings = cfturnstile_settings_list();
    // Remove all registered settings
    foreach ($settings as $setting) {
        delete_option($setting);
    }
    // Remove the "cfturnstile_tested" option
    delete_option('cfturnstile_tested');
    // Remove the "cfturnstile_invalid_secret_notice" option
    delete_option( 'cfturnstile_invalid_secret_notice' );
    // Remove the "cfturnstile_soft_tested" option
    delete_option( 'cfturnstile_soft_tested' );
    // Remove the throttle transient
    delete_transient( 'cfturnstile_invalid_secret_throttle' );
    // Remove the "cfturnstile_uninstall_remove" option itself
    delete_option('cfturnstile_uninstall_remove');
}