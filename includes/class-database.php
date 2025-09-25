<?php
/**
 * AI Community Database Layer
 * * Enhanced database operations with proper error handling, caching, and performance optimization
 */

if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class AI_Community_Database {
    
    use AI_Community_Logger;
    
    /**
     * Database version for migrations
     */
    public const DB_VERSION = '2.0.0';
    
    /**
     * Table names
     */
    private array $tables = [];
    
    /**
     * Cache instance
     */
    private ?AI_Community_Cache $cache = null;
    
    /**
     * Query statistics
     */
    private array $query_stats = [
        'total' => 0,
        'cached' => 0,
        'time' => 0,
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->tables = [
            'posts' => $wpdb->prefix . 'ai_community_posts',
            'comments' => $wpdb->prefix . 'ai_community_comments',
            'votes' => $wpdb->prefix . 'ai_community_votes',
            'communities' => $wpdb->prefix . 'ai_community_communities',
            'user_meta' => $wpdb->prefix . 'ai_community_user_meta',
            'analytics' => $wpdb->prefix . 'ai_community_analytics',
            'notifications' => $wpdb->prefix . 'ai_community_notifications',
            'logs' => $wpdb->prefix . 'ai_community_logs',
            'sessions' => $wpdb->prefix . 'ai_community_sessions',
            'moderation_queue' => $wpdb->prefix . 'ai_community_moderation_queue',
        ];
    }
    
    /**
     * Initialize database
     */
    public function init(): void {
        add_action('ai_community_create_tables', [$this, 'create_tables']);
        add_action('ai_community_cleanup_expired', [$this, 'cleanup_expired_data']);
        
        // Set cache instance if available
        try {
            $container = AI_Community_Container::get_instance();
            if ($container->has('cache')) {
                $this->cache = $container->get('cache');
            }
        } catch (Throwable $e) {
            $this->log_debug('Cache not available for database operations');
        }
    }
    
    /**
     * Create all database tables
     */
    public function create_tables(): bool {
        try {
            $this->log_info('Creating database tables');
            
            $created_tables = [];
            
            // Create tables in dependency order
            $table_methods = [
                'create_communities_table',
                'create_user_meta_table',
                'create_posts_table',
                'create_comments_table',
                'create_votes_table',
                'create_analytics_table',
                'create_notifications_table',
                'create_logs_table',
                'create_sessions_table',
                'create_moderation_queue_table',
            ];
            
            foreach ($table_methods as $method) {
                if (method_exists($this, $method)) {
                    $result = $this->$method();
                    if ($result) {
                        $created_tables[] = str_replace('create_', '', str_replace('_table', '', $method));
                    }
                }
            }
            
            // Update database version
            update_option('ai_community_db_version', self::DB_VERSION);
            
            $this->log_info('Database tables created successfully', ['tables' => $created_tables]);
            
            return true;
            
        } catch (Throwable $e) {
            $this->log_error('Failed to create database tables', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Create posts table with optimized schema
     */
    private function create_posts_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['posts'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `title` text NOT NULL,
            `content` longtext NOT NULL,
            `excerpt` text,
            `author_id` bigint(20) unsigned NOT NULL,
            `community_slug` varchar(100) NOT NULL DEFAULT 'general',
            `tags` text,
            `status` enum('draft','published','pending','private','trash') NOT NULL DEFAULT 'published',
            `featured_image` varchar(500),
            `votes_up` int(11) unsigned DEFAULT 0,
            `votes_down` int(11) unsigned DEFAULT 0,
            `votes_total` int(11) DEFAULT 0,
            `comment_count` int(11) unsigned DEFAULT 0,
            `view_count` int(11) unsigned DEFAULT 0,
            `is_ai_generated` tinyint(1) DEFAULT 0,
            `ai_model` varchar(100),
            `ai_confidence` decimal(3,2) DEFAULT NULL,
            `source_url` varchar(500),
            `quality_score` decimal(3,2) DEFAULT NULL,
            `meta_data` longtext,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `published_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_author_status` (`author_id`, `status`),
            KEY `idx_community_status_published` (`community_slug`, `status`, `published_at`),
            KEY `idx_status_published` (`status`, `published_at`),
            KEY `idx_votes_total` (`votes_total`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_ai_generated` (`is_ai_generated`),
            KEY `idx_quality_score` (`quality_score`),
            FULLTEXT KEY `idx_content_search` (`title`, `content`, `excerpt`, `tags`)
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'posts table');
    }
    
    /**
     * Create comments table with threading support
     */
    private function create_comments_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['comments'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `post_id` bigint(20) unsigned NOT NULL,
            `parent_id` bigint(20) unsigned DEFAULT 0,
            `author_id` bigint(20) unsigned NOT NULL,
            `author_name` varchar(200),
            `author_email` varchar(200),
            `author_ip` varchar(45),
            `content` longtext NOT NULL,
            `status` enum('approved','pending','spam','trash') NOT NULL DEFAULT 'approved',
            `votes_up` int(11) unsigned DEFAULT 0,
            `votes_down` int(11) unsigned DEFAULT 0,
            `votes_total` int(11) DEFAULT 0,
            `is_ai_generated` tinyint(1) DEFAULT 0,
            `ai_model` varchar(100),
            `ai_confidence` decimal(3,2) DEFAULT NULL,
            `quality_score` decimal(3,2) DEFAULT NULL,
            `depth` tinyint(3) unsigned DEFAULT 0,
            `path` varchar(500),
            `meta_data` longtext,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_post_status` (`post_id`, `status`),
            KEY `idx_parent_id` (`parent_id`),
            KEY `idx_author_id` (`author_id`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_path` (`path`(255)),
            KEY `idx_votes_total` (`votes_total`),
            FULLTEXT KEY `idx_content_search` (`content`),
            CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `{$this->tables['posts']}` (`id`) ON DELETE CASCADE
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'comments table');
    }
    
    /**
     * Create optimized votes table
     */
    private function create_votes_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['votes'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `post_id` bigint(20) unsigned DEFAULT NULL,
            `comment_id` bigint(20) unsigned DEFAULT NULL,
            `vote_type` enum('up','down') NOT NULL,
            `vote_weight` decimal(3,2) DEFAULT 1.00,
            `user_ip` varchar(45),
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_user_post_unique` (`user_id`, `post_id`),
            UNIQUE KEY `idx_user_comment_unique` (`user_id`, `comment_id`),
            KEY `idx_post_votes` (`post_id`, `vote_type`),
            KEY `idx_comment_votes` (`comment_id`, `vote_type`),
            KEY `idx_user_votes` (`user_id`, `created_at`),
            CONSTRAINT `fk_votes_post` FOREIGN KEY (`post_id`) REFERENCES `{$this->tables['posts']}` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_votes_comment` FOREIGN KEY (`comment_id`) REFERENCES `{$this->tables['comments']}` (`id`) ON DELETE CASCADE
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'votes table');
    }
    
    /**
     * Create communities table
     */
    private function create_communities_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['communities'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(200) NOT NULL,
            `slug` varchar(100) NOT NULL,
            `description` text,
            `color` varchar(7) DEFAULT '#6366f1',
            `icon` varchar(100),
            `banner_image` varchar(500),
            `rules` longtext,
            `member_count` int(11) unsigned DEFAULT 0,
            `post_count` int(11) unsigned DEFAULT 0,
            `is_private` tinyint(1) DEFAULT 0,
            `requires_approval` tinyint(1) DEFAULT 0,
            `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
            `featured` tinyint(1) DEFAULT 0,
            `sort_order` int(11) unsigned DEFAULT 0,
            `created_by` bigint(20) unsigned,
            `moderators` longtext,
            `settings` longtext,
            `meta_data` longtext,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_slug` (`slug`),
            UNIQUE KEY `idx_name` (`name`),
            KEY `idx_status_featured` (`status`, `featured`),
            KEY `idx_post_count` (`post_count`),
            KEY `idx_member_count` (`member_count`),
            KEY `idx_created_by` (`created_by`)
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'communities table');
    }
    
    /**
     * Create enhanced user meta table
     */
    private function create_user_meta_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['user_meta'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `karma` int(11) DEFAULT 0,
            `reputation` int(11) unsigned DEFAULT 0,
            `post_count` int(11) unsigned DEFAULT 0,
            `comment_count` int(11) unsigned DEFAULT 0,
            `upvotes_received` int(11) unsigned DEFAULT 0,
            `downvotes_received` int(11) unsigned DEFAULT 0,
            `upvotes_given` int(11) unsigned DEFAULT 0,
            `downvotes_given` int(11) unsigned DEFAULT 0,
            `badges` longtext,
            `achievements` longtext,
            `bio` text,
            `location` varchar(200),
            `website` varchar(500),
            `social_links` longtext,
            `preferences` longtext,
            `notification_settings` longtext,
            `privacy_settings` longtext,
            `last_active` datetime,
            `last_login` datetime,
            `login_count` int(11) unsigned DEFAULT 0,
            `email_verified` tinyint(1) DEFAULT 0,
            `status` enum('active','suspended','banned') DEFAULT 'active',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_user_id` (`user_id`),
            KEY `idx_karma` (`karma`),
            KEY `idx_reputation` (`reputation`),
            KEY `idx_status` (`status`),
            KEY `idx_last_active` (`last_active`)
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'user_meta table');
    }
    
    /**
     * Create analytics table
     */
    private function create_analytics_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['analytics'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `date` date NOT NULL,
            `event_type` varchar(50) NOT NULL,
            `entity_type` varchar(50),
            `entity_id` bigint(20) unsigned,
            `user_id` bigint(20) unsigned,
            `session_id` varchar(128),
            `ip_address` varchar(45),
            `user_agent` text,
            `referrer` varchar(500),
            `data` longtext,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_date_event` (`date`, `event_type`),
            KEY `idx_entity` (`entity_type`, `entity_id`),
            KEY `idx_user_date` (`user_id`, `date`),
            KEY `idx_session` (`session_id`),
            KEY `idx_created_at` (`created_at`)
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'analytics table');
    }
    
    /**
     * Create notifications table
     */
    private function create_notifications_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['notifications'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `type` varchar(50) NOT NULL,
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `data` longtext,
            `is_read` tinyint(1) DEFAULT 0,
            `is_sent` tinyint(1) DEFAULT 0,
            `send_email` tinyint(1) DEFAULT 0,
            `email_sent_at` datetime DEFAULT NULL,
            `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
            `expires_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `read_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user_read` (`user_id`, `is_read`),
            KEY `idx_user_created` (`user_id`, `created_at`),
            KEY `idx_type` (`type`),
            KEY `idx_priority` (`priority`),
            KEY `idx_expires` (`expires_at`)
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'notifications table');
    }
    
    /**
     * Create logs table
     */
    private function create_logs_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['logs'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `level` varchar(20) NOT NULL,
            `message` text NOT NULL,
            `context` longtext,
            `component` varchar(100),
            `user_id` bigint(20) unsigned,
            `ip_address` varchar(45),
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_level_component` (`level`, `component`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_user_id` (`user_id`)
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'logs table');
    }
    
    /**
     * Create sessions table
     */
    private function create_sessions_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['sessions'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` varchar(128) NOT NULL,
            `user_id` bigint(20) unsigned,
            `ip_address` varchar(45),
            `user_agent` text,
            `data` longtext NOT NULL,
            `last_activity` datetime NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_last_activity` (`last_activity`),
            KEY `idx_expires_at` (`expires_at`)
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'sessions table');
    }
    
    /**
     * Create moderation queue table
     */
    private function create_moderation_queue_table(): bool {
        global $wpdb;
        
        $table_name = $this->tables['moderation_queue'];
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `item_type` enum('post','comment','user') NOT NULL,
            `item_id` bigint(20) unsigned NOT NULL,
            `reason` varchar(100) NOT NULL,
            `ai_confidence` decimal(3,2),
            `ai_flags` longtext,
            `status` enum('pending','approved','rejected','escalated') DEFAULT 'pending',
            `priority` enum('low','normal','high','critical') DEFAULT 'normal',
            `assigned_to` bigint(20) unsigned,
            `reviewed_by` bigint(20) unsigned,
            `review_notes` text,
            `auto_action_taken` varchar(100),
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `reviewed_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_status_priority` (`status`, `priority`),
            KEY `idx_item` (`item_type`, `item_id`),
            KEY `idx_assigned_to` (`assigned_to`),
            KEY `idx_created_at` (`created_at`)
        ) {$charset_collate};";
        
        return $this->execute_sql($sql, 'moderation_queue table');
    }
    
    /**
     * Execute SQL with proper error handling
     */
    private function execute_sql(string $sql, string $description = ''): bool {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        global $wpdb;
        
        try {
            dbDelta($sql);
            
            if (!empty($wpdb->last_error)) {
                $this->log_error("SQL error creating {$description}", ['error' => $wpdb->last_error]);
                return false;
            }
            
            $this->log_debug("Successfully created {$description}");
            return true;
            
        } catch (Throwable $e) {
            $this->log_error("Exception creating {$description}", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get table names array
     */
    public function get_table_names(): array {
        return $this->tables;
    }
    
    /**
     * Get posts with advanced filtering and caching
     */
    public function get_posts(array $args = []): array {
        $defaults = [
            'per_page' => 10,
            'page' => 1,
            'community' => '',
            'author_id' => null,
            'status' => 'published',
            'sort' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'tags' => [],
            'is_ai_generated' => null,
            'date_from' => null,
            'date_to' => null,
            'min_votes' => null,
            'max_votes' => null,
            'include_meta' => true,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Generate cache key
        $cache_key = 'posts_' . md5(serialize($args));
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== false) {
                $this->query_stats['cached']++;
                return $cached;
            }
        }
        
        // Build query
        $start_time = microtime(true);
        
        try {
            $result = $this->build_posts_query($args);
            
            // Cache the result
            if ($this->cache) {
                $this->cache->set($cache_key, $result, 300); // 5 minutes cache
            }
            
            $this->query_stats['total']++;
            $this->query_stats['time'] += (microtime(true) - $start_time);
            
            return $result;
            
        } catch (Throwable $e) {
            $this->log_error('Failed to get posts', ['error' => $e->getMessage(), 'args' => $args]);
            return [];
        }
    }
    
    /**
     * Build optimized posts query
     */
    private function build_posts_query(array $args): array {
        global $wpdb;
        
        $posts_table = $this->tables['posts'];
        $communities_table = $this->tables['communities'];
        
        $where_conditions = ['p.status = %s'];
        $where_values = [$args['status']];
        
        // Community filter
        if (!empty($args['community'])) {
            $where_conditions[] = 'p.community_slug = %s';
            $where_values[] = $args['community'];
        }
        
        // Author filter
        if ($args['author_id']) {
            $where_conditions[] = 'p.author_id = %d';
            $where_values[] = $args['author_id'];
        }
        
        // AI generated filter
        if ($args['is_ai_generated'] !== null) {
            $where_conditions[] = 'p.is_ai_generated = %d';
            $where_values[] = (int) $args['is_ai_generated'];
        }
        
        // Date range filter
        if ($args['date_from']) {
            $where_conditions[] = 'p.created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where_conditions[] = 'p.created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        // Vote range filter
        if ($args['min_votes'] !== null) {
            $where_conditions[] = 'p.votes_total >= %d';
            $where_values[] = $args['min_votes'];
        }
        
        if ($args['max_votes'] !== null) {
            $where_conditions[] = 'p.votes_total <= %d';
            $where_values[] = $args['max_votes'];
        }
        
        // Search filter
        if (!empty($args['search'])) {
            $where_conditions[] = 'MATCH(p.title, p.content, p.excerpt) AGAINST(%s IN NATURAL LANGUAGE MODE)';
            $where_values[] = $args['search'];
        }
        
        // Tags filter
        if (!empty($args['tags'])) {
            $tag_conditions = [];
            foreach ((array) $args['tags'] as $tag) {
                $tag_conditions[] = 'p.tags LIKE %s';
                $where_values[] = '%' . $wpdb->esc_like($tag) . '%';
            }
            if (!empty($tag_conditions)) {
                $where_conditions[] = '(' . implode(' OR ', $tag_conditions) . ')';
            }
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Order clause
        $order_clause = $this->build_order_clause($args['sort'], $args['order']);
        
        // Pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_clause = "LIMIT %d OFFSET %d";
        $where_values[] = $args['per_page'];
        $where_values[] = $offset;
        
        // Main query
        $sql = "
            SELECT p.*, 
                   u.display_name as author_name,
                   u.user_login as author_username,
                   c.name as community_name,
                   c.color as community_color,
                   c.icon as community_icon
            FROM {$posts_table} p
            LEFT JOIN {$wpdb->users} u ON p.author_id = u.ID
            LEFT JOIN {$communities_table} c ON p.community_slug = c.slug
            {$where_clause}
            {$order_clause}
            {$limit_clause}
        ";
        
        $prepared_sql = $wpdb->prepare($sql, $where_values);
        $results = $wpdb->get_results($prepared_sql);
        
        if (!$results) {
            return [];
        }
        
        // Process results
        return array_map([$this, 'process_post_result'], $results);
    }
    
    /**
     * Build order clause for posts query
     */
    private function build_order_clause(string $sort, string $order): string {
        $valid_sorts = [
            'created_at' => 'p.created_at',
            'updated_at' => 'p.updated_at',
            'published_at' => 'p.published_at',
            'title' => 'p.title',
            'votes' => 'p.votes_total',
            'comments' => 'p.comment_count',
            'views' => 'p.view_count',
            'quality' => 'p.quality_score',
        ];
        
        $valid_orders = ['ASC', 'DESC'];
        
        $sort_field = $valid_sorts[$sort] ?? 'p.created_at';
        $order_dir = in_array(strtoupper($order), $valid_orders) ? strtoupper($order) : 'DESC';
        
        // Special handling for 'hot' sorting
        if ($sort === 'hot') {
            return "ORDER BY (
                (p.votes_total * 0.6) + 
                (p.comment_count * 0.3) + 
                (p.view_count / 10 * 0.1)
            ) DESC, p.created_at DESC";
        }
        
        return "ORDER BY {$sort_field} {$order_dir}";
    }
    
    /**
     * Process post result
     */
    private function process_post_result($post): array {
        $processed = (array) $post;
        
        // Parse JSON fields
        $processed['meta_data'] = json_decode($post->meta_data ?? '{}', true) ?: [];
        $processed['tags_array'] = !empty($post->tags) ? 
            array_map('trim', explode(',', $post->tags)) : [];
        
        // Add computed fields
        $processed['time_ago'] = human_time_diff(strtotime($post->created_at), current_time('timestamp')) . ' ago';
        $processed['permalink'] = $this->get_post_permalink($post->id);
        $processed['edit_link'] = current_user_can('edit_post', $post->id) ? 
            $this->get_post_edit_link($post->id) : null;
        
        // Add user-specific data if logged in
        if (is_user_logged_in()) {
            $processed['user_vote'] = $this->get_user_vote($post->id, get_current_user_id());
            $processed['user_bookmarked'] = $this->is_post_bookmarked($post->id, get_current_user_id());
        }
        
        return $processed;
    }
    
    /**
     * Create a new post
     */
    public function create_post(array $data): int {
        global $wpdb;
        
        try {
            // Validate required fields
            $required_fields = ['title', 'content', 'author_id'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("Field '{$field}' is required");
                }
            }
            
            // Prepare data
            $insert_data = $this->prepare_post_data($data);
            
            // Insert post
            $result = $wpdb->insert($this->tables['posts'], $insert_data);
            
            if ($result === false) {
                throw new RuntimeException('Failed to insert post: ' . $wpdb->last_error);
            }
            
            $post_id = $wpdb->insert_id;
            
            // Update community post count
            $this->update_community_post_count($insert_data['community_slug']);
            
            // Update user post count
            $this->update_user_post_count($insert_data['author_id']);
            
            // Clear relevant caches
            if ($this->cache) {
                $this->cache->delete_group('posts');
                $this->cache->delete_group('communities');
            }
            
            $this->log_info('Post created successfully', ['post_id' => $post_id]);
            
            return $post_id;
            
        } catch (Throwable $e) {
            $this->log_error('Failed to create post', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }
    
    /**
     * Prepare post data for insertion/update
     */
    private function prepare_post_data(array $data): array {
        $defaults = [
            'community_slug' => 'general',
            'status' => 'published',
            'is_ai_generated' => 0,
            'votes_up' => 0,
            'votes_down' => 0,
            'comment_count' => 0,
            'view_count' => 0,
        ];

        $data = array_merge($defaults, $data);

        // Generate excerpt if not provided
        if (empty($data['excerpt']) && !empty($data['content'])) {
            $data['excerpt'] = wp_trim_words(wp_strip_all_tags($data['content']), 30);
        }

        // Set published date if status is changing to published
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = current_time('mysql');
        }

        // Serialize meta_data if it's an array
        if (isset($data['meta_data']) && is_array($data['meta_data'])) {
            $data['meta_data'] = wp_json_encode($data['meta_data']);
        }

        // Clean and validate data
        $prepared = [
            'title' => sanitize_text_field($data['title']),
            'content' => wp_kses_post($data['content']),
            'excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
            'author_id' => absint($data['author_id']),
            'community_slug' => sanitize_title($data['community_slug']),
            'tags' => sanitize_text_field($data['tags'] ?? ''),
            'status' => $data['status'],
            'featured_image' => esc_url_raw($data['featured_image'] ?? ''),
            'is_ai_generated' => (int) $data['is_ai_generated'],
            'ai_model' => sanitize_text_field($data['ai_model'] ?? ''),
            'ai_confidence' => isset($data['ai_confidence']) ? (float) $data['ai_confidence'] : null,
            'source_url' => esc_url_raw($data['source_url'] ?? ''),
            'quality_score' => isset($data['quality_score']) ? (float) $data['quality_score'] : null,
            'meta_data' => $data['meta_data'] ?? wp_json_encode([]),
            'published_at' => $data['published_at'] ?? null,
        ];
        
        // Only include fields that are being set, not all defaults
        $final_data = [];
        foreach($prepared as $key => $value) {
            if (array_key_exists($key, $data)) {
                $final_data[$key] = $value;
            }
        }
        
        // Add vote counts if they exist in the input data
        if (isset($data['votes_up'])) $final_data['votes_up'] = absint($data['votes_up']);
        if (isset($data['votes_down'])) $final_data['votes_down'] = absint($data['votes_down']);
        if (isset($data['votes_up']) && isset($data['votes_down'])) {
             $final_data['votes_total'] = absint($data['votes_up']) - absint($data['votes_down']);
        }

        return $final_data;
    }

    // --- Placeholder/Helper methods for functions called in process_post_result ---

    private function get_post_permalink(int $post_id): string {
        // This would typically generate a URL like /community/post/{post_id}/{post_slug}
        // For now, it's a placeholder.
        return home_url("/ai-community/post/{$post_id}/");
    }

    private function get_post_edit_link(int $post_id): string {
        // Placeholder for a frontend editing link
        return home_url("/ai-community/post/{$post_id}/edit");
    }

    private function get_user_vote(int $post_id, int $user_id): ?string {
        // Placeholder: This would query the votes table
        // SELECT vote_type FROM {$this->tables['votes']} WHERE post_id = %d AND user_id = %d
        return null;
    }

    private function is_post_bookmarked(int $post_id, int $user_id): bool {
        // Placeholder: This would check user meta or a dedicated bookmarks table
        return false;
    }

    // --- Other essential methods from original file, adapted for new structure ---

    /**
     * Update an existing post
     */
    public function update_post(int $post_id, array $data): bool {
        global $wpdb;

        try {
            $update_data = $this->prepare_post_data($data);
            if (empty($update_data)) {
                return false;
            }

            $result = $wpdb->update($this->tables['posts'], $update_data, ['id' => $post_id]);

            if ($result === false) {
                throw new RuntimeException('Failed to update post: ' . $wpdb->last_error);
            }
            
            // Invalidate caches
            if ($this->cache) {
                $this->cache->delete("post_{$post_id}");
                $this->cache->delete_group('posts');
            }

            $this->log_info('Post updated successfully', ['post_id' => $post_id]);
            return true;

        } catch (Throwable $e) {
            $this->log_error('Failed to update post', ['error' => $e->getMessage(), 'post_id' => $post_id, 'data' => $data]);
            return false;
        }
    }

    /**
     * Delete a post and its related data
     */
    public function delete_post(int $post_id): bool {
        global $wpdb;
        
        $post = $this->get_post($post_id);
        if (!$post) {
            return false;
        }

        try {
            $result = $wpdb->delete($this->tables['posts'], ['id' => $post_id]);
            
            if ($result === false) {
                throw new RuntimeException('Failed to delete post: ' . $wpdb->last_error);
            }
            
            // ON DELETE CASCADE will handle comments and votes
            $this->update_community_post_count($post['community_slug']);
            $this->update_user_post_count($post['author_id']);
            
            // Invalidate caches
            if ($this->cache) {
                $this->cache->delete("post_{$post_id}");
                $this->cache->delete_group('posts');
            }

            $this->log_info('Post deleted successfully', ['post_id' => $post_id]);
            return true;

        } catch (Throwable $e) {
            $this->log_error('Failed to delete post', ['error' => $e->getMessage(), 'post_id' => $post_id]);
            return false;
        }
    }

    /**
     * Get a single post by its ID
     */
    public function get_post(int $post_id): ?array {
        global $wpdb;
        
        $cache_key = "post_{$post_id}";
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $sql = "
            SELECT p.*, u.display_name as author_name, u.user_login as author_username,
                   c.name as community_name, c.color as community_color, c.icon as community_icon
            FROM {$this->tables['posts']} p
            LEFT JOIN {$wpdb->users} u ON p.author_id = u.ID
            LEFT JOIN {$this->tables['communities']} c ON p.community_slug = c.slug
            WHERE p.id = %d
        ";
        
        $result = $wpdb->get_row($wpdb->prepare($sql, $post_id));

        if (!$result) {
            return null;
        }

        $processed_result = $this->process_post_result($result);

        if ($this->cache) {
            $this->cache->set($cache_key, $processed_result, 3600); // 1 hour cache
        }
        
        return $processed_result;
    }
    
    /**
     * Get total number of posts based on filters
     */
    public function get_posts_count(array $args = []): int {
        global $wpdb;
        // Simplified version for brevity, a full implementation would mirror the filtering logic of get_posts
        $status = $args['status'] ?? 'published';
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->tables['posts']} WHERE status = %s", $status));
    }
    
    /**
     * Recalculate and update the post count for a community
     */
    private function update_community_post_count(string $community_slug): void {
        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['posts']} WHERE community_slug = %s AND status = 'published'",
            $community_slug
        ));
        $wpdb->update($this->tables['communities'], ['post_count' => $count], ['slug' => $community_slug]);
    }

    /**
     * Recalculate and update the post count for a user
     */
    private function update_user_post_count(int $user_id): void {
        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['posts']} WHERE author_id = %d AND status = 'published'",
            $user_id
        ));
        
        // This should be an upsert for robustness
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->tables['user_meta']} (user_id, post_count) VALUES (%d, %d)
             ON DUPLICATE KEY UPDATE post_count = %d",
            $user_id, $count, $count
        ));
    }

    /**
     * Get various statistics from the database
     */
    public function get_stats(): array {
        global $wpdb;
        
        $stats = [];
        $stats['total_posts'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['posts']}");
        $stats['published_posts'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->tables['posts']} WHERE status = %s", 'published'));
        $stats['total_comments'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['comments']}");
        $stats['total_votes'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['votes']}");
        $stats['total_communities'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['communities']}");
        
        return $stats;
    }
}