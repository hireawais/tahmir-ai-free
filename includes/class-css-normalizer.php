<?php
/**
 * CSS Normalizer - Transform legacy CSS for modern rendering
 * Converts deprecated properties, normalizes units, handles vendor prefixes
 * 
 * @package Ugentic_Builder
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_CSS_Normalizer
{

    /**
     * Legacy property conversions
     * Maps old properties to their modern equivalents
     */
    private $legacy_property_map = [
        // Opacity conversions
        '-moz-opacity' => 'opacity',
        '-khtml-opacity' => 'opacity',

        // Border radius
        '-webkit-border-radius' => 'border-radius',
        '-moz-border-radius' => 'border-radius',
        '-webkit-border-top-left-radius' => 'border-top-left-radius',
        '-webkit-border-top-right-radius' => 'border-top-right-radius',
        '-webkit-border-bottom-left-radius' => 'border-bottom-left-radius',
        '-webkit-border-bottom-right-radius' => 'border-bottom-right-radius',
        '-moz-border-radius-topleft' => 'border-top-left-radius',
        '-moz-border-radius-topright' => 'border-top-right-radius',
        '-moz-border-radius-bottomleft' => 'border-bottom-left-radius',
        '-moz-border-radius-bottomright' => 'border-bottom-right-radius',

        // Box shadow
        '-webkit-box-shadow' => 'box-shadow',
        '-moz-box-shadow' => 'box-shadow',

        // Transform
        '-webkit-transform' => 'transform',
        '-moz-transform' => 'transform',
        '-ms-transform' => 'transform',
        '-o-transform' => 'transform',

        // Transition
        '-webkit-transition' => 'transition',
        '-moz-transition' => 'transition',
        '-o-transition' => 'transition',

        // Flexbox (old syntax)
        '-webkit-box-orient' => 'flex-direction',
        '-webkit-box-pack' => 'justify-content',
        '-webkit-box-align' => 'align-items',

        // Box sizing
        '-webkit-box-sizing' => 'box-sizing',
        '-moz-box-sizing' => 'box-sizing',

        // User select
        '-webkit-user-select' => 'user-select',
        '-moz-user-select' => 'user-select',
        '-ms-user-select' => 'user-select',

        // Appearance
        '-webkit-appearance' => 'appearance',
        '-moz-appearance' => 'appearance',
    ];

    /**
     * Properties to remove (unsupported or problematic)
     */
    private $remove_properties = [
        'behavior', // IE-specific HTC behaviors
        'scrollbar-face-color',
        'scrollbar-shadow-color',
        'scrollbar-highlight-color',
        'scrollbar-3dlight-color',
        'scrollbar-darkshadow-color',
        'scrollbar-track-color',
        'scrollbar-arrow-color',
        '-ms-filter', // Old IE filter
        'filter:progid', // IE DirectX filters (partial match)
    ];

    /**
     * Unit conversion table (legacy to px)
     */
    private $unit_conversions = [
        'pt' => 1.333333, // 1pt = 1.33px
        'pc' => 16, // 1pc = 16px
        'cm' => 37.795275, // 1cm = ~37.8px
        'mm' => 3.779528, // 1mm = ~3.78px
        'in' => 96, // 1in = 96px
    ];

    /**
     * Modern font stack fallbacks
     */
    private $font_fallbacks = [
        'Arial' => 'Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        'Helvetica' => 'Helvetica, Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'Verdana' => 'Verdana, Geneva, -apple-system, BlinkMacSystemFont, sans-serif',
        'Georgia' => 'Georgia, "Times New Roman", Times, serif',
        'Times New Roman' => '"Times New Roman", Times, Georgia, serif',
        'Times' => 'Times, "Times New Roman", Georgia, serif',
        'Courier New' => '"Courier New", Courier, "Lucida Console", monospace',
        'Courier' => 'Courier, "Courier New", "Lucida Console", monospace',
        'Tahoma' => 'Tahoma, Verdana, Arial, sans-serif',
        'Trebuchet MS' => '"Trebuchet MS", Arial, Helvetica, sans-serif',
        'Comic Sans MS' => '"Comic Sans MS", cursive, sans-serif',
        'Impact' => 'Impact, "Arial Black", sans-serif',
        'Lucida Sans' => '"Lucida Sans", "Lucida Sans Unicode", "Lucida Grande", sans-serif',
    ];

    /**
     * Run full normalization pipeline
     * 
     * @param string $css CSS content
     * @param array $options Normalization options from legacy detector
     * @return string Normalized CSS
     */
    public function normalize($css, $options = [])
    {
        $defaults = [
            'convert_legacy_properties' => true,
            'normalize_units' => true,
            'normalize_vendor_prefixes' => true,
            'modernize_fonts' => true,
            'specificity_boost' => 0,
            'remove_ie_hacks' => true,
        ];

        $options = array_merge($defaults, $options);

        // Strip BOM (Byte Order Mark) — U+FEFF at start of CSS breaks :root selector
        $css = preg_replace('/^\x{FEFF}/u', '', $css);
        $css = str_replace("\xEF\xBB\xBF", '', $css);

        // Stage 1: Remove IE-specific hacks and dangerous code
        if ($options['remove_ie_hacks']) {
            $css = $this->remove_ie_hacks($css);
        }

        // Stage 2: Convert legacy properties
        if ($options['convert_legacy_properties']) {
            $css = $this->convert_legacy_properties($css);
        }

        // Stage 3: Handle filter:alpha(opacity) specially
        $css = $this->convert_ie_opacity($css);

        // Stage 4: Normalize units
        if ($options['normalize_units']) {
            $css = $this->normalize_units($css);
        }

        // Stage 5: Normalize vendor prefixes
        if ($options['normalize_vendor_prefixes']) {
            $css = $this->normalize_vendor_prefixes($css);
        }

        // Stage 6: Modernize font stacks
        if ($options['modernize_fonts']) {
            $css = $this->modernize_font_stacks($css);
        }

        // Stage 7: Clean up
        $css = $this->cleanup_css($css);

        return $css;
    }

    /**
     * Remove IE-specific CSS hacks
     */
    private function remove_ie_hacks($css)
    {
        // Remove * html selectors (IE6 hack)
        $css = preg_replace('/\*\s+html\s+[^{]+\{[^}]*\}/i', '', $css);

        // Remove _property hacks (IE6)
        $css = preg_replace('/_[a-z-]+\s*:[^;]+;?/i', '', $css);

        // Remove expression() (dangerous)
        $css = preg_replace('/expression\s*\([^)]*\)/i', 'inherit', $css);

        // Remove behavior: url() (HTC files)
        $css = preg_replace('/behavior\s*:\s*url\s*\([^)]*\)\s*;?/i', '', $css);

        // Remove progid: DirectX filters
        $css = preg_replace('/filter\s*:\s*progid:[^;]+;?/i', '', $css);

        // Remove -ms-filter
        $css = preg_replace('/-ms-filter\s*:\s*["\'][^"\']*["\']\s*;?/i', '', $css);

        // Remove IE scrollbar colors
        foreach ($this->remove_properties as $prop) {
            if (strpos($prop, ':') === false) {
                $css = preg_replace('/' . preg_quote($prop, '/') . '\s*:[^;]+;?/i', '', $css);
            }
        }

        return $css;
    }

    /**
     * Convert legacy CSS properties to modern equivalents
     */
    public function convert_legacy_properties($css)
    {
        foreach ($this->legacy_property_map as $old => $new) {
            // Match the property in declarations
            $pattern = '/(' . preg_quote($old, '/') . ')\s*:/i';
            $css = preg_replace($pattern, $new . ':', $css);
        }

        return $css;
    }

    /**
     * Convert IE filter:alpha(opacity=X) to standard opacity
     */
    private function convert_ie_opacity($css)
    {
        // Match filter: alpha(opacity=XX)
        $pattern = '/filter\s*:\s*alpha\s*\(\s*opacity\s*=\s*(\d+)\s*\)\s*;?/i';

        return preg_replace_callback($pattern, function ($matches) {
            $opacity = intval($matches[1]) / 100;
            return 'opacity: ' . $opacity . ';';
        }, $css);
    }

    /**
     * Normalize legacy units to px
     */
    public function normalize_units($css)
    {
        foreach ($this->unit_conversions as $unit => $multiplier) {
            // Match values with this unit (but not in font-family, content, etc.)
            $pattern = '/:\s*([^;{]*?)(\d+(?:\.\d+)?)\s*' . preg_quote($unit, '/') . '(\s*[;}])/i';

            $css = preg_replace_callback($pattern, function ($matches) use ($multiplier) {
                $value = floatval($matches[2]) * $multiplier;
                $px_value = round($value, 1);

                // Remove trailing .0
                if ($px_value == intval($px_value)) {
                    $px_value = intval($px_value);
                }

                return ':' . $matches[1] . $px_value . 'px' . $matches[3];
            }, $css);
        }

        return $css;
    }

    /**
     * Ensure vendor-prefixed properties have unprefixed equivalents
     */
    public function normalize_vendor_prefixes($css)
    {
        // Properties that need unprefixed versions
        $prefixed_properties = [
            'transform', 'transition', 'animation', 'animation-name',
            'animation-duration', 'animation-timing-function', 'animation-delay',
            'animation-iteration-count', 'animation-direction', 'animation-fill-mode',
            'animation-play-state', 'box-shadow', 'border-radius', 'user-select',
            'box-sizing', 'background-size', 'background-clip', 'appearance',
            'column-count', 'column-gap', 'column-rule', 'columns',
        ];

        foreach ($prefixed_properties as $property) {
            // Find rules that have prefixed versions but no unprefixed
            $prefixed_pattern = '/(-webkit-|-moz-|-ms-|-o-)' . preg_quote($property, '/') . '\s*:\s*([^;]+);/i';

            $css = preg_replace_callback($prefixed_pattern, function ($matches) use ($property, $css) {
                $full_match = $matches[0];
                $value = $matches[2];

                // Check if unprefixed version already exists nearby
                if (preg_match('/[^-]' . preg_quote($property, '/') . '\s*:/i', $css)) {
                    return $full_match;
                }

                // Add unprefixed version after the prefixed one
                return $full_match . "\n    " . $property . ': ' . $value . ';';
            }, $css);
        }

        return $css;
    }

    /**
     * Modernize font stacks with system font fallbacks
     */
    public function modernize_font_stacks($css)
    {
        // Match font-family declarations
        $pattern = '/font-family\s*:\s*([^;}]+)/i';

        return preg_replace_callback($pattern, function ($matches) {
            $font_stack = $matches[1];

            // Check each known font and add fallbacks if needed
            foreach ($this->font_fallbacks as $font => $fallback) {
                // Check if this font is used alone or without proper fallbacks
                $font_quoted = preg_quote($font, '/');

                // Match font alone or at start of stack without system fonts
                if (preg_match('/^["\']?' . $font_quoted . '["\']?\s*$/i', trim($font_stack)) ||
                preg_match('/^["\']?' . $font_quoted . '["\']?\s*,\s*(?!.*(-apple-system|BlinkMacSystemFont|system-ui|Segoe UI))/i', $font_stack)) {

                    // If font is alone, replace with full fallback stack
                    if (preg_match('/^["\']?' . $font_quoted . '["\']?\s*$/i', trim($font_stack))) {
                        return 'font-family: ' . $fallback;
                    }
                }
            }

            return $matches[0];
        }, $css);
    }

    /**
     * Scope all CSS selectors to a container element
     * Prefixes every selector with the container selector so imported CSS
     * only affects content inside that container — prevents theme conflicts.
     *
     * Handles:
     * - Regular selectors (prefixed with container)
     * - :root, html, body selectors (transformed to container)
     * - @media queries (selectors inside are prefixed)
     * - @keyframes, @font-face (left untouched)
     * - @import, @charset (left untouched)
     * - Comma-separated selectors (each part prefixed)
     *
     * @param string $css Raw CSS content
     * @param string $container Container selector, e.g. '#ugentic-page-content'
     * @return string Scoped CSS
     */
    public function scope_to_container($css, $container = '#ugentic-page-content')
    {
        if (empty(trim($css))) {
            return $css;
        }

        // Preserve @keyframes blocks — extract, placeholder, re-insert at end
        $keyframes_blocks = [];
        $css = preg_replace_callback(
            '/@(?:-webkit-|-moz-)?keyframes\s+[^\{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/is',
            function ($match) use (&$keyframes_blocks) {
            $placeholder = '/* __UGENTIC_KF_' . count($keyframes_blocks) . '__ */';
            $keyframes_blocks[$placeholder] = $match[0];
            return $placeholder;
        },
            $css
        );

        // Preserve @font-face blocks
        $fontface_blocks = [];
        $css = preg_replace_callback(
            '/@font-face\s*\{[^}]*\}/is',
            function ($match) use (&$fontface_blocks) {
            $placeholder = '/* __UGENTIC_FF_' . count($fontface_blocks) . '__ */';
            $fontface_blocks[$placeholder] = $match[0];
            return $placeholder;
        },
            $css
        );

        // Preserve @import and @charset rules
        $at_imports = [];
        $css = preg_replace_callback(
            '/@(?:import|charset)\s+[^;]+;/i',
            function ($match) use (&$at_imports) {
            $placeholder = '/* __UGENTIC_IMP_' . count($at_imports) . '__ */';
            $at_imports[$placeholder] = $match[0];
            return $placeholder;
        },
            $css
        );

        // Process @media blocks: scope selectors INSIDE @media rules
        $css = preg_replace_callback(
            '/@media\s+([^{]+)\{((?:[^{}]*\{[^{}]*\})*[^{}]*)\}/is',
            function ($match) use ($container) {
            $media_query = $match[1];
            $inner_css = $match[2];
            $scoped_inner = $this->_scope_selectors($inner_css, $container);
            return '@media ' . $media_query . '{' . $scoped_inner . '}';
        },
            $css
        );

        // Process @supports blocks: scope selectors inside
        $css = preg_replace_callback(
            '/@supports\s+([^{]+)\{((?:[^{}]*\{[^{}]*\})*[^{}]*)\}/is',
            function ($match) use ($container) {
            $supports_condition = $match[1];
            $inner_css = $match[2];
            $scoped_inner = $this->_scope_selectors($inner_css, $container);
            return '@supports ' . $supports_condition . '{' . $scoped_inner . '}';
        },
            $css
        );

        // Process @layer blocks: scope selectors inside
        // Handles both named layers (@layer name { ... }) and anonymous (@layer { ... })
        $css = preg_replace_callback(
            '/@layer\s*([^{]*)\{((?:[^{}]*\{[^{}]*\})*[^{}]*)\}/is',
            function ($match) use ($container) {
            $layer_name = $match[1];
            $inner_css = $match[2];
            $scoped_inner = $this->_scope_selectors($inner_css, $container);
            return '@layer ' . $layer_name . '{' . $scoped_inner . '}';
        },
            $css
        );

        // Process top-level selectors (everything not inside an @-rule)
        // At this point @media/@supports/@layer are already processed, @keyframes/@font-face are placeholders
        $css = $this->_scope_selectors($css, $container);

        // Re-insert preserved blocks
        foreach ($at_imports as $placeholder => $original) {
            $css = str_replace($placeholder, $original, $css);
        }
        foreach ($fontface_blocks as $placeholder => $original) {
            $css = str_replace($placeholder, $original, $css);
        }
        foreach ($keyframes_blocks as $placeholder => $original) {
            $css = str_replace($placeholder, $original, $css);
        }

        return $css;
    }

    /**
     * Internal: scope CSS selectors within a block of rules
     *
     * @param string $css CSS rules (without outer @-rule wrappers)
     * @param string $container Container selector
     * @return string Scoped CSS
     */
    private function _scope_selectors($css, $container)
    {
        // Match selector { declarations } pairs
        return preg_replace_callback(
            '/([^{}\/]+?)\{([^{}]*)\}/s',
            function ($match) use ($container) {
            $selector_block = trim($match[1]);
            $declarations = $match[2];

            // Skip placeholder comments
            if (strpos($selector_block, '__UGENTIC_') !== false) {
                return $match[0];
            }

            // Skip @-rules that slipped through (e.g. nested @layer)
            if (strpos($selector_block, '@') === 0) {
                return $match[0];
            }

            // Split comma-separated selectors
            $selectors = array_map('trim', explode(',', $selector_block));
            $scoped = [];

            foreach ($selectors as $selector) {
                if (empty($selector))
                    continue;

                // Skip selectors that already contain the container (prevent double-scoping)
                if (strpos($selector, $container) !== false) {
                    $scoped[] = $selector;
                    continue;
                }

                $lower = strtolower(trim($selector));

                // :root, html, body → transform to container selector
                if (preg_match('/^(:root|html|body)$/i', $lower)) {
                    $scoped[] = $container;
                }
                // html body, html, body combined → container
                elseif (preg_match('/^(html\s+body|html,\s*body)$/i', $lower)) {
                    $scoped[] = $container;
                }
                // body.some-class or html.some-class → container.some-class
                elseif (preg_match('/^(?:body|html)(\.[a-zA-Z][\w-]*)(.*)$/i', $selector, $m)) {
                    $scoped[] = $container . $m[1] . $m[2];
                }
                // body > .child or body .child → container > .child or container .child
                elseif (preg_match('/^(?:body|html|:root)\s*([\s>~+].+)$/i', $selector, $m)) {
                    $scoped[] = $container . ' ' . ltrim($m[1]);
                }
                // * (universal selector) → container *
                elseif ($lower === '*') {
                    $scoped[] = $container . ' *';
                }
                // Regular selector → prepend container
                else {
                    $scoped[] = $container . ' ' . $selector;
                }
            }

            return implode(",\n", $scoped) . ' {' . $declarations . '}';
        },
            $css
        );
    }

    /**
     * Boost CSS specificity by repeating namespace selector
     * 
     * @param string $css CSS content
     * @param int $level Boost level (1 = double, 2 = triple)
     * @param string $namespace The namespace class
     * @return string CSS with boosted specificity
     */
    public function boost_specificity($css, $level = 1, $namespace = 'ugentic-page')
    {
        if ($level < 1) {
            return $css;
        }

        $namespace_selector = '.' . $namespace;
        $boosted_selector = str_repeat($namespace_selector, $level + 1);

        // Replace single namespace with boosted version in selectors
        // This is a simplified approach - the Smart Resolver handles full namespacing
        $pattern = '/(\.' . preg_quote($namespace, '/') . ')(\s+)/';
        $css = preg_replace($pattern, $boosted_selector . '$2', $css);

        return $css;
    }

    /**
     * Clean up CSS - remove empty rules, extra whitespace, etc.
     */
    private function cleanup_css($css)
    {
        // Strip BOM (Byte Order Mark) — U+FEFF corrupts selectors like :root
        $css = preg_replace('/\x{FEFF}/u', '', $css);
        $css = str_replace("\xEF\xBB\xBF", '', $css);
        // Remove empty declarations
        $css = preg_replace('/[^{}]+\{\s*\}/', '', $css);

        // Remove multiple semicolons
        $css = preg_replace('/;+/', ';', $css);

        // Remove semicolons before closing braces
        $css = preg_replace('/;\s*\}/', '}', $css);

        // Normalize whitespace
        $css = preg_replace('/\s+/', ' ', $css);

        // Add newlines after closing braces for readability
        $css = preg_replace('/\}/', "}\n", $css);

        return trim($css);
    }

    /**
     * Get a report of what was normalized
     * Useful for debugging and user feedback
     * 
     * @param string $original_css Original CSS
     * @param string $normalized_css Normalized CSS
     * @return array Report of changes
     */
    public function get_normalization_report($original_css, $normalized_css)
    {
        $report = [
            'original_size' => strlen($original_css),
            'normalized_size' => strlen($normalized_css),
            'size_change' => strlen($normalized_css) - strlen($original_css),
            'changes' => [],
        ];

        // Detect what was changed
        foreach ($this->legacy_property_map as $old => $new) {
            if (preg_match('/' . preg_quote($old, '/') . '/i', $original_css)) {
                $report['changes'][] = "Converted {$old} to {$new}";
            }
        }

        if (preg_match('/filter\s*:\s*alpha/i', $original_css)) {
            $report['changes'][] = 'Converted IE filter:alpha to opacity';
        }

        foreach ($this->unit_conversions as $unit => $multiplier) {
            if (preg_match('/\d+\s*' . preg_quote($unit, '/') . '/i', $original_css)) {
                $report['changes'][] = "Converted {$unit} units to px";
            }
        }

        return $report;
    }

    /**
     * Extract CSS rules that target header/footer elements and scope them
     * to the global header/footer wrappers instead of #ugentic-page-content.
     *
     * When the importer detects header/footer HTML and moves it outside
     * #ugentic-page-content, the scoped CSS no longer matches. This method
     * creates a copy of the relevant rules re-scoped to the global wrappers.
     *
     * @param array  $raw_css_array Array of raw (unscoped) CSS strings
     * @param string $header_html  Detected header HTML
     * @param string $footer_html  Detected footer HTML
     * @return string CSS scoped to #ugentic-global-header / #ugentic-global-footer
     */
    public function extract_header_footer_css($raw_css_array, $header_html, $footer_html)
    {
        if (empty($raw_css_array) || (empty($header_html) && empty($footer_html))) {
            return '';
        }

        // 1. Parse all class names and IDs from header/footer HTML
        $header_classes = [];
        $header_ids = [];
        $footer_classes = [];
        $footer_ids = [];

        if (!empty($header_html)) {
            preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/i', $header_html, $hc);
            foreach (($hc[1] ?? []) as $class_str) {
                foreach (preg_split('/\s+/', $class_str) as $cls) {
                    $cls = trim($cls);
                    if (!empty($cls)) {
                        $header_classes[$cls] = true;
                    }
                }
            }
            preg_match_all('/id\s*=\s*["\']([^"\']+)["\']/i', $header_html, $hi);
            foreach (($hi[1] ?? []) as $id) {
                $header_ids[trim($id)] = true;
            }
            // Extract all HTML tag names used in header (dynamically, not hardcoded)
            // Filter out overly generic tags to avoid pulling in unrelated CSS rules
            $generic_tags = ['div', 'span', 'p', 'section', 'article', 'main', 'aside', 'br', 'hr', 'script', 'style', 'link', 'meta'];
            preg_match_all('/<([a-z][a-z0-9]*)\b/i', $header_html, $htags);
            foreach (($htags[1] ?? []) as $tag) {
                $tag = strtolower($tag);
                if (!in_array($tag, $generic_tags, true)) {
                    $header_classes['__tag_' . $tag] = true;
                }
            }
        }

        if (!empty($footer_html)) {
            preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/i', $footer_html, $fc);
            foreach (($fc[1] ?? []) as $class_str) {
                foreach (preg_split('/\s+/', $class_str) as $cls) {
                    $cls = trim($cls);
                    if (!empty($cls)) {
                        $footer_classes[$cls] = true;
                    }
                }
            }
            preg_match_all('/id\s*=\s*["\']([^"\']+)["\']/i', $footer_html, $fi);
            foreach (($fi[1] ?? []) as $id) {
                $footer_ids[trim($id)] = true;
            }
            // Extract all HTML tag names used in footer (dynamically)
            $generic_tags = ['div', 'span', 'p', 'section', 'article', 'main', 'aside', 'br', 'hr', 'script', 'style', 'link', 'meta'];
            preg_match_all('/<([a-z][a-z0-9]*)\b/i', $footer_html, $ftags);
            foreach (($ftags[1] ?? []) as $tag) {
                $tag = strtolower($tag);
                if (!in_array($tag, $generic_tags, true)) {
                    $footer_classes['__tag_' . $tag] = true;
                }
            }
        }

        $all_selectors = array_merge(
            array_keys($header_classes),
            array_keys($header_ids),
            array_keys($footer_classes),
            array_keys($footer_ids)
        );

        if (empty($all_selectors)) {
            return '';
        }

        // 2. Combine all raw CSS
        $combined_css = implode("\n", $raw_css_array);

        // 3. Extract matching CSS rules
        $header_css_rules = [];
        $footer_css_rules = [];
        $shared_css_rules = []; // Rules that match both (like :root, body, *, etc.)

        // Extract @keyframes, @font-face — include them as shared
        preg_match_all(
            '/@(?:-webkit-|-moz-)?keyframes\s+[^\{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/is',
            $combined_css,
            $kf_matches
        );
        foreach ($kf_matches[0] as $kf) {
            $shared_css_rules[] = $kf;
        }

        preg_match_all('/@font-face\s*\{[^}]*\}/is', $combined_css, $ff_matches);
        foreach ($ff_matches[0] as $ff) {
            $shared_css_rules[] = $ff;
        }

        // Extract @import rules
        preg_match_all('/@(?:import|charset)\s+[^;]+;/i', $combined_css, $import_matches);
        // @imports are already handled elsewhere, skip them

        // Process @media blocks
        preg_match_all(
            '/@media\s+([^{]+)\{((?:[^{}]*\{[^{}]*\})*[^{}]*)\}/is',
            $combined_css,
            $media_matches,
            PREG_SET_ORDER
        );

        foreach ($media_matches as $mm) {
            $media_query = $mm[1];
            $inner_css = $mm[2];

            $header_inner = $this->_extract_matching_rules($inner_css, $header_classes, $header_ids);
            $footer_inner = $this->_extract_matching_rules($inner_css, $footer_classes, $footer_ids);

            if (!empty($header_inner)) {
                $header_css_rules[] = '@media ' . $media_query . '{' . $header_inner . '}';
            }
            if (!empty($footer_inner)) {
                $footer_css_rules[] = '@media ' . $media_query . '{' . $footer_inner . '}';
            }
        }

        // Remove @media, @keyframes, @font-face from CSS before rule extraction
        $flat_css = preg_replace('/@media\s+[^{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/is', '', $combined_css);
        $flat_css = preg_replace('/@(?:-webkit-|-moz-)?keyframes\s+[^\{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/is', '', $flat_css);
        $flat_css = preg_replace('/@font-face\s*\{[^}]*\}/is', '', $flat_css);

        // Extract top-level rules
        $header_top = $this->_extract_matching_rules($flat_css, $header_classes, $header_ids);
        $footer_top = $this->_extract_matching_rules($flat_css, $footer_classes, $footer_ids);

        if (!empty($header_top)) {
            $header_css_rules[] = $header_top;
        }
        if (!empty($footer_top)) {
            $footer_css_rules[] = $footer_top;
        }

        // Also extract :root / body / html / * rules — these provide CSS variables and resets
        $root_body_rules = $this->_extract_root_body_rules($flat_css);
        if (!empty($root_body_rules)) {
            $shared_css_rules[] = $root_body_rules;
        }

        // 4. Build output CSS scoped to global header/footer wrappers
        $output = '';

        if (!empty($shared_css_rules)) {
            $output .= "/* ── Shared rules (keyframes, fonts, variables) ── */\n";
            $output .= implode("\n", $shared_css_rules) . "\n\n";
        }

        if (!empty($header_css_rules)) {
            $header_css = implode("\n", $header_css_rules);
            $header_css = $this->scope_to_container($header_css, '#ugentic-global-header');
            $output .= "/* ── Header styles ── */\n";
            $output .= $header_css . "\n\n";
        }

        if (!empty($footer_css_rules)) {
            $footer_css = implode("\n", $footer_css_rules);
            $footer_css = $this->scope_to_container($footer_css, '#ugentic-global-footer');
            $output .= "/* ── Footer styles ── */\n";
            $output .= $footer_css . "\n\n";
        }

        return $output;
    }

    /**
     * Extract CSS rules whose selectors reference any of the given classes or IDs.
     *
     * @param string $css    CSS rules (flat, no @-rule wrappers)
     * @param array  $classes Associative array of class names
     * @param array  $ids     Associative array of IDs
     * @return string Matching CSS rules
     */
    private function _extract_matching_rules($css, $classes, $ids)
    {
        $matched = [];

        preg_match_all('/([^{}\/]+?)\{([^{}]*)\}/s', $css, $rule_matches, PREG_SET_ORDER);

        foreach ($rule_matches as $rule) {
            $selector = trim($rule[1]);
            $declarations = $rule[2];

            // Skip placeholders and @-rules
            if (strpos($selector, '__UGENTIC_') !== false || strpos($selector, '@') === 0) {
                continue;
            }

            if ($this->_selector_matches_elements($selector, $classes, $ids)) {
                $matched[] = $selector . '{' . $declarations . '}';
            }
        }

        return implode("\n", $matched);
    }

    /**
     * Check if a CSS selector references any of the given classes, IDs, or tag names.
     *
     * @param string $selector CSS selector string
     * @param array  $classes  Associative array of class names
     * @param array  $ids      Associative array of IDs
     * @return bool
     */
    private function _selector_matches_elements($selector, $classes, $ids)
    {
        // Check class selectors
        foreach ($classes as $cls => $v) {
            if (strpos($cls, '__tag_') === 0) {
                // Tag name match
                $tag = substr($cls, 6);
                if (preg_match('/(?:^|[\s>+~,])' . preg_quote($tag, '/') . '(?:[\s>+~,.:{[\]]|$)/i', $selector)) {
                    return true;
                }
            }
            else {
                // Class name match — look for .classname in selector
                if (strpos($selector, '.' . $cls) !== false) {
                    return true;
                }
            }
        }

        // Check ID selectors
        foreach ($ids as $id => $v) {
            if (strpos($selector, '#' . $id) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract :root, html, body, and * rules from CSS — these provide
     * CSS custom properties and base styles needed by header/footer.
     *
     * @param string $css Flat CSS content
     * @return string Extracted rules
     */
    private function _extract_root_body_rules($css)
    {
        $matched = [];

        preg_match_all('/([^{}\/]+?)\{([^{}]*)\}/s', $css, $rule_matches, PREG_SET_ORDER);

        foreach ($rule_matches as $rule) {
            $selector = trim($rule[1]);
            $declarations = $rule[2];

            $selectors = array_map('trim', explode(',', $selector));
            foreach ($selectors as $sel) {
                $lower = strtolower($sel);
                if (preg_match('/^(:root|html|body|\*)(\s|$|,|{|\[|:)/', $lower) ||
                $lower === ':root' || $lower === 'html' || $lower === 'body' || $lower === '*') {
                    $matched[] = $selector . '{' . $declarations . '}';
                    break;
                }
            }
        }

        return implode("\n", $matched);
    }
}
