<?php
/**
 * License Management Class
 *
 * Compatibility stubs — all features are available without a license key.
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
     * Check if a feature is available
     *
     * @param string $feature Feature ID.
     * @return bool True if the feature exists.
     */
    public static function can( $feature ) {
        return Bfpi_Features::is_available( $feature );
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
