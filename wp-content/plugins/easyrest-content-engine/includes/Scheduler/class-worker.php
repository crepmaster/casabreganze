<?php
/**
 * Worker
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Worker
 *
 * Processes queue items and generates content
 */
class EasyRest_CE_Worker {

    /**
     * @var EasyRest_CE_Queue_Repository
     */
    private $queue_repo;

    /**
     * @var EasyRest_CE_Context_Repository
     */
    private $context_repo;

    /**
     * @var EasyRest_CE_Content_Generator
     */
    private $generator;

    /**
     * @var EasyRest_CE_Quality_Scorer
     */
    private $scorer;

    /**
     * @var EasyRest_CE_Post_Publisher
     */
    private $publisher;

    /**
     * @var EasyRest_CE_Channel_Registry
     */
    private $channel_registry;

    /**
     * @var int Max execution time in seconds (overall timeout)
     */
    private $max_execution_time;

    /**
     * @var int Time budget in seconds (for batch processing)
     */
    private $time_budget;

    /**
     * @var int Default batch size
     */
    private $batch_size;

    /**
     * @var int Start time
     */
    private $start_time;

    /**
     * Constructor
     */
    public function __construct() {
        $this->queue_repo   = new EasyRest_CE_Queue_Repository();
        $this->context_repo = new EasyRest_CE_Context_Repository();
        $this->generator    = new EasyRest_CE_Content_Generator();
        $this->scorer       = new EasyRest_CE_Quality_Scorer();

        // Publisher will be created when needed to avoid circular dependencies
        $this->publisher = null;

        // Channel registry for multi-channel support
        $this->channel_registry = EasyRest_CE_Channel_Registry::instance();

        $this->max_execution_time = (int) get_option('easyrest_ce_worker_timeout', 55);
        $this->time_budget        = (int) get_option('easyrest_ce_worker_time_budget', 25);
        $this->batch_size         = (int) get_option('easyrest_ce_worker_batch_size', 3);
    }

    /**
     * Run worker cycle
     *
     * Processes up to batch_size items, stopping early if:
     * - time_budget is exceeded
     * - max_execution_time is exceeded
     * - no more eligible items
     *
     * @param int|null $batch_size Maximum items to process (null = use configured default)
     * @return array ['processed' => int, 'succeeded' => int, 'failed' => int, 'items' => array, 'stale_released' => int]
     */
    public function run(?int $batch_size = null): array {
        $this->start_time = time();

        if ($batch_size === null) {
            $batch_size = $this->batch_size;
        }

        $results = [
            'processed'      => 0,
            'succeeded'      => 0,
            'failed'         => 0,
            'items'          => [],
            'stale_released' => 0,
            'stopped_reason' => null,
        ];

        // Release stale locks first
        $released = $this->queue_repo->release_stale_locks();
        $results['stale_released'] = $released;
        if ($released > 0) {
            EasyRest_CE_Logger::log('worker_run', 0, 0, "Released {$released} stale locks");
        }

        // Check generator readiness for WordPress channel items
        // NOTE: We no longer block the entire worker if generator is not ready.
        // Instead, we check per-item and allow social channel items to process.
        $generator_readiness = $this->generator->check_readiness();
        $generator_ready     = $generator_readiness['ready'];

        // Process items
        for ($i = 0; $i < $batch_size; $i++) {
            // Check time budget (soft limit for batch processing)
            if ($this->is_time_budget_exceeded()) {
                $results['stopped_reason'] = 'time_budget';
                break;
            }

            // Check max execution time (hard limit)
            if ($this->is_time_exceeded()) {
                $results['stopped_reason'] = 'max_execution_time';
                break;
            }

            // Get next eligible item (respects next_retry_at backoff)
            $item = $this->queue_repo->lock_next_pending();

            if (!$item) {
                $results['stopped_reason'] = 'no_more_items';
                break;
            }

            // Per-channel readiness gating
            $channel_ready = $this->check_channel_readiness($item, $generator_ready, $generator_readiness);

            if (!$channel_ready['ready']) {
                // Mark as skipped if channel is permanently unavailable
                if ($channel_ready['skip']) {
                    $this->queue_repo->mark_skipped($item->id, $channel_ready['reason'], $item->lock_token);
                    EasyRest_CE_Logger::log(
                        'channel_skipped',
                        $item->id,
                        0,
                        $channel_ready['reason'],
                        ['channel' => $item->channel]
                    );
                } else {
                    // Release lock for retry later (don't count as processed)
                    $this->queue_repo->release_lock($item->id, $item->lock_token);
                }
                continue;
            }

            $item_result = $this->process_item($item);

            $results['processed']++;
            $results['items'][] = $item_result;

            if ($item_result['success']) {
                $results['succeeded']++;
            } else {
                $results['failed']++;
            }
        }

        if ($results['stopped_reason'] === null && $results['processed'] >= $batch_size) {
            $results['stopped_reason'] = 'batch_complete';
        }

        // Log worker run
        $duration = time() - $this->start_time;
        EasyRest_CE_Logger::log(
            'worker_run',
            0,
            0,
            sprintf(
                'Processed %d items: %d succeeded, %d failed (%s)',
                $results['processed'],
                $results['succeeded'],
                $results['failed'],
                $results['stopped_reason'] ?? 'unknown'
            ),
            [
                'duration'       => $duration,
                'stale_released' => $released,
                'stopped_reason' => $results['stopped_reason'],
            ]
        );

        return $results;
    }

    /**
     * Check if time budget exceeded (for batch processing)
     *
     * @return bool
     */
    private function is_time_budget_exceeded(): bool {
        return (time() - $this->start_time) >= $this->time_budget;
    }

    /**
     * Process a single queue item
     *
     * Routes to appropriate handler based on channel:
     * - 'wordpress': Full generation flow (generate → score → publish)
     * - Other channels: Distribution flow (publish pre-generated content to social)
     *
     * @param EasyRest_CE_Queue_Item $item
     * @return array
     */
    private function process_item(EasyRest_CE_Queue_Item $item): array {
        $result = [
            'id'           => $item->id,
            'content_type' => $item->content_type,
            'lang'         => $item->lang,
            'channel'      => $item->channel,
            'success'      => false,
            'error'        => null,
            'post_id'      => null,
            'external_id'  => null,
            'stats'        => [],
        ];

        // Get lock token from item (set by lock_next_pending or try_lock_by_id)
        $lock_token = $item->lock_token;

        if (empty($lock_token)) {
            $result['error'] = 'Item does not have a valid lock token';
            EasyRest_CE_Logger::log(
                'generation_error',
                $item->id,
                0,
                'Missing lock token - cannot process',
                ['item' => $item->unique_key, 'channel' => $item->channel]
            );
            return $result;
        }

        // Route handler-based jobs through JobHandlerRegistry (skip generator readiness)
        $registry = EasyRest_CE_Job_Handler_Registry::instance();
        if ($registry->has($item->content_type) && $item->content_type !== 'content_generation') {
            return $this->process_handler_item($item, $lock_token, $result, $registry);
        }

        // Route based on channel
        if ($item->channel !== 'wordpress') {
            return $this->process_channel_item($item, $lock_token, $result);
        }

        // WordPress channel: full generation flow
        try {
            // Mark as generating (with lock verification)
            if (!$this->queue_repo->mark_generating($item->id, $lock_token)) {
                throw new Exception('Failed to transition to generating - lock may have been lost');
            }

            // Get context
            $context = $this->context_repo->get($item->context_id);

            if (!$context) {
                throw new Exception('Context not found: ' . $item->context_id);
            }

            if (!$context->is_active()) {
                throw new Exception('Context is not active: ' . $context->slug);
            }

            // Generate content
            $generation = $this->generator->generate($item, $context);

            if (!$generation['success']) {
                throw new Exception($generation['error']);
            }

            $result['stats'] = $generation['stats'];

            // Score quality
            $quality = $this->scorer->passes_quality($generation['content']);

            if (!$quality['passes']) {
                // Log but don't fail - send to review instead
                EasyRest_CE_Logger::log(
                    'quality_low',
                    $item->id,
                    0,
                    "Quality score {$quality['score']} below threshold",
                    ['breakdown' => $quality['result']['breakdown']]
                );
            }

            // Attach quality score and warnings
            $generation['content']['quality_score'] = $quality['score'];
            $generation['content']['quality']       = $quality['result'];

            // Determine publish status based on quality
            $auto_publish     = (bool) get_option('easyrest_ce_auto_publish', false);
            $min_auto_publish = (int) get_option('easyrest_ce_min_auto_publish_score', 75);

            $should_publish = $auto_publish && $quality['score'] >= $min_auto_publish;
            $publish_status = $should_publish ? 'publish' : 'draft';

            // Publish/create post
            $post_id = $this->publish_content($generation['content'], $item, $context, $publish_status);

            if (!$post_id) {
                throw new Exception('Failed to create post');
            }

            $result['post_id'] = $post_id;

            // Mark as completed (with lock verification)
            $final_status = $should_publish ? EasyRest_CE_Queue_Status::PUBLISHED : EasyRest_CE_Queue_Status::REVIEW;

            $this->queue_repo->mark_completed(
                $item->id,
                $post_id,
                $generation['stats']['tokens_total'],
                $generation['stats']['cost'],
                $final_status,
                $lock_token
            );

            // Log success
            EasyRest_CE_Logger::log(
                'content_generated',
                $item->id,
                $generation['stats']['tokens_total'],
                "Generated {$item->content_type} ({$item->lang})",
                [
                    'post_id'       => $post_id,
                    'quality_score' => $quality['score'],
                    'publish_status' => $publish_status,
                ],
                $generation['stats']['cost'],
                $generation['stats']['duration']
            );

            $result['success'] = true;

            // Notify admin when content is ready for review (draft)
            if ($final_status === EasyRest_CE_Queue_Status::REVIEW) {
                $this->notify_admin_review($post_id, $item, $quality['score']);
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();

            // Mark as failed (with lock verification)
            $this->queue_repo->mark_failed($item->id, $e->getMessage(), $lock_token);

            // Log error
            EasyRest_CE_Logger::log(
                'generation_error',
                $item->id,
                0,
                $e->getMessage(),
                ['item' => $item->unique_key]
            );
        }

        return $result;
    }

    /**
     * Publish content as WordPress post
     *
     * @param array                     $content
     * @param EasyRest_CE_Queue_Item    $item
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $status
     * @return int|false Post ID or false
     */
    private function publish_content(array $content, EasyRest_CE_Queue_Item $item, EasyRest_CE_Context_Model $context, string $status = 'draft'): int|false {
        // Lazy load publisher
        if (!$this->publisher) {
            $this->publisher = new EasyRest_CE_Post_Publisher();
        }

        return $this->publisher->publish($content, $item, $context, $status);
    }

    /**
     * Check if time limit exceeded
     *
     * @return bool
     */
    private function is_time_exceeded(): bool {
        return (time() - $this->start_time) >= $this->max_execution_time;
    }

    /**
     * Process a social channel queue item
     *
     * Social channel items contain pre-generated content (from the WordPress publish).
     * This method delegates to the appropriate channel adapter.
     *
     * @param EasyRest_CE_Queue_Item $item       Queue item
     * @param string                 $lock_token Lock token for ownership
     * @param array                  $result     Result array to populate
     * @return array
     */
    private function process_handler_item(EasyRest_CE_Queue_Item $item, string $lock_token, array $result, EasyRest_CE_Job_Handler_Registry $registry): array {
        try {
            // Mark as processing (handler-based jobs use 'processing' not 'generating')
            if (!$this->queue_repo->mark_processing($item->id, $lock_token)) {
                throw new Exception('Failed to transition to processing - lock may have been lost');
            }

            $payload = json_decode($item->source_ref, true) ?: [];
            $handler = $registry->get($item->content_type);
            $job_result = $handler->handle($payload, $item->id, $item->attempts + 1);

            if ($job_result->is_success()) {
                $this->queue_repo->mark_completed($item->id, 0, 0, 0, EasyRest_CE_Queue_Status::DONE, $lock_token);
                $result['success'] = true;
                $result['stats']   = $job_result->get_data();
            } else {
                if ($job_result->should_retry()) {
                    $max = $handler->get_max_attempts();
                    $this->queue_repo->mark_failed(
                        $item->id,
                        $job_result->get_error(),
                        $lock_token,
                        $max,
                        $job_result->get_retry_delay()
                    );
                } else {
                    $this->queue_repo->mark_permanent_failure($item->id, $job_result->get_error(), $lock_token);
                }
                $result['error'] = $job_result->get_error();
            }
        } catch (Exception $e) {
            $this->queue_repo->mark_failed($item->id, $e->getMessage(), $lock_token);
            $result['error'] = $e->getMessage();
            EasyRest_CE_Logger::log('handler_error', $item->id, 0, $e->getMessage(), [
                'type' => $item->content_type,
            ]);
        }

        return $result;
    }

    /**
     * Process a social channel item
     *
     * @return array
     */
    private function process_channel_item(EasyRest_CE_Queue_Item $item, string $lock_token, array $result): array {
        $channel_id = $item->channel;

        try {
            // Mark as generating (with lock verification)
            if (!$this->queue_repo->mark_generating($item->id, $lock_token)) {
                throw new Exception('Failed to transition to generating - lock may have been lost');
            }

            // Get channel adapter
            $adapter = $this->channel_registry->get($channel_id);

            if (!$adapter) {
                throw new Exception("Channel adapter not found: {$channel_id}");
            }

            if (!$adapter->is_enabled()) {
                throw new Exception("Channel is disabled: {$channel_id}");
            }

            // Get context
            $context = $this->context_repo->get($item->context_id);

            if (!$context) {
                throw new Exception('Context not found: ' . $item->context_id);
            }

            // Load source data using robust method (handles both new and legacy formats)
            $source_data = $item->get_source_payload_array();

            if (!$source_data) {
                throw new Exception('Could not load source payload - invalid or missing JSON');
            }

            if (($source_data['type'] ?? '') !== 'channel_distribution') {
                throw new Exception('Invalid source data type for channel distribution');
            }

            $content_snapshot = $source_data['content_snapshot'] ?? [];

            if (empty($content_snapshot['title'])) {
                throw new Exception('Content snapshot missing required title');
            }

            // Extract parent post ID using helper method
            $parent_post_id = $item->get_wordpress_post_id();

            // Build content array for channel adapter
            $content = [
                'title'          => $content_snapshot['title'],
                'excerpt'        => $content_snapshot['excerpt'] ?? '',
                'body'           => '', // Social channels typically use excerpt
                'permalink'      => $content_snapshot['permalink'] ?? '',
                'featured_image' => $content_snapshot['featured_image'] ?? null,
                'content_type'   => $content_snapshot['content_type'] ?? $item->content_type,
                'lang'           => $content_snapshot['lang'] ?? $item->lang,
                'parent_post_id' => $parent_post_id,
            ];

            // Publish via channel adapter
            $publish_result = $adapter->publish($content, $item, $context);

            if (!$publish_result['success']) {
                throw new Exception($publish_result['message'] ?? 'Channel publish failed');
            }

            $external_id = $publish_result['external_id'] ?? null;
            $result['external_id'] = $external_id;

            // Mark as completed using dedicated channel method
            $this->queue_repo->mark_channel_completed(
                $item->id,
                $external_id,
                $parent_post_id,
                $lock_token
            );

            // Log success
            EasyRest_CE_Logger::log(
                'channel_published',
                $item->id,
                0,
                "Published to {$channel_id}: " . ($publish_result['message'] ?? 'Success'),
                [
                    'channel'        => $channel_id,
                    'external_id'    => $external_id,
                    'parent_post_id' => $parent_post_id,
                    'stub'           => $publish_result['stub'] ?? false,
                ]
            );

            $result['success'] = true;

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();

            // Mark as failed (with lock verification)
            $this->queue_repo->mark_failed($item->id, $e->getMessage(), $lock_token);

            // Log error
            EasyRest_CE_Logger::log(
                'channel_error',
                $item->id,
                0,
                "Channel {$channel_id} failed: " . $e->getMessage(),
                ['channel' => $channel_id, 'item' => $item->unique_key]
            );
        }

        return $result;
    }

    /**
     * Check if a channel is ready to process its item
     *
     * Per-channel readiness gating:
     * - WordPress channel requires generator to be ready
     * - Social channels require adapter to be enabled and configured
     *
     * @param EasyRest_CE_Queue_Item $item                Queue item
     * @param bool                   $generator_ready     Whether generator is ready
     * @param array                  $generator_readiness Full readiness result
     * @return array ['ready' => bool, 'skip' => bool, 'reason' => string]
     */
    private function check_channel_readiness(EasyRest_CE_Queue_Item $item, bool $generator_ready, array $generator_readiness): array {
        $channel_id = $item->channel;

        // WordPress channel: requires generator
        if ($channel_id === 'wordpress') {
            if (!$generator_ready) {
                return [
                    'ready'  => false,
                    'skip'   => false, // Don't skip, just wait for config
                    'reason' => 'Generator not ready: ' . implode(', ', $generator_readiness['errors'] ?? ['unknown']),
                ];
            }
            return ['ready' => true, 'skip' => false, 'reason' => ''];
        }

        // Social channel: check adapter availability
        $adapter = $this->channel_registry->get($channel_id);

        if (!$adapter) {
            return [
                'ready'  => false,
                'skip'   => true, // Adapter doesn't exist - permanent skip
                'reason' => "Channel adapter not registered: {$channel_id}",
            ];
        }

        if (!$adapter->is_enabled()) {
            return [
                'ready'  => false,
                'skip'   => true, // Disabled - permanent skip
                'reason' => "Channel is disabled: {$channel_id}",
            ];
        }

        // Check channel-specific configuration
        $validation = $adapter->validate_configuration();

        if (!$validation['valid']) {
            return [
                'ready'  => false,
                'skip'   => true, // Misconfigured - permanent skip
                'reason' => "Channel misconfigured: " . ($validation['message'] ?? 'invalid configuration'),
            ];
        }

        return ['ready' => true, 'skip' => false, 'reason' => ''];
    }

    /**
     * Process a specific item by ID
     *
     * Uses atomic lock acquisition to prevent race conditions.
     * If the item is already locked by another worker, this will abort.
     *
     * Applies the same channel readiness gating as the cron worker:
     * - WordPress channel: requires generator to be ready
     * - Social channels: requires adapter to be enabled and configured
     *
     * @param int $item_id
     * @return array
     */
    public function process_single(int $item_id): array {
        $this->start_time = time(); // Initialize for time checks

        $item = $this->queue_repo->get($item_id);

        if (!$item) {
            return [
                'success' => false,
                'error'   => 'Item not found',
            ];
        }

        // Skipped items cannot be processed (terminal state)
        if ($item->status === EasyRest_CE_Queue_Status::SKIPPED) {
            return [
                'success' => false,
                'error'   => 'Item is skipped (terminal state) and cannot be processed',
            ];
        }

        // Only allow processing of pending or failed items
        if ($item->status !== EasyRest_CE_Queue_Status::PENDING && $item->status !== EasyRest_CE_Queue_Status::FAILED) {
            return [
                'success' => false,
                'error'   => 'Item cannot be processed (status: ' . $item->status . ')',
            ];
        }

        // Check channel readiness BEFORE acquiring lock (same as cron worker)
        $generator_readiness = $this->generator->check_readiness();
        $generator_ready     = $generator_readiness['ready'];

        $channel_ready = $this->check_channel_readiness($item, $generator_ready, $generator_readiness);

        if (!$channel_ready['ready']) {
            if ($channel_ready['skip']) {
                // Mark as skipped (acquire lock first for atomicity)
                $lock_token = $this->queue_repo->try_lock_by_id($item_id);
                if ($lock_token) {
                    $this->queue_repo->mark_skipped($item->id, $channel_ready['reason'], $lock_token);
                    EasyRest_CE_Logger::log(
                        'channel_skipped',
                        $item->id,
                        0,
                        $channel_ready['reason'],
                        ['channel' => $item->channel, 'triggered_by' => 'process_single']
                    );
                }
                return [
                    'success' => false,
                    'error'   => 'Channel skipped: ' . $channel_ready['reason'],
                    'skipped' => true,
                ];
            }

            // Not ready but not skippable (e.g., generator not configured)
            return [
                'success' => false,
                'error'   => 'Channel not ready: ' . $channel_ready['reason'],
            ];
        }

        // If item is failed, reset attempts before trying to lock
        if ($item->status === EasyRest_CE_Queue_Status::FAILED) {
            $this->queue_repo->retry($item_id);
        }

        // Try to acquire lock atomically
        $lock_token = $this->queue_repo->try_lock_by_id($item_id);

        if ($lock_token === false) {
            return [
                'success' => false,
                'error'   => 'Could not acquire lock - item may already be processing',
            ];
        }

        // Get fresh item with lock token
        $item = $this->queue_repo->get($item_id);

        if (!$item || $item->lock_token !== $lock_token) {
            return [
                'success' => false,
                'error'   => 'Lock verification failed after acquisition',
            ];
        }

        return $this->process_item($item);
    }

    /**
     * Get worker statistics
     *
     * @return array
     */
    public function get_stats(): array {
        return [
            'queue_counts'       => $this->queue_repo->get_status_counts(),
            'generator_ready'    => $this->generator->check_readiness(),
            'max_execution_time' => $this->max_execution_time,
        ];
    }

    /**
     * Notify admin that a guide is ready for review
     *
     * Sends an email with the post title, edit link, and quality score
     * so the admin can review and approve it for publishing.
     *
     * @param int                    $post_id       WordPress post ID
     * @param EasyRest_CE_Queue_Item $item          Queue item
     * @param int                    $quality_score Quality score (0-100)
     */
    private function notify_admin_review(int $post_id, EasyRest_CE_Queue_Item $item, int $quality_score): void {
        $admin_email = get_option('easyrest_ce_admin_email', get_option('admin_email'));

        if (empty($admin_email)) {
            return;
        }

        $post      = get_post($post_id);
        $edit_link = get_edit_post_link($post_id, 'raw');
        $site_name = get_bloginfo('name');

        $subject = sprintf(
            '[%s] New guide ready for review: %s',
            $site_name,
            $post ? $post->post_title : "Post #{$post_id}"
        );

        $body = sprintf(
            "A new guide has been generated and is waiting for your review.\n\n"
            . "Title: %s\n"
            . "Content type: %s\n"
            . "Language: %s\n"
            . "Quality score: %d/100\n\n"
            . "Review and publish: %s\n\n"
            . "---\n"
            . "EasyRest Content Engine",
            $post ? $post->post_title : 'Unknown',
            $item->content_type,
            strtoupper($item->lang),
            $quality_score,
            $edit_link
        );

        wp_mail($admin_email, $subject, $body);
    }
}
