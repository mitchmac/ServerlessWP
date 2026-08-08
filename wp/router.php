<?php

/**
 * Router script for PHP's built-in server, used when the wp-content stream
 * wrapper is enabled. See api/index.js.
 *
 * Without it, `php -S` handles any URI with a file extension itself and
 * returns 404 for a missing file without running PHP at all — so a file that
 * lives only in object storage is never seen by WordPress, and the plugin's
 * template_redirect handler never gets a chance to serve it. Apache does this
 * job with .htaccess; this is the equivalent.
 */

declare(strict_types=1);

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__), '/');

$isTraversal = str_contains($path, '..') || str_contains($path, "\0");

// Anything that exists locally — a file, or a directory with its index — is
// the built-in server's job; returning false hands the request back to it,
// and auto_prepend_file applies to the script it then executes. `/` included:
// the server resolves the directory index itself.
if (!$isTraversal && ($path === '/' || is_file($root . $path) || is_dir($root . $path))) {
    return false;
}

// A local miss (or a traversal attempt): WordPress decides — it serves the
// file from object storage via the stream wrapper plugin's template_redirect
// handler, or renders its 404.
//
// php -S does NOT apply auto_prepend_file to the router script's own
// execution — only to scripts it executes directly after the router returns
// false. WordPress loaded from here would therefore run without the stream
// wrapper registered, and every remote file would silently 404, so the router
// has to load the prepend itself. require_once keeps this safe if PHP ever
// changes that behavior.
$prepend = (string) ini_get('auto_prepend_file');
if ($prepend !== '' && is_file($prepend)) {
    require_once $prepend;
}

// WordPress routes on REQUEST_URI, but leaving these pointed at the router
// would surface router.php in generated URLs.
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';

require $root . '/index.php';
