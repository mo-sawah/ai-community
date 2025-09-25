<?php
/**
 * Plugin Name: AI Community
 * Description: AI-powered community platform that generates engaging discussions based on website content using OpenRouter AI
 * Version: 1.0.1
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
define('AI_COMMUNITY_VERSION', '1.0.1');
define('AI_COMMUNITY_PLUGIN_FILE', __FILE__);
define('AI_COMMUNITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_COMMUNITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_COMMUNITY_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Minimum requirements
define('AI_COMMUNITY_MIN_WP_VERSION', '5.0');
define('AI_COMMUNITY_MIN_PHP_VERSION', '7.4');

/**
 * Main plugin initialization
 */
class AI_Community_Plugin {

    private function initialize_component($name, $config, &$initialized) {
        $class_name = $config['class'];
        
        // Check if class exists
        if (!class_exists($class_name)) {
            error_log("AI Community: Class {$class_name} not found for component {$name}");
            return false;
        }
        
        // Check dependencies
        foreach ($config['deps'] as $dep) {
            if (!in_array($dep, $initialized)) {
                error_log("AI Community: Dependency {$dep} not initialized for component {$name}");
                return false;
            }
        }
        
        try {
            $this->$name = new $class_name();
            return true;
        } catch (Exception $e) {
            error_log("AI Community: Failed to initialize {$name}: " . $e->getMessage());
            return false;
        }
    }
    
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin components - initialized later to avoid circular dependencies
     */
    public $database;
    public $settings;
    public $openrouter_api;
    public $ai_generator;
    public $rest_api;
    public $admin;
    public $frontend;
    
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
        // Check requirements first
        if (!$this->check_requirements()) {
            return;
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize hooks
        $this->init_hooks();
        
        // Initialize components on WordPress init to avoid dependency issues
        add_action('init', array($this, 'init_components'), 1);
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), AI_COMMUNITY_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, AI_COMMUNITY_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Helper functions first
        $this->include_if_exists('includes/functions.php');
        
        // Core classes - order matters to avoid dependency issues
        $this->include_if_exists('includes/class-database.php');
        $this->include_if_exists('includes/class-settings.php');
        $this->include_if_exists('includes/class-openrouter-api.php');
        $this->include_if_exists('includes/class-ai-generator.php');
        $this->include_if_exists('includes/class-rest-api.php');
        
        // Admin classes (only if in admin)
        if (is_admin()) {
            $this->include_if_exists('includes/class-admin.php');
        }
        
        // Frontend classes (only if not admin)
        if (!is_admin()) {
            $this->include_if_exists('includes/class-frontend.php');
        }
    }
    
    /**
     * Safely include file if it exists
     */
    private function include_if_exists($file) {
        $path = AI_COMMUNITY_PLUGIN_DIR . $file;
        if (file_exists($path)) {
            require_once $path;
            return true;
        } else {
            error_log("AI Community: Missing file - {$file}");
            return false;
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(AI_COMMUNITY_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(AI_COMMUNITY_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . AI_COMMUNITY_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        
        // Load textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Basic AJAX handler (more detailed ones in components)
        add_action('wp_ajax_ai_community_basic', array($this, 'handle_basic_ajax'));
        add_action('wp_ajax_nopriv_ai_community_basic', array($this, 'handle_basic_ajax'));
        
        // Shortcode
        add_shortcode('ai_community', array($this, 'shortcode_handler'));
    }
    
    private function init_components() {
        try {
            // Initialize in dependency order
            $this->database = new AI_Community_Database();
            $this->settings = new AI_Community_Settings();
            $this->openrouter_api = new AI_Community_OpenRouter_API();
            $this->ai_generator = new AI_Community_AI_Generator();
            $this->rest_api = new AI_Community_REST_API();
            
            // Context-specific components
            if (is_admin()) {
                $this->admin = new AI_Community_Admin();
            } else {
                $this->frontend = new AI_Community_Frontend();
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('AI Community: Failed to initialize components - ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>AI Community Plugin Error: ' . esc_html($e->getMessage()) . '</p></div>';
            });
            return false;
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        try {
            // Check requirements first
            if (!$this->check_requirements()) {
                wp_die(
                    'AI Community Plugin requirements not met. Please check PHP version (7.4+) and WordPress version (5.0+).',
                    'Plugin Requirements Not Met',
                    array('back_link' => true)
                );
            }
            
            // Load dependencies first
            $this->load_dependencies();

            // Check required classes exist
            $required_classes = ['AI_Community_Database', 'AI_Community_Settings'];
            foreach ($required_classes as $class) {
                if (!class_exists($class)) {
                    wp_die(
                        "AI Community: Required class {$class} not found. Plugin files may be corrupted.",
                        'Plugin Activation Error',
                        array('back_link' => true)
                    );
                }
            }
            
            // Create database instance for activation
            $database = new AI_Community_Database();
            $database->create_tables();
            
            // Create settings instance for activation
            $settings = new AI_Community_Settings();
            $settings->set_default_options();
            
            // Schedule cron events
            $this->schedule_events();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Set activation metadata
            update_option('ai_community_activation_time', current_time('timestamp'));
            update_option('ai_community_version', AI_COMMUNITY_VERSION);
            
            // Create default communities
            $this->create_default_communities($database);
            
            // Log successful activation
            error_log('AI Community Plugin activated successfully');
            
        } catch (Exception $e) {
            error_log('AI Community Activation Error: ' . $e->getMessage());
            
            // Clean up any partial installation
            $this->cleanup_failed_activation();
            
            // Show error to user
            wp_die(
                'AI Community Plugin activation failed: ' . $e->getMessage() . '<br><br>Please check your error logs for more details.',
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }
    }

    /**
     * Create default communities during activation
     */
    private function create_default_communities($database) {
        global $wpdb;
        $tables = $database->get_table_names();
        
        $default_communities = array(
            array(
                'name' => 'General',
                'slug' => 'general',
                'description' => 'General discussions and topics',
                'color' => '#6366f1',
                'created_by' => 1
            ),
            array(
                'name' => 'Development',
                'slug' => 'development',
                'description' => 'Software development discussions',
                'color' => '#10b981',
                'created_by' => 1
            ),
            array(
                'name' => 'AI & Machine Learning',
                'slug' => 'ai',
                'description' => 'Artificial Intelligence and ML topics',
                'color' => '#8b5cf6',
                'created_by' => 1
            ),
            array(
                'name' => 'Announcements',
                'slug' => 'announcements',
                'description' => 'Important announcements and news',
                'color' => '#f59e0b',
                'created_by' => 1
            )
        );
        
        foreach ($default_communities as $community) {
            // Check if community already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['communities']} WHERE slug = %s",
                $community['slug']
            ));
            
            if (!$exists) {
                $wpdb->insert($tables['communities'], $community);
            }
        }
    }

    /**
     * Clean up after failed activation
     */
    private function cleanup_failed_activation() {
        // Remove any options that were set
        delete_option('ai_community_activation_time');
        delete_option('ai_community_version');
        
        // Clear any scheduled events
        wp_clear_scheduled_hook('ai_community_generate_content');
        wp_clear_scheduled_hook('ai_community_cleanup_old_posts');
        
        // Note: We don't drop database tables in case there's existing data
        error_log('AI Community: Cleaned up failed activation');
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
     * Schedule cron events
     */
    private function schedule_events() {
        // Only schedule if not already scheduled
        if (!wp_next_scheduled('ai_community_generate_content')) {
            wp_schedule_event(time(), 'hourly', 'ai_community_generate_content');
        }
        
        if (!wp_next_scheduled('ai_community_cleanup_old_posts')) {
            wp_schedule_event(time(), 'daily', 'ai_community_cleanup_old_posts');
        }
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
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if shortcode is present or on specific pages
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
        
        // Localize script with safe data
        wp_localize_script('ai-community-frontend', 'aiCommunityData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('ai-community/v1/'),
            'nonce' => wp_create_nonce('ai_community_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'currentUser' => $this->get_safe_current_user_data(),
            'translations' => array(
                'loading' => __('Loading...', 'ai-community'),
                'error' => __('Error occurred', 'ai-community'),
                'success' => __('Success!', 'ai-community')
            )
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
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_localize_script('jquery', 'aiCommunityAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_community_admin_nonce')
        ));
    }
    
    /**
     * Basic shortcode handler
     */
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'layout' => 'sidebar',
            'posts_per_page' => 10
        ), $atts);
        
        // Basic HTML structure that JavaScript will populate
        ob_start();
        ?>
        <div id="ai-community-app" class="ai-community-container" data-layout="<?php echo esc_attr($atts['layout']); ?>">
            <div class="ai-community-loading">
                <p><?php _e('Loading AI Community...', 'ai-community'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Basic AJAX handler
     */
    public function handle_basic_ajax() {
        check_ajax_referer('ai_community_nonce', 'nonce');
        
        wp_send_json_success(array(
            'message' => 'AI Community is working!',
            'version' => AI_COMMUNITY_VERSION
        ));
    }
    
    /**
     * Check if frontend assets should be loaded
     */
    private function should_load_frontend_assets() {
        global $post;
        
        // Check if shortcode is present
        if (is_singular() && $post && has_shortcode($post->post_content, 'ai_community')) {
            return true;
        }
        
        // Check specific pages/settings
        if ($this->settings && $this->settings->get('show_on_homepage', false) && is_front_page()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get safe current user data
     */
    private function get_safe_current_user_data() {
        if (!is_user_logged_in()) {
            return null;
        }
        
        $user = wp_get_current_user();
        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'username' => $user->user_login,
            'avatar' => get_avatar_url($user->ID, array('size' => 64))
        );
    }
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=ai-community') . '">' . __('Dashboard', 'ai-community') . '</a>',
            '<a href="' . admin_url('admin.php?page=ai-community-settings') . '">' . __('Settings', 'ai-community') . '</a>',
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
    
    /**
     * Get component safely
     */
    public function get_component($name) {
        return isset($this->$name) ? $this->$name : null;
    }
}

/**
 * Initialize plugin
 */
function ai_community_init() {
    return AI_Community_Plugin::get_instance();
}

// Start the plugin after all plugins are loaded
add_action('plugins_loaded', 'ai_community_init', 10);

/**
 * Emergency error handler for debugging
 */
if (!function_exists('ai_community_handle_error')) {
    function ai_community_handle_error($errno, $errstr, $errfile, $errline) {
        if (strpos($errfile, 'ai-community') !== false) {
            error_log("AI Community Error: [{$errno}] {$errstr} in {$errfile} on line {$errline}");
        }
        return false; // Don't prevent normal error handling
    }
    
    set_error_handler('ai_community_handle_error');
}