<?php
/**
 * Template Name: EasyRest Guides
 *
 * Guides archive-style page (works even if /guides/ archive is not available).
 *
 * @package EasyRest_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$paged = get_query_var('paged');
if (!$paged) {
    $paged = get_query_var('page');
}
if (!$paged) {
    $paged = 1;
}
?>

<div id="primary" class="content-area easyrest-guides-page">
    <main id="main" class="site-main">
        <section class="guides-hero">
            <div class="container">
                <h1>Guides</h1>
                <p>Discover Milan tips, events, and weekly updates.</p>
            </div>
        </section>

        <section class="guides-list">
            <div class="container">
                <div class="guides-grid">
                    <?php
                    $guides = new WP_Query([
                        'post_type'      => 'easyrest_guide',
                        'posts_per_page' => 9,
                        'post_status'    => 'publish',
                        'paged'          => $paged,
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
                                <p class="guide-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 24)); ?></p>
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

                <?php if ($guides->max_num_pages > 1) : ?>
                    <div class="guides-pagination">
                        <?php
                        echo paginate_links([
                            'total'   => $guides->max_num_pages,
                            'current' => $paged,
                        ]);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<?php
get_footer();
?>
