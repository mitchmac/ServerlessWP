<?php
/**
 * Admin Settings View
 * Variables passed from Yctvn_Admin class
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Settings is passed from admin class
$settings = $this->settings;
?>
<div class="wrap">
    <h1><?php esc_html_e('Yctvn Media Offload Settings', 'yctvn-media-offload-cloudflare-r2'); ?></h1>

    <form method="post">
        <?php wp_nonce_field('yctvn_media_offload_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Account ID', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <input type="text" name="yctvn_media_offload_settings[account_id]"
                           value="<?php echo esc_attr($settings['account_id']); ?>"
                           class="regular-text" />
                    <p class="description"><?php esc_html_e('Your Cloudflare account ID', 'yctvn-media-offload-cloudflare-r2'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Access Key ID', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <input type="text" name="yctvn_media_offload_settings[access_key_id]"
                           value="<?php echo esc_attr($settings['access_key_id']); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Secret Access Key', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <input type="password" name="yctvn_media_offload_settings[secret_access_key]"
                           value="<?php echo esc_attr($settings['secret_access_key']); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Bucket Name', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <input type="text" name="yctvn_media_offload_settings[bucket_name]"
                           value="<?php echo esc_attr($settings['bucket_name']); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Public URL', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <input type="url" name="yctvn_media_offload_settings[public_url]"
                           value="<?php echo esc_attr($settings['public_url']); ?>"
                           class="regular-text" />
                    <p class="description"><?php esc_html_e('Optional: Custom domain (e.g., https://cdn.example.com)', 'yctvn-media-offload-cloudflare-r2'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Options', 'yctvn-media-offload-cloudflare-r2'); ?></h3>
        <div class="notice notice-info inline">
            <p><strong><?php esc_html_e('Speed Optimization Tips:', 'yctvn-media-offload-cloudflare-r2'); ?></strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php esc_html_e('Use "Fast - Full size only" mode for quickest uploads', 'yctvn-media-offload-cloudflare-r2'); ?></li>
                <li><?php esc_html_e('Disable "Auto Fix Thumbnails" if not needed', 'yctvn-media-offload-cloudflare-r2'); ?></li>
                <li><?php esc_html_e('Enable "Delete Local Files" to save disk space', 'yctvn-media-offload-cloudflare-r2'); ?></li>
            </ul>
        </div>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Auto Offload New Media', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="yctvn_media_offload_settings[auto_offload]"
                               value="1" <?php checked($settings['auto_offload']); ?> />
                        <?php esc_html_e('Automatically upload new media to Cloudflare R2', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Enable URL Rewrite', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="yctvn_media_offload_settings[enable_url_rewrite]"
                               value="1" <?php checked($settings['enable_url_rewrite']); ?> />
                        <?php esc_html_e('Serve media from Cloudflare R2/CDN', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Delete Local Files', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="yctvn_media_offload_settings[delete_local_files]"
                               value="1" <?php checked($settings['delete_local_files']); ?> />
                        <?php esc_html_e('Remove local files after upload', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </label>
                    <p class="description" style="color: red;">
                        <?php esc_html_e('⚠️ Use with caution! Files will be permanently deleted from server.', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Upload Mode', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="radio" name="yctvn_media_offload_settings[upload_mode]"
                               value="full_only" <?php checked(($settings['upload_mode'] ?? 'full_only'), 'full_only'); ?> />
                        <?php esc_html_e('Fast - Full size only', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </label><br>
                    <label>
                        <input type="radio" name="yctvn_media_offload_settings[upload_mode]"
                               value="all_sizes" <?php checked(($settings['upload_mode'] ?? 'full_only'), 'all_sizes'); ?> />
                        <?php esc_html_e('Complete - All sizes (slower)', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Fast mode only uploads the main image for speed', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Auto Fix Thumbnails', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="yctvn_media_offload_settings[auto_fix_thumbnails]"
                               value="1" <?php checked($settings['auto_fix_thumbnails'] ?? false); ?> />
                        <?php esc_html_e('Automatically fix missing thumbnails when uploading', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Will regenerate thumbnails and sync to Cloudflare R2 automatically', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Enable Debug Logging', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="yctvn_media_offload_settings[enable_debug_logging]"
                               value="1" <?php checked($settings['enable_debug_logging']); ?> />
                        <?php esc_html_e('Enable debug logging to error log', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Auto Sync Settings', 'yctvn-media-offload-cloudflare-r2'); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Enable Auto Background Sync', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="yctvn_media_offload_auto_sync_enabled" id="auto-sync-enabled"
                               value="1" <?php checked(get_option('yctvn_media_offload_auto_sync_enabled', false)); ?> />
                        <?php esc_html_e('Automatically sync old media to Cloudflare R2 in background', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Will sync unsynced media every hour automatically', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Batch Size', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <input type="number" name="yctvn_media_offload_auto_sync_batch_size"
                           value="<?php echo esc_attr(get_option('yctvn_media_offload_auto_sync_batch_size', 10)); ?>"
                           min="1" max="50" class="small-text" />
                    <p class="description">
                        <?php esc_html_e('Number of files to sync per batch (1-50)', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Sync Interval', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <select name="yctvn_media_offload_auto_sync_interval">
                        <?php
                        $current_interval = get_option('yctvn_media_offload_auto_sync_interval', 'hourly');
                        $intervals = array(
                            'yctvn_every_5_minutes' => esc_html__('Every 5 minutes (for testing)', 'yctvn-media-offload-cloudflare-r2'),
                            'yctvn_every_15_minutes' => esc_html__('Every 15 minutes', 'yctvn-media-offload-cloudflare-r2'),
                            'yctvn_every_30_minutes' => esc_html__('Every 30 minutes', 'yctvn-media-offload-cloudflare-r2'),
                            'hourly' => esc_html__('Every hour', 'yctvn-media-offload-cloudflare-r2'),
                            'twicedaily' => esc_html__('Twice daily', 'yctvn-media-offload-cloudflare-r2'),
                            'daily' => esc_html__('Once daily', 'yctvn-media-offload-cloudflare-r2'),
                        );
                        foreach ($intervals as $value => $label) {
                            echo '<option value="' . esc_attr($value) . '" ' . selected($current_interval, $value, false) . '>' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
        
        <?php
        // Show auto sync status
        if ( get_option('yctvn_media_offload_auto_sync_enabled', false) ) {
            $next_run = wp_next_scheduled( 'yctvn_media_offload_auto_sync_cron' );
            if ( $next_run ) {
                echo '<div class="notice notice-info inline">';
                echo '<p><strong>' . esc_html__('Auto Sync Status:', 'yctvn-media-offload-cloudflare-r2') . '</strong> ';
                /* translators: %s: Date and time of next sync */
                echo esc_html( sprintf( esc_html__('Next sync scheduled at %s', 'yctvn-media-offload-cloudflare-r2'),
                    date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $next_run ) ) );
                echo '</p>';
                echo '</div>';
            }
        }
        ?>

        <p class="submit">
            <button type="button" id="save-settings" class="button-primary">
                <?php echo esc_attr__('Save Changes', 'yctvn-media-offload-cloudflare-r2'); ?>
            </button>
            <button type="button" id="test-connection" class="button-secondary">
                <?php esc_html_e('Test Connection', 'yctvn-media-offload-cloudflare-r2'); ?>
            </button>
            <button type="button" id="fix-all-thumbnails" class="button-secondary">
                <?php esc_html_e('Fix All Thumbnails', 'yctvn-media-offload-cloudflare-r2'); ?>
            </button>
            <a href="<?php echo esc_url( admin_url('options-general.php?page=yctvn-media-offload-bulk-sync') ); ?>"
               class="button-secondary">
                <?php esc_html_e('Bulk Sync Media', 'yctvn-media-offload-cloudflare-r2'); ?>
            </a>
            <?php if ( get_option('yctvn_media_offload_auto_sync_enabled', false) ) : ?>
            <button type="button" id="run-sync-now" class="button-secondary">
                <?php esc_html_e('Run Auto Sync Now', 'yctvn-media-offload-cloudflare-r2'); ?>
            </button>
            <?php endif; ?>
        </p>
        
        <div id="save-result" style="margin-top: 10px;"></div>
    </form>
    
    <div id="test-result" style="display: none; margin-top: 20px;"></div>
    
    <?php if ( current_user_can('manage_options') && $settings['enable_debug_logging'] ): ?>
    <div id="yctvn-media-offload-debug-info" style="background: white; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px; max-width: 100%; overflow: hidden; box-sizing: border-box;">
        <h2>Debug Information</h2>
        <div style="max-width: 100%; overflow-x: auto;">
            <?php $this->show_debug_info(); ?>
        </div>
    </div>
    <?php endif; ?>
</div>
