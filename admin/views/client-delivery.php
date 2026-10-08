<?php
/**
 * Client Delivery workspace.
 */
if (!defined('ABSPATH')) {
    exit;
}

$pages = get_posts([
    'post_type' => 'page',
    'post_status' => ['publish', 'draft', 'private'],
    'numberposts' => 500,
    'orderby' => 'title',
    'order' => 'ASC',
]);
$legacy_settings = get_option('ugentic_settings', []);
$legacy_public_access = !empty($legacy_settings['public_edit_enabled']);
?>
<div class="wrap ugentic-wrap tahmir-delivery" id="tahmir-client-delivery">
    <?php
    Ugentic_Admin_UI::page_header([
        'title' => esc_html__('Client Delivery', 'tahmir-ai-to-wp-converter'),
        'description' => esc_html__('Give a client secure editing access without creating a WordPress administrator account.', 'tahmir-ai-to-wp-converter'),
        'icon' => 'dashicons-admin-links',
        'actions' => [[
            'label' => esc_html__('Manage Modules', 'tahmir-ai-to-wp-converter'),
            'url' => admin_url('admin.php?page=ugentic-modules'),
            'class' => 'button',
            'icon' => 'dashicons-screenoptions',
        ]],
    ]);
    ?>

    <section class="td-intro">
        <div>
            <span class="td-eyebrow"><?php esc_html_e('Passwordless handoff', 'tahmir-ai-to-wp-converter'); ?></span>
            <h2><?php esc_html_e('Create a client access URL', 'tahmir-ai-to-wp-converter'); ?></h2>
            <p><?php esc_html_e('Each client receives a separate revocable URL. Choose whether that link opens the visual editor, AI editing, or both.', 'tahmir-ai-to-wp-converter'); ?></p>
        </div>
        <ul>
            <li><span class="dashicons dashicons-lock"></span><?php esc_html_e('No WordPress login required', 'tahmir-ai-to-wp-converter'); ?></li>
            <li><span class="dashicons dashicons-admin-tools"></span><?php esc_html_e('Permissions are controlled per client', 'tahmir-ai-to-wp-converter'); ?></li>
            <li><span class="dashicons dashicons-dismiss"></span><?php esc_html_e('Revoke or rotate a URL at any time', 'tahmir-ai-to-wp-converter'); ?></li>
        </ul>
    </section>

    <div class="td-status<?php echo $legacy_public_access ? ' is-visible is-warning' : ''; ?>" data-delivery-status role="status" aria-live="polite">
        <?php if ($legacy_public_access): ?>
            <strong><?php esc_html_e('Previous site-wide link detected.', 'tahmir-ai-to-wp-converter'); ?></strong>
            <?php esc_html_e('It remains active during the move. Creating your first client URL here retires that old shared link.', 'tahmir-ai-to-wp-converter'); ?>
        <?php endif; ?>
    </div>

    <section class="td-card td-create-card">
        <header>
            <div><span class="td-step">1</span><div><h2><?php esc_html_e('New delivery link', 'tahmir-ai-to-wp-converter'); ?></h2><p><?php esc_html_e('Name the client, choose their first page, and set exactly which editor they can use.', 'tahmir-ai-to-wp-converter'); ?></p></div></div>
        </header>
        <form data-delivery-create>
            <label>
                <span><?php esc_html_e('Client or project name', 'tahmir-ai-to-wp-converter'); ?></span>
                <input type="text" name="client_name" required maxlength="120" placeholder="<?php esc_attr_e('Acme website handoff', 'tahmir-ai-to-wp-converter'); ?>">
            </label>
            <label>
                <span><?php esc_html_e('First page to open', 'tahmir-ai-to-wp-converter'); ?></span>
                <select name="start_page_id" required>
                    <option value=""><?php esc_html_e('Choose a Tahmir AI page', 'tahmir-ai-to-wp-converter'); ?></option>
                    <?php foreach ($pages as $page):
                        if (!get_post_meta($page->ID, '_ugentic_page_id', true) && !ugentic_builder()->database->get_page_by_wp_id($page->ID)) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo esc_attr($page->ID); ?>"><?php echo esc_html(get_the_title($page->ID) ?: sprintf(/* translators: Page identifier. */ esc_html__('Page #%d', 'tahmir-ai-to-wp-converter'), $page->ID)); ?><?php echo $page->post_status !== 'publish' ? ' — ' . esc_html($page->post_status) : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Editing access', 'tahmir-ai-to-wp-converter'); ?></span>
                <select name="access_mode">
                    <option value="both"><?php esc_html_e('AI + visual drag-and-drop', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="visual"><?php esc_html_e('Visual drag-and-drop only', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="ai"><?php esc_html_e('AI editing only', 'tahmir-ai-to-wp-converter'); ?></option>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Link expires after', 'tahmir-ai-to-wp-converter'); ?></span>
                <select name="expires_days">
                    <option value="1"><?php esc_html_e('1 day', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="7" selected><?php esc_html_e('7 days', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="14"><?php esc_html_e('14 days', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="30"><?php esc_html_e('30 days', 'tahmir-ai-to-wp-converter'); ?></option>
                    <option value="90"><?php esc_html_e('90 days', 'tahmir-ai-to-wp-converter'); ?></option>
                </select>
            </label>
            <button type="submit" class="button button-primary"><span class="dashicons dashicons-admin-links"></span><?php esc_html_e('Create client URL', 'tahmir-ai-to-wp-converter'); ?></button>
        </form>
        <p class="td-note"><span class="dashicons dashicons-shield"></span><?php esc_html_e('Client URLs are encrypted at rest, expire automatically, and can be revoked immediately.', 'tahmir-ai-to-wp-converter'); ?></p>
    </section>

    <section class="td-links-section">
        <header class="td-section-heading">
            <div><span class="td-step">2</span><div><h2><?php esc_html_e('Client access', 'tahmir-ai-to-wp-converter'); ?></h2><p><?php esc_html_e('Copy a URL, change its editor permissions, rotate it, or revoke access.', 'tahmir-ai-to-wp-converter'); ?></p></div></div>
        </header>
        <div class="td-links" data-delivery-links></div>
    </section>
</div>
