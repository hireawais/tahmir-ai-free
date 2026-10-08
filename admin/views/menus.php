<?php
/**
 * Menu Manager View
 * Visual drag-and-drop menu editor
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ugentic-wrap">
    <?php
    Ugentic_Admin_UI::page_header([
        'title' => esc_html__('Menu Manager', 'tahmir-ai-to-wp-converter'),
        'description' => esc_html__('Build and reorder header or footer navigation, link existing pages, and preview changes before saving them sitewide.', 'tahmir-ai-to-wp-converter'),
        'icon' => 'dashicons-menu-alt3',
    ]);
    ?>
    
    <div class="ugentic-menu-manager" style="display: flex; gap: 24px; margin-top: 20px; flex-wrap: wrap;">
        
        <!-- Menu Editor Panel -->
        <div class="ugentic-card" style="flex: 1; min-width: 400px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <h2 style="margin: 0;">
                    <span class="dashicons dashicons-menu" style="margin-right: 8px; color: #075cff;"></span>
                    <?php esc_html_e('Menu Items', 'tahmir-ai-to-wp-converter'); ?>
                </h2>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <label for="menu-location-select" style="font-weight: 600; font-size: 13px;"><?php esc_html_e('Location:', 'tahmir-ai-to-wp-converter'); ?></label>
                    <select id="menu-location-select" style="min-width: 160px; padding: 4px 8px; border-radius: 4px; border: 1px solid #8c8f94;">
                        <option value="header"><?php esc_html_e('Header Menu', 'tahmir-ai-to-wp-converter'); ?></option>
                        <option value="footer"><?php esc_html_e('Footer Menu', 'tahmir-ai-to-wp-converter'); ?></option>
                    </select>
                    <button type="button" class="button" id="menu-auto-link-pages">
                        <span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Auto-link Pages', 'tahmir-ai-to-wp-converter'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="menu-add-item">
                        <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Add Item', 'tahmir-ai-to-wp-converter'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Loading state -->
            <div id="menu-loading" style="text-align: center; padding: 40px;">
                <span class="spinner is-active" style="float: none;"></span>
                <p><?php esc_html_e('Loading menu items...', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            
            <!-- Empty state -->
            <div id="menu-empty" style="display: none; text-align: center; padding: 40px; background: #f9f9f9; border-radius: 8px;">
                <span class="dashicons dashicons-menu" style="font-size: 48px; width: 48px; height: 48px; color: #ccc;"></span>
                <p style="color: #666; margin-top: 10px;"><?php esc_html_e('No menu items found in the global header.', 'tahmir-ai-to-wp-converter'); ?></p>
                <p style="color: #999; font-size: 13px;"><?php esc_html_e('Add items manually or import a website with a navigation menu.', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            
            <!-- Menu items list (sortable) -->
            <ul id="menu-items-list" class="ugentic-menu-list" style="display: none; list-style: none; padding: 0; margin: 0;">
                <!-- Items inserted via JS -->
            </ul>
            
            <!-- Save bar -->
            <div id="menu-save-bar" style="display: none; margin-top: 20px; padding: 15px; background: #f0f0f1; border-radius: 6px; display: none; align-items: center; justify-content: space-between;">
                <span id="menu-save-status" style="font-style: italic; color: #666;"></span>
                <button type="button" class="button button-primary button-large" id="menu-save-btn">
                    <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                    <?php esc_html_e('Save Menu', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
        
        <!-- Header Preview Panel -->
        <div class="ugentic-card" style="flex: 1; min-width: 400px;">
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-visibility" style="margin-right: 8px; color: #075cff;"></span>
                <span id="menu-preview-title"><?php esc_html_e('Header Preview', 'tahmir-ai-to-wp-converter'); ?></span>
            </h2>
            <p class="description"><?php esc_html_e('Live preview of your header with the current menu.', 'tahmir-ai-to-wp-converter'); ?></p>
            
            <div id="menu-header-preview" style="border: 1px solid #ddd; border-radius: 6px; overflow: hidden; background: #fff; min-height: 80px; margin-top: 15px;">
                <div id="menu-preview-content" style="padding: 0;"></div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Menu Item Modal -->
    <div id="menu-item-modal" class="ugentic-modal" style="display: none;">
        <div class="ugentic-modal-content" style="max-width: 500px;">
            <div class="ugentic-modal-header">
                <h2 id="menu-modal-title"><?php esc_html_e('Add Menu Item', 'tahmir-ai-to-wp-converter'); ?></h2>
                <button type="button" class="close-modal">&times;</button>
            </div>
            <form id="menu-item-form">
                <div class="form-field" style="margin-bottom: 15px;">
                    <label for="menu-item-text" style="display: block; font-weight: 600; margin-bottom: 5px;">
                        <?php esc_html_e('Label', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                    <input type="text" id="menu-item-text" class="regular-text" style="width: 100%;" placeholder="<?php esc_attr_e('e.g. About Us', 'tahmir-ai-to-wp-converter'); ?>" required>
                </div>
                
                <div class="form-field" style="margin-bottom: 15px;">
                    <label for="menu-item-url" style="display: block; font-weight: 600; margin-bottom: 5px;">
                        <?php esc_html_e('URL', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                    <input type="text" id="menu-item-url" class="regular-text" style="width: 100%;" placeholder="<?php esc_attr_e('https:// or select a page below', 'tahmir-ai-to-wp-converter'); ?>">
                </div>
                
                <div class="form-field" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                        <?php esc_html_e('Or select an existing page:', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                    <select id="menu-item-page-select" style="width: 100%;">
                        <option value=""><?php esc_html_e('— Select a page —', 'tahmir-ai-to-wp-converter'); ?></option>
                        <!-- Populated via JS -->
                    </select>
                </div>
                
                <div class="ugentic-modal-footer">
                    <button type="button" class="button close-modal"><?php esc_html_e('Cancel', 'tahmir-ai-to-wp-converter'); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Add to Menu', 'tahmir-ai-to-wp-converter'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.ugentic-menu-list .menu-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 12px 15px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: box-shadow 0.2s, border-color 0.2s;
    cursor: grab;
}
.ugentic-menu-list .menu-item:hover {
    border-color: #075cff;
    box-shadow: 0 2px 8px rgba(7, 92, 255, 0.15);
}
.ugentic-menu-list .menu-item.ui-sortable-helper {
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    border-color: #075cff;
}
.ugentic-menu-list .menu-item.ui-sortable-placeholder {
    visibility: visible !important;
    background: #f0f3ff;
    border: 2px dashed #075cff;
    height: 48px;
}
.menu-item .drag-handle {
    color: #999;
    cursor: grab;
    font-size: 18px;
}
.menu-item .item-text {
    flex: 1;
    font-weight: 600;
    font-size: 14px;
}
.menu-item .item-url {
    color: #999;
    font-size: 12px;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.menu-item .item-actions {
    display: flex;
    gap: 6px;
}
.menu-item .item-actions button {
    padding: 2px 8px;
    font-size: 12px;
    line-height: 1.6;
}
.menu-item .children-badge {
    background: #075cff;
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
}
.menu-item .edit-inline {
    border: 1px solid #075cff;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 13px;
    outline: none;
}
.menu-children-list {
    list-style: none;
    padding: 0 0 0 30px;
    margin: 8px 0 0 0;
}
.menu-children-list .menu-item {
    background: #f9f9ff;
    border-left: 3px solid #075cff;
}
</style>
