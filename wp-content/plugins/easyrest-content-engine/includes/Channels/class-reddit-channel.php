<?php
/**
 * Reddit Channel Adapter
 *
 * STUB implementation for Reddit subreddit posting.
 * Real API integration to be added in Phase 2.
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_Reddit_Channel
 *
 * Stub implementation for Reddit publishing.
 * Currently logs actions without making real API calls.
 */
class EasyRest_CE_Reddit_Channel extends EasyRest_CE_Abstract_Social_Channel {

    /**
     * Channel identifier
     */
    const CHANNEL_ID = 'reddit';

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
        return __('Reddit', 'easyrest-content-engine');
    }

    /**
     * Get channel-specific settings schema
     *
     * @return array
     */
    public function get_settings_schema(): array {
        return [
            [
                'id'          => 'easyrest_ce_channel_reddit_enabled',
                'type'        => 'checkbox',
                'label'       => __('Enable Reddit Publishing', 'easyrest-content-engine'),
                'description' => __('Automatically post content to Reddit subreddits.', 'easyrest-content-engine'),
                'default'     => false,
            ],
            [
                'id'          => 'easyrest_ce_reddit_client_id',
                'type'        => 'text',
                'label'       => __('Reddit App Client ID', 'easyrest-content-engine'),
                'description' => __('Client ID from your Reddit app (https://www.reddit.com/prefs/apps).', 'easyrest-content-engine'),
                'default'     => '',
            ],
            [
                'id'          => 'easyrest_ce_reddit_client_secret',
                'type'        => 'password',
                'label'       => __('Reddit App Client Secret', 'easyrest-content-engine'),
                'description' => __('Client secret from your Reddit app.', 'easyrest-content-engine'),
                'default'     => '',
            ],
            [
                'id'          => 'easyrest_ce_reddit_username',
                'type'        => 'text',
                'label'       => __('Reddit Username', 'easyrest-content-engine'),
                'description' => __('Your Reddit account username.', 'easyrest-content-engine'),
                'default'     => '',
            ],
            [
                'id'          => 'easyrest_ce_reddit_password',
                'type'        => 'password',
                'label'       => __('Reddit Password', 'easyrest-content-engine'),
                'description' => __('Your Reddit account password (stored securely).', 'easyrest-content-engine'),
                'default'     => '',
            ],
            [
                'id'          => 'easyrest_ce_reddit_subreddits',
                'type'        => 'textarea',
                'label'       => __('Target Subreddits', 'easyrest-content-engine'),
                'description' => __('Subreddits to post to (one per line, without r/ prefix). Be mindful of subreddit rules!', 'easyrest-content-engine'),
                'default'     => "milan\ntravel\nitaly",
                'placeholder' => "milan\ntravel\nitaly",
            ],
            [
                'id'          => 'easyrest_ce_reddit_post_type',
                'type'        => 'select',
                'label'       => __('Post Type', 'easyrest-content-engine'),
                'description' => __('How to share content on Reddit.', 'easyrest-content-engine'),
                'default'     => 'link',
                'options'     => [
                    'link' => __('Link Post', 'easyrest-content-engine'),
                    'self' => __('Text Post (Self Post)', 'easyrest-content-engine'),
                ],
            ],
        ];
    }

    /**
     * Transform content for Reddit format
     *
     * @param array $content Original content data
     * @return array Transformed content for Reddit posting
     */
    protected function transform_content_for_social(array $content): array {
        $base = parent::transform_content_for_social($content);

        // Reddit-specific formatting
        // - No hashtags (Reddit doesn't use them)
        // - Engaging titles crucial
        // - Self-posts can include full content
        $subreddits = get_option('easyrest_ce_reddit_subreddits', '');
        $subreddit_list = array_filter(array_map('trim', explode("\n", $subreddits)));

        return [
            'title'      => $base['title'],
            'url'        => $base['link'],
            'selftext'   => $base['excerpt'],
            'subreddits' => $subreddit_list,
            'kind'       => get_option('easyrest_ce_reddit_post_type', 'link'),
        ];
    }

    /**
     * Override extract_hashtags to return empty (Reddit doesn't use hashtags)
     *
     * @param array $content Content data
     * @return array
     */
    protected function extract_hashtags(array $content): array {
        return [];
    }
}
