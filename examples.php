<?php
/**
 * Product Data Generator - Usage Examples
 *
 * This file contains practical examples of how to use the template system
 *
 * @package ProductDataGenerator
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Example 1: Generate a simple product description
 */
function example_generate_simple_description() {
    $product_id = 123; // Replace with actual product ID
    
    $result = ProductDataGenerator\generate_product_content( $product_id );
    
    if ( is_wp_error( $result ) ) {
        error_log( 'Generation failed: ' . $result->get_error_message() );
        return;
    }
    
    // Output the generated content
    echo $result['content'];
    
    // Access metadata
    $model_used = $result['metadata']['model'];
    $generated_at = $result['metadata']['generated_at'];
}

/**
 * Example 2: Generate and auto-save
 */
function example_generate_and_save() {
    $product_id = 123;
    
    $config = [
        'auto_save' => true,
        'field_mapping' => [
            'description' => 'post_content',
        ],
    ];
    
    $result = ProductDataGenerator\generate_product_content(
        $product_id,
        'product_description',
        $config
    );
    
    if ( ! is_wp_error( $result ) ) {
        echo "Description saved successfully!";
    }
}

/**
 * Example 3: Use a preset configuration
 */
function example_use_preset() {
    $product_id = 123;
    
    // Use the short_description preset
    $generator = ProductDataGenerator\AI_Generator::from_preset( 
        'short_description', 
        $product_id 
    );
    
    $result = $generator->generate();
    
    if ( ! is_wp_error( $result ) ) {
        // Manually save if needed
        $generator->save( $result['content'] );
    }
}

/**
 * Example 4: Generate with custom context
 */
function example_custom_context() {
    $product_id = 123;
    
    $config = [
        'context' => [
            'brand_voice' => 'professional yet friendly',
            'target_audience' => 'young professionals aged 25-35',
            'style' => 'informative and persuasive',
            'word_limit' => 250,
        ],
        'ai_settings' => [
            'temperature' => 0.8, // More creative
        ],
    ];
    
    $result = ProductDataGenerator\generate_product_content(
        $product_id,
        'product_description',
        $config
    );
    
    return $result;
}

/**
 * Example 5: Generate SEO-optimized content
 */
function example_seo_content() {
    $product_id = 123;
    
    $config = [
        'context' => [
            'focus_keyword' => 'organic cotton t-shirt',
            'search_intent' => 'transactional',
            'include_schema' => true,
        ],
    ];
    
    $result = ProductDataGenerator\generate_product_content(
        $product_id,
        'product_seo',
        $config
    );
    
    return $result;
}

/**
 * Example 6: Batch generate for multiple products
 */
function example_batch_generate() {
    $product_ids = [ 123, 456, 789 ];
    $results = [];
    
    foreach ( $product_ids as $product_id ) {
        $result = ProductDataGenerator\generate_product_content( $product_id );
        
        if ( ! is_wp_error( $result ) ) {
            $results[ $product_id ] = [
                'success' => true,
                'content' => $result['content'],
            ];
            
            // Save the content
            $generator = ProductDataGenerator\get_generator( 
                'product_description', 
                $product_id 
            );
            $generator->save( $result['content'] );
            
        } else {
            $results[ $product_id ] = [
                'success' => false,
                'error' => $result->get_error_message(),
            ];
        }
        
        // Add delay to avoid rate limits
        sleep( 1 );
    }
    
    return $results;
}

/**
 * Example 7: Generate content with product-specific metadata
 */
function example_with_metadata() {
    $product_id = 123;
    $product = wc_get_product( $product_id );
    
    // Get custom metadata
    $warranty = get_post_meta( $product_id, '_warranty_period', true );
    $origin = get_post_meta( $product_id, '_country_of_origin', true );
    
    $config = [
        'context' => [
            'warranty' => $warranty,
            'origin' => $origin,
            'highlight_features' => [ 'eco-friendly', 'durable', 'premium quality' ],
        ],
    ];
    
    $result = ProductDataGenerator\generate_product_content(
        $product_id,
        'product_description',
        $config
    );
    
    return $result;
}

/**
 * Example 8: WP-CLI command for generating descriptions
 * 
 * Usage: wp product-generate description <product_id>
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'product-generate', function( $args, $assoc_args ) {
        $action = $args[0] ?? 'description';
        $product_id = $args[1] ?? null;
        
        if ( ! $product_id ) {
            WP_CLI::error( 'Please provide a product ID' );
        }
        
        $template_map = [
            'description' => 'product_description',
            'short' => 'product_short_description',
            'seo' => 'product_seo',
        ];
        
        $template_id = $template_map[ $action ] ?? 'product_description';
        
        WP_CLI::log( "Generating {$action} for product {$product_id}..." );
        
        $result = ProductDataGenerator\generate_product_content( 
            $product_id, 
            $template_id 
        );
        
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }
        
        // Save the content
        $generator = ProductDataGenerator\get_generator( $template_id, $product_id );
        $saved = $generator->save( $result['content'] );
        
        if ( $saved ) {
            WP_CLI::success( "Content generated and saved!" );
            WP_CLI::log( "\nGenerated Content:\n" . $result['content'] );
        } else {
            WP_CLI::warning( "Content generated but not saved" );
        }
    });
}

/**
 * Example 9: REST API endpoint for frontend generation
 */
add_action( 'rest_api_init', function() {
    register_rest_route( 'product-data-generator/v1', '/generate', [
        'methods' => 'POST',
        'callback' => function( WP_REST_Request $request ) {
            $product_id = $request->get_param( 'product_id' );
            $template_id = $request->get_param( 'template' ) ?? 'product_description';
            $context = $request->get_param( 'context' ) ?? [];
            
            if ( ! $product_id ) {
                return new WP_Error(
                    'missing_product_id',
                    'Product ID is required',
                    [ 'status' => 400 ]
                );
            }
            
            $config = [
                'context' => $context,
            ];
            
            $result = ProductDataGenerator\generate_product_content(
                $product_id,
                $template_id,
                $config
            );
            
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            
            return rest_ensure_response( $result );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_products' );
        },
    ]);
});

/**
 * Example 10: Add custom data to product description template
 */
add_filter( 'product_data_generator_description_product_data', function( $data, $product, $template ) {
    // Add custom fields
    $product_id = $product->get_id();
    
    $data['warranty'] = get_post_meta( $product_id, '_warranty_info', true );
    $data['manufacturer'] = get_post_meta( $product_id, '_manufacturer', true );
    $data['material'] = get_post_meta( $product_id, '_material', true );
    
    // Add related products for context
    $related_ids = wc_get_related_products( $product_id, 3 );
    if ( ! empty( $related_ids ) ) {
        $data['related_products'] = array_map( function( $id ) {
            $prod = wc_get_product( $id );
            return $prod ? $prod->get_name() : '';
        }, $related_ids );
    }
    
    return $data;
}, 10, 3 );

/**
 * Example 11: Customize the AI prompt
 */
add_filter( 'product_data_generator_description_user_prompt', function( $prompt, $data, $template ) {
    // Add custom instructions to the prompt
    $prompt .= "\n\nAdditional Instructions:\n";
    $prompt .= "- Emphasize sustainability and eco-friendliness\n";
    $prompt .= "- Include a call-to-action at the end\n";
    $prompt .= "- Mention free shipping and returns\n";
    
    return $prompt;
}, 10, 3 );

/**
 * Example 12: Modify AI parameters based on product type
 */
add_filter( 'product_data_generator_ai_params', function( $params, $generator ) {
    $product = $generator->get_product();
    
    if ( ! $product ) {
        return $params;
    }
    
    // Use more tokens for variable products
    if ( $product->is_type( 'variable' ) ) {
        $params['max_tokens'] = 2000;
        $params['temperature'] = 0.7;
    }
    
    // Use GPT-4 for expensive products
    if ( $product->get_price() > 500 ) {
        $params['model'] = 'gpt-4';
    }
    
    return $params;
}, 10, 2 );

/**
 * Example 13: Post-process generated content
 */
add_filter( 'product_data_generator_generated_content', function( $result, $generator ) {
    $product = $generator->get_product();
    
    if ( ! $product ) {
        return $result;
    }
    
    // Add custom HTML wrapper
    $result['content'] = '<div class="ai-generated-content">' . $result['content'] . '</div>';
    
    // Add metadata
    $result['metadata']['word_count'] = str_word_count( 
        wp_strip_all_tags( $result['content'] ) 
    );
    
    // Store generation history
    $history = get_post_meta( $product->get_id(), '_ai_generation_history', true ) ?: [];
    $history[] = [
        'date' => current_time( 'mysql' ),
        'template' => $result['metadata']['template_id'],
        'model' => $result['metadata']['model'],
    ];
    update_post_meta( $product->get_id(), '_ai_generation_history', $history );
    
    return $result;
}, 10, 2 );

/**
 * Example 14: Schedule automatic regeneration
 */
function example_schedule_regeneration() {
    // Schedule weekly regeneration for specific products
    if ( ! wp_next_scheduled( 'pdg_weekly_regeneration' ) ) {
        wp_schedule_event( time(), 'weekly', 'pdg_weekly_regeneration' );
    }
    
    add_action( 'pdg_weekly_regeneration', function() {
        // Get products that need updating
        $products = wc_get_products( [
            'limit' => 10,
            'orderby' => 'modified',
            'order' => 'ASC',
            'return' => 'ids',
        ] );
        
        foreach ( $products as $product_id ) {
            $result = ProductDataGenerator\generate_product_content( $product_id );
            
            if ( ! is_wp_error( $result ) ) {
                $generator = ProductDataGenerator\get_generator( 
                    'product_description', 
                    $product_id 
                );
                $generator->save( $result['content'] );
            }
            
            sleep( 2 ); // Rate limiting
        }
    });
}
