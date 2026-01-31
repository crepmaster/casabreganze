<?php
/**
 * WordPress Channel Adapter
 *
 * Publishes content to WordPress as easyrest_guide posts.
 * This is the primary channel that wraps the existing Post Publisher.
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_WordPress_Channel
 *
 * Implements the Channel Adapter interface for WordPress publishing.
 * Delegates actual publishing to the existing EasyRest_CE_Post_Publisher class.
 */
class EasyRest_CE_WordPress_Channel implements EasyRest_CE_Channel_Adapter_Interface {

    /**
     * Channel identifier
     */
    const CHANNEL_ID = 'wordpress';

    /**
     * @var EasyRest_CE_Post_Publisher
     */
    private $publisher;

    /**
     * Constructor
     */
    public function __construct() {
        $this->publisher = new EasyRest_CE_Post_Publisher();
    }

    /**
     * Get the unique channel identifier
     *
     * @return string
     */
    public function get_channel_id(): string {
        return self::CHANNEL_ID;
    }

    /**
     * Get human-readable channel name
     *
     * @return string
     */
    public function get_channel_name(): string {
        return __('WordPress (Guides)', 'easyrest-content-engine');
    }

    /**
     * Check if the channel is currently enabled
     *
     * WordPress channel is always enabled as the primary publishing destination.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return true;
    }

    /**
     * Publish content to WordPress
     *
     * @param array                     $content Generated content data
     * @param EasyRest_CE_Queue_Item    $item    Queue item being processed
     * @param EasyRest_CE_Context_Model $context Context model
     * @return array Result with keys: success, message, external_id
     */
    public function publish(array $content, EasyRest_CE_Queue_Item $item, EasyRest_CE_Context_Model $context): array {
        // Determine publish status based on quality score
        $quality_threshold = (int) get_option('easyrest_ce_quality_threshold', 70);
        $quality_score     = $content['quality_score'] ?? 0;
        $post_status       = $quality_score >= $quality_threshold ? 'publish' : 'draft';

        // Delegate to existing publisher
        $post_id = $this->publisher->publish($content, $item, $context, $post_status);

        if ($post_id === false) {
            return [
                'success'     => false,
                'message'     => __('Failed to create WordPress post', 'easyrest-content-engine'),
                'external_id' => null,
            ];
        }

        $message = $post_status === 'publish'
            ? sprintf(__('Published as post #%d', 'easyrest-content-engine'), $post_id)
            : sprintf(__('Created as draft #%d (quality score below threshold)', 'easyrest-content-engine'), $post_id);

        EasyRest_CE_Logger::info("WordPress channel: {$message}", [
            'post_id'       => $post_id,
            'queue_item_id' => $item->id,
            'quality_score' => $quality_score,
            'post_status'   => $post_status,
        ]);

        return [
            'success'     => true,
            'message'     => $message,
            'external_id' => (string) $post_id,
            'post_id'     => $post_id,
            'post_status' => $post_status,
        ];
    }

    /**
     * Validate that the channel is properly configured
     *
     * WordPress channel requires minimal configuration.
     *
     * @return array
     */
    public function validate_configuration(): array {
        $errors = [];

        // Check if the custom post type is registered
        if (!post_type_exists('easyrest_guide')) {
            $errors[] = __('Custom post type "easyrest_guide" is not registered', 'easyrest-content-engine');
        }

        // Check if required taxonomies exist
        $required_taxonomies = ['easyrest_topic', 'easyrest_lang', 'easyrest_content_type'];
        foreach ($required_taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                $errors[] = sprintf(
                    __('Required taxonomy "%s" is not registered', 'easyrest-content-engine'),
                    $taxonomy
                );
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get channel-specific settings schema
     *
     * @return array
     */
    public function get_settings_schema(): array {
        return [
            [
                'id'          => 'easyrest_ce_quality_threshold',
                'type'        => 'number',
                'label'       => __('Auto-Publish Quality Threshold', 'easyrest-content-engine'),
                'description' => __('Minimum quality score (0-100) for automatic publishing. Below this, posts are saved as drafts.', 'easyrest-content-engine'),
                'default'     => 70,
                'min'         => 0,
                'max'         => 100,
            ],
            [
                'id'          => 'easyrest_ce_post_author',
                'type'        => 'user',
                'label'       => __('Default Post Author', 'easyrest-content-engine'),
                'description' => __('User to assign as author for generated posts.', 'easyrest-content-engine'),
                'default'     => 0,
            ],
        ];
    }

    /**
     * Get the underlying publisher instance
     *
     * Useful for advanced operations like updating or trashing posts.
     *
     * @return EasyRest_CE_Post_Publisher
     */
    public function get_publisher(): EasyRest_CE_Post_Publisher {
        return $this->publisher;
    }
}
