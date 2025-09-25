<?php
/**
 * AI Community REST API Class
 * 
 * Handles REST API endpoints for frontend communication
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Community_REST_API {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'ai-community/v1';
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Settings instance
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new AI_Community_Database();
        $this->settings = new AI_Community_Settings();
        
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Posts endpoints
        register_rest_route(self::NAMESPACE, '/posts', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_posts'),
            'permission_callback' => '__return_true',
            'args' => $this->get_posts_args()
        ));
        
        register_rest_route(self::NAMESPACE, '/posts', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_post'),
            'permission_callback' => array($this, 'check_user_permission'),
            'args' => $this->get_create_post_args()
        ));
        
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_post'),
            'permission_callback' => array($this, 'check_post_edit_permission'),
            'args' => $this->get_update_post_args()
        ));
        
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_post'),
            'permission_callback' => array($this, 'check_post_delete_permission')
        ));
        
        // Voting endpoints
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)/vote', array(
            'methods' => 'POST',
            'callback' => array($this, 'vote_post'),
            'permission_callback' => array($this, 'check_user_permission'),
            'args' => array(
                'vote_type' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return in_array($param, array('up', 'down'));
                    }
                )
            )
        ));
        
        // Comments endpoints
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)/comments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_comments'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route(self::NAMESPACE, '/comments', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_comment'),
            'permission_callback' => array($this, 'check_user_permission'),
            'args' => $this->get_create_comment_args()
        ));
        
        register_rest_route(self::NAMESPACE, '/comments/(?P<id>\d+)/vote', array(
            'methods' => 'POST',
            'callback' => array($this, 'vote_comment'),
            'permission_callback' => array($this, 'check_user_permission'),
            'args' => array(
                'vote_type' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return in_array($param, array('up', 'down'));
                    }
                )
            )
        ));
        
        // Communities endpoints
        register_rest_route(self::NAMESPACE, '/communities', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_communities'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route(self::NAMESPACE, '/communities/(?P<slug>[a-zA-Z0-9-_]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_community'),
            'permission_callback' => '__return_true'
        ));
        
        // User endpoints
        register_rest_route(self::NAMESPACE, '/users/me', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_current_user'),
            'permission_callback' => array($this, 'check_user_permission')
        ));
        
        register_rest_route(self::NAMESPACE, '/users/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user'),
            'permission_callback' => '__return_true'
        ));
        
        // Search endpoint
        register_rest_route(self::NAMESPACE, '/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'type' => array(
                    'default' => 'posts',
                    'validate_callback' => function($param) {
                        return in_array($param, array('posts', 'comments', 'users', 'all'));
                    }
                )
            )
        ));
        
        // AI Generation endpoints (admin only)
        register_rest_route(self::NAMESPACE, '/ai/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_ai_content'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        register_rest_route(self::NAMESPACE, '/ai/moderate', array(
            'methods' => 'POST',
            'callback' => array($this, 'moderate_content'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'content' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_kses_post'
                )
            )
        ));
        
        // Stats endpoints (admin only)
        register_rest_route(self::NAMESPACE, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
    }
    
    /**
     * Get posts
     */
    public function get_posts($request) {
        $params = array(
            'page' => $request->get_param('page') ?: 1,
            'per_page' => min($request->get_param('per_page') ?: 10, 50),
            'community' => $request->get_param('community'),
            'author_id' => $request->get_param('author_id'),
            'sort' => $request->get_param('sort') ?: 'created_at',
            'order' => $request->get_param('order') ?: 'DESC',
            'search' => $request->get_param('search'),
            'tag' => $request->get_param('tag'),
            'is_ai_generated' => $request->get_param('ai_generated')
        );
        
        $posts = $this->database->get_posts($params);
        $total = $this->database->get_posts_count($params);
        
        // Add user vote information if logged in
        if (is_user_logged_in()) {
            $posts = $this->add_user_votes($posts, get_current_user_id());
        }
        
        $response = rest_ensure_response($posts);
        $response->header('X-Total-Count', $total);
        $response->header('X-Total-Pages', ceil($total / $params['per_page']));
        
        return $response;
    }
    
    /**
     * Get single post
     */
    public function get_post($request) {
        $post_id = $request->get_param('id');
        $post = $this->database->get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'ai-community'), array('status' => 404));
        }
        
        // Increment view count
        $this->increment_post_views($post_id);
        
        // Add user vote if logged in
        if (is_user_logged_in()) {
            $post->user_vote = $this->get_user_vote($post_id, get_current_user_id());
        }
        
        return rest_ensure_response($post);
    }
    
    /**
     * Create post
     */
    public function create_post($request) {
        if (!$this->can_user_post()) {
            return new WP_Error('insufficient_karma', __('Insufficient karma to post', 'ai-community'), array('status' => 403));
        }
        
        $data = array(
            'title' => $request->get_param('title'),
            'content' => $request->get_param('content'),
            'community' => $request->get_param('community') ?: 'general',
            'tags' => $request->get_param('tags'),
            'author_id' => get_current_user_id(),
            'status' => $this->settings->get('require_moderation') ? 'pending' : 'published'
        );
        
        // Moderate content if enabled
        if ($this->settings->get('ai_moderation_enabled')) {
            $moderation = $this->moderate_post_content($data['content']);
            if (!$moderation['appropriate']) {
                return new WP_Error('content_rejected', $moderation['reason'], array('status' => 400));
            }
        }
        
        $post_id = $this->database->create_post($data);
        
        if (!$post_id) {
            return new WP_Error('create_failed', __('Failed to create post', 'ai-community'), array('status' => 500));
        }
        
        // Update user karma
        $this->update_user_karma(get_current_user_id(), 'post_created');
        
        // Send notifications
        do_action('ai_community_post_created', $post_id, $data);
        
        return rest_ensure_response(array(
            'id' => $post_id,
            'message' => __('Post created successfully', 'ai-community')
        ));
    }
    
    /**
     * Update post
     */
    public function update_post($request) {
        $post_id = $request->get_param('id');
        $post = $this->database->get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'ai-community'), array('status' => 404));
        }
        
        $data = array();
        if ($request->has_param('title')) {
            $data['title'] = $request->get_param('title');
        }
        if ($request->has_param('content')) {
            $data['content'] = $request->get_param('content');
        }
        if ($request->has_param('tags')) {
            $data['tags'] = $request->get_param('tags');
        }
        
        $result = $this->database->update_post($post_id, $data);
        
        if (!$result) {
            return new WP_Error('update_failed', __('Failed to update post', 'ai-community'), array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'message' => __('Post updated successfully', 'ai-community')
        ));
    }
    
    /**
     * Delete post
     */
    public function delete_post($request) {
        $post_id = $request->get_param('id');
        $result = $this->database->delete_post($post_id);
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete post', 'ai-community'), array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'message' => __('Post deleted successfully', 'ai-community')
        ));
    }
    
    /**
     * Vote on post
     */
    public function vote_post($request) {
        $post_id = $request->get_param('id');
        $vote_type = $request->get_param('vote_type');
        $user_id = get_current_user_id();
        
        if (!$this->can_user_vote()) {
            return new WP_Error('insufficient_karma', __('Insufficient karma to vote', 'ai-community'), array('status' => 403));
        }
        
        $result = $this->process_vote($post_id, null, $user_id, $vote_type);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'message' => __('Vote recorded', 'ai-community'),
            'new_score' => $result['new_score']
        ));
    }
    
    /**
     * Get comments for post
     */
    public function get_comments($request) {
        $post_id = $request->get_param('id');
        $comments = $this->get_post_comments($post_id);
        
        return rest_ensure_response($comments);
    }
    
    /**
     * Create comment
     */
    public function create_comment($request) {
        $post_id = $request->get_param('post_id');
        $content = $request->get_param('content');
        $parent_id = $request->get_param('parent_id') ?: 0;
        
        if (!$this->database->get_post($post_id)) {
            return new WP_Error('post_not_found', __('Post not found', 'ai-community'), array('status' => 404));
        }
        
        $data = array(
            'post_id' => $post_id,
            'parent_id' => $parent_id,
            'content' => $content,
            'author_id' => get_current_user_id()
        );
        
        $comment_id = $this->create_comment_record($data);
        
        if (!$comment_id) {
            return new WP_Error('create_failed', __('Failed to create comment', 'ai-community'), array('status' => 500));
        }
        
        // Update user karma
        $this->update_user_karma(get_current_user_id(), 'comment_created');
        
        return rest_ensure_response(array(
            'id' => $comment_id,
            'message' => __('Comment created successfully', 'ai-community')
        ));
    }
    
    /**
     * Vote on comment
     */
    public function vote_comment($request) {
        $comment_id = $request->get_param('id');
        $vote_type = $request->get_param('vote_type');
        $user_id = get_current_user_id();
        
        $result = $this->process_vote(null, $comment_id, $user_id, $vote_type);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'message' => __('Vote recorded', 'ai-community'),
            'new_score' => $result['new_score']
        ));
    }
    
    /**
     * Get communities
     */
    public function get_communities($request) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        $communities = $wpdb->get_results(
            "SELECT * FROM {$tables['communities']} 
             WHERE status = 'active' 
             ORDER BY member_count DESC, name ASC"
        );
        
        return rest_ensure_response($communities);
    }
    
    /**
     * Get single community
     */
    public function get_community($request) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        $slug = $request->get_param('slug');
        
        $community = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['communities']} WHERE slug = %s",
            $slug
        ));
        
        if (!$community) {
            return new WP_Error('community_not_found', __('Community not found', 'ai-community'), array('status' => 404));
        }
        
        return rest_ensure_response($community);
    }
    
    /**
     * Get current user
     */
    public function get_current_user($request) {
        $user = wp_get_current_user();
        $user_data = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'avatar_url' => get_avatar_url($user->ID),
            'karma' => $this->get_user_karma($user->ID),
            'post_count' => $this->get_user_post_count($user->ID),
            'comment_count' => $this->get_user_comment_count($user->ID)
        );
        
        return rest_ensure_response($user_data);
    }
    
    /**
     * Get user by ID
     */
    public function get_user($request) {
        $user_id = $request->get_param('id');
        $user = get_userdata($user_id);
        
        if (!$user) {
            return new WP_Error('user_not_found', __('User not found', 'ai-community'), array('status' => 404));
        }
        
        $user_data = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'avatar_url' => get_avatar_url($user->ID),
            'karma' => $this->get_user_karma($user->ID),
            'post_count' => $this->get_user_post_count($user->ID),
            'comment_count' => $this->get_user_comment_count($user->ID)
        );
        
        return rest_ensure_response($user_data);
    }
    
    /**
     * Search
     */
    public function search($request) {
        $query = $request->get_param('q');
        $type = $request->get_param('type');
        
        $results = array();
        
        if (in_array($type, array('posts', 'all'))) {
            $posts = $this->database->get_posts(array(
                'search' => $query,
                'per_page' => 10
            ));
            $results['posts'] = $posts;
        }
        
        if (in_array($type, array('users', 'all'))) {
            $users = $this->search_users($query);
            $results['users'] = $users;
        }
        
        return rest_ensure_response($results);
    }
    
    /**
     * Generate AI content (admin only)
     */
    public function generate_ai_content($request) {
        $ai_generator = new AI_Community_AI_Generator();
        $result = $ai_generator->generate_content_manually();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'message' => __('AI content generated successfully', 'ai-community'),
            'posts_created' => $result['posts_created'],
            'replies_created' => $result['replies_created']
        ));
    }
    
    /**
     * Moderate content
     */
    public function moderate_content($request) {
        $content = $request->get_param('content');
        $openrouter = new AI_Community_OpenRouter_API();
        $result = $openrouter->moderate_content($content);
        
        return rest_ensure_response($result);
    }
    
    /**
     * Get stats (admin only)
     */
    public function get_stats($request) {
        $stats = $this->database->get_stats();
        
        // Add AI-specific stats
        $openrouter = new AI_Community_OpenRouter_API();
        $ai_stats = array(
            'api_health' => $openrouter->get_api_health(),
            'usage_stats' => $openrouter->get_usage_stats(30),
            'recent_errors' => $openrouter->get_recent_errors(5)
        );
        
        return rest_ensure_response(array(
            'database' => $stats,
            'ai' => $ai_stats
        ));
    }
    
    // Permission callbacks
    
    public function check_user_permission() {
        return is_user_logged_in();
    }
    
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    public function check_post_edit_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $post_id = $request->get_param('id');
        $post = $this->database->get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        return get_current_user_id() == $post->author_id || current_user_can('edit_others_posts');
    }
    
    public function check_post_delete_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $post_id = $request->get_param('id');
        $post = $this->database->get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        return get_current_user_id() == $post->author_id || current_user_can('delete_others_posts');
    }
    
    // Helper methods
    
    private function get_posts_args() {
        return array(
            'page' => array(
                'default' => 1,
                'sanitize_callback' => 'absint'
            ),
            'per_page' => array(
                'default' => 10,
                'sanitize_callback' => 'absint'
            ),
            'community' => array(
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'sort' => array(
                'default' => 'created_at',
                'validate_callback' => function($param) {
                    return in_array($param, array('created_at', 'votes', 'hot', 'top'));
                }
            ),
            'search' => array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
    }
    
    private function get_create_post_args() {
        return array(
            'title' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param) {
                    return strlen(trim($param)) >= 5;
                }
            ),
            'content' => array(
                'required' => true,
                'sanitize_callback' => 'wp_kses_post',
                'validate_callback' => function($param) {
                    return strlen(trim($param)) >= 10;
                }
            ),
            'community' => array(
                'default' => 'general',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'tags' => array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
    }
    
    private function get_update_post_args() {
        return array(
            'title' => array(
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'content' => array(
                'sanitize_callback' => 'wp_kses_post'
            ),
            'tags' => array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
    }
    
    private function get_create_comment_args() {
        return array(
            'post_id' => array(
                'required' => true,
                'sanitize_callback' => 'absint'
            ),
            'content' => array(
                'required' => true,
                'sanitize_callback' => 'wp_kses_post',
                'validate_callback' => function($param) {
                    return strlen(trim($param)) >= 3;
                }
            ),
            'parent_id' => array(
                'default' => 0,
                'sanitize_callback' => 'absint'
            )
        );
    }
    
    private function add_user_votes($posts, $user_id) {
        if (empty($posts)) {
            return $posts;
        }
        
        global $wpdb;
        $tables = $this->database->get_table_names();
        $post_ids = array_column($posts, 'id');
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        
        $votes = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, vote_type FROM {$tables['votes']} 
             WHERE user_id = %d AND post_id IN ({$placeholders})",
            array_merge(array($user_id), $post_ids)
        ), OBJECT_K);
        
        foreach ($posts as &$post) {
            $post->user_vote = isset($votes[$post->id]) ? $votes[$post->id]->vote_type : null;
        }
        
        return $posts;
    }
    
    private function get_user_vote($post_id, $user_id, $comment_id = null) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        $where_post = $comment_id ? 'comment_id = %d' : 'post_id = %d';
        $id_value = $comment_id ?: $post_id;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT vote_type FROM {$tables['votes']} 
             WHERE user_id = %d AND {$where_post}",
            $user_id, $id_value
        ));
    }
    
    private function increment_post_views($post_id) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['posts']} SET view_count = view_count + 1 WHERE id = %d",
            $post_id
        ));
    }
    
    private function can_user_post() {
        if (!$this->settings->get('karma_system_enabled')) {
            return true;
        }
        
        $min_karma = $this->settings->get('min_karma_to_post', 0);
        $user_karma = $this->get_user_karma(get_current_user_id());
        
        return $user_karma >= $min_karma;
    }
    
    private function can_user_vote() {
        if (!$this->settings->get('karma_system_enabled')) {
            return true;
        }
        
        $min_karma = $this->settings->get('min_karma_to_vote', 0);
        $user_karma = $this->get_user_karma(get_current_user_id());
        
        return $user_karma >= $min_karma;
    }
    
    private function process_vote($post_id, $comment_id, $user_id, $vote_type) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        $existing_vote = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['votes']} 
             WHERE user_id = %d AND " . ($post_id ? 'post_id = %d' : 'comment_id = %d'),
            $user_id, $post_id ?: $comment_id
        ));
        
        $vote_change = 0;
        
        if ($existing_vote) {
            if ($existing_vote->vote_type === $vote_type) {
                // Remove vote
                $wpdb->delete($tables['votes'], array('id' => $existing_vote->id));
                $vote_change = ($vote_type === 'up') ? -1 : 1;
            } else {
                // Change vote
                $wpdb->update(
                    $tables['votes'],
                    array('vote_type' => $vote_type),
                    array('id' => $existing_vote->id)
                );
                $vote_change = ($vote_type === 'up') ? 2 : -2;
            }
        } else {
            // New vote
            $vote_data = array(
                'user_id' => $user_id,
                'vote_type' => $vote_type
            );
            
            if ($post_id) {
                $vote_data['post_id'] = $post_id;
            } else {
                $vote_data['comment_id'] = $comment_id;
            }
            
            $wpdb->insert($tables['votes'], $vote_data);
            $vote_change = ($vote_type === 'up') ? 1 : -1;
        }
        
        // Update post/comment votes
        $target_table = $post_id ? $tables['posts'] : $tables['comments'];
        $target_id = $post_id ?: $comment_id;
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$target_table} SET votes = votes + %d WHERE id = %d",
            $vote_change, $target_id
        ));
        
        // Update karma for post/comment author
        if ($post_id) {
            $post = $this->database->get_post($post_id);
            if ($post) {
                $karma_change = ($vote_type === 'up') ? 
                    $this->settings->get('karma_for_upvote', 1) : 
                    $this->settings->get('karma_for_downvote', -1);
                $this->update_user_karma($post->author_id, 'vote_received', $karma_change);
            }
        }
        
        $new_score = $wpdb->get_var($wpdb->prepare(
            "SELECT votes FROM {$target_table} WHERE id = %d",
            $target_id
        ));
        
        return array('new_score' => (int) $new_score);
    }
    
    private function get_post_comments($post_id) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as author_name, u.user_login as author_username
             FROM {$tables['comments']} c
             LEFT JOIN {$wpdb->users} u ON c.author_id = u.ID
             WHERE c.post_id = %d AND c.status = 'approved'
             ORDER BY c.created_at ASC",
            $post_id
        ));
        
        // Add user votes if logged in
        if (is_user_logged_in() && !empty($comments)) {
            $comment_ids = array_column($comments, 'id');
            $placeholders = implode(',', array_fill(0, count($comment_ids), '%d'));
            
            $votes = $wpdb->get_results($wpdb->prepare(
                "SELECT comment_id, vote_type FROM {$tables['votes']} 
                 WHERE user_id = %d AND comment_id IN ({$placeholders})",
                array_merge(array(get_current_user_id()), $comment_ids)
            ), OBJECT_K);
            
            foreach ($comments as &$comment) {
                $comment->user_vote = isset($votes[$comment->id]) ? $votes[$comment->id]->vote_type : null;
                $comment->time_ago = human_time_diff(strtotime($comment->created_at), current_time('timestamp')) . ' ago';
            }
        }
        
        return $this->build_comment_tree($comments);
    }
    
    private function build_comment_tree($comments, $parent_id = 0) {
        $tree = array();
        
        foreach ($comments as $comment) {
            if ($comment->parent_id == $parent_id) {
                $comment->replies = $this->build_comment_tree($comments, $comment->id);
                $tree[] = $comment;
            }
        }
        
        return $tree;
    }
    
    private function create_comment_record($data) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        $result = $wpdb->insert($tables['comments'], array(
            'post_id' => $data['post_id'],
            'parent_id' => $data['parent_id'],
            'content' => $data['content'],
            'author_id' => $data['author_id'],
            'status' => $this->settings->get('require_moderation') ? 'pending' : 'approved',
            'votes' => 1,
            'author_name' => wp_get_current_user()->display_name,
            'author_email' => wp_get_current_user()->user_email,
            'author_ip' => $this->get_client_ip()
        ));
        
        if ($result) {
            // Update post comment count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tables['posts']} 
                 SET comment_count = (SELECT COUNT(*) FROM {$tables['comments']} WHERE post_id = %d AND status = 'approved')
                 WHERE id = %d",
                $data['post_id'], $data['post_id']
            ));
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    private function moderate_post_content($content) {
        $openrouter = new AI_Community_OpenRouter_API();
        return $openrouter->moderate_content($content);
    }
    
    private function update_user_karma($user_id, $action, $custom_amount = null) {
        if (!$this->settings->get('karma_system_enabled')) {
            return;
        }
        
        $karma_changes = array(
            'post_created' => $this->settings->get('karma_for_post', 2),
            'comment_created' => $this->settings->get('karma_for_comment', 1),
            'vote_received' => $custom_amount ?? 0
        );
        
        $karma_change = $karma_changes[$action] ?? 0;
        
        if ($karma_change == 0) {
            return;
        }
        
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        // Upsert user meta
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT karma FROM {$tables['user_meta']} WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing !== null) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tables['user_meta']} 
                 SET karma = karma + %d, last_active = NOW() 
                 WHERE user_id = %d",
                $karma_change, $user_id
            ));
        } else {
            $wpdb->insert($tables['user_meta'], array(
                'user_id' => $user_id,
                'karma' => $karma_change,
                'last_active' => current_time('mysql')
            ));
        }
    }
    
    private function get_user_karma($user_id) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT karma FROM {$tables['user_meta']} WHERE user_id = %d",
            $user_id
        ));
    }
    
    private function get_user_post_count($user_id) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['posts']} 
             WHERE author_id = %d AND status = 'published'",
            $user_id
        ));
    }
    
    private function get_user_comment_count($user_id) {
        global $wpdb;
        $tables = $this->database->get_table_names();
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['comments']} 
             WHERE author_id = %d AND status = 'approved'",
            $user_id
        ));
    }
    
    private function search_users($query) {
        global $wpdb;
        
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT ID as id, user_login as username, display_name 
             FROM {$wpdb->users} 
             WHERE display_name LIKE %s OR user_login LIKE %s 
             ORDER BY display_name ASC 
             LIMIT 10",
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%'
        ));
        
        foreach ($users as &$user) {
            $user->avatar_url = get_avatar_url($user->id);
            $user->karma = $this->get_user_karma($user->id);
        }
        
        return $users;
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Handle CORS for API requests
     */
    public function handle_cors() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Add custom headers to API responses
     */
    public function add_api_headers($response, $handler, $request) {
        if (strpos($request->get_route(), '/ai-community/v1/') !== false) {
            $response->header('X-API-Version', '1.0');
            $response->header('X-Plugin-Version', AI_COMMUNITY_VERSION);
            
            // Add rate limiting headers
            if (is_user_logged_in()) {
                $rate_info = $this->get_user_rate_limit_info(get_current_user_id());
                $response->header('X-RateLimit-Limit', $rate_info['limit']);
                $response->header('X-RateLimit-Remaining', $rate_info['remaining']);
                $response->header('X-RateLimit-Reset', $rate_info['reset']);
            }
        }
        
        return $response;
    }
    
    private function get_user_rate_limit_info($user_id) {
        $limit = $this->settings->get('max_posts_per_hour', 10);
        $current_hour = date('Y-m-d H:00:00');
        $key = "ai_community_user_rate_{$user_id}_{$current_hour}";
        $current_count = (int) get_transient($key);
        
        return array(
            'limit' => $limit,
            'remaining' => max(0, $limit - $current_count),
            'reset' => strtotime(date('Y-m-d H:59:59'))
        );
    }
    
    /**
     * Log API requests for analytics
     */
    public function log_api_request($response, $handler, $request) {
        if (!$this->settings->get('log_api_requests')) {
            return $response;
        }
        
        $route = $request->get_route();
        if (strpos($route, '/ai-community/v1/') === false) {
            return $response;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'route' => $route,
            'method' => $request->get_method(),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'response_code' => $response->get_status()
        );
        
        // Store in database or log file
        error_log('AI Community API: ' . json_encode($log_data));
        
        return $response;
    }
    
    /**
     * Validate API request data
     */
    public function validate_request_data($request) {
        // Check for required fields based on endpoint
        $route = $request->get_route();
        $method = $request->get_method();
        
        // Rate limiting
        if (is_user_logged_in() && in_array($method, array('POST', 'PUT', 'DELETE'))) {
            $rate_check = $this->check_user_rate_limit(get_current_user_id(), $route);
            if (is_wp_error($rate_check)) {
                return $rate_check;
            }
        }
        
        // Content length limits
        if ($method === 'POST' && strpos($route, '/posts') !== false) {
            $content = $request->get_param('content');
            $max_length = $this->settings->get('max_post_length', 10000);
            
            if (strlen($content) > $max_length) {
                return new WP_Error('content_too_long', 
                    sprintf(__('Content exceeds maximum length of %d characters', 'ai-community'), $max_length),
                    array('status' => 400)
                );
            }
        }
        
        return true;
    }
    
    private function check_user_rate_limit($user_id, $route) {
        $limits = array(
            'posts' => $this->settings->get('max_posts_per_hour', 10),
            'comments' => $this->settings->get('max_comments_per_hour', 50)
        );
        
        $limit_key = 'posts';
        if (strpos($route, '/comments') !== false) {
            $limit_key = 'comments';
        }
        
        $limit = $limits[$limit_key];
        $current_hour = date('Y-m-d H:00:00');
        $transient_key = "ai_community_user_rate_{$user_id}_{$limit_key}_{$current_hour}";
        
        $current_count = (int) get_transient($transient_key);
        
        if ($current_count >= $limit) {
            return new WP_Error('rate_limit_exceeded', 
                sprintf(__('Rate limit exceeded. Maximum %d %s per hour.', 'ai-community'), $limit, $limit_key),
                array('status' => 429)
            );
        }
        
        // Increment counter
        set_transient($transient_key, $current_count + 1, HOUR_IN_SECONDS);
        
        return true;
    }
}

// Initialize REST API hooks
add_action('rest_api_init', function() {
    $api = new AI_Community_REST_API();
    add_filter('rest_pre_serve_request', array($api, 'handle_cors'), 0, 4);
    add_filter('rest_post_dispatch', array($api, 'add_api_headers'), 10, 3);
    add_filter('rest_post_dispatch', array($api, 'log_api_request'), 20, 3);
});