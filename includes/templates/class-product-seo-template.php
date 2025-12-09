<?php
/**
 * Product SEO Content Template
 *
 * Template for generating SEO-optimized product content
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator\Templates;

use ProductDataGenerator\Template;

defined( 'ABSPATH' ) || exit;

class Product_SEO_Template extends Template {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'product_seo',
            __( 'SEO-Optimized Content', 'product-data-generator' ),
            __( 'Generate SEO-optimized product content with keywords and structured data', 'product-data-generator' )
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
            $data['description'] = $this->product->get_description();
            $data['short_description'] = $this->product->get_short_description();
            
            // Get existing SEO data
            $product_id = $this->product->get_id();
            $data['seo'] = [
                'meta_title' => get_post_meta( $product_id, '_yoast_wpseo_title', true ),
                'meta_description' => get_post_meta( $product_id, '_yoast_wpseo_metadesc', true ),
                'focus_keyword' => get_post_meta( $product_id, '_yoast_wpseo_focuskw', true ),
            ];

            // Get competitor data if available
            $competitors = get_post_meta( $product_id, '_competitor_products', true );
            if ( ! empty( $competitors ) ) {
                $data['competitors'] = $competitors;
            }

            // Get search volume data if available
            $search_terms = get_post_meta( $product_id, '_target_keywords', true );
            if ( ! empty( $search_terms ) ) {
                $data['target_keywords'] = $search_terms;
            }

            // Get review data for social proof
            $reviews = $this->product->get_reviews_allowed() ? $this->get_review_summary() : null;
            if ( $reviews ) {
                $data['reviews'] = $reviews;
            }
        }

        /**
         * Filter product data for SEO template
         *
         * @param array $data Product data
         * @param \WC_Product $product Product object
         * @param Product_SEO_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_seo_product_data', $data, $this->product, $this );
    }

    /**
     * Build the system prompt
     *
     * @return string
     */
    protected function build_system_prompt() {
        $prompt = "You are an expert SEO copywriter and e-commerce specialist. ";
        $prompt .= "Your task is to create product content that is highly optimized for search engines while remaining engaging for human readers. ";
        $prompt .= "Focus on:\n";
        $prompt .= "1. Natural keyword integration (avoid keyword stuffing)\n";
        $prompt .= "2. Semantic SEO and related terms\n";
        $prompt .= "3. Search intent matching\n";
        $prompt .= "4. Structured, scannable content with headings\n";
        $prompt .= "5. Clear value propositions and benefits\n";
        $prompt .= "6. Trust signals and social proof\n";
        $prompt .= "7. Call-to-action optimization\n\n";
        $prompt .= "Write content that satisfies both search engine algorithms and user needs.";

        /**
         * Filter the system prompt for SEO generation
         *
         * @param string $prompt System prompt
         * @param Product_SEO_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_seo_system_prompt', $prompt, $this );
    }

    /**
     * Build the user prompt
     *
     * @return string
     */
    protected function build_user_prompt() {
        $data = $this->aggregate_product_data();
        
        $prompt = "Create SEO-optimized product content for:\n\n";
        
        // Product basics
        if ( ! empty( $data['name'] ) ) {
            $prompt .= "Product Name: {$data['name']}\n";
        }

        if ( ! empty( $data['categories'] ) ) {
            $prompt .= "Categories: " . implode( ', ', $data['categories'] ) . "\n";
        }

        // SEO context
        if ( ! empty( $this->context['focus_keyword'] ) ) {
            $prompt .= "Primary Keyword: {$this->context['focus_keyword']}\n";
        } elseif ( ! empty( $data['seo']['focus_keyword'] ) ) {
            $prompt .= "Primary Keyword: {$data['seo']['focus_keyword']}\n";
        }

        if ( ! empty( $data['target_keywords'] ) ) {
            if ( is_array( $data['target_keywords'] ) ) {
                $prompt .= "Target Keywords: " . implode( ', ', $data['target_keywords'] ) . "\n";
            } else {
                $prompt .= "Target Keywords: {$data['target_keywords']}\n";
            }
        }

        // Search intent
        if ( ! empty( $this->context['search_intent'] ) ) {
            $prompt .= "Search Intent: {$this->context['search_intent']}\n";
        }

        // Product attributes
        if ( ! empty( $data['attributes'] ) ) {
            $prompt .= "\nProduct Attributes:\n";
            foreach ( $data['attributes'] as $name => $values ) {
                if ( is_array( $values ) && ! empty( $values ) ) {
                    $prompt .= "- " . ucfirst( str_replace( '_', ' ', $name ) ) . ": " . implode( ', ', $values ) . "\n";
                }
            }
        }

        // Existing content
        if ( ! empty( $data['description'] ) ) {
            $prompt .= "\nCurrent Description (for reference):\n{$data['description']}\n";
        }

        // Social proof
        if ( ! empty( $data['reviews'] ) ) {
            $prompt .= "\nReview Summary:\n";
            $prompt .= "- Average Rating: {$data['reviews']['average']}/5\n";
            $prompt .= "- Total Reviews: {$data['reviews']['count']}\n";
            if ( ! empty( $data['reviews']['top_themes'] ) ) {
                $prompt .= "- Top Review Themes: " . implode( ', ', $data['reviews']['top_themes'] ) . "\n";
            }
        }

        // Competitor context
        if ( ! empty( $data['competitors'] ) ) {
            $prompt .= "\nCompetitor Context: {$data['competitors']}\n";
        }

        // Additional context
        if ( ! empty( $this->context ) ) {
            $filtered_context = array_diff_key( $this->context, array_flip( [ 'focus_keyword', 'search_intent' ] ) );
            if ( ! empty( $filtered_context ) ) {
                $prompt .= "\nAdditional Context:\n";
                foreach ( $filtered_context as $key => $value ) {
                    if ( is_string( $value ) || is_numeric( $value ) ) {
                        $prompt .= "- " . ucfirst( str_replace( '_', ' ', $key ) ) . ": {$value}\n";
                    }
                }
            }
        }

        $prompt .= "\nContent Requirements:\n";
        $prompt .= "1. Length: 300-500 words (optimal for SEO)\n";
        $prompt .= "2. Include the focus keyword naturally 2-3 times\n";
        $prompt .= "3. Use semantic variations and related terms\n";
        $prompt .= "4. Structure with H2/H3 headings (use HTML tags)\n";
        $prompt .= "5. Include bullet points for key features\n";
        $prompt .= "6. Add a compelling call-to-action\n";
        $prompt .= "7. Incorporate trust signals (warranty, quality, reviews)\n";
        $prompt .= "8. Write for readability (short paragraphs, clear language)\n";
        $prompt .= "9. Match the search intent\n";
        $prompt .= "10. Front-load important information\n";

        if ( ! empty( $this->context['include_schema'] ) ) {
            $prompt .= "\nAlso generate JSON-LD schema markup for the product.";
        }

        /**
         * Filter the user prompt for SEO generation
         *
         * @param string $prompt User prompt
         * @param array $data Aggregated product data
         * @param Product_SEO_Template $template Template instance
         */
        return apply_filters( 'product_data_generator_seo_user_prompt', $prompt, $data, $this );
    }

    /**
     * Get review summary
     *
     * @return array|null
     */
    private function get_review_summary() {
        if ( ! $this->product ) {
            return null;
        }

        $average = $this->product->get_average_rating();
        $count = $this->product->get_review_count();

        if ( $count === 0 ) {
            return null;
        }

        $summary = [
            'average' => $average,
            'count' => $count,
        ];

        // Get top review themes (requires custom implementation or plugin)
        $top_themes = apply_filters( 'product_data_generator_review_themes', [], $this->product->get_id() );
        if ( ! empty( $top_themes ) ) {
            $summary['top_themes'] = $top_themes;
        }

        return $summary;
    }
}
