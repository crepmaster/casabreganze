<?php
/**
 * Channel Registry
 *
 * Central registry for all channel adapters.
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Channel_Registry
 *
 * Singleton registry that holds all registered channel adapters.
 * Provides methods to register, retrieve, and query channels.
 */
class EasyRest_CE_Channel_Registry {

    /**
     * Singleton instance
     *
     * @var EasyRest_CE_Channel_Registry|null
     */
    private static $instance = null;

    /**
     * Registered channel adapters
     *
     * @var EasyRest_CE_Channel_Adapter_Interface[]
     */
    private $channels = [];

    /**
     * Get singleton instance
     *
     * @return EasyRest_CE_Channel_Registry
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor (singleton)
     */
    private function __construct() {}

    /**
     * Register a channel adapter
     *
     * @param EasyRest_CE_Channel_Adapter_Interface $adapter Channel adapter instance
     * @return bool True if registered, false if channel ID already exists
     */
    public function register(EasyRest_CE_Channel_Adapter_Interface $adapter): bool {
        $channel_id = $adapter->get_channel_id();

        if (isset($this->channels[$channel_id])) {
            EasyRest_CE_Logger::warning(
                "Channel '{$channel_id}' is already registered. Skipping duplicate.",
                ['existing' => get_class($this->channels[$channel_id]), 'new' => get_class($adapter)]
            );
            return false;
        }

        $this->channels[$channel_id] = $adapter;

        EasyRest_CE_Logger::debug("Channel registered: {$channel_id}", [
            'class' => get_class($adapter),
            'name'  => $adapter->get_channel_name(),
        ]);

        return true;
    }

    /**
     * Get a channel adapter by ID
     *
     * @param string $channel_id Channel identifier
     * @return EasyRest_CE_Channel_Adapter_Interface|null
     */
    public function get(string $channel_id): ?EasyRest_CE_Channel_Adapter_Interface {
        return $this->channels[$channel_id] ?? null;
    }

    /**
     * Check if a channel is registered
     *
     * @param string $channel_id Channel identifier
     * @return bool
     */
    public function has(string $channel_id): bool {
        return isset($this->channels[$channel_id]);
    }

    /**
     * Get all registered channels
     *
     * @return EasyRest_CE_Channel_Adapter_Interface[]
     */
    public function get_all(): array {
        return $this->channels;
    }

    /**
     * Get all enabled channels
     *
     * @return EasyRest_CE_Channel_Adapter_Interface[]
     */
    public function get_enabled(): array {
        return array_filter($this->channels, function ($adapter) {
            return $adapter->is_enabled();
        });
    }

    /**
     * Get channel IDs of all registered channels
     *
     * @return string[]
     */
    public function get_channel_ids(): array {
        return array_keys($this->channels);
    }

    /**
     * Get channel IDs of all enabled channels
     *
     * @return string[]
     */
    public function get_enabled_channel_ids(): array {
        return array_keys($this->get_enabled());
    }

    /**
     * Get channels as options array (for admin dropdowns)
     *
     * @param bool $enabled_only Only include enabled channels
     * @return array Associative array of channel_id => channel_name
     */
    public function get_as_options(bool $enabled_only = false): array {
        $channels = $enabled_only ? $this->get_enabled() : $this->channels;
        $options  = [];

        foreach ($channels as $channel_id => $adapter) {
            $options[$channel_id] = $adapter->get_channel_name();
        }

        return $options;
    }

    /**
     * Validate all registered channels
     *
     * @return array Array of channel_id => validation result
     */
    public function validate_all(): array {
        $results = [];

        foreach ($this->channels as $channel_id => $adapter) {
            $results[$channel_id] = $adapter->validate_configuration();
        }

        return $results;
    }

    /**
     * Unregister a channel (primarily for testing)
     *
     * @param string $channel_id Channel identifier
     * @return bool True if unregistered, false if not found
     */
    public function unregister(string $channel_id): bool {
        if (!isset($this->channels[$channel_id])) {
            return false;
        }

        unset($this->channels[$channel_id]);
        return true;
    }

    /**
     * Clear all registered channels (primarily for testing)
     */
    public function clear(): void {
        $this->channels = [];
    }
}
