<?php
/**
 * Main Plugin Class
 *
 * Singleton pattern for centralized plugin management
 *
 * @package EasyRest_Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_Core_Plugin
 */
class EasyRest_Core_Plugin {

    /**
     * Singleton instance
     *
     * @var EasyRest_Core_Plugin|null
     */
    private static $instance = null;

    /**
     * Settings instance
     *
     * @var EasyRest_Settings|null
     */
    public $settings;

    /**
     * AJAX handler instance
     *
     * @var EasyRest_Ajax|null
     */
    public $ajax;

    /**
     * Price service instance
     *
     * @var EasyRest_Price_Service|null
     */
    public $price_service;

    /**
     * Logger instance
     *
     * @var EasyRest_Logger|null
     */
    public $logger;

    /**
     * Get singleton instance
     *
     * @return EasyRest_Core_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private for singleton
     */
    private function __construct() {
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Logger first (used by other components)
        $this->logger = new EasyRest_Logger();
        
        // Price service
        $this->price_service = new EasyRest_Price_Service($this->logger);
        
        // Settings (admin only)
        if (is_admin()) {
            $this->settings = new EasyRest_Settings($this->logger);
        }
        
        // AJAX handler (always needed for frontend)
        $this->ajax = new EasyRest_Ajax($this->price_service, $this->logger);
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this->settings, 'register_menu'));
            add_action('admin_init', array($this->settings, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        }
        
        // AJAX hooks (logged in and not logged in)
        add_action('wp_ajax_easyrest_get_price', array($this->ajax, 'get_price'));
        add_action('wp_ajax_nopriv_easyrest_get_price', array($this->ajax, 'get_price'));
        
        // REST API (optional, more modern approach)
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Cron for cache cleanup
        add_action('easyrest_cleanup_cache', array($this, 'cleanup_old_cache'));
        
        if (!wp_next_scheduled('easyrest_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'easyrest_cleanup_cache');
        }
        
        // Plugin links
        add_filter('plugin_action_links_' . EASYREST_CORE_BASENAME, array($this, 'plugin_action_links'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('easyrest/v1', '/price', array(
            'methods' => 'POST',
            'callback' => array($this->ajax, 'rest_get_price'),
            'permission_callback' => '__return_true',
            'args' => array(
                'checkin' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    },
                ),
                'checkout' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    },
                ),
                'adults' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 2,
                    'minimum' => 1,
                    'maximum' => 10,
                ),
                'children' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'minimum' => 0,
                    'maximum' => 6,
                ),
            ),
        ));
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function admin_scripts($hook) {
        // Only on our settings page
        if ('toplevel_page_easyrest-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'easyrest-admin',
            EASYREST_CORE_URL . 'assets/css/admin.css',
            array(),
            EASYREST_CORE_VERSION
        );
    }

    /**
     * Cleanup old cache entries
     */
    public function cleanup_old_cache() {
        global $wpdb;
        
        // This is handled by transient expiration, but we can force cleanup
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_easyrest_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
        
        $this->logger->info('Cache cleanup completed');
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=easyrest-settings'),
            esc_html__('Settings', 'easyrest-core')
        );
        
        array_unshift($links, $settings_link);
        
        return $links;
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return EASYREST_CORE_VERSION;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function is_debug_mode() {
        return (bool) get_option('easyrest_debug_mode', false);
    }
}
