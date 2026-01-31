<?php
/**
 * Template Name: EasyRest Milan Landing
 *
 * Landing page premium pour l'appartement Milan - JO 2026
 * Design Phase Pro : thème sombre avec accents dorés
 * Mode "landing pure" : header/footer Astra masqués via CSS
 *
 * @package EasyRest_Child
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div id="primary" class="content-area easyrest-milan-landing">
    <main id="main" class="site-main">

        <!-- HERO SECTION -->
        <section class="easyrest-hero">
            <div class="easyrest-hero-content">
                <div class="jo-badge">
                    <span data-fr="JO Milan-Cortina 2026"
                          data-en="Milan-Cortina 2026 Olympics"
                          data-it="Olimpiadi Milano-Cortina 2026"
                          data-es="JO Milán-Cortina 2026"
                          data-pt="JO Milão-Cortina 2026"
                          data-zh="2026米兰-科尔蒂纳奥运会">JO Milan-Cortina 2026</span>
                </div>

                <h1 data-fr="Doux foyer à Milan"
                    data-en="Sweet Home in Milan"
                    data-it="Dolce casa a Milano"
                    data-es="Dulce hogar en Milán"
                    data-pt="Doce lar em Milão"
                    data-zh="米兰温馨之家">Doux foyer à Milan</h1>

                <p class="subtitle"
                   data-fr="Via Giovanni di Breganze 1, 20152 Milan"
                   data-en="Via Giovanni di Breganze 1, 20152 Milan"
                   data-it="Via Giovanni di Breganze 1, 20152 Milano"
                   data-es="Via Giovanni di Breganze 1, 20152 Milán"
                   data-pt="Via Giovanni di Breganze 1, 20152 Milão"
                   data-zh="Via Giovanni di Breganze 1, 20152 米兰">Via Giovanni di Breganze 1, 20152 Milan</p>

                <a href="#easyrest-reservation" class="btn btn-primary"
                   data-fr="Réserver maintenant"
                   data-en="Book Now"
                   data-it="Prenota ora"
                   data-es="Reservar ahora"
                   data-pt="Reserve agora"
                   data-zh="立即预订">Réserver maintenant</a>
            </div>
        </section>

        <!-- Language Selector (sticky pill) -->
        <div class="language-selector">
            <button data-lang="fr" class="active">Français</button>
            <button data-lang="en">English</button>
            <button data-lang="it">Italiano</button>
            <button data-lang="es">Español</button>
            <button data-lang="pt">Português</button>
            <button data-lang="zh">中文</button>
        </div>

        <!-- LATEST GUIDES SECTION -->
        <section class="latest-guides-section" id="easyrest-guides">
            <div class="container">
                <div class="section-head">
                    <h2 class="section-title"
                        data-fr="Derniers guides"
                        data-en="Latest guides"
                        data-it="Ultime guide"
                        data-es="Ultimas guias"
                        data-pt="Ultimos guias"
                        data-zh="最新指南">Derniers guides</h2>
                    <p class="section-subtitle"
                       data-fr="Nos articles récents pour planifier votre séjour et découvrir Milan avant de réserver."
                       data-en="Fresh guides to help plan your stay and discover Milan before you book."
                       data-it="Guide recenti per organizzare il tuo soggiorno e scoprire Milano prima di prenotare."
                       data-es="Guías recientes para planear tu estancia y descubrir Milán antes de reservar."
                       data-pt="Guias recentes para planejar sua estadia e descobrir Milão antes de reservar."
                       data-zh="最新指南帮助您规划行程，了解米兰，再决定预订。">Nos articles récents pour planifier votre séjour et découvrir Milan avant de réserver.</p>
                </div>

                <div class="guides-grid">
                    <?php
                    // Only show guides flagged as having meaningful content.
                    $guides = new WP_Query([
                        'post_type'      => 'easyrest_guide',
                        'posts_per_page' => 3,
                        'post_status'    => 'publish',
                        'no_found_rows'  => true,
                        'meta_query'     => [
                            [
                                'key'   => '_easyrest_has_content',
                                'value' => '1',
                            ],
                        ],
                    ]);

                    if ($guides->have_posts()) :
                        while ($guides->have_posts()) :
                            $guides->the_post();
                            $thumb = get_the_post_thumbnail_url(get_the_ID(), 'easyrest-blog-thumb');
                            $thumb = $thumb ? $thumb : EASYREST_CHILD_URI . '/assets/images/gallery/livingvuensemble.webp';
                            $lang_terms = get_the_terms(get_the_ID(), 'easyrest_lang');
                            $type_terms = get_the_terms(get_the_ID(), 'easyrest_content_type');
                            $lang_label = $lang_terms && !is_wp_error($lang_terms) ? strtoupper($lang_terms[0]->slug) : 'EN';
                            $type_label = $type_terms && !is_wp_error($type_terms) ? $type_terms[0]->name : 'Guide';
                    ?>
                        <article class="guide-card">
                            <a class="guide-thumb" href="<?php the_permalink(); ?>" style="background-image: url('<?php echo esc_url($thumb); ?>');">
                                <span class="guide-badge"><?php echo esc_html($lang_label); ?></span>
                            </a>
                            <div class="guide-body">
                                <div class="guide-meta">
                                    <span class="guide-type"><?php echo esc_html($type_label); ?></span>
                                    <span class="guide-date"><?php echo esc_html(get_the_date('M j, Y')); ?></span>
                                </div>
                                <h3 class="guide-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                                <p class="guide-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 18)); ?></p>
                                <a class="guide-link" href="<?php the_permalink(); ?>">Read guide</a>
                            </div>
                        </article>
                    <?php
                        endwhile;
                        wp_reset_postdata();
                    else :
                    ?>
                        <div class="guides-empty">
                            <p>No guides yet. New articles will appear here soon.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- RESERVATION SECTION (Primary - right after hero) -->
        <section class="reservation-section" id="easyrest-reservation">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Réservez votre séjour"
                    data-en="Book your stay"
                    data-it="Prenota il tuo soggiorno"
                    data-es="Reserve su estancia"
                    data-pt="Reserve sua estadia"
                    data-zh="预订您的住宿">Réservez votre séjour</h2>

                <p class="section-subtitle"
                   data-fr="Sélectionnez vos dates et le nombre de voyageurs pour voir le tarif exact. Réservez en direct et économisez 15% par rapport à Booking.com !"
                   data-en="Select your dates and number of guests to see the exact price. Book direct and save 15% compared to Booking.com!"
                   data-it="Seleziona le date e il numero di ospiti per vedere il prezzo esatto. Prenota direttamente e risparmia il 15% rispetto a Booking.com!"
                   data-es="Seleccione sus fechas y número de huéspedes para ver el precio exacto. ¡Reserve directamente y ahorre un 15% en comparación con Booking.com!"
                   data-pt="Selecione suas datas e número de hóspedes para ver o preço exato. Reserve diretamente e economize 15% em relação ao Booking.com!"
                   data-zh="选择您的日期和旅客人数以查看确切价格。直接预订比Booking.com节省15%！">Sélectionnez vos dates et le nombre de voyageurs pour voir le tarif exact. Réservez en direct et économisez 15% par rapport à Booking.com !</p>

                <div class="booking-form-wrapper">
                    <?php echo do_shortcode('[easyrest_booking]'); ?>
                </div>
            </div>
        </section>

        <!-- GALLERY SECTION -->
        <section class="gallery-section">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Galerie Photos"
                    data-en="Photo Gallery"
                    data-it="Galleria Fotografica"
                    data-es="Galería de Fotos"
                    data-pt="Galeria de Fotos"
                    data-zh="图片库">Galerie Photos</h2>

                <?php
                // ACF Gallery
                if (function_exists('get_field') && $gallery = get_field('easyrest_gallery')) :
                ?>
                    <div class="easyrest-gallery">
                        <?php foreach ($gallery as $index => $image) : ?>
                            <div class="easyrest-gallery-item<?php echo $index === 0 ? ' featured' : ''; ?>">
                                <img src="<?php echo esc_url($image['sizes']['large']); ?>"
                                     alt="<?php echo esc_attr($image['alt']); ?>"
                                     loading="<?php echo $index < 3 ? 'eager' : 'lazy'; ?>"
                                     decoding="async">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <!-- Fallback: use shortcode gallery -->
                    <?php echo do_shortcode('[easyrest_gallery category="all" columns="3"]'); ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- AMENITIES SECTION -->
        <section class="amenities-section">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Équipements"
                    data-en="Amenities"
                    data-it="Servizi"
                    data-es="Comodidades"
                    data-pt="Comodidades"
                    data-zh="设施">Équipements</h2>

                <?php echo do_shortcode('[easyrest_amenities]'); ?>
            </div>
        </section>

        <!-- JO 2026 SECTION -->
        <?php echo do_shortcode('[easyrest_jo_distances]'); ?>

        <!-- CTA FINAL SECTION -->
        <section class="cta-section">
            <div class="container">
                <h2 data-fr="Prêt à réserver ?"
                    data-en="Ready to book?"
                    data-it="Pronto a prenotare?"
                    data-es="¿Listo para reservar?"
                    data-pt="Pronto para reservar?"
                    data-zh="准备预订？">Prêt à réserver ?</h2>

                <p data-fr="Ne manquez pas cette occasion unique de séjourner dans cet appartement idéalement situé pour les Jeux Olympiques d'hiver Milan-Cortina 2026."
                   data-en="Don't miss this unique opportunity to stay in this ideally located apartment for the Milan-Cortina 2026 Winter Olympics."
                   data-it="Non perdere questa opportunità unica di soggiornare in questo appartamento idealmente situato per le Olimpiadi Invernali Milano-Cortina 2026."
                   data-es="No pierdas esta oportunidad única de alojarte en este apartamento idealmente ubicado para los Juegos Olímpicos de Invierno Milán-Cortina 2026."
                   data-pt="Não perca esta oportunidade única de se hospedar neste apartamento idealmente localizado para os Jogos Olímpicos de Inverno Milão-Cortina 2026."
                   data-zh="不要错过这个独特的机会，入住这间位置绝佳的公寓，参加2026年米兰-科尔蒂纳冬季奥运会。">Ne manquez pas cette occasion unique de séjourner dans cet appartement idéalement situé pour les Jeux Olympiques d'hiver Milan-Cortina 2026.</p>

                <a href="#easyrest-reservation" class="btn btn-primary"
                   data-fr="Réserver maintenant"
                   data-en="Book Now"
                   data-it="Prenota ora"
                   data-es="Reservar ahora"
                   data-pt="Reserve agora"
                   data-zh="立即预订">Réserver maintenant</a>
            </div>
        </section>

        <!-- LANDING FOOTER -->
        <footer class="landing-footer">
            <div class="container">
                <p data-fr="© <?php echo date('Y'); ?> EasyRest - Location d'appartement à Milan"
                   data-en="© <?php echo date('Y'); ?> EasyRest - Apartment rental in Milan"
                   data-it="© <?php echo date('Y'); ?> EasyRest - Affitto appartamento a Milano"
                   data-es="© <?php echo date('Y'); ?> EasyRest - Alquiler de apartamento en Milán"
                   data-pt="© <?php echo date('Y'); ?> EasyRest - Aluguel de apartamento em Milão"
                   data-zh="© <?php echo date('Y'); ?> EasyRest - 米兰公寓出租">© <?php echo date('Y'); ?> EasyRest - Location d'appartement à Milan</p>
            </div>
        </footer>

    </main>
</div>

<!-- Language Switch Script -->
<script>
(function(){
    const root = document.querySelector('.easyrest-milan-landing');
    if(!root) return;

    const selector = root.querySelector('.language-selector');
    if(!selector) return;

    const buttons = Array.from(selector.querySelectorAll('button[data-lang]'));
    const supported = new Set(buttons.map(b => b.dataset.lang));

    // Default language
    let lang = 'fr';

    // Check URL param
    const urlLang = new URLSearchParams(window.location.search).get('lang');
    if(urlLang && supported.has(urlLang)) lang = urlLang;

    // Check localStorage
    try {
        const savedLang = localStorage.getItem('easyrest_lang');
        if(savedLang && supported.has(savedLang)) lang = savedLang;
    } catch(e) {}

    // Apply language function
    function applyLang(newLang){
        lang = newLang;

        // Update button states
        buttons.forEach(b => b.classList.toggle('active', b.dataset.lang === lang));

        // Update all elements with data-[lang] attribute
        const all = root.querySelectorAll('[data-fr],[data-en],[data-it],[data-es],[data-pt],[data-zh]');
        all.forEach(el => {
            const val = el.getAttribute('data-' + lang);
            if(val !== null) el.textContent = val;
        });

        // Update html lang attribute
        document.documentElement.setAttribute('lang', lang);

        // Save preference
        try {
            localStorage.setItem('easyrest_lang', lang);
        } catch(e) {}

        // Update URL without reload (optional)
        if(history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.set('lang', lang);
            history.replaceState(null, '', url);
        }

        // Dispatch event for other scripts
        document.dispatchEvent(new CustomEvent('easyrest:languageChange', {
            detail: { lang: lang }
        }));
    }

    // Bind click events
    buttons.forEach(btn => {
        btn.addEventListener('click', () => applyLang(btn.dataset.lang));
    });

    // Smooth scroll for CTA buttons
    root.querySelectorAll('a[href="#easyrest-reservation"]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.getElementById('easyrest-reservation');
            if(target) {
                const offset = 20;
                const pos = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: pos, behavior: 'smooth' });
                if(history.pushState) {
                    history.pushState(null, null, '#easyrest-reservation');
                }
            }
        });
    });

    // Handle direct hash navigation
    if(window.location.hash === '#easyrest-reservation') {
        setTimeout(function() {
            const target = document.getElementById('easyrest-reservation');
            if(target) {
                const pos = target.getBoundingClientRect().top + window.pageYOffset - 20;
                window.scrollTo({ top: pos, behavior: 'smooth' });
            }
        }, 300);
    }

    // Initialize
    applyLang(lang);
})();
</script>

<?php
// get_footer() is called but Astra footer is hidden via CSS
// This maintains WordPress hooks compatibility
get_footer();
