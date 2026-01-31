<?php
/**
 * Channel Adapter Interface
 *
 * Defines the contract for all channel adapters (WordPress, Facebook, LinkedIn, etc.)
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface EasyRest_CE_Channel_Adapter_Interface
 *
 * All channel adapters must implement this interface to be registered
 * with the Channel Registry and used by the Channel Distributor.
 */
interface EasyRest_CE_Channel_Adapter_Interface {

    /**
     * Get the unique channel identifier
     *
     * @return string Channel slug (e.g., 'wordpress', 'facebook', 'linkedin')
     */
    public function get_channel_id(): string;

    /**
     * Get human-readable channel name
     *
     * @return string Display name (e.g., 'WordPress', 'Facebook Page')
     */
    public function get_channel_name(): string;

    /**
     * Check if the channel is currently enabled
     *
     * @return bool True if channel is active and configured
     */
    public function is_enabled(): bool;

    /**
     * Publish content to this channel
     *
     * @param array                     $content Generated content data
     * @param EasyRest_CE_Queue_Item    $item    Queue item being processed
     * @param EasyRest_CE_Context_Model $context Context model
     * @return array Result with keys: success (bool), message (string), external_id (string|null)
     */
    public function publish(array $content, EasyRest_CE_Queue_Item $item, EasyRest_CE_Context_Model $context): array;

    /**
     * Validate that the channel is properly configured
     *
     * @return array Result with keys: valid (bool), errors (array of strings)
     */
    public function validate_configuration(): array;

    /**
     * Get channel-specific settings schema
     *
     * Used by admin UI to render configuration fields.
     *
     * @return array Array of setting definitions
     */
    public function get_settings_schema(): array;
}
