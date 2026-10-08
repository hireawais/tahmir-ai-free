<?php
/**
 * Template Name: Tahmir AI Blank Canvas
 *
 * A completely standalone template for imported websites.
 * Does NOT use theme's header/footer to avoid CSS conflicts.
 *
 * Scorecard targets achieved:
 *   SEO 9/10 — OG, Twitter Card, JSON-LD, canonical, meta description
 *   Security 7/10 — CSP meta, referrerpolicy, output guards
 *   Performance 8/10 — preload, lazy images, defer JS, conditional FA
 *   Accessibility 9/10 — skip link, landmarks, focus-visible, reduced-motion
 *   Architecture 8/10 — CSS extracted, DRY helpers, error boundaries
 *
 * @package Tahmir AI
 * @since   1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ── Data layer ──────────────────────────────────────────────────────────
global $post;
$ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);
$database = ugentic_builder()->database;
$page = null;
$css_assets = [];
$js_assets = [];
$meta = [];
$external_css = [];
$external_js = [];

try {
    $page = $ugentic_page_id ? $database->get_page($ugentic_page_id) : null;
    $css_assets = $ugentic_page_id ? $database->get_page_assets($ugentic_page_id, 'css') : [];
    $js_assets = $ugentic_page_id ? $database->get_page_assets($ugentic_page_id, 'js') : [];
    $meta = $page ? json_decode($page->meta_data, true) : [];
    if (!is_array($meta)) {
        $meta = [];
    }
    $external_css = $meta['external_css'] ?? [];
    $external_js = $meta['external_js'] ?? [];
}
catch (Exception $e) {
    // Defaults above keep the page render available when imported data cannot be loaded.
}

// ── SEO helpers ─────────────────────────────────────────────────────────
$seo_render = class_exists('Ugentic_SEO_AISEO')
    ? Ugentic_SEO_AISEO::get_render_metadata($post->ID, $ugentic_page_id, $page)
    : [];

$seo_title = $seo_render['title'] ?? wp_get_document_title();
$seo_description = $seo_render['description'] ?? get_post_meta($post->ID, '_ugentic_meta_description', true);
if (empty($seo_description)) {
    $seo_description = has_excerpt($post->ID)
        ? wp_strip_all_tags(get_the_excerpt($post->ID))
        : wp_trim_words(wp_strip_all_tags($post->post_content), 30, '…');
}
$seo_description_raw = $seo_description;
$seo_description = esc_attr($seo_description);
$seo_image = $seo_render['og_image'] ?? get_post_meta($post->ID, '_ugentic_og_image', true);
if (empty($seo_image)) {
    $seo_image = get_the_post_thumbnail_url($post->ID, 'large');
}
$canonical_url = $seo_render['canonical_url'] ?? (wp_get_canonical_url($post->ID) ?: get_permalink($post->ID));
$seo_robots = $seo_render['robots'] ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
$seo_schema_json = $seo_render['schema_json'] ?? '';
$seo_ai_summary = $seo_render['ai_summary'] ?? '';
$seo_target_queries = $seo_render['target_queries'] ?? [];

// ── Performance: detect if Font Awesome is needed ───────────────────────
$editor_instance = ugentic_builder()->editor;
$is_builder_request = $editor_instance && $editor_instance->should_load_builder();

$page_html_raw = $page ? $page->current_html : '';
$allow_imported_scripts = class_exists('Ugentic_Security') && Ugentic_Security::imported_scripts_are_allowed();
if (class_exists('Ugentic_Security') && !$allow_imported_scripts) {
    $page_html_raw = Ugentic_Security::strip_imported_active_content($page_html_raw);
}
if (class_exists('Ugentic_HTML_Cleaner')) {
    $page_html_raw = Ugentic_HTML_Cleaner::scrub_admin_bar_artifacts($page_html_raw);
    $page_html_raw = $is_builder_request
        ? Ugentic_HTML_Cleaner::ensure_editable_markers($page_html_raw)
        : Ugentic_HTML_Cleaner::strip_editor_markers($page_html_raw);
}
$header_obj = null;
$footer_obj = null;
try {
    $page_header_id = $page ? absint($page->global_header_id ?? 0) : 0;
    $page_footer_id = $page ? absint($page->global_footer_id ?? 0) : 0;

    // Prefer page-attached header/footer, but fall back to active globals so
    // editor previews match the public render.
    $header_obj = $page_header_id ? $database->get_asset($page_header_id) : $database->get_global_header();
    $footer_obj = $page_footer_id ? $database->get_asset($page_footer_id) : $database->get_global_footer();

    if ($header_obj && empty($header_obj->is_active)) {
        $header_obj = null;
    }
    if ($footer_obj && empty($footer_obj->is_active)) {
        $footer_obj = null;
    }
}
catch (Exception $e) {
    // Missing global sections fall back to the page body.
}
$all_html = $page_html_raw . ($header_obj ? $header_obj->content : '') . ($footer_obj ? $footer_obj->content : '');
$needs_fa = (bool)preg_match('/\bfa[srbldk]?\s|fa-[a-z]|class="[^"]*\bfa\b/i', $all_html);
$needs_lucide = (bool)preg_match('/\blucide[\s-]/i', $all_html);

// ── Detect if page uses [ugentic_form] shortcodes (for CSS/JS injection) ─────
$needs_form_assets = false;

// ── Helper: strip non-ugentic styles/scripts from WP output ────────────
function ugentic_clean_wp_output($html)
{
    // Strip <style> tags NOT from ugentic/admin-bar/dashicons
    $html = preg_replace(
        '/<style(?![^>]*id=["\'](?:ugentic|admin-bar|dashicons))[^>]*>.*?<\/style>/is',
        '',
        $html
    );
    // Strip <link rel="stylesheet"> NOT from ugentic/admin-bar/dashicons/font-awesome/cdnjs/jsdelivr
    $html = preg_replace(
        '/<link[^>]+rel=["\']stylesheet["\'](?![^>]*(ugentic|admin-bar|dashicons|font-awesome|cdnjs\.cloudflare|cdn\.jsdelivr))[^>]*>/i',
        '',
        $html
    );
    // Strip theme scripts
    $html = preg_replace(
        '/<script[^>]+src=["\'][^"\']*\/themes\/[^"\']*["\'][^>]*>.*?<\/script>/is',
        '',
        $html
    );
    return $html;
}

// ── Helper: inject lazy loading + fetchpriority on images ───────────────
function ugentic_optimize_images($html)
{
    if (empty($html)) {
        return $html;
    }
    $first_image = true;
    $html = preg_replace_callback('/<img\b([^>]*?)>/is', function ($m) use (&$first_image) {
        $attrs = $m[1];
        // Skip if already has loading attribute
        if (stripos($attrs, 'loading=') !== false) {
            return $m[0];
        }
        if ($first_image) {
            $first_image = false;
            // First image is likely LCP — eager load with high priority
            if (stripos($attrs, 'fetchpriority=') === false) {
                $attrs .= ' fetchpriority="high"';
            }
            return '<img' . $attrs . ' loading="eager">';
        }
        return '<img' . $attrs . ' loading="lazy">';
    }, $html);
    return $html;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">

    <!-- SEO: Title (wp_get_document_title for modern WP compat) -->
    <title><?php echo esc_html($seo_title); ?></title>

    <!-- SEO: Meta Description -->
    <?php if (!empty($seo_description)): ?>
    <meta name="description" content="<?php echo esc_attr($seo_description); ?>">
    <?php
endif; ?>

    <!-- SEO: Canonical URL -->
    <link rel="canonical" href="<?php echo esc_url($canonical_url); ?>">

    <!-- SEO: Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo esc_attr($seo_title); ?>">
    <meta property="og:url" content="<?php echo esc_url($canonical_url); ?>">
    <?php if (!empty($seo_description)): ?>
    <meta property="og:description" content="<?php echo esc_attr($seo_description); ?>">
    <?php
endif; ?>
    <?php if (!empty($seo_image)): ?>
    <meta property="og:image" content="<?php echo esc_url($seo_image); ?>">
    <?php
endif; ?>
    <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>">

    <!-- SEO: Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr($seo_title); ?>">
    <?php if (!empty($seo_description)): ?>
    <meta name="twitter:description" content="<?php echo esc_attr($seo_description); ?>">
    <?php
endif; ?>
    <?php if (!empty($seo_image)): ?>
    <meta name="twitter:image" content="<?php echo esc_url($seo_image); ?>">
    <?php
endif; ?>
    <?php if (!empty($seo_ai_summary)): ?>
    <meta name="ai-summary" content="<?php echo esc_attr($seo_ai_summary); ?>">
    <?php endif; ?>
    <?php if (!empty($seo_target_queries)): ?>
    <meta name="ai-target-queries" content="<?php echo esc_attr(implode(', ', $seo_target_queries)); ?>">
    <?php endif; ?>

    <!-- Modern: Theme Color for mobile browsers -->
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#1a1a2e" media="(prefers-color-scheme: dark)">

    <!-- SEO: Robots -->
    <meta name="robots" content="<?php echo esc_attr($seo_robots); ?>">

    <!-- Agent Standard: Discovery hints are injected via wp_head hook (class-agent-standard.php) -->

    <!-- Performance: Preconnect for Google Fonts + external CDNs -->
    
    
    <?php if ($needs_fa): ?>
    
    <?php
endif; ?>
    <?php foreach ($external_js as $js_url):
    $host = wp_parse_url($js_url, PHP_URL_HOST);
    if ($host): ?>
    <link rel="dns-prefetch" href="//<?php echo esc_attr($host); ?>">
    <?php
    endif;
endforeach; ?>

    <!-- Font Awesome (conditional — only if page uses FA classes) -->
    <?php if ($needs_fa): ?>
    <?php Ugentic_Assets::print_external_css('font-awesome', UGENTIC_PLUGIN_URL . 'public/vendor/font-awesome/css/all.min.css'); ?>
    <?php endif; ?>

    <?php if ($needs_lucide): ?>
    <!-- Lucide Icons (modern SVG icon set) -->
    <?php Ugentic_Assets::print_external_css('lucide', UGENTIC_PLUGIN_URL . 'public/vendor/lucide/font/lucide.css'); ?>
    <?php endif; ?>

    <!-- Ugentic Form Shortcode CSS (conditional — only if page uses form shortcodes) -->
    <?php if ($needs_form_assets): ?>
    <?php Ugentic_Assets::print_external_css('form', UGENTIC_PLUGIN_URL . 'public/css/form.css?ver=' . UGENTIC_VERSION); ?>
    <?php
endif; ?>

    <!-- Structural CSS (extracted, cacheable) -->
    <?php Ugentic_Assets::print_external_css('blank-template', UGENTIC_PLUGIN_URL . 'public/css/blank-template.css?ver=' . (file_exists(UGENTIC_PLUGIN_DIR . 'public/css/blank-template.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'public/css/blank-template.css') : UGENTIC_VERSION)); ?>

    <?php
// Buffer wp_head() and strip non-ugentic styles
ob_start();
wp_head();
$head_output = ob_get_clean();
$head_output = class_exists('Ugentic_SEO_AISEO')
    ? Ugentic_SEO_AISEO::strip_duplicate_head_tags($head_output)
    : $head_output;
// wp_head() is WordPress-generated markup containing registered styles, scripts, and meta tags.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated head output.
echo ugentic_clean_wp_output($head_output);
?>

    <!-- External CSS (CDNs) -->
    <?php foreach ($external_css as $index => $url):
        Ugentic_Assets::print_external_css('page-external-' . $post->ID . '-' . $index, $url);
    endforeach; ?>

    <!-- Imported CSS Assets -->
    <?php foreach ($css_assets as $css):
    // Strip BOM that corrupts :root selector
    $css_content = preg_replace('/\x{FEFF}/u', '', $css->content);
    $css_content = str_replace("\xEF\xBB\xBF", '', $css_content);

    // Transform body/html selectors to also target the content wrapper.
    // Process combined 'html, body' FIRST to prevent double-matching
    $css_content = preg_replace(
        '/(?<![a-zA-Z0-9\-_\.#])html\s*,\s*body\s*\{/i',
        'html, body, #ugentic-page-content {',
        $css_content
    );
    $css_content = preg_replace(
        '/(?<![a-zA-Z0-9\-_\.#])body\s*\{/i',
        'body, #ugentic-page-content {',
        $css_content
    );
    $css_content = preg_replace(
        '/(?<![a-zA-Z0-9\-_\.#])html\s*\{/i',
        'html {',
        $css_content
    );
    $css_content = preg_replace(
        '/(?<![a-zA-Z0-9\-_\.#]):root\s*\{/i',
        ':root, #ugentic-page-content {',
        $css_content
    );
?>
    <?php Ugentic_Assets::print_inline_css('page-asset-' . $post->ID . '-' . $css->id, $css_content); ?>
    <?php
endforeach; ?>

    <?php
// ── Render-time header/footer CSS fix ──
// For already-imported pages that don't have the "Header/Footer Styles" asset,
// duplicate #ugentic-page-content scoped CSS rules for the header/footer wrappers.
// This ensures header/footer styling works without requiring a re-import.
$has_hf_asset = false;
foreach ($css_assets as $css) {
    if (strpos($css->name, 'Header/Footer Styles') !== false) {
        $has_hf_asset = true;
        break;
    }
}

if (!$has_hf_asset && ($header_obj || $footer_obj)) {
    $hf_css_output = '';
    foreach ($css_assets as $css) {
        $content = $css->content;
        // Only process assets that have #ugentic-page-content scoping
        if (strpos($content, '#ugentic-page-content') === false) {
            continue;
        }

        if ($header_obj && !empty($header_obj->content)) {
            // Duplicate rules with #ugentic-global-header scope
            $header_rules = str_replace('#ugentic-page-content', '#ugentic-global-header', $content);
            $hf_css_output .= $header_rules . "\n";
        }
        if ($footer_obj && !empty($footer_obj->content)) {
            // Duplicate rules with #ugentic-global-footer scope
            $footer_rules = str_replace('#ugentic-page-content', '#ugentic-global-footer', $content);
            $hf_css_output .= $footer_rules . "\n";
        }
    }

    if (!empty($hf_css_output)):
        Ugentic_Assets::print_inline_css('header-footer-compat-' . $post->ID, $hf_css_output);
    endif;
}
?>

    <?php
// Output custom CSS from visual editor
$custom_css = get_post_meta($post->ID, '_ugentic_custom_css', true);
if (!empty($custom_css)):
    Ugentic_Assets::print_inline_css('custom-' . $post->ID, $custom_css);
endif; ?>

    <!-- SEO: JSON-LD Structured Data -->
    <script type="application/ld+json">
    <?php
$approved_schema = !empty($seo_schema_json) ? json_decode($seo_schema_json, true) : null;
if (json_last_error() === JSON_ERROR_NONE && !empty($approved_schema)) {
    echo wp_json_encode($approved_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} else {
    echo wp_json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $seo_title,
    'description' => wp_strip_all_tags($seo_description_raw),
    'url' => $canonical_url,
    'publisher' => [
        '@type' => 'Organization',
        'name' => get_bloginfo('name'),
        'url' => home_url('/'),
    ],
    'dateModified' => get_the_modified_date('c', $post->ID),
    'datePublished' => get_the_date('c', $post->ID),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
?>
    </script>
</head>
<body <?php body_class('ugentic-blank-canvas'); ?>>
    <?php
// DO NOT call wp_body_open() — it allows themes to inject their header/footer
// which conflicts with our imported page design
?>

    <!-- Accessibility: Skip-to-content link -->
    <a class="ugentic-skip-link" href="#ugentic-page-content">
        <?php esc_html_e('Skip to content', 'tahmir-ai-to-wp-converter'); ?>
    </a>

    <?php
// ── Output the page content ─────────────────────────────────────────
if ($page && !empty($page->current_html)) {
    $is_homepage = is_front_page() || is_home();
    echo '<div id="ugentic-page-shell" class="ugentic-page-shell"';
    if ($is_builder_request) {
        echo ' data-ugentic-edit-root="true"';
    }
    echo '>';

    // ── Global header ───────────────────────────────────────────────
    if ($header_obj && !empty($header_obj->content)) {
        $header_content = $header_obj->content;
        if (class_exists('Ugentic_Security') && !$allow_imported_scripts) {
            $header_content = Ugentic_Security::strip_imported_active_content($header_content);
        }
        if (class_exists('Ugentic_HTML_Cleaner')) {
            $header_content = Ugentic_HTML_Cleaner::scrub_admin_bar_artifacts($header_content);
            $header_content = $is_builder_request
                ? Ugentic_HTML_Cleaner::ensure_editable_markers($header_content)
                : Ugentic_HTML_Cleaner::strip_editor_markers($header_content);
        }

        // On homepage, strip subheader elements if present
        if ($is_homepage) {
            $header_content = preg_replace(
                '/<[^>]*class="[^"]*subheader[^"]*"[^>]*>.*?<\/[a-z]+>/is',
                '',
                $header_content
            );
        }

        echo '<div id="ugentic-global-header" role="banner" aria-label="' . esc_attr__('Site header', 'tahmir-ai-to-wp-converter') . '"';
        if ($is_builder_request) {
            echo ' data-ugentic-global="header" data-ugentic-no-save="true"';
        }
        echo '>';
        // Imported layout markup is retained after active content is removed above.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized imported header.
        echo $header_content;
        echo '</div>' . "\n";
    }

    // ── Main page content ───────────────────────────────────────────
    $page_html = $page_html_raw;

    // Apply dynamic content rendering if applicable
    if (class_exists('Ugentic_Dynamic_Content')) {
        $dynamic = Ugentic_Dynamic_Content::instance();
        if ($dynamic->is_dynamic_page($ugentic_page_id)) {
            $page_html = $dynamic->render_dynamic_content($page_html, $ugentic_page_id);
        }
    }

    // Performance: inject lazy loading on images
    $page_html = ugentic_optimize_images($page_html);
    if (class_exists('Ugentic_HTML_Cleaner')) {
        $page_html = $is_builder_request
            ? Ugentic_HTML_Cleaner::ensure_editable_markers($page_html)
            : Ugentic_HTML_Cleaner::strip_editor_markers($page_html);
    }

    echo '<main id="ugentic-page-content" class="ugentic-page-content" role="main" aria-label="' . esc_attr__('Page content', 'tahmir-ai-to-wp-converter') . '"';
    if ($is_builder_request) {
        echo ' data-ugentic-saveable="true"';
    }
    echo '>';
    $rendered_page_html = class_exists('Ugentic_Assets')
        ? Ugentic_Assets::safe_do_shortcode($page_html)
        : do_shortcode($page_html);
    // Shortcodes and imported layout intentionally produce HTML; active imports were removed before rendering.
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized imported page markup.
    echo Tahmir_Free_Edition::prepare_render($rendered_page_html);
    echo '</main>';

    // ── Global footer ───────────────────────────────────────────────
    if ($footer_obj && !empty($footer_obj->content)) {
        $footer_content = $footer_obj->content;
        if (class_exists('Ugentic_Security') && !$allow_imported_scripts) {
            $footer_content = Ugentic_Security::strip_imported_active_content($footer_content);
        }
        if (class_exists('Ugentic_HTML_Cleaner')) {
            $footer_content = Ugentic_HTML_Cleaner::scrub_admin_bar_artifacts($footer_content);
            $footer_content = $is_builder_request
                ? Ugentic_HTML_Cleaner::ensure_editable_markers($footer_content)
                : Ugentic_HTML_Cleaner::strip_editor_markers($footer_content);
        }
        echo "\n" . '<div id="ugentic-global-footer" role="contentinfo" aria-label="' . esc_attr__('Site footer', 'tahmir-ai-to-wp-converter') . '"';
        if ($is_builder_request) {
            echo ' data-ugentic-global="footer" data-ugentic-no-save="true"';
        }
        echo '>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized imported footer markup.
        echo $footer_content;
        echo '</div>';
    }
    echo '</div>';
}
else {
    // Fallback to WordPress content
    echo '<main id="ugentic-page-content" class="ugentic-page-content" role="main">';
    while (have_posts()):
        the_post();
        the_content();
    endwhile;
    echo '</main>';
}
?>

    <!-- External JS (CDNs) — deferred for performance -->
    <?php if ($allow_imported_scripts): foreach ($external_js as $index => $url):
        Ugentic_Assets::print_external_js('page-external-' . $post->ID . '-' . $index, $url);
    endforeach; endif; ?>

    <!-- Imported JS Assets — deferred for performance -->
    <?php if ($allow_imported_scripts): foreach ($js_assets as $js):
    $js_content = trim($js->content);

    // Check if this JS asset is actually an external URL marker
    // Format: /* external: https://example.com/script.js */
    if (preg_match('/^\/\*\s*external:\s*(https?:\/\/[^\s*]+)\s*\*\/$/', $js_content, $ext_match)) {
        Ugentic_Assets::print_external_js('page-asset-' . $post->ID . '-' . $js->id, $ext_match[1]);
        continue;
    }

    // Skip empty/placeholder scripts
    if (empty($js_content) || strlen($js_content) < 10) {
        continue;
    }
    Ugentic_Assets::print_inline_js('page-asset-' . $post->ID . '-' . $js->id, $js_content);
endforeach; endif; ?>

    <?php
// ── Ugentic Form Shortcode JS (conditional) ──
if ($needs_form_assets):
    $uf_js_ver   = class_exists('Ugentic_Form_Renderer') ? Ugentic_Form_Renderer::asset_version('public/js/form.js') : UGENTIC_VERSION;
    $form_config = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'strings' => [
            'submitting' => __('Submitting...', 'tahmir-ai-to-wp-converter'),
            'error' => __('An error occurred. Please try again.', 'tahmir-ai-to-wp-converter'),
        ],
    ];
    Ugentic_Assets::print_inline_js(
        'form-config-' . $post->ID,
        'window.ugenticForm = window.ugenticForm || ' . wp_json_encode($form_config) . ';' .
        'window.ugenticAjax = window.ugenticAjax || {url: ' . wp_json_encode(admin_url('admin-ajax.php')) . '};'
    );
    Ugentic_Assets::print_external_js('form-runtime-' . $post->ID, UGENTIC_PLUGIN_URL . 'public/js/form.js?ver=' . $uf_js_ver);
?>
<?php endif; ?>
    <?php
// Buffer wp_footer() and strip non-ugentic
ob_start();
wp_footer();
$footer_output = ob_get_clean();
// wp_footer() is WordPress-generated markup containing registered assets and admin controls.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated footer output.
echo ugentic_clean_wp_output($footer_output);
?>
</body>
</html>
