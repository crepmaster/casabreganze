<?php
/**
 * Queue Dispatcher Service
 *
 * Provides a clean API for enqueueing jobs.
 * Validates payloads and handles deduplication.
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EasyRest_CE_Queue_Dispatcher
 *
 * Service for dispatching jobs to the queue.
 */
class EasyRest_CE_Queue_Dispatcher {

    /**
     * Repository instance.
     *
     * @var EasyRest_CE_Queue_Repository_Interface
     */
    private EasyRest_CE_Queue_Repository_Interface $repository;

    /**
     * Handler registry instance.
     *
     * @var EasyRest_CE_Job_Handler_Registry|null
     */
    private ?EasyRest_CE_Job_Handler_Registry $handler_registry = null;

    /**
     * Constructor.
     *
     * @param EasyRest_CE_Queue_Repository_Interface|null $repository Optional repository instance.
     */
    public function __construct( ?EasyRest_CE_Queue_Repository_Interface $repository = null ) {
        $this->repository = $repository ?? new EasyRest_CE_Queue_Repository();
    }

    /**
     * Set handler registry for validation.
     *
     * @param EasyRest_CE_Job_Handler_Registry $registry Handler registry.
     * @return self
     */
    public function set_handler_registry( EasyRest_CE_Job_Handler_Registry $registry ): self {
        $this->handler_registry = $registry;
        return $this;
    }

    /**
     * Dispatch a new job to the queue.
     *
     * Note: max_attempts is controlled at the handler level via get_max_attempts().
     * If no handler is registered, the global 'easyrest_ce_max_attempts' option is used.
     *
     * @param string   $type          Job type (must be registered in QueueType).
     * @param array    $payload       Job payload data.
     * @param int      $priority      Priority (lower = higher priority, default 10).
     * @param int|null $delay_seconds Delay before job becomes eligible (null = immediate).
     * @param int      $context_id    Context ID (default 0 for generic jobs).
     * @param string   $lang          Language code (default 'en').
     * @return int|false Inserted job ID or false on failure.
     * @throws InvalidArgumentException If type or payload is invalid.
     */
    public function dispatch(
        string $type,
        array $payload,
        int $priority = 10,
        ?int $delay_seconds = null,
        int $context_id = 0,
        string $lang = 'en'
    ): int|false {
        // Validate type
        if ( ! EasyRest_CE_Queue_Type::is_valid( $type ) ) {
            throw new InvalidArgumentException(
                sprintf( 'Invalid queue type: %s', $type )
            );
        }

        // Validate payload is JSON-serializable
        $payload_json = wp_json_encode( $payload );
        if ( $payload_json === false ) {
            throw new InvalidArgumentException( 'Payload must be JSON-serializable' );
        }

        // Validate with handler if registry available
        if ( $this->handler_registry !== null && $this->handler_registry->has( $type ) ) {
            $handler = $this->handler_registry->get( $type );
            if ( ! $handler->can_handle( $payload ) ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Payload validation failed for type %s. Required keys: %s',
                        $type,
                        implode( ', ', $handler->get_required_keys() )
                    )
                );
            }
        }

        // Generate unique key for deduplication
        $unique_key = $this->generate_unique_key( $type, $payload, $context_id, $lang );

        // Check for duplicates (first-level guard)
        if ( $this->repository->exists_by_unique_key( $unique_key ) ) {
            $this->log( 'dispatch_skipped', 0, "Duplicate job skipped: {$type}", [ 'unique_key' => $unique_key ] );
            return false;
        }

        // Calculate scheduled_at
        $scheduled_at = current_time( 'mysql' );
        if ( $delay_seconds !== null && $delay_seconds > 0 ) {
            $scheduled_at = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $delay_seconds );
        }

        // Prepare data
        $data = [
            'context_id'   => $context_id,
            'content_type' => $type,
            'lang'         => $lang,
            'source_ref'   => $payload_json,
            'unique_key'   => $unique_key,
            'priority'     => $priority,
            'scheduled_at' => $scheduled_at,
            'status'       => EasyRest_CE_Queue_Status::PENDING,
            'attempts'     => 0,
            'created_at'   => current_time( 'mysql' ),
            'updated_at'   => current_time( 'mysql' ),
        ];

        // Insert job (with DB-level duplicate handling)
        // The unique_key column has a UNIQUE index, so concurrent inserts will fail gracefully
        $job_id = $this->insert_with_duplicate_handling( $data, $unique_key );

        if ( $job_id === false ) {
            // If insertion failed due to duplicate (race condition), treat as "already exists"
            // This is not an error condition
            return false;
        }

        // Log success
        $this->log(
            'dispatch_success',
            $job_id,
            sprintf( 'Job dispatched: %s (priority=%d, delay=%ds)', $type, $priority, $delay_seconds ?? 0 ),
            [
                'type'       => $type,
                'priority'   => $priority,
                'delay'      => $delay_seconds,
                'unique_key' => $unique_key,
            ]
        );

        /**
         * Action fired when a job is dispatched.
         *
         * @param int    $job_id  Inserted job ID.
         * @param string $type    Job type.
         * @param array  $payload Job payload.
         */
        do_action( 'easyrest_ce_job_dispatched', $job_id, $type, $payload );

        return $job_id;
    }

    /**
     * Dispatch a job if it doesn't already exist.
     *
     * Alias for dispatch() with explicit deduplication check.
     *
     * @param string   $type          Job type.
     * @param array    $payload       Job payload.
     * @param int      $priority      Priority.
     * @param int|null $delay_seconds Delay.
     * @return int|false Job ID or false if exists/failed.
     */
    public function dispatch_unique(
        string $type,
        array $payload,
        int $priority = 10,
        ?int $delay_seconds = null
    ): int|false {
        return $this->dispatch( $type, $payload, $priority, $delay_seconds );
    }

    /**
     * Dispatch multiple jobs in a batch.
     *
     * @param array $jobs Array of job definitions, each with 'type', 'payload', and optional 'priority', 'delay'.
     * @return array Array of results: ['dispatched' => int, 'skipped' => int, 'failed' => int, 'ids' => int[]].
     */
    public function dispatch_batch( array $jobs ): array {
        $results = [
            'dispatched' => 0,
            'skipped'    => 0,
            'failed'     => 0,
            'ids'        => [],
        ];

        foreach ( $jobs as $job ) {
            if ( ! isset( $job['type'], $job['payload'] ) ) {
                $results['failed']++;
                continue;
            }

            try {
                $id = $this->dispatch(
                    $job['type'],
                    $job['payload'],
                    $job['priority'] ?? 10,
                    $job['delay'] ?? null
                );

                if ( $id === false ) {
                    $results['skipped']++;
                } else {
                    $results['dispatched']++;
                    $results['ids'][] = $id;
                }
            } catch ( InvalidArgumentException $e ) {
                $results['failed']++;
                $this->log( 'batch_dispatch_error', 0, $e->getMessage(), [ 'job' => $job ] );
            }
        }

        return $results;
    }

    /**
     * Insert job with DB-level duplicate handling.
     *
     * The unique_key column has a UNIQUE index, so concurrent inserts will fail
     * with a duplicate key error. This method handles that gracefully.
     *
     * @param array  $data       Job data.
     * @param string $unique_key Unique key for logging.
     * @return int|false Inserted job ID or false if duplicate/error.
     */
    private function insert_with_duplicate_handling( array $data, string $unique_key ): int|false {
        global $wpdb;

        // Suppress errors temporarily to handle duplicate key gracefully
        $suppress = $wpdb->suppress_errors( true );
        $job_id   = $this->repository->create( $data );
        $wpdb->suppress_errors( $suppress );

        // Check for duplicate key error (MySQL error code 1062)
        if ( $job_id === false ) {
            $last_error = $wpdb->last_error;

            // Check if it's a duplicate key error
            if ( strpos( $last_error, 'Duplicate entry' ) !== false ||
                 strpos( $last_error, '1062' ) !== false ) {
                // This is a race condition duplicate - log and return gracefully
                $this->log(
                    'dispatch_duplicate_race',
                    0,
                    "Concurrent duplicate detected for unique_key: {$unique_key}",
                    [ 'unique_key' => $unique_key ]
                );
                return false;
            }

            // Some other error occurred
            $this->log(
                'dispatch_failed',
                0,
                "Failed to insert job: {$last_error}",
                [ 'data' => $data, 'error' => $last_error ]
            );
            return false;
        }

        return $job_id;
    }

    /**
     * Generate unique key for deduplication.
     *
     * Normalizes the payload to ensure consistent hashing regardless of
     * associative array key ordering.
     *
     * @param string $type       Job type.
     * @param array  $payload    Job payload.
     * @param int    $context_id Context ID.
     * @param string $lang       Language.
     * @return string Unique key.
     */
    private function generate_unique_key( string $type, array $payload, int $context_id, string $lang ): string {
        // Normalize payload for consistent hashing
        $normalized_payload = $this->normalize_payload( $payload );

        // Create a deterministic hash from normalized payload
        $payload_hash = md5( wp_json_encode( $normalized_payload ) );

        return sprintf( '%d|%s|%s|%s', $context_id, $type, $lang, $payload_hash );
    }

    /**
     * Normalize payload for consistent hashing.
     *
     * Recursively sorts associative array keys to ensure that equivalent
     * payloads with different key orderings produce the same hash.
     *
     * @param mixed $data Data to normalize.
     * @return mixed Normalized data.
     */
    private function normalize_payload( mixed $data ): mixed {
        if ( ! is_array( $data ) ) {
            return $data;
        }

        // Recursively normalize nested arrays
        $normalized = [];
        foreach ( $data as $key => $value ) {
            $normalized[ $key ] = $this->normalize_payload( $value );
        }

        // Sort by keys for associative arrays (string keys)
        // For indexed arrays (numeric keys), preserve order
        if ( $this->is_associative_array( $normalized ) ) {
            ksort( $normalized, SORT_STRING );
        }

        return $normalized;
    }

    /**
     * Check if an array is associative (has string keys).
     *
     * @param array $array Array to check.
     * @return bool True if associative.
     */
    private function is_associative_array( array $array ): bool {
        if ( empty( $array ) ) {
            return false;
        }

        // An array is associative if it has non-sequential or string keys
        return array_keys( $array ) !== range( 0, count( $array ) - 1 );
    }

    /**
     * Log a message.
     *
     * @param string $action  Action type.
     * @param int    $job_id  Job ID.
     * @param string $message Message.
     * @param array  $data    Additional data.
     * @return void
     */
    private function log( string $action, int $job_id, string $message, array $data = [] ): void {
        if ( class_exists( 'EasyRest_CE_Logger' ) ) {
            EasyRest_CE_Logger::log( $action, $job_id, 0, $message, $data );
        }

        // Also log to error_log if debug enabled
        if ( get_option( 'easyrest_ce_debug_logs', false ) ) {
            error_log( sprintf(
                '[easyrest-ce][queue][%s] %s %s',
                $action,
                $message,
                ! empty( $data ) ? wp_json_encode( $data ) : ''
            ) );
        }
    }

    /**
     * Get queue statistics.
     *
     * @return array
     */
    public function get_stats(): array {
        return [
            'status_counts'    => $this->repository->get_status_counts(),
            'eligible_pending' => $this->repository->count_eligible_pending(),
            'total'            => $this->repository->count_by_status(),
        ];
    }
}
