<?php
/**
 * Database operations for Tahmir AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Database
{

    /**
     * Database version
     */
    const DB_VERSION = '3.5.2';

    /**
     * Table names
     */
    private $pages_table;
    private $assets_table;
    private $page_assets_table;
    private $history_table;
    private $revision_batches_table;
    private $revision_items_table;
    private $projects_table;
    private $project_pages_table;
    private $api_usage_table;
    private $forms_table;
    private $form_entries_table;
    private $form_logs_table;
    private $wizard_conversations_table;
    private $global_sections_table;
    private $global_section_links_table;
    private $bulk_conversations_table;
    private $agency_clients_table;
    private $agency_groups_table;
    private $agency_api_keys_table;
    private $agency_credit_log_table;
    private $agency_client_groups_table;
    private $ai_usage_log_table;
    private static $wizard_columns_checked = false;
    private static $revision_tables_checked = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->pages_table = $wpdb->prefix . 'ugentic_pages';
        $this->assets_table = $wpdb->prefix . 'ugentic_assets';
        $this->page_assets_table = $wpdb->prefix . 'ugentic_page_assets';
        $this->history_table = $wpdb->prefix . 'ugentic_history';
        $this->revision_batches_table = $wpdb->prefix . 'ugentic_revision_batches';
        $this->revision_items_table = $wpdb->prefix . 'ugentic_revision_items';
        $this->projects_table = $wpdb->prefix . 'ugentic_projects';
        $this->project_pages_table = $wpdb->prefix . 'ugentic_project_pages';
        $this->api_usage_table = $wpdb->prefix . 'ugentic_api_usage';
        $this->forms_table = $wpdb->prefix . 'ugentic_forms';
        $this->form_entries_table = $wpdb->prefix . 'ugentic_form_entries';
        $this->form_logs_table = $wpdb->prefix . 'ugentic_form_logs';
        $this->wizard_conversations_table = $wpdb->prefix . 'ugentic_wizard_conversations';
        $this->global_sections_table = $wpdb->prefix . 'ugentic_global_sections';
        $this->global_section_links_table = $wpdb->prefix . 'ugentic_global_section_links';
        $this->bulk_conversations_table = $wpdb->prefix . 'ugentic_bulk_conversations';
        $this->agency_clients_table = $wpdb->prefix . 'ugentic_agency_clients';
        $this->agency_groups_table = $wpdb->prefix . 'ugentic_agency_groups';
        $this->agency_api_keys_table = $wpdb->prefix . 'ugentic_agency_api_keys';
        $this->agency_credit_log_table = $wpdb->prefix . 'ugentic_agency_credit_log';
        $this->agency_client_groups_table = $wpdb->prefix . 'ugentic_agency_client_groups';
        $this->ai_usage_log_table = $wpdb->prefix . 'ugentic_ai_usage_log';
    }

    /** Accept only real calendar dates for prepared form-entry filters. */
    private static function valid_date_filter($value)
    {
        $date = (string) $value;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return '';
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $date : '';
    }

    /**
     * Create database tables
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Pages table
        $sql_pages = "CREATE TABLE IF NOT EXISTS {$this->pages_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wp_page_id bigint(20) unsigned NOT NULL,
            original_html longtext,
            current_html longtext,
            global_header_id bigint(20) unsigned DEFAULT NULL,
            global_footer_id bigint(20) unsigned DEFAULT NULL,
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY wp_page_id (wp_page_id)
        ) $charset_collate;";

        // Assets table (CSS, JS, Header, Footer)
        $sql_assets = "CREATE TABLE IF NOT EXISTS {$this->assets_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type enum('css','js','header','footer') NOT NULL,
            name varchar(255) NOT NULL,
            content longtext,
            file_path varchar(500) DEFAULT NULL,
            version varchar(50) DEFAULT '1.0.0',
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Page-Assets junction table
        $sql_page_assets = "CREATE TABLE IF NOT EXISTS {$this->page_assets_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id bigint(20) unsigned NOT NULL,
            asset_id bigint(20) unsigned NOT NULL,
            load_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY page_id (page_id),
            KEY asset_id (asset_id),
            UNIQUE KEY page_asset (page_id, asset_id)
        ) $charset_collate;";

        // History table for version tracking
        $sql_history = "CREATE TABLE IF NOT EXISTS {$this->history_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id bigint(20) unsigned NOT NULL,
            action varchar(100) NOT NULL,
            element_selector varchar(500) DEFAULT NULL,
            old_content longtext,
            new_content longtext,
            prompt text,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_id (page_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Sequential revision batches for page, asset, option, and sitewide rollback.
        $sql_revision_batches = "CREATE TABLE IF NOT EXISTS {$this->revision_batches_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id varchar(64) NOT NULL,
            label varchar(255) NOT NULL,
            source varchar(100) NOT NULL,
            scope varchar(50) NOT NULL DEFAULT 'page',
            status varchar(50) NOT NULL DEFAULT 'completed',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            restored_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY batch_id (batch_id),
            KEY source (source),
            KEY scope (scope),
            KEY status (status),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql_revision_items = "CREATE TABLE IF NOT EXISTS {$this->revision_items_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id varchar(64) NOT NULL,
            history_id bigint(20) unsigned DEFAULT 0,
            target_type varchar(50) NOT NULL DEFAULT 'page',
            target_id varchar(191) NOT NULL DEFAULT '',
            page_id bigint(20) unsigned DEFAULT 0,
            wp_page_id bigint(20) unsigned DEFAULT 0,
            old_content longtext,
            new_content longtext,
            old_meta longtext,
            new_meta longtext,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY batch_id (batch_id),
            KEY history_id (history_id),
            KEY target_type (target_type),
            KEY target_id (target_id),
            KEY page_id (page_id),
            KEY wp_page_id (wp_page_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Projects table (wizard sessions) — v2.0 expanded schema
        $sql_projects = "CREATE TABLE IF NOT EXISTS {$this->projects_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            business_type varchar(100),
            tagline varchar(500) DEFAULT NULL,
            target_audience text,
            unique_selling_points text,
            business_goals text,
            tone_of_voice varchar(100) DEFAULT 'professional',
            competitors text,
            services_products text,
            contact_info text,
            logo_url varchar(500) DEFAULT NULL,
            logo_prompts_history longtext,
            brand_colors text,
            primary_color varchar(20) DEFAULT NULL,
            secondary_color varchar(20) DEFAULT NULL,
            typography_preference varchar(100) DEFAULT NULL,
            design_style varchar(50),
            sitemap longtext,
            compliance_data longtext,
            design_dna longtext,
            wizard_state longtext,
            image_placeholders longtext,
            generated_images longtext,
            ai_brief longtext,
            design_research longtext,
            design_tokens longtext,
            asset_manifest longtext,
            layout_selections longtext,
            pipeline_status varchar(50) DEFAULT NULL,
            pipeline_log longtext,
            status varchar(50) DEFAULT 'discovery',
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        // Project pages table — v2.0 expanded schema
        $sql_project_pages = "CREATE TABLE IF NOT EXISTS {$this->project_pages_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            page_name varchar(255) NOT NULL,
            page_slug varchar(255),
            page_type varchar(50),
            page_description text,
            section_outline longtext,
            copy_content longtext,
            copy_status enum('pending','approved','revision_needed') DEFAULT 'pending',
            seo_meta text,
            compliance_sections text,
            generated_html longtext,
            generation_status enum('pending','generating','complete','failed') DEFAULT 'pending',
            image_slots longtext,
            revision_notes longtext,
            wp_page_id bigint(20) unsigned DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY copy_status (copy_status),
            KEY generation_status (generation_status)
        ) $charset_collate;";

        // Wizard conversations table — stores conversational history per step
        $sql_wizard_conversations = "CREATE TABLE IF NOT EXISTS {$this->wizard_conversations_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            step_name varchar(50) NOT NULL,
            role varchar(20) NOT NULL DEFAULT 'user',
            message longtext NOT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id_step (project_id, step_name),
            KEY created_at (created_at)
        ) $charset_collate;";

        // API usage tracking table
        $sql_api_usage = "CREATE TABLE IF NOT EXISTS {$this->api_usage_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action_type varchar(50) NOT NULL,
            model_used varchar(100),
            tokens_input int(11) DEFAULT 0,
            tokens_output int(11) DEFAULT 0,
            tokens_total int(11) DEFAULT 0,
            cost_estimate decimal(10,6) DEFAULT 0,
            project_id bigint(20) unsigned DEFAULT NULL,
            page_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL,
            request_data longtext,
            response_summary text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action_type (action_type),
            KEY user_id (user_id),
            KEY project_id (project_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Forms table
        $sql_forms = "CREATE TABLE IF NOT EXISTS {$this->forms_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            fields longtext NOT NULL,
            settings longtext,
            notifications longtext,
            confirmations longtext,
            style_settings longtext,
            submissions_count int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Form entries/submissions table
        $sql_form_entries = "CREATE TABLE IF NOT EXISTS {$this->form_entries_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            entry_data longtext NOT NULL,
            user_ip varchar(45),
            user_agent text,
            page_url varchar(500),
            is_read tinyint(1) DEFAULT 0,
            is_starred tinyint(1) DEFAULT 0,
            is_spam tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY is_read (is_read),
            KEY is_starred (is_starred),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Form logs table (email & webhook logs)
        $sql_form_logs = "CREATE TABLE IF NOT EXISTS {$this->form_logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            entry_id bigint(20) unsigned DEFAULT NULL,
            type enum('email','webhook','gohighlevel') NOT NULL,
            destination varchar(500),
            status enum('success','error','pending','queued') DEFAULT 'pending',
            message text,
            response_code int(5),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Global sections table
        $sql_global_sections = "CREATE TABLE IF NOT EXISTS {$this->global_sections_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            section_html longtext NOT NULL,
            section_hash varchar(64) NOT NULL,
            fingerprint_json longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY section_hash (section_hash)
        ) $charset_collate;";

        // Global section links (maps global sections to pages)
        $sql_global_section_links = "CREATE TABLE IF NOT EXISTS {$this->global_section_links_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            global_section_id bigint(20) unsigned NOT NULL,
            page_id bigint(20) unsigned NOT NULL,
            section_index int(11) NOT NULL DEFAULT 0,
            section_uid varchar(64) DEFAULT NULL,
            section_locator longtext DEFAULT NULL,
            section_fingerprint longtext DEFAULT NULL,
            match_confidence smallint DEFAULT 100,
            last_verified_at datetime DEFAULT NULL,
            is_detached tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY global_section_id (global_section_id),
            KEY page_id (page_id),
            UNIQUE KEY section_page (global_section_id, page_id, section_index)
        ) $charset_collate;";

        // Bulk conversations table — stores All Pages AI chat history
        $sql_bulk_conversations = "CREATE TABLE IF NOT EXISTS {$this->bulk_conversations_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            messages longtext NOT NULL,
            page_ids longtext DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        // Agency clients table
        $sql_agency_clients = "CREATE TABLE IF NOT EXISTS {$this->agency_clients_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) DEFAULT NULL,
            company varchar(255) DEFAULT NULL,
            website_url varchar(500) DEFAULT NULL,
            status enum('active','suspended','archived') DEFAULT 'active',
            credit_limit int(11) DEFAULT 0,
            credits_used int(11) DEFAULT 0,
            api_key_ids longtext DEFAULT NULL,
            notes text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            last_active_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY email (email)
        ) $charset_collate;";

        // Agency groups table
        $sql_agency_groups = "CREATE TABLE IF NOT EXISTS {$this->agency_groups_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            color varchar(20) DEFAULT '#fb923c',
            icon varchar(50) DEFAULT 'dashicons-groups',
            description text DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Agency API keys vault
        $sql_agency_api_keys = "CREATE TABLE IF NOT EXISTS {$this->agency_api_keys_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            label varchar(255) NOT NULL,
            provider enum('gemini','anthropic','openai','deepseek') NOT NULL DEFAULT 'gemini',
            encrypted_key text NOT NULL,
            model_preference varchar(100) DEFAULT '',
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            monthly_budget decimal(10,2) DEFAULT 0,
            monthly_spent decimal(10,2) DEFAULT 0,
            priority int(11) DEFAULT 0,
            last_used_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY provider (provider),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Agency credit log (per-client usage tracking)
        $sql_agency_credit_log = "CREATE TABLE IF NOT EXISTS {$this->agency_credit_log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            credits_used int(11) DEFAULT 0,
            action_type varchar(50) NOT NULL,
            model_used varchar(100) DEFAULT NULL,
            tokens_total int(11) DEFAULT 0,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Agency client-group junction (many-to-many)
        $sql_agency_client_groups = "CREATE TABLE IF NOT EXISTS {$this->agency_client_groups_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            group_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY group_id (group_id),
            UNIQUE KEY client_group (client_id, group_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql_pages);
        dbDelta($sql_assets);
        dbDelta($sql_page_assets);
        dbDelta($sql_history);
        dbDelta($sql_revision_batches);
        dbDelta($sql_revision_items);
        dbDelta($sql_projects);
        dbDelta($sql_project_pages);
        dbDelta($sql_api_usage);
        dbDelta($sql_forms);
        dbDelta($sql_form_entries);
        dbDelta($sql_wizard_conversations);
        dbDelta($sql_form_logs);
        dbDelta($sql_global_sections);
        dbDelta($sql_global_section_links);
        dbDelta($sql_bulk_conversations);
        dbDelta($sql_agency_clients);
        dbDelta($sql_agency_groups);
        dbDelta($sql_agency_api_keys);
        dbDelta($sql_agency_credit_log);
        dbDelta($sql_agency_client_groups);
        // AI usage log (detailed per-request tracking)
        $sql_ai_usage_log = "CREATE TABLE IF NOT EXISTS {$this->ai_usage_log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned DEFAULT 0,
            provider varchar(20) NOT NULL,
            model varchar(100) NOT NULL,
            task_type varchar(50) NOT NULL,
            input_tokens int(11) DEFAULT 0,
            output_tokens int(11) DEFAULT 0,
            total_tokens int(11) DEFAULT 0,
            cost_usd decimal(10,6) DEFAULT 0,
            latency_ms int(11) DEFAULT 0,
            success tinyint(1) DEFAULT 1,
            error_message text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_client_date (client_id, created_at),
            KEY idx_provider_date (provider, created_at),
            KEY idx_task (task_type, created_at)
        ) $charset_collate;";

        dbDelta($sql_ai_usage_log);

        $this->ensure_revision_tables();
        $this->ensure_wizard_columns();
        $this->remove_duplicated_faithful_form_markup();

        update_option('ugentic_db_version', self::DB_VERSION);
    }

    /**
     * Auto-upgrade: run create_tables if the stored DB version is behind code.
     * Safe to call on every request — dbDelta is idempotent.
     */
    public function maybe_upgrade()
    {
        $installed = get_option('ugentic_db_version', '0');
        if (version_compare($installed, self::DB_VERSION, '<')) {
            $this->create_tables();
        }

        $this->ensure_form_log_schema();

        // Some existing Local/production installs already have the current DB
        // version option but were created before the wizard persistence columns
        // existed. Repair those columns defensively so Studio state can persist.
        $this->ensure_revision_tables();
        $this->ensure_wizard_columns();
    }

    /**
     * Ensure existing installations have the wizard/canvas persistence columns.
     */
    private function ensure_revision_tables()
    {
        global $wpdb;

        if (self::$revision_tables_checked) {
            return;
        }
        self::$revision_tables_checked = true;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $sql_batches = "CREATE TABLE IF NOT EXISTS {$this->revision_batches_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id varchar(64) NOT NULL,
            label varchar(255) NOT NULL,
            source varchar(100) NOT NULL,
            scope varchar(50) NOT NULL DEFAULT 'page',
            status varchar(50) NOT NULL DEFAULT 'completed',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            restored_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY batch_id (batch_id),
            KEY source (source),
            KEY scope (scope),
            KEY status (status),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql_items = "CREATE TABLE IF NOT EXISTS {$this->revision_items_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id varchar(64) NOT NULL,
            history_id bigint(20) unsigned DEFAULT 0,
            target_type varchar(50) NOT NULL DEFAULT 'page',
            target_id varchar(191) NOT NULL DEFAULT '',
            page_id bigint(20) unsigned DEFAULT 0,
            wp_page_id bigint(20) unsigned DEFAULT 0,
            old_content longtext,
            new_content longtext,
            old_meta longtext,
            new_meta longtext,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY batch_id (batch_id),
            KEY history_id (history_id),
            KEY target_type (target_type),
            KEY target_id (target_id),
            KEY page_id (page_id),
            KEY wp_page_id (wp_page_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_batches);
        dbDelta($sql_items);
    }

    /**
     * Ensure existing installations have the wizard/canvas persistence columns.
     */
    private function ensure_wizard_columns()
    {
        global $wpdb;

        if (self::$wizard_columns_checked) {
            return;
        }
        self::$wizard_columns_checked = true;

        $project_columns = [
            'tagline' => "varchar(500) DEFAULT NULL",
            'target_audience' => "text",
            'unique_selling_points' => "text",
            'business_goals' => "text",
            'tone_of_voice' => "varchar(100) DEFAULT 'professional'",
            'competitors' => "text",
            'services_products' => "text",
            'contact_info' => "text",
            'logo_url' => "varchar(500) DEFAULT NULL",
            'logo_prompts_history' => "longtext",
            'primary_color' => "varchar(20) DEFAULT NULL",
            'secondary_color' => "varchar(20) DEFAULT NULL",
            'typography_preference' => "varchar(100) DEFAULT NULL",
            'compliance_data' => "longtext",
            'design_dna' => "longtext",
            'wizard_state' => "longtext",
            'image_placeholders' => "longtext",
            'generated_images' => "longtext",
            'ai_brief' => "longtext",
            'design_research' => "longtext",
            'design_tokens' => "longtext",
            'asset_manifest' => "longtext",
            'layout_selections' => "longtext",
            'pipeline_status' => "varchar(50) DEFAULT NULL",
            'pipeline_log' => "longtext",
        ];

        $page_columns = [
            'section_outline' => "longtext",
            'seo_meta' => "text",
            'compliance_sections' => "text",
            'image_slots' => "longtext",
            'revision_notes' => "longtext",
        ];

        $this->add_missing_columns($this->projects_table, $project_columns);
        $this->add_missing_columns($this->project_pages_table, $page_columns);
    }

    /**
     * Add missing columns without changing existing data.
     */
    private function add_missing_columns($table, $definitions)
    {
        global $wpdb;

        $existing = $wpdb->get_col("DESC {$table}", 0);
        if (empty($existing)) {
            return;
        }

        $existing = array_flip($existing);
        foreach ($definitions as $column => $definition) {
            if (isset($existing[$column])) {
                continue;
            }

            $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    /**
     * Older faithful imports copied the complete original form markup into form
     * settings. The imported page HTML already owns that preserved markup, so
     * forms only need lightweight metadata.
     */
    private function remove_duplicated_faithful_form_markup()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, settings FROM {$this->forms_table} WHERE settings LIKE '%\"original_form_html\"%'"
        );

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $settings = json_decode($row->settings, true);
            if (!is_array($settings) || !array_key_exists('original_form_html', $settings)) {
                continue;
            }

            unset($settings['original_form_html']);
            $wpdb->update(
                $this->forms_table,
                ['settings' => wp_json_encode($settings)],
                ['id' => (int) $row->id],
                ['%s'],
                ['%d']
            );
        }
    }

    /**
     * Get table name
     */

    // ==================== PAGE OPERATIONS ====================

    /**
     * Enforce editor-neutral current_html at the lowest shared database boundary.
     */
    private function prepare_page_storage_data($data)
    {
        if (!is_array($data) || !array_key_exists('current_html', $data) || !class_exists('Ugentic_HTML_Cleaner')) {
            return $data;
        }

        // Old clone builds could add Tahmir's own compatibility runtime before
        // this shared storage boundary. Those nodes are render-time helpers,
        // not editable page content, and the cleaner intentionally classifies
        // them as hard editor/runtime artifacts. Strip the known legacy nodes
        // before validation so a valid import is not rejected by code Tahmir
        // itself injected. Unknown editor/runtime markup still fails closed.
        $html = Ugentic_HTML_Cleaner::strip_legacy_static_runtime((string) $data['current_html']);
        $prepared = Ugentic_HTML_Cleaner::prepare_persisted_html($html);
        if (empty($prepared['ok'])) {
            $artifacts = implode(', ', $prepared['artifacts'] ?? []);
            do_action('ugentic_page_storage_rejected', $artifacts, $data);
            return false;
        }

        $data['current_html'] = $prepared['html'];
        return $data;
    }

    /**
     * Insert a new page
     */
    public function insert_page($data)
    {
        global $wpdb;

        $defaults = [
            'wp_page_id' => 0,
            'original_html' => '',
            'current_html' => '',
            'global_header_id' => null,
            'global_footer_id' => null,
            'meta_data' => '',
        ];

        $data = wp_parse_args($data, $defaults);
        $data = $this->prepare_page_storage_data($data);
        if ($data === false) {
            return false;
        }

        $inserted = $wpdb->insert($this->pages_table, $data);
        if ($inserted === false) {
            return false;
        }

        $insert_id = $wpdb->insert_id;

        /**
         * Fires after a Tahmir AI page record is inserted.
         *
         * @param int   $insert_id Tahmir AI page ID.
         * @param array $data      Inserted page data.
         */
        do_action('ugentic_page_inserted', $insert_id, $data);

        return $insert_id;
    }

    /**
     * Get page by WordPress page ID
     */
    public function get_page_by_wp_id($wp_page_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->pages_table} WHERE wp_page_id = %d",
                $wp_page_id
            )
        );
    }

    /**
     * Get page by ID
     */
    public function get_page($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->pages_table} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Update page
     */
    public function update_page($id, $data)
    {
        global $wpdb;

        $data = $this->prepare_page_storage_data($data);
        if ($data === false) {
            return false;
        }

        $old_page = $this->get_page($id);
        $result = $wpdb->update(
            $this->pages_table,
            $data,
            ['id' => $id]
        );

        if ($result !== false) {
            /**
             * Fires after a Tahmir AI page record is updated.
             *
             * @param int         $id       Tahmir AI page ID.
             * @param array       $data     Updated data.
             * @param object|null $old_page Previous page record.
             */
            do_action('ugentic_page_updated', $id, $data, $old_page);
        }

        return $result;
    }

    /**
     * Get all pages
     */
    public function count_pages()
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->pages_table}");
    }

    public function get_all_pages($args = [])
    {
        global $wpdb;

        $defaults = [
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);
        $order_sql = Ugentic_Security::sanitize_sql_order(
            $args['orderby'],
            $args['order'],
            [
                'id' => 'p.id',
                'created_at' => 'p.created_at',
                'updated_at' => 'p.updated_at',
                'wp_page_id' => 'p.wp_page_id',
                'post_title' => 'wp.post_title',
            ],
            'created_at'
        );
        $limit = Ugentic_Security::clamp_int($args['limit'], 1, 1000);
        $offset = Ugentic_Security::clamp_int($args['offset'], 0, 100000);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, wp.post_title
                FROM {$this->pages_table} p
                LEFT JOIN {$wpdb->posts} wp ON p.wp_page_id = wp.ID
                ORDER BY {$order_sql}
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Get page identity and import metadata without loading large HTML blobs.
     */
    public function get_page_summaries($args = [])
    {
        global $wpdb;

        $args = wp_parse_args($args, [
            'limit' => 1000,
            'offset' => 0,
        ]);
        $limit = Ugentic_Security::clamp_int($args['limit'], 1, 1000);
        $offset = Ugentic_Security::clamp_int($args['offset'], 0, 100000);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.wp_page_id, p.meta_data, OCTET_LENGTH(p.current_html) AS content_bytes, wp.post_name, wp.post_title
                FROM {$this->pages_table} p
                LEFT JOIN {$wpdb->posts} wp ON p.wp_page_id = wp.ID
                ORDER BY p.id ASC
                LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Delete page
     */
    public function delete_page($id)
    {
        global $wpdb;

        // Delete page assets links
        $wpdb->delete($this->page_assets_table, ['page_id' => $id]);

        // Delete history
        $wpdb->delete($this->history_table, ['page_id' => $id]);

        // Delete page
        return $wpdb->delete($this->pages_table, ['id' => $id]);
    }

    // ==================== ASSET OPERATIONS ====================

    /**
     * Insert asset
     */
    public function insert_asset($data)
    {
        global $wpdb;

        $defaults = [
            'type' => 'css',
            'name' => '',
            'content' => '',
            'file_path' => null,
            'version' => '1.0.0',
            'is_active' => 1,
        ];

        $data = wp_parse_args($data, $defaults);

        $wpdb->insert($this->assets_table, $data);

        return $wpdb->insert_id;
    }

    /**
     * Get asset by ID
     */
    public function get_asset($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->assets_table} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Get assets by type
     */
    public function get_assets_by_type($type, $active_only = true)
    {
        global $wpdb;

        if ($active_only) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->assets_table} WHERE type = %s AND is_active = 1 ORDER BY id ASC",
                $type
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->assets_table} WHERE type = %s ORDER BY id ASC",
            $type
        ));
    }

    /**
     * Find asset by name and type (for dedup)
     */

    /**
     * Update asset
     */
    public function update_asset($id, $data)
    {
        global $wpdb;

        // Increment version if content changed
        if (isset($data['content'])) {
            $current = $this->get_asset($id);
            if ($current && $current->content !== $data['content']) {
                $version_parts = explode('.', $current->version);
                $version_parts[2] = isset($version_parts[2]) ? intval($version_parts[2]) + 1 : 1;
                $data['version'] = implode('.', $version_parts);
            }
        }

        $result = $wpdb->update(
            $this->assets_table,
            $data,
            ['id' => $id]
        );

        // Clear any object cache to ensure fresh data on next load
        wp_cache_delete('ugentic_header_' . $id, 'ugentic');
        wp_cache_delete('ugentic_footer_' . $id, 'ugentic');

        return $result;
    }

    /**
     * Delete asset
     */
    public function delete_asset($id)
    {
        global $wpdb;

        // Remove from page-assets links
        $wpdb->delete($this->page_assets_table, ['asset_id' => $id]);

        // Delete asset
        return $wpdb->delete($this->assets_table, ['id' => $id]);
    }

    /**
     * Get global header
     */
    public function get_global_header()
    {
        global $wpdb;

        return $wpdb->get_row(
            "SELECT * FROM {$this->assets_table} WHERE type = 'header' AND is_active = 1 LIMIT 1"
        );
    }

    /**
     * Get global footer
     */
    public function get_global_footer()
    {
        global $wpdb;

        return $wpdb->get_row(
            "SELECT * FROM {$this->assets_table} WHERE type = 'footer' AND is_active = 1 LIMIT 1"
        );
    }

    /**
     * Update all internal links across header, footer, and all pages
     * Converts .html file links to WordPress permalinks
     * Call this after importing all pages
     */
    public function update_header_footer_links($original_base_url = '', $include_pages = true)
    {
        global $wpdb;

        // Build comprehensive link map from all imported pages
        $link_map = $this->build_link_map();

        if (empty($link_map))
            return;

        // AI-assisted cloning can create multiple page-specific header/footer
        // assets. Repair every active variant, not only the first global row.
        foreach (['header', 'footer'] as $asset_type) {
            $assets = $this->get_assets_by_type($asset_type, true);
            foreach ($assets as $asset) {
                if (empty($asset->content)) {
                    continue;
                }
                $updated_content = $this->replace_links_in_html($asset->content, $link_map, $original_base_url);
                if (class_exists('Ugentic_Menu_Manager')) {
                    $menu_repair_stats = [];
                    $updated_content = Ugentic_Menu_Manager::instance()->repair_imported_navigation_html(
                        $updated_content,
                        $menu_repair_stats
                    );
                }
                if ($updated_content !== $asset->content) {
                    $this->update_asset($asset->id, ['content' => $updated_content]);
                }
            }
        }

        if (!$include_pages) {
            return;
        }

        // Update page content one row at a time. Large Framer/AI imports can be
        // several megabytes each; loading the whole site into PHP exhausted the
        // default 128 MB limit at the end of otherwise successful clones.
        for ($offset = 0; ; $offset++) {
            $pages = $this->get_all_pages(['limit' => 1, 'offset' => $offset, 'orderby' => 'id', 'order' => 'ASC']);
            if (empty($pages)) {
                break;
            }
            $page = $pages[0];
            if (empty($page->current_html)) {
                continue;
            }
            $updated_html = $this->replace_links_in_html($page->current_html, $link_map, $original_base_url);
            if ($updated_html !== $page->current_html) {
                $this->update_page($page->id, ['current_html' => $updated_html]);
            }
            unset($pages, $page, $updated_html);
        }
    }

    /**
     * Build a comprehensive link map from all imported pages
     * Maps various forms of original links to WordPress permalinks
     */
    public function build_link_map()
    {
        global $wpdb;

        $link_map = [];

        // Get all Tahmir AI pages with their metadata
        $pages = $wpdb->get_results(
            "SELECT p.id, p.wp_page_id, p.meta_data, OCTET_LENGTH(p.current_html) AS content_bytes, wp.post_name, wp.post_title
             FROM {$this->pages_table} p
             LEFT JOIN {$wpdb->posts} wp ON p.wp_page_id = wp.ID
             WHERE p.wp_page_id IS NOT NULL"
        );

        foreach ($pages as $page) {
            if (!$page->wp_page_id)
                continue;

            $permalink = get_permalink($page->wp_page_id);
            $meta = json_decode($page->meta_data ?? '{}', true);
            $source_file = $meta['source_file'] ?? '';
            $source_candidates = [];

            // Map from source filename (e.g., "about.html")
            if (!empty($source_file)) {
                $link_map[$source_file] = $permalink;
                $link_map['./' . $source_file] = $permalink;
                $link_map['/' . $source_file] = $permalink;
                $link_map['../' . $source_file] = $permalink;

                // Without extension
                $name_without_ext = pathinfo($source_file, PATHINFO_FILENAME);
                $link_map[$name_without_ext . '.html'] = $permalink;
                $link_map[$name_without_ext . '.htm'] = $permalink;
                $source_candidates[] = $source_file;
            }

            if (!empty($meta['source_relative_path'])) {
                $source_candidates[] = $meta['source_relative_path'];
            }
            if (!empty($meta['source_aliases']) && is_array($meta['source_aliases'])) {
                $source_candidates = array_merge($source_candidates, $meta['source_aliases']);
            }
            if (!empty($meta['clone_source_url'])) {
                $clone_source_url = html_entity_decode((string) $meta['clone_source_url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $source_parts = wp_parse_url($clone_source_url);
                if (is_array($source_parts) && !empty($source_parts['host'])) {
                    $scheme = strtolower((string) ($source_parts['scheme'] ?? 'https'));
                    $host = strtolower((string) $source_parts['host']);
                    $port = !empty($source_parts['port']) ? ':' . absint($source_parts['port']) : '';
                    $path = '/' . ltrim((string) ($source_parts['path'] ?? '/'), '/');
                    $path = preg_replace('#/+#', '/', $path);
                    $absolute_source = $scheme . '://' . $host . $port . $path;
                    $link_map[$absolute_source] = $permalink;
                    $link_map[rtrim($absolute_source, '/')] = $permalink;
                    $link_map[$absolute_source . ($path === '/' ? '' : '/')] = $permalink;
                    if (strpos($host, 'www.') === 0) {
                        $link_map[$scheme . '://' . substr($host, 4) . $port . $path] = $permalink;
                    } else {
                        $link_map[$scheme . '://www.' . $host . $port . $path] = $permalink;
                    }
                }
                $source_path = wp_parse_url($clone_source_url, PHP_URL_PATH);
                if ($source_path) {
                    $source_candidates[] = rawurldecode($source_path);
                }
            }

            foreach (array_unique(array_filter($source_candidates)) as $source_candidate) {
                $normalized_path = ltrim(str_replace('\\', '/', $source_candidate), './');
                $basename = basename($normalized_path);
                $without_extension = preg_replace('/\.(?:html?|php|aspx?)$/i', '', $basename);
                $directory_aliases = [];
                if (preg_match('/^index\.(?:html?|php|aspx?)$/i', $basename)) {
                    $directory = trim(str_replace('\\', '/', dirname($normalized_path)), './');
                    if ($directory !== '') {
                        $directory_aliases = [$directory, $directory . '/'];
                    }
                }

                foreach (array_unique(array_filter(array_merge([
                    $normalized_path,
                    $basename,
                    $without_extension,
                    $without_extension . '.html',
                    $without_extension . '.htm',
                ], $directory_aliases))) as $candidate) {
                    $link_map[$candidate] = $permalink;
                    $link_map['./' . $candidate] = $permalink;
                    $link_map['/' . $candidate] = $permalink;
                }
            }

            // Map from post slug
            if (!empty($page->post_name)) {
                $link_map[$page->post_name . '.html'] = $permalink;
                $link_map[$page->post_name . '.htm'] = $permalink;
                $link_map['/' . $page->post_name . '.html'] = $permalink;
                $link_map['./' . $page->post_name . '.html'] = $permalink;
                $link_map[$page->post_name] = $permalink;

                $canonical_slug = preg_replace('/-\d+$/', '', $page->post_name);
                if ($canonical_slug !== $page->post_name) {
                    $link_map[$canonical_slug] = $permalink;
                    $link_map[$canonical_slug . '.html'] = $permalink;
                    $link_map[$canonical_slug . '.htm'] = $permalink;
                }
            }

            // Map from post title (lowercase, for matching)
            if (!empty($page->post_title)) {
                $title_slug = sanitize_title($page->post_title);
                $link_map[$title_slug . '.html'] = $permalink;
                $link_map[$title_slug . '.htm'] = $permalink;
                foreach ($this->build_title_link_aliases($page->post_title) as $title_alias) {
                    $link_map[$title_alias] = $permalink;
                    $link_map[$title_alias . '.html'] = $permalink;
                    $link_map[$title_alias . '.htm'] = $permalink;
                    $link_map['/' . $title_alias . '.html'] = $permalink;
                    $link_map['./' . $title_alias . '.html'] = $permalink;
                }
            }

            $import_role = (string) get_post_meta($page->wp_page_id, '_ugentic_import_role', true);
            if ($import_role === 'blog_listing') {
                foreach (['blog', 'blog.html', 'blog.htm', '/blog.html', './blog.html'] as $blog_alias) {
                    $link_map[$blog_alias] = $permalink;
                }
            }
        }

        $native_posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => 500,
            'meta_key' => '_ugentic_import_role',
            'meta_value' => 'blog_single',
        ]);

        foreach ($native_posts as $post) {
            $permalink = get_permalink($post);
            $source_file = get_post_meta($post->ID, '_ugentic_clone_source_file', true);
            $source_url = get_post_meta($post->ID, '_ugentic_clone_source_url', true);
            $candidates = [];

            if ($source_file) {
                $candidates[] = $source_file;
                $candidates[] = basename($source_file);
            }
            if ($source_url) {
                $path = wp_parse_url($source_url, PHP_URL_PATH);
                if ($path) {
                    $candidates[] = ltrim($path, '/');
                    $candidates[] = basename($path);
                }
            }
            if (!empty($post->post_name)) {
                $candidates[] = $post->post_name;
                $candidates[] = $post->post_name . '.html';
                $candidates[] = $post->post_name . '.htm';
            }

            foreach (array_unique(array_filter($candidates)) as $candidate) {
                $link_map[$candidate] = $permalink;
                $link_map['./' . $candidate] = $permalink;
                $link_map['/' . $candidate] = $permalink;

                $without_ext = preg_replace('/\.html?$/i', '', basename($candidate));
                if ($without_ext !== '') {
                    $link_map[$without_ext] = $permalink;
                    $link_map[$without_ext . '.html'] = $permalink;
                    $link_map[$without_ext . '.htm'] = $permalink;
                }
            }
        }

        // Handle index.html -> home
        $home_url = home_url('/');
        $link_map['index.html'] = $home_url;
        $link_map['./index.html'] = $home_url;
        $link_map['/index.html'] = $home_url;
        $link_map['index.htm'] = $home_url;

        $normalized_map = $link_map;
        foreach ($link_map as $original => $new_url) {
            $normalized_map[strtolower(html_entity_decode((string) $original, ENT_QUOTES | ENT_HTML5, 'UTF-8'))] = $new_url;
        }

        return $normalized_map;
    }

    /**
     * Build conservative aliases from the human page title. Imported sites
     * commonly append a brand after a dash or pipe while their files use only
     * the leading title, for example "Contact | Brand" -> contact.html.
     */
    private function build_title_link_aliases($title)
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

    /**
     * Repair legacy internal links for a rendered faithful document. The map
     * is cached for the current request and no database content is mutated.
     */
    public function repair_internal_links_for_render($html, $base_url = '')
    {
        static $render_link_map = null;

        if (!is_string($html) || trim($html) === '') {
            return $html;
        }
        if ($render_link_map === null) {
            $render_link_map = $this->build_link_map();
        }
        if (!empty($render_link_map)) {
            $html = $this->replace_links_in_html($html, $render_link_map, $base_url);
        }
        if (class_exists('Ugentic_Menu_Manager')) {
            $navigation_stats = [];
            $html = Ugentic_Menu_Manager::instance()->repair_imported_navigation_html($html, $navigation_stats);
        }

        return $html;
    }

    /**
     * Replace links in HTML content using the link map
     * Handles href attributes for navigation links
     */
    public function replace_links_in_html($html, $link_map, $base_url = '')
    {
        if (!is_string($html) || $html === '' || empty($link_map)) {
            return $html;
        }

        $normalized_map = [];
        $source_hosts = [];
        foreach ($link_map as $original => $new_url) {
            $decoded = html_entity_decode((string) $original, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $normalized_map[$decoded] = $new_url;
            $normalized_map[strtolower($decoded)] = $new_url;
            $host = wp_parse_url($decoded, PHP_URL_HOST);
            if ($host) {
                $source_hosts[strtolower(preg_replace('/^www\./i', '', $host))] = true;
            }
        }
        $target_host = strtolower(preg_replace('/^www\./i', '', wp_parse_url(home_url('/'), PHP_URL_HOST) ?: ''));

        // Rewrite each href exactly once. This avoids the previous cascading
        // regex pass which could produce ?page_id=1?page_id=1 on draft URLs.
        $html = preg_replace_callback(
            '/(\bhref\s*=\s*)(["\'])([^"\']*)\2/i',
            function ($matches) use ($normalized_map, $source_hosts, $target_host) {
                $href = html_entity_decode((string) $matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($href === '' || preg_match('/^(?:#|mailto:|tel:|sms:|javascript:|data:|blob:)/i', $href)) {
                    return $matches[0];
                }

                $fragment = '';
                if (($hash_pos = strpos($href, '#')) !== false) {
                    $fragment = substr($href, $hash_pos + 1);
                    $href = substr($href, 0, $hash_pos);
                }
                $query = '';
                if (($query_pos = strpos($href, '?')) !== false) {
                    $query = substr($href, $query_pos + 1);
                    $href_path = substr($href, 0, $query_pos);
                } else {
                    $href_path = $href;
                }

                $lookup = [$href_path, strtolower($href_path), rawurldecode($href_path), strtolower(rawurldecode($href_path))];
                $absolute_host = wp_parse_url($href_path, PHP_URL_HOST);
                $known_source_url = false;
                if ($absolute_host) {
                    $normalized_host = strtolower(preg_replace('/^www\./i', '', $absolute_host));
                    $known_source_url = isset($source_hosts[$normalized_host]);
                    if ($normalized_host === $target_host && (!$known_source_url || preg_match('/(?:^|[&?])(?:page_id|p|attachment_id)=\d+(?:$|[&?])/', $query))) {
                        $local = $this->normalize_local_permalink($href_path . ($query !== '' ? '?' . $query : '') . ($fragment !== '' ? '#' . $fragment : ''));
                        return $matches[1] . $matches[2] . $local . $matches[2];
                    }
                    if ($known_source_url) {
                        $path = rawurldecode((string) (wp_parse_url($href_path, PHP_URL_PATH) ?? '/'));
                        $lookup[] = $path;
                        $lookup[] = ltrim($path, '/');
                    }
                } elseif (strpos($href_path, '//') === 0) {
                    return $matches[0];
                }

                $trimmed = preg_replace('/^(?:\.\.\/|\.\/|\/)+/', '', rawurldecode($href_path));
                $lookup[] = $trimmed;
                $lookup[] = strtolower($trimmed);
                $lookup[] = rtrim($trimmed, '/');
                $basename = basename(rtrim($trimmed, '/'));
                if ($basename !== '') {
                    $lookup[] = $basename;
                    $lookup[] = preg_replace('/\.(?:html?|php|aspx?)$/i', '', $basename);
                }

                $new_url = '';
                foreach (array_values(array_unique(array_filter($lookup, static function ($value) {
                    return $value !== null && $value !== '';
                }))) as $candidate) {
                    if (isset($normalized_map[$candidate])) {
                        $new_url = $normalized_map[$candidate];
                        break;
                    }
                    $lower = strtolower($candidate);
                    if (isset($normalized_map[$lower])) {
                        $new_url = $normalized_map[$lower];
                        break;
                    }
                }
                if ($new_url === '') {
                    return $matches[0];
                }

                $new_url = $this->merge_permalink_suffix($new_url, $query, $fragment);
                return $matches[1] . $matches[2] . $new_url . $matches[2];
            },
            $html
        );

        // Remove any remaining file:// or full local paths
        $html = preg_replace('/href="file:\/\/[^"]+\.html?"/', 'href="#"', $html);
        $html = preg_replace("/href='file:\/\/[^']+\.html?'/", "href='#'", $html);

        // Clean up paths that look like /Users/... or C:\...
        $html = preg_replace('/href="\/Users\/[^"]+\.html?"/', 'href="#"', $html);
        $html = preg_replace('/href="[A-Z]:\\\\[^"]+\.html?"/', 'href="#"', $html);

        // Also replace base URL references
        if (!empty($base_url)) {
            $html = str_replace($base_url, home_url(), $html);
        }

        return $html;
    }

    /** Merge a source query/fragment into a WordPress permalink safely. */
    public function merge_permalink_suffix($permalink, $query = '', $fragment = '')
    {
        $permalink = $this->normalize_local_permalink((string) $permalink);
        $parts = wp_parse_url($permalink);
        if (!is_array($parts)) {
            return $permalink;
        }
        $target_fragment = (string) ($parts['fragment'] ?? '');
        $target_query = [];
        $source_query = [];
        parse_str(str_replace('?', '&', (string) ($parts['query'] ?? '')), $target_query);
        parse_str(str_replace('?', '&', (string) $query), $source_query);
        // The destination controls routing keys such as page_id; source
        // tracking/filter parameters are retained without duplicating them.
        $merged_query = array_merge($source_query, $target_query);
        $base = preg_replace('/[?#].*$/', '', $permalink);
        if ($merged_query) {
            $base .= '?' . http_build_query($merged_query, '', '&', PHP_QUERY_RFC3986);
        }
        $final_fragment = $fragment !== '' ? $fragment : $target_fragment;
        return $base . ($final_fragment !== '' ? '#' . ltrim($final_fragment, '#') : '');
    }

    /** Repair legacy duplicate question marks in local draft permalinks. */
    private function normalize_local_permalink($url)
    {
        $url = html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $host = strtolower(preg_replace('/^www\./i', '', wp_parse_url($url, PHP_URL_HOST) ?: ''));
        $target_host = strtolower(preg_replace('/^www\./i', '', wp_parse_url(home_url('/'), PHP_URL_HOST) ?: ''));
        if ($host === '' || $host !== $target_host || strpos($url, '?') === false) {
            return $url;
        }
        $fragment = '';
        if (($hash_pos = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hash_pos);
            $url = substr($url, 0, $hash_pos);
        }
        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        $params = [];
        parse_str(str_replace('?', '&', $query), $params);
        return $base . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '') . $fragment;
    }

    // ==================== PAGE-ASSET LINKING ====================

    /**
     * Link asset to page
     */
    public function link_asset_to_page($page_id, $asset_id, $load_order = 0)
    {
        global $wpdb;

        return $wpdb->replace(
            $this->page_assets_table,
            [
                'page_id' => $page_id,
                'asset_id' => $asset_id,
                'load_order' => $load_order,
            ]
        );
    }

    /**
     * Unlink asset from page
     */

    /**
     * Get page assets
     */
    public function get_page_assets($page_id, $type = null)
    {
        global $wpdb;

        if ($type) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT a.* FROM {$this->assets_table} a
                INNER JOIN {$this->page_assets_table} pa ON a.id = pa.asset_id
                WHERE pa.page_id = %d AND a.is_active = 1 AND a.type = %s
                ORDER BY pa.load_order ASC",
                $page_id,
                $type
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.* FROM {$this->assets_table} a
            INNER JOIN {$this->page_assets_table} pa ON a.id = pa.asset_id
            WHERE pa.page_id = %d AND a.is_active = 1
            ORDER BY pa.load_order ASC",
            $page_id
        ));
    }

    // ==================== HISTORY OPERATIONS ====================

    /**
     * Add history entry
     */
    public function add_history($data)
    {
        global $wpdb;

        $batch_id = isset($data['batch_id']) ? sanitize_text_field($data['batch_id']) : '';
        $revision_label = isset($data['revision_label']) ? sanitize_text_field($data['revision_label']) : '';
        $revision_source = isset($data['revision_source']) ? sanitize_key($data['revision_source']) : '';
        $revision_scope = isset($data['revision_scope']) ? sanitize_key($data['revision_scope']) : 'page';
        $revision_metadata = isset($data['revision_metadata']) ? $data['revision_metadata'] : [];
        $record_revision = array_key_exists('record_revision', $data) ? (bool) $data['record_revision'] : true;

        $defaults = [
            'page_id' => 0,
            'action' => '',
            'element_selector' => null,
            'old_content' => '',
            'new_content' => '',
            'prompt' => '',
            'user_id' => get_current_user_id(),
        ];

        $data = wp_parse_args($data, $defaults);
        $data = array_intersect_key($data, $defaults);

        $wpdb->insert($this->history_table, $data);

        $history_id = $wpdb->insert_id;

        if ($record_revision && $history_id) {
            $page = !empty($data['page_id']) ? $this->get_page($data['page_id']) : null;
            if (empty($batch_id)) {
                $batch_id = $this->create_revision_batch([
                    'label' => $revision_label ?: $this->format_revision_label($data['action']),
                    'source' => $revision_source ?: sanitize_key($data['action'] ?: 'history'),
                    'scope' => $revision_scope ?: 'page',
                    'metadata' => [
                        'history_id' => $history_id,
                        'prompt' => $data['prompt'],
                    ],
                ]);
            } else {
                $this->ensure_revision_batch_exists($batch_id, [
                    'label' => $revision_label ?: $this->format_revision_label($data['action']),
                    'source' => $revision_source ?: sanitize_key($data['action'] ?: 'history'),
                    'scope' => $revision_scope ?: 'page',
                    'metadata' => [
                        'history_id' => $history_id,
                        'prompt' => $data['prompt'],
                    ],
                ]);
            }

            if (!empty($batch_id)) {
                $this->add_revision_item([
                    'batch_id' => $batch_id,
                    'history_id' => $history_id,
                    'target_type' => 'page',
                    'target_id' => (string) absint($data['page_id']),
                    'page_id' => absint($data['page_id']),
                    'wp_page_id' => $page ? absint($page->wp_page_id) : 0,
                    'old_content' => $data['old_content'],
                    'new_content' => $data['new_content'],
                    'metadata' => array_merge(
                        is_array($revision_metadata) ? $revision_metadata : ['metadata' => $revision_metadata],
                        [
                            'action' => $data['action'],
                            'element_selector' => $data['element_selector'],
                            'prompt' => $data['prompt'],
                        ]
                    ),
                ]);
            }
        }

        return $history_id;
    }

    private function format_revision_label($action)
    {
        $action = trim((string) $action);
        if ($action === '') {
            return 'Page revision';
        }

        return ucwords(str_replace(['_', '-'], ' ', $action));
    }

    private function normalize_revision_metadata($metadata)
    {
        if (is_string($metadata)) {
            return $metadata;
        }

        if (empty($metadata)) {
            return '';
        }

        return wp_json_encode($metadata);
    }

    public function generate_revision_batch_id($prefix = 'rev')
    {
        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('', true);
        return sanitize_key($prefix . '-' . $uuid);
    }

    public function create_revision_batch($data = [])
    {
        global $wpdb;

        $this->ensure_revision_tables();

        $defaults = [
            'batch_id' => $this->generate_revision_batch_id(),
            'label' => 'Revision',
            'source' => 'manual',
            'scope' => 'page',
            'status' => 'completed',
            'user_id' => get_current_user_id(),
            'metadata' => '',
        ];

        $data = wp_parse_args($data, $defaults);
        $data['batch_id'] = sanitize_text_field($data['batch_id']);
        $data['label'] = sanitize_text_field($data['label']);
        $data['source'] = sanitize_key($data['source']);
        $data['scope'] = sanitize_key($data['scope']);
        $data['status'] = sanitize_key($data['status']);
        $data['user_id'] = absint($data['user_id']);
        $data['metadata'] = $this->normalize_revision_metadata($data['metadata']);

        $existing = $this->get_revision_batch($data['batch_id']);
        if ($existing) {
            return $existing->batch_id;
        }

        $wpdb->insert($this->revision_batches_table, $data);

        return $wpdb->insert_id ? $data['batch_id'] : '';
    }

    public function ensure_revision_batch_exists($batch_id, $defaults = [])
    {
        $batch_id = sanitize_text_field($batch_id);
        if ($batch_id === '') {
            return '';
        }

        $existing = $this->get_revision_batch($batch_id);
        if ($existing) {
            return $batch_id;
        }

        $defaults['batch_id'] = $batch_id;
        return $this->create_revision_batch($defaults);
    }

    public function add_revision_item($data)
    {
        global $wpdb;

        $this->ensure_revision_tables();

        $defaults = [
            'batch_id' => '',
            'history_id' => 0,
            'target_type' => 'page',
            'target_id' => '',
            'page_id' => 0,
            'wp_page_id' => 0,
            'old_content' => '',
            'new_content' => '',
            'old_meta' => '',
            'new_meta' => '',
            'metadata' => '',
        ];

        $data = wp_parse_args($data, $defaults);
        $data = array_intersect_key($data, $defaults);
        $data['batch_id'] = sanitize_text_field($data['batch_id']);
        $data['history_id'] = absint($data['history_id']);
        $data['target_type'] = sanitize_key($data['target_type']);
        $data['target_id'] = sanitize_text_field((string) $data['target_id']);
        $data['page_id'] = absint($data['page_id']);
        $data['wp_page_id'] = absint($data['wp_page_id']);
        $data['old_meta'] = $this->normalize_revision_metadata($data['old_meta']);
        $data['new_meta'] = $this->normalize_revision_metadata($data['new_meta']);
        $data['metadata'] = $this->normalize_revision_metadata($data['metadata']);

        if ($data['batch_id'] === '' || $data['target_type'] === '') {
            return 0;
        }

        $wpdb->insert($this->revision_items_table, $data);

        return absint($wpdb->insert_id);
    }

    public function get_revision_batch($batch_id)
    {
        global $wpdb;

        $this->ensure_revision_tables();
        $batch_id = sanitize_text_field($batch_id);
        if ($batch_id === '') {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->revision_batches_table} WHERE batch_id = %s LIMIT 1",
                $batch_id
            )
        );
    }

    /**
     * Get page history
     */

    /**
     * Revert to history entry
     */

    /**
     * Get recent history across ALL pages (last N days)
     */

    /**
     * Get bulk change history across ALL pages — grouped by batch.
     * Returns the last N bulk_ai_edit entries with page info, prompt, and timestamp.
     *
     * @param int $limit Max entries to return (default 50)
     * @return array History entries with page titles
     */

    /**
     * Delete history entries older than N days.
     * Always preserves the last 50 entries PER PAGE for revert capability,
     * plus the last 50 bulk_ai_edit/bulk_ai_backup entries globally.
     */

    // ==================== PROJECT OPERATIONS ====================

    /**
     * Insert a new project
     */

    /**
     * Get project by ID
     */

    /**
     * Update project
     */

    /**
     * Get all projects for current user
     */

    /**
     * Delete project and its pages
     */

    // ==================== PROJECT PAGE OPERATIONS ====================

    /**
     * Insert a project page
     */

    /**
     * Get project page by ID
     */

    /**
     * Update project page
     */

    /**
     * Get all pages for a project
     */

    /**
     * Delete project page
     */

    /**
     * Bulk insert project pages (for sitemap)
     */

    // ==================== WIZARD CONVERSATION OPERATIONS ====================

    /**
     * Insert a conversation message
     */

    /**
     * Get conversation messages for a project step
     */

    /**
     * Clear conversation for a project step (for regeneration)
     */

    // ==================== API USAGE OPERATIONS ====================

    /**
     * Log API usage
     */

    /**
     * Get API usage statistics
     */
    public function get_api_usage_stats($args = [])
    {
        global $wpdb;

        $defaults = [
            'user_id' => null,
            'period' => 'all', // today, week, month, all
            'group_by' => 'action_type', // action_type, day, model
        ];

        $args = wp_parse_args($args, $defaults);

        $user_id = absint($args['user_id']);
        $today_filter = $args['period'] === 'today' ? 1 : 0;
        $week_filter = $args['period'] === 'week' ? 1 : 0;
        $month_filter = $args['period'] === 'month' ? 1 : 0;

        if ($args['group_by'] === 'day') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as request_count,
                SUM(tokens_input) as total_input_tokens, SUM(tokens_output) as total_output_tokens,
                SUM(tokens_total) as total_tokens, SUM(cost_estimate) as total_cost
                FROM {$this->api_usage_table}
                WHERE (%d = 0 OR user_id = %d)
                AND (%d = 0 OR DATE(created_at) = CURDATE())
                AND (%d = 0 OR created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
                AND (%d = 0 OR created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                GROUP BY DATE(created_at) ORDER BY total_tokens DESC",
                $user_id, $user_id, $today_filter, $week_filter, $month_filter
            ));
        }
        if ($args['group_by'] === 'model') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT model_used, COUNT(*) as request_count,
                SUM(tokens_input) as total_input_tokens, SUM(tokens_output) as total_output_tokens,
                SUM(tokens_total) as total_tokens, SUM(cost_estimate) as total_cost
                FROM {$this->api_usage_table}
                WHERE (%d = 0 OR user_id = %d)
                AND (%d = 0 OR DATE(created_at) = CURDATE())
                AND (%d = 0 OR created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
                AND (%d = 0 OR created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                GROUP BY model_used ORDER BY total_tokens DESC",
                $user_id, $user_id, $today_filter, $week_filter, $month_filter
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, COUNT(*) as request_count,
            SUM(tokens_input) as total_input_tokens, SUM(tokens_output) as total_output_tokens,
            SUM(tokens_total) as total_tokens, SUM(cost_estimate) as total_cost
            FROM {$this->api_usage_table}
            WHERE (%d = 0 OR user_id = %d)
            AND (%d = 0 OR DATE(created_at) = CURDATE())
            AND (%d = 0 OR created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
            AND (%d = 0 OR created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
            GROUP BY action_type ORDER BY total_tokens DESC",
            $user_id, $user_id, $today_filter, $week_filter, $month_filter
        ));
    }

    /**
     * Get total API usage summary
     */
    public function get_api_usage_summary($user_id = null)
    {
        global $wpdb;

        $user_id = absint($user_id);
        $result = $wpdb->get_row($wpdb->prepare("SELECT
                    COUNT(*) as total_requests,
                    COALESCE(SUM(tokens_input), 0) as total_input_tokens,
                    COALESCE(SUM(tokens_output), 0) as total_output_tokens,
                    COALESCE(SUM(tokens_total), 0) as total_tokens,
                    COALESCE(SUM(cost_estimate), 0) as total_cost,
                    MIN(created_at) as first_request,
                    MAX(created_at) as last_request
                FROM {$this->api_usage_table}
                WHERE (%d = 0 OR user_id = %d)", $user_id, $user_id));

        // Return default object if null
        if (!$result) {
            return (object) [
                'total_requests' => 0,
                'total_input_tokens' => 0,
                'total_output_tokens' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'first_request' => null,
                'last_request' => null,
            ];
        }

        return $result;
    }

    // ==================== FORM OPERATIONS ====================

    /**
     * Insert a new form
     */

    /**
     * Get form by ID
     */

    /**
     * Update form
     */

    /**
     * Delete form
     */

    /**
     * Get all forms
     */
    public function get_all_forms($args = [])
    {
        global $wpdb;

        $defaults = [
            'user_id' => null,
            'is_active' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 100,
        ];

        $args = wp_parse_args($args, $defaults);
        $order_sql = Ugentic_Security::sanitize_sql_order(
            $args['orderby'],
            $args['order'],
            [
                'id' => 'id',
                'title' => 'title',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
                'submissions_count' => 'submissions_count',
            ],
            'created_at'
        );
        $limit = Ugentic_Security::clamp_int($args['limit'], 1, 500);

        $user_id = absint($args['user_id']);
        $active_filter = $args['is_active'] !== null ? 1 : 0;
        $active = (int) $args['is_active'];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->forms_table}
            WHERE (%d = 0 OR user_id = %d) AND (%d = 0 OR is_active = %d)
            ORDER BY {$order_sql} LIMIT %d",
            $user_id, $user_id, $active_filter, $active, $limit
        ));
    }

    /**
     * Increment form submissions count
     */

    // ==================== FORM ENTRY OPERATIONS ====================

    /**
     * Insert form entry
     */

    /**
     * Get form entry by ID
     */

    /**
     * Get form entries
     */

    /**
     * Update form entry
     */

    /**
     * Delete form entry
     */

    /**
     * Mark entry as read
     */

    /**
     * Toggle entry starred status
     */

    /**
     * Get form entries count
     */

    /**
     * Get entries across all forms with filtering
     */

    /**
     * Count entries across all forms with filtering
     */

    /**
     * Get form logs
     */

    /**
     * Insert form log
     */

    /**
     * Keep delivery-log enums compatible with optional integrations.
     *
     * dbDelta does not consistently widen enum definitions on existing MySQL
     * tables, so verify these two columns explicitly. The checks make this
     * idempotent and avoid ALTER TABLE work during normal requests.
     */
    private function ensure_form_log_schema()
    {
        global $wpdb;

        if (get_option('ugentic_form_log_schema_version') === '1') {
            return;
        }

        $type_column = $wpdb->get_row(
            "SHOW COLUMNS FROM {$this->form_logs_table} LIKE 'type'",
            ARRAY_A
        );
        if (is_array($type_column) && strpos((string) ($type_column['Type'] ?? ''), 'gohighlevel') === false) {
            $wpdb->query(
                "ALTER TABLE {$this->form_logs_table}
                 MODIFY type enum('email','webhook','gohighlevel') NOT NULL"
            );
        }

        $status_column = $wpdb->get_row(
            "SHOW COLUMNS FROM {$this->form_logs_table} LIKE 'status'",
            ARRAY_A
        );
        if (is_array($status_column) && strpos((string) ($status_column['Type'] ?? ''), 'queued') === false) {
            $wpdb->query(
                "ALTER TABLE {$this->form_logs_table}
                 MODIFY status enum('success','error','pending','queued') DEFAULT 'pending'"
            );
        }

        $type_column = $wpdb->get_row(
            "SHOW COLUMNS FROM {$this->form_logs_table} LIKE 'type'",
            ARRAY_A
        );
        $status_column = $wpdb->get_row(
            "SHOW COLUMNS FROM {$this->form_logs_table} LIKE 'status'",
            ARRAY_A
        );
        if (
            is_array($type_column)
            && is_array($status_column)
            && strpos((string) ($type_column['Type'] ?? ''), 'gohighlevel') !== false
            && strpos((string) ($status_column['Type'] ?? ''), 'queued') !== false
        ) {
            update_option('ugentic_form_log_schema_version', '1', false);
        }
    }

    // ==================== GLOBAL SECTION OPERATIONS ====================

    /**
     * Ensure global sections tables exist (auto-create on first use).
     * Avoids requiring plugin re-activation after code update.
     */

    /**
     * Insert a new global section
     */

    /**
     * Get global section by ID
     */

    /**
     * Get all global sections
     */

    /**
     * Update global section
     */

    /**
     * Delete global section and all its links
     */

    /**
     * Link a page to a global section
     */

    /**
     * Unlink a page from a global section (delete the link row)
     */

    /**
     * Detach a page from global section (keep link but mark detached)
     */

    /**
     * Reattach a detached page to global section
     */

    /**
     * Update a global section link (e.g. to correct drifted section_index)
     */

    /**
     * Get all links for a global section (with page info)
     */

    /**
     * Get all global sections linked to a specific page
     */
    public function get_page_global_sections($page_id)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT gs.*, gsl.id as link_id, gsl.page_id, gsl.section_index, gsl.section_uid,
                    gsl.section_locator, gsl.section_fingerprint, gsl.match_confidence,
                    gsl.last_verified_at, gsl.is_detached
             FROM {$this->global_sections_table} gs
             INNER JOIN {$this->global_section_links_table} gsl ON gs.id = gsl.global_section_id
             WHERE gsl.page_id = %d
             ORDER BY gsl.section_index ASC",
            $page_id
        ));
    }

    /**
     * Find global section link for a specific page and section index
     */

    /**
     * Find pages with sections matching a specific hash
     */

    // ==================== BULK CONVERSATION OPERATIONS ====================

    /**
     * Ensure bulk conversations table exists (auto-create on first use).
     * Avoids requiring plugin re-activation after code update.
     */

    /**
     * Save (insert or update) a bulk AI conversation
     */

    /**
     * Get a single bulk conversation by ID
     */

    /**
     * List recent bulk conversations for a user
     */

    /**
     * Delete a bulk conversation
     */

    // ==================== AGENCY CLIENT OPERATIONS ====================

    /**
     * Insert a new agency client
     */

    /**
     * Get agency client by ID
     */

    /**
     * Get all agency clients with optional filters
     */

    /**
     * Count agency clients by status
     */

    /**
     * Update an agency client
     */

    /**
     * Delete an agency client (and cleanup relations)
     */

    // ==================== AGENCY GROUP OPERATIONS ====================

    /**
     * Insert a new agency group
     */

    /**
     * Get all agency groups
     */

    /**
     * Get a single agency group
     */

    /**
     * Update an agency group
     */

    /**
     * Delete an agency group (and cleanup junctions)
     */

    /**
     * Assign a client to a group
     */

    /**
     * Remove a client from a group
     */

    /**
     * Get groups for a specific client
     */

    // ==================== AGENCY API KEY OPERATIONS ====================

    /**
     * Insert an agency API key
     */

    /**
     * Get all agency API keys
     */

    /**
     * Get a single API key
     */

    /**
     * Update an agency API key
     */

    /**
     * Delete an agency API key
     */

    /**
     * Increment API key usage count
     */

    // ==================== AGENCY CREDIT LOG OPERATIONS ====================

    /**
     * Log a credit usage entry
     */

    /**
     * Get credit log for a client
     */

    /**
     * Get total credits used by a client in a date range
     */

    /**
     * Get daily credit usage for charts (last N days)
     */

    /**
     * Get total credits used across all clients
     */

    /**
     * Get top clients by credit usage
     */

    /**
     * Get credit usage breakdown by model
     */

    /**
     * Get credit usage per group
     */

    // ─── AI Usage Log ────────────────────────────────────────────

    /**
     * Insert an AI usage log entry.
     */

    /**
     * Get AI usage stats for a time period.
     */

    /**
     * Get daily AI usage for chart rendering.
     */

    /**
     * Get AI usage by provider.
     */

    /**
     * Get AI usage by task type.
     */

    /**
     * Get monthly AI cost.
     */

    public function delete_form($id)
    {
        global $wpdb;
        $id = absint($id);
        if ($id <= 0 || !$this->get_form($id)) {
            return false;
        }

        $transaction_started = $wpdb->query('START TRANSACTION') !== false;

        // Logs and entries are dependent records. Remove them in the same
        // operation so a deleted form cannot leave private submissions behind.
        $logs_deleted = $wpdb->delete($this->form_logs_table, ['form_id' => $id], ['%d']);
        $entries_deleted = $wpdb->delete($this->form_entries_table, ['form_id' => $id], ['%d']);
        if ($logs_deleted === false || $entries_deleted === false) {
            if ($transaction_started) {
                $wpdb->query('ROLLBACK');
            }
            return false;
        }

        $deleted = $wpdb->delete($this->forms_table, ['id' => $id], ['%d']);
        if ($deleted !== 1) {
            if ($transaction_started) {
                $wpdb->query('ROLLBACK');
            }
            return false;
        }

        if ($transaction_started) {
            $wpdb->query('COMMIT');
        }

        return $deleted;
    }

    public function insert_form_entry($data)
    {
        global $wpdb;

        $defaults = [
            'form_id' => 0,
            'entry_data' => '{}',
            'user_ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'page_url' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
            'is_read' => 0,
            'is_starred' => 0,
            'is_spam' => 0,
        ];

        $data = wp_parse_args($data, $defaults);
        $data['user_ip'] = filter_var((string) $data['user_ip'], FILTER_VALIDATE_IP) ?: '';
        $data['user_agent'] = substr(sanitize_text_field((string) $data['user_agent']), 0, 500);
        $data['page_url'] = esc_url_raw((string) $data['page_url']);

        if (is_array($data['entry_data'])) {
            $data['entry_data'] = json_encode($data['entry_data']);
        }

        $wpdb->insert($this->form_entries_table, $data);

        // Increment form submissions count
        if ($wpdb->insert_id) {
            $this->increment_form_submissions($data['form_id']);
        }

        return $wpdb->insert_id;
    }

    public function get_form_entry($id)
    {
        global $wpdb;

        $entry = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->form_entries_table} WHERE id = %d",
                $id
            )
        );

        if ($entry) {
            $entry->entry_data = json_decode($entry->entry_data, true) ?: [];
        }

        return $entry;
    }

    public function get_form_entries_count($form_id, $unread_only = false)
    {
        global $wpdb;

        if ($unread_only) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->form_entries_table} WHERE form_id = %d AND is_spam = 0 AND is_read = 0",
                $form_id
            ));
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->form_entries_table} WHERE form_id = %d AND is_spam = 0",
            $form_id
        ));
    }

    public function increment_form_submissions($form_id)
    {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->forms_table} SET submissions_count = submissions_count + 1 WHERE id = %d",
                $form_id
            )
        );
    }

    public function update_form($id, $data)
    {
        global $wpdb;

        if (class_exists('Ugentic_Form_Engine')) {
            $existing = $this->get_form($id);
            $defaults = $existing ? [
                'fields' => $existing->fields,
                'settings' => $existing->settings,
                'notifications' => $existing->notifications,
                'confirmations' => $existing->confirmations,
                'style_settings' => $existing->style_settings,
            ] : [];
            $normalized = Ugentic_Form_Engine::normalize_form_data($data, $defaults);
            foreach (['fields', 'settings', 'notifications', 'confirmations', 'style_settings'] as $field) {
                if (isset($data[$field]) || isset($defaults[$field])) {
                    $data[$field] = $normalized[$field];
                }
            }
        }

        // Ensure JSON fields are strings
        foreach (['fields', 'settings', 'notifications', 'confirmations', 'style_settings'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        return $wpdb->update(
            $this->forms_table,
            $data,
            ['id' => $id]
        );
    }

    public function toggle_entry_starred($id)
    {
        $entry = $this->get_form_entry($id);
        if ($entry) {
            return $this->update_form_entry($id, ['is_starred' => $entry->is_starred ? 0 : 1]);
        }
        return false;
    }

    public function insert_form_log($data)
    {
        global $wpdb;

        $defaults = [
            'form_id' => 0,
            'entry_id' => null,
            'type' => 'email',
            'destination' => '',
            'status' => 'pending',
            'message' => '',
            'response_code' => null,
        ];

        $data = wp_parse_args($data, $defaults);

        $wpdb->insert($this->form_logs_table, $data);

        return $wpdb->insert_id;
    }

    public function delete_form_entry($id)
    {
        global $wpdb;
        $id = absint($id);
        if ($id <= 0) {
            return false;
        }

        $entry = $this->get_form_entry($id);
        if (!$entry) {
            return false;
        }

        $form_id = absint($entry->form_id);
        $transaction_started = $wpdb->query('START TRANSACTION') !== false;
        $logs_deleted = $wpdb->delete($this->form_logs_table, ['entry_id' => $id], ['%d']);
        $deleted = $wpdb->delete($this->form_entries_table, ['id' => $id], ['%d']);
        if ($logs_deleted === false || $deleted !== 1) {
            if ($transaction_started) {
                $wpdb->query('ROLLBACK');
            }
            return false;
        }

        // Keep dashboard totals exact after single and bulk deletion, including
        // older databases whose cached submissions_count may already be stale.
        $remaining = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->form_entries_table} WHERE form_id = %d",
            $form_id
        ));
        $count_updated = $wpdb->update(
            $this->forms_table,
            ['submissions_count' => $remaining],
            ['id' => $form_id],
            ['%d'],
            ['%d']
        );
        if ($count_updated === false) {
            if ($transaction_started) {
                $wpdb->query('ROLLBACK');
            }
            return false;
        }

        if ($transaction_started) {
            $wpdb->query('COMMIT');
        }

        return $deleted;
    }

    public function get_form_entries($form_id, $args = [])
    {
        global $wpdb;

        $defaults = [
            'is_read' => null,
            'is_starred' => null,
            'is_spam' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);
        $order_sql = Ugentic_Security::sanitize_sql_order(
            $args['orderby'],
            $args['order'],
            [
                'id' => 'id',
                'created_at' => 'created_at',
                'is_read' => 'is_read',
                'is_starred' => 'is_starred',
            ],
            'created_at'
        );
        $limit = Ugentic_Security::clamp_int($args['limit'], 1, 500);
        $offset = Ugentic_Security::clamp_int($args['offset'], 0, 100000);

        $read_filter = $args['is_read'] !== null ? 1 : 0;
        $starred_filter = $args['is_starred'] !== null ? 1 : 0;
        $spam_filter = $args['is_spam'] !== null ? 1 : 0;
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->form_entries_table}
            WHERE form_id = %d
            AND (%d = 0 OR is_read = %d)
            AND (%d = 0 OR is_starred = %d)
            AND (%d = 0 OR is_spam = %d)
            ORDER BY {$order_sql} LIMIT %d OFFSET %d",
            $form_id,
            $read_filter, (int) $args['is_read'],
            $starred_filter, (int) $args['is_starred'],
            $spam_filter, (int) $args['is_spam'],
            $limit, $offset
        ));

        // Decode entry data
        foreach ($entries as $entry) {
            $entry->entry_data = json_decode($entry->entry_data, true) ?: [];
        }

        return $entries;
    }

    public function get_form_logs($form_id, $limit = 100)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->form_logs_table} WHERE form_id = %d ORDER BY created_at DESC LIMIT %d",
            $form_id,
            $limit
        ));
    }

    public function update_form_entry($id, $data)
    {
        global $wpdb;

        if (isset($data['entry_data']) && is_array($data['entry_data'])) {
            $data['entry_data'] = json_encode($data['entry_data']);
        }

        return $wpdb->update(
            $this->form_entries_table,
            $data,
            ['id' => $id]
        );
    }

    public function get_all_entries_count($args = [])
    {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'ugentic_forms';

        $form_id = absint($args['form_id'] ?? 0);
        $read_filter = isset($args['is_read']) && $args['is_read'] !== '' ? 1 : 0;
        $starred_filter = isset($args['is_starred']) && $args['is_starred'] !== '' ? 1 : 0;
        $spam_filter = isset($args['is_spam']) ? 1 : 0;
        $search = isset($args['search']) ? (string) $args['search'] : '';
        $from = self::valid_date_filter($args['date_from'] ?? '');
        $to = self::valid_date_filter($args['date_to'] ?? '');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->form_entries_table} e
            LEFT JOIN {$forms_table} f ON e.form_id = f.id
            WHERE (%d = 0 OR e.form_id = %d)
            AND (%d = 0 OR e.is_read = %d)
            AND (%d = 0 OR e.is_starred = %d)
            AND (%d = 0 OR e.is_spam = %d)
            AND (%d = 0 OR e.entry_data LIKE %s)
            AND (%d = 0 OR e.created_at >= %s)
            AND (%d = 0 OR e.created_at <= %s)",
            $form_id, $form_id,
            $read_filter, (int) ($args['is_read'] ?? 0),
            $starred_filter, (int) ($args['is_starred'] ?? 0),
            $spam_filter, (int) ($args['is_spam'] ?? 0),
            $search !== '' ? 1 : 0, '%' . $wpdb->esc_like($search) . '%',
            $from !== '' ? 1 : 0, ($from ?: '1970-01-01') . ' 00:00:00',
            $to !== '' ? 1 : 0, ($to ?: '2999-12-31') . ' 23:59:59'
        ));
    }

    public function mark_entry_read($id, $read = true)
    {
        return $this->update_form_entry($id, ['is_read' => $read ? 1 : 0]);
    }

    public function get_all_entries($args = [])
    {
        global $wpdb;

        $defaults = [
            'form_id' => 0,
            'is_read' => null,
            'is_starred' => null,
            'is_spam' => 0,
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);
        $forms_table = $wpdb->prefix . 'ugentic_forms';
        $order_sql = Ugentic_Security::sanitize_sql_order(
            $args['orderby'],
            $args['order'],
            [
                'id' => 'e.id',
                'created_at' => 'e.created_at',
                'form_id' => 'e.form_id',
                'is_read' => 'e.is_read',
                'is_starred' => 'e.is_starred',
                'form_title' => 'f.title',
            ],
            'created_at'
        );
        $limit = Ugentic_Security::clamp_int($args['limit'], 1, 500);
        $offset = Ugentic_Security::clamp_int($args['offset'], 0, 100000);

        $form_id = absint($args['form_id']);
        $read_filter = $args['is_read'] !== null ? 1 : 0;
        $starred_filter = $args['is_starred'] !== null ? 1 : 0;
        $spam_filter = $args['is_spam'] !== null ? 1 : 0;
        $search = (string) $args['search'];
        $from = self::valid_date_filter($args['date_from']);
        $to = self::valid_date_filter($args['date_to']);
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, f.title as form_title FROM {$this->form_entries_table} e
            LEFT JOIN {$forms_table} f ON e.form_id = f.id
            WHERE (%d = 0 OR e.form_id = %d)
            AND (%d = 0 OR e.is_read = %d)
            AND (%d = 0 OR e.is_starred = %d)
            AND (%d = 0 OR e.is_spam = %d)
            AND (%d = 0 OR e.entry_data LIKE %s)
            AND (%d = 0 OR e.created_at >= %s)
            AND (%d = 0 OR e.created_at <= %s)
            ORDER BY {$order_sql} LIMIT %d OFFSET %d",
            $form_id, $form_id,
            $read_filter, (int) $args['is_read'],
            $starred_filter, (int) $args['is_starred'],
            $spam_filter, (int) $args['is_spam'],
            $search !== '' ? 1 : 0, '%' . $wpdb->esc_like($search) . '%',
            $from !== '' ? 1 : 0, ($from ?: '1970-01-01') . ' 00:00:00',
            $to !== '' ? 1 : 0, ($to ?: '2999-12-31') . ' 23:59:59',
            $limit, $offset
        ));

        foreach ($entries as $entry) {
            $entry->entry_data = json_decode($entry->entry_data, true) ?: [];
        }

        return $entries;
    }

    public function insert_form($data)
    {
        global $wpdb;

        $defaults = [
            'title' => 'Untitled Form',
            'description' => '',
            'fields' => '[]',
            'settings' => '{}',
            'notifications' => '[]',
            'confirmations' => '{}',
            'style_settings' => '{}',
            'submissions_count' => 0,
            'is_active' => 1,
            'user_id' => get_current_user_id(),
        ];

        $data = wp_parse_args($data, $defaults);

        if (class_exists('Ugentic_Form_Engine')) {
            $normalized = Ugentic_Form_Engine::normalize_form_data($data, $defaults);
            $data['fields'] = $normalized['fields'];
            $data['settings'] = $normalized['settings'];
            $data['notifications'] = $normalized['notifications'];
            $data['confirmations'] = $normalized['confirmations'];
            $data['style_settings'] = $normalized['style_settings'];
        }

        // Ensure JSON fields are strings
        foreach (['fields', 'settings', 'notifications', 'confirmations', 'style_settings'] as $field) {
            if (is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $wpdb->insert($this->forms_table, $data);

        return $wpdb->insert_id;
    }

    public function get_form($id)
    {
        global $wpdb;

        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->forms_table} WHERE id = %d",
                $id
            )
        );

        if ($form) {
            // Decode JSON fields
            $form->fields = json_decode($form->fields, true) ?: [];
            $form->settings = json_decode($form->settings, true) ?: [];
            $form->notifications = json_decode($form->notifications, true) ?: [];
            $form->confirmations = json_decode($form->confirmations, true) ?: [];
            $form->style_settings = json_decode($form->style_settings, true) ?: [];
        }

        return $form;
    }
}
