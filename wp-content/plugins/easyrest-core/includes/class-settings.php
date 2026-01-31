<?php
/**
 * Settings Class
 *
 * Admin settings page and options management
 *
 * @package EasyRest_Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_Settings
 */
class EasyRest_Settings {

    /**
     * Logger instance
     *
     * @var EasyRest_Logger|null
     */
    private $logger;

    /**
     * Settings group name
     *
     * @var string
     */
    private $settings_group = 'easyrest_settings_group';

    /**
     * Constructor
     *
     * @param EasyRest_Logger|null $logger Logger instance
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
    }

    /**
     * Register admin menu
     */
    public function register_menu() {
        add_menu_page(
            __('EasyRest Settings', 'easyrest-core'),
            __('EasyRest', 'easyrest-core'),
            'manage_options',
            'easyrest-settings',
            array($this, 'render_settings_page'),
            'dashicons-building',
            30
        );
        
        // Submenu for logs (if debug mode)
        if (get_option('easyrest_debug_mode', false)) {
            add_submenu_page(
                'easyrest-settings',
                __('Logs', 'easyrest-core'),
                __('Logs', 'easyrest-core'),
                'manage_options',
                'easyrest-logs',
                array($this, 'render_logs_page')
            );
        }
    }

    /**
     * Register settings with WordPress Settings API
     */
    public function register_settings() {
        // Booking URL
        register_setting($this->settings_group, 'easyrest_booking_url', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_booking_url'),
            'default' => 'https://www.booking.com/hotel/it/easy-rest-affitti-brevi-italia.fr.html',
        ));
        
        // Discount percentage
        register_setting($this->settings_group, 'easyrest_discount_percent', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_discount'),
            'default' => 15,
        ));
        
        // WhatsApp number
        register_setting($this->settings_group, 'easyrest_whatsapp_number', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_phone'),
            'default' => '',
        ));
        
        // Email
        register_setting($this->settings_group, 'easyrest_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => 'contact@easyrest.eu',
        ));
        
        // Cache duration
        register_setting($this->settings_group, 'easyrest_cache_duration', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_cache_duration'),
            'default' => 15,
        ));
        
        // Rate limit
        register_setting($this->settings_group, 'easyrest_rate_limit', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_rate_limit'),
            'default' => 10,
        ));
        
        // Debug mode
        register_setting($this->settings_group, 'easyrest_debug_mode', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ));

        // Node.js scraper path (CLI mode)
        register_setting($this->settings_group, 'easyrest_node_scraper_path', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        // Microservice URL
        register_setting($this->settings_group, 'easyrest_microservice_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'http://localhost:3456',
        ));

        // Microservice Token
        register_setting($this->settings_group, 'easyrest_microservice_token', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        // WP Hotel Booking bridge settings
        if (class_exists('EasyRest_WPHB_Pricing_Bridge')) {
            EasyRest_WPHB_Pricing_Bridge::register_settings();
        }

        // MotoPress Hotel Booking bridge settings
        if (class_exists('EasyRest_MPHB_Pricing_Bridge')) {
            EasyRest_MPHB_Pricing_Bridge::register_settings();
        }

        // Add settings sections
        add_settings_section(
            'easyrest_booking_section',
            __('Booking.com Integration', 'easyrest-core'),
            array($this, 'render_booking_section'),
            'easyrest-settings'
        );
        
        add_settings_section(
            'easyrest_contact_section',
            __('Contact Information', 'easyrest-core'),
            array($this, 'render_contact_section'),
            'easyrest-settings'
        );
        
        add_settings_section(
            'easyrest_advanced_section',
            __('Advanced Settings', 'easyrest-core'),
            array($this, 'render_advanced_section'),
            'easyrest-settings'
        );

        // WP Hotel Booking Integration section
        add_settings_section(
            'easyrest_wphb_section',
            __('WP Hotel Booking Integration', 'easyrest-core'),
            array($this, 'render_wphb_section'),
            'easyrest-settings'
        );

        // MotoPress Hotel Booking Integration section
        add_settings_section(
            'easyrest_mphb_section',
            __('MotoPress Hotel Booking Integration', 'easyrest-core'),
            array($this, 'render_mphb_section'),
            'easyrest-settings'
        );

        // Add settings fields
        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // Booking section
        add_settings_field(
            'easyrest_booking_url',
            __('Booking.com URL', 'easyrest-core'),
            array($this, 'render_url_field'),
            'easyrest-settings',
            'easyrest_booking_section'
        );
        
        add_settings_field(
            'easyrest_discount_percent',
            __('Discount (%)', 'easyrest-core'),
            array($this, 'render_discount_field'),
            'easyrest-settings',
            'easyrest_booking_section'
        );
        
        // Contact section
        add_settings_field(
            'easyrest_whatsapp_number',
            __('WhatsApp Number', 'easyrest-core'),
            array($this, 'render_whatsapp_field'),
            'easyrest-settings',
            'easyrest_contact_section'
        );
        
        add_settings_field(
            'easyrest_email',
            __('Contact Email', 'easyrest-core'),
            array($this, 'render_email_field'),
            'easyrest-settings',
            'easyrest_contact_section'
        );
        
        // Advanced section
        add_settings_field(
            'easyrest_cache_duration',
            __('Cache Duration (minutes)', 'easyrest-core'),
            array($this, 'render_cache_field'),
            'easyrest-settings',
            'easyrest_advanced_section'
        );
        
        add_settings_field(
            'easyrest_rate_limit',
            __('Rate Limit (requests/minute)', 'easyrest-core'),
            array($this, 'render_rate_limit_field'),
            'easyrest-settings',
            'easyrest_advanced_section'
        );
        
        add_settings_field(
            'easyrest_debug_mode',
            __('Debug Mode', 'easyrest-core'),
            array($this, 'render_debug_field'),
            'easyrest-settings',
            'easyrest_advanced_section'
        );

        add_settings_field(
            'easyrest_node_scraper_path',
            __('Node.js Scraper Path (CLI)', 'easyrest-core'),
            array($this, 'render_node_scraper_field'),
            'easyrest-settings',
            'easyrest_advanced_section'
        );

        add_settings_field(
            'easyrest_microservice_url',
            __('Microservice URL', 'easyrest-core'),
            array($this, 'render_microservice_url_field'),
            'easyrest-settings',
            'easyrest_advanced_section'
        );

        add_settings_field(
            'easyrest_microservice_token',
            __('Microservice Token', 'easyrest-core'),
            array($this, 'render_microservice_token_field'),
            'easyrest-settings',
            'easyrest_advanced_section'
        );

        // WP Hotel Booking Integration fields
        add_settings_field(
            'easyrest_wphb_dynamic_pricing_enabled',
            __('Enable Dynamic Pricing', 'easyrest-core'),
            array('EasyRest_WPHB_Pricing_Bridge', 'render_enabled_field'),
            'easyrest-settings',
            'easyrest_wphb_section'
        );

        add_settings_field(
            'easyrest_wphb_dynamic_pricing_fallback',
            __('On Price Fetch Failure', 'easyrest-core'),
            array('EasyRest_WPHB_Pricing_Bridge', 'render_fallback_mode_field'),
            'easyrest-settings',
            'easyrest_wphb_section'
        );

        add_settings_field(
            'easyrest_wphb_validation_enabled',
            __('Pre-Checkout Validation', 'easyrest-core'),
            array('EasyRest_WPHB_Pricing_Bridge', 'render_validation_field'),
            'easyrest-settings',
            'easyrest_wphb_section'
        );

        add_settings_field(
            'easyrest_wphb_cache_ttl',
            __('Price Cache TTL', 'easyrest-core'),
            array('EasyRest_WPHB_Pricing_Bridge', 'render_cache_ttl_field'),
            'easyrest-settings',
            'easyrest_wphb_section'
        );

        // MotoPress Hotel Booking Integration fields
        add_settings_field(
            'easyrest_mphb_dynamic_pricing_enabled',
            __('Enable Dynamic Pricing', 'easyrest-core'),
            array('EasyRest_MPHB_Pricing_Bridge', 'render_enabled_field'),
            'easyrest-settings',
            'easyrest_mphb_section'
        );

        add_settings_field(
            'easyrest_mphb_dynamic_pricing_fallback',
            __('On Price Fetch Failure', 'easyrest-core'),
            array('EasyRest_MPHB_Pricing_Bridge', 'render_fallback_mode_field'),
            'easyrest-settings',
            'easyrest_mphb_section'
        );

        add_settings_field(
            'easyrest_mphb_validation_enabled',
            __('Pre-Checkout Validation', 'easyrest-core'),
            array('EasyRest_MPHB_Pricing_Bridge', 'render_validation_field'),
            'easyrest-settings',
            'easyrest_mphb_section'
        );

        add_settings_field(
            'easyrest_mphb_cache_ttl',
            __('Price Cache TTL', 'easyrest-core'),
            array('EasyRest_MPHB_Pricing_Bridge', 'render_cache_ttl_field'),
            'easyrest-settings',
            'easyrest_mphb_section'
        );
    }

    /**
     * Sanitize Booking URL
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    public function sanitize_booking_url($url) {
        $url = esc_url_raw($url);
        
        // Validate it's a Booking.com URL
        $parsed = wp_parse_url($url);
        if (empty($parsed['host']) || !in_array($parsed['host'], array('booking.com', 'www.booking.com'), true)) {
            add_settings_error(
                'easyrest_booking_url',
                'invalid_url',
                __('Please enter a valid Booking.com URL.', 'easyrest-core')
            );
            return get_option('easyrest_booking_url');
        }
        
        return $url;
    }

    /**
     * Sanitize discount percentage
     *
     * @param mixed $value Value to sanitize
     * @return int Sanitized value
     */
    public function sanitize_discount($value) {
        $value = absint($value);
        return min(max($value, 0), 50);
    }

    /**
     * Sanitize phone number
     *
     * @param string $phone Phone number
     * @return string Sanitized phone
     */
    public function sanitize_phone($phone) {
        // Remove all non-numeric except +
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    /**
     * Sanitize cache duration
     *
     * @param mixed $value Value to sanitize
     * @return int Sanitized value (5-60 minutes)
     */
    public function sanitize_cache_duration($value) {
        $value = absint($value);
        return min(max($value, 5), 60);
    }

    /**
     * Sanitize rate limit
     *
     * @param mixed $value Value to sanitize
     * @return int Sanitized value (5-50)
     */
    public function sanitize_rate_limit($value) {
        $value = absint($value);
        return min(max($value, 5), 50);
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'easyrest-core'));
        }
        
        // Handle cache clear
        if (isset($_POST['easyrest_clear_cache']) && check_admin_referer('easyrest_clear_cache_action')) {
            easyrest_core_clear_cache();
            echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared successfully!', 'easyrest-core') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->settings_group);
                do_settings_sections('easyrest-settings');
                submit_button(__('Save Settings', 'easyrest-core'));
                ?>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('Maintenance', 'easyrest-core'); ?></h2>
            
            <?php $this->render_cache_stats(); ?>
            
            <form method="post">
                <?php wp_nonce_field('easyrest_clear_cache_action'); ?>
                <p>
                    <button type="submit" name="easyrest_clear_cache" class="button button-secondary">
                        <?php esc_html_e('Clear Price Cache', 'easyrest-core'); ?>
                    </button>
                    <span class="description">
                        <?php esc_html_e('Clear all cached prices to fetch fresh data from Booking.com.', 'easyrest-core'); ?>
                    </span>
                </p>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('Test Price Retrieval', 'easyrest-core'); ?></h2>
            <?php $this->render_test_form(); ?>
        </div>
        <?php
    }

    /**
     * Render cache statistics
     */
    private function render_cache_stats() {
        $service = new EasyRest_Price_Service();
        $stats = $service->get_cache_stats();
        
        echo '<p>';
        printf(
            /* translators: %d: number of cached prices */
            esc_html__('Currently %d prices cached.', 'easyrest-core'),
            $stats['cached_prices']
        );
        echo '</p>';
    }

    /**
     * Render test form
     */
    private function render_test_form() {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $next_week = date('Y-m-d', strtotime('+7 days'));
        ?>
        <div id="easyrest-test-form">
            <p>
                <label><?php esc_html_e('Check-in:', 'easyrest-core'); ?></label>
                <input type="date" id="test-checkin" value="<?php echo esc_attr($tomorrow); ?>">
                
                <label><?php esc_html_e('Check-out:', 'easyrest-core'); ?></label>
                <input type="date" id="test-checkout" value="<?php echo esc_attr($next_week); ?>">
                
                <label><?php esc_html_e('Adults:', 'easyrest-core'); ?></label>
                <select id="test-adults">
                    <option value="1">1</option>
                    <option value="2" selected>2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
                
                <button type="button" id="test-price-btn" class="button button-secondary">
                    <?php esc_html_e('Test Price', 'easyrest-core'); ?>
                </button>
            </p>
            
            <div id="test-result" style="display:none; margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 4px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-price-btn').on('click', function() {
                var $btn = $(this);
                var $result = $('#test-result');
                
                $btn.prop('disabled', true).text('<?php esc_html_e('Loading...', 'easyrest-core'); ?>');
                $result.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'easyrest_get_price',
                        nonce: '<?php echo esc_js(wp_create_nonce('easyrest_price_nonce')); ?>',
                        checkin: $('#test-checkin').val(),
                        checkout: $('#test-checkout').val(),
                        adults: $('#test-adults').val(),
                        children: 0
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<strong><?php esc_html_e('Success!', 'easyrest-core'); ?></strong><br>';
                            html += '<?php esc_html_e('Booking.com price:', 'easyrest-core'); ?> €' + response.data.booking_price + '<br>';
                            html += '<?php esc_html_e('Direct price:', 'easyrest-core'); ?> €' + response.data.direct_price + '<br>';
                            html += '<?php esc_html_e('Savings:', 'easyrest-core'); ?> €' + response.data.savings + ' (-' + response.data.discount + '%)<br>';
                            html += '<?php esc_html_e('Cached:', 'easyrest-core'); ?> ' + (response.data.cached ? '<?php esc_html_e('Yes', 'easyrest-core'); ?>' : '<?php esc_html_e('No', 'easyrest-core'); ?>');
                            $result.html(html).css('background', '#d4edda').show();
                        } else {
                            $result.html('<strong><?php esc_html_e('Error:', 'easyrest-core'); ?></strong> ' + (response.data ? response.data.message : 'Unknown error')).css('background', '#f8d7da').show();
                        }
                    },
                    error: function() {
                        $result.html('<strong><?php esc_html_e('Error:', 'easyrest-core'); ?></strong> <?php esc_html_e('Request failed', 'easyrest-core'); ?>').css('background', '#f8d7da').show();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php esc_html_e('Test Price', 'easyrest-core'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render logs page
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'easyrest-core'));
        }
        
        // Handle clear logs
        if (isset($_POST['easyrest_clear_logs']) && check_admin_referer('easyrest_clear_logs_action')) {
            if ($this->logger) {
                $this->logger->clear_logs();
                echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared!', 'easyrest-core') . '</p></div>';
            }
        }
        
        $logs = $this->logger ? $this->logger->get_recent_logs(200) : array();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('EasyRest Logs', 'easyrest-core'); ?></h1>
            
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field('easyrest_clear_logs_action'); ?>
                <button type="submit" name="easyrest_clear_logs" class="button button-secondary">
                    <?php esc_html_e('Clear Logs', 'easyrest-core'); ?>
                </button>
            </form>
            
            <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; max-height: 600px; overflow-y: auto; font-family: monospace; font-size: 12px;">
                <?php if (empty($logs)) : ?>
                    <em><?php esc_html_e('No logs available.', 'easyrest-core'); ?></em>
                <?php else : ?>
                    <?php foreach (array_reverse($logs) as $log) : ?>
                        <div style="margin-bottom: 2px; <?php echo strpos($log, 'ERROR') !== false ? 'color: #f48771;' : ''; ?>">
                            <?php echo esc_html($log); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // Section render methods
    public function render_booking_section() {
        echo '<p>' . esc_html__('Configure your Booking.com integration settings.', 'easyrest-core') . '</p>';
    }
    
    public function render_contact_section() {
        echo '<p>' . esc_html__('Contact information for reservations.', 'easyrest-core') . '</p>';
    }
    
    public function render_advanced_section() {
        echo '<p>' . esc_html__('Advanced configuration options.', 'easyrest-core') . '</p>';
    }

    public function render_wphb_section() {
        if (class_exists('EasyRest_WPHB_Pricing_Bridge')) {
            EasyRest_WPHB_Pricing_Bridge::render_settings_section();
        } else {
            echo '<p>' . esc_html__('WP Hotel Booking integration module not loaded.', 'easyrest-core') . '</p>';
        }
    }

    public function render_mphb_section() {
        if (class_exists('EasyRest_MPHB_Pricing_Bridge')) {
            EasyRest_MPHB_Pricing_Bridge::render_settings_section();
        } else {
            echo '<p>' . esc_html__('MotoPress Hotel Booking integration module not loaded.', 'easyrest-core') . '</p>';
        }
    }

    // Field render methods
    public function render_url_field() {
        $value = get_option('easyrest_booking_url', '');
        echo '<input type="url" name="easyrest_booking_url" value="' . esc_url($value) . '" class="regular-text" style="width:100%;">';
        echo '<p class="description">' . esc_html__('Your property URL on Booking.com', 'easyrest-core') . '</p>';
    }
    
    public function render_discount_field() {
        $value = get_option('easyrest_discount_percent', 15);
        echo '<input type="number" name="easyrest_discount_percent" value="' . esc_attr($value) . '" min="0" max="50" style="width:80px;"> %';
        echo '<p class="description">' . esc_html__('Discount compared to Booking.com price (0-50%)', 'easyrest-core') . '</p>';
    }
    
    public function render_whatsapp_field() {
        $value = get_option('easyrest_whatsapp_number', '');
        echo '<input type="text" name="easyrest_whatsapp_number" value="' . esc_attr($value) . '" class="regular-text" placeholder="+39123456789">';
        echo '<p class="description">' . esc_html__('International format with country code', 'easyrest-core') . '</p>';
    }
    
    public function render_email_field() {
        $value = get_option('easyrest_email', 'contact@easyrest.eu');
        echo '<input type="email" name="easyrest_email" value="' . esc_attr($value) . '" class="regular-text">';
    }
    
    public function render_cache_field() {
        $value = get_option('easyrest_cache_duration', 15);
        echo '<input type="number" name="easyrest_cache_duration" value="' . esc_attr($value) . '" min="5" max="60" style="width:80px;"> ' . esc_html__('minutes', 'easyrest-core');
        echo '<p class="description">' . esc_html__('How long to cache prices (5-60 minutes)', 'easyrest-core') . '</p>';
    }
    
    public function render_rate_limit_field() {
        $value = get_option('easyrest_rate_limit', 10);
        echo '<input type="number" name="easyrest_rate_limit" value="' . esc_attr($value) . '" min="5" max="50" style="width:80px;"> ' . esc_html__('requests/minute per IP', 'easyrest-core');
        echo '<p class="description">' . esc_html__('Maximum requests per minute per visitor', 'easyrest-core') . '</p>';
    }
    
    public function render_debug_field() {
        $value = get_option('easyrest_debug_mode', false);
        echo '<label><input type="checkbox" name="easyrest_debug_mode" value="1" ' . checked($value, true, false) . '> ';
        echo esc_html__('Enable debug logging', 'easyrest-core') . '</label>';
        echo '<p class="description">' . esc_html__('Log detailed information for troubleshooting', 'easyrest-core') . '</p>';
    }

    public function render_node_scraper_field() {
        $value = get_option('easyrest_node_scraper_path', '');
        $default_path = EASYREST_CORE_DIR . 'scraper-node/scraper.js';

        echo '<input type="text" name="easyrest_node_scraper_path" value="' . esc_attr($value) . '" class="regular-text" style="width:100%;" placeholder="' . esc_attr($default_path) . '">';
        echo '<p class="description">' . esc_html__('CLI mode: Path to scraper.js (fallback if microservice unavailable)', 'easyrest-core') . '</p>';
    }

    public function render_microservice_url_field() {
        $value = get_option('easyrest_microservice_url', 'http://localhost:3456');

        echo '<input type="url" name="easyrest_microservice_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="http://localhost:3456">';
        echo '<p class="description">' . esc_html__('URL of the Node.js scraper microservice (recommended for production)', 'easyrest-core') . '</p>';

        // Test microservice connection
        if (!empty($value)) {
            $test_url = trailingslashit($value) . 'health';
            $response = wp_remote_get($test_url, array('timeout' => 5));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                echo '<p class="description" style="color:green;">✓ ' . esc_html__('Microservice connected', 'easyrest-core');
                if (!empty($data['version'])) {
                    echo ' (v' . esc_html($data['version']) . ')';
                }
                echo '</p>';
            } else {
                echo '<p class="description" style="color:orange;">⚠ ' . esc_html__('Microservice not responding. Make sure it is running.', 'easyrest-core') . '</p>';
            }
        }
    }

    public function render_microservice_token_field() {
        $value = get_option('easyrest_microservice_token', '');

        echo '<input type="text" name="easyrest_microservice_token" value="' . esc_attr($value) . '" class="regular-text" style="width:100%;" placeholder="' . esc_attr__('Enter your secret token', 'easyrest-core') . '">';
        echo '<p class="description">' . esc_html__('Security token (must match EASYREST_TOKEN in microservice .env)', 'easyrest-core') . '</p>';

        if (empty($value)) {
            echo '<p class="description" style="color:orange;">⚠ ' . esc_html__('Token not set. Microservice will not be used. CLI fallback will be used instead.', 'easyrest-core') . '</p>';
        }
    }
}
