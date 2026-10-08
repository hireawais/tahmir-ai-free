<?php
/**
 * Assets Manager View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
/* Fix textarea visibility - ensure text is visible before focus */
#header-content,
#footer-content,
.code-editor-wrapper textarea {
    color: #1d2327 !important;
    background-color: #fff !important;
    font-family: Consolas, Monaco, 'Andale Mono', monospace;
    font-size: 13px;
    line-height: 1.5;
    width: 100%;
    min-height: 300px;
}
</style>
<div class="wrap ugentic-wrap">
    <h1><?php esc_html_e('Global Assets', 'tahmir-ai-to-wp-converter'); ?></h1>
    
    <!-- AI Scan Feature -->
    <div class="ugentic-card" style="margin-bottom: 20px; padding: 20px; background: linear-gradient(135deg, #f97316 0%, #c2410c 100%);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div style="color: white;">
                <h3 style="margin: 0 0 5px 0; color: white;">
                    <span class="dashicons dashicons-superhero" style="margin-right: 8px;"></span>
                    <?php esc_html_e('AI Header/Footer Scanner', 'tahmir-ai-to-wp-converter'); ?>
                </h3>
                <p style="margin: 0; opacity: 0.9; font-size: 13px;">
                    <?php esc_html_e('Scan all imported pages and automatically detect common header/footer elements.', 'tahmir-ai-to-wp-converter'); ?>
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <label style="color: white; display: flex; align-items: center; gap: 5px; font-size: 13px;">
                    <input type="checkbox" id="strip-from-pages" checked style="margin: 0;">
                    <?php esc_html_e('Remove from pages after scan', 'tahmir-ai-to-wp-converter'); ?>
                </label>
                <button type="button" class="button button-large" id="ai-scan-header-footer" style="background: white; border: none; color: #f97316; font-weight: 600;">
                    <span class="dashicons dashicons-search" style="margin-top: 4px;"></span>
                    <?php esc_html_e('Scan All Pages', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2);">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <p style="color: white; margin: 0; opacity: 0.9; font-size: 13px;">
                    <span class="dashicons dashicons-warning" style="margin-right: 5px;"></span>
                    <?php esc_html_e('If pages contain embedded headers/footers, strip them so only the global ones are used:', 'tahmir-ai-to-wp-converter'); ?>
                </p>
                <button type="button" class="button" id="strip-headers-footers" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;">
                    <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
                    <?php esc_html_e('Strip Headers/Footers from All Pages', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
        <div id="ai-scan-status" style="color: white; margin-top: 10px; display: none;"></div>
    </div>
    
    <div class="ugentic-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#tab-css" class="nav-tab nav-tab-active"><?php esc_html_e('CSS Files', 'tahmir-ai-to-wp-converter'); ?></a>
            <a href="#tab-js" class="nav-tab"><?php esc_html_e('JS Files', 'tahmir-ai-to-wp-converter'); ?></a>
            <a href="#tab-header" class="nav-tab"><?php esc_html_e('Global Header', 'tahmir-ai-to-wp-converter'); ?></a>
            <a href="#tab-footer" class="nav-tab"><?php esc_html_e('Global Footer', 'tahmir-ai-to-wp-converter'); ?></a>
        </nav>
        
        <!-- CSS Tab -->
        <div id="tab-css" class="ugentic-tab-content active">
            <div class="ugentic-card">
                <div class="card-header">
                    <h2><?php esc_html_e('CSS Files', 'tahmir-ai-to-wp-converter'); ?></h2>
                    <div class="card-header-actions">
                        <button type="button" class="button bulk-delete-btn" data-type="css" style="display:none;">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Delete Selected', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="button button-primary add-asset-btn" data-type="css">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('Add CSS File', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" class="select-all-css"></th>
                            <th class="column-name"><?php esc_html_e('Name', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th class="column-version"><?php esc_html_e('Version', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th class="column-status"><?php esc_html_e('Status', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th class="column-actions"><?php esc_html_e('Actions', 'tahmir-ai-to-wp-converter'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($css_assets)): ?>
                            <tr>
                                <td colspan="5" class="no-items"><?php esc_html_e('No CSS files yet.', 'tahmir-ai-to-wp-converter'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($css_assets as $asset): ?>
                                <tr data-id="<?php echo esc_attr($asset->id); ?>">
                                    <th class="check-column"><input type="checkbox" class="asset-checkbox" value="<?php echo esc_attr($asset->id); ?>"></th>
                                    <td class="column-name">
                                        <strong><?php echo esc_html($asset->name); ?></strong>
                                    </td>
                                    <td class="column-version">
                                        <code>v<?php echo esc_html($asset->version); ?></code>
                                    </td>
                                    <td class="column-status">
                                        <span class="status-badge <?php echo $asset->is_active ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $asset->is_active ? esc_html__('Active', 'tahmir-ai-to-wp-converter') : esc_html__('Inactive', 'tahmir-ai-to-wp-converter'); ?>
                                        </span>
                                    </td>
                                    <td class="column-actions">
                                        <button type="button" class="button edit-asset-btn" data-id="<?php echo esc_attr($asset->id); ?>" data-type="css">
                                            <?php esc_html_e('Edit', 'tahmir-ai-to-wp-converter'); ?>
                                        </button>
                                        <button type="button" class="button delete-asset-btn" data-id="<?php echo esc_attr($asset->id); ?>">
                                            <?php esc_html_e('Delete', 'tahmir-ai-to-wp-converter'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- JS Tab -->
        <div id="tab-js" class="ugentic-tab-content">
            <div class="ugentic-card">
                <div class="card-header">
                    <h2><?php esc_html_e('JS Files', 'tahmir-ai-to-wp-converter'); ?></h2>
                    <div class="card-header-actions">
                        <button type="button" class="button bulk-delete-btn" data-type="js" style="display:none;">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Delete Selected', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="button button-primary add-asset-btn" data-type="js">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('Add JS File', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" class="select-all-js"></th>
                            <th class="column-name"><?php esc_html_e('Name', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th class="column-version"><?php esc_html_e('Version', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th class="column-status"><?php esc_html_e('Status', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th class="column-actions"><?php esc_html_e('Actions', 'tahmir-ai-to-wp-converter'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($js_assets)): ?>
                            <tr>
                                <td colspan="5" class="no-items"><?php esc_html_e('No JS files yet.', 'tahmir-ai-to-wp-converter'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($js_assets as $asset): ?>
                                <tr data-id="<?php echo esc_attr($asset->id); ?>">
                                    <th class="check-column"><input type="checkbox" class="asset-checkbox" value="<?php echo esc_attr($asset->id); ?>"></th>
                                    <td class="column-name">
                                        <strong><?php echo esc_html($asset->name); ?></strong>
                                    </td>
                                    <td class="column-version">
                                        <code>v<?php echo esc_html($asset->version); ?></code>
                                    </td>
                                    <td class="column-status">
                                        <span class="status-badge <?php echo $asset->is_active ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $asset->is_active ? esc_html__('Active', 'tahmir-ai-to-wp-converter') : esc_html__('Inactive', 'tahmir-ai-to-wp-converter'); ?>
                                        </span>
                                    </td>
                                    <td class="column-actions">
                                        <button type="button" class="button edit-asset-btn" data-id="<?php echo esc_attr($asset->id); ?>" data-type="js">
                                            <?php esc_html_e('Edit', 'tahmir-ai-to-wp-converter'); ?>
                                        </button>
                                        <button type="button" class="button delete-asset-btn" data-id="<?php echo esc_attr($asset->id); ?>">
                                            <?php esc_html_e('Delete', 'tahmir-ai-to-wp-converter'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Header Tab -->
        <div id="tab-header" class="ugentic-tab-content">
            <div class="ugentic-card">
                <h2><?php esc_html_e('Global Header', 'tahmir-ai-to-wp-converter'); ?></h2>
                <p class="description"><?php esc_html_e('This HTML will be added at the beginning of all Tahmir AI pages.', 'tahmir-ai-to-wp-converter'); ?></p>
                
                <div class="auto-link-actions" style="margin: 15px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
                        <button type="button" class="button button-secondary" id="auto-generate-links">
                            <span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span>
                            <?php esc_html_e('Auto Generate Links', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="visual-edit-header">
                            <span class="dashicons dashicons-edit" style="margin-top: 3px;"></span>
                            <?php esc_html_e('Visual Editor', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                    </div>
                    <span class="auto-link-status" style="margin-left: 10px; font-style: italic;"></span>
                    <p class="description" style="margin-top: 8px; margin-bottom: 0;">
                        <?php esc_html_e('Auto Generate: Creates menu items for all your pages. Visual Editor: Click on elements to edit them directly.', 'tahmir-ai-to-wp-converter'); ?>
                    </p>
                </div>
                
                <!-- Visual Editor Panel -->
                <div id="header-visual-editor" style="display: none; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: linear-gradient(135deg, #f97316, #c2410c); border-radius: 8px 8px 0 0;">
                        <span style="color: white; font-weight: 600;">
                            <span class="dashicons dashicons-edit" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Visual Edit Mode', 'tahmir-ai-to-wp-converter'); ?>
                        </span>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="button" id="header-add-menu-item" style="background: white; border: none;">
                                <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                <?php esc_html_e('Add Menu Item', 'tahmir-ai-to-wp-converter'); ?>
                            </button>
                            <button type="button" class="button" id="header-save-visual" style="background: white; border: none; color: #46b450;">
                                <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
                                <?php esc_html_e('Save Changes', 'tahmir-ai-to-wp-converter'); ?>
                            </button>
                            <button type="button" class="button" id="header-close-visual" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.5); color: white;">
                                <?php esc_html_e('Close', 'tahmir-ai-to-wp-converter'); ?>
                            </button>
                        </div>
                    </div>
                    <div id="header-preview-container" style="border: 1px solid #ddd; border-top: none; min-height: 150px; background: white; position: relative;">
                        <div id="header-preview" contenteditable="false" style="padding: 0; min-height: 80px;"></div>
                        <div id="header-edit-tooltip" style="display: none; position: absolute; background: #1e1e2e; color: white; padding: 8px 12px; border-radius: 6px; font-size: 12px; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                            <input type="text" id="header-edit-text" style="background: transparent; border: none; color: white; width: 200px; outline: none;">
                            <button type="button" id="header-edit-apply" style="background: #f97316; color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; margin-left: 8px;">✓</button>
                        </div>
                    </div>
                    <p class="description" style="margin-top: 8px; color: #666;">
                        <?php esc_html_e('Click on any text or link to edit. Changes are saved when you click "Save Changes".', 'tahmir-ai-to-wp-converter'); ?>
                    </p>
                </div>
                
                <form id="header-form" class="asset-form">
                    <input type="hidden" name="id" value="<?php echo $header ? esc_attr($header->id) : ''; ?>">
                    <input type="hidden" name="type" value="header">
                    <input type="hidden" name="name" value="Global Header">
                    <input type="hidden" name="is_active" value="1">
                    
                    <div class="code-editor-wrapper" id="header-code-wrapper">
                        <div style="display: flex; justify-content: flex-end; margin-bottom: 5px;">
                            <button type="button" class="button button-small" id="switch-to-visual-header">
                                <span class="dashicons dashicons-visibility" style="margin-top: 4px; font-size: 14px;"></span>
                                <?php esc_html_e('Switch to Visual', 'tahmir-ai-to-wp-converter'); ?>
                            </button>
                        </div>
                        <textarea id="header-content" name="content" rows="15"><?php echo $header ? esc_textarea($header->content) : ''; ?></textarea>
                    </div>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Header', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <span class="save-status"></span>
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Footer Tab -->
        <div id="tab-footer" class="ugentic-tab-content">
            <div class="ugentic-card">
                <h2><?php esc_html_e('Global Footer', 'tahmir-ai-to-wp-converter'); ?></h2>
                <p class="description"><?php esc_html_e('This HTML will be added at the end of all Tahmir AI pages.', 'tahmir-ai-to-wp-converter'); ?></p>
                
                <form id="footer-form" class="asset-form">
                    <input type="hidden" name="id" value="<?php echo $footer ? esc_attr($footer->id) : ''; ?>">
                    <input type="hidden" name="type" value="footer">
                    <input type="hidden" name="name" value="Global Footer">
                    <input type="hidden" name="is_active" value="1">
                    
                    <div class="code-editor-wrapper">
                        <textarea id="footer-content" name="content" rows="15"><?php echo $footer ? esc_textarea($footer->content) : ''; ?></textarea>
                    </div>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Footer', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <span class="save-status"></span>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Asset Edit Modal -->
<div id="asset-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content">
        <div class="ugentic-modal-header">
            <h2 id="modal-title"><?php esc_html_e('Edit Asset', 'tahmir-ai-to-wp-converter'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <form id="asset-form" class="asset-form">
            <input type="hidden" name="id" id="asset-id" value="">
            <input type="hidden" name="type" id="asset-type" value="">
            
            <div class="form-field">
                <label for="asset-name"><?php esc_html_e('Name', 'tahmir-ai-to-wp-converter'); ?></label>
                <input type="text" id="asset-name" name="name" class="regular-text" required>
            </div>
            
            <div class="form-field">
                <label for="asset-content"><?php esc_html_e('Content', 'tahmir-ai-to-wp-converter'); ?></label>
                <div class="code-editor-wrapper">
                    <textarea id="asset-content" name="content" rows="20"></textarea>
                </div>
            </div>
            
            <div class="form-field">
                <label>
                    <input type="checkbox" id="asset-active" name="is_active" value="1" checked>
                    <?php esc_html_e('Active', 'tahmir-ai-to-wp-converter'); ?>
                </label>
            </div>
            
            <div class="ugentic-modal-footer">
                <button type="button" class="button close-modal"><?php esc_html_e('Cancel', 'tahmir-ai-to-wp-converter'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save', 'tahmir-ai-to-wp-converter'); ?></button>
            </div>
        </form>
    </div>
</div>
