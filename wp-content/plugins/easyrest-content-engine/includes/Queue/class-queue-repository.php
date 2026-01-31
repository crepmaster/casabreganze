<?php
/**
 * Queue Repository
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Queue_Repository
 *
 * Handles database operations for queue items.
 *
 * Supports multi-channel distribution with:
 * - source_payload: LONGTEXT for large JSON data
 * - external_id: Platform-specific IDs from channel adapters
 * - skipped status: Terminal state for disabled/misconfigured channels
 */
class EasyRest_CE_Queue_Repository implements EasyRest_CE_Queue_Repository_Interface {

    /**
     * @var string
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'easyrest_queue';
    }

    /**
     * Get queue item by ID
     *
     * @param int $id
     * @return EasyRest_CE_Queue_Item|null
     */
    public function get(int $id): ?EasyRest_CE_Queue_Item {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );

        if (!$row) {
            return null;
        }

        return EasyRest_CE_Queue_Item::from_row($row);
    }

    /**
     * Get queue items with filters
     *
     * @param array $args
     * @return EasyRest_CE_Queue_Item[]
     */
    public function get_all(array $args = []): array {
        global $wpdb;

        $defaults = [
            'status'       => null,
            'context_id'   => null,
            'content_type' => null,
            'lang'         => null,
            'channel'      => null,
            'orderby'      => 'scheduled_at',
            'order'        => 'ASC',
            'limit'        => 100,
            'offset'       => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where  = ['1=1'];
        $values = [];

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[]      = "status IN ($placeholders)";
                $values       = array_merge($values, $args['status']);
            } else {
                $where[]  = 'status = %s';
                $values[] = $args['status'];
            }
        }

        if ($args['context_id']) {
            $where[]  = 'context_id = %d';
            $values[] = $args['context_id'];
        }

        if ($args['content_type']) {
            $where[]  = 'content_type = %s';
            $values[] = $args['content_type'];
        }

        if ($args['lang']) {
            $where[]  = 'lang = %s';
            $values[] = $args['lang'];
        }

        if ($args['channel']) {
            $where[]  = 'channel = %s';
            $values[] = $args['channel'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby      = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'scheduled_at ASC';

        $sql      = "SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values));

        return array_map([EasyRest_CE_Queue_Item::class, 'from_row'], $rows);
    }

    /**
     * Check if unique key exists
     *
     * @param string $unique_key
     * @return bool
     */
    public function exists_by_unique_key(string $unique_key): bool {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE unique_key = %s",
                $unique_key
            )
        ) > 0;
    }

    /**
     * Create a new queue item
     *
     * @param array $data
     * @return int|false Item ID or false on failure
     */
    public function create(array $data): int|false {
        global $wpdb;

        $defaults = [
            'context_id'     => 0,
            'content_type'   => '',
            'lang'           => 'en',
            'source_ref'     => '',
            'source_payload' => null,
            'unique_key'     => '',
            'channel'        => 'wordpress',
            'priority'       => 5,
            'scheduled_at'   => current_time('mysql'),
            'status'         => EasyRest_CE_Queue_Status::PENDING,
            'external_id'    => null,
            'attempts'       => 0,
            'created_at'     => current_time('mysql'),
            'updated_at'     => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Build insert data and format arrays dynamically
        // This handles nullable fields properly
        $insert_data = [];
        $formats     = [];

        foreach ($data as $key => $value) {
            $insert_data[$key] = $value;

            // Determine format based on key/value
            if (in_array($key, ['context_id', 'priority', 'attempts'], true)) {
                $formats[] = '%d';
            } elseif ($value === null) {
                $formats[] = '%s'; // NULL will be handled by wpdb
            } else {
                $formats[] = '%s';
            }
        }

        $result = $wpdb->insert($this->table, $insert_data, $formats);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Lock the next pending item for processing
     *
     * Only locks items that are:
     * - status = 'pending'
     * - scheduled_at <= now
     * - next_retry_at is null OR next_retry_at <= now (backoff respected)
     *
     * @return EasyRest_CE_Queue_Item|null
     */
    public function lock_next_pending(): ?EasyRest_CE_Queue_Item {
        global $wpdb;

        $lock_token = bin2hex(random_bytes(16));
        $now        = current_time('mysql');

        // Try to lock an item, respecting next_retry_at for backoff
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table}
                SET status = '" . EasyRest_CE_Queue_Status::LOCKED . "', locked_at = %s, lock_token = %s
                WHERE status = '" . EasyRest_CE_Queue_Status::PENDING . "'
                AND scheduled_at <= %s
                AND (next_retry_at IS NULL OR next_retry_at <= %s)
                ORDER BY priority DESC, scheduled_at ASC
                LIMIT 1",
                $now,
                $lock_token,
                $now,
                $now
            )
        );

        if ($result === 0) {
            return null;
        }

        // Fetch the locked item
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE lock_token = %s",
                $lock_token
            )
        );

        if (!$row) {
            return null;
        }

        return EasyRest_CE_Queue_Item::from_row($row);
    }

    /**
     * Count eligible pending items (ready to process now)
     *
     * @return int
     */
    public function count_eligible_pending(): int {
        global $wpdb;

        $now = current_time('mysql');

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                WHERE status = '" . EasyRest_CE_Queue_Status::PENDING . "'
                AND scheduled_at <= %s
                AND (next_retry_at IS NULL OR next_retry_at <= %s)",
                $now,
                $now
            )
        );
    }

    /**
     * Release stale locks
     *
     * Uses the configured lock timeout option.
     *
     * @param int|null $timeout_minutes Optional override for timeout
     * @return int Number of released locks
     */
    public function release_stale_locks(?int $timeout_minutes = null): int {
        global $wpdb;

        if ($timeout_minutes === null) {
            $timeout_minutes = (int) get_option('easyrest_ce_lock_timeout_min', 10);
        }

        $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - ($timeout_minutes * 60));

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table}
                SET status = '" . EasyRest_CE_Queue_Status::PENDING . "', locked_at = NULL, lock_token = NULL
                WHERE status IN ('" . EasyRest_CE_Queue_Status::LOCKED . "', '" . EasyRest_CE_Queue_Status::GENERATING . "')
                AND locked_at < %s",
                $cutoff
            )
        );
    }

    /**
     * Release lock on an item without changing its status
     *
     * Used when an item is locked but cannot be processed yet
     * (e.g., channel not ready). Returns item to pending for retry.
     *
     * @param int         $id         Item ID
     * @param string|null $lock_token Lock token for ownership verification
     * @return bool
     */
    public function release_lock(int $id, ?string $lock_token = null): bool {
        global $wpdb;

        if ($lock_token !== null) {
            // Verify lock ownership before releasing
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table}
                    SET status = '" . EasyRest_CE_Queue_Status::PENDING . "', locked_at = NULL, lock_token = NULL, updated_at = %s
                    WHERE id = %d AND lock_token = %s",
                    current_time('mysql'),
                    $id,
                    $lock_token
                )
            );

            return $result > 0;
        }

        // No lock token, direct update
        return $wpdb->update(
            $this->table,
            [
                'status'     => EasyRest_CE_Queue_Status::PENDING,
                'locked_at'  => null,
                'lock_token' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id]
        ) !== false;
    }

    /**
     * Try to lock a specific item by ID
     *
     * Uses atomic conditional update to prevent race conditions.
     *
     * @param int $id Item ID
     * @return string|false Lock token on success, false on failure
     */
    public function try_lock_by_id(int $id): string|false {
        global $wpdb;

        $lock_token = bin2hex(random_bytes(16));
        $now        = current_time('mysql');

        // Only lock if status is pending or failed (for retry)
        $affected = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table}
                SET status = '" . EasyRest_CE_Queue_Status::LOCKED . "', locked_at = %s, lock_token = %s, updated_at = %s
                WHERE id = %d
                AND status IN ('" . EasyRest_CE_Queue_Status::PENDING . "', '" . EasyRest_CE_Queue_Status::FAILED . "')
                AND (locked_at IS NULL OR lock_token IS NULL)",
                $now,
                $lock_token,
                $now,
                $id
            )
        );

        if ($affected === 0) {
            return false;
        }

        return $lock_token;
    }

    /**
     * Transition item state with lock token ownership verification
     *
     * @param int    $id         Item ID
     * @param string $lock_token Lock token to verify ownership
     * @param string $new_status New status
     * @param array  $extra_data Additional fields to update
     * @return bool True if transition succeeded
     */
    public function transition_with_lock(int $id, string $lock_token, string $new_status, array $extra_data = []): bool {
        global $wpdb;

        $data = array_merge([
            'status'     => $new_status,
            'updated_at' => current_time('mysql'),
        ], $extra_data);

        // Build SET clause
        $set_parts   = [];
        $set_values  = [];

        foreach ($data as $key => $value) {
            $set_parts[] = "$key = %s";
            $set_values[] = $value;
        }

        $set_clause = implode(', ', $set_parts);
        $set_values[] = $id;
        $set_values[] = $lock_token;

        // Use raw query to include lock_token in WHERE clause
        $sql = $wpdb->prepare(
            "UPDATE {$this->table}
            SET $set_clause
            WHERE id = %d AND lock_token = %s",
            ...$set_values
        );

        $affected = $wpdb->query($sql);

        return $affected > 0;
    }

    /**
     * Mark item as generating (with lock verification)
     *
     * @param int    $id         Item ID
     * @param string $lock_token Lock token for ownership verification
     * @return bool
     */
    public function mark_generating(int $id, ?string $lock_token = null): bool {
        global $wpdb;

        // If no lock_token provided, use legacy behavior (for backwards compatibility)
        // But log a warning for debugging
        if ($lock_token === null) {
            error_log('[EasyRest CE] Warning: mark_generating called without lock_token for item ' . $id);
            return $wpdb->update(
                $this->table,
                [
                    'status'     => EasyRest_CE_Queue_Status::GENERATING,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            ) !== false;
        }

        return $this->transition_with_lock($id, $lock_token, EasyRest_CE_Queue_Status::GENERATING);
    }

    /**
     * Mark item as completed (published or review) with lock verification
     *
     * For WordPress channel items only. Use mark_channel_completed() for social channels.
     *
     * @param int    $id         Item ID
     * @param int    $post_id    Created WordPress post ID
     * @param int    $tokens_used Tokens used
     * @param float  $cost       Generation cost
     * @param string $status     New status (published|review)
     * @param string $lock_token Lock token for ownership verification
     * @return bool
     */
    public function mark_completed(int $id, int $post_id, int $tokens_used, float $cost, string $status = EasyRest_CE_Queue_Status::PUBLISHED, ?string $lock_token = null): bool {
        global $wpdb;

        $extra_data = [
            'post_id'         => $post_id,
            'tokens_used'     => $tokens_used,
            'generation_cost' => $cost,
            'locked_at'       => null,
            'lock_token'      => null,
        ];

        // If no lock_token provided, use legacy behavior
        if ($lock_token === null) {
            error_log('[EasyRest CE] Warning: mark_completed called without lock_token for item ' . $id);
            return $wpdb->update(
                $this->table,
                array_merge(['status' => $status, 'updated_at' => current_time('mysql')], $extra_data),
                ['id' => $id],
                ['%s', '%s', '%d', '%d', '%f', '%s', '%s'],
                ['%d']
            ) !== false;
        }

        // Clear lock after completion
        $extra_data_for_transition = [
            'post_id'         => $post_id,
            'tokens_used'     => $tokens_used,
            'generation_cost' => $cost,
        ];

        $result = $this->transition_with_lock($id, $lock_token, $status, $extra_data_for_transition);

        // Clear lock fields after successful transition
        if ($result) {
            $wpdb->update(
                $this->table,
                ['locked_at' => null, 'lock_token' => null],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
        }

        return $result;
    }

    /**
     * Mark social channel item as completed with external_id
     *
     * For social channel items that don't generate content but distribute existing content.
     *
     * @param int         $id              Item ID
     * @param string|null $external_id     Platform-specific ID from channel adapter
     * @param int|null    $parent_post_id  Reference to WordPress post (optional)
     * @param string      $lock_token      Lock token for ownership verification
     * @return bool
     */
    public function mark_channel_completed(int $id, ?string $external_id, ?int $parent_post_id = null, ?string $lock_token = null): bool {
        global $wpdb;

        $extra_data = [
            'external_id'     => $external_id,
            'post_id'         => $parent_post_id, // Reference to parent WP post
            'tokens_used'     => 0,
            'generation_cost' => 0,
        ];

        // If no lock_token provided, use direct update
        if ($lock_token === null) {
            error_log('[EasyRest CE] Warning: mark_channel_completed called without lock_token for item ' . $id);
            return $wpdb->update(
                $this->table,
                array_merge([
                    'status'     => EasyRest_CE_Queue_Status::PUBLISHED,
                    'updated_at' => current_time('mysql'),
                    'locked_at'  => null,
                    'lock_token' => null,
                ], $extra_data),
                ['id' => $id]
            ) !== false;
        }

        $result = $this->transition_with_lock($id, $lock_token, EasyRest_CE_Queue_Status::PUBLISHED, $extra_data);

        // Clear lock fields after successful transition
        if ($result) {
            $wpdb->update(
                $this->table,
                ['locked_at' => null, 'lock_token' => null],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
        }

        return $result;
    }

    /**
     * Mark item as skipped (terminal state, no retries)
     *
     * Use this for disabled or misconfigured channels.
     * Skipped items are NOT retried and do NOT increment attempts.
     *
     * @param int    $id         Item ID
     * @param string $reason     Reason for skipping (stored in last_error)
     * @param string $lock_token Lock token for ownership verification (optional)
     * @return bool
     */
    public function mark_skipped(int $id, string $reason, ?string $lock_token = null): bool {
        global $wpdb;

        $data = [
            'status'     => EasyRest_CE_Queue_Status::SKIPPED,
            'last_error' => mb_substr($reason, 0, 65535),
            'updated_at' => current_time('mysql'),
            'locked_at'  => null,
            'lock_token' => null,
            // Note: Do NOT touch attempts or next_retry_at
        ];

        if ($lock_token !== null) {
            // Verify lock ownership
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table}
                    SET status = '" . EasyRest_CE_Queue_Status::SKIPPED . "', last_error = %s, updated_at = %s, locked_at = NULL, lock_token = NULL
                    WHERE id = %d AND lock_token = %s",
                    $data['last_error'],
                    $data['updated_at'],
                    $id,
                    $lock_token
                )
            );

            return $result > 0;
        }

        // No lock token, direct update
        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        ) !== false;
    }

    /**
     * Mark item as processing with lock verification.
     *
     * Used for handler-based jobs (non-legacy).
     *
     * @param int    $id         Item ID.
     * @param string $lock_token Lock token for ownership verification.
     * @return bool
     */
    public function mark_processing( int $id, string $lock_token ): bool {
        return $this->transition_with_lock( $id, $lock_token, EasyRest_CE_Queue_Status::PROCESSING );
    }

    /**
     * Mark item as permanently failed (no retry).
     *
     * @param int    $id            Item ID.
     * @param string $error_message Error message.
     * @param string $lock_token    Lock token for ownership verification.
     * @return bool
     */
    public function mark_permanent_failure( int $id, string $error_message, string $lock_token ): bool {
        global $wpdb;

        $result = $this->transition_with_lock( $id, $lock_token, EasyRest_CE_Queue_Status::FAILED, [
            'last_error' => mb_substr( $error_message, 0, 65535 ),
        ] );

        if ( $result ) {
            $wpdb->update(
                $this->table,
                [ 'locked_at' => null, 'lock_token' => null ],
                [ 'id' => $id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }

        return $result;
    }

    /**
     * Purge old completed/failed items.
     *
     * @param int $days_done   Days to keep done/published items.
     * @param int $days_failed Days to keep failed items.
     * @return int Number of purged items.
     */
    public function purge_old( int $days_done = 30, int $days_failed = 60 ): int {
        global $wpdb;

        $purged = 0;

        // Purge old done/published items
        $cutoff_done = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days_done * DAY_IN_SECONDS ) );
        $purged += (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE status IN ('" . EasyRest_CE_Queue_Status::PUBLISHED . "', '" . EasyRest_CE_Queue_Status::DONE . "', '" . EasyRest_CE_Queue_Status::REVIEW . "') AND updated_at < %s",
                $cutoff_done
            )
        );

        // Purge old failed items
        $cutoff_failed = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days_failed * DAY_IN_SECONDS ) );
        $purged += (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE status = '" . EasyRest_CE_Queue_Status::FAILED . "' AND updated_at < %s",
                $cutoff_failed
            )
        );

        return $purged;
    }

    /**
     * Mark item as failed with lock verification
     *
     * Implements exponential backoff for retries:
     * - Base delay: easyrest_ce_retry_base_min (default 15 min)
     * - Backoff: base * 2^(attempt-1), capped at easyrest_ce_retry_cap_min (default 240 min / 4 hours)
     * - Example: 15m, 30m, 60m, 120m, 240m (max)
     *
     * @param int      $id                  Item ID
     * @param string   $error_message       Error message
     * @param string   $lock_token          Lock token for ownership verification
     * @param int|null $max_attempts         Handler-specific max attempts (null = use global option)
     * @param int|null $retry_delay_seconds  Custom retry delay in seconds (null = use exponential backoff)
     * @return bool
     */
    public function mark_failed(int $id, string $error_message, ?string $lock_token = null, ?int $max_attempts = null, ?int $retry_delay_seconds = null): bool {
        global $wpdb;

        // Get current attempts
        $item = $this->get($id);
        if (!$item) {
            return false;
        }

        $new_attempts    = $item->attempts + 1;
        $effective_max   = $max_attempts ?? (int) get_option('easyrest_ce_max_attempts', 5);
        $new_status      = $new_attempts >= $effective_max ? EasyRest_CE_Queue_Status::FAILED : EasyRest_CE_Queue_Status::PENDING;

        // Calculate retry delay
        $next_retry_at = null;
        if ($new_status === EasyRest_CE_Queue_Status::PENDING) {
            if ($retry_delay_seconds !== null) {
                $next_retry_at = date('Y-m-d H:i:s', current_time('timestamp') + $retry_delay_seconds);
            } else {
                $next_retry_at = $this->calculate_backoff($new_attempts);
            }
        }

        $extra_data = [
            'attempts'      => $new_attempts,
            'last_error'    => mb_substr($error_message, 0, 65535), // Truncate to TEXT limit
            'next_retry_at' => $next_retry_at,
            'locked_at'     => null,
            'lock_token'    => null,
        ];

        // If no lock_token provided, use legacy behavior
        if ($lock_token === null) {
            error_log('[EasyRest CE] Warning: mark_failed called without lock_token for item ' . $id);
            return $wpdb->update(
                $this->table,
                array_merge(['status' => $new_status, 'updated_at' => current_time('mysql')], $extra_data),
                ['id' => $id],
                ['%s', '%s', '%d', '%s', '%s', '%s', '%s'],
                ['%d']
            ) !== false;
        }

        // Use transition with lock, then clear lock fields and set next_retry_at
        $extra_data_for_transition = [
            'attempts'      => $new_attempts,
            'last_error'    => mb_substr($error_message, 0, 65535),
            'next_retry_at' => $next_retry_at,
        ];

        $result = $this->transition_with_lock($id, $lock_token, $new_status, $extra_data_for_transition);

        // Clear lock fields after transition
        if ($result) {
            $wpdb->update(
                $this->table,
                ['locked_at' => null, 'lock_token' => null],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
        }

        return $result;
    }

    /**
     * Calculate exponential backoff for retry
     *
     * @param int $attempt_count Current attempt number (1-based)
     * @return string MySQL datetime for next retry
     */
    private function calculate_backoff($attempt_count) {
        $base_minutes = (int) get_option('easyrest_ce_retry_base_min', 15);
        $cap_minutes  = (int) get_option('easyrest_ce_retry_cap_min', 240);

        // Exponential backoff: base * 2^(attempt-1)
        $delay_minutes = $base_minutes * pow(2, $attempt_count - 1);
        $delay_minutes = min($delay_minutes, $cap_minutes);

        return date('Y-m-d H:i:s', current_time('timestamp') + ($delay_minutes * 60));
    }

    /**
     * Count items by status
     *
     * @param string|null $status
     * @return int
     */
    public function count_by_status(?string $status = null): int {
        global $wpdb;

        if ($status) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
                    $status
                )
            );
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    /**
     * Get status counts
     *
     * @return array
     */
    public function get_status_counts(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status",
            ARRAY_A
        );

        $counts = [
            EasyRest_CE_Queue_Status::PENDING    => 0,
            EasyRest_CE_Queue_Status::LOCKED     => 0,
            EasyRest_CE_Queue_Status::GENERATING => 0,
            EasyRest_CE_Queue_Status::REVIEW     => 0,
            EasyRest_CE_Queue_Status::PUBLISHED  => 0,
            EasyRest_CE_Queue_Status::FAILED     => 0,
            EasyRest_CE_Queue_Status::SKIPPED    => 0,
        ];

        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Delete a queue item
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        global $wpdb;

        return $wpdb->delete($this->table, ['id' => $id], ['%d']) !== false;
    }

    /**
     * Delete failed items
     *
     * @return int Number of deleted items
     */
    public function delete_failed(): int {
        global $wpdb;

        return $wpdb->query("DELETE FROM {$this->table} WHERE status = '" . EasyRest_CE_Queue_Status::FAILED . "'");
    }

    /**
     * Retry a failed item
     *
     * Resets attempts, clears error and next_retry_at for immediate eligibility.
     *
     * @param int $id
     * @return bool
     */
    public function retry(int $id): bool {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            [
                'status'        => EasyRest_CE_Queue_Status::PENDING,
                'attempts'      => 0,
                'last_error'    => null,
                'next_retry_at' => null,
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s', '%s'],
            ['%d']
        ) !== false;
    }
}
