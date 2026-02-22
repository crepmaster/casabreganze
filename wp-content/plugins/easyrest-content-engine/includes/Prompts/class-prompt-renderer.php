<?php
/**
 * Prompt Renderer
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Prompt_Renderer
 *
 * Renders prompts with variable substitution
 */
class EasyRest_CE_Prompt_Renderer {

    /**
     * @var EasyRest_CE_Prompt_Library
     */
    private $library;

    /**
     * @var EasyRest_CE_Event_Parser
     */
    private $event_parser;

    /**
     * Constructor
     *
     * @param EasyRest_CE_Prompt_Library $library
     * @param EasyRest_CE_Event_Parser   $event_parser
     */
    public function __construct(EasyRest_CE_Prompt_Library $library, EasyRest_CE_Event_Parser $event_parser) {
        $this->library      = $library;
        $this->event_parser = $event_parser;
    }

    /**
     * Render a prompt for content generation
     *
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $content_type
     * @param string                    $lang
     * @param array                     $extra_vars
     * @return array ['system' => string, 'user' => string]
     */
    public function render(EasyRest_CE_Context_Model $context, string $content_type, string $lang, array $extra_vars = []): array {
        // Get prompts
        $prompts  = $this->library->get_prompts_for_context($context->slug);
        $template = $prompts['content_types'][$content_type] ?? null;

        if (!$template) {
            throw new Exception("Template not found for content type: {$content_type}");
        }

        // Build variables
        $vars = $this->build_variables($context, $content_type, $lang, $extra_vars, $prompts);

        // Render system prompt
        $system_prompt = $this->substitute_variables(
            $prompts['system_prompt'] ?? '',
            $vars
        );

        // Add language instruction
        $system_prompt .= $this->get_language_instruction($lang);

        // Render user prompt
        $user_prompt = $this->substitute_variables(
            $template['template'],
            $vars
        );

        return [
            'system'     => $system_prompt,
            'user'       => $user_prompt,
            'word_count' => $template['word_count'] ?? [1000, 1500],
            'sections'   => $template['sections'] ?? [],
        ];
    }

    /**
     * Build variables for substitution
     *
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $content_type
     * @param string                    $lang
     * @param array                     $extra_vars
     * @param array                     $prompts
     * @return array
     */
    private function build_variables(EasyRest_CE_Context_Model $context, string $content_type, string $lang, array $extra_vars, array $prompts): array {
        // Start with base variables from prompts
        $vars = $prompts['variables'] ?? [];

        // Add context variables
        $vars['context_name']  = $context->name;
        $vars['context_slug']  = $context->slug;
        $vars['context_type']  = $context->type;
        $vars['content_type']  = $content_type;
        $vars['lang']          = $lang;
        $vars['lang_name']     = $this->get_language_name($lang);

        // Add date variables
        $vars['current_date']  = current_time('Y-m-d');
        $vars['current_year']  = current_time('Y');
        $vars['current_month'] = current_time('F');

        // Add week variables if weekly content
        if (strpos($content_type, 'weekly') !== false) {
            $week_start = $extra_vars['week_start'] ?? $this->get_next_monday();
            $week_end   = date('Y-m-d', strtotime($week_start . ' +6 days'));

            $vars['week_start']  = $week_start;
            $vars['week_end']    = $week_end;
            $vars['week_dates']  = $this->format_week_range($week_start, $week_end, $lang);
            $vars['week_number'] = date('W', strtotime($week_start));
        }

        // Add events
        $events = $this->event_parser->parse_events($context);

        if (!empty($events)) {
            // Filter for relevant period
            if (!empty($vars['week_start'])) {
                $events = $this->event_parser->filter_by_week($events, $vars['week_start']);
            }

            $vars['events_list']   = $this->event_parser->format_for_prompt($events, 'markdown');
            $vars['events_json']   = $this->event_parser->format_for_prompt($events, 'json');
            $vars['events_count']  = count($events);
            $vars['unique_sports'] = implode(', ', $this->event_parser->get_unique_sports($events));
        } else {
            $vars['events_list']   = 'No specific events scheduled.';
            $vars['events_json']   = '[]';
            $vars['events_count']  = 0;
            $vars['unique_sports'] = '';
        }

        // Add venues
        $venues = $context->get_venues();
        if (!empty($venues)) {
            $vars['venues_info'] = $this->format_venues($venues);
            $vars['venues_list'] = implode(', ', array_column($venues, 'name'));
        } else {
            $vars['venues_info'] = '';
            $vars['venues_list'] = '';
        }

        // Add keyword from extra vars or generate default
        if (!empty($extra_vars['keyword'])) {
            $vars['keyword'] = $extra_vars['keyword'];
        } else {
            $vars['keyword'] = $this->generate_default_keyword($content_type, $context, $lang);
        }

        // Merge extra variables (override if specified)
        $vars = array_merge($vars, $extra_vars);

        return $vars;
    }

    /**
     * Substitute variables in template
     *
     * @param string $template
     * @param array  $vars
     * @return string
     */
    private function substitute_variables(string $template, array $vars): string {
        // Replace {{variable}} patterns
        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            function ($matches) use ($vars) {
                $key = $matches[1];
                return $vars[$key] ?? $matches[0]; // Keep original if not found
            },
            $template
        );
    }

    /**
     * Get language instruction
     *
     * @param string $lang
     * @return string
     */
    private function get_language_instruction(string $lang): string {
        $instructions = [
            'en' => "\n\nWrite all content in English (US).",
            'fr' => "\n\nÉcris tout le contenu en français.",
            'it' => "\n\nScrivi tutti i contenuti in italiano.",
            'de' => "\n\nSchreibe alle Inhalte auf Deutsch.",
            'es' => "\n\nEscribe todo el contenido en español.",
        ];

        return $instructions[$lang] ?? $instructions['en'];
    }

    /**
     * Get language name
     *
     * @param string $lang
     * @return string
     */
    private function get_language_name(string $lang): string {
        $names = [
            'en' => 'English',
            'fr' => 'French',
            'it' => 'Italian',
            'de' => 'German',
            'es' => 'Spanish',
        ];

        return $names[$lang] ?? 'English';
    }

    /**
     * Get next Monday
     *
     * @return string Y-m-d
     */
    private function get_next_monday(): string {
        $today = current_time('timestamp');
        $day   = date('N', $today); // 1 = Monday, 7 = Sunday

        if ($day === 1) {
            return date('Y-m-d', $today);
        }

        $days_until_monday = 8 - $day;
        return date('Y-m-d', strtotime("+{$days_until_monday} days", $today));
    }

    /**
     * Format week range for display
     *
     * @param string $start
     * @param string $end
     * @param string $lang
     * @return string
     */
    private function format_week_range(string $start, string $end, string $lang): string {
        $locales = [
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'it' => 'it_IT',
            'de' => 'de_DE',
            'es' => 'es_ES',
        ];

        $locale = $locales[$lang] ?? 'en_US';

        // Use WordPress date_i18n with temporary locale switch
        $start_ts = strtotime($start);
        $end_ts   = strtotime($end);

        $start_formatted = date_i18n('F j', $start_ts);
        $end_formatted   = date_i18n('F j, Y', $end_ts);

        return "{$start_formatted} - {$end_formatted}";
    }

    /**
     * Format venues for prompt
     *
     * @param array $venues
     * @return string
     */
    private function format_venues(array $venues): string {
        $lines = [];

        foreach ($venues as $venue) {
            $line = "- **{$venue['name']}**";

            if (!empty($venue['sport'])) {
                $line .= " ({$venue['sport']})";
            }

            if (!empty($venue['transport'])) {
                $line .= ": {$venue['transport']}";
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Generate default keyword
     *
     * @param string                    $content_type
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $lang
     * @return string
     */
    private function generate_default_keyword(string $content_type, EasyRest_CE_Context_Model $context, string $lang): string {
        $keywords = [
            'en' => [
                'weekly_guide'    => 'Milan events this week',
                'sport_guide'     => 'Milan sports guide',
                'transport_guide' => 'Milan transport guide',
                'match_preview'   => 'Milan match preview',
                'venue_guide'     => 'Milan venue guide',
            ],
            'fr' => [
                'weekly_guide'    => 'événements Milan cette semaine',
                'sport_guide'     => 'guide sports Milan',
                'transport_guide' => 'guide transport Milan',
                'match_preview'   => 'aperçu match Milan',
                'venue_guide'     => 'guide lieux Milan',
            ],
            'it' => [
                'weekly_guide'    => 'eventi Milano questa settimana',
                'sport_guide'     => 'guida sport Milano',
                'transport_guide' => 'guida trasporti Milano',
                'match_preview'   => 'anteprima partita Milano',
                'venue_guide'     => 'guida luoghi Milano',
            ],
            'es' => [
                'weekly_guide'    => 'eventos Milán esta semana',
                'sport_guide'     => 'guía deportes Milán',
                'transport_guide' => 'guía transporte Milán',
                'match_preview'   => 'previa partido Milán',
                'venue_guide'     => 'guía lugares Milán',
            ],
        ];

        $lang_keywords = $keywords[$lang] ?? $keywords['en'];
        $base_keyword  = $lang_keywords[$content_type] ?? $lang_keywords['weekly_guide'];

        // Add context specificity
        if ($context->slug !== 'evergreen') {
            $base_keyword = str_replace('Milan', $context->name, $base_keyword);
        }

        return $base_keyword;
    }

    /**
     * Render SEO metadata prompt
     *
     * @param string $content
     * @param string $keyword
     * @param string $lang
     * @return string
     */
    public function render_seo_prompt(string $content, string $keyword, string $lang): string {
        $lang_instruction = $this->get_language_instruction($lang);

        return <<<PROMPT
Based on the following article content, generate SEO metadata.

Target keyword: {$keyword}

Article content:
{$content}

Generate the following in JSON format:
{
    "title": "SEO optimized title (50-60 characters, include keyword)",
    "meta_description": "Compelling meta description (150-160 characters, include keyword)",
    "focus_keyword": "primary focus keyword",
    "secondary_keywords": ["keyword1", "keyword2", "keyword3"],
    "slug": "url-friendly-slug"
}
{$lang_instruction}

Return ONLY the JSON, no additional text.
PROMPT;
    }

    /**
     * Render internal linking suggestions prompt
     *
     * @param string $content
     * @param array  $existing_posts
     * @return string
     */
    public function render_linking_prompt(string $content, array $existing_posts): string {
        $posts_list = '';
        foreach ($existing_posts as $post) {
            $posts_list .= "- ID: {$post['id']}, Title: {$post['title']}, URL: {$post['url']}\n";
        }

        return <<<PROMPT
Analyze the following article and suggest internal links to related content.

Article content:
{$content}

Available posts to link to:
{$posts_list}

For each suggested link, provide:
1. The anchor text to use
2. The post ID to link to
3. Where in the content it should be inserted (quote the surrounding text)

Return as JSON array:
[
    {
        "anchor_text": "text to make clickable",
        "post_id": 123,
        "context": "surrounding text where link should be inserted"
    }
]

Only suggest 2-4 highly relevant links. Return ONLY the JSON array.
PROMPT;
    }
}
