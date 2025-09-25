<?php
/**
 * AI Community Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$this->render_admin_header(__('Settings', 'ai-community'), $current_tab, 'ai-community-settings');
?>

<div class="ai-community-settings-page">
    <form method="post" action="options.php">
        <?php settings_fields('ai_community_settings'); ?>
        
        <div class="settings-content">
            <?php if ($current_tab === 'general'): ?>
                <!-- General Settings -->
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="layout_type"><?php _e('Layout Type', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <select id="layout_type" name="ai_community_settings[layout_type]">
                                    <option value="sidebar" <?php selected($settings['general']['layout_type'], 'sidebar'); ?>><?php _e('With Sidebar', 'ai-community'); ?></option>
                                    <option value="fullwidth" <?php selected($settings['general']['layout_type'], 'fullwidth'); ?>><?php _e('Full Width', 'ai-community'); ?></option>
                                    <option value="compact" <?php selected($settings['general']['layout_type'], 'compact'); ?>><?php _e('Compact', 'ai-community'); ?></option>
                                </select>
                                <p class="description"><?php _e('Choose the layout for community pages.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="primary_color"><?php _e('Primary Color', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="primary_color" name="ai_community_settings[primary_color]" value="<?php echo esc_attr($settings['general']['primary_color']); ?>" class="color-picker">
                                <p class="description"><?php _e('Main brand color used throughout the community.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="secondary_color"><?php _e('Secondary Color', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="secondary_color" name="ai_community_settings[secondary_color]" value="<?php echo esc_attr($settings['general']['secondary_color']); ?>" class="color-picker">
                                <p class="description"><?php _e('Secondary accent color.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="posts_per_page"><?php _e('Posts Per Page', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="posts_per_page" name="ai_community_settings[posts_per_page]" value="<?php echo esc_attr($settings['general']['posts_per_page']); ?>" min="5" max="50" class="small-text">
                                <p class="description"><?php _e('Number of posts to display per page.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Features', 'ai-community'); ?></th>
                            <td>
                                <fieldset>
                                    <label for="enable_voting">
                                        <input type="checkbox" id="enable_voting" name="ai_community_settings[enable_voting]" value="1" <?php checked($settings['general']['enable_voting']); ?>>
                                        <?php _e('Enable Voting', 'ai-community'); ?>
                                    </label>
                                    <br>
                                    <label for="enable_comments">
                                        <input type="checkbox" id="enable_comments" name="ai_community_settings[enable_comments]" value="1" <?php checked($settings['general']['enable_comments']); ?>>
                                        <?php _e('Enable Comments', 'ai-community'); ?>
                                    </label>
                                    <br>
                                    <label for="enable_user_registration">
                                        <input type="checkbox" id="enable_user_registration" name="ai_community_settings[enable_user_registration]" value="1" <?php checked($settings['general']['enable_user_registration']); ?>>
                                        <?php _e('Enable User Registration', 'ai-community'); ?>
                                    </label>
                                    <br>
                                    <label for="require_moderation">
                                        <input type="checkbox" id="require_moderation" name="ai_community_settings[require_moderation]" value="1" <?php checked($settings['general']['require_moderation']); ?>>
                                        <?php _e('Require Content Moderation', 'ai-community'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'ai_generation'): ?>
                <!-- AI Generation Settings -->
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="ai_generation_enabled"><?php _e('Enable AI Generation', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <label for="ai_generation_enabled">
                                    <input type="checkbox" id="ai_generation_enabled" name="ai_community_settings[ai_generation_enabled]" value="1" <?php checked($settings['ai_generation']['ai_generation_enabled']); ?>>
                                    <?php _e('Allow AI to generate posts and comments automatically', 'ai-community'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="openrouter_api_key"><?php _e('OpenRouter API Key', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="openrouter_api_key" name="ai_community_settings[openrouter_api_key]" value="<?php echo esc_attr($settings['ai_generation']['openrouter_api_key']); ?>" class="regular-text">
                                <button type="button" id="test-api-key" class="button"><?php _e('Test Connection', 'ai-community'); ?></button>
                                <p class="description">
                                    <?php _e('Your OpenRouter API key for AI content generation.', 'ai-community'); ?>
                                    <a href="https://openrouter.ai/keys" target="_blank"><?php _e('Get your API key', 'ai-community'); ?></a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ai_model"><?php _e('AI Model', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <select id="ai_model" name="ai_community_settings[ai_model]">
                                    <option value="openai/gpt-3.5-turbo" <?php selected($settings['ai_generation']['ai_model'], 'openai/gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                                    <option value="openai/gpt-4" <?php selected($settings['ai_generation']['ai_model'], 'openai/gpt-4'); ?>>GPT-4</option>
                                    <option value="anthropic/claude-2" <?php selected($settings['ai_generation']['ai_model'], 'anthropic/claude-2'); ?>>Claude 2</option>
                                    <option value="anthropic/claude-instant-1" <?php selected($settings['ai_generation']['ai_model'], 'anthropic/claude-instant-1'); ?>>Claude Instant</option>
                                </select>
                                <p class="description"><?php _e('Choose the AI model for content generation.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="posts_per_day"><?php _e('Posts Per Day', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="posts_per_day" name="ai_community_settings[posts_per_day]" value="<?php echo esc_attr($settings['ai_generation']['posts_per_day']); ?>" min="1" max="50" class="small-text">
                                <p class="description"><?php _e('Maximum number of AI-generated posts per day.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="replies_per_post"><?php _e('Replies Per Post', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="replies_per_post" name="ai_community_settings[replies_per_post]" value="<?php echo esc_attr($settings['ai_generation']['replies_per_post']); ?>" min="0" max="20" class="small-text">
                                <p class="description"><?php _e('Maximum number of AI replies to generate per post.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="source_websites"><?php _e('Source Websites', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <textarea id="source_websites" name="ai_community_settings[source_websites]" rows="5" cols="50" class="large-text"><?php echo esc_textarea(implode("\n", $settings['ai_generation']['source_websites'])); ?></textarea>
                                <p class="description"><?php _e('URLs to scan for content inspiration (one per line).', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="post_topics"><?php _e('Post Topics', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="post_topics" name="ai_community_settings[post_topics]" value="<?php echo esc_attr(implode(', ', $settings['ai_generation']['post_topics'])); ?>" class="large-text">
                                <p class="description"><?php _e('Comma-separated list of topics for AI posts.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ai_generation_schedule"><?php _e('Generation Schedule', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <select id="ai_generation_schedule" name="ai_community_settings[ai_generation_schedule]">
                                    <option value="hourly" <?php selected($settings['ai_generation']['ai_generation_schedule'], 'hourly'); ?>><?php _e('Every Hour', 'ai-community'); ?></option>
                                    <option value="twicedaily" <?php selected($settings['ai_generation']['ai_generation_schedule'], 'twicedaily'); ?>><?php _e('Twice Daily', 'ai-community'); ?></option>
                                    <option value="daily" <?php selected($settings['ai_generation']['ai_generation_schedule'], 'daily'); ?>><?php _e('Daily', 'ai-community'); ?></option>
                                    <option value="manual" <?php selected($settings['ai_generation']['ai_generation_schedule'], 'manual'); ?>><?php _e('Manual Only', 'ai-community'); ?></option>
                                </select>
                                <p class="description"><?php _e('How often to automatically generate content.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'communities'): ?>
                <!-- Communities Settings -->
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="default_community"><?php _e('Default Community', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <select id="default_community" name="ai_community_settings[default_community]">
                                    <option value="general" <?php selected($settings['communities']['default_community'], 'general'); ?>><?php _e('General', 'ai-community'); ?></option>
                                    <option value="development" <?php selected($settings['communities']['default_community'], 'development'); ?>><?php _e('Development', 'ai-community'); ?></option>
                                    <option value="ai" <?php selected($settings['communities']['default_community'], 'ai'); ?>><?php _e('AI', 'ai-community'); ?></option>
                                </select>
                                <p class="description"><?php _e('Default community for new posts.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="allow_community_creation"><?php _e('Allow Community Creation', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <label for="allow_community_creation">
                                    <input type="checkbox" id="allow_community_creation" name="ai_community_settings[allow_community_creation]" value="1" <?php checked($settings['communities']['allow_community_creation']); ?>>
                                    <?php _e('Allow users to create new communities', 'ai-community'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'users'): ?>
                <!-- Users & Karma Settings -->
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="karma_system_enabled"><?php _e('Karma System', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <label for="karma_system_enabled">
                                    <input type="checkbox" id="karma_system_enabled" name="ai_community_settings[karma_system_enabled]" value="1" <?php checked($settings['users']['karma_system_enabled']); ?>>
                                    <?php _e('Enable karma system for users', 'ai-community'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Karma Values', 'ai-community'); ?></th>
                            <td>
                                <fieldset>
                                    <label for="karma_for_post">
                                        <?php _e('Creating a post:', 'ai-community'); ?>
                                        <input type="number" id="karma_for_post" name="ai_community_settings[karma_for_post]" value="<?php echo esc_attr($settings['users']['karma_for_post']); ?>" class="small-text">
                                        <?php _e('points', 'ai-community'); ?>
                                    </label>
                                    <br><br>
                                    <label for="karma_for_comment">
                                        <?php _e('Creating a comment:', 'ai-community'); ?>
                                        <input type="number" id="karma_for_comment" name="ai_community_settings[karma_for_comment]" value="<?php echo esc_attr($settings['users']['karma_for_comment']); ?>" class="small-text">
                                        <?php _e('points', 'ai-community'); ?>
                                    </label>
                                    <br><br>
                                    <label for="karma_for_upvote">
                                        <?php _e('Receiving an upvote:', 'ai-community'); ?>
                                        <input type="number" id="karma_for_upvote" name="ai_community_settings[karma_for_upvote]" value="<?php echo esc_attr($settings['users']['karma_for_upvote']); ?>" class="small-text">
                                        <?php _e('points', 'ai-community'); ?>
                                    </label>
                                    <br><br>
                                    <label for="karma_for_downvote">
                                        <?php _e('Receiving a downvote:', 'ai-community'); ?>
                                        <input type="number" id="karma_for_downvote" name="ai_community_settings[karma_for_downvote]" value="<?php echo esc_attr($settings['users']['karma_for_downvote']); ?>" class="small-text">
                                        <?php _e('points', 'ai-community'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Minimum Karma Requirements', 'ai-community'); ?></th>
                            <td>
                                <fieldset>
                                    <label for="min_karma_to_post">
                                        <?php _e('To create posts:', 'ai-community'); ?>
                                        <input type="number" id="min_karma_to_post" name="ai_community_settings[min_karma_to_post]" value="<?php echo esc_attr($settings['users']['min_karma_to_post']); ?>" min="0" class="small-text">
                                        <?php _e('karma points', 'ai-community'); ?>
                                    </label>
                                    <br><br>
                                    <label for="min_karma_to_vote">
                                        <?php _e('To vote:', 'ai-community'); ?>
                                        <input type="number" id="min_karma_to_vote" name="ai_community_settings[min_karma_to_vote]" value="<?php echo esc_attr($settings['users']['min_karma_to_vote']); ?>" min="0" class="small-text">
                                        <?php _e('karma points', 'ai-community'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'advanced'): ?>
                <!-- Advanced Settings -->
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="cache_enabled"><?php _e('Caching', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <label for="cache_enabled">
                                    <input type="checkbox" id="cache_enabled" name="ai_community_settings[cache_enabled]" value="1" <?php checked($settings['advanced']['cache_enabled']); ?>>
                                    <?php _e('Enable caching for better performance', 'ai-community'); ?>
                                </label>
                                <br><br>
                                <label for="cache_duration">
                                    <?php _e('Cache duration:', 'ai-community'); ?>
                                    <input type="number" id="cache_duration" name="ai_community_settings[cache_duration]" value="<?php echo esc_attr($settings['advanced']['cache_duration']); ?>" min="300" class="small-text">
                                    <?php _e('seconds', 'ai-community'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="debug_mode"><?php _e('Debug Mode', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <label for="debug_mode">
                                    <input type="checkbox" id="debug_mode" name="ai_community_settings[debug_mode]" value="1" <?php checked($settings['advanced']['debug_mode']); ?>>
                                    <?php _e('Enable debug logging', 'ai-community'); ?>
                                </label>
                                <p class="description"><?php _e('Log detailed information for troubleshooting.', 'ai-community'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="cleanup_old_posts"><?php _e('Cleanup Old Posts', 'ai-community'); ?></label>
                            </th>
                            <td>
                                <label for="cleanup_old_posts">
                                    <input type="checkbox" id="cleanup_old_posts" name="ai_community_settings[cleanup_old_posts]" value="1" <?php checked($settings['advanced']['cleanup_old_posts']); ?>>
                                    <?php _e('Automatically delete old AI posts', 'ai-community'); ?>
                                </label>
                                <br><br>
                                <label for="cleanup_old_posts_days">
                                    <?php _e('Delete posts older than:', 'ai-community'); ?>
                                    <input type="number" id="cleanup_old_posts_days" name="ai_community_settings[cleanup_old_posts_days]" value="<?php echo esc_attr($settings['advanced']['cleanup_old_posts_days']); ?>" min="30" class="small-text">
                                    <?php _e('days', 'ai-community'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<style>
.settings-content {
    background: #fff;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.color-picker {
    width: 60px;
    height: 30px;
    padding: 0;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    cursor: pointer;
}

#test-api-key {
    margin-left: 10px;
}

.form-table th {
    width: 220px;
}

.form-table td fieldset label {
    display: block;
    margin-bottom: 8px;
}

.form-table td fieldset label input {
    margin-right: 8px;
}

.api-test-result {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
}

.api-test-success {
    background: #d1e7dd;
    color: #0f5132;
    border: 1px solid #badbcc;
}

.api-test-error {
    background: #f8d7da;
    color: #842029;
    border: 1px solid #f5c2c7;
}

@media (max-width: 768px) {
    .form-table th,
    .form-table td {
        display: block;
        width: 100%;
        padding: 10px 0;
    }
    
    .form-table th {
        border-bottom: none;
        font-weight: 600;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Color picker initialization
    if (typeof wp !== 'undefined' && wp.colorPicker) {
        $('.color-picker').wpColorPicker();
    }
    
    // Test API Key
    $('#test-api-key').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var apiKey = $('#openrouter_api_key').val();
        
        if (!apiKey) {
            alert('<?php _e("Please enter an API key first.", "ai-community"); ?>');
            return;
        }
        
        button.prop('disabled', true).text('<?php _e("Testing...", "ai-community"); ?>');
        
        // Remove previous results
        $('.api-test-result').remove();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_community_test_api',
                api_key: apiKey,
                nonce: aiCommunityAdmin.nonce
            },
            success: function(response) {
                var resultClass = response.success ? 'api-test-success' : 'api-test-error';
                var message = response.success ? 
                    '<?php _e("API connection successful!", "ai-community"); ?>' : 
                    response.data || '<?php _e("API connection failed.", "ai-community"); ?>';
                
                var resultDiv = $('<div class="api-test-result ' + resultClass + '">' + message + '</div>');
                $('#openrouter_api_key').parent().append(resultDiv);
            },
            error: function() {
                var resultDiv = $('<div class="api-test-result api-test-error"><?php _e("Connection test failed.", "ai-community"); ?></div>');
                $('#openrouter_api_key').parent().append(resultDiv);
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e("Test Connection", "ai-community"); ?>');
            }
        });
    });
    
    // Auto-generate slug from name
    $('#community_name').on('input', function() {
        var name = $(this).val();
        var slug = name.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        $('#community_slug').val(slug);
    });
    
    // Handle source websites textarea
    $('#source_websites').on('blur', function() {
        var lines = $(this).val().split('\n');
        var validUrls = [];
        
        lines.forEach(function(line) {
            line = line.trim();
            if (line && (line.startsWith('http://') || line.startsWith('https://'))) {
                validUrls.push(line);
            }
        });
        
        $(this).val(validUrls.join('\n'));
    });
    
    // Conditional field display
    $('#karma_system_enabled').change(function() {
        var karmaFields = $(this).closest('tbody').find('tr').slice(1);
        if ($(this).is(':checked')) {
            karmaFields.show();
        } else {
            karmaFields.hide();
        }
    }).trigger('change');
    
    $('#cache_enabled').change(function() {
        var durationField = $(this).parent().find('label').slice(1);
        if ($(this).is(':checked')) {
            durationField.show();
        } else {
            durationField.hide();
        }
    }).trigger('change');
    
    $('#cleanup_old_posts').change(function() {
        var daysField = $(this).parent().find('label').slice(1);
        if ($(this).is(':checked')) {
            daysField.show();
        } else {
            daysField.hide();
        }
    }).trigger('change');
});
</script>