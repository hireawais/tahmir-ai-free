<?php
/**
 * Website Wizard for Tahmir AI
 * Thin AJAX router that delegates to Wizard_Engine, Logo_Generator, and Compliance_Engine.
 *
 * @package Ugentic_Builder
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Wizard
{public function __construct() {
    add_action('admin_menu', [$this, 'add_wizard_menu'], 20);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    add_action('ugentic_wizard_run_clone_job', [$this, 'run_clone_job'], 10, 3);
add_action('wp_ajax_ugentic_wizard_clone_website', [$this, 'ajax_clone_website']);
add_action('wp_ajax_ugentic_wizard_clone_status', [$this, 'ajax_clone_status']);
add_action('wp_ajax_ugentic_wizard_materialize_client_shell', [$this, 'ajax_materialize_client_shell']);
}

    /**
     * Add wizard menu page
     */
    public function add_wizard_menu()
    {
        add_submenu_page(
            null,
            __('Website Wizard', 'tahmir-ai-to-wp-converter'),
            __('Website Wizard', 'tahmir-ai-to-wp-converter'),
            'manage_options',
            'ugentic-wizard',
            [$this, 'render_wizard']
        );

        add_submenu_page(
            null,
            __('Clone Website', 'tahmir-ai-to-wp-converter'),
            __('Clone Website', 'tahmir-ai-to-wp-converter'),
            'manage_options',
            'ugentic-clone',
            [$this, 'render_clone']
        );

        // DEPRECATED: Page Designer removed in v0.7.1
    }

    // DEPRECATED: render_section_designer() removed in v0.7.1

    /**
     * Render the wizard view
     */public function render_wizard() { Tahmir_Free_Edition::locked_view('wizard', __('AI Website Wizard', 'tahmir-ai-to-wp-converter')); }

    /**
     * Render direct clone center outside of the website wizard.
     */
    public function render_clone()
    {
        include UGENTIC_PLUGIN_DIR . 'admin/views/clone.php';
    }

    /**
     * Enqueue wizard assets
     */
    public function enqueue_assets($hook)
    {
        // DEPRECATED: Section Designer assets removed in v0.7.1

        if (strpos($hook, 'ugentic-wizard') === false && strpos($hook, 'ugentic-clone') === false) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'ugentic-wizard',
            UGENTIC_PLUGIN_URL . 'admin/css/wizard.css',
        [],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/wizard.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/wizard.css') : UGENTIC_VERSION
        );

        if (strpos($hook, 'ugentic-clone') !== false) {
            wp_enqueue_style(
                'ugentic-clone',
                UGENTIC_PLUGIN_URL . 'admin/css/clone.css',
                ['ugentic-wizard'],
                file_exists(UGENTIC_PLUGIN_DIR . 'admin/css/clone.css') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/css/clone.css') : UGENTIC_VERSION
            );
        }

        wp_enqueue_script('ugentic-clone-capture', UGENTIC_PLUGIN_URL . 'admin/js/clone-capture.js', [], UGENTIC_VERSION, true);
        wp_enqueue_script(
            'ugentic-wizard',
            UGENTIC_PLUGIN_URL . 'admin/js/wizard.js',
            ['jquery', 'ugentic-clone-capture'],
            file_exists(UGENTIC_PLUGIN_DIR . 'admin/js/wizard.js') ? filemtime(UGENTIC_PLUGIN_DIR . 'admin/js/wizard.js') : UGENTIC_VERSION,
            true
        );

        // Color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

wp_localize_script('ugentic-wizard', 'ugenticWizard', [
    'ajaxUrl'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('ugentic_wizard_nonce'),
    'restUrl'=>rest_url('ugentic/v1/'), 'projects'=>[], 'iconStyles'=>[], 'moods'=>[],
    'shapeLanguages'=>[], 'complianceQuestions'=>[], 'discoveryQuestions'=>[],
    'industryGroups'=>[], 'inspirationQuiz'=>[], 'inspirationGallery'=>[]
]);
}
    /**
     * Transform gallery data for JavaScript consumption.
     * Maps notable_features → design_attributes and adds CSS color_palette gradients.
     */

    // ========================================================================
    // AJAX HANDLERS
    // ========================================================================

    /**
     * Verify nonce helper
     */
    private function verify_nonce()
    {
        if (!check_ajax_referer('ugentic_wizard_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }
    }

    // ------------------- DISCOVERY -------------------

    // ------------------- LOGO -------------------

    // ------------------- BRAND -------------------

    // ------------------- INSPIRATION -------------------

    // ------------------- BLUEPRINT (Upload / URL) -------------------

    // ------------------- PAGES -------------------

    // ------------------- CONTENT -------------------

    // ------------------- COMPLIANCE -------------------

    // ------------------- DESIGN -------------------

    // ------------------- IMAGES -------------------

    /**
     * Universal skip handler — skip any skippable wizard step.
     * The step name is passed via POST and the project status is advanced
     * to the "completed" state for that step.
     */

    // ------------------- STATE -------------------

    // ------------------- PUBLISH -------------------

    // ------------------- SEQUENTIAL PUBLISH -------------------

    /**
     * Prepare for sequential publishing — saves header/footer, returns page list.
     */

    /**
     * Publish a single page. Called sequentially from frontend.
     */

    // ------------------- SITEMAP XML -------------------

    // ═══════════════════════════════════════════════════════════════════════
    // DESIGN RESEARCH PRE-FETCH
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Pre-fetch design research when user enters the design step.
     * This caches competitor analysis and trend data so actual generation is fast.
     */

    // ═══════════════════════════════════════════════════════════════════════
    // SECTION-BY-SECTION DESIGNER (DEPRECATED — removed in v0.7.1)
    // ═══════════════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════════════
    // QUICK GENERATE — One-Step Website Generation
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Generate a complete website from minimal input (name + industry).
     * Skips all discovery, auto-generates pages, content, and design.
     */
    /**
     * Quick Setup — Creates project + page list FAST (< 5 seconds).
     * Does NOT generate content or designs. The canvas handles that page-by-page.
     */

    /**
     * Generate content + design for a SINGLE page (called from canvas, one at a time).
     */

    // ═══════════════════════════════════════════════════════════════════════
    // CANVAS PIPELINE — 7-Stage Orchestration
    // ═══════════════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════════════
    // URL AUTO-EXTRACTION — Scrape existing website to auto-fill discovery
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Extract page link text from an HTML fragment, filtering out non-page links.
     */

    /**
     * Clone Website — Scrape and replicate a website's structure and design.
     */
    public function ajax_clone_website()
    {
        $this->verify_nonce();
        @set_time_limit(600);

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $url = esc_url_raw($_POST['url'] ?? '');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => 'Please enter a valid URL']);
        }

        $permission_confirmed = strtolower(sanitize_text_field($_POST['permission_confirmed'] ?? ''));
        if ($permission_confirmed !== 'yes') {
            wp_send_json_error(['message' => 'Please confirm you have permission to clone this website']);
        }

        if (!class_exists('Ugentic_Importer')) {
            wp_send_json_error(['message' => 'Faithful importer is not available']);
        }

        $clone_mode = sanitize_key($_POST['clone_mode'] ?? 'site');
        $clone_assist = sanitize_key($_POST['clone_assist'] ?? 'standard');
        $ai_assisted = $clone_assist === 'ai';
        if ($ai_assisted) {
            if (!class_exists('Ugentic_AI_Clone_Assistant') || !Ugentic_AI_Clone_Assistant::is_configured()) {
                wp_send_json_error([
                    'message' => __('Configure an AI provider in Tahmir AI Settings before using AI-assisted cloning.', 'tahmir-ai-to-wp-converter'),
                    'settings_url' => admin_url('admin.php?page=ugentic-settings#tab-api-keys'),
                ], 409);
            }
            if (sanitize_key($_POST['ai_data_consent'] ?? '') !== 'yes') {
                wp_send_json_error([
                    'message' => __('Confirm the AI data notice before starting AI-assisted cloning.', 'tahmir-ai-to-wp-converter'),
                ], 400);
            }
        }
        $clone_source = sanitize_key($_POST['clone_source'] ?? 'auto');
        $allowed_clone_sources = ['auto', 'ai_studio', 'webflow', 'framer', 'lovable', 'replit', 'wix', 'squarespace', 'carrd', 'bubble', 'shopify', 'vercel', 'netlify', 'vite', 'nextjs', 'wordpress', 'generic'];
        if (!in_array($clone_source, $allowed_clone_sources, true)) {
            $clone_source = 'auto';
        }
        $single_page = $clone_mode === 'single_page' || sanitize_key($_POST['crawl_scope'] ?? '') === 'single_page';
        $max_pages = $single_page ? 1 : max(1, min(100, intval($_POST['max_pages'] ?? 50)));

        $importer = ugentic_builder()->importer ?? new Ugentic_Importer();

        if (!$single_page && !empty($_POST['preview_pages'])) {
            try {
                $result = $importer->discover_remote_site_pages($url, [
                    'crawl_scope' => 'full_site',
                    'max_pages' => $max_pages,
                    'clone_source' => $clone_source,
                    'ai_assisted' => $ai_assisted,
                    'ai_data_consent' => $ai_assisted,
                    'preview' => true,
                    'preview_time_budget' => 16,
                    'preview_fetch_limit' => 8,
                ]);
            } catch (Throwable $e) {
                wp_send_json_error([
                    'message' => __('Could not discover pages for this site. Try Clone Page, or reduce the page limit and try again.', 'tahmir-ai-to-wp-converter'),
                    'warnings' => [$e->getMessage()],
                ]);
            }

            if (empty($result['success'])) {
                wp_send_json_error([
                    'message' => $result['message'] ?? 'Could not discover pages for this site',
                    'warnings' => $result['warnings'] ?? [],
                ]);
            }

            wp_send_json_success($result);
        }

        $selected_pages = [];
        if (!$single_page) {
            $selected_raw = wp_unslash($_POST['selected_pages'] ?? '');
            $decoded = is_string($selected_raw) && $selected_raw !== '' ? json_decode($selected_raw, true) : [];
            if (is_array($decoded)) {
                foreach ($decoded as $selected_url) {
                    $selected_url = esc_url_raw($selected_url);
                    if ($selected_url !== '' && filter_var($selected_url, FILTER_VALIDATE_URL)) {
                        $selected_pages[] = $selected_url;
                    }
                }
            }

            if (empty($selected_pages)) {
                wp_send_json_error([
                    'message' => __('Choose the pages to clone first.', 'tahmir-ai-to-wp-converter'),
                    'needs_page_selection' => true,
                ]);
            }
        }

        $options = [
            'crawl_scope' => $single_page ? 'single_page' : 'full_site',
            'max_pages' => $max_pages,
            'single_page' => $single_page,
            'publish' => false,
            'selected_pages' => $selected_pages,
            'require_selected_pages' => !$single_page,
            'clone_source' => $clone_source,
            'ai_assisted' => $ai_assisted,
            'ai_data_consent' => $ai_assisted,
            'asset_timeout' => 15,
            'asset_time_budget' => $single_page ? 240 : 900,
            'max_asset_downloads' => $single_page ? 1000 : 2400,
        ];

        $job_id = $this->create_clone_job($url, $options);

        if (function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
            $this->send_clone_job_accepted($job_id, $single_page);
            $this->run_clone_job($job_id, $url, $options);
            exit;
        }

        // Windows and several CGI hosts do not expose fastcgi_finish_request().
        // Running the crawl inline on those servers keeps the AJAX request open
        // until PHP terminates it, which surfaces as a 500. Queue the same job in
        // WP-Cron so the UI can immediately switch to its existing status poller.
        $scheduled = $this->schedule_clone_job($job_id, $url, $options);
        if (is_wp_error($scheduled)) {
            $message = $scheduled->get_error_message();
            $this->update_clone_job($job_id, [
                'status' => 'failed',
                'progress' => 100,
                'message' => $message,
                'updated_at' => time(),
            ]);
            wp_send_json_error([
                'message' => $message,
                'warnings' => [],
            ]);
        }

        wp_send_json_success($this->clone_job_accepted_data($job_id, $single_page), 202);
    }

    public function ajax_clone_status()
    {
        $this->verify_nonce();

        $job_id = sanitize_key($_POST['job_id'] ?? '');
        if ($job_id === '') {
            wp_send_json_error(['message' => 'Clone job ID is required'], 400);
        }

        $job = get_transient($this->clone_job_key($job_id));
        if (!is_array($job)) {
            wp_send_json_error(['message' => 'Clone job was not found or has expired'], 404);
        }

        $guarded_job = $this->clone_job_with_stale_guard($job);
        if ($guarded_job !== $job) {
            $job = $guarded_job;
            $this->update_clone_job($job_id, $job);
        }

        wp_send_json_success($job);
    }

    /**
     * Turn a dead background process into an actionable retry state. Asset
     * requests send frequent heartbeats, so three silent minutes in that phase
     * means the worker is no longer making useful progress.
     */
    private function clone_job_with_stale_guard($job, $now = null)
    {
        if (!is_array($job) || !in_array($job['status'] ?? '', ['queued', 'running'], true)) {
            return $job;
        }

        $now = $now === null ? time() : intval($now);
        $updated_at = intval($job['updated_at'] ?? ($job['started_at'] ?? $now));
        $phase = sanitize_key($job['phase'] ?? ($job['status'] ?? 'running'));
        $stale_after = $phase === 'download_assets' ? 180 : ($phase === 'queued' ? 120 : 420);
        if ($updated_at <= 0 || ($now - $updated_at) <= $stale_after) {
            return $job;
        }

        $warning = __('The previous background worker stopped responding. Retry the import; unreachable assets will remain linked to the source instead of blocking completion.', 'tahmir-ai-to-wp-converter');
        $warnings = is_array($job['warnings'] ?? null) ? $job['warnings'] : [];
        $warnings[] = $warning;

        return array_merge($job, [
            'status' => 'failed',
            'progress' => 100,
            'message' => __('This clone stopped making progress and was safely released. You can retry it now; no incomplete page was published.', 'tahmir-ai-to-wp-converter'),
            'warnings' => array_values(array_unique($warnings)),
            'retryable' => true,
            'stale_detected_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Persist a browser-rendered SPA shell as ordinary editable HTML.
     *
     * Lovable and similar builders ship an almost-empty #root plus a JavaScript
     * bundle. The clone preview runs only the exact downloaded scripts recorded
     * in Tahmir's hash manifest; the wizard then posts the rendered document
     * here. Active content is removed again before the snapshot is stored, so
     * the normal visual editor never has to execute imported application code.
     */
    public function ajax_materialize_client_shell()
    {
        $this->verify_nonce();
        @set_time_limit(240);

        $wp_page_id = absint($_POST['wp_page_id'] ?? 0);
        $ugentic_page_id = absint($_POST['ugentic_id'] ?? 0);
        $html = wp_unslash($_POST['html'] ?? '');

        if (!$wp_page_id || !$ugentic_page_id || !current_user_can('edit_post', $wp_page_id)) {
            wp_send_json_error(['message' => __('The imported page could not be verified.', 'tahmir-ai-to-wp-converter')], 403);
        }
        if (!is_string($html) || strlen($html) < 500 || strlen($html) > 8 * MB_IN_BYTES) {
            wp_send_json_error(['message' => __('The rendered page snapshot was empty or too large.', 'tahmir-ai-to-wp-converter')], 400);
        }

        $database = ugentic_builder()->database;
        $page = $database->get_page($ugentic_page_id);
        if (!$page || absint($page->wp_page_id ?? 0) !== $wp_page_id) {
            wp_send_json_error(['message' => __('The Tahmir page record did not match the WordPress page.', 'tahmir-ai-to-wp-converter')], 404);
        }

        $meta = json_decode((string) ($page->meta_data ?? ''), true);
        $meta = is_array($meta) ? $meta : [];
        $zip_capture = Tahmir_Rendered_Import::needs_capture($page);
        if (!empty($meta['client_shell_materialized'])) { wp_send_json_error(['message'=>__('This page is already prepared. Reload the editor.', 'tahmir-ai-to-wp-converter')],409); }
        if ($zip_capture && !hash_equals(hash('sha256', $page->current_html), sanitize_text_field(wp_unslash($_POST['snapshot_baseline'] ?? '')))) { wp_send_json_error(['message'=>__('This page changed while it was being prepared. Reload and try again.', 'tahmir-ai-to-wp-converter')],409); }
        if (empty($meta['client_rendered_shell']) && !$zip_capture) {
            wp_send_json_error(['message' => __('This page is not a client-rendered import.', 'tahmir-ai-to-wp-converter')], 409);
        }

        if (!$this->client_shell_snapshot_is_substantive($html)) {
            wp_send_json_error(['message' => __('The client application had not finished rendering yet.', 'tahmir-ai-to-wp-converter')], 422);
        }

        if (class_exists('Ugentic_Security')) {
            $html = Ugentic_Security::strip_imported_active_content($html);
        } else {
            $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script\s*>/i', '', $html);
        }

        // Remove the source-builder badge after it has served its purpose in the
        // temporary runtime preview. The selector is intentionally exact.
        $html = preg_replace(
            '/\s*<aside\b(?=[^>]*\bid=(["\'])lovable-badge\1)[^>]*>[\s\S]*?<\/aside\s*>/i',
            '',
            $html
        );

        if (class_exists('Ugentic_HTML_Cleaner')) {
            $html = Ugentic_HTML_Cleaner::scrub_admin_bar_artifacts($html);
            $html = Ugentic_HTML_Cleaner::strip_editor_markers($html);
            $html = Ugentic_HTML_Cleaner::materialize_static_snapshot($html);
        }

        if (preg_match('/<form\b|\[ugentic_form\b/i', $page->current_html)) { Tahmir_Free_Form_Policy::validate($page->current_html, $html); }
        $source_url = (string) ($meta['clone_source_url'] ?? get_post_meta($wp_page_id, '_ugentic_clone_source_url', true));
        $snapshot_assets = [
            'html' => $html,
            'assets_downloaded' => 0,
            'assets_failed' => 0,
            'warnings' => [],
        ];
        $importer = ugentic_builder()->importer ?? null;
        $rendered_links = $importer && method_exists($importer, 'remember_client_snapshot_links')
            ? $importer->remember_client_snapshot_links($html, $source_url) : [];
        $meta['clone_rendered_discovered_pages'] = $rendered_links;
        if ($importer && method_exists($importer, 'localize_client_shell_snapshot_assets')) {
            $snapshot_assets = $importer->localize_client_shell_snapshot_assets(
                $html,
                $source_url,
                (string) ($meta['clone_asset_namespace'] ?? ''),
                (string) ($meta['clone_source_type'] ?? 'auto')
            );
            if (!empty($snapshot_assets['html'])) {
                $html = $snapshot_assets['html'];
            }
        }
        if (method_exists($database, 'repair_internal_links_for_render')) {
            $html = $database->repair_internal_links_for_render($html, $source_url);
        }

        if (!$this->client_shell_snapshot_is_substantive($html)) {
            wp_send_json_error(['message' => __('The safe editable snapshot did not contain enough rendered page content.', 'tahmir-ai-to-wp-converter')], 422);
        }

        // Initial SPA HTML has no controls. Register the rendered form now,
        // while the importing user's ownership and complete DOM are available.
        if (class_exists('Ugentic_Form_Converter') && empty($meta['ai_assisted_clone'])) {
            if (class_exists('Ugentic_Form_Engine')) {
                $html = Ugentic_Form_Engine::materialize_static_controls($html);
            }
            $forms = (new Ugentic_Form_Converter())->detect_and_register_forms($html);
            $html = $forms['html'];
            $meta['form_ids'] = array_values(array_unique(array_merge((array) ($meta['form_ids'] ?? []), $forms['form_ids'])));
            $meta['forms_created'] = (int) ($meta['forms_created'] ?? 0) + $forms['forms_created'];
        }
        $ai_snapshot = [];
        if (!empty($meta['ai_assisted_clone']) && $importer && method_exists($importer, 'enhance_ai_assisted_snapshot')) {
            $ai_snapshot = $importer->enhance_ai_assisted_snapshot($html, $source_url, $meta);
            if (!empty($ai_snapshot['html'])) {
                $html = $ai_snapshot['html'];
            }
            $meta['form_ids'] = array_values(array_unique(array_merge((array) ($meta['form_ids'] ?? []), (array) ($ai_snapshot['form_ids'] ?? []))));
            $meta['forms_created'] = (int) ($meta['forms_created'] ?? 0) + intval($ai_snapshot['forms_created'] ?? 0);
            $meta['form_audit'] = $ai_snapshot['form_audit'] ?? ($meta['form_audit'] ?? []);
            $meta['section_separation_status'] = sanitize_key($ai_snapshot['section_separation_status'] ?? 'not_found');
        }
        $meta['client_rendered_shell'] = true;
        $meta['client_shell_materialized'] = true;
        $meta['client_shell_materialized_at'] = current_time('mysql');
        $meta['client_shell_snapshot_bytes'] = strlen($html);
        $meta['client_shell_runtime_removed'] = true;
        $meta['client_shell_assets_downloaded'] = intval($snapshot_assets['assets_downloaded'] ?? 0);
        $meta['client_shell_assets_failed'] = intval($snapshot_assets['assets_failed'] ?? 0);
        $meta['client_shell_asset_warnings'] = array_values(array_slice((array) ($snapshot_assets['warnings'] ?? []), 0, 20));

        $page_update = [
            'current_html' => $html,
            'meta_data' => wp_json_encode($meta),
        ];
        if (!empty($ai_snapshot['global_header_id'])) {
            $page_update['global_header_id'] = absint($ai_snapshot['global_header_id']);
        }
        if (!empty($ai_snapshot['global_footer_id'])) {
            $page_update['global_footer_id'] = absint($ai_snapshot['global_footer_id']);
        }
        $updated = $database->update_page($ugentic_page_id, $page_update);
        if ($updated === false) {
            wp_send_json_error(['message' => __('Tahmir could not save the editable page snapshot.', 'tahmir-ai-to-wp-converter')], 500);
        }

        delete_post_meta($wp_page_id, '_ugentic_imported_script_manifest_v1');

        wp_send_json_success([
            'wp_page_id' => $wp_page_id,
            'ugentic_id' => $ugentic_page_id,
            'bytes' => strlen($html),
            'client_shell_materialized' => true,
            'discovered_pages' => $rendered_links,
            'assets_downloaded' => intval($snapshot_assets['assets_downloaded'] ?? 0),
            'assets_failed' => intval($snapshot_assets['assets_failed'] ?? 0),
            'warnings' => array_values(array_slice(array_merge((array) ($snapshot_assets['warnings'] ?? []), (array) ($ai_snapshot['warnings'] ?? [])), 0, 20)),
            'ai_assisted' => !empty($meta['ai_assisted_clone']),
            'global_header_id' => absint($ai_snapshot['global_header_id'] ?? 0),
            'global_footer_id' => absint($ai_snapshot['global_footer_id'] ?? 0),
            'form_audit' => $ai_snapshot['form_audit'] ?? [],
        ]);
    }

    /**
     * Confirm that the app root contains real rendered layout rather than the
     * source's empty bootstrap element.
     */
    private function client_shell_snapshot_is_substantive($html)
    {
        if (!is_string($html) || stripos($html, '<html') === false) {
            return false;
        }

        if (!class_exists('DOMDocument')) {
            $probe = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script\s*>/i', '', $html);
            $probe = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style\s*>/i', '', $probe);
            $text = preg_replace('/\s+/u', ' ', trim(wp_strip_all_tags($probe)));
            $tags = preg_match_all('/<[a-z][a-z0-9:-]*\b/i', $probe);
            $images = preg_match_all('/<img\b/i', $probe);
            return $tags >= 12 && (strlen($text) >= 80 || ($images >= 3 && strlen($text) >= 20));
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $flags = defined('LIBXML_NONET') ? LIBXML_NONET : 0;
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, $flags);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return false;
        }

        $xpath = new DOMXPath($dom);
        $roots = $xpath->query('//*[@id="root" or @id="app" or @id="__next" or @id="__nuxt" or @id="___gatsby" or @id="gatsby-focus-wrapper" or @data-reactroot or @ng-version] | //app-root');
        if (!$roots || $roots->length < 1) {
            $roots = $xpath->query('//body');
        }
        if (!$roots || $roots->length < 1) {
            return false;
        }

        $root = $roots->item(0);
        foreach ($xpath->query('.//script | .//style | .//noscript', $root) as $active) {
            $active->parentNode->removeChild($active);
        }
        $headings = $xpath->query('//title | .//h1 | .//h2', $root);
        foreach ($headings as $heading) {
            $label = preg_replace('/\s+/u', ' ', trim((string) $heading->textContent));
            if (preg_match('/^(?:404(?:\s*[-:]\s*.*)?|page not found|not found|application error(?::.*)?|something went wrong|just a moment[.!]*|vercel security checkpoint|access denied)$/i', $label)) {
                return false;
            }
        }
        $descendants = $xpath->query('.//*', $root);
        $text = preg_replace('/\s+/u', ' ', trim((string) $root->textContent));
        $images = $xpath->query('.//img', $root);
        return ($descendants ? $descendants->length : 0) >= 12
            && (strlen($text) >= 80 || (($images ? $images->length : 0) >= 3 && strlen($text) >= 20));
    }

    private function create_clone_job($url, $options)
    {
        $job_id = 'ugclone_' . wp_generate_uuid4();
        $this->update_clone_job($job_id, [
            'job_id' => $job_id,
            'status' => 'queued',
            'progress' => 5,
            'message' => __('Clone queued. Preparing the background importer...', 'tahmir-ai-to-wp-converter'),
            'url' => esc_url_raw($url),
            'single_page' => !empty($options['single_page']),
            'max_pages' => intval($options['max_pages'] ?? 1),
            'started_at' => time(),
            'updated_at' => time(),
        ]);

        return $job_id;
    }

    private function schedule_clone_job($job_id, $url, $options)
    {
        $scheduled = wp_schedule_single_event(
            time(),
            'ugentic_wizard_run_clone_job',
            [$job_id, $url, $options],
            true
        );

        if (is_wp_error($scheduled)) {
            return $scheduled;
        }
        if (!$scheduled) {
            return new WP_Error(
                'ugentic_clone_schedule_failed',
                __('The clone could not be queued. Please try again.', 'tahmir-ai-to-wp-converter')
            );
        }

        // The normal wp_loaded cron check has already run by the time an AJAX
        // handler is reached, so explicitly kick the newly scheduled event.
        if (function_exists('spawn_cron')) {
            spawn_cron(time());
        }

        return true;
    }

    public function run_clone_job($job_id, $url, $options)
    {
        ignore_user_abort(true);
        @set_time_limit(0);
        $options = is_array($options) ? $options : [];
        $options['background_job'] = true;

        $job_finished = false;
        register_shutdown_function(function () use ($job_id, &$job_finished) {
            if ($job_finished) {
                return;
            }

            $error = error_get_last();
            $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!is_array($error) || !in_array(intval($error['type'] ?? 0), $fatal_types, true)) {
                return;
            }

            $this->update_clone_job($job_id, [
                'status' => 'failed',
                'progress' => 100,
                'message' => __('The background clone stopped unexpectedly. Please retry; no incomplete page was published.', 'tahmir-ai-to-wp-converter'),
                'updated_at' => time(),
            ]);
        });

        $options['progress_callback'] = function ($event) use ($job_id) {
            $event = is_array($event) ? $event : [];
            $this->update_clone_job($job_id, [
                'status' => 'running',
                'progress' => max(5, min(99, intval($event['progress'] ?? 15))),
                'phase' => sanitize_key($event['phase'] ?? 'running'),
                'message' => sanitize_text_field($event['message'] ?? __('Clone is running...', 'tahmir-ai-to-wp-converter')),
                'assets_downloaded' => intval($event['assets_downloaded'] ?? 0),
                'retry_attempt' => isset($event['attempt']) ? intval($event['attempt']) : null,
                'retry_attempts_total' => isset($event['attempts_total']) ? intval($event['attempts_total']) : null,
                'retry_reason' => isset($event['reason']) ? sanitize_text_field($event['reason']) : '',
                'page_index' => isset($event['page_index']) ? intval($event['page_index']) : null,
                'pages_total' => isset($event['pages_total']) ? intval($event['pages_total']) : null,
                'last_event' => $event,
                'updated_at' => time(),
            ]);
        };

        $this->update_clone_job($job_id, [
            'status' => 'running',
            'progress' => 15,
            'message' => __('Fetching and importing the source website...', 'tahmir-ai-to-wp-converter'),
            'phase' => 'fetch_source',
            'updated_at' => time(),
        ]);

        try {
            $result = $this->run_clone_import($url, $options);
            if (empty($result['success'])) {
                $this->update_clone_job($job_id, [
                    'status' => 'failed',
                    'progress' => 100,
                    'message' => $result['message'] ?? __('Faithful import failed.', 'tahmir-ai-to-wp-converter'),
                    'warnings' => $result['warnings'] ?? [],
                    'result' => $result,
                    'updated_at' => time(),
                ]);
                $job_finished = true;
                return;
            }

            $this->update_clone_job($job_id, [
                'status' => 'complete',
                'progress' => 100,
                'message' => __('Clone import complete.', 'tahmir-ai-to-wp-converter'),
                'result' => $result,
                'updated_at' => time(),
                'completed_at' => time(),
            ]);
            $job_finished = true;
        } catch (Throwable $e) {
            $this->update_clone_job($job_id, [
                'status' => 'failed',
                'progress' => 100,
                'message' => $e->getMessage(),
                'updated_at' => time(),
            ]);
            $job_finished = true;
        }
    }

    private function run_clone_import($url, $options)
    {
        if (!class_exists('Ugentic_Importer')) {
            return [
                'success' => false,
                'message' => __('Faithful importer is not available.', 'tahmir-ai-to-wp-converter'),
            ];
        }

        $importer = ugentic_builder()->importer ?? new Ugentic_Importer();
        return $importer->import_remote_site_faithful($url, $options);
    }

    private function update_clone_job($job_id, $data)
    {
        $existing = get_transient($this->clone_job_key($job_id));
        if (!is_array($existing)) {
            $existing = [];
        }

        set_transient($this->clone_job_key($job_id), array_merge($existing, $data), 2 * HOUR_IN_SECONDS);
    }

    private function clone_job_key($job_id)
    {
        return 'ugentic_wizard_clone_' . sanitize_key($job_id);
    }

    private function send_clone_job_accepted($job_id, $single_page)
    {
        $response = [
            'success' => true,
            'data' => $this->clone_job_accepted_data($job_id, $single_page),
        ];

        if (!headers_sent()) {
            status_header(202);
            nocache_headers();
            header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        }

        echo wp_json_encode($response);
        fastcgi_finish_request();
    }

    private function clone_job_accepted_data($job_id, $single_page)
    {
        return [
            'mode' => 'faithful_clone_job',
            'job_id' => $job_id,
            'status' => 'queued',
            'phase' => 'queued',
            'progress' => 5,
            'started_at' => time(),
            'message' => $single_page
                ? __('Page clone started in the background. You can keep working while it completes.', 'tahmir-ai-to-wp-converter')
                : __('Website clone started in the background. You can keep working while it completes.', 'tahmir-ai-to-wp-converter'),
        ];
    }
}
