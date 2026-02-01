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

<div id="primary" class="<?php echo esc_attr($wrapper_class); ?>">
    <main id="main" class="site-main">

        <!-- Minimal guide header (Astra header is hidden via CSS) -->
        <nav class="guide-nav">
            <div class="container">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="guide-nav-home">
                    ← <?php esc_html_e( 'Home', 'easyrest-child' ); ?>
                </a>
            </div>
        </nav>

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
                            <h3><?php esc_html_e('Book EasyRest Milan', 'easyrest-child'); ?></h3>
                            <p><?php esc_html_e('Check availability and book direct in a few clicks.', 'easyrest-child'); ?></p>
                            <a class="btn btn-primary" href="<?php echo esc_url(home_url('/?lang=fr#easyrest-booking')); ?>">
                                <?php esc_html_e('Check availability', 'easyrest-child'); ?>
                            </a>
                        </div>
                    </aside>
                </div>
                <?php else : ?>
                <div class="guide-content guide-content--empty container">
                    <div class="guide-coming-soon">
                        <p><?php esc_html_e('This guide is coming soon. Check back shortly for the full article.', 'easyrest-child'); ?></p>
                        <a class="btn btn-primary" href="<?php echo esc_url(home_url('/?lang=fr#easyrest-booking')); ?>">
                            <?php esc_html_e('Check availability', 'easyrest-child'); ?>
                        </a>
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
                                Photo by
                                <?php if ($credit_url) : ?>
                                    <a href="<?php echo esc_url($credit_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($credit_name); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($credit_name); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($source_name) : ?>
                                <?php echo $credit_name ? ' on ' : 'Photo on '; ?>
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
                    <h2 class="section-title"><?php esc_html_e('More guides', 'easyrest-child'); ?></h2>
                    <div class="guides-grid">
                        <?php
                        $more_guides = new WP_Query([
                            'post_type'      => 'easyrest_guide',
                            'posts_per_page' => 3,
                            'post__not_in'   => [get_the_ID()],
                            'post_status'    => 'publish',
                            'no_found_rows'  => true,
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
                                    <a class="guide-link" href="<?php the_permalink(); ?>">
                                        <?php esc_html_e('Read guide', 'easyrest-child'); ?>
                                    </a>
                                </div>
                            </article>
                        <?php
                            endwhile;
                            wp_reset_postdata();
                        else :
                        ?>
                            <div class="guides-empty">
                                <p><?php esc_html_e('No guides yet. New articles will appear here soon.', 'easyrest-child'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endwhile; endif; ?>
    </main>
</div>

<?php
get_footer();
