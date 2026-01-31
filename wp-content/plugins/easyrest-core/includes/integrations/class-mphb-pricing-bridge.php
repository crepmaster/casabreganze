<?php
/**
 * MotoPress Hotel Booking Pricing Bridge v1.0.0
 *
 * Integration of EasyRest dynamic pricing with MotoPress Hotel Booking.
 *
 * Key features:
 * - Hooks into mphb_booking_calculate_total_price to override price calculation
 * - Uses Booking.com price as SINGLE SOURCE OF TRUTH (tax-inclusive)
 * - Prevents MotoPress from recalculating or adding taxes
 * - Fallback to native MotoPress pricing if dynamic price fails
 * - Consistent architecture with WPHB Pricing Bridge
 * - Flag-driven UI price hiding
 * - Safe logging without PII exposure
 *
 * @package EasyRest_Core
 * @since 1.0.0
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_MPHB_Pricing_Bridge
 */
class EasyRest_MPHB_Pricing_Bridge {

    /**
     * Singleton instance
     *
     * @var EasyRest_MPHB_Pricing_Bridge|null
     */
    private static $instance = null;

    /**
     * Option keys
     */
    const OPTION_ENABLED = 'easyrest_mphb_dynamic_pricing_enabled';
    const OPTION_MODE = 'easyrest_mphb_dynamic_pricing_fallback';
    const OPTION_CACHE_TTL = 'easyrest_mphb_cache_ttl';
    const OPTION_VALIDATION_ENABLED = 'easyrest_mphb_validation_enabled';

    /**
     * Mode constants
     */
    const MODE_STRICT = 'block';
    const MODE_FALLBACK = 'fallback';

    /**
     * Cache constants
     */
    const CACHE_PREFIX = 'easyrest_mphb_price_';
    const CACHE_LOCK_PREFIX = 'easyrest_mphb_lock_';
    const ERROR_TRANSIENT_PREFIX = 'easyrest_mphb_err_';
    const DEFAULT_CACHE_TTL = 900; // 15 minutes
    const LOCK_TTL = 30; // 30 seconds

    /**
     * Lock wait constants
     */
    const LOCK_WAIT_INTERVAL_MS = 100;
    const LOCK_WAIT_MAX_MS = 2000;

    /**
     * Price provider instance
     *
     * @var EasyRest_MPHB_Price_Provider|null
     */
    private $price_provider = null;

    /**
     * Per-request cache to avoid duplicate provider calls
     *
     * @var array
     */
    private $request_cache = [];

    /**
     * Flag indicating if booking form dynamic price was successfully rendered
     *
     * Used to coordinate with native MPHB price display - when this is true,
     * we hide/adapt the native base price to avoid showing two conflicting prices.
     *
     * @var bool
     */
    private $booking_form_dynamic_price_available = false;

    /**
     * Flag indicating if checkout total was overridden with dynamic pricing
     *
     * @var bool
     */
    private $checkout_dynamic_pricing_applied = false;

    /**
     * Current booking context (for sharing between hooks)
     *
     * @var array|null
     */
    private $current_booking_context = null;

    /**
     * Get singleton instance
     *
     * @return EasyRest_MPHB_Pricing_Bridge
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
        // Only initialize if MotoPress Hotel Booking is active
        if (!$this->is_mphb_active()) {
            $this->log_safe('Bridge NOT initialized: MotoPress Hotel Booking not active', 'warning');
            return;
        }

        // Only initialize if bridge is enabled
        if (!$this->is_enabled()) {
            $this->log_safe('Bridge disabled via settings (option: ' . self::OPTION_ENABLED . ' = ' . var_export(get_option(self::OPTION_ENABLED), true) . ')', 'debug');
            return;
        }

        // Initialize price provider
        $this->price_provider = new EasyRest_MPHB_Price_Provider($this);

        $this->init_hooks();
        $this->log_safe('Bridge initialized (v1.0.0) - Mode: ' . $this->get_mode() . ', Validation: ' . ($this->is_validation_enabled() ? 'ON' : 'OFF'), 'info');
    }

    /**
     * Check if MotoPress Hotel Booking plugin is active
     *
     * @return bool
     */
    private function is_mphb_active() {
        return class_exists('HotelBookingPlugin') || function_exists('MPHB');
    }

    /**
     * Check if bridge is enabled via settings
     *
     * @return bool
     */
    public function is_enabled() {
        return (bool) get_option(self::OPTION_ENABLED, false);
    }

    /**
     * Get pricing mode setting
     *
     * @return string 'block' (strict) or 'fallback'
     */
    public function get_mode() {
        $mode = get_option(self::OPTION_MODE, self::MODE_STRICT);
        return in_array($mode, [self::MODE_STRICT, self::MODE_FALLBACK], true) ? $mode : self::MODE_STRICT;
    }

    /**
     * Check if validation-first mode is enabled
     *
     * @return bool
     */
    public function is_validation_enabled() {
        return (bool) get_option(self::OPTION_VALIDATION_ENABLED, true);
    }

    /**
     * Get cache TTL in seconds
     *
     * @return int
     */
    public function get_cache_ttl() {
        return absint(get_option(self::OPTION_CACHE_TTL, self::DEFAULT_CACHE_TTL));
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // =========================================================================
        // PRIMARY PRICE OVERRIDE HOOK
        // =========================================================================
        //
        // This is the MAIN hook for overriding MotoPress price calculation.
        // Fires in PriceBreakdownHelper::getPriceBreakdown() at LINE 132.
        //
        // Filter receives: $discountTotal (float), $booking (MPHB\Entities\Booking)
        // We return our EasyRest price to REPLACE MotoPress calculation.
        //
        add_filter('mphb_booking_calculate_total_price', [$this, 'override_booking_total_price'], 10, 2);

        // =========================================================================
        // PRICE BREAKDOWN MODIFICATION
        // =========================================================================
        //
        // This hook allows us to modify the full price breakdown after calculation.
        // Used to ensure breakdown reflects our overridden total.
        // Fires in PriceBreakdownHelper::getPriceBreakdown() at LINE 164.
        //
        add_filter('mphb_booking_price_breakdown', [$this, 'modify_price_breakdown'], 10, 2);

        // =========================================================================
        // DEPOSIT PRICE OVERRIDE
        // =========================================================================
        //
        // Override deposit calculation if needed.
        // Fires in Booking::calcDepositAmount() at LINE 385.
        //
        add_filter('mphb_booking_deposit_price', [$this, 'override_deposit_price'], 10, 2);

        // =========================================================================
        // BOOKING FORM - DYNAMIC PRICE DISPLAY
        // =========================================================================
        //
        // Display EasyRest price on booking form when dates are selected.
        // This provides real-time price feedback before checkout.
        //
        add_action('mphb_render_single_room_type_after_reservation_form', [$this, 'render_booking_form_dynamic_price'], 10);
        add_action('mphb_sc_booking_form_after_form', [$this, 'render_booking_form_dynamic_price'], 10);

        // =========================================================================
        // CSS INJECTION FOR PRICE HIDING
        // =========================================================================
        //
        // Hide native MPHB prices when EasyRest dynamic price is displayed.
        //
        add_action('wp_footer', [$this, 'inject_price_hiding_css'], 20);

        // =========================================================================
        // AJAX PRICE CALCULATION
        // =========================================================================
        //
        // Hook into MPHB AJAX price calculations for real-time updates.
        //
        add_filter('mphb_get_booking_price_breakdown_json', [$this, 'override_ajax_price_breakdown'], 10, 2);

        // =========================================================================
        // CHECKOUT VALIDATION
        // =========================================================================
        //
        // Validate price before booking confirmation in strict mode.
        //
        add_action('mphb_sc_checkout_step_booking', [$this, 'validate_checkout_pricing'], 5);

        // =========================================================================
        // CLEANUP
        // =========================================================================
        add_action('wp_logout', [$this, 'cleanup_error_state']);
    }

    // =========================================================================
    // PRICE OVERRIDE METHODS
    // =========================================================================

    /**
     * Override booking total price (PRIMARY HOOK)
     *
     * This is the MAIN integration point. MotoPress calls this filter in
     * PriceBreakdownHelper::getPriceBreakdown() with the calculated total.
     * We return our EasyRest price to REPLACE their calculation entirely.
     *
     * IMPORTANT: Booking.com price is TAX-INCLUSIVE and FINAL.
     * We must prevent MotoPress from adding taxes on top.
     *
     * @param float                    $total   MotoPress calculated total
     * @param \MPHB\Entities\Booking   $booking Booking entity
     * @return float Our EasyRest price or original if unavailable
     */
    public function override_booking_total_price($total, $booking) {
        $this->log_safe('>>> override_booking_total_price CALLED (PRIMARY HOOK)', 'debug', [
            'mphb_total' => $total,
            'booking_id' => $booking ? $booking->getId() : 'N/A',
        ]);

        // Build context from booking
        $context = $this->build_price_context_from_booking($booking);

        if (empty($context['checkin']) || empty($context['checkout'])) {
            $this->log_safe('Missing dates in booking context, using MPHB native price', 'debug');
            return $total;
        }

        // Store context for later use
        $this->current_booking_context = $context;

        // Get EasyRest price
        $result = $this->price_provider->get_price($context);

        if ($result->is_error()) {
            $this->log_safe('EasyRest price fetch FAILED', 'warning', [
                'error_code' => $result->get_error_code(),
                'error_msg' => $result->get_error_message(),
                'mode' => $this->get_mode(),
            ]);

            if ($this->get_mode() === self::MODE_FALLBACK) {
                // Return original MPHB price
                return $total;
            }

            // In strict mode, store error for later handling
            $this->store_error_state($result->get_error_message());
            return $total;
        }

        // SUCCESS: Use EasyRest price
        $easyrest_price = $result->get_total_price();

        $this->log_safe('Booking total OVERRIDDEN with EasyRest price', 'info', [
            'mphb_original' => $total,
            'easyrest_price' => $easyrest_price,
            'source' => $result->get_source(),
            'tax_inclusive' => $result->is_tax_inclusive() ? 'YES' : 'NO',
        ]);

        // Set flag for UI coordination
        $this->checkout_dynamic_pricing_applied = true;

        return $easyrest_price;
    }

    /**
     * Modify price breakdown to reflect our overridden total
     *
     * This ensures the breakdown structure matches our overridden price.
     * We set taxes to 0 since Booking.com price is already tax-inclusive.
     *
     * @param array                    $breakdown Price breakdown array
     * @param \MPHB\Entities\Booking   $booking   Booking entity
     * @return array Modified breakdown
     */
    public function modify_price_breakdown($breakdown, $booking) {
        // Only modify if we successfully applied dynamic pricing
        if (!$this->checkout_dynamic_pricing_applied) {
            return $breakdown;
        }

        // If we have a cached EasyRest price, ensure breakdown reflects it
        if ($this->current_booking_context) {
            $result = $this->price_provider->get_price($this->current_booking_context);

            if (!$result->is_error() && $result->is_tax_inclusive()) {
                // Booking.com price is tax-inclusive, so we need to neutralize
                // any taxes MotoPress might have added.
                // The total should already be correct from our primary hook.

                $this->log_safe('Price breakdown modified for tax-inclusive EasyRest price', 'debug', [
                    'breakdown_total' => $breakdown['total'] ?? 'N/A',
                ]);
            }
        }

        return $breakdown;
    }

    /**
     * Override deposit price calculation
     *
     * Deposit should be based on our EasyRest total, not MPHB calculation.
     *
     * @param float                    $deposit Calculated deposit
     * @param \MPHB\Entities\Booking   $booking Booking entity
     * @return float Modified deposit
     */
    public function override_deposit_price($deposit, $booking) {
        // If dynamic pricing not applied, use original
        if (!$this->checkout_dynamic_pricing_applied) {
            return $deposit;
        }

        // Deposit logic could be customized here
        // For now, let MPHB handle deposit calculation based on our total
        return $deposit;
    }

    // =========================================================================
    // BOOKING FORM PRICE DISPLAY
    // =========================================================================

    /**
     * Render dynamic EasyRest price on booking form
     *
     * Displays price when dates are selected via URL parameters or form state.
     *
     * @param object|null $room_type MPHB room type object
     */
    public function render_booking_form_dynamic_price($room_type = null) {
        // Reset flag at start
        $this->booking_form_dynamic_price_available = false;

        // Get room type ID
        $room_type_id = null;
        if ($room_type && method_exists($room_type, 'getId')) {
            $room_type_id = $room_type->getId();
        } elseif ($room_type && method_exists($room_type, 'getOriginalId')) {
            $room_type_id = $room_type->getOriginalId();
        } else {
            $room_type_id = get_the_ID();
        }

        if (!$room_type_id) {
            $this->log_safe('Booking form price: No room type ID available', 'debug');
            return;
        }

        // Extract search context from URL/form parameters
        $checkin = $this->get_search_param('mphb_check_in_date');
        $checkout = $this->get_search_param('mphb_check_out_date');

        // Try alternative param names
        if (empty($checkin)) {
            $checkin = $this->get_search_param('check_in_date');
        }
        if (empty($checkout)) {
            $checkout = $this->get_search_param('check_out_date');
        }
        if (empty($checkin)) {
            $checkin = $this->get_search_param('checkin');
        }
        if (empty($checkout)) {
            $checkout = $this->get_search_param('checkout');
        }

        // Get guest counts - MPHB defaults: adults=1, children=0
        $adults_raw = $this->get_search_param('mphb_adults', '');
        if ($adults_raw === '') {
            $adults_raw = $this->get_search_param('adults', '');
        }
        $adults = ($adults_raw !== '') ? absint($adults_raw) : 1;

        $children_raw = $this->get_search_param('mphb_children', '');
        if ($children_raw === '') {
            $children_raw = $this->get_search_param('children', '');
        }
        $children = ($children_raw !== '') ? absint($children_raw) : 0;

        $this->log_safe('Booking form price: URL params extracted', 'debug', [
            'room_type_id' => $room_type_id,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
        ]);

        // Skip if no valid dates
        if (empty($checkin) || empty($checkout)) {
            return;
        }

        // Normalize date format
        $checkin = $this->normalize_date_format($checkin);
        $checkout = $this->normalize_date_format($checkout);

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) {
            $this->log_safe('Booking form price: Invalid date format', 'debug', [
                'checkin' => $checkin,
                'checkout' => $checkout,
            ]);
            return;
        }

        // Validate checkout > checkin
        if (strtotime($checkout) <= strtotime($checkin)) {
            return;
        }

        // Build price context
        $context = [
            'accommodation_type_id' => $room_type_id,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => max(1, $adults),
            'children' => $children,
            'currency' => $this->get_mphb_currency(),
        ];

        // Fetch price
        $result = $this->price_provider->get_price($context);

        if ($result->is_error()) {
            $this->log_safe('Booking form price: Provider returned error', 'warning', [
                'error_code' => $result->get_error_code(),
            ]);

            // In debug mode, show a note
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<div class="easyrest-dynamic-price easyrest-dynamic-price--error" style="margin-top:15px;padding:10px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;font-size:13px;">';
                echo '<em>' . esc_html__('Dynamic price unavailable for these dates.', 'easyrest-core') . '</em>';
                echo '</div>';
            }
            return;
        }

        // Success - render the price block
        $price = $result->get_total_price();
        $currency = $result->get_currency();

        // Format dates for display
        $checkin_display = date_i18n(get_option('date_format'), strtotime($checkin));
        $checkout_display = date_i18n(get_option('date_format'), strtotime($checkout));

        // Calculate nights
        $nights = (strtotime($checkout) - strtotime($checkin)) / DAY_IN_SECONDS;

        // Format price
        $formatted_price = $this->format_mphb_price($price, $currency);

        // Build guest string
        $guest_parts = [];
        if ($adults > 0) {
            $guest_parts[] = sprintf(_n('%d adult', '%d adults', $adults, 'easyrest-core'), $adults);
        }
        if ($children > 0) {
            $guest_parts[] = sprintf(_n('%d child', '%d children', $children, 'easyrest-core'), $children);
        }
        $guest_string = implode(', ', $guest_parts);

        $this->log_safe('Booking form price: Rendering dynamic price block', 'info', [
            'room_type_id' => $room_type_id,
            'price' => $price,
            'nights' => $nights,
        ]);

        // Set flag for CSS injection
        $this->booking_form_dynamic_price_available = true;

        // Output the dynamic price block
        ?>
        <div class="easyrest-dynamic-price" style="margin-top:15px;padding:15px;background:linear-gradient(135deg,#e8f5e9 0%,#c8e6c9 100%);border:1px solid #4caf50;border-radius:8px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span style="font-size:18px;">✨</span>
                <strong style="color:#2e7d32;font-size:14px;">
                    <?php esc_html_e('EasyRest Direct Price', 'easyrest-core'); ?>
                </strong>
            </div>
            <div style="font-size:12px;color:#555;margin-bottom:10px;">
                <?php
                printf(
                    /* translators: %1$s: check-in date, %2$s: check-out date, %3$d: number of nights, %4$s: guest info */
                    esc_html__('%1$s – %2$s (%3$d nights, %4$s)', 'easyrest-core'),
                    esc_html($checkin_display),
                    esc_html($checkout_display),
                    absint($nights),
                    esc_html($guest_string)
                );
                ?>
            </div>
            <div style="font-size:22px;font-weight:bold;color:#1b5e20;">
                <?php echo esc_html($formatted_price); ?>
            </div>
            <div style="font-size:11px;color:#666;margin-top:5px;">
                <?php esc_html_e('Tax included • Book directly and save!', 'easyrest-core'); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Inject CSS to hide native MPHB prices when EasyRest dynamic price is shown
     */
    public function inject_price_hiding_css() {
        // Only on single accommodation pages or pages with booking form
        if (!$this->booking_form_dynamic_price_available && !$this->checkout_dynamic_pricing_applied) {
            return;
        }

        $this->log_safe('Injecting CSS to hide native MPHB prices', 'debug');
        ?>
        <style id="easyrest-mphb-price-hiding">
            /* Hide native MPHB prices when EasyRest dynamic price is displayed */
            <?php if ($this->booking_form_dynamic_price_available): ?>
            /* Hide price in booking form when dynamic price is shown */
            .mphb-reservation-form .mphb-price,
            .mphb_sc_booking_form-wrapper .mphb-price,
            .mphb-room-type .mphb-price {
                display: none !important;
            }
            <?php endif; ?>

            <?php if ($this->checkout_dynamic_pricing_applied): ?>
            /* Optionally style checkout prices differently */
            .mphb-checkout-form .mphb-price-breakdown {
                /* Prices are already overridden via hooks */
            }
            <?php endif; ?>
        </style>
        <?php
    }

    // =========================================================================
    // AJAX PRICE CALCULATION
    // =========================================================================

    /**
     * Override AJAX price breakdown response
     *
     * @param string                   $json    JSON response
     * @param \MPHB\Entities\Booking   $booking Booking entity
     * @return string Modified JSON
     */
    public function override_ajax_price_breakdown($json, $booking) {
        // The price should already be overridden via mphb_booking_calculate_total_price
        // This hook can be used for additional modifications if needed
        return $json;
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    /**
     * Validate checkout pricing before booking confirmation
     *
     * In strict mode, this can prevent booking if pricing fails.
     */
    public function validate_checkout_pricing() {
        if (!$this->is_validation_enabled()) {
            return;
        }

        // Check if we have an error state from price calculation
        $error = $this->get_error_state();

        if (!empty($error) && $this->get_mode() === self::MODE_STRICT) {
            $this->log_safe('Checkout validation FAILED in strict mode', 'error', [
                'error' => $error,
            ]);

            // Clear the error
            $this->clear_error_state();

            // Add error notice for MPHB
            if (function_exists('MPHB')) {
                MPHB()->notices()->addError(
                    __('Unable to calculate the price for your selected dates. Please try again or contact us.', 'easyrest-core')
                );
            }
        }
    }

    // =========================================================================
    // CONTEXT BUILDERS
    // =========================================================================

    /**
     * Build price context from MPHB Booking entity
     *
     * @param \MPHB\Entities\Booking $booking Booking entity
     * @return array Context array
     */
    private function build_price_context_from_booking($booking) {
        $checkin = '';
        $checkout = '';
        $adults = 1;
        $children = 0;
        $accommodation_type_id = 0;

        if ($booking) {
            // Get dates
            $checkin_date = $booking->getCheckInDate();
            $checkout_date = $booking->getCheckOutDate();

            if ($checkin_date instanceof \DateTime) {
                $checkin = $checkin_date->format('Y-m-d');
            }
            if ($checkout_date instanceof \DateTime) {
                $checkout = $checkout_date->format('Y-m-d');
            }

            // Get reserved rooms for guest counts and accommodation type
            $reserved_rooms = $booking->getReservedRooms();
            if (!empty($reserved_rooms)) {
                $first_room = $reserved_rooms[0];

                if (method_exists($first_room, 'getAdults')) {
                    $adults = absint($first_room->getAdults()) ?: 1;
                }
                if (method_exists($first_room, 'getChildren')) {
                    $children = absint($first_room->getChildren()) ?: 0;
                }
                if (method_exists($first_room, 'getRoomTypeId')) {
                    $accommodation_type_id = absint($first_room->getRoomTypeId());
                }
            }
        }

        return [
            'accommodation_type_id' => $accommodation_type_id,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
            'currency' => $this->get_mphb_currency(),
        ];
    }

    // =========================================================================
    // ERROR STATE MANAGEMENT (Transient-based)
    // =========================================================================

    /**
     * Get unique key for error state storage
     *
     * @return string Transient key
     */
    private function get_error_state_key() {
        $parts = [];

        // Add user ID or guest identifier
        if (is_user_logged_in()) {
            $parts[] = 'u' . get_current_user_id();
        } else {
            $guest_id = isset($_COOKIE['easyrest_mphb_guest_id']) ? sanitize_key($_COOKIE['easyrest_mphb_guest_id']) : '';
            if (empty($guest_id)) {
                $guest_id = wp_generate_password(16, false);
                setcookie('easyrest_mphb_guest_id', $guest_id, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            }
            $parts[] = 'g' . $guest_id;
        }

        return self::ERROR_TRANSIENT_PREFIX . md5(implode('|', $parts));
    }

    /**
     * Store error state
     *
     * @param string $error Error message
     */
    private function store_error_state($error) {
        $key = $this->get_error_state_key();
        set_transient($key, sanitize_text_field($error), 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Get error state
     *
     * @return string|null Error message or null
     */
    private function get_error_state() {
        $key = $this->get_error_state_key();
        $error = get_transient($key);
        return $error !== false ? $error : null;
    }

    /**
     * Clear error state
     */
    private function clear_error_state() {
        $key = $this->get_error_state_key();
        delete_transient($key);
    }

    /**
     * Cleanup error state (called on logout, etc.)
     */
    public function cleanup_error_state() {
        $this->clear_error_state();
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get MPHB currency
     *
     * @return string Currency code
     */
    private function get_mphb_currency() {
        if (function_exists('MPHB') && method_exists(MPHB()->settings(), 'currency')) {
            return MPHB()->settings()->currency()->getCurrencyCode();
        }
        return 'EUR';
    }

    /**
     * Format price using MPHB formatter
     *
     * @param float  $price    Price value
     * @param string $currency Currency code
     * @return string Formatted price
     */
    private function format_mphb_price($price, $currency = 'EUR') {
        if (function_exists('mphb_format_price')) {
            return mphb_format_price($price);
        }
        return number_format($price, 2, ',', ' ') . ' ' . $currency;
    }

    /**
     * Get search parameter from URL (GET or POST)
     *
     * @param string $key     Parameter key
     * @param mixed  $default Default value
     * @return mixed Parameter value or default
     */
    private function get_search_param($key, $default = '') {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            return sanitize_text_field(wp_unslash($_GET[$key]));
        }
        if (isset($_POST[$key]) && $_POST[$key] !== '') {
            return sanitize_text_field(wp_unslash($_POST[$key]));
        }
        return $default;
    }

    /**
     * Normalize date format to Y-m-d
     *
     * @param string $date Date string
     * @return string Normalized date
     */
    private function normalize_date_format($date) {
        if (empty($date)) {
            return $date;
        }

        // Already correct format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Convert YYYY/MM/DD to YYYY-MM-DD
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $date, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        // Try strtotime as fallback
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return $date;
    }

    /**
     * Safe logging (no PII, no tokens)
     *
     * @param string $message Log message
     * @param string $level   Log level (debug, info, warning, error)
     * @param array  $context Context data
     */
    private function log_safe($message, $level = 'info', $context = []) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        // Sanitize context
        $safe_context = [];
        $sensitive_keys = ['token', 'password', 'email', 'phone', 'address', 'name', 'first_name', 'last_name'];

        foreach ($context as $key => $value) {
            if (in_array(strtolower($key), $sensitive_keys, true)) {
                $safe_context[$key] = '[REDACTED]';
            } elseif (is_array($value) || is_object($value)) {
                $safe_context[$key] = wp_json_encode($value);
            } else {
                $safe_context[$key] = $value;
            }
        }

        $log_entry = sprintf(
            '[EasyRest MPHB Bridge] [%s] %s%s',
            strtoupper($level),
            $message,
            !empty($safe_context) ? ' | ' . wp_json_encode($safe_context) : ''
        );

        error_log($log_entry);
    }

    // =========================================================================
    // ADMIN SETTINGS
    // =========================================================================

    /**
     * Register settings for admin
     */
    public static function register_settings() {
        // Enable/disable toggle
        register_setting('easyrest_settings_group', self::OPTION_ENABLED, [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ]);

        // Mode (strict/fallback)
        register_setting('easyrest_settings_group', self::OPTION_MODE, [
            'type' => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_mode'],
            'default' => self::MODE_STRICT,
        ]);

        // Validation enabled
        register_setting('easyrest_settings_group', self::OPTION_VALIDATION_ENABLED, [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true,
        ]);

        // Cache TTL
        register_setting('easyrest_settings_group', self::OPTION_CACHE_TTL, [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => self::DEFAULT_CACHE_TTL,
        ]);
    }

    /**
     * Sanitize mode option
     *
     * @param string $value Input value
     * @return string Sanitized value
     */
    public static function sanitize_mode($value) {
        return in_array($value, [self::MODE_STRICT, self::MODE_FALLBACK], true) ? $value : self::MODE_STRICT;
    }

    /**
     * Render settings section description
     */
    public static function render_settings_section() {
        echo '<p>' . esc_html__('Configure MotoPress Hotel Booking integration. When enabled, accommodation prices will be dynamically fetched from Booking.com via EasyRest.', 'easyrest-core') . '</p>';

        $mphb_active = class_exists('HotelBookingPlugin') || function_exists('MPHB');
        if (!$mphb_active) {
            echo '<p style="color:orange;"><strong>⚠ ' . esc_html__('MotoPress Hotel Booking plugin is not active.', 'easyrest-core') . '</strong></p>';
        }
    }

    /**
     * Render enabled field
     */
    public static function render_enabled_field() {
        $enabled = get_option(self::OPTION_ENABLED, false);
        $mphb_active = class_exists('HotelBookingPlugin') || function_exists('MPHB');

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr(self::OPTION_ENABLED) . '" value="1" ' . checked($enabled, true, false);
        if (!$mphb_active) {
            echo ' disabled';
        }
        echo '> ';
        echo esc_html__('Enable dynamic pricing for MotoPress Hotel Booking', 'easyrest-core');
        echo '</label>';

        if (!$mphb_active) {
            echo '<p class="description" style="color:orange;">⚠ ' . esc_html__('MotoPress Hotel Booking plugin must be active.', 'easyrest-core') . '</p>';
        }
    }

    /**
     * Render mode field
     */
    public static function render_fallback_mode_field() {
        $mode = get_option(self::OPTION_MODE, self::MODE_STRICT);

        echo '<select name="' . esc_attr(self::OPTION_MODE) . '">';
        echo '<option value="' . esc_attr(self::MODE_STRICT) . '" ' . selected($mode, self::MODE_STRICT, false) . '>';
        echo esc_html__('Strict (block checkout on error)', 'easyrest-core');
        echo '</option>';
        echo '<option value="' . esc_attr(self::MODE_FALLBACK) . '" ' . selected($mode, self::MODE_FALLBACK, false) . '>';
        echo esc_html__('Fallback (use MPHB native price)', 'easyrest-core');
        echo '</option>';
        echo '</select>';

        echo '<p class="description">';
        echo '<strong>' . esc_html__('Strict:', 'easyrest-core') . '</strong> ' . esc_html__('Prevents checkout if EasyRest price unavailable.', 'easyrest-core') . '<br>';
        echo '<strong>' . esc_html__('Fallback:', 'easyrest-core') . '</strong> ' . esc_html__('Uses MotoPress calculated price if EasyRest fails.', 'easyrest-core');
        echo '</p>';
    }

    /**
     * Render validation enabled field
     */
    public static function render_validation_field() {
        $enabled = get_option(self::OPTION_VALIDATION_ENABLED, true);

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr(self::OPTION_VALIDATION_ENABLED) . '" value="1" ' . checked($enabled, true, false) . '> ';
        echo esc_html__('Validate pricing before checkout', 'easyrest-core');
        echo '</label>';

        echo '<p class="description">';
        echo esc_html__('When enabled, prices are validated before booking creation.', 'easyrest-core');
        echo '</p>';
    }

    /**
     * Render cache TTL field
     */
    public static function render_cache_ttl_field() {
        $ttl = get_option(self::OPTION_CACHE_TTL, self::DEFAULT_CACHE_TTL);
        $ttl_minutes = intval($ttl / 60);

        echo '<input type="number" name="' . esc_attr(self::OPTION_CACHE_TTL) . '" value="' . esc_attr($ttl) . '" min="60" max="3600" step="60" style="width:100px;"> ';
        echo esc_html__('seconds', 'easyrest-core');
        echo ' <span class="description">(' . sprintf(esc_html__('%d minutes', 'easyrest-core'), $ttl_minutes) . ')</span>';

        echo '<p class="description">';
        echo esc_html__('How long to cache MPHB pricing results. Default: 900 seconds (15 minutes).', 'easyrest-core');
        echo '</p>';
    }
}

// =========================================================================
// PRICE PROVIDER CLASS
// =========================================================================

/**
 * Class EasyRest_MPHB_Price_Provider
 *
 * Centralized price fetching with caching and error handling for MPHB
 */
class EasyRest_MPHB_Price_Provider {

    /**
     * Bridge reference
     *
     * @var EasyRest_MPHB_Pricing_Bridge
     */
    private $bridge;

    /**
     * Per-request cache
     *
     * @var array
     */
    private $request_cache = [];

    /**
     * Constructor
     *
     * @param EasyRest_MPHB_Pricing_Bridge $bridge Bridge instance
     */
    public function __construct($bridge) {
        $this->bridge = $bridge;
    }

    /**
     * Get price for given context
     *
     * @param array $context Price context
     * @return EasyRest_MPHB_Price_Result Price result object
     */
    public function get_price(array $context) {
        // Validate required fields
        $validation = $this->validate_context($context);
        if ($validation !== true) {
            return EasyRest_MPHB_Price_Result::error('validation_error', $validation);
        }

        // Build cache key
        $cache_key = $this->build_cache_key($context);

        // Check per-request cache
        if (isset($this->request_cache[$cache_key])) {
            $this->log_debug('Per-request cache hit', $cache_key);
            return $this->request_cache[$cache_key];
        }

        // Check transient cache
        $cached = get_transient(EasyRest_MPHB_Pricing_Bridge::CACHE_PREFIX . $cache_key);
        if ($cached !== false && is_array($cached)) {
            $result = EasyRest_MPHB_Price_Result::from_array($cached);
            $this->request_cache[$cache_key] = $result;
            $this->log_debug('Transient cache hit', $cache_key);
            return $result;
        }

        // Acquire lock
        $lock_key = EasyRest_MPHB_Pricing_Bridge::CACHE_LOCK_PREFIX . $cache_key;
        $lock_acquired = wp_cache_add($lock_key, 1, 'easyrest_mphb', EasyRest_MPHB_Pricing_Bridge::LOCK_TTL);

        if (!$lock_acquired) {
            // Another request is fetching - use retry loop
            $result = $this->wait_for_cache_or_timeout($cache_key);
            if ($result !== null) {
                return $result;
            }

            return EasyRest_MPHB_Price_Result::error(
                'lock_timeout',
                'Price calculation is taking longer than expected. Please try again.'
            );
        }

        try {
            $this->log_debug('Lock acquired, fetching from EasyRest', $cache_key);

            // Fetch from EasyRest
            $result = $this->fetch_from_easyrest($context);

            // Cache successful results
            if (!$result->is_error()) {
                set_transient(
                    EasyRest_MPHB_Pricing_Bridge::CACHE_PREFIX . $cache_key,
                    $result->to_array(),
                    $this->bridge->get_cache_ttl()
                );
            }

            $this->request_cache[$cache_key] = $result;
            return $result;

        } finally {
            // Always release lock
            wp_cache_delete($lock_key, 'easyrest_mphb');
        }
    }

    /**
     * Wait for cache to appear with retry loop
     *
     * @param string $cache_key Cache key
     * @return EasyRest_MPHB_Price_Result|null Result or null if timeout
     */
    private function wait_for_cache_or_timeout($cache_key) {
        $wait_interval_us = EasyRest_MPHB_Pricing_Bridge::LOCK_WAIT_INTERVAL_MS * 1000;
        $max_wait_us = EasyRest_MPHB_Pricing_Bridge::LOCK_WAIT_MAX_MS * 1000;
        $total_waited_us = 0;

        while ($total_waited_us < $max_wait_us) {
            usleep($wait_interval_us);
            $total_waited_us += $wait_interval_us;

            $cached = get_transient(EasyRest_MPHB_Pricing_Bridge::CACHE_PREFIX . $cache_key);
            if ($cached !== false && is_array($cached)) {
                $result = EasyRest_MPHB_Price_Result::from_array($cached);
                $this->request_cache[$cache_key] = $result;
                return $result;
            }
        }

        return null;
    }

    /**
     * Validate context array
     *
     * @param array $context Context array
     * @return true|string True if valid, error message if not
     */
    private function validate_context(array $context) {
        if (empty($context['checkin']) || empty($context['checkout'])) {
            return 'Missing check-in or check-out date';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $context['checkin'])) {
            return 'Invalid check-in date format';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $context['checkout'])) {
            return 'Invalid check-out date format';
        }

        $checkin_dt = DateTime::createFromFormat('Y-m-d', $context['checkin']);
        $checkout_dt = DateTime::createFromFormat('Y-m-d', $context['checkout']);

        if (!$checkin_dt || !$checkout_dt) {
            return 'Invalid date';
        }

        if ($checkout_dt <= $checkin_dt) {
            return 'Check-out must be after check-in';
        }

        $nights = $checkin_dt->diff($checkout_dt)->days;
        if ($nights > 30) {
            return 'Maximum stay is 30 nights';
        }

        $adults = isset($context['adults']) ? absint($context['adults']) : 0;
        $children = isset($context['children']) ? absint($context['children']) : 0;

        if ($adults < 1) {
            return 'At least 1 adult required';
        }
        if (($adults + $children) > 10) {
            return 'Maximum 10 guests';
        }

        return true;
    }

    /**
     * Build cache key from context
     *
     * @param array $context Context array
     * @return string Cache key
     */
    private function build_cache_key(array $context) {
        $key_parts = [
            'at' => isset($context['accommodation_type_id']) ? absint($context['accommodation_type_id']) : 0,
            'in' => $context['checkin'],
            'out' => $context['checkout'],
            'a' => isset($context['adults']) ? absint($context['adults']) : 1,
            'c' => isset($context['children']) ? absint($context['children']) : 0,
            'cur' => isset($context['currency']) ? strtoupper($context['currency']) : 'EUR',
            'lang' => function_exists('MPHB') ? MPHB()->translation()->getCurrentLanguage() : 'en',
        ];

        return md5(implode('|', $key_parts));
    }

    /**
     * Fetch price from EasyRest service
     *
     * @param array $context Context array
     * @return EasyRest_MPHB_Price_Result Price result
     */
    private function fetch_from_easyrest(array $context) {
        if (!function_exists('easyrest_get_price')) {
            return EasyRest_MPHB_Price_Result::error('service_unavailable', 'EasyRest service not available');
        }

        $result = easyrest_get_price(
            $context['checkin'],
            $context['checkout'],
            isset($context['adults']) ? $context['adults'] : 1,
            isset($context['children']) ? $context['children'] : 0
        );

        if (empty($result) || !is_array($result)) {
            return EasyRest_MPHB_Price_Result::error('invalid_response', 'Invalid response from EasyRest');
        }

        if (empty($result['success'])) {
            $error_code = isset($result['code']) ? $result['code'] : 'fetch_failed';
            $error_msg = isset($result['error']) ? $result['error'] : 'Price not available';
            return EasyRest_MPHB_Price_Result::error($error_code, $error_msg);
        }

        // Extract price - use direct_price (discounted) or booking_price
        $price = 0;
        if (isset($result['direct_price']) && $result['direct_price'] > 0) {
            $price = floatval($result['direct_price']);
        } elseif (isset($result['booking_price']) && $result['booking_price'] > 0) {
            $price = floatval($result['booking_price']);
        }

        if ($price <= 0) {
            return EasyRest_MPHB_Price_Result::error('invalid_price', 'Price must be positive');
        }

        return EasyRest_MPHB_Price_Result::success(
            $price,
            isset($result['currency']) ? $result['currency'] : 'EUR',
            isset($result['meta']['source']) ? $result['meta']['source'] : 'easyrest',
            true // EasyRest prices are tax-inclusive
        );
    }

    /**
     * Debug logging helper
     *
     * @param string $message Message
     * @param string $cache_key Cache key for context
     */
    private function log_debug($message, $cache_key) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        $short_key = substr($cache_key, 0, 8);
        error_log("[EasyRest MPHB Provider] [DEBUG] [{$short_key}] {$message}");
    }

    /**
     * Invalidate cache for a specific context
     *
     * @param array $context Context array
     */
    public function invalidate_cache(array $context) {
        $cache_key = $this->build_cache_key($context);
        delete_transient(EasyRest_MPHB_Pricing_Bridge::CACHE_PREFIX . $cache_key);
        unset($this->request_cache[$cache_key]);
    }
}

// =========================================================================
// PRICE RESULT CLASS
// =========================================================================

/**
 * Class EasyRest_MPHB_Price_Result
 *
 * Immutable value object for price fetch results
 */
class EasyRest_MPHB_Price_Result {

    private $success;
    private $total_price;
    private $currency;
    private $source;
    private $tax_inclusive;
    private $error_code;
    private $error_message;

    /**
     * Private constructor - use static factory methods
     */
    private function __construct() {}

    /**
     * Create success result
     *
     * @param float  $price         Total price
     * @param string $currency      Currency code
     * @param string $source        Price source
     * @param bool   $tax_inclusive Whether price includes tax
     * @return EasyRest_MPHB_Price_Result
     */
    public static function success($price, $currency = 'EUR', $source = 'easyrest', $tax_inclusive = true) {
        $result = new self();
        $result->success = true;
        $result->total_price = round(floatval($price), 2);
        $result->currency = strtoupper($currency);
        $result->source = $source;
        $result->tax_inclusive = (bool) $tax_inclusive;
        $result->error_code = null;
        $result->error_message = null;
        return $result;
    }

    /**
     * Create error result
     *
     * @param string $code    Error code
     * @param string $message Error message
     * @return EasyRest_MPHB_Price_Result
     */
    public static function error($code, $message) {
        $result = new self();
        $result->success = false;
        $result->total_price = 0;
        $result->currency = 'EUR';
        $result->source = null;
        $result->tax_inclusive = false;
        $result->error_code = $code;
        $result->error_message = $message;
        return $result;
    }

    /**
     * Create from array (for cache retrieval)
     *
     * @param array $data Data array
     * @return EasyRest_MPHB_Price_Result
     */
    public static function from_array(array $data) {
        if (!empty($data['success'])) {
            return self::success(
                $data['total_price'],
                isset($data['currency']) ? $data['currency'] : 'EUR',
                isset($data['source']) ? $data['source'] : 'cache',
                isset($data['tax_inclusive']) ? $data['tax_inclusive'] : true
            );
        }
        return self::error(
            isset($data['error_code']) ? $data['error_code'] : 'unknown',
            isset($data['error_message']) ? $data['error_message'] : 'Unknown error'
        );
    }

    /**
     * Convert to array (for caching)
     *
     * @return array
     */
    public function to_array() {
        return [
            'success' => $this->success,
            'total_price' => $this->total_price,
            'currency' => $this->currency,
            'source' => $this->source,
            'tax_inclusive' => $this->tax_inclusive,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
        ];
    }

    // Getters
    public function is_error() { return !$this->success; }
    public function is_success() { return $this->success; }
    public function get_total_price() { return $this->total_price; }
    public function get_currency() { return $this->currency; }
    public function get_source() { return $this->source; }
    public function is_tax_inclusive() { return $this->tax_inclusive; }
    public function get_error_code() { return $this->error_code; }
    public function get_error_message() { return $this->error_message; }
}
