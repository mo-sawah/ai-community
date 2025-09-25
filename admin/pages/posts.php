<?php
/**
 * AI Community Posts Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$this->render_admin_header(__('Posts', 'ai-community'), $current_tab, 'ai-community-posts');
?>

<div class="ai-community-posts-page">
    <form method="post" action="">
        <?php wp_nonce_field('ai_community_action', '_wpnonce'); ?>
        <input type="hidden" name="ai_community_action" value="bulk_action_posts">
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'ai-community'); ?></label>
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk Actions', 'ai-community'); ?></option>
                    <option value="delete"><?php _e('Delete', 'ai-community'); ?></option>
                    <?php if ($current_tab === 'pending'): ?>
                        <option value="approve"><?php _e('Approve', 'ai-community'); ?></option>
                    <?php endif; ?>
                </select>
                <input type="submit" class="button action" value="<?php _e('Apply', 'ai-community'); ?>">
            </div>
            
            <div class="alignright">
                <div class="search-box">
                    <label class="screen-reader-text" for="post-search-input"><?php _e('Search Posts', 'ai-community'); ?></label>
                    <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="<?php _e('Search posts...', 'ai-community'); ?>">
                    <input type="submit" class="button" value="<?php _e('Search Posts', 'ai-community'); ?>">
                </div>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped posts">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th scope="col" class="manage-column column-title column-primary">
                        <a href="<?php echo admin_url('admin.php?page=ai-community-posts&orderby=title&order=' . (($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc')); ?>">
                            <span><?php _e('Title', 'ai-community'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column"><?php _e('Author', 'ai-community'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Community', 'ai-community'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Status', 'ai-community'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Votes', 'ai-community'); ?></th>
                    <th scope="col" class="manage-column"><?php _e('Comments', 'ai-community'); ?></th>
                    <th scope="col" class="manage-column">
                        <a href="<?php echo admin_url('admin.php?page=ai-community-posts&orderby=date&order=' . (($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc')); ?>">
                            <span><?php _e('Date', 'ai-community'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="8"><?php _e('No posts found.', 'ai-community'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="post_ids[]" value="<?php echo $post->id; ?>">
                            </th>
                            <td class="title column-title column-primary">
                                <strong>
                                    <a href="#" class="row-title"><?php echo esc_html($post->title); ?></a>
                                    <?php if ($post->is_ai_generated): ?>
                                        <span class="ai-badge" title="<?php _e('AI Generated', 'ai-community'); ?>">
                                            <span class="dashicons dashicons-robot"></span>
                                            <?php _e('AI', 'ai-community'); ?>
                                        </span>
                                    <?php endif; ?>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="#" title="<?php _e('Edit this item', 'ai-community'); ?>"><?php _e('Edit', 'ai-community'); ?></a> |
                                    </span>
                                    <span class="view">
                                        <a href="#" title="<?php _e('View this post', 'ai-community'); ?>" target="_blank"><?php _e('View', 'ai-community'); ?></a> |
                                    </span>
                                    <span class="trash">
                                        <a href="#" class="submitdelete" title="<?php _e('Delete this item', 'ai-community'); ?>"><?php _e('Delete', 'ai-community'); ?></a>
                                    </span>
                                </div>
                            </td>
                            <td class="author column-author">
                                <a href="<?php echo admin_url('admin.php?page=ai-community-posts&author=' . $post->author_id); ?>">
                                    <?php echo esc_html($post->author_name); ?>
                                </a>
                            </td>
                            <td class="community column-community">
                                <span class="community-badge" style="background-color: <?php echo esc_attr($post->community_color ?? '#6366f1'); ?>;">
                                    <?php echo esc_html($post->community_name ?? $post->community); ?>
                                </span>
                            </td>
                            <td class="status column-status">
                                <span class="status-<?php echo esc_attr($post->status); ?>">
                                    <?php echo esc_html(ucfirst($post->status)); ?>
                                </span>
                            </td>
                            <td class="votes column-votes">
                                <span class="vote-count <?php echo $post->votes > 0 ? 'positive' : ($post->votes < 0 ? 'negative' : ''); ?>">
                                    <?php echo number_format($post->votes); ?>
                                </span>
                            </td>
                            <td class="comments column-comments">
                                <div class="post-com-count-wrapper">
                                    <a href="#" class="post-com-count">
                                        <span class="comment-count"><?php echo number_format($post->comment_count); ?></span>
                                    </a>
                                </div>
                            </td>
                            <td class="date column-date">
                                <?php
                                $date = strtotime($post->created_at);
                                if ($date > strtotime('-1 day')):
                                ?>
                                    <abbr title="<?php echo date_i18n('Y/m/d g:i:s a', $date); ?>">
                                        <?php echo human_time_diff($date, current_time('timestamp')) . ' ago'; ?>
                                    </abbr>
                                <?php else: ?>
                                    <abbr title="<?php echo date_i18n('Y/m/d g:i:s a', $date); ?>">
                                        <?php echo date_i18n('Y/m/d', $date); ?>
                                    </abbr>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action2">
                    <option value="-1"><?php _e('Bulk Actions', 'ai-community'); ?></option>
                    <option value="delete"><?php _e('Delete', 'ai-community'); ?></option>
                    <?php if ($current_tab === 'pending'): ?>
                        <option value="approve"><?php _e('Approve', 'ai-community'); ?></option>
                    <?php endif; ?>
                </select>
                <input type="submit" class="button action" value="<?php _e('Apply', 'ai-community'); ?>">
            </div>
            
            <?php
            // Pagination
            $total_pages = ceil($total_posts / $posts_per_page);
            if ($total_pages > 1):
                $current_url = admin_url('admin.php?page=ai-community-posts&tab=' . $current_tab);
                echo '<div class="tablenav-pages">';
                echo paginate_links(array(
                    'base' => $current_url . '%_%',
                    'format' => '&paged=%#%',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ));
                echo '</div>';
            endif;
            ?>
        </div>
    </form>
</div>

<style>
.ai-badge {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    background: #8b5cf6;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
    margin-left: 8px;
}

.ai-badge .dashicons {
    font-size: 12px;
    width: 12px;
    height: 12px;
}

.community-badge {
    display: inline-block;
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.status-published {
    color: #46b450;
}

.status-pending {
    color: #ffb900;
}

.status-draft {
    color: #646970;
}

.vote-count.positive {
    color: #46b450;
    font-weight: 600;
}

.vote-count.negative {
    color: #dc3232;
    font-weight: 600;
}

.comment-count {
    font-weight: 600;
}

.search-box {
    float: right;
}

.search-box input[type="search"] {
    width: 280px;
    margin-right: 8px;
}

@media screen and (max-width: 782px) {
    .search-box input[type="search"] {
        width: 100%;
        margin-bottom: 8px;
    }
}
</style>