<?php
/**
 * Shared admin UI helpers for Tahmir AI.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Admin_UI
{
    /**
     * Render the persistent Tahmir workspace navigation.
     *
     * @param string $current_page Current admin page slug.
     */
    public static function workspace_bar($current_page)
    {
        $module_pages = ['ugentic-modules', 'ugentic-conversions', 'ugentic-optimize'];
        if (class_exists('Ugentic_Conversion_Suite')) {
            $suite = Ugentic_Conversion_Suite::instance();
            foreach ($suite->get_modules() as $module_key => $enabled) {
                if ($enabled) {
                    $module_pages[] = 'ugentic-module-' . $module_key;
                }
            }
        }

        $sections = [
            'overview' => [
                'label' => __('Overview', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-builder'),
                'pages' => ['ugentic-builder'],
                'items' => [],
            ],
            'import' => [
                'label' => __('Import', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-build'),
                'pages' => ['ugentic-build', 'ugentic-clone', 'ugentic-wizard', 'ugentic-generate', 'ugentic-import', 'ugentic-design-vision'],
                'items' => [
                    ['label' => __('Import overview', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-build'],
                    ['label' => __('Import HTML', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-import'],
                    ['label' => __('Import from AI / Clone a Live Site', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-clone'],
                ],
            ],
            'manage' => [
                'label' => __('Manage', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-manage'),
                'pages' => ['ugentic-manage', 'ugentic-pages', 'ugentic-all-pages', 'ugentic-menus', 'ugentic-global-sections', 'ugentic-assets'],
                'items' => [
                    ['label' => __('Manage overview', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-manage'],
                    ['label' => __('Pages', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-pages'],
                    ['label' => __('Bulk Changes', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-all-pages'],
                    ['label' => __('Menus', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-menus'],
                    ['label' => __('Global Sections', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-global-sections'],
                    ['label' => __('Global Assets', 'tahmir-ai-to-wp-converter'), 'slug' => 'ugentic-assets'],
                ],
            ],
            'forms' => [
                'label' => __('Forms', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-forms'),
                'pages' => ['ugentic-forms'],
                'items' => [],
            ],
            'form_entries' => [
                'label' => __('Form Entries', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-form-entries'),
                'pages' => ['ugentic-form-entries'],
                'items' => [],
            ],
            'modules' => [
                'label' => __('Modules', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-modules'),
                'pages' => $module_pages,
                'items' => [],
            ],
            'settings' => [
                'label' => __('Settings', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-settings'),
                'pages' => ['ugentic-settings', 'ugentic-asset-conflicts', 'ugentic-accessibility', 'ugentic-change-history', 'ugentic-api-usage'],
                'items' => [],
            ],
        ];

        $sections['upgrade'] = ['label'=>__('Upgrade', 'tahmir-ai-to-wp-converter'), 'url'=>admin_url('admin.php?page=ugentic-upgrade'), 'pages'=>['ugentic-upgrade'], 'items'=>[]];
        $active_section = 'overview';
        foreach ($sections as $key => $section) {
            if (in_array($current_page, $section['pages'], true)) {
                $active_section = $key;
                break;
            }
        }

        echo '<section class="tahmir-admin-workspace" aria-label="' . esc_attr__('Tahmir AI workspace', 'tahmir-ai-to-wp-converter') . '">';
        echo '<div class="tahmir-admin-workspace__top">';
        echo '<a class="tahmir-admin-workspace__brand" href="' . esc_url(admin_url('admin.php?page=ugentic-builder')) . '">';
        echo '<img src="' . esc_url(UGENTIC_BRAND_LOGO_URL) . '" alt="' . esc_attr__('Tahmir AI', 'tahmir-ai-to-wp-converter') . '">';
        echo '<span><strong>' . esc_html__('Control Center', 'tahmir-ai-to-wp-converter') . '</strong><small>' . esc_html__('Build, manage, and grow', 'tahmir-ai-to-wp-converter') . '</small></span>';
        echo '</a>';
        echo '<nav class="tahmir-admin-workspace__primary" aria-label="' . esc_attr__('Primary workspace navigation', 'tahmir-ai-to-wp-converter') . '">';
        foreach ($sections as $key => $section) {
            $active = $key === $active_section;
            echo '<a href="' . esc_url($section['url']) . '" class="' . ($active ? 'is-active' : '') . '"' . ($active ? ' aria-current="page"' : '') . '>';
            echo esc_html($section['label']);
            echo '</a>';
        }
        echo '</nav>';
        echo '<a class="tahmir-admin-workspace__quick" href="' . esc_url(admin_url('admin.php?page=ugentic-build')) . '">';
        echo '<span class="dashicons dashicons-upload" aria-hidden="true"></span>' . esc_html__('Import', 'tahmir-ai-to-wp-converter');
        echo '</a>';
        echo '</div>';

        $items = $sections[$active_section]['items'];
        if (!empty($items)) {
            echo '<nav class="tahmir-admin-workspace__secondary" aria-label="' . esc_attr__('Section navigation', 'tahmir-ai-to-wp-converter') . '">';
            foreach ($items as $item) {
                $active = $item['slug'] === $current_page;
                echo '<a href="' . esc_url(admin_url('admin.php?page=' . $item['slug'])) . '" class="' . ($active ? 'is-active' : '') . '"' . ($active ? ' aria-current="page"' : '') . '>';
                echo esc_html($item['label']);
                echo '</a>';
            }
            echo '</nav>';
        }
        echo '</section>';
    }

    /**
     * Render a consistent page header.
     *
     * @param array $args Header arguments.
     */
    public static function page_header($args)
    {
        $title = $args['title'] ?? '';
        $description = $args['description'] ?? '';
        $icon = $args['icon'] ?? 'dashicons-layout';
        $actions = $args['actions'] ?? [];

        echo '<div class="ug-admin-header">';
        echo '<div class="ug-admin-header__main">';
        echo '<span class="dashicons ' . esc_attr($icon) . ' ug-admin-header__icon" aria-hidden="true"></span>';
        echo '<div>';
        echo '<h1>' . esc_html($title) . '</h1>';
        if ($description !== '') {
            echo '<p>' . esc_html($description) . '</p>';
        }
        echo '</div>';
        echo '</div>';

        if (!empty($actions)) {
            echo '<div class="ug-admin-header__actions">';
            foreach ($actions as $action) {
                $class = $action['class'] ?? 'button';
                $url = $action['url'] ?? '#';
                $label = $action['label'] ?? '';
                $action_icon = $action['icon'] ?? '';

                echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">';
                if ($action_icon !== '') {
                    echo '<span class="dashicons ' . esc_attr($action_icon) . '" aria-hidden="true"></span>';
                }
                echo esc_html($label);
                echo '</a>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render a grouped hub page.
     *
     * @param array $hub Hub definition.
     */
    public static function hub_page($hub)
    {
        $title = $hub['title'] ?? '';
        $description = $hub['description'] ?? '';
        $cards = $hub['cards'] ?? [];

        echo '<div class="wrap ugentic-wrap ug-admin-hub">';
        self::page_header([
            'title' => $title,
            'description' => $description,
            'icon' => 'dashicons-grid-view',
        ]);

        $grid_class = count($cards) === 2 ? ' ug-admin-card-grid--two' : '';
        echo '<div class="ug-admin-card-grid' . esc_attr($grid_class) . '">';
        foreach ($cards as $card) {
            self::hub_card($card);
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render one hub card.
     *
     * @param array $card Card definition.
     */
    private static function hub_card($card)
    {
        $title = $card['title'] ?? '';
        $description = $card['description'] ?? '';
        $url = $card['url'] ?? '#';
        $icon = $card['icon'] ?? 'dashicons-admin-generic';

        echo '<a class="ug-admin-card ug-admin-card--link" href="' . esc_url($url) . '">';
        echo '<span class="dashicons ' . esc_attr($icon) . ' ug-admin-card__icon" aria-hidden="true"></span>';
        echo '<span class="ug-admin-card__body">';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<span>' . esc_html($description) . '</span>';
        echo '</span>';
        echo '<span class="dashicons dashicons-arrow-right-alt2 ug-admin-card__arrow" aria-hidden="true"></span>';
        echo '</a>';
    }
}
