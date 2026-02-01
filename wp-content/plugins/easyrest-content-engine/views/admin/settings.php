<?php
/**
 * Settings View
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap easyrest-ce-admin">
    <h1><?php esc_html_e('Content Engine Settings', 'easyrest-ce'); ?></h1>

    <div class="easyrest-ce-settings">
        <form method="post" action="options.php">

            <!-- API Settings -->
            <h2><?php esc_html_e('OpenAI API Settings', 'easyrest-ce'); ?></h2>
            <?php settings_fields('easyrest_ce_api'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_openai_api_key"><?php esc_html_e('API Key', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               name="easyrest_ce_openai_api_key"
                               id="easyrest_ce_openai_api_key"
                               value="<?php echo esc_attr(get_option('easyrest_ce_openai_api_key', '')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Your OpenAI API key. Get one at', 'easyrest-ce'); ?>
                            <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_openai_model"><?php esc_html_e('Model', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <select name="easyrest_ce_openai_model" id="easyrest_ce_openai_model">
                            <?php foreach ($data['models'] as $model => $label): ?>
                            <option value="<?php echo esc_attr($model); ?>" <?php selected(get_option('easyrest_ce_openai_model', 'gpt-4o-mini'), $model); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_openai_temperature"><?php esc_html_e('Temperature', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="easyrest_ce_openai_temperature"
                               id="easyrest_ce_openai_temperature"
                               value="<?php echo esc_attr(get_option('easyrest_ce_openai_temperature', 0.7)); ?>"
                               step="0.1"
                               min="0"
                               max="2"
                               class="small-text">
                        <p class="description">
                            <?php esc_html_e('0 = deterministic, 2 = creative. Recommended: 0.7', 'easyrest-ce'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Test Connection', 'easyrest-ce'); ?></th>
                    <td>
                        <button type="button" class="button" id="test-api">
                            <?php esc_html_e('Test API', 'easyrest-ce'); ?>
                        </button>
                        <span id="api-test-result"></span>
                    </td>
                </tr>
            </table>

            <!-- Worker Settings -->
            <h2><?php esc_html_e('Worker Settings', 'easyrest-ce'); ?></h2>
            <?php settings_fields('easyrest_ce_worker'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_worker_token"><?php esc_html_e('Worker Token', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="easyrest_ce_worker_token"
                               id="easyrest_ce_worker_token"
                               value="<?php echo esc_attr($data['worker_token']); ?>"
                               class="large-text"
                               readonly>
                        <button type="button" class="button" id="regenerate-token">
                            <?php esc_html_e('Regenerate', 'easyrest-ce'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Use this token in the X-Worker-Token header for cron requests.', 'easyrest-ce'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_worker_timeout"><?php esc_html_e('Worker Timeout', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="easyrest_ce_worker_timeout"
                               id="easyrest_ce_worker_timeout"
                               value="<?php echo esc_attr(get_option('easyrest_ce_worker_timeout', 55)); ?>"
                               class="small-text">
                        <span><?php esc_html_e('seconds', 'easyrest-ce'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Maximum execution time for worker. Set below your server timeout.', 'easyrest-ce'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_max_attempts"><?php esc_html_e('Max Retry Attempts', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="easyrest_ce_max_attempts"
                               id="easyrest_ce_max_attempts"
                               value="<?php echo esc_attr(get_option('easyrest_ce_max_attempts', 3)); ?>"
                               min="1"
                               max="10"
                               class="small-text">
                    </td>
                </tr>
            </table>

            <!-- Publishing Settings -->
            <h2><?php esc_html_e('Publishing Settings', 'easyrest-ce'); ?></h2>
            <?php settings_fields('easyrest_ce_publish'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Auto Publish', 'easyrest-ce'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="easyrest_ce_auto_publish"
                                   value="1"
                                   <?php checked(get_option('easyrest_ce_auto_publish', false)); ?>>
                            <?php esc_html_e('Automatically publish high-quality content', 'easyrest-ce'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_min_auto_publish_score"><?php esc_html_e('Min Auto-Publish Score', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="easyrest_ce_min_auto_publish_score"
                               id="easyrest_ce_min_auto_publish_score"
                               value="<?php echo esc_attr(get_option('easyrest_ce_min_auto_publish_score', 75)); ?>"
                               min="0"
                               max="100"
                               class="small-text">
                        <span>/100</span>
                        <p class="description">
                            <?php esc_html_e('Content scoring above this will be auto-published.', 'easyrest-ce'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_min_quality_score"><?php esc_html_e('Min Quality Score', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="easyrest_ce_min_quality_score"
                               id="easyrest_ce_min_quality_score"
                               value="<?php echo esc_attr(get_option('easyrest_ce_min_quality_score', 60)); ?>"
                               min="0"
                               max="100"
                               class="small-text">
                        <span>/100</span>
                        <p class="description">
                            <?php esc_html_e('Content scoring below this will be marked as failed.', 'easyrest-ce'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_post_author"><?php esc_html_e('Post Author', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <select name="easyrest_ce_post_author" id="easyrest_ce_post_author">
                            <option value="0"><?php esc_html_e('-- Default (First Admin) --', 'easyrest-ce'); ?></option>
                            <?php foreach ($data['authors'] as $author): ?>
                            <option value="<?php echo esc_attr($author->ID); ?>" <?php selected(get_option('easyrest_ce_post_author', 0), $author->ID); ?>>
                                <?php echo esc_html($author->display_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <!-- Content Settings -->
            <h2><?php esc_html_e('Content Settings', 'easyrest-ce'); ?></h2>
            <?php settings_fields('easyrest_ce_content'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Internal Links', 'easyrest-ce'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="easyrest_ce_enable_internal_links"
                                   value="1"
                                   <?php checked(get_option('easyrest_ce_enable_internal_links', true)); ?>>
                            <?php esc_html_e('Automatically add internal links to content', 'easyrest-ce'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_max_internal_links"><?php esc_html_e('Max Internal Links', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="easyrest_ce_max_internal_links"
                               id="easyrest_ce_max_internal_links"
                               value="<?php echo esc_attr(get_option('easyrest_ce_max_internal_links', 3)); ?>"
                               min="0"
                               max="10"
                               class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_booking_url"><?php esc_html_e('Booking URL', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="easyrest_ce_booking_url"
                               id="easyrest_ce_booking_url"
                               value="<?php echo esc_attr(get_option('easyrest_ce_booking_url', '/reservation/')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('URL for booking CTAs in generated content.', 'easyrest-ce'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="easyrest_ce_pexels_api_key"><?php esc_html_e('Pexels API Key', 'easyrest-ce'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               name="easyrest_ce_pexels_api_key"
                               id="easyrest_ce_pexels_api_key"
                               value="<?php echo esc_attr(get_option('easyrest_ce_pexels_api_key', '')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Used to fetch featured images for guides. Leave empty if using wp-config.php.', 'easyrest-ce'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <!-- SEO Plugin Info -->
            <h2><?php esc_html_e('SEO Integration', 'easyrest-ce'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Detected Plugin', 'easyrest-ce'); ?></th>
                    <td>
                        <?php if ($data['seo_plugin']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                            <strong><?php echo esc_html(ucfirst($data['seo_plugin'])); ?></strong>
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color: orange;"></span>
                            <?php esc_html_e('No SEO plugin detected. SEO metadata will be stored in custom fields.', 'easyrest-ce'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <h2><?php esc_html_e('Maintenance', 'easyrest-ce'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Backfill Featured Images', 'easyrest-ce'); ?></th>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('easyrest_ce_backfill_images'); ?>
                        <input type="hidden" name="action" value="easyrest_ce_backfill_images">
                        <label style="display:inline-block;margin-right:10px;">
                            <input type="checkbox" name="force_replace" value="1">
                            <?php esc_html_e('Replace existing featured images', 'easyrest-ce'); ?>
                        </label>
                        <button type="submit" class="button">
                            <?php esc_html_e('Fetch images for existing guides', 'easyrest-ce'); ?>
                        </button>
                        <?php if (!empty($_GET['backfill']) && $_GET['backfill'] === 'done') : ?>
                            <p class="description" style="margin-top: 8px;">
                                <?php echo esc_html(sprintf(__('Backfill complete. Updated %d guides.', 'easyrest-ce'), absint($_GET['count'] ?? 0))); ?>
                            </p>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        </table>
    </div>
</div>
