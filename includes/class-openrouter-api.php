<?php
/**
 * AI Community OpenRouter API Class
 * 
 * Handles integration with OpenRouter API for AI content generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Community_OpenRouter_API {
    
    /**
     * API endpoint
     */
    const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    
    /**
     * Settings instance
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = null;
    }

    private function get_settings() {
        if (!$this->settings) {
            $this->settings = new AI_Community_Settings();
        }
        return $this->settings;
    }
    
    /**
     * Generate content using OpenRouter API
     */
    public function generate_content($prompt, $options = array()) {
        $api_key = $this->get_settings()->get('openrouter_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenRouter API key is required', 'ai-community'));
        }
        
        // Check rate limits
        $rate_check = $this->check_rate_limits();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        $defaults = array(
            'model' => $this->get_settings()->get('ai_model', 'openai/gpt-3.5-turbo'),
            'max_tokens' => 1500,
            'temperature' => 0.8,
            'top_p' => 0.9,
            'frequency_penalty' => 0.1,
            'presence_penalty' => 0.1,
            'timeout' => 60
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Check cache first
        $cache_key = md5($prompt . serialize($options));
        $cached_response = $this->get_cached_response($cache_key);
        if ($cached_response !== false) {
            return $cached_response;
        }
        
        // Prepare messages
        $messages = array(
            array(
                'role' => 'system',
                'content' => $this->get_system_prompt()
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        $body = array(
            'model' => $options['model'],
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'top_p' => $options['top_p'],
            'frequency_penalty' => $options['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty']
        );
        
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => get_site_url(),
            'X-Title' => get_bloginfo('name') . ' - AI Community'
        );
        
        // Log request if debug mode is enabled
        if ($this->get_settings()->get('log_ai_requests')) {
            $this->log_request($body, 'generate_content');
        }
        
        $response = wp_remote_post(self::API_ENDPOINT, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => $options['timeout']
        ));
        
        if (is_wp_error($response)) {
            $this->log_error('Request failed: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_message = $this->parse_error_response($response_body, $response_code);
            $this->log_error('API error: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }
        
        $data = json_decode($response_body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            $this->log_error('Invalid response format: ' . $response_body);
            return new WP_Error('invalid_response', __('Invalid API response format', 'ai-community'));
        }
        
        $content = $data['choices'][0]['message']['content'];
        
        // Log successful response
        if ($this->get_settings()->get('log_ai_requests')) {
            $this->log_response($data, 'generate_content');
        }
        
        // Update usage stats
        if (isset($data['usage'])) {
            $this->update_usage_stats($data['usage']);
        }
        
        // Update last successful request time
        update_option('ai_community_last_successful_api_request', current_time('mysql'));
        
        // Validate content quality
        if (!$this->validate_content_quality($content)) {
            return new WP_Error('low_quality', __('Generated content did not meet quality standards', 'ai-community'));
        }
        
        $result = array(
            'content' => $content,
            'model' => $data['model'] ?? $options['model'],
            'usage' => $data['usage'] ?? array(),
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop'
        );
        
        // Cache the response
        $this->cache_response($cache_key, $result);
        
        return $result;
    }
    
    /**
     * Generate posts from website content
     */
    public function generate_posts_from_content($source_content, $count = 5) {
        $topics = implode(', ', $this->get_settings()->get('post_topics', array('development', 'ai', 'community')));
        $website_name = get_bloginfo('name');
        
        $prompt = "Based on the following recent content from {$website_name}, create {$count} engaging community discussion posts about topics like: {$topics}.\n\n";
        
        $prompt .= "Recent content:\n";
        foreach (array_slice($source_content, 0, 10) as $i => $content) {
            $prompt .= ($i + 1) . ". Title: {$content['title']}\n";
            if (!empty($content['content'])) {
                $prompt .= "   Content: " . substr(strip_tags($content['content']), 0, 300) . "...\n";
            }
            $prompt .= "   URL: {$content['url']}\n\n";
        }
        
        $prompt .= $this->get_post_generation_instructions($count);
        
        $result = $this->generate_content($prompt, array(
            'max_tokens' => 2000,
            'temperature' => 0.7
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $this->parse_generated_posts($result['content']);
    }
    
    /**
     * Generate replies for a post
     */
    public function generate_replies($post_data, $count = 3) {
        $prompt = "Generate {$count} natural, engaging replies to this community post:\n\n";
        $prompt .= "Title: {$post_data['title']}\n";
        $prompt .= "Content: {$post_data['content']}\n";
        $prompt .= "Community: {$post_data['community']}\n\n";
        $prompt .= $this->get_reply_generation_instructions($count);
        
        $result = $this->generate_content($prompt, array(
            'max_tokens' => 1000,
            'temperature' => 0.8
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $this->parse_generated_replies($result['content']);
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $test_prompt = "Respond with 'Hello from AI Community!' to test the connection.";
        
        $result = $this->generate_content($test_prompt, array(
            'max_tokens' => 50,
            'temperature' => 0.1
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $response_content = strtolower(trim($result['content']));
        if (strpos($response_content, 'hello') !== false) {
            return array(
                'success' => true,
                'message' => __('API connection successful', 'ai-community'),
                'model' => $result['model'],
                'usage' => $result['usage']
            );
        } else {
            return new WP_Error('unexpected_response', __('API responded but with unexpected content', 'ai-community'));
        }
    }
    
    /**
     * Moderate content using AI
     */
    public function moderate_content($content, $context = '') {
        $prompt = "Please analyze this community post content for appropriateness. ";
        $prompt .= "Check for: spam, offensive language, harassment, misinformation, or off-topic content. ";
        $prompt .= "Respond with JSON: {\"appropriate\": true/false, \"reason\": \"explanation\", \"confidence\": 0.0-1.0}\n\n";
        $prompt .= "Content to moderate:\n{$content}";
        
        if (!empty($context)) {
            $prompt .= "\n\nContext: {$context}";
        }
        
        $result = $this->generate_content($prompt, array(
            'max_tokens' => 200,
            'temperature' => 0.1
        ));
        
        if (is_wp_error($result)) {
            return array(
                'appropriate' => true,
                'reason' => 'Moderation check failed',
                'confidence' => 0.0
            );
        }
        
        // Parse JSON response
        if (preg_match('/\{.*\}/s', $result['content'], $matches)) {
            $moderation = json_decode($matches[0], true);
            if (is_array($moderation)) {
                return array(
                    'appropriate' => (bool) ($moderation['appropriate'] ?? true),
                    'reason' => sanitize_text_field($moderation['reason'] ?? ''),
                    'confidence' => (float) ($moderation['confidence'] ?? 0.0)
                );
            }
        }
        
        return array(
            'appropriate' => true,
            'reason' => 'Unable to parse moderation response',
            'confidence' => 0.0
        );
    }
    
    /**
     * Get system prompt for AI
     */
    private function get_system_prompt() {
        $website_name = get_bloginfo('name');
        $website_description = get_bloginfo('description');
        
        return "You are an AI assistant helping to create engaging community content for {$website_name}. " .
               "Website description: {$website_description}. " .
               "Create natural, human-like content that encourages discussion and engagement. " .
               "Always maintain a helpful, friendly, and professional tone. " .
               "Avoid controversial topics and focus on constructive discussions.";
    }
    
    /**
     * Get post generation instructions
     */
    private function get_post_generation_instructions($count) {
        $communities = $this->get_available_communities();
        
        $instructions = "Format your response as valid JSON with this structure:\n";
        $instructions .= "{\n";
        $instructions .= '  "posts": [' . "\n";
        $instructions .= '    {' . "\n";
        $instructions .= '      "title": "Engaging post title that asks a question or starts discussion",' . "\n";
        $instructions .= '      "content": "Post content that provides context and encourages replies (150-300 words)",' . "\n";
        $instructions .= '      "community": "community_slug",' . "\n";
        $instructions .= '      "tags": ["tag1", "tag2", "tag3"],' . "\n";
        $instructions .= '      "excerpt": "Brief summary (50-100 words)"' . "\n";
        $instructions .= '    }' . "\n";
        $instructions .= '  ]' . "\n";
        $instructions .= "}\n\n";
        
        $instructions .= "Available communities: " . implode(', ', $communities) . "\n";
        $instructions .= "Guidelines:\n";
        $instructions .= "- Make posts sound natural and human-like\n";
        $instructions .= "- End posts with questions to encourage replies\n";
        $instructions .= "- Vary the communities and topics\n";
        $instructions .= "- Include relevant tags (3-5 per post)\n";
        $instructions .= "- Focus on practical, helpful content\n";
        $instructions .= "- Avoid overly promotional language\n";
        
        return $instructions;
    }
    
    /**
     * Get reply generation instructions
     */
    private function get_reply_generation_instructions($count) {
        $instructions = "Generate {$count} different reply perspectives as JSON:\n";
        $instructions .= "{\n";
        $instructions .= '  "replies": [' . "\n";
        $instructions .= '    {' . "\n";
        $instructions .= '      "content": "Thoughtful reply that adds value to the discussion",' . "\n";
        $instructions .= '      "tone": "helpful|questioning|sharing_experience"' . "\n";
        $instructions .= '    }' . "\n";
        $instructions .= '  ]' . "\n";
        $instructions .= "}\n\n";
        
        $instructions .= "Guidelines:\n";
        $instructions .= "- Keep replies between 50-150 words\n";
        $instructions .= "- Provide different perspectives or experiences\n";
        $instructions .= "- Be constructive and add value\n";
        $instructions .= "- Sound natural and conversational\n";
        $instructions .= "- Ask follow-up questions when appropriate\n";
        
        return $instructions;
    }
    
    /**
     * Parse generated posts from AI response
     */
    private function parse_generated_posts($content) {
        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            
            if ($json && isset($json['posts']) && is_array($json['posts'])) {
                $posts = array();
                
                foreach ($json['posts'] as $post_data) {
                    if (empty($post_data['title']) || empty($post_data['content'])) {
                        continue;
                    }
                    
                    $posts[] = array(
                        'title' => sanitize_text_field($post_data['title']),
                        'content' => wp_kses_post($post_data['content']),
                        'excerpt' => sanitize_textarea_field($post_data['excerpt'] ?? ''),
                        'community' => sanitize_text_field($post_data['community'] ?? 'general'),
                        'tags' => is_array($post_data['tags']) ? 
                                 implode(',', array_map('sanitize_text_field', $post_data['tags'])) : 
                                 sanitize_text_field($post_data['tags'] ?? ''),
                    );
                }
                
                return $posts;
            }
        }
        
        // Fallback: try to parse as simple text
        return $this->parse_simple_text_posts($content);
    }
    
    /**
     * Parse generated replies from AI response
     */
    private function parse_generated_replies($content) {
        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            
            if ($json && isset($json['replies']) && is_array($json['replies'])) {
                $replies = array();
                
                foreach ($json['replies'] as $reply_data) {
                    if (empty($reply_data['content'])) {
                        continue;
                    }
                    
                    $replies[] = array(
                        'content' => wp_kses_post($reply_data['content']),
                        'tone' => sanitize_text_field($reply_data['tone'] ?? 'helpful')
                    );
                }
                
                return $replies;
            }
        }
        
        // Fallback: split by lines and treat as individual replies
        $lines = explode("\n", $content);
        $replies = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && strlen($line) > 10) {
                // Remove numbering and formatting
                $line = preg_replace('/^\d+\.\s*/', '', $line);
                $line = preg_replace('/^[-*]\s*/', '', $line);
                
                if (strlen($line) > 20) {
                    $replies[] = array(
                        'content' => wp_kses_post($line),
                        'tone' => 'helpful'
                    );
                }
            }
        }
        
        return array_slice($replies, 0, 5);
    }
    
    /**
     * Parse simple text posts (fallback)
     */
    private function parse_simple_text_posts($content) {
        $sections = preg_split('/(?:^|\n)(?:\d+\.|Title:|##|\*\*)/m', $content);
        $posts = array();
        
        foreach ($sections as $section) {
            $section = trim($section);
            if (strlen($section) < 50) continue;
            
            $lines = explode("\n", $section);
            $title = trim($lines[0]);
            $content_text = implode("\n", array_slice($lines, 1));
            
            if (!empty($title) && !empty($content_text)) {
                $posts[] = array(
                    'title' => sanitize_text_field($title),
                    'content' => wp_kses_post($content_text),
                    'excerpt' => wp_trim_words($content_text, 20),
                    'community' => 'general',
                    'tags' => 'ai-generated'
                );
            }
        }
        
        return array_slice($posts, 0, 10);
    }
    
    /**
     * Validate content quality
     */
    private function validate_content_quality($content) {
        $threshold = $this->get_settings()->get('ai_content_quality_threshold', 0.7);
        $content = trim($content);
        
        // Check minimum length
        if (strlen($content) < 50) {
            return false;
        }
        
        // Check for reasonable sentence structure
        $sentences = explode('.', $content);
        if (count($sentences) < 2) {
            return false;
        }
        
        // Check for repeated phrases
        $words = explode(' ', strtolower($content));
        $word_count = array_count_values($words);
        $total_words = count($words);
        
        foreach ($word_count as $word => $count) {
            if (strlen($word) > 3 && ($count / $total_words) > 0.2) {
                return false;
            }
        }
        
        // Check for AI-generated phrases that indicate low quality
        $low_quality_phrases = array(
            'as an ai',
            'i cannot',
            'i apologize',
            'please note that',
            'it\'s worth noting',
            'it is important to note'
        );
        
        $content_lower = strtolower($content);
        foreach ($low_quality_phrases as $phrase) {
            if (strpos($content_lower, $phrase) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get available communities
     */
    private function get_available_communities() {
        global $wpdb;
        $database = new AI_Community_Database();
        $tables = $database->get_table_names();
        
        $communities = $wpdb->get_col(
            "SELECT slug FROM {$tables['communities']} WHERE status = 'active' ORDER BY name"
        );
        
        return !empty($communities) ? $communities : array('general', 'development', 'ai', 'help');
    }
    
    /**
     * Parse error response
     */
    private function parse_error_response($response_body, $response_code) {
        $data = json_decode($response_body, true);
        
        if ($data && isset($data['error']['message'])) {
            return $data['error']['message'];
        }
        
        $error_messages = array(
            400 => __('Bad request - check your API key and request format', 'ai-community'),
            401 => __('Unauthorized - invalid API key', 'ai-community'),
            403 => __('Forbidden - API key does not have required permissions', 'ai-community'),
            404 => __('Not found - invalid API endpoint', 'ai-community'),
            429 => __('Rate limit exceeded - too many requests', 'ai-community'),
            500 => __('Internal server error - please try again later', 'ai-community'),
            502 => __('Bad gateway - service temporarily unavailable', 'ai-community'),
            503 => __('Service unavailable - please try again later', 'ai-community')
        );
        
        return isset($error_messages[$response_code]) ? 
               $error_messages[$response_code] : 
               sprintf(__('API error: HTTP %d', 'ai-community'), $response_code);
    }
    
    /**
     * Log API request
     */
    private function log_request($request_data, $context = '') {
        if (!$this->get_settings()->get('log_ai_requests')) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'context' => $context,
            'model' => $request_data['model'] ?? 'unknown',
            'max_tokens' => $request_data['max_tokens'] ?? 0,
            'temperature' => $request_data['temperature'] ?? 0,
            'message_count' => count($request_data['messages'] ?? array()),
            'user_id' => get_current_user_id()
        );
        
        error_log('AI Community API Request: ' . json_encode($log_entry));
    }
    
    /**
     * Log API response
     */
    private function log_response($response_data, $context = '') {
        if (!$this->get_settings()->get('log_ai_requests')) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'context' => $context,
            'model' => $response_data['model'] ?? 'unknown',
            'usage' => $response_data['usage'] ?? array(),
            'finish_reason' => $response_data['choices'][0]['finish_reason'] ?? 'unknown',
            'content_length' => strlen($response_data['choices'][0]['message']['content'] ?? '')
        );
        
        error_log('AI Community API Response: ' . json_encode($log_entry));
    }
    
    /**
     * Log API error
     */
    private function log_error($error_message, $context = '') {
        error_log("AI Community API Error [{$context}]: {$error_message}");
        $this->store_error_for_dashboard($error_message, $context);
    }
    
    /**
     * Store error for dashboard display
     */
    private function store_error_for_dashboard($error_message, $context = '') {
        $errors = get_option('ai_community_api_errors', array());
        
        $errors[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $error_message,
            'context' => $context,
            'user_id' => get_current_user_id()
        );
        
        // Keep only last 50 errors
        $errors = array_slice($errors, -50);
        update_option('ai_community_api_errors', $errors);
    }
    
    /**
     * Get recent API errors for dashboard
     */
    public function get_recent_errors($limit = 10) {
        $errors = get_option('ai_community_api_errors', array());
        return array_slice(array_reverse($errors), 0, $limit);
    }
    
    /**
     * Clear API errors
     */
    public function clear_errors() {
        delete_option('ai_community_api_errors');
    }
    
    /**
     * Get API usage statistics
     */
    public function get_usage_stats($days = 30) {
        $stats = get_option('ai_community_api_stats', array());
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $filtered_stats = array();
        foreach ($stats as $date => $day_stats) {
            if ($date >= $cutoff_date) {
                $filtered_stats[$date] = $day_stats;
            }
        }
        
        return $filtered_stats;
    }
    
    /**
     * Update API usage statistics
     */
    public function update_usage_stats($usage_data) {
        $stats = get_option('ai_community_api_stats', array());
        $today = date('Y-m-d');
        
        if (!isset($stats[$today])) {
            $stats[$today] = array(
                'requests' => 0,
                'tokens_used' => 0,
                'tokens_generated' => 0,
                'cost_estimate' => 0
            );
        }
        
        $stats[$today]['requests']++;
        
        if (isset($usage_data['prompt_tokens'])) {
            $stats[$today]['tokens_used'] += $usage_data['prompt_tokens'];
        }
        
        if (isset($usage_data['completion_tokens'])) {
            $stats[$today]['tokens_generated'] += $usage_data['completion_tokens'];
        }
        
        // Estimate cost
        $model_costs = array(
            'openai/gpt-3.5-turbo' => 0.002,
            'openai/gpt-4' => 0.03,
            'anthropic/claude-2' => 0.01
        );
        
        $model = $this->get_settings()->get('ai_model', 'openai/gpt-3.5-turbo');
        $cost_per_1k = $model_costs[$model] ?? 0.002;
        $total_tokens = ($usage_data['prompt_tokens'] ?? 0) + ($usage_data['completion_tokens'] ?? 0);
        $stats[$today]['cost_estimate'] += ($total_tokens / 1000) * $cost_per_1k;
        
        // Keep only last 90 days
        $cutoff_date = date('Y-m-d', strtotime('-90 days'));
        foreach ($stats as $date => $day_stats) {
            if ($date < $cutoff_date) {
                unset($stats[$date]);
            }
        }
        
        update_option('ai_community_api_stats', $stats);
    }
    
    /**
     * Estimate monthly cost
     */
    public function estimate_monthly_cost() {
        $daily_stats = $this->get_usage_stats(7);
        
        if (empty($daily_stats)) {
            return 0;
        }
        
        $avg_daily_cost = array_sum(array_column($daily_stats, 'cost_estimate')) / count($daily_stats);
        return $avg_daily_cost * 30;
    }
    
    /**
     * Check API rate limits
     */
    public function check_rate_limits() {
        $max_per_hour = $this->get_settings()->get('max_ai_posts_per_hour', 10);
        $current_hour = date('Y-m-d H:00:00');
        
        $recent_requests = get_transient('ai_community_api_requests_' . $current_hour);
        
        if ($recent_requests === false) {
            $recent_requests = 0;
        }
        
        if ($recent_requests >= $max_per_hour) {
            return new WP_Error('rate_limit_exceeded', 
                sprintf(__('Rate limit exceeded. Maximum %d requests per hour.', 'ai-community'), $max_per_hour)
            );
        }
        
        // Increment counter
        set_transient('ai_community_api_requests_' . $current_hour, $recent_requests + 1, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Get cached response
     */
    private function get_cached_response($cache_key) {
        if (!$this->get_settings()->get('cache_enabled')) {
            return false;
        }
        
        return get_transient('ai_community_api_' . md5($cache_key));
    }
    
    /**
     * Cache response
     */
    private function cache_response($cache_key, $response, $duration = null) {
        if (!$this->get_settings()->get('cache_enabled')) {
            return;
        }
        
        $duration = $duration ?? $this->get_settings()->get('cache_duration', 3600);
        set_transient('ai_community_api_' . md5($cache_key), $response, $duration);
    }
    
    /**
     * Get supported models
     */
    public function get_supported_models() {
        return array(
            'openai/gpt-3.5-turbo' => array(
                'name' => 'GPT-3.5 Turbo',
                'cost_per_1k' => 0.002,
                'max_tokens' => 4096,
                'description' => __('Fast and affordable model for general content generation', 'ai-community')
            ),
            'openai/gpt-4' => array(
                'name' => 'GPT-4',
                'cost_per_1k' => 0.03,
                'max_tokens' => 8192,
                'description' => __('More capable model for complex content creation', 'ai-community')
            ),
            'openai/gpt-4-turbo' => array(
                'name' => 'GPT-4 Turbo',
                'cost_per_1k' => 0.01,
                'max_tokens' => 128000,
                'description' => __('Latest GPT-4 model with larger context window', 'ai-community')
            ),
            'anthropic/claude-2' => array(
                'name' => 'Claude 2',
                'cost_per_1k' => 0.01,
                'max_tokens' => 100000,
                'description' => __('Excellent for long-form content and detailed discussions', 'ai-community')
            ),
            'anthropic/claude-instant-1' => array(
                'name' => 'Claude Instant',
                'cost_per_1k' => 0.005,
                'max_tokens' => 100000,
                'description' => __('Fast and efficient model for quick responses', 'ai-community')
            ),
            'meta-llama/llama-2-70b-chat' => array(
                'name' => 'Llama 2 70B',
                'cost_per_1k' => 0.0007,
                'max_tokens' => 4096,
                'description' => __('Open-source model good for general conversations', 'ai-community')
            ),
            'mistralai/mistral-7b-instruct' => array(
                'name' => 'Mistral 7B Instruct',
                'cost_per_1k' => 0.0002,
                'max_tokens' => 32768,
                'description' => __('Efficient model for instruction following', 'ai-community')
            )
        );
    }
    
    /**
     * Generate topic suggestions based on website content
     */
    public function generate_topic_suggestions($source_content, $count = 10) {
        $prompt = "Based on this website content, suggest {$count} engaging discussion topics for a community forum:\n\n";
        
        foreach (array_slice($source_content, 0, 5) as $content) {
            $prompt .= "Title: {$content['title']}\n";
            if (!empty($content['content'])) {
                $prompt .= "Content: " . substr(strip_tags($content['content']), 0, 200) . "...\n\n";
            }
        }
        
        $prompt .= "Return topics as a JSON array of strings, focusing on questions that would generate good discussions.";
        
        $result = $this->generate_content($prompt, array(
            'max_tokens' => 500,
            'temperature' => 0.7
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Parse JSON response
        if (preg_match('/\[.*\]/s', $result['content'], $matches)) {
            $topics = json_decode($matches[0], true);
            if (is_array($topics)) {
                return array_map('sanitize_text_field', $topics);
            }
        }
        
        return array();
    }
    
    /**
     * Generate personalized content based on user preferences
     */
    public function generate_personalized_content($user_preferences, $recent_activity, $options = array()) {
        $prompt = "Create personalized community content based on user preferences and activity:\n\n";
        
        $prompt .= "User interests: " . implode(', ', $user_preferences['interests'] ?? array()) . "\n";
        $prompt .= "Preferred topics: " . implode(', ', $user_preferences['topics'] ?? array()) . "\n";
        $prompt .= "Activity level: " . ($user_preferences['activity_level'] ?? 'moderate') . "\n\n";
        
        $prompt .= "Recent activity:\n";
        foreach (array_slice($recent_activity, 0, 5) as $activity) {
            $prompt .= "- {$activity['type']}: {$activity['content']}\n";
        }
        
        $prompt .= "\nGenerate 3 personalized post suggestions that would interest this user.";
        
        return $this->generate_content($prompt, array_merge($options, array(
            'max_tokens' => 1000,
            'temperature' => 0.8
        )));
    }
    
    /**
     * Batch generate multiple content pieces
     */
    public function batch_generate($requests, $options = array()) {
        $results = array();
        $delay = $options['delay'] ?? 1; // Seconds between requests
        
        foreach ($requests as $index => $request) {
            if ($index > 0 && $delay > 0) {
                sleep($delay); // Rate limiting
            }
            
            $result = $this->generate_content($request['prompt'], $request['options'] ?? array());
            
            $results[] = array(
                'request' => $request,
                'result' => $result,
                'success' => !is_wp_error($result)
            );
            
            
            // Check rate limits
            $rate_check = $this->check_rate_limits();
            if (is_wp_error($rate_check)) {
                // Stop batch if rate limited
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Get content quality score
     */
    public function get_content_quality_score($content) {
        $score = 1.0;
        
        // Length check
        if (strlen($content) < 50) {
            $score -= 0.3;
        } elseif (strlen($content) < 100) {
            $score -= 0.1;
        }
        
        // Sentence structure
        $sentences = explode('.', $content);
        $sentence_count = count(array_filter($sentences, 'trim'));
        if ($sentence_count < 2) {
            $score -= 0.2;
        }
        
        // Word variety
        $words = array_filter(explode(' ', strtolower($content)));
        $unique_words = array_unique($words);
        $word_variety = count($unique_words) / count($words);
        if ($word_variety < 0.3) {
            $score -= 0.2;
        }
        
        // Check for AI-generated markers
        $ai_markers = array('as an ai', 'i cannot', 'i apologize', 'please note');
        foreach ($ai_markers as $marker) {
            if (stripos($content, $marker) !== false) {
                $score -= 0.4;
                break;
            }
        }
        
        return max(0.0, min(1.0, $score));
    }
    
    /**
     * Get API health status
     */
    public function get_api_health() {
        $health = array(
            'status' => 'unknown',
            'last_successful_request' => get_option('ai_community_last_successful_api_request'),
            'recent_errors' => $this->get_recent_errors(5),
            'rate_limit_status' => $this->get_rate_limit_status(),
            'estimated_monthly_cost' => $this->estimate_monthly_cost()
        );
        
        // Determine overall health status
        $recent_errors = count($health['recent_errors']);
        $last_success = strtotime($health['last_successful_request']);
        $hours_since_success = $last_success ? (time() - $last_success) / 3600 : 999;
        
        if ($recent_errors === 0 && $hours_since_success < 24) {
            $health['status'] = 'healthy';
        } elseif ($recent_errors < 3 && $hours_since_success < 48) {
            $health['status'] = 'warning';
        } else {
            $health['status'] = 'error';
        }
        
        return $health;
    }
    
    /**
     * Get rate limit status
     */
    private function get_rate_limit_status() {
        $current_hour = date('Y-m-d H:00:00');
        $requests_this_hour = get_transient('ai_community_api_requests_' . $current_hour) ?: 0;
        $max_per_hour = $this->get_settings()->get('max_ai_posts_per_hour', 10);
        
        return array(
            'requests_this_hour' => $requests_this_hour,
            'max_per_hour' => $max_per_hour,
            'remaining' => max(0, $max_per_hour - $requests_this_hour),
            'reset_time' => date('Y-m-d H:59:59')
        );
    }
    
    /**
     * Clean up old logs and cache
     */
    public function cleanup() {
        // Clean up old error logs
        $errors = get_option('ai_community_api_errors', array());
        if (count($errors) > 100) {
            $errors = array_slice($errors, -50);
            update_option('ai_community_api_errors', $errors);
        }
        
        // Clean up old stats
        $stats = get_option('ai_community_api_stats', array());
        $cutoff_date = date('Y-m-d', strtotime('-90 days'));
        foreach ($stats as $date => $day_stats) {
            if ($date < $cutoff_date) {
                unset($stats[$date]);
            }
        }
        update_option('ai_community_api_stats', $stats);
        
        // Clean up old cache entries
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_ai_community_api_%' 
             AND option_name NOT LIKE '_transient_timeout_%'"
        );
    }
    
    /**
     * Export API logs for debugging
     */
    public function export_logs($format = 'json') {
        $data = array(
            'errors' => get_option('ai_community_api_errors', array()),
            'stats' => get_option('ai_community_api_stats', array()),
            'settings' => array(
                'model' => $this->get_settings()->get('ai_model'),
                'enabled' => $this->get_settings()->get('ai_generation_enabled'),
                'posts_per_day' => $this->get_settings()->get('posts_per_day'),
                'web_search_enabled' => $this->get_settings()->get('web_search_enabled')
            ),
            'export_time' => current_time('mysql'),
            'plugin_version' => AI_COMMUNITY_VERSION
        );
        
        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
            // Convert to CSV format for errors
            $csv = "Timestamp,Message,Context,User ID\n";
            foreach ($data['errors'] as $error) {
                $csv .= sprintf('"%s","%s","%s",%d' . "\n",
                    $error['timestamp'],
                    str_replace('"', '""', $error['message']),
                    $error['context'],
                    $error['user_id']
                );
            }
            return $csv;
        }
        
        return $data;
    }
    
    /**
     * Perform web search (placeholder for future implementation)
     */
    private function perform_web_search($query, $num_results = 5) {
        // Placeholder for web search integration
        // In production, integrate with search APIs like SerpAPI, Google Custom Search, etc.
        return array(
            array(
                'title' => 'Sample Search Result',
                'snippet' => 'This would be a real search result snippet from a search API.',
                'url' => 'https://example.com'
            )
        );
    }
    
    /**
     * Generate content with web search enhancement
     */
    public function generate_content_with_web_search($prompt, $search_queries = array(), $options = array()) {
        if (!$this->get_settings()->get('web_search_enabled') || empty($search_queries)) {
            return $this->generate_content($prompt, $options);
        }

        // Perform web searches first
        $search_results = array();
        foreach ($search_queries as $query) {
            $results = $this->perform_web_search($query);
            if (!empty($results)) {
                $search_results[] = array(
                    'query' => $query,
                    'results' => $results
                );
            }
        }

        // Enhance prompt with search results
        if (!empty($search_results)) {
            $enhanced_prompt = $prompt . "\n\nAdditional context from web search:\n";
            foreach ($search_results as $search) {
                $enhanced_prompt .= "\nQuery: {$search['query']}\n";
                foreach (array_slice($search['results'], 0, 3) as $result) {
                    $enhanced_prompt .= "- {$result['title']}: {$result['snippet']}\n";
                }
            }
            $prompt = $enhanced_prompt;
        }

        return $this->generate_content($prompt, $options);
    }
}