<?php
/**
 * Logger Class
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Logger
 *
 * Handles logging to database (static methods for easy access)
 */
class EasyRest_CE_Logger {

    /**
     * Get table name
     *
     * @return string
     */
    private static function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'easyrest_logs';
    }

    /**
     * Log a debug message (only in WP_DEBUG mode)
     *
     * @param string $message  Log message
     * @param array  $metadata Additional metadata
     */
    public static function debug(string $message, array $metadata = []): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        self::log('debug', 0, 0, $message, $metadata);
    }

    /**
     * Log an info message
     *
     * @param string $message  Log message
     * @param array  $metadata Additional metadata
     */
    public static function info(string $message, array $metadata = []): void {
        self::log('info', 0, 0, $message, $metadata);
    }

    /**
     * Log an error message
     *
     * @param string $message  Log message
     * @param array  $metadata Additional metadata
     */
    public static function error(string $message, array $metadata = []): void {
        self::log('error', 0, 0, $message, $metadata);
    }

    /**
     * Log a warning message
     *
     * @param string $message  Log message
     * @param array  $metadata Additional metadata
     */
    public static function warning(string $message, array $metadata = []): void {
        self::log('warning', 0, 0, $message, $metadata);
    }

    /**
     * Log an action (static method)
     *
     * @param string $action      Action identifier
     * @param int    $queue_id    Queue item ID (0 if not applicable)
     * @param int    $tokens_used Tokens used
     * @param string $message     Log message
     * @param array  $metadata    Additional metadata
     * @param float  $cost        Cost in USD
     * @param float  $duration    Duration in seconds
     */
    public static function log(string $action, int $queue_id = 0, int $tokens_used = 0, string $message = '', array $metadata = [], float $cost = 0.0, float $duration = 0.0): void {
        global $wpdb;

        $table = self::get_table();

        $insert_data = [
            'queue_id'    => absint($queue_id),
            'action'      => sanitize_text_field($action),
            'message'     => sanitize_textarea_field($message),
            'tokens_used' => absint($tokens_used),
            'cost'        => floatval($cost),
            'duration'    => floatval($duration),
            'metadata'    => !empty($metadata) ? wp_json_encode($metadata) : null,
            'created_at'  => current_time('mysql'),
        ];

        $wpdb->insert($table, $insert_data, [
            '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s',
        ]);
    }

    /**
     * Get logs for a queue item
     *
     * @param int $queue_id
     * @return array
     */
    public static function get_by_queue_id(int $queue_id): array {
        global $wpdb;

        $table = self::get_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE queue_id = %d ORDER BY created_at DESC",
                $queue_id
            )
        );
    }

    /**
     * Get recent logs
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_recent(int $limit = 50, int $offset = 0): array {
        global $wpdb;

        $table = self::get_table();
        $queue_table = $wpdb->prefix . 'easyrest_queue';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, q.content_type, q.lang, q.source_ref
                FROM {$table} l
                LEFT JOIN {$queue_table} q ON l.queue_id = q.id
                ORDER BY l.created_at DESC
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Get total cost (all time or for a period)
     *
     * @param string|null $start_date Y-m-d format (optional)
     * @param string|null $end_date   Y-m-d format (optional)
     * @return float
     */
    public static function get_total_cost(?string $start_date = null, ?string $end_date = null): float {
        global $wpdb;

        $table = self::get_table();

        if ($start_date && $end_date) {
            return (float) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(cost), 0) FROM {$table}
                    WHERE created_at BETWEEN %s AND %s",
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                )
            );
        }

        return (float) $wpdb->get_var("SELECT COALESCE(SUM(cost), 0) FROM {$table}");
    }

    /**
     * Get total tokens (all time or for a period)
     *
     * @param string|null $start_date Y-m-d format (optional)
     * @param string|null $end_date   Y-m-d format (optional)
     * @return int
     */
    public static function get_total_tokens(?string $start_date = null, ?string $end_date = null): int {
        global $wpdb;

        $table = self::get_table();

        if ($start_date && $end_date) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(tokens_used), 0) FROM {$table}
                    WHERE created_at BETWEEN %s AND %s",
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                )
            );
        }

        return (int) $wpdb->get_var("SELECT COALESCE(SUM(tokens_used), 0) FROM {$table}");
    }

    /**
     * Get stats by action
     *
     * @param string|null $start_date Y-m-d format (optional)
     * @param string|null $end_date   Y-m-d format (optional)
     * @return array
     */
    public static function get_stats_by_action(?string $start_date = null, ?string $end_date = null): array {
        global $wpdb;

        $table = self::get_table();

        if ($start_date && $end_date) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT action, COUNT(*) as count, SUM(tokens_used) as total_tokens, SUM(cost) as total_cost
                    FROM {$table}
                    WHERE created_at BETWEEN %s AND %s
                    GROUP BY action",
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                ),
                ARRAY_A
            );
        }

        return $wpdb->get_results(
            "SELECT action, COUNT(*) as count, SUM(tokens_used) as total_tokens, SUM(cost) as total_cost
            FROM {$table}
            GROUP BY action",
            ARRAY_A
        );
    }

    /**
     * Cleanup old logs
     *
     * @param int $days Number of days to retain
     * @return int Number of deleted rows
     */
    public static function cleanup(?int $days = null): int {
        global $wpdb;

        if ($days === null) {
            $days = (int) get_option('easyrest_ce_log_retention_days', 180);
        }

        $table = self::get_table();
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff_date
            )
        );
    }

    /**
     * Count total logs
     *
     * @return int
     */
    public static function count_total(): int {
        global $wpdb;

        $table = self::get_table();

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Get logs by action type
     *
     * @param string $action
     * @param int    $limit
     * @return array
     */
    public static function get_by_action(string $action, int $limit = 50): array {
        global $wpdb;

        $table = self::get_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE action = %s ORDER BY created_at DESC LIMIT %d",
                $action,
                $limit
            )
        );
    }
}
