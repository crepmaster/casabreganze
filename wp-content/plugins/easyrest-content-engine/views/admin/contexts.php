<?php
/**
 * Contexts View
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

$editing = $data['editing'] ?? null;
?>

<div class="wrap easyrest-ce-admin">
    <h1>
        <?php esc_html_e('Content Contexts', 'easyrest-ce'); ?>
        <?php if (!$editing): ?>
        <a href="<?php echo esc_url(add_query_arg('edit', 'new')); ?>" class="page-title-action">
            <?php esc_html_e('Add New', 'easyrest-ce'); ?>
        </a>
        <?php endif; ?>
    </h1>

    <?php if (isset($_GET['saved'])): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Context saved successfully.', 'easyrest-ce'); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="notice notice-error is-dismissible">
        <p>
        <?php
        $error = sanitize_text_field($_GET['error']);
        switch ($error) {
            case 'duplicate_slug':
                esc_html_e('A context with this slug already exists. Please use a different slug.', 'easyrest-ce');
                break;
            case 'invalid_json':
                $field = sanitize_text_field($_GET['field'] ?? 'unknown');
                printf(
                    esc_html__('Invalid JSON in field: %s. Please check the syntax.', 'easyrest-ce'),
                    esc_html($field)
                );
                break;
            case 'save_failed':
                esc_html_e('Failed to save context. Please try again.', 'easyrest-ce');
                break;
            default:
                esc_html_e('An error occurred.', 'easyrest-ce');
        }
        ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="easyrest-ce-contexts-wrapper">
        <!-- Context Form -->
        <?php if ($editing || isset($_GET['edit'])): ?>
        <div class="easyrest-ce-context-form">
            <h2><?php echo $editing ? esc_html__('Edit Context', 'easyrest-ce') : esc_html__('New Context', 'easyrest-ce'); ?></h2>

            <form method="post">
                <?php wp_nonce_field('easyrest_save_context'); ?>
                <input type="hidden" name="action" value="save_context">
                <?php if ($editing): ?>
                <input type="hidden" name="context_id" value="<?php echo esc_attr($editing->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="name"><?php esc_html_e('Name', 'easyrest-ce'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   value="<?php echo esc_attr($editing->name ?? ''); ?>"
                                   class="regular-text"
                                   required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="slug"><?php esc_html_e('Slug', 'easyrest-ce'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="slug"
                                   id="slug"
                                   value="<?php echo esc_attr($editing->slug ?? ''); ?>"
                                   class="regular-text"
                                   required
                                   pattern="[a-z0-9-]+">
                            <p class="description"><?php esc_html_e('Lowercase letters, numbers, and hyphens only.', 'easyrest-ce'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="type"><?php esc_html_e('Type', 'easyrest-ce'); ?></label>
                        </th>
                        <td>
                            <select name="type" id="type">
                                <?php foreach ($data['types'] as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected($editing->type ?? '', $type); ?>>
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="status"><?php esc_html_e('Status', 'easyrest-ce'); ?></label>
                        </th>
                        <td>
                            <select name="status" id="status">
                                <?php foreach ($data['statuses'] as $status): ?>
                                <option value="<?php echo esc_attr($status); ?>" <?php selected($editing->status ?? 'active', $status); ?>>
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="date_start"><?php esc_html_e('Date Range', 'easyrest-ce'); ?></label>
                        </th>
                        <td>
                            <input type="date"
                                   name="date_start"
                                   id="date_start"
                                   value="<?php echo esc_attr($editing->date_start ?? ''); ?>">
                            <span><?php esc_html_e('to', 'easyrest-ce'); ?></span>
                            <input type="date"
                                   name="date_end"
                                   id="date_end"
                                   value="<?php echo esc_attr($editing->date_end ?? ''); ?>">
                            <p class="description"><?php esc_html_e('Optional. For event-based and seasonal contexts.', 'easyrest-ce'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="events_json"><?php esc_html_e('Events JSON', 'easyrest-ce'); ?></label>
                        </th>
                        <td>
                            <textarea name="events_json"
                                      id="events_json"
                                      rows="10"
                                      class="large-text code"><?php echo esc_textarea($editing->events_json ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('JSON array of events. Each event should have: date, sport, venue, teams (optional).', 'easyrest-ce'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="venues_json"><?php esc_html_e('Venues JSON', 'easyrest-ce'); ?></label>
                        </th>
                        <td>
                            <?php
                            // Extract venues from events_json for display
                            $venues_display = '';
                            if ($editing && !empty($editing->events_json)) {
                                $events_data = json_decode($editing->events_json, true);
                                if (isset($events_data['venues'])) {
                                    $venues_display = wp_json_encode($events_data['venues'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                }
                            }
                            ?>
                            <textarea name="venues_json"
                                      id="venues_json"
                                      rows="8"
                                      class="large-text code"><?php echo esc_textarea($venues_display); ?></textarea>
                            <p class="description"><?php esc_html_e('JSON object of venues with transport info.', 'easyrest-ce'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="prompts_json"><?php esc_html_e('Prompt Overrides', 'easyrest-ce'); ?></label>
                        </th>
                        <td>
                            <?php
                            // prompts_config is stored as JSON string in DB
                            $prompts_display = '';
                            if ($editing && !empty($editing->prompts_config)) {
                                // It may already be an array (from from_row) or JSON string
                                if (is_array($editing->prompts_config)) {
                                    $prompts_display = wp_json_encode($editing->prompts_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                } else {
                                    $prompts_display = $editing->prompts_config;
                                }
                            }
                            ?>
                            <textarea name="prompts_json"
                                      id="prompts_json"
                                      rows="8"
                                      class="large-text code"><?php echo esc_textarea($prompts_display); ?></textarea>
                            <p class="description"><?php esc_html_e('JSON object to override default prompts.', 'easyrest-ce'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $editing ? esc_html__('Update Context', 'easyrest-ce') : esc_html__('Create Context', 'easyrest-ce'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=easyrest-ce-contexts')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'easyrest-ce'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php endif; ?>

        <!-- Contexts List -->
        <div class="easyrest-ce-contexts-list">
            <h2><?php esc_html_e('All Contexts', 'easyrest-ce'); ?></h2>

            <?php if (empty($data['contexts'])): ?>
                <p><?php esc_html_e('No contexts found. Create your first context to start generating content.', 'easyrest-ce'); ?></p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-name"><?php esc_html_e('Name', 'easyrest-ce'); ?></th>
                        <th class="column-type"><?php esc_html_e('Type', 'easyrest-ce'); ?></th>
                        <th class="column-status"><?php esc_html_e('Status', 'easyrest-ce'); ?></th>
                        <th class="column-dates"><?php esc_html_e('Date Range', 'easyrest-ce'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'easyrest-ce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['contexts'] as $context): ?>
                    <tr>
                        <td class="column-name">
                            <strong><?php echo esc_html($context->name); ?></strong>
                            <br><code><?php echo esc_html($context->slug); ?></code>
                        </td>
                        <td class="column-type">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $context->type))); ?>
                        </td>
                        <td class="column-status">
                            <span class="status-badge status-<?php echo esc_attr($context->status); ?>">
                                <?php echo esc_html(ucfirst($context->status)); ?>
                            </span>
                        </td>
                        <td class="column-dates">
                            <?php if ($context->date_start): ?>
                                <?php echo esc_html($context->date_start); ?>
                                <br><?php esc_html_e('to', 'easyrest-ce'); ?>
                                <?php echo esc_html($context->date_end); ?>
                            <?php else: ?>
                                <em><?php esc_html_e('Ongoing', 'easyrest-ce'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo esc_url(add_query_arg('edit', $context->id)); ?>" class="button button-small">
                                <?php esc_html_e('Edit', 'easyrest-ce'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
