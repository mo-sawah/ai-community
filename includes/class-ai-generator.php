<?php
/**
 * AI Community Content Generator Class
 * 
 * Handles automated AI content generation for the community
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Community_AI_Generator {
    
    /**
     * Settings instance
     */
    private $settings;
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * OpenRouter API instance
     */
    private $openrouter;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize properties as null
        $this->settings = null;
        $this->database = null;
        $this->openrouter = null;
        
        add_action('init', array($this, 'init'));
    }

    private function get_settings() {
        if (!$this->settings) {
            $this->settings = new AI_Community_Settings();
        }
        return $this->settings;
    }

    private function get_database() {
        if (!$this->database) {
            $this->database = new AI_Community_Database();
        }
        return $this->database;
    }

    private function get_openrouter() {
        if (!$this->openrouter) {
            $this->openrouter = new AI_Community_OpenRouter_API();
        }
        return $this->openrouter;
    }
    
    /**
     * Initialize the generator
     */
    public function init() {
        // Hook into WordPress cron
        add_action('ai_community_generate_content', array($this, 'scheduled_generation'));
        
        // Admin hooks
        add_action('wp_ajax_ai_community_generate_now', array($this, 'ajax_generate_now'));
        add_action('wp_ajax_ai_community_test_generation', array($this, 'ajax_test_generation'));
    }
    
    /**
     * Scheduled content generation (called by cron)
     */
    public function scheduled_generation() {
        if (!$this->get_settings()->get('ai_generation_enabled')) {
            return;
        }
        
        // Check if we should generate content based on schedule
        if (!$this->should_generate_now()) {
            return;
        }
        
        $this->log_generation_attempt('scheduled');
        
        try {
            $result = $this->generate_content_batch();
            $this->log_generation_result('scheduled', $result);
        } catch (Exception $e) {
            $this->log_generation_error('scheduled', $e->getMessage());
        }
    }
    
    /**
     * Manual content generation (admin triggered)
     */
    public function generate_content_manually() {
        if (!$this->get_settings()->get('ai_generation_enabled')) {
            return new WP_Error('disabled', __('AI content generation is disabled', 'ai-community'));
        }
        
        $this->log_generation_attempt('manual');
        
        try {
            $result = $this->generate_content_batch();
            $this->log_generation_result('manual', $result);
            return $result;
        } catch (Exception $e) {
            $this->log_generation_error('manual', $e->getMessage());
            return new WP_Error('generation_failed', $e->getMessage());
        }
    }
    
    /**
     * Generate a batch of content
     */
    private function generate_content_batch() {
        $posts_created = 0;
        $replies_created = 0;
        
        // Step 1: Fetch source content from websites
        $source_content = $this->fetch_source_content();
        
        if (empty($source_content)) {
            throw new Exception(__('No source content found to generate from', 'ai-community'));
        }
        
        // Step 2: Generate posts
        $posts_to_create = $this->calculate_posts_to_create();
        
        if ($posts_to_create > 0) {
            $generated_posts = $this->generate_posts($source_content, $posts_to_create);
            
            if (!is_wp_error($generated_posts)) {
                $posts_created = count($generated_posts);
                
                // Step 3: Generate replies for existing posts
                $replies_created = $this->generate_replies_for_recent_posts();
            }
        }
        
        // Step 4: Update generation statistics
        $this->update_generation_stats($posts_created, $replies_created);
        
        return array(
            'posts_created' => $posts_created,
            'replies_created' => $replies_created,
            'source_content_count' => count($source_content)
        );
    }
    
    /**
     * Fetch content from source websites
     */
    private function fetch_source_content() {
        $websites = $this->get_settings()->get('source_websites', array());
        $content = array();
        
        foreach ($websites as $website) {
            try {
                $site_content = $this->fetch_website_content($website);
                $content = array_merge($content, $site_content);
            } catch (Exception $e) {
                error_log("AI Community: Failed to fetch content from {$website}: " . $e->getMessage());
            }
        }
        
        // Sort by date (newest first)
        usort($content, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Limit to latest content
        return array_slice($content, 0, 20);
    }
    
    /**
     * Fetch content from a single website
     */
    private function fetch_website_content($website_url) {
        $content = array();
        
        // Try RSS feed first
        $rss_content = $this->fetch_rss_content($website_url);
        if (!empty($rss_content)) {
            return $rss_content;
        }
        
        // Fallback to WordPress REST API if it's a WordPress site
        $wp_api_content = $this->fetch_wordpress_api_content($website_url);
        if (!empty($wp_api_content)) {
            return $wp_api_content;
        }
        
        // Fallback to web scraping (basic)
        return $this->scrape_website_content($website_url);
    }
    
    /**
     * Fetch RSS content
     */
    private function fetch_rss_content($website_url) {
        $rss_urls = array(
            rtrim($website_url, '/') . '/feed/',
            rtrim($website_url, '/') . '/rss.xml',
            rtrim($website_url, '/') . '/feed.xml',
            rtrim($website_url, '/') . '/rss/',
            rtrim($website_url, '/') . '/atom.xml'
        );
        
        foreach ($rss_urls as $rss_url) {
            $feed = fetch_feed($rss_url);
            
            if (!is_wp_error($feed)) {
                $items = $feed->get_items(0, 10);
                $content = array();
                
                foreach ($items as $item) {
                    $content[] = array(
                        'title' => $item->get_title(),
                        'content' => $this->clean_content($item->get_content() ?: $item->get_description()),
                        'excerpt' => $this->clean_content($item->get_description()),
                        'url' => $item->get_link(),
                        'date' => $item->get_date('Y-m-d H:i:s'),
                        'author' => $item->get_author() ? $item->get_author()->get_name() : '',
                        'source' => 'rss'
                    );
                }
                
                return $content;
            }
        }
        
        return array();
    }
    
    /**
     * Fetch WordPress REST API content
     */
    private function fetch_wordpress_api_content($website_url) {
        $api_url = rtrim($website_url, '/') . '/wp-json/wp/v2/posts?per_page=10&_embed';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'AI Community Plugin/' . AI_COMMUNITY_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $posts = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!is_array($posts)) {
            return array();
        }
        
        $content = array();
        foreach ($posts as $post) {
            $content[] = array(
                'title' => $post['title']['rendered'] ?? '',
                'content' => $this->clean_content($post['content']['rendered'] ?? ''),
                'excerpt' => $this->clean_content($post['excerpt']['rendered'] ?? ''),
                'url' => $post['link'] ?? '',
                'date' => $post['date'] ?? current_time('Y-m-d H:i:s'),
                'author' => $post['_embedded']['author'][0]['name'] ?? '',
                'source' => 'wp-api'
            );
        }
        
        return $content;
    }
    
    /**
     * Basic web scraping fallback
     */
    private function scrape_website_content($website_url) {
        $response = wp_remote_get($website_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'AI Community Plugin/' . AI_COMMUNITY_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Basic HTML parsing to extract titles and content
        $content = array();
        
        // Extract page title
        preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $title_matches);
        $page_title = $title_matches[1] ?? 'Untitled';
        
        // Extract headings as potential topics
        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $html, $heading_matches);
        
        foreach (array_slice($heading_matches[1], 0, 5) as $heading) {
            $clean_heading = $this->clean_content($heading);
            if (strlen($clean_heading) > 10) {
                $content[] = array(
                    'title' => $clean_heading,
                    'content' => '',
                    'excerpt' => '',
                    'url' => $website_url,
                    'date' => current_time('Y-m-d H:i:s'),
                    'author' => '',
                    'source' => 'scrape'
                );
            }
        }
        
        // If no headings found, use page title
        if (empty($content)) {
            $content[] = array(
                'title' => $this->clean_content($page_title),
                'content' => '',
                'excerpt' => '',
                'url' => $website_url,
                'date' => current_time('Y-m-d H:i:s'),
                'author' => '',
                'source' => 'scrape'
            );
        }
        
        return $content;
    }
    
    /**
     * Generate posts from source content
     */
    private function generate_posts($source_content, $count) {
        $result = $this->openrouter->generate_posts_from_content($source_content, $count);
        
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        
        $created_posts = array();
        
        foreach ($result as $post_data) {
            $post_id = $this->create_ai_post($post_data);
            if ($post_id) {
                $created_posts[] = $post_id;
            }
        }
        
        return $created_posts;
    }
    
    /**
     * Create AI-generated post
     */
    private function create_ai_post($post_data) {
        // Get or create AI bot user
        $ai_user_id = $this->get_ai_bot_user();
        
        $data = array(
            'title' => $post_data['title'],
            'content' => $post_data['content'],
            'excerpt' => $post_data['excerpt'] ?? wp_trim_words($post_data['content'], 30),
            'author_id' => $ai_user_id,
            'community' => $this->validate_community($post_data['community']),
            'tags' => $post_data['tags'],
            'status' => 'published',
            'is_ai_generated' => 1,
            'ai_model' => $this->get_settings()->get('ai_model'),
            'meta_data' => array(
                'generation_time' => current_time('mysql'),
                'source_count' => count($this->fetch_source_content())
            )
        );
        
        return $this->get_database()->create_post($data);
    }
    
    /**
     * Generate replies for recent posts
     */
    private function generate_replies_for_recent_posts() {
        $replies_per_post = $this->get_settings()->get('replies_per_post', 3);
        
        if ($replies_per_post <= 0) {
            return 0;
        }
        
        // Get recent posts that need replies
        $recent_posts = $this->get_posts_needing_replies();
        $total_replies = 0;
        
        foreach ($recent_posts as $post) {
            try {
                $replies = $this->generate_replies_for_post($post, $replies_per_post);
                $total_replies += count($replies);
            } catch (Exception $e) {
                error_log("AI Community: Failed to generate replies for post {$post->id}: " . $e->getMessage());
            }
        }
        
        return $total_replies;
    }
    
    /**
     * Generate replies for a specific post
     */
    private function generate_replies_for_post($post, $count) {
        $post_data = array(
            'title' => $post->title,
            'content' => $post->content,
            'community' => $post->community
        );
        
        $result = $this->openrouter->generate_replies($post_data, $count);
        
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        
        $created_replies = array();
        
        foreach ($result as $reply_data) {
            $reply_id = $this->create_ai_reply($post->id, $reply_data);
            if ($reply_id) {
                $created_replies[] = $reply_id;
            }
        }
        
        return $created_replies;
    }
    
    /**
     * Create AI-generated reply
     */
    private function create_ai_reply($post_id, $reply_data) {
        global $wpdb;
        $tables = $this->get_database()->get_table_names();
        
        $ai_user_id = $this->get_ai_bot_user();
        
        $result = $wpdb->insert($tables['comments'], array(
            'post_id' => $post_id,
            'content' => $reply_data['content'],
            'author_id' => $ai_user_id,
            'status' => 'approved',
            'votes' => rand(1, 3), // Random initial votes
            'is_ai_generated' => 1,
            'ai_model' => $this->get_settings()->get('ai_model'),
            'author_name' => 'AI Assistant',
            'author_email' => 'ai@' . parse_url(get_site_url(), PHP_URL_HOST),
            'author_ip' => '127.0.0.1',
            'meta_data' => json_encode(array(
                'tone' => $reply_data['tone'] ?? 'helpful',
                'generation_time' => current_time('mysql')
            ))
        ));
        
        if ($result) {
            // Update post comment count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tables['posts']} SET comment_count = comment_count + 1 WHERE id = %d",
                $post_id
            ));
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get or create AI bot user
     */
    private function get_ai_bot_user() {
        $bot_username = 'ai_content_bot';
        $bot_user = get_user_by('login', $bot_username);
        
        if (!$bot_user) {
            $bot_user_id = wp_create_user(
                $bot_username,
                wp_generate_password(20),
                'ai-bot@' . parse_url(get_site_url(), PHP_URL_HOST)
            );
            
            if (!is_wp_error($bot_user_id)) {
                wp_update_user(array(
                    'ID' => $bot_user_id,
                    'display_name' => 'AI Assistant',
                    'description' => 'Automated AI content generator for community discussions',
                    'role' => 'subscriber'
                ));
                
                // Add custom meta to identify as AI bot
                update_user_meta($bot_user_id, 'ai_community_bot', true);
                
                return $bot_user_id;
            }
            
            return 1; // Fallback to admin user
        }
        
        return $bot_user->ID;
    }
    
    /**
     * Get posts that need replies
     */
    private function get_posts_needing_replies() {
        global $wpdb;
        $tables = $this->get_database()->get_table_names();
        
        // Get recent posts with low comment counts
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['posts']} 
             WHERE status = 'published' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND comment_count < 3
             AND is_ai_generated = 0
             ORDER BY created_at DESC, votes DESC
             LIMIT %d",
            5
        ));
    }
    
    /**
     * Calculate how many posts to create
     */
    private function calculate_posts_to_create() {
        $posts_per_day = $this->get_settings()->get('posts_per_day', 5);
        $max_per_run = ceil($posts_per_day / 4); // Assuming 4 runs per day
        
        // Check how many posts were created today
        $posts_today = $this->get_ai_posts_count_today();
        
        // Don't exceed daily limit
        $remaining_today = max(0, $posts_per_day - $posts_today);
        
        return min($max_per_run, $remaining_today);
    }
    
    /**
     * Get count of AI posts created today
     */
    private function get_ai_posts_count_today() {
        global $wpdb;
        $tables = $this->get_database()->get_table_names();
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['posts']} 
             WHERE is_ai_generated = 1 
             AND DATE(created_at) = CURDATE()"
        );
    }
    
    /**
     * Check if we should generate content now
     */
    private function should_generate_now() {
        $schedule = $this->get_settings()->get('ai_generation_schedule', 'hourly');
        $last_run = get_option('ai_community_last_generation_run', 0);
        $current_time = current_time('timestamp');
        
        $intervals = array(
            'hourly' => HOUR_IN_SECONDS,
            'twicedaily' => 12 * HOUR_IN_SECONDS,
            'daily' => DAY_IN_SECONDS,
            'manual' => PHP_INT_MAX // Never run automatically
        );
        
        $interval = $intervals[$schedule] ?? HOUR_IN_SECONDS;
        
        return ($current_time - $last_run) >= $interval;
    }
    
    /**
     * Validate community slug
     */
    private function validate_community($community_slug) {
        global $wpdb;
        $tables = $this->get_database()->get_table_names();
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['communities']} WHERE slug = %s",
            $community_slug
        ));
        
        return $exists ? $community_slug : 'general';
    }
    
    /**
     * Clean content from HTML and unwanted characters
     */
    private function clean_content($content) {
        // Remove HTML tags
        $content = wp_strip_all_tags($content);
        
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Trim
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Update generation statistics
     */
    private function update_generation_stats($posts_created, $replies_created) {
        $stats = get_option('ai_community_generation_stats', array());
        $today = date('Y-m-d');
        
        if (!isset($stats[$today])) {
            $stats[$today] = array(
                'posts' => 0,
                'replies' => 0,
                'runs' => 0,
                'errors' => 0
            );
        }
        
        $stats[$today]['posts'] += $posts_created;
        $stats[$today]['replies'] += $replies_created;
        $stats[$today]['runs']++;
        
        // Keep only last 30 days
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        foreach ($stats as $date => $day_stats) {
            if ($date < $cutoff_date) {
                unset($stats[$date]);
            }
        }
        
        update_option('ai_community_generation_stats', $stats);
        update_option('ai_community_last_generation_run', current_time('timestamp'));
    }
    
    /**
     * Log generation attempt
     */
    private function log_generation_attempt($type) {
        if ($this->get_settings()->get('debug_mode')) {
            error_log("AI Community: Starting {$type} content generation");
        }
        
        $this->log_generation_event('attempt', $type);
    }
    
    /**
     * Log generation result
     */
    private function log_generation_result($type, $result) {
        if ($this->get_settings()->get('debug_mode')) {
            error_log("AI Community: {$type} generation completed - Posts: {$result['posts_created']}, Replies: {$result['replies_created']}");
        }
        
        $this->log_generation_event('success', $type, $result);
    }
    
    /**
     * Log generation error
     */
    private function log_generation_error($type, $error_message) {
        error_log("AI Community: {$type} generation failed - {$error_message}");
        
        $this->log_generation_event('error', $type, array('error' => $error_message));
        
        // Update error count in stats
        $stats = get_option('ai_community_generation_stats', array());
        $today = date('Y-m-d');
        
        if (!isset($stats[$today])) {
            $stats[$today] = array('posts' => 0, 'replies' => 0, 'runs' => 0, 'errors' => 0);
        }
        
        $stats[$today]['errors']++;
        update_option('ai_community_generation_stats', $stats);
    }
    
    /**
     * Log generation event
     */
    private function log_generation_event($event_type, $trigger_type, $data = array()) {
        $logs = get_option('ai_community_generation_logs', array());
        
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'event' => $event_type,
            'trigger' => $trigger_type,
            'data' => $data,
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        );
        
        // Keep only last 100 logs
        $logs = array_slice($logs, -100);
        
        update_option('ai_community_generation_logs', $logs);
    }
    
    /**
     * Get generation statistics
     */
    public function get_generation_stats($days = 30) {
        $stats = get_option('ai_community_generation_stats', array());
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $filtered_stats = array();
        $totals = array('posts' => 0, 'replies' => 0, 'runs' => 0, 'errors' => 0);
        
        foreach ($stats as $date => $day_stats) {
            if ($date >= $cutoff_date) {
                $filtered_stats[$date] = $day_stats;
                foreach ($totals as $key => $value) {
                    $totals[$key] += $day_stats[$key] ?? 0;
                }
            }
        }
        
        return array(
            'daily' => $filtered_stats,
            'totals' => $totals,
            'averages' => array(
                'posts_per_day' => $totals['posts'] / max(1, count($filtered_stats)),
                'replies_per_day' => $totals['replies'] / max(1, count($filtered_stats)),
                'success_rate' => $totals['runs'] > 0 ? (($totals['runs'] - $totals['errors']) / $totals['runs']) * 100 : 0
            )
        );
    }
    
    /**
     * Get generation logs
     */
    public function get_generation_logs($limit = 50) {
        $logs = get_option('ai_community_generation_logs', array());
        return array_slice(array_reverse($logs), 0, $limit);
    }
    
    /**
     * Test AI generation (for admin)
     */
    public function test_generation($source_content = null) {
        if (!$source_content) {
            $source_content = $this->fetch_source_content();
        }
        
        if (empty($source_content)) {
            return new WP_Error('no_content', __('No source content available for testing', 'ai-community'));
        }
        
        // Generate test content without saving
        $result = $this->openrouter->generate_posts_from_content(
            array_slice($source_content, 0, 3), 
            2
        );
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return array(
            'success' => true,
            'source_content_count' => count($source_content),
            'generated_posts' => $result,
            'message' => __('Test generation successful', 'ai-community')
        );
    }
    
    /**
     * AJAX handler for manual generation
     */
    public function ajax_generate_now() {
        check_ajax_referer('ai_community_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-community'));
        }
        
        $result = $this->generate_content_manually();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX handler for test generation
     */
    public function ajax_test_generation() {
        check_ajax_referer('ai_community_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-community'));
        }
        
        $result = $this->test_generation();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Cleanup old AI content
     */
    public function cleanup_old_content($days = null) {
        if (!$days) {
            $days = $this->get_settings()->get('cleanup_old_posts_days', 365);
        }
        
        if ($days <= 0) {
            return 0;
        }
        
        return $this->get_database()->cleanup_old_posts($days);
    }
    
    /**
     * Get AI content quality report
     */
    public function get_quality_report($days = 7) {
        global $wpdb;
        $tables = $this->get_database()->get_table_names();
        
        // Get AI posts from last N days
        $ai_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, content, votes, comment_count, view_count, created_at 
             FROM {$tables['posts']} 
             WHERE is_ai_generated = 1 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY created_at DESC",
            $days
        ));
        
        $quality_scores = array();
        $total_score = 0;
        
        foreach ($ai_posts as $post) {
            $score = $this->openrouter->get_content_quality_score($post->content);
            $quality_scores[] = array(
                'post_id' => $post->id,
                'title' => $post->title,
                'quality_score' => $score,
                'engagement_score' => ($post->votes * 0.4) + ($post->comment_count * 0.6),
                'views' => $post->view_count,
                'created_at' => $post->created_at
            );
            $total_score += $score;
        }
        
        $avg_quality = count($quality_scores) > 0 ? $total_score / count($quality_scores) : 0;
        
        return array(
            'posts' => $quality_scores,
            'summary' => array(
                'total_posts' => count($quality_scores),
                'average_quality' => $avg_quality,
                'high_quality_posts' => count(array_filter($quality_scores, function($p) { return $p['quality_score'] >= 0.8; })),
                'low_quality_posts' => count(array_filter($quality_scores, function($p) { return $p['quality_score'] < 0.5; }))
            )
        );
    }
    
    /**
     * Schedule next generation run
     */
    public function schedule_next_run() {
        $schedule = $this->get_settings()->get('ai_generation_schedule', 'hourly');
        
        if ($schedule === 'manual') {
            return; // Don't schedule automatic runs
        }
        
        // Clear existing scheduled events
        wp_clear_scheduled_hook('ai_community_generate_content');
        
        // Schedule next run
        wp_schedule_event(time(), $schedule, 'ai_community_generate_content');
    }
    
    /**
     * Get next scheduled run time
     */
    public function get_next_run_time() {
        return wp_next_scheduled('ai_community_generate_content');
    }
    
    /**
     * Check system requirements for AI generation
     */
    public function check_system_requirements() {
        $checks = array();
        
        // API key check
        $api_key = $this->get_settings()->get('openrouter_api_key');
        $checks['api_key'] = array(
            'status' => !empty($api_key),
            'message' => empty($api_key) ? 
                __('OpenRouter API key is required', 'ai-community') : 
                __('API key configured', 'ai-community')
        );
        
        // Source websites check
        $websites = $this->get_settings()->get('source_websites', array());
        $checks['source_websites'] = array(
            'status' => !empty($websites),
            'message' => empty($websites) ? 
                __('At least one source website is required', 'ai-community') : 
                sprintf(__('%d source websites configured', 'ai-community'), count($websites))
        );
        
        // WP Cron check
        $checks['wp_cron'] = array(
            'status' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'message' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 
                __('WP Cron is disabled - automatic generation will not work', 'ai-community') : 
                __('WP Cron is enabled', 'ai-community')
        );
        
        // Memory check
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $checks['memory'] = array(
            'status' => $memory_limit >= (128 * 1024 * 1024), // 128MB
            'message' => $memory_limit < (128 * 1024 * 1024) ? 
                __('Low memory limit may cause generation failures', 'ai-community') : 
                __('Memory limit adequate', 'ai-community')
        );
        
        // Overall status
        $all_good = array_reduce($checks, function($carry, $check) {
            return $carry && $check['status'];
        }, true);
        
        return array(
            'overall_status' => $all_good,
            'checks' => $checks
        );
    }
    
    /**
     * Export generation data for debugging
     */
    public function export_generation_data() {
        return array(
            'stats' => $this->get_generation_stats(30),
            'logs' => $this->get_generation_logs(100),
            'quality_report' => $this->get_quality_report(7),
            'system_check' => $this->check_system_requirements(),
            'settings' => array(
                'enabled' => $this->get_settings()->get('ai_generation_enabled'),
                'posts_per_day' => $this->get_settings()->get('posts_per_day'),
                'replies_per_post' => $this->get_settings()->get('replies_per_post'),
                'schedule' => $this->get_settings()->get('ai_generation_schedule'),
                'model' => $this->get_settings()->get('ai_model'),
                'source_websites' => $this->get_settings()->get('source_websites')
            ),
            'export_time' => current_time('mysql'),
            'plugin_version' => AI_COMMUNITY_VERSION
        );
    }
}