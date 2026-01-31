<?php
/**
 * Queue Repository Interface
 *
 * Contract for queue data access operations.
 * Enables dependency injection and testing.
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface EasyRest_CE_Queue_Repository_Interface
 *
 * Defines the contract for queue repository implementations.
 */
interface EasyRest_CE_Queue_Repository_Interface {

    /**
     * Enqueue a new job.
     *
     * @param array $data Job data including:
     *                    - context_id (int)
     *                    - content_type (string)
     *                    - lang (string)
     *                    - source_ref (string)
     *                    - unique_key (string)
     *                    - priority (int)
     *                    - scheduled_at (string, optional)
     * @return int|false Inserted ID or false on failure.
     */
    public function create( array $data ): int|false;

    /**
     * Get a queue item by ID.
     *
     * @param int $id Item ID.
     * @return EasyRest_CE_Queue_Item|null
     */
    public function get( int $id ): ?EasyRest_CE_Queue_Item;

    /**
     * Fetch and lock the next pending item for processing.
     *
     * This operation MUST be atomic to prevent race conditions.
     * The lock token should be stored in the returned item.
     *
     * @return EasyRest_CE_Queue_Item|null Locked item or null if none available.
     */
    public function lock_next_pending(): ?EasyRest_CE_Queue_Item;

    /**
     * Try to lock a specific item by ID.
     *
     * Uses atomic conditional update to prevent race conditions.
     * Only succeeds if item is in a lockable state (pending/failed).
     *
     * @param int $id Item ID.
     * @return string|false Lock token on success, false on failure.
     */
    public function try_lock_by_id( int $id ): string|false;

    /**
     * Mark item as generating with lock verification.
     *
     * Used for legacy content generation jobs.
     *
     * @param int         $id         Item ID.
     * @param string|null $lock_token Lock token for ownership verification.
     * @return bool
     */
    public function mark_generating( int $id, ?string $lock_token = null ): bool;

    /**
     * Mark item as processing with lock verification.
     *
     * Used for handler-based jobs (non-legacy).
     * Requires lock token (strict mode).
     *
     * @param int    $id         Item ID.
     * @param string $lock_token Lock token for ownership verification.
     * @return bool
     */
    public function mark_processing( int $id, string $lock_token ): bool;

    /**
     * Mark item as completed with lock verification.
     *
     * @param int         $id          Item ID.
     * @param int         $post_id     Created post ID (or result ID).
     * @param int         $tokens_used Tokens/resources used.
     * @param float       $cost        Processing cost.
     * @param string      $status      Final status (published|review|done).
     * @param string|null $lock_token  Lock token for ownership verification.
     * @return bool
     */
    public function mark_completed(
        int $id,
        int $post_id,
        int $tokens_used,
        float $cost,
        string $status = EasyRest_CE_Queue_Status::PUBLISHED,
        ?string $lock_token = null
    ): bool;

    /**
     * Mark item as failed with lock verification.
     *
     * Should implement retry logic with backoff if attempts < max_attempts.
     *
     * @param int         $id                   Item ID.
     * @param string      $error_message        Error message.
     * @param string|null $lock_token           Lock token for ownership verification.
     * @param int|null    $max_attempts         Handler-specific max attempts (null = use global option).
     * @param int|null    $retry_delay_seconds  Custom retry delay in seconds (null = use exponential backoff).
     * @return bool
     */
    public function mark_failed( int $id, string $error_message, ?string $lock_token = null, ?int $max_attempts = null, ?int $retry_delay_seconds = null ): bool;

    /**
     * Mark item as permanently failed (no retry).
     *
     * Used when a handler indicates the job should not be retried.
     * Requires lock token (strict mode).
     *
     * @param int    $id            Item ID.
     * @param string $error_message Error message.
     * @param string $lock_token    Lock token for ownership verification.
     * @return bool
     */
    public function mark_permanent_failure( int $id, string $error_message, string $lock_token ): bool;

    /**
     * Release stale locks.
     *
     * Items locked longer than timeout should be returned to pending.
     *
     * @param int|null $timeout_minutes Override timeout (null = use config).
     * @return int Number of released locks.
     */
    public function release_stale_locks( ?int $timeout_minutes = null ): int;

    /**
     * Transition item state with lock token ownership verification.
     *
     * @param int    $id         Item ID.
     * @param string $lock_token Lock token to verify ownership.
     * @param string $new_status New status.
     * @param array  $extra_data Additional fields to update.
     * @return bool True if transition succeeded.
     */
    public function transition_with_lock(
        int $id,
        string $lock_token,
        string $new_status,
        array $extra_data = []
    ): bool;

    /**
     * Check if a unique key exists.
     *
     * Used for deduplication.
     *
     * @param string $unique_key Unique key to check.
     * @return bool
     */
    public function exists_by_unique_key( string $unique_key ): bool;

    /**
     * Count items by status.
     *
     * @param string|null $status Status to count, or null for all.
     * @return int
     */
    public function count_by_status( ?string $status = null ): int;

    /**
     * Count eligible pending items (ready to process now).
     *
     * @return int
     */
    public function count_eligible_pending(): int;

    /**
     * Get status counts.
     *
     * @return array Associative array of status => count.
     */
    public function get_status_counts(): array;

    /**
     * Delete a queue item.
     *
     * @param int $id Item ID.
     * @return bool
     */
    public function delete( int $id ): bool;

    /**
     * Retry a failed item.
     *
     * Resets attempts and clears error for immediate eligibility.
     *
     * @param int $id Item ID.
     * @return bool
     */
    public function retry( int $id ): bool;

    /**
     * Purge old completed/failed items.
     *
     * @param int $days_done   Days to keep done items.
     * @param int $days_failed Days to keep failed items.
     * @return int Number of purged items.
     */
    public function purge_old( int $days_done = 30, int $days_failed = 60 ): int;
}
