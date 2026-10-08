<?php
/**
 * Admin menu structure for Tahmir AI.
 *
 * Keeps legacy admin page slugs routable while exposing a smaller grouped menu.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Admin_Menu
{
    const PARENT_SLUG = 'ugentic-builder';
    const CAPABILITY = 'manage_options';

    /**
     * Register the top-level menu, grouped hubs, and hidden legacy routes.
     *
     * @param Ugentic_Admin $admin Admin controller instance.
     */
    public static function register($admin)
    {
        add_menu_page(
            __('Tahmir AI', 'tahmir-ai-to-wp-converter'),
            __('Tahmir AI', 'tahmir-ai-to-wp-converter'),
            self::CAPABILITY,
            self::PARENT_SLUG,
            [$admin, 'render_dashboard'],
            'dashicons-layout',
            30
        );

        foreach (self::visible_pages() as $page) {
            // Keep enabled module destinations together immediately before
            // Settings. The Modules switchboard itself is always available.
            if ($page['slug'] === 'ugentic-settings') {
                self::register_module_pages($admin);
            }
            add_submenu_page(
                in_array($page['slug'], ['ugentic-builder','ugentic-build','ugentic-manage'], true) ? self::PARENT_SLUG : null,
                $page['page_title'],
                $page['menu_title'],
                self::CAPABILITY,
                $page['slug'],
                [$admin, $page['callback']]
            );
        }

        foreach (self::hidden_pages() as $page) {
            add_submenu_page(
                null,
                $page['page_title'],
                $page['menu_title'],
                self::CAPABILITY,
                $page['slug'],
                [$admin, $page['callback']]
            );
        }
    }

    /**
     * Add one focused sidebar destination for every enabled optional module.
     *
     * @param Ugentic_Admin $admin Admin controller instance.
     */
    private static function register_module_pages($admin)
    {
        if (!class_exists('Ugentic_Conversion_Suite')) {
            return;
        }

        $suite = Ugentic_Conversion_Suite::instance();
        $enabled = $suite->get_modules();
        foreach ($suite->module_definitions() as $key => $definition) {
            if (empty($enabled[$key])) {
                continue;
            }

            $callback = $key === 'seo'
                ? 'render_optimize'
                : ($key === 'mcp_conversion' ? 'render_settings'
                    : ($key === 'client_delivery' ? 'render_client_delivery' : 'render_conversions'));
            add_submenu_page(
                self::PARENT_SLUG,
                $definition['label'],
                $definition['menu_title'] ?? $definition['label'],
                self::CAPABILITY,
                'ugentic-module-' . $key,
                [$admin, $callback]
            );
        }
    }

    /**
     * Visible WordPress admin sidebar items.
     */
    public static function visible_pages()
    {
        return [
            [
                'page_title' => __('Overview', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Overview', 'tahmir-ai-to-wp-converter'),
                'slug'       => self::PARENT_SLUG,
                'callback'   => 'render_dashboard',
            ],
            [
                'page_title' => __('Import', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Import', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-build',
                'callback'   => 'render_build',
            ],
            [
                'page_title' => __('Manage', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Manage', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-manage',
                'callback'   => 'render_manage',
            ],
            [
                'page_title' => __('Forms', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Forms', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-forms',
                'callback'   => 'render_forms',
            ],
            [
                'page_title' => __('Form Entries', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Form Entries', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-form-entries',
                'callback'   => 'render_form_entries',
            ],
            [
                'page_title' => __('Modules', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Modules', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-modules',
                'callback'   => 'render_modules',
            ],
            [
                'page_title' => __('Settings', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Settings', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-settings',
                'callback'   => 'render_settings',
            ],
        ];
    }

    /**
     * Hidden pages preserve existing direct URLs and internal links.
     */
    public static function hidden_pages()
    {
        return [
            [
                'page_title' => __('Conversions', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Conversions', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-conversions',
                'callback'   => 'render_conversions',
            ],
            [
                'page_title' => __('SEO & Tools', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('SEO & Tools', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-optimize',
                'callback'   => 'render_optimize',
            ],
            [
                'page_title' => __('Pages', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Pages', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-pages',
                'callback'   => 'render_pages',
            ],
            [
                'page_title' => __('Bulk Changes', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Bulk Changes', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-all-pages',
                'callback'   => 'render_all_pages',
            ],
            [
                'page_title' => __('Generate Page', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Generate Page', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-generate',
                'callback'   => 'render_generate',
            ],
            [
                'page_title' => __('Import Website', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Import Website', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-import',
                'callback'   => 'render_import',
            ],
            [
                'page_title' => __('Design Vision', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Design Vision', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-design-vision',
                'callback'   => 'render_design_vision',
            ],
            [
                'page_title' => __('Global Assets', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Global Assets', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-assets',
                'callback'   => 'render_assets',
            ],
            [
                'page_title' => __('Global Sections', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Global Sections', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-global-sections',
                'callback'   => 'render_global_sections',
            ],
            [
                'page_title' => __('Menu Manager', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Menu Manager', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-menus',
                'callback'   => 'render_menus',
            ],
            [
                'page_title' => __('Asset Conflicts', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Asset Conflicts', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-asset-conflicts',
                'callback'   => 'render_asset_conflicts',
            ],
            [
                'page_title' => __('Accessibility Settings', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Accessibility Settings', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-accessibility',
                'callback'   => 'render_accessibility',
            ],
            [
                'page_title' => __('Change History', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Change History', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-change-history',
                'callback'   => 'render_change_history',
            ],
            [
                'page_title' => __('API Usage', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('API Usage', 'tahmir-ai-to-wp-converter'),
                'slug'       => 'ugentic-api-usage',
                'callback'   => 'render_api_usage',
            ],
        ];
    }

    /**
     * Hub definitions used by the Import, Manage, and SEO & Tools pages.
     *
     * @param string $hub Hub key.
     * @return array
     */
    public static function get_hub($hub)
    {
        $hubs = [
            'build' => [
                'title'       => __('Import', 'tahmir-ai-to-wp-converter'),
                'description' => __('Bring an existing website into WordPress from HTML files or directly from a live URL.', 'tahmir-ai-to-wp-converter'),
                'cards'       => [
                    [
                        'title'       => __('Import HTML', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Upload a website ZIP containing HTML, CSS, JavaScript, fonts, and images, then review it before importing.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-import'),
                        'icon'        => 'dashicons-upload',
                    ],
                    [
                        'title'       => __('Import from AI / Clone a Live Site', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Paste a live URL, choose its source platform, select one page or a full site, and import editable pages.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-clone'),
                        'icon'        => 'dashicons-admin-site-alt2',
                    ],
                ],
            ],
            'manage' => [
                'title'       => __('Manage', 'tahmir-ai-to-wp-converter'),
                'description' => __('Manage pages, navigation, reusable sections, and the shared code that powers your Tahmir AI site.', 'tahmir-ai-to-wp-converter'),
                'cards'       => [
                    [
                        'title'       => __('Pages', 'tahmir-ai-to-wp-converter'),
                        'description' => __('View all Tahmir AI pages, edit, export, inspect design DNA, and manage page-level actions.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-pages'),
                        'icon'        => 'dashicons-media-document',
                    ],
                    [
                        'title'       => __('Bulk Changes', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Use AI to edit or create multiple pages while preserving context across requests.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-all-pages'),
                        'icon'        => 'dashicons-edit-page',
                    ],
                    [
                        'title'       => __('Menu Manager', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Build and reorder header or footer navigation with a live preview.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-menus'),
                        'icon'        => 'dashicons-menu-alt3',
                    ],
                    [
                        'title'       => __('Global Assets', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Manage shared CSS, JavaScript, headers, footers, and header/footer scanning.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-assets'),
                        'icon'        => 'dashicons-admin-appearance',
                    ],
                    [
                        'title'       => __('Global Sections', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Manage reusable page sections that stay linked across multiple pages.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-global-sections'),
                        'icon'        => 'dashicons-layout',
                    ],
                ],
            ],
            'optimize' => [
                'title'       => __('SEO & Tools', 'tahmir-ai-to-wp-converter'),
                'description' => __('Grow search visibility with full on-page audits, then access performance, accessibility, history, and usage tools.', 'tahmir-ai-to-wp-converter'),
                'cards'       => [
                    [
                        'title'       => __('Asset Conflicts', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Detect library conflicts, optimization opportunities, and page-level asset issues.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-asset-conflicts'),
                        'icon'        => 'dashicons-performance',
                    ],
                    [
                        'title'       => __('Accessibility Settings', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Enable a visitor accessibility popup, choose its position, and control which options appear.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-accessibility'),
                        'icon'        => 'dashicons-universal-access',
                    ],
                    [
                        'title'       => __('Change History', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Review recent page changes and open the editor from the audit trail.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-change-history'),
                        'icon'        => 'dashicons-backup',
                    ],
                    [
                        'title'       => __('API Usage', 'tahmir-ai-to-wp-converter'),
                        'description' => __('Track AI requests, token volume, estimated cost, and usage by action type.', 'tahmir-ai-to-wp-converter'),
                        'url'         => admin_url('admin.php?page=ugentic-api-usage'),
                        'icon'        => 'dashicons-chart-bar',
                    ],
                ],
            ],
        ];

        return $hubs[$hub] ?? [];
    }
}
