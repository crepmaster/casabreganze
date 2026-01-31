<?php
/**
 * Admin Controller
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Admin_Controller
 *
 * Handles admin pages and actions
 */
class EasyRest_CE_Admin_Controller {

    /**
     * @var string Menu slug
     */
    private $menu_slug = 'easyrest-ce';

    /**
     * Initialize admin
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_easyrest_ce_action', [$this, 'handle_ajax']);
    }

    /**
     * Register admin menus
     */
    public function register_menus(): void {
        // Main menu
        add_menu_page(
            __('Content Engine', 'easyrest-ce'),
            __('Content Engine', 'easyrest-ce'),
            'manage_options',
            $this->menu_slug,
            [$this, 'render_dashboard'],
            'dashicons-edit-page',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'easyrest-ce'),
            __('Dashboard', 'easyrest-ce'),
            'manage_options',
            $this->menu_slug,
            [$this, 'render_dashboard']
        );

        // Contexts
        add_submenu_page(
            $this->menu_slug,
            __('Contexts', 'easyrest-ce'),
            __('Contexts', 'easyrest-ce'),
            'manage_options',
            $this->menu_slug . '-contexts',
            [$this, 'render_contexts']
        );

        // Queue
        add_submenu_page(
            $this->menu_slug,
            __('Queue', 'easyrest-ce'),
            __('Queue', 'easyrest-ce'),
            'manage_options',
            $this->menu_slug . '-queue',
            [$this, 'render_queue']
        );

        // Logs
        add_submenu_page(
            $this->menu_slug,
            __('Logs', 'easyrest-ce'),
            __('Logs', 'easyrest-ce'),
            'manage_options',
            $this->menu_slug . '-logs',
            [$this, 'render_logs']
        );

        // Settings
        add_submenu_page(
            $this->menu_slug,
            __('Settings', 'easyrest-ce'),
            __('Settings', 'easyrest-ce'),
            'manage_options',
            $this->menu_slug . '-settings',
            [$this, 'render_settings']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook
     */
    public function enqueue_assets(string $hook): void {
        // Only load on our pages
        if (strpos($hook, $this->menu_slug) === false) {
            return;
        }

        wp_enqueue_style(
            'easyrest-ce-admin',
            EASYREST_CE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            EASYREST_CE_VERSION
        );

        wp_enqueue_script(
            'easyrest-ce-admin',
            EASYREST_CE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            EASYREST_CE_VERSION,
            true
        );

        wp_localize_script('easyrest-ce-admin', 'easyrestCE', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('easyrest_ce_admin'),
            'i18n'    => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'easyrest-ce'),
                'processing'     => __('Processing...', 'easyrest-ce'),
                'success'        => __('Success!', 'easyrest-ce'),
                'error'          => __('An error occurred.', 'easyrest-ce'),
            ],
        ]);
    }

    /**
     * Register settings
     *
     * All options use the easyrest_ce_ prefix to avoid collisions.
     */
    public function register_settings(): void {
        // API Settings
        register_setting('easyrest_ce_api', 'easyrest_ce_openai_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('easyrest_ce_api', 'easyrest_ce_openai_model', [
            'type'              => 'string',
            'default'           => 'gpt-4o-mini',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('easyrest_ce_api', 'easyrest_ce_openai_temperature', [
            'type'              => 'number',
            'default'           => 0.7,
            'sanitize_callback' => function ($value) {
                return max(0, min(2, floatval($value)));
            },
        ]);

        // Worker Settings
        register_setting('easyrest_ce_worker', 'easyrest_ce_worker_token', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('easyrest_ce_worker', 'easyrest_ce_worker_timeout', [
            'type'              => 'integer',
            'default'           => 55,
            'sanitize_callback' => 'absint',
        ]);

        register_setting('easyrest_ce_worker', 'easyrest_ce_max_attempts', [
            'type'              => 'integer',
            'default'           => 3,
            'sanitize_callback' => 'absint',
        ]);

        // Publishing Settings
        register_setting('easyrest_ce_publish', 'easyrest_ce_auto_publish', [
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);

        register_setting('easyrest_ce_publish', 'easyrest_ce_min_auto_publish_score', [
            'type'              => 'integer',
            'default'           => 75,
            'sanitize_callback' => 'absint',
        ]);

        register_setting('easyrest_ce_publish', 'easyrest_ce_min_quality_score', [
            'type'              => 'integer',
            'default'           => 60,
            'sanitize_callback' => 'absint',
        ]);

        register_setting('easyrest_ce_publish', 'easyrest_ce_post_author', [
            'type'              => 'integer',
            'default'           => 0,
            'sanitize_callback' => 'absint',
        ]);

        // Content Settings
        register_setting('easyrest_ce_content', 'easyrest_ce_enable_internal_links', [
            'type'              => 'boolean',
            'default'           => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);

        register_setting('easyrest_ce_content', 'easyrest_ce_max_internal_links', [
            'type'              => 'integer',
            'default'           => 3,
            'sanitize_callback' => 'absint',
        ]);

        register_setting('easyrest_ce_content', 'easyrest_ce_booking_url', [
            'type'              => 'string',
            'default'           => '/reservation/',
            'sanitize_callback' => 'esc_url_raw',
        ]);
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard(): void {
        $queue_repo   = new EasyRest_CE_Queue_Repository();
        $context_repo = new EasyRest_CE_Context_Repository();

        $data = [
            'queue_counts'    => $queue_repo->get_status_counts(),
            'contexts'        => $context_repo->get_active_contexts(),
            'total_cost'      => EasyRest_CE_Logger::get_total_cost(),
            'total_tokens'    => EasyRest_CE_Logger::get_total_tokens(),
            'recent_logs'     => EasyRest_CE_Logger::get_recent(10),
            'generator_ready' => (new EasyRest_CE_Content_Generator())->check_readiness(),
            'cron_status'     => [
                'planner'  => wp_next_scheduled('easyrest_ce_planner_cron'),
                'process'  => wp_next_scheduled(EasyRest_CE_Queue_Cron::HOOK_PROCESS_QUEUE),
                'cleanup'  => wp_next_scheduled(EasyRest_CE_Queue_Cron::HOOK_CLEANUP_QUEUE),
            ],
        ];

        include EASYREST_CE_PLUGIN_DIR . 'views/admin/dashboard.php';
    }

    /**
     * Render contexts page
     */
    public function render_contexts(): void {
        $repo = new EasyRest_CE_Context_Repository();

        // Handle actions
        if (isset($_POST['action']) && $_POST['action'] === 'save_context') {
            $this->handle_save_context();
        }

        $data = [
            'contexts' => $repo->get_all(),
            'types'    => ['event_based', 'seasonal', 'evergreen'],
            'statuses' => ['active', 'paused', 'archived'],
        ];

        // Edit mode
        if (isset($_GET['edit'])) {
            $data['editing'] = $repo->get(absint($_GET['edit']));
        }

        include EASYREST_CE_PLUGIN_DIR . 'views/admin/contexts.php';
    }

    /**
     * Render queue page
     */
    public function render_queue(): void {
        $repo = new EasyRest_CE_Queue_Repository();

        // Handle actions
        if (isset($_POST['action'])) {
            $this->handle_queue_action();
        }

        // Filters
        $filters = [
            'status'       => sanitize_text_field($_GET['status'] ?? ''),
            'content_type' => sanitize_text_field($_GET['content_type'] ?? ''),
            'lang'         => sanitize_text_field($_GET['lang'] ?? ''),
        ];

        $args = array_filter($filters);
        $args['limit'] = 50;

        $data = [
            'items'       => $repo->get_all($args),
            'counts'      => $repo->get_status_counts(),
            'filters'     => $filters,
            'statuses'    => [
                EasyRest_CE_Queue_Status::PENDING,
                EasyRest_CE_Queue_Status::LOCKED,
                EasyRest_CE_Queue_Status::GENERATING,
                EasyRest_CE_Queue_Status::REVIEW,
                EasyRest_CE_Queue_Status::PUBLISHED,
                EasyRest_CE_Queue_Status::FAILED,
            ],
            'languages'   => ['en', 'fr', 'it', 'de', 'es'],
        ];

        include EASYREST_CE_PLUGIN_DIR . 'views/admin/queue.php';
    }

    /**
     * Render logs page
     */
    public function render_logs(): void {
        $page  = max(1, absint($_GET['paged'] ?? 1));
        $limit = 50;

        $data = [
            'logs'         => EasyRest_CE_Logger::get_recent($limit, ($page - 1) * $limit),
            'total_cost'   => EasyRest_CE_Logger::get_total_cost(),
            'total_tokens' => EasyRest_CE_Logger::get_total_tokens(),
            'page'         => $page,
        ];

        include EASYREST_CE_PLUGIN_DIR . 'views/admin/logs.php';
    }

    /**
     * Render settings page
     */
    public function render_settings(): void {
        $openai = new EasyRest_CE_OpenAI_Client();
        $seo    = new EasyRest_CE_SEO_Adapter();

        $data = [
            'models'        => $openai->get_available_models(),
            'seo_plugin'    => $seo->get_seo_plugin(),
            'authors'       => get_users(['role__in' => ['administrator', 'editor', 'author']]),
            'worker_token'  => get_option('easyrest_ce_worker_token', ''),
        ];

        include EASYREST_CE_PLUGIN_DIR . 'views/admin/settings.php';
    }

    /**
     * Handle AJAX actions
     */
    public function handle_ajax(): void {
        check_ajax_referer('easyrest_ce_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $action = sanitize_text_field($_POST['ce_action'] ?? '');

        switch ($action) {
            case 'test_api':
                $this->ajax_test_api();
                break;

            case 'run_planner':
                $this->ajax_run_planner();
                break;

            case 'run_worker':
                $this->ajax_run_worker();
                break;

            case 'process_item':
                $this->ajax_process_item();
                break;

            case 'retry_item':
                $this->ajax_retry_item();
                break;

            case 'delete_item':
                $this->ajax_delete_item();
                break;

            case 'regenerate_token':
                $this->ajax_regenerate_token();
                break;

            case 'release_stale_locks':
                $this->ajax_release_stale_locks();
                break;

            default:
                wp_send_json_error(['message' => 'Unknown action']);
        }
    }

    /**
     * Test API connection
     */
    private function ajax_test_api(): void {
        $openai = new EasyRest_CE_OpenAI_Client();
        $result = $openai->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Run planner
     */
    private function ajax_run_planner(): void {
        $planner = new EasyRest_CE_Planner();
        $result  = $planner->run();

        $result['message'] = sprintf(
            __('Planner completed: %d items planned, %d skipped.', 'easyrest-ce'),
            $result['planned'],
            $result['skipped']
        );

        wp_send_json_success($result);
    }

    /**
     * Run worker (processes full batch based on configured settings)
     */
    private function ajax_run_worker(): void {
        $worker = new EasyRest_CE_Worker();
        $result = $worker->run(); // Use configured batch size

        $result['message'] = sprintf(
            __('Worker completed: %d processed (%d succeeded, %d failed). Reason: %s', 'easyrest-ce'),
            $result['processed'],
            $result['succeeded'],
            $result['failed'],
            $result['stopped_reason'] ?? 'unknown'
        );

        wp_send_json_success($result);
    }

    /**
     * Process single item
     */
    private function ajax_process_item(): void {
        $item_id = absint($_POST['item_id'] ?? 0);

        if (!$item_id) {
            wp_send_json_error(['message' => 'Invalid item ID']);
        }

        $worker = new EasyRest_CE_Worker();
        $result = $worker->process_single($item_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Retry failed item
     */
    private function ajax_retry_item(): void {
        $item_id = absint($_POST['item_id'] ?? 0);

        if (!$item_id) {
            wp_send_json_error(['message' => 'Invalid item ID']);
        }

        $repo   = new EasyRest_CE_Queue_Repository();
        $result = $repo->retry($item_id);

        if ($result) {
            wp_send_json_success(['message' => 'Item reset for retry']);
        } else {
            wp_send_json_error(['message' => 'Failed to retry item']);
        }
    }

    /**
     * Delete queue item
     */
    private function ajax_delete_item(): void {
        $item_id = absint($_POST['item_id'] ?? 0);

        if (!$item_id) {
            wp_send_json_error(['message' => 'Invalid item ID']);
        }

        $repo   = new EasyRest_CE_Queue_Repository();
        $result = $repo->delete($item_id);

        if ($result) {
            wp_send_json_success(['message' => 'Item deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete item']);
        }
    }

    /**
     * Regenerate worker token
     */
    private function ajax_regenerate_token(): void {
        $token = bin2hex(random_bytes(32));
        update_option('easyrest_ce_worker_token', $token);

        wp_send_json_success(['token' => $token]);
    }

    /**
     * Release stale locks
     */
    private function ajax_release_stale_locks(): void {
        $repo     = new EasyRest_CE_Queue_Repository();
        $released = $repo->release_stale_locks();

        wp_send_json_success([
            'message'  => sprintf(__('%d stale locks released.', 'easyrest-ce'), $released),
            'released' => $released,
        ]);
    }

    /**
     * Handle save context
     */
    private function handle_save_context(): void {
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'easyrest-ce'));
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'easyrest_save_context')) {
            wp_die(__('Security check failed.', 'easyrest-ce'));
        }

        $repo = new EasyRest_CE_Context_Repository();
        $id   = absint($_POST['context_id'] ?? 0);
        $slug = sanitize_title($_POST['slug'] ?? '');

        // Validate slug uniqueness
        if ($repo->slug_exists($slug, $id)) {
            wp_redirect(admin_url('admin.php?page=easyrest-ce-contexts&edit=' . ($id ?: 'new') . '&error=duplicate_slug'));
            exit;
        }

        // Validate and prepare JSON fields
        $events_json_raw  = wp_unslash($_POST['events_json'] ?? '');
        $venues_json_raw  = wp_unslash($_POST['venues_json'] ?? '');
        $prompts_json_raw = wp_unslash($_POST['prompts_json'] ?? '');

        // Validate JSON if not empty
        $json_error = null;
        if (!empty($events_json_raw) && json_decode($events_json_raw) === null && json_last_error() !== JSON_ERROR_NONE) {
            $json_error = 'events_json';
        }
        if (!empty($venues_json_raw) && json_decode($venues_json_raw) === null && json_last_error() !== JSON_ERROR_NONE) {
            $json_error = 'venues_json';
        }
        if (!empty($prompts_json_raw) && json_decode($prompts_json_raw) === null && json_last_error() !== JSON_ERROR_NONE) {
            $json_error = 'prompts_json';
        }

        if ($json_error) {
            wp_redirect(admin_url('admin.php?page=easyrest-ce-contexts&edit=' . ($id ?: 'new') . '&error=invalid_json&field=' . $json_error));
            exit;
        }

        // Merge venues into events_json if provided (DB stores combined events data)
        $events_data = [];
        if (!empty($events_json_raw)) {
            $events_data = json_decode($events_json_raw, true) ?: [];
        }
        if (!empty($venues_json_raw)) {
            $venues_data = json_decode($venues_json_raw, true);
            if (is_array($venues_data)) {
                $events_data['venues'] = $venues_data;
            }
        }

        // Prepare data matching actual DB columns
        $data = [
            'name'           => sanitize_text_field($_POST['name'] ?? ''),
            'slug'           => $slug,
            'type'           => sanitize_text_field($_POST['type'] ?? 'evergreen'),
            'status'         => sanitize_text_field($_POST['status'] ?? 'active'),
            'date_start'     => sanitize_text_field($_POST['date_start'] ?? '') ?: null,
            'date_end'       => sanitize_text_field($_POST['date_end'] ?? '') ?: null,
            'events_json'    => !empty($events_data) ? wp_json_encode($events_data) : null,
            'prompts_config' => !empty($prompts_json_raw) ? $prompts_json_raw : null, // Already valid JSON string
            'settings'       => null, // Reserved for future use
        ];

        if ($id) {
            $result = $repo->update($id, $data);
        } else {
            $result = $repo->create($data);
        }

        if ($result === false) {
            wp_redirect(admin_url('admin.php?page=easyrest-ce-contexts&edit=' . ($id ?: 'new') . '&error=save_failed'));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=easyrest-ce-contexts&saved=1'));
        exit;
    }

    /**
     * Handle queue actions
     */
    private function handle_queue_action(): void {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'easyrest_queue_action')) {
            return;
        }

        $action = sanitize_text_field($_POST['action']);
        $repo   = new EasyRest_CE_Queue_Repository();

        switch ($action) {
            case 'delete_failed':
                $repo->delete_failed();
                break;

            case 'manual_queue':
                $planner = new EasyRest_CE_Planner();
                $planner->queue_manual(
                    absint($_POST['context_id']),
                    sanitize_text_field($_POST['content_type']),
                    sanitize_text_field($_POST['lang'])
                );
                break;
        }

        wp_redirect(admin_url('admin.php?page=easyrest-ce-queue&actioned=1'));
        exit;
    }
}
