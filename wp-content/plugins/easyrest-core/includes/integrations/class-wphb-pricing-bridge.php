<?php
/**
 * WP Hotel Booking Pricing Bridge v2.2
 *
 * Bulletproof integration of EasyRest dynamic pricing with WP Hotel Booking.
 *
 * Key features:
 * - Centralized price provider with robust caching
 * - Pre-checkout validation (strict mode blocks before booking creation)
 * - No $_SESSION usage - uses transients keyed by cart hash
 * - Consistent pricing across all WPHB hooks
 * - Proper tax handling
 * - Safe logging without PII exposure
 * - Single room page dynamic price display (v2.2)
 *
 * v2.2.2 Changes:
 * - FIXED Issue 1: Native WPHB base price now properly hidden on single room page when
 *   dynamic price is rendered. Moved CSS injection to wp_footer to ensure flag is set.
 * - FIXED Issue 2: Cart "Gross Total" column hidden when dynamic pricing is active to
 *   prevent showing two conflicting prices (old WPHB row price vs correct Grand Total).
 * - ADDED cart_dynamic_pricing_applied flag to track when cart prices are overridden.
 * - IMPROVED hide mechanism uses flag-driven logic for reliability.
 *
 * v2.2.1 Changes:
 * - FIXED adults/children defaults to match WPHB (adults=1, children=0 instead of 2/0)
 * - ADDED single_room_dynamic_price_available flag to track when dynamic price is shown
 * - ADDED filter hotel_booking_loop_room_price to hide native base price when dynamic shown
 * - ADDED debug logging for single-room context parameters
 *
 * v2.2 Changes:
 * - ADDED render_single_room_dynamic_price() to display EasyRest price on single
 *   room pages when dates are in URL parameters. Does NOT override native WPHB
 *   price - adds supplementary "Price for your dates" block.
 * - Hooks into hotel_booking_single_room_after_booking_form action.
 *
 * v2.1 Changes (Codex runtime audit fixes):
 * - REMOVED product-level hooks (hotel_booking_room_total_price_*) to avoid
 *   per-night breakdown and qty>1 mismatches. Dynamic pricing now enforced
 *   exclusively via cart/checkout/transaction hooks.
 * - IMPROVED lock timeout handling with retry loop (up to 2s) to prevent
 *   spurious failures in strict mode when primary request is still fetching.
 *
 * @package EasyRest_Core
 * @since 2.0.0
 * @version 2.2.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_WPHB_Pricing_Bridge
 */
class EasyRest_WPHB_Pricing_Bridge {

    /**
     * Singleton instance
     *
     * @var EasyRest_WPHB_Pricing_Bridge|null
     */
    private static $instance = null;

    /**
     * Option keys
     */
    const OPTION_ENABLED = 'easyrest_wphb_dynamic_pricing_enabled';
    const OPTION_MODE = 'easyrest_wphb_dynamic_pricing_fallback';
    const OPTION_CACHE_TTL = 'easyrest_wphb_cache_ttl';
    const OPTION_VALIDATION_ENABLED = 'easyrest_wphb_validation_enabled';

    /**
     * Mode constants
     */
    const MODE_STRICT = 'block';
    const MODE_FALLBACK = 'fallback';

    /**
     * Cache constants
     */
    const CACHE_PREFIX = 'easyrest_wphb_price_';
    const CACHE_LOCK_PREFIX = 'easyrest_wphb_lock_';
    const ERROR_TRANSIENT_PREFIX = 'easyrest_wphb_err_';
    const DEFAULT_CACHE_TTL = 900; // 15 minutes
    const LOCK_TTL = 30; // 30 seconds

    /**
     * Lock wait constants (v2.1 - improved lock timeout handling)
     *
     * When another request holds the lock, we retry in a loop instead of
     * immediately failing. This prevents spurious lock_timeout errors in
     * strict mode when the primary request is still fetching.
     */
    const LOCK_WAIT_INTERVAL_MS = 100;   // Sleep 100ms between retries
    const LOCK_WAIT_MAX_MS = 2000;       // Maximum wait time: 2 seconds

    /**
     * Price provider instance
     *
     * @var EasyRest_WPHB_Price_Provider|null
     */
    private $price_provider = null;

    /**
     * Per-request cache to avoid duplicate provider calls
     *
     * @var array
     */
    private $request_cache = [];

    /**
     * Flag indicating if single room dynamic price was successfully rendered
     *
     * Used to coordinate with native WPHB price display - when this is true,
     * we hide/adapt the native base price to avoid showing two conflicting prices.
     *
     * @since 2.2.1
     * @var bool
     */
    private $single_room_dynamic_price_available = false;

    /**
     * Flag indicating if cart total was overridden with dynamic pricing
     *
     * Used to hide the per-row "Gross Total" column that shows the old WPHB price
     * when we've overridden the Grand Total with EasyRest dynamic pricing.
     *
     * @since 2.2.2
     * @var bool
     */
    private $cart_dynamic_pricing_applied = false;

    /**
     * Get singleton instance
     *
     * @return EasyRest_WPHB_Pricing_Bridge
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
        // Only initialize if WP Hotel Booking is active
        if (!$this->is_wphb_active()) {
            $this->log_safe('Bridge NOT initialized: WP Hotel Booking not active', 'warning');
            return;
        }

        // Only initialize if bridge is enabled
        if (!$this->is_enabled()) {
            $this->log_safe('Bridge disabled via settings (option: ' . self::OPTION_ENABLED . ' = ' . var_export(get_option(self::OPTION_ENABLED), true) . ')', 'debug');
            return;
        }

        // Initialize price provider
        $this->price_provider = new EasyRest_WPHB_Price_Provider($this);

        $this->init_hooks();
        $this->log_safe('Bridge initialized (v2.2.2) - Mode: ' . $this->get_mode() . ', Validation: ' . ($this->is_validation_enabled() ? 'ON' : 'OFF'), 'info');
    }

    /**
     * Check if WP Hotel Booking plugin is active
     *
     * @return bool
     */
    private function is_wphb_active() {
        return class_exists('WP_Hotel_Booking') || function_exists('hb_get_cart');
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
        // VALIDATION HOOKS (Pre-booking - can block checkout)
        // =========================================================================

        // Primary validation: fires BEFORE booking creation
        // We throw an exception here in strict mode to prevent booking
        add_filter('hotel_booking_checkout_booking_info', [$this, 'validate_checkout_pricing'], 5, 2);

        // =========================================================================
        // TRANSACTION HOOKS (Final pricing for checkout/payment)
        // =========================================================================

        // Override individual room prices in transaction (PRIMARY hook)
        add_filter('hb_generate_transaction_object_room', [$this, 'override_transaction_room'], 10, 2);

        // Override entire transaction object (for totals consistency)
        add_filter('hb_generate_transaction_object', [$this, 'override_transaction_object'], 10, 2);

        // =========================================================================
        // CART ITEM HOOKS (Display in cart page)
        // =========================================================================

        add_filter('hotel_booking_cart_item_amount_incl_tax', [$this, 'override_cart_item_amount'], 10, 4);
        add_filter('hotel_booking_cart_item_amount_excl_tax', [$this, 'override_cart_item_amount_excl'], 10, 4);
        add_filter('hotel_booking_cart_item_total_amount', [$this, 'override_cart_item_amount'], 10, 4);

        // =========================================================================
        // CART TOTAL HOOKS (Cart page totals)
        // =========================================================================

        add_filter('hotel_booking_cart_total_include_tax', [$this, 'override_cart_total_incl_tax'], 10, 1);
        add_filter('hotel_booking_cart_total_exclude_tax', [$this, 'override_cart_total_excl_tax'], 10, 1);
        add_filter('hb_cart_sub_total', [$this, 'override_cart_subtotal'], 10, 1);
        add_filter('hotel_booking_get_cart_total', [$this, 'override_cart_final_total'], 10, 1);

        // =========================================================================
        // ROOM PRODUCT HOOKS - INTENTIONALLY REMOVED (v2.1)
        // =========================================================================
        //
        // IMPORTANT: We do NOT hook into product-level filters:
        //   - hotel_booking_room_total_price_excl_tax
        //   - hotel_booking_room_total_price_incl_tax
        //   - hotel_booking_room_item_total_include_tax
        //   - hotel_booking_room_item_total_exclude_tax
        //
        // Reason (per Codex runtime audit):
        //   WPHB uses these filters for BOTH full-stay totals AND per-night breakdowns
        //   (via get_total($c_date, 1, 1, ...)). Our EasyRest override returns the full
        //   stay price regardless of context, which causes:
        //     1. Per-night breakdown displays to show wrong values
        //     2. Qty > 1 scenarios to undercharge (product context lacks quantity)
        //
        // Our dynamic pricing is correctly enforced via:
        //   - Cart item hooks (hotel_booking_cart_item_amount_*, hotel_booking_cart_item_total_amount)
        //   - Cart total hooks (hotel_booking_cart_total_*, hb_cart_sub_total, hotel_booking_get_cart_total)
        //   - Transaction hooks (hb_generate_transaction_object_room - PRIMARY)
        //   - Pre-checkout validation (hotel_booking_checkout_booking_info)
        //
        // These hooks fire in cart/checkout context where we have full booking parameters.
        // Per-night displays and singular product prices use WPHB native logic, which is correct.
        //
        // @see https://codex-audit/wphb-pricing-bridge-v2.1

        // =========================================================================
        // LEGACY GUARD (Backup safety - runs after booking created)
        // =========================================================================

        // Keep as safety net but mark as legacy
        add_action('hb_new_booking', [$this, 'legacy_guard_booking_creation'], 5, 1);

        // =========================================================================
        // SINGLE ROOM PAGE - DYNAMIC PRICE DISPLAY (v2.2)
        // =========================================================================
        //
        // Display an additional "EasyRest price for your dates" block on single
        // room pages when the user has selected dates via URL parameters.
        // This does NOT override the native WPHB price - it adds supplementary info.
        //
        add_action('hotel_booking_single_room_after_booking_form', [$this, 'render_single_room_dynamic_price'], 10, 1);

        // =========================================================================
        // SINGLE ROOM PAGE - HIDE NATIVE BASE PRICE WHEN DYNAMIC AVAILABLE (v2.2.2)
        // =========================================================================
        //
        // When dynamic EasyRest price is successfully rendered, we hide the native
        // WPHB base price to avoid showing two conflicting prices (UX confusion).
        //
        // IMPORTANT (v2.2.2 fix): We use wp_footer instead of hotel_booking_loop_room_price
        // because hotel_booking_loop_room_price fires BEFORE hotel_booking_single_room_after_booking_form,
        // so the flag wouldn't be set yet. By using wp_footer, we ensure the flag has been
        // set by render_single_room_dynamic_price() before we decide to inject hiding CSS.
        //
        add_action('wp_footer', [$this, 'inject_single_room_price_hiding_css'], 20);

        // =========================================================================
        // CART PAGE - HIDE GROSS TOTAL COLUMN WHEN DYNAMIC PRICING ACTIVE (v2.2.2)
        // =========================================================================
        //
        // When EasyRest dynamic pricing has been applied to the cart (Grand Total is
        // overridden), we hide the per-row "Gross Total" column to avoid showing
        // conflicting prices (old WPHB row price vs correct Grand Total).
        //
        add_action('wp_footer', [$this, 'inject_cart_gross_total_hiding_css'], 20);

        // =========================================================================
        // CLEANUP
        // =========================================================================

        add_action('hotel_booking_empty_cart', [$this, 'cleanup_error_state']);
        add_action('wp_logout', [$this, 'cleanup_error_state']);
    }

    // =========================================================================
    // VALIDATION METHODS
    // =========================================================================

    /**
     * Validate checkout pricing BEFORE booking creation
     *
     * This is the PRIMARY validation point. In strict mode, we throw an exception
     * if pricing cannot be fetched, which prevents booking creation.
     *
     * @param array  $booking_info Booking metadata
     * @param object $transaction  Full transaction object
     * @return array Booking info (unchanged if valid)
     * @throws Exception If pricing validation fails in strict mode
     */
    public function validate_checkout_pricing($booking_info, $transaction) {
        if (!$this->is_validation_enabled()) {
            return $booking_info;
        }

        $mode = $this->get_mode();
        $order_items = isset($transaction->order_items) ? $transaction->order_items : [];

        $this->log_safe('Pre-checkout validation started', 'info', [
            'mode' => $mode,
            'item_count' => count($order_items),
        ]);

        foreach ($order_items as $item) {
            // Skip extras (have parent_id)
            if (!empty($item['parent_id'])) {
                continue;
            }

            $context = $this->build_price_context_from_transaction_item($item);
            $result = $this->price_provider->get_price($context);

            if ($result->is_error()) {
                $this->log_safe('Validation failed for item', 'warning', [
                    'product_id' => $context['product_id'],
                    'error_code' => $result->get_error_code(),
                ]);

                if ($mode === self::MODE_STRICT) {
                    // Store error for display
                    $this->store_error_state($result->get_error_message());

                    // Throw exception to prevent booking creation
                    throw new Exception(
                        __('Unable to calculate the price for your selected dates. Please try again or contact us.', 'easyrest-core')
                    );
                }
                // In fallback mode, continue with WPHB native pricing
            }
        }

        $this->log_safe('Pre-checkout validation passed', 'info');
        return $booking_info;
    }

    // =========================================================================
    // TRANSACTION HOOKS
    // =========================================================================

    /**
     * Override room data in transaction object (PRIMARY pricing hook)
     *
     * @param array  $room_array Room transaction data
     * @param object $product    WPHB_Product_Room object
     * @return array Modified room array
     */
    public function override_transaction_room($room_array, $product) {
        $this->log_safe('>>> override_transaction_room CALLED (PRIMARY HOOK)', 'debug', [
            'has_room_array' => !empty($room_array),
            'has_product' => !empty($product),
            'room_total' => isset($room_array['total']) ? $room_array['total'] : 'N/A',
        ]);

        // Skip extras
        if (!empty($room_array['parent_id'])) {
            $this->log_safe('Skipping extra in transaction (has parent_id)', 'debug');
            return $room_array;
        }

        $context = $this->build_price_context_from_transaction_item($room_array, $product);
        $this->log_safe('Transaction context built', 'debug', $context);

        $result = $this->price_provider->get_price($context);

        if ($result->is_error()) {
            $this->log_safe('Transaction room price fetch failed', 'warning', [
                'product_id' => $context['product_id'],
                'error_code' => $result->get_error_code(),
                'mode' => $this->get_mode(),
            ]);

            if ($this->get_mode() === self::MODE_FALLBACK) {
                // Return original - WPHB pricing will be used
                return $room_array;
            }

            // In strict mode, validation should have caught this
            // But as safety, store error
            $this->store_error_state($result->get_error_message());
            return $room_array;
        }

        // Calculate amounts
        $qty = isset($room_array['qty']) ? max(1, absint($room_array['qty'])) : 1;
        $unit_total = $result->get_total_price();
        $total = $unit_total * $qty;

        // Tax handling: EasyRest returns tax-inclusive price
        // We set subtotal = total and tax_total = 0 to avoid double-taxation
        // OR calculate tax based on WPHB settings
        $tax_rate = function_exists('hb_get_tax_settings') ? hb_get_tax_settings() : 0;

        if ($tax_rate > 0 && $result->is_tax_inclusive()) {
            // Price includes tax, extract it
            $subtotal = $total / (1 + $tax_rate);
            $tax_total = $total - $subtotal;
        } else {
            // Price is net or no tax configured
            $subtotal = $total;
            $tax_total = 0;
        }

        // Ensure positive values
        $total = max(0.01, round($total, 2));
        $subtotal = max(0.01, round($subtotal, 2));
        $tax_total = max(0, round($tax_total, 2));

        $room_array['subtotal'] = $subtotal;
        $room_array['total'] = $total;
        $room_array['tax_total'] = $tax_total;

        $this->log_safe('Transaction room overridden', 'info', [
            'product_id' => $context['product_id'],
            'qty' => $qty,
            'unit_price' => $unit_total,
            'total' => $total,
            'source' => $result->get_source(),
        ]);

        return $room_array;
    }

    /**
     * Override entire transaction object for totals consistency
     *
     * @param object $transaction   Transaction object
     * @param object $payment_method Payment method
     * @return object Modified transaction
     */
    public function override_transaction_object($transaction, $payment_method) {
        // Recalculate booking_info totals based on our overridden order_items
        if (!empty($transaction->order_items)) {
            $total_tax = 0;
            $total_amount = 0;

            foreach ($transaction->order_items as $item) {
                if (empty($item['parent_id'])) {
                    $total_amount += isset($item['total']) ? floatval($item['total']) : 0;
                    $total_tax += isset($item['tax_total']) ? floatval($item['tax_total']) : 0;
                }
            }

            // Update booking_info tax field
            if (isset($transaction->booking_info['_hb_tax'])) {
                $transaction->booking_info['_hb_tax'] = $total_tax;
            }
        }

        return $transaction;
    }

    // =========================================================================
    // CART ITEM HOOKS
    // =========================================================================

    /**
     * Override cart item amount (including tax)
     *
     * @param float  $amount    Original amount
     * @param string $cart_id   Cart item ID
     * @param object $cart_item Cart item object
     * @param object $product   WPHB_Product_Room object
     * @return float Modified amount
     */
    public function override_cart_item_amount($amount, $cart_id, $cart_item, $product) {
        $this->log_safe('>>> override_cart_item_amount CALLED', 'debug', [
            'original_amount' => $amount,
            'cart_id' => $cart_id,
            'has_cart_item' => !empty($cart_item),
            'has_product' => !empty($product),
        ]);

        // Skip extras
        if (isset($cart_item->parent_id) && !empty($cart_item->parent_id)) {
            $this->log_safe('Skipping extra (has parent_id)', 'debug');
            return $amount;
        }

        $context = $this->build_price_context_from_cart_item($cart_item, $product);
        $this->log_safe('Built price context', 'debug', $context);

        $result = $this->price_provider->get_price($context);

        if ($result->is_error()) {
            $this->log_safe('Price fetch ERROR in cart item override', 'warning', [
                'error_code' => $result->get_error_code(),
                'error_msg' => $result->get_error_message(),
            ]);
            // For display, silently fall back to original
            return $amount;
        }

        $qty = isset($cart_item->quantity) ? max(1, absint($cart_item->quantity)) : 1;
        $new_amount = round($result->get_total_price() * $qty, 2);

        $this->log_safe('Cart item price OVERRIDDEN', 'info', [
            'original' => $amount,
            'new' => $new_amount,
            'source' => $result->get_source(),
        ]);

        return $new_amount;
    }

    /**
     * Override cart item amount (excluding tax)
     *
     * @param float  $amount    Original amount
     * @param string $cart_id   Cart item ID
     * @param object $cart_item Cart item object
     * @param object $product   WPHB_Product_Room object
     * @return float Modified amount
     */
    public function override_cart_item_amount_excl($amount, $cart_id, $cart_item, $product) {
        // Skip extras
        if (isset($cart_item->parent_id) && !empty($cart_item->parent_id)) {
            return $amount;
        }

        $context = $this->build_price_context_from_cart_item($cart_item, $product);
        $result = $this->price_provider->get_price($context);

        if ($result->is_error()) {
            return $amount;
        }

        $qty = isset($cart_item->quantity) ? max(1, absint($cart_item->quantity)) : 1;
        $total = $result->get_total_price() * $qty;

        // Extract net amount if tax-inclusive
        $tax_rate = function_exists('hb_get_tax_settings') ? hb_get_tax_settings() : 0;
        if ($tax_rate > 0 && $result->is_tax_inclusive()) {
            $total = $total / (1 + $tax_rate);
        }

        return round($total, 2);
    }

    // =========================================================================
    // CART TOTAL HOOKS
    // =========================================================================

    /**
     * Override cart total including tax
     *
     * @param float $total Original total
     * @return float Modified total
     */
    public function override_cart_total_incl_tax($total) {
        $calculated = $this->calculate_cart_total(true);
        return $calculated !== null ? $calculated : $total;
    }

    /**
     * Override cart total excluding tax
     *
     * @param float $total Original total
     * @return float Modified total
     */
    public function override_cart_total_excl_tax($total) {
        $calculated = $this->calculate_cart_total(false);
        return $calculated !== null ? $calculated : $total;
    }

    /**
     * Override cart subtotal
     *
     * @param float $subtotal Original subtotal
     * @return float Modified subtotal
     */
    public function override_cart_subtotal($subtotal) {
        $calculated = $this->calculate_cart_total(false);
        return $calculated !== null ? $calculated : $subtotal;
    }

    /**
     * Override final cart total
     *
     * @param float $total Original total
     * @return float Modified total
     */
    public function override_cart_final_total($total) {
        $calculated = $this->calculate_cart_total(true);
        return $calculated !== null ? $calculated : $total;
    }

    /**
     * Calculate cart total from all room items
     *
     * @param bool $include_tax Whether to include tax
     * @return float|null Total or null if unable to calculate
     */
    private function calculate_cart_total($include_tax = true) {
        if (!function_exists('hb_get_cart')) {
            return null;
        }

        $cart = hb_get_cart();
        if (!$cart || empty($cart->cart_contents)) {
            return null;
        }

        $total = 0;
        $any_success = false;

        foreach ($cart->cart_contents as $cart_id => $cart_item) {
            // Skip extras
            if (isset($cart_item->parent_id) && !empty($cart_item->parent_id)) {
                // Add extra's original amount
                $total += isset($cart_item->amount) ? floatval($cart_item->amount) : 0;
                continue;
            }

            $product = isset($cart_item->product_data) ? $cart_item->product_data : null;
            $context = $this->build_price_context_from_cart_item($cart_item, $product);
            $result = $this->price_provider->get_price($context);

            if ($result->is_error()) {
                // Can't calculate full total if any item fails
                return null;
            }

            $any_success = true;
            $qty = isset($cart_item->quantity) ? max(1, absint($cart_item->quantity)) : 1;
            $item_total = $result->get_total_price() * $qty;

            if (!$include_tax && $result->is_tax_inclusive()) {
                $tax_rate = function_exists('hb_get_tax_settings') ? hb_get_tax_settings() : 0;
                if ($tax_rate > 0) {
                    $item_total = $item_total / (1 + $tax_rate);
                }
            }

            $total += $item_total;
        }

        // v2.2.2: Set flag when we successfully calculate dynamic pricing for cart
        if ($any_success) {
            $this->cart_dynamic_pricing_applied = true;
        }

        return $any_success ? round($total, 2) : null;
    }

    // =========================================================================
    // SINGLE ROOM PAGE - DYNAMIC PRICE DISPLAY (v2.2)
    // =========================================================================

    /**
     * Render dynamic EasyRest price on single room page
     *
     * Displays an additional "Price for your dates" block when the user has
     * selected dates via URL parameters. When dynamic price is successfully
     * rendered, sets $single_room_dynamic_price_available flag which is used
     * to hide the native WPHB base price (avoiding two conflicting prices).
     *
     * @since 2.2.0
     * @since 2.2.1 Fixed adults/children defaults to match WPHB (1/0 instead of 2/0)
     * @param object|null $room WPHB_Room object (passed by the action hook)
     */
    public function render_single_room_dynamic_price($room = null) {
        // Reset flag at start of each render attempt
        $this->single_room_dynamic_price_available = false;

        // Get room ID from passed object or global post
        $room_id = null;
        if ($room && isset($room->ID)) {
            $room_id = absint($room->ID);
        } elseif ($room && isset($room->post) && isset($room->post->ID)) {
            $room_id = absint($room->post->ID);
        } else {
            $room_id = get_the_ID();
        }

        if (!$room_id) {
            $this->log_safe('Single room price: No room ID available', 'debug');
            return;
        }

        // Extract search context from URL parameters
        // WPHB uses various param names: check_in_date, check_out_date, adults, max_child, etc.
        $checkin = $this->get_search_param('check_in_date');
        $checkout = $this->get_search_param('check_out_date');

        // Get adults with WPHB-matching default of 1 (not 2)
        // Check multiple param names: adults, adult_qty (form submission)
        $adults_raw = $this->get_search_param('adults', '');
        if ($adults_raw === '') {
            $adults_raw = $this->get_search_param('adult_qty', '');
        }
        $adults = ($adults_raw !== '') ? absint($adults_raw) : 1; // Default: 1 (WPHB default)

        // Get children with WPHB-matching default of 0
        // Check multiple param names: max_child, child_qty, children
        $children_raw = $this->get_search_param('max_child', '');
        if ($children_raw === '') {
            $children_raw = $this->get_search_param('child_qty', '');
        }
        if ($children_raw === '') {
            $children_raw = $this->get_search_param('children', '');
        }
        $children = ($children_raw !== '') ? absint($children_raw) : 0; // Default: 0 (WPHB default)

        // Also check alternative param names for dates
        if (empty($checkin)) {
            $checkin = $this->get_search_param('hb_check_in_date');
        }
        if (empty($checkout)) {
            $checkout = $this->get_search_param('hb_check_out_date');
        }
        if (empty($checkin)) {
            $checkin = $this->get_search_param('checkin');
        }
        if (empty($checkout)) {
            $checkout = $this->get_search_param('checkout');
        }

        // Debug log all extracted parameters (v2.2.1)
        $this->log_safe('Single room price: URL params extracted', 'debug', [
            'room_id' => $room_id,
            'checkin_raw' => $this->get_search_param('check_in_date'),
            'checkout_raw' => $this->get_search_param('check_out_date'),
            'adults_param' => $this->get_search_param('adults', 'not_set'),
            'adult_qty_param' => $this->get_search_param('adult_qty', 'not_set'),
            'max_child_param' => $this->get_search_param('max_child', 'not_set'),
            'child_qty_param' => $this->get_search_param('child_qty', 'not_set'),
            'adults_final' => $adults,
            'children_final' => $children,
        ]);

        // If no valid dates, skip rendering
        if (empty($checkin) || empty($checkout)) {
            $this->log_safe('Single room price: No dates in URL, skipping', 'debug', [
                'room_id' => $room_id,
            ]);
            return;
        }

        // Normalize date formats (YYYY/MM/DD -> YYYY-MM-DD)
        $checkin = $this->normalize_date_format($checkin);
        $checkout = $this->normalize_date_format($checkout);

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) {
            $this->log_safe('Single room price: Invalid date format after normalization', 'debug', [
                'checkin' => $checkin,
                'checkout' => $checkout,
            ]);
            return;
        }

        // Validate checkout > checkin
        if (strtotime($checkout) <= strtotime($checkin)) {
            $this->log_safe('Single room price: Checkout not after checkin', 'debug');
            return;
        }

        $this->log_safe('Single room price: Fetching dynamic price', 'info', [
            'room_id' => $room_id,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
        ]);

        // Build price context
        $context = [
            'product_id' => $room_id,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => max(1, $adults),
            'children' => $children,
            'currency' => function_exists('hb_get_currency') ? hb_get_currency() : 'EUR',
            'qty' => 1,
        ];

        // Fetch price from provider
        $result = $this->price_provider->get_price($context);

        if ($result->is_error()) {
            $this->log_safe('Single room price: Provider returned error', 'warning', [
                'room_id' => $room_id,
                'error_code' => $result->get_error_code(),
                'error_msg' => $result->get_error_message(),
            ]);

            // In debug mode, show a note; otherwise, silently skip
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
        $formatted_price = function_exists('hb_format_price')
            ? hb_format_price($price, true, false)
            : number_format($price, 2, ',', ' ') . ' ' . $currency;

        // Build guest string
        $guest_parts = [];
        if ($adults > 0) {
            $guest_parts[] = sprintf(_n('%d adult', '%d adults', $adults, 'easyrest-core'), $adults);
        }
        if ($children > 0) {
            $guest_parts[] = sprintf(_n('%d child', '%d children', $children, 'easyrest-core'), $children);
        }
        $guest_string = implode(', ', $guest_parts);

        $this->log_safe('Single room price: Rendering dynamic price block', 'info', [
            'room_id' => $room_id,
            'price' => $price,
            'nights' => $nights,
            'source' => $result->get_source(),
        ]);

        // Set flag BEFORE rendering - this signals that dynamic price is available
        // and will be used by maybe_hide_native_base_price() to suppress native price
        $this->single_room_dynamic_price_available = true;

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
     * Inject CSS to hide native WPHB base price on single room page (v2.2.2)
     *
     * Called via wp_footer to ensure the flag has been set by render_single_room_dynamic_price().
     * Only injects CSS if $single_room_dynamic_price_available is true (flag-driven).
     *
     * Targets:
     * - .hb-total-price (the "Total: €XXX" in the booking form)
     * - .hb_price (native price display in header via hotel_booking_loop_room_price)
     *
     * @since 2.2.2
     */
    public function inject_single_room_price_hiding_css() {
        // Only run on single room pages
        if (!is_singular('hb_room')) {
            return;
        }

        // Only hide if dynamic price was successfully rendered (flag-driven)
        if (!$this->single_room_dynamic_price_available) {
            $this->log_safe('[SingleRoom] Not hiding native price: dynamic price not available', 'debug');
            return;
        }

        $this->log_safe('[SingleRoom] Hiding native WPHB base price (dynamic price available)', 'debug');

        // Output CSS to hide native base price elements
        ?>
        <style id="easyrest-hide-native-single-room-price">
            /* v2.2.2: Hide native WPHB prices on single room page when EasyRest dynamic price is shown */

            /* Hide the "Total: €XXX" in the booking form (lines 121-129 of booking-form.php) */
            body.single-hb_room .hb-room-price .hb-total-price {
                display: none !important;
            }

            /* Hide native price display in header (from hotel_booking_loop_room_price action) */
            body.single-hb_room .hb-booking-room-form-head .hb_price,
            body.single-hb_room .hb-booking-room-form-header .hb_price,
            body.single-hb_room .hb-booking-room-form-head .price,
            body.single-hb_room .hb-booking-room-form-header .price,
            body.single-hb_room .hb-booking-room-form-head > .hb_room_price,
            body.single-hb_room .hb-booking-room-form-header > .hb_room_price {
                display: none !important;
            }

            /* Keep the "View details" link visible but hide only the price */
            body.single-hb_room .hb_view_price {
                display: block !important;
            }
        </style>
        <?php
    }

    /**
     * Inject CSS to hide cart "Gross Total" column when dynamic pricing is active (v2.2.2)
     *
     * Called via wp_footer to ensure cart total hooks have run and set the flag.
     * Only injects CSS if $cart_dynamic_pricing_applied is true (flag-driven).
     *
     * This prevents showing two conflicting prices:
     * - Per-row "Gross Total" shows old WPHB price (e.g., €360)
     * - Grand Total shows correct EasyRest price (e.g., €545.70)
     *
     * @since 2.2.2
     */
    public function inject_cart_gross_total_hiding_css() {
        // Only run if cart dynamic pricing was applied
        if (!$this->cart_dynamic_pricing_applied) {
            return;
        }

        $this->log_safe('[Cart] Hiding WPHB Gross Total column (dynamic price applied)', 'debug');

        // Output CSS to hide the Gross Total column in cart table
        ?>
        <style id="easyrest-hide-cart-gross-total">
            /* v2.2.2: Hide per-row Gross Total column when EasyRest dynamic pricing is active */
            /* This prevents showing conflicting prices (old WPHB row price vs correct Grand Total) */

            /* Hide the Gross Total header and data cells */
            #hotel-booking-cart .hb_table th.hb_gross_total,
            #hotel-booking-cart .hb_table td.hb_gross_total {
                display: none !important;
            }

            /* Alternative selectors for different theme structures */
            .hb-cart-table th.hb_gross_total,
            .hb-cart-table td.hb_gross_total,
            table.hb_table th.hb_gross_total,
            table.hb_table td.hb_gross_total {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * @deprecated 2.2.2 Replaced by inject_single_room_price_hiding_css()
     */
    public function maybe_hide_native_base_price() {
        // Deprecated: This method is no longer hooked.
        // Kept for backwards compatibility in case external code calls it directly.
        // The new inject_single_room_price_hiding_css() uses flag-driven logic via wp_footer.
        return;
    }

    /**
     * Get search parameter from URL (GET or POST)
     *
     * @since 2.2.0
     * @param string $key     Parameter key
     * @param mixed  $default Default value
     * @return mixed Parameter value or default
     */
    private function get_search_param($key, $default = '') {
        // Check GET first (URL parameters from search)
        if (isset($_GET[$key]) && !empty($_GET[$key])) {
            return sanitize_text_field(wp_unslash($_GET[$key]));
        }

        // Check POST (form submissions)
        if (isset($_POST[$key]) && !empty($_POST[$key])) {
            return sanitize_text_field(wp_unslash($_POST[$key]));
        }

        // Use WPHB's helper if available
        if (function_exists('hb_get_request')) {
            $value = hb_get_request($key, '');
            if (!empty($value)) {
                return sanitize_text_field($value);
            }
        }

        return $default;
    }

    // =========================================================================
    // ROOM PRODUCT HOOKS - DEPRECATED (v2.1)
    // =========================================================================
    //
    // These methods are NO LONGER HOOKED as of v2.1.
    // Kept for backwards compatibility in case external code calls them directly.
    // They now simply return the original value (passthrough).
    //
    // Reason: Product-level filters in WPHB are used for both full-stay totals
    // and per-night breakdowns. Our override caused incorrect per-night displays
    // and qty>1 undercharging. See init_hooks() comments for full explanation.

    /**
     * Override room total price excluding tax
     *
     * @deprecated 2.1.0 No longer hooked - returns original value
     * @param float  $total   Original total
     * @param object $product WPHB_Product_Room object
     * @return float Original total (passthrough)
     */
    public function override_room_total_excl_tax($total, $product) {
        // DEPRECATED: Return original value - we no longer override product-level pricing
        // Dynamic pricing is enforced via cart/transaction hooks instead
        return $total;
    }

    /**
     * Override room tax amount
     *
     * @deprecated 2.1.0 No longer hooked - returns original value
     * @param float  $tax_price Original tax amount
     * @param object $product   WPHB_Product_Room object
     * @return float Original tax amount (passthrough)
     */
    public function override_room_tax_amount($tax_price, $product) {
        // DEPRECATED: Return original value - we no longer override product-level pricing
        return $tax_price;
    }

    /**
     * Override room item total including tax
     *
     * @deprecated 2.1.0 No longer hooked - returns original value
     * @param float  $total   Original total
     * @param object $product WPHB_Product_Room object
     * @return float Original total (passthrough)
     */
    public function override_room_item_total_incl($total, $product) {
        // DEPRECATED: Return original value - we no longer override product-level pricing
        return $total;
    }

    /**
     * Override room item total excluding tax
     *
     * @deprecated 2.1.0 No longer hooked - returns original value
     * @param float  $total   Original total
     * @param object $product WPHB_Product_Room object
     * @return float Original total (passthrough)
     */
    public function override_room_item_total_excl($total, $product) {
        // DEPRECATED: Return original value - we no longer override product-level pricing
        return $total;
    }

    // =========================================================================
    // LEGACY GUARD (Backup safety)
    // =========================================================================

    /**
     * LEGACY: Guard booking creation
     *
     * This runs AFTER booking is created. Kept as safety net but validation
     * should catch issues earlier. Marked for potential removal in future.
     *
     * @param int $booking_id Booking ID
     * @deprecated Use validate_checkout_pricing() instead
     */
    public function legacy_guard_booking_creation($booking_id) {
        // Only act in strict mode
        if ($this->get_mode() !== self::MODE_STRICT) {
            return;
        }

        $error = $this->get_error_state();

        if (!empty($error)) {
            $this->log_safe('LEGACY GUARD: Error state found after booking creation', 'error', [
                'booking_id' => $booking_id,
            ]);

            // Clear the error
            $this->clear_error_state();

            // At this point booking exists - log for manual review
            // We cannot delete the booking safely here
            // Consider: update booking status to 'failed' or add meta for review
            update_post_meta($booking_id, '_easyrest_pricing_error', $error);
            update_post_meta($booking_id, '_easyrest_pricing_error_time', current_time('mysql'));
        }
    }

    // =========================================================================
    // CONTEXT BUILDERS
    // =========================================================================

    /**
     * Build price context from transaction item array
     *
     * @param array       $item    Transaction item array
     * @param object|null $product WPHB_Product_Room object (optional)
     * @return array Context array
     */
    private function build_price_context_from_transaction_item($item, $product = null) {
        $check_in = isset($item['check_in_date']) ? $item['check_in_date'] : 0;
        $check_out = isset($item['check_out_date']) ? $item['check_out_date'] : 0;

        // Convert timestamps to Y-m-d
        $checkin_ymd = $this->timestamp_to_ymd($check_in);
        $checkout_ymd = $this->timestamp_to_ymd($check_out);

        // v2.2.1: Use WPHB defaults (adults=1, children=0)
        return [
            'product_id' => isset($item['product_id']) ? absint($item['product_id']) : 0,
            'checkin' => $checkin_ymd,
            'checkout' => $checkout_ymd,
            'adults' => isset($item['adult_qty']) ? absint($item['adult_qty']) : 1,
            'children' => isset($item['child_qty']) ? absint($item['child_qty']) : 0,
            'currency' => function_exists('hb_get_currency') ? hb_get_currency() : 'EUR',
            'qty' => isset($item['qty']) ? absint($item['qty']) : 1,
        ];
    }

    /**
     * Build price context from cart item
     *
     * @param object      $cart_item Cart item object
     * @param object|null $product   WPHB_Product_Room object
     * @return array Context array
     */
    private function build_price_context_from_cart_item($cart_item, $product = null) {
        // Dates may be strings or timestamps
        $checkin = isset($cart_item->check_in_date) ? $cart_item->check_in_date : '';
        $checkout = isset($cart_item->check_out_date) ? $cart_item->check_out_date : '';

        // Convert if timestamps
        if (is_numeric($checkin)) {
            $checkin = $this->timestamp_to_ymd($checkin);
        }
        if (is_numeric($checkout)) {
            $checkout = $this->timestamp_to_ymd($checkout);
        }

        // Normalize date format: WPHB may use YYYY/MM/DD, we need YYYY-MM-DD
        $checkin = $this->normalize_date_format($checkin);
        $checkout = $this->normalize_date_format($checkout);

        // Get guest counts - v2.2.1: Use WPHB defaults (adults=1, children=0)
        $adults = 1;
        $children = 0;

        if ($product && method_exists($product, 'get_data')) {
            $adults = absint($product->get_data('adult_qty')) ?: 1;
            $children = absint($product->get_data('child_qty')) ?: 0;
        } elseif (isset($cart_item->adult_qty)) {
            $adults = absint($cart_item->adult_qty) ?: 1;
            $children = isset($cart_item->child_qty) ? absint($cart_item->child_qty) : 0;
        }

        return [
            'product_id' => isset($cart_item->product_id) ? absint($cart_item->product_id) : 0,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
            'currency' => function_exists('hb_get_currency') ? hb_get_currency() : 'EUR',
            'qty' => isset($cart_item->quantity) ? absint($cart_item->quantity) : 1,
        ];
    }

    /**
     * Build price context from WPHB product object
     *
     * @param object $product WPHB_Product_Room object
     * @return array Context array
     */
    private function build_price_context_from_product($product) {
        $checkin = '';
        $checkout = '';
        // v2.2.1: Use WPHB defaults (adults=1, children=0)
        $adults = 1;
        $children = 0;
        $product_id = 0;

        if ($product && method_exists($product, 'get_data')) {
            $checkin = $product->get_data('check_in_date') ?: '';
            $checkout = $product->get_data('check_out_date') ?: '';
            $adults = absint($product->get_data('adult_qty')) ?: 1;
            $children = absint($product->get_data('child_qty')) ?: 0;
        }

        if ($product && isset($product->ID)) {
            $product_id = absint($product->ID);
        } elseif ($product && isset($product->post) && isset($product->post->ID)) {
            $product_id = absint($product->post->ID);
        }

        // Convert timestamps if needed
        if (is_numeric($checkin)) {
            $checkin = $this->timestamp_to_ymd($checkin);
        }
        if (is_numeric($checkout)) {
            $checkout = $this->timestamp_to_ymd($checkout);
        }

        return [
            'product_id' => $product_id,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'adults' => $adults,
            'children' => $children,
            'currency' => function_exists('hb_get_currency') ? hb_get_currency() : 'EUR',
            'qty' => 1,
        ];
    }

    // =========================================================================
    // ERROR STATE MANAGEMENT (Transient-based, no $_SESSION)
    // =========================================================================

    /**
     * Get unique key for error state storage
     *
     * @return string Transient key
     */
    private function get_error_state_key() {
        $parts = [];

        // Use cart hash if available
        if (function_exists('hb_get_cart')) {
            $cart = hb_get_cart();
            if ($cart && !empty($cart->cart_contents)) {
                $parts[] = md5(serialize(array_keys($cart->cart_contents)));
            }
        }

        // Add user ID or guest identifier
        if (is_user_logged_in()) {
            $parts[] = 'u' . get_current_user_id();
        } else {
            // For guests, use a cookie-based identifier
            $guest_id = isset($_COOKIE['easyrest_guest_id']) ? sanitize_key($_COOKIE['easyrest_guest_id']) : '';
            if (empty($guest_id)) {
                $guest_id = wp_generate_password(16, false);
                setcookie('easyrest_guest_id', $guest_id, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
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
     * Cleanup error state (called on cart empty, logout, etc.)
     */
    public function cleanup_error_state() {
        $this->clear_error_state();
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Convert Unix timestamp to Y-m-d using WP timezone
     *
     * @param int $timestamp Unix timestamp
     * @return string|null Date string or null if invalid
     */
    private function timestamp_to_ymd($timestamp) {
        if (empty($timestamp) || !is_numeric($timestamp)) {
            return null;
        }

        try {
            $timezone = wp_timezone();
            $datetime = new DateTime('@' . $timestamp);
            $datetime->setTimezone($timezone);
            return $datetime->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Normalize date format to Y-m-d
     *
     * WPHB may provide dates in various formats:
     * - YYYY/MM/DD (slashes)
     * - YYYY-MM-DD (dashes - already correct)
     * - DD/MM/YYYY or other formats
     *
     * @param string $date Date string
     * @return string Normalized date in Y-m-d format, or original if unparseable
     */
    private function normalize_date_format($date) {
        if (empty($date)) {
            return $date;
        }

        // Already in correct format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Convert YYYY/MM/DD to YYYY-MM-DD
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $date, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        // Try to parse with strtotime as fallback
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        // Return original if we can't parse
        return $date;
    }

    /**
     * Safe logging (no PII, no tokens)
     *
     * @param string $message Log message
     * @param string $level   Log level (debug, info, warning, error)
     * @param array  $context Context data (will be sanitized)
     */
    private function log_safe($message, $level = 'info', $context = []) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        // Sanitize context - remove sensitive fields
        $safe_context = [];
        $sensitive_keys = ['token', 'password', 'email', 'phone', 'address', 'name', 'first_name', 'last_name'];

        foreach ($context as $key => $value) {
            if (in_array(strtolower($key), $sensitive_keys, true)) {
                $safe_context[$key] = '[REDACTED]';
            } elseif (is_array($value) || is_object($value)) {
                // For nested structures, serialize for debug visibility
                $safe_context[$key] = wp_json_encode($value);
            } else {
                $safe_context[$key] = $value;
            }
        }

        $log_entry = sprintf(
            '[EasyRest WPHB Bridge] [%s] %s%s',
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
        echo '<p>' . esc_html__('Configure WP Hotel Booking integration. When enabled, room prices will be dynamically fetched from Booking.com via EasyRest.', 'easyrest-core') . '</p>';

        $wphb_active = class_exists('WP_Hotel_Booking') || function_exists('hb_get_cart');
        if (!$wphb_active) {
            echo '<p style="color:orange;"><strong>⚠ ' . esc_html__('WP Hotel Booking plugin is not active.', 'easyrest-core') . '</strong></p>';
        }
    }

    /**
     * Render enabled field
     */
    public static function render_enabled_field() {
        $enabled = get_option(self::OPTION_ENABLED, false);
        $wphb_active = class_exists('WP_Hotel_Booking') || function_exists('hb_get_cart');

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr(self::OPTION_ENABLED) . '" value="1" ' . checked($enabled, true, false);
        if (!$wphb_active) {
            echo ' disabled';
        }
        echo '> ';
        echo esc_html__('Enable dynamic pricing for WP Hotel Booking', 'easyrest-core');
        echo '</label>';

        if (!$wphb_active) {
            echo '<p class="description" style="color:orange;">⚠ ' . esc_html__('WP Hotel Booking plugin must be active.', 'easyrest-core') . '</p>';
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
        echo esc_html__('Fallback (use WPHB native price)', 'easyrest-core');
        echo '</option>';
        echo '</select>';

        echo '<p class="description">';
        echo '<strong>' . esc_html__('Strict:', 'easyrest-core') . '</strong> ' . esc_html__('Prevents checkout if EasyRest price unavailable. No 0€ bookings.', 'easyrest-core') . '<br>';
        echo '<strong>' . esc_html__('Fallback:', 'easyrest-core') . '</strong> ' . esc_html__('Uses WP Hotel Booking calculated price if EasyRest fails.', 'easyrest-core');
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
        echo esc_html__('When enabled, prices are validated before booking creation. In Strict mode, this prevents checkout if pricing fails.', 'easyrest-core');
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
        echo esc_html__('How long to cache WPHB pricing results. Default: 900 seconds (15 minutes). Range: 60-3600 seconds.', 'easyrest-core');
        echo '</p>';
    }
}

// =========================================================================
// PRICE PROVIDER CLASS
// =========================================================================

/**
 * Class EasyRest_WPHB_Price_Provider
 *
 * Centralized price fetching with caching and error handling
 */
class EasyRest_WPHB_Price_Provider {

    /**
     * Bridge reference
     *
     * @var EasyRest_WPHB_Pricing_Bridge
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
     * @param EasyRest_WPHB_Pricing_Bridge $bridge Bridge instance
     */
    public function __construct($bridge) {
        $this->bridge = $bridge;
    }

    /**
     * Get price for given context
     *
     * Implements robust lock handling (v2.1):
     * - When lock is held by another request, retries in a loop up to LOCK_WAIT_MAX_MS
     * - Only fails with lock_timeout after retry loop is exhausted
     * - Distinguishes between cache hits, slow fetches, and true lock timeouts in logs
     *
     * @param array $context Price context (product_id, checkin, checkout, adults, children, currency)
     * @return EasyRest_WPHB_Price_Result Price result object
     */
    public function get_price(array $context) {
        // Validate required fields
        $validation = $this->validate_context($context);
        if ($validation !== true) {
            return EasyRest_WPHB_Price_Result::error('validation_error', $validation);
        }

        // Build cache key
        $cache_key = $this->build_cache_key($context);

        // Check per-request cache first
        if (isset($this->request_cache[$cache_key])) {
            $this->log_debug('Per-request cache hit', $cache_key);
            return $this->request_cache[$cache_key];
        }

        // Check transient cache
        $cached = get_transient(EasyRest_WPHB_Pricing_Bridge::CACHE_PREFIX . $cache_key);
        if ($cached !== false && is_array($cached)) {
            $result = EasyRest_WPHB_Price_Result::from_array($cached);
            $this->request_cache[$cache_key] = $result;
            $this->log_debug('Transient cache hit', $cache_key);
            return $result;
        }

        // Acquire lock to prevent thundering herd
        $lock_key = EasyRest_WPHB_Pricing_Bridge::CACHE_LOCK_PREFIX . $cache_key;
        $lock_acquired = wp_cache_add($lock_key, 1, 'easyrest', EasyRest_WPHB_Pricing_Bridge::LOCK_TTL);

        if (!$lock_acquired) {
            // Another request is fetching - use retry loop (v2.1 improvement)
            $result = $this->wait_for_cache_or_timeout($cache_key);
            if ($result !== null) {
                return $result;
            }

            // Retry loop exhausted - handle based on mode
            $this->log_warning('Lock timeout after retry loop', $cache_key);

            // In fallback mode, return a special error that callers can handle
            // In strict mode, this will propagate as a hard failure
            return EasyRest_WPHB_Price_Result::error(
                'lock_timeout',
                'Price calculation is taking longer than expected. Please try again.'
            );
        }

        try {
            $this->log_debug('Lock acquired, fetching from EasyRest', $cache_key);
            $fetch_start = microtime(true);

            // Fetch from EasyRest
            $result = $this->fetch_from_easyrest($context);

            $fetch_duration_ms = round((microtime(true) - $fetch_start) * 1000);

            // Cache successful results
            if (!$result->is_error()) {
                set_transient(
                    EasyRest_WPHB_Pricing_Bridge::CACHE_PREFIX . $cache_key,
                    $result->to_array(),
                    $this->bridge->get_cache_ttl()
                );
                $this->log_debug("Fetch success ({$fetch_duration_ms}ms), cached", $cache_key);
            } else {
                $this->log_debug("Fetch failed ({$fetch_duration_ms}ms): " . $result->get_error_code(), $cache_key);
            }

            $this->request_cache[$cache_key] = $result;
            return $result;

        } finally {
            // Always release lock
            wp_cache_delete($lock_key, 'easyrest');
        }
    }

    /**
     * Wait for cache to appear (another request is fetching) with retry loop
     *
     * Instead of immediately failing when lock is held, we retry in a loop.
     * This prevents spurious lock_timeout errors in strict mode when the
     * primary request is still fetching but will succeed shortly.
     *
     * @since 2.1.0
     * @param string $cache_key Cache key to check
     * @return EasyRest_WPHB_Price_Result|null Result if cache appeared, null if timeout
     */
    private function wait_for_cache_or_timeout($cache_key) {
        $wait_interval_us = EasyRest_WPHB_Pricing_Bridge::LOCK_WAIT_INTERVAL_MS * 1000; // Convert to microseconds
        $max_wait_us = EasyRest_WPHB_Pricing_Bridge::LOCK_WAIT_MAX_MS * 1000;
        $total_waited_us = 0;
        $retry_count = 0;

        $this->log_debug('Lock held by another request, entering retry loop', $cache_key);

        while ($total_waited_us < $max_wait_us) {
            // Sleep for the interval
            usleep($wait_interval_us);
            $total_waited_us += $wait_interval_us;
            $retry_count++;

            // Check if cache has appeared
            $cached = get_transient(EasyRest_WPHB_Pricing_Bridge::CACHE_PREFIX . $cache_key);
            if ($cached !== false && is_array($cached)) {
                $result = EasyRest_WPHB_Price_Result::from_array($cached);
                $this->request_cache[$cache_key] = $result;

                $waited_ms = round($total_waited_us / 1000);
                $this->log_debug("Cache appeared after {$retry_count} retries ({$waited_ms}ms wait)", $cache_key);

                return $result;
            }
        }

        // Max wait exceeded, cache never appeared
        $waited_ms = round($total_waited_us / 1000);
        $this->log_debug("Retry loop exhausted after {$retry_count} retries ({$waited_ms}ms)", $cache_key);

        return null;
    }

    /**
     * Debug logging helper for price provider
     *
     * @since 2.1.0
     * @param string $message Message
     * @param string $cache_key Cache key for context
     */
    private function log_debug($message, $cache_key) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        // Only log first 8 chars of cache key for brevity
        $short_key = substr($cache_key, 0, 8);
        error_log("[EasyRest WPHB Provider] [DEBUG] [{$short_key}] {$message}");
    }

    /**
     * Warning logging helper for price provider
     *
     * @since 2.1.0
     * @param string $message Message
     * @param string $cache_key Cache key for context
     */
    private function log_warning($message, $cache_key) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        $short_key = substr($cache_key, 0, 8);
        error_log("[EasyRest WPHB Provider] [WARNING] [{$short_key}] {$message}");
    }

    /**
     * Validate context array
     *
     * @param array $context Context array
     * @return true|string True if valid, error message if not
     */
    private function validate_context(array $context) {
        // Required fields
        if (empty($context['checkin']) || empty($context['checkout'])) {
            return 'Missing check-in or check-out date';
        }

        // Date format validation
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $context['checkin'])) {
            return 'Invalid check-in date format';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $context['checkout'])) {
            return 'Invalid check-out date format';
        }

        // Date logic validation
        $checkin_dt = DateTime::createFromFormat('Y-m-d', $context['checkin']);
        $checkout_dt = DateTime::createFromFormat('Y-m-d', $context['checkout']);

        if (!$checkin_dt || !$checkout_dt) {
            return 'Invalid date';
        }

        if ($checkout_dt <= $checkin_dt) {
            return 'Check-out must be after check-in';
        }

        // Nights bounds
        $nights = $checkin_dt->diff($checkout_dt)->days;
        if ($nights > 30) {
            return 'Maximum stay is 30 nights';
        }

        // Guest bounds
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
            'pid' => isset($context['product_id']) ? absint($context['product_id']) : 0,
            'in' => $context['checkin'],
            'out' => $context['checkout'],
            'a' => isset($context['adults']) ? absint($context['adults']) : 1,
            'c' => isset($context['children']) ? absint($context['children']) : 0,
            'cur' => isset($context['currency']) ? strtoupper($context['currency']) : 'EUR',
        ];

        return md5(implode('|', $key_parts));
    }

    /**
     * Fetch price from EasyRest service
     *
     * @param array $context Context array
     * @return EasyRest_WPHB_Price_Result Price result
     */
    private function fetch_from_easyrest(array $context) {
        if (!function_exists('easyrest_get_price')) {
            return EasyRest_WPHB_Price_Result::error('service_unavailable', 'EasyRest service not available');
        }

        $result = easyrest_get_price(
            $context['checkin'],
            $context['checkout'],
            isset($context['adults']) ? $context['adults'] : 1,
            isset($context['children']) ? $context['children'] : 0
        );

        // Check response structure
        if (empty($result) || !is_array($result)) {
            return EasyRest_WPHB_Price_Result::error('invalid_response', 'Invalid response from EasyRest');
        }

        if (empty($result['success'])) {
            $error_code = isset($result['code']) ? $result['code'] : 'fetch_failed';
            $error_msg = isset($result['error']) ? $result['error'] : 'Price not available';
            return EasyRest_WPHB_Price_Result::error($error_code, $error_msg);
        }

        // Extract price - use direct_price (discounted) or booking_price
        $price = 0;
        if (isset($result['direct_price']) && $result['direct_price'] > 0) {
            $price = floatval($result['direct_price']);
        } elseif (isset($result['booking_price']) && $result['booking_price'] > 0) {
            $price = floatval($result['booking_price']);
        }

        if ($price <= 0) {
            return EasyRest_WPHB_Price_Result::error('invalid_price', 'Price must be positive');
        }

        return EasyRest_WPHB_Price_Result::success(
            $price,
            isset($result['currency']) ? $result['currency'] : 'EUR',
            isset($result['meta']['source']) ? $result['meta']['source'] : 'easyrest',
            true // EasyRest prices are tax-inclusive
        );
    }

    /**
     * Invalidate cache for a specific context
     *
     * @param array $context Context array
     */
    public function invalidate_cache(array $context) {
        $cache_key = $this->build_cache_key($context);
        delete_transient(EasyRest_WPHB_Pricing_Bridge::CACHE_PREFIX . $cache_key);
        unset($this->request_cache[$cache_key]);
    }
}

// =========================================================================
// PRICE RESULT CLASS
// =========================================================================

/**
 * Class EasyRest_WPHB_Price_Result
 *
 * Immutable value object for price fetch results
 */
class EasyRest_WPHB_Price_Result {

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
     * @return EasyRest_WPHB_Price_Result
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
     * @return EasyRest_WPHB_Price_Result
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
     * @return EasyRest_WPHB_Price_Result
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
