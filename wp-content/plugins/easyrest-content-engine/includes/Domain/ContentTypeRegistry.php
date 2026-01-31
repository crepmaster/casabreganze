<?php
/**
 * Content Type Registry
 *
 * Single source of truth for content types used by both Planner and Worker.
 * Eliminates type drift by centralizing all content type definitions.
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EasyRest_CE_Content_Type_Registry
 *
 * Canonical registry for content types. Both Planner and Worker
 * MUST use this class to determine content type configurations
 * and routing decisions.
 */
final class EasyRest_CE_Content_Type_Registry {

    /**
     * Planner content types with their configuration.
     *
     * These are the ONLY content types that the Planner will enqueue.
     * All of these are handled by the legacy content generator.
     *
     * @var array<string, array{planning_mode: string, lead_days: int, priority: int}>
     */
    private static array $planner_types = [
        'weekly_guide' => [
            'planning_mode' => 'weekly',
            'lead_days'     => 7,
            'priority'      => 8,
        ],
        'sport_guide' => [
            'planning_mode' => 'event_based',
            'lead_days'     => 14,
            'priority'      => 7,
        ],
        'transport_guide' => [
            'planning_mode' => 'event_based',
            'lead_days'     => 21,
            'priority'      => 6,
        ],
        'venue_guide' => [
            'planning_mode' => 'evergreen',
            'lead_days'     => 30,
            'priority'      => 4,
        ],
        'match_preview' => [
            'planning_mode' => 'event_based',
            'lead_days'     => 3,
            'priority'      => 9,
        ],
        'nationality_guide' => [
            'planning_mode' => 'evergreen',
            'lead_days'     => 30,
            'priority'      => 5,
        ],
        'easyrest_guide' => [
            'planning_mode' => 'evergreen',
            'lead_days'     => 30,
            'priority'      => 3,
        ],
        'post' => [
            'planning_mode' => 'evergreen',
            'lead_days'     => 30,
            'priority'      => 2,
        ],
    ];

    /**
     * Legacy aliases for backward compatibility.
     *
     * These are old content type names that may exist in the queue
     * from before the naming standardization. They all route to
     * the legacy content generator.
     *
     * @var string[]
     */
    private static array $legacy_aliases = [
        'weekly',              // old name for weekly_guide
        'transport',           // old name for transport_guide
        'event_guide',         // generic event type
        'neighborhood_guide',  // legacy evergreen type
        'daytrip_guide',       // legacy evergreen type
        'seasonal',            // legacy seasonal type
        'content_generation',  // generic legacy type marker
    ];

    /**
     * Cached combined list of all legacy generator types.
     *
     * @var string[]|null
     */
    private static ?array $all_legacy_types = null;

    /**
     * Get all planner content types with their configurations.
     *
     * Used by Planner to know what content types to schedule.
     *
     * @return array<string, array{planning_mode: string, lead_days: int, priority: int}>
     */
    public static function get_planner_types(): array {
        /**
         * Filter to modify planner content types.
         *
         * @param array $types Planner content type configurations.
         */
        return apply_filters( 'easyrest_ce_planner_content_types', self::$planner_types );
    }

    /**
     * Get planner content type names only (no config).
     *
     * @return string[]
     */
    public static function get_planner_type_names(): array {
        return array_keys( self::get_planner_types() );
    }

    /**
     * Get configuration for a specific planner type.
     *
     * @param string $type Content type name.
     * @return array|null Configuration array or null if not found.
     */
    public static function get_planner_type_config( string $type ): ?array {
        $types = self::get_planner_types();
        return $types[ $type ] ?? null;
    }

    /**
     * Check if a type is a planner type.
     *
     * @param string $type Content type to check.
     * @return bool
     */
    public static function is_planner_type( string $type ): bool {
        return array_key_exists( $type, self::get_planner_types() );
    }

    /**
     * Get all legacy aliases.
     *
     * @return string[]
     */
    public static function get_legacy_aliases(): array {
        /**
         * Filter to add additional legacy aliases.
         *
         * @param string[] $aliases Legacy content type aliases.
         */
        return apply_filters( 'easyrest_ce_legacy_aliases', self::$legacy_aliases );
    }

    /**
     * Get all content types handled by the legacy generator.
     *
     * This includes:
     * - All planner types (they all use legacy generator)
     * - All legacy aliases (backward compatibility)
     *
     * Used by Worker to determine routing.
     *
     * @return string[]
     */
    public static function get_legacy_generator_types(): array {
        if ( self::$all_legacy_types === null ) {
            // Combine planner types + legacy aliases
            self::$all_legacy_types = array_merge(
                self::get_planner_type_names(),
                self::get_legacy_aliases()
            );

            // Remove duplicates and re-index
            self::$all_legacy_types = array_values( array_unique( self::$all_legacy_types ) );
        }

        /**
         * Filter to modify the complete list of legacy generator types.
         *
         * This is the final list used by Worker for routing decisions.
         *
         * @param string[] $types All legacy generator content types.
         */
        return apply_filters( 'easyrest_ce_legacy_content_types', self::$all_legacy_types );
    }

    /**
     * Check if a content type should be handled by the legacy generator.
     *
     * This is the primary method Worker should use for routing decisions.
     *
     * @param string $content_type Content type to check.
     * @return bool True if legacy generator should handle it.
     */
    public static function is_legacy_generator_type( string $content_type ): bool {
        return in_array( $content_type, self::get_legacy_generator_types(), true );
    }

    /**
     * Reset cached data (for testing).
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$all_legacy_types = null;
    }
}
