<?php
/**
 * Step 3: Import Progress Interface
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

// Get import ID from URL
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
$import_id = isset($_GET['import_id']) ? absint( wp_unslash( $_GET['import_id'] ) ) : 0;

if (!$import_id) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Invalid import ID. Please start a new import.', 'bootflow-product-xml-csv-importer') . '</p></div>';
    return;
}

// Get import details from database
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$import = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bfpi_imports WHERE id = %d",
    $import_id
));

if (!$import) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Import not found.', 'bootflow-product-xml-csv-importer') . '</p></div>';
    return;
}

$percentage = $import->total_products > 0 ? round(($import->processed_products / $import->total_products) * 100) : 0;
?>

<?php ob_start(); ?>
/* Live progress animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.import-status.processing {
    animation: pulse 2s infinite;
}

.progress-circle {
    transition: background 0.5s ease-out;
}

/* Live Progress Section - Fixed */
.live-progress-section {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Product Activity Log */
.import-logs {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
    scrollbar-width: thin;
    scrollbar-color: #ccc #f5f5f5;
}

.import-logs::-webkit-scrollbar {
    width: 8px;
}

.import-logs::-webkit-scrollbar-track {
    background: #f5f5f5;
    border-radius: 4px;
}

.import-logs::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 4px;
}

.import-logs::-webkit-scrollbar-thumb:hover {
    background: #aaa;
}

.log-entry {
    padding: 8px 12px;
    margin-bottom: 4px;
    border-left: 3px solid #ddd;
    background: #f9f9f9;
    border-radius: 3px;
    transition: all 0.2s ease;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    animation: slideIn 0.3s ease;
}

.log-entry:hover {
    background: #f0f0f0;
    border-left-color: #0073aa;
}

.log-entry .log-icon {
    flex-shrink: 0;
    font-size: 14px;
}

.log-entry .log-time {
    color: #888;
    flex-shrink: 0;
    font-size: 11px;
}

.log-entry .log-message {
    flex: 1;
    word-break: break-word;
}

.log-entry .log-sku {
    color: #0073aa;
    font-weight: 500;
    flex-shrink: 0;
}

.log-entry.error {
    border-left-color: #dc3232;
    background: #fee;
}

.log-entry.success {
    border-left-color: #46b450;
    background: #efe;
}

.log-entry.warning {
    border-left-color: #ffb900;
    background: #fff8e5;
}

.log-entry.info {
    border-left-color: #0073aa;
    background: #f0f6fc;
}

/* Smooth number counter animation */
.stat-value {
    transition: all 0.3s ease;
}
<?php
$bfpi_progress_css = ob_get_clean();
wp_add_inline_style('bfpi-import-admin', $bfpi_progress_css);
?>

<div class="bfpi-step bfpi-step-3" data-import-id="<?php echo esc_attr($import_id); ?>" data-batch-size="<?php echo esc_attr($import->batch_size ?? 50); ?>">
    <div class="import-progress-card">
        <h2><?php esc_html_e('Import Progress', 'bootflow-product-xml-csv-importer'); ?></h2>
        <p class="description"><?php 
        // translators: %s is the import name
        printf(esc_html__('Importing "%s" - Please keep this page open until the import is complete.', 'bootflow-product-xml-csv-importer'), esc_html($import->name)); ?></p>
        
        <!-- Cron Info Box -->
        <div class="cron-info-box" style="background: linear-gradient(135deg, #e8f4fd 0%, #f0f7ff 100%); border: 1px solid #0073aa; border-radius: 8px; padding: 15px; margin: 15px 0; display: flex; align-items: flex-start; gap: 12px;">
            <span style="font-size: 24px;">💡</span>
            <div>
                <strong style="color: #0073aa;"><?php esc_html_e('Want to close this page?', 'bootflow-product-xml-csv-importer'); ?></strong>
                <p style="margin: 5px 0 10px; color: #555; font-size: 13px;">
                    <?php esc_html_e('Set up a cron job to continue imports in the background, even when the browser is closed.', 'bootflow-product-xml-csv-importer'); ?>
                </p>
                <details style="cursor: pointer;">
                    <summary style="color: #0073aa; font-weight: 500;"><?php esc_html_e('Show cron setup instructions', 'bootflow-product-xml-csv-importer'); ?></summary>
                    <div style="background: #fff; border-radius: 4px; padding: 12px; margin-top: 10px; font-size: 12px;">
                        <p style="margin: 0 0 8px;"><strong><?php esc_html_e('cPanel / Plesk:', 'bootflow-product-xml-csv-importer'); ?></strong></p>
                        <p style="margin: 0 0 5px;"><?php esc_html_e('Add a new Cron Job with:', 'bootflow-product-xml-csv-importer'); ?></p>
                        <ul style="margin: 0 0 10px; padding-left: 20px;">
                            <li><?php esc_html_e('Schedule: Every minute', 'bootflow-product-xml-csv-importer'); ?> (<code>* * * * *</code>)</li>
                        </ul>
                        <p style="margin: 0 0 5px;"><strong><?php esc_html_e('Command:', 'bootflow-product-xml-csv-importer'); ?></strong></p>
                        <code style="display: block; background: #f5f5f5; padding: 8px; border-radius: 4px; word-break: break-all; font-size: 11px;">wget -q -O /dev/null "<?php echo esc_url(site_url('/wp-cron.php')); ?>" &gt;/dev/null 2&gt;&amp;1</code>
                        <p style="margin: 10px 0 0; color: #666; font-size: 11px;">
                            <?php esc_html_e('Once configured, imports will continue automatically even if you close this page.', 'bootflow-product-xml-csv-importer'); ?>
                        </p>
                    </div>
                </details>
            </div>
        </div>
        
        <!-- Import Status -->
        <div class="import-status <?php echo esc_attr($import->status); ?>">
            <?php 
            switch ($import->status) {
                case 'preparing':
                    esc_html_e('Preparing Import...', 'bootflow-product-xml-csv-importer');
                    break;
                case 'processing':
                    esc_html_e('Processing Products...', 'bootflow-product-xml-csv-importer');
                    break;
                case 'completed':
                    esc_html_e('Import Completed Successfully!', 'bootflow-product-xml-csv-importer');
                    break;
                case 'failed':
                    esc_html_e('Import Failed', 'bootflow-product-xml-csv-importer');
                    break;
                default:
                    esc_html_e('Unknown Status', 'bootflow-product-xml-csv-importer');
                    break;
            }
            ?>
        </div>

        <!-- Progress Circle -->
        <div class="progress-circle" style="background: conic-gradient(#0073aa <?php echo esc_attr($percentage); ?>%, #f0f0f0 0%);">
            <div class="progress-percentage"><?php echo esc_html($percentage); ?>%</div>
        </div>

        <!-- Import Statistics -->
        <div class="import-stats">
            <div class="stat-item">
                <span class="stat-value"><?php echo esc_html(number_format($import->total_products)); ?></span>
                <span class="stat-label"><?php esc_html_e('Total Products', 'bootflow-product-xml-csv-importer'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo esc_html(number_format($import->processed_products)); ?></span>
                <span class="stat-label"><?php esc_html_e('Processed', 'bootflow-product-xml-csv-importer'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo esc_html(number_format($import->total_products - $import->processed_products)); ?></span>
                <span class="stat-label"><?php esc_html_e('Remaining', 'bootflow-product-xml-csv-importer'); ?></span>
            </div>
        </div>

        <!-- Import Details -->
        <div class="import-details">
            <table class="wp-list-table widefat">
                <tr>
                    <td><strong><?php esc_html_e('Import Name:', 'bootflow-product-xml-csv-importer'); ?></strong></td>
                    <td><?php echo esc_html($import->name); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('File Type:', 'bootflow-product-xml-csv-importer'); ?></strong></td>
                    <td><?php echo esc_html(strtoupper($import->file_type)); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Started:', 'bootflow-product-xml-csv-importer'); ?></strong></td>
                    <td><?php echo esc_html(Bfpi_i18n::localize_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import->created_at))); ?></td>
                </tr>
                <?php if ($import->last_run): ?>
                <tr>
                    <td><strong><?php esc_html_e('Last Activity:', 'bootflow-product-xml-csv-importer'); ?></strong></td>
                    <td><?php echo esc_html(Bfpi_i18n::localize_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import->last_run))); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong><?php esc_html_e('Schedule:', 'bootflow-product-xml-csv-importer'); ?></strong></td>
                    <td><?php echo esc_html(ucfirst($import->schedule_type)); ?></td>
                </tr>
            </table>
        </div>

        <!-- Import Controls -->
        <div class="import-controls" style="margin: 20px 0;">
            <?php if (in_array($import->status, array('processing', 'pending', 'preparing', 'active'))): ?>
                <button type="button" id="pause-import" class="button button-secondary">
                    <span class="dashicons dashicons-controls-pause"></span>
                    <?php esc_html_e('Pause Import', 'bootflow-product-xml-csv-importer'); ?>
                </button>
                <button type="button" id="stop-import" class="button button-secondary" style="color: #d63638; border-color: #d63638;">
                    <span class="dashicons dashicons-controls-stop"></span>
                    <?php esc_html_e('Stop Import', 'bootflow-product-xml-csv-importer'); ?>
                </button>
            <?php elseif ($import->status === 'paused'): ?>
                <button type="button" id="resume-import" class="button button-primary">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php esc_html_e('Resume Import', 'bootflow-product-xml-csv-importer'); ?>
                </button>
                <button type="button" id="stop-import" class="button button-secondary" style="color: #d63638; border-color: #d63638;">
                    <span class="dashicons dashicons-controls-stop"></span>
                    <?php esc_html_e('Stop Import', 'bootflow-product-xml-csv-importer'); ?>
                </button>
            <?php elseif ($import->status === 'failed'): ?>
                <button type="button" id="retry-import" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Retry Import', 'bootflow-product-xml-csv-importer'); ?>
                </button>
            <?php endif; ?>

            <button type="button" id="view-logs" class="button button-secondary">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e('View Detailed Logs', 'bootflow-product-xml-csv-importer'); ?>
            </button>
        </div>

        <!-- Live Progress Stats (Fixed at top) -->
        <div class="live-progress-section" id="live-progress-section" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; position: sticky; top: 32px; z-index: 100;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <!-- Progress Bar -->
                <div style="flex: 2; min-width: 250px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-weight: 600; color: #495057;"><?php esc_html_e('Progress', 'bootflow-product-xml-csv-importer'); ?></span>
                        <span id="live-progress-text" style="font-weight: 700; color: #0073aa;"><?php echo esc_html($percentage); ?>%</span>
                    </div>
                    <div style="background: #e9ecef; border-radius: 10px; height: 20px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                        <div id="live-progress-bar" style="background: linear-gradient(90deg, #0073aa, #00a0d2); height: 100%; width: <?php echo esc_attr($percentage); ?>%; transition: width 0.5s ease; border-radius: 10px;"></div>
                    </div>
                </div>
                
                <!-- Stats -->
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="text-align: center; min-width: 80px;">
                        <div id="live-processed" style="font-size: 24px; font-weight: 700; color: #28a745;"><?php echo esc_html($import->processed_products); ?></div>
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase;"><?php esc_html_e('Processed', 'bootflow-product-xml-csv-importer'); ?></div>
                    </div>
                    <div style="text-align: center; min-width: 80px;">
                        <div id="live-total" style="font-size: 24px; font-weight: 700; color: #495057;"><?php echo esc_html($import->total_products); ?></div>
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase;"><?php esc_html_e('Total', 'bootflow-product-xml-csv-importer'); ?></div>
                    </div>
                    <div style="text-align: center; min-width: 80px;">
                        <div id="live-speed" style="font-size: 24px; font-weight: 700; color: #17a2b8;">-</div>
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase;"><?php esc_html_e('prod/min', 'bootflow-product-xml-csv-importer'); ?></div>
                    </div>
                    <div style="text-align: center; min-width: 80px;">
                        <div id="live-eta" style="font-size: 24px; font-weight: 700; color: #6f42c1;">-</div>
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase;"><?php esc_html_e('ETA', 'bootflow-product-xml-csv-importer'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Current chunk info -->
            <div id="live-chunk-info" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #dee2e6; font-size: 13px; color: #6c757d;">
                <span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>
                <span id="live-current-action"><?php esc_html_e('Waiting for progress...', 'bootflow-product-xml-csv-importer'); ?></span>
            </div>
        </div>

        <!-- Product Activity Log (Scrollable) -->
        
        <!-- Performance Metrics -->
        <?php if ($import->status === 'processing' || $import->status === 'completed'): ?>
        <div class="performance-metrics" style="margin-top: 20px;">
            <h3>
                <span class="dashicons dashicons-chart-line"></span>
                <?php esc_html_e('Performance Metrics', 'bootflow-product-xml-csv-importer'); ?>
            </h3>
            <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="metric-item">
                    <div class="metric-label"><?php esc_html_e('Processing Speed', 'bootflow-product-xml-csv-importer'); ?></div>
                    <div class="metric-value" id="processing-speed">
                        <?php
                        if ($import->last_run && $import->created_at) {
                            $elapsed = strtotime($import->last_run) - strtotime($import->created_at);
                            $rate = $elapsed > 0 ? round($import->processed_products / ($elapsed / 60), 2) : 0;
                            echo esc_html($rate) . ' ' . esc_html__('products/min', 'bootflow-product-xml-csv-importer');
                        } else {
                            echo esc_html__('Calculating...', 'bootflow-product-xml-csv-importer');
                        }
                        ?>
                    </div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><?php esc_html_e('Estimated Time Remaining', 'bootflow-product-xml-csv-importer'); ?></div>
                    <div class="metric-value" id="time-remaining">
                        <?php
                        if ($import->processed_products > 0 && $import->total_products > $import->processed_products) {
                            $remaining = $import->total_products - $import->processed_products;
                            if (isset($rate) && $rate > 0) {
                                $eta_minutes = round($remaining / $rate);
                                if ($eta_minutes < 60) {
                                    echo esc_html($eta_minutes) . ' ' . esc_html__('minutes', 'bootflow-product-xml-csv-importer');
                                } else {
                                    $eta_hours = floor($eta_minutes / 60);
                                    $eta_mins = $eta_minutes % 60;
                                    echo esc_html($eta_hours) . 'h ' . esc_html($eta_mins) . 'm';
                                }
                            } else {
                                echo esc_html__('Calculating...', 'bootflow-product-xml-csv-importer');
                            }
                        } else {
                            echo esc_html__('N/A', 'bootflow-product-xml-csv-importer');
                        }
                        ?>
                    </div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><?php esc_html_e('Success Rate', 'bootflow-product-xml-csv-importer'); ?></div>
                    <div class="metric-value" id="success-rate">
                        <?php
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $error_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}bfpi_import_logs WHERE import_id = %d AND level = 'error'",
                            $import_id
                        ));
                        $success_rate = $import->processed_products > 0 ? round((($import->processed_products - $error_count) / $import->processed_products) * 100, 1) : 100;
                        echo esc_html($success_rate) . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions after completion -->
        <?php if ($import->status === 'completed'): ?>
        <div class="completion-actions" style="margin-top: 30px; padding: 20px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 6px;">
            <h3><?php esc_html_e('Import Completed Successfully!', 'bootflow-product-xml-csv-importer'); ?></h3>
            <p><?php 
            // translators: %1$d is the number of successfully imported products, %2$d is the total number of products
            printf(esc_html__('Successfully imported %1$d out of %2$d products.', 'bootflow-product-xml-csv-importer'), intval($import->processed_products), intval($import->total_products)); ?></p>
            
            <div class="action-buttons" style="margin-top: 15px;">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-products"></span>
                    <?php esc_html_e('View Products', 'bootflow-product-xml-csv-importer'); ?>
                </a>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=bfpi-import')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Start New Import', 'bootflow-product-xml-csv-importer'); ?>
                </a>
                
                                

            </div>
        </div>
        <?php endif; ?>

        <!-- Error information -->
        <?php if ($import->status === 'failed'): ?>
        <div class="error-information" style="margin-top: 20px; padding: 20px; background: #fee; border: 1px solid #dc3545; border-radius: 6px;">
            <h3><?php esc_html_e('Import Failed', 'bootflow-product-xml-csv-importer'); ?></h3>
            <p><?php esc_html_e('The import process encountered an error and could not be completed. Please check the logs below for more details.', 'bootflow-product-xml-csv-importer'); ?></p>
            
            <?php
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $error_logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bfpi_import_logs WHERE import_id = %d AND level = 'error' ORDER BY created_at DESC LIMIT 5",
                $import_id
            ));
            
            if (!empty($error_logs)): ?>
            <div class="error-logs">
                <h4><?php esc_html_e('Recent Errors:', 'bootflow-product-xml-csv-importer'); ?></h4>
                <?php foreach ($error_logs as $error_log): ?>
                <div class="error-log-entry">
                    <strong><?php echo esc_html(date_i18n('H:i:s', strtotime($error_log->created_at))); ?></strong>: 
                    <?php echo esc_html($error_log->message); ?>
                    <?php if (!empty($error_log->product_sku)): ?>
                        (SKU: <?php echo esc_html($error_log->product_sku); ?>)
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="error-actions" style="margin-top: 15px;">
                <button type="button" id="retry-import" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Retry Import', 'bootflow-product-xml-csv-importer'); ?>
                </button>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=bfpi-import')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Start Over', 'bootflow-product-xml-csv-importer'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    var importId = $('.bfpi-step-3').data('import-id');
    var batchSize = parseInt($('.bfpi-step-3').data('batch-size')) || 50;
    var progressInterval;
    var importKickstarted = false;
    var currentProcessed = 0; // Track current processed count for animation
    var currentTotal = 0; // Track total
    var lastLogId = 0; // Track last log ID to avoid duplicates
    
    // Start monitoring if import is still processing
    var importStatus = '<?php echo esc_js($import->status); ?>';
    var processedProducts = <?php echo intval($import->processed_products); ?>;
    var totalProducts = <?php echo intval($import->total_products); ?>;
    
    void 0 && console.log('Import Progress Monitor initialized:', {
        importId: importId,
        batchSize: batchSize,
        status: importStatus,
        processed: processedProducts,
        total: totalProducts
    });
    
    // Initialize current percentage and counts from server
    currentPercentage = totalProducts > 0 ? Math.round((processedProducts / totalProducts) * 100) : 0;
    currentProcessed = processedProducts;
    currentTotal = totalProducts;
    
    // Kickstart import if status is pending (happens after re-run/resume)
    // For pending status, always kickstart regardless of processed count (resume case)
    if ((importStatus === 'pending' || (importStatus === 'processing' && processedProducts === 0)) && totalProducts > 0 && !importKickstarted) {
        void 0 && console.log('Import needs kickstart - status:', importStatus, 'processed:', processedProducts);
        importKickstarted = true;
        
        // Reset progress to 0% before kickstart
        updateProgressBar(0);
        
        $.ajax({
            url: bfpi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bfpi_kickstart',
                import_id: importId,
                nonce: bfpi_ajax.nonce
            },
            success: function(response) {
                void 0 && console.log('Kickstart response:', response);
                // Start monitoring after kickstart
                setTimeout(function() {
                    startProgressMonitoring();
                }, 500); // Reduced delay for faster response
            },
            error: function() {
                console.error('Failed to kickstart import');
                // Start monitoring anyway
                startProgressMonitoring();
            }
        });
    } else if (importStatus === 'processing' || importStatus === 'preparing' || importStatus === 'pending') {
        // Start monitoring immediately for active imports
        startProgressMonitoring();
    }
    
    function startProgressMonitoring() {
        void 0 && console.log('Starting progress monitoring...');
        
        // Initial update
        updateProgressImmediately();
        
        // Poll every 1 second for more responsive UI
        progressInterval = setInterval(function() {
            updateProgressImmediately();
        }, 1000); // Update every 1 second (was 3 seconds)
        
        // CRITICAL: Ping WP-Cron every 2 seconds to keep it running
        // This also reschedules stuck imports automatically
        setInterval(function() {
            pingCron();
        }, 2000); // Ping every 2 seconds
        
        // Initial cron ping
        pingCron();
    }
    
    function pingCron() {
        // Use AJAX POST for reliability instead of Image GET
        $.ajax({
            url: bfpi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bfpi_ping_cron',
                import_id: importId,
                nonce: bfpi_ajax.nonce
            },
            timeout: 5000, // 5 second timeout
            success: function(response) {
                // Silent success - cron was triggered
            },
            error: function() {
                // Silent fail - will retry in 2 seconds
            }
        });
    }
    
    function updateProgressImmediately() {
        $.ajax({
            url: bfpi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bfpi_get_progress',
                import_id: importId,
                nonce: bfpi_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    void 0 && console.log('Progress update:', response.data);
                    updateProgress(response.data);
                    
                    // NOTE: Batch processing happens via WP-Cron in background
                    // DO NOT trigger AJAX batch processing - it causes timeouts with AI processing
                    // Just monitor progress and let WP-Cron handle the actual work
                    
                    if (response.data.status === 'completed' || response.data.status === 'failed') {
                        clearInterval(progressInterval);
                        void 0 && console.log('Import finished with status:', response.data.status);
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to get progress update:', error);
            }
        });
    }
    
    function updateProgressBar(targetPercentage) {
        // Smooth animation 1% at a time
        if (currentPercentage === targetPercentage) {
            return; // Already at target
        }
        
        // Clear any existing animation
        if (window.progressAnimationInterval) {
            clearInterval(window.progressAnimationInterval);
        }
        
        // Animate 1% per step, 100ms per step = smooth visible progress
        window.progressAnimationInterval = setInterval(function() {
            if (currentPercentage < targetPercentage) {
                currentPercentage++;
            } else if (currentPercentage > targetPercentage) {
                currentPercentage--;
            }
            
            // Update progress circle
            $('.progress-circle').css('background', 'conic-gradient(#0073aa ' + currentPercentage + '%, #f0f0f0 0%)');
            $('.progress-percentage').text(currentPercentage + '%');
            
            // Stop when reached target
            if (currentPercentage === targetPercentage) {
                clearInterval(window.progressAnimationInterval);
            }
        }, 100); // 100ms per 1% = smooth visible animation
    }
    
    function updateProductCounts(targetProcessed, targetTotal) {
        // Smooth animation for product counts
        if (currentProcessed === targetProcessed && currentTotal === targetTotal) {
            return; // Already at target
        }
        
        // Clear any existing animation
        if (window.countAnimationInterval) {
            clearInterval(window.countAnimationInterval);
        }
        
        // Animate counts one by one, faster than percentage (50ms per product)
        window.countAnimationInterval = setInterval(function() {
            var changed = false;
            
            if (currentProcessed < targetProcessed) {
                currentProcessed++;
                changed = true;
            } else if (currentProcessed > targetProcessed) {
                currentProcessed--;
                changed = true;
            }
            
            if (currentTotal < targetTotal) {
                currentTotal++;
                changed = true;
            } else if (currentTotal > targetTotal) {
                currentTotal--;
                changed = true;
            }
            
            // Update displayed values
            $('.stat-item').eq(0).find('.stat-value').text(Number(currentTotal).toLocaleString());
            $('.stat-item').eq(1).find('.stat-value').text(Number(currentProcessed).toLocaleString());
            $('.stat-item').eq(2).find('.stat-value').text(Number(currentTotal - currentProcessed).toLocaleString());
            
            // Stop when reached target
            if (!changed || (currentProcessed === targetProcessed && currentTotal === targetTotal)) {
                clearInterval(window.countAnimationInterval);
            }
        }, 50); // 50ms per product = faster than percentage
    }
    
    function updateProgress(data) {
        var percentage = data.percentage || 0;
        
        // Smooth progress bar update (1% at a time)
        updateProgressBar(percentage);
        
        // Smooth product count update (1 product at a time)
        updateProductCounts(data.processed_products || 0, data.total_products || 0);
        
        // Update status text and styling
        var statusText = '';
        switch(data.status) {
            case 'preparing':
                statusText = '<?php echo esc_js(__('Preparing Import...', 'bootflow-product-xml-csv-importer')); ?>';
                break;
            case 'processing':
                statusText = '<?php echo esc_js(__('Processing Products...', 'bootflow-product-xml-csv-importer')); ?>';
                break;
            case 'completed':
                statusText = '<?php echo esc_js(__('Import Completed Successfully!', 'bootflow-product-xml-csv-importer')); ?>';
                break;
            case 'failed':
                statusText = '<?php echo esc_js(__('Import Failed', 'bootflow-product-xml-csv-importer')); ?>';
                break;
        }
        $('.import-status').removeClass('preparing processing completed failed').addClass(data.status).text(statusText);
        
        // Update logs with live feed - prepend new logs, keep only latest
        if (data.logs && data.logs.length > 0) {
            updateLiveLogs(data.logs);
        }
    }
    
    function updateLiveLogs(logs) {
        var $logsContainer = $('#import-logs');
        
        // Logs come sorted by ID DESC (newest first)
        // Simply replace all logs with fresh data to avoid jumping/flickering
        // This is cleaner and more reliable than trying to merge
        
        if (!logs || logs.length === 0) {
            return;
        }
        
        // Check if we have new logs by comparing the newest log ID
        var newestLogId = parseInt(logs[0].id);
        if (newestLogId <= lastLogId) {
            return; // No new logs, don't update
        }
        lastLogId = newestLogId;
        
        // Build all log entries HTML at once
        var logsHtml = '';
        logs.forEach(function(log) {
            var timestamp = new Date(log.created_at).toLocaleTimeString();
            logsHtml += '<div class="log-entry ' + log.level + '" data-log-id="' + log.id + '">';
            logsHtml += '[' + timestamp + '] ' + escapeHtml(log.message);
            if (log.product_sku) {
                logsHtml += ' (SKU: ' + escapeHtml(log.product_sku) + ')';
            }
            logsHtml += '</div>';
        });
        
        // Replace all content at once (no flickering, no jumping)
        $logsContainer.html(logsHtml);
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Old updateProgress function - remove/replace above
    /*
    function updateProgress(data) {
        // Update progress circle
        var percentage = data.percentage || 0;
        $('.progress-circle').css('background', 'conic-gradient(#0073aa ' + percentage + '%, #f0f0f0 0%)');
        $('.progress-percentage').text(percentage + '%');
        
        // Update status
        $('.import-status').removeClass('preparing processing completed failed').addClass(data.status);
        
        // Update stats
        $('.stat-item').eq(0).find('.stat-value').text(data.total_products || 0);
        $('.stat-item').eq(1).find('.stat-value').text(data.processed_products || 0);
        $('.stat-item').eq(2).find('.stat-value').text((data.total_products - data.processed_products) || 0);
        
        // Update logs
        if (data.logs && data.logs.length > 0) {
            var logsHtml = '';
            data.logs.slice(0, 10).forEach(function(log) {
                var timestamp = new Date(log.created_at).toLocaleTimeString();
                logsHtml += '<div class="log-entry ' + log.level + '">';
                logsHtml += '[' + timestamp + '] ' + log.message;
                if (log.product_sku) {
                    logsHtml += ' (SKU: ' + log.product_sku + ')';
                }
                logsHtml += '</div>';
            });
            $('#import-logs').html(logsHtml);
        }
    }
    */
    
    // Import control buttons
    $('#pause-import, #resume-import, #stop-import, #retry-import').on('click', function() {
        var action = $(this).attr('id').replace('-import', '');
        
        if (confirm('Are you sure you want to ' + action + ' this import?')) {
            $.ajax({
                url: bfpi_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bfpi_control_import',
                    import_id: importId,
                    control_action: action,
                    nonce: bfpi_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to ' + action + ' import: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Failed to ' + action + ' import.');
                }
            });
        }
    });
    
    // Download report
    $('#download-report').on('click', function() {
        window.open(
            bfpi_ajax.ajax_url + 
            '?action=bfpi_download_report&import_id=' + importId + 
            '&nonce=' + bfpi_ajax.nonce,
            '_blank'
        );
    });
    
    // View detailed logs
    $('#view-logs').on('click', function() {
        window.open(
            '<?php echo esc_url(admin_url("admin.php?page=bfpi-import-logs&import_id=")); ?>' + importId,
            '_blank'
        );
    });
    
    // Auto-refresh page title with progress
    function updatePageTitle() {
        var percentage = $('.progress-percentage').text();
        var originalTitle = document.title.split(' - ')[0];
        document.title = originalTitle + ' - ' + percentage + ' Complete';
    }
    
    if (importStatus === 'processing') {
        setInterval(updatePageTitle, 5000);
    }
});
<?php
$bfpi_progress_js = ob_get_clean();
wp_add_inline_script('bfpi-import-admin', $bfpi_progress_js, 'after');
?>