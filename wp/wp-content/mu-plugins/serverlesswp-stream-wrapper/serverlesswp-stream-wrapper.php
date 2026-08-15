<?php

/**
 * Plugin Name:  ServerlessWP Stream Wrapper
 * Description:  Routes wp-content files to object storage.
 * Version:      0.1.0
 * Requires PHP: 8.2
 * License:      MIT
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Skip the SDK when storage is disabled.
$provider = defined('WP_STREAM_PROVIDER') ? (string) constant('WP_STREAM_PROVIDER') : (string) getenv('WP_STREAM_PROVIDER');
if ($provider === '') {
    return;
}

use ServerlessWpStreamWrapper\WordPress\Plugin;

// The prepend may have loaded the bundled copy already.
if (!class_exists(Plugin::class)) {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>ServerlessWP Stream Wrapper:</strong> Composer dependencies not installed. Run <code>composer install</code> in the plugin directory.</p></div>';
        });
        return;
    }
    require_once $autoload;
}

(new Plugin())->register();
