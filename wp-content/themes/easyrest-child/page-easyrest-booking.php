<?php
/**
 * EasyRest Booking Shell Template
 *
 * Used to render booking plugin pages with EasyRest shared header/footer.
 *
 * @package EasyRest_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
get_template_part( 'template-parts/site-header' );
?>

<div id="primary" class="content-area easyrest-booking-shell">
    <main id="main" class="site-main">
        <section class="easyrest-booking-shell__content">
            <div class="container">
                <?php
                if ( have_posts() ) {
                    while ( have_posts() ) {
                        the_post();
                        the_content();
                    }
                }
                ?>
            </div>
        </section>
    </main>
</div>

<?php
get_template_part( 'template-parts/site-footer' );
get_footer();

