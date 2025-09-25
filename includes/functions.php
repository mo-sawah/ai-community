<?php
/**
 * AI Community Helper Functions
 * 
 * Global utility functions for the AI Community plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin instance
 */
function ai_community() {
    return AI_Community_Plugin::get_instance();
}

/**
 * Get plugin settings
 */
function ai_community_get_settings() {
    static $settings = null;
    
    if ($settings === null) {
        $settings = new AI_Community_Settings();
    }
    
    return $settings;
}

/**
 * Get a specific setting value
 */
function ai_community_get_setting($key, $default = null) {
    return ai_community_get_settings()->get($key, $default);
}

/**
 * Check if a feature is enabled
 */
function ai_community_is_enabled($feature) {
    return ai_community_get_settings()->is_enabled($feature);
}

/**
 * Get database instance
 */
function ai_community_get_database() {
    static $database = null;
    
    if ($database === null) {
        $database = new AI_Community_Database();
    }
    
    return $database;
}

/**
 * Get posts with optional filters
 */
function ai_community_get_posts($args = array()) {
    return ai_community_get_database()->get_posts($args);
}

/**
 * Get single post by ID
 */
function ai_community_get_post($post_id) {
    return ai_community_get_database()->get_post($post_id);
}

/**
 * Create a new community post
 */
function ai_community_create_post($data) {
    return ai_community_get_database()->create_post($data);
}

/**
 * Get communities
 */
function ai_community_get_communities() {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    return $wpdb->get_results(
        "SELECT * FROM {$tables['communities']} 
         WHERE status = 'active' 
         ORDER BY name ASC"
    );
}

/**
 * Get user karma
 */
function ai_community_get_user_karma($user_id) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT karma FROM {$tables['user_meta']} WHERE user_id = %d",
        $user_id
    ));
}

/**
 * Update user karma
 */
function ai_community_update_user_karma($user_id, $amount) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    // Check if user meta exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT karma FROM {$tables['user_meta']} WHERE user_id = %d",
        $user_id
    ));
    
    if ($existing !== null) {
        $wpdb->update(
            $tables['user_meta'],
            array('karma' => $existing + $amount),
            array('user_id' => $user_id),
            array('%d'),
            array('%d')
        );
    } else {
        $wpdb->insert(
            $tables['user_meta'],
            array('user_id' => $user_id, 'karma' => $amount),
            array('%d', '%d')
        );
    }
    
    return true;
}

/**
 * Check if user can perform action based on karma
 */
function ai_community_user_can_action($user_id, $action) {
    if (!ai_community_is_enabled('karma_system')) {
        return true;
    }
    
    $karma_requirements = array(
        'post' => ai_community_get_setting('min_karma_to_post', 0),
        'vote' => ai_community_get_setting('min_karma_to_vote', 0),
        'comment' => ai_community_get_setting('min_karma_to_comment', 0)
    );
    
    $required_karma = $karma_requirements[$action] ?? 0;
    $user_karma = ai_community_get_user_karma($user_id);
    
    return $user_karma >= $required_karma;
}

/**
 * Format time ago string
 */
function ai_community_time_ago($datetime) {
    $time = current_time('timestamp') - strtotime($datetime);
    
    if ($time < 60) {
        return __('just now', 'ai-community');
    } elseif ($time < 3600) {
        $mins = floor($time / 60);
        return sprintf(_n('%d minute ago', '%d minutes ago', $mins, 'ai-community'), $mins);
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'ai-community'), $hours);
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'ai-community'), $days);
    } else {
        return date_i18n(get_option('date_format'), strtotime($datetime));
    }
}

/**
 * Format number for display
 */
function ai_community_format_number($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'k';
    } else {
        return number_format($number);
    }
}

/**
 * Generate excerpt from content
 */
function ai_community_generate_excerpt($content, $length = 30) {
    $content = wp_strip_all_tags($content);
    return wp_trim_words($content, $length);
}

/**
 * Get community color
 */
function ai_community_get_community_color($community_slug) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    $color = $wpdb->get_var($wpdb->prepare(
        "SELECT color FROM {$tables['communities']} WHERE slug = %s",
        $community_slug
    ));
    
    return $color ?: '#6366f1';
}

/**
 * Check if content is AI generated
 */
function ai_community_is_ai_content($post_id) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT is_ai_generated FROM {$tables['posts']} WHERE id = %d",
        $post_id
    ));
}

/**
 * Log plugin activity
 */
function ai_community_log($message, $level = 'info', $context = array()) {
    if (!ai_community_get_setting('debug_mode')) {
        return;
    }
    
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    error_log('AI Community [' . strtoupper($level) . ']: ' . $message . ' ' . json_encode($context));
    
    // Store in database for admin viewing
    $logs = get_option('ai_community_debug_logs', array());
    $logs[] = $log_entry;
    
    // Keep only last 100 logs
    $logs = array_slice($logs, -100);
    update_option('ai_community_debug_logs', $logs);
}

/**
 * Send notification to user
 */
function ai_community_send_notification($user_id, $type, $data = array()) {
    if (!ai_community_get_setting('email_notifications')) {
        return false;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $notifications = array(
        'new_comment' => array(
            'subject' => __('New comment on your post', 'ai-community'),
            'template' => 'new_comment'
        ),
        'post_voted' => array(
            'subject' => __('Your post received a vote', 'ai-community'),
            'template' => 'post_voted'
        ),
        'mention' => array(
            'subject' => __('You were mentioned in a post', 'ai-community'),
            'template' => 'mention'
        )
    );
    
    if (!isset($notifications[$type])) {
        return false;
    }
    
    $notification = $notifications[$type];
    $subject = $notification['subject'];
    $message = ai_community_render_email_template($notification['template'], $data);
    
    return wp_mail($user->user_email, $subject, $message);
}

/**
 * Render email template
 */
function ai_community_render_email_template($template, $data = array()) {
    $site_name = get_bloginfo('name');
    $site_url = get_site_url();
    
    $templates = array(
        'new_comment' => sprintf(
            __('Hello,\n\nYou received a new comment on your post "%s".\n\nComment: %s\n\nView the post: %s\n\nBest regards,\n%s', 'ai-community'),
            $data['post_title'] ?? '',
            $data['comment_content'] ?? '',
            $data['post_url'] ?? $site_url,
            $site_name
        ),
        'post_voted' => sprintf(
            __('Hello,\n\nYour post "%s" received a %s vote!\n\nView the post: %s\n\nBest regards,\n%s', 'ai-community'),
            $data['post_title'] ?? '',
            $data['vote_type'] ?? 'up',
            $data['post_url'] ?? $site_url,
            $site_name
        ),
        'mention' => sprintf(
            __('Hello,\n\nYou were mentioned in a post: "%s"\n\nView the post: %s\n\nBest regards,\n%s', 'ai-community'),
            $data['post_title'] ?? '',
            $data['post_url'] ?? $site_url,
            $site_name
        )
    );
    
    return $templates[$template] ?? '';
}

/**
 * Get avatar URL for user
 */
function ai_community_get_avatar_url($user_id, $size = 64) {
    return get_avatar_url($user_id, array('size' => $size));
}

/**
 * Get user initials for avatar fallback
 */
function ai_community_get_user_initials($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return 'U';
    }
    
    $name_parts = explode(' ', $user->display_name);
    $initials = '';
    
    foreach (array_slice($name_parts, 0, 2) as $part) {
        $initials .= substr($part, 0, 1);
    }
    
    return strtoupper($initials) ?: substr($user->user_login, 0, 2);
}

/**
 * Validate community slug
 */
function ai_community_validate_community($slug) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$tables['communities']} WHERE slug = %s AND status = 'active'",
        $slug
    ));
    
    return $exists > 0;
}

/**
 * Sanitize community slug
 */
function ai_community_sanitize_slug($slug) {
    $slug = sanitize_title($slug);
    $slug = preg_replace('/[^a-zA-Z0-9-_]/', '', $slug);
    return substr($slug, 0, 50);
}

/**
 * Get post permalink
 */
function ai_community_get_post_permalink($post_id) {
    // In a real implementation, this would generate a proper permalink
    // For now, return a hash-based URL
    return get_site_url() . '/#/post/' . $post_id;
}

/**
 * Get community permalink
 */
function ai_community_get_community_permalink($community_slug) {
    return get_site_url() . '/#/community/' . $community_slug;
}

/**
 * Check if user has voted on post
 */
function ai_community_user_has_voted($user_id, $post_id, $comment_id = null) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    $where = array('user_id = %d');
    $values = array($user_id);
    
    if ($comment_id) {
        $where[] = 'comment_id = %d';
        $values[] = $comment_id;
    } else {
        $where[] = 'post_id = %d';
        $values[] = $post_id;
    }
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT vote_type FROM {$tables['votes']} WHERE " . implode(' AND ', $where),
        $values
    ));
}

/**
 * Get post vote count
 */
function ai_community_get_post_votes($post_id) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    $votes = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) as upvotes,
            SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) as downvotes
         FROM {$tables['votes']} WHERE post_id = %d",
        $post_id
    ));
    
    return array(
        'upvotes' => (int) ($votes->upvotes ?? 0),
        'downvotes' => (int) ($votes->downvotes ?? 0),
        'total' => (int) (($votes->upvotes ?? 0) - ($votes->downvotes ?? 0))
    );
}

/**
 * Get trending posts
 */
function ai_community_get_trending_posts($limit = 10, $days = 7) {
    return ai_community_get_posts(array(
        'sort' => 'hot',
        'per_page' => $limit,
        'date_after' => date('Y-m-d', strtotime("-{$days} days"))
    ));
}

/**
 * Get user's recent activity
 */
function ai_community_get_user_activity($user_id, $limit = 20) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    // Get posts
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT 'post' as type, id, title as content, created_at, community 
         FROM {$tables['posts']} 
         WHERE author_id = %d AND status = 'published'
         ORDER BY created_at DESC LIMIT %d",
        $user_id, $limit / 2
    ));
    
    // Get comments
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT 'comment' as type, c.id, c.content, c.created_at, p.title as post_title
         FROM {$tables['comments']} c
         JOIN {$tables['posts']} p ON c.post_id = p.id
         WHERE c.author_id = %d AND c.status = 'approved'
         ORDER BY c.created_at DESC LIMIT %d",
        $user_id, $limit / 2
    ));
    
    // Combine and sort
    $activity = array_merge($posts, $comments);
    usort($activity, function($a, $b) {
        return strtotime($b->created_at) - strtotime($a->created_at);
    });
    
    return array_slice($activity, 0, $limit);
}

/**
 * Get popular tags
 */
function ai_community_get_popular_tags($limit = 20) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    $posts = $wpdb->get_col(
        "SELECT tags FROM {$tables['posts']} 
         WHERE tags IS NOT NULL AND tags != '' 
         AND status = 'published'"
    );
    
    $tag_counts = array();
    
    foreach ($posts as $tags_string) {
        $tags = explode(',', $tags_string);
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (!empty($tag)) {
                $tag_counts[$tag] = ($tag_counts[$tag] ?? 0) + 1;
            }
        }
    }
    
    arsort($tag_counts);
    
    return array_slice($tag_counts, 0, $limit, true);
}

/**
 * Search posts and comments
 */
function ai_community_search($query, $type = 'posts', $limit = 20) {
    if ($type === 'posts') {
        return ai_community_get_posts(array(
            'search' => $query,
            'per_page' => $limit
        ));
    } elseif ($type === 'users') {
        return ai_community_search_users($query, $limit);
    }
    
    return array();
}

/**
 * Search users
 */
function ai_community_search_users($query, $limit = 10) {
    global $wpdb;
    
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, user_login, display_name 
         FROM {$wpdb->users} 
         WHERE display_name LIKE %s OR user_login LIKE %s 
         ORDER BY display_name ASC 
         LIMIT %d",
        '%' . $wpdb->esc_like($query) . '%',
        '%' . $wpdb->esc_like($query) . '%',
        $limit
    ));
    
    foreach ($users as &$user) {
        $user->karma = ai_community_get_user_karma($user->ID);
        $user->avatar_url = ai_community_get_avatar_url($user->ID);
    }
    
    return $users;
}

/**
 * Get post tags as array
 */
function ai_community_get_post_tags($post_id) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    $tags_string = $wpdb->get_var($wpdb->prepare(
        "SELECT tags FROM {$tables['posts']} WHERE id = %d",
        $post_id
    ));
    
    if (empty($tags_string)) {
        return array();
    }
    
    return array_map('trim', explode(',', $tags_string));
}

/**
 * Check if user is community moderator
 */
function ai_community_is_moderator($user_id, $community_slug = null) {
    // Check if user has general moderation capabilities
    if (user_can($user_id, 'moderate_ai_community')) {
        return true;
    }
    
    // Check community-specific moderation
    if ($community_slug) {
        $moderators = get_option("ai_community_{$community_slug}_moderators", array());
        return in_array($user_id, $moderators);
    }
    
    return false;
}

/**
 * Get community statistics
 */
function ai_community_get_community_stats($community_slug) {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(p.id) as post_count,
            COUNT(DISTINCT p.author_id) as unique_authors,
            SUM(p.votes) as total_votes,
            SUM(p.view_count) as total_views,
            AVG(p.votes) as avg_votes
         FROM {$tables['posts']} p
         WHERE p.community = %s AND p.status = 'published'",
        $community_slug
    ));
    
    return array(
        'post_count' => (int) ($stats->post_count ?? 0),
        'unique_authors' => (int) ($stats->unique_authors ?? 0),
        'total_votes' => (int) ($stats->total_votes ?? 0),
        'total_views' => (int) ($stats->total_views ?? 0),
        'avg_votes' => round($stats->avg_votes ?? 0, 1)
    );
}

/**
 * Cache wrapper for expensive operations
 */
function ai_community_cache_get_or_set($key, $callback, $expiration = 3600) {
    if (!ai_community_get_setting('cache_enabled', true)) {
        return $callback();
    }
    
    $cache_key = 'ai_community_' . md5($key);
    $cached_value = get_transient($cache_key);
    
    if ($cached_value !== false) {
        return $cached_value;
    }
    
    $value = $callback();
    set_transient($cache_key, $value, $expiration);
    
    return $value;
}

/**
 * Clear all plugin caches
 */
function ai_community_clear_cache() {
    global $wpdb;
    
    // Delete all plugin transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_ai_community_%' 
         OR option_name LIKE '_transient_timeout_ai_community_%'"
    );
    
    // Clear object cache if available
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('ai_community');
    }
}

/**
 * Get plugin URLs and paths
 */
function ai_community_get_plugin_url($path = '') {
    return AI_COMMUNITY_PLUGIN_URL . ltrim($path, '/');
}

function ai_community_get_plugin_path($path = '') {
    return AI_COMMUNITY_PLUGIN_DIR . ltrim($path, '/');
}

/**
 * Check if plugin is properly configured
 */
function ai_community_is_configured() {
    $api_key = ai_community_get_setting('openrouter_api_key');
    $source_websites = ai_community_get_setting('source_websites', array());
    
    return !empty($api_key) && !empty($source_websites);
}

/**
 * Get plugin status
 */
function ai_community_get_status() {
    return array(
        'configured' => ai_community_is_configured(),
        'ai_enabled' => ai_community_get_setting('ai_generation_enabled', true),
        'total_posts' => ai_community_get_database()->get_posts_count(),
        'ai_posts_today' => ai_community_get_ai_posts_today(),
        'next_generation' => wp_next_scheduled('ai_community_generate_content')
    );
}

/**
 * Get AI posts created today
 */
function ai_community_get_ai_posts_today() {
    global $wpdb;
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$tables['posts']} 
         WHERE is_ai_generated = 1 AND DATE(created_at) = CURDATE()"
    );
}

/**
 * Format bytes to human readable
 */
function ai_community_format_bytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get system info for debugging
 */
function ai_community_get_system_info() {
    global $wpdb;
    
    return array(
        'plugin_version' => AI_COMMUNITY_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'mysql_version' => $wpdb->db_version(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'wp_cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
        'multisite' => is_multisite(),
        'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
        'active_plugins' => get_option('active_plugins'),
        'active_theme' => wp_get_theme()->get('Name')
    );
}

/**
 * Plugin activation hook helpers
 */
function ai_community_flush_rewrite_rules() {
    flush_rewrite_rules();
}

/**
 * Plugin deactivation cleanup
 */
function ai_community_cleanup_on_deactivation() {
    // Clear scheduled events
    wp_clear_scheduled_hook('ai_community_generate_content');
    wp_clear_scheduled_hook('ai_community_cleanup_old_posts');
    
    // Clear caches
    ai_community_clear_cache();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin uninstall cleanup
 */
function ai_community_cleanup_on_uninstall() {
    global $wpdb;
    
    // Only proceed if user explicitly wants to delete data
    if (!get_option('ai_community_delete_data_on_uninstall', false)) {
        return;
    }
    
    $database = ai_community_get_database();
    $tables = $database->get_table_names();
    
    // Drop custom tables
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Delete options
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE 'ai_community_%'"
    );
    
    // Delete user meta
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} 
         WHERE meta_key LIKE 'ai_community_%'"
    );
    
    // Clear caches
    ai_community_clear_cache();
}

// Hook cleanup functions
if (defined('AI_COMMUNITY_PLUGIN_FILE')) {
    register_deactivation_hook(AI_COMMUNITY_PLUGIN_FILE, 'ai_community_cleanup_on_deactivation');
    register_uninstall_hook(AI_COMMUNITY_PLUGIN_FILE, 'ai_community_cleanup_on_uninstall');
}

/**
 * Debug helper - only works when debug mode is enabled
 */
function ai_community_debug($data, $label = '') {
    if (!ai_community_get_setting('debug_mode')) {
        return;
    }
    
    $output = $label ? "[$label] " : '';
    $output .= print_r($data, true);
    
    error_log("AI Community Debug: {$output}");
}

/**
 * Migration helper for database updates
 */
function ai_community_maybe_migrate() {
    $current_version = get_option('ai_community_db_version', '0.0.0');
    
    if (version_compare($current_version, AI_Community_Database::DB_VERSION, '<')) {
        // Run migrations
        $database = ai_community_get_database();
        
        if (version_compare($current_version, '1.0.0', '<')) {
            $database->update_tables_to_v1();
        }
        
        update_option('ai_community_db_version', AI_Community_Database::DB_VERSION);
    }
}

// Initialize migration check on admin pages
if (is_admin()) {
    add_action('admin_init', 'ai_community_maybe_migrate');
}