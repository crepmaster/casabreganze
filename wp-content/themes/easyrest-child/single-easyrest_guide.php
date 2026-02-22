<?php
/**
 * Single EasyRest Guide
 *
 * @package EasyRest_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$thumb = get_the_post_thumbnail_url(get_the_ID(), 'easyrest-hero');
$thumb = $thumb ? $thumb : EASYREST_CHILD_URI . '/assets/images/gallery/livingvuensemble.webp';

$lang_terms = get_the_terms(get_the_ID(), 'easyrest_lang');
$type_terms = get_the_terms(get_the_ID(), 'easyrest_content_type');
$lang_label = $lang_terms && !is_wp_error($lang_terms) ? strtoupper($lang_terms[0]->slug) : 'EN';
$type_label = $type_terms && !is_wp_error($type_terms) ? $type_terms[0]->name : esc_html__('Guide', 'easyrest-child');

// Determine if the post has meaningful content (> 50 visible chars).
$has_content = easyrest_has_meaningful_content( get_the_ID() );
$wrapper_class = 'content-area easyrest-guide-post' . ($has_content ? '' : ' easyrest-guide--short');
?>

<?php get_template_part( 'template-parts/site-header' ); ?>

<div id="primary" class="<?php echo esc_attr($wrapper_class); ?>">
    <main id="main" class="site-main">

        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <article <?php post_class('guide-article'); ?>>
                <header class="guide-hero" style="background-image: url('<?php echo esc_url($thumb); ?>');">
                    <div class="guide-hero-inner">
                        <div class="guide-meta">
                            <span class="guide-type"><?php echo esc_html($type_label); ?></span>
                            <span class="guide-dot">•</span>
                            <span class="guide-lang"><?php echo esc_html($lang_label); ?></span>
                            <span class="guide-dot">•</span>
                            <span class="guide-date"><?php echo esc_html(get_the_date('F j, Y')); ?></span>
                        </div>
                        <h1 class="guide-title"><?php the_title(); ?></h1>
                        <?php if (has_excerpt()) : ?>
                            <p class="guide-deck"><?php echo esc_html(get_the_excerpt()); ?></p>
                        <?php endif; ?>
                    </div>
                </header>
                <?php if ($has_content) : ?>
                <div class="guide-content container">
                    <div class="guide-body-content">
                        <?php the_content(); ?>
                    </div>

                    <aside class="guide-aside">
                        <div class="guide-cta-card">
                            <h3 data-fr="Réservez EasyRest Milan"
                                data-en="Book EasyRest Milan"
                                data-it="Prenota EasyRest Milano"
                                data-es="Reserva EasyRest Milán"
                                data-pt="Reserve EasyRest Milão"
                                data-zh="预订 EasyRest 米兰">Réservez EasyRest Milan</h3>
                            <p data-fr="Vérifiez la disponibilité et réservez en direct en quelques clics."
                               data-en="Check availability and book direct in a few clicks."
                               data-it="Verifica la disponibilità e prenota direttamente in pochi clic."
                               data-es="Consulta disponibilidad y reserva directo en pocos clics."
                               data-pt="Verifique a disponibilidade e reserve diretamente em poucos cliques."
                               data-zh="查看空房并直接预订，仅需几步。">Vérifiez la disponibilité et réservez en direct en quelques clics.</p>
                            <a class="btn btn-primary" href="<?php echo esc_url(home_url('/#easyrest-booking')); ?>"
                               data-fr="Vérifier la disponibilité"
                               data-en="Check availability"
                               data-it="Verifica disponibilità"
                               data-es="Ver disponibilidad"
                               data-pt="Verificar disponibilidade"
                               data-zh="查看空房">Vérifier la disponibilité</a>
                        </div>
                    </aside>
                </div>
                <?php else : ?>
                <div class="guide-content guide-content--empty container">
                    <div class="guide-coming-soon">
                        <p data-fr="Ce guide arrive bientôt. Revenez prochainement pour l'article complet."
                           data-en="This guide is coming soon. Check back shortly for the full article."
                           data-it="Questa guida sarà disponibile a breve. Torna presto per l'articolo completo."
                           data-es="Esta guía estará disponible pronto. Vuelve pronto para el artículo completo."
                           data-pt="Este guia estará disponível em breve. Volte em breve para o artigo completo."
                           data-zh="本指南即将推出，请稍后回来查看完整文章。">Ce guide arrive bientôt. Revenez prochainement pour l'article complet.</p>
                        <a class="btn btn-primary" href="<?php echo esc_url(home_url('/#easyrest-booking')); ?>"
                           data-fr="Vérifier la disponibilité"
                           data-en="Check availability"
                           data-it="Verifica disponibilità"
                           data-es="Ver disponibilidad"
                           data-pt="Verificar disponibilidade"
                           data-zh="查看空房">Vérifier la disponibilité</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                $credit_name = get_post_meta(get_the_ID(), '_easyrest_image_credit_name', true);
                $credit_url  = get_post_meta(get_the_ID(), '_easyrest_image_credit_url', true);
                $source_name = get_post_meta(get_the_ID(), '_easyrest_image_source', true);
                $source_url  = get_post_meta(get_the_ID(), '_easyrest_image_source_url', true);
                ?>
                <?php if ($credit_name || $source_name) : ?>
                    <div class="guide-image-credit container">
                        <small>
                            <?php if ($credit_name) : ?>
                                <span data-fr="Photo par" data-en="Photo by" data-it="Foto di"
                                      data-es="Foto de" data-pt="Foto de" data-zh="照片由">Photo par</span>
                                <?php if ($credit_url) : ?>
                                    <a href="<?php echo esc_url($credit_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($credit_name); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($credit_name); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($source_name) : ?>
                                <?php if ($credit_name) : ?>
                                    <span data-fr="sur" data-en="on" data-it="su"
                                          data-es="en" data-pt="no" data-zh="来自">sur</span>
                                <?php else : ?>
                                    <span data-fr="Photo sur" data-en="Photo on" data-it="Foto su"
                                          data-es="Foto en" data-pt="Foto no" data-zh="照片来自">Photo sur</span>
                                <?php endif; ?>
                                <?php if ($source_url) : ?>
                                    <a href="<?php echo esc_url($source_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($source_name); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($source_name); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </article>

            <section class="guide-more">
                <div class="container">
                    <h2 class="section-title"
                        data-fr="Plus de guides"
                        data-en="More guides"
                        data-it="Altre guide"
                        data-es="Más guías"
                        data-pt="Mais guias"
                        data-zh="更多指南">Plus de guides</h2>
                    <div class="guides-grid">
                        <?php
                        $more_guides = new WP_Query([
                            'post_type'      => 'easyrest_guide',
                            'posts_per_page' => 3,
                            'post__not_in'   => [get_the_ID()],
                            'post_status'    => 'publish',
                            'no_found_rows'  => true,
                            'meta_query'     => [
                                [
                                    'key'   => '_easyrest_has_content',
                                    'value' => '1',
                                ],
                            ],
                        ]);

                        if ($more_guides->have_posts()) :
                            while ($more_guides->have_posts()) :
                                $more_guides->the_post();
                                $thumb = get_the_post_thumbnail_url(get_the_ID(), 'easyrest-blog-thumb');
                                $thumb = $thumb ? $thumb : EASYREST_CHILD_URI . '/assets/images/gallery/livingvuensemble.webp';
                                $lang_terms = get_the_terms(get_the_ID(), 'easyrest_lang');
                                $type_terms = get_the_terms(get_the_ID(), 'easyrest_content_type');
                                $lang_label = $lang_terms && !is_wp_error($lang_terms) ? strtoupper($lang_terms[0]->slug) : 'EN';
                                $type_label = $type_terms && !is_wp_error($type_terms) ? $type_terms[0]->name : esc_html__('Guide', 'easyrest-child');
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
                                    <a class="guide-link" href="<?php the_permalink(); ?>"
                                       data-fr="Lire le guide" data-en="Read guide" data-it="Leggi la guida"
                                       data-es="Leer guía" data-pt="Ler guia" data-zh="阅读指南">Lire le guide</a>
                                </div>
                            </article>
                        <?php
                            endwhile;
                            wp_reset_postdata();
                        else :
                        ?>
                            <div class="guides-empty">
                                <p data-fr="Pas encore de guides. De nouveaux articles apparaîtront bientôt."
                                   data-en="No guides yet. New articles will appear here soon."
                                   data-it="Nessuna guida ancora. Nuovi articoli appariranno presto."
                                   data-es="No hay guías todavía. Nuevos artículos aparecerán pronto."
                                   data-pt="Ainda sem guias. Novos artigos aparecerão em breve."
                                   data-zh="暂无指南。新文章即将推出。">Pas encore de guides. De nouveaux articles apparaîtront bientôt.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endwhile; endif; ?>
    </main>
</div>

<?php get_template_part( 'template-parts/site-footer' ); ?>

<?php
get_footer();
