<?php
/**
 * Debug script to check if queue classes can be loaded
 * Run this from the command line in the plugin directory
 */

// Load WordPress
require_once __DIR__ . '/../../../../../wp-load.php';

echo "=== Queue System Debug ===\n\n";

// Check if constants are defined
echo "1. Constants:\n";
echo "   PRODUCT_DATA_GENERATOR_PLUGIN_DIR: " . (defined('PRODUCT_DATA_GENERATOR_PLUGIN_DIR') ? '✓' : '✗') . "\n";
echo "   Value: " . (defined('PRODUCT_DATA_GENERATOR_PLUGIN_DIR') ? PRODUCT_DATA_GENERATOR_PLUGIN_DIR : 'Not defined') . "\n\n";

// Check if files exist
echo "2. Queue Files:\n";
$queue_files = [
    'class-queue-post-type.php',
    'class-queue-admin.php',
    'class-queue-processor.php',
];

foreach ($queue_files as $file) {
    $path = PRODUCT_DATA_GENERATOR_PLUGIN_DIR . 'includes/queue/' . $file;
    echo "   $file: " . (file_exists($path) ? '✓' : '✗') . " ($path)\n";
}

echo "\n3. Check if classes exist:\n";
$classes = [
    'ProductDataGenerator\Queue\Queue_Post_Type',
    'ProductDataGenerator\Queue\Queue_Admin',
    'ProductDataGenerator\Queue\Queue_Processor',
];

foreach ($classes as $class) {
    echo "   $class: " . (class_exists($class) ? '✓' : '✗') . "\n";
}

echo "\n4. Check registered post types:\n";
$post_types = get_post_types(['_builtin' => false], 'names');
echo "   pdg_queue registered: " . (in_array('pdg_queue', $post_types) ? '✓' : '✗') . "\n";

if (in_array('pdg_queue', $post_types)) {
    $post_type_object = get_post_type_object('pdg_queue');
    echo "   Show in menu: " . var_export($post_type_object->show_in_menu, true) . "\n";
    echo "   Show UI: " . var_export($post_type_object->show_ui, true) . "\n";
}

echo "\n5. Check init hooks:\n";
global $wp_filter;
if (isset($wp_filter['init'])) {
    echo "   Init hooks registered: " . count($wp_filter['init']->callbacks) . "\n";
    foreach ($wp_filter['init']->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function']) && is_string($callback['function'][0])) {
                if (strpos($callback['function'][0], 'Queue') !== false) {
                    echo "   Found Queue hook at priority $priority: " . $callback['function'][0] . '::' . $callback['function'][1] . "\n";
                }
            }
        }
    }
}

echo "\n6. Current user capability:\n";
echo "   Can manage_woocommerce: " . (current_user_can('manage_woocommerce') ? '✓' : '✗') . "\n";

echo "\n=== End Debug ===\n";
