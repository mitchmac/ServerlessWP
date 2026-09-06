<?php
/**
 * Plugin Name: ServerlessWP Stream Wrapper
 * Description: Loads the framework implementation from the serverlesswp npm package.
 */
if (!defined('ABSPATH')) exit;

// Set by the Node handler. Other MU plugins in this directory load normally.
$serverlesswpAssets = getenv('SERVERLESSWP_ASSETS_DIR');
if (!$serverlesswpAssets) {
    throw new RuntimeException('Run WordPress through serverlesswp/wordpress to load its framework PHP.');
}
require_once $serverlesswpAssets . '/serverlesswp-stream-wrapper/serverlesswp-stream-wrapper.php';
unset($serverlesswpAssets);
