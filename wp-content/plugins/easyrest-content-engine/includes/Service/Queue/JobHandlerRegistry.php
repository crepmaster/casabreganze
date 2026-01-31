<?php
/**
 * Job Handler Registry
 *
 * Manages registration and retrieval of job handlers.
 * Implements singleton pattern for global access.
 *
 * @package EasyRest_Content_Engine
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EasyRest_CE_Job_Handler_Registry
 *
 * Registry for job handlers.
 */
class EasyRest_CE_Job_Handler_Registry {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Registered handlers.
     *
     * @var array<string, EasyRest_CE_Job_Handler_Interface>
     */
    private array $handlers = [];

    /**
     * Registered handler factories (lazy loading).
     *
     * @var array<string, callable>
     */
    private array $factories = [];

    /**
     * Private constructor (singleton).
     */
    private function __construct() {
        // Register core handlers
        $this->register_core_handlers();
    }

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
     * Reset instance (for testing).
     *
     * @return void
     */
    public static function reset(): void {
        self::$instance = null;
    }

    /**
     * Register a job handler.
     *
     * @param string                            $type    Job type.
     * @param EasyRest_CE_Job_Handler_Interface $handler Handler instance.
     * @return self
     */
    public function register( string $type, EasyRest_CE_Job_Handler_Interface $handler ): self {
        $type = strtolower( trim( $type ) );

        if ( empty( $type ) ) {
            throw new InvalidArgumentException( 'Handler type cannot be empty' );
        }

        $this->handlers[ $type ] = $handler;

        // Also register as a valid queue type
        add_filter( 'easyrest_ce_queue_types', function ( array $types ) use ( $type ) {
            if ( ! in_array( $type, $types, true ) ) {
                $types[] = $type;
            }
            return $types;
        } );

        // Clear type cache
        EasyRest_CE_Queue_Type::reset_cache();

        return $this;
    }

    /**
     * Register a handler factory (lazy loading).
     *
     * The factory will be called only when the handler is first needed.
     *
     * @param string   $type    Job type.
     * @param callable $factory Factory callable returning EasyRest_CE_Job_Handler_Interface.
     * @return self
     */
    public function register_factory( string $type, callable $factory ): self {
        $type = strtolower( trim( $type ) );

        if ( empty( $type ) ) {
            throw new InvalidArgumentException( 'Handler type cannot be empty' );
        }

        $this->factories[ $type ] = $factory;

        // Also register as a valid queue type
        add_filter( 'easyrest_ce_queue_types', function ( array $types ) use ( $type ) {
            if ( ! in_array( $type, $types, true ) ) {
                $types[] = $type;
            }
            return $types;
        } );

        // Clear type cache
        EasyRest_CE_Queue_Type::reset_cache();

        return $this;
    }

    /**
     * Check if a handler is registered for a type.
     *
     * @param string $type Job type.
     * @return bool
     */
    public function has( string $type ): bool {
        $type = strtolower( trim( $type ) );

        return isset( $this->handlers[ $type ] ) || isset( $this->factories[ $type ] );
    }

    /**
     * Get handler for a type.
     *
     * @param string $type Job type.
     * @return EasyRest_CE_Job_Handler_Interface
     * @throws RuntimeException If handler not found.
     */
    public function get( string $type ): EasyRest_CE_Job_Handler_Interface {
        $type = strtolower( trim( $type ) );

        // Return existing handler
        if ( isset( $this->handlers[ $type ] ) ) {
            return $this->handlers[ $type ];
        }

        // Create from factory
        if ( isset( $this->factories[ $type ] ) ) {
            $handler = call_user_func( $this->factories[ $type ] );

            if ( ! $handler instanceof EasyRest_CE_Job_Handler_Interface ) {
                throw new RuntimeException(
                    sprintf( 'Factory for type %s did not return a valid handler', $type )
                );
            }

            $this->handlers[ $type ] = $handler;
            unset( $this->factories[ $type ] );

            return $handler;
        }

        throw new RuntimeException(
            sprintf( 'No handler registered for type: %s', $type )
        );
    }

    /**
     * Get all registered handler types.
     *
     * @return string[]
     */
    public function get_registered_types(): array {
        return array_unique(
            array_merge(
                array_keys( $this->handlers ),
                array_keys( $this->factories )
            )
        );
    }

    /**
     * Unregister a handler.
     *
     * @param string $type Job type.
     * @return bool True if handler was removed.
     */
    public function unregister( string $type ): bool {
        $type = strtolower( trim( $type ) );

        $removed = false;

        if ( isset( $this->handlers[ $type ] ) ) {
            unset( $this->handlers[ $type ] );
            $removed = true;
        }

        if ( isset( $this->factories[ $type ] ) ) {
            unset( $this->factories[ $type ] );
            $removed = true;
        }

        return $removed;
    }

    /**
     * Register core handlers.
     *
     * @return void
     */
    private function register_core_handlers(): void {
        // Content generation handler uses existing worker logic
        // We don't register it here as it's handled by the existing EasyRest_CE_Worker

        /**
         * Action to register custom job handlers.
         *
         * @param EasyRest_CE_Job_Handler_Registry $registry The handler registry.
         */
        do_action( 'easyrest_ce_register_job_handlers', $this );
    }

    /**
     * Handle a job using the appropriate registered handler.
     *
     * @param string $type    Job type.
     * @param array  $payload Job payload.
     * @param int    $job_id  Job ID.
     * @param int    $attempt Current attempt number.
     * @return EasyRest_CE_Job_Result
     * @throws RuntimeException If no handler registered.
     */
    public function handle( string $type, array $payload, int $job_id, int $attempt ): EasyRest_CE_Job_Result {
        $handler = $this->get( $type );

        return $handler->handle( $payload, $job_id, $attempt );
    }
}
