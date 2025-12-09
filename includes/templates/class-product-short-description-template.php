<?php
/**
 * Product Short Description Template
 *
 * Template for generating product short descriptions
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator\Templates;

use ProductDataGenerator\Template;

defined( 'ABSPATH' ) || exit;

class Product_Short_Description_Template extends Template {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'product_short_description',
            __( 'Product Short Description', 'product-data-generator' ),
            __( 'Generate a concise product short description', 'product-data-generator' )
        );
    }

    /**
     * Aggregate product data
     *
     * @return array
     */
    protected function aggregate_product_data() {
        $data = $this->get_basic_product_data();

        if ( $this->product ) {
            $data['short_description'] = $this->product->get_short_description();
            $data['description'] = $this->product->get_description();
            
            // Get key selling points from custom fields
            $selling_points = get_post_meta( $this->product->get_id(), '_selling_points', true );
            if ( ! empty( $selling_points ) ) {
                $data['selling_points'] = $selling_points;
            }
        }

        /**
         * Filter product data for short description template
         *
         * @param array $data Product data
         * @param \WC_Product $product Product object
         * @param Product_Short_Description_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_short_description_product_data', $data, $this->product, $this );
    }

    /**
     * Build the system prompt
     *
     * @return string
     */
    protected function build_system_prompt() {
        $prompt = "You are an expert e-commerce copywriter specializing in creating concise, impactful product summaries. ";
        $prompt .= "Your task is to create short descriptions that quickly capture attention and communicate the most important product benefits. ";
        $prompt .= "Use clear, punchy language and focus on what makes the product unique or valuable to customers. ";
        $prompt .= "\n\nFORMATTING RULES:\n";
        $prompt .= "- Use HTML tags for formatting: <strong> for emphasis, <em> for italics\n";
        $prompt .= "- Do NOT use markdown asterisks (*) or underscores (_) for formatting\n";
        $prompt .= "- If using a list, use <ul> and <li> tags, not dashes or asterisks\n";
        $prompt .= "- Do NOT include the product title or author name in the description\n";
        $prompt .= "- Do NOT mention pricing or cost information\n";
        $prompt .= "- Start directly with the description content\n";
        $prompt .= "- Return only the description text, no headers or titles\n";
        $prompt .= "- Keep it concise and avoid unnecessary line breaks";

        /**
         * Filter the system prompt for short description generation
         *
         * @param string $prompt System prompt
         * @param Product_Short_Description_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_short_description_system_prompt', $prompt, $this );
    }

    /**
     * Build the user prompt
     *
     * @return string
     */
    protected function build_user_prompt() {
        $data = $this->aggregate_product_data();
        
        $prompt = "Generate a short product description for the following product:\n\n";
        
        $prompt .= "=== PRODUCT DATA ===\n\n";
        
        if ( ! empty( $data['name'] ) ) {
            $prompt .= "Product: {$data['name']}\n";
        }

        if ( ! empty( $data['categories'] ) ) {
            $prompt .= "Category: " . implode( ', ', $data['categories'] ) . "\n";
        }

        if ( ! empty( $data['tags'] ) ) {
            $prompt .= "Tags: " . implode( ', ', $data['tags'] ) . "\n";
        }

        if ( ! empty( $data['description'] ) ) {
            $prompt .= "\nFull Description:\n{$data['description']}\n";
        }

        if ( ! empty( $data['selling_points'] ) ) {
            $prompt .= "\nKey Selling Points:\n";
            if ( is_array( $data['selling_points'] ) ) {
                foreach ( $data['selling_points'] as $point ) {
                    $prompt .= "- {$point}\n";
                }
            } else {
                $prompt .= $data['selling_points'] . "\n";
            }
        }

        // Add context data
        if ( ! empty( $this->context ) ) {
            $prompt .= "\nAdditional Context:\n";
            foreach ( $this->context as $key => $value ) {
                if ( $key === 'word_limit' ) {
                    continue; // Skip word_limit as it's used in instructions
                }
                if ( is_string( $value ) || is_numeric( $value ) ) {
                    $prompt .= "- " . ucfirst( str_replace( '_', ' ', $key ) ) . ": {$value}\n";
                }
            }
        }

        // Add any additional data that doesn't fit the standard fields
        // This allows developers to add custom data via the filter
        $standard_keys = ['name', 'sku', 'categories', 'tags', 'attributes', 'description', 'short_description', 'selling_points'];
        $custom_data = array_diff_key( $data, array_flip( $standard_keys ) );
        if ( ! empty( $custom_data ) ) {
            $prompt .= "\nCustom Data:\n";
            foreach ( $custom_data as $key => $value ) {
                if ( is_string( $value ) || is_numeric( $value ) ) {
                    $prompt .= "- " . ucfirst( str_replace( '_', ' ', $key ) ) . ": {$value}\n";
                } elseif ( is_array( $value ) && ! empty( $value ) ) {
                    $prompt .= "- " . ucfirst( str_replace( '_', ' ', $key ) ) . ":\n";
                    foreach ( $value as $item_key => $item_value ) {
                        if ( is_string( $item_value ) || is_numeric( $item_value ) ) {
                            $prompt .= "  - {$item_value}\n";
                        }
                    }
                }
            }
        }

        if ( ! empty( $this->context['word_limit'] ) ) {
            $word_limit = $this->context['word_limit'];
        } else {
            $word_limit = 50;
        }

        $prompt .= "\n=== INSTRUCTIONS ===\n\n";
        $prompt .= "Create a compelling short description that:\n";
        $prompt .= "1. Is no more than {$word_limit} words\n";
        $prompt .= "2. Highlights the most important benefit or feature\n";
        $prompt .= "3. Creates urgency or desire\n";
        $prompt .= "4. Uses active, engaging language\n";

        /**
         * Filter the user prompt for short description generation
         *
         * @param string $prompt User prompt
         * @param array $data Aggregated product data
         * @param Product_Short_Description_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_short_description_user_prompt', $prompt, $data, $this );
    }
}
