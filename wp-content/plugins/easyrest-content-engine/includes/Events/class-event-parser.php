<?php
/**
 * Event Parser
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Event_Parser
 *
 * Parses events from context JSON data
 */
class EasyRest_CE_Event_Parser {

    /**
     * Parse events from context
     *
     * @param EasyRest_CE_Context_Model $context
     * @return array
     */
    public function parse_events(EasyRest_CE_Context_Model $context): array {
        $events = $context->get_events();

        if (empty($events)) {
            return [];
        }

        $parsed = [];

        foreach ($events as $event) {
            $parsed_event = $this->parse_single_event($event);
            if ($parsed_event) {
                $parsed[] = $parsed_event;
            }
        }

        return $parsed;
    }

    /**
     * Parse a single event
     *
     * @param array $event
     * @return array|null
     */
    private function parse_single_event(array $event): ?array {
        // Validate required fields
        if (empty($event['date']) || empty($event['sport'])) {
            return null;
        }

        return [
            'date'        => $this->parse_date($event['date']),
            'date_raw'    => $event['date'],
            'sport'       => sanitize_text_field($event['sport']),
            'teams'       => $this->parse_teams($event),
            'venue'       => sanitize_text_field($event['venue'] ?? ''),
            'venue_slug'  => sanitize_title($event['venue'] ?? ''),
            'round'       => sanitize_text_field($event['round'] ?? ''),
            'competition' => sanitize_text_field($event['competition'] ?? ''),
            'time'        => sanitize_text_field($event['time'] ?? ''),
            'category'    => sanitize_text_field($event['category'] ?? ''),
            'tickets_url' => esc_url($event['tickets_url'] ?? ''),
            'broadcast'   => $event['broadcast'] ?? [],
            'meta'        => $event['meta'] ?? [],
        ];
    }

    /**
     * Parse date string
     *
     * @param string $date_string
     * @return array
     */
    private function parse_date(string $date_string): array {
        $timestamp = strtotime($date_string);

        if ($timestamp === false) {
            $timestamp = time();
        }

        return [
            'timestamp'  => $timestamp,
            'date'       => date('Y-m-d', $timestamp),
            'time'       => date('H:i', $timestamp),
            'day'        => date('l', $timestamp),
            'day_short'  => date('D', $timestamp),
            'month'      => date('F', $timestamp),
            'month_num'  => date('m', $timestamp),
            'year'       => date('Y', $timestamp),
            'week'       => date('W', $timestamp),
            'formatted'  => date_i18n(get_option('date_format'), $timestamp),
        ];
    }

    /**
     * Parse teams from event
     *
     * @param array $event
     * @return array
     */
    private function parse_teams(array $event): array {
        $teams = [];

        if (!empty($event['teams'])) {
            if (is_array($event['teams'])) {
                foreach ($event['teams'] as $team) {
                    $teams[] = [
                        'name'    => sanitize_text_field($team['name'] ?? $team),
                        'country' => sanitize_text_field($team['country'] ?? ''),
                        'flag'    => sanitize_text_field($team['flag'] ?? ''),
                    ];
                }
            }
        } elseif (!empty($event['home_team']) && !empty($event['away_team'])) {
            $teams[] = [
                'name'    => sanitize_text_field($event['home_team']),
                'country' => sanitize_text_field($event['home_country'] ?? ''),
                'type'    => 'home',
            ];
            $teams[] = [
                'name'    => sanitize_text_field($event['away_team']),
                'country' => sanitize_text_field($event['away_country'] ?? ''),
                'type'    => 'away',
            ];
        }

        return $teams;
    }

    /**
     * Get events for a specific date range
     *
     * @param array  $events
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function filter_by_date_range(array $events, string $start_date, string $end_date): array {
        $start_ts = strtotime($start_date);
        $end_ts   = strtotime($end_date);

        return array_filter($events, function ($event) use ($start_ts, $end_ts) {
            $event_ts = $event['date']['timestamp'];
            return $event_ts >= $start_ts && $event_ts <= $end_ts;
        });
    }

    /**
     * Get events for a specific week
     *
     * @param array  $events
     * @param string $week_start Monday of the week (Y-m-d)
     * @return array
     */
    public function filter_by_week(array $events, string $week_start): array {
        $start_ts = strtotime($week_start);
        $end_ts   = strtotime($week_start . ' +6 days 23:59:59');

        return array_filter($events, function ($event) use ($start_ts, $end_ts) {
            $event_ts = $event['date']['timestamp'];
            return $event_ts >= $start_ts && $event_ts <= $end_ts;
        });
    }

    /**
     * Get events by sport
     *
     * @param array  $events
     * @param string $sport
     * @return array
     */
    public function filter_by_sport(array $events, string $sport): array {
        $sport_lower = strtolower($sport);

        return array_filter($events, function ($event) use ($sport_lower) {
            return strtolower($event['sport']) === $sport_lower;
        });
    }

    /**
     * Get events by venue
     *
     * @param array  $events
     * @param string $venue_slug
     * @return array
     */
    public function filter_by_venue(array $events, string $venue_slug): array {
        return array_filter($events, function ($event) use ($venue_slug) {
            return $event['venue_slug'] === $venue_slug;
        });
    }

    /**
     * Get upcoming events (from today)
     *
     * @param array $events
     * @param int   $limit
     * @return array
     */
    public function get_upcoming(array $events, int $limit = 10): array {
        $now = time();

        $upcoming = array_filter($events, function ($event) use ($now) {
            return $event['date']['timestamp'] >= $now;
        });

        // Sort by date
        usort($upcoming, function ($a, $b) {
            return $a['date']['timestamp'] - $b['date']['timestamp'];
        });

        return array_slice($upcoming, 0, $limit);
    }

    /**
     * Group events by date
     *
     * @param array $events
     * @return array
     */
    public function group_by_date(array $events): array {
        $grouped = [];

        foreach ($events as $event) {
            $date = $event['date']['date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $event;
        }

        ksort($grouped);
        return $grouped;
    }

    /**
     * Group events by sport
     *
     * @param array $events
     * @return array
     */
    public function group_by_sport(array $events): array {
        $grouped = [];

        foreach ($events as $event) {
            $sport = $event['sport'];
            if (!isset($grouped[$sport])) {
                $grouped[$sport] = [];
            }
            $grouped[$sport][] = $event;
        }

        ksort($grouped);
        return $grouped;
    }

    /**
     * Group events by venue
     *
     * @param array $events
     * @return array
     */
    public function group_by_venue(array $events): array {
        $grouped = [];

        foreach ($events as $event) {
            $venue = $event['venue'] ?: 'Unknown';
            if (!isset($grouped[$venue])) {
                $grouped[$venue] = [];
            }
            $grouped[$venue][] = $event;
        }

        ksort($grouped);
        return $grouped;
    }

    /**
     * Get unique sports from events
     *
     * @param array $events
     * @return array
     */
    public function get_unique_sports(array $events): array {
        $sports = array_unique(array_column($events, 'sport'));
        sort($sports);
        return $sports;
    }

    /**
     * Get unique venues from events
     *
     * @param array $events
     * @return array
     */
    public function get_unique_venues(array $events): array {
        $venues = [];

        foreach ($events as $event) {
            if (!empty($event['venue'])) {
                $venues[$event['venue_slug']] = $event['venue'];
            }
        }

        asort($venues);
        return $venues;
    }

    /**
     * Format events for prompt context
     *
     * @param array  $events
     * @param string $format markdown|json|list
     * @return string
     */
    public function format_for_prompt(array $events, string $format = 'markdown'): string {
        if (empty($events)) {
            return '';
        }

        switch ($format) {
            case 'json':
                return json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            case 'list':
                $lines = [];
                foreach ($events as $event) {
                    $teams_str = '';
                    if (!empty($event['teams'])) {
                        $team_names = array_column($event['teams'], 'name');
                        $teams_str  = ' - ' . implode(' vs ', $team_names);
                    }
                    $lines[] = sprintf(
                        '- %s: %s%s at %s',
                        $event['date']['formatted'],
                        $event['sport'],
                        $teams_str,
                        $event['venue']
                    );
                }
                return implode("\n", $lines);

            case 'markdown':
            default:
                $md = "## Events\n\n";
                $grouped = $this->group_by_date($events);

                foreach ($grouped as $date => $day_events) {
                    $formatted_date = date_i18n('l, F j, Y', strtotime($date));
                    $md .= "### {$formatted_date}\n\n";

                    foreach ($day_events as $event) {
                        $md .= "- **{$event['sport']}**";

                        if (!empty($event['teams'])) {
                            $team_names = array_column($event['teams'], 'name');
                            $md .= ': ' . implode(' vs ', $team_names);
                        }

                        if (!empty($event['venue'])) {
                            $md .= " @ {$event['venue']}";
                        }

                        if (!empty($event['time'])) {
                            $md .= " ({$event['time']})";
                        }

                        $md .= "\n";
                    }

                    $md .= "\n";
                }

                return $md;
        }
    }
}
