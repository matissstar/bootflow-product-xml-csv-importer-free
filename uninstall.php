<?php
/**
 * Plugin Uninstaller
 *
 * Fired when the plugin is deleted. This file is responsible for cleaning up
 * plugin data when the plugin is uninstalled.
 *
 * WP.org compliance: Only deletes this plugin's data with proper prefixes
 *
 * @since      1.0.0
 * @package    Bfpi
 * @subpackage Bfpi/includes
 */

// WP.org compliance: security check for uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// WP.org compliance: additional security - verify we're uninstalling THIS plugin
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall file variables are not truly global

/**
 * Clean up plugin data
 */

global $wpdb;

// Check if user wants to keep data
$keep_data = get_option('bfpi_keep_data_on_uninstall', false);

if (!$keep_data) {
    // WP.org compliance: Delete custom database tables with proper escaping
    // These tables are created by this plugin only
    $table_imports = $wpdb->prefix . 'bfpi_imports';
    $table_logs = $wpdb->prefix . 'bfpi_import_logs';
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Uninstall cleanup, %i requires WP 6.2+
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_imports));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Uninstall cleanup, %i requires WP 6.2+
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_logs));
    
    // Delete plugin options - only this plugin's options with specific prefix
    $options_to_delete = array(
        'bfpi_version',
        'bfpi_ai_settings',
        'bfpi_performance_settings',
        'bfpi_import_settings',
        'bfpi_file_settings',
        'bfpi_logging_settings',
        'bfpi_security_settings',
        'bfpi_keep_data_on_uninstall',
        'bfpi_db_version',
        'bfpi_settings'
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // WP.org compliance: Delete transients with specific plugin prefix only
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup with specific prefix
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_bfpi_%'
        )
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup with specific prefix
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_bfpi_%'
        )
    );
    
    // Delete uploaded files (if directory exists and is within uploads)
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'bootflow-product-xml-csv-importer/';
    
    // WP.org compliance: verify path is within uploads before deletion
    if (file_exists($plugin_upload_dir) && strpos(realpath($plugin_upload_dir), realpath($upload_dir['basedir'])) === 0) {
        // Recursively delete directory and contents
        bfpi_delete_directory($plugin_upload_dir);
    }
    
    // Also clean up legacy directory name (before slug rename)
    $legacy_upload_dir = trailingslashit($upload_dir['basedir']) . 'bootflow-product-importer/';
    if (file_exists($legacy_upload_dir) && strpos(realpath($legacy_upload_dir), realpath($upload_dir['basedir'])) === 0) {
        bfpi_delete_directory($legacy_upload_dir);
    }
    
    // Clear any scheduled events
    $scheduled_hooks = array(
        'bfpi_process_batch',
        'bfpi_process_chunk',
        'bfpi_cleanup_files',
        'bfpi_cleanup_logs'
    );
    
    foreach ($scheduled_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }
    
    // WP.org compliance: Delete user meta with specific plugin prefix only
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup with specific prefix
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            'bfpi_%'
        )
    );
    
    // Also delete bfpi_admin_language user meta used by language switcher
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
            'bfpi_admin_language'
        )
    );
    
    // Clear any cached data
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Recursively delete a directory
 * WP.org compliance: helper function for cleanup
 *
 * @param string $dir Directory path
 * @return bool Success
 */
function bfpi_delete_directory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return wp_delete_file($dir);
    }
    
    $items = scandir($dir);
    if ($items === false) {
        return false;
    }
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        if (!bfpi_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Required for recursive directory removal
    return rmdir($dir);
}