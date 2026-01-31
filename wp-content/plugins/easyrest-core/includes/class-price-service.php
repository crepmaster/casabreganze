<?php
/**
 * Price Service Class
 *
 * Business logic layer for price retrieval with caching and rate limiting
 * 
 * CONTRAT JSON GARANTI:
 * Succès:
 * {
 *   success: true,
 *   booking_price: float,
 *   direct_price: float,
 *   discount: float,
 *   currency: string,
 *   nights: int,
 *   price_per_night: float,
 *   savings: float,
 *   meta: {
 *     source: 'booking'|'cache'|'fallback',
 *     cached: bool,
 *     warnings: string[]
 *   }
 * }
 * 
 * Erreur:
 * {
 *   success: false,
 *   code: string,
 *   error: string
 * }
 *
 * @package EasyRest_Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_Price_Service
 */
class EasyRest_Price_Service {

    /**
     * Error codes
     */
    const ERROR_VALIDATION = 'validation_error';
    const ERROR_RATE_LIMITED = 'rate_limited';
    const ERROR_SCRAPE_FAILED = 'scrape_failed';
    const ERROR_INTERNAL = 'internal_error';

    /**
     * Source types
     */
    const SOURCE_BOOKING = 'booking';
    const SOURCE_CACHE = 'cache';
    const SOURCE_FALLBACK = 'fallback';

    /**
     * Scraper instance
     *
     * @var EasyRest_Scraper
     */
    private $scraper;

    /**
     * Logger instance
     *
     * @var EasyRest_Logger|null
     */
    private $logger;

    /**
     * Cache duration in minutes
     *
     * @var int
     */
    private $cache_duration;

    /**
     * Rate limit (requests per minute per IP)
     *
     * @var int
     */
    private $rate_limit;

    /**
     * Discount percentage
     *
     * @var float
     */
    private $discount_percent;

    /**
     * Constructor
     *
     * @param EasyRest_Logger|null $logger Logger instance
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->scraper = new EasyRest_Scraper($logger);
        
        // Load settings
        $this->cache_duration = absint(get_option('easyrest_cache_duration', 15));
        $this->rate_limit = absint(get_option('easyrest_rate_limit', 10));
        $this->discount_percent = floatval(get_option('easyrest_discount_percent', 15));
    }

    /**
     * Get price with caching and validation
     * 
     * TOUJOURS retourne un array avec structure garantie
     *
     * @param string $checkin  Check-in date (Y-m-d)
     * @param string $checkout Check-out date (Y-m-d)
     * @param int    $adults   Number of adults
     * @param int    $children Number of children
     * @param string $currency Currency code
     * @return array Result array (contrat garanti)
     */
    public function get_price($checkin, $checkout, $adults = 2, $children = 0, $currency = 'EUR') {
        $warnings = array();
        
        try {
            // Validate inputs
            $validation = $this->validate_inputs($checkin, $checkout, $adults, $children);
            if (!$validation['valid']) {
                return $this->build_error_response(
                    self::ERROR_VALIDATION,
                    $validation['error']
                );
            }
            
            // Sanitize inputs
            $checkin = sanitize_text_field($checkin);
            $checkout = sanitize_text_field($checkout);
            $adults = absint($adults);
            $children = absint($children);
            $currency = sanitize_text_field($currency);
            
            // Apply room logic (single person gets double room)
            $original_guests = $adults + $children;
            if ($original_guests === 1) {
                $adults = 2;
                $children = 0;
                $warnings[] = 'Single occupancy priced as double room';
            }
            
            // Check rate limit
            if (!$this->check_rate_limit()) {
                $this->log('warning', 'Rate limit exceeded');
                return $this->build_error_response(
                    self::ERROR_RATE_LIMITED,
                    'Too many requests. Please wait a moment and try again.'
                );
            }
            
            // Check cache
            $cache_key = $this->get_cache_key($checkin, $checkout, $adults, $children, $currency);
            $cached = get_transient($cache_key);
            
            if ($cached !== false && isset($cached['success']) && $cached['success'] === true) {
                $this->log('debug', 'Cache hit', array('key' => $cache_key));
                
                // Mettre à jour meta pour indiquer cache
                $cached['meta']['cached'] = true;
                $cached['meta']['source'] = self::SOURCE_CACHE;
                
                return $cached;
            }
            
            // Scrape price
            $result = $this->scraper->scrape_price($checkin, $checkout, $adults, $children, $currency);
            
            if (!$result['success']) {
                // NE PAS mettre en cache les erreurs - retour direct
                return $this->build_error_response(
                    self::ERROR_SCRAPE_FAILED,
                    isset($result['error']) ? $result['error'] : 'Price not available for these dates'
                );
            }
            
            // Calculate final price with discount
            $booking_price = floatval($result['price']);
            $direct_price = $booking_price * (1 - $this->discount_percent / 100);
            $nights = $this->calculate_nights($checkin, $checkout);
            
            // Build success response with full contract
            $final_result = $this->build_success_response(
                $booking_price,
                $direct_price,
                $nights,
                $currency,
                self::SOURCE_BOOKING,
                false,
                $warnings
            );
            
            // Cache successful result
            set_transient($cache_key, $final_result, $this->cache_duration * MINUTE_IN_SECONDS);
            
            $this->log('info', 'Price retrieved and cached', array(
                'booking_price' => $booking_price,
                'direct_price' => $direct_price,
            ));
            
            return $final_result;
            
        } catch (Exception $e) {
            // Catch any unexpected exception
            $this->log('error', 'Unexpected exception: ' . $e->getMessage());
            
            return $this->build_error_response(
                self::ERROR_INTERNAL,
                'An unexpected error occurred. Please try again.'
            );
        }
    }

    /**
     * Build standardized success response
     *
     * @param float  $booking_price Booking.com price
     * @param float  $direct_price  Direct price
     * @param int    $nights        Number of nights
     * @param string $currency      Currency code
     * @param string $source        Data source
     * @param bool   $cached        Whether from cache
     * @param array  $warnings      Warning messages
     * @return array
     */
    private function build_success_response($booking_price, $direct_price, $nights, $currency, $source, $cached, $warnings = array()) {
        return array(
            'success' => true,
            'booking_price' => round((float) $booking_price, 2),
            'direct_price' => round((float) $direct_price, 2),
            'discount' => (float) $this->discount_percent,
            'currency' => (string) $currency,
            'nights' => (int) $nights,
            'price_per_night' => round((float) $direct_price / max(1, $nights), 2),
            'savings' => round((float) ($booking_price - $direct_price), 2),
            'meta' => array(
                'source' => (string) $source,
                'cached' => (bool) $cached,
                'warnings' => (array) $warnings,
            ),
        );
    }

    /**
     * Build standardized error response
     *
     * @param string $code    Error code
     * @param string $message Error message
     * @return array
     */
    private function build_error_response($code, $message) {
        return array(
            'success' => false,
            'code' => (string) $code,
            'error' => (string) $message,
        );
    }

    /**
     * Validate input parameters
     *
     * @param string $checkin  Check-in date
     * @param string $checkout Check-out date
     * @param int    $adults   Number of adults
     * @param int    $children Number of children
     * @return array Validation result
     */
    private function validate_inputs($checkin, $checkout, $adults, $children) {
        // Date format validation
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin)) {
            return array('valid' => false, 'error' => 'Invalid check-in date format. Use YYYY-MM-DD.');
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) {
            return array('valid' => false, 'error' => 'Invalid check-out date format. Use YYYY-MM-DD.');
        }
        
        // Parse dates (with exception safety)
        try {
            $checkin_date = DateTime::createFromFormat('Y-m-d', $checkin);
            $checkout_date = DateTime::createFromFormat('Y-m-d', $checkout);
        } catch (Exception $e) {
            return array('valid' => false, 'error' => 'Invalid date.');
        }
        
        if (!$checkin_date || !$checkout_date) {
            return array('valid' => false, 'error' => 'Invalid date.');
        }
        
        // Validate dates are real (reject 2024-02-30)
        if ($checkin_date->format('Y-m-d') !== $checkin || $checkout_date->format('Y-m-d') !== $checkout) {
            return array('valid' => false, 'error' => 'Invalid date.');
        }
        
        // Check-out must be after check-in
        if ($checkout_date <= $checkin_date) {
            return array('valid' => false, 'error' => 'Check-out date must be after check-in date.');
        }
        
        // Check-in not in the past
        $today = new DateTime('today');
        if ($checkin_date < $today) {
            return array('valid' => false, 'error' => 'Check-in date cannot be in the past.');
        }
        
        // Maximum stay (30 days)
        $max_nights = 30;
        $nights = $checkin_date->diff($checkout_date)->days;
        if ($nights > $max_nights) {
            return array('valid' => false, 'error' => "Maximum stay is $max_nights nights.");
        }
        
        // Guest count validation
        $adults = intval($adults);
        $children = intval($children);
        $total = $adults + $children;
        
        if ($adults < 1) {
            return array('valid' => false, 'error' => 'At least 1 adult is required.');
        }
        
        if ($total > 4) {
            return array('valid' => false, 'error' => 'Maximum 4 guests allowed.');
        }
        
        if ($children < 0 || $children > 3) {
            return array('valid' => false, 'error' => 'Invalid number of children.');
        }
        
        return array('valid' => true);
    }

    /**
     * Check rate limiting
     *
     * @return bool True if request is allowed
     */
    private function check_rate_limit() {
        $ip_hash = $this->get_ip_hash();
        $rate_key = 'easyrest_rate_' . $ip_hash;
        
        $current = get_transient($rate_key);
        
        if ($current === false) {
            // First request in window
            set_transient($rate_key, 1, MINUTE_IN_SECONDS);
            return true;
        }
        
        if ($current >= $this->rate_limit) {
            // Rate limit exceeded
            return false;
        }
        
        // Increment counter
        set_transient($rate_key, $current + 1, MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * Get hashed client IP
     *
     * @return string Hashed IP
     */
    private function get_ip_hash() {
        $ip = '';
        
        // Check various headers
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        );
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                break;
            }
        }
        
        // Hash for privacy
        return md5($ip . 'easyrest_salt_' . NONCE_SALT);
    }

    /**
     * Generate cache key
     *
     * @param string $checkin  Check-in date
     * @param string $checkout Check-out date
     * @param int    $adults   Adults count
     * @param int    $children Children count
     * @param string $currency Currency code
     * @return string Cache key
     */
    private function get_cache_key($checkin, $checkout, $adults, $children, $currency) {
        $data = "{$checkin}|{$checkout}|{$adults}|{$children}|{$currency}";
        return 'easyrest_price_' . md5($data);
    }

    /**
     * Calculate number of nights
     *
     * @param string $checkin  Check-in date
     * @param string $checkout Check-out date
     * @return int Number of nights
     */
    private function calculate_nights($checkin, $checkout) {
        try {
            $start = new DateTime($checkin);
            $end = new DateTime($checkout);
            return $start->diff($end)->days;
        } catch (Exception $e) {
            return 1; // Safe fallback
        }
    }

    /**
     * Clear all price cache
     *
     * @return bool
     */
    public static function clear_cache() {
        return easyrest_core_clear_cache();
    }

    /**
     * Get cache statistics
     *
     * @return array Cache stats
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_easyrest_price_%'"
        );
        
        return array(
            'cached_prices' => intval($count),
            'cache_duration' => $this->cache_duration,
            'rate_limit' => $this->rate_limit,
        );
    }

    /**
     * Log message
     *
     * @param string $level   Log level
     * @param string $message Message
     * @param array  $context Context data
     */
    private function log($level, $message, $context = array()) {
        if ($this->logger) {
            $this->logger->$level('[PriceService] ' . $message, $context);
        }
    }
}
