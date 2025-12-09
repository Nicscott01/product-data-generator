# Product Data Generator - File Structure

## Overview
This document outlines the complete file structure of the Product Data Generator plugin's template/config system.

## Directory Structure

```
product-data-generator/
├── product-data-generator.php     # Main plugin file
├── init.php                        # Bootstrap file with autoloader
├── composer.json                   # Dependencies (wp-ai-client)
├── composer.lock
├── vendor/                         # Composer dependencies
├── TEMPLATE_SYSTEM.md             # Complete documentation
├── examples.php                    # Usage examples
├── README.md                       # Plugin README
│
└── includes/
    ├── class-template.php                          # Base Template abstract class
    ├── class-template-registry.php                 # Template registration/management
    ├── class-template-config.php                   # Configuration management
    ├── class-ai-generator.php                      # Main generator orchestration
    │
    └── templates/
        ├── class-product-description-template.php       # Full description template
        ├── class-product-short-description-template.php # Short description template
        └── class-product-seo-template.php              # SEO-optimized template
```

## Core Classes

### 1. Template (Abstract Base Class)
**File:** `includes/class-template.php`

The foundation for all templates. Defines the contract for:
- Product data aggregation
- System prompt building
- User prompt building
- Helper methods for common product data

**Key Methods:**
- `aggregate_product_data()` - Abstract method to collect product data
- `build_system_prompt()` - Abstract method for AI system instructions
- `build_user_prompt()` - Abstract method for user request
- `get_prompt_data()` - Returns complete prompt data
- `get_messages()` - Returns formatted messages for AI_Client
- `get_basic_product_data()` - Helper for common product data
- `set_product()`, `set_context()` - Setters for configuration

### 2. Template_Registry
**File:** `includes/class-template-registry.php`

Manages template registration and retrieval.

**Key Methods:**
- `register( Template $template )` - Register a template
- `unregister( string $id )` - Remove a template
- `get( string $id )` - Retrieve a template
- `get_all()` - Get all registered templates
- `is_registered( string $id )` - Check if template exists
- `get_choices()` - Get template options for select fields

### 3. Template_Config
**File:** `includes/class-template-config.php`

Configuration management with dot notation support.

**Key Methods:**
- `get( string $key, $default )` - Get config value
- `set( string $key, $value )` - Set config value
- `for_template()` - Factory method to create config
- `from_preset()` - Create from preset
- `validate()` - Validate configuration
- `get_presets()` - Get available presets

**Configuration Structure:**
```php
[
    'template_id' => 'product_description',
    'product_id' => 123,
    'context' => [],
    'ai_settings' => [
        'model' => 'gpt-4',
        'temperature' => 0.7,
        'max_tokens' => 1000,
    ],
    'output_format' => 'html',
    'auto_save' => false,
    'field_mapping' => [],
]
```

### 4. AI_Generator
**File:** `includes/class-ai-generator.php`

Orchestrates the generation process.

**Key Methods:**
- `generate()` - Generate content using AI
- `save( string $content )` - Save content to product
- `create()` - Factory method
- `from_preset()` - Create from preset
- `get_template()`, `get_product()`, `get_config()` - Getters

**Workflow:**
1. Setup (validate config, get template, get product)
2. Generate (get messages, call AI_Client, format content)
3. Save (optional, based on config)

## Built-in Templates

### 1. Product Description Template
**File:** `includes/templates/class-product-description-template.php`
**ID:** `product_description`

Generates comprehensive product descriptions.

**Aggregated Data:**
- Basic product data (name, SKU, price, categories, tags, attributes)
- Short description
- Description
- Images (featured + gallery)
- Custom meta fields (via filter)

**Context Options:**
- Custom brand voice
- Target audience
- Style preferences

### 2. Product Short Description Template
**File:** `includes/templates/class-product-short-description-template.php`
**ID:** `product_short_description`

Generates concise short descriptions.

**Aggregated Data:**
- Basic product data
- Existing descriptions
- Selling points

**Context Options:**
- `word_limit` - Maximum words (default: 50)

### 3. Product SEO Template
**File:** `includes/templates/class-product-seo-template.php`
**ID:** `product_seo`

Generates SEO-optimized content.

**Aggregated Data:**
- Basic product data
- Existing SEO metadata
- Target keywords
- Review summary
- Competitor context

**Context Options:**
- `focus_keyword` - Primary SEO keyword
- `search_intent` - User search intent
- `include_schema` - Generate JSON-LD schema

## Configuration Presets

Defined in `Template_Config::get_presets()`:

### 1. full_description
- Template: `product_description`
- Model: GPT-4
- Max tokens: 1500
- Maps to: `post_content`

### 2. short_description
- Template: `product_short_description`
- Model: GPT-3.5-turbo
- Max tokens: 200
- Word limit: 50
- Maps to: `post_excerpt`

### 3. seo_optimized
- Template: `product_description`
- Model: GPT-4
- Temperature: 0.6
- Include schema: true

## Helper Functions

Defined in `init.php`:

```php
// Get generator instance
get_generator( $template_id, $product_id, $config );

// Quick generate
generate_product_content( $product_id, $template_id, $config );
```

## Hooks Reference

### Actions

```php
// System hooks
'product_data_generator_register_templates'
'product_data_generator_template_registered'
'product_data_generator_template_unregistered'

// Generation hooks
'product_data_generator_before_generate'
'product_data_generator_after_generate'
'product_data_generator_before_save'
'product_data_generator_after_save'
```

### Filters

```php
// Data aggregation
'product_data_generator_prompt_data'
'product_data_generator_basic_product_data'
'product_data_generator_description_product_data'
'product_data_generator_short_description_product_data'
'product_data_generator_seo_product_data'

// Prompt building
'product_data_generator_messages'
'product_data_generator_description_system_prompt'
'product_data_generator_description_user_prompt'
'product_data_generator_short_description_system_prompt'
'product_data_generator_short_description_user_prompt'
'product_data_generator_seo_system_prompt'
'product_data_generator_seo_user_prompt'

// AI parameters
'product_data_generator_ai_params'

// Output
'product_data_generator_generated_content'
'product_data_generator_format_{format}'

// Configuration
'product_data_generator_config_presets'
'product_data_generator_config_validate'
```

## Usage Patterns

### Pattern 1: Simple Generation
```php
$result = ProductDataGenerator\generate_product_content( $product_id );
```

### Pattern 2: With Custom Config
```php
$config = [
    'context' => [ 'brand_voice' => 'friendly' ],
    'auto_save' => true,
];
$result = ProductDataGenerator\generate_product_content( 
    $product_id, 
    'product_description', 
    $config 
);
```

### Pattern 3: Using Presets
```php
$generator = ProductDataGenerator\AI_Generator::from_preset( 
    'short_description', 
    $product_id 
);
$result = $generator->generate();
```

### Pattern 4: Full Control
```php
$config = ProductDataGenerator\Template_Config::for_template(
    'product_description',
    $product_id,
    [
        'context' => [ 'style' => 'professional' ],
        'ai_settings' => [ 'temperature' => 0.9 ],
    ]
);

$generator = new ProductDataGenerator\AI_Generator( $config );
$result = $generator->generate();

if ( ! is_wp_error( $result ) ) {
    $generator->save( $result['content'] );
}
```

## Extension Points

### Custom Template
1. Extend `Template` class
2. Implement abstract methods
3. Register via `product_data_generator_register_templates` hook

### Custom Data Aggregation
Use filters to add product data:
- `product_data_generator_basic_product_data`
- `product_data_generator_{template}_product_data`

### Custom Prompt Modification
Use filters to modify prompts:
- `product_data_generator_{template}_system_prompt`
- `product_data_generator_{template}_user_prompt`

### Custom Configuration Preset
Use filter to add presets:
- `product_data_generator_config_presets`

### Custom Output Format
Use filter to add formats:
- `product_data_generator_format_{format_name}`

## Dependencies

- WordPress 6.0+
- PHP 7.4+
- WooCommerce 7.0+
- wordpress/wp-ai-client ^0.2

## Autoloading

The plugin uses a custom autoloader in `init.php` that:
1. Only loads classes in the `ProductDataGenerator` namespace
2. Converts class names to file paths (lowercase, dashes)
3. Handles the `Templates` subdirectory
4. Uses the `class-` prefix for files

Example:
- `ProductDataGenerator\Template` → `includes/class-template.php`
- `ProductDataGenerator\Templates\Product_SEO_Template` → `includes/templates/class-product-seo-template.php`

## Testing

See `examples.php` for 14 different usage examples covering:
- Basic generation
- Batch processing
- Custom context
- WP-CLI commands
- REST API endpoints
- Filters and hooks
- Scheduled regeneration
