<?php
/**
 * Template Config Class
 *
 * Manages configuration and settings for templates
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator;

defined( 'ABSPATH' ) || exit;

class Template_Config {

    /**
     * Configuration data
     *
     * @var array
     */
    private $config = [];

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct( array $config = [] ) {
        $this->config = $this->parse_config( $config );
    }

    /**
     * Parse and validate configuration
     *
     * @param array $config Raw configuration
     * @return array Parsed configuration
     */
    private function parse_config( array $config ) {
        $defaults = [
            'template_id' => 'product_description',
            'product_id' => null,
            'context' => [],
            'ai_settings' => [
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ],
            'output_format' => 'html',
            'auto_save' => false,
            'field_mapping' => [],
        ];

        return wp_parse_args( $config, $defaults );
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $default Default value
     * @return mixed
     */
    public function get( $key, $default = null ) {
        $keys = explode( '.', $key );
        $value = $this->config;

        foreach ( $keys as $k ) {
            if ( ! isset( $value[ $k ] ) ) {
                return $default;
            }
            $value = $value[ $k ];
        }

        return $value;
    }

    /**
     * Set configuration value
     *
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $value Value to set
     * @return self
     */
    public function set( $key, $value ) {
        $keys = explode( '.', $key );
        $config = &$this->config;

        foreach ( $keys as $i => $k ) {
            if ( $i === count( $keys ) - 1 ) {
                $config[ $k ] = $value;
            } else {
                if ( ! isset( $config[ $k ] ) || ! is_array( $config[ $k ] ) ) {
                    $config[ $k ] = [];
                }
                $config = &$config[ $k ];
            }
        }

        return $this;
    }

    /**
     * Get all configuration
     *
     * @return array
     */
    public function get_all() {
        return $this->config;
    }

    /**
     * Merge configuration
     *
     * @param array $config Configuration to merge
     * @return self
     */
    public function merge( array $config ) {
        $this->config = array_merge( $this->config, $config );
        return $this;
    }

    /**
     * Get template ID
     *
     * @return string
     */
    public function get_template_id() {
        return $this->get( 'template_id' );
    }

    /**
     * Get product ID
     *
     * @return int|null
     */
    public function get_product_id() {
        return $this->get( 'product_id' );
    }

    /**
     * Get context data
     *
     * @return array
     */
    public function get_context() {
        return $this->get( 'context', [] );
    }

    /**
     * Get AI settings
     *
     * @return array
     */
    public function get_ai_settings() {
        return $this->get( 'ai_settings', [] );
    }

    /**
     * Get output format
     *
     * @return string
     */
    public function get_output_format() {
        return $this->get( 'output_format', 'html' );
    }

    /**
     * Should auto-save results
     *
     * @return bool
     */
    public function should_auto_save() {
        return (bool) $this->get( 'auto_save', false );
    }

    /**
     * Get field mapping
     *
     * Maps AI output fields to product fields
     *
     * @return array
     */
    public function get_field_mapping() {
        return $this->get( 'field_mapping', [] );
    }

    /**
     * Create config from template
     *
     * Factory method to create a config for a specific template
     *
     * @param string $template_id Template ID
     * @param int $product_id Product ID
     * @param array $overrides Configuration overrides
     * @return Template_Config
     */
    public static function for_template( $template_id, $product_id, array $overrides = [] ) {
        $config = [
            'template_id' => $template_id,
            'product_id' => $product_id,
        ];

        $config = array_merge( $config, $overrides );

        return new self( $config );
    }

    /**
     * Create config from preset
     *
     * @param string $preset_name Preset name
     * @param int $product_id Product ID
     * @return Template_Config
     */
    public static function from_preset( $preset_name, $product_id ) {
        $presets = self::get_presets();

        if ( ! isset( $presets[ $preset_name ] ) ) {
            return new self( [ 'product_id' => $product_id ] );
        }

        $config = $presets[ $preset_name ];
        $config['product_id'] = $product_id;

        return new self( $config );
    }

    /**
     * Get available presets
     *
     * @return array
     */
    public static function get_presets() {
        $presets = [
            'full_description' => [
                'template_id' => 'product_description',
                'ai_settings' => [
                    'model' => 'gpt-4',
                    'temperature' => 0.7,
                    'max_tokens' => 1500,
                ],
                'output_format' => 'html',
                'field_mapping' => [
                    'description' => 'post_content',
                ],
            ],
            'short_description' => [
                'template_id' => 'product_short_description',
                'context' => [
                    'word_limit' => 50,
                ],
                'ai_settings' => [
                    'model' => 'gpt-3.5-turbo',
                    'temperature' => 0.7,
                    'max_tokens' => 200,
                ],
                'output_format' => 'text',
                'field_mapping' => [
                    'short_description' => 'post_excerpt',
                ],
            ],
            'seo_optimized' => [
                'template_id' => 'product_description',
                'context' => [
                    'focus_keyword' => '',
                    'include_schema' => true,
                ],
                'ai_settings' => [
                    'model' => 'gpt-4',
                    'temperature' => 0.6,
                    'max_tokens' => 1000,
                ],
                'output_format' => 'html',
            ],
        ];

        /**
         * Filter available configuration presets
         *
         * @param array $presets Configuration presets
         */
        return apply_filters( 'product_data_generator_config_presets', $presets );
    }

    /**
     * Validate configuration
     *
     * @return bool|\WP_Error
     */
    public function validate() {
        // Check if template exists
        $template_id = $this->get_template_id();
        if ( ! Template_Registry::is_registered( $template_id ) ) {
            return new \WP_Error(
                'invalid_template',
                sprintf( __( 'Template "%s" is not registered.', 'product-data-generator' ), $template_id )
            );
        }

        // Check if product exists
        $product_id = $this->get_product_id();
        if ( $product_id && ! wc_get_product( $product_id ) ) {
            return new \WP_Error(
                'invalid_product',
                sprintf( __( 'Product with ID %d does not exist.', 'product-data-generator' ), $product_id )
            );
        }

        /**
         * Filter configuration validation
         *
         * @param bool|\WP_Error $valid Validation result
         * @param Template_Config $config Configuration instance
         */
        return apply_filters( 'product_data_generator_config_validate', true, $this );
    }
}
