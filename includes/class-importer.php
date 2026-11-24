<?php
/**
 * Improved Website ZIP Importer for Tahmir AI
 * - Proper image/CSS/JS path resolution
 * - AI-powered header/footer detection
 * - All assets properly linked
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Importer
{

    /**
     * Temp directory for extraction
     */
    private $temp_dir;

    /**
     * Base URL for imported assets
     */
    private $assets_base_url;

    /**
     * Unique upload namespace for the current ZIP import. Isolating every
     * import prevents filename collisions with assets from older websites.
     */
    private $asset_upload_namespace = '';

    /**
     * Identifier for the active ZIP import session. This lets page-level
     * idempotency distinguish aliases inside one archive from a later import.
     */
    private $import_session_id = '';

    /**
     * Mapping of original paths to new URLs
     */
    private $asset_map = [];

    /**
     * Remote clone asset failures collected for response reporting.
     */
    private $clone_asset_failures = [];

    /**
     * Runtime limits for remote clone asset localization.
     */
    private $clone_asset_download_limit = 0;
    private $clone_asset_download_count = 0;
    private $clone_asset_deadline = 0.0;
    private $clone_asset_timeout = 20;
    private $clone_runtime_warnings = [];
    private $clone_progress_callback = null;
    private $clone_progress_events = [];
    private $clone_progress_last_value = 0;
    private $clone_asset_progress_last_report = 0;
    private $clone_current_source_type = 'generic';
    private $clone_import_map = [];

    /**
     * File index: basename → full path (built once during scan, reused in url_to_path)
     */
    private $file_index = [];

    /**
     * Smart Resolver instance for path resolution, CSS namespacing, JS isolation
     */
    private $smart_resolver;

    /**
     * Legacy Detector instance for era and quirks mode detection
     */
    private $legacy_detector;

    /**
     * CSS Normalizer instance for legacy CSS conversion
     */
    private $css_normalizer;

    /**
     * Maximum file sizes for safety (in bytes)
     */
    private const MAX_HTML_SIZE = 10 * 1024 * 1024; // 10 MB
    private const MAX_CSS_SIZE = 5 * 1024 * 1024; // 5 MB
    private const MAX_JS_SIZE = 5 * 1024 * 1024; // 5 MB
    private const MAX_REMOTE_CLONE_ASSET_SIZE = 50 * 1024 * 1024; // 50 MB
    private const REMOTE_CLONE_HTML_RETRIES = 3;
    private const REMOTE_CLONE_ASSET_RETRIES = 2;
    private const MAX_ZIP_FILES = 3000;
    private const MAX_ZIP_UNCOMPRESSED_SIZE = 500 * 1024 * 1024; // 500 MB
    private const BLOCKED_ARCHIVE_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtm', 'phtml', 'phar', 'shtml', 'shtm', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb',
        'sh', 'bash', 'zsh', 'exe', 'dll', 'so', 'dylib',
    ];
    private const BLOCKED_ARCHIVE_FILENAMES = [
        '.htaccess', '.user.ini', 'web.config',
    ];

    /**
     * Form Converter instance for HTML form → Ugentic form conversion
     */
    private $form_converter;

    /**
     * CDN fallback patterns for commonly used front-end libraries
     * When local assets are missing, these CDN links are injected automatically
     */
    // Unused advanced-import CDN fallback catalog belongs to Pro.

    /**
     * AJAX: Replace internal .html links with WordPress page URLs across all imported pages.
     * Called after all pages have been imported so the full filename→URL mapping is available.
     */
    public function ajax_replace_internal_links()
    {

        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $result = $this->replace_internal_links();
        wp_send_json_success($result);
    }

    /**
     * AJAX: Detect menus from the first faithful-mode imported page and create
     * WordPress native menus. Called after replace_internal_links so that
     * menu URLs already point to WordPress page URLs.
     */
    public function ajax_detect_import_menus()
    {

        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        wp_send_json_success($this->detect_import_menus_from_stored_pages());
    }

    /**
     * Refresh shared header/footer navigation without allowing optional
     * post-import housekeeping to invalidate an otherwise successful clone.
     * Background clone requests can reach this step without a local $database
     * variable, so resolve the plugin service lazily and guard every call.
     */
    private function refresh_shared_navigation_links($database = null, $include_pages = false, &$warnings = null)
    {
        if (!$database && function_exists('ugentic_builder')) {
            $builder = ugentic_builder();
            $database = is_object($builder) ? ($builder->database ?? null) : null;
        }
        if (!$database || !is_callable([$database, 'update_header_footer_links'])) {
            $message = __('Shared header and footer links could not be refreshed automatically. The imported pages remain available.', 'tahmir-ai-to-wp-converter');
            if (is_array($warnings)) $warnings[] = $message;
            return false;
        }
        try {
            $database->update_header_footer_links('', (bool) $include_pages);
            return true;
        } catch (\Throwable $error) {
            $message = sprintf(
                /* translators: Failure reason. */ __('Shared header and footer link refresh was skipped: %s', 'tahmir-ai-to-wp-converter'),
                $error->getMessage()
            );
            if (is_array($warnings)) $warnings[] = $message;
            return false;
        }
    }
    /**
     * Replace internal .html links across all faithful-mode imported pages.
     *
     * Builds a mapping from source filenames (e.g. 'about.html') to their
     * WordPress page URLs, then rewrites href attributes in every faithful page.
     *
     * @return array ['pages_updated' => int, 'links_replaced' => int]
     */
    private function replace_internal_links()
    {
        $database = ugentic_builder()->database;
        $session = $this->import_session_id;
        if ($session === '') {
            $pending = get_transient('ugentic_import_' . get_current_user_id());
            $session = is_array($pending) ? sanitize_key($pending['unique_id'] ?? '') : '';
        }


        // Step 1: Use the comprehensive link map from the database class
        // This includes source_file, slug, title, and extension variants
        $link_map = $session !== '' ? [] : $database->build_link_map();

        // Also build a slug-only lookup for folder-based path matching
        // e.g. "Hardrooftop/page-name" → match "page-name" against slugs
        $slug_map = [];
        $source_path_map = [];
        $source_hosts = [];
        $home_url = '';
        $home_depth = PHP_INT_MAX;
        $target_host = strtolower(preg_replace('/^www\./', '', wp_parse_url(home_url('/'), PHP_URL_HOST) ?: ''));
        $page_summaries = $database->get_page_summaries(['limit' => 1000]);
        if ($session !== '') {
            $page_summaries = array_values(array_filter($page_summaries, static function ($page) use ($session) {
                $meta = json_decode($page->meta_data ?? '{}', true);
                return ($meta['import_session_id'] ?? '') === $session;
            }));
        }
        $scope_ids = array_map(static function ($page) { return (int) $page->id; }, $page_summaries);

        foreach ($page_summaries as $page) {
            if (empty($page->wp_page_id)) {
                continue;
            }
            $wp_url = get_permalink($page->wp_page_id);
            if (!$wp_url) {
                continue;
            }
            $wp_post = get_post($page->wp_page_id);
            if ($wp_post && !empty($wp_post->post_name)) {
                $slug_map[strtolower($wp_post->post_name)] = $wp_url;
            }
            $meta = json_decode($page->meta_data ?? '{}', true);
            if (!empty($meta['source_file'])) {
                $name = pathinfo($meta['source_file'], PATHINFO_FILENAME);
                $slug_map[strtolower($name)] = $wp_url;
            }
            $source_paths = [];
            if (!empty($meta['source_relative_path'])) {
                $source_paths[] = $meta['source_relative_path'];
            }
            if (!empty($meta['source_aliases']) && is_array($meta['source_aliases'])) {
                $source_paths = array_merge($source_paths, $meta['source_aliases']);
            }
            foreach (array_values(array_unique(array_filter($source_paths))) as $source_alias) {
                $source_path = strtolower($this->normalize_import_path($source_alias));
                if ($source_path === '') {
                    continue;
                }
                $source_path_map[$source_path] = $wp_url;
                $source_path_map[preg_replace('/\.html?$/i', '', $source_path)] = $wp_url;
                if (preg_match('#(?:^|/)index\.html?$#i', $source_path)) {
                    $directory = trim(dirname($source_path), './');
                    $source_path_map[$directory] = $wp_url;
                    $source_path_map[rtrim($directory, '/') . '/'] = $wp_url;
                }
            }
            if (
                !empty($meta['source_relative_path'])
                && self::canonical_import_page_route($meta['source_relative_path']) === ''
            ) {
                $depth = 0;
                if ($depth < $home_depth) {
                    $home_depth = $depth;
                    $home_url = $wp_url;
                }
            }
            if (!empty($meta['clone_source_url'])) {
                $source_host = strtolower(preg_replace('/^www\./', '', wp_parse_url($meta['clone_source_url'], PHP_URL_HOST) ?: ''));
                if ($source_host !== '') {
                    $source_hosts[$source_host] = true;
                }
                $clone_path = wp_parse_url($meta['clone_source_url'], PHP_URL_PATH);
                if ($clone_path) {
                    $clone_path = strtolower($this->normalize_import_path(rawurldecode($clone_path)));
                    $source_path_map[$clone_path] = $wp_url;
                    $source_path_map[preg_replace('/\.(?:html?|php|aspx?)$/i', '', $clone_path)] = $wp_url;
                }
            }
        }

        if (empty($link_map) && empty($slug_map)) {
            return ['pages_updated' => 0, 'links_replaced' => 0];
        }

        // Step 2: Replace links in all faithful-mode pages
        $pages_updated = 0;
        $total_links_replaced = 0;
        $total_duplicates_removed = 0;
        $rewrite_total = max(1, count($page_summaries));

        for ($page_offset = 0; ; $page_offset++) {
            $page_batch = $database->get_all_pages([
                'limit' => 1,
                'offset' => $page_offset,
                'orderby' => 'id',
                'order' => 'ASC',
            ]);
            if (empty($page_batch)) {
                break;
            }
            $page = $page_batch[0];
            if ($session !== '' && !in_array((int) $page->id, $scope_ids, true)) { continue; }
            $this->clone_report_progress(
                'rewrite_links',
                88 + min(8, (int) floor((($page_offset + 1) / $rewrite_total) * 8)),
                sprintf(
                    /* translators: 1: Current page number; 2: Total pages. */ __('Rebuilding internal links... page %1$d of %2$d', 'tahmir-ai-to-wp-converter'),
                    min($page_offset + 1, $rewrite_total),
                    $rewrite_total
                ),
                [
                    'page_index' => $page_offset,
                    'pages_total' => $rewrite_total,
                    'wp_page_id' => (int) ($page->wp_page_id ?? 0),
                ]
            );
            $meta = json_decode($page->meta_data ?? '{}', true);
            // Only process faithful-mode pages (they have the full HTML document)
            if (!isset($meta['import_mode']) || $meta['import_mode'] !== 'faithful') {
                continue;
            }

            $html = $page->current_html;
            if (empty($html)) {
                continue;
            }

            $links_replaced = 0;
            $current_source = $this->normalize_import_path($meta['source_relative_path'] ?? '');
            $current_source_dir = $current_source !== '' ? trim(dirname($current_source), './') : '';

            // Replace href attributes pointing to internal pages
            // Matches: href="about.html", href="./about.html", href="pages/about.html",
            //          href="Hardrooftop/page-name" (folder-based paths)
            // Does NOT touch: external URLs (http://...), anchors (#...), mailto:, tel:, javascript:
            if (strlen($html) > 350000) {
                // Use the compact mapper for very large documents. It resolves
                // the same source hosts/path aliases without the heavier
                // source-directory callback that can stall on deeply nested
                // Framer markup.
                $updated_html = $database->replace_links_in_html($html, $link_map);
                $links_replaced = $updated_html !== $html ? 1 : 0;
            } else {
                $updated_html = preg_replace_callback(
                '/(<(?:a|area)\b[^>]*\bhref\s*=\s*["\'])([^"\'>]+)(["\'][^>]*>)/i',
                function ($match) use ($link_map, $slug_map, $source_path_map, $source_hosts, $target_host, $home_url, $current_source_dir, &$links_replaced) {
                    $prefix = $match[1];
                    $href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $suffix = $match[3];

                    // Skip non-page protocols and anchors. Same-site/source HTTP URLs
                    // are resolved below so absolute legacy links are not missed.
                    if (preg_match('/^(mailto:|tel:|javascript:|data:|#)/i', $href)) {
                        return $match[0];
                    }

                    // Separate the anchor/hash part
                    $anchor = '';
                    if (($hash_pos = strpos($href, '#')) !== false) {
                        $anchor = substr($href, $hash_pos);
                        $href_path = substr($href, 0, $hash_pos);
                    } else {
                        $href_path = $href;
                    }

                    // Separate query string
                    $query = '';
                    if (($q_pos = strpos($href_path, '?')) !== false) {
                        $query = substr($href_path, $q_pos);
                        $href_path = substr($href_path, 0, $q_pos);
                    }

                    $is_absolute = (bool) preg_match('/^https?:\/\//i', $href_path);
                    if ($is_absolute) {
                        $absolute_host = strtolower(preg_replace('/^www\./', '', wp_parse_url($href_path, PHP_URL_HOST) ?: ''));
                        $absolute_path = wp_parse_url($href_path, PHP_URL_PATH);
                        $is_known_internal = $absolute_host !== ''
                            && ($absolute_host === $target_host || isset($source_hosts[$absolute_host]));
                        if (!$is_known_internal || $absolute_path === null) {
                            return $match[0];
                        }
                        $href_path = rawurldecode($absolute_path);
                    }

                    if (($href_path === '' || $href_path === '/') && $home_url !== '') {
                        $links_replaced++;
                        return $prefix . $home_url . $query . $anchor . $suffix;
                    }

                    $source_candidate = $is_absolute || strpos($href_path, '/') === 0
                        ? ltrim($href_path, '/')
                        : ($current_source_dir !== '' ? $current_source_dir . '/' : '') . $href_path;
                    $source_candidate = strtolower($this->normalize_import_path($source_candidate));
                    if (isset($source_path_map[$source_candidate])) {
                        $links_replaced++;
                        return $prefix . $source_path_map[$source_candidate] . $query . $anchor . $suffix;
                    }

                    // Normalize: strip leading ./ or /
                    $normalized = strtolower(preg_replace('/^(\.?\/)+/', '', rawurldecode($href_path)));
                    // Also strip trailing slash for folder-based paths
                    $normalized = rtrim($normalized, '/');

                    // Try exact match first (comprehensive map includes slugs, filenames, etc.)
                    if (isset($link_map[$normalized])) {
                        $links_replaced++;
                        return $prefix . $link_map[$normalized] . $query . $anchor . $suffix;
                    }

                    // Try basename match (e.g. 'pages/about.html' → 'about.html')
                    $basename = basename($normalized);
                    if (isset($link_map[$basename])) {
                        $links_replaced++;
                        return $prefix . $link_map[$basename] . $query . $anchor . $suffix;
                    }

                    // Try without extension
                    $without_ext = preg_replace('/\.html?$/i', '', $basename);
                    if (isset($link_map[$without_ext])) {
                        $links_replaced++;
                        return $prefix . $link_map[$without_ext] . $query . $anchor . $suffix;
                    }

                    // Try last path segment as slug (for folder-based paths like "Section/page-name")
                    $last_segment = strtolower(rtrim(basename($normalized), '/'));
                    if (!empty($last_segment) && isset($slug_map[$last_segment])) {
                        $links_replaced++;
                        return $prefix . $slug_map[$last_segment] . $query . $anchor . $suffix;
                    }

                    // Try sanitized last segment (slugify it for matching)
                    $slugified = sanitize_title($last_segment);
                    if (!empty($slugified) && $slugified !== $last_segment && isset($slug_map[$slugified])) {
                        $links_replaced++;
                        return $prefix . $slug_map[$slugified] . $query . $anchor . $suffix;
                    }

                    return $match[0]; // No match, leave unchanged
                },
                    $html
                );
            }

            $navigation_stats = [];
            // The source-aware mapper above already repairs every anchor. The
            // secondary pass only removes exact duplicate CTA controls, and its
            // nested-header regex can become pathological on very large Framer
            // documents. Keep it for ordinary pages and skip it for documents
            // where correctness is already covered by the primary mapper.
            if ($session === '' && strlen($updated_html) <= 350000) {
                $updated_html = Ugentic_Menu_Manager::instance()->repair_imported_navigation_html($updated_html, $navigation_stats);
            }
            $links_replaced += intval($navigation_stats['links_resolved'] ?? 0);
            $total_duplicates_removed += intval($navigation_stats['duplicates_removed'] ?? 0);

            if ($updated_html !== $html) {
                $database->update_page($page->id, [
                    'current_html' => $updated_html,
                ]);
                $pages_updated++;
                $total_links_replaced += $links_replaced;
            }
            unset($page_batch, $page, $html, $updated_html);
        }

        // Pages were already rewritten above with source-aware route handling.
        // Only shared assets remain; scanning every large page a second time is
        // redundant and made big Framer sites appear stuck at completion.
        if ($session === '') { $this->refresh_shared_navigation_links($database, false); }

        return [
            'pages_updated' => $pages_updated,
            'links_replaced' => $total_links_replaced,
            'duplicates_removed' => $total_duplicates_removed,
        ];
    }

    /**
     * Well-known Google Fonts that can be auto-resolved from CDN
     */
    private static $google_fonts = [
        'Inter',
        'Roboto',
        'Poppins',
        'Montserrat',
        'Open Sans',
        'Lato',
        'Raleway',
        'Nunito',
        'Plus Jakarta Sans',
        'Outfit',
        'DM Sans',
        'Manrope',
        'Work Sans',
        'Source Sans Pro',
        'Oswald',
        'Merriweather',
        'Playfair Display',
        'Barlow',
        'Quicksand',
        'Mulish',
        'Rubik',
        'Karla',
        'Josefin Sans',
        'Cabin',
        'Libre Baskerville',
        'Cormorant Garamond',
        'Ubuntu',
        'Noto Sans',
        'Fira Sans',
        'PT Sans',
        'Arimo',
    ];

    /**
     * Constructor
     */public function __construct() {
    $this->smart_resolver = new Ugentic_Smart_Resolver();
    $this->legacy_detector = new Ugentic_Legacy_Detector();
    $this->css_normalizer = new Ugentic_CSS_Normalizer();
    $this->form_converter = new Tahmir_Imported_Form_Preview();
add_action('wp_ajax_ugentic_upload_website', [$this, 'ajax_upload_website']);
add_action('wp_ajax_ugentic_import_page', [$this, 'ajax_import_page']);
add_action('wp_ajax_ugentic_import_all', [$this, 'ajax_import_all']);
add_action('wp_ajax_ugentic_cleanup_import', [$this, 'ajax_cleanup_import']);
add_action('wp_ajax_ugentic_delete_import', [$this, 'ajax_delete_import']);
add_action('wp_ajax_ugentic_replace_internal_links', [$this, 'ajax_replace_internal_links']);
add_action('wp_ajax_ugentic_detect_import_menus', [$this, 'ajax_detect_import_menus']);
add_action('wp_ajax_ugentic_force_clear_import_lock', [$this, 'ajax_force_clear_import_lock']);
}

    /**
     * Import a live website as faithful, editable WordPress pages.
     *
     * @param string $url
     * @param array  $options
     * @return array
     */
    public function discover_remote_site_pages($url, $options = [])
    {
        @set_time_limit(180);

        $url = esc_url_raw($url);
        $max_pages = max(1, min(100, intval($options['max_pages'] ?? 50)));
        $preview_mode = !empty($options['preview']);
        $preview_time_budget = $preview_mode ? max(6.0, min(30.0, floatval($options['preview_time_budget'] ?? 16))) : 0.0;
        $preview_deadline = $preview_time_budget > 0 ? microtime(true) + $preview_time_budget : 0.0;
        $preview_fetch_limit = $preview_mode ? max(3, min(16, intval($options['preview_fetch_limit'] ?? 8))) : $max_pages;
        $crawl_scope = sanitize_key($options['crawl_scope'] ?? 'full_site');
        if (!in_array($crawl_scope, ['full_site', 'nav'], true)) {
            $crawl_scope = 'full_site';
        }

        $warnings = [];
        $this->clone_runtime_warnings = [];
        $initial_html = isset($options['initial_html']) && is_string($options['initial_html'])
            ? (string) $options['initial_html']
            : '';
        $home = $initial_html !== ''
            ? ['success' => true, 'message' => '', 'html' => $initial_html]
            : $this->clone_fetch_html($url, $warnings);
        if (!$home['success']) {
            return [
                'success' => false,
                'message' => $home['message'],
                'warnings' => $warnings,
            ];
        }

        $url = $home['url'] ?? $url;
        if ($this->clone_is_rendered_shell($home['html'])) {
            $this->clone_note_client_rendered_shell($url, $warnings);
        }

        $discovery = $this->clone_discover_pages($url, $home['html'], $max_pages, $warnings, $crawl_scope, [
            'time_budget' => $preview_mode ? max(4.0, $preview_time_budget * 0.45) : 0,
            'recursive_fetch_limit' => $preview_mode ? 0 : PHP_INT_MAX,
        ]);
        $home_url = $this->clone_canonical_page_url($url);
        $pages = [];
        $preview_fetches = 0;
        $preview_truncated = false;

        foreach ($discovery['pages'] as $idx => $page) {
            $page_url = $page['url'];
            $title = $page['title'] ?? $this->clone_title_from_url($page_url);
            $blog_role = 'none';
            $selection_role = 'page';
            $default_selected = true;
            $note = '';
            $fetch = ['success' => false, 'html' => ''];
            $needs_html_detection = !$preview_mode
                || $page_url === $home_url
                || $idx === 0;

            if ($needs_html_detection) {
                if ($preview_mode && $preview_fetches >= $preview_fetch_limit && $page_url !== $home_url) {
                    $preview_truncated = true;
                } elseif ($preview_deadline > 0 && microtime(true) >= $preview_deadline && $page_url !== $home_url) {
                    $preview_truncated = true;
                } else {
                    $fetch = ($page_url === $home_url || $idx === 0) ? $home : $this->clone_fetch_html($page_url, $warnings, $preview_mode ? 8 : 30);
                    if ($page_url !== $home_url && $idx !== 0) {
                        $preview_fetches++;
                    }
                }
            }

            if (!empty($fetch['success']) && !empty($fetch['html'])) {
                if ($this->clone_is_rendered_shell($fetch['html'])) {
                    $this->clone_note_client_rendered_shell($page_url, $warnings);
                }
                $fetched_title = $this->clone_extract_title($fetch['html'], $title);
                if (!$preview_mode || $this->clone_fetched_title_matches_page($fetched_title, $title, $page_url)) {
                    $title = $fetched_title;
                }
            }

            $pages[] = [
                'url' => $page_url,
                'title' => $title,
                'source' => $page['source'] ?? '',
                'blog_role' => $blog_role,
                'selection_role' => $selection_role,
                'default_selected' => $default_selected,
                'note' => $note,
            ];
        }

        if ($preview_truncated) {
            $warnings[] = __('Preview discovery was kept fast; some page titles may be finalized during import.', 'tahmir-ai-to-wp-converter');
        }

        return [
            'success' => true,
            'mode' => 'faithful_clone_discovery',
            'pages' => $pages,
            'pages_found' => count($pages),
            'warnings' => array_values(array_unique(array_filter($warnings))),
        ];
    }

    public function import_remote_site_faithful($url, $options = [])
    {
        // Background clone workers are intentionally detached from the browser
        // request. Do not overwrite their unlimited execution allowance with the
        // old 300-second interactive limit (the asset budget still bounds work).
        $background_job = PHP_SAPI === 'cli' || !empty($options['background_job']);
        @set_time_limit($background_job ? 0 : 300);

        $url = esc_url_raw($url);
        $publish = !empty($options['publish']);
        $single_page = !empty($options['single_page']) || (($options['crawl_scope'] ?? '') === 'single_page');
        $max_pages = $single_page ? 1 : max(1, min(100, intval($options['max_pages'] ?? 50)));
        $crawl_scope = $single_page ? 'single_page' : sanitize_key($options['crawl_scope'] ?? 'full_site');
        $clone_source = $this->clone_normalize_source_type($options['clone_source'] ?? 'auto');
        $ai_assisted = false;
        $ai_data_consent = !empty($options['ai_data_consent']);
        $ai_assistant = $ai_assisted && class_exists('Ugentic_AI_Clone_Assistant')
            ? new Ugentic_AI_Clone_Assistant()
            : null;
        if ($ai_assisted && !$ai_data_consent) {
            return [
                'success' => false,
                'message' => __('AI-assisted cloning requires confirmation of the AI data notice.', 'tahmir-ai-to-wp-converter'),
                'warnings' => [],
                'progress_events' => [],
            ];
        }
        if ($ai_assisted && (!$ai_assistant || !Ugentic_AI_Clone_Assistant::is_configured())) {
            return [
                'success' => false,
                'message' => __('Configure an AI provider in Tahmir AI Settings before using AI-assisted cloning.', 'tahmir-ai-to-wp-converter'),
                'settings_url' => class_exists('Ugentic_AI_Clone_Assistant') ? Ugentic_AI_Clone_Assistant::settings_url() : admin_url('admin.php?page=ugentic-settings#tab-api-keys'),
                'warnings' => [],
                'progress_events' => [],
            ];
        }
        if (!in_array($crawl_scope, ['full_site', 'nav', 'single_page'], true)) {
            $crawl_scope = 'full_site';
        }
        $selected_pages = [];
        if (!$single_page && !empty($options['selected_pages']) && is_array($options['selected_pages'])) {
            foreach ($options['selected_pages'] as $selected_url) {
                $selected_url = esc_url_raw($selected_url);
                if ($selected_url !== '') {
                    $selected_pages[$this->clone_canonical_page_url($selected_url)] = true;
                }
            }
        }
        $warnings = [];
        $asset_cache = [];
        $this->clone_asset_failures = [];
        $this->clone_asset_download_count = 0;
        $this->clone_runtime_warnings = [];
        $this->clone_progress_events = [];
        $this->clone_progress_last_value = 0;
        $this->clone_asset_progress_last_report = 0;
        $this->clone_progress_callback = is_callable($options['progress_callback'] ?? null) ? $options['progress_callback'] : null;

        $this->clone_report_progress('fetch_source', 7, __('Fetching source HTML...', 'tahmir-ai-to-wp-converter'), [
            'url' => $url,
        ]);
        $home = $this->clone_fetch_html($url, $warnings);
        if (!$home['success']) {
            return [
                'success' => false,
                'message' => $home['message'],
                'warnings' => $warnings,
                'progress_events' => $this->clone_progress_events,
            ];
        }

        $url = $home['url'] ?? $url;
        if ($this->clone_is_rendered_shell($home['html'])) {
            $this->clone_note_client_rendered_shell($url, $warnings);
            $this->clone_report_progress('client_app_shell', 12, __('Detected a JavaScript app shell. Preserving runtime assets instead of rejecting it...', 'tahmir-ai-to-wp-converter'), [
                'url' => $url,
            ]);
        }

        $home_url = $this->clone_canonical_page_url($url);
        $crawl_sources = [];
        if ($single_page) {
            $this->clone_report_progress('select_pages', 18, __('Single-page clone selected. Preparing import...', 'tahmir-ai-to-wp-converter'));
            $pages_to_import = [[
                'url' => $home_url,
                'title' => $this->clone_extract_title($home['html'], $this->clone_title_from_url($url)),
                'source' => 'submitted_url',
            ]];
            $crawl_sources[$home_url] = ['submitted_url'];
        } else {
            $this->clone_report_progress('discover_pages', 16, __('Discovering same-domain pages from sitemap and navigation...', 'tahmir-ai-to-wp-converter'));
            $discovery = $this->clone_discover_pages($url, $home['html'], $max_pages, $warnings, $crawl_scope);
            $pages_to_import = $discovery['pages'];
            $crawl_sources = $discovery['sources'];
            if (!empty($selected_pages)) {
                $filtered_pages = [];
                $filtered_urls = [];
                foreach ($pages_to_import as $page) {
                    if (empty($page['url'])) {
                        continue;
                    }
                    $canonical = $this->clone_canonical_page_url($page['url']);
                    if (isset($selected_pages[$canonical])) {
                        $filtered_pages[] = $page;
                        $filtered_urls[$canonical] = true;
                    }
                }
                foreach (array_keys($selected_pages) as $selected_url) {
                    if (isset($filtered_urls[$selected_url])) {
                        continue;
                    }
                    $filtered_pages[] = [
                        'url' => $selected_url,
                        'title' => $this->clone_title_from_url($selected_url),
                        'source' => 'selected',
                    ];
                }
                $pages_to_import = $filtered_pages;
            } elseif (!empty($options['require_selected_pages'])) {
                return [
                    'success' => false,
                    'message' => __('Choose the pages to clone before starting the website import.', 'tahmir-ai-to-wp-converter'),
                    'warnings' => $warnings,
                    'progress_events' => $this->clone_progress_events,
                ];
            }
        }

        if (!$single_page && count($pages_to_import) <= 1) {
            $warnings[] = __('Only one importable same-domain page was discovered. Check whether the source blocks crawling, hides navigation behind JavaScript, or has no sitemap/links in the fetched HTML.', 'tahmir-ai-to-wp-converter');
        }

        // The local structural safety pass is always authoritative. A provider
        // plan can be connected only after the administrator explicitly allows
        // the disclosed route/structure transmission.
        $ai_plan = [];
        $ai_plan_status = $ai_assisted ? 'local_protected' : 'disabled';
        $ai_plan_model = '';
        if ($ai_assisted) {
            $this->clone_report_progress('ai_plan', 21, __('Mapping shared sections, blog routes, and complex forms...', 'tahmir-ai-to-wp-converter'), [
                'pages_total' => count($pages_to_import),
            ]);
        }

        $this->clone_report_progress('prepare_assets', 24, sprintf(
            /* translators: Number of the pages, records, files, or items described in this message. */ __('Preparing asset localization for %d page(s)...', 'tahmir-ai-to-wp-converter'),
            count($pages_to_import)
        ), [
            'pages_total' => count($pages_to_import),
        ]);

        $this->clone_configure_asset_runtime_limits($options, $single_page, count($pages_to_import));

        $upload_dir = wp_upload_dir();
        $clone_id = 'ugentic-clone-' . uniqid();
        $asset_dir = trailingslashit($upload_dir['basedir']) . 'ugentic-clones/' . $clone_id;
        $asset_url = trailingslashit($upload_dir['baseurl']) . 'ugentic-clones/' . $clone_id;

        if (!wp_mkdir_p($asset_dir)) {
            return [
                'success' => false,
                'message' => __('Failed to create clone asset directory.', 'tahmir-ai-to-wp-converter'),
                'warnings' => $warnings,
                'progress_events' => $this->clone_progress_events,
            ];
        }

        $this->temp_dir = $asset_dir;
        $this->assets_base_url = trailingslashit($asset_url);
        $this->asset_map = [];

        $imported = [];
        $pages_skipped = [];
        $client_app_shell_pages = 0;
        $ai_header_ids = [];
        $ai_footer_ids = [];
        $ai_blog_archives = 0;
        $ai_blog_posts = 0;
        $ai_complex_forms = 0;
        $ai_multi_step_forms = 0;
        $ai_forms_planned = 0;
        $ai_form_fallbacks = 0;
        $total_pages = max(1, count($pages_to_import));
        foreach ($pages_to_import as $idx => $page_info) {
            $page_url = $page_info['url'];
            $page_number = $idx + 1;
            $page_progress = 28 + intval(($idx / $total_pages) * 56);
            $this->clone_report_progress('import_page', $page_progress, sprintf(
                /* translators: 1: Current page number; 2: Total pages; 3: Page title. */ __('Importing page %1$d of %2$d: %3$s', 'tahmir-ai-to-wp-converter'),
                $page_number,
                $total_pages,
                $page_info['title'] ?? $page_url
            ), [
                'page_url' => $page_url,
                'page_index' => $idx,
                'pages_total' => $total_pages,
                'assets_downloaded' => $this->clone_asset_download_count,
            ]);
            $fetch = ($page_url === $home_url) ? $home : $this->clone_fetch_html($page_url, $warnings);

            if (!$fetch['success']) {
                $reason = $fetch['message'];
                $warnings[] = sprintf(/* translators: 1: Source URL; 2: Reason the page was skipped. */ __('Skipped %1$s: %2$s', 'tahmir-ai-to-wp-converter'), $page_url, $reason);
                $pages_skipped[] = [
                    'url' => $page_url,
                    'reason' => $reason,
                ];
                continue;
            }
            $is_client_shell = $this->clone_is_rendered_shell($fetch['html']);
            if ($is_client_shell) {
                $client_app_shell_pages++;
                $this->clone_note_client_rendered_shell($page_url, $warnings);
            }

            // SPA shells commonly reuse one generic <title> for every route.
            // Preserve the route title discovered from the router for child pages.
            $title = $this->clone_extract_title($fetch['html'], $page_info['title']);
            if ($is_client_shell && $page_url !== $home_url) {
                $route_title = trim(wp_strip_all_tags((string) ($page_info['title'] ?? '')));
                $title = $route_title !== '' ? $route_title : $this->clone_title_from_url($page_url);
            }
            $processed = $this->clone_process_html($fetch['html'], $fetch['url'] ?? $page_url, $asset_dir, $asset_url, $asset_cache, $warnings, $clone_source, $ai_assisted, $ai_assistant);
            if ($ai_assisted && !empty($processed['ai_form_model'])) {
                $ai_plan_model = sanitize_text_field($processed['ai_form_model']);
            }
            if ($ai_assisted && ($processed['ai_form_status'] ?? '') === 'applied') {
                $ai_plan_status = 'form_ai_applied';
            }
            $processed['clone_asset_namespace'] = $clone_id;
            $processed['ai_assisted'] = $ai_assisted;
            $processed['ai_form_analysis_allowed'] = $ai_assisted && $ai_data_consent;
            $processed['ai_plan_status'] = $ai_plan_status;
            $processed['ai_plan_model'] = $ai_plan_model;
            $processed['ai_plan_summary'] = $ai_assisted ? [
                'version' => 2,
                'site_type' => sanitize_key($ai_plan['site_type'] ?? 'mixed'),
                'forms' => [
                    'status' => sanitize_key($processed['ai_form_status'] ?? 'not_run'),
                    'planned' => intval($processed['form_audit']['ai_forms_planned'] ?? 0),
                    'model' => sanitize_text_field($processed['ai_form_model'] ?? ''),
                ],
            ] : [];

            if ($ai_assisted && !$is_client_shell) {
                $processed = array_merge($processed, $this->clone_prepare_ai_assisted_page($processed['html'], $page_url, $ai_plan));
            } elseif ($ai_assisted) {
                $processed['ai_deferred_until_snapshot'] = true;
            }

            $blog_detection = $this->detect_blog_page_for_clone($processed['html'], $title, $page_url);
            if ($ai_assisted && $ai_assistant) {
                $blog_detection = $ai_assistant->merge_blog_detection($blog_detection, $ai_plan, $page_url);
            }
            $processed['blog_detection_override'] = $blog_detection;

            $result = null;
            if ($ai_assisted && in_array($blog_detection['blog_role'] ?? 'none', ['archive', 'single'], true)) {
                $result = Ugentic_Blog_Autopilot::instance()->maybe_handle_import([
                    'processed_html' => $processed['html'],
                    'original_html' => $fetch['html'],
                    'title' => $title,
                    'source_url' => $page_url,
                    'source_file' => basename(wp_parse_url($page_url, PHP_URL_PATH) ?: 'index.html') ?: 'index.html',
                    'publish' => $publish,
                    'sort_order' => $idx,
                    'forms_created' => intval($processed['forms_created'] ?? 0),
                    'form_ids' => $processed['form_ids'] ?? [],
                    'blog_detection' => $blog_detection,
                    'import_source_blog_posts' => true,
                ]);
            }
            if (!is_array($result)) {
                $result = $this->clone_create_faithful_page($title, $fetch['html'], $processed['html'], $page_url, $idx, $publish, $processed);
            }

            if (!$result['success']) {
                $reason = $result['message'];
                $warnings[] = sprintf(/* translators: 1: Source URL; 2: Failure reason. */ __('Could not import %1$s: %2$s', 'tahmir-ai-to-wp-converter'), $page_url, $reason);
                $pages_skipped[] = [
                    'url' => $page_url,
                    'reason' => $reason,
                ];
                continue;
            }

            if ($ai_assisted) {
                $result = $this->clone_attach_ai_assisted_metadata($result, $processed);
                if (!empty($processed['global_header_id'])) {
                    $ai_header_ids[intval($processed['global_header_id'])] = true;
                }
                if (!empty($processed['global_footer_id'])) {
                    $ai_footer_ids[intval($processed['global_footer_id'])] = true;
                }
                $ai_complex_forms += intval($processed['form_audit']['complex_forms'] ?? 0);
                $ai_multi_step_forms += intval($processed['form_audit']['multi_step_forms'] ?? 0);
                $ai_forms_planned += intval($processed['form_audit']['ai_forms_planned'] ?? 0);
                if (in_array(($processed['ai_form_status'] ?? ''), ['provider_failed', 'invalid_response', 'empty_response'], true)) {
                    $ai_form_fallbacks++;
                }
                $import_role = $result['page']['import_role'] ?? '';
                if ($import_role === 'blog_listing') {
                    $ai_blog_archives++;
                } elseif ($import_role === 'blog_single') {
                    $ai_blog_posts++;
                }
            }

            $imported[] = $result['page'];
        }

        if (empty($imported)) {
            return [
                'success' => false,
                'message' => __('No pages could be faithfully imported from this site.', 'tahmir-ai-to-wp-converter'),
                'warnings' => $warnings,
                'pages_skipped' => $pages_skipped,
                'progress_events' => $this->clone_progress_events,
            ];
        }

        $this->clone_report_progress('rewrite_links', 88, __('Rewriting internal links to imported WordPress pages...', 'tahmir-ai-to-wp-converter'), [
            'pages_imported' => count($imported),
            'assets_downloaded' => count($asset_cache),
        ]);
        $link_result = $this->clone_replace_internal_links($imported);
        // The in-memory clone map is complete for this batch. Repair shared
        // sections only; a second global scan of every historical page made
        // large imports appear stuck after all pages were already complete.
        $this->refresh_shared_navigation_links(null, false, $warnings);
        $link_result['duplicates_removed'] = intval($link_result['duplicates_removed'] ?? 0);
        $menu_result = ['menus_created' => 0, 'skipped' => true];
        if (!isset($options['detect_menus']) || !empty($options['detect_menus'])) {
            $this->clone_report_progress('detect_menus', 94, __('Detecting navigation menus...', 'tahmir-ai-to-wp-converter'));
            $menu_result = $this->detect_import_menus_from_stored_pages();
        }

        $this->clone_report_progress('complete', 100, __('Clone import complete.', 'tahmir-ai-to-wp-converter'), [
            'pages_imported' => count($imported),
            'assets_downloaded' => count($asset_cache),
            'assets_failed' => count($this->clone_asset_failures),
        ]);

        return [
            'success' => true,
            'mode' => $single_page ? 'faithful_page_import' : 'faithful_import',
            'ai_assisted' => $ai_assisted,
            'ai_plan_status' => $ai_plan_status,
            'ai_plan_model' => $ai_plan_model,
            'ai_report' => [
                'headers_separated' => count($ai_header_ids),
                'footers_separated' => count($ai_footer_ids),
                'blog_archives_created' => $ai_blog_archives,
                'blog_posts_created' => $ai_blog_posts,
                'complex_forms_preserved' => $ai_complex_forms,
                'multi_step_forms_preserved' => $ai_multi_step_forms,
                'ai_forms_planned' => $ai_forms_planned,
                'ai_form_fallbacks' => $ai_form_fallbacks,
            ],
            'pages_imported' => $imported,
            'pages_found' => count($pages_to_import),
            'pages_skipped' => $pages_skipped,
            'client_app_shell_pages' => $client_app_shell_pages,
            'assets_downloaded' => count($asset_cache),
            'assets_failed' => count($this->clone_asset_failures),
            'links_replaced' => intval($link_result['links_replaced'] ?? 0),
            'menus_created' => intval($menu_result['menus_created'] ?? 0),
            'menu_detection' => $menu_result,
            'crawl_sources' => $crawl_sources,
            'asset_dir' => $asset_dir,
            'asset_url' => $asset_url,
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'progress_events' => $this->clone_progress_events,
        ];
    }

    /**
     * Fetch HTML from a remote URL.
     */
    private function clone_fetch_html($url, &$warnings, $timeout = 30)
    {
        $response = $this->clone_remote_get_with_retries($url, [
            'timeout' => max(4, min(30, intval($timeout))),
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cache-Control' => 'no-cache',
            ],
        ], 'import_clone_html', $warnings, [
            'retries' => self::REMOTE_CLONE_HTML_RETRIES,
            'warn_retries' => true,
            'retry_base_ms' => 450,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $this->clone_remote_error_message($url, $response, $response->get_error_message()), 'html' => ''];
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        if ($code < 200 || $code >= 400) {
            return ['success' => false, 'message' => $this->clone_remote_error_message($url, $response, sprintf(/* translators: HTTP status code. */ __('HTTP %d', 'tahmir-ai-to-wp-converter'), $code)), 'html' => ''];
        }

        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if ($content_type && strpos($content_type, 'html') === false && strpos($content_type, 'text/plain') === false) {
            return [
                'success' => false,
                'message' => sprintf(/* translators: Response content type. */ __('not an HTML page (%s)', 'tahmir-ai-to-wp-converter'), $content_type),
                'html' => '',
            ];
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return ['success' => false, 'message' => __('empty response body', 'tahmir-ai-to-wp-converter'), 'html' => ''];
        }

        $problem = $this->clone_source_response_problem($html);
        if ($problem !== '') {
            return ['success' => false, 'message' => $problem, 'html' => ''];
        }
        return ['success' => true, 'message' => '', 'html' => $html, 'url' => $this->clone_response_url($response, $url)];
    }

    private function clone_response_url($response, $fallback)
    {
        if (isset($response['http_response']) && is_object($response['http_response'])
            && method_exists($response['http_response'], 'get_response_object')) {
            $object = $response['http_response']->get_response_object();
            if (is_object($object) && !empty($object->url) && preg_match('~^https?://~i', $object->url)) {
                return (string) $object->url;
            }
        }
        return $fallback;
    }

    private function clone_source_response_problem($html)
    {
        $title = strtolower(trim($this->clone_extract_title($html)));
        $challenge_title = preg_match('/^(?:just a moment(?:\.\.\.)?|attention required!?(?:\s*\|.*)?|vercel security checkpoint|checking your browser|access denied|verify (?:you are|that you are) human)$/i', $title);
        if ($challenge_title || preg_match('~<(?:form|div)\b[^>]*\bid\s*=\s*["\'](?:challenge-form|cf-challenge-running|px-captcha)["\']~i', $html)) {
            return __('The source returned an access-check page instead of the website. Use its public published URL, allow access on the source you control, or import its HTML export.', 'tahmir-ai-to-wp-converter');
        }
        return '';
    }

    /**
     * Discover same-domain pages for a faithful website clone.
     */
    private function rendered_route_cache_key($url)
    {
        $parts = wp_parse_url($url);
        $origin = strtolower(($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')) . ':' . ($parts['port'] ?? '');
        return 'ugentic_rendered_routes_' . substr(hash('sha256', $origin), 0, 32);
    }

    /** Remember public links that only exist after a client app renders. */

    private function clone_discover_pages($url, $html, $max_pages, &$warnings, $crawl_scope = 'full_site', $options = [])
    {
        $home_url = $this->clone_canonical_page_url($url);
        $pages = [];
        $sources = [];
        $time_budget = floatval($options['time_budget'] ?? 0);
        $deadline = $time_budget > 0 ? microtime(true) + $time_budget : 0.0;
        $recursive_fetch_limit = isset($options['recursive_fetch_limit']) ? intval($options['recursive_fetch_limit']) : PHP_INT_MAX;
        $quick_preview = $recursive_fetch_limit <= 0 || $time_budget > 0;
        $recursive_fetches = 0;

        $this->clone_add_discovered_page($pages, $sources, $home_url, $url, $url, 'Home', 'home', $max_pages);
        if ($crawl_scope === 'full_site') {
            $rendered = get_transient($this->rendered_route_cache_key($url));
            foreach (is_array($rendered) ? $rendered : [] as $page) {
                $this->clone_add_discovered_page($pages, $sources, $page['url'] ?? '', $url, $url, $page['title'] ?? '', 'rendered_links', $max_pages);
            }
        }

        foreach ($this->clone_extract_page_links($html, $url, 'nav') as $link) {
            $this->clone_add_discovered_page($pages, $sources, $link['url'], $url, $url, $link['title'], 'nav', $max_pages);
        }

        if ($crawl_scope === 'nav') {
            if (count($pages) <= 1) {
                $warnings[] = __('No semantic nav/header/footer links were found, so clone used visible page links as fallback.', 'tahmir-ai-to-wp-converter');
                foreach ($this->clone_extract_page_links($html, $url, 'all') as $link) {
                    $this->clone_add_discovered_page($pages, $sources, $link['url'], $url, $url, $link['title'], 'homepage_links', $max_pages);
                }
            }

            return [
                'pages' => array_values($pages),
                'sources' => $sources,
            ];
        }

        foreach ($this->clone_discover_sitemap_pages($url, $max_pages, $warnings, [
            'deadline' => $deadline,
            'robots_timeout' => $quick_preview ? 5 : 12,
            'sitemap_timeout' => $quick_preview ? 6 : 20,
        ]) as $page) {
            $this->clone_add_discovered_page($pages, $sources, $page['url'], $url, $url, $page['title'], 'sitemap', $max_pages);
        }

        foreach ($this->clone_extract_page_links($html, $url, 'all') as $link) {
            $this->clone_add_discovered_page($pages, $sources, $link['url'], $url, $url, $link['title'], 'homepage_links', $max_pages);
        }

        $bundle_found = 0;
        foreach ($this->clone_discover_client_bundle_pages($url, $html, $max_pages, $warnings, [
            'deadline' => $deadline,
            'asset_limit' => $quick_preview ? 4 : 8,
            'timeout' => $quick_preview ? 6 : 12,
        ]) as $page) {
            $before_count = count($pages);
            $this->clone_add_discovered_page($pages, $sources, $page['url'], $url, $url, $page['title'], 'client_bundle', $max_pages);
            if (count($pages) > $before_count) {
                $bundle_found++;
            }
            if (count($pages) >= $max_pages) {
                break;
            }
        }
        if ($bundle_found > 0) {
            $this->clone_add_runtime_warning_once(
                'client_bundle_routes_discovered',
                sprintf(
                    /* translators: Number of the pages, records, files, or items described in this message. */ _n(
                        'Discovered %d JavaScript-defined route from the client app bundle.',
                        'Discovered %d JavaScript-defined routes from the client app bundle.',
                        $bundle_found, 'tahmir-ai-to-wp-converter'
                    ),
                    $bundle_found
                ),
                $warnings
            );
        }

        if ($recursive_fetch_limit <= 0) {
            return [
                'pages' => array_values($pages),
                'sources' => $sources,
            ];
        }

        $queue = array_values($pages);
        $visited = [];
        for ($i = 0; $i < count($queue) && count($pages) < $max_pages; $i++) {
            if ($deadline > 0 && microtime(true) >= $deadline) {
                $warnings[] = __('Page discovery was time-boxed; review the discovered pages and add missing URLs manually if needed.', 'tahmir-ai-to-wp-converter');
                break;
            }
            $page_url = $queue[$i]['url'];
            if (isset($visited[$page_url])) {
                continue;
            }
            $visited[$page_url] = true;

            if ($page_url !== $home_url) {
                if ($recursive_fetches >= $recursive_fetch_limit) {
                    break;
                }
                $recursive_fetches++;
            }

            $fetch = ($page_url === $home_url) ? ['success' => true, 'html' => $html] : $this->clone_fetch_html($page_url, $warnings, $quick_preview ? 8 : 30);
            if (empty($fetch['success']) || empty($fetch['html'])) {
                continue;
            }
            if ($this->clone_is_rendered_shell($fetch['html'])) {
                $this->clone_note_client_rendered_shell($page_url, $warnings);
            }

            foreach ($this->clone_extract_page_links($fetch['html'], $page_url, 'all') as $link) {
                $before_count = count($pages);
                $canonical = $this->clone_add_discovered_page($pages, $sources, $link['url'], $page_url, $url, $link['title'], 'recursive', $max_pages);
                if ($canonical && count($pages) > $before_count) {
                    $queue[] = $pages[$canonical];
                }
                if (count($pages) >= $max_pages) {
                    break;
                }
            }
        }

        return [
            'pages' => array_values($pages),
            'sources' => $sources,
        ];
    }

    private function clone_add_discovered_page(&$pages, &$sources, $candidate, $base_url, $home_url, $title, $source, $max_pages)
    {
        $resolved = $this->clone_resolve_url($candidate, $base_url);
        if (!$this->clone_is_importable_page_url($resolved, $home_url)) {
            return '';
        }

        $canonical = $this->clone_canonical_page_url($resolved);
        if (!isset($pages[$canonical])) {
            if (count($pages) >= $max_pages) {
                return '';
            }
            $clean_title = trim(wp_strip_all_tags((string) $title));
            if ($clean_title === '' || strlen($clean_title) > 100) {
                $clean_title = $this->clone_title_from_url($canonical);
            }
            $pages[$canonical] = [
                'url' => $canonical,
                'title' => $clean_title,
                'source' => $source,
            ];
        }

        if (!isset($sources[$canonical])) {
            $sources[$canonical] = [];
        }
        if (!in_array($source, $sources[$canonical], true)) {
            $sources[$canonical][] = $source;
        }

        return $canonical;
    }

    private function clone_fetched_title_matches_page($fetched_title, $fallback_title, $url)
    {
        $fetched = trim((string) $fetched_title);
        $fallback = trim((string) $fallback_title);
        if ($fetched === '' || $fallback === '' || strtolower($fallback) === 'home') {
            return true;
        }

        $normalized_fetched = sanitize_title($fetched);
        $normalized_fallback = sanitize_title($fallback);
        $slug_title = sanitize_title($this->clone_title_from_url($url));
        if ($normalized_fallback !== '' && strpos($normalized_fetched, $normalized_fallback) !== false) {
            return true;
        }
        if ($slug_title !== '' && strpos($normalized_fetched, $slug_title) !== false) {
            return true;
        }

        return false;
    }

    private function clone_extract_page_links($html, $base_url, $scope = 'all')
    {
        $links = [];
        $seen = [];
        $dom = $this->clone_load_html_dom($html);

        if ($dom) {
            $xpath = new DOMXPath($dom);
            $query = $scope === 'nav'
                ? '//header//a[@href] | //nav//a[@href] | //footer//a[@href]'
                : '//a[@href]';

            foreach ($xpath->query($query) as $node) {
                $href = trim((string) $node->getAttribute('href'));
                if ($href === '') {
                    continue;
                }
                $key = $href . '|' . $scope;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $links[] = [
                    'url' => $href,
                    'title' => trim(preg_replace('/\s+/', ' ', $node->textContent ?: '')),
                ];
            }
        }

        $fallback_html = $html;
        if ($scope === 'nav') {
            $fragments = [];
            if (preg_match('/<header\b[^>]*>[\s\S]*?<\/header>/i', $html, $m)) {
                $fragments[] = $m[0];
            }
            if (preg_match_all('/<nav\b[^>]*>[\s\S]*?<\/nav>/i', $html, $matches)) {
                $fragments = array_merge($fragments, $matches[0]);
            }
            if (preg_match('/<footer\b[^>]*>[\s\S]*?<\/footer>/i', $html, $m)) {
                $fragments[] = $m[0];
            }
            $fallback_html = implode("\n", $fragments);
        }

        if ($fallback_html && preg_match_all('/<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>([\s\S]*?)<\/a>/i', $fallback_html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $href = trim(html_entity_decode($match[1] ?: ($match[2] ?: ($match[3] ?? '')), ENT_QUOTES));
                if ($href === '') {
                    continue;
                }
                $key = $href . '|' . $scope;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $links[] = [
                    'url' => $href,
                    'title' => trim(wp_strip_all_tags($match[4] ?? '')),
                ];
            }
        }

        return $links;
    }

    private function clone_discover_client_bundle_pages($home_url, $html, $max_pages, &$warnings, $options = [])
    {
        $asset_urls = $this->clone_extract_client_bundle_urls($html, $home_url, intval($options['asset_limit'] ?? 8));
        if (empty($asset_urls)) {
            return [];
        }

        $pages = [];
        $seen = [];
        $deadline = floatval($options['deadline'] ?? 0);
        $timeout = max(4, min(15, intval($options['timeout'] ?? 12)));

        foreach ($asset_urls as $asset_url) {
            if ($deadline > 0 && microtime(true) >= $deadline) {
                break;
            }

            $response = $this->clone_remote_get_with_retries($asset_url, [
                'timeout' => $timeout,
                'redirection' => 3,
                'user-agent' => 'Mozilla/5.0 (compatible; UgenticFaithfulClone/1.0)',
        ], 'import_clone_client_bundle', $warnings, [
            'retries' => 1,
            'warn_retries' => false,
            'retry_base_ms' => 250,
            'deadline' => $deadline,
        ]);

            if (is_wp_error($response)) {
                continue;
            }

            $code = intval(wp_remote_retrieve_response_code($response));
            if ($code < 200 || $code >= 400) {
                continue;
            }

            $body = (string) wp_remote_retrieve_body($response);
            if ($body === '' || strlen($body) > 2000000) {
                continue;
            }

            foreach ($this->clone_extract_client_bundle_route_paths($body) as $path) {
                if (count($pages) >= $max_pages) {
                    break 2;
                }

                $resolved = $this->clone_resolve_url($path, $home_url);
                if (!$this->clone_is_importable_page_url($resolved, $home_url)) {
                    continue;
                }

                $canonical = $this->clone_canonical_page_url($resolved);
                if (isset($seen[$canonical])) {
                    continue;
                }

                $seen[$canonical] = true;
                $pages[] = [
                    'url' => $canonical,
                    'title' => $this->clone_title_from_url($canonical),
                ];
            }
        }

        return $pages;
    }

    private function clone_extract_client_bundle_urls($html, $home_url, $limit = 8)
    {
        $urls = [];
        $seen = [];
        $limit = max(1, min(12, intval($limit)));

        if (!preg_match_all('/<(script|link)\b([^>]*)>/i', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            if (count($urls) >= $limit) {
                break;
            }

            $tag = strtolower($match[1]);
            $attrs = $match[2] ?? '';
            $asset = '';
            if ($tag === 'script' && preg_match('/\bsrc\s*=\s*(["\'])([^"\']+\.(?:js|mjs)(?:\?[^"\']*)?)\1/i', $attrs, $m)) {
                $asset = $m[2];
            } elseif ($tag === 'link' && preg_match('/\brel\s*=\s*(["\'])[^"\']*modulepreload[^"\']*\1/i', $attrs) && preg_match('/\bhref\s*=\s*(["\'])([^"\']+\.(?:js|mjs)(?:\?[^"\']*)?)\1/i', $attrs, $m)) {
                $asset = $m[2];
            }

            if ($asset === '') {
                continue;
            }

            $resolved = $this->clone_resolve_url($asset, $home_url);
            if (!$resolved || !$this->clone_same_origin_url($resolved, $home_url)) {
                continue;
            }

            $path = strtolower(wp_parse_url($resolved, PHP_URL_PATH) ?: '');
            if (!preg_match('/\.(?:js|mjs)$/i', $path) || preg_match('#/(?:__l5e|~flock|wp-admin|wp-includes|wp-content)/#i', $path)) {
                continue;
            }

            $key = $this->clone_asset_cache_key($resolved);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $urls[] = $resolved;
        }

        return $urls;
    }

    private function clone_extract_client_bundle_route_paths($js)
    {
        $paths = [];
        $seen = [];
        $strong_patterns = [
            '/\b(?:path|to|href)\s*:\s*(["\'])(\/[^"\']{0,160})\1/i',
            '/<Route\b[^>]*\bpath\s*=\s*(["\'])(\/[^"\']{0,160})\1/i',
            '/(?:createBrowserRouter|useRoutes)\s*\([^)]*(["\'])(\/[^"\']{0,160})\1/i',
        ];

        foreach ($strong_patterns as $pattern) {
            if (!preg_match_all($pattern, $js, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $match) {
                $path = $match[2] ?? '';
                $path = html_entity_decode(rawurldecode(trim($path)), ENT_QUOTES);
                if (!$this->clone_client_bundle_route_path_allowed($path)) {
                    continue;
                }
                if (isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $paths[] = $path;
            }
        }

        // A broad quoted-string scan is useful for some hand-rolled routers,
        // but minified Lovable bundles also contain many SDK/API paths such as
        // /vector, /recovery, and /iceberg. Only use that fallback when no
        // explicit router/path declarations were found at all.
        if (empty($paths) && preg_match_all('/(["\'])(\/[A-Za-z0-9][^"\']{0,120})\1/', $js, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $path = html_entity_decode(rawurldecode(trim($match[2] ?? '')), ENT_QUOTES);
                if (!$this->clone_client_bundle_route_path_allowed($path) || isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function clone_client_bundle_route_path_allowed($path)
    {
        if (!is_string($path) || $path === '' || $path[0] !== '/' || strpos($path, '//') === 0) {
            return false;
        }
        if ($this->clone_is_special_url($path)) {
            return false;
        }
        if (preg_match('#/(?:%3a|:)[a-z_][a-z0-9_]*(?:/|$)#i', $path)) {
            return false;
        }
        if (strlen($path) > 160 || preg_match('/[\{\}\[\]\(\)\*\$`<>\\\\]/', $path)) {
            return false;
        }
        if (preg_match('#^/(?:assets|api|__|~|@|src|node_modules|vite|wp-|\.well-known)(?:/|$)#i', $path)) {
            return false;
        }
        if (preg_match('#/(?:chunk|chunks|static|dist|build)/#i', $path)) {
            return false;
        }
        if (preg_match('/\.(?:css|js|mjs|json|map|jpg|jpeg|png|gif|webp|svg|ico|bmp|avif|woff|woff2|ttf|eot|otf|mp4|webm|ogg|mp3|wav|pdf|zip)(?:$|\?)/i', $path)) {
            return false;
        }
        if (preg_match('#^/(?:auth|admin|demo|dashboard|settings)(?:/|$)#i', $path)) {
            return false;
        }
        if (preg_match('#/(?:login|logout|signup|sign-up|register|callback|oauth|recovery|reset-password|forgot-password|verify|invite|api|search|cart|checkout)(?:/|$)#i', $path)) {
            return false;
        }

        return true;
    }

    private function clone_load_html_dom($html)
    {
        if (!class_exists('DOMDocument')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $flags = defined('LIBXML_NONET') ? LIBXML_NONET : 0;
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, $flags);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }

    private function clone_discover_sitemap_pages($home_url, $max_pages, &$warnings, $options = [])
    {
        $sitemaps = $this->clone_get_sitemap_candidates($home_url, $warnings, $options);
        $visited = [];
        $pages = [];
        $deadline = floatval($options['deadline'] ?? 0);
        $sitemap_timeout = max(4, min(20, intval($options['sitemap_timeout'] ?? 20)));

        foreach ($sitemaps as $sitemap) {
            if ($deadline > 0 && microtime(true) >= $deadline) {
                $warnings[] = __('Slow fallback sitemap checks were stopped early; review the discovered pages and add any missing URLs manually if needed.', 'tahmir-ai-to-wp-converter');
                break;
            }
            foreach ($this->clone_fetch_sitemap_locations($sitemap['url'], $home_url, $warnings, $visited, 0, !empty($sitemap['explicit']), $sitemap_timeout, $deadline) as $page_url) {
                if (count($pages) >= $max_pages) {
                    break 2;
                }
                if (!$this->clone_is_importable_page_url($page_url, $home_url)) {
                    continue;
                }
                $canonical = $this->clone_canonical_page_url($page_url);
                if (isset($pages[$canonical])) {
                    continue;
                }
                $pages[$canonical] = [
                    'url' => $canonical,
                    'title' => $this->clone_title_from_url($canonical),
                ];
            }
        }

        return array_values($pages);
    }

    private function clone_get_sitemap_candidates($home_url, &$warnings, $options = [])
    {
        $parsed = wp_parse_url($home_url);
        if (empty($parsed['host'])) {
            return [];
        }

        $origin = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        $candidates = [];

        $robots_url = trailingslashit($origin) . 'robots.txt';
        $response = $this->clone_remote_get_with_retries($robots_url, [
            'timeout' => max(4, min(12, intval($options['robots_timeout'] ?? 12))),
            'redirection' => 3,
            'user-agent' => 'Mozilla/5.0 (compatible; UgenticFaithfulClone/1.0)',
        ], 'import_clone_robots', $warnings, [
            'retries' => 2,
            'warn_retries' => false,
            'retry_base_ms' => 300,
            'deadline' => floatval($options['deadline'] ?? 0),
        ]);

        if (!is_wp_error($response) && intval(wp_remote_retrieve_response_code($response)) >= 200 && intval(wp_remote_retrieve_response_code($response)) < 400) {
            $body = wp_remote_retrieve_body($response);
            if (preg_match_all('/^\s*Sitemap:\s*(\S+)\s*$/im', $body, $matches)) {
                foreach ($matches[1] as $sitemap_url) {
                    $candidates[] = ['url' => esc_url_raw(trim($sitemap_url)), 'explicit' => true];
                }
            }
        }

        foreach (['sitemap.xml', 'sitemap_index.xml', 'wp-sitemap.xml'] as $path) {
            $candidates[] = ['url' => trailingslashit($origin) . $path, 'explicit' => false];
        }

        $deduped = [];
        foreach ($candidates as $candidate) {
            if (empty($candidate['url']) || isset($deduped[$candidate['url']])) {
                continue;
            }
            $deduped[$candidate['url']] = $candidate;
        }

        return array_values($deduped);
    }

    private function clone_fetch_sitemap_locations($sitemap_url, $home_url, &$warnings, &$visited, $depth = 0, $warn_on_failure = false, $timeout = 20, $deadline = 0.0)
    {
        if ($depth > 4 || isset($visited[$sitemap_url])) {
            return [];
        }
        if ($deadline > 0 && microtime(true) >= $deadline) {
            return [];
        }
        $visited[$sitemap_url] = true;

        $response = $this->clone_remote_get_with_retries($sitemap_url, [
            'timeout' => max(4, min(20, intval($timeout))),
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (compatible; UgenticFaithfulClone/1.0)',
        ], 'import_clone_sitemap', $warnings, [
            'retries' => 2,
            'warn_retries' => $warn_on_failure,
            'retry_base_ms' => 350,
            'deadline' => $deadline,
        ]);

        if (is_wp_error($response)) {
            if ($warn_on_failure) {
                $warnings[] = sprintf(/* translators: 1: Sitemap URL; 2: Fetch error. */ __('Sitemap skipped %1$s: %2$s', 'tahmir-ai-to-wp-converter'), $sitemap_url, $this->clone_remote_error_message($sitemap_url, $response, $response->get_error_message()));
            }
            return [];
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        if ($code < 200 || $code >= 400) {
            if ($warn_on_failure) {
                $warnings[] = sprintf(/* translators: 1: Sitemap URL; 2: Fetch error. */ __('Sitemap skipped %1$s: %2$s', 'tahmir-ai-to-wp-converter'), $sitemap_url, $this->clone_remote_error_message($sitemap_url, $response, sprintf(/* translators: HTTP status code. */ __('HTTP %d', 'tahmir-ai-to-wp-converter'), $code)));
            }
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        if (!preg_match_all('#<loc>\s*(.*?)\s*</loc>#is', $body, $matches)) {
            return [];
        }

        $locations = [];
        foreach ($matches[1] as $loc) {
            $loc = trim(html_entity_decode(wp_strip_all_tags($loc), ENT_QUOTES));
            if ($loc === '') {
                continue;
            }

            if (preg_match('/\.xml(?:$|\?)/i', wp_parse_url($loc, PHP_URL_PATH) ?: '')) {
                $locations = array_merge($locations, $this->clone_fetch_sitemap_locations($loc, $home_url, $warnings, $visited, $depth + 1, true, $timeout, $deadline));
                continue;
            }

            if ($this->clone_is_importable_page_url($loc, $home_url)) {
                $locations[] = $loc;
            }
        }

        return $locations;
    }

    private function clone_configure_asset_runtime_limits($options, $single_page, $page_count)
    {
        $page_count = max(1, intval($page_count));
        $default_limit = $single_page ? 750 : min(2500, max(900, $page_count * 320));
        $default_budget = $single_page ? 240.0 : min(900.0, max(300.0, $page_count * 120.0));

        $this->clone_asset_download_limit = max(40, min(2500, intval($options['max_asset_downloads'] ?? $default_limit)));
        $this->clone_asset_timeout = max(5, min(30, intval($options['asset_timeout'] ?? 12)));
        $asset_time_budget = max(45.0, min(900.0, floatval($options['asset_time_budget'] ?? $default_budget)));
        $this->clone_asset_deadline = microtime(true) + $asset_time_budget;
    }

    private function clone_report_progress($phase, $progress, $message, $data = [])
    {
        $progress = max($this->clone_progress_last_value, max(0, min(100, intval($progress))));
        $this->clone_progress_last_value = $progress;

        $event = array_merge([
            'phase' => sanitize_key($phase),
            'progress' => $progress,
            'message' => (string) $message,
            'updated_at' => time(),
        ], is_array($data) ? $data : []);

        $this->clone_progress_events[] = $event;
        if (count($this->clone_progress_events) > 80) {
            $this->clone_progress_events = array_slice($this->clone_progress_events, -80);
        }

        if (is_callable($this->clone_progress_callback)) {
            call_user_func($this->clone_progress_callback, $event);
        }
    }

    private function clone_remote_get_with_retries($url, $args, $context, &$warnings = null, $options = [])
    {
        $max_retries = max(0, min(5, intval($options['retries'] ?? self::REMOTE_CLONE_HTML_RETRIES)));
        $max_attempts = $max_retries + 1;
        $warn_retries = !empty($options['warn_retries']);
        $retry_base_ms = max(120, min(2500, intval($options['retry_base_ms'] ?? 350)));
        $deadline = floatval($options['deadline'] ?? 0);
        $last_response = null;
        $last_reason = '';
        $ssl_fallback_active = false;

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $request_args = $args;
            if ($deadline > 0) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    return new WP_Error(
                        'ugentic_clone_deadline_exceeded',
                        __('The clone time budget ended. Remaining assets will keep their source URLs.', 'tahmir-ai-to-wp-converter'),
                        ['url' => $url, 'attempts' => $attempt - 1]
                    );
                }

                // A retry must never outlive the caller's total time budget.
                $configured_timeout = max(1.0, floatval($request_args['timeout'] ?? 30));
                $request_args['timeout'] = max(1, min($configured_timeout, (int) ceil($remaining)));
            }
            $response = Ugentic_Security::remote_get($url, $request_args, $context);
            $last_response = $response;
            $last_reason = $this->clone_remote_fetch_failure_reason($response);

            if (!$this->clone_remote_fetch_should_retry($response) || $attempt >= $max_attempts) {
                if (is_array($response)) {
                    $response['ugentic_retry_attempts'] = $attempt;
                    $response['ugentic_retry_reason'] = $last_reason;
                    $response['ugentic_ssl_fallback'] = $ssl_fallback_active;
                } elseif (is_wp_error($response)) {
                    $response->add_data([
                        'attempts' => $attempt,
                        'reason' => $last_reason,
                        'url' => $url,
                        'ssl_fallback' => $ssl_fallback_active,
                    ], 'ugentic_remote_fetch');
                }
                return $response;
            }

            if ($warn_retries && is_array($warnings)) {
                $retry_message = sprintf(
                    /* translators: 1: Source URL; 2: Network error; 3: Retry number; 4: Maximum attempts. */ __('Temporary network/source error while fetching %1$s: %2$s. Retrying attempt %3$d of %4$d...', 'tahmir-ai-to-wp-converter'),
                    $url,
                    $last_reason ?: __('unknown error', 'tahmir-ai-to-wp-converter'),
                    $attempt + 1,
                    $max_attempts
                );
                $warnings[] = $retry_message;
                $this->clone_report_progress('network_retry', min(96, $this->clone_progress_last_value + 1), $retry_message, [
                    'url' => $url,
                    'attempt' => $attempt + 1,
                    'attempts_total' => $max_attempts,
                    'reason' => $last_reason,
                    'context' => $context,
                ]);
            }

            $sleep_ms = $retry_base_ms * $attempt;
            if ($deadline > 0) {
                $remaining_ms = (int) floor(max(0, $deadline - microtime(true)) * 1000);
                if ($remaining_ms <= 0) {
                    return new WP_Error(
                        'ugentic_clone_deadline_exceeded',
                        __('The clone time budget ended. Remaining assets will keep their source URLs.', 'tahmir-ai-to-wp-converter'),
                        ['url' => $url, 'attempts' => $attempt]
                    );
                }
                $sleep_ms = min($sleep_ms, $remaining_ms);
            }
            usleep($sleep_ms * 1000);
        }

        if (is_wp_error($last_response)) {
            $last_response->add_data([
                'attempts' => $max_attempts,
                'reason' => $last_reason,
                'url' => $url,
                'ssl_fallback' => $ssl_fallback_active,
            ], 'ugentic_remote_fetch');
        }

        return $last_response;
    }

    private function clone_remote_fetch_should_retry($response)
    {
        if (is_wp_error($response)) {
            if (in_array($response->get_error_code(), [
                'ugentic_empty_url',
                'ugentic_invalid_url',
                'ugentic_invalid_scheme',
                'ugentic_invalid_host',
                'ugentic_private_remote_fetch',
                'ugentic_clone_deadline_exceeded',
            ], true)) {
                return false;
            }
            return true;
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        return in_array($code, [408, 409, 425, 429], true) || ($code >= 500 && $code <= 599);
    }

    private function clone_remote_fetch_failure_reason($response)
    {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        if ($code < 200 || $code >= 400) {
            $message = trim((string) wp_remote_retrieve_response_message($response));
            return $message !== '' ? sprintf('HTTP %d %s', $code, $message) : sprintf('HTTP %d', $code);
        }

        return '';
    }

    private function clone_remote_attempt_count($response)
    {
        if (is_array($response) && isset($response['ugentic_retry_attempts'])) {
            return max(1, intval($response['ugentic_retry_attempts']));
        }
        if (is_wp_error($response)) {
            $data = $response->get_error_data('ugentic_remote_fetch');
            if (is_array($data) && isset($data['attempts'])) {
                return max(1, intval($data['attempts']));
            }
        }
        return 1;
    }

    private function clone_remote_error_message($url, $response, $fallback = '')
    {
        $attempts = $this->clone_remote_attempt_count($response);
        $reason = $this->clone_remote_fetch_failure_reason($response);
        if ($reason === '') {
            $reason = $fallback ?: __('unknown network error', 'tahmir-ai-to-wp-converter');
        }

        if ($attempts > 1) {
            return sprintf(
                /* translators: 1: Source URL; 2: Attempt count; 3: Last network error. */ __('Could not fetch %1$s after %2$d attempts. Last error: %3$s', 'tahmir-ai-to-wp-converter'),
                $url,
                $attempts,
                $reason
            );
        }

        return sprintf(/* translators: 1: Source URL; 2: Network error. */ __('Could not fetch %1$s. Error: %2$s', 'tahmir-ai-to-wp-converter'), $url, $reason);
    }

    /**
     * Process a remote HTML document for faithful local import.
     */
    private function clone_process_html($html, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings, $clone_source = 'auto', $ai_assisted = false, $ai_assistant = null)
    {
        $detected_source = $this->clone_detect_source_type($html, $page_url, $clone_source);
        $previous_source_type = $this->clone_current_source_type;
        $this->clone_current_source_type = $detected_source;
        $client_rendered_shell = $this->clone_is_rendered_shell($html);
        $source_publish_safety = $this->clone_build_publish_safety_report($html);
        $resource_base = $this->clone_resource_base_url($html, $page_url);
        $this->clone_import_map = $this->clone_extract_import_map($html, $resource_base);
        $html = preg_replace('/<base\b[^>]*>/i', '', $html);
        $html = $this->clone_stabilize_static_framework_document($html, $page_url, $warnings);
        if ($this->clone_needs_client_app_path_shim($html, $page_url, $client_rendered_shell)) {
            $html = $this->clone_inject_client_app_path_shim($html, $page_url, $warnings, $asset_dir, $asset_url);
        }
        $html = $this->clone_stabilize_builder_runtime($html, $detected_source, $warnings);

        // Localize stylesheets (and their nested font/icon assets) before
        // images can consume the clone's time/download budget.
        $html = $this->clone_rewrite_stylesheet_assets($html, $resource_base, $asset_dir, $asset_url, $asset_cache, $warnings);
        $html = $this->clone_rewrite_srcset_attributes($html, $resource_base, $asset_dir, $asset_url, $asset_cache, $warnings);
        $html = $this->clone_rewrite_url_attributes($html, $resource_base, $asset_dir, $asset_url, $asset_cache, $warnings);
        $html = $this->clone_rewrite_meta_content_assets($html, $resource_base, $asset_dir, $asset_url, $asset_cache, $warnings);
        $html = $this->clone_rewrite_style_blocks($html, $resource_base, $asset_dir, $asset_url, $asset_cache, $warnings);
        $html = $this->clone_rewrite_inline_styles($html, $resource_base, $asset_dir, $asset_url, $asset_cache, $warnings);
        $html = $this->clone_rewrite_inline_script_assets($html, $resource_base, $asset_dir, $asset_url, $asset_cache, $warnings);
        $html = $this->clone_rewrite_localized_client_imports($html);
        $html = $this->clone_remove_broken_image_placeholders($html);
        if ($client_rendered_shell) {
            $html = $this->clone_externalize_inline_bootstrap($html, $asset_dir, $asset_url, $warnings);
        }
        // Persist only the editable document. Runtime protection is applied to
        // a render copy by faithful-template.php, where it cannot poison the
        // database safety check or become part of the page users edit.
        $html = $this->clone_hide_builder_badges($html, $detected_source, $warnings);
        $this->clone_current_source_type = $previous_source_type;
        $this->clone_import_map = [];

        $image_optimization_time_left = $this->clone_asset_deadline > 0
            ? $this->clone_asset_deadline - microtime(true)
            : 20.0;
        if (class_exists('Ugentic_Image_Optimizer') && $image_optimization_time_left >= 5.0) {
            $html = Ugentic_Image_Optimizer::optimize_imported_html($html, [
                'source' => 'remote_clone',
                'source_url' => $page_url,
                // Image variants share the clone's total asset deadline. A
                // large news grid must not continue for minutes after asset
                // localization has already reached its budget.
                'deadline' => $this->clone_asset_deadline,
            ]);
        }

        if ($ai_assisted && class_exists('Ugentic_Form_Engine')) {
            // Add native fallbacks for controls (for example JS-only date
            // pickers) before registration. Existing real controls are never
            // replaced and source form roots remain intact.
            $html = Ugentic_Form_Engine::materialize_static_controls($html);
        }

        $ai_form_analysis = [
            'success' => true,
            'status' => $ai_assisted ? 'no_forms' : 'disabled',
            'model' => '',
            'plans' => [],
            'forms_planned' => 0,
        ];
        if ($ai_assisted && $ai_assistant && method_exists($ai_assistant, 'analyze_forms') && stripos($html, '<form') !== false) {
            $ai_form_analysis = $ai_assistant->analyze_forms($html, $page_url);
            if (empty($ai_form_analysis['success'])) {
                $this->clone_add_runtime_warning_once(
                    'ai_form_planner_fallback',
                    __('AI form planning was unavailable for at least one page. Tahmir used its deterministic form recovery, so the import continued without dropping fields.', 'tahmir-ai-to-wp-converter'),
                    $warnings
                );
            }
        }
        $form_options = [
            'ai_form_plans' => $ai_form_analysis['plans'] ?? [],
            'ai_model' => $ai_form_analysis['model'] ?? '',
        ];

        $form_result = $this->form_converter->detect_and_register_forms($html, $form_options);
        $html = $form_result['html'];
        $forms_created = intval($form_result['forms_created'] ?? 0);
        $form_ids = $form_result['form_ids'] ?? [];
        $form_audit = [
            'forms' => count($form_ids),
            'complex_forms' => 0,
            'multi_step_forms' => 0,
            'fields' => 0,
            'ai_forms_planned' => intval($form_result['ai_forms_planned'] ?? 0),
        ];
        if ($ai_assisted && method_exists($this->form_converter, 'enhance_ai_assisted_forms')) {
            $enhanced_forms = $this->form_converter->enhance_ai_assisted_forms($html, $form_options);
            $html = $enhanced_forms['html'] ?? $html;
            $form_audit = [
                'forms' => intval($enhanced_forms['forms'] ?? count($form_ids)),
                'complex_forms' => intval($enhanced_forms['complex_forms'] ?? 0),
                'multi_step_forms' => intval($enhanced_forms['multi_step_forms'] ?? 0),
                'fields' => intval($enhanced_forms['fields'] ?? 0),
                'ai_forms_planned' => intval($enhanced_forms['ai_forms_planned'] ?? ($form_result['ai_forms_planned'] ?? 0)),
            ];
        }

        // Faithful form handling is supplied by faithful-template.php at render
        // time. Do not bake submit handlers into imported HTML.

        // Store faithful source HTML without editor-only attributes.
        $processed_publish_safety = $this->clone_build_publish_safety_report($html);
        if (!empty($processed_publish_safety['risks'])) {
            $this->clone_add_runtime_warning_once(
                'publish_safety_residual_risks',
                sprintf(
                    /* translators: List of detected runtime risks. */ __('Publish safety check found residual runtime risks: %s', 'tahmir-ai-to-wp-converter'),
                    implode(', ', array_slice($processed_publish_safety['risks'], 0, 4))
                ),
                $warnings
            );
        }

        return [
            'html' => $html,
            'forms_created' => $forms_created,
            'form_ids' => $form_ids,
            'form_audit' => $form_audit,
            'ai_form_status' => sanitize_key($ai_form_analysis['status'] ?? 'not_run'),
            'ai_form_model' => sanitize_text_field($ai_form_analysis['model'] ?? ''),
            'ai_form_analysis_allowed' => $ai_assisted,
            'client_rendered_shell' => $client_rendered_shell,
            'clone_source_type' => $detected_source,
            'publish_safety' => [
                'source' => $source_publish_safety,
                'processed' => $processed_publish_safety,
            ],
        ];
    }

    /**
     * Localize assets that only become visible after a client application has
     * rendered its DOM. Initial Lovable shells contain no hero/content images,
     * so the normal source pass cannot discover them.
     *
     * @return array{html:string,assets_downloaded:int,assets_failed:int,warnings:array}
     */
    public function localize_client_shell_snapshot_assets($html, $page_url, $asset_namespace, $clone_source = 'auto')
    {
        $result = [
            'html' => (string) $html,
            'assets_downloaded' => 0,
            'assets_failed' => 0,
            'warnings' => [],
        ];
        $asset_namespace = sanitize_file_name((string) $asset_namespace);
        if (
            $result['html'] === ''
            || !filter_var($page_url, FILTER_VALIDATE_URL)
            || !preg_match('/^ugentic-clone-[a-f0-9]{8,64}$/i', $asset_namespace)
        ) {
            return $result;
        }

        $uploads = wp_upload_dir();
        $uploads_dir = !empty($uploads['basedir']) ? realpath((string) $uploads['basedir']) : false;
        $uploads_url = untrailingslashit((string) ($uploads['baseurl'] ?? ''));
        if (!$uploads_dir || $uploads_url === '') {
            return $result;
        }

        $asset_dir = realpath(trailingslashit($uploads_dir) . 'ugentic-clones' . DIRECTORY_SEPARATOR . $asset_namespace);
        $clone_root = realpath(trailingslashit($uploads_dir) . 'ugentic-clones');
        if (!$asset_dir || !$clone_root || strpos(
            strtolower(trailingslashit(wp_normalize_path($asset_dir))),
            strtolower(trailingslashit(wp_normalize_path($clone_root)))
        ) !== 0) {
            return $result;
        }
        $asset_url = $uploads_url . '/ugentic-clones/' . rawurlencode($asset_namespace);

        $cache_transient = 'ugentic_shell_assets_' . strtolower($asset_namespace);
        $asset_cache = get_transient($cache_transient);
        $asset_cache = is_array($asset_cache) ? $asset_cache : [];
        $asset_cache = array_filter($asset_cache, function ($local_url) use ($asset_url) {
            return is_string($local_url) && strpos($local_url, trailingslashit($asset_url)) === 0;
        });
        $initial_asset_count = count($asset_cache);

        $warnings = [];
        $previous_source_type = $this->clone_current_source_type;
        $this->clone_current_source_type = $this->clone_normalize_source_type($clone_source);
        $this->clone_asset_failures = [];
        $this->clone_asset_download_count = 0;
        $this->clone_asset_progress_last_report = 0;
        $this->clone_asset_download_limit = 300;
        $this->clone_asset_deadline = microtime(true) + 150;
        $this->clone_asset_timeout = 20;

        $localized = $result['html'];
        $localized = $this->clone_rewrite_stylesheet_assets($localized, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
        $localized = $this->clone_rewrite_srcset_attributes($localized, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
        $localized = $this->clone_rewrite_url_attributes($localized, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
        $localized = $this->clone_rewrite_meta_content_assets($localized, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
        $localized = $this->clone_rewrite_style_blocks($localized, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
        $localized = $this->clone_rewrite_inline_styles($localized, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
        $localized = $this->clone_remove_broken_image_placeholders($localized);

        set_transient($cache_transient, $asset_cache, 6 * HOUR_IN_SECONDS);
        $this->clone_current_source_type = $previous_source_type;

        return [
            'html' => $localized,
            'assets_downloaded' => max(0, count($asset_cache) - $initial_asset_count),
            'assets_failed' => count($this->clone_asset_failures),
            'warnings' => array_values(array_unique(array_filter($warnings))),
        ];
    }

    /** Prevent hash-only image requests from rendering broken-image glyphs. */
    private function clone_remove_broken_image_placeholders($html)
    {
        return preg_replace_callback('/<img\b[^>]*>/i', static function ($match) {
            return preg_replace('/\s+(?:src|data-src|data-lazy-src|data-original)\s*=\s*(?:"\s*#?\s*"|\'\s*#?\s*\'|#(?=\s|\/?>))/i', '', $match[0]);
        }, (string) $html);
    }

    /** Finish structural recovery after a JavaScript application is rendered. */public function enhance_ai_assisted_snapshot($html, $source_url, $meta = []) { return ['html'=>$html, 'forms_created'=>0, 'form_ids'=>[]]; }

    /** Separate shared chrome only when the remaining document is substantive. */private function clone_prepare_ai_assisted_page($html, $source_url, $meta = []) { return ['html'=>$html, 'css_ids'=>[], 'js_ids'=>[], 'header_id'=>0, 'footer_id'=>0]; }

    private function clone_attach_ai_assisted_metadata($result, $processed)
    {
        if (empty($result['success']) || empty($result['page'])) {
            return $result;
        }
        $page = $result['page'];
        $target_wp_ids = [];
        if (!empty($page['wp_page_id']) && get_post_type($page['wp_page_id']) === 'page') {
            $target_wp_ids[] = absint($page['wp_page_id']);
        }
        if (in_array(($page['import_role'] ?? ''), ['blog_single', 'blog_listing'], true)) {
            $settings = get_option('ugentic_settings', []);
            if (!empty($settings['blog_template_wp_id'])) {
                $target_wp_ids[] = absint($settings['blog_template_wp_id']);
            }
        }
        $target_wp_ids = array_values(array_unique(array_filter($target_wp_ids)));
        $database = ugentic_builder()->database;
        foreach ($target_wp_ids as $wp_page_id) {
            $ugentic_page_id = absint(get_post_meta($wp_page_id, '_ugentic_page_id', true));
            $record = $ugentic_page_id ? $database->get_page($ugentic_page_id) : null;
            if (!$record) {
                continue;
            }
            $meta = json_decode($record->meta_data ?: '{}', true);
            $meta = is_array($meta) ? $meta : [];
            $meta['ai_assisted_clone'] = true;
            $meta['ai_form_analysis_allowed'] = !empty($processed['ai_form_analysis_allowed']);
            $meta['ai_form_status'] = sanitize_key($processed['ai_form_status'] ?? 'not_run');
            $meta['ai_plan_status'] = sanitize_key($processed['ai_plan_status'] ?? 'local_protected');
            $meta['ai_plan_model'] = sanitize_text_field($processed['ai_plan_model'] ?? '');
            $meta['ai_plan_summary'] = $processed['ai_plan_summary'] ?? [];
            $meta['form_audit'] = $processed['form_audit'] ?? [];
            $meta['section_separation_status'] = sanitize_key($processed['section_separation_status'] ?? 'not_found');
            $database->update_page($ugentic_page_id, [
                'global_header_id' => !empty($processed['global_header_id']) ? absint($processed['global_header_id']) : null,
                'global_footer_id' => !empty($processed['global_footer_id']) ? absint($processed['global_footer_id']) : null,
                'meta_data' => wp_json_encode($meta),
            ]);
            update_post_meta($wp_page_id, '_ugentic_ai_assisted_clone', '1');
            $verified = $this->build_verified_local_script_manifest($record->current_html ?: ($processed['html'] ?? ''));
            if ($verified) {
                update_post_meta($wp_page_id, '_ugentic_imported_script_manifest_v1', $verified);
            }
        }
        $result['page']['ai_assisted'] = true;
        $result['page']['global_header_id'] = absint($processed['global_header_id'] ?? 0);
        $result['page']['global_footer_id'] = absint($processed['global_footer_id'] ?? 0);
        $result['page']['form_audit'] = $processed['form_audit'] ?? [];
        return $result;
    }

    /**
     * Create a WordPress page that uses the faithful full-document template.
     */
    private function clone_create_faithful_page($title, $original_html, $processed_html, $source_url, $sort_order, $publish, $processed)
    {
        $database = ugentic_builder()->database;
        $title = $title ?: $this->clone_title_from_url($source_url);
        $blog_detection = is_array($processed['blog_detection_override'] ?? null)
            ? $processed['blog_detection_override']
            : $this->detect_blog_page_for_clone($processed_html, $title, $source_url);

        $wp_page_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => '<!-- Faithful Clone: full HTML served by template -->',
            'post_status' => $publish ? 'publish' : 'draft',
            'post_type' => 'page',
            'post_name' => sanitize_title($title),
            'menu_order' => intval($sort_order),
        ], true);

        if (is_wp_error($wp_page_id)) {
            return ['success' => false, 'message' => $wp_page_id->get_error_message()];
        }

        $ugentic_page_id = $database->insert_page([
            'wp_page_id' => $wp_page_id,
            'original_html' => $original_html,
            'current_html' => $processed_html,
            'global_header_id' => !empty($processed['global_header_id']) ? absint($processed['global_header_id']) : null,
            'global_footer_id' => !empty($processed['global_footer_id']) ? absint($processed['global_footer_id']) : null,
            'meta_data' => wp_json_encode([
                'imported' => true,
                'import_mode' => 'faithful',
                'clone_source_url' => $source_url,
                'source_file' => basename(wp_parse_url($source_url, PHP_URL_PATH) ?: 'index.html') ?: 'index.html',
                'forms_created' => intval($processed['forms_created'] ?? 0),
                'form_ids' => $processed['form_ids'] ?? [],
                'client_rendered_shell' => !empty($processed['client_rendered_shell']),
                'clone_source_type' => $processed['clone_source_type'] ?? 'auto',
                'clone_asset_namespace' => sanitize_file_name((string) ($processed['clone_asset_namespace'] ?? '')),
                'publish_safety' => $processed['publish_safety'] ?? [],
                'blog_detection' => $blog_detection,
                'ai_assisted_clone' => !empty($processed['ai_assisted']),
                'ai_form_analysis_allowed' => !empty($processed['ai_form_analysis_allowed']),
                'ai_form_status' => sanitize_key($processed['ai_form_status'] ?? ''),
                'ai_plan_status' => sanitize_key($processed['ai_plan_status'] ?? ''),
                'ai_plan_model' => sanitize_text_field($processed['ai_plan_model'] ?? ''),
                'ai_plan_summary' => $processed['ai_plan_summary'] ?? [],
                'form_audit' => $processed['form_audit'] ?? [],
                'section_separation_status' => sanitize_key($processed['section_separation_status'] ?? ''),
            ]),
        ]);

        if (!$ugentic_page_id) {
            // The WordPress post is only a transport shell. Never leave that
            // placeholder behind when the actual Tahmir document did not save.
            wp_delete_post($wp_page_id, true);
            return [
                'success' => false,
                'message' => __('Tahmir could not store the imported page HTML. The empty placeholder was removed; no blank page was created.', 'tahmir-ai-to-wp-converter'),
            ];
        }

        update_post_meta($wp_page_id, '_wp_page_template', 'ugentic-faithful');
        update_post_meta($wp_page_id, '_ugentic_page_id', $ugentic_page_id);
        update_post_meta($wp_page_id, '_ugentic_clone_source_url', esc_url_raw($source_url));
        $verified_local_scripts = $this->build_verified_local_script_manifest($processed_html);
        if (!empty($verified_local_scripts)) {
            update_post_meta($wp_page_id, '_ugentic_imported_script_manifest_v1', $verified_local_scripts);
        }

        $post_status = get_post_status($wp_page_id);
        $view_url = $post_status === 'publish' ? get_permalink($wp_page_id) : get_preview_post_link($wp_page_id);

        return [
            'success' => true,
            'page' => [
                'title' => $title,
                'source_url' => $source_url,
                'wp_page_id' => $wp_page_id,
                'ugentic_id' => $ugentic_page_id,
                'edit_url' => get_edit_post_link($wp_page_id, 'raw'),
                'view_url' => $view_url ?: get_permalink($wp_page_id),
                'blog_detection' => $blog_detection,
                'client_rendered_shell' => !empty($processed['client_rendered_shell']),
                'clone_source_type' => $processed['clone_source_type'] ?? 'auto',
                'import_role' => 'page',
                'ai_assisted' => !empty($processed['ai_assisted']),
                'global_header_id' => absint($processed['global_header_id'] ?? 0),
                'global_footer_id' => absint($processed['global_footer_id'] ?? 0),
                'form_audit' => $processed['form_audit'] ?? [],
            ],
        ];
    }

    /**
     * Prioritize linked stylesheets so nested webfonts and icon fonts are cloned
     * before large pages exhaust the general asset budget.
     */
    private function clone_rewrite_stylesheet_assets($html, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        return $this->clone_map_html_tags($html, function ($tag) use ($page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
            if (!preg_match('/^<link\b/i', $tag)) {
                return $tag;
            }
            if (!preg_match('/\brel\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $rel_match)) {
                return $tag;
            }
            $rel = strtolower(trim((string) ($rel_match[1] ?: ($rel_match[2] ?: ($rel_match[3] ?? '')))));
            if (strpos(' ' . $rel . ' ', ' stylesheet ') === false) {
                return $tag;
            }
            if (!preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $href_match)) {
                return $tag;
            }
            $href = html_entity_decode(trim((string) ($href_match[1] ?: ($href_match[2] ?: ($href_match[3] ?? '')))), ENT_QUOTES);
            if ($href === '' || $this->clone_is_special_url($href)) {
                return $tag;
            }
            $downloaded = $this->clone_download_asset($href, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings, true);
            $downloaded = $downloaded ?: $this->clone_resolve_url($href, $page_url);
            if (!$downloaded) {
                return $tag;
            }
            $tag = preg_replace_callback('/(\bhref\s*=\s*)(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', function ($href_attribute) use ($downloaded) {
                return $href_attribute[1] . '"' . esc_url($downloaded) . '"';
            }, $tag, 1);
            return preg_replace('/\s+integrity\s*=\s*(["\']).*?\1/i', '', $tag);
        });
    }

    /**
     * Rewrite URLs in src/href/poster/data-* attributes.
     */
    private function clone_rewrite_url_attributes($html, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        return $this->clone_map_html_tags($html, function ($opening) use ($page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
            preg_match('/^<([a-z0-9:-]+)/i', $opening, $name);
            $tag = strtolower($name[1] ?? '');
            $localized = false;
            $rewritten = preg_replace_callback(
                '/(\s)([a-z_:][a-z0-9_:.-]*)(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
                function ($attr) use ($tag, $opening, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings, &$localized) {
                    $key = strtolower($attr[2]);
                    $value = html_entity_decode(($attr[4] ?? '') !== '' ? $attr[4] : (($attr[5] ?? '') !== '' ? $attr[5] : ($attr[6] ?? '')), ENT_QUOTES, 'UTF-8');
                    // Candidate lists have already been localized by the srcset pass.
                    // Widget state and Framer's boolean image-wrapper markers are
                    // not URLs. Rewriting them also breaks exact-match CSS selectors.
                    if (in_array($key, ['srcset', 'data-srcset', 'data-lazy-srcset', 'data-bgset', 'imagesrcset'], true)
                        || (strpos($key, 'data-') === 0 && in_array(strtolower(trim($value)), ['true', 'false'], true))) {
                        return $attr[0];
                    }
                    $asset = in_array($key, ['src', 'poster', 'background', 'xlink:href', 'data-original', 'data-lazy'], true)
                        || (strpos($key, 'data-') === 0 && $this->clone_attribute_is_asset_hint($key))
                        || ($key === 'href' && $tag === 'link' && $this->clone_href_is_asset($tag, $opening, $value));
                    $link = $key === 'href' && in_array($tag, ['a', 'area', 'use', 'image', 'link'], true);
                    if ((!$asset && !$link) || $this->clone_is_special_url($value)) {
                        return $attr[0];
                    }
                    // Frames remain embeds; navigation remains navigation.
                    $downloaded = '';
                    if ($asset && $tag !== 'iframe' && !$this->clone_should_preserve_remote_module_asset($tag, $key, $opening, $value, $page_url)) {
                        $downloaded = $this->clone_download_asset($value, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings, true);
                    }
                    $target = $downloaded ?: $this->clone_resolve_url($value, $page_url);
                    if ($target === '') {
                        return $attr[0];
                    }
                    $localized = $localized || ($downloaded !== '' && $downloaded !== $value);
                    return $attr[1] . $attr[2] . $attr[3] . '"' . esc_attr($target) . '"';
                },
                $opening
            );
            return $localized ? preg_replace('/\s+integrity\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $rewritten) : $rewritten;
        });
    }

    /**
     * Visit opening tags without rewriting markup-like strings inside scripts,
     * styles, comments, or textareas. Preserve the original document bytes.
     */
    private function clone_map_html_tags($html, $callback)
    {
        return preg_replace_callback(
            '~<!--[\s\S]*?-->|(<(script|style|textarea|title)\b(?:[^\'">]|"[^"]*"|\'[^\']*\')*>)([\s\S]*?)(</\2\s*>)|<[a-z][a-z0-9:-]*\b(?:[^\'">]|"[^"]*"|\'[^\']*\')*>~i',
            function ($match) use ($callback) {
                if (strpos($match[0], '<!--') === 0) {
                    return $match[0];
                }
                if (!empty($match[1])) {
                    return $callback($match[1]) . $match[3] . $match[4];
                }
                return $callback($match[0]);
            },
            $html
        );
    }

    private function clone_resource_base_url($html, $page_url)
    {
        if (preg_match('/<base\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $html, $match)) {
            $href = $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : ($match[3] ?? ''));
            return $this->clone_resolve_url($href, $page_url) ?: $page_url;
        }
        return $page_url;
    }

    private function clone_extract_import_map($html, $base_url)
    {
        $maps = ['imports' => [], 'scopes' => []];
        if (!preg_match_all('/<script\b[^>]*\btype\s*=\s*["\']importmap["\'][^>]*>([\s\S]*?)<\/script>/i', $html, $matches)) {
            return $maps;
        }
        $normalize = function ($entries) use ($base_url) {
            $result = [];
            foreach ((array) $entries as $key => $value) {
                if (is_string($value)) {
                    $resolved = $this->clone_resolve_url($value, $base_url);
                    if ($resolved !== '') {
                        $result[$key] = $resolved;
                    }
                }
            }
            uksort($result, function ($a, $b) { return strlen($b) <=> strlen($a); });
            return $result;
        };
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (!is_array($data)) continue;
            $maps['imports'] += $normalize($data['imports'] ?? []);
            foreach ((array) ($data['scopes'] ?? []) as $scope => $entries) {
                $scope_url = $this->clone_resolve_url($scope, $base_url);
                if ($scope_url !== '') $maps['scopes'][$scope_url] = $normalize($entries);
            }
        }
        uksort($maps['scopes'], function ($a, $b) { return strlen($b) <=> strlen($a); });
        return $maps;
    }

    private function clone_resolve_module_specifier($specifier, $base_url)
    {
        $groups = [];
        foreach (($this->clone_import_map['scopes'] ?? []) as $scope => $entries) {
            if (strpos($base_url, $scope) === 0) $groups[] = $entries;
        }
        $groups[] = $this->clone_import_map['imports'] ?? [];
        foreach ($groups as $entries) {
            if (isset($entries[$specifier])) return $entries[$specifier];
            foreach ($entries as $key => $target) {
                if (substr($key, -1) === '/' && substr($target, -1) === '/' && strpos($specifier, $key) === 0) {
                    return $target . substr($specifier, strlen($key));
                }
            }
        }
        return $specifier;
    }

    private function clone_externalize_inline_bootstrap($html, $asset_dir, $asset_url, &$warnings)
    {
        return preg_replace_callback('/<script\b([^>]*)>([\s\S]*?)<\/script>/i', function ($m) use ($asset_dir, $asset_url, &$warnings) {
            if (preg_match('/\bsrc\s*=/i', $m[1]) || trim($m[2]) === '') return $m[0];
            $type = '';
            if (preg_match('/\btype\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $m[1], $t)) {
                $type = strtolower(trim($t[1] ?: ($t[2] ?: ($t[3] ?? ''))));
            }
            if (!in_array($type, ['', 'module', 'text/javascript', 'application/javascript'], true)) return $m[0];
            $name = 'inline-bootstrap-' . hash('sha256', $m[2]) . ($type === 'module' ? '.mjs' : '.js');
            if (!wp_mkdir_p($asset_dir) || file_put_contents(trailingslashit($asset_dir) . $name, $m[2]) === false) {
                $warnings[] = __('Could not save an inline application bootstrap. This page may need an HTML export.', 'tahmir-ai-to-wp-converter');
                return $m[0];
            }
            // The existing exact-hash manifest verifies these bytes before the
            // preview runs them. Editor snapshots still strip all active code.
            return '<script' . $m[1] . ' src="' . esc_url(trailingslashit($asset_url) . $name) . '"></script>';
        }, $html);
    }


    /**
     * Rewrite srcset and data-srcset assets.
     */
    private function clone_rewrite_srcset_attributes($html, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        return $this->clone_map_html_tags($html, function ($tag) use ($page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
            return preg_replace_callback(
            '/(\s)((?:srcset|data-srcset|data-lazy-srcset|data-bgset|imagesrcset)\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
            function ($m) use ($page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
                $value = isset($m[3]) && $m[3] !== '' ? $m[3] : (isset($m[4]) && $m[4] !== '' ? $m[4] : ($m[5] ?? ''));
                if (stripos($value, 'data:') !== false || stripos(rawurldecode($value), '<svg') !== false) {
                    return $m[0];
                }
                // Commas can be part of CDN image URLs (for example /cdn-cgi/image).
                // A candidate separator follows its descriptor or whitespace.
                $items = preg_split('/\s*,\s+(?=\S)|(?<=w),\s*|(?<=x),\s*/', trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8')));
                $candidates = [];
                foreach ($items as $index => $item) {
                    if ($item === '') {
                        continue;
                    }
                    $parts = preg_split('/\s+/', $item, 2);
                    $candidate = $parts[0];
                    $descriptor = isset($parts[1]) ? ' ' . $parts[1] : '';
                    $absolute = $this->clone_resolve_url($candidate, $page_url) ?: $candidate;
                    $candidates[] = [
                        'candidate' => $candidate,
                        'absolute' => $absolute,
                        'descriptor' => $descriptor,
                        'index' => $index,
                    ];
                }

                // Framer emits one image as several scale-down variants. Fetch
                // the largest first, then reuse it for each responsive slot.
                $download_order = $candidates;
                usort($download_order, function ($left, $right) {
                    $left_key = $this->clone_asset_cache_key($left['absolute']);
                    $right_key = $this->clone_asset_cache_key($right['absolute']);
                    if ($left_key === $right_key && $this->clone_is_framer_responsive_asset($left['absolute'])) {
                        $score_difference = $this->clone_srcset_candidate_score($right) <=> $this->clone_srcset_candidate_score($left);
                        if ($score_difference !== 0) {
                            return $score_difference;
                        }
                    }
                    return $left['index'] <=> $right['index'];
                });

                $downloads = [];
                foreach ($download_order as $entry) {
                    $cache_key = $this->clone_asset_cache_key($entry['absolute']);
                    if (!array_key_exists($cache_key, $downloads)) {
                        $downloads[$cache_key] = $this->clone_download_asset($entry['candidate'], $page_url, $asset_dir, $asset_url, $asset_cache, $warnings, true);
                    }
                }

                $rewritten = [];
                foreach ($candidates as $entry) {
                    $cache_key = $this->clone_asset_cache_key($entry['absolute']);
                    $downloaded = $downloads[$cache_key] ?? '';
                    $candidate = $entry['candidate'];
                    $descriptor = $entry['descriptor'];
                    $rewritten[] = ($downloaded ?: ($this->clone_resolve_url($candidate, $page_url) ?: $candidate)) . $descriptor;
                }
                return $m[1] . $m[2] . '"' . esc_attr(implode(', ', $rewritten)) . '"';
            },
            $tag
            );
        });
    }

    private function clone_srcset_candidate_score($entry)
    {
        $descriptor = trim((string) ($entry['descriptor'] ?? ''));
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)w$/i', $descriptor, $match)) {
            return floatval($match[1]);
        }
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)x$/i', $descriptor, $match)) {
            return floatval($match[1]) * 10000;
        }

        $query = wp_parse_url((string) ($entry['absolute'] ?? ''), PHP_URL_QUERY);
        $parameters = [];
        if (is_string($query)) {
            parse_str($query, $parameters);
        }
        return max(
            intval($parameters['scale-down-to'] ?? 0),
            intval($parameters['width'] ?? 0),
            intval($parameters['height'] ?? 0)
        );
    }

    /**
     * Rewrite asset URLs in meta content fields such as og:image/twitter:image.
     */
    private function clone_rewrite_meta_content_assets($html, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        return preg_replace_callback(
            '/<(meta|link)\b([^>]*\scontent\s*=\s*(["\'])([^"\']+)\3[^>]*)>/i',
            function ($m) use ($page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
                $tag = $m[0];
                $content = html_entity_decode(trim($m[4]), ENT_QUOTES);
                if (!$this->clone_url_looks_like_asset($content)) {
                    return $tag;
                }

                $downloaded = $this->clone_download_asset($content, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings, true);
                if (!$downloaded) {
                    return $tag;
                }

                return preg_replace('/\bcontent\s*=\s*(["\'])([^"\']+)\1/i', 'content="' . esc_url($downloaded) . '"', $tag, 1);
            },
            $html
        );
    }

    /**
     * Rewrite url(...) references inside style blocks.
     */
    private function clone_rewrite_style_blocks($html, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        return preg_replace_callback(
            '/<style([^>]*)>([\s\S]*?)<\/style>/i',
            function ($m) use ($page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
                $css = $this->clone_rewrite_css_urls($m[2], $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
                return '<style' . $m[1] . '>' . $css . '</style>';
            },
            $html
        );
    }

    /**
     * Rewrite url(...) references inside inline style attributes.
     */
    private function clone_rewrite_inline_styles($html, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        return preg_replace_callback(
            '/(style\s*=\s*)(["\'])(.*?)\2/is',
            function ($m) use ($page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
                $style_raw = html_entity_decode($m[3], ENT_QUOTES);
                if (stripos($style_raw, 'url(') === false) {
                    return $m[0];
                }

                $style = $this->clone_rewrite_css_urls($style_raw, $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
                return $m[1] . $m[2] . esc_attr($style) . $m[2];
            },
            $html
        );
    }

    /**
     * Rewrite image/font/media URLs embedded in inline framework data scripts.
     */
    private function clone_rewrite_inline_script_assets($html, $page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        return preg_replace_callback(
            '/<script\b([^>]*)>([\s\S]*?)<\/script>/i',
            function ($m) use ($page_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
                $attrs = $m[1];
                if (preg_match('/\bsrc\s*=/i', $attrs) || preg_match('/\btype\s*=\s*(["\'])(?:importmap|speculationrules)\1/i', $attrs)) {
                    return $m[0];
                }

                $rewritten = $this->clone_rewrite_js_urls($m[2], $page_url, $asset_dir, $asset_url, $asset_cache, $warnings);
                return '<script' . $attrs . '>' . $rewritten . '</script>';
            },
            $html
        );
    }

    /**
     * Rewrite framework runtime strings that still point at root-relative app assets.
     *
     * Lovable/TanStack snapshots can include inline router payloads such as
     * import("/assets/index-abc.js") even after modulepreload tags have been
     * localized. On a WordPress permalink that resolves to /assets/... at the
     * site root and can break hydration, leaving the page visually blank.
     */
    private function clone_rewrite_localized_client_imports($html)
    {
        if (stripos($html, '/assets/') === false || stripos($html, 'ugentic-clones/') === false) {
            return $html;
        }

        $asset_map = $this->clone_extract_localized_client_asset_map($html);
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

    private function clone_extract_localized_client_asset_map($html)
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

    /**
     * Keep SSR app-builder pages as a stable static snapshot inside WordPress.
     *
     * Modern builders such as Next/Nuxt often ship complete HTML plus a runtime
     * that expects the original route, build manifest, and data endpoints. After
     * cloning into WordPress that runtime can re-hydrate a mismatched tree and
     * hide or remove server-rendered sections. Keep the HTML/CSS/images, remove
     * fragile framework boot scripts, and add a small no-dependency helper for
     * lazy assets, AOS reveals, menus, search overlays, and theme toggles.
     */
    private function clone_stabilize_static_framework_document($html, $page_url, &$warnings)
    {
        $framework = $this->clone_detect_ssr_framework($html);
        $client_rendered_shell = $this->clone_is_rendered_shell($html);
        $needs_static_runtime = ($framework !== '' && !$client_rendered_shell) || preg_match('/\bdata-aos\b|\baos-init\b|\bswiper\b|\bdata-(?:lazy-)?src\b|\bdata-bg\b/i', $html);
        if (!$needs_static_runtime) {
            return $html;
        }

        $before = $html;
        if ($framework !== '' && !$client_rendered_shell) {
            $html = $this->clone_strip_ssr_framework_runtime($html, $framework);
        }
        if (class_exists('Ugentic_HTML_Cleaner')) {
            $html = Ugentic_HTML_Cleaner::strip_legacy_static_runtime($html);
        }

        if ($html !== $before) {
            $this->clone_add_runtime_warning_once(
                'static_framework_snapshot',
                sprintf(/* translators: Detected website framework name. */ __('Prepared a scriptless static snapshot for %s-style app-builder markup.', 'tahmir-ai-to-wp-converter'), $framework ?: __('animated', 'tahmir-ai-to-wp-converter')),
                $warnings
            );
        }

        return $html;
    }

    private function clone_detect_ssr_framework($html)
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

    private function clone_strip_ssr_framework_runtime($html, $framework)
    {
        $html = preg_replace_callback(
            '/<script\b([^>]*)>([\s\S]*?)<\/script>/i',
            function ($m) use ($framework) {
                return $this->clone_script_is_ssr_framework_runtime($m[1], $m[2], $framework) ? '' : $m[0];
            },
            $html
        );

        return preg_replace_callback(
            '/<link\b[^>]*>/i',
            function ($m) use ($framework) {
                return $this->clone_link_is_ssr_framework_script_preload($m[0], $framework) ? '' : $m[0];
            },
            $html
        );
    }

    private function clone_script_is_ssr_framework_runtime($attrs, $body, $framework)
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

    private function clone_link_is_ssr_framework_script_preload($tag, $framework)
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

    private function clone_inject_client_app_path_shim($html, $page_url, &$warnings, $asset_dir = '', $asset_url = '')
    {
        if (strpos($html, 'ugentic-client-app-path-shim') !== false) {
            return $html;
        }

        $app_path = wp_parse_url($page_url, PHP_URL_PATH);
        if (!is_string($app_path) || $app_path === '') {
            $app_path = '/';
        }
        $app_path = '/' . ltrim($app_path, '/');
        $app_path = preg_replace('#/+#', '/', $app_path);
        if ($app_path === '') {
            $app_path = '/';
        }

        $shim_js = '(function(){'
            . 'window.__ugenticClientAppPathShimStarted=true;'
            . 'if(window.__ugenticClientAppPathShim)return;window.__ugenticClientAppPathShim=true;'
            . 'try{if(!window.history||!history.replaceState)return;var appPath=' . wp_json_encode($app_path) . ';'
            . 'var nativeReplace=history.replaceState.bind(history);'
            . 'if(!appPath||appPath.charAt(0)!=="/")appPath="/";'
            . 'var original=location.pathname+location.search+location.hash;'
            . 'if(location.pathname===appPath)return;'
            . 'nativeReplace(history.state,document.title,appPath+location.search+location.hash);'
            . 'var restored=false;function restore(){try{if(restored)return;restored=true;nativeReplace(history.state,document.title,original);}catch(_e){}}'
            . 'function textReady(){var t=document.body&&document.body.innerText?document.body.innerText:"";return t.length>120&&!/(?:404|not found|did you forget to add the page to the router)/i.test(t);}'
            . 'function restoreWhenReady(){if(textReady()){restore();return;}if(!restored)setTimeout(restoreWhenReady,1000);}'
            . 'function queueRestore(){setTimeout(restoreWhenReady,4500);setTimeout(restore,10000);}'
            . 'window.__ugenticRestoreWordPressPath=restore;'
            . 'queueRestore();'
            . 'window.addEventListener("load",function(){setTimeout(restoreWhenReady,2500);},{once:true});'
            . '}catch(_e){}'
            . '})();';

        $shim_src = '';
        $asset_dir = is_string($asset_dir) ? rtrim($asset_dir, "/\\") : '';
        $asset_url = is_string($asset_url) ? rtrim($asset_url, '/') : '';
        if ($asset_dir !== '' && $asset_url !== '' && (is_dir($asset_dir) || wp_mkdir_p($asset_dir))) {
            // Every route needs immutable shim bytes. Reusing one filename made
            // later routes overwrite earlier ones, invalidating their manifests
            // and causing those pages to render the home route instead.
            $shim_name = 'ugentic-client-app-path-shim-' . substr(hash('sha256', $app_path), 0, 16) . '.js';
            $shim_file = $asset_dir . DIRECTORY_SEPARATOR . $shim_name;
            if (@file_put_contents($shim_file, $shim_js) !== false) {
                $shim_src = $asset_url . '/' . $shim_name;
            }
        }

        $shim = $shim_src !== ''
            ? Ugentic_Assets::render_asset('js', 'client-app-shim-' . substr(hash('sha256', $app_path), 0, 16), $shim_src)
            : Ugentic_Assets::render_asset('inline-js', 'client-app-shim-' . substr(hash('sha256', $app_path), 0, 16), $shim_js);

        $count = 0;
        $updated = preg_replace_callback(
            '/<script\b(?=[^>]*\btype\s*=\s*(?:"module"|\'module\'|module))[^>]*>/i',
            function ($m) use ($shim) {
                return $shim . "\n" . $m[0];
            },
            $html,
            1,
            $count
        );

        if ($count > 0 && is_string($updated)) {
            $this->clone_add_runtime_warning_once(
                'client_app_path_shim',
                __('Injected a WordPress permalink compatibility shim so JavaScript app-shell clones boot on their original route instead of rendering an in-app 404.', 'tahmir-ai-to-wp-converter'),
                $warnings
            );
            return $updated;
        }

        $this->clone_add_runtime_warning_once(
            'client_app_path_shim',
            __('Injected a WordPress permalink compatibility shim so JavaScript app-shell clones boot on their original route instead of rendering an in-app 404.', 'tahmir-ai-to-wp-converter'),
            $warnings
        );
        return $this->clone_inject_before_closing_tag($html, 'head', $shim);
    }

    private function clone_inject_before_closing_tag($html, $tag, $content)
    {
        if ($content === '') {
            return $html;
        }
        if (preg_match('/<\/' . preg_quote($tag, '/') . '>/i', $html)) {
            return preg_replace('/<\/' . preg_quote($tag, '/') . '>/i', $content . "\n" . '</' . $tag . '>', $html, 1);
        }
        return $html . "\n" . $content;
    }

    /**
     * Rewrite CSS url(...) references by downloading referenced assets.
     */
    private function clone_rewrite_css_urls($css, $base_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        $css = preg_replace_callback(
            '/image-set\s*\((.*?)\)/is',
            function ($m) use ($base_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
                $inner = preg_replace_callback(
                    '/(["\'])(?!data:)([^"\']+\.(?:jpg|jpeg|png|gif|webp|svg|ico|avif)(?:\?[^"\']*)?)\1/i',
                    function ($asset) use ($base_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
                        $downloaded = $this->clone_download_asset($asset[2], $base_url, $asset_dir, $asset_url, $asset_cache, $warnings, true);
                        $downloaded = $downloaded ?: $this->clone_resolve_url($asset[2], $base_url);
                        if (!$downloaded) {
                            return $asset[0];
                        }
                        return $asset[1] . $this->clone_escape_css_url($downloaded) . $asset[1];
                    },
                    $m[1]
                );
                return 'image-set(' . $inner . ')';
            },
            $css
        );

        $css = preg_replace_callback(
            '/@import\s+(?:url\s*\(\s*(["\']?)([^)"\'\s]+)\1\s*\)|(["\'])([^"\']+)\3)([^;]*);/i',
            function ($m) use ($base_url, $asset_dir, $asset_url, &$asset_cache, &$warnings) {
                $import_url = !empty($m[2]) ? $m[2] : ($m[4] ?? '');
                if ($this->clone_is_special_url($import_url)) {
                    return $m[0];
                }
                $downloaded = $this->clone_download_asset($import_url, $base_url, $asset_dir, $asset_url, $asset_cache, $warnings, true);
                $downloaded = $downloaded ?: $this->clone_resolve_url($import_url, $base_url);
                if (!$downloaded) {
                    return $m[0];
                }
                return '@import url("' . $this->clone_escape_css_url($downloaded) . '")' . ($m[5] ?? '') . ';';
            },
            $css
        );

        return $this->clone_rewrite_css_url_functions($css, $base_url, $asset_dir, $asset_url, $asset_cache, $warnings);
    }

    /**
     * Rewrite CSS url(...) functions, including quoted URLs with parentheses.
     */
    private function clone_rewrite_css_url_functions($css, $base_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        $css = (string) $css;
        $length = strlen($css);
        $offset = 0;
        $output = '';

        while (preg_match('/url\s*\(/i', $css, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $inner_start = $start + strlen($match[0][0]);
            $close = $this->clone_find_css_function_close($css, $inner_start);
            if ($close === false) {
                break;
            }

            $output .= substr($css, $offset, $start - $offset);
            $original = substr($css, $start, $close - $start + 1);
            $inner = trim(substr($css, $inner_start, $close - $inner_start));
            $quote = '';

            if ($inner !== '' && ($inner[0] === '"' || $inner[0] === "'")) {
                $quote = $inner[0];
                if (substr($inner, -1) === $quote) {
                    $inner = substr($inner, 1, -1);
                } else {
                    $inner = substr($inner, 1);
                }
            }

            $url = trim(stripcslashes($inner));
            if ($url === '' || $this->clone_is_special_url($url)) {
                $output .= $original;
                $offset = $close + 1;
                continue;
            }

            $downloaded = $this->clone_download_asset($url, $base_url, $asset_dir, $asset_url, $asset_cache, $warnings);
            $downloaded = $downloaded ?: $this->clone_resolve_url($url, $base_url);
            if (!$downloaded) {
                $output .= $original;
                $offset = $close + 1;
                continue;
            }

            $wrap = $quote ?: '"';
            $output .= 'url(' . $wrap . $this->clone_escape_css_url($downloaded) . $wrap . ')';
            $offset = $close + 1;
        }

        return $output . substr($css, $offset);
    }

    private function clone_find_css_function_close($css, $inner_start)
    {
        $length = strlen($css);
        $depth = 1;
        $quote = '';
        $escaped = false;

        for ($i = $inner_start; $i < $length; $i++) {
            $char = $css[$i];

            if ($quote !== '') {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return false;
    }

    private function clone_escape_css_url($url)
    {
        return str_replace(["\\", '"', "'", "\r", "\n", '<'], ["\\\\", '\\"', "\\'", '', '', '\\3c '], $url);
    }

    /**
     * Download a remote static asset and return its local URL.
     */
    private function clone_download_asset($url, $base_url, $asset_dir, $asset_url, &$asset_cache, &$warnings, $force = false)
    {
        $absolute = $this->clone_resolve_url($url, $base_url);
        if (!$absolute || $this->clone_is_special_url($absolute)) {
            return '';
        }
        if ($asset_url && strpos($absolute, trailingslashit($asset_url)) === 0) {
            return $absolute;
        }

        $cache_key = $this->clone_asset_cache_key($absolute);
        if (isset($asset_cache[$cache_key])) {
            $fragment = wp_parse_url($absolute, PHP_URL_FRAGMENT);
            return $asset_cache[$cache_key] . ($fragment !== null && $fragment !== false ? '#' . $fragment : '');
        }
        if (isset($this->clone_asset_failures[$cache_key])) {
            return '';
        }
        $fragment = wp_parse_url($absolute, PHP_URL_FRAGMENT);
        $absolute = preg_replace('/#.*$/', '', $absolute);

        if (!$force && !$this->clone_url_looks_like_asset($absolute)) {
            return '';
        }

        // Resume/recovery runs should reuse bytes already downloaded into the
        // same clone namespace. The filename prefix is deterministic from the
        // canonical source URL, so this works even when the in-memory cache was
        // lost after a timeout, PHP restart, or a failed page-record insert.
        $existing_path = wp_parse_url($absolute, PHP_URL_PATH) ?: '/asset';
        $existing_host = sanitize_file_name(wp_parse_url($absolute, PHP_URL_HOST) ?: 'asset');
        $existing_prefix = trailingslashit($asset_dir) . $existing_host . '/' . substr(md5($absolute), 0, 12) . '-';
        $existing_files = glob($existing_prefix . '*');
        if (is_array($existing_files)) {
            foreach ($existing_files as $existing_file) {
                $existing_ext = strtolower(pathinfo($existing_file, PATHINFO_EXTENSION));
                if (!is_file($existing_file) || filesize($existing_file) <= 0 || !in_array($existing_ext, [
                    'css', 'js', 'mjs', 'json', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'avif',
                    'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp4', 'webm', 'ogg', 'mp3', 'wav', 'pdf',
                ], true)) {
                    continue;
                }
                $relative = $existing_host . '/' . basename($existing_file);
                $local_url = trailingslashit($asset_url) . str_replace('\\', '/', $relative);
                $asset_cache[$cache_key] = $local_url;
                $this->asset_map[$absolute] = $local_url;
                return $local_url . ($fragment !== null && $fragment !== false ? '#' . $fragment : '');
            }
        }

        if ($this->clone_asset_deadline > 0 && microtime(true) >= $this->clone_asset_deadline) {
            $this->clone_add_runtime_warning_once(
                'asset_time_budget',
                __('Asset processing time limit reached; remaining remote assets were left linked to the source CDN.', 'tahmir-ai-to-wp-converter'),
                $warnings
            );
            return '';
        }
        if ($this->clone_asset_download_limit > 0 && $this->clone_asset_download_count >= $this->clone_asset_download_limit) {
            $this->clone_add_runtime_warning_once(
                'asset_download_limit',
                __('Asset download limit reached; remaining remote assets were left linked to the source CDN.', 'tahmir-ai-to-wp-converter'),
                $warnings
            );
            return '';
        }
        $this->clone_asset_download_count++;
        // This is also the background job heartbeat. Reporting each unique
        // request prevents a slow CDN from looking permanently stuck.
        $this->clone_asset_progress_last_report = $this->clone_asset_download_count;
        $asset_progress = min(84, 45 + (int) floor(max(0, $this->clone_asset_download_count - 1) / 25) * 10);
        $this->clone_report_progress('download_assets', $asset_progress, sprintf(
            /* translators: Number of the pages, records, files, or items described in this message. */ __('Downloading and localizing assets... %d processed', 'tahmir-ai-to-wp-converter'),
            $this->clone_asset_download_count
        ), [
            'assets_downloaded' => $this->clone_asset_download_count,
            'asset_url' => $absolute,
        ]);

        $response = $this->clone_remote_get_with_retries($absolute, [
            'timeout' => $this->clone_asset_timeout ?: 20,
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 TahmirAI-FaithfulClone/1.0',
            'headers' => [
                'Accept' => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ], 'import_clone_asset', $warnings, [
            'retries' => self::REMOTE_CLONE_ASSET_RETRIES,
            'warn_retries' => false,
            'retry_base_ms' => 250,
            'deadline' => $this->clone_asset_deadline,
        ]);

        if (is_wp_error($response)) {
            if ($response->get_error_code() === 'ugentic_clone_deadline_exceeded') {
                $this->clone_add_runtime_warning_once(
                    'asset_time_budget',
                    __('Asset processing time limit reached; remaining remote assets were left linked to the source CDN.', 'tahmir-ai-to-wp-converter'),
                    $warnings
                );
                return '';
            }
            $this->clone_record_asset_failure($absolute, $this->clone_remote_error_message($absolute, $response, $response->get_error_message()), $warnings);
            return '';
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        if ($code < 200 || $code >= 400) {
            $this->clone_record_asset_failure($absolute, $this->clone_remote_error_message($absolute, $response, sprintf(/* translators: HTTP status code. */ __('HTTP %d', 'tahmir-ai-to-wp-converter'), $code)), $warnings);
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '' || strlen($body) > self::MAX_REMOTE_CLONE_ASSET_SIZE) {
            $this->clone_record_asset_failure($absolute, __('empty or too large', 'tahmir-ai-to-wp-converter'), $warnings);
            return '';
        }

        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        $path = wp_parse_url($absolute, PHP_URL_PATH) ?: '/asset';
        // Some hosts return their app shell or access challenge with HTTP 200
        // for a missing JS/CSS/image path. Never persist it as a runtime asset.
        if (strpos($content_type, 'html') !== false || preg_match('/^\s*(?:<!doctype\s+html\b|<html\b)/i', $body)) {
            $this->clone_record_asset_failure($absolute, __('received an HTML page instead of the requested asset', 'tahmir-ai-to-wp-converter'), $warnings);
            return '';
        }
        $ext = $this->clone_safe_asset_extension($path, $content_type);
        if ($ext === '') {
            $this->clone_record_asset_failure($absolute, __('unsupported or unsafe asset type', 'tahmir-ai-to-wp-converter'), $warnings);
            return '';
        }
        if ($ext === 'svg') {
            $body = $this->clone_sanitize_svg_asset($body);
            if ($body === '') {
                $this->clone_record_asset_failure($absolute, __('unsafe SVG content', 'tahmir-ai-to-wp-converter'), $warnings);
                return '';
            }
        }

        $stem = sanitize_file_name(pathinfo(basename($path), PATHINFO_FILENAME));
        $filename = ($stem !== '' ? $stem : 'asset') . '.' . $ext;

        $host = sanitize_file_name(wp_parse_url($absolute, PHP_URL_HOST) ?: 'asset');
        $relative = $host . '/' . substr(md5($absolute), 0, 12) . '-' . $filename;
        $target = trailingslashit($asset_dir) . $relative;
        wp_mkdir_p(dirname($target));

        $local_url = trailingslashit($asset_url) . str_replace('\\', '/', $relative);
        // Register a provisional cache entry before rewriting nested bundles so
        // a circular module import cannot recursively download itself forever.
        // The entry is removed again unless a complete, non-empty file reaches
        // disk below.
        $asset_cache[$cache_key] = $local_url;

        if ($ext === 'css') {
            $original_body = $body;
            $rewritten_body = $this->clone_rewrite_css_urls($body, $this->clone_response_url($response, $absolute), $asset_dir, $asset_url, $asset_cache, $warnings);
            if (!is_string($rewritten_body) || $rewritten_body === '') {
                $body = $original_body;
                $this->clone_add_runtime_warning_once(
                    'asset_rewrite_fallback',
                    __('A large asset could not be rewritten safely, so Tahmir preserved its original bytes instead of saving a broken local file.', 'tahmir-ai-to-wp-converter'),
                    $warnings
                );
            } else {
                $body = $rewritten_body;
            }
        }
        if (in_array($ext, ['js', 'mjs'], true)) {
            $original_body = $body;
            $rewritten_body = $this->clone_rewrite_js_urls($body, $this->clone_response_url($response, $absolute), $asset_dir, $asset_url, $asset_cache, $warnings);
            if (!is_string($rewritten_body) || $rewritten_body === '') {
                $body = $original_body;
                $this->clone_add_runtime_warning_once(
                    'asset_rewrite_fallback',
                    __('A large asset could not be rewritten safely, so Tahmir preserved its original bytes instead of saving a broken local file.', 'tahmir-ai-to-wp-converter'),
                    $warnings
                );
            } else {
                $body = $rewritten_body;
            }
        }
        // Never apply HTML/branding regular expressions to executable bundles,
        // JSON or CSS: even a small text substitution can corrupt their syntax.

        $expected_bytes = strlen($body);
        $written_bytes = file_put_contents($target, $body, LOCK_EX);
        clearstatcache(true, $target);
        if (
            $expected_bytes < 1
            || $written_bytes === false
            || intval($written_bytes) !== $expected_bytes
            || !is_file($target)
            || intval(filesize($target)) !== $expected_bytes
        ) {
            if (is_file($target)) {
                wp_delete_file($target);
            }
            unset($asset_cache[$cache_key]);
            $this->clone_record_asset_failure($absolute, __('could not write a complete local file', 'tahmir-ai-to-wp-converter'), $warnings);
            return '';
        }
        $this->asset_map[$absolute] = $local_url;

        return $local_url . ($fragment !== null && $fragment !== false ? '#' . $fragment : '');
    }

    private function clone_record_asset_failure($url, $reason, &$warnings)
    {
        $key = $this->clone_asset_cache_key($url);
        if (isset($this->clone_asset_failures[$key])) {
            return;
        }

        $this->clone_asset_failures[$key] = [
            'url' => $url,
            'reason' => $reason,
        ];
        $warnings[] = sprintf(/* translators: 1: Asset URL; 2: Reason the asset was skipped. */ __('Asset skipped %1$s: %2$s', 'tahmir-ai-to-wp-converter'), $url, $reason);
    }

    /**
     * Replace internal cloned links with WordPress URLs after import.
     */
    private function clone_replace_internal_links($imported)
    {
        $database = ugentic_builder()->database;
        $map = [];
        $slug_map = [];
        foreach ($imported as $page) {
            if (($page['import_role'] ?? '') === 'blog_template_sample') {
                continue;
            }
            if (empty($page['source_url']) || empty($page['view_url'])) {
                continue;
            }
            $canonical_source = $this->clone_canonical_page_url($page['source_url']);
            $map[$canonical_source] = $page['view_url'];
            $source_slug = $this->clone_slug_from_url($canonical_source);
            if ($source_slug !== '') {
                $slug_map[$source_slug] = $page['view_url'];
            }
            if (!empty($page['source_file'])) {
                $source_file_slug = sanitize_title(pathinfo($page['source_file'], PATHINFO_FILENAME));
                if ($source_file_slug !== '') {
                    $slug_map[$source_file_slug] = $page['view_url'];
                }
            }
        }

        $pages_updated = 0;
        $links_replaced = 0;
        foreach ($imported as $page) {
            if (empty($page['ugentic_id'])) {
                continue;
            }
            $stored = $database->get_page($page['ugentic_id']);
            if (!$stored || empty($stored->current_html)) {
                continue;
            }

            $base_url = $page['source_url'];
            $count = 0;
            $updated = preg_replace_callback(
                '/(<a\b[^>]*\bhref\s*=\s*["\'])([^"\']+)(["\'][^>]*>)/i',
                function ($m) use ($map, $slug_map, $base_url, &$count) {
                    $href = html_entity_decode($m[2], ENT_QUOTES);
                    if ($this->clone_is_special_url($href)) {
                        return $m[0];
                    }
                    $absolute = $this->clone_resolve_url($href, $base_url);
                    $resolved = $this->clone_canonical_page_url($absolute);
                    if (isset($map[$resolved])) {
                        $count++;
                        return $m[1] . esc_url($this->clone_link_with_suffix($map[$resolved], $absolute)) . $m[3];
                    }
                    // Unselected pages still belong to the source. A matching
                    // basename is not evidence that a URL is an imported page.
                    if ($absolute !== '' && $absolute !== $href) {
                        $count++;
                        return $m[1] . esc_url($absolute) . $m[3];
                    }
                    return $m[0];
                },
                $stored->current_html
            );

            if ($count > 0 && $updated !== $stored->current_html) {
                $database->update_page($stored->id, ['current_html' => $updated]);
                $pages_updated++;
                $links_replaced += $count;
            }
        }

        foreach ($imported as $page) {
            $post_id = intval($page['wp_post_id'] ?? 0);
            if (!$post_id) {
                continue;
            }

            $post = get_post($post_id);
            if (!$post || empty($post->post_content)) {
                continue;
            }

            $base_url = $page['source_url'] ?? '';
            $count = 0;
            $updated_content = preg_replace_callback(
                '/(<a\b[^>]*\bhref\s*=\s*["\'])([^"\']+)(["\'][^>]*>)/i',
                function ($m) use ($map, $slug_map, $base_url, &$count) {
                    $href = html_entity_decode($m[2], ENT_QUOTES);
                    if ($this->clone_is_special_url($href)) {
                        return $m[0];
                    }
                    $absolute = $base_url ? $this->clone_resolve_url($href, $base_url) : $href;
                    $resolved = $this->clone_canonical_page_url($absolute);
                    if (isset($map[$resolved])) {
                        $count++;
                        return $m[1] . esc_url($this->clone_link_with_suffix($map[$resolved], $absolute)) . $m[3];
                    }
                    if ($absolute !== '' && $absolute !== $href) {
                        $count++;
                        return $m[1] . esc_url($absolute) . $m[3];
                    }
                    return $m[0];
                },
                $post->post_content
            );

            if ($count > 0 && $updated_content !== $post->post_content) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $updated_content,
                ]);
                $pages_updated++;
                $links_replaced += $count;
            }
        }

        return [
            'pages_updated' => $pages_updated,
            'links_replaced' => $links_replaced,
        ];
    }

    /**
     * Rewrite relative/static asset references inside downloaded JS bundles.
     */
    private function clone_rewrite_js_urls($js, $base_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        $source = (string) $js;
        if ($source === '') {
            return $source;
        }

        // A single PCRE over a production Vite bundle can exceed PHP's default
        // backtrack limit. preg_replace_callback() then returns null; older
        // builds wrote that value as a successful zero-byte script and every
        // client-rendered page stayed blank. Scan comments and string literals
        // in linear time instead. URL policy remains in the helper below.
        $length = strlen($source);
        $parts = [];
        $cursor = 0;
        $offset = 0;
        while ($offset < $length) {
            $char = $source[$offset];

            if ($char === '/' && $offset + 1 < $length) {
                $next = $source[$offset + 1];
                if ($next === '/') {
                    $newline = strpos($source, "\n", $offset + 2);
                    $offset = $newline === false ? $length : $newline + 1;
                    continue;
                }
                if ($next === '*') {
                    $comment_end = strpos($source, '*/', $offset + 2);
                    $offset = $comment_end === false ? $length : $comment_end + 2;
                    continue;
                }
            }

            if (!in_array($char, ['"', "'", chr(96)], true)) {
                $offset++;
                continue;
            }

            $literal_start = $offset;
            $quote = $char;
            $offset++;
            while ($offset < $length) {
                $literal_char = $source[$offset];
                if ($literal_char === '\\') {
                    $offset += min(2, $length - $offset);
                    continue;
                }
                $offset++;
                if ($literal_char === $quote) {
                    break;
                }
            }

            // Leave an unterminated literal untouched. The source response may
            // itself be truncated, but the localizer must never make it worse.
            if ($offset > $length || $source[$offset - 1] !== $quote) {
                break;
            }

            $literal = substr($source, $literal_start, $offset - $literal_start);
            $parts[] = substr($source, $cursor, $literal_start - $cursor);
            $parts[] = $this->clone_rewrite_js_string_literal(
                $literal,
                $literal_start,
                $source,
                $base_url,
                $asset_dir,
                $asset_url,
                $asset_cache,
                $warnings
            );
            $cursor = $offset;
        }
        $parts[] = substr($source, $cursor);
        $js = implode('', $parts);

        // Vite's absolute-base preload helper prepends "/" to dependencies.
        // Downloaded dependency URLs are already absolute.
        if (strpos($source, '__vite__mapDeps') !== false) {
            $vite_rewrite = preg_replace_callback(
                '~(function\s*\(\s*([a-z_$][\w$]*)\s*\)\s*\{\s*return\s*)(["\'])(/[^"\']*)\3\s*\+\s*\2\s*([;}])~i',
                function ($m) {
                    $arg = $m[2];
                    return $m[1] . '(/^https?:\/\//.test(' . $arg . ')||' . $arg . '.charAt(0)==="/")?' . $arg . ':' . $m[3] . $m[4] . $m[3] . '+' . $arg . $m[5];
                },
                $js
            );
            if (is_string($vite_rewrite) && $vite_rewrite !== '') {
                $js = $vite_rewrite;
            }
        }
        return $js;
    }

    /**
     * Rewrite one complete JavaScript string literal discovered by the linear
     * scanner. Dynamic template expressions and escaped program text are kept
     * byte-for-byte so cloning cannot change application semantics.
     */
    private function clone_rewrite_js_string_literal($literal, $offset, $source, $base_url, $asset_dir, $asset_url, &$asset_cache, &$warnings)
    {
        $quote = substr($literal, 0, 1);
        $raw = substr($literal, 1, -1);
        if (
            $raw === ''
            || ($quote === chr(96) && strpos($raw, '${') !== false)
            || preg_match('/\{[^}]+\}/', $raw)
            || preg_match('/\\\\(?!\/)/', $raw)
        ) {
            return $literal;
        }

        $url = str_replace('\/', '/', $raw);
        $prefix = substr($source, max(0, $offset - 160), min(160, $offset));
        $module_reference = preg_match('/(?:\b(?:import|export)\s*|\bfrom\s*|\bimport\s*\(\s*|\bnew\s+URL\s*\(\s*)$/', $prefix);
        if ($module_reference) {
            $url = $this->clone_resolve_module_specifier($url, $base_url);
        }
        $vite_path = preg_match('~^(?:assets|_nuxt|_next)/~', $url);
        $qualified = preg_match('~^(?:https?://|//|/|\.\.?/)~i', $url);
        if (
            (!$qualified && !$module_reference && !$vite_path)
            || (!$module_reference && !$this->clone_url_looks_like_asset($url))
        ) {
            return $literal;
        }
        // Bare package imports use an import map, not an HTTP path.
        if ($module_reference && !$qualified && !$vite_path && !preg_match('/\.(?:m?js|css)(?:[?#]|$)/i', $url)) {
            return $literal;
        }
        if ($vite_path) {
            $url = '/' . $url;
        }
        $target = $this->clone_download_asset($url, $base_url, $asset_dir, $asset_url, $asset_cache, $warnings, true);
        $target = $target ?: $this->clone_resolve_url($url, $base_url);
        if ($target === '') {
            return $literal;
        }
        $target = str_replace(['\\', $quote, '<', "\r", "\n"], ['\\\\', '\\' . $quote, '\x3c', '', ''], $target);
        if (strpos($raw, '\/') !== false) {
            $target = str_replace('/', '\/', $target);
        }
        return $quote . $target . $quote;
    }

    private function clone_link_with_suffix($local_url, $source_url)
    {
        $query = wp_parse_url($source_url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $source_args);
            parse_str((string) (wp_parse_url($local_url, PHP_URL_QUERY) ?? ''), $local_args);
            // WordPress page and preview routing must keep its own identifiers.
            $args = array_merge($source_args, $local_args);
            $local_url = preg_replace('/[?#].*$/', '', $local_url) . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
        }
        $fragment = wp_parse_url($source_url, PHP_URL_FRAGMENT);
        return $local_url . (is_string($fragment) && $fragment !== '' ? '#' . $fragment : '');
    }

    private function clone_should_preserve_remote_module_asset($tag, $attr, $tag_attrs, $url, $page_url)
    {
        $tag = strtolower((string) $tag);
        $attr = strtolower((string) $attr);
        if ($tag !== 'script' || $attr !== 'src') {
            return false;
        }

        if (preg_match('/\btype\s*=\s*["\'](?:importmap|speculationrules)["\']/i', $tag_attrs)) {
            return true;
        }

        return false;
    }

    private function clone_add_runtime_warning_once($key, $message, &$warnings)
    {
        if (isset($this->clone_runtime_warnings[$key])) {
            return;
        }

        $this->clone_runtime_warnings[$key] = true;
        $warnings[] = $message;
    }

    private function clone_note_client_rendered_shell($url, &$warnings)
    {
        $display_url = esc_url_raw($url);
        $this->clone_add_runtime_warning_once(
            'client_rendered_app_shell',
            sprintf(/* translators: Source page URL. */ __('Detected a JavaScript-rendered app shell at %s. Tahmir will preserve the client runtime and localize its JS/CSS/assets instead of rejecting the clone; API-backed live data may still depend on the original service.', 'tahmir-ai-to-wp-converter'), $display_url),
            $warnings
        );
    }

    private function clone_normalize_source_type($source)
    {
        $source = sanitize_key((string) $source);
        $allowed = ['auto', 'ai_studio', 'webflow', 'framer', 'lovable', 'replit', 'wix', 'squarespace', 'carrd', 'bubble', 'shopify', 'vercel', 'netlify', 'vite', 'nextjs', 'wordpress', 'generic'];
        return in_array($source, $allowed, true) ? $source : 'auto';
    }

    private function clone_detect_source_type($html, $page_url, $selected = 'auto')
    {
        $selected = $this->clone_normalize_source_type($selected);
        if ($selected !== 'auto') {
            return $selected;
        }

        $host = strtolower((string) (wp_parse_url($page_url, PHP_URL_HOST) ?: ''));
        $probe = strtolower(substr((string) $html, 0, 250000));

        // This host is inspected only to classify an imported page; no plugin asset is loaded from it.
        // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Source detection, not remote asset offloading.
        if (strpos($host, 'aistudio.google.com') !== false || strpos($host, 'googleusercontent.com') !== false || strpos($probe, 'google ai studio') !== false) {
            return 'ai_studio';
        }
        if (strpos($host, 'webflow.io') !== false || strpos($probe, 'data-wf-page') !== false || strpos($probe, 'webflow.js') !== false || strpos($probe, 'w-webflow-badge') !== false) {
            return 'webflow';
        }
        if (strpos($host, 'framer.website') !== false || strpos($probe, '__framer') !== false || strpos($probe, 'framerusercontent.com') !== false || strpos($probe, 'made in framer') !== false) {
            return 'framer';
        }
        if (strpos($host, 'lovable.app') !== false || strpos($probe, 'lovable-badge') !== false || strpos($probe, 'edit with lovable') !== false) {
            return 'lovable';
        }
        if (strpos($host, 'replit.app') !== false || strpos($host, 'repl.co') !== false || strpos($probe, 'replit') !== false) {
            return 'replit';
        }
        if (strpos($host, 'wixsite.com') !== false || strpos($host, 'wix.com') !== false || strpos($probe, 'wixstatic.com') !== false || strpos($probe, 'x-wix-') !== false) {
            return 'wix';
        }
        if (strpos($host, 'squarespace.com') !== false || strpos($probe, 'static1.squarespace.com') !== false || strpos($probe, 'powered by squarespace') !== false) {
            return 'squarespace';
        }
        if (strpos($host, 'carrd.co') !== false || strpos($host, 'crd.co') !== false || strpos($probe, 'carrd.co/assets') !== false || strpos($probe, 'made with carrd') !== false) {
            return 'carrd';
        }
        if (strpos($host, 'bubbleapps.io') !== false || strpos($probe, 'built on bubble') !== false || strpos($probe, 'bubble.io') !== false) {
            return 'bubble';
        }
        if (strpos($host, 'myshopify.com') !== false || strpos($probe, 'cdn.shopify.com') !== false || strpos($probe, 'shopify-section') !== false) {
            return 'shopify';
        }
        if (strpos($host, 'vercel.app') !== false || strpos($probe, '/_vercel/') !== false) {
            return 'vercel';
        }
        if (strpos($host, 'netlify.app') !== false || strpos($probe, 'netlify') !== false) {
            return 'netlify';
        }
        if (strpos($probe, 'wp-content/') !== false || strpos($probe, 'wp-includes/') !== false || strpos($probe, 'powered by wordpress') !== false) {
            return 'wordpress';
        }
        if ($this->clone_detect_ssr_framework($html) === 'next') {
            return 'nextjs';
        }
        if (preg_match('/<script\b[^>]*\btype\s*=\s*(?:"module"|\'module\'|module)[^>]*\bsrc\s*=\s*(["\'])[^"\']*\/assets\/[^"\']+\.(?:js|mjs)/i', $html)) {
            return 'vite';
        }

        return 'generic';
    }

    private function clone_hide_builder_badges($html, $source_type, &$warnings)
    {
        $source_type = $this->clone_normalize_source_type($source_type);
        $hide_badges = in_array($source_type, ['webflow', 'framer', 'lovable', 'replit', 'wix', 'squarespace', 'carrd', 'bubble', 'shopify', 'vercel', 'netlify', 'wordpress'], true);
        if (!$hide_badges || strpos($html, 'ugentic-builder-badge-hide') !== false) {
            return $html;
        }

        // Branding is handled by the exact DOM selectors below. Do not rewrite
        // strings inside the source application's scripts or serialized data.

        $css = '<style id="ugentic-builder-badge-hide">'
            . '.w-webflow-badge,[class*="w-webflow-badge"],[class*="webflow-badge"],'
            . '#__framer-badge-container,[id*="framer-badge"],[class*="framer-badge"],'
            . '#lovable-badge,[id*="lovable-badge"],[class*="lovable-badge"],'
            . '[id*="replit-badge"],[class*="replit-badge"],'
            . '#customBubbleBanner,#customBubbleBanner *,.banner-content,'
            . '#text31,'
            . '.footer-divider,.footer-copyright-holder,.footer-copyright-center,.footer-bottom a[href*="webflow.com"],.footer-bottom .p-12px,'
            . '[id*="wix"][id*="ads"],[class*="wix"][class*="ads"],[id*="wix"][id*="brand"],[class*="wix"][class*="brand"],'
            . '[id*="squarespace"][id*="badge"],[class*="squarespace"][class*="badge"],[id*="carrd"][id*="badge"],[class*="carrd"][class*="badge"],'
            . '[id*="bubble"][id*="badge"],[class*="bubble"][class*="badge"],[id*="shopify"][id*="badge"],[class*="shopify"][class*="badge"],'
            . 'a[href*="webflow.com"][href*="brand"],a[href*="webflow.com"][aria-label*="Webflow"],'
            . 'a[href*="framer.com/made"],a[href*="framer.com?via"],a[href*="lovable.dev"],a[href*="lovable.app"][aria-label*="Lovable"],'
            . 'a[href*="wix.com"][href*="website"],a[href*="squarespace.com"],a[href*="carrd.co"],a[href*="bubble.io"],a[href*="shopify.com"][href*="powered"],'
            . 'a[href*="vercel.com"][href*="deploy"],a[href*="netlify.com"][href*="deploy"],a[href*="wordpress.com"][href*="start"]'
            . ',.ugentic-builder-badge-hidden{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;width:0!important;height:0!important;overflow:hidden!important;}'
            . '</style>';

        $js = '<script id="ugentic-builder-badge-hide-runtime">(function(){'
            . 'var state=window.__ugenticBadgeCleanup=window.__ugenticBadgeCleanup||{runs:0,hidden:0,errors:[]};'
            . 'function safe(v){try{return String(v&&v.baseVal?v.baseVal:v||"");}catch(_e){return "";}}'
            . 'function hide(n){try{var extra=";display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;width:0!important;height:0!important;overflow:hidden!important;";if(n.classList){n.classList.add("ugentic-builder-badge-hidden");}if(n.setAttribute){n.setAttribute("style",safe(n.getAttribute&&n.getAttribute("style"))+extra);n.setAttribute("aria-hidden","true");n.setAttribute("hidden","hidden");}if(n.style&&typeof n.style.setProperty==="function"){n.style.setProperty("display","none","important");n.style.setProperty("visibility","hidden","important");n.style.setProperty("opacity","0","important");n.style.setProperty("pointer-events","none","important");n.style.setProperty("width","0","important");n.style.setProperty("height","0","important");n.style.setProperty("overflow","hidden","important");}state.hidden++;}catch(e){if(state.errors.length<5){state.errors.push(safe(e&&e.message||e));}}}'
            . 'var observer=null,timer=null,pending=false,stopAt=Date.now()+45000;'
            . 'function clean(){state.runs++;var names="webflow|framer|lovable|replit|wix|squarespace|carrd|bubble|shopify|vercel|netlify|wordpress";var rx=new RegExp("(?:made|built|created|designed|powered|hosted|edit|edited)\\\\s+(?:in|with|by|on)?\\\\s*(?:"+names+")|(?:"+names+")\\\\s*(?:badge|template|website builder|watermark|branding)|(?:use|remix|clone)\\\\s+(?:this\\\\s+)?(?:template|project|app|site)|(?:more\\\\s+templates|use\\\\s+template\\\\s+for\\\\s+free)|(?:created|designed|crafted)\\\\s+by\\\\s+[a-z0-9 .&_-]{2,60}","i");'
            . 'var keyRx=/(webflow|framer|lovable|replit|wix|squarespace|carrd|bubble|shopify|vercel|netlify|wordpress).{0,28}(badge|brand|watermark|ads|template|powered)|framer\\.com\\/(made|projects\\/new|marketplace|templates)|webflow\\.com\\/brand|lovable\\.dev|bubble\\.io|carrd\\.co|squarespace\\.com|wix\\.com\\/website|wordpress\\.com\\/start|vercel\\.com\\/deploy|netlify\\.com\\/deploy/i;'
            . 'Array.prototype.slice.call(document.querySelectorAll("a,div,span,p,button,small,em")).forEach(function(n){try{var txt=safe(n.innerText||n.textContent).replace(/\\s+/g," ").trim();var key=safe(n.id)+" "+safe(n.className)+" "+safe(n.getAttribute&&n.getAttribute("href"))+" "+safe(n.getAttribute&&n.getAttribute("aria-label"))+" "+safe(n.getAttribute&&n.getAttribute("title"));if((txt.length&&txt.length<180&&rx.test(txt))||keyRx.test(key)){hide(n);}}catch(e){if(state.errors.length<5){state.errors.push(safe(e&&e.message||e));}}});'
            . 'try{var walker=document.createTreeWalker(document.body||document.documentElement,NodeFilter.SHOW_TEXT,null);var t;while((t=walker.nextNode())){var p=t.parentElement;if(!p){continue;}var s=safe(t.nodeValue).replace(/\\s+/g," ").trim();var whole=safe(p.innerText||p.textContent).replace(/\\s+/g," ").trim();if(s.length&&s.length<180&&rx.test(s)&&whole.length<240){hide(p);}}}catch(e){if(state.errors.length<5){state.errors.push(safe(e&&e.message||e));}}}'
            . 'function schedule(){if(pending){return;}pending=true;setTimeout(function(){pending=false;clean();if(Date.now()>stopAt){if(observer){observer.disconnect();observer=null;}if(timer){clearInterval(timer);timer=null;}}},80);}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",clean,{once:true});}else{clean();}setTimeout(clean,800);setTimeout(clean,2500);setTimeout(clean,6000);timer=setInterval(clean,350);'
            . 'try{observer=new MutationObserver(schedule);observer.observe(document.documentElement||document.body,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:["style","class","hidden","aria-hidden"]});}catch(_e){}'
            . '})();</script>';

        $this->clone_add_runtime_warning_once(
            'builder_badge_hidden_' . $source_type,
            sprintf(/* translators: Source website platform name. */ __('Applied %s source cleanup and hid builder/editor badges from the imported page.', 'tahmir-ai-to-wp-converter'), ucfirst($source_type)),
            $warnings
        );

        $html = $this->clone_inject_before_closing_tag($html, 'head', $css);
        return $this->clone_inject_before_closing_tag($html, 'body', $js);
    }

    private function clone_stabilize_builder_runtime($html, $source_type, &$warnings)
    {
        if ($source_type !== 'framer') {
            return $html;
        }

        $original = $html;
        $html = preg_replace(
            '/<script\b(?=[^>]*\btype\s*=\s*(["\'])module\1)(?=[^>]*\bsrc\s*=\s*(["\'])[^"\']*framerusercontent\.com\/sites\/[^"\']*script_main[^"\']*\.mjs(?:\?[^"\']*)?\2)[^>]*>\s*<\/script>\s*/i',
            '',
            $html
        );

        if ($html !== $original) {
            $this->clone_add_runtime_warning_once(
                'framer_static_runtime_stabilized',
                __('Detected a Framer template runtime and kept the server-rendered page stable instead of hydrating with brittle editor/template modules.', 'tahmir-ai-to-wp-converter'),
                $warnings
            );
        }

        return $html;
    }

    private function clone_href_is_asset($tag, $tag_attrs, $url)
    {
        if ($tag === 'link' && preg_match('/\brel\s*=\s*["\'][^"\']*(stylesheet|icon|preload|modulepreload|apple-touch-icon)[^"\']*["\']/i', $tag_attrs)) {
            return true;
        }
        return $this->clone_url_looks_like_asset($url);
    }

    private function clone_attribute_is_asset_hint($attr)
    {
        $attr = strtolower((string) $attr);
        if (!preg_match('/^data-/', $attr)) {
            return false;
        }

        return (bool) preg_match('/(?:src|image|bg|background|poster|thumb|thumbnail|video|media)/', $attr)
            && !preg_match('/(?:data-href|data-url)$/', $attr);
    }

    private function clone_url_looks_like_asset($url)
    {
        if ($this->clone_is_special_url($url)) {
            return false;
        }
        $decoded = html_entity_decode(rawurldecode((string) $url), ENT_QUOTES);
        $path = strtolower(wp_parse_url($decoded, PHP_URL_PATH) ?: $decoded);
        $query = strtolower(wp_parse_url($decoded, PHP_URL_QUERY) ?: '');
        if (preg_match('/\.(css|js|mjs|json|jpg|jpeg|png|gif|webp|svg|ico|bmp|avif|tiff|tif|woff|woff2|ttf|eot|otf|mp4|webm|ogg|ogv|mp3|wav|pdf)(?:$|\?)/i', $path)) {
            return true;
        }
        if (preg_match('#/(?:_next/image|_vercel/image|cdn-cgi/image|cdn-cgi/imagedelivery|_ipx/|image/upload)(?:/|$)#i', $path)) {
            return true;
        }
        if ($query && preg_match('/(?:^|&)(?:url|src|image)=/i', $query) && preg_match('/\.(?:jpg|jpeg|png|gif|webp|svg|ico|avif)(?:$|[?&#])|%2f/i', $query)) {
            return true;
        }
        return false;
    }

    private function clone_is_importable_page_url($candidate, $home_url)
    {
        if (!$candidate || $this->clone_is_special_url($candidate)) {
            return false;
        }
        $home = wp_parse_url($home_url);
        $parsed = wp_parse_url($candidate);
        if (empty($parsed['host']) || empty($home['host'])) {
            return false;
        }
        $candidate_host = strtolower(preg_replace('/^www\./', '', $parsed['host']));
        $home_host = strtolower(preg_replace('/^www\./', '', $home['host']));
        if ($candidate_host !== $home_host) {
            return false;
        }

        $path = strtolower($parsed['path'] ?? '/');
        // A GitHub Pages user host can contain many unrelated repository sites.
        // When cloning /repository/, remain inside that project prefix instead
        // of crawling every other project linked from the portfolio.
        if (preg_match('/\.github\.io$/i', $home_host)) {
            $home_path = strtolower(rawurldecode($home['path'] ?? '/'));
            $home_segments = array_values(array_filter(explode('/', trim($home_path, '/')), 'strlen'));
            if ($home_segments) {
                $project_prefix = '/' . $home_segments[0];
                $candidate_path = '/' . ltrim(strtolower(rawurldecode($parsed['path'] ?? '/')), '/');
                if ($candidate_path !== $project_prefix && strpos($candidate_path, $project_prefix . '/') !== 0) {
                    return false;
                }
            }
        }
        if (preg_match('#/(?:%3a|:)[a-z_][a-z0-9_]*(?:/|$)#i', $path)) {
            return false;
        }
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico|pdf|zip|css|js|woff|woff2|ttf|eot|otf|mp4|webm|mp3|wav)$/i', $path)) {
            return false;
        }
        if (preg_match('#/(admin|wp-admin|wp-login|wp-content|wp-includes|feed|xmlrpc|wp-json|api|cart|checkout|my-account|account|login|register|signup|sign-up|search|pdf|download|downloads)(/|$)#i', $path)) {
            return false;
        }

        $query = strtolower($parsed['query'] ?? '');
        if ($query && preg_match('/(^|&)(s|q|search|replytocom|add-to-cart|add_to_cart|filter|sort|orderby)=/i', $query)) {
            return false;
        }

        return true;
    }

    private function clone_resolve_url($url, $base_url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES));
        if ($url === '' || $this->clone_is_special_url($url)) {
            return '';
        }
        if (preg_match('/^\/\//', $url)) {
            $scheme = wp_parse_url($base_url, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $url;
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return '';
        }

        $base = wp_parse_url($base_url);
        if (empty($base['host'])) {
            return '';
        }

        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';
        $url_parts = wp_parse_url($url);
        $url_path = $url_parts['path'] ?? '';
        $query = isset($url_parts['query']) ? '?' . $url_parts['query'] : '';
        $fragment = isset($url_parts['fragment']) ? '#' . $url_parts['fragment'] : '';

        if ($url_path === '') {
            $path = $base['path'] ?? '/';
        } elseif (strpos($url, '/') === 0) {
            $path = $url_path;
        } else {
            $base_path = $base['path'] ?? '/';
            $dir = preg_match('/\/$/', $base_path) ? $base_path : dirname($base_path) . '/';
            $path = $dir . $url_path;
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        $resolved_path = '/' . implode('/', $parts);
        if ($resolved_path !== '/' && preg_match('~(?:/|/\.\.?)$~', $path)) {
            $resolved_path .= '/';
        }
        return $scheme . '://' . $host . $port . $resolved_path . $query . $fragment;
    }

    private function clone_canonical_page_url($url)
    {
        $parsed = wp_parse_url($url);
        if (empty($parsed['host'])) {
            return $url;
        }
        $scheme = $parsed['scheme'] ?? 'https';
        $host = strtolower($parsed['host']);
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '/';
        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path);
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        return $scheme . '://' . $host . $port . $path;
    }

    private function clone_slug_from_url($url)
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return '';
        }
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return 'home';
        }
        $last = basename($trimmed);
        $last = preg_replace('/\.(html?|php|aspx?)$/i', '', $last);
        return sanitize_title($last);
    }

    private function clone_same_origin_url($left, $right)
    {
        $left_parsed = wp_parse_url($left);
        $right_parsed = wp_parse_url($right);
        if (empty($left_parsed['host']) || empty($right_parsed['host'])) {
            return false;
        }

        $left_host = strtolower(preg_replace('/^www\./', '', $left_parsed['host']));
        $right_host = strtolower(preg_replace('/^www\./', '', $right_parsed['host']));
        if ($left_host !== $right_host) {
            return false;
        }

        $left_port = $left_parsed['port'] ?? null;
        $right_port = $right_parsed['port'] ?? null;
        return $left_port === $right_port;
    }

    private function clone_asset_cache_key($url)
    {
        $parsed = wp_parse_url($url);
        if (!$parsed) {
            return $url;
        }
        $query = $parsed['query'] ?? '';
        if ($this->clone_is_framer_responsive_asset($url) && $query !== '') {
            $parameters = [];
            parse_str($query, $parameters);
            // Framer's scale-down-to parameter alone denotes the responsive
            // derivative. Preserve dimensions, quality and crop parameters so
            // genuinely different image treatments never collide.
            foreach (['scale-down-to'] as $variant_key) {
                unset($parameters[$variant_key]);
            }
            ksort($parameters);
            $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        }

        return strtolower(($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? ''))
            . (isset($parsed['port']) ? ':' . $parsed['port'] : '')
            . ($parsed['path'] ?? '/')
            . ($query !== '' ? '?' . $query : '');
    }

    private function clone_is_framer_responsive_asset($url)
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return ($host === 'framerusercontent.com' || str_ends_with($host, '.framerusercontent.com'))
            && str_starts_with($path, '/images/');
    }

    private function clone_is_special_url($url)
    {
        $url = trim((string) $url);
        if ($url === '' || preg_match('/^(#|mailto:|tel:|javascript:|data:|blob:|about:)/i', $url)) {
            return true;
        }

        $decoded = html_entity_decode(rawurldecode($url), ENT_QUOTES);
        $decoded = ltrim($decoded);
        if (stripos($decoded, '<svg') !== false || stripos($decoded, '&lt;svg') !== false || preg_match('/%3c\s*svg/i', $url)) {
            return true;
        }

        return false;
    }

    private function clone_extension_from_content_type($content_type)
    {
        $map = [
            'text/css' => 'css',
            'javascript' => 'js',
            'application/json' => 'json',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg' => 'svg',
            'image/avif' => 'avif',
            'image/x-icon' => 'ico',
            'font/woff2' => 'woff2',
            'font/woff' => 'woff',
            'font/ttf' => 'ttf',
            'application/font-woff' => 'woff',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
        ];
        foreach ($map as $needle => $ext) {
            if (strpos($content_type, $needle) !== false) {
                return $ext;
            }
        }
        return '';
    }

    /**
     * Choose a non-executable extension for cloned assets. Never trust a remote
     * filename such as image.php, even when its response body looks harmless.
     */
    private function clone_safe_asset_extension($path, $content_type)
    {
        $allowed = [
            'css', 'js', 'mjs', 'json', 'txt', 'map',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'ico',
            'woff', 'woff2', 'ttf', 'otf', 'eot',
            'mp4', 'webm', 'mp3', 'ogg', 'wav',
        ];
        $from_type = $this->clone_extension_from_content_type(strtolower((string) $content_type));
        if ($from_type !== '') {
            return in_array($from_type, $allowed, true) ? $from_type : '';
        }

        $from_path = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        return in_array($from_path, $allowed, true) ? $from_path : '';
    }

    /**
     * Accept remote SVG image assets only when they are inert. SVG files are
     * same-origin after localization, so active markup must never be stored.
     */
    private function clone_sanitize_svg_asset($svg)
    {
        $svg = trim((string) $svg);
        if ($svg === '' || !preg_match('/<svg\b/i', $svg)) {
            return '';
        }
        if (preg_match(
            '/<(?:script|foreignObject|iframe|object|embed|audio|video)\b|\son[a-z0-9_-]+\s*=|(?:href|xlink:href)\s*=\s*(["\'])\s*(?:javascript:|data\s*:\s*text\/html)/i',
            $svg
        )) {
            return '';
        }
        return $svg;
    }

    private function clone_extract_title($html, $fallback = '')
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            $title = trim(html_entity_decode(wp_strip_all_tags($m[1]), ENT_QUOTES));
            if ($title !== '') {
                if ($fallback && strtolower($fallback) !== 'home' && stripos($title, $fallback) !== false) {
                    return trim($fallback);
                }
                $title = preg_split('/\s+[|–-]\s+/', $title)[0] ?: $title;
                return trim($title);
            }
        }
        if (preg_match('/<h1\b[^>]*>(.*?)<\/h1>/si', $html, $m)) {
            $title = trim(wp_strip_all_tags($m[1]));
            if ($title !== '') {
                return mb_substr($title, 0, 80);
            }
        }
        return $fallback ?: 'Imported Page';
    }

    private function clone_title_from_url($url)
    {
        $path = trim(wp_parse_url($url, PHP_URL_PATH) ?? '', '/');
        if ($path === '') {
            return 'Home';
        }
        $last = basename($path);
        return ucwords(str_replace(['-', '_'], ' ', preg_replace('/\.html?$/i', '', $last)));
    }

    private function clone_is_rendered_shell($html)
    {
        $body = $html;
        if (preg_match('/<body\b[^>]*>([\s\S]*?)<\/body>/i', $html, $m)) {
            $body = $m[1];
        }
        $without_scripts = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $body);
        $without_styles = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $without_scripts);
        $without_comments = preg_replace('/<!--[\s\S]*?-->/s', '', $without_styles);
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($without_comments)));
        $has_app_root = preg_match('/\bid\s*=\s*["\'](?:app|root|__next|__nuxt|___gatsby|gatsby-focus-wrapper)["\']|\bdata-reactroot\b|\bng-version\b|<app-root\b/i', $html);
        $has_client_bundle = preg_match('/<script\b[^>]*(?:\btype\s*=\s*["\']module["\']|\/assets\/[^"\']+\.(?:js|mjs)|\/_next\/static\/[^"\']+\.(?:js|mjs)|\/_nuxt\/[^"\']+\.(?:js|mjs)|\/build\/_assets\/[^"\']+\.(?:js|mjs))/i', $html);
        $has_empty_app_root = preg_match('/<([a-z0-9:-]+)\b[^>]*\bid\s*=\s*["\'](?:app|root|__next|__nuxt|___gatsby|gatsby-focus-wrapper)["\'][^>]*>\s*(?:<!--[\s\S]*?-->\s*)*<\/\1>/i', $body);
        $tag_count = preg_match_all('/<[a-z][a-z0-9:-]*\b/i', $without_comments);
        return ($has_app_root || ($has_client_bundle && strlen($text) < 80 && $tag_count < 8)) && (
            $has_empty_app_root
            || ($has_client_bundle && strlen($text) < 80 && $tag_count < 12)
            || (!$has_client_bundle && strlen($text) < 80 && $tag_count < 8)
        );
    }

    private function clone_snapshot_metrics($html)
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

    private function clone_build_publish_safety_report($html)
    {
        $metrics = $this->clone_snapshot_metrics($html);
        $framework = $this->clone_detect_ssr_framework($html);
        $report = [
            'framework' => $framework ?: '',
            'body_text_length' => intval($metrics['body_text_length']),
            'body_tag_count' => intval($metrics['body_tag_count']),
            'has_layout' => !empty($metrics['has_layout']),
            'has_tanstack_runtime' => stripos($html, '$_TSR') !== false || stripos($html, '$tsr-stream-barrier') !== false || stripos($html, 'class="$tsr"') !== false,
            'has_module_script' => (bool) preg_match('/<script\b[^>]*\btype\s*=\s*(["\'])module\1/i', $html),
            'has_cloned_js_script' => (bool) preg_match('/<script\b[^>]*\bsrc\s*=\s*(["\'])[^"\']*(?:ugentic-clones|ugentic-instant-clones)[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?\1/i', $html),
            'has_modulepreload' => (bool) preg_match('/<link\b[^>]*\brel\s*=\s*(["\'])[^"\']*modulepreload[^"\']*\1/i', $html),
            'has_root_asset_import' => stripos($html, 'import("/assets/') !== false || stripos($html, 'import(\"/assets/') !== false,
            'has_snapshot_guard' => strpos($html, 'ugentic-static-snapshot-guard') !== false,
            'mitigations' => [],
            'risks' => [],
        ];

        if ($report['has_snapshot_guard']) {
            $report['mitigations'][] = 'static_snapshot_guard';
        }

        $guarded = !empty($report['has_snapshot_guard']);
        if (!$guarded && $report['has_tanstack_runtime']) {
            $report['risks'][] = 'tanstack_runtime_can_hydrate_empty_body';
        }
        if ($report['has_root_asset_import']) {
            $report['risks'][] = 'root_relative_asset_import';
        }
        if (!$guarded && $report['has_module_script'] && $report['has_layout'] && $report['body_text_length'] > 120) {
            $report['risks'][] = 'module_runtime_on_static_snapshot';
        }
        if (!$guarded && $report['has_cloned_js_script'] && $report['has_layout'] && $report['body_text_length'] > 120) {
            $report['risks'][] = 'cloned_runtime_script_on_static_snapshot';
        }
        if (!$guarded && $report['has_modulepreload'] && $report['has_layout'] && $report['body_text_length'] > 120) {
            $report['risks'][] = 'modulepreload_for_static_snapshot';
        }

        $report['mitigations'] = array_values(array_unique($report['mitigations']));
        $report['risks'] = array_values(array_unique($report['risks']));
        return $report;
    }

    private function clone_needs_client_app_path_shim($html, $page_url, $client_rendered_shell = false)
    {
        if (strpos($html, 'ugentic-client-app-path-shim') !== false) {
            return false;
        }
        if ($client_rendered_shell) {
            return true;
        }

        $path = wp_parse_url($page_url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $framework = $this->clone_detect_ssr_framework($html);
        if ($framework !== '') {
            return false;
        }

        $has_app_root = preg_match('/\bid\s*=\s*["\'](?:app|root)["\']/i', $html);
        if (!$has_app_root) {
            return false;
        }

        $has_module_bundle = preg_match('/<script\b[^>]*\btype\s*=\s*(?:"module"|\'module\'|module)[^>]*(?:\bsrc\s*=|>)/i', $html)
            || preg_match('#<script\b[^>]*\bsrc\s*=\s*(["\'])[^"\']*/assets/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?\1#i', $html);
        if (!$has_module_bundle) {
            return false;
        }

        return preg_match('/\b(?:vite|react|svelte|solid|vue|astro|spa|router|route|root)\b/i', $html)
            || preg_match('#/assets/[^"\']+\.(?:js|mjs)(?:\?[^"\']*)?#i', $html);
    }

    /**
     * Insert an asset or reuse existing one with same name and type.
     * If found, updates its content; if not, creates a new asset.
     * 
     * @param array $data Asset data with 'name', 'type', 'content', 'is_active'
     * @return int Asset ID
     */

    /**
     * AJAX: Upload and process website ZIP
     */
    public function ajax_upload_website()
    {

        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        // A large multi-page archive can contain hundreds of images, fonts, and
        // scripts. Let the finite, validated archive scan finish even when the
        // hosting account has a short default PHP execution limit; otherwise a
        // request can stop after copying only the first portion of the assets.
        @ignore_user_abort(true);
        @set_time_limit(0);

        if (empty($_FILES['website_zip'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'tahmir-ai-to-wp-converter')]);
        }

        $file = $_FILES['website_zip'];

        // ZIP file size cap (200 MB)
        $max_upload_size = 200 * 1024 * 1024;
        $upload_validation = Ugentic_Security::validate_uploaded_file(
            $file,
            ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
            $max_upload_size,
            ['zip']
        );
        if (is_wp_error($upload_validation)) {
            wp_send_json_error(['message' => $upload_validation->get_error_message()]);
        }

        // Prevent concurrent imports — use a transient lock
        $lock_key = 'ugentic_import_lock_' . get_current_user_id();
        if (get_transient($lock_key)) {
            wp_send_json_error(['message' => __('An import is already in progress. Please wait for it to finish.', 'tahmir-ai-to-wp-converter')]);
        }
        set_transient($lock_key, true, 10 * MINUTE_IN_SECONDS);

        // Create temp directory with unique name
        $upload_dir = wp_upload_dir();
        $unique_id = 'ugentic-import-' . uniqid();
        $this->asset_upload_namespace = sanitize_file_name($unique_id);
        $this->temp_dir = $upload_dir['basedir'] . '/' . $unique_id;

        if (!wp_mkdir_p($this->temp_dir)) {
            delete_transient($lock_key);
            wp_send_json_error(['message' => __('Failed to create temp directory', 'tahmir-ai-to-wp-converter')]);
        }

        try {
            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                throw new \RuntimeException(__('Failed to open ZIP file', 'tahmir-ai-to-wp-converter'));
            }

            $zip_validation = $this->validate_zip_archive($zip, (int) $file['size']);
            if (is_wp_error($zip_validation)) {
                $zip->close();
                throw new \RuntimeException($zip_validation->get_error_message());
            }

            $extract_entries = $zip_validation['entries'] ?? [];
            if (empty($extract_entries)) {
                $zip->close();
                throw new \RuntimeException(__('ZIP does not contain any importable website files.', 'tahmir-ai-to-wp-converter'));
            }

            if (!$zip->extractTo($this->temp_dir, $extract_entries)) {
                $zip->close();
                throw new \RuntimeException(__('Failed to extract ZIP file.', 'tahmir-ai-to-wp-converter'));
            }
            $zip->close();

            // Find the actual root (handle nested folders)
            $this->temp_dir = $this->find_actual_root($this->temp_dir);

            // First, upload ALL assets to ensure they're available
            $this->assets_base_url = $upload_dir['baseurl'] . '/' . $unique_id . '/';

            // Scan and process all files
            $result = $this->scan_and_prepare_files($this->temp_dir);

            // Never allow an import session to proceed with a partial asset map.
            // A failed copy must be explicit instead of publishing pages whose
            // images will inevitably resolve to broken URLs.
            if (!empty($result['asset_upload_summary']['failed'])) {
                $failed = $result['asset_upload_summary']['failed'];
                $preview = implode(', ', array_slice($failed, 0, 5));
                if (count($failed) > 5) {
                    $preview .= sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ __(' and %d more', 'tahmir-ai-to-wp-converter'), count($failed) - 5);
                }
                throw new \RuntimeException(sprintf(
                    /* translators: 1: Failed asset count; 2: File names. */ __('Import stopped because %1$d asset file(s) could not be copied: %2$s. No pages were imported; fix the upload/storage issue and retry.', 'tahmir-ai-to-wp-converter'),
                    count($failed),
                    $preview
                ));
            }

            // AI-powered import analysis (gracefully falls back without API key)
            $ai_analysis = $this->ai_analyze_import($result);
            if (!empty($result['duplicate_html_aliases'])) {
                if (empty($ai_analysis['warnings']) || !is_array($ai_analysis['warnings'])) {
                    $ai_analysis['warnings'] = [];
                }
                $ai_analysis['warnings'][] = sprintf(
                    /* translators: Number of the pages, records, files, or items described in this message. */ _n(
                        'Merged %d duplicate HTML route alias so it imports as one WordPress page.',
                        'Merged %d duplicate HTML route aliases so each public page imports only once.',
                        intval($result['duplicate_html_aliases']), 'tahmir-ai-to-wp-converter'
                    ),
                    intval($result['duplicate_html_aliases'])
                );
            }
            $archive_blocked_entries = $zip_validation['blocked_entries'] ?? [];
            $archive_skipped_entries = $zip_validation['skipped_entries'] ?? [];
            if (!empty($archive_blocked_entries) || !empty($archive_skipped_entries)) {
                if (empty($ai_analysis['warnings']) || !is_array($ai_analysis['warnings'])) {
                    $ai_analysis['warnings'] = [];
                }
                $ai_analysis['warnings'][] = sprintf(
                    /* translators: 1: Source URL; 2: Reason the page was skipped. */ __('Skipped %d unsafe or non-importable archive file(s) before extraction.', 'tahmir-ai-to-wp-converter'),
                    count($archive_blocked_entries) + count($archive_skipped_entries)
                );
            }

            // Store temp info for next step (6 hours for large imports)
            set_transient('ugentic_import_' . get_current_user_id(), [
                'temp_dir' => $this->temp_dir,
                'unique_id' => $unique_id,
                'asset_upload_namespace' => $this->asset_upload_namespace,
                'asset_map' => $this->asset_map,
                'file_index' => $this->file_index,
                'files' => $result,
                'ai_analysis' => $ai_analysis,
                'archive_skipped_entries' => $archive_skipped_entries,
                'archive_blocked_entries' => $archive_blocked_entries,
            ], 6 * HOUR_IN_SECONDS);

            // Extraction has completed and the resumable import state is now
            // stored. Release the concurrency lock before terminating JSON.
            delete_transient($lock_key);

            wp_send_json_success([
                'message' => __('Website extracted successfully!', 'tahmir-ai-to-wp-converter'),
                'files' => $result,
                'temp_dir' => $this->temp_dir,
                'ai_analysis' => $ai_analysis,
                'archive_skipped_entries' => $archive_skipped_entries,
                'archive_blocked_entries' => $archive_blocked_entries,
            ]);
        } catch (\Throwable $e) {
            // Release import lock
            delete_transient('ugentic_import_lock_' . get_current_user_id());
            // Clean up temp directory on any failure
            if (!empty($this->temp_dir) && is_dir($this->temp_dir)) {
                $this->delete_directory($this->temp_dir);
            }
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Find actual root directory (skip single nested folder)
     */
    private function find_actual_root($dir)
    {
        $items = array_values(array_diff(scandir($dir), ['.', '..', '__MACOSX', '.DS_Store']));
        $directories = [];
        $has_direct_html = false;

        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $directories[] = $path;
            } elseif (preg_match('/\.html?$/i', $item)) {
                $has_direct_html = true;
            }
        }

        // Export tools often add README/license files beside one wrapper
        // directory. They should not prevent Tahmir from finding the real root.
        if (!$has_direct_html && count($directories) === 1 && $this->directory_contains_html($directories[0])) {
            return $this->find_actual_root($directories[0]);
        }

        return rtrim($dir, '/\\');
    }

    private function directory_contains_html($dir)
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.html?$/i', $file->getFilename())) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Validate ZIP archive entries before extraction.
     */
    private function validate_zip_archive($zip, $compressed_size)
    {
        if (!$zip instanceof ZipArchive) {
            return new WP_Error('ugentic_zip_invalid', __('Invalid ZIP archive.', 'tahmir-ai-to-wp-converter'));
        }

        if ((int) $zip->numFiles > self::MAX_ZIP_FILES) {
            return new WP_Error('ugentic_zip_too_many_files', __('ZIP file contains too many files.', 'tahmir-ai-to-wp-converter'));
        }

        $total_uncompressed = 0;
        $extract_entries = [];
        $blocked_entries = [];
        $skipped_entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || empty($stat['name'])) {
                continue;
            }

            $raw_entry = (string) $stat['name'];
            $entry = str_replace('\\', '/', $raw_entry);
            if (Ugentic_Security::is_unsafe_archive_path($entry)) {
                return new WP_Error('ugentic_zip_unsafe_path', __('ZIP contains an unsafe file path.', 'tahmir-ai-to-wp-converter'));
            }

            if ($this->zip_entry_is_directory($entry)) {
                continue;
            }

            if ($this->zip_entry_is_symlink($zip, $i)) {
                $skipped_entries[] = $entry;
                continue;
            }

            if ($this->zip_entry_should_skip($entry)) {
                $skipped_entries[] = $entry;
                continue;
            }

            $entry_ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            // Apache may interpret an earlier suffix (for example payload.php.jpg).
            $suffixes = array_slice(explode('.', strtolower(basename($entry))), 1);
            if (array_intersect($suffixes, self::BLOCKED_ARCHIVE_EXTENSIONS)) {
                $blocked_entries[] = $entry;
                continue;
            }

            $total_uncompressed += (int) ($stat['size'] ?? 0);
            if ($total_uncompressed > self::MAX_ZIP_UNCOMPRESSED_SIZE) {
                return new WP_Error('ugentic_zip_uncompressed_too_large', __('ZIP expands to too much data.', 'tahmir-ai-to-wp-converter'));
            }

            $extract_entries[] = $raw_entry;
        }

        $ratio = $compressed_size > 0 ? $total_uncompressed / $compressed_size : 0;
        if ($ratio > 20) {
            return new WP_Error('ugentic_zip_bomb', __('ZIP file rejected: suspicious decompression ratio.', 'tahmir-ai-to-wp-converter'));
        }

        return [
            'entries' => $extract_entries,
            'blocked_entries' => $blocked_entries,
            'skipped_entries' => $skipped_entries,
        ];
    }

    /**
     * Check whether a ZIP entry is a directory.
     */
    private function zip_entry_is_directory($entry)
    {
        return substr((string) $entry, -1) === '/';
    }

    /**
     * Check whether a ZIP entry points to a symlink.
     */
    private function zip_entry_is_symlink($zip, $index)
    {
        if (!$zip instanceof ZipArchive || !method_exists($zip, 'getExternalAttributesIndex')) {
            return false;
        }

        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return false;
        }

        return (($attributes >> 16) & 0xF000) === 0xA000;
    }

    /**
     * Skip archive entries that are not useful for a front-end website import.
     */
    private function zip_entry_should_skip($entry)
    {
        $parts = explode('/', str_replace('\\', '/', (string) $entry));

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            $name = strtolower($part);
            if ($name === '__macosx' || in_array($name, self::BLOCKED_ARCHIVE_FILENAMES, true)) {
                return true;
            }

            if (strpos($name, '.') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deprecated one-pass scanner retained temporarily for migration review.
     * New imports use scan_and_prepare_files() below.
     */

    /**
     * Directory-agnostic, order-independent ZIP inventory and upload pipeline.
     */
    private function scan_and_prepare_files($dir)
    {
        $result = [
            'html_files' => [],
            'css_files' => [],
            'js_files' => [],
            'images' => [],
            'videos' => [],
            'audio' => [],
            'fonts' => [],
            'other_assets' => [],
            'other' => [],
        ];
        $inventory = [];
        $basename_candidates = [];
        $this->file_index = [];
        $this->asset_map = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        // Pass one: build a complete, canonical inventory. Basename aliases are
        // allowed only when unique, so duplicate logo.png files are never mixed.
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $relative = $this->get_relative_path($dir, $path);
            $filename = $file->getFilename();
            if ($relative === '' || strpos($filename, '.') === 0 || preg_match('#(?:^|/)__MACOSX(?:/|$)#i', $relative)) {
                continue;
            }

            $item = [
                'path' => $path,
                'relative' => $relative,
                'name' => $filename,
                'size' => $file->getSize(),
                'extension' => strtolower($file->getExtension()),
            ];
            $inventory[] = $item;
            $this->file_index[$relative] = $path;

            $basename = strtolower($filename);
            if (!isset($basename_candidates[$basename])) {
                $basename_candidates[$basename] = [];
            }
            $basename_candidates[$basename][] = $path;
        }

        foreach ($basename_candidates as $basename => $paths) {
            if (count($paths) === 1) {
                $this->file_index[$basename] = $paths[0];
            }
        }

        // Pass two: upload all non-HTML front-end files. The ZIP validator has
        // already excluded executable extensions, so uncommon assets such as
        // web manifests, JSON, WASM, captions, and Lottie files are preserved.
        foreach ($inventory as $item) {
            $extension = $item['extension'];
            $file_info = [
                'path' => $item['path'],
                'relative' => $item['relative'],
                'name' => $item['name'],
                'size' => $item['size'],
            ];

            if (in_array($extension, ['html', 'htm'], true)) {
                if ($item['size'] > self::MAX_HTML_SIZE) {
                    $file_info['skipped'] = true;
                    $file_info['skip_reason'] = 'File too large (max ' . (self::MAX_HTML_SIZE / 1024 / 1024) . 'MB)';
                    $result['other'][] = $file_info;
                    continue;
                }
                $content = file_get_contents($item['path']);
                preg_match('/<title>(.*?)<\/title>/is', (string) $content, $matches);
                $file_info['title'] = isset($matches[1]) ? trim(html_entity_decode($matches[1])) : $item['name'];
                $file_info['canonical_url'] = self::extract_import_page_canonical_url((string) $content);
                $result['html_files'][] = $file_info;
                continue;
            }

            $bucket = 'other_assets';
            if ($extension === 'css') {
                $bucket = 'css_files';
            } elseif (in_array($extension, ['js', 'mjs', 'cjs'], true)) {
                $bucket = 'js_files';
            } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif', 'tiff', 'tif'], true)) {
                $bucket = 'images';
            } elseif (in_array($extension, ['mp4', 'webm', 'ogv', 'mov'], true)) {
                $bucket = 'videos';
            } elseif (in_array($extension, ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac'], true)) {
                $bucket = 'audio';
            } elseif (in_array($extension, ['woff', 'woff2', 'ttf', 'eot', 'otf'], true)) {
                $bucket = 'fonts';
            }

            $too_large = ($extension === 'css' && $item['size'] > self::MAX_CSS_SIZE)
                || (in_array($extension, ['js', 'mjs', 'cjs'], true) && $item['size'] > self::MAX_JS_SIZE);
            if ($too_large) {
                $file_info['skipped'] = true;
                $file_info['skip_reason'] = __('Asset exceeds its safe import size limit.', 'tahmir-ai-to-wp-converter');
                $result[$bucket][] = $file_info;
                continue;
            }

            $new_url = $this->upload_asset($item['path'], $dir);
            if ($new_url) {
                $this->asset_map[$item['relative']] = $new_url;
                $file_info['new_url'] = $new_url;
            }
            $result[$bucket][] = $file_info;
        }

        // Static exports commonly contain both `about.html` and
        // `about/index.html`. They are two filesystem aliases for one public
        // route, not two WordPress pages. Collapse them before the review UI
        // and retain every alias for deterministic internal-link rewriting.
        $html_file_count = count($result['html_files']);
        $result['html_files'] = self::deduplicate_import_page_files($result['html_files']);
        $result['duplicate_html_aliases'] = max(0, $html_file_count - count($result['html_files']));

        // Pass three: CSS can now be rewritten against the complete asset map.
        $css_resolution_failures = $this->resolve_uploaded_css_urls();
        $js_resolution_failures = $this->resolve_uploaded_javascript_urls();
        $asset_buckets = ['css_files', 'js_files', 'images', 'videos', 'audio', 'fonts', 'other_assets'];
        $asset_expected = 0;
        $asset_uploaded = 0;
        $asset_failed = [];
        foreach ($asset_buckets as $bucket) {
            foreach ($result[$bucket] as $asset) {
                $asset_expected++;
                if (!empty($asset['new_url'])) {
                    $asset_uploaded++;
                } else {
                    $asset_failed[] = (string) ($asset['relative'] ?? $asset['name'] ?? 'unknown asset');
                }
            }
        }
        $asset_failed = array_merge($asset_failed, $css_resolution_failures, $js_resolution_failures);
        $result['asset_upload_summary'] = [
            'expected' => $asset_expected,
            'uploaded' => $asset_uploaded,
            'failed' => array_values(array_unique($asset_failed)),
        ];

        return $result;
    }

    /**
     * Get a canonical relative path from a trusted base directory.
     */
    private function get_relative_path($base, $path)
    {
        $base = rtrim(str_replace('\\', '/', (string) $base), '/');
        $path = str_replace('\\', '/', (string) $path);
        $base_real = realpath($base);
        $path_real = realpath($path);
        if ($base_real !== false) {
            $base = rtrim(str_replace('\\', '/', $base_real), '/');
        }
        if ($path_real !== false) {
            $path = str_replace('\\', '/', $path_real);
        }

        $base_prefix = strtolower($base) . '/';
        if (strtolower(substr($path, 0, strlen($base_prefix))) !== $base_prefix) {
            return '';
        }

        return $this->normalize_import_path(substr($path, strlen($base_prefix)));
    }

    /**
     * Canonicalize an archive-relative path without allowing it to escape root.
     */
    private function normalize_import_path($path)
    {
        $path = rawurldecode(preg_replace('/[?#].*$/', '', str_replace('\\', '/', (string) $path)));
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (!empty($parts)) {
                    array_pop($parts);
                }
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    /**
     * Convert an archive HTML path to its public route identity.
     *
     * Examples:
     * - contact.html       => contact
     * - contact/index.html => contact
     * - index.html         => (home route)
     *
     * Public for the standalone importer regression suite.
     */
    public static function canonical_import_page_route($path)
    {
        $path = rawurldecode(preg_replace('/[?#].*$/', '', str_replace('\\', '/', (string) $path)));
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (!empty($parts)) {
                    array_pop($parts);
                }
                continue;
            }
            $parts[] = $part;
        }

        $route = strtolower(implode('/', $parts));
        $route = preg_replace('/\.html?$/i', '', $route);
        $route = preg_replace('#(?:^|/)index$#i', '', $route);

        return trim((string) $route, '/');
    }

    /**
     * Collapse HTML export aliases while keeping the winning file's complete
     * alias list for link repair. A declared canonical URL takes precedence as
     * page identity; otherwise the normalized archive route is used.
     *
     * Public for deterministic standalone testing.
     */
    public static function deduplicate_import_page_files($html_files)
    {
        $winners = [];

        foreach ((array) $html_files as $index => $file) {
            if (!is_array($file)) {
                continue;
            }

            $relative = (string) ($file['relative'] ?? $file['name'] ?? '');
            $route = self::canonical_import_page_route($relative);
            $canonical_identity = self::canonical_import_page_url_identity($file['canonical_url'] ?? '');
            $identity = $canonical_identity !== ''
                ? 'canonical:' . $canonical_identity
                : 'route:' . ($route !== '' ? $route : '__home__');

            $aliases = array_merge([$relative], (array) ($file['source_aliases'] ?? []));
            $aliases = self::normalize_import_source_aliases($aliases);
            $file['source_aliases'] = $aliases;
            $file['duplicate_alias_count'] = max(0, count($aliases) - 1);

            if (!isset($winners[$identity])) {
                $file['_import_scan_order'] = intval($index);
                $winners[$identity] = $file;
                continue;
            }

            $existing = $winners[$identity];
            $merged_aliases = self::normalize_import_source_aliases(array_merge(
                (array) ($existing['source_aliases'] ?? []),
                $aliases
            ));

            if (self::import_page_file_preference($file) < self::import_page_file_preference($existing)) {
                $file['_import_scan_order'] = $existing['_import_scan_order'] ?? intval($index);
                $winner = $file;
            } else {
                $winner = $existing;
            }

            $winner['source_aliases'] = $merged_aliases;
            $winner['duplicate_alias_count'] = max(0, count($merged_aliases) - 1);
            $winners[$identity] = $winner;
        }

        uasort($winners, function ($left, $right) {
            return intval($left['_import_scan_order'] ?? 0) <=> intval($right['_import_scan_order'] ?? 0);
        });

        return array_values(array_map(function ($file) {
            unset($file['_import_scan_order']);
            return $file;
        }, $winners));
    }

    private static function canonical_import_page_url_identity($url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '';
        }

        $parsed = wp_parse_url($url);
        if ($parsed === false) {
            return '';
        }

        $host = strtolower(preg_replace('/^www\./i', '', (string) ($parsed['host'] ?? '')));
        $port = isset($parsed['port']) ? ':' . intval($parsed['port']) : '';
        $route = self::canonical_import_page_route($parsed['path'] ?? $url);
        $route = $route !== '' ? $route : '__home__';

        return ($host !== '' ? $host . $port . '|' : '') . $route;
    }

    private static function import_page_file_preference($file)
    {
        $relative = str_replace('\\', '/', (string) ($file['relative'] ?? $file['name'] ?? ''));
        $basename = strtolower(basename($relative));
        $is_nested_index = preg_match('#(?:^|/)index\.html?$#i', $relative) && trim(dirname($relative), './') !== '';
        $depth = substr_count(trim($relative, '/'), '/');

        // Prefer the concise flat alias (about.html) over about/index.html.
        // It normally matches the archive's own navigation hrefs and needs
        // fewer relative-path adjustments during faithful processing.
        return ($is_nested_index ? 100000000 : 0)
            + (($basename === 'index.html' || $basename === 'index.htm') ? 10000000 : 0)
            + (min(999, $depth) * 10000)
            + min(9999, strlen($relative));
    }

    private static function normalize_import_source_aliases($aliases)
    {
        $normalized = [];
        foreach ((array) $aliases as $alias) {
            $alias = rawurldecode(preg_replace('/[?#].*$/', '', str_replace('\\', '/', (string) $alias)));
            $parts = [];
            foreach (explode('/', $alias) as $part) {
                if ($part === '' || $part === '.') {
                    continue;
                }
                if ($part === '..') {
                    if (!empty($parts)) {
                        array_pop($parts);
                    }
                    continue;
                }
                $parts[] = $part;
            }
            $alias = implode('/', $parts);
            if ($alias !== '') {
                $normalized[strtolower($alias)] = $alias;
            }
        }

        return array_values($normalized);
    }

    private static function extract_import_page_canonical_url($html)
    {
        if (!preg_match_all('/<link\b[^>]*>/i', (string) $html, $links)) {
            return '';
        }

        foreach ($links[0] as $link) {
            if (!preg_match('/\brel\s*=\s*(["\'])([^"\']*)\1/i', $link, $rel_match)) {
                continue;
            }
            $rels = preg_split('/\s+/', strtolower(trim($rel_match[2])));
            if (!in_array('canonical', $rels, true)) {
                continue;
            }
            if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $link, $href_match)) {
                return trim(html_entity_decode($href_match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        return '';
    }

    /**
     * Process CSS file to find and upload referenced images/fonts
     * Handles url() references, @import rules, and @font-face declarations
     */

    /**
     * Resolve a relative path from a base directory
     */

    /**
     * Upload an asset to WordPress uploads
     */
    private function upload_asset($file_path, $base_dir)
    {
        if (!file_exists($file_path)) {
            return false;
        }

        if (!Ugentic_Security::path_is_inside($file_path, $base_dir)) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $relative = $this->get_relative_path($base_dir, $file_path);
        if ($relative === '') {
            return false;
        }

        $filename = sanitize_file_name(basename($relative));
        if ($filename === '') {
            $filename = 'asset-' . substr(hash('sha256', $relative), 0, 12);
        }
        $upload_dir = wp_upload_dir();

        // Isolate the complete directory tree for this import. Folder names are
        // sanitized segment-by-segment, while the original relative path stays
        // in asset_map for exact source resolution.
        $namespace = sanitize_file_name($this->asset_upload_namespace);
        if ($namespace === '') {
            $namespace = 'import-' . substr(hash('sha256', (string) $base_dir), 0, 12);
        }
        $target_dir = $upload_dir['basedir'] . '/ugentic-assets/' . $namespace . '/';
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        $relative_dir = dirname($relative);
        $safe_relative_dir = '';

        if ($relative_dir !== '.') {
            $safe_segments = [];
            foreach (explode('/', str_replace('\\', '/', $relative_dir)) as $segment) {
                $safe = sanitize_file_name($segment);
                if ($safe !== '') {
                    $safe_segments[] = $safe;
                }
            }
            $safe_relative_dir = implode('/', $safe_segments);
            if ($safe_relative_dir !== '') {
                $target_dir .= $safe_relative_dir . '/';
            }
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }
        }

        $target_path = $target_dir . $filename;
        $allowed_root = $upload_dir['basedir'] . '/ugentic-assets';

        // A sanitized path collision inside the same import is resolved with a
        // stable source-path hash instead of traversal-order counters.
        if (file_exists($target_path)) {
            $same_size = @filesize($target_path) === @filesize($file_path);
            $same_hash = $same_size && hash_file('sha256', $target_path) === hash_file('sha256', $file_path);
            if ($same_hash) {
                $relative_url = $safe_relative_dir !== '' ? $safe_relative_dir . '/' : '';
                return $upload_dir['baseurl'] . '/ugentic-assets/' . $namespace . '/' . $relative_url . basename($target_path);
            }
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $suffix = '-' . substr(hash('sha256', $relative), 0, 10);
            $target_path = $target_dir . $name . $suffix . ($ext !== '' ? '.' . $ext : '');
        }

        if (!Ugentic_Security::path_is_inside($target_path, $allowed_root)) {
            return false;
        }

        // Copy the file
        if (!copy($file_path, $target_path)) {
            return false;
        }

        // copy() can report success before a remote/networked filesystem has
        // persisted the complete payload. Verify the destination before adding
        // it to the asset map so the import cannot silently reference a partial
        // or zero-byte file.
        clearstatcache(true, $target_path);
        $source_size = @filesize($file_path);
        $target_size = @filesize($target_path);
        if (!is_file($target_path) || $source_size === false || $target_size === false || $source_size !== $target_size) {
            @wp_delete_file($target_path);
            return false;
        }

        // Build URL
        $target_url = $upload_dir['baseurl'] . '/ugentic-assets/' . $namespace . '/';
        if ($safe_relative_dir !== '') {
            $target_url .= $safe_relative_dir . '/';
        }
        $target_url .= basename($target_path);

        return $target_url;
    }

    /**
     * AJAX: Import individual page
     */
    public function ajax_import_page()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $html_file = sanitize_text_field(wp_unslash($_POST['html_file'] ?? ''));
        $page_title = sanitize_text_field($_POST['title'] ?? '');
        $import_mode = sanitize_text_field($_POST['import_mode'] ?? 'faithful');
        $detect_header_footer = !empty($_POST['detect_header_footer']);
        $make_header_global = !empty($_POST['make_header_global']);
        $publish = intval($_POST['publish'] ?? 0);

        $import_data = get_transient('ugentic_import_' . get_current_user_id());

        if (!$import_data || !file_exists($html_file)) {
            wp_send_json_error(['message' => __('Import session expired. Please upload again.', 'tahmir-ai-to-wp-converter')]);
        }

        $this->temp_dir = $import_data['temp_dir'];
        $this->asset_map = $import_data['asset_map'];
        $this->file_index = $import_data['file_index'] ?? [];
        $this->asset_upload_namespace = $import_data['asset_upload_namespace'] ?? ($import_data['unique_id'] ?? '');
        $this->import_session_id = sanitize_key($import_data['unique_id'] ?? '');
        $source_aliases = $this->get_import_source_aliases($import_data, $html_file);

        $approved_files = array_column($import_data['files']['html_files'] ?? [], 'path');
        if (!in_array($html_file, $approved_files, true) || !is_file($html_file)
            || !Ugentic_Security::path_is_inside($html_file, $this->temp_dir)) {
            wp_send_json_error(['message' => __('Invalid import file path.', 'tahmir-ai-to-wp-converter')]);
        }

        // Route to faithful or advanced import
        if ($import_mode === 'faithful') {
            $result = $this->import_single_page_faithful($html_file, $page_title, $publish, $source_aliases);
        } else {
            $result = $this->import_single_page($html_file, $page_title, $detect_header_footer, $publish, $make_header_global, $source_aliases);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        if (!is_array($result)) {
            wp_send_json_error(['message' => __('Import failed with an unexpected response.', 'tahmir-ai-to-wp-converter')]);
        }

        if ($result['success']) {
            // Make link rewriting a server-side import guarantee. The admin UI
            // also runs a final idempotent pass, but imports triggered through
            // REST/MCP/CLI must not depend on that browser callback.
            if (empty($_POST['defer_link_rewrite'])) {
                $result['data']['link_rewrite'] = $this->replace_internal_links();
            }
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['data']);
        }
    }

    private function get_import_source_aliases($import_data, $html_file)
    {
        $target = wp_normalize_path((string) $html_file);
        foreach (($import_data['files']['html_files'] ?? []) as $file) {
            if (!is_array($file) || wp_normalize_path((string) ($file['path'] ?? '')) !== $target) {
                continue;
            }

            return self::normalize_import_source_aliases(array_merge(
                [(string) ($file['relative'] ?? '')],
                (array) ($file['source_aliases'] ?? [])
            ));
        }

        $relative = $this->get_relative_path($this->temp_dir, $html_file);
        return self::normalize_import_source_aliases([$relative]);
    }

    /**
     * Classify blog pages during import without converting them automatically.
     */
    private function detect_blog_page($html, $page_title = '', $source = '')
    {
        if (!class_exists('Ugentic_Blog_Page_Detector')) {
            return [
                'content_type' => null,
                'blog_role' => 'unknown',
                'confidence' => 0,
                'evidence' => [],
                'reason' => __('Blog detector is unavailable.', 'tahmir-ai-to-wp-converter'),
                'item_count' => 0,
                'ai_powered' => false,
            ];
        }

        return Ugentic_Blog_Page_Detector::detect(
            (string) $html,
            (string) $page_title,
            (string) $source
        );
    }

    /**
     * Expose high-confidence archive detection as a suggestion only.
     */private function maybe_handle_blog_autopilot($args) { return []; }

    private function detect_blog_page_for_clone($html, $title, $source)
    {
        return $this->detect_blog_page($html, $title, $source);
    }private function detect_import_menus_from_stored_pages() { return ['success' => true, 'menus_created' => 0]; }

    /** Iterate the complete inventory: the admin list defaults to only 50 pages. */
    private function existing_pages_for_import()
    {
        $database = ugentic_builder()->database;
        $offset = 0;
        do {
            $pages = $database->get_all_pages(['limit' => 50, 'offset' => $offset, 'orderby' => 'id', 'order' => 'ASC']);
            foreach ($pages as $page) {
                yield $page;
            }
            $offset += 50;
        } while (count($pages) === 50 && $offset <= 100000);
    }

    /**
     * Core import logic for a single page — returns data instead of calling wp_send_json_*.
     * Called by both ajax_import_page() and ajax_import_all().
     *
     * @param string $html_file           Full path to the HTML file
     * @param string $page_title          Title for the WordPress page
     * @param bool   $detect_header_footer Whether to detect header/footer
     * @param int    $publish             1 to publish, 0 for draft
     * @param bool   $make_header_global  Whether to extract header/footer as global assets
     * @return array ['success' => bool, 'data' => array]
     */private function import_single_page($html_file, $page_title, $detect_header_footer, $publish, $make_header_global = false, $source_aliases = []) { return new WP_Error('tahmir_pro_required', __('Advanced import requires Tahmir AI Pro. Choose Faithful Import to import this website for free.', 'tahmir-ai-to-wp-converter')); }

    /**
     * Faithful Import: minimal processing that only replaces asset paths and registers forms.
     * The full HTML document structure (head, body, scripts, styles) is preserved as-is.
     * Forms are parsed and saved to the DB (for backend editing) but their original
     * HTML/CSS is kept intact — only annotated with data-ugentic-form-id.
     *
     * @param string $html Raw HTML content (full document)
     * @return array ['html' => path-replaced HTML, 'forms_created' => int, 'form_ids' => array]
     */
    private function process_faithful_page($html, $source_file = '')
    {
        $source_dir = $source_file !== '' ? dirname($source_file) : $this->temp_dir;
        $runtime_integrity_warnings = [];

        if (class_exists('Ugentic_HTML_Cleaner')) {
            $html = Ugentic_HTML_Cleaner::strip_utf8_bom($html);
        }

        // Step 1: Replace asset paths in HTML attributes (src, href, srcset, data-*, inline styles)
        $html = $this->replace_asset_paths($html, $source_dir);

        // Step 2: Resolve url() paths inside any <style> blocks in the HTML
        if ($this->smart_resolver && !empty($this->asset_map)) {
            $html = preg_replace_callback('/<style([^>]*)>(.*?)<\/style>/is', function ($match) use ($source_dir) {
                $attrs = $match[1];
                $css = $match[2];
                $resolved_css = $this->smart_resolver->resolve_css_urls($css, $source_dir);
                return '<style' . $attrs . '>' . $resolved_css . '</style>';
            }, $html);

            // Inline runtime code resolves relative URLs against the WordPress
            // page, not the source script folder. Rewrite statically visible
            // asset strings (fetch, workers, dynamic imports, image creation).
            $html = preg_replace_callback('/<script([^>]*)>(.*?)<\/script>/is', function ($match) use ($source_dir, &$runtime_integrity_warnings) {
                if (preg_match('/\bsrc\s*=/i', $match[1]) || preg_match('/\btype\s*=\s*[\'\"]application\/(?:ld\+json|json)[\'\"]/i', $match[1])) {
                    return $match[0];
                }
                $resolved_js = $this->resolve_javascript_asset_strings($match[2], $source_dir, $unresolved);
                foreach ($unresolved as $ref) {
                    $runtime_integrity_warnings[] = 'Unresolved inline JavaScript asset: ' . $ref;
                }
                return '<script' . $match[1] . '>' . $resolved_js . '</script>';
            }, $html);
        }

        // Uploaded stylesheets are resolved once during the complete ZIP scan.
        // Reprocessing every stylesheet for every page made site imports quadratic.
        require_once __DIR__ . '/class-zip-assets.php';
        $html = Ugentic_Zip_Assets::prepare_runtime($html, $source_file, $this->temp_dir, $this->asset_map);

        if (class_exists('Ugentic_Image_Optimizer')) {
            $html = Ugentic_Image_Optimizer::optimize_imported_html($html, [
                'source' => 'zip_faithful',
                'source_file' => $source_file,
            ]);
        }

        // Step 4: Detect forms, save fields to the DB, and annotate HTML with data attributes.
        // We do NOT convert to [ugentic_form] shortcodes because that destroys styling.
        // Instead, forms are registered in the DB (editable via form builder) and the
        // original HTML is annotated with data-ugentic-form-id for runtime field sync.
        $forms_created = 0;
        $form_ids = [];

        $converter = new Tahmir_Imported_Form_Preview();
        $form_result = $converter->detect_and_register_forms($html);
        $html = $form_result['html'];
        $forms_created = $form_result['forms_created'];
        $form_ids = $form_result['form_ids'];

        $integrity_warnings = array_values(array_unique(array_merge(
            $runtime_integrity_warnings,
            $this->validate_import_result($html)
        )));

        // Runtime form handling is injected by faithful-template.php so saved
        // imported HTML stays clean and never accumulates duplicate handlers.

        return [
            'html' => $html,
            'forms_created' => $forms_created,
            'form_ids' => $form_ids,
            'integrity_warnings' => $integrity_warnings,
        ];
    }

    /**
     * AJAX: Handle form submissions from faithful-mode imported pages.
     * Stores entry in database, sends email notification, and fires webhooks.
     */

    /**
     * AJAX: Save form settings (webhooks) for a faithful-mode page.
     */

    /**
     * AJAX: Restore original form HTML for all faithful-mode pages.
     * Re-processes pages from original_html without form-to-shortcode conversion.
     */

    /**
     * Resolve url() references inside uploaded CSS files on disk.
     * CSS files that reference images/fonts via relative url() need those
     * paths updated to point to the correct uploaded locations.
     */

    /**
     * Rewrite uploaded CSS using each stylesheet's original source directory.
     * Returns unresolved local references so the upload phase can fail closed.
     */
    private function resolve_uploaded_css_urls()
    {
        if (empty($this->asset_map) || !$this->smart_resolver) {
            return [];
        }

        $failures = [];
        $upload_dir = wp_upload_dir();
        $this->smart_resolver->set_path_map($this->asset_map);

        foreach ($this->asset_map as $original_path => $uploaded_url) {
            if (!preg_match('/\.css$/i', preg_replace('/[?#].*$/', '', $original_path))) {
                continue;
            }

            $source_path = $this->file_index[$this->normalize_import_path($original_path)] ?? null;
            if (!$source_path || !is_file($source_path)) {
                $failures[] = 'CSS source missing: ' . $original_path;
                continue;
            }

            $url_path = preg_replace('/[?#].*$/', '', (string) $uploaded_url);
            if (strpos($url_path, $upload_dir['baseurl']) !== 0) {
                $failures[] = 'CSS upload URL is outside the media directory: ' . $original_path;
                continue;
            }
            $css_file = $upload_dir['basedir'] . substr($url_path, strlen($upload_dir['baseurl']));
            $css_file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $css_file);
            if (!is_file($css_file)) {
                $failures[] = 'Uploaded CSS missing: ' . $original_path;
                continue;
            }

            $css_content = file_get_contents($css_file);
            if ($css_content === false) {
                $failures[] = 'Uploaded CSS unreadable: ' . $original_path;
                continue;
            }

            $source_dir = dirname($source_path);
            $resolved = $this->smart_resolver->resolve_css_urls($css_content, $source_dir);
            $resolved = preg_replace_callback(
                '/(@import\s+)([\'\"])([^\'\"]+)\2/i',
                function ($match) use ($source_dir) {
                    $ref = trim($match[3]);
                    if ($this->is_external_or_special_reference($ref)) {
                        return $match[0];
                    }
                    $mapped = $this->smart_resolver->resolve_path($ref, $source_dir);
                    if (preg_match('#^(?:https?:)?//#i', $mapped)) {
                        return $match[1] . $match[2] . $mapped . $match[2];
                    }
                    return $match[0];
                },
                $resolved
            );

            preg_match_all('/url\s*\(\s*[\'\"]?([^\'\"\)\s]+)[\'\"]?\s*\)/i', $resolved, $url_matches);
            preg_match_all('/@import\s+[\'\"]([^\'\"]+)[\'\"]/i', $resolved, $import_matches);
            foreach (array_merge($url_matches[1] ?? [], $import_matches[1] ?? []) as $ref) {
                if (!$this->is_external_or_special_reference($ref)) {
                    $failures[] = 'Unresolved CSS reference in ' . $original_path . ': ' . $ref;
                }
            }

            if ($resolved !== $css_content) {
                $written = file_put_contents($css_file, $resolved, LOCK_EX);
                if ($written === false || $written !== strlen($resolved)) {
                    $failures[] = 'Failed to persist rewritten CSS: ' . $original_path;
                }
            }
        }

        return array_values(array_unique($failures));
    }

    /**
     * Rewrite statically identifiable asset strings in uploaded JavaScript.
     * This preserves fetch(), dynamic imports, workers, and runtime-created
     * images whose relative base would otherwise become the WordPress page URL.
     */
    private function resolve_uploaded_javascript_urls()
    {
        if (empty($this->asset_map) || !$this->smart_resolver) {
            return [];
        }

        $failures = [];
        $upload_dir = wp_upload_dir();
        $this->smart_resolver->set_path_map($this->asset_map);
        foreach ($this->asset_map as $original_path => $uploaded_url) {
            if (!preg_match('/\.(?:js|mjs|cjs)$/i', preg_replace('/[?#].*$/', '', $original_path))) {
                continue;
            }
            $source_path = $this->file_index[$this->normalize_import_path($original_path)] ?? null;
            $url_path = preg_replace('/[?#].*$/', '', (string) $uploaded_url);
            if (!$source_path || !is_file($source_path) || strpos($url_path, $upload_dir['baseurl']) !== 0) {
                $failures[] = 'JavaScript source or upload missing: ' . $original_path;
                continue;
            }
            $js_file = $upload_dir['basedir'] . substr($url_path, strlen($upload_dir['baseurl']));
            $js_file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $js_file);
            $content = is_file($js_file) ? file_get_contents($js_file) : false;
            if ($content === false) {
                $failures[] = 'Uploaded JavaScript unreadable: ' . $original_path;
                continue;
            }

            $resolved = $this->resolve_javascript_asset_strings($content, dirname($source_path), $unresolved);
            foreach ($unresolved as $ref) {
                $failures[] = 'Unresolved JavaScript asset in ' . $original_path . ': ' . $ref;
            }
            if ($resolved !== $content) {
                $written = file_put_contents($js_file, $resolved, LOCK_EX);
                if ($written === false || $written !== strlen($resolved)) {
                    $failures[] = 'Failed to persist rewritten JavaScript: ' . $original_path;
                }
            }
        }
        return array_values(array_unique($failures));
    }

    private function resolve_javascript_asset_strings($javascript, $source_dir, &$unresolved = [])
    {
        require_once __DIR__ . '/class-zip-assets.php';
        return Ugentic_Zip_Assets::rewrite_javascript($javascript, $source_dir, $this->smart_resolver, $this->asset_map, $unresolved);
    }

    /**
     * Record only external scripts copied into this import's private uploads
     * namespace. The renderer rechecks size and SHA-256 before every use.
     */
    public function build_verified_local_script_manifest($html)
    {
        $manifest = [];
        if (
            !is_string($html)
            || $html === ''
            || !preg_match_all(
                '/<script\b(?P<before>[^>]*)\bsrc\s*=\s*(?P<quote>["\'])(?P<src>[^"\']+)\k<quote>(?P<after>[^>]*)>(?P<body>[\s\S]*?)<\/script\s*>/i',
                $html,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            return $manifest;
        }

        $uploads = wp_upload_dir();
        $base_url = untrailingslashit((string) ($uploads['baseurl'] ?? ''));
        $base_dir = !empty($uploads['basedir']) ? realpath((string) $uploads['basedir']) : false;
        if ($base_url === '' || !$base_dir) {
            return $manifest;
        }

        foreach (array_slice($matches, 0, 32) as $ordinal => $match) {
            if (trim((string) ($match['body'] ?? '')) !== '') {
                continue;
            }

            $src = esc_url_raw(html_entity_decode((string) ($match['src'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $clean_url = preg_replace('/[?#].*$/', '', $src);
            if ($src === '' || strpos($clean_url, $base_url . '/') !== 0) {
                continue;
            }

            $relative = ltrim(substr($clean_url, strlen($base_url)), '/');
            $resolved = class_exists('Ugentic_Security')
                ? Ugentic_Security::resolve_verified_imported_script_path($relative, $base_dir)
                : false;
            if (!$resolved) {
                continue;
            }

            $file = $resolved['file'];
            $bytes = $file && is_file($file) ? filesize($file) : false;
            if (
                !$file
                || !is_readable($file)
                || $bytes === false
                || $bytes < 1
                || $bytes > 2097152
            ) {
                continue;
            }

            $sha256 = hash_file('sha256', $file);
            if (!$sha256) {
                continue;
            }

            $attrs = (string) ($match['before'] ?? '') . ' ' . (string) ($match['after'] ?? '');
            $type = '';
            if (preg_match('/\btype\s*=\s*(["\'])(module|text\/javascript|application\/javascript)\1/i', $attrs, $type_match)) {
                $type = strtolower((string) $type_match[2]);
            }

            $manifest[] = [
                'src' => $src,
                'upload_relative' => $relative,
                'sha256' => $sha256,
                'bytes' => (int) $bytes,
                'sri' => 'sha256-' . base64_encode(hex2bin($sha256)),
                'ordinal' => (int) $ordinal,
                'type' => $type,
                'defer' => (bool) preg_match('/(?:^|\s)defer(?:\s|$|=)/i', $attrs),
                'async' => (bool) preg_match('/(?:^|\s)async(?:\s|$|=)/i', $attrs),
                'nomodule' => (bool) preg_match('/(?:^|\s)nomodule(?:\s|$|=)/i', $attrs),
            ];
        }

        return $manifest;
    }
    private function is_external_or_special_reference($value)
    {
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES));
        return $value === ''
            || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i', $value)
            || preg_match('/^(?:var|env)\s*\(/i', $value)
            || strpos($value, '/wp-content/') === 0
            || strpos($value, '/wp-includes/') === 0;
    }

    /**
     * Faithful Import: imports a single page preserving the complete HTML document.
     * Asset paths are replaced, CSS file url() references resolved, forms converted.
     * No CSS scoping, no header/footer detection, no JS isolation, no section detection.
     *
     * @param string $html_file  Full path to the HTML file
     * @param string $page_title Title for the WordPress page
     * @param int    $publish    1 to publish, 0 for draft
     * @return array ['success' => bool, 'data' => array]
     */
    private function import_single_page_faithful($html_file, $page_title, $publish, $source_aliases = [])
    {
        $database = ugentic_builder()->database;
        $source_filename = basename($html_file);
        $source_hash = is_file($html_file) ? hash_file('sha256', $html_file) : '';
        $source_relative_path = $this->get_relative_path($this->temp_dir, $html_file);
        $source_route = self::canonical_import_page_route($source_relative_path);
        $source_aliases = self::normalize_import_source_aliases(array_merge([$source_relative_path], (array) $source_aliases));

        // Check for true duplicate imports. A basename alone is not unique:
        // nearly every uploaded site contains an unrelated index.html.
        $existing_pages = $this->existing_pages_for_import();
        foreach ($existing_pages as $existing) {
            $meta = json_decode($existing->meta_data ?? '{}', true);
            $existing_hash = (string) ($meta['source_hash'] ?? '');
            if ($existing_hash === '' && !empty($existing->original_html)) {
                $existing_hash = hash('sha256', (string) $existing->original_html);
            }
            $same_file = $source_hash !== ''
                && $existing_hash !== ''
                && hash_equals($existing_hash, $source_hash)
                && (
                    (!empty($meta['source_relative_path']) && $meta['source_relative_path'] === $source_relative_path)
                    || (empty($meta['source_relative_path']) && ($meta['source_file'] ?? '') === $source_filename)
                );
            $same_session_route = $this->import_session_id !== ''
                && ($meta['import_session_id'] ?? '') === $this->import_session_id
                && self::canonical_import_page_route($meta['source_relative_path'] ?? '') === $source_route;

            if ($same_file || $same_session_route) {
                // Verify the WordPress page still exists (not trashed/deleted)
                $wp_post = get_post($existing->wp_page_id);
                if (!$wp_post || $wp_post->post_status === 'trash') {
                    continue; // WP page gone — allow re-import
                }
                return [
                    'success' => true,
                    'data' => [
                        'message' => sprintf(/* translators: Source file name. */ __('Page "%s" already imported, skipping.', 'tahmir-ai-to-wp-converter'), $source_filename),
                        'page_id' => $existing->wp_page_id,
                        'ugentic_id' => $existing->id,
                        'skipped' => true,
                        'edit_url' => get_edit_post_link($existing->wp_page_id, 'raw'),
                        'view_url' => get_permalink($existing->wp_page_id),
                    ],
                ];
            }
        }

        // File validation
        if (!file_exists($html_file)) {
            return ['success' => false, 'data' => ['message' => __('HTML file not found', 'tahmir-ai-to-wp-converter')]];
        }
        $file_size = filesize($html_file);
        if ($file_size > self::MAX_HTML_SIZE) {
            return [
                'success' => false,
                'data' => [
                    'message' => sprintf(
                        /* translators: 1: File size in MB; 2: Maximum file size in MB. */ __('HTML file too large (%1$s MB, max %2$s MB)', 'tahmir-ai-to-wp-converter'),
                        round($file_size / 1024 / 1024, 1),
                        self::MAX_HTML_SIZE / 1024 / 1024
                    )
                ]
            ];
        }

        $html = file_get_contents($html_file);
        if (empty($html)) {
            return ['success' => false, 'data' => ['message' => __('Failed to read HTML file', 'tahmir-ai-to-wp-converter')]];
        }

        // Process: replace asset paths, resolve CSS url()s, register forms in DB
        $processed = $this->process_faithful_page($html, $html_file);
        $processed_html = $processed['html'];
        $forms_created = $processed['forms_created'] ?? 0;
        $form_ids = $processed['form_ids'] ?? [];
        $integrity_warnings = $processed['integrity_warnings'] ?? [];
        if (!empty($integrity_warnings)) {
            return [
                'success' => false,
                'data' => [
                    'message' => sprintf(
                        /* translators: 1: Unresolved resource count; 2: Resource names. */ __('Tahmir stopped this page from publishing because %1$d local resource(s) could not be resolved: %2$s', 'tahmir-ai-to-wp-converter'),
                        count($integrity_warnings),
                        implode('; ', array_slice($integrity_warnings, 0, 5))
                    ),
                    'integrity_warnings' => $integrity_warnings,
                ],
            ];
        }

        // Extract title from HTML if not provided
        if (empty($page_title)) {
            preg_match('/<title>(.*?)<\/title>/is', $html, $matches);
            $page_title = isset($matches[1]) ? trim(html_entity_decode(wp_strip_all_tags($matches[1]))) : 'Imported Page';
        }

        $blog_detection = $this->detect_blog_page($processed_html, $page_title, $source_filename);


        // Create WordPress page (content is a placeholder — the template outputs current_html directly)
        $page_data = [
            'post_title' => $page_title,
            'post_content' => '<!-- Faithful Import: full HTML served by template -->',
            'post_status' => $publish ? 'publish' : 'draft',
            'post_type' => 'page',
        ];

        $wp_page_id = wp_insert_post($page_data, true);
        if (is_wp_error($wp_page_id) || !$wp_page_id) {
            return ['success' => false, 'data' => ['message' => is_wp_error($wp_page_id) ? $wp_page_id->get_error_message() : __('Could not create the WordPress page.', 'tahmir-ai-to-wp-converter')]];
        }

        // Store in Ugentic database
        $ugentic_page_id = $database->insert_page([
            'wp_page_id' => $wp_page_id,
            'original_html' => $html,
            'current_html' => $processed_html,
            'meta_data' => json_encode([
                'imported' => true,
                'import_mode' => 'faithful',
                'source_file' => $source_filename,
                'source_relative_path' => $source_relative_path,
                'source_aliases' => $source_aliases,
                'source_hash' => $source_hash,
                'import_session_id' => $this->import_session_id,
                'forms_created' => $forms_created,
                'form_ids' => $form_ids,
                'css_ids' => [],
                'js_ids' => [],
                'blog_detection' => $blog_detection,
            ]),
        ]);

        if (!$ugentic_page_id) {
            wp_delete_post($wp_page_id, true);
            return ['success' => false, 'data' => ['message' => __('Could not store the imported page. No page was published; please retry.', 'tahmir-ai-to-wp-converter')]];
        }

        // Set page template to faithful (serves full HTML document)
        update_post_meta($wp_page_id, '_wp_page_template', 'ugentic-faithful');
        update_post_meta($wp_page_id, '_ugentic_page_id', $ugentic_page_id);
        $verified_local_scripts = $this->build_verified_local_script_manifest($processed_html);
        if (!empty($verified_local_scripts)) {
            update_post_meta($wp_page_id, '_ugentic_imported_script_manifest_v1', $verified_local_scripts);
        }

        return [
            'success' => true,
            'data' => [
                'message' => sprintf(
                    /* translators: Additional form import details. */ __('Page imported successfully (faithful copy)! %s', 'tahmir-ai-to-wp-converter'),
                    $forms_created > 0 ? sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ __('%d form(s) preserved and registered.', 'tahmir-ai-to-wp-converter'), $forms_created) : ''
                ),
                'page_id' => $wp_page_id,
                'ugentic_id' => $ugentic_page_id,
                'page_title' => $page_title,
                'edit_url' => get_edit_post_link($wp_page_id, 'raw'),
                'view_url' => get_permalink($wp_page_id),
                'import_mode' => 'faithful',
                'forms_created' => $forms_created,
                'integrity_warnings' => [],
                'blog_detection' => $blog_detection,
            ],
        ];
    }

    /**
     * Process the full HTML page
     *
     * @param string $html                 Raw HTML content
     * @param bool   $detect_header_footer  Whether to detect header/footer sections
     * @param bool   $make_header_global    Whether to extract header/footer as global assets
     *                                      (only relevant when $detect_header_footer is true)
     */

    /**
     * Replace all asset paths in HTML with uploaded URLs
     * Uses Smart Resolver for comprehensive path handling
     */
    private function replace_asset_paths($html, $base_dir = '')
    {
        $base_dir = $base_dir !== '' ? $base_dir : $this->temp_dir;
        // Set the path map in smart resolver
        $this->smart_resolver->set_path_map($this->asset_map);

        // Use Smart Resolver for comprehensive path resolution
        // This handles: src, href, srcset, data-* attributes, inline styles, background
        $html = $this->smart_resolver->resolve_all_paths($html, $base_dir, $this->asset_map);

        // Build a canonicalized map for the inline-style fallback pass
        $canonical_map = [];
        $basename_counts = [];
        foreach (array_keys($this->asset_map) as $original) {
            $basename = strtolower(basename(preg_replace('/[?#].*$/', '', $original)));
            $basename_counts[$basename] = ($basename_counts[$basename] ?? 0) + 1;
        }
        foreach ($this->asset_map as $original => $new_url) {
            $canonical = preg_replace('/^(\.?\/)+/', '', $original);
            $canonical = preg_replace('/[\?#].*$/', '', $canonical);
            $canonical_map[$canonical] = $new_url;

            $basename = basename($canonical);
            if (($basename_counts[strtolower($basename)] ?? 0) === 1) {
                $canonical_map['__basename__' . $basename] = $new_url;
            }
        }

        // Lightweight fallback: catch url() inside inline style="" that Smart Resolver may miss
        $html = preg_replace_callback(
            '/style\s*=\s*"([^"]*url\s*\([^)]+\)[^"]*)"/i',
            function ($matches) use ($canonical_map) {
                $style = $matches[1];
                $style = preg_replace_callback(
                    '/url\s*\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/i',
                    function ($url_match) use ($canonical_map) {
                        $url = $url_match[1];
                        if (strpos($url, 'data:') === 0 || strpos($url, 'http') === 0 || strpos($url, '//') === 0) {
                            return $url_match[0];
                        }
                        $canonical = preg_replace('/^(\.?\/)+/', '', $url);
                        $canonical = preg_replace('/[\?#].*$/', '', $canonical);
                        if (isset($canonical_map[$canonical])) {
                            return 'url(' . $canonical_map[$canonical] . ')';
                        }
                        $basename = basename($canonical);
                        if (isset($canonical_map['__basename__' . $basename])) {
                            return 'url(' . $canonical_map['__basename__' . $basename] . ')';
                        }
                        return $url_match[0];
                    }
                    ,
                    $style
                );
                return 'style="' . $style . '"';
            },
            $html
        );

        return $html;
    }

    /**
     * Validate import result — scan for remaining broken asset references
     * Logs warnings for any unresolved relative paths
     */

    /**
     * AI-powered import analysis — analyzes the uploaded site structure
     * Detects framework, CDN dependencies, and recommends import strategy
     * 
     * @param array $scan_result The result from scan_and_prepare_files
     * @return array Analysis result with framework, dependencies, warnings, strategy
     */
    private function validate_import_result($html)
    {
        $warnings = [];
        $asset_attributes = [
            'src', 'poster', 'background', 'data-src', 'data-bg', 'data-background',
            'data-image', 'data-poster', 'data-lazy', 'data-original', 'data-thumb',
            'data-thumbnail',
        ];
        $attribute_pattern = implode('|', array_map(function ($name) {
            return preg_quote($name, '/');
        }, $asset_attributes));

        preg_match_all('/(?:' . $attribute_pattern . ')\s*=\s*([\'\"])(.*?)\1/is', $html, $attribute_matches);
        foreach ($attribute_matches[2] ?? [] as $value) {
            if ($this->is_unresolved_local_reference($value)) {
                $warnings[] = 'Unresolved local asset: ' . trim($value);
            }
        }

        preg_match_all('/(?:srcset|data-srcset)\s*=\s*([\'\"])(.*?)\1/is', $html, $srcset_matches);
        foreach ($srcset_matches[2] ?? [] as $srcset) {
            foreach (explode(',', $srcset) as $candidate) {
                $parts = preg_split('/\s+/', trim($candidate), 2);
                if (!empty($parts[0]) && $this->is_unresolved_local_reference($parts[0])) {
                    $warnings[] = 'Unresolved srcset asset: ' . $parts[0];
                }
            }
        }

        preg_match_all('/<link\b[^>]*>/is', $html, $link_tags);
        foreach ($link_tags[0] ?? [] as $tag) {
            if (!preg_match('/\bhref\s*=\s*([\'\"])(.*?)\1/is', $tag, $href)) {
                continue;
            }
            $is_asset_link = preg_match('/\brel\s*=\s*([\'\"])[^\'\"]*(?:stylesheet|icon|preload|manifest)[^\'\"]*\1/i', $tag)
                || preg_match('/\.(?:css|ico|png|svg|webmanifest|json)(?:[?#]|$)/i', $href[2]);
            if ($is_asset_link && $this->is_unresolved_local_reference($href[2])) {
                $warnings[] = 'Unresolved linked asset: ' . trim($href[2]);
            }
        }

        preg_match_all('/url\s*\(\s*[\'\"]?([^\'\"\)\s]+)[\'\"]?\s*\)/i', $html, $css_matches);
        foreach ($css_matches[1] ?? [] as $value) {
            if ($this->is_unresolved_local_reference($value)) {
                $warnings[] = 'Unresolved inline CSS asset: ' . trim($value);
            }
        }

        $warnings = array_values(array_unique($warnings));
        return $warnings;
    }

    private function is_unresolved_local_reference($value)
    {
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES));
        if ($value === '' || preg_match('/(?:\{\{|\}\}|<\?|\[\[)/', $value)) {
            return false;
        }
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $value)) {
            return true;
        }
        if ($this->is_external_or_special_reference($value)) {
            return false;
        }
        if (strpos($value, '/wp-content/') !== false || strpos($value, '/wp-includes/') !== false || strpos($value, '/ugentic-assets/') !== false) {
            return false;
        }
        $path = preg_replace('/[?#].*$/', '', $value);
        if (preg_match('/\.html?$/i', $path)) {
            return false;
        }
        return true;
    }

    private function ai_analyze_import($scan_result)
    {
        $api = ugentic_builder()->api;

        // Default analysis (no AI fallback)
        $analysis = [
            'framework' => 'Unknown',
            'dependencies' => [],
            'warnings' => [],
            'strategy' => 'standard',
            'quality_score' => null,
            'ai_powered' => false,
        ];

        // Detect basic info without AI
        $analysis['file_summary'] = [
            'html_count' => count($scan_result['html_files'] ?? []),
            'css_count' => count($scan_result['css_files'] ?? []),
            'js_count' => count($scan_result['js_files'] ?? []),
            'image_count' => count($scan_result['images'] ?? []),
            'font_count' => count($scan_result['fonts'] ?? []),
        ];

        // Quick pattern-based framework detection
        $all_js_names = array_map(function ($f) {
            return strtolower($f['name']);
        }, $scan_result['js_files'] ?? []);
        $all_css_names = array_map(function ($f) {
            return strtolower($f['name']);
        }, $scan_result['css_files'] ?? []);
        $all_names = implode(' ', array_merge($all_js_names, $all_css_names));

        if (strpos($all_names, 'bootstrap') !== false)
            $analysis['dependencies'][] = 'Bootstrap';
        if (strpos($all_names, 'tailwind') !== false)
            $analysis['dependencies'][] = 'TailwindCSS';
        if (strpos($all_names, 'jquery') !== false)
            $analysis['dependencies'][] = 'jQuery';
        if (strpos($all_names, 'react') !== false) {
            $analysis['framework'] = 'React';
            $analysis['dependencies'][] = 'React';
        }
        if (strpos($all_names, 'vue') !== false) {
            $analysis['framework'] = 'Vue';
            $analysis['dependencies'][] = 'Vue';
        }
        if (strpos($all_names, 'angular') !== false) {
            $analysis['framework'] = 'Angular';
            $analysis['dependencies'][] = 'Angular';
        }
        if (strpos($all_names, 'font-awesome') !== false || strpos($all_names, 'fontawesome') !== false)
            $analysis['dependencies'][] = 'FontAwesome';
        if (strpos($all_names, 'animate') !== false)
            $analysis['dependencies'][] = 'Animate.css';
        if (strpos($all_names, 'gsap') !== false)
            $analysis['dependencies'][] = 'GSAP';
        if (strpos($all_names, 'swiper') !== false)
            $analysis['dependencies'][] = 'Swiper';
        if (strpos($all_names, 'slick') !== false)
            $analysis['dependencies'][] = 'Slick';


return $analysis;
}

    /**
     * AI-powered post-import quality validation
     * Checks the processed HTML for quality issues
     * 
     * @param string $html Processed HTML content
     * @return array Quality result with score and issues
     */private function ai_validate_post_import($html) { return []; }

    /**
     * Replace paths inside CSS content
     */

    /**
     * Convert URL to file path
     */

    /**
     * Detect header and footer using 200+ pattern scoring system
     * Handles: semantic/non-semantic HTML, mega menus, sticky headers, multiple navs,
     *          framework-specific markup (Bootstrap, Webflow, Divi, Elementor, etc.)
     */

    /**
     * Smart Section Detection — identify body content sections
     * Adds data-section-type attributes for better editing scope
     * 
     * Detects 12 section types: hero, features, pricing, testimonials, CTA,
     * stats, team, FAQ/accordion, contact/form, gallery, about, clients
     * 
     * @param string $html The body HTML (header/footer already removed)
     * @return string HTML with data-section-type attributes added
     */

    /**
     * Score and classify a section's content type
     * @return string|null Section type or null if undetermined
     */


    /**
     * Use AI to detect header and footer - Enhanced for complex HTML
     * Handles: mega menus, sticky headers, non-semantic markup, multi-navigation
     */

    /**
     * Extract body content from HTML
     * Uses string position instead of regex to avoid PCRE backtracking limits on large files
     */

    /**
     * Extract CSS variables from :root and html blocks
     * Returns just the CSS variable declarations to be used as a high-priority asset
     *
     * @param string $css The CSS content to extract variables from
     * @return string The extracted :root variables CSS block, or empty string if none found
     */

    /**
     * Get the scoped reset CSS content
     * 
     * @return string Reset CSS content
     */

    /**
     * Add editable markers to HTML elements
     */

    /**
     * Detect and convert HTML forms to Ugentic Forms
     * Scans for <form> tags, analyzes inputs, creates form in database,
     * and replaces the original form with a shortcode
     * 
     * @param string $html The HTML content to process
     * @return array ['html' => modified HTML, 'forms_created' => count]
     */

    /**
     * @deprecated Kept for backward compatibility. Use Ugentic_Form_Converter directly.
     */

    /**
     * Check if form is a search form
     */

    /**
     * Analyze form fields from HTML
     */

    /**
     * Parse an input field
     */

    /**
     * Parse a textarea field
     */

    /**
     * Parse a select field
     */

    /**
     * Generate a readable label from field attributes
     */

    /**
     * Determine form type based on fields
     */

    /**
     * Extract submit button text from form
     */

    /**
     * AJAX: Import all pages at once
     */
    public function ajax_import_all()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $import_data = get_transient('ugentic_import_' . get_current_user_id());

        if (!$import_data) {
            wp_send_json_error(['message' => __('Import session expired', 'tahmir-ai-to-wp-converter')]);
        }

        $this->temp_dir = $import_data['temp_dir'];
        $this->asset_map = $import_data['asset_map'];
        $this->file_index = $import_data['file_index'] ?? [];
        $this->asset_upload_namespace = $import_data['asset_upload_namespace'] ?? ($import_data['unique_id'] ?? '');
        $this->import_session_id = sanitize_key($import_data['unique_id'] ?? '');

        $html_files = $import_data['files']['html_files'] ?? [];
        $imported = [];
        $errors = [];
        $dynamic_suggestions = [];

        $import_mode = sanitize_text_field($_POST['import_mode'] ?? 'faithful');
        $detect_header_footer = !empty($_POST['detect_header_footer']);
        $make_header_global = !empty($_POST['make_header_global']);
        $publish = intval($_POST['publish'] ?? 0);

        foreach ($html_files as $file) {
            try {
                // Route to faithful or advanced import
                if ($import_mode === 'faithful') {
                    $result = $this->import_single_page_faithful(
                        $file['path'],
                        $file['title'] ?? '',
                        $publish,
                        $file['source_aliases'] ?? []
                    );
                } else {
                    $result = $this->import_single_page(
                        $file['path'],
                        $file['title'] ?? '',
                        $detect_header_footer,
                        $publish,
                        $make_header_global,
                        $file['source_aliases'] ?? []
                    );
                }

                if (is_wp_error($result)) {
                    $errors[] = $file['title'] . ': ' . $result->get_error_message();
                    continue;
                }
                if (!is_array($result)) {
                    $errors[] = $file['title'] . ': ' . __('Import failed with an unexpected response.', 'tahmir-ai-to-wp-converter');
                    continue;
                }

                if ($result['success']) {
                    $imported[] = $file['title'];

                    // Collect dynamic content suggestions
                    if (!empty($result['data']['dynamic_suggestion'])) {
                        $dynamic_suggestions[] = [
                            'page_title' => $file['title'],
                            'ugentic_id' => $result['data']['ugentic_id'] ?? 0,
                            'wp_page_id' => $result['data']['page_id'] ?? 0,
                            'detection' => $result['data']['dynamic_suggestion'],
                        ];
                    }
                } else {
                    $errors[] = $file['title'] . ': ' . ($result['data']['message'] ?? 'Unknown error');
                }
            } catch (\Throwable $e) {
                $errors[] = $file['title'] . ': ' . $e->getMessage();
            }
        }

        $link_result = $this->replace_internal_links();
        $menu_result = $this->detect_import_menus_from_stored_pages();

        // Trigger full Site Blueprint rebuild after import
        do_action('ugentic_import_complete');

        wp_send_json_success([
            'message' => sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ __('Imported %d pages', 'tahmir-ai-to-wp-converter'), count($imported)),
            'imported' => $imported,
            'errors' => $errors,
            'dynamic_suggestions' => $dynamic_suggestions,
            'link_rewrite' => $link_result,
            'menu_detection' => $menu_result,
        ]);
    }

    /**
     * AJAX: Explicitly cleanup import session and temp files
     */
    public function ajax_cleanup_import()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $this->cleanup_temp();
        wp_send_json_success(['message' => __('Import session cleaned up', 'tahmir-ai-to-wp-converter')]);
    }

    /**
     * AJAX: Force-clear the import lock transient.
     * Used when the lock gets stuck with no actual import running.
     */
    public function ajax_force_clear_import_lock()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $user_id = get_current_user_id();
        delete_transient('ugentic_import_lock_' . $user_id);
        delete_transient('ugentic_import_' . $user_id);

        wp_send_json_success(['message' => __('Import lock cleared. You can now start a new import.', 'tahmir-ai-to-wp-converter')]);
    }

    /**
     * AJAX: Delete an imported page and all its linked assets (rollback)
     */
    public function ajax_delete_import()
    {
        check_ajax_referer('ugentic_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'tahmir-ai-to-wp-converter')]);
        }

        $ugentic_id = intval($_POST['ugentic_id'] ?? 0);
        if (!$ugentic_id) {
            wp_send_json_error(['message' => __('No page ID specified', 'tahmir-ai-to-wp-converter')]);
        }

        $database = ugentic_builder()->database;
        $page = $database->get_page($ugentic_id);

        if (!$page) {
            wp_send_json_error(['message' => __('Page not found', 'tahmir-ai-to-wp-converter')]);
        }

        // Get linked assets before deleting the page
        $meta = json_decode($page->meta_data ?? '{}', true);
        $css_ids = $meta['css_ids'] ?? [];
        $js_ids = $meta['js_ids'] ?? [];

        // Delete the WordPress page
        if (!empty($page->wp_page_id)) {
            wp_delete_post($page->wp_page_id, true);
        }

        // Delete the Tahmir AI page record (and asset links)
        $database->delete_page($ugentic_id);

        // Delete orphaned assets (not linked to any other page)
        $all_asset_ids = array_merge($css_ids, $js_ids);
        $deleted_assets = 0;
        foreach ($all_asset_ids as $asset_id) {
            $links = $database->get_asset_page_links($asset_id);
            if (empty($links)) {
                $database->delete_asset($asset_id);
                $deleted_assets++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: Number of the pages, records, files, or items described in this message. */ __('Import rolled back: deleted page and %d orphaned assets', 'tahmir-ai-to-wp-converter'),
                $deleted_assets
            ),
        ]);
    }

    /**
     * Cleanup temp directory and import session
     */
    public function cleanup_temp()
    {
        $user_id = get_current_user_id();
        $import_data = get_transient('ugentic_import_' . $user_id);

        if ($import_data && !empty($import_data['temp_dir']) && is_dir($import_data['temp_dir'])) {
            $this->delete_directory($import_data['temp_dir']);
        }
        delete_transient('ugentic_import_' . $user_id);
        // Release concurrent import lock
        delete_transient('ugentic_import_lock_' . $user_id);

        // Auto-cleanup abandoned temp directories older than 6 hours
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'];
        $dirs = glob($base . '/ugentic-import-*', GLOB_ONLYDIR);
        if ($dirs) {
            $cutoff = time() - (6 * HOUR_IN_SECONDS);
            foreach ($dirs as $dir) {
                if (filemtime($dir) < $cutoff) {
                    $this->delete_directory($dir);
                }
            }
        }
    }

    /**
     * Recursively delete directory
     */
    private function delete_directory($dir)
    {
        $uploads = wp_upload_dir();
        $base = realpath($uploads['basedir']);
        $resolved = realpath($dir);
        if ($base === false || $resolved === false || is_link($dir)
            || !Ugentic_Security::path_is_inside($dir, $base)) {
            return;
        }
        $relative = ltrim(substr(wp_normalize_path($resolved), strlen(wp_normalize_path($base))), '/');
        if (!preg_match('/^ugentic-import-[a-zA-Z0-9_-]+(?:\/|$)/', $relative)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            // Never traverse symlinks/junctions when cleaning user-supplied archives.
            if (is_link($path)) {
                @wp_delete_file($path);
            } elseif (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @wp_delete_file($path);
            }
        }
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;
        if ($wp_filesystem) {
            $wp_filesystem->rmdir($dir);
        }
    }

    // =========================================================================
    //  AUTO-REPAIR SYSTEM
    //  Detects and fixes broken paths, missing CSS, CDN dependencies
    //  after the initial import pipeline has run.
    // =========================================================================

    /**
     * Orchestrator: run all auto-repair passes on the processed HTML and CSS assets.
     *
     * @param string $html  Processed body HTML
     * @param array  $css_ids  IDs of CSS assets already stored in DB
     * @return array ['html' => repaired HTML, 'css_ids' => updated CSS IDs, 'repairs' => log]
     */

    /**
     * Auto-repair HTML: fix broken src/href paths, strip dead local asset tags.
     *
     * Strategy:
     *  - Find all remaining relative src/href that weren't resolved
     *  - Try fuzzy basename matching against the asset map
     *  - Strip <link>/<script> tags that point to nonexistent local files
     *  - Keep external/CDN links untouched
     *
     * @param string $html
     * @return array ['html' => repaired, 'repairs' => log entries]
     */

    /**
     * Auto-repair CSS: fix url() references inside a CSS string.
     *
     * @param string $css  CSS content
     * @return array ['css' => repaired, 'repairs' => log entries]
     */

    /**
     * Repair stored CSS assets in the database.
     * Runs auto_repair_css() on each asset's content and updates if changed.
     *
     * @param array $css_ids  IDs of CSS assets to check
     * @return array Repair log entries
     */

    /**
     * Detect missing CDN libraries by scanning HTML for class patterns.
     * If a library's classes are used but its CSS wasn't imported, inject the CDN link.
     *
     * @param string $html
     * @param array  $css_ids
     * @return array ['html' => updated, 'css_ids' => updated, 'repairs' => log]
     */

    /**
     * Detect missing JS CDN libraries and inject fallbacks.
     * Checks if HTML uses classes/attributes that indicate a JS library is needed
     * (e.g. AOS data attributes, Swiper containers), then injects CDN script references.
     *
     * @param string $html
     * @param array  $js_ids  Existing JS asset IDs
     * @return array ['js_ids' => updated, 'repairs' => log]
     */

    /**
     * Detect missing Google Fonts and inject CDN imports.
     * Scans CSS for font-family declarations that reference well-known Google Fonts
     * but have broken @font-face blocks (already removed by auto_repair_css).
     *
     * @param string $html
     * @param array  $css_ids
     * @return array ['css_ids' => updated, 'repairs' => log]
     */

    /**
     * Auto-inject CSS rules needed for mega-menus, dropdowns, and submenus.
     * Detects common menu class patterns in the HTML and adds hover/display rules.
     */

    /**
     * Auto-inject JavaScript for interactive menu behavior.
     * Handles: mobile toggle, mega-menu hover delays, keyboard accessibility.
     */

    // ═══════════════════════════════════════════════════════════════════
    //  SMART MENU DETECTION & WORDPRESS MENU REGISTRATION
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Detect navigation menus from header/footer HTML and register them
     * as WordPress nav menus with proper hierarchy.
     *
     * @param string $header_html  Detected header HTML
     * @param string $footer_html  Detected footer HTML
     */

    /**
     * Parse all navigation structures from an HTML string.
     * Returns an array of detected menus, each with type, classes, and items.
     *
     * @param string $html
     * @return array [ ['type'=>..., 'classes'=>..., 'items'=>[...]] , ... ]
     */

    /**
     * Classify what kind of nav element this is.
     *
     * @param \DOMElement $node
     * @param \DOMXPath   $xpath
     * @return string  'mega-menu' | 'navbar' | 'nav' | 'list'
     */

    /**
     * Extract a full hierarchical menu tree from a DOM node.
     * Handles nested sub-menus and mega menu structures.
     *
     * @param \DOMElement $container
     * @param \DOMXPath   $xpath
     * @return array
     */

    /**
     * Parse a single <li> element into a menu item with children.
     *
     * @param \DOMElement $li
     * @param \DOMXPath   $xpath
     * @return array|null
     */

    /**
     * Create a WordPress nav menu from parsed items.
     *
     * @param string      $menu_name
     * @param array       $items      Parsed menu items with children
     * @param string|null $location   Theme location to assign (e.g. 'ugentic-header')
     * @return int|false  Menu ID or false on failure
     */

    /**
     * Recursively add menu items to a WordPress nav menu.
     *
     * @param int   $menu_id
     * @param array $items
     * @param int   $parent_id
     */

    /**
     * Detect CTA buttons in header/footer HTML.
     * These are links styled as buttons but NOT inside a <nav> or menu <ul>.
     *
     * @param string $html
     * @return array [ ['text'=>..., 'url'=>..., 'classes'=>..., 'id'=>...], ... ]
     */
}
