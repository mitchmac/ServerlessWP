<?php
/**
 * E2E test helper: run a real wp_handle_sideload().
 *
 * A sideload (import, "add from URL", any plugin-fetched file) reaches
 * pre_move_uploaded_file with a tmp_name PHP never recorded in $_FILES, so
 * move_uploaded_file() cannot move it. Nothing else in the suite exercises that
 * path — REST uploads are all real HTTP uploads.
 *
 * Echoes the wp_handle_sideload() result as JSON, plus where the tmp file was.
 */

// dirname(__DIR__, 6): setup → E2E → tests → wp-alt-streamwrapper → mu-plugins → wp-content → html
require_once dirname(__DIR__, 6) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$fixture = dirname(__DIR__, 2) . '/Fixtures/test-image.jpg';
$name    = 'e2e-sideload-' . (int) ($_GET['tag'] ?? getmypid()) . '.jpg';

$tmp = wp_tempnam($name);
if (!copy($fixture, $tmp)) {
    http_response_code(500);
    echo json_encode(['error' => "could not stage a tmp copy of {$fixture}"]);
    exit;
}

// wp_handle_sideload() takes $file by reference, so it needs a variable.
$file = [
    'name'     => $name,
    'tmp_name' => $tmp,
    'type'     => 'image/jpeg',
    'error'    => 0,
    'size'     => filesize($tmp),
];

$result = wp_handle_sideload($file, ['test_form' => false]);

header('Content-Type: application/json');
echo json_encode([
    'result'         => $result,
    'tmp_path'       => $tmp,
    'tmp_left_behind' => file_exists($tmp),
]);
