<?php
/**
 * Logger Class
 *
 * Simple logging utility for debugging and monitoring
 *
 * @package EasyRest_Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_Logger
 */
class EasyRest_Logger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    private $enabled;

    /**
     * Maximum log file size in bytes (5MB)
     *
     * @var int
     */
    private $max_size = 5242880;

    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/easyrest-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Add .htaccess to protect logs
            file_put_contents($log_dir . '/.htaccess', 'Deny from all');
            
            // Add index.php for extra protection
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        }
        
        $this->log_file = $log_dir . '/easyrest-' . date('Y-m') . '.log';
        $this->enabled = (bool) get_option('easyrest_debug_mode', false);
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public function debug($message, $context = array()) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public function info($message, $context = array()) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public function warning($message, $context = array()) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    public function error($message, $context = array()) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Main logging method
     *
     * @param string $level   Log level
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    private function log($level, $message, $context = array()) {
        // Always log errors, other levels only if enabled
        if (!$this->enabled && $level !== self::LEVEL_ERROR) {
            return;
        }
        
        // Rotate log if needed
        $this->maybe_rotate_log();
        
        // Format log entry
        $timestamp = current_time('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | ' . wp_json_encode($context) : '';
        $log_entry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $level,
            $message,
            $context_str
        );
        
        // Write to file
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log if WP_DEBUG is on
        if (defined('WP_DEBUG') && WP_DEBUG && $level === self::LEVEL_ERROR) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[EasyRest] ' . $message);
        }
    }

    /**
     * Rotate log file if it exceeds max size
     */
    private function maybe_rotate_log() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        if (filesize($this->log_file) < $this->max_size) {
            return;
        }
        
        // Rename current log with timestamp
        $backup_name = str_replace('.log', '-' . date('Ymd-His') . '.log', $this->log_file);
        rename($this->log_file, $backup_name);
        
        // Delete old backups (keep last 5)
        $this->cleanup_old_logs();
    }

    /**
     * Remove old log backups
     */
    private function cleanup_old_logs() {
        $log_dir = dirname($this->log_file);
        $files = glob($log_dir . '/easyrest-*.log');
        
        if (count($files) <= 5) {
            return;
        }
        
        // Sort by modification time
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove oldest files
        $to_delete = array_slice($files, 0, count($files) - 5);
        foreach ($to_delete as $file) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            unlink($file);
        }
    }

    /**
     * Get recent log entries
     *
     * @param int $lines Number of lines to retrieve
     * @return array Log entries
     */
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start = max(0, $total_lines - $lines);
        $logs = array();
        
        $file->seek($start);
        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (!empty($line)) {
                $logs[] = $line;
            }
        }
        
        return $logs;
    }

    /**
     * Clear all logs
     *
     * @return bool
     */
    public function clear_logs() {
        $log_dir = dirname($this->log_file);
        $files = glob($log_dir . '/easyrest-*.log');
        
        foreach ($files as $file) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            unlink($file);
        }
        
        return true;
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Enable or disable logging
     *
     * @param bool $enabled Whether to enable logging
     */
    public function set_enabled($enabled) {
        $this->enabled = (bool) $enabled;
    }
}
