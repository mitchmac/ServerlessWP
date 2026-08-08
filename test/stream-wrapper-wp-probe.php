<?php

/**
 * Diagnostic: loads WordPress and reports whether the stream wrapper plugin's
 * hooks actually registered.
 *
 * The probe next door deliberately avoids WordPress, which is what makes it
 * useful for testing the prepend — but it cannot tell whether the mu-plugin
 * loaded. This one can.
 *
 * Never deploy this file to production. Guarded by SERVERLESSWP_TESTING.
 */

declare(strict_types=1);

if (getenv('SERVERLESSWP_TESTING') !== '1') {
    http_response_code(403);
    exit('Not allowed.');
}

require_once __DIR__ . '/wp-load.php';

$muPlugin = WP_CONTENT_DIR . '/mu-plugins/wp-alt-streamwrapper.php';
$payload  = WP_CONTENT_DIR . '/mu-plugins/wp-alt-streamwrapper/wp-alt-streamwrapper.php';

// Replays serveRemoteFile()'s path computation under a real WordPress load, so
// a failure to serve can be attributed to a specific condition rather than
// guessed at from the outside.
if (isset($_GET['path'])) {
    $requestPath    = urldecode((string) $_GET['path']);
    $contentUrlPath = rtrim((string) parse_url(content_url(), PHP_URL_PATH), '/');
    $matches        = $contentUrlPath !== '' && str_starts_with($requestPath, $contentUrlPath . '/');
    $absolutePath   = $matches ? WP_CONTENT_DIR . substr($requestPath, strlen($contentUrlPath)) : null;
    $wrapper        = 'WpAltStreamWrapper\\StreamWrapper';
    $registered     = class_exists($wrapper) && $wrapper::isRegistered();

    header('Content-Type: application/json');
    echo json_encode([
        'request_path'     => $requestPath,
        'content_url_path' => $contentUrlPath,
        'prefix_matches'   => $matches,
        'absolute_path'    => $absolutePath,
        'registered'       => $registered,
        'is_remote'        => $absolutePath !== null && $registered && $wrapper::isRemotePath($absolutePath),
        'file_exists'      => $absolutePath !== null && @file_exists($absolutePath),
        'read_bytes'       => $absolutePath !== null && ($c = @file_get_contents($absolutePath)) !== false
            ? strlen($c)
            : false,
        'stream_wrappers'  => stream_get_wrappers(),
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'wp_content_dir'      => WP_CONTENT_DIR,
    'content_url'         => content_url(),
    'content_url_path'    => parse_url(content_url(), PHP_URL_PATH),
    'loader_exists'       => file_exists($muPlugin),
    'payload_exists'      => file_exists($payload),
    'plugin_class_loaded' => class_exists('WpAltStreamWrapper\\WordPress\\Plugin'),
    'wrapper_registered'  => class_exists('WpAltStreamWrapper\\StreamWrapper')
        && WpAltStreamWrapper\StreamWrapper::isRegistered(),
    // has_action() with one argument is true when anything at all is hooked,
    // and core hooks template_redirect several times. Name the callbacks.
    'serve_hook'          => has_action('template_redirect'),
    'template_callbacks'  => array_values(array_map(
        static function (array $entry): string {
            $cb = $entry['function'];
            if (is_array($cb)) {
                return (is_object($cb[0]) ? get_class($cb[0]) : (string) $cb[0]) . '::' . $cb[1];
            }
            return is_string($cb) ? $cb : 'closure';
        },
        array_merge(...array_values(array_map(
            'array_values',
            $GLOBALS['wp_filter']['template_redirect']->callbacks ?? [],
        ))) ?: [],
    )),
    'upload_hook'         => has_filter('pre_move_uploaded_file'),
    'metadata_hook'       => has_filter('wp_generate_attachment_metadata'),
    'mu_plugins_loaded'   => wp_get_mu_plugins(),
    'permalink_structure' => get_option('permalink_structure'),
]);
