<?php

/**
 * Plugin Name:  WP Alt Stream Wrapper
 * Description:  Routes wp-content file operations to remote object storage (S3, Vercel Blob). Configure via WP_STREAM_* environment variables. Register bootstrap/prepend.php via auto_prepend_file for early interception.
 * Version:      0.1.0
 * Requires PHP: 8.2
 * License:      MIT
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// The stream wrapper itself is registered by bootstrap/prepend.php (auto_prepend_file).
// This file handles WordPress-level integration: URL rewriting and the WP filter hook.

// Without a provider there is nothing to hook. Checked before the autoloader
// so sites that never enable this don't pay to load the AWS SDK per request.
$provider = defined('WP_STREAM_PROVIDER') ? (string) constant('WP_STREAM_PROVIDER') : (string) getenv('WP_STREAM_PROVIDER');
if ($provider === '') {
    return;
}

use WpAltStreamWrapper\WordPress\Plugin;

// bootstrap/prepend.php may already have loaded these classes from a different
// copy of the tree — on serverless it runs from the read-only bundle while
// WordPress runs from /tmp. require_once can't tell the two apart because the
// paths differ, and Composer names its autoloader class after the project, so
// loading the second one is a fatal "cannot declare class" error.
if (!class_exists(Plugin::class)) {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WP Alt Stream Wrapper:</strong> Composer dependencies not installed. Run <code>composer install</code> in the plugin directory.</p></div>';
        });
        return;
    }
    require_once $autoload;
}

(new Plugin())->register();
