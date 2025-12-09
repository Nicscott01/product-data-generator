<?php
/**
 * Base Template Class
 *
 * Abstract class for creating AI prompt templates
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator;

defined( 'ABSPATH' ) || exit;

abstract class Template {

    /**
     * Template ID
     *
     * @var string
     */
    protected $id;

    /**
     * Template name
     *
     * @var string
     */
    protected $name;

    /**
     * Template description
     *
     * @var string
     */
    protected $description;

    /**
     * Product object
     *
     * @var \WC_Product
     */
    protected $product;

    /**
     * Additional context data
     *
     * @var array
     */
    protected $context = [];

    /**
     * Constructor
     *
     * @param string $id Template ID
     * @param string $name Template name
     * @param string $description Template description
     */
    public function __construct( $id, $name, $description = '' ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
    }

    /**
     * Get template ID
     *
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get template description
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Set product
     *
     * @param \WC_Product $product Product object
     * @return self
     */
    public function set_product( $product ) {
        $this->product = $product;
        return $this;
    }

    /**
     * Set context data
     *
     * @param array $context Context data
     * @return self
     */
    public function set_context( array $context ) {
        $this->context = array_merge( $this->context, $context );
        return $this;
    }

    /**
     * Get context data
     *
     * @return array
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Aggregate product data
     *
     * This method should be overridden by child classes to define
     * what product data should be collected
     *
     * @return array
     */
    abstract protected function aggregate_product_data();

    /**
     * Build the system prompt
     *
     * This method should be overridden by child classes to define
     * the system instructions for the AI
     *
     * @return string
     */
    abstract protected function build_system_prompt();

    /**
     * Build the user prompt
     *
     * This method should be overridden by child classes to define
     * the user request for the AI
     *
     * @return string
     */
    abstract protected function build_user_prompt();

    /**
     * Get aggregated data for AI prompt
     *
     * @return array {
     *     @type string $system_prompt System instructions
     *     @type string $user_prompt User request
     *     @type array $product_data Aggregated product data
     *     @type array $context Additional context
     * }
     */
    public function get_prompt_data() {
        $product_data = $this->aggregate_product_data();
        $system_prompt = $this->build_system_prompt();
        $user_prompt = $this->build_user_prompt();

        $data = [
            'system_prompt' => $system_prompt,
            'user_prompt' => $user_prompt,
            'product_data' => $product_data,
            'context' => $this->context,
        ];

        /**
         * Filter the aggregated prompt data
         *
         * @param array $data Prompt data
         * @param Template $template Template instance
         * @param \WC_Product $product Product object
         */
        return apply_filters( 'product_data_generator_prompt_data', $data, $this, $this->product );
    }

    /**
     * Get formatted messages for AI_Client
     *
     * @return array
     */
    public function get_messages() {
        $data = $this->get_prompt_data();
        
        $messages = [
            [
                'role' => 'system',
                'content' => $data['system_prompt'],
            ],
            [
                'role' => 'user',
                'content' => $data['user_prompt'],
            ],
        ];

        // Log the prompts being sent
        $this->log_prompt( $messages, $data );

        /**
         * Filter the AI messages
         *
         * @param array $messages Messages array
         * @param array $data Prompt data
         * @param Template $template Template instance
         */
        return apply_filters( 'product_data_generator_messages', $messages, $data, $this );
    }

    /**
     * Get basic product data
     *
     * Helper method to get common product data
     *
     * @return array
     */
    protected function get_basic_product_data() {
        if ( ! $this->product ) {
            return [];
        }

        $data = [
            'id' => $this->product->get_id(),
            'name' => $this->product->get_name(),
            'sku' => $this->product->get_sku(),
            'type' => $this->product->get_type(),
            'price' => $this->product->get_price(),
            'regular_price' => $this->product->get_regular_price(),
            'sale_price' => $this->product->get_sale_price(),
            'categories' => wp_list_pluck( wc_get_product_terms( $this->product->get_id(), 'product_cat' ), 'name' ),
            'tags' => wp_list_pluck( wc_get_product_terms( $this->product->get_id(), 'product_tag' ), 'name' ),
            'attributes' => $this->get_product_attributes(),
        ];

        /**
         * Filter basic product data
         *
         * @param array $data Product data
         * @param \WC_Product $product Product object
         * @param Template $template Template instance
         */
        return apply_filters( 'product_data_generator_basic_product_data', $data, $this->product, $this );
    }

    /**
     * Get product attributes
     *
     * @return array
     */
    protected function get_product_attributes() {
        if ( ! $this->product ) {
            return [];
        }

        $attributes = [];
        foreach ( $this->product->get_attributes() as $attribute ) {
            if ( $attribute->is_taxonomy() ) {
                $terms = wp_get_post_terms( $this->product->get_id(), $attribute->get_name() );
                $attributes[ $attribute->get_name() ] = wp_list_pluck( $terms, 'name' );
            } else {
                $attributes[ $attribute->get_name() ] = $attribute->get_options();
            }
        }

        return $attributes;
    }

    /**
     * Log prompt being sent to AI
     *
     * @param array $messages Messages array
     * @param array $data Prompt data
     */
    protected function log_prompt( $messages, $data ) {
        /**
         * Filter whether to enable prompt logging
         *
         * @param bool $enabled Whether logging is enabled (default: WP_DEBUG)
         * @param Template $template Template instance
         */
        $logging_enabled = apply_filters( 'product_data_generator_enable_logging', defined( 'WP_DEBUG' ) && WP_DEBUG, $this );

        if ( ! $logging_enabled ) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time( 'mysql' ),
            'template_id' => $this->id,
            'product_id' => $this->product ? $this->product->get_id() : null,
            'product_name' => $this->product ? $this->product->get_name() : null,
            'system_prompt' => $messages[0]['content'] ?? '',
            'user_prompt' => $messages[1]['content'] ?? '',
            'context' => $this->context,
        ];

        /**
         * Fires when a prompt is logged
         *
         * @param array $log_entry Log entry data
         * @param Template $template Template instance
         */
        do_action( 'product_data_generator_log_prompt', $log_entry, $this );

        // Write to debug log if no custom logging handler is attached
        if ( ! has_action( 'product_data_generator_log_prompt' ) ) {
            error_log( sprintf(
                "[Product Data Generator] PROMPT SENT\nTemplate: %s\nProduct: #%d - %s\nSystem Prompt Length: %d chars\nUser Prompt Length: %d chars\n---\nSYSTEM:\n%s\n---\nUSER:\n%s\n---",
                $log_entry['template_id'],
                $log_entry['product_id'],
                $log_entry['product_name'],
                strlen( $log_entry['system_prompt'] ),
                strlen( $log_entry['user_prompt'] ),
                $log_entry['system_prompt'],
                $log_entry['user_prompt']
            ) );
        }
    }
}
