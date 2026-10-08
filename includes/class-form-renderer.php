<?php
/**
 * Form Renderer - Handles form display and AJAX submissions
 * 
 * @package Ugentic_Builder
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Form_Renderer
{

    /**
     * Constructor
     */
    /**
     * Track whether form assets have been enqueued in this request
     */
    private $assets_enqueued = false;

    public function __construct()
    {
        // Shortcode
        add_shortcode('ugentic_form', [$this, 'render_shortcode']);

        // AJAX handlers
        add_action('wp_ajax_ugentic_form_submit', [$this, 'ajax_submit']);
        add_action('wp_ajax_nopriv_ugentic_form_submit', [$this, 'ajax_submit']);
        add_action('wp_ajax_ugentic_faithful_form_submit', [$this, 'ajax_faithful_submit']);
        add_action('wp_ajax_nopriv_ugentic_faithful_form_submit', [$this, 'ajax_faithful_submit']);

        // Register form assets early so they survive aggressive dequeuing
        add_action('wp_enqueue_scripts', [$this, 'register_scripts'], 5);

        // Enqueue form scripts on pages with our shortcode (early pass)
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_scripts'], 8);

        // Late-pass re-enqueue: after aggressive dequeue at priority 999,
        // restore our form assets if they were stripped
        add_action('wp_enqueue_scripts', [$this, 'restore_form_assets'], 99999);
    }

    public static function asset_version($relative_path)
    {
        $path = UGENTIC_PLUGIN_DIR . ltrim($relative_path, '/');
        return file_exists($path) ? filemtime($path) : UGENTIC_VERSION;
    }

    private static function clean_runtime_form_attrs($attrs)
    {
        foreach (['data-ugentic-handled', 'data-ugentic-submitting', 'data-ugentic-inline-ready'] as $attr) {
            $attrs = preg_replace('/\s' . preg_quote($attr, '/') . '(?:\s*=\s*(["\']).*?\1)?/i', '', (string) $attrs);
        }

        return $attrs;
    }

    /**
     * Prepare preserved faithful forms for frontend rendering.
     */
    public static function prepare_faithful_html($html)
    {
        if (strpos($html, 'data-ugentic-form-id') === false) {
            return $html;
        }

        $html = self::sync_faithful_forms($html);

        // Strip baked runtime snippets from older imports, then load the shared,
        // idempotent form runtime. This keeps saved HTML clean and prevents
        // duplicate submit handlers on faithful pages and native blog templates.
        $html = preg_replace('/<script[^>]*id=["\']ugentic-faithful-form-handler["\'][^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<script[^>]*id=["\']ugentic-form-config["\'][^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<script[^>]*id=["\']ugentic-form-js["\'][^>]*>.*?<\/script>/is', '', $html);

        $faithful_css_url = UGENTIC_PLUGIN_URL . 'public/css/form.css';
        $faithful_js_url = UGENTIC_PLUGIN_URL . 'public/js/form.js';
        $faithful_css_ver = self::asset_version('public/css/form.css');
        $faithful_js_ver = self::asset_version('public/js/form.js');
        $faithful_ajax_url = esc_url(admin_url('admin-ajax.php'));
        $faithful_submitting = esc_js(__('Submitting...', 'tahmir-ai-to-wp-converter'));
        $faithful_error = esc_js(__('An error occurred. Please try again.', 'tahmir-ai-to-wp-converter'));

        if (strpos($html, 'ugentic-form-css') === false && strpos($html, 'public/css/form.css') === false) {
            $faithful_form_css = Ugentic_Assets::render_asset('css', 'faithful-form', $faithful_css_url . '?ver=' . $faithful_css_ver);
            if (preg_match('/<\/head>/i', $html)) {
                $html = preg_replace('/<\/head>/i', $faithful_form_css . "\n</head>", $html, 1);
            } elseif (preg_match('/<body[\s>]/i', $html)) {
                $html = preg_replace('/<body([\s>])/i', $faithful_form_css . "\n<body$1", $html, 1);
            } else {
                $html = $faithful_form_css . "\n" . $html;
            }
        }

        $faithful_form_js = Ugentic_Assets::render_asset(
            'inline-js',
            'faithful-form-config',
            'window.ugenticForm=window.ugenticForm||{ajaxUrl:' . wp_json_encode($faithful_ajax_url) . ',strings:{submitting:' . wp_json_encode($faithful_submitting) . ',error:' . wp_json_encode($faithful_error) . '}};'
            . 'window.ugenticAjax=window.ugenticAjax||{url:' . wp_json_encode($faithful_ajax_url) . '};'
        );
        $faithful_form_js .= Ugentic_Assets::render_asset('js', 'faithful-form', $faithful_js_url . '?ver=' . $faithful_js_ver);

        if (preg_match('/<\/body>/i', $html)) {
            return preg_replace('/<\/body>/i', $faithful_form_js . "\n</body>", $html, 1);
        }

        return $html . "\n" . $faithful_form_js;
    }

    /**
     * Render form shortcode
     */
    public function render_shortcode($atts)
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'ugentic_form');

        $form_id = intval($atts['id']);

        if (!$form_id) {
            return '<!-- Ugentic Form: No form ID specified -->';
        }

        // Force-enqueue form assets when the shortcode actually executes.
        // This catches cases where maybe_enqueue_scripts ran too early
        // or the shortcode is in Ugentic DB HTML (not WP post_content).
        if (!$this->assets_enqueued) {
            $this->enqueue_scripts();
        }

        return $this->render_form($form_id);
    }

    /**
     * Render a form by ID
     */
    public function render_form($form_id)
    {
        $database = ugentic_builder()->database;
        $form = $database->get_form($form_id);

        if (!$form) {
            return '<!-- Ugentic Form: Form not found -->';
        }

        if (!$form->is_active) {
            return '<!-- Ugentic Form: Form is inactive -->';
        }

        if (class_exists('Ugentic_Form_Engine')) {
            $normalized = Ugentic_Form_Engine::normalize_form_data([
                'fields' => $form->fields,
                'settings' => $form->settings,
                'notifications' => $form->notifications,
                'confirmations' => $form->confirmations,
                'style_settings' => $form->style_settings,
            ]);
            $fields = $normalized['fields'];
            $settings = $normalized['settings'];
            $styles = $normalized['style_settings'];
        } else {
            $fields = $form->fields;
            $settings = $form->settings;
            $styles = $form->style_settings;
        }

        $form_class = 'ugentic-form';
        $form_class .= ' ugentic-form--' . ($styles['layout'] ?? 'stacked');
        if (!empty($settings['css_class'])) {
            $form_class .= ' ' . sanitize_html_class($settings['css_class']);
        }
        $source_mode = $settings['source_mode'] ?? 'native';
        $fingerprint = $settings['form_fingerprint'] ?? '';

        ob_start();
?>
        <div class="<?php echo esc_attr($form_class); ?>" id="ugentic-form-<?php echo esc_attr($form_id); ?>" data-ugentic-form-shell="true">
            <?php $this->render_form_styles($form_id, $styles); ?>
            
            <form class="ugentic-form__form"
                  data-form-id="<?php echo esc_attr($form_id); ?>"
                  data-ugentic-form-id="<?php echo esc_attr($form_id); ?>"
                  data-ugentic-form-mode="<?php echo esc_attr($source_mode); ?>"
                  data-ugentic-form-signature="<?php echo esc_attr($fingerprint); ?>"
                  data-action="ugentic_form_submit"
                  novalidate>
                <?php wp_nonce_field('ugentic_form_submit_' . $form_id, 'ugentic_form_nonce'); ?>
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
                <input type="hidden" name="page_url" value="<?php echo esc_url(home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? ''))); ?>">
                <?php if (!empty($settings['honeypot'])): ?>
                <div class="ugentic-form__hp" aria-hidden="true">
                    <label><?php esc_html_e('Leave this field empty', 'tahmir-ai-to-wp-converter'); ?></label>
                    <input type="text" name="_ugentic_hp" tabindex="-1" autocomplete="off">
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form->title) && ($settings['show_title'] ?? true)): ?>
                <h3 class="ugentic-form__title"><?php echo esc_html($form->title); ?></h3>
                <?php
        endif; ?>
                
                <?php if (!empty($form->description) && ($settings['show_description'] ?? true)): ?>
                <p class="ugentic-form__description"><?php echo esc_html($form->description); ?></p>
                <?php
        endif; ?>
                
                <div class="ugentic-form__fields">
                    <?php
                    $has_steps = !empty($settings['is_multi_step']);
                    foreach ($fields as $field) {
                        if (($field['type'] ?? '') === 'page_break' || isset($field['step_index'])) $has_steps = true;
                    }
                    $step_index = 0;
                    if ($has_steps) echo '<div class="ugentic-form__step" data-ugentic-step="0">';
                    foreach ($fields as $field) {
                        if (($field['type'] ?? '') === 'page_break') {
                            $step_index++;
                            echo '</div><div class="ugentic-form__step" data-ugentic-step="' . esc_attr($step_index) . '" data-step-label="' . esc_attr($field['label'] ?? '') . '">';
                            continue;
                        }
                        $field_step = (int) ($field['step_index'] ?? $step_index);
                        if ($has_steps && $field_step !== $step_index) {
                            $step_index = $field_step;
                            echo '</div><div class="ugentic-form__step" data-ugentic-step="' . esc_attr($step_index) . '" data-step-label="' . esc_attr($field['step_label'] ?? '') . '">';
                        }
                        $this->render_field($field);
                    }
                    if ($has_steps) echo '</div>';
                    ?>
                </div>
                
                <div class="ugentic-form__submit">
                    <button type="submit" class="ugentic-form__button">
                        <?php echo esc_html($settings['submit_text'] ?? 'Submit'); ?>
                    </button>
                </div>
                
                <div class="ugentic-form__messages" style="display: none;">
                    <div class="ugentic-form__success"></div>
                    <div class="ugentic-form__error"></div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single form field
     */
    private function render_field($field)
    {
        $type = $field['type'] ?? 'text';
        $id = 'field_' . ($field['id'] ?? uniqid());
        $name = $field['name'] ?? $id;
        $label = $field['label'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $required = !empty($field['required']);
        $options = $field['options'] ?? [];
        $default = $field['default'] ?? '';
        $description = $field['description'] ?? '';
        $width = $field['width'] ?? 'full';

        $field_class = 'ugentic-form__field';
        $field_class .= ' ugentic-form__field--' . $type;
        $field_class .= ' ugentic-form__field--' . $width;
        if ($required)
            $field_class .= ' ugentic-form__field--required';

        // ARIA attributes
        $aria_attrs = '';
        if ($description) {
            $aria_attrs .= ' aria-describedby="' . esc_attr($id) . '_desc"';
        }
        if ($required) {
            $aria_attrs .= ' aria-required="true"';
        }
?>
        <div class="<?php echo esc_attr($field_class); ?>" data-field-id="<?php echo esc_attr($field['id'] ?? ''); ?>"<?php if (!empty($field['conditionalLogic']['enabled'])): ?> data-conditions="<?php echo esc_attr(wp_json_encode($field['conditionalLogic'])); ?>"<?php endif; ?>>
            <?php if ($label && $type !== 'hidden'): ?>
            <label class="ugentic-form__label" for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required): ?><span class="required">*</span><?php
            endif; ?>
            </label>
            <?php
        endif; ?>
            
            <?php
        switch ($type) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
            case 'number':
            case 'date':
            case 'time':
            case 'datetime-local':
                $this->render_input_field($type, $id, $name, $placeholder, $required, $default, $field);
                break;

            case 'textarea':
                $this->render_textarea_field($id, $name, $placeholder, $required, $default, $field);
                break;

            case 'select':
                $this->render_select_field($id, $name, $required, $options, $default, $field);
                break;

            case 'radio':
                $this->render_radio_field($id, $name, $required, $options, $default);
                break;

            case 'checkbox':
                $this->render_checkbox_field($id, $name, $required, $options, $default);
                break;

            case 'file':
                $this->render_file_field($id, $name, $required, $field);
                break;

            case 'hidden':
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($default) . '">';
                break;

            case 'rating':
                $this->render_rating_field($id, $name, $required, $field);
                break;

            case 'html':
            case 'content':
                echo '<div class="ugentic-form__content">' . wp_kses_post($field['content'] ?? $field['label'] ?? '') . '</div>';
                break;

            case 'divider':
                echo '<hr class="ugentic-form__divider">';
                break;

            case 'page_break':
                echo '</div><div class="ugentic-form__step" data-step-label="' . esc_attr($label) . '">';
                break;

            case 'password':
                $this->render_input_field('password', $id, $name, $placeholder, $required, $default, $field);
                break;

            case 'slider':
                $min = $field['min'] ?? 0;
                $max = $field['max'] ?? 100;
                $step = $field['step'] ?? 1;
                echo '<div class="ugentic-form__slider-wrap">';
                echo '<input type="range" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '" value="' . esc_attr($default ?: $min) . '" class="ugentic-form__slider" oninput="this.nextElementSibling.textContent=this.value">';
                echo '<span class="ugentic-form__slider-value">' . esc_html($default ?: $min) . '</span>';
                echo '</div>';
                break;

            case 'signature':
                echo '<div class="ugentic-form__signature-wrap">';
                echo '<canvas id="' . esc_attr($id) . '-canvas" class="ugentic-form__signature-canvas" width="400" height="150"></canvas>';
                echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
                echo '<button type="button" class="ugentic-form__signature-clear" data-canvas="' . esc_attr($id) . '-canvas">' . esc_html__('Clear', 'tahmir-ai-to-wp-converter') . '</button>';
                echo '</div>';
                break;

            case 'address':
                echo '<div class="ugentic-form__address-group">';
                echo '<input type="text" name="' . esc_attr($name) . '[street]" placeholder="' . esc_attr__('Street Address', 'tahmir-ai-to-wp-converter') . '" class="ugentic-form__input" ' . ($required ? 'required' : '') . '>';
                echo '<div class="ugentic-form__address-row">';
                echo '<input type="text" name="' . esc_attr($name) . '[city]" placeholder="' . esc_attr__('City', 'tahmir-ai-to-wp-converter') . '" class="ugentic-form__input" ' . ($required ? 'required' : '') . '>';
                echo '<input type="text" name="' . esc_attr($name) . '[state]" placeholder="' . esc_attr__('State/Province', 'tahmir-ai-to-wp-converter') . '" class="ugentic-form__input">';
                echo '</div>';
                echo '<div class="ugentic-form__address-row">';
                echo '<input type="text" name="' . esc_attr($name) . '[zip]" placeholder="' . esc_attr__('ZIP/Postal Code', 'tahmir-ai-to-wp-converter') . '" class="ugentic-form__input">';
                echo '<input type="text" name="' . esc_attr($name) . '[country]" placeholder="' . esc_attr__('Country', 'tahmir-ai-to-wp-converter') . '" class="ugentic-form__input">';
                echo '</div>';
                echo '</div>';
                break;

            case 'likert':
                $rows = $field['likert_rows'] ?? ['Item 1', 'Item 2', 'Item 3'];
                $cols = $field['likert_cols'] ?? ['Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree'];
                echo '<table class="ugentic-form__likert"><thead><tr><th></th>';
                foreach ($cols as $col) {
                    echo '<th>' . esc_html($col) . '</th>';
                }
                echo '</tr></thead><tbody>';
                foreach ($rows as $ri => $row) {
                    echo '<tr><td>' . esc_html($row) . '</td>';
                    foreach ($cols as $ci => $col) {
                        echo '<td><input type="radio" name="' . esc_attr($name) . '[' . esc_attr($ri) . ']" value="' . esc_attr($col) . '" ' . ($required && $ci === 0 ? '' : '') . '></td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
                break;

            case 'name':
                echo '<div class="ugentic-form__name-group">';
                echo '<input type="text" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" class="ugentic-form__input" placeholder="' . esc_attr($placeholder ?: __('Full Name', 'tahmir-ai-to-wp-converter')) . '" ' . ($required ? 'required' : '') . '>';
                echo '</div>';
                break;
        }
?>
            
            <?php if ($description): ?>
            <span class="ugentic-form__description" id="<?php echo esc_attr($id); ?>_desc"><?php echo esc_html($description); ?></span>
            <?php
        endif; ?>
        </div>
        <?php
    }

    private function render_input_field($type, $id, $name, $placeholder, $required, $default, $field)
    {
        $attrs = [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'class' => 'ugentic-form__input',
            'placeholder' => $placeholder,
            'value' => $default,
        ];

        if ($required)
            $attrs['required'] = 'required';
        if (!empty($field['min']))
            $attrs['min'] = $field['min'];
        if (!empty($field['max']))
            $attrs['max'] = $field['max'];
        if (!empty($field['pattern']))
            $attrs['pattern'] = $field['pattern'];
        if (!empty($field['min_length']))
            $attrs['minlength'] = max(0, (int) $field['min_length']);
        if (!empty($field['max_length']))
            $attrs['maxlength'] = max(0, (int) $field['max_length']);
        if (isset($field['step']) && $field['step'] !== '')
            $attrs['step'] = $field['step'];
        if (!empty($field['autocomplete']))
            $attrs['autocomplete'] = $field['autocomplete'];
        if (!empty($field['inputmode']))
            $attrs['inputmode'] = $field['inputmode'];

        echo '<input';
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                echo ' ' . esc_attr($key);
            } elseif ($value !== false && $value !== '') {
                echo ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }
        echo '>';
    }

    private function render_textarea_field($id, $name, $placeholder, $required, $default, $field)
    {
        $rows = $field['rows'] ?? 4;
?>
        <textarea 
            id="<?php echo esc_attr($id); ?>"
            name="<?php echo esc_attr($name); ?>"
            class="ugentic-form__textarea"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            rows="<?php echo esc_attr($rows); ?>"
            <?php if (!empty($field['min_length'])): ?>minlength="<?php echo esc_attr(max(0, (int) $field['min_length'])); ?>"<?php endif; ?>
            <?php if (!empty($field['max_length'])): ?>maxlength="<?php echo esc_attr(max(0, (int) $field['max_length'])); ?>"<?php endif; ?>
            <?php if (!empty($field['autocomplete'])): ?>autocomplete="<?php echo esc_attr($field['autocomplete']); ?>"<?php endif; ?>
            <?php if ($required): ?>required<?php
        endif; ?>
        ><?php echo esc_textarea($default); ?></textarea>
        <?php
    }

    private function render_select_field($id, $name, $required, $options, $default, $field)
    {
        $multiple = !empty($field['multiple']);
?>
        <select 
            id="<?php echo esc_attr($id); ?>"
            name="<?php echo esc_attr($name); ?><?php if ($multiple): ?>[]<?php
        endif; ?>"
            class="ugentic-form__select"
            <?php if ($required): ?>required<?php
        endif; ?>
            <?php if ($multiple): ?>multiple<?php
        endif; ?>
        >
            <?php if (!$multiple && empty($required)): ?>
            <option value=""><?php echo esc_html($field['placeholder'] ?? 'Select...'); ?></option>
            <?php
        endif; ?>
            <?php foreach ($options as $option): ?>
            <option value="<?php echo esc_attr($option['value'] ?? $option); ?>" 
                    <?php selected($default, $option['value'] ?? $option); ?>>
                <?php echo esc_html($option['label'] ?? $option); ?>
            </option>
            <?php
        endforeach; ?>
        </select>
        <?php
    }

    private function render_radio_field($id, $name, $required, $options, $default)
    {
?>
        <div class="ugentic-form__radio-group">
            <?php foreach ($options as $index => $option): ?>
            <label class="ugentic-form__radio-label">
                <input type="radio" 
                       name="<?php echo esc_attr($name); ?>"
                       value="<?php echo esc_attr($option['value'] ?? $option); ?>"
                       <?php checked($default, $option['value'] ?? $option); ?>
                       <?php if ($required && $index === 0): ?>required<?php
            endif; ?>>
                <span><?php echo esc_html($option['label'] ?? $option); ?></span>
            </label>
            <?php
        endforeach; ?>
        </div>
        <?php
    }

    private function render_checkbox_field($id, $name, $required, $options, $default)
    {
        $defaults = is_array($default) ? $default : [$default];
?>
        <div class="ugentic-form__checkbox-group">
            <?php foreach ($options as $option): ?>
            <label class="ugentic-form__checkbox-label">
                <input type="checkbox" 
                       name="<?php echo esc_attr($name); ?>[]"
                       value="<?php echo esc_attr($option['value'] ?? $option); ?>"
                       <?php checked(in_array($option['value'] ?? $option, $defaults)); ?>>
                <span><?php echo esc_html($option['label'] ?? $option); ?></span>
            </label>
            <?php
        endforeach; ?>
        </div>
        <?php
    }

    private function render_file_field($id, $name, $required, $field)
    {
        $accept = $field['accept'] ?? '';
        $multiple = !empty($field['multiple']);
?>
        <input type="file"
               id="<?php echo esc_attr($id); ?>"
               name="<?php echo esc_attr($name); ?><?php if ($multiple): ?>[]<?php
        endif; ?>"
               class="ugentic-form__file"
               <?php if ($accept): ?>accept="<?php echo esc_attr($accept); ?>"<?php
        endif; ?>
               <?php if ($required): ?>required<?php
        endif; ?>
               <?php if ($multiple): ?>multiple<?php
        endif; ?>>
        <?php
    }

    private function render_rating_field($id, $name, $required, $field)
    {
        $max = $field['max'] ?? 5;
?>
        <div class="ugentic-form__rating" data-max="<?php echo esc_attr($max); ?>">
            <?php for ($i = 1; $i <= $max; $i++): ?>
            <label class="ugentic-form__rating-star">
                <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($i); ?>"
                       <?php if ($required && $i === 1): ?>required<?php
            endif; ?>>
                <span>★</span>
            </label>
            <?php
        endfor; ?>
        </div>
        <?php
    }

    /**
     * Build HTML attributes string
     */
    private function build_attrs($attrs)
    {
        $output = [];
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $output[] = esc_attr($key);
            }
            elseif ($value !== false && $value !== '') {
                $output[] = esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }
        return implode(' ', $output);
    }

    /**
     * Render form-specific styles
     */
    private function render_form_styles($form_id, $styles)
    {
        $primary = $styles['primary_color'] ?? '#f97316';
        $bg = $styles['background'] ?? 'transparent';
        $border_radius = $styles['border_radius'] ?? '8';
        $padding = $styles['padding'] ?? '20';

?>
        <style>
        #ugentic-form-<?php echo esc_attr($form_id); ?> {
            --form-primary: <?php echo esc_attr($primary); ?>;
            background: <?php echo esc_attr($bg); ?>;
            border-radius: <?php echo esc_attr($border_radius); ?>px;
            padding: <?php echo esc_attr($padding); ?>px;
        }
        </style>
        <?php
    }

    /**
     * AJAX: Handle form submission
     */
    public function ajax_submit()
    {
        $form_id = intval($_POST['form_id'] ?? ($_POST['ugentic_form_id'] ?? 0));

        // Verify nonce — support both shortcode and widget form nonce patterns
        $nonce_valid = false;

        // Path 1: Shortcode form sends ugentic_form_nonce with action ugentic_form_submit_{form_id}
        if (!empty($_POST['ugentic_form_nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['ugentic_form_nonce'], 'ugentic_form_submit_' . $form_id);
        }

        // Path 2: Widget form sends nonce with generic action ugentic_form_submit
        if (!$nonce_valid && !empty($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'ugentic_form_submit');
        }

        if (!$nonce_valid) {
            wp_send_json_error(['message' => __('Security check failed.', 'tahmir-ai-to-wp-converter')]);
        }

        if (class_exists('Ugentic_Form_Engine')) {
            $result = Ugentic_Form_Engine::handle_submission([
                'form_id' => $form_id,
                'source_mode' => 'native',
                'post' => $_POST,
                'files' => $_FILES,
            ]);
            Ugentic_Form_Engine::wp_send_submission_response($result);
        }

    }

    /**
     * AJAX: Handle preserved faithful imported forms.
     */
    public function ajax_faithful_submit()
    {
        $form_id = intval($_POST['ugentic_form_id'] ?? ($_POST['form_id'] ?? 0));

        if (!$form_id) {
            wp_send_json_error(['message' => __('Form not found.', 'tahmir-ai-to-wp-converter')]);
        }

        if (class_exists('Ugentic_Form_Engine')) {
            $result = Ugentic_Form_Engine::handle_submission([
                'form_id' => $form_id,
                'source_mode' => 'faithful',
                'post' => $_POST,
                'files' => $_FILES,
            ]);
            Ugentic_Form_Engine::wp_send_submission_response($result);
        }

        wp_send_json_error(['message' => __('Form handler unavailable.', 'tahmir-ai-to-wp-converter')]);
    }

    /**
     * Send email notifications
     */

    /**
     * Register form scripts early (priority 5) so they survive aggressive dequeuing.
     * Registration alone does not load them on the page.
     */
    public function register_scripts()
    {
        $form_css_version = self::asset_version('public/css/form.css');
        $form_js_version = self::asset_version('public/js/form.js');

        wp_register_style(
            'ugentic-form',
            UGENTIC_PLUGIN_URL . 'public/css/form.css',
            [],
            $form_css_version
        );

        wp_register_script(
            'ugentic-form',
            UGENTIC_PLUGIN_URL . 'public/js/form.js',
            ['jquery'],
            $form_js_version,
            true
        );
    }

    /**
     * Maybe enqueue form scripts (early detection pass).
     * Checks post_content AND Ugentic DB HTML for the [ugentic_form] shortcode.
     */
    public function maybe_enqueue_scripts()
    {
        global $post;

        if (!$post)
            return;

        // Check if WP post content contains our shortcode
        if (has_shortcode($post->post_content, 'ugentic_form')) {
            $this->enqueue_scripts();
            return;
        }

        // Also check Ugentic custom HTML storage (shortcodes live there for imported pages)
        $ugentic_page_id = get_post_meta($post->ID, '_ugentic_page_id', true);
        if ($ugentic_page_id) {
            $database = ugentic_builder()->database;
            $page = $database->get_page($ugentic_page_id);
            if ($page && !empty($page->current_html) && strpos($page->current_html, '[ugentic_form') !== false) {
                $this->enqueue_scripts();
            }
        }
    }

    /**
     * Restore form assets after aggressive theme dequeuing.
     * The dequeue_theme_styles() in class-assets.php runs at priority 999 and
     * deregisters ALL styles not matching 'ugentic' in the handle. Our handle
     * IS 'ugentic-form' so it should survive, but this is a safety net in case
     * the registration was lost or the order was wrong.
     */
    public function restore_form_assets()
    {
        if (!$this->assets_enqueued) {
            return;
        }

        // Re-register if deregistered
        if (!wp_style_is('ugentic-form', 'registered')) {
            wp_register_style(
                'ugentic-form',
                UGENTIC_PLUGIN_URL . 'public/css/form.css',
                [],
                self::asset_version('public/css/form.css')
            );
        }
        if (!wp_script_is('ugentic-form', 'registered')) {
            wp_register_script(
                'ugentic-form',
                UGENTIC_PLUGIN_URL . 'public/js/form.js',
                ['jquery'],
                self::asset_version('public/js/form.js'),
                true
            );
        }

        // Re-enqueue
        wp_enqueue_style('ugentic-form');
        wp_enqueue_script('ugentic-form');
    }

    /**
     * Enqueue form scripts and styles.
     * Safe to call multiple times — WordPress deduplicates.
     */
    public function enqueue_scripts()
    {
        $this->assets_enqueued = true;

        // Re-register if somehow deregistered before this point
        if (!wp_style_is('ugentic-form', 'registered')) {
            wp_register_style(
                'ugentic-form',
                UGENTIC_PLUGIN_URL . 'public/css/form.css',
                [],
                self::asset_version('public/css/form.css')
            );
        }
        if (!wp_script_is('ugentic-form', 'registered')) {
            wp_register_script(
                'ugentic-form',
                UGENTIC_PLUGIN_URL . 'public/js/form.js',
                ['jquery'],
                self::asset_version('public/js/form.js'),
                true
            );
        }

        wp_enqueue_style('ugentic-form');
        wp_enqueue_script('ugentic-form');

        // Localize only once
        static $localized = false;
        if (!$localized) {
            $localized = true;
            wp_localize_script('ugentic-form', 'ugenticForm', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'strings' => [
                    'submitting' => __('Submitting...', 'tahmir-ai-to-wp-converter'),
                    'error' => __('An error occurred. Please try again.', 'tahmir-ai-to-wp-converter'),
                ],
            ]);

            // Also localize ugenticAjax for widget form inline JS
            wp_localize_script('ugentic-form', 'ugenticAjax', [
                'url' => admin_url('admin-ajax.php'),
            ]);
        }
    }

    /**
     * Sync faithful form fields in the full page HTML.
     * Finds all <form data-ugentic-form-id="X"> tags and rebuilds their
     * input/textarea/select elements from the DB, while preserving the
     * original CSS classes, wrappers, and styling.
     *
     * @param string $html Full page HTML
     * @return string HTML with synced form fields
     */
    public static function sync_faithful_forms($html)
    {
        // Find all forms with data-ugentic-form-id
        $pattern = '/<form([^>]*data-ugentic-form-id=["\'](\d+)["\'][^>]*)>(.*?)<\/form>/is';

        $html = preg_replace_callback($pattern, function ($matches) {
            $form_attrs = self::clean_runtime_form_attrs($matches[1]);
            $form_id = intval($matches[2]);
            $form_content = $matches[3];

            $database = ugentic_builder()->database;
            $form = $database->get_form($form_id);

            if (!$form || empty($form->fields)) {
                // Form not found in DB — leave original HTML as-is
                return '<form' . $form_attrs . '>' . $form_content . '</form>';
            }

            $renderer = ugentic_builder()->form_renderer;
            if (!$renderer instanceof self) {
                return '<form' . $form_attrs . '>' . $form_content . '</form>';
            }
            $synced_content = $renderer->render_faithful_form_fields($form, $form_content);

            return '<form' . $form_attrs . '>' . $synced_content . '</form>';
        }, $html);

        return $html;
    }

    /**
     * Render faithful form fields: sync DB fields into the original form HTML.
     *
     * Strategy:
     * 1. Try to match DB fields to HTML elements by name or id attribute
     * 2. If most fields are unmatched (common for imported forms without name attrs),
     *    fall back to positional matching: match DB fields to HTML elements by type + order
     * 3. For each matched pair, inject the DB field's name attribute so form submission works
     * 4. Preserve all CSS classes, wrapper divs, labels, and structural HTML
     *
     * @param object $form The form object from the DB
     * @param string $original_content The original inner HTML of the form
     * @return string The synced form content
     */

    public function render_faithful_form_fields($form, $original_content)
    {
        $db_fields = $form->fields;
        if (!is_array($db_fields)) {
            return $original_content;
        }

        $settings = is_array($form->settings ?? null) ? $form->settings : [];
        $preserve_mode = ($settings['preservation_mode'] ?? '') === 'preserve' || ($settings['source_mode'] ?? '') === 'faithful';

        // Build ordered lists of DB fields by element type for positional matching
        $db_inputs = [];   // text, email, tel, url, number, date, etc.
        $db_textareas = [];
        $db_selects = [];
        $db_field_map = [];
        foreach ($db_fields as $field) {
            $name = $field['name'] ?? '';
            if (empty($name)) continue;
            
            foreach ($this->faithful_field_aliases($field) as $alias) {
                $db_field_map[$alias] = $field;
            }
            $type = $field['type'] ?? 'text';

            if (in_array($type, ['hidden', 'content', 'divider', 'page_break'], true)) {
                continue;
            }
            
            if ($type === 'textarea') {
                $db_textareas[] = $field;
            } elseif ($type === 'select') {
                $db_selects[] = $field;
            } else {
                // text, email, tel, url, number, date, etc. → all render as <input>
                $db_inputs[] = $field;
            }
        }

        $html_input_count = 0;
        if (preg_match_all('/<input([^>]+)>/i', $original_content, $input_matches)) {
            foreach ($input_matches[1] as $attrs_str) {
                $type = strtolower($this->faithful_get_attr($attrs_str, 'type') ?: 'text');
                if (!in_array($type, ['submit', 'button', 'image', 'reset', 'hidden'], true)) {
                    $html_input_count++;
                }
            }
        }
        $html_textarea_count = preg_match_all('/<textarea\b/i', $original_content) ?: 0;
        $html_select_count = preg_match_all('/<select\b/i', $original_content) ?: 0;
        $allow_input_position = !$preserve_mode || $html_input_count === count($db_inputs);
        $allow_textarea_position = !$preserve_mode || $html_textarea_count === count($db_textareas);
        $allow_select_position = !$preserve_mode || $html_select_count === count($db_selects);

        $content = $original_content;

        // Positional counters for fallback matching — tracks how many HTML elements
        // consumed each DB field list
        $input_index = 0;
        $textarea_index = 0;
        $select_index = 0;
        // Track which DB field names were matched (by name, id, or position)
        $matched_field_names = [];

        // --- 1) Sync existing <input> elements ---
        $content = preg_replace_callback('/<input([^>]+)>/i', function ($match) use ($db_field_map, $db_inputs, $preserve_mode, $allow_input_position, &$input_index, &$matched_field_names) {
            $attrs_str = $match[1];

            // Extract the name attribute
            $name = $this->faithful_get_attr($attrs_str, 'name');

            // Extract the id attribute
            $el_id = $this->faithful_get_attr($attrs_str, 'id');

            // Extract the type attribute
            $type = strtolower($this->faithful_get_attr($attrs_str, 'type') ?: 'text');

            // Skip submit/button/hidden/image/reset — these aren't form data fields
            if (in_array($type, ['submit', 'button', 'image', 'reset', 'hidden'])) {
                return $match[0];
            }

            // Try to find matching DB field: by name, id, aliases, then position.
            $db_field = $this->faithful_find_field($db_field_map, $name, $el_id);
            if (!$db_field && $allow_input_position && isset($db_inputs[$input_index])) {
                // Positional fallback: match to the next DB input field in order
                $db_field = $db_inputs[$input_index];
            }
            $input_index++;

            if ($db_field) {
                $field_name = $db_field['name'] ?? '';
                if (!empty($field_name)) $matched_field_names[$field_name] = true;

                $attrs_str = $this->sync_faithful_control_attrs($attrs_str, $db_field, 'input', $type, $name, $preserve_mode);

                return '<input' . $attrs_str . '>';
            }

            return $match[0];
        }, $content);

        // --- 2) Sync existing <textarea> elements ---
        $content = preg_replace_callback('/<textarea([^>]*)>(.*?)<\/textarea>/is', function ($match) use ($db_field_map, $db_textareas, $allow_textarea_position, &$textarea_index, &$matched_field_names) {
            $attrs_str = $match[1];
            $inner = $match[2];

            $name = $this->faithful_get_attr($attrs_str, 'name');

            $el_id = $this->faithful_get_attr($attrs_str, 'id');

            // Try to find matching DB field.
            $db_field = $this->faithful_find_field($db_field_map, $name, $el_id);
            if (!$db_field && $allow_textarea_position && isset($db_textareas[$textarea_index])) {
                $db_field = $db_textareas[$textarea_index];
            }
            $textarea_index++;

            if ($db_field) {
                $field_name = $db_field['name'] ?? '';
                if (!empty($field_name)) $matched_field_names[$field_name] = true;

                $attrs_str = $this->sync_faithful_control_attrs($attrs_str, $db_field, 'textarea', 'textarea', $name, true);

                return '<textarea' . $attrs_str . '>' . $inner . '</textarea>';
            }

            return $match[0];
        }, $content);

        // --- 3) Sync existing <select> elements ---
        $content = preg_replace_callback('/<select([^>]*)>(.*?)<\/select>/is', function ($match) use ($db_field_map, $db_selects, $allow_select_position, &$select_index, &$matched_field_names) {
            $attrs_str = $match[1];
            $inner = $match[2];

            $name = $this->faithful_get_attr($attrs_str, 'name');

            $el_id = $this->faithful_get_attr($attrs_str, 'id');

            // Try to find matching DB field.
            $db_field = $this->faithful_find_field($db_field_map, $name, $el_id);
            if (!$db_field && $allow_select_position && isset($db_selects[$select_index])) {
                $db_field = $db_selects[$select_index];
            }
            $select_index++;

            if ($db_field) {
                $field_name = $db_field['name'] ?? '';
                if (!empty($field_name)) $matched_field_names[$field_name] = true;

                $attrs_str = $this->sync_faithful_control_attrs($attrs_str, $db_field, 'select', 'select', $name, true);

                return '<select' . $attrs_str . '>' . $inner . '</select>';
            }

            return $match[0];
        }, $content);

        // --- 5) Append genuinely NEW fields added via the form builder ---
        // Only append fields that weren't matched to any HTML element (by name, id, or position).
        // These are fields the user added after the initial import.
        // DUPLICATION GUARD: Also check if the field name already appears in the
        // HTML content (it might have been added in a previous sync cycle).
        $new_fields_html = '';
        foreach ($db_fields as $field) {
            $name = $field['name'] ?? '';
            if (empty($name) || isset($matched_field_names[$name])) {
                continue; // Already matched to an HTML element
            }

            // DUPLICATION GUARD: Check all known aliases, not only the normalized
            // DB field name. Imported HTML often uses source names such as
            // topics[] while the DB stores topics.
            if ($this->faithful_field_exists_in_html($content, $field)) {
                continue; // Field already exists in HTML — skip to prevent duplication
            }

            $type = $field['type'] ?? 'text';
            $label = $field['label'] ?? ucwords(str_replace(['_', '-'], ' ', $name));
            $placeholder = $field['placeholder'] ?? '';
            $required = !empty($field['required']) ? ' required' : '';

            if ($type === 'hidden') {
                $new_fields_html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($field['default'] ?? '') . '">';
                continue;
            }

            if (in_array($type, ['content', 'divider', 'page_break'], true)) {
                continue;
            }

            // Try to detect existing form styling from the page
            $input_class = '';
            if (preg_match('/class=["\']([^"\']*)["\']/', $original_content, $cls)) {
                // Use the first input's CSS class for consistency
                $input_class = ' class="' . esc_attr($cls[1]) . '"';
            }

            $new_fields_html .= '<div class="ugentic-faithful-new-field" data-field-name="' . esc_attr($name) . '" style="margin-bottom:16px;">';
            $new_fields_html .= '<label style="display:block;margin-bottom:4px;font-weight:600;">' . esc_html($label);
            if (!empty($field['required'])) {
                $new_fields_html .= ' <span style="color:red;">*</span>';
            }
            $new_fields_html .= '</label>';

            switch ($type) {
                case 'textarea':
                    $rows = $field['rows'] ?? 4;
                    $new_fields_html .= '<textarea name="' . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '" rows="' . intval($rows) . '"' . $required . $input_class . ' style="width:100%;padding:8px 12px;border:1px solid rgba(255,255,255,0.2);border-radius:8px;box-sizing:border-box;background:rgba(255,255,255,0.05);color:inherit;font:inherit;"></textarea>';
                    break;

                case 'select':
                    $new_fields_html .= '<select name="' . esc_attr($name) . '"' . $required . $input_class . ' style="width:100%;padding:8px 12px;border:1px solid rgba(255,255,255,0.2);border-radius:8px;background:rgba(255,255,255,0.05);color:inherit;font:inherit;">';
                    $new_fields_html .= '<option value="">Select...</option>';
                    if (!empty($field['options'])) {
                        foreach ($field['options'] as $opt) {
                            $val = $opt['value'] ?? $opt;
                            $lbl = $opt['label'] ?? $opt;
                            $new_fields_html .= '<option value="' . esc_attr($val) . '">' . esc_html($lbl) . '</option>';
                        }
                    }
                    $new_fields_html .= '</select>';
                    break;

                default:
                    $input_type = in_array($type, ['text', 'email', 'tel', 'url', 'number', 'date', 'time']) ? $type : 'text';
                    $new_fields_html .= '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '"' . $required . $input_class . ' style="width:100%;padding:8px 12px;border:1px solid rgba(255,255,255,0.2);border-radius:8px;box-sizing:border-box;background:rgba(255,255,255,0.05);color:inherit;font:inherit;">';
                    break;
            }

            $new_fields_html .= '</div>';
        }

        // Insert new fields before the submit button if any
        if (!empty($new_fields_html)) {
            $submit_patterns = [
                '/(<button[^>]*type=["\']submit["\'][^>]*>)/i',
                '/(<input[^>]*type=["\']submit["\'][^>]*>)/i',
                '/(<button[^>]*>)/i',
            ];

            $inserted = false;
            foreach ($submit_patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, $new_fields_html . '$1', $content, 1);
                    $inserted = true;
                    break;
                }
            }

            if (!$inserted) {
                $content .= $new_fields_html;
            }
        }

        return $content;
    }


    private function faithful_get_attr($attrs_str, $attr)
    {
        preg_match_all('~\s+([^\s=/>]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))?~', $attrs_str, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (strcasecmp($match[1], $attr) !== 0) continue;
            return html_entity_decode(trim($match[2] ?? '', "\"'"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }


    private function faithful_find_field($field_map, $name = '', $id = '')
    {
        foreach ([$name, $id] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (isset($field_map[$candidate])) {
                return $field_map[$candidate];
            }

            if (substr($candidate, -2) === '[]') {
                $base = substr($candidate, 0, -2);
                if (isset($field_map[$base])) {
                    return $field_map[$base];
                }
            }

            $normalized = $this->faithful_normalize_field_name($candidate);
            if ($normalized !== '' && isset($field_map[$normalized])) {
                return $field_map[$normalized];
            }
        }

        return null;
    }


    private function faithful_field_aliases($field)
    {
        $aliases = [];
        foreach (['name', 'source_name', 'source_id', 'id'] as $key) {
            if (empty($field[$key])) {
                continue;
            }

            $value = (string) $field[$key];
            $aliases[] = $value;

            if (substr($value, -2) === '[]') {
                $aliases[] = substr($value, 0, -2);
            }

            $normalized = $this->faithful_normalize_field_name($value);
            if ($normalized !== '') {
                $aliases[] = $normalized;
            }
        }

        return array_values(array_unique(array_filter($aliases, 'strlen')));
    }


    private function faithful_field_exists_in_html($html, $field)
    {
        foreach ($this->faithful_field_aliases($field) as $alias) {
            $escaped = preg_quote($alias, '/');
            if (preg_match('/\s(?:name|id|data-field-name)=["\']' . $escaped . '["\']/i', $html)) {
                return true;
            }
        }

        return false;
    }


    private function sync_faithful_control_attrs($attrs_str, $field, $tag, $current_type = '', $current_name = '', $preserve_type = true)
    {
        $field_name = $field['name'] ?? '';
        $type = $field['type'] ?? $current_type;

        if ($current_name === '' && $field_name !== '') {
            $attrs_str = $this->faithful_set_attr($attrs_str, 'name', $field_name);
        }

        if ($tag === 'input' && !$preserve_type && in_array($type, ['text', 'email', 'tel', 'url', 'number', 'date', 'time', 'datetime-local'], true)) {
            $attrs_str = $this->faithful_set_attr($attrs_str, 'type', $type);
        }

        if (!empty($field['required'])) {
            $attrs_str = $this->faithful_set_attr($attrs_str, 'data-ugentic-required', '1');
            $attrs_str = $this->faithful_set_attr($attrs_str, 'aria-required', 'true');
            if (!in_array($type, ['checkbox', 'radio'], true)) {
                $attrs_str = $this->faithful_set_attr($attrs_str, 'required', null);
            }
        } else {
            $attrs_str = $this->faithful_remove_attr($attrs_str, 'required');
            $attrs_str = $this->faithful_remove_attr($attrs_str, 'data-ugentic-required');
            $attrs_str = $this->faithful_remove_attr($attrs_str, 'aria-required');
        }

        foreach (['min', 'max', 'step', 'pattern', 'accept'] as $attr) {
            if (array_key_exists($attr, $field) && $field[$attr] !== '') {
                $attrs_str = $this->faithful_set_attr($attrs_str, $attr, $field[$attr]);
            }
        }

        if (!empty($field['min_length'])) {
            $attrs_str = $this->faithful_set_attr($attrs_str, 'minlength', $field['min_length']);
            $attrs_str = $this->faithful_set_attr($attrs_str, 'data-minlength', $field['min_length']);
        }
        if (!empty($field['max_length'])) {
            $attrs_str = $this->faithful_set_attr($attrs_str, 'maxlength', $field['max_length']);
        }

        return $attrs_str;
    }


    private function faithful_remove_attr($attrs_str, $attr)
    {
        return $this->faithful_update_attr($attrs_str, $attr, null, true);
    }


    private function faithful_set_attr($attrs_str, $attr, $value = null)
    {
        return $this->faithful_update_attr($attrs_str, $attr, $value, false);
    }


    private function faithful_normalize_field_name($value)
    {
        $value = strtolower(trim((string) $value));
        if (substr($value, -2) === '[]') {
            $value = substr($value, 0, -2);
        }
        $value = preg_replace('/[^a-z0-9_\-\[\]]+/', '_', $value);
        return trim($value, '_-');
    }


    private function faithful_update_attr($attrs_str, $attr, $value, $remove)
    {
        $found = false;
        $replacement = $value === null ? $attr : $attr . '="' . esc_attr($value) . '"';
        // Consume each entire attribute, including its quoted value. Words
        // such as "name" or "required" inside placeholders are not attributes.
        $result = preg_replace_callback('~\s+([^\s=/>]+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?~', function ($match) use ($attr, $replacement, $remove, &$found) {
            if (strcasecmp($match[1], $attr) !== 0) return $match[0];
            $duplicate = $found;
            $found = true;
            return ($remove || $duplicate) ? '' : ' ' . $replacement;
        }, $attrs_str);
        return !$found && !$remove ? rtrim($result) . ' ' . $replacement : $result;
    }
}
