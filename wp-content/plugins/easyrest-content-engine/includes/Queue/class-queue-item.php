<?php
/**
 * Queue Item Model
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Queue_Item
 *
 * Represents a content generation queue item.
 *
 * Supports multi-channel distribution:
 * - channel: Distribution target (wordpress, facebook, linkedin, reddit)
 * - source_payload: Large JSON data (content snapshots for social channels)
 * - external_id: Platform-specific ID returned by channel adapters
 *
 * Status values:
 * - pending: Waiting to be processed
 * - locked: Being processed (has lock_token)
 * - generating: Content generation in progress
 * - review: Completed but needs manual review
 * - published: Successfully published
 * - failed: Failed after max retries
 * - skipped: Permanently skipped (disabled/misconfigured channel)
 */
class EasyRest_CE_Queue_Item {

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $context_id;

    /**
     * @var string
     */
    public $content_type;

    /**
     * @var string
     */
    public $lang;

    /**
     * @var string Short reference (e.g., "wp_post:123", "event:456")
     */
    public $source_ref;

    /**
     * @var string|null Large JSON payload for channel distribution
     */
    public $source_payload;

    /**
     * @var string
     */
    public $unique_key;

    /**
     * @var string Distribution channel (wordpress, facebook, linkedin, reddit)
     */
    public $channel = 'wordpress';

    /**
     * @var int
     */
    public $priority;

    /**
     * @var string
     */
    public $scheduled_at;

    /**
     * @var string pending|locked|generating|review|published|failed|skipped
     */
    public $status;

    /**
     * @var string|null
     */
    public $locked_at;

    /**
     * @var string|null
     */
    public $lock_token;

    /**
     * @var int|null WordPress post ID (for wordpress channel only)
     */
    public $post_id;

    /**
     * @var string|null Platform-specific external ID (for social channels)
     */
    public $external_id;

    /**
     * @var int
     */
    public $attempts;

    /**
     * @var string|null
     */
    public $last_error;

    /**
     * @var string|null
     */
    public $next_retry_at;

    /**
     * @var float|null
     */
    public $generation_cost;

    /**
     * @var int|null
     */
    public $tokens_used;

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

        $model->id              = (int) $row->id;
        $model->context_id      = (int) $row->context_id;
        $model->content_type    = $row->content_type;
        $model->lang            = $row->lang;
        $model->source_ref      = $row->source_ref;
        $model->source_payload  = $row->source_payload ?? null;
        $model->unique_key      = $row->unique_key;
        $model->channel         = $row->channel ?? 'wordpress';
        $model->priority        = (int) $row->priority;
        $model->scheduled_at    = $row->scheduled_at;
        $model->status          = $row->status;
        $model->locked_at       = $row->locked_at;
        $model->lock_token      = $row->lock_token;
        $model->post_id         = $row->post_id ? (int) $row->post_id : null;
        $model->external_id     = $row->external_id ?? null;
        $model->attempts        = (int) $row->attempts;
        $model->last_error      = $row->last_error;
        $model->next_retry_at   = $row->next_retry_at ?? null;
        $model->generation_cost = $row->generation_cost ? (float) $row->generation_cost : null;
        $model->tokens_used     = $row->tokens_used ? (int) $row->tokens_used : null;
        $model->created_at      = $row->created_at;
        $model->updated_at      = $row->updated_at;

        return $model;
    }

    /**
     * Generate unique key for deduplication
     *
     * @param string $context_slug
     * @param string $content_type
     * @param string $lang
     * @param string $source_ref
     * @return string
     */
    public static function generate_unique_key(string $context_slug, string $content_type, string $lang, string $source_ref): string {
        return sprintf('%s|%s|%s|%s', $context_slug, $content_type, $lang, $source_ref);
    }

    /**
     * Check if item can be retried
     *
     * @return bool
     */
    public function can_retry(): bool {
        // Skipped items cannot be retried
        if ($this->status === EasyRest_CE_Queue_Status::SKIPPED) {
            return false;
        }

        $max_attempts = get_option('easyrest_ce_max_attempts', 3);
        return $this->attempts < $max_attempts;
    }

    /**
     * Check if item is locked
     *
     * @return bool
     */
    public function is_locked(): bool {
        if (!$this->locked_at) {
            return false;
        }

        $lock_timeout = get_option('easyrest_ce_lock_timeout_min', 10);
        $lock_expires = strtotime($this->locked_at) + ($lock_timeout * 60);

        return time() < $lock_expires;
    }

    /**
     * Check if item is ready to be processed
     *
     * @return bool
     */
    public function is_ready(): bool {
        if ($this->status !== EasyRest_CE_Queue_Status::PENDING) {
            return false;
        }

        // Check scheduled time
        if (strtotime($this->scheduled_at) > time()) {
            return false;
        }

        return true;
    }

    /**
     * Check if this is a social channel item (not WordPress)
     *
     * @return bool
     */
    public function is_social_channel(): bool {
        return !empty($this->channel) && $this->channel !== 'wordpress';
    }

    /**
     * Parse source reference
     *
     * @return array ['type' => string, 'value' => string]
     */
    public function parse_source_ref(): array {
        $parts = explode(':', $this->source_ref, 2);

        return [
            'type'  => $parts[0] ?? 'unknown',
            'value' => $parts[1] ?? '',
        ];
    }

    /**
     * Get source payload as array
     *
     * Handles both new (source_payload) and legacy (JSON in source_ref) formats.
     *
     * @return array|null Decoded payload or null if not available/invalid
     */
    public function get_source_payload_array(): ?array {
        // Prefer source_payload if available
        if (!empty($this->source_payload)) {
            $data = json_decode($this->source_payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        // Fallback: check if source_ref contains JSON (legacy)
        if (!empty($this->source_ref) && (
            strpos($this->source_ref, '{') === 0 ||
            strpos($this->source_ref, '[') === 0
        )) {
            $data = json_decode($this->source_ref, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Get WordPress post ID from source data
     *
     * Works for both WordPress channel items (post_id field) and social channel items
     * (extracted from source_payload or source_ref).
     *
     * @return int|null
     */
    public function get_wordpress_post_id(): ?int {
        // For WordPress channel, use the post_id field directly
        if ($this->channel === 'wordpress' && $this->post_id) {
            return $this->post_id;
        }

        // For social channels, extract from source data
        $payload = $this->get_source_payload_array();

        if ($payload) {
            // Check various field names
            if (!empty($payload['wordpress_post_id'])) {
                return (int) $payload['wordpress_post_id'];
            }
            if (!empty($payload['parent_post_id'])) {
                return (int) $payload['parent_post_id'];
            }
        }

        // Try parsing source_ref format "wp_post:123"
        $parsed = $this->parse_source_ref();
        if ($parsed['type'] === 'wp_post' && is_numeric($parsed['value'])) {
            return (int) $parsed['value'];
        }

        return null;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'id'              => $this->id,
            'context_id'      => $this->context_id,
            'content_type'    => $this->content_type,
            'lang'            => $this->lang,
            'source_ref'      => $this->source_ref,
            'source_payload'  => $this->source_payload,
            'unique_key'      => $this->unique_key,
            'channel'         => $this->channel,
            'priority'        => $this->priority,
            'scheduled_at'    => $this->scheduled_at,
            'status'          => $this->status,
            'post_id'         => $this->post_id,
            'external_id'     => $this->external_id,
            'attempts'        => $this->attempts,
            'last_error'      => $this->last_error,
            'generation_cost' => $this->generation_cost,
            'tokens_used'     => $this->tokens_used,
        ];
    }
}
