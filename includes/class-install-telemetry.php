<?php
/** Optional installation activity; no telemetry is sent until an admin opts in. */
if (!defined('ABSPATH')) { exit; }

final class Tahmir_Install_Telemetry {
    private const OPTION = 'tahmir_install_tracking_consent';
    private const ID_OPTION = 'tahmir_install_tracking_id';
    private const CRON = 'tahmir_install_tracking_heartbeat';

    public static function boot() {
        add_action('admin_notices', [__CLASS__, 'notice']);
        add_action('admin_menu', [__CLASS__, 'menu'], 35);
        add_action('admin_post_tahmir_tracking_choice', [__CLASS__, 'choice']);
        add_action(self::CRON, [__CLASS__, 'send']);
        add_action('tahmir_install_tracking_after_update', [__CLASS__, 'send']);
        add_action('admin_init', [__CLASS__, 'schedule']);
        add_action('upgrader_process_complete', [__CLASS__, 'on_update'], 10, 2);
    }

    public static function notice() {
        if (!current_user_can('manage_options') || get_option(self::OPTION) !== false) { return; }
        echo '<div class="notice notice-info"><p><strong>' . esc_html__('Help improve Tahmir AI', 'tahmir-ai-to-wp-converter') . '</strong></p>';
        echo '<p>' . esc_html__('May we send a random installation ID, plugin edition and version, WordPress and PHP versions, and a daily activity signal to Tahmir? This lets us count active installations and find update problems. We do not send your site URL, page content, visitors, or form entries. The plugin works normally if you decline.', 'tahmir-ai-to-wp-converter') . '</p>';
        foreach (['yes' => __('Allow tracking', 'tahmir-ai-to-wp-converter'), 'no' => __('No thanks', 'tahmir-ai-to-wp-converter')] as $choice => $label) {
            echo '<form style="display:inline-block;margin:0 8px 8px 0" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="tahmir_tracking_choice"><input type="hidden" name="choice" value="' . esc_attr($choice) . '">';
            wp_nonce_field('tahmir_tracking_choice');
            echo '<button class="button ' . ($choice === 'yes' ? 'button-primary' : '') . '" type="submit">' . esc_html($label) . '</button></form>';
        }
        echo '</div>';
    }

    public static function menu() {
        add_submenu_page('ugentic-builder',
            __('Usage sharing', 'tahmir-ai-to-wp-converter'),
            __('Usage sharing', 'tahmir-ai-to-wp-converter'),
            'manage_options', 'tahmir-usage-sharing', [__CLASS__, 'settings']);
    }

    public static function settings() {
        if (!current_user_can('manage_options')) { return; }
        $choice = get_option(self::OPTION);
        echo '<div class="wrap"><h1>' . esc_html__('Tahmir usage sharing', 'tahmir-ai-to-wp-converter') . '</h1>';
        echo '<p>' . esc_html__('When allowed, Tahmir receives a random installation ID, Free and Pro versions, WordPress and PHP versions, and a daily activity signal. No site URL, page content, visitors, or form entries are sent. You can change this choice at any time.', 'tahmir-ai-to-wp-converter') . '</p>';
        echo '<p>' . esc_html__('Current choice:', 'tahmir-ai-to-wp-converter') . ' <strong>' . esc_html($choice === 'yes' ? __('Allowed', 'tahmir-ai-to-wp-converter') : __('Not allowed', 'tahmir-ai-to-wp-converter')) . '</strong></p>';
        foreach (['yes' => __('Allow usage sharing', 'tahmir-ai-to-wp-converter'), 'no' => __('Stop usage sharing', 'tahmir-ai-to-wp-converter')] as $value => $label) {
            echo '<form style="display:inline-block;margin-right:8px" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="tahmir_tracking_choice"><input type="hidden" name="choice" value="' . esc_attr($value) . '">';
            wp_nonce_field('tahmir_tracking_choice');
            echo '<button class="button ' . ($value === 'yes' ? 'button-primary' : '') . '" type="submit">' . esc_html($label) . '</button></form>';
        }
        echo '</div>';
    }

    public static function choice() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Permission denied.', 'tahmir-ai-to-wp-converter'), '', 403); }
        check_admin_referer('tahmir_tracking_choice');
        $value = sanitize_key(wp_unslash($_POST['choice'] ?? ''));
        if (!in_array($value, ['yes', 'no'], true)) { wp_die(esc_html__('Invalid choice.', 'tahmir-ai-to-wp-converter'), '', 400); }
        update_option(self::OPTION, $value, false);
        if ($value === 'yes') {
            if (!get_option(self::ID_OPTION)) { add_option(self::ID_OPTION, wp_generate_uuid4(), '', false); }
            self::schedule();
            self::send();
        } else {
            wp_clear_scheduled_hook(self::CRON);
            delete_option(self::ID_OPTION);
        }
        wp_safe_redirect(admin_url('plugins.php'));
        exit;
    }

    public static function schedule() {
        if (get_option(self::OPTION) !== 'yes') { return; }
        if (!wp_next_scheduled(self::CRON)) { wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON); }
    }

    public static function on_update($upgrader, $options) {
        if (get_option(self::OPTION) !== 'yes' || ($options['action'] ?? '') !== 'update' || ($options['type'] ?? '') !== 'plugin') { return; }
        $plugins = (array) ($options['plugins'] ?? []);
        if (array_intersect($plugins, [plugin_basename(TAHMIR_FREE_FILE), 'tahmir-ai-pro/tahmir-ai-pro.php'])) {
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'tahmir_install_tracking_after_update');
        }
    }

    public static function send() {
        if (get_option(self::OPTION) !== 'yes') { return; }
        $id = get_option(self::ID_OPTION);
        if (!$id || !wp_is_uuid($id)) { return; }
        $pro_file = 'tahmir-ai-pro/tahmir-ai-pro.php';
        $pro_installed = in_array($pro_file, (array) get_option('active_plugins', []), true)
            || array_key_exists($pro_file, (array) get_site_option('active_sitewide_plugins', []));
        $pro_active = $pro_installed && defined('TAHMIR_PRO_ENGINE_ACTIVE');
        $base = apply_filters('tahmir_release_api_base', 'https://dash.tahmir.com/wp-json/tahmir-releases/v1');
        wp_remote_post(trailingslashit(esc_url_raw($base)) . 'installations', [
            'timeout' => 8,
            'blocking' => false,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'install_id' => $id,
                'free_version' => TAHMIR_FREE_VERSION,
                'pro_active' => $pro_active,
                'pro_version' => $pro_active && defined('TAHMIR_PRO_VERSION') ? TAHMIR_PRO_VERSION : null,
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            ]),
        ]);
    }
}
