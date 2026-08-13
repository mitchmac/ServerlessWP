<?php
/**
 * Bulk Sync View
 * Variables: $total, $synced, $remaining
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Yctvn Media Offload Bulk Sync', 'yctvn-media-offload-cloudflare-r2'); ?></h1>

    <div style="background: white; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
        <h2><?php esc_html_e('Media Statistics', 'yctvn-media-offload-cloudflare-r2'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Total Media Files', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td><strong><?php echo number_format($total); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Already Synced', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td><strong><?php echo number_format($synced); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Remaining', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td><strong><?php echo number_format($remaining); ?></strong></td>
            </tr>
        </table>
    </div>

    <div style="background: white; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
        <h2><?php esc_html_e('Bulk Sync Options', 'yctvn-media-offload-cloudflare-r2'); ?></h2>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Sync Mode', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="radio" name="sync-mode" value="full" checked>
                        <strong><?php esc_html_e('Full Sync', 'yctvn-media-offload-cloudflare-r2'); ?></strong>
                        <br>
                        <span class="description"><?php esc_html_e('Upload all unsynced attachments and their sizes', 'yctvn-media-offload-cloudflare-r2'); ?></span>
                    </label>
                    <br><br>
                    <label>
                        <input type="radio" name="sync-mode" value="incremental">
                        <strong><?php esc_html_e('Incremental Sync', 'yctvn-media-offload-cloudflare-r2'); ?></strong>
                        <br>
                        <span class="description"><?php esc_html_e('Check already synced attachments and upload only missing thumbnail sizes', 'yctvn-media-offload-cloudflare-r2'); ?></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Batch Size', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <select id="batch-size">
                        <option value="5">5 files</option>
                        <option value="10" selected>10 files</option>
                        <option value="25">25 files</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Advanced Options', 'yctvn-media-offload-cloudflare-r2'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="regenerate-metadata" value="1">
                        <?php esc_html_e('Regenerate image metadata before syncing', 'yctvn-media-offload-cloudflare-r2'); ?>
                        <br>
                        <span class="description"><?php esc_html_e('This will recreate missing thumbnail sizes if metadata is corrupted', 'yctvn-media-offload-cloudflare-r2'); ?></span>
                    </label>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" id="start-sync" class="button-primary button-hero">
                🚀 <?php esc_html_e('Start Bulk Sync', 'yctvn-media-offload-cloudflare-r2'); ?>
            </button>
            <button type="button" id="stop-sync" class="button-secondary" style="display: none;">
                ⏹️ <?php esc_html_e('Stop Sync', 'yctvn-media-offload-cloudflare-r2'); ?>
            </button>
        </p>
        
        <div id="sync-progress" style="display: none; margin-top: 20px;">
            <h3><?php esc_html_e('Sync Progress', 'yctvn-media-offload-cloudflare-r2'); ?></h3>
            
            <!-- Progress Bar -->
            <div style="background: #f0f0f1; border-radius: 5px; overflow: hidden; margin: 15px 0; height: 30px; position: relative; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                <div id="progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #2271b1 0%, #135e96 100%); transition: width 0.3s ease; position: relative;">
                    <span id="progress-text" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; text-shadow: 0 1px 2px rgba(0,0,0,0.3); width: 200px; text-align: center;">0%</span>
                </div>
            </div>
            
            <!-- Stats -->
            <div style="display: flex; justify-content: space-between; margin: 15px 0; padding: 10px; background: #f9f9f9; border-radius: 3px;">
                <div>
                    <strong><?php esc_html_e('Status:', 'yctvn-media-offload-cloudflare-r2'); ?></strong>
                    <span id="sync-status">Initializing...</span>
                </div>
                <div>
                    <strong><?php esc_html_e('Processed:', 'yctvn-media-offload-cloudflare-r2'); ?></strong>
                    <span id="processed-count">0</span> / <span id="total-count">0</span>
                </div>
                <div>
                    <button type="button" id="clear-log" class="button-link" style="color: #2271b1; text-decoration: none; cursor: pointer;">
                        🗑️ <?php esc_html_e('Clear Log', 'yctvn-media-offload-cloudflare-r2'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Log -->
            <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 3px; font-family: 'Courier New', monospace; font-size: 13px; max-height: 400px; overflow-y: auto; margin-top: 15px;" id="sync-log">
                <!-- Logs will appear here -->
            </div>
        </div>
    </div>

    <?php if ($remaining == 0 && $synced > 0) : ?>
    <div class="notice notice-info">
        <p><?php esc_html_e('All media files are already synced! You can use Incremental Sync to check for missing thumbnail sizes.', 'yctvn-media-offload-cloudflare-r2'); ?></p>
    </div>
    <?php endif; ?>
</div>


