<?php
/**
 * Enhanced Dashboard View
 * Quick stats, quick actions, recent pages, AI usage
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get recent pages
$database = ugentic_builder()->database;
$recent_pages = $database->get_all_pages();
$recent_pages = array_slice($recent_pages, 0, 5);

// Forms and entry statistics are available only in Pro.
$forms_count = 0;

// Get AI usage (from settings or transient)
$ai_calls_today = get_transient('ugentic_ai_calls_today') ?: 0;
?>

<div class="wrap ugentic-wrap ugentic-dashboard-v2">
    <div class="dashboard-header">
        <div class="header-left">
            <h1>
                <span class="dashicons dashicons-layout"></span>
                <?php esc_html_e('Tahmir AI', 'tahmir-ai-to-wp-converter'); ?>
            </h1>
            <span class="version-badge">v<?php echo esc_html(UGENTIC_VERSION); ?></span>
        </div>
        <div class="header-right">
            <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-clone')); ?>" class="button button-primary">
                <span class="dashicons dashicons-admin-site-alt2"></span>
                <?php esc_html_e('Import from URL', 'tahmir-ai-to-wp-converter'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-import')); ?>" class="button button-hero">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e('Import HTML', 'tahmir-ai-to-wp-converter'); ?>
            </a>
        </div>
    </div>
    
    <div class="ugentic-card">
        <h2><?php esc_html_e('Free HTML / Lovable to WordPress converter plugin', 'tahmir-ai-to-wp-converter'); ?></h2>
        <p><?php esc_html_e('Import supported HTML websites, deployed Lovable pages and AI-built front ends for free. You can also test a public GoHighLevel website or funnel page. Review the result and reconnect external integrations before publishing.', 'tahmir-ai-to-wp-converter'); ?></p>
        <p><a class="button" href="https://www.tahmir.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Visit Tahmir.com', 'tahmir-ai-to-wp-converter'); ?></a>
        <a class="button" href="https://www.tahmir.com/free-html-to-wordpress-converter/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('HTML to WordPress guide', 'tahmir-ai-to-wp-converter'); ?></a>
        <a class="button" href="https://www.tahmir.com/lovable-to-wordpress-converter/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Lovable to WordPress guide', 'tahmir-ai-to-wp-converter'); ?></a>
        <a class="button" href="https://www.tahmir.com/gohighlevel-to-wordpress-converter/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('GoHighLevel to WordPress guide', 'tahmir-ai-to-wp-converter'); ?></a></p>
        <h3><?php esc_html_e('Make pages editable and prepare client delivery with Pro', 'tahmir-ai-to-wp-converter'); ?></h3>
        <p><?php esc_html_e('Pro adds visual editing and page modules, logo detection and replacement, shared-menu controls, advanced forms, SEO, AI tools, analytics and client delivery. Keep Free installed; imported pages carry over. Pro requires an active license, and optional AI services may require credits or provider setup.', 'tahmir-ai-to-wp-converter'); ?></p>
        <p><a class="button button-primary" href="https://www.tahmir.com/upgrade/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('See Pro benefits and upgrade', 'tahmir-ai-to-wp-converter'); ?></a>
        <a class="button" href="https://www.tahmir.com/ai-to-wordpress-agency-playbook/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Client delivery playbook', 'tahmir-ai-to-wp-converter'); ?></a></p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon pages-icon">
                <span class="dashicons dashicons-media-text"></span>
            </div>
            <div class="stat-info">
                <span class="stat-number"><?php echo esc_html($stats['pages']); ?></span>
                <span class="stat-label"><?php esc_html_e('Pages', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon forms-icon">
                <span class="dashicons dashicons-feedback"></span>
            </div>
            <div class="stat-info">
                <span class="stat-number"><?php echo esc_html__('Pro', 'tahmir-ai-to-wp-converter'); ?></span>
                <span class="stat-label"><?php esc_html_e('Forms', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon assets-icon">
                <span class="dashicons dashicons-admin-appearance"></span>
            </div>
            <div class="stat-info">
                <span class="stat-number"><?php echo esc_html($stats['css_assets'] + $stats['js_assets']); ?></span>
                <span class="stat-label"><?php esc_html_e('Assets', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </div>
        
        <div class="stat-card <?php echo $stats['api_configured'] ? 'stat-success' : 'stat-warning'; ?>">
            <div class="stat-icon ai-icon">
                <span class="dashicons <?php echo $stats['api_configured'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
            </div>
            <div class="stat-info">
                <span class="stat-number"><?php echo $stats['api_configured'] ? 'Active' : 'Not Set'; ?></span>
                <span class="stat-label"><?php esc_html_e('AI Status', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <!-- Quick Actions -->
        <div class="dashboard-panel quick-actions-panel">
            <h2><span class="dashicons dashicons-lightning"></span> <?php esc_html_e('Quick Actions', 'tahmir-ai-to-wp-converter'); ?></h2>
            <div class="action-list">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-clone')); ?>" class="action-item">
                    <span class="dashicons dashicons-admin-site-alt2"></span>
                    <span><?php esc_html_e('Import from AI / Clone a Live Site', 'tahmir-ai-to-wp-converter'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-import')); ?>" class="action-item">
                    <span class="dashicons dashicons-upload"></span>
                    <span><?php esc_html_e('Import HTML', 'tahmir-ai-to-wp-converter'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-forms')); ?>" class="action-item">
                    <span class="dashicons dashicons-feedback"></span>
                    <span><?php esc_html_e('Create Form', 'tahmir-ai-to-wp-converter'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-asset-conflicts')); ?>" class="action-item">
                    <span class="dashicons dashicons-performance"></span>
                    <span><?php esc_html_e('Optimize Assets', 'tahmir-ai-to-wp-converter'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-settings')); ?>" class="action-item">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <span><?php esc_html_e('Settings', 'tahmir-ai-to-wp-converter'); ?></span>
                </a>
            </div>
        </div>
        
        <!-- Recent Pages -->
        <div class="dashboard-panel recent-pages-panel">
            <h2><span class="dashicons dashicons-clock"></span> <?php esc_html_e('Recent Pages', 'tahmir-ai-to-wp-converter'); ?></h2>
            <?php if (!empty($recent_pages)): ?>
            <div class="page-list">
                <?php foreach ($recent_pages as $page): ?>
                <div class="page-item">
                    <div class="page-info">
                        <strong><?php echo esc_html($page->post_title ?? '(Untitled)'); ?></strong>
                        <span class="page-date"><?php echo esc_html(date_i18n('M j, Y', strtotime($page->created_at))); ?></span>
                    </div>
                    <div class="page-actions">
                        <a href="<?php echo esc_url(get_permalink($page->wp_page_id)); ?>" target="_blank" class="button-link">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-builder&action=edit&id=' . $page->id)); ?>" class="button-link">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                    </div>
                </div>
                <?php
    endforeach; ?>
            </div>
            <?php
else: ?>
            <p class="no-pages"><?php esc_html_e('No pages imported yet. Start with HTML files or a live website URL.', 'tahmir-ai-to-wp-converter'); ?></p>
            <?php
endif; ?>
        </div>
    </div>
    
    <!-- Getting Started -->
    <?php if (!$stats['api_configured']): ?>
    <div class="ugentic-notice ugentic-notice-warning">
        <div class="notice-icon">
            <span class="dashicons dashicons-lightbulb"></span>
        </div>
        <div class="notice-content">
            <h3><?php esc_html_e('Set up AI to unlock the full power!', 'tahmir-ai-to-wp-converter'); ?></h3>
            <p><?php esc_html_e('Add your Gemini API key to use AI-powered page generation, smart editing, and content suggestions.', 'tahmir-ai-to-wp-converter'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-settings')); ?>" class="button button-primary">
                <?php esc_html_e('Configure API Key', 'tahmir-ai-to-wp-converter'); ?>
            </a>
        </div>
    </div>
    <?php
endif; ?>
</div>

<style>
.ugentic-dashboard-v2 {
    margin: -20px -20px 0;
    padding: 24px;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ed 100%);
    min-height: 100vh;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-left h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 28px;
    color: #1e293b;
}

.header-left h1 .dashicons {
    color: #075cff;
    font-size: 32px;
    width: 32px;
    height: 32px;
}

.version-badge {
    background: #eaf1ff;
    color: #063891;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.header-right {
    display: flex;
    gap: 10px;
}

.header-right .button {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    height: auto;
}

.button-hero {
    background: linear-gradient(135deg, #075cff 0%, #0348cc 100%) !important;
    border: none !important;
    color: white !important;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: transform 0.15s, box-shadow 0.15s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon .dashicons {
    font-size: 26px;
    width: 26px;
    height: 26px;
    color: white;
}

.pages-icon { background: linear-gradient(135deg, #075cff, #0348cc); }
.forms-icon { background: linear-gradient(135deg, #10b981, #059669); }
.assets-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.ai-icon { background: linear-gradient(135deg, #06b6d4, #0891b2); }

.stat-success .ai-icon { background: linear-gradient(135deg, #10b981, #059669); }
.stat-warning .ai-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    margin-top: 4px;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.dashboard-panel {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.dashboard-panel h2 {
    margin: 0 0 20px;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1e293b;
}

.dashboard-panel h2 .dashicons {
    color: #075cff;
}

/* Quick Actions */
.action-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.action-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 10px;
    text-decoration: none;
    color: #334155;
    font-weight: 500;
    transition: all 0.15s;
}

.action-item:hover {
    background: #eaf1ff;
    color: #063891;
}

.action-item .dashicons {
    color: #075cff;
}

/* Recent Pages */
.page-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.page-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 10px;
}

.page-info strong {
    display: block;
    color: #1e293b;
}

.page-date {
    font-size: 12px;
    color: #94a3b8;
}

.page-actions {
    display: flex;
    gap: 8px;
}

.button-link {
    color: #64748b;
    text-decoration: none;
}

.button-link:hover {
    color: #075cff;
}

.no-pages {
    color: #94a3b8;
    text-align: center;
    padding: 20px;
}

/* Notice */
.ugentic-notice {
    display: flex;
    gap: 20px;
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.notice-icon {
    width: 60px;
    height: 60px;
    background: #fef3c7;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notice-icon .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #f59e0b;
}

.notice-content h3 {
    margin: 0 0 8px;
    font-size: 18px;
}

.notice-content p {
    margin: 0 0 16px;
    color: #64748b;
}

@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .dashboard-grid { grid-template-columns: 1fr; }
}
</style>
