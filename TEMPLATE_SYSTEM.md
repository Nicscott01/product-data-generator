# Product Data Generator - Template System

A flexible template and configuration system for aggregating product data and generating AI-powered content for WooCommerce products.

## Architecture Overview

The system consists of four main components:

1. **Template** - Base class for defining data aggregation and prompt building
2. **Template Registry** - Manages registration and retrieval of templates
3. **Template Config** - Configuration management for generation requests
4. **AI Generator** - Orchestrates the generation process

## Quick Start

### Basic Usage

```php
// Generate a product description
$result = ProductDataGenerator\generate_product_content( $product_id );

if ( ! is_wp_error( $result ) ) {
    echo $result['content'];
}
```

### Using Presets

```php
// Use a preset configuration
$generator = ProductDataGenerator\AI_Generator::from_preset( 'short_description', $product_id );
$result = $generator->generate();

// Auto-save the result
$generator->save( $result['content'] );
```

### Custom Configuration

```php
use ProductDataGenerator\AI_Generator;

$config = [
    'context' => [
        'brand_voice' => 'professional and friendly',
        'target_audience' => 'young professionals',
        'word_limit' => 250,
    ],
    'ai_settings' => [
        'model' => 'gpt-4',
        'temperature' => 0.8,
        'max_tokens' => 1500,
    ],
    'auto_save' => true,
];

$generator = AI_Generator::create( 'product_description', $product_id, $config );
$result = $generator->generate();
```

## Creating Custom Templates

### Step 1: Extend the Template Class

```php
<?php
namespace ProductDataGenerator\Templates;

use ProductDataGenerator\Template;

class Product_Features_Template extends Template {
    
    public function __construct() {
        parent::__construct(
            'product_features',
            __( 'Product Features List', 'product-data-generator' ),
            __( 'Generate a bullet-point list of product features', 'product-data-generator' )
        );
    }
    
    protected function aggregate_product_data() {
        $data = $this->get_basic_product_data();
        
        // Add custom data
        if ( $this->product ) {
            $data['specifications'] = get_post_meta( 
                $this->product->get_id(), 
                '_specifications', 
                true 
            );
        }
        
        return $data;
    }
    
    protected function build_system_prompt() {
        return "You are an expert at creating clear, concise feature lists. " .
               "Focus on highlighting key benefits and technical specifications.";
    }
    
    protected function build_user_prompt() {
        $data = $this->aggregate_product_data();
        
        $prompt = "Create a feature list for: {$data['name']}\n\n";
        
        if ( ! empty( $data['specifications'] ) ) {
            $prompt .= "Specifications:\n" . $data['specifications'] . "\n\n";
        }
        
        $prompt .= "Create a bullet-point list of 5-7 key features.";
        
        return $prompt;
    }
}
```

### Step 2: Register the Template

```php
add_action( 'product_data_generator_register_templates', function() {
    ProductDataGenerator\Template_Registry::register( 
        new ProductDataGenerator\Templates\Product_Features_Template() 
    );
});
```

### Step 3: Use Your Template

```php
$result = ProductDataGenerator\generate_product_content( 
    $product_id, 
    'product_features' 
);
```

## Built-in Templates

### Product Description Template
**ID:** `product_description`

Generates comprehensive product descriptions optimized for e-commerce.

**Data Aggregated:**
- Basic product data (name, SKU, price, categories, tags)
- Attributes
- Images and gallery
- Existing descriptions
- Custom meta fields (via filter)

**Context Options:**
- `focus_keyword` - SEO keyword to focus on
- `include_schema` - Whether to include schema markup

### Product Short Description Template
**ID:** `product_short_description`

Generates concise, impactful short descriptions.

**Data Aggregated:**
- Basic product data
- Existing descriptions
- Selling points

**Context Options:**
- `word_limit` - Maximum word count (default: 50)

## Configuration Presets

Presets provide pre-configured settings for common use cases:

### Available Presets

```php
// Full description with GPT-4
$generator = AI_Generator::from_preset( 'full_description', $product_id );

// Short description with GPT-3.5-turbo
$generator = AI_Generator::from_preset( 'short_description', $product_id );

// SEO-optimized description
$generator = AI_Generator::from_preset( 'seo_optimized', $product_id );
```

### Creating Custom Presets

```php
add_filter( 'product_data_generator_config_presets', function( $presets ) {
    $presets['my_custom_preset'] = [
        'template_id' => 'product_description',
        'context' => [
            'brand_voice' => 'luxury',
            'style' => 'persuasive',
        ],
        'ai_settings' => [
            'model' => 'gpt-4',
            'temperature' => 0.9,
            'max_tokens' => 2000,
        ],
        'auto_save' => true,
    ];
    
    return $presets;
});
```

## Hooks & Filters

### Actions

```php
// Before content generation
do_action( 'product_data_generator_before_generate', $generator );

// After content generation
do_action( 'product_data_generator_after_generate', $result, $generator );

// Before saving content
do_action( 'product_data_generator_before_save', $content, $product, $generator );

// After saving content
do_action( 'product_data_generator_after_save', $content, $product, $generator );

// When a template is registered
do_action( 'product_data_generator_template_registered', $id, $template );

// When templates are being registered
do_action( 'product_data_generator_register_templates' );
```

### Filters

```php
// Filter aggregated prompt data
apply_filters( 'product_data_generator_prompt_data', $data, $template, $product );

// Filter AI messages
apply_filters( 'product_data_generator_messages', $messages, $data, $template );

// Filter basic product data
apply_filters( 'product_data_generator_basic_product_data', $data, $product, $template );

// Filter AI request parameters
apply_filters( 'product_data_generator_ai_params', $params, $generator );

// Filter generated content
apply_filters( 'product_data_generator_generated_content', $result, $generator );

// Filter configuration presets
apply_filters( 'product_data_generator_config_presets', $presets );

// Template-specific filters
apply_filters( 'product_data_generator_description_product_data', $data, $product, $template );
apply_filters( 'product_data_generator_description_system_prompt', $prompt, $template );
apply_filters( 'product_data_generator_description_user_prompt', $prompt, $data, $template );
```

## Advanced Usage

### Adding Custom Context Data

```php
add_filter( 'product_data_generator_prompt_data', function( $data, $template, $product ) {
    // Add store-wide context
    $data['context']['store_name'] = get_bloginfo( 'name' );
    $data['context']['brand_guidelines'] = get_option( 'brand_guidelines' );
    
    return $data;
}, 10, 3 );
```

### Customizing Product Data Aggregation

```php
add_filter( 'product_data_generator_description_product_data', function( $data, $product, $template ) {
    // Add custom fields
    $data['warranty'] = get_post_meta( $product->get_id(), '_warranty_info', true );
    $data['manufacturer'] = get_post_meta( $product->get_id(), '_manufacturer', true );
    
    // Add related products
    $related_ids = wc_get_related_products( $product->get_id(), 3 );
    $data['related_products'] = array_map( function( $id ) {
        $prod = wc_get_product( $id );
        return $prod->get_name();
    }, $related_ids );
    
    return $data;
}, 10, 3 );
```

### Modifying AI Parameters

```php
add_filter( 'product_data_generator_ai_params', function( $params, $generator ) {
    $product = $generator->get_product();
    
    // Use different models based on product type
    if ( $product->is_type( 'variable' ) ) {
        $params['model'] = 'gpt-4';
        $params['max_tokens'] = 2000;
    }
    
    return $params;
}, 10, 2 );
```

### Post-Processing Generated Content

```php
add_filter( 'product_data_generator_generated_content', function( $result, $generator ) {
    // Add custom HTML wrapper
    $result['content'] = '<div class="ai-generated">' . $result['content'] . '</div>';
    
    // Add metadata
    $result['metadata']['word_count'] = str_word_count( 
        wp_strip_all_tags( $result['content'] ) 
    );
    
    return $result;
}, 10, 2 );
```

### Custom Output Formatting

```php
add_filter( 'product_data_generator_format_json', function( $content, $generator ) {
    // Parse AI output as JSON
    $data = json_decode( $content, true );
    
    if ( json_last_error() === JSON_ERROR_NONE ) {
        // Process structured data
        return $data;
    }
    
    return $content;
}, 10, 2 );
```

## Configuration Options

### Template Config Options

```php
$config = [
    // Required
    'template_id' => 'product_description',
    'product_id' => 123,
    
    // Optional
    'context' => [
        // Custom context data passed to template
    ],
    'ai_settings' => [
        'model' => 'gpt-4',           // AI model to use
        'temperature' => 0.7,          // Creativity (0-1)
        'max_tokens' => 1000,          // Maximum response length
    ],
    'output_format' => 'html',         // html, text, markdown, or custom
    'auto_save' => false,              // Automatically save to product
    'field_mapping' => [
        // Map output to product fields
        'description' => 'post_content',
        'short_description' => 'post_excerpt',
    ],
];
```

## Examples

### Batch Generate Descriptions

```php
$product_ids = [123, 456, 789];

foreach ( $product_ids as $product_id ) {
    $generator = ProductDataGenerator\AI_Generator::from_preset( 
        'full_description', 
        $product_id 
    );
    
    $result = $generator->generate();
    
    if ( ! is_wp_error( $result ) ) {
        $generator->save( $result['content'] );
        error_log( "Generated description for product {$product_id}" );
    }
}
```

### WP-CLI Integration

```php
WP_CLI::add_command( 'product generate', function( $args, $assoc_args ) {
    $product_id = $args[0];
    $template = $assoc_args['template'] ?? 'product_description';
    
    $result = ProductDataGenerator\generate_product_content( $product_id, $template );
    
    if ( is_wp_error( $result ) ) {
        WP_CLI::error( $result->get_error_message() );
    } else {
        WP_CLI::success( "Generated content for product {$product_id}" );
        WP_CLI::log( $result['content'] );
    }
});
```

### REST API Endpoint

```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'pdg/v1', '/generate/(?P<id>\d+)', [
        'methods' => 'POST',
        'callback' => function( $request ) {
            $product_id = $request->get_param( 'id' );
            $template = $request->get_param( 'template' ) ?? 'product_description';
            
            $result = ProductDataGenerator\generate_product_content( 
                $product_id, 
                $template 
            );
            
            if ( is_wp_error( $result ) ) {
                return new WP_Error( 
                    'generation_failed', 
                    $result->get_error_message(), 
                    [ 'status' => 400 ] 
                );
            }
            
            return rest_ensure_response( $result );
        },
        'permission_callback' => function() {
            return current_user_can( 'edit_products' );
        },
    ]);
});
```

## Best Practices

1. **Use Presets** - Create presets for common use cases to maintain consistency
2. **Add Context** - Provide brand guidelines, target audience, and style preferences in context
3. **Filter Wisely** - Use filters to add store-specific data without modifying core templates
4. **Error Handling** - Always check for WP_Error responses
5. **Test Templates** - Test custom templates with various product types
6. **Monitor Costs** - Different AI models have different costs; choose appropriately
7. **Cache Results** - Consider caching generated content to avoid redundant API calls

## Troubleshooting

### Template Not Found
Ensure the template is registered before use:
```php
if ( ! ProductDataGenerator\Template_Registry::is_registered( 'my_template' ) ) {
    // Register or handle error
}
```

### Product Data Not Aggregating
Check if the product exists and filters are working:
```php
add_filter( 'product_data_generator_basic_product_data', function( $data ) {
    error_log( print_r( $data, true ) );
    return $data;
});
```

### AI Client Not Available
Ensure the WordPress AI Client plugin is installed and initialized:
```php
if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
    // Install wordpress/wp-ai-client
}
```
