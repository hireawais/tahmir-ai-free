<?php
/**
 * Change History Admin View
 * Shows the last 3 days of changes across all pages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Human-readable action labels
$action_labels = [
    'ai_edit' => esc_html__('AI Edit', 'tahmir-ai-to-wp-converter'),
    'manual' => esc_html__('Manual Save', 'tahmir-ai-to-wp-converter'),
    'full_save' => esc_html__('Manual Save', 'tahmir-ai-to-wp-converter'),
    'save' => esc_html__('Save', 'tahmir-ai-to-wp-converter'),
    'revert' => esc_html__('Revert', 'tahmir-ai-to-wp-converter'),
    'inline_edit' => esc_html__('Inline Edit', 'tahmir-ai-to-wp-converter'),
    'generate' => esc_html__('AI Generate', 'tahmir-ai-to-wp-converter'),
    'import' => esc_html__('Import', 'tahmir-ai-to-wp-converter'),
    'delete' => esc_html__('Delete', 'tahmir-ai-to-wp-converter'),
];

// Action badge CSS classes
$action_badge_class = [
    'ai_edit' => 'ug-action-badge--ai-edit',
    'manual' => 'ug-action-badge--manual-save',
    'full_save' => 'ug-action-badge--full-save',
    'save' => 'ug-action-badge--manual-save',
    'revert' => 'ug-action-badge--revert',
    'inline_edit' => 'ug-action-badge--ai-edit',
    'generate' => 'ug-action-badge--generate',
    'import' => 'ug-action-badge--import',
    'delete' => 'ug-action-badge--delete',
];

// Collect unique values for filters
$unique_pages = [];
$unique_users = [];
$unique_actions = [];
if (!empty($history)) {
    foreach ($history as $entry) {
        $pt = $entry->page_title ?: '(Unknown)';
        $unique_pages[$pt] = $pt;
        $un = $entry->user_name ?: 'Token User';
        $unique_users[$un] = $un;
        $unique_actions[$entry->action] = $action_labels[$entry->action] ?? $entry->action;
    }
}
?>

<div class="wrap ugentic-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Change History', 'tahmir-ai-to-wp-converter'); ?></h1>
    <p class="description"><?php esc_html_e('Showing changes from the last 3 days across all pages.', 'tahmir-ai-to-wp-converter'); ?></p>

    <?php if (empty($history)): ?>
        <div class="ugentic-card" style="margin-top: 20px;">
            <div class="ugentic-empty-state">
                <span class="dashicons dashicons-backup"></span>
                <h3><?php esc_html_e('No Recent Changes', 'tahmir-ai-to-wp-converter'); ?></h3>
                <p><?php esc_html_e('No changes have been recorded in the last 3 days. Changes will appear here as you edit pages.', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
        </div>
    <?php
else: ?>
        <!-- Filter Controls -->
        <div class="ugentic-card" style="margin-top: 20px; padding: 12px 16px;">
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <label style="font-weight: 500; color: var(--ug-gray-600); font-size: 13px;">
                    <span class="dashicons dashicons-filter" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Filter:', 'tahmir-ai-to-wp-converter'); ?>
                </label>
                <select id="ug-filter-page" style="min-width: 160px;">
                    <option value=""><?php esc_html_e('All Pages', 'tahmir-ai-to-wp-converter'); ?></option>
                    <?php foreach ($unique_pages as $p): ?>
                        <option value="<?php echo esc_attr($p); ?>"><?php echo esc_html($p); ?></option>
                    <?php
    endforeach; ?>
                </select>
                <select id="ug-filter-action" style="min-width: 140px;">
                    <option value=""><?php esc_html_e('All Actions', 'tahmir-ai-to-wp-converter'); ?></option>
                    <?php foreach ($unique_actions as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php
    endforeach; ?>
                </select>
                <select id="ug-filter-user" style="min-width: 140px;">
                    <option value=""><?php esc_html_e('All Users', 'tahmir-ai-to-wp-converter'); ?></option>
                    <?php foreach ($unique_users as $u): ?>
                        <option value="<?php echo esc_attr($u); ?>"><?php echo esc_html($u); ?></option>
                    <?php
    endforeach; ?>
                </select>
                <span id="ug-filter-count" style="color: var(--ug-gray-400); font-size: 12px; margin-left: auto;">
                    <?php printf(/* translators: Number of the pages, records, files, or items described in this message. */ esc_html__('%d entries', 'tahmir-ai-to-wp-converter'), count($history)); ?>
                </span>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 12px;" id="ug-history-table">
            <thead>
                <tr>
                    <th style="width: 160px;"><?php esc_html_e('Date / Time', 'tahmir-ai-to-wp-converter'); ?></th>
                    <th style="width: 200px;"><?php esc_html_e('Page', 'tahmir-ai-to-wp-converter'); ?></th>
                    <th style="width: 130px;"><?php esc_html_e('Action', 'tahmir-ai-to-wp-converter'); ?></th>
                    <th style="width: 140px;"><?php esc_html_e('User', 'tahmir-ai-to-wp-converter'); ?></th>
                    <th><?php esc_html_e('Prompt / Description', 'tahmir-ai-to-wp-converter'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $entry):
        $timestamp = strtotime($entry->created_at);
        $date_str = esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp));
        $page_name = esc_html($entry->page_title ?: esc_html__('(Unknown Page)', 'tahmir-ai-to-wp-converter'));
        $user_name = esc_html($entry->user_name ?: esc_html__('Token User', 'tahmir-ai-to-wp-converter'));
        $action_key = $entry->action;
        $action_label = esc_html($action_labels[$action_key] ?? $action_key);
        $badge_class = $action_badge_class[$action_key] ?? 'ug-action-badge--manual-save';
        $prompt = $entry->prompt ?? '';
        if (strlen($prompt) > 120) {
            $prompt = substr($prompt, 0, 120) . '…';
        }
        // Build edit link
        $edit_url = '';
        if (!empty($entry->page_id)) {
            $edit_url = admin_url('admin.php?page=ugentic-visual-editor&page_id=' . intval($entry->page_id));
        }
        // User profile link
        $user_url = '';
        if (!empty($entry->user_id) && $entry->user_id > 0) {
            $user_url = get_edit_user_link($entry->user_id);
        }
?>
                    <tr data-page="<?php echo esc_attr($page_name); ?>" data-action="<?php echo esc_attr($action_key); ?>" data-user="<?php echo esc_attr($user_name); ?>">
                        <td>
                            <span style="color: var(--ug-gray-700);"><?php echo esc_attr($date_str); ?></span>
                        </td>
                        <td>
                            <?php if ($edit_url): ?>
                                <a href="<?php echo esc_url($edit_url); ?>" style="font-weight: 500;"><?php echo esc_attr($page_name); ?></a>
                            <?php
        else: ?>
                                <?php echo esc_attr($page_name); ?>
                            <?php
        endif; ?>
                        </td>
                        <td>
                            <span class="ug-action-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_attr($action_label); ?></span>
                        </td>
                        <td>
                            <?php if ($user_url): ?>
                                <a href="<?php echo esc_url($user_url); ?>"><?php echo esc_attr($user_name); ?></a>
                            <?php
        else: ?>
                                <span style="color: var(--ug-gray-500);"><?php echo esc_attr($user_name); ?></span>
                            <?php
        endif; ?>
                        </td>
                        <td>
                            <span style="color: var(--ug-gray-500);"><?php echo esc_html($prompt ?: '—'); ?></span>
                        </td>
                    </tr>
                <?php
    endforeach; ?>
            </tbody>
        </table>

        <p class="description" style="margin-top: 12px;">
            <?php printf(/* translators: Number of the pages, records, files, or items described in this message. */ esc_html__('Showing %d entries. Entries older than 3 days are automatically removed.', 'tahmir-ai-to-wp-converter'), count($history)); ?>
        </p>

        
    <?php
endif; ?>
</div>
