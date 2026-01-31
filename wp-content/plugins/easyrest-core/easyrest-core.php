<?php
/**
 * Plugin Name: EasyRest Core
 * Plugin URI: https://www.easyrest.eu
 * Description: Core services for EasyRest Milan - Booking price scraping, settings, and AJAX endpoints
 * Version: 1.0.0
 * Author: EasyRest
 * Author URI: https://www.easyrest.eu
 * Text Domain: easyrest-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package EasyRest_Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Plugin constants
define('EASYREST_CORE_VERSION', '1.0.0');
define('EASYREST_CORE_FILE', __FILE__);
define('EASYREST_CORE_DIR', plugin_dir_path(__FILE__));
define('EASYREST_CORE_URL', plugin_dir_url(__FILE__));
define('EASYREST_CORE_BASENAME', plugin_basename(__FILE__));

// Minimum requirements
define('EASYREST_CORE_MIN_PHP', '7.4');
define('EASYREST_CORE_MIN_WP', '6.0');

/**
 * Check requirements before loading
 */
function easyrest_core_check_requirements() {
    $errors = array();
    
    // Check PHP version
    if (version_compare(PHP_VERSION, EASYREST_CORE_MIN_PHP, '<')) {
        $errors[] = sprintf(
            /* translators: 1: Required PHP version, 2: Current PHP version */
            __('EasyRest Core requires PHP %1$s or higher. You are running PHP %2$s.', 'easyrest-core'),
            EASYREST_CORE_MIN_PHP,
            PHP_VERSION
        );
    }
    
    // Check WordPress version
    global $wp_version;
    if (version_compare($wp_version, EASYREST_CORE_MIN_WP, '<')) {
        $errors[] = sprintf(
            /* translators: 1: Required WP version, 2: Current WP version */
            __('EasyRest Core requires WordPress %1$s or higher. You are running WordPress %2$s.', 'easyrest-core'),
            EASYREST_CORE_MIN_WP,
            $wp_version
        );
    }
    
    // Check cURL extension
    if (!function_exists('curl_init')) {
        $errors[] = __('EasyRest Core requires the cURL PHP extension.', 'easyrest-core');
    }
    
    return $errors;
}

/**
 * Display admin notice for requirements errors
 */
function easyrest_core_requirements_notice() {
    $errors = easyrest_core_check_requirements();
    
    if (!empty($errors)) {
        echo '<div class="notice notice-error"><p><strong>EasyRest Core:</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
}

/**
 * Initialize the plugin
 */
function easyrest_core_init() {
    // Check requirements
    $errors = easyrest_core_check_requirements();
    
    if (!empty($errors)) {
        add_action('admin_notices', 'easyrest_core_requirements_notice');
        return;
    }
    
    // Load text domain
    load_plugin_textdomain('easyrest-core', false, dirname(EASYREST_CORE_BASENAME) . '/languages');
    
    // Load classes
    require_once EASYREST_CORE_DIR . 'includes/class-logger.php';
    require_once EASYREST_CORE_DIR . 'includes/class-scraper.php';
    require_once EASYREST_CORE_DIR . 'includes/class-price-service.php';
    require_once EASYREST_CORE_DIR . 'includes/class-settings.php';
    require_once EASYREST_CORE_DIR . 'includes/class-ajax.php';
    require_once EASYREST_CORE_DIR . 'includes/class-plugin.php';

    // Load integrations
    require_once EASYREST_CORE_DIR . 'includes/integrations/class-wphb-pricing-bridge.php';
    require_once EASYREST_CORE_DIR . 'includes/integrations/class-mphb-pricing-bridge.php';

    // Initialize main plugin class
    EasyRest_Core_Plugin::get_instance();

    // Initialize WP Hotel Booking bridge (checks internally if WPHB is active and bridge is enabled)
    EasyRest_WPHB_Pricing_Bridge::get_instance();

    // Initialize MotoPress Hotel Booking bridge (checks internally if MPHB is active and bridge is enabled)
    EasyRest_MPHB_Pricing_Bridge::get_instance();
}
add_action('plugins_loaded', 'easyrest_core_init');

/**
 * Activation hook
 */
function easyrest_core_activate() {
    // Check requirements
    $errors = easyrest_core_check_requirements();
    
    if (!empty($errors)) {
        wp_die(
            implode('<br>', array_map('esc_html', $errors)),
            'Plugin Activation Error',
            array('back_link' => true)
        );
    }
    
    // Set default options
    $defaults = array(
        'easyrest_booking_url' => 'https://www.booking.com/hotel/it/easy-rest-affitti-brevi-italia.fr.html',
        'easyrest_discount_percent' => 15,
        'easyrest_whatsapp_number' => '',
        'easyrest_email' => 'contact@easyrest.eu',
        'easyrest_cache_duration' => 15,
        'easyrest_rate_limit' => 10,
        'easyrest_debug_mode' => false,
        // WP Hotel Booking bridge defaults (disabled by default)
        'easyrest_wphb_dynamic_pricing_enabled' => false,
        'easyrest_wphb_dynamic_pricing_fallback' => 'block',
        'easyrest_wphb_validation_enabled' => true,
        'easyrest_wphb_cache_ttl' => 900, // 15 minutes in seconds
        // MotoPress Hotel Booking bridge defaults (disabled by default)
        'easyrest_mphb_dynamic_pricing_enabled' => false,
        'easyrest_mphb_dynamic_pricing_fallback' => 'block',
        'easyrest_mphb_validation_enabled' => true,
        'easyrest_mphb_cache_ttl' => 900, // 15 minutes in seconds
    );
    
    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
    
    // Clear any existing price cache (safe check for $wpdb)
    global $wpdb;
    if ($wpdb) {
        easyrest_core_clear_cache();
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'easyrest_core_activate');

/**
 * Deactivation hook
 */
function easyrest_core_deactivate() {
    // Clear cache
    easyrest_core_clear_cache();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'easyrest_core_deactivate');

/**
 * Uninstall hook (static, cannot use class methods)
 */
function easyrest_core_uninstall() {
    // Only run if explicitly uninstalling
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }
    
    // Remove options
    $options = array(
        'easyrest_booking_url',
        'easyrest_discount_percent',
        'easyrest_whatsapp_number',
        'easyrest_email',
        'easyrest_cache_duration',
        'easyrest_rate_limit',
        'easyrest_debug_mode',
        // WP Hotel Booking bridge options
        'easyrest_wphb_dynamic_pricing_enabled',
        'easyrest_wphb_dynamic_pricing_fallback',
        'easyrest_wphb_validation_enabled',
        'easyrest_wphb_cache_ttl',
        // MotoPress Hotel Booking bridge options
        'easyrest_mphb_dynamic_pricing_enabled',
        'easyrest_mphb_dynamic_pricing_fallback',
        'easyrest_mphb_validation_enabled',
        'easyrest_mphb_cache_ttl',
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Clear cache
    easyrest_core_clear_cache();
}

/**
 * Clear all plugin transients
 */
function easyrest_core_clear_cache() {
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_easyrest_%' 
         OR option_name LIKE '_transient_timeout_easyrest_%'"
    );
    
    return true;
}

/**
 * Public API: Get price (for use by theme)
 * 
 * @param string $checkin  Check-in date (Y-m-d)
 * @param string $checkout Check-out date (Y-m-d)
 * @param int    $adults   Number of adults
 * @param int    $children Number of children
 * @return array Price data or error
 */
function easyrest_get_price($checkin, $checkout, $adults = 2, $children = 0) {
    if (!class_exists('EasyRest_Price_Service')) {
        return array('success' => false, 'error' => 'Plugin not loaded');
    }
    
    $service = new EasyRest_Price_Service();
    return $service->get_price($checkin, $checkout, $adults, $children);
}

/**
 * Public API: Get plugin option
 * 
 * @param string $key Option key (without prefix)
 * @param mixed  $default Default value
 * @return mixed Option value
 */
function easyrest_get_option($key, $default = null) {
    return get_option('easyrest_' . $key, $default);
}
