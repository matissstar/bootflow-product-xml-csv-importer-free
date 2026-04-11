<?php
/**
 * Step 1: File Upload Interface
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

$settings = get_option('bfpi_settings', array());
$max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 100;
?>

<div class="bfpi-step bfpi-step-1">
    <div class="bfpi-card">
        <h2><?php esc_html_e('Step 1: Upload File', 'bootflow-product-xml-csv-importer'); ?></h2>
        <p class="description"><?php esc_html_e('Upload your XML or CSV file, or provide a URL to import products from.', 'bootflow-product-xml-csv-importer'); ?></p>
        
        <form id="bfpi-upload-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('bfpi_nonce', 'nonce'); ?>
            
            <!-- Import Name -->
            <div class="form-group">
                <label for="import_name" class="required">
                    <strong><?php esc_html_e('Import Name', 'bootflow-product-xml-csv-importer'); ?></strong>
                    <span class="required-asterisk">*</span>
                </label>
                <input type="text" id="import_name" name="import_name" class="regular-text" required 
                       placeholder="<?php esc_html_e('e.g., Summer Collection 2024', 'bootflow-product-xml-csv-importer'); ?>" />
                <p class="description"><?php esc_html_e('Give this import a descriptive name for easy identification.', 'bootflow-product-xml-csv-importer'); ?></p>
            </div>

            <!-- Upload Method Selection -->
            <div class="form-group">
                <label><strong><?php esc_html_e('Upload Method', 'bootflow-product-xml-csv-importer'); ?></strong></label>
                <div class="upload-method-selection">
                    <label class="upload-method-option">
                        <input type="radio" name="upload_method" value="file" checked />
                        <span class="method-icon">📁</span>
                        <span class="method-title"><?php esc_html_e('Upload File', 'bootflow-product-xml-csv-importer'); ?></span>
                        <span class="method-desc"><?php esc_html_e('Upload XML/CSV file from your computer', 'bootflow-product-xml-csv-importer'); ?></span>
                    </label>

                </div>
            </div>

            <!-- File Upload -->
            <div id="file-upload-section" class="form-group upload-section">
                <label for="file_upload"><strong><?php esc_html_e('Select File', 'bootflow-product-xml-csv-importer'); ?></strong></label>
                <div class="file-upload-area" id="file-upload-area">
                    <div class="upload-dropzone">
                        <div class="upload-icon">📤</div>
                        <div class="upload-text">
                            <p class="upload-primary"><?php esc_html_e('Drag & drop your file here', 'bootflow-product-xml-csv-importer'); ?></p>
                            <p class="upload-secondary"><?php esc_html_e('or', 'bootflow-product-xml-csv-importer'); ?></p>
                            <button type="button" class="button button-secondary" id="browse-files">
                                <?php esc_html_e('Browse Files', 'bootflow-product-xml-csv-importer'); ?>
                            </button>
                            <input type="file" id="file_upload" name="file" style="display: none;" />
                        </div>
                        <div class="upload-requirements">
                            <p><?php 
                            // translators: %d is the maximum file size in MB
                            printf(esc_html__('Accepted formats: XML, CSV | Max size: %dMB', 'bootflow-product-xml-csv-importer'), intval($max_file_size)); ?></p>
                        </div>
                    </div>
                    <div class="file-preview" id="file-preview" style="display: none;">
                        <div class="file-info">
                            <span class="file-name"></span>
                            <span class="file-size"></span>
                            <button type="button" class="remove-file" id="remove-file">❌</button>
                        </div>
                        <div class="upload-progress" id="upload-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <span class="progress-text">0%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Type Selection -->
            <div class="form-group">
                <label for="force_file_type"><strong><?php esc_html_e('File Type', 'bootflow-product-xml-csv-importer'); ?></strong></label>
                <select id="force_file_type" name="force_file_type" class="regular-text">
                    <option value="auto"><?php esc_html_e('Auto-detect (from extension or content)', 'bootflow-product-xml-csv-importer'); ?></option>
                    <option value="xml"><?php esc_html_e('Force XML', 'bootflow-product-xml-csv-importer'); ?></option>
                    <option value="csv"><?php esc_html_e('Force CSV', 'bootflow-product-xml-csv-importer'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Select file type manually for URLs without file extension (e.g., API feeds).', 'bootflow-product-xml-csv-importer'); ?></p>
            </div>

            <!-- XML Product Wrapper -->
            <div id="xml-wrapper-section" class="form-group">
                <label for="product_wrapper">
                    <strong><?php esc_html_e('XML Product Element', 'bootflow-product-xml-csv-importer'); ?></strong>
                </label>
                <input type="text" id="product_wrapper" name="product_wrapper" class="regular-text" 
                       value="" placeholder="product" />
                <p class="description"><?php esc_html_e('The XML element name that contains individual product data (e.g., "product", "item", "goods"). Leave as "product" for CSV files.', 'bootflow-product-xml-csv-importer'); ?></p>
            </div>


            <!-- Advanced Options -->
            <div class="form-group">
                <h3>
                    <?php esc_html_e('Advanced Options', 'bootflow-product-xml-csv-importer'); ?>
                </h3>
                <div id="advanced-options" class="advanced-options">
                    <div class="advanced-grid">

                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="skip_unchanged" value="1" />
                                <?php esc_html_e('Skip products if data unchanged', 'bootflow-product-xml-csv-importer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Skip updating products if mapped data hasn\'t changed.', 'bootflow-product-xml-csv-importer'); ?></p>
                        </div>
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="create_categories" value="1" checked />
                                <?php esc_html_e('Auto-create Categories', 'bootflow-product-xml-csv-importer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Automatically create product categories if they don\'t exist.', 'bootflow-product-xml-csv-importer'); ?></p>
                        </div>
                        <div class="advanced-item">
                            <label for="default_status"><?php esc_html_e('Default Product Status', 'bootflow-product-xml-csv-importer'); ?></label>
                            <select name="default_status" id="default_status">
                                <option value="publish"><?php esc_html_e('Published', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="draft"><?php esc_html_e('Draft', 'bootflow-product-xml-csv-importer'); ?></option>
                                <option value="private"><?php esc_html_e('Private', 'bootflow-product-xml-csv-importer'); ?></option>
                            </select>
                        </div>
                        <div class="advanced-item">
                            <label for="batch_size"><?php esc_html_e('Batch Size', 'bootflow-product-xml-csv-importer'); ?></label>
                            <input type="number" name="batch_size" id="batch_size" value="50" min="1" max="500" />
                            <p class="description"><?php esc_html_e('Number of products to process at once.', 'bootflow-product-xml-csv-importer'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Missing Products Handling -->
                    <div class="missing-products-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h4 style="margin-top: 0;"><?php esc_html_e('Handle Products No Longer in Feed', 'bootflow-product-xml-csv-importer'); ?></h4>
                        <p class="description" style="margin-bottom: 15px;">
                            <?php esc_html_e('What to do with products that were imported before but are no longer present in the XML/CSV file.', 'bootflow-product-xml-csv-importer'); ?>
                        </p>
                        
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="handle_missing" id="handle_missing" value="1" />
                                <?php esc_html_e('Process products that are no longer in feed', 'bootflow-product-xml-csv-importer'); ?>
                            </label>
                        </div>
                        
                        <div id="missing-products-options" style="margin-left: 25px; margin-top: 10px; display: none;">
                            <div class="advanced-item">
                                <label for="missing_action"><?php esc_html_e('Action for missing products:', 'bootflow-product-xml-csv-importer'); ?></label>
                                <select name="missing_action" id="missing_action" class="regular-text">
                                    <option value="draft"><?php esc_html_e('Move to Draft (Recommended)', 'bootflow-product-xml-csv-importer'); ?></option>
                                    <option value="outofstock"><?php esc_html_e('Mark as Out of Stock', 'bootflow-product-xml-csv-importer'); ?></option>
                                    <option value="backorder"><?php esc_html_e('Allow Backorder (stock=0)', 'bootflow-product-xml-csv-importer'); ?></option>
                                    <option value="trash"><?php esc_html_e('Move to Trash (auto-delete after 30 days)', 'bootflow-product-xml-csv-importer'); ?></option>
                                    <option value="delete"><?php esc_html_e('Permanently Delete (⚠️ DANGEROUS)', 'bootflow-product-xml-csv-importer'); ?></option>
                                </select>
                            </div>
                            
                            <div class="advanced-item" style="margin-top: 10px;">
                                <label>
                                    <input type="checkbox" name="delete_variations" id="delete_variations" value="1" checked />
                                    <?php esc_html_e('Also process variations when parent product is missing', 'bootflow-product-xml-csv-importer'); ?>
                                </label>
                            </div>
                            
                            <p class="description" style="margin-top: 10px; color: #666;">
                                <span style="color: #0073aa;">ℹ️</span> 
                                <?php esc_html_e('Action will only affect products that were last updated by THIS import. Products updated by other imports will not be affected.', 'bootflow-product-xml-csv-importer'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="button button-primary button-large" id="proceed-mapping">
                    <?php esc_html_e('Proceed to Field Mapping', 'bootflow-product-xml-csv-importer'); ?>
                    <span class="button-icon">➡️</span>
                </button>
            </div>

            <!-- Messages -->
            <div id="upload-messages" class="upload-messages"></div>
        </form>
    </div>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    const uploadForm = $('#bfpi-upload-form');
    const fileInput = $('#file_upload');
    const fileUploadArea = $('#file-upload-area');
    const filePreview = $('#file-preview');
    const uploadProgress = $('#upload-progress');
    const messagesDiv = $('#upload-messages');
    

    
    // File type detection - XML wrapper is now always visible
    fileInput.off('change').on('change', function() {
        const file = this.files[0];
        if (file) {
            showFilePreview(file);
        }
    });
    
    // URL input - XML wrapper is now always visible
    // No longer need to show/hide based on URL extension
    
    // Browse files button
    $('#browse-files').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.trigger('click');
    });
    
    // Drag and drop functionality
    fileUploadArea.on({
        dragover: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        },
        dragleave: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        },
        drop: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                fileInput[0].files = files;
                fileInput.trigger('change');
            }
        }
    });
    
    // Remove file
    $('#remove-file').on('click', function() {
        fileInput.val('');
        filePreview.hide();
        $('#xml-wrapper-section').hide();
    });
    
    // Toggle advanced options (backup if admin.js fails)
    $('#toggle-advanced').off('click').on('click', function() {
        void 0 && console.log('Step-1 toggle clicked');
        const icon = $(this).find('.dashicons');
        const options = $('#advanced-options');
        
        if (options.is(':visible')) {
            options.slideUp();
            icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
        } else {
            options.slideDown();
            icon.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
        }
    });
    
    // Form submission
    // Prevent duplicate event handlers
    uploadForm.off('submit').on('submit', function(e) {
        e.preventDefault();
        
        // Validation
        if (!$('#import_name').val().trim()) {
            showMessage('Please enter an import name.', 'error');
            return;
        }
        
        const uploadMethod = $('input[name="upload_method"]:checked').val();
        
        if (uploadMethod === 'file' && !fileInput[0].files.length) {
            showMessage('Please select a file to upload.', 'error');
            return;
        }
        

        
        // Submit form via AJAX
        const formData = new FormData(this);
        formData.append('action', 'bfpi_upload_file');
        
        const $submitBtn = $('#proceed-mapping');
        $submitBtn.prop('disabled', true).html('<span class="spinner is-active"></span> Uploading and scanning file...');
        
        $.ajax({
            url: bfpi_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 600000, // 10 minutes for large files
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                // Upload progress (only for file uploads, not URL downloads)
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable && uploadMethod === 'file') {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        $submitBtn.html('<span class="spinner is-active"></span> Uploading: ' + Math.round(percentComplete) + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                void 0 && console.log('=== UPLOAD RESPONSE ===', response);
                if (response.success) {
                    const totalProducts = response.data.total_products || 0;
                    void 0 && console.log('Total products from response:', totalProducts);
                    showMessage(response.data.message + ' Found ' + totalProducts + ' products.', 'success');
                    
                    // Show product count in button
                    $submitBtn.html('<span class="spinner is-active"></span> Processing ' + totalProducts + ' products. Redirecting...');
                    
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                } else {
                    console.error('Upload failed:', response.data);
                    showMessage(response.data.message, 'error');
                    $submitBtn.prop('disabled', false).html('<?php esc_html_e('Proceed to Field Mapping', 'bootflow-product-xml-csv-importer'); ?> <span class="button-icon">➡️</span>');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showMessage('Upload timed out. The file might be too large.', 'error');
                } else {
                    showMessage('Upload failed: ' + error, 'error');
                }
                $submitBtn.prop('disabled', false).html('<?php esc_html_e('Proceed to Field Mapping', 'bootflow-product-xml-csv-importer'); ?> <span class="button-icon">➡️</span>');
            }
        });
    });
    
    function showFilePreview(file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        
        filePreview.find('.file-name').text(fileName);
        filePreview.find('.file-size').text(fileSize);
        filePreview.show();
    }
    
    function showMessage(message, type) {
        const alertClass = type === 'error' ? 'notice-error' : 'notice-success';
        messagesDiv.html('<div class="notice ' + alertClass + '"><p>' + message + '</p></div>');
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                messagesDiv.fadeOut();
            }, 3000);
        }
    }
    
    // Toggle missing products options visibility
    $('#handle_missing').on('change', function() {
        if ($(this).is(':checked')) {
            $('#missing-products-options').slideDown();
        } else {
            $('#missing-products-options').slideUp();
        }
    });
});
<?php
$bfpi_step1_js = ob_get_clean();
wp_add_inline_script('bfpi-import-admin', $bfpi_step1_js, 'after');
?>