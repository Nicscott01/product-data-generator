<?php

namespace ProductDataGenerator;

use WordPress\AI_Client\AI_Client;

defined( 'ABSPATH' ) || exit;

/**
 * Autoload classes
 */
spl_autoload_register( function( $class ) {
    // Only autoload classes in this namespace
    if ( strpos( $class, 'ProductDataGenerator\\' ) !== 0 ) {
        return;
    }

    // Remove namespace prefix
    $class = str_replace( 'ProductDataGenerator\\', '', $class );
    
    // Handle templates subdirectory
    if ( strpos( $class, 'Templates\\' ) === 0 ) {
        $class = str_replace( 'Templates\\', '', $class );
        $class = str_replace( '_', '-', $class );
        $class = strtolower( $class );
        $file = PRODUCT_DATA_GENERATOR_PLUGIN_DIR . 'includes/templates/class-' . $class . '.php';
    } 
    // Handle queue subdirectory
    elseif ( strpos( $class, 'Queue\\' ) === 0 ) {
        $class = str_replace( 'Queue\\', '', $class );
        $class = str_replace( '_', '-', $class );
        $class = strtolower( $class );
        $file = PRODUCT_DATA_GENERATOR_PLUGIN_DIR . 'includes/queue/class-' . $class . '.php';
    } 
    else {
        // Convert class name to file path
        $class = str_replace( '\\', '/', $class );
        $class = str_replace( '_', '-', $class );
        $class = strtolower( $class );
        $file = PRODUCT_DATA_GENERATOR_PLUGIN_DIR . 'includes/class-' . $class . '.php';
    }
    
    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

// 1. Init the AI_Client early (on init hook)
add_action( 'init', [ \WordPress\AI_Client\AI_Client::class, 'init' ], 5 );

// 2. Initialize template registry (on init hook for normal requests)
add_action( 'init', [ Template_Registry::class, 'init' ], 10 );

// 3. Initialize REST API
add_action( 'init', [ REST_API::class, 'init' ], 10 );

// 4. Initialize Admin UI
add_action( 'init', [ Admin_UI::class, 'init' ], 10 );

// 5. Initialize Queue System
add_action( 'init', [ \ProductDataGenerator\Queue\Queue_Post_Type::class, 'init' ], 10 );
add_action( 'init', [ \ProductDataGenerator\Queue\Queue_Admin::class, 'init' ], 10 );
add_action( 'init', [ \ProductDataGenerator\Queue\Queue_Processor::class, 'init' ], 10 );

/**
 * Ensure templates are initialized
 * 
 * Useful for CLI and early execution contexts where init hook hasn't fired
 */
function ensure_initialized() {
    // Initialize template registry directly (it has its own guard)
    Template_Registry::init();
    
    // Initialize AI_Client if not already done
    if ( class_exists( '\WordPress\AI_Client\AI_Client' ) && method_exists( '\WordPress\AI_Client\AI_Client', 'init' ) ) {
        \WordPress\AI_Client\AI_Client::init();
    }
}

/**
 * Get AI generator instance
 *
 * Helper function to create a generator
 *
 * @param string $template_id Template ID
 * @param int $product_id Product ID
 * @param array $config Configuration overrides
 * @return AI_Generator
 */
function get_generator( $template_id, $product_id, array $config = [] ) {
    ensure_initialized();
    return AI_Generator::create( $template_id, $product_id, $config );
}

/**
 * Generate product content
 *
 * Quick helper to generate content for a product
 *
 * @param int $product_id Product ID
 * @param string $template_id Template ID (default: 'product_description')
 * @param array $config Configuration overrides
 * @return array|\WP_Error
 */
function generate_product_content( $product_id, $template_id = 'product_description', array $config = [] ) {
    ensure_initialized();
    $generator = get_generator( $template_id, $product_id, $config );
    return $generator->generate();
}

/**
 * Example: Register a custom template
 *
 * add_action( 'product_data_generator_register_templates', function() {
 *     ProductDataGenerator\Template_Registry::register( new My_Custom_Template() );
 * });
 *
 * Example: Generate product description
 *
 * $result = ProductDataGenerator\generate_product_content( $product_id );
 * if ( ! is_wp_error( $result ) ) {
 *     echo $result['content'];
 * }
 *
 * Example: Use a preset configuration
 *
 * $generator = ProductDataGenerator\AI_Generator::from_preset( 'short_description', $product_id );
 * $result = $generator->generate();
 *
 * Example: Custom context
 *
 * $config = [
 *     'context' => [
 *         'brand_voice' => 'professional and friendly',
 *         'target_audience' => 'young professionals',
 *     ],
 *     'ai_settings' => [
 *         'temperature' => 0.8,
 *     ],
 * ];
 * $result = ProductDataGenerator\generate_product_content( $product_id, 'product_description', $config );
 */


