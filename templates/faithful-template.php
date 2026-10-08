<?php
/**
 * Faithful Import Template
 *
 * Serves the complete, unmodified HTML document with only asset paths replaced.
 * No CSS scoping, no container wrappers, no asset injection â€” just the original page.
 *
 * Template Name: Tahmir AI Faithful Canvas
 */

if (!defined('ABSPATH')) {
    exit;
}

$faithful_is_blog_mapper_request = isset($_GET['tahmir_blog_mapper']) && current_user_can('manage_options');
if ($faithful_is_blog_mapper_request) {
    show_admin_bar(false);
}

// â”€â”€ Disable WordPress emoji conversion â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// WordPress replaces emoji characters (e.g. ðŸ¢) with tiny ~20px <img> SVG tags
// via wp-emoji-release.min.js injected by wp_head(). This breaks imported pages
// that use emoji as large styled icons (e.g. .card-icon with font-size: 48px).
// Removing these hooks/filters keeps emojis as native unicode text, allowing
// them to respect the page's CSS font-size.
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_filter('the_content', 'wp_staticize_emoji');
remove_filter('the_excerpt', 'wp_staticize_emoji');
remove_filter('comment_text', 'wp_staticize_emoji');
remove_filter('widget_text_content', 'wp_staticize_emoji');
add_filter('emoji_svg_url', '__return_false');
remove_action('wp_footer', 'wp_print_speculation_rules');

// Get the Tahmir AI page data
$wp_page_id = get_the_ID();
$ugentic_page_id = get_post_meta($wp_page_id, '_ugentic_page_id', true);

if (!$ugentic_page_id) {
    // Fallback: show standard WP content
    get_header();
    the_content();
    get_footer();
    return;
}

$database = ugentic_builder()->database;
$ugentic_page_record = $database->get_page($ugentic_page_id);

if (!$ugentic_page_record || empty($ugentic_page_record->current_html)) {
    get_header();
    echo '<p>' . esc_html__('Page content not found.', 'tahmir-ai-to-wp-converter') . '</p>';
    get_footer();
    return;
}

$faithful_source_html = $ugentic_page_record->current_html;
$faithful_mapper_role = (string) get_post_meta($wp_page_id, '_ugentic_import_role', true);
$faithful_visual_source_html = (string) get_post_meta($wp_page_id, '_ugentic_blog_visual_source_html', true);
if (
    isset($_GET['tahmir_blog_mapper'])
    && !isset($_GET['tahmir_blog_live_template'])
    && current_user_can('manage_options')
    && in_array($faithful_mapper_role, ['blog_template', 'blog_listing'], true)
    && !empty($ugentic_page_record->original_html)
) {
    $faithful_source_html = trim($faithful_visual_source_html) !== ''
        ? $faithful_visual_source_html
        : $ugentic_page_record->original_html;
}

$editor_instance = ugentic_builder()->editor;
$is_builder_request = $editor_instance && $editor_instance->should_load_builder();
$builder_full_code_mode = $is_builder_request
    && class_exists('Ugentic_Security')
    && Ugentic_Security::builder_full_code_mode_is_enabled();

if (!function_exists('ugentic_faithful_collect_verified_scripts')) {
    /**
     * Rebuild approved imported script tags from immutable attributes only.
     * Builder requests always return an empty list.
     */
    function ugentic_faithful_collect_verified_scripts($source_html, $wp_page_id, $is_builder_request)
    {
        if ($is_builder_request || !is_string($source_html) || $source_html === '' || !$wp_page_id) {
            return [];
        }

        $manifest = get_post_meta($wp_page_id, '_ugentic_imported_script_manifest_v1', true);
        if ((!is_array($manifest) || empty($manifest)) && isset(ugentic_builder()->importer)) {
            $importer = ugentic_builder()->importer;
            if (method_exists($importer, 'build_verified_local_script_manifest')) {
                $manifest = $importer->build_verified_local_script_manifest($source_html);
                if (!empty($manifest)) {
                    update_post_meta($wp_page_id, '_ugentic_imported_script_manifest_v1', $manifest);
                }
            }
        }
        if (!is_array($manifest) || empty($manifest)) {
            return [];
        }

        $uploads = wp_upload_dir();
        $base_url = untrailingslashit((string) ($uploads['baseurl'] ?? ''));
        $base_dir = !empty($uploads['basedir']) ? realpath((string) $uploads['basedir']) : false;
        if ($base_url === '' || !$base_dir) {
            return [];
        }

        $by_src = [];
        foreach (array_slice($manifest, 0, 32) as $entry) {
            if (
                !is_array($entry)
                || empty($entry['src'])
                || empty($entry['upload_relative'])
                || empty($entry['sha256'])
                || empty($entry['bytes'])
            ) {
                continue;
            }
            $by_src[(string) $entry['src']] = $entry;
        }
        if (!$by_src) {
            return [];
        }

        preg_match_all(
            '/<script\b(?P<before>[^>]*)\bsrc\s*=\s*(?P<quote>["\'])(?P<src>[^"\']+)\k<quote>(?P<after>[^>]*)>(?P<body>[\s\S]*?)<\/script\s*>/i',
            $source_html,
            $matches,
            PREG_SET_ORDER
        );

        $approved = [];
        foreach (array_slice($matches, 0, 32) as $match) {
            if (trim((string) ($match['body'] ?? '')) !== '') {
                continue;
            }

            $src = esc_url_raw(html_entity_decode((string) ($match['src'] ?? ''), ENT_QUOTES, 'UTF-8'));
            if ($src === '' || empty($by_src[$src])) {
                continue;
            }

            $entry = $by_src[$src];
            $clean_url = preg_replace('/[?#].*$/', '', $src);
            if (strpos($clean_url, $base_url . '/') !== 0) {
                continue;
            }

            $relative = ltrim(substr($clean_url, strlen($base_url)), '/');
            if (!hash_equals((string) $entry['upload_relative'], $relative)) {
                continue;
            }

            $resolved = class_exists('Ugentic_Security')
                ? Ugentic_Security::resolve_verified_imported_script_path($relative, $base_dir)
                : false;
            if (!$resolved) {
                continue;
            }
            $file = $resolved['file'];
            $actual_bytes = $file && is_file($file) ? filesize($file) : false;
            if (
                !$file
                || !is_readable($file)
                || $actual_bytes === false
                || (int) $actual_bytes !== (int) $entry['bytes']
            ) {
                continue;
            }

            $actual_hash = hash_file('sha256', $file);
            $expected_hash = strtolower((string) $entry['sha256']);
            if (!$actual_hash || !hash_equals($expected_hash, strtolower($actual_hash))) {
                continue;
            }

            $sri = 'sha256-' . base64_encode(hex2bin($actual_hash));
            if (!empty($entry['sri']) && !hash_equals((string) $entry['sri'], $sri)) {
                continue;
            }

            $type = strtolower((string) ($entry['type'] ?? ''));
            $approved[$src] = [
                'ordinal' => (int) ($entry['ordinal'] ?? 0),
                'tag' => Ugentic_Assets::render_verified_js(
                    'faithful-' . $wp_page_id . '-' . (int) ($entry['ordinal'] ?? 0),
                    $src,
                    $sri,
                    [
                        'type' => $type,
                        'defer' => !empty($entry['defer']),
                        'async' => !empty($entry['async']),
                        'nomodule' => !empty($entry['nomodule']),
                    ]
                ),
            ];
        }

        uasort($approved, function ($left, $right) {
            return $left['ordinal'] <=> $right['ordinal'];
        });
        return array_values(array_map(function ($entry) {
            return $entry['tag'];
        }, $approved));
    }
}
$faithful_verified_script_tags = ugentic_faithful_collect_verified_scripts(
    $faithful_source_html,
    $wp_page_id,
    $is_builder_request
);
$html = $faithful_source_html;
if (
    $faithful_mapper_role === 'blog_listing'
    && Tahmir_Free_Edition::has_method(ugentic_builder()->blog_generator, 'prepare_archive_template_html')
) {
    $faithful_post_type = (string) get_post_meta($wp_page_id, '_ugentic_blog_target_post_type', true);
    $html = ugentic_builder()->blog_generator->prepare_archive_template_html($html, $faithful_post_type ?: 'post');
}
if (method_exists($database, 'repair_internal_links_for_render')) {
    $html = $database->repair_internal_links_for_render($html);
}
if (!Tahmir_Rendered_Import::capture_preview() && isset(ugentic_builder()->assets) && method_exists(ugentic_builder()->assets, 'inject_faithful_sections')) {
    $html = ugentic_builder()->assets->inject_faithful_sections($html, $wp_page_id, $is_builder_request);
}
if (!$builder_full_code_mode) {
    if (class_exists('Ugentic_Security')) {
        // The legacy broad allow option never bypasses the exact-hash manifest.
        $html = Ugentic_Security::strip_imported_active_content($html);
    } else {
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script\s*>/i', '', $html);
    }
}
if (class_exists('Ugentic_HTML_Cleaner')) {
    $html = Ugentic_HTML_Cleaner::scrub_admin_bar_artifacts($html);
    $html = $is_builder_request
        ? Ugentic_HTML_Cleaner::ensure_editable_markers($html)
        : Ugentic_HTML_Cleaner::strip_editor_markers($html);
}

// â”€â”€ Sync faithful form fields from the database â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// A faithful page can lose its data-ugentic-form-id when its rendered HTML is
// edited and saved. Re-register any unbound preserved form at render time so
// it receives the canonical AJAX runtime and continues recording entries.
if (
    class_exists('Ugentic_Form_Converter')
    && preg_match('/<form\b(?![^>]*\bdata-ugentic-form-id\s*=)[^>]*>/i', $html)
) {
    $faithful_form_converter = new Ugentic_Form_Converter();
    $faithful_form_result = $faithful_form_converter->detect_and_register_forms($html);
    if (!empty($faithful_form_result['html'])) {
        $html = $faithful_form_result['html'];
    }
}

if (class_exists('Ugentic_Form_Renderer')) {
    $html = Ugentic_Form_Renderer::prepare_faithful_html($html);
    if (class_exists('Ugentic_HTML_Cleaner')) {
        $html = $is_builder_request
            ? Ugentic_HTML_Cleaner::ensure_editable_markers($html)
            : Ugentic_HTML_Cleaner::strip_editor_markers($html);
    }
}

// Stabilize modern SSR/builder snapshots inside WordPress preview.
if (!function_exists('ugentic_faithful_detect_ssr_framework')) {
    function ugentic_faithful_detect_ssr_framework($html)
    {
        if (stripos($html, '/_next/') !== false || stripos($html, 'self.__next_f') !== false || stripos($html, '__NEXT_DATA__') !== false || preg_match('/\bid\s*=\s*["\']__next["\']/i', $html)) {
            return 'next';
        }
        if (preg_match('/\bdata-nimg\s*=/i', $html) && preg_match('#/(?:ugentic-clones|ugentic-instant-clones)/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?#i', $html)) {
            return 'next';
        }
        if (stripos($html, '/_nuxt/') !== false || stripos($html, 'window.__NUXT__') !== false || stripos($html, '__NUXT__') !== false) {
            return 'nuxt';
        }
        if (stripos($html, 'gatsby-focus-wrapper') !== false || stripos($html, '___gatsby') !== false || stripos($html, '/page-data/') !== false) {
            return 'gatsby';
        }
        if (stripos($html, '__remixContext') !== false || stripos($html, '__remixManifest') !== false || stripos($html, '/build/_assets/') !== false) {
            return 'remix';
        }
        if (
            stripos($html, '$_TSR') !== false
            || stripos($html, '$tsr-stream-barrier') !== false
            || stripos($html, 'class="$tsr"') !== false
            || (stripos($html, 'lovable.app') !== false && stripos($html, 'modulepreload') !== false && stripos($html, '/assets/') !== false)
        ) {
            return 'tanstack';
        }
        return '';
    }
}

if (!function_exists('ugentic_faithful_is_client_app_shell')) {
    function ugentic_faithful_is_client_app_shell($html)
    {
        $body = $html;
        if (preg_match('/<body\b[^>]*>([\s\S]*?)<\/body>/i', $html, $m)) {
            $body = $m[1];
        }
        $without_scripts = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $body);
        $without_styles = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $without_scripts);
        $without_comments = preg_replace('/<!--[\s\S]*?-->/s', '', $without_styles);
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($without_comments)));
        $has_app_root = preg_match('/\bid\s*=\s*["\'](?:app|root|__next|gatsby-focus-wrapper)["\']/i', $html);
        $has_client_bundle = preg_match('/<script\b[^>]*(?:\btype\s*=\s*["\']module["\']|\/assets\/[^"\']+\.(?:js|mjs)|\/_next\/static\/[^"\']+\.(?:js|mjs)|\/_nuxt\/[^"\']+\.(?:js|mjs)|\/build\/_assets\/[^"\']+\.(?:js|mjs))/i', $html);
        $has_empty_app_root = preg_match('/<([a-z0-9:-]+)\b[^>]*\bid\s*=\s*["\'](?:app|root|__next|gatsby-focus-wrapper)["\'][^>]*>\s*(?:<!--[\s\S]*?-->\s*)*<\/\1>/i', $body);
        $tag_count = preg_match_all('/<[a-z][a-z0-9:-]*\b/i', $without_comments);
        return $has_app_root && (
            $has_empty_app_root
            || ($has_client_bundle && strlen($text) < 80 && $tag_count < 12)
            || (!$has_client_bundle && strlen($text) < 80 && $tag_count < 8)
        );
    }
}

if (!function_exists('ugentic_faithful_script_is_ssr_runtime')) {
    function ugentic_faithful_script_is_ssr_runtime($attrs, $body, $framework)
    {
        $attrs = (string) $attrs;
        $body = (string) $body;
        if (preg_match('#\bsrc\s*=\s*(["\'])[^"\']*/(?:ugentic-clones|ugentic-instant-clones)/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?\1#i', $attrs)) {
            return true;
        }
        if ($framework === 'next') {
            return preg_match('#\bsrc\s*=\s*(["\'])[^"\']*/_next/static/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?\1#i', $attrs)
                || preg_match('/\bid\s*=\s*(["\'])__NEXT_DATA__\1/i', $attrs)
                || stripos($body, 'self.__next_f') !== false
                || stripos($body, '__NEXT_DATA__') !== false
                || stripos($body, 'next-route-announcer') !== false;
        }
        if ($framework === 'nuxt') {
            return preg_match('#\bsrc\s*=\s*(["\'])[^"\']*/_nuxt/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?\1#i', $attrs)
                || stripos($body, 'window.__NUXT__') !== false
                || stripos($body, '__NUXT__') !== false;
        }
        if ($framework === 'gatsby') {
            return preg_match('#\bsrc\s*=\s*(["\'])[^"\']*/(?:webpack-runtime|framework|app|commons|component---|page-data)[^"\']*\.(?:js|json)(?:\?[^"\']*)?\1#i', $attrs)
                || stripos($body, 'window.___gatsby') !== false
                || stripos($body, '___loader') !== false;
        }
        if ($framework === 'remix') {
            return preg_match('#\bsrc\s*=\s*(["\'])[^"\']*/build/_assets/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?\1#i', $attrs)
                || stripos($body, '__remixContext') !== false
                || stripos($body, '__remixManifest') !== false;
        }
        if ($framework === 'tanstack') {
            return preg_match('#\bsrc\s*=\s*(["\'])(?:[^"\']*/)?(?:~flock\.js|__l5e/events\.js|assets/[^"\']+\.(?:js|mjs))[^"\']*\1#i', $attrs)
                || stripos($body, '$_TSR') !== false
                || stripos($body, '$tsr') !== false
                || stripos($body, 'tsr-stream-barrier') !== false
                || (preg_match('/\btype\s*=\s*(["\'])module\1/i', $attrs) && preg_match('#import\(\s*(["\'])(?:[^"\']*/(?:ugentic-clones|ugentic-instant-clones)/[^"\']+|/assets/[^"\']+)\.(?:js|mjs)(?:\?[^"\']*)?\1\s*\)#i', $body));
        }
        return false;
    }
}

if (!function_exists('ugentic_faithful_link_is_ssr_script_preload')) {
    function ugentic_faithful_link_is_ssr_script_preload($tag, $framework)
    {
        if (!preg_match('/\brel\s*=\s*(["\'])[^"\']*(?:preload|modulepreload)[^"\']*\1/i', $tag)) {
            return false;
        }
        $is_script_hint = preg_match('/\bmodulepreload\b/i', $tag) || preg_match('/\bas\s*=\s*(["\'])script\1/i', $tag);
        if (!$is_script_hint) {
            return false;
        }
        if (preg_match('#/(?:ugentic-clones|ugentic-instant-clones)/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?#i', $tag)) {
            return true;
        }
        if ($framework === 'next') {
            return (bool) preg_match('#/_next/static/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?#i', $tag);
        }
        if ($framework === 'nuxt') {
            return (bool) preg_match('#/_nuxt/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?#i', $tag);
        }
        if ($framework === 'gatsby') {
            return (bool) preg_match('#/(?:webpack-runtime|framework|app|commons|component---)[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?#i', $tag);
        }
        if ($framework === 'remix') {
            return (bool) preg_match('#/build/_assets/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?#i', $tag);
        }
        if ($framework === 'tanstack') {
            return (bool) preg_match('#/(?:assets|ugentic-clones|ugentic-instant-clones)/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?#i', $tag);
        }
        return false;
    }
}

if (!function_exists('ugentic_faithful_inject_before_closing_tag')) {
    function ugentic_faithful_inject_before_closing_tag($html, $tag, $content)
    {
        if ($content === '') {
            return $html;
        }
        if (preg_match('/<\/' . preg_quote($tag, '/') . '>/i', $html)) {
            return preg_replace('/<\/' . preg_quote($tag, '/') . '>/i', $content . "\n" . '</' . $tag . '>', $html, 1);
        }
        return $html . "\n" . $content;
    }
}

if (!function_exists('ugentic_faithful_inject_static_clone_runtime')) {
    /**
     * Backward-compatible name retained for callers; output is now scriptless.
     */
    function ugentic_faithful_inject_static_clone_runtime($html)
    {
        return class_exists('Ugentic_HTML_Cleaner')
            ? Ugentic_HTML_Cleaner::materialize_static_snapshot($html)
            : $html;
    }
}
if (!function_exists('ugentic_faithful_stabilize_app_builder_snapshot')) {
    function ugentic_faithful_stabilize_app_builder_snapshot($html)
    {
        if (class_exists('Ugentic_HTML_Cleaner')) {
            $html = Ugentic_HTML_Cleaner::strip_legacy_static_runtime($html);
        }
        $framework = ugentic_faithful_detect_ssr_framework($html);
        $client_app_shell = ugentic_faithful_is_client_app_shell($html);
        $needs_static_runtime = ($framework !== '' && !$client_app_shell)
            || preg_match('/\bdata-framer-appear-id\b|\bdata-aos\b|\baos-init\b|\bswiper\b|\bdata-(?:lazy-)?src\b|\bdata-bg\b|\b(?:reveal(?:-(?:left|right|scale))?|wow|fadeIn(?:Up|Down|Left|Right)?)\b/i', $html);
        if (!$needs_static_runtime) {
            return $html;
        }

        if ($framework !== '' && !$client_app_shell) {
            $html = preg_replace_callback('/<script\b([^>]*)>([\s\S]*?)<\/script>/i', function ($m) use ($framework) {
                return ugentic_faithful_script_is_ssr_runtime($m[1], $m[2], $framework) ? '' : $m[0];
            }, $html);
            $html = preg_replace_callback('/<link\b[^>]*>/i', function ($m) use ($framework) {
                return ugentic_faithful_link_is_ssr_script_preload($m[0], $framework) ? '' : $m[0];
            }, $html);
        }

        return ugentic_faithful_inject_static_clone_runtime($html);
    }
}

if (!function_exists('ugentic_faithful_rewrite_localized_client_imports')) {
    function ugentic_faithful_rewrite_localized_client_imports($html)
    {
        if (stripos($html, '/assets/') === false || stripos($html, 'ugentic-clones/') === false) {
            return $html;
        }

        $asset_map = ugentic_faithful_extract_localized_client_asset_map($html);
        if (empty($asset_map)) {
            return $html;
        }

        return preg_replace_callback(
            '/<script\b([^>]*)>([\s\S]*?)<\/script>/i',
            function ($m) use ($asset_map) {
                $body = $m[2];
                foreach ($asset_map as $original_name => $local_url) {
                    $quoted_name = preg_quote($original_name, '/');
                    $safe_url = esc_url($local_url);
                    $body = preg_replace('#(?<![A-Za-z0-9._-])/assets/' . $quoted_name . '(?![A-Za-z0-9._-])#', $safe_url, $body);
                    $body = preg_replace('#(?<![A-Za-z0-9._-])\\\\/assets\\\\/' . $quoted_name . '(?![A-Za-z0-9._-])#', str_replace('/', '\\/', $safe_url), $body);
                }
                return '<script' . $m[1] . '>' . $body . '</script>';
            },
            $html
        );
    }
}

if (!function_exists('ugentic_faithful_extract_localized_client_asset_map')) {
    function ugentic_faithful_extract_localized_client_asset_map($html)
    {
        $map = [];
        if (!preg_match_all('#https?://[^"\'\s<>]+/wp-content/uploads/ugentic-clones/[^"\'\s<>]+\.(?:js|mjs|css|json)(?:\?[^"\'\s<>]*)?#i', $html, $matches)) {
            return $map;
        }

        foreach ($matches[0] as $url) {
            $path = wp_parse_url(html_entity_decode($url, ENT_QUOTES), PHP_URL_PATH);
            $basename = $path ? basename($path) : '';
            if ($basename === '') {
                continue;
            }
            $map[$basename] = $url;
            if (preg_match('/^[a-f0-9]{8,16}-(.+)$/i', $basename, $m)) {
                $map[$m[1]] = $url;
            }
        }

        return $map;
    }
}

if (!function_exists('ugentic_faithful_inject_lovable_color_fallbacks')) {
    function ugentic_faithful_inject_lovable_color_fallbacks($html)
    {
        if (
            strpos($html, 'id="ugentic-lovable-color-fallbacks"') !== false
            || strpos($html, "id='ugentic-lovable-color-fallbacks'") !== false
        ) {
            return $html;
        }

        $looks_lovable = stripos($html, 'lovable.app') !== false
            || stripos($html, 'lovable-badge') !== false
            || stripos($html, '__l5e') !== false
            || stripos($html, 'data-artifact-kind=') !== false;
        $uses_lovable_palette = stripos($html, 'bg-gradient-dark') !== false
            || stripos($html, 'gradient-text-accent') !== false
            || stripos($html, 'text-h1') !== false;

        if (!$looks_lovable || !$uses_lovable_palette) {
            return $html;
        }

        // Lovable/Tailwind v4 exports OKLCH CSS variables. Older publish/browser
        // environments can drop those values, leaving white hero text on white.
        $css = '<style id="ugentic-lovable-color-fallbacks">'
            . ':root{--background:#fff;--foreground:#111827;--card:#fff;--card-foreground:#111827;--popover:#fff;--popover-foreground:#111827;--primary:#2563eb;--primary-foreground:#fff;--primary-dark:#1d4ed8;--accent-purple:#ea580c;--accent-cyan:#06b6d4;--secondary:#f8fafc;--secondary-foreground:#111827;--muted:#f8fafc;--muted-foreground:#64748b;--accent:#eef2ff;--accent-foreground:#2563eb;--destructive:#dc2626;--destructive-foreground:#fff;--success:#10b981;--warning:#f59e0b;--border:#e2e8f0;--input:#e2e8f0;--ring:#2563eb;--surface:#f8fafc;--surface-dark:#334155;--bg-dark:#111827;--text-primary:#111827;--text-secondary:#64748b;--text-tertiary:#94a3b8;--color-background:#fff;--color-border:#e2e8f0;--color-text-primary:#111827;--gradient-hero:linear-gradient(135deg,#2563eb 0%,#ea580c 100%);--gradient-accent:linear-gradient(135deg,#06b6d4 0%,#2563eb 100%);--gradient-subtle:linear-gradient(to bottom,#fff 0%,#f8fafc 100%);--gradient-dark:linear-gradient(135deg,#111827 0%,#334155 100%);}'
            . '.dark{--background:#111827;--foreground:#f8fafc;--card:#1f2937;--card-foreground:#f8fafc;--border:rgba(255,255,255,.1);--input:rgba(255,255,255,.15);}'
            . 'html body .bg-gradient-dark{background:linear-gradient(135deg,#111827 0%,#334155 100%)!important;}'
            . 'html body .bg-gradient-hero{background:linear-gradient(135deg,#2563eb 0%,#ea580c 100%)!important;}'
            . 'html body .bg-gradient-subtle{background:linear-gradient(to bottom,#fff 0%,#f8fafc 100%)!important;}'
            . 'html body .gradient-text,html body .gradient-text-accent{background:linear-gradient(135deg,#06b6d4 0%,#2563eb 100%);-webkit-background-clip:text;background-clip:text;color:transparent;}'
            . 'html body .text-primary{color:#2563eb!important;}'
            . 'html body .text-text-primary{color:#111827!important;}'
            . 'html body .text-text-secondary{color:#64748b!important;}'
            . 'html body .text-text-tertiary{color:#94a3b8!important;}'
            . 'html body .text-bg-dark{color:#111827!important;}'
            . 'html body .text-accent-cyan{color:#06b6d4!important;}'
            . 'html body .bg-primary,html body .bg-primary\\/30{background-color:#2563eb!important;}'
            . 'html body .bg-accent-purple,html body .bg-accent-purple\\/30{background-color:#ea580c!important;}'
            . 'html body .bg-accent-cyan,html body .bg-accent-cyan\\/20{background-color:#06b6d4!important;}'
            . '</style>';

        return ugentic_faithful_inject_before_closing_tag($html, 'head', $css);
    }
}

if (!function_exists('ugentic_faithful_snapshot_metrics')) {
    function ugentic_faithful_snapshot_metrics($html)
    {
        $body = $html;
        if (preg_match('/<body\b[^>]*>([\s\S]*?)<\/body>/i', $html, $m)) {
            $body = $m[1];
        }
        $probe = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $body);
        $probe = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $probe);
        $probe = preg_replace('/<!--[\s\S]*?-->/s', '', $probe);
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($probe)));
        return [
            'body_text_length' => strlen($text),
            'body_tag_count' => preg_match_all('/<[a-z][a-z0-9:-]*\b/i', $probe),
            'has_layout' => preg_match('/<(?:main|section|header|footer)\b/i', $body) || stripos($body, 'data-ugentic-editable') !== false,
        ];
    }
}

if (!function_exists('ugentic_faithful_has_risky_publish_runtime')) {
    function ugentic_faithful_has_risky_publish_runtime($html)
    {
        return preg_match('/<script\b[^>]*(?:\btype\s*=\s*(["\'])module\1|\bsrc\s*=\s*(["\'])[^"\']*(?:ugentic-clones|ugentic-instant-clones|__l5e|~flock|\/assets\/)[^"\']*\2)[^>]*>/i', $html)
            || preg_match('/<script\b[^>]*>[\s\S]*?(?:\$_TSR|\$tsr|hydrateRoot|createRoot|import\(\s*(["\'])(?:\/assets\/|[^"\']*\/ugentic-clones\/))[\s\S]*?<\/script>/i', $html);
    }
}

if (!function_exists('ugentic_faithful_inject_static_snapshot_guard')) {
    function ugentic_faithful_inject_static_snapshot_guard($html)
    {
        if (strpos($html, 'id="ugentic-static-snapshot-guard"') !== false || strpos($html, "id='ugentic-static-snapshot-guard'") !== false) {
            return $html;
        }

        $metrics = ugentic_faithful_snapshot_metrics($html);
        if (empty($metrics['has_layout']) || intval($metrics['body_text_length']) < 120 || !ugentic_faithful_has_risky_publish_runtime($html)) {
            return $html;
        }

        $js = '<script id="ugentic-static-snapshot-guard">(function(){try{if(!document.body)return;function textLen(){return((document.body&&document.body.innerText)||"").replace(/\s+/g," ").trim().length;}function hasLayout(){return!!document.querySelector("main,section,header,footer,[data-ugentic-editable]");}var expected=textLen();if(expected<120)return;var clone=document.body.cloneNode(true);Array.prototype.slice.call(clone.querySelectorAll("script")).forEach(function(s){var src=s.getAttribute("src")||"",type=(s.getAttribute("type")||"").toLowerCase(),txt=s.textContent||"",id=s.id||"",cls=String(s.className||"");if(type==="module"||src.indexOf("/ugentic-clones/")!==-1||src.indexOf("/ugentic-instant-clones/")!==-1||src.indexOf("/__l5e/")!==-1||src.indexOf("/~flock")!==-1||txt.indexOf("$_TSR")!==-1||txt.indexOf("$tsr")!==-1||id.indexOf("$tsr")!==-1||cls.indexOf("$tsr")!==-1){s.remove();}});var snapshot=clone.innerHTML;function check(){var now=textLen();if((expected>120&&now<Math.max(80,expected*.2))||!hasLayout()){document.body.innerHTML=snapshot;document.documentElement.setAttribute("data-ugentic-snapshot-restored","1");}}[100,500,1500,3500].forEach(function(ms){setTimeout(check,ms);});window.addEventListener("load",function(){setTimeout(check,250);setTimeout(check,2000);},{once:true});}catch(e){}})();</script>';

        if (preg_match('/<script\b[^>]*(?:\btype\s*=\s*(["\'])module\1|\bsrc\s*=\s*(["\'])[^"\']*(?:ugentic-clones|ugentic-instant-clones|__l5e|~flock|\/assets\/)[^"\']*\2)[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = intval($m[0][1]);
            return substr($html, 0, $pos) . $js . "\n" . substr($html, $pos);
        }

        return ugentic_faithful_inject_before_closing_tag($html, 'body', $js);
    }
}

if (!function_exists('ugentic_faithful_strip_wp_theme_residue')) {
    function ugentic_faithful_strip_wp_theme_residue($html)
    {
        $html = preg_replace('/<style id="global-styles-inline-css">[\s\S]*?<\/style>\s*/i', '', $html);
        $html = preg_replace('/<script id="kadence-navigation-js-extra">[\s\S]*?<\/script>\s*/i', '', $html);

        $patterns = [
            '/<style\b[^>]*\bid=["\'](?:global-styles-inline-css|classic-theme-styles-inline-css|core-block-supports-inline-css|wp-[^"\']+)["\'][^>]*>[\s\S]*?<\/style>/i',
            '/<link\b[^>]*\bid=["\'](?:wp-block-library-css|global-styles-css|classic-theme-styles-css|kadence-[^"\']+|[^"\']*-theme-css)["\'][^>]*>/i',
            // Keep WordPress runtime handles such as wp-i18n/wp-a11y. Tahmir's
            // native media picker depends on them, and removing only the main
            // script while leaving its inline translation payload breaks the
            // entire editor startup sequence.
            '/<script\b[^>]*\bid=["\'](?:kadence-[^"\']+|wp-emoji-[^"\']+)["\'][^>]*>[\s\S]*?<\/script>/i',
            '/<script\b[^>]*\bsrc=["\'][^"\']*\/wp-content\/themes\/[^"\']*["\'][^>]*>\s*<\/script>/i',
        ];

        return preg_replace($patterns, '', $html);
    }
}

$html = ugentic_faithful_rewrite_localized_client_imports($html);
$html = ugentic_faithful_inject_lovable_color_fallbacks($html);
$faithful_builder_render_guard = '';
if ($is_builder_request && !$builder_full_code_mode) {
    if (class_exists('Ugentic_HTML_Cleaner')) {
        $html = Ugentic_HTML_Cleaner::strip_legacy_static_runtime($html);
        $html = Ugentic_HTML_Cleaner::neutralize_imported_loading_state($html);
        $faithful_builder_render_guard = Ugentic_HTML_Cleaner::imported_loading_guard_markup()
            . Ugentic_HTML_Cleaner::imported_builder_content_guard_markup();
    }
} else {
    if (!$is_builder_request) {
        $html = ugentic_faithful_stabilize_app_builder_snapshot($html);
    }
}

if (!function_exists('ugentic_faithful_html_contains_shortcode')) {
    function ugentic_faithful_html_contains_shortcode($html)
    {
        if (strpos($html, '[') === false || !function_exists('get_shortcode_regex')) {
            return false;
        }
        $probe = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
        $probe = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $probe);
        $probe = preg_replace('/<!--[\s\S]*?-->/s', '', $probe);
        return (bool) preg_match('/' . get_shortcode_regex() . '/s', $probe);
    }
}

// â”€â”€ Process ALL shortcodes in the faithful HTML â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Faithful pages serve a complete HTML document. WordPress shortcodes
// would otherwise appear as raw text. We use safe_do_shortcode() which
// protects <style> and <script> blocks from being mangled, then runs
// full do_shortcode() to support ALL plugins (WPForms, CF7, Woo, etc).
if (class_exists('Ugentic_Assets') && ugentic_faithful_html_contains_shortcode($html)) {
    $html = Ugentic_Assets::safe_do_shortcode($html);
}

// â”€â”€ Helper: strip non-ugentic styles/scripts from WP output â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Same approach as blank-template.php â€” keeps SEO/analytics plugins working
// while preventing theme CSS from conflicting with the faithful page
if (!function_exists('ugentic_clean_wp_output')) {
    /**
     * Keep the faithful page isolated from theme CSS. During a builder request,
     * retain only WordPress core styles required by its native Media Library.
     */
    function ugentic_clean_wp_output($html, $allow_builder_media = false)
    {
        $base_pattern = '/(?:ugentic|tahmir|admin-bar|dashicons|font-awesome|cdnjs\.cloudflare)/i';
        $media_pattern = '/(?:'
            . '\bid\s*=\s*(["\'])(?:buttons|forms|common|dashicons|mediaelement|wp-mediaelement|media-views|imgareaselect|wp-jquery-ui-dialog|wp-auth-check|thickbox)(?:-inline)?-css\1'
            . '|\/wp-(?:admin|includes)\/[^"\']*(?:buttons|forms|common|dashicons|media-views|mediaelement|wp-mediaelement|imgareaselect|jquery-ui-dialog|wp-auth-check|thickbox)[^"\']*\.css'
            . ')/i';

        $html = preg_replace_callback(
            '/<style\b[^>]*>[\s\S]*?<\/style>/i',
            function ($match) use ($base_pattern, $media_pattern, $allow_builder_media) {
                $tag = $match[0];
                if (preg_match($base_pattern, $tag)) {
                    return $tag;
                }
                return $allow_builder_media && preg_match($media_pattern, $tag) ? $tag : '';
            },
            $html
        );

        $html = preg_replace_callback(
            '/<link\b[^>]*\brel\s*=\s*(["\'])stylesheet\1[^>]*>/i',
            function ($match) use ($base_pattern, $media_pattern, $allow_builder_media) {
                $tag = $match[0];
                if (preg_match($base_pattern, $tag)) {
                    return $tag;
                }
                return $allow_builder_media && preg_match($media_pattern, $tag) ? $tag : '';
            },
            $html
        );

        $html = preg_replace(
            '/<script[^>]+src=["\'][^"\']*\/themes\/[^"\']*["\'][^>]*>.*?<\/script>/is',
            '',
            $html
        );
        return $html;
    }
}

// Inject content before </head> with a safe fallback.
if (!function_exists('ugentic_inject_head')) {
    function ugentic_inject_head($html, $content)
    {
        if (empty($content))
            return $html;
        // Try </head>
        if (preg_match('/<\/head>/i', $html)) {
            return preg_replace('/<\/head>/i', $content . "\n" . '</head>', $html, 1);
        }
        // Fallback: inject before <body
        if (preg_match('/<body[\s>]/i', $html)) {
            return preg_replace('/<body([\s>])/i', $content . "\n" . '<body$1', $html, 1);
        }
        // Last resort: prepend
        return $content . "\n" . $html;
    }
}

// â”€â”€ Helper: inject content before </body> with fallback â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (!function_exists('ugentic_inject_body')) {
    function ugentic_inject_body($html, $content)
    {
        if (empty($content))
            return $html;
        // Try </body>
        if (preg_match('/<\/body>/i', $html)) {
            return preg_replace('/<\/body>/i', $content . "\n" . '</body>', $html, 1);
        }
        // Fallback: inject before </html>
        if (preg_match('/<\/html>/i', $html)) {
            return preg_replace('/<\/html>/i', $content . "\n" . '</html>', $html, 1);
        }
        // Last resort: append
        return $html . "\n" . $content;
    }
}

// â”€â”€ Inject wp_head() output into <head> â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// This enables SEO plugins (Yoast, RankMath), analytics (GA), and other
// WP plugins that hook into wp_head to inject their meta tags/scripts.
if (!empty($faithful_builder_render_guard)) {
    $html = ugentic_inject_head($html, $faithful_builder_render_guard);
}

$faithful_metadata_generator = ugentic_builder()->blog_generator;
if (
    !$is_builder_request
    && $faithful_metadata_generator
    && method_exists($faithful_metadata_generator, 'strip_stale_document_metadata')
) {
    $html = $faithful_metadata_generator->strip_stale_document_metadata($html);
}

if ($is_builder_request && is_user_logged_in() && current_user_can('upload_files')) {
    wp_enqueue_media();
}
ob_start();
wp_head();
$wp_head_output = ob_get_clean();
$wp_head_cleaned = ugentic_clean_wp_output($wp_head_output, $is_builder_request);

if (
    !$is_builder_request
    && $faithful_metadata_generator
    && method_exists($faithful_metadata_generator, 'build_document_metadata_markup')
) {
    $faithful_queried_post = is_singular() ? get_queried_object() : null;
    $wp_head_cleaned = $faithful_metadata_generator->build_document_metadata_markup(
        $wp_head_cleaned,
        $faithful_queried_post
    ) . $wp_head_cleaned;
}

if (!empty($wp_head_cleaned)) {
    if ($is_builder_request) {
        $wp_head_cleaned = "\n<!-- ugentic-editor-runtime:start -->\n"
            . $wp_head_cleaned
            . "\n<!-- ugentic-editor-runtime:end -->\n";
    }
    $html = ugentic_inject_head($html, $wp_head_cleaned);
}

// Capture the imported document's resolved body appearance after its own CSS
// has loaded, but before editor footer scripts add builder classes. The canvas
// can then retain the source palette while the surrounding editor stays neutral.
if ($is_builder_request) {
    $source_appearance_capture = '<script data-ugentic-no-save="source-appearance">'
        . '(function(){if(!document.body||window.ugenticSourcePageAppearance)return;try{var sourceStyle=window.getComputedStyle(document.body);window.ugenticSourcePageAppearance={background:sourceStyle.background,backgroundColor:sourceStyle.backgroundColor,backgroundImage:sourceStyle.backgroundImage,color:sourceStyle.color,fontFamily:sourceStyle.fontFamily,fontSize:sourceStyle.fontSize,lineHeight:sourceStyle.lineHeight};}catch(ignore){}})();'
        . '</script>';
    $html = ugentic_inject_body($html, $source_appearance_capture);
}

// â”€â”€ Inject wp_footer() output before </body> â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
ob_start();
wp_footer();
$wp_footer_output = ob_get_clean();
$wp_footer_cleaned = ugentic_clean_wp_output($wp_footer_output, $is_builder_request);

if (!empty($wp_footer_cleaned)) {
    if ($is_builder_request) {
        $wp_footer_cleaned = "\n<!-- ugentic-editor-runtime:start -->\n"
            . $wp_footer_cleaned
            . "\n<!-- ugentic-editor-runtime:end -->\n";
    }
    $html = ugentic_inject_body($html, $wp_footer_cleaned);
}

// Faithful pages bypass Ugentic_Assets::output_global_js() because they serve
// a complete HTML document. Still honor the Global Assets manager: these
// scripts are explicitly enabled by an administrator and are commonly used to
// restore shared navigation, sliders, and other imported-site interactions.
$faithful_global_js = '';
$faithful_global_js_assets = $is_builder_request ? [] : $database->get_assets_by_type('js', true);

foreach ($faithful_global_js_assets as $faithful_js_asset) {
    $faithful_js_content = trim((string) $faithful_js_asset->content);
    if ($faithful_js_content === '') {
        continue;
    }

    $faithful_js_id = 'site-global-js-' . absint($faithful_js_asset->id);
    if (strpos($html, 'id="' . $faithful_js_id . '"') !== false) {
        continue;
    }

    if (preg_match('/^\/\*\s*external:\s*(https?:\/\/\S+)\s*\*\/$/i', $faithful_js_content, $faithful_external_match)) {
        $faithful_global_js .= '<script id="' . esc_attr($faithful_js_id) . '" src="' . esc_url($faithful_external_match[1]) . '"></script>' . "\n";
        continue;
    }

    $faithful_global_js .= '<script id="' . esc_attr($faithful_js_id) . '">' . "\n";
    $faithful_global_js .= '/* ' . esc_html($faithful_js_asset->name) . ' v' . esc_html($faithful_js_asset->version) . ' */' . "\n";
    $faithful_global_js .= $faithful_js_asset->content . "\n</script>\n";
}

if ($faithful_global_js !== '') {
    $html = ugentic_inject_body($html, "\n<!-- Site global JavaScript -->\n" . $faithful_global_js);
}

// â”€â”€ Inject form shortcode assets into <head> and footer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Client-rendered imports can create navigation after PHP has finished
// rewriting stored HTML. Keep those links aligned with imported WP pages.
if (!$is_builder_request && method_exists($database, 'build_link_map')) {
    $faithful_navigation_map = [];
    foreach ($database->build_link_map() as $source_key => $target_url) {
        $source_path = wp_parse_url(
            html_entity_decode((string) $source_key, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            PHP_URL_PATH
        );
        if (!is_string($source_path) || $source_path === '') {
            continue;
        }

        $source_basename = strtolower(basename(str_replace('\\', '/', $source_path)));
        if ($source_basename === '' || $source_basename === '.' || $source_basename === '..') {
            continue;
        }

        $faithful_navigation_map[$source_basename] = esc_url_raw((string) $target_url);
    }

    require_once UGENTIC_PLUGIN_DIR . 'includes/class-zip-assets.php';
    $zip_navigation = Ugentic_Zip_Assets::navigation($ugentic_page_record, $database);
    if ($faithful_navigation_map || $zip_navigation) {
        $faithful_navigation_runtime_path = UGENTIC_PLUGIN_DIR . 'public/js/faithful-navigation-runtime.js';
        $faithful_navigation_runtime_url = UGENTIC_PLUGIN_URL . 'public/js/faithful-navigation-runtime.js';
        $faithful_navigation_runtime_version = file_exists($faithful_navigation_runtime_path)
            ? (string) filemtime($faithful_navigation_runtime_path)
            : UGENTIC_VERSION;
        $faithful_navigation_config = wp_json_encode(array_merge([
            'map' => $faithful_navigation_map,
            'homeUrl' => home_url('/'),
        ], $zip_navigation), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $faithful_navigation_markup = Ugentic_Assets::render_asset('inline-js', 'faithful-navigation-config', 'window.ugenticNavigationRuntime=' . $faithful_navigation_config . ';')
            . Ugentic_Assets::render_asset('js', 'faithful-navigation-runtime', $faithful_navigation_runtime_url . '?ver=' . rawurlencode($faithful_navigation_runtime_version));
        $html = ugentic_inject_body($html, "\n" . $faithful_navigation_markup);
    }
}

// If the page contained [ugentic_form], inject CSS in <head> and JS in
// footer. Skip if wp_enqueue_scripts already loaded them via wp_head/wp_footer.
if (class_exists('Ugentic_Form_Renderer') && strpos($faithful_source_html, '[ugentic_form') !== false) {
    $uf_css_url  = UGENTIC_PLUGIN_URL . 'public/css/form.css';
    $uf_js_url   = UGENTIC_PLUGIN_URL . 'public/js/form.js';
    $uf_css_ver  = class_exists('Ugentic_Form_Renderer') ? Ugentic_Form_Renderer::asset_version('public/css/form.css') : UGENTIC_VERSION;
    $uf_js_ver   = class_exists('Ugentic_Form_Renderer') ? Ugentic_Form_Renderer::asset_version('public/js/form.js') : UGENTIC_VERSION;
    $uf_ajax_url = esc_url(admin_url('admin-ajax.php'));
    $uf_submitting = esc_js(__('Submitting...', 'tahmir-ai-to-wp-converter'));
    $uf_error      = esc_js(__('An error occurred. Please try again.', 'tahmir-ai-to-wp-converter'));

    // CSS in <head> (only if not already present from wp_enqueue_scripts)
    if (strpos($html, 'form.css') === false) {
        $form_css = Ugentic_Assets::render_asset('css', 'faithful-form-template', $uf_css_url . '?ver=' . $uf_css_ver);
        $html = ugentic_inject_head($html, $form_css);
    }

    $form_js = '';
    if (strpos($html, 'ugentic-form-config') === false) {
        $form_js = Ugentic_Assets::render_asset(
            'inline-js',
            'faithful-form-template-config',
            'window.ugenticForm=window.ugenticForm||{ajaxUrl:' . wp_json_encode($uf_ajax_url) . ',strings:{submitting:' . wp_json_encode($uf_submitting) . ',error:' . wp_json_encode($uf_error) . '}};'
            . 'window.ugenticAjax=window.ugenticAjax||{url:' . wp_json_encode($uf_ajax_url) . '};'
        );
    }

    // JS loader (only if not already present from wp_enqueue_scripts)
    if (strpos($html, 'form.js') === false) {
        $form_js .= Ugentic_Assets::render_asset('js', 'faithful-form-template', $uf_js_url . '?ver=' . $uf_js_ver);
    }
    if (!empty($form_js)) {
        $html = ugentic_inject_body($html, $form_js);
    }
}

// â”€â”€ Admin bar support for logged-in admins â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (!$faithful_is_blog_mapper_request && is_user_logged_in() && is_admin_bar_showing()) {
    // Render admin bar HTML
    ob_start();
    wp_admin_bar_render();
    $admin_bar_html = ob_get_clean();

    if (!empty($admin_bar_html)) {
        $html = ugentic_inject_body($html, $admin_bar_html);
    }

}

// Cookie-based auth (ugentic_edit_auth) now handles cross-page authentication.
// When a user first visits with ?ugentic_edit=TOKEN, the cookie is set and
// the URL is cleaned via redirect. No script injection needed.

// â”€â”€ Approved Tahmir AI SEO/AISEO metadata â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Faithful pages serve a complete imported document, so apply approved
// Ugentic metadata at the end to remove duplicate imported/WP SEO tags and
// inject the reviewed canonical, social, robots, AI, and schema signals once.
if (class_exists('Ugentic_SEO_AISEO')) {
    $seo_metadata = Ugentic_SEO_AISEO::get_render_metadata($wp_page_id, $ugentic_page_id, $ugentic_page_record);
    if (!empty($seo_metadata['approved'])) {
        $html = Ugentic_SEO_AISEO::apply_metadata_to_full_document($html, $seo_metadata);
    }
}

$html = ugentic_faithful_strip_wp_theme_residue($html);

// Restore only exact-hash, same-import scripts on the public page. Builder
// sessions never execute them, so runtime-created nodes cannot enter saves.
if (!$is_builder_request && !empty($faithful_verified_script_tags)) {
    $html = ugentic_inject_body($html, "\n" . implode("\n", $faithful_verified_script_tags));
}

// Output the complete HTML document directly â€” no WordPress header/footer wrapping
if (class_exists('Ugentic_Asset_Optimizer')) {
    $html = Ugentic_Asset_Optimizer::minify_rendered_html($html);
}
$html = Tahmir_Free_Edition::prepare_render($html, $is_builder_request);
// The faithful canvas serves a complete imported document. Active imported content was stripped above;
// any restored script must match the local file manifest's path, size, hash, and integrity value.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized full-document output.
echo $html;
exit; // Prevent any further WordPress output
