<?php
/**
 * Queue Status Domain Object
 *
 * Represents the possible states of a queue item.
 * Uses class constants for PHP 7.4 compatibility (no enums).
 *
 * ## State Machine
 *
 * The queue follows this state machine:
 *
 *     PENDING → LOCKED → PROCESSING/GENERATING → (PUBLISHED | REVIEW | FAILED)
 *                 ↓                                      ↓
 *              (timeout)                           (if retryable)
 *                 ↓                                      ↓
 *              PENDING ←─────────────────────────────────┘
 *
 * ### Status Descriptions:
 *
 * - PENDING: Item is waiting to be picked up by a worker.
 *            Respects scheduled_at and next_retry_at for backoff.
 *
 * - LOCKED: Item has been claimed by a worker but processing hasn't started.
 *           Includes lock_token for ownership verification.
 *           Times out to PENDING if stale (configurable, default 10 min).
 *
 * - PROCESSING: Item is being processed by a handler-based job (non-legacy).
 *               Used for distribution, translation, etc.
 *
 * - GENERATING: Item is being processed by the legacy content generator.
 *               Reserved for content generation jobs (weekly, sport_guide, etc.)
 *
 * - PUBLISHED: Item completed successfully and content was published.
 *              Terminal status - no further processing.
 *
 * - REVIEW: Item completed but requires manual review (quality threshold not met).
 *           Terminal status - human action required.
 *
 * - FAILED: Item failed after max retries or permanent failure.
 *           Terminal status - requires manual retry or investigation.
 *
 * - DONE: Generic completion status (alias for backward compatibility).
 *         Terminal status.
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EasyRest_CE_Queue_Status
 *
 * Immutable value object representing queue item status.
 */
final class EasyRest_CE_Queue_Status {

    /**
     * Item is waiting to be processed.
     * Initial state for all jobs. Returns here after stale lock release or retryable failure.
     */
    public const PENDING = 'pending';

    /**
     * Item is locked by a worker (pre-processing).
     * Includes lock_token for ownership verification.
     */
    public const LOCKED = 'locked';

    /**
     * Item is actively being processed by a handler-based job.
     * Used for non-legacy job types (distribution, translation, etc.)
     */
    public const PROCESSING = 'processing';

    /**
     * Item is actively being processed by the legacy content generator.
     * Reserved for legacy content types (weekly, sport_guide, etc.)
     */
    public const GENERATING = 'generating';

    /**
     * Item completed successfully (generic).
     * Terminal status. Kept for backward compatibility.
     */
    public const DONE = 'done';

    /**
     * Item completed and content was published.
     * Terminal status.
     */
    public const PUBLISHED = 'published';

    /**
     * Item completed but requires manual review.
     * Terminal status - human action required.
     */
    public const REVIEW = 'review';

    /**
     * Item failed after max retries or permanent failure.
     * Terminal status.
     */
    public const FAILED = 'failed';

    /**
     * Item was skipped (disabled/misconfigured channel).
     * Terminal status - no retries.
     */
    public const SKIPPED = 'skipped';

    /**
     * All valid status values.
     *
     * @var string[]
     */
    private static array $valid_statuses = [
        self::PENDING,
        self::LOCKED,
        self::PROCESSING,
        self::GENERATING,
        self::DONE,
        self::PUBLISHED,
        self::REVIEW,
        self::FAILED,
        self::SKIPPED,
    ];

    /**
     * Terminal statuses (no further automatic processing).
     * Items in these states require manual action or are complete.
     *
     * @var string[]
     */
    private static array $terminal_statuses = [
        self::DONE,
        self::PUBLISHED,
        self::REVIEW, // Terminal: requires human review
        self::FAILED,
        self::SKIPPED,
    ];

    /**
     * Active statuses (currently being worked on by a worker).
     * Items in these states have an active lock.
     *
     * @var string[]
     */
    private static array $active_statuses = [
        self::LOCKED,
        self::PROCESSING,
        self::GENERATING,
    ];

    /**
     * Current status value.
     *
     * @var string
     */
    private string $value;

    /**
     * Constructor.
     *
     * @param string $status Status value.
     * @throws InvalidArgumentException If status is invalid.
     */
    public function __construct( string $status ) {
        $status = strtolower( trim( $status ) );

        if ( ! self::is_valid( $status ) ) {
            throw new InvalidArgumentException(
                sprintf( 'Invalid queue status: %s', $status )
            );
        }

        $this->value = $status;
    }

    /**
     * Get status value.
     *
     * @return string
     */
    public function value(): string {
        return $this->value;
    }

    /**
     * Check if status is terminal (no further processing).
     *
     * @return bool
     */
    public function is_terminal(): bool {
        return in_array( $this->value, self::$terminal_statuses, true );
    }

    /**
     * Check if status is active (being processed).
     *
     * @return bool
     */
    public function is_active(): bool {
        return in_array( $this->value, self::$active_statuses, true );
    }

    /**
     * Check if status is pending.
     *
     * @return bool
     */
    public function is_pending(): bool {
        return $this->value === self::PENDING;
    }

    /**
     * Check if status is failed.
     *
     * @return bool
     */
    public function is_failed(): bool {
        return $this->value === self::FAILED;
    }

    /**
     * Check if status indicates work is in progress.
     *
     * @return bool
     */
    public function is_working(): bool {
        return $this->value === self::PROCESSING || $this->value === self::GENERATING;
    }

    /**
     * Check if status is needs review.
     *
     * @return bool
     */
    public function needs_review(): bool {
        return $this->value === self::REVIEW;
    }

    /**
     * Check if a given status string is valid.
     *
     * @param string $status Status to check.
     * @return bool
     */
    public static function is_valid( string $status ): bool {
        return in_array( strtolower( trim( $status ) ), self::$valid_statuses, true );
    }

    /**
     * Get all valid status values.
     *
     * @return string[]
     */
    public static function all(): array {
        return self::$valid_statuses;
    }

    /**
     * Get terminal status values.
     *
     * @return string[]
     */
    public static function terminals(): array {
        return self::$terminal_statuses;
    }

    /**
     * Get active status values.
     *
     * @return string[]
     */
    public static function actives(): array {
        return self::$active_statuses;
    }

    /**
     * Create from string (factory method).
     *
     * @param string $status Status string.
     * @return self
     */
    public static function from( string $status ): self {
        return new self( $status );
    }

    /**
     * Create PENDING status.
     *
     * @return self
     */
    public static function pending(): self {
        return new self( self::PENDING );
    }

    /**
     * Create FAILED status.
     *
     * @return self
     */
    public static function failed(): self {
        return new self( self::FAILED );
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
