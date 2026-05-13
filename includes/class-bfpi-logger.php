<?php
/**
 * Logger Class
 *
 * Provides debug logging that respects WP_DEBUG setting.
 * Replaces direct error_log calls throughout the plugin.
 *
 * @package    Bfpi
 * @subpackage Bfpi/includes
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger Class
 */
class Bfpi_Logger {

    /**
     * Log a debug message (only if WP_DEBUG is enabled)
     *
     * @param string $message Message to log
     * @param string $level   Log level: debug, info, warning, error
     */
    public static function log($message, $level = 'debug') {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        // Format the message
        $prefix = '[Bootflow Import]';
        $formatted = sprintf('%s [%s] %s', $prefix, strtoupper($level), $message);

        // Log to error_log
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log($formatted); }
    }

    /**
     * Log debug message
     *
     * @param string $message Message to log
     */
    public static function debug($message) {
        self::log($message, 'debug');
    }

    /**
     * Log info message
     *
     * @param string $message Message to log
     */
    public static function info($message) {
        self::log($message, 'info');
    }

    /**
     * Log warning message
     *
     * @param string $message Message to log
     */
    public static function warning($message) {
        self::log($message, 'warning');
    }

    /**
     * Log error message (always logs, even without WP_DEBUG)
     *
     * @param string $message Message to log
     */
    public static function error($message) {
        $prefix = '[Bootflow Import]';
        $formatted = sprintf('%s [ERROR] %s', $prefix, $message);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log($formatted); }
    }

    /**
     * Log to a custom file (only if WP_DEBUG is enabled)
     *
     * @param string $filename Filename without path
     * @param string $message  Message to log
     */
    public static function log_to_file($filename, $message) {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/bootflow-product-xml-csv-importer';
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }
        $log_file = $log_dir . '/' . sanitize_file_name($filename);
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted = sprintf("[%s] %s\n", $timestamp, $message);
        
        // Use WP_Filesystem API for file writing
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $existing = '';
        if ( $wp_filesystem->exists( $log_file ) ) {
            $existing = $wp_filesystem->get_contents( $log_file );
        }
        $result = $wp_filesystem->put_contents( $log_file, $existing . $formatted, FS_CHMOD_FILE );
        
        if ($result === false) {
            self::error("Could not write to log file: $filename");
        }
    }

    /**
     * Clear a log file
     *
     * @param string $filename Filename without path
     */
    public static function clear_log_file($filename) {
        $upload_dir = wp_upload_dir();
        $log_file   = $upload_dir['basedir'] . '/bootflow-product-xml-csv-importer/' . sanitize_file_name($filename);
        if (file_exists($log_file)) {
            @wp_delete_file($log_file);
        }
    }
}

/**
 * Global helper function for logging
 *
 * @param string $message Message to log
 * @param string $level   Log level
 */
function bfpi_log($message, $level = 'debug') {
    Bfpi_Logger::log($message, $level);
}
