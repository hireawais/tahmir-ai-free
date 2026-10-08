<?php
/**
 * Assets Manager for Tahmir AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Assets
{

    /** Print imported CSS through WordPress while preserving its page order. */
    public static function print_inline_css($id, $css)
    {
        $handle = 'tahmir-import-css-' . sanitize_key($id);
        wp_register_style($handle, false, [], UGENTIC_VERSION);
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, (string) $css);
        wp_print_styles($handle);
    }

    /** Print an imported stylesheet through the registered style API. */
    public static function print_external_css($id, $url)
    {
        $handle = 'tahmir-import-css-' . sanitize_key($id);
        wp_register_style($handle, esc_url_raw($url), [], null);
        wp_enqueue_style($handle);
        wp_print_styles($handle);
    }

    /** Print imported JavaScript through WordPress while preserving its page order. */
    public static function print_inline_js($id, $javascript)
    {
        $handle = 'tahmir-import-js-' . sanitize_key($id);
        wp_register_script($handle, false, [], UGENTIC_VERSION, true);
        wp_enqueue_script($handle);
        wp_add_inline_script($handle, (string) $javascript);
        wp_print_scripts($handle);
    }

    /** Print an imported script through the registered script API. */
    public static function print_external_js($id, $url)
    {
        $handle = 'tahmir-import-js-' . sanitize_key($id);
        wp_register_script($handle, esc_url_raw($url), [], null, true);
        wp_enqueue_script($handle);
        wp_print_scripts($handle);
    }

    /** Return a WordPress-printed asset for insertion into an imported document. */
    public static function render_asset($kind, $id, $value)
    {
        ob_start();
        switch ($kind) {
            case 'css':
                self::print_external_css($id, $value);
                break;
            case 'js':
                self::print_external_js($id, $value);
                break;
            case 'inline-js':
                self::print_inline_js($id, $value);
                break;
        }
        return ob_get_clean();
    }

    /** Print a locally verified imported script with its integrity and loading attributes. */
    public static function render_verified_js($id, $src, $sri, $attributes = [])
    {
        $id = 'verified-' . $id;
        $handle = 'tahmir-import-js-' . sanitize_key($id);
        $filter = static function ($tag, $printed_handle) use ($handle, $sri, $attributes) {
            if ($printed_handle !== $handle) {
                return $tag;
            }
            $processor = new WP_HTML_Tag_Processor($tag);
            if (!$processor->next_tag('SCRIPT')) {
                return '';
            }
            $processor->set_attribute('integrity', $sri);
            $type = strtolower((string) ($attributes['type'] ?? ''));
            if (in_array($type, ['module', 'text/javascript', 'application/javascript'], true)) {
                $processor->set_attribute('type', $type);
            }
            foreach (['defer', 'async', 'nomodule'] as $attribute) {
                if (!empty($attributes[$attribute])) {
                    $processor->set_attribute($attribute, '');
                }
            }
            return $processor->get_updated_html();
        };
        add_filter('script_loader_tag', $filter, 10, 2);
        $markup = self::render_asset('js', $id, $src);
        remove_filter('script_loader_tag', $filter, 10);
        return $markup;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add filter to inject assets on Tahmir AI pages
        add_filter('the_content', [$this, 'filter_page_content'], 99);
        add_action('wp_head', [$this, 'output_global_css'], 100);
        add_action('wp_footer', [$this, 'output_global_footer'], 20);
        add_action('wp_footer', [$this, 'output_global_js'], 100);

        // Early hook to remove WP global styles BEFORE wp_enqueue_scripts fires
        add_action('template_redirect', [$this, 'remove_wp_global_styles'], 1);
        add_action('template_redirect', [$this, 'remove_incompatible_learndash_template_filters'], 2);

        // Late hook to dequeue any remaining theme styles
        add_action('wp_enqueue_scripts', [$this, 'dequeue_theme_styles'], 999);
    }

    /**
     * Prevent strict third-party LearnDash template filters from crashing Tahmir pages.
     *
     * Some LearnDash add-ons type-hint the learner template $args parameter as
     * array, while LearnDash itself can pass null for non-topic templates during
     * wp_enqueue_scripts. Tahmir faithful pages call wp_head() manually, so remove
     * the incompatible callback only for Tahmir-owned page renders.
     */
    public function remove_incompatible_learndash_template_filters()
    {
        if (is_admin() || !is_singular('page')) {
            return;
        }

        global $post, $wp_filter;
        if (!$post || empty($wp_filter['learndash_template'])) {
            return;
        }

        $template = get_page_template_slug($post->ID);
        $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);
        $is_ugentic = !empty($ugentic_page_id)
            || in_array($template, ['ugentic-blank', 'templates/blank-template.php', 'ugentic-faithful', 'ugentic-faithful-blog'], true);
        if (!$is_ugentic) {
            return;
        }

        $hook = $wp_filter['learndash_template'];
        if (!is_object($hook) || empty($hook->callbacks) || !is_array($hook->callbacks)) {
            return;
        }

        foreach ($hook->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'] ?? null;
                if (
                    is_array($function)
                    && isset($function[0], $function[1])
                    && is_object($function[0])
                    && get_class($function[0]) === 'EFL\\Frontend\\TopicEnhancements'
                    && $function[1] === 'override_topic_list'
                ) {
                    remove_filter('learndash_template', $function, (int) $priority);
                }
            }
        }
    }

    /**
     * Remove WordPress global styles on Ugentic blank-canvas pages
     * Must fire BEFORE wp_enqueue_scripts to prevent inline theme.json CSS
     */
    public function remove_wp_global_styles()
    {
        if (!is_singular('page')) {
            return;
        }

        global $post;

        // Check if this is a Tahmir AI page by template slug OR by _ugentic_page_id meta
        // The meta check is a reliable fallback since template slug can be incorrect
        $template = get_page_template_slug($post->ID);
        // Faithful pages serve their own full HTML document — keep all original styles
        if ($template === 'ugentic-faithful' || $template === 'ugentic-faithful-blog') {
            return;
        }
        $is_ugentic = ($template === 'ugentic-blank' || $template === 'templates/blank-template.php');
        if (!$is_ugentic) {
            $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);
            $is_ugentic = !empty($ugentic_page_id);
        }
        if (!$is_ugentic) {
            return;
        }

        // Remove WordPress global styles (theme.json inline CSS)
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);

        // Remove block supports styles
        remove_action('wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles');

        // Remove SVG filters (duotone etc)
        remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');

        // Remove block library styles (Gutenberg block CSS)
        add_action('wp_enqueue_scripts', function () {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('wp-block-paragraph');
            wp_dequeue_style('wp-block-heading');
            wp_dequeue_style('wp-block-list');
            wp_dequeue_style('wp-block-image');
            wp_dequeue_style('wp-block-columns');
            wp_dequeue_style('wp-block-group');
            wp_dequeue_style('wp-block-navigation');
            wp_dequeue_style('wp-block-buttons');
            wp_dequeue_style('wp-block-button');
            wp_dequeue_style('global-styles');
        }, 50);

        // Remove layout support inline styles
        remove_filter('render_block', 'wp_render_layout_support_flag', 10);
        remove_filter('render_block', 'gutenberg_render_layout_support_flag', 10);

        // Remove Customizer CSS output
        remove_action('wp_head', 'wp_custom_css_cb', 101);
        remove_action('wp_head', 'wp_custom_css_cb', 11);

        // Remove block supports inline styles
        remove_filter('render_block', 'wp_render_block_support_styles', 10);
    }

    /**
     * Dequeue all theme styles and scripts on Ugentic blank-canvas pages
     * Prevents WordPress theme CSS from overriding imported page layouts
     */
    public function dequeue_theme_styles()
    {
        if (!is_singular('page')) {
            return;
        }

        global $post;

        // Check if this is a Tahmir AI page by template slug OR by _ugentic_page_id meta
        $template = get_page_template_slug($post->ID);
        // Faithful pages serve their own full HTML document — keep all original styles
        if ($template === 'ugentic-faithful' || $template === 'ugentic-faithful-blog') {
            return;
        }
        $is_ugentic = ($template === 'ugentic-blank' || $template === 'templates/blank-template.php');
        if (!$is_ugentic) {
            $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);
            $is_ugentic = !empty($ugentic_page_id);
        }
        if (!$is_ugentic) {
            return;
        }

        // Dequeue ALL registered styles except admin bar and ugentic
        // We are aggressive here because on blank canvas pages, only imported CSS should apply
        global $wp_styles;
        if ($wp_styles) {
            $keep_styles = [
                'admin-bar', 'dashicons', 'font-awesome-6',
                // Native media picker styles used by Tahmir image controls.
                'buttons', 'media-views', 'imgareaselect', 'wp-mediaelement',
                'mediaelement', 'wp-jquery-ui-dialog',
            ];
            foreach ($wp_styles->registered as $handle => $style) {
                if (in_array($handle, $keep_styles, true))
                    continue;
                if (strpos($handle, 'ugentic') !== false)
                    continue;
                // Also clear any inline styles attached to this handle
                if (isset($wp_styles->registered[$handle])) {
                    $wp_styles->registered[$handle]->extra = [];
                }
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }

        // Explicitly kill WordPress global styles and block library
        // These inject display:flex on body and .wp-site-blocks, breaking imported layouts
        wp_dequeue_style('global-styles');
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('classic-theme-styles');

        // Remove global styles inline CSS action that can fire after dequeue
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);

        // Dequeue ALL non-essential scripts, not just /themes/ path
        // On blank canvas pages, only jQuery core and ugentic scripts should load
        global $wp_scripts;
        if ($wp_scripts) {
            $keep_scripts = [
                'admin-bar', 'jquery', 'jquery-core', 'jquery-migrate',
                'wp-hooks', 'wp-i18n', 'wp-a11y', 'wp-dom-ready',
            ];

            // Preserve the complete dependency trees of scripts that survive
            // this cleanup. In particular, wp_enqueue_media() relies on
            // wp-i18n, wp-a11y, wp-util and other /js/dist/ handles; removing
            // those dependencies leaves the Tahmir editor unable to start.
            $preserved_dependencies = [];
            $collect_dependencies = function ($handle) use (&$collect_dependencies, &$preserved_dependencies, $wp_scripts) {
                if (isset($preserved_dependencies[$handle])) return;
                $preserved_dependencies[$handle] = true;
                $registered = $wp_scripts->registered[$handle] ?? null;
                if (!$registered) return;
                foreach ((array) $registered->deps as $dependency) $collect_dependencies($dependency);
            };
            foreach ($wp_scripts->registered as $handle => $script) {
                $src = $script->src ?? '';
                $blocked = strpos($src, '/themes/') !== false
                    || strpos($src, '/wp-includes/blocks/') !== false
                    || strpos($src, '/wp-includes/js/dist/') !== false
                    || strpos($handle, 'wp-block-') === 0;
                if (!$blocked || in_array($handle, $keep_scripts, true) || strpos($handle, 'ugentic') !== false) {
                    $collect_dependencies($handle);
                }
            }
            foreach ($wp_scripts->registered as $handle => $script) {
                if (in_array($handle, $keep_scripts, true))
                    continue;
                if (strpos($handle, 'ugentic') !== false)
                    continue;
                // Core wp-* runtimes are safe on the blank canvas and are
                // required by the media picker/editor. Block front-end
                // scripts remain excluded below.
                if (strpos($handle, 'wp-') === 0 && strpos($handle, 'wp-block-') !== 0)
                    continue;
                if (isset($preserved_dependencies[$handle]))
                    continue;
                $src = $script->src ?? '';
                // Remove ALL theme, block, and wp-includes scripts
                if (
                strpos($src, '/themes/') !== false ||
                strpos($src, '/wp-includes/blocks/') !== false ||
                strpos($src, '/wp-includes/js/dist/') !== false ||
                strpos($handle, 'wp-block-') === 0
                ) {
                    wp_dequeue_script($handle);
                }
            }
        }
    }

    /**
     * Filter page content to add header/footer
     * Wraps content in .ugentic-page for CSS namespacing
     */
    public function filter_page_content($content)
    {
        if (!is_singular('page')) {
            return $content;
        }

        global $post;

        // Check if this is a Tahmir AI page
        $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);

        if (empty($ugentic_page_id)) {
            return $content;
        }

        $database = ugentic_builder()->database;
        $page = $database->get_page($ugentic_page_id);

        if (!$page) {
            return $content;
        }

        // Use current_html from our database
        $html = $page->current_html;

        // Strip any remaining <script src="..."> tags with broken temp-directory paths
        // These are leftover from imports before the fix was added to strip scripts during import
        $html = preg_replace('/<script[^>]+src=["\'][^"\']*(?:ugentic-import-|\/Users\/)[^"\']*["\'][^>]*>\s*<\/script>/i', '', $html);

        // Add global header
        $header = $database->get_global_header();
        if ($header && !empty($header->content)) {
            $html = $header->content . "\n" . $html;
        }

        // Add global footer
        $footer = $database->get_global_footer();
        if ($footer && !empty($footer->content)) {
            $html = $html . "\n" . $footer->content;
        }

        // Wrap in #ugentic-page-content container for CSS scoping
        // CSS selectors are scoped to #ugentic-page-content by the normalizer at import time
        $html = '<div id="ugentic-page-content" class="ugentic-page-content ugentic-page">' . $html . '</div>';

        // Process ALL WordPress shortcodes in the Ugentic HTML.
        // This filter runs at priority 99, AFTER WP's own do_shortcode (priority 11),
        // so shortcodes in the DB HTML would otherwise appear as raw text.
        // We protect <style> and <script> blocks first to prevent do_shortcode()
        // from mangling bracket patterns in CSS/JS (e.g. content:"[icon]", data[0]).
        $html = self::safe_do_shortcode($html);

        return $html;
    }

    /**
     * Render attached/global footer HTML for faithful Tahmir pages.
     *
     * Blank-canvas templates render their attached footer directly. Faithful
     * imports preserve full documents and only inject wp_footer() output, so
     * they need this guarded hook for MCP-created footer assets to appear.
     */
    public function output_global_footer()
    {
        if (class_exists('Tahmir_Rendered_Import') && Tahmir_Rendered_Import::capture_preview()) { return; }
        if (is_admin() || !is_singular('page')) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);
        if (empty($ugentic_page_id)) {
            return;
        }

        $template = get_page_template_slug($post->ID);
        if ($template === 'ugentic-blank' || $template === 'templates/blank-template.php') {
            return;
        }

        $database = ugentic_builder()->database;
        $page = $database->get_page(absint($ugentic_page_id));
        if (!$page) {
            return;
        }

        // Complete imported documents use only explicitly attached sections.
        if (in_array($template, ['ugentic-faithful', 'ugentic-faithful-blog'], true) && empty($page->global_footer_id)) return;

        $footer = !empty($page->global_footer_id)
            ? $database->get_asset(absint($page->global_footer_id))
            : $database->get_global_footer();

        if (!$footer || empty($footer->is_active) || empty($footer->content)) {
            return;
        }

        $content = $footer->content;
        if (class_exists('Ugentic_Security') && !Ugentic_Security::imported_scripts_are_allowed()) {
            $content = Ugentic_Security::strip_imported_active_content($content);
        }
        if (class_exists('Ugentic_HTML_Cleaner')) {
            $content = Ugentic_HTML_Cleaner::scrub_admin_bar_artifacts($content);
        }

        echo "\n" . '<div id="ugentic-global-footer" role="contentinfo" aria-label="' . esc_attr__('Site footer', 'tahmir-ai-to-wp-converter') . '" data-ugentic-global="footer" data-ugentic-no-save="true">';
        // The saved administrator-authored layout must retain HTML; active imported content is removed above.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized imported layout markup.
        echo $content;
        echo '</div>' . "\n";
    }

    /**
     * Inject page-attached header/footer assets into a faithful full document.
     * These assets stay outside the saved page body and are therefore edited
     * once from Tahmir's global asset tools instead of being duplicated.
     */
    public function inject_faithful_sections($html, $wp_page_id, $is_builder_request = false)
    {
        $html = (string) $html;
        $wp_page_id = absint($wp_page_id);
        if ($html === '' || !$wp_page_id) {
            return $html;
        }
        $database = ugentic_builder()->database;
        $page = $database->get_page_by_wp_id($wp_page_id);
        if (!$page) {
            return $html;
        }

        $header = !empty($page->global_header_id) ? $database->get_asset(absint($page->global_header_id)) : null;
        $footer = !empty($page->global_footer_id) ? $database->get_asset(absint($page->global_footer_id)) : null;
        if (!$header && !$footer) {
            return $html;
        }

        if ($header && !empty($header->content) && strpos($html, 'data-ugentic-global-asset="header"') === false) {
            $content = $this->mark_faithful_section_root($header->content, 'header', $header->id);
            if (preg_match('/<body\b[^>]*>/i', $html)) {
                $html = preg_replace('/(<body\b[^>]*>)/i', '$1' . "\n" . $content, $html, 1);
            } else {
                $html = $content . "\n" . $html;
            }
        }
        if ($footer && !empty($footer->content) && strpos($html, 'data-ugentic-global-asset="footer"') === false) {
            $content = $this->mark_faithful_section_root($footer->content, 'footer', $footer->id);
            if (preg_match('/<\/body>/i', $html)) {
                $html = preg_replace('/<\/body>/i', $content . "\n</body>", $html, 1);
            } else {
                $html .= "\n" . $content;
            }

            // output_global_footer() is also attached to wp_footer. The page-
            // attached asset has already been placed at its source position.
            remove_action('wp_footer', [$this, 'output_global_footer'], 20);
        }
        return $html;
    }

    private function mark_faithful_section_root($content, $type, $asset_id)
    {
        $content = (string) $content;
        if (class_exists('Ugentic_HTML_Cleaner')) {
            $content = Ugentic_HTML_Cleaner::scrub_admin_bar_artifacts($content);
        }
        return preg_replace_callback('/<([a-z][a-z0-9:-]*)\b([^>]*)>/i', function ($matches) use ($type, $asset_id) {
            $attrs = preg_replace('/\s+data-ugentic-global-asset\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $matches[2]);
            $attrs = preg_replace('/\s+data-ugentic-global-asset-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);
            $attrs = preg_replace('/\s+data-ugentic-no-save\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);
            return '<' . $matches[1] . $attrs
                . ' data-ugentic-global-asset="' . esc_attr($type) . '"'
                . ' data-ugentic-global-asset-id="' . absint($asset_id) . '"'
                . ' data-ugentic-no-save="ai-clone-global-' . esc_attr($type) . '">';
        }, $content, 1);
    }

    /**
     * Run do_shortcode() safely on imported HTML.
     *
     * Protects <style> and <script> blocks from being mangled by WP shortcode
     * processing (bracket patterns like content:"[icon]" or data[0] would be
     * misinterpreted as shortcodes). Extracts them, runs do_shortcode() on the
     * remaining HTML, then re-inserts the protected blocks.
     *
     * @param string $html Full HTML content.
     * @return string HTML with all shortcodes processed.
     */
    public static function safe_do_shortcode($html)
    {
        // Quick bail: no brackets at all means no shortcodes
        if (strpos($html, '[') === false) {
            return $html;
        }

        // 1. Extract and protect <style> blocks
        $style_placeholders = [];
        $html = preg_replace_callback('/<style\b[^>]*>.*?<\/style>/is', function ($match) use (&$style_placeholders) {
            $key = '<!--UGENTIC_STYLE_' . count($style_placeholders) . '-->';
            $style_placeholders[$key] = $match[0];
            return $key;
        }, $html);

        // 2. Extract and protect <script> blocks
        $script_placeholders = [];
        $html = preg_replace_callback('/<script\b[^>]*>.*?<\/script>/is', function ($match) use (&$script_placeholders) {
            $key = '<!--UGENTIC_SCRIPT_' . count($script_placeholders) . '-->';
            $script_placeholders[$key] = $match[0];
            return $key;
        }, $html);

        // 3. Extract and protect HTML comments (can contain bracket patterns)
        $comment_placeholders = [];
        $html = preg_replace_callback('/<!--(?!UGENTIC_).*?-->/s', function ($match) use (&$comment_placeholders) {
            $key = '<!--UGENTIC_COMMENT_' . count($comment_placeholders) . '-->';
            $comment_placeholders[$key] = $match[0];
            return $key;
        }, $html);

        $has_shortcode = function_exists('get_shortcode_regex') && preg_match('/' . get_shortcode_regex() . '/s', $html);
        if (!$has_shortcode) {
            if (!empty($comment_placeholders)) {
                $html = str_replace(array_keys($comment_placeholders), array_values($comment_placeholders), $html);
            }
            if (!empty($script_placeholders)) {
                $html = str_replace(array_keys($script_placeholders), array_values($script_placeholders), $html);
            }
            if (!empty($style_placeholders)) {
                $html = str_replace(array_keys($style_placeholders), array_values($style_placeholders), $html);
            }
            return $html;
        }

        // 4. Run full do_shortcode() on the safe HTML
        $html = do_shortcode($html);

        // 5. Restore protected blocks (reverse order)
        if (!empty($comment_placeholders)) {
            $html = str_replace(array_keys($comment_placeholders), array_values($comment_placeholders), $html);
        }
        if (!empty($script_placeholders)) {
            $html = str_replace(array_keys($script_placeholders), array_values($script_placeholders), $html);
        }
        if (!empty($style_placeholders)) {
            $html = str_replace(array_keys($style_placeholders), array_values($style_placeholders), $html);
        }

        return $html;
    }

    /**
     * Output global CSS in head
     */
    public function output_global_css()
    {
        if (!is_singular('page')) {
            return;
        }

        global $post;

        // Check if this is a Tahmir AI page
        $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);

        if (empty($ugentic_page_id)) {
            return;
        }

        // Skip on blank canvas pages — the template handles its own CSS output
        // with body-to-wrapper transformations that this method doesn't do
        $template = get_page_template_slug($post->ID);
        if ($template === 'ugentic-blank' || $template === 'templates/blank-template.php') {
            return;
        }
        // Skip on faithful pages — they serve their own complete HTML document
        // with embedded styles; preloader killer and asset injection would break them
        if ($template === 'ugentic-faithful' || $template === 'ugentic-faithful-blog') {
            return;
        }

        $database = ugentic_builder()->database;

        // Get page-specific CSS assets
        $css_assets = $database->get_page_assets($ugentic_page_id, 'css');

        // Also get all active global CSS (shared header/footer styles, etc.)
        $global_css = $database->get_assets_by_type('css', true);

        // Merge global + page-specific, removing duplicates (page-specific wins)
        $all_css = [];
        foreach ($global_css as $css) {
            $all_css[$css->id] = $css;
        }
        foreach ($css_assets as $css) {
            $all_css[$css->id] = $css;
        }

        if (empty($all_css)) {
            return;
        }

        // Print the reset first, then each imported asset in its original order.
        self::print_inline_css('preloader-' . $post->ID, <<<'CSS'
#spinner, #preloader, #page-loader, #loading, #loader-wrapper,
#page-loading, #site-loader, #loading-screen,
.preloader, .page-loader, .loading-screen, .loader-wrapper,
.site-loader, .page-loading, .sk-cube-grid, .loader-overlay {
  display: none !important;
  visibility: hidden !important;
  opacity: 0 !important;
  pointer-events: none !important;
}
body { overflow: auto !important; }
.ugentic-page { display: block; width: 100%; max-width: 100%; margin: 0; padding: 0; }
CSS
        );

        foreach ($all_css as $css) {
            $trimmed = trim($css->content);
            if (preg_match('/^@import\s+url\(\s*["\']?(https?:\/\/[^"\')\\s]+)["\']?\s*\)\s*;?\s*$/i', $trimmed, $import_match)) {
                self::print_external_css('global-' . $css->id, $import_match[1]);
            } else {
                $content = preg_replace('/@import\s+url\(\s*["\']?https?:\/\/[^"\')\\s]+["\']?\s*\)\s*;?\s*/i', '', $css->content);
                $content = preg_replace('/\x{FEFF}/u', '', $content);
                $content = str_replace("\xEF\xBB\xBF", '', $content);
                self::print_inline_css('global-' . $css->id, $content);
            }
        }
    }

    /**
     * Output global JS in footer
     */
    public function output_global_js()
    {
        if (!is_singular('page')) {
            return;
        }

        global $post;

        // Check if this is a Tahmir AI page
        $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);

        if (empty($ugentic_page_id)) {
            return;
        }

        // Skip on blank canvas pages — the template handles its own JS output
        $template = get_page_template_slug($post->ID);
        if ($template === 'ugentic-blank' || $template === 'templates/blank-template.php') {
            return;
        }
        // Skip on faithful pages — they serve their own complete HTML document
        // with embedded scripts; preloader killer would remove legitimate elements
        if ($template === 'ugentic-faithful' || $template === 'ugentic-faithful-blog') {
            return;
        }

        $database = ugentic_builder()->database;

        // Get page-specific JS assets
        $js_assets = $database->get_page_assets($ugentic_page_id, 'js');

        // Also get all active global JS (shared scripts, etc.)
        $global_js = $database->get_assets_by_type('js', true);

        // Merge global + page-specific, removing duplicates (page-specific wins)
        $all_js = [];
        foreach ($global_js as $js) {
            $all_js[$js->id] = $js;
        }
        foreach ($js_assets as $js) {
            $all_js[$js->id] = $js;
        }

        if (empty($all_js)) {
            return;
        }

        self::print_inline_js('preloader-' . $post->ID, <<<'JS'
(function(){
  var ids=['spinner','preloader','page-loader','loading','loader-wrapper','page-loading','site-loader','loading-screen'];
  var cls=['preloader','page-loader','loading-screen','loader-wrapper','site-loader','page-loading','loader-overlay'];
  function kill(){
    ids.forEach(function(id){var e=document.getElementById(id);if(e)e.remove();});
    cls.forEach(function(c){var els=document.querySelectorAll('.'+c);els.forEach(function(e){e.remove();});});
    document.body.style.overflow='auto';
  }
  kill();
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',kill);}
})();
JS
        );

        foreach ($all_js as $js) {
            $trimmed = trim($js->content);
            if (preg_match('/^\/\*\s*external:\s*(https?:\/\/\S+)\s*\*\/$/i', $trimmed, $ext_match)) {
                self::print_external_js('global-' . $js->id, $ext_match[1]);
            } else {
                self::print_inline_js('global-' . $js->id, $js->content);
            }
        }
    }

    /**
     * Save CSS asset to file
     */
    public function save_css_file($name, $content)
    {
        $filename = sanitize_file_name($name) . '.css';
        $asset_dir = $this->get_asset_upload_dir('css');
        if (!$asset_dir) {
            return false;
        }
        $filepath = $asset_dir . $filename;

        // Strip BOM at import time so it doesn't need to happen on every render
        $content = preg_replace('/\x{FEFF}/u', '', $content);
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        $result = file_put_contents($filepath, $content);

        if ($result !== false) {
            return $filepath;
        }

        return false;
    }

    /**
     * Save JS asset to file
     */
    public function save_js_file($name, $content)
    {
        $filename = sanitize_file_name($name) . '.js';
        $asset_dir = $this->get_asset_upload_dir('js');
        if (!$asset_dir) {
            return false;
        }
        $filepath = $asset_dir . $filename;

        $result = file_put_contents($filepath, $content);

        if ($result !== false) {
            return $filepath;
        }

        return false;
    }

    /**
     * Get asset URL
     */
    public function get_asset_url($type, $filename)
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return '';
        }

        $type = sanitize_key($type);
        $filename = sanitize_file_name($filename);

        return trailingslashit($upload_dir['baseurl']) . 'ugentic-builder/assets/' . $type . '/' . $filename;
    }

    /**
     * Get or create the uploads-backed asset directory for generated assets.
     */
    private function get_asset_upload_dir($type)
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return false;
        }

        $type = sanitize_key($type);
        if (!in_array($type, ['css', 'js'], true)) {
            return false;
        }

        $dir = trailingslashit($upload_dir['basedir']) . 'ugentic-builder/assets/' . $type . '/';
        if (!file_exists($dir) && !wp_mkdir_p($dir)) {
            return false;
        }

        return $dir;
    }

    /**
     * Link all global assets to a page
     */
    public function link_global_assets_to_page($page_id)
    {
        $database = ugentic_builder()->database;

        // Link all active CSS
        $css_assets = $database->get_assets_by_type('css', true);
        $order = 0;
        foreach ($css_assets as $css) {
            $database->link_asset_to_page($page_id, $css->id, $order++);
        }

        // Link all active JS
        $js_assets = $database->get_assets_by_type('js', true);
        foreach ($js_assets as $js) {
            $database->link_asset_to_page($page_id, $js->id, $order++);
        }
    }

    /**
     * Create default assets on first install
     */
    public function create_default_assets()
    {
        $database = ugentic_builder()->database;

        // Check if defaults already exist
        $header = $database->get_global_header();
        if (!$header) {
            $database->insert_asset([
                'type' => 'header',
                'name' => 'Global Header',
                'content' => '<!-- Global Header - Add your header HTML here -->',
                'is_active' => 1,
            ]);
        }

        $footer = $database->get_global_footer();
        if (!$footer) {
            $database->insert_asset([
                'type' => 'footer',
                'name' => 'Global Footer',
                'content' => '<!-- Global Footer - Add your footer HTML here -->',
                'is_active' => 1,
            ]);
        }
    }
}
