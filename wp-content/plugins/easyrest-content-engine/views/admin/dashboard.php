<?php
/**
 * Dashboard View
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap easyrest-ce-admin">
    <h1><?php esc_html_e('Content Engine Dashboard', 'easyrest-ce'); ?></h1>

    <?php if (!$data['generator_ready']['ready']): ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('Setup Required:', 'easyrest-ce'); ?></strong>
            <?php echo esc_html(implode(', ', $data['generator_ready']['errors'])); ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="easyrest-ce-dashboard">
        <!-- Stats Cards -->
        <div class="easyrest-ce-stats">
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html($data['queue_counts'][EasyRest_CE_Queue_Status::PENDING]); ?></span>
                <span class="stat-label"><?php esc_html_e('Pending', 'easyrest-ce'); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html($data['queue_counts'][EasyRest_CE_Queue_Status::GENERATING]); ?></span>
                <span class="stat-label"><?php esc_html_e('Generating', 'easyrest-ce'); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html($data['queue_counts'][EasyRest_CE_Queue_Status::REVIEW]); ?></span>
                <span class="stat-label"><?php esc_html_e('In Review', 'easyrest-ce'); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html($data['queue_counts'][EasyRest_CE_Queue_Status::PUBLISHED]); ?></span>
                <span class="stat-label"><?php esc_html_e('Published', 'easyrest-ce'); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo esc_html($data['queue_counts'][EasyRest_CE_Queue_Status::FAILED]); ?></span>
                <span class="stat-label"><?php esc_html_e('Failed', 'easyrest-ce'); ?></span>
            </div>
        </div>

        <!-- Cost Stats -->
        <div class="easyrest-ce-cost-stats">
            <div class="cost-card">
                <h3><?php esc_html_e('Usage Statistics', 'easyrest-ce'); ?></h3>
                <table class="widefat">
                    <tr>
                        <td><?php esc_html_e('Total Tokens Used', 'easyrest-ce'); ?></td>
                        <td><strong><?php echo esc_html(number_format($data['total_tokens'])); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Total Cost', 'easyrest-ce'); ?></td>
                        <td><strong>$<?php echo esc_html(number_format($data['total_cost'], 4)); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Active Contexts', 'easyrest-ce'); ?></td>
                        <td><strong><?php echo esc_html(count($data['contexts'])); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Cron Status -->
        <div class="easyrest-ce-cron-status">
            <h3><?php esc_html_e('Cron Schedule', 'easyrest-ce'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Hook', 'easyrest-ce'); ?></th>
                        <th><?php esc_html_e('Next Run', 'easyrest-ce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cron_entries = [
                        'planner' => __('Planner (daily)', 'easyrest-ce'),
                        'process' => __('Queue Processing (5 min)', 'easyrest-ce'),
                        'cleanup' => __('Queue Cleanup (daily)', 'easyrest-ce'),
                    ];
                    foreach ($cron_entries as $key => $label):
                        $timestamp = $data['cron_status'][$key] ?? false;
                    ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td>
                            <?php if ($timestamp): ?>
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)); ?>
                                <em>(<?php echo esc_html(human_time_diff($timestamp)); ?>)</em>
                            <?php else: ?>
                                <span style="color:#d63638;"><?php esc_html_e('Not scheduled', 'easyrest-ce'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Actions -->
        <div class="easyrest-ce-actions">
            <h3><?php esc_html_e('Quick Actions', 'easyrest-ce'); ?></h3>
            <div class="action-buttons">
                <button type="button" class="button button-primary" id="run-planner">
                    <?php esc_html_e('Run Planner', 'easyrest-ce'); ?>
                </button>
                <button type="button" class="button button-secondary" id="run-worker">
                    <?php esc_html_e('Run Worker Batch', 'easyrest-ce'); ?>
                </button>
                <button type="button" class="button" id="release-stale-locks">
                    <?php esc_html_e('Release Stale Locks', 'easyrest-ce'); ?>
                </button>
                <button type="button" class="button" id="test-api">
                    <?php esc_html_e('Test API', 'easyrest-ce'); ?>
                </button>
            </div>
            <p class="description">
                <?php
                printf(
                    esc_html__('Worker batch size: %d items, time budget: %d seconds.', 'easyrest-ce'),
                    (int) get_option('easyrest_ce_worker_batch_size', 3),
                    (int) get_option('easyrest_ce_worker_time_budget', 25)
                );
                ?>
            </p>
            <div id="action-result" class="action-result"></div>
        </div>

        <!-- Active Contexts -->
        <div class="easyrest-ce-contexts-overview">
            <h3><?php esc_html_e('Active Contexts', 'easyrest-ce'); ?></h3>
            <?php if (empty($data['contexts'])): ?>
                <p><?php esc_html_e('No active contexts. Create one to start generating content.', 'easyrest-ce'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=easyrest-ce-contexts')); ?>" class="button">
                    <?php esc_html_e('Create Context', 'easyrest-ce'); ?>
                </a>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'easyrest-ce'); ?></th>
                            <th><?php esc_html_e('Type', 'easyrest-ce'); ?></th>
                            <th><?php esc_html_e('Status', 'easyrest-ce'); ?></th>
                            <th><?php esc_html_e('Date Range', 'easyrest-ce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['contexts'] as $context): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($context->name); ?></strong>
                                <br><code><?php echo esc_html($context->slug); ?></code>
                            </td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $context->type))); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($context->status); ?>">
                                    <?php echo esc_html(ucfirst($context->status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($context->date_start): ?>
                                    <?php echo esc_html($context->date_start); ?> - <?php echo esc_html($context->date_end); ?>
                                <?php else: ?>
                                    <?php esc_html_e('Ongoing', 'easyrest-ce'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="easyrest-ce-recent-logs">
            <h3><?php esc_html_e('Recent Activity', 'easyrest-ce'); ?></h3>
            <?php if (empty($data['recent_logs'])): ?>
                <p><?php esc_html_e('No recent activity.', 'easyrest-ce'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Time', 'easyrest-ce'); ?></th>
                            <th><?php esc_html_e('Action', 'easyrest-ce'); ?></th>
                            <th><?php esc_html_e('Message', 'easyrest-ce'); ?></th>
                            <th><?php esc_html_e('Cost', 'easyrest-ce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['recent_logs'] as $log): ?>
                        <tr>
                            <td><?php echo esc_html(human_time_diff(strtotime($log->created_at), current_time('timestamp'))); ?> ago</td>
                            <td><code><?php echo esc_html($log->action); ?></code></td>
                            <td><?php echo esc_html(wp_trim_words($log->message, 10)); ?></td>
                            <td>
                                <?php if ($log->cost > 0): ?>
                                    $<?php echo esc_html(number_format($log->cost, 4)); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=easyrest-ce-logs')); ?>">
                        <?php esc_html_e('View All Logs', 'easyrest-ce'); ?> &rarr;
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Cron Endpoint Info -->
        <div class="easyrest-ce-cron-info">
            <h3><?php esc_html_e('External Cron Setup', 'easyrest-ce'); ?></h3>
            <p><?php esc_html_e('Configure these endpoints in your cron service (e.g., o2switch cron):', 'easyrest-ce'); ?></p>

            <table class="widefat">
                <tr>
                    <th><?php esc_html_e('Planner (Weekly)', 'easyrest-ce'); ?></th>
                    <td>
                        <code><?php echo esc_url(rest_url('easyrest/v1/planner')); ?></code>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Worker (Every 5 min)', 'easyrest-ce'); ?></th>
                    <td>
                        <code><?php echo esc_url(rest_url('easyrest/v1/worker')); ?></code>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Auth Header', 'easyrest-ce'); ?></th>
                    <td>
                        <code>X-Worker-Token: <?php echo esc_html(substr(get_option('easyrest_ce_worker_token', ''), 0, 8)); ?>...</code>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
