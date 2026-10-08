<?php
/** Preserve complete ZIP resources without rewriting JavaScript filename fragments. */
if (!defined('ABSPATH')) exit;

final class Ugentic_Zip_Assets
{
    public static function navigation($page, $database)
    {
        $meta = json_decode($page->meta_data ?? '{}', true) ?: [];
        $session = $meta['import_session_id'] ?? '';
        if (!$session || empty($meta['source_relative_path'])) return [];
        $paths = [];
        $home = home_url('/');
        foreach (self::page_inventory($database) as $candidate) {
            $other = json_decode($candidate->meta_data ?? '{}', true) ?: [];
            if (($other['import_session_id'] ?? '') !== $session || !$candidate->wp_page_id) continue;
            $target = get_permalink($candidate->wp_page_id);
            if (!$target) continue;
            foreach (array_merge([$other['source_relative_path'] ?? ''], $other['source_aliases'] ?? []) as $alias) {
                $route = Ugentic_Importer::canonical_import_page_route($alias);
                $paths[$route] = $target;
                if ($route === '') $home = $target;
            }
        }
        $dir = dirname($meta['source_relative_path']);
        return ['pathMap' => $paths, 'sourceDir' => '/' . ($dir === '.' ? '' : $dir . '/'), 'homeUrl' => $home];
    }

    private static function page_inventory($database)
    {
        if (!method_exists($database, 'get_page_summaries')) {
            yield from $database->get_all_pages(['limit' => 10000]);
            return;
        }
        // Navigation needs identity and import metadata, never full documents.
        $offset = 0;
        do {
            $batch = $database->get_page_summaries(['limit' => 1000, 'offset' => $offset]);
            yield from $batch;
            $count = count($batch);
            $offset += $count;
        } while ($count === 1000 && $offset < 100000);
    }

    public static function rewrite_javascript($javascript, $source_dir, $resolver, $asset_map, &$unresolved)
    {
        $unresolved = [];
        $uploaded = array_fill_keys(array_values($asset_map), true);
        return preg_replace_callback(
            '/([\'"`])([^\'"`\s]+\.(?:css|js|mjs|cjs|json|map|webmanifest|wasm|jpg|jpeg|png|gif|webp|svg|ico|bmp|avif|tiff?|woff2?|ttf|otf|eot|mp4|webm|ogv|mov|mp3|wav|ogg|oga|m4a|aac|flac|vtt|pdf)(?:[?#][^\'"`\s]*)?)\1/i',
            static function ($match) use ($source_dir, $resolver, $uploaded) {
                $ref = $match[2];
                // Bare names and template fragments may be passed through a
                // URL builder later. Their meaning is known only at runtime.
                if (strpos($ref, '/') === false || strpos($ref, '${') !== false
                    || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|\#)#i', $ref)) return $match[0];
                $mapped = $resolver->resolve_path($ref, $source_dir);
                $clean = preg_replace('/[?#].*$/', '', $mapped);
                if (!isset($uploaded[$clean])) return $match[0];
                return $match[1] . $mapped . $match[1];
            },
            $javascript
        );
    }

    public static function prepare_runtime($html, $source_file, $root, $asset_map)
    {
        if (!$source_file || !$asset_map || !preg_match('/<script\b/i', $html)) return $html;
        $uploads = wp_upload_dir();
        $namespace_url = '';
        foreach ($asset_map as $url) {
            if (preg_match('#^(.*?/ugentic-assets/[^/]+/)#', $url, $match)) {
                $namespace_url = $match[1];
                break;
            }
        }
        if ($namespace_url === '' || strpos($namespace_url, $uploads['baseurl'] . '/') !== 0) return $html;
        $relative = ltrim(substr(wp_normalize_path($source_file), strlen(rtrim(wp_normalize_path($root), '/'))), '/');
        $source_dir = dirname($relative);
        $map = [];
        foreach ($asset_map as $path => $url) {
            if (preg_match('/\.(?:js|mjs|cjs|css)$/i', $path)) continue;
            $map[str_replace('\\', '/', $path)] = $url;
        }
        $config = ['sourcePath' => '/' . $relative, 'sourceDir' => '/' . ($source_dir === '.' ? '' : $source_dir . '/'), 'map' => $map];
        $runtime = file_get_contents(dirname(__DIR__) . '/public/js/zip-asset-runtime.js');
        if ($runtime === false) throw new RuntimeException('ZIP asset runtime is unavailable.');
        $bytes = '(function(){var config=' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n" . $runtime . "\n})();";
        // Use immutable content names: repairing one route cannot invalidate
        // the verified script manifest of another route in the same import.
        $filename = 'ugentic-zip-runtime-' . substr(hash('sha256', $bytes), 0, 20) . '.js';
        $url = $namespace_url . $filename;
        $dir = $uploads['basedir'] . substr($namespace_url, strlen($uploads['baseurl']));
        if (!wp_mkdir_p($dir) || file_put_contents($dir . $filename, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Could not save the ZIP asset runtime.');
        }
        $html = preg_replace('/<script\b[^>]*\bid=["\']ugentic-zip-asset-runtime["\'][^>]*>[\s\S]*?<\/script>\s*/i', '', $html);
        return preg_replace('/(<head\b[^>]*>)/i', '$1' . "\n" . '<script id="ugentic-zip-asset-runtime" src="' . esc_url($url) . '"></script>', $html, 1);
    }
}
