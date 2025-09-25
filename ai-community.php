<?php
/**
 * Plugin Name: AI Community
 * Description: AI-powered community platform that generates engaging discussions based on website content using OpenRouter AI
 * Version: 1.0.0
 * Author: Mohamed Sawah
 * Author URI: https://sawahsolutions.com
 * License: GPL v2 or later
 * Text Domain: ai-community
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_COMMUNITY_VERSION', '1.0.0');
define('AI_COMMUNITY_PLUGIN_FILE', __FILE__);
define('AI_COMMUNITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_COMMUNITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_COMMUNITY_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Minimum WordPress and PHP version requirements
define('AI_COMMUNITY_MIN_WP_VERSION', '5.0');
define('AI_COMMUNITY_MIN_PHP_VERSION', '7.4');

/**
 * Main plugin class
 */
class AI_Community_Plugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    public $database;
    public $rest_api;
    public $ai_generator;
    public $admin;
    public $frontend;
    public $shortcodes;
    public $user_management;
    public $communities;
    public $posts;
    public $comments;
    public $voting;
    public $settings;
    public $openrouter_api;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->check_requirements();
        $this->init();
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), AI_COMMUNITY_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, AI_COMMUNITY_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return;
        }
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load plugin files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Setup hooks
        $this->setup_hooks();
        
        // Load textdomain
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core functions
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/functions.php';
        
        // Main classes
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-database.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-settings.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-openrouter-api.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-ai-generator.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-user-management.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-communities.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-posts.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-comments.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-voting.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once AI_COMMUNITY_PLUGIN_DIR . 'includes/class-admin.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->database = new AI_Community_Database();
        $this->settings = new AI_Community_Settings();
        $this->openrouter_api = new AI_Community_OpenRouter_API();
        $this->ai_generator = new AI_Community_AI_Generator();
        $this->user_management = new AI_Community_User_Management();
        $this->communities = new AI_Community_Communities();
        $this->posts = new AI_Community_Posts();
        $this->comments = new AI_Community_Comments();
        $this->voting = new AI_Community_Voting();
        $this->rest_api = new AI_Community_REST_API();
        $this->shortcodes = new AI_Community_Shortcodes();
        $this->frontend = new AI_Community_Frontend();
        
        // Initialize admin only in admin context
        if (is_admin()) {
            $this->admin = new AI_Community_Admin();
        }
    }
    
    /**
     * Setup plugin hooks
     */
    private function setup_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(AI_COMMUNITY_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(AI_COMMUNITY_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . AI_COMMUNITY_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        
        // Init hook for post-WordPress load tasks
        add_action('init', array($this, 'init_after_wp_loaded'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_ai_community_action', array($this, 'handle_ajax'));
        add_action('wp_ajax_nopriv_ai_community_action', array($this, 'handle_ajax'));
        
        // Cron hooks
        add_action('ai_community_generate_content', array($this->ai_generator, 'scheduled_generation'));
        add_action('ai_community_cleanup_old_posts', array($this, 'cleanup_old_posts'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-community',
            false,
            dirname(AI_COMMUNITY_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Initialize after WordPress is fully loaded
     */
    public function init_after_wp_loaded() {
        // Schedule cron jobs if not already scheduled
        $this->schedule_cron_jobs();
        
        // Handle plugin updates
        $this->maybe_update_plugin();
        
        // Initialize user roles and capabilities
        $this->setup_user_roles();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->database->create_tables();
        
        // Set default options
        $this->settings->set_default_options();
        
        // Create default communities
        $this->communities->create_default_communities();
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        // Setup user roles
        $this->setup_user_roles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('ai_community_activation_time', current_time('timestamp'));
        update_option('ai_community_version', AI_COMMUNITY_VERSION);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('ai_community_generate_content');
        wp_clear_scheduled_hook('ai_community_cleanup_old_posts');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        // Schedule AI content generation
        if (!wp_next_scheduled('ai_community_generate_content')) {
            wp_schedule_event(time(), 'hourly', 'ai_community_generate_content');
        }
        
        // Schedule cleanup task
        if (!wp_next_scheduled('ai_community_cleanup_old_posts')) {
            wp_schedule_event(time(), 'daily', 'ai_community_cleanup_old_posts');
        }
    }
    
    /**
     * Setup user roles and capabilities
     */
    private function setup_user_roles() {
        $capabilities = array(
            'read_ai_community_posts',
            'create_ai_community_posts',
            'edit_ai_community_posts',
            'delete_ai_community_posts',
            'moderate_ai_community',
            'manage_ai_community_settings'
        );
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Add basic capabilities to subscribers
        $subscriber_role = get_role('subscriber');
        if ($subscriber_role) {
            $subscriber_role->add_cap('read_ai_community_posts');
            $subscriber_role->add_cap('create_ai_community_posts');
        }
    }
    
    /**
     * Maybe update plugin
     */
    private function maybe_update_plugin() {
        $installed_version = get_option('ai_community_version', '0.0.0');
        
        if (version_compare($installed_version, AI_COMMUNITY_VERSION, '<')) {
            $this->update_plugin($installed_version);
            update_option('ai_community_version', AI_COMMUNITY_VERSION);
        }
    }
    
    /**
     * Update plugin
     */
    private function update_plugin($from_version) {
        // Run update routines based on version
        if (version_compare($from_version, '1.0.0', '<')) {
            // Update to 1.0.0
            $this->database->update_tables_to_v1();
        }
        
        // Clear any caches
        wp_cache_flush();
    }
    
    /**
     * Cleanup old posts
     */
    public function cleanup_old_posts() {
        $settings = $this->settings->get_all();
        $cleanup_days = isset($settings['cleanup_old_posts_days']) ? $settings['cleanup_old_posts_days'] : 365;
        
        if ($cleanup_days > 0) {
            $this->posts->cleanup_old_posts($cleanup_days);
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages where the community is displayed
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'ai-community-frontend',
            AI_COMMUNITY_PLUGIN_URL . 'assets/css/ai-community.css',
            array(),
            AI_COMMUNITY_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'ai-community-frontend',
            AI_COMMUNITY_PLUGIN_URL . 'assets/js/ai-community.js',
            array('jquery'),
            AI_COMMUNITY_VERSION,
            true
        );
        
        // React components (if needed)
        if ($this->settings->get('enable_react_components', false)) {
            wp_enqueue_script(
                'ai-community-react',
                AI_COMMUNITY_PLUGIN_URL . 'assets/js/react-components.js',
                array('react', 'react-dom'),
                AI_COMMUNITY_VERSION,
                true
            );
        }
        
        // Localize script
        wp_localize_script('ai-community-frontend', 'aiCommunityData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('ai-community/v1/'),
            'nonce' => wp_create_nonce('ai_community_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'currentUser' => $this->get_current_user_data(),
            'settings' => $this->get_frontend_settings(),
            'translations' => $this->get_frontend_translations()
        ));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
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
        
        // Localize admin script
        wp_localize_script('ai-community-admin', 'aiCommunityAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_community_admin_nonce'),
            'translations' => $this->get_admin_translations()
        ));
    }
    
    /**
     * Check if frontend assets should be loaded
     */
    private function should_load_frontend_assets() {
        global $post;
        
        // Always load on community pages
        if (is_page() && $post && has_shortcode($post->post_content, 'ai_community')) {
            return true;
        }
        
        // Load if community is set as homepage
        if (is_front_page() && $this->settings->get('show_on_homepage', false)) {
            return true;
        }
        
        // Allow themes/plugins to override
        return apply_filters('ai_community_load_frontend_assets', false);
    }
    
    /**
     * Get current user data for frontend
     */
    private function get_current_user_data() {
        if (!is_user_logged_in()) {
            return null;
        }
        
        $user = wp_get_current_user();
        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'karma' => $this->user_management->get_user_karma($user->ID),
            'avatar' => get_avatar_url($user->ID, array('size' => 64)),
            'capabilities' => $user->allcaps
        );
    }
    
    /**
     * Get frontend settings
     */
    private function get_frontend_settings() {
        $settings = $this->settings->get_all();
        
        // Only return settings needed for frontend
        return array(
            'layout_type' => $settings['layout_type'],
            'primary_color' => $settings['primary_color'],
            'secondary_color' => $settings['secondary_color'],
            'font_family' => $settings['font_family'],
            'posts_per_page' => $settings['posts_per_page'],
            'enable_voting' => $settings['enable_voting'],
            'enable_comments' => $settings['enable_comments']
        );
    }
    
    /**
     * Get frontend translations
     */
    private function get_frontend_translations() {
        return array(
            'loading' => __('Loading...', 'ai-community'),
            'error' => __('Error occurred', 'ai-community'),
            'success' => __('Success!', 'ai-community'),
            'confirm_delete' => __('Are you sure you want to delete this?', 'ai-community'),
            'vote_login_required' => __('Please login to vote', 'ai-community'),
            'comment_login_required' => __('Please login to comment', 'ai-community')
        );
    }
    
    /**
     * Get admin translations
     */
    private function get_admin_translations() {
        return array(
            'generating_content' => __('Generating AI content...', 'ai-community'),
            'content_generated' => __('AI content generated successfully!', 'ai-community'),
            'generation_failed' => __('Failed to generate content', 'ai-community'),
            'settings_saved' => __('Settings saved successfully', 'ai-community'),
            'confirm_delete' => __('Are you sure?', 'ai-community')
        );
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_community_nonce')) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['sub_action'] ?? '');
        
        // Route to appropriate handler
        switch ($action) {
            case 'get_posts':
                $this->posts->ajax_get_posts();
                break;
            case 'create_post':
                $this->posts->ajax_create_post();
                break;
            case 'vote_post':
                $this->voting->ajax_vote_post();
                break;
            case 'add_comment':
                $this->comments->ajax_add_comment();
                break;
            default:
                wp_die('Invalid action');
        }
    }
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=ai-community-settings') . '">' . __('Settings', 'ai-community') . '</a>',
            '<a href="' . admin_url('admin.php?page=ai-community') . '">' . __('Dashboard', 'ai-community') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * WordPress version notice
     */
    public function wp_version_notice() {
        $message = sprintf(
            __('AI Community requires WordPress %s or higher. Please update WordPress.', 'ai-community'),
            AI_COMMUNITY_MIN_WP_VERSION
        );
        
        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice() {
        $message = sprintf(
            __('AI Community requires PHP %s or higher. Please update PHP.', 'ai-community'),
            AI_COMMUNITY_MIN_PHP_VERSION
        );
        
        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
    }
}

// Initialize plugin
function ai_community_init() {
    return AI_Community_Plugin::get_instance();
}

// Start the plugin
ai_community_init();