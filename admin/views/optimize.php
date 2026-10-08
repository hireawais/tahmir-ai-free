<?php
/**
 * SEO & Tools workspace.
 */

if (!defined('ABSPATH')) {
    exit;
}

$seo = $seo ?? (ugentic_builder()->seo ?? null);
$pages = $pages ?? [];
$hub = $hub ?? Ugentic_Admin_Menu::get_hub('optimize');
$settings = ($seo && method_exists($seo, 'get_settings')) ? $seo->get_settings() : ['enabled' => false];
$nonce = wp_create_nonce('ugentic_admin_nonce');

$total_pages = count($pages);
$score_total = 0;
$score_count = 0;
$status_counts = [
    'unscanned' => 0,
    'pending_review' => 0,
    'approved' => 0,
    'stale' => 0,
    'approved_stale' => 0,
    'queued_until_publish' => 0,
];
$onpage_issue_counts = [
    'alt_text' => 0,
    'heading' => 0,
    'content_gaps' => 0,
    'thin_copy_pages' => 0,
    'missing_links_pages' => 0,
    'faq_pages' => 0,
    'trust_pages' => 0,
];
$ai_counts = [
    'summaries' => 0,
    'schema' => 0,
    'queries' => 0,
    'sections' => 0,
    'content_gaps' => 0,
];
$impact_counts = [
    'pages' => 0,
    'metadata_fields' => 0,
    'alt_text' => 0,
    'ai_summaries' => 0,
    'target_queries' => 0,
    'schema_graphs' => 0,
];
$gap_counts = [];
$low_score_pages = 0;
$review_ready_pages = 0;
$page_rows = [];
$category_labels = [
    'metadata' => esc_html__('Metadata', 'tahmir-ai-to-wp-converter'),
    'content' => esc_html__('Content', 'tahmir-ai-to-wp-converter'),
    'structure' => esc_html__('Structure', 'tahmir-ai-to-wp-converter'),
    'media' => esc_html__('Media', 'tahmir-ai-to-wp-converter'),
    'links' => esc_html__('Links', 'tahmir-ai-to-wp-converter'),
    'technical' => esc_html__('Technical', 'tahmir-ai-to-wp-converter'),
    'schema' => esc_html__('Schema', 'tahmir-ai-to-wp-converter'),
    'social_ai' => esc_html__('Social & AI', 'tahmir-ai-to-wp-converter'),
];
$category_totals = array_fill_keys(array_keys($category_labels), 0);
$category_counts = array_fill_keys(array_keys($category_labels), 0);
$audit_totals = ['pass' => 0, 'warning' => 0, 'fail' => 0, 'total' => 0];
$score_bands = ['excellent' => 0, 'good' => 0, 'needs_work' => 0, 'critical' => 0, 'unscanned' => 0];

$count_items = static function ($items) {
    return is_array($items) ? count($items) : 0;
};

$char_len = static function ($value) {
    $value = (string) $value;
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
};

$valid_json = static function ($json) {
    if (!is_string($json) || trim($json) === '') {
        return false;
    }
    json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE;
};

$contains_text = static function ($items, $needles) {
    if (!is_array($items)) {
        return false;
    }
    foreach ($items as $item) {
        foreach ($needles as $needle) {
            if (stripos((string) $item, $needle) !== false) {
                return true;
            }
        }
    }
    return false;
};

foreach ($pages as $page) {
    $wp_post = !empty($page->wp_page_id) ? get_post((int) $page->wp_page_id) : null;
    $state = ($seo && method_exists($seo, 'get_page_state')) ? $seo->get_page_state($page) : [];
    $summary = ($seo && method_exists($seo, 'get_page_state_summary'))
        ? $seo->get_page_state_summary($page)
        : ['status' => 'unscanned', 'score' => null, 'label' => esc_html__('Not scanned', 'tahmir-ai-to-wp-converter')];
    $recommendations = isset($state['recommendations']) && is_array($state['recommendations']) ? $state['recommendations'] : [];
    $status = $summary['status'] ?? 'unscanned';
    $score = $summary['score'];

    if (!isset($status_counts[$status])) {
        $status_counts[$status] = 0;
    }
    $status_counts[$status]++;

    if ($score !== null) {
        $score_total += (int) $score;
        $score_count++;
        if ((int) $score < 80) {
            $low_score_pages++;
        }
    }

    $alt_count = $count_items($recommendations['alt_text_fixes'] ?? []);
    $heading_count = $count_items($recommendations['heading_fixes'] ?? []);
    $gap_count = $count_items($recommendations['content_gaps'] ?? []);
    $query_count = $count_items($recommendations['target_queries'] ?? []);
    $section_count = $count_items($recommendations['sections'] ?? []);
    $schema_ready = $valid_json($recommendations['schema_json'] ?? '');
    $summary_ready = !empty($recommendations['ai_summary']);
    $description = (string) ($recommendations['meta_description'] ?? '');
    $description_length = $char_len($description);
    $meta_ready = !empty($recommendations['title']) && $description_length >= 70 && $description_length <= 165 && !empty($recommendations['canonical_url']);
    $audit_categories = isset($recommendations['audit_categories']) && is_array($recommendations['audit_categories']) ? $recommendations['audit_categories'] : [];
    $audit_checks = isset($recommendations['audit_checks']) && is_array($recommendations['audit_checks']) ? $recommendations['audit_checks'] : [];
    $audit_summary = isset($recommendations['audit_summary']) && is_array($recommendations['audit_summary'])
        ? array_merge(['pass' => 0, 'warning' => 0, 'fail' => 0, 'total' => 0], $recommendations['audit_summary'])
        : ['pass' => 0, 'warning' => 0, 'fail' => 0, 'total' => 0];

    foreach ($category_labels as $category_key => $category_label) {
        if (isset($audit_categories[$category_key]['score'])) {
            $category_totals[$category_key] += (int) $audit_categories[$category_key]['score'];
            $category_counts[$category_key]++;
        }
    }
    foreach (['pass', 'warning', 'fail', 'total'] as $audit_key) {
        $audit_totals[$audit_key] += (int) ($audit_summary[$audit_key] ?? 0);
    }

    if ($score === null) {
        $score_bands['unscanned']++;
    } elseif ((int) $score >= 90) {
        $score_bands['excellent']++;
    } elseif ((int) $score >= 75) {
        $score_bands['good']++;
    } elseif ((int) $score >= 50) {
        $score_bands['needs_work']++;
    } else {
        $score_bands['critical']++;
    }

    $onpage_issue_counts['alt_text'] += $alt_count;
    $onpage_issue_counts['heading'] += $heading_count;
    $onpage_issue_counts['content_gaps'] += $gap_count;
    if ($contains_text($recommendations['content_gaps'] ?? [], ['thin visible copy'])) {
        $onpage_issue_counts['thin_copy_pages']++;
    }
    if ($contains_text($recommendations['content_gaps'] ?? [], ['No links were detected'])) {
        $onpage_issue_counts['missing_links_pages']++;
    }
    if ($contains_text($recommendations['content_gaps'] ?? [], ['FAQ'])) {
        $onpage_issue_counts['faq_pages']++;
    }
    if ($contains_text($recommendations['content_gaps'] ?? [], ['trust signals', 'testimonials', 'reviews', 'case studies'])) {
        $onpage_issue_counts['trust_pages']++;
    }

    $ai_counts['summaries'] += $summary_ready ? 1 : 0;
    $ai_counts['schema'] += $schema_ready ? 1 : 0;
    $ai_counts['queries'] += $query_count;
    $ai_counts['sections'] += $section_count;
    $ai_counts['content_gaps'] += $gap_count;

    foreach (($recommendations['content_gaps'] ?? []) as $gap) {
        $gap = (string) $gap;
        if ($gap === '') {
            continue;
        }
        if (!isset($gap_counts[$gap])) {
            $gap_counts[$gap] = 0;
        }
        $gap_counts[$gap]++;
    }

    $is_review_ready = in_array($status, ['pending_review', 'stale'], true);
    if ($is_review_ready) {
        $review_ready_pages++;
        $impact_counts['pages']++;
        foreach (['title', 'meta_description', 'canonical_url', 'robots', 'og_image', 'schema_json', 'ai_summary', 'target_queries'] as $field) {
            if (!empty($recommendations[$field])) {
                $impact_counts['metadata_fields']++;
            }
        }
        $impact_counts['alt_text'] += $alt_count;
        $impact_counts['ai_summaries'] += $summary_ready ? 1 : 0;
        $impact_counts['target_queries'] += $query_count;
        $impact_counts['schema_graphs'] += $schema_ready ? 1 : 0;
    }

    $top_improvement = esc_html__('Ready to review metadata and AI search signals.', 'tahmir-ai-to-wp-converter');
    $priority_check = null;
    foreach (['fail', 'warning'] as $priority_status) {
        foreach ($audit_checks as $audit_check) {
            if (($audit_check['status'] ?? '') === $priority_status) {
                $priority_check = $audit_check;
                break 2;
            }
        }
    }
    if ($priority_check && !empty($priority_check['recommendation'])) {
        $top_improvement = $priority_check['recommendation'];
    } elseif (!empty($recommendations['content_gaps'][0])) {
        $top_improvement = $recommendations['content_gaps'][0];
    } elseif (!empty($recommendations['heading_fixes'][0])) {
        $top_improvement = $recommendations['heading_fixes'][0];
    } elseif ($alt_count > 0) {
        $top_improvement = sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ _n('%d image needs alt text.', '%d images need alt text.', $alt_count, 'tahmir-ai-to-wp-converter'), $alt_count);
    } elseif ($status === 'approved') {
        $top_improvement = esc_html__('Approved metadata is live for this page.', 'tahmir-ai-to-wp-converter');
    } elseif ($status === 'queued_until_publish') {
        $top_improvement = esc_html__('Publish the page or enable draft scanning to generate recommendations.', 'tahmir-ai-to-wp-converter');
    }

    $page_rows[] = [
        'id' => (int) $page->id,
        'wp_id' => (int) ($page->wp_page_id ?? 0),
        'title' => $page->post_title ?: esc_html__('(No title)', 'tahmir-ai-to-wp-converter'),
        'status' => $status,
        'label' => $summary['label'] ?? ucfirst(str_replace('_', ' ', $status)),
        'score' => $score,
        'meta_ready' => $meta_ready,
        'description_length' => $description_length,
        'alt_count' => $alt_count,
        'heading_count' => $heading_count,
        'gap_count' => $gap_count,
        'summary_ready' => $summary_ready,
        'schema_ready' => $schema_ready,
        'query_count' => $query_count,
        'section_count' => $section_count,
        'top_improvement' => $top_improvement,
        'audit_categories' => $audit_categories,
        'audit_summary' => $audit_summary,
        'audit_checks' => $audit_checks,
        'edit_url' => $wp_post ? get_edit_post_link((int) $page->wp_page_id) : '',
        'view_url' => $wp_post ? get_permalink((int) $page->wp_page_id) : '',
        'onpage_issues' => (int) ($audit_summary['warning'] ?? 0) + (int) ($audit_summary['fail'] ?? 0),
        'ai_issues' => (int) ($audit_categories['schema']['issues'] ?? 0) + (int) ($audit_categories['social_ai']['issues'] ?? 0),
    ];
}

$average_score = $score_count > 0 ? round($score_total / $score_count) : 0;
$coverage_percent = $total_pages > 0 ? round(($score_count / $total_pages) * 100) : 0;
$approved_percent = $total_pages > 0 ? round((($status_counts['approved'] ?? 0) / $total_pages) * 100) : 0;
$ai_readiness_percent = $total_pages > 0 ? round((($ai_counts['summaries'] + $ai_counts['schema']) / max(1, $total_pages * 2)) * 100) : 0;
$category_averages = [];
foreach ($category_labels as $category_key => $category_label) {
    $category_averages[$category_key] = $category_counts[$category_key] > 0
        ? (int) round($category_totals[$category_key] / $category_counts[$category_key])
        : 0;
}
$health_label = $average_score >= 90
    ? esc_html__('Exceptional', 'tahmir-ai-to-wp-converter')
    : ($average_score >= 75 ? esc_html__('Strong', 'tahmir-ai-to-wp-converter') : ($average_score >= 50 ? esc_html__('Needs work', 'tahmir-ai-to-wp-converter') : esc_html__('Critical', 'tahmir-ai-to-wp-converter')));
$max_score_band = max(1, max($score_bands));
$audit_resolved_percent = $audit_totals['total'] > 0 ? (int) round(($audit_totals['pass'] / $audit_totals['total']) * 100) : 0;

$radar_center = 160;
$radar_radius = 112;
$radar_axes = [];
$radar_points = [];
$radar_grid = [25 => [], 50 => [], 75 => [], 100 => []];
$radar_index = 0;
foreach ($category_labels as $category_key => $category_label) {
    $angle = deg2rad(-90 + ($radar_index * (360 / count($category_labels))));
    $axis_x = $radar_center + cos($angle) * $radar_radius;
    $axis_y = $radar_center + sin($angle) * $radar_radius;
    $radar_axes[] = ['x' => $axis_x, 'y' => $axis_y];
    foreach (array_keys($radar_grid) as $grid_percent) {
        $grid_radius = $radar_radius * ($grid_percent / 100);
        $radar_grid[$grid_percent][] = ($radar_center + cos($angle) * $grid_radius) . ',' . ($radar_center + sin($angle) * $grid_radius);
    }
    $value_radius = $radar_radius * (($category_averages[$category_key] ?? 0) / 100);
    $radar_points[] = ($radar_center + cos($angle) * $value_radius) . ',' . ($radar_center + sin($angle) * $value_radius);
    $radar_index++;
}
arsort($gap_counts);
$top_gaps = array_slice($gap_counts, 0, 5, true);
?>

<div class="wrap ugentic-wrap ug-optimize">
    <?php
    Ugentic_Admin_UI::page_header([
        'title' => $hub['title'] ?? esc_html__('SEO & Tools', 'tahmir-ai-to-wp-converter'),
        'description' => $hub['description'] ?? esc_html__('Check page SEO, review issues, and apply recommended fixes from one clear workspace.', 'tahmir-ai-to-wp-converter'),
        'icon' => 'dashicons-chart-area',
        'actions' => [
            [
                'label' => esc_html__('SEO Settings', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-settings#tab-seo-aiseo'),
                'class' => 'button',
                'icon' => 'dashicons-admin-generic',
            ],
            [
                'label' => esc_html__('Manage Pages', 'tahmir-ai-to-wp-converter'),
                'url' => admin_url('admin.php?page=ugentic-pages'),
                'class' => 'button button-primary',
                'icon' => 'dashicons-media-document',
            ],
        ],
    ]);
    ?>

    <?php if (empty($settings['enabled'])): ?>
        <div class="notice notice-warning ug-opt-notice">
            <p><?php esc_html_e('SEO/AISEO reviews are disabled. Enable them in SEO Settings to scan pages and publish recommendations.', 'tahmir-ai-to-wp-converter'); ?></p>
        </div>
    <?php endif; ?>

    <div class="ug-opt-tabs" role="tablist" aria-label="<?php esc_attr_e('SEO and tools sections', 'tahmir-ai-to-wp-converter'); ?>">
        <button type="button" class="ug-opt-tab is-active" data-tab="seo">
            <span class="dashicons dashicons-search"></span>
            <?php esc_html_e('SEO Overview', 'tahmir-ai-to-wp-converter'); ?>
        </button>
        <button type="button" class="ug-opt-tab" data-tab="tools">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php esc_html_e('Tools', 'tahmir-ai-to-wp-converter'); ?>
        </button>
    </div>

    <section class="ug-opt-tab-panel is-active" data-panel="seo">
        <section class="ug-seo-command">
            <div class="ug-seo-command__score">
                <div class="ug-seo-orbit" style="--seo-angle:<?php echo esc_attr(round($average_score * 3.6, 2)); ?>deg">
                    <div><strong><?php echo esc_html($score_count ? $average_score : '0'); ?></strong><span>/100</span></div>
                </div>
                <div>
                    <span class="ug-seo-kicker"><?php esc_html_e('Site health', 'tahmir-ai-to-wp-converter'); ?></span>
                    <h2><?php echo esc_html($health_label); ?></h2>
                    <p><?php echo esc_html(sprintf(/* translators: 1: Scanned page count; 2: Total page count; 3: Coverage percentage. */ esc_html__('%1$d of %2$d pages scanned · %3$d%% coverage', 'tahmir-ai-to-wp-converter'), $score_count, $total_pages, $coverage_percent)); ?></p>
                </div>
            </div>
            <div class="ug-seo-command__metrics">
                <article><span class="dashicons dashicons-yes-alt"></span><div><strong><?php echo esc_html($audit_resolved_percent); ?>%</strong><small><?php esc_html_e('checks passing', 'tahmir-ai-to-wp-converter'); ?></small></div></article>
                <article><span class="dashicons dashicons-warning"></span><div><strong><?php echo esc_html($audit_totals['warning'] + $audit_totals['fail']); ?></strong><small><?php esc_html_e('open signals', 'tahmir-ai-to-wp-converter'); ?></small></div></article>
                <article><span class="dashicons dashicons-media-document"></span><div><strong><?php echo esc_html($score_count); ?>/<?php echo esc_html($total_pages); ?></strong><small><?php esc_html_e('pages scanned', 'tahmir-ai-to-wp-converter'); ?></small></div></article>
                <article><span class="dashicons dashicons-yes"></span><div><strong><?php echo esc_html($review_ready_pages); ?></strong><small><?php esc_html_e('ready to apply', 'tahmir-ai-to-wp-converter'); ?></small></div></article>
            </div>
            <div class="ug-seo-command__actions">
                <button type="button" id="ug-opt-rescan-all" class="button ug-seo-button-secondary"><span class="dashicons dashicons-update"></span><?php esc_html_e('Re-scan all', 'tahmir-ai-to-wp-converter'); ?></button>
                <button type="button" id="ug-opt-apply-all" class="button button-primary" <?php disabled($review_ready_pages, 0); ?>><span class="dashicons dashicons-saved"></span><?php esc_html_e('Apply review-ready', 'tahmir-ai-to-wp-converter'); ?></button>
            </div>
        </section>

        <div class="ug-seo-graph-grid">
            <section class="ug-seo-graph-card ug-seo-radar-card">
                <header><div><span class="ug-seo-card-kicker"><?php esc_html_e('Factor intelligence', 'tahmir-ai-to-wp-converter'); ?></span><h3><?php esc_html_e('SEO signal radar', 'tahmir-ai-to-wp-converter'); ?></h3></div><span class="ug-seo-live-dot"><?php esc_html_e('Live audit', 'tahmir-ai-to-wp-converter'); ?></span></header>
                <div class="ug-seo-radar-layout">
                    <svg class="ug-seo-radar" viewBox="0 0 320 320" role="img" aria-label="<?php esc_attr_e('Average scores across eight SEO categories', 'tahmir-ai-to-wp-converter'); ?>">
                        <defs>
                            <linearGradient id="ugSeoRadarFill" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#35e6ae" stop-opacity=".72"/><stop offset="1" stop-color="#4a7cff" stop-opacity=".34"/></linearGradient>
                            <filter id="ugSeoGlow"><feGaussianBlur stdDeviation="5" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                        </defs>
                        <?php foreach ($radar_grid as $grid_percent => $grid_points): ?>
                            <polygon points="<?php echo esc_attr(implode(' ', $grid_points)); ?>" class="ug-seo-radar__grid <?php echo $grid_percent === 100 ? 'is-outer' : ''; ?>"/>
                        <?php endforeach; ?>
                        <?php foreach ($radar_axes as $axis): ?><line x1="160" y1="160" x2="<?php echo esc_attr($axis['x']); ?>" y2="<?php echo esc_attr($axis['y']); ?>" class="ug-seo-radar__axis"/><?php endforeach; ?>
                        <polygon points="<?php echo esc_attr(implode(' ', $radar_points)); ?>" class="ug-seo-radar__area" filter="url(#ugSeoGlow)"/>
                        <?php $radar_point_index = 0; foreach ($category_averages as $category_score):
                            $point_parts = explode(',', $radar_points[$radar_point_index]); ?>
                            <circle cx="<?php echo esc_attr($point_parts[0]); ?>" cy="<?php echo esc_attr($point_parts[1]); ?>" r="4" class="ug-seo-radar__point"/>
                        <?php $radar_point_index++; endforeach; ?>
                    </svg>
                    <div class="ug-seo-radar-legend">
                        <?php foreach ($category_labels as $category_key => $category_label):
                            $category_score = $category_averages[$category_key] ?? 0; ?>
                            <div><span><?php echo esc_html($category_label); ?></span><strong><?php echo esc_html($category_score); ?></strong><i style="--factor-score:<?php echo esc_attr($category_score); ?>%"></i></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="ug-seo-graph-card ug-seo-distribution-card">
                <header><div><span class="ug-seo-card-kicker"><?php esc_html_e('Portfolio health', 'tahmir-ai-to-wp-converter'); ?></span><h3><?php esc_html_e('Score distribution', 'tahmir-ai-to-wp-converter'); ?></h3></div><span><?php echo esc_html(sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ _n('%d page', '%d pages', $total_pages, 'tahmir-ai-to-wp-converter'), $total_pages)); ?></span></header>
                <div class="ug-seo-bar-chart" aria-label="<?php esc_attr_e('Pages grouped by SEO score', 'tahmir-ai-to-wp-converter'); ?>">
                    <?php
                    $band_meta = [
                        'excellent' => ['label' => esc_html__('90–100', 'tahmir-ai-to-wp-converter'), 'name' => esc_html__('Excellent', 'tahmir-ai-to-wp-converter')],
                        'good' => ['label' => esc_html__('75–89', 'tahmir-ai-to-wp-converter'), 'name' => esc_html__('Good', 'tahmir-ai-to-wp-converter')],
                        'needs_work' => ['label' => esc_html__('50–74', 'tahmir-ai-to-wp-converter'), 'name' => esc_html__('Needs work', 'tahmir-ai-to-wp-converter')],
                        'critical' => ['label' => esc_html__('0–49', 'tahmir-ai-to-wp-converter'), 'name' => esc_html__('Critical', 'tahmir-ai-to-wp-converter')],
                        'unscanned' => ['label' => esc_html__('—', 'tahmir-ai-to-wp-converter'), 'name' => esc_html__('Unscanned', 'tahmir-ai-to-wp-converter')],
                    ];
                    foreach ($band_meta as $band_key => $band):
                        $band_value = $score_bands[$band_key];
                        $bar_height = $band_value > 0 ? max(5, round(($band_value / $max_score_band) * 100)) : 0; ?>
                        <div class="ug-seo-bar ug-seo-bar--<?php echo esc_attr($band_key); ?>"><strong><?php echo esc_html($band_value); ?></strong><div><i style="height:<?php echo esc_attr($bar_height); ?>%"></i></div><span><?php echo esc_html($band['label']); ?></span><small><?php echo esc_html($band['name']); ?></small></div>
                    <?php endforeach; ?>
                </div>
                <div class="ug-seo-distribution-note"><span class="dashicons dashicons-chart-line"></span><p><?php echo esc_html($low_score_pages ? sprintf(/* translators: Number of the pages, records, files, or items described in this message. */ _n('%d page is below 80 and has high-growth potential.', '%d pages are below 80 and have high-growth potential.', $low_score_pages, 'tahmir-ai-to-wp-converter'), $low_score_pages) : esc_html__('Every scanned page is currently at 80 or above.', 'tahmir-ai-to-wp-converter')); ?></p></div>
            </section>

            <section class="ug-seo-graph-card ug-seo-resolution-card">
                <header><div><span class="ug-seo-card-kicker"><?php esc_html_e('Audit resolution', 'tahmir-ai-to-wp-converter'); ?></span><h3><?php esc_html_e('Factor status', 'tahmir-ai-to-wp-converter'); ?></h3></div><span><?php echo esc_html($audit_totals['total']); ?> <?php esc_html_e('checks', 'tahmir-ai-to-wp-converter'); ?></span></header>
                <div class="ug-seo-donut-layout">
                    <?php $warning_end_percent = $audit_resolved_percent + ($audit_totals['total'] ? round(($audit_totals['warning'] / $audit_totals['total']) * 100) : 0); ?>
                    <div class="ug-seo-donut" style="--pass-end:<?php echo esc_attr($audit_resolved_percent); ?>%;--warning-end:<?php echo esc_attr(min(100, $warning_end_percent)); ?>%"><div><strong><?php echo esc_html($audit_resolved_percent); ?>%</strong><span><?php esc_html_e('passing', 'tahmir-ai-to-wp-converter'); ?></span></div></div>
                    <div class="ug-seo-status-legend">
                        <div><i class="is-pass"></i><span><?php esc_html_e('Passed', 'tahmir-ai-to-wp-converter'); ?></span><strong><?php echo esc_html($audit_totals['pass']); ?></strong></div>
                        <div><i class="is-warning"></i><span><?php esc_html_e('Warnings', 'tahmir-ai-to-wp-converter'); ?></span><strong><?php echo esc_html($audit_totals['warning']); ?></strong></div>
                        <div><i class="is-fail"></i><span><?php esc_html_e('Failed', 'tahmir-ai-to-wp-converter'); ?></span><strong><?php echo esc_html($audit_totals['fail']); ?></strong></div>
                    </div>
                </div>
                <div class="ug-seo-impact-callout"><span><?php esc_html_e('Ready impact', 'tahmir-ai-to-wp-converter'); ?></span><strong><?php echo esc_html($impact_counts['metadata_fields'] + $impact_counts['alt_text'] + $impact_counts['schema_graphs']); ?></strong><p><?php esc_html_e('recommended improvements can be published now', 'tahmir-ai-to-wp-converter'); ?></p></div>
            </section>
        </div>

        <section class="ug-seo-factor-board">
            <header><div><span class="ug-seo-card-kicker"><?php esc_html_e('Complete on-page model', 'tahmir-ai-to-wp-converter'); ?></span><h3><?php esc_html_e('Eight weighted SEO dimensions', 'tahmir-ai-to-wp-converter'); ?></h3><p><?php esc_html_e('The score combines current—not merely suggested—signals across dozens of explainable checks.', 'tahmir-ai-to-wp-converter'); ?></p></div><a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-settings#tab-seo-aiseo')); ?>"><?php esc_html_e('Configure SEO', 'tahmir-ai-to-wp-converter'); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a></header>
            <div class="ug-seo-factor-grid">
                <?php
                $factor_icons = ['metadata'=>'dashicons-tag','content'=>'dashicons-text-page','structure'=>'dashicons-editor-ol','media'=>'dashicons-format-image','links'=>'dashicons-admin-links','technical'=>'dashicons-admin-generic','schema'=>'dashicons-networking','social_ai'=>'dashicons-superhero'];
                foreach ($category_labels as $category_key => $category_label):
                    $category_score = $category_averages[$category_key] ?? 0;
                    $factor_tone = $category_score >= 85 ? 'good' : ($category_score >= 65 ? 'warning' : 'critical'); ?>
                    <article class="ug-seo-factor ug-seo-factor--<?php echo esc_attr($factor_tone); ?>">
                        <div><span class="dashicons <?php echo esc_attr($factor_icons[$category_key]); ?>"></span><strong><?php echo esc_html($category_label); ?></strong><b><?php echo esc_html($category_score); ?></b></div>
                        <i><span style="width:<?php echo esc_attr($category_score); ?>%"></span></i>
                        <small><?php echo esc_html($category_score >= 85 ? esc_html__('Healthy signal', 'tahmir-ai-to-wp-converter') : ($category_score >= 65 ? esc_html__('Opportunity', 'tahmir-ai-to-wp-converter') : esc_html__('Priority area', 'tahmir-ai-to-wp-converter'))); ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="ug-opt-action-strip ug-seo-decision-strip">
            <div><span class="ug-seo-card-kicker"><?php esc_html_e('Decision impact', 'tahmir-ai-to-wp-converter'); ?></span><h2><?php esc_html_e('Publish the work already waiting for review', 'tahmir-ai-to-wp-converter'); ?></h2><p><?php echo esc_html(sprintf(/* translators: 1: Page count; 2: Metadata field count; 3: Image count. */ esc_html__('Applying current recommendations updates %1$d page(s), %2$d metadata fields, %3$d image descriptions, %4$d AI summaries, and %5$d schema graphs.', 'tahmir-ai-to-wp-converter'), $impact_counts['pages'], $impact_counts['metadata_fields'], $impact_counts['alt_text'], $impact_counts['ai_summaries'], $impact_counts['schema_graphs'])); ?></p></div>
            <div class="ug-seo-decision-badges"><span><strong><?php echo esc_html($onpage_issue_counts['thin_copy_pages']); ?></strong><?php esc_html_e('thin pages', 'tahmir-ai-to-wp-converter'); ?></span><span><strong><?php echo esc_html($onpage_issue_counts['alt_text']); ?></strong><?php esc_html_e('missing alt', 'tahmir-ai-to-wp-converter'); ?></span><span><strong><?php echo esc_html($onpage_issue_counts['heading']); ?></strong><?php esc_html_e('heading issues', 'tahmir-ai-to-wp-converter'); ?></span></div>
        </div>

        <div class="ug-opt-page-toolbar">
            <div><h2><?php esc_html_e('Page SEO', 'tahmir-ai-to-wp-converter'); ?></h2><p><?php esc_html_e('Review the pages that need attention first.', 'tahmir-ai-to-wp-converter'); ?></p></div>
            <div class="ug-opt-page-toolbar__controls"><div class="ug-opt-filter-group" aria-label="<?php esc_attr_e('SEO page filters', 'tahmir-ai-to-wp-converter'); ?>"><button type="button" class="button ug-opt-filter is-active" data-filter="all"><?php esc_html_e('All', 'tahmir-ai-to-wp-converter'); ?></button><button type="button" class="button ug-opt-filter" data-filter="review"><?php esc_html_e('Ready to apply', 'tahmir-ai-to-wp-converter'); ?></button><button type="button" class="button ug-opt-filter" data-filter="low"><?php esc_html_e('Below 80', 'tahmir-ai-to-wp-converter'); ?></button><button type="button" class="button ug-opt-filter" data-filter="onpage"><?php esc_html_e('Open issues', 'tahmir-ai-to-wp-converter'); ?></button><button type="button" class="button ug-opt-filter" data-filter="ai"><?php esc_html_e('Structured data', 'tahmir-ai-to-wp-converter'); ?></button></div><label class="screen-reader-text" for="ug-opt-page-search"><?php esc_html_e('Search pages', 'tahmir-ai-to-wp-converter'); ?></label><input type="search" id="ug-opt-page-search" placeholder="<?php esc_attr_e('Search pages…', 'tahmir-ai-to-wp-converter'); ?>"></div>
        </div>

        <div class="ug-opt-table-wrap">
            <?php if (empty($page_rows)): ?>
                <div class="ugentic-empty-state">
                    <span class="dashicons dashicons-media-text"></span>
                    <h3><?php esc_html_e('No Tahmir AI pages yet', 'tahmir-ai-to-wp-converter'); ?></h3>
                    <p><?php esc_html_e('Import pages, then return here to scan and improve their SEO.', 'tahmir-ai-to-wp-converter'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-build')); ?>" class="button button-primary"><?php esc_html_e('Import Pages', 'tahmir-ai-to-wp-converter'); ?></a>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat striped ug-opt-page-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Page', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th><?php esc_html_e('Score', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th><?php esc_html_e('Next step', 'tahmir-ai-to-wp-converter'); ?></th>
                            <th><?php esc_html_e('Actions', 'tahmir-ai-to-wp-converter'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($page_rows as $row): ?>
                            <tr
                                data-title="<?php echo esc_attr(strtolower($row['title'])); ?>"
                                data-status="<?php echo esc_attr($row['status']); ?>"
                                data-score="<?php echo esc_attr($row['score'] === null ? -1 : (int) $row['score']); ?>"
                                data-onpage="<?php echo esc_attr((int) $row['onpage_issues']); ?>"
                                data-ai="<?php echo esc_attr((int) $row['ai_issues']); ?>"
                            >
                                <td>
                                    <strong><?php echo esc_html($row['title']); ?></strong>
                                    <span class="status-badge status-<?php echo esc_attr($row['status']); ?>"><?php echo esc_html($row['label']); ?></span>
                                    <div class="row-actions">
                                        <?php if (!empty($row['edit_url'])): ?>
                                            <span><a href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('Edit', 'tahmir-ai-to-wp-converter'); ?></a></span>
                                        <?php endif; ?>
                                        <?php if (!empty($row['view_url'])): ?>
                                            <span><a href="<?php echo esc_url($row['view_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'tahmir-ai-to-wp-converter'); ?></a></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['score'] !== null): ?>
                                        <div class="ug-seo-table-score <?php echo $row['score'] >= 85 ? 'is-good' : ($row['score'] >= 65 ? 'is-warning' : 'is-critical'); ?>"><strong><?php echo esc_html((int) $row['score']); ?></strong><span>/100</span></div>
                                    <?php else: ?>
                                        <span class="ug-opt-muted"><?php esc_html_e('Not scanned', 'tahmir-ai-to-wp-converter'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ug-opt-improvement"><?php echo esc_html($row['top_improvement']); ?></span>
                                </td>
                                <td>
                                    <div class="ug-opt-row-actions">
                                        <button type="button" class="button button-small ug-opt-review" data-id="<?php echo esc_attr($row['id']); ?>"><span class="dashicons dashicons-visibility"></span><?php esc_html_e('Audit', 'tahmir-ai-to-wp-converter'); ?></button>
                                        <button type="button" class="button button-small ug-opt-rescan" data-id="<?php echo esc_attr($row['id']); ?>" title="<?php esc_attr_e('Re-scan page', 'tahmir-ai-to-wp-converter'); ?>"><span class="dashicons dashicons-update"></span></button>
                                        <button type="button" class="button button-small button-primary ug-opt-apply" data-id="<?php echo esc_attr($row['id']); ?>" <?php disabled(empty($row['score']) && $row['score'] !== 0); ?>><?php esc_html_e('Apply', 'tahmir-ai-to-wp-converter'); ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <section class="ug-opt-tab-panel" data-panel="tools">
        <div class="ug-admin-card-grid">
            <?php foreach (($hub['cards'] ?? []) as $card): ?>
                <a class="ug-admin-card ug-admin-card--link" href="<?php echo esc_url($card['url'] ?? '#'); ?>">
                    <span class="dashicons <?php echo esc_attr($card['icon'] ?? 'dashicons-admin-generic'); ?> ug-admin-card__icon"></span>
                    <span class="ug-admin-card__body">
                        <strong><?php echo esc_html($card['title'] ?? ''); ?></strong>
                        <span><?php echo esc_html($card['description'] ?? ''); ?></span>
                    </span>
                    <span class="dashicons dashicons-arrow-right-alt2 ug-admin-card__arrow"></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div id="ug-opt-seo-modal" class="ugentic-modal" style="display: none;">
    <div class="ugentic-modal-content ug-opt-modal">
        <div class="ugentic-modal-header">
            <h2><?php esc_html_e('Complete On-Page Audit', 'tahmir-ai-to-wp-converter'); ?></h2>
            <button type="button" class="close-modal" aria-label="<?php esc_attr_e('Close', 'tahmir-ai-to-wp-converter'); ?>">&times;</button>
        </div>
        <div class="modal-body">
            <div id="ug-opt-review-loading" class="ug-opt-loading">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e('Loading page recommendations...', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            <div id="ug-opt-review-content" style="display: none;"></div>
            <div id="ug-opt-review-actions" class="ug-opt-review-actions" style="display: none;">
                <button type="button" class="button close-modal"><?php esc_html_e('Close', 'tahmir-ai-to-wp-converter'); ?></button>
                <button type="button" id="ug-opt-review-rescan" class="button"><?php esc_html_e('Re-scan', 'tahmir-ai-to-wp-converter'); ?></button>
                <button type="button" id="ug-opt-review-apply" class="button button-primary"><?php esc_html_e('Apply Recommendations', 'tahmir-ai-to-wp-converter'); ?></button>
            </div>
        </div>
    </div>
</div>


