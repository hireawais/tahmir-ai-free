<?php
/**
 * API Usage Statistics View
 */

if (!defined('ABSPATH')) {
    exit;
}

$database = ugentic_builder()->database;
$summary = $database->get_api_usage_summary();
$by_action = $database->get_api_usage_stats(['group_by' => 'action_type']);
$by_day = $database->get_api_usage_stats(['group_by' => 'day', 'period' => 'month']);

// Calculate cost (Gemini 1.5 Pro pricing approximation)
$cost_per_1k_input = 0.00125; // $1.25 per 1M input tokens
$cost_per_1k_output = 0.005; // $5 per 1M output tokens
?>

<style>
.api-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    text-align: center;
}

.stat-card.primary {
    background: linear-gradient(135deg, #f97316 0%, #c2410c 100%);
    color: white;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.stat-card.primary .stat-label {
    color: rgba(255,255,255,0.8);
}

.usage-table {
    width: 100%;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.usage-table th,
.usage-table td {
    padding: 15px 20px;
    text-align: left;
    border-bottom: 1px solid #e8e8e8;
}

.usage-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.usage-table tr:last-child td {
    border-bottom: none;
}

.usage-table tr:hover {
    background: #f8f9fa;
}

.action-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: #e8e8e8;
    color: #666;
}

.action-badge.generate_page { background: #f97316; color: white; }
.action-badge.generate_sitemap { background: #28a745; color: white; }
.action-badge.generate_copy { background: #17a2b8; color: white; }
.action-badge.edit_content { background: #ffc107; color: #333; }

.section-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.section-card h3 {
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 2px solid #e8e8e8;
}

.chart-container {
    height: 200px;
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 20px 0;
}

.chart-bar {
    flex: 1;
    background: linear-gradient(180deg, #f97316 0%, #c2410c 100%);
    border-radius: 4px 4px 0 0;
    min-height: 10px;
    position: relative;
    cursor: pointer;
    transition: opacity 0.2s;
}

.chart-bar:hover {
    opacity: 0.8;
}

.chart-bar-label {
    position: absolute;
    bottom: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    color: #666;
    white-space: nowrap;
}

.chart-bar-value {
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11px;
    font-weight: 600;
    color: #333;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #666;
}
</style>

<div class="wrap ugentic-wrap">
    <h1><?php esc_html_e('API Usage Statistics', 'tahmir-ai-to-wp-converter'); ?></h1>
    
    <!-- Summary Cards -->
    <div class="api-stats-grid">
        <div class="stat-card primary">
            <div class="stat-value"><?php echo number_format($summary->total_requests ?: 0); ?></div>
            <div class="stat-label"><?php esc_html_e('Total Requests', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($summary->total_tokens ?: 0); ?></div>
            <div class="stat-label"><?php esc_html_e('Total Tokens', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($summary->total_input_tokens ?: 0); ?></div>
            <div class="stat-label"><?php esc_html_e('Input Tokens', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($summary->total_output_tokens ?: 0); ?></div>
            <div class="stat-label"><?php esc_html_e('Output Tokens', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$<?php
$cost = (($summary->total_input_tokens ?: 0) / 1000 * $cost_per_1k_input) +
    (($summary->total_output_tokens ?: 0) / 1000 * $cost_per_1k_output);
echo number_format($cost, 4);
?></div>
            <div class="stat-label"><?php esc_html_e('Est. Cost', 'tahmir-ai-to-wp-converter'); ?></div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
        
        <!-- Usage by Action Type -->
        <div class="section-card">
            <h3><?php esc_html_e('Usage by Action Type', 'tahmir-ai-to-wp-converter'); ?></h3>
            
            <?php if (empty($by_action)): ?>
            <div class="no-data">
                <span class="dashicons dashicons-chart-bar" style="font-size: 48px; color: #ddd;"></span>
                <p><?php esc_html_e('No API usage data yet.', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            <?php
else: ?>
            <table class="usage-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Action', 'tahmir-ai-to-wp-converter'); ?></th>
                        <th><?php esc_html_e('Requests', 'tahmir-ai-to-wp-converter'); ?></th>
                        <th><?php esc_html_e('Tokens', 'tahmir-ai-to-wp-converter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($by_action as $action): ?>
                    <tr>
                        <td>
                            <span class="action-badge <?php echo esc_attr($action->action_type); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $action->action_type))); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($action->request_count); ?></td>
                        <td><?php echo number_format($action->total_tokens); ?></td>
                    </tr>
                    <?php
    endforeach; ?>
                </tbody>
            </table>
            <?php
endif; ?>
        </div>
        
        <!-- Daily Usage Chart -->
        <div class="section-card">
            <h3><?php esc_html_e('Daily Usage (Last 30 Days)', 'tahmir-ai-to-wp-converter'); ?></h3>
            
            <?php if (empty($by_day)): ?>
            <div class="no-data">
                <span class="dashicons dashicons-chart-line" style="font-size: 48px; color: #ddd;"></span>
                <p><?php esc_html_e('No usage data for this period.', 'tahmir-ai-to-wp-converter'); ?></p>
            </div>
            <?php
else:
    $max_tokens = max(array_column($by_day, 'total_tokens'));
?>
            <div class="chart-container">
                <?php foreach ($by_day as $day):
        $height = $max_tokens > 0 ? ($day->total_tokens / $max_tokens * 160) : 10;
        $date = date_i18n('M j', strtotime($day->date));
?>
                <div class="chart-bar" style="height: <?php echo esc_attr($height); ?>px;" title="<?php echo esc_attr($date . ': ' . number_format($day->total_tokens) . ' tokens'); ?>">
                    <span class="chart-bar-value"><?php echo number_format($day->total_tokens / 1000, 1); ?>k</span>
                    <span class="chart-bar-label"><?php echo esc_attr($date); ?></span>
                </div>
                <?php
    endforeach; ?>
            </div>
            <?php
endif; ?>
        </div>
        
    </div>
    
    <!-- Info Note -->
    <div class="section-card" style="background: #f8f9fa;">
        <h3 style="margin-bottom: 10px;">
            <span class="dashicons dashicons-info" style="color: #f97316;"></span>
            <?php esc_html_e('About API Usage', 'tahmir-ai-to-wp-converter'); ?>
        </h3>
        <p style="margin: 0; color: #666; line-height: 1.6;">
            <?php esc_html_e('This page tracks all API calls made to Google Gemini. Cost estimates are based on approximate Gemini pricing and may vary. Input tokens include your prompts and context, while output tokens are the AI-generated responses.', 'tahmir-ai-to-wp-converter'); ?>
        </p>
        <p style="margin: 15px 0 0 0; color: #666;">
            <strong><?php esc_html_e('First Request:', 'tahmir-ai-to-wp-converter'); ?></strong> <?php echo esc_html($summary->first_request ? date_i18n('M j, Y g:i A', strtotime($summary->first_request)) : 'N/A'); ?><br>
            <strong><?php esc_html_e('Last Request:', 'tahmir-ai-to-wp-converter'); ?></strong> <?php echo esc_html($summary->last_request ? date_i18n('M j, Y g:i A', strtotime($summary->last_request)) : 'N/A'); ?>
        </p>
    </div>
</div>
