<?php
/**
 * Internal Linker
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Internal_Linker
 *
 * Adds internal links to generated content
 */
class EasyRest_CE_Internal_Linker {

    /**
     * @var int Maximum links to add
     */
    private $max_links;

    /**
     * @var array Cache of available posts
     */
    private $posts_cache = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->max_links = (int) get_option('easyrest_ce_max_internal_links', 3);
    }

    /**
     * Check if internal linking is enabled
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool) get_option('easyrest_ce_enable_internal_links', true);
    }

    /**
     * Add internal links to content
     *
     * @param string $content
     * @param int    $current_post_id
     * @return string
     */
    public function add_internal_links(string $content, int $current_post_id): string {
        if (!$this->is_enabled() || $this->max_links < 1) {
            return $content;
        }

        // Get available posts for linking
        $available_posts = $this->get_available_posts($current_post_id);

        if (empty($available_posts)) {
            return $content;
        }

        // Find link opportunities
        $opportunities = $this->find_opportunities($content, $available_posts);

        if (empty($opportunities)) {
            return $content;
        }

        // Add links (limit to max)
        $links_added = 0;

        foreach ($opportunities as $opportunity) {
            if ($links_added >= $this->max_links) {
                break;
            }

            $linked_content = $this->add_single_link($content, $opportunity);

            if ($linked_content !== $content) {
                $content = $linked_content;
                $links_added++;
            }
        }

        return $content;
    }

    /**
     * Get available posts for linking
     *
     * @param int $exclude_id
     * @return array
     */
    private function get_available_posts(int $exclude_id = 0): array {
        if ($this->posts_cache === null) {
            $posts = get_posts([
                'post_type'      => ['easyrest_guide', 'post', 'page'],
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);

            $this->posts_cache = [];

            foreach ($posts as $post) {
                // Extract keywords from title
                $keywords = $this->extract_keywords($post->post_title);

                $this->posts_cache[] = [
                    'id'       => $post->ID,
                    'title'    => $post->post_title,
                    'url'      => get_permalink($post->ID),
                    'keywords' => $keywords,
                ];
            }
        }

        // Filter out current post
        return array_filter($this->posts_cache, function ($post) use ($exclude_id) {
            return $post['id'] != $exclude_id;
        });
    }

    /**
     * Extract keywords from title
     *
     * @param string $title
     * @return array
     */
    private function extract_keywords(string $title): array {
        // Remove common words
        $stopwords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
            'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
            'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
            'your', 'our', 'their', 'this', 'that', 'these', 'those', 'how',
            // French
            'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou',
            'pour', 'avec', 'dans', 'sur', 'votre', 'notre',
            // Italian
            'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'e', 'o',
            'per', 'con', 'in', 'su', 'tuo', 'nostro',
            // German
            'der', 'die', 'das', 'ein', 'eine', 'und', 'oder', 'für', 'mit',
            'in', 'auf', 'ihr', 'unser',
        ];

        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9\s-]/', '', $title);
        $words = explode(' ', $title);

        $keywords = [];

        // Single words
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 4 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }

        // Two-word phrases
        for ($i = 0; $i < count($words) - 1; $i++) {
            $phrase = $words[$i] . ' ' . $words[$i + 1];
            if (strlen($phrase) >= 8) {
                $keywords[] = $phrase;
            }
        }

        // Three-word phrases
        for ($i = 0; $i < count($words) - 2; $i++) {
            $phrase = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
            if (strlen($phrase) >= 12) {
                $keywords[] = $phrase;
            }
        }

        return array_unique($keywords);
    }

    /**
     * Find link opportunities in content
     *
     * @param string $content
     * @param array  $available_posts
     * @return array
     */
    private function find_opportunities(string $content, array $available_posts): array {
        $content_lower = strtolower(strip_tags($content));
        $opportunities = [];

        foreach ($available_posts as $post) {
            foreach ($post['keywords'] as $keyword) {
                // Check if keyword exists in content
                if (stripos($content_lower, $keyword) !== false) {
                    // Calculate relevance score
                    $count = substr_count($content_lower, $keyword);
                    $relevance = $count * strlen($keyword);

                    $opportunities[] = [
                        'post'      => $post,
                        'keyword'   => $keyword,
                        'relevance' => $relevance,
                        'count'     => $count,
                    ];
                }
            }
        }

        // Sort by relevance (longer keywords with more occurrences first)
        usort($opportunities, function ($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });

        // Remove duplicates (same post)
        $seen_posts = [];
        $unique_opportunities = [];

        foreach ($opportunities as $opp) {
            if (!in_array($opp['post']['id'], $seen_posts)) {
                $seen_posts[] = $opp['post']['id'];
                $unique_opportunities[] = $opp;
            }
        }

        return $unique_opportunities;
    }

    /**
     * Add a single link to content
     *
     * @param string $content
     * @param array  $opportunity
     * @return string
     */
    private function add_single_link(string $content, array $opportunity): string {
        $keyword = $opportunity['keyword'];
        $url     = $opportunity['post']['url'];
        $title   = esc_attr($opportunity['post']['title']);

        // Find the keyword in content (case-insensitive)
        $pattern = '/(?<![<"\'])(' . preg_quote($keyword, '/') . ')(?![>"\'])/i';

        // Check if keyword is already linked
        if (preg_match('/<a[^>]*>' . preg_quote($keyword, '/') . '<\/a>/i', $content)) {
            return $content;
        }

        // Replace only the first occurrence
        $replacement = '<a href="' . esc_url($url) . '" title="' . $title . '">$1</a>';

        $new_content = preg_replace($pattern, $replacement, $content, 1);

        // Verify link was added
        if ($new_content === null || $new_content === $content) {
            return $content;
        }

        return $new_content;
    }

    /**
     * Get suggested links for content (without applying)
     *
     * @param string $content
     * @param int    $current_post_id
     * @return array
     */
    public function get_suggestions(string $content, int $current_post_id): array {
        $available_posts = $this->get_available_posts($current_post_id);
        $opportunities   = $this->find_opportunities($content, $available_posts);

        return array_slice($opportunities, 0, 10);
    }

    /**
     * Clear posts cache
     */
    public function clear_cache(): void {
        $this->posts_cache = null;
    }

    /**
     * Get link statistics for a post
     *
     * @param int $post_id
     * @return array
     */
    public function get_link_stats(int $post_id): array {
        $post = get_post($post_id);

        if (!$post) {
            return [];
        }

        $content = $post->post_content;

        // Count internal links
        $internal_links = preg_match_all(
            '/<a[^>]+href=["\']' . preg_quote(home_url(), '/') . '[^"\']*["\'][^>]*>/i',
            $content
        );

        // Count external links
        $all_links = preg_match_all('/<a[^>]+href=["\'][^"\']+["\'][^>]*>/i', $content);
        $external_links = $all_links - $internal_links;

        return [
            'internal' => $internal_links,
            'external' => $external_links,
            'total'    => $all_links,
        ];
    }
}
