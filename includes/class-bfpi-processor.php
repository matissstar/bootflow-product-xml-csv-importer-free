<?php
/**
 * Field Processor — FREE Version
 *
 * Handles direct field processing only.
 *
 * @since      1.0.0
 * @package    Bfpi
 * @subpackage Bfpi/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field Processor class.
 */
class Bfpi_Processor {

    /**
     * Constructor.
     */
    public function __construct() {
        // No AI providers in FREE version.
    }

    /**
     * Process field value based on configuration.
     *
     * @since    1.0.0
     * @param    mixed $value Original field value
     * @param    array $config Field configuration
     * @param    array $product_data Complete product data for context
     * @return   mixed Processed value
     */
    public function process_field($value, $config, $product_data = array()) {
        try {
            $processing_mode = isset($config['processing_mode']) ? $config['processing_mode'] : 'direct';

            switch ($processing_mode) {
                case 'direct':
                    return $this->process_direct($value);


                default:
                    return $this->process_direct($value);
            }

        } catch (Exception $e) {
            return $value;
        }
    }

    /**
     * Process field with direct mapping (no transformation).
     *
     * @since    1.0.0
     * @param    mixed $value Field value
     * @return   mixed Sanitized value
     */
    private function process_direct($value) {
        if (is_array($value)) {
            return implode(', ', array_filter($value, 'is_scalar'));
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Validate and sanitize field value based on WooCommerce field type.
     *
     * @since    1.0.0
     * @param    mixed $value Field value
     * @param    string $field_type WooCommerce field type
     * @return   mixed Sanitized value
     */
    public function validate_field_value($value, $field_type) {
        switch ($field_type) {
            case 'sku':
                return preg_replace('/[^a-zA-Z0-9\-_.]/', '', (string)$value);

            case 'price':
            case 'regular_price':
            case 'sale_price':
                $price = preg_replace('/[^0-9.,]/', '', (string)$value);
                $price = str_replace(',', '.', $price);
                return is_numeric($price) ? floatval($price) : 0;

            case 'stock_quantity':
                return max(0, intval($value));

            case 'weight':
            case 'length':
            case 'width':
            case 'height':
                $dimension = preg_replace('/[^0-9.,]/', '', (string)$value);
                $dimension = str_replace(',', '.', $dimension);
                return is_numeric($dimension) ? floatval($dimension) : 0;

            case 'name':
            case 'description':
            case 'short_description':
                return wp_kses_post((string)$value);

            case 'categories':
            case 'tags':
                if (is_array($value)) {
                    return array_map('trim', $value);
                }
                return array_map('trim', explode(',', (string)$value));

            case 'images':
            case 'featured_image':
            case 'gallery_images':
                if (is_array($value)) {
                    return array_filter(array_map('esc_url_raw', $value));
                }
                return array_filter(array_map('esc_url_raw', explode(',', (string)$value)));

            case 'status':
                $valid_statuses = array('publish', 'draft', 'private');
                return in_array($value, $valid_statuses) ? $value : 'publish';

            case 'stock_status':
                $valid_statuses = array('instock', 'outofstock', 'onbackorder');
                return in_array($value, $valid_statuses) ? $value : 'instock';

            case 'tax_status':
                $valid_statuses = array('taxable', 'shipping', 'none');
                return in_array($value, $valid_statuses) ? $value : 'taxable';

            case 'manage_stock':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            default:
                return is_string($value) ? sanitize_text_field($value) : $value;
        }
    }

    /**
     * Process multiple fields in batch.
     *
     * @since    1.0.0
     * @param    array $field_values Array of field values
     * @param    array $field_configs Array of field configurations
     * @param    array $product_data Product context
     * @return   array Processed field values
     */
    public function process_fields_batch($field_values, $field_configs, $product_data = array()) {
        $processed_values = array();

        foreach ($field_values as $field_key => $value) {
            if (isset($field_configs[$field_key])) {
                $processed_values[$field_key] = $this->process_field($value, $field_configs[$field_key], $product_data);
                $processed_values[$field_key] = $this->validate_field_value($processed_values[$field_key], $field_key);
            } else {
                $processed_values[$field_key] = $this->process_direct($value);
            }
        }

        return $processed_values;
    }
}
