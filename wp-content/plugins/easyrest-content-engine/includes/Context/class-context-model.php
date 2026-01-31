<?php
/**
 * Context Model
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Context_Model
 *
 * Represents a content context (e.g., JO 2026, Serie A, Evergreen)
 */
class EasyRest_CE_Context_Model {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $slug;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string event_based|seasonal|evergreen
     */
    public $type;

    /**
     * @var string active|paused|archived
     */
    public $status;

    /**
     * @var int
     */
    public $priority;

    /**
     * @var int
     */
    public $daily_quota;

    /**
     * @var string|null
     */
    public $date_start;

    /**
     * @var string|null
     */
    public $date_end;

    /**
     * @var string|null JSON string of events
     */
    public $events_json;

    /**
     * @var array|null Parsed prompts config
     */
    public $prompts_config;

    /**
     * @var array|null Parsed settings
     */
    public $settings;

    /**
     * @var string
     */
    public $created_at;

    /**
     * @var string
     */
    public $updated_at;

    /**
     * Create model from database row
     *
     * @param object|array $row
     * @return self
     */
    public static function from_row(object|array $row): self {
        $row = (object) $row;

        $model = new self();

        $model->id             = (int) $row->id;
        $model->slug           = $row->slug;
        $model->name           = $row->name;
        $model->type           = $row->type;
        $model->status         = $row->status;
        $model->priority       = (int) $row->priority;
        $model->daily_quota    = (int) $row->daily_quota;
        $model->date_start     = $row->date_start;
        $model->date_end       = $row->date_end;
        $model->events_json    = $row->events_json;
        $model->prompts_config = $row->prompts_config ? json_decode($row->prompts_config, true) : null;
        $model->settings       = $row->settings ? json_decode($row->settings, true) : null;
        $model->created_at     = $row->created_at;
        $model->updated_at     = $row->updated_at;

        return $model;
    }

    /**
     * Check if context is currently in date range
     *
     * @return bool
     */
    public function is_in_date_range(): bool {
        // Evergreen contexts have no date range
        if ($this->type === 'evergreen') {
            return true;
        }

        $today = date('Y-m-d');

        // Check start date
        if ($this->date_start && $today < $this->date_start) {
            return false;
        }

        // Check end date
        if ($this->date_end && $today > $this->date_end) {
            return false;
        }

        return true;
    }

    /**
     * Check if context is active
     *
     * @return bool
     */
    public function is_active(): bool {
        return $this->status === 'active' && $this->is_in_date_range();
    }

    /**
     * Get active languages for this context
     *
     * @return array
     */
    public function get_active_langs(): array {
        if (isset($this->settings['active_langs']) && is_array($this->settings['active_langs'])) {
            return $this->settings['active_langs'];
        }

        return get_option('easyrest_ce_active_langs', ['en', 'fr']);
    }

    /**
     * Get active content types for this context
     *
     * @return array
     */
    public function get_active_types(): array {
        if (isset($this->settings['active_types']) && is_array($this->settings['active_types'])) {
            return $this->settings['active_types'];
        }

        return get_option('easyrest_ce_active_types', ['weekly', 'sport_guide', 'nationality_guide', 'transport', 'match_preview']);
    }

    /**
     * Get parsed events
     *
     * @return array
     */
    public function get_events(): array {
        if (empty($this->events_json)) {
            return [];
        }

        $parsed = json_decode($this->events_json, true);

        return isset($parsed['events']) ? $parsed['events'] : [];
    }

    /**
     * Get venues from events JSON
     *
     * @return array
     */
    public function get_venues(): array {
        if (empty($this->events_json)) {
            return [];
        }

        $parsed = json_decode($this->events_json, true);

        return isset($parsed['venues']) ? $parsed['venues'] : [];
    }

    /**
     * Get target countries from events JSON
     *
     * @return array
     */
    public function get_target_countries(): array {
        if (empty($this->events_json)) {
            return [];
        }

        $parsed = json_decode($this->events_json, true);

        return isset($parsed['target_countries']) ? $parsed['target_countries'] : [];
    }

    /**
     * Get prompt override for a content type
     *
     * @param string $content_type
     * @return array|null
     */
    public function get_prompt_override(string $content_type): ?array {
        if (!$this->prompts_config || !isset($this->prompts_config['overrides'][$content_type])) {
            return null;
        }

        return $this->prompts_config['overrides'][$content_type];
    }

    /**
     * Convert to array for database insert/update
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'slug'           => $this->slug,
            'name'           => $this->name,
            'type'           => $this->type,
            'status'         => $this->status,
            'priority'       => $this->priority,
            'daily_quota'    => $this->daily_quota,
            'date_start'     => $this->date_start,
            'date_end'       => $this->date_end,
            'events_json'    => $this->events_json,
            'prompts_config' => $this->prompts_config ? wp_json_encode($this->prompts_config) : null,
            'settings'       => $this->settings ? wp_json_encode($this->settings) : null,
        ];
    }
}
