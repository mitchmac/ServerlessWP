<?php
if (!defined('ABSPATH')) {
	exit;
}

function cfturnstile_render_export_tab() {
	?>
	<div class="sct-tab-content sct-export-tab">
		<p class="sct-tab-intro">
			<?php echo esc_html__('Export all plugin settings to a JSON file, or import from a JSON file exported from this plugin.', 'simple-cloudflare-turnstile'); ?>
		</p>
		<div class="sct-export-grid">
			<div class="sct-admin-card">
				<h2><?php echo esc_html__('Export Settings', 'simple-cloudflare-turnstile'); ?></h2>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="cfturnstile_export_settings" />
					<?php wp_nonce_field('cfturnstile_export_settings'); ?>
					<label class="sct-checkbox-row">
						<input type="checkbox" name="include_keys" value="1">
						<span><?php echo esc_html__('Include API keys (sensitive)', 'simple-cloudflare-turnstile'); ?></span>
					</label>
					<?php submit_button(esc_html__('Download JSON', 'simple-cloudflare-turnstile'), 'secondary', 'submit', false); ?>
				</form>
			</div>

			<div class="sct-admin-card">
				<h2><?php echo esc_html__('Import Settings', 'simple-cloudflare-turnstile'); ?></h2>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="cfturnstile_import_settings" />
					<?php wp_nonce_field('cfturnstile_import_settings'); ?>
					<input type="file" name="cfturnstile_import_file" accept="application/json,.json" />
					<?php submit_button(esc_html__('Import JSON', 'simple-cloudflare-turnstile'), 'primary', 'submit', false); ?>
					<p class="description">
						<?php echo esc_html__('Site and Secret keys defined in wp-config.php will not be overwritten by import.', 'simple-cloudflare-turnstile'); ?>
					</p>
				</form>
			</div>
		</div>
	</div>
	<?php
}
