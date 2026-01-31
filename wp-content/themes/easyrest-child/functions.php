<?php
/**
 * EasyRest Milan - Astra Child Theme
 * 
 * Lightweight functions.php - Business logic moved to easyrest-core plugin
 *
 * @package EasyRest_Child
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Theme constants
define('EASYREST_CHILD_VERSION', '2.2.0');
define('EASYREST_CHILD_DIR', get_stylesheet_directory());
define('EASYREST_CHILD_URI', get_stylesheet_directory_uri());

/**
 * ============================================================================
 * PLUGIN DEPENDENCY CHECK
 * ============================================================================
 */

/**
 * Check if EasyRest Core plugin is active
 *
 * @return bool
 */
function easyrest_is_plugin_active() {
    return function_exists('easyrest_get_price');
}

/**
 * Show admin notice if plugin is missing
 */
function easyrest_plugin_notice() {
    if (!easyrest_is_plugin_active() && current_user_can('activate_plugins')) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('EasyRest Theme:', 'easyrest-child'); ?></strong>
                <?php esc_html_e('The EasyRest Core plugin is required for full functionality. Please install and activate it.', 'easyrest-child'); ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'easyrest_plugin_notice');

/**
 * ============================================================================
 * ENQUEUE ASSETS
 * ============================================================================
 */

/**
 * Enqueue styles and scripts
 */
function easyrest_child_enqueue_assets() {
    // Parent theme
    wp_enqueue_style(
        'astra-parent-style',
        get_template_directory_uri() . '/style.css'
    );
    
    // Child theme
    wp_enqueue_style(
        'easyrest-child-style',
        get_stylesheet_uri(),
        array('astra-parent-style'),
        EASYREST_CHILD_VERSION
    );
    
    // Google Fonts
    wp_enqueue_style(
        'easyrest-google-fonts',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Open+Sans:wght@400;500;600&display=swap',
        array(),
        null
    );
    
    // Main JS
    wp_enqueue_script(
        'easyrest-main-js',
        EASYREST_CHILD_URI . '/assets/js/main.js',
        array('jquery'),
        EASYREST_CHILD_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'easyrest_child_enqueue_assets');

/**
 * Conditionally load booking script
 */
function easyrest_child_enqueue_booking_script() {
    global $post;
    
    // Only load if plugin is active
    if (!easyrest_is_plugin_active()) {
        return;
    }
    
    // Check if shortcode is present
    $load_script = false;
    
    if (is_a($post, 'WP_Post')) {
        if (has_shortcode($post->post_content, 'easyrest_booking')) {
            $load_script = true;
        }

        $template = get_page_template_slug($post->ID);
        if (strpos($template, 'template-home') !== false) {
            $load_script = true;
        }

        // Load for Milan templates (shortcode is in template file, not post content)
        if (strpos($template, 'page-easyrest-milan') !== false) {
            $load_script = true;
        }
    }
    
    $load_script = apply_filters('easyrest_load_booking_script', $load_script);
    
    if (!$load_script) {
        return;
    }
    
    wp_enqueue_script(
        'easyrest-booking-price',
        EASYREST_CHILD_URI . '/assets/js/booking-price.js',
        array('jquery'),
        EASYREST_CHILD_VERSION,
        true
    );
    
    // Localize script with data from plugin
    wp_localize_script('easyrest-booking-price', 'easyrestConfig', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('easyrest_price_nonce'),
        'currency' => '€',
        'discountPercent' => absint(easyrest_get_option('discount_percent', 15)),
        // WhatsApp: numéro par défaut si non configuré (remplacez par votre numéro)
        'whatsappNumber' => sanitize_text_field(easyrest_get_option('whatsapp_number', '33612345678')),
        'email' => sanitize_email(easyrest_get_option('email', 'contact@easyrest.eu')),
        // URL Booking.com pour réservation
        'bookingUrl' => esc_url(easyrest_get_option('booking_url', 'https://www.booking.com/hotel/it/easyrest-milan.html')),
        'i18n' => array(
            'loading' => esc_html__('Searching for best price...', 'easyrest-child'),
            'error' => esc_html__('Price not available for these dates', 'easyrest-child'),
            'perNight' => esc_html__('/night', 'easyrest-child'),
            'total' => esc_html__('Total', 'easyrest-child'),
            'savings' => esc_html__('You save', 'easyrest-child'),
            'nights' => esc_html__('nights', 'easyrest-child'),
            'night' => esc_html__('night', 'easyrest-child'),
            'selectDates' => esc_html__('Please select dates', 'easyrest-child'),
            'invalidDates' => esc_html__('Check-out must be after check-in', 'easyrest-child'),
            'tooManyRequests' => esc_html__('Too many requests. Please wait.', 'easyrest-child'),
        ),
    ));
}
add_action('wp_enqueue_scripts', 'easyrest_child_enqueue_booking_script', 20);

/**
 * ============================================================================
 * THEME SETUP
 * ============================================================================
 */

/**
 * Theme setup
 */
function easyrest_child_setup() {
    // Translations
    load_child_theme_textdomain('easyrest-child', EASYREST_CHILD_DIR . '/languages');
    
    // Featured images
    add_theme_support('post-thumbnails');
    
    // Image sizes
    add_image_size('easyrest-gallery', 800, 600, true);
    add_image_size('easyrest-gallery-large', 1200, 900, true);
    add_image_size('easyrest-blog-thumb', 640, 360, true);
    add_image_size('easyrest-hero', 1920, 1080, true);
    
    // Menus
    register_nav_menus(array(
        'primary' => esc_html__('Primary Menu', 'easyrest-child'),
        'footer' => esc_html__('Footer Menu', 'easyrest-child'),
    ));
}
add_action('after_setup_theme', 'easyrest_child_setup');

/**
 * ============================================================================
 * SHORTCODES (Visual only - data from plugin)
 * ============================================================================
 */

/**
 * Booking form shortcode
 */
function easyrest_booking_form_shortcode($atts) {
    // Check plugin dependency
    if (!easyrest_is_plugin_active()) {
        return '<p class="easyrest-error">' . esc_html__('EasyRest Core plugin required.', 'easyrest-child') . '</p>';
    }

    $atts = shortcode_atts(array(
        'show_price' => 'yes',
        'button_text' => esc_html__('Check availability', 'easyrest-child'),
    ), $atts, 'easyrest_booking');

    $discount = absint(easyrest_get_option('discount_percent', 15));
    // WhatsApp: utilise le numéro configuré ou un numéro par défaut
    $whatsapp = easyrest_get_option('whatsapp_number', '33612345678'); // Remplacez par votre numéro
    $email = easyrest_get_option('email', 'contact@easyrest.eu');
    // URL Booking.com pour la réservation directe
    $booking_url = easyrest_get_option('booking_url', 'https://www.booking.com/hotel/it/easyrest-milan.html');
    
    ob_start();
    ?>
    <div class="easyrest-booking-form booking-form" id="easyrest-booking-form">
        <h3>
            <span data-fr="Réservez en direct et économisez !"
                  data-en="Book direct and save!"
                  data-it="Prenota diretto e risparmia!"
                  data-es="¡Reserva directo y ahorra!"
                  data-pt="Reserve direto e economize!"
                  data-zh="直接预订并节省！">Book direct and save!</span>
        </h3>
        
        <div class="form-row">
            <div class="form-group">
                <label for="checkin">
                    <span data-fr="Arrivée"
                          data-en="Check-in"
                          data-it="Arrivo"
                          data-es="Llegada"
                          data-pt="Check-in"
                          data-zh="入住">Check-in</span>
                </label>
                <input type="date" id="checkin" name="checkin" required 
                       min="<?php echo esc_attr(date('Y-m-d')); ?>">
            </div>
            <div class="form-group">
                <label for="checkout">
                    <span data-fr="Départ"
                          data-en="Check-out"
                          data-it="Partenza"
                          data-es="Salida"
                          data-pt="Check-out"
                          data-zh="退房">Check-out</span>
                </label>
                <input type="date" id="checkout" name="checkout" required 
                       min="<?php echo esc_attr(date('Y-m-d', strtotime('+1 day'))); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="adults">
                    <span data-fr="Adultes"
                          data-en="Adults"
                          data-it="Adulti"
                          data-es="Adultos"
                          data-pt="Adultos"
                          data-zh="成人">Adults</span>
                </label>
                <select id="adults" name="adults">
                    <option value="1">1</option>
                    <option value="2" selected>2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>
            <div class="form-group">
                <label for="children">
                    <span data-fr="Enfants"
                          data-en="Children"
                          data-it="Bambini"
                          data-es="Niños"
                          data-pt="Crianças"
                          data-zh="儿童">Children</span>
                </label>
                <select id="children" name="children">
                    <option value="0" selected>0</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                </select>
            </div>
        </div>
        
        <?php if ($atts['show_price'] === 'yes') : ?>
        <div class="price-section" id="price-section" style="display: none;">
            <div class="price-loading" id="price-loading">
                <span class="spinner"></span>
                <span data-fr="Recherche du meilleur prix..."
                      data-en="Searching for best price..."
                      data-it="Ricerca del miglior prezzo..."
                      data-es="Buscando el mejor precio..."
                      data-pt="Buscando o melhor preço..."
                      data-zh="正在搜索最佳价格...">Searching for best price...</span>
            </div>
            
            <div class="price-comparison" id="price-result" style="display: none;">
                <div class="price-booking">
                    <span class="label" data-fr="Prix Booking.com :"
                          data-en="Booking.com price:"
                          data-it="Prezzo Booking.com:"
                          data-es="Precio Booking.com:"
                          data-pt="Preço Booking.com:"
                          data-zh="Booking.com 价格：">Booking.com price:</span>
                    <span class="amount" id="booking-price">--</span>
                </div>
                <div class="price-direct">
                    <span class="label" data-fr="Votre prix direct :"
                          data-en="Your direct price:"
                          data-it="Il tuo prezzo diretto:"
                          data-es="Tu precio directo:"
                          data-pt="Seu preço direto:"
                          data-zh="您的直订价格：">Your direct price:</span>
                    <span class="amount" id="direct-price">--</span>
                    <span class="savings" id="savings-badge">-<?php echo esc_html($discount); ?>%</span>
                </div>
                <div class="price-total" id="price-total-row">
                    <span class="label" data-fr="Total séjour :"
                          data-en="Total stay:"
                          data-it="Totale soggiorno:"
                          data-es="Total estancia:"
                          data-pt="Total da estadia:"
                          data-zh="总价：">Total stay:</span>
                    <span class="amount" id="total-price">--</span>
                </div>
            </div>
            
            <div class="price-error" id="price-error" style="display: none;">
                <span data-fr="Prix non disponible pour ces dates"
                      data-en="Price not available for these dates"
                      data-it="Prezzo non disponibile per queste date"
                      data-es="Precio no disponible para estas fechas"
                      data-pt="Preço não disponível para essas datas"
                      data-zh="这些日期无可用价格">Price not available for these dates</span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="button" class="btn btn-primary" id="check-availability">
                <span data-fr="Vérifier la disponibilité"
                      data-en="Check availability"
                      data-it="Verifica disponibilità"
                      data-es="Ver disponibilidad"
                      data-pt="Verificar disponibilidade"
                      data-zh="查看空房">Check availability</span>
            </button>
        </div>
        
        <div class="booking-cta" id="booking-cta" style="display: none;">
            <p data-fr="Disponible ! Réservez maintenant :"
               data-en="Available! Book now:"
               data-it="Disponibile! Prenota ora:"
               data-es="¡Disponible! Reserva ahora:"
               data-pt="Disponível! Reserve agora:"
               data-zh="有空房！立即预订：">Available! Book now:</p>
            <div class="cta-buttons">
                <!-- Bouton WhatsApp -->
                <a href="#" class="btn btn-whatsapp" id="whatsapp-booking" target="_blank" rel="noopener noreferrer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <span data-fr="Réserver via WhatsApp"
                          data-en="Book via WhatsApp"
                          data-it="Prenota via WhatsApp"
                          data-es="Reservar por WhatsApp"
                          data-pt="Reservar via WhatsApp"
                          data-zh="通过 WhatsApp 预订">Book via WhatsApp</span>
                </a>
                <!-- Bouton Booking.com -->
                <a href="<?php echo esc_url($booking_url); ?>" class="btn btn-booking" id="booking-com-btn" target="_blank" rel="noopener noreferrer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                    </svg>
                    <span data-fr="Réserver sur Booking.com"
                          data-en="Book on Booking.com"
                          data-it="Prenota su Booking.com"
                          data-es="Reservar en Booking.com"
                          data-pt="Reservar no Booking.com"
                          data-zh="在 Booking.com 预订">Book on Booking.com</span>
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('easyrest_booking', 'easyrest_booking_form_shortcode');

/**
 * Gallery shortcode
 */
function easyrest_gallery_shortcode($atts) {
    $atts = shortcode_atts(array(
        'category' => 'all',
        'columns' => 3,
        'lightbox' => 'yes',
    ), $atts, 'easyrest_gallery');
    
    $galleries = array(
        'building' => array(
            'entreimmeuble.webp' => esc_html__('Building entrance', 'easyrest-child'),
            'hallimmeuble.webp' => esc_html__('Building hall', 'easyrest-child'),
        ),
        'living' => array(
            'livingvuensemble.webp' => esc_html__('Living room overview', 'easyrest-child'),
            'livingvuensemble_cotemur.webp' => esc_html__('Living room wall side', 'easyrest-child'),
            'livingvuebalcon.webp' => esc_html__('Living room balcony view', 'easyrest-child'),
            'salleamangevueliving.webp' => esc_html__('Dining area', 'easyrest-child'),
            'tableamangerdeface.webp' => esc_html__('Dining table', 'easyrest-child'),
        ),
        'bedroom' => array(
            'chambrevuearmoire.webp' => esc_html__('Bedroom wardrobe view', 'easyrest-child'),
            'chambrevuehaut.webp' => esc_html__('Bedroom top view', 'easyrest-child'),
            'lit.webp' => esc_html__('Double bed', 'easyrest-child'),
            'armoire.webp' => esc_html__('Wardrobe', 'easyrest-child'),
        ),
        'bathroom' => array(
            'salledebainvueentree.webp' => esc_html__('Bathroom', 'easyrest-child'),
            'douche.webp' => esc_html__('Shower', 'easyrest-child'),
            'evier.webp' => esc_html__('Sink', 'easyrest-child'),
        ),
        'kitchen' => array(
            'angle_cuisine.webp' => esc_html__('Kitchen', 'easyrest-child'),
            'cuisinewelcomekit.webp' => esc_html__('Welcome kit', 'easyrest-child'),
            'tableamangervuecuisne.webp' => esc_html__('Kitchen view', 'easyrest-child'),
        ),
    );
    
    $images = array();
    $category = sanitize_key($atts['category']);
    
    if ($category === 'all') {
        foreach ($galleries as $imgs) {
            $images = array_merge($images, $imgs);
        }
    } elseif (isset($galleries[$category])) {
        $images = $galleries[$category];
    }
    
    if (empty($images)) {
        return '';
    }
    
    $columns = min(max(absint($atts['columns']), 1), 6);
    
    ob_start();
    ?>
    <div class="easyrest-gallery" style="--columns: <?php echo esc_attr($columns); ?>">
        <?php foreach ($images as $filename => $alt) : 
            $img_url = EASYREST_CHILD_URI . '/assets/images/gallery/' . sanitize_file_name($filename);
        ?>
            <div class="easyrest-gallery-item">
                <?php if ($atts['lightbox'] === 'yes') : ?>
                <a href="<?php echo esc_url($img_url); ?>" class="lightbox" data-caption="<?php echo esc_attr($alt); ?>">
                <?php endif; ?>
                    <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async">
                <?php if ($atts['lightbox'] === 'yes') : ?>
                </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('easyrest_gallery', 'easyrest_gallery_shortcode');

/**
 * Amenities shortcode
 */
function easyrest_amenities_shortcode($atts) {
    $amenities = array(
        array('icon' => '📐', 'title' => esc_html__('70 m2', 'easyrest-child'), 'desc' => esc_html__('Living space', 'easyrest-child')),
        array('icon' => '👥', 'title' => esc_html__('4 guests', 'easyrest-child'), 'desc' => esc_html__('Maximum capacity', 'easyrest-child')),
        array('icon' => '🛗', 'title' => esc_html__('5th floor', 'easyrest-child'), 'desc' => esc_html__('With elevator', 'easyrest-child')),
        array('icon' => '📶', 'title' => esc_html__('Free WiFi', 'easyrest-child'), 'desc' => esc_html__('High speed', 'easyrest-child')),
        array('icon' => '❄️', 'title' => esc_html__('Air conditioning', 'easyrest-child'), 'desc' => esc_html__('Heat and cool', 'easyrest-child')),
        array('icon' => '📺', 'title' => esc_html__('Flat screen TV', 'easyrest-child'), 'desc' => esc_html__('International channels', 'easyrest-child')),
        array('icon' => '🍳', 'title' => esc_html__('Equipped kitchen', 'easyrest-child'), 'desc' => esc_html__('Oven, microwave, dishwasher', 'easyrest-child')),
        array('icon' => '🧺', 'title' => esc_html__('Washing machine', 'easyrest-child'), 'desc' => esc_html__('In apartment', 'easyrest-child')),
        array('icon' => '🛁', 'title' => esc_html__('Full bathroom', 'easyrest-child'), 'desc' => esc_html__('Bathtub, bidet, towel warmer', 'easyrest-child')),
        array('icon' => '☕', 'title' => esc_html__('Welcome kit', 'easyrest-child'), 'desc' => esc_html__('Coffee, tea, breakfast', 'easyrest-child')),
        array('icon' => '🚭', 'title' => esc_html__('Non-smoking', 'easyrest-child'), 'desc' => esc_html__('Smoke-free apartment', 'easyrest-child')),
        array('icon' => '🅿️', 'title' => esc_html__('Parking', 'easyrest-child'), 'desc' => esc_html__('Easy street parking nearby', 'easyrest-child')),
    );
    
    ob_start();
    ?>
    <div class="amenities-grid">
        <?php foreach ($amenities as $amenity) : ?>
            <div class="amenity-card">
                <div class="amenity-icon" aria-hidden="true"><?php echo esc_html($amenity['icon']); ?></div>
                <div class="amenity-info">
                    <h4><?php echo esc_html($amenity['title']); ?></h4>
                    <p><?php echo esc_html($amenity['desc']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('easyrest_amenities', 'easyrest_amenities_shortcode');

/**
 * Olympics 2026 distances shortcode
 */
function easyrest_jo_distances_shortcode($atts) {
    $venues = array(
        array('name' => 'San Siro', 'sport' => esc_html__('Opening ceremony', 'easyrest-child'), 'time' => '25 min', 'transport' => 'M1 + M5'),
        array('name' => 'Milano Ice Skating Arena', 'sport' => esc_html__('Figure skating & Short track', 'easyrest-child'), 'time' => '35 min', 'transport' => 'M1 + M2'),
        array('name' => 'PalaItalia Santa Giulia', 'sport' => esc_html__('Ice hockey', 'easyrest-child'), 'time' => '40 min', 'transport' => 'M1 + M4'),
        array('name' => 'Rho Ice Hockey Arena', 'sport' => esc_html__('Ice hockey', 'easyrest-child'), 'time' => '30 min', 'transport' => 'M1 to Rho'),
    );
    
    ob_start();
    ?>
    <div class="location-jo">
        <div class="container">
            <h2><?php esc_html_e('Steps away from Olympics 2026', 'easyrest-child'); ?></h2>
            <p><?php esc_html_e('Your apartment ideally located for the Winter Olympics', 'easyrest-child'); ?></p>
            
            <div class="jo-distances">
                <?php foreach ($venues as $venue) : ?>
                    <div class="jo-distance-card">
                        <div class="venue"><?php echo esc_html($venue['name']); ?></div>
                        <div class="sport"><?php echo esc_html($venue['sport']); ?></div>
                        <div class="time"><span aria-hidden="true">🚇</span> <?php echo esc_html($venue['time']); ?></div>
                        <div class="transport"><?php echo esc_html($venue['transport']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('easyrest_jo_distances', 'easyrest_jo_distances_shortcode');

/**
 * Helper function to get plugin options (wrapper)
 */
if (!function_exists('easyrest_get_option')) {
    function easyrest_get_option($key, $default = null) {
        return get_option('easyrest_' . $key, $default);
    }
}

/**
 * ============================================================================
 * MILAN LANDING PAGE ASSETS
 * ============================================================================
 */

/**
 * Enqueue CSS/JS for the Milan landing page template
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_page_template('page-easyrest-milan.php')) {
        return;
    }

    // Milan landing page specific styles
    wp_enqueue_style(
        'easyrest-milan-landing',
        get_stylesheet_directory_uri() . '/assets/css/milan-landing.css',
        array('easyrest-child-style'),
        EASYREST_CHILD_VERSION
    );

    // CTA smooth scroll script
    wp_enqueue_script(
        'easyrest-cta',
        get_stylesheet_directory_uri() . '/assets/js/easyrest-cta.js',
        array(),
        EASYREST_CHILD_VERSION,
        true
    );
});

/**
 * ============================================================================
 * ACF GALLERY FIELD REGISTRATION
 * ============================================================================
 */

/**
 * Register ACF field group for Milan landing page gallery
 * This creates the field if ACF is active
 */
add_action('acf/init', function() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_easyrest_milan_gallery',
        'title' => 'EasyRest Milan - Galerie',
        'fields' => array(
            array(
                'key' => 'field_easyrest_gallery',
                'label' => 'Galerie Photos',
                'name' => 'easyrest_gallery',
                'type' => 'gallery',
                'instructions' => 'Ajoutez les photos de l\'appartement. La première image sera mise en avant.',
                'required' => 0,
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
                'min' => 0,
                'max' => 30,
                'mime_types' => 'jpg, jpeg, png, webp',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'page-easyrest-milan.php',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
    ));
});

/**
 * ============================================================================
 * MILAN CLASSIC TEMPLATE ASSETS
 * ============================================================================
 */

/**
 * Enqueue CSS/JS for the Milan Classic template
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_page_template('page-easyrest-milan-classic.php')) {
        return;
    }

    // Font Awesome for icons
    wp_enqueue_style(
        'font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        array(),
        '6.4.0'
    );

    // Milan Classic specific styles
    wp_enqueue_style(
        'easyrest-milan-classic',
        get_stylesheet_directory_uri() . '/assets/css/milan-classic.css',
        array('easyrest-child-style', 'font-awesome'),
        EASYREST_CHILD_VERSION
    );

    // Milan Classic JavaScript (gallery tabs + maps lazy load)
    wp_enqueue_script(
        'easyrest-milan-classic-js',
        get_stylesheet_directory_uri() . '/assets/js/milan-classic.js',
        array(),
        EASYREST_CHILD_VERSION,
        true
    );

    // Localize script with Google Maps API key if available
    wp_localize_script('easyrest-milan-classic-js', 'easyrestMilanClassic', array(
        'googleMapsApiKey' => easyrest_get_option('google_maps_api_key', ''),
        'apartmentLat' => 45.4572,
        'apartmentLng' => 9.1458,
    ));
});

/**
 * ============================================================================
 * MILAN LANDING PAGE BODY CLASS
 * ============================================================================
 */

/**
 * Add custom body class for Milan landing page templates
 * This allows more reliable CSS targeting
 */
add_filter('body_class', function($classes) {
    if (is_page_template('page-easyrest-milan.php')) {
        $classes[] = 'easyrest-milan-landing-body';
    }
    if (is_page_template('page-easyrest-milan-classic.php')) {
        $classes[] = 'easyrest-milan-classic-body';
    }
    if ( function_exists( 'easyrest_is_guide' ) && easyrest_is_guide() ) {
        $classes[] = 'easyrest-guide-body';
    }
    return $classes;
});
add_action('wp_enqueue_scripts', function () {
  if (is_page_template('page-easyrest-milan.php')) {
    $base = get_stylesheet_directory_uri();

    wp_enqueue_style(
      'easyrest-milan-css',
      $base . '/assets/easyrest/easyrest-milan.css',
      [],
      '1.0.0'
    );

    wp_enqueue_script(
      'easyrest-milan-js',
      $base . '/assets/easyrest/easyrest-milan.js',
      [],
      '1.0.0',
      true
    );
  }
});

/**
 * Helper: check if a post has meaningful content (> 50 visible chars).
 *
 * Two modes:
 *  - Runtime (default): expands shortcodes for accuracy.
 *  - Save context ($save_safe = true): uses strip_shortcodes() to avoid
 *    executing shortcodes outside the loop where global $post may be wrong.
 */
function easyrest_has_meaningful_content( $post_id = null, $save_safe = false ) {
    $post_id = $post_id ? $post_id : easyrest_get_guide_context_id();
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    $raw     = get_post_field( 'post_content', $post_id );
    if ( ! $raw ) {
        return false;
    }
    $text = $save_safe
        ? wp_strip_all_tags( strip_shortcodes( $raw ) )
        : wp_strip_all_tags( do_shortcode( $raw ) );
    $text = trim( preg_replace( '/\s+/', ' ', $text ) );
    $len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
    return $len > 50;
}

/**
 * Store _easyrest_has_content meta on guide save.
 * Skips revisions, autosaves, and unauthorised contexts.
 */
add_action( 'save_post_easyrest_guide', function ( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $has = easyrest_has_meaningful_content( $post_id, true ) ? '1' : '0';
    update_post_meta( $post_id, '_easyrest_has_content', $has );
}, 20 );

/**
 * One-time backfill: populate _easyrest_has_content for existing guides
 * that were published before this meta existed. Runs once in admin.
 */
add_action( 'admin_init', function () {
    if ( get_option( 'easyrest_content_meta_backfilled' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $guides = get_posts([
        'post_type'      => 'easyrest_guide',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_easyrest_has_content',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ]);
    foreach ( $guides as $id ) {
        $has = easyrest_has_meaningful_content( $id, true ) ? '1' : '0';
        update_post_meta( $id, '_easyrest_has_content', $has );
    }
    update_option( 'easyrest_content_meta_backfilled', '1' );
});

/**
 * Helper: resolve the guide post ID in tricky contexts (draft previews, revisions).
 */
function easyrest_get_guide_context_id() {
    $id = get_queried_object_id();
    if ( ! $id ) {
        global $post;
        $id = $post ? $post->ID : 0;
    }
    if ( ! $id ) {
        if ( isset( $_GET['preview_id'] ) ) {
            $id = absint( $_GET['preview_id'] );
        } elseif ( isset( $_GET['p'] ) ) {
            $id = absint( $_GET['p'] );
        }
    }
    if ( $id && get_post_type( $id ) === 'revision' ) {
        $parent_id = wp_get_post_parent_id( $id );
        if ( $parent_id ) {
            $id = $parent_id;
        }
    }
    return $id;
}

/**
 * Helper: detect guide context reliably (including draft previews).
 */
function easyrest_is_guide() {
    if ( is_singular( 'easyrest_guide' ) ) {
        return true;
    }
    $id = easyrest_get_guide_context_id();
    return $id && get_post_type( $id ) === 'easyrest_guide';
}

/**
 * EasyRest guide single assets
 */
add_action('wp_enqueue_scripts', function () {
    if ( ! easyrest_is_guide() ) {
        return;
    }

    wp_enqueue_style(
        'easyrest-guide-post',
        get_stylesheet_directory_uri() . '/assets/css/guide-post.css',
        array('easyrest-child-style'),
        EASYREST_CHILD_VERSION
    );
});
