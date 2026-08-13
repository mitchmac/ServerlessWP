<?php
/**
 * Yctvn Logger Class
 *
 * Handles debug logging with rotation and size limits
 *
 * @package Yctvn_Media_Offload
 * @since 1.1.0
 */

namespace Yctvn_Media_Offload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Yctvn_Logger
 *
 * Manages debug logging with automatic rotation and size limits
 */
class Yctvn_Logger {
    
    /**
     * Maximum log file size in bytes (1MB)
     */
    const MAX_LOG_SIZE = 1048576;
    
    /**
     * Maximum number of old log files to keep
     */
    const MAX_LOG_FILES = 5;
    
    /**
     * Log directory path
     *
     * @var string
     */
    private $log_dir;
    
    /**
     * Current log file path
     *
     * @var string
     */
    private $log_file;
    
    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    private $enabled;
    
    /**
     * Singleton instance
     *
     * @var Yctvn_Logger
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Yctvn_Logger
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/yctvn-media-offload-cloudflare-r2-logs';
        $this->log_file = $this->log_dir . '/debug.log';
        
        // Create log directory if needed
        if ( ! file_exists( $this->log_dir ) ) {
            wp_mkdir_p( $this->log_dir );
            
            // Add .htaccess to protect logs
            $htaccess = $this->log_dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                // Use WordPress filesystem API for better error handling
                global $wp_filesystem;
                if ( ! $wp_filesystem ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }

                if ( $wp_filesystem ) {
                    $wp_filesystem->put_contents( $htaccess, "Deny from all\n", FS_CHMOD_FILE );
                } else {
                    // Fallback to file_put_contents with error suppression
                    @file_put_contents( $htaccess, "Deny from all\n" );
                }
            }

            // Add index.php to prevent directory listing
            $index = $this->log_dir . '/index.php';
            if ( ! file_exists( $index ) ) {
                if ( $wp_filesystem ) {
                    $wp_filesystem->put_contents( $index, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
                } else {
                    // Fallback to file_put_contents with error suppression
                    @file_put_contents( $index, "<?php\n// Silence is golden.\n" );
                }
            }
        }
        
        $settings = get_option( 'yctvn_media_offload_settings', array() );
        $this->enabled = ! empty( $settings['enable_debug_logging'] );
    }
    
    /**
     * Set logging enabled state
     *
     * @param bool $enabled Whether logging is enabled
     */
    public function set_enabled( $enabled ) {
        $this->enabled = $enabled;
    }
    
    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param string $level   Log level (INFO, ERROR, WARNING, DEBUG)
     */
    public function log( $message, $level = 'INFO' ) {
        if ( ! $this->enabled ) {
            return;
        }
        
        // Check if rotation is needed
        $this->rotate_if_needed();
        
        // Format message
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $formatted = sprintf( "[%s] [%s] %s\n", $timestamp, $level, $message );
        
        // Write to log file (error_log with mode 3 writes to file, not system log)
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( $formatted, 3, $this->log_file );
    }
    
    /**
     * Log an error
     *
     * @param string $message Error message
     */
    public function error( $message ) {
        $this->log( $message, 'ERROR' );
    }
    
    /**
     * Log a warning
     *
     * @param string $message Warning message
     */
    public function warning( $message ) {
        $this->log( $message, 'WARNING' );
    }
    
    /**
     * Log debug information
     *
     * @param string $message Debug message
     */
    public function debug( $message ) {
        $this->log( $message, 'DEBUG' );
    }
    
    /**
     * Log info message
     *
     * @param string $message Info message
     */
    public function info( $message ) {
        $this->log( $message, 'INFO' );
    }
    
    /**
     * Rotate log file if it exceeds size limit
     */
    private function rotate_if_needed() {
        if ( ! file_exists( $this->log_file ) ) {
            return;
        }
        
        $size = @filesize( $this->log_file );
        if ( $size === false || $size < self::MAX_LOG_SIZE ) {
            return;
        }
        
        // Rotate logs
        $this->rotate_logs();
    }
    
    /**
     * Rotate log files
     */
    private function rotate_logs() {
        // Delete oldest log if we have max files
        $oldest = $this->log_file . '.' . self::MAX_LOG_FILES;
        if ( file_exists( $oldest ) ) {
            wp_delete_file( $oldest );
        }
        
        // Use WP_Filesystem for file operations
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // Rotate existing logs
        for ( $i = self::MAX_LOG_FILES - 1; $i >= 1; $i-- ) {
            $old_file = $this->log_file . '.' . $i;
            $new_file = $this->log_file . '.' . ( $i + 1 );

            if ( file_exists( $old_file ) ) {
                if ( $wp_filesystem ) {
                    $wp_filesystem->move( $old_file, $new_file );
                } else {
                    @rename( $old_file, $new_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Fallback for file rotation
                }
            }
        }

        // Move current log to .1
        if ( file_exists( $this->log_file ) ) {
            if ( $wp_filesystem ) {
                $wp_filesystem->move( $this->log_file, $this->log_file . '.1' );
            } else {
                @rename( $this->log_file, $this->log_file . '.1' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Fallback for file rotation
            }
        }
    }
    
    /**
     * Clear all log files
     */
    public function clear_logs() {
        // Delete main log
        if ( file_exists( $this->log_file ) ) {
            wp_delete_file( $this->log_file );
        }
        
        // Delete rotated logs
        for ( $i = 1; $i <= self::MAX_LOG_FILES; $i++ ) {
            $file = $this->log_file . '.' . $i;
            if ( file_exists( $file ) ) {
                wp_delete_file( $file );
            }
        }
    }
    
    /**
     * Get current log file content
     *
     * @param int $lines Number of lines to retrieve (0 = all)
     * @return string Log content
     */
    public function get_log_content( $lines = 100 ) {
        if ( ! file_exists( $this->log_file ) ) {
            return '';
        }
        
        if ( $lines <= 0 ) {
            // Use error suppression for file_get_contents
            $content = @file_get_contents( $this->log_file );
            return $content !== false ? $content : '';
        }
        
        // Get last N lines with error handling
        try {
            $file = new \SplFileObject( $this->log_file, 'r' );
            $file->seek( PHP_INT_MAX );
            $total_lines = $file->key();

            $start = max( 0, $total_lines - $lines );
            $content = '';

            $file->seek( $start );
            while ( ! $file->eof() ) {
                $line = $file->current();
                if ( $line !== false ) {
                    $content .= $line;
                }
                $file->next();
            }

            return $content;
        } catch ( Exception $e ) {
            // Fallback to simple file_get_contents if SplFileObject fails
            $content = @file_get_contents( $this->log_file );
            return $content !== false ? $content : '';
        }
    }
    
    /**
     * Get log file size
     *
     * @return int Size in bytes
     */
    public function get_log_size() {
        if ( ! file_exists( $this->log_file ) ) {
            return 0;
        }
        $size = @filesize( $this->log_file );
        return $size !== false ? $size : 0;
    }
    
    /**
     * Get formatted log size
     *
     * @return string Formatted size
     */
    public function get_log_size_formatted() {
        $size = $this->get_log_size();
        
        if ( $size < 1024 ) {
            return $size . ' B';
        } elseif ( $size < 1048576 ) {
            return round( $size / 1024, 2 ) . ' KB';
        } else {
            return round( $size / 1048576, 2 ) . ' MB';
        }
    }
}
