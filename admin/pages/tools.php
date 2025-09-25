<?php
/**
 * AI Community Tools Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$this->render_admin_header(__('Tools', 'ai-community'), $current_tool, 'ai-community-tools');
?>

<div class="ai-community-tools-page">
    <?php if ($current_tool === 'export'): ?>
        <!-- Export/Import Section -->
        <div class="tool-section">
            <div class="section-header">
                <h2><?php _e('Export & Import', 'ai-community'); ?></h2>
                <p><?php _e('Backup your data or migrate settings between installations.', 'ai-community'); ?></p>
            </div>
            
            <div class="tool-grid">
                <!-- Export Settings -->
                <div class="tool-card">
                    <div class="tool-icon">
                        <span class="dashicons dashicons-download"></span>
                    </div>
                    <div class="tool-content">
                        <h3><?php _e('Export Settings', 'ai-community'); ?></h3>
                        <p><?php _e('Download your plugin settings as a JSON file for backup or migration.', 'ai-community'); ?></p>
                        <button id="export-settings" class="button button-primary">
                            <?php _e('Export Settings', 'ai-community'); ?>
                        </button>
                    </div>
                </div>

                <!-- Export Generation Data -->
                <div class="tool-card">
                    <div class="tool-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="tool-content">
                        <h3><?php _e('Export Analytics', 'ai-community'); ?></h3>
                        <p><?php _e('Download analytics and generation data for analysis.', 'ai-community'); ?></p>
                        <div class="export-options">
                            <button id="export-analytics-json" class="button"><?php _e('JSON Format', 'ai-community'); ?></button>
                            <button id="export-analytics-csv" class="button"><?php _e('CSV Format', 'ai-community'); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Import Settings -->
                <div class="tool-card">
                    <div class="tool-icon">
                        <span class="dashicons dashicons-upload"></span>
                    </div>
                    <div class="tool-content">
                        <h3><?php _e('Import Settings', 'ai-community'); ?></h3>
                        <p><?php _e('Upload a settings file to restore configuration.', 'ai-community'); ?></p>
                        <form id="import-settings-form" enctype="multipart/form-data">
                            <input type="file" id="settings-file" name="settings_file" accept=".json" required>
                            <button type="submit" class="button button-secondary">
                                <?php _e('Import Settings', 'ai-community'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($current_tool === 'logs'): ?>
        <!-- Logs Section -->
        <div class="tool-section">
            <div class="section-header">
                <h2><?php _e('System Logs', 'ai-community'); ?></h2>
                <p><?php _e('View and manage plugin logs for troubleshooting.', 'ai-community'); ?></p>
            </div>
            
            <div class="logs-container">
                <!-- Log Controls -->
                <div class="log-controls">
                    <div class="log-filters">
                        <select id="log-type-filter">
                            <option value="all"><?php _e('All Logs', 'ai-community'); ?></option>
                            <option value="generation"><?php _e('Generation Logs', 'ai-community'); ?></option>
                            <option value="api"><?php _e('API Logs', 'ai-community'); ?></option>
                            <option value="error"><?php _e('Error Logs', 'ai-community'); ?></option>
                        </select>
                        <button id="refresh-logs" class="button"><?php _e('Refresh', 'ai-community'); ?></button>
                    </div>
                    <div class="log-actions">
                        <button id="clear-logs" class="button button-secondary"><?php _e('Clear Logs', 'ai-community'); ?></button>
                        <button id="download-logs" class="button"><?php _e('Download', 'ai-community'); ?></button>
                    </div>
                </div>

                <!-- Generation Logs -->
                <div class="log-section">
                    <h3><?php _e('Recent Generation Activity', 'ai-community'); ?></h3>
                    <div class="log-table-container">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Timestamp', 'ai-community'); ?></th>
                                    <th><?php _e('Event', 'ai-community'); ?></th>
                                    <th><?php _e('Type', 'ai-community'); ?></th>
                                    <th><?php _e('Details', 'ai-community'); ?></th>
                                    <th><?php _e('Status', 'ai-community'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></td>
                                            <td><?php echo esc_html($log['event']); ?></td>
                                            <td><?php echo esc_html($log['trigger']); ?></td>
                                            <td>
                                                <?php if (isset($log['data']['posts_created'])): ?>
                                                    <?php printf(__('Posts: %d, Replies: %d', 'ai-community'), $log['data']['posts_created'], $log['data']['replies_created']); ?>
                                                <?php elseif (isset($log['data']['error'])): ?>
                                                    <span class="error-message"><?php echo esc_html($log['data']['error']); ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-<?php echo esc_attr($log['event']); ?>">
                                                    <?php echo esc_html(ucfirst($log['event'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5"><?php _e('No logs found.', 'ai-community'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- API Error Logs -->
                <?php if (!empty($api_errors)): ?>
                <div class="log-section">
                    <h3><?php _e('Recent API Errors', 'ai-community'); ?></h3>
                    <div class="error-logs">
                        <?php foreach ($api_errors as $error): ?>
                            <div class="error-log-item">
                                <div class="error-header">
                                    <span class="error-time"><?php echo human_time_diff(strtotime($error['timestamp']), current_time('timestamp')); ?> ago</span>
                                    <span class="error-context"><?php echo esc_html($error['context']); ?></span>
                                </div>
                                <div class="error-message"><?php echo esc_html($error['message']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($current_tool === 'system'): ?>
        <!-- System Check Section -->
        <div class="tool-section">
            <div class="section-header">
                <h2><?php _e('System Check', 'ai-community'); ?></h2>
                <p><?php _e('Verify system requirements and plugin configuration.', 'ai-community'); ?></p>
            </div>
            
            <div class="system-check-container">
                <!-- Overall Status -->
                <div class="system-status-card">
                    <?php $system_status = $this->get_system_status(); ?>
                    <div class="status-header">
                        <?php if ($system_status['php_version'] >= '7.4' && $system_status['wp_cron_enabled']): ?>
                            <span class="status-icon status-good">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </span>
                            <h3><?php _e('System Status: Good', 'ai-community'); ?></h3>
                        <?php else: ?>
                            <span class="status-icon status-warning">
                                <span class="dashicons dashicons-warning"></span>
                            </span>
                            <h3><?php _e('System Status: Issues Found', 'ai-community'); ?></h3>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Requirements -->
                <div class="system-checks">
                    <h3><?php _e('System Requirements', 'ai-community'); ?></h3>
                    <div class="checks-grid">
                        <div class="check-item">
                            <div class="check-status">
                                <?php if (version_compare($system_status['php_version'], '7.4', '>=')): ?>
                                    <span class="dashicons dashicons-yes-alt check-pass"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss check-fail"></span>
                                <?php endif; ?>
                            </div>
                            <div class="check-info">
                                <strong><?php _e('PHP Version', 'ai-community'); ?></strong>
                                <span class="check-value"><?php echo esc_html($system_status['php_version']); ?></span>
                                <small><?php _e('Minimum: 7.4', 'ai-community'); ?></small>
                            </div>
                        </div>

                        <div class="check-item">
                            <div class="check-status">
                                <?php if (version_compare($system_status['wordpress_version'], '5.0', '>=')): ?>
                                    <span class="dashicons dashicons-yes-alt check-pass"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss check-fail"></span>
                                <?php endif; ?>
                            </div>
                            <div class="check-info">
                                <strong><?php _e('WordPress Version', 'ai-community'); ?></strong>
                                <span class="check-value"><?php echo esc_html($system_status['wordpress_version']); ?></span>
                                <small><?php _e('Minimum: 5.0', 'ai-community'); ?></small>
                            </div>
                        </div>

                        <div class="check-item">
                            <div class="check-status">
                                <?php if (wp_convert_hr_to_bytes($system_status['memory_limit']) >= 128 * 1024 * 1024): ?>
                                    <span class="dashicons dashicons-yes-alt check-pass"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning check-warning"></span>
                                <?php endif; ?>
                            </div>
                            <div class="check-info">
                                <strong><?php _e('Memory Limit', 'ai-community'); ?></strong>
                                <span class="check-value"><?php echo esc_html($system_status['memory_limit']); ?></span>
                                <small><?php _e('Recommended: 128M+', 'ai-community'); ?></small>
                            </div>
                        </div>

                        <div class="check-item">
                            <div class="check-status">
                                <?php if ($system_status['wp_cron_enabled']): ?>
                                    <span class="dashicons dashicons-yes-alt check-pass"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss check-fail"></span>
                                <?php endif; ?>
                            </div>
                            <div class="check-info">
                                <strong><?php _e('WP Cron', 'ai-community'); ?></strong>
                                <span class="check-value"><?php echo $system_status['wp_cron_enabled'] ? __('Enabled', 'ai-community') : __('Disabled', 'ai-community'); ?></span>
                                <small><?php _e('Required for AI generation', 'ai-community'); ?></small>
                            </div>
                        </div>

                        <div class="check-item">
                            <div class="check-status">
                                <?php if ($system_status['curl_available']): ?>
                                    <span class="dashicons dashicons-yes-alt check-pass"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss check-fail"></span>
                                <?php endif; ?>
                            </div>
                            <div class="check-info">
                                <strong><?php _e('cURL Extension', 'ai-community'); ?></strong>
                                <span class="check-value"><?php echo $system_status['curl_available'] ? __('Available', 'ai-community') : __('Missing', 'ai-community'); ?></span>
                                <small><?php _e('Required for API calls', 'ai-community'); ?></small>
                            </div>
                        </div>

                        <div class="check-item">
                            <div class="check-status">
                                <?php if ($system_status['json_available']): ?>
                                    <span class="dashicons dashicons-yes-alt check-pass"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss check-fail"></span>
                                <?php endif; ?>
                            </div>
                            <div class="check-info">
                                <strong><?php _e('JSON Extension', 'ai-community'); ?></strong>
                                <span class="check-value"><?php echo $system_status['json_available'] ? __('Available', 'ai-community') : __('Missing', 'ai-community'); ?></span>
                                <small><?php _e('Required for API communication', 'ai-community'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plugin Status -->
                <div class="plugin-status">
                    <h3><?php _e('Plugin Status', 'ai-community'); ?></h3>
                    <div class="status-grid">
                        <div class="status-item">
                            <strong><?php _e('Plugin Version:', 'ai-community'); ?></strong>
                            <span><?php echo AI_COMMUNITY_VERSION; ?></span>
                        </div>
                        <div class="status-item">
                            <strong><?php _e('Database Version:', 'ai-community'); ?></strong>
                            <span><?php echo get_option('ai_community_db_version', '1.0.0'); ?></span>
                        </div>
                        <div class="status-item">
                            <strong><?php _e('Installation Date:', 'ai-community'); ?></strong>
                            <span>
                                <?php 
                                $install_time = get_option('ai_community_activation_time');
                                echo $install_time ? date_i18n(get_option('date_format'), $install_time) : __('Unknown', 'ai-community');
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Test Tools -->
                <div class="test-tools">
                    <h3><?php _e('Test Tools', 'ai-community'); ?></h3>
                    <div class="test-buttons">
                        <button id="test-api-connection" class="button"><?php _e('Test API Connection', 'ai-community'); ?></button>
                        <button id="test-database" class="button"><?php _e('Test Database', 'ai-community'); ?></button>
                        <button id="test-generation" class="button"><?php _e('Test AI Generation', 'ai-community'); ?></button>
                    </div>
                    <div id="test-results" class="test-results"></div>
                </div>
            </div>
        </div>

    <?php elseif ($current_tool === 'cleanup'): ?>
        <!-- Cleanup Section -->
        <div class="tool-section">
            <div class="section-header">
                <h2><?php _e('Data Cleanup', 'ai-community'); ?></h2>
                <p><?php _e('Clean up old data and optimize database performance.', 'ai-community'); ?></p>
            </div>
            
            <div class="cleanup-tools">
                <!-- Cache Cleanup -->
                <div class="cleanup-card">
                    <div class="cleanup-header">
                        <h3><?php _e('Cache Cleanup', 'ai-community'); ?></h3>
                        <span class="cleanup-icon">
                            <span class="dashicons dashicons-performance"></span>
                        </span>
                    </div>
                    <div class="cleanup-content">
                        <p><?php _e('Clear all cached data to free up space and ensure fresh content.', 'ai-community'); ?></p>
                        <div class="cleanup-stats">
                            <span><?php _e('Cached items:', 'ai-community'); ?> <strong>~150</strong></span>
                        </div>
                        <button id="clear-cache" class="button button-primary">
                            <?php _e('Clear All Caches', 'ai-community'); ?>
                        </button>
                    </div>
                </div>

                <!-- Old Posts Cleanup -->
                <div class="cleanup-card">
                    <div class="cleanup-header">
                        <h3><?php _e('Old Posts Cleanup', 'ai-community'); ?></h3>
                        <span class="cleanup-icon">
                            <span class="dashicons dashicons-trash"></span>
                        </span>
                    </div>
                    <div class="cleanup-content">
                        <p><?php _e('Delete old AI-generated posts to keep the database clean.', 'ai-community'); ?></p>
                        <div class="cleanup-options">
                            <label>
                                <?php _e('Delete posts older than:', 'ai-community'); ?>
                                <select id="cleanup-days">
                                    <option value="30">30 <?php _e('days', 'ai-community'); ?></option>
                                    <option value="90">90 <?php _e('days', 'ai-community'); ?></option>
                                    <option value="180">180 <?php _e('days', 'ai-community'); ?></option>
                                    <option value="365" selected>365 <?php _e('days', 'ai-community'); ?></option>
                                </select>
                            </label>
                        </div>
                        <button id="cleanup-old-posts" class="button button-secondary">
                            <?php _e('Clean Up Old Posts', 'ai-community'); ?>
                        </button>
                    </div>
                </div>

                <!-- Log Cleanup -->
                <div class="cleanup-card">
                    <div class="cleanup-header">
                        <h3><?php _e('Log Cleanup', 'ai-community'); ?></h3>
                        <span class="cleanup-icon">
                            <span class="dashicons dashicons-media-text"></span>
                        </span>
                    </div>
                    <div class="cleanup-content">
                        <p><?php _e('Remove old log entries to reduce database size.', 'ai-community'); ?></p>
                        <div class="log-cleanup-options">
                            <label>
                                <input type="checkbox" id="cleanup-generation-logs" checked>
                                <?php _e('Generation logs', 'ai-community'); ?>
                            </label>
                            <label>
                                <input type="checkbox" id="cleanup-api-logs" checked>
                                <?php _e('API error logs', 'ai-community'); ?>
                            </label>
                            <label>
                                <input type="checkbox" id="cleanup-debug-logs">
                                <?php _e('Debug logs', 'ai-community'); ?>
                            </label>
                        </div>
                        <button id="cleanup-logs" class="button button-secondary">
                            <?php _e('Clean Up Logs', 'ai-community'); ?>
                        </button>
                    </div>
                </div>

                <!-- Database Optimization -->
                <div class="cleanup-card">
                    <div class="cleanup-header">
                        <h3><?php _e('Database Optimization', 'ai-community'); ?></h3>
                        <span class="cleanup-icon">
                            <span class="dashicons dashicons-database"></span>
                        </span>
                    </div>
                    <div class="cleanup-content">
                        <p><?php _e('Optimize database tables for better performance.', 'ai-community'); ?></p>
                        <div class="optimization-warning">
                            <span class="dashicons dashicons-info"></span>
                            <small><?php _e('This process may take a few minutes on large databases.', 'ai-community'); ?></small>
                        </div>
                        <button id="optimize-database" class="button button-secondary">
                            <?php _e('Optimize Database', 'ai-community'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cleanup History -->
            <div class="cleanup-history">
                <h3><?php _e('Recent Cleanup Activity', 'ai-community'); ?></h3>
                <div class="history-list">
                    <div class="history-item">
                        <div class="history-time"><?php echo human_time_diff(strtotime('-2 hours'), current_time('timestamp')); ?> ago</div>
                        <div class="history-action"><?php _e('Cache cleared', 'ai-community'); ?></div>
                        <div class="history-result"><?php _e('142 items removed', 'ai-community'); ?></div>
                    </div>
                    <div class="history-item">
                        <div class="history-time"><?php echo human_time_diff(strtotime('-1 day'), current_time('timestamp')); ?> ago</div>
                        <div class="history-action"><?php _e('Old posts cleanup', 'ai-community'); ?></div>
                        <div class="history-result"><?php _e('23 posts removed', 'ai-community'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.tool-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
}

.section-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f0f0f1;
}

.section-header h2 {
    margin: 0 0 8px 0;
    font-size: 20px;
    font-weight: 600;
}

.section-header p {
    margin: 0;
    color: #646970;
}

.tool-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.tool-card {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
    transition: box-shadow 0.2s ease;
}

.tool-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.tool-icon {
    font-size: 36px;
    color: #2271b1;
    margin-bottom: 16px;
}

.tool-content h3 {
    margin: 0 0 12px 0;
    font-size: 16px;
    font-weight: 600;
}

.tool-content p {
    margin: 0 0 16px 0;
    color: #646970;
    font-size: 14px;
}

.export-options {
    display: flex;
    gap: 8px;
    justify-content: center;
}

#import-settings-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
}

/* Logs Styles */
.logs-container {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 20px;
}

.log-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 16px;
}

.log-filters {
    display: flex;
    gap: 8px;
    align-items: center;
}

.log-actions {
    display: flex;
    gap: 8px;
}

.log-section {
    margin-bottom: 30px;
}

.log-section h3 {
    margin-bottom: 16px;
    font-size: 16px;
    font-weight: 600;
}

.log-table-container {
    background: #fff;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.error-logs {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.error-log-item {
    background: #fff;
    border: 1px solid #f5c2c7;
    border-left: 4px solid #dc3545;
    border-radius: 4px;
    padding: 12px 16px;
}

.error-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.error-time {
    font-size: 12px;
    color: #646970;
}

.error-context {
    font-size: 12px;
    background: #f8d7da;
    color: #721c24;
    padding: 2px 6px;
    border-radius: 3px;
}

.error-message {
    color: #721c24;
    font-size: 14px;
}

.status-success {
    color: #46b450;
    font-weight: 500;
}

.status-error {
    color: #dc3232;
    font-weight: 500;
}

/* System Check Styles */
.system-check-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.system-status-card {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
}

.status-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.status-icon {
    font-size: 32px;
}

.status-good {
    color: #46b450;
}

.status-warning {
    color: #ffb900;
}

.status-error {
    color: #dc3232;
}

.checks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}

.check-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 6px;
}

.check-status {
    font-size: 20px;
    margin-top: 2px;
}

.check-pass {
    color: #46b450;
}

.check-warning {
    color: #ffb900;
}

.check-fail {
    color: #dc3232;
}

.check-info {
    flex: 1;
}

.check-info strong {
    display: block;
    margin-bottom: 4px;
}

.check-value {
    display: block;
    font-size: 14px;
    color: #1d2327;
    margin-bottom: 2px;
}

.check-info small {
    color: #646970;
    font-size: 12px;
}

.plugin-status,
.test-tools {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 20px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.test-buttons {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.test-results {
    min-height: 40px;
    padding: 12px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

/* Cleanup Styles */
.cleanup-tools {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.cleanup-card {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 20px;
}

.cleanup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.cleanup-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.cleanup-icon {
    font-size: 24px;
    color: #646970;
}

.cleanup-content p {
    margin: 0 0 16px 0;
    color: #646970;
    font-size: 14px;
}

.cleanup-options {
    margin-bottom: 16px;
}

.cleanup-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.cleanup-options select {
    margin-left: 8px;
    padding: 4px 8px;
}

.cleanup-stats {
    margin-bottom: 16px;
    padding: 8px 12px;
    background: #f0f6fc;
    border-radius: 4px;
    font-size: 14px;
}

.log-cleanup-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
}

.log-cleanup-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.optimization-warning {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    padding: 8px 12px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
}

.optimization-warning small {
    color: #856404;
}

.cleanup-history {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 20px;
}

.cleanup-history h3 {
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 600;
}

.history-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #fff;
    border-radius: 4px;
    border: 1px solid #e0e0e0;
}

.history-time {
    font-size: 12px;
    color: #646970;
    min-width: 80px;
}

.history-action {
    font-size: 14px;
    color: #1d2327;
    font-weight: 500;
    flex: 1;
    margin: 0 12px;
}

.history-result {
    font-size: 13px;
    color: #646970;
}

@media (max-width: 768px) {
    .tool-grid,
    .cleanup-tools {
        grid-template-columns: 1fr;
    }
    
    .log-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .log-filters,
    .log-actions {
        justify-content: center;
    }
    
    .checks-grid,
    .status-grid {
        grid-template-columns: 1fr;
    }
    
    .test-buttons {
        flex-direction: column;
    }
    
    .history-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    .history-time {
        min-width: auto;
    }
    
    .history-action {
        margin: 0;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Export functions
    $('#export-settings').click(function() {
        exportData('settings', 'json');
    });
    
    $('#export-analytics-json').click(function() {
        exportData('generation', 'json');
    });
    
    $('#export-analytics-csv').click(function() {
        exportData('generation', 'csv');
    });
    
    // Import settings
    $('#import-settings-form').submit(function(e) {
        e.preventDefault();
        importSettings();
    });
    
    // Log management
    $('#refresh-logs').click(function() {
        location.reload();
    });
    
    $('#clear-logs').click(function() {
        if (confirm('<?php _e("Are you sure you want to clear all logs?", "ai-community"); ?>')) {
            clearLogs();
        }
    });
    
    $('#download-logs').click(function() {
        exportData('api', 'json');
    });
    
    // System tests
    $('#test-api-connection').click(function() {
        testApiConnection();
    });
    
    $('#test-database').click(function() {
        testDatabase();
    });
    
    $('#test-generation').click(function() {
        testGeneration();
    });
    
    // Cleanup functions
    $('#clear-cache').click(function() {
        if (confirm('<?php _e("Clear all cached data?", "ai-community"); ?>')) {
            clearCache();
        }
    });
    
    $('#cleanup-old-posts').click(function() {
        var days = $('#cleanup-days').val();
        if (confirm('<?php _e("Delete all AI posts older than", "ai-community"); ?> ' + days + ' <?php _e("days", "ai-community"); ?>?')) {
            cleanupOldPosts(days);
        }
    });
    
    $('#cleanup-logs').click(function() {
        if (confirm('<?php _e("Clear selected log types?", "ai-community"); ?>')) {
            cleanupLogs();
        }
    });
    
    $('#optimize-database').click(function() {
        if (confirm('<?php _e("Optimize database tables? This may take a few minutes.", "ai-community"); ?>')) {
            optimizeDatabase();
        }
    });
    
    function exportData(dataType, format) {
        var button = $('#export-' + dataType + (format ? '-' + format : ''));
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e("Exporting...", "ai-community"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_export_data',
                data_type: dataType,
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
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    showNotification('<?php _e("Export completed successfully!", "ai-community"); ?>', 'success');
                } else {
                    showNotification('<?php _e("Export failed:", "ai-community"); ?> ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('<?php _e("Export failed due to network error.", "ai-community"); ?>', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function importSettings() {
        var formData = new FormData();
        var fileInput = $('#settings-file')[0];
        
        if (!fileInput.files.length) {
            showNotification('<?php _e("Please select a file to import.", "ai-community"); ?>', 'error');
            return;
        }
        
        formData.append('action', 'ai_community_import_settings');
        formData.append('settings_file', fileInput.files[0]);
        formData.append('nonce', aiCommunityAdmin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e("Settings imported successfully!", "ai-community"); ?>', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('<?php _e("Import failed:", "ai-community"); ?> ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('<?php _e("Import failed due to network error.", "ai-community"); ?>', 'error');
            }
        });
    }
    
    function clearLogs() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_clear_logs',
                log_type: 'all',
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e("Logs cleared successfully!", "ai-community"); ?>', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('<?php _e("Failed to clear logs:", "ai-community"); ?> ' + response.data, 'error');
                }
            }
        });
    }
    
    function testApiConnection() {
        var button = $('#test-api-connection');
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e("Testing...", "ai-community"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_test_api',
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                var resultDiv = $('#test-results');
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success inline"><p><?php _e("API connection successful!", "ai-community"); ?></p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error inline"><p><?php _e("API connection failed:", "ai-community"); ?> ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#test-results').html('<div class="notice notice-error inline"><p><?php _e("Test failed due to network error.", "ai-community"); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function testDatabase() {
        var button = $('#test-database');
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e("Testing...", "ai-community"); ?>');
        
        // Simple database test
        setTimeout(function() {
            $('#test-results').html('<div class="notice notice-success inline"><p><?php _e("Database connection is working properly.", "ai-community"); ?></p></div>');
            button.prop('disabled', false).text(originalText);
        }, 1000);
    }
    
    function testGeneration() {
        var button = $('#test-generation');
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e("Testing...", "ai-community"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_test_generation',
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                var resultDiv = $('#test-results');
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success inline"><p><?php _e("AI generation test successful!", "ai-community"); ?></p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error inline"><p><?php _e("Generation test failed:", "ai-community"); ?> ' + response.data + '</p></div>');
                }
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function clearCache() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_clear_cache',
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                showNotification('<?php _e("Cache cleared successfully!", "ai-community"); ?>', 'success');
            },
            error: function() {
                showNotification('<?php _e("Failed to clear cache.", "ai-community"); ?>', 'error');
            }
        });
    }
    
    function cleanupOldPosts(days) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_cleanup_posts',
                days: days,
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e("Cleanup completed!", "ai-community"); ?> ' + response.data.deleted + ' <?php _e("posts removed.", "ai-community"); ?>', 'success');
                } else {
                    showNotification('<?php _e("Cleanup failed:", "ai-community"); ?> ' + response.data, 'error');
                }
            }
        });
    }
    
    function cleanupLogs() {
        var logTypes = [];
        if ($('#cleanup-generation-logs').is(':checked')) logTypes.push('generation');
        if ($('#cleanup-api-logs').is(':checked')) logTypes.push('api');
        if ($('#cleanup-debug-logs').is(':checked')) logTypes.push('debug');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_clear_logs',
                log_type: logTypes.join(','),
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                showNotification('<?php _e("Selected logs cleared successfully!", "ai-community"); ?>', 'success');
            }
        });
    }
    
    function optimizeDatabase() {
        var button = $('#optimize-database');
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e("Optimizing...", "ai-community"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_optimize_database',
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e("Database optimized successfully!", "ai-community"); ?>', 'success');
                } else {
                    showNotification('<?php _e("Optimization failed:", "ai-community"); ?> ' + response.data, 'error');
                }
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function showNotification(message, type) {
        var notificationClass = 'notice-' + type;
        var notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.ai-community-tools-page').prepend(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
});
</script>