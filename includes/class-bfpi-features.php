<?php
/**
 * Features Management Class
 *
 * Defines which features are available in the plugin.
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
     * Get list of all available features
     *
     * @return array Feature IDs.
     */
    public static function get_features() {
        return array_keys( self::FEATURES );
    }

    /**
     * Check feature availability
     *
     * @param string $feature    Feature ID.
     * @return bool True if feature is known.
     */
    public static function check( $feature ) {
        return self::is_available( $feature );
    }
}
