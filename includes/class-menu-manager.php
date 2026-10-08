<?php
/**
 * Menu Manager Class
 * Visual menu editing from the global header
 * 
 * @package Ugentic_Builder
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Menu_Manager
{

    private static $instance = null;

    /**
     * Singleton instance
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    /** Static flag to prevent recursive sync loops (replaces define() which crashes on re-entry) */
    private static $syncing_menu = false;private function __construct() {}

    /** Queue of menu IDs to propagate at shutdown */
    private $deferred_menu_ids = [];

    /** Request-local permalink lookup used while parsing and syncing menus. */
    private $slug_lookup_cache = null;

    /**
     * Parse navigation items from the global header HTML
     * Uses DOMDocument to extract links from <nav> and menu containers
     * 
     * @return array Structured menu items [{text, url, classes, children[]}]
     */

    /**
     * Parse navigation items from the global footer HTML
     * 
     * @return array Structured menu items [{text, url, classes, children[]}]
     */

    /**
     * Parse menus from the given location
     * 
     * @param string $location 'header' or 'footer'
     * @return array
     */

    /**
     * Parse nav items from an HTML string
     * 
     * @param string $html
     * @return array
     */

    private function normalize_menu_compare_value($value)
    {
        return strtolower(trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function normalize_menu_text($value)
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }

    private function normalize_menu_url_identity($url, $slug_map)
    {
        $resolved = $this->resolve_menu_url((string) $url, $slug_map);
        if ($resolved === '' || $resolved === '#') {
            return $resolved;
        }

        $parts = wp_parse_url(html_entity_decode($resolved, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!is_array($parts)) {
            return $this->normalize_menu_compare_value($resolved);
        }

        $host = $this->normalize_host($parts['host'] ?? '');
        $path = rawurldecode($parts['path'] ?? '');
        $path = '/' . trim(preg_replace('/\.(?:html?|php|aspx?)$/i', '', $path), '/');
        if ($path !== '/') {
            $path = untrailingslashit($path);
        }

        return strtolower(($host !== '' ? $host : $this->normalize_host(wp_parse_url(home_url('/'), PHP_URL_HOST))) . $path);
    }

    /**
     * Resolve imported URLs before comparing menu items so legacy .html links
     * and their WordPress permalinks are treated as the same destination.
     */

    /**
     * Some imported sites use flat menu markup like nav > div > a instead of ul/li.
     * Find the smallest container whose direct anchor children are real menu links.
     */

    /**
     * Extract menu items from a DOM node (nav, ul, or menu container)
     * 
     * @param DOMElement $node
     * @param DOMXPath $xpath
     * @return array
     */

    /**
     * Parse a single <li> element into a menu item structure.
     * Supports recursive sub-menu parsing for mega menus of arbitrary depth.
     * 
     * @param DOMElement $li
     * @param DOMXPath $xpath
     * @param int $depth Current nesting depth (max 5 to prevent runaway recursion)
     * @return array|null
     */

    /**
     * Recursively parse a <li> element for deep sub-menus (mega menus).
     * Unlike parse_li_element(), this does NOT skip nested <li>s — it's called
     * explicitly for children so the "is nested" check is not needed.
     *
     * @param DOMElement $li
     * @param DOMXPath $xpath
     * @param int $depth Current nesting depth
     * @return array|null
     */

    /**
     * Save menu items back to the global header HTML
     * Preserves the original nav structure and CSS classes
     * 
     * @param array $items Menu items in [{text, url, children[]}] format
     * @return bool Success
     */

    /**
     * Provide a valid editable navigation shell when a site has no global asset.
     */

    /**
     * Sync HTML menu items to a WordPress nav menu.
     * Called after save_menu_items() to keep WP menus in sync.
     *
     * @param array  $items    Parsed menu items
     * @param string $location 'header' or 'footer'
     */

    /**
     * Recursively add items to a WordPress nav menu.
     */

    /**
     * Build a slug-based lookup map of all Tahmir AI pages.
     * Maps post slugs and source filenames (without extension) to permalinks.
     *
     * @return array [slug => permalink]
     */
    private function build_slug_lookup()
    {
        if (is_array($this->slug_lookup_cache)) {
            return $this->slug_lookup_cache;
        }

        $db = ugentic_builder()->database;
        // Menu resolution needs only page identity and import metadata. Loading
        // original_html/current_html for every page made large Framer sites
        // exceed PHP's memory limit during the final link pass.
        $pages = method_exists($db, 'get_page_summaries')
            ? $db->get_page_summaries(['limit' => 1000])
            : $db->get_all_pages(['limit' => 1000]);
        $map = [];
        $source_hosts = [];
        foreach ($pages as $page) {
            if (empty($page->wp_page_id)) {
                continue;
            }
            $wp_post = get_post($page->wp_page_id);
            if ($wp_post) {
                $permalink = get_permalink($wp_post->ID);
                $map[$wp_post->post_name] = $permalink;
                $canonical_slug = preg_replace('/-\d+$/', '', $wp_post->post_name);
                if ($canonical_slug !== $wp_post->post_name) {
                    $map[$canonical_slug] = $permalink;
                }
                foreach ($this->build_title_slug_aliases($wp_post->post_title) as $title_alias) {
                    $map[$title_alias] = $permalink;
                }
                // Also map from source filename without extension
                $meta = json_decode($page->meta_data ?? '{}', true);
                if (!empty($meta['source_file'])) {
                    $name = pathinfo($meta['source_file'], PATHINFO_FILENAME);
                    $map[$name] = $permalink;
                    $map[basename($meta['source_file'])] = $permalink;
                }
                $source_url = $meta['clone_source_url'] ?? get_post_meta($wp_post->ID, '_ugentic_clone_source_url', true);
                if (!empty($source_url)) {
                    $host = $this->normalize_host(wp_parse_url($source_url, PHP_URL_HOST) ?: '');
                    if ($host !== '') {
                        $source_hosts[$host] = true;
                    }
                    $path = wp_parse_url($source_url, PHP_URL_PATH);
                    $source_slug = $path ? sanitize_title(basename(trim($path, '/'))) : '';
                    if ($source_slug !== '') {
                        $map[$source_slug] = $permalink;
                    }
                    if ($path) {
                        $source_path = ltrim(rawurldecode($path), '/');
                        $source_file = basename($source_path);
                        $map[$source_path] = $permalink;
                        $map[$source_file] = $permalink;
                        $map[preg_replace('/\.(?:html?|php|aspx?)$/i', '', $source_file)] = $permalink;
                    }
                }
                if ((string) get_post_meta($wp_post->ID, '_ugentic_import_role', true) === 'blog_listing') {
                    $map['blog'] = $permalink;
                    $map['blog.html'] = $permalink;
                    $map['blog.htm'] = $permalink;
                }
            }
        }

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => 500,
            'meta_key' => '_ugentic_import_role',
            'meta_value' => 'blog_single',
        ]);
        foreach ($posts as $post) {
            $permalink = get_permalink($post->ID);
            if (!$permalink) {
                continue;
            }
            $map[$post->post_name] = $permalink;
            $source_file = get_post_meta($post->ID, '_ugentic_clone_source_file', true);
            if (!empty($source_file)) {
                $map[pathinfo($source_file, PATHINFO_FILENAME)] = $permalink;
            }
            $source_url = get_post_meta($post->ID, '_ugentic_clone_source_url', true);
            $path = $source_url ? wp_parse_url($source_url, PHP_URL_PATH) : '';
            $host = $source_url ? $this->normalize_host(wp_parse_url($source_url, PHP_URL_HOST) ?: '') : '';
            if ($host !== '') {
                $source_hosts[$host] = true;
            }
            if ($path) {
                $map[sanitize_title(basename(trim($path, '/')))] = $permalink;
            }
        }

        $normalized_map = [];
        foreach ($map as $key => $value) {
            $normalized_map[strtolower(html_entity_decode((string) $key, ENT_QUOTES | ENT_HTML5, 'UTF-8'))] = $value;
        }
        $normalized_map['__source_hosts'] = $source_hosts;

        $this->slug_lookup_cache = $normalized_map;
        return $this->slug_lookup_cache;
    }

    private function build_title_slug_aliases($title)
    {
        $title = trim(html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title === '') {
            return [];
        }

        $segments = preg_split('/\s*(?:\x{2013}|\x{2014}|\|)\s*/u', $title);
        $aliases = [sanitize_title($title)];
        if (is_array($segments) && !empty($segments[0])) {
            $aliases[] = sanitize_title($segments[0]);
        }
        return array_values(array_unique(array_filter($aliases)));
    }

    private function normalize_host($host)
    {
        $host = strtolower(trim((string) $host));
        return preg_replace('/^www\./', '', $host);
    }

    /**
     * Resolve a potentially relative or broken menu URL to a WordPress page URL.
     * Handles folder-based paths (e.g. "Section/page-name") and URLs that contain
     * the uploads directory path from import extraction.
     *
     * @param string $url      The URL to resolve
     * @param array  $slug_map Slug-to-permalink lookup map
     * @return string Resolved URL
     */
    private function resolve_menu_url($url, $slug_map)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (empty($url) || $url === '#' || $url[0] === '#' || $url[0] === '?') {
            return $url;
        }

        if (preg_match('/^(mailto:|tel:|javascript:|data:)/i', $url)) {
            return $url;
        }

        $fragment = '';
        if (($hash_pos = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hash_pos);
            $url = substr($url, 0, $hash_pos);
        }
        $query = '';
        if (($query_pos = strpos($url, '?')) !== false) {
            $query = substr($url, $query_pos);
            $url = substr($url, 0, $query_pos);
        }
        $suffix = $query . $fragment;

        if (preg_match('/^https?:\/\//i', $url)) {
            $source_hosts = is_array($slug_map['__source_hosts'] ?? null) ? $slug_map['__source_hosts'] : [];
            $host = $this->normalize_host(wp_parse_url($url, PHP_URL_HOST) ?: '');
            $is_source_host = $host !== '' && isset($source_hosts[$host]);
            $is_home_host = $host !== '' && $host === $this->normalize_host(wp_parse_url(home_url('/'), PHP_URL_HOST) ?: '');
            $is_import_asset = strpos($url, '/wp-content/uploads/') !== false || strpos($url, 'ugentic-import') !== false;
            $path = wp_parse_url($url, PHP_URL_PATH) ?: '/';
            $is_legacy_page = (bool) preg_match('/\.(?:html?|php|aspx?)$/i', $path);
            if (!$is_source_host && !$is_import_asset && !($is_home_host && $is_legacy_page)) {
                return $url . $suffix;
            }

            $normalized = trim(rawurldecode($path), '/');
            if ($normalized === '') {
                return home_url('/') . $suffix;
            }

            $last = basename($normalized);
            $without_extension = preg_replace('/\.(?:html?|php|aspx?)$/i', '', $last);
            foreach ([$normalized, $last, $without_extension, sanitize_title($without_extension)] as $candidate) {
                $candidate = strtolower(trim((string) $candidate));
                if ($candidate !== '' && isset($slug_map[$candidate])) {
                    return $slug_map[$candidate] . $suffix;
                }
            }

            if ($is_import_asset || $is_source_host) {
                return '#'; // Can't resolve broken uploads URL
            }
            return $url . $suffix;
        }

        // Relative path: normalize it
        $normalized = preg_replace('/^(\.?\/)+/', '', $url);
        $normalized = rtrim($normalized, '/');
        if ($normalized === '') {
            return home_url('/') . $suffix;
        }

        $last = basename($normalized);
        $without_extension = preg_replace('/\.(?:html?|php|aspx?)$/i', '', $last);
        foreach ([$normalized, $last, $without_extension, sanitize_title($without_extension)] as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate !== '' && isset($slug_map[$candidate])) {
                return $slug_map[$candidate] . $suffix;
            }
        }

        return $url . $suffix; // Leave as-is if it cannot be resolved.
    }

    /**
     * Get CTA buttons for a location.
     *
     * @param string $location 'header' or 'footer'
     * @return array
     */

    /**
     * Save a single CTA button configuration.
     *
     * @param string $location  'header' or 'footer'
     * @param int    $index     Button index
     * @param array  $config    ['text'=>..., 'url'=>..., 'visible'=>bool]
     * @return bool
     */

    /**
     * Update a CTA button in the actual header/footer HTML.
     *
     * @param string $location
     * @param array  $button
     */

    /**
     * Get all Tahmir AI pages for the page selector dropdown
     * 
     * @return array [{title, url}]
     */

    // ==================== FAITHFUL IMPORT MENU DETECTION ====================

    /**
     * Parse navigation menus from a full HTML document (faithful import).
     * Unlike parse_nav_from_html() which works on header/footer fragments,
     * this handles complete HTML documents with <html>, <head>, <body> tags.
     *
     * Detection strategy (in order):
     *  1. <header> → <nav> → <ul>
     *  2. <nav> anywhere in <body>
     *  3. Common class patterns: .navbar, .nav-menu, .main-menu, .site-nav
     *  4. Any <ul> with 3+ <li>/<a> children (fallback)
     *
     * For footer menus, the same strategy is applied within <footer>.
     *
     * @param string $html Full HTML document
     * @return array ['header' => [...items], 'footer' => [...items]]
     */

    /**
     * Repair imported header links without reserializing the full document.
     *
     * Exact duplicate responsive controls are removed only when their label,
     * resolved destination, and CSS classes all match. Distinct mobile/desktop
     * controls and repeated links in separate navigation structures are kept.
     */
    public function repair_imported_navigation_html($html, &$stats = null)
    {
        $stats = [
            'links_resolved' => 0,
            'duplicates_removed' => 0,
        ];
        if (!is_string($html) || trim($html) === '') {
            return $html;
        }

        $slug_map = $this->build_slug_lookup();
        $pattern = '/<header\b[^>]*>[\s\S]*?<\/header>/i';
        if (!preg_match($pattern, $html)) {
            $pattern = '/<nav\b[^>]*>[\s\S]*?<\/nav>/i';
        }

        return preg_replace_callback($pattern, function ($fragment_match) use ($slug_map, &$stats) {
            $seen_controls = [];

            return preg_replace_callback(
                '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>[\s\S]*?<\/a>/i',
                function ($anchor_match) use ($slug_map, &$stats, &$seen_controls) {
                    $anchor_html = $anchor_match[0];
                    $raw_url = html_entity_decode(trim((string) $anchor_match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $resolved_url = $this->resolve_menu_url($raw_url, $slug_map);

                    if ($resolved_url !== '' && $resolved_url !== $raw_url) {
                        $escaped_url = esc_attr($resolved_url);
                        $anchor_html = preg_replace_callback(
                            '/(\bhref\s*=\s*)(["\']).*?\2/i',
                            function ($href_match) use ($escaped_url) {
                                return $href_match[1] . $href_match[2] . $escaped_url . $href_match[2];
                            },
                            $anchor_html,
                            1
                        );
                        $stats['links_resolved']++;
                    }

                    $class_value = '';
                    if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $anchor_html, $class_match)) {
                        $classes = preg_split('/\s+/', strtolower(trim($class_match[2])));
                        $classes = array_values(array_unique(array_filter($classes)));
                        sort($classes);
                        $class_value = implode(' ', $classes);
                    }

                    $text = $this->normalize_menu_text(
                        html_entity_decode(wp_strip_all_tags($anchor_html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    );
                    $is_explicit_control = $class_value !== ''
                        && preg_match('/(?:^|\s)(?:button|btn|cta|desktop[\w-]*|mobile[\w-]*|menu-(?:cta|contact)|nav-(?:cta|contact))(?:\s|$)/i', $class_value);
                    if (!$is_explicit_control || $text === '' || $resolved_url === '' || $resolved_url === '#') {
                        return $anchor_html;
                    }

                    $identity = $text . '|' . $this->normalize_menu_url_identity($resolved_url, $slug_map) . '|' . $class_value;
                    if (isset($seen_controls[$identity])) {
                        $stats['duplicates_removed']++;
                        return '';
                    }

                    $seen_controls[$identity] = true;
                    return $anchor_html;
                },
                $fragment_match[0]
            );
        }, $html);
    }

    /**
     * Detect the primary (header) navigation from a full document.
     *
     * @param DOMDocument $doc
     * @param DOMXPath    $xpath
     * @return array Menu items
     */

    /**
     * Detect footer navigation from a full document.
     *
     * @param DOMDocument $doc
     * @param DOMXPath    $xpath
     * @return array Menu items
     */

    /**
     * Import menus from a full faithful-mode HTML document into WordPress native menus.
     * Parses the HTML for header and footer navs, then syncs them as WP menus.
     *
     * @param string $html Full HTML document (with WordPress URLs already resolved)
     * @return array ['header_items' => int, 'footer_items' => int, 'menus_created' => int]
     */

    /**
     * Public sitewide menu save entrypoint for authenticated MCP writes.
     *
     * Keeps the same behavior as the admin/editor flows: sanitize the menu tree,
     * update stored Tahmir AI pages, update the global header/footer asset, and
     * sync the corresponding WordPress nav menu.
     */

    // ==================== AJAX HANDLERS ====================

    /**
     * AJAX: Get menu items from global header
     */

    /**
     * AJAX: Save menu items to global header
     */

    /**
     * AJAX: Save menu items across ALL pages (cross-page propagation)
     * Finds nav elements in every Tahmir AI page and updates them.
     */

    /**
     * Update nav element in a page's HTML string.
     * Uses DOMDocument to find the nav <ul> and rebuild it with new items.
     *
     * @param string $html     Full page HTML
     * @param array  $items    Menu items [{text, url, children[]}]
     * @param string $location 'header' or 'footer'
     * @return string|false Updated HTML or false if nav not found
     */

    /**
     * Propagate a sanitized menu tree to all stored Tahmir AI pages.
     */

    /**
     * Hook: When a WP menu is saved from wp-admin/nav-menus.php,
     * queue it for deferred propagation to all Tahmir AI page HTML.
     * Uses deferred (shutdown) propagation so all menu items are saved first.
     */

    /**
     * Hook: Individual menu item update – also queue the parent menu.
     */

    /**
     * Shutdown hook: propagate all queued menu changes.
     * Runs after WP has finished saving all menu items.
     */

    /**
     * Actually propagate a WP menu to all Tahmir AI pages.
     * Extracted from on_wp_menu_update for reuse.
     */

    /**
     * Convert WordPress nav menu items (flat array with parent IDs) to a nested tree.
     */

    /**
     * AJAX: Re-sync WP menus from page HTML.
     * Reads the nav structure from page HTML and populates the WP nav menu.
     */

    /**
     * Sync WP nav menus from page HTML.
     * Reads the actual nav structure from Tahmir AI pages and syncs to WordPress menus.
     */

    /**
     * Parse footer navigation from HTML.
     */

    /**
     * AJAX: Add a single menu item
     */

    /**
     * AJAX: Delete a menu item
     */

    /**
     * Sanitize menu items recursively
     * 
     * @param array $items
     * @return array
     */

    // ==================== CTA BUTTON AJAX HANDLERS ====================

    /**
     * AJAX: Get CTA buttons for a location
     */

    /**
     * AJAX: Save a single CTA button
     */
}

// Initialize
add_action('plugins_loaded', function () {
    Ugentic_Menu_Manager::instance();
});
