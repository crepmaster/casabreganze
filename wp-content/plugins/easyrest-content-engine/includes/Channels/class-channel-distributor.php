<?php
/**
 * Channel Distributor
 *
 * Listens for WordPress content publication and creates queue jobs
 * for enabled social channels.
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Channel_Distributor
 *
 * Hooks into the WordPress publish action and creates queue items
 * for each enabled social media channel.
 */
class EasyRest_CE_Channel_Distributor {

    /**
     * @var EasyRest_CE_Channel_Registry
     */
    private $registry;

    /**
     * @var EasyRest_CE_Queue_Repository
     */
    private $queue_repository;

    /**
     * Constructor
     */
    public function __construct() {
        $this->registry         = EasyRest_CE_Channel_Registry::instance();
        $this->queue_repository = new EasyRest_CE_Queue_Repository();
    }

    /**
     * Initialize hooks
     */
    public function init(): void {
        // Hook into the content published action fired by Post Publisher
        add_action('easyrest_ce_content_published', [$this, 'on_content_published'], 10, 4);
    }

    /**
     * Handle content publication
     *
     * Creates queue items for each enabled social channel when WordPress content is published.
     *
     * @param int                       $post_id WordPress post ID
     * @param array                     $content Generated content data
     * @param EasyRest_CE_Queue_Item    $item    Original queue item (WordPress channel)
     * @param EasyRest_CE_Context_Model $context Context model
     */
    public function on_content_published(int $post_id, array $content, EasyRest_CE_Queue_Item $item, EasyRest_CE_Context_Model $context): void {
        // Only distribute from WordPress channel items
        if ($item->channel !== 'wordpress') {
            EasyRest_CE_Logger::debug('Skipping distribution for non-WordPress channel item', [
                'queue_item_id' => $item->id,
                'channel'       => $item->channel,
            ]);
            return;
        }

        // Get all enabled social channels (excluding WordPress)
        $enabled_channels = $this->registry->get_enabled();
        unset($enabled_channels['wordpress']);

        if (empty($enabled_channels)) {
            EasyRest_CE_Logger::debug('No social channels enabled for distribution', [
                'post_id' => $post_id,
            ]);
            return;
        }

        // Add permalink to content for social sharing
        $content['permalink'] = get_permalink($post_id);
        $content['post_id']   = $post_id;

        // Get featured image if available
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $content['featured_image'] = wp_get_attachment_url($thumbnail_id);
        }

        // Create queue items for each enabled channel
        $created_count = 0;
        foreach ($enabled_channels as $channel_id => $adapter) {
            $result = $this->create_channel_queue_item($channel_id, $content, $item, $context);

            if ($result) {
                $created_count++;
            }
        }

        EasyRest_CE_Logger::info('Channel distribution completed', [
            'post_id'         => $post_id,
            'wordpress_item'  => $item->id,
            'channels_queued' => $created_count,
            'channels_total'  => count($enabled_channels),
        ]);
    }

    /**
     * Create a queue item for a specific channel
     *
     * @param string                    $channel_id Channel identifier
     * @param array                     $content    Content data with permalink added
     * @param EasyRest_CE_Queue_Item    $parent_item Original WordPress queue item
     * @param EasyRest_CE_Context_Model $context    Context model
     * @return int|false New queue item ID or false on failure
     */
    private function create_channel_queue_item(
        string $channel_id,
        array $content,
        EasyRest_CE_Queue_Item $parent_item,
        EasyRest_CE_Context_Model $context
    ): int|false {
        // Generate unique key for this channel distribution
        // Format: {context}|{content_type}|{lang}|{source_ref}|{channel}
        $unique_key = sprintf(
            '%s|%s',
            $parent_item->unique_key,
            $channel_id
        );

        // Check if already queued
        if ($this->queue_repository->exists_by_unique_key($unique_key)) {
            EasyRest_CE_Logger::debug("Channel item already exists in queue", [
                'channel'    => $channel_id,
                'unique_key' => $unique_key,
            ]);
            return false;
        }

        // Build short source reference (VARCHAR safe, max 255 chars)
        $source_ref = sprintf('wp_post:%d', (int) $content['post_id']);

        // Build full payload for source_payload (LONGTEXT, no truncation)
        $source_payload = wp_json_encode([
            'type'              => 'channel_distribution',
            'wordpress_post_id' => (int) $content['post_id'],
            'parent_queue_id'   => $parent_item->id,
            'parent_unique_key' => $parent_item->unique_key,
            'channel'           => $channel_id,
            'created_at'        => current_time('mysql'),
            'content_snapshot'  => [
                'title'          => $content['title'] ?? '',
                'excerpt'        => $content['excerpt'] ?? '',
                'permalink'      => $content['permalink'] ?? '',
                'featured_image' => $content['featured_image'] ?? null,
                'content_type'   => $content['content_type'] ?? '',
                'lang'           => $content['lang'] ?? '',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Create queue item with slight delay (stagger social posts)
        $delay_minutes = $this->get_channel_delay($channel_id);
        $scheduled_at  = date('Y-m-d H:i:s', current_time('timestamp') + ($delay_minutes * 60));

        $queue_item_id = $this->queue_repository->create([
            'context_id'     => $parent_item->context_id,
            'content_type'   => $parent_item->content_type,
            'lang'           => $parent_item->lang,
            'source_ref'     => $source_ref,
            'source_payload' => $source_payload,
            'unique_key'     => $unique_key,
            'channel'        => $channel_id,
            'priority'       => $parent_item->priority,
            'scheduled_at'   => $scheduled_at,
            'status'         => EasyRest_CE_Queue_Status::PENDING,
        ]);

        if ($queue_item_id) {
            EasyRest_CE_Logger::info("Channel queue item created", [
                'new_queue_id'    => $queue_item_id,
                'channel'         => $channel_id,
                'parent_queue_id' => $parent_item->id,
                'scheduled_at'    => $scheduled_at,
            ]);
        } else {
            EasyRest_CE_Logger::error("Failed to create channel queue item", [
                'channel'         => $channel_id,
                'parent_queue_id' => $parent_item->id,
            ]);
        }

        return $queue_item_id;
    }

    /**
     * Get delay in minutes for a channel
     *
     * Staggers social media posts to avoid appearing spammy.
     *
     * @param string $channel_id Channel identifier
     * @return int Delay in minutes
     */
    private function get_channel_delay(string $channel_id): int {
        $delays = [
            'facebook' => 5,   // Post to Facebook 5 minutes after WP publish
            'linkedin' => 15,  // LinkedIn 15 minutes after
            'reddit'   => 30,  // Reddit 30 minutes after
        ];

        return $delays[$channel_id] ?? 10;
    }

    /**
     * Manually trigger distribution for an existing post
     *
     * Useful for distributing content that was published before channels were enabled.
     *
     * @param int      $post_id    WordPress post ID
     * @param string[] $channel_ids Optional specific channels (or all enabled if empty)
     * @return array Results per channel
     */
    public function distribute_existing_post(int $post_id, array $channel_ids = []): array {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'easyrest_guide') {
            return ['error' => 'Invalid post or wrong post type'];
        }

        // Reconstruct content and item from post meta
        $queue_id = get_post_meta($post_id, '_easyrest_queue_id', true);

        if (!$queue_id) {
            return ['error' => 'Post was not created by content engine'];
        }

        $queue_item = $this->queue_repository->get($queue_id);

        if (!$queue_item) {
            return ['error' => 'Original queue item not found'];
        }

        // Build content array from post
        $content = [
            'title'          => $post->post_title,
            'body'           => $post->post_content,
            'excerpt'        => $post->post_excerpt,
            'content_type'   => get_post_meta($post_id, '_easyrest_content_type', true),
            'lang'           => get_post_meta($post_id, '_easyrest_lang', true),
            'quality_score'  => get_post_meta($post_id, '_easyrest_quality_score', true),
            'permalink'      => get_permalink($post_id),
            'post_id'        => $post_id,
        ];

        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $content['featured_image'] = wp_get_attachment_url($thumbnail_id);
        }

        // Get context
        $context_repo = new EasyRest_CE_Context_Repository();
        $context      = $context_repo->get($queue_item->context_id);

        if (!$context) {
            return ['error' => 'Context not found'];
        }

        // Determine channels to distribute to
        if (empty($channel_ids)) {
            $enabled_channels = $this->registry->get_enabled();
            unset($enabled_channels['wordpress']);
            $channel_ids = array_keys($enabled_channels);
        }

        $results = [];
        foreach ($channel_ids as $channel_id) {
            if ($channel_id === 'wordpress') {
                continue;
            }

            $adapter = $this->registry->get($channel_id);
            if (!$adapter || !$adapter->is_enabled()) {
                $results[$channel_id] = ['success' => false, 'error' => 'Channel not available'];
                continue;
            }

            $result = $this->create_channel_queue_item($channel_id, $content, $queue_item, $context);
            $results[$channel_id] = [
                'success'  => $result !== false,
                'queue_id' => $result ?: null,
            ];
        }

        return $results;
    }
}
