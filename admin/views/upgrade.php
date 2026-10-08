<?php
/** A single, contextual showcase for the separately installed Pro add-on. */
if (!defined('ABSPATH')) { exit; }
$benefits = [
    ['dashicons-edit-page', esc_html__('Visual Editor — Edit your imported pages', 'tahmir-ai-to-wp-converter'), esc_html__('Update text, images, links, layout and page modules with the Pro visual editor.', 'tahmir-ai-to-wp-converter'), esc_html__('For websites that need ongoing content and design changes.', 'tahmir-ai-to-wp-converter')],
    ['dashicons-feedback', esc_html__('Form Builder & Integrations — Turn visitors into enquiries', 'tahmir-ai-to-wp-converter'), esc_html__('Pro includes Form Builder, field editing, submission handling, entry management, form settings, email notifications and webhooks.', 'tahmir-ai-to-wp-converter'), esc_html__('For service websites, lead generation, and appointment requests.', 'tahmir-ai-to-wp-converter')],
    ['dashicons-admin-links', esc_html__('Client Delivery — Deliver a website to your client', 'tahmir-ai-to-wp-converter'), esc_html__('Create a separate, revocable editing link for each client. Choose their pages and whether they can use visual editing, AI, or both.', 'tahmir-ai-to-wp-converter'), esc_html__('For freelancers and agencies handing over a finished website.', 'tahmir-ai-to-wp-converter')],
    ['dashicons-superhero', esc_html__('AI Builder & AI Editing — Make changes with AI', 'tahmir-ai-to-wp-converter'), esc_html__('Describe the change you want, generate sections or pages, work with images, and apply reviewed changes across several pages.', 'tahmir-ai-to-wp-converter'), esc_html__('For revisions, campaign pages, and faster content production.', 'tahmir-ai-to-wp-converter')],
    ['dashicons-search', esc_html__('SEO & AI Search — Prepare your pages for search', 'tahmir-ai-to-wp-converter'), esc_html__('Review SEO issues, edit search metadata, and use the SEO and AI Search tools to improve page structure and machine-readable information.', 'tahmir-ai-to-wp-converter'), esc_html__('For business websites that need a considered search presence.', 'tahmir-ai-to-wp-converter')],
    ['dashicons-layout', esc_html__('Global Sections, Menus & Assets — Keep the whole website consistent', 'tahmir-ai-to-wp-converter'), esc_html__('Manage shared headers, footers, menus, global sections, and assets. The visual editor, page modules and all form features require Pro.', 'tahmir-ai-to-wp-converter'), esc_html__('For sites where one brand change needs to reach many pages.', 'tahmir-ai-to-wp-converter')],
    ['dashicons-chart-area', esc_html__('Website Analytics & Experiments — Learn what helps visitors take action', 'tahmir-ai-to-wp-converter'), esc_html__('Enable website analytics, inspect journeys, and run controlled page experiments. Use conversion tools to review evidence before changing a page.', 'tahmir-ai-to-wp-converter'), esc_html__('For campaigns and landing pages you improve over time.', 'tahmir-ai-to-wp-converter')],
    ['dashicons-welcome-write-blog', esc_html__('Blog, Accessibility, Performance & Revisions — Build a website that keeps growing', 'tahmir-ai-to-wp-converter'), esc_html__('Work with dynamic blog layouts, content generation, accessibility options, performance tools, and page revision workflows.', 'tahmir-ai-to-wp-converter'), esc_html__('For publications and business websites with ongoing updates.', 'tahmir-ai-to-wp-converter')],
    ['dashicons-rest-api', esc_html__('MCP Access & Agency Tools — Connect your workflow', 'tahmir-ai-to-wp-converter'), esc_html__('Use authenticated MCP access and the agency tools to connect supported assistants and manage client work from the Tahmir workspace.', 'tahmir-ai-to-wp-converter'), esc_html__('For teams that want repeatable website delivery workflows.', 'tahmir-ai-to-wp-converter')],
];
?>
<div class="wrap ugentic-wrap tahmir-upgrade">
    <header class="tu-hero">
        <span class="tu-eyebrow"><?php esc_html_e('Tahmir AI Pro', 'tahmir-ai-to-wp-converter'); ?></span>
        <h1><?php esc_html_e('Bring the rest of your website workflow into Tahmir.', 'tahmir-ai-to-wp-converter'); ?></h1>
        <p><?php esc_html_e('Free includes website cloning, HTML import and imported page publishing. Add Pro for the visual editor, all form features, client handoff, AI and advanced website tools.', 'tahmir-ai-to-wp-converter'); ?></p>
        <a class="tu-primary" href="https://tahmir.com/upgrade" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Explore Pro plans', 'tahmir-ai-to-wp-converter'); ?><span aria-hidden="true"> ↗</span></a>
        <span class="tu-note"><?php esc_html_e('Same interface. Same pages. More possibilities.', 'tahmir-ai-to-wp-converter'); ?></span>
    </header>
    <section class="tu-benefits" aria-label="<?php esc_attr_e('What you can do with Pro', 'tahmir-ai-to-wp-converter'); ?>">
        <?php foreach ($benefits as [$icon,$title,$description,$application]): ?>
        <article class="tu-benefit">
            <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            <h2><?php echo esc_html($title); ?></h2>
            <p><?php echo esc_html($description); ?></p>
            <p class="tu-application"><?php echo esc_html($application); ?></p>
        </article>
        <?php endforeach; ?>
    </section>
    <section class="tu-next">
        <div><h2><?php esc_html_e('Keep everything you have already built.', 'tahmir-ai-to-wp-converter'); ?></h2><p><?php esc_html_e('Install Pro alongside Free, activate your Pro license, and continue with your existing pages. There is no need to import the website again.', 'tahmir-ai-to-wp-converter'); ?></p></div>
        <div class="tu-free"><strong><?php esc_html_e('Free stays free', 'tahmir-ai-to-wp-converter'); ?></strong><p><?php esc_html_e('Cloning, HTML import and imported page publishing do not need an account or license.', 'tahmir-ai-to-wp-converter'); ?></p></div>
    </section>
    <p class="tu-footnote"><?php esc_html_e('Pro features require an active Pro license. AI tools also require a supported provider or available account credits. Enable only the optional modules you need.', 'tahmir-ai-to-wp-converter'); ?></p>
    <?php do_action('tahmir_upgrade_after_showcase'); ?>
</div>
