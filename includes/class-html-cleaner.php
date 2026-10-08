<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Shared HTML cleanup helpers.
 *
 * @package Tahmir AI
 */

if (!defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}

class Ugentic_HTML_Cleaner
{
    /**
     * Ensure meaningful text-like HTML elements can be selected and edited.
     *
     * Some generated/imported pages can reach storage without editable markers
     * when they bypass the normal importer. This repair is intentionally narrow:
     * it skips executable/protected blocks and decorative or hidden text.
     */
    public static function ensure_editable_markers($html)
    {
        if (!is_string($html) || $html === '' || strpos($html, '<') === false) {
            return $html;
        }

        $protected_blocks = [];
        $html = preg_replace_callback(
            '/<script\b[^>]*>[\s\S]*?<\/script>|<style\b[^>]*>[\s\S]*?<\/style>|<noscript\b[^>]*>[\s\S]*?<\/noscript>|<template\b[^>]*>[\s\S]*?<\/template>|<svg\b[^>]*>[\s\S]*?<\/svg>|<math\b[^>]*>[\s\S]*?<\/math>|<!--[\s\S]*?-->/i',
            function ($match) use (&$protected_blocks) {
                $key = '%%UGENTIC_PROTECTED_EDITABLE_BLOCK_' . count($protected_blocks) . '%%';
                $protected_blocks[$key] = $match[0];
                return $key;
            },
            $html
        );

        $editable_tags = 'h[1-6]|p|a|li|td|th|button|span|label|figcaption|blockquote|dt|dd|small|strong|em|b|u|mark|summary|legend|caption|time|abbr|cite|q|pre|code';
        $html = preg_replace_callback('/<(' . $editable_tags . ')\b([^>]*?)(\/?)>/i', function ($matches) {
            $attrs = $matches[2] ?? '';

            if (($matches[3] ?? '') === '/' || self::should_skip_editable_marker($attrs)) {
                return $matches[0];
            }

            return '<' . $matches[1] . $attrs . ' data-ugentic-editable="true">';
        }, $html);

        if (!empty($protected_blocks)) {
            $html = str_replace(array_keys($protected_blocks), array_values($protected_blocks), $html);
        }

        return $html;
    }

    /**
     * Remove Tahmir editor-only attributes and transient selection classes.
     *
     * Stored widget data such as data-widget-type is intentionally preserved;
     * only edit-session markers are removed from the public render.
     */
    public static function strip_editor_markers($html)
    {
        if (!is_string($html) || $html === '' || strpos($html, '<') === false) {
            return $html;
        }

        // WordPress emits these exact style handles while rendering wp_head().
        // They are regenerated on every request and are not faithful page
        // source. Removing only the exact handles also repairs older saves
        // made before render boundaries were available.
        $html = preg_replace(
            '/\s*<style\b(?=[^>]*\bid=(["\'])(?:global-styles|classic-theme-styles|core-block-supports)-inline-css\1)[^>]*>[\s\S]*?<\/style>/i',
            '',
            $html
        );

        // Raw-text elements may contain tag-looking strings. Protect them so
        // cleanup never rewrites JavaScript, CSS, templates, or textarea data.
        $protected_blocks = [];
        $html = preg_replace_callback(
            '/<script\b[^>]*>[\s\S]*?<\/script>|<style\b[^>]*>[\s\S]*?<\/style>|<textarea\b[^>]*>[\s\S]*?<\/textarea>|<title\b[^>]*>[\s\S]*?<\/title>|<template\b[^>]*>[\s\S]*?<\/template>|<noscript\b[^>]*>[\s\S]*?<\/noscript>|<!--[\s\S]*?-->/i',
            function ($match) use (&$protected_blocks) {
                $key = '%%UGENTIC_NEUTRAL_RAW_' . count($protected_blocks) . '%%';
                $protected_blocks[$key] = $match[0];
                return $key;
            },
            $html
        );

        $html = preg_replace_callback('/<([a-z][\w:-]*)\b[^>]*>/i', function ($match) {
            $tag = $match[0];
            $tag_name = strtolower((string) ($match[1] ?? ''));
            $was_canvas_root = (bool) preg_match('/\bclass=(["\'])[^"\']*\bug-v2-canvas-root\b[^"\']*\1/i', $tag);
            $is_document_root = in_array($tag_name, ['html', 'body'], true);

            $tag = preg_replace(
                '/\s+(?:data-ugentic-(?:editable|edit-root|saveable|no-save|selected|hover|element-id|selection-id|widget|link-id|original-(?:animation|transition|html|transform|text)|(?:full-text|all-words|target-number)|animation-paused|editor-[\w-]+)|data-ug-v2-[\w-]+|data-ug-layer-[\w-]+|contenteditable)=(["\'])(.*?)\1/is',
                '',
                $tag
            );
            $tag = preg_replace_callback('/\sclass=(["\'])(.*?)\1/is', function ($class_match) {
                $classes = preg_split('/\s+/', trim($class_match[2]));
                $classes = array_values(array_filter($classes, function ($class) {
                    return $class !== '' && !preg_match(
                        '/^(?:customize-support|admin-bar|ug-v2-[\w-]+|ugentic-(?:selected(?:-row|-col)?|element-selected|element-hover|hover|section-hover|editing|edit-active|dragging|drag-over|drop-target|preview-mode|public-edit-mode|sf-mode|sf-element-selected|sf-element-hover|save-lock|traditional-(?:panel|settings)-open))$/i',
                        $class
                    );
                }));
                return empty($classes) ? '' : ' class=' . $class_match[1] . implode(' ', $classes) . $class_match[1];
            }, $tag);

            $tag = preg_replace_callback('/\sstyle=(["\'])(.*?)\1/is', function ($style_match) use ($was_canvas_root, $is_document_root) {
                $style = html_entity_decode($style_match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $needs_cleanup = preg_match('/(?:^|;)\s*--ug-(?:v2|editbar|left-panel|wp-adminbar)[\w-]*\s*:/i', $style)
                    || ($is_document_root && preg_match('/(?:^|;)\s*--scrollbar-offset\s*:/i', $style))
                    || ($was_canvas_root && preg_match('/(?:^|;)\s*(?:zoom|transform-origin)\s*:/i', $style));
                if (!$needs_cleanup) {
                    return $style_match[0];
                }

                $parts = self::split_inline_style_declarations($style);
                $kept = [];
                foreach ($parts as $part) {
                    $declaration = trim($part);
                    if ($declaration === '') {
                        continue;
                    }
                    if (preg_match('/^--ug-(?:v2|editbar|left-panel|wp-adminbar)[\w-]*\s*:/i', $declaration)) {
                        continue;
                    }
                    if ($is_document_root && preg_match('/^--scrollbar-offset\s*:/i', $declaration)) {
                        continue;
                    }
                    if ($was_canvas_root && preg_match('/^(?:zoom|transform-origin)\s*:/i', $declaration)) {
                        continue;
                    }
                    $kept[] = $declaration;
                }

                if (empty($kept)) {
                    return '';
                }
                $encoded = htmlspecialchars(implode('; ', $kept) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return ' style=' . $style_match[1] . $encoded . $style_match[1];
            }, $tag);

            return $tag;
        }, $html);

        $html = preg_replace('/\s(class|style)=(["\'])\s*\2/i', '', $html);
        if (!empty($protected_blocks)) {
            $html = str_replace(array_keys($protected_blocks), array_values($protected_blocks), $html);
        }

        return $html;
    }

    /**
     * Split inline CSS declarations without breaking semicolons inside strings
     * or function parentheses.
     *
     * @return string[]
     */
    private static function split_inline_style_declarations($style)
    {
        $parts = [];
        $buffer = '';
        $quote = '';
        $depth = 0;
        $escaped = false;
        $length = strlen((string) $style);

        for ($index = 0; $index < $length; $index++) {
            $char = $style[$index];
            if ($escaped) {
                $buffer .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $buffer .= $char;
                $escaped = true;
                continue;
            }
            if ($quote !== '') {
                $buffer .= $char;
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }
            if ($char === ';' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        if ($buffer !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * Neutralize imported loaders that would otherwise block the builder canvas.
     *
     * Imported faithful pages do not execute arbitrary source scripts in builder
     * mode, so script-dismissed preloaders can remain fixed over the whole page.
     * This operates on the render copy only; marked nodes are stripped by the
     * editor's no-save cleanup if the page is later saved.
     */
    public static function neutralize_imported_loading_state($html)
    {
        if (!is_string($html) || $html === '' || strpos($html, '<') === false) {
            return $html;
        }

        if (class_exists('WP_HTML_Tag_Processor')) {
            $processor = new WP_HTML_Tag_Processor($html);
            while ($processor->next_tag()) {
                $tag_name = strtolower((string) $processor->get_tag());
                if (in_array($tag_name, ['script', 'style', 'template', 'textarea', 'noscript'], true)) {
                    continue;
                }

                if (in_array($tag_name, ['html', 'body'], true)) {
                    self::processor_remove_loading_root_classes($processor);
                    continue;
                }

                $classes = preg_split('/\s+/', trim((string) $processor->get_attribute('class')));
                $classes = array_values(array_filter($classes));
                if (self::is_imported_loader_signature((string) $processor->get_attribute('id'), $classes)) {
                    $processor->set_attribute('data-ugentic-no-save', 'imported-loader');
                    $processor->set_attribute('aria-hidden', 'true');
                    $processor->set_attribute(
                        'style',
                        self::merge_hidden_loader_style((string) $processor->get_attribute('style'))
                    );
                }
            }

            return $processor->get_updated_html();
        }

        return self::neutralize_imported_loading_state_fallback($html);
    }

    /**
     * Render-only guard for loader patterns that can only be identified at runtime.
     */
    public static function imported_loading_guard_markup()
    {
        return '<style id="ugentic-imported-loader-guard" data-ugentic-no-save="true">'
            . 'html,body{overflow:auto!important;}'
            . '#spinner:not([data-ugentic-no-save="false"]),#preloader:not([data-ugentic-no-save="false"]),#page-loader:not([data-ugentic-no-save="false"]),#page-loading:not([data-ugentic-no-save="false"]),#loader-wrapper:not([data-ugentic-no-save="false"]),#site-loader:not([data-ugentic-no-save="false"]),#loading-screen:not([data-ugentic-no-save="false"]),'
            . '.preloader:not([data-ugentic-no-save="false"]),.page-loader:not([data-ugentic-no-save="false"]),.page-loading:not([data-ugentic-no-save="false"]),.loader-wrapper:not([data-ugentic-no-save="false"]),.site-loader:not([data-ugentic-no-save="false"]),.site-preloader:not([data-ugentic-no-save="false"]),.loading-screen:not([data-ugentic-no-save="false"]),.loading-overlay:not([data-ugentic-no-save="false"]),.loader-overlay:not([data-ugentic-no-save="false"]),.page-wipe:not([data-ugentic-no-save="false"])'
            . '{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;animation:none!important;transition:none!important;}'
            . '</style>'
            . '<script id="ugentic-imported-loader-guard-js" data-ugentic-no-save="true">(function(){'
            . 'var root=document.documentElement,body=document.body;if(!body)return;'
            . '["is-loading","loading","preloading","is-preloading","page-loading","site-loading","pace-running","page-leaving"].forEach(function(c){root.classList.remove(c);body.classList.remove(c);});'
            . 'var sel="#spinner,#preloader,#page-loader,#page-loading,#loader-wrapper,#site-loader,#loading-screen,.preloader,.page-loader,.page-loading,.loader-wrapper,.site-loader,.site-preloader,.loading-screen,.loading-overlay,.loader-overlay,.page-wipe,.page-transition,.transition-overlay,.pace";'
            . 'function looksLikeOverlay(el){if(!el||el.closest("[data-ugentic-no-save=false],#wpadminbar,#ugentic-edit-bar,#ugentic-builder,.ug-v2-chrome,.ugentic-sidebar"))return false;var cs=getComputedStyle(el),r=el.getBoundingClientRect(),name=((el.id||"")+" "+(el.className||"")).toLowerCase();var strong=/(preloader|page-loader|site-loader|loader-wrapper|loading-screen|loading-overlay|loader-overlay|page-wipe|page-transition|transition-overlay|pace)/.test(name);var large=(r.width>=innerWidth*.35&&r.height>=innerHeight*.2)||(r.width>=innerWidth*.75&&r.height>=40);return strong&&large&&/^(fixed|absolute|sticky)$/.test(cs.position);}'
            . 'function clean(){document.querySelectorAll(sel).forEach(function(el){if(looksLikeOverlay(el))el.remove();});}'
            . 'clean();if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",clean,{once:true});}setTimeout(clean,600);'
            . '})();</script>';
    }

    /**
     * Keep script-gated imported content visible and editable in normal builder mode.
     *
     * Imported active content is intentionally suppressed in the default editor.
     * This render-only guard materializes common reveal states through CSS and
     * hydrates lazy media without running arbitrary page JavaScript.
     */
    public static function imported_builder_content_guard_markup()
    {
        $reveal_selector = ':where('
            . '[data-reveal],[data-scroll-reveal],[data-aos],[data-animate],[data-animation],'
            . '[data-appear],[data-framer-appear-id],[data-inview],[data-in-view],[data-ug-animate],'
            . '.aos-init,.wow,.reveal,.scroll-reveal,.animate-on-scroll,.ug-reveal,'
            . '[class*="ug-reveal"],.ugv2-reveal,[class*="reveal-"],[class*="-reveal"]'
            . ')';
        $builder_reveal_selector = 'html body.ug-v2-builder.ugentic-edit-active ' . $reveal_selector;

        return '<style id="ugentic-imported-content-guard" data-ugentic-no-save="true">'
            . $builder_reveal_selector
            . '{opacity:1!important;visibility:visible!important;transform:none!important;'
            . 'translate:none!important;scale:none!important;filter:none!important;clip-path:none!important;'
            . 'animation-delay:0s!important;animation-play-state:paused!important;transition-delay:0s!important;}'
            . $builder_reveal_selector . '[data-framer-layout-hint-center-x]:not([data-framer-layout-hint-center-y])'
            . '{transform:translateX(-50%)!important;}'
            . $builder_reveal_selector . '[data-framer-layout-hint-center-y]:not([data-framer-layout-hint-center-x])'
            . '{transform:translateY(-50%)!important;}'
            . $builder_reveal_selector . '[data-framer-layout-hint-center-x][data-framer-layout-hint-center-y]'
            . '{transform:translate(-50%,-50%)!important;}'
            . $builder_reveal_selector . '[hidden]{display:revert!important;}'
            // All imported steps must remain selectable in edit mode. This is
            // render-only CSS; public navigation and saved visibility stay intact.
            . 'body.ugentic-edit-active:not(.ugentic-preview-mode) form :is([data-ugentic-step],.ugentic-form__step,.form-step,.wizard-step)'
            . '{display:block!important;visibility:visible!important;opacity:1!important;pointer-events:auto!important;}'
            . '</style>'
            . '<script id="ugentic-imported-content-guard-js" data-ugentic-no-save="true">(function(){'
            . 'var selector="img[data-src],img[data-lazy-src],img[data-original],img[data-srcset],'
            . 'source[data-srcset],source[data-lazy-srcset],video[data-poster],'
            . '[data-bg],[data-background],[data-bg-src],[data-background-image]";'
            . 'function safe(value){value=String(value||"").trim();return value&&value.charAt(0)!=="#"&&!/^(?:javascript|vbscript|data\\s*:\\s*text)/i.test(value);}'
            . 'function first(el,names){for(var i=0;i<names.length;i++){var value=el.getAttribute(names[i]);if(safe(value))return value;}return"";}'
            . 'function hydrate(root){var nodes=[];if(root&&root.nodeType===1&&root.matches(selector))nodes.push(root);'
            . 'if(root&&root.querySelectorAll)nodes=nodes.concat([].slice.call(root.querySelectorAll(selector)));'
            . 'nodes.forEach(function(el){var tag=el.tagName.toLowerCase(),value;'
            . 'if(tag==="img"){value=first(el,["data-src","data-lazy-src","data-original"]);'
            . 'if(value&&(!el.getAttribute("src")||/^(?:data:image\\/(?:gif|png);base64,|about:blank|#)/i.test(el.getAttribute("src"))))el.setAttribute("src",value);'
            . 'value=first(el,["data-srcset","data-lazy-srcset"]);if(value&&!el.getAttribute("srcset"))el.setAttribute("srcset",value);}'
            . 'if(tag==="source"){value=first(el,["data-srcset","data-lazy-srcset"]);if(value&&!el.getAttribute("srcset"))el.setAttribute("srcset",value);}'
            . 'if(tag==="video"){value=first(el,["data-poster"]);if(value&&!el.getAttribute("poster"))el.setAttribute("poster",value);}'
            . 'value=first(el,["data-bg","data-background","data-bg-src","data-background-image"]);'
            . 'if(value&&!el.style.backgroundImage)el.style.backgroundImage="url("+JSON.stringify(value)+")";'
            . '});}'
            . 'function start(){hydrate(document);if(!window.MutationObserver)return;'
            . 'new MutationObserver(function(records){records.forEach(function(record){[].slice.call(record.addedNodes||[]).forEach(hydrate);});})'
            . '.observe(document.documentElement,{childList:true,subtree:true});}'
            . 'if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",start,{once:true});else start();'
            . '})();</script>';
    }

    private static function processor_remove_loading_root_classes($processor)
    {
        $classes = preg_split('/\s+/', trim((string) $processor->get_attribute('class')));
        $classes = self::remove_loading_root_classes($classes);
        if (empty($classes)) {
            $processor->remove_attribute('class');
            return;
        }

        $processor->set_attribute('class', implode(' ', $classes));
    }

    private static function neutralize_imported_loading_state_fallback($html)
    {
        $html = preg_replace_callback('/<(html|body)\b[^>]*>/i', function ($match) {
            $tag = $match[0];
            $classes = preg_split('/\s+/', trim(self::fallback_get_attribute($tag, 'class')));
            $classes = self::remove_loading_root_classes($classes);
            if (empty($classes)) {
                return self::fallback_remove_attribute($tag, 'class');
            }
            return self::fallback_set_attribute($tag, 'class', implode(' ', $classes));
        }, $html);

        return preg_replace_callback('/<([a-z][\w:-]*)\b[^>]*>/i', function ($match) {
            $tag_name = strtolower($match[1]);
            if (in_array($tag_name, ['html', 'body', 'script', 'style', 'template', 'textarea', 'noscript'], true)) {
                return $match[0];
            }

            $classes = preg_split('/\s+/', trim(self::fallback_get_attribute($match[0], 'class')));
            $classes = array_values(array_filter($classes));
            if (!self::is_imported_loader_signature(self::fallback_get_attribute($match[0], 'id'), $classes)) {
                return $match[0];
            }

            $tag = self::fallback_set_attribute($match[0], 'data-ugentic-no-save', 'imported-loader');
            $tag = self::fallback_set_attribute($tag, 'aria-hidden', 'true');
            return self::fallback_set_attribute(
                $tag,
                'style',
                self::merge_hidden_loader_style(self::fallback_get_attribute($tag, 'style'))
            );
        }, $html);
    }

    private static function remove_loading_root_classes($classes)
    {
        $classes = array_values(array_filter((array) $classes));
        return array_values(array_filter($classes, function ($class) {
            return !preg_match('/^(?:is-loading|loading|preloading|is-preloading|page-loading|site-loading|pace-running|page-leaving)$/i', (string) $class);
        }));
    }

    private static function is_imported_loader_signature($id, $classes)
    {
        $id = strtolower(trim((string) $id));
        if ($id !== '' && preg_match('/^(?:spinner|preloader|page-loader|page-loading|loader-wrapper|site-loader|site-preloader|loading-screen|loading-overlay|loader-overlay|page-wipe|page-transition|transition-overlay)$/', $id)) {
            return true;
        }

        foreach ((array) $classes as $class) {
            $class = strtolower(trim((string) $class));
            if ($class === '' || strpos($class, 'ugentic') !== false || strpos($class, 'wp-') === 0) {
                continue;
            }
            if (preg_match('/^(?:preloader|page-loader|page-loading|loader-wrapper|site-loader|site-preloader|loading-screen|loading-overlay|loader-overlay|page-wipe|page-transition|transition-overlay|pace)$/', $class)) {
                return true;
            }
        }

        return false;
    }

    private static function merge_hidden_loader_style($style)
    {
        $style = trim((string) $style);
        $style = rtrim($style, ';');
        $hidden = 'display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;animation:none!important;transition:none!important;';
        return $style === '' ? $hidden : $style . ';' . $hidden;
    }

    /**
     * Remove a UTF-8 byte-order mark from the start of imported HTML.
     *
     * A BOM emitted inside the document body creates an anonymous line box and
     * a visible strip above otherwise full-bleed faithful pages.
     */
    public static function strip_utf8_bom($html)
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        while (strncmp($html, "\xEF\xBB\xBF", 3) === 0) {
            $html = substr($html, 3);
        }

        return preg_replace('/^\x{FEFF}+/u', '', $html);
    }

    /**
     * Remove WordPress admin-bar and transient editor artifacts that must never become saved content.
     *
     * This is intentionally narrow: it only strips the actual admin bar, known
     * admin-bar assets, saved save overlays, admin/editor body/html classes,
     * and exact admin-bar offsets on document/page wrapper chrome.
     */
    public static function scrub_admin_bar_artifacts($html)
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        $html = self::strip_utf8_bom($html);
        $html = self::remove_element_by_id($html, 'wpadminbar');
        $html = self::remove_element_by_id($html, 'ugentic-save-overlay');

        $html = preg_replace(
            '/\s*<style\b(?=[^>]*(?:id=["\'](?:ugentic-admin-bar-(?:bump|offset)|admin-bar[^"\']*|wp-admin-bar[^"\']*)["\']))[^>]*>[\s\S]*?<\/style>/i',
            '',
            $html
        );
        $html = preg_replace(
            '/\s*<link\b(?=[^>]*(?:id=["\']admin-bar[^"\']*["\']|href=["\'][^"\']*admin-bar[^"\']*\.css[^"\']*["\']))[^>]*>/i',
            '',
            $html
        );
        $html = preg_replace(
            '/\s*<script\b(?=[^>]*(?:id=["\']admin-bar[^"\']*["\']|src=["\'][^"\']*admin-bar[^"\']*\.js[^"\']*["\']))[^>]*>[\s\S]*?<\/script>/i',
            '',
            $html
        );

        $html = preg_replace_callback('/<([a-z][\w:-]*)\b[^>]*>/i', function ($match) {
            return self::scrub_opening_tag($match[0], strtolower($match[1]));
        }, $html);

        $html = preg_replace('/\s(class|style)=(["\'])\s*\2/i', '', $html);
        $html = preg_replace('/<style\b[^>]*>\s*<\/style>/i', '', $html);

        return trim($html);
    }

    /**
     * Remove legacy Tahmir compatibility nodes from a render copy.
     */
    public static function strip_legacy_static_runtime($html)
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        foreach ([
            'ugentic-static-clone-fixes',
            'ugentic-static-clone-runtime',
            'ugentic-static-snapshot-guard',
        ] as $id) {
            $html = self::remove_element_by_id($html, $id);
        }

        return $html;
    }

    /**
     * Materialize script-dependent reveal/lazy states as ordinary HTML.
     *
     * This is intended for an anonymous render copy only. It does not add a
     * Tahmir style/script block and it never needs to be written to current_html.
     */
    public static function materialize_static_snapshot($html)
    {
        if (!is_string($html) || $html === '' || strpos($html, '<') === false) {
            return $html;
        }

        $html = self::strip_legacy_static_runtime($html);

        if (class_exists('WP_HTML_Tag_Processor')) {
            $processor = new WP_HTML_Tag_Processor($html);
            while ($processor->next_tag()) {
                $tag_name = strtolower((string) $processor->get_tag());
                if (in_array($tag_name, ['script', 'style', 'template', 'textarea', 'noscript'], true)) {
                    continue;
                }

                $inline_style = (string) $processor->get_attribute('style');
                $final_inline_style = self::finalize_inline_transition_reveal_style($inline_style);
                if ($final_inline_style !== $inline_style) {
                    $processor->set_attribute('style', $final_inline_style);
                }

                $classes = preg_split('/\s+/', trim((string) $processor->get_attribute('class')));
                $classes = array_values(array_filter($classes));
                $is_framer_appear = $processor->get_attribute('data-framer-appear-id') !== null;
                $is_reveal = self::is_static_reveal_gate(
                    $classes,
                    $processor->get_attribute('data-aos') !== null,
                    $is_framer_appear
                );

                if ($is_reveal) {
                    if (self::class_list_matches($classes, '/^(?:reveal(?:-(?:left|right|scale))?)$/i')) {
                        $classes = self::append_unique_classes($classes, ['visible', 'is-visible']);
                    }
                    if (
                        $processor->get_attribute('data-aos') !== null
                        || in_array('aos-init', $classes, true)
                    ) {
                        $classes = self::append_unique_classes($classes, ['aos-init', 'aos-animate']);
                    }
                    if (self::class_list_matches($classes, '/^(?:wow|fadeIn(?:Up|Down|Left|Right)?)$/i')) {
                        $classes = self::append_unique_classes($classes, ['animated']);
                    }
                    $processor->set_attribute('class', implode(' ', $classes));
                    $processor->set_attribute(
                        'style',
                        self::merge_final_visibility_style(
                            (string) $processor->get_attribute('style'),
                            $is_framer_appear ? self::framer_layout_transform(
                                $processor->get_attribute('data-framer-layout-hint-center-x') !== null,
                                $processor->get_attribute('data-framer-layout-hint-center-y') !== null
                            ) : ''
                        )
                    );
                }

                self::materialize_processor_lazy_attributes($processor, $tag_name);
            }

            return $processor->get_updated_html();
        }

        return self::materialize_static_snapshot_fallback($html);
    }

    private static function materialize_processor_lazy_attributes($processor, $tag_name)
    {
        if ($tag_name === 'img') {
            $current_source = trim((string) $processor->get_attribute('src'));
            if ($current_source !== '' && $current_source[0] === '#') {
                $processor->remove_attribute('src');
            }
            $source = self::first_safe_asset_attribute($processor, ['data-src', 'data-lazy-src', 'data-original']);
            if ($source !== '') {
                $processor->set_attribute('src', $source);
            }
            foreach (['data-src', 'data-lazy-src', 'data-original'] as $name) {
                $processor->remove_attribute($name);
            }
        }

        if ($tag_name === 'img' || $tag_name === 'source') {
            $srcset = self::first_safe_srcset_attribute($processor, ['data-srcset', 'data-lazy-srcset']);
            if ($srcset !== '') {
                $processor->set_attribute('srcset', $srcset);
            }
            foreach (['data-srcset', 'data-lazy-srcset'] as $name) {
                $processor->remove_attribute($name);
            }
        }

        if ($tag_name === 'video') {
            $poster = trim((string) $processor->get_attribute('data-poster'));
            if (self::is_safe_static_asset_url($poster)) {
                $processor->set_attribute('poster', $poster);
            }
            $processor->remove_attribute('data-poster');
        }

        $background = self::first_safe_asset_attribute(
            $processor,
            ['data-bg', 'data-background', 'data-bg-src', 'data-background-image']
        );
        if ($background !== '') {
            $style = (string) $processor->get_attribute('style');
            if (!preg_match('/(?:^|;)\s*background-image\s*:/i', $style)) {
                $style = rtrim(trim($style), ';');
                $style .= ($style === '' ? '' : ';') . 'background-image:url("' . str_replace(['\\', '"'], ['\\\\', '\\"'], $background) . '");';
                $processor->set_attribute('style', $style);
            }
        }
        foreach (['data-bg', 'data-background', 'data-bg-src', 'data-background-image'] as $name) {
            $processor->remove_attribute($name);
        }
    }

    private static function first_safe_asset_attribute($processor, $names)
    {
        foreach ($names as $name) {
            $value = trim((string) $processor->get_attribute($name));
            if (self::is_safe_static_asset_url($value)) {
                return $value;
            }
        }
        return '';
    }

    private static function first_safe_srcset_attribute($processor, $names)
    {
        foreach ($names as $name) {
            $value = trim((string) $processor->get_attribute($name));
            if (self::is_safe_static_srcset($value)) {
                return $value;
            }
        }
        return '';
    }

    private static function materialize_static_snapshot_fallback($html)
    {
        $protected = [];
        $html = preg_replace_callback(
            '/<script\b[^>]*>[\s\S]*?<\/script>|<style\b[^>]*>[\s\S]*?<\/style>|<template\b[^>]*>[\s\S]*?<\/template>|<textarea\b[^>]*>[\s\S]*?<\/textarea>|<noscript\b[^>]*>[\s\S]*?<\/noscript>|<!--[\s\S]*?-->/i',
            function ($match) use (&$protected) {
                $key = '%%UGENTIC_STATIC_PROTECTED_' . count($protected) . '%%';
                $protected[$key] = $match[0];
                return $key;
            },
            $html
        );

        $html = preg_replace_callback('/<([a-z][\w:-]*)\b[^>]*>/i', function ($match) {
            $tag = $match[0];
            $tag_name = strtolower($match[1]);
            $inline_style = self::fallback_get_attribute($tag, 'style');
            $final_inline_style = self::finalize_inline_transition_reveal_style($inline_style);
            if ($final_inline_style !== $inline_style) {
                $tag = self::fallback_set_attribute($tag, 'style', $final_inline_style);
            }
            $classes = preg_split('/\s+/', trim(self::fallback_get_attribute($tag, 'class')));
            $classes = array_values(array_filter($classes));
            $has_aos = self::fallback_has_attribute($tag, 'data-aos');
            $is_framer_appear = self::fallback_has_attribute($tag, 'data-framer-appear-id');

            if (self::is_static_reveal_gate($classes, $has_aos, $is_framer_appear)) {
                if (self::class_list_matches($classes, '/^(?:reveal(?:-(?:left|right|scale))?)$/i')) {
                    $classes = self::append_unique_classes($classes, ['visible', 'is-visible']);
                }
                if ($has_aos || in_array('aos-init', $classes, true)) {
                    $classes = self::append_unique_classes($classes, ['aos-init', 'aos-animate']);
                }
                if (self::class_list_matches($classes, '/^(?:wow|fadeIn(?:Up|Down|Left|Right)?)$/i')) {
                    $classes = self::append_unique_classes($classes, ['animated']);
                }
                $tag = self::fallback_set_attribute($tag, 'class', implode(' ', $classes));
                $tag = self::fallback_set_attribute(
                    $tag,
                    'style',
                    self::merge_final_visibility_style(
                        self::fallback_get_attribute($tag, 'style'),
                        $is_framer_appear ? self::framer_layout_transform(
                            self::fallback_has_attribute($tag, 'data-framer-layout-hint-center-x'),
                            self::fallback_has_attribute($tag, 'data-framer-layout-hint-center-y')
                        ) : ''
                    )
                );
            }

            if ($tag_name === 'img') {
                $current_source = trim(self::fallback_get_attribute($tag, 'src'));
                if ($current_source !== '' && $current_source[0] === '#') {
                    $tag = self::fallback_remove_attribute($tag, 'src');
                }
                foreach (['data-src', 'data-lazy-src', 'data-original'] as $name) {
                    $value = self::fallback_get_attribute($tag, $name);
                    if (self::is_safe_static_asset_url($value)) {
                        $tag = self::fallback_set_attribute($tag, 'src', $value);
                        break;
                    }
                }
                foreach (['data-src', 'data-lazy-src', 'data-original'] as $name) {
                    $tag = self::fallback_remove_attribute($tag, $name);
                }
            }

            if ($tag_name === 'img' || $tag_name === 'source') {
                foreach (['data-srcset', 'data-lazy-srcset'] as $name) {
                    $value = self::fallback_get_attribute($tag, $name);
                    if (self::is_safe_static_srcset($value)) {
                        $tag = self::fallback_set_attribute($tag, 'srcset', $value);
                        break;
                    }
                }
                foreach (['data-srcset', 'data-lazy-srcset'] as $name) {
                    $tag = self::fallback_remove_attribute($tag, $name);
                }
            }

            if ($tag_name === 'video') {
                $poster = self::fallback_get_attribute($tag, 'data-poster');
                if (self::is_safe_static_asset_url($poster)) {
                    $tag = self::fallback_set_attribute($tag, 'poster', $poster);
                }
                $tag = self::fallback_remove_attribute($tag, 'data-poster');
            }

            foreach (['data-bg', 'data-background', 'data-bg-src', 'data-background-image'] as $name) {
                $value = self::fallback_get_attribute($tag, $name);
                if (self::is_safe_static_asset_url($value)) {
                    $style = self::fallback_get_attribute($tag, 'style');
                    if (!preg_match('/(?:^|;)\s*background-image\s*:/i', $style)) {
                        $style = rtrim(trim($style), ';');
                        $style .= ($style === '' ? '' : ';') . 'background-image:url("' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '");';
                        $tag = self::fallback_set_attribute($tag, 'style', $style);
                    }
                    break;
                }
            }
            foreach (['data-bg', 'data-background', 'data-bg-src', 'data-background-image'] as $name) {
                $tag = self::fallback_remove_attribute($tag, $name);
            }

            return $tag;
        }, $html);

        if (!empty($protected)) {
            $html = str_replace(array_keys($protected), array_values($protected), $html);
        }
        return $html;
    }

    private static function is_static_reveal_gate($classes, $has_aos, $is_framer_appear = false)
    {
        return $has_aos
            || $is_framer_appear
            || in_array('aos-init', $classes, true)
            || self::class_list_matches($classes, '/^(?:reveal(?:-(?:left|right|scale))?|wow|fadeIn(?:Up|Down|Left|Right)?)$/i');
    }

    private static function framer_layout_transform($center_x, $center_y)
    {
        if ($center_x && $center_y) {
            return 'translate(-50%,-50%)';
        }
        if ($center_x) {
            return 'translateX(-50%)';
        }
        if ($center_y) {
            return 'translateY(-50%)';
        }
        return 'none';
    }

    private static function class_list_matches($classes, $pattern)
    {
        foreach ($classes as $class) {
            if (preg_match($pattern, $class)) {
                return true;
            }
        }
        return false;
    }

    private static function append_unique_classes($classes, $additions)
    {
        foreach ($additions as $class) {
            if (!in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }
        return $classes;
    }

    private static function merge_final_visibility_style($style, $transform = 'none')
    {
        $kept = [];
        foreach (preg_split('/;(?![^()]*\))/', (string) $style) as $part) {
            $declaration = trim($part);
            if ($declaration === '' || preg_match('/^(?:opacity|visibility|transform)\s*:/i', $declaration)) {
                continue;
            }
            $kept[] = $declaration;
        }
        $prefix = empty($kept) ? '' : implode(';', $kept) . ';';
        return $prefix . 'opacity:1!important;visibility:visible!important;transform:' . $transform . '!important;';
    }

    /**
     * Freeze inline JavaScript entrance animations at their visible end state.
     *
     * Lovable commonly writes the animation gate directly into style attributes
     * (opacity:0 / translateY(115%)) rather than using AOS/reveal classes. Once
     * the application runtime is removed those elements otherwise stay hidden.
     * Requiring both transition-property and will-change keeps this narrowly
     * scoped to animation state and avoids exposing ordinary hidden UI panels.
     */
    private static function finalize_inline_transition_reveal_style($style)
    {
        $style = (string) $style;
        if (
            $style === ''
            || !preg_match('/(?:^|;)\s*transition-property\s*:[^;]*(?:opacity|transform)/i', $style)
            || !preg_match('/(?:^|;)\s*will-change\s*:[^;]*(?:opacity|transform)/i', $style)
        ) {
            return $style;
        }

        $style = preg_replace(
            '/(^|;)\s*opacity\s*:\s*0(?:\.0+)?\s*(?=;|$)/i',
            '$1opacity:1',
            $style
        );
        $style = preg_replace('/translate3d\(\s*[^,]+,\s*[^,]+,\s*[^)]+\)/i', 'translate3d(0px, 0px, 0px)', $style);
        $style = preg_replace('/translate([XY])\(\s*[^)]+\)/i', 'translate$1(0px)', $style);
        $style = preg_replace('/translate\(\s*[^,]+,\s*[^)]+\)/i', 'translate(0px, 0px)', $style);
        return $style;
    }

    private static function is_safe_static_asset_url($url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || $url[0] === '#' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }
        if (preg_match('/^(?:javascript|vbscript|data\s*:\s*text|data\s*:\s*image\/svg)/i', $url)) {
            return false;
        }
        if (preg_match('/^data:/i', $url)) {
            return (bool) preg_match('#^data:image/(?:png|gif|jpe?g|webp|avif);base64,#i', $url);
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $match)) {
            return in_array(strtolower($match[1]), ['http', 'https'], true);
        }
        return true;
    }

    private static function is_safe_static_srcset($srcset)
    {
        $srcset = trim((string) $srcset);
        if ($srcset === '' || preg_match('/(?:javascript|vbscript|data\s*:\s*text|data\s*:\s*image\/svg)/i', $srcset)) {
            return false;
        }
        foreach (preg_split('/\s*,\s*/', $srcset) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate), 2);
            if (empty($parts[0]) || !self::is_safe_static_asset_url($parts[0])) {
                return false;
            }
        }
        return true;
    }

    private static function fallback_has_attribute($tag, $name)
    {
        return (bool) preg_match('/\s' . preg_quote($name, '/') . '(?:\s*=|\s|\/?>)/i', $tag);
    }

    private static function fallback_get_attribute($tag, $name)
    {
        if (preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/is', $tag, $match)) {
            return html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*([^\s>]+)/i', $tag, $match)) {
            return html_entity_decode(trim($match[1], "\"'"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }

    private static function fallback_remove_attribute($tag, $name)
    {
        return preg_replace(
            '/\s+' . preg_quote($name, '/') . '(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?/i',
            '',
            $tag
        );
    }

    private static function fallback_set_attribute($tag, $name, $value)
    {
        $tag = self::fallback_remove_attribute($tag, $name);
        $encoded = htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s*(\/?>)$/', ' ' . $name . '="' . $encoded . '"$1', $tag, 1);
    }
    /**
     * Detect editor/runtime artifacts that must never be persisted as page HTML.
     *
     * The checks use reserved Tahmir/WordPress editor signatures only; ordinary
     * production classes such as ugentic-section and imported asset URLs remain valid.
     *
     * @return string[] Human-readable contamination signatures.
     */
    public static function detect_hard_editor_artifacts($html)
    {
        if (!is_string($html) || $html === '') {
            return [];
        }

        // Global-section editing keeps an escaped copy of the original element
        // in this transient attribute. Ignore the snapshot while scanning, then
        // strip the attribute during normal marker cleanup.
        $scan_html = preg_replace(
            '/\s+data-ugentic-original-html=(["\'])(.*?)\1/is',
            '',
            $html
        );
        if (!is_string($scan_html)) {
            $scan_html = $html;
        }

        $patterns = [
            'editor no-save marker' => '/\bdata-ugentic-no-save\b/i',
            'v2 responsive state attribute' => '/\bdata-ug-v2-[\w-]+\s*=/i',
            'v2/editor state class' => '/\bclass=(["\'])[^"\']*\b(?:ug-v2-[\w-]+|ugentic-(?:sidebar|settings-panel|widget-panel|edit-bar|context-toolbar|inline-toolbar|inline-text-editor|modal(?:-overlay)?|widget-modal|ai-chat(?:-toggle)?|section-action-bar|section-drop-placeholder|section-divider|section-prompt|section-toast|save-(?:dialog|orb|copy))|admin-bar|customize-support)\b[^"\']*\1/i',
            'editor chrome id' => '/\bid=(["\'])(?:ugentic-builder|ugentic-sidebar-(?:left|right)|ugentic-editor-loading-bar|ug-v2-[\w-]+|wpadminbar|a11y-speak-[\w-]+)\1/i',
            'WordPress media template' => '/<script\b[^>]*\bid=(["\'])tmpl-(?:attachment|media|image|gallery|embed|uploader|playlist|crop|site-icon|audio|video)[^"\']*\1/i',
            'WordPress editor runtime asset' => '/<(?:link|script)\b[^>]*\bid=(["\'])(?:wp-(?:util|backbone|media|plupload|a11y|i18n|hooks|dom-ready|api-fetch|url|keycodes)[^"\']*|media-(?:models|views|editor|widgets)[^"\']*|(?:backbone|underscore|shortcode|clipboard|hoverintent|jquery-ui-[\w-]+)-js)\1/i',
            'Tahmir editor asset' => '/<(?:link|script)\b[^>]*(?:href|src)=(["\'])[^"\']*\/wp-content\/plugins\/(?:tahmirai|ugentic-builder)\/[^"\']*(?:editor|settings-panel|widget-panel|widget-controls|element-detector|global-sections|section-designer|builder-shell-v2(?:-fixes|-pro)?|builder-inspector-(?:modern|authority)|tahmir-ui-tokens)\.(?:css|js)(?:[?"\'])/i',
            'Tahmir editor configuration' => '/\bugenticEditorConfig\b|\bugenticGlobalSections\b/i',
            'editor runtime boundary' => '/ugentic-editor-runtime:(?:start|end)/i',
            'legacy static-clone runtime' => '/\bugentic-static-(?:clone-(?:fixes|runtime)|snapshot-guard)\b/i',
            'editor layout variable' => '/--ug-(?:v2|editbar|left-panel|wp-adminbar)[\w-]*\s*:/i',
        ];

        $issues = [];
        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $scan_html)) {
                $issues[] = $label;
            }
        }
        return $issues;
    }

    /**
     * Detect hard UI/runtime contamination plus removable edit-session markers.
     *
     * @return string[]
     */
    public static function detect_editor_artifacts($html)
    {
        if (!is_string($html) || $html === '') {
            return [];
        }

        $issues = self::detect_hard_editor_artifacts($html);
        $soft_patterns = [
            'editor selection attribute' => '/\b(?:contenteditable|data-ugentic-(?:editable|edit-root|saveable|selected|hover|element-id|selection-id|widget|link-id|original-(?:animation|transition|html|transform|text)|(?:full-text|all-words|target-number)|animation-paused|editor-[\w-]+))\s*=/i',
            'Tahmir layer metadata' => '/\bdata-ug-layer-[\w-]+\s*=/i',
            'WordPress render-only style' => '/<style\b[^>]*\bid=(["\'])(?:global-styles|classic-theme-styles|core-block-supports)-inline-css\1/i',
            'page runtime scrollbar variable' => '/<(?:html|body)\b[^>]*\bstyle=(["\'])[^"\']*--scrollbar-offset\s*:/i',
            'editor selection class' => '/\bclass=(["\'])[^"\']*\bugentic-(?:selected(?:-row|-col)?|element-selected|element-hover|hover|section-hover|editing|edit-active|dragging|drag-over|drop-target|sf-mode|sf-element-selected|sf-element-hover|preview-mode|public-edit-mode|traditional-(?:panel|settings)-open|save-lock)\b[^"\']*\1/i',
        ];
        foreach ($soft_patterns as $label => $pattern) {
            if (preg_match($pattern, $html) && !in_array($label, $issues, true)) {
                $issues[] = $label;
            }
        }
        return $issues;
    }

    /**
     * Central persistence boundary for page HTML.
     *
     * Hard editor UI/runtime nodes reject the write. Soft selection markers
     * are removed, then the result is verified again before storage.
     *
     * @return array{ok:bool,html:string,artifacts:string[]}
     */
    public static function prepare_persisted_html($html)
    {
        if (!is_string($html)) {
            return ['ok' => false, 'html' => '', 'artifacts' => ['invalid HTML payload']];
        }

        $hard = self::detect_hard_editor_artifacts($html);
        if (!empty($hard)) {
            return ['ok' => false, 'html' => $html, 'artifacts' => array_values(array_unique($hard))];
        }

        $clean = self::strip_utf8_bom($html);
        $clean = self::strip_editor_markers($clean);
        $remaining = self::detect_editor_artifacts($clean);

        return [
            'ok' => empty($remaining),
            'html' => trim($clean),
            'artifacts' => array_values(array_unique($remaining)),
        ];
    }

    private static function should_skip_editable_marker($attrs)
    {
        $attrs = (string) $attrs;

        if (preg_match('/\bdata-ugentic-editable\s*=/i', $attrs)) {
            return true;
        }

        if (preg_match('/\b(?:data-ugentic-no-save|data-ugentic-global|hidden|inert)\b/i', $attrs)) {
            return true;
        }

        if (preg_match('/\baria-hidden\s*=\s*(["\'])(.*?)\1/i', $attrs, $match)) {
            $value = strtolower(trim($match[2]));
            if ($value === 'true') {
                return true;
            }
        }

        if (preg_match('/\brole\s*=\s*(["\'])(.*?)\1/i', $attrs, $match)) {
            $value = strtolower(trim($match[2]));
            if ($value === 'presentation' || $value === 'none') {
                return true;
            }
        }

        if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/is', $attrs, $match)) {
            $class = strtolower($match[2]);
            if (preg_match('/\b(?:sr-only|screen-reader-text|visually-hidden|dashicons|material-icons|lucide|fa|fas|far|fab|fal|fa-solid|fa-regular|fa-brands)\b/', $class)) {
                return true;
            }
            if (preg_match('/(?:^|[\s_-])(?:icon|ico|glyph|chevron|caret|arrow|dot|bullet)(?:[\s_-]|$)/', $class)) {
                return true;
            }
        }

        return false;
    }

    private static function scrub_opening_tag($tag, $tag_name)
    {
        $is_wrapper = in_array($tag_name, ['html', 'body'], true)
            || preg_match('/\bid=(["\'])ugentic-page-content\1/i', $tag)
            || preg_match('/\bclass=(["\'])[^"\']*\bugentic-page-content\b[^"\']*\1/i', $tag);

        if (!$is_wrapper) {
            return $tag;
        }

        $tag = preg_replace_callback('/\sclass=(["\'])(.*?)\1/is', function ($match) {
            $classes = preg_split('/\s+/', trim($match[2]));
            $classes = array_values(array_filter($classes, function ($class) {
                $class = strtolower($class);
                return $class !== '' && $class !== 'admin-bar' && $class !== 'ugentic-save-lock';
            }));

            if (empty($classes)) {
                return '';
            }

            return ' class=' . $match[1] . implode(' ', $classes) . $match[1];
        }, $tag);

        $tag = preg_replace_callback('/\sstyle=(["\'])(.*?)\1/is', function ($match) {
            $style = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $parts = preg_split('/;(?![^()]*\))/', $style);
            $kept = [];

            foreach ($parts as $part) {
                $declaration = trim($part);
                if ($declaration === '') {
                    continue;
                }

                if (preg_match('/^(?:margin-top|padding-top)\s*:\s*(?:32px|46px|var\(--wp-admin--admin-bar--height\))\s*(?:!important)?$/i', $declaration)) {
                    continue;
                }

                $kept[] = $declaration;
            }

            if (empty($kept)) {
                return '';
            }

            $encoded = htmlspecialchars(implode('; ', $kept) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return ' style=' . $match[1] . $encoded . $match[1];
        }, $tag);

        return $tag;
    }

    private static function remove_element_by_id($html, $id)
    {
        $offset = 0;
        $id_pattern = preg_quote($id, '/');

        while (preg_match('/<([a-z][\w:-]*)\b[^>]*\bid=(["\'])' . $id_pattern . '\2[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $open = $match[0][0];
            $tag = strtolower($match[1][0]);
            $after_open = $start + strlen($open);

            if (preg_match('/\/>\s*$/', $open)) {
                $html = substr_replace($html, '', $start, strlen($open));
                $offset = $start;
                continue;
            }

            $depth = 1;
            $cursor = $after_open;
            $end = null;
            $tag_pattern = '/<\/?' . preg_quote($tag, '/') . '\b[^>]*>/i';

            while (preg_match($tag_pattern, $html, $tag_match, PREG_OFFSET_CAPTURE, $cursor)) {
                $token = $tag_match[0][0];
                $token_start = $tag_match[0][1];
                $cursor = $token_start + strlen($token);

                if (preg_match('/^<\//', $token)) {
                    $depth--;
                    if ($depth === 0) {
                        $end = $cursor;
                        break;
                    }
                } elseif (!preg_match('/\/>\s*$/', $token)) {
                    $depth++;
                }
            }

            if ($end === null) {
                $end = $after_open;
            }

            $html = substr_replace($html, '', $start, $end - $start);
            $offset = $start;
        }

        return $html;
    }
}
