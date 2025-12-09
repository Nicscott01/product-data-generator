<?php
/**
 * REST API Class
 *
 * Handles REST API endpoints for product data generation
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator;

defined( 'ABSPATH' ) || exit;

class REST_API {

    /**
     * Initialize REST API
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Get product data endpoint
        register_rest_route( 'product-data-generator/v1', '/product/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [ __CLASS__, 'get_product_data' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ],
            ],
        ] );

        // Get prompt data endpoint
        register_rest_route( 'product-data-generator/v1', '/prompt/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [ __CLASS__, 'get_prompt_data' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ],
                'template' => [
                    'required' => false,
                    'default' => 'product_description',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // Generate content endpoint
        register_rest_route( 'product-data-generator/v1', '/generate/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [ __CLASS__, 'generate_content' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ],
                'template' => [
                    'required' => false,
                    'default' => 'product_description',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'auto_save' => [
                    'required' => false,
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ] );
    }

    /**
     * Check user permission
     *
     * @param \WP_REST_Request $request Request object
     * @return bool
     */
    public static function check_permission( $request ) {
        return current_user_can( 'edit_products' );
    }

    /**
     * Get product data
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public static function get_product_data( $request ) {
        $product_id = $request->get_param( 'id' );
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return new \WP_Error(
                'product_not_found',
                __( 'Product not found.', 'product-data-generator' ),
                [ 'status' => 404 ]
            );
        }

        // Return basic product data
        $data = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'categories' => wp_list_pluck( wc_get_product_terms( $product_id, 'product_cat' ), 'name' ),
            'tags' => wp_list_pluck( wc_get_product_terms( $product_id, 'product_tag' ), 'name' ),
        ];

        return rest_ensure_response( $data );
    }

    /**
     * Get prompt data for generation
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public static function get_prompt_data( $request ) {
        $product_id = $request->get_param( 'id' );
        $template_id = $request->get_param( 'template' );

        // Ensure templates are initialized
        ensure_initialized();

        // Get template
        $template = Template_Registry::get( $template_id );
        if ( ! $template ) {
            return new \WP_Error(
                'template_not_found',
                sprintf( __( 'Template "%s" not found.', 'product-data-generator' ), $template_id ),
                [ 'status' => 404 ]
            );
        }

        // Get product
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new \WP_Error(
                'product_not_found',
                __( 'Product not found.', 'product-data-generator' ),
                [ 'status' => 404 ]
            );
        }

        // Set product on template
        $template->set_product( $product );

        // Get messages
        $messages = $template->get_messages();

        $response = [
            'system_prompt' => $messages[0]['content'] ?? '',
            'user_prompt' => $messages[1]['content'] ?? '',
            'template_id' => $template_id,
            'product_id' => $product_id,
        ];

        return rest_ensure_response( $response );
    }

    /**
     * Generate content
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public static function generate_content( $request ) {
        $product_id = $request->get_param( 'id' );
        $template_id = $request->get_param( 'template' );
        $auto_save = $request->get_param( 'auto_save' );

        $config = [
            'auto_save' => $auto_save,
        ];

        $result = generate_product_content( $product_id, $template_id, $config );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }
}
