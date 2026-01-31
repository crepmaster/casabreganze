<?php
/**
 * Logs View
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap easyrest-ce-admin">
    <h1><?php esc_html_e('Activity Logs', 'easyrest-ce'); ?></h1>

    <!-- Stats -->
    <div class="easyrest-ce-log-stats">
        <div class="stat-card">
            <span class="stat-number"><?php echo esc_html(number_format($data['total_tokens'])); ?></span>
            <span class="stat-label"><?php esc_html_e('Total Tokens', 'easyrest-ce'); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-number">$<?php echo esc_html(number_format($data['total_cost'], 4)); ?></span>
            <span class="stat-label"><?php esc_html_e('Total Cost', 'easyrest-ce'); ?></span>
        </div>
    </div>

    <!-- Logs Table -->
    <?php if (empty($data['logs'])): ?>
        <p><?php esc_html_e('No logs found.', 'easyrest-ce'); ?></p>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="column-time"><?php esc_html_e('Time', 'easyrest-ce'); ?></th>
                <th class="column-action"><?php esc_html_e('Action', 'easyrest-ce'); ?></th>
                <th class="column-queue"><?php esc_html_e('Queue ID', 'easyrest-ce'); ?></th>
                <th class="column-message"><?php esc_html_e('Message', 'easyrest-ce'); ?></th>
                <th class="column-tokens"><?php esc_html_e('Tokens', 'easyrest-ce'); ?></th>
                <th class="column-cost"><?php esc_html_e('Cost', 'easyrest-ce'); ?></th>
                <th class="column-duration"><?php esc_html_e('Duration', 'easyrest-ce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['logs'] as $log): ?>
            <tr class="log-<?php echo esc_attr(strpos($log->action, 'error') !== false ? 'error' : 'normal'); ?>">
                <td class="column-time">
                    <?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_at))); ?>
                    <br>
                    <small><?php echo esc_html(human_time_diff(strtotime($log->created_at), current_time('timestamp'))); ?> ago</small>
                </td>
                <td class="column-action">
                    <code><?php echo esc_html($log->action); ?></code>
                </td>
                <td class="column-queue">
                    <?php if ($log->queue_id > 0): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=easyrest-ce-queue&status=all&search=' . $log->queue_id)); ?>">
                            #<?php echo esc_html($log->queue_id); ?>
                        </a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="column-message">
                    <?php echo esc_html($log->message); ?>
                    <?php if ($log->metadata): ?>
                        <br>
                        <small class="log-metadata">
                            <?php
                            $meta = json_decode($log->metadata, true);
                            if ($meta) {
                                echo esc_html(wp_trim_words(json_encode($meta), 20));
                            }
                            ?>
                        </small>
                    <?php endif; ?>
                </td>
                <td class="column-tokens">
                    <?php if ($log->tokens_used > 0): ?>
                        <?php echo esc_html(number_format($log->tokens_used)); ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="column-cost">
                    <?php if ($log->cost > 0): ?>
                        $<?php echo esc_html(number_format($log->cost, 6)); ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="column-duration">
                    <?php if ($log->duration > 0): ?>
                        <?php echo esc_html(number_format($log->duration, 2)); ?>s
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            $prev_page = max(1, $data['page'] - 1);
            $next_page = $data['page'] + 1;
            ?>

            <?php if ($data['page'] > 1): ?>
                <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $prev_page)); ?>">
                    &laquo; <?php esc_html_e('Previous', 'easyrest-ce'); ?>
                </a>
            <?php endif; ?>

            <span class="paging-input">
                <?php printf(esc_html__('Page %d', 'easyrest-ce'), $data['page']); ?>
            </span>

            <?php if (count($data['logs']) >= 50): ?>
                <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $next_page)); ?>">
                    <?php esc_html_e('Next', 'easyrest-ce'); ?> &raquo;
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
