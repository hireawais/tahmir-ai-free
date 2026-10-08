<?php
/**
 * Canonical form engine for detection, normalization, validation, submissions,
 * notifications, and integration dispatch.
 *
 * @package Ugentic_Builder
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Form_Engine
{
    public const SCHEMA_VERSION = 2;

    private const SOURCE_NATIVE = 'native';
    private const SOURCE_FAITHFUL = 'faithful';
    private const SOURCE_EXTERNAL = 'external';
    private const SOURCE_PLUGIN = 'plugin';

    private static $field_types = [
        'text', 'email', 'textarea', 'tel', 'number', 'url', 'date', 'time',
        'datetime-local', 'select', 'radio', 'checkbox', 'file', 'hidden',
        'rating', 'content', 'divider', 'page_break', 'password', 'slider',
        'signature', 'address', 'likert', 'repeater', 'name',
    ];

    private static $type_aliases = [
        'phone' => 'tel',
        'dropdown' => 'select',
        'html' => 'content',
        'button' => 'content',
    ];

    private static $plugin_markers = [
        'wpcf7', 'wpforms', 'gform', 'gravityform', 'gform_wrapper',
        'ninja-forms', 'nf-form', 'fluentform', 'ff-el-form', 'formidable',
        'frm_forms', 'forminator', 'caldera-grid', 'mc4wp-form',
        'elementor-form', 'bricks-form', 'jet-form-builder',
    ];

    private static $protected_action_markers = [
        'paypal.', 'stripe.', 'checkout.', '/checkout', 'squareup.',
        'authorize.net', '2checkout', 'braintree', 'razorpay', 'paystack',
        'klarna', 'shopify', 'woocommerce', 'cart', 'login', 'wp-login.php',
        'google.com', 'recaptcha',
    ];

    /**
     * Normalize raw form fields/settings into schema v2.
     */
    public static function normalize_form_data($data, $defaults = [])
    {
        $fields = self::decode_array($data['fields'] ?? ($defaults['fields'] ?? []));
        $default_settings = self::decode_array($defaults['settings'] ?? []);
        $incoming_settings = self::decode_array($data['settings'] ?? []);
        $settings = array_merge(
            is_array($default_settings) ? $default_settings : [],
            is_array($incoming_settings) ? $incoming_settings : []
        );
        $notifications = self::decode_array($data['notifications'] ?? ($defaults['notifications'] ?? []));
        $confirmations = self::decode_array($data['confirmations'] ?? ($defaults['confirmations'] ?? []));
        $style_settings = self::decode_array($data['style_settings'] ?? ($defaults['style_settings'] ?? []));

        $fields = self::normalize_fields($fields);

        $settings = array_merge([
            'schema_version' => self::SCHEMA_VERSION,
            'source_mode' => self::SOURCE_NATIVE,
            'preservation_mode' => 'managed',
            'sync_status' => 'ok',
            'submit_text' => 'Submit',
            'success_message' => __('Thank you! Your submission has been received.', 'tahmir-ai-to-wp-converter'),
            'ajax_submit' => true,
            'honeypot' => true,
            'layout' => 'stacked',
        ], is_array($settings) ? $settings : []);

        $settings['schema_version'] = self::SCHEMA_VERSION;
        $settings['source_mode'] = self::sanitize_source_mode($settings['source_mode'] ?? self::SOURCE_NATIVE);
        $settings['preservation_mode'] = self::sanitize_slug($settings['preservation_mode'] ?? 'managed') ?: 'managed';

        if (empty($settings['form_fingerprint'])) {
            $settings['form_fingerprint'] = self::fingerprint_from_fields($fields, $settings);
        }

        return [
            'fields' => $fields,
            'settings' => $settings,
            'notifications' => is_array($notifications) ? $notifications : [],
            'confirmations' => is_array($confirmations) ? $confirmations : [],
            'style_settings' => is_array($style_settings) ? $style_settings : [],
        ];
    }

    /**
     * Normalize fields and repair common schema mismatches.
     */
    public static function normalize_fields($fields)
    {
        if (!is_array($fields)) {
            return [];
        }

        $normalized = [];
        $name_counts = [];
        $source_lookup = [];

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $type = self::canonical_type($field['type'] ?? ($field['field_type'] ?? 'text'));
            $label = self::clean_text($field['label'] ?? ($field['field_label'] ?? self::default_label_for_type($type)));
            $raw_name = $field['name'] ?? ($field['field_name'] ?? '');
            $name = self::sanitize_field_name($raw_name ?: self::slug_from_label($label) ?: ('field_' . ($index + 1)));

            if (isset($name_counts[$name])) {
                $name_counts[$name]++;
                $name .= '_' . $name_counts[$name];
            } else {
                $name_counts[$name] = 1;
            }

            $id = self::sanitize_field_id($field['id'] ?? ('field_' . substr(md5($type . '|' . $name . '|' . $index), 0, 10)));

            $normalized_field = [
                'id' => $id,
                'type' => $type,
                'label' => $label,
                'name' => $name,
                'placeholder' => self::clean_text($field['placeholder'] ?? ($field['field_placeholder'] ?? '')),
                'required' => !empty($field['required']) || !empty($field['field_required']),
                'width' => self::normalize_width($field['width'] ?? ($field['field_width'] ?? 'full')),
                'description' => self::clean_text($field['description'] ?? ''),
                'default' => $field['default'] ?? ($field['field_default'] ?? ''),
                'options' => self::normalize_options($field['options'] ?? ($field['field_options'] ?? [])),
                'source_signature' => $field['source_signature'] ?? '',
                'source_name' => self::clean_text($field['source_name'] ?? ($raw_name ?: $name)),
                'source_id' => self::clean_text($field['source_id'] ?? ''),
            ];

            foreach (['min', 'max', 'step', 'step_index', 'step_label', 'rows', 'min_length', 'max_length', 'pattern', 'accept', 'max_size', 'multiple', 'autocomplete', 'inputmode'] as $optional) {
                if (array_key_exists($optional, $field)) {
                    $normalized_field[$optional] = $field[$optional];
                }
            }

            if (array_key_exists('field_min_length', $field)) {
                $normalized_field['min_length'] = $field['field_min_length'];
            }
            if (array_key_exists('field_max_length', $field)) {
                $normalized_field['max_length'] = $field['field_max_length'];
            }
            if (array_key_exists('field_pattern', $field)) {
                $normalized_field['pattern'] = $field['field_pattern'];
            }
            if (array_key_exists('field_accept', $field)) {
                $normalized_field['accept'] = $field['field_accept'];
            }
            if (array_key_exists('field_max_size', $field)) {
                $normalized_field['max_size'] = $field['field_max_size'];
            }

            if (array_key_exists('ai_semantic_role', $field)) {
                $normalized_field['ai_semantic_role'] = self::sanitize_slug($field['ai_semantic_role']);
            }
            if (array_key_exists('ai_recommended_type', $field)) {
                $normalized_field['ai_recommended_type'] = self::canonical_type($field['ai_recommended_type']);
            }
            if (array_key_exists('ai_required_recommendation', $field)) {
                $normalized_field['ai_required_recommendation'] = !empty($field['ai_required_recommendation']);
            }

            $normalized_field['conditionalLogic'] = self::normalize_condition($field['conditionalLogic'] ?? [], $source_lookup);

            if (empty($normalized_field['source_signature'])) {
                $normalized_field['source_signature'] = self::field_source_signature($normalized_field);
            }

            $normalized[] = $normalized_field;
            $source_lookup[$id] = $id;
            $source_lookup[$name] = $id;
            if (!empty($normalized_field['source_name'])) {
                $source_lookup[$normalized_field['source_name']] = $id;
            }
        }

        // Second pass repairs conditional logic that referenced a field name before
        // the source field was normalized.
        foreach ($normalized as &$field) {
            if (empty($field['conditionalLogic']['enabled'])) {
                continue;
            }
            $candidate = $field['conditionalLogic']['fieldId'] ?? ($field['conditionalLogic']['field'] ?? '');
            if (!empty($candidate) && isset($source_lookup[$candidate])) {
                $field['conditionalLogic']['fieldId'] = $source_lookup[$candidate];
            }
        }
        unset($field);

        return $normalized;
    }

    /**
     * Analyze one HTML form using DOM parsing and preservation-first rules.
     */
    public static function analyze_form_html($form_html, $context = [])
    {
        $dom = self::load_form_dom($form_html);
        if (!$dom) {
            return self::empty_analysis('invalid', ['Could not parse form HTML.']);
        }

        $xpath = new DOMXPath($dom);
        $form = $xpath->query('//form')->item(0);
        if (!$form instanceof DOMElement) {
            return self::empty_analysis('invalid', ['No form element found.']);
        }

        $attrs = self::node_attrs($form);
        $classes = strtolower($attrs['class'] ?? '');
        $id = strtolower($attrs['id'] ?? '');
        $action = trim($attrs['action'] ?? '');
        $method = strtolower($attrs['method'] ?? 'post') ?: 'post';
        $text = strtolower(trim($form->textContent ?? ''));
        $steps = self::annotate_step_nodes($form, $xpath);
        $fields = self::extract_fields_from_dom($form, $xpath);

        $is_search = self::detect_search_form($form, $attrs, $fields, $text);
        $is_plugin = self::detect_plugin_form($classes . ' ' . $id . ' ' . $text, $attrs);
        $protected_action = self::detect_protected_action($action);

        $source_mode = self::SOURCE_NATIVE;
        $form_kind = self::determine_form_kind($fields, $text, $is_search);
        $preservation_mode = 'managed';
        $decision = 'convert';
        $warnings = [];

        if ($is_search) {
            $source_mode = self::SOURCE_EXTERNAL;
            $preservation_mode = 'preserve';
            $decision = 'skip';
            $warnings[] = 'Search form preserved.';
        } elseif ($is_plugin) {
            $source_mode = self::SOURCE_PLUGIN;
            $preservation_mode = 'preserve';
            $decision = 'skip';
            $warnings[] = 'Known plugin form preserved.';
        } elseif ($protected_action) {
            $source_mode = self::SOURCE_EXTERNAL;
            $preservation_mode = 'preserve';
            $decision = 'skip';
            $warnings[] = 'External or payment-like form preserved.';
        } elseif (($context['mode'] ?? '') === self::SOURCE_FAITHFUL) {
            $source_mode = self::SOURCE_FAITHFUL;
            $preservation_mode = 'preserve';
            $decision = 'register';
        }

        $confidence = self::calculate_detection_confidence($form, $attrs, $fields, $form_kind, $decision);
        $fingerprint = self::fingerprint_form_node($form, $fields, $action, $method);

        return [
            'valid' => true,
            'source_mode' => $source_mode,
            'form_kind' => $form_kind,
            'preservation_mode' => $preservation_mode,
            'decision' => $decision,
            'confidence' => $confidence,
            'fingerprint' => $fingerprint,
            'field_count' => count(array_filter($fields, function ($field) {
                return !in_array($field['type'] ?? '', ['hidden', 'content', 'divider', 'page_break'], true);
            })),
            'fields' => self::normalize_fields($fields),
            'settings' => [
                'schema_version' => self::SCHEMA_VERSION,
                'source_mode' => $source_mode,
                'source_kind' => $form_kind,
                'preservation_mode' => $preservation_mode,
                'sync_status' => $decision === 'skip' ? 'preserved' : 'ok',
                'form_fingerprint' => $fingerprint,
                'original_action' => $action,
                'original_method' => $method,
                'submit_text' => self::extract_submit_text_from_dom($form),
                'show_title' => false,
                'layout' => 'stacked',
            ],
            'warnings' => $warnings,
            'attrs' => $attrs,
            'steps' => $steps,
            'html' => $dom->saveHTML($form),
        ];
    }

    /**
     * Match whole DOM subtrees, not the first nested closing DIV. Only explicit
     * step containers (or hidden fieldsets) establish a wizard.
     */
    private static function annotate_step_nodes($form, $xpath)
    {
        $candidates = [];
        $fieldsets = [];
        foreach ($xpath->query('.//div|.//section|.//fieldset', $form) as $node) {
            if (!$xpath->query('.//input|.//select|.//textarea', $node)->length) continue;
            $class = $node->getAttribute('class');
            if ($node->hasAttribute('data-step') || $node->hasAttribute('data-ugentic-step')
                || preg_match('/(?:^|\s)(?:ugentic-form__step|form-step|wizard-step)(?:\s|$)/', $class)) {
                $candidates[] = $node;
            }
            if (strtolower($node->tagName) === 'fieldset') $fieldsets[] = $node;
        }
        if (count($candidates) < 2 && count($fieldsets) > 1) {
            foreach ($fieldsets as $fieldset) {
                if (self::control_is_statically_hidden($fieldset)) { $candidates = $fieldsets; break; }
            }
        }
        $outer = array_values(array_filter($candidates, function ($node) use ($candidates, $form) {
            for ($parent = $node->parentNode; $parent && $parent !== $form; $parent = $parent->parentNode) {
                foreach ($candidates as $candidate) if ($parent->isSameNode($candidate)) return false;
            }
            return true;
        }));
        if (count($outer) < 2) return [];
        $steps = [];
        foreach ($outer as $index => $node) {
            $label = $node->getAttribute('data-step-label');
            $legend = $xpath->query('./legend', $node)->item(0);
            if (!$label && $legend) $label = trim($legend->textContent);
            $label = $label ?: 'Step ' . ($index + 1);
            $node->setAttribute('data-ugentic-step', (string) $index);
            $node->setAttribute('data-step-label', $label);
            $steps[] = ['label' => $label];
        }
        return $steps;
    }

    /**
     * Shared submission handler for native shortcode forms, widget forms, and faithful forms.
     */
    public static function handle_submission($args = [])
    {
        $database = ugentic_builder()->database;
        $post = $args['post'] ?? $_POST;
        $files = $args['files'] ?? $_FILES;
        $form_id = intval($args['form_id'] ?? ($post['form_id'] ?? ($post['ugentic_form_id'] ?? 0)));
        if (!$form_id) { return self::submission_error(__('Form not found.', 'tahmir-ai-to-wp-converter')); }
        $source_mode = self::sanitize_source_mode($args['source_mode'] ?? self::SOURCE_NATIVE);
        $form = $form_id > 0 ? $database->get_form($form_id) : null;

        if (!is_user_logged_in() && self::submission_rate_limited($form_id)) {
            return self::submission_error(__('Too many submissions. Please wait a few minutes and try again.', 'tahmir-ai-to-wp-converter'));
        }

        if ($form_id > 0 && !$form) {
            return self::submission_error(__('Form not found.', 'tahmir-ai-to-wp-converter'));
        }

        if ($source_mode === self::SOURCE_FAITHFUL && !$form) {
            return self::submission_error(__('This imported form is not connected to a backend form.', 'tahmir-ai-to-wp-converter'));
        }

        if ($form) {
            $normalized = self::normalize_form_data([
                'fields' => $form->fields,
                'settings' => $form->settings,
                'notifications' => $form->notifications,
                'confirmations' => $form->confirmations,
                'style_settings' => $form->style_settings,
            ]);
            $form->fields = $normalized['fields'];
            $form->settings = $normalized['settings'];
            $form->notifications = $normalized['notifications'];
            $form->confirmations = $normalized['confirmations'];
            $form->style_settings = $normalized['style_settings'];
        }

        if ($form && empty($form->is_active)) {
            return self::submission_error(__('This form is inactive.', 'tahmir-ai-to-wp-converter'));
        }

        $availability_error = $form ? self::check_form_availability($form) : '';
        if ($availability_error) {
            return self::submission_error($availability_error);
        }

        if ($form && !empty($form->settings['honeypot']) && self::honeypot_triggered($post)) {
            return self::submission_error(__('Submission blocked.', 'tahmir-ai-to-wp-converter'), ['spam' => true]);
        }

        $entry_data = self::collect_entry_data($form, $post, $files, $source_mode);
        if (empty($entry_data)) {
            return self::submission_error(__('No form data received.', 'tahmir-ai-to-wp-converter'));
        }

        $validation = self::validate_entry_data($form, $entry_data, $post, $source_mode);
        if (!$validation['valid']) {
            return self::submission_error($validation['message'], ['field_errors' => $validation['errors']]);
        }

        $entry_payload = [
            'form_id' => $form_id,
            'entry_data' => $entry_data,
            'user_ip' => (!empty($form->settings['store_ip']) || !$form) && isset($_SERVER['REMOTE_ADDR'])
                ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'page_url' => self::clean_url($post['page_url'] ?? (isset($_SERVER['HTTP_REFERER']) ? wp_unslash($_SERVER['HTTP_REFERER']) : '')),
        ];

        $entry_id = $database->insert_form_entry($entry_payload);
        if (!$entry_id) {
            return self::submission_error(__('Failed to save entry.', 'tahmir-ai-to-wp-converter'));
        }

        // Free sites cannot open the entries screen, so deliver each successful
        // imported-form lead to the site's configured administrator address.
        self::notify_site_owner($form, $entry_data);

        $response_data = [
            'message' => self::success_message($form),
            'entry_id' => $entry_id,
        ];

        return [
            'success' => true,
            'data' => $response_data,
        ];
    }

    private static function notify_site_owner($form, $entry_data)
    {
        $recipient = sanitize_email((string) get_option('admin_email', ''));
        if (!$recipient || !is_email($recipient)) {
            return;
        }
        $title = sanitize_text_field((string) ($form->title ?? __('Imported form', 'tahmir-ai-to-wp-converter')));
        $lines = [sprintf(__('A new submission was received from %s.', 'tahmir-ai-to-wp-converter'), $title), ''];
        foreach ((array) $entry_data as $name => $field) {
            $label = is_array($field) ? ($field['label'] ?? $name) : $name;
            $value = is_array($field) ? ($field['value'] ?? '') : $field;
            if (is_array($value)) { $value = implode(', ', array_map('sanitize_text_field', $value)); }
            $lines[] = sanitize_text_field((string) $label) . ': ' . sanitize_textarea_field((string) $value);
        }
        wp_mail($recipient, sprintf(__('New submission: %s', 'tahmir-ai-to-wp-converter'), $title), implode("\n", $lines), ['Content-Type: text/plain; charset=UTF-8']);
    }

    public static function wp_send_submission_response($result)
    {
        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? []);
        }
        wp_send_json_error($result['data'] ?? ['message' => __('An error occurred. Please try again.', 'tahmir-ai-to-wp-converter')]);
    }

    private static function collect_entry_data($form, $post, $files, $source_mode)
    {
        $entry_data = [];
        $matched_post_keys = [];
        $skip_keys = [
            'action', 'nonce', 'ugentic_form_nonce', 'form_id', 'ugentic_form_id',
            'page_url', 'email_to', 'email_subject', 'email_signature',
            '_ugentic_hp', 'ugentic_hp', 'g-recaptcha-response', 'cf-turnstile-response',
            '_ugentic_form_contract',
        ];

        if ($form && !empty($form->fields)) {
            foreach ($form->fields as $field) {
                $type = $field['type'] ?? 'text';
                if (in_array($type, ['content', 'divider', 'page_break'], true)) {
                    continue;
                }
                $name = self::sanitize_field_name($field['name'] ?? ($field['id'] ?? ''));
                if (!$name) {
                    continue;
                }

                $matched_file_key = '';
                $matched_post_key = '';
                $has_file = $type === 'file' && self::file_value_for_field($files, $field, $matched_file_key);
                $post_lookup = self::post_value_for_field($post, $field, $matched_post_key);
                $has_post = $post_lookup['found'];
                if (!$has_file && !$has_post && empty($field['default'])) {
                    continue;
                }

                $value = $has_file
                    ? self::handle_file_upload($field, $files[$matched_file_key])
                    : self::sanitize_entry_value($has_post ? $post_lookup['value'] : ($field['default'] ?? ''));

                $entry_data[$name] = [
                    'label' => sanitize_text_field($field['label'] ?? $name),
                    'value' => $value,
                    'type' => $type,
                    'field_id' => $field['id'] ?? '',
                ];

                if ($matched_post_key !== '') {
                    $matched_post_keys[$matched_post_key] = true;
                }
            }

            // A saved form definition is the submission contract. Never persist
            // attacker-supplied fields or files that are absent from that schema.
            return $entry_data;
        }

        foreach ($post as $key => $value) {
            if (in_array($key, $skip_keys, true) || strpos((string) $key, 'field_') !== 0) {
                continue;
            }
            $safe_key = self::sanitize_field_name($key);
            if ($safe_key === '') {
                continue;
            }
            $entry_data[$safe_key] = [
                'label' => sanitize_text_field(ucwords(str_replace(['_', '-'], ' ', $safe_key))),
                'value' => self::sanitize_entry_value($value),
                'type' => 'text',
            ];
        }

        foreach ($files as $key => $file) {
            $safe_key = self::sanitize_field_name($key);
            if ($safe_key === '' || isset($entry_data[$safe_key]) || strpos((string) $key, 'field_') !== 0 || empty($file['name'])) {
                continue;
            }
            $entry_data[$safe_key] = [
                'label' => sanitize_text_field(ucwords(str_replace(['_', '-'], ' ', $safe_key))),
                'value' => self::handle_file_upload(['type' => 'file', 'name' => $safe_key, 'label' => $safe_key], $file),
                'type' => 'file',
            ];
        }

        return $entry_data;
    }

    /**
     * Bound unauthenticated write and email work per client/form.
     */
    private static function submission_rate_limited($form_id)
    {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $identity = hash_hmac('sha256', $ip . '|' . $agent, wp_salt('nonce'));
        $key = 'ugentic_form_rl_' . absint($form_id) . '_' . substr($identity, 0, 20);
        $count = (int) get_transient($key);
        $limit = max(3, (int) apply_filters('ugentic_form_submission_rate_limit', 12, $form_id));

        if ($count >= $limit) {
            return true;
        }

        set_transient($key, $count + 1, 5 * MINUTE_IN_SECONDS);
        return false;
    }

    private static function post_value_for_field($post, $field, &$matched_key)
    {
        foreach (self::field_post_candidates($field) as $candidate) {
            $lookup = self::post_value_from_candidate($post, $candidate);
            if ($lookup['found']) {
                $matched_key = $lookup['key'];
                return $lookup;
            }
        }

        $matched_key = '';
        return ['found' => false, 'value' => null, 'key' => ''];
    }

    private static function file_value_for_field($files, $field, &$matched_key)
    {
        foreach (self::field_post_candidates($field) as $candidate) {
            if (isset($files[$candidate]) && !empty($files[$candidate]['name'])) {
                $matched_key = $candidate;
                return true;
            }
            if (substr($candidate, -2) === '[]') {
                $base = substr($candidate, 0, -2);
                if (isset($files[$base]) && !empty($files[$base]['name'])) {
                    $matched_key = $base;
                    return true;
                }
            }
        }

        $matched_key = '';
        return false;
    }

    private static function field_post_candidates($field)
    {
        $candidates = [];
        foreach (['name', 'source_name', 'source_id', 'id'] as $key) {
            self::add_field_post_candidate($candidates, $field[$key] ?? '');
        }

        return array_keys($candidates);
    }

    private static function add_field_post_candidate(&$candidates, $value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        foreach ([$value, str_replace(['.', ' '], '_', $value), self::sanitize_field_name($value)] as $candidate) {
            if ($candidate !== '') {
                $candidates[$candidate] = true;
            }
            if (substr($candidate, -2) === '[]') {
                $base = substr($candidate, 0, -2);
                if ($base !== '') {
                    $candidates[$base] = true;
                }
            }
        }
    }

    private static function post_value_from_candidate($post, $candidate)
    {
        if (array_key_exists($candidate, $post)) {
            return ['found' => true, 'value' => $post[$candidate], 'key' => $candidate];
        }

        $php_key = str_replace(['.', ' '], '_', $candidate);
        if ($php_key !== $candidate && array_key_exists($php_key, $post)) {
            return ['found' => true, 'value' => $post[$php_key], 'key' => $php_key];
        }

        if (substr($candidate, -2) === '[]') {
            $base = substr($candidate, 0, -2);
            if ($base !== '' && array_key_exists($base, $post)) {
                return ['found' => true, 'value' => $post[$base], 'key' => $base];
            }
        }

        return self::nested_post_value_from_candidate($post, $candidate);
    }

    private static function nested_post_value_from_candidate($post, $candidate)
    {
        if (!preg_match('/^([^\[]+)((?:\[[^\]]*\])+)\z/', (string) $candidate, $match)) {
            return ['found' => false, 'value' => null, 'key' => ''];
        }

        $root_candidates = array_values(array_unique(array_filter([
            $match[1],
            str_replace(['.', ' '], '_', $match[1]),
            self::sanitize_field_name($match[1]),
        ], 'strlen')));

        preg_match_all('/\[([^\]]*)\]/', $match[2], $parts);
        $path = $parts[1] ?? [];

        foreach ($root_candidates as $root) {
            if (!array_key_exists($root, $post)) {
                continue;
            }

            $value = $post[$root];
            foreach ($path as $part) {
                if ($part === '') {
                    return ['found' => true, 'value' => $value, 'key' => $root];
                }
                if (!is_array($value) || !array_key_exists($part, $value)) {
                    continue 2;
                }
                $value = $value[$part];
            }

            return ['found' => true, 'value' => $value, 'key' => $root];
        }

        return ['found' => false, 'value' => null, 'key' => ''];
    }

    private static function validate_entry_data($form, $entry_data, $post, $source_mode = self::SOURCE_NATIVE)
    {
        $errors = [];
        if (!$form || empty($form->fields)) {
            return ['valid' => true, 'errors' => [], 'message' => ''];
        }

        $submission_contract = self::submission_contract($post, $source_mode);

        foreach ($form->fields as $field) {
            $type = $field['type'] ?? 'text';
            if (in_array($type, ['content', 'divider', 'page_break', 'hidden'], true)) {
                continue;
            }

            $name = $field['name'] ?? '';
            $label = $field['label'] ?? $name;
            $value = $entry_data[$name]['value'] ?? '';
            if (is_array($value) && isset($value['__ugentic_upload_error'])) {
                $errors[$name] = $value['__ugentic_upload_error'];
                continue;
            }
            $plain_value = ($type === 'file' && is_array($value))
                ? self::json_encode($value)
                : (is_array($value) ? implode(', ', array_filter(array_map('strval', $value))) : (string) $value);

            if (
                !empty($field['required'])
                && trim($plain_value) === ''
                && self::field_is_required_for_submission($field, $submission_contract)
            ) {
                $error_key = self::field_error_key($field, $submission_contract);
                // translators: %s: form field label.
                $errors[$error_key ?: $name] = sprintf(__('%s is required.', 'tahmir-ai-to-wp-converter'), $label);
                continue;
            }

            if (trim($plain_value) === '') {
                continue;
            }

            if ($type === 'email' && !self::is_valid_email($plain_value)) {
                // translators: %s: form field label.
                $errors[$name] = sprintf(__('Please enter a valid email address for %s.', 'tahmir-ai-to-wp-converter'), $label);
            } elseif ($type === 'url' && !filter_var($plain_value, FILTER_VALIDATE_URL)) {
                // translators: %s: form field label.
                $errors[$name] = sprintf(__('Please enter a valid URL for %s.', 'tahmir-ai-to-wp-converter'), $label);
            } elseif ($type === 'tel' && !preg_match('/^[\d\s()+.\-]{7,}$/', $plain_value)) {
                // translators: %s: form field label.
                $errors[$name] = sprintf(__('Please enter a valid phone number for %s.', 'tahmir-ai-to-wp-converter'), $label);
            } elseif ($type === 'number') {
                if (!is_numeric($plain_value)) {
                    // translators: %s: form field label.
                    $errors[$name] = sprintf(__('%s must be a number.', 'tahmir-ai-to-wp-converter'), $label);
                } elseif (isset($field['min']) && $field['min'] !== '' && $plain_value < $field['min']) {
                    // translators: %s: form field label.
                    $errors[$name] = sprintf(__('%s is below the minimum value.', 'tahmir-ai-to-wp-converter'), $label);
                } elseif (isset($field['max']) && $field['max'] !== '' && $plain_value > $field['max']) {
                    // translators: %s: form field label.
                    $errors[$name] = sprintf(__('%s is above the maximum value.', 'tahmir-ai-to-wp-converter'), $label);
                }
            }

            if (!empty($field['min_length']) && strlen($plain_value) < (int) $field['min_length']) {
                // translators: %s: form field label.
                $errors[$name] = sprintf(__('%s is too short.', 'tahmir-ai-to-wp-converter'), $label);
            }
            if (!empty($field['max_length']) && strlen($plain_value) > (int) $field['max_length']) {
                // translators: %s: form field label.
                $errors[$name] = sprintf(__('%s is too long.', 'tahmir-ai-to-wp-converter'), $label);
            }
            if (!empty($field['pattern']) && !self::matches_pattern($plain_value, $field['pattern'])) {
                // translators: %s: form field label.
                $errors[$name] = sprintf(__('%s does not match the required format.', 'tahmir-ai-to-wp-converter'), $label);
            }
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors,
                'message' => reset($errors),
            ];
        }

        return ['valid' => true, 'errors' => [], 'message' => ''];
    }

    /**
     * Parse the faithful runtime's bounded successful-control contract.
     */
    private static function submission_contract($post, $source_mode)
    {
        $empty = [
            'valid' => false,
            'present' => [],
            'active' => [],
        ];
        if ($source_mode !== self::SOURCE_FAITHFUL || empty($post['_ugentic_form_contract'])) {
            return $empty;
        }

        $raw = wp_unslash((string) $post['_ugentic_form_contract']);
        if (strlen($raw) > 20000) {
            return $empty;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || intval($decoded['version'] ?? 0) !== 1) {
            return $empty;
        }

        $contract = $empty;
        $contract['valid'] = true;
        foreach (['present', 'active'] as $key) {
            $values = is_array($decoded[$key] ?? null) ? array_slice($decoded[$key], 0, 250) : [];
            foreach ($values as $value) {
                $value = trim(sanitize_text_field((string) $value));
                if ($value === '' || strlen($value) > 190) {
                    continue;
                }
                $contract[$key][$value] = true;
                $contract[$key][str_replace(['.', ' '], '_', $value)] = true;
                if (substr($value, -2) === '[]') {
                    $contract[$key][substr($value, 0, -2)] = true;
                }
            }
        }

        return $contract;
    }

    private static function field_is_required_for_submission($field, $contract)
    {
        // Wizard steps are all part of one submission. A client contract must
        // not make an earlier step optional simply by omitting it as "hidden".
        if (isset($field['step_index']) && empty($field['conditionalLogic']['enabled'])) {
            return true;
        }
        if (empty($contract['valid'])) {
            return true;
        }

        $present = false;
        $active = false;
        foreach (self::field_post_candidates($field) as $candidate) {
            $plain = substr($candidate, -2) === '[]' ? substr($candidate, 0, -2) : $candidate;
            if (isset($contract['present'][$candidate]) || isset($contract['present'][$plain])) {
                $present = true;
            }
            if (isset($contract['active'][$candidate]) || isset($contract['active'][$plain])) {
                $active = true;
            }
        }

        return $present && $active;
    }

    private static function field_error_key($field, $contract)
    {
        foreach (self::field_post_candidates($field) as $candidate) {
            $plain = substr($candidate, -2) === '[]' ? substr($candidate, 0, -2) : $candidate;
            if (
                isset($contract['active'][$candidate])
                || isset($contract['active'][$plain])
                || isset($contract['present'][$candidate])
                || isset($contract['present'][$plain])
            ) {
                return $plain;
            }
        }

        return self::sanitize_field_name($field['name'] ?? '');
    }

    private static function check_form_availability($form)
    {
        $settings = $form->settings ?? [];
        $now = function_exists('current_time') ? strtotime(current_time('mysql')) : time();
        $closed = $settings['closed_message'] ?? __('This form is no longer accepting submissions.', 'tahmir-ai-to-wp-converter');

        if (!empty($settings['schedule_start']) && strtotime($settings['schedule_start']) > $now) {
            return $closed;
        }
        if (!empty($settings['schedule_end']) && strtotime($settings['schedule_end']) < $now) {
            return $closed;
        }
        if (!empty($settings['entry_limit']) && intval($settings['entry_limit']) > 0) {
            $count = ugentic_builder()->database->get_form_entries_count(intval($form->id));
            if ($count >= intval($settings['entry_limit'])) {
                return $closed;
            }
        }

        return '';
    }

    private static function handle_file_upload($field, $file)
    {
        if (empty($file['name'])) {
            return '';
        }

        $files = self::normalize_file_array($file);
        $uploaded = [];
        $errors = [];
        foreach ($files as $single_file) {
            if (!empty($single_file['error'])) {
                $errors[] = __('File upload failed.', 'tahmir-ai-to-wp-converter');
                continue;
            }

            $max_size_mb = intval($field['max_size'] ?? 5);
            if ($max_size_mb > 0 && !empty($single_file['size']) && $single_file['size'] > $max_size_mb * 1024 * 1024) {
                // translators: %d: maximum upload size in megabytes.
                $errors[] = sprintf(__('File exceeds the %d MB size limit.', 'tahmir-ai-to-wp-converter'), $max_size_mb);
                continue;
            }

            $extension = strtolower(pathinfo(sanitize_file_name($single_file['name'] ?? ''), PATHINFO_EXTENSION));
            $blocked_extensions = ['php', 'phtml', 'phar', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh', 'exe', 'dll', 'so', 'dylib'];
            if ($extension === '' || in_array($extension, $blocked_extensions, true)) {
                $errors[] = __('File type is not allowed.', 'tahmir-ai-to-wp-converter');
                continue;
            }

            $checked = wp_check_filetype_and_ext($single_file['tmp_name'], $single_file['name']);
            if (empty($checked['ext']) || empty($checked['type'])) {
                $errors[] = __('File type could not be verified.', 'tahmir-ai-to-wp-converter');
                continue;
            }

            $single_file['type'] = $checked['type'];
            if (!self::file_accepts($single_file, $field['accept'] ?? '')) {
                $errors[] = __('File type is not allowed.', 'tahmir-ai-to-wp-converter');
                continue;
            }

            if (!function_exists('wp_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $result = wp_handle_upload($single_file, ['test_form' => false]);
            if (!empty($result['url'])) {
                $uploaded[] = [
                    'url' => esc_url_raw($result['url']),
                    'name' => sanitize_file_name($single_file['name']),
                    'type' => $result['type'] ?? '',
                ];
            }
        }

        if (!empty($errors)) {
            return ['__ugentic_upload_error' => reset($errors)];
        }

        if (count($uploaded) === 1) {
            return $uploaded[0]['url'];
        }

        return $uploaded;
    }

    /**
     * Replace recognisable script-only calendar buttons after source scripts
     * have been removed. Native date inputs remain editable and submit values.
     * Never guess at payment/search/plugin controls or arbitrary dialog buttons.
     */

    public static function materialize_static_controls($html)
    {
        return preg_replace_callback('/<form\b[^>]*>[\s\S]*?<\/form>/i', function ($match) {
            $analysis = self::analyze_form_html($match[0], ['mode' => 'faithful']);
            if (($analysis['decision'] ?? '') === 'skip') return $match[0];
            $dom = self::load_form_dom($match[0]);
            if (!$dom) return $match[0];
            $xpath = new DOMXPath($dom);
            $form = $xpath->query('//form')->item(0);
            $changed = false;
            $buttons = iterator_to_array($xpath->query('.//button[@aria-haspopup="dialog"]', $form));
            foreach ($buttons as $button) {
                $text = trim($button->textContent);
                if (!preg_match('/\b(?:(?:pick|select|choose)\s+(?:a\s+)?date(?:\s+range)?|event\s+dates?)\b/i', $text)) continue;
                $range = (bool) preg_match('/\brange\b|\bdates\b/i', $text);
                $parent = $button->parentNode;
                // Leave existing native/custom backing inputs alone.
                if ($xpath->query('.//input[@type="date"]', $parent)->length
                    || ($parent !== $form && $xpath->query('.//input', $parent)->length)) continue;
                $wrap = $dom->createElement('span');
                $wrap->setAttribute('data-ugentic-native-date', $range ? 'range' : 'single');
                $wrap->setAttribute('style', 'display:grid;gap:8px;grid-template-columns:' . ($range ? 'repeat(2,minmax(0,1fr))' : 'minmax(0,1fr)'));
                foreach ($range ? ['Start date', 'End date'] : ['Date'] as $label_text) {
                    $label = $dom->createElement('label');
                    $label->appendChild($dom->createTextNode($label_text));
                    $input = $dom->createElement('input');
                    $input->setAttribute('type', 'date');
                    $base_name = $range ? ($label_text === 'Start date' ? 'date_start' : 'date_end') : 'date';
                    $name = $base_name;
                    $suffix = 2;
                    while ($xpath->query('.//*[@name="' . $name . '"]', $form)->length) $name = $base_name . '_' . $suffix++;
                    $input->setAttribute('name', $name);
                    $input->setAttribute('aria-label', $label_text);
                    $input->setAttribute('class', $button->getAttribute('class'));
                    $input->setAttribute('style', 'width:100%;min-width:0;');
                    if ($button->getAttribute('aria-required') === 'true') $input->setAttribute('required', '');
                    $label->appendChild($input);
                    $wrap->appendChild($label);
                }
                $parent->replaceChild($wrap, $button);
                $changed = true;
            }
            return $changed ? $dom->saveHTML($form) : $match[0];
        }, $html);
    }

    private static function extract_fields_from_dom($form, $xpath)
    {
        $fields = [];
        $groups = [];
        $index = 0;
        $nodes = $xpath->query('.//input|.//textarea|.//select', $form);

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            $attrs = self::node_attrs($node);
            $raw_type = $tag === 'input' ? strtolower($attrs['type'] ?? 'text') : $tag;
            if (
                in_array($raw_type, ['submit', 'button', 'image', 'reset'], true)
                || self::control_is_disabled($node)
            ) {
                continue;
            }

            $type = self::canonical_type($raw_type);
            $source_name = $attrs['name'] ?? '';
            $source_id = $attrs['id'] ?? '';
            $name = self::sanitize_field_name($source_name ?: $source_id ?: ('field_' . ($index + 1)));

            if ($name) {
                $group_key = $type . ':' . $name;
                $option = self::input_option_from_node($node, $form, $xpath);
                if (isset($groups[$group_key])) {
                    if (in_array($type, ['radio', 'checkbox'], true)) {
                        $fields[$groups[$group_key]]['options'][] = $option;
                    }
                    if (
                        (array_key_exists('required', $attrs) || strtolower($attrs['aria-required'] ?? '') === 'true')
                        && !self::control_is_statically_hidden($node)
                    ) {
                        $fields[$groups[$group_key]]['required'] = true;
                    }
                    continue;
                }
                $groups[$group_key] = count($fields);
            }

            $label = self::label_for_node($node, $form, $xpath);
            if (!$label) {
                $label = self::clean_text($attrs['placeholder'] ?? '');
            }
            if (!$label) {
                $label = self::generate_label($name, $type);
            }

            $field = [
                'id' => 'field_' . substr(md5(($source_id ?: $name) . '|' . $type . '|' . $index), 0, 10),
                'type' => $type,
                'name' => $name,
                'label' => $label,
                'placeholder' => self::clean_text($attrs['placeholder'] ?? ''),
                'required' => (
                    array_key_exists('required', $attrs)
                    || strtolower($attrs['aria-required'] ?? '') === 'true'
                ) && !self::control_is_statically_hidden($node),
                'width' => 'full',
                'default' => $tag === 'textarea' ? self::clean_text($node->textContent) : self::clean_text($attrs['value'] ?? ''),
                'source_name' => $source_name,
                'source_id' => $source_id,
            ];
            for ($parent = $node->parentNode; $parent instanceof DOMElement && $parent !== $form; $parent = $parent->parentNode) {
                if ($parent->hasAttribute('data-ugentic-step')) {
                    $field['step_index'] = (int) $parent->getAttribute('data-ugentic-step');
                    $field['step_label'] = $parent->getAttribute('data-step-label');
                    break;
                }
            }

            if ($type === 'select') {
                $field['options'] = self::select_options($node);
                if (array_key_exists('multiple', $attrs)) {
                    $field['multiple'] = true;
                }
            } elseif (in_array($type, ['radio', 'checkbox'], true)) {
                $field['options'] = [self::input_option_from_node($node, $form, $xpath)];
            }

            foreach (['min', 'max', 'step', 'pattern', 'accept', 'maxlength', 'minlength'] as $attr_name) {
                if (array_key_exists($attr_name, $attrs)) {
                    $key = ['maxlength' => 'max_length', 'minlength' => 'min_length'][$attr_name] ?? $attr_name;
                    $field[$key] = $attrs[$attr_name];
                }
            }

            $field['source_signature'] = self::field_source_signature($field);
            $fields[] = $field;
            $index++;
        }

        return $fields;
    }

    private static function control_is_disabled($node)
    {
        if ($node->hasAttribute('disabled')) {
            return true;
        }

        $parent = $node->parentNode;
        while ($parent instanceof DOMElement && strtolower($parent->tagName) !== 'form') {
            if (strtolower($parent->tagName) === 'fieldset' && $parent->hasAttribute('disabled')) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private static function control_is_statically_hidden($node)
    {
        if (strtolower($node->getAttribute('type')) === 'hidden') {
            return true;
        }

        $parent = $node;
        while ($parent instanceof DOMElement && strtolower($parent->tagName) !== 'form') {
            // A hidden wizard step is pending, not an inactive conditional field.
            if ($parent->hasAttribute('data-ugentic-step')) {
                $parent = $parent->parentNode;
                continue;
            }
            $attrs = self::node_attrs($parent);
            $class = strtolower((string) ($attrs['class'] ?? ''));
            $style = strtolower((string) ($attrs['style'] ?? ''));
            if (
                array_key_exists('hidden', $attrs)
                || strtolower((string) ($attrs['aria-hidden'] ?? '')) === 'true'
                || array_key_exists('inert', $attrs)
                || preg_match('/(?:^|;)\s*display\s*:\s*none\b/', $style)
                || preg_match('/(?:^|;)\s*visibility\s*:\s*(?:hidden|collapse)\b/', $style)
                || preg_match('/(?:^|\s)(?:d-none|is-hidden|hidden|visually-hidden|sr-only)(?:\s|$)/', $class)
            ) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private static function load_form_dom($html)
    {
        if (!class_exists('DOMDocument')) {
            return null;
        }

        $html = trim((string) $html);
        if (stripos($html, '<form') === false) {
            $html = '<form>' . $html . '</form>';
        }

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div id="ugentic-form-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $loaded ? $dom : null;
    }

    private static function label_for_node($node, $form, $xpath)
    {
        $id = $node->getAttribute('id');
        if ($id) {
            $labels = $xpath->query('.//label[@for=' . self::xpath_literal($id) . ']', $form);
            if ($labels && $labels->length) {
                return self::clean_text($labels->item(0)->textContent);
            }
        }

        $parent = $node->parentNode;
        while ($parent instanceof DOMElement && strtolower($parent->tagName) !== 'form') {
            if (strtolower($parent->tagName) === 'label') {
                return self::clean_text($parent->textContent);
            }
            if (in_array(strtolower($parent->tagName), ['fieldset', 'div', 'p', 'li'], true)) {
                $label = $xpath->query('.//label', $parent)->item(0);
                if ($label instanceof DOMElement) {
                    return self::clean_text($label->textContent);
                }
                if (strtolower($parent->tagName) === 'fieldset') {
                    $legend = $xpath->query('.//legend', $parent)->item(0);
                    if ($legend instanceof DOMElement) {
                        return self::clean_text($legend->textContent);
                    }
                }
            }
            $parent = $parent->parentNode;
        }

        return '';
    }

    private static function select_options($select)
    {
        $options = [];
        foreach ($select->getElementsByTagName('option') as $option) {
            $label = self::clean_text($option->textContent);
            if ($label === '') {
                continue;
            }
            $value = $option->hasAttribute('value') ? $option->getAttribute('value') : $label;
            $options[] = [
                'value' => self::clean_text($value),
                'label' => $label,
            ];
        }
        return $options;
    }

    private static function input_option_from_node($node, $form, $xpath)
    {
        $attrs = self::node_attrs($node);
        $value = self::clean_text($attrs['value'] ?? 'on');
        $label = self::label_for_node($node, $form, $xpath);
        if (!$label || strtolower($label) === strtolower($value)) {
            $label = $value === 'on' ? __('Option', 'tahmir-ai-to-wp-converter') : $value;
        }
        return [
            'value' => $value,
            'label' => $label,
        ];
    }

    private static function detect_search_form($form, $attrs, $fields, $text)
    {
        $class_id_action = strtolower(($attrs['class'] ?? '') . ' ' . ($attrs['id'] ?? '') . ' ' . ($attrs['action'] ?? '') . ' ' . ($attrs['role'] ?? ''));
        if (strpos($class_id_action, 'search') !== false) {
            return true;
        }
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'search') {
                return true;
            }
            $name_label = strtolower(($field['name'] ?? '') . ' ' . ($field['label'] ?? ''));
            if (preg_match('/\b(search|query|keyword|keywords|s)\b/', $name_label) && count($fields) <= 2) {
                return true;
            }
        }
        return count($fields) <= 2 && preg_match('/\b(search|find)\b/', $text);
    }

    private static function detect_plugin_form($haystack, $attrs)
    {
        $haystack = strtolower($haystack . ' ' . implode(' ', array_keys($attrs)) . ' ' . implode(' ', $attrs));
        foreach (self::$plugin_markers as $marker) {
            if (strpos($haystack, $marker) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function detect_protected_action($action)
    {
        $action = strtolower((string) $action);
        if ($action === '' || $action === '#' || strpos($action, 'mailto:') === 0) {
            return false;
        }
        if (preg_match('/^https?:\/\//i', $action)) {
            $action_host = wp_parse_url($action, PHP_URL_HOST);
            $site_host = function_exists('home_url') ? wp_parse_url(home_url('/'), PHP_URL_HOST) : '';
            if (!$site_host || ($action_host && strtolower($action_host) !== strtolower($site_host))) {
                return true;
            }
        }
        foreach (self::$protected_action_markers as $marker) {
            if (strpos($action, $marker) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function determine_form_kind($fields, $text, $is_search)
    {
        if ($is_search) {
            return 'search';
        }
        $has_email = false;
        $has_message = false;
        $has_phone = false;
        $has_password = false;
        foreach ($fields as $field) {
            $type = $field['type'] ?? '';
            $label = strtolower(($field['name'] ?? '') . ' ' . ($field['label'] ?? ''));
            $has_email = $has_email || $type === 'email';
            $has_message = $has_message || $type === 'textarea' || strpos($label, 'message') !== false;
            $has_phone = $has_phone || $type === 'tel' || strpos($label, 'phone') !== false;
            $has_password = $has_password || $type === 'password';
        }
        if ($has_password) {
            return 'login';
        }
        if ($has_email && !$has_message && count($fields) <= 3 && preg_match('/newsletter|subscribe|updates|signup|sign up/i', $text)) {
            return 'newsletter';
        }
        if ($has_email && $has_phone && $has_message) {
            return 'lead';
        }
        if ($has_email && $has_message) {
            return 'contact';
        }
        return 'general';
    }

    private static function calculate_detection_confidence($form, $attrs, $fields, $form_kind, $decision)
    {
        $score = 0.35;
        if (strtolower($form->tagName) === 'form') {
            $score += 0.25;
        }
        if (count($fields) > 0) {
            $score += 0.20;
        }
        if (count($fields) >= 2) {
            $score += 0.10;
        }
        if (!empty($attrs['method']) || !empty($attrs['action'])) {
            $score += 0.05;
        }
        if ($form_kind !== 'general') {
            $score += 0.05;
        }
        if ($decision === 'skip') {
            $score = max($score, 0.9);
        }
        return min(1.0, round($score, 2));
    }

    private static function extract_submit_text_from_dom($form)
    {
        foreach ($form->getElementsByTagName('button') as $button) {
            $type = strtolower($button->getAttribute('type') ?: 'submit');
            if ($type === 'submit') {
                $text = self::clean_text($button->textContent);
                if ($text) {
                    return $text;
                }
            }
        }
        foreach ($form->getElementsByTagName('input') as $input) {
            if (strtolower($input->getAttribute('type')) === 'submit') {
                $value = self::clean_text($input->getAttribute('value'));
                if ($value) {
                    return $value;
                }
            }
        }
        return 'Submit';
    }

    private static function fingerprint_form_node($form, $fields, $action, $method)
    {
        $parts = [
            'action=' . strtolower((string) $action),
            'method=' . strtolower((string) $method),
            'class=' . strtolower($form->getAttribute('class')),
            'id=' . strtolower($form->getAttribute('id')),
        ];
        foreach ($fields as $field) {
            $parts[] = self::field_source_signature($field);
        }
        return substr(hash('sha256', implode('|', $parts)), 0, 20);
    }

    public static function field_source_signature($field)
    {
        $options = $field['options'] ?? [];
        return substr(hash('sha256', self::json_encode([
            'type' => $field['type'] ?? '',
            'name' => $field['source_name'] ?? ($field['name'] ?? ''),
            'id' => $field['source_id'] ?? ($field['id'] ?? ''),
            'label' => $field['label'] ?? '',
            'options' => $options,
        ])), 0, 16);
    }

    private static function fingerprint_from_fields($fields, $settings)
    {
        $parts = [$settings['source_mode'] ?? self::SOURCE_NATIVE, $settings['preservation_mode'] ?? 'managed'];
        foreach ($fields as $field) {
            $parts[] = $field['source_signature'] ?? self::field_source_signature($field);
        }
        return substr(hash('sha256', implode('|', $parts)), 0, 20);
    }

    private static function normalize_condition($condition, $source_lookup = [])
    {
        if (!is_array($condition)) {
            $condition = [];
        }

        $enabled = !empty($condition['enabled']);
        $field = $condition['fieldId'] ?? ($condition['field'] ?? '');
        if ($field && isset($source_lookup[$field])) {
            $field = $source_lookup[$field];
        }

        $action = $condition['action'] ?? 'show';
        $operator = $condition['operator'] ?? 'equals';

        return [
            'enabled' => $enabled,
            'action' => in_array($action, ['show', 'hide'], true) ? $action : 'show',
            'fieldId' => self::sanitize_field_id($field),
            'operator' => in_array($operator, ['equals', 'not_equals', 'contains', 'not_empty', 'empty'], true) ? $operator : 'equals',
            'value' => self::clean_text($condition['value'] ?? ''),
        ];
    }

    private static function normalize_options($options)
    {
        if (is_string($options)) {
            $options = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $options)));
        }
        if (!is_array($options)) {
            return [];
        }
        $normalized = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                $label = self::clean_text($option['label'] ?? ($option['value'] ?? ''));
                $value = self::clean_text($option['value'] ?? $label);
            } else {
                $label = self::clean_text($option);
                $value = $label;
            }
            if ($label === '') {
                continue;
            }
            $normalized[] = ['value' => $value, 'label' => $label];
        }
        return $normalized;
    }

    private static function normalize_file_array($file)
    {
        if (!is_array($file['name'])) {
            return [$file];
        }
        $files = [];
        foreach ($file['name'] as $index => $name) {
            $files[] = [
                'name' => $name,
                'type' => $file['type'][$index] ?? '',
                'tmp_name' => $file['tmp_name'][$index] ?? '',
                'error' => $file['error'][$index] ?? 0,
                'size' => $file['size'][$index] ?? 0,
            ];
        }
        return $files;
    }

    private static function file_accepts($file, $accept)
    {
        $accept = trim((string) $accept);
        if ($accept === '') {
            return true;
        }
        $allowed = array_filter(array_map('trim', explode(',', strtolower($accept))));
        $name = strtolower($file['name'] ?? '');
        $ext = '.' . strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $type = strtolower($file['type'] ?? '');

        foreach ($allowed as $rule) {
            if ($rule === $ext || $rule === $type) {
                return true;
            }
            if (str_ends_with($rule, '/*') && strpos($type, substr($rule, 0, -1)) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function matches_pattern($value, $pattern)
    {
        $pattern = trim((string) $pattern);
        if ($pattern === '') {
            return true;
        }
        $delimiter = '/';
        $escaped = str_replace($delimiter, '\\' . $delimiter, $pattern);
        return @preg_match($delimiter . $escaped . $delimiter, $value) === 1;
    }

    private static function success_message($form)
    {
        if ($form && !empty($form->confirmations['message'])) {
            return $form->confirmations['message'];
        }
        if ($form && !empty($form->settings['success_message'])) {
            return $form->settings['success_message'];
        }
        return __('Thank you! Your submission has been received.', 'tahmir-ai-to-wp-converter');
    }

    private static function honeypot_triggered($post)
    {
        return !empty($post['_ugentic_hp']) || !empty($post['ugentic_hp']);
    }

    private static function submission_error($message, $extra = [])
    {
        return [
            'success' => false,
            'data' => array_merge(['message' => $message], $extra),
        ];
    }

    private static function empty_analysis($kind, $warnings)
    {
        return [
            'valid' => false,
            'source_mode' => self::SOURCE_EXTERNAL,
            'form_kind' => $kind,
            'preservation_mode' => 'preserve',
            'decision' => 'skip',
            'confidence' => 0,
            'fingerprint' => '',
            'field_count' => 0,
            'fields' => [],
            'settings' => [],
            'warnings' => $warnings,
            'attrs' => [],
        ];
    }

    private static function node_attrs($node)
    {
        $attrs = [];
        if (!$node->hasAttributes()) {
            return $attrs;
        }
        foreach ($node->attributes as $attr) {
            $attrs[$attr->name] = $attr->value;
        }
        return $attrs;
    }

    private static function xpath_literal($value)
    {
        $value = (string) $value;
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        $parts = explode('"', $value);
        return 'concat("' . implode('", \'"\', "', $parts) . '")';
    }

    private static function canonical_type($type)
    {
        $type = strtolower((string) $type);
        $type = self::$type_aliases[$type] ?? $type;
        if ($type === 'search') {
            return 'search';
        }
        return in_array($type, self::$field_types, true) ? $type : 'text';
    }

    private static function sanitize_source_mode($mode)
    {
        return in_array($mode, [self::SOURCE_NATIVE, self::SOURCE_FAITHFUL, self::SOURCE_EXTERNAL, self::SOURCE_PLUGIN], true) ? $mode : self::SOURCE_NATIVE;
    }

    private static function normalize_width($width)
    {
        return in_array($width, ['full', 'half', 'third', 'two_thirds'], true) ? $width : 'full';
    }

    private static function default_label_for_type($type)
    {
        $labels = [
            'email' => 'Email Address',
            'tel' => 'Phone Number',
            'textarea' => 'Message',
            'select' => 'Select Option',
            'radio' => 'Choose One',
            'checkbox' => 'Select Options',
            'file' => 'Upload File',
            'hidden' => 'Hidden Field',
            'password' => 'Password',
        ];
        return $labels[$type] ?? ucwords(str_replace(['_', '-'], ' ', $type ?: 'Field'));
    }

    private static function generate_label($name, $type)
    {
        if ($name) {
            return ucwords(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-', '[', ']'], ' ', $name))));
        }
        return self::default_label_for_type($type);
    }

    private static function slug_from_label($label)
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));
    }

    private static function sanitize_field_name($name)
    {
        $name = strtolower(trim((string) $name));
        if (str_ends_with($name, '[]')) {
            $name = substr($name, 0, -2);
        }
        $name = preg_replace('/[^a-z0-9_\-\[\]]+/', '_', $name);
        $name = trim($name, '_-');
        return $name ?: 'field';
    }

    private static function sanitize_field_id($id)
    {
        return self::sanitize_slug($id) ?: 'field_' . substr(md5((string) $id), 0, 8);
    }

    private static function sanitize_slug($value)
    {
        if (function_exists('sanitize_key')) {
            return sanitize_key($value);
        }
        return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value));
    }

    private static function sanitize_entry_value($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[self::clean_text($key)] = self::sanitize_entry_value($item);
            }
            return $clean;
        }
        return self::clean_text($value);
    }

    private static function clean_text($value)
    {
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field((string) $value);
        }
        return trim(wp_strip_all_tags((string) $value));
    }

    private static function clean_url($value)
    {
        if (function_exists('esc_url_raw')) {
            return esc_url_raw((string) $value);
        }
        return filter_var((string) $value, FILTER_SANITIZE_URL);
    }

    private static function is_valid_email($value)
    {
        if (function_exists('is_email')) {
            return (bool) is_email($value);
        }
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    private static function decode_array($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private static function json_encode($value)
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($value);
        }
        return json_encode($value);
    }
}
