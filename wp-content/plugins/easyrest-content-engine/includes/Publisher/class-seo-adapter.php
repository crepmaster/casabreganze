<?php
/**
 * SEO Adapter
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_SEO_Adapter
 *
 * Integrates with SEO plugins (RankMath, Yoast)
 */
class EasyRest_CE_SEO_Adapter {

    /**
     * @var string Detected SEO plugin
     */
    private $seo_plugin;

    /**
     * Constructor
     */
    public function __construct() {
        $this->seo_plugin = $this->detect_seo_plugin();
    }

    /**
     * Detect active SEO plugin
     *
     * @return string|null
     */
    private function detect_seo_plugin(): ?string {
        // RankMath
        if (class_exists('RankMath') || defined('RANK_MATH_VERSION')) {
            return 'rankmath';
        }

        // Yoast SEO
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Meta')) {
            return 'yoast';
        }

        // All in One SEO
        if (defined('AIOSEO_VERSION') || class_exists('AIOSEO\Plugin\AIOSEO')) {
            return 'aioseo';
        }

        // SEOPress
        if (defined('SEOPRESS_VERSION') || function_exists('seopress_init')) {
            return 'seopress';
        }

        return null;
    }

    /**
     * Get detected SEO plugin
     *
     * @return string|null
     */
    public function get_seo_plugin(): ?string {
        return $this->seo_plugin;
    }

    /**
     * Check if SEO plugin is available
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->seo_plugin !== null;
    }

    /**
     * Set SEO meta for a post
     *
     * @param int   $post_id
     * @param array $seo_data
     * @return bool
     */
    public function set_seo_meta(int $post_id, array $seo_data): bool {
        if (empty($seo_data)) {
            return false;
        }

        switch ($this->seo_plugin) {
            case 'rankmath':
                return $this->set_rankmath_meta($post_id, $seo_data);

            case 'yoast':
                return $this->set_yoast_meta($post_id, $seo_data);

            case 'aioseo':
                return $this->set_aioseo_meta($post_id, $seo_data);

            case 'seopress':
                return $this->set_seopress_meta($post_id, $seo_data);

            default:
                // Store in custom meta as fallback
                return $this->set_fallback_meta($post_id, $seo_data);
        }
    }

    /**
     * Set RankMath SEO meta
     *
     * @param int   $post_id
     * @param array $seo_data
     * @return bool
     */
    private function set_rankmath_meta(int $post_id, array $seo_data): bool {
        // Title
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, 'rank_math_title', $seo_data['title']);
        }

        // Meta description
        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, 'rank_math_description', $seo_data['meta_description']);
        }

        // Focus keyword
        if (!empty($seo_data['focus_keyword'])) {
            update_post_meta($post_id, 'rank_math_focus_keyword', $seo_data['focus_keyword']);

            // Secondary keywords
            if (!empty($seo_data['secondary_keywords'])) {
                $keywords = is_array($seo_data['secondary_keywords'])
                    ? implode(',', $seo_data['secondary_keywords'])
                    : $seo_data['secondary_keywords'];

                // RankMath stores focus keywords comma-separated, first is primary
                $all_keywords = $seo_data['focus_keyword'] . ',' . $keywords;
                update_post_meta($post_id, 'rank_math_focus_keyword', $all_keywords);
            }
        }

        // Open Graph
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, 'rank_math_facebook_title', $seo_data['title']);
            update_post_meta($post_id, 'rank_math_twitter_title', $seo_data['title']);
        }

        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, 'rank_math_facebook_description', $seo_data['meta_description']);
            update_post_meta($post_id, 'rank_math_twitter_description', $seo_data['meta_description']);
        }

        // Robots meta
        update_post_meta($post_id, 'rank_math_robots', ['index', 'follow']);

        // Schema - Article
        $schema = [
            '@type'      => 'Article',
            'headline'   => $seo_data['title'] ?? get_the_title($post_id),
            'datePublished' => get_the_date('c', $post_id),
            'dateModified'  => get_the_modified_date('c', $post_id),
        ];

        update_post_meta($post_id, 'rank_math_rich_snippet', 'article');
        update_post_meta($post_id, 'rank_math_snippet_article_type', 'Article');

        return true;
    }

    /**
     * Set Yoast SEO meta
     *
     * @param int   $post_id
     * @param array $seo_data
     * @return bool
     */
    private function set_yoast_meta(int $post_id, array $seo_data): bool {
        // Title
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', $seo_data['title']);
        }

        // Meta description
        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_data['meta_description']);
        }

        // Focus keyword
        if (!empty($seo_data['focus_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $seo_data['focus_keyword']);
        }

        // Open Graph
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $seo_data['title']);
            update_post_meta($post_id, '_yoast_wpseo_twitter-title', $seo_data['title']);
        }

        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $seo_data['meta_description']);
            update_post_meta($post_id, '_yoast_wpseo_twitter-description', $seo_data['meta_description']);
        }

        return true;
    }

    /**
     * Set All in One SEO meta
     *
     * @param int   $post_id
     * @param array $seo_data
     * @return bool
     */
    private function set_aioseo_meta(int $post_id, array $seo_data): bool {
        // AIOSEO uses JSON stored in custom table, but also supports meta
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, '_aioseo_title', $seo_data['title']);
        }

        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, '_aioseo_description', $seo_data['meta_description']);
        }

        // AIOSEO stores data in aioseo_posts table
        if (class_exists('AIOSEO\Plugin\Models\Post')) {
            try {
                $aioseo_post = \AIOSEO\Plugin\Models\Post::getPost($post_id);

                if ($aioseo_post) {
                    if (!empty($seo_data['title'])) {
                        $aioseo_post->title = $seo_data['title'];
                    }
                    if (!empty($seo_data['meta_description'])) {
                        $aioseo_post->description = $seo_data['meta_description'];
                    }
                    if (!empty($seo_data['focus_keyword'])) {
                        $keyphrases = [
                            'focus' => ['keyphrase' => $seo_data['focus_keyword']],
                        ];

                        if (!empty($seo_data['secondary_keywords'])) {
                            foreach ($seo_data['secondary_keywords'] as $i => $kw) {
                                $keyphrases['additional'][] = ['keyphrase' => $kw];
                            }
                        }

                        $aioseo_post->keyphrases = json_encode($keyphrases);
                    }

                    $aioseo_post->save();
                }
            } catch (Exception $e) {
                // Fallback to meta only
            }
        }

        return true;
    }

    /**
     * Set SEOPress meta
     *
     * @param int   $post_id
     * @param array $seo_data
     * @return bool
     */
    private function set_seopress_meta(int $post_id, array $seo_data): bool {
        // Title
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, '_seopress_titles_title', $seo_data['title']);
        }

        // Meta description
        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, '_seopress_titles_desc', $seo_data['meta_description']);
        }

        // Target keyword
        if (!empty($seo_data['focus_keyword'])) {
            $keywords = [$seo_data['focus_keyword']];

            if (!empty($seo_data['secondary_keywords'])) {
                $keywords = array_merge($keywords, (array) $seo_data['secondary_keywords']);
            }

            update_post_meta($post_id, '_seopress_analysis_target_kw', implode(',', $keywords));
        }

        // Social
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, '_seopress_social_fb_title', $seo_data['title']);
            update_post_meta($post_id, '_seopress_social_twitter_title', $seo_data['title']);
        }

        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, '_seopress_social_fb_desc', $seo_data['meta_description']);
            update_post_meta($post_id, '_seopress_social_twitter_desc', $seo_data['meta_description']);
        }

        return true;
    }

    /**
     * Set fallback meta (when no SEO plugin)
     *
     * @param int   $post_id
     * @param array $seo_data
     * @return bool
     */
    private function set_fallback_meta(int $post_id, array $seo_data): bool {
        update_post_meta($post_id, '_easyrest_seo_title', $seo_data['title'] ?? '');
        update_post_meta($post_id, '_easyrest_seo_description', $seo_data['meta_description'] ?? '');
        update_post_meta($post_id, '_easyrest_seo_focus_keyword', $seo_data['focus_keyword'] ?? '');
        update_post_meta($post_id, '_easyrest_seo_secondary_keywords', $seo_data['secondary_keywords'] ?? []);

        return true;
    }

    /**
     * Get SEO meta for a post
     *
     * @param int $post_id
     * @return array
     */
    public function get_seo_meta(int $post_id): array {
        switch ($this->seo_plugin) {
            case 'rankmath':
                return [
                    'title'              => get_post_meta($post_id, 'rank_math_title', true),
                    'meta_description'   => get_post_meta($post_id, 'rank_math_description', true),
                    'focus_keyword'      => explode(',', get_post_meta($post_id, 'rank_math_focus_keyword', true))[0] ?? '',
                ];

            case 'yoast':
                return [
                    'title'              => get_post_meta($post_id, '_yoast_wpseo_title', true),
                    'meta_description'   => get_post_meta($post_id, '_yoast_wpseo_metadesc', true),
                    'focus_keyword'      => get_post_meta($post_id, '_yoast_wpseo_focuskw', true),
                ];

            default:
                return [
                    'title'              => get_post_meta($post_id, '_easyrest_seo_title', true),
                    'meta_description'   => get_post_meta($post_id, '_easyrest_seo_description', true),
                    'focus_keyword'      => get_post_meta($post_id, '_easyrest_seo_focus_keyword', true),
                ];
        }
    }

    /**
     * Calculate SEO score (based on RankMath criteria)
     *
     * @param int   $post_id
     * @param array $seo_data
     * @return int Score 0-100
     */
    public function calculate_score(int $post_id, ?array $seo_data = null): int {
        if (!$seo_data) {
            $seo_data = $this->get_seo_meta($post_id);
        }

        $post    = get_post($post_id);
        $content = $post->post_content;
        $title   = $post->post_title;
        $score   = 0;

        // Focus keyword in title (20 points)
        if (!empty($seo_data['focus_keyword']) && stripos($title, $seo_data['focus_keyword']) !== false) {
            $score += 20;
        }

        // Meta description present and good length (15 points)
        $meta_len = strlen($seo_data['meta_description'] ?? '');
        if ($meta_len >= 120 && $meta_len <= 160) {
            $score += 15;
        } elseif ($meta_len >= 50) {
            $score += 10;
        }

        // Focus keyword in meta description (10 points)
        if (!empty($seo_data['focus_keyword']) && stripos($seo_data['meta_description'] ?? '', $seo_data['focus_keyword']) !== false) {
            $score += 10;
        }

        // Content length (15 points)
        $word_count = str_word_count(strip_tags($content));
        if ($word_count >= 1500) {
            $score += 15;
        } elseif ($word_count >= 1000) {
            $score += 10;
        } elseif ($word_count >= 500) {
            $score += 5;
        }

        // Focus keyword in content (15 points)
        if (!empty($seo_data['focus_keyword'])) {
            $keyword_count = substr_count(strtolower($content), strtolower($seo_data['focus_keyword']));
            if ($keyword_count >= 3) {
                $score += 15;
            } elseif ($keyword_count >= 1) {
                $score += 10;
            }
        }

        // Headings present (10 points)
        if (preg_match('/<h2|<h3/', $content)) {
            $score += 10;
        }

        // Internal links (5 points)
        if (preg_match('/<a[^>]+href=["\'][^"\']*' . preg_quote(home_url(), '/') . '/', $content)) {
            $score += 5;
        }

        // Images with alt (10 points)
        if (preg_match('/<img[^>]+alt=["\'][^"\']+["\']/', $content)) {
            $score += 10;
        }

        return min(100, $score);
    }
}
