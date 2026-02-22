<?php
/**
 * Plugin Activator
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Activator
 *
 * Handles plugin activation tasks and database migrations.
 *
 * Migration History:
 * - 1.0.0: Initial schema
 * - 1.1.0: Added next_retry_at column
 * - 1.2.0: Added channel column for multi-channel distribution
 * - 1.3.0: Added source_payload (LONGTEXT), external_id, skipped status; backfill legacy JSON
 * - 1.3.1: Hardened migrations with error handling and batched backfill
 */
class EasyRest_CE_Activator {

    /**
     * Current database schema version.
     * Increment this when adding new migrations.
     */
    const DB_VERSION = '1.3.1';

    /**
     * Maximum batches for backfill (safety limit to prevent runaway loops)
     */
    const BACKFILL_MAX_BATCHES = 20;

    /**
     * Rows per batch for backfill operations
     */
    const BACKFILL_BATCH_SIZE = 250;

    /**
     * Activate the plugin
     */
    public static function activate(): void {
        self::create_tables();
        self::create_options();
        self::create_default_context();
        self::schedule_cron_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option('easyrest_ce_activated', time());
    }

    /**
     * Check and run migrations on upgrade (called from plugins_loaded)
     *
     * This ensures migrations run not just on activation, but also on plugin updates.
     * Safe to call multiple times - migrations are idempotent.
     * Wrapped in try/catch to prevent fatal errors from crashing the site.
     */
    public static function maybe_run_migrations(): void {
        $current_version = get_option('easyrest_ce_db_version', '1.0.0');

        // Skip if already at current version
        if (version_compare($current_version, self::DB_VERSION, '>=')) {
            return;
        }

        self::log_migration_info("Starting migration from {$current_version} to " . self::DB_VERSION);

        try {
            // Run migrations
            self::run_migrations();

            // Update version after successful migration
            update_option('easyrest_ce_db_version', self::DB_VERSION);

            self::log_migration_success("Migration completed successfully to version " . self::DB_VERSION);

        } catch (\Throwable $e) {
            // Log the error but don't crash the site
            self::log_migration_error(
                "Migration failed: " . $e->getMessage(),
                [
                    'from_version' => $current_version,
                    'to_version'   => self::DB_VERSION,
                    'file'         => $e->getFile(),
                    'line'         => $e->getLine(),
                    'trace'        => $e->getTraceAsString(),
                ]
            );

            // Store partial migration state for debugging
            update_option('easyrest_ce_migration_error', [
                'time'    => current_time('mysql'),
                'message' => $e->getMessage(),
                'from'    => $current_version,
                'to'      => self::DB_VERSION,
            ]);
        }
    }

    /**
     * Create database tables
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Contexts table
        $table_contexts = $wpdb->prefix . 'easyrest_contexts';
        $sql_contexts   = "CREATE TABLE $table_contexts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            type ENUM('event_based','seasonal','evergreen') DEFAULT 'event_based',
            status ENUM('active','paused','archived') DEFAULT 'active',
            priority TINYINT DEFAULT 5,
            daily_quota TINYINT DEFAULT 2,
            date_start DATE NULL,
            date_end DATE NULL,
            events_json LONGTEXT NULL,
            prompts_config JSON NULL,
            settings JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_dates (date_start, date_end)
        ) $charset_collate;";

        // Queue table - includes all columns up to DB_VERSION 1.3.0
        // Note: status uses VARCHAR for maximum compatibility with ALTER operations
        $table_queue = $wpdb->prefix . 'easyrest_queue';
        $sql_queue   = "CREATE TABLE $table_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            context_id BIGINT UNSIGNED NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            lang VARCHAR(5) NOT NULL,
            source_ref VARCHAR(255) NOT NULL,
            source_payload LONGTEXT NULL,
            unique_key VARCHAR(255) NOT NULL UNIQUE,
            channel VARCHAR(20) NOT NULL DEFAULT 'wordpress',
            priority TINYINT DEFAULT 5,
            scheduled_at DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            locked_at DATETIME NULL,
            lock_token VARCHAR(64) NULL,
            post_id BIGINT UNSIGNED NULL,
            external_id VARCHAR(191) NULL,
            attempts TINYINT DEFAULT 0,
            last_error TEXT NULL,
            next_retry_at DATETIME NULL,
            generation_cost DECIMAL(10,4) NULL,
            tokens_used INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status_scheduled (status, scheduled_at),
            INDEX idx_context (context_id),
            INDEX idx_priority (priority),
            INDEX idx_lang (lang),
            INDEX idx_channel (channel),
            INDEX idx_locked (locked_at),
            INDEX idx_next_retry (next_retry_at)
        ) $charset_collate;";

        // Logs table
        $table_logs = $wpdb->prefix . 'easyrest_logs';
        $sql_logs   = "CREATE TABLE $table_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            message TEXT NULL,
            tokens_used INT NULL,
            cost DECIMAL(10,6) NULL,
            duration DECIMAL(10,3) NULL,
            metadata JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_queue_action (queue_id, action),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql_contexts);
        dbDelta($sql_queue);
        dbDelta($sql_logs);

        // Run migrations for existing installs
        self::run_migrations();

        // Store DB version
        update_option('easyrest_ce_db_version', self::DB_VERSION);
    }

    /**
     * Run database migrations for existing installs
     *
     * All migrations are idempotent - safe to run multiple times.
     * Each migration checks if its changes already exist before applying.
     * Throws exception on SQL errors to trigger rollback handling.
     */
    private static function run_migrations(): void {
        global $wpdb;

        $current_version = get_option('easyrest_ce_db_version', '1.0.0');
        $table_queue     = $wpdb->prefix . 'easyrest_queue';

        // Migration: 1.0.0 -> 1.1.0 (add next_retry_at column)
        if (version_compare($current_version, '1.1.0', '<')) {
            if (!self::column_exists($table_queue, 'next_retry_at')) {
                $wpdb->query("ALTER TABLE {$table_queue} ADD COLUMN next_retry_at DATETIME NULL AFTER last_error");
                self::check_db_error('Adding next_retry_at column');
                self::maybe_add_index($table_queue, 'idx_next_retry', 'next_retry_at');
            }
            self::log_migration_info('Migration 1.1.0: next_retry_at column checked/added');
        }

        // Migration: 1.1.0 -> 1.2.0 (add channel column for multi-channel distribution)
        if (version_compare($current_version, '1.2.0', '<')) {
            if (!self::column_exists($table_queue, 'channel')) {
                $wpdb->query("ALTER TABLE {$table_queue} ADD COLUMN channel VARCHAR(20) NOT NULL DEFAULT 'wordpress' AFTER unique_key");
                self::check_db_error('Adding channel column');
                self::maybe_add_index($table_queue, 'idx_channel', 'channel');
            }
            self::log_migration_info('Migration 1.2.0: channel column checked/added');
        }

        // Migration: 1.2.0 -> 1.3.0 (add source_payload, external_id, skipped status)
        if (version_compare($current_version, '1.3.0', '<')) {
            // A1: Add source_payload column (LONGTEXT for large JSON)
            if (!self::column_exists($table_queue, 'source_payload')) {
                $wpdb->query("ALTER TABLE {$table_queue} ADD COLUMN source_payload LONGTEXT NULL AFTER source_ref");
                self::check_db_error('Adding source_payload column');
            }
            self::log_migration_info('Migration 1.3.0: source_payload column checked/added');

            // A2: Add external_id column for platform-specific IDs
            if (!self::column_exists($table_queue, 'external_id')) {
                $wpdb->query("ALTER TABLE {$table_queue} ADD COLUMN external_id VARCHAR(191) NULL AFTER post_id");
                self::check_db_error('Adding external_id column');
            }
            self::log_migration_info('Migration 1.3.0: external_id column checked/added');

            // A3: Convert status from ENUM to VARCHAR if needed, to support 'skipped'
            self::ensure_status_supports_skipped($table_queue);
            self::log_migration_info('Migration 1.3.0: status column VARCHAR conversion checked');

            // A5: Backfill legacy rows where JSON was stored in source_ref (batched)
            self::backfill_legacy_source_ref($table_queue);
        }

        // Migration: 1.3.0 -> 1.3.1 (no schema changes, just hardened migration logic)
        if (version_compare($current_version, '1.3.1', '<')) {
            // Continue any incomplete backfill from 1.3.0
            self::backfill_legacy_source_ref($table_queue);
            self::log_migration_info('Migration 1.3.1: backfill continuation completed');
        }
    }

    /**
     * Check for database error and throw exception if found
     *
     * @param string $operation Description of the operation for error message
     * @throws \RuntimeException if database error occurred
     */
    private static function check_db_error(string $operation): void {
        global $wpdb;

        if (!empty($wpdb->last_error)) {
            throw new \RuntimeException(
                "Database error during '{$operation}': " . $wpdb->last_error
            );
        }
    }

    /**
     * Check if a column exists in a table
     *
     * @param string $table      Table name (with prefix)
     * @param string $column     Column name
     * @return bool
     */
    private static function column_exists(string $table, string $column): bool {
        global $wpdb;

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                $column
            )
        );

        return !empty($result);
    }

    /**
     * Add an index if it doesn't already exist
     *
     * @param string $table      Table name (with prefix)
     * @param string $index_name Index name
     * @param string $column     Column to index
     */
    private static function maybe_add_index(string $table, string $index_name, string $column): void {
        global $wpdb;

        // Check if index exists using SHOW INDEX
        $existing = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW INDEX FROM {$table} WHERE Key_name = %s",
                $index_name
            )
        );

        if (empty($existing)) {
            // Suppress errors for environments where index already exists differently
            $wpdb->suppress_errors(true);
            $wpdb->query("ALTER TABLE {$table} ADD INDEX {$index_name} ({$column})");
            $wpdb->suppress_errors(false);
        }
    }

    /**
     * Ensure status column can hold 'skipped' value
     *
     * If status is ENUM, convert to VARCHAR(20) for flexibility.
     * If already VARCHAR or contains 'skipped', skip.
     *
     * @param string $table Table name (with prefix)
     */
    private static function ensure_status_supports_skipped(string $table): void {
        global $wpdb;

        $column_info = $wpdb->get_row(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                'status'
            )
        );

        if (!$column_info) {
            return; // Column doesn't exist, will be created by dbDelta
        }

        $type = strtoupper($column_info->Type);

        // If it's already VARCHAR, we're good
        if (strpos($type, 'VARCHAR') !== false) {
            return;
        }

        // If it's ENUM and already includes 'skipped', we're good
        if (strpos($type, 'ENUM') !== false && strpos($type, "'skipped'") !== false) {
            return;
        }

        // Convert ENUM to VARCHAR for flexibility
        // This is safe and preserves existing values
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN status VARCHAR(20) DEFAULT 'pending'");
    }

    /**
     * Backfill legacy rows where JSON was stored in source_ref
     *
     * Moves JSON data from source_ref (VARCHAR 255) to source_payload (LONGTEXT).
     * Replaces source_ref with a short reference.
     *
     * Uses batched processing to handle large datasets without timeouts.
     * Logs progress and errors for visibility.
     *
     * @param string $table Table name (with prefix)
     */
    private static function backfill_legacy_source_ref(string $table): void {
        global $wpdb;

        $batch_size   = self::BACKFILL_BATCH_SIZE;
        $max_batches  = self::BACKFILL_MAX_BATCHES;
        $total_updated = 0;
        $total_skipped = 0;

        for ($batch = 0; $batch < $max_batches; $batch++) {
            // Find rows where source_payload is NULL and source_ref looks like JSON
            // JSON starts with { or [
            $legacy_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, source_ref FROM {$table}
                     WHERE source_payload IS NULL
                     AND (source_ref LIKE '{%%' OR source_ref LIKE '[%%')
                     LIMIT %d",
                    $batch_size
                )
            );

            // No more rows to process - we're done
            if (empty($legacy_rows)) {
                break;
            }

            $batch_updated = 0;
            $batch_skipped = 0;

            foreach ($legacy_rows as $row) {
                // Try to decode JSON
                $data = json_decode($row->source_ref, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Not valid JSON - log and skip this row
                    self::log_migration_info(
                        "Backfill skipped row {$row->id}: invalid JSON - " . json_last_error_msg()
                    );
                    $batch_skipped++;
                    $total_skipped++;

                    // Mark as processed by setting source_payload to empty string
                    // This prevents infinite loops on invalid JSON
                    $wpdb->update(
                        $table,
                        ['source_payload' => ''],
                        ['id' => $row->id],
                        ['%s'],
                        ['%d']
                    );
                    continue;
                }

                // Determine short reference
                $short_ref = 'legacy_json';

                if (!empty($data['parent_post_id'])) {
                    $short_ref = 'wp_post:' . intval($data['parent_post_id']);
                } elseif (!empty($data['wordpress_post_id'])) {
                    $short_ref = 'wp_post:' . intval($data['wordpress_post_id']);
                }

                // Update row: move JSON to source_payload, set short source_ref
                $result = $wpdb->update(
                    $table,
                    [
                        'source_payload' => $row->source_ref,
                        'source_ref'     => $short_ref,
                    ],
                    ['id' => $row->id],
                    ['%s', '%s'],
                    ['%d']
                );

                if ($result === false) {
                    self::log_migration_error(
                        "Backfill failed for row {$row->id}: " . $wpdb->last_error
                    );
                } else {
                    $batch_updated++;
                    $total_updated++;
                }
            }

            self::log_migration_info(
                "Backfill batch {$batch}: updated {$batch_updated}, skipped {$batch_skipped}"
            );

            // If we processed fewer rows than batch size, we're done
            if (count($legacy_rows) < $batch_size) {
                break;
            }
        }

        if ($total_updated > 0 || $total_skipped > 0) {
            self::log_migration_success(
                "Backfill complete: {$total_updated} rows migrated, {$total_skipped} rows skipped"
            );
        }
    }

    /**
     * Create default options
     */
    private static function create_options(): void {
        // Generate secure worker token
        $worker_token = bin2hex(random_bytes(16));

        // Default options - all prefixed with easyrest_ce_
        $defaults = [
            'easyrest_ce_worker_token'       => $worker_token,
            'easyrest_ce_openai_api_key'     => '',
            'easyrest_ce_openai_model'       => 'gpt-4o-mini',
            'easyrest_ce_booking_url'        => '/reservation/',
            'easyrest_ce_admin_email'        => get_option('admin_email'),
            'easyrest_ce_log_retention_days' => 180,
            'easyrest_ce_featured_images'    => [],
            'easyrest_ce_rate_limit_per_min' => 1,
            'easyrest_ce_max_attempts'       => 5,
            'easyrest_ce_lock_timeout_min'   => 10,
            'easyrest_ce_quality_threshold'  => 70,
            'easyrest_ce_active_langs'       => ['fr', 'en', 'it', 'es'],
            'easyrest_ce_active_types'       => ['weekly', 'sport_guide', 'nationality_guide', 'transport', 'match_preview'],
            // Lot B: Worker batching and retry options
            'easyrest_ce_worker_batch_size'     => 3,
            'easyrest_ce_worker_time_budget'    => 25,
            'easyrest_ce_retry_base_min'        => 15,
            'easyrest_ce_retry_cap_min'         => 240,
        ];

        // Force-update languages to FR/EN/IT/ES (one-time migration)
        update_option('easyrest_ce_active_langs', ['fr', 'en', 'it', 'es']);

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Create default evergreen context
     */
    private static function create_default_context(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'easyrest_contexts';

        // Check if evergreen context exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE slug = %s", 'evergreen')
        );

        if (!$exists) {
            $wpdb->insert($table, [
                'slug'       => 'evergreen',
                'name'       => 'Evergreen Content',
                'type'       => 'evergreen',
                'status'     => 'paused',
                'priority'   => 3,
                'daily_quota' => 1,
                'settings'   => wp_json_encode([
                    'active_langs' => ['fr', 'en', 'it', 'es'],
                    'active_types' => ['venue_guide', 'neighborhood_guide'],
                ]),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']);
        }
    }

    /**
     * Schedule cron events
     *
     * Note: Intervals are registered at runtime by QueueCron::register().
     * Activation temporarily registers them via add_cron_intervals_for_activation().
     */
    private static function schedule_cron_events(): void {
        // Planner - once daily at 6:00 AM
        if (!wp_next_scheduled('easyrest_ce_planner_cron')) {
            $time = strtotime('tomorrow 06:00:00');
            wp_schedule_event($time, 'daily', 'easyrest_ce_planner_cron');
        }

        // Queue processing via QueueCron (replaces legacy easyrest_ce_worker_cron)
        // Register intervals first so wp_schedule_event recognizes them
        add_filter('cron_schedules', [__CLASS__, 'add_cron_intervals_for_activation']);

        EasyRest_CE_Queue_Cron::instance()->ensure_scheduled();

        // Clear legacy worker cron if still scheduled
        wp_clear_scheduled_hook('easyrest_ce_worker_cron');

        remove_filter('cron_schedules', [__CLASS__, 'add_cron_intervals_for_activation']);
    }

    /**
     * Temporarily add custom cron intervals for activation scheduling
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public static function add_cron_intervals_for_activation(array $schedules): array {
        $schedules['fifteen_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __('Every 15 Minutes', 'easyrest-content-engine'),
        ];

        $schedules[ EasyRest_CE_Queue_Cron::INTERVAL_FIVE_MINUTES ] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Every 5 Minutes', 'easyrest-content-engine'),
        ];

        return $schedules;
    }

    /**
     * Log migration success message
     *
     * @param string $message Success message
     * @param array  $context Additional context
     */
    private static function log_migration_success(string $message, array $context = []): void {
        // Log to WordPress debug.log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[EasyRest CE Migration SUCCESS] {$message}");
        }

        // Log to plugin logger if available
        if (class_exists('EasyRest_CE_Logger')) {
            EasyRest_CE_Logger::info("[Migration] {$message}", $context);
        }
    }

    /**
     * Log migration error message
     *
     * @param string $message Error message
     * @param array  $context Additional context
     */
    private static function log_migration_error(string $message, array $context = []): void {
        // Always log errors to error_log
        error_log("[EasyRest CE Migration ERROR] {$message}");

        if (!empty($context)) {
            error_log("[EasyRest CE Migration ERROR] Context: " . wp_json_encode($context));
        }

        // Log to plugin logger if available
        if (class_exists('EasyRest_CE_Logger')) {
            EasyRest_CE_Logger::error("[Migration] {$message}", $context);
        }
    }

    /**
     * Log migration info message
     *
     * @param string $message Info message
     * @param array  $context Additional context
     */
    private static function log_migration_info(string $message, array $context = []): void {
        // Log to WordPress debug.log in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[EasyRest CE Migration INFO] {$message}");
        }

        // Log to plugin logger if available
        if (class_exists('EasyRest_CE_Logger')) {
            EasyRest_CE_Logger::debug("[Migration] {$message}", $context);
        }
    }
}
