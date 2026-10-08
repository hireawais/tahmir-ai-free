<?php
/**
 * Shared Tahmir engine for the Free edition.
 * The installable plugin metadata is declared in tahmir-ai.php.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('UGENTIC_VERSION', TAHMIR_FREE_VERSION);
define('UGENTIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UGENTIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UGENTIC_PLUGIN_BASENAME', plugin_basename(TAHMIR_FREE_FILE));
define('UGENTIC_BRAND_LOGO_URL', UGENTIC_PLUGIN_URL . 'public/images/tahmir-brand.png');

/**
 * Main Tahmir AI Class
 */
final class Ugentic_Builder
{

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $admin;
    public $api;
    public $assets;
    public $database;
    public $importer;
    public $editor;
    public $ai_features;
    public $visual_editor;
    public $wizard;
    public $license;
    public $whitelabel;
    public $updater;
    public $form_renderer;
    public $design_intelligence;
    public $design_vision;
    public $visual_qa;
    public $agent_standard;
    public $seo;
    public $global_sections;
    public $blog_generator;
    public $wp_operations;
    public $blueprint;
    public $agency_manager;
    public $accessibility;
    public $mcp_rest;
    public $conversion_suite;
    public $client_delivery;

    /**
     * Get single instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load composer autoloader and class files
     */private function load_dependencies() {
require_once UGENTIC_PLUGIN_DIR . 'includes/class-free-edition.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-database.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-form-engine.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-form-converter.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-form-renderer.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-security.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-html-cleaner.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-api.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-assets.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-admin-menu.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-admin-ui.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-admin.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-smart-resolver.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-legacy-detector.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-css-normalizer.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-blog-page-detector.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-importer.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-editor.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-menu-manager.php';
require_once UGENTIC_PLUGIN_DIR . 'includes/class-wizard.php';
}

    /**
     * Initialize hooks
     */private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init_components'], 25);
        add_action('after_setup_theme', [$this, 'register_nav_menus']);
    }

    /**
     * Cron callback: delete history entries older than 3 days
     */

    /**
     * Initialize plugin components
     */public function init_components() {
    $this->license = null;
    $this->database = new Ugentic_Database();
    $this->database->maybe_upgrade();
    $this->api = new Ugentic_API();
    $this->admin = new Ugentic_Admin();
    $this->assets = new Ugentic_Assets();
    $this->importer = new Ugentic_Importer();
    $this->editor = new Ugentic_Editor();
    $this->form_renderer = new Ugentic_Form_Renderer();
    $this->visual_editor = null;
    $this->wizard = new Ugentic_Wizard();
    Tahmir_Free_Edition::boot();
    $this->repair_page_templates();
}

    /**
     * Repair Tahmir AI pages that have lost their template assignment
     */
    private function repair_page_templates()
    {
        // Only run this check once per request and in admin
        static $repaired = false;
        if ($repaired || !is_admin()) {
            return;
        }
        $repaired = true;

        // Get all Tahmir AI pages from our database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ugentic_pages';

        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return;
        }

        $pages = $wpdb->get_results("SELECT id, wp_page_id, meta_data FROM $table_name WHERE wp_page_id IS NOT NULL");

        if (empty($pages)) {
            return;
        }

        foreach ($pages as $page) {
            $current_template = get_post_meta($page->wp_page_id, '_wp_page_template', true);

            // If template is empty, default, or set to the wrong slug, fix it
            if (empty($current_template) || $current_template === 'default' || $current_template === '' || $current_template === 'templates/blank-template.php') {
                // Check the Ugentic DB metadata for import_mode to determine the correct template
                $meta = json_decode($page->meta_data ?? '{}', true);
                $import_mode = $meta['import_mode'] ?? '';

                if ($import_mode === 'faithful') {
                    update_post_meta($page->wp_page_id, '_wp_page_template', 'ugentic-faithful');
                } else {
                    update_post_meta($page->wp_page_id, '_wp_page_template', 'ugentic-blank');
                }
            }
        }
    }

    /**
     * Plugin activation
     */public function activate() { $db = new Ugentic_Database(); $db->create_tables(); }

    /**
     * Plugin deactivation
     */public function deactivate() { wp_clear_scheduled_hook('ugentic_wizard_run_clone_job'); }

    /**
     * Register WordPress nav menu locations
     */
    public function register_nav_menus()
    {
        register_nav_menus([
            'ugentic-header' => __('Tahmir AI Header Menu', 'tahmir-ai-to-wp-converter'),
            'ugentic-footer' => __('Tahmir AI Footer Menu', 'tahmir-ai-to-wp-converter'),
            'ugentic-mobile' => __('Tahmir AI Mobile Menu', 'tahmir-ai-to-wp-converter'),
        ]);

        // Register Customizer controls for CTA buttons
        add_action('customize_register', [$this, 'register_customizer_controls']);
    }

    /**
     * Register WordPress Customizer controls for CTA button configuration.
     */
    public function register_customizer_controls($wp_customize)
    {
        // ── Section ──
        $wp_customize->add_section('ugentic_navigation', [
            'title' => __('Tahmir AI Navigation', 'tahmir-ai-to-wp-converter'),
            'priority' => 30,
        ]);

        // ── Header CTA Button Text ──
        $wp_customize->add_setting('ugentic_header_cta_text', [
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport' => 'postMessage',
        ]);
        $wp_customize->add_control('ugentic_header_cta_text', [
            'label' => __('Header CTA Button Text', 'tahmir-ai-to-wp-converter'),
            'section' => 'ugentic_navigation',
            'type' => 'text',
        ]);

        // ── Header CTA Button URL ──
        $wp_customize->add_setting('ugentic_header_cta_url', [
            'default' => '#',
            'sanitize_callback' => 'esc_url_raw',
            'transport' => 'postMessage',
        ]);
        $wp_customize->add_control('ugentic_header_cta_url', [
            'label' => __('Header CTA Button URL', 'tahmir-ai-to-wp-converter'),
            'section' => 'ugentic_navigation',
            'type' => 'url',
        ]);

        // ── Header CTA Visible ──
        $wp_customize->add_setting('ugentic_header_cta_visible', [
            'default' => true,
            'sanitize_callback' => function ($v) {
                return (bool) $v;
            },
        ]);
        $wp_customize->add_control('ugentic_header_cta_visible', [
            'label' => __('Show Header CTA Button', 'tahmir-ai-to-wp-converter'),
            'section' => 'ugentic_navigation',
            'type' => 'checkbox',
        ]);

        // ── Footer CTA Button Text ──
        $wp_customize->add_setting('ugentic_footer_cta_text', [
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport' => 'postMessage',
        ]);
        $wp_customize->add_control('ugentic_footer_cta_text', [
            'label' => __('Footer CTA Button Text', 'tahmir-ai-to-wp-converter'),
            'section' => 'ugentic_navigation',
            'type' => 'text',
        ]);

        // ── Footer CTA Button URL ──
        $wp_customize->add_setting('ugentic_footer_cta_url', [
            'default' => '#',
            'sanitize_callback' => 'esc_url_raw',
            'transport' => 'postMessage',
        ]);
        $wp_customize->add_control('ugentic_footer_cta_url', [
            'label' => __('Footer CTA Button URL', 'tahmir-ai-to-wp-converter'),
            'section' => 'ugentic_navigation',
            'type' => 'url',
        ]);
    }

    /**
     * Load translations
     */

    /**
     * Get plugin setting
     */
    public static function get_setting($key, $default = '')
    {
        $settings = get_option('ugentic_settings', []);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Update plugin setting
     */
    public static function update_setting($key, $value)
    {
        $settings = get_option('ugentic_settings', []);
        $settings[$key] = $value;
        update_option('ugentic_settings', $settings);
    }
}

/**
 * Get plugin instance
 */
function ugentic_builder()
{
    return Ugentic_Builder::instance();
}

// Initialize plugin
ugentic_builder();
