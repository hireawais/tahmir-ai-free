<?php
/**
 * Plugin Name: Tahmir AI - AI to WP Website Converter
 * Plugin URI: https://tahmir.com/
 * Description: Free HTML to WordPress and Lovable to WordPress import. Convert supported AI website exports or public URLs into WordPress pages. No license required.
 * Version: 1.0.8
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Author: Tahmir
 * Author URI: https://tahmir.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tahmir-ai-to-wp-converter
 */
if (!defined('ABSPATH')) { exit; }
define('TAHMIR_FREE_FILE', __FILE__);
define('TAHMIR_FREE_DIR', plugin_dir_path(__FILE__));
define('TAHMIR_FREE_URL', plugin_dir_url(__FILE__));
define('TAHMIR_FREE_VERSION', '1.0.8');

require_once __DIR__ . '/includes/class-free-release-updater.php';
require_once __DIR__ . '/includes/class-install-telemetry.php';
new Tahmir_Free_Release_Updater();
Tahmir_Install_Telemetry::boot();

register_activation_hook(__FILE__, static function () {
    update_option('tahmir_free_pending_activation', '1', false);
});
add_action('plugins_loaded', static function () {
    if (defined('TAHMIR_PRO_ENGINE_ACTIVE') || function_exists('ugentic_builder')) { return; }
    require_once __DIR__ . '/engine.php';
}, 20);
add_action('plugins_loaded', static function () {
    if (get_option('tahmir_free_pending_activation') === '1' && function_exists('ugentic_builder')) {
        ugentic_builder()->activate();
        delete_option('tahmir_free_pending_activation');
    }
}, 30);
