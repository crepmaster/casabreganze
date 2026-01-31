<?php
/**
 * Queue Self Test
 *
 * Sanity checker for queue functionality.
 * Verifies locking, priorities, backoff, and deduplication.
 *
 * SECURITY: This test writes to the production queue table and should only
 * be executed by administrators via WP Admin or WP-CLI. All test data uses
 * a special content_type prefix and is cleaned up after tests complete.
 *
 * Usage:
 * - Admin UI: Navigate to EasyRest > Tools > Queue Self-Test (requires manage_options)
 * - WP-CLI: wp easyrest queue-test
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EasyRest_CE_Queue_Self_Test
 *
 * Self-test suite for queue operations.
 */
class EasyRest_CE_Queue_Self_Test {

    /**
     * Content type prefix for test items.
     * Used to identify and cleanup test data.
     */
    public const TEST_CONTENT_TYPE = 'test_self_test';

    /**
     * Repository instance.
     *
     * @var EasyRest_CE_Queue_Repository
     */
    private EasyRest_CE_Queue_Repository $repository;

    /**
     * Dispatcher instance.
     *
     * @var EasyRest_CE_Queue_Dispatcher
     */
    private EasyRest_CE_Queue_Dispatcher $dispatcher;

    /**
     * Test results.
     *
     * @var array
     */
    private array $results = [];

    /**
     * Created test item IDs (for cleanup).
     *
     * @var int[]
     */
    private array $test_item_ids = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->repository = new EasyRest_CE_Queue_Repository();
        $this->dispatcher = new EasyRest_CE_Queue_Dispatcher( $this->repository );
    }

    /**
     * Check if current context allows running tests.
     *
     * Tests can only be run from:
     * - WP-CLI
     * - Admin area by user with manage_options capability
     *
     * @return bool True if tests can be run.
     */
    public static function can_run(): bool {
        // Allow from WP-CLI
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }

        // Allow from admin area with proper capability
        if ( is_admin() && current_user_can( 'manage_options' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Run all tests.
     *
     * IMPORTANT: This method should only be called after verifying can_run().
     * For admin UI, also verify nonce before calling.
     *
     * @return array Test results.
     * @throws RuntimeException If access is not allowed.
     */
    public function run(): array {
        // Double-check access (defense in depth)
        if ( ! self::can_run() ) {
            throw new RuntimeException(
                'Queue self-test can only be run from WP-CLI or by admin users with manage_options capability.'
            );
        }

        $this->results = [
            'passed'     => true,
            'tests'      => [],
            'summary'    => '',
            'ran_at'     => current_time( 'mysql' ),
            'context'    => defined( 'WP_CLI' ) && WP_CLI ? 'wp-cli' : 'admin',
        ];

        try {
            $this->test_create_and_retrieve();
            $this->test_priority_ordering();
            $this->test_atomic_locking();
            $this->test_deduplication();
            $this->test_scheduled_delay();
            $this->test_failure_and_retry();
            $this->test_stale_lock_release();
            $this->test_purge_old();

        } catch ( Throwable $e ) {
            $this->add_result( 'unexpected_error', false, 'Unexpected error: ' . $e->getMessage() );
        } finally {
            $this->cleanup();
        }

        // Calculate summary
        $passed = count( array_filter( $this->results['tests'], fn( $t ) => $t['passed'] ) );
        $total  = count( $this->results['tests'] );

        $this->results['summary'] = sprintf( '%d/%d tests passed', $passed, $total );
        $this->results['passed']  = $passed === $total;

        return $this->results;
    }

    /**
     * Test: Create and retrieve queue item.
     */
    private function test_create_and_retrieve(): void {
        $test_name = 'create_and_retrieve';

        $id = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => wp_json_encode( [ 'test' => true ] ),
            'unique_key'   => 'test_' . uniqid(),
            'priority'     => 5,
        ] );

        if ( ! $id ) {
            $this->add_result( $test_name, false, 'Failed to create item' );
            return;
        }

        $this->test_item_ids[] = $id;

        $item = $this->repository->get( $id );

        if ( ! $item ) {
            $this->add_result( $test_name, false, 'Failed to retrieve item' );
            return;
        }

        if ( $item->content_type !== self::TEST_CONTENT_TYPE || $item->status !== EasyRest_CE_Queue_Status::PENDING ) {
            $this->add_result( $test_name, false, 'Item data mismatch' );
            return;
        }

        $this->add_result( $test_name, true, 'Item created and retrieved successfully' );
    }

    /**
     * Test: Priority ordering.
     */
    private function test_priority_ordering(): void {
        $test_name = 'priority_ordering';

        // Create two items with different priorities
        $low_priority_id = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => '{}',
            'unique_key'   => 'test_low_' . uniqid(),
            'priority'     => 1, // Lower number = lower priority in our system (DESC order)
        ] );

        $high_priority_id = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => '{}',
            'unique_key'   => 'test_high_' . uniqid(),
            'priority'     => 10, // Higher number = higher priority
        ] );

        $this->test_item_ids[] = $low_priority_id;
        $this->test_item_ids[] = $high_priority_id;

        // Lock next pending - should get high priority first
        $first = $this->repository->lock_next_pending();

        if ( ! $first ) {
            $this->add_result( $test_name, false, 'Failed to lock first item' );
            return;
        }

        // Check if we got the high priority item
        // Note: lock_next_pending orders by priority DESC, scheduled_at ASC
        if ( $first->id !== $high_priority_id ) {
            $this->add_result( $test_name, false, 'Wrong priority order: expected high priority first' );
            // Release the lock for cleanup
            $this->repository->transition_with_lock( $first->id, $first->lock_token, EasyRest_CE_Queue_Status::PENDING );
            return;
        }

        // Release for cleanup
        $this->repository->transition_with_lock( $first->id, $first->lock_token, EasyRest_CE_Queue_Status::PENDING );

        $this->add_result( $test_name, true, 'Priority ordering works correctly' );
    }

    /**
     * Test: Atomic locking prevents double-processing.
     */
    private function test_atomic_locking(): void {
        $test_name = 'atomic_locking';

        $id = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => '{}',
            'unique_key'   => 'test_lock_' . uniqid(),
            'priority'     => 5,
        ] );

        $this->test_item_ids[] = $id;

        // First lock attempt
        $token1 = $this->repository->try_lock_by_id( $id );

        if ( $token1 === false ) {
            $this->add_result( $test_name, false, 'First lock attempt failed' );
            return;
        }

        // Second lock attempt should fail
        $token2 = $this->repository->try_lock_by_id( $id );

        if ( $token2 !== false ) {
            $this->add_result( $test_name, false, 'Second lock attempt should have failed' );
            return;
        }

        // Verify lock token is correct
        $item = $this->repository->get( $id );

        if ( $item->lock_token !== $token1 ) {
            $this->add_result( $test_name, false, 'Lock token mismatch' );
            return;
        }

        // Transition with wrong token should fail
        $wrong_result = $this->repository->transition_with_lock( $id, 'wrong_token', EasyRest_CE_Queue_Status::GENERATING );

        if ( $wrong_result ) {
            $this->add_result( $test_name, false, 'Transition with wrong token should have failed' );
            return;
        }

        // Transition with correct token should succeed
        $correct_result = $this->repository->transition_with_lock( $id, $token1, EasyRest_CE_Queue_Status::GENERATING );

        if ( ! $correct_result ) {
            $this->add_result( $test_name, false, 'Transition with correct token failed' );
            return;
        }

        $this->add_result( $test_name, true, 'Atomic locking works correctly' );
    }

    /**
     * Test: Deduplication via unique_key.
     */
    private function test_deduplication(): void {
        $test_name  = 'deduplication';
        $unique_key = 'test_dedup_' . uniqid();

        $id1 = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => '{}',
            'unique_key'   => $unique_key,
            'priority'     => 5,
        ] );

        $this->test_item_ids[] = $id1;

        // Check exists
        if ( ! $this->repository->exists_by_unique_key( $unique_key ) ) {
            $this->add_result( $test_name, false, 'exists_by_unique_key returned false for existing key' );
            return;
        }

        // Check non-existent
        if ( $this->repository->exists_by_unique_key( 'nonexistent_' . uniqid() ) ) {
            $this->add_result( $test_name, false, 'exists_by_unique_key returned true for non-existent key' );
            return;
        }

        $this->add_result( $test_name, true, 'Deduplication works correctly' );
    }

    /**
     * Test: Scheduled delay prevents immediate processing.
     */
    private function test_scheduled_delay(): void {
        $test_name = 'scheduled_delay';

        // Create item scheduled for future
        $future = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 3600 ); // 1 hour from now

        $id = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => '{}',
            'unique_key'   => 'test_delay_' . uniqid(),
            'priority'     => 100, // Very high priority
            'scheduled_at' => $future,
        ] );

        $this->test_item_ids[] = $id;

        // Try to lock - should not get this item (scheduled for future)
        $locked = $this->repository->lock_next_pending();

        // If we got an item, it should NOT be our future-scheduled one
        if ( $locked && $locked->id === $id ) {
            $this->add_result( $test_name, false, 'Future-scheduled item was incorrectly locked' );
            $this->repository->transition_with_lock( $locked->id, $locked->lock_token, EasyRest_CE_Queue_Status::PENDING );
            return;
        }

        // Release any locked item
        if ( $locked ) {
            $this->repository->transition_with_lock( $locked->id, $locked->lock_token, EasyRest_CE_Queue_Status::PENDING );
        }

        $this->add_result( $test_name, true, 'Scheduled delay works correctly' );
    }

    /**
     * Test: Failure and retry with backoff.
     */
    private function test_failure_and_retry(): void {
        $test_name = 'failure_and_retry';

        $id = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => '{}',
            'unique_key'   => 'test_fail_' . uniqid(),
            'priority'     => 5,
        ] );

        $this->test_item_ids[] = $id;

        // Lock the item
        $token = $this->repository->try_lock_by_id( $id );

        if ( ! $token ) {
            $this->add_result( $test_name, false, 'Failed to lock item' );
            return;
        }

        // Mark as failed
        $this->repository->mark_failed( $id, 'Test failure', $token );

        // Check status and next_retry_at
        $item = $this->repository->get( $id );

        if ( $item->status !== EasyRest_CE_Queue_Status::PENDING ) {
            $this->add_result( $test_name, false, 'Item should be pending after first failure' );
            return;
        }

        if ( $item->attempts !== 1 ) {
            $this->add_result( $test_name, false, 'Attempts should be 1' );
            return;
        }

        if ( empty( $item->next_retry_at ) ) {
            $this->add_result( $test_name, false, 'next_retry_at should be set' );
            return;
        }

        if ( empty( $item->last_error ) ) {
            $this->add_result( $test_name, false, 'last_error should be set' );
            return;
        }

        $this->add_result( $test_name, true, 'Failure and retry works correctly' );
    }

    /**
     * Test: Stale lock release.
     */
    private function test_stale_lock_release(): void {
        $test_name = 'stale_lock_release';

        global $wpdb;
        $table = $wpdb->prefix . 'easyrest_queue';

        $id = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => '{}',
            'unique_key'   => 'test_stale_' . uniqid(),
            'priority'     => 5,
        ] );

        $this->test_item_ids[] = $id;

        // Lock the item
        $this->repository->try_lock_by_id( $id );

        // Manually set locked_at to 20 minutes ago (past the 10 min timeout)
        $old_time = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 20 * 60 ) );

        $wpdb->update(
            $table,
            [ 'locked_at' => $old_time ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Release stale locks
        $released = $this->repository->release_stale_locks( 10 );

        if ( $released < 1 ) {
            $this->add_result( $test_name, false, 'No stale locks released' );
            return;
        }

        // Check item is now pending
        $item = $this->repository->get( $id );

        if ( $item->status !== EasyRest_CE_Queue_Status::PENDING || $item->lock_token !== null ) {
            $this->add_result( $test_name, false, 'Item not properly reset after stale lock release' );
            return;
        }

        $this->add_result( $test_name, true, 'Stale lock release works correctly' );
    }

    /**
     * Test: Purge old items.
     */
    private function test_purge_old(): void {
        $test_name = 'purge_old';

        global $wpdb;
        $table = $wpdb->prefix . 'easyrest_queue';

        // Create an old published item
        $id = $this->repository->create( [
            'context_id'   => 0,
            'content_type' => self::TEST_CONTENT_TYPE,
            'lang'         => 'en',
            'source_ref'   => '{}',
            'unique_key'   => 'test_purge_' . uniqid(),
            'priority'     => 5,
            'status'       => EasyRest_CE_Queue_Status::PUBLISHED,
        ] );

        // Don't add to test_item_ids - we want to test purge

        // Set updated_at to 60 days ago
        $old_time = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 60 * DAY_IN_SECONDS ) );

        $wpdb->update(
            $table,
            [ 'updated_at' => $old_time ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Purge with 30 day retention for done items
        $purged = $this->repository->purge_old( 30, 60 );

        if ( $purged < 1 ) {
            $this->add_result( $test_name, false, 'No items purged' );
            // Cleanup manually
            $this->repository->delete( $id );
            return;
        }

        // Verify item is gone
        $item = $this->repository->get( $id );

        if ( $item !== null ) {
            $this->add_result( $test_name, false, 'Item should have been purged' );
            $this->repository->delete( $id );
            return;
        }

        $this->add_result( $test_name, true, 'Purge old items works correctly' );
    }

    /**
     * Add a test result.
     *
     * @param string $name    Test name.
     * @param bool   $passed  Whether test passed.
     * @param string $message Result message.
     */
    private function add_result( string $name, bool $passed, string $message ): void {
        $this->results['tests'][ $name ] = [
            'passed'  => $passed,
            'message' => $message,
        ];

        if ( ! $passed ) {
            $this->results['passed'] = false;
        }
    }

    /**
     * Cleanup test items.
     *
     * Deletes all tracked test items by ID.
     * Also cleans up any orphaned test items (safety net).
     */
    private function cleanup(): void {
        // Delete tracked test items
        foreach ( $this->test_item_ids as $id ) {
            $this->repository->delete( $id );
        }

        // Safety net: clean up any orphaned test items
        // This handles cases where test was interrupted before cleanup
        $this->cleanup_orphaned_test_items();
    }

    /**
     * Clean up any orphaned test items from previous runs.
     *
     * Removes all items with content_type = TEST_CONTENT_TYPE.
     * This is a safety net for interrupted tests.
     */
    private function cleanup_orphaned_test_items(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'easyrest_queue';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE content_type = %s",
                self::TEST_CONTENT_TYPE
            )
        );
    }

    /**
     * Run cleanup only (without running tests).
     *
     * Useful for cleaning up after a failed test run.
     * Requires same permissions as run().
     *
     * @return int Number of items cleaned up.
     * @throws RuntimeException If access is not allowed.
     */
    public function cleanup_only(): int {
        if ( ! self::can_run() ) {
            throw new RuntimeException(
                'Queue self-test cleanup can only be run from WP-CLI or by admin users with manage_options capability.'
            );
        }

        global $wpdb;

        $table = $wpdb->prefix . 'easyrest_queue';

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE content_type = %s",
                self::TEST_CONTENT_TYPE
            )
        );

        return $deleted ?: 0;
    }
}
