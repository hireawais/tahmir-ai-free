<?php
/** Read-only compatibility adapter for imported Free pages. Editing belongs to Pro. */
if (!defined('ABSPATH')) { exit; }
class Ugentic_Editor {
    public function __construct() {
        add_filter('template_include', [$this, 'force_ugentic_template'], 999);
        add_filter('page_template', [$this, 'force_page_template'], 999);
        add_filter('theme_page_templates', [$this, 'register_ugentic_templates']);
    }
    public function can_edit() { return false; }
    public function should_load_builder() { return false; }
    public function check_public_edit_token() {}
    public function has_public_edit_token() { return false; }
    public static function is_public_edit_enabled() { return false; }
    public static function is_ai_builder_enabled() { return false; }
    public static function is_traditional_builder_enabled() { return false; }
    public static function request_has_valid_public_edit_token($allow_cookie = false) { return false; }
    public static function get_public_edit_token() { return ''; }
    public static function get_public_edit_url($url = '') { return ''; }
    public function register_ugentic_templates($templates) {
        $templates['ugentic-blank'] = __('Tahmir AI Blank Canvas', 'tahmir-ai-to-wp-converter');
        $templates['ugentic-faithful'] = __('Tahmir AI Faithful Canvas', 'tahmir-ai-to-wp-converter');
        $templates['ugentic-faithful-blog'] = __('Tahmir AI Faithful Blog', 'tahmir-ai-to-wp-converter');
        return $templates;
    }
    public function force_ugentic_template($template)
    {
        if (!is_singular('page')) {
            return $template;
        }

        global $post;
        if (!$post) {
            return $template;
        }

        $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);

        if (!empty($ugentic_page_id)) {
            $current_template = get_post_meta($post->ID, '_wp_page_template', true);

            // Faithful-mode pages: serve the full HTML document directly
            if ($current_template === 'ugentic-faithful') {
                $faithful_tpl = UGENTIC_PLUGIN_DIR . 'templates/faithful-template.php';
                if (file_exists($faithful_tpl)) {
                    return $faithful_tpl;
                }
            }

            // Faithful-blog pages: blog template with faithful page design
            if ($current_template === 'ugentic-faithful-blog') {
                $blog_tpl = UGENTIC_PLUGIN_DIR . 'templates/faithful-blog-template.php';
                if (file_exists($blog_tpl)) {
                    return $blog_tpl;
                }
            }

            // Wizard-generated pages use page-template.php for theme header/footer integration
            if ($current_template === 'page-template.php' || $current_template === 'ugentic-template') {
                $page_tpl = UGENTIC_PLUGIN_DIR . 'templates/page-template.php';
                if (file_exists($page_tpl)) {
                    return $page_tpl;
                }
            }

            // Blank-canvas pages (advanced-mode imported)
            if ($current_template === 'ugentic-blank' || $current_template === 'templates/blank-template.php') {
                $blank_tpl = UGENTIC_PLUGIN_DIR . 'templates/blank-template.php';
                if (file_exists($blank_tpl)) {
                    return $blank_tpl;
                }
            }

            // Fallback: pages with empty/default template -- use blank-template
            // but do NOT overwrite the stored template metadata
            if (empty($current_template) || $current_template === 'default') {
                $blank_tpl = UGENTIC_PLUGIN_DIR . 'templates/blank-template.php';
                if (file_exists($blank_tpl)) {
                    return $blank_tpl;
                }
            }
        }

        return $template;
    }

    public function force_page_template($template)
    {
        global $post;
        if (!$post) {
            return $template;
        }

        // Check if this is a Tahmir AI page
        $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);

        if (!empty($ugentic_page_id)) {
            $current_template = get_post_meta($post->ID, '_wp_page_template', true);

            // Faithful pages -> faithful template
            if ($current_template === 'ugentic-faithful') {
                $faithful_tpl = UGENTIC_PLUGIN_DIR . 'templates/faithful-template.php';
                if (file_exists($faithful_tpl)) {
                    return $faithful_tpl;
                }
            }

            // Faithful blog pages -> blog template
            if ($current_template === 'ugentic-faithful-blog') {
                $blog_tpl = UGENTIC_PLUGIN_DIR . 'templates/faithful-blog-template.php';
                if (file_exists($blog_tpl)) {
                    return $blog_tpl;
                }
            }

            // All others -> blank template
            $our_template = UGENTIC_PLUGIN_DIR . 'templates/blank-template.php';
            if (file_exists($our_template)) {
                return $our_template;
            }
        }

        return $template;
    }
}
