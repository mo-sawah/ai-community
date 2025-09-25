<?php
/**
 * AI Community Frontend Class
 * 
 * Basic frontend functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Community_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if not in admin
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_head', array($this, 'add_custom_css'));
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // This will be called by the main plugin class
        // Just ensure we don't cause any conflicts
    }
    
    /**
     * Add custom CSS based on settings
     */
    public function add_custom_css() {
        // Try to get settings safely
        $settings = $this->get_settings_safely();
        
        if ($settings) {
            $primary_color = $settings->get('primary_color', '#3b82f6');
            $secondary_color = $settings->get('secondary_color', '#10b981');
            
            echo "<style>
                :root {
                    --ai-primary-color: {$primary_color};
                    --ai-secondary-color: {$secondary_color};
                }
                .ai-community-container .btn-primary {
                    background-color: var(--ai-primary-color) !important;
                }
                .ai-community-container .btn-secondary {
                    background-color: var(--ai-secondary-color) !important;
                }
            </style>";
        }
    }
    
    /**
     * Safely get settings instance
     */
    private function get_settings_safely() {
        try {
            if (class_exists('AI_Community_Settings')) {
                return new AI_Community_Settings();
            }
        } catch (Exception $e) {
            error_log('AI Community Frontend: Could not load settings - ' . $e->getMessage());
        }
        
        return null;
    }
}