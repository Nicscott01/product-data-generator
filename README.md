# Product Data Generator

A powerful WooCommerce plugin that uses AI to automatically generate product descriptions, short descriptions, and other product content. Features a flexible template/config system for customizing AI prompts and data aggregation.

## Features

- 🤖 **AI-Powered Content Generation** - Uses WordPress AI Client with GPT models
- 📝 **Multiple Templates** - Built-in templates for descriptions, short descriptions, and SEO content
- ⚙️ **Flexible Configuration** - Extensive customization through config system and hooks
- 🎨 **Custom Templates** - Easy to create your own templates for specific needs
- 🔌 **Developer-Friendly** - Comprehensive hooks, filters, and extension points
- 📦 **Preset Configurations** - Pre-configured settings for common use cases
- 💾 **Auto-Save** - Optionally save generated content automatically
- 🎯 **Context-Aware** - Provide custom context for better, more targeted content

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce 7.0+
- [WordPress AI Client](https://github.com/WordPress/wp-ai-client) ^0.2

## Installation

1. Upload the plugin to `/wp-content/plugins/product-data-generator/`
2. Run `composer install` in the plugin directory
3. Activate the plugin through WordPress admin
4. Ensure WooCommerce is installed and activated

```bash
cd wp-content/plugins/product-data-generator
composer install
```

## Quick Usage

### Basic Example

```php
// Generate a product description
$result = ProductDataGenerator\generate_product_content( $product_id );

if ( ! is_wp_error( $result ) ) {
    echo $result['content'];
}
```

### With Custom Context

```php
$config = [
    'context' => [
        'brand_voice' => 'professional and friendly',
        'target_audience' => 'young professionals',
    ],
    'auto_save' => true,
];

$result = ProductDataGenerator\generate_product_content( 
    $product_id, 
    'product_description', 
    $config 
);
```

### Using Presets

```php
// Use a preset configuration
$generator = ProductDataGenerator\AI_Generator::from_preset( 
    'short_description', 
    $product_id 
);

$result = $generator->generate();
$generator->save( $result['content'] );
```

## Built-in Templates

### Product Description (`product_description`)
Generates comprehensive, engaging product descriptions optimized for e-commerce.

**Features:**
- 300-500 words
- SEO-friendly
- Highlights benefits and features
- Professional tone

### Product Short Description (`product_short_description`)
Creates concise, impactful product summaries.

**Features:**
- 50 words or less (configurable)
- Attention-grabbing
- Focuses on key benefits
- Perfect for product cards

### Product SEO (`product_seo`)
Generates SEO-optimized content with keyword integration.

**Features:**
- Keyword-optimized
- Structured with headings
- Search intent matching
- Schema markup support

## Architecture

The plugin uses a modular architecture with four core components:

1. **Template** - Base class defining data aggregation and prompt building
2. **Template Registry** - Manages template registration and retrieval
3. **Template Config** - Configuration management with presets
4. **AI Generator** - Orchestrates the generation process

See [FILE_STRUCTURE.md](FILE_STRUCTURE.md) for detailed architecture documentation.

## Configuration

### Available Options

```php
$config = [
    'template_id' => 'product_description',
    'product_id' => 123,
    'context' => [
        'brand_voice' => 'professional',
        'target_audience' => 'millennials',
        'word_limit' => 250,
    ],
    'ai_settings' => [
        'model' => 'gpt-4',
        'temperature' => 0.7,
        'max_tokens' => 1000,
    ],
    'output_format' => 'html',
    'auto_save' => false,
    'field_mapping' => [
        'description' => 'post_content',
    ],
];
```

### Available Presets

- `full_description` - GPT-4, comprehensive descriptions
- `short_description` - GPT-3.5-turbo, concise summaries
- `seo_optimized` - GPT-4, SEO-focused content

## Customization

### Add Custom Product Data

```php
add_filter( 'product_data_generator_description_product_data', function( $data, $product ) {
    $data['warranty'] = get_post_meta( $product->get_id(), '_warranty', true );
    $data['features'] = get_post_meta( $product->get_id(), '_features', true );
    return $data;
}, 10, 2 );
```

### Modify AI Prompts

```php
add_filter( 'product_data_generator_description_user_prompt', function( $prompt, $data ) {
    $prompt .= "\n\nEmphasize eco-friendliness and sustainability.";
    return $prompt;
}, 10, 2 );
```

### Create Custom Template

```php
class My_Template extends ProductDataGenerator\Template {
    // Implement required methods
    protected function aggregate_product_data() { /* ... */ }
    protected function build_system_prompt() { /* ... */ }
    protected function build_user_prompt() { /* ... */ }
}

// Register it
add_action( 'product_data_generator_register_templates', function() {
    ProductDataGenerator\Template_Registry::register( new My_Template() );
});
```

## Hooks & Filters

### Key Actions

- `product_data_generator_register_templates` - Register custom templates
- `product_data_generator_before_generate` - Before content generation
- `product_data_generator_after_generate` - After content generation
- `product_data_generator_before_save` - Before saving content
- `product_data_generator_after_save` - After saving content

### Key Filters

- `product_data_generator_prompt_data` - Modify aggregated prompt data
- `product_data_generator_messages` - Modify AI messages
- `product_data_generator_ai_params` - Modify AI request parameters
- `product_data_generator_generated_content` - Post-process generated content
- `product_data_generator_config_presets` - Add custom presets

See [TEMPLATE_SYSTEM.md](TEMPLATE_SYSTEM.md) for complete hooks documentation.

## Documentation

- **[QUICKSTART.md](QUICKSTART.md)** - Get started in 5 minutes
- **[TEMPLATE_SYSTEM.md](TEMPLATE_SYSTEM.md)** - Comprehensive system documentation
- **[FILE_STRUCTURE.md](FILE_STRUCTURE.md)** - Architecture and file structure
- **[examples.php](examples.php)** - 14 practical code examples

## Examples

### Batch Generate

```php
$product_ids = [123, 456, 789];

foreach ( $product_ids as $product_id ) {
    $result = ProductDataGenerator\generate_product_content( $product_id );
    if ( ! is_wp_error( $result ) ) {
        $generator = ProductDataGenerator\get_generator( 'product_description', $product_id );
        $generator->save( $result['content'] );
    }
    sleep( 2 ); // Rate limiting
}
```

### WP-CLI Integration

```php
WP_CLI::add_command( 'product generate', function( $args ) {
    $product_id = $args[0];
    $result = ProductDataGenerator\generate_product_content( $product_id );
    // ...
});
```

### REST API

```php
register_rest_route( 'pdg/v1', '/generate/(?P<id>\d+)', [
    'methods' => 'POST',
    'callback' => function( $request ) {
        return ProductDataGenerator\generate_product_content( 
            $request->get_param( 'id' ) 
        );
    },
]);
```

See [examples.php](examples.php) for more complete examples.

## Best Practices

1. ✅ Always check for `WP_Error` responses
2. ✅ Use presets for consistency
3. ✅ Provide context for better results
4. ✅ Add rate limiting for batch operations
5. ✅ Test generation before auto-saving
6. ✅ Monitor AI API costs
7. ✅ Cache generated content when possible

## Troubleshooting

### Common Issues

**"Template not found"**
```php
// Check registered templates
$templates = ProductDataGenerator\Template_Registry::get_all();
var_dump( array_keys( $templates ) );
```

**"WordPress AI Client not found"**
```bash
cd wp-content/plugins/product-data-generator
composer install
```

**Generation errors**
```php
$result = ProductDataGenerator\generate_product_content( 123 );
if ( is_wp_error( $result ) ) {
    error_log( $result->get_error_message() );
}
```

## Roadmap

- [ ] Admin UI for easy generation from product edit screen
- [ ] Bulk generation admin interface
- [ ] Generation history and versioning
- [ ] A/B testing for generated content
- [ ] Multi-language support
- [ ] More built-in templates
- [ ] Analytics and performance tracking

## Contributing

Contributions are welcome! Please:

1. Follow WordPress coding standards
2. Add comprehensive docblocks
3. Include examples for new features
4. Test with multiple product types
5. Update documentation

## Support

- **Documentation**: Start with [QUICKSTART.md](QUICKSTART.md)
- **Examples**: Check [examples.php](examples.php)
- **Issues**: Review error messages and logs
- **Code**: All classes are well-documented

## Credits

- **Author**: Nic Scott
- **Company**: [Creare Web Solutions](https://crearewebsolutions.com)
- **License**: GPL v2 or later

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Changelog

### 0.0.1 (Current)
- Initial release
- Template/config system implementation
- Three built-in templates
- Comprehensive documentation
- Example code and usage patterns
