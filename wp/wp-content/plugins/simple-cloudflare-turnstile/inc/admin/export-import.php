<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Export settings as JSON download
 */
function cfturnstile_handle_export_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to export settings.', 'simple-cloudflare-turnstile'));
    }
    check_admin_referer('cfturnstile_export_settings');

    // Collect active settings (as shown on the settings page)
    if (!function_exists('cfturnstile_settings_list')) {
        require_once plugin_dir_path(__FILE__) . 'register-settings.php';
    }
    $option_names = cfturnstile_settings_list();

    // Whether to include API keys in export (sensitive)
    $include_keys = isset($_POST['include_keys']) ? (int) wp_unslash($_POST['include_keys']) : 0;

    $payload = array(
        'plugin'   => 'simple-cloudflare-turnstile',
        'exported' => current_time('mysql'),
        'settings' => array(),
    );

    foreach ($option_names as $name) {
        // Skip potentially large/PII-heavy runtime logs
        if ($name === 'cfturnstile_log' || $name === 'cfturnstile_analytics') {
            continue;
        }
        // Optionally exclude API keys by default
        if (!$include_keys && ($name === 'cfturnstile_key' || $name === 'cfturnstile_secret')) {
            continue;
        }
        // Use get_option which already respects overrides/filters
        $payload['settings'][$name] = get_option($name);
    }

    $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // Send as file download
    nocache_headers();
    header('Content-Description: File Transfer');
    header('Content-Type: application/json; charset=' . get_option('blog_charset'));
    $filename = 'simple-cloudflare-turnstile-settings-' . date('Ymd-His') . '.json';
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}
add_action('admin_post_cfturnstile_export_settings', 'cfturnstile_handle_export_settings');

/**
 * Import settings from uploaded JSON
 */
function cfturnstile_handle_import_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to import settings.', 'simple-cloudflare-turnstile'));
    }
    check_admin_referer('cfturnstile_import_settings');

    $redirect = admin_url('options-general.php?page=cfturnstile&tab=export');

    if (!isset($_FILES['cfturnstile_import_file']) || !is_array($_FILES['cfturnstile_import_file'])) {
        wp_safe_redirect(add_query_arg(array('cfturnstile_import' => 'error', 'reason' => 'nofile'), $redirect));
        exit;
    }

    $file = $_FILES['cfturnstile_import_file'];
    if (!empty($file['error'])) {
        wp_safe_redirect(add_query_arg(array('cfturnstile_import' => 'error', 'reason' => 'upload'), $redirect));
        exit;
    }

    // Basic size validation
    $max_size = 2 * 1024 * 1024; // 2 MB
    if (!empty($file['size']) && intval($file['size']) > $max_size) {
        wp_safe_redirect(add_query_arg(array('cfturnstile_import' => 'error', 'reason' => 'size'), $redirect));
        exit;
    }

    // Basic type validation
    $contents = file_get_contents($file['tmp_name']);
    if ($contents === false || $contents === '') {
        wp_safe_redirect(add_query_arg(array('cfturnstile_import' => 'error', 'reason' => 'empty'), $redirect));
        exit;
    }

    $data = json_decode($contents, true);
    if (!is_array($data)) {
        wp_safe_redirect(add_query_arg(array('cfturnstile_import' => 'error', 'reason' => 'json'), $redirect));
        exit;
    }

    // Require our plugin marker when present
    if (isset($data['plugin']) && $data['plugin'] !== 'simple-cloudflare-turnstile') {
        wp_safe_redirect(add_query_arg(array('cfturnstile_import' => 'error', 'reason' => 'plugin'), $redirect));
        exit;
    }
    // The payload we generate stores settings under 'settings', but also accept a flat array for flexibility
    $settings = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : $data;

    if (!function_exists('cfturnstile_settings_list')) {
        require_once plugin_dir_path(__FILE__) . 'register-settings.php';
    }
    // Allowed keys: all known settings (including integration ones)
    $allowed = cfturnstile_settings_list(true);
    $allowed = array_fill_keys($allowed, true);

    $updated = 0;
    foreach ($settings as $key => $value) {
        if (!isset($allowed[$key])) {
            continue; // skip unknown options
        }
        // Skip runtime logs import
        if ($key === 'cfturnstile_log' || $key === 'cfturnstile_analytics') {
            continue;
        }
        // Do not override wp-config constants
        if (($key === 'cfturnstile_key' && defined('CF_TURNSTILE_SITE_KEY') && CF_TURNSTILE_SITE_KEY)
            || ($key === 'cfturnstile_secret' && defined('CF_TURNSTILE_SECRET_KEY') && CF_TURNSTILE_SECRET_KEY)) {
            continue;
        }
        // Basic normalization (keep arrays as-is, WP will serialize)
        update_option($key, $value);
        $updated++;
    }

    wp_safe_redirect(add_query_arg(array('cfturnstile_import' => 'success', 'count' => $updated), $redirect));
    exit;
}
add_action('admin_post_cfturnstile_import_settings', 'cfturnstile_handle_import_settings');

/**
 * Admin notices for import results
 */
function cfturnstile_import_admin_notices() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'cfturnstile') {
        return;
    }
    if (!isset($_GET['cfturnstile_import'])) {
        return;
    }
    if ($_GET['cfturnstile_import'] === 'success') {
        $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('Settings imported successfully. %d option(s) updated.', 'simple-cloudflare-turnstile'), $count)) . '</p></div>';
    } else {
        $reason = isset($_GET['reason']) ? sanitize_text_field(wp_unslash($_GET['reason'])) : 'unknown';
        $msg = __('Import failed.', 'simple-cloudflare-turnstile');
        switch ($reason) {
            case 'nofile':
                $msg = __('Import failed: No file uploaded.', 'simple-cloudflare-turnstile');
                break;
            case 'upload':
                $msg = __('Import failed: Upload error.', 'simple-cloudflare-turnstile');
                break;
            case 'empty':
                $msg = __('Import failed: File was empty.', 'simple-cloudflare-turnstile');
                break;
            case 'json':
                $msg = __('Import failed: Invalid JSON file.', 'simple-cloudflare-turnstile');
                break;
            case 'type':
                $msg = __('Import failed: File type must be .json.', 'simple-cloudflare-turnstile');
                break;
            case 'size':
                $msg = __('Import failed: File too large.', 'simple-cloudflare-turnstile');
                break;
            case 'plugin':
                $msg = __('Import failed: File is not a Simple Cloudflare Turnstile export.', 'simple-cloudflare-turnstile');
                break;
        }
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($msg) . '</p></div>';
    }
}
add_action('admin_notices', 'cfturnstile_import_admin_notices');
