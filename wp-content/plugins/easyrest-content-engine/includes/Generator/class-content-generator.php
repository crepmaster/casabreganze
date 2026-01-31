<?php
/**
 * Content Generator
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Content_Generator
 *
 * Orchestrates content generation using OpenAI
 */
class EasyRest_CE_Content_Generator {

    /**
     * @var EasyRest_CE_OpenAI_Client
     */
    private $openai;

    /**
     * @var EasyRest_CE_Prompt_Library
     */
    private $prompt_library;

    /**
     * @var EasyRest_CE_Prompt_Renderer
     */
    private $prompt_renderer;

    /**
     * @var EasyRest_CE_Event_Parser
     */
    private $event_parser;

    /**
     * @var array Generation statistics
     */
    private $stats = [];

    /**
     * @var array Content types that should use continuation for long-form output
     */
    private $longform_content_types = ['weekly_guide'];

    /**
     * Constructor
     */
    public function __construct() {
        $this->openai          = new EasyRest_CE_OpenAI_Client();
        $this->prompt_library  = new EasyRest_CE_Prompt_Library();
        $this->event_parser    = new EasyRest_CE_Event_Parser();
        $this->prompt_renderer = new EasyRest_CE_Prompt_Renderer(
            $this->prompt_library,
            $this->event_parser
        );
    }

    /**
     * Generate content for a queue item
     *
     * @param EasyRest_CE_Queue_Item    $queue_item
     * @param EasyRest_CE_Context_Model $context
     * @return array ['success' => bool, 'content' => array, 'error' => string, 'stats' => array]
     */
    public function generate(EasyRest_CE_Queue_Item $queue_item, EasyRest_CE_Context_Model $context): array {
        $start_time = microtime(true);

        $this->stats = [
            'tokens_prompt'       => 0,
            'tokens_completion'   => 0,
            'tokens_total'        => 0,
            'cost'                => 0,
            'duration'            => 0,
            'api_calls'           => 0,
            'continuations'       => 0,
            'had_truncation'      => false,
            'still_truncated'     => false,
            'continuation_failed' => false,
        ];

        try {
            // 1. Render the prompt
            $prompt_data = $this->prompt_renderer->render(
                $context,
                $queue_item->content_type,
                $queue_item->lang,
                $this->get_extra_vars($queue_item)
            );

            // 2. Generate main content
            // Use continuation-aware generation for long-form content types
            $content_result = $this->generate_main_content(
                $prompt_data['system'],
                $prompt_data['user'],
                $queue_item->content_type
            );

            if (!$content_result['success']) {
                return [
                    'success' => false,
                    'content' => null,
                    'error'   => 'Content generation failed: ' . $content_result['error'],
                    'stats'   => $this->stats,
                ];
            }

            $this->add_to_stats($content_result['usage']);

            // 3. Generate SEO metadata
            $seo_result = $this->generate_seo_metadata(
                $content_result['content'],
                $prompt_data,
                $queue_item->lang
            );

            if ($seo_result['success']) {
                $this->add_to_stats($seo_result['usage']);
            }

            // 4. Assemble final content
            $final_content = [
                'body'         => $content_result['content'],
                'title'        => $seo_result['parsed']['title'] ?? $this->extract_title($content_result['content']),
                'excerpt'      => $this->generate_excerpt($content_result['content']),
                'seo'          => $seo_result['parsed'] ?? [],
                'word_count'   => str_word_count(strip_tags($content_result['content'])),
                'content_type' => $queue_item->content_type,
                'lang'         => $queue_item->lang,
                'context'      => $context->slug,
            ];

            // 5. Validate content
            $validation = $this->validate_content($final_content, $prompt_data);

            if (!$validation['valid']) {
                return [
                    'success'  => false,
                    'content'  => $final_content,
                    'error'    => 'Content validation failed: ' . implode(', ', $validation['errors']),
                    'stats'    => $this->stats,
                    'warnings' => $validation['warnings'],
                ];
            }

            $this->stats['duration'] = microtime(true) - $start_time;

            return [
                'success'  => true,
                'content'  => $final_content,
                'error'    => '',
                'stats'    => $this->stats,
                'warnings' => $validation['warnings'],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'content' => null,
                'error'   => 'Generation exception: ' . $e->getMessage(),
                'stats'   => $this->stats,
            ];
        }
    }

    /**
     * Generate main content
     *
     * For long-form content types (e.g., weekly_guide), uses chat_with_continuation()
     * to handle potential truncation from max_tokens limits.
     *
     * @param string $system_prompt
     * @param string $user_prompt
     * @param string $content_type
     * @return array
     */
    private function generate_main_content(string $system_prompt, string $user_prompt, string $content_type = ''): array {
        $this->stats['api_calls']++;

        // Use continuation for long-form content types
        if (in_array($content_type, $this->longform_content_types, true)) {
            $result = $this->openai->chat_with_continuation($system_prompt, $user_prompt);

            // Track continuation-specific stats
            $this->stats['continuations']       = $result['continuations'] ?? 0;
            $this->stats['had_truncation']      = $result['had_truncation'] ?? false;
            $this->stats['still_truncated']     = $result['still_truncated'] ?? false;
            $this->stats['continuation_failed'] = $result['continuation_failed'] ?? false;

            // Adjust API calls count for continuations
            if ($result['continuations'] > 0) {
                $this->stats['api_calls'] += $result['continuations'];
            }

            // Log if content required continuation or was truncated
            if ($result['continuations'] > 0 || $result['still_truncated']) {
                EasyRest_CE_Logger::log(
                    'content_continuation',
                    0,
                    $result['usage']['total_tokens'] ?? 0,
                    sprintf(
                        'Long-form generation: %d continuations, still_truncated=%s, word_count=%d',
                        $result['continuations'],
                        $result['still_truncated'] ? 'true' : 'false',
                        str_word_count(strip_tags($result['content'] ?? ''))
                    ),
                    [
                        'content_type'        => $content_type,
                        'continuations'       => $result['continuations'],
                        'had_truncation'      => $result['had_truncation'],
                        'still_truncated'     => $result['still_truncated'],
                        'continuation_failed' => $result['continuation_failed'],
                    ]
                );
            }

            return $result;
        }

        // Standard generation for other content types
        return $this->openai->generate_content($system_prompt, $user_prompt);
    }

    /**
     * Generate SEO metadata
     *
     * @param string $content
     * @param array  $prompt_data
     * @param string $lang
     * @return array
     */
    private function generate_seo_metadata(string $content, array $prompt_data, string $lang): array {
        $this->stats['api_calls']++;

        $seo_prompt = $this->prompt_renderer->render_seo_prompt(
            $content,
            $prompt_data['keyword'] ?? '',
            $lang
        );

        return $this->openai->generate_json(
            'You are an SEO specialist. Generate metadata in JSON format only.',
            $seo_prompt
        );
    }

    /**
     * Get extra variables for prompt
     *
     * @param EasyRest_CE_Queue_Item $queue_item
     * @return array
     */
    private function get_extra_vars(EasyRest_CE_Queue_Item $queue_item): array {
        $vars = [];

        // Parse source_ref for additional data
        if (!empty($queue_item->source_ref)) {
            $ref_data = json_decode($queue_item->source_ref, true);
            if (is_array($ref_data)) {
                $vars = array_merge($vars, $ref_data);
            } else {
                $vars['source_ref'] = $queue_item->source_ref;
            }
        }

        return $vars;
    }

    /**
     * Extract title from content
     *
     * @param string $content
     * @return string
     */
    private function extract_title(string $content): string {
        // Try to find H1
        if (preg_match('/<h1[^>]*>(.+?)<\/h1>/i', $content, $matches)) {
            return strip_tags($matches[1]);
        }

        // Try to find first H2
        if (preg_match('/<h2[^>]*>(.+?)<\/h2>/i', $content, $matches)) {
            return strip_tags($matches[1]);
        }

        // Try markdown H1
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        // Fallback: first line
        $lines = explode("\n", strip_tags($content));
        return trim($lines[0] ?? 'Untitled');
    }

    /**
     * Generate excerpt from content
     *
     * @param string $content
     * @param int    $length
     * @return string
     */
    private function generate_excerpt(string $content, int $length = 160): string {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) <= $length) {
            return $text;
        }

        $excerpt = substr($text, 0, $length);
        $last_space = strrpos($excerpt, ' ');

        if ($last_space !== false) {
            $excerpt = substr($excerpt, 0, $last_space);
        }

        return $excerpt . '...';
    }

    /**
     * Validate generated content
     *
     * @param array $content
     * @param array $prompt_data
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    private function validate_content(array $content, array $prompt_data): array {
        $errors   = [];
        $warnings = [];

        // Check minimum word count
        // Long-form content types require stricter validation (90% minimum)
        // to prevent truncated content from being published
        $min_words    = $prompt_data['word_count'][0] ?? 800;
        $max_words    = $prompt_data['word_count'][1] ?? 2000;
        $content_type = $content['content_type'] ?? '';

        // Long-form content (weekly_guide etc.) must meet 90% of minimum
        // Regular content can pass with 70% as a soft threshold
        $is_longform       = in_array($content_type, $this->longform_content_types, true);
        $hard_fail_ratio   = $is_longform ? 0.9 : 0.7;
        $warning_ratio     = $is_longform ? 0.95 : 0.9;

        if ($content['word_count'] < $min_words * $hard_fail_ratio) {
            $threshold_pct = intval($hard_fail_ratio * 100);
            $errors[] = "Content too short: {$content['word_count']} words (minimum: {$min_words}, "
                      . "required {$threshold_pct}% = " . intval($min_words * $hard_fail_ratio) . " words)";
        } elseif ($content['word_count'] < $min_words * $warning_ratio) {
            $warnings[] = "Content slightly short: {$content['word_count']} words (target: {$min_words})";
        }

        if ($content['word_count'] > $max_words * 1.3) {
            $warnings[] = "Content longer than expected: {$content['word_count']} words (target max: {$max_words})";
        }

        // Check for required shortcodes
        $required_shortcodes = ['easyrest_booking_cta'];
        foreach ($required_shortcodes as $shortcode) {
            if (strpos($content['body'], "[$shortcode") === false) {
                $warnings[] = "Missing shortcode: [$shortcode]";
            }
        }

        // Check for empty title
        if (empty($content['title']) || $content['title'] === 'Untitled') {
            $errors[] = 'Missing or invalid title';
        }

        // Check for placeholder text
        $placeholders = ['{{', '}}', '[TODO]', '[INSERT]', 'Lorem ipsum'];
        foreach ($placeholders as $placeholder) {
            if (stripos($content['body'], $placeholder) !== false) {
                $warnings[] = "Content may contain placeholder: {$placeholder}";
            }
        }

        // Check SEO metadata
        if (empty($content['seo'])) {
            $warnings[] = 'Missing SEO metadata';
        } else {
            if (empty($content['seo']['meta_description'])) {
                $warnings[] = 'Missing meta description';
            }
            if (empty($content['seo']['focus_keyword'])) {
                $warnings[] = 'Missing focus keyword';
            }
        }

        return [
            'valid'    => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Add usage to stats
     *
     * @param array $usage
     */
    private function add_to_stats(array $usage): void {
        $this->stats['tokens_prompt']     += $usage['prompt_tokens'] ?? 0;
        $this->stats['tokens_completion'] += $usage['completion_tokens'] ?? 0;
        $this->stats['tokens_total']      += $usage['total_tokens'] ?? 0;
        $this->stats['cost']              += $usage['cost'] ?? 0;
    }

    /**
     * Get generation statistics
     *
     * @return array
     */
    public function get_stats(): array {
        return $this->stats;
    }

    /**
     * Get detailed generation statistics including OpenAI debug info
     *
     * Useful for debugging and monitoring long-form content generation.
     *
     * @return array
     */
    public function get_detailed_stats(): array {
        return [
            'generation_stats' => $this->stats,
            'openai_debug'     => $this->openai->get_last_response_debug_info(),
        ];
    }

    /**
     * Get the OpenAI client instance
     *
     * Useful for accessing debug methods like get_last_response_debug_info().
     *
     * @return EasyRest_CE_OpenAI_Client
     */
    public function get_openai_client(): EasyRest_CE_OpenAI_Client {
        return $this->openai;
    }

    /**
     * Check if generator is ready
     *
     * @return array ['ready' => bool, 'errors' => array]
     */
    public function check_readiness(): array {
        $errors = [];

        if (!$this->openai->is_configured()) {
            $errors[] = 'OpenAI API key not configured';
        }

        // Check prompt files
        $available = $this->prompt_library->list_available_prompts();
        if (!isset($available['base'])) {
            $errors[] = 'Base prompts file not found (prompts/base.json)';
        }

        return [
            'ready'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Regenerate content with modifications
     *
     * @param array  $original_content
     * @param string $modification_request
     * @return array
     */
    public function regenerate_with_modifications(array $original_content, string $modification_request): array {
        $this->stats['api_calls']++;

        $system_prompt = "You are a content editor. Modify the provided content according to the user's request.
Maintain the same structure and style. Return the full modified content.";

        $user_prompt = "Original content:\n\n{$original_content['body']}\n\n";
        $user_prompt .= "Modification request: {$modification_request}\n\n";
        $user_prompt .= "Please provide the updated content:";

        $result = $this->openai->generate_content($system_prompt, $user_prompt);

        if ($result['success']) {
            $this->add_to_stats($result['usage']);

            return [
                'success' => true,
                'content' => [
                    'body'       => $result['content'],
                    'title'      => $original_content['title'],
                    'seo'        => $original_content['seo'],
                    'word_count' => str_word_count(strip_tags($result['content'])),
                ],
                'stats' => $this->stats,
            ];
        }

        return $result;
    }
}
