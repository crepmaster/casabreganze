<?php
/**
 * TEMPORARY DEBUG – REMOVE AFTER TEST
 */

require_once dirname(__FILE__) . '/../../../wp-load.php';

echo '<pre style="font-family: monospace; font-size: 14px;">';

echo "=== EasyRest CE – Weekly Guide Debug ===\n\n";

// 1. Generator readiness
if (!class_exists('EasyRest_CE_Content_Generator')) {
    echo "❌ EasyRest_CE_Content_Generator class not found. Plugin active?\n";
    exit;
}

$generator = new EasyRest_CE_Content_Generator();
$readiness = $generator->check_readiness();

if (empty($readiness['ready']) || !$readiness['ready']) {
    echo "❌ Generator NOT ready\n";
    echo "Errors:\n";
    print_r($readiness['errors'] ?? []);
    exit;
}

echo "✅ Generator ready\n";

// 2. Context
if (!class_exists('EasyRest_CE_Context_Repository')) {
    echo "❌ EasyRest_CE_Context_Repository class not found.\n";
    exit;
}

$context_repo = new EasyRest_CE_Context_Repository();
$contexts     = $context_repo->get_all();
$context      = $contexts[0] ?? null;

if (!$context) {
    echo "❌ No context found – create one in admin first.\n";
    exit;
}

echo "Using context: {$context->name} ({$context->slug})\n\n";

// 3. Fake queue item
$queue_item = (object) [
    'id'           => 0,
    'content_type' => 'weekly_guide',
    'lang'         => 'en',
    'source_ref'   => '',
    'channel'      => 'wordpress',
];

// 4. Generate
echo "⏳ Generating weekly_guide...\n\n";
$start  = microtime(true);
$result = $generator->generate($queue_item, $context);
$elapsed = round(microtime(true) - $start, 2);

// 5. Result
echo "=== RESULT ===\n";
echo "Success: " . (!empty($result['success']) ? 'YES' : 'NO') . "\n";
echo "Error: " . (!empty($result['error']) ? $result['error'] : 'none') . "\n";
echo "Elapsed: {$elapsed}s\n\n";

if (!empty($result['success'])) {
    echo "Title: " . ($result['content']['title'] ?? '(no title)') . "\n";
    echo "Word Count: " . ($result['content']['word_count'] ?? 0) . "\n";
}

echo "\n=== STATS ===\n";
print_r($result['stats'] ?? []);

echo "\n=== OPENAI DEBUG ===\n";
if (method_exists($generator, 'get_detailed_stats')) {
    $debug = $generator->get_detailed_stats();
    print_r($debug['openai_debug'] ?? $debug);
} else {
    echo "No get_detailed_stats() method.\n";
}

// 6. Create a draft EasyRest Guide post (for manual review in WP admin)
if (!empty($result['success']) && !empty($result['content'])) {
    $post_data = [
        'post_title'   => $result['content']['title'] ?? 'Untitled Weekly Guide',
        'post_content' => $result['content']['body'] ?? '',
        'post_status'  => 'draft',
        'post_type'    => 'easyrest_guide',
    ];

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        echo "\n❌ Failed to insert post:\n";
        print_r($post_id->get_error_messages());
    } else {
        echo "\n✅ Post created with ID: {$post_id}\n";
    }
}

echo "\n\nDONE.\n";
echo '</pre>';
