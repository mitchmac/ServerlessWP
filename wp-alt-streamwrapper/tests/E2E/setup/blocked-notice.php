<?php

require_once dirname(__DIR__, 6) . '/wp-load.php';

const NOTICE_KEY   = 'wp_alt_streamwrapper_blocked_asset';
const COOLDOWN_KEY = 'wp_alt_streamwrapper_report_cooldown';

$action = $_GET['action'] ?? 'state';

if ($action === 'reset') {
    delete_transient(NOTICE_KEY);
    delete_transient(COOLDOWN_KEY);
}

$notice = get_transient(NOTICE_KEY);

header('Content-Type: application/json');
echo json_encode([
    'notice_path'   => is_string($notice) && $notice !== '' ? $notice : null,
    'cooldown_held' => get_transient(COOLDOWN_KEY) !== false,
]);
