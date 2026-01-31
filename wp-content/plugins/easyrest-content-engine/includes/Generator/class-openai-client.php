<?php
/**
 * OpenAI Client
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EasyRest_CE_OpenAI_Client
 *
 * Handles communication with OpenAI API
 */
class EasyRest_CE_OpenAI_Client {

    /**
     * @var string API URL
     */
    private $api_url = 'https://api.openai.com/v1/chat/completions';

    /**
     * @var string API Key
     */
    private $api_key;

    /**
     * @var string Model
     */
    private $model;

    /**
     * @var float Temperature
     */
    private $temperature;

    /**
     * @var int Max tokens
     *
     * For long-form content (1400+ words), ensure this is set to at least 4000.
     * A 1500-word article typically requires ~2000-2500 completion tokens.
     * The default of 4000 provides headroom for detailed content with SEO metadata.
     */
    private $max_tokens;

    /**
     * @var int Request timeout
     */
    private $timeout;

    /**
     * @var int Maximum continuation attempts for truncated responses
     */
    private $max_continuations = 3;

    /**
     * @var array Last response metadata
     */
    private $last_response_meta = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key     = get_option('easyrest_ce_openai_api_key', '');
        $this->model       = get_option('easyrest_ce_openai_model', 'gpt-4o-mini');
        $this->temperature = (float) get_option('easyrest_ce_openai_temperature', 0.7);
        $this->max_tokens  = (int) get_option('easyrest_ce_openai_max_tokens', 4000);
        $this->timeout     = (int) get_option('easyrest_ce_openai_timeout', 120);
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->api_key);
    }

    /**
     * Send a chat completion request
     *
     * @param string $system_prompt
     * @param string $user_prompt
     * @param array  $options
     * @return array ['success' => bool, 'content' => string, 'error' => string, 'usage' => array]
     */
    public function chat(string $system_prompt, string $user_prompt, array $options = []): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'content' => '',
                'error'   => 'OpenAI API key not configured',
                'usage'   => [],
            ];
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => $system_prompt,
            ],
        ];

        // Add conversation history if provided (for continuations)
        if (!empty($options['messages'])) {
            $messages = array_merge($messages, $options['messages']);
        }

        // Add user prompt only if non-empty (continuations have prompt in messages)
        if (!empty($user_prompt)) {
            $messages[] = [
                'role'    => 'user',
                'content' => $user_prompt,
            ];
        }

        $body = [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => $messages,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'max_tokens'  => $options['max_tokens'] ?? $this->max_tokens,
        ];

        // Add response format if JSON requested
        if (!empty($options['json_mode'])) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $start_time = microtime(true);

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($body),
            'timeout' => $options['timeout'] ?? $this->timeout,
        ]);

        $duration = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            return [
                'success'  => false,
                'content'  => '',
                'error'    => $response->get_error_message(),
                'usage'    => [],
                'duration' => $duration,
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);
        $data        = json_decode($body, true);

        if ($status_code !== 200) {
            $error = $data['error']['message'] ?? 'Unknown API error';

            // Log rate limit errors specially
            if ($status_code === 429) {
                EasyRest_CE_Logger::log('rate_limit', 0, 0, $error);
            }

            return [
                'success'     => false,
                'content'     => '',
                'error'       => $error,
                'status_code' => $status_code,
                'usage'       => [],
                'duration'    => $duration,
            ];
        }

        // Extract content
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Extract the effective model from API response
        $effective_model = $data['model'] ?? $this->model;
        $finish_reason   = $data['choices'][0]['finish_reason'] ?? '';

        // Extract usage
        $usage = [
            'prompt_tokens'     => $data['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
            'total_tokens'      => $data['usage']['total_tokens'] ?? 0,
        ];

        // Calculate cost using effective model from API response
        $usage['cost'] = $this->calculate_cost($usage, $effective_model);

        // Store last response metadata
        $this->last_response_meta = [
            'model'           => $effective_model,
            'finish_reason'   => $finish_reason,
            'usage'           => $usage,
            'duration'        => $duration,
            'system_fp'       => $data['system_fingerprint'] ?? '',
            'configured_model' => $this->model,
        ];

        // Validate finish_reason - fail on content_filter or unexpected values
        // Valid reasons: 'stop' (complete), 'length' (truncated but valid)
        if ($finish_reason === 'content_filter') {
            return [
                'success'       => false,
                'content'       => $content,
                'error'         => 'Content blocked by OpenAI content filter',
                'usage'         => $usage,
                'duration'      => $duration,
                'finish_reason' => $finish_reason,
            ];
        }

        // Validate that we have actual content
        if (empty(trim($content))) {
            return [
                'success'       => false,
                'content'       => '',
                'error'         => 'Empty response from OpenAI API (finish_reason: ' . $finish_reason . ')',
                'usage'         => $usage,
                'duration'      => $duration,
                'finish_reason' => $finish_reason,
            ];
        }

        return [
            'success'       => true,
            'content'       => $content,
            'error'         => '',
            'usage'         => $usage,
            'duration'      => $duration,
            'finish_reason' => $finish_reason,
        ];
    }

    /**
     * Generate content with structured output
     *
     * @param string $system_prompt
     * @param string $user_prompt
     * @return array
     */
    public function generate_content(string $system_prompt, string $user_prompt): array {
        return $this->chat($system_prompt, $user_prompt, [
            'max_tokens' => $this->max_tokens,
        ]);
    }

    /**
     * Generate JSON response
     *
     * @param string $system_prompt
     * @param string $user_prompt
     * @return array
     */
    public function generate_json(string $system_prompt, string $user_prompt): array {
        $response = $this->chat($system_prompt, $user_prompt, [
            'json_mode'  => true,
            'max_tokens' => 1000,
        ]);

        if ($response['success']) {
            $parsed = json_decode($response['content'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response['parsed'] = $parsed;
            } else {
                $response['success'] = false;
                $response['error']   = 'Failed to parse JSON response';
            }
        }

        return $response;
    }

    /**
     * Chat with automatic continuation for truncated responses
     *
     * Handles finish_reason="length" by automatically requesting continuation.
     * Useful for long-form content generation where output may exceed max_tokens.
     *
     * @param string $system_prompt
     * @param string $user_prompt
     * @param array  $options
     * @return array ['success' => bool, 'content' => string, 'error' => string, 'usage' => array, 'continuations' => int]
     */
    public function chat_with_continuation(string $system_prompt, string $user_prompt, array $options = []): array {
        $full_content    = '';
        $total_usage     = [
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'total_tokens'      => 0,
            'cost'              => 0,
        ];
        $continuations      = 0;
        $total_duration     = 0;
        $last_error         = '';
        $had_truncation     = false;  // Track if ANY response was truncated
        $continuation_failed = false;

        // Initial request
        $response = $this->chat($system_prompt, $user_prompt, $options);

        if (!$response['success']) {
            return $response;
        }

        $full_content   = $response['content'];
        $total_duration = $response['duration'] ?? 0;
        $this->accumulate_usage($total_usage, $response['usage']);

        // Track if initial response was truncated
        if (($response['finish_reason'] ?? '') === 'length') {
            $had_truncation = true;
        }

        // Continue if truncated
        while (
            ($response['finish_reason'] ?? '') === 'length' &&
            $continuations < $this->max_continuations
        ) {
            $continuations++;

            // Build continuation prompt with truncated context (last 1500 chars max)
            // to avoid exceeding context limits on very long content
            $context_tail = substr($full_content, -1500);
            $continuation_prompt = "Continue from where you left off. Do not repeat any content. "
                                 . "The previous response ended with:\n\n"
                                 . "..." . $context_tail;

            // Build message history - use truncated assistant content to save tokens
            // Keep last ~4000 chars of content for context continuity
            $assistant_context = strlen($full_content) > 4000
                ? '...' . substr($full_content, -4000)
                : $full_content;

            $messages = [
                [
                    'role'    => 'assistant',
                    'content' => $assistant_context,
                ],
                [
                    'role'    => 'user',
                    'content' => $continuation_prompt,
                ],
            ];

            $continuation_options = array_merge($options, ['messages' => $messages]);
            $response = $this->chat($system_prompt, '', $continuation_options);

            if (!$response['success']) {
                $last_error = $response['error'];
                $continuation_failed = true;
                break;
            }

            $full_content   .= $response['content'];
            $total_duration += $response['duration'] ?? 0;
            $this->accumulate_usage($total_usage, $response['usage']);

            // Track any truncation
            if (($response['finish_reason'] ?? '') === 'length') {
                $had_truncation = true;
            }
        }

        // Final truncation state: still truncated after all attempts
        $still_truncated = ($response['finish_reason'] ?? '') === 'length';

        // Determine overall success:
        // - Must have content
        // - Must NOT be still truncated (content incomplete)
        // - Must NOT have had a failed continuation attempt
        // This ensures truncated/incomplete content is NEVER treated as success
        $has_content = !empty($full_content);
        $is_complete = $has_content && !$still_truncated && !$continuation_failed;

        // Build error message for failure cases
        if (!$is_complete && $has_content) {
            if ($still_truncated) {
                $last_error = 'Content truncated: response exceeded max_tokens limit after '
                            . $this->max_continuations . ' continuation attempts';
            } elseif ($continuation_failed && empty($last_error)) {
                $last_error = 'Continuation failed during long-form generation';
            }
        }

        return [
            'success'             => $is_complete,
            'content'             => $full_content,
            'error'               => $last_error,
            'usage'               => $total_usage,
            'duration'            => $total_duration,
            'continuations'       => $continuations,
            'had_truncation'      => $had_truncation,      // Any response was truncated
            'still_truncated'     => $still_truncated,     // Final response was truncated
            'continuation_failed' => $continuation_failed, // A continuation request failed
            'finish_reason'       => $response['finish_reason'] ?? '',
        ];
    }

    /**
     * Accumulate usage stats from multiple API calls
     *
     * @param array $total Reference to total usage array
     * @param array $usage Usage from single API call
     */
    private function accumulate_usage(array &$total, array $usage): void {
        $total['prompt_tokens']     += $usage['prompt_tokens'] ?? 0;
        $total['completion_tokens'] += $usage['completion_tokens'] ?? 0;
        $total['total_tokens']      += $usage['total_tokens'] ?? 0;
        $total['cost']              += $usage['cost'] ?? 0;
    }

    /**
     * Get debug information from last response
     *
     * Returns a formatted array useful for debugging and logging.
     * Includes model comparison, token usage, and response metadata.
     *
     * @return array
     */
    public function get_last_response_debug_info(): array {
        $meta = $this->last_response_meta;

        if (empty($meta)) {
            return [
                'available' => false,
                'message'   => 'No API response recorded yet',
            ];
        }

        $effective_model  = $meta['model'] ?? 'unknown';
        $configured_model = $meta['configured_model'] ?? $this->model;
        $model_mismatch   = $effective_model !== $configured_model;

        return [
            'available'        => true,
            'configured_model' => $configured_model,
            'effective_model'  => $effective_model,
            'model_mismatch'   => $model_mismatch,
            'finish_reason'    => $meta['finish_reason'] ?? '',
            'was_truncated'    => ($meta['finish_reason'] ?? '') === 'length',
            'usage'            => [
                'prompt_tokens'     => $meta['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $meta['usage']['completion_tokens'] ?? 0,
                'total_tokens'      => $meta['usage']['total_tokens'] ?? 0,
                'cost_usd'          => $meta['usage']['cost'] ?? 0,
            ],
            'duration_seconds' => round($meta['duration'] ?? 0, 3),
            'system_fingerprint' => $meta['system_fp'] ?? '',
        ];
    }

    /**
     * Calculate cost based on usage
     *
     * Uses the effective model name from the API response (not just configured model)
     * to ensure accurate cost calculation when the API returns a different model variant.
     *
     * @param array       $usage          Token usage from API response
     * @param string|null $effective_model Model name from API response (e.g., 'gpt-4o-mini-2024-07-18')
     * @return float
     */
    private function calculate_cost(array $usage, ?string $effective_model = null): float {
        // Pricing per 1M tokens (as of 2024)
        $pricing = [
            'gpt-4o-mini' => [
                'input'  => 0.15,  // $0.15 per 1M input tokens
                'output' => 0.60,  // $0.60 per 1M output tokens
            ],
            'gpt-4o' => [
                'input'  => 2.50,
                'output' => 10.00,
            ],
            'gpt-4-turbo' => [
                'input'  => 10.00,
                'output' => 30.00,
            ],
            'gpt-3.5-turbo' => [
                'input'  => 0.50,
                'output' => 1.50,
            ],
        ];

        // Use effective model if provided, otherwise fall back to configured model
        $model_to_use = $effective_model ?? $this->model;

        // Normalize model name (e.g., 'gpt-4o-mini-2024-07-18' -> 'gpt-4o-mini')
        $normalized_model = $this->normalize_model_name($model_to_use);

        $model_pricing = $pricing[$normalized_model] ?? $pricing['gpt-4o-mini'];

        $input_cost  = ($usage['prompt_tokens'] / 1000000) * $model_pricing['input'];
        $output_cost = ($usage['completion_tokens'] / 1000000) * $model_pricing['output'];

        return round($input_cost + $output_cost, 6);
    }

    /**
     * Normalize model name to base model for pricing lookup
     *
     * Strips date suffixes and version numbers (e.g., 'gpt-4o-mini-2024-07-18' -> 'gpt-4o-mini')
     *
     * @param string $model
     * @return string
     */
    private function normalize_model_name(string $model): string {
        // Strip date suffixes like -2024-07-18
        $normalized = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $model);

        // Map known variants to base models
        $model_map = [
            'gpt-4-turbo-preview' => 'gpt-4-turbo',
            'gpt-4-0125-preview'  => 'gpt-4-turbo',
            'gpt-4-1106-preview'  => 'gpt-4-turbo',
            'gpt-3.5-turbo-0125'  => 'gpt-3.5-turbo',
            'gpt-3.5-turbo-1106'  => 'gpt-3.5-turbo',
        ];

        return $model_map[$normalized] ?? $normalized;
    }

    /**
     * Get last response metadata
     *
     * @return array
     */
    public function get_last_response_meta(): array {
        return $this->last_response_meta;
    }

    /**
     * Test API connection
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function test_connection(): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => 'API key not configured',
            ];
        }

        $response = $this->chat(
            'You are a helpful assistant.',
            'Reply with exactly: "Connection successful"',
            [
                'max_tokens' => 20,
                'timeout'    => 30,
            ]
        );

        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'API connection successful. Model: ' . $this->model,
                'usage'   => $response['usage'],
            ];
        }

        return [
            'success' => false,
            'message' => 'API error: ' . $response['error'],
        ];
    }

    /**
     * Get available models
     *
     * @return array
     */
    public function get_available_models(): array {
        return [
            'gpt-4o-mini'     => 'GPT-4o Mini (Recommended - Best value)',
            'gpt-4o'          => 'GPT-4o (Higher quality, higher cost)',
            'gpt-4-turbo'     => 'GPT-4 Turbo (Legacy)',
            'gpt-3.5-turbo'   => 'GPT-3.5 Turbo (Fastest, lowest cost)',
        ];
    }

    /**
     * Set API key
     *
     * @param string $key
     */
    public function set_api_key(string $key): void {
        $this->api_key = $key;
    }

    /**
     * Set model
     *
     * @param string $model
     */
    public function set_model(string $model): void {
        $this->model = $model;
    }
}
