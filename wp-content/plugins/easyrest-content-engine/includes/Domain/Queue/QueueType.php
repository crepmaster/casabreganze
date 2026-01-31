<?php
/**
 * Queue Type Domain Object
 *
 * Represents the types of jobs that can be queued.
 * Extensible via filter hook for plugins/modules.
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EasyRest_CE_Queue_Type
 *
 * Immutable value object representing queue job type.
 */
final class EasyRest_CE_Queue_Type {

    /**
     * Content generation job (existing).
     */
    public const CONTENT_GENERATION = 'content_generation';

    /**
     * Post distribution to external channels.
     */
    public const POST_DISTRIBUTION = 'post_distribution';

    /**
     * Translation job.
     */
    public const TRANSLATION = 'translation';

    /**
     * AI generation job (generic).
     */
    public const AI_GENERATION = 'ai_generation';

    /**
     * Image processing job.
     */
    public const IMAGE_PROCESSING = 'image_processing';

    /**
     * Email notification job.
     */
    public const EMAIL_NOTIFICATION = 'email_notification';

    /**
     * Webhook delivery job.
     */
    public const WEBHOOK_DELIVERY = 'webhook_delivery';

    /**
     * Cleanup/maintenance job.
     */
    public const CLEANUP = 'cleanup';

    /**
     * Core types (always available).
     *
     * @var string[]
     */
    private static array $core_types = [
        self::CONTENT_GENERATION,
        self::POST_DISTRIBUTION,
        self::TRANSLATION,
        self::AI_GENERATION,
        self::IMAGE_PROCESSING,
        self::EMAIL_NOTIFICATION,
        self::WEBHOOK_DELIVERY,
        self::CLEANUP,
    ];

    /**
     * Cached registered types (including extensions).
     *
     * @var string[]|null
     */
    private static ?array $registered_types = null;

    /**
     * Current type value.
     *
     * @var string
     */
    private string $value;

    /**
     * Constructor.
     *
     * @param string $type Type value.
     * @throws InvalidArgumentException If type is invalid.
     */
    public function __construct( string $type ) {
        $type = strtolower( trim( $type ) );

        if ( ! self::is_valid( $type ) ) {
            throw new InvalidArgumentException(
                sprintf( 'Invalid queue type: %s. Registered types: %s', $type, implode( ', ', self::all() ) )
            );
        }

        $this->value = $type;
    }

    /**
     * Get type value.
     *
     * @return string
     */
    public function value(): string {
        return $this->value;
    }

    /**
     * Check if a given type string is valid.
     *
     * @param string $type Type to check.
     * @return bool
     */
    public static function is_valid( string $type ): bool {
        return in_array( strtolower( trim( $type ) ), self::all(), true );
    }

    /**
     * Get all registered type values.
     *
     * Includes core types plus any registered via filter.
     *
     * @return string[]
     */
    public static function all(): array {
        if ( self::$registered_types === null ) {
            self::$registered_types = self::$core_types;

            /**
             * Filter to register additional queue types.
             *
             * @param string[] $types Array of registered type strings.
             */
            self::$registered_types = apply_filters(
                'easyrest_ce_queue_types',
                self::$registered_types
            );

            // Normalize all types
            self::$registered_types = array_map(
                function ( $type ) {
                    return strtolower( trim( (string) $type ) );
                },
                self::$registered_types
            );

            // Remove duplicates and empty values
            self::$registered_types = array_values(
                array_unique( array_filter( self::$registered_types ) )
            );
        }

        return self::$registered_types;
    }

    /**
     * Get core types only.
     *
     * @return string[]
     */
    public static function core(): array {
        return self::$core_types;
    }

    /**
     * Reset cached types (for testing).
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$registered_types = null;
    }

    /**
     * Create from string (factory method).
     *
     * @param string $type Type string.
     * @return self
     */
    public static function from( string $type ): self {
        return new self( $type );
    }

    /**
     * Create CONTENT_GENERATION type.
     *
     * @return self
     */
    public static function content_generation(): self {
        return new self( self::CONTENT_GENERATION );
    }

    /**
     * Create POST_DISTRIBUTION type.
     *
     * @return self
     */
    public static function post_distribution(): self {
        return new self( self::POST_DISTRIBUTION );
    }

    /**
     * Check if this is a content generation type.
     *
     * @return bool
     */
    public function is_content_generation(): bool {
        return $this->value === self::CONTENT_GENERATION;
    }

    /**
     * Check if this is a distribution type.
     *
     * @return bool
     */
    public function is_distribution(): bool {
        return $this->value === self::POST_DISTRIBUTION;
    }

    /**
     * String representation.
     *
     * @return string
     */
    public function __toString(): string {
        return $this->value;
    }
}
