<?php
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_cfturnstile_reset_analytics', 'cfturnstile_handle_reset_analytics');
function cfturnstile_handle_reset_analytics() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have permission to reset analytics.', 'simple-cloudflare-turnstile'));
	}
	if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) {
		wp_die(__('Invalid analytics reset request.', 'simple-cloudflare-turnstile'));
	}

	check_admin_referer('cfturnstile_reset_analytics');
	delete_option('cfturnstile_analytics');

	wp_safe_redirect(add_query_arg('cfturnstile_analytics_reset', 'success', admin_url('options-general.php?page=cfturnstile&tab=analytics')));
	exit;
}

add_action('admin_post_cfturnstile_reset_log', 'cfturnstile_handle_reset_log');
function cfturnstile_handle_reset_log() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have permission to reset the debug log.', 'simple-cloudflare-turnstile'));
	}
	if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) {
		wp_die(__('Invalid debug log reset request.', 'simple-cloudflare-turnstile'));
	}

	check_admin_referer('cfturnstile_reset_log');
	delete_option('cfturnstile_log');

	wp_safe_redirect(add_query_arg('cfturnstile_log_reset', 'success', admin_url('options-general.php?page=cfturnstile&tab=analytics')));
	exit;
}

add_action('admin_notices', 'cfturnstile_analytics_reset_admin_notice');
function cfturnstile_analytics_reset_admin_notice() {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!isset($_GET['page']) || 'cfturnstile' !== sanitize_key(wp_unslash($_GET['page']))) {
		return;
	}
	if (!isset($_GET['tab']) || 'analytics' !== sanitize_key(wp_unslash($_GET['tab']))) {
		return;
	}
	if (isset($_GET['cfturnstile_analytics_reset']) && 'success' === sanitize_key(wp_unslash($_GET['cfturnstile_analytics_reset']))) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Turnstile analytics data reset successfully.', 'simple-cloudflare-turnstile') . '</p></div>';
	}
	if (isset($_GET['cfturnstile_log_reset']) && 'success' === sanitize_key(wp_unslash($_GET['cfturnstile_log_reset']))) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Debug log reset successfully.', 'simple-cloudflare-turnstile') . '</p></div>';
	}
}

function cfturnstile_get_analytics_data() {
	$analytics = get_option('cfturnstile_analytics');
	if (!is_array($analytics)) {
		$analytics = array();
	}

	$analytics = wp_parse_args($analytics, array(
		'started' => '',
		'updated' => '',
		'total' => 0,
		'verified' => 0,
		'blocked' => 0,
		'retries' => 0,
		'forms' => array(),
		'errors' => array(),
	));

	$analytics['total'] = absint($analytics['total']);
	$analytics['verified'] = absint($analytics['verified']);
	$analytics['blocked'] = absint($analytics['blocked']);
	$analytics['retries'] = absint($analytics['retries']);
	$analytics['started'] = cfturnstile_sanitize_analytics_scalar($analytics['started']);
	$analytics['updated'] = cfturnstile_sanitize_analytics_scalar($analytics['updated']);
	$analytics['forms'] = is_array($analytics['forms']) ? $analytics['forms'] : array();
	$analytics['errors'] = is_array($analytics['errors']) ? $analytics['errors'] : array();

	foreach ($analytics['forms'] as $form_key => $form) {
		if (!is_array($form)) {
			unset($analytics['forms'][$form_key]);
			continue;
		}

		$analytics['forms'][$form_key] = array(
			'label' => isset($form['label']) ? cfturnstile_sanitize_analytics_scalar($form['label']) : __('Unknown form', 'simple-cloudflare-turnstile'),
			'total' => isset($form['total']) ? absint($form['total']) : 0,
			'verified' => isset($form['verified']) ? absint($form['verified']) : 0,
			'blocked' => isset($form['blocked']) ? absint($form['blocked']) : 0,
			'retries' => isset($form['retries']) ? absint($form['retries']) : 0,
			'last_checked' => isset($form['last_checked']) ? cfturnstile_sanitize_analytics_scalar($form['last_checked']) : '',
		);
	}

	foreach ($analytics['errors'] as $error_code => $count) {
		$normalized_code = sanitize_key($error_code);
		if ('' === $normalized_code) {
			unset($analytics['errors'][$error_code]);
			continue;
		}
		if ($normalized_code !== $error_code) {
			unset($analytics['errors'][$error_code]);
		}
		$analytics['errors'][$normalized_code] = absint($count);
	}
	if (count($analytics['forms']) > 40) {
		uasort($analytics['forms'], 'cfturnstile_sort_admin_analytics_forms');
		$analytics['forms'] = array_slice($analytics['forms'], 0, 40, true);
	}
	if (count($analytics['errors']) > 20) {
		arsort($analytics['errors']);
		$analytics['errors'] = array_slice($analytics['errors'], 0, 20, true);
	}

	return $analytics;
}

function cfturnstile_sanitize_analytics_scalar($value) {
	if (!is_scalar($value)) {
		return '';
	}

	return sanitize_text_field((string) $value);
}

function cfturnstile_format_analytics_datetime($value) {
	$value = cfturnstile_sanitize_analytics_scalar($value);
	$timestamp = $value ? strtotime($value) : false;
	if (!$timestamp) {
		return __('Not yet', 'simple-cloudflare-turnstile');
	}

	return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
}

function cfturnstile_format_debug_log_error($value) {
	if (is_array($value)) {
		$values = array();
		foreach ($value as $item) {
			$item = cfturnstile_sanitize_analytics_scalar($item);
			if ('' !== $item) {
				$values[] = $item;
			}
		}

		return implode(', ', $values);
	}

	return cfturnstile_sanitize_analytics_scalar($value);
}

function cfturnstile_analytics_percent($part, $total) {
	$part = absint($part);
	$total = absint($total);
	if (!$total) {
		return '0%';
	}

	return number_format_i18n(($part / $total) * 100, 1) . '%';
}

function cfturnstile_analytics_percent_value($part, $total) {
	$part = absint($part);
	$total = absint($total);
	if (!$total) {
		return 0;
	}

	return min(100, max(0, ($part / $total) * 100));
}

function cfturnstile_sort_admin_analytics_forms($a, $b) {
	$a_total = isset($a['total']) ? absint($a['total']) : 0;
	$b_total = isset($b['total']) ? absint($b['total']) : 0;

	if ($a_total === $b_total) {
		return 0;
	}

	return ($a_total < $b_total) ? 1 : -1;
}

function cfturnstile_render_analytics_card($label, $value, $detail = '') {
	echo '<div class="sct-analytics-card">';
	echo '<span>' . esc_html($label) . '</span>';
	echo '<strong>' . esc_html($value) . '</strong>';
	if ($detail) {
		echo '<small>' . esc_html($detail) . '</small>';
	}
	echo '</div>';
}

function cfturnstile_render_analytics_tab() {
	$log_enabled = cfturnstile_is_checkbox_enabled(get_option('cfturnstile_log_enable'));
	$advanced_enabled = cfturnstile_is_checkbox_enabled(get_option('cfturnstile_advanced_analytics'));
	?>
	<div class="sct-tab-content sct-analytics-tab">
		<form method="post" action="options.php" class="cfturnstile-settings sct-analytics-settings">
			<?php settings_fields('cfturnstile-analytics-settings-group'); ?>
			<div class="sct-admin-card sct-analytics-options">
				<h2><?php echo esc_html__('Turnstile Analytics Settings', 'simple-cloudflare-turnstile'); ?></h2>
				<label class="sct-checkbox-row">
					<input type="hidden" name="cfturnstile_advanced_analytics" value="0">
					<input type="checkbox" name="cfturnstile_advanced_analytics" value="1" <?php checked($advanced_enabled); ?>>
					<span><?php echo esc_html__('Enable Turnstile Analytics', 'simple-cloudflare-turnstile'); ?></span>
				</label>
				<p class="description">
					<?php echo esc_html__('Analytics stores small counters for verification results and form actions. It does not store IP addresses or page URLs.', 'simple-cloudflare-turnstile'); ?>
				</p>
				<label class="sct-checkbox-row">
					<input type="hidden" name="cfturnstile_log_enable" value="0">
					<input type="checkbox" name="cfturnstile_log_enable" value="1" <?php checked($log_enabled); ?>>
					<span><?php echo esc_html__('Enable Turnstile Debug Logging', 'simple-cloudflare-turnstile'); ?></span>
				</label>
                <p class="description">
                    <?php echo esc_html__('Debug logging stores the date, success/failure, error messages, IP address, and page URL for each Turnstile verification request. This is useful for troubleshooting but may contain sensitive information.', 'simple-cloudflare-turnstile'); ?>
                </p>
				<?php submit_button(esc_html__('Save Analytics Settings', 'simple-cloudflare-turnstile'), 'primary', 'submit', false); ?>
			</div>
		</form>

		<?php if ($advanced_enabled) { ?>
			<?php cfturnstile_render_advanced_analytics_section(); ?>
		<?php } ?>

		<?php cfturnstile_render_debug_log_section(); ?>
	</div>
	<?php
}

function cfturnstile_get_reset_analytics_button_attributes() {
	$analytics = get_option('cfturnstile_analytics');
	$has_analytics = is_array($analytics) && (
		!empty($analytics['total']) ||
		!empty($analytics['verified']) ||
		!empty($analytics['blocked']) ||
		!empty($analytics['retries']) ||
		!empty($analytics['forms']) ||
		!empty($analytics['errors'])
	);

	return $has_analytics
		? array('onclick' => "return confirm('" . esc_js(__('Reset all Turnstile analytics data?', 'simple-cloudflare-turnstile')) . "');")
		: array('disabled' => 'disabled');
}

function cfturnstile_render_reset_analytics_button() {
	?>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sct-reset-analytics-form">
		<input type="hidden" name="action" value="cfturnstile_reset_analytics" />
		<?php wp_nonce_field('cfturnstile_reset_analytics'); ?>
		<?php submit_button(esc_html__('Reset Analytics', 'simple-cloudflare-turnstile'), 'secondary', 'submit', false, cfturnstile_get_reset_analytics_button_attributes()); ?>
	</form>
	<?php
}

function cfturnstile_render_advanced_analytics_section() {
	$analytics = cfturnstile_get_analytics_data();
	$total = absint($analytics['total']);
	$verified = absint($analytics['verified']);
	$blocked = absint($analytics['blocked']);
	$retries = absint($analytics['retries']);
	$forms = is_array($analytics['forms']) ? $analytics['forms'] : array();
	$errors = is_array($analytics['errors']) ? $analytics['errors'] : array();
	$updated = cfturnstile_format_analytics_datetime($analytics['updated']);
	?>
	<div class="sct-admin-card sct-advanced-analytics">
		<div class="sct-analytics-heading">
			<div>
				<h2><?php echo esc_html__('Turnstile Analytics', 'simple-cloudflare-turnstile'); ?></h2>
				<p><?php echo esc_html(sprintf(__('Last updated: %s', 'simple-cloudflare-turnstile'), $updated)); ?></p>
			</div>
			<?php cfturnstile_render_reset_analytics_button(); ?>
		</div>

		<div class="sct-analytics-cards">
			<?php
			cfturnstile_render_analytics_card(__('Total Checks', 'simple-cloudflare-turnstile'), number_format_i18n($total), __('All verification requests', 'simple-cloudflare-turnstile'));
			cfturnstile_render_analytics_card(__('Verified', 'simple-cloudflare-turnstile'), number_format_i18n($verified), cfturnstile_analytics_percent($verified, $total));
			cfturnstile_render_analytics_card(__('Blocked', 'simple-cloudflare-turnstile'), number_format_i18n($blocked), cfturnstile_analytics_percent($blocked, $total));
			cfturnstile_render_analytics_card(__('Retries', 'simple-cloudflare-turnstile'), number_format_i18n($retries), cfturnstile_analytics_percent($retries, $total));
			?>
		</div>

		<div class="sct-rate-row">
			<span><?php echo esc_html__('Verification Rate', 'simple-cloudflare-turnstile'); ?></span>
			<strong><?php echo esc_html(cfturnstile_analytics_percent($verified, $total)); ?></strong>
			<div class="sct-rate-bar"><span style="width: <?php echo esc_attr(cfturnstile_analytics_percent_value($verified, $total)); ?>%;"></span></div>
		</div>

		<h3><?php echo esc_html__('Form Analytics', 'simple-cloudflare-turnstile'); ?></h3>
		<?php if (!empty($forms)) { ?>
			<div class="sct-table-wrap">
				<table class="widefat striped sct-analytics-table">
					<thead>
						<tr>
							<th><?php echo esc_html__('Form', 'simple-cloudflare-turnstile'); ?></th>
							<th><?php echo esc_html__('Checks', 'simple-cloudflare-turnstile'); ?></th>
							<th><?php echo esc_html__('Verified', 'simple-cloudflare-turnstile'); ?></th>
							<th><?php echo esc_html__('Blocked', 'simple-cloudflare-turnstile'); ?></th>
							<th><?php echo esc_html__('Retries', 'simple-cloudflare-turnstile'); ?></th>
							<th><?php echo esc_html__('Success Rate', 'simple-cloudflare-turnstile'); ?></th>
							<th><?php echo esc_html__('Last Check', 'simple-cloudflare-turnstile'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						uasort($forms, 'cfturnstile_sort_admin_analytics_forms');
						foreach ($forms as $form) {
							$form_total = absint($form['total']);
							$form_verified = absint($form['verified']);
							$form_blocked = absint($form['blocked']);
							$form_retries = absint($form['retries']);
							$form_label = !empty($form['label']) ? $form['label'] : __('Unknown form', 'simple-cloudflare-turnstile');
							$last_checked = cfturnstile_format_analytics_datetime($form['last_checked']);
							?>
							<tr>
								<td><strong><?php echo esc_html($form_label); ?></strong></td>
								<td><?php echo esc_html(number_format_i18n($form_total)); ?></td>
								<td><?php echo esc_html(number_format_i18n($form_verified)); ?></td>
								<td><?php echo esc_html(number_format_i18n($form_blocked)); ?></td>
								<td><?php echo esc_html(number_format_i18n($form_retries)); ?></td>
								<td><?php echo esc_html(cfturnstile_analytics_percent($form_verified, $form_total)); ?></td>
								<td><?php echo esc_html($last_checked); ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</div>
		<?php } else { ?>
			<p class="sct-muted"><?php echo esc_html__('No Turnstile analytics have been collected yet.', 'simple-cloudflare-turnstile'); ?></p>
		<?php } ?>

		<h3><?php echo esc_html__('Blocked Reasons', 'simple-cloudflare-turnstile'); ?></h3>
		<?php if (!empty($errors)) { ?>
			<div class="sct-error-chips">
				<?php foreach ($errors as $error_code => $count) { ?>
					<span><strong><?php echo esc_html($error_code); ?></strong><?php echo esc_html(number_format_i18n(absint($count))); ?></span>
				<?php } ?>
			</div>
		<?php } else { ?>
			<p class="sct-muted"><?php echo esc_html__('No blocked verification reasons have been recorded yet.', 'simple-cloudflare-turnstile'); ?></p>
		<?php } ?>
	</div>
	<?php
}

function cfturnstile_render_debug_log_section() {
	if (!cfturnstile_is_checkbox_enabled(get_option('cfturnstile_log_enable'))) {
		if (get_option('cfturnstile_log')) {
			delete_option('cfturnstile_log');
		}
		return;
	}
	$cfturnstile_log = get_option('cfturnstile_log');
	$has_log = is_array($cfturnstile_log) && !empty($cfturnstile_log);
	?>
	<div class="sct-admin-card sct-debug-log">
		<div class="sct-debug-log-heading">
			<h2><?php echo esc_html__('Turnstile Debug Log', 'simple-cloudflare-turnstile'); ?></h2>
			<?php cfturnstile_render_debug_log_actions($has_log); ?>
		</div>
		<?php
		if ($has_log) {
			$cfturnstile_log = array_slice($cfturnstile_log, -50);
			$cfturnstile_log_reversed = array_reverse($cfturnstile_log);
			$cfturnstile_csv_escape = function($value) {
				$value = (string) $value;
				$value = str_replace(array("\r\n", "\r"), "\n", $value);
				// Neutralize spreadsheet formula injection by prefixing values
				// that begin with a formula trigger character.
				if ($value !== '' && in_array($value[0], array('=', '+', '-', '@', "\t"), true)) {
					$value = "'" . $value;
				}
				$value = str_replace('"', '""', $value);
				return '"' . $value . '"';
			};
			$cfturnstile_log_text =
				$cfturnstile_csv_escape(__('Date', 'simple-cloudflare-turnstile')) . ',' .
				$cfturnstile_csv_escape(__('Success', 'simple-cloudflare-turnstile')) . ',' .
				$cfturnstile_csv_escape(__('Response', 'simple-cloudflare-turnstile')) . ',' .
				$cfturnstile_csv_escape(__('IP', 'simple-cloudflare-turnstile')) . ',' .
				$cfturnstile_csv_escape(__('URL', 'simple-cloudflare-turnstile')) . "\n";
			foreach ($cfturnstile_log_reversed as $log_item) {
				if (!is_array($log_item)) {
					continue;
				}
				$log_date = isset($log_item['date']) ? cfturnstile_format_analytics_datetime($log_item['date']) : '';
				$log_success = !empty($log_item['success']) ? 'Yes' : 'No';
				$error_val = isset($log_item['error']) ? $log_item['error'] : '';
				$log_response = empty($log_item['success']) ? cfturnstile_format_debug_log_error($error_val) : __('Success', 'simple-cloudflare-turnstile');
				$log_ip = isset($log_item['ip']) ? cfturnstile_sanitize_analytics_scalar($log_item['ip']) : '';
				$log_page = isset($log_item['page']) ? cfturnstile_sanitize_analytics_scalar($log_item['page']) : '';
				$cfturnstile_log_text .=
					$cfturnstile_csv_escape($log_date) . ',' .
					$cfturnstile_csv_escape($log_success) . ',' .
					$cfturnstile_csv_escape($log_response) . ',' .
					$cfturnstile_csv_escape($log_ip) . ',' .
					$cfturnstile_csv_escape($log_page) . "\n";
			}

			echo '<textarea id="cfturnstile-debug-log-text" readonly style="position:absolute; left:-9999px; top:-9999px;">' . esc_textarea($cfturnstile_log_text) . '</textarea>';

			echo '<div class="sct-table-wrap sct-debug-log-table">';
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('Date', 'simple-cloudflare-turnstile') . '</th>';
			echo '<th>' . esc_html__('Success', 'simple-cloudflare-turnstile') . '</th>';
			echo '<th>' . esc_html__('Response', 'simple-cloudflare-turnstile') . '</th>';
			echo '<th>' . esc_html__('Info', 'simple-cloudflare-turnstile') . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($cfturnstile_log_reversed as $log) {
				if (!is_array($log)) {
					continue;
				}
				echo '<tr>';
				$log_date = isset($log['date']) ? cfturnstile_format_analytics_datetime($log['date']) : '';
				echo '<td>' . esc_html($log_date) . '</td>';
				echo '<td>' . (!empty($log['success']) ? '<span style="color: green;">' . esc_html__('Yes', 'simple-cloudflare-turnstile') . '</span>' : '<span style="color: red;">' . esc_html__('No', 'simple-cloudflare-turnstile') . '</span>') . '</td>';
				echo '<td>';
				if (empty($log['success'])) {
					$error_val = isset($log['error']) ? $log['error'] : '';
					echo esc_html(cfturnstile_format_debug_log_error($error_val));
				} else {
					echo '<span>' . esc_html__('Success', 'simple-cloudflare-turnstile') . '</span>';
				}
				echo '</td>';
				echo '<td>';
				echo '<strong>' . esc_html__('IP:', 'simple-cloudflare-turnstile') . '</strong> ' . esc_html(isset($log['ip']) ? cfturnstile_sanitize_analytics_scalar($log['ip']) : '') . '<br />';
				echo '<strong>' . esc_html__('URL:', 'simple-cloudflare-turnstile') . '</strong> ' . esc_html(isset($log['page']) ? cfturnstile_sanitize_analytics_scalar($log['page']) : '');
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '</div>';

			echo '<div class="sct-error-help">';
			echo '<strong><u>' . esc_html__('Error Codes', 'simple-cloudflare-turnstile') . '</u></strong><br />';
			echo '- <strong>missing-input-response:</strong> ' . cfturnstile_error_message('missing-input-response') . esc_html__(' (Visitor submitted form when Turnstile was not successfully completed.)', 'simple-cloudflare-turnstile') . '<br />';
			echo '- <strong>missing-input-secret:</strong> ' . cfturnstile_error_message('missing-input-secret') . '<br />';
			echo '- <strong>invalid-input-secret:</strong> ' . cfturnstile_error_message('invalid-input-secret') . '<br />';
			echo '- <strong>invalid-input-response:</strong> ' . cfturnstile_error_message('invalid-input-response') . '<br />';
			echo '- <strong>bad-request:</strong> ' . cfturnstile_error_message('bad-request') . '<br />';
			echo '- <strong>timeout-or-duplicate:</strong> ' . cfturnstile_error_message('timeout-or-duplicate') . '<br />';
			echo '- <strong>internal-error:</strong> ' . cfturnstile_error_message('internal-error') . '<br />';
			echo '</div>';
		} else {
			echo '<textarea id="cfturnstile-debug-log-text" readonly style="position:absolute; left:-9999px; top:-9999px;"></textarea>';
			echo '<p>' . esc_html__('No events logged yet.', 'simple-cloudflare-turnstile') . '</p>';
		}
		?>
	</div>
	<?php
}

function cfturnstile_render_debug_log_actions($has_log) {
	$reset_attributes = $has_log
		? array('onclick' => "return confirm('" . esc_js(__('Reset the debug log?', 'simple-cloudflare-turnstile')) . "');")
		: array('disabled' => 'disabled');
	?>
	<div class="sct-debug-log-actions">
		<button type="button" class="button button-secondary sct-small-button" id="cfturnstile-copy-log" data-target="cfturnstile-debug-log-text" <?php disabled(!$has_log); ?>><?php echo esc_html__('Copy Log', 'simple-cloudflare-turnstile'); ?></button>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sct-reset-log-form">
			<input type="hidden" name="action" value="cfturnstile_reset_log" />
			<?php wp_nonce_field('cfturnstile_reset_log'); ?>
			<?php submit_button(esc_html__('Reset Log', 'simple-cloudflare-turnstile'), 'secondary', 'submit', false, $reset_attributes); ?>
		</form>
	</div>
	<?php
}
