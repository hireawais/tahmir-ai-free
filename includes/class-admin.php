<?php
/**
 * Admin Panel for Tahmir AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Admin
{
    /**
     * Frontend edit-token proxies validate auth before entering the shared bulk
     * handlers, so the admin nonce/cap checks can be skipped for that call.
     */
    private $bulk_frontend_bypass = false;

    /**
     * Constructor
     */public function __construct() {
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('admin_notices', [$this, 'render_admin_workspace_bar'], 1);
    add_filter('admin_body_class', [$this, 'add_admin_body_class']);
    add_filter('page_row_actions', [$this, 'add_page_row_actions'], 10, 2);
    add_filter('use_block_editor_for_post', [$this, 'disable_block_editor_for_ugentic_pages'], 10, 2);
    add_action('edit_form_after_title', [$this, 'render_ugentic_edit_placeholder']);
    add_action('admin_head-post.php', [$this, 'print_ugentic_post_editor_css']);
add_action('wp_ajax_ugentic_delete_page', [$this, 'ajax_delete_page']);
add_action('wp_ajax_ugentic_delete_pages_bulk', [$this, 'ajax_delete_pages_bulk']);
add_action('wp_ajax_ugentic_delete_all_pages', [$this, 'ajax_delete_all_pages']);
add_action('wp_ajax_ugentic_cleanup_orphans', [$this, 'ajax_cleanup_orphans']);
}

    /**
     * Register AJAX handlers by feature group. Action names remain unchanged.
     */

    /**
     * AJAX hook map, grouped for maintainability.
     *
     * @return array
     */

    /**
     * Show account status without implying that the site or builder is disabled.
     */

    /**
     * Show one-off notices for Ugentic controls on normal WordPress screens.
     */

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        Ugentic_Admin_Menu::register($this);
    }

    /**
     * Add a stable body class for the Tahmir admin experience.
     *
     * @param string $classes Existing body classes.
     * @return string
     */
    public function add_admin_body_class($classes)
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'ugentic') === 0) {
            $classes .= ' tahmir-admin-page tahmir-admin-page--' . sanitize_html_class($page);
        }
        return $classes;
    }

    /**
     * Render the persistent Tahmir workspace navigation on plugin screens.
     */
    public function render_admin_workspace_bar()
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'ugentic') !== 0) {
            return;
        }
        Ugentic_Admin_UI::workspace_bar($page);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // On the Pages list, inject conversion JS via admin_footer
        $screen = get_current_screen();
        if ($hook === 'edit.php' && $screen && $screen->post_type === 'page') {

            return;
        }

        // Only load full assets on our pages
        if (strpos($hook, 'ugentic') === false) {
            return;
        }

        // WordPress Media Library (for logo upload etc.)
        wp_enqueue_media();

        // CodeMirror for code editing
        wp_enqueue_code_editor(['type' => 'text/html']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');

        // Shared Tahmir product-interface tokens.
        wp_enqueue_style(
            'tahmir-ui-tokens',
            UGENTIC_PLUGIN_URL . 'public/css/tahmir-ui-tokens.css',
            [],
            file_exists(UGENTIC_PLUGIN_DIR . 'public/css/tahmir-ui-tokens.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'public/css/tahmir-ui-tokens.css') : UGENTIC_VERSION
        );

        // Legacy aliases and components remain during the gradual migration.
        wp_enqueue_style(
            'ugentic-design-tokens',
            UGENTIC_PLUGIN_URL . 'admin/css/ugentic-design-tokens.css',
            ['tahmir-ui-tokens'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/ugentic-design-tokens.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/ugentic-design-tokens.css') : UGENTIC_VERSION
        );

        // Admin CSS
        wp_enqueue_style(
            'ugentic-admin',
            UGENTIC_PLUGIN_URL . 'admin/css/admin.css',
            ['ugentic-design-tokens'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/admin.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/admin.css') : UGENTIC_VERSION
        );

        // Admin JS
        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_script(
            'ugentic-admin',
            UGENTIC_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery', 'jquery-ui-sortable', 'wp-util'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/js/admin.js') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/js/admin.js') : UGENTIC_VERSION,
            true
        );

        wp_enqueue_script(
            'tahmir-admin-experience-v3',
            UGENTIC_PLUGIN_URL . 'admin/js/admin-experience-v3.js',
            ['jquery', 'ugentic-admin'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/js/admin-experience-v3.js') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/js/admin-experience-v3.js') : UGENTIC_VERSION,
            true
        );

        // Data Mapper (Visual Dynamic Content Mapping)
        wp_enqueue_style(
            'ugentic-data-mapper',
            UGENTIC_PLUGIN_URL . 'admin/css/data-mapper.css',
            ['ugentic-admin'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/data-mapper.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/data-mapper.css') : UGENTIC_VERSION
        );
        wp_localize_script('ugentic-admin', 'ugenticAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ugentic_admin_nonce'),
            'edition' => 'free',
            'strings' => [
                'saving' => __('Saving...', 'tahmir-ai-to-wp-converter'),
                'saved' => __('Saved!', 'tahmir-ai-to-wp-converter'),
                'error' => __('Error occurred', 'tahmir-ai-to-wp-converter'),
                'testing' => __('Testing connection...', 'tahmir-ai-to-wp-converter'),
                'generating' => __('Generating page...', 'tahmir-ai-to-wp-converter'),
                'confirmDelete' => __('Are you sure you want to delete this?', 'tahmir-ai-to-wp-converter'),
            ],
        ]);

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($page === 'ugentic-all-pages') {
            
        }

        if ($page === 'ugentic-accessibility') {
            
        }

        if ($page === 'ugentic-settings') {
            
        }

        if ($page === 'ugentic-forms' && $this->is_form_builder_request()) {
            
        }

        // Design Vision page — enqueue dedicated JS
        if (strpos($hook, 'ugentic-design-vision') !== false) {
            
        }

        wp_enqueue_style(
            'ugentic-admin-polish',
            UGENTIC_PLUGIN_URL . 'admin/css/admin-polish.css',
            ['ugentic-admin'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/admin-polish.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/admin-polish.css') : UGENTIC_VERSION
        );

        // Branded Tahmir v2 shell is the final plugin-page layer and is scoped
        // to .ugentic-wrap so media modals and third-party admin UI stay native.
        wp_enqueue_style(
            'tahmir-admin-modern-v2',
            UGENTIC_PLUGIN_URL . 'admin/css/admin-modern-v2.css',
            ['ugentic-admin-polish', 'tahmir-ui-tokens'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/admin-modern-v2.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/admin-modern-v2.css') : UGENTIC_VERSION
        );

        wp_enqueue_style(
            'tahmir-admin-experience-v3',
            UGENTIC_PLUGIN_URL . 'admin/css/admin-experience-v3.css',
            ['tahmir-admin-modern-v2'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/admin-experience-v3.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/admin-experience-v3.css') : UGENTIC_VERSION
        );

        // SEO command center is the final visual layer for its workspace.
        if (in_array($page, ['ugentic-optimize', 'ugentic-module-seo'], true)) {
            wp_enqueue_style(
                'tahmir-seo-dashboard',
                UGENTIC_PLUGIN_URL . 'admin/css/seo-dashboard.css',
                ['tahmir-admin-experience-v3'],
                file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/seo-dashboard.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/seo-dashboard.css') : UGENTIC_VERSION
            );
        }
    }

    /**
     * Load the focused per-page SEO console on Tahmir AI WordPress pages.
     */

    /**
     * Enqueue Bulk Changes assets through WordPress instead of from the view.
     */

    /**
     * Enqueue Accessibility Settings assets.
     */

    /**
     * Enqueue Blog Settings visual mapper assets.
     */

    /**
     * Form builder mode is selected by an existing form ID or the new form action.
     */
    private function is_form_builder_request()
    {
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        return !empty($_GET['form_id']) || $action === 'new';
    }

    /**
     * Enqueue third-party Form Builder assets through WordPress.
     */

    /**
     * Build the Bulk Changes localized payload.
     *
     * @return array
     */

    /**
     * Render dashboard
     */
    public function render_dashboard()
    {
        $database = ugentic_builder()->database;
        $api = ugentic_builder()->api;

        $stats = [
            'pages' => $database->count_pages(),
            'css_assets' => count($database->get_assets_by_type('css')),
            'js_assets' => count($database->get_assets_by_type('js')),
            'api_configured' => $api->is_configured(),
        ];

        include UGENTIC_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render Build hub.
     */
    public function render_build()
    {
        Ugentic_Admin_UI::hub_page(Ugentic_Admin_Menu::get_hub('build'));
    }

    /**
     * Render Manage hub.
     */
    public function render_manage()
    {
        Ugentic_Admin_UI::hub_page(Ugentic_Admin_Menu::get_hub('manage'));
    }

    /**
     * Render Optimize hub.
     */public function render_optimize() { Tahmir_Free_Edition::locked_view('optimize', 'Optimize'); }

    /**
     * Render modular conversion reporting and experimentation.
     */public function render_conversions() { Tahmir_Free_Edition::locked_view('conversions', 'Conversions'); }

    /**
     * Render passwordless client handoff and per-link editor permissions.
     */public function render_client_delivery() { Tahmir_Free_Edition::locked_view('client-delivery', 'Client Delivery'); }

    /**
     * Render the minimal module switchboard.
     */public function render_modules() { $module_definitions = Tahmir_Free_Edition::module_definitions(); $modules = array_fill_keys(array_keys($module_definitions), false); include UGENTIC_PLUGIN_DIR . 'admin/views/modules.php'; }

    /**
     * Render pages list
     */
    public function render_pages()
    {
        $database = ugentic_builder()->database;
        $total_pages = $database->count_pages();
        $page_size = 50;
        $current_page = max(1, min(max(1, (int) ceil($total_pages / $page_size)), absint($_GET['ugentic_page'] ?? 1)));
        $page_args = ['limit' => $page_size, 'offset' => ($current_page - 1) * $page_size];
        $pages = $database->get_all_pages($page_args);
        $seo = ugentic_builder()->seo ?? null;

        if ($seo && method_exists($seo, 'scan_all_pages')) {
            $seo->scan_all_pages(false);
            $pages = $database->get_all_pages($page_args);
        }

        include UGENTIC_PLUGIN_DIR . 'admin/views/pages.php';
    }

    /**
     * Render All Pages (Bulk AI Editing)
     */public function render_all_pages() { Tahmir_Free_Edition::locked_view('all-pages', 'All Pages'); }

    /**
     * Render generate page form
     */public function render_generate() { Tahmir_Free_Edition::locked_view('generate', 'Generate'); }

    /**
     * Render Design Vision (Image-to-Code) page
     */public function render_design_vision() { Tahmir_Free_Edition::locked_view('design-vision', 'Design Vision'); }

    /**
     * Render menus page
     */public function render_menus() { Tahmir_Free_Edition::locked_view('menus', 'Menus'); }

    /**
     * Render import page
     */
    public function render_import()
    {
        include UGENTIC_PLUGIN_DIR . 'admin/views/import.php';
    }

    /**
     * Render assets manager
     */public function render_assets() { Tahmir_Free_Edition::locked_view('assets', 'Assets'); }

    /**
     * Render forms page
     */public function render_forms() { if (current_user_can('manage_options')) { Tahmir_Free_Edition::locked_view('forms-list', 'Forms'); } }

    /**
     * Render form entries page
     */
    public function render_form_entries() { if (current_user_can('manage_options')) { Tahmir_Free_Edition::locked_view('form-entries', 'Form Entries'); } }

    /**
     * Render settings page
     */public function render_settings() { include UGENTIC_PLUGIN_DIR . 'admin/views/settings-free.php'; }

    /**
     * Render asset conflicts page
     */public function render_asset_conflicts() { Tahmir_Free_Edition::locked_view('asset-conflicts', 'Asset Conflicts'); }

    /**
     * Render Accessibility Settings page.
     */public function render_accessibility() { Tahmir_Free_Edition::locked_view('accessibility', 'Accessibility'); }

    /**
     * Render global sections page
     */public function render_global_sections() { Tahmir_Free_Edition::locked_view('global-sections', 'Global Sections'); }

    /**
     * Render change history page
     */public function render_change_history() { Tahmir_Free_Edition::locked_view('change-history', 'Change History'); }

    /**
     * Render API usage page
     */public function render_api_usage() { Tahmir_Free_Edition::locked_view('api-usage', 'Api Usage'); }

    /**
     * AJAX: Save settings
     */

    /**
     * AJAX: Analyze an existing page for manual blog conversion.
     */

    /**
     * AJAX: Store the fully rendered mapper DOM and analyze that exact snapshot.
     */

    /**
     * AJAX: Convert an existing page into the blog archive or single-post template.
     */

    /**
     * Get page HTML for analysis without forcing the page into Tahmir AI storage.
     */

    /**
     * Ensure an existing WP page has a Ugentic DB row before in-place conversion.
     */

    /**
     * Keep rendered layout/content while removing executable and mapper-only UI.
     */

    /**
     * Capture rendered content from a normal WordPress page.
     */

    /**
     * Add basic editable markers when capturing a regular WP page.
     */

    /**
     * Remove scripts and ensure the mapper receives a full preview document.
     */

    /**
     * Build first-pass XPath suggestions for the visual mapper.
     */

    /**
     * Validate submitted mapper XPaths against the current page DOM.
     */

    // ─── License AJAX Handlers ──────────────────────────────────────

    /**
     * AJAX: Activate a license key
     */

    /**
     * AJAX: Deactivate the current license
     */

    /**
     * AJAX: Refresh license status from the server
     */

    /**
     * AJAX: Save whitelabel settings (local overrides)
     */

    /**
     * AJAX: Detect logos from imported pages at each location (header, footer, sticky)
     */

    /**
     * AJAX: Apply logos to all Tahmir AI pages with location-aware replacement
     */

    /**
     * AJAX: Test API connection
     */

    /**
     * AJAX: Test Anthropic API connection
     */

    /**
     * AJAX: Save asset
     */

    /**
     * AJAX: Delete asset
     */

    /**
     * AJAX: Bulk delete assets
     */

    /**
     * AJAX: Generate page with AI
     */

    /**
     * AJAX: Auto-generate links in header from WordPress pages
     * Comprehensive non-AI method that matches various link patterns
     */

    /**
     * AJAX: Update all internal links across all pages
     * Converts .html file links to WordPress permalinks
     */

    /**
     * AJAX: AI Scan all pages for header/footer
     */

    /**
     * Strip headers/footers AND fix corrupted content from all pages
     * This handles pages that accidentally saved the full HTML document
     */

    /**
     * AJAX: Strip headers/footers and fix corrupted content from all pages
     */

    /**
     * AJAX: Delete single page
     */
    public function ajax_delete_page()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ugentic_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'tahmir-ai-to-wp-converter')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $page_id = intval($_POST['page_id'] ?? 0);
        $wp_page_id = intval($_POST['wp_page_id'] ?? 0);

        if (!$page_id) {
            wp_send_json_error(['message' => __('Invalid page ID', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;

        // Delete from Ugentic database
        $database->delete_page($page_id);

        // Delete WordPress page if exists
        if ($wp_page_id && get_post($wp_page_id)) {
            wp_delete_post($wp_page_id, true);
        }

        wp_send_json_success([
            'message' => __('Page deleted successfully!', 'tahmir-ai-to-wp-converter'),
        ]);
    }

    /**
     * AJAX: Bulk delete pages
     */
    public function ajax_delete_pages_bulk()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ugentic_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'tahmir-ai-to-wp-converter')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $page_ids = array_map('intval', $_POST['page_ids'] ?? []);

        if (empty($page_ids)) {
            wp_send_json_error(['message' => __('No pages selected', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;
        $deleted = 0;

        foreach ($page_ids as $page_id) {
            $page = $database->get_page($page_id);
            if ($page) {
                // Delete WordPress page if exists
                if ($page->wp_page_id && get_post($page->wp_page_id)) {
                    wp_delete_post($page->wp_page_id, true);
                }
                // Delete from Ugentic database
                $database->delete_page($page_id);
                $deleted++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ __('Deleted %d page(s) successfully!', 'tahmir-ai-to-wp-converter'), $deleted),
        ]);
    }

    /**
     * AJAX: Delete all pages
     */
    public function ajax_delete_all_pages()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ugentic_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'tahmir-ai-to-wp-converter')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;
        $pages = $database->get_all_pages();
        $deleted = 0;

        foreach ($pages as $page) {
            // Delete WordPress page if exists
            if ($page->wp_page_id && get_post($page->wp_page_id)) {
                wp_delete_post($page->wp_page_id, true);
            }
            // Delete from Ugentic database
            $database->delete_page($page->id);
            $deleted++;
        }

        wp_send_json_success([
            'message' => sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ __('Deleted all %d page(s) successfully!', 'tahmir-ai-to-wp-converter'), $deleted),
        ]);
    }

    /**
     * AJAX: Reset entire database
     */

    /**
     * AJAX: Cleanup orphaned pages (Ugentic records where WP page no longer exists)
     */
    public function ajax_cleanup_orphans()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ugentic_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'tahmir-ai-to-wp-converter')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;
        $pages = $database->get_all_pages();
        $cleaned = 0;

        foreach ($pages as $page) {
            // Check if WordPress page still exists
            if (!get_post($page->wp_page_id)) {
                $database->delete_page($page->id);
                $cleaned++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ __('Cleaned up %d orphaned record(s)!', 'tahmir-ai-to-wp-converter'), $cleaned),
        ]);
    }

    /**
     * AJAX: Save form
     */

    /**
     * AJAX: AI Generate Form
     */

    /**
     * Smart keyword-based form generator fallback
     */

    /**
     * AJAX: Delete form
     */

    /**
     * AJAX: Delete several forms and their dependent entries/logs.
     */

    /**
     * AJAX: Get form entries
     */

    /**
     * AJAX: Get single form entry
     */

    /**
     * AJAX: Delete form entry
     */

    /**
     * AJAX: Get all entries across forms (for the Form Entries page)
     */

    /**
     * AJAX: Bulk action on entries (delete, mark read, mark unread)
     */

    /**
     * AJAX: Export entries as CSV
     */

    /**
     * AJAX: Get form logs (email & webhook logs)
     */

    /**
     * AJAX: Test webhook
     */

    /**
     * AJAX: Regenerate public edit token
     */

    /**
     * AJAX: Scrub edit tokens from all page HTML.
     * Scans all Tahmir AI pages and removes any embedded ?ugentic_edit=TOKEN from links.
     */

    /**
     * AJAX: Generate a hashed MCP access token.
     */

    /**
     * AJAX: Revoke an MCP access token.
     */

    /**
     * Print inline JS for the page conversion button on the Pages list.
     * Hooked to admin_footer.
     */
    public function print_convert_page_js()
    {
        ?>
        <script>
            jQuery(function($) {
                var progressTimer = null;
                var currentProgress = 0;

                $('<style id="ugentic-admin-loading-bar-css">\
                    #ugentic-admin-loading-bar{position:fixed;top:0;left:0;right:0;height:3px;z-index:1000000;opacity:0;pointer-events:none;transition:opacity 160ms ease;}\
                    #ugentic-admin-loading-bar.is-active{opacity:1;}\
                    #ugentic-admin-loading-bar .ugentic-admin-loading-fill{width:0;height:100%;background:linear-gradient(90deg,#2563eb,#ea580c,#16a34a);box-shadow:0 0 14px rgba(37,99,235,.45);transition:width 280ms ease;}\
                    #ugentic-admin-loading-bar .ugentic-admin-loading-label{position:absolute;top:9px;right:12px;max-width:min(360px,calc(100vw - 24px));padding:6px 10px;border-radius:4px;background:rgba(17,24,39,.94);color:#fff;font:600 12px/1.3 -apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;box-shadow:0 8px 22px rgba(15,23,42,.22);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}\
                </style>').appendTo('head');

                function ensureLoadingBar(message) {
                    var $bar = $('#ugentic-admin-loading-bar');
                    if (!$bar.length) {
                        $bar = $('<div id="ugentic-admin-loading-bar"><div class="ugentic-admin-loading-fill"></div><div class="ugentic-admin-loading-label"></div></div>').appendTo('body');
                    }
                    $bar.find('.ugentic-admin-loading-label').text(message || 'Loading...');
                    $bar.addClass('is-active');
                    return $bar;
                }

                function setLoadingProgress(percent) {
                    currentProgress = Math.max(currentProgress, percent);
                    $('#ugentic-admin-loading-bar .ugentic-admin-loading-fill').css('width', Math.min(currentProgress, 100) + '%');
                }

                function startLoading(message) {
                    currentProgress = 0;
                    ensureLoadingBar(message);
                    setLoadingProgress(8);

                    if (progressTimer) {
                        clearInterval(progressTimer);
                    }

                    progressTimer = setInterval(function() {
                        if (currentProgress < 88) {
                            setLoadingProgress(currentProgress + Math.max(2, Math.round((88 - currentProgress) * 0.18)));
                        }
                    }, 280);
                }

                function finishLoading(keepVisible) {
                    if (progressTimer) {
                        clearInterval(progressTimer);
                        progressTimer = null;
                    }
                    setLoadingProgress(100);
                    if (!keepVisible) {
                        setTimeout(function() {
                            $('#ugentic-admin-loading-bar').removeClass('is-active');
                        }, 450);
                    }
                }

                $(document).on('click', '.ugentic-convert-page-action', function (e) {
                    e.preventDefault();
                    var $link = $(this);
                    var pageId = $link.data('page-id');
                    var nonce = $link.data('nonce');
                    var origText = $link.text();

                    startLoading('Converting page to Tahmir AI...');
                    $link.text('Converting...').css('pointer-events', 'none');

                    $.post(ajaxurl, {
                        action: 'ugentic_convert_page',
                        page_id: pageId,
                        nonce: nonce
                    }, function (resp) {
                        if (resp.success) {
                            $link.text(resp.data.message);
                            ensureLoadingBar('Opening editor...');
                            finishLoading(true);
                            setTimeout(function () {
                                window.location.href = resp.data.edit_url;
                            }, 350);
                        } else {
                            finishLoading(false);
                            $link.text(resp.data ? resp.data.message : 'Error');
                            setTimeout(function () { $link.text(origText).css('pointer-events', ''); }, 3000);
                        }
                    }).fail(function () {
                        finishLoading(false);
                        $link.text('Network error');
                        setTimeout(function () { $link.text(origText).css('pointer-events', ''); }, 3000);
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Add "Edit with Tahmir" row action to WordPress Pages list.
     * Similar to Elementor's "Edit with Elementor" button.
     */
    public function add_page_row_actions($actions, $post) {
        if ($this->is_ugentic_wp_page($post) && current_user_can('edit_page', $post->ID)) {
            $actions['edit_ugentic'] = '<a href="https://tahmir.com/upgrade" target="_blank" rel="noopener noreferrer">'.esc_html__('Visual Editor · Pro', 'tahmir-ai-to-wp-converter').'</a>';
        }
        return $actions;
    }

    /**
     * Tahmir AI pages should be edited through the frontend visual editor.
     */
    public function disable_block_editor_for_ugentic_pages($use_block_editor, $post)
    {
        if ($this->is_ugentic_wp_page($post)) {
            return false;
        }

        return $use_block_editor;
    }

    /**
     * Replace the normal content editor with a visual frontend-edit entry point.
     */
    public function render_ugentic_edit_placeholder($post) {
        if (!$this->is_ugentic_wp_page($post) || !current_user_can('edit_page', $post->ID)) { return; }
        echo '<div class="ugentic-wp-edit-placeholder"><h2>'.esc_html__('Visual editing requires Tahmir AI Pro', 'tahmir-ai-to-wp-converter').'</h2><p>'.esc_html__('Free imports and publishes your pages. Activate Pro to edit their content and layout. Your imported page is retained.', 'tahmir-ai-to-wp-converter').'</p><a class="button button-primary" href="https://tahmir.com/upgrade" target="_blank" rel="noopener noreferrer">'.esc_html__('Explore Pro', 'tahmir-ai-to-wp-converter').'</a> <a class="button" href="'.esc_url(get_permalink($post->ID)).'">'.esc_html__('View page', 'tahmir-ai-to-wp-converter').'</a></div>';
    }

    /**
     * Hide the WordPress content editor and style the Ugentic edit card.
     */
    public function print_ugentic_post_editor_css()
    {
        global $post;
        if (!$this->is_ugentic_wp_page($post)) {
            return;
        }
        ?>
        <style>
            #postdivrich,
            #postexcerpt,
            #commentstatusdiv,
            #commentsdiv {
                display: none !important;
            }
            .ugentic-wp-edit-placeholder {
                margin: 18px 0 20px;
                max-width: 920px;
            }
            .ugentic-wp-edit-placeholder__link {
                display: block;
                border: 1px solid #cbd5e1;
                border-radius: 10px;
                background: #111827;
                box-shadow: 0 10px 26px rgba(15, 23, 42, 0.12);
                overflow: hidden;
                text-decoration: none;
            }
            .ugentic-wp-edit-placeholder__link:focus {
                outline: 3px solid #c2410c;
                outline-offset: 3px;
            }
            .ugentic-wp-edit-placeholder img {
                display: block;
                width: 100%;
                height: auto;
            }
            .ugentic-seo-field {
                margin: 0 0 14px;
            }
            .ugentic-seo-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
            }
            .ugentic-seo-field small {
                display: block;
                color: #646970;
                margin-top: 4px;
            }
        </style>
        <?php
    }

    /**
     * Add Ugentic metadata controls to the normal WordPress page edit screen.
     */

    /**
     * Render editable SEO fields for a Tahmir AI page.
     */

    /**
     * Save backend SEO edits for Tahmir AI pages.
     */

    /**
     * Check whether a WordPress page is backed by Tahmir AI.
     */
    private function is_ugentic_wp_page($post)
    {
        if (!$post || !($post instanceof WP_Post) || $post->post_type !== 'page') {
            return false;
        }

        if (get_post_meta($post->ID, '_ugentic_page_id', true)) {
            return true;
        }

        if (function_exists('ugentic_builder') && isset(ugentic_builder()->database)) {
            return (bool) ugentic_builder()->database->get_page_by_wp_id($post->ID);
        }

        return false;
    }

    /**
     * Gather saved SEO values with sensible defaults.
     */

    /**
     * AJAX: Convert any WordPress page (including Elementor pages) to Ugentic format.
     * Renders the page's frontend HTML, strips headers/footers, and imports as a Tahmir AI page.
     */
    public function ajax_convert_page()
    {
        check_ajax_referer('ugentic_convert_page', 'nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $wp_page_id = intval($_POST['page_id'] ?? 0);
        if (!$wp_page_id) {
            wp_send_json_error(['message' => 'Invalid page ID']);
        }

        $post = get_post($wp_page_id);
        if (!$post || $post->post_type !== 'page') {
            wp_send_json_error(['message' => 'Page not found']);
        }

        $db = ugentic_builder()->database;

        // Check if already a Tahmir AI page
        $existing = $db->get_page_by_wp_id($wp_page_id);
        if ($existing) {
            wp_send_json_success([
                'message' => 'Page is already in Tahmir AI',
                'edit_url' => add_query_arg('ugentic_edit', '1', get_permalink($wp_page_id)),
                'ugentic_page_id' => $existing->id,
            ]);
            return;
        }

        // Get the rendered frontend HTML
        $is_elementor = !empty(get_post_meta($wp_page_id, '_elementor_data', true));
        $html_content = '';

        if ($is_elementor && class_exists('\Elementor\Plugin')) {
            // Render Elementor content to clean HTML
            $elementor = \Elementor\Plugin::instance();
            $html_content = $elementor->frontend->get_builder_content_for_display($wp_page_id);
        }

        if (empty($html_content)) {
            // Fallback: use post_content (works for Gutenberg, Classic, etc.)
            $html_content = apply_filters('the_content', $post->post_content);
        }

        if (empty($html_content)) {
            wp_send_json_error(['message' => 'Page has no content to convert']);
        }

        // Store source-neutral HTML; builder renders receive editable markers.

        // Insert into Tahmir AI pages table
        $ugentic_page_id = $db->insert_page([
            'wp_page_id' => $wp_page_id,
            'original_html' => $html_content,
            'current_html' => $html_content,
        ]);

        // Set meta so Ugentic editor recognizes the page
        update_post_meta($wp_page_id, '_ugentic_page_id', $ugentic_page_id);
        update_post_meta($wp_page_id, '_ugentic_html', $html_content);
        update_post_meta($wp_page_id, '_wp_page_template', 'ugentic-blank');

        wp_send_json_success([
            'message' => $is_elementor
                ? 'Elementor page imported to Tahmir AI!'
                : 'Page converted to Tahmir AI!',
            'edit_url' => add_query_arg('ugentic_edit', '1', get_permalink($wp_page_id)),
            'ugentic_page_id' => $ugentic_page_id,
        ]);
    }

    /**
     * AJAX: Bulk AI Plan (Phase 1) — "Analyze One, Apply to All"
     *
     * Instead of sending all pages to AI at once (which overwhelms it):
     * 1. Pick ONE representative page
     * 2. Send its FULL HTML + content map to AI for deep analysis
     * 3. AI returns precise DSL commands (text-based so they work cross-page)
     * 4. Same commands are assigned to ALL selected pages
     * 5. Deterministic executor applies commands per-page
     */

    // parse_agent_language_response removed — no longer needed with analyze-one-apply-all

    /**
     * AJAX: Bulk AI Apply (Phase 2) — Agent Language Edition
     * Receives one page ID + DSL commands (or legacy ops for backward compat).
     * Uses the deterministic Agent Language executor + AI generate for GENERATE commands.
     */

    /**
     * AJAX: Confirm and execute WP backend operations.
     * Called after user reviews the preview and clicks "Apply Backend Changes".
     */

    /**
     * Smart HTML chunking — intelligently reduce page HTML for AI context.
     * Preserves structural integrity instead of crude truncation.
     *
     * Strategy:
     * 1. Extract <head> meta (cap 5KB — just CSS vars and meta)
     * 2. Preserve full <header> and <footer>
     * 3. For <body> sections, include opening tags + first 500 chars each
     * 4. Progressive trimming if still over 80KB
     *
     * @param string $html Full page HTML
     * @param int $max_size Maximum output size in bytes (default 80KB)
     * @return string Chunked HTML preserving structure
     */

    /**
     * Helper: Replace element content by CSS selector using DOMDocument
     */

    /**
     * Helper: Remove elements by CSS selector
     */

    /**
     * Helper: Set attribute on elements by CSS selector
     */

    /**
     * Convert simple CSS selectors to XPath
     * Supports: .class, #id, tag, tag.class, .class1.class2
     */

    /**
     * AJAX: Bulk AI Revert
     * Reverts a page to its state before the last bulk_ai_edit.
     * Supports optional history_id to revert to a specific version.
     */

    /**
     * AJAX: Bulk AI Revert All
     * Reverts ALL pages from a specific batch operation.
     */

    // ==========================================
    // PAGE HISTORY FOR VERSION TIMELINE
    // ==========================================

    /**
     * AJAX: Get page edit history for version timeline
     */

    /**
     * AJAX: Get bulk change history across all pages.
     * Returns the last 50 bulk edit/backup entries grouped by batch_id for the history panel.
     */

    /**
     * AJAX: Revert a specific page to a specific history entry.
     * Used by the bulk change history panel for precise, targeted reverts.
     */

    // ==========================================
    // BULK PAGE CREATION
    // ==========================================

    /**
     * AJAX: Create a new page from bulk changes chat
     * Generates a page matching existing site design, publishes it,
     * and injects footer links across all existing pages.
     */

    /**
     * Inject a footer link into all existing Tahmir AI pages
     *
     * @param string $link_title   The text for the new link
     * @param string $link_url     The URL for the new link
     * @param int    $exclude_page_id  Page ID to exclude (the new page itself)
     * @return array Results of the injection
     */

    // ==========================================
    // BULK CONVERSATION PERSISTENCE
    // ==========================================

    /**
     * AJAX: Save a bulk conversation (insert or update)
     */

    /**
     * AJAX: List recent bulk conversations for current user
     */

    /**
     * AJAX: Load a specific bulk conversation
     */

    /**
     * AJAX: Delete a bulk conversation
     */

    /**
     * AJAX: Upload an image for the bulk AI conversation
     * Saves to WP uploads, returns URL + base64 data URI for AI consumption
     */

    // ─── Design Vision (Image-to-Code) AJAX Handlers ──────────────

    /**
     * AJAX: Analyze a design image using AI vision.
     * Receives base64 image data, returns structured design analysis + smart questions.
     */

    /**
     * AJAX: Generate HTML/CSS from a design image + analysis + user answers.
     */

    /**
     * AJAX: Refine generated HTML based on user feedback.
     */

    /**
     * AJAX: Auto-refine — score current HTML against original image and fix differences.
     * Called in a loop by JS until match score exceeds threshold or max iterations.
     */

    /**
     * AJAX: Visual QA auto-refine — uses screenshot comparison for higher accuracy.
     * Expects the client to send a rendered screenshot of the generated HTML.
     */

    /**
     * AJAX: Extract dominant colors from an uploaded design image.
     * Returns a color palette with roles and frequencies.
     */

    // ═══════════════════════════════════════════════════════════════
    //  SPLIT PIPELINE AJAX HANDLERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * AJAX: Quick overview scan — list sections + extract global styles.
     */

    /**
     * AJAX: Deep analysis of a single section.
     */

    /**
     * AJAX: Generate HTML/CSS for a single section.
     */

    // ==================== AGENCY PANEL AJAX HANDLERS ====================

    /**
     * Render the Agency Management Panel
     */

    /**
     * Get clients (with enriched group data)
     */

    /**
     * Get a single client
     */

    /**
     * Create a new client
     */

    /**
     * Update a client
     */

    /**
     * Delete a client
     */

    /**
     * Bulk update client status
     */

    /**
     * Bulk delete clients
     */

    /**
     * Get all groups
     */

    /**
     * Get a single group
     */

    /**
     * Create a group
     */

    /**
     * Update a group
     */

    /**
     * Delete a group
     */

    /**
     * Get all API keys (masked)
     */

    /**
     * Add a new API key
     */

    /**
     * Toggle API key active/inactive
     */

    /**
     * Delete an API key
     */

    /**
     * Test an API key connection
     */

    /**
     * Get credit log for a client
     */

    /**
     * Get chart data (last 30 days)
     */

    /**
     * Export credit report as CSV
     */

    /**
     * Prevent spreadsheet applications from evaluating exported user content.
     */

    // ─── Phase 1: Multi-Provider ─────────────────────────────────

    // ─── Phase 2: Magic Login ────────────────────────────────────

    // ─── Phase 3: Analytics ──────────────────────────────────────

    // ─── Phase 4: Whitelabel ─────────────────────────────────────

    public function ajax_get_form_entries()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);

        $database = ugentic_builder()->database;
        $entries = $database->get_form_entries($form_id, [
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ]);
        $total = $database->get_form_entries_count($form_id);

        wp_send_json_success([
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }

    public function ajax_get_form_entry()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $entry_id = intval($_POST['entry_id'] ?? 0);

        if ($entry_id <= 0) {
            wp_send_json_error(['message' => __('Invalid entry ID', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;
        $entry = $database->get_form_entry($entry_id);

        if ($entry) {
            // Mark as read
            $database->mark_entry_read($entry_id);
        }

        wp_send_json_success([
            'entry' => $entry,
        ]);
    }

    public function ajax_get_all_entries()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;

        $args = [
            'form_id' => intval($_POST['form_id'] ?? 0),
            'is_read' => isset($_POST['is_read']) && $_POST['is_read'] !== '' ? intval($_POST['is_read']) : null,
            'is_starred' => isset($_POST['is_starred']) && $_POST['is_starred'] !== '' ? intval($_POST['is_starred']) : null,
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'limit' => intval($_POST['per_page'] ?? 20),
            'offset' => (intval($_POST['page'] ?? 1) - 1) * intval($_POST['per_page'] ?? 20),
        ];

        $entries = $database->get_all_entries($args);
        $total = $database->get_all_entries_count($args);

        wp_send_json_success([
            'entries' => $entries,
            'total' => $total,
            'page' => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 20),
            'total_pages' => ceil($total / max(1, intval($_POST['per_page'] ?? 20))),
        ]);
    }

    public function ajax_delete_form_entry()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $entry_id = intval($_POST['entry_id'] ?? 0);

        if ($entry_id <= 0) {
            wp_send_json_error(['message' => __('Invalid entry ID', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;
        if (!$database->get_form_entry($entry_id)) {
            wp_send_json_error(['message' => __('Entry not found. It may already have been deleted.', 'tahmir-ai-to-wp-converter')]);
        }

        $deleted = $database->delete_form_entry($entry_id);
        if (!$deleted) {
            wp_send_json_error(['message' => __('The entry could not be deleted. Please try again.', 'tahmir-ai-to-wp-converter')]);
        }

        wp_send_json_success([
            'message' => __('Entry deleted', 'tahmir-ai-to-wp-converter'),
        ]);
    }

    public function ajax_bulk_entries_action()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $action = sanitize_key(wp_unslash($_POST['bulk_action'] ?? ''));
        $allowed_actions = ['delete', 'mark_read', 'mark_unread'];
        if (!in_array($action, $allowed_actions, true)) {
            wp_send_json_error(['message' => __('Choose a valid bulk action.', 'tahmir-ai-to-wp-converter')]);
        }

        $raw_ids = isset($_POST['entry_ids']) ? (array) wp_unslash($_POST['entry_ids']) : [];
        $ids = array_slice(array_values(array_unique(array_filter(array_map('absint', $raw_ids)))), 0, 500);

        if (empty($ids)) {
            wp_send_json_error(['message' => __('No entries selected', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;
        $count = 0;
        $failed = 0;

        foreach ($ids as $id) {
            if (!$database->get_form_entry($id)) {
                $failed++;
                continue;
            }

            switch ($action) {
                case 'delete':
                    if ($database->delete_form_entry($id)) {
                        $count++;
                    } else {
                        $failed++;
                    }
                    break;
                case 'mark_read':
                    if ($database->mark_entry_read($id, true) !== false) {
                        $count++;
                    } else {
                        $failed++;
                    }
                    break;
                case 'mark_unread':
                    if ($database->mark_entry_read($id, false) !== false) {
                        $count++;
                    } else {
                        $failed++;
                    }
                    break;
            }
        }

        if ($count === 0) {
            wp_send_json_error(['message' => __('None of the selected entries could be updated.', 'tahmir-ai-to-wp-converter')]);
        }

        wp_send_json_success([
            // translators: %d: number of form entries updated.
            'message' => sprintf(__('%d entries updated', 'tahmir-ai-to-wp-converter'), $count),
            'count' => $count,
            'failed' => $failed,
        ]);
    }
}
