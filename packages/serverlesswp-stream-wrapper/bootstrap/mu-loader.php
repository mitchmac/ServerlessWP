<?php

/**
 * Plugin Name: ServerlessWP Stream Wrapper
 * Description: Routes wp-content files to object storage.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// WordPress only auto-loads top-level MU-plugin files.
require_once __DIR__ . '/serverlesswp-stream-wrapper/serverlesswp-stream-wrapper.php';
