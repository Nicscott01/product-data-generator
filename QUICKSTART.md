# Product Data Generator - Quick Start Guide

## Installation

1. Ensure WooCommerce is installed and activated
2. Install the WordPress AI Client dependency:
   ```bash
   cd wp-content/plugins/product-data-generator
   composer install
   ```
3. Activate the plugin in WordPress

## 5-Minute Quick Start

### 1. Basic Product Description Generation

```php
// In your theme's functions.php or a custom plugin

// Generate a description for product ID 123
$result = ProductDataGenerator\generate_product_content( 123 );

if ( ! is_wp_error( $result ) ) {
    echo $result['content'];
} else {
    echo 'Error: ' . $result->get_error_message();
}
```

### 2. Generate and Auto-Save

```php
// Generate and automatically save to the product
$config = [
    'auto_save' => true,
];

$result = ProductDataGenerator\generate_product_content( 
    123, 
    'product_description', 
    $config 
);
```

### 3. Generate Short Description

```php
// Use the short description template
$result = ProductDataGenerator\generate_product_content( 
    123, 
    'product_short_description' 
);
```

### 4. Use a Preset

```php
// Use a pre-configured preset
$generator = ProductDataGenerator\AI_Generator::from_preset( 
    'short_description', 
    123 
);

$result = $generator->generate();

if ( ! is_wp_error( $result ) ) {
    // Manually save the result
    $generator->save( $result['content'] );
}
```

### 5. Add Custom Context

```php
// Provide additional context for better results
$config = [
    'context' => [
        'brand_voice' => 'professional and trustworthy',
        'target_audience' => 'eco-conscious consumers',
        'tone' => 'warm and informative',
    ],
];

$result = ProductDataGenerator\generate_product_content( 
    123, 
    'product_description', 
    $config 
);
```

## Common Use Cases

### Bulk Generate Descriptions

```php
// Get all products without descriptions
$products = wc_get_products([
    'limit' => -1,
    'return' => 'ids',
]);

foreach ( $products as $product_id ) {
    $product = wc_get_product( $product_id );
    
    // Skip if already has a description
    if ( ! empty( $product->get_description() ) ) {
        continue;
    }
    
    // Generate and save
    $config = [ 'auto_save' => true ];
    $result = ProductDataGenerator\generate_product_content( 
        $product_id, 
        'product_description', 
        $config 
    );
    
    if ( ! is_wp_error( $result ) ) {
        error_log( "Generated description for product {$product_id}" );
    }
    
    // Rate limiting - wait 2 seconds between requests
    sleep( 2 );
}
```

### Add Custom Product Data

```php
// Add custom fields to the product data aggregation
add_filter( 'product_data_generator_description_product_data', function( $data, $product ) {
    $product_id = $product->get_id();
    
    // Add custom fields
    $data['warranty'] = get_post_meta( $product_id, '_warranty_info', true );
    $data['features'] = get_post_meta( $product_id, '_key_features', true );
    
    return $data;
}, 10, 2 );

// Now generate with the additional data
$result = ProductDataGenerator\generate_product_content( 123 );
```

### Create a Custom Template

```php
// 1. Create your template class
class My_Custom_Template extends ProductDataGenerator\Template {
    
    public function __construct() {
        parent::__construct(
            'my_custom_template',
            'My Custom Template',
            'Description of what this template does'
        );
    }
    
    protected function aggregate_product_data() {
        $data = $this->get_basic_product_data();
        // Add your custom data
        return $data;
    }
    
    protected function build_system_prompt() {
        return "You are an expert copywriter...";
    }
    
    protected function build_user_prompt() {
        $data = $this->aggregate_product_data();
        return "Generate content for: " . $data['name'];
    }
}

// 2. Register the template
add_action( 'product_data_generator_register_templates', function() {
    ProductDataGenerator\Template_Registry::register( 
        new My_Custom_Template() 
    );
});

// 3. Use your template
$result = ProductDataGenerator\generate_product_content( 
    123, 
    'my_custom_template' 
);
```

## WP-CLI Usage

If you've set up the WP-CLI command from examples.php:

```bash
# Generate description
wp product-generate description 123

# Generate short description
wp product-generate short 123

# Generate SEO content
wp product-generate seo 123
```

## REST API Usage

If you've set up the REST endpoint from examples.php:

```bash
# POST request to generate content
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 123, "template": "product_description"}' \
  https://yoursite.com/wp-json/product-data-generator/v1/generate
```

## Available Templates

### product_description
Full, comprehensive product descriptions (300-500 words)

### product_short_description
Concise summaries (50 words or less)

### product_seo
SEO-optimized content with keyword integration

## Available Presets

### full_description
- Uses GPT-4
- 1500 max tokens
- Saves to product description

### short_description
- Uses GPT-3.5-turbo
- 50-word limit
- Saves to short description

### seo_optimized
- Uses GPT-4
- Lower temperature for consistency
- Includes schema markup option

## Configuration Options

```php
$config = [
    // Template to use
    'template_id' => 'product_description',
    
    // Product ID
    'product_id' => 123,
    
    // Custom context data
    'context' => [
        'brand_voice' => 'professional',
        'target_audience' => 'millennials',
        'word_limit' => 250,
        'focus_keyword' => 'organic cotton shirt',
    ],
    
    // AI settings
    'ai_settings' => [
        'model' => 'gpt-4',              // or 'gpt-3.5-turbo'
        'temperature' => 0.7,             // 0-1, higher = more creative
        'max_tokens' => 1000,             // max response length
    ],
    
    // Output format
    'output_format' => 'html',           // html, text, markdown
    
    // Auto-save to product
    'auto_save' => false,
    
    // Field mapping for saving
    'field_mapping' => [
        'description' => 'post_content',
        'short_description' => 'post_excerpt',
    ],
];
```

## Troubleshooting

### "Template not found" error
```php
// Check if template is registered
$templates = ProductDataGenerator\Template_Registry::get_all();
var_dump( array_keys( $templates ) );
```

### "Product not found" error
```php
// Verify product exists
$product = wc_get_product( 123 );
if ( ! $product ) {
    echo "Product does not exist";
}
```

### AI Client errors
```php
// Ensure AI Client is available
if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
    echo "WordPress AI Client not found. Run: composer install";
}
```

### Check generation result
```php
$result = ProductDataGenerator\generate_product_content( 123 );

if ( is_wp_error( $result ) ) {
    // Error occurred
    echo "Error: " . $result->get_error_message();
    echo "Code: " . $result->get_error_code();
} else {
    // Success
    echo "Generated content: " . $result['content'];
    echo "Model used: " . $result['metadata']['model'];
    echo "Generated at: " . $result['metadata']['generated_at'];
}
```

## Best Practices

1. **Always check for errors**: Use `is_wp_error()` on results
2. **Rate limiting**: Add delays when batch processing
3. **Test first**: Generate without auto-save, review, then save manually
4. **Provide context**: The more context you provide, the better the results
5. **Use appropriate models**: GPT-3.5-turbo for simple tasks, GPT-4 for complex
6. **Monitor costs**: AI API calls cost money; use wisely
7. **Cache results**: Store generated content to avoid regenerating unnecessarily

## Next Steps

- Read [TEMPLATE_SYSTEM.md](TEMPLATE_SYSTEM.md) for comprehensive documentation
- Check [examples.php](examples.php) for 14 detailed examples
- Review [FILE_STRUCTURE.md](FILE_STRUCTURE.md) for architecture details
- Create custom templates for your specific needs
- Set up filters to add your custom product data

## Support

For issues or questions:
1. Check the documentation files
2. Review the examples
3. Examine the source code (well-commented)
4. Test with a single product first

## License

GPL v2 or later
