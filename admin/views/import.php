<?php
/**
 * Import Website View
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('ugentic_settings', []);
?>
<div class="wrap ugentic-wrap">
    <?php
    Ugentic_Admin_UI::page_header([
        'title' => esc_html__('Import HTML', 'tahmir-ai-to-wp-converter'),
        'description' => esc_html__('Upload a website ZIP containing HTML, CSS, JavaScript, fonts, and images, then review the detected pages and assets before importing.', 'tahmir-ai-to-wp-converter'),
        'icon' => 'dashicons-upload',
        'actions' => [
            [
                'label' => esc_html__('Import from a URL', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-clone'),
                'class' => 'button',
                'icon' => 'dashicons-admin-links',
            ],
        ],
    ]);
    ?>
    
    <div class="ugentic-import-page">
        <!-- Step 1: Upload -->
        <div id="step-upload" class="ugentic-card import-step active">
            <h2>
                <span class="step-number">1</span>
                <?php esc_html_e('Upload HTML Website ZIP', 'tahmir-ai-to-wp-converter'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Upload a ZIP file containing your HTML, CSS, JS files and images.', 'tahmir-ai-to-wp-converter'); ?>
            </p>
            
            <form id="upload-form" enctype="multipart/form-data">
                <?php wp_nonce_field('ugentic_admin_nonce', 'nonce'); ?>
                
                <div class="upload-zone" id="upload-zone">
                    <div class="upload-zone-content">
                        <span class="dashicons dashicons-upload"></span>
                        <p><?php esc_html_e('Drag and drop your ZIP file here', 'tahmir-ai-to-wp-converter'); ?></p>
                        <p class="or"><?php esc_html_e('or', 'tahmir-ai-to-wp-converter'); ?></p>
                        <label class="button button-primary">
                            <?php esc_html_e('Choose File', 'tahmir-ai-to-wp-converter'); ?>
                            <input type="file" name="website_zip" id="website-zip" accept=".zip" style="display: none;">
                        </label>
                    </div>
                    <div class="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <span class="progress-text"><?php esc_html_e('Uploading...', 'tahmir-ai-to-wp-converter'); ?></span>
                    </div>
                </div>
                
                <div id="upload-error" class="notice notice-error" style="display: none;"></div>
            </form>
        </div>
        
        <!-- Step 2: AI Analysis -->
        <div id="step-analyze" class="ugentic-card import-step" style="display: none;">
            <h2>
                <span class="step-number">2</span>
                <?php esc_html_e('File Analysis', 'tahmir-ai-to-wp-converter'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Tahmir checks your HTML, stylesheets, scripts, and images before import.', 'tahmir-ai-to-wp-converter'); ?>
            </p>
            
            <div class="analysis-progress">
                <div class="analysis-item" id="analyze-html">
                    <span class="dashicons dashicons-update spin"></span>
                    <span><?php esc_html_e('Scanning HTML structure...', 'tahmir-ai-to-wp-converter'); ?></span>
                </div>
                <div class="analysis-item" id="analyze-css">
                    <span class="dashicons dashicons-clock"></span>
                    <span><?php esc_html_e('Analyzing CSS stylesheets...', 'tahmir-ai-to-wp-converter'); ?></span>
                </div>
                <div class="analysis-item" id="analyze-js">
                    <span class="dashicons dashicons-clock"></span>
                    <span><?php esc_html_e('Detecting JavaScript libraries...', 'tahmir-ai-to-wp-converter'); ?></span>
                </div>
                <div class="analysis-item" id="analyze-images">
                    <span class="dashicons dashicons-clock"></span>
                    <span><?php esc_html_e('Cataloging images...', 'tahmir-ai-to-wp-converter'); ?></span>
                </div>
            </div>
            
            <div class="analysis-results" id="analysis-results" style="display: none;">
                <h3><?php esc_html_e('Analysis Results', 'tahmir-ai-to-wp-converter'); ?></h3>
                
                <div class="result-cards">
                    <div class="result-card">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <div class="result-info">
                            <strong id="detected-libs">0</strong>
                            <span><?php esc_html_e('Libraries Detected', 'tahmir-ai-to-wp-converter'); ?></span>
                        </div>
                    </div>
                    <div class="result-card">
                        <span class="dashicons dashicons-warning"></span>
                        <div class="result-info">
                            <strong id="detected-issues">0</strong>
                            <span><?php esc_html_e('Potential Issues', 'tahmir-ai-to-wp-converter'); ?></span>
                        </div>
                    </div>
                    <div class="result-card">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div class="result-info">
                            <strong id="detected-optimizations">0</strong>
                            <span><?php esc_html_e('Optimizations Available', 'tahmir-ai-to-wp-converter'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div id="lib-list" class="lib-list"></div>
                
                <div class="ai-recommendations" id="ai-recommendations" style="display: none;">
                    <h4><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e('AI Recommendations', 'tahmir-ai-to-wp-converter'); ?></h4>
                    <ul id="rec-list"></ul>
                </div>
            </div>
            
            <div class="step-actions" id="analyze-actions" style="display: none;">
                <button type="button" class="button" id="back-to-upload-from-analyze">
                    <?php esc_html_e('Back', 'tahmir-ai-to-wp-converter'); ?>
                </button>
                <button type="button" class="button button-primary" id="continue-to-review">
                    <?php esc_html_e('Continue to Configure', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
        
        <!-- Step 3: Review Files -->
        <div id="step-review" class="ugentic-card import-step" style="display: none;">
            <h2>
                <span class="step-number">3</span>
                <?php esc_html_e('Configure Import', 'tahmir-ai-to-wp-converter'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Select which files to import and configure import options.', 'tahmir-ai-to-wp-converter'); ?>
            </p>
            
            <div class="file-review">
                <!-- HTML Files -->
                <div class="file-group">
                    <h3>
                        <span class="dashicons dashicons-media-code"></span>
                        <?php esc_html_e('HTML Pages', 'tahmir-ai-to-wp-converter'); ?>
                        <span class="file-count" id="html-count">0</span>
                    </h3>
                    <div id="html-files" class="file-list"></div>
                </div>
                
                <!-- CSS Files -->
                <div class="file-group">
                    <h3>
                        <span class="dashicons dashicons-admin-customizer"></span>
                        <?php esc_html_e('CSS Files', 'tahmir-ai-to-wp-converter'); ?>
                        <span class="file-count" id="css-count">0</span>
                    </h3>
                    <div id="css-files" class="file-list"></div>
                </div>
                
                <!-- JS Files -->
                <div class="file-group">
                    <h3>
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e('JavaScript Files', 'tahmir-ai-to-wp-converter'); ?>
                        <span class="file-count" id="js-count">0</span>
                    </h3>
                    <div id="js-files" class="file-list"></div>
                </div>
                
                <!-- Images -->
                <div class="file-group">
                    <h3>
                        <span class="dashicons dashicons-format-image"></span>
                        <?php esc_html_e('Images', 'tahmir-ai-to-wp-converter'); ?>
                        <span class="file-count" id="image-count">0</span>
                    </h3>
                    <div id="image-files" class="file-list file-grid"></div>
                </div>
            </div>
            
            <div class="import-options">
                <h3><?php esc_html_e('Import Options', 'tahmir-ai-to-wp-converter'); ?></h3>
                
                <div class="option-group">
                    <label for="import-mode"><?php esc_html_e('Import Mode', 'tahmir-ai-to-wp-converter'); ?></label>
                    <select id="import-mode">
                        <option value="faithful" selected><?php esc_html_e('Faithful Canvas (recommended)', 'tahmir-ai-to-wp-converter'); ?></option>
                        <option value="advanced"><?php esc_html_e('Advanced (WP integration)', 'tahmir-ai-to-wp-converter'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Faithful Canvas: Exact visual match — only asset paths are replaced.', 'tahmir-ai-to-wp-converter'); ?><br>
                        <?php esc_html_e('Advanced: Extracts CSS/JS as assets, detects header/footer, converts forms.', 'tahmir-ai-to-wp-converter'); ?>
                    </p>
                </div>
                
                <div id="advanced-import-options" style="display: none;">
                
                <div class="option-group">
                    <label for="import-css-handling"><?php esc_html_e('CSS Handling', 'tahmir-ai-to-wp-converter'); ?></label>
                    <select id="import-css-handling">
                        <option value="global"><?php esc_html_e('Add to Global Assets', 'tahmir-ai-to-wp-converter'); ?></option>
                        <option value="inline"><?php esc_html_e('Keep Inline in Pages', 'tahmir-ai-to-wp-converter'); ?></option>
                    </select>
                </div>
                
                <div class="option-group">
                    <label for="import-js-handling"><?php esc_html_e('JavaScript Handling', 'tahmir-ai-to-wp-converter'); ?></label>
                    <select id="import-js-handling">
                        <option value="global"><?php esc_html_e('Add to Global Assets', 'tahmir-ai-to-wp-converter'); ?></option>
                        <option value="inline"><?php esc_html_e('Keep Inline in Pages', 'tahmir-ai-to-wp-converter'); ?></option>
                    </select>
                </div>
                
                <div class="option-group">
                    <label for="import-image-handling"><?php esc_html_e('Image Handling', 'tahmir-ai-to-wp-converter'); ?></label>
                    <select id="import-image-handling">
                        <option value="media_library" <?php selected($settings['image_handling'] ?? '', 'media_library'); ?>>
                            <?php esc_html_e('Upload to Media Library', 'tahmir-ai-to-wp-converter'); ?>
                        </option>
                        <option value="plugin_folder" <?php selected($settings['image_handling'] ?? '', 'plugin_folder'); ?>>
                            <?php esc_html_e('Store in Plugin Folder', 'tahmir-ai-to-wp-converter'); ?>
                        </option>
                    </select>
                </div>
                
                <div class="option-group">
                    <label>
                        <input type="checkbox" id="import-detect-header-footer" value="1" checked>
                        <?php esc_html_e('Auto-detect header/footer', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                    <p class="description" style="margin: 4px 0 0 24px;">
                        <?php esc_html_e('Detects header and footer sections in each page. Links within the header will be updated automatically.', 'tahmir-ai-to-wp-converter'); ?>
                    </p>
                </div>
                
                <div class="option-group" id="global-header-option" style="margin-left: 24px;">
                    <label>
                        <input type="checkbox" id="import-make-header-global" value="1">
                        <?php esc_html_e('Make header/footer global', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                    <p class="description" style="margin: 4px 0 0 24px;">
                        <?php esc_html_e('Extracts the header/footer from each page and saves it as a shared global template. If unchecked, headers remain inside each page\'s content.', 'tahmir-ai-to-wp-converter'); ?>
                    </p>
                </div>
                
                </div><!-- #advanced-import-options -->
                
                <div class="option-group">
                    <label>
                        <input type="checkbox" id="import-publish" value="1">
                        <?php esc_html_e('Publish pages immediately (otherwise save as draft)', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                </div>
            </div>
            
            <div class="step-actions">
                <button type="button" class="button" id="back-to-upload">
                    <?php esc_html_e('Back', 'tahmir-ai-to-wp-converter'); ?>
                </button>
                <button type="button" class="button button-primary" id="start-import">
                    <?php esc_html_e('Import Selected', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
        
        <!-- Step 4: Import Progress -->
        <div id="step-progress" class="ugentic-card import-step" style="display: none;">
            <h2>
                <span class="step-number">4</span>
                <?php esc_html_e('Importing...', 'tahmir-ai-to-wp-converter'); ?>
            </h2>
            
            <div class="import-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="import-progress-fill"></div>
                </div>
                <div class="progress-status" id="import-status"></div>
                <div class="import-progress-stages" id="import-progress-stages" aria-label="<?php esc_attr_e('Import progress stages', 'tahmir-ai-to-wp-converter'); ?>">
                    <span class="is-active" data-stage="prepare"><?php esc_html_e('Prepare', 'tahmir-ai-to-wp-converter'); ?></span>
                    <span data-stage="pages"><?php esc_html_e('Pages', 'tahmir-ai-to-wp-converter'); ?></span>
                    <span data-stage="links"><?php esc_html_e('Links', 'tahmir-ai-to-wp-converter'); ?></span>
                    <span data-stage="menus"><?php esc_html_e('Menus', 'tahmir-ai-to-wp-converter'); ?></span>
                    <span data-stage="complete"><?php esc_html_e('Complete', 'tahmir-ai-to-wp-converter'); ?></span>
                </div>
            </div>
            
            <div id="import-log" class="import-log"></div>
        </div>
        
        <!-- Step 5: Complete -->
        <div id="step-complete" class="ugentic-card import-step" style="display: none;">
            <h2>
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php esc_html_e('Import Complete!', 'tahmir-ai-to-wp-converter'); ?>
            </h2>
            
            <div id="import-summary" class="import-summary"></div>
            
            <!-- Dynamic Content Suggestions -->
            <div id="dynamic-suggestions" class="dynamic-suggestions" style="display: none;">
                <div class="dynamic-suggestions-header">
                    <span class="dashicons dashicons-lightbulb" style="color: #f0b849;"></span>
                    <h3><?php esc_html_e('Dynamic Content Detected', 'tahmir-ai-to-wp-converter'); ?></h3>
                    <p class="description"><?php esc_html_e('We detected pages that contain repeating content (blog posts, portfolio items, etc.). Convert them to dynamic WordPress content for easy management.', 'tahmir-ai-to-wp-converter'); ?></p>
                </div>
                <div id="dynamic-suggestion-cards" class="dynamic-suggestion-cards"></div>
            </div>
            
            <div class="step-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-pages')); ?>" class="button button-primary">
                    <?php esc_html_e('View Imported Pages', 'tahmir-ai-to-wp-converter'); ?>
                </a>
                <button type="button" class="button" id="import-another">
                    <?php esc_html_e('Import Another Website', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
