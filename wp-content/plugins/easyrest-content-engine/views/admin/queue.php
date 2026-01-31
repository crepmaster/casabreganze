<?php
/**
 * Queue View
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap easyrest-ce-admin">
    <h1><?php esc_html_e('Content Queue', 'easyrest-ce'); ?></h1>

    <!-- Status Tabs -->
    <div class="easyrest-ce-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=easyrest-ce-queue')); ?>"
           class="tab <?php echo empty($data['filters']['status']) ? 'active' : ''; ?>">
            <?php esc_html_e('All', 'easyrest-ce'); ?>
            <span class="count"><?php echo esc_html(array_sum($data['counts'])); ?></span>
        </a>
        <?php foreach ($data['statuses'] as $status): ?>
        <a href="<?php echo esc_url(add_query_arg('status', $status)); ?>"
           class="tab <?php echo $data['filters']['status'] === $status ? 'active' : ''; ?>">
            <?php echo esc_html(ucfirst($status)); ?>
            <span class="count"><?php echo esc_html($data['counts'][$status] ?? 0); ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="easyrest-ce-filters">
        <form method="get">
            <input type="hidden" name="page" value="easyrest-ce-queue">
            <?php if ($data['filters']['status']): ?>
            <input type="hidden" name="status" value="<?php echo esc_attr($data['filters']['status']); ?>">
            <?php endif; ?>

            <select name="content_type">
                <option value=""><?php esc_html_e('All Content Types', 'easyrest-ce'); ?></option>
                <option value="weekly_guide" <?php selected($data['filters']['content_type'], 'weekly_guide'); ?>>Weekly Guide</option>
                <option value="sport_guide" <?php selected($data['filters']['content_type'], 'sport_guide'); ?>>Sport Guide</option>
                <option value="transport_guide" <?php selected($data['filters']['content_type'], 'transport_guide'); ?>>Transport Guide</option>
                <option value="venue_guide" <?php selected($data['filters']['content_type'], 'venue_guide'); ?>>Venue Guide</option>
                <option value="match_preview" <?php selected($data['filters']['content_type'], 'match_preview'); ?>>Match Preview</option>
            </select>

            <select name="lang">
                <option value=""><?php esc_html_e('All Languages', 'easyrest-ce'); ?></option>
                <?php foreach ($data['languages'] as $lang): ?>
                <option value="<?php echo esc_attr($lang); ?>" <?php selected($data['filters']['lang'], $lang); ?>>
                    <?php echo esc_html(strtoupper($lang)); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button"><?php esc_html_e('Filter', 'easyrest-ce'); ?></button>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div class="easyrest-ce-bulk-actions">
        <form method="post">
            <?php wp_nonce_field('easyrest_queue_action'); ?>
            <input type="hidden" name="action" value="delete_failed">
            <button type="submit" class="button" onclick="return confirm('<?php esc_attr_e('Delete all failed items?', 'easyrest-ce'); ?>')">
                <?php esc_html_e('Delete Failed', 'easyrest-ce'); ?>
            </button>
        </form>
    </div>

    <!-- Queue Table -->
    <?php if (empty($data['items'])): ?>
        <p><?php esc_html_e('No items in queue.', 'easyrest-ce'); ?></p>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="column-id"><?php esc_html_e('ID', 'easyrest-ce'); ?></th>
                <th class="column-content"><?php esc_html_e('Content', 'easyrest-ce'); ?></th>
                <th class="column-lang"><?php esc_html_e('Lang', 'easyrest-ce'); ?></th>
                <th class="column-status"><?php esc_html_e('Status', 'easyrest-ce'); ?></th>
                <th class="column-scheduled"><?php esc_html_e('Scheduled', 'easyrest-ce'); ?></th>
                <th class="column-attempts"><?php esc_html_e('Attempts', 'easyrest-ce'); ?></th>
                <th class="column-actions"><?php esc_html_e('Actions', 'easyrest-ce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['items'] as $item): ?>
            <tr data-id="<?php echo esc_attr($item->id); ?>">
                <td class="column-id"><?php echo esc_html($item->id); ?></td>
                <td class="column-content">
                    <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $item->content_type))); ?></strong>
                    <?php if ($item->post_id): ?>
                        <br>
                        <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" target="_blank">
                            <?php esc_html_e('View Post', 'easyrest-ce'); ?> #<?php echo esc_html($item->post_id); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($item->source_ref): ?>
                        <br><small><code><?php echo esc_html(wp_trim_words($item->source_ref, 5)); ?></code></small>
                    <?php endif; ?>
                </td>
                <td class="column-lang"><?php echo esc_html(strtoupper($item->lang)); ?></td>
                <td class="column-status">
                    <span class="status-badge status-<?php echo esc_attr($item->status); ?>">
                        <?php echo esc_html(ucfirst($item->status)); ?>
                    </span>
                    <?php if ($item->last_error): ?>
                        <br><small class="error-message" title="<?php echo esc_attr($item->last_error); ?>">
                            <?php echo esc_html(wp_trim_words($item->last_error, 5)); ?>
                        </small>
                    <?php endif; ?>
                </td>
                <td class="column-scheduled">
                    <?php echo esc_html(human_time_diff(strtotime($item->scheduled_at), current_time('timestamp'))); ?>
                    <?php if (strtotime($item->scheduled_at) > current_time('timestamp')): ?>
                        <br><small><?php esc_html_e('from now', 'easyrest-ce'); ?></small>
                    <?php else: ?>
                        <br><small><?php esc_html_e('ago', 'easyrest-ce'); ?></small>
                    <?php endif; ?>
                </td>
                <td class="column-attempts"><?php echo esc_html($item->attempts); ?></td>
                <td class="column-actions">
                    <?php if ($item->status === EasyRest_CE_Queue_Status::PENDING || $item->status === EasyRest_CE_Queue_Status::FAILED): ?>
                        <button type="button" class="button button-small process-item" data-id="<?php echo esc_attr($item->id); ?>">
                            <?php esc_html_e('Process', 'easyrest-ce'); ?>
                        </button>
                    <?php endif; ?>

                    <?php if ($item->status === EasyRest_CE_Queue_Status::FAILED): ?>
                        <button type="button" class="button button-small retry-item" data-id="<?php echo esc_attr($item->id); ?>">
                            <?php esc_html_e('Retry', 'easyrest-ce'); ?>
                        </button>
                    <?php endif; ?>

                    <button type="button" class="button button-small button-link-delete delete-item" data-id="<?php echo esc_attr($item->id); ?>">
                        <?php esc_html_e('Delete', 'easyrest-ce'); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Manual Queue Form -->
    <div class="easyrest-ce-manual-queue">
        <h3><?php esc_html_e('Manually Queue Content', 'easyrest-ce'); ?></h3>
        <form method="post">
            <?php wp_nonce_field('easyrest_queue_action'); ?>
            <input type="hidden" name="action" value="manual_queue">

            <table class="form-table">
                <tr>
                    <th><label for="context_id"><?php esc_html_e('Context', 'easyrest-ce'); ?></label></th>
                    <td>
                        <select name="context_id" id="context_id" required>
                            <?php
                            $context_repo = new EasyRest_CE_Context_Repository();
                            $contexts = $context_repo->get_active_contexts();
                            foreach ($contexts as $ctx):
                            ?>
                            <option value="<?php echo esc_attr($ctx->id); ?>">
                                <?php echo esc_html($ctx->name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="content_type"><?php esc_html_e('Content Type', 'easyrest-ce'); ?></label></th>
                    <td>
                        <select name="content_type" id="content_type" required>
                            <option value="weekly_guide">Weekly Guide</option>
                            <option value="sport_guide">Sport Guide</option>
                            <option value="transport_guide">Transport Guide</option>
                            <option value="venue_guide">Venue Guide</option>
                            <option value="match_preview">Match Preview</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="lang"><?php esc_html_e('Language', 'easyrest-ce'); ?></label></th>
                    <td>
                        <select name="lang" id="lang" required>
                            <?php foreach ($data['languages'] as $lang): ?>
                            <option value="<?php echo esc_attr($lang); ?>">
                                <?php echo esc_html(strtoupper($lang)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Add to Queue', 'easyrest-ce'); ?>
                </button>
            </p>
        </form>
    </div>
</div>
