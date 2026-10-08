<?php
/**
 * Minimal module switchboard.
 */
if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap ugentic-wrap tahmir-modules" id="tahmir-modules-app">
    <header class="tm-hero">
        <div class="tm-hero__copy">
            <span class="tm-eyebrow"><?php esc_html_e('Choose only what you need', 'tahmir-ai-to-wp-converter'); ?></span>
            <h1><?php esc_html_e('Modules', 'tahmir-ai-to-wp-converter'); ?></h1>
            <p><?php esc_html_e('Turn a capability on to add its workspace under Tahmir AI. Turn it off to remove it from the sidebar.', 'tahmir-ai-to-wp-converter'); ?></p>
            <div class="tm-hero__note">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <?php esc_html_e('Changes save instantly and the page reloads to update your menu.', 'tahmir-ai-to-wp-converter'); ?>
            </div>
        </div>
        <img src="<?php echo esc_url(UGENTIC_PLUGIN_URL . 'public/images/tahmir-ai-module-visual.png'); ?>" alt="" aria-hidden="true">
    </header>

    <div class="tm-status" role="status" aria-live="polite" data-module-status></div>

    <section class="tm-group" aria-labelledby="tm-all-modules">
        <header class="tm-group__header">
            <h2 id="tm-all-modules"><?php esc_html_e('Available modules', 'tahmir-ai-to-wp-converter'); ?></h2>
            <span><?php echo esc_html(sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ _n('%d module', '%d modules', count($module_definitions), 'tahmir-ai-to-wp-converter'), count($module_definitions))); ?></span>
        </header>
        <div class="tm-grid">
                <?php foreach ($module_definitions as $module_key => $definition):
                    $enabled = !empty($modules[$module_key]);
                    $module_url = admin_url('admin.php?page=ugentic-module-' . $module_key);
                    ?>
                    <article class="tm-card<?php echo $enabled ? ' is-enabled' : ''; ?>" data-module-card="<?php echo esc_attr($module_key); ?>">
                        <div class="tm-card__top">
                            <div>
                                <span class="tm-card__state" data-module-state><?php echo $enabled ? esc_html__('On', 'tahmir-ai-to-wp-converter') : esc_html__('Off', 'tahmir-ai-to-wp-converter'); ?></span>
                                <h3><?php echo esc_html($definition['label']); ?></h3>
                            </div>
                            <label class="tm-switch">
                                <span class="screen-reader-text"><?php echo esc_html(sprintf(/* translators: Module name. */ esc_html__('Toggle %s', 'tahmir-ai-to-wp-converter'), $definition['label'])); ?></span>
                                <input type="checkbox" data-module="<?php echo esc_attr($module_key); ?>" <?php checked($enabled); ?>>
                                <span class="tm-switch__track" aria-hidden="true"><span></span></span>
                            </label>
                        </div>
                        <p class="tm-card__description"><?php echo esc_html($definition['description']); ?></p>
                        <div class="tm-card__benefit">
                            <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                            <p><strong><?php esc_html_e('Benefit', 'tahmir-ai-to-wp-converter'); ?></strong><?php echo esc_html($definition['benefit']); ?></p>
                        </div>
                        <?php if ($enabled): ?>
                            <a class="tm-card__open" href="<?php echo esc_url($module_url); ?>">
                                <?php esc_html_e('Open workspace', 'tahmir-ai-to-wp-converter'); ?>
                                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
        </div>
    </section>

    <p class="tm-dependency-note">
        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
        <?php esc_html_e('When a feature needs another module, Tahmir enables that dependency automatically.', 'tahmir-ai-to-wp-converter'); ?>
    </p>
</div>
