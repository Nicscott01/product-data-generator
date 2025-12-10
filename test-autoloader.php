<?php
// Test autoloader
define( 'PRODUCT_DATA_GENERATOR_PLUGIN_DIR', __DIR__ . '/' );

// Test the autoloader logic
$test_class = 'ProductDataGenerator\\Queue\\Queue_Post_Type';

echo "Testing: $test_class\n";

// Remove namespace prefix
$class = str_replace( 'ProductDataGenerator\\', '', $test_class );
echo "After removing prefix: $class\n";

// Check for Queue subdirectory
if ( strpos( $class, 'Queue\\' ) === 0 ) {
    echo "Matched Queue subdirectory\n";
    $class = str_replace( 'Queue\\', '', $class );
    echo "After removing Queue\\: $class\n";
    
    $class = str_replace( '_', '-', $class );
    echo "After replacing underscores: $class\n";
    
    $class = strtolower( $class );
    echo "After lowercase: $class\n";
    
    $file = PRODUCT_DATA_GENERATOR_PLUGIN_DIR . 'includes/queue/class-' . $class . '.php';
    echo "Final file path: $file\n";
    echo "File exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";
}
