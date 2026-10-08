<?php
/** Preserve imported form definitions until the Pro form editor is available. */
if (!defined('ABSPATH')) { exit; }
final class Tahmir_Free_Form_Policy {
    public static function validate($before, &$after) {
        $display_before = class_exists('Ugentic_Form_Renderer') ? Ugentic_Form_Renderer::sync_faithful_forms($before) : $before;
        if (self::signatures($display_before) !== self::signatures($after)) {
            wp_send_json_error([
                'code'=>'tahmir_pro_required',
                'message'=>__('Form editing requires Tahmir AI Pro. Your page was not changed.', 'tahmir-ai-to-wp-converter'),
            ],403);
        }
        // After validating the field definition, retain the exact stored form.
        // Browser validation/runtime attributes must never become saved edits.
        preg_match_all('/<form\b[^>]*>[\s\S]*?<\/form>/i', $before, $forms);
        $index = 0;
        $after = preg_replace_callback('/<form\b[^>]*>[\s\S]*?<\/form>/i', static function ($match) use (&$index, $forms) {
            return $forms[0][$index++] ?? $match[0];
        }, $after);
    }

    private static function signatures($html) {
        $html=Ugentic_Security::strip_imported_active_content((string)$html);
        $html=Ugentic_HTML_Cleaner::strip_editor_markers($html);
        preg_match_all('/\[ugentic_form\b[^\]]*\]/i',$html,$shortcodes);
        $previous=libxml_use_internal_errors(true);
        $dom=new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
        libxml_clear_errors();libxml_use_internal_errors($previous);
        $forms=[];
        foreach($dom->getElementsByTagName('form') as $form) {
            $form->removeAttribute('novalidate'); $form->removeAttribute('aria-live');
            $form->removeAttribute('action'); // The render deliberately omits external submission URLs.
            $form->removeAttribute('inert'); $form->removeAttribute('aria-label');
            self::normalize($form);
            $forms[]=preg_replace('/>\s+</','><',$dom->saveHTML($form));
        }
        $forms=array_merge($forms,$shortcodes[0]??[]);
        return $forms;
    }
    private static function normalize($node) {
        if($node instanceof DOMElement) {
            $attributes=[];
            foreach(iterator_to_array($node->attributes) as $attribute) {
                $name=$attribute->name;$value=$attribute->value;
                if(strpos($name,'data-ugentic-')===0 || strpos($name,'data-tahmir-')===0 || in_array($name,['contenteditable','spellcheck','inputmode'],true)) {$node->removeAttribute($name);continue;}
                if($name==='class') {
                    $value=trim(preg_replace('/\bugentic-(?:editable|selected|hover|editing)\b/','',$value));
                    if($value==='') {$node->removeAttribute($name);continue;}
                }
                $attributes[$name]=$value;$node->removeAttribute($name);
            }
            ksort($attributes);foreach($attributes as $name=>$value)$node->setAttribute($name,$value);
        }
        foreach($node->childNodes as $child) self::normalize($child);
    }
}
