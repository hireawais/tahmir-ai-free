<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Forms Workspace
 * Dense form list with search, status, source mode, and entry stats.
 */

$total_forms = count($forms);
$active_forms = 0;
$total_entries = 0;
foreach ($forms as $f) {
    if ($f->is_active) $active_forms++;
    $total_entries += intval($f->submissions_count ?? 0);
}
$nonce = wp_create_nonce('ugentic_admin_nonce');
?>

<div class="wrap ugentic-wrap uf-forms-page" id="uf-forms-page">
    <!-- Workspace Header -->
    <div class="uf-header">
        <div class="uf-header__mesh"></div>
        <div class="uf-header__content">
            <div class="uf-header__left">
                <div class="uf-header__icon">
                    <img src="<?php echo esc_url(UGENTIC_BRAND_LOGO_URL); ?>" alt="<?php esc_attr_e('Tahmir AI', 'tahmir-ai-to-wp-converter'); ?>">
                </div>
                <div>
                    <h1 class="uf-header__title"><?php esc_html_e('Forms', 'tahmir-ai-to-wp-converter'); ?></h1>
                    <p class="uf-header__subtitle"><?php esc_html_e('Collect submissions and view your form entries', 'tahmir-ai-to-wp-converter'); ?></p>
                </div>
            </div>
            <div class="uf-header__right">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-form-entries')); ?>" class="uf-btn">
                    <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                    <?php esc_html_e('View Entries', 'tahmir-ai-to-wp-converter'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-forms&action=new')); ?>" class="uf-btn uf-btn--primary" id="uf-create-form">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php esc_html_e('Create Form', 'tahmir-ai-to-wp-converter'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="uf-stats">
        <div class="uf-stat" style="--delay:0">
            <div class="uf-stat__value" data-count="<?php echo esc_attr($total_forms); ?>">0</div>
            <div class="uf-stat__label"><?php esc_html_e('Total Forms', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
        <div class="uf-stat" style="--delay:1">
            <div class="uf-stat__value" data-count="<?php echo esc_attr($active_forms); ?>">0</div>
            <div class="uf-stat__label"><?php esc_html_e('Active', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
        <div class="uf-stat" style="--delay:2">
            <div class="uf-stat__value" data-count="<?php echo esc_attr($total_entries); ?>">0</div>
            <div class="uf-stat__label"><?php esc_html_e('Total Entries', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
        <div class="uf-stat" style="--delay:3">
            <div class="uf-stat__value"><?php echo esc_html($total_forms > 0 ? round($total_entries / $total_forms, 1) : 0); ?></div>
            <div class="uf-stat__label"><?php esc_html_e('Avg Entries/Form', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="uf-toolbar">
        <div class="uf-toolbar__search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="uf-search" placeholder="<?php esc_html_e('Search forms...', 'tahmir-ai-to-wp-converter'); ?>">
        </div>
        <div class="uf-toolbar__right">
            <div class="uf-bulk-controls" aria-label="<?php esc_attr_e('Bulk form actions', 'tahmir-ai-to-wp-converter'); ?>">
                <label class="uf-select-all" for="uf-select-all">
                    <input type="checkbox" id="uf-select-all">
                    <span><?php esc_html_e('Select all', 'tahmir-ai-to-wp-converter'); ?></span>
                </label>
                <select id="uf-bulk-action" aria-label="<?php esc_attr_e('Choose a bulk action', 'tahmir-ai-to-wp-converter'); ?>">
                    <option value=""><?php esc_html_e('Bulk actions', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete selected', 'tahmir-ai-to-wp-converter'); ?></option>
                </select>
                <button type="button" class="uf-btn uf-btn--secondary" id="uf-apply-bulk" disabled><?php esc_html_e('Apply', 'tahmir-ai-to-wp-converter'); ?></button>
                <span class="uf-selected-count" id="uf-selected-count" aria-live="polite"></span>
            </div>
            <div class="uf-view-toggle">
                <button class="uf-view-btn active" data-view="grid" title="Grid View">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                </button>
                <button class="uf-view-btn" data-view="list" title="List View">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </button>
            </div>
        </div>
    </div>

    <?php if (empty($forms)): ?>
    <!-- Empty State -->
    <div class="uf-empty">
        <div class="uf-empty__visual">
            <div class="uf-empty__rings">
                <div class="uf-empty__ring"></div>
                <div class="uf-empty__ring"></div>
                <div class="uf-empty__ring"></div>
            </div>
            <div class="uf-empty__icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
            </div>
        </div>
        <h2><?php esc_html_e('Import a website with a form', 'tahmir-ai-to-wp-converter'); ?></h2>
        <p><?php esc_html_e('Forms in your imported website collect submissions here. Form Builder and field editing are available in Pro.', 'tahmir-ai-to-wp-converter'); ?></p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-forms&action=new')); ?>" class="uf-btn uf-btn--primary uf-btn--lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?php esc_html_e('Create Form', 'tahmir-ai-to-wp-converter'); ?>
        </a>
    </div>
    <?php else: ?>

    <!-- Forms Grid -->
    <div class="uf-forms-grid" id="uf-forms-grid">
        <?php foreach ($forms as $index => $form): 
            $entry_count = intval($form->submissions_count ?? 0);
            $created = date_i18n('M j, Y', strtotime($form->created_at));
            $shortcode = '[ugentic_form id="' . $form->id . '"]';
            $settings = is_array($form->settings ?? null) ? $form->settings : (json_decode($form->settings ?? '[]', true) ?: []);
            $source_mode = $settings['source_mode'] ?? (!empty($settings['faithful_source']) ? 'faithful' : 'native');
            $preserved = in_array($source_mode, ['faithful', 'external', 'plugin'], true) || (($settings['preservation_mode'] ?? '') === 'preserve');
            $ghl_enabled = !empty($settings['ghl']['enabled']);
        ?>
        <div class="uf-card" data-form-id="<?php echo absint($form->id); ?>" data-title="<?php echo esc_attr(strtolower($form->title)); ?>" style="--index:<?php echo esc_attr($index); ?>">
            <div class="uf-card__header">
                <label class="uf-card__select" title="<?php esc_attr_e('Select form', 'tahmir-ai-to-wp-converter'); ?>">
                    <input type="checkbox" class="uf-form-checkbox" value="<?php echo intval($form->id); ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: Form title. */ esc_html__('Select %s', 'tahmir-ai-to-wp-converter'), $form->title)); ?>">
                    <span aria-hidden="true"></span>
                </label>
                <div class="uf-card__status <?php echo $form->is_active ? 'uf-card__status--active' : 'uf-card__status--inactive'; ?>">
                    <span class="uf-card__status-dot"></span>
                    <?php echo $form->is_active ? esc_html__('Active', 'tahmir-ai-to-wp-converter') : esc_html__('Inactive', 'tahmir-ai-to-wp-converter'); ?>
                </div>
                <div class="uf-card__source <?php echo $preserved ? 'uf-card__source--preserved' : ''; ?>">
                    <?php echo esc_html(ucfirst($source_mode)); ?>
                </div>
                <?php if ($ghl_enabled): ?>
                <div class="uf-card__integration"><?php esc_html_e('GHL connected', 'tahmir-ai-to-wp-converter'); ?></div>
                <?php endif; ?>
                <div class="uf-card__menu">
                    <button class="uf-card__menu-btn" title="More actions">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </button>
                    <div class="uf-card__dropdown">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-form-entries&form_id=' . $form->id)); ?>" class="uf-card__dropdown-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <?php esc_html_e('Edit', 'tahmir-ai-to-wp-converter'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-form-entries&form_id=' . $form->id)); ?>" class="uf-card__dropdown-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            <?php esc_html_e('Entries', 'tahmir-ai-to-wp-converter'); ?>
                        </a>
                        <div class="uf-card__dropdown-divider"></div>
                        <button class="uf-card__dropdown-item uf-card__dropdown-item--danger delete-form" data-form-id="<?php echo absint($form->id); ?>" data-form-title="<?php echo esc_attr($form->title); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            <?php esc_html_e('Delete', 'tahmir-ai-to-wp-converter'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-form-entries&form_id=' . $form->id)); ?>" class="uf-card__body">
                <h3 class="uf-card__title"><?php echo esc_html($form->title); ?></h3>
                <div class="uf-card__meta">
                    <span class="uf-card__meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo esc_attr($created); ?>
                    </span>
                </div>
            </a>

            <div class="uf-card__footer tahmir-form-actions">
                <button type="button" class="uf-btn uf-btn--primary tahmir-edit-form-upgrade" data-tahmir-pro="Form Builder" aria-haspopup="dialog"><?php esc_html_e('Edit Form', 'tahmir-ai-to-wp-converter'); ?><span class="tahmir-pro-badge">Pro</span></button>
                <a class="uf-btn" href="<?php echo esc_url(admin_url('admin.php?page=ugentic-form-entries&form_id=' . absint($form->id))); ?>"><?php esc_html_e('View Entries', 'tahmir-ai-to-wp-converter'); ?></a>
            </div>
            <div class="uf-card__footer">
                <div class="uf-card__entries">
                    <span class="uf-card__entries-count"><?php echo esc_attr($entry_count); ?></span>
                    <span class="uf-card__entries-label"><?php echo $entry_count === 1 ? esc_html__('entry', 'tahmir-ai-to-wp-converter') : esc_html__('entries', 'tahmir-ai-to-wp-converter'); ?></span>
                </div>
                <div class="uf-card__shortcode" title="<?php esc_html_e('Click to copy shortcode', 'tahmir-ai-to-wp-converter'); ?>" onclick="event.stopPropagation(); navigator.clipboard.writeText('<?php echo esc_js($shortcode); ?>'); this.classList.add('copied'); setTimeout(() => this.classList.remove('copied'), 1500);">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span class="uf-card__shortcode-text"><?php esc_html_e('Copy Shortcode', 'tahmir-ai-to-wp-converter'); ?></span>
                    <span class="uf-card__shortcode-copied"><?php esc_html_e('Copied!', 'tahmir-ai-to-wp-converter'); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<style>
/* ============================
   Forms Workspace UI
   ============================ */

/* Reset & Base */
.uf-forms-page {
    margin: -20px -20px 0;
    min-height: 100vh;
    background: #0f0f14;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #e4e4e7;
    overflow-x: hidden;
}


/* === Header === */
.uf-header {
    position: relative;
    padding: 40px 36px 32px;
    overflow: hidden;
}
.uf-header__mesh {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 80% 60% at 10% 20%, rgba(234, 88, 12, 0.18) 0%, transparent 70%),
        radial-gradient(ellipse 60% 50% at 80% 30%, rgba(59, 130, 246, 0.14) 0%, transparent 70%),
        radial-gradient(ellipse 40% 40% at 50% 80%, rgba(20, 103, 255, 0.10) 0%, transparent 70%);
    pointer-events: none;
}
.uf-header__content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1400px;
    margin: 0 auto;
}
.uf-header__left {
    display: flex;
    align-items: center;
    gap: 18px;
}
.uf-header__icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(234, 88, 12, 0.25), rgba(59, 130, 246, 0.15));
    border: 1px solid rgba(234, 88, 12, 0.3);
    border-radius: 14px;
    color: #fdba74;
    backdrop-filter: blur(10px);
}
.uf-header__title {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: #fafafa;
    line-height: 1.2;
}
.uf-header__subtitle {
    margin: 4px 0 0;
    font-size: 14px;
    color: #71717a;
    font-weight: 400;
}

/* Buttons */
.uf-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 22px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}
.uf-btn--primary {
    background: linear-gradient(135deg, #ea580c, #fb923c);
    color: #fff;
    box-shadow: 0 4px 20px rgba(234, 88, 12, 0.35), inset 0 1px 0 rgba(255,255,255,0.1);
}
.uf-btn--primary:hover {
    background: linear-gradient(135deg, #c2410c, #c2410c);
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(234, 88, 12, 0.45), inset 0 1px 0 rgba(255,255,255,0.15);
    color: #fff;
}
.uf-btn--lg {
    padding: 14px 32px;
    font-size: 15px;
    border-radius: 12px;
}

/* === Stats === */
.uf-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    padding: 0 36px 8px;
    max-width: 1400px;
    margin: 0 auto;
}
.uf-stat {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 20px 22px;
    backdrop-filter: blur(12px);
    animation: uf-slideUp 0.5s ease both;
    animation-delay: calc(var(--delay) * 80ms);
}
.uf-stat__value {
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, #c4b5fd, #93c5fd);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}
.uf-stat__label {
    font-size: 12px;
    color: #71717a;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-top: 4px;
}
@keyframes uf-slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* === Toolbar === */
.uf-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 36px 12px;
    max-width: 1400px;
    margin: 0 auto;
}
.uf-toolbar__search {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 10px 16px;
    min-width: 280px;
    transition: all 0.2s;
    color: #71717a;
}
.uf-toolbar__search:focus-within {
    border-color: rgba(234, 88, 12, 0.5);
    background: rgba(255,255,255,0.07);
    box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.1);
}
.uf-toolbar__search input {
    border: none;
    background: transparent;
    color: #e4e4e7;
    font-size: 14px;
    outline: none;
    width: 100%;
}
.uf-toolbar__search input::placeholder { color: #52525b; }
.uf-toolbar__right { display: flex; align-items: center; gap: 12px; }

.uf-view-toggle {
    display: flex;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    overflow: hidden;
}
.uf-view-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 34px;
    border: none;
    background: transparent;
    color: #71717a;
    cursor: pointer;
    transition: all 0.2s;
}
.uf-view-btn:hover { color: #a1a1aa; }
.uf-view-btn.active {
    background: rgba(234, 88, 12, 0.2);
    color: #fdba74;
}

/* === Forms Grid === */
.uf-forms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 18px;
    padding: 12px 36px 40px;
    max-width: 1400px;
    margin: 0 auto;
}
.uf-forms-grid.list-view {
    grid-template-columns: 1fr;
    gap: 8px;
}

/* === Card === */
.uf-card {
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: uf-slideUp 0.4s ease both;
    animation-delay: calc(var(--index) * 50ms);
    position: relative;
}
.uf-card:hover {
    border-color: rgba(234, 88, 12, 0.3);
    background: rgba(255,255,255,0.055);
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.3), 0 0 0 1px rgba(234, 88, 12, 0.15);
}
.uf-card__header {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
    padding: 16px 18px 0;
}
.uf-card__status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 4px 10px;
    border-radius: 20px;
}
.uf-card__status--active {
    background: rgba(34, 197, 94, 0.12);
    color: #4ade80;
}
.uf-card__status--inactive {
    background: rgba(161, 161, 170, 0.1);
    color: #71717a;
}
.uf-card__source {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 4px 10px;
    border-radius: 20px;
    background: rgba(148, 163, 184, 0.1);
    color: #cbd5e1;
}
.uf-card__source--preserved {
    background: rgba(251, 191, 36, 0.12);
    color: #fbbf24;
}
.uf-card__menu {
    margin-left: auto;
}
.uf-card__status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}
.uf-card__status--active .uf-card__status-dot {
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.6);
    animation: uf-pulse 2s infinite;
}
@keyframes uf-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Card Menu */
.uf-card__menu { position: relative; }
.uf-card__menu-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #52525b;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}
.uf-card__menu-btn:hover {
    background: rgba(255,255,255,0.08);
    color: #a1a1aa;
}
.uf-card__dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    width: 180px;
    background: #1c1c24;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.5);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px) scale(0.96);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 100;
    padding: 6px;
}
.uf-card__menu:hover .uf-card__dropdown,
.uf-card__dropdown:hover {
    opacity: 1;
    visibility: visible;
    transform: translateY(4px) scale(1);
}
.uf-card__dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: #d4d4d8;
    text-decoration: none;
    border-radius: 6px;
    border: none;
    background: none;
    width: 100%;
    cursor: pointer;
    transition: background 0.15s;
}
.uf-card__dropdown-item:hover {
    background: rgba(255,255,255,0.06);
    color: #f4f4f5;
}
.uf-card__dropdown-item--danger { color: #f87171; }
.uf-card__dropdown-item--danger:hover { background: rgba(239, 68, 68, 0.1); }
.uf-card__dropdown-divider {
    height: 1px;
    background: rgba(255,255,255,0.06);
    margin: 4px 0;
}

/* Card Body */
.uf-card__body {
    display: block;
    padding: 18px 18px 14px;
    text-decoration: none;
    color: inherit;
}
.uf-card__body:hover { color: inherit; }
.uf-card__title {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: #fafafa;
    line-height: 1.3;
}
.uf-card__meta {
    display: flex;
    gap: 14px;
    margin-top: 10px;
}
.uf-card__meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #71717a;
}

/* Card Footer */
.uf-card__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px 16px;
    border-top: 1px solid rgba(255,255,255,0.05);
}
.uf-card__entries {
    display: flex;
    align-items: baseline;
    gap: 5px;
}
.uf-card__entries-count {
    font-size: 18px;
    font-weight: 700;
    color: #fdba74;
}
.uf-card__entries-label {
    font-size: 12px;
    color: #71717a;
}
.uf-card__shortcode {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #52525b;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 6px;
    transition: all 0.2s;
}
.uf-card__shortcode:hover {
    background: rgba(255,255,255,0.05);
    color: #a1a1aa;
}
.uf-card__shortcode-copied { display: none; }
.uf-card__shortcode.copied .uf-card__shortcode-text { display: none; }
.uf-card__shortcode.copied .uf-card__shortcode-copied {
    display: inline;
    color: #4ade80;
}
.uf-card__shortcode.copied svg { stroke: #4ade80; }

/* === List View Cards === */
.list-view .uf-card {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    border-radius: 12px;
}
.list-view .uf-card__header { padding: 12px 16px; order: 3; }
.list-view .uf-card__body { padding: 14px 8px 14px 16px; order: 1; }
.list-view .uf-card__footer {
    border-top: none;
    border-left: 1px solid rgba(255,255,255,0.05);
    padding: 12px 16px;
    order: 2;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}
.list-view .uf-card__title { font-size: 15px; }

/* === Empty State === */
.uf-empty {
    text-align: center;
    padding: 80px 20px 100px;
    max-width: 500px;
    margin: 0 auto;
}
.uf-empty__visual {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.uf-empty__icon {
    position: relative;
    z-index: 2;
    color: #ea580c;
}
.uf-empty__rings { position: absolute; inset: 0; }
.uf-empty__ring {
    position: absolute;
    inset: 0;
    border: 1px solid rgba(234, 88, 12, 0.15);
    border-radius: 50%;
    animation: uf-ringPulse 3s ease-in-out infinite;
}
.uf-empty__ring:nth-child(2) {
    inset: -12px;
    animation-delay: 0.5s;
    border-color: rgba(234, 88, 12, 0.1);
}
.uf-empty__ring:nth-child(3) {
    inset: -24px;
    animation-delay: 1s;
    border-color: rgba(234, 88, 12, 0.05);
}
@keyframes uf-ringPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.08); opacity: 0.5; }
}
.uf-empty h2 {
    font-size: 22px;
    font-weight: 700;
    color: #fafafa;
    margin: 0 0 8px;
}
.uf-empty p {
    color: #71717a;
    font-size: 15px;
    margin: 0 0 28px;
    line-height: 1.6;
}

/* === Responsive === */
@media (max-width: 900px) {
    .uf-stats { grid-template-columns: repeat(2, 1fr); }
    .uf-forms-grid { grid-template-columns: 1fr; }
    .uf-header { padding: 28px 20px 24px; }
    .uf-toolbar, .uf-forms-grid, .uf-stats { padding-left: 20px; padding-right: 20px; }
}
@media (max-width: 600px) {
    .uf-header__content { flex-direction: column; gap: 16px; align-items: flex-start; }
    .uf-toolbar { flex-direction: column; gap: 12px; align-items: stretch; }
    .uf-toolbar__search { min-width: unset; }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .uf-card, .uf-stat, .uf-empty__ring { animation: none !important; }
    .uf-card:hover { transform: none; }
    .uf-btn--primary:hover { transform: none; }
    .uf-card__status--active .uf-card__status-dot { animation: none; }
}
</style>



<?php
$forms_dashboard_css_path = UGENTIC_PLUGIN_DIR . 'admin/css/forms-dashboard-v2.css';
$forms_dashboard_css_url = UGENTIC_PLUGIN_URL . 'admin/css/forms-dashboard-v2.css?ver=' . (file_exists($forms_dashboard_css_path) ? filemtime($forms_dashboard_css_path) : UGENTIC_VERSION);
?>


<style id="tahmir-free-form-access">.uf-forms-page .delete-form,.uf-forms-page .uf-bulk-bar,.uf-forms-page .uf-card__select,.uf-forms-page .uf-card__integration,.uf-forms-page .uf-bulk-controls,.uf-forms-page .uf-form-checkbox{display:none!important}</style>
<p class="description"><?php esc_html_e('Forms & Entries: collect submissions and read entries here for free. Form Builder, field editing, form settings, notifications and integrations require Pro.', 'tahmir-ai-to-wp-converter'); ?></p>

<script>
jQuery(document).ready(function($) {
    // Animated counters
    $('.uf-stat__value[data-count]').each(function() {
        var $el = $(this);
        var target = parseInt($el.data('count')) || 0;
        if (target === 0) { $el.text('0'); return; }
        var duration = 800;
        var start = 0;
        var startTime = null;
        function animate(ts) {
            if (!startTime) startTime = ts;
            var progress = Math.min((ts - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            $el.text(Math.floor(eased * target));
            if (progress < 1) requestAnimationFrame(animate);
        }
        requestAnimationFrame(animate);
    });

    // Search
    $('#uf-search').on('input', function() {
        var q = $(this).val().toLowerCase();
        $('.uf-card').each(function() {
            var title = $(this).data('title') || '';
            $(this).toggle(title.indexOf(q) !== -1);
        });
        updateBulkState();
    });

    // View toggle
    $('.uf-view-btn').on('click', function() {
        var view = $(this).data('view');
        $('.uf-view-btn').removeClass('active');
        $(this).addClass('active');
        $('#uf-forms-grid').toggleClass('list-view', view === 'list');
    });

    function selectedFormIds() {
        return $('.uf-form-checkbox:checked').map(function() {
            return String($(this).val());
        }).get();
    }

    function updateBulkState() {
        var ids = selectedFormIds();
        var $visible = $('.uf-card:visible .uf-form-checkbox');
        var visibleChecked = $visible.filter(':checked').length;
        var allVisibleChecked = $visible.length > 0 && visibleChecked === $visible.length;
        $('.uf-card').each(function() {
            $(this).toggleClass('is-selected', $(this).find('.uf-form-checkbox').is(':checked'));
        });
        $('#uf-select-all')
            .prop('checked', allVisibleChecked)
            .prop('indeterminate', visibleChecked > 0 && !allVisibleChecked);
        $('#uf-selected-count').text(ids.length ? ids.length + ' selected' : '');
        $('#uf-apply-bulk').prop('disabled', !ids.length || $('#uf-bulk-action').val() !== 'delete');
    }

    $('#uf-select-all').on('change', function() {
        $('.uf-card:visible .uf-form-checkbox').prop('checked', this.checked);
        updateBulkState();
    });

    $(document).on('change', '.uf-form-checkbox', updateBulkState);
    $('#uf-bulk-action').on('change', updateBulkState);

    $(document).on('click', '.uf-card__select', function(e) {
        e.stopPropagation();
    });

    $('#uf-apply-bulk').on('click', function() {
        var ids = selectedFormIds();
        if ($('#uf-bulk-action').val() !== 'delete' || !ids.length) {
            updateBulkState();
            return;
        }
        if (!confirm('Delete ' + ids.length + (ids.length === 1 ? ' selected form' : ' selected forms') + '? Their entries and delivery logs will also be deleted.')) {
            return;
        }

        var $button = $(this).prop('disabled', true).text('Deleting...');
        $.post(ajaxurl, {
            action: 'ugentic_bulk_delete_forms',
            form_ids: ids,
            nonce: '<?php echo esc_js($nonce); ?>'
        }).done(function(response) {
            if (response && response.success) {
                location.reload();
                return;
            }
            alert(response && response.data && response.data.message ? response.data.message : 'The selected forms could not be deleted.');
        }).fail(function() {
            alert('The delete request could not be completed. Please check your connection and try again.');
        }).always(function() {
            $button.text('Apply');
            updateBulkState();
        });
    });

    // Delete form
    $(document).on('click', '.delete-form', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $button = $(this);
        var formId = $button.data('form-id');
        var formTitle = $button.data('form-title');
        if (!confirm('Delete "' + formTitle + '"? Its entries and delivery logs will also be deleted.')) {
            return;
        }

        $button.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'ugentic_delete_form',
            form_id: formId,
            nonce: '<?php echo esc_js($nonce); ?>'
        }).done(function(response) {
            if (response && response.success) {
                location.reload();
                return;
            }
            alert(response && response.data && response.data.message ? response.data.message : 'The form could not be deleted.');
        }).fail(function() {
            alert('The delete request could not be completed. Please check your connection and try again.');
        }).always(function() {
            $button.prop('disabled', false);
        });
    });

    updateBulkState();
});
</script>
<?php include UGENTIC_PLUGIN_DIR . 'admin/views/form-upgrade.php'; ?>
