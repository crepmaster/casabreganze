<?php
/**
 * LinkedIn Channel Adapter
 *
 * STUB implementation for LinkedIn Company Page publishing.
 * Real API integration to be added in Phase 2.
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_LinkedIn_Channel
 *
 * Stub implementation for LinkedIn publishing.
 * Currently logs actions without making real API calls.
 */
class EasyRest_CE_LinkedIn_Channel extends EasyRest_CE_Abstract_Social_Channel {

    /**
     * Channel identifier
     */
    const CHANNEL_ID = 'linkedin';

    /**
     * Get the unique channel identifier
     *
     * @return string
     */
    public function get_channel_id(): string {
        return self::CHANNEL_ID;
    }

    /**
     * Get human-readable channel name
     *
     * @return string
     */
    public function get_channel_name(): string {
        return __('LinkedIn Company Page', 'easyrest-content-engine');
    }

    /**
     * Get channel-specific settings schema
     *
     * @return array
     */
    public function get_settings_schema(): array {
        return [
            [
                'id'          => 'easyrest_ce_channel_linkedin_enabled',
                'type'        => 'checkbox',
                'label'       => __('Enable LinkedIn Publishing', 'easyrest-content-engine'),
                'description' => __('Automatically post content to your LinkedIn Company Page.', 'easyrest-content-engine'),
                'default'     => false,
            ],
            [
                'id'          => 'easyrest_ce_linkedin_organization_id',
                'type'        => 'text',
                'label'       => __('Organization ID', 'easyrest-content-engine'),
                'description' => __('Your LinkedIn Organization/Company ID.', 'easyrest-content-engine'),
                'default'     => '',
                'placeholder' => 'e.g., urn:li:organization:12345678',
            ],
            [
                'id'          => 'easyrest_ce_linkedin_access_token',
                'type'        => 'password',
                'label'       => __('Access Token', 'easyrest-content-engine'),
                'description' => __('LinkedIn API Access Token with w_organization_social permission.', 'easyrest-content-engine'),
                'default'     => '',
            ],
            [
                'id'          => 'easyrest_ce_linkedin_visibility',
                'type'        => 'select',
                'label'       => __('Post Visibility', 'easyrest-content-engine'),
                'description' => __('Who can see your LinkedIn posts.', 'easyrest-content-engine'),
                'default'     => 'PUBLIC',
                'options'     => [
                    'PUBLIC'      => __('Public (Anyone)', 'easyrest-content-engine'),
                    'CONNECTIONS' => __('Connections Only', 'easyrest-content-engine'),
                ],
            ],
        ];
    }

    /**
     * Transform content for LinkedIn format
     *
     * @param array $content Original content data
     * @return array Transformed content for LinkedIn posting
     */
    protected function transform_content_for_social(array $content): array {
        $base = parent::transform_content_for_social($content);

        // LinkedIn-specific formatting
        // - Professional tone
        // - Limited hashtags (3-5 recommended)
        // - Article-style sharing preferred
        $post_text = $base['title'] . "\n\n" . $base['excerpt'];

        if (!empty($base['hashtags'])) {
            // LinkedIn works best with 3-5 hashtags
            $post_text .= "\n\n" . implode(' ', array_slice($base['hashtags'], 0, 3));
        }

        return array_merge($base, [
            'commentary' => $post_text,
            'visibility' => get_option('easyrest_ce_linkedin_visibility', 'PUBLIC'),
        ]);
    }
}
