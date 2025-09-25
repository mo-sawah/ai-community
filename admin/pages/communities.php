<?php
/**
 * AI Community Communities Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$this->render_admin_header(__('Communities', 'ai-community'));
?>

<div class="ai-community-communities-page">
    <div class="page-header-actions">
        <a href="#" id="add-community" class="button button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php _e('Add New Community', 'ai-community'); ?>
        </a>
    </div>

    <!-- Add Community Form (hidden by default) -->
    <div id="add-community-form" class="add-community-form" style="display: none;">
        <div class="form-container">
            <h3><?php _e('Add New Community', 'ai-community'); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field('ai_community_action', '_wpnonce'); ?>
                <input type="hidden" name="ai_community_action" value="add_community">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="community_name"><?php _e('Community Name', 'ai-community'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="community_name" name="community_name" class="regular-text" required>
                            <p class="description"><?php _e('The display name for the community.', 'ai-community'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="community_slug"><?php _e('Slug', 'ai-community'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="community_slug" name="community_slug" class="regular-text">
                            <p class="description"><?php _e('URL-friendly version of the name. Leave empty to auto-generate.', 'ai-community'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="community_description"><?php _e('Description', 'ai-community'); ?></label>
                        </th>
                        <td>
                            <textarea id="community_description" name="community_description" rows="4" cols="50" class="large-text"></textarea>
                            <p class="description"><?php _e('Brief description of the community.', 'ai-community'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="community_color"><?php _e('Color', 'ai-community'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="community_color" name="community_color" value="#6366f1">
                            <p class="description"><?php _e('Theme color for this community.', 'ai-community'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('Add Community', 'ai-community'); ?>">
                    <button type="button" id="cancel-add-community" class="button"><?php _e('Cancel', 'ai-community'); ?></button>
                </p>
            </form>
        </div>
    </div>

    <!-- Communities List -->
    <div class="communities-list">
        <?php if (empty($communities)): ?>
            <div class="no-communities">
                <div class="no-communities-content">
                    <span class="dashicons dashicons-groups"></span>
                    <h3><?php _e('No communities found', 'ai-community'); ?></h3>
                    <p><?php _e('Create your first community to get started.', 'ai-community'); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="communities-grid">
                <?php foreach ($communities as $community): ?>
                    <div class="community-card" data-community-id="<?php echo $community->id; ?>">
                        <div class="community-header">
                            <div class="community-info">
                                <div class="community-avatar" style="background-color: <?php echo esc_attr($community->color); ?>;">
                                    <?php echo esc_html(strtoupper(substr($community->name, 0, 1))); ?>
                                </div>
                                <div class="community-details">
                                    <h3 class="community-name"><?php echo esc_html($community->name); ?></h3>
                                    <p class="community-slug">c/<?php echo esc_html($community->slug); ?></p>
                                </div>
                            </div>
                            <div class="community-actions">
                                <button class="button button-small edit-community" data-id="<?php echo $community->id; ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <?php if (!in_array($community->slug, array('general', 'announcements'))): ?>
                                    <button class="button button-small delete-community" data-id="<?php echo $community->id; ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($community->description)): ?>
                            <div class="community-description">
                                <p><?php echo esc_html($community->description); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="community-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($community->post_count); ?></span>
                                <span class="stat-label"><?php _e('Posts', 'ai-community'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($community->member_count); ?></span>
                                <span class="stat-label"><?php _e('Members', 'ai-community'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-status status-<?php echo esc_attr($community->status); ?>">
                                    <?php echo esc_html(ucfirst($community->status)); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="community-meta">
                            <small>
                                <?php
                                printf(
                                    __('Created %s by %s', 'ai-community'),
                                    human_time_diff(strtotime($community->created_at), current_time('timestamp')) . ' ago',
                                    get_userdata($community->created_by)->display_name ?? __('Unknown', 'ai-community')
                                );
                                ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-community-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3><?php _e('Delete Community', 'ai-community'); ?></h3>
        <p><?php _e('Are you sure you want to delete this community? All posts in this community will be moved to the General community. This action cannot be undone.', 'ai-community'); ?></p>
        <div class="modal-actions">
            <form method="post" action="">
                <?php wp_nonce_field('ai_community_action', '_wpnonce'); ?>
                <input type="hidden" name="ai_community_action" value="delete_community">
                <input type="hidden" id="delete_community_id" name="community_id" value="">
                <button type="submit" class="button button-primary button-danger"><?php _e('Delete Community', 'ai-community'); ?></button>
                <button type="button" class="button cancel-delete"><?php _e('Cancel', 'ai-community'); ?></button>
            </form>
        </div>
    </div>
</div>

<style>
.page-header-actions {
    margin-bottom: 20px;
}

.add-community-form {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 20px;
}

.add-community-form .form-container {
    padding: 20px;
}

.add-community-form h3 {
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #c3c4c7;
}

.communities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.community-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
    transition: box-shadow 0.2s ease;
}

.community-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.community-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.community-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.community-avatar {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 18px;
}

.community-details h3 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
}

.community-slug {
    margin: 0;
    font-size: 13px;
    color: #646970;
}

.community-actions {
    display: flex;
    gap: 4px;
}

.community-actions .button {
    padding: 4px 8px;
    min-height: auto;
}

.community-description {
    margin-bottom: 15px;
}

.community-description p {
    margin: 0;
    color: #646970;
    font-size: 14px;
    line-height: 1.5;
}

.community-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-top: 1px solid #f0f0f1;
    border-bottom: 1px solid #f0f0f1;
    margin-bottom: 12px;
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-value {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

.stat-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.status-active {
    background: #d1e7dd;
    color: #0f5132;
}

.status-inactive {
    background: #f8d7da;
    color: #842029;
}

.community-meta {
    color: #646970;
    font-size: 13px;
}

.no-communities {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
}

.no-communities-content .dashicons {
    font-size: 48px;
    color: #c3c4c7;
    margin-bottom: 16px;
}

.no-communities-content h3 {
    margin: 0 0 8px 0;
    color: #646970;
}

.no-communities-content p {
    margin: 0;
    color: #646970;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.modal-content h3 {
    margin-top: 0;
    margin-bottom: 16px;
}

.modal-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.button-danger {
    background-color: #dc3232 !important;
    border-color: #dc3232 !important;
    color: #fff !important;
}

.button-danger:hover {
    background-color: #c02d2d !important;
    border-color: #c02d2d !important;
}

@media (max-width: 768px) {
    .communities-grid {
        grid-template-columns: 1fr;
    }
    
    .community-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .community-stats {
        flex-direction: column;
        gap: 8px;
    }
    
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-align: left;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Show/hide add community form
    $('#add-community').click(function(e) {
        e.preventDefault();
        $('#add-community-form').slideToggle();
    });
    
    $('#cancel-add-community').click(function() {
        $('#add-community-form').slideUp();
    });
    
    // Auto-generate slug from name
    $('#community_name').on('input', function() {
        var name = $(this).val();
        var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        $('#community_slug').val(slug);
    });
    
    // Delete community
    $('.delete-community').click(function(e) {
        e.preventDefault();
        var communityId = $(this).data('id');
        $('#delete_community_id').val(communityId);
        $('#delete-community-modal').show();
    });
    
    $('.cancel-delete').click(function() {
        $('#delete-community-modal').hide();
    });
    
    // Close modal when clicking overlay
    $('#delete-community-modal').click(function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
});
</script>