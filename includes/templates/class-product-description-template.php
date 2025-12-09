<?php
/**
 * Product Description Template
 *
 * Default template for generating product descriptions
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator\Templates;

use ProductDataGenerator\Template;

defined( 'ABSPATH' ) || exit;

class Product_Description_Template extends Template {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'product_description',
            __( 'Product Description', 'product-data-generator' ),
            __( 'Generate a compelling product description based on product data', 'product-data-generator' )
        );
    }

    /**
     * Aggregate product data
     *
     * @return array
     */
    protected function aggregate_product_data() {
        $data = $this->get_basic_product_data();

        // Add additional data specific to description generation
        if ( $this->product ) {
            $data['short_description'] = $this->product->get_short_description();
            $data['description'] = $this->product->get_description();
            
            // Get custom fields if any
            $meta_keys = apply_filters( 'product_data_generator_description_meta_keys', [] );
            foreach ( $meta_keys as $key ) {
                $data['meta'][ $key ] = get_post_meta( $this->product->get_id(), $key, true );
            }

            // Get image data
            $image_id = $this->product->get_image_id();
            if ( $image_id ) {
                $data['image'] = [
                    'url' => wp_get_attachment_url( $image_id ),
                    'alt' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
                    'caption' => wp_get_attachment_caption( $image_id ),
                ];
            }

            // Get gallery images
            $gallery_ids = $this->product->get_gallery_image_ids();
            if ( ! empty( $gallery_ids ) ) {
                $data['gallery'] = [];
                foreach ( $gallery_ids as $gallery_id ) {
                    $data['gallery'][] = [
                        'url' => wp_get_attachment_url( $gallery_id ),
                        'alt' => get_post_meta( $gallery_id, '_wp_attachment_image_alt', true ),
                        'caption' => wp_get_attachment_caption( $gallery_id ),
                    ];
                }
            }
        }

        /**
         * Filter product data for description template
         *
         * @param array $data Product data
         * @param \WC_Product $product Product object
         * @param Product_Description_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_description_product_data', $data, $this->product, $this );
    }

    /**
     * Build the system prompt
     *
     * @return string
     */
    protected function build_system_prompt() {
        $prompt = "You are an expert e-commerce copywriter specializing in product descriptions. ";
        $prompt .= "Your task is to create compelling, SEO-friendly product descriptions that are informative, engaging, and persuasive. ";
        $prompt .= "Focus on benefits over features, use clear and concise language, and maintain a professional yet approachable tone. ";
        $prompt .= "Consider the target audience and write descriptions that resonate with them. ";
        $prompt .= "\n\nFORMATTING RULES:\n";
        $prompt .= "- Use HTML tags for formatting: <strong> for emphasis, <em> for italics, <p> for paragraphs\n";
        $prompt .= "- Do NOT use markdown asterisks (*) or underscores (_) for formatting\n";
        $prompt .= "- Use <br> tags for line breaks within paragraphs if needed\n";
        $prompt .= "- Use <ul> and <li> tags for lists, not dashes or asterisks\n";
        $prompt .= "- Do NOT include the product title or author name in the description\n";
        $prompt .= "- Do NOT mention pricing or cost information\n";
        $prompt .= "- Start directly with the description content\n";
        $prompt .= "- Return only the description body, no headers or titles";

        /**
         * Filter the system prompt for description generation
         *
         * @param string $prompt System prompt
         * @param Product_Description_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_description_system_prompt', $prompt, $this );
    }

    /**
     * Build the user prompt
     *
     * @return string
     */
    protected function build_user_prompt() {
        $data = $this->aggregate_product_data();
        
        $prompt = "Generate a product description for the following product:\n\n";
        
        $prompt .= "=== PRODUCT DATA ===\n\n";
        
        // Add product name
        if ( ! empty( $data['name'] ) ) {
            $prompt .= "Product Name: {$data['name']}\n";
        }

        // Add SKU
        if ( ! empty( $data['sku'] ) ) {
            $prompt .= "SKU: {$data['sku']}\n";
        }

        // Add categories
        if ( ! empty( $data['categories'] ) ) {
            $prompt .= "Categories: " . implode( ', ', $data['categories'] ) . "\n";
        }

        // Add tags
        if ( ! empty( $data['tags'] ) ) {
            $prompt .= "Tags: " . implode( ', ', $data['tags'] ) . "\n";
        }

        // Add attributes
        if ( ! empty( $data['attributes'] ) ) {
            $prompt .= "\nAttributes:\n";
            foreach ( $data['attributes'] as $name => $values ) {
                if ( is_array( $values ) ) {
                    $prompt .= "- " . ucfirst( str_replace( '_', ' ', $name ) ) . ": " . implode( ', ', $values ) . "\n";
                }
            }
        }

        // Add existing description if available
        if ( ! empty( $data['description'] ) ) {
            $prompt .= "\nCurrent Description: {$data['description']}\n";
            $prompt .= "(Try to maintain the essense of this description if it exists and is at least 3 sentences. Improve, enhance and append it in a thoughtful way.)\n";
        }

        // Add custom meta data
        if ( ! empty( $data['meta'] ) ) {
            $prompt .= "\nAdditional Information:\n";
            foreach ( $data['meta'] as $key => $value ) {
                if ( ! empty( $value ) ) {
                    $prompt .= "- " . ucfirst( str_replace( '_', ' ', $key ) ) . ": {$value}\n";
                }
            }
        }

        // Add context data
        if ( ! empty( $this->context ) ) {
            $prompt .= "\nAdditional Context:\n";
            foreach ( $this->context as $key => $value ) {
                if ( is_string( $value ) || is_numeric( $value ) ) {
                    $prompt .= "- " . ucfirst( str_replace( '_', ' ', $key ) ) . ": {$value}\n";
                }
            }
        }

        // Add any additional data that doesn't fit the standard fields
        // This allows developers to add custom data via the filter
        $standard_keys = ['name', 'sku', 'categories', 'tags', 'attributes', 'description', 'short_description', 'meta', 'image', 'gallery'];
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

        $prompt .= "\n=== INSTRUCTIONS ===\n\n";
        $prompt .= "Please generate a compelling product description that:\n";
        $prompt .= "1. Highlights key features and benefits\n";
        $prompt .= "2. Uses persuasive language to encourage purchase\n";
        $prompt .= "3. Is optimized for search engines\n";
        $prompt .= "4. Maintains a professional yet engaging tone\n";
        $prompt .= "5. Is between 150-300 words\n";

        /**
         * Filter the user prompt for description generation
         *
         * @param string $prompt User prompt
         * @param array $data Aggregated product data
         * @param Product_Description_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_description_user_prompt', $prompt, $data, $this );
    }
}
