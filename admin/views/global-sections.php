<?php
/**
 * Global Sections Admin View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ugentic-wrap">
    <h1>
        <?php esc_html_e('Global Sections', 'tahmir-ai-to-wp-converter'); ?>
    </h1>
    <p class="description">
        <?php esc_html_e('Manage reusable sections that stay in sync across multiple pages. Edit once, update everywhere.', 'tahmir-ai-to-wp-converter'); ?>
    </p>

    <div id="global-sections-container">
        <div class="ugentic-card" style="padding: 40px; text-align: center;">
            <span class="dashicons dashicons-update spin"
                style="font-size: 32px; width: 32px; height: 32px; color: #f97316;"></span>
            <p>
                <?php esc_html_e('Loading global sections...', 'tahmir-ai-to-wp-converter'); ?>
            </p>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="global-section-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content" style="max-width: 800px;">
        <div class="ugentic-modal-header">
            <h2 id="gs-modal-title">
                <?php esc_html_e('Edit Global Section', 'tahmir-ai-to-wp-converter'); ?>
            </h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <form id="gs-edit-form">
            <input type="hidden" name="global_section_id" id="gs-edit-id" value="">
            <div class="form-field">
                <label for="gs-edit-name">
                    <?php esc_html_e('Name', 'tahmir-ai-to-wp-converter'); ?>
                </label>
                <input type="text" id="gs-edit-name" name="name" class="regular-text" style="width: 100%;">
            </div>
            <div class="form-field" style="margin-top: 15px;">
                <label for="gs-edit-html">
                    <?php esc_html_e('Section HTML', 'tahmir-ai-to-wp-converter'); ?>
                </label>
                <textarea id="gs-edit-html" name="section_html" rows="15"
                    style="width: 100%; font-family: monospace; font-size: 13px;"></textarea>
            </div>
            <div class="ugentic-modal-footer" style="margin-top: 15px;">
                <button type="button" class="button close-modal">
                    <?php esc_html_e('Cancel', 'tahmir-ai-to-wp-converter'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save & Sync All Pages', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

