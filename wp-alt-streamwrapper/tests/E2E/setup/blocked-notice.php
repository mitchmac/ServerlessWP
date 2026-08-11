<?php
/**
 * E2E test helper: read or reset the serving-policy reporting state.
 *
 * The report is rate-limited across requests and only fires for objects that
 * really exist, so a test has to be able to see the resulting transients and
 * clear them between cases.
 *
 * action=state  — JSON: the asset path in the admin-notice transient (or null)
 *                 and whether the cooldown is currently held.
 * action=reset  — delete both transients, so the next blocked request reports.
 */

// dirname(__DIR__, 6): setup → E2E → tests → wp-alt-streamwrapper → mu-plugins → wp-content → html
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
