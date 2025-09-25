<?php
/**
 * AI Community Database Class
 * 
 * Handles database operations for the AI Community plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Community_Database {
    
    /**
     * Database version for updates
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Table names
     */
    private $posts_table;
    private $comments_table;
    private $votes_table;
    private $communities_table;
    private $user_meta_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->posts_table = $wpdb->prefix . 'ai_community_posts';
        $this->comments_table = $wpdb->prefix . 'ai_community_comments';
        $this->votes_table = $wpdb->prefix . 'ai_community_votes';
        $this->communities_table = $wpdb->prefix . 'ai_community_communities';
        $this->user_meta_table = $wpdb->prefix . 'ai_community_user_meta';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        $this->create_posts_table();
        $this->create_comments_table();
        $this->create_votes_table();
        $this->create_communities_table();
        $this->create_user_meta_table();
        
        // Update database version
        update_option('ai_community_db_version', self::DB_VERSION);
    }
    
    /**
     * Create posts table
     */
    private function create_posts_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->posts_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            content longtext NOT NULL,
            excerpt text,
            author_id bigint(20) NOT NULL,
            community varchar(100) NOT NULL DEFAULT 'general',
            tags text,
            status varchar(20) NOT NULL DEFAULT 'published',
            featured_image varchar(255),
            votes int(11) DEFAULT 0,
            comment_count int(11) DEFAULT 0,
            view_count int(11) DEFAULT 0,
            is_ai_generated tinyint(1) DEFAULT 0,
            ai_model varchar(100),
            source_url varchar(255),
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY author_id (author_id),
            KEY community (community),
            KEY status (status),
            KEY is_ai_generated (is_ai_generated),
            KEY created_at (created_at),
            KEY votes (votes),
            FULLTEXT KEY content_search (title, content, excerpt)
        ) $charset_collate;";
        
        $this->run_sql($sql);
    }
    
    /**
     * Create comments table
     */
    private function create_comments_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->comments_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            parent_id mediumint(9) DEFAULT 0,
            author_id bigint(20) NOT NULL,
            author_name varchar(100),
            author_email varchar(100),
            author_ip varchar(45),
            content text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'approved',
            votes int(11) DEFAULT 0,
            is_ai_generated tinyint(1) DEFAULT 0,
            ai_model varchar(100),
            meta_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY parent_id (parent_id),
            KEY author_id (author_id),
            KEY status (status),
            KEY is_ai_generated (is_ai_generated),
            KEY created_at (created_at),
            FULLTEXT KEY content_search (content)
        ) $charset_collate;";
        
        $this->run_sql($sql);
    }
    
    /**
     * Create votes table
     */
    private function create_votes_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->votes_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            post_id mediumint(9) DEFAULT NULL,
            comment_id mediumint(9) DEFAULT NULL,
            vote_type enum('up','down') NOT NULL,
            user_ip varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_vote (user_id, post_id, comment_id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY comment_id (comment_id),
            KEY vote_type (vote_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $this->run_sql($sql);
    }
    
    /**
     * Create communities table
     */
    private function create_communities_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->communities_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            color varchar(7) DEFAULT '#6366f1',
            icon varchar(255),
            banner_image varchar(255),
            rules longtext,
            member_count int(11) DEFAULT 0,
            post_count int(11) DEFAULT 0,
            is_private tinyint(1) DEFAULT 0,
            requires_approval tinyint(1) DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_by bigint(20),
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY is_private (is_private),
            KEY created_by (created_by),
            KEY member_count (member_count),
            KEY post_count (post_count)
        ) $charset_collate;";
        
        $this->run_sql($sql);
    }
    
    /**
     * Create user meta table
     */
    private function create_user_meta_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->user_meta_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            karma int(11) DEFAULT 0,
            post_count int(11) DEFAULT 0,
            comment_count int(11) DEFAULT 0,
            upvotes_received int(11) DEFAULT 0,
            downvotes_received int(11) DEFAULT 0,
            upvotes_given int(11) DEFAULT 0,
            downvotes_given int(11) DEFAULT 0,
            badges text,
            bio text,
            location varchar(100),
            website varchar(255),
            social_links longtext,
            preferences longtext,
            last_active datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY karma (karma),
            KEY last_active (last_active)
        ) $charset_collate;";
        
        $this->run_sql($sql);
    }
    
    /**
     * Run SQL query with error handling
     */
    private function run_sql($sql) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        global $wpdb;
        if (!empty($wpdb->last_error)) {
            error_log('AI Community DB Error: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Update tables to version 1.0
     */
    public function update_tables_to_v1() {
        // Add any schema changes for version 1.0
        global $wpdb;
        
        // Example: Add new column if it doesn't exist
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->posts_table}");
        if (!in_array('source_url', $columns)) {
            $wpdb->query("ALTER TABLE {$this->posts_table} ADD COLUMN source_url varchar(255) AFTER ai_model");
        }
    }
    
    /**
     * Get posts with pagination and filters
     */
    public function get_posts($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 10,
            'page' => 1,
            'community' => '',
            'author_id' => '',
            'sort' => 'created_at',
            'order' => 'DESC',
            'status' => 'published',
            'search' => '',
            'tag' => '',
            'is_ai_generated' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("p.status = %s");
        $where_values = array($args['status']);
        
        // Community filter
        if (!empty($args['community'])) {
            $where_conditions[] = "p.community = %s";
            $where_values[] = $args['community'];
        }
        
        // Author filter
        if (!empty($args['author_id'])) {
            $where_conditions[] = "p.author_id = %d";
            $where_values[] = $args['author_id'];
        }
        
        // AI generated filter
        if ($args['is_ai_generated'] !== null) {
            $where_conditions[] = "p.is_ai_generated = %d";
            $where_values[] = (int) $args['is_ai_generated'];
        }
        
        // Search filter
        if (!empty($args['search'])) {
            $where_conditions[] = "MATCH(p.title, p.content, p.excerpt) AGAINST(%s IN NATURAL LANGUAGE MODE)";
            $where_values[] = $args['search'];
        }
        
        // Tag filter
        if (!empty($args['tag'])) {
            $where_conditions[] = "p.tags LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($args['tag']) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Order by clause
        $valid_sort_fields = array('created_at', 'updated_at', 'title', 'votes', 'comment_count', 'view_count');
        $sort_field = in_array($args['sort'], $valid_sort_fields) ? $args['sort'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Special sorting for 'hot' posts
        if ($args['sort'] === 'hot') {
            $order_clause = "ORDER BY (p.votes * 0.7 + p.comment_count * 0.3) DESC, p.created_at DESC";
        } else {
            $order_clause = "ORDER BY p.{$sort_field} {$order}";
        }
        
        // Pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_clause = "LIMIT %d OFFSET %d";
        $where_values[] = $args['per_page'];
        $where_values[] = $offset;
        
        $sql = "
            SELECT p.*, 
                   u.display_name as author_name,
                   c.name as community_name,
                   c.color as community_color
            FROM {$this->posts_table} p
            LEFT JOIN {$wpdb->users} u ON p.author_id = u.ID
            LEFT JOIN {$this->communities_table} c ON p.community = c.slug
            {$where_clause}
            {$order_clause}
            {$limit_clause}
        ";
        
        $prepared_sql = $wpdb->prepare($sql, $where_values);
        $results = $wpdb->get_results($prepared_sql);
        
        // Add formatted data
        foreach ($results as &$post) {
            $post->tags_array = !empty($post->tags) ? explode(',', $post->tags) : array();
            $post->time_ago = human_time_diff(strtotime($post->created_at), current_time('timestamp')) . ' ago';
            $post->meta_data = !empty($post->meta_data) ? json_decode($post->meta_data, true) : array();
        }
        
        return $results;
    }
    
    /**
     * Get total posts count
     */
    public function get_posts_count($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'community' => '',
            'author_id' => '',
            'status' => 'published',
            'search' => '',
            'tag' => '',
            'is_ai_generated' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array("status = %s");
        $where_values = array($args['status']);
        
        if (!empty($args['community'])) {
            $where_conditions[] = "community = %s";
            $where_values[] = $args['community'];
        }
        
        if (!empty($args['author_id'])) {
            $where_conditions[] = "author_id = %d";
            $where_values[] = $args['author_id'];
        }
        
        if ($args['is_ai_generated'] !== null) {
            $where_conditions[] = "is_ai_generated = %d";
            $where_values[] = (int) $args['is_ai_generated'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = "MATCH(title, content, excerpt) AGAINST(%s IN NATURAL LANGUAGE MODE)";
            $where_values[] = $args['search'];
        }
        
        if (!empty($args['tag'])) {
            $where_conditions[] = "tags LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($args['tag']) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "SELECT COUNT(*) FROM {$this->posts_table} {$where_clause}";
        $prepared_sql = $wpdb->prepare($sql, $where_values);
        
        return (int) $wpdb->get_var($prepared_sql);
    }
    
    /**
     * Get single post by ID
     */
    public function get_post($post_id) {
        global $wpdb;
        
        $sql = "
            SELECT p.*, 
                   u.display_name as author_name,
                   c.name as community_name,
                   c.color as community_color
            FROM {$this->posts_table} p
            LEFT JOIN {$wpdb->users} u ON p.author_id = u.ID
            LEFT JOIN {$this->communities_table} c ON p.community = c.slug
            WHERE p.id = %d
        ";
        
        $result = $wpdb->get_row($wpdb->prepare($sql, $post_id));
        
        if ($result) {
            $result->tags_array = !empty($result->tags) ? explode(',', $result->tags) : array();
            $result->time_ago = human_time_diff(strtotime($result->created_at), current_time('timestamp')) . ' ago';
            $result->meta_data = !empty($result->meta_data) ? json_decode($result->meta_data, true) : array();
        }
        
        return $result;
    }
    
    /**
     * Create new post
     */
    public function create_post($data) {
        global $wpdb;
        
        $defaults = array(
            'title' => '',
            'content' => '',
            'excerpt' => '',
            'author_id' => 0,
            'community' => 'general',
            'tags' => '',
            'status' => 'published',
            'is_ai_generated' => 0,
            'ai_model' => '',
            'source_url' => '',
            'meta_data' => array()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Generate excerpt if not provided
        if (empty($data['excerpt']) && !empty($data['content'])) {
            $data['excerpt'] = wp_trim_words(strip_tags($data['content']), 30);
        }
        
        // Prepare data for insertion
        $insert_data = array(
            'title' => sanitize_text_field($data['title']),
            'content' => wp_kses_post($data['content']),
            'excerpt' => sanitize_textarea_field($data['excerpt']),
            'author_id' => (int) $data['author_id'],
            'community' => sanitize_text_field($data['community']),
            'tags' => sanitize_text_field($data['tags']),
            'status' => sanitize_text_field($data['status']),
            'is_ai_generated' => (int) $data['is_ai_generated'],
            'ai_model' => sanitize_text_field($data['ai_model']),
            'source_url' => esc_url_raw($data['source_url']),
            'meta_data' => json_encode($data['meta_data'])
        );
        
        $result = $wpdb->insert($this->posts_table, $insert_data);
        
        if ($result !== false) {
            $post_id = $wpdb->insert_id;
            
            // Update community post count
            $this->update_community_post_count($data['community']);
            
            // Update user post count
            $this->update_user_post_count($data['author_id']);
            
            return $post_id;
        }
        
        return false;
    }
    
    /**
     * Update post
     */
    public function update_post($post_id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        $allowed_fields = array('title', 'content', 'excerpt', 'community', 'tags', 'status', 'meta_data');
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                switch ($field) {
                    case 'title':
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        break;
                    case 'content':
                        $update_data[$field] = wp_kses_post($data[$field]);
                        break;
                    case 'excerpt':
                        $update_data[$field] = sanitize_textarea_field($data[$field]);
                        break;
                    case 'community':
                    case 'tags':
                    case 'status':
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        break;
                    case 'meta_data':
                        $update_data[$field] = json_encode($data[$field]);
                        break;
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->posts_table,
            $update_data,
            array('id' => $post_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete post
     */
    public function delete_post($post_id) {
        global $wpdb;
        
        // Get post data before deletion
        $post = $this->get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // Delete related data
        $wpdb->delete($this->comments_table, array('post_id' => $post_id));
        $wpdb->delete($this->votes_table, array('post_id' => $post_id));
        
        // Delete the post
        $result = $wpdb->delete($this->posts_table, array('id' => $post_id));
        
        if ($result !== false) {
            // Update community post count
            $this->update_community_post_count($post->community);
            
            // Update user post count
            $this->update_user_post_count($post->author_id);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update community post count
     */
    private function update_community_post_count($community_slug) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->posts_table} WHERE community = %s AND status = 'published'",
            $community_slug
        ));
        
        $wpdb->update(
            $this->communities_table,
            array('post_count' => $count),
            array('slug' => $community_slug),
            array('%d'),
            array('%s')
        );
    }
    
    /**
     * Update user post count
     */
    private function update_user_post_count($user_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->posts_table} WHERE author_id = %d AND status = 'published'",
            $user_id
        ));
        
        // Upsert user meta
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->user_meta_table} WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $this->user_meta_table,
                array('post_count' => $count),
                array('user_id' => $user_id),
                array('%d'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $this->user_meta_table,
                array('user_id' => $user_id, 'post_count' => $count),
                array('%d', '%d')
            );
        }
    }
    
    /**
     * Get database statistics
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total posts
        $stats['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->posts_table}");
        
        // Published posts
        $stats['published_posts'] = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->posts_table} WHERE status = %s", 'published')
        );
        
        // AI generated posts
        $stats['ai_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->posts_table} WHERE is_ai_generated = 1");
        
        // Total comments
        $stats['total_comments'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->comments_table}");
        
        // Total votes
        $stats['total_votes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->votes_table}");
        
        // Total communities
        $stats['total_communities'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->communities_table}");
        
        // Active users (posted in last 30 days)
        $stats['active_users'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT author_id) FROM {$this->posts_table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return $stats;
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup_old_posts($days = 365) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get old post IDs
        $old_post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->posts_table} 
             WHERE created_at < %s AND is_ai_generated = 1",
            $cutoff_date
        ));
        
        if (!empty($old_post_ids)) {
            $post_ids_placeholder = implode(',', array_fill(0, count($old_post_ids), '%d'));
            
            // Delete related data
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->comments_table} WHERE post_id IN ({$post_ids_placeholder})",
                $old_post_ids
            ));
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->votes_table} WHERE post_id IN ({$post_ids_placeholder})",
                $old_post_ids
            ));
            
            // Delete posts
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->posts_table} WHERE id IN ({$post_ids_placeholder})",
                $old_post_ids
            ));
            
            return count($old_post_ids);
        }
        
        return 0;
    }
    
    /**
     * Get table names
     */
    public function get_table_names() {
        return array(
            'posts' => $this->posts_table,
            'comments' => $this->comments_table,
            'votes' => $this->votes_table,
            'communities' => $this->communities_table,
            'user_meta' => $this->user_meta_table
        );
    }
}