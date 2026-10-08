<?php
/** Free edition policy and presentation. Premium implementations live in Pro. */
if (!defined('ABSPATH')) { exit; }

final class Tahmir_Free_Edition {
    public static function has_method($object, $method) { return is_object($object) && method_exists($object, $method); }
    public static function boot() { require_once __DIR__ . '/class-rendered-import.php';
        add_action('template_redirect', [__CLASS__, 'buffer_imported_page'], 0);
        add_action('admin_init', [__CLASS__, 'guard_ajax'], 0);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue'], 100);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 100);
        add_action('admin_menu', [__CLASS__, 'module_routes'], 30);
    }
    public static function buffer_imported_page() {
        if (is_singular('page') && get_post_meta(get_queried_object_id(), '_ugentic_page_id', true)) {
            ob_start([__CLASS__, 'prepare_render']);
        }
    }
    public static function allowed_actions() {
        return [
            'ugentic_upload_website', 'ugentic_import_page', 'ugentic_import_all',
            'ugentic_cleanup_import', 'ugentic_delete_import', 'ugentic_replace_internal_links',
            'ugentic_force_clear_import_lock', 'ugentic_detect_import_menus',
            'ugentic_wizard_clone_website', 'ugentic_wizard_clone_status', 'ugentic_wizard_materialize_client_shell',
            'ugentic_delete_page', 'ugentic_delete_pages_bulk', 'ugentic_delete_all_pages', 'ugentic_cleanup_orphans',
            'ugentic_form_submit', 'ugentic_faithful_form_submit',
        ];
    }
    public static function guard_ajax() {
        if (!wp_doing_ajax()) { return; }
        $action = sanitize_key(wp_unslash($_REQUEST['action'] ?? ''));
        if (strpos($action, 'ugentic_') !== 0) { return; }
        if (!in_array($action, self::allowed_actions(), true)) { self::pro_required(); }
        if (in_array($action, ['ugentic_import_page','ugentic_import_all'], true)
            && sanitize_key(wp_unslash($_POST['import_mode'] ?? 'faithful')) !== 'faithful') { self::pro_required(); }
        if ($action === 'ugentic_wizard_clone_website'
            && sanitize_key(wp_unslash($_POST['clone_assist'] ?? 'standard')) === 'ai') { self::pro_required(); }
        if ($action === 'ugentic_save_design_tokens' && ($_POST['scope'] ?? 'page') === 'site') { self::pro_required(); }
    }
    public static function pro_required() {
        wp_send_json_error([
            'code'=>'tahmir_pro_required',
            'message'=>__('This feature is included in Tahmir AI Pro. Your free cloning and import tools remain available.', 'tahmir-ai-to-wp-converter'),
            'upgrade_url'=>'https://tahmir.com/upgrade',
        ], 403);
    }
    public static function enqueue($hook = '') {
        if (is_admin()) {
            if (strpos((string)$hook, 'ugentic') === false) { return; }
        } elseif (!ugentic_builder()->editor->should_load_builder()) { return; }
        wp_enqueue_style('tahmir-local-icons', UGENTIC_PLUGIN_URL . 'public/vendor/font-awesome/css/all.min.css', [], '6.5.1');
        wp_enqueue_style('tahmir-free-edition', UGENTIC_PLUGIN_URL . 'public/css/free-edition.css', [], UGENTIC_VERSION);
        wp_enqueue_script('tahmir-free-edition', UGENTIC_PLUGIN_URL . 'public/js/free-edition.js', [], filemtime(UGENTIC_PLUGIN_DIR . 'public/js/free-edition.js'), true);
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        if (is_admin() && in_array($page, ['ugentic-conversions', 'ugentic-module-conversion_analytics', 'ugentic-module-revenue_insights', 'ugentic-module-ab_testing', 'ugentic-module-ai_conversion_agent'], true)) {
            wp_enqueue_style('tahmir-conversion-studio', UGENTIC_PLUGIN_URL . 'admin/css/conversion-studio.css', [], filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/conversion-studio.css'));
            wp_enqueue_style('tahmir-analytics-workspace', UGENTIC_PLUGIN_URL . 'admin/css/analytics-workspace.css', ['tahmir-conversion-studio'], filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/analytics-workspace.css'));
            wp_enqueue_script('tahmir-analytics-demo', UGENTIC_PLUGIN_URL . 'admin/js/analytics-demo.js', [], filemtime(UGENTIC_PLUGIN_DIR . 'admin/js/analytics-demo.js'), true);
            wp_enqueue_script('tahmir-heatmaps', UGENTIC_PLUGIN_URL . 'admin/js/analytics-heatmaps.js', [], filemtime(UGENTIC_PLUGIN_DIR . 'admin/js/analytics-heatmaps.js'), true);
            wp_enqueue_script('tahmir-conversion-studio', UGENTIC_PLUGIN_URL . 'admin/js/conversion-studio.js', ['jquery','tahmir-analytics-demo','tahmir-heatmaps'], filemtime(UGENTIC_PLUGIN_DIR . 'admin/js/conversion-studio.js'), true);
        }
    }
    public static function prepare_render($html, $builder = false) {
        // Imported forms remain usable in Free; management stays behind Pro.
        return $html;
    }
    public static function module_routes() {
        add_submenu_page('ugentic-builder', __('Upgrade', 'tahmir-ai-to-wp-converter'), __('Upgrade', 'tahmir-ai-to-wp-converter'), 'manage_options', 'ugentic-upgrade', static function () { include UGENTIC_PLUGIN_DIR . 'admin/views/upgrade.php'; });
        foreach (self::module_definitions() as $key=>$definition) {
            add_submenu_page(null, $definition['label'], $definition['menu_title'], 'manage_options', 'ugentic-module-'.$key,
                static function () use ($key, $definition) {
                    self::locked_view($key === 'seo' ? 'optimize' : ($key === 'mcp_conversion' ? 'settings' : ($key === 'client_delivery' ? 'client-delivery' : 'conversions')), $definition['label']);
                });
        }
    }
    public static function banner($title) {
        echo '<div class="tahmir-pro-banner" role="note"><span class="dashicons dashicons-lock" aria-hidden="true"></span><div><strong>'
            .esc_html($title).' · '.esc_html__('Tahmir AI Pro', 'tahmir-ai-to-wp-converter').'</strong><p>'
            .esc_html__('Install the Pro add-on to use this feature in the same Tahmir workspace. Your imported pages and saved data stay here.', 'tahmir-ai-to-wp-converter')
            .'</p></div><a class="button button-primary" href="https://tahmir.com/upgrade" target="_blank" rel="noopener noreferrer">'
            .esc_html__('Explore Pro', 'tahmir-ai-to-wp-converter').'</a></div>';
    }
    public static function locked_view($view, $title) {
        if ($view === 'conversions') {
            // An interactive sample with no premium service or real site data loaded.
            $module_context = '';
            $conversion_suite = new self();
            include UGENTIC_PLUGIN_DIR . 'admin/views/conversions.php';
            return;
        }
        if (in_array($view, ['forms-list', 'form-entries'], true)) {
            self::banner($title);
            echo '<div class="wrap ugentic-wrap"><div class="ugentic-card"><h2>'.esc_html__('Form management requires Pro', 'tahmir-ai-to-wp-converter').'</h2><p>'.esc_html__('Imported forms can receive submissions in Free. Creating or editing forms and viewing entries require an active Pro license.', 'tahmir-ai-to-wp-converter').'</p></div></div>';
            return;
        }
        if ($view === 'settings') { include UGENTIC_PLUGIN_DIR . 'admin/views/settings-free.php'; return; }
        if ($view === 'menus') { self::banner(__('Central menu management', 'tahmir-ai-to-wp-converter')); echo '<div class="wrap ugentic-wrap"><div class="ugentic-card"><h2>'.esc_html__('One place for your website navigation', 'tahmir-ai-to-wp-converter').'</h2><p>'.esc_html__('Pro lets you edit menu items, destinations and structure from the central menu manager, then apply them across the relevant pages. Your imported navigation remains available in Free.', 'tahmir-ai-to-wp-converter').'</p></div></div>'; return; }
        self::banner($title);
        // These are the original views, rendered with empty preview data. No
        // Premium editing, entry-management and API handlers are not loaded.
        $database=ugentic_builder()->database;
        $settings=[]; $api_key=''; $api_configured=false; $masked_key=''; $license_data=[];
        $api=ugentic_builder()->api;
        $forms=[]; $pages=[]; $assets=[]; $css_assets=[]; $js_assets=[]; $header_assets=[]; $footer_assets=[];
        $global_headers=[]; $global_footers=[]; $history=[]; $logs=[]; $stats=[];
        $module_definitions=self::module_definitions();
        $modules=array_fill_keys(array_keys($module_definitions),false);
        $conversion_suite=new self();
        $module_context=str_replace('ugentic-module-', '', sanitize_key(wp_unslash($_GET['page'] ?? '')));
        if (!isset($module_definitions[$module_context])) { $module_context=''; }
        $safe_views=['optimize','global-sections','asset-conflicts','accessibility','change-history','api-usage','client-delivery','conversions','forms-list','form-entries','assets','menus','all-pages','generate','design-vision','settings','modules'];
        echo '<div class="tahmir-pro-preview" data-tahmir-pro-preview="true">';
        if (in_array($view, $safe_views, true)) {
            ob_start();
            include UGENTIC_PLUGIN_DIR . 'admin/views/' . $view . '.php';
            $markup=ob_get_clean();
            // Inline scripts can process actions independently of disabled inputs.
            echo preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $markup); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- original views escape each dynamic value.
        } else {
            Ugentic_Admin_UI::page_header(['title'=>$title,'description'=>__('Available with the Tahmir AI Pro add-on.', 'tahmir-ai-to-wp-converter'),'icon'=>'dashicons-lock']);
            Ugentic_Admin_UI::hub_page(Ugentic_Admin_Menu::get_hub('optimize'));
        }
        echo '</div>';
    }
    
    public function admin_tab_for_module($key)
    {
        if ($key === 'ab_testing') {
            return 'experiments';
        }
        if ($key === 'ai_conversion_agent') {
            return 'intelligence';
        }
        return 'overview';
    }
    
    public static function accessibility_options()
    {
        return [
            'text_size' => [
                'label' => __('Text Size', 'tahmir-ai-to-wp-converter'),
                'description' => __('Let visitors increase or decrease readable text.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Reading', 'tahmir-ai-to-wp-converter'),
            ],
            'line_height' => [
                'label' => __('Line Height', 'tahmir-ai-to-wp-converter'),
                'description' => __('Add more vertical space between lines of readable content.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Reading', 'tahmir-ai-to-wp-converter'),
            ],
            'text_spacing' => [
                'label' => __('Text Spacing', 'tahmir-ai-to-wp-converter'),
                'description' => __('Increase letter and word spacing for easier scanning.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Reading', 'tahmir-ai-to-wp-converter'),
            ],
            'readable_font' => [
                'label' => __('Readable Font', 'tahmir-ai-to-wp-converter'),
                'description' => __('Switch text to a simpler system font stack.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Reading', 'tahmir-ai-to-wp-converter'),
            ],
            'reading_guide' => [
                'label' => __('Reading Guide', 'tahmir-ai-to-wp-converter'),
                'description' => __('Show a horizontal guide that follows the visitor while reading.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Reading', 'tahmir-ai-to-wp-converter'),
            ],
            'contrast' => [
                'label' => __('High Contrast', 'tahmir-ai-to-wp-converter'),
                'description' => __('Improve text, link, and button contrast without filtering the whole page.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Visual', 'tahmir-ai-to-wp-converter'),
            ],
            'grayscale' => [
                'label' => __('Grayscale', 'tahmir-ai-to-wp-converter'),
                'description' => __('Remove color from page visuals.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Visual', 'tahmir-ai-to-wp-converter'),
            ],
            'saturation' => [
                'label' => __('Color Saturation', 'tahmir-ai-to-wp-converter'),
                'description' => __('Increase color intensity for visitors who need stronger visual separation.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Visual', 'tahmir-ai-to-wp-converter'),
            ],
            'hide_images' => [
                'label' => __('Hide Images', 'tahmir-ai-to-wp-converter'),
                'description' => __('Reduce visible media while keeping page spacing stable.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Visual', 'tahmir-ai-to-wp-converter'),
            ],
            'underline_links' => [
                'label' => __('Link Highlight', 'tahmir-ai-to-wp-converter'),
                'description' => __('Underline and highlight links so they are easier to identify.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Navigation', 'tahmir-ai-to-wp-converter'),
            ],
            'focus_highlight' => [
                'label' => __('Focus Highlight', 'tahmir-ai-to-wp-converter'),
                'description' => __('Make keyboard focus states more visible.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Navigation', 'tahmir-ai-to-wp-converter'),
            ],
            'pause_animations' => [
                'label' => __('Pause Animations', 'tahmir-ai-to-wp-converter'),
                'description' => __('Pause animations, transitions, and smooth scrolling.', 'tahmir-ai-to-wp-converter'),
                'group' => __('Motion', 'tahmir-ai-to-wp-converter'),
            ],
        ];
    }
    

    public static function module_definitions()
    {
        return [
            'conversion_analytics' => [
                'label' => __('Website Analytics', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Analytics', 'tahmir-ai-to-wp-converter'),
                'description' => __('Lightweight first-party visitors, sessions, bounce rate, sources, page paths, and individual journeys.', 'tahmir-ai-to-wp-converter'),
                'benefit' => __('See how people move through the website without a heavy third-party analytics script.', 'tahmir-ai-to-wp-converter'),
                'group' => 'measure',
                'default' => false,
            ],
            'revenue_insights' => [
                'label' => __('Revenue Insights', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Revenue', 'tahmir-ai-to-wp-converter'),
                'description' => __('WooCommerce revenue, refunds, marketing attribution, and MCP payment checks are available in Pro.', 'tahmir-ai-to-wp-converter'),
                'benefit' => __('See which recorded sources bring paying customers.', 'tahmir-ai-to-wp-converter'),
                'group' => 'measure',
                'default' => false,
            ],
            'ab_testing' => [
                'label' => __('Experiments & Smart Traffic', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Experiments', 'tahmir-ai-to-wp-converter'),
                'description' => __('A/B test complete pages with percentage allocation, sticky visitors, source rules, and adaptive traffic.', 'tahmir-ai-to-wp-converter'),
                'benefit' => __('Run one complete testing workflow instead of managing separate A/B, personalization, and traffic modules.', 'tahmir-ai-to-wp-converter'),
                'group' => 'experiment',
                'default' => false,
            ],
            'ai_conversion_agent' => [
                'label' => __('AI Growth Assistant', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('AI Growth', 'tahmir-ai-to-wp-converter'),
                'description' => __('Evidence-backed recommendations, website data chat, and one-click draft variants in one focused assistant.', 'tahmir-ai-to-wp-converter'),
                'benefit' => __('Move from performance data to a reviewable improvement without switching between duplicate AI tools.', 'tahmir-ai-to-wp-converter'),
                'group' => 'intelligence',
                'default' => false,
            ],
            'mcp_conversion' => [
                'label' => __('MCP Access', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('MCP Access', 'tahmir-ai-to-wp-converter'),
                'description' => __('Create and manage authenticated access for trusted MCP clients and conversion tools.', 'tahmir-ai-to-wp-converter'),
                'benefit' => __('Connect Claude, Codex, and other trusted tools from a dedicated access and token workspace.', 'tahmir-ai-to-wp-converter'),
                'group' => 'connect',
                'default' => false,
            ],
            'client_delivery' => [
                'label' => __('Client Delivery', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('Client Delivery', 'tahmir-ai-to-wp-converter'),
                'description' => __('Create separate, expiring access URLs so clients can edit without a WordPress administrator account.', 'tahmir-ai-to-wp-converter'),
                'benefit' => __('Give each client only AI editing, only visual editing, or both—and revoke their link at any time.', 'tahmir-ai-to-wp-converter'),
                'group' => 'delivery',
                'default' => false,
            ],
            'seo' => [
                'label' => __('SEO & AI Search', 'tahmir-ai-to-wp-converter'),
                'menu_title' => __('SEO & AI Search', 'tahmir-ai-to-wp-converter'),
                'description' => __('Technical SEO, metadata, structured data, answer-ready summaries, and AI discovery checks together.', 'tahmir-ai-to-wp-converter'),
                'benefit' => __('Improve traditional search and AI answer visibility from one audit and recommendation workflow.', 'tahmir-ai-to-wp-converter'),
                'group' => 'search',
                'default' => true,
            ],
        ];
    }
}

/** Preserve source form HTML while registering a working submission backend. */
final class Tahmir_Imported_Form_Preview {
    private $converter;
    public function __construct() { $this->converter = new Ugentic_Form_Converter(); }
    public function detect_and_register_forms($html, $options=[]) {
        return $this->converter->detect_and_register_forms($html, $options);
    }
}
