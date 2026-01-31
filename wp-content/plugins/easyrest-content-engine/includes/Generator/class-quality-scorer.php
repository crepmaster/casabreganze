<?php
/**
 * Quality Scorer
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Quality_Scorer
 *
 * Scores generated content quality (0-100)
 */
class EasyRest_CE_Quality_Scorer {

    /**
     * @var array Scoring weights
     */
    private $weights = [
        'word_count'     => 15,
        'structure'      => 20,
        'seo'            => 20,
        'shortcodes'     => 15,
        'readability'    => 15,
        'uniqueness'     => 15,
    ];

    /**
     * Score content
     *
     * @param array $content Generated content array
     * @param array $config  Expected configuration
     * @return array ['score' => int, 'breakdown' => array, 'suggestions' => array]
     */
    public function score(array $content, array $config = []): array {
        $breakdown   = [];
        $suggestions = [];

        // Word count score
        $wc_result = $this->score_word_count($content, $config);
        $breakdown['word_count'] = $wc_result['score'];
        if (!empty($wc_result['suggestion'])) {
            $suggestions[] = $wc_result['suggestion'];
        }

        // Structure score
        $struct_result = $this->score_structure($content);
        $breakdown['structure'] = $struct_result['score'];
        $suggestions = array_merge($suggestions, $struct_result['suggestions']);

        // SEO score
        $seo_result = $this->score_seo($content);
        $breakdown['seo'] = $seo_result['score'];
        $suggestions = array_merge($suggestions, $seo_result['suggestions']);

        // Shortcodes score
        $sc_result = $this->score_shortcodes($content);
        $breakdown['shortcodes'] = $sc_result['score'];
        $suggestions = array_merge($suggestions, $sc_result['suggestions']);

        // Readability score
        $read_result = $this->score_readability($content);
        $breakdown['readability'] = $read_result['score'];
        $suggestions = array_merge($suggestions, $read_result['suggestions']);

        // Uniqueness score (check for common AI patterns)
        $unique_result = $this->score_uniqueness($content);
        $breakdown['uniqueness'] = $unique_result['score'];
        $suggestions = array_merge($suggestions, $unique_result['suggestions']);

        // Calculate weighted total
        $total = 0;
        foreach ($breakdown as $category => $score) {
            $weight = $this->weights[$category] ?? 10;
            $total += ($score / 100) * $weight;
        }

        return [
            'score'       => (int) round($total),
            'breakdown'   => $breakdown,
            'suggestions' => array_unique($suggestions),
            'grade'       => $this->get_grade($total),
        ];
    }

    /**
     * Score word count
     *
     * @param array $content
     * @param array $config
     * @return array
     */
    private function score_word_count(array $content, array $config): array {
        $word_count = $content['word_count'] ?? 0;
        $min_words  = $config['word_count'][0] ?? 1000;
        $max_words  = $config['word_count'][1] ?? 1800;
        $target     = ($min_words + $max_words) / 2;

        $suggestion = null;

        if ($word_count < $min_words * 0.5) {
            $score      = 20;
            $suggestion = "Content too short ({$word_count} words). Target: {$min_words}-{$max_words} words.";
        } elseif ($word_count < $min_words * 0.7) {
            $score      = 40;
            $suggestion = "Content is short. Consider adding more detail.";
        } elseif ($word_count < $min_words) {
            $score      = 70;
            $suggestion = "Content slightly below target word count.";
        } elseif ($word_count <= $max_words) {
            $score = 100;
        } elseif ($word_count <= $max_words * 1.2) {
            $score      = 90;
            $suggestion = "Content slightly longer than target.";
        } else {
            $score      = 70;
            $suggestion = "Content too long. Consider trimming.";
        }

        return ['score' => $score, 'suggestion' => $suggestion];
    }

    /**
     * Score content structure
     *
     * @param array $content
     * @return array
     */
    private function score_structure(array $content): array {
        $body        = $content['body'] ?? '';
        $score       = 100;
        $suggestions = [];

        // Check for H2 headings
        $h2_count = preg_match_all('/<h2|## /', $body);
        if ($h2_count < 2) {
            $score -= 30;
            $suggestions[] = 'Add more H2 headings to improve structure';
        } elseif ($h2_count < 3) {
            $score -= 10;
        }

        // Check for H3 subheadings
        $h3_count = preg_match_all('/<h3|### /', $body);
        if ($h3_count < 1) {
            $score -= 15;
            $suggestions[] = 'Consider adding H3 subheadings for better hierarchy';
        }

        // Check for lists
        $has_lists = preg_match('/<[uo]l|^[-*]\s/m', $body);
        if (!$has_lists) {
            $score -= 15;
            $suggestions[] = 'Add bullet points or numbered lists for scannability';
        }

        // Check for paragraphs (not one big block)
        $para_count = substr_count($body, '</p>') + preg_match_all('/\n\n/', $body);
        if ($para_count < 5) {
            $score -= 20;
            $suggestions[] = 'Break content into more paragraphs';
        }

        // Check paragraph length (avoid walls of text)
        $paragraphs = preg_split('/<\/p>|\n\n/', $body);
        $long_paras = 0;
        foreach ($paragraphs as $para) {
            if (str_word_count(strip_tags($para)) > 150) {
                $long_paras++;
            }
        }
        if ($long_paras > 2) {
            $score -= 10;
            $suggestions[] = 'Some paragraphs are too long. Consider splitting them.';
        }

        return ['score' => max(0, $score), 'suggestions' => $suggestions];
    }

    /**
     * Score SEO elements
     *
     * @param array $content
     * @return array
     */
    private function score_seo(array $content): array {
        $score       = 100;
        $suggestions = [];
        $seo         = $content['seo'] ?? [];

        // Title
        if (empty($content['title'])) {
            $score -= 30;
            $suggestions[] = 'Missing title';
        } else {
            $title_len = strlen($content['title']);
            if ($title_len < 30) {
                $score -= 15;
                $suggestions[] = 'Title too short for SEO';
            } elseif ($title_len > 60) {
                $score -= 10;
                $suggestions[] = 'Title may be truncated in search results (>60 chars)';
            }
        }

        // Meta description
        if (empty($seo['meta_description'])) {
            $score -= 25;
            $suggestions[] = 'Missing meta description';
        } else {
            $meta_len = strlen($seo['meta_description']);
            if ($meta_len < 120) {
                $score -= 10;
                $suggestions[] = 'Meta description could be longer';
            } elseif ($meta_len > 160) {
                $score -= 5;
                $suggestions[] = 'Meta description may be truncated (>160 chars)';
            }
        }

        // Focus keyword
        if (empty($seo['focus_keyword'])) {
            $score -= 20;
            $suggestions[] = 'Missing focus keyword';
        } else {
            // Check if keyword appears in title and content
            $keyword = strtolower($seo['focus_keyword']);
            $title   = strtolower($content['title'] ?? '');
            $body    = strtolower($content['body'] ?? '');

            if (strpos($title, $keyword) === false) {
                $score -= 10;
                $suggestions[] = 'Focus keyword not found in title';
            }

            $keyword_count = substr_count($body, $keyword);
            if ($keyword_count < 2) {
                $score -= 10;
                $suggestions[] = 'Focus keyword appears rarely in content';
            }
        }

        // Slug
        if (empty($seo['slug'])) {
            $score -= 10;
            $suggestions[] = 'Missing URL slug';
        }

        return ['score' => max(0, $score), 'suggestions' => $suggestions];
    }

    /**
     * Score shortcode usage
     *
     * @param array $content
     * @return array
     */
    private function score_shortcodes(array $content): array {
        $body        = $content['body'] ?? '';
        $score       = 100;
        $suggestions = [];

        // Check for booking CTA
        if (strpos($body, '[easyrest_booking_cta') === false) {
            $score -= 40;
            $suggestions[] = 'Missing booking CTA shortcode';
        }

        // Check for excessive CTAs
        $cta_count = substr_count($body, '[easyrest_booking_cta');
        if ($cta_count > 3) {
            $score -= 20;
            $suggestions[] = 'Too many CTAs may seem pushy';
        }

        // Bonus for venue/distance shortcodes where relevant
        $has_venue_info = strpos($body, '[easyrest_venue_info') !== false ||
                          strpos($body, '[easyrest_jo_distances') !== false;

        if (!$has_venue_info && $content['content_type'] !== 'evergreen') {
            $score -= 20;
            $suggestions[] = 'Consider adding venue or transport info shortcodes';
        }

        return ['score' => max(0, $score), 'suggestions' => $suggestions];
    }

    /**
     * Score readability
     *
     * @param array $content
     * @return array
     */
    private function score_readability(array $content): array {
        $body        = strip_tags($content['body'] ?? '');
        $score       = 100;
        $suggestions = [];

        // Average sentence length
        $sentences   = preg_split('/[.!?]+/', $body, -1, PREG_SPLIT_NO_EMPTY);
        $total_words = str_word_count($body);
        $avg_sentence_length = count($sentences) > 0 ? $total_words / count($sentences) : 0;

        if ($avg_sentence_length > 25) {
            $score -= 25;
            $suggestions[] = 'Sentences are too long on average. Aim for 15-20 words.';
        } elseif ($avg_sentence_length > 20) {
            $score -= 10;
            $suggestions[] = 'Consider shortening some sentences for better readability.';
        }

        // Check for complex words (3+ syllables approximation: 7+ characters)
        $words = str_word_count($body, 1);
        $complex_words = array_filter($words, function ($word) {
            return strlen($word) > 10;
        });
        $complex_ratio = count($words) > 0 ? count($complex_words) / count($words) : 0;

        if ($complex_ratio > 0.15) {
            $score -= 20;
            $suggestions[] = 'Too many complex/long words. Simplify language.';
        } elseif ($complex_ratio > 0.10) {
            $score -= 10;
        }

        // Check for passive voice indicators
        $passive_indicators = ['was ', 'were ', 'been ', 'being ', 'is being', 'are being'];
        $passive_count = 0;
        foreach ($passive_indicators as $indicator) {
            $passive_count += substr_count(strtolower($body), $indicator);
        }
        $passive_ratio = $total_words > 0 ? $passive_count / ($total_words / 100) : 0;

        if ($passive_ratio > 5) {
            $score -= 15;
            $suggestions[] = 'Consider using more active voice.';
        }

        return ['score' => max(0, $score), 'suggestions' => $suggestions];
    }

    /**
     * Score uniqueness (check for AI patterns)
     *
     * @param array $content
     * @return array
     */
    private function score_uniqueness(array $content): array {
        $body        = strtolower($content['body'] ?? '');
        $score       = 100;
        $suggestions = [];

        // Common AI filler phrases
        $ai_patterns = [
            'in conclusion',
            'it\'s important to note',
            'it is worth noting',
            'whether you\'re',
            'in today\'s world',
            'in the realm of',
            'dive into',
            'delve into',
            'embark on',
            'rest assured',
            'unlock the',
            'elevate your',
            'seamlessly',
            'effortlessly',
            'at the end of the day',
        ];

        $pattern_count = 0;
        $found_patterns = [];

        foreach ($ai_patterns as $pattern) {
            if (strpos($body, $pattern) !== false) {
                $pattern_count++;
                $found_patterns[] = $pattern;
            }
        }

        if ($pattern_count > 5) {
            $score -= 40;
            $suggestions[] = 'Content uses many common AI phrases. Consider rewriting for uniqueness.';
        } elseif ($pattern_count > 3) {
            $score -= 20;
            $suggestions[] = 'Some common AI phrases detected: ' . implode(', ', array_slice($found_patterns, 0, 3));
        } elseif ($pattern_count > 1) {
            $score -= 10;
        }

        // Check for repetitive sentence starters
        $sentences = preg_split('/[.!?]+/', $body, -1, PREG_SPLIT_NO_EMPTY);
        $starters  = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 10) {
                $starter = substr($sentence, 0, strpos($sentence . ' ', ' '));
                $starters[] = $starter;
            }
        }

        $starter_counts = array_count_values($starters);
        $repetitive = array_filter($starter_counts, function ($count) {
            return $count > 3;
        });

        if (count($repetitive) > 2) {
            $score -= 20;
            $suggestions[] = 'Many sentences start the same way. Vary your sentence structure.';
        }

        return ['score' => max(0, $score), 'suggestions' => $suggestions];
    }

    /**
     * Get letter grade
     *
     * @param int $score
     * @return string
     */
    private function get_grade(int $score): string {
        if ($score >= 90) {
            return 'A';
        }
        if ($score >= 80) {
            return 'B';
        }
        if ($score >= 70) {
            return 'C';
        }
        if ($score >= 60) {
            return 'D';
        }
        return 'F';
    }

    /**
     * Get minimum acceptable score
     *
     * @return int
     */
    public function get_minimum_score(): int {
        return (int) get_option('easyrest_ce_min_quality_score', 60);
    }

    /**
     * Check if content passes quality threshold
     *
     * @param array $content
     * @param array $config
     * @return array ['passes' => bool, 'score' => int, 'result' => array]
     */
    public function passes_quality(array $content, array $config = []): array {
        $result  = $this->score($content, $config);
        $minimum = $this->get_minimum_score();

        return [
            'passes' => $result['score'] >= $minimum,
            'score'  => $result['score'],
            'result' => $result,
        ];
    }
}
