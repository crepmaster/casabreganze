<?php
/**
 * Job Handler Interface
 *
 * Contract for queue job handlers.
 * Each job type should have a corresponding handler implementing this interface.
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface EasyRest_CE_Job_Handler_Interface
 *
 * Defines the contract for processing queue jobs.
 */
interface EasyRest_CE_Job_Handler_Interface {

    /**
     * Handle a queue job.
     *
     * @param array $payload  Job payload data.
     * @param int   $job_id   Queue item ID.
     * @param int   $attempt  Current attempt number (1-based).
     * @return EasyRest_CE_Job_Result Result of job processing.
     * @throws Exception On unrecoverable error.
     */
    public function handle( array $payload, int $job_id, int $attempt ): EasyRest_CE_Job_Result;

    /**
     * Get the job type this handler processes.
     *
     * @return string Job type identifier.
     */
    public function get_type(): string;

    /**
     * Get maximum attempts for this job type.
     *
     * Return null to use default from options.
     *
     * @return int|null
     */
    public function get_max_attempts(): ?int;

    /**
     * Check if this handler can process the given payload.
     *
     * Used for validation before enqueueing.
     *
     * @param array $payload Payload to validate.
     * @return bool
     */
    public function can_handle( array $payload ): bool;

    /**
     * Get required payload keys.
     *
     * @return string[]
     */
    public function get_required_keys(): array;
}

/**
 * Class EasyRest_CE_Job_Result
 *
 * Immutable result object from job processing.
 */
final class EasyRest_CE_Job_Result {

    /**
     * @var bool Success flag.
     */
    private bool $success;

    /**
     * @var string|null Error message if failed.
     */
    private ?string $error;

    /**
     * @var array Additional data/stats.
     */
    private array $data;

    /**
     * @var bool Whether to retry on failure.
     */
    private bool $should_retry;

    /**
     * @var int|null Custom retry delay in seconds.
     */
    private ?int $retry_delay;

    /**
     * Constructor.
     *
     * @param bool        $success      Success flag.
     * @param string|null $error        Error message.
     * @param array       $data         Additional data.
     * @param bool        $should_retry Whether to retry on failure.
     * @param int|null    $retry_delay  Custom retry delay.
     */
    private function __construct(
        bool $success,
        ?string $error = null,
        array $data = [],
        bool $should_retry = true,
        ?int $retry_delay = null
    ) {
        $this->success      = $success;
        $this->error        = $error;
        $this->data         = $data;
        $this->should_retry = $should_retry;
        $this->retry_delay  = $retry_delay;
    }

    /**
     * Create a success result.
     *
     * @param array $data Additional data.
     * @return self
     */
    public static function success( array $data = [] ): self {
        return new self( true, null, $data );
    }

    /**
     * Create a failure result (will retry).
     *
     * @param string   $error       Error message.
     * @param array    $data        Additional data.
     * @param int|null $retry_delay Custom retry delay in seconds.
     * @return self
     */
    public static function failure( string $error, array $data = [], ?int $retry_delay = null ): self {
        return new self( false, $error, $data, true, $retry_delay );
    }

    /**
     * Create a permanent failure result (will NOT retry).
     *
     * @param string $error Error message.
     * @param array  $data  Additional data.
     * @return self
     */
    public static function permanent_failure( string $error, array $data = [] ): self {
        return new self( false, $error, $data, false );
    }

    /**
     * Check if job succeeded.
     *
     * @return bool
     */
    public function is_success(): bool {
        return $this->success;
    }

    /**
     * Check if job failed.
     *
     * @return bool
     */
    public function is_failure(): bool {
        return ! $this->success;
    }

    /**
     * Get error message.
     *
     * @return string|null
     */
    public function get_error(): ?string {
        return $this->error;
    }

    /**
     * Get additional data.
     *
     * @return array
     */
    public function get_data(): array {
        return $this->data;
    }

    /**
     * Check if should retry on failure.
     *
     * @return bool
     */
    public function should_retry(): bool {
        return $this->should_retry && ! $this->success;
    }

    /**
     * Get custom retry delay.
     *
     * @return int|null Seconds, or null for default backoff.
     */
    public function get_retry_delay(): ?int {
        return $this->retry_delay;
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'success'      => $this->success,
            'error'        => $this->error,
            'data'         => $this->data,
            'should_retry' => $this->should_retry,
            'retry_delay'  => $this->retry_delay,
        ];
    }
}
