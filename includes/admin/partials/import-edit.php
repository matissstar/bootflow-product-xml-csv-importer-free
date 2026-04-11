<?php
/**
 * Import Edit/View Page
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

// Ensure file_path is set for AJAX - use latest XML if missing
if (empty($import['file_url']) || !file_exists($import['file_url'])) {
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/bfpi-import/';
    if (is_dir($plugin_upload_dir)) {
        $files = glob($plugin_upload_dir . '*.xml');
        if ($files && count($files) > 0) {
            // Use the most recently modified XML file
            usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
            $import['file_url'] = $files[0];
        }
    }
}

// For backwards compatibility, also set file_path
$import['file_path'] = $import['file_url'] ?? '';
$import['file_type'] = $import['file_type'] ?? 'xml';
$import['product_wrapper'] = $import['product_wrapper'] ?? 'product';
$import['schedule_type'] = $import['schedule_type'] ?? 'disabled';
$import['update_existing'] = $import['update_existing'] ?? '0';

// Load file_fields from file structure for filter dropdowns
$file_fields = array();
$debug_file_path = $import['file_path'] ?? 'NOT SET';
$debug_file_exists = (!empty($import['file_path']) && file_exists($import['file_path'])) ? 'YES' : 'NO';

if (!empty($import['file_path']) && file_exists($import['file_path'])) {
    try {
        if ($import['file_type'] === 'xml') {
            $xml_parser = new Bfpi_XML_Parser();
            $parsed = $xml_parser->parse_structure($import['file_path'], $import['product_wrapper'] ?? 'product');
            // parse_structure returns 'structure' key, not 'sample_fields'
            $structure_data = $parsed['structure'] ?? array();
            // Extract field paths from structure
            foreach ($structure_data as $field_info) {
                if (isset($field_info['path'])) {
                    $file_fields[] = $field_info['path'];
                }
            }
        }
    } catch (Exception $e) {
        $debug_error = $e->getMessage();
    }
}
?>
<!-- DEBUG: file_path=<?php echo esc_html($debug_file_path); ?>, file_exists=<?php echo esc_html($debug_file_exists); ?>, fields_count=<?php echo esc_html(count($file_fields)); ?>, first_5=<?php echo esc_html(implode(', ', array_slice($file_fields, 0, 5))); ?> -->
<div class="wrap bfpi-step bfpi-step-2">
    <h1><?php echo esc_html__('Import Details:', 'bootflow-product-xml-csv-importer') . ' ' . esc_html($import['name']); ?></h1>
    
    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=bfpi-import-history')); ?>" class="button">
            ⬅️ <?php esc_html_e('Back to Import History', 'bootflow-product-xml-csv-importer'); ?>
        </a>
    </p>
    
    <div class="bfpi-card">
        <h2><?php esc_html_e('Import Information', 'bootflow-product-xml-csv-importer'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Name', 'bootflow-product-xml-csv-importer'); ?></th>
                <td><strong><?php echo esc_html($import['name']); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('File Type', 'bootflow-product-xml-csv-importer'); ?></th>
                <td><?php echo esc_html(strtoupper($import['file_type'])); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Status', 'bootflow-product-xml-csv-importer'); ?></th>
                <td><strong><?php echo esc_html(ucfirst($import['status'])); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Products', 'bootflow-product-xml-csv-importer'); ?></th>
                <td><?php echo esc_html($import['processed_products'] . '/' . $import['total_products']); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Created', 'bootflow-product-xml-csv-importer'); ?></th>
                <td><?php echo esc_html(Bfpi_i18n::localize_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import['created_at']))); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Last Run', 'bootflow-product-xml-csv-importer'); ?></th>
                <td>
                    <?php if ($import['last_run']): ?>
                        <?php
                        $last_run_ts = strtotime($import['last_run']);
                        $ago_seconds = current_time('timestamp') - $last_run_ts;
                        if ($ago_seconds < 60) {
                            $ago_text = __('just now', 'bootflow-product-xml-csv-importer');
                        } elseif ($ago_seconds < 3600) {
                            /* translators: %d = number of minutes */
                            $ago_text = sprintf(__('%d min ago', 'bootflow-product-xml-csv-importer'), intval($ago_seconds / 60));
                        } elseif ($ago_seconds < 86400) {
                            $hours = intval($ago_seconds / 3600);
                            $mins = intval(($ago_seconds % 3600) / 60);
                            /* translators: %1$d = hours, %2$d = minutes */
                            $ago_text = sprintf(__('%1$dh %2$dm ago', 'bootflow-product-xml-csv-importer'), $hours, $mins);
                        } else {
                            /* translators: %d = number of days */
                            $ago_text = sprintf(__('%d days ago', 'bootflow-product-xml-csv-importer'), intval($ago_seconds / 86400));
                        }
                        ?>
                        <?php echo esc_html(Bfpi_i18n::localize_date('d.m.Y H:i:s', $last_run_ts)); ?>
                        <span style="color:#888; margin-left:8px;">(<?php echo esc_html($ago_text); ?>)</span>
                        <?php
                        // Show next scheduled run
                        if (!empty($import['schedule_type']) && $import['schedule_type'] !== 'none' && $import['schedule_type'] !== 'disabled') {
                            $intervals = array('bfpi_15min'=>900, 'hourly'=>3600, 'bfpi_6hours'=>21600, 'daily'=>86400, 'weekly'=>604800, 'monthly'=>2592000);
                            $interval = $intervals[$import['schedule_type']] ?? 0;
                            if ($interval > 0) {
                                $next_run_ts = $last_run_ts + $interval;
                                $until_seconds = $next_run_ts - current_time('timestamp');
                                if ($until_seconds <= 0) {
                                    $next_text = __('⏳ due now', 'bootflow-product-xml-csv-importer');
                                } elseif ($until_seconds < 60) {
                                    $next_text = __('⏳ in less than 1 min', 'bootflow-product-xml-csv-importer');
                                } elseif ($until_seconds < 3600) {
                                    /* translators: %d = number of minutes */
                                    $next_text = sprintf(__('⏳ next run in %d min', 'bootflow-product-xml-csv-importer'), intval($until_seconds / 60));
                                } else {
                                    /* translators: %1$d = hours, %2$d = minutes */
                                    $next_text = sprintf(__('⏳ next run in %1$dh %2$dm', 'bootflow-product-xml-csv-importer'), intval($until_seconds / 3600), intval(($until_seconds % 3600) / 60));
                                }
                                echo '<br><span style="color:#0073aa;">' . esc_html($next_text) . '</span>';
                            }
                        }
                        ?>
                    <?php else: ?>
                        <?php esc_html_e('Never', 'bootflow-product-xml-csv-importer'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Batch Size', 'bootflow-product-xml-csv-importer'); ?></th>
                <td><strong><?php echo intval($import['batch_size'] ?? 50); ?></strong> <?php esc_html_e('products per chunk', 'bootflow-product-xml-csv-importer'); ?></td>
            </tr>
            <?php if (!empty($import['original_file_url'])): ?>
            <tr>
                <th><?php esc_html_e('File URL', 'bootflow-product-xml-csv-importer'); ?></th>
                <td>
                    <input type="text" id="import_file_url" name="import_file_url" value="<?php echo esc_attr($import['original_file_url']); ?>" class="regular-text" style="width: 500px;">
                    <button type="button" id="update-file-url-btn" class="button"><?php esc_html_e('Update URL', 'bootflow-product-xml-csv-importer'); ?></button>
                    <span id="url-update-status"></span>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <form method="post" id="import-edit-form" action="">
        
    <!-- Import Behavior (moved INSIDE form to fix save issue) -->
    <div class="bfpi-card" style="margin-top: 20px;">
        <h2>⚙️ <?php esc_html_e('Import Behavior', 'bootflow-product-xml-csv-importer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Update Existing Products', 'bootflow-product-xml-csv-importer'); ?></th>
                <td>
                    <label style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" name="update_existing" value="1" <?php checked($import['update_existing'], '1'); ?> style="margin-top: 3px;" />
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
                        <input type="checkbox" name="skip_unchanged" value="1" <?php checked(($import['skip_unchanged'] ?? '0') == '1', true); ?> style="margin-top: 3px;" />
                        <div>
                            <strong><?php esc_html_e('Skip products if data unchanged', 'bootflow-product-xml-csv-importer'); ?></strong>
                            <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                <?php esc_html_e('Reduces import time by skipping products that haven\'t changed.', 'bootflow-product-xml-csv-importer'); ?>
                            </p>
                        </div>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Handle Missing Products', 'bootflow-product-xml-csv-importer'); ?></th>
                <td>
                    <label style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" name="handle_missing" id="handle_missing_edit" value="1" <?php checked(($import['handle_missing'] ?? '0') == '1', true); ?> style="margin-top: 3px;" />
                        <div>
                            <strong><?php esc_html_e('Process products no longer in feed', 'bootflow-product-xml-csv-importer'); ?></strong>
                            <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                <?php esc_html_e('When enabled, products that were imported before but are no longer in the XML/CSV file will be processed.', 'bootflow-product-xml-csv-importer'); ?>
                            </p>
                        </div>
                    </label>
                    
                    <div id="missing-products-options-edit" style="margin-left: 25px; margin-top: 15px; <?php echo esc_attr((($import['handle_missing'] ?? '0') != '1') ? 'display: none;' : ''); ?>">
                        <div style="margin-bottom: 10px;">
                            <label for="missing_action_edit"><?php esc_html_e('Action for missing products:', 'bootflow-product-xml-csv-importer'); ?></label><br>
                            <select name="missing_action" id="missing_action_edit" class="regular-text" style="margin-top: 5px;">
                                <option value="draft" <?php selected($import['missing_action'] ?? 'draft', 'draft'); ?>><?php esc_html_e('Move to Draft (Recommended)', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="outofstock" <?php selected($import['missing_action'] ?? '', 'outofstock'); ?>><?php esc_html_e('Mark as Out of Stock', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="backorder" <?php selected($import['missing_action'] ?? '', 'backorder'); ?>><?php esc_html_e('Allow Backorder (stock=0)', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="trash" <?php selected($import['missing_action'] ?? '', 'trash'); ?>><?php esc_html_e('Move to Trash (auto-delete after 30 days)', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="delete" <?php selected($import['missing_action'] ?? '', 'delete'); ?>><?php esc_html_e('Permanently Delete (⚠️ DANGEROUS)', 'bootflow-product-xml-csv-importer'); ?></option>
                            </select>
                        </div>
                        
                        <label style="display: flex; align-items: flex-start; gap: 10px;">
                            <input type="checkbox" name="delete_variations" value="1" <?php checked(($import['delete_variations'] ?? '1') == '1', true); ?> style="margin-top: 3px;" />
                            <span><?php esc_html_e('Also process variations when parent product is missing', 'bootflow-product-xml-csv-importer'); ?></span>
                        </label>
                        
                        <p class="description" style="margin-top: 10px; color: #666;">
                            <span style="color: #0073aa;">ℹ️</span> 
                            <?php esc_html_e('Action will only affect products last updated by THIS import.', 'bootflow-product-xml-csv-importer'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>
    </div>
        <?php
        // Pass import data for AJAX structure loading (edit mode)
        $bfpi_edit_data = array(
            'file_path'       => $import['file_path'],
            'file_type'       => $import['file_type'],
            'import_name'     => $import['name'],
            'schedule_type'   => $import['schedule_type'],
            'product_wrapper' => $import['product_wrapper'],
            'update_existing' => $import['update_existing'],
            'batch_size'      => intval($import['batch_size'] ?? 50),
            'existing_mappings' => $existing_mappings,
            'saved_mappings'  => $existing_mappings,
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('bfpi_nonce'),
        );
        wp_add_inline_script(
            'bfpi-import-admin',
            'var bfpiImportData = ' . wp_json_encode($bfpi_edit_data) . ';',
            'before'
        );
        ?>
        <?php wp_nonce_field('update_import_' . $import_id); ?>
        
        <!-- Hidden fields for schedule -->
        <input type="hidden" name="schedule_type_hidden" value="<?php echo esc_attr($import['schedule_type'] ?? 'none'); ?>" />
        <input type="hidden" name="schedule_method_hidden" value="<?php echo esc_attr($import['schedule_method'] ?? 'action_scheduler'); ?>" />
        
        <!-- Import Settings -->
        <div class="bfpi-card" style="margin-bottom: 20px;">
            <h3><?php esc_html_e('Import Settings', 'bootflow-product-xml-csv-importer'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Batch Size', 'bootflow-product-xml-csv-importer'); ?></th>
                    <td>
                        <input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr($import['batch_size'] ?? 50); ?>" min="1" max="500" style="width: 100px;">
                        <span class="description"><?php esc_html_e('Products to process per chunk (1-500). Higher = faster, but more memory. Recommended: 50-200 for updates.', 'bootflow-product-xml-csv-importer'); ?></span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="bfpi-layout">
            <!-- Left Sidebar - File Structure -->
            <div class="bfpi-sidebar">
                <div class="bfpi-card">
                    <h3><?php esc_html_e('File Structure', 'bootflow-product-xml-csv-importer'); ?></h3>
                    <div class="file-info">
                        <p><strong><?php esc_html_e('File:', 'bootflow-product-xml-csv-importer'); ?></strong> <?php echo esc_html(basename($import['file_path'])); ?></p>
                        <p><strong><?php esc_html_e('Type:', 'bootflow-product-xml-csv-importer'); ?></strong> <?php echo esc_html(strtoupper($import['file_type'])); ?></p>
                        <p><strong><?php esc_html_e('Import:', 'bootflow-product-xml-csv-importer'); ?></strong> <?php echo esc_html($import['name']); ?></p>
                    </div>
                    <div id="file-structure-browser">
                        <div class="structure-loader">
                            <div class="spinner is-active"></div>
                            <p><?php esc_html_e('Loading file structure...', 'bootflow-product-xml-csv-importer'); ?></p>
                        </div>
                    </div>
                    <div class="structure-pagination" id="structure-pagination" style="display: none; margin-top: 15px; text-align: center;">
                        <button type="button" class="button" id="prev-page"><?php esc_html_e('Previous', 'bootflow-product-xml-csv-importer'); ?></button>
                        <span class="pagination-info" style="display: inline-block; vertical-align: middle;">
                            Page <input type="number" id="current-page-input" min="1" style="width: 50px; text-align: center; display: inline-block; vertical-align: middle;" /> 
                            of <span id="total-pages-display">1</span>
                        </span>
                        <button type="button" class="button" id="next-page"><?php esc_html_e('Next', 'bootflow-product-xml-csv-importer'); ?></button>
                    </div>
                </div>
                <div class="bfpi-card">
                    <h3><?php esc_html_e('Sample Data', 'bootflow-product-xml-csv-importer'); ?></h3>
                    <div id="sample-data-preview">
                        <p class="description"><?php esc_html_e('Sample product data will appear here after loading the file structure.', 'bootflow-product-xml-csv-importer'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Main Content - Field Mapping -->
            <div class="bfpi-main">
                <div class="bfpi-card">
                    <h2><?php esc_html_e('Field Mappings', 'bootflow-product-xml-csv-importer'); ?></h2>
                    <p class="description"><?php esc_html_e('Map your file fields to WooCommerce product fields and configure processing modes.', 'bootflow-product-xml-csv-importer'); ?></p>
                    
                    <?php if (empty($existing_mappings)): ?>
                    <div class="notice notice-warning inline" style="margin: 15px 0;">
                        <p><strong><?php esc_html_e('Configuration Required:', 'bootflow-product-xml-csv-importer'); ?></strong> <?php esc_html_e('This import needs to be configured. Please select source fields from the dropdowns below, configure processing modes, and click "Save Changes" to activate this import.', 'bootflow-product-xml-csv-importer'); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="mapping-actions" style="margin-bottom: 15px;">
                        <button type="button" class="button button-secondary" onclick="clearAllMapping()">
                            <?php esc_html_e('Clear All', 'bootflow-product-xml-csv-importer'); ?>
                        </button>
                        <button type="button" class="button button-secondary" onclick="alert('Test mapping feature coming soon')">
                            <?php esc_html_e('Test Mapping', 'bootflow-product-xml-csv-importer'); ?>
                        </button>
                    </div>
            
                    <!-- Field Mapping Sections -->
                    <div class="field-mapping-sections">
                <?php foreach ($woocommerce_fields as $section_key => $section): ?>
                    <div class="mapping-section" data-section="<?php echo esc_attr($section_key); ?>">
                        <h3 class="section-toggle">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <?php echo esc_html($section['title']); ?>
                            <?php 
                            $mapped = 0;
                            foreach ($section['fields'] as $fk => $fv) {
                                if (isset($existing_mappings[$fk]['source']) && !empty($existing_mappings[$fk]['source'])) {
                                    $mapped++;
                                }
                            }
                            ?>
                            <span class="mapped-count"><?php echo esc_html($mapped . '/' . count($section['fields'])); ?></span>
                        </h3>
                        
                        <div class="section-fields" id="section-<?php echo esc_attr($section_key); ?>">
                            <?php if ($section_key === 'pricing_engine'): ?>
                                <!-- ═══════════ PRICING ENGINE ═══════════ -->
                                <div class="pricing-engine-container" style="padding: 20px;">
                                    <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-radius: 8px; border-left: 4px solid #ff9800;">
                                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                            <input type="checkbox" id="pricing_engine_enabled" name="pricing_engine_enabled" value="1" style="width: 20px; height: 20px;">
                                            <span>
                                                <strong style="font-size: 15px; color: #e65100;"><?php esc_html_e('Enable Price Markup', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                <small style="display: block; color: #bf360c; margin-top: 3px;"><?php esc_html_e('Calculate final prices by applying markup rules to the base price from XML', 'bootflow-product-xml-csv-importer'); ?></small>
                                            </span>
                                        </label>
                                    </div>
                                    <div id="pricing-engine-settings" style="display: none;">
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
                                        <div style="margin-bottom: 20px; padding: 20px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px;">
                                            <label style="font-weight: 600; display: block; margin-bottom: 12px; color: #333; font-size: 14px;">
                                                <span class="dashicons dashicons-tag" style="color: #ff9800;"></span>
                                                <?php esc_html_e('Base Price Source (from XML):', 'bootflow-product-xml-csv-importer'); ?>
                                            </label>
                                            <select id="pricing_engine_base_price" name="pricing_engine_base_price" class="bfpi-field-select" style="width: 100%; max-width: 400px; padding: 10px;">
                                                <option value=""><?php esc_html_e('-- Select XML field with base price --', 'bootflow-product-xml-csv-importer'); ?></option>
                                            </select>
                                            <p class="description" style="margin-top: 8px; color: #666;"><?php esc_html_e('Select the XML field that contains the supplier/wholesale price', 'bootflow-product-xml-csv-importer'); ?></p>
                                        </div>
                                        <div style="margin-bottom: 20px;">
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
                                                    <span style="font-size: 12px; font-weight: normal; color: #666; margin-left: 10px;"><?php esc_html_e('(First matching rule wins)', 'bootflow-product-xml-csv-importer'); ?></span>
                                                </h4>
                                                <button type="button" id="btn-add-pricing-rule" class="button button-primary" style="display: flex; align-items: center; gap: 5px;">
                                                    <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                    <?php esc_html_e('Add Rule', 'bootflow-product-xml-csv-importer'); ?>
                                                </button>
                                            </div>
                                            <div id="pricing-rules-list">
                                                <div class="pricing-rule-row pricing-rule-default" data-rule-id="default" style="padding: 20px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #4caf50; border-radius: 8px; margin-bottom: 15px;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <span style="font-size: 20px;">🏠</span>
                                                            <strong style="color: #2e7d32; font-size: 14px;"><?php esc_html_e('Default Rule (Fallback)', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <span style="font-size: 11px; background: #4caf50; color: white; padding: 2px 8px; border-radius: 10px;"><?php esc_html_e('Always applies if no other rule matches', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;"><?php esc_html_e('Markup %', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="number" name="pricing_rule[default][markup_percent]" value="0" min="-100" max="10000" step="0.01" style="width: 80px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                        </div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;"><?php esc_html_e('+ Fixed €', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="number" name="pricing_rule[default][fixed_amount]" value="0" min="-10000" max="10000" step="0.01" style="width: 80px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                        </div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;"><?php esc_html_e('Round to', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <select name="pricing_rule[default][rounding]" style="padding: 8px; border: 2px solid #81c784; border-radius: 4px; min-width: 130px;">
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
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;"><?php esc_html_e('Min Price €', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="number" name="pricing_rule[default][min_price]" value="" min="0" step="0.01" placeholder="—" style="width: 70px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                        </div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;"><?php esc_html_e('Max Price €', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="number" name="pricing_rule[default][max_price]" value="" min="0" step="0.01" placeholder="—" style="width: 70px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <template id="pricing-rule-template">
                                                <div class="pricing-rule-row pricing-rule-conditional" data-rule-id="" style="padding: 20px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; transition: all 0.2s;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <span class="rule-drag-handle" style="cursor: move; color: #999; font-size: 18px;">⋮⋮</span>
                                                            <span class="rule-number" style="background: #ff9800; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                                            <input type="text" name="pricing_rule[{id}][name]" placeholder="<?php esc_html_e('Rule name (optional)', 'bootflow-product-xml-csv-importer'); ?>" style="border: none; border-bottom: 1px dashed #ccc; padding: 5px; font-weight: 500; width: 200px;">
                                                        </div>
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <label style="display: flex; align-items: center; gap: 5px; font-size: 12px; color: #666;">
                                                                <input type="checkbox" name="pricing_rule[{id}][enabled]" checked>
                                                                <?php esc_html_e('Enabled', 'bootflow-product-xml-csv-importer'); ?>
                                                            </label>
                                                            <button type="button" class="button-link remove-pricing-rule" style="color: #d63638;"><span class="dashicons dashicons-trash"></span></button>
                                                        </div>
                                                    </div>
                                                    <div class="rule-conditions" style="margin-bottom: 15px; padding: 15px; background: #fafafa; border-radius: 6px;">
                                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                            <strong style="font-size: 13px; color: #333;"><span class="dashicons dashicons-filter" style="color: #2196f3;"></span> <?php esc_html_e('Apply when:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <select name="pricing_rule[{id}][condition_logic]" style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                                <option value="AND"><?php esc_html_e('ALL conditions match', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                <option value="OR"><?php esc_html_e('ANY condition matches', 'bootflow-product-xml-csv-importer'); ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="conditions-list" style="display: flex; flex-direction: column; gap: 8px;">
                                                            <div class="condition-row" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                                                <select name="pricing_rule[{id}][conditions][0][type]" class="condition-type" style="padding: 6px; border-radius: 4px; min-width: 140px;">
                                                                    <option value="price_range"><?php esc_html_e('Price Range', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <option value="category">📁 <?php esc_html_e('Category', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <option value="brand">🏷️ <?php esc_html_e('Brand', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <option value="supplier">🏭 <?php esc_html_e('Supplier', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <option value="xml_field">📄 <?php esc_html_e('XML Field', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <option value="sku_pattern">🔢 <?php esc_html_e('SKU Pattern', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                </select>
                                                                <div class="condition-fields condition-price_range" style="display: flex; gap: 8px; align-items: center;">
                                                                    <span style="color: #666;"><?php esc_html_e('from', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <input type="number" name="pricing_rule[{id}][conditions][0][price_from]" placeholder="0" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                    <span style="color: #666;"><?php esc_html_e('to', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <input type="number" name="pricing_rule[{id}][conditions][0][price_to]" placeholder="∞" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                    <span style="color: #888; font-size: 11px;">€</span>
                                                                </div>
                                                                <div class="condition-fields condition-category" style="display: none; gap: 8px; align-items: center;">
                                                                    <select name="pricing_rule[{id}][conditions][0][category_operator]" style="padding: 6px; border-radius: 4px;">
                                                                        <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="not_equals"><?php esc_html_e('not equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    </select>
                                                                    <input type="text" name="pricing_rule[{id}][conditions][0][category_value]" placeholder="<?php esc_html_e('Category name or slug', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                </div>
                                                                <div class="condition-fields condition-brand" style="display: none; gap: 8px; align-items: center;">
                                                                    <select name="pricing_rule[{id}][conditions][0][brand_operator]" style="padding: 6px; border-radius: 4px;">
                                                                        <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="not_equals"><?php esc_html_e('not equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    </select>
                                                                    <input type="text" name="pricing_rule[{id}][conditions][0][brand_value]" placeholder="<?php esc_html_e('Brand name', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                </div>
                                                                <div class="condition-fields condition-supplier" style="display: none; gap: 8px; align-items: center;">
                                                                    <select name="pricing_rule[{id}][conditions][0][supplier_operator]" style="padding: 6px; border-radius: 4px;">
                                                                        <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    </select>
                                                                    <input type="text" name="pricing_rule[{id}][conditions][0][supplier_value]" placeholder="<?php esc_html_e('Supplier name', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                </div>
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
                                                    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                                                        <div>
                                                            <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;"><?php esc_html_e('Markup %', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="number" name="pricing_rule[{id}][markup_percent]" value="0" min="-100" max="10000" step="0.01" style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                        </div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;"><?php esc_html_e('+ Fixed €', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="number" name="pricing_rule[{id}][fixed_amount]" value="0" min="-10000" max="10000" step="0.01" style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                        </div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;"><?php esc_html_e('Round to', 'bootflow-product-xml-csv-importer'); ?></label>
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
                                                            <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;"><?php esc_html_e('Min €', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="number" name="pricing_rule[{id}][min_price]" value="" min="0" step="0.01" placeholder="—" style="width: 70px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                        </div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;"><?php esc_html_e('Max €', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <input type="number" name="pricing_rule[{id}][max_price]" value="" min="0" step="0.01" placeholder="—" style="width: 70px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        <div style="padding: 20px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 8px; border: 2px solid #4caf50; margin-top: 20px;">
                                            <h4 style="margin: 0 0 15px 0; color: #2e7d32; display: flex; align-items: center; gap: 8px;">
                                                <span class="dashicons dashicons-calculator" style="color: #4caf50;"></span>
                                                <?php esc_html_e('Live Preview', 'bootflow-product-xml-csv-importer'); ?>
                                            </h4>
                                            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                                <div>
                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;"><?php esc_html_e('Test with Rule:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                    <select id="pricing_engine_test_rule" style="padding: 8px 12px; border: 2px solid #81c784; border-radius: 4px; font-size: 14px; min-width: 150px;">
                                                        <option value="default"><?php esc_html_e('Default Rule', 'bootflow-product-xml-csv-importer'); ?></option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;"><?php esc_html_e('Test Base Price:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                    <input type="number" id="pricing_engine_test_input" value="100" step="0.01" min="0" style="width: 120px; padding: 8px; border: 2px solid #81c784; border-radius: 4px; font-size: 16px; font-weight: 600;">
                                                </div>
                                                <div style="font-size: 24px; color: #4caf50;">→</div>
                                                <div>
                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;"><?php esc_html_e('Final Price:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                    <div id="pricing_engine_test_output" style="padding: 8px 15px; background: #fff; border: 2px solid #4caf50; border-radius: 4px; font-size: 18px; font-weight: 700; color: #2e7d32; min-width: 100px;">€100.00</div>
                                                </div>
                                                <div style="margin-left: 10px; padding: 8px 12px; background: #fff; border-radius: 4px; font-size: 12px; color: #666;">
                                                    <span id="pricing_engine_matched_rule" style="color: #4caf50; font-weight: 600;"><?php esc_html_e('Default Rule', 'bootflow-product-xml-csv-importer'); ?></span><br>
                                                    <span id="pricing_engine_formula">100 × 1.00 + 0 = 100.00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="pricing-engine-disabled-msg" style="padding: 25px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                                        <span style="font-size: 36px; opacity: 0.5;">⚡</span>
                                        <p style="margin: 10px 0 0 0; color: #999;"><?php esc_html_e('Enable the Price Markup above to configure automatic price calculations', 'bootflow-product-xml-csv-importer'); ?></p>
                                    </div>
                                </div>
                            <?php elseif ($section_key === 'shipping_class_engine'): ?>
                                <!-- ═══════════ SHIPPING CLASS ENGINE ═══════════ -->
                                <?php
                                $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));
                                if (is_wp_error($shipping_classes)) { $shipping_classes = array(); }
                                ?>
                                <div class="shipping-class-engine-container" style="padding: 20px;">
                                    <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 8px; border-left: 4px solid #1976d2;">
                                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                            <input type="checkbox" id="shipping_class_engine_enabled" name="shipping_class_engine_enabled" value="1" style="width: 20px; height: 20px;">
                                            <span>
                                                <strong style="font-size: 15px; color: #0d47a1;"><?php esc_html_e('Enable Shipping Class Rules', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                <small style="display: block; color: #1565c0; margin-top: 3px;"><?php esc_html_e('Auto-assign shipping classes based on product weight, category, price, or any XML field', 'bootflow-product-xml-csv-importer'); ?></small>
                                            </span>
                                        </label>
                                    </div>
                                    <div id="shipping-class-engine-settings" style="display: none;">
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
                                        <div style="margin-bottom: 20px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0;">
                                                <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                                    <span class="dashicons dashicons-car" style="color: #1976d2;"></span>
                                                    <?php esc_html_e('Shipping Class Rules', 'bootflow-product-xml-csv-importer'); ?>
                                                    <span style="font-size: 12px; font-weight: normal; color: #666; margin-left: 10px;"><?php esc_html_e('(First matching rule wins)', 'bootflow-product-xml-csv-importer'); ?></span>
                                                </h4>
                                                <button type="button" id="btn-add-shipping-rule" class="button button-primary" style="display: flex; align-items: center; gap: 5px;">
                                                    <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                    <?php esc_html_e('Add Rule', 'bootflow-product-xml-csv-importer'); ?>
                                                </button>
                                            </div>
                                            <div id="shipping-class-rules-list">
                                                <div class="shipping-rule-row shipping-rule-default" data-rule-id="default" style="padding: 20px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 2px solid #1976d2; border-radius: 8px; margin-bottom: 15px;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <span style="font-size: 20px;">🏠</span>
                                                            <strong style="color: #0d47a1; font-size: 14px;"><?php esc_html_e('Default Rule (Fallback)', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <span style="font-size: 11px; background: #1976d2; color: white; padding: 2px 8px; border-radius: 10px;"><?php esc_html_e('Always applies if no other rule matches', 'bootflow-product-xml-csv-importer'); ?></span>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                                                        <div>
                                                            <label style="font-size: 12px; color: #1565c0; display: block; margin-bottom: 4px;"><?php esc_html_e('Assign Shipping Class:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div style="display: flex; gap: 8px; align-items: center;">
                                                                <select name="shipping_rule[default][shipping_class]" class="shipping-class-select" style="min-width: 200px; padding: 8px; border: 2px solid #90caf9; border-radius: 4px;">
                                                                    <option value=""><?php esc_html_e('-- No shipping class --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <?php foreach ($shipping_classes as $sc): ?>
                                                                        <option value="<?php echo esc_attr($sc->slug); ?>"><?php echo esc_html($sc->name); ?> (<?php echo esc_html($sc->slug); ?>)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <span style="color: #999; font-size: 12px;"><?php esc_html_e('or type new:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                <input type="text" name="shipping_rule[default][shipping_class_custom]" placeholder="<?php esc_html_e('New class name', 'bootflow-product-xml-csv-importer'); ?>" class="shipping-class-custom" style="width: 150px; padding: 8px; border: 2px solid #90caf9; border-radius: 4px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <template id="shipping-rule-template">
                                                <div class="shipping-rule-row shipping-rule-conditional" data-rule-id="" style="padding: 20px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; transition: all 0.2s;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <span class="rule-drag-handle" style="cursor: move; color: #999; font-size: 18px;">⋮⋮</span>
                                                            <span class="shipping-rule-number" style="background: #1976d2; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                                            <input type="text" name="shipping_rule[{id}][name]" placeholder="<?php esc_html_e('Rule name (e.g., Heavy items)', 'bootflow-product-xml-csv-importer'); ?>" style="border: none; border-bottom: 1px dashed #ccc; padding: 5px; font-weight: 500; width: 200px;">
                                                        </div>
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <label style="display: flex; align-items: center; gap: 5px; font-size: 12px; color: #666;">
                                                                <input type="checkbox" name="shipping_rule[{id}][enabled]" checked>
                                                                <?php esc_html_e('Enabled', 'bootflow-product-xml-csv-importer'); ?>
                                                            </label>
                                                            <button type="button" class="button-link remove-shipping-rule" style="color: #d63638;"><span class="dashicons dashicons-trash"></span></button>
                                                        </div>
                                                    </div>
                                                    <div class="shipping-rule-conditions" style="margin-bottom: 15px; padding: 15px; background: #fafafa; border-radius: 6px;">
                                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                            <strong style="font-size: 13px; color: #333;"><span class="dashicons dashicons-filter" style="color: #1976d2;"></span> <?php esc_html_e('Apply when:', 'bootflow-product-xml-csv-importer'); ?></strong>
                                                            <select name="shipping_rule[{id}][condition_logic]" style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                                <option value="AND"><?php esc_html_e('ALL conditions match', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                <option value="OR"><?php esc_html_e('ANY condition matches', 'bootflow-product-xml-csv-importer'); ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="shipping-conditions-list" style="display: flex; flex-direction: column; gap: 8px;">
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
                                                                <div class="shipping-condition-fields shipping-condition-weight_range" style="display: flex; gap: 8px; align-items: center;">
                                                                    <span style="color: #666;"><?php esc_html_e('from', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <input type="number" name="shipping_rule[{id}][conditions][0][weight_from]" placeholder="0" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                    <span style="color: #666;"><?php esc_html_e('to', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <input type="number" name="shipping_rule[{id}][conditions][0][weight_to]" placeholder="∞" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                    <span style="color: #888; font-size: 11px;">kg</span>
                                                                </div>
                                                                <div class="shipping-condition-fields shipping-condition-price_range" style="display: none; gap: 8px; align-items: center;">
                                                                    <span style="color: #666;"><?php esc_html_e('from', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <input type="number" name="shipping_rule[{id}][conditions][0][price_from]" placeholder="0" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                    <span style="color: #666;"><?php esc_html_e('to', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <input type="number" name="shipping_rule[{id}][conditions][0][price_to]" placeholder="∞" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                    <span style="color: #888; font-size: 11px;">€</span>
                                                                </div>
                                                                <div class="shipping-condition-fields shipping-condition-volume_range" style="display: none; gap: 8px; align-items: center;">
                                                                    <span style="color: #666;"><?php esc_html_e('from', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <input type="number" name="shipping_rule[{id}][conditions][0][volume_from]" placeholder="0" min="0" step="1" style="width: 100px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                    <span style="color: #666;"><?php esc_html_e('to', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                    <input type="number" name="shipping_rule[{id}][conditions][0][volume_to]" placeholder="∞" min="0" step="1" style="width: 100px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                    <span style="color: #888; font-size: 11px;">cm³</span>
                                                                </div>
                                                                <div class="shipping-condition-fields shipping-condition-category" style="display: none; gap: 8px; align-items: center;">
                                                                    <select name="shipping_rule[{id}][conditions][0][category_operator]" style="padding: 6px; border-radius: 4px;">
                                                                        <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="not_equals"><?php esc_html_e('not equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    </select>
                                                                    <input type="text" name="shipping_rule[{id}][conditions][0][category_value]" placeholder="<?php esc_html_e('Category name', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                </div>
                                                                <div class="shipping-condition-fields shipping-condition-brand" style="display: none; gap: 8px; align-items: center;">
                                                                    <select name="shipping_rule[{id}][conditions][0][brand_operator]" style="padding: 6px; border-radius: 4px;">
                                                                        <option value="equals"><?php esc_html_e('equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="contains"><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                        <option value="not_equals"><?php esc_html_e('not equals', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    </select>
                                                                    <input type="text" name="shipping_rule[{id}][conditions][0][brand_value]" placeholder="<?php esc_html_e('Brand name', 'bootflow-product-xml-csv-importer'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                </div>
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
                                                    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                                                        <div>
                                                            <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;"><?php esc_html_e('Assign Shipping Class:', 'bootflow-product-xml-csv-importer'); ?></label>
                                                            <div style="display: flex; gap: 8px; align-items: center;">
                                                                <select name="shipping_rule[{id}][shipping_class]" class="shipping-class-select" style="min-width: 200px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                    <option value=""><?php esc_html_e('-- No shipping class --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                                    <?php foreach ($shipping_classes as $sc): ?>
                                                                        <option value="<?php echo esc_attr($sc->slug); ?>"><?php echo esc_html($sc->name); ?> (<?php echo esc_html($sc->slug); ?>)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <span style="color: #999; font-size: 12px;"><?php esc_html_e('or type new:', 'bootflow-product-xml-csv-importer'); ?></span>
                                                                <input type="text" name="shipping_rule[{id}][shipping_class_custom]" placeholder="<?php esc_html_e('New class name', 'bootflow-product-xml-csv-importer'); ?>" class="shipping-class-custom" style="width: 150px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
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
                                                    <p style="color: #999; margin: 5px 0; font-size: 12px;"><?php esc_html_e('No shipping classes found. They will be created automatically during import.', 'bootflow-product-xml-csv-importer'); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="shipping-class-engine-disabled-msg" style="padding: 25px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                                        <span style="font-size: 36px; opacity: 0.5;">📦</span>
                                        <p style="margin: 10px 0 0 0; color: #999;"><?php esc_html_e('Enable Shipping Class Rules above to automatically assign shipping classes based on product data', 'bootflow-product-xml-csv-importer'); ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                            <?php foreach ($section['fields'] as $field_key => $field): 
                                $current_mapping = $existing_mappings[$field_key] ?? array();
                                $current_source = $current_mapping['source'] ?? '';
                                $current_mode = $current_mapping['processing_mode'] ?? 'direct';
                            ?>
                                <?php if (false): ?><?php else: ?>
                                <div class="field-mapping-row" data-field="<?php echo esc_attr($field_key); ?>">
                                    <div class="field-target">
                                        <label class="field-label <?php echo esc_attr($field['required'] ? 'required' : ''); ?>">
                                            <?php echo esc_html($field['label']); ?>
                                            <?php if ($field['required']): ?>
                                                <span class="required-asterisk">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <span class="field-type"><?php echo esc_html($field['type'] ?? 'text'); ?></span>
                                        <label class="update-field-checkbox" style="margin-top: 8px; font-weight: normal; font-size: 12px; display: flex; align-items: center; gap: 5px;">
                                            <!-- Hidden input sends '0' if checkbox unchecked, checkbox overrides with '1' if checked -->
                                            <input type="hidden" 
                                                   name="field_mapping[<?php echo esc_attr($field_key); ?>][update_on_sync]" 
                                                   value="0">
                                            <input type="checkbox" 
                                                   name="field_mapping[<?php echo esc_attr($field_key); ?>][update_on_sync]" 
                                                   value="1"
                                                   <?php checked(!isset($current_mapping['update_on_sync']) || $current_mapping['update_on_sync'] !== '0'); ?>
                                                   style="margin: 0;">
                                            <span style="color: #646970;"><?php esc_html_e('Update this field?', 'bootflow-product-xml-csv-importer'); ?></span>
                                        </label>
                                    </div>
                                    
                                    <div class="field-source">
                                        <?php
                                        // Use new textarea UI for all fields (with drag & drop support)
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
                                            ><?php echo esc_textarea($current_source); ?></textarea>
                                            <?php if (!empty($field['description'])): ?>
                                                <p class="description" style="margin-top: 4px; font-size: 11px;"><?php echo esc_html($field['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    
                                    
                                    
                                    
                                    <div class="field-actions">
                                        <button type="button" class="button button-small clear-mapping" title="<?php esc_html_e('Clear Mapping', 'bootflow-product-xml-csv-importer'); ?>">
                                            <span class="dashicons dashicons-no-alt"></span>
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Import Filters -->
            <div class="mapping-section" data-section="filters">
                <h3 class="section-toggle">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                    <?php esc_html_e('Import Filters', 'bootflow-product-xml-csv-importer'); ?>
                    <button type="button" class="button button-small" onclick="addFilterRule(event)" style="margin-left: 10px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Add Filter', 'bootflow-product-xml-csv-importer'); ?>
                    </button>
                </h3>
                <p class="description" style="margin: 10px 15px; color: #666;">
                    <?php esc_html_e('Filter which products to import based on field values. Products that don\'t match will be skipped.', 'bootflow-product-xml-csv-importer'); ?>
                </p>
                
                <div class="section-fields">
                    <div id="import-filters-container">
                        <?php 
                        $existing_filters = isset($import['import_filters']) ? json_decode($import['import_filters'], true) : array();
                        if (!empty($existing_filters) && is_array($existing_filters)):
                            $total_filters = count($existing_filters);
                            foreach ($existing_filters as $filter_index => $filter):
                                $is_last = ($filter_index === $total_filters - 1);
                        ?>
                            <div class="filter-rule-row" style="display: flex; gap: 10px; align-items: center; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Field', 'bootflow-product-xml-csv-importer'); ?></label>
                                    <select name="import_filters[<?php echo esc_attr($filter_index); ?>][field]" class="filter-field-select import-filter-field-select" data-selected="<?php echo esc_attr($filter['field'] ?? ''); ?>" style="width: 100%;">
                                        <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <?php foreach ($file_fields as $ff): ?>
                                            <option value="<?php echo esc_attr($ff); ?>" <?php selected($filter['field'] ?? '', $ff); ?>><?php echo esc_html($ff); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div style="flex: 0 0 150px;">
                                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Operator', 'bootflow-product-xml-csv-importer'); ?></label>
                                    <select name="import_filters[<?php echo esc_attr($filter_index); ?>][operator]" style="width: 100%;">
                                        <option value="=" <?php selected($filter['operator'] ?? '', '='); ?>>=</option>
                                        <option value="!=" <?php selected($filter['operator'] ?? '', '!='); ?>>!=</option>
                                        <option value=">" <?php selected($filter['operator'] ?? '', '>'); ?>>></option>
                                        <option value="<" <?php selected($filter['operator'] ?? '', '<'); ?>><</option>
                                        <option value=">=" <?php selected($filter['operator'] ?? '', '>='); ?>>>=</option>
                                        <option value="<=" <?php selected($filter['operator'] ?? '', '<='); ?>><=</option>
                                        <option value="contains" <?php selected($filter['operator'] ?? '', 'contains'); ?>><?php esc_html_e('contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="not_contains" <?php selected($filter['operator'] ?? '', 'not_contains'); ?>><?php esc_html_e('not contains', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="empty" <?php selected($filter['operator'] ?? '', 'empty'); ?>><?php esc_html_e('is empty', 'bootflow-product-xml-csv-importer'); ?></option>
                                        <option value="not_empty" <?php selected($filter['operator'] ?? '', 'not_empty'); ?>><?php esc_html_e('not empty', 'bootflow-product-xml-csv-importer'); ?></option>
                                    </select>
                                </div>
                                
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Value', 'bootflow-product-xml-csv-importer'); ?></label>
                                    <input type="text" name="import_filters[<?php echo esc_attr($filter_index); ?>][value]" value="<?php echo esc_attr($filter['value'] ?? ''); ?>" placeholder="<?php esc_html_e('Comparison value', 'bootflow-product-xml-csv-importer'); ?>" style="width: 100%;" />
                                </div>
                                
                                <?php if (!$is_last): ?>
                                <div style="flex: 0 0 100px;">
                                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Condition', 'bootflow-product-xml-csv-importer'); ?></label>
                                    <div style="display: flex; gap: 8px;">
                                        <label style="margin: 0; display: flex; align-items: center;">
                                            <input type="radio" name="import_filters[<?php echo esc_attr($filter_index); ?>][logic]" value="AND" <?php checked($filter['logic'] ?? 'AND', 'AND'); ?> style="margin: 0 4px 0 0;" />
                                            <span style="font-size: 12px;">AND</span>
                                        </label>
                                        <label style="margin: 0; display: flex; align-items: center;">
                                            <input type="radio" name="import_filters[<?php echo esc_attr($filter_index); ?>][logic]" value="OR" <?php checked($filter['logic'] ?? 'AND', 'OR'); ?> style="margin: 0 4px 0 0;" />
                                            <span style="font-size: 12px;">OR</span>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div style="flex: 0 0 40px;">
                                    <label style="display: block; font-size: 11px; color: transparent; margin-bottom: 3px;">.</label>
                                    <button type="button" class="button button-small remove-filter-rule" onclick="removeFilterRule(event)" style="padding: 6px 10px;">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <p class="no-filters" style="padding: 15px; color: #666; text-align: center;">
                                <?php esc_html_e('No filters added. All products will be imported.', 'bootflow-product-xml-csv-importer'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Filter Warning and Options -->
                    <?php if (!empty($existing_filters)): ?>
                    <div style="margin: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                        <p style="margin: 0 0 10px 0; font-weight: 600; color: #856404;">
                            <span class="dashicons dashicons-warning" style="color: #ffc107;"></span>
                            <?php esc_html_e('Filter Behavior', 'bootflow-product-xml-csv-importer'); ?>
                        </p>
                        <p style="margin: 0 0 10px 0; font-size: 13px; color: #856404;">
                            <?php esc_html_e('⚠️ Changing filters will affect future imports. Existing products won\'t be modified automatically.', 'bootflow-product-xml-csv-importer'); ?>
                        </p>
                        <label style="display: block; margin-top: 10px;">
                            <input type="checkbox" name="draft_non_matching" value="1" <?php checked($import['draft_non_matching'] ?? '0', '1'); ?> />
                            <strong><?php esc_html_e('Move non-matching products to Draft', 'bootflow-product-xml-csv-importer'); ?></strong>
                            <br>
                            <span style="font-size: 12px; color: #666; margin-left: 20px;">
                                <?php esc_html_e('When re-running, products that no longer match filters will be set to Draft status (not deleted).', 'bootflow-product-xml-csv-importer'); ?>
                            </span>
                            <br>
                            <span style="font-size: 12px; color: #d63638; margin-left: 20px; margin-top: 5px; display: block;">
                                <?php esc_html_e('⚠️ If unchecked: Products not matching filters will be skipped during import, but existing products will remain Published.', 'bootflow-product-xml-csv-importer'); ?>
                            </span>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Custom Fields -->
            <div class="mapping-section" data-section="custom">
                <h3 class="section-toggle">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                    <?php esc_html_e('Custom Fields', 'bootflow-product-xml-csv-importer'); ?>
                    <button type="button" class="button button-small" onclick="addCustomField(event)" style="margin-left: 10px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Add Custom Field', 'bootflow-product-xml-csv-importer'); ?>
                    </button>
                </h3>
                
                <div class="section-fields">
                    <div id="custom-fields-container">
                        <?php 
                        $custom_field_index = 0;
                        // Use $saved_custom_fields array (populated by admin class from both sources)
                        if (!empty($saved_custom_fields) && is_array($saved_custom_fields)):
                            foreach ($saved_custom_fields as $mapping):
                                $cf_name = $mapping['name'] ?? '';
                                if (empty($cf_name)) continue;
                                ?>
                                <div class="custom-field-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 10px; border-left: 3px solid #2271b1;">
                                    <table class="form-table" style="margin: 0;">
                                        <tr>
                                            <td style="width: 20%;">
                                                <input type="text" name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][name]" value="<?php echo esc_attr($cf_name); ?>" placeholder="<?php esc_html_e('Field Name', 'bootflow-product-xml-csv-importer'); ?>" class="widefat" />
                                            </td>
                                            <td style="width: 20%;">
                                                <select name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][source]" class="widefat">
                                                    <option value=""><?php esc_html_e('-- Select Source --', 'bootflow-product-xml-csv-importer'); ?></option>
                                                    <?php foreach ($file_fields as $ff): ?>
                                                        <option value="<?php echo esc_attr($ff); ?>" <?php selected($mapping['source'] ?? '', $ff); ?>><?php echo esc_html($ff); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td style="width: 15%;">
                                                <select name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][type]" class="widefat">
                                                    <option value="text" <?php selected($mapping['type'] ?? 'text', 'text'); ?>><?php esc_html_e('Text', 'bootflow-product-xml-csv-importer'); ?></option>
                                                    <option value="number" <?php selected($mapping['type'] ?? '', 'number'); ?>><?php esc_html_e('Number', 'bootflow-product-xml-csv-importer'); ?></option>
                                                    <option value="textarea" <?php selected($mapping['type'] ?? '', 'textarea'); ?>><?php esc_html_e('Textarea', 'bootflow-product-xml-csv-importer'); ?></option>
                                                </select>
                                            </td>
                                            <td style="width: 35%;">
                                                
                                            </td>
                                            <td style="width: 10%; text-align: center;">
                                                <button type="button" class="button" onclick="this.closest('.custom-field-row').remove();" title="<?php esc_html_e('Remove', 'bootflow-product-xml-csv-importer'); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="custom-field-config-row" style="<?php echo esc_attr(($mapping['processing_mode'] ?? 'direct') === 'direct' ? 'display:none;' : ''); ?>">
                                            <td colspan="5" style="padding-top: 10px;">
                                                <!-- PHP Formula Config -->
                                                
                                                <!-- AI Processing Config -->
                                                
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <?php
                                $custom_field_index++;
                            endforeach;
                        endif;
                        
                        // Show message if no custom fields
                        if ($custom_field_index === 0):
                        ?>
                        <p class="no-custom-fields" style="color: #666; font-style: italic;">
                            <?php esc_html_e('No custom fields added yet. Click "Add Custom Field" to create one.', 'bootflow-product-xml-csv-importer'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
                </div>
            </div>
        </div>
        
        <!-- Automated Schedule -->
        <?php 
            $schedule_method = $import['schedule_method'] ?? 'action_scheduler';
            $global_settings = get_option('bfpi_settings', array());
            $cron_secret = $global_settings['cron_secret_key'] ?? '';
            $cron_url = admin_url('admin-ajax.php') . '?action=bfpi_cron&secret=' . $cron_secret;
        ?>
        <div class="bfpi-card" style="margin-top: 20px;">
            <h2><?php esc_html_e('Automated Schedule', 'bootflow-product-xml-csv-importer'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Schedule Interval', 'bootflow-product-xml-csv-importer'); ?></th>
                    <td>
                        <select name="schedule_type" id="schedule_type_edit" class="regular-text">
                            <option value="none" <?php selected($import['schedule_type'], 'none'); ?>><?php esc_html_e('Disabled', 'bootflow-product-xml-csv-importer'); ?></option>
                            <option value="bfpi_15min" <?php selected($import['schedule_type'], 'bfpi_15min'); ?>><?php esc_html_e('Every 15 minutes', 'bootflow-product-xml-csv-importer'); ?></option>
                            <option value="hourly" <?php selected($import['schedule_type'], 'hourly'); ?>><?php esc_html_e('Hourly', 'bootflow-product-xml-csv-importer'); ?></option>
                            <option value="bfpi_6hours" <?php selected($import['schedule_type'], 'bfpi_6hours'); ?>><?php esc_html_e('Every 6 hours', 'bootflow-product-xml-csv-importer'); ?></option>
                            <option value="daily" <?php selected($import['schedule_type'], 'daily'); ?>><?php esc_html_e('Daily', 'bootflow-product-xml-csv-importer'); ?></option>
                            <option value="weekly" <?php selected($import['schedule_type'], 'weekly'); ?>><?php esc_html_e('Weekly', 'bootflow-product-xml-csv-importer'); ?></option>
                            <option value="monthly" <?php selected($import['schedule_type'], 'monthly'); ?>><?php esc_html_e('Monthly', 'bootflow-product-xml-csv-importer'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="schedule_method_row" style="<?php echo esc_attr( ($import['schedule_type'] === 'none' || empty($import['schedule_type'])) ? 'display:none;' : '' ); ?>">
                    <th scope="row"><?php esc_html_e('Schedule Method', 'bootflow-product-xml-csv-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label style="display: block; margin-bottom: 12px; padding: 12px; border: 2px solid <?php echo esc_attr($schedule_method === 'action_scheduler' ? '#0073aa' : '#ddd'); ?>; border-radius: 6px; cursor: pointer; background: <?php echo esc_attr($schedule_method === 'action_scheduler' ? '#f0f6fc' : '#fff'); ?>;">
                                <input type="radio" name="schedule_method" value="action_scheduler" <?php checked($schedule_method, 'action_scheduler'); ?>>
                                <strong><?php esc_html_e('Action Scheduler', 'bootflow-product-xml-csv-importer'); ?></strong>
                                <span style="background: #28a745; color: white; font-size: 10px; padding: 2px 6px; border-radius: 8px; margin-left: 6px;"><?php esc_html_e('Recommended', 'bootflow-product-xml-csv-importer'); ?></span>
                                <p class="description" style="margin: 6px 0 0 22px;">
                                    <?php esc_html_e('Automatically continues until complete. No server cron needed. Requires website traffic.', 'bootflow-product-xml-csv-importer'); ?>
                                </p>
                            </label>
                            
                            <label style="display: block; padding: 12px; border: 2px solid <?php echo esc_attr($schedule_method === 'server_cron' ? '#0073aa' : '#ddd'); ?>; border-radius: 6px; cursor: pointer; background: <?php echo esc_attr($schedule_method === 'server_cron' ? '#f0f6fc' : '#fff'); ?>;">
                                <input type="radio" name="schedule_method" value="server_cron" <?php checked($schedule_method, 'server_cron'); ?>>
                                <strong><?php esc_html_e('Server Cron', 'bootflow-product-xml-csv-importer'); ?></strong>
                                <p class="description" style="margin: 6px 0 0 22px;">
                                    <?php esc_html_e('Processes entire import in one request. 100% reliable but requires server cron setup.', 'bootflow-product-xml-csv-importer'); ?>
                                </p>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <!-- Server Cron Setup Instructions (only shown when server_cron is selected) -->
            <div id="server_cron_instructions" style="<?php echo esc_attr($schedule_method !== 'server_cron' ? 'display:none;' : ''); ?> margin-top: 15px; background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 15px;">
                <h4 style="margin-top: 0;">
                    <span class="dashicons dashicons-clock" style="color: #0073aa;"></span>
                    <?php esc_html_e('Server Cron Setup', 'bootflow-product-xml-csv-importer'); ?>
                </h4>
                
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th style="padding: 8px 10px 8px 0; width: 120px;"><?php esc_html_e('Cron URL', 'bootflow-product-xml-csv-importer'); ?></th>
                        <td style="padding: 8px 0;">
                            <input type="text" value="<?php echo esc_attr($cron_url); ?>" readonly class="large-text" style="font-size: 12px;" />
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($cron_url); ?>'); alert('<?php esc_html_e('Copied!', 'bootflow-product-xml-csv-importer'); ?>');">
                                <?php esc_html_e('Copy', 'bootflow-product-xml-csv-importer'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 8px 10px 8px 0;"><?php esc_html_e('cPanel Command', 'bootflow-product-xml-csv-importer'); ?></th>
                        <td style="padding: 8px 0;">
                            <?php 
                            $cron_patterns = array('bfpi_15min'=>'*/15 * * * *','hourly'=>'0 * * * *','bfpi_6hours'=>'0 */6 * * *','daily'=>'0 0 * * *','weekly'=>'0 0 * * 0','monthly'=>'0 0 1 * *');
                            $pattern = $cron_patterns[$import['schedule_type']] ?? '* * * * *';
                            $cmd = $pattern . ' curl -s "' . $cron_url . '" > /dev/null 2>&1';
                            ?>
                            <code style="display: block; padding: 8px; background: #1e1e1e; color: #9cdcfe; border-radius: 4px; font-size: 11px; word-break: break-all;"><?php echo esc_html($cmd); ?></code>
                            <button type="button" class="button button-small" style="margin-top: 5px;" onclick="navigator.clipboard.writeText('<?php echo esc_js($cmd); ?>'); alert('<?php esc_html_e('Copied!', 'bootflow-product-xml-csv-importer'); ?>');">
                                <?php esc_html_e('Copy Command', 'bootflow-product-xml-csv-importer'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
                <p class="description" style="margin-top: 10px; margin-bottom: 0;">
                    <span style="color: #0073aa;">ℹ️</span> 
                    <?php esc_html_e('We recommend running cron every minute. The plugin will only process when the scheduled interval has passed.', 'bootflow-product-xml-csv-importer'); ?>
                </p>
            </div>
        </div>
        
        <!-- Hidden inputs for engine JSON data (populated by JS on submit) -->
        <input type="hidden" name="pricing_engine_json" id="pricing_engine_json" value="" />
        <input type="hidden" name="shipping_class_engine_json" id="shipping_class_engine_json" value="" />
        
        <p class="submit">
            <input type="submit" name="update_import" class="button button-primary button-large" value="<?php esc_html_e('Save Changes', 'bootflow-product-xml-csv-importer'); ?>" />
            
            <input type="submit" name="run_import_now" class="button button-hero" value="<?php esc_html_e('▶ Run Import Now', 'bootflow-product-xml-csv-importer'); ?>" style="background: #00a32a; border-color: #00a32a; color: #fff; margin-left: 10px;" />
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=bfpi-import-history')); ?>" class="button button-secondary">
                <?php esc_html_e('Cancel', 'bootflow-product-xml-csv-importer'); ?>
            </a>
        </p>
    </form>
</div>

<?php ob_start(); ?>
.bfpi-layout {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}
.bfpi-sidebar {
    width: 320px;
    flex-shrink: 0;
}
.bfpi-main {
    flex: 1;
    min-width: 0;
}
.bfpi-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}
.bfpi-card h2,
.bfpi-card h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.bfpi-sidebar .bfpi-card h3 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
}
.file-info p {
    margin: 5px 0;
    font-size: 12px;
}
.file-info strong {
    display: inline-block;
    width: 60px;
}
.structure-content {
    max-height: 400px;
    overflow-y: auto;
    background: #f9f9f9;
    padding: 10px;
    border-radius: 3px;
    margin-top: 10px;
}
.mapping-section {
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.mapping-section.collapsed .section-fields {
    display: none !important;
}
.mapping-section:not(.collapsed) .section-fields {
    display: block !important;
}
.mapping-section.collapsed .dashicons-arrow-down-alt2:before {
    content: "\f345" !important;
}
.mapping-section:not(.collapsed) .dashicons-arrow-down-alt2:before {
    content: "\f347" !important;
}
.section-toggle {
    background: #f6f7f7;
    padding: 12px 15px;
    margin: 0;
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #ddd;
}
.section-toggle:hover {
    background: #f0f0f1;
}
.section-toggle .dashicons {
    margin-right: 8px;
}
.section-toggle .mapped-count {
    margin-left: auto;
    background: #2271b1;
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
}
.section-fields {
    padding: 15px;
}
.processing-mode-select {
    font-size: 13px;
}
.mapping-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}
.field-mapping-row {
    display: grid;
    grid-template-columns: 200px 1fr 180px 40px;
    gap: 15px;
    align-items: start;
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
    background: #fff;
}
.field-mapping-row:hover {
    background: #f9f9f9;
}
.field-target {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.field-label {
    font-weight: 600;
    font-size: 13px;
    color: #1d2327;
}
.field-label.required {
    position: relative;
}
.required-asterisk {
    color: #d63638;
    margin-left: 3px;
}
.field-type {
    font-size: 11px;
    color: #646970;
    text-transform: uppercase;
}
.field-source select,
.processing-mode select {
    width: 100%;
}
.processing-config {
    grid-column: 1 / -1;
    padding: 15px;
    background: #f6f7f7;
    border-radius: 4px;
    margin-top: 10px;
}
.config-panel {
    margin-bottom: 15px;
}
.config-panel:last-child {
    margin-bottom: 0;
}
.config-panel label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 12px;
}
.config-panel textarea {
    width: 100%;
    font-family: monospace;
    font-size: 12px;
}
.config-panel .description {
    margin-top: 5px;
    font-size: 11px;
    color: #646970;
}
.ai-provider-selection {
    margin-bottom: 10px;
}
.ai-provider-selection select {
    width: 100%;
    max-width: 300px;
}
.field-actions {
    display: flex;
    gap: 5px;
    align-items: start;
}
.field-actions .button {
    padding: 4px 8px;
    min-width: auto;
}
.field-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
<?php
$bfpi_edit_css = ob_get_clean();
wp_add_inline_style('bfpi-import-admin', $bfpi_edit_css);
?>

<?php ob_start(); ?>
let customFieldCounter = <?php echo intval($custom_field_index); ?>;

// Toggle custom field config visibility based on processing mode
function toggleCustomFieldConfig(selectElement) {
    const row = selectElement.closest('.custom-field-row');
    const configRow = row.querySelector('.custom-field-config-row');
    const phpConfig = row.querySelector('.php-formula-config');
    const aiConfig = row.querySelector('.ai-processing-config');
    const mode = selectElement.value;
    
    if (mode === 'direct') {
        configRow.style.display = 'none';
    } else {
        configRow.style.display = '';
        phpConfig.style.display = mode === 'php_formula' ? '' : 'none';
        aiConfig.style.display = mode === 'ai_processing' ? '' : 'none';
    }
}

function addCustomField(e) {
    e.preventDefault();
    const container = document.getElementById('custom-fields-container');
    const html = `
        <div class="custom-field-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 10px; border-left: 3px solid #2271b1;">
            <table class="form-table" style="margin: 0;">
                <tr>
                    <td style="width: 20%;">
                        <input type="text" name="custom_fields[${customFieldCounter}][name]" placeholder="<?php esc_html_e('Field Name', 'bootflow-product-xml-csv-importer'); ?>" class="widefat" />
                    </td>
                    <td style="width: 20%;">
                        <select name="custom_fields[${customFieldCounter}][source]" class="widefat">
                            <option value=""><?php esc_html_e('-- Select Source --', 'bootflow-product-xml-csv-importer'); ?></option>
                            <?php foreach ($file_fields as $ff): ?>
                                <option value="<?php echo esc_attr($ff); ?>"><?php echo esc_html($ff); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="width: 15%;">
                        <select name="custom_fields[${customFieldCounter}][type]" class="widefat">
                            <option value="text"><?php esc_html_e('Text', 'bootflow-product-xml-csv-importer'); ?></option>
                            <option value="number"><?php esc_html_e('Number', 'bootflow-product-xml-csv-importer'); ?></option>
                            <option value="textarea"><?php esc_html_e('Textarea', 'bootflow-product-xml-csv-importer'); ?></option>
                        </select>
                    </td>
                    <td style="width: 35%;">
                        
                    </td>
                    <td style="width: 10%; text-align: center;">
                        <button type="button" class="button" onclick="this.closest('.custom-field-row').remove();" title="<?php esc_html_e('Remove', 'bootflow-product-xml-csv-importer'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
                <tr class="custom-field-config-row" style="display:none;">
                    <td colspan="5" style="padding-top: 10px;">
                        <!-- PHP Formula Config -->
                        
                        <!-- AI Processing Config -->
                        
                    </td>
                </tr>
            </table>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    customFieldCounter++;
}

// Filter rule functions
let filterRuleCounter = <?php echo intval( !empty($existing_filters) ? count($existing_filters) : 0 ); ?>;

function addFilterRule(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const container = document.getElementById('import-filters-container');
    const noFilters = container.querySelector('.no-filters');
    if (noFilters) noFilters.remove();
    
    const html = `
        <div class="filter-rule-row" style="display: flex; gap: 10px; align-items: center; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
            <div style="flex: 1;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Field', 'bootflow-product-xml-csv-importer'); ?></label>
                <select name="import_filters[${filterRuleCounter}][field]" style="width: 100%;">
                    <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-xml-csv-importer'); ?></option>
                    <?php foreach ($file_fields as $ff): ?>
                        <option value="<?php echo esc_attr($ff); ?>"><?php echo esc_html($ff); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="flex: 0 0 150px;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Operator', 'bootflow-product-xml-csv-importer'); ?></label>
                <select name="import_filters[${filterRuleCounter}][operator]" style="width: 100%;">
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
                </select>
            </div>
            
            <div style="flex: 1;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Value', 'bootflow-product-xml-csv-importer'); ?></label>
                <input type="text" name="import_filters[${filterRuleCounter}][value]" placeholder="<?php esc_html_e('Comparison value', 'bootflow-product-xml-csv-importer'); ?>" style="width: 100%;" />
            </div>
            
            <div style="flex: 0 0 40px;">
                <label style="display: block; font-size: 11px; color: transparent; margin-bottom: 3px;">.</label>
                <button type="button" class="button button-small" onclick="removeFilterRule(event)" style="padding: 6px 10px;">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    filterRuleCounter++;
    
    // Show logic toggle if more than one filter
    const filterCount = container.querySelectorAll('.filter-rule-row').length;
    const logicToggle = document.getElementById('filter-logic-toggle');
    if (filterCount > 1) {
        logicToggle.style.display = '';
    }
}

function removeFilterRule(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const row = e.target.closest('.filter-rule-row');
    const container = document.getElementById('import-filters-container');
    
    row.remove();
    
    const filterCount = container.querySelectorAll('.filter-rule-row').length;
    const logicToggle = document.getElementById('filter-logic-toggle');
    
    if (filterCount <= 1) {
        logicToggle.style.display = 'none';
    }
    
    if (filterCount === 0) {
        container.innerHTML = '<p class="no-filters" style="padding: 15px; color: #666; text-align: center;"><?php echo esc_js(esc_html__('No filters added. All products will be imported.', 'bootflow-product-xml-csv-importer')); ?></p>';
    }
}

function clearAllMapping() {
    if (confirm('<?php echo esc_js(__('Are you sure you want to clear all field mappings?', 'bootflow-product-xml-csv-importer')); ?>')) {
        // Clear all source dropdowns
        document.querySelectorAll('select[name^="field_mapping"]').forEach(select => {
            if (select.name.includes('[source]')) {
                select.value = '';
            }
        });
        // Clear custom fields
        document.getElementById('custom-fields-container').innerHTML = '';
    }
}

// Collapse all sections by default except first
document.addEventListener('DOMContentLoaded', function() {
    void 0 && console.log('Import edit page loaded');
    
    const sections = document.querySelectorAll('.mapping-section');
    void 0 && console.log('Found sections:', sections.length);
    
    sections.forEach((section, index) => {
        if (index > 0) {
            section.classList.add('collapsed');
        }
    });
    
    // Add click handlers to section toggles
    document.querySelectorAll('.section-toggle').forEach((toggle, idx) => {
        void 0 && console.log('Adding listener to toggle', idx, toggle);
        
        toggle.addEventListener('click', function(e) {
            void 0 && console.log('Section toggle clicked', e.target);
            
            // Don't toggle if clicking on button inside toggle
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                void 0 && console.log('Button clicked, ignoring toggle');
                return;
            }
            
            const section = this.closest('.mapping-section');
            if (!section) {
                void 0 && console.log('ERROR: No .mapping-section found!');
                return;
            }
            
            const wasCollapsed = section.classList.contains('collapsed');
            section.classList.toggle('collapsed');
            
            void 0 && console.log('Section toggled. Was collapsed:', wasCollapsed, 'Now collapsed:', section.classList.contains('collapsed'));
            
            // Force visibility check and update
            const fieldsDiv = section.querySelector('.section-fields');
            if (fieldsDiv) {
                if (section.classList.contains('collapsed')) {
                    fieldsDiv.style.display = 'none';
                } else {
                    fieldsDiv.style.display = 'block';
                }
                void 0 && console.log('Fields div display:', window.getComputedStyle(fieldsDiv).display);
            }
        });
        
        // Make it clear it's clickable
        toggle.style.cursor = 'pointer';
    });
    
    // Handle processing mode changes
    document.querySelectorAll('.processing-mode-select').forEach(select => {
        select.addEventListener('change', function() {
            const row = this.closest('.field-mapping-row');
            if (!row) return;
            
            const configDiv = row.querySelector('.processing-config');
            if (!configDiv) return;
            
            const mode = this.value;
            
            if (mode === 'direct') {
                configDiv.style.display = 'none';
            } else {
                configDiv.style.display = 'block';
                
                // Show/hide relevant config panels
                configDiv.querySelectorAll('.config-panel').forEach(panel => {
                    panel.style.display = 'none';
                });
                
                const activePanel = configDiv.querySelector('.' + mode.replace('_', '-') + '-config');
                if (activePanel) {
                    activePanel.style.display = 'block';
                }
            }
        });
    });
    
    // Handle toggle config button
    document.querySelectorAll('.toggle-config').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const row = this.closest('.field-mapping-row');
            if (!row) return;
            
            const configDiv = row.querySelector('.processing-config');
            if (!configDiv) return;
            
            if (configDiv.style.display === 'none' || !configDiv.style.display) {
                configDiv.style.display = 'block';
            } else {
                configDiv.style.display = 'none';
            }
        });
    });
    
    // Handle clear mapping button
    document.querySelectorAll('.clear-mapping').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('<?php echo esc_js(__('Clear this field mapping?', 'bootflow-product-xml-csv-importer')); ?>')) {
                const row = this.closest('.field-mapping-row');
                if (!row) return;
                
                // Support both old select and new textarea UI
                const sourceSelect = row.querySelector('.field-source-select');
                const sourceTextarea = row.querySelector('.field-mapping-textarea');
                const modeSelect = row.querySelector('.processing-mode-select');
                const configDiv = row.querySelector('.processing-config');
                
                if (sourceSelect) sourceSelect.value = '';
                if (sourceTextarea) {
                    sourceTextarea.value = '';
                    sourceTextarea.dispatchEvent(new Event('input')); // Trigger input to update preview
                }
                if (modeSelect) modeSelect.value = 'direct';
                if (configDiv) configDiv.style.display = 'none';
                
                // Clear all config inputs
                row.querySelectorAll('textarea:not(.field-mapping-textarea)').forEach(ta => ta.value = '');
            }
        });
    });
    
    // Populate existing filter field dropdowns when file structure loads
    // This is triggered by admin.js after AJAX loads the structure
    window.populateExistingFilterDropdowns = function() {
        document.querySelectorAll('.filter-field-select').forEach(select => {
            if (select.options.length <= 1 && window.currentFileStructure) {
                const selectedValue = select.getAttribute('data-selected') || '';
                let options = '<option value="">-- Select Field --</option>';
                window.currentFileStructure.forEach(field => {
                    const selected = field.path === selectedValue ? ' selected' : '';
                    options += `<option value="${field.path}"${selected}>${field.path}</option>`;
                });
                select.innerHTML = options;
            }
        });
    };
    
    // Toggle missing products options visibility in edit mode
    const handleMissingCheckbox = document.getElementById('handle_missing_edit');
    if (handleMissingCheckbox) {
        handleMissingCheckbox.addEventListener('change', function() {
            const optionsDiv = document.getElementById('missing-products-options-edit');
            if (optionsDiv) {
                optionsDiv.style.display = this.checked ? 'block' : 'none';
            }
        });
    }
    
    // Toggle schedule method row visibility based on schedule type
    const scheduleTypeSelect = document.getElementById('schedule_type_edit');
    if (scheduleTypeSelect) {
        scheduleTypeSelect.addEventListener('change', function() {
            const methodRow = document.getElementById('schedule_method_row');
            const cronInstructions = document.getElementById('server_cron_instructions');
            if (methodRow) {
                methodRow.style.display = (this.value === 'none' || this.value === '') ? 'none' : '';
            }
            if (cronInstructions && this.value === 'none') {
                cronInstructions.style.display = 'none';
            }
            // Update hidden field
            const hiddenField = document.querySelector('input[name="schedule_type_hidden"]');
            if (hiddenField) {
                hiddenField.value = this.value;
            }
        });
    }
    
    // Toggle server cron instructions and update label styling
    document.querySelectorAll('input[name="schedule_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const cronInstructions = document.getElementById('server_cron_instructions');
            
            // Update label styling
            document.querySelectorAll('input[name="schedule_method"]').forEach(r => {
                const label = r.closest('label');
                if (label) {
                    if (r.checked) {
                        label.style.borderColor = '#0073aa';
                        label.style.background = '#f0f6fc';
                    } else {
                        label.style.borderColor = '#ddd';
                        label.style.background = '#fff';
                    }
                }
            });
            
            // Show/hide server cron instructions
            if (cronInstructions) {
                cronInstructions.style.display = (this.value === 'server_cron') ? 'block' : 'none';
            }
            
            // Update hidden field
            const hiddenField = document.querySelector('input[name="schedule_method_hidden"]');
            if (hiddenField) {
                hiddenField.value = this.value;
            }
        });
    });
    // Collect engine data on form submit
    var editForm = document.getElementById('import-edit-form');
    if (editForm) {
        console.log('BFPI DEBUG: submit handler ATTACHED to import-edit-form');
        editForm.addEventListener('submit', function(e) {
            console.log('BFPI DEBUG: form submit triggered');
            console.log('BFPI DEBUG: getPricingEngineConfig exists?', typeof window.getPricingEngineConfig);
            console.log('BFPI DEBUG: getShippingClassEngineConfig exists?', typeof window.getShippingClassEngineConfig);
            // Collect Pricing Engine config
            if (typeof window.getPricingEngineConfig === 'function') {
                var pricingConfig = window.getPricingEngineConfig();
                console.log('BFPI DEBUG: pricingConfig =', JSON.stringify(pricingConfig));
                var pricingInput = document.getElementById('pricing_engine_json');
                if (pricingInput) {
                    pricingInput.value = JSON.stringify(pricingConfig);
                    console.log('BFPI DEBUG: pricing_engine_json value SET, length:', pricingInput.value.length);
                } else {
                    console.log('BFPI DEBUG: pricing_engine_json hidden input NOT FOUND');
                }
            } else {
                console.log('BFPI DEBUG: getPricingEngineConfig NOT a function - engines not initialized?');
            }
            // Collect Shipping Class Engine config
            if (typeof window.getShippingClassEngineConfig === 'function') {
                var shippingConfig = window.getShippingClassEngineConfig();
                console.log('BFPI DEBUG: shippingConfig =', JSON.stringify(shippingConfig));
                var shippingInput = document.getElementById('shipping_class_engine_json');
                if (shippingInput) {
                    shippingInput.value = JSON.stringify(shippingConfig);
                    console.log('BFPI DEBUG: shipping_class_engine_json value SET, length:', shippingInput.value.length);
                } else {
                    console.log('BFPI DEBUG: shipping_class_engine_json hidden input NOT FOUND');
                }
            } else {
                console.log('BFPI DEBUG: getShippingClassEngineConfig NOT a function');
            }
        });
    } else {
        console.log('BFPI DEBUG: import-edit-form NOT FOUND');
    }
});
<?php
$bfpi_edit_js = ob_get_clean();
wp_add_inline_script('bfpi-import-admin', $bfpi_edit_js, 'after');
?>
```
