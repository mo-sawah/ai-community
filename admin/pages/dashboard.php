<?php
/**
 * AI Community Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$this->render_admin_header(__('Dashboard', 'ai-community'));
?>

<div class="ai-community-dashboard">
    <!-- Stats Overview -->
    <div class="dashboard-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-admin-post"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_posts'] ?? 0); ?></h3>
                    <p><?php _e('Total Posts', 'ai-community'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-robot"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['ai_posts'] ?? 0); ?></h3>
                    <p><?php _e('AI Generated Posts', 'ai-community'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-admin-comments"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_comments'] ?? 0); ?></h3>
                    <p><?php _e('Total Comments', 'ai-community'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['active_users'] ?? 0); ?></h3>
                    <p><?php _e('Active Users', 'ai-community'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <!-- AI Generation Status -->
        <div class="dashboard-widget">
            <h3><?php _e('AI Generation Status', 'ai-community'); ?></h3>
            <div class="ai-status">
                <?php if ($api_health['status'] === 'healthy'): ?>
                    <span class="status-indicator status-healthy"></span>
                    <span><?php _e('API Connection: Healthy', 'ai-community'); ?></span>
                <?php elseif ($api_health['status'] === 'warning'): ?>
                    <span class="status-indicator status-warning"></span>
                    <span><?php _e('API Connection: Warning', 'ai-community'); ?></span>
                <?php else: ?>
                    <span class="status-indicator status-error"></span>
                    <span><?php _e('API Connection: Error', 'ai-community'); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="ai-stats">
                <p><strong><?php _e('Posts Generated Today:', 'ai-community'); ?></strong> <?php echo $generation_stats['daily'][date('Y-m-d')]['posts'] ?? 0; ?></p>
                <p><strong><?php _e('Replies Generated Today:', 'ai-community'); ?></strong> <?php echo $generation_stats['daily'][date('Y-m-d')]['replies'] ?? 0; ?></p>
                <p><strong><?php _e('Next Generation Run:', 'ai-community'); ?></strong> 
                    <?php 
                    $next_run = wp_next_scheduled('ai_community_generate_content');
                    echo $next_run ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run) : __('Not scheduled', 'ai-community');
                    ?>
                </p>
            </div>
            
            <p>
                <button id="generate-ai-content" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Generate AI Content Now', 'ai-community'); ?>
                </button>
            </p>
        </div>

        <!-- System Status -->
        <div class="dashboard-widget">
            <h3><?php _e('System Status', 'ai-community'); ?></h3>
            <div class="system-checks">
                <?php foreach ($system_check['checks'] as $check_name => $check): ?>
                    <div class="system-check">
                        <?php if ($check['status']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                        <?php endif; ?>
                        <span><?php echo esc_html($check['message']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Posts -->
        <div class="dashboard-widget">
            <h3><?php _e('Recent Posts', 'ai-community'); ?></h3>
            <?php $recent_posts = $this->get_recent_posts(5); ?>
            <?php if (!empty($recent_posts)): ?>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Title', 'ai-community'); ?></th>
                            <th><?php _e('Author', 'ai-community'); ?></th>
                            <th><?php _e('Community', 'ai-community'); ?></th>
                            <th><?php _e('Date', 'ai-community'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_posts as $post): ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($post->title); ?>
                                    <?php if ($post->is_ai_generated): ?>
                                        <span class="ai-badge">AI</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($post->author_name); ?></td>
                                <td><?php echo esc_html($post->community); ?></td>
                                <td><?php echo human_time_diff(strtotime($post->created_at), current_time('timestamp')) . ' ago'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No posts found.', 'ai-community'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.dashboard-stats {
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    font-size: 24px;
    color: #2271b1;
}

.stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: 600;
}

.stat-content p {
    margin: 0;
    color: #646970;
    font-size: 14px;
}

.dashboard-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.dashboard-widget {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}

.dashboard-widget h3 {
    margin-top: 0;
    margin-bottom: 15px;
    border-bottom: 1px solid #c3c4c7;
    padding-bottom: 10px;
}

.ai-status {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.status-healthy {
    background: #46b450;
}

.status-warning {
    background: #ffb900;
}

.status-error {
    background: #dc3232;
}

.ai-stats p {
    margin: 5px 0;
}

.system-check {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 8px 0;
}

.ai-badge {
    background: #8b5cf6;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    margin-left: 5px;
}
</style>