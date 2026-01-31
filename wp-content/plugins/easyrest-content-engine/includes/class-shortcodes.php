<?php
/**
 * Shortcodes Class
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Shortcodes
 *
 * Handles plugin shortcodes
 */
class EasyRest_CE_Shortcodes {

    /**
     * Register all shortcodes
     */
    public function register(): void {
        add_shortcode('easyrest_booking_cta', [$this, 'booking_cta']);
        add_shortcode('easyrest_jo_distances', [$this, 'jo_distances']);
        add_shortcode('easyrest_venue_info', [$this, 'venue_info']);
    }

    /**
     * Booking CTA shortcode
     *
     * @param array $atts
     * @return string
     */
    public function booking_cta(array $atts): string {
        $atts = shortcode_atts([
            'text'  => '',
            'class' => 'easyrest-cta-button',
            'style' => 'default', // default, compact, banner
        ], $atts, 'easyrest_booking_cta');

        $url     = get_option('easyrest_ce_booking_url', '/reservation/');
        $post_id = get_the_ID();

        // Get context from post meta
        $context      = get_post_meta($post_id, '_easyrest_context', true);
        $content_type = '';

        $terms = get_the_terms($post_id, 'easyrest_content_type');
        if ($terms && !is_wp_error($terms)) {
            $content_type = $terms[0]->slug;
        }

        // Add UTM parameters
        $url = add_query_arg([
            'utm_source'   => 'easyrest',
            'utm_medium'   => 'content',
            'utm_campaign' => $context ?: 'general',
            'utm_content'  => $content_type,
        ], $url);

        // Get language-specific default text
        $lang = $this->get_post_lang($post_id);
        $text = $atts['text'] ?: $this->get_cta_text($lang);

        // Build HTML based on style
        $class = esc_attr($atts['class']);

        switch ($atts['style']) {
            case 'compact':
                $html = sprintf(
                    '<a href="%s" class="%s easyrest-cta-compact">%s</a>',
                    esc_url($url),
                    $class,
                    esc_html($text)
                );
                break;

            case 'banner':
                $html = sprintf(
                    '<div class="easyrest-cta-banner">
                        <div class="easyrest-cta-banner-text">%s</div>
                        <a href="%s" class="%s">%s</a>
                    </div>',
                    esc_html($this->get_banner_text($lang)),
                    esc_url($url),
                    $class,
                    esc_html($text)
                );
                break;

            default:
                $html = sprintf(
                    '<div class="easyrest-cta-wrapper">
                        <a href="%s" class="%s">%s</a>
                    </div>',
                    esc_url($url),
                    $class,
                    esc_html($text)
                );
        }

        return $html;
    }

    /**
     * JO distances shortcode
     *
     * @param array $atts
     * @return string
     */
    public function jo_distances(array $atts): string {
        $atts = shortcode_atts([
            'from' => 'bisceglie',
        ], $atts, 'easyrest_jo_distances');

        $venues = [
            'san_siro' => [
                'name'   => 'San Siro Stadium',
                'sport'  => 'Ceremonies',
                'time'   => '25-30 min',
                'method' => 'M1 + M5',
            ],
            'rho_arena' => [
                'name'   => 'Rho Ice Hockey Arena',
                'sport'  => 'Ice Hockey',
                'time'   => '30 min',
                'method' => 'M1 direct',
            ],
            'assago_forum' => [
                'name'   => 'Mediolanum Forum',
                'sport'  => 'Figure Skating, Short Track',
                'time'   => '35-40 min',
                'method' => 'M1 + M2',
            ],
            'santa_giulia' => [
                'name'   => 'PalaItalia Santa Giulia',
                'sport'  => 'Ice Hockey',
                'time'   => '40-45 min',
                'method' => 'M1 + M4',
            ],
        ];

        $html = '<div class="easyrest-distances">';
        $html .= '<table class="easyrest-distances-table">';
        $html .= '<thead><tr><th>Venue</th><th>Sport</th><th>Travel Time</th><th>Transport</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($venues as $key => $venue) {
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($venue['name']),
                esc_html($venue['sport']),
                esc_html($venue['time']),
                esc_html($venue['method'])
            );
        }

        $html .= '</tbody></table>';
        $html .= '<p class="easyrest-distances-note"><em>From Bisceglie metro station (M1)</em></p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Venue info shortcode
     *
     * @param array $atts
     * @return string
     */
    public function venue_info(array $atts): string {
        $atts = shortcode_atts([
            'venue' => '',
        ], $atts, 'easyrest_venue_info');

        if (empty($atts['venue'])) {
            return '';
        }

        $venues = $this->get_venue_data();

        if (!isset($venues[$atts['venue']])) {
            return '';
        }

        $venue = $venues[$atts['venue']];

        $html = '<div class="easyrest-venue-info">';
        $html .= sprintf('<h4>%s</h4>', esc_html($venue['name']));
        $html .= sprintf('<p><strong>Sport:</strong> %s</p>', esc_html($venue['sport']));
        $html .= sprintf('<p><strong>Travel time from West Milan:</strong> %s</p>', esc_html($venue['travel_time']));
        $html .= sprintf('<p><strong>Transport:</strong> %s</p>', esc_html($venue['transport']));
        $html .= '</div>';

        return $html;
    }

    /**
     * Get post language
     *
     * @param int $post_id
     * @return string
     */
    private function get_post_lang(int $post_id): string {
        $terms = get_the_terms($post_id, 'easyrest_lang');

        if ($terms && !is_wp_error($terms)) {
            return $terms[0]->slug;
        }

        return 'en';
    }

    /**
     * Get CTA button text by language
     *
     * @param string $lang
     * @return string
     */
    private function get_cta_text(string $lang): string {
        $texts = [
            'en' => 'Book your apartment now',
            'fr' => 'Réservez votre appartement maintenant',
            'it' => 'Prenota il tuo appartamento ora',
            'de' => 'Buchen Sie jetzt Ihre Wohnung',
            'es' => 'Reserve su apartamento ahora',
        ];

        return $texts[$lang] ?? $texts['en'];
    }

    /**
     * Get banner text by language
     *
     * @param string $lang
     * @return string
     */
    private function get_banner_text(string $lang): string {
        $texts = [
            'en' => 'Save 15% by booking direct - no commission fees!',
            'fr' => 'Économisez 15% en réservant en direct - sans commission !',
            'it' => 'Risparmia il 15% prenotando direttamente - senza commissioni!',
            'de' => 'Sparen Sie 15% bei Direktbuchung - keine Provision!',
            'es' => '¡Ahorre 15% reservando directo - sin comisiones!',
        ];

        return $texts[$lang] ?? $texts['en'];
    }

    /**
     * Get venue data
     *
     * @return array
     */
    private function get_venue_data(): array {
        return [
            'san_siro' => [
                'name'        => 'San Siro Stadium',
                'name_local'  => 'Stadio Giuseppe Meazza',
                'sport'       => 'Ceremonies',
                'travel_time' => '25-30 min',
                'transport'   => 'M1 → Lotto → M5 San Siro Stadio',
            ],
            'rho_arena' => [
                'name'        => 'Rho Ice Hockey Arena',
                'sport'       => 'Ice Hockey',
                'travel_time' => '30 min',
                'transport'   => 'M1 direct to Rho Fiera',
            ],
            'assago_forum' => [
                'name'        => 'Milano Ice Skating Arena',
                'name_local'  => 'Mediolanum Forum',
                'sport'       => 'Figure Skating, Short Track',
                'travel_time' => '35-40 min',
                'transport'   => 'M1 + M2 or Bus 321',
            ],
            'santa_giulia' => [
                'name'        => 'PalaItalia Santa Giulia',
                'sport'       => 'Ice Hockey',
                'travel_time' => '40-45 min',
                'transport'   => 'M1 + M4',
            ],
        ];
    }
}
