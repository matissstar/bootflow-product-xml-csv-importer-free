<?php
/**
 * Security and Validation Helper Class
 *
 * Provides security functions, input validation, and sanitization
 * for the XML/CSV AI Import plugin
 *
 * @since      1.0.0
 * @package    Bfpi
 * @subpackage Bfpi/includes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Bfpi_Security {

    /**
     * Initialize security measures
     */
    public static function init() {
        // Add security headers
        add_action('admin_init', array(__CLASS__, 'add_security_headers'));
        
        // Validate all AJAX requests
        add_action('wp_ajax_bfpi_upload_file', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_parse_structure', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_test_ai', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_start_import', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_get_progress', array(__CLASS__, 'validate_ajax_request'), 1);
        // NOTE: ping_cron removed from validation - it only calls spawn_cron() and needs to work with GET requests
        add_action('wp_ajax_bfpi_control_import', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_save_mapping', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_process_batch', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_kickstart', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_delete_products_batch', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_get_products_count', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_update_url', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_detect_attribute_values', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_bfpi_auto_detect_mapping', array(__CLASS__, 'validate_ajax_request'), 1);
    }

    /**
     * Add security headers
     */
    public static function add_security_headers() {
        // WP.org compliance: sanitize GET input
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (is_admin() && strpos($page, 'bfpi-import') !== false) {
            // Only add headers if they haven't been sent yet
            if (!headers_sent()) {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
            }
        }
    }

    /**
     * Validate AJAX requests
     */
    public static function validate_ajax_request() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_die(esc_html__('You must be logged in to perform this action.', 'bootflow-product-xml-csv-importer'));
        }

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'bootflow-product-xml-csv-importer'));
        }

        // WP.org compliance: sanitize nonce input
        $nonce = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        if (isset($_POST['nonce'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $nonce = sanitize_key(wp_unslash($_POST['nonce']));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        } elseif (isset($_REQUEST['nonce'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $nonce = sanitize_key(wp_unslash($_REQUEST['nonce']));
        }
        
        if (empty($nonce) || !wp_verify_nonce($nonce, 'bfpi_nonce')) {
            wp_die(esc_html__('Security check failed.', 'bootflow-product-xml-csv-importer'));
        }

        // Rate limiting
        $user_id = get_current_user_id();
        $rate_limit_key = 'bfpi_rate_limit_' . $user_id;
        $current_count = get_transient($rate_limit_key) ?: 0;
        
        if ($current_count >= 60) { // 60 requests per minute
            wp_die(esc_html__('Rate limit exceeded. Please wait before making another request.', 'bootflow-product-xml-csv-importer'));
        }
        
        set_transient($rate_limit_key, $current_count + 1, 60);
    }

    /**
     * Sanitize file upload
     */
    public static function sanitize_file_upload($file) {
        $errors = array();
        
        if (!$file || !is_array($file)) {
            $errors[] = __('No file uploaded.', 'bootflow-product-xml-csv-importer');
            return array('file' => null, 'errors' => $errors);
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = __('File is too large.', 'bootflow-product-xml-csv-importer');
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = __('File upload was incomplete.', 'bootflow-product-xml-csv-importer');
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                case UPLOAD_ERR_CANT_WRITE:
                    $errors[] = __('Server error during upload.', 'bootflow-product-xml-csv-importer');
                    break;
                default:
                    $errors[] = __('Unknown upload error.', 'bootflow-product-xml-csv-importer');
                    break;
            }
            return array('file' => null, 'errors' => $errors);
        }

        // Validate file name
        $filename = sanitize_file_name($file['name']);
        if (empty($filename)) {
            $errors[] = __('Invalid file name.', 'bootflow-product-xml-csv-importer');
            return array('file' => null, 'errors' => $errors);
        }

        // Check file extension
        $allowed_extensions = array('xml', 'csv');
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = __('File type not allowed. Only XML and CSV files are supported.', 'bootflow-product-xml-csv-importer');
            return array('file' => null, 'errors' => $errors);
        }

        // Check file size
        $max_size = 100 * 1024 * 1024; // 100MB
        if ($file['size'] > $max_size) {
            $errors[] = __('File is too large. Maximum size is 100MB.', 'bootflow-product-xml-csv-importer');
            return array('file' => null, 'errors' => $errors);
        }

        // Check MIME type
        $allowed_mimes = array(
            'xml' => array('application/xml', 'text/xml'),
            'csv' => array('text/csv', 'application/csv', 'text/plain')
        );

        $file_mime = mime_content_type($file['tmp_name']);
        if (!in_array($file_mime, $allowed_mimes[$file_extension])) {
            $errors[] = __('File MIME type does not match extension.', 'bootflow-product-xml-csv-importer');
            return array('file' => null, 'errors' => $errors);
        }

        // Scan for malicious content
        if (self::scan_file_for_threats($file['tmp_name'], $file_extension)) {
            $errors[] = __('File contains potentially malicious content.', 'bootflow-product-xml-csv-importer');
            return array('file' => null, 'errors' => $errors);
        }

        return array('file' => $file, 'errors' => $errors);
    }

    /**
     * Scan file for threats
     */
    private static function scan_file_for_threats($file_path, $extension) {
        $dangerous_patterns = array(
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/file_get_contents\s*\(/i',
        );

        // Read first 8KB of file for scanning
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Required for binary file scanning
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return true; // Err on the side of caution
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
        $content = fread($handle, 8192);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($handle);

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize field mapping data
     */
    public static function sanitize_field_mapping($mapping_data) {
        if (!is_array($mapping_data)) {
            return array();
        }

        $sanitized = array();
        
        foreach ($mapping_data as $source_field => $mapping) {
            $source_field = sanitize_text_field($source_field);
            
            if (!is_array($mapping)) {
                continue;
            }

            $sanitized_mapping = array(
                'target' => sanitize_text_field($mapping['target'] ?? ''),
                'mode' => sanitize_text_field($mapping['mode'] ?? 'direct'),
                'enabled' => (bool)($mapping['enabled'] ?? true)
            );

            // Validate mode
            $allowed_modes = array('direct', 'static', 'mapping');
            if (!in_array($sanitized_mapping['mode'], $allowed_modes)) {
                $sanitized_mapping['mode'] = 'direct';
            }

            // Validate target field
            $allowed_targets = array(
                'name', 'description', 'short_description', 'sku', 'price', 'sale_price',
                'stock_quantity', 'category', 'tags', 'images', 'weight', 'length',
                'width', 'height', 'status', 'visibility', 'featured'
            );
            
            if (!in_array($sanitized_mapping['target'], $allowed_targets)) {
                continue; // Skip invalid targets
            }

            $sanitized[$source_field] = $sanitized_mapping;
        }

        return $sanitized;
    }



    /**
     * Validate import settings
     */
    public static function validate_import_settings($settings) {
        if (!is_array($settings)) {
            return array();
        }

        $validated = array();
        
        // Validate import name
        $validated['name'] = sanitize_text_field($settings['name'] ?? '');
        if (empty($validated['name'])) {
            $validated['name'] = 'Import ' . gmdate('Y-m-d H:i:s');
        }

        // Validate schedule
        $allowed_schedules = array('disabled', 'once', 'hourly', 'daily', 'weekly', 'monthly');
        $validated['schedule'] = sanitize_text_field($settings['schedule'] ?? 'disabled');
        if (!in_array($validated['schedule'], $allowed_schedules)) {
            $validated['schedule'] = 'disabled';
        }

        // Validate batch size
        $validated['batch_size'] = absint($settings['batch_size'] ?? 50);
        if ($validated['batch_size'] < 1 || $validated['batch_size'] > 500) {
            $validated['batch_size'] = 50;
        }

        // Validate other boolean settings
        $validated['update_existing'] = (bool)($settings['update_existing'] ?? false);
        $validated['create_categories'] = (bool)($settings['create_categories'] ?? false);
        $validated['download_images'] = (bool)($settings['download_images'] ?? false);

        return $validated;
    }

    /**
     * Secure file path
     */
    public static function secure_file_path($path) {
        // Remove any directory traversal attempts
        $path = str_replace(array('../', '..\\', '../', '..\\'), '', $path);
        
        // Remove null bytes
        $path = str_replace(chr(0), '', $path);
        
        // Sanitize
        $path = sanitize_file_name(basename($path));
        
        return $path;
    }

    /**
     * Log security events
     */
    public static function log_security_event($event, $details = '') {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_ip' => self::get_user_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'event' => $event,
            'details' => $details,
            'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
        );

        // Log to WordPress error log
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Bfpi Security Event: ' . wp_json_encode($log_data)); }
        
        // Also log to plugin's logging system if available
        if (class_exists('Bfpi_Logger')) {
            Bfpi_Logger::log('security', $event, $details);
        }
    }

    /**
     * Get user IP address
     */
    private static function get_user_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[$key] ) );
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    }

    /**
     * Check if request is from admin area
     */
    public static function is_admin_request() {
        return is_admin() || (defined('DOING_AJAX') && DOING_AJAX);
    }

    /**
     * Validate database queries
     */
    public static function validate_db_query($table, $operation, $data = array()) {
        global $wpdb;
        
        // Ensure table name is valid
        $allowed_tables = array(
            $wpdb->prefix . 'bfpi_imports',
            $wpdb->prefix . 'bfpi_import_logs'
        );
        
        if (!in_array($table, $allowed_tables)) {
            self::log_security_event('invalid_table_access', $table);
            return false;
        }

        // Validate operation
        $allowed_operations = array('SELECT', 'INSERT', 'UPDATE', 'DELETE');
        if (!in_array(strtoupper($operation), $allowed_operations)) {
            self::log_security_event('invalid_db_operation', $operation);
            return false;
        }

        // Log potentially dangerous operations
        if (in_array(strtoupper($operation), array('DELETE', 'UPDATE')) && empty($data['where'])) {
            self::log_security_event('dangerous_db_operation', $operation . ' without WHERE clause');
            return false;
        }

        return true;
    }

    /**
     * Validate remote URL for SSRF protection
     * WP.org compliance: prevent requests to internal/private networks
     *
     * @param string $url The URL to validate
     * @return array Array with 'valid' boolean and 'error' message if invalid
     */
    public static function validate_remote_url($url) {
        // Must be a valid URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return array('valid' => false, 'error' => __('Invalid URL format.', 'bootflow-product-xml-csv-importer'));
        }

        $parsed = wp_parse_url($url);
        
        // Must use http or https
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), array('http', 'https'), true)) {
            return array('valid' => false, 'error' => __('URL must use HTTP or HTTPS protocol.', 'bootflow-product-xml-csv-importer'));
        }

        if (!isset($parsed['host']) || empty($parsed['host'])) {
            return array('valid' => false, 'error' => __('URL must contain a valid host.', 'bootflow-product-xml-csv-importer'));
        }

        $host = strtolower($parsed['host']);

        // Block localhost and loopback
        $blocked_hosts = array('localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]');
        if (in_array($host, $blocked_hosts, true)) {
            return array('valid' => false, 'error' => __('Localhost URLs are not allowed.', 'bootflow-product-xml-csv-importer'));
        }

        // Block .local domains
        if (preg_match('/\.local$/i', $host)) {
            return array('valid' => false, 'error' => __('Local domain URLs are not allowed.', 'bootflow-product-xml-csv-importer'));
        }

        // Resolve hostname to IP and check for private networks
        $ip = gethostbyname($host);
        if ($ip !== $host) { // gethostbyname returns the hostname if resolution fails
            // Block private IP ranges (SSRF protection)
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return array('valid' => false, 'error' => __('URLs pointing to private or reserved IP ranges are not allowed.', 'bootflow-product-xml-csv-importer'));
            }
        }

        return array('valid' => true, 'error' => '');
    }
}

// Initialize security measures
Bfpi_Security::init();