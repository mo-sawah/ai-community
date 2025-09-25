<?php
/**
 * AI Community Settings Class
 * 
 * Manages plugin settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Community_Settings {
    
    /**
     * Settings option name
     */
    const OPTION_NAME = 'ai_community_settings';
    
    /**
     * Default settings
     */
    private $defaults = array(
        // General Settings
        'layout_type' => 'sidebar',
        'primary_color' => '#3b82f6',
        'secondary_color' => '#10b981',
        'font_family' => 'system',
        'posts_per_page' => 10,
        'enable_voting' => true,
        'enable_comments' => true,
        'enable_user_registration' => true,
        'require_moderation' => false,
        'show_on_homepage' => false,
        
        // AI Generation Settings
        'ai_generation_enabled' => true,
        'posts_per_day' => 5,
        'replies_per_post' => 3,
        'openrouter_api_key' => '',
        'ai_model' => 'openai/gpt-3.5-turbo',
        'web_search_enabled' => true,
        'source_websites' => array(),
        'post_topics' => array('development', 'ai', 'community', 'tutorials'),
        'ai_generation_schedule' => 'hourly',
        'max_ai_posts_per_hour' => 10,
        'ai_content_quality_threshold' => 0.7,
        
        // Content Settings
        'max_post_length' => 10000,
        'max_comment_length' => 2000,
        'enable_rich_editor' => true,
        'allow_html_in_posts' => true,
        'enable_post_thumbnails' => true,
        'auto_generate_excerpts' => true,
        
        // Community Settings
        'default_community' => 'general',
        'allow_community_creation' => false,
        'max_communities_per_user' => 3,
        'community_moderation_required' => true,
        
        // User Settings
        'karma_system_enabled' => true,
        'karma_for_upvote' => 1,
        'karma_for_downvote' => -1,
        'karma_for_post' => 2,
        'karma_for_comment' => 1,
        'min_karma_to_vote' => 0,
        'min_karma_to_post' => 0,
        
        // Notification Settings
        'email_notifications' => true,
        'notify_on_comment' => true,
        'notify_on_vote' => false,
        'notify_on_mention' => true,
        'admin_notification_email' => '',
        
        // Security Settings
        'enable_captcha' => false,
        'captcha_provider' => 'recaptcha',
        'captcha_site_key' => '',
        'captcha_secret_key' => '',
        'enable_rate_limiting' => true,
        'max_posts_per_hour' => 10,
        'max_comments_per_hour' => 50,
        
        // Advanced Settings
        'enable_rest_api' => true,
        'enable_webhooks' => false,
        'webhook_urls' => array(),
        'cache_enabled' => true,
        'cache_duration' => 3600,
        'cleanup_old_posts' => true,
        'cleanup_old_posts_days' => 365,
        'debug_mode' => false,
        'log_ai_requests' => false
    );
    
    /**
     * Settings cache
     */
    private $settings = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Don't initialize hooks in constructor to avoid circular dependencies
        // They will be initialized by the main plugin class
    }
    
    /**
     * Initialize settings (called by main plugin)
     */
    public function init() {
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('ai_community_default_settings', array($this, 'filter_default_settings'));
    }
    
    /**
     * Register settings with WordPress
     */
    public function register_settings() {
        register_setting(
            'ai_community_settings',
            self::OPTION_NAME,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->defaults
            )
        );
    }
    
    /**
     * Get all settings
     */
    public function get_all() {
        if ($this->settings === null) {
            $saved_settings = get_option(self::OPTION_NAME, array());
            $this->settings = wp_parse_args($saved_settings, $this->defaults);
        }
        
        return $this->settings;
    }
    
    /**
     * Get specific setting
     */
    public function get($key, $default = null) {
        $settings = $this->get_all();
        
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        
        return $default !== null ? $default : (isset($this->defaults[$key]) ? $this->defaults[$key] : null);
    }
    
    /**
     * Update specific setting
     */
    public function update($key, $value) {
        $settings = $this->get_all();
        $settings[$key] = $value;
        
        return $this->update_all($settings);
    }
    
    /**
     * Update all settings
     */
    public function update_all($settings) {
        $sanitized_settings = $this->sanitize_settings($settings);
        $result = update_option(self::OPTION_NAME, $sanitized_settings);
        
        if ($result) {
            $this->settings = $sanitized_settings;
            do_action('ai_community_settings_updated', $sanitized_settings);
        }
        
        return $result;
    }
    
    /**
     * Set default options
     */
    public function set_default_options() {
        $existing_settings = get_option(self::OPTION_NAME, false);
        
        if ($existing_settings === false) {
            // First installation - set all defaults
            $this->update_all($this->defaults);
        } else {
            // Update existing settings with new defaults
            $updated_settings = wp_parse_args($existing_settings, $this->defaults);
            if ($updated_settings !== $existing_settings) {
                $this->update_all($updated_settings);
            }
        }
        
        // Set default source websites if empty
        if (empty($this->get('source_websites'))) {
            $this->update('source_websites', array(get_site_url()));
        }
        
        // Set admin notification email if empty
        if (empty($this->get('admin_notification_email'))) {
            $this->update('admin_notification_email', get_option('admin_email'));
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($settings) {
        $sanitized = array();
        
        foreach ($settings as $key => $value) {
            switch ($key) {
                // Text fields
                case 'layout_type':
                case 'font_family':
                case 'default_community':
                case 'ai_model':
                case 'ai_generation_schedule':
                case 'captcha_provider':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                
                // Color fields
                case 'primary_color':
                case 'secondary_color':
                    $sanitized[$key] = sanitize_hex_color($value);
                    break;
                
                // Integer fields
                case 'posts_per_page':
                case 'posts_per_day':
                case 'replies_per_post':
                case 'max_ai_posts_per_hour':
                case 'max_post_length':
                case 'max_comment_length':
                case 'max_communities_per_user':
                case 'karma_for_upvote':
                case 'karma_for_downvote':
                case 'karma_for_post':
                case 'karma_for_comment':
                case 'min_karma_to_vote':
                case 'min_karma_to_post':
                case 'max_posts_per_hour':
                case 'max_comments_per_hour':
                case 'cache_duration':
                case 'cleanup_old_posts_days':
                    $sanitized[$key] = (int) $value;
                    break;
                
                // Float fields
                case 'ai_content_quality_threshold':
                    $sanitized[$key] = (float) $value;
                    break;
                
                // Boolean fields
                case 'enable_voting':
                case 'enable_comments':
                case 'enable_user_registration':
                case 'require_moderation':
                case 'show_on_homepage':
                case 'ai_generation_enabled':
                case 'web_search_enabled':
                case 'enable_rich_editor':
                case 'allow_html_in_posts':
                case 'enable_post_thumbnails':
                case 'auto_generate_excerpts':
                case 'allow_community_creation':
                case 'community_moderation_required':
                case 'karma_system_enabled':
                case 'email_notifications':
                case 'notify_on_comment':
                case 'notify_on_vote':
                case 'notify_on_mention':
                case 'enable_captcha':
                case 'enable_rate_limiting':
                case 'enable_rest_api':
                case 'enable_webhooks':
                case 'cache_enabled':
                case 'cleanup_old_posts':
                case 'debug_mode':
                case 'log_ai_requests':
                    $sanitized[$key] = (bool) $value;
                    break;
                
                // Email fields
                case 'admin_notification_email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                
                // API keys and sensitive data
                case 'openrouter_api_key':
                case 'captcha_site_key':
                case 'captcha_secret_key':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                
                // Array fields
                case 'source_websites':
                case 'webhook_urls':
                    if (is_array($value)) {
                        $sanitized[$key] = array_map('esc_url_raw', array_filter($value));
                    } else {
                        $sanitized[$key] = array();
                    }
                    break;
                
                case 'post_topics':
                    if (is_array($value)) {
                        $sanitized[$key] = array_map('sanitize_text_field', array_filter($value));
                    } elseif (is_string($value)) {
                        $sanitized[$key] = array_map('trim', explode(',', $value));
                        $sanitized[$key] = array_map('sanitize_text_field', array_filter($sanitized[$key]));
                    } else {
                        $sanitized[$key] = array();
                    }
                    break;
                
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        // Validate specific settings
        $sanitized = $this->validate_settings($sanitized);
        
        return $sanitized;
    }
    
    /**
     * Validate settings values
     */
    private function validate_settings($settings) {
        // Validate layout type
        $valid_layouts = array('sidebar', 'fullwidth', 'compact');
        if (!in_array($settings['layout_type'], $valid_layouts)) {
            $settings['layout_type'] = 'sidebar';
        }
        
        // Validate font family
        $valid_fonts = array('system', 'inter', 'roboto', 'open-sans');
        if (!in_array($settings['font_family'], $valid_fonts)) {
            $settings['font_family'] = 'system';
        }
        
        // Validate AI model
        $valid_models = array(
            'openai/gpt-3.5-turbo',
            'openai/gpt-4',
            'anthropic/claude-2',
            'anthropic/claude-instant-1'
        );
        if (!in_array($settings['ai_model'], $valid_models)) {
            $settings['ai_model'] = 'openai/gpt-3.5-turbo';
        }
        
        // Validate numeric ranges
        $settings['posts_per_page'] = max(1, min(100, $settings['posts_per_page']));
        $settings['posts_per_day'] = max(1, min(50, $settings['posts_per_day']));
        $settings['replies_per_post'] = max(0, min(20, $settings['replies_per_post']));
        $settings['max_ai_posts_per_hour'] = max(1, min(100, $settings['max_ai_posts_per_hour']));
        $settings['ai_content_quality_threshold'] = max(0.1, min(1.0, $settings['ai_content_quality_threshold']));
        
        // Validate colors
        if (!preg_match('/^#[a-f0-9]{6}$/i', $settings['primary_color'])) {
            $settings['primary_color'] = '#3b82f6';
        }
        if (!preg_match('/^#[a-f0-9]{6}$/i', $settings['secondary_color'])) {
            $settings['secondary_color'] = '#10b981';
        }
        
        // Validate email
        if (!is_email($settings['admin_notification_email'])) {
            $settings['admin_notification_email'] = get_option('admin_email');
        }
        
        // Ensure source_websites is not empty
        if (empty($settings['source_websites'])) {
            $settings['source_websites'] = array(get_site_url());
        }
        
        // Ensure post_topics is not empty
        if (empty($settings['post_topics'])) {
            $settings['post_topics'] = array('development', 'ai', 'community', 'tutorials');
        }
        
        return $settings;
    }
    
    /**
     * Get settings for export
     */
    public function export_settings() {
        $settings = $this->get_all();
        
        // Remove sensitive data from export
        $sensitive_keys = array(
            'openrouter_api_key',
            'captcha_secret_key',
            'admin_notification_email'
        );
        
        foreach ($sensitive_keys as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = '';
            }
        }
        
        return $settings;
    }
    
    /**
     * Import settings
     */
    public function import_settings($settings) {
        if (!is_array($settings)) {
            return new WP_Error('invalid_data', 'Settings must be an array');
        }
        
        // Merge with current settings to preserve sensitive data
        $current_settings = $this->get_all();
        $sensitive_keys = array(
            'openrouter_api_key',
            'captcha_secret_key'
        );
        
        foreach ($sensitive_keys as $key) {
            if (isset($current_settings[$key]) && !empty($current_settings[$key])) {
                $settings[$key] = $current_settings[$key];
            }
        }
        
        // Validate and update
        $result = $this->update_all($settings);
        
        if ($result) {
            return true;
        } else {
            return new WP_Error('update_failed', 'Failed to update settings');
        }
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset_to_defaults() {
        delete_option(self::OPTION_NAME);
        $this->settings = null;
        $this->set_default_options();
        
        do_action('ai_community_settings_reset');
        
        return true;
    }
    
    /**
     * Get settings grouped by category
     */
    public function get_settings_by_category() {
        $settings = $this->get_all();
        
        return array(
            'general' => array(
                'layout_type' => $settings['layout_type'],
                'primary_color' => $settings['primary_color'],
                'secondary_color' => $settings['secondary_color'],
                'font_family' => $settings['font_family'],
                'posts_per_page' => $settings['posts_per_page'],
                'enable_voting' => $settings['enable_voting'],
                'enable_comments' => $settings['enable_comments'],
                'enable_user_registration' => $settings['enable_user_registration'],
                'require_moderation' => $settings['require_moderation'],
                'show_on_homepage' => $settings['show_on_homepage']
            ),
            'ai_generation' => array(
                'ai_generation_enabled' => $settings['ai_generation_enabled'],
                'posts_per_day' => $settings['posts_per_day'],
                'replies_per_post' => $settings['replies_per_post'],
                'openrouter_api_key' => $settings['openrouter_api_key'],
                'ai_model' => $settings['ai_model'],
                'web_search_enabled' => $settings['web_search_enabled'],
                'source_websites' => $settings['source_websites'],
                'post_topics' => $settings['post_topics'],
                'ai_generation_schedule' => $settings['ai_generation_schedule'],
                'max_ai_posts_per_hour' => $settings['max_ai_posts_per_hour'],
                'ai_content_quality_threshold' => $settings['ai_content_quality_threshold']
            ),
            'content' => array(
                'max_post_length' => $settings['max_post_length'],
                'max_comment_length' => $settings['max_comment_length'],
                'enable_rich_editor' => $settings['enable_rich_editor'],
                'allow_html_in_posts' => $settings['allow_html_in_posts'],
                'enable_post_thumbnails' => $settings['enable_post_thumbnails'],
                'auto_generate_excerpts' => $settings['auto_generate_excerpts']
            ),
            'communities' => array(
                'default_community' => $settings['default_community'],
                'allow_community_creation' => $settings['allow_community_creation'],
                'max_communities_per_user' => $settings['max_communities_per_user'],
                'community_moderation_required' => $settings['community_moderation_required']
            ),
            'users' => array(
                'karma_system_enabled' => $settings['karma_system_enabled'],
                'karma_for_upvote' => $settings['karma_for_upvote'],
                'karma_for_downvote' => $settings['karma_for_downvote'],
                'karma_for_post' => $settings['karma_for_post'],
                'karma_for_comment' => $settings['karma_for_comment'],
                'min_karma_to_vote' => $settings['min_karma_to_vote'],
                'min_karma_to_post' => $settings['min_karma_to_post']
            ),
            'notifications' => array(
                'email_notifications' => $settings['email_notifications'],
                'notify_on_comment' => $settings['notify_on_comment'],
                'notify_on_vote' => $settings['notify_on_vote'],
                'notify_on_mention' => $settings['notify_on_mention'],
                'admin_notification_email' => $settings['admin_notification_email']
            ),
            'security' => array(
                'enable_captcha' => $settings['enable_captcha'],
                'captcha_provider' => $settings['captcha_provider'],
                'captcha_site_key' => $settings['captcha_site_key'],
                'captcha_secret_key' => $settings['captcha_secret_key'],
                'enable_rate_limiting' => $settings['enable_rate_limiting'],
                'max_posts_per_hour' => $settings['max_posts_per_hour'],
                'max_comments_per_hour' => $settings['max_comments_per_hour']
            ),
            'advanced' => array(
                'enable_rest_api' => $settings['enable_rest_api'],
                'enable_webhooks' => $settings['enable_webhooks'],
                'webhook_urls' => $settings['webhook_urls'],
                'cache_enabled' => $settings['cache_enabled'],
                'cache_duration' => $settings['cache_duration'],
                'cleanup_old_posts' => $settings['cleanup_old_posts'],
                'cleanup_old_posts_days' => $settings['cleanup_old_posts_days'],
                'debug_mode' => $settings['debug_mode'],
                'log_ai_requests' => $settings['log_ai_requests']
            )
        );
    }
    
    /**
     * Get setting field configuration
     */
    public function get_field_config($field_name) {
        $configs = array(
            'layout_type' => array(
                'type' => 'select',
                'label' => __('Layout Type', 'ai-community'),
                'description' => __('Choose the layout for community pages', 'ai-community'),
                'options' => array(
                    'sidebar' => __('With Sidebar', 'ai-community'),
                    'fullwidth' => __('Full Width', 'ai-community'),
                    'compact' => __('Compact', 'ai-community')
                )
            ),
            'primary_color' => array(
                'type' => 'color',
                'label' => __('Primary Color', 'ai-community'),
                'description' => __('Main brand color used throughout the community', 'ai-community')
            ),
            'secondary_color' => array(
                'type' => 'color',
                'label' => __('Secondary Color', 'ai-community'),
                'description' => __('Secondary accent color', 'ai-community')
            ),
            'font_family' => array(
                'type' => 'select',
                'label' => __('Font Family', 'ai-community'),
                'description' => __('Choose the font family for the community', 'ai-community'),
                'options' => array(
                    'system' => __('System Default', 'ai-community'),
                    'inter' => 'Inter',
                    'roboto' => 'Roboto',
                    'open-sans' => 'Open Sans'
                )
            ),
            'ai_generation_enabled' => array(
                'type' => 'checkbox',
                'label' => __('Enable AI Generation', 'ai-community'),
                'description' => __('Allow AI to generate posts and comments automatically', 'ai-community')
            ),
            'openrouter_api_key' => array(
                'type' => 'password',
                'label' => __('OpenRouter API Key', 'ai-community'),
                'description' => __('Your OpenRouter API key for AI content generation', 'ai-community'),
                'placeholder' => 'sk-or-v1-...'
            ),
            'posts_per_day' => array(
                'type' => 'number',
                'label' => __('Posts Per Day', 'ai-community'),
                'description' => __('Maximum number of AI-generated posts per day', 'ai-community'),
                'min' => 1,
                'max' => 50
            ),
            'source_websites' => array(
                'type' => 'textarea',
                'label' => __('Source Websites', 'ai-community'),
                'description' => __('URLs to scan for content (one per line)', 'ai-community'),
                'placeholder' => "https://example.com\nhttps://blog.example.com"
            ),
            'post_topics' => array(
                'type' => 'text',
                'label' => __('Post Topics', 'ai-community'),
                'description' => __('Comma-separated list of topics for AI posts', 'ai-community'),
                'placeholder' => 'development, ai, tutorials'
            )
        );
        
        return isset($configs[$field_name]) ? $configs[$field_name] : null;
    }
    
    /**
     * Filter default settings
     */
    public function filter_default_settings($defaults) {
        return wp_parse_args($defaults, $this->defaults);
    }
    
    /**
     * Check if a feature is enabled
     */
    public function is_enabled($feature) {
        $feature_map = array(
            'voting' => 'enable_voting',
            'comments' => 'enable_comments',
            'user_registration' => 'enable_user_registration',
            'ai_generation' => 'ai_generation_enabled',
            'rich_editor' => 'enable_rich_editor',
            'karma_system' => 'karma_system_enabled',
            'email_notifications' => 'email_notifications',
            'captcha' => 'enable_captcha',
            'rate_limiting' => 'enable_rate_limiting',
            'rest_api' => 'enable_rest_api',
            'webhooks' => 'enable_webhooks',
            'cache' => 'cache_enabled',
            'debug' => 'debug_mode'
        );
        
        if (isset($feature_map[$feature])) {
            return $this->get($feature_map[$feature], false);
        }
        
        return false;
    }
    
    /**
     * Get cache key for settings
     */
    private function get_cache_key($key = '') {
        return 'ai_community_settings_' . $key;
    }
    
    /**
     * Clear settings cache
     */
    public function clear_cache() {
        $this->settings = null;
        wp_cache_delete($this->get_cache_key(), 'ai_community');
    }
}