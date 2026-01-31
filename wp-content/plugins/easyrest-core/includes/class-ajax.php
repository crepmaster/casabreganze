<?php
/**
 * AJAX Handler Class
 *
 * Handles AJAX and REST API requests for price retrieval
 * 
 * CONTRAT JSON STRICT:
 * - Succès: { booking_price, direct_price, discount, currency, meta: { source, cached, warnings } }
 * - Erreur: { code, message }
 *
 * @package EasyRest_Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_Ajax
 */
class EasyRest_Ajax {

    /**
     * Error codes
     */
    const ERROR_NONCE_INVALID = 'nonce_invalid';
    const ERROR_DATES_MISSING = 'dates_missing';
    const ERROR_DATE_FORMAT = 'date_format_invalid';
    const ERROR_DATE_LOGIC = 'date_logic_invalid';
    const ERROR_PRICE_UNAVAILABLE = 'price_unavailable';
    const ERROR_RATE_LIMITED = 'rate_limited';
    const ERROR_VALIDATION = 'validation_error';

    /**
     * Price service instance
     *
     * @var EasyRest_Price_Service
     */
    private $price_service;

    /**
     * Logger instance
     *
     * @var EasyRest_Logger|null
     */
    private $logger;

    /**
     * Constructor
     *
     * @param EasyRest_Price_Service $price_service Price service instance
     * @param EasyRest_Logger|null   $logger        Logger instance
     */
    public function __construct($price_service, $logger = null) {
        $this->price_service = $price_service;
        $this->logger = $logger;
    }

    /**
     * Handle AJAX price request
     *
     * Called via admin-ajax.php
     */
    public function get_price() {
        // 1. Verify nonce (strict)
        if (!check_ajax_referer('easyrest_price_nonce', 'nonce', false)) {
            $this->log('warning', 'Invalid nonce in AJAX request');
            $this->send_error(self::ERROR_NONCE_INVALID, 'Security verification failed. Please refresh the page.', 403);
            return;
        }
        
        // 2. Get and sanitize parameters
        $checkin = isset($_POST['checkin']) ? sanitize_text_field(wp_unslash($_POST['checkin'])) : '';
        $checkout = isset($_POST['checkout']) ? sanitize_text_field(wp_unslash($_POST['checkout'])) : '';
        $adults = isset($_POST['adults']) ? absint($_POST['adults']) : 2;
        $children = isset($_POST['children']) ? absint($_POST['children']) : 0;
        
        // 3. Validate presence
        if (empty($checkin) || empty($checkout)) {
            $this->send_error(self::ERROR_DATES_MISSING, 'Please select check-in and check-out dates.', 400);
            return;
        }
        
        // 4. Validate ISO date format (YYYY-MM-DD)
        if (!$this->is_valid_date_format($checkin)) {
            $this->send_error(self::ERROR_DATE_FORMAT, 'Invalid check-in date format. Use YYYY-MM-DD.', 400);
            return;
        }
        
        if (!$this->is_valid_date_format($checkout)) {
            $this->send_error(self::ERROR_DATE_FORMAT, 'Invalid check-out date format. Use YYYY-MM-DD.', 400);
            return;
        }
        
        // 5. Validate date logic (checkout > checkin)
        if (!$this->is_checkout_after_checkin($checkin, $checkout)) {
            $this->send_error(self::ERROR_DATE_LOGIC, 'Check-out date must be after check-in date.', 400);
            return;
        }
        
        $this->log('debug', 'AJAX price request', array(
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
        ));
        
        // 6. Delegate to service (NO business logic here)
        $result = $this->price_service->get_price($checkin, $checkout, $adults, $children);
        
        // 7. Return response (service garantit le contrat)
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            $code = isset($result['code']) ? $result['code'] : self::ERROR_PRICE_UNAVAILABLE;
            $message = isset($result['error']) ? $result['error'] : 'Price not available';

            // Map error codes to appropriate HTTP status
            $status = 400;
            if ($code === self::ERROR_PRICE_UNAVAILABLE || $code === 'scrape_failed') {
                $status = 503; // Service unavailable
            } elseif ($code === self::ERROR_RATE_LIMITED) {
                $status = 429; // Too many requests
            }

            $this->send_error($code, $message, $status);
        }
    }

    /**
     * Handle REST API price request
     *
     * Called via REST API endpoint
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function rest_get_price($request) {
        // Get parameters (already validated by REST API schema)
        $checkin = $request->get_param('checkin');
        $checkout = $request->get_param('checkout');
        $adults = $request->get_param('adults') ?: 2;
        $children = $request->get_param('children') ?: 0;
        
        // Additional date logic check
        if (!$this->is_checkout_after_checkin($checkin, $checkout)) {
            return new WP_Error(
                self::ERROR_DATE_LOGIC,
                'Check-out date must be after check-in date.',
                array('status' => 400)
            );
        }
        
        $this->log('debug', 'REST API price request', array(
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
        ));
        
        // Get price from service
        $result = $this->price_service->get_price($checkin, $checkout, $adults, $children);
        
        // Return response
        if ($result['success']) {
            return new WP_REST_Response($result, 200);
        }

        $code = isset($result['code']) ? $result['code'] : self::ERROR_PRICE_UNAVAILABLE;
        $message = isset($result['error']) ? $result['error'] : 'Price not available';

        // Map error codes to appropriate HTTP status
        $status = 400;
        if ($code === self::ERROR_PRICE_UNAVAILABLE || $code === 'scrape_failed') {
            $status = 503; // Service unavailable
        } elseif ($code === self::ERROR_RATE_LIMITED) {
            $status = 429; // Too many requests
        }

        return new WP_Error($code, $message, array('status' => $status));
    }

    /**
     * Validate ISO date format (YYYY-MM-DD) and valid date
     *
     * @param string $date Date string
     * @return bool
     */
    private function is_valid_date_format($date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        
        // Validate actual date (e.g., reject 2024-02-30)
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }

    /**
     * Validate checkout is after checkin
     *
     * @param string $checkin  Check-in date
     * @param string $checkout Check-out date
     * @return bool
     */
    private function is_checkout_after_checkin($checkin, $checkout) {
        return strtotime($checkout) > strtotime($checkin);
    }

    /**
     * Send standardized error response
     *
     * @param string $code    Error code
     * @param string $message Human readable message
     * @param int    $status  HTTP status code
     */
    private function send_error($code, $message, $status = 400) {
        wp_send_json_error(
            array(
                'code' => $code,
                'message' => $message,
            ),
            $status
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
            $this->logger->$level('[AJAX] ' . $message, $context);
        }
    }
}
