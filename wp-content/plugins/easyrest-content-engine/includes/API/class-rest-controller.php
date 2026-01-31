<?php
/**
 * REST Controller
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_REST_Controller
 *
 * Handles REST API endpoints for external cron
 */
class EasyRest_CE_REST_Controller {

    /**
     * @var string Namespace
     */
    private $namespace = 'easyrest/v1';

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Worker endpoint
        register_rest_route($this->namespace, '/worker', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_worker'],
            'permission_callback' => [$this, 'check_token'],
        ]);

        // Planner endpoint
        register_rest_route($this->namespace, '/planner', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_planner'],
            'permission_callback' => [$this, 'check_token'],
        ]);

        // Status endpoint (GET, no auth required)
        register_rest_route($this->namespace, '/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_status'],
            'permission_callback' => '__return_true',
        ]);

        // Queue status (requires auth)
        register_rest_route($this->namespace, '/queue', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_queue_status'],
            'permission_callback' => [$this, 'check_token'],
        ]);

        // Manual queue (POST, requires auth)
        register_rest_route($this->namespace, '/queue', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_queue_add'],
            'permission_callback' => [$this, 'check_token'],
        ]);

        // Process specific item
        register_rest_route($this->namespace, '/process/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_process_single'],
            'permission_callback' => [$this, 'check_token'],
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // Contexts
        register_rest_route($this->namespace, '/contexts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_get_contexts'],
            'permission_callback' => [$this, 'check_token'],
        ]);

        // Logs
        register_rest_route($this->namespace, '/logs', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_get_logs'],
            'permission_callback' => [$this, 'check_token'],
        ]);
    }

    /**
     * Check worker token
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_token(WP_REST_Request $request): bool {
        return EasyRest_CE_Security::verify_worker_token($request);
    }

    /**
     * Handle worker request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_worker(WP_REST_Request $request): WP_REST_Response {
        $batch_size = (int) ($request->get_param('batch_size') ?? 5);
        $batch_size = min(10, max(1, $batch_size));

        $worker = new EasyRest_CE_Worker();
        $result = $worker->run($batch_size);

        return new WP_REST_Response([
            'success'   => true,
            'processed' => $result['processed'],
            'succeeded' => $result['succeeded'],
            'failed'    => $result['failed'],
            'items'     => array_map(function ($item) {
                return [
                    'id'      => $item['id'],
                    'success' => $item['success'],
                    'post_id' => $item['post_id'],
                    'error'   => $item['error'],
                ];
            }, $result['items']),
        ], 200);
    }

    /**
     * Handle planner request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_planner(WP_REST_Request $request): WP_REST_Response {
        $planner = new EasyRest_CE_Planner();
        $result  = $planner->run();

        return new WP_REST_Response([
            'success' => true,
            'planned' => $result['planned'],
            'skipped' => $result['skipped'],
            'errors'  => $result['errors'],
        ], 200);
    }

    /**
     * Handle status request (public)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_status(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'status'  => 'ok',
            'version' => EASYREST_CE_VERSION,
            'time'    => current_time('mysql'),
        ], 200);
    }

    /**
     * Handle queue status request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_queue_status(WP_REST_Request $request): WP_REST_Response {
        $repo = new EasyRest_CE_Queue_Repository();

        $status = $request->get_param('status');
        $limit  = (int) ($request->get_param('limit') ?? 50);
        $limit  = min(100, max(1, $limit));

        $args = ['limit' => $limit];

        if ($status) {
            $args['status'] = sanitize_text_field($status);
        }

        $items  = $repo->get_all($args);
        $counts = $repo->get_status_counts();

        return new WP_REST_Response([
            'counts' => $counts,
            'items'  => array_map(function ($item) {
                return [
                    'id'           => $item->id,
                    'content_type' => $item->content_type,
                    'lang'         => $item->lang,
                    'status'       => $item->status,
                    'attempts'     => $item->attempts,
                    'scheduled_at' => $item->scheduled_at,
                    'post_id'      => $item->post_id,
                    'last_error'   => $item->last_error,
                ];
            }, $items),
        ], 200);
    }

    /**
     * Handle add to queue request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_queue_add(WP_REST_Request $request): WP_REST_Response {
        $context_id   = (int) $request->get_param('context_id');
        $content_type = sanitize_text_field($request->get_param('content_type'));
        $lang         = sanitize_text_field($request->get_param('lang'));

        if (!$context_id || !$content_type || !$lang) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Missing required parameters: context_id, content_type, lang',
            ], 400);
        }

        $planner = new EasyRest_CE_Planner();
        $item_id = $planner->queue_manual($context_id, $content_type, $lang, [
            'source_ref' => $request->get_param('source_ref') ?? '',
            'priority'   => (int) ($request->get_param('priority') ?? 5),
        ]);

        if ($item_id) {
            return new WP_REST_Response([
                'success' => true,
                'item_id' => $item_id,
            ], 201);
        }

        return new WP_REST_Response([
            'success' => false,
            'error'   => 'Failed to add item to queue (may already exist)',
        ], 400);
    }

    /**
     * Handle process single item request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_process_single(WP_REST_Request $request): WP_REST_Response {
        $item_id = (int) $request->get_param('id');

        $worker = new EasyRest_CE_Worker();
        $result = $worker->process_single($item_id);

        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'post_id' => $result['post_id'],
                'stats'   => $result['stats'],
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'error'   => $result['error'],
        ], 400);
    }

    /**
     * Handle get contexts request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_get_contexts(WP_REST_Request $request): WP_REST_Response {
        $repo     = new EasyRest_CE_Context_Repository();
        $contexts = $repo->get_all();

        return new WP_REST_Response([
            'contexts' => array_map(function ($ctx) {
                return [
                    'id'         => $ctx->id,
                    'name'       => $ctx->name,
                    'slug'       => $ctx->slug,
                    'type'       => $ctx->type,
                    'status'     => $ctx->status,
                    'date_start' => $ctx->date_start,
                    'date_end'   => $ctx->date_end,
                ];
            }, $contexts),
        ], 200);
    }

    /**
     * Handle get logs request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_get_logs(WP_REST_Request $request): WP_REST_Response {
        $limit = (int) ($request->get_param('limit') ?? 50);
        $limit = min(100, max(1, $limit));

        $logs = EasyRest_CE_Logger::get_recent($limit);

        return new WP_REST_Response([
            'total_tokens' => EasyRest_CE_Logger::get_total_tokens(),
            'total_cost'   => EasyRest_CE_Logger::get_total_cost(),
            'logs'         => array_map(function ($log) {
                return [
                    'id'         => $log->id,
                    'action'     => $log->action,
                    'queue_id'   => $log->queue_id,
                    'message'    => $log->message,
                    'tokens'     => $log->tokens_used,
                    'cost'       => $log->cost,
                    'duration'   => $log->duration,
                    'created_at' => $log->created_at,
                ];
            }, $logs),
        ], 200);
    }
}
