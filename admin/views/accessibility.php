<?php
/**
 * Accessibility Settings admin page.
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = class_exists('Ugentic_Accessibility')
    ? Ugentic_Accessibility::get_settings()
    : [];
$option_definitions = Tahmir_Free_Edition::accessibility_options();

$database = ugentic_builder()->database;
$pages = $database ? $database->get_all_pages(['limit' => 200]) : [];
$positions = [
    'bottom_right' => esc_html__('Bottom Right', 'tahmir-ai-to-wp-converter'),
    'bottom_left' => esc_html__('Bottom Left', 'tahmir-ai-to-wp-converter'),
    'middle_right' => esc_html__('Middle Right', 'tahmir-ai-to-wp-converter'),
    'middle_left' => esc_html__('Middle Left', 'tahmir-ai-to-wp-converter'),
    'top_right' => esc_html__('Top Right', 'tahmir-ai-to-wp-converter'),
    'top_left' => esc_html__('Top Left', 'tahmir-ai-to-wp-converter'),
];

$option_groups = [];
foreach ($option_definitions as $key => $definition) {
    $group = $definition['group'] ?? esc_html__('Other', 'tahmir-ai-to-wp-converter');
    if (!isset($option_groups[$group])) {
        $option_groups[$group] = [];
    }
    $option_groups[$group][$key] = $definition;
}
?>

<div class="wrap ugentic-wrap ug-accessibility-settings">
    <?php
    Ugentic_Admin_UI::page_header([
        'title' => esc_html__('Accessibility Settings', 'tahmir-ai-to-wp-converter'),
        'description' => esc_html__('Configure the visitor accessibility popup shown on Tahmir AI pages.', 'tahmir-ai-to-wp-converter'),
        'icon' => 'dashicons-universal-access',
        'actions' => [
            [
                'label' => esc_html__('Back to SEO & Tools', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-optimize'),
                'class' => 'button',
                'icon' => 'dashicons-arrow-left-alt2',
            ],
        ],
    ]);
    ?>

    <form id="ugentic-accessibility-form" class="ugentic-form">
        <?php wp_nonce_field('ugentic_admin_nonce', 'nonce'); ?>

        <div class="ug-accessibility-grid">
            <div class="ugentic-card">
                <h2><?php esc_html_e('Widget Availability', 'tahmir-ai-to-wp-converter'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="ug-a11y-enabled"><?php esc_html_e('Enable Popup', 'tahmir-ai-to-wp-converter'); ?></label>
                        </th>
                        <td>
                            <label class="ug-accessibility-toggle">
                                <input type="checkbox" id="ug-a11y-enabled" name="enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                                <span><?php esc_html_e('Show the accessibility popup on Tahmir AI pages', 'tahmir-ai-to-wp-converter'); ?></span>
                            </label>
                            <p class="description">
                                <?php esc_html_e('The popup is not loaded on normal WordPress posts/pages or inside wp-admin.', 'tahmir-ai-to-wp-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ug-a11y-position"><?php esc_html_e('Popup Position', 'tahmir-ai-to-wp-converter'); ?></label>
                        </th>
                        <td>
                            <select id="ug-a11y-position" name="position" class="regular-text">
                                <?php foreach ($positions as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['position'] ?? 'bottom_right', $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose which fixed corner should show the accessibility trigger.', 'tahmir-ai-to-wp-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ug-a11y-auto-hide"><?php esc_html_e('Smart Visibility', 'tahmir-ai-to-wp-converter'); ?></label>
                        </th>
                        <td>
                            <label class="ug-accessibility-toggle">
                                <input type="checkbox" id="ug-a11y-auto-hide" name="auto_hide_unavailable" value="1" <?php checked(!empty($settings['auto_hide_unavailable'])); ?>>
                                <span><?php esc_html_e('Auto-hide controls that are not relevant to the current page', 'tahmir-ai-to-wp-converter'); ?></span>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Detection uses page HTML, CSS, and browser-side computed styles where available.', 'tahmir-ai-to-wp-converter'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ugentic-card">
                <h2><?php esc_html_e('Popup Placement Preview', 'tahmir-ai-to-wp-converter'); ?></h2>
                <div class="ug-accessibility-preview" data-position="<?php echo esc_attr($settings['position'] ?? 'bottom_right'); ?>">
                    <span class="ug-accessibility-preview__corner ug-accessibility-preview__corner--top-left"></span>
                    <span class="ug-accessibility-preview__corner ug-accessibility-preview__corner--top-right"></span>
                    <span class="ug-accessibility-preview__corner ug-accessibility-preview__corner--middle-left"></span>
                    <span class="ug-accessibility-preview__corner ug-accessibility-preview__corner--middle-right"></span>
                    <span class="ug-accessibility-preview__corner ug-accessibility-preview__corner--bottom-left"></span>
                    <span class="ug-accessibility-preview__corner ug-accessibility-preview__corner--bottom-right"></span>
                    <span class="ug-accessibility-preview__trigger">Aa</span>
                </div>
            </div>
        </div>

        <div class="ugentic-card">
            <h2><?php esc_html_e('Controls to Show', 'tahmir-ai-to-wp-converter'); ?></h2>
            <p class="description">
                <?php esc_html_e('Disabled controls are never rendered on the frontend. Enabled controls wait for the page CSS, fonts, and layout to settle before appearing to visitors.', 'tahmir-ai-to-wp-converter'); ?>
            </p>

            <div class="ug-accessibility-options">
                <?php foreach ($option_groups as $group_label => $group_options): ?>
                    <section class="ug-accessibility-option-group" aria-label="<?php echo esc_attr($group_label); ?>">
                        <h3><?php echo esc_html($group_label); ?></h3>
                        <div class="ug-accessibility-option-group__grid">
                            <?php foreach ($group_options as $key => $definition): ?>
                                <label class="ug-accessibility-option" for="ug-a11y-option-<?php echo esc_attr($key); ?>">
                                    <input
                                        type="checkbox"
                                        id="ug-a11y-option-<?php echo esc_attr($key); ?>"
                                        name="options[<?php echo esc_attr($key); ?>]"
                                        value="1"
                                        <?php checked(!empty($settings['options'][$key])); ?>
                                    >
                                    <span>
                                        <strong><?php echo esc_html($definition['label']); ?></strong>
                                        <em><?php echo esc_html($definition['description']); ?></em>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="submit ug-accessibility-submit">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Accessibility Settings', 'tahmir-ai-to-wp-converter'); ?>
            </button>
            <span id="ug-accessibility-save-result" class="ug-accessibility-result" aria-live="polite"></span>
        </p>
    </form>

    <div class="ugentic-card ug-accessibility-detection">
        <div class="ug-accessibility-detection__header">
            <div>
                <h2><?php esc_html_e('Detection Preview', 'tahmir-ai-to-wp-converter'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Scan a Tahmir AI page to preview which controls will appear with the current settings.', 'tahmir-ai-to-wp-converter'); ?>
                </p>
            </div>
            <div class="ug-accessibility-detection__actions">
                <select id="ug-accessibility-page-id" class="regular-text">
                    <?php if (empty($pages)): ?>
                        <option value=""><?php esc_html_e('No Tahmir AI pages found', 'tahmir-ai-to-wp-converter'); ?></option>
                    <?php else: ?>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->id); ?>">
                                <?php echo esc_html($page->post_title ?: sprintf(/* translators: Page identifier. */ esc_html__('Page #%d', 'tahmir-ai-to-wp-converter'), $page->id)); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="button" id="ug-accessibility-scan" class="button">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Scan Page', 'tahmir-ai-to-wp-converter'); ?>
                </button>
            </div>
        </div>

        <div id="ug-accessibility-detection-result" class="ug-accessibility-detection__result" aria-live="polite">
            <p class="description"><?php esc_html_e('Choose a page and run a scan to see detected text, links, forms, focusable elements, animations, media, and color signals.', 'tahmir-ai-to-wp-converter'); ?></p>
        </div>
    </div>
</div>

