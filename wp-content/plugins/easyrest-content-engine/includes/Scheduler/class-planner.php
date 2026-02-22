<?php
/**
 * Planner
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Planner
 *
 * Plans and schedules content generation jobs
 */
class EasyRest_CE_Planner {

    /**
     * @var EasyRest_CE_Context_Repository
     */
    private $context_repo;

    /**
     * @var EasyRest_CE_Queue_Repository
     */
    private $queue_repo;

    /**
     * @var EasyRest_CE_Event_Parser
     */
    private $event_parser;

    /**
     * @var array Supported languages
     */
    private $languages = ['fr', 'en', 'it', 'es'];

    /**
     * @var array Content type configurations
     *
     * Note: These are PROMPT TEMPLATE content types (weekly_guide, sport_guide, etc.),
     * NOT WordPress post types. The publisher determines the post type (easyrest_guide).
     */
    private $content_types = [
        'weekly_guide' => [
            'planning_mode' => 'weekly',  // Also used for 'evergreen' context fallback
            'lead_days'     => 7,
            'priority'      => 8,
        ],
        'sport_guide' => [
            'planning_mode' => 'on_demand',
            'lead_days'     => 14,
            'priority'      => 6,
        ],
        'transport_guide' => [
            'planning_mode' => 'on_demand',
            'lead_days'     => 30,
            'priority'      => 5,
        ],
        'venue_guide' => [
            'planning_mode' => 'on_demand',
            'lead_days'     => 30,
            'priority'      => 5,
        ],
        'match_preview' => [
            'planning_mode' => 'event_based',
            'lead_days'     => 3,
            'priority'      => 9,
        ],
        'nationality_guide' => [
            'planning_mode' => 'on_demand',
            'lead_days'     => 60,
            'priority'      => 4,
        ],
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->context_repo = new EasyRest_CE_Context_Repository();
        $this->queue_repo   = new EasyRest_CE_Queue_Repository();
        $this->event_parser = new EasyRest_CE_Event_Parser();

        // Allow filtering languages
        $this->languages = apply_filters('easyrest_ce_languages', $this->languages);
    }

    /**
     * Run planning cycle
     *
     * @return array ['planned' => int, 'skipped' => int, 'errors' => array]
     */
    public function run(): array {
        $start_time = microtime(true);
        $results    = [
            'planned' => 0,
            'skipped' => 0,
            'errors'  => [],
        ];

        // Get active contexts
        $contexts = $this->context_repo->get_active_contexts();

        if (empty($contexts)) {
            EasyRest_CE_Logger::log('planner_run', 0, 0, 'No active contexts');
            return $results;
        }

        foreach ($contexts as $context) {
            try {
                $context_results = $this->plan_for_context($context);
                $results['planned'] += $context_results['planned'];
                $results['skipped'] += $context_results['skipped'];
            } catch (Exception $e) {
                $results['errors'][] = "Context {$context->slug}: " . $e->getMessage();
            }
        }

        // Log planner run
        $duration = microtime(true) - $start_time;
        EasyRest_CE_Logger::log(
            'planner_run',
            0,
            0,
            sprintf('Planned %d items, skipped %d', $results['planned'], $results['skipped']),
            ['duration' => $duration, 'contexts' => count($contexts)]
        );

        return $results;
    }

    /**
     * Plan content for a specific context
     *
     * @param EasyRest_CE_Context_Model $context
     * @return array
     */
    public function plan_for_context(EasyRest_CE_Context_Model $context): array {
        $results = ['planned' => 0, 'skipped' => 0];

        // Get enabled content types for this context
        $enabled_types = $this->get_enabled_content_types($context);

        // Get languages for this context (with fallback)
        $context_languages = $this->get_context_languages($context);

        foreach ($enabled_types as $content_type) {
            $type_config = $this->content_types[$content_type] ?? null;

            if (!$type_config) {
                continue;
            }

            switch ($type_config['planning_mode']) {
                case 'weekly':
                    $type_results = $this->plan_weekly_content($context, $content_type, $type_config, $context_languages);
                    break;

                case 'event_based':
                    $type_results = $this->plan_event_based_content($context, $content_type, $type_config, $context_languages);
                    break;

                case 'on_demand':
                    $type_results = $this->plan_on_demand_content($context, $content_type, $type_config, $context_languages);
                    break;

                case 'evergreen':
                    $type_results = $this->plan_evergreen_content($context, $content_type, $type_config, $context_languages);
                    break;

                default:
                    $type_results = ['planned' => 0, 'skipped' => 0];
            }

            $results['planned'] += $type_results['planned'];
            $results['skipped'] += $type_results['skipped'];
        }

        return $results;
    }

    /**
     * Plan weekly content (e.g., weekly guides)
     *
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $content_type
     * @param array                     $config
     * @param array                     $languages Languages to plan for
     * @return array
     */
    private function plan_weekly_content(EasyRest_CE_Context_Model $context, string $content_type, array $config, ?array $languages = null): array {
        $results = ['planned' => 0, 'skipped' => 0];

        // Use provided languages or fall back to global
        $langs = $languages ?? $this->languages;

        // Get the next Monday
        $next_monday = $this->get_next_monday();

        // Plan for this week and next week
        $weeks_to_plan = [
            $next_monday,
            date('Y-m-d', strtotime($next_monday . ' +7 days')),
        ];

        foreach ($weeks_to_plan as $week_start) {
            foreach ($langs as $lang) {
                $unique_key = $this->generate_unique_key(
                    $context->slug,
                    $content_type,
                    $lang,
                    $week_start
                );

                // Check if already exists
                if ($this->queue_repo->exists_by_unique_key($unique_key)) {
                    $results['skipped']++;
                    continue;
                }

                // Calculate scheduled time
                $scheduled_at = $this->calculate_schedule_time($week_start, $config['lead_days']);

                // Create queue item
                $item_id = $this->queue_repo->create([
                    'context_id'   => $context->id,
                    'content_type' => $content_type,
                    'lang'         => $lang,
                    'source_ref'   => json_encode(['week_start' => $week_start]),
                    'unique_key'   => $unique_key,
                    'priority'     => $config['priority'],
                    'scheduled_at' => $scheduled_at,
                ]);

                if ($item_id) {
                    $results['planned']++;
                }
            }
        }

        return $results;
    }

    /**
     * Plan event-based content (e.g., match previews)
     *
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $content_type
     * @param array                     $config
     * @param array                     $languages Languages to plan for
     * @return array
     */
    private function plan_event_based_content(EasyRest_CE_Context_Model $context, string $content_type, array $config, ?array $languages = null): array {
        $results = ['planned' => 0, 'skipped' => 0];

        // Use provided languages or fall back to global
        $langs = $languages ?? $this->languages;

        // Parse events from context
        $events = $this->event_parser->parse_events($context);

        if (empty($events)) {
            return $results;
        }

        // Get upcoming events within planning window
        $planning_window = date('Y-m-d', strtotime('+' . $config['lead_days'] . ' days'));
        $upcoming = $this->event_parser->filter_by_date_range(
            $events,
            date('Y-m-d'),
            $planning_window
        );

        foreach ($upcoming as $event) {
            // Only plan for significant events (e.g., matches, finals)
            if (!$this->is_significant_event($event)) {
                continue;
            }

            foreach ($langs as $lang) {
                $event_ref = $event['date']['date'] . '_' . sanitize_title($event['sport']);

                if (!empty($event['teams'])) {
                    $team_names = array_column($event['teams'], 'name');
                    $event_ref .= '_' . sanitize_title(implode('_vs_', $team_names));
                }

                $unique_key = $this->generate_unique_key(
                    $context->slug,
                    $content_type,
                    $lang,
                    $event_ref
                );

                if ($this->queue_repo->exists_by_unique_key($unique_key)) {
                    $results['skipped']++;
                    continue;
                }

                // Schedule lead_days before the event
                $scheduled_at = date('Y-m-d H:i:s', strtotime($event['date']['date'] . " -{$config['lead_days']} days"));

                // Don't schedule in the past
                if (strtotime($scheduled_at) < time()) {
                    $scheduled_at = current_time('mysql');
                }

                $item_id = $this->queue_repo->create([
                    'context_id'   => $context->id,
                    'content_type' => $content_type,
                    'lang'         => $lang,
                    'source_ref'   => json_encode($event),
                    'unique_key'   => $unique_key,
                    'priority'     => $config['priority'],
                    'scheduled_at' => $scheduled_at,
                ]);

                if ($item_id) {
                    $results['planned']++;
                }
            }
        }

        return $results;
    }

    /**
     * Plan on-demand content (guides that don't repeat)
     *
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $content_type
     * @param array                     $config
     * @param array                     $languages Languages to plan for
     * @return array
     */
    private function plan_on_demand_content(EasyRest_CE_Context_Model $context, string $content_type, array $config, ?array $languages = null): array {
        $results = ['planned' => 0, 'skipped' => 0];

        // Use provided languages or fall back to global
        $langs = $languages ?? $this->languages;

        // Get items to generate based on content type
        $items_to_plan = $this->get_on_demand_items($context, $content_type);

        foreach ($items_to_plan as $item) {
            foreach ($langs as $lang) {
                $unique_key = $this->generate_unique_key(
                    $context->slug,
                    $content_type,
                    $lang,
                    $item['ref']
                );

                if ($this->queue_repo->exists_by_unique_key($unique_key)) {
                    $results['skipped']++;
                    continue;
                }

                $item_id = $this->queue_repo->create([
                    'context_id'   => $context->id,
                    'content_type' => $content_type,
                    'lang'         => $lang,
                    'source_ref'   => json_encode($item),
                    'unique_key'   => $unique_key,
                    'priority'     => $config['priority'],
                    'scheduled_at' => current_time('mysql'),
                ]);

                if ($item_id) {
                    $results['planned']++;
                }
            }
        }

        return $results;
    }

    /**
     * Get on-demand items to plan
     *
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $content_type
     * @return array
     */
    private function get_on_demand_items(EasyRest_CE_Context_Model $context, string $content_type): array {
        $items = [];

        switch ($content_type) {
            case 'sport_guide':
                // Get unique sports from events
                $events = $this->event_parser->parse_events($context);
                $sports = $this->event_parser->get_unique_sports($events);

                foreach ($sports as $sport) {
                    $items[] = [
                        'ref'   => sanitize_title($sport),
                        'sport' => $sport,
                    ];
                }
                break;

            case 'venue_guide':
                // Get venues from context
                $venues = $context->get_venues();

                foreach ($venues as $venue_slug => $venue) {
                    $items[] = [
                        'ref'   => $venue_slug,
                        'venue' => $venue,
                    ];
                }
                break;

            case 'transport_guide':
                // Get venues and create transport guides
                $venues = $context->get_venues();

                foreach ($venues as $venue_slug => $venue) {
                    $items[] = [
                        'ref'         => 'transport_to_' . $venue_slug,
                        'destination' => $venue['name'],
                        'venue_slug'  => $venue_slug,
                    ];
                }
                break;

            case 'nationality_guide':
                // Get nationalities from events or use default list
                $nationalities = ['france', 'italy', 'germany', 'usa', 'uk', 'spain', 'canada', 'japan', 'china'];

                foreach ($nationalities as $nationality) {
                    $items[] = [
                        'ref'         => $nationality,
                        'nationality' => $nationality,
                    ];
                }
                break;
        }

        return $items;
    }

    /**
     * Get enabled content types for context
     *
     * For Evergreen contexts without events/venues data, falls back to a real
     * registered post type ('easyrest_guide' if available, otherwise 'post').
     *
     * @param EasyRest_CE_Context_Model $context
     * @return array
     */
    private function get_enabled_content_types(EasyRest_CE_Context_Model $context): array {
        // Check context-specific override
        $context_types = $context->get_prompt_override('enabled_content_types');

        if (!empty($context_types)) {
            return $context_types;
        }

        // Fallback for Evergreen contexts without events/venues:
        // Use weekly_guide as the content type (prompt template), not the post type.
        // The post type (easyrest_guide) is determined by the publisher, not the planner.
        if ($context->type === 'evergreen') {
            $has_events = !empty($context->get_events());
            $has_venues = !empty($context->get_venues());

            if (!$has_events && !$has_venues) {
                // Use weekly_guide as default content type for evergreen contexts
                // This matches the prompt template in prompts/base.json
                $fallback_type = 'weekly_guide';

                EasyRest_CE_Logger::log(
                    'planner_fallback',
                    0,
                    0,
                    "Using fallback content type '{$fallback_type}' for context '{$context->slug}' (no events/venues configured)",
                    ['context_type' => $context->type]
                );

                return [$fallback_type];
            }
        }

        // Default enabled types (require events or venues data)
        return ['weekly_guide', 'sport_guide', 'transport_guide'];
    }

    /**
     * Get languages for a specific context
     *
     * Resolution order:
     * 1. Context-explicit languages (from $context->settings['active_langs'])
     * 2. Multilingual plugin languages (Polylang/WPML only)
     * 3. EasyRest configured default languages (option easyrest_ce_default_languages)
     * 4. WordPress locale as single language
     * 5. Ultimate fallback: ['en']
     *
     * CRITICAL: This method must NEVER call $context->get_active_langs() as it may
     * return hardcoded defaults. Only explicit settings or actual site config are used.
     *
     * @param EasyRest_CE_Context_Model $context
     * @return array Non-empty array of normalized language codes
     */
    private function get_context_languages(EasyRest_CE_Context_Model $context): array {
        // 1. Explicit context settings ONLY (bypass get_active_langs() to avoid defaults)
        $explicit = isset($context->settings['active_langs']) ? (array) $context->settings['active_langs'] : [];

        if (!empty($explicit)) {
            $normalized = array_map([$this, 'normalize_language_code'], $explicit);
            $normalized = array_values(array_unique(array_filter($normalized)));

            if (!empty($normalized)) {
                return $normalized;
            }
        }

        // 2. Multilingual plugin languages (Polylang/WPML only, NOT locale)
        $plugin_langs = $this->get_site_active_languages();

        if (!empty($plugin_langs)) {
            return $plugin_langs;
        }

        // 3. EasyRest configured default languages (opt-in multilingual without WPML/Polylang)
        $configured_langs = $this->get_configured_default_languages();

        if (!empty($configured_langs)) {
            return $configured_langs;
        }

        // 4. WordPress locale as single language
        $locale = get_locale();

        if (empty($locale)) {
            $locale = get_bloginfo('language');
        }

        if (!empty($locale)) {
            $code = $this->normalize_language_code($locale);

            if (!empty($code)) {
                return [$code];
            }
        }

        // 5. Ultimate fallback (edge case: nothing detected)
        return ['en'];
    }

    /**
     * Get active languages from multilingual plugins ONLY (Polylang or WPML)
     *
     * This method returns plugin-managed languages only.
     * It does NOT fall back to WordPress locale - that is handled separately
     * in get_context_languages() resolution order.
     *
     * @return array Normalized language codes from plugins, or empty array if no plugin active
     */
    private function get_site_active_languages(): array {
        // 1. Polylang detection
        if (function_exists('pll_languages_list')) {
            $langs = pll_languages_list(['fields' => 'slug']);
            $langs = array_map([$this, 'normalize_language_code'], (array) $langs);
            $langs = array_values(array_unique(array_filter($langs)));

            if (!empty($langs)) {
                return $langs;
            }
        }

        // 2. WPML detection
        if (has_filter('wpml_active_languages')) {
            $active = apply_filters('wpml_active_languages', null, []);

            if (is_array($active) && !empty($active)) {
                $codes = array_keys($active);
                $codes = array_map([$this, 'normalize_language_code'], $codes);
                $codes = array_values(array_unique(array_filter($codes)));

                if (!empty($codes)) {
                    return $codes;
                }
            }
        }

        // No multilingual plugin active - return empty array
        // (locale fallback is handled in get_context_languages)
        return [];
    }

    /**
     * Get configured default languages from EasyRest plugin option
     *
     * This allows multilingual content generation without Polylang/WPML.
     * Checks 'easyrest_ce_default_languages' first, then falls back to
     * 'easyrest_ce_active_langs' (set by the activator).
     *
     * Accepts:
     * - An array of language codes: ['en', 'fr', 'it']
     * - A comma-separated string: 'en,fr,it'
     *
     * @return array Normalized language codes, or empty array if not configured
     */
    private function get_configured_default_languages(): array {
        $option = get_option('easyrest_ce_default_languages', null);

        // Fall back to the activation-created option
        if (empty($option)) {
            $option = get_option('easyrest_ce_active_langs', []);
        }

        // Handle comma-separated string
        if (is_string($option) && !empty($option)) {
            $option = array_map('trim', explode(',', $option));
        }

        // Ensure it's an array
        if (!is_array($option) || empty($option)) {
            return [];
        }

        // Normalize and filter
        $normalized = array_map([$this, 'normalize_language_code'], $option);
        $normalized = array_values(array_unique(array_filter($normalized)));

        return $normalized;
    }

    /**
     * Normalize a language code to its base form
     *
     * Examples:
     * - 'en-US' -> 'en'
     * - 'fr_FR' -> 'fr'
     * - 'de' -> 'de'
     *
     * @param string $code Raw language code
     * @return string Normalized two-letter language code, or empty string if invalid
     */
    private function normalize_language_code(string $code): string {
        $code = strtolower(trim((string) $code));
        $code = str_replace('_', '-', $code);

        if ($code === '') {
            return '';
        }

        $parts = explode('-', $code);

        return $parts[0] ?? '';
    }

    /**
     * Plan evergreen content (simple guide generation without event/venue dependencies)
     *
     * This planning mode is used as a fallback for Evergreen contexts that have
     * valid Prompt Overrides but no Events/Venues JSON configured.
     * It creates one queue item per language using the context slug as the source reference.
     *
     * Content type will be a real registered post type ('easyrest_guide' or 'post').
     * Idempotence: unique_key = {context_slug}|{content_type}|{lang}|{context_slug}
     * Re-running the planner will skip existing items via exists_by_unique_key() check.
     *
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $content_type Real post type (easyrest_guide or post)
     * @param array                     $config
     * @param array                     $languages Languages to plan for
     * @return array
     */
    private function plan_evergreen_content(EasyRest_CE_Context_Model $context, string $content_type, array $config, array $languages): array {
        $results = ['planned' => 0, 'skipped' => 0];

        foreach ($languages as $lang) {
            // Use context slug as source_ref for evergreen content
            // This ensures one item per context per language
            $source_ref = $context->slug;

            $unique_key = $this->generate_unique_key(
                $context->slug,
                $content_type,
                $lang,
                $source_ref
            );

            // Idempotence check: skip if already exists
            if ($this->queue_repo->exists_by_unique_key($unique_key)) {
                $results['skipped']++;
                continue;
            }

            // Legacy idempotence check: also check for old 'evergreen_guide' key
            // This preserves idempotence across the content_type rename
            $legacy_key = $this->generate_unique_key(
                $context->slug,
                'evergreen_guide', // Legacy content_type before rename
                $lang,
                $source_ref
            );
            if ($this->queue_repo->exists_by_unique_key($legacy_key)) {
                $results['skipped']++;
                continue;
            }

            // Create queue item
            $item_id = $this->queue_repo->create([
                'context_id'   => $context->id,
                'content_type' => $content_type,
                'lang'         => $lang,
                'source_ref'   => json_encode(['context' => $context->slug, 'type' => 'evergreen']),
                'unique_key'   => $unique_key,
                'priority'     => $config['priority'],
                'scheduled_at' => current_time('mysql'),
            ]);

            if ($item_id) {
                $results['planned']++;
                EasyRest_CE_Logger::log(
                    'planner_item_created',
                    $item_id,
                    0,
                    "Planned evergreen content for context '{$context->slug}' in '{$lang}'",
                    ['content_type' => $content_type, 'unique_key' => $unique_key]
                );
            }
        }

        return $results;
    }

    /**
     * Check if event is significant enough for dedicated content
     *
     * @param array $event
     * @return bool
     */
    private function is_significant_event(array $event): bool {
        // Finals, semi-finals, opening/closing ceremonies
        $significant_rounds = ['final', 'semi-final', 'quarter-final', 'ceremony', 'opening', 'closing'];

        $round = strtolower($event['round'] ?? '');
        foreach ($significant_rounds as $sig_round) {
            if (strpos($round, $sig_round) !== false) {
                return true;
            }
        }

        // Events with specific teams (matches)
        if (!empty($event['teams']) && count($event['teams']) >= 2) {
            return true;
        }

        return false;
    }

    /**
     * Generate unique key for queue item
     *
     * @param string $context_slug
     * @param string $content_type
     * @param string $lang
     * @param string $source_ref
     * @return string
     */
    private function generate_unique_key(string $context_slug, string $content_type, string $lang, string $source_ref): string {
        return "{$context_slug}|{$content_type}|{$lang}|{$source_ref}";
    }

    /**
     * Get next Monday
     *
     * @return string Y-m-d
     */
    private function get_next_monday(): string {
        $today = current_time('timestamp');
        $day   = date('N', $today);

        if ($day == 1) {
            return date('Y-m-d', $today);
        }

        $days_until_monday = 8 - $day;
        return date('Y-m-d', strtotime("+{$days_until_monday} days", $today));
    }

    /**
     * Calculate scheduled time
     *
     * @param string $target_date
     * @param int    $lead_days
     * @return string
     */
    private function calculate_schedule_time(string $target_date, int $lead_days): string {
        $scheduled = date('Y-m-d H:i:s', strtotime($target_date . " -{$lead_days} days"));

        // Don't schedule in the past
        if (strtotime($scheduled) < time()) {
            return current_time('mysql');
        }

        return $scheduled;
    }

    /**
     * Manually queue content
     *
     * @param int    $context_id
     * @param string $content_type
     * @param string $lang
     * @param array  $options
     * @return int|false Queue item ID or false
     */
    public function queue_manual(int $context_id, string $content_type, string $lang, array $options = []): int|false {
        $context = $this->context_repo->get($context_id);

        if (!$context) {
            return false;
        }

        $source_ref = $options['source_ref'] ?? date('Y-m-d-His');
        $unique_key = $this->generate_unique_key(
            $context->slug,
            $content_type,
            $lang,
            $source_ref
        );

        // For manual items, allow override of duplicate check
        if (empty($options['allow_duplicate']) && $this->queue_repo->exists_by_unique_key($unique_key)) {
            return false;
        }

        return $this->queue_repo->create([
            'context_id'   => $context_id,
            'content_type' => $content_type,
            'lang'         => $lang,
            'source_ref'   => is_array($source_ref) ? json_encode($source_ref) : $source_ref,
            'unique_key'   => $unique_key,
            'priority'     => $options['priority'] ?? 5,
            'scheduled_at' => $options['scheduled_at'] ?? current_time('mysql'),
        ]);
    }

    /**
     * Get planning statistics
     *
     * @return array
     */
    public function get_stats(): array {
        $queue_counts = $this->queue_repo->get_status_counts();
        $contexts     = $this->context_repo->get_active_contexts();

        return [
            'queue_counts'    => $queue_counts,
            'active_contexts' => count($contexts),
            'languages'       => count($this->languages),
            'content_types'   => array_keys($this->content_types),
        ];
    }
}
