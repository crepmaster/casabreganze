<?php
/**
 * Queue Cron Manager
 *
 * Manages WordPress cron schedules for queue processing.
 * Provides unified interface for scheduling and execution.
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EasyRest_CE_Queue_Cron
 *
 * Cron manager for queue processing.
 */
class EasyRest_CE_Queue_Cron {

    /**
     * Hook name for queue processing.
     */
    public const HOOK_PROCESS_QUEUE = 'easyrest_ce_process_queue';

    /**
     * Hook name for queue cleanup.
     */
    public const HOOK_CLEANUP_QUEUE = 'easyrest_ce_cleanup_queue';

    /**
     * Interval name for queue processing.
     */
    public const INTERVAL_FIVE_MINUTES = 'easyrest_ce_five_minutes';

    /**
     * Interval name for daily cleanup.
     */
    public const INTERVAL_DAILY = 'daily';

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Worker instance.
     *
     * @var EasyRest_CE_Worker|null
     */
    private ?EasyRest_CE_Worker $worker = null;

    /**
     * Repository instance.
     *
     * @var EasyRest_CE_Queue_Repository|null
     */
    private ?EasyRest_CE_Queue_Repository $repository = null;

    /**
     * Private constructor (singleton).
     */
    private function __construct() {}

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register cron hooks and schedules.
     *
     * Should be called on plugin init.
     *
     * @return void
     */
    public function register(): void {
        // Register custom intervals
        add_filter( 'cron_schedules', [ $this, 'add_cron_intervals' ] );

        // Register action hooks
        add_action( self::HOOK_PROCESS_QUEUE, [ $this, 'process_queue' ] );
        add_action( self::HOOK_CLEANUP_QUEUE, [ $this, 'cleanup_queue' ] );
    }

    /**
     * Add custom cron intervals.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function add_cron_intervals( array $schedules ): array {
        // 5-minute interval for queue processing
        if ( ! isset( $schedules[ self::INTERVAL_FIVE_MINUTES ] ) ) {
            $schedules[ self::INTERVAL_FIVE_MINUTES ] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every 5 Minutes', 'easyrest-content-engine' ),
            ];
        }

        // 15-minute interval (may already exist from main plugin)
        if ( ! isset( $schedules['fifteen_minutes'] ) ) {
            $schedules['fifteen_minutes'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every 15 Minutes', 'easyrest-content-engine' ),
            ];
        }

        return $schedules;
    }

    /**
     * Ensure cron events are scheduled.
     *
     * Should be called on plugin activation or admin_init.
     *
     * @return void
     */
    public function ensure_scheduled(): void {
        // Schedule queue processing (if not already scheduled)
        if ( ! wp_next_scheduled( self::HOOK_PROCESS_QUEUE ) ) {
            wp_schedule_event(
                time(),
                self::INTERVAL_FIVE_MINUTES,
                self::HOOK_PROCESS_QUEUE
            );
        }

        // Schedule daily cleanup
        if ( ! wp_next_scheduled( self::HOOK_CLEANUP_QUEUE ) ) {
            // Schedule at 3 AM local time
            $next_3am = strtotime( 'tomorrow 03:00:00' );
            wp_schedule_event(
                $next_3am,
                self::INTERVAL_DAILY,
                self::HOOK_CLEANUP_QUEUE
            );
        }
    }

    /**
     * Unschedule all cron events.
     *
     * Uses wp_clear_scheduled_hook() to remove ALL instances of each hook,
     * not just the next scheduled one. This handles edge cases where multiple
     * schedules might exist (re-activation, manual scheduling, etc.)
     *
     * Should be called on plugin deactivation.
     *
     * @return void
     */
    public function unschedule(): void {
        // Use wp_clear_scheduled_hook to remove ALL instances, not just next one
        wp_clear_scheduled_hook( self::HOOK_PROCESS_QUEUE );
        wp_clear_scheduled_hook( self::HOOK_CLEANUP_QUEUE );
    }

    /**
     * Process queue items.
     *
     * Called by cron hook.
     *
     * @return array Processing results.
     */
    public function process_queue(): array {
        // Prevent concurrent runs
        $lock_key = 'easyrest_ce_queue_cron_lock';
        $lock     = get_transient( $lock_key );

        if ( $lock !== false ) {
            $this->log( 'cron_skipped', 0, 'Queue processing already in progress' );
            return [ 'skipped' => true, 'reason' => 'locked' ];
        }

        // Set lock with configurable TTL
        // Default: 10 minutes, should cover worst-case batch processing
        // The lock is released in finally{} block, but TTL is safety net for crashes
        $lock_ttl = (int) get_option( 'easyrest_ce_cron_lock_ttl', 10 * MINUTE_IN_SECONDS );
        set_transient( $lock_key, time(), $lock_ttl );

        try {
            $worker  = $this->get_worker();
            $results = $worker->run();

            $this->log(
                'cron_completed',
                0,
                sprintf(
                    'Queue cron completed: %d processed, %d succeeded, %d failed',
                    $results['processed'],
                    $results['succeeded'],
                    $results['failed']
                ),
                $results
            );

            return $results;

        } catch ( Throwable $e ) {
            $this->log(
                'cron_error',
                0,
                'Queue cron error: ' . $e->getMessage(),
                [ 'exception' => get_class( $e ) ]
            );

            return [ 'error' => $e->getMessage() ];

        } finally {
            // Always release lock
            delete_transient( $lock_key );
        }
    }

    /**
     * Cleanup old queue items.
     *
     * Called by daily cron hook.
     *
     * @return array Cleanup results.
     */
    public function cleanup_queue(): array {
        $repository = $this->get_repository();

        // Get retention settings
        $days_done   = (int) get_option( 'easyrest_ce_queue_retention_done', 30 );
        $days_failed = (int) get_option( 'easyrest_ce_queue_retention_failed', 60 );

        // Purge old items
        $purged = $repository->purge_old( $days_done, $days_failed );

        // Also cleanup logs if Logger exists
        if ( class_exists( 'EasyRest_CE_Logger' ) ) {
            $log_retention = (int) get_option( 'easyrest_ce_log_retention_days', 180 );
            EasyRest_CE_Logger::cleanup( $log_retention );
        }

        $this->log(
            'cleanup_completed',
            0,
            sprintf( 'Cleanup completed: %d queue items purged', $purged ),
            [
                'days_done'   => $days_done,
                'days_failed' => $days_failed,
            ]
        );

        return [
            'purged'      => $purged,
            'days_done'   => $days_done,
            'days_failed' => $days_failed,
        ];
    }

    /**
     * Get next scheduled run time.
     *
     * @param string $hook Hook name.
     * @return int|false Timestamp or false if not scheduled.
     */
    public function get_next_run( string $hook = self::HOOK_PROCESS_QUEUE ): int|false {
        return wp_next_scheduled( $hook );
    }

    /**
     * Check if a hook is scheduled.
     *
     * @param string $hook Hook name.
     * @return bool
     */
    public function is_scheduled( string $hook = self::HOOK_PROCESS_QUEUE ): bool {
        return wp_next_scheduled( $hook ) !== false;
    }

    /**
     * Manually trigger queue processing.
     *
     * @return array Processing results.
     */
    public function trigger_now(): array {
        return $this->process_queue();
    }

    /**
     * Get cron status information.
     *
     * @return array
     */
    public function get_status(): array {
        return [
            'process_queue' => [
                'scheduled'  => $this->is_scheduled( self::HOOK_PROCESS_QUEUE ),
                'next_run'   => $this->get_next_run( self::HOOK_PROCESS_QUEUE ),
                'next_run_human' => $this->get_next_run( self::HOOK_PROCESS_QUEUE )
                    ? human_time_diff( $this->get_next_run( self::HOOK_PROCESS_QUEUE ) ) . ' from now'
                    : 'Not scheduled',
            ],
            'cleanup_queue' => [
                'scheduled' => $this->is_scheduled( self::HOOK_CLEANUP_QUEUE ),
                'next_run'  => $this->get_next_run( self::HOOK_CLEANUP_QUEUE ),
            ],
        ];
    }

    /**
     * Get worker instance.
     *
     * @return EasyRest_CE_Worker
     */
    private function get_worker(): EasyRest_CE_Worker {
        if ( $this->worker === null ) {
            $this->worker = new EasyRest_CE_Worker();
        }

        return $this->worker;
    }

    /**
     * Get repository instance.
     *
     * @return EasyRest_CE_Queue_Repository
     */
    private function get_repository(): EasyRest_CE_Queue_Repository {
        if ( $this->repository === null ) {
            $this->repository = new EasyRest_CE_Queue_Repository();
        }

        return $this->repository;
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

        if ( get_option( 'easyrest_ce_debug_logs', false ) ) {
            error_log( sprintf(
                '[easyrest-ce][cron][%s] %s',
                $action,
                $message
            ) );
        }
    }
}
