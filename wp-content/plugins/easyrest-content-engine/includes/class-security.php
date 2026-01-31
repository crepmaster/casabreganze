<?php
/**
 * Security Class
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Security
 *
 * Handles security for REST endpoints
 */
class EasyRest_CE_Security {

    /**
     * @var string Rate limit transient key
     */
    private static $rate_limit_key = 'easyrest_ce_rate_limit';

    /**
     * Verify worker token from request
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public static function verify_worker_token(WP_REST_Request $request): bool|WP_Error {
        // Check for token in header first, then parameter
        $token = $request->get_header('X-Worker-Token');
        if (empty($token)) {
            $token = $request->get_param('token');
        }

        if (empty($token)) {
            return new WP_Error(
                'missing_token',
                __('Authentication token is required.', 'easyrest-content-engine'),
                ['status' => 401]
            );
        }

        $stored_token = get_option('easyrest_ce_worker_token', '');

        if (empty($stored_token)) {
            return new WP_Error(
                'token_not_configured',
                __('Worker token is not configured.', 'easyrest-content-engine'),
                ['status' => 500]
            );
        }

        // Timing-safe comparison
        if (!hash_equals($stored_token, $token)) {
            // Log failed attempt
            self::log_failed_auth($request);

            return new WP_Error(
                'invalid_token',
                __('Invalid authentication token.', 'easyrest-content-engine'),
                ['status' => 403]
            );
        }

        // Check rate limit
        if (self::is_rate_limited()) {
            return new WP_Error(
                'rate_limited',
                __('Rate limit exceeded. Please wait before making another request.', 'easyrest-content-engine'),
                ['status' => 429]
            );
        }

        // Update rate limit
        self::update_rate_limit();

        return true;
    }

    /**
     * Check if rate limited
     *
     * @return bool
     */
    public static function is_rate_limited(): bool {
        $rate_limit_data = get_transient(self::$rate_limit_key);

        if (!$rate_limit_data) {
            return false;
        }

        $max_requests = absint(get_option('easyrest_ce_rate_limit_per_min', 10));

        return $rate_limit_data['count'] >= $max_requests;
    }

    /**
     * Update rate limit counter
     */
    private static function update_rate_limit(): void {
        $rate_limit_data = get_transient(self::$rate_limit_key);

        if (!$rate_limit_data) {
            $rate_limit_data = ['count' => 0, 'started_at' => time()];
        }

        $rate_limit_data['count']++;

        // Set transient for 1 minute
        set_transient(self::$rate_limit_key, $rate_limit_data, MINUTE_IN_SECONDS);
    }

    /**
     * Log failed authentication attempt
     *
     * @param WP_REST_Request $request
     */
    private static function log_failed_auth(WP_REST_Request $request): void {
        $ip = self::get_client_ip();

        error_log(sprintf(
            '[EasyRest CE] Failed auth attempt from IP: %s, Endpoint: %s',
            $ip,
            $request->get_route()
        ));
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Regenerate worker token
     *
     * @return string New token
     */
    public static function regenerate_token(): string {
        $new_token = bin2hex(random_bytes(16));
        update_option('easyrest_ce_worker_token', $new_token);
        return $new_token;
    }

    /**
     * Verify admin nonce for AJAX requests
     *
     * @param string $nonce
     * @return bool
     */
    public static function verify_admin_nonce(string $nonce): bool {
        return wp_verify_nonce($nonce, 'easyrest_ce_admin');
    }
}
