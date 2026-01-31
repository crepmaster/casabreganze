<?php
/**
 * Prompt Library
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Prompt_Library
 *
 * Manages prompt templates loaded from JSON files
 */
class EasyRest_CE_Prompt_Library {

    /**
     * @var array Loaded prompts cache
     */
    private $prompts = [];

    /**
     * @var string Base prompts directory
     */
    private $prompts_dir;

    /**
     * Constructor
     */
    public function __construct() {
        $this->prompts_dir = EASYREST_CE_PLUGIN_DIR . 'prompts/';
    }

    /**
     * Load base prompts
     *
     * @return array
     */
    public function load_base_prompts(): array {
        if (isset($this->prompts['base'])) {
            return $this->prompts['base'];
        }

        $file = $this->prompts_dir . 'base.json';

        if (!file_exists($file)) {
            EasyRest_CE_Logger::log('prompt_error', 0, 0, 'Base prompts file not found');
            return $this->get_default_base_prompts();
        }

        $content = file_get_contents($file);
        $data    = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            EasyRest_CE_Logger::log('prompt_error', 0, 0, 'Invalid JSON in base prompts: ' . json_last_error_msg());
            return $this->get_default_base_prompts();
        }

        $this->prompts['base'] = $data;
        return $data;
    }

    /**
     * Load context-specific prompts
     *
     * @param string $context_slug
     * @return array
     */
    public function load_context_prompts(string $context_slug): array {
        $cache_key = 'context_' . $context_slug;

        if (isset($this->prompts[$cache_key])) {
            return $this->prompts[$cache_key];
        }

        $file = $this->prompts_dir . $context_slug . '.json';

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $data    = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            EasyRest_CE_Logger::log('prompt_error', 0, 0, 'Invalid JSON in context prompts: ' . $context_slug);
            return [];
        }

        $this->prompts[$cache_key] = $data;
        return $data;
    }

    /**
     * Get merged prompts for context
     *
     * @param string $context_slug
     * @return array
     */
    public function get_prompts_for_context(string $context_slug): array {
        $base    = $this->load_base_prompts();
        $context = $this->load_context_prompts($context_slug);

        return $this->merge_prompts($base, $context);
    }

    /**
     * Merge base and context prompts
     *
     * @param array $base
     * @param array $context
     * @return array
     */
    private function merge_prompts(array $base, array $context): array {
        if (empty($context)) {
            return $base;
        }

        $merged = $base;

        // Merge system prompt
        if (!empty($context['system_prompt'])) {
            $merged['system_prompt'] = $context['system_prompt'];
        }

        // Merge content type templates
        if (!empty($context['content_types'])) {
            foreach ($context['content_types'] as $type => $template) {
                $merged['content_types'][$type] = array_merge(
                    $merged['content_types'][$type] ?? [],
                    $template
                );
            }
        }

        // Merge variables
        if (!empty($context['variables'])) {
            $merged['variables'] = array_merge(
                $merged['variables'] ?? [],
                $context['variables']
            );
        }

        // Merge examples
        if (!empty($context['examples'])) {
            $merged['examples'] = array_merge(
                $merged['examples'] ?? [],
                $context['examples']
            );
        }

        return $merged;
    }

    /**
     * Get template for content type
     *
     * @param string $context_slug
     * @param string $content_type
     * @return array|null
     */
    public function get_template(string $context_slug, string $content_type): ?array {
        $prompts = $this->get_prompts_for_context($context_slug);

        return $prompts['content_types'][$content_type] ?? null;
    }

    /**
     * Get system prompt
     *
     * @param string $context_slug
     * @return string
     */
    public function get_system_prompt(string $context_slug): string {
        $prompts = $this->get_prompts_for_context($context_slug);

        return $prompts['system_prompt'] ?? $this->get_default_system_prompt();
    }

    /**
     * Get default base prompts
     *
     * @return array
     */
    private function get_default_base_prompts(): array {
        return [
            'system_prompt'  => $this->get_default_system_prompt(),
            'content_types'  => [
                'weekly_guide' => [
                    'title'       => 'Weekly Guide',
                    'template'    => $this->get_default_weekly_template(),
                    'word_count'  => [1200, 1800],
                    'sections'    => ['intro', 'events', 'transport', 'tips', 'accommodation', 'cta'],
                ],
                'sport_guide' => [
                    'title'       => 'Sport Guide',
                    'template'    => $this->get_default_sport_template(),
                    'word_count'  => [1000, 1500],
                    'sections'    => ['intro', 'sport_info', 'venues', 'schedule', 'tips', 'cta'],
                ],
                'transport_guide' => [
                    'title'       => 'Transport Guide',
                    'template'    => $this->get_default_transport_template(),
                    'word_count'  => [800, 1200],
                    'sections'    => ['intro', 'routes', 'tips', 'costs', 'cta'],
                ],
            ],
            'variables'      => [
                'brand_name'    => 'EasyRest Milan',
                'location'      => 'Bisceglie, Milan',
                'booking_url'   => '/reservation/',
                'metro_station' => 'Bisceglie (M1)',
            ],
        ];
    }

    /**
     * Get default system prompt
     *
     * @return string
     */
    private function get_default_system_prompt(): string {
        return <<<PROMPT
You are a professional travel and lifestyle content writer for EasyRest Milan,
a premium apartment rental service located near Milan's Bisceglie metro station (M1 line).

Your goal is to create engaging, SEO-optimized content that helps visitors plan their
stay in Milan while subtly promoting the apartment as an ideal accommodation choice.

Guidelines:
- Write in a warm, helpful, and informative tone
- Include practical information that travelers find valuable
- Naturally mention the apartment's location advantages (metro access, proximity to venues)
- Use the provided shortcodes for CTAs and venue information
- Format content with proper headings (H2, H3), bullet points, and short paragraphs
- Include relevant internal linking opportunities
- Optimize for the target keyword while maintaining natural readability

Do not:
- Be overly promotional or salesy
- Make false claims about availability or prices
- Include external links without verification
- Use generic filler content
PROMPT;
    }

    /**
     * Get default weekly guide template
     *
     * @return string
     */
    private function get_default_weekly_template(): string {
        return <<<TEMPLATE
Write a comprehensive weekly guide for visitors to Milan during {{week_dates}}.

Target keyword: {{keyword}}
Language: {{lang}}

Include the following sections:

## Introduction
- Welcome message for the week
- Brief overview of what's happening

## Events This Week
{{events_list}}
- Describe each major event
- Include dates, times, and venues
- Add practical visitor tips

## Getting Around
- Best transport options from Bisceglie area
- Metro connections to event venues
- Travel time estimates

## Local Tips
- Weather expectations
- What to pack
- Local dining recommendations

## Where to Stay
- Benefits of staying near Bisceglie metro
- Proximity to events
- Include CTA shortcode: [easyrest_booking_cta style="banner"]

Format with proper H2/H3 headings and keep paragraphs concise.
TEMPLATE;
    }

    /**
     * Get default sport guide template
     *
     * @return string
     */
    private function get_default_sport_template(): string {
        return <<<TEMPLATE
Write a comprehensive guide about {{sport}} events in Milan.

Target keyword: {{keyword}}
Language: {{lang}}

Include:

## Introduction to {{sport}} in Milan
- Brief history/context
- Why Milan is a great destination for {{sport}} fans

## Venues
{{venues_info}}
- Describe each venue
- Include transport info using [easyrest_venue_info venue="{{venue_slug}}"]

## Event Schedule
{{events_list}}
- Upcoming {{sport}} events
- How to get tickets

## Fan Guide
- Best viewing spots
- Pre/post event activities
- Local fan culture

## Accommodation
- Why stay near Bisceglie for {{sport}} events
- Include CTA: [easyrest_booking_cta]

Use proper headings and include practical tips throughout.
TEMPLATE;
    }

    /**
     * Get default transport guide template
     *
     * @return string
     */
    private function get_default_transport_template(): string {
        return <<<TEMPLATE
Write a practical transport guide for getting to {{destination}} from Milan.

Target keyword: {{keyword}}
Language: {{lang}}

Include:

## Overview
- Brief intro to the route
- Why this guide is useful

## Transport Options
- Metro routes and connections
- Travel times from Bisceglie
- Include [easyrest_jo_distances] if relevant

## Step-by-Step Directions
- Detailed instructions
- What to expect at each point

## Tips and Costs
- Ticket prices
- Best times to travel
- Accessibility information

## Accommodation Tip
- Benefits of staying near Bisceglie metro
- Quick CTA: [easyrest_booking_cta style="compact"]

Keep instructions clear and practical.
TEMPLATE;
    }

    /**
     * List available prompt files
     *
     * @return array
     */
    public function list_available_prompts(): array {
        $files = glob($this->prompts_dir . '*.json');
        $prompts = [];

        foreach ($files as $file) {
            $slug = basename($file, '.json');
            $prompts[$slug] = [
                'file'     => $file,
                'modified' => filemtime($file),
            ];
        }

        return $prompts;
    }

    /**
     * Validate prompt file
     *
     * @param string $file_path
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate_prompt_file(string $file_path): array {
        $errors = [];

        if (!file_exists($file_path)) {
            return ['valid' => false, 'errors' => ['File not found']];
        }

        $content = file_get_contents($file_path);
        $data    = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['valid' => false, 'errors' => ['Invalid JSON: ' . json_last_error_msg()]];
        }

        // Check required structure
        if (empty($data['content_types']) && empty($data['system_prompt'])) {
            $errors[] = 'Missing content_types or system_prompt';
        }

        // Validate content types
        if (!empty($data['content_types'])) {
            foreach ($data['content_types'] as $type => $template) {
                if (empty($template['template'])) {
                    $errors[] = "Missing template for content type: {$type}";
                }
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }
}
