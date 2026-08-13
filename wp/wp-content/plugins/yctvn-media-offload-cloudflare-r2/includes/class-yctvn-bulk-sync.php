<?php
/**
 * Yctvn Bulk Sync Class
 *
 * Handles bulk synchronization of media to R2
 *
 * @package Yctvn_Media_Offload
 * @since 1.0.0
 */

namespace Yctvn_Media_Offload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Yctvn_Bulk_Sync
 *
 * Manages bulk synchronization operations and AJAX handlers
 */
class Yctvn_Bulk_Sync {
    
    /**
     * R2 API instance
     *
     * @var Yctvn_API
     */
    private $api;
    
    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;
    
    /**
     * Logger instance
     *
     * @var Yctvn_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param Yctvn_API $api      Cloudflare R2 API instance
     * @param array  $settings Plugin settings
     */
    public function __construct( Yctvn_API $api, $settings ) {
        $this->api = $api;
        $this->settings = $settings;
        $this->logger = Yctvn_Logger::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_yctvn_media_offload_bulk_sync', array( $this, 'ajax_bulk_sync' ) );
    }
    
    /**
     * Get total media count
     *
     * @return int
     */
    public function get_total_media() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
    }
    
    /**
     * Get synced media count
     *
     * @return int
     */
    public function get_synced_media() {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var( "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND pm.meta_key = '_r2_url'
            AND pm.meta_value != ''
        " );
    }
    
    /**
     * Get unsynced attachments
     *
     * @param int $offset Offset
     * @param int $limit  Limit
     * @return array Array of attachment posts
     */
    public function get_unsynced_attachments( $offset, $limit ) {
        global $wpdb;
        
        $query = "SELECT p.* FROM {$wpdb->posts} p 
                  LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_r2_url'
                  WHERE p.post_type = 'attachment' 
                  AND p.post_status = 'inherit'
                  AND (pm.meta_value IS NULL OR pm.meta_value = '')
                  ORDER BY p.ID ASC 
                  LIMIT %d OFFSET %d";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $query, $limit, $offset ) );
    }
    
    /**
     * Get partially synced attachments (for incremental sync)
     *
     * @param int $offset Offset
     * @param int $limit  Limit
     * @return array Array of attachment posts
     */
    public function get_partially_synced_attachments( $offset, $limit ) {
        global $wpdb;
        
        // Get attachments that have main file synced but may be missing sizes
        $query = "SELECT p.* FROM {$wpdb->posts} p 
                  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_r2_url'
                  WHERE p.post_type = 'attachment' 
                  AND p.post_status = 'inherit'
                  AND pm.meta_value != ''
                  ORDER BY p.ID ASC 
                  LIMIT %d OFFSET %d";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $query, $limit, $offset ) );
    }
    
    /**
     * AJAX handler for bulk sync
     */
    public function ajax_bulk_sync() {
        $this->logger->debug( '[R2 Bulk Sync] Starting bulk sync request' );
        
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yctvn_media_offload_bulk_sync' ) ) {
            $this->logger->error( '[R2 Bulk Sync] Nonce verification failed' );
            wp_send_json_error( 'Security check failed' );
            return;
        }
        $this->logger->debug( '[R2 Bulk Sync] Nonce verified' );
        
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
        $mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'full'; // 'full' or 'incremental'
        $regenerate_metadata = isset( $_POST['regenerate_metadata'] ) && $_POST['regenerate_metadata'] === 'true';
        
        $this->logger->debug( '[R2 Bulk Sync] Processing batch: offset=' . $offset . ', batch_size=' . $batch_size . ', mode=' . $mode );
        
        // Check if plugin is configured
        if ( ! $this->api->is_configured() ) {
            $this->logger->error( '[R2 Bulk Sync] Plugin not configured' );
            wp_send_json_error( 'Plugin not configured. Please check R2 credentials.' );
            return;
        }
        $this->logger->debug( '[R2 Bulk Sync] Plugin is configured' );
        
        // Get attachments based on mode
        if ( $mode === 'incremental' ) {
            $attachments = $this->get_partially_synced_attachments( $offset, $batch_size );
            $this->logger->debug( '[R2 Bulk Sync] Found ' . count( $attachments ) . ' partially synced attachments' );
        } else {
            $attachments = $this->get_unsynced_attachments( $offset, $batch_size );
            $this->logger->debug( '[R2 Bulk Sync] Found ' . count( $attachments ) . ' unsynced attachments' );
        }
        
        $messages = array();
        $processed = 0;
        
        foreach ( $attachments as $attachment ) {
            if ( $mode === 'incremental' ) {
                $result = $this->sync_missing_sizes( $attachment, $regenerate_metadata );
            } else {
                $result = $this->sync_attachment( $attachment, $regenerate_metadata );
            }
            $messages[] = $result;
            $processed++;
        }
        
        wp_send_json_success( array(
            'processed' => $processed,
            'messages' => $messages
        ) );
    }
    
    /**
     * Sync a single attachment
     *
     * @param \stdClass $attachment         Attachment post object
     * @param bool      $regenerate_metadata Whether to regenerate metadata
     * @return array Message array with type and message
     */
    private function sync_attachment( $attachment, $regenerate_metadata = false ) {
        $file_path = get_attached_file( $attachment->ID );
        $title = get_the_title( $attachment->ID ) ?: basename( $file_path );
        
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return array(
                'type' => 'error',
                'message' => "Skipped {$title}: File not found"
            );
        }
        
        // Check if already synced (full)
        $existing_url = get_post_meta( $attachment->ID, '_r2_url', true );
        if ( ! empty( $existing_url ) ) {
            return array(
                'type' => 'info',
                'message' => "Skipped {$title}: Already synced"
            );
        }
        
        // Regenerate metadata if requested and it's an image
        $mime_type = get_post_mime_type( $attachment->ID );
        if ( $regenerate_metadata && strpos( $mime_type, 'image/' ) === 0 ) {
            $this->logger->debug( "[R2 Bulk Sync] Regenerating metadata for attachment {$attachment->ID}" );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $metadata = wp_generate_attachment_metadata( $attachment->ID, $file_path );
            if ( ! empty( $metadata ) ) {
                wp_update_attachment_metadata( $attachment->ID, $metadata );
                $this->logger->debug( "[R2 Bulk Sync] Metadata regenerated for attachment {$attachment->ID}" );
            }
        }
        
        // Collect files: full + sizes if image
        $files_to_upload = array( 'full' => $file_path );
        
        if ( strpos( $mime_type, 'image/' ) === 0 ) {
            $meta = wp_get_attachment_metadata( $attachment->ID );
            if ( $meta && ! empty( $meta['sizes'] ) ) {
                $upload_dir = dirname( $file_path );
                foreach ( $meta['sizes'] as $size => $size_data ) {
                    $size_file = $upload_dir . '/' . $size_data['file'];
                    if ( file_exists( $size_file ) ) {
                        $files_to_upload[ $size ] = $size_file;
                    }
                }
            }
        }
        
        // Upload all files
        $all_ok = true;
        $uploaded_count = 0;
        
        foreach ( $files_to_upload as $size => $path ) {
            $res = $this->api->upload_file( $attachment->ID, $path, $size );
            
            if ( is_wp_error( $res ) ) {
                $all_ok = false;
                if ( $size === 'full' ) {
                    // If main file fails, return error
                    return array(
                        'type' => 'error',
                        'message' => "Failed {$title} ({$size}): " . $res->get_error_message()
                    );
                }
                // For thumbnails, just log but continue
                $this->logger->warning( "[R2 Bulk Sync] Failed to upload {$size} for attachment {$attachment->ID}: " . $res->get_error_message() );
            } else {
                $uploaded_count++;
                if ( $size === 'full' ) {
                    update_post_meta( $attachment->ID, '_r2_url', $res['url'] );
                    update_post_meta( $attachment->ID, '_r2_key', $res['key'] );
                } else {
                    update_post_meta( $attachment->ID, '_r2_url_' . $size, $res['url'] );
                    update_post_meta( $attachment->ID, '_r2_key_' . $size, $res['key'] );
                }
                
                // Truncate URL for logging
                $short_url = strlen($res['url']) > 50 ? substr($res['url'], 0, 47) . '...' : $res['url'];
                $this->logger->info( "Uploaded {$size} to {$short_url}" );
            }
        }
        
        if ( $uploaded_count > 0 ) {
            $message = "Synced {$title} ({$uploaded_count} files) -> R2";
            
            // Delete local files if enabled and all uploads successful
            if ( $all_ok && $this->settings['delete_local_files'] ) {
                foreach ( $files_to_upload as $path ) {
                    if ( file_exists( $path ) ) {
                        wp_delete_file( $path );
                    }
                }
                update_post_meta( $attachment->ID, '_r2_local_deleted', true );
                $message .= ' (local files deleted)';
            }
            
            return array(
                'type' => 'success',
                'message' => $message
            );
        }
        
        return array(
            'type' => 'error',
            'message' => "Failed to sync {$title}"
        );
    }
    
    /**
     * Sync only missing sizes for a partially synced attachment
     *
     * @param \stdClass $attachment         Attachment post object
     * @param bool      $regenerate_metadata Whether to regenerate metadata
     * @return array Message array with type and message
     */
    private function sync_missing_sizes( $attachment, $regenerate_metadata = false ) {
        $file_path = get_attached_file( $attachment->ID );
        $title = get_the_title( $attachment->ID ) ?: basename( $file_path );
        
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return array(
                'type' => 'error',
                'message' => "Skipped {$title}: Main file not found"
            );
        }
        
        $mime_type = get_post_mime_type( $attachment->ID );
        
        // Only process images for missing sizes
        if ( strpos( $mime_type, 'image/' ) !== 0 ) {
            return array(
                'type' => 'info',
                'message' => "Skipped {$title}: Not an image, no sizes to check"
            );
        }
        
        // Regenerate metadata if requested
        if ( $regenerate_metadata ) {
            $this->logger->debug( "[R2 Incremental Sync] Regenerating metadata for attachment {$attachment->ID}" );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $metadata = wp_generate_attachment_metadata( $attachment->ID, $file_path );
            if ( ! empty( $metadata ) ) {
                wp_update_attachment_metadata( $attachment->ID, $metadata );
                $this->logger->debug( "[R2 Incremental Sync] Metadata regenerated for attachment {$attachment->ID}" );
            }
        }
        
        // Get metadata to check available sizes
        $meta = wp_get_attachment_metadata( $attachment->ID );
        if ( ! $meta || empty( $meta['sizes'] ) ) {
            return array(
                'type' => 'info',
                'message' => "Skipped {$title}: No image sizes found in metadata"
            );
        }
        
        // Check which sizes are missing on R2
        $files_to_upload = array();
        $upload_dir = dirname( $file_path );
        $missing_sizes = array();
        
        foreach ( $meta['sizes'] as $size => $size_data ) {
            // Check if this size is already synced
            $size_url = get_post_meta( $attachment->ID, '_r2_url_' . $size, true );
            
            if ( empty( $size_url ) ) {
                // Size not synced, check if file exists locally
                $size_file = $upload_dir . '/' . $size_data['file'];
                if ( file_exists( $size_file ) ) {
                    $files_to_upload[ $size ] = $size_file;
                    $missing_sizes[] = $size;
                } else {
                    $this->logger->warning( "[R2 Incremental Sync] Size {$size} file not found: {$size_file}" );
                }
            }
        }
        
        // If no missing sizes, everything is synced
        if ( empty( $files_to_upload ) ) {
            return array(
                'type' => 'info',
                'message' => "Skipped {$title}: All sizes already synced"
            );
        }
        
        // Upload missing sizes
        $uploaded_count = 0;
        $failed_sizes = array();
        
        foreach ( $files_to_upload as $size => $path ) {
            $res = $this->api->upload_file( $attachment->ID, $path, $size );
            
            if ( is_wp_error( $res ) ) {
                $failed_sizes[] = $size;
                $this->logger->error( "[R2 Incremental Sync] Failed to upload {$size} for attachment {$attachment->ID}: " . $res->get_error_message() );
            } else {
                $uploaded_count++;
                update_post_meta( $attachment->ID, '_r2_url_' . $size, $res['url'] );
                update_post_meta( $attachment->ID, '_r2_key_' . $size, $res['key'] );
                $this->logger->info( "[R2 Incremental] Uploaded {$size} for ID {$attachment->ID}" );
            }
        }
        
        if ( $uploaded_count > 0 ) {
            $message = "Synced {$title}: {$uploaded_count} missing size(s) uploaded";
            if ( ! empty( $failed_sizes ) ) {
                $message .= " (failed: " . implode( ', ', $failed_sizes ) . ")";
            }
            
            // Delete local files if enabled and all uploads successful
            if ( empty( $failed_sizes ) && $this->settings['delete_local_files'] ) {
                foreach ( $files_to_upload as $path ) {
                    if ( file_exists( $path ) ) {
                        wp_delete_file( $path );
                    }
                }
                $message .= ' (local files deleted)';
            }
            
            return array(
                'type' => 'success',
                'message' => $message
            );
        }
        
        return array(
            'type' => 'error',
            'message' => "Failed to sync missing sizes for {$title}"
        );
    }
}
