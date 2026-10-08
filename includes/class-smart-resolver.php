<?php
/**
 * Smart Path Resolver for Tahmir AI
 * Handles all path resolution, CSS namespacing, and JS isolation for zero-error HTML imports
 * 
 * @package Ugentic_Builder
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Smart_Resolver
{

    /**
     * Upload directory URL
     */
    private $upload_url;

    /**
     * Upload directory path
     */
    private $upload_path;

    /**
     * Asset path map for tracking resolved paths
     */
    private $path_map = [];

    /** Canonical suffix lookup, rebuilt only when the import inventory changes. */
    private $path_index = [];

    /**
     * CSS namespace prefix
     */
    private $css_namespace = 'ugentic-page';

    /**
     * Constructor
     */
    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->upload_url = $upload_dir['baseurl'] . '/ugentic/';
        $this->upload_path = $upload_dir['basedir'] . '/ugentic/';

        // Ensure upload directory exists
        if (!file_exists($this->upload_path)) {
            wp_mkdir_p($this->upload_path);
        }
    }

    /**
     * Resolve all asset paths in HTML content
     * Handles: images, CSS, JS, fonts, videos, srcset, data attributes, inline styles
     *
     * @param string $html HTML content
     * @param string $base_dir Base directory for resolving relative paths
     * @param array $uploaded_assets Map of original paths to uploaded URLs
     * @return string HTML with resolved paths
     */
    public function resolve_all_paths($html, $base_dir, $uploaded_assets = [])
    {
        $this->set_path_map($uploaded_assets);

        // 1. Resolve src/href attributes (images, stylesheets, scripts)
        $html = $this->resolve_src_href($html, $base_dir);

        // 2. Resolve srcset for responsive images
        $html = $this->resolve_srcset($html, $base_dir);

        // 3. Resolve data-* attributes with paths
        $html = $this->resolve_data_attributes($html, $base_dir);

        // 4. Resolve inline style url() references
        $html = $this->resolve_inline_styles($html, $base_dir);

        // 5. Resolve background/poster attributes
        $html = $this->resolve_background_attributes($html, $base_dir);

        return $html;
    }

    /**
     * Resolve src and href attributes
     * Skips internal HTML page links (handled by menu auto-update)
     */
    private function resolve_src_href($html, $base_dir)
    {
        // Match src="..." and href="..." attributes
        $patterns = [
            '/(src\s*=\s*["\'])([^"\']+)(["\'])/i',
            '/(href\s*=\s*["\'])([^"\']+)(["\'])/i',
        ];

        foreach ($patterns as $pattern) {
            $html = preg_replace_callback($pattern, function ($matches) use ($base_dir) {
                $attr = $matches[1];
                $original_path = $matches[2];
                $quote = $matches[3];

                // Browser-rendered imports may already have expanded a ZIP path
                // such as assets/logo.png to https://example.test/assets/logo.png.
                // Prefer a known uploaded asset before treating absolute URLs as external.
                $mapped = $this->match_uploaded_asset($original_path);
                if ($mapped !== null) {
                    return $attr . $mapped . $quote;
                }

                // Skip absolute URLs, data URIs, anchors, mailto, tel
                if ($this->is_external_or_special($original_path)) {
                    return $matches[0];
                }

                // Skip internal HTML page links - these will be auto-updated to WordPress permalinks
                // after all pages are imported
                if (preg_match('/\.html?$/i', $original_path) || preg_match('/\.html?\?/i', $original_path) || preg_match('/\.html?#/i', $original_path)) {
                    return $matches[0]; // Keep original link for now
                }

                // href is also used for page navigation. Only rewrite it when
                // it names a known uploaded asset (handled above) or clearly
                // has a front-end asset extension. This prevents links such as
                // href="/", href="about/", and logo links from becoming local
                // filesystem paths during import.
                if (stripos($attr, 'href') !== false && !$this->is_asset_href($original_path)) {
                    return $matches[0];
                }

                // Only resolve asset paths (images, css, js, fonts, etc.)
                $resolved = $this->resolve_path($original_path, $base_dir);
                return $attr . $resolved . $quote;
            }, $html);
        }

        return $html;
    }

    private function is_asset_href($path)
    {
        $path = preg_replace('/[?#].*$/', '', trim((string) $path));
        return (bool) preg_match(
            '/\.(?:css|js|mjs|cjs|json|map|webmanifest|wasm|jpg|jpeg|png|gif|webp|svg|ico|bmp|avif|tiff?|woff2?|ttf|otf|eot|mp4|webm|ogv|mov|mp3|wav|ogg|oga|m4a|aac|flac|vtt|pdf)$/i',
            $path
        );
    }

    /**
     * Resolve srcset attribute for responsive images
     */
    private function resolve_srcset($html, $base_dir)
    {
        return preg_replace_callback(
            '/(srcset\s*=\s*["\'])([^"\']+)(["\'])/i',
                function ($matches) use ($base_dir) {
            $attr = $matches[1];
            $srcset = $matches[2];
            $quote = $matches[3];

            // Parse srcset (format: "path1 1x, path2 2x" or "path1 100w, path2 200w")
            $sources = explode(',', $srcset);
            $resolved_sources = [];

            foreach ($sources as $source) {
                $source = trim($source);
                $parts = preg_split('/\s+/', $source, 2);
                $path = $parts[0];
                $descriptor = isset($parts[1]) ? ' ' . $parts[1] : '';

                $mapped = $this->match_uploaded_asset($path);
                if ($mapped !== null) {
                    $path = $mapped;
                } elseif (!$this->is_external_or_special($path)) {
                    $path = $this->resolve_path($path, $base_dir);
                }

                $resolved_sources[] = $path . $descriptor;
            }

            return $attr . implode(', ', $resolved_sources) . $quote;
        },
            $html
        );
    }

    /**
     * Resolve data-* attributes that contain paths
     */
    private function resolve_data_attributes($html, $base_dir)
    {
        // Common data attributes that contain paths
        $data_attrs = [
            'data-src',
            'data-srcset',
            'data-bg',
            'data-background',
            'data-image',
            'data-poster',
            'data-lazy',
            'data-original',
            'data-thumb',
            'data-thumbnail'
        ];

        foreach ($data_attrs as $attr) {
            $pattern = '/(' . preg_quote($attr, '/') . '\s*=\s*["\'])([^"\']+)(["\'])/i';
            $html = preg_replace_callback($pattern, function ($matches) use ($base_dir) {
                $attr_part = $matches[1];
                $original_path = $matches[2];
                $quote = $matches[3];

                $mapped = $this->match_uploaded_asset($original_path);
                if ($mapped !== null) {
                    return $attr_part . $mapped . $quote;
                }

                if ($this->is_external_or_special($original_path)) {
                    return $matches[0];
                }

                $resolved = $this->resolve_path($original_path, $base_dir);
                return $attr_part . $resolved . $quote;
            }, $html);
        }

        return $html;
    }

    /**
     * Resolve url() references in inline styles
     */
    private function resolve_inline_styles($html, $base_dir)
    {
        return preg_replace_callback(
            '/(style\s*=\s*)(["\'])(.*?)\2/is',
                function ($matches) use ($base_dir) {
            $attr = $matches[1];
            $quote = $matches[2];
            $style = $matches[3];

            // Resolve url() in the style
            $style = $this->resolve_css_urls($style, $base_dir);

            return $attr . $quote . $style . $quote;
        },
            $html
        );
    }

    /**
     * Resolve background and poster attributes
     */
    private function resolve_background_attributes($html, $base_dir)
    {
        $attrs = ['background', 'poster'];

        foreach ($attrs as $attr) {
            $pattern = '/(' . $attr . '\s*=\s*["\'])([^"\']+)(["\'])/i';
            $html = preg_replace_callback($pattern, function ($matches) use ($base_dir) {
                $attr_part = $matches[1];
                $original_path = $matches[2];
                $quote = $matches[3];

                $mapped = $this->match_uploaded_asset($original_path);
                if ($mapped !== null) {
                    return $attr_part . $mapped . $quote;
                }

                if ($this->is_external_or_special($original_path)) {
                    return $matches[0];
                }

                $resolved = $this->resolve_path($original_path, $base_dir);
                return $attr_part . $resolved . $quote;
            }, $html);
        }

        return $html;
    }

    /**
     * Resolve a single path to absolute URL
     *
     * @param string $path Original path (relative or absolute)
     * @param string $base_dir Base directory for resolution
     * @return string Resolved absolute URL
     */
    public function resolve_path($path, $base_dir)
    {
        $is_contextual_relative = !preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|/)#i', trim((string) $path));
        $mapped = $this->match_uploaded_asset($path);
        if ($mapped !== null) {
            return $mapped;
        }

        // Check if already in path map
        if (!$is_contextual_relative && isset($this->path_map[$path])) {
            return $this->path_map[$path];
        }

        // Try normalized relative path (strip leading ./ and /)
        $normalized_key = preg_replace('/^\.\//', '', $path);
        $normalized_key = ltrim($normalized_key, '/');
        if (!$is_contextual_relative && $normalized_key !== $path && isset($this->path_map[$normalized_key])) {
            return $this->path_map[$normalized_key];
        }

        // Clean up the path
        $path = trim($path);

        // Handle query strings
        $query = '';
        if (strpos($path, '?') !== false) {
            list($path, $query) = explode('?', $path, 2);
            $query = '?' . $query;
        }

        // Handle anchors
        $anchor = '';
        if (strpos($path, '#') !== false) {
            list($path, $anchor) = explode('#', $path, 2);
            $anchor = '#' . $anchor;
        }

        // Resolve relative path
        $resolved = $this->make_absolute($path, $base_dir);

        // Check if path is in path map after resolution
        if (isset($this->path_map[$resolved])) {
            return $this->path_map[$resolved] . $query . $anchor;
        }

        // Try matching by relative path extracted from the resolved absolute path
        // The asset_map stores keys as relative paths (e.g. 'images/logo.png')
        // but $resolved is an absolute filesystem path.
        // Use longest (most directory-specific) match to avoid basename collisions
        // (e.g. 'images/logo.png' vs 'css/logo.png')
        $best_match = $this->indexed_asset_match($resolved);
        if ($best_match !== null) {
            return $best_match . $query . $anchor;
        }

        // Fallback: convert absolute filesystem paths under ABSPATH to HTTP URLs
        // This prevents raw filesystem paths from leaking into HTML output
        $abspath = rtrim(str_replace('\\', '/', ABSPATH), '/');
        $resolved_normalized = str_replace('\\', '/', $resolved);
        if (strpos($resolved_normalized, $abspath) === 0) {
            $relative_to_wp = substr($resolved_normalized, strlen($abspath));
            return site_url($relative_to_wp) . $query . $anchor;
        }

        return $resolved . $query . $anchor;
    }

    /**
     * Match a source URL/path to an asset uploaded from the import package.
     *
     * This deliberately accepts absolute URLs when their URL path matches a
     * known archive path. Dynamic sites commonly expand relative image paths
     * in the browser before Tahmir captures their rendered HTML.
     *
     * @param string $path Source path or URL.
     * @return string|null Uploaded URL, including the source query/fragment.
     */
    private function match_uploaded_asset($path)
    {
        if (empty($this->path_map) || !is_string($path)) {
            return null;
        }

        $path = trim(html_entity_decode($path, ENT_QUOTES));
        if ($path === '' || preg_match('/^(?:data:|blob:|mailto:|tel:|javascript:|#)/i', $path)) {
            return null;
        }

        // Relative paths must be resolved against the current HTML/CSS file's
        // directory first. A direct map lookup here would confuse, for example,
        // root/images/logo.png with pages/about/images/logo.png.
        if (!preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|/)#i', $path)) {
            return null;
        }

        $suffix_pos = strcspn($path, '?#');
        $suffix = $suffix_pos < strlen($path) ? substr($path, $suffix_pos) : '';
        $source = substr($path, 0, $suffix_pos);
        $source_key = $this->normalize_asset_map_key($source);
        if ($source_key === '') {
            return null;
        }

        $best_url = $this->indexed_asset_match($source_key);

        return $best_url !== null ? $best_url . $suffix : null;
    }

    private function indexed_asset_match($path)
    {
        $key = $this->normalize_asset_map_key($path);
        while ($key !== '') {
            if (array_key_exists($key, $this->path_index)) return $this->path_index[$key];
            $slash = strpos($key, '/');
            if ($slash === false) break;
            $key = substr($key, $slash + 1);
        }
        return null;
    }

    /**
     * Normalize archive paths and absolute URL paths for stable comparison.
     */
    private function normalize_asset_map_key($path)
    {
        $path = trim(str_replace('\\', '/', (string) $path));
        if ($path === '') {
            return '';
        }

        if (preg_match('#^(?:https?:)?//#i', $path)) {
            $parsed_path = parse_url(strpos($path, '//') === 0 ? 'https:' . $path : $path, PHP_URL_PATH);
            $path = is_string($parsed_path) ? $parsed_path : '';
        }

        $path = rawurldecode(preg_replace('/[?#].*$/', '', $path));
        $path = preg_replace('#/+#', '/', $path);
        $path = preg_replace('#^(?:\./)+#', '', $path);
        $path = ltrim($path, '/');

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * Convert relative path to absolute path
     */
    public function make_absolute($path, $base_dir)
    {
        // Already absolute URL
        if (preg_match('/^https?:\/\//i', $path) || preg_match('/^\/\//i', $path)) {
            return $path;
        }

        // Normalize base_dir
        $base_dir = rtrim($base_dir, '/\\') . '/';

        // Handle root-relative paths (/assets/...)
        if (strpos($path, '/') === 0) {
            // For root-relative, use the base directory without subdirs
            $root = $this->find_root_dir($base_dir);
            $full_path = $root . ltrim($path, '/');
        }
        // Handle relative paths (./assets, ../assets, assets)
        else {
            // Remove leading ./
            if (strpos($path, './') === 0) {
                $path = substr($path, 2);
            }

            // Handle ../ by going up directories
            while (strpos($path, '../') === 0) {
                $path = substr($path, 3);
                $base_dir = dirname(rtrim($base_dir, '/')) . '/';
            }

            $full_path = $base_dir . $path;
        }

        // Normalize the path
        $full_path = $this->normalize_path($full_path);

        return $full_path;
    }

    /**
     * Find the root directory of an imported site
     */
    private function find_root_dir($path)
    {
        // Walk up until we find a common web root indicator
        $indicators = ['index.html', 'index.htm', '.htaccess'];
        $current = rtrim($path, '/\\');

        while ($current && $current !== dirname($current)) {
            foreach ($indicators as $indicator) {
                if (file_exists($current . '/' . $indicator)) {
                    return $current . '/';
                }
            }
            $current = dirname($current);
        }

        return $path;
    }

    /**
     * Normalize a path (resolve ../ and ./)
     */
    private function normalize_path($path)
    {
        $parts = explode('/', $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($normalized);
            }
            else {
                $normalized[] = $part;
            }
        }

        $result = implode('/', $normalized);

        // Preserve leading / for absolute paths
        if (strpos($path, '/') === 0) {
            $result = '/' . $result;
        }

        return $result;
    }

    /**
     * Check if path is external or special (data URI, anchor, etc.)
     */
    private function is_external_or_special($path)
    {
        $path = trim($path);

        // External URLs
        if (preg_match('/^https?:\/\//i', $path) || preg_match('/^\/\//i', $path)) {
            return true;
        }

        // Data URIs
        if (strpos($path, 'data:') === 0) {
            return true;
        }

        // Anchors only
        if (strpos($path, '#') === 0) {
            return true;
        }

        // mailto/tel
        if (preg_match('/^(mailto|tel|javascript):/i', $path)) {
            return true;
        }

        // Empty
        if ($path === '' || $path === 'about:blank') {
            return true;
        }

        return false;
    }

    /**
     * Resolve url() paths in CSS content
     *
     * @param string $css CSS content
     * @param string $css_dir Directory where CSS file is located
     * @return string CSS with resolved paths
     */
    public function resolve_css_urls($css, $css_dir)
    {
        return preg_replace_callback(
            '/url\s*\(\s*(["\']?)([^)"\'\s]+)\1\s*\)/i',
                function ($matches) use ($css_dir) {
            $quote = $matches[1];
            $path = trim($matches[2]);

            $mapped = $this->match_uploaded_asset($path);
            if ($mapped !== null) {
                return 'url(' . $quote . $mapped . $quote . ')';
            }

            if ($this->is_external_or_special($path)) {
                return $matches[0];
            }

            $resolved = $this->resolve_path($path, $css_dir);
            return 'url(' . $quote . $resolved . $quote . ')';
        },
            $css
        );
    }

    /**
     * Namespace CSS to prevent conflicts with WordPress theme
     * All selectors are prefixed with .ugentic-page
     *
     * @param string $css CSS content
     * @param string $namespace Optional custom namespace
     * @param int $specificity_boost Level of specificity boost (0=none, 1=double, 2=triple)
     * @return string Namespaced CSS
     */
    public function namespace_css($css, $namespace = null, $specificity_boost = 0)
    {
        if ($namespace === null) {
            $namespace = $this->css_namespace;
        }

        // Build the namespace selector with optional specificity boost
        // e.g., ".ugentic-page" becomes ".ugentic-page.ugentic-page" at boost level 1
        $base_namespace = '.' . $namespace;
        $boosted_namespace = str_repeat($base_namespace, $specificity_boost + 1);

        // Parse CSS and namespace selectors
        $namespaced = '';
        $pos = 0;
        $len = strlen($css);

        while ($pos < $len) {
            // Find the start of a rule or at-rule
            $next_brace = strpos($css, '{', $pos);

            if ($next_brace === false) {
                $namespaced .= substr($css, $pos);
                break;
            }

            $selector_block = substr($css, $pos, $next_brace - $pos);

            // Check if this is an at-rule
            $trimmed = trim($selector_block);
            if (strpos($trimmed, '@') === 0) {
                // Handle different at-rules
                if (preg_match('/^@(media|supports|document)/i', $trimmed)) {
                    // Find matching closing brace for at-rule
                    $at_rule_end = $this->find_matching_brace($css, $next_brace);
                    $at_rule_content = substr($css, $next_brace + 1, $at_rule_end - $next_brace - 1);

                    // Recursively namespace content inside at-rule
                    $namespaced_content = $this->namespace_css($at_rule_content, $namespace, $specificity_boost);
                    $namespaced .= $selector_block . '{' . $namespaced_content . '}';
                    $pos = $at_rule_end + 1;
                    continue;
                }
                elseif (preg_match('/^@(keyframes|font-face|import|charset|namespace)/i', $trimmed)) {
                    // Keep these as-is
                    $rule_end = $this->find_matching_brace($css, $next_brace);
                    $namespaced .= substr($css, $pos, $rule_end - $pos + 1);
                    $pos = $rule_end + 1;
                    continue;
                }
            }

            // Regular CSS rule - namespace the selectors
            $selectors = explode(',', $selector_block);
            $namespaced_selectors = [];

            foreach ($selectors as $selector) {
                $selector = trim($selector);
                if (empty($selector))
                    continue;

                // Don't namespace :root or html/body level selectors
                if (preg_match('/^(:root|html|body)\s*$/i', $selector)) {
                    // Convert to scoped selector (boosted)
                    $namespaced_selectors[] = $boosted_namespace;
                }
                elseif (preg_match('/^(html|body)\s+/i', $selector)) {
                    // Replace html/body with namespace (boosted)
                    $selector = preg_replace('/^(html|body)\s+/i', '', $selector);
                    $namespaced_selectors[] = $boosted_namespace . ' ' . $selector;
                }
                else {
                    // Regular selector - prefix with namespace (boosted)
                    $namespaced_selectors[] = $boosted_namespace . ' ' . $selector;
                }
            }

            $namespaced .= implode(', ', $namespaced_selectors) . ' {';

            // Find the closing brace and copy the declaration block
            $close_brace = $this->find_matching_brace($css, $next_brace);
            $namespaced .= substr($css, $next_brace + 1, $close_brace - $next_brace);
            $pos = $close_brace + 1;
        }

        return $namespaced;
    }

    /**
     * Find the matching closing brace
     */
    private function find_matching_brace($css, $open_pos)
    {
        $depth = 1;
        $pos = $open_pos + 1;
        $len = strlen($css);

        while ($pos < $len && $depth > 0) {
            $char = $css[$pos];
            if ($char === '{') {
                $depth++;
            }
            elseif ($char === '}') {
                $depth--;
            }
            $pos++;
        }

        return $pos - 1;
    }

    /**
     * Isolate JavaScript to prevent global scope pollution
     * Uses a shared scope so inter-script dependencies are preserved
     * (Script A defines initSlider(), Script B calls it — both must share scope)
     *
     * @param string $js JavaScript content (may be multiple concatenated scripts)
     * @param int $page_id Page ID for unique scoping
     * @return string Isolated JavaScript
     */
    public function isolate_javascript($js, $page_id)
    {
        // Check if already wrapped
        if (strpos($js, '/* ugentic-isolated */') !== false) {
            return $js;
        }

        // Handle jQuery
        $jquery_setup = '';
        if (preg_match('/\bjQuery\b|\$\s*\(/', $js)) {
            $jquery_setup = "    var $ = jQuery.noConflict(true); var jQuery = $;\n";
        }

        // document.write shim — prevents legacy scripts from destroying the page
        // Converts document.write() calls to safe DOM insertions
        $doc_write_shim = '';
        if (preg_match('/document\.write\s*\(/', $js)) {
            $doc_write_shim = "    // Safe document.write shim\n";
            $doc_write_shim .= "    var _ugOrigWrite = document.write;\n";
            $doc_write_shim .= "    document.write = document.writeln = function(s) {\n";
            $doc_write_shim .= "        if (document.readyState === 'loading') {\n";
            $doc_write_shim .= "            _ugOrigWrite.call(document, s);\n";
            $doc_write_shim .= "        } else {\n";
            $doc_write_shim .= "            var _ugTmp = document.createElement('div');\n";
            $doc_write_shim .= "            _ugTmp.innerHTML = s;\n";
            $doc_write_shim .= "            while (_ugTmp.firstChild) {\n";
            $doc_write_shim .= "                document.body.appendChild(_ugTmp.firstChild);\n";
            $doc_write_shim .= "            }\n";
            $doc_write_shim .= "        }\n";
            $doc_write_shim .= "    };\n\n";
        }

        // Single shared-scope IIFE: all scripts share the same closure so
        // inter-script function calls work, but nothing leaks to window.*
        $isolated = "/* ugentic-isolated */\n";
        $isolated .= "(function(window, document) {\n";
        $isolated .= $jquery_setup;
        $isolated .= $doc_write_shim;
        $isolated .= "\n    // ── Imported scripts (shared scope) ──\n";
        $isolated .= "    " . str_replace("\n", "\n    ", trim($js));
        $isolated .= "\n\n})(window, document);\n";

        return $isolated;
    }

    /**
     * Audit all paths in an imported page and report issues
     *
     * @param int $page_id Page ID to audit
     * @return array Audit results with issues found
     */
    public function audit_paths($page_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ugentic_pages';
        $page = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $page_id));

        if (!$page) {
            return ['error' => 'Page not found'];
        }

        $issues = [];
        $html = $page->body_content;

        // Find all src, href, srcset values
        preg_match_all('/(src|href|srcset)\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);

        foreach ($matches[2] as $index => $path) {
            if ($this->is_external_or_special($path)) {
                continue;
            }

            // Check if it's a valid WordPress URL or file exists
            if (!$this->validate_path($path)) {
                $issues[] = [
                    'type' => 'broken_path',
                    'attribute' => $matches[1][$index],
                    'path' => $path,
                    'suggestion' => $this->suggest_fix($path)
                ];
            }
        }

        return [
            'page_id' => $page_id,
            'page_title' => $page->title,
            'issues_count' => count($issues),
            'issues' => $issues
        ];
    }

    /**
     * Validate if a path resolves to an existing resource
     */
    private function validate_path($path)
    {
        // Check if it's a WordPress URL
        $site_url = site_url();
        if (strpos($path, $site_url) === 0) {
            // Convert URL to file path
            $file_path = str_replace($site_url, ABSPATH, $path);
            return file_exists($file_path);
        }

        // Check uploads directory
        $upload_dir = wp_upload_dir();
        if (strpos($path, $upload_dir['baseurl']) === 0) {
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $path);
            return file_exists($file_path);
        }

        return true; // Assume external paths are valid
    }

    /**
     * Suggest a fix for a broken path
     */
    private function suggest_fix($path)
    {
        // Extract filename
        $filename = basename($path);

        // Search for the file in uploads
        $upload_dir = wp_upload_dir();
        $found = $this->search_file($upload_dir['basedir'], $filename);

        if ($found) {
            return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $found);
        }

        return null;
    }

    /**
     * Search for a file recursively
     */
    private function search_file($dir, $filename)
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..')
                continue;

            $path = $dir . '/' . $file;

            if (is_file($path) && $file === $filename) {
                return $path;
            }

            if (is_dir($path)) {
                $found = $this->search_file($path, $filename);
                if ($found)
                    return $found;
            }
        }

        return null;
    }

    /**
     * Set the path map (for use during import)
     */
    public function set_path_map($map)
    {
        if ($this->path_map === $map) return;
        $this->path_map = $map;
        $this->path_index = [];
        foreach ($map as $key => $url) {
            $key = $this->normalize_asset_map_key($key);
            if ($key === '' || !is_string($url) || $url === '') continue;
            if (array_key_exists($key, $this->path_index) && $this->path_index[$key] !== $url) {
                $this->path_index[$key] = null;
            } else {
                $this->path_index[$key] = $url;
            }
        }
    }

    /**
     * Get the path map
     */
    public function get_path_map()
    {
        return $this->path_map;
    }

    /**
     * Add a path to the map
     */
    public function add_to_path_map($original, $resolved)
    {
        $map = $this->path_map;
        $map[$original] = $resolved;
        $this->set_path_map($map);
    }
}
