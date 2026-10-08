<?php
/** Finish imported client-rendered content before opening the original editor. */
if (!defined('ABSPATH')) { exit; }
final class Tahmir_Rendered_Import {
    public static function needs_capture($page) {
        $meta=json_decode($page->meta_data??'{}',true);
        if (($meta['import_mode']??'')!=='faithful' || !empty($meta['client_shell_materialized'])) { return false; }
        $html=(string)$page->current_html;
        if (!preg_match_all('/<(?:div|main|section)\b[^>]*\bid=["\']([^"\']+)["\'][^>]*>\s*<\/(?:div|main|section)>/i',$html,$matches)) { return false; }
        $manifest=get_post_meta($page->wp_page_id,'_ugentic_imported_script_manifest_v1',true);
        if (!is_array($manifest)) { return false; }
        $uploads=wp_upload_dir();
        foreach ($manifest as $entry) {
            $path=Ugentic_Security::resolve_verified_imported_script_path($entry['upload_relative']??'',$uploads['basedir']);
            if (!$path || !is_readable($path['file']) || filesize($path['file'])>2097152 || !hash_equals((string)($entry['sha256']??''),hash_file('sha256',$path['file']))) { continue; }
            $script=file_get_contents($path['file']);
            if (!preg_match('/\.(?:innerHTML|outerHTML)\s*=|\.replaceChildren\s*\(/',$script)) { continue; }
            foreach ($matches[1] as $id) {
                if (strpos($script,'#'.$id)!==false || preg_match('/getElementById\(\s*["\']'.preg_quote($id,'/').'["\']\s*\)/',$script)) { return true; }
            }
        }
        return false;
    }
    public static function capture_preview() {
        $id=get_queried_object_id();
        return isset($_GET['tahmir_capture_import'],$_GET['_wpnonce'])
            && current_user_can('edit_post',$id)
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'tahmir_capture_import_'.$id);
    }
    public static function prepare() {
        if (!is_page() || !is_user_logged_in() || !ugentic_builder()->editor->should_load_builder()) { return; }
        $id=get_queried_object_id();
        if (!current_user_can('edit_post',$id)) { return; }
        $page=ugentic_builder()->database->get_page((int)get_post_meta($id,'_ugentic_page_id',true));
        if (!$page || !self::needs_capture($page)) { return; }
        $preview=add_query_arg(['tahmir_capture_import'=>1,'_wpnonce'=>wp_create_nonce('tahmir_capture_import_'.$id)],get_permalink($id));
        $config=['pages_imported'=>[['wp_page_id'=>$id,'ugentic_id'=>(int)$page->id,'title'=>get_the_title($id),'view_url'=>$preview,'client_rendered_shell'=>true,'snapshot_baseline'=>hash('sha256',$page->current_html)]],'editor_url'=>add_query_arg('tahmir_builder','1',get_permalink($id))];
        wp_enqueue_style('tahmir-import-preparation',UGENTIC_PLUGIN_URL.'public/css/import-preparation.css',[],UGENTIC_VERSION);
        wp_enqueue_script('ugentic-clone-capture',UGENTIC_PLUGIN_URL.'admin/js/clone-capture.js',[],UGENTIC_VERSION,true);
        wp_enqueue_script('tahmir-import-capture',UGENTIC_PLUGIN_URL.'admin/js/wizard.js',['jquery','ugentic-clone-capture'],UGENTIC_VERSION,true);
        wp_localize_script('tahmir-import-capture','ugenticWizard',['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('ugentic_wizard_nonce')]);
        wp_enqueue_script('tahmir-import-preparation',UGENTIC_PLUGIN_URL.'public/js/import-preparation.js',['tahmir-import-capture'],UGENTIC_VERSION,true);
        wp_localize_script('tahmir-import-preparation','TahmirImportPreparation',$config);
        status_header(200);nocache_headers();
        echo '<!doctype html><html lang="'.esc_attr(get_bloginfo('language')).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.esc_html__('Preparing your page','tahmir-ai-to-wp-converter').'</title>';
        wp_print_styles(['tahmir-import-preparation']);
        echo '</head><body><main class="tahmir-preparation"><span class="tahmir-preparation-label">Tahmir AI</span><h1>'.esc_html__('Preparing the complete page for editing','tahmir-ai-to-wp-converter').'</h1><p>'.esc_html__('This import builds some sections when the page loads. We are preparing those sections, images and navigation so the whole page is editable.','tahmir-ai-to-wp-converter').'</p><div id="tahmir-import-progress" role="status" aria-live="polite"></div><div id="tahmir-import-recovery" hidden><p>'.esc_html__('Your existing page is unchanged. Retry when its images and content can finish loading.','tahmir-ai-to-wp-converter').'</p><button type="button" id="tahmir-import-retry">'.esc_html__('Try again','tahmir-ai-to-wp-converter').'</button></div><a href="'.esc_url(get_permalink($id)).'">'.esc_html__('Back to preview','tahmir-ai-to-wp-converter').'</a></main>';
        wp_print_scripts(['tahmir-import-preparation']);echo '</body></html>';exit;
    }
}
add_action('template_redirect',['Tahmir_Rendered_Import','prepare'],1);
