<?php
/**
 * License Management Class
 *
 * All features are available.
 * without any license key. This class provides compatibility stubs.
 *
 * @package Bfpi
 * @since 0.9
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bfpi_License
 *
 * Stub class for compatibility.
 */
class Bfpi_License {

    /**
     * Get current tier
     *
     * @return string Always 'free'.
     */
    public static function get_tier() {
        return 'free';
    }

    /**
     * Check if a feature is available
     *
     * @param string $feature Feature ID.
     * @return bool Always true for known features.
     */
    public static function can( $feature ) {
        return Bfpi_Features::is_available( $feature );
    }

    /**
     * Check if license is valid
     *
     * @return bool Always false.
     */
    public static function is_valid() {
        return false;
    }

    /**
     * Render upgrade notice (no-op)
     *
     * @param string $feature Feature ID.
     */
    public static function render_upgrade_notice( $feature = '' ) {
        // No-op.
    }

    /**
     * License notice (no-op)
     */
    public static function maybe_show_license_notice() {
        // No-op – all features free.
    }
}

/**
 * Global helper: check if a feature is available
 *
 * @param string $feature Feature ID.
 * @return bool True if available.
 */
function bfpi_can( $feature ) {
    return Bfpi_Features::is_available( $feature );
}

/**
 * Global helper: get current tier
 *
 * @return string Always 'free'.
 */
function bfpi_get_tier() {
    return 'free';
}
