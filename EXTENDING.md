# Extending Product Data Generator

This guide shows how other plugins can extend the Product Data Generator to add custom templates.

## Overview

The Product Data Generator uses an extensible architecture that allows other plugins to:
1. Create custom template classes
2. Register them with the Template Registry
3. Use all the built-in functionality (AI generation, REST API, admin UI)

## Architecture

### Key Components

1. **`Template` (Abstract Class)**: Base class all templates extend
2. **`Template_Registry`**: Manages template registration
3. **Hook System**: Provides integration points

### Template Lifecycle

```
Plugin Init → Register Templates → Template Available → Generate Content → Save Result
```

## Creating a Custom Template

### Step 1: Create Your Template Class

Create a new template by extending the `ProductDataGenerator\Template` abstract class:

```php
<?php
/**
 * Plugin Name: Book Genre Generator
 * Description: Adds genre detection template to Product Data Generator
 * Version: 1.0.0
 */

namespace BookGenreGenerator;

use ProductDataGenerator\Template;

class Book_Genre_Template extends Template {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'book_genre',  // Unique template ID
            __( 'Book Genre', 'book-genre-generator' ),  // Template name
            __( 'Automatically determine the genre(s) for a book based on its metadata', 'book-genre-generator' )  // Description
        );
    }

    /**
     * Aggregate product data for genre detection
     *
     * Collect all relevant data that will help the AI determine the genre
     *
     * @return array
     */
    protected function aggregate_product_data() {
        // Start with basic product data
        $data = $this->get_basic_product_data();

        if ( ! $this->product ) {
            return $data;
        }

        $product_id = $this->product->get_id();

        // Add book-specific metadata
        $data['book_data'] = array(
            'subtitle' => get_post_meta( $product_id, 'book_subtitle', true ),
            'author' => get_post_meta( $product_id, 'book_author', true ),
            'publisher' => get_post_meta( $product_id, 'book_publisher', true ),
            'published_date' => get_post_meta( $product_id, 'book_published_date', true ),
            'pages' => get_post_meta( $product_id, 'book_pages', true ),
            'maturity_rating' => get_post_meta( $product_id, 'book_maturity_rating', true ),
        );

        // Add description for context
        $data['description'] = $this->product->get_description();
        $data['short_description'] = $this->product->get_short_description();

        // Add API data if available
        $isbndb_data = get_post_meta( $product_id, '_isbndb_raw_response', true );
        if ( $isbndb_data ) {
            $isbndb_decoded = json_decode( $isbndb_data, true );
            if ( isset( $isbndb_decoded['book']['subjects'] ) ) {
                $data['existing_subjects'] = $isbndb_decoded['book']['subjects'];
            }
        }

        $google_data = get_post_meta( $product_id, '_google_books_raw_response', true );
        if ( $google_data ) {
            $google_decoded = json_decode( $google_data, true );
            if ( isset( $google_decoded['items'][0]['volumeInfo']['categories'] ) ) {
                $data['google_categories'] = $google_decoded['items'][0]['volumeInfo']['categories'];
            }
        }

        /**
         * Filter product data for genre detection
         *
         * @param array $data Product data
         * @param \WC_Product $product Product object
         * @param Book_Genre_Template $template Template instance
         */
        return apply_filters( 'book_genre_generator_product_data', $data, $this->product, $this );
    }

    /**
     * Build the system prompt
     *
     * Define instructions for the AI on how to determine genres
     *
     * @return string
     */
    protected function build_system_prompt() {
        $prompt = "You are an expert literary analyst and book categorization specialist. ";
        $prompt .= "Your task is to accurately determine the most appropriate genre(s) for books based on their metadata, descriptions, and subject classifications. ";
        $prompt .= "\n\nGENRE CLASSIFICATION RULES:\n";
        $prompt .= "- Identify 1-3 most relevant genres for the book\n";
        $prompt .= "- Use standard book industry genre classifications\n";
        $prompt .= "- Consider both primary and secondary genres\n";
        $prompt .= "- Be specific but not overly narrow\n";
        $prompt .= "- Common genres include: Fiction, Mystery, Thriller, Romance, Science Fiction, Fantasy, Historical Fiction, Literary Fiction, Young Adult, Children's, Biography, History, Self-Help, Business, etc.\n";
        $prompt .= "\n\nOUTPUT FORMAT:\n";
        $prompt .= "Return ONLY a JSON object with this exact structure:\n";
        $prompt .= "{\n";
        $prompt .= '  "primary_genre": "Genre Name",\n';
        $prompt .= '  "secondary_genre": "Genre Name" (optional),\n';
        $prompt .= '  "tertiary_genre": "Genre Name" (optional),\n';
        $prompt .= '  "confidence": "high|medium|low",\n';
        $prompt .= '  "reasoning": "Brief explanation of why these genres were chosen"\n';
        $prompt .= "}\n";
        $prompt .= "\nDo not include any text outside the JSON object.";

        /**
         * Filter the system prompt for genre detection
         *
         * @param string $prompt System prompt
         * @param Book_Genre_Template $template Template instance
         */
        return apply_filters( 'book_genre_generator_system_prompt', $prompt, $this );
    }

    /**
     * Build the user prompt
     *
     * Create the actual request with product data
     *
     * @return string
     */
    protected function build_user_prompt() {
        $data = $this->aggregate_product_data();
        
        $prompt = "Determine the genre(s) for the following book:\n\n";
        
        $prompt .= "=== BOOK INFORMATION ===\n\n";
        
        // Title
        if ( ! empty( $data['name'] ) ) {
            $prompt .= "Title: {$data['name']}\n";
        }

        // Subtitle
        if ( ! empty( $data['book_data']['subtitle'] ) ) {
            $prompt .= "Subtitle: {$data['book_data']['subtitle']}\n";
        }

        // Author
        if ( ! empty( $data['book_data']['author'] ) ) {
            $prompt .= "Author: {$data['book_data']['author']}\n";
        }

        // Publisher
        if ( ! empty( $data['book_data']['publisher'] ) ) {
            $prompt .= "Publisher: {$data['book_data']['publisher']}\n";
        }

        // Maturity Rating
        if ( ! empty( $data['book_data']['maturity_rating'] ) ) {
            $prompt .= "Maturity Rating: {$data['book_data']['maturity_rating']}\n";
        }

        // Description
        if ( ! empty( $data['description'] ) ) {
            $prompt .= "\n=== DESCRIPTION ===\n\n";
            $prompt .= wp_strip_all_tags( $data['description'] ) . "\n";
        }

        // Existing classifications
        if ( ! empty( $data['existing_subjects'] ) ) {
            $prompt .= "\n=== EXISTING SUBJECT CLASSIFICATIONS ===\n\n";
            $prompt .= implode( ', ', $data['existing_subjects'] ) . "\n";
        }

        if ( ! empty( $data['google_categories'] ) ) {
            $prompt .= "\n=== GOOGLE BOOKS CATEGORIES ===\n\n";
            $prompt .= implode( ', ', $data['google_categories'] ) . "\n";
        }

        // Product categories (may provide hints)
        if ( ! empty( $data['categories'] ) ) {
            $prompt .= "\n=== STORE CATEGORIES ===\n\n";
            $prompt .= implode( ', ', $data['categories'] ) . "\n";
        }

        $prompt .= "\nPlease analyze this information and return the appropriate genre classification in JSON format.";

        /**
         * Filter the user prompt for genre detection
         *
         * @param string $prompt User prompt
         * @param array $data Aggregated product data
         * @param Book_Genre_Template $template Template instance
         */
        return apply_filters( 'book_genre_generator_user_prompt', $prompt, $data, $this );
    }
}
```

### Step 2: Register Your Template

Hook into the template registration system:

```php
/**
 * Register the genre template with Product Data Generator
 */
add_action( 'product_data_generator_register_templates', function() {
    // Make sure the Template Registry is available
    if ( ! class_exists( 'ProductDataGenerator\Template_Registry' ) ) {
        return;
    }

    // Register your custom template
    \ProductDataGenerator\Template_Registry::register( 
        new \BookGenreGenerator\Book_Genre_Template() 
    );
});
```

### Step 3: Handle the Response (Optional)

Add custom handling for the AI response:

```php
/**
 * Process genre detection results
 */
add_action( 'product_data_generator_content_generated', function( $content, $template_id, $product_id ) {
    // Only handle our template's responses
    if ( $template_id !== 'book_genre' ) {
        return;
    }

    // Parse the JSON response
    $genre_data = json_decode( $content, true );
    
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $genre_data ) ) {
        error_log( 'Failed to parse genre data: ' . $content );
        return;
    }

    // Save to product meta
    if ( ! empty( $genre_data['primary_genre'] ) ) {
        update_post_meta( $product_id, '_book_primary_genre', sanitize_text_field( $genre_data['primary_genre'] ) );
    }

    if ( ! empty( $genre_data['secondary_genre'] ) ) {
        update_post_meta( $product_id, '_book_secondary_genre', sanitize_text_field( $genre_data['secondary_genre'] ) );
    }

    // Create/assign to WooCommerce category
    $genre_term = get_term_by( 'name', $genre_data['primary_genre'], 'product_cat' );
    
    if ( ! $genre_term ) {
        $result = wp_insert_term( $genre_data['primary_genre'], 'product_cat' );
        if ( ! is_wp_error( $result ) ) {
            $genre_term_id = $result['term_id'];
        }
    } else {
        $genre_term_id = $genre_term->term_id;
    }

    if ( isset( $genre_term_id ) ) {
        wp_set_object_terms( $product_id, array( $genre_term_id ), 'product_cat', true );
    }

    // Log the result
    error_log( sprintf(
        'Genre detected for product #%d: %s (Confidence: %s)',
        $product_id,
        $genre_data['primary_genre'] ?? 'Unknown',
        $genre_data['confidence'] ?? 'unknown'
    ) );

}, 10, 3 );
```

## Complete Plugin Example

Here's a complete, working plugin:

```php
<?php
/**
 * Plugin Name: Book Genre Generator
 * Plugin URI: https://example.com
 * Description: Adds AI-powered book genre detection to Product Data Generator
 * Version: 1.0.0
 * Author: Your Name
 * Requires Plugins: product-data-generator
 * Text Domain: book-genre-generator
 */

namespace BookGenreGenerator;

use ProductDataGenerator\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Main Plugin Class
 */
class Plugin {
    
    /**
     * Initialize the plugin
     */
    public static function init() {
        // Check if Product Data Generator is active
        if ( ! class_exists( 'ProductDataGenerator\Template' ) ) {
            add_action( 'admin_notices', [ __CLASS__, 'dependency_notice' ] );
            return;
        }

        // Register our template
        add_action( 'product_data_generator_register_templates', [ __CLASS__, 'register_template' ] );
        
        // Handle AI responses
        add_action( 'product_data_generator_content_generated', [ __CLASS__, 'handle_response' ], 10, 3 );
    }

    /**
     * Show dependency notice
     */
    public static function dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php esc_html_e( 'Book Genre Generator requires the Product Data Generator plugin to be installed and activated.', 'book-genre-generator' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Register the genre template
     */
    public static function register_template() {
        require_once __DIR__ . '/includes/class-book-genre-template.php';
        
        \ProductDataGenerator\Template_Registry::register( 
            new Book_Genre_Template() 
        );
    }

    /**
     * Handle genre detection response
     */
    public static function handle_response( $content, $template_id, $product_id ) {
        if ( $template_id !== 'book_genre' ) {
            return;
        }

        $genre_data = json_decode( $content, true );
        
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $genre_data ) ) {
            // Save genres to meta
            if ( ! empty( $genre_data['primary_genre'] ) ) {
                update_post_meta( $product_id, '_book_primary_genre', sanitize_text_field( $genre_data['primary_genre'] ) );
            }
            
            // Assign to category
            if ( ! empty( $genre_data['primary_genre'] ) ) {
                $term = get_term_by( 'name', $genre_data['primary_genre'], 'product_cat' );
                
                if ( ! $term ) {
                    $result = wp_insert_term( $genre_data['primary_genre'], 'product_cat' );
                    if ( ! is_wp_error( $result ) ) {
                        wp_set_object_terms( $product_id, array( $result['term_id'] ), 'product_cat', true );
                    }
                } else {
                    wp_set_object_terms( $product_id, array( $term->term_id ), 'product_cat', true );
                }
            }
        }
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', [ '\BookGenreGenerator\Plugin', 'init' ] );
```

## Available Hooks

### Actions

- **`product_data_generator_register_templates`**: Register custom templates (fired during initialization)
- **`product_data_generator_content_generated`**: Handle AI-generated content after generation
  - Parameters: `$content` (string), `$template_id` (string), `$product_id` (int)
- **`product_data_generator_template_registered`**: After a template is registered
- **`product_data_generator_log_prompt`**: Log prompt data

### Filters

- **`product_data_generator_prompt_data`**: Modify prompt data before sending to AI
- **`product_data_generator_messages`**: Modify AI messages array
- **`product_data_generator_basic_product_data`**: Modify basic product data
- **`product_data_generator_enable_logging`**: Enable/disable logging

### JavaScript Events

- **`pdg_apply_content`**: Fired when user clicks "Apply to Product" button
  - Custom event with `detail.templateId` and `detail.content`
  - Call `event.preventDefault()` to indicate the event was handled

## Using the Metabox

Once your template is registered, it automatically appears in the **AI Content Generator** metabox on the product edit screen (in the sidebar).

For each template, the metabox shows:
- Template name and description
- Last generation timestamp (if previously generated)
- **Generate** button to create new content
- Preview area with **Apply to Product** and **Cancel** buttons

### Default Template Behavior

Built-in templates automatically apply content to the appropriate fields:
- `product_description` → Product description field
- `product_short_description` → Short description field
- `product_seo` → Yoast/RankMath SEO fields (if installed)

### Custom Template Handling

For custom templates like `book_genre`, you have three options:

#### Option 1: Handle via PHP Hook (Recommended)

```php
add_action( 'product_data_generator_content_generated', function( $content, $template_id, $product_id ) {
    if ( $template_id !== 'book_genre' ) {
        return;
    }

    $genre_data = json_decode( $content, true );
    
    if ( json_last_error() === JSON_ERROR_NONE && is_array( $genre_data ) ) {
        // Save to meta
        update_post_meta( $product_id, '_book_primary_genre', $genre_data['primary_genre'] );
        
        // Create/assign category
        $term = get_term_by( 'name', $genre_data['primary_genre'], 'product_cat' );
        if ( ! $term ) {
            $result = wp_insert_term( $genre_data['primary_genre'], 'product_cat' );
            if ( ! is_wp_error( $result ) ) {
                wp_set_object_terms( $product_id, array( $result['term_id'] ), 'product_cat', true );
            }
        } else {
            wp_set_object_terms( $product_id, array( $term->term_id ), 'product_cat', true );
        }
    }
}, 10, 3 );
```

#### Option 2: Handle via JavaScript Event

```javascript
document.addEventListener('pdg_apply_content', function(event) {
    if (event.detail.templateId === 'book_genre') {
        try {
            const data = JSON.parse(event.detail.content);
            
            // Apply genre data to your custom fields
            jQuery('#custom_genre_field').val(data.primary_genre);
            
            // Mark event as handled
            event.preventDefault();
            
            alert('Genre applied: ' + data.primary_genre);
        } catch (e) {
            console.error('Error applying genre:', e);
        }
    }
});
```

#### Option 3: Automatic Meta Field Detection

If you have a meta field with a matching name, it will be automatically populated:
- Template ID: `book_genre`
- Auto-detects fields: `_book_genre` or `book_genre`

Simply add the field to your product form, and the content will be applied automatically.

## Best Practices

1. **Use Unique IDs**: Template IDs must be unique across all plugins
2. **Handle Dependencies**: Check if Product Data Generator is active
3. **Validate Responses**: Always validate and sanitize AI responses
4. **Use Filters**: Allow other developers to extend your templates
5. **Error Handling**: Implement proper error handling for AI failures
6. **Logging**: Use the built-in logging system for debugging
7. **Documentation**: Document your template's expected output format

## Testing Your Template

Once registered, your template will appear in the AI Content Generator metabox on any product edit screen. Simply:

1. Edit or create a product
2. Look for the **AI Content Generator** metabox in the sidebar
3. Find your template in the list
4. Click **Generate** to create content
5. Review the generated content in the preview area
6. Click **Apply to Product** to use it (or Cancel to discard)

### Programmatic Testing

You can also test your template programmatically:

```php
$template = \ProductDataGenerator\Template_Registry::get( 'book_genre' );
$product = wc_get_product( 123 );

$template->set_product( $product );
$messages = $template->get_messages();

// Use WordPress AI Client
$prompt_builder = \WordPress\AI_Client\AI_Client::prompt_with_wp_error();
$prompt_builder->using_system_instruction( $messages[0]['content'] );
$prompt_builder->with_text( $messages[1]['content'] );
$result = $prompt_builder->generate_text();

if ( ! is_wp_error( $result ) ) {
    echo $result;
}
```
