<?php
/**
 * Plugin Name: AI Community Pro
 * Description: Advanced AI-powered community platform with intelligent content generation, moderation, and engagement features
 * Version: 2.0.1
 * Author: AI Community Team
 * Author URI: https://aicommunity.pro
 * License: GPL v2 or later
 * Text Domain: ai-community
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Define plugin constants
define('AI_COMMUNITY_VERSION', '2.0.1');
define('AI_COMMUNITY_PLUGIN_FILE', __FILE__);
define('AI_COMMUNITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_COMMUNITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_COMMUNITY_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AI_COMMUNITY_DB_VERSION', '2.0.1');

// Minimum requirements
define('AI_COMMUNITY_MIN_WP_VERSION', '6.0');
define('AI_COMMUNITY_MIN_PHP_VERSION', '8.0');

/**
 * Main AI Community Plugin Class
 * 
 * Handles plugin initialization, dependency injection, and lifecycle management
 */
final class AI_Community_Plugin {
    
    /**
     * Singleton instance
     */
    private static ?AI_Community_Plugin $instance = null;
    
    /**
     * Plugin components container
     */
    private array $components = [];
    
    /**
     * Plugin initialization status
     */
    private bool $initialized = false;
    
    /**
     * Error collection
     */
    private array $errors = [];
    
    /**
     * Get plugin instance (Singleton pattern)
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
    
    /**
     * Initialize plugin
     */
    private function init(): void {
        try {
            // Check requirements first
            if (!$this->check_requirements()) {
                return;
            }
            
            // Load dependencies
            $this->load_dependencies();
            
            // Initialize hooks
            $this->init_hooks();
            
            // Initialize components
            $this->init_components();
            
            $this->initialized = true;
            
        } catch (Throwable $e) {
            $this->add_error('Plugin initialization failed: ' . $e->getMessage());
            add_action('admin_notices', [$this, 'show_init_error']);
        }
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements(): bool {
        $errors = [];
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), AI_COMMUNITY_MIN_WP_VERSION, '<')) {
            $errors[] = sprintf(
                __('AI Community requires WordPress %s or higher. Current version: %s', 'ai-community'),
                AI_COMMUNITY_MIN_WP_VERSION,
                get_bloginfo('version')
            );
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, AI_COMMUNITY_MIN_PHP_VERSION, '<')) {
            $errors[] = sprintf(
                __('AI Community requires PHP %s or higher. Current version: %s', 'ai-community'),
                AI_COMMUNITY_MIN_PHP_VERSION,
                PHP_VERSION
            );
        }
        
        // Check required PHP extensions
        $required_extensions = ['json', 'curl', 'mbstring'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf(
                    __('AI Community requires PHP %s extension.', 'ai-community'),
                    $extension
                );
            }
        }
        
        if (!empty($errors)) {
            $this->errors = array_merge($this->errors, $errors);
            add_action('admin_notices', [$this, 'show_requirement_errors']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies(): void {
        $dependencies = [
            'includes/traits/trait-singleton.php',
            'includes/traits/trait-logger.php',
            'includes/class-container.php',
            'includes/class-exception-handler.php',
            'includes/class-database.php',
            'includes/class-settings.php',
            'includes/class-cache.php',
            'includes/class-openrouter-api.php',
            'includes/class-ai-generator.php',
            'includes/class-content-processor.php',
            'includes/class-moderation.php',
            'includes/class-notification.php',
            'includes/class-analytics.php',
            'includes/class-rest-api.php',
            'includes/class-webhook.php',
            'includes/functions.php',
        ];
        
        // Load admin-specific dependencies
        if (is_admin()) {
            $dependencies = array_merge($dependencies, [
                'includes/admin/class-admin.php',
                'includes/admin/class-dashboard.php',
                'includes/admin/class-settings-page.php',
                'includes/admin/class-posts-manager.php',
                'includes/admin/class-communities-manager.php',
                'includes/admin/class-analytics-page.php',
                'includes/admin/class-tools-page.php',
            ]);
        } else {
            $dependencies[] = 'includes/class-frontend.php';
        }
        
        foreach ($dependencies as $file) {
            $this->require_file($file);
        }
    }
    
    /**
     * Safely require a file
     */
    private function require_file(string $file): void {
        $path = AI_COMMUNITY_PLUGIN_DIR . $file;
        
        if (!file_exists($path)) {
            throw new Exception("Required file not found: {$file}");
        }
        
        require_once $path;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Activation/Deactivation hooks
        register_activation_hook(AI_COMMUNITY_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(AI_COMMUNITY_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Core WordPress hooks
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init_post_activation'], 10);
        add_action('wp_loaded', [$this, 'init_late_components'], 15);
        
        // Plugin management hooks
        add_filter('plugin_action_links_' . AI_COMMUNITY_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);
        
        // Asset hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX hooks for basic functionality
        add_action('wp_ajax_ai_community_heartbeat', [$this, 'ajax_heartbeat']);
        add_action('wp_ajax_nopriv_ai_community_heartbeat', [$this, 'ajax_heartbeat']);
        
        // Shortcode
        add_shortcode('ai_community', [$this, 'shortcode_handler']);
        
        // Cleanup hooks
        add_action('wp_scheduled_delete', [$this, 'cleanup_expired_data']);
    }
    
    /**
     * Initialize plugin components using dependency injection
     */
    private function init_components(): void {
        try {
            $container = AI_Community_Container::get_instance();
            
            // Register core components
            $this->register_core_components($container);
            
            // Register context-specific components
            if (is_admin()) {
                $this->register_admin_components($container);
            } else {
                $this->register_frontend_components($container);
            }
            
            // Initialize all registered components
            $container->init_all();
            
        } catch (Throwable $e) {
            throw new Exception('Component initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Register core components
     */
    private function register_core_components(AI_Community_Container $container): void {
        $container->register('exception_handler', AI_Community_Exception_Handler::class);
        $container->register('database', AI_Community_Database::class);
        $container->register('settings', AI_Community_Settings::class);
        $container->register('cache', AI_Community_Cache::class);
        $container->register('openrouter_api', AI_Community_OpenRouter_API::class);
        $container->register('ai_generator', AI_Community_AI_Generator::class);
        $container->register('content_processor', AI_Community_Content_Processor::class);
        $container->register('moderation', AI_Community_Moderation::class);
        $container->register('notification', AI_Community_Notification::class);
        $container->register('analytics', AI_Community_Analytics::class);
        $container->register('rest_api', AI_Community_REST_API::class);
        $container->register('webhook', AI_Community_Webhook::class);
    }
    
    /**
     * Register admin components
     */
    private function register_admin_components(AI_Community_Container $container): void {
        $container->register('admin', AI_Community_Admin::class);
        $container->register('dashboard', AI_Community_Dashboard::class);
        $container->register('settings_page', AI_Community_Settings_Page::class);
        $container->register('posts_manager', AI_Community_Posts_Manager::class);
        $container->register('communities_manager', AI_Community_Communities_Manager::class);
        $container->register('analytics_page', AI_Community_Analytics_Page::class);
        $container->register('tools_page', AI_Community_Tools_Page::class);
    }
    
    /**
     * Register frontend components
     */
    private function register_frontend_components(AI_Community_Container $container): void {
        $container->register('frontend', AI_Community_Frontend::class);
    }
    
    /**
     * Plugin activation
     */
    public function activate(): void {
        try {
            // Verify requirements again
            if (!$this->check_requirements()) {
                wp_die(implode('<br>', $this->errors), 'Plugin Requirements Not Met');
            }
            
            // Initialize components for activation
            if (!$this->initialized) {
                $this->load_dependencies();
                $this->init_components();
            }
            
            $container = AI_Community_Container::get_instance();
            
            // Create database tables
            $container->get('database')->create_tables();
            
            // Set default settings
            $container->get('settings')->set_defaults();
            
            // Schedule cron events
            $this->schedule_events();
            
            // Create default communities
            $this->create_default_communities();
            
            // Set activation metadata
            update_option('ai_community_activation_time', current_time('timestamp'));
            update_option('ai_community_version', AI_COMMUNITY_VERSION);
            update_option('ai_community_db_version', AI_COMMUNITY_DB_VERSION);
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Log successful activation
            error_log('AI Community Pro activated successfully (v' . AI_COMMUNITY_VERSION . ')');
            
        } catch (Throwable $e) {
            error_log('AI Community activation error: ' . $e->getMessage());
            wp_die(
                'AI Community activation failed: ' . esc_html($e->getMessage()),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        try {
            // Clear scheduled events
            wp_clear_scheduled_hook('ai_community_generate_content');
            wp_clear_scheduled_hook('ai_community_cleanup_expired');
            wp_clear_scheduled_hook('ai_community_process_analytics');
            
            // Clear caches
            if (class_exists('AI_Community_Cache')) {
                AI_Community_Cache::get_instance()->flush_all();
            }
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            error_log('AI Community Pro deactivated successfully');
            
        } catch (Throwable $e) {
            error_log('AI Community deactivation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize components that need WordPress to be fully loaded
     */
    public function init_post_activation(): void {
        if (!$this->initialized) {
            return;
        }
        
        try {
            $container = AI_Community_Container::get_instance();
            
            // Initialize REST API
            $container->get('rest_api')->init();
            
            // Initialize webhooks if enabled
            if ($container->get('settings')->get('webhooks_enabled', false)) {
                $container->get('webhook')->init();
            }
            
        } catch (Throwable $e) {
            $this->add_error('Post-activation initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize late components (after WordPress is fully loaded)
     */
    public function init_late_components(): void {
        if (!$this->initialized) {
            return;
        }
        
        try {
            $container = AI_Community_Container::get_instance();
            
            // Start AI content generation if enabled
            if ($container->get('settings')->get('ai_generation_enabled', false)) {
                $container->get('ai_generator')->schedule_next_run();
            }
            
            // Initialize analytics collection
            $container->get('analytics')->init();
            
        } catch (Throwable $e) {
            $this->add_error('Late component initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'ai-community',
            false,
            dirname(AI_COMMUNITY_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Schedule cron events
     */
    private function schedule_events(): void {
        $events = [
            'ai_community_generate_content' => 'hourly',
            'ai_community_cleanup_expired' => 'daily',
            'ai_community_process_analytics' => 'daily',
        ];
        
        foreach ($events as $hook => $recurrence) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $recurrence, $hook);
            }
        }
    }
    
    /**
     * Create default communities
     */
    private function create_default_communities(): void {
        try {
            $container = AI_Community_Container::get_instance();
            $database = $container->get('database');
            
            $default_communities = [
                [
                    'name' => __('General Discussion', 'ai-community'),
                    'slug' => 'general',
                    'description' => __('General discussions and community topics', 'ai-community'),
                    'color' => '#6366f1',
                    'icon' => 'dashicons-format-chat',
                    'status' => 'active',
                    'created_by' => 1
                ],
                [
                    'name' => __('AI & Technology', 'ai-community'),
                    'slug' => 'ai-tech',
                    'description' => __('Artificial Intelligence and technology discussions', 'ai-community'),
                    'color' => '#8b5cf6',
                    'icon' => 'dashicons-admin-tools',
                    'status' => 'active',
                    'created_by' => 1
                ],
                [
                    'name' => __('Help & Support', 'ai-community'),
                    'slug' => 'support',
                    'description' => __('Get help and support from the community', 'ai-community'),
                    'color' => '#10b981',
                    'icon' => 'dashicons-sos',
                    'status' => 'active',
                    'created_by' => 1
                ],
                [
                    'name' => __('Announcements', 'ai-community'),
                    'slug' => 'announcements',
                    'description' => __('Important announcements and updates', 'ai-community'),
                    'color' => '#f59e0b',
                    'icon' => 'dashicons-megaphone',
                    'status' => 'active',
                    'created_by' => 1
                ]
            ];
            
            foreach ($default_communities as $community) {
                $database->create_community($community);
            }
            
        } catch (Throwable $e) {
            error_log('Failed to create default communities: ' . $e->getMessage());
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void {
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        try {
            // CSS
            wp_enqueue_style(
                'ai-community-frontend',
                AI_COMMUNITY_PLUGIN_URL . 'assets/css/frontend.min.css',
                [],
                AI_COMMUNITY_VERSION
            );
            
            // JavaScript
            wp_enqueue_script(
                'ai-community-frontend',
                AI_COMMUNITY_PLUGIN_URL . 'assets/js/frontend.min.js',
                ['jquery', 'wp-api-fetch'],
                AI_COMMUNITY_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('ai-community-frontend', 'aiCommunityData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url('ai-community/v1/'),
                'nonce' => wp_create_nonce('ai_community_nonce'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'currentUser' => $this->get_current_user_data(),
                'settings' => $this->get_frontend_settings(),
                'i18n' => $this->get_i18n_data(),
            ]);
            
        } catch (Throwable $e) {
            error_log('Failed to enqueue frontend assets: ' . $e->getMessage());
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        if (!$this->is_plugin_admin_page($hook)) {
            return;
        }
        
        try {
            // CSS
            wp_enqueue_style(
                'ai-community-admin',
                AI_COMMUNITY_PLUGIN_URL . 'assets/css/admin.min.css',
                ['wp-color-picker', 'wp-components'],
                AI_COMMUNITY_VERSION
            );
            
            // JavaScript
            wp_enqueue_script(
                'ai-community-admin',
                AI_COMMUNITY_PLUGIN_URL . 'assets/js/admin.min.js',
                ['jquery', 'wp-color-picker', 'wp-components', 'wp-api-fetch'],
                AI_COMMUNITY_VERSION,
                true
            );
            
            // Chart.js for analytics
            wp_enqueue_script(
                'chart-js',
                'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.js',
                [],
                '4.4.0',
                true
            );
            
            // Localize script
            wp_localize_script('ai-community-admin', 'aiCommunityAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url('ai-community/v1/'),
                'nonce' => wp_create_nonce('ai_community_admin_nonce'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'version' => AI_COMMUNITY_VERSION,
                'i18n' => $this->get_admin_i18n_data(),
            ]);
            
        } catch (Throwable $e) {
            error_log('Failed to enqueue admin assets: ' . $e->getMessage());
        }
    }
    
    /**
     * Shortcode handler
     */
    public function shortcode_handler(array $atts): string {
        $atts = shortcode_atts([
            'layout' => 'default',
            'community' => '',
            'posts_per_page' => 10,
            'show_sidebar' => true,
        ], $atts, 'ai_community');
        
        ob_start();
        ?>
        <div id="ai-community-app" 
             class="ai-community-wrapper" 
             data-layout="<?php echo esc_attr($atts['layout']); ?>"
             data-community="<?php echo esc_attr($atts['community']); ?>"
             data-posts-per-page="<?php echo esc_attr($atts['posts_per_page']); ?>"
             data-show-sidebar="<?php echo esc_attr($atts['show_sidebar'] ? '1' : '0'); ?>">
            
            <div class="ai-community-loading">
                <div class="loading-spinner"></div>
                <p><?php esc_html_e('Loading AI Community...', 'ai-community'); ?></p>
            </div>
            
            <noscript>
                <div class="ai-community-noscript">
                    <h3><?php esc_html_e('JavaScript Required', 'ai-community'); ?></h3>
                    <p><?php esc_html_e('AI Community requires JavaScript to function properly. Please enable JavaScript in your browser.', 'ai-community'); ?></p>
                </div>
            </noscript>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX heartbeat handler
     */
    public function ajax_heartbeat(): void {
        check_ajax_referer('ai_community_nonce', 'nonce');
        
        wp_send_json_success([
            'timestamp' => current_time('timestamp'),
            'version' => AI_COMMUNITY_VERSION,
            'status' => 'ok'
        ]);
    }
    
    /**
     * Clean up expired data
     */
    public function cleanup_expired_data(): void {
        if (!$this->initialized) {
            return;
        }
        
        try {
            $container = AI_Community_Container::get_instance();
            
            // Clean up expired cache entries
            $container->get('cache')->cleanup_expired();
            
            // Clean up old analytics data
            $container->get('analytics')->cleanup_old_data();
            
            // Clean up old AI generation logs
            $container->get('ai_generator')->cleanup_old_logs();
            
        } catch (Throwable $e) {
            error_log('Cleanup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links(array $links): array {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=ai-community') . '">' . __('Dashboard', 'ai-community') . '</a>',
            '<a href="' . admin_url('admin.php?page=ai-community-settings') . '">' . __('Settings', 'ai-community') . '</a>',
        ];
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Add plugin row meta links
     */
    public function plugin_row_meta(array $meta, string $plugin_file): array {
        if ($plugin_file === AI_COMMUNITY_PLUGIN_BASENAME) {
            $meta[] = '<a href="https://aicommunity.pro/docs/" target="_blank">' . __('Documentation', 'ai-community') . '</a>';
            $meta[] = '<a href="https://aicommunity.pro/support/" target="_blank">' . __('Support', 'ai-community') . '</a>';
        }
        
        return $meta;
    }
    
    /**
     * Show initialization error
     */
    public function show_init_error(): void {
        $class = 'notice notice-error';
        $message = __('AI Community Pro failed to initialize. Please check error logs for details.', 'ai-community');
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    /**
     * Show requirement errors
     */
    public function show_requirement_errors(): void {
        $class = 'notice notice-error';
        
        foreach ($this->errors as $error) {
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($error));
        }
    }
    
    /**
     * Helper methods
     */
    private function should_load_frontend_assets(): bool {
        global $post;
        
        // Check if shortcode is present
        if (is_singular() && $post && has_shortcode($post->post_content, 'ai_community')) {
            return true;
        }
        
        // Check if we're on a dedicated community page
        if ($this->initialized) {
            try {
                $settings = AI_Community_Container::get_instance()->get('settings');
                return $settings->get('load_on_all_pages', false) || 
                       ($settings->get('load_on_homepage', false) && is_front_page());
            } catch (Throwable $e) {
                return false;
            }
        }
        
        return false;
    }
    
    private function is_plugin_admin_page(string $hook): bool {
        return strpos($hook, 'ai-community') !== false;
    }
    
    private function get_current_user_data(): ?array {
        if (!is_user_logged_in()) {
            return null;
        }
        
        $user = wp_get_current_user();
        return [
            'id' => $user->ID,
            'name' => $user->display_name,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'avatar' => get_avatar_url($user->ID, ['size' => 64]),
            'roles' => $user->roles,
            'capabilities' => array_keys($user->allcaps),
        ];
    }
    
    private function get_frontend_settings(): array {
        if (!$this->initialized) {
            return [];
        }
        
        try {
            $container = AI_Community_Container::get_instance();
            $settings = $container->get('settings');
            
            return [
                'theme' => $settings->get('theme', 'default'),
                'primary_color' => $settings->get('primary_color', '#3b82f6'),
                'posts_per_page' => $settings->get('posts_per_page', 10),
                'enable_voting' => $settings->get('enable_voting', true),
                'enable_comments' => $settings->get('enable_comments', true),
                'enable_notifications' => $settings->get('enable_notifications', true),
                'moderation_mode' => $settings->get('moderation_mode', 'auto'),
            ];
        } catch (Throwable $e) {
            return [];
        }
    }
    
    private function get_i18n_data(): array {
        return [
            'loading' => __('Loading...', 'ai-community'),
            'error' => __('An error occurred', 'ai-community'),
            'success' => __('Success!', 'ai-community'),
            'confirm' => __('Are you sure?', 'ai-community'),
            'cancel' => __('Cancel', 'ai-community'),
            'save' => __('Save', 'ai-community'),
            'delete' => __('Delete', 'ai-community'),
            'edit' => __('Edit', 'ai-community'),
            'reply' => __('Reply', 'ai-community'),
            'vote_up' => __('Vote up', 'ai-community'),
            'vote_down' => __('Vote down', 'ai-community'),
            'login_required' => __('Please log in to continue', 'ai-community'),
        ];
    }
    
    private function get_admin_i18n_data(): array {
        return array_merge($this->get_i18n_data(), [
            'generating' => __('Generating content...', 'ai-community'),
            'testing_api' => __('Testing API connection...', 'ai-community'),
            'saving_settings' => __('Saving settings...', 'ai-community'),
            'import_success' => __('Data imported successfully', 'ai-community'),
            'export_success' => __('Data exported successfully', 'ai-community'),
            'bulk_action_success' => __('Bulk action completed', 'ai-community'),
        ]);
    }
    
    private function add_error(string $error): void {
        $this->errors[] = $error;
        error_log('AI Community Error: ' . $error);
    }
    
    /**
     * Get plugin instance (global function)
     */
    public static function plugin(): self {
        return self::get_instance();
    }
    
    /**
     * Get component from container
     */
    public function get_component(string $name) {
        if (!$this->initialized) {
            return null;
        }
        
        try {
            return AI_Community_Container::get_instance()->get($name);
        } catch (Throwable $e) {
            error_log("Failed to get component '{$name}': " . $e->getMessage());
            return null;
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    AI_Community_Plugin::get_instance();
}, 10);

// Emergency uninstall handler
if (!function_exists('ai_community_uninstall')) {
    function ai_community_uninstall() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Only proceed if user explicitly wants to delete data
        $delete_data = get_option('ai_community_delete_on_uninstall', false);
        if (!$delete_data) {
            return;
        }
        
        global $wpdb;
        
        // Drop custom tables
        $tables = [
            $wpdb->prefix . 'ai_community_posts',
            $wpdb->prefix . 'ai_community_comments',
            $wpdb->prefix . 'ai_community_votes',
            $wpdb->prefix . 'ai_community_communities',
            $wpdb->prefix . 'ai_community_user_meta',
            $wpdb->prefix . 'ai_community_analytics',
            $wpdb->prefix . 'ai_community_notifications',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
        
        // Delete options
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ai_community_%'");
        
        // Delete user meta
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ai_community_%'");
        
        // Clear caches
        wp_cache_flush();
    }
}

register_uninstall_hook(__FILE__, 'ai_community_uninstall');