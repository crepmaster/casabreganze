<?php
/**
 * Abstract Social Channel
 *
 * Base class for social media channel adapters.
 * Provides common functionality for Facebook, LinkedIn, Reddit, etc.
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Class EasyRest_CE_Abstract_Social_Channel
 *
 * Base implementation for social media channels.
 * Subclasses must implement get_channel_id(), get_channel_name(), and get_settings_schema().
 */
abstract class EasyRest_CE_Abstract_Social_Channel implements EasyRest_CE_Channel_Adapter_Interface {

    /**
     * Option key for enabled state
     *
     * @return string
     */
    protected function get_enabled_option_key(): string {
        return 'easyrest_ce_channel_' . $this->get_channel_id() . '_enabled';
    }

    /**
     * Check if the channel is currently enabled
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool) get_option($this->get_enabled_option_key(), false);
    }

    /**
     * Publish content to this social channel
     *
     * STUB IMPLEMENTATION: Logs the action and returns success without calling real APIs.
     * Real implementations will be added in future phases.
     *
     * @param array                     $content Generated content data
     * @param EasyRest_CE_Queue_Item    $item    Queue item being processed
     * @param EasyRest_CE_Context_Model $context Context model
     * @return array Result with keys: success, message, external_id
     */
    public function publish(array $content, EasyRest_CE_Queue_Item $item, EasyRest_CE_Context_Model $context): array {
        $channel_id   = $this->get_channel_id();
        $channel_name = $this->get_channel_name();

        // Log the stub publish action
        EasyRest_CE_Logger::info("[STUB] {$channel_name} publish called", [
            'channel'       => $channel_id,
            'queue_item_id' => $item->id,
            'content_type'  => $content['content_type'] ?? 'unknown',
            'title'         => $content['title'] ?? 'untitled',
            'lang'          => $content['lang'] ?? 'en',
            'context'       => $context->slug,
        ]);

        // Generate deterministic external_id for stub mode
        // Format: {channel}_stub_{queue_item_id}_{timestamp}
        // This is idempotent for retries within the same second
        $external_id = sprintf(
            '%s_stub_%d_%d',
            $channel_id,
            $item->id,
            time()
        );

        return [
            'success'     => true,
            'message'     => sprintf(
                __('[STUB] Content queued for %s (no real API call)', 'easyrest-content-engine'),
                $channel_name
            ),
            'external_id' => $external_id,
            'stub'        => true,
        ];
    }

    /**
     * Validate that the channel is properly configured
     *
     * STUB IMPLEMENTATION: Returns valid=true for stub channels.
     * Real implementations will validate API credentials here.
     *
     * The 'valid' flag determines whether items should be processed or skipped:
     * - valid=true: Channel is ready, process items
     * - valid=false: Channel is misconfigured, mark items as 'skipped'
     *
     * @return array ['valid' => bool, 'message' => string|null, 'errors' => array, 'warnings' => array, 'stub' => bool]
     */
    public function validate_configuration(): array {
        $errors   = [];
        $warnings = [];

        // Note: Enabled state is checked separately by the worker
        // This method validates configuration assuming channel is enabled

        // Stub channels are always valid (no API credentials required)
        // Real implementations should check:
        // - API credentials exist
        // - OAuth tokens are not expired
        // - Required scopes are available

        $warnings[] = sprintf(
            __('%s API integration not yet implemented (stub mode)', 'easyrest-content-engine'),
            $this->get_channel_name()
        );

        return [
            'valid'    => true, // Stubs are always valid
            'message'  => null,
            'errors'   => $errors,
            'warnings' => $warnings,
            'stub'     => true,
        ];
    }

    /**
     * Transform content for social media format
     *
     * Converts full article content to social-appropriate format.
     * Subclasses can override for channel-specific transformations.
     *
     * @param array $content Original content data
     * @return array Transformed content for social posting
     */
    protected function transform_content_for_social(array $content): array {
        return [
            'title'   => $content['title'] ?? '',
            'excerpt' => $content['excerpt'] ?? $this->generate_excerpt($content['body'] ?? '', 280),
            'link'    => $content['permalink'] ?? '',
            'image'   => $content['featured_image'] ?? null,
            'hashtags' => $this->extract_hashtags($content),
        ];
    }

    /**
     * Generate excerpt from body content
     *
     * @param string $body      Full content body
     * @param int    $max_chars Maximum characters
     * @return string
     */
    protected function generate_excerpt(string $body, int $max_chars = 280): string {
        $text = wp_strip_all_tags($body);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) <= $max_chars) {
            return $text;
        }

        $excerpt = substr($text, 0, $max_chars - 3);
        $last_space = strrpos($excerpt, ' ');

        if ($last_space !== false) {
            $excerpt = substr($excerpt, 0, $last_space);
        }

        return $excerpt . '...';
    }

    /**
     * Extract hashtags from content
     *
     * @param array $content Content data
     * @return array
     */
    protected function extract_hashtags(array $content): array {
        $hashtags = [];

        // Add content type as hashtag
        if (!empty($content['content_type'])) {
            $hashtags[] = '#' . str_replace(['_', '-'], '', $content['content_type']);
        }

        // Add language
        if (!empty($content['lang'])) {
            $hashtags[] = '#' . $content['lang'];
        }

        // Common hashtags for EasyRest content
        $hashtags[] = '#Milan';
        $hashtags[] = '#EasyRest';

        return array_unique($hashtags);
    }
}
