<?php
/**
 * Step 2: Field Mapping Interface
 *
 * @since      1.0.0
 * @package    Bfpi
 * @subpackage Bfpi/includes/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to including method

// Feature availability - all features are included
$can_variable_products = Bfpi_Features::is_available('variable_products');
$can_import_filters = Bfpi_Features::is_available('import_filters');
// Legacy compatibility
$can_selective_update = true;
$can_filters_advanced = $can_import_filters;
// Note: No product count limits - both FREE and PRO have unlimited products

// Get parameters from URL
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$import_id = isset($_GET['import_id']) ? absint(wp_unslash($_GET['import_id'])) : 0;

// Load import data from database
global $wpdb;
$table_name = $wpdb->prefix . 'bfpi_imports';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix
$import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $import_id), ARRAY_A);

if (!$import) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Import not found. Please start over.', 'bootflow-product-xml-csv-importer') . '</p></div>';
    return;
}

// Extract variables from import record
$file_path = $import['file_path'];
$file_url = $import['file_url'] ?? '';
$file_type = $import['file_type'];
$import_name = $import['name'];
$schedule_type = $import['schedule_type'] ?? 'disabled';
$product_wrapper = $import['product_wrapper'];
$update_existing = $import['update_existing'];
$skip_unchanged = $import['skip_unchanged'];
$total_products_from_session = $import['total_products'];

// Check if this is a URL source (for scheduling UI)
$is_url_source = !empty($file_url);

// Load saved mappings if exists
$saved_mappings = array();
$saved_custom_fields = array();
if (!empty($import['field_mappings'])) {
    $saved_mappings = json_decode($import['field_mappings'], true);
    if (!is_array($saved_mappings)) {
        $saved_mappings = array();
    }
}

// Extract custom fields from BOTH sources:
// 1. From field_mappings with '_custom_' prefix (new format)
foreach ($saved_mappings as $field_key => $field_data) {
    if (strpos($field_key, '_custom_') === 0 && is_array($field_data)) {
        $saved_custom_fields[] = $field_data;
    }
}

// 2. From dedicated custom_fields column (legacy format) - if no custom fields found in field_mappings
if (empty($saved_custom_fields) && !empty($import['custom_fields'])) {
    $legacy_custom_fields = json_decode($import['custom_fields'], true);
    if (is_array($legacy_custom_fields)) {
        $saved_custom_fields = $legacy_custom_fields;
    }
}

if (empty($file_path) || !file_exists($file_path)) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Invalid file path. Please start over.', 'bootflow-product-xml-csv-importer') . '</p></div>';
    return;
}

// WooCommerce target fields
$woocommerce_fields = array(
    'basic' => array(
        'title' => __('Basic Product Fields', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'sku' => array(
                'label' => __('Product Code (SKU)', 'bootflow-product-xml-csv-importer'), 
                'required' => true, 
                'type' => 'sku_with_generate',
                'description' => __('Unique product identifier. Can be mapped from file or auto-generated.', 'bootflow-product-xml-csv-importer')
            ),
            'name' => array('label' => __('Product Name', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'type' => array(
                'label' => __('Product Type', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'product_type_select',
                'options' => array(
                    'simple' => __('Simple', 'bootflow-product-xml-csv-importer'),
                    'variable' => __('Variable', 'bootflow-product-xml-csv-importer'),
                    'grouped' => __('Grouped', 'bootflow-product-xml-csv-importer'),
                    'external' => __('External/Affiliate', 'bootflow-product-xml-csv-importer'),
                ),
                'description' => __('Product type. Auto-detected if grouped_products or external_url is mapped.', 'bootflow-product-xml-csv-importer')
            ),
            'description' => array('label' => __('Description', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'short_description' => array('label' => __('Short Description', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'status' => array(
                'label' => __('Product Status', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'status_select',
                'options' => array(
                    'publish' => __('Published', 'bootflow-product-xml-csv-importer'),
                    'draft' => __('Draft', 'bootflow-product-xml-csv-importer'),
                    'pending' => __('Pending Review', 'bootflow-product-xml-csv-importer'),
                    'private' => __('Private', 'bootflow-product-xml-csv-importer'),
                )
            ),
        )
    ),
    'pricing_engine' => array(
        'title' => __('Price Markup', 'bootflow-product-xml-csv-importer'),
        'fields' => array(),
        'custom_content' => true, // Flag for custom rendering
    ),
    'pricing' => array(
        'title' => __('Pricing Fields', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'regular_price' => array('label' => __('Regular Price', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'number'),
            'sale_price' => array('label' => __('Sale Price', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'number'),
            'sale_price_dates_from' => array('label' => __('Sale Price From Date', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text', 'description' => 'Format: YYYY-MM-DD'),
            'sale_price_dates_to' => array('label' => __('Sale Price To Date', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text', 'description' => 'Format: YYYY-MM-DD'),
            'tax_status' => array(
                'label' => __('Tax Status', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'tax_status_select',
                'options' => array(
                    'taxable' => __('Taxable', 'bootflow-product-xml-csv-importer'),
                    'shipping' => __('Shipping only', 'bootflow-product-xml-csv-importer'),
                    'none' => __('None', 'bootflow-product-xml-csv-importer'),
                )
            ),
            'tax_class' => array(
                'label' => __('Tax Class', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'tax_class_select'
            ),
        )
    ),
    'inventory' => array(
        'title' => __('Inventory Fields', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'manage_stock' => array(
                'label' => __('Manage Stock', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'stock_quantity' => array('label' => __('Stock Quantity', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'number'),
            'stock_status' => array(
                'label' => __('Stock Status', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'stock_status_select',
                'options' => array(
                    'instock' => __('In stock', 'bootflow-product-xml-csv-importer'),
                    'outofstock' => __('Out of stock', 'bootflow-product-xml-csv-importer'),
                    'onbackorder' => __('On backorder', 'bootflow-product-xml-csv-importer'),
                )
            ),
            'backorders' => array(
                'label' => __('Allow Backorders', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'backorders_select',
                'options' => array(
                    'no' => __('Do not allow', 'bootflow-product-xml-csv-importer'),
                    'notify' => __('Allow, but notify customer', 'bootflow-product-xml-csv-importer'),
                    'yes' => __('Allow', 'bootflow-product-xml-csv-importer'),
                )
            ),
        )
    ),
    'physical' => array(
        'title' => __('Physical Properties', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'weight' => array('label' => __('Weight', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'number'),
            'length' => array('label' => __('Length', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'number'),
            'width' => array('label' => __('Width', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'number'),
            'height' => array('label' => __('Height', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'number'),
            'shipping_class' => array('label' => __('Shipping Class', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text', 'description' => 'Slug of shipping class (e.g., "fragile", "heavy")'),
        )
    ),
    'shipping_class_engine' => array(
        'title' => __('Shipping Class Rules', 'bootflow-product-xml-csv-importer'),
        'fields' => array(),
        'custom_content' => true, // Flag for custom rendering (like pricing_engine)
    ),
    'media' => array(
        'title' => __('Media Fields', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'images' => array(
                'label' => __('Product Images', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'textarea',
                'description' => __('Enter image URLs or use placeholders: {image} = first image, {image[1]} = first, {image[2]} = second, {image*} = all images. Separate multiple values with commas.', 'bootflow-product-xml-csv-importer')
            ),
            'featured_image' => array('label' => __('Featured Image', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
        )
    ),
    'taxonomy' => array(
        'title' => __('Categories & Tags', 'bootflow-product-xml-csv-importer'),
        'fields' => array(),
        'custom_content' => true, // Flag for custom rendering (taxonomy UI)
    ),
    'product_options' => array(
        'title' => __('Product Options', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'featured' => array(
                'label' => __('Featured Product', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'virtual' => array(
                'label' => __('Virtual Product', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'downloadable' => array(
                'label' => __('Downloadable', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'sold_individually' => array(
                'label' => __('Sold Individually', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'reviews_allowed' => array(
                'label' => __('Reviews Allowed', 'bootflow-product-xml-csv-importer'), 
                'required' => false, 
                'type' => 'boolean'
            ),
        )
    ),
    'download_settings' => array(
        'title' => __('Download Settings', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'download_limit' => array('label' => __('Download Limit', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'download_expiry' => array('label' => __('Download Expiry (days)', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
        )
    ),
    'product_identifiers' => array(
        'title' => __('Product Identifiers', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'ean' => array('label' => __('EAN', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'identifier'),
            'upc' => array('label' => __('UPC', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'identifier'),
            'isbn' => array('label' => __('ISBN', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'identifier'),
            'mpn' => array('label' => __('MPN', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'identifier'),
            'gtin' => array('label' => __('GTIN', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'identifier'),
        )
    ),
    'linked_products' => array(
        'title' => __('Linked Products', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'upsell_ids' => array('label' => __('Upsell Product IDs', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'cross_sell_ids' => array('label' => __('Cross-sell Product IDs', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'grouped_products' => array('label' => __('Grouped Products', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'parent_id' => array('label' => __('Parent Product ID', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
        )
    ),

    'advanced' => array(
        'title' => __('Advanced Fields', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'purchase_note' => array('label' => __('Purchase Note', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'menu_order' => array('label' => __('Menu Order', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'button_text' => array('label' => __('Button Text', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'external_url' => array('label' => __('External URL', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
        )
    ),
    'seo' => array(
        'title' => __('SEO Fields', 'bootflow-product-xml-csv-importer'),
        'fields' => array(
            'meta_title' => array('label' => __('Meta Title', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'meta_description' => array('label' => __('Meta Description', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
            'meta_keywords' => array('label' => __('Meta Keywords', 'bootflow-product-xml-csv-importer'), 'required' => false, 'type' => 'text'),
        )
    ),
    'attributes_variations' => array(
        'title' => __('Attributes & Variations', 'bootflow-product-xml-csv-importer'),
        'fields' => array() // Will be handled separately with custom UI
    )
);

$settings = get_option('bfpi_settings', array());
$ai_settings_global = get_option('bfpi_ai_settings', array());
$default_ai_provider = $ai_settings_global['default_provider'] ?? 'openai';
$ai_providers = array(
    'openai' => 'OpenAI GPT',
    'claude' => 'Anthropic Claude'
    // 'gemini' => 'Google Gemini',  // TODO: Enable when tested
    // 'grok' => 'xAI Grok',          // TODO: Enable when tested
    // 'copilot' => 'Microsoft Copilot' // TODO: Enable when tested
);
?>

<div class="bfpi-step bfpi-step-2">
    <div class="bfpi-layout">
        <!-- Left Sidebar - File Structure -->
        <div class="bfpi-sidebar">
            <div class="bfpi-card">
                <h3><?php esc_html_e('File Structure', 'bootflow-product-xml-csv-importer'); ?></h3>
                
                <!-- File Info Grid -->
                <div class="file-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e2e4e7;">
                    <div class="file-info-item" style="display: flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #666; font-weight: 600;">📄 <?php esc_html_e('File', 'bootflow-product-xml-csv-importer'); ?></span>
                        <span style="font-size: 12px; color: #1e1e1e; word-break: break-all;" title="<?php echo esc_attr(basename($file_path)); ?>"><?php 
                            $filename = basename($file_path);
                            echo strlen($filename) > 25 ? esc_html(substr($filename, 0, 22)) . '...' : esc_html($filename); 
                        ?></span>
                    </div>
                    <div class="file-info-item" style="display: flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #666; font-weight: 600;">📦 <?php esc_html_e('Type', 'bootflow-product-xml-csv-importer'); ?></span>
                        <span style="font-size: 12px; color: #1e1e1e;"><?php echo esc_html(strtoupper($file_type)); ?></span>
                    </div>
                    <div class="file-info-item" style="display: flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #666; font-weight: 600;">🏷️ <?php esc_html_e('Import', 'bootflow-product-xml-csv-importer'); ?></span>
                        <span style="font-size: 12px; color: #1e1e1e;" title="<?php echo esc_attr($import_name); ?>"><?php 
                            echo strlen($import_name) > 20 ? esc_html(substr($import_name, 0, 17)) . '...' : esc_html($import_name); 
                        ?></span>
                    </div>
                    <div class="file-info-item" id="total-products-info" style="display: none; flex-direction: column; gap: 2px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #666; font-weight: 600;">🛒 <?php esc_html_e('Products', 'bootflow-product-xml-csv-importer'); ?></span>
                        <span style="font-size: 12px; color: #1e1e1e; font-weight: 600;" id="total-products-count">-</span>
                    </div>
                </div>
                
                <!-- Unique Fields Info (injected by JS) -->
                <div id="fields-info-container"></div>
                
                <div id="file-structure-browser">
                    <div class="structure-loader">
                        <div class="spinner is-active"></div>
                        <p><?php esc_html_e('Loading file structure...', 'bootflow-product-xml-csv-importer'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Hidden sample data container for JS compatibility -->
            <div id="sample-data-preview" style="display: none;"></div>
        </div>

        <!-- Main Content - Field Mapping -->
        <div class="bfpi-main">
            <!-- Import Behavior Options (moved up for visibility) -->
            <div class="bfpi-card" style="margin-bottom: 20px;">
                <h2>⚙️ <?php esc_html_e('Import Behavior', 'bootflow-product-xml-csv-importer'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Update Existing Products', 'bootflow-product-xml-csv-importer'); ?></th>
                        <td>
                            <label style="display: flex; align-items: flex-start; gap: 10px;">
                                <input type="checkbox" name="update_existing" id="update_existing" value="1" <?php checked($update_existing, '1'); ?> style="margin-top: 3px;" />
                                <div>
                                    <strong><?php esc_html_e('Update products that already exist (matched by SKU)', 'bootflow-product-xml-csv-importer'); ?></strong>
                                    <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                        <?php esc_html_e('When enabled, existing products with matching SKUs will be updated instead of creating duplicates.', 'bootflow-product-xml-csv-importer'); ?>
                                    </p>
                                </div>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Skip Unchanged Products', 'bootflow-product-xml-csv-importer'); ?></th>
                        <td>
                            <label style="display: flex; align-items: flex-start; gap: 10px;">
                                <input type="checkbox" name="skip_unchanged" id="skip_unchanged" value="1" <?php checked($skip_unchanged, '1'); ?> style="margin-top: 3px;" />
                                <div>
                                    <strong><?php esc_html_e('Skip products if data unchanged', 'bootflow-product-xml-csv-importer'); ?></strong>
                                    <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                        <?php esc_html_e('Reduces import time by skipping products that haven\'t changed.', 'bootflow-product-xml-csv-importer'); ?>
                                    </p>
                                </div>
                            </label>
                        </td>
                    </tr>
                    
                    
                    
                    <!-- Batch Size -->
                    <tr>
                        <th scope="row"><?php esc_html_e('Batch Size', 'bootflow-product-xml-csv-importer'); ?></th>
                        <td>
                            <?php $batch_size = $import['batch_size'] ?? 50; ?>
                            <input type="number" name="batch_size" id="batch_size" value="<?php echo esc_attr($batch_size); ?>" min="1" max="500" style="width: 100px;">
                            <span class="description"><?php esc_html_e('Products per chunk (1-500). Higher = faster, but more memory.', 'bootflow-product-xml-csv-importer'); ?></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="bfpi-card">
                <h2><?php esc_html_e('Step 2: Field Mapping & Processing', 'bootflow-product-xml-csv-importer'); ?></h2>
                <p class="description"><?php esc_html_e('Map your file fields to WooCommerce product fields and configure processing modes.', 'bootflow-product-xml-csv-importer'); ?></p>
                
                <form id="bfpi-mapping-form" method="post">
                    <?php wp_nonce_field('bfpi_nonce', 'nonce'); ?>
                    
                    <!-- Hidden fields -->
                    <input type="hidden" name="file_path" value="<?php echo esc_attr($file_path); ?>" />
                    <input type="hidden" name="file_type" value="<?php echo esc_attr($file_type); ?>" />
                    <input type="hidden" name="product_wrapper" value="<?php echo esc_attr($product_wrapper); ?>" />
                    
                    <input type="hidden" name="import_name" value="<?php echo esc_attr($import_name); ?>" />
                    
                    <!-- Mapping Templates & Auto-detect -->
                    
                    
                    
                    
                    <!-- ═══════════════════════════════════════════════════════════════ -->
                    <!-- AI Auto-Mapping - Minimal, non-intrusive block                -->
                    <!-- ═══════════════════════════════════════════════════════════════ -->
                    
                    
                    <!-- Auto-Mapping Warning (shown after any auto-mapping) -->
                    <div id="auto-mapping-warning" style="display: none; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px 15px; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 20px;">⚠️</span>
                            <div style="flex: 1;">
                                <strong style="color: #856404;"><?php esc_html_e('Please verify mapped fields before importing.', 'bootflow-product-xml-csv-importer'); ?></strong>
                            </div>
                            <button type="button" id="btn-confirm-mapping" class="button button-small">
                                <?php esc_html_e('OK', 'bootflow-product-xml-csv-importer'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mapping-actions">
                        <button type="button" class="button button-secondary" id="clear-all-mapping">
                            <?php esc_html_e('Clear All', 'bootflow-product-xml-csv-importer'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="test-mapping">
                            <?php esc_html_e('Test Mapping', 'bootflow-product-xml-csv-importer'); ?>
                        </button>
                    </div>
                    
                    <!-- Field Mapping Sections -->
                    <div class="field-mapping-sections">
                        <?php foreach ($woocommerce_fields as $section_key => $section): ?>
                            <div class="mapping-section" data-section="<?php echo esc_attr($section_key); ?>">
                                <h3 class="section-toggle" data-target="<?php echo esc_attr($section_key); ?>">
                                    <span class="dashicons dashicons-arrow-down"></span>
                                    <?php echo esc_html($section['title']); ?>
                                    <span class="mapped-count">0/<?php echo esc_html( ($section_key === 'taxonomy') ? 3 : count($section['fields']) ); ?></span>
                                </h3>
                                
                                <div class="section-fields" id="section-<?php echo esc_attr($section_key); ?>">
                                    <?php if ($section_key === 'pricing_engine'): ?>
                                        <?php 
                                        ?>
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <!-- PRICING ENGINE - Calculate prices before mapping                 -->
                                        <!-- Pipeline: XML Base Price → Price Markup → Regular Price       -->
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <div class="pricing-engine-container" style="padding: 20px;">
                                            
                                            <!-- Enable/Disable Toggle -->
                                            <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-radius: 8px; border-left: 4px solid #ff9800;">
                                                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                                    <input type="checkbox" id="pricing_engine_enabled" name="pricing_engine_enabled" value="1" style="width: 20px; height: 20px;">
                                                    <span>
                                                        <strong style="font-size: 15px; color: #e65100;"><?php esc_html_e('Enable Price Markup', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                        <small style="display: block; color: #bf360c; margin-top: 3px;">
                                                            <?php esc_html_e('Calculate final prices by applying markup rules to the base price from XML', 'bootflow-product-xml-csv-importer'); ?>
                                                        </small>
                                                    </span>
                                                </label>
                                            </div>
                                            
                                            <!-- Price Markup Settings (shown when enabled) -->
                                            <div id="pricing-engine-settings" style="display: none;">
                                                
                                                <!-- Info Box -->
                                                <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
                                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                                        <span style="font-size: 24px;">💡</span>
                                                        <div>
                                                            <strong style="color: #1565c0;"><?php esc_html_e('How it works:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #1976d2; font-size: 13px; line-height: 1.6;">
                                                                <?php esc_html_e('XML Base Price → Apply Matching Rules → Apply Rounding → Final Price', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                <?php esc_html_e('Rules are evaluated from top to bottom. First matching rule wins (or use "Apply All" mode).', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Base Price Source -->
                                                <div style="margin-bottom: 20px; padding: 20px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px;">
                                                    <label style="font-weight: 600; display: block; margin-bottom: 12px; color: #333; font-size: 14px;">
                                                        <span class="dashicons dashicons-tag" style="color: #ff9800;"></span>
                                                        <?php esc_html_e('Base Price Source (from XML):', 'bootflow-product-xml-csv-importer'); ?>
                                                    </label>
                                                    <select id="pricing_engine_base_price" name="pricing_engine_base_price" class="bfpi-field-select" style="width: 100%; max-width: 400px; padding: 10px;">
                                                        <option value=""><?php esc_html_e('-- Select XML field with base price --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                    </select>
                                                    <p class="description" style="margin-top: 8px; color: #666;">
                                                        <?php esc_html_e('Select the XML field that contains the supplier/wholesale price', 'bootflow-product-xml-csv-importer'); ?>
                                                    </p>
                                                </div>
                                                
                                                <!-- ═══════════════════════════════════════════════════════════════ -->
                                                <!-- PRICING RULES - Multiple conditional rules                      -->
                                                <!-- ═══════════════════════════════════════════════════════════════ -->
                                                <div style="margin-bottom: 20px;">
                                                    
                                                    <!-- Rule Priority Info Box -->
                                                    <div style="margin-bottom: 15px; padding: 12px 15px; background: #fff8e1; border-radius: 6px; border-left: 4px solid #ffc107; display: flex; align-items: center; gap: 10px;">
                                                        <span style="font-size: 18px;">⚠️</span>
                                                        <div style="font-size: 13px; color: #795548;">
                                                            <strong><?php esc_html_e('Rule Priority:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <?php esc_html_e('Rules are evaluated top to bottom. First matching rule applies. Avoid overlapping conditions.', 'bootflow-product-xml-csv-importer'); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0;">
                                                        <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                                            <span class="dashicons dashicons-chart-line" style="color: #4caf50;"></span>
                                                            <?php esc_html_e('Pricing Rules', 'bootflow-product-xml-csv-importer'); ?>
                                                            <span style="font-size: 12px; font-weight: normal; color: #666; margin-left: 10px;">
                                                                <?php esc_html_e('(First matching rule wins)', 'bootflow-product-xml-csv-importer'); ?>
                                                            </span>
                                                        </h4>
                                                        <button type="button" id="btn-add-pricing-rule" class="button button-primary" style="display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                            <?php esc_html_e('Add Rule', 'bootflow-product-xml-csv-importer'); ?>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Rules Container -->
                                                    <div id="pricing-rules-list">
                                                        
                                                        <!-- Default/Fallback Rule (always present) -->
                                                        <div class="pricing-rule-row pricing-rule-default" data-rule-id="default" style="padding: 20px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #4caf50; border-radius: 8px; margin-bottom: 15px;">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <span style="font-size: 20px;">🏠</span>
                                                                    <strong style="color: #2e7d32; font-size: 14px;"><?php esc_html_e('Default Rule (Fallback)', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                    <span style="font-size: 11px; background: #4caf50; color: white; padding: 2px 8px; border-radius: 10px;">
                                                                        <?php esc_html_e('Always applies if no other rule matches', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Markup %', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[default][markup_percent]" value="0" min="-100" max="10000" step="0.01"
                                                                           style="width: 80px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('+ Fixed €', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[default][fixed_amount]" value="0" min="-10000" max="10000" step="0.01"
                                                                           style="width: 80px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Round to', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <select name="pricing_rule[default][rounding]" style="padding: 8px; border: 2px solid #81c784; border-radius: 4px; min-width: 130px;">
                                                                        <option value="none"><?php esc_html_e('No rounding', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="0.01"><?php esc_html_e('0.01', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="0.05"><?php esc_html_e('0.05', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="0.10"><?php esc_html_e('0.10', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="0.50"><?php esc_html_e('0.50', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="1.00"><?php esc_html_e('1.00', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="0.99"><?php esc_html_e('.99', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="0.95"><?php esc_html_e('.95', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Min Price €', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[default][min_price]" value="" min="0" step="0.01" placeholder="—"
                                                                           style="width: 70px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Max Price €', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[default][max_price]" value="" min="0" step="0.01" placeholder="—"
                                                                           style="width: 70px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Dynamic rules will be added here -->
                                                        
                                                    </div>
                                                    
                                                    <!-- Rule Template (hidden, cloned by JS) -->
                                                    <template id="pricing-rule-template">
                                                        <div class="pricing-rule-row pricing-rule-conditional" data-rule-id="" style="padding: 20px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; transition: all 0.2s;">
                                                            
                                                            <!-- Rule Header -->
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <span class="rule-drag-handle" style="cursor: move; color: #999; font-size: 18px;">⋮⋮</span>
                                                                    <span class="rule-number" style="background: #ff9800; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                                                    <input type="text" name="pricing_rule[{id}][name]" placeholder="<?php esc_html_e('Rule name (optional)', 'bootflow-product-xml-csv-importer'); ?>" 
                                                                           style="border: none; border-bottom: 1px dashed #ccc; padding: 5px; font-weight: 500; width: 200px;">
                                                                </div>
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <label style="display: flex; align-items: center; gap: 5px; font-size: 12px; color: #666;">
                                                                        <input type="checkbox" name="pricing_rule[{id}][enabled]" checked>
                                                                        <?php esc_html_e('Enabled', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <button type="button" class="button-link remove-pricing-rule" style="color: #d63638;">
                                                                        <span class="dashicons dashicons-trash"></span>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Conditions -->
                                                            <div class="rule-conditions" style="margin-bottom: 15px; padding: 15px; background: #fafafa; border-radius: 6px;">
                                                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                                    <strong style="font-size: 13px; color: #333;">
                                                                        <span class="dashicons dashicons-filter" style="color: #2196f3;"></span>
                                                                        <?php esc_html_e('Apply when:', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </strong>
                                                                    <select name="pricing_rule[{id}][condition_logic]" style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                                        <option value="AND"><?php esc_html_e('ALL conditions match', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="OR"><?php esc_html_e('ANY condition matches', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="conditions-list" style="display: flex; flex-direction: column; gap: 8px;">
                                                                    <!-- Condition row template -->
                                                                    <div class="condition-row" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                                                        <select name="pricing_rule[{id}][conditions][0][type]" class="condition-type" style="padding: 6px; border-radius: 4px; min-width: 140px;">
                                                                            <option value="price_range"><?php esc_html_e('Price Range', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="category"><?php esc_html_e('📁 Category', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="brand"><?php esc_html_e('🏷️ Brand', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="supplier"><?php esc_html_e('🏭 Supplier', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="xml_field"><?php esc_html_e('📄 XML Field', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="sku_pattern"><?php esc_html_e('🔢 SKU Pattern', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        </select>
                                                                        
                                                                        <!-- Price Range condition fields -->
                                                                        <div class="condition-fields condition-price_range" style="display: flex; gap: 8px; align-items: center;">
                                                                            <span style="color: #666;"><?php esc_html_e('from', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                            <input type="number" name="pricing_rule[{id}][conditions][0][price_from]" placeholder="0" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #666;"><?php esc_html_e('to', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                            <input type="number" name="pricing_rule[{id}][conditions][0][price_to]" placeholder="∞" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #888; font-size: 11px;">€</span>
                                                                        </div>
                                                                        
                                                                        <!-- Category condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-category" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][category_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="not_equals"><?php esc_html_e('not equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][category_value]" placeholder="<?php esc_html_e('Category name or slug', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- Brand condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-brand" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][brand_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="not_equals"><?php esc_html_e('not equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][brand_value]" placeholder="<?php esc_html_e('Brand name', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- Supplier condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-supplier" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][supplier_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][supplier_value]" placeholder="<?php esc_html_e('Supplier name', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- XML Field condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-xml_field" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][xml_field_name]" class="xml-field-select" style="padding: 6px; border-radius: 4px; min-width: 120px;">
                                                                                <option value=""><?php esc_html_e('-- Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <select name="pricing_rule[{id}][conditions][0][xml_field_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals">=</option>
                                                                                <option value="not_equals">≠</option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="gt">></option>
                                                                                <option value="lt"><</option>
                                                                                <option value="gte">≥</option>
                                                                                <option value="lte">≤</option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][xml_field_value]" placeholder="<?php esc_html_e('Value', 'bootflow-product-xml-csv-importer'); ?>" style="width: 150px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- SKU Pattern condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-sku_pattern" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][sku_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="starts_with"><?php esc_html_e('starts with', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="ends_with"><?php esc_html_e('ends with', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="regex"><?php esc_html_e('matches regex', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][sku_value]" placeholder="<?php esc_html_e('Pattern', 'bootflow-product-xml-csv-importer'); ?>" style="width: 150px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <button type="button" class="button-link remove-condition" style="color: #999; padding: 5px;" title="<?php esc_html_e('Remove condition', 'bootflow-product-xml-csv-importer'); ?>">✕</button>
                                                                    </div>
                                                                </div>
                                                                
                                                                <button type="button" class="button button-small add-condition" style="margin-top: 10px;">
                                                                    <span class="dashicons dashicons-plus" style="font-size: 14px; line-height: 1.4;"></span>
                                                                    <?php esc_html_e('Add Condition', 'bootflow-product-xml-csv-importer'); ?>
                                                                </button>
                                                            </div>
                                                            
                                                            <!-- Pricing Values -->
                                                            <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Markup %', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[{id}][markup_percent]" value="0" min="-100" max="10000" step="0.01"
                                                                           style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('+ Fixed €', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[{id}][fixed_amount]" value="0" min="-10000" max="10000" step="0.01"
                                                                           style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Round to', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <select name="pricing_rule[{id}][rounding]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 130px;">
                                                                        <option value="inherit"><?php esc_html_e('Use default', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="none"><?php esc_html_e('No rounding', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="0.01">0.01</option>
                                                                        <option value="0.05">0.05</option>
                                                                        <option value="0.10">0.10</option>
                                                                        <option value="0.50">0.50</option>
                                                                        <option value="1.00">1.00</option>
                                                                        <option value="0.99">.99</option>
                                                                        <option value="0.95">.95</option>
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Min €', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[{id}][min_price]" value="" min="0" step="0.01" placeholder="—"
                                                                           style="width: 70px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Max €', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[{id}][max_price]" value="" min="0" step="0.01" placeholder="—"
                                                                           style="width: 70px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                </div>
                                                            </div>
                                                            
                                                        </div>
                                                    </template>
                                                    
                                                </div>
                                                
                                                <!-- Live Preview Calculator -->
                                                <div style="padding: 20px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 8px; border: 2px solid #4caf50; margin-top: 20px;">
                                                    <h4 style="margin: 0 0 15px 0; color: #2e7d32; display: flex; align-items: center; gap: 8px;">
                                                        <span class="dashicons dashicons-calculator" style="color: #4caf50;"></span>
                                                        <?php esc_html_e('Live Preview', 'bootflow-product-xml-csv-importer'); ?>
                                                    </h4>
                                                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                <?php esc_html_e('Test with Rule:', 'bootflow-product-xml-csv-importer'); ?>
                                                            </label>
                                                            <select id="pricing_engine_test_rule" style="padding: 8px 12px; border: 2px solid #81c784; border-radius: 4px; font-size: 14px; min-width: 150px;">
                                                                <option value="default"><?php esc_html_e('Default Rule', 'bootflow-product-xml-csv-importer'); ?></option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                <?php esc_html_e('Test Base Price:', 'bootflow-product-xml-csv-importer'); ?>
                                                            </label>
                                                            <input type="number" id="pricing_engine_test_input" value="100" step="0.01" min="0"
                                                                   style="width: 120px; padding: 8px; border: 2px solid #81c784; border-radius: 4px; font-size: 16px; font-weight: 600;">
                                                        </div>
                                                        <div style="font-size: 24px; color: #4caf50;">→</div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                <?php esc_html_e('Final Price:', 'bootflow-product-xml-csv-importer'); ?>
                                                            </label>
                                                            <div id="pricing_engine_test_output" style="padding: 8px 15px; background: #fff; border: 2px solid #4caf50; border-radius: 4px; font-size: 18px; font-weight: 700; color: #2e7d32; min-width: 100px;">
                                                                €100.00
                                                            </div>
                                                        </div>
                                                        <div style="margin-left: 10px; padding: 8px 12px; background: #fff; border-radius: 4px; font-size: 12px; color: #666;">
                                                            <span id="pricing_engine_matched_rule" style="color: #4caf50; font-weight: 600;"><?php esc_html_e('Default Rule', 'bootflow-product-xml-csv-importer'); ?></span><br>
                                                            <span id="pricing_engine_formula">100 × 1.00 + 0 = 100.00</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                            </div>
                                            
                                            <!-- Disabled State Message -->
                                            <div id="pricing-engine-disabled-msg" style="padding: 25px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                                                <span style="font-size: 36px; opacity: 0.5;">⚡</span>
                                                <p style="margin: 10px 0 0 0; color: #999;">
                                                    <?php esc_html_e('Enable the Price Markup above to configure automatic price calculations', 'bootflow-product-xml-csv-importer'); ?>
                                                </p>
                                            </div>
                                            
                                        </div>
                                    <?php elseif ($section_key === 'shipping_class_engine'): ?>
                                        <?php 
                                        // Get existing WooCommerce shipping classes
                                        $shipping_classes = get_terms(array(
                                            'taxonomy' => 'product_shipping_class',
                                            'hide_empty' => false,
                                        ));
                                        if (is_wp_error($shipping_classes)) {
                                            $shipping_classes = array();
                                        }
                                        ?>
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <!-- SHIPPING CLASS ENGINE - Assign shipping classes via rules       -->
                                        <!-- Pipeline: Product Data → Match Rules → Assign Shipping Class   -->
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <div class="shipping-class-engine-container" style="padding: 20px;">
                                            
                                            <!-- Enable/Disable Toggle -->
                                            <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 8px; border-left: 4px solid #1976d2;">
                                                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                                    <input type="checkbox" id="shipping_class_engine_enabled" name="shipping_class_engine_enabled" value="1" style="width: 20px; height: 20px;">
                                                    <span>
                                                        <strong style="font-size: 15px; color: #0d47a1;"><?php esc_html_e('Enable Shipping Class Rules', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                        <small style="display: block; color: #1565c0; margin-top: 3px;">
                                                            <?php esc_html_e('Auto-assign shipping classes based on product weight, category, price, or any XML field', 'bootflow-product-xml-csv-importer'); ?>
                                                        </small>
                                                    </span>
                                                </label>
                                            </div>
                                            
                                            <!-- Shipping Class Engine Settings (shown when enabled) -->
                                            <div id="shipping-class-engine-settings" style="display: none;">
                                                
                                                <!-- Info Box -->
                                                <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
                                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                                        <span style="font-size: 24px;">📦</span>
                                                        <div>
                                                            <strong style="color: #1565c0;"><?php esc_html_e('How it works:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #1976d2; font-size: 13px; line-height: 1.6;">
                                                                <?php esc_html_e('Rules are evaluated from top to bottom. First matching rule wins. The assigned shipping class will be created automatically if it doesn\'t exist.', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                <?php esc_html_e('Note: If you have a direct shipping_class field mapped above, it takes priority over these rules.', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- ═══════════════════════════════════════════════════════════════ -->
                                                <!-- SHIPPING CLASS RULES                                           -->
                                                <!-- ═══════════════════════════════════════════════════════════════ -->
                                                <div style="margin-bottom: 20px;">
                                                    
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0;">
                                                        <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                                            <span class="dashicons dashicons-car" style="color: #1976d2;"></span>
                                                            <?php esc_html_e('Shipping Class Rules', 'bootflow-product-xml-csv-importer'); ?>
                                                            <span style="font-size: 12px; font-weight: normal; color: #666; margin-left: 10px;">
                                                                <?php esc_html_e('(First matching rule wins)', 'bootflow-product-xml-csv-importer'); ?>
                                                            </span>
                                                        </h4>
                                                        <button type="button" id="btn-add-shipping-rule" class="button button-primary" style="display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                            <?php esc_html_e('Add Rule', 'bootflow-product-xml-csv-importer'); ?>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Rules Container -->
                                                    <div id="shipping-class-rules-list">
                                                        
                                                        <!-- Default/Fallback Rule (always present) -->
                                                        <div class="shipping-rule-row shipping-rule-default" data-rule-id="default" style="padding: 20px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 2px solid #1976d2; border-radius: 8px; margin-bottom: 15px;">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <span style="font-size: 20px;">🏠</span>
                                                                    <strong style="color: #0d47a1; font-size: 14px;"><?php esc_html_e('Default Rule (Fallback)', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                    <span style="font-size: 11px; background: #1976d2; color: white; padding: 2px 8px; border-radius: 10px;">
                                                                        <?php esc_html_e('Always applies if no other rule matches', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                                                                <div>
                                                                    <label style="font-size: 12px; color: #1565c0; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Assign Shipping Class:', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <div style="display: flex; gap: 8px; align-items: center;">
                                                                        <select name="shipping_rule[default][shipping_class]" class="shipping-class-select" style="min-width: 200px; padding: 8px; border: 2px solid #90caf9; border-radius: 4px;">
                                                                            <option value=""><?php esc_html_e('-- No shipping class --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <?php foreach ($shipping_classes as $sc): ?>
                                                                                <option value="<?php echo esc_attr($sc->slug); ?>"><?php echo esc_html($sc->name); ?> (<?php echo esc_html($sc->slug); ?>)</option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <span style="color: #999; font-size: 12px;"><?php esc_html_e('or type new:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                        <input type="text" name="shipping_rule[default][shipping_class_custom]" placeholder="<?php esc_html_e('New class name', 'bootflow-product-xml-csv-importer'); ?>" 
                                                                               class="shipping-class-custom" style="width: 150px; padding: 8px; border: 2px solid #90caf9; border-radius: 4px;">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Dynamic rules will be added here -->
                                                        
                                                    </div>
                                                    
                                                    <!-- Rule Template (hidden, cloned by JS) -->
                                                    <template id="shipping-rule-template">
                                                        <div class="shipping-rule-row shipping-rule-conditional" data-rule-id="" style="padding: 20px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; transition: all 0.2s;">
                                                            
                                                            <!-- Rule Header -->
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <span class="rule-drag-handle" style="cursor: move; color: #999; font-size: 18px;">⋮⋮</span>
                                                                    <span class="shipping-rule-number" style="background: #1976d2; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                                                    <input type="text" name="shipping_rule[{id}][name]" placeholder="<?php esc_html_e('Rule name (e.g., Heavy items)', 'bootflow-product-xml-csv-importer'); ?>" 
                                                                           style="border: none; border-bottom: 1px dashed #ccc; padding: 5px; font-weight: 500; width: 200px;">
                                                                </div>
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <label style="display: flex; align-items: center; gap: 5px; font-size: 12px; color: #666;">
                                                                        <input type="checkbox" name="shipping_rule[{id}][enabled]" checked>
                                                                        <?php esc_html_e('Enabled', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <button type="button" class="button-link remove-shipping-rule" style="color: #d63638;">
                                                                        <span class="dashicons dashicons-trash"></span>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Conditions -->
                                                            <div class="shipping-rule-conditions" style="margin-bottom: 15px; padding: 15px; background: #fafafa; border-radius: 6px;">
                                                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                                    <strong style="font-size: 13px; color: #333;">
                                                                        <span class="dashicons dashicons-filter" style="color: #1976d2;"></span>
                                                                        <?php esc_html_e('Apply when:', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </strong>
                                                                    <select name="shipping_rule[{id}][condition_logic]" style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                                        <option value="AND"><?php esc_html_e('ALL conditions match', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="OR"><?php esc_html_e('ANY condition matches', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="shipping-conditions-list" style="display: flex; flex-direction: column; gap: 8px;">
                                                                    <!-- Condition row template -->
                                                                    <div class="shipping-condition-row" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                                                        <select name="shipping_rule[{id}][conditions][0][type]" class="shipping-condition-type" style="padding: 6px; border-radius: 4px; min-width: 140px;">
                                                                            <option value="weight_range">⚖️ <?php esc_html_e('Weight Range', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="price_range"><?php esc_html_e('Price Range', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="volume_range">📐 <?php esc_html_e('Volume (L×W×H)', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="category">📁 <?php esc_html_e('Category', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="brand">🏷️ <?php esc_html_e('Brand', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="xml_field">📄 <?php esc_html_e('XML Field', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <option value="sku_pattern">🔢 <?php esc_html_e('SKU Pattern', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        </select>
                                                                        
                                                                        <!-- Weight Range condition fields (shown by default) -->
                                                                        <div class="shipping-condition-fields shipping-condition-weight_range" style="display: flex; gap: 8px; align-items: center;">
                                                                            <span style="color: #666;"><?php esc_html_e('from', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                            <input type="number" name="shipping_rule[{id}][conditions][0][weight_from]" placeholder="0" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #666;"><?php esc_html_e('to', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                            <input type="number" name="shipping_rule[{id}][conditions][0][weight_to]" placeholder="∞" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #888; font-size: 11px;">kg</span>
                                                                        </div>
                                                                        
                                                                        <!-- Price Range condition fields (hidden by default) -->
                                                                        <div class="shipping-condition-fields shipping-condition-price_range" style="display: none; gap: 8px; align-items: center;">
                                                                            <span style="color: #666;"><?php esc_html_e('from', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                            <input type="number" name="shipping_rule[{id}][conditions][0][price_from]" placeholder="0" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #666;"><?php esc_html_e('to', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                            <input type="number" name="shipping_rule[{id}][conditions][0][price_to]" placeholder="∞" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #888; font-size: 11px;">€</span>
                                                                        </div>
                                                                        
                                                                        <!-- Volume Range condition fields (hidden by default) -->
                                                                        <div class="shipping-condition-fields shipping-condition-volume_range" style="display: none; gap: 8px; align-items: center;">
                                                                            <span style="color: #666;"><?php esc_html_e('from', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                            <input type="number" name="shipping_rule[{id}][conditions][0][volume_from]" placeholder="0" min="0" step="1" style="width: 100px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #666;"><?php esc_html_e('to', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                            <input type="number" name="shipping_rule[{id}][conditions][0][volume_to]" placeholder="∞" min="0" step="1" style="width: 100px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #888; font-size: 11px;">cm³</span>
                                                                        </div>
                                                                        
                                                                        <!-- Category condition fields (hidden by default) -->
                                                                        <div class="shipping-condition-fields shipping-condition-category" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="shipping_rule[{id}][conditions][0][category_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="not_equals"><?php esc_html_e('not equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="shipping_rule[{id}][conditions][0][category_value]" placeholder="<?php esc_html_e('Category name', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- Brand condition fields (hidden by default) -->
                                                                        <div class="shipping-condition-fields shipping-condition-brand" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="shipping_rule[{id}][conditions][0][brand_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="not_equals"><?php esc_html_e('not equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="shipping_rule[{id}][conditions][0][brand_value]" placeholder="<?php esc_html_e('Brand name', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- XML Field condition fields (hidden by default) -->
                                                                        <div class="shipping-condition-fields shipping-condition-xml_field" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="shipping_rule[{id}][conditions][0][xml_field_name]" class="shipping-xml-field-select" style="padding: 6px; border-radius: 4px; min-width: 120px;">
                                                                                <option value=""><?php esc_html_e('-- Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <select name="shipping_rule[{id}][conditions][0][xml_field_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals">=</option>
                                                                                <option value="not_equals">≠</option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="gt">></option>
                                                                                <option value="lt"><</option>
                                                                                <option value="gte">≥</option>
                                                                                <option value="lte">≤</option>
                                                                            </select>
                                                                            <input type="text" name="shipping_rule[{id}][conditions][0][xml_field_value]" placeholder="<?php esc_html_e('Value', 'bootflow-product-xml-csv-importer'); ?>" style="width: 150px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- SKU Pattern condition fields (hidden by default) -->
                                                                        <div class="shipping-condition-fields shipping-condition-sku_pattern" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="shipping_rule[{id}][conditions][0][sku_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="starts_with"><?php esc_html_e('starts with', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="ends_with"><?php esc_html_e('ends with', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="regex"><?php esc_html_e('matches regex', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="shipping_rule[{id}][conditions][0][sku_value]" placeholder="<?php esc_html_e('Pattern', 'bootflow-product-xml-csv-importer'); ?>" style="width: 150px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <button type="button" class="button-link remove-shipping-condition" style="color: #999; padding: 5px;" title="<?php esc_html_e('Remove condition', 'bootflow-product-xml-csv-importer'); ?>">✕</button>
                                                                    </div>
                                                                </div>
                                                                
                                                                <button type="button" class="button button-small add-shipping-condition" style="margin-top: 10px;">
                                                                    <span class="dashicons dashicons-plus" style="font-size: 14px; line-height: 1.4;"></span>
                                                                    <?php esc_html_e('Add Condition', 'bootflow-product-xml-csv-importer'); ?>
                                                                </button>
                                                            </div>
                                                            
                                                            <!-- Shipping Class Assignment -->
                                                            <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php esc_html_e('Assign Shipping Class:', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <div style="display: flex; gap: 8px; align-items: center;">
                                                                        <select name="shipping_rule[{id}][shipping_class]" class="shipping-class-select" style="min-width: 200px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                            <option value=""><?php esc_html_e('-- No shipping class --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                            <?php foreach ($shipping_classes as $sc): ?>
                                                                                <option value="<?php echo esc_attr($sc->slug); ?>"><?php echo esc_html($sc->name); ?> (<?php echo esc_html($sc->slug); ?>)</option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <span style="color: #999; font-size: 12px;"><?php esc_html_e('or type new:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                        <input type="text" name="shipping_rule[{id}][shipping_class_custom]" placeholder="<?php esc_html_e('New class name', 'bootflow-product-xml-csv-importer'); ?>" 
                                                                               class="shipping-class-custom" style="width: 150px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                        </div>
                                                    </template>
                                                    
                                                </div>
                                                
                                                <!-- Existing Shipping Classes Reference -->
                                                <div style="padding: 15px; background: #f5f5f5; border-radius: 8px; margin-top: 15px;">
                                                    <strong style="color: #333; font-size: 13px;">
                                                        <span class="dashicons dashicons-info-outline" style="color: #666;"></span>
                                                        <?php esc_html_e('Available WooCommerce Shipping Classes:', 'bootflow-product-xml-csv-importer'); ?>
                                                    </strong>
                                                    <div style="margin-top: 8px;">
                                                        <?php if (!empty($shipping_classes)): ?>
                                                            <?php foreach ($shipping_classes as $sc): ?>
                                                                <span style="display: inline-block; padding: 3px 10px; background: #fff; border: 1px solid #ddd; border-radius: 12px; margin: 3px; font-size: 12px;">
                                                                    <strong><?php echo esc_html($sc->name); ?></strong>
                                                                    <code style="font-size: 11px; color: #666;"><?php echo esc_html($sc->slug); ?></code>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <p style="color: #999; margin: 5px 0; font-size: 12px;">
                                                                <?php esc_html_e('No shipping classes found. They will be created automatically during import, or create them in WooCommerce → Settings → Shipping → Shipping Classes.', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                            </div>
                                            
                                            <!-- Disabled State Message -->
                                            <div id="shipping-class-engine-disabled-msg" style="padding: 25px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                                                <span style="font-size: 36px; opacity: 0.5;">📦</span>
                                                <p style="margin: 10px 0 0 0; color: #999;">
                                                    <?php esc_html_e('Enable Shipping Class Rules above to automatically assign shipping classes based on product data', 'bootflow-product-xml-csv-importer'); ?>
                                                </p>
                                            </div>
                                            
                                        </div>
                                    <?php elseif ($section_key === 'attributes_variations'): ?>
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <!-- SIMPLIFIED UI - 3 Clear Options                                  -->
                                        <!-- 1. Simple (default)  2. Attributes  3. Variations               -->
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <div class="attributes-variations-container">
                                            
                                            <input type="hidden" name="variation_mode" id="variation_mode_hidden" value="simple">
                                            
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <!-- PRODUCT MODE SELECTION - 3 Cards                                -->
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <div class="product-mode-selection" style="margin-bottom: 25px;">
                                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                                    
                                                    <!-- OPTION 1: Simple Products -->
                                                    <label class="mode-card" id="card-simple" style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; cursor: pointer; transition: all 0.3s; border: 3px solid transparent; color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                                                        <input type="radio" name="product_mode" value="simple" style="position: absolute; opacity: 0; pointer-events: none;" checked>
                                                        <div style="text-align: center;">
                                                            <span style="font-size: 36px; display: block; margin-bottom: 10px;">🛍️</span>
                                                            <strong style="font-size: 16px; display: block;"><?php esc_html_e('Simple Products', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <p style="margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;">
                                                                <?php esc_html_e('Regular products without variations or attributes', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    </label>
                                                    
                                                    <!-- OPTION 2: With Attributes -->
                                                    <label class="mode-card" id="card-attributes" style="padding: 20px; background: #f5f5f5; border-radius: 12px; cursor: pointer; transition: all 0.3s; border: 3px solid #e0e0e0;">
                                                        <input type="radio" name="product_mode" value="attributes" style="position: absolute; opacity: 0; pointer-events: none;">
                                                        <div style="text-align: center;">
                                                            <span style="font-size: 36px; display: block; margin-bottom: 10px;">🏷️</span>
                                                            <strong style="font-size: 16px; display: block; color: #333;"><?php esc_html_e('With Attributes', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                                                                <?php esc_html_e('Add display attributes like Material, Brand, Color', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    </label>
                                                    
                                                    <!-- OPTION 3: Variable Products -->
                                                    <label class="mode-card" id="card-variable" style="padding: 20px; background: #f5f5f5; border-radius: 12px; cursor: pointer; transition: all 0.3s; border: 3px solid #e0e0e0;">
                                                        <input type="radio" name="product_mode" value="variable" style="position: absolute; opacity: 0; pointer-events: none;">
                                                        <div style="text-align: center;">
                                                            <span style="font-size: 36px; display: block; margin-bottom: 10px;">📦</span>
                                                            <strong style="font-size: 16px; display: block; color: #333;"><?php esc_html_e('Variable Products', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                                                                <?php esc_html_e('Products with variations like Size, Color', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    </label>
                                                    
                                                </div>
                                            </div>
                                            
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <!-- PANEL: SIMPLE (nothing to configure)                            -->
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <div id="panel-simple" class="mode-panel" style="display: block;">
                                                <div style="padding: 30px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 8px; text-align: center;">
                                                    <span style="font-size: 48px;">✅</span>
                                                    <h4 style="margin: 15px 0 10px 0; color: #2e7d32;"><?php esc_html_e('Simple Products Mode', 'bootflow-product-xml-csv-importer'); ?></h4>
                                                    <p style="color: #558b2f; margin: 0;">
                                                        <?php esc_html_e('No additional configuration needed. Products will be imported as simple products.', 'bootflow-product-xml-csv-importer'); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <!-- PANEL: ATTRIBUTES                                                -->
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <div id="panel-attributes" class="mode-panel" style="display: none;">
                                                <div style="padding: 25px; background: #fff; border-radius: 8px; border: 2px solid #e0e0e0;">
                                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                                                        <span style="font-size: 32px;">🏷️</span>
                                                        <div>
                                                            <h4 style="margin: 0; color: #333;"><?php esc_html_e('Display Attributes', 'bootflow-product-xml-csv-importer'); ?></h4>
                                                            <p class="description" style="margin: 5px 0 0 0;">
                                                                <?php esc_html_e('Add attributes that will be shown on product pages. These are informational only.', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Attribute Mode Selection -->
                                                    <div style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 12px; color: #333;">
                                                            <?php esc_html_e('Attribute Input Mode:', 'bootflow-product-xml-csv-importer'); ?>
                                                        </label>
                                                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 15px; background: #fff; border: 2px solid #e0e0e0; border-radius: 6px; transition: all 0.2s;">
                                                                <input type="radio" name="attr_input_mode" value="standard" checked style="margin: 0;">
                                                                <span>
                                                                    <strong><?php esc_html_e('Standard', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                    <small style="display: block; color: #666; font-size: 11px;"><?php esc_html_e('Fixed name + dynamic value', 'bootflow-product-xml-csv-importer'); ?></small>
                                                                </span>
                                                            </label>
                                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 15px; background: #fff; border: 2px solid #e0e0e0; border-radius: 6px; transition: all 0.2s;">
                                                                <input type="radio" name="attr_input_mode" value="key_value" style="margin: 0;">
                                                                <span>
                                                                    <strong><?php esc_html_e('Key-Value Pairs', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                    <small style="display: block; color: #666; font-size: 11px;"><?php esc_html_e('{attribute1}→{value1}', 'bootflow-product-xml-csv-importer'); ?></small>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Standard Mode: Attributes List -->
                                                    <div id="attr-mode-standard">
                                                        <div id="attributes-list" style="margin-bottom: 15px;"></div>
                                                        
                                                        <button type="button" class="button button-primary" id="btn-add-attribute" style="display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                            <?php esc_html_e('Add Attribute', 'bootflow-product-xml-csv-importer'); ?>
                                                        </button>
                                                        
                                                        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 6px; border-left: 4px solid #2196f3;">
                                                            <strong style="color: #1565c0;">💡 <?php esc_html_e('Example:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #555;">
                                                                <?php esc_html_e('Attribute Name: "Material" → Source: select the XML/CSV field containing material values', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Key-Value Mode: Attribute Pairs List -->
                                                    <div id="attr-mode-key-value" style="display: none;">
                                                        <div style="margin-bottom: 15px; padding: 12px; background: #fff3e0; border-radius: 6px; border-left: 4px solid #ff9800;">
                                                            <strong style="color: #e65100;">📋 <?php esc_html_e('Key-Value Pair Mode', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #555; font-size: 13px;">
                                                                <?php esc_html_e('For XML files where attribute name and value are in separate fields (e.g., BigBuy format with attribute1/value1, attribute2/value2).', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                        
                                                        <div id="attribute-pairs-list" style="margin-bottom: 15px;"></div>
                                                        
                                                        <button type="button" class="button button-primary" id="btn-add-attribute-pair" style="display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                            <?php esc_html_e('Add Key-Value Pair', 'bootflow-product-xml-csv-importer'); ?>
                                                        </button>
                                                        
                                                        <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 6px; border-left: 4px solid #4caf50;">
                                                            <strong style="color: #2e7d32;">💡 <?php esc_html_e('Example for BigBuy XML:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <div style="margin-top: 8px; font-size: 13px; color: #555;">
                                                                <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">&lt;attribute1&gt;Colour&lt;/attribute1&gt; &lt;value1&gt;Blue&lt;/value1&gt;</code><br><br>
                                                                <?php esc_html_e('Name Field:', 'bootflow-product-xml-csv-importer'); ?> <code>{attribute1}</code> → <?php esc_html_e('Value Field:', 'bootflow-product-xml-csv-importer'); ?> <code>{value1}</code><br>
                                                                <?php esc_html_e('Result: Attribute "Colour" with value "Blue"', 'bootflow-product-xml-csv-importer'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <!-- PANEL: VARIABLE PRODUCTS                                         -->
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <div id="panel-variable" class="mode-panel" style="display: none;">
                                                <div style="padding: 25px; background: #fff; border-radius: 8px; border: 2px solid #e0e0e0;">
                                                    
                                                    <!-- Header -->
                                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                                                        <span style="font-size: 32px;">📦</span>
                                                        <div>
                                                            <h4 style="margin: 0; color: #333;"><?php esc_html_e('Variable Products Configuration', 'bootflow-product-xml-csv-importer'); ?></h4>
                                                            <p class="description" style="margin: 5px 0 0 0;">
                                                                <?php esc_html_e('Configure how variations are structured in your file.', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($file_type === 'csv'): ?>
                                                    <!-- ═══════════════════════════════════════════════════════════════ -->
                                                    <!-- CSV VARIATION MODE - Parent/Child Rows                          -->
                                                    <!-- ═══════════════════════════════════════════════════════════════ -->
                                                    <div class="csv-variation-config">
                                                        
                                                        <!-- Info Box -->
                                                        <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
                                                            <strong style="color: #1565c0;">💡 <?php esc_html_e('CSV Variable Products', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #555; font-size: 13px;">
                                                                <?php esc_html_e('For CSV files, variable products require Parent SKU and Type columns to link variations to parent products.', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                        
                                                        <!-- CSV Grouping Fields -->
                                                        <div style="margin-bottom: 25px; padding: 20px; background: #fff3e0; border-radius: 8px; border: 1px solid #ffcc80;">
                                                            <label style="font-weight: 600; display: block; margin-bottom: 15px; color: #e65100; font-size: 14px;">
                                                                🔗 <?php esc_html_e('CSV Grouping Fields', 'bootflow-product-xml-csv-importer'); ?>
                                                            </label>
                                                            
                                                            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 12px; align-items: center;">
                                                                <!-- Parent SKU Column -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Parent SKU Column:', 'bootflow-product-xml-csv-importer'); ?> <span style="color: #e53e3e;">*</span></label>
                                                                <select name="csv_var[parent_sku_column]" id="csv-parent-sku-column" class="field-source-select" style="max-width: 320px;">
                                                                    <option value=""><?php esc_html_e('-- Select Column --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                                
                                                                <!-- Type Column -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Type Column:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <select name="csv_var[type_column]" id="csv-type-column" class="field-source-select" style="max-width: 320px;">
                                                                    <option value=""><?php esc_html_e('-- Select Column (optional) --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                            </div>
                                                            
                                                            <p class="description" style="margin-top: 12px; font-size: 12px; color: #666;">
                                                                <?php esc_html_e('Parent SKU links variations to parent. Type column (values: "variable" or "variation") helps identify row types.', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                        
                                                        <!-- CSV Variation Attributes -->
                                                        <div style="margin-bottom: 25px; padding: 20px; background: #e8f5e9; border-radius: 8px; border: 1px solid #a5d6a7;">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                                <label style="font-weight: 600; color: #2e7d32; font-size: 14px;">
                                                                    🏷️ <?php esc_html_e('Variation Attributes (CSV Columns)', 'bootflow-product-xml-csv-importer'); ?>
                                                                </label>
                                                                <button type="button" class="button" id="btn-add-csv-var-attribute">
                                                                    <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
                                                                    <?php esc_html_e('Add Attribute', 'bootflow-product-xml-csv-importer'); ?>
                                                                </button>
                                                            </div>
                                                            <p class="description" style="margin-bottom: 10px; font-size: 12px;">
                                                                <?php esc_html_e('Map CSV columns that contain attribute values (e.g., "Attribute:Color", "Attribute:Size").', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                            
                                                            <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #c8e6c9; margin-bottom: 15px;">
                                                                <strong style="font-size: 12px; color: #2e7d32;">💡 <?php esc_html_e('Example for CSV structure:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                <div style="font-size: 11px; color: #666; margin-top: 8px;">
                                                                    <?php esc_html_e('If your CSV has columns:', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                    <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 10px;">Attribute:Color, Attribute:Size</code><br><br>
                                                                    <?php esc_html_e('Add 2 attributes:', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                    • <strong>Color</strong> → Source Column: <code>Attribute:Color</code><br>
                                                                    • <strong>Size</strong> → Source Column: <code>Attribute:Size</code>
                                                                </div>
                                                            </div>
                                                            <div id="csv-variation-attributes-list"></div>
                                                        </div>
                                                        
                                                        <!-- CSV Variation Field Mapping -->
                                                        <div style="padding: 20px; background: #f5f5f5; border-radius: 8px; border: 1px solid #e0e0e0;">
                                                            <label style="font-weight: 600; display: block; margin-bottom: 15px; color: #333; font-size: 14px;">
                                                                📦 <?php esc_html_e('Variation Field Mapping (CSV Columns)', 'bootflow-product-xml-csv-importer'); ?>
                                                            </label>
                                                            <p class="description" style="margin-bottom: 15px; font-size: 12px;">
                                                                <?php esc_html_e('Map CSV columns to WooCommerce variation fields. These are read from variation rows.', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                            
                                                            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 12px; align-items: center;">
                                                                
                                                                <!-- Variation SKU -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Variation SKU:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[sku]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- GTIN/EAN/UPC/ISBN -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('GTIN/EAN/UPC/ISBN:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[gtin]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Regular Price -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Regular Price:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[regular_price]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Sale Price -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Sale Price:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[sale_price]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Sale Price Dates From -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Sale Date From:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[sale_price_dates_from]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Sale Price Dates To -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Sale Date To:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[sale_price_dates_to]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Stock Quantity -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Stock Quantity:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[stock_quantity]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Stock Status -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Stock Status:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[stock_status]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Manage Stock -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Manage Stock:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[manage_stock]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Low Stock Amount -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Low Stock Threshold:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[low_stock_amount]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Weight -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Weight:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[weight]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Dimensions -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Length:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[length]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <label style="font-weight: 500;"><?php esc_html_e('Width:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[width]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <label style="font-weight: 500;"><?php esc_html_e('Height:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[height]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Image -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Image URL:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[image]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Description -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Description:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[description]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Virtual -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Virtual:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[virtual]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Downloadable -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Downloadable:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[downloadable]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Shipping Class -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Shipping Class:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[shipping_class]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Status -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Status:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[status]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Menu Order -->
                                                                <label style="font-weight: 500;"><?php esc_html_e('Menu Order:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[menu_order]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                            </div>
                                                            
                                                            <!-- Custom Meta Fields for CSV Variations -->
                                                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                                    <label style="font-weight: 600; color: #555;">
                                                                        🔧 <?php esc_html_e('Custom Meta Fields (EAN, GTIN, UPC, etc.)', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </label>
                                                                    <button type="button" class="button" id="btn-add-csv-var-meta">
                                                                        <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
                                                                        <?php esc_html_e('Add Meta Field', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </button>
                                                                </div>
                                                                <div id="csv-variation-meta-list"></div>
                                                            </div>
                                                        </div>
                                                        
                                                    </div><!-- /csv-variation-config -->
                                                    <?php else: ?>
                                                    
                                                    <!-- ═══════════════════════════════════════════════════════════════ -->
                                                    <!-- XML VARIATION MODE - Nested Elements                            -->
                                                    <!-- ═══════════════════════════════════════════════════════════════ -->
                                                    
                                                    <!-- SECTION 1: Variation Path -->
                                                    <div style="margin-bottom: 25px; padding: 20px; background: #e3f2fd; border-radius: 8px; border: 1px solid #90caf9;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 10px; color: #1565c0; font-size: 14px;">
                                                            📍 <?php esc_html_e('Variation Container Path', 'bootflow-product-xml-csv-importer'); ?>
                                                            <span style="color: #e53e3e;">*</span>
                                                        </label>
                                                        <input type="text" 
                                                               name="variation_path" 
                                                               id="variation_path"
                                                               value=""
                                                               placeholder="e.g., variations.variation or attributes.attribute"
                                                               style="width: 100%; max-width: 500px; padding: 12px; border: 2px solid #64b5f6; border-radius: 6px; font-family: monospace; font-size: 14px;">
                                                        <p class="description" style="margin-top: 10px;">
                                                            <?php esc_html_e('Path to variation/attribute container. Examples:', 'bootflow-product-xml-csv-importer'); ?><br>
                                                            <code style="cursor: pointer; background: #bbdefb; padding: 3px 8px; border-radius: 4px; margin: 5px 5px 0 0; display: inline-block;" onclick="document.getElementById('variation_path').value='variations.variation'">variations.variation</code>
                                                            <code style="cursor: pointer; background: #bbdefb; padding: 3px 8px; border-radius: 4px; margin: 5px 5px 0 0; display: inline-block;" onclick="document.getElementById('variation_path').value='attributes.attribute'">attributes.attribute</code>
                                                            <code style="cursor: pointer; background: #bbdefb; padding: 3px 8px; border-radius: 4px; margin: 5px 5px 0 0; display: inline-block;" onclick="document.getElementById('variation_path').value='variants.variant'">variants.variant</code>
                                                        </p>
                                                    </div>
                                                    
                                                    <!-- SECTION 2: Variation Attributes -->
                                                    <div style="margin-bottom: 25px; padding: 20px; background: #fff3e0; border-radius: 8px; border: 1px solid #ffcc80;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                            <label style="font-weight: 600; color: #e65100; font-size: 14px;">
                                                                🏷️ <?php esc_html_e('Variation Attributes', 'bootflow-product-xml-csv-importer'); ?>
                                                            </label>
                                                        </div>
                                                        <p class="description" style="margin-bottom: 15px;">
                                                            <?php esc_html_e('Define attributes used for variations (e.g., Size, Color). Each unique combination creates a product variation.', 'bootflow-product-xml-csv-importer'); ?>
                                                        </p>
                                                        
                                                        <!-- Attribute Mode Selection -->
                                                        <div style="background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #ffe0b2; margin-bottom: 15px;">
                                                            <div style="display: flex; gap: 25px; flex-wrap: wrap;">
                                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                                                    <input type="radio" name="attribute_detection_mode" value="manual" checked class="attribute-mode-radio">
                                                                    <span>📝 <?php esc_html_e('Manual', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <span style="font-weight: normal; color: #666; font-size: 12px;">— <?php esc_html_e('Define each attribute manually', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                </label>
                                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                                                    <input type="radio" name="attribute_detection_mode" value="auto" class="attribute-mode-radio">
                                                                    <span>🔍 <?php esc_html_e('Auto-detect', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <span style="font-weight: normal; color: #666; font-size: 12px;">— <?php esc_html_e('Read all attributes from XML automatically', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- AUTO-DETECT Settings (hidden by default) -->
                                                        <div id="auto-detect-attributes-settings" style="display: none; background: #e8f5e9; padding: 15px; border-radius: 6px; border: 1px solid #a5d6a7; margin-bottom: 15px;">
                                                            <p style="margin: 0 0 12px 0; font-weight: 500; color: #2e7d32;">
                                                                ✨ <?php esc_html_e('Auto-detect Settings', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 10px; align-items: center;">
                                                                <label style="font-weight: 500;"><?php esc_html_e('Attributes path:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                                <div class="textarea-mapping-wrapper">
                                                                    <textarea name="auto_attributes_path" id="auto-attributes-path" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('e.g., variations.variation.attributes', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 350px;"></textarea>
                                                                    <p class="description" style="font-size: 10px; margin-top: 2px; color: #666;">
                                                                        <?php esc_html_e('Path to attributes container in each variation (without { })', 'bootflow-product-xml-csv-importer'); ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div style="margin-top: 12px;">
                                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                                    <input type="checkbox" name="auto_create_attributes" id="auto-create-attributes" checked>
                                                                    <span><?php esc_html_e('Create WooCommerce attributes if they don\'t exist', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                </label>
                                                            </div>
                                                            <div style="background: #fff; padding: 10px; border-radius: 4px; margin-top: 12px; font-size: 11px; color: #555;">
                                                                <strong>💡 <?php esc_html_e('How it works:', 'bootflow-product-xml-csv-importer'); ?></strong><br>
                                                                <?php esc_html_e('For XML like:', 'bootflow-product-xml-csv-importer'); ?> <code>&lt;attributes&gt;&lt;size&gt;L&lt;/size&gt;&lt;color&gt;Black&lt;/color&gt;&lt;/attributes&gt;</code><br>
                                                                <?php esc_html_e('Plugin will automatically create attributes: pa_size=L, pa_color=Black', 'bootflow-product-xml-csv-importer'); ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- MANUAL Attributes Settings -->
                                                        <div id="manual-attributes-settings">
                                                            <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                                                                <button type="button" class="button" id="btn-add-var-attribute">
                                                                    <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
                                                                    <?php esc_html_e('Add Attribute', 'bootflow-product-xml-csv-importer'); ?>
                                                                </button>
                                                            </div>
                                                            <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #ffe0b2; margin-bottom: 15px;">
                                                                <strong style="font-size: 12px; color: #e65100;">💡 <?php esc_html_e('Example for your XML structure:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                <div style="font-size: 11px; color: #666; margin-top: 8px;">
                                                                    <?php esc_html_e('If your XML has:', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                    <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 10px;">&lt;attributes&gt;&lt;attribute&gt;S&lt;/attribute&gt;&lt;attribute&gt;Red&lt;/attribute&gt;&lt;/attributes&gt;</code><br><br>
                                                                    <?php esc_html_e('Add 2 attributes:', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                    • <strong>Size</strong> → Source: <code>attributes.attribute</code> + Array Index: <code>0</code><br>
                                                                    • <strong>Color</strong> → Source: <code>attributes.attribute</code> + Array Index: <code>1</code>
                                                                </div>
                                                            </div>
                                                            <div id="variation-attributes-list"></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- SECTION 3: Variation Field Mapping -->
                                                    <div style="padding: 20px; background: #f5f5f5; border-radius: 8px; border: 1px solid #e0e0e0;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 15px; color: #333; font-size: 14px;">
                                                            📦 <?php esc_html_e('Variation Field Mapping', 'bootflow-product-xml-csv-importer'); ?>
                                                        </label>
                                                        <p class="description" style="margin-bottom: 15px;">
                                                            <?php esc_html_e('Map fields from your source file to WooCommerce variation fields.', 'bootflow-product-xml-csv-importer'); ?>
                                                        </p>
                                                        
                                                        <div style="display: grid; grid-template-columns: 180px 1fr; gap: 12px; align-items: center;">
                                                            
                                                            <!-- Parent SKU (for linking variations to parent) -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Parent SKU:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper">
                                                                <textarea name="var_field[parent_sku]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea>
                                                                <p class="description" style="font-size: 10px; margin-top: 2px; color: #666;">
                                                                    <?php esc_html_e('Optional: Link variation to parent by SKU', 'bootflow-product-xml-csv-importer'); ?>
                                                                </p>
                                                            </div>
                                                            
                                                            <!-- SKU -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Variation SKU:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[sku]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- GTIN/EAN/UPC/ISBN -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('GTIN/EAN/UPC/ISBN:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[gtin]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Regular Price -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Regular Price:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[regular_price]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Sale Price -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Sale Price:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[sale_price]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Sale Price Dates From -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Sale Date From:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[sale_price_dates_from]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Sale Price Dates To -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Sale Date To:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[sale_price_dates_to]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Stock Quantity -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Stock Quantity:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[stock_quantity]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Stock Status -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Stock Status:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[stock_status]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Manage Stock -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Manage Stock:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[manage_stock]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Low Stock Amount -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Low Stock Threshold:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[low_stock_amount]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Backorders -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Backorders:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div style="display: flex; gap: 10px; align-items: center;">
                                                                <textarea name="var_field[backorders]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 200px;"></textarea>
                                                                <select name="var_field[backorders_default]" style="max-width: 120px;">
                                                                    <option value=""><?php esc_html_e('From source', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <option value="no"><?php esc_html_e('No', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <option value="notify"><?php esc_html_e('Allow, notify', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <option value="yes"><?php esc_html_e('Allow', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                            </div>
                                                            
                                                            <!-- Weight with inheritance option -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Weight:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div style="display: flex; gap: 10px; align-items: center;">
                                                                <textarea name="var_field[weight]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 200px;"></textarea>
                                                                <label style="font-size: 11px; display: flex; align-items: center; gap: 4px;">
                                                                    <input type="checkbox" name="var_field[weight_inherit]" value="1">
                                                                    <?php esc_html_e('Inherit from parent', 'bootflow-product-xml-csv-importer'); ?>
                                                                </label>
                                                            </div>
                                                            
                                                            <!-- Dimensions with inheritance option -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Length:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div style="display: flex; gap: 10px; align-items: center;">
                                                                <textarea name="var_field[length]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 200px;"></textarea>
                                                                <label style="font-size: 11px; display: flex; align-items: center; gap: 4px;">
                                                                    <input type="checkbox" name="var_field[dimensions_inherit]" value="1">
                                                                    <?php esc_html_e('Inherit L/W/H from parent', 'bootflow-product-xml-csv-importer'); ?>
                                                                </label>
                                                            </div>
                                                            
                                                            <label style="font-weight: 500;"><?php esc_html_e('Width:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[width]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <label style="font-weight: 500;"><?php esc_html_e('Height:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[height]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Image -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Image URL:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[image]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Description -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Description:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[description]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Virtual -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Virtual:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[virtual]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Downloadable -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Downloadable:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[downloadable]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Download Limit -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Download Limit:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[download_limit]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Download Expiry -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Download Expiry (days):', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[download_expiry]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Downloads (file URLs) -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Download Files:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper">
                                                                <textarea name="var_field[downloads]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea>
                                                                <p class="description" style="font-size: 11px; margin-top: 4px;">
                                                                    <?php esc_html_e('Format: Name|URL or just URL. Multiple: Name1|URL1,Name2|URL2', 'bootflow-product-xml-csv-importer'); ?>
                                                                </p>
                                                            </div>
                                                            
                                                            <!-- Tax Class -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Tax Class:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[tax_class]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Shipping Class -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Shipping Class:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[shipping_class]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Status -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Status:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[status]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Menu Order -->
                                                            <label style="font-weight: 500;"><?php esc_html_e('Menu Order:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[menu_order]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'bootflow-product-xml-csv-importer'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                        </div>
                                                        
                                                        <!-- Custom Meta Fields for Variations -->
                                                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                                <label style="font-weight: 600; color: #555;">
                                                                    🔧 <?php esc_html_e('Custom Meta Fields (EAN, GTIN, UPC, etc.)', 'bootflow-product-xml-csv-importer'); ?>
                                                                </label>
                                                                <button type="button" class="button" id="btn-add-var-meta">
                                                                    <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
                                                                    <?php esc_html_e('Add Meta Field', 'bootflow-product-xml-csv-importer'); ?>
                                                                </button>
                                                            </div>
                                                            
                                                            <!-- Common meta field presets -->
                                                            <div style="margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px;">
                                                                <label style="font-size: 11px; font-weight: 600; color: #666; display: block; margin-bottom: 8px;">
                                                                    <?php esc_html_e('Quick Add Common Fields:', 'bootflow-product-xml-csv-importer'); ?>
                                                                </label>
                                                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_supplier_id" data-label="Supplier ID">Supplier ID</button>
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_warehouse_code" data-label="Warehouse">Warehouse</button>
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_lead_time_days" data-label="Lead Time">Lead Time</button>
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_cost_price" data-label="Cost Price">Cost Price</button>
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_barcode" data-label="Barcode">Barcode</button>
                                                                </div>
                                                            </div>
                                                            
                                                            <div id="variation-meta-list"></div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?><!-- /file_type check -->
                                                    
                                                </div>
                                            </div>
                                            
                                        </div><!-- /attributes-variations-container -->
                                    <?php elseif ($section_key === 'taxonomy'): ?>
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <!-- TAXONOMY MAPPING - Categories, Tags, Brand                      -->
                                        <!-- WP All Import style UI with modes, separators, mapping table    -->
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <?php
                                        // Fetch existing WooCommerce categories for mapping dropdown
                                        $store_categories = get_terms(array(
                                            'taxonomy' => 'product_cat',
                                            'hide_empty' => false,
                                            'orderby' => 'name',
                                            'order' => 'ASC',
                                        ));
                                        $categories_tree = array();
                                        if (!is_wp_error($store_categories) && !empty($store_categories)) {
                                            // Build flat list with hierarchy indicator
                                            $cat_by_parent = array();
                                            foreach ($store_categories as $cat) {
                                                $cat_by_parent[$cat->parent][] = $cat;
                                            }
                                            // Recursive function to build indented list
                                            if (!function_exists('bfpi_build_cat_tree')) {
                                            function bfpi_build_cat_tree($parent_id, $cat_by_parent, $depth = 0) {
                                                $result = array();
                                                if (!isset($cat_by_parent[$parent_id])) return $result;
                                                foreach ($cat_by_parent[$parent_id] as $cat) {
                                                    $result[] = array(
                                                        'id' => $cat->term_id,
                                                        'name' => $cat->name,
                                                        'slug' => $cat->slug,
                                                        'depth' => $depth,
                                                        'label' => str_repeat('— ', $depth) . $cat->name,
                                                    );
                                                    $result = array_merge($result, bfpi_build_cat_tree($cat->term_id, $cat_by_parent, $depth + 1));
                                                }
                                                return $result;
                                            }
                                            } // end function_exists check
                                            $categories_tree = bfpi_build_cat_tree(0, $cat_by_parent);
                                        }

                                        // Fetch existing WooCommerce tags
                                        $store_tags = get_terms(array(
                                            'taxonomy' => 'product_tag',
                                            'hide_empty' => false,
                                            'orderby' => 'name',
                                            'order' => 'ASC',
                                        ));

                                        // Fetch existing brands
                                        $store_brands = array();
                                        if (taxonomy_exists('product_brand')) {
                                            $store_brands = get_terms(array(
                                                'taxonomy' => 'product_brand',
                                                'hide_empty' => false,
                                                'orderby' => 'name',
                                                'order' => 'ASC',
                                            ));
                                            if (is_wp_error($store_brands)) $store_brands = array();
                                        }
                                        
                                        // Load saved taxonomy settings for edit mode
                                        $saved_cat = $saved_mappings['categories'] ?? array();
                                        $saved_tag = $saved_mappings['tags'] ?? array();
                                        $saved_brand = $saved_mappings['brand'] ?? array();
                                        $saved_cat_source = $saved_cat['source'] ?? '';
                                        $saved_cat_mode = $saved_cat['cat_mode'] ?? 'multiple';
                                        $saved_cat_separator = $saved_cat['cat_separator'] ?? ',';
                                        $saved_cat_hier_sep = $saved_cat['cat_hier_sep'] ?? '>';
                                        $saved_cat_multi_sep = $saved_cat['cat_multi_sep'] ?? '|';
                                        $saved_cat_auto_create = isset($saved_cat['cat_auto_create']) ? $saved_cat['cat_auto_create'] : '1';
                                        $saved_cat_match_existing = isset($saved_cat['cat_match_existing']) ? $saved_cat['cat_match_existing'] : '1';
                                        $saved_cat_leaf_only = !empty($saved_cat['cat_leaf_only']);
                                        $saved_cat_also_tags = !empty($saved_cat['cat_also_tags']);
                                        $saved_cat_enable_mapping = !empty($saved_cat['cat_enable_mapping']);
                                        $saved_cat_mapping = $saved_cat['cat_mapping'] ?? array();
                                        $saved_tag_source = $saved_tag['source'] ?? '';
                                        $saved_tag_separator = $saved_tag['tag_separator'] ?? ',';
                                        $saved_tag_enable_mapping = !empty($saved_tag['tag_enable_mapping']);
                                        $saved_tag_mapping = $saved_tag['tag_mapping'] ?? array();
                                        $saved_brand_source = $saved_brand['source'] ?? '';
                                        $saved_brand_enable_mapping = !empty($saved_brand['brand_enable_mapping']);
                                        $saved_brand_mapping = $saved_brand['brand_mapping'] ?? array();
                                        ?>
                                        
                                        <div class="taxonomy-mapping-container" style="padding: 20px;">

                                            <!-- ══════════════════════════════════════════ -->
                                            <!-- PRODUCT CATEGORIES                          -->
                                            <!-- ══════════════════════════════════════════ -->
                                            <div class="taxonomy-block" data-taxonomy="categories" style="margin-bottom: 30px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                                                <div style="padding: 15px 20px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-bottom: 1px solid #a5d6a7; display: flex; align-items: center; gap: 10px;">
                                                    <span style="font-size: 22px;">📂</span>
                                                    <strong style="font-size: 15px; color: #2e7d32;"><?php esc_html_e('Product Categories', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                </div>
                                                
                                                <div style="padding: 20px;">
                                                    <!-- Source Field -->
                                                    <div style="margin-bottom: 20px;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                                                            <?php esc_html_e('Source Field', 'bootflow-product-xml-csv-importer'); ?>
                                                        </label>
                                                        <div class="textarea-mapping-wrapper" data-field="categories">
                                                            <textarea name="field_mapping[categories][source]" 
                                                                      class="field-mapping-textarea" 
                                                                      rows="1"
                                                                      data-field-name="categories"
                                                                      placeholder="<?php esc_attr_e('Type { to see fields or drag field here...', 'bootflow-product-xml-csv-importer'); ?>"><?php echo esc_textarea($saved_cat_source); ?></textarea>
                                                        </div>
                                                        <p class="description" style="margin-top: 5px;">
                                                            <?php esc_html_e('Select the XML/CSV field containing category data. You can combine fields: {category} or {parent_cat} > {sub_cat}', 'bootflow-product-xml-csv-importer'); ?>
                                                        </p>
                                                    </div>
                                                    
                                                    <!-- Category Mode -->
                                                    <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 12px;">
                                                            <?php esc_html_e('Category Mode', 'bootflow-product-xml-csv-importer'); ?>
                                                        </label>
                                                        
                                                        <!-- Mode 1: Single -->
                                                        <label class="taxonomy-mode-option" style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                                                            <input type="radio" name="field_mapping[categories][cat_mode]" value="single" style="margin-top: 3px;" <?php checked($saved_cat_mode, 'single'); ?>>
                                                            <span>
                                                                <strong><?php esc_html_e('Single category per product', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                <small style="display: block; color: #666; margin-top: 2px;">
                                                                    <?php esc_html_e('Each product gets one category from source field value', 'bootflow-product-xml-csv-importer'); ?>
                                                                </small>
                                                            </span>
                                                        </label>
                                                        
                                                        <!-- Mode 2: Multiple -->
                                                        <label class="taxonomy-mode-option" style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                                            <input type="radio" name="field_mapping[categories][cat_mode]" value="multiple" style="margin-top: 3px;" <?php checked($saved_cat_mode, 'multiple'); ?>>
                                                            <span>
                                                                <strong><?php esc_html_e('Multiple categories, separated by:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                <small style="display: block; color: #666; margin-top: 2px;">
                                                                    <?php esc_html_e('Source field contains multiple category names delimited by separator', 'bootflow-product-xml-csv-importer'); ?>
                                                                </small>
                                                            </span>
                                                        </label>
                                                        <div class="taxonomy-mode-detail" data-for="multiple" style="margin-left: 26px; margin-bottom: 12px; display: <?php echo esc_attr( ($saved_cat_mode === 'multiple') ? 'block' : 'none' ); ?>;">
                                                            <input type="text" name="field_mapping[categories][cat_separator]" value="<?php echo esc_attr($saved_cat_separator); ?>" 
                                                                   style="width: 80px; text-align: center; font-family: monospace; font-size: 14px; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;"
                                                                   placeholder=",">
                                                            <span style="color: #888; margin-left: 8px; font-size: 12px;">
                                                                <?php esc_html_e('e.g., Electronics, Phones, Accessories', 'bootflow-product-xml-csv-importer'); ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <!-- Mode 3: Hierarchical -->
                                                        <label class="taxonomy-mode-option" style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                                            <input type="radio" name="field_mapping[categories][cat_mode]" value="hierarchical" style="margin-top: 3px;" <?php checked($saved_cat_mode, 'hierarchical'); ?>>
                                                            <span>
                                                                <strong><?php esc_html_e('Hierarchical categories (Parent > Child)', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                <small style="display: block; color: #666; margin-top: 2px;">
                                                                    <?php esc_html_e('Source field contains nested category path with separator', 'bootflow-product-xml-csv-importer'); ?>
                                                                </small>
                                                            </span>
                                                        </label>
                                                        <div class="taxonomy-mode-detail" data-for="hierarchical" style="margin-left: 26px; margin-bottom: 12px; display: <?php echo esc_attr( ($saved_cat_mode === 'hierarchical') ? 'block' : 'none' ); ?>;">
                                                            <label style="font-size: 13px; color: #555; margin-right: 8px;"><?php esc_html_e('Hierarchy separator:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="text" name="field_mapping[categories][cat_hier_sep]" value="<?php echo esc_attr($saved_cat_hier_sep); ?>" 
                                                                   style="width: 80px; text-align: center; font-family: monospace; font-size: 14px; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;"
                                                                   placeholder=">">
                                                            <span style="color: #888; margin-left: 8px; font-size: 12px;">
                                                                <?php esc_html_e('e.g., Electronics > Phones > Apple', 'bootflow-product-xml-csv-importer'); ?>
                                                            </span>
                                                            <br>
                                                            <label style="font-size: 13px; color: #555; margin-right: 8px; margin-top: 8px; display: inline-block;"><?php esc_html_e('Multi-category separator:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="text" name="field_mapping[categories][cat_multi_sep]" value="<?php echo esc_attr($saved_cat_multi_sep); ?>" 
                                                                   style="width: 80px; text-align: center; font-family: monospace; font-size: 14px; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;"
                                                                   placeholder="|">
                                                            <span style="color: #888; margin-left: 8px; font-size: 12px;">
                                                                <?php esc_html_e('Separates multiple hierarchical paths, e.g., A > B | C > D', 'bootflow-product-xml-csv-importer'); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Category Options -->
                                                    <div style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 6px;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 12px;">
                                                            <?php esc_html_e('Options', 'bootflow-product-xml-csv-importer'); ?>
                                                        </label>
                                                        
                                                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                                                            <input type="checkbox" name="field_mapping[categories][cat_auto_create]" value="1" <?php checked($saved_cat_auto_create); ?>>
                                                            <span><?php esc_html_e('Auto-create categories if they don\'t exist', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                        
                                                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                                                            <input type="checkbox" name="field_mapping[categories][cat_match_existing]" value="1" <?php checked($saved_cat_match_existing); ?>>
                                                            <span><?php esc_html_e('Try to match existing child categories (match by name regardless of hierarchy)', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                        
                                                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                                                            <input type="checkbox" name="field_mapping[categories][cat_leaf_only]" value="1" <?php checked($saved_cat_leaf_only); ?>>
                                                            <span><?php esc_html_e('Only assign the deepest (leaf) category, not the entire hierarchy', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                        
                                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                            <input type="checkbox" name="field_mapping[categories][cat_also_tags]" value="1" <?php checked($saved_cat_also_tags); ?>>
                                                            <span><?php esc_html_e('Also add category names as product tags', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                    </div>
                                                    
                                                    <!-- Category Mapping Table -->
                                                    <div style="margin-bottom: 10px;">
                                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 15px;">
                                                            <input type="checkbox" id="cat-enable-mapping" name="field_mapping[categories][cat_enable_mapping]" value="1" <?php checked($saved_cat_enable_mapping); ?>>
                                                            <strong><?php esc_html_e('Enable Category Mapping', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <span style="color: #888; font-size: 12px; font-weight: normal;">
                                                                — <?php esc_html_e('Map feed values to specific WooCommerce categories', 'bootflow-product-xml-csv-importer'); ?>
                                                            </span>
                                                        </label>
                                                        
                                                        <div id="cat-mapping-table-wrap" style="display: <?php echo esc_attr($saved_cat_enable_mapping ? 'block' : 'none'); ?>;">
                                                            <div style="padding: 15px; background: #fff3e0; border-radius: 6px; border: 1px solid #ffcc80; margin-bottom: 15px;">
                                                                <p style="margin: 0; font-size: 13px; color: #e65100;">
                                                                    <strong>💡 <?php esc_html_e('How mapping works:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                                    <?php esc_html_e('Add rows to map feed category values to your WooCommerce categories. Values not in the mapping table will be imported as-is (or auto-created).', 'bootflow-product-xml-csv-importer'); ?>
                                                                </p>
                                                            </div>
                                                            
                                                            <table class="taxonomy-mapping-table widefat" style="margin-bottom: 10px;">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 45%;"><?php esc_html_e('Feed Value (from source)', 'bootflow-product-xml-csv-importer'); ?></th>
                                                                        <th style="width: 45%;"><?php esc_html_e('WooCommerce Category', 'bootflow-product-xml-csv-importer'); ?></th>
                                                                        <th style="width: 10%;"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="cat-mapping-rows">
                                                                    <?php if (!empty($saved_cat_mapping)): ?>
                                                                        <?php foreach ($saved_cat_mapping as $idx => $map_row): ?>
                                                                        <tr>
                                                                            <td><input type="text" name="field_mapping[categories][cat_mapping][<?php echo (int)$idx; ?>][from]" value="<?php echo esc_attr($map_row['from'] ?? ''); ?>" /></td>
                                                                            <td><select name="field_mapping[categories][cat_mapping][<?php echo (int)$idx; ?>][to]">
                                                                                <option value=""><?php esc_html_e('— Select Category —', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="__new__"><?php esc_html_e('+ Create New', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <?php foreach ($categories_tree as $cat_item): ?>
                                                                                    <option value="<?php echo esc_attr($cat_item['id']); ?>" <?php selected($map_row['to'] ?? '', $cat_item['id']); ?>><?php echo esc_html($cat_item['label']); ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select></td>
                                                                            <td><button type="button" class="btn-remove-mapping-row" title="Remove">✕</button></td>
                                                                        </tr>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </tbody>
                                                            </table>
                                                            
                                                            <button type="button" class="button" id="btn-add-cat-mapping-row">
                                                                <span class="dashicons dashicons-plus" style="vertical-align: middle; margin-top: 2px;"></span>
                                                                <?php esc_html_e('Add Mapping Row', 'bootflow-product-xml-csv-importer'); ?>
                                                            </button>
                                                            
                                                            <?php
                                                            wp_add_inline_script(
                                                                'bfpi-import-admin',
                                                                'var bfpiCategories = ' . wp_json_encode($categories_tree) . ';',
                                                                'before'
                                                            );
                                                            ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Update on Sync for categories -->
                                                    <?php if ($can_selective_update): ?>
                                                    <div class="update-on-sync-wrapper" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                                        <label>
                                                            <input type="checkbox" name="field_mapping[categories][update_on_sync]" value="1" checked>
                                                            <span><?php esc_html_e('Update categories on re-import?', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                    </div>
                                                    <?php else: ?>
                                                    <input type="hidden" name="field_mapping[categories][update_on_sync]" value="1">
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- ══════════════════════════════════════════ -->
                                            <!-- PRODUCT TAGS                                 -->
                                            <!-- ══════════════════════════════════════════ -->
                                            <div class="taxonomy-block" data-taxonomy="tags" style="margin-bottom: 30px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                                                <div style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-bottom: 1px solid #90caf9; display: flex; align-items: center; gap: 10px;">
                                                    <span style="font-size: 22px;">🏷️</span>
                                                    <strong style="font-size: 15px; color: #1565c0;"><?php esc_html_e('Product Tags', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                </div>
                                                
                                                <div style="padding: 20px;">
                                                    <!-- Source Field -->
                                                    <div style="margin-bottom: 20px;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                                                            <?php esc_html_e('Source Field', 'bootflow-product-xml-csv-importer'); ?>
                                                        </label>
                                                        <div class="textarea-mapping-wrapper" data-field="tags">
                                                            <textarea name="field_mapping[tags][source]" 
                                                                      class="field-mapping-textarea" 
                                                                      rows="1"
                                                                      data-field-name="tags"
                                                                      placeholder="<?php esc_attr_e('Type { to see fields or drag field here...', 'bootflow-product-xml-csv-importer'); ?>"><?php echo esc_textarea($saved_tag_source); ?></textarea>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Tag Separator -->
                                                    <div style="margin-bottom: 20px;">
                                                        <label style="font-weight: 600; display: inline-block; margin-right: 10px;">
                                                            <?php esc_html_e('Tag separator:', 'bootflow-product-xml-csv-importer'); ?>
                                                        </label>
                                                        <input type="text" name="field_mapping[tags][tag_separator]" value="<?php echo esc_attr($saved_tag_separator); ?>" 
                                                               style="width: 80px; text-align: center; font-family: monospace; font-size: 14px; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;"
                                                               placeholder=",">
                                                        <span style="color: #888; margin-left: 8px; font-size: 12px;">
                                                            <?php esc_html_e('e.g., outdoor, camping, waterproof', 'bootflow-product-xml-csv-importer'); ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Tag Options -->
                                                    <div style="margin-bottom: 15px;">
                                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                            <input type="checkbox" name="field_mapping[tags][tag_auto_create]" value="1" checked>
                                                            <span><?php esc_html_e('Auto-create tags if they don\'t exist', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                    </div>
                                                    
                                                    <!-- Tag Mapping Table -->
                                                    <div style="margin-bottom: 10px;">
                                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 15px;">
                                                            <input type="checkbox" id="tag-enable-mapping" name="field_mapping[tags][tag_enable_mapping]" value="1" <?php checked($saved_tag_enable_mapping); ?>>
                                                            <strong><?php esc_html_e('Enable Tag Mapping', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <span style="color: #888; font-size: 12px; font-weight: normal;">
                                                                — <?php esc_html_e('Map feed values to specific WooCommerce tags', 'bootflow-product-xml-csv-importer'); ?>
                                                            </span>
                                                        </label>
                                                        
                                                        <div id="tag-mapping-table-wrap" style="display: <?php echo esc_attr($saved_tag_enable_mapping ? 'block' : 'none'); ?>;">
                                                            <table class="taxonomy-mapping-table widefat" style="margin-bottom: 10px;">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 45%;"><?php esc_html_e('Feed Value (from source)', 'bootflow-product-xml-csv-importer'); ?></th>
                                                                        <th style="width: 45%;"><?php esc_html_e('WooCommerce Tag', 'bootflow-product-xml-csv-importer'); ?></th>
                                                                        <th style="width: 10%;"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="tag-mapping-rows">
                                                                    <?php if (!empty($saved_tag_mapping)): ?>
                                                                        <?php foreach ($saved_tag_mapping as $idx => $map_row): ?>
                                                                        <tr>
                                                                            <td><input type="text" name="field_mapping[tags][tag_mapping][<?php echo (int)$idx; ?>][from]" value="<?php echo esc_attr($map_row['from'] ?? ''); ?>" /></td>
                                                                            <td><select name="field_mapping[tags][tag_mapping][<?php echo (int)$idx; ?>][to]">
                                                                                <option value=""><?php esc_html_e('\u2014 Select Tag \u2014', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="__new__"><?php esc_html_e('+ Create New', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <?php if (!is_wp_error($store_tags) && !empty($store_tags)): ?>
                                                                                    <?php foreach ($store_tags as $tag_item): ?>
                                                                                        <option value="<?php echo esc_attr($tag_item->term_id); ?>" <?php selected($map_row['to'] ?? '', $tag_item->term_id); ?>><?php echo esc_html($tag_item->name); ?></option>
                                                                                    <?php endforeach; ?>
                                                                                <?php endif; ?>
                                                                            </select></td>
                                                                            <td><button type="button" class="btn-remove-mapping-row" title="Remove">\u2715</button></td>
                                                                        </tr>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </tbody>
                                                            </table>
                                                            
                                                            <button type="button" class="button" id="btn-add-tag-mapping-row">
                                                                <span class="dashicons dashicons-plus" style="vertical-align: middle; margin-top: 2px;"></span>
                                                                <?php esc_html_e('Add Mapping Row', 'bootflow-product-xml-csv-importer'); ?>
                                                            </button>
                                                            
                                                            <?php
                                                            wp_add_inline_script(
                                                                'bfpi-import-admin',
                                                                'var bfpiTags = ' . wp_json_encode(
                                                                    !is_wp_error($store_tags) && !empty($store_tags) 
                                                                        ? array_map(function($t) { return array('id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug); }, $store_tags) 
                                                                        : array()
                                                                ) . ';',
                                                                'before'
                                                            );
                                                            ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Update on Sync for tags -->
                                                    <?php if ($can_selective_update): ?>
                                                    <div class="update-on-sync-wrapper" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                                        <label>
                                                            <input type="checkbox" name="field_mapping[tags][update_on_sync]" value="1" checked>
                                                            <span><?php esc_html_e('Update tags on re-import?', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                    </div>
                                                    <?php else: ?>
                                                    <input type="hidden" name="field_mapping[tags][update_on_sync]" value="1">
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- ══════════════════════════════════════════ -->
                                            <!-- BRAND                                        -->
                                            <!-- ══════════════════════════════════════════ -->
                                            <div class="taxonomy-block" data-taxonomy="brand" style="border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                                                <div style="padding: 15px 20px; background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%); border-bottom: 1px solid #f48fb1; display: flex; align-items: center; gap: 10px;">
                                                    <span style="font-size: 22px;">🏪</span>
                                                    <strong style="font-size: 15px; color: #c2185b;"><?php esc_html_e('Brand / Manufacturer', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                </div>
                                                
                                                <div style="padding: 20px;">
                                                    <!-- Source Field -->
                                                    <div style="margin-bottom: 20px;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                                                            <?php esc_html_e('Source Field', 'bootflow-product-xml-csv-importer'); ?>
                                                        </label>
                                                        <div class="textarea-mapping-wrapper" data-field="brand">
                                                            <textarea name="field_mapping[brand][source]" 
                                                                      class="field-mapping-textarea" 
                                                                      rows="1"
                                                                      data-field-name="brand"
                                                                      placeholder="<?php esc_attr_e('Type { to see fields or drag field here...', 'bootflow-product-xml-csv-importer'); ?>"><?php echo esc_textarea($saved_brand_source); ?></textarea>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Brand Options -->
                                                    <div style="margin-bottom: 15px;">
                                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                            <input type="checkbox" name="field_mapping[brand][brand_auto_create]" value="1" checked>
                                                            <span><?php esc_html_e('Auto-create brand if it doesn\'t exist', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                    </div>
                                                    
                                                    <!-- Brand Mapping Table -->
                                                    <div style="margin-bottom: 10px;">
                                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 15px;">
                                                            <input type="checkbox" id="brand-enable-mapping" name="field_mapping[brand][brand_enable_mapping]" value="1" <?php checked($saved_brand_enable_mapping); ?>>
                                                            <strong><?php esc_html_e('Enable Brand Mapping', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <span style="color: #888; font-size: 12px; font-weight: normal;">
                                                                — <?php esc_html_e('Map feed values to specific WooCommerce brands', 'bootflow-product-xml-csv-importer'); ?>
                                                            </span>
                                                        </label>
                                                        
                                                        <div id="brand-mapping-table-wrap" style="display: <?php echo esc_attr($saved_brand_enable_mapping ? 'block' : 'none'); ?>;">
                                                            <table class="taxonomy-mapping-table widefat" style="margin-bottom: 10px;">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 45%;"><?php esc_html_e('Feed Value (from source)', 'bootflow-product-xml-csv-importer'); ?></th>
                                                                        <th style="width: 45%;"><?php esc_html_e('WooCommerce Brand', 'bootflow-product-xml-csv-importer'); ?></th>
                                                                        <th style="width: 10%;"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="brand-mapping-rows">
                                                                    <?php if (!empty($saved_brand_mapping)): ?>
                                                                        <?php foreach ($saved_brand_mapping as $idx => $map_row): ?>
                                                                        <tr>
                                                                            <td><input type="text" name="field_mapping[brand][brand_mapping][<?php echo (int)$idx; ?>][from]" value="<?php echo esc_attr($map_row['from'] ?? ''); ?>" /></td>
                                                                            <td><select name="field_mapping[brand][brand_mapping][<?php echo (int)$idx; ?>][to]">
                                                                                <option value=""><?php esc_html_e('\u2014 Select Brand \u2014', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <option value="__new__"><?php esc_html_e('+ Create New', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                                <?php foreach ($store_brands as $brand_item): ?>
                                                                                    <option value="<?php echo esc_attr($brand_item->term_id); ?>" <?php selected($map_row['to'] ?? '', $brand_item->term_id); ?>><?php echo esc_html($brand_item->name); ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select></td>
                                                                            <td><button type="button" class="btn-remove-mapping-row" title="Remove">\u2715</button></td>
                                                                        </tr>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </tbody>
                                                            </table>
                                                            
                                                            <button type="button" class="button" id="btn-add-brand-mapping-row">
                                                                <span class="dashicons dashicons-plus" style="vertical-align: middle; margin-top: 2px;"></span>
                                                                <?php esc_html_e('Add Mapping Row', 'bootflow-product-xml-csv-importer'); ?>
                                                            </button>
                                                            
                                                            <?php
                                                            wp_add_inline_script(
                                                                'bfpi-import-admin',
                                                                'var bfpiBrands = ' . wp_json_encode(
                                                                    !empty($store_brands) 
                                                                        ? array_map(function($b) { return array('id' => $b->term_id, 'name' => $b->name, 'slug' => $b->slug); }, $store_brands)
                                                                        : array()
                                                                ) . ';',
                                                                'before'
                                                            );
                                                            ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Update on Sync for brand -->
                                                    <?php if ($can_selective_update): ?>
                                                    <div class="update-on-sync-wrapper" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                                        <label>
                                                            <input type="checkbox" name="field_mapping[brand][update_on_sync]" value="1" checked>
                                                            <span><?php esc_html_e('Update brand on re-import?', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </label>
                                                    </div>
                                                    <?php else: ?>
                                                    <input type="hidden" name="field_mapping[brand][update_on_sync]" value="1">
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                        </div><!-- /taxonomy-mapping-container -->

                                    <?php else: ?>
                                    <?php $is_first_field_in_section = true; ?>
                                    <?php foreach ($section['fields'] as $field_key => $field): ?>
                                        <?php if ($field_key === 'shipping_class_formula'): ?>
                                            <!-- Shipping Class Formula - separate from regular mapping -->
                                            <div class="shipping-class-formula-section" style="padding: 20px; background: #f9f9f9; border-radius: 4px; margin-bottom: 20px;">
                                                <h4 style="margin-top: 0;">🧮 <?php esc_html_e('Auto Shipping Class Formula', 'bootflow-product-xml-csv-importer'); ?></h4>
                                                <p class="description" style="margin-bottom: 15px;">
                                                    <?php esc_html_e('Optional: Calculate shipping class based on dimensions/weight when no direct mapping is set.', 'bootflow-product-xml-csv-importer'); ?>
                                                </p>
                                                
                                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">
                                                    <?php esc_html_e('PHP Formula (return shipping class slug):', 'bootflow-product-xml-csv-importer'); ?>
                                                </label>
                                                
                                                <textarea name="field_mapping[shipping_class_formula][formula]" 
                                                          id="shipping-class-formula" 
                                                          rows="12" 
                                                          style="width: 100%; font-family: 'Courier New', monospace; font-size: 13px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                                                          placeholder="// Available variables: $weight, $length, $width, $height&#10;&#10;if ($weight > 30) {&#10;    return 'Smags';&#10;}&#10;&#10;if ($height <= 8 && $length <= 38 && $width <= 64) {&#10;    return 'S';&#10;}&#10;&#10;if ($height <= 39 && $length <= 38 && $width <= 64) {&#10;    return 'M';&#10;}&#10;&#10;return 'L';"></textarea>
                                                
                                                <button type="button" class="button button-small test-shipping-formula" style="margin-top: 10px;">
                                                    <?php esc_html_e('Test Shipping Formula', 'bootflow-product-xml-csv-importer'); ?>
                                                </button>
                                                
                                                <div style="margin-top: 10px; padding: 10px; background: #fff; border-left: 3px solid #0073aa; border-radius: 3px;">
                                                    <strong><?php esc_html_e('Available Variables:', 'bootflow-product-xml-csv-importer'); ?></strong><br>
                                                    <code>$weight</code>, <code>$length</code>, <code>$width</code>, <code>$height</code>
                                                    <br><br>
                                                    <strong><?php esc_html_e('Available Shipping Classes:', 'bootflow-product-xml-csv-importer'); ?></strong><br>
                                                    <?php
                                                    $shipping_classes = get_terms(array(
                                                        'taxonomy' => 'product_shipping_class',
                                                        'hide_empty' => false,
                                                    ));
                                                    if (!empty($shipping_classes) && !is_wp_error($shipping_classes)):
                                                        foreach ($shipping_classes as $class):
                                                            echo '<code>' . esc_html($class->slug) . '</code> (' . esc_html($class->name) . ') ';
                                                        endforeach;
                                                    else:
                                                        esc_html_e('No shipping classes found. Create them in WooCommerce → Settings → Shipping', 'bootflow-product-xml-csv-importer');
                                                    endif;
                                                    ?>
                                                    <br><br>
                                                    <em><?php esc_html_e('This formula is used only when no direct mapping is set above. Leave empty to skip.', 'bootflow-product-xml-csv-importer'); ?></em>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                        <div class="field-mapping-row" data-field="<?php echo esc_attr($field_key); ?>" data-field-type="<?php echo esc_attr($field['type']); ?>">
                                            <div class="field-target">
                                                <label class="field-label <?php echo esc_attr($field['required'] ? 'required' : ''); ?>">
                                                    <?php echo esc_html($field['label']); ?>
                                                    <?php if ($field['required']): ?>
                                                        <span class="required-asterisk">*</span>
                                                    <?php endif; ?>
                                                </label>
                                                <span class="field-type"><?php echo esc_html($field['type']); ?></span>
                                            </div>
                                            
                                            <div class="field-source">
                                                <?php 
                                                // Get Tax Classes for tax_class_select
                                                $tax_classes = array();
                                                if ($field['type'] === 'tax_class_select') {
                                                    $tax_classes = WC_Tax::get_tax_classes();
                                                    array_unshift($tax_classes, ''); // Standard rate
                                                }
                                                
                                                // Render based on field type
                                                switch ($field['type']):
                                                    case 'boolean': ?>
                                                        <!-- Boolean Field: Yes / No / Map from XML -->
                                                        <div class="boolean-field-options">
                                                            <label class="boolean-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][boolean_mode]" 
                                                                       value="yes" 
                                                                       class="boolean-mode-radio">
                                                                <span class="boolean-label boolean-yes"><?php esc_html_e('Yes', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                            <label class="boolean-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][boolean_mode]" 
                                                                       value="no" 
                                                                       class="boolean-mode-radio"
                                                                       checked>
                                                                <span class="boolean-label boolean-no"><?php esc_html_e('No', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                            <label class="boolean-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][boolean_mode]" 
                                                                       value="map" 
                                                                       class="boolean-mode-radio">
                                                                <span class="boolean-label boolean-map"><?php esc_html_e('Map', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                        </div>
                                                        <div class="boolean-map-field" style="display: none; margin-top: 8px;">
                                                            <select name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" class="field-source-select" style="width: 100%;">
                                                                <option value=""><?php esc_html_e('-- Select XML Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                            </select>
                                                            <p class="description" style="font-size: 11px; margin-top: 4px;">
                                                                <?php esc_html_e('XML values: yes/no, true/false, 1/0', 'bootflow-product-xml-csv-importer'); ?>
                                                            </p>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'product_type_select': ?>
                                                        <!-- Product Type Select: Dropdown + Map option -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                                                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($opt_value, 'simple'); ?>>
                                                                            <?php echo esc_html($opt_label); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php esc_html_e('Map from XML:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php esc_html_e('-- Select XML Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                                <p class="description" style="font-size: 11px; margin-top: 4px;">
                                                                    <?php esc_html_e('Values: simple, variable, grouped, external', 'bootflow-product-xml-csv-importer'); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'status_select':
                                                        // Pre-fill from saved mapping, else from step-1 default_status
                                                        $saved_status_mapping = isset($saved_mappings[$field_key]) && is_array($saved_mappings[$field_key]) ? $saved_mappings[$field_key] : array();
                                                        $status_select_mode = isset($saved_status_mapping['select_mode']) && $saved_status_mapping['select_mode'] === 'map' ? 'map' : 'fixed';
                                                        $import_default_status = isset($import['default_status']) && in_array($import['default_status'], array('publish', 'draft', 'private', 'pending'), true) ? $import['default_status'] : 'publish';
                                                        $status_fixed_value = isset($saved_status_mapping['fixed_value']) && in_array($saved_status_mapping['fixed_value'], array('publish', 'draft', 'private', 'pending'), true) ? $saved_status_mapping['fixed_value'] : $import_default_status;
                                                        $status_saved_source = isset($saved_status_mapping['source']) ? $saved_status_mapping['source'] : '';
                                                        ?>
                                                        <!-- Status Select: Dropdown + Map option -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       <?php checked($status_select_mode, 'fixed'); ?>>
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                                                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($opt_value, $status_fixed_value); ?>>
                                                                            <?php echo esc_html($opt_label); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio"
                                                                       <?php checked($status_select_mode, 'map'); ?>>
                                                                <span><?php esc_html_e('Map from XML:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="<?php echo $status_select_mode === 'map' ? '' : 'display: none;'; ?> margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" class="field-source-select" style="width: 100%;" data-saved-source="<?php echo esc_attr($status_saved_source); ?>">
                                                                    <option value=""><?php esc_html_e('-- Select XML Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'tax_status_select': ?>
                                                        <!-- Tax Status Select -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                                                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($opt_value, 'taxable'); ?>>
                                                                            <?php echo esc_html($opt_label); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php esc_html_e('Map from XML:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php esc_html_e('-- Select XML Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                                <p class="description" style="font-size: 11px; margin-top: 4px;">
                                                                    <?php esc_html_e('Expected values: taxable, shipping, none', 'bootflow-product-xml-csv-importer'); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'tax_class_select': ?>
                                                        <!-- Tax Class Select (dynamic from WooCommerce) -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <option value=""><?php esc_html_e('Standard', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <?php 
                                                                    foreach (WC_Tax::get_tax_classes() as $tax_class):
                                                                        $slug = sanitize_title($tax_class);
                                                                    ?>
                                                                        <option value="<?php echo esc_attr($slug); ?>">
                                                                            <?php echo esc_html($tax_class); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php esc_html_e('Map from XML:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php esc_html_e('-- Select XML Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'stock_status_select':
                                                    case 'backorders_select': ?>
                                                        <!-- Stock Status / Backorders Select -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                                                        <option value="<?php echo esc_attr($opt_value); ?>">
                                                                            <?php echo esc_html($opt_label); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php esc_html_e('Map from XML:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php esc_html_e('-- Select XML Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'identifier': ?>
                                                        <!-- Product Identifier Field with Primary checkbox -->
                                                        <div class="identifier-field-wrapper">
                                                            <select name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" class="field-source-select" style="width: 60%;">
                                                                <option value=""><?php esc_html_e('-- Select Source Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                            </select>
                                                            <label class="primary-identifier-label" style="margin-left: 10px; display: inline-flex; align-items: center; gap: 5px;">
                                                                <input type="checkbox" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][is_primary]" 
                                                                       value="1" 
                                                                       class="primary-identifier-checkbox"
                                                                       data-identifier="<?php echo esc_attr($field_key); ?>">
                                                                <span style="font-size: 11px; color: #666;"><?php esc_html_e('Use as primary identifier (WC UI)', 'bootflow-product-xml-csv-importer'); ?></span>
                                                            </label>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'textarea': 
                                                        $saved_textarea_value = isset($saved_mappings[$field_key]['source']) ? $saved_mappings[$field_key]['source'] : '';
                                                        ?>
                                                        <textarea name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" 
                                                                  class="field-source-textarea" 
                                                                  rows="3" 
                                                                  style="width:100%;" 
                                                                  placeholder="<?php echo esc_attr($field['description'] ?? ''); ?>"><?php echo esc_textarea($saved_textarea_value); ?></textarea>
                                                        <?php if (!empty($field['description'])): ?>
                                                            <p class="description" style="margin-top: 5px; font-size: 11px;"><?php echo esc_html($field['description']); ?></p>
                                                        <?php endif; ?>
                                                    <?php break;
                                                    
                                                    case 'sku_with_generate': ?>
                                                        <!-- SKU Field with Generate option -->
                                                        <div class="sku-field-options">
                                                            <div class="sku-mode-selector" style="margin-bottom: 10px;">
                                                                <label class="sku-mode-option" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 15px; cursor: pointer;">
                                                                    <input type="radio" 
                                                                           name="field_mapping[<?php echo esc_attr($field_key); ?>][sku_mode]" 
                                                                           value="map" 
                                                                           class="sku-mode-radio"
                                                                           checked>
                                                                    <span style="font-weight: 500;"><?php esc_html_e('Map from file', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                </label>
                                                                <label class="sku-mode-option" style="display: inline-flex; align-items: center; gap: 5px; cursor: pointer;">
                                                                    <input type="radio" 
                                                                           name="field_mapping[<?php echo esc_attr($field_key); ?>][sku_mode]" 
                                                                           value="generate" 
                                                                           class="sku-mode-radio">
                                                                    <span style="font-weight: 500;"><?php esc_html_e('Auto-generate', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                </label>
                                                            </div>
                                                            
                                                            <!-- Map from file panel -->
                                                            <div class="sku-map-panel" style="display: block;">
                                                                <select name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php esc_html_e('-- Select Source Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                            </div>
                                                            
                                                            <!-- Auto-generate panel -->
                                                            <div class="sku-generate-panel" style="display: none; background: #f0f7ff; padding: 12px; border-radius: 6px; border: 1px solid #c3d9f3;">
                                                                <label style="display: block; font-weight: 500; margin-bottom: 8px;">
                                                                    <?php esc_html_e('SKU Pattern:', 'bootflow-product-xml-csv-importer'); ?>
                                                                </label>
                                                                <input type="text" 
                                                                       name="field_mapping[<?php echo esc_attr($field_key); ?>][sku_pattern]" 
                                                                       class="sku-pattern-input" 
                                                                       value="PROD-{row}" 
                                                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                                                       placeholder="PROD-{row}">
                                                                <p class="description" style="font-size: 11px; margin-top: 8px; color: #666;">
                                                                    <strong><?php esc_html_e('Available placeholders:', 'bootflow-product-xml-csv-importer'); ?></strong><br>
                                                                    <code>{row}</code> - <?php esc_html_e('Row number (1, 2, 3...)', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                    <code>{timestamp}</code> - <?php esc_html_e('Unix timestamp', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                    <code>{random}</code> - <?php esc_html_e('Random 6-char string', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                    <code>{name}</code> - <?php esc_html_e('Product name slug (first 20 chars)', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                    <code>{md5}</code> - <?php esc_html_e('MD5 hash from name+row (8 chars)', 'bootflow-product-xml-csv-importer'); ?><br>
                                                                </p>
                                                                <div style="margin-top: 10px; padding: 8px; background: #fff; border-radius: 4px; border: 1px solid #ddd;">
                                                                    <span style="font-size: 11px; color: #666;"><?php esc_html_e('Preview:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <code class="sku-preview" style="display: block; margin-top: 4px; font-size: 13px; color: #0073aa;">PROD-1</code>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    default: 
                                                        // Determine textarea size: large for description fields
                                                        $is_large_textarea = in_array($field_key, array('description', 'short_description', 'purchase_note', 'meta_description'));
                                                        $textarea_rows = $is_large_textarea ? 4 : 1;
                                                        $textarea_class = $is_large_textarea ? 'field-mapping-textarea field-mapping-textarea-large' : 'field-mapping-textarea field-mapping-textarea-small';
                                                        ?>
                                                        <div class="textarea-mapping-wrapper" data-field="<?php echo esc_attr($field_key); ?>">
                                                            <textarea name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" 
                                                                      class="<?php echo esc_attr($textarea_class); ?>" 
                                                                      rows="<?php echo esc_attr($textarea_rows); ?>"
                                                                      data-field-name="<?php echo esc_attr($field_key); ?>"
                                                                      placeholder="<?php 
                                                                      // translators: %s is the field key example
                                                                      echo esc_attr(sprintf(__('Type { to see fields or drag field here. E.g. {%s}', 'bootflow-product-xml-csv-importer'), strtolower(str_replace('_', '', $field_key)))); ?>"
                                                            ></textarea>
                                                            <?php if (!empty($field['description'])): ?>
                                                                <p class="description" style="margin-top: 4px; font-size: 11px;"><?php echo esc_html($field['description']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                <?php endswitch; ?>
                                            </div>
                                            
                                            
                                            
                                            
                                            
                                            <!-- Update on Sync Checkbox -->
                                            <?php if ($can_selective_update): ?>
                                            <div class="update-on-sync-wrapper">
                                                <label>
                                                    <input type="checkbox" 
                                                           name="field_mapping[<?php echo esc_attr($field_key); ?>][update_on_sync]" 
                                                           value="1" 
                                                           checked>
                                                    <span>
                                                        <?php esc_html_e('Update this field on re-import?', 'bootflow-product-xml-csv-importer'); ?>
                                                    </span>
                                                </label>
                                                <p class="description">
                                                    <?php esc_html_e('Uncheck to prevent this field from being updated when re-importing existing products', 'bootflow-product-xml-csv-importer'); ?>
                                                </p>
                                            </div>
                                            <?php else: ?>
                                            <input type="hidden" name="field_mapping[<?php echo esc_attr($field_key); ?>][update_on_sync]" value="1">
                                            <?php endif; ?>
                                            
                                            <div class="field-actions">
                                                <button type="button" class="button button-small clear-mapping" title="<?php esc_html_e('Clear Mapping', 'bootflow-product-xml-csv-importer'); ?>">
                                                    <span class="dashicons dashicons-no-alt"></span>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php endif; // End attributes_variations special handling ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Import Filters Section -->
                    <div class="mapping-section import-filters-section">
                        <h3 style="display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; margin: 0; background: #f7f7f7;">
                            <span>
                                <span class="dashicons dashicons-filter"></span>
                                <?php esc_html_e('Import Filters', 'bootflow-product-xml-csv-importer'); ?>
                            </span>
                            <button type="button" class="button button-small" id="add-filter-rule">
                                <span class="dashicons dashicons-plus" style="vertical-align: middle;"></span>
                                <?php esc_html_e('Add Filter', 'bootflow-product-xml-csv-importer'); ?>
                            </button>
                        </h3>
                        <p class="description" style="margin: 10px 0 15px 0; padding: 0 15px;">
                            <?php esc_html_e('Filter which products to import based on field values. Products that don\'t match will be skipped.', 'bootflow-product-xml-csv-importer'); ?>
                        </p>
                        
                        <div class="section-fields" id="section-import-filters" style="display: block;">
                            <div id="import-filters-container">
                                <p class="no-filters" style="padding: 15px; color: #666;">
                                    <?php esc_html_e('No filters added. All products will be imported.', 'bootflow-product-xml-csv-importer'); ?>
                                </p>
                            </div>
                            
                            <div class="filter-logic-toggle" id="filter-logic-toggle" style="display: none; margin: 15px; padding: 12px; background: #f5f5f5; border-radius: 4px;">
                                <label style="font-weight: 600; margin-right: 10px;">
                                    <?php esc_html_e('Filter Logic:', 'bootflow-product-xml-csv-importer'); ?>
                                </label>
                                <label style="margin-right: 20px;">
                                    <input type="radio" name="filter_logic" value="AND" checked />
                                    <strong>AND</strong> <?php esc_html_e('(all conditions must match)', 'bootflow-product-xml-csv-importer'); ?>
                                </label>
                                <label>
                                    <input type="radio" name="filter_logic" value="OR" />
                                    <strong>OR</strong> <?php esc_html_e('(any condition can match)', 'bootflow-product-xml-csv-importer'); ?>
                                </label>
                            </div>
                            
                            <!-- Filter Options Note -->
                            <div id="filter-options-note" style="display: none; margin: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; font-weight: 600; color: #856404;">
                                    <span class="dashicons dashicons-info" style="color: #ffc107;"></span>
                                    <?php esc_html_e('Filter Behavior', 'bootflow-product-xml-csv-importer'); ?>
                                </p>
                                <p style="margin: 0 0 10px 0; font-size: 13px; color: #856404;">
                                    <?php esc_html_e('Filters will be applied during import. Products that don\'t match will be skipped.', 'bootflow-product-xml-csv-importer'); ?>
                                </p>
                                <label style="display: block; margin-top: 10px;">
                                    <input type="checkbox" name="draft_non_matching" value="1" id="draft-non-matching-checkbox" />
                                    <strong><?php esc_html_e('Move non-matching products to Draft', 'bootflow-product-xml-csv-importer'); ?></strong>
                                    <br>
                                    <span style="font-size: 12px; color: #666; margin-left: 20px;">
                                        <?php esc_html_e('If re-running import, existing products that no longer match filters will be set to Draft status.', 'bootflow-product-xml-csv-importer'); ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Fields Section -->
                    <div class="mapping-section custom-fields-section">
                        <h3 class="section-toggle" data-target="custom-fields">
                            <span class="dashicons dashicons-arrow-down"></span>
                            <?php esc_html_e('Custom Fields', 'bootflow-product-xml-csv-importer'); ?>
                            <button type="button" class="button button-small add-custom-field" id="add-custom-field">
                                <span class="dashicons dashicons-plus"></span>
                                <?php esc_html_e('Add Custom Field', 'bootflow-product-xml-csv-importer'); ?>
                            </button>
                        </h3>
                        
                        <div class="section-fields" id="section-custom-fields">
                            <div id="custom-fields-container">
                                <p class="no-custom-fields"><?php esc_html_e('No custom fields added yet. Click "Add Custom Field" to create one.', 'bootflow-product-xml-csv-importer'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=bfpi-import&step=1')); ?>" class="button button-secondary">
                            <span class="button-icon">⬅️</span>
                            <?php esc_html_e('Back to Upload', 'bootflow-product-xml-csv-importer'); ?>
                        </a>
                        
                        <div class="actions-right">
                            <button type="button" class="button button-secondary" id="save-mapping">
                                <?php esc_html_e('Save', 'bootflow-product-xml-csv-importer'); ?>
                            </button>
                            
                            <button type="submit" class="button button-primary button-large" id="start-import">
                                <?php esc_html_e('Start Import', 'bootflow-product-xml-csv-importer'); ?>
                                <span class="button-icon">🚀</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div id="mapping-messages" class="mapping-messages"></div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Filter Rule Template -->
<script type="text/template" id="filter-rule-template">
    <div class="filter-rule-row" data-filter-index="{index}" style="display: flex; gap: 10px; align-items: center; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
        <div style="flex: 1;">
            <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Field', 'bootflow-product-xml-csv-importer'); ?></label>
            <select name="import_filters[{index}][field]" class="filter-field-select" style="width: 100%;">
                <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
            </select>
        </div>
        
        <div style="flex: 0 0 150px;">
            <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Operator', 'bootflow-product-xml-csv-importer'); ?></label>
            <select name="import_filters[{index}][operator]" class="filter-operator-select" style="width: 100%;">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">">></option>
                <option value="<"><</option>
                <option value=">=">>=</option>
                <option value="<="><=</option>
                <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="not_contains"><?php esc_html_e('not contains', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="empty"><?php esc_html_e('is empty', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="not_empty"><?php esc_html_e('not empty', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="regex_match"><?php esc_html_e('regex match', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="regex_not_match"><?php esc_html_e('regex not match', 'bootflow-product-xml-csv-importer'); ?></option>
            </select>
        </div>
        
        <div style="flex: 1;">
            <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Value', 'bootflow-product-xml-csv-importer'); ?></label>
            <input type="text" name="import_filters[{index}][value]" class="filter-value-input" placeholder="<?php esc_html_e('Comparison value', 'bootflow-product-xml-csv-importer'); ?>" style="width: 100%;" />
        </div>
        
        <div style="flex: 0 0 40px;">
            <label style="display: block; font-size: 11px; color: transparent; margin-bottom: 3px;">.</label>
            <button type="button" class="button button-small remove-filter-rule" title="<?php esc_html_e('Remove Filter', 'bootflow-product-xml-csv-importer'); ?>" style="padding: 6px 10px;">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>
</script>

<!-- Custom Field Template -->
<script type="text/template" id="custom-field-template">
    <div class="field-mapping-row custom-field-row" data-field="custom-{index}">
        <div class="field-target">
            <input type="text" name="custom_fields[{index}][name]" placeholder="<?php esc_html_e('Custom Field Name', 'bootflow-product-xml-csv-importer'); ?>" class="custom-field-name" />
            <select name="custom_fields[{index}][type]" class="custom-field-type">
                <option value="text"><?php esc_html_e('Text', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="number"><?php esc_html_e('Number', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="textarea"><?php esc_html_e('Textarea', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="checkbox"><?php esc_html_e('Checkbox', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="date"><?php esc_html_e('Date', 'bootflow-product-xml-csv-importer'); ?></option>
                <option value="url"><?php esc_html_e('URL', 'bootflow-product-xml-csv-importer'); ?></option>
            </select>
        </div>
        
        <div class="field-source">
            <select name="custom_fields[{index}][source]" class="field-source-select">
                <option value=""><?php esc_html_e('-- Select Source Field --', 'bootflow-product-xml-csv-importer'); ?></option>
            </select>
        </div>
        
        
        
        <!-- Processing Config Panels for Custom Fields -->
        
        
        <!-- Update on Sync Checkbox -->
        <?php if ($can_selective_update): ?>
        <div class="update-on-sync-wrapper">
            <label>
                <input type="checkbox" 
                       name="custom_fields[{index}][update_on_sync]" 
                       value="1" 
                       checked>
                <span>
                    <?php esc_html_e('Update this field on re-import?', 'bootflow-product-xml-csv-importer'); ?>
                </span>
            </label>
            <p class="description">
                <?php esc_html_e('Uncheck to prevent this field from being updated when re-importing existing products', 'bootflow-product-xml-csv-importer'); ?>
            </p>
        </div>
        <?php else: ?>
        <input type="hidden" name="custom_fields[{index}][update_on_sync]" value="1">
        <?php endif; ?>
        
        <div class="field-actions">
            <button type="button" class="button button-small remove-custom-field" title="<?php esc_html_e('Remove Custom Field', 'bootflow-product-xml-csv-importer'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>
</script>

<!-- Attribute Template -->
<script type="text/template" id="attribute-row-template">
    <div class="attribute-row" data-index="{{index}}" style="padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div style="flex: 1;">
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('Attribute Name (WooCommerce):', 'bootflow-product-xml-csv-importer'); ?>
                    </label>
                    <input type="text" 
                           name="attributes[{{index}}][name]" 
                           class="attribute-name"
                           placeholder="<?php esc_attr_e('e.g., Izmērs, Krāsa, Material', 'bootflow-product-xml-csv-importer'); ?>"
                           style="width: 100%; max-width: 300px;">
                    <p class="description"><?php esc_html_e('Display name in WooCommerce (any language). Auto-adds pa_ prefix.', 'bootflow-product-xml-csv-importer'); ?></p>
                </div>
                
                <div style="margin-bottom: 15px; padding: 10px; background: #fff8e1; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('XML Attribute Key:', 'bootflow-product-xml-csv-importer'); ?>
                        <span style="color: #d63638; font-weight: normal;">*</span>
                    </label>
                    <input type="text" 
                           name="attributes[{{index}}][xml_attribute_key]" 
                           class="attribute-xml-key"
                           placeholder="<?php esc_attr_e('e.g., size, color, material', 'bootflow-product-xml-csv-importer'); ?>"
                           style="width: 100%; max-width: 300px;">
                    <p class="description" style="margin-top: 5px;">
                        <?php esc_html_e('<strong>Required for Map mode!</strong> The XML element name inside &lt;attributes&gt;.', 'bootflow-product-xml-csv-importer'); ?><br>
                        <?php esc_html_e('Example: if XML has <code>&lt;attributes&gt;&lt;size&gt;S&lt;/size&gt;&lt;/attributes&gt;</code> → enter <code>size</code>', 'bootflow-product-xml-csv-importer'); ?>
                    </p>
                </div>
                
                <div style="margin-bottom: 15px; display: none;" class="xml-attribute-name-field">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('XML Attribute Name (for name/value structure):', 'bootflow-product-xml-csv-importer'); ?>
                    </label>
                    <input type="text" 
                           name="attributes[{{index}}][xml_attribute_name]" 
                           class="attribute-xml-name"
                           placeholder="<?php esc_attr_e('e.g., Material', 'bootflow-product-xml-csv-importer'); ?>"
                           style="width: 100%; max-width: 300px;">
                    <p class="description"><?php esc_html_e('Only for XML with &lt;attribute&gt;&lt;name&gt;...&lt;/name&gt;&lt;value&gt;...&lt;/value&gt;&lt;/attribute&gt; structure', 'bootflow-product-xml-csv-importer'); ?></p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('Attribute Values (optional override):', 'bootflow-product-xml-csv-importer'); ?>
                    </label>
                    <div style="display: flex; gap: 10px; align-items: start; flex-wrap: wrap;">
                        <select name="attributes[{{index}}][values_source]" class="field-source-select attribute-values-source" style="flex: 1; max-width: 300px;">
                            <option value=""><?php esc_html_e('-- Select Source Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                        </select>
                        
                    </div>
                    <div class="attribute-values-config" style="margin-top: 10px; display: none;">
                        
                        
                        
                    </div>
                    <p class="description"><?php esc_html_e('Leave empty for XML &lt;attributes&gt; auto-mapping, or select field for direct mapping', 'bootflow-product-xml-csv-importer'); ?></p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('Attribute Image (optional):', 'bootflow-product-xml-csv-importer'); ?>
                    </label>
                    <div style="display: flex; gap: 10px; align-items: start; flex-wrap: wrap;">
                        <select name="attributes[{{index}}][image_source]" class="field-source-select attribute-image-source" style="flex: 1; max-width: 300px;">
                            <option value=""><?php esc_html_e('-- Select Source Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                        </select>
                        
                    </div>
                    <div class="attribute-image-config" style="margin-top: 10px; display: none;">
                        
                        
                        
                    </div>
                    <p class="description"><?php esc_html_e('Image URL for this attribute (e.g., color swatch, size chart). For variations, use variation image mapping below.', 'bootflow-product-xml-csv-importer'); ?></p>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <label style="display: inline-block; margin-right: 20px;">
                        <input type="checkbox" name="attributes[{{index}}][visible]" value="1" class="attribute-visible">
                        <?php esc_html_e('Visible on product page', 'bootflow-product-xml-csv-importer'); ?>
                    </label>
                    
                    <label style="display: inline-block;">
                        <input type="checkbox" name="attributes[{{index}}][used_for_variations]" value="1" class="attribute-variation-checkbox attribute-variations">
                        <strong><?php esc_html_e('Used for variations', 'bootflow-product-xml-csv-importer'); ?></strong>
                    </label>
                </div>
                
                <!-- Variation Settings (shown when "Used for variations" is checked) -->
                <div class="variation-attribute-settings" style="display: none; margin-top: 15px; padding: 15px; background: #f0f8ff; border: 2px solid #2271b1; border-radius: 6px;">
                    <h4 style="margin: 0 0 15px 0; color: #2271b1;">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('Variation Adjustments for this Attribute', 'bootflow-product-xml-csv-importer'); ?>
                    </h4>
                    <p class="description" style="margin-bottom: 15px;">
                        <?php esc_html_e('These settings apply to ALL variations created from this attribute. Leave as "No change" to use parent product values.', 'bootflow-product-xml-csv-importer'); ?>
                    </p>
                    
                    <!-- Price Adjustment -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-tag" style="color: #2271b1;"></span>
                            <?php esc_html_e('Price Adjustment:', 'bootflow-product-xml-csv-importer'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_price_type]" class="var-price-type-select" style="width: 130px;">
                                <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="operator"><?php esc_html_e('Calculate', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="map"><?php esc_html_e('Map field', 'bootflow-product-xml-csv-importer'); ?></option>
                            </select>
                            <!-- Calculate config -->
                            <div class="var-price-operator-config" style="display: none; gap: 5px; align-items: center;">
                                <select name="attributes[{{index}}][var_price_operator]" style="width: 60px;">
                                    <option value="+">+</option>
                                    <option value="-">−</option>
                                    <option value="*">×</option>
                                    <option value="/">÷</option>
                                </select>
                                <input type="number" step="0.01" name="attributes[{{index}}][var_price_value]" placeholder="0" style="width: 100px;">
                            </div>
                            <!-- Map field config -->
                            <div class="var-price-map-config" style="display: none; gap: 10px; align-items: center; flex: 1;">
                                <select name="attributes[{{index}}][var_price_source]" class="field-source-select" style="min-width: 200px;">
                                    <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                </select>
                                
                            </div>
                        </div>
                        <!-- Processing configs for mapped price -->
                        
                    </div>
                    
                    <!-- Stock Adjustment -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-products" style="color: #2271b1;"></span>
                            <?php esc_html_e('Stock Adjustment:', 'bootflow-product-xml-csv-importer'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_stock_type]" class="var-stock-type-select" style="width: 130px;">
                                <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="operator"><?php esc_html_e('Calculate', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="fixed"><?php esc_html_e('Fixed value', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="map"><?php esc_html_e('Map field', 'bootflow-product-xml-csv-importer'); ?></option>
                            </select>
                            <div class="var-stock-operator-config" style="display: none; flex: 1; gap: 5px; align-items: center;">
                                <select name="attributes[{{index}}][var_stock_operator]" style="width: 60px;">
                                    <option value="+">+</option>
                                    <option value="-">−</option>
                                </select>
                                <input type="number" step="1" name="attributes[{{index}}][var_stock_value]" placeholder="0" style="width: 100px;">
                            </div>
                            <div class="var-stock-fixed-config" style="display: none; flex: 1;">
                                <input type="number" step="1" name="attributes[{{index}}][var_stock_fixed]" placeholder="10" style="width: 100px;">
                            </div>
                            <div class="var-stock-map-config" style="display: none; flex: 1;">
                                <select name="attributes[{{index}}][var_stock_source]" class="field-source-select" style="flex: 1;">
                                    <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sale Price -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-money-alt" style="color: #d63638;"></span>
                            <?php esc_html_e('Sale Price:', 'bootflow-product-xml-csv-importer'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_sale_price_type]" class="var-sale-price-type-select" style="width: 130px;">
                                <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="fixed"><?php esc_html_e('Fixed value', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="map"><?php esc_html_e('Map field', 'bootflow-product-xml-csv-importer'); ?></option>
                            </select>
                            <div class="var-sale-price-fixed-config" style="display: none; flex: 1;">
                                <input type="number" step="0.01" name="attributes[{{index}}][var_sale_price_fixed]" placeholder="0.00" style="width: 120px;">
                            </div>
                            <div class="var-sale-price-map-config" style="display: none; flex: 1;">
                                <select name="attributes[{{index}}][var_sale_price_source]" class="field-source-select" style="flex: 1;">
                                    <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SKU -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-tag" style="color: #135e96;"></span>
                            <?php esc_html_e('SKU:', 'bootflow-product-xml-csv-importer'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_sku_type]" class="var-sku-type-select" style="width: 130px;">
                                <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="suffix"><?php esc_html_e('Suffix', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="map"><?php esc_html_e('Map field', 'bootflow-product-xml-csv-importer'); ?></option>
                            </select>
                            <div class="var-sku-suffix-config" style="display: none; flex: 1;">
                                <input type="text" name="attributes[{{index}}][var_sku_suffix]" placeholder="<?php esc_attr_e('e.g., -red, -xl', 'bootflow-product-xml-csv-importer'); ?>" style="width: 150px;">
                                <span style="color: #666; font-size: 11px;"><?php esc_html_e('Added to parent SKU', 'bootflow-product-xml-csv-importer'); ?></span>
                            </div>
                            <div class="var-sku-map-config" style="display: none; flex: 1;">
                                <select name="attributes[{{index}}][var_sku_source]" class="field-source-select" style="flex: 1;">
                                    <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- GTIN/UPC/EAN/ISBN -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-barcode" style="color: #135e96;"></span>
                            <?php esc_html_e('GTIN/UPC/EAN/ISBN:', 'bootflow-product-xml-csv-importer'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_gtin_type]" class="var-gtin-type-select" style="width: 130px;">
                                <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="map"><?php esc_html_e('Map field', 'bootflow-product-xml-csv-importer'); ?></option>
                            </select>
                            <div class="var-gtin-map-config" style="display: none; flex: 1;">
                                <select name="attributes[{{index}}][var_gtin_source]" class="field-source-select" style="flex: 1;">
                                    <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings (collapsed) -->
                    <details style="margin-top: 15px;">
                        <summary style="cursor: pointer; font-weight: 600; padding: 8px; background: #e8f4fc; border-radius: 3px;">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                            <?php esc_html_e('Advanced (Status, Shipping, Dimensions)', 'bootflow-product-xml-csv-importer'); ?>
                        </summary>
                        <div style="padding: 15px; background: #fafafa; border-radius: 3px; margin-top: 5px;">
                            
                            <!-- Status Fields Row -->
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                                <!-- Enabled -->
                                <div class="setting-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 4px;" title="<?php esc_attr_e('Whether the variation is available for purchase on the frontend', 'bootflow-product-xml-csv-importer'); ?>">
                                        <?php esc_html_e('Enabled:', 'bootflow-product-xml-csv-importer'); ?>
                                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; color: #666; cursor: help;"></span>
                                    </label>
                                    <select name="attributes[{{index}}][var_enabled]" style="width: 100%;">
                                        <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="yes"><?php esc_html_e('Yes', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="no"><?php esc_html_e('No', 'bootflow-product-xml-csv-importer'); ?></option>
                                    </select>
                                    <p class="description" style="font-size: 10px; color: #888; margin: 4px 0 0 0;"><?php esc_html_e('Show on frontend', 'bootflow-product-xml-csv-importer'); ?></p>
                                </div>
                                
                                <!-- Virtual -->
                                <div class="setting-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 4px;" title="<?php esc_attr_e('Virtual products have no shipping (e.g., services, consultations)', 'bootflow-product-xml-csv-importer'); ?>">
                                        <?php esc_html_e('Virtual:', 'bootflow-product-xml-csv-importer'); ?>
                                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; color: #666; cursor: help;"></span>
                                    </label>
                                    <select name="attributes[{{index}}][var_virtual]" style="width: 100%;">
                                        <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="yes"><?php esc_html_e('Yes', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="no"><?php esc_html_e('No', 'bootflow-product-xml-csv-importer'); ?></option>
                                    </select>
                                    <p class="description" style="font-size: 10px; color: #888; margin: 4px 0 0 0;"><?php esc_html_e('No shipping needed', 'bootflow-product-xml-csv-importer'); ?></p>
                                </div>
                                
                                <!-- Downloadable -->
                                <div class="setting-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 4px;" title="<?php esc_attr_e('Downloadable products give access to files after purchase (e.g., ebooks, software)', 'bootflow-product-xml-csv-importer'); ?>">
                                        <?php esc_html_e('Downloadable:', 'bootflow-product-xml-csv-importer'); ?>
                                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; color: #666; cursor: help;"></span>
                                    </label>
                                    <select name="attributes[{{index}}][var_downloadable]" style="width: 100%;">
                                        <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="yes"><?php esc_html_e('Yes', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="no"><?php esc_html_e('No', 'bootflow-product-xml-csv-importer'); ?></option>
                                    </select>
                                    <p class="description" style="font-size: 10px; color: #888; margin: 4px 0 0 0;"><?php esc_html_e('Has file downloads', 'bootflow-product-xml-csv-importer'); ?></p>
                                </div>
                                
                                <!-- Manage Stock -->
                                <div class="setting-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 4px;" title="<?php esc_attr_e('Enable stock management at variation level (track inventory for each variation separately)', 'bootflow-product-xml-csv-importer'); ?>">
                                        <?php esc_html_e('Manage Stock:', 'bootflow-product-xml-csv-importer'); ?>
                                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; color: #666; cursor: help;"></span>
                                    </label>
                                    <select name="attributes[{{index}}][var_manage_stock]" style="width: 100%;">
                                        <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="yes"><?php esc_html_e('Yes', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="no"><?php esc_html_e('No', 'bootflow-product-xml-csv-importer'); ?></option>
                                    </select>
                                    <p class="description" style="font-size: 10px; color: #888; margin: 4px 0 0 0;"><?php esc_html_e('Track inventory', 'bootflow-product-xml-csv-importer'); ?></p>
                                </div>
                            </div>
                            
                            <!-- Weight -->
                            <div class="setting-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e('Weight:', 'bootflow-product-xml-csv-importer'); ?></label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <select name="attributes[{{index}}][var_weight_type]" class="var-weight-type-select" style="width: 130px;">
                                        <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="operator"><?php esc_html_e('Calculate', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="map"><?php esc_html_e('Map field', 'bootflow-product-xml-csv-importer'); ?></option>
                                    </select>
                                    <div class="var-weight-operator-config" style="display: none; flex: 1; gap: 5px;">
                                        <select name="attributes[{{index}}][var_weight_operator]" style="width: 60px;">
                                            <option value="+">+</option>
                                            <option value="-">−</option>
                                            <option value="*">×</option>
                                        </select>
                                        <input type="number" step="0.01" name="attributes[{{index}}][var_weight_value]" placeholder="0" style="width: 100px;">
                                        <span style="color: #666; font-size: 12px;">kg</span>
                                    </div>
                                    <div class="var-weight-map-config" style="display: none; flex: 1;">
                                        <select name="attributes[{{index}}][var_weight_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dimensions -->
                            <div class="setting-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e('Dimensions (L×W×H):', 'bootflow-product-xml-csv-importer'); ?></label>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php esc_html_e('Length:', 'bootflow-product-xml-csv-importer'); ?></label>
                                        <select name="attributes[{{index}}][var_length_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php esc_html_e('Width:', 'bootflow-product-xml-csv-importer'); ?></label>
                                        <select name="attributes[{{index}}][var_width_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php esc_html_e('Height:', 'bootflow-product-xml-csv-importer'); ?></label>
                                        <select name="attributes[{{index}}][var_height_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Variation Image -->
                            <div class="setting-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e('Variation Image:', 'bootflow-product-xml-csv-importer'); ?></label>
                                <select name="attributes[{{index}}][var_image_source]" class="field-source-select" style="width: 100%;">
                                    <option value=""><?php esc_html_e('-- Select Image Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                </select>
                            </div>
                            
                            <!-- Shipping Class -->
                            <div class="setting-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e('Shipping Class:', 'bootflow-product-xml-csv-importer'); ?></label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <select name="attributes[{{index}}][var_shipping_class_type]" class="var-shipping-class-type-select" style="width: 130px;">
                                        <option value="none"><?php esc_html_e('No change', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="map"><?php esc_html_e('Map field', 'bootflow-product-xml-csv-importer'); ?></option>
                                    </select>
                                    <div class="var-shipping-class-map-config" style="display: none; flex: 1;">
                                        <select name="attributes[{{index}}][var_shipping_class_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="setting-group">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e('Description:', 'bootflow-product-xml-csv-importer'); ?></label>
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <select name="attributes[{{index}}][var_description_source]" class="field-source-select var-description-source-select" style="min-width: 200px;">
                                        <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                    </select>
                                    
                                </div>
                                <!-- Processing configs for description -->
                                
                            </div>
                        </div>
                    </details>
                </div>
            </div>
            
            <button type="button" class="button button-link-delete remove-attribute" data-index="{{index}}" style="margin-left: 10px;">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
    </div>
</script>

<?php
// Pass data and behavior to JavaScript via wp_add_inline_script
$bfpi_step2_data = array(
    'file_path'       => $file_path,
    'file_type'       => $file_type,
    'import_name'     => $import_name,
    'schedule_type'   => $schedule_type,
    'product_wrapper' => $product_wrapper,
    'update_existing' => $update_existing,
    'skip_unchanged'  => $skip_unchanged,
    'ajax_url'        => admin_url('admin-ajax.php'),
    'nonce'           => wp_create_nonce('bfpi_nonce'),
    'total_products'  => intval($total_products_from_session),
    'saved_mappings'  => $saved_mappings,
    'saved_custom_fields' => $saved_custom_fields,
);

ob_start();
?>
var bfpiImportData = <?php echo wp_json_encode($bfpi_step2_data); ?>;
// Copy ajax_url and nonce from localized data if not set
if (typeof bfpi_ajax !== 'undefined') {
    bfpiImportData.ajax_url = bfpi_ajax.ajax_url;
    bfpiImportData.nonce = bfpi_ajax.nonce;
}

// Show product count if available
jQuery(document).ready(function($) {
    if (bfpiImportData.total_products > 0) {
        $('#total-products-count').text(bfpiImportData.total_products.toLocaleString());
        $('#total-products-info').show();
    }
    
    // Schedule Type change - show/hide schedule_method_row
    $('select[name="schedule_type"]').on('change', function() {
        var selectedValue = $(this).val();
        if (selectedValue && selectedValue !== 'disabled') {
            $('#schedule_method_row_new').show();
        } else {
            $('#schedule_method_row_new').hide();
        }
    });
    
    // Schedule Method change - show/hide server cron URL
    $('input[name="schedule_method"]').on('change', function() {
        var selectedMethod = $(this).val();
        
        // Update radio button styles
        $('input[name="schedule_method"]').each(function() {
            var $label = $(this).closest('label');
            if ($(this).is(':checked')) {
                $label.css({'border-color': '#0073aa', 'background': '#f0f6fc'});
            } else {
                $label.css({'border-color': '#ddd', 'background': '#fff'});
            }
        });
        
        // Show/hide server cron URL
        if (selectedMethod === 'server_cron') {
            $('#server_cron_url_new').show();
        } else {
            $('#server_cron_url_new').hide();
        }
    });
});
<?php
$bfpi_step2_js = ob_get_clean();
wp_add_inline_script('bfpi-import-admin', $bfpi_step2_js, 'after');
?>