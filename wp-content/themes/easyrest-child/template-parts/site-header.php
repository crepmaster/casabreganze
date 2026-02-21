<?php
/**
 * Shared EasyRest site header
 *
 * Included via get_template_part('template-parts/site-header') from every
 * custom page template (milan-classic, guide posts, guides archive).
 *
 * @package EasyRest_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$logo_url   = EASYREST_CHILD_URI . '/assets/images/gallery/logo esayrest.png';
$home_url   = home_url( '/' );
$guides_url = home_url( '/guides/' );

// Book link: local anchor on homepage, full URL elsewhere.
$is_homepage = is_page_template( 'page-easyrest-milan-classic.php' );
$book_url    = $is_homepage ? '#easyrest-booking' : home_url( '/#easyrest-booking' );
?>

<header class="easyrest-site-header" id="easyrest-site-header">
    <div class="container">
        <a href="<?php echo esc_url( $home_url ); ?>" class="easyrest-site-logo">
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="EasyRest" onerror="this.style.display='none'">
            <span class="easyrest-site-logo-text">EasyRest</span>
        </a>

        <button class="easyrest-menu-toggle" id="easyrest-menu-toggle"
                aria-label="<?php esc_attr_e( 'Toggle menu', 'easyrest-child' ); ?>"
                aria-expanded="false">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <nav class="easyrest-header-nav" id="easyrest-header-nav">
            <ul class="easyrest-nav-menu">
                <li>
                    <a href="<?php echo esc_url( $home_url ); ?>"
                       data-fr="Accueil" data-en="Home" data-it="Home"
                       data-es="Inicio" data-pt="Início" data-zh="首页">Accueil</a>
                </li>
                <li>
                    <a href="<?php echo esc_url( $guides_url ); ?>"
                       data-fr="Découvrir Milan" data-en="Enjoy Milan" data-it="Scopri Milano"
                       data-es="Descubrir Milán" data-pt="Descubra Milão" data-zh="探索米兰">Découvrir Milan</a>
                </li>
                <li>
                    <a href="<?php echo esc_url( $book_url ); ?>"
                       data-fr="Réserver" data-en="Book" data-it="Prenota"
                       data-es="Reservar" data-pt="Reservar" data-zh="预订">Réserver</a>
                </li>
            </ul>

            <div class="easyrest-language-selector">
                <button data-lang="fr" class="active">FR</button>
                <button data-lang="en">EN</button>
                <button data-lang="it">IT</button>
                <button data-lang="es">ES</button>
                <button data-lang="pt">PT</button>
                <button data-lang="zh">中文</button>
            </div>
        </nav>
    </div>
</header>
