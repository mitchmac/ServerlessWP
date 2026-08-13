<?php
/**
 * Yctvn Fix Thumbnails Class
 *
 * Handles thumbnail fixing and regeneration
 *
 * @package Yctvn_Media_Offload
 * @since 1.0.0
 */

namespace Yctvn_Media_Offload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Yctvn_Fix_Thumbnails
 */
class Yctvn_Fix_Thumbnails {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Yctvn_Logger::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add AJAX handlers
        add_action( 'wp_ajax_yctvn_media_offload_fix_single_thumbnail', array( $this, 'ajax_fix_single' ) );
        add_action( 'wp_ajax_yctvn_media_offload_fix_batch_thumbnails', array( $this, 'ajax_fix_batch' ) );
        
        // Add to attachment row actions in Media Library
        add_filter( 'media_row_actions', array( $this, 'add_fix_action' ), 10, 2 );
    }
    
    /**
     * Add fix action to media row
     */
    public function add_fix_action( $actions, $post ) {
        if ( wp_attachment_is_image( $post->ID ) ) {
            $actions['yctvn_media_offload_fix'] = sprintf(
                '<a href="#" onclick="yctvnMediaOffloadFixThumbnail(%d); return false;">%s</a>',
                $post->ID,
                __( 'Fix Thumbnails', 'yctvn-media-offload-cloudflare-r2' )
            );
        }
        return $actions;
    }
    
    /**
     * Fix thumbnails for single attachment
     */
    public function fix_single_thumbnail( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file_path ) ) {
            $this->logger->error( "File not found for attachment {$attachment_id}: {$file_path}" );
            return false;
        }
        
        // Check if it's an image
        $mime_type = get_post_mime_type( $attachment_id );
        if ( strpos( $mime_type, 'image/' ) !== 0 ) {
            $this->logger->info( "Attachment {$attachment_id} is not an image" );
            return false;
        }
        
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        
        // Delete old thumbnails first
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( $metadata && ! empty( $metadata['sizes'] ) ) {
            $upload_dir = dirname( $file_path );
            foreach ( $metadata['sizes'] as $size => $size_data ) {
                $thumb_path = $upload_dir . '/' . $size_data['file'];
                if ( file_exists( $thumb_path ) ) {
                    wp_delete_file( $thumb_path );
                    $this->logger->debug( "Deleted old thumbnail: {$thumb_path}" );
                }
            }
        }
        
        // Generate new thumbnails
        $new_metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
        
        if ( ! empty( $new_metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $new_metadata );
            $this->logger->info( "Fixed thumbnails for attachment {$attachment_id}" );
            
            // Count new sizes
            $sizes_count = isset( $new_metadata['sizes'] ) ? count( $new_metadata['sizes'] ) : 0;
            $this->logger->info( "Generated {$sizes_count} thumbnail sizes" );
            
            return true;
        }
        
        $this->logger->error( "Failed to generate metadata for attachment {$attachment_id}" );
        return false;
    }
    
    /**
     * AJAX handler for single fix
     */
    public function ajax_fix_single() {
        check_ajax_referer( 'yctvn_media_offload_fix_thumbnail', 'nonce' );
        
        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        
        if ( ! $attachment_id ) {
            wp_send_json_error( 'Invalid attachment ID' );
        }
        
        if ( $this->fix_single_thumbnail( $attachment_id ) ) {
            $metadata = wp_get_attachment_metadata( $attachment_id );
            $sizes_count = isset( $metadata['sizes'] ) ? count( $metadata['sizes'] ) : 0;
            
            wp_send_json_success( array(
                'message' => sprintf( 'Fixed! Generated %d sizes', $sizes_count ),
                'sizes' => $sizes_count
            ) );
        } else {
            wp_send_json_error( 'Failed to fix thumbnails' );
        }
    }
    
    /**
     * AJAX handler for batch fix
     */
    public function ajax_fix_batch() {
        check_ajax_referer( 'yctvn_media_offload_fix_batch', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        global $wpdb;
        
        $limit = intval( $_POST['limit'] ?? 10 );
        $offset = intval( $_POST['offset'] ?? 0 );
        
        // Get image attachments
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $attachments = $wpdb->get_results( $wpdb->prepare( "
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE %s
            ORDER BY ID DESC
            LIMIT %d OFFSET %d
        ", 'image/%', $limit, $offset ) );
        
        $fixed = 0;
        $failed = 0;
        $results = array();
        
        foreach ( $attachments as $attachment ) {
            if ( $this->fix_single_thumbnail( $attachment->ID ) ) {
                $fixed++;
                $results[] = array(
                    'id' => $attachment->ID,
                    'status' => 'success'
                );
            } else {
                $failed++;
                $results[] = array(
                    'id' => $attachment->ID,
                    'status' => 'failed'
                );
            }
        }
        
        wp_send_json_success( array(
            'fixed' => $fixed,
            'failed' => $failed,
            'total' => count( $attachments ),
            'results' => $results,
            'message' => sprintf( 'Fixed %d, Failed %d', $fixed, $failed )
        ) );
    }
    
    /**
     * Get statistics about thumbnails
     */
    public function get_thumbnail_stats() {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_images = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
        " );
        
        $missing_thumbs = 0;
        $total_sizes = 0;
        
        // Sample check on recent images
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $recent_images = $wpdb->get_results( "
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
            ORDER BY ID DESC
            LIMIT 100
        " );
        
        foreach ( $recent_images as $image ) {
            $metadata = wp_get_attachment_metadata( $image->ID );
            if ( empty( $metadata['sizes'] ) ) {
                $missing_thumbs++;
            } else {
                $total_sizes += count( $metadata['sizes'] );
            }
        }
        
        return array(
            'total_images' => $total_images,
            'sample_size' => count( $recent_images ),
            'missing_thumbnails' => $missing_thumbs,
            'average_sizes' => $total_sizes / max( 1, count( $recent_images ) - $missing_thumbs )
        );
    }
}

// Initialize
new Yctvn_Fix_Thumbnails();
