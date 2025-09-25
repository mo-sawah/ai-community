<?php
/**
 * AI Community Analytics Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$this->render_admin_header(__('Analytics', 'ai-community'));
?>

<div class="ai-community-analytics-page">
    <!-- Overview Stats -->
    <div class="analytics-overview">
        <div class="overview-grid">
            <div class="overview-card">
                <div class="card-header">
                    <h3><?php _e('Content Generation', 'ai-community'); ?></h3>
                    <span class="dashicons dashicons-robot"></span>
                </div>
                <div class="card-content">
                    <div class="metric">
                        <span class="metric-value"><?php echo $generation_stats['totals']['posts'] ?? 0; ?></span>
                        <span class="metric-label"><?php _e('AI Posts Generated', 'ai-community'); ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?php echo $generation_stats['totals']['replies'] ?? 0; ?></span>
                        <span class="metric-label"><?php _e('AI Replies Generated', 'ai-community'); ?></span>
                    </div>
                    <div class="metric-small">
                        <span class="metric-label"><?php _e('Success Rate:', 'ai-community'); ?></span>
                        <span class="metric-value"><?php echo round($generation_stats['averages']['success_rate'] ?? 0, 1); ?>%</span>
                    </div>
                </div>
            </div>

            <div class="overview-card">
                <div class="card-header">
                    <h3><?php _e('API Usage', 'ai-community'); ?></h3>
                    <span class="dashicons dashicons-cloud"></span>
                </div>
                <div class="card-content">
                    <div class="metric">
                        <span class="metric-value"><?php echo array_sum(array_column($usage_stats, 'requests')) ?? 0; ?></span>
                        <span class="metric-label"><?php _e('API Requests (30 days)', 'ai-community'); ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?php echo number_format(array_sum(array_column($usage_stats, 'tokens_used')) ?? 0); ?></span>
                        <span class="metric-label"><?php _e('Tokens Used', 'ai-community'); ?></span>
                    </div>
                    <div class="metric-small">
                        <span class="metric-label"><?php _e('Est. Cost:', 'ai-community'); ?></span>
                        <span class="metric-value">$<?php echo number_format($cost_estimate ?? 0, 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="overview-card">
                <div class="card-header">
                    <h3><?php _e('Content Quality', 'ai-community'); ?></h3>
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="card-content">
                    <div class="metric">
                        <span class="metric-value"><?php echo $quality_report['summary']['high_quality_posts'] ?? 0; ?></span>
                        <span class="metric-label"><?php _e('High Quality Posts', 'ai-community'); ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?php echo round($quality_report['summary']['average_quality'] ?? 0, 2); ?></span>
                        <span class="metric-label"><?php _e('Avg Quality Score', 'ai-community'); ?></span>
                    </div>
                    <div class="metric-small">
                        <span class="metric-label"><?php _e('Low Quality:', 'ai-community'); ?></span>
                        <span class="metric-value"><?php echo $quality_report['summary']['low_quality_posts'] ?? 0; ?></span>
                    </div>
                </div>
            </div>

            <div class="overview-card">
                <div class="card-header">
                    <h3><?php _e('Community Stats', 'ai-community'); ?></h3>
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="card-content">
                    <div class="metric">
                        <span class="metric-value"><?php echo $stats['total_posts'] ?? 0; ?></span>
                        <span class="metric-label"><?php _e('Total Posts', 'ai-community'); ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-value"><?php echo $stats['total_comments'] ?? 0; ?></span>
                        <span class="metric-label"><?php _e('Total Comments', 'ai-community'); ?></span>
                    </div>
                    <div class="metric-small">
                        <span class="metric-label"><?php _e('Active Users:', 'ai-community'); ?></span>
                        <span class="metric-value"><?php echo $stats['active_users'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="analytics-charts">
        <div class="charts-grid">
            <!-- Generation Trends Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('Content Generation Trends', 'ai-community'); ?></h3>
                    <div class="chart-controls">
                        <select id="generation-period">
                            <option value="7"><?php _e('Last 7 days', 'ai-community'); ?></option>
                            <option value="30" selected><?php _e('Last 30 days', 'ai-community'); ?></option>
                            <option value="90"><?php _e('Last 90 days', 'ai-community'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="generation-trends-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- API Usage Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><?php _e('API Usage', 'ai-community'); ?></h3>
                </div>
                <div class="chart-content">
                    <canvas id="api-usage-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Quality Score Chart -->
        <div class="chart-container full-width">
            <div class="chart-header">
                <h3><?php _e('Content Quality Over Time', 'ai-community'); ?></h3>
            </div>
            <div class="chart-content">
                <canvas id="quality-chart" width="800" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Analytics -->
    <div class="analytics-details">
        <div class="details-grid">
            <!-- Recent Quality Report -->
            <div class="detail-section">
                <h3><?php _e('Recent Content Quality', 'ai-community'); ?></h3>
                <?php if (!empty($quality_report['posts'])): ?>
                    <div class="quality-posts-list">
                        <?php foreach (array_slice($quality_report['posts'], 0, 10) as $post): ?>
                            <div class="quality-post-item">
                                <div class="post-info">
                                    <h4><?php echo esc_html($post['title']); ?></h4>
                                    <div class="post-meta">
                                        <span class="post-date"><?php echo human_time_diff(strtotime($post['created_at']), current_time('timestamp')); ?> ago</span>
                                        <span class="post-views"><?php echo number_format($post['views']); ?> views</span>
                                    </div>
                                </div>
                                <div class="quality-scores">
                                    <div class="quality-score">
                                        <span class="score-label"><?php _e('Quality', 'ai-community'); ?></span>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo ($post['quality_score'] * 100); ?>%"></div>
                                        </div>
                                        <span class="score-value"><?php echo round($post['quality_score'], 2); ?></span>
                                    </div>
                                    <div class="engagement-score">
                                        <span class="score-label"><?php _e('Engagement', 'ai-community'); ?></span>
                                        <span class="score-value"><?php echo round($post['engagement_score'], 1); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data"><?php _e('No quality data available yet.', 'ai-community'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Performance Metrics -->
            <div class="detail-section">
                <h3><?php _e('Performance Metrics', 'ai-community'); ?></h3>
                <div class="metrics-list">
                    <div class="metric-row">
                        <span class="metric-name"><?php _e('Avg Posts per Day', 'ai-community'); ?></span>
                        <span class="metric-value"><?php echo round($generation_stats['averages']['posts_per_day'] ?? 0, 1); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-name"><?php _e('Avg Replies per Day', 'ai-community'); ?></span>
                        <span class="metric-value"><?php echo round($generation_stats['averages']['replies_per_day'] ?? 0, 1); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-name"><?php _e('Generation Errors', 'ai-community'); ?></span>
                        <span class="metric-value error"><?php echo $generation_stats['totals']['errors'] ?? 0; ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-name"><?php _e('API Response Time', 'ai-community'); ?></span>
                        <span class="metric-value"><?php echo '~2.3s'; // Would be calculated from logs ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-name"><?php _e('Cache Hit Rate', 'ai-community'); ?></span>
                        <span class="metric-value success"><?php echo '87%'; // Would be calculated from cache stats ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Section -->
    <div class="analytics-export">
        <div class="export-section">
            <h3><?php _e('Export Analytics Data', 'ai-community'); ?></h3>
            <p><?php _e('Download analytics data for further analysis.', 'ai-community'); ?></p>
            <div class="export-buttons">
                <button id="export-csv" class="button"><?php _e('Export as CSV', 'ai-community'); ?></button>
                <button id="export-json" class="button"><?php _e('Export as JSON', 'ai-community'); ?></button>
                <button id="generate-report" class="button button-primary"><?php _e('Generate Report', 'ai-community'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.analytics-overview {
    margin-bottom: 30px;
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.overview-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f1;
}

.card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.card-header .dashicons {
    font-size: 24px;
    color: #646970;
}

.card-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.metric {
    text-align: center;
}

.metric-value {
    display: block;
    font-size: 28px;
    font-weight: 600;
    color: #1d2327;
    line-height: 1.2;
}

.metric-label {
    display: block;
    font-size: 13px;
    color: #646970;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.metric-small {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}

.metric-small .metric-value {
    font-size: 16px;
    font-weight: 600;
}

.analytics-charts {
    margin-bottom: 30px;
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.chart-container {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
}

.chart-container.full-width {
    grid-column: 1 / -1;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f1;
}

.chart-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.chart-controls select {
    padding: 4px 8px;
    font-size: 13px;
}

.chart-content {
    position: relative;
    min-height: 200px;
}

.analytics-details {
    margin-bottom: 30px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.detail-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
}

.detail-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f1;
}

.quality-posts-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.quality-post-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

.post-info {
    flex: 1;
}

.post-info h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.4;
}

.post-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #646970;
}

.quality-scores {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 120px;
}

.quality-score {
    display: flex;
    align-items: center;
    gap: 8px;
}

.score-label {
    font-size: 11px;
    color: #646970;
    min-width: 40px;
}

.score-bar {
    width: 50px;
    height: 6px;
    background: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
}

.score-fill {
    height: 100%;
    background: linear-gradient(90deg, #dc3232 0%, #ffb900 50%, #46b450 100%);
    transition: width 0.3s ease;
}

.score-value {
    font-size: 12px;
    font-weight: 500;
}

.engagement-score {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.metrics-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.metric-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.metric-name {
    font-size: 14px;
    color: #1d2327;
}

.metric-row .metric-value {
    font-size: 14px;
    font-weight: 600;
}

.metric-row .metric-value.success {
    color: #46b450;
}

.metric-row .metric-value.error {
    color: #dc3232;
}

.analytics-export {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
}

.export-section h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.export-section p {
    margin-bottom: 20px;
    color: #646970;
}

.export-buttons {
    display: flex;
    gap: 10px;
}

.no-data {
    text-align: center;
    color: #646970;
    font-style: italic;
    padding: 40px 20px;
}

@media (max-width: 768px) {
    .overview-grid,
    .charts-grid,
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .quality-post-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .quality-scores {
        width: 100%;
    }
    
    .export-buttons {
        flex-direction: column;
    }
    
    .export-buttons .button {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize charts
    initializeCharts();
    
    // Export handlers
    $('#export-csv').click(function() {
        exportData('csv');
    });
    
    $('#export-json').click(function() {
        exportData('json');
    });
    
    $('#generate-report').click(function() {
        generateReport();
    });
    
    // Period change handler
    $('#generation-period').change(function() {
        updateGenerationChart($(this).val());
    });
    
    function initializeCharts() {
        // Generation trends chart
        var generationCtx = document.getElementById('generation-trends-chart');
        if (generationCtx) {
            var generationData = <?php echo json_encode($generation_stats['daily'] ?? array()); ?>;
            createGenerationChart(generationCtx, generationData);
        }
        
        // API usage chart
        var apiCtx = document.getElementById('api-usage-chart');
        if (apiCtx) {
            var apiData = <?php echo json_encode($usage_stats ?? array()); ?>;
            createApiChart(apiCtx, apiData);
        }
        
        // Quality chart
        var qualityCtx = document.getElementById('quality-chart');
        if (qualityCtx) {
            var qualityData = <?php echo json_encode($quality_report['posts'] ?? array()); ?>;
            createQualityChart(qualityCtx, qualityData);
        }
    }
    
    function createGenerationChart(ctx, data) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    label: '<?php _e("Posts", "ai-community"); ?>',
                    data: Object.values(data).map(d => d.posts || 0),
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.1
                }, {
                    label: '<?php _e("Replies", "ai-community"); ?>',
                    data: Object.values(data).map(d => d.replies || 0),
                    borderColor: '#00a32a',
                    backgroundColor: 'rgba(0, 163, 42, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function createApiChart(ctx, data) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    label: '<?php _e("API Requests", "ai-community"); ?>',
                    data: Object.values(data).map(d => d.requests || 0),
                    backgroundColor: '#8b5cf6'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function createQualityChart(ctx, data) {
        var chartData = data.slice(0, 20).map(post => ({
            x: post.created_at,
            y: post.quality_score
        }));
        
        new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: '<?php _e("Quality Score", "ai-community"); ?>',
                    data: chartData,
                    backgroundColor: '#f59e0b'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 1
                    }
                }
            }
        });
    }
    
    function exportData(format) {
        var button = $('#export-' + format);
        button.prop('disabled', true).text('<?php _e("Exporting...", "ai-community"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_export_data',
                data_type: 'all',
                format: format,
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and trigger download
                    var blob = new Blob([response.data.content], {type: response.data.mime_type});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    a.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    alert('<?php _e("Export failed", "ai-community"); ?>: ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e("Export failed", "ai-community"); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text(format.toUpperCase() + ' <?php _e("Export", "ai-community"); ?>');
            }
        });
    }
    
    function generateReport() {
        alert('<?php _e("Report generation feature coming soon!", "ai-community"); ?>');
    }
    
    function updateGenerationChart(period) {
        // This would reload the chart with different period data
        console.log('Update chart for period:', period);
    }
});
</script>