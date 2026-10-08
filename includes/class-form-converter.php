<?php
/**
 * Form Converter — Extracts HTML forms from imported pages and converts them
 * to Tahmir AI form entries in the database.
 *
 * Extracted from class-importer.php for single-responsibility.
 *
 * @package Ugentic_Builder
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Form_Converter
{
    /**
     * Cache imported form lookups during a multi-page import.
     *
     * @var array<string,int>
     */
    private $import_form_fingerprint_cache = [];

    /**
     * Detect and convert HTML forms to Ugentic forms.
     *
     * @param string $html The HTML content to process
     * @return array ['html' => modified HTML, 'forms_created' => count]
     */

    /**
     * Check if form is a search form
     */

    /**
     * Analyze form fields from HTML
     */
    public function analyze_form_fields($form_content)
    {
        if (class_exists('Ugentic_Form_Engine')) {
            $analysis = Ugentic_Form_Engine::analyze_form_html('<form>' . $form_content . '</form>');
            if (!empty($analysis['fields'])) {
                return $analysis['fields'];
            }
        }

        $fields = [];
        $field_index = 0;
        $seen_names = []; // Track field names to prevent duplicates

        // Match input fields
        preg_match_all('/<input[^>]+>/i', $form_content, $input_matches);
        foreach ($input_matches[0] as $input) {
            $field = $this->parse_input_field($input);
            if ($field) {
                // Deduplicate by name — radio/checkbox groups share the same name
                $name = $field['name'] ?? '';
                if (!empty($name) && isset($seen_names[$name])) {
                    continue;
                }
                $field['id'] = 'field_' . $field_index++;
                $fields[] = $field;
                if (!empty($name)) {
                    $seen_names[$name] = true;
                }
            }
        }

        // Match textarea fields
        preg_match_all('/<textarea[^>]*>.*?<\/textarea>/is', $form_content, $textarea_matches);
        foreach ($textarea_matches[0] as $textarea) {
            $field = $this->parse_textarea_field($textarea);
            if ($field) {
                $name = $field['name'] ?? '';
                if (!empty($name) && isset($seen_names[$name])) {
                    continue;
                }
                $field['id'] = 'field_' . $field_index++;
                $fields[] = $field;
                if (!empty($name)) {
                    $seen_names[$name] = true;
                }
            }
        }

        // Match select fields
        preg_match_all('/<select[^>]*>.*?<\/select>/is', $form_content, $select_matches);
        foreach ($select_matches[0] as $select) {
            $field = $this->parse_select_field($select);
            if ($field) {
                $name = $field['name'] ?? '';
                if (!empty($name) && isset($seen_names[$name])) {
                    continue;
                }
                $field['id'] = 'field_' . $field_index++;
                $fields[] = $field;
                if (!empty($name)) {
                    $seen_names[$name] = true;
                }
            }
        }

        return $fields;
    }

    /**
     * Parse an input field
     */
    private function parse_input_field($input)
    {
        $type = 'text';
        if (preg_match('/type=["\']([^"\']+)[\'"]/i', $input, $type_match)) {
            $type = strtolower($type_match[1]);
        }

        if (in_array($type, ['submit', 'button', 'image', 'reset', 'hidden'])) {
            return null;
        }

        $type_map = [
            'text' => 'text',
            'email' => 'email',
            'tel' => 'tel',
            'phone' => 'tel',
            'number' => 'number',
            'url' => 'url',
            'date' => 'date',
            'time' => 'time',
            'datetime-local' => 'datetime-local',
            'password' => 'text',
            'checkbox' => 'checkbox',
            'radio' => 'radio',
        ];

        $our_type = $type_map[$type] ?? 'text';

        $name = '';
        if (preg_match('/name=["\']([^"\']+)[\'"]/i', $input, $name_match)) {
            $name = $name_match[1];
        }
        // Fallback: use id attribute when name is missing
        if (empty($name) && preg_match('/id=["\']([^"\']+)[\'"]/i', $input, $id_match)) {
            $name = $id_match[1];
        }

        $placeholder = '';
        if (preg_match('/placeholder=["\']([^"\']+)[\'"]/i', $input, $ph_match)) {
            $placeholder = $ph_match[1];
        }

        $required = preg_match('/required/i', $input);

        $label = $this->generate_label($name, $placeholder, $type);

        return [
            'type' => $our_type,
            'name' => $name ?: 'field_' . uniqid(),
            'label' => $label,
            'placeholder' => $placeholder,
            'required' => $required,
            'width' => 'full',
        ];
    }

    /**
     * Parse a textarea field
     */
    private function parse_textarea_field($textarea)
    {
        $name = '';
        if (preg_match('/name=["\']([^"\']+)[\'"]/i', $textarea, $name_match)) {
            $name = $name_match[1];
        }
        // Fallback: use id attribute when name is missing
        if (empty($name) && preg_match('/id=["\']([^"\']+)[\'"]/i', $textarea, $id_match)) {
            $name = $id_match[1];
        }

        $placeholder = '';
        if (preg_match('/placeholder=["\']([^"\']+)[\'"]/i', $textarea, $ph_match)) {
            $placeholder = $ph_match[1];
        }

        $required = preg_match('/required/i', $textarea);

        $label = $this->generate_label($name, $placeholder, 'textarea');

        return [
            'type' => 'textarea',
            'name' => $name ?: 'message',
            'label' => $label,
            'placeholder' => $placeholder,
            'required' => $required,
            'width' => 'full',
            'rows' => 4,
        ];
    }

    /**
     * Parse a select field
     */
    private function parse_select_field($select)
    {
        $name = '';
        if (preg_match('/name=["\']([^"\']+)[\'"]/i', $select, $name_match)) {
            $name = $name_match[1];
        }
        // Fallback: use id attribute when name is missing
        if (empty($name) && preg_match('/id=["\']([^"\']+)[\'"]/i', $select, $id_match)) {
            $name = $id_match[1];
        }

        $required = preg_match('/required/i', $select);

        $options = [];
        preg_match_all('/<option[^>]*value=["\']([^"\']*)["\'][^>]*>([^<]*)<\/option>/i', $select, $opt_matches, PREG_SET_ORDER);
        foreach ($opt_matches as $opt) {
            if (!empty(trim($opt[2]))) {
                $options[] = [
                    'value' => $opt[1] ?: $opt[2],
                    'label' => trim($opt[2]),
                ];
            }
        }

        if (empty($options)) {
            return null;
        }

        $label = $this->generate_label($name, '', 'select');

        return [
            'type' => 'select',
            'name' => $name ?: 'select_' . uniqid(),
            'label' => $label,
            'required' => $required,
            'width' => 'full',
            'options' => $options,
        ];
    }

    /**
     * Generate a readable label from field attributes
     */
    private function generate_label($name, $placeholder, $type)
    {
        if (!empty($placeholder) && strlen($placeholder) < 50) {
            return $placeholder;
        }

        if (!empty($name)) {
            $label = str_replace(['_', '-', '[', ']'], ' ', $name);
            $label = preg_replace('/\s+/', ' ', $label);
            return ucwords(trim($label));
        }

        $defaults = [
            'email' => 'Email Address',
            'tel' => 'Phone Number',
            'textarea' => 'Message',
            'text' => 'Text Field',
            'select' => 'Select Option',
        ];

        return $defaults[$type] ?? 'Field';
    }

    /**
     * Determine form type based on fields
     */
    public function determine_form_type($fields)
    {
        $has_email = false;
        $has_message = false;
        $has_phone = false;
        $has_name = false;

        foreach ($fields as $field) {
            $name_lower = strtolower($field['name']);
            $label_lower = strtolower($field['label']);

            if ($field['type'] === 'email') {
                $has_email = true;
            }
            if ($field['type'] === 'textarea') {
                $has_message = true;
            }
            if ($field['type'] === 'tel') {
                $has_phone = true;
            }
            if (strpos($name_lower, 'name') !== false || strpos($label_lower, 'name') !== false) {
                $has_name = true;
            }
        }

        if ($has_email && $has_message) {
            return 'contact';
        }
        if ($has_email && !$has_message && count($fields) <= 2) {
            return 'newsletter';
        }
        if ($has_email && $has_phone && $has_name) {
            return 'lead';
        }

        return 'contact';
    }

    /**
     * Extract submit button text from form
     */
    public function extract_submit_text($form_content)
    {
        if (preg_match('/<button[^>]*type=["\']submit["\'][^>]*>([^<]+)</i', $form_content, $btn_match)) {
            return trim(wp_strip_all_tags($btn_match[1]));
        }

        if (preg_match('/<input[^>]*type=["\']submit["\'][^>]*value=["\']([^"\']+)[\'"]/i', $form_content, $input_match)) {
            return trim($input_match[1]);
        }

        if (preg_match('/<button[^>]*>([^<]+)</i', $form_content, $btn_match2)) {
            $text = trim(wp_strip_all_tags($btn_match2[1]));
            if (!empty($text)) {
                return $text;
            }
        }

        return 'Submit';
    }

    /**
     * Extract inline <script> tags from form content
     * Preserves validation JS that would otherwise be lost during form conversion
     *
     * @param string $form_content The inner HTML of the form
     * @return string Extracted JavaScript code, or empty string
     */


    /**
     * Detect forms and register them in the DB WITHOUT replacing HTML.
     * Used for faithful-mode imports: the original form HTML is preserved
     * but annotated with data-ugentic-form-id so fields can be managed
     * from the backend form builder.
     *
     * @param string $html The HTML content to process
     * @param array $options Optional normalized AI form plans and selected model.
     * @return array ['html' => annotated HTML, 'forms_created' => count, 'form_ids' => array]
     */
    public function detect_and_register_forms($html, $options = [])
    {
        $database = ugentic_builder()->database;
        $forms_created = 0;
        $form_ids = [];
        $ai_plans = $this->normalize_ai_plan_map($options['ai_form_plans'] ?? []);
        $ai_model = sanitize_text_field($options['ai_model'] ?? '');
        $ai_forms_planned = 0;

        // Find all form tags
        $form_pattern = '/<form([^>]*)>(.*?)<\/form>/is';

        $html = preg_replace_callback($form_pattern, function ($matches) use ($database, &$forms_created, &$form_ids, &$ai_forms_planned, $ai_plans, $ai_model) {
            $form_attrs = $matches[1];
            $form_content = $matches[2];
            $form_html = $matches[0];
            if (preg_match('/data-ugentic-form-id=["\'](\d+)["\']/', $form_attrs, $known) && $database->get_form((int)$known[1])) { return $form_html; }
            $analysis = class_exists('Ugentic_Form_Engine')
                ? Ugentic_Form_Engine::analyze_form_html($form_html, ['mode' => 'faithful'])
                : null;
            $ai_plan = $this->find_ai_plan($ai_plans, $analysis);
            if ($analysis && $ai_plan) {
                $analysis = $this->apply_ai_plan_to_analysis($analysis, $ai_plan, $ai_model);
                $ai_forms_planned++;
            }

            // Preserve search, external/payment-like, and plugin-managed forms exactly.
            if ($analysis && ($analysis['decision'] ?? '') === 'skip') {
                return $form_html;
            }

            // Extract form attributes
            $action = '';
            if (preg_match('/action=["\']([^"\']*)["\']/', $form_html, $action_match)) {
                $action = $action_match[1];
            }

            // Skip forms with external actions (payment processors, etc)
            if (!empty($action) && (
            strpos($action, 'paypal.com') !== false ||
            strpos($action, 'stripe.com') !== false ||
            strpos($action, 'google.com') !== false
            )) {
                return $form_html;
            }

            // Analyze form fields
            $fields = $analysis ? ($analysis['fields'] ?? []) : $this->analyze_form_fields($form_content);

            // Need at least one field to create a form
            if (empty($fields) || ($analysis && intval($analysis['field_count'] ?? 0) === 0)) {
                return $form_html;
            }

            // Match fields by DOM ancestry, not substrings or numeric increments.
            $steps = $analysis['steps'] ?? [];
            $is_multi_step = count($steps) > 1;
            if ($analysis && !empty($analysis['html']) && $is_multi_step) {
                if (preg_match('/^<form\b[^>]*>([\s\S]*)<\/form>$/i', $analysis['html'], $step_match)) {
                    $form_content = $step_match[1];
                }
                if ($ai_plan) {
                    $form_content = $this->mark_ai_step_controls($form_content, $fields);
                }
            }

            // Determine form type based on fields
            $form_type = $ai_plan ? sanitize_key($ai_plan['form_kind'] ?? 'general') : $this->determine_form_type($fields);

            $engine_settings = $analysis['settings'] ?? [];
            $form_result = $this->get_or_create_imported_form($database, [
                'title' => 'Imported: ' . ucfirst($form_type) . ' Form',
                'description' => 'Auto-detected from faithful import — original styling preserved',
                'fields' => $fields,
                'settings' => array_merge($engine_settings, [
                    'submit_text' => $this->extract_submit_text($form_content) ?: 'Submit',
                    'success_message' => 'Thank you for your submission!',
                    'show_title' => false,
                    'layout' => 'stacked',
                    'is_multi_step' => $is_multi_step,
                    'step_count' => $is_multi_step ? count($steps) : 1,
                    'faithful_source' => true,
                    'source_mode' => 'faithful',
                    'preservation_mode' => 'preserve',
                ]),
                'confirmations' => $analysis['confirmations'] ?? [],
                'notifications' => [[
                        'email' => get_option('admin_email'),
                        'subject' => 'New ' . ucfirst($form_type) . ' Form Submission',
                    ]],
            ]);
            $form_id = intval($form_result['id'] ?? 0);

            if ($form_id) {
                if (!empty($form_result['created'])) {
                    $forms_created++;
                }
                $form_ids[$form_id] = $form_id;

                // Annotate the <form> tag with stable Ugentic attributes.
                $new_attrs = $this->with_ugentic_form_attrs(
                    $form_attrs,
                    $form_id,
                    'faithful',
                    $form_result['fingerprint'] ?? ($engine_settings['form_fingerprint'] ?? '')
                );
                if ($ai_plan) {
                    $new_attrs = $this->set_data_attribute($new_attrs, 'data-ugentic-ai-flow', $ai_plan['flow'] ?? 'single_step');
                    $new_attrs = $this->set_data_attribute($new_attrs, 'data-ugentic-ai-form-kind', $ai_plan['form_kind'] ?? 'general');
                    if ($ai_model !== '') {
                        $new_attrs = $this->set_data_attribute($new_attrs, 'data-ugentic-ai-model', $ai_model);
                    }
                }

                return '<form' . $new_attrs . '>' . $form_content . '</form>';
            }

            return $form_html;
        }, $html);

        return [
            'html' => $html,
            'forms_created' => $forms_created,
            'form_ids' => array_values($form_ids),
            'ai_forms_planned' => $ai_forms_planned,
        ];
    }

    /**
     * Audit and mark faithful forms after registration without replacing their
     * DOM. The shared browser runtime uses the canonical step annotations as a
     * recovery layer when a source bundle no longer runs inside WordPress.
     *
     * @param string $html Registered faithful HTML.
     * @param array $options Optional normalized AI form plans and selected model.
     * @return array{html:string,forms:int,complex_forms:int,multi_step_forms:int,fields:int}
     */

    /** Index model plans by the stable source form fingerprint. */private function normalize_ai_plan_map($plans) { return []; }

    private function find_ai_plan($plans, $analysis)
    {
        if (!is_array($analysis) || empty($analysis['fingerprint'])) {
            return null;
        }
        $form_ref = sanitize_text_field($analysis['fingerprint']);
        return isset($plans[$form_ref]) && is_array($plans[$form_ref]) ? $plans[$form_ref] : null;
    }

    /** Apply only normalized plan data; the source DOM and every field survive. */
    private function apply_ai_plan_to_analysis($analysis, $plan, $model)
    {
        if (!is_array($analysis) || !is_array($plan) || empty($analysis['fields'])) {
            return $analysis;
        }

        $source_fields = [];
        foreach ((array) $analysis['fields'] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $field_ref = sanitize_text_field($field['source_signature'] ?? '');
            if ($field_ref !== '') {
                $source_fields[$field_ref] = $field;
            }
        }
        if (empty($source_fields)) {
            return $analysis;
        }

        $decisions = is_array($plan['fields'] ?? null) ? $plan['fields'] : [];
        $ordered = [];
        $steps = [];
        foreach ((array) ($plan['steps'] ?? []) as $step_index => $step) {
            if (!is_array($step)) {
                continue;
            }
            $label = sanitize_text_field($step['title'] ?? ('Step ' . ($step_index + 1)));
            $step_refs = [];
            foreach ((array) ($step['field_refs'] ?? []) as $field_ref) {
                $field_ref = sanitize_text_field($field_ref);
                if (!isset($source_fields[$field_ref]) || isset($ordered[$field_ref])) {
                    continue;
                }
                $field = $source_fields[$field_ref];
                $decision = $decisions[$field_ref] ?? [];
                $field['ai_semantic_role'] = sanitize_key($decision['semantic_role'] ?? 'other');
                $field['ai_recommended_type'] = sanitize_key($decision['recommended_type'] ?? ($field['type'] ?? 'text'));
                $field['ai_required_recommendation'] = !empty($decision['ai_required']);
                if (($plan['flow'] ?? '') === 'multi_step') {
                    $field['step_index'] = count($steps);
                    $field['step_label'] = $label;
                }
                $ordered[$field_ref] = $field;
                $step_refs[] = $field_ref;
            }
            if (!empty($step_refs)) {
                $steps[] = ['label' => $label, 'field_refs' => $step_refs];
            }
        }
        foreach ($source_fields as $field_ref => $field) {
            if (isset($ordered[$field_ref])) {
                continue;
            }
            $decision = $decisions[$field_ref] ?? [];
            $field['ai_semantic_role'] = sanitize_key($decision['semantic_role'] ?? 'other');
            $field['ai_recommended_type'] = sanitize_key($decision['recommended_type'] ?? ($field['type'] ?? 'text'));
            $field['ai_required_recommendation'] = !empty($decision['ai_required']);
            if (($plan['flow'] ?? '') === 'multi_step' && !empty($steps)) {
                $last = count($steps) - 1;
                $field['step_index'] = $last;
                $field['step_label'] = $steps[$last]['label'];
                $steps[$last]['field_refs'][] = $field_ref;
            }
            $ordered[$field_ref] = $field;
        }

        $is_multi_step = ($plan['flow'] ?? '') === 'multi_step' && count($steps) > 1;
        if (!$is_multi_step) {
            $steps = [['label' => sanitize_text_field($steps[0]['label'] ?? 'Form'), 'field_refs' => array_keys($ordered)]];
        }
        $analysis['fields'] = array_values($ordered);
        $analysis['steps'] = array_map(static function ($step) {
            return ['label' => sanitize_text_field($step['label'] ?? '')];
        }, $steps);
        $analysis['form_kind'] = sanitize_key($plan['form_kind'] ?? ($analysis['form_kind'] ?? 'general'));
        $message = sanitize_text_field($plan['completion']['message'] ?? __('Thank you! Your submission has been received.', 'tahmir-ai-to-wp-converter'));
        $analysis['confirmations'] = ['message' => $message];
        $analysis['settings'] = array_merge((array) ($analysis['settings'] ?? []), [
            'source_kind' => $analysis['form_kind'],
            'submit_text' => sanitize_text_field($plan['submit_label'] ?? ($analysis['settings']['submit_text'] ?? 'Submit')),
            'success_message' => $message,
            'is_multi_step' => $is_multi_step,
            'step_count' => $is_multi_step ? count($steps) : 1,
            'steps' => $steps,
            'ai_flow_planned' => true,
            'ai_form_model' => sanitize_text_field($model),
            'ai_plan_confidence' => max(0, min(1, floatval($plan['confidence'] ?? 0))),
            'ai_plan_reason' => sanitize_text_field($plan['reason'] ?? ''),
        ]);
        $analysis['ai_plan'] = $plan;
        return $analysis;
    }

    /**
     * Mark captured controls with their AI step without changing layout. The
     * browser runtime uses these only if the original wizard containers are
     * unavailable after a JavaScript-built form is materialized.
     */
    private function mark_ai_step_controls($content, $fields)
    {
        $names = [];
        $ids = [];
        foreach ((array) $fields as $field) {
            if (!is_array($field) || !isset($field['step_index'])) {
                continue;
            }
            $step = max(0, intval($field['step_index']));
            $label = sanitize_text_field($field['step_label'] ?? ('Step ' . ($step + 1)));
            if (!empty($field['source_name'])) {
                $names[rtrim((string) $field['source_name'], '[]')] = [$step, $label];
            }
            if (!empty($field['source_id'])) {
                $ids[(string) $field['source_id']] = [$step, $label];
            }
        }
        if (empty($names) && empty($ids)) {
            return $content;
        }

        return preg_replace_callback('/<(?:input|select|textarea)\b(?:[^\'">]|"[^"]*"|\'[^\']*\')*>/i', function ($match) use ($names, $ids) {
            $tag = $match[0];
            $name = '';
            $id = '';
            if (preg_match('/\bname\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $name_match)) {
                $name = rtrim((string) ($name_match[1] ?: ($name_match[2] ?: ($name_match[3] ?? ''))), '[]');
            }
            if (preg_match('/\bid\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $id_match)) {
                $id = (string) ($id_match[1] ?: ($id_match[2] ?: ($id_match[3] ?? '')));
            }
            $step_data = ($id !== '' && isset($ids[$id])) ? $ids[$id] : (($name !== '' && isset($names[$name])) ? $names[$name] : null);
            if (!$step_data) {
                return $tag;
            }
            $tag = preg_replace('/\s+data-ugentic-ai-step\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $tag);
            $tag = preg_replace('/\s+data-ugentic-ai-step-label\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $tag);
            $self_closing = (bool) preg_match('/\/\s*>$/', $tag);
            $tag = preg_replace('/\s*\/?\s*>$/', '', $tag);
            return $tag . ' data-ugentic-ai-step="' . intval($step_data[0]) . '" data-ugentic-ai-step-label="' . esc_attr($step_data[1]) . '"' . ($self_closing ? ' />' : '>');
        }, (string) $content);
    }

    private function set_data_attribute($attrs, $name, $value)
    {
        $attrs = preg_replace('/\s+' . preg_quote($name, '/') . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $attrs);
        return rtrim($attrs) . ' ' . $name . '="' . esc_attr($value) . '"';
    }

    /**
     * Create a new imported form or reuse an existing one with the same field set.
     *
     * @param Ugentic_Database $database
     * @param array $form_data
     * @return array{id:int,created:bool,fingerprint:string}
     */
    private function get_or_create_imported_form($database, $form_data)
    {
        $fields = $form_data['fields'] ?? [];
        $settings = isset($form_data['settings']) && is_array($form_data['settings'])
            ? $form_data['settings']
            : [];
        $fingerprint = $this->get_import_fields_fingerprint($fields, $settings);

        if ($fingerprint) {
            $existing_id = $this->find_imported_form_by_fingerprint($fingerprint);
            if ($existing_id) {
                return [
                    'id' => $existing_id,
                    'created' => false,
                    'fingerprint' => $fingerprint,
                ];
            }
        }

        if ($fingerprint) {
            $settings['import_fields_fingerprint'] = $fingerprint;
            $settings['import_dedupe_strategy'] = 'fields_v1';
        }
        $form_data['settings'] = $settings;

        $form_id = intval($database->insert_form($form_data));
        if ($form_id && $fingerprint) {
            $this->import_form_fingerprint_cache[$fingerprint] = $form_id;
        }

        return [
            'id' => $form_id,
            'created' => $form_id > 0,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Look up an active imported form by the field-only fingerprint.
     */
    private function find_imported_form_by_fingerprint($fingerprint)
    {
        if (empty($fingerprint)) {
            return 0;
        }

        if (isset($this->import_form_fingerprint_cache[$fingerprint])) {
            return intval($this->import_form_fingerprint_cache[$fingerprint]);
        }

        global $wpdb;
        $forms_table = $wpdb->prefix . 'ugentic_forms';
        $like = '%' . $wpdb->esc_like('"import_fields_fingerprint":"' . $fingerprint . '"') . '%';
        $user_id = get_current_user_id();

        $form_id = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$forms_table}
                 WHERE is_active = 1
                   AND user_id = %d
                   AND settings LIKE %s
                 ORDER BY id ASC
                 LIMIT 1",
                $user_id,
                $like
            )
        ));

        if ($form_id) {
            $this->import_form_fingerprint_cache[$fingerprint] = $form_id;
        }

        return $form_id;
    }

    /**
     * Build a field-only fingerprint so repeated footer/contact forms reuse one DB form.
     */
    private function get_import_fields_fingerprint($fields, $settings = [])
    {
        if (!is_array($fields) || empty($fields)) {
            return '';
        }

        if (class_exists('Ugentic_Form_Engine')) {
            $fields = Ugentic_Form_Engine::normalize_fields($fields);
        }

        $signature_fields = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $signature_field = [
                'type' => $this->normalize_signature_value($field['type'] ?? 'text'),
                'name' => $this->normalize_signature_value($field['source_name'] ?? ($field['name'] ?? '')),
                'label' => $this->normalize_signature_value($field['label'] ?? ''),
                'placeholder' => $this->normalize_signature_value($field['placeholder'] ?? ''),
                'required' => !empty($field['required']) ? 1 : 0,
                'multiple' => !empty($field['multiple']) ? 1 : 0,
                'options' => $this->normalize_signature_options($field['options'] ?? []),
            ];

            foreach (['min', 'max', 'step', 'rows', 'min_length', 'max_length', 'pattern', 'accept', 'step_index', 'step_label'] as $key) {
                if (array_key_exists($key, $field)) {
                    $signature_field[$key] = $this->normalize_signature_value($field[$key]);
                }
            }

            $signature_fields[] = $signature_field;
        }

        if (empty($signature_fields)) {
            return '';
        }

        $signature = $this->json_encode($signature_fields);
        if (!empty($settings['ai_flow_planned'])) {
            // Keep AI-managed form schemas separate from an older standard
            // import while still deduplicating the same shared source form.
            $signature .= '|ai_flow_v1|' . sanitize_text_field($settings['form_fingerprint'] ?? '');
        }

        return substr(hash('sha256', $signature), 0, 24);
    }

    /**
     * Normalize select/radio/checkbox options for field fingerprinting.
     */
    private function normalize_signature_options($options)
    {
        if (!is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                $label = $option['label'] ?? ($option['value'] ?? '');
                $value = $option['value'] ?? $label;
            } else {
                $label = $option;
                $value = $option;
            }

            $normalized[] = [
                'value' => $this->normalize_signature_value($value),
                'label' => $this->normalize_signature_value($label),
            ];
        }

        return $normalized;
    }

    /**
     * Normalize scalar-ish field metadata for stable field fingerprinting.
     */
    private function normalize_signature_value($value)
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value) || is_object($value)) {
            $value = $this->json_encode($value);
        }

        $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        $value = wp_strip_all_tags($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return strtolower(trim((string) $value));
    }

    /**
     * Add/update Ugentic form attributes without duplicating existing attributes.
     */
    private function with_ugentic_form_attrs($attrs, $form_id, $mode, $signature)
    {
        $attrs = preg_replace('/\sdata-ugentic-form-id=(["\']).*?\1/is', '', (string) $attrs);
        $attrs = preg_replace('/\sdata-ugentic-form-mode=(["\']).*?\1/is', '', $attrs);
        $attrs = preg_replace('/\sdata-ugentic-form-signature=(["\']).*?\1/is', '', $attrs);

        $attrs .= ' data-ugentic-form-id="' . intval($form_id) . '"';
        $attrs .= ' data-ugentic-form-mode="' . esc_attr($mode) . '"';
        if (!empty($signature)) {
            $attrs .= ' data-ugentic-form-signature="' . esc_attr($signature) . '"';
        }

        return $attrs;
    }

    /**
     * JSON encode with the WordPress helper when available.
     */
    private function json_encode($value)
    {
        return function_exists('wp_json_encode') ? wp_json_encode($value) : json_encode($value);
    }
}
