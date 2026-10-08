<?php
/**
 * Bulk Changes — Bulk AI Editing View (V3 - 10/10 Edition)
 */
if (!defined('ABSPATH')) exit;

$api = ugentic_builder()->api;
?>
<div class="wrap ugentic-wrap ugentic-all-pages-v2">

    <!-- Hero Header -->
    <div class="allpages-header">
        <div class="header-content">
            <div class="header-icon">
                <span class="dashicons dashicons-admin-page"></span>
            </div>
            <div class="header-text">
                <h1><?php esc_html_e('Bulk Changes', 'tahmir-ai-to-wp-converter'); ?></h1>
                <p><?php esc_html_e('Make bulk changes across multiple pages with AI. Context is preserved between messages.', 'tahmir-ai-to-wp-converter'); ?>
                    <span class="shortcut-hint">⌘/Ctrl+Enter to send</span>
                </p>
            </div>
        </div>
        <div class="header-stats">
            <div class="stat-item">
                <span class="stat-number" id="stat-total"><?php echo count($pages); ?></span>
                <span class="stat-label"><?php esc_html_e('Total Pages', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number" id="stat-selected">0</span>
                <span class="stat-label"><?php esc_html_e('Selected', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </div>
        <div class="header-decoration">
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="blob-svg">
                <path fill="rgba(255,255,255,0.08)"
                    d="M44.7,-76.4C58.8,-69.2,71.8,-59.1,79.6,-45.8C87.4,-32.5,90,-16.3,88.5,-0.9C87,14.5,81.3,29,72.8,41.7C64.3,54.4,53,65.3,39.7,72.9C26.4,80.5,13.2,84.8,-1.1,86.7C-15.4,88.6,-30.8,88.1,-43.3,80.8C-55.8,73.5,-65.4,59.4,-73.1,44.8C-80.8,30.2,-86.6,15.1,-87.2,-0.3C-87.8,-15.8,-83.2,-31.6,-74.5,-44.4C-65.8,-57.2,-53,-67,-39.2,-74.4C-25.4,-81.8,-12.7,-86.8,1.4,-89.2C15.5,-91.6,30.6,-83.6,44.7,-76.4Z"
                    transform="translate(100 100)" />
            </svg>
        </div>
    </div>

    <?php if (!$api->is_configured('gemini') && !$api->is_configured('anthropic')): ?>
        <div class="allpages-notice">
            <div class="notice-icon-wrap">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="notice-body">
                <strong><?php esc_html_e('API Key Required', 'tahmir-ai-to-wp-converter'); ?></strong>
                <p>
                    <?php esc_html_e('Please configure your Gemini or Anthropic API key in', 'tahmir-ai-to-wp-converter'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-settings')); ?>"><?php esc_html_e('Settings', 'tahmir-ai-to-wp-converter'); ?></a>
                    <?php esc_html_e('to use AI features.', 'tahmir-ai-to-wp-converter'); ?>
                </p>
            </div>
        </div>
    <?php else: ?>

        <!-- Mode Toggle -->
        <div class="mode-toggle-bar">
            <button type="button" class="mode-toggle-btn active" data-mode="edit" id="mode-edit-btn">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e('Bulk Edit Pages', 'tahmir-ai-to-wp-converter'); ?>
            </button>
            <button type="button" class="mode-toggle-btn" data-mode="create" id="mode-create-btn">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e('Create New Pages', 'tahmir-ai-to-wp-converter'); ?>
            </button>
        </div>

        <!-- Progress Bar (global) -->
        <div class="bulk-progress-bar" id="bulk-progress-bar" style="display:none;">
            <div class="progress-info">
                <span class="progress-label" id="progress-label"><?php esc_html_e('Processing...', 'tahmir-ai-to-wp-converter'); ?></span>
                <span class="progress-count" id="progress-count">0/0</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="progress-fill" style="width:0%"></div>
            </div>
        </div>

        <div class="allpages-layout" id="allpages-layout">

            <!-- Pages Selection Panel (Edit Mode Only) -->
            <div class="allpages-panel pages-panel" id="pages-panel">
                <div class="panel-header">
                    <div class="panel-icon">
                        <span class="dashicons dashicons-screenoptions"></span>
                    </div>
                    <h2><?php esc_html_e('Select Pages', 'tahmir-ai-to-wp-converter'); ?></h2>
                    <div class="panel-actions">
                        <button type="button" class="select-toggle-btn" id="select-all-btn">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Select All', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                    </div>
                </div>

                <!-- AI Accuracy Mode -->
                <div class="bulk-ai-mode" role="radiogroup" aria-label="<?php esc_attr_e('AI accuracy mode', 'tahmir-ai-to-wp-converter'); ?>">
                    <label class="bulk-ai-mode-option active" data-ai-mode-option="standard">
                        <input type="radio" name="ugentic-bulk-ai-mode" value="standard" checked>
                        <span class="dashicons dashicons-performance"></span>
                        <span class="bulk-ai-mode-text">
                            <strong><?php esc_html_e('Fast AI', 'tahmir-ai-to-wp-converter'); ?></strong>
                            <small><?php esc_html_e('Shared plan', 'tahmir-ai-to-wp-converter'); ?></small>
                        </span>
                    </label>
                    <label class="bulk-ai-mode-option" data-ai-mode-option="pure_ai" title="<?php esc_attr_e('Analyzes each selected page separately for maximum accuracy.', 'tahmir-ai-to-wp-converter'); ?>">
                        <input type="radio" name="ugentic-bulk-ai-mode" value="pure_ai">
                        <span class="dashicons dashicons-superhero-alt"></span>
                        <span class="bulk-ai-mode-text">
                            <strong><?php esc_html_e('Pure AI', 'tahmir-ai-to-wp-converter'); ?></strong>
                            <small><?php esc_html_e('Every page', 'tahmir-ai-to-wp-converter'); ?></small>
                        </span>
                    </label>
                </div>

                <!-- Search -->
                <div class="pages-search">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="page-search" placeholder="<?php esc_html_e('Search pages...', 'tahmir-ai-to-wp-converter'); ?>">
                </div>

                <!-- Pages List with Groups -->
                <div class="pages-list" id="pages-list">
                    <?php if (empty($pages)): ?>
                        <div class="no-pages">
                            <span class="dashicons dashicons-media-document"></span>
                            <p><?php esc_html_e('No Tahmir AI pages found. Create some pages first!', 'tahmir-ai-to-wp-converter'); ?></p>
                        </div>
                    <?php else:
                        $published = [];
                        $drafts = [];
                        foreach ($pages as $p) {
                            $wp_post = get_post($p->wp_page_id);
                            $status = $wp_post ? $wp_post->post_status : 'unknown';
                            if ($status === 'publish') {
                                $published[] = $p;
                            } else {
                                $drafts[] = $p;
                            }
                        }
                    ?>
                        <?php if (!empty($published)): ?>
                        <div class="page-group" data-group="published">
                            <div class="page-group-header" data-toggle-group="published">
                                <span class="group-chevron">▾</span>
                                <span class="group-label"><?php esc_html_e('Published', 'tahmir-ai-to-wp-converter'); ?></span>
                                <span class="group-count"><?php echo count($published); ?></span>
                            </div>
                            <div class="page-group-items" id="group-published">
                                <?php foreach ($published as $p):
                                    $wp_post = get_post($p->wp_page_id);
                                    $title = $wp_post ? $wp_post->post_title : esc_html__('Page #', 'tahmir-ai-to-wp-converter') . $p->id;
                                ?>
                                    <label class="page-item" data-title="<?php echo esc_attr(strtolower($title)); ?>" data-page-id="<?php echo esc_attr($p->id); ?>">
                                        <input type="checkbox" class="page-checkbox" value="<?php echo esc_attr($p->id); ?>">
                                        <div class="page-info">
                                            <span class="page-title"><?php echo esc_html($title); ?></span>
                                            <span class="page-meta">
                                                <span class="status-badge status-active"><?php esc_html_e('Published', 'tahmir-ai-to-wp-converter'); ?></span>
                                                <span class="page-id">ID: <?php echo esc_html($p->id); ?></span>
                                            </span>
                                        </div>
                                        <span class="check-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($drafts)): ?>
                        <div class="page-group" data-group="drafts">
                            <div class="page-group-header" data-toggle-group="drafts">
                                <span class="group-chevron">▾</span>
                                <span class="group-label"><?php esc_html_e('Drafts & Other', 'tahmir-ai-to-wp-converter'); ?></span>
                                <span class="group-count"><?php echo count($drafts); ?></span>
                            </div>
                            <div class="page-group-items" id="group-drafts">
                                <?php foreach ($drafts as $p):
                                    $wp_post = get_post($p->wp_page_id);
                                    $title = $wp_post ? $wp_post->post_title : esc_html__('Page #', 'tahmir-ai-to-wp-converter') . $p->id;
                                    $status = $wp_post ? $wp_post->post_status : 'unknown';
                                    $status_label = ucfirst($status);
                                ?>
                                    <label class="page-item" data-title="<?php echo esc_attr(strtolower($title)); ?>" data-page-id="<?php echo esc_attr($p->id); ?>">
                                        <input type="checkbox" class="page-checkbox" value="<?php echo esc_attr($p->id); ?>">
                                        <div class="page-info">
                                            <span class="page-title"><?php echo esc_html($title); ?></span>
                                            <span class="page-meta">
                                                <span class="status-badge status-draft"><?php echo esc_html($status_label); ?></span>
                                                <span class="page-id">ID: <?php echo esc_html($p->id); ?></span>
                                            </span>
                                        </div>
                                        <span class="check-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions (Edit Mode) -->
                <div class="quick-actions edit-mode-only">
                    <label class="quick-label">
                        <span class="dashicons dashicons-admin-customizer"></span>
                        <?php esc_html_e('Quick Actions', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                    <div class="action-chips" id="action-chips-default">
                        <button type="button" class="action-chip" data-prompt="Change the header background color to dark navy blue on all pages">
                            <span>🎨</span> <?php esc_html_e('Header Color', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="action-chip" data-prompt="Update the footer copyright year to 2026 on all pages">
                            <span>📅</span> <?php esc_html_e('Copyright Year', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="action-chip" data-prompt="Add a cookie consent banner at the bottom of all pages">
                            <span>🍪</span> <?php esc_html_e('Cookie Banner', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="action-chip" data-prompt="Make all call-to-action buttons rounded with a gradient background">
                            <span>🔘</span> <?php esc_html_e('Style Buttons', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                    </div>

                    <!-- Custom Quick Actions -->
                    <div class="custom-actions-section" id="custom-actions-section">
                        <label class="quick-label custom-label">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('My Actions', 'tahmir-ai-to-wp-converter'); ?>
                            <button type="button" class="add-custom-action-btn" id="add-custom-action-btn" title="<?php esc_html_e('Save current prompt as action', 'tahmir-ai-to-wp-converter'); ?>">
                                <span class="dashicons dashicons-plus-alt2"></span>
                            </button>
                        </label>
                        <div class="action-chips custom-chips" id="custom-action-chips">
                            <!-- Populated by JS from saved actions -->
                        </div>
                    </div>
                </div>

                <!-- Version Timeline -->
                <div class="version-timeline-section" id="version-timeline-section" style="display:none;">
                    <label class="quick-label">
                        <span class="dashicons dashicons-backup"></span>
                        <?php esc_html_e('Version History', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                    <div class="timeline-container" id="timeline-container">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <!-- Create Pages Panel (Create Mode Only) -->
            <div class="allpages-panel create-panel" id="create-panel" style="display:none;">
                <div class="panel-header">
                    <div class="panel-icon" style="background:linear-gradient(135deg,#43a047,#66bb6a);">
                        <span class="dashicons dashicons-plus-alt"></span>
                    </div>
                    <h2><?php esc_html_e('Create New Pages', 'tahmir-ai-to-wp-converter'); ?></h2>
                </div>
                <div class="create-panel-info">
                    <p><?php esc_html_e('Select a reference page below, then describe the page to create. AI will clone the design from your reference.', 'tahmir-ai-to-wp-converter'); ?></p>
                </div>

                <div class="reference-page-selector">
                    <label class="reference-label">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <?php esc_html_e('Design Reference Page', 'tahmir-ai-to-wp-converter'); ?>
                        <span class="required-badge">*</span>
                    </label>
                    <select id="reference-page-select" class="reference-select">
                        <option value=""><?php esc_html_e('— Select a page to clone design from —', 'tahmir-ai-to-wp-converter'); ?></option>
                        <?php foreach ($pages as $p):
                            $wp_post = get_post($p->wp_page_id);
                            $ref_title = $wp_post ? $wp_post->post_title : esc_html__('Page #', 'tahmir-ai-to-wp-converter') . $p->id;
                        ?>
                            <option value="<?php echo esc_attr($p->id); ?>"><?php echo esc_html($ref_title); ?> (ID: <?php echo esc_html($p->id); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="reference-hint"><?php esc_html_e('The new page will match this page\'s colors, fonts, and layout.', 'tahmir-ai-to-wp-converter'); ?></span>
                </div>
                <div class="quick-actions">
                    <label class="quick-label">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Quick Create', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                    <div class="action-chips create-page-chips">
                        <button type="button" class="action-chip chip-create" data-prompt="Create a new Privacy Policy page with comprehensive content covering data collection, cookies, third-party services, user rights, GDPR compliance. Link it in the footer of all pages.">
                            <span>🔒</span> <?php esc_html_e('Privacy Policy', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="action-chip chip-create" data-prompt="Create a new Terms and Conditions page with comprehensive content covering acceptance of terms, user responsibilities, intellectual property, limitation of liability, governing law. Link it in the footer of all pages.">
                            <span>📜</span> <?php esc_html_e('Terms & Conditions', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="action-chip chip-create" data-prompt="Create a new California Privacy (CCPA) page with content covering consumer rights, data categories, opt-out instructions, do-not-sell rights, data access requests. Link it in the footer of all pages.">
                            <span>🏛️</span> <?php esc_html_e('CCPA / California', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="action-chip chip-create" data-prompt="Create a new Cookie Policy page covering types of cookies used, how to manage cookies, third-party cookies, consent mechanisms. Link it in the footer of all pages.">
                            <span>🍪</span> <?php esc_html_e('Cookie Policy', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="action-chip chip-create" data-prompt="Create a new Disclaimer page covering general disclaimers, warranties, external links, professional advice limitations. Link it in the footer of all pages.">
                            <span>⚖️</span> <?php esc_html_e('Disclaimer', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                        <button type="button" class="action-chip chip-create" data-prompt="Create both a Privacy Policy page AND a Terms and Conditions page with full professional content. Link both in the footer of all pages.">
                            <span>📦</span> <?php esc_html_e('Privacy + Terms (batch)', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                    </div>
                </div>
            </div>


            <!-- AI Chat Panel -->
            <div class="allpages-panel chat-panel">
                <div class="panel-header">
                    <div class="panel-icon ai-icon">
                        <span class="dashicons dashicons-superhero-alt"></span>
                    </div>
                    <h2><?php esc_html_e('AI Conversation', 'tahmir-ai-to-wp-converter'); ?></h2>
                    <div class="panel-header-actions">
                        <!-- Preview Toggle -->
                        <button type="button" class="preview-toggle-btn" id="preview-toggle-btn" title="<?php esc_html_e('Toggle live preview', 'tahmir-ai-to-wp-converter'); ?>" style="display:none;">
                            <span class="dashicons dashicons-visibility"></span>
                            <span class="preview-btn-label"><?php esc_html_e('Preview', 'tahmir-ai-to-wp-converter'); ?></span>
                        </button>
                        <div class="conversation-history-wrap">
                            <button type="button" class="conv-history-btn" id="conv-history-btn" title="<?php esc_html_e('Previous conversations', 'tahmir-ai-to-wp-converter'); ?>">
                                <span class="dashicons dashicons-backup"></span>
                                <span class="conv-btn-label"><?php esc_html_e('History', 'tahmir-ai-to-wp-converter'); ?></span>
                            </button>
                            <div class="conv-history-dropdown" id="conv-history-dropdown" style="display:none;">
                                <div class="conv-dropdown-header">
                                    <strong><?php esc_html_e('Previous Conversations', 'tahmir-ai-to-wp-converter'); ?></strong>
                                    <button type="button" class="conv-new-btn" id="conv-new-btn" title="<?php esc_html_e('New conversation', 'tahmir-ai-to-wp-converter'); ?>">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <?php esc_html_e('New', 'tahmir-ai-to-wp-converter'); ?>
                                    </button>
                                </div>
                                <div class="conv-list" id="conv-list">
                                    <div class="conv-empty"><?php esc_html_e('No previous conversations', 'tahmir-ai-to-wp-converter'); ?></div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="bulk-history-btn" id="bulk-history-btn" title="<?php esc_html_e('Bulk change history — revert any of the last 50 operations', 'tahmir-ai-to-wp-converter'); ?>">
                            <span class="dashicons dashicons-list-view"></span>
                            <span class="bulk-history-label"><?php esc_html_e('Changes Log', 'tahmir-ai-to-wp-converter'); ?></span>
                        </button>
                        <button type="button" class="clear-chat-btn" id="clear-chat-btn" title="<?php esc_html_e('Clear conversation', 'tahmir-ai-to-wp-converter'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>

                <!-- Bulk Change History Panel (slide-out overlay) -->
                <div class="bulk-history-panel" id="bulk-history-panel" style="display:none;">
                    <div class="bulk-history-header">
                        <div class="bulk-history-title">
                            <span class="dashicons dashicons-backup"></span>
                            <h3><?php esc_html_e('Bulk Changes History', 'tahmir-ai-to-wp-converter'); ?></h3>
                            <span class="bulk-history-badge" id="bulk-history-count">0</span>
                        </div>
                        <button type="button" class="bulk-history-close" id="bulk-history-close">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="bulk-history-list" id="bulk-history-list">
                        <div class="bulk-history-loading">
                            <span class="dashicons dashicons-update spin"></span>
                            <?php esc_html_e('Loading history...', 'tahmir-ai-to-wp-converter'); ?>
                        </div>
                    </div>
                </div>

                <!-- Tabbed Conversations -->
                <div class="conv-tabs-bar" id="conv-tabs-bar">
                    <div class="conv-tabs-scroll" id="conv-tabs-scroll">
                        <div class="conv-tab active" data-tab-id="0">
                            <span class="conv-tab-label"><?php esc_html_e('Chat 1', 'tahmir-ai-to-wp-converter'); ?></span>
                            <span class="conv-tab-status"></span>
                        </div>
                    </div>
                    <button type="button" class="conv-tab-add" id="conv-tab-add" title="<?php esc_html_e('New parallel conversation', 'tahmir-ai-to-wp-converter'); ?>">
                        <span class="dashicons dashicons-plus"></span>
                    </button>
                </div>

                <!-- Chat Messages Thread -->
                <div class="chat-thread" id="chat-thread">
                    <div class="chat-welcome">
                        <div class="welcome-icon">💬</div>
                        <h3><?php esc_html_e('Start a conversation', 'tahmir-ai-to-wp-converter'); ?></h3>
                        <p><?php esc_html_e('Select pages on the left, then type your instruction below. I\'ll remember context between messages — so you can say follow-ups like "fix that" or "revert the header change".', 'tahmir-ai-to-wp-converter'); ?></p>
                        <div class="welcome-shortcuts">
                            <span class="shortcut-tag">⌘/Ctrl+Enter</span> <?php esc_html_e('Send', 'tahmir-ai-to-wp-converter'); ?> &nbsp;
                            <span class="shortcut-tag">Shift+Click</span> <?php esc_html_e('Range select', 'tahmir-ai-to-wp-converter'); ?> &nbsp;
                            <span class="shortcut-tag">Esc</span> <?php esc_html_e('Cancel plan', 'tahmir-ai-to-wp-converter'); ?>
                        </div>
                    </div>
                </div>

                <!-- Live Preview Panel (hidden by default, slides in) -->
                <div class="preview-panel" id="preview-panel" style="display:none;">
                    <div class="preview-header">
                        <div class="preview-tabs">
                            <button type="button" class="preview-tab active" data-view="after"><?php esc_html_e('After', 'tahmir-ai-to-wp-converter'); ?></button>
                            <button type="button" class="preview-tab" data-view="before"><?php esc_html_e('Before', 'tahmir-ai-to-wp-converter'); ?></button>
                            <button type="button" class="preview-tab" data-view="split"><?php esc_html_e('Split', 'tahmir-ai-to-wp-converter'); ?></button>
                        </div>
                        <button type="button" class="preview-close-btn" id="preview-close-btn">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="preview-body" id="preview-body">
                        <div class="preview-frame-wrap preview-before" id="preview-before">
                            <div class="preview-frame-label"><?php esc_html_e('Before', 'tahmir-ai-to-wp-converter'); ?></div>
                            <iframe class="preview-iframe" id="preview-iframe-before" sandbox="allow-same-origin"></iframe>
                        </div>
                        <div class="preview-frame-wrap preview-after" id="preview-after">
                            <div class="preview-frame-label"><?php esc_html_e('After', 'tahmir-ai-to-wp-converter'); ?></div>
                            <iframe class="preview-iframe" id="preview-iframe-after" sandbox="allow-same-origin"></iframe>
                        </div>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="chat-input-area">
                    <div class="image-preview-area" id="image-preview-area" style="display:none;">
                        <div class="image-preview-inner">
                            <img id="image-preview-thumb" src="" alt="">
                            <span class="image-preview-name" id="image-preview-name"></span>
                            <button type="button" class="image-preview-remove" id="image-preview-remove" title="<?php esc_html_e('Remove image', 'tahmir-ai-to-wp-converter'); ?>">&times;</button>
                        </div>
                    </div>
                    <div class="chat-input-wrap">
                        <button type="button" class="chat-attach-btn" id="chat-attach-btn" title="<?php esc_html_e('Attach image', 'tahmir-ai-to-wp-converter'); ?>">
                            <span class="dashicons dashicons-paperclip"></span>
                        </button>
                        <input type="file" id="image-upload-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                        <textarea id="chat-input" rows="2"
                            placeholder="<?php esc_html_e('Type your instruction... e.g. Change the header color to dark blue on all pages', 'tahmir-ai-to-wp-converter'); ?>"></textarea>
                        <button type="button" class="chat-send-btn" id="chat-send-btn" disabled>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                    <div class="chat-hints">
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php esc_html_e('AI remembers previous edits. Attach images as design references. Use ⌘+Enter to send.', 'tahmir-ai-to-wp-converter'); ?>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>
</div>
