<?php
/**
 * Features Management Class — FREE Version
 *
 * Only FREE features are listed. PRO features are not available.
 *
 * @package Bfpi
 * @since 0.9
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bfpi_Features
 *
 * Defines which features are available in the FREE version.
 */
class Bfpi_Features {

    /**
     * Feature definitions — FREE version only
     *
     * @var array Feature ID => edition
     */
    const FEATURES = array(
        'variable_products'     => 'free',
        'attributes_automation' => 'free',
        'import_filters'        => 'free',
        'price_formulas'        => 'free',
        'rule_engine'           => 'free',
        'batch_optimization'    => 'free',
        'large_feed_support'    => 'free',
        'custom_meta_fields'    => 'free',
        'simple_products'       => 'free',
        'basic_mapping'         => 'free',
        'manual_import'         => 'free',
        'file_upload'           => 'free',
        'basic_fields'          => 'free',
        'categories_tags'       => 'free',
    );

    /**
     * Check if a specific feature is available
     *
     * @param string $feature Feature ID to check.
     * @return bool True if available in FREE.
     */
    public static function is_available( $feature ) {
        return isset( self::FEATURES[ $feature ] );
    }

    /**
     * Get current edition
     *
     * @return string Always 'free'.
     */
    public static function get_edition() {
        return 'free';
    }

    /**
     * Check if Pro edition is active
     *
     * @return bool Always false.
     */
    public static function is_pro() {
        return false;
    }

    /**
     * Check if this is the extended plugin
     *
     * @return bool Always false.
     */
    public static function is_pro_plugin() {
        return false;
    }

    /**
     * Get list of extended features
     *
     * @return array Empty array.
     */
    public static function get_pro_features() {
        return array();
    }

    /**
     * Get list of all free features
     *
     * @return array Feature IDs.
     */
    public static function get_free_features() {
        return array_keys( self::FEATURES );
    }

    /**
     * Clear cached edition (no-op)
     */
    public static function clear_cache() {
        // No-op.
    }

    /**
     * Output badge HTML (no-op)
     *
     * @param string $feature Optional feature ID.
     * @param bool   $clickable Whether badge should be clickable.
     * @return string Empty string.
     */
    public static function pro_badge( $feature = '', $clickable = true ) {
        return '';
    }

    /**
     * Check feature availability
     *
     * @param string $feature    Feature ID.
     * @param bool   $echo_badge Ignored.
     * @return bool True if feature is known.
     */
    public static function check( $feature, $echo_badge = false ) {
        return self::is_available( $feature );
    }
}
