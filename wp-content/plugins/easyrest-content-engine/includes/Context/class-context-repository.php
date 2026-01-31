<?php
/**
 * Context Repository
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Context_Repository
 *
 * Handles database operations for contexts
 */
class EasyRest_CE_Context_Repository {

    /**
     * @var string
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'easyrest_contexts';
    }

    /**
     * Get context by ID
     *
     * @param int $id
     * @return EasyRest_CE_Context_Model|null
     */
    public function get(int $id): ?EasyRest_CE_Context_Model {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );

        if (!$row) {
            return null;
        }

        return EasyRest_CE_Context_Model::from_row($row);
    }

    /**
     * Get context by slug
     *
     * @param string $slug
     * @return EasyRest_CE_Context_Model|null
     */
    public function get_by_slug(string $slug): ?EasyRest_CE_Context_Model {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE slug = %s", $slug)
        );

        if (!$row) {
            return null;
        }

        return EasyRest_CE_Context_Model::from_row($row);
    }

    /**
     * Get all contexts
     *
     * @param array $args
     * @return EasyRest_CE_Context_Model[]
     */
    public function get_all(array $args = []): array {
        global $wpdb;

        $defaults = [
            'status'   => null,
            'type'     => null,
            'orderby'  => 'priority',
            'order'    => 'DESC',
            'limit'    => 100,
            'offset'   => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['type']) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'priority DESC';

        $sql = "SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if (count($values) > 2) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare($sql, $args['limit'], $args['offset']));
        }

        return array_map([EasyRest_CE_Context_Model::class, 'from_row'], $rows);
    }

    /**
     * Get active contexts
     *
     * @return EasyRest_CE_Context_Model[]
     */
    public function get_active_contexts(): array {
        $contexts = $this->get_all(['status' => 'active']);

        // Filter by date range
        return array_filter($contexts, function ($context) {
            return $context->is_in_date_range();
        });
    }

    /**
     * Create a new context
     *
     * @param array $data
     * @return int|false Context ID or false on failure
     */
    public function create(array $data): int|false {
        global $wpdb;

        $defaults = [
            'slug'        => '',
            'name'        => '',
            'type'        => 'event_based',
            'status'      => 'paused',
            'priority'    => 5,
            'daily_quota' => 2,
            'date_start'  => null,
            'date_end'    => null,
            'events_json' => null,
            'prompts_config' => null,
            'settings'    => null,
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Sanitize
        $data['slug'] = sanitize_title($data['slug']);
        $data['name'] = sanitize_text_field($data['name']);

        $result = $wpdb->insert($this->table, $data, [
            '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
        ]);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a context
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        // Sanitize slug if present
        if (isset($data['slug'])) {
            $data['slug'] = sanitize_title($data['slug']);
        }

        // Sanitize name if present
        if (isset($data['name'])) {
            $data['name'] = sanitize_text_field($data['name']);
        }

        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id],
            null,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete a context
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Update context status
     *
     * @param int    $id
     * @param string $status active|paused|archived
     * @return bool
     */
    public function update_status(int $id, string $status): bool {
        return $this->update($id, ['status' => $status]);
    }

    /**
     * Check if slug exists
     *
     * @param string $slug
     * @param int    $exclude_id Optional ID to exclude
     * @return bool
     */
    public function slug_exists(string $slug, int $exclude_id = 0): bool {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE slug = %s";
        $values = [$slug];

        if ($exclude_id > 0) {
            $sql .= " AND id != %d";
            $values[] = $exclude_id;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$values)) > 0;
    }

    /**
     * Count contexts by status
     *
     * @return array
     */
    public function count_by_status(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status",
            ARRAY_A
        );

        $counts = [
            'active'   => 0,
            'paused'   => 0,
            'archived' => 0,
        ];

        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }
}
