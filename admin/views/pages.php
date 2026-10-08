<?php
/**
 * Pages List View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ugentic-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Tahmir AI Pages', 'tahmir-ai-to-wp-converter'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-generate')); ?>" class="page-title-action">
        <?php esc_html_e('Generate New', 'tahmir-ai-to-wp-converter'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-import')); ?>" class="page-title-action">
        <?php esc_html_e('Import Website', 'tahmir-ai-to-wp-converter'); ?>
    </a>
    <?php if (!empty($pages)): ?>
    <button type="button" id="export-site-btn" class="page-title-action" style="color: #10b981; border-color: #10b981;">
        <span class="dashicons dashicons-download" style="font-size: 16px; vertical-align: text-bottom;"></span>
        <?php esc_html_e('Export Site', 'tahmir-ai-to-wp-converter'); ?>
    </button>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    <?php if (isset($total_pages, $page_size, $current_page)): ?>
        <div class="tablenav top ugentic-pages-pagination" aria-label="Page navigation">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html(sprintf(__('%d pages', 'ugentic-builder'), $total_pages)); ?></span>
                <?php echo wp_kses_post(paginate_links([
                    'base' => str_replace('999999999', '%#%', esc_url(add_query_arg('ugentic_page', 999999999, admin_url('admin.php?page=ugentic-pages')))),
                    'current' => $current_page,
                    'total' => max(1, (int) ceil($total_pages / $page_size)),
                    'prev_text' => __('Previous', 'ugentic-builder'),
                    'next_text' => __('Next', 'ugentic-builder'),
                ])); ?>
            </div>
        </div>
    <?php endif; ?>

    
    
    <div class="ugentic-card">
        <?php if (empty($pages)): ?>
            <div class="ugentic-empty-state">
                <span class="dashicons dashicons-media-text"></span>
                <h3><?php esc_html_e('No pages yet', 'tahmir-ai-to-wp-converter'); ?></h3>
                <p><?php esc_html_e('Create your first page with AI or import an existing website.', 'tahmir-ai-to-wp-converter'); ?></p>
                <div class="empty-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-generate')); ?>" class="button button-primary">
                        <?php esc_html_e('Generate Page with AI', 'tahmir-ai-to-wp-converter'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-import')); ?>" class="button">
                        <?php esc_html_e('Import Website', 'tahmir-ai-to-wp-converter'); ?>
                    </a>
                </div>
            </div>
        <?php
else: ?>
            <table class="wp-list-table widefat fixed striped pages">
                <thead>
                    <tr>
                        <th class="check-column" style="width: 30px;">
                            <input type="checkbox" id="select-all-pages">
                        </th>
                        <th class="column-title"><?php esc_html_e('Title', 'tahmir-ai-to-wp-converter'); ?></th>
                        <th class="column-status"><?php esc_html_e('Status', 'tahmir-ai-to-wp-converter'); ?></th>
                        <th class="column-seo"><?php esc_html_e('SEO/AISEO', 'tahmir-ai-to-wp-converter'); ?></th>
                        <th class="column-date"><?php esc_html_e('Created', 'tahmir-ai-to-wp-converter'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'tahmir-ai-to-wp-converter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <?php
        $wp_post = get_post($page->wp_page_id);
        $status = $wp_post ? $wp_post->post_status : 'orphaned';
        $meta = json_decode($page->meta_data, true) ?: [];
        $seo_summary = (isset($seo) && $seo && method_exists($seo, 'get_page_state_summary'))
            ? $seo->get_page_state_summary($page)
            : ['status' => 'unscanned', 'score' => null, 'label' => esc_html__('Not scanned', 'tahmir-ai-to-wp-converter')];
?>
                        <tr data-page-id="<?php echo esc_attr($page->id); ?>" data-wp-id="<?php echo esc_attr($page->wp_page_id); ?>">
                            <td class="check-column">
                                <input type="checkbox" name="page_ids[]" value="<?php echo esc_attr($page->id); ?>">
                            </td>
                            <td class="column-title">
                                <strong>
                                    <?php if ($wp_post): ?>
                                        <a href="<?php echo esc_url('https://tahmir.com/upgrade'); ?>">
                                            <?php echo esc_html($page->post_title ?: esc_html__('(No title)', 'tahmir-ai-to-wp-converter')); ?>
                                        </a>
                                    <?php
        else: ?>
                                        <span style="color: #dc3545;">
                                            <?php echo esc_html($page->post_title ?: esc_html__('(No title)', 'tahmir-ai-to-wp-converter')); ?>
                                            <small>(WP page deleted)</small>
                                        </span>
                                    <?php
        endif; ?>
                                </strong>
                                <?php if (!empty($meta['imported'])): ?>
                                    <span class="badge badge-info"><?php esc_html_e('Imported', 'tahmir-ai-to-wp-converter'); ?></span>
                                <?php
        endif; ?>
                                <?php if (!empty($meta['is_dynamic'])): ?>
                                    <span class="badge" style="background: #f97316; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 4px;">
                                        <span class="dashicons dashicons-database" style="font-size: 12px; width: 12px; height: 12px; vertical-align: middle;"></span>
                                        <?php echo esc_html($meta['dynamic_config']['content_type'] ?? 'Dynamic'); ?>
                                    </span>
                                <?php
        endif; ?>
                            </td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo esc_attr($status); ?>">
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td class="column-seo">
                                <span class="status-badge status-<?php echo esc_attr($seo_summary['status']); ?>">
                                    <?php echo esc_html($seo_summary['label']); ?>
                                </span>
                                <?php if ($seo_summary['score'] !== null): ?>
                                    <span style="display:block;margin-top:4px;font-size:12px;color:#64748b;">
                                        <?php echo esc_html(sprintf(/* translators: SEO score from zero to one hundred. */ esc_html__('Score: %d/100', 'tahmir-ai-to-wp-converter'), (int) $seo_summary['score'])); ?>
                                    </span>
                                <?php endif; ?>
                                <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap;">
                                    <button type="button" class="button button-small ugentic-seo-review-btn" data-id="<?php echo esc_attr($page->id); ?>">
                                        <?php esc_html_e('Review', 'tahmir-ai-to-wp-converter'); ?>
                                    </button>
                                    <button type="button" class="button button-small ugentic-seo-rescan-btn" data-id="<?php echo esc_attr($page->id); ?>">
                                        <?php esc_html_e('Re-scan', 'tahmir-ai-to-wp-converter'); ?>
                                    </button>
                                </div>
                            </td>
                            <td class="column-date">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($page->created_at))); ?>
                            </td>
                            <td class="column-actions">
                                <?php if ($wp_post): ?>
                                    <a href="<?php echo esc_url(get_permalink($page->wp_page_id)); ?>" class="button button-small" target="_blank">
                                        <?php esc_html_e('View', 'tahmir-ai-to-wp-converter'); ?>
                                    </a>
                                    <a href="<?php echo esc_url('https://tahmir.com/upgrade'); ?>" class="button button-small">
                                        <?php esc_html_e('Visual Editor · Pro', 'tahmir-ai-to-wp-converter'); ?>
                                    </a>
                                <?php
        endif; ?>
                                <?php if (empty($meta['is_dynamic'])): ?>
                                    <button type="button" class="button button-small analyze-dynamic-btn" data-id="<?php echo esc_attr($page->id); ?>" style="color: #f97316; border-color: #f97316;" title="Analyze for dynamic content">
                                        <span class="dashicons dashicons-database" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                                        <?php esc_html_e('Analyze', 'tahmir-ai-to-wp-converter'); ?>
                                    </button>
                                <?php
        else: ?>
                                    <button type="button" class="button button-small remove-dynamic-btn" data-id="<?php echo esc_attr($page->id); ?>" style="color: #999;" title="Remove dynamic content">
                                        <?php esc_html_e('Remove Dynamic', 'tahmir-ai-to-wp-converter'); ?>
                                    </button>
                                <?php
        endif; ?>
                                <button type="button" class="button button-small export-page-btn" data-id="<?php echo esc_attr($page->id); ?>" data-title="<?php echo esc_attr($page->post_title ?: 'page'); ?>" style="color: #10b981; border-color: #10b981;" title="Export as clean HTML">
                                    <span class="dashicons dashicons-download" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                                    <?php esc_html_e('Export', 'tahmir-ai-to-wp-converter'); ?>
                                </button>
                                <button type="button" class="button button-small view-dna-btn" data-id="<?php echo esc_attr($page->id); ?>" style="color: #f97316; border-color: #f97316;" title="View/Generate Design DNA">
                                    🧬 <?php esc_html_e('DNA', 'tahmir-ai-to-wp-converter'); ?>
                                </button>
                                <button type="button" class="button button-small view-history-btn" data-id="<?php echo esc_attr($page->id); ?>">
                                    <?php esc_html_e('History', 'tahmir-ai-to-wp-converter'); ?>
                                </button>
                                <?php if (!empty($meta['import_mode']) && $meta['import_mode'] === 'faithful'): ?>
                                    <button type="button" class="button button-small webhook-settings-btn" data-id="<?php echo esc_attr($page->id); ?>" data-webhooks="<?php echo esc_attr(json_encode($meta['webhooks'] ?? [])); ?>" style="color: #f97316; border-color: #f97316;" title="Configure form webhooks">
                                        <span class="dashicons dashicons-rest-api" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                                        <?php esc_html_e('Webhooks', 'tahmir-ai-to-wp-converter'); ?>
                                    </button>
                                <?php
        endif; ?>
                                <button type="button" class="button button-small delete-page-btn" data-id="<?php echo esc_attr($page->id); ?>" data-wp-id="<?php echo esc_attr($page->wp_page_id); ?>" style="color: #dc3545; border-color: #dc3545;">
                                    <?php esc_html_e('Delete', 'tahmir-ai-to-wp-converter'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php
    endforeach; ?>
                </tbody>
            </table>
            
            <!-- Bulk Actions -->
            <div style="margin-top: 15px;">
                <button type="button" id="ugentic-seo-apply-all-btn" class="button" style="color: #2563eb; border-color: #2563eb;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Apply Review-Ready SEO/AISEO', 'tahmir-ai-to-wp-converter'); ?>
                </button>
                <button type="button" id="ugentic-seo-rescan-all-btn" class="button" style="color: #ea580c; border-color: #ea580c;">
                    <span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Re-scan All SEO/AISEO', 'tahmir-ai-to-wp-converter'); ?>
                </button>
                <button type="button" id="delete-selected-btn" class="button" style="color: #dc3545; border-color: #dc3545;">
                    <?php esc_html_e('Delete Selected', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        <?php
endif; ?>
    </div>
</div>

<!-- SEO/AISEO Review Modal -->
<div id="seo-aiseo-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content" style="max-width: 820px;">
        <div class="ugentic-modal-header">
            <h2><?php esc_html_e('SEO/AISEO Review', 'tahmir-ai-to-wp-converter'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div id="seo-aiseo-loading" style="text-align: center; padding: 30px;">
                <span class="spinner is-active" style="float: none;"></span>
                <p><?php esc_html_e('Loading recommendations...', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            <div id="seo-aiseo-result" style="display: none;"></div>
            <div id="seo-aiseo-actions" style="display: none; text-align: right; padding-top: 16px; border-top: 1px solid #ddd; margin-top: 16px;">
                <button type="button" class="button close-modal"><?php esc_html_e('Close', 'tahmir-ai-to-wp-converter'); ?></button>
                <button type="button" id="seo-aiseo-rescan-current" class="button" style="margin-left: 8px;">
                    <?php esc_html_e('Re-scan', 'tahmir-ai-to-wp-converter'); ?>
                </button>
                <button type="button" id="seo-aiseo-apply-current" class="button button-primary" style="margin-left: 8px;">
                    <?php esc_html_e('Save SEO Settings', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Content Analysis Modal -->
<div id="dynamic-analysis-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content" style="max-width: 600px;">
        <div class="ugentic-modal-header">
            <h2><?php esc_html_e('Dynamic Content Analysis', 'tahmir-ai-to-wp-converter'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div id="dynamic-analysis-content" class="modal-body" style="padding: 20px;">
            <div id="dynamic-analysis-loading" style="text-align: center; padding: 30px;">
                <span class="spinner is-active" style="float: none;"></span>
                <p><?php esc_html_e('Analyzing page for repeating content patterns...', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            <div id="dynamic-analysis-result" style="display: none;"></div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="history-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content">
        <div class="ugentic-modal-header">
            <h2><?php esc_html_e('Page History', 'tahmir-ai-to-wp-converter'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div id="history-content" class="modal-body">
            <!-- History items will be loaded here -->
        </div>
    </div>
</div>

<!-- Webhook Settings Modal -->
<div id="webhook-settings-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content" style="max-width: 600px;">
        <div class="ugentic-modal-header">
            <h2><?php esc_html_e('Form Webhook Settings', 'tahmir-ai-to-wp-converter'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p class="description" style="margin-bottom: 15px;">
                <?php esc_html_e('Configure webhook URLs to receive form submission data as JSON POST requests.', 'tahmir-ai-to-wp-converter'); ?>
            </p>
            <div id="webhooks-list-admin" style="margin-bottom: 15px;"></div>
            <button type="button" id="add-webhook-admin" class="button" style="margin-bottom: 15px;">
                <span class="dashicons dashicons-plus-alt" style="vertical-align: text-bottom;"></span>
                <?php esc_html_e('Add Webhook', 'tahmir-ai-to-wp-converter'); ?>
            </button>
            <div style="text-align: right; padding-top: 15px; border-top: 1px solid #ddd;">
                <button type="button" class="button close-modal"><?php esc_html_e('Cancel', 'tahmir-ai-to-wp-converter'); ?></button>
                <button type="button" id="save-webhooks-btn" class="button button-primary" style="margin-left: 8px;">
                    <?php esc_html_e('Save Webhooks', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Design DNA Modal -->
<div id="dna-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content" style="max-width: 700px;">
        <div class="ugentic-modal-header">
            <h2>🧬 <?php esc_html_e('Page Design DNA', 'tahmir-ai-to-wp-converter'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div id="dna-loading" style="text-align: center; padding: 30px;">
                <span class="spinner is-active" style="float: none;"></span>
                <p><?php esc_html_e('Extracting Design DNA...', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            <div id="dna-result" style="display: none;"></div>
            <div style="text-align: right; padding-top: 15px; border-top: 1px solid #ddd; margin-top: 15px;" id="dna-actions" style="display: none;">
                <button type="button" class="button close-modal"><?php esc_html_e('Close', 'tahmir-ai-to-wp-converter'); ?></button>
                <button type="button" id="regenerate-dna-btn" class="button" style="margin-left: 8px; color: #f97316; border-color: #f97316;">
                    🔄 <?php esc_html_e('Regenerate DNA', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Freedom Export Modal -->
<div id="export-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content" style="max-width: 520px;">
        <div class="ugentic-modal-header">
            <h2><span class="dashicons dashicons-download" style="color: #10b981;"></span> <?php esc_html_e('Freedom Export', 'tahmir-ai-to-wp-converter'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p class="description" style="margin-bottom: 15px; font-size: 13px;">
                <?php esc_html_e('Export your pages as clean, standalone HTML files — no WordPress, no plugin dependencies, no vendor lock-in.', 'tahmir-ai-to-wp-converter'); ?>
            </p>
            
            <div id="export-single-section" style="display: none;">
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div style="font-weight: 600; font-size: 14px; margin-bottom: 8px;">📄 <span id="export-page-title"></span></div>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                        <input type="checkbox" id="export-inline-css">
                        <?php esc_html_e('Inline all CSS into the HTML file', 'tahmir-ai-to-wp-converter'); ?>
                    </label>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="button close-modal"><?php esc_html_e('Cancel', 'tahmir-ai-to-wp-converter'); ?></button>
                    <button type="button" id="do-export-page" class="button button-primary" style="margin-left: 8px; background: #10b981; border-color: #059669;">
                        <span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span>
                        <?php esc_html_e('Download HTML', 'tahmir-ai-to-wp-converter'); ?>
                    </button>
                </div>
            </div>
            
            <div id="export-site-section" style="display: none;">
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px;">📦 <?php esc_html_e('Full Site Export', 'tahmir-ai-to-wp-converter'); ?></div>
                    <p style="font-size: 12px; color: #64748b; margin: 4px 0 0;">
                        <?php esc_html_e('Downloads all pages as a ZIP archive, ready to deploy on Netlify, Vercel, or any static host.', 'tahmir-ai-to-wp-converter'); ?>
                    </p>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="button close-modal"><?php esc_html_e('Cancel', 'tahmir-ai-to-wp-converter'); ?></button>
                    <button type="button" id="do-export-site" class="button button-primary" style="margin-left: 8px; background: #10b981; border-color: #059669;">
                        <span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span>
                        <?php esc_html_e('Download ZIP', 'tahmir-ai-to-wp-converter'); ?>
                    </button>
                </div>
            </div>
            
            <div id="export-progress" style="display: none; text-align: center; padding: 20px;">
                <span class="spinner is-active" style="float: none;"></span>
                <p id="export-progress-text"><?php esc_html_e('Preparing export...', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            
            <div id="export-result" style="display: none; text-align: center; padding: 16px;">
                <div style="font-size: 48px; margin-bottom: 8px;">✅</div>
                <p id="export-result-text" style="font-weight: 600; font-size: 14px;"></p>
                <p id="export-result-details" style="font-size: 12px; color: #64748b;"></p>
            </div>
        </div>
    </div>
</div>


<script>
jQuery(document).ready(function($) {
    function requestErrorMessage(xhr, fallback) {
        var payload = xhr && xhr.responseJSON;
        var message = payload && payload.data;
        if (message && typeof message.message === 'string') return message.message;
        if (typeof message === 'string' && message) return message;
        if (xhr && (xhr.status === 401 || xhr.status === 403 || String(xhr.responseText || '').trim() === '-1')) {
            return 'Your session or permissions changed. Reload this page and try again.';
        }
        return fallback;
    }

    // Select all checkbox
    $('#select-all-pages').on('change', function() {
        $('input[name="page_ids[]"]').prop('checked', $(this).is(':checked'));
    });

    // SEO/AISEO review workflow
    var seoCurrentPageId = 0;

    function seoEscape(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function seoAttr(value) {
        return seoEscape(value).replace(/"/g, '&quot;');
    }

    function seoList(items) {
        if (!items || !items.length) {
            return '<p style="margin:6px 0;color:#64748b;">None detected.</p>';
        }
        var html = '<ul style="margin:6px 0 0 18px;">';
        items.forEach(function(item) {
            if (typeof item === 'object') {
                html += '<li><code>' + seoEscape(item.src || '') + '</code><br><span>' + seoEscape(item.suggested_alt || '') + '</span></li>';
            } else {
                html += '<li>' + seoEscape(item) + '</li>';
            }
        });
        html += '</ul>';
        return html;
    }

    function seoCard(title, body) {
        return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:10px;">' +
            '<div style="font-weight:600;margin-bottom:6px;">' + seoEscape(title) + '</div>' +
            '<div style="font-size:13px;color:#334155;">' + body + '</div>' +
            '</div>';
    }

    function seoInput(field, label, value, type, help) {
        type = type || 'text';
        return '<label style="display:block;margin-bottom:12px;">' +
            '<span style="display:block;font-weight:600;margin-bottom:5px;">' + seoEscape(label) + '</span>' +
            '<input type="' + seoAttr(type) + '" class="large-text seo-aiseo-field" data-field="' + seoAttr(field) + '" value="' + seoAttr(value || '') + '">' +
            (help ? '<small style="display:block;color:#64748b;margin-top:4px;">' + seoEscape(help) + '</small>' : '') +
            '</label>';
    }

    function seoTextarea(field, label, value, rows, help, codeStyle) {
        return '<label style="display:block;margin-bottom:12px;">' +
            '<span style="display:block;font-weight:600;margin-bottom:5px;">' + seoEscape(label) + '</span>' +
            '<textarea class="large-text seo-aiseo-field" data-field="' + seoAttr(field) + '" rows="' + parseInt(rows || 3, 10) + '"' +
            (codeStyle ? ' style="font-family:Consolas,Monaco,monospace;font-size:12px;"' : '') + '>' + seoEscape(value || '') + '</textarea>' +
            (help ? '<small style="display:block;color:#64748b;margin-top:4px;">' + seoEscape(help) + '</small>' : '') +
            '</label>';
    }

    function seoReadList(items) {
        return seoCard('Review Notes', '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">' +
            '<div><strong>Content Gaps</strong>' + seoList(items.content_gaps || []) + '</div>' +
            '<div><strong>Heading Fixes</strong>' + seoList(items.heading_fixes || []) + '</div>' +
            '<div><strong>Image Alt Text Fixes</strong>' + seoList(items.alt_text_fixes || []) + '</div>' +
            '<div><strong>Detected Sections</strong>' + seoList(items.sections || []) + '</div>' +
            '</div>');
    }

    function seoRenderReview(state) {
        var rec = state.recommendations || {};
        var html = '';
        html += '<div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">';
        html += '<span class="status-badge status-' + seoEscape(state.status || 'unscanned') + '">' + seoEscape(state.label || state.status || 'Review') + '</span>';
        if (typeof rec.score !== 'undefined') {
            html += '<strong style="font-size:14px;">Score: ' + parseInt(rec.score, 10) + '/100</strong>';
        }
        if (state.scanned_at) {
            html += '<span style="color:#64748b;font-size:12px;">Scanned: ' + seoEscape(state.scanned_at) + '</span>';
        }
        html += '</div>';

        html += '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:12px;">';
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
        html += seoInput('title', 'SEO Title', rec.title || '', 'text', 'Rendered in the page <title> and social title tags.');
        html += seoInput('canonical_url', 'Canonical URL', rec.canonical_url || '', 'url', 'Leave as the page URL unless this page has a preferred canonical.');
        html += seoTextarea('meta_description', 'Meta Description', rec.meta_description || '', 3, 'Rendered as the search/social description.');
        html += seoInput('robots', 'Robots', rec.robots || '', 'text', 'Example: index, follow, max-image-preview:large.');
        html += seoInput('og_image', 'Social Image URL', rec.og_image || '', 'url', 'Used for Open Graph and Twitter image tags.');
        html += seoTextarea('ai_summary', 'AI Summary', rec.ai_summary || '', 3, 'A concise answer-engine summary for this page.');
        html += '</div>';
        html += seoTextarea('target_queries', 'Target Queries', (rec.target_queries || []).join('\n'), 3, 'One query per line, or comma-separated.');
        html += seoTextarea('schema_json', 'Schema JSON-LD', rec.schema_json || '', 8, 'Must be valid JSON. Leave blank to disable custom schema for this page.', true);
        html += '</div>';

        html += seoReadList(rec);

        $('#seo-aiseo-result').html(html);
    }

    function seoFormRecommendations() {
        var payload = {};
        $('#seo-aiseo-result .seo-aiseo-field').each(function() {
            var field = $(this).data('field');
            var value = $(this).val();
            if (field === 'target_queries') {
                payload[field] = String(value || '').split(/[\n,]+/).map(function(item) {
                    return item.trim();
                }).filter(Boolean);
            } else {
                payload[field] = value;
            }
        });
        return payload;
    }

    function seoLoadReview(pageId) {
        seoCurrentPageId = pageId;
        $('#seo-aiseo-loading').show();
        $('#seo-aiseo-result, #seo-aiseo-actions').hide();
        $('#seo-aiseo-modal').show();

        $.post(ajaxurl, {
            action: 'ugentic_seo_get_review',
            nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
            page_id: pageId
        }, function(response) {
            $('#seo-aiseo-loading').hide();
            if (response.success) {
                seoRenderReview(response.data.state || {});
                $('#seo-aiseo-result, #seo-aiseo-actions').show();
            } else {
                UgenticToast.error(response.data.message || 'Unable to load SEO review.');
                $('#seo-aiseo-modal').hide();
            }
        }).fail(function(xhr) {
            $('#seo-aiseo-loading').hide();
            UgenticToast.error(requestErrorMessage(xhr, 'Network error while loading SEO review.'));
            $('#seo-aiseo-modal').hide();
        });
    }

    function seoScanPage(pageId, reloadAfter) {
        $.post(ajaxurl, {
            action: 'ugentic_seo_scan_page',
            nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
            page_id: pageId
        }, function(response) {
            if (response.success) {
                UgenticToast.success(response.data.message || 'SEO/AISEO scan complete.');
                if (reloadAfter) {
                    location.reload();
                } else {
                    seoLoadReview(pageId);
                }
            } else {
                UgenticToast.error(response.data.message || 'SEO/AISEO scan failed.');
            }
        }).fail(function(xhr) {
            UgenticToast.error(requestErrorMessage(xhr, 'Network error during SEO/AISEO scan.'));
        });
    }

    $('.ugentic-seo-review-btn').on('click', function() {
        seoLoadReview($(this).data('id'));
    });

    $('.ugentic-seo-rescan-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Scanning...');
        seoScanPage($btn.data('id'), true);
    });

    $('#seo-aiseo-rescan-current').on('click', function() {
        if (seoCurrentPageId) {
            seoScanPage(seoCurrentPageId, false);
        }
    });

    $('#seo-aiseo-apply-current').on('click', function() {
        if (!seoCurrentPageId) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');
        $.post(ajaxurl, {
            action: 'ugentic_seo_save_manual',
            nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
            page_id: seoCurrentPageId,
            recommendations: seoFormRecommendations()
        }, function(response) {
            $btn.prop('disabled', false).text('Save SEO Settings');
            if (response.success) {
                UgenticToast.success(response.data.message || 'SEO settings saved.');
                location.reload();
            } else {
                UgenticToast.error(response.data.message || 'Unable to save SEO settings.');
            }
        }).fail(function(xhr) {
            $btn.prop('disabled', false).text('Save SEO Settings');
            UgenticToast.error(requestErrorMessage(xhr, 'Network error while saving SEO settings.'));
        });
    });

    $('#ugentic-seo-apply-all-btn').on('click', function() {
        UgenticConfirm.show({
            title: 'Apply SEO/AISEO Recommendations',
            message: 'Apply all current review-ready SEO/AISEO recommendations to Tahmir AI pages?',
            confirmText: 'Apply All',
            type: 'primary'
        }).then(function() {
            $.post(ajaxurl, {
                action: 'ugentic_seo_apply_bulk',
                nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>'
            }, function(response) {
                if (response.success) {
                    UgenticToast.success(response.data.message || 'Bulk SEO/AISEO apply complete.');
                    location.reload();
                } else {
                    UgenticToast.error(response.data.message || 'Bulk apply failed.');
                }
            });
        }).catch(function() {});
    });

    $('#ugentic-seo-rescan-all-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Scanning...');
        $.post(ajaxurl, {
            action: 'ugentic_seo_rescan_all',
            nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>'
        }, function(response) {
            if (response.success) {
                UgenticToast.success(response.data.message || 'SEO/AISEO re-scan complete.');
                location.reload();
            } else {
                $btn.prop('disabled', false).text('Re-scan All SEO/AISEO');
                UgenticToast.error(response.data.message || 'Re-scan failed.');
            }
        }).fail(function(xhr) {
            $btn.prop('disabled', false).text('Re-scan All SEO/AISEO');
            UgenticToast.error(requestErrorMessage(xhr, 'Network error during re-scan.'));
        });
    });
    
    // Delete single page
    $('.delete-page-btn').on('click', function() {
        var pageId = $(this).data('id');
        var wpId = $(this).data('wp-id');
        var $row = $(this).closest('tr');
        
        UgenticConfirm.show({
            title: 'Delete Page',
            message: 'Are you sure you want to delete this page? This will also delete the WordPress page.',
            confirmText: 'Delete',
            type: 'danger'
        }).then(function () {
            $.post(ajaxurl, {
                action: 'ugentic_delete_page',
                nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
                page_id: pageId,
                wp_page_id: wpId
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() { $(this).remove(); });
                    UgenticToast.success(response.data.message || 'Page deleted successfully!');
                } else {
                    UgenticToast.error(response.data.message || 'Error deleting page');
                }
            });
        }).catch(function () {});
    });
    
    // Delete selected pages
    $('#delete-selected-btn').on('click', function() {
        var selected = $('input[name="page_ids[]"]').filter(':checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selected.length === 0) {
            UgenticToast.warning('Please select at least one page to delete.');
            return;
        }
        
        UgenticConfirm.show({
            title: 'Delete ' + selected.length + ' Pages',
            message: 'This will delete ' + selected.length + ' selected pages and their associated WordPress pages.',
            confirmText: 'Delete All',
            type: 'danger'
        }).then(function () {
            $.post(ajaxurl, {
                action: 'ugentic_delete_pages_bulk',
                nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
                page_ids: selected
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    UgenticToast.error(response.data.message || 'Error deleting pages');
                }
            });
        }).catch(function () {});
    });
    

    // Dynamic Content: Analyze button
    $('.analyze-dynamic-btn').on('click', function() {
        var pageId = $(this).data('id');
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        // Show modal
        $('#dynamic-analysis-modal').show();
        $('#dynamic-analysis-loading').show();
        $('#dynamic-analysis-result').hide();
        
        $.post(ajaxurl, {
            action: 'ugentic_analyze_dynamic_page',
            nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
            page_id: pageId
        }, function(response) {
            $btn.prop('disabled', false);
            $('#dynamic-analysis-loading').hide();
            var $result = $('#dynamic-analysis-result');
            
            if (response.success && response.data.content_type) {
                var d = response.data;
                var fieldsHtml = (d.fields || []).map(function(f) {
                    return '<li><strong>' + f.label + '</strong> <code>' + f.key + '</code> (' + f.type + ')</li>';
                }).join('');
                
                $result.html(
                    '<div style="padding: 10px; background: #f0f3ff; border-radius: 8px; margin-bottom: 15px;">' +
                    '<h3 style="margin-top:0;"><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> Pattern Detected: ' + d.label + '</h3>' +
                    '<p>Found <strong>' + (d.item_count || '?') + '</strong> repeating items. ' +
                    (d.ai_powered ? '<span style="color:#f97316;">AI-powered analysis</span>' : 'Pattern-based detection') + '</p>' +
                    '<h4>Detected Fields:</h4><ul>' + fieldsHtml + '</ul>' +
                    '</div>' +
                    '<p>This will create a <strong>' + d.label + '</strong> Custom Post Type in your WordPress admin, allowing you to manage entries dynamically.</p>' +
                    '<button type="button" class="button button-primary button-large" id="confirm-convert-dynamic" data-page-id="' + pageId + '" data-analysis=\'' + JSON.stringify(d).replace(/'/g, '&#39;') + '\'>' +
                    '<span class="dashicons dashicons-database-add" style="margin-top:4px;"></span> Convert to Dynamic Content</button>'
                ).show();
                
                // Bind convert button
                $('#confirm-convert-dynamic').off('click').on('click', function() {
                    var $cBtn = $(this);
                    $cBtn.prop('disabled', true).text('Converting...');
                    
                    $.post(ajaxurl, {
                        action: 'ugentic_convert_to_dynamic',
                        nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
                        page_id: $cBtn.data('page-id'),
                        analysis: JSON.stringify(d)
                    }, function(resp) {
                        if (resp.success) {
                            UgenticToast.success(resp.data.message);
                            location.reload();
                        } else {
                            UgenticToast.error(resp.data.message || 'Conversion failed');
                            $cBtn.prop('disabled', false).text('Convert to Dynamic Content');
                        }
                    });
                });
            } else {
                $result.html(
                    '<div style="padding: 20px; text-align: center; color: #666;">' +
                    '<span class="dashicons dashicons-info" style="font-size: 36px; width: 36px; height: 36px; color: #ccc;"></span>' +
                    '<p>' + (response.data.reason || response.data.message || 'No repeating content patterns found on this page.') + '</p>' +
                    '</div>'
                ).show();
            }
        }).fail(function(xhr) {
            $btn.prop('disabled', false);
            $('#dynamic-analysis-loading').hide();
            $('#dynamic-analysis-result').html('<p style="color:red;">Failed to analyze page.</p>').show();
        });
    });
    
    // Remove dynamic
    $('.remove-dynamic-btn').on('click', function() {
        var btn = $(this);
        var pageId = btn.data('id');
        UgenticConfirm.show({
            title: 'Remove Dynamic Content',
            message: 'Remove dynamic content from this page? The CPT and its entries will remain.',
            confirmText: 'Remove',
            type: 'danger'
        }).then(function () {
            $.post(ajaxurl, {
                action: 'ugentic_remove_dynamic',
                nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
                page_id: pageId
            }, function(response) {
                if (response.success) {
                    UgenticToast.success(response.data.message);
                    location.reload();
                } else {
                    UgenticToast.error(response.data.message || 'Error');
                }
            });
        }).catch(function () {});
    });

    // Webhook Settings
    var currentWebhookPageId = 0;
    var currentWebhooks = [];

    $('.webhook-settings-btn').on('click', function() {
        currentWebhookPageId = $(this).data('id');
        try {
            currentWebhooks = JSON.parse(JSON.stringify($(this).data('webhooks') || []));
        } catch(e) {
            currentWebhooks = [];
        }
        renderWebhooksAdmin();
        $('#webhook-settings-modal').show();
    });

    function renderWebhooksAdmin() {
        var $list = $('#webhooks-list-admin');
        $list.empty();
        if (currentWebhooks.length === 0) {
            $list.html('<div style="padding: 20px; text-align: center; color: #999; background: #f9f9f9; border-radius: 8px;"><p>No webhooks configured yet. Click "Add Webhook" to get started.</p></div>');
            return;
        }
        currentWebhooks.forEach(function(wh, i) {
            var html = '<div class="wh-item" data-index="' + i + '" style="padding: 12px; margin-bottom: 10px; background: #f9f9f9; border-radius: 8px; border: 1px solid #ddd;">' +
                '<div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px;">' +
                    '<span style="background: #f97316; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">POST</span>' +
                    '<input type="text" class="wh-url regular-text" value="' + (wh.url || '') + '" placeholder="https://hooks.zapier.com/..." style="flex: 1;" />' +
                    '<button type="button" class="button button-small wh-remove" style="color: #dc3545;">&times;</button>' +
                '</div>' +
                '<div style="display: flex; gap: 15px; padding-left: 4px;">' +
                    '<label style="font-size: 12px;"><input type="checkbox" class="wh-enabled"' + (wh.enabled !== false ? ' checked' : '') + '> Enabled</label>' +
                    '<label style="font-size: 12px;"><input type="checkbox" class="wh-include-meta"' + (wh.include_meta ? ' checked' : '') + '> Include IP/User Agent</label>' +
                '</div>' +
            '</div>';
            $list.append(html);
        });

        // Bind events
        $list.find('.wh-remove').off('click').on('click', function() {
            var idx = $(this).closest('.wh-item').data('index');
            currentWebhooks.splice(idx, 1);
            renderWebhooksAdmin();
        });
        $list.find('.wh-url, .wh-enabled, .wh-include-meta').off('change').on('change', function() {
            var $item = $(this).closest('.wh-item');
            var idx = $item.data('index');
            currentWebhooks[idx] = {
                url: $item.find('.wh-url').val(),
                enabled: $item.find('.wh-enabled').is(':checked'),
                include_meta: $item.find('.wh-include-meta').is(':checked'),
            };
        });
    }

    $('#add-webhook-admin').on('click', function() {
        currentWebhooks.push({ url: '', enabled: true, include_meta: false });
        renderWebhooksAdmin();
        // Focus the new URL input
        $('#webhooks-list-admin .wh-item:last .wh-url').focus();
    });

    $('#save-webhooks-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');

        // Sync from UI before saving
        $('#webhooks-list-admin .wh-item').each(function(i) {
            currentWebhooks[i] = {
                url: $(this).find('.wh-url').val(),
                enabled: $(this).find('.wh-enabled').is(':checked'),
                include_meta: $(this).find('.wh-include-meta').is(':checked'),
            };
        });

        $.post(ajaxurl, {
            action: 'ugentic_save_faithful_form_settings',
            nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
            page_id: currentWebhookPageId,
            webhooks: JSON.stringify(currentWebhooks)
        }, function(response) {
            $btn.prop('disabled', false).text('Save Webhooks');
            if (response.success) {
                UgenticToast.success(response.data.message);
                // Update the button's data attribute
                $('tr[data-page-id="' + currentWebhookPageId + '"] .webhook-settings-btn').data('webhooks', response.data.webhooks);
                $('#webhook-settings-modal').hide();
            } else {
                UgenticToast.error(response.data.message || 'Error saving webhooks');
            }
        }).fail(function(xhr) {
            $btn.prop('disabled', false).text('Save Webhooks');
            UgenticToast.error(requestErrorMessage(xhr, 'Network error. Please try again.'));
        });
    });

    // ── Design DNA ──
    var currentDnaPageId = 0;

    $('.view-dna-btn').on('click', function() {
        currentDnaPageId = $(this).data('id');
        $('#dna-loading').show();
        $('#dna-result').hide();
        $('#dna-modal').show();
        fetchDna(false);
    });

    $('#regenerate-dna-btn').on('click', function() {
        $('#dna-loading').show();
        $('#dna-result').hide();
        fetchDna(true);
    });

    function fetchDna(regenerate) {
        $.post(ajaxurl, {
            action: 'ugentic_generate_page_dna',
            nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
            page_id: currentDnaPageId,
            regenerate: regenerate ? 1 : 0
        }, function(response) {
            $('#dna-loading').hide();
            if (response.success && response.data.dna) {
                renderDna(response.data.dna, response.data.cached);
            } else {
                $('#dna-result').html('<p style="color:#dc3545;">' + (response.data && response.data.message ? response.data.message : 'Error extracting DNA') + '</p>').show();
            }
        }).fail(function(xhr) {
            $('#dna-loading').hide();
            $('#dna-result').html('<p style="color:#dc3545;">Network error. Please try again.</p>').show();
        });
    }

    function renderDna(dna, cached) {
        var html = '';
        if (cached) {
            html += '<p style="font-size:12px;color:#888;margin-bottom:12px;">📦 Loaded from cache — generated ' + (dna.generated_at || 'during import') + '</p>';
        }

        // Framework & convention
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">';
        html += dnaCard('🏗️ CSS Framework', dna.css_framework || '—');
        html += dnaCard('📐 Class Convention', dna.class_convention || '—');
        html += dnaCard('🎨 Icon Library', dna.icon_library || '—');
        html += '</div>';

        // Colors
        if (dna.colors) {
            html += '<h4 style="margin:12px 0 8px;">🎨 Color Palette</h4>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">';
            if (dna.colors.primary) html += colorSwatch(dna.colors.primary, 'Primary');
            if (dna.colors.secondary) html += colorSwatch(dna.colors.secondary, 'Secondary');
            (dna.colors.accents || []).forEach(function(c) { html += colorSwatch(c); });
            (dna.colors.backgrounds || []).forEach(function(c) { html += colorSwatch(c); });
            (dna.colors.text || []).forEach(function(c) { html += colorSwatch(c); });
            html += '</div>';
        }

        // Typography
        if (dna.typography) {
            html += '<h4 style="margin:12px 0 8px;">🔤 Typography</h4>';
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">';
            if (dna.typography.heading_font) html += dnaCard('Heading Font', dna.typography.heading_font);
            if (dna.typography.body_font) html += dnaCard('Body Font', dna.typography.body_font);
            if (dna.typography.body_size) html += dnaCard('Body Size', dna.typography.body_size);
            if (dna.typography.heading_sizes && dna.typography.heading_sizes.length) html += dnaCard('Heading Sizes', dna.typography.heading_sizes.join(', '));
            html += '</div>';
        }

        // Spacing
        if (dna.spacing && (dna.spacing.section_padding || dna.spacing.container_max_width)) {
            html += '<h4 style="margin:12px 0 8px;">📏 Spacing</h4>';
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">';
            if (dna.spacing.section_padding) html += dnaCard('Section Padding', dna.spacing.section_padding);
            if (dna.spacing.container_max_width) html += dnaCard('Container Width', dna.spacing.container_max_width);
            html += '</div>';
        }

        // Sections
        if (dna.sections && dna.sections.length) {
            html += '<h4 style="margin:12px 0 8px;">📋 Detected Sections</h4>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;">';
            dna.sections.forEach(function(s) {
                html += '<span style="background:#f0f0f5;padding:3px 10px;border-radius:12px;font-size:12px;">' + s + '</span>';
            });
            html += '</div>';
        }

        // Components
        if (dna.components) {
            var compItems = [];
            if (dna.components.buttons && dna.components.buttons.length) compItems.push('Buttons: .' + dna.components.buttons.join(', .'));
            if (dna.components.cards && dna.components.cards.length) compItems.push('Cards: .' + dna.components.cards.join(', .'));
            if (dna.components.nav && dna.components.nav.length) compItems.push('Nav: .' + dna.components.nav.join(', .'));
            if (compItems.length) {
                html += '<h4 style="margin:12px 0 8px;">🧩 Components</h4>';
                html += '<div style="font-size:12px;background:#f9f9f9;padding:10px;border-radius:6px;font-family:monospace;">' + compItems.join('<br>') + '</div>';
            }
        }

        // CSS Variables (collapsible)
        if (dna.css_variables && Object.keys(dna.css_variables).length) {
            var varKeys = Object.keys(dna.css_variables);
            html += '<details style="margin-top:12px;"><summary style="cursor:pointer;font-weight:600;">🔧 CSS Variables (' + varKeys.length + ')</summary>';
            html += '<div style="font-size:11px;background:#f9f9f9;padding:10px;border-radius:6px;font-family:monospace;margin-top:6px;max-height:200px;overflow-y:auto;">';
            varKeys.forEach(function(k) {
                html += '<div>' + k + ': ' + dna.css_variables[k] + '</div>';
            });
            html += '</div></details>';
        }

        $('#dna-result').html(html).show();
    }

    function dnaCard(label, value) {
        return '<div style="background:#fff7ed;padding:8px 12px;border-radius:8px;border-left:3px solid #f97316;">' +
            '<div style="font-size:10px;text-transform:uppercase;color:#f97316;font-weight:600;">' + label + '</div>' +
            '<div style="font-size:13px;margin-top:2px;">' + value + '</div></div>';
    }

    function colorSwatch(hex, label) {
        return '<div style="display:inline-flex;align-items:center;gap:4px;background:#f9f9f9;padding:3px 8px;border-radius:6px;font-size:11px;">' +
            '<span style="width:16px;height:16px;border-radius:4px;background:' + hex + ';border:1px solid #ddd;display:inline-block;"></span>' +
            (label ? '<strong>' + label + '</strong> ' : '') + hex + '</div>';
    }

    // ── Freedom Export ──────────────────────────────────────────────────────
    var exportPageId = 0;

    // Single page export button
    $('.export-page-btn').on('click', function() {
        exportPageId = $(this).data('id');
        var title = $(this).data('title') || 'Untitled';
        $('#export-page-title').text(title);
        $('#export-single-section').show();
        $('#export-site-section, #export-progress, #export-result').hide();
        $('#export-modal').show();
    });

    // Site export button
    $('#export-site-btn').on('click', function() {
        $('#export-site-section').show();
        $('#export-single-section, #export-progress, #export-result').hide();
        $('#export-modal').show();
    });

    // Download single page HTML
    $('#do-export-page').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Exporting...');
        $('#export-single-section').hide();
        $('#export-progress').show();
        $('#export-progress-text').text('Cleaning HTML and stripping WordPress dependencies...');

        $.post(ajaxurl, {
            action: 'ugentic_export_page_html',
            nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>',
            page_id: exportPageId,
            inline_css: $('#export-inline-css').is(':checked') ? 1 : 0
        }, function(response) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span> Download HTML');
            $('#export-progress').hide();

            if (response.success) {
                // Trigger browser download
                var blob = new Blob([response.data.html], { type: 'text/html;charset=utf-8' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                $('#export-result-text').text('Page exported successfully!');
                $('#export-result-details').text(response.data.filename + ' — ' + (response.data.html.length / 1024).toFixed(1) + ' KB');
                $('#export-result').show();
            } else {
                UgenticToast.error(response.data.message || 'Export failed');
                $('#export-single-section').show();
            }
        }).fail(function(xhr) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span> Download HTML');
            $('#export-progress').hide();
            $('#export-single-section').show();
            UgenticToast.error(requestErrorMessage(xhr, 'Network error. Please try again.'));
        });
    });

    // Download full site ZIP
    $('#do-export-site').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Exporting...');
        $('#export-site-section').hide();
        $('#export-progress').show();
        $('#export-progress-text').text('Downloading images and building ZIP archive...');

        // Animate progress text since this can take a while
        var dots = 0;
        var progressMsgs = [
            'Downloading images and building ZIP archive',
            'Collecting CSS stylesheets',
            'Converting internal links',
            'Packaging files'
        ];
        var msgIdx = 0;
        var progressInterval = setInterval(function() {
            dots = (dots + 1) % 4;
            if (dots === 0) msgIdx = Math.min(msgIdx + 1, progressMsgs.length - 1);
            $('#export-progress-text').text(progressMsgs[msgIdx] + '.'.repeat(dots + 1));
        }, 800);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 900000, // 15 min for large multi-page sites and asset localization
            data: {
                action: 'ugentic_export_site_zip',
                nonce: '<?php echo esc_attr(wp_create_nonce('ugentic_admin_nonce')); ?>'
            },
            success: function(response) {
            clearInterval(progressInterval);
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span> Download ZIP');
            $('#export-progress').hide();

            if (response.success) {
                // Trigger browser download
                var a = document.createElement('a');
                a.href = response.data.download_url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);

                $('#export-result-text').text('Site exported successfully!');
                var exportDetails = response.data.page_count + ' of ' + response.data.requested_page_count + ' pages' +
                    ' • ' + response.data.asset_count + ' local assets' +
                    ' • ' + response.data.size_human + ' • ' + response.data.filename;
                if (response.data.skipped_count) exportDetails += ' • ' + response.data.skipped_count + ' skipped';
                if (response.data.failed_asset_count) exportDetails += ' • ' + response.data.failed_asset_count + ' asset warnings';
                $('#export-result-details').text(exportDetails);
                $('#export-result').show();
            } else {
                UgenticToast.error(response.data.message || 'Export failed');
                $('#export-site-section').show();
            }
        },
        error: function() {
            clearInterval(progressInterval);
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: text-bottom;"></span> Download ZIP');
            $('#export-progress').hide();
            $('#export-site-section').show();
            UgenticToast.error('Export did not finish. Nothing was silently skipped; please retry or check export-manifest.json from the last completed archive.');
        }
        });
    });

    // Modal close handlers
    $('.close-modal').on('click', function() {
        $(this).closest('.ugentic-modal').hide();
    });
});
</script>
