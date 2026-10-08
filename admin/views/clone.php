<?php
/**
 * Import from AI builders or clone a live website.
 */

if (!defined('ABSPATH')) {
    exit;
}
$ai_clone_ready = class_exists('Ugentic_AI_Clone_Assistant') && Ugentic_AI_Clone_Assistant::is_configured();
$ai_clone_settings_url = class_exists('Ugentic_AI_Clone_Assistant')
    ? Ugentic_AI_Clone_Assistant::settings_url()
    : admin_url('admin.php?page=ugentic-settings#tab-api-keys');
$ai_clone_form_model = class_exists('Ugentic_AI_Clone_Assistant') ? Ugentic_AI_Clone_Assistant::selected_form_model() : '';
?>
<div class="wrap ugentic-wrap ug-clone-center">
    <?php
    Ugentic_Admin_UI::page_header([
        'title' => esc_html__('Import from AI / Clone a Live Site', 'tahmir-ai-to-wp-converter'),
        'description' => esc_html__('Paste a URL, select the source platform, choose one page or a full site, and import it as editable pages.', 'tahmir-ai-to-wp-converter'),
        'icon' => 'dashicons-admin-site-alt2',
        'actions' => [
            [
                'label' => esc_html__('Import HTML instead', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-import'),
                'class' => 'button',
                'icon' => 'dashicons-upload',
            ],
        ],
    ]);
    ?>

    <div class="ugw-wrap ug-clone-shell" id="ugw-wizard" data-ai-clone-ready="<?php echo $ai_clone_ready ? '1' : '0'; ?>">
        <section class="ug-clone-hero">
            <div class="ug-clone-hero__copy">
                <span class="ug-clone-eyebrow"><span class="dashicons dashicons-migrate"></span><?php esc_html_e('URL import', 'tahmir-ai-to-wp-converter'); ?></span>
                <h2><?php esc_html_e('Paste a URL and choose how to import it.', 'tahmir-ai-to-wp-converter'); ?></h2>
                <p><?php esc_html_e('Tahmir supports AI-built sites and conventional websites, discovers public pages, downloads supported assets, and rewrites internal links.', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            <div class="ug-clone-hero__flow" aria-label="<?php esc_attr_e('Clone workflow', 'tahmir-ai-to-wp-converter'); ?>">
                <span><strong>01</strong><?php esc_html_e('Discover', 'tahmir-ai-to-wp-converter'); ?></span>
                <i></i>
                <span><strong>02</strong><?php esc_html_e('Select', 'tahmir-ai-to-wp-converter'); ?></span>
                <i></i>
                <span><strong>03</strong><?php esc_html_e('Import', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>
        </section>

        <section class="ug-clone-card" id="ugw-url-extract-section">
            <div class="ug-clone-card__header">
                <div>
                    <span class="ug-clone-card__step">1</span>
                    <h3><?php esc_html_e('Source website', 'tahmir-ai-to-wp-converter'); ?></h3>
                    <p><?php esc_html_e('Only clone websites you own or have explicit permission to reproduce.', 'tahmir-ai-to-wp-converter'); ?></p>
                </div>
                <span class="ug-clone-secure"><span class="dashicons dashicons-lock"></span><?php esc_html_e('Permission-gated', 'tahmir-ai-to-wp-converter'); ?></span>
            </div>

            <label class="ug-clone-url-label" for="ugw-url-input"><?php esc_html_e('Website or page URL', 'tahmir-ai-to-wp-converter'); ?></label>
            <div class="ug-clone-url-row">
                <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                <input type="url" class="ugw-input" id="ugw-url-input" placeholder="https://your-website.com" autocomplete="url">
            </div>

            <fieldset class="ug-clone-engine">
                <legend><?php esc_html_e('Import intelligence', 'tahmir-ai-to-wp-converter'); ?></legend>
                <label class="ug-clone-engine__option is-selected">
                    <input type="radio" name="ugw-clone-assist" value="standard" checked>
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <span><strong><?php esc_html_e('Faithful import', 'tahmir-ai-to-wp-converter'); ?></strong><small><?php esc_html_e('Capture the site exactly and keep its original structure.', 'tahmir-ai-to-wp-converter'); ?></small></span>
                </label>
                <label class="ug-clone-engine__option<?php echo $ai_clone_ready ? '' : ' is-disabled'; ?>">
                    <input type="radio" name="ugw-clone-assist" value="ai" <?php disabled(!$ai_clone_ready); ?>>
                    <span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
                    <span><strong><?php esc_html_e('AI-assisted faithful', 'tahmir-ai-to-wp-converter'); ?></strong><small><?php esc_html_e('Plans each form\'s fields, steps, submit action, and thank-you flow while preserving the source design.', 'tahmir-ai-to-wp-converter'); ?></small></span>
                    <em><?php echo $ai_clone_ready ? esc_html__('AI ready', 'tahmir-ai-to-wp-converter') : esc_html__('API required', 'tahmir-ai-to-wp-converter'); ?></em>
                </label>
                <?php if (!$ai_clone_ready): ?>
                    <p class="ug-clone-engine__help"><span class="dashicons dashicons-lock"></span><?php esc_html_e('Add an AI provider first to unlock this mode.', 'tahmir-ai-to-wp-converter'); ?> <a href="<?php echo esc_url($ai_clone_settings_url); ?>"><?php esc_html_e('Open AI settings', 'tahmir-ai-to-wp-converter'); ?></a></p>
                <?php else: ?>
                    <p class="ug-clone-engine__help"><span class="dashicons dashicons-shield"></span><?php printf(/* translators: AI model name. */ esc_html__('Uses %s. Route paths plus privacy-filtered form labels, field types, choices, and step headings may be shared. Entered/default values, hidden tokens, unrelated page copy, scripts, and styles stay local.', 'tahmir-ai-to-wp-converter'), esc_html($ai_clone_form_model ?: esc_html__('the selected import model', 'tahmir-ai-to-wp-converter'))); ?> <a href="<?php echo esc_url($ai_clone_settings_url); ?>"><?php esc_html_e('Change model', 'tahmir-ai-to-wp-converter'); ?></a></p>
                <?php endif; ?>
            </fieldset>

            <div class="ug-clone-options">
                <label>
                    <span><?php esc_html_e('Source platform', 'tahmir-ai-to-wp-converter'); ?></span>
                    <select class="ugw-input" id="ugw-url-clone-source">
                        <option value="auto" selected><?php esc_html_e('Auto-detect (recommended)', 'tahmir-ai-to-wp-converter'); ?></option>
                        <option value="ai_studio"><?php esc_html_e('AI Studio', 'tahmir-ai-to-wp-converter'); ?></option>
                        <option value="webflow">Webflow</option>
                        <option value="framer">Framer</option>
                        <option value="lovable">Lovable</option>
                        <option value="replit">Replit</option>
                        <option value="wix">Wix</option>
                        <option value="squarespace">Squarespace</option>
                        <option value="carrd">Carrd</option>
                        <option value="bubble">Bubble</option>
                        <option value="shopify">Shopify</option>
                        <option value="vercel">Vercel</option>
                        <option value="netlify">Netlify</option>
                        <option value="vite">Vite / React</option>
                        <option value="nextjs">Next.js</option>
                        <option value="wordpress">WordPress</option>
                        <option value="generic">Generic HTML</option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Discovery limit', 'tahmir-ai-to-wp-converter'); ?></span>
                    <select class="ugw-input" id="ugw-url-clone-max-pages">
                        <option value="10"><?php esc_html_e('Up to 10 pages', 'tahmir-ai-to-wp-converter'); ?></option>
                        <option value="50" selected><?php esc_html_e('Up to 50 pages', 'tahmir-ai-to-wp-converter'); ?></option>
                        <option value="100"><?php esc_html_e('Up to 100 pages', 'tahmir-ai-to-wp-converter'); ?></option>
                    </select>
                </label>
            </div>

            <div class="ug-clone-mode-grid">
                <button type="button" class="ug-clone-mode ug-clone-mode--primary" id="ugw-url-clone-btn">
                    <span class="dashicons dashicons-admin-multisite"></span>
                    <span><strong><?php esc_html_e('Import entire website', 'tahmir-ai-to-wp-converter'); ?></strong><small><?php esc_html_e('Discover pages first, then choose what to import.', 'tahmir-ai-to-wp-converter'); ?></small></span>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
                <button type="button" class="ug-clone-mode" id="ugw-url-clone-page-btn">
                    <span class="dashicons dashicons-media-document"></span>
                    <span><strong><?php esc_html_e('Import this page only', 'tahmir-ai-to-wp-converter'); ?></strong><small><?php esc_html_e('Import only the exact URL entered above.', 'tahmir-ai-to-wp-converter'); ?></small></span>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>

            <div id="ugw-url-extract-status" class="ug-clone-status" style="display:none;"></div>
        </section>

        <div class="ug-clone-info-grid">
            <article><span class="dashicons dashicons-download"></span><div><strong><?php esc_html_e('Assets come with you', 'tahmir-ai-to-wp-converter'); ?></strong><p><?php esc_html_e('Supported images, CSS, fonts, and scripts are downloaded and repointed to local URLs.', 'tahmir-ai-to-wp-converter'); ?></p></div></article>
            <article><span class="dashicons dashicons-editor-code"></span><div><strong><?php esc_html_e('Faithful and editable', 'tahmir-ai-to-wp-converter'); ?></strong><p><?php esc_html_e('Imported pages retain their visual structure while remaining editable in Tahmir AI.', 'tahmir-ai-to-wp-converter'); ?></p></div></article>
            <article><span class="dashicons dashicons-admin-links"></span><div><strong><?php esc_html_e('Links are rebuilt', 'tahmir-ai-to-wp-converter'); ?></strong><p><?php esc_html_e('Internal links are mapped to the imported WordPress pages wherever possible.', 'tahmir-ai-to-wp-converter'); ?></p></div></article>
        </div>

        <div class="ugw-toast-container" id="ugw-toasts"></div>
    </div>
</div>
