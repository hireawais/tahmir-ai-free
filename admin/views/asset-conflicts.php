<?php
/**
 * Asset Conflicts Detection UI
 * Lists assets per page with warning indicators and one-click fixes
 */

if (!defined('ABSPATH')) {
    exit;
}

$database = ugentic_builder()->database;
$pages = $database->get_all_pages();
?>

<div class="wrap ugentic-wrap ugentic-asset-conflicts">
    <h1>
        <span class="dashicons dashicons-warning"></span>
        <?php esc_html_e('Asset Conflicts & Optimization', 'tahmir-ai-to-wp-converter'); ?>
    </h1>
    
    <p class="description">
        <?php esc_html_e('Detect library conflicts, optimize assets, and resolve issues across your pages.', 'tahmir-ai-to-wp-converter'); ?>
    </p>
    
    <!-- Summary Cards -->
    <div class="conflict-summary">
        <div class="summary-card" id="summary-conflicts">
            <span class="dashicons dashicons-warning"></span>
            <div class="summary-content">
                <span class="summary-number">-</span>
                <span class="summary-label"><?php esc_html_e('Conflicts Detected', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </div>
        <div class="summary-card" id="summary-optimizations">
            <span class="dashicons dashicons-performance"></span>
            <div class="summary-content">
                <span class="summary-number">-</span>
                <span class="summary-label"><?php esc_html_e('Optimization Opportunities', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </div>
        <div class="summary-card" id="summary-pages">
            <span class="dashicons dashicons-admin-page"></span>
            <div class="summary-content">
                <span class="summary-number"><?php echo count($pages); ?></span>
                <span class="summary-label"><?php esc_html_e('Total Pages', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </div>
        <div class="summary-card action-card">
            <button type="button" class="button button-primary" id="scan-all-btn">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e('Scan All Pages', 'tahmir-ai-to-wp-converter'); ?>
            </button>
        </div>
    </div>
    
    <!-- Pages Table -->
    <div class="pages-table-wrap">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-title"><?php esc_html_e('Page', 'tahmir-ai-to-wp-converter'); ?></th>
                    <th class="column-libraries"><?php esc_html_e('Libraries Detected', 'tahmir-ai-to-wp-converter'); ?></th>
                    <th class="column-conflicts"><?php esc_html_e('Conflicts', 'tahmir-ai-to-wp-converter'); ?></th>
                    <th class="column-size"><?php esc_html_e('Asset Size', 'tahmir-ai-to-wp-converter'); ?></th>
                    <th class="column-actions"><?php esc_html_e('Actions', 'tahmir-ai-to-wp-converter'); ?></th>
                </tr>
            </thead>
            <tbody id="pages-tbody">
                <?php foreach ($pages as $page): ?>
                <tr data-page-id="<?php echo esc_attr($page->id); ?>">
                    <td class="column-title">
                        <strong><?php echo esc_html($page->post_title ?: esc_html__('Untitled page', 'tahmir-ai-to-wp-converter')); ?></strong>
                        <div class="row-actions">
                            <a href="<?php echo esc_url(get_permalink($page->wp_page_id)); ?>" target="_blank"><?php esc_html_e('View', 'tahmir-ai-to-wp-converter'); ?></a> |
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-builder&action=edit&id=' . $page->id)); ?>"><?php esc_html_e('Edit', 'tahmir-ai-to-wp-converter'); ?></a>
                        </div>
                    </td>
                    <td class="column-libraries">
                        <span class="libraries-loading"><?php esc_html_e('Click scan...', 'tahmir-ai-to-wp-converter'); ?></span>
                        <div class="libraries-list"></div>
                    </td>
                    <td class="column-conflicts">
                        <span class="conflicts-loading">-</span>
                        <div class="conflicts-list"></div>
                    </td>
                    <td class="column-size">
                        <span class="size-loading">-</span>
                    </td>
                    <td class="column-actions">
                        <button type="button" class="button scan-page-btn" data-page-id="<?php echo esc_attr($page->id); ?>">
                            <?php esc_html_e('Scan', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="button optimize-page-btn" data-page-id="<?php echo esc_attr($page->id); ?>" style="display:none;">
                            <?php esc_html_e('Optimize', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Conflict Details Modal -->
    <div id="conflict-modal" class="conflict-modal" style="display:none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php esc_html_e('Conflict Details', 'tahmir-ai-to-wp-converter'); ?></h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="conflict-modal-body">
                <!-- Populated by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="button modal-close-btn"><?php esc_html_e('Close', 'tahmir-ai-to-wp-converter'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.ugentic-asset-conflicts h1 {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ugentic-asset-conflicts h1 .dashicons {
    color: #f59e0b;
}

.conflict-summary {
    display: flex;
    gap: 20px;
    margin: 24px 0;
}

.summary-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 200px;
}

.summary-card .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #6b7280;
}

#summary-conflicts .dashicons { color: #ef4444; }
#summary-optimizations .dashicons { color: #f59e0b; }
#summary-pages .dashicons { color: #f97316; }

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-number {
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
}

.summary-label {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.action-card {
    margin-left: auto;
}

.pages-table-wrap {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.pages-table-wrap table {
    border: none;
}

.pages-table-wrap th {
    background: #f9fafb;
    font-weight: 600;
}

.column-libraries { width: 25%; }
.column-conflicts { width: 20%; }
.column-size { width: 12%; }
.column-actions { width: 15%; }

.libraries-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.library-badge {
    background: #e0e7ff;
    color: #4338ca;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.library-badge.has-wp { background: #d1fae5; color: #065f46; }
.library-badge.duplicate { background: #fef3c7; color: #92400e; }

.conflict-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    margin: 2px;
}

.conflict-badge.severity-high {
    background: #fee2e2;
    color: #991b1b;
}

.conflict-badge.severity-medium {
    background: #fef3c7;
    color: #92400e;
}

.conflict-badge.severity-low {
    background: #e0e7ff;
    color: #4338ca;
}

.conflict-badge .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* Modal */
.conflict-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
}

.modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
    margin: 0;
    font-size: 18px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 16px 20px;
    border-top: 1px solid #e5e7eb;
    text-align: right;
}

.conflict-item {
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 12px;
}

.conflict-item h4 {
    margin: 0 0 8px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.conflict-item p {
    margin: 0 0 12px;
    color: #6b7280;
    font-size: 13px;
}

.fix-btn {
    background: #f97316 !important;
    border-color: #f97316 !important;
    color: white !important;
}

.fix-btn:hover {
    background: #5a67d8 !important;
}

/* Loading state */
.scan-page-btn.loading {
    pointer-events: none;
    opacity: 0.6;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>


