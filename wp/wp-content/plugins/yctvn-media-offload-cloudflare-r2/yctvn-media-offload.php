<?php
/**
 * Plugin Name: Yctvn Media Offload for Cloudflare R2
 * Description: Offload WordPress media files to Cloudflare R2 Storage
 * Version: 1.0.2
 * Author: kangta911
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yctvn-media-offload-cloudflare-r2
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants with proper prefix
define( 'YCTVN_MEDIA_OFFLOAD_VERSION', '1.0.2' );
define( 'YCTVN_MEDIA_OFFLOAD_FILE', __FILE__ );
define( 'YCTVN_MEDIA_OFFLOAD_URL', plugin_dir_url( __FILE__ ) );
define( 'YCTVN_MEDIA_OFFLOAD_PATH', plugin_dir_path( __FILE__ ) );
define( 'YCTVN_MEDIA_OFFLOAD_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class - Bootstrap
 */
class Yctvn_Media_Offload {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     */
    private $settings = array();
    
    /**
     * Component instances
     */
    private $api = null;
    private $url_rewriter = null;
    private $bulk_sync = null;
    private $admin = null;
    private $logger = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->load_settings();
        
        // Delay initialization to 'init' hook to avoid textdomain issues
        add_action( 'init', array( $this, 'init' ) );
    }
    
    /**
     * Initialize plugin on init hook
     */
    public function init() {
        // Textdomain is now automatically loaded by WordPress for plugins hosted on wordpress.org
        // No need to call load_plugin_textdomain() as of WordPress 4.6+
        
        // Initialize components and hooks
        $this->init_components();
        $this->init_hooks();
        $this->migrate_existing_data();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Load all class files in correct order
        require_once YCTVN_MEDIA_OFFLOAD_PATH . 'includes/class-yctvn-logger.php';
        require_once YCTVN_MEDIA_OFFLOAD_PATH . 'includes/class-yctvn-api.php';
        require_once YCTVN_MEDIA_OFFLOAD_PATH . 'includes/class-yctvn-url-rewriter.php';
        require_once YCTVN_MEDIA_OFFLOAD_PATH . 'includes/class-yctvn-bulk-sync.php';
        require_once YCTVN_MEDIA_OFFLOAD_PATH . 'includes/class-yctvn-admin.php';
        require_once YCTVN_MEDIA_OFFLOAD_PATH . 'includes/class-yctvn-fix-thumbnails.php';
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $defaults = array(
            'account_id'           => '',
            'access_key_id'        => '',
            'secret_access_key'    => '',
            'bucket_name'          => '',
            'public_url'           => '',
            'auto_offload'         => false,
            'enable_url_rewrite'   => false,
            'delete_local_files'   => false,
            'auto_fix_thumbnails'  => false,
            'enable_debug_logging' => false,
        );
        
        $this->settings = wp_parse_args(
            get_option( 'yctvn_media_offload_settings', array() ),
            $defaults
        );
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize Logger
        $this->logger = \Yctvn_Media_Offload\Yctvn_Logger::get_instance();

        // Initialize API handler
        $this->api = new \Yctvn_Media_Offload\Yctvn_API( $this->settings );

        // Initialize URL rewriter (it registers its own hooks)
        $this->url_rewriter = new \Yctvn_Media_Offload\Yctvn_URL_Rewriter( $this->settings );

        // Initialize bulk sync handler
        $this->bulk_sync = new \Yctvn_Media_Offload\Yctvn_Bulk_Sync( $this->api, $this->settings );

        // Initialize admin interface
        $this->admin = new \Yctvn_Media_Offload\Yctvn_Admin( $this->settings, $this->api, $this->bulk_sync );
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Auto offload hooks
        if ( $this->settings['auto_offload'] ) {
            add_filter( 'wp_generate_attachment_metadata', array( $this, 'auto_offload_with_metadata' ), 999, 2 );
            add_filter( 'wp_update_attachment_metadata', array( $this, 'auto_offload_on_update' ), 999, 2 );
        }
        
        // Delete attachment hook
        add_action( 'delete_attachment', array( $this, 'delete_from_r2' ) );
        
        // Settings reload hook
        add_action( 'yctvn_media_offload_settings_updated', array( $this, 'reload_settings' ) );

        // Setup cron for auto sync
        $this->setup_cron();

        // Hook for cron execution
        add_action( 'yctvn_media_offload_auto_sync_cron', array( $this, 'run_auto_sync' ) );
    }
    
    /**
     * Auto offload on metadata generation
     */
    public function auto_offload_with_metadata( $metadata, $attachment_id ) {
        // Check if it's an image
        $mime_type = get_post_mime_type( $attachment_id );
        if ( strpos( $mime_type, 'image/' ) !== 0 ) {
            return $metadata;
        }
        
        // Skip thumbnail generation if fast mode enabled
        if ( ( $this->settings['upload_mode'] ?? 'full_only' ) === 'full_only' ) {
            // Don't regenerate thumbnails in fast mode
        } else if ( ! empty( $this->settings['auto_fix_thumbnails'] ) ) {
            // Auto fix thumbnails only if not in fast mode
            $file_path = get_attached_file( $attachment_id );
            if ( file_exists( $file_path ) ) {
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                $new_metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
                if ( ! empty( $new_metadata ) ) {
                    $metadata = $new_metadata;
                    wp_update_attachment_metadata( $attachment_id, $metadata );
                }
            }
        }
        
        if ( $this->settings['enable_debug_logging'] ) {
            $this->logger->debug( "Auto offload with metadata for image {$attachment_id}" );
        }
        
        if ( ! $this->api->is_configured() ) {
            if ( $this->settings['enable_debug_logging'] ) {
                $this->logger->error( "Cloudflare R2 not configured" );
            }
            return $metadata;
        }
        
        // Increase execution time only for actual upload processing
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
        @set_time_limit(300);
        
        // Process upload
        $this->process_attachment_upload( $attachment_id, $metadata );
        
        return $metadata;
    }
    
    /**
     * Auto offload on metadata update
     */
    public function auto_offload_on_update( $metadata, $attachment_id ) {
        // Check if already uploaded
        $existing = get_post_meta( $attachment_id, '_r2_url', true );
        if ( ! empty( $existing ) ) {
            // Re-upload all sizes to ensure they're synced
            if ( $this->settings['enable_debug_logging'] ) {
                $this->logger->debug( "Re-syncing attachment {$attachment_id} on update" );
            }
            $this->process_attachment_upload( $attachment_id, $metadata );
        }
        
        return $metadata;
    }
    
    /**
     * Process attachment upload - OPTIMIZED
     */
    private function process_attachment_upload( $attachment_id, $metadata ) {
        // Get main file path
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return false;
        }
        
        // Fast mode - only upload main file
        $files_to_upload = array( 'full' => $file_path );
        
        // Only add thumbnails if in all_sizes mode
        $upload_mode = isset( $this->settings['upload_mode'] ) ? $this->settings['upload_mode'] : 'full_only';
        if ( $upload_mode === 'all_sizes' ) {
            if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
                $upload_dir = dirname( $file_path );
                
                foreach ( $metadata['sizes'] as $size => $size_data ) {
                    $size_file = $upload_dir . '/' . $size_data['file'];
                    if ( file_exists( $size_file ) ) {
                        $files_to_upload[ $size ] = $size_file;
                    }
                }
            }
        }
        
        // Upload files (API will handle based on settings)
        return $this->api->upload_all_sizes( $attachment_id, $files_to_upload );
    }
    
    /**
     * Delete from Cloudflare R2 when attachment is deleted
     */
    public function delete_from_r2( $attachment_id ) {
        $cloudflare_r2_key = get_post_meta( $attachment_id, '_r2_key', true );
        
        if ( ! empty( $cloudflare_r2_key ) ) {
            $result = $this->api->delete_file( $cloudflare_r2_key );
            
            if ( $this->settings['enable_debug_logging'] ) {
                if ( is_wp_error( $result ) ) {
                    $this->logger->error( "Delete failed for {$attachment_id}: " . $result->get_error_message() );
                } else {
                    $this->logger->info( "Deleted from Cloudflare R2: {$cloudflare_r2_key}" );
                }
            }
        }
        
        // Also delete thumbnail keys
        $sizes = get_intermediate_image_sizes();
        foreach ( $sizes as $size ) {
            $size_key = get_post_meta( $attachment_id, '_r2_key_' . $size, true );
            if ( ! empty( $size_key ) ) {
                $this->api->delete_file( $size_key );
            }
        }
    }
    
    /**
     * Setup cron for auto sync
     */
    private function setup_cron() {
        // Add custom cron intervals
        add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
        
        // Check if auto sync is enabled
        $auto_sync_enabled = get_option( 'yctvn_media_offload_auto_sync_enabled', false );
        $sync_interval = get_option( 'yctvn_media_offload_auto_sync_interval', 'hourly' );

        // Clear existing cron
        $timestamp = wp_next_scheduled( 'yctvn_media_offload_auto_sync_cron' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'yctvn_media_offload_auto_sync_cron' );
        }

        if ( $auto_sync_enabled ) {
            // Schedule with selected interval
            wp_schedule_event( time(), $sync_interval, 'yctvn_media_offload_auto_sync_cron' );
            $this->logger->info( 'Auto sync cron scheduled with interval: ' . $sync_interval );
        } else {
            $this->logger->info( 'Auto sync cron disabled' );
        }
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals( $schedules ) {
        $schedules['yctvn_every_5_minutes'] = array(
            'interval' => 300,
            'display' => esc_html__( 'Every 5 minutes', 'yctvn-media-offload-cloudflare-r2' )
        );
        $schedules['yctvn_every_15_minutes'] = array(
            'interval' => 900,
            'display' => esc_html__( 'Every 15 minutes', 'yctvn-media-offload-cloudflare-r2' )
        );
        $schedules['yctvn_every_30_minutes'] = array(
            'interval' => 1800,
            'display' => esc_html__( 'Every 30 minutes', 'yctvn-media-offload-cloudflare-r2' )
        );
        return $schedules;
    }
    
    /**
     * Run auto sync process
     */
    public function run_auto_sync() {
        if ( ! $this->api->is_configured() ) {
            $this->logger->error( 'Auto sync: Cloudflare R2 not configured' );
            return;
        }
        
        $this->logger->info( 'Starting auto sync process' );
        
        // Get batch of unsynced attachments
        $batch_size = get_option( 'yctvn_media_offload_auto_sync_batch_size', 10 );
        $attachments = $this->bulk_sync->get_unsynced_attachments( 0, $batch_size );
        
        if ( empty( $attachments ) ) {
            $this->logger->info( 'Auto sync: No unsynced attachments found' );
            return;
        }
        
        $this->logger->info( 'Auto sync: Processing ' . count( $attachments ) . ' attachments' );
        
        foreach ( $attachments as $attachment ) {
            $file_path = get_attached_file( $attachment->ID );
            if ( ! $file_path || ! file_exists( $file_path ) ) {
                continue;
            }
            
            // Get metadata
            $metadata = wp_get_attachment_metadata( $attachment->ID );
            
            // Process upload
            $result = $this->process_attachment_upload( $attachment->ID, $metadata );
            
            if ( $result ) {
                $this->logger->info( "Auto sync: Successfully synced attachment {$attachment->ID}" );
            } else {
                $this->logger->error( "Auto sync: Failed to sync attachment {$attachment->ID}" );
            }
            
            // Small delay to prevent overload
            usleep( 500000 ); // 0.5 second
        }
        
        $this->logger->info( 'Auto sync process completed' );
    }
    
    /**
     * Reload settings when updated
     */
    public function reload_settings( $new_settings ) {
        $this->settings = $new_settings;
        
        // Re-initialize logger
        $this->logger = \Yctvn_Media_Offload\Yctvn_Logger::get_instance();

        // Re-initialize components with new settings
        $this->api = new \Yctvn_Media_Offload\Yctvn_API( $this->settings );
        $this->url_rewriter = new \Yctvn_Media_Offload\Yctvn_URL_Rewriter( $this->settings );
        $this->bulk_sync = new \Yctvn_Media_Offload\Yctvn_Bulk_Sync( $this->api, $this->settings );
        
        // Re-init hooks
        remove_filter( 'wp_generate_attachment_metadata', array( $this, 'auto_offload_with_metadata' ), 999 );
        remove_filter( 'wp_update_attachment_metadata', array( $this, 'auto_offload_on_update' ), 999 );

        if ( $this->settings['auto_offload'] ) {
            add_filter( 'wp_generate_attachment_metadata', array( $this, 'auto_offload_with_metadata' ), 999, 2 );
            add_filter( 'wp_update_attachment_metadata', array( $this, 'auto_offload_on_update' ), 999, 2 );
        }

        // Re-setup cron
        $this->setup_cron();
    }

    /**
     * Migrate existing data from old table structure
     */
    private function migrate_existing_data() {
        static $migrated = false;
        if ( $migrated ) {
            return;
        }
        $migrated = true;

        global $wpdb;
        $table_name = $wpdb->prefix . 'yctvn_media_offload_files';
        
        // Check if old table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
            return;
        }

        // Get all files from table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $files = $wpdb->get_results( "SELECT * FROM {$table_name}" );
        
        if ( empty( $files ) ) {
            return;
        }
        
        // Migrate each file to post meta
        foreach ( $files as $file ) {
            $existing = get_post_meta( $file->attachment_id, '_r2_url', true );
            if ( empty( $existing ) ) {
                update_post_meta( $file->attachment_id, '_r2_url', $file->r2_url );
                update_post_meta( $file->attachment_id, '_r2_key', $file->r2_key );
                
                if ( $this->settings['enable_debug_logging'] ) {
                    $this->logger->info( "Migrated attachment {$file->attachment_id} from table to post meta" );
                }
            }
        }
    }
}

// Activation hook
register_activation_hook( __FILE__, 'yctvn_media_offload_activate' );
function yctvn_media_offload_activate() {
    // Create default options
    $default_options = array(
        'account_id'           => '',
        'access_key_id'        => '',
        'secret_access_key'    => '',
        'bucket_name'          => '',
        'public_url'           => '',
        'auto_offload'         => false,
        'enable_url_rewrite'   => false,
        'delete_local_files'   => false,
        'enable_debug_logging' => false,
    );

    add_option( 'yctvn_media_offload_settings', $default_options );
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'yctvn_media_offload_deactivate' );
function yctvn_media_offload_deactivate() {
    // Clear auto sync cron
    $timestamp = wp_next_scheduled( 'yctvn_media_offload_auto_sync_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'yctvn_media_offload_auto_sync_cron' );
    }
}

// Initialize plugin
add_action( 'plugins_loaded', 'yctvn_media_offload_init' );
function yctvn_media_offload_init() {
    Yctvn_Media_Offload::get_instance();
}

// Include test script in development/debug mode
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    $test_file = YCTVN_MEDIA_OFFLOAD_PATH . 'test-plugin.php';
    $debug_file = YCTVN_MEDIA_OFFLOAD_PATH . 'debug-check.php';
    
    if ( file_exists( $test_file ) ) {
        require_once $test_file;
    }
    if ( file_exists( $debug_file ) ) {
        require_once $debug_file;
    }
}
