<?php
/**
 * AI Community Admin Class
 * 
 * Handles WordPress admin interface and functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Community_Admin {
    
    /**
     * Settings instance
     */
    private $settings;
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * AI Generator instance
     */
    private $ai_generator;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize properties as null - use getter methods to instantiate when needed
        $this->settings = null;
        $this->database = null;
        $this->ai_generator = null;
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // AJAX handlers
        add_action('wp_ajax_ai_community_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_ai_community_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_ai_community_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_ai_community_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_ai_community_export_data', array($this, 'ajax_export_data'));
        // Add this line in the constructor
        add_filter('admin_body_class', array($this, 'admin_body_class'));
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

    private function get_ai_generator() {
        if (!$this->ai_generator) {
            $this->ai_generator = new AI_Community_AI_Generator();
        }
        return $this->ai_generator;
    }
    
    /**
     * Initialize admin
     */
    public function admin_init() {
        // Register settings
        register_setting('ai_community_settings', 'ai_community_settings');
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . AI_COMMUNITY_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        
        // Handle form submissions
        $this->handle_form_submissions();
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="11" width="18" height="10" rx="2" ry="2" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="5" r="2" stroke="currentColor" stroke-width="2"/><path d="M12 7v4" stroke="currentColor" stroke-width="2"/></svg>');
        
        // Main menu page
        add_menu_page(
            __('AI Community', 'ai-community'),
            __('AI Community', 'ai-community'),
            'manage_options',
            'ai-community',
            array($this, 'dashboard_page'),
            $icon_svg,
            30
        );
        
        // Dashboard submenu (default)
        add_submenu_page(
            'ai-community',
            __('Dashboard', 'ai-community'),
            __('Dashboard', 'ai-community'),
            'manage_options',
            'ai-community',
            array($this, 'dashboard_page')
        );
        
        // Posts management
        add_submenu_page(
            'ai-community',
            __('Posts', 'ai-community'),
            __('Posts', 'ai-community'),
            'manage_options',
            'ai-community-posts',
            array($this, 'posts_page')
        );
        
        // Communities management
        add_submenu_page(
            'ai-community',
            __('Communities', 'ai-community'),
            __('Communities', 'ai-community'),
            'manage_options',
            'ai-community-communities',
            array($this, 'communities_page')
        );
        
        // Settings
        add_submenu_page(
            'ai-community',
            __('Settings', 'ai-community'),
            __('Settings', 'ai-community'),
            'manage_options',
            'ai-community-settings',
            array($this, 'settings_page')
        );
        
        // Analytics
        add_submenu_page(
            'ai-community',
            __('Analytics', 'ai-community'),
            __('Analytics', 'ai-community'),
            'manage_options',
            'ai-community-analytics',
            array($this, 'analytics_page')
        );
        
        // Tools
        add_submenu_page(
            'ai-community',
            __('Tools', 'ai-community'),
            __('Tools', 'ai-community'),
            'manage_options',
            'ai-community-tools',
            array($this, 'tools_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'ai-community') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'ai-community-admin',
            AI_COMMUNITY_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-color-picker'),
            AI_COMMUNITY_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'ai-community-admin',
            AI_COMMUNITY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker', 'chart.js'),
            AI_COMMUNITY_VERSION,
            true
        );
        
        // Chart.js for analytics
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            array(),
            '3.9.1',
            true
        );
        
        // Localize script
        wp_localize_script('ai-community-admin', 'aiCommunityAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_community_admin_nonce'),
            'restUrl' => rest_url('ai-community/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'strings' => array(
                'confirm_delete' => __('Are you sure?', 'ai-community'),
                'generating' => __('Generating content...', 'ai-community'),
                'testing' => __('Testing API connection...', 'ai-community'),
                'saving' => __('Saving settings...', 'ai-community'),
                'success' => __('Success!', 'ai-community'),
                'error' => __('An error occurred', 'ai-community')
            )
        ));
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $stats = $this->get_database()->get_stats();
        $ai_stats = $this->get_ai_generator()->get_generation_stats(30);
        $openrouter = new AI_Community_OpenRouter_API();
        $api_health = $openrouter->get_api_health();
        $system_check = $this->get_ai_generator()->check_system_requirements();
        
        include AI_COMMUNITY_PLUGIN_DIR . 'admin/pages/dashboard.php';
    }
    
    /**
     * Posts management page
     */
    public function posts_page() {
        $current_tab = $_GET['tab'] ?? 'all';
        $posts_per_page = 20;
        $current_page = max(1, $_GET['paged'] ?? 1);
        
        $filters = array(
            'per_page' => $posts_per_page,
            'page' => $current_page
        );
        
        switch ($current_tab) {
            case 'ai':
                $filters['is_ai_generated'] = 1;
                break;
            case 'pending':
                $filters['status'] = 'pending';
                break;
        }
        
        $posts = $this->get_database()->get_posts($filters);
        $total_posts = $this->get_database()->get_posts_count($filters);
        
        include AI_COMMUNITY_PLUGIN_DIR . 'admin/pages/posts.php';
    }
    
    /**
     * Communities management page
     */
    public function communities_page() {
        global $wpdb;
        $tables = $this->get_database()->get_table_names();
        
        $communities = $wpdb->get_results(
            "SELECT c.*, 
                    (SELECT COUNT(*) FROM {$tables['posts']} WHERE community = c.slug) as post_count
             FROM {$tables['communities']} c 
             ORDER BY c.name ASC"
        );
        
        include AI_COMMUNITY_PLUGIN_DIR . 'admin/pages/communities.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $current_tab = $_GET['tab'] ?? 'general';
        $settings = $this->get_settings()->get_settings_by_category();
        
        include AI_COMMUNITY_PLUGIN_DIR . 'admin/pages/settings.php';
    }
    
    /**
     * Analytics page
     */
    public function analytics_page() {
        $stats = $this->get_database()->get_stats();
        $generation_stats = $this->get_ai_generator()->get_generation_stats(30);
        $quality_report = $this->get_ai_generator()->get_quality_report(7);
        
        $openrouter = new AI_Community_OpenRouter_API();
        $usage_stats = $openrouter->get_usage_stats(30);
        $cost_estimate = $openrouter->estimate_monthly_cost();
        
        include AI_COMMUNITY_PLUGIN_DIR . 'admin/pages/analytics.php';
    }
    
    /**
     * Tools page
     */
    public function tools_page() {
        $current_tool = $_GET['tool'] ?? 'export';
        $logs = $this->get_ai_generator()->get_generation_logs(50);
        $openrouter = new AI_Community_OpenRouter_API();
        $api_errors = $openrouter->get_recent_errors(20);
        
        include AI_COMMUNITY_PLUGIN_DIR . 'admin/pages/tools.php';
    }
    
    /**
     * Handle form submissions
     */
    private function handle_form_submissions() {
        if (!isset($_POST['ai_community_action'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_community_action')) {
            wp_die(__('Security check failed', 'ai-community'));
        }
        
        $action = $_POST['ai_community_action'];
        
        switch ($action) {
            case 'save_settings':
                $this->handle_save_settings();
                break;
            case 'add_community':
                $this->handle_add_community();
                break;
            case 'delete_community':
                $this->handle_delete_community();
                break;
            case 'bulk_action_posts':
                $this->handle_bulk_posts_action();
                break;
        }
    }
    
    /**
     * Handle settings save
     */
    private function handle_save_settings() {
        $new_settings = $_POST['ai_community_settings'] ?? array();
        
        // Sanitize and validate settings
        $result = $this->get_settings()->update_all($new_settings);
        
        if ($result) {
            // Reschedule AI generation if schedule changed
            $this->get_ai_generator()->schedule_next_run();
            
            add_settings_error(
                'ai_community_settings',
                'settings_saved',
                __('Settings saved successfully!', 'ai-community'),
                'success'
            );
        } else {
            add_settings_error(
                'ai_community_settings',
                'settings_error',
                __('Failed to save settings.', 'ai-community'),
                'error'
            );
        }
    }
    
    /**
     * Handle add community
     */
    private function handle_add_community() {
        global $wpdb;
        $tables = $this->get_database()->get_table_names();
        
        $name = sanitize_text_field($_POST['community_name']);
        $slug = sanitize_title($_POST['community_slug'] ?: $name);
        $description = sanitize_textarea_field($_POST['community_description']);
        $color = sanitize_hex_color($_POST['community_color']);
        
        // Check if community already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['communities']} WHERE slug = %s OR name = %s",
            $slug, $name
        ));
        
        if ($exists) {
            add_settings_error(
                'ai_community_communities',
                'community_exists',
                __('Community already exists.', 'ai-community'),
                'error'
            );
            return;
        }
        
        $result = $wpdb->insert($tables['communities'], array(
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'color' => $color,
            'created_by' => get_current_user_id()
        ));
        
        if ($result) {
            add_settings_error(
                'ai_community_communities',
                'community_added',
                __('Community added successfully!', 'ai-community'),
                'success'
            );
        } else {
            add_settings_error(
                'ai_community_communities',
                'community_error',
                __('Failed to add community.', 'ai-community'),
                'error'
            );
        }
    }
    
    /**
     * Handle delete community
     */
    private function handle_delete_community() {
        global $wpdb;
        $tables = $this->get_database()->get_table_names();
        
        $community_id = intval($_POST['community_id']);
        
        // Don't allow deleting default communities
        $community = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['communities']} WHERE id = %d",
            $community_id
        ));
        
        if (!$community) {
            add_settings_error(
                'ai_community_communities',
                'community_not_found',
                __('Community not found.', 'ai-community'),
                'error'
            );
            return;
        }
        
        if (in_array($community->slug, array('general', 'announcements'))) {
            add_settings_error(
                'ai_community_communities',
                'cannot_delete_default',
                __('Cannot delete default communities.', 'ai-community'),
                'error'
            );
            return;
        }
        
        // Move posts to general community
        $wpdb->update(
            $tables['posts'],
            array('community' => 'general'),
            array('community' => $community->slug),
            array('%s'),
            array('%s')
        );
        
        // Delete community
        $result = $wpdb->delete($tables['communities'], array('id' => $community_id));
        
        if ($result) {
            add_settings_error(
                'ai_community_communities',
                'community_deleted',
                __('Community deleted successfully!', 'ai-community'),
                'success'
            );
        } else {
            add_settings_error(
                'ai_community_communities',
                'delete_error',
                __('Failed to delete community.', 'ai-community'),
                'error'
            );
        }
    }
    
    /**
     * Handle bulk posts actions
     */
    private function handle_bulk_posts_action() {
        $action = $_POST['bulk_action'] ?? '';
        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());
        
        if (empty($post_ids)) {
            add_settings_error(
                'ai_community_posts',
                'no_posts_selected',
                __('No posts selected.', 'ai-community'),
                'error'
            );
            return;
        }
        
        $count = 0;
        
        switch ($action) {
            case 'delete':
                foreach ($post_ids as $post_id) {
                    if ($this->get_database()->delete_post($post_id)) {
                        $count++;
                    }
                }
                add_settings_error(
                    'ai_community_posts',
                    'posts_deleted',
                    sprintf(__('%d posts deleted.', 'ai-community'), $count),
                    'success'
                );
                break;
                
            case 'approve':
                global $wpdb;
                $tables = $this->get_database()->get_table_names();
                $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
                
                $count = $wpdb->query($wpdb->prepare(
                    "UPDATE {$tables['posts']} SET status = 'published' WHERE id IN ({$placeholders})",
                    $post_ids
                ));
                
                add_settings_error(
                    'ai_community_posts',
                    'posts_approved',
                    sprintf(__('%d posts approved.', 'ai-community'), $count),
                    'success'
                );
                break;
        }
    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ai-community-settings') . '">' . __('Settings', 'ai-community') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'ai-community') === false) {
            return;
        }
        
        // Check for missing API key
        if (empty($this->get_settings()->get('openrouter_api_key'))) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('AI Community: OpenRouter API key is required for AI content generation.', 'ai-community'); ?>
                    <a href="<?php echo admin_url('admin.php?page=ai-community-settings&tab=ai_generation'); ?>">
                        <?php _e('Configure now', 'ai-community'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Check system requirements
        $system_check = $this->get_ai_generator()->check_system_requirements();
        if (!$system_check['overall_status']) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php _e('AI Community: System requirements not met.', 'ai-community'); ?>
                    <a href="<?php echo admin_url('admin.php?page=ai-community-tools&tool=system'); ?>">
                        <?php _e('Check details', 'ai-community'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Show settings errors
        settings_errors();
    }
    
    // AJAX Handlers
    
    /**
     * AJAX: Generate AI content
     */
    public function ajax_generate_content() {
        check_ajax_referer('ai_community_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ai-community'));
        }
        
        $result = $this->get_ai_generator()->generate_content_manually();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Generated %d posts and %d replies successfully!', 'ai-community'),
                    $result['posts_created'],
                    $result['replies_created']
                ),
                'data' => $result
            ));
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api() {
        check_ajax_referer('ai_community_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ai-community'));
        }
        
        $openrouter = new AI_Community_OpenRouter_API();
        $result = $openrouter->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('ai_community_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ai-community'));
        }
        
        $settings = $_POST['settings'] ?? array();
        $result = $this->get_settings()->update_all($settings);
        
        if ($result) {
            // Reschedule if needed
            $this->get_ai_generator()->schedule_next_run();
            wp_send_json_success(__('Settings saved successfully!', 'ai-community'));
        } else {
            wp_send_json_error(__('Failed to save settings', 'ai-community'));
        }
    }
    
    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('ai_community_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ai-community'));
        }
        
        $log_type = $_POST['log_type'] ?? 'all';
        
        switch ($log_type) {
            case 'generation':
                delete_option('ai_community_generation_logs');
                delete_option('ai_community_generation_stats');
                break;
            case 'api':
                $openrouter = new AI_Community_OpenRouter_API();
                $openrouter->clear_errors();
                delete_option('ai_community_api_stats');
                break;
            case 'all':
                delete_option('ai_community_generation_logs');
                delete_option('ai_community_generation_stats');
                $openrouter = new AI_Community_OpenRouter_API();
                $openrouter->clear_errors();
                delete_option('ai_community_api_stats');
                break;
        }
        
        wp_send_json_success(__('Logs cleared successfully!', 'ai-community'));
    }
    
    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        check_ajax_referer('ai_community_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ai-community'));
        }
        
        $data_type = $_POST['data_type'] ?? 'all';
        $format = $_POST['format'] ?? 'json';
        
        switch ($data_type) {
            case 'settings':
                $data = $this->get_settings()->export_settings();
                break;
            case 'generation':
                $data = $this->get_ai_generator()->export_generation_data();
                break;
            case 'api':
                $openrouter = new AI_Community_OpenRouter_API();
                $data = $openrouter->export_logs($format);
                break;
            case 'all':
                $data = array(
                    'settings' => $this->get_settings()->export_settings(),
                    'generation' => $this->get_ai_generator()->export_generation_data(),
                    'database_stats' => $this->get_database()->get_stats(),
                    'export_info' => array(
                        'plugin_version' => AI_COMMUNITY_VERSION,
                        'wordpress_version' => get_bloginfo('version'),
                        'export_date' => current_time('mysql'),
                        'site_url' => get_site_url()
                    )
                );
                break;
            default:
                wp_send_json_error(__('Invalid data type', 'ai-community'));
                return;
        }
        
        if ($format === 'json') {
            $filename = "ai-community-{$data_type}-" . date('Y-m-d-H-i-s') . '.json';
            $content = json_encode($data, JSON_PRETTY_PRINT);
            $mime_type = 'application/json';
        } else {
            wp_send_json_error(__('Unsupported format', 'ai-community'));
            return;
        }
        
        wp_send_json_success(array(
            'filename' => $filename,
            'content' => $content,
            'mime_type' => $mime_type
        ));
    }
    
    /**
     * Get dashboard widget data
     */
    public function get_dashboard_data() {
        $stats = $this->get_database()->get_stats();
        $generation_stats = $this->get_ai_generator()->get_generation_stats(7);
        
        $openrouter = new AI_Community_OpenRouter_API();
        $api_health = $openrouter->get_api_health();
        
        return array(
            'database_stats' => $stats,
            'generation_stats' => $generation_stats,
            'api_health' => $api_health,
            'recent_posts' => $this->get_recent_posts(5),
            'system_status' => $this->get_system_status()
        );
    }
    
    /**
     * Get recent posts for dashboard
     */
    private function get_recent_posts($limit = 5) {
        return $this->get_database()->get_posts(array(
            'per_page' => $limit,
            'sort' => 'created_at',
            'order' => 'DESC'
        ));
    }
    
    /**
     * Get system status
     */
    private function get_system_status() {
        $status = array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => AI_COMMUNITY_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'wp_cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'curl_available' => function_exists('curl_init'),
            'json_available' => function_exists('json_encode'),
            'mbstring_available' => extension_loaded('mbstring')
        );
        
        return $status;
    }
    
    /**
     * Render admin page header
     */
    public function render_admin_header($page_title, $current_tab = '') {
        ?>
        <div class="wrap ai-community-admin">
            <div class="ai-community-header">
                <div class="ai-community-header-content">
                    <div class="ai-community-logo">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="11" width="18" height="10" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="5" r="2" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 7v4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <div class="ai-community-title-group">
                            <h1 class="ai-community-title"><?php echo esc_html($page_title); ?></h1>
                            <span class="ai-community-version">v<?php echo AI_COMMUNITY_VERSION; ?></span>
                        </div>
                    </div>
                    
                    <div class="ai-community-actions">
                        <?php if (current_user_can('manage_options')): ?>
                        <button id="generate-ai-content" class="button button-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Generate AI Content', 'ai-community'); ?>
                        </button>
                        <?php endif; ?>
                        
                        <a href="<?php echo admin_url('admin.php?page=ai-community-settings'); ?>" class="button">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Settings', 'ai-community'); ?>
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($current_tab)): ?>
                <nav class="nav-tab-wrapper wp-clearfix">
                    <?php echo $this->render_admin_tabs($current_tab); ?>
                </nav>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render admin tabs
     */
    public function render_admin_tabs($current_tab, $page = '') {
        $page = $page ?: ($_GET['page'] ?? '');
        
        $tabs = array();
        
        switch ($page) {
            case 'ai-community-settings':
                $tabs = array(
                    'general' => __('General', 'ai-community'),
                    'ai_generation' => __('AI Generation', 'ai-community'),
                    'communities' => __('Communities', 'ai-community'),
                    'users' => __('Users & Karma', 'ai-community'),
                    'advanced' => __('Advanced', 'ai-community')
                );
                break;
                
            case 'ai-community-posts':
                $tabs = array(
                    'all' => __('All Posts', 'ai-community'),
                    'ai' => __('AI Generated', 'ai-community'),
                    'pending' => __('Pending', 'ai-community')
                );
                break;
                
            case 'ai-community-tools':
                $tabs = array(
                    'export' => __('Export/Import', 'ai-community'),
                    'logs' => __('Logs', 'ai-community'),
                    'system' => __('System Check', 'ai-community'),
                    'cleanup' => __('Cleanup', 'ai-community')
                );
                break;
        }
        
        $tab_html = '';
        foreach ($tabs as $tab_key => $tab_label) {
            $url = admin_url("admin.php?page={$page}&tab={$tab_key}");
            $active = $current_tab === $tab_key ? 'nav-tab-active' : '';
            $tab_html .= "<a href='{$url}' class='nav-tab {$active}'>{$tab_label}</a>";
        }
        
        return $tab_html;
    }
    
    /**
     * Render stats widget
     */
    public function render_stats_widget($title, $stats, $icon = 'chart-bar') {
        ?>
        <div class="ai-community-stat-widget">
            <div class="stat-widget-header">
                <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
                <h3><?php echo esc_html($title); ?></h3>
            </div>
            <div class="stat-widget-content">
                <?php foreach ($stats as $label => $value): ?>
                <div class="stat-item">
                    <span class="stat-label"><?php echo esc_html($label); ?></span>
                    <span class="stat-value"><?php echo esc_html($value); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render notice box
     */
    public function render_notice($message, $type = 'info', $dismissible = true) {
        $classes = array('notice', "notice-{$type}");
        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }
        
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <p><?php echo wp_kses_post($message); ?></p>
        </div>
        <?php
    }
    
    /**
     * Get admin page URL
     */
    public function get_admin_url($page, $params = array()) {
        $url = admin_url("admin.php?page=ai-community-{$page}");
        
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * Check if current screen is plugin admin page
     */
    public function is_plugin_admin_page() {
        $screen = get_current_screen();
        return strpos($screen->id, 'ai-community') !== false;
    }
    
    /**
     * Add admin body class
     */
    public function admin_body_class($classes) {
        if ($this->is_plugin_admin_page()) {
            $classes .= ' ai-community-admin-page';
        }
        return $classes;
    }
}