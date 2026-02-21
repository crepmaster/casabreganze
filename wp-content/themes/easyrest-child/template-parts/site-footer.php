<?php
/**
 * Shared EasyRest site footer
 *
 * Included via get_template_part('template-parts/site-footer') from every
 * custom page template.
 *
 * @package EasyRest_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$year = gmdate( 'Y' );
?>

<footer class="easyrest-site-footer">
    <div class="container">
        <p data-fr="&copy; <?php echo esc_html( $year ); ?> Location d'Appartement &agrave; Milan. Tous droits r&eacute;serv&eacute;s."
           data-en="&copy; <?php echo esc_html( $year ); ?> Milan Apartment Rental. All rights reserved."
           data-it="&copy; <?php echo esc_html( $year ); ?> Affitto Appartamento Milano. Tutti i diritti riservati."
           data-es="&copy; <?php echo esc_html( $year ); ?> Alquiler de Apartamentos en Mil&aacute;n. Todos los derechos reservados."
           data-pt="&copy; <?php echo esc_html( $year ); ?> Aluguer de Apartamentos em Mil&atilde;o. Todos os direitos reservados."
           data-zh="&copy; <?php echo esc_html( $year ); ?> 米兰公寓出租。保留所有权利。">&copy; <?php echo esc_html( $year ); ?> Location d'Appartement &agrave; Milan. Tous droits r&eacute;serv&eacute;s.</p>
    </div>
</footer>
