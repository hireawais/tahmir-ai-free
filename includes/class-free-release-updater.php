<?php
/**
 * Self-hosted Free updates. A version check sends only the installed version;
 * installation telemetry is handled separately and requires consent.
 */
if (!defined('ABSPATH')) { exit; }

final class Tahmir_Free_Release_Updater {
    private const SLUG = 'tahmir-ai-to-wp-converter';
    private const CACHE_KEY = 'tahmir_free_release_manifest';

    public function __construct() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'update']);
        add_filter('plugins_api', [$this, 'info'], 20, 3);
    }

    private function manifest() {
        $cached = get_site_transient(self::CACHE_KEY);
        if ($cached !== false) { return $cached; }
        $base = apply_filters('tahmir_release_api_base', 'https://dash.tahmir.com/wp-json/tahmir-releases/v1');
        $url = trailingslashit(esc_url_raw($base)) . 'manifest/free';
        $url = add_query_arg('version', rawurlencode(TAHMIR_FREE_VERSION), $url);
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'redirection' => 0,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return false; }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['version']) || empty($data['download_url'])
            || !preg_match('/^\d+\.\d+(?:\.\d+)?(?:[-.][A-Za-z0-9]+)*$/', $data['version'])
            || !wp_http_validate_url($data['download_url'])) { return false; }
        set_site_transient(self::CACHE_KEY, $data, HOUR_IN_SECONDS);
        return $data;
    }

    public function update($transient) {
        if (!is_object($transient) || empty($transient->checked)) { return $transient; }
        $remote = $this->manifest();
        if (!$remote || !version_compare($remote['version'], TAHMIR_FREE_VERSION, '>')) { return $transient; }
        $key = plugin_basename(TAHMIR_FREE_FILE);
        $transient->response[$key] = (object) [
            'slug' => self::SLUG,
            'plugin' => $key,
            'new_version' => $remote['version'],
            'package' => $remote['download_url'],
            'url' => $remote['homepage'] ?? 'https://tahmir.com/',
            'requires' => $remote['requires'] ?? '6.2',
            'requires_php' => $remote['requires_php'] ?? '8.1',
            'tested' => $remote['tested'] ?? '',
        ];
        unset($transient->no_update[$key]);
        return $transient;
    }

    public function info($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== self::SLUG) { return $result; }
        $remote = $this->manifest();
        if (!$remote) { return $result; }
        return (object) [
            'name' => 'Tahmir AI Free',
            'slug' => self::SLUG,
            'version' => $remote['version'],
            'author' => 'Tahmir',
            'homepage' => $remote['homepage'] ?? 'https://tahmir.com/',
            'requires' => $remote['requires'] ?? '6.2',
            'requires_php' => $remote['requires_php'] ?? '8.1',
            'tested' => $remote['tested'] ?? '',
            'download_link' => $remote['download_url'],
            'sections' => [
                'description' => wp_kses_post($remote['description'] ?? 'Import and edit supported websites in WordPress.'),
                'changelog' => wp_kses_post($remote['changelog'] ?? ''),
            ],
        ];
    }
}
