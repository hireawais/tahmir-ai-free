<?php
/**
 * Legacy Detector - Detect website era and characteristics
 * Analyzes imported HTML/CSS to determine appropriate normalization settings
 * 
 * @package Ugentic_Builder
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Legacy_Detector {
    
    /**
     * Era constants
     */
    const ERA_ANCIENT = 'ancient';      // Pre-2005: Table layouts, no DOCTYPE
    const ERA_TRANSITIONAL = 'transitional'; // 2005-2010: XHTML, early CSS
    const ERA_MODERN_EARLY = 'modern_early';  // 2010-2015: HTML5 adoption
    const ERA_MODERN = 'modern';        // 2015+: Modern standards
    
    /**
     * Deprecated HTML elements from legacy sites
     */
    private $deprecated_elements = [
        'font', 'center', 'marquee', 'blink', 'bgsound',
        'applet', 'basefont', 'big', 'strike', 's', 'u',
        'frame', 'frameset', 'noframes', 'isindex', 'dir',
        'menu', 'acronym', 'tt', 'xmp', 'listing', 'plaintext'
    ];
    
    /**
     * Legacy CSS property patterns
     */
    private $legacy_css_patterns = [
        'filter\s*:\s*alpha\s*\(' => 'pre-2010',
        '-moz-opacity' => 'pre-2010',
        '-khtml-' => 'pre-2008',
        'behavior\s*:' => 'pre-2012',
        'scrollbar-.*-color' => 'IE-specific',
        'zoom\s*:\s*1' => 'hasLayout-hack',
        '\*\s*html' => 'IE6-hack',
        '_[a-z-]+\s*:' => 'IE6-underscore-hack',
        'expression\s*\(' => 'IE-expression',
    ];
    
    /**
     * Check if HTML is in quirks mode (missing or malformed DOCTYPE)
     * 
     * @param string $html Full HTML content
     * @return bool True if quirks mode detected
     */
    public function is_quirks_mode($html) {
        // No DOCTYPE at all = quirks mode
        if (!preg_match('/<!DOCTYPE\s+/i', $html)) {
            return true;
        }
        
        // HTML5 DOCTYPE is standards mode
        if (preg_match('/<!DOCTYPE\s+html\s*>/i', $html)) {
            return false;
        }
        
        // XHTML strict or HTML4 strict = standards mode
        if (preg_match('/<!DOCTYPE.*strict/i', $html)) {
            return false;
        }
        
        // Transitional DOCTYPE with system identifier = almost standards
        if (preg_match('/<!DOCTYPE.*transitional/i', $html)) {
            // Check for system identifier (URL)
            if (preg_match('/<!DOCTYPE[^>]+http:\/\//i', $html)) {
                return false; // Almost standards mode
            }
            return true; // Quirks mode without URL
        }
        
        // Loose/frameset DOCTYPE = quirks mode
        if (preg_match('/<!DOCTYPE.*(loose|frameset)/i', $html)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Detect the approximate era of the website
     * 
     * @param string $html HTML content
     * @param string $css Combined CSS content
     * @return string Era constant
     */
    public function detect_era($html, $css = '') {
        $signals = [
            'ancient' => 0,
            'transitional' => 0,
            'modern_early' => 0,
            'modern' => 0,
        ];
        
        // DOCTYPE analysis
        if (!preg_match('/<!DOCTYPE/i', $html)) {
            $signals['ancient'] += 5;
        } elseif (preg_match('/<!DOCTYPE\s+html\s*>/i', $html)) {
            $signals['modern'] += 3;
        } elseif (preg_match('/XHTML/i', $html)) {
            $signals['transitional'] += 3;
        }
        
        // Deprecated elements
        foreach ($this->deprecated_elements as $element) {
            if (preg_match('/<' . $element . '[\s>]/i', $html)) {
                $signals['ancient'] += 2;
            }
        }
        
        // Table-based layout detection
        if ($this->is_table_layout($html)) {
            $signals['ancient'] += 4;
        }
        
        // Meta charset encoding
        if (preg_match('/<meta[^>]+charset\s*=/i', $html)) {
            $signals['modern'] += 1;
        } elseif (preg_match('/<meta[^>]+http-equiv\s*=\s*["\']?content-type/i', $html)) {
            $signals['transitional'] += 2;
        }
        
        // HTML5 elements
        $html5_elements = ['header', 'footer', 'nav', 'main', 'article', 'section', 'aside', 'figure'];
        foreach ($html5_elements as $element) {
            if (preg_match('/<' . $element . '[\s>]/i', $html)) {
                $signals['modern'] += 1;
            }
        }
        
        // CSS patterns
        if (!empty($css)) {
            foreach ($this->legacy_css_patterns as $pattern => $era) {
                if (preg_match('/' . $pattern . '/i', $css)) {
                    if (strpos($era, 'pre-2010') !== false || strpos($era, 'pre-2008') !== false) {
                        $signals['ancient'] += 2;
                    } elseif (strpos($era, 'pre-2012') !== false) {
                        $signals['transitional'] += 2;
                    } else {
                        $signals['ancient'] += 1;
                    }
                }
            }
            
            // Modern CSS features
            if (preg_match('/display\s*:\s*(flex|grid)/i', $css)) {
                $signals['modern'] += 3;
            }
            if (preg_match('/--[a-z-]+\s*:/i', $css)) { // CSS custom properties
                $signals['modern'] += 2;
            }
            if (preg_match('/@media/i', $css)) {
                $signals['modern_early'] += 2;
            }
        }
        
        // JavaScript patterns in HTML
        if (preg_match('/document\.all[^\w]/i', $html)) {
            $signals['ancient'] += 3;
        }
        if (preg_match('/attachEvent\s*\(/i', $html)) {
            $signals['ancient'] += 2;
        }
        if (preg_match('/addEventListener\s*\(/i', $html)) {
            $signals['modern_early'] += 1;
        }
        
        // Find highest scoring era
        $max = 0;
        $detected_era = self::ERA_MODERN;
        foreach ($signals as $era => $score) {
            if ($score > $max) {
                $max = $score;
                $detected_era = $era;
            }
        }
        
        return $detected_era;
    }
    
    /**
     * Detect if page uses table-based layout
     * 
     * @param string $html HTML content
     * @return bool True if table-based layout detected
     */
    public function is_table_layout($html) {
        // Count tables
        preg_match_all('/<table[\s>]/i', $html, $tables);
        $table_count = count($tables[0]);
        
        if ($table_count === 0) {
            return false;
        }
        
        // Check for layout-indicative patterns
        $layout_signals = 0;
        
        // Nested tables
        if (preg_match('/<table[^>]*>.*<table/is', $html)) {
            $layout_signals += 2;
        }
        
        // Tables with width="100%" or width in pixels > 500
        if (preg_match('/<table[^>]+width\s*=\s*["\']?(100%|\d{3,})/i', $html)) {
            $layout_signals += 2;
        }
        
        // Spacer GIFs
        if (preg_match('/spacer\.(gif|png)|1x1\.(gif|png)|clear\.(gif|png)/i', $html)) {
            $layout_signals += 3;
        }
        
        // Tables used for page structure (high table to content ratio)
        preg_match_all('/<(div|section|article|main|aside)[\s>]/i', $html, $semantic);
        $semantic_count = count($semantic[0]);
        
        if ($table_count > 3 && $semantic_count < 5) {
            $layout_signals += 2;
        }
        
        // Cellpadding/cellspacing attributes
        if (preg_match('/<table[^>]+(cellpadding|cellspacing)\s*=/i', $html)) {
            $layout_signals++;
        }
        
        return $layout_signals >= 3;
    }
    
    /**
     * Check if HTML uses frames
     * 
     * @param string $html HTML content
     * @return bool True if frameset detected
     */
    public function uses_frames($html) {
        return preg_match('/<frameset[\s>]/i', $html) || 
               preg_match('/<frame[\s>]/i', $html);
    }
    
    /**
     * Get recommended normalization options based on detected characteristics
     * 
     * @param string $html HTML content
     * @param string $css CSS content
     * @return array Configuration options for normalization
     */
    public function get_normalization_config($html, $css = '') {
        $era = $this->detect_era($html, $css);
        $is_quirks = $this->is_quirks_mode($html);
        $is_table = $this->is_table_layout($html);
        
        // For modern sites, use minimal normalization to preserve original CSS
        if ($era === self::ERA_MODERN || $era === self::ERA_MODERN_EARLY) {
            return [
                'era' => $era,
                'quirks_mode' => $is_quirks,
                'table_layout' => $is_table,
                
                // Minimal normalization for modern sites
                'convert_legacy_properties' => false,  // Modern sites don't need this
                'normalize_units' => false,            // Preserve original units
                'normalize_vendor_prefixes' => false,  // Already has proper prefixes
                'modernize_fonts' => false,            // Don't touch font stacks
                'remove_ie_hacks' => false,            // Unlikely to have IE hacks
                'specificity_boost' => 0,              // Don't boost specificity
                
                // Era-specific settings
                'preserve_table_widths' => false,
                'convert_font_tags' => false,
                'convert_center_tags' => false,
                'inject_box_sizing_reset' => false,
                'inject_normalize_css' => false,
            ];
        }
        
        // For ancient/transitional sites, use full normalization
        $config = [
            'era' => $era,
            'quirks_mode' => $is_quirks,
            'table_layout' => $is_table,
            
            // Full normalization settings for legacy sites
            'convert_legacy_properties' => true,
            'normalize_units' => true,
            'normalize_vendor_prefixes' => true,
            'modernize_fonts' => true,
            'remove_ie_hacks' => true,
            'specificity_boost' => 1, // Double namespace selector
            
            // Era-specific settings
            'preserve_table_widths' => $is_table,
            'convert_font_tags' => ($era === self::ERA_ANCIENT),
            'convert_center_tags' => ($era === self::ERA_ANCIENT),
            'inject_box_sizing_reset' => true,
            'inject_normalize_css' => $is_quirks,
        ];
        
        // For very old sites, increase specificity boost
        if ($era === self::ERA_ANCIENT) {
            $config['specificity_boost'] = 2;
        }
        
        return $config;
    }
    
    /**
     * Get a human-readable report of detected characteristics
     * 
     * @param string $html HTML content
     * @param string $css CSS content
     * @return array Report data
     */
    public function get_detection_report($html, $css = '') {
        $era = $this->detect_era($html, $css);
        $config = $this->get_normalization_config($html, $css);
        
        $era_labels = [
            self::ERA_ANCIENT => 'Pre-2005 (Table layouts, no DOCTYPE)',
            self::ERA_TRANSITIONAL => '2005-2010 (XHTML, early CSS)',
            self::ERA_MODERN_EARLY => '2010-2015 (HTML5 adoption)',
            self::ERA_MODERN => '2015+ (Modern standards)',
        ];
        
        return [
            'era' => $era,
            'era_label' => $era_labels[$era] ?? 'Unknown',
            'quirks_mode' => $config['quirks_mode'],
            'table_layout' => $config['table_layout'],
            'uses_frames' => $this->uses_frames($html),
            'deprecated_elements_found' => $this->count_deprecated_elements($html),
            'normalization_config' => $config,
        ];
    }
    
    /**
     * Count deprecated HTML elements
     * 
     * @param string $html HTML content
     * @return int Count of deprecated elements
     */
    private function count_deprecated_elements($html) {
        $count = 0;
        foreach ($this->deprecated_elements as $element) {
            preg_match_all('/<' . $element . '[\s>]/i', $html, $matches);
            $count += count($matches[0]);
        }
        return $count;
    }
    
    /**
     * Detect modern frameworks, CMS origins, page builders, and animation libraries
     * Returns framework-specific import strategy recommendations
     * 
     * @param string $html HTML content
     * @param string $css Combined CSS content
     * @return array Detected frameworks with categories and strategies
     */
    public function detect_framework($html, $css = '') {
        $detected = [];
        
        // ─── CSS Frameworks ───
        $css_frameworks = [
            'Bootstrap 5' => [
                'html' => ['class="container', 'class="row', 'col-md-', 'col-lg-', 'col-sm-', 'col-xl-', 'navbar-expand-', 'btn-primary'],
                'css' => ['--bs-', '.container-fluid', '.row>*', '@media (min-width: 576px)'],
                'strategy' => 'preserve_grid',
            ],
            'Bootstrap 4' => [
                'html' => ['col-md-', 'col-lg-', 'navbar-expand-', 'card-body', 'card-deck'],
                'css' => ['.custom-control', '.card-deck', '.carousel-inner'],
                'strategy' => 'preserve_grid',
            ],
            'Bootstrap 3' => [
                'html' => ['col-xs-', 'col-sm-', 'panel-default', 'panel-heading', 'glyphicon'],
                'css' => ['.panel-default', '.col-xs-', 'glyphicon'],
                'strategy' => 'preserve_grid',
            ],
            'Tailwind CSS' => [
                'html' => ['class="flex', 'class="grid', 'bg-gradient', 'text-gray-', 'rounded-lg', 'px-', 'py-', 'mx-auto', 'space-x-', 'space-y-'],
                'css' => ['--tw-', '@tailwind', '.prose', '.-translate-'],
                'strategy' => 'preserve_utilities',
            ],
            'Foundation' => [
                'html' => ['small-12', 'medium-6', 'large-4', 'cell', 'grid-x', 'grid-y'],
                'css' => ['.grid-x', '.grid-y', '.cell'],
                'strategy' => 'preserve_grid',
            ],
            'Bulma' => [
                'html' => ['is-primary', 'is-info', 'is-success', 'columns', 'column is-'],
                'css' => ['.columns', '.column', '.is-size-', '.has-text-'],
                'strategy' => 'preserve_grid',
            ],
            'Material UI / MUI' => [
                'html' => ['MuiButton', 'MuiPaper', 'MuiGrid', 'MuiTypography', 'MuiAppBar'],
                'css' => ['.MuiButton-root', '.MuiPaper-root'],
                'strategy' => 'preserve_components',
            ],
        ];
        
        // ─── CMS Origins ───
        $cms_origins = [
            'WordPress' => [
                'html' => ['wp-content/', 'wp-includes/', 'wp-json', 'wordpress', 'wp-block-'],
                'css' => ['.wp-block-', '#wpadminbar', '.entry-content'],
                'strategy' => 'standard',
            ],
            'Webflow' => [
                'html' => ['w-nav', 'w-slider', 'w-tab', 'w-form', 'w-embed', 'data-wf-site'],
                'css' => ['.w-nav', '.w-slider', '.w-tab', '.w-form'],
                'strategy' => 'preserve_webflow_classes',
            ],
            'Squarespace' => [
                'html' => ['squarespace', 'sqs-block', 'sqs-layout', 'sqs-row'],
                'css' => ['.sqs-block', '.sqs-layout'],
                'strategy' => 'standard',
            ],
            'Wix' => [
                'html' => ['wix-image', 'wix-iframe', '_2Yzaw', 'comp-', 'wixui.'],
                'css' => [],
                'strategy' => 'complex',
            ],
            'Shopify' => [
                'html' => ['shopify-section', 'shopify-features', 'cdn.shopify.com'],
                'css' => ['.shopify-section'],
                'strategy' => 'standard',
            ],
        ];
        
        // ─── Page Builders ───
        $page_builders = [
            'Elementor' => [
                'html' => ['elementor-element', 'elementor-widget', 'elementor-section', 'elementor-column'],
                'css' => ['.elementor-element', '.elementor-section', '.elementor-widget-wrap'],
                'strategy' => 'preserve_components',
            ],
            'Divi' => [
                'html' => ['et_pb_section', 'et_pb_row', 'et_pb_column', 'et_pb_module'],
                'css' => ['.et_pb_section', '.et_pb_row'],
                'strategy' => 'preserve_components',
            ],
            'Beaver Builder' => [
                'html' => ['fl-builder', 'fl-row', 'fl-col', 'fl-module'],
                'css' => ['.fl-builder', '.fl-row', '.fl-col'],
                'strategy' => 'preserve_components',
            ],
            'WPBakery' => [
                'html' => ['vc_row', 'vc_column', 'vc_custom_', 'wpb_wrapper'],
                'css' => ['.vc_row', '.vc_column', '.wpb_wrapper'],
                'strategy' => 'preserve_grid',
            ],
            'Oxygen' => [
                'html' => ['ct-section', 'ct-inner-content', 'ct-div-block', 'oxy-'],
                'css' => ['.ct-section', '.oxy-dynamic-list'],
                'strategy' => 'preserve_components',
            ],
        ];
        
        // ─── JS Frameworks ───
        $js_frameworks = [
            'React' => [
                'html' => ['data-reactroot', 'data-reactid', '_react', '__next', 'react-root'],
                'css' => [],
                'strategy' => 'complex',
            ],
            'Vue' => [
                'html' => ['data-v-', 'v-cloak', 'v-bind', 'v-model', '__vue'],
                'css' => ['[data-v-'],
                'strategy' => 'complex',
            ],
            'Angular' => [
                'html' => ['ng-app', 'ng-controller', 'ng-model', '_ngcontent', 'ng-version'],
                'css' => ['[_ngcontent'],
                'strategy' => 'complex',
            ],
            'Alpine.js' => [
                'html' => ['x-data', 'x-bind', 'x-on', 'x-show', 'x-model', '@click'],
                'css' => [],
                'strategy' => 'standard',
            ],
            'jQuery UI' => [
                'html' => ['ui-widget', 'ui-dialog', 'ui-tabs', 'ui-accordion'],
                'css' => ['.ui-widget', '.ui-dialog'],
                'strategy' => 'standard',
            ],
        ];
        
        // ─── Animation Libraries ───
        $animation_libs = [
            'AOS' => [
                'html' => ['data-aos=', 'data-aos-duration', 'data-aos-delay'],
                'css' => ['[data-aos]', '.aos-animate'],
                'strategy' => 'preserve_animations',
            ],
            'GSAP' => [
                'html' => ['gsap', 'ScrollTrigger', 'TweenMax', 'TimelineMax'],
                'css' => [],
                'strategy' => 'preserve_animations',
            ],
            'Animate.css' => [
                'html' => ['animate__animated', 'animate__', 'animated fadeIn', 'animated bounce'],
                'css' => ['.animate__animated', '@keyframes fadeIn'],
                'strategy' => 'preserve_animations',
            ],
            'Framer Motion' => [
                'html' => ['data-framer', 'framer-motion'],
                'css' => [],
                'strategy' => 'complex',
            ],
            'Lottie' => [
                'html' => ['lottie-player', 'lottie-web', 'bodymovin'],
                'css' => [],
                'strategy' => 'standard',
            ],
        ];
        
        // Run detection across all categories
        $all_checks = [
            'css_framework' => $css_frameworks,
            'cms' => $cms_origins,
            'page_builder' => $page_builders,
            'js_framework' => $js_frameworks,
            'animation' => $animation_libs,
        ];
        
        foreach ($all_checks as $category => $frameworks) {
            foreach ($frameworks as $name => $config) {
                $matches = 0;
                $total_signals = count($config['html']) + count($config['css']);
                
                foreach ($config['html'] as $signal) {
                    if (stripos($html, $signal) !== false) $matches++;
                }
                foreach ($config['css'] as $signal) {
                    if (stripos($css, $signal) !== false) $matches++;
                }
                
                // Need at least 2 matches or 50% of signals to confirm
                if ($matches >= 2 || ($total_signals > 0 && ($matches / $total_signals) >= 0.5)) {
                    $detected[] = [
                        'name' => $name,
                        'category' => $category,
                        'confidence' => min(100, round(($matches / max($total_signals, 1)) * 100)),
                        'strategy' => $config['strategy'],
                    ];
                }
            }
        }
        
        return $detected;
    }
}
