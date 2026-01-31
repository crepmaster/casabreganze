<?php
/**
 * Post Publisher
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Post_Publisher
 *
 * Publishes generated content as WordPress posts
 */
class EasyRest_CE_Post_Publisher {

    /**
     * @var EasyRest_CE_SEO_Adapter
     */
    private $seo_adapter;

    /**
     * @var EasyRest_CE_Internal_Linker
     */
    private $linker;

    /**
     * Constructor
     */
    public function __construct() {
        $this->seo_adapter = new EasyRest_CE_SEO_Adapter();
        $this->linker      = new EasyRest_CE_Internal_Linker();
    }

    /**
     * Publish content as WordPress post
     *
     * @param array                     $content
     * @param EasyRest_CE_Queue_Item    $item
     * @param EasyRest_CE_Context_Model $context
     * @param string                    $status
     * @return int|false Post ID or false
     */
    public function publish(array $content, EasyRest_CE_Queue_Item $item, EasyRest_CE_Context_Model $context, string $status = 'draft'): int|false {
        // Process content (shortcodes, internal links, etc.)
        $processed_body = $this->process_content($content['body'], $context);

        // Prepare post data
        $post_data = [
            'post_title'   => $content['title'],
            'post_content' => $processed_body,
            'post_excerpt' => $content['excerpt'] ?? '',
            'post_status'  => $status,
            'post_type'    => 'easyrest_guide',
            'post_author'  => $this->get_author_id(),
            'meta_input'   => $this->prepare_meta($content, $item, $context),
        ];

        // Generate slug from SEO data or title
        if (!empty($content['seo']['slug'])) {
            $post_data['post_name'] = sanitize_title($content['seo']['slug']);
        }

        // Insert post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            EasyRest_CE_Logger::log(
                'publish_error',
                $item->id,
                0,
                $post_id->get_error_message()
            );
            return false;
        }

        // Set taxonomies
        $this->set_taxonomies($post_id, $content, $item, $context);

        // Set SEO meta
        $this->seo_adapter->set_seo_meta($post_id, $content['seo'] ?? []);

        // Add internal links
        if ($this->linker->is_enabled()) {
            $linked_content = $this->linker->add_internal_links($processed_body, $post_id);
            if ($linked_content !== $processed_body) {
                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => $linked_content,
                ]);
            }
        }

        // Fire action for extensions
        do_action('easyrest_ce_content_published', $post_id, $content, $item, $context);

        return $post_id;
    }

    /**
     * Process content before publishing
     *
     * @param string                    $body
     * @param EasyRest_CE_Context_Model $context
     * @return string
     */
    private function process_content(string $body, ?EasyRest_CE_Context_Model $context): string {
        // Convert markdown to HTML if needed
        if ($this->is_markdown($body)) {
            $body = $this->convert_markdown($body);
        }

        // Ensure shortcodes are properly formatted
        $body = $this->normalize_shortcodes($body);

        // Add context-specific modifications
        $body = apply_filters('easyrest_ce_content_body', $body, $context);

        return $body;
    }

    /**
     * Check if content is markdown
     *
     * @param string $content
     * @return bool
     */
    private function is_markdown(string $content): bool {
        // Check for common markdown patterns
        $patterns = [
            '/^#{1,6}\s/m',      // Headers
            '/^\*{3,}$/m',       // HR
            '/\[.+\]\(.+\)/',    // Links
            '/^[-*]\s/m',        // Lists
            '/```/',             // Code blocks
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert markdown to HTML
     *
     * @param string $content
     * @return string
     */
    private function convert_markdown(string $content): string {
        // Basic markdown conversion
        $html = $content;

        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Links
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);

        // Unordered lists
        $html = preg_replace_callback(
            '/^([-*])\s+(.+)$/m',
            function ($matches) {
                return '<li>' . $matches[2] . '</li>';
            },
            $html
        );
        $html = preg_replace('/(<li>.+<\/li>\n?)+/', '<ul>$0</ul>', $html);

        // Paragraphs
        $paragraphs = preg_split('/\n\n+/', $html);
        $paragraphs = array_map(function ($p) {
            $p = trim($p);
            if (empty($p)) {
                return '';
            }
            // Don't wrap if already has block element
            if (preg_match('/^<(h[1-6]|ul|ol|li|div|blockquote|p)/', $p)) {
                return $p;
            }
            return '<p>' . $p . '</p>';
        }, $paragraphs);

        $html = implode("\n\n", array_filter($paragraphs));

        return $html;
    }

    /**
     * Normalize shortcode formatting
     *
     * @param string $content
     * @return string
     */
    private function normalize_shortcodes(string $content): string {
        // Fix common shortcode issues
        // Ensure shortcodes are on their own line for block rendering
        $shortcodes = [
            'easyrest_booking_cta',
            'easyrest_jo_distances',
            'easyrest_venue_info',
        ];

        foreach ($shortcodes as $shortcode) {
            // Add line breaks around shortcodes if needed
            $content = preg_replace(
                '/([^\n])(\[' . $shortcode . '[^\]]*\])/',
                "$1\n\n$2",
                $content
            );
            $content = preg_replace(
                '/(\[' . $shortcode . '[^\]]*\])([^\n])/',
                "$1\n\n$2",
                $content
            );
        }

        return $content;
    }

    /**
     * Prepare post meta
     *
     * @param array                     $content
     * @param EasyRest_CE_Queue_Item    $item
     * @param EasyRest_CE_Context_Model $context
     * @return array
     */
    private function prepare_meta(array $content, EasyRest_CE_Queue_Item $item, EasyRest_CE_Context_Model $context): array {
        return [
            '_easyrest_context'       => $context->slug,
            '_easyrest_queue_id'      => $item->id,
            '_easyrest_content_type'  => $content['content_type'],
            '_easyrest_lang'          => $content['lang'],
            '_easyrest_quality_score' => $content['quality_score'] ?? 0,
            '_easyrest_generated_at'  => current_time('mysql'),
            '_easyrest_word_count'    => $content['word_count'],
            '_easyrest_source_ref'    => $item->source_ref,
        ];
    }

    /**
     * Set taxonomies for post
     *
     * @param int                       $post_id
     * @param array                     $content
     * @param EasyRest_CE_Queue_Item    $item
     * @param EasyRest_CE_Context_Model $context
     */
    private function set_taxonomies(int $post_id, array $content, EasyRest_CE_Queue_Item $item, EasyRest_CE_Context_Model $context): void {
        // Content type
        wp_set_object_terms($post_id, $content['content_type'], 'easyrest_content_type');

        // Language
        wp_set_object_terms($post_id, $content['lang'], 'easyrest_lang');

        // Topic (context)
        wp_set_object_terms($post_id, $context->slug, 'easyrest_topic');

        // Additional topics from source ref
        $source_data = json_decode($item->source_ref, true);

        if (!empty($source_data['sport'])) {
            wp_set_object_terms($post_id, sanitize_title($source_data['sport']), 'easyrest_topic', true);
        }

        if (!empty($source_data['venue_slug'])) {
            wp_set_object_terms($post_id, $source_data['venue_slug'], 'easyrest_topic', true);
        }
    }

    /**
     * Get author ID for posts
     *
     * @return int
     */
    private function get_author_id(): int {
        $author_id = get_option('easyrest_ce_post_author', 0);

        if ($author_id) {
            return (int) $author_id;
        }

        // Default to first admin
        $admins = get_users(['role' => 'administrator', 'number' => 1]);

        if (!empty($admins)) {
            return $admins[0]->ID;
        }

        return get_current_user_id() ?: 1;
    }

    /**
     * Update existing post
     *
     * @param int   $post_id
     * @param array $content
     * @return bool
     */
    public function update(int $post_id, array $content): bool {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'easyrest_guide') {
            return false;
        }

        $update_data = [
            'ID'           => $post_id,
            'post_content' => $this->process_content($content['body'], null),
        ];

        if (!empty($content['title'])) {
            $update_data['post_title'] = $content['title'];
        }

        if (!empty($content['excerpt'])) {
            $update_data['post_excerpt'] = $content['excerpt'];
        }

        $result = wp_update_post($update_data, true);

        if (is_wp_error($result)) {
            return false;
        }

        // Update meta
        update_post_meta($post_id, '_easyrest_word_count', $content['word_count']);
        update_post_meta($post_id, '_easyrest_quality_score', $content['quality_score'] ?? 0);
        update_post_meta($post_id, '_easyrest_updated_at', current_time('mysql'));

        // Update SEO
        if (!empty($content['seo'])) {
            $this->seo_adapter->set_seo_meta($post_id, $content['seo']);
        }

        return true;
    }

    /**
     * Trash a post
     *
     * @param int $post_id
     * @return bool
     */
    public function trash(int $post_id): bool {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'easyrest_guide') {
            return false;
        }

        return wp_trash_post($post_id) !== false;
    }

    /**
     * Get posts by queue item
     *
     * @param int $queue_id
     * @return WP_Post|null
     */
    public function get_by_queue_id(int $queue_id): ?WP_Post {
        $posts = get_posts([
            'post_type'   => 'easyrest_guide',
            'meta_key'    => '_easyrest_queue_id',
            'meta_value'  => $queue_id,
            'numberposts' => 1,
        ]);

        return !empty($posts) ? $posts[0] : null;
    }
}
