<?php
/**
 * Conversion Studio workspace.
 */
if (!defined('ABSPATH')) {
    exit;
}
$module_context = $module_context ?? '';
$module_definition = $module_context ? ($conversion_suite->module_definitions()[$module_context] ?? null) : null;
$context_tab = $module_context ? $conversion_suite->admin_tab_for_module($module_context) : '';
?>
<div class="wrap tahmir-conversion<?php echo $module_definition ? ' is-module-focus' : ''; ?>" id="tahmir-conversion-app" data-context-tab="<?php echo esc_attr($context_tab); ?>">
    <header class="tc-hero">
        <div>
            <span class="tc-eyebrow"><?php echo $module_definition ? esc_html__('Enabled module', 'tahmir-ai-to-wp-converter') : esc_html__('Growth workspace', 'tahmir-ai-to-wp-converter'); ?></span>
            <h1><?php echo $module_definition ? esc_html($module_definition['label']) : esc_html__('Website overview', 'tahmir-ai-to-wp-converter'); ?></h1>
            <p><?php echo $module_definition ? esc_html($module_definition['description']) : esc_html__('Understand visitor behavior, run controlled page experiments, and turn evidence into improvements.', 'tahmir-ai-to-wp-converter'); ?></p>
            <?php if ($module_definition): ?>
                <div class="tc-context-benefit"><strong><?php esc_html_e('Benefit:', 'tahmir-ai-to-wp-converter'); ?></strong> <?php echo esc_html($module_definition['benefit']); ?></div>
            <?php endif; ?>
        </div>
        <div class="tc-hero__actions">
            <div class="tc-period-controls"<?php echo $context_tab && $context_tab !== 'overview' ? ' hidden' : ''; ?>>
            <select id="tc-range" aria-label="<?php esc_attr_e('Report period', 'tahmir-ai-to-wp-converter'); ?>"<?php echo $context_tab && $context_tab !== 'overview' ? ' hidden' : ''; ?>>
                <option value="1"><?php esc_html_e('Last 24 hours', 'tahmir-ai-to-wp-converter'); ?></option>
                <option value="7"><?php esc_html_e('Last 7 days', 'tahmir-ai-to-wp-converter'); ?></option>
                <option value="30" selected><?php esc_html_e('Last 30 days', 'tahmir-ai-to-wp-converter'); ?></option>
                <option value="90"><?php esc_html_e('Last 90 days', 'tahmir-ai-to-wp-converter'); ?></option>
                <option value="180"><?php esc_html_e('Last 180 days', 'tahmir-ai-to-wp-converter'); ?></option>
                <option value="365"><?php esc_html_e('Last 12 months', 'tahmir-ai-to-wp-converter'); ?></option>
            </select>
            <select id="tc-granularity" aria-label="<?php esc_attr_e('Chart interval', 'tahmir-ai-to-wp-converter'); ?>"><option value="daily"><?php esc_html_e('Daily', 'tahmir-ai-to-wp-converter'); ?></option><option value="weekly"><?php esc_html_e('Weekly', 'tahmir-ai-to-wp-converter'); ?></option><option value="monthly"><?php esc_html_e('Monthly', 'tahmir-ai-to-wp-converter'); ?></option></select>
            <button type="button" class="button tc-refresh" data-refresh-report aria-label="<?php esc_attr_e('Refresh report', 'tahmir-ai-to-wp-converter'); ?>" title="<?php esc_attr_e('Refresh report', 'tahmir-ai-to-wp-converter'); ?>">↻</button>
            </div>
            <button type="button" class="button tc-button tc-button--primary<?php echo $context_tab && $context_tab !== 'experiments' ? ' is-context-hidden' : ''; ?>" data-open-experiment><?php esc_html_e('New experiment', 'tahmir-ai-to-wp-converter'); ?></button>
            <?php if ($module_definition): ?>
                <a class="button tc-button" href="<?php echo esc_url(admin_url('admin.php?page=ugentic-modules')); ?>"><?php esc_html_e('Manage modules', 'tahmir-ai-to-wp-converter'); ?></a>
            <?php endif; ?>
        </div>
    </header>

    <p class="tc-report-freshness" data-report-freshness></p>

    <nav class="tc-tabs" aria-label="<?php esc_attr_e('Conversion workspace', 'tahmir-ai-to-wp-converter'); ?>">
        <button type="button" class="is-active" data-tab="overview"><?php esc_html_e('Overview', 'tahmir-ai-to-wp-converter'); ?></button>
        <button type="button" data-tab="journeys"><?php esc_html_e('Journeys', 'tahmir-ai-to-wp-converter'); ?></button>
        <button type="button" data-tab="heatmaps"><?php esc_html_e('Heatmaps', 'tahmir-ai-to-wp-converter'); ?></button>
        <button type="button" data-tab="experiments"><?php esc_html_e('Experiments', 'tahmir-ai-to-wp-converter'); ?></button>
        <button type="button" data-tab="intelligence"><?php esc_html_e('Intelligence', 'tahmir-ai-to-wp-converter'); ?></button>
    </nav>

    <main>
        <section class="tc-panel is-active" data-panel="overview">
            <div class="tc-module-notice" data-analytics-notice hidden>
                <div><strong><?php esc_html_e('Website Analytics is off', 'tahmir-ai-to-wp-converter'); ?></strong><span><?php esc_html_e('Enable it in Modules to collect lightweight, first-party visitor journeys.', 'tahmir-ai-to-wp-converter'); ?></span></div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ugentic-modules')); ?>"><?php esc_html_e('Open Modules', 'tahmir-ai-to-wp-converter'); ?></a>
            </div>
            <div class="tc-kpis" data-kpis></div>
            <article class="tc-card tc-card--dashboard">
                <div class="tc-chart-heading"><div><h2 data-chart-title><?php esc_html_e('Visits', 'tahmir-ai-to-wp-converter'); ?></h2><p data-chart-description><?php esc_html_e('Visits by day. Select a number above to explore another measure.', 'tahmir-ai-to-wp-converter'); ?></p></div><div class="tc-chart-legend"><span><i></i><b data-chart-primary-label><?php esc_html_e('Visits', 'tahmir-ai-to-wp-converter'); ?></b></span><span><i></i><b data-chart-secondary-label><?php esc_html_e('Completed visits', 'tahmir-ai-to-wp-converter'); ?></b></span></div></div>
                <div class="tc-chart" data-trend-chart></div>
            </article>
            <div class="tc-grid tc-grid--insights">
                <article class="tc-card tc-retention-card"><header><div><span class="tc-card__kicker">Retention</span><h2>Which sources bring people back?</h2></div></header><div data-retention-winner></div><div data-retention-overview></div><p class="tc-note">Returning means the same anonymous browser visited in another session. Compare sources with enough visitors to be meaningful.</p></article>
                <article class="tc-card tc-retention-card"><header><div><span class="tc-card__kicker">Attribution</span><h2>Social handles and locations</h2></div></header><div class="tc-attribution-grid"><div><h3>Shared by</h3><div data-source-handles></div></div><div><h3>Visited from</h3><div data-locations></div></div></div><p class="tc-note">Locations are approximate. When the host provides no city or country, Tahmir uses a brief, cached IP geolocation lookup.</p></article>
                <article class="tc-card tc-discovery-card"><header><div><span class="tc-card__kicker"><?php esc_html_e('Source', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Where visits came from', 'tahmir-ai-to-wp-converter'); ?></h2></div></header><div class="tc-card-tabs" role="tablist" aria-label="<?php esc_attr_e('Source breakdown', 'tahmir-ai-to-wp-converter'); ?>"><button type="button" class="is-active" data-breakdown="source" data-breakdown-view="source">Source</button><button type="button" data-breakdown="source" data-breakdown-view="referrer">Referrer</button><button type="button" data-breakdown="source" data-breakdown-view="campaign">Campaign</button><button type="button" data-breakdown="source" data-breakdown-view="keyword">Keyword</button></div><div data-sources></div><p class="tc-note" data-source-note><?php esc_html_e('Based on the first page of each visit.', 'tahmir-ai-to-wp-converter'); ?></p></article>
                <article class="tc-card tc-discovery-card"><header><div><span class="tc-card__kicker"><?php esc_html_e('Path', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Pages in the journey', 'tahmir-ai-to-wp-converter'); ?></h2></div></header><div class="tc-card-tabs" role="tablist" aria-label="<?php esc_attr_e('Page breakdown', 'tahmir-ai-to-wp-converter'); ?>"><button type="button" class="is-active" data-breakdown="path" data-breakdown-view="page">Page</button><button type="button" data-breakdown="path" data-breakdown-view="entry">Entry page</button><button type="button" data-breakdown="path" data-breakdown-view="exit">Last seen</button></div><div data-pages></div><p class="tc-note" data-path-note><?php esc_html_e('Visits with a view of each page.', 'tahmir-ai-to-wp-converter'); ?></p></article>
                <article class="tc-card tc-discovery-card"><header><div><span class="tc-card__kicker"><?php esc_html_e('Activity', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('What visitors did', 'tahmir-ai-to-wp-converter'); ?></h2></div></header><div class="tc-card-tabs" role="tablist" aria-label="<?php esc_attr_e('Activity breakdown', 'tahmir-ai-to-wp-converter'); ?>"><button type="button" class="is-active" data-breakdown="activity" data-breakdown-view="click">Clicks</button><button type="button" data-breakdown="activity" data-breakdown-view="form">Forms</button><button type="button" data-breakdown="activity" data-breakdown-view="scroll">Scroll depth</button></div><div data-heatmap></div><p class="tc-note" data-activity-note><?php esc_html_e('Tracked clicks on buttons and links.', 'tahmir-ai-to-wp-converter'); ?></p></article>
                <article class="tc-card tc-discovery-card"><header><div><span class="tc-card__kicker"><?php esc_html_e('Outcomes', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Where attention ended', 'tahmir-ai-to-wp-converter'); ?></h2></div></header><div class="tc-card-tabs" role="tablist" aria-label="<?php esc_attr_e('Outcome breakdown', 'tahmir-ai-to-wp-converter'); ?>"><button type="button" class="is-active" data-breakdown="outcome" data-breakdown-view="dropoff">Last seen</button><button type="button" data-breakdown="outcome" data-breakdown-view="payments">Payments</button></div><div data-dropoffs></div><p class="tc-note" data-outcome-note><?php esc_html_e('Based on the 50 most recent tracked visitors. Last seen is an observation, not proof of abandonment.', 'tahmir-ai-to-wp-converter'); ?></p></article>
            </div>
            <article class="tc-card tc-card--session-preview"><header><div><span class="tc-card__kicker"><?php esc_html_e('Journey', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Recent visitor sessions', 'tahmir-ai-to-wp-converter'); ?></h2></div><button type="button" class="button" data-tab="journeys"><?php esc_html_e('See all journeys', 'tahmir-ai-to-wp-converter'); ?> →</button></header><div data-session-preview></div></article>
            <article class="tc-card tc-card--paths"><header><div><span class="tc-card__kicker"><?php esc_html_e('Common routes', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Popular paths', 'tahmir-ai-to-wp-converter'); ?></h2></div></header><div data-journey-paths></div></article>
            <article class="tc-card tc-card--ai"><header><div><span class="tc-card__kicker"><?php esc_html_e('AI analysis', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('What could improve conversions?', 'tahmir-ai-to-wp-converter'); ?></h2><p><?php esc_html_e('Ask the connected AI to review the measured sources, pages, forms, and exits for this period.', 'tahmir-ai-to-wp-converter'); ?></p></div><button type="button" class="button tc-button tc-button--primary" data-ai-overview><?php esc_html_e('Analyze this report', 'tahmir-ai-to-wp-converter'); ?></button></header><div class="tc-ai-overview__answer" data-ai-overview-answer aria-live="polite"></div></article>
        </section>

        <section class="tc-panel" data-panel="journeys">
            <div class="tc-section-heading"><div><span class="tc-eyebrow"><?php esc_html_e('Visitor activity', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Journey maps', 'tahmir-ai-to-wp-converter'); ?></h2><p><?php esc_html_e('Each card is one visit. A visitor may have several visits. Cookie IDs are anonymous and can reset when cookies are cleared.', 'tahmir-ai-to-wp-converter'); ?></p></div><div class="tc-journey-tools"><span class="tc-journey-count" data-journey-count></span><button type="button" class="button" data-clear-journey-filter hidden><?php esc_html_e('Show all', 'tahmir-ai-to-wp-converter'); ?></button></div></div>
            <div class="tc-session-list" data-recent-journeys></div>
        </section>

        <section class="tc-panel" data-panel="heatmaps">
            <article class="tc-card"><header><div><span class="tc-card__kicker">Behavior on the page</span><h2>Page heatmaps</h2><p>See which buttons and links receive attention, and how far a page was visible.</p></div></header>
                <div class="tc-heat-controls"><label>Page<select data-heat-page><option value="">Choose a page</option></select></label><label>Recorded screen size<select data-heat-layout><option value="">Load a page first</option></select></label><label>Show<select data-heat-mode><option value="click">Click intensity</option><option value="scroll">Scroll reach</option></select></label><button type="button" class="button" data-heat-load>Load heatmap</button></div>
                <p class="tc-note" data-heat-status aria-live="polite">Heatmaps load only when requested. No page recording or form values are collected.</p>
                <label class="tc-heat-position" data-heat-position-wrap hidden>Preview position <input type="range" min="0" max="0" value="0" step="20" data-heat-position aria-label="Scroll the page preview"></label>
                <div class="tc-heat-legend"><span>Less attention</span><i></i><span>More attention</span></div>
                <div class="tc-heat-scroll" data-heat-scroll hidden><div class="tc-heat-stage" data-heat-stage><iframe title="Read-only page heatmap preview" sandbox="allow-same-origin" referrerpolicy="no-referrer" data-heat-frame></iframe><div class="tc-heat-overlay" data-heat-overlay></div></div></div>
                <div data-heat-details></div>
                <p class="tc-note">Click intensity highlights matched elements, not guessed mouse positions. Dynamic or changed elements are left unmapped. Scroll percentages describe visible reach, not reading or attention. Maps use a bounded recent sample from the selected period.</p>
            </article>
        </section>

        <section class="tc-panel" data-panel="experiments">
            <div class="tc-section-heading">
                <div><span class="tc-eyebrow"><?php esc_html_e('Controlled learning', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Page experiments', 'tahmir-ai-to-wp-converter'); ?></h2><p><?php esc_html_e('Use multiple published pages at one URL. Allocate by percentage, source, or Smart Traffic.', 'tahmir-ai-to-wp-converter'); ?></p></div>
                <button type="button" class="button tc-button tc-button--primary" data-open-experiment><?php esc_html_e('New experiment', 'tahmir-ai-to-wp-converter'); ?></button>
            </div>
            <div class="tc-experiment-list" data-experiments></div>
        </section>

        <section class="tc-panel" data-panel="intelligence">
            <div class="tc-grid tc-grid--half">
                <article class="tc-card">
                    <header><div><span class="tc-card__kicker"><?php esc_html_e('AI Conversion Agent', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Recommended next moves', 'tahmir-ai-to-wp-converter'); ?></h2></div></header>
                    <div class="tc-insights" data-insights></div>
                    <button type="button" class="button tc-button tc-button--primary" data-ai-analyze><?php esc_html_e('Analyze conversions with AI', 'tahmir-ai-to-wp-converter'); ?></button>
                    <div class="tc-chat__answer" data-ai-analysis aria-live="polite"></div>
                </article>
                <article class="tc-card tc-chat">
                    <header><div><span class="tc-card__kicker"><?php esc_html_e('Website Data Chat', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Ask your website', 'tahmir-ai-to-wp-converter'); ?></h2></div></header>
                    <div class="tc-chat__answer" data-chat-answer><?php esc_html_e('Ask why performance changed, which source converts, or which paths visitors take before leaving.', 'tahmir-ai-to-wp-converter'); ?></div>
                    <form data-chat-form>
                        <label class="screen-reader-text" for="tc-question"><?php esc_html_e('Question', 'tahmir-ai-to-wp-converter'); ?></label>
                        <textarea id="tc-question" rows="3" placeholder="<?php esc_attr_e('Why did conversions change this month?', 'tahmir-ai-to-wp-converter'); ?>"></textarea>
                        <button type="submit" class="button tc-button tc-button--primary"><?php esc_html_e('Ask', 'tahmir-ai-to-wp-converter'); ?></button>
                    </form>
                </article>
            </div>
        </section>

    </main>

    <div class="tc-journey-sheet" data-journey-sheet hidden>
        <div class="tc-journey-sheet__backdrop" data-close-journey></div>
        <section class="tc-journey-sheet__dialog" role="dialog" aria-modal="true" aria-labelledby="tc-thread-title" tabindex="-1">
            <header><div><span class="tc-card__kicker">Session thread</span><h2 id="tc-thread-title">Visitor journey</h2></div><button type="button" class="tc-icon-button" data-close-journey aria-label="Close journey">×</button></header>
            <div data-journey-detail></div>
        </section>
    </div>
    <div class="tc-modal" data-experiment-modal hidden>
        <div class="tc-modal__backdrop" data-close-experiment></div>
        <section class="tc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tc-experiment-title">
            <header><div><span class="tc-eyebrow"><?php esc_html_e('Experiment setup', 'tahmir-ai-to-wp-converter'); ?></span><h2 id="tc-experiment-title"><?php esc_html_e('New page experiment', 'tahmir-ai-to-wp-converter'); ?></h2></div><button type="button" class="tc-icon-button" data-close-experiment aria-label="<?php esc_attr_e('Close', 'tahmir-ai-to-wp-converter'); ?>">×</button></header>
            <form data-experiment-form>
                <input type="hidden" name="experiment_id" value="0">
                <div class="tc-form-grid">
                    <label><span><?php esc_html_e('Name', 'tahmir-ai-to-wp-converter'); ?></span><input type="text" name="name" required placeholder="<?php esc_attr_e('Homepage message test', 'tahmir-ai-to-wp-converter'); ?>"></label>
                    <label><span><?php esc_html_e('Public URL path', 'tahmir-ai-to-wp-converter'); ?></span><input type="text" name="target_path" value="/" required placeholder="/"></label>
                    <label><span><?php esc_html_e('Primary goal', 'tahmir-ai-to-wp-converter'); ?></span><input type="text" name="primary_goal" value="conversion" placeholder="lead"></label>
                    <label><span><?php esc_html_e('Cookie days', 'tahmir-ai-to-wp-converter'); ?></span><input type="number" name="cookie_days" min="1" max="365" value="30"></label>
                </div>
                <div class="tc-check-row">
                    <label><input type="checkbox" name="sticky"> <?php esc_html_e('Keep each visitor on the same variant', 'tahmir-ai-to-wp-converter'); ?></label>
                    <label><input type="checkbox" name="smart_traffic"> <?php esc_html_e('Use Smart Traffic', 'tahmir-ai-to-wp-converter'); ?></label>
                </div>
                <div class="tc-variant-heading"><div><h3><?php esc_html_e('Variants', 'tahmir-ai-to-wp-converter'); ?></h3><p><?php esc_html_e('Optional UTM rules take priority when a source matches.', 'tahmir-ai-to-wp-converter'); ?></p></div><button type="button" class="button" data-add-variant><?php esc_html_e('Add page', 'tahmir-ai-to-wp-converter'); ?></button></div>
                <div data-variant-rows></div>
                <footer><button type="button" class="button" data-close-experiment><?php esc_html_e('Cancel', 'tahmir-ai-to-wp-converter'); ?></button><button type="submit" class="button tc-button tc-button--primary"><?php esc_html_e('Save experiment', 'tahmir-ai-to-wp-converter'); ?></button></footer>
            </form>
        </section>
    </div>
    <div class="tc-toast" role="status" aria-live="polite" data-toast hidden></div>
</div>
