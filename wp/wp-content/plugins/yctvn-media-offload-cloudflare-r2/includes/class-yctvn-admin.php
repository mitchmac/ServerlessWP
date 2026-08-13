<?php
/**
 * Yctvn Admin Class
 *
 * Handles admin pages and settings
 *
 * @package Yctvn_Media_Offload
 * @since 1.0.0
 */

namespace Yctvn_Media_Offload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Yctvn_Admin
 *
 * Manages admin interface and settings pages
 */
class Yctvn_Admin {
    
    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;
    
    /**
     * R2 API instance
     *
     * @var Yctvn_API
     */
    private $api;
    
    /**
     * Bulk sync instance
     *
     * @var Yctvn_Bulk_Sync
     */
    private $bulk_sync;
    
    /**
     * Constructor
     *
     * @param array        $settings  Plugin settings
     * @param Yctvn_API       $api       Cloudflare R2 API instance
     * @param Yctvn_Bulk_Sync $bulk_sync Bulk sync instance
     */
    public function __construct( $settings, Yctvn_API $api, Yctvn_Bulk_Sync $bulk_sync ) {
        $this->settings = $settings;
        $this->api = $api;
        $this->bulk_sync = $bulk_sync;
        $this->init_hooks();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'init_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_yctvn_media_offload_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_yctvn_media_offload_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_yctvn_media_offload_run_auto_sync', array( $this, 'ajax_run_auto_sync' ) );
        add_action( 'wp_ajax_yctvn_media_offload_fix_all_thumbnails', array( $this, 'ajax_fix_all_thumbnails' ) );
        add_action( 'wp_ajax_yctvn_media_offload_bulk_sync_batch', array( $this->bulk_sync, 'ajax_bulk_sync_batch' ) );
        add_action( 'wp_ajax_yctvn_media_offload_get_sync_count', array( $this->bulk_sync, 'ajax_get_sync_count' ) );

        // Add bulk actions to Media Library
        add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on our plugin pages
        if ( ! in_array( $hook, array( 'settings_page_yctvn-media-offload', 'settings_page_yctvn-media-offload-bulk-sync' ) ) ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'yctvn-media-offload-admin',
            YCTVN_MEDIA_OFFLOAD_URL . 'assets/css/admin.css',
            array(),
            YCTVN_MEDIA_OFFLOAD_VERSION
        );

        // Enqueue jQuery
        wp_enqueue_script( 'jquery' );

        if ( $hook === 'settings_page_yctvn-media-offload' ) {
            // Settings page JS
            wp_enqueue_script(
                'yctvn-media-offload-admin-settings',
                YCTVN_MEDIA_OFFLOAD_URL . 'assets/js/admin-settings.js',
                array( 'jquery' ),
                YCTVN_MEDIA_OFFLOAD_VERSION,
                true
            );

            // Localize script for settings page
            wp_localize_script( 'yctvn-media-offload-admin-settings', 'yctvn_media_offload_admin', array(
                'save_settings_nonce' => wp_create_nonce( 'yctvn_media_offload_save_settings' ),
                'test_connection_nonce' => wp_create_nonce( 'yctvn_media_offload_test_connection' ),
                'fix_thumbnails_nonce' => wp_create_nonce( 'yctvn_media_offload_fix_all_thumbnails' ),
                'run_sync_nonce' => wp_create_nonce( 'yctvn_media_offload_run_auto_sync' ),
                'test_connection_text' => esc_html__( 'Test Connection', 'yctvn-media-offload-cloudflare-r2' ),
                'fix_thumbnails_text' => esc_html__( 'Fix All Thumbnails', 'yctvn-media-offload-cloudflare-r2' ),
                'run_sync_text' => esc_html__( 'Run Auto Sync Now', 'yctvn-media-offload-cloudflare-r2' ),
                'fix_thumbnails_confirm' => esc_html__( 'This will regenerate thumbnails for ALL media. Continue?', 'yctvn-media-offload-cloudflare-r2' ),
            ) );
        } elseif ( $hook === 'settings_page_yctvn-media-offload-bulk-sync' ) {
            // Bulk sync page JS
            wp_enqueue_script(
                'yctvn-media-offload-bulk-sync',
                YCTVN_MEDIA_OFFLOAD_URL . 'assets/js/bulk-sync.js',
                array( 'jquery' ),
                YCTVN_MEDIA_OFFLOAD_VERSION,
                true
            );

            // Localize script for bulk sync page
            wp_localize_script( 'yctvn-media-offload-bulk-sync', 'yctvn_media_offload_bulk', array(
                'nonce' => wp_create_nonce( 'yctvn_media_offload_bulk_sync' ),
            ) );
        }
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main plugin page under Settings
        add_options_page(
            esc_html__( 'Yctvn Media Offload', 'yctvn-media-offload-cloudflare-r2' ),
            esc_html__( 'Yctvn Media Offload', 'yctvn-media-offload-cloudflare-r2' ),
            'manage_options',
            'yctvn-media-offload',
            array( $this, 'render_settings_page' )
        );

        // Bulk Sync as submenu of the main plugin page
        add_submenu_page(
            'options-general.php',
            esc_html__( 'Bulk Sync', 'yctvn-media-offload-cloudflare-r2' ),
            esc_html__( 'Bulk Sync', 'yctvn-media-offload-cloudflare-r2' ),
            'manage_options',
            'yctvn-media-offload-bulk-sync',
            array( $this, 'render_bulk_sync_page' )
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting( 'yctvn_media_offload_settings', 'yctvn_media_offload_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' )
        ) );
    }
    
    /**
     * Sanitize settings
     *
     * @param array $input The settings input array
     * @return array Sanitized settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        // Sanitize text fields
        $sanitized['account_id'] = sanitize_text_field( $input['account_id'] ?? '' );
        $sanitized['access_key_id'] = sanitize_text_field( $input['access_key_id'] ?? '' );
        $sanitized['secret_access_key'] = sanitize_text_field( $input['secret_access_key'] ?? '' );
        $sanitized['bucket_name'] = sanitize_text_field( $input['bucket_name'] ?? '' );
        $sanitized['public_url'] = esc_url_raw( $input['public_url'] ?? '' );
        
        // Sanitize checkboxes (booleans)
        $sanitized['auto_offload'] = ! empty( $input['auto_offload'] );
        $sanitized['enable_url_rewrite'] = ! empty( $input['enable_url_rewrite'] );
        $sanitized['delete_local_files'] = ! empty( $input['delete_local_files'] );
        $sanitized['auto_fix_thumbnails'] = ! empty( $input['auto_fix_thumbnails'] );
        $sanitized['enable_debug_logging'] = ! empty( $input['enable_debug_logging'] );
        
        // Sanitize upload mode with whitelist
        $valid_modes = array( 'full_only', 'complete' );
        $upload_mode = isset( $input['upload_mode'] ) ? sanitize_text_field( $input['upload_mode'] ) : 'full_only';
        $sanitized['upload_mode'] = in_array( $upload_mode, $valid_modes, true ) ? $upload_mode : 'full_only';
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Handle form submission
        if ( isset( $_POST['submit'] ) ) {
            check_admin_referer( 'yctvn_media_offload_settings_nonce' );
            
            $new_settings = array();
            $input = isset( $_POST['yctvn_media_offload_settings'] ) ? map_deep( wp_unslash( $_POST['yctvn_media_offload_settings'] ), 'sanitize_text_field' ) : array();
            
            // Sanitize settings
            $new_settings['account_id'] = sanitize_text_field( $input['account_id'] ?? '' );
            $new_settings['access_key_id'] = sanitize_text_field( $input['access_key_id'] ?? '' );
            $new_settings['secret_access_key'] = sanitize_text_field( $input['secret_access_key'] ?? '' );
            $new_settings['bucket_name'] = sanitize_text_field( $input['bucket_name'] ?? '' );
            $new_settings['public_url'] = esc_url_raw( $input['public_url'] ?? '' );
            $new_settings['auto_offload'] = isset( $input['auto_offload'] );
            $new_settings['enable_url_rewrite'] = isset( $input['enable_url_rewrite'] );
            $new_settings['delete_local_files'] = isset( $input['delete_local_files'] );
            $new_settings['auto_fix_thumbnails'] = isset( $input['auto_fix_thumbnails'] );
            $new_settings['upload_mode'] = sanitize_text_field( $input['upload_mode'] ?? 'full_only' );
            $new_settings['enable_debug_logging'] = isset( $input['enable_debug_logging'] );
            
            update_option( 'yctvn_media_offload_settings', $new_settings );
            
            // Handle auto sync settings
            $auto_sync_enabled = isset( $_POST['yctvn_media_offload_auto_sync_enabled'] );
            update_option( 'yctvn_media_offload_auto_sync_enabled', $auto_sync_enabled );
            
            if ( isset( $_POST['yctvn_media_offload_auto_sync_batch_size'] ) ) {
                $batch_size = intval( $_POST['yctvn_media_offload_auto_sync_batch_size'] );
                $batch_size = max( 1, min( 50, $batch_size ) ); // Clamp between 1 and 50
                update_option( 'yctvn_media_offload_auto_sync_batch_size', $batch_size );
            }
            
            if ( isset( $_POST['yctvn_media_offload_auto_sync_interval'] ) ) {
                update_option( 'yctvn_media_offload_auto_sync_interval', sanitize_text_field( wp_unslash( $_POST['yctvn_media_offload_auto_sync_interval'] ) ) );
            }
            
            // Trigger settings reload in main class
            do_action( 'yctvn_media_offload_settings_updated', $new_settings );
            
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved!', 'yctvn-media-offload-cloudflare-r2' ) . '</p></div>';
            
            // Update local settings
            $this->settings = $new_settings;
            
            // Test connection if configured
            if ( $this->api->is_configured() ) {
                $test_result = $this->api->test_connection();
                if ( is_wp_error( $test_result ) ) {
                    echo '<div class="notice notice-warning"><p><strong>Warning:</strong> ' . esc_html( $test_result->get_error_message() ) . '</p></div>';
                }
            }
        }
        
        include YCTVN_MEDIA_OFFLOAD_PATH . 'views/admin-settings.php';
    }
    
    /**
     * Render bulk sync page
     */
    public function render_bulk_sync_page() {
        $total = $this->bulk_sync->get_total_media();
        $synced = $this->bulk_sync->get_synced_media();
        $remaining = $total - $synced;
        
        include YCTVN_MEDIA_OFFLOAD_PATH . 'views/bulk-sync.php';
    }
    
    /**
     * AJAX handler for connection test
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'yctvn_media_offload_test_connection', 'nonce' );
        
        if ( ! $this->api->is_configured() ) {
            wp_send_json_error( 'Please configure R2 credentials first.' );
        }
        
        $result = $this->api->test_connection();
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( 'Connection successful! R2 bucket is accessible.' );
        }
    }
    
    /**
     * AJAX handler for save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'yctvn_media_offload_save_settings', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        // Parse the serialized form data
        parse_str( wp_unslash( $_POST['settings'] ), $form_data );
        
        if ( ! isset( $form_data['yctvn_media_offload_settings'] ) ) {
            wp_send_json_error( 'Invalid form data' );
        }
        
        $input = $form_data['yctvn_media_offload_settings'];
        
        // Sanitize settings
        $new_settings = array();
        $new_settings['account_id'] = sanitize_text_field( $input['account_id'] ?? '' );
        $new_settings['access_key_id'] = sanitize_text_field( $input['access_key_id'] ?? '' );
        $new_settings['secret_access_key'] = sanitize_text_field( $input['secret_access_key'] ?? '' );
        $new_settings['bucket_name'] = sanitize_text_field( $input['bucket_name'] ?? '' );
        $new_settings['public_url'] = esc_url_raw( $input['public_url'] ?? '' );
        $new_settings['auto_offload'] = isset( $input['auto_offload'] );
        $new_settings['enable_url_rewrite'] = isset( $input['enable_url_rewrite'] );
        $new_settings['delete_local_files'] = isset( $input['delete_local_files'] );
        $new_settings['auto_fix_thumbnails'] = isset( $input['auto_fix_thumbnails'] );
        $new_settings['upload_mode'] = sanitize_text_field( $input['upload_mode'] ?? 'full_only' );
        $new_settings['enable_debug_logging'] = isset( $input['enable_debug_logging'] );
        
        // Update settings
        update_option( 'yctvn_media_offload_settings', $new_settings );
        
        // Handle auto sync settings
        $auto_sync_enabled = isset( $form_data['yctvn_media_offload_auto_sync_enabled'] );
        update_option( 'yctvn_media_offload_auto_sync_enabled', $auto_sync_enabled );
        
        if ( isset( $form_data['yctvn_media_offload_auto_sync_batch_size'] ) ) {
            $batch_size = intval( $form_data['yctvn_media_offload_auto_sync_batch_size'] );
            $batch_size = max( 1, min( 50, $batch_size ) );
            update_option( 'yctvn_media_offload_auto_sync_batch_size', $batch_size );
        }
        
        if ( isset( $form_data['yctvn_media_offload_auto_sync_interval'] ) ) {
            update_option( 'yctvn_media_offload_auto_sync_interval', sanitize_text_field( $form_data['yctvn_media_offload_auto_sync_interval'] ) );
        }
        
        // Trigger settings reload in main class
        do_action( 'yctvn_media_offload_settings_updated', $new_settings );
        
        // Update local settings
        $this->settings = $new_settings;
        
        // Prepare response
        $response = array(
            'message' => __( 'Settings saved successfully!', 'yctvn-media-offload-cloudflare-r2' )
        );
        
        // Test connection if configured
        $this->api = new \Yctvn_Media_Offload\Yctvn_API( $new_settings );
        if ( $this->api->is_configured() ) {
            $test_result = $this->api->test_connection();
            if ( is_wp_error( $test_result ) ) {
                $response['warning'] = 'Warning: ' . $test_result->get_error_message();
            }
        }
        
        wp_send_json_success( $response );
    }
    
    /**
     * AJAX handler for manual auto sync trigger
     */
    public function ajax_run_auto_sync() {
        check_ajax_referer( 'yctvn_media_offload_run_auto_sync', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        // Get main plugin instance and run sync
        $plugin = Yctvn_Media_Offload::get_instance();
        
        // Run the auto sync process
        do_action( 'yctvn_media_offload_auto_sync_cron' );
        
        wp_send_json_success( 'Auto sync process completed. Check the logs for details.' );
    }
    
    /**
     * AJAX handler for fix all thumbnails
     */
    public function ajax_fix_all_thumbnails() {
        check_ajax_referer( 'yctvn_media_offload_fix_all_thumbnails', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        // Use the Fix Thumbnails class
        $fixer = new \Yctvn_Media_Offload\Yctvn_Fix_Thumbnails();
        
        global $wpdb;
        
        // Get all image attachments (limit to 10 for testing)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $attachments = $wpdb->get_results( "
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type LIKE 'image/%'
            ORDER BY ID DESC
            LIMIT 10
        " );
        
        if ( empty( $attachments ) ) {
            wp_send_json_error( esc_html__( 'No images found to fix', 'yctvn-media-offload-cloudflare-r2' ) );
        }
        
        $fixed = 0;
        $errors = 0;
        $details = array();
        
        foreach ( $attachments as $attachment ) {
            $title = get_the_title( $attachment->ID ) ?: 'Attachment ' . $attachment->ID;
            
            if ( $fixer->fix_single_thumbnail( $attachment->ID ) ) {
                $fixed++;
                $metadata = wp_get_attachment_metadata( $attachment->ID );
                $sizes = isset( $metadata['sizes'] ) ? count( $metadata['sizes'] ) : 0;
                $details[] = sprintf( '%s: %d sizes', $title, $sizes );
            } else {
                $errors++;
            }
        }
        
        $message = sprintf(
            /* translators: 1: Number of fixed attachments, 2: Number of errors, 3: Details string */
            esc_html__( 'Fixed %1$d attachments, %2$d errors. Details: %3$s', 'yctvn-media-offload-cloudflare-r2' ),
            $fixed,
            $errors,
            implode( ', ', array_slice( $details, 0, 3 ) )
        );
        
        wp_send_json_success( $message );
    }
    
    /**
     * Show debug information
     */
    public function show_debug_info() {
        global $wpdb;
        
        // Get recent attachments with R2 status
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $attachments = $wpdb->get_results( "
            SELECT p.ID, p.post_title, pm.meta_value as r2_url, pm2.meta_value as r2_key
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_r2_url'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_r2_key'
            WHERE p.post_type = 'attachment'
            ORDER BY p.ID DESC
            LIMIT 10
        " );
        
        echo '<h3>Recent Attachments Status:</h3>';
        echo '<div style="overflow-x: auto; max-width: 100%;">';
        echo '<table class="widefat" style="table-layout: fixed; width: 100%;">';
        echo '<thead><tr>';
        echo '<th style="width: 60px;">ID</th>';
        echo '<th style="width: 200px;">Title</th>';
        echo '<th>R2 URL</th>';
        echo '<th style="width: 100px;">Status</th>';
        echo '</tr></thead><tbody>';
        
        foreach ( $attachments as $att ) {
            $status = ! empty( $att->r2_url ) ? '✅ Synced' : '❌ Not synced';
            $title = ! empty( $att->post_title ) ? $att->post_title : 'No title';
            
            // Truncate long title
            if ( strlen( $title ) > 30 ) {
                $title = substr( $title, 0, 27 ) . '...';
            }
            
            // Truncate long URL for display
            $display_url = $att->r2_url;
            if ( strlen( $display_url ) > 60 ) {
                $display_url = substr( $display_url, 0, 30 ) . '...' . substr( $display_url, -25 );
            }
            
            echo '<tr>';
            echo '<td>' . esc_html( $att->ID ) . '</td>';
            echo '<td style="word-wrap: break-word; overflow-wrap: break-word;">' . esc_html( $title ) . '</td>';
            echo '<td style="word-wrap: break-word; word-break: break-all; overflow-wrap: anywhere;" title="' . esc_attr( $att->r2_url ) . '">' . esc_html( $display_url ) . '</td>';
            echo '<td>' . esc_html( $status ) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>'; // Close overflow div
        
        // Settings debug
        echo '<h3>Current Settings:</h3>';
        echo '<pre>';
        echo 'Auto Offload: ' . ( $this->settings['auto_offload'] ? 'YES' : 'NO' ) . "\n";
        echo 'URL Rewrite: ' . ( $this->settings['enable_url_rewrite'] ? 'YES' : 'NO' ) . "\n";
        echo 'Debug Logging: ' . ( $this->settings['enable_debug_logging'] ? 'YES' : 'NO' ) . "\n";
        echo 'Configured: ' . ( $this->api->is_configured() ? 'YES' : 'NO' ) . "\n";
        echo '</pre>';
        
        // Test URL rewrite
        if ( ! empty( $attachments ) ) {
            $test_att = $attachments[0];
            echo '<h3>URL Rewrite Test:</h3>';
            echo '<p><strong>Testing attachment ID ' . esc_html( $test_att->ID ) . ':</strong></p>';
            
            $original_url = wp_get_attachment_url( $test_att->ID );
            echo '<div style="word-wrap: break-word; word-break: break-all; overflow-wrap: anywhere; background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ddd; font-family: monospace; font-size: 12px;">';
            echo '<strong>Current URL:</strong><br>' . esc_html( $original_url );
            echo '</div>';
            
            if ( ! empty( $test_att->r2_url ) ) {
                echo '<div style="word-wrap: break-word; word-break: break-all; overflow-wrap: anywhere; background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ddd; font-family: monospace; font-size: 12px;">';
                echo '<strong>Expected R2 URL:</strong><br>' . esc_html( $test_att->r2_url );
                echo '</div>';
                if ( $original_url === $test_att->r2_url ) {
                    echo '<p style="color: green;">✅ URL rewrite is working!</p>';
                } else {
                    echo '<p style="color: red;">❌ URL rewrite is NOT working!</p>';
                }
            } else {
                echo '<p style="color: orange;">⚠️ No R2 URL available for this attachment</p>';
            }
        }
    }
    
    /**
     * Add bulk actions to Media Library
     *
     * @param array $actions Existing bulk actions
     * @return array Modified actions
     */
    public function add_bulk_actions( $actions ) {
        $actions['yctvn_media_offload_fix_thumbnails'] = esc_html__( 'Fix Thumbnails', 'yctvn-media-offload-cloudflare-r2' );
        $actions['yctvn_media_offload_sync_to_cloudflare_r2'] = esc_html__( 'Sync to Cloudflare R2', 'yctvn-media-offload-cloudflare-r2' );
        $actions['yctvn_media_offload_regenerate_metadata'] = esc_html__( 'Regenerate Metadata', 'yctvn-media-offload-cloudflare-r2' );
        return $actions;
    }
    
    /**
     * Handle bulk actions
     *
     * @param string $redirect_to Redirect URL
     * @param string $action      Action name
     * @param array  $post_ids    Selected post IDs
     * @return string Redirect URL
     */
    public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
        if ( empty( $post_ids ) ) {
            return $redirect_to;
        }
        
        $count = 0;
        $errors = 0;
        
        switch ( $action ) {
            case 'yctvn_media_offload_fix_thumbnails':
                foreach ( $post_ids as $post_id ) {
                    if ( $this->fix_attachment_thumbnails( $post_id ) ) {
                        $count++;
                    } else {
                        $errors++;
                    }
                }
                $redirect_to = add_query_arg( 'yctvn_media_offload_fixed_thumbnails', $count, $redirect_to );
                if ( $errors > 0 ) {
                    $redirect_to = add_query_arg( 'yctvn_media_offload_fix_errors', $errors, $redirect_to );
                }
                break;
                
            case 'yctvn_media_offload_sync_to_cloudflare_r2':
                foreach ( $post_ids as $post_id ) {
                    if ( $this->sync_single_attachment( $post_id ) ) {
                        $count++;
                    } else {
                        $errors++;
                    }
                }
                $redirect_to = add_query_arg( 'yctvn_media_offload_synced', $count, $redirect_to );
                if ( $errors > 0 ) {
                    $redirect_to = add_query_arg( 'yctvn_media_offload_sync_errors', $errors, $redirect_to );
                }
                break;
                
            case 'yctvn_media_offload_regenerate_metadata':
                foreach ( $post_ids as $post_id ) {
                    if ( $this->regenerate_attachment_metadata( $post_id ) ) {
                        $count++;
                    } else {
                        $errors++;
                    }
                }
                $redirect_to = add_query_arg( 'yctvn_media_offload_regenerated', $count, $redirect_to );
                if ( $errors > 0 ) {
                    $redirect_to = add_query_arg( 'yctvn_media_offload_regen_errors', $errors, $redirect_to );
                }
                break;
        }
        
        return $redirect_to;
    }
    
    /**
     * Display bulk action notices
     */
    public function bulk_action_notices() {
        // Fix thumbnails notice
        if ( isset( $_GET['yctvn_media_offload_fixed_thumbnails'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'yctvn_media_offload_fix_thumbnails' ) ) {
            $count = intval( $_GET['yctvn_media_offload_fixed_thumbnails'] );
            $errors = isset( $_GET['yctvn_media_offload_fix_errors'] ) ? intval( $_GET['yctvn_media_offload_fix_errors'] ) : 0;
            
            if ( $count > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                /* translators: %d: Number of media items */
                printf( esc_html( _n( 'Fixed thumbnails for %d media item.', 'Fixed thumbnails for %d media items.', $count, 'yctvn-media-offload-cloudflare-r2' ) ), esc_html( $count ) );
                echo '</p></div>';
            }
            
            if ( $errors > 0 ) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                /* translators: %d: Number of media items */
                printf( esc_html( _n( 'Failed to fix %d media item.', 'Failed to fix %d media items.', $errors, 'yctvn-media-offload-cloudflare-r2' ) ), esc_html( $errors ) );
                echo '</p></div>';
            }
        }
        
        // Sync notice
        if ( isset( $_GET['yctvn_media_offload_synced'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'yctvn_media_offload_bulk_sync' ) ) {
            $count = intval( $_GET['yctvn_media_offload_synced'] );
            $errors = isset( $_GET['yctvn_media_offload_sync_errors'] ) ? intval( $_GET['yctvn_media_offload_sync_errors'] ) : 0;
            
            if ( $count > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                /* translators: %d: Number of media items */
                printf( esc_html( _n( 'Synced %d media item to Cloudflare R2.', 'Synced %d media items to Cloudflare R2.', $count, 'yctvn-media-offload-cloudflare-r2' ) ), esc_html( $count ) );
                echo '</p></div>';
            }
            
            if ( $errors > 0 ) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                /* translators: %d: Number of media items */
                printf( esc_html( _n( 'Failed to sync %d media item.', 'Failed to sync %d media items.', $errors, 'yctvn-media-offload-cloudflare-r2' ) ), esc_html( $errors ) );
                echo '</p></div>';
            }
        }
        
        // Regenerate metadata notice
        if ( isset( $_GET['yctvn_media_offload_regenerated'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'yctvn_media_offload_regenerate_metadata' ) ) {
            $count = intval( $_GET['yctvn_media_offload_regenerated'] );
            $errors = isset( $_GET['yctvn_media_offload_regen_errors'] ) ? intval( $_GET['yctvn_media_offload_regen_errors'] ) : 0;
            
            if ( $count > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                /* translators: %d: Number of media items */
                printf( esc_html( _n( 'Regenerated metadata for %d media item.', 'Regenerated metadata for %d media items.', $count, 'yctvn-media-offload-cloudflare-r2' ) ), esc_html( $count ) );
                echo '</p></div>';
            }
            
            if ( $errors > 0 ) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                /* translators: %d: Number of media items */
                printf( esc_html( _n( 'Failed to regenerate %d media item.', 'Failed to regenerate %d media items.', $errors, 'yctvn-media-offload-cloudflare-r2' ) ), esc_html( $errors ) );
                echo '</p></div>';
            }
        }
    }
    
    /**
     * Fix thumbnails for a single attachment
     *
     * @param int $attachment_id Attachment ID
     * @return bool Success status
     */
    public function fix_attachment_thumbnails( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file_path ) ) {
            // If local file missing but has R2 URL, just regenerate metadata
            $cloudflare_r2_url = get_post_meta( $attachment_id, '_r2_url', true );
            if ( ! empty( $cloudflare_r2_url ) ) {
                return $this->regenerate_attachment_metadata( $attachment_id );
            }
            return false;
        }
        
        // Regenerate thumbnails
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
        
        if ( ! empty( $metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
            
            // If R2 sync is enabled, sync the new thumbnails
            if ( $this->api->is_configured() && $this->settings['auto_offload'] ) {
                $this->sync_single_attachment( $attachment_id );
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Sync single attachment to R2
     *
     * @param int $attachment_id Attachment ID
     * @return bool Success status
     */
    private function sync_single_attachment( $attachment_id ) {
        if ( ! $this->api->is_configured() ) {
            return false;
        }
        
        $file_path = get_attached_file( $attachment_id );
        if ( ! file_exists( $file_path ) ) {
            return false;
        }
        
        // Get metadata
        $metadata = wp_get_attachment_metadata( $attachment_id );
        
        // Collect files to upload
        $files_to_upload = array( 'full' => $file_path );
        
        if ( ! empty( $metadata['sizes'] ) ) {
            $upload_dir = dirname( $file_path );
            foreach ( $metadata['sizes'] as $size => $size_data ) {
                $size_file = $upload_dir . '/' . $size_data['file'];
                if ( file_exists( $size_file ) ) {
                    $files_to_upload[ $size ] = $size_file;
                }
            }
        }
        
        // Upload all files
        return $this->api->upload_all_sizes( $attachment_id, $files_to_upload );
    }
    
    /**
     * Regenerate attachment metadata
     *
     * @param int $attachment_id Attachment ID
     * @return bool Success status
     */
    private function regenerate_attachment_metadata( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        
        // Check if file exists locally or on R2
        $cloudflare_r2_url = get_post_meta( $attachment_id, '_r2_url', true );
        
        if ( file_exists( $file_path ) ) {
            // Local file exists, regenerate normally
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
        } elseif ( ! empty( $cloudflare_r2_url ) ) {
            // File on R2, create basic metadata
            $metadata = wp_get_attachment_metadata( $attachment_id );
            if ( empty( $metadata ) ) {
                // Create basic metadata structure
                $imagesize = @getimagesize( $cloudflare_r2_url );
                if ( $imagesize ) {
                    $metadata = array(
                        'width' => $imagesize[0],
                        'height' => $imagesize[1],
                        'file' => basename( $file_path ),
                        'sizes' => array(),
                        'image_meta' => array()
                    );
                }
            }
        } else {
            return false;
        }
        
        if ( ! empty( $metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
            return true;
        }
        
        return false;
    }
}
