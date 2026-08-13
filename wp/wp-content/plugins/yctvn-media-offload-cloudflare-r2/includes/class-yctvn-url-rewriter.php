<?php
/**
 * Yctvn URL Rewriter Class
 *
 * Handles all URL rewriting for R2-hosted media
 *
 * @package Yctvn_Media_Offload
 * @since 1.0.0
 */

namespace Yctvn_Media_Offload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Yctvn_URL_Rewriter
 *
 * Manages URL rewriting for attachments, srcset, and content
 */
class Yctvn_URL_Rewriter {
    
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
     * @param array $settings Plugin settings
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
        $this->logger = Yctvn_Logger::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize rewrite hooks
     */
    private function init_hooks() {
        // URL rewrite hooks - always add, but check setting inside each function
        add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_attachment_url' ), 10, 2 );
        add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset' ), 10, 5 );
        add_filter( 'wp_get_attachment_image_src', array( $this, 'rewrite_image_src' ), 10, 3 );
        add_filter( 'wp_get_attachment_image_attributes', array( $this, 'rewrite_image_attributes' ), 10, 3 );
        add_filter( 'the_content', array( $this, 'rewrite_content_images' ), 10 );
        
        // Fix missing thumbnails in Media Library
        add_filter( 'wp_prepare_attachment_for_js', array( $this, 'fix_media_library_thumbnails' ), 10, 3 );
        add_filter( 'wp_get_attachment_image_src', array( $this, 'fix_missing_thumbnail_src' ), 999, 3 );
    }
    
    /**
     * Rewrite attachment URL to serve from R2
     *
     * @param string $url           Original URL
     * @param int    $attachment_id Attachment ID
     * @return string Modified URL
     */
    public function rewrite_attachment_url( $url, $attachment_id ) {
        // Check if URL rewrite is enabled
        if ( ! $this->settings['enable_url_rewrite'] ) {
            return $url;
        }
        
        // Check if this is a resized image URL (contains size suffix)
        $base_url = $url;
        $size_suffix = '';
        
        // Extract size suffix from URL if present (e.g., -150x150.jpg)
        if ( preg_match( '/-([0-9]+x[0-9]+)(\.[a-z]+)$/i', $url, $matches ) ) {
            $size_suffix = $matches[1];
            $base_url = str_replace( $matches[0], $matches[2], $url );
        }
        
        // First try to get main R2 URL
        $cloudflare_r2_url = get_post_meta( $attachment_id, '_r2_url', true );
        
        // If not in post meta, try database table (migration support)
        if ( empty( $cloudflare_r2_url ) ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'yctvn_media_offload_files';
            
            // Check if table exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $cloudflare_r2_url = $wpdb->get_var( $wpdb->prepare(
                    "SELECT r2_url FROM {$table_name} WHERE attachment_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $attachment_id
                ) );
            }
        }
        
        if ( ! empty( $cloudflare_r2_url ) ) {
            // If this is a sized image, modify the R2 URL accordingly
            if ( ! empty( $size_suffix ) ) {
                // Replace filename.ext with filename-WxH.ext
                $cloudflare_r2_url = preg_replace( '/(\.[a-z]+)$/i', '-' . $size_suffix . '$1', $cloudflare_r2_url );
            }
            
            // Debug info
            if ( $this->settings['enable_debug_logging'] ) {
                $this->logger->debug( "URL Rewrite - Attachment {$attachment_id}: original={$url}, cloudflare_r2_url={$cloudflare_r2_url}" );
            }
            
            return $cloudflare_r2_url;
        }
        
        return $url;
    }
    
    /**
     * Rewrite srcset for responsive images
     *
     * @param array  $sources       Array of image sources
     * @param array  $size_array    Image size array
     * @param string $image_src     Image source URL
     * @param array  $image_meta    Image metadata
     * @param int    $attachment_id Attachment ID
     * @return array Modified sources
     */
    public function rewrite_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
        if ( ! $this->settings['enable_url_rewrite'] ) {
            return $sources;
        }
        
        $cloudflare_r2_base_url = get_post_meta( $attachment_id, '_r2_url', true );
        
        if ( empty( $cloudflare_r2_base_url ) ) {
            return $sources;
        }
        
        // Get base URL without file extension
        $cloudflare_r2_base = preg_replace( '/(\.[a-z]+)$/i', '', $cloudflare_r2_base_url );
        $extension = preg_match( '/(\.[a-z]+)$/i', $cloudflare_r2_base_url, $matches ) ? $matches[1] : '';
        
        foreach ( $sources as $width => &$source ) {
            // Extract size from descriptor (e.g., image-300x200.jpg)
            if ( preg_match( '/-([0-9]+x[0-9]+)\.[a-z]+$/i', $source['url'], $matches ) ) {
                $size_suffix = $matches[1];
                $source['url'] = $cloudflare_r2_base . '-' . $size_suffix . $extension;
            } else {
                // Full size image
                $source['url'] = $cloudflare_r2_base_url;
            }
        }
        
        return $sources;
    }
    
    /**
     * Rewrite image src array
     *
     * @param array|false $image         Image data array or false
     * @param int         $attachment_id Attachment ID
     * @param string|int  $size          Image size
     * @return array|false Modified image data
     */
    public function rewrite_image_src( $image, $attachment_id, $size ) {
        if ( ! $this->settings['enable_url_rewrite'] || empty( $image ) ) {
            return $image;
        }
        
        $cloudflare_r2_url = $this->rewrite_attachment_url( $image[0], $attachment_id );
        
        if ( $cloudflare_r2_url !== $image[0] ) {
            $image[0] = $cloudflare_r2_url;
        }
        
        return $image;
    }
    
    /**
     * Rewrite image attributes for better handling
     *
     * @param array       $attr       Image attributes
     * @param \WP_Post    $attachment Attachment post object
     * @param string|int  $size       Image size
     * @return array Modified attributes
     */
    public function rewrite_image_attributes( $attr, $attachment, $size ) {
        if ( ! $this->settings['enable_url_rewrite'] ) {
            return $attr;
        }
        
        // Rewrite src
        if ( isset( $attr['src'] ) ) {
            $attr['src'] = $this->rewrite_attachment_url( $attr['src'], $attachment->ID );
        }
        
        // Rewrite srcset
        if ( isset( $attr['srcset'] ) ) {
            $attachment_id = $attachment->ID;
            $cloudflare_r2_base_url = get_post_meta( $attachment_id, '_r2_url', true );
            
            if ( ! empty( $cloudflare_r2_base_url ) ) {
                $srcset_parts = explode( ',', $attr['srcset'] );
                $new_srcset = array();
                
                foreach ( $srcset_parts as $part ) {
                    $part = trim( $part );
                    if ( preg_match( '/^(.+?)\s+(\d+w|\d+x)$/', $part, $matches ) ) {
                        $url = $matches[1];
                        $descriptor = $matches[2];
                        
                        // Replace with R2 URL
                        $new_url = $this->rewrite_attachment_url( $url, $attachment_id );
                        $new_srcset[] = $new_url . ' ' . $descriptor;
                    }
                }
                
                $attr['srcset'] = implode( ', ', $new_srcset );
            }
        }
        
        return $attr;
    }
    
    /**
     * Rewrite images in post content
     *
     * @param string $content Post content
     * @return string Modified content
     */
    public function rewrite_content_images( $content ) {
        if ( ! $this->settings['enable_url_rewrite'] ) {
            return $content;
        }
        
        // Get upload directory info
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        
        // Find all images in content
        if ( preg_match_all( '/<img[^>]+>/i', $content, $matches ) ) {
            foreach ( $matches[0] as $img_tag ) {
                // Extract src
                if ( preg_match( '/src=["\']([^"\']+)["\']/i', $img_tag, $src_match ) ) {
                    $src = $src_match[1];
                    
                    // Check if it's a local upload
                    if ( strpos( $src, $upload_url ) !== false ) {
                        // Try to get attachment ID from URL
                        $attachment_id = $this->get_attachment_id_from_url( $src );
                        
                        if ( $attachment_id ) {
                            $new_src = $this->rewrite_attachment_url( $src, $attachment_id );
                            
                            if ( $new_src !== $src ) {
                                $new_img_tag = str_replace( $src, $new_src, $img_tag );
                                
                                // Also update srcset if present
                                if ( preg_match( '/srcset=["\']([^"\']+)["\']/i', $new_img_tag, $srcset_match ) ) {
                                    $srcset = $srcset_match[1];
                                    $new_srcset = $this->rewrite_srcset_string( $srcset, $attachment_id );
                                    $new_img_tag = str_replace( $srcset, $new_srcset, $new_img_tag );
                                }
                                
                                $content = str_replace( $img_tag, $new_img_tag, $content );
                            }
                        }
                    }
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Get attachment ID from URL
     *
     * @param string $url Media URL
     * @return int|null Attachment ID or null
     */
    private function get_attachment_id_from_url( $url ) {
        global $wpdb;
        
        // Remove size suffix from URL
        $url = preg_replace( '/-\d+x\d+(?=\.[a-z]+$)/i', '', $url );
        
        // Remove domain to get path
        $upload_dir = wp_upload_dir();
        $path = str_replace( $upload_dir['baseurl'] . '/', '', $url );
        
        // Query database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $attachment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
            $path
        ) );
        
        return $attachment_id;
    }
    
    /**
     * Rewrite srcset string
     *
     * @param string $srcset        Srcset string
     * @param int    $attachment_id Attachment ID
     * @return string Modified srcset
     */
    private function rewrite_srcset_string( $srcset, $attachment_id ) {
        $cloudflare_r2_base_url = get_post_meta( $attachment_id, '_r2_url', true );
        
        if ( empty( $cloudflare_r2_base_url ) ) {
            return $srcset;
        }
        
        $parts = explode( ',', $srcset );
        $new_parts = array();
        
        foreach ( $parts as $part ) {
            $part = trim( $part );
            if ( preg_match( '/^(.+?)\s+(\d+w)$/', $part, $matches ) ) {
                $url = $matches[1];
                $descriptor = $matches[2];
                $new_url = $this->rewrite_attachment_url( $url, $attachment_id );
                $new_parts[] = $new_url . ' ' . $descriptor;
            }
        }
        
        return implode( ', ', $new_parts );
    }
    
    /**
     * Fix missing thumbnail src when local file is deleted
     *
     * @param array|false $image         Image data
     * @param int         $attachment_id Attachment ID
     * @param string|int  $size          Image size
     * @return array|false Modified image data
     */
    public function fix_missing_thumbnail_src( $image, $attachment_id, $size ) {
        // If image already found or URL rewrite disabled, return as is
        if ( ! empty( $image ) || ! $this->settings['enable_url_rewrite'] ) {
            return $image;
        }
        
        // Check if we have R2 URL for this size
        if ( $size === 'full' ) {
            $cloudflare_r2_url = get_post_meta( $attachment_id, '_r2_url', true );
        } else {
            // Try to get specific size URL
            $cloudflare_r2_url = get_post_meta( $attachment_id, '_r2_url_' . $size, true );
            
            // If not found, try to construct from main URL
            if ( empty( $cloudflare_r2_url ) ) {
                $cloudflare_r2_base_url = get_post_meta( $attachment_id, '_r2_url', true );
                if ( ! empty( $cloudflare_r2_base_url ) ) {
                    // Get image metadata
                    $metadata = wp_get_attachment_metadata( $attachment_id );
                    if ( isset( $metadata['sizes'][$size] ) ) {
                        // Replace filename with sized version
                        $sized_file = $metadata['sizes'][$size]['file'];
                        $cloudflare_r2_url = preg_replace( '/[^\/]+$/', $sized_file, $cloudflare_r2_base_url );
                    }
                }
            }
        }
        
        if ( ! empty( $cloudflare_r2_url ) ) {
            // Get dimensions from metadata
            $metadata = wp_get_attachment_metadata( $attachment_id );
            $width = 0;
            $height = 0;
            
            if ( $size === 'full' ) {
                $width = $metadata['width'] ?? 0;
                $height = $metadata['height'] ?? 0;
            } elseif ( isset( $metadata['sizes'][$size] ) ) {
                $width = $metadata['sizes'][$size]['width'];
                $height = $metadata['sizes'][$size]['height'];
            }
            
            return array( $cloudflare_r2_url, $width, $height, true );
        }
        
        return $image;
    }
    
    /**
     * Fix thumbnails in Media Library grid view
     *
     * @param array   $response   Attachment data
     * @param WP_Post $attachment Attachment object
     * @param array   $meta       Attachment metadata
     * @return array Modified response
     */
    public function fix_media_library_thumbnails( $response, $attachment, $meta ) {
        if ( ! $this->settings['enable_url_rewrite'] ) {
            return $response;
        }
        
        $attachment_id = $attachment->ID;
        
        // Check if local file exists
        $file_path = get_attached_file( $attachment_id );
        $local_missing = ! file_exists( $file_path );
        
        // Get R2 URLs
        $cloudflare_r2_url = get_post_meta( $attachment_id, '_r2_url', true );
        
        if ( ! empty( $cloudflare_r2_url ) ) {
            // Fix main URL if local is missing
            if ( $local_missing ) {
                $response['url'] = $cloudflare_r2_url;
            }
            
            // Fix sizes
            if ( isset( $response['sizes'] ) ) {
                foreach ( $response['sizes'] as $size => &$size_data ) {
                    // Check if local thumbnail exists
                    $thumb_path = str_replace( basename( $file_path ), $size_data['url'], $file_path );
                    
                    if ( $local_missing || ! file_exists( $thumb_path ) ) {
                        // Try to get R2 URL for this size
                        $size_r2_url = get_post_meta( $attachment_id, '_r2_url_' . $size, true );
                        
                        if ( empty( $size_r2_url ) && ! empty( $cloudflare_r2_url ) ) {
                            // Construct URL from base R2 URL
                            $sized_filename = basename( $size_data['url'] );
                            $size_r2_url = preg_replace( '/[^\/]+$/', $sized_filename, $cloudflare_r2_url );
                        }
                        
                        if ( ! empty( $size_r2_url ) ) {
                            $size_data['url'] = $size_r2_url;
                        }
                    }
                }
            }
            
            // Add sync status indicator
            $response['cloudflare_r2_synced'] = true;
            $response['local_deleted'] = get_post_meta( $attachment_id, '_r2_local_deleted', true ) ? true : false;
        }
        
        return $response;
    }
}
