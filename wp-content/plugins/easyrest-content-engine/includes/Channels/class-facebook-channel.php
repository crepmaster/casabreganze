<?php
/**
 * Facebook Channel Adapter
 *
 * STUB implementation for Facebook Page publishing.
 * Real API integration to be added in Phase 2.
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Facebook_Channel
 *
 * Stub implementation for Facebook publishing.
 * Currently logs actions without making real API calls.
 */
class EasyRest_CE_Facebook_Channel extends EasyRest_CE_Abstract_Social_Channel {

    /**
     * Channel identifier
     */
    const CHANNEL_ID = 'facebook';

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
        return __('Facebook Page', 'easyrest-content-engine');
    }

    /**
     * Get channel-specific settings schema
     *
     * @return array
     */
    public function get_settings_schema(): array {
        return [
            [
                'id'          => 'easyrest_ce_channel_facebook_enabled',
                'type'        => 'checkbox',
                'label'       => __('Enable Facebook Publishing', 'easyrest-content-engine'),
                'description' => __('Automatically post content to your Facebook Page.', 'easyrest-content-engine'),
                'default'     => false,
            ],
            [
                'id'          => 'easyrest_ce_facebook_page_id',
                'type'        => 'text',
                'label'       => __('Facebook Page ID', 'easyrest-content-engine'),
                'description' => __('Your Facebook Page ID (found in Page settings).', 'easyrest-content-engine'),
                'default'     => '',
                'placeholder' => 'e.g., 123456789012345',
            ],
            [
                'id'          => 'easyrest_ce_facebook_access_token',
                'type'        => 'password',
                'label'       => __('Page Access Token', 'easyrest-content-engine'),
                'description' => __('Long-lived Page Access Token with publish_pages permission.', 'easyrest-content-engine'),
                'default'     => '',
            ],
            [
                'id'          => 'easyrest_ce_facebook_post_type',
                'type'        => 'select',
                'label'       => __('Post Type', 'easyrest-content-engine'),
                'description' => __('How to share content on Facebook.', 'easyrest-content-engine'),
                'default'     => 'link',
                'options'     => [
                    'link'  => __('Link Post (with preview)', 'easyrest-content-engine'),
                    'photo' => __('Photo Post with Link', 'easyrest-content-engine'),
                ],
            ],
        ];
    }

    /**
     * Transform content for Facebook format
     *
     * @param array $content Original content data
     * @return array Transformed content for Facebook posting
     */
    protected function transform_content_for_social(array $content): array {
        $base = parent::transform_content_for_social($content);

        // Facebook-specific formatting
        // - Hashtags at end
        // - Emoji support
        // - Link preview optimization
        $post_text = $base['excerpt'];

        if (!empty($base['hashtags'])) {
            $post_text .= "\n\n" . implode(' ', array_slice($base['hashtags'], 0, 5));
        }

        return array_merge($base, [
            'message' => $post_text,
        ]);
    }
}
