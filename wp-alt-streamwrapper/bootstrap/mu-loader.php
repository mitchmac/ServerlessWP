<?php

/**
 * Plugin Name: WP Alt Stream Wrapper (loader)
 * Description: Routes wp-content file operations to remote object storage. Active only when WP_STREAM_PROVIDER is set.
 * License:     MIT
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// WordPress only auto-loads .php files sitting directly in mu-plugins, so this
// file pulls in the plugin proper from the directory beside it. It is copied
// to wp-content/mu-plugins/wp-alt-streamwrapper.php by build-plugin.sh and
// mounted at the same place by the E2E compose file.
require_once __DIR__ . '/wp-alt-streamwrapper/wp-alt-streamwrapper.php';
