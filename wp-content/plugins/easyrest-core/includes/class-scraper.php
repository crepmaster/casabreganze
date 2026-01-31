<?php
/**
 * Booking.com Scraper Class
 *
 * Handles price retrieval via Node.js microservice HTTP API
 * with fallback to CLI and cURL
 *
 * @package EasyRest_Core
 * @since 1.0.0
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_Scraper
 */
class EasyRest_Scraper {

    /**
     * Hotel URL on Booking.com
     *
     * @var string
     */
    private $hotel_url;

    /**
     * Logger instance
     *
     * @var EasyRest_Logger|null
     */
    private $logger;

    /**
     * Microservice endpoint URL
     *
     * @var string
     */
    private $microservice_url;

    /**
     * Microservice authentication token
     *
     * @var string
     */
    private $microservice_token;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 45;

    /**
     * User agents for cURL rotation
     *
     * @var array
     */
    private $user_agents = array(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
    );

    /**
     * Constructor
     *
     * @param EasyRest_Logger|null $logger Logger instance
     */
    public function __construct($logger = null) {
        $this->hotel_url = $this->get_safe_hotel_url();
        $this->logger = $logger;
        $this->microservice_url = get_option('easyrest_microservice_url', 'http://localhost:3456');
        $this->microservice_token = get_option('easyrest_microservice_token', '');

        // Debug log to help diagnose configuration issues
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[EasyRest Scraper] Config: microservice_url=' . $this->microservice_url . ', token_set=' . (!empty($this->microservice_token) ? 'YES' : 'NO'));
        }
    }

    /**
     * Get validated hotel URL from options
     *
     * @return string
     */
    private function get_safe_hotel_url() {
        $default = 'https://www.booking.com/hotel/it/easy-rest-affitti-brevi-italia.fr.html';
        $url = get_option('easyrest_booking_url', $default);

        if (!$this->is_valid_booking_url($url)) {
            $this->log('warning', 'Invalid Booking URL in settings, using default', array('url' => $url));
            return $default;
        }

        return $url;
    }

    /**
     * Validate Booking.com URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    private function is_valid_booking_url($url) {
        $parsed = wp_parse_url($url);

        if (!$parsed || empty($parsed['host'])) {
            return false;
        }

        return in_array($parsed['host'], array('booking.com', 'www.booking.com'), true);
    }

    /**
     * Scrape price from Booking.com
     *
     * Priority: 1) Microservice HTTP 2) CLI script 3) cURL fallback
     *
     * @param string $checkin  Check-in date (Y-m-d)
     * @param string $checkout Check-out date (Y-m-d)
     * @param int    $adults   Number of adults
     * @param int    $children Number of children
     * @param string $currency Currency code
     * @return array Result with price or error
     */
    public function scrape_price($checkin, $checkout, $adults, $children, $currency = 'EUR') {
        $this->log('debug', 'Starting price scrape', array(
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
        ));

        // 1) Try microservice HTTP API (recommended for production)
        if (!empty($this->microservice_token)) {
            $result = $this->scrape_via_microservice($checkin, $checkout, $adults, $children, $currency);

            if ($result['success']) {
                $this->log('info', 'Price retrieved via microservice', array('price' => $result['price']));
                return $result;
            }

            $this->log('warning', 'Microservice failed', array('error' => $result['error'] ?? 'Unknown'));
        }

        // 2) Try CLI script (good for local/development)
        $scraper_path = get_option('easyrest_node_scraper_path', '');
        if (empty($scraper_path)) {
            $scraper_path = EASYREST_CORE_DIR . 'scraper-node/scraper.js';
        }

        if (file_exists($scraper_path)) {
            $result = $this->scrape_via_cli($checkin, $checkout, $adults, $children, $scraper_path);

            if ($result['success']) {
                $this->log('info', 'Price retrieved via CLI', array('price' => $result['price']));
                return $result;
            }

            $this->log('warning', 'CLI scraper failed', array('error' => $result['error'] ?? 'Unknown'));
        }

        // 3) Fallback to cURL (usually blocked by WAF)
        $this->log('debug', 'Trying cURL fallback');
        return $this->scrape_via_curl($checkin, $checkout, $adults, $children, $currency);
    }

    /**
     * Scrape price via Node.js microservice HTTP API
     *
     * @param string $checkin  Check-in date
     * @param string $checkout Check-out date
     * @param int    $adults   Number of adults
     * @param int    $children Number of children
     * @param string $currency Currency code
     * @return array Result
     */
    private function scrape_via_microservice($checkin, $checkout, $adults, $children, $currency) {
        $endpoint = trailingslashit($this->microservice_url) . 'booking/price';

        $payload = array(
            'url' => $this->hotel_url,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => intval($adults),
            'children' => intval($children),
            'currency' => $currency,
            'lang' => 'fr',
            'token' => $this->microservice_token,
        );

        $this->log('debug', 'Calling microservice', array(
            'endpoint' => $endpoint,
            'checkin' => $checkin,
            'checkout' => $checkout,
        ));

        $response = wp_remote_post($endpoint, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-EasyRest-Token' => $this->microservice_token,
            ),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            $this->log('error', 'Microservice request failed', array(
                'error' => $error_msg,
                'endpoint' => $endpoint,
            ));
            // Also log directly to debug.log for visibility
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[EasyRest Scraper] Microservice FAILED: ' . $error_msg . ' (endpoint: ' . $endpoint . ')');
            }
            return array(
                'success' => false,
                'error' => 'Microservice unavailable: ' . $error_msg,
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log('debug', 'Microservice response', array(
            'status' => $status_code,
            'body' => substr($body, 0, 500),
        ));

        if ($status_code !== 200 || empty($data)) {
            return array(
                'success' => false,
                'error' => $data['message'] ?? 'Microservice error (HTTP ' . $status_code . ')',
                'code' => $data['code'] ?? 'MICROSERVICE_ERROR',
            );
        }

        if (!empty($data['success']) && !empty($data['price'])) {
            $price = floatval($data['price']);
            // Log success for debugging
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[EasyRest Scraper] Microservice SUCCESS: price=' . $price . ' ' . ($data['currency'] ?? $currency));
            }
            return array(
                'success' => true,
                'price' => $price,
                'currency' => $data['currency'] ?? $currency,
                'nights' => $data['nights'] ?? null,
                'source' => 'microservice',
            );
        }

        return array(
            'success' => false,
            'error' => $data['message'] ?? 'Price not found',
            'code' => $data['code'] ?? 'PRICE_NOT_FOUND',
        );
    }

    /**
     * Scrape price via CLI Node.js script
     *
     * @param string $checkin     Check-in date
     * @param string $checkout    Check-out date
     * @param int    $adults      Number of adults
     * @param int    $children    Number of children
     * @param string $scraper_path Path to scraper.js
     * @return array Result
     */
    private function scrape_via_cli($checkin, $checkout, $adults, $children, $scraper_path) {
        $node_path = $this->find_node_executable();
        if (!$node_path) {
            $this->log('error', 'Node.js executable not found');
            return array('success' => false, 'error' => 'Node.js not found on server');
        }

        $command = sprintf(
            '%s %s --url=%s --checkin=%s --checkout=%s --adults=%d --children=%d 2>&1',
            escapeshellarg($node_path),
            escapeshellarg($scraper_path),
            escapeshellarg($this->hotel_url),
            escapeshellarg($checkin),
            escapeshellarg($checkout),
            intval($adults),
            intval($children)
        );

        $this->log('debug', 'Executing CLI scraper', array('command' => $command));

        $output = shell_exec($command);

        if (empty($output)) {
            $this->log('error', 'CLI scraper returned empty output');
            return array('success' => false, 'error' => 'Scraper returned empty response');
        }

        $this->log('debug', 'CLI scraper output', array('output' => $output));

        $result = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('error', 'Failed to parse CLI output', array('output' => $output));
            return array('success' => false, 'error' => 'Invalid scraper response');
        }

        if (!empty($result['success']) && !empty($result['price'])) {
            return array(
                'success' => true,
                'price' => floatval($result['price']),
                'currency' => $result['currency'] ?? 'EUR',
                'source' => 'cli',
            );
        }

        return array(
            'success' => false,
            'error' => $result['error'] ?? 'Price not found',
        );
    }

    /**
     * Scrape price via cURL (fallback, usually blocked by WAF)
     *
     * @param string $checkin  Check-in date
     * @param string $checkout Check-out date
     * @param int    $adults   Number of adults
     * @param int    $children Number of children
     * @param string $currency Currency code
     * @return array Result
     */
    private function scrape_via_curl($checkin, $checkout, $adults, $children, $currency = 'EUR') {
        $url = $this->build_url($checkin, $checkout, $adults, $children, $currency);
        $html = $this->fetch_page($url);

        if (!$html) {
            $this->log('error', 'Failed to fetch Booking.com page');
            return array(
                'success' => false,
                'error' => 'Unable to connect to Booking.com',
                'code' => 'scrape_failed',
            );
        }

        if ($this->is_blocked($html)) {
            $this->log('warning', 'Request blocked by Booking.com WAF');
            return array(
                'success' => false,
                'error' => 'Request blocked. Please try again later.',
                'code' => 'blocked',
            );
        }

        $price = $this->extract_price($html);

        if (!$price || $price <= 0) {
            $this->log('warning', 'Price not found in page');
            return array(
                'success' => false,
                'error' => 'Price not available for these dates',
                'code' => 'price_not_found',
            );
        }

        $this->log('info', 'Price extracted via cURL', array('price' => $price));

        return array(
            'success' => true,
            'price' => $price,
            'currency' => $currency,
            'source' => 'curl',
        );
    }

    /**
     * Build Booking.com URL with parameters
     */
    private function build_url($checkin, $checkout, $adults, $children, $currency) {
        $base_url = strtok($this->hotel_url, '?');

        $params = array(
            'checkin' => $checkin,
            'checkout' => $checkout,
            'group_adults' => $adults,
            'group_children' => $children,
            'no_rooms' => 1,
            'selected_currency' => $currency,
            'lang' => 'fr',
        );

        return $base_url . '?' . http_build_query($params);
    }

    /**
     * Fetch page via cURL
     */
    private function fetch_page($url) {
        $max_retries = 2;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $html = $this->curl_request($url);

            if ($html && strlen($html) > 50000) {
                if (strpos($html, 'b_price') !== false || strpos($html, 'data-price') !== false) {
                    return $html;
                }
            }

            if ($attempt < $max_retries) {
                usleep(500000);
            }
        }

        return null;
    }

    /**
     * Execute cURL request
     */
    private function curl_request($url) {
        $ch = curl_init();

        $user_agent = $this->user_agents[array_rand($this->user_agents)];

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => $user_agent,
            CURLOPT_ENCODING => 'gzip, deflate',
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            return null;
        }

        return $response;
    }

    /**
     * Check if response indicates blocking
     */
    private function is_blocked($html) {
        $indicators = array('AWS WAF', 'Access Denied', 'captcha', 'unusual traffic');

        foreach ($indicators as $indicator) {
            if (stripos($html, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract price from HTML
     */
    private function extract_price($html) {
        // Try data attributes
        if (preg_match('/data-price="([\d.]+)"/i', $html, $match)) {
            return floatval($match[1]);
        }

        if (preg_match('/data-price-amount="([\d.]+)"/i', $html, $match)) {
            return floatval($match[1]);
        }

        // Try JSON
        if (preg_match('/"gross_amount":\s*([\d.]+)/i', $html, $match)) {
            return floatval($match[1]);
        }

        return null;
    }

    /**
     * Find Node.js executable path
     */
    private function find_node_executable() {
        $custom_node = get_option('easyrest_node_path', '');
        if (!empty($custom_node) && file_exists($custom_node)) {
            return $custom_node;
        }

        $possible_paths = array(
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            '/usr/bin/node',
            '/usr/local/bin/node',
        );

        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $result = trim(shell_exec('where node 2>nul') ?? '');
        } else {
            $result = trim(shell_exec('which node 2>/dev/null') ?? '');
        }

        if (!empty($result) && file_exists($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Log message
     */
    private function log($level, $message, $context = array()) {
        if ($this->logger) {
            $this->logger->$level('[Scraper] ' . $message, $context);
        }
    }
}
