<?php
/**
 * AI Generator Class
 *
 * Main class for generating product data using AI
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator;

use WordPress\AI_Client\AI_Client;

defined( 'ABSPATH' ) || exit;

class AI_Generator {

    /**
     * Template config
     *
     * @var Template_Config
     */
    private $config;

    /**
     * Template instance
     *
     * @var Template
     */
    private $template;

    /**
     * Product instance
     *
     * @var \WC_Product
     */
    private $product;

    /**
     * Setup error
     *
     * @var \WP_Error|null
     */
    private $setup_error = null;

    /**
     * Constructor
     *
     * @param Template_Config $config Configuration instance
     */
    public function __construct( Template_Config $config ) {
        $this->config = $config;
        $this->setup();
    }

    /**
     * Setup the generator
     */
    private function setup() {
        // Validate configuration
        $validation = $this->config->validate();
        if ( is_wp_error( $validation ) ) {
            $this->setup_error = $validation;
            return;
        }

        // Get template
        $template_id = $this->config->get_template_id();
        $this->template = Template_Registry::get( $template_id );

        if ( ! $this->template ) {
            $this->setup_error = new \WP_Error(
                'template_not_found',
                sprintf( __( 'Template "%s" not found.', 'product-data-generator' ), $template_id )
            );
            return;
        }

        // Get product
        $product_id = $this->config->get_product_id();
        if ( $product_id ) {
            $this->product = wc_get_product( $product_id );
            
            if ( ! $this->product ) {
                $this->setup_error = new \WP_Error(
                    'product_not_found',
                    sprintf( __( 'Product with ID %d not found.', 'product-data-generator' ), $product_id )
                );
                return;
            }

            // Set product on template
            $this->template->set_product( $this->product );
        }

        // Set context on template
        $context = $this->config->get_context();
        if ( ! empty( $context ) ) {
            $this->template->set_context( $context );
        }
    }

    /**
     * Generate content using AI
     *
     * @return array|\WP_Error {
     *     @type string $content Generated content
     *     @type array $metadata Additional metadata
     * }
     */
    public function generate() {
        // Check for setup errors
        if ( $this->setup_error ) {
            return $this->setup_error;
        }

        // Ensure AI_Client is initialized
        if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
            return new \WP_Error(
                'ai_client_missing',
                __( 'WordPress AI Client is not available.', 'product-data-generator' )
            );
        }

        /**
         * Fires before content generation
         *
         * @param AI_Generator $generator Generator instance
         */
        do_action( 'product_data_generator_before_generate', $this );

        // Get messages from template
        $messages = $this->template->get_messages();

        // Get AI settings
        $ai_settings = $this->config->get_ai_settings();

        /**
         * Filter AI request parameters
         *
         * @param array $params AI request parameters
         * @param AI_Generator $generator Generator instance
         */
        $params = apply_filters( 'product_data_generator_ai_params', [
            'messages' => $messages,
            'temperature' => $ai_settings['temperature'] ?? 0.7,
            'max_tokens' => $ai_settings['max_tokens'] ?? 1000,
        ], $this );

        try {
            // Build the prompt with system instruction and user message
            $prompt_builder = AI_Client::prompt_with_wp_error();
            
            // Set system instruction if available
            if ( ! empty( $messages[0]['content'] ) && $messages[0]['role'] === 'system' ) {
                $prompt_builder->using_system_instruction( $messages[0]['content'] );
            }
            
            // Add user message
            if ( ! empty( $messages[1]['content'] ) && $messages[1]['role'] === 'user' ) {
                $prompt_builder->with_text( $messages[1]['content'] );
            }
            
            // Set temperature and max tokens
            if ( isset( $params['temperature'] ) ) {
                $prompt_builder->using_temperature( (float) $params['temperature'] );
            }
            if ( isset( $params['max_tokens'] ) ) {
                $prompt_builder->using_max_tokens( (int) $params['max_tokens'] );
            }
            
            // Generate text
            $content = $prompt_builder->generate_text();
            
            if ( is_wp_error( $content ) ) {
                // Log the error
                $this->log_response( null, $content, $messages );
                return $content;
            }

            // Format content based on output format
            $formatted_content = $this->format_content( $content );

            $result = [
                'content' => $formatted_content,
                'raw_content' => $content,
                'metadata' => [
                    'template_id' => $this->config->get_template_id(),
                    'product_id' => $this->config->get_product_id(),
                    'generated_at' => current_time( 'mysql' ),
                ],
            ];

            // Log the successful response
            $this->log_response( $result, null, $messages );

            /**
             * Filter generated content
             *
             * @param array $result Generation result
             * @param AI_Generator $generator Generator instance
             */
            $result = apply_filters( 'product_data_generator_generated_content', $result, $this );

            // Auto-save if configured
            if ( $this->config->should_auto_save() ) {
                $this->save( $result['content'] );
            }

            /**
             * Fires after content generation
             *
             * @param array $result Generation result
             * @param AI_Generator $generator Generator instance
             */
            do_action( 'product_data_generator_after_generate', $result, $this );

            return $result;

        } catch ( \Exception $e ) {
            return new \WP_Error(
                'generation_error',
                $e->getMessage()
            );
        }
    }



    /**
     * Format content based on output format
     *
     * @param string $content Raw content
     * @return string
     */
    private function format_content( $content ) {
        $format = $this->config->get_output_format();

        switch ( $format ) {
            case 'html':
                // Convert markdown to HTML if needed
                $content = wpautop( $content );
                break;

            case 'text':
                // Strip any HTML tags
                $content = wp_strip_all_tags( $content );
                break;

            case 'markdown':
                // Keep as-is
                break;

            default:
                /**
                 * Filter content formatting
                 *
                 * @param string $content Content to format
                 * @param string $format Format type
                 * @param AI_Generator $generator Generator instance
                 */
                $content = apply_filters( "product_data_generator_format_{$format}", $content, $this );
                break;
        }

        return $content;
    }

    /**
     * Save generated content to product
     *
     * @param string $content Content to save
     * @return bool|\WP_Error
     */
    public function save( $content ) {
        if ( ! $this->product ) {
            return new \WP_Error(
                'no_product',
                __( 'No product to save to.', 'product-data-generator' )
            );
        }

        $field_mapping = $this->config->get_field_mapping();

        if ( empty( $field_mapping ) ) {
            // Default mapping based on template
            $template_id = $this->config->get_template_id();
            
            switch ( $template_id ) {
                case 'product_description':
                    $field_mapping = [ 'description' => 'post_content' ];
                    break;
                
                case 'product_short_description':
                    $field_mapping = [ 'short_description' => 'post_excerpt' ];
                    break;
            }
        }

        /**
         * Fires before saving generated content
         *
         * @param string $content Content to save
         * @param \WC_Product $product Product instance
         * @param AI_Generator $generator Generator instance
         */
        do_action( 'product_data_generator_before_save', $content, $this->product, $this );

        $saved = false;

        foreach ( $field_mapping as $key => $field ) {
            switch ( $field ) {
                case 'post_content':
                    $this->product->set_description( $content );
                    $saved = true;
                    break;

                case 'post_excerpt':
                    $this->product->set_short_description( $content );
                    $saved = true;
                    break;

                default:
                    // Save as meta
                    $this->product->update_meta_data( $key, $content );
                    $saved = true;
                    break;
            }
        }

        if ( $saved ) {
            $this->product->save();

            /**
             * Fires after saving generated content
             *
             * @param string $content Saved content
             * @param \WC_Product $product Product instance
             * @param AI_Generator $generator Generator instance
             */
            do_action( 'product_data_generator_after_save', $content, $this->product, $this );
        }

        return $saved;
    }

    /**
     * Get template instance
     *
     * @return Template
     */
    public function get_template() {
        return $this->template;
    }

    /**
     * Get product instance
     *
     * @return \WC_Product
     */
    public function get_product() {
        return $this->product;
    }

    /**
     * Get configuration
     *
     * @return Template_Config
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Factory method to create a generator
     *
     * @param string $template_id Template ID
     * @param int $product_id Product ID
     * @param array $config_overrides Configuration overrides
     * @return AI_Generator
     */
    public static function create( $template_id, $product_id, array $config_overrides = [] ) {
        $config = Template_Config::for_template( $template_id, $product_id, $config_overrides );
        return new self( $config );
    }

    /**
     * Factory method to create a generator from preset
     *
     * @param string $preset_name Preset name
     * @param int $product_id Product ID
     * @return AI_Generator
     */
    public static function from_preset( $preset_name, $product_id ) {
        $config = Template_Config::from_preset( $preset_name, $product_id );
        return new self( $config );
    }

    /**
     * Log AI response
     *
     * @param array|null $result Generation result (null on error)
     * @param \WP_Error|null $error Error object (null on success)
     * @param array $messages Original messages sent
     */
    private function log_response( $result, $error, $messages ) {
        /**
         * Filter whether to enable response logging
         *
         * @param bool $enabled Whether logging is enabled (default: WP_DEBUG)
         * @param AI_Generator $generator Generator instance
         */
        $logging_enabled = apply_filters( 'product_data_generator_enable_logging', defined( 'WP_DEBUG' ) && WP_DEBUG, $this );

        if ( ! $logging_enabled ) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time( 'mysql' ),
            'template_id' => $this->config->get_template_id(),
            'product_id' => $this->config->get_product_id(),
            'product_name' => $this->product ? $this->product->get_name() : null,
            'success' => ! is_null( $result ),
            'result' => $result,
            'error' => $error,
        ];

        /**
         * Fires when a response is logged
         *
         * @param array $log_entry Log entry data
         * @param AI_Generator $generator Generator instance
         */
        do_action( 'product_data_generator_log_response', $log_entry, $this );

        // Write to debug log if no custom logging handler is attached
        if ( ! has_action( 'product_data_generator_log_response' ) ) {
            if ( $error ) {
                error_log( sprintf(
                    "[Product Data Generator] RESPONSE ERROR\nTemplate: %s\nProduct: #%d - %s\nError Code: %s\nError Message: %s",
                    $log_entry['template_id'],
                    $log_entry['product_id'],
                    $log_entry['product_name'],
                    $error->get_error_code(),
                    $error->get_error_message()
                ) );
            } else {
                error_log( sprintf(
                    "[Product Data Generator] RESPONSE RECEIVED\nTemplate: %s\nProduct: #%d - %s\nContent Length: %d chars\n---\nRESPONSE:\n%s\n---",
                    $log_entry['template_id'],
                    $log_entry['product_id'],
                    $log_entry['product_name'],
                    strlen( $result['raw_content'] ),
                    $result['raw_content']
                ) );
            }
        }
    }
}
