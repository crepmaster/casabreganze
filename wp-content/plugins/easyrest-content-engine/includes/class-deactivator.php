<?php
/**
 * Plugin Deactivator
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Deactivator
 *
 * Handles plugin deactivation tasks
 */
class EasyRest_CE_Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate(): void {
        self::clear_cron_events();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear scheduled cron events
     */
    private static function clear_cron_events(): void {
        // Legacy hooks
        $cron_hooks = [
            'easyrest_ce_planner_cron',
            'easyrest_ce_worker_cron',
        ];

        foreach ($cron_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }

        // New QueueCron hooks
        EasyRest_CE_Queue_Cron::instance()->unschedule();
    }
}
