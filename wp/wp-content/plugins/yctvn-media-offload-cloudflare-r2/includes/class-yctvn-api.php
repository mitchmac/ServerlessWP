<?php
namespace Yctvn_Media_Offload;

/**
 * Yctvn API Class
 *
 * Handles all Cloudflare R2 API operations
 *
 * @package Yctvn_Media_Offload
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load logger class
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-yctvn-logger.php';

/**
 * Class Yctvn_API
 *
 * Manages all R2 storage operations including upload, delete, and authentication
 */
class Yctvn_API {
    
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
    }
    
    /**
     * Check if R2 is configured
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->settings['account_id'] ) 
            && ! empty( $this->settings['access_key_id'] ) 
            && ! empty( $this->settings['secret_access_key'] ) 
            && ! empty( $this->settings['bucket_name'] );
    }
    
    /**
     * Get R2 endpoint URL
     *
     * @return string
     */
    public function get_endpoint() {
        if ( empty( $this->settings['account_id'] ) ) {
            return '';
        }
        return sprintf( 'https://%s.r2.cloudflarestorage.com', $this->settings['account_id'] );
    }
    
    /**
     * Test R2 connection
     *
     * @return true|\WP_Error
     */
    public function test_connection() {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'not_configured', 'R2 credentials not configured.' );
        }
        
        $endpoint = $this->get_endpoint();
        $bucket = $this->settings['bucket_name'];
        $url = $endpoint . '/' . $bucket . '/';
        
        // Try HEAD request to bucket
        $response = wp_remote_head( $url, array(
            'headers' => $this->get_auth_headers( 'HEAD', '/' . $bucket . '/', '' ),
            'timeout' => 15,
            'sslverify' => true,
            'user-agent' => 'WordPress/Yctvn-Media-Offload/1.0.0'
        ) );
        
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'R2 Test - Connection error: ' . $response->get_error_message() );
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        $this->logger->debug( 'R2 Test - Response code: ' . $code . ', Body: ' . substr( $body, 0, 200 ) );
        
        if ( $code === 200 || $code === 403 ) { // 403 is OK, just means no list permission
            return true;
        }
        
        return new \WP_Error( 'connection_failed', 'HTTP ' . $code . ': ' . $body );
    }
    
    /**
     * Upload file to R2
     *
     * @param int    $attachment_id Attachment ID
     * @param string $file_path     Local file path
     * @param string $size          Image size name
     * @return array|\WP_Error Array with key, url, size on success
     */
    public function upload_file( $attachment_id, $file_path, $size = 'full' ) {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'not_configured', 'R2 not configured' );
        }
        
        if ( ! file_exists( $file_path ) ) {
            return new \WP_Error( 'file_not_found', 'File not found: ' . $file_path );
        }
        
        $filename = basename( $file_path );
        $key = $this->generate_object_key( $attachment_id, $filename, $size );
        
        $endpoint = $this->get_endpoint();
        $bucket = $this->settings['bucket_name'];
        $url = $endpoint . '/' . $bucket . '/' . $key;
        
        $file_content = file_get_contents( $file_path );
        if ( $file_content === false ) {
            return new \WP_Error( 'file_read_error', 'Cannot read file' );
        }
        
        $mime_type = wp_check_filetype( $filename )['type'] ?: 'application/octet-stream';
        
        $path = '/' . $bucket . '/' . $key;
        
        // Get auth headers with content type for proper signing
        $auth_headers = $this->get_auth_headers( 'PUT', $path, $file_content, $mime_type );
        
        $headers = array_merge( $auth_headers, array(
            'Content-Length' => strlen( $file_content ),
        ) );
        
        // Skip debug for speed
        
        $response = wp_remote_request( $url, array(
            'method' => 'PUT',
            'headers' => $headers,
            'body' => $file_content,
            'timeout' => 60, // Increased timeout to prevent errors
            'sslverify' => false, // Skip SSL verify for speed
            'user-agent' => 'WP-Yctvn/1.0',
            'blocking' => true,
            'httpversion' => '1.1'
        ) );
        
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'R2 Upload - Upload error: ' . $response->get_error_message() );
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $this->logger->debug( 'R2 Upload - Response code: ' . $code );
        
        if ( $code === 200 ) {
            $public_url = $this->get_public_url( $key );
            
            $this->logger->info( 'R2 Upload - Successful: ' . $public_url );
            
            return array(
                'key' => $key,
                'url' => $public_url,
                'size' => strlen( $file_content )
            );
        }
        
        $error_body = wp_remote_retrieve_body( $response );
        $error_message = 'HTTP ' . $code;
        
        // Parse XML error response for better error messages
        if ( $code === 403 && strpos( $error_body, '<?xml' ) === 0 ) {
            // Suppress XML errors and handle them gracefully
            libxml_use_internal_errors( true );
            $xml = simplexml_load_string( $error_body );

            if ( $xml !== false ) {
                $error_code = (string) $xml->Code;
                $error_desc = (string) $xml->Message;

                if ( $error_code === 'AccessDenied' ) {
                    $error_message = 'Access Denied - Please check your R2 credentials have Object Write permission';
                } else {
                    $error_message = $error_code . ': ' . $error_desc;
                }
            } else {
                // XML parsing failed, use generic error
                $error_message .= ': ' . $error_body;
            }

            // Clear any XML errors
            libxml_clear_errors();
        } else {
            $error_message .= ': ' . $error_body;
        }
        
        return new \WP_Error( 'upload_failed', $error_message );
    }
    
    /**
     * Upload all image sizes - OPTIMIZED VERSION
     *
     * @param int   $attachment_id   Attachment ID
     * @param array $files_to_upload Array of size => file_path
     * @return bool Success status
     */
    public function upload_all_sizes( $attachment_id, $files_to_upload ) {
        // Check upload mode setting
        $upload_mode = isset( $this->settings['upload_mode'] ) ? $this->settings['upload_mode'] : 'all';
        
        // Only upload main file in fast mode
        if ( $upload_mode === 'full_only' ) {
            // Only keep full size
            $files_to_upload = array( 'full' => $files_to_upload['full'] );
        }
        
        $uploaded_files = array();
        $upload_success = true;
        
        // Quick upload - only essential files
        foreach ( $files_to_upload as $size => $file ) {
            // Skip debug logging for speed
            if ( $size !== 'full' && ! ( $this->settings['upload_all_sizes'] ?? false ) ) {
                continue; // Skip non-full sizes unless explicitly enabled
            }
            
            $result = $this->upload_file( $attachment_id, $file, $size );
            
            if ( ! is_wp_error( $result ) ) {
                if ( $size === 'full' ) {
                    update_post_meta( $attachment_id, '_r2_url', $result['url'] );
                    update_post_meta( $attachment_id, '_r2_key', $result['key'] );
                } else {
                    update_post_meta( $attachment_id, '_r2_url_' . $size, $result['url'] );
                    update_post_meta( $attachment_id, '_r2_key_' . $size, $result['key'] );
                }
                $uploaded_files[ $size ] = array(
                    'url' => $result['url'],
                    'key' => $result['key']
                );
            } else {
                if ( $size === 'full' ) {
                    $upload_success = false;
                }
                // Don't fail for thumbnail errors
            }
        }
        
        // Store uploaded info in single update
        if ( ! empty( $uploaded_files ) ) {
            update_post_meta( $attachment_id, '_r2_uploaded_files', $uploaded_files );
        }
        
        // Delete local files if enabled
        if ( $upload_success && $this->settings['delete_local_files'] ) {
            foreach ( $uploaded_files as $size => $data ) {
                $file = $files_to_upload[ $size ] ?? null;
                if ( $file && file_exists( $file ) ) {
                    wp_delete_file( $file );
                }
            }
            update_post_meta( $attachment_id, '_r2_local_deleted', true );
        }
        
        return $upload_success;
    }
    
    /**
     * Delete object from R2
     *
     * @param string $key Object key
     * @return true|\WP_Error
     */
    public function delete_file( $key ) {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'not_configured', 'R2 not configured' );
        }
        
        $endpoint = $this->get_endpoint();
        $bucket = $this->settings['bucket_name'];
        $url = $endpoint . '/' . $bucket . '/' . $key;
        
        $response = wp_remote_request( $url, array(
            'method' => 'DELETE',
            'headers' => $this->get_auth_headers( 'DELETE', '/' . $bucket . '/' . $key, '' ),
            'timeout' => 30,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 204 || $code === 404 ) { // 404 means already deleted
            return true;
        }
        
        return new \WP_Error( 'delete_failed', 'HTTP ' . $code );
    }
    
    /**
     * Generate R2 object key
     *
     * @param int    $attachment_id Attachment ID
     * @param string $filename      File name
     * @param string $size          Image size
     * @return string
     */
    private function generate_object_key( $attachment_id, $filename, $size = 'full' ) {
        $upload_date = get_the_date( 'Y/m', $attachment_id );
        $safe_filename = sanitize_file_name( $filename );
        
        // Keep original WordPress path structure for all sizes
        return $upload_date . '/' . $safe_filename;
    }
    
    /**
     * Get public URL for R2 object
     *
     * @param string $key Object key
     * @return string
     */
    public function get_public_url( $key ) {
        if ( ! empty( $this->settings['public_url'] ) ) {
            return rtrim( $this->settings['public_url'], '/' ) . '/' . $key;
        }
        
        return $this->get_endpoint() . '/' . $this->settings['bucket_name'] . '/' . $key;
    }
    
    /**
     * Generate AWS Signature V4 headers
     *
     * @param string $method       HTTP method
     * @param string $path         Request path
     * @param string $body         Request body
     * @param string $content_type Content type
     * @return array Headers array
     */
    private function get_auth_headers( $method, $path, $body = '', $content_type = '' ) {
        $service = 's3';
        $region = 'auto'; // R2 uses 'auto' region
        $access_key = $this->settings['access_key_id'];
        $secret_key = $this->settings['secret_access_key'];
        $host = wp_parse_url( $this->get_endpoint(), PHP_URL_HOST );
        
        // Ensure path has leading slash
        $canonical_uri = $path;
        if ( substr( $canonical_uri, 0, 1 ) !== '/' ) {
            $canonical_uri = '/' . $canonical_uri;
        }
        
        $timestamp = gmdate( 'Ymd\THis\Z' );
        $date = gmdate( 'Ymd' );
        
        $payload_hash = hash( 'sha256', $body );
        
        // Build headers array
        $req_headers = array(
            'Host' => $host,
            'X-Amz-Date' => $timestamp,
            'X-Amz-Content-Sha256' => $payload_hash,
        );
        
        // Add Content-Type for PUT requests
        if ( $method === 'PUT' && ! empty( $content_type ) ) {
            $req_headers['Content-Type'] = $content_type;
        }
        
        // Create canonical headers
        $canonical_headers = '';
        $signed_headers_arr = array();
        
        // Sort headers by lowercase key for canonical form
        ksort( $req_headers, SORT_STRING | SORT_FLAG_CASE );
        
        foreach ( $req_headers as $key => $value ) {
            $lower_key = strtolower( $key );
            $signed_headers_arr[] = $lower_key;
            $canonical_headers .= $lower_key . ':' . trim( $value ) . "\n";
        }
        
        $signed_headers = implode( ';', $signed_headers_arr );
        
        // Build canonical request
        $canonical_request = implode( "\n", array(
            $method,
            $canonical_uri,
            '', // Query string (empty for our use case)
            $canonical_headers,
            $signed_headers,
            $payload_hash
        ) );
        
        // Debug output
        if ( $this->settings['enable_debug_logging'] ) {
            $this->logger->debug( '[R2 Auth] Method: ' . $method );
            $this->logger->debug( '[R2 Auth] Path: ' . $canonical_uri );
            $this->logger->debug( '[R2 Auth] Signed Headers: ' . $signed_headers );
        }
        
        // Create string to sign
        $credential_scope = $date . '/' . $region . '/' . $service . '/aws4_request';
        $string_to_sign = implode( "\n", array(
            'AWS4-HMAC-SHA256',
            $timestamp,
            $credential_scope,
            hash( 'sha256', $canonical_request )
        ) );
        
        // Calculate signing key
        $date_key = hash_hmac( 'sha256', $date, 'AWS4' . $secret_key, true );
        $region_key = hash_hmac( 'sha256', $region, $date_key, true );
        $service_key = hash_hmac( 'sha256', $service, $region_key, true );
        $signing_key = hash_hmac( 'sha256', 'aws4_request', $service_key, true );
        
        // Calculate signature
        $signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );
        
        // Build authorization header
        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $access_key,
            $credential_scope,
            $signed_headers,
            $signature
        );
        
        $req_headers['Authorization'] = $authorization;
        
        return $req_headers;
    }
    
}
