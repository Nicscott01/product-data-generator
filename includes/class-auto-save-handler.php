<?php
/**
 * Auto-save handler for built-in templates
 *
 * Automatically saves generated content for product_description and product_short_description
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator;

defined( 'ABSPATH' ) || exit;

class Auto_Save_Handler {

    /**
     * Initialize the auto-save handler
     */
    public static function init() {
        add_action( 'product_data_generator_content_generated', [ __CLASS__, 'handle_auto_save' ], 10, 3 );
    }

    /**
     * Handle auto-saving for specific templates
     *
     * @param string $content Generated content
     * @param string $template_id Template ID
     * @param int $product_id Product ID
     */
    public static function handle_auto_save( $content, $template_id, $product_id ) {
        // Get the product
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return;
        }

        // Handle different template types
        switch ( $template_id ) {
            case 'product_description':
                self::save_product_description( $product, $content );
                break;

            case 'product_short_description':
                self::save_product_short_description( $product, $content );
                break;

            case 'product_seo':
                self::save_product_seo( $product, $content );
                break;
        }
    }

    /**
     * Save product description
     *
     * @param \WC_Product $product Product object
     * @param string $content Generated content
     */
    private static function save_product_description( $product, $content ) {
        $product->set_description( $content );
        $product->save();
        
        error_log( sprintf( 
            'PDG Auto-Save: Saved description for product #%d (%s)', 
            $product->get_id(), 
            $product->get_name() 
        ) );
    }

    /**
     * Save product short description
     *
     * @param \WC_Product $product Product object
     * @param string $content Generated content
     */
    private static function save_product_short_description( $product, $content ) {
        $product->set_short_description( $content );
        $product->save();
        
        error_log( sprintf( 
            'PDG Auto-Save: Saved short description for product #%d (%s)', 
            $product->get_id(), 
            $product->get_name() 
        ) );
    }

    /**
     * Save product SEO data
     *
     * @param \WC_Product $product Product object
     * @param string $content Generated content (JSON)
     */
    private static function save_product_seo( $product, $content ) {
        // Parse JSON content
        $seo_data = json_decode( $content, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $seo_data ) ) {
            error_log( 'PDG Auto-Save: Failed to parse SEO JSON for product #' . $product->get_id() );
            return;
        }

        // Save to Yoast SEO if available
        if ( class_exists( 'WPSEO_Meta' ) ) {
            if ( isset( $seo_data['meta_title'] ) ) {
                update_post_meta( $product->get_id(), '_yoast_wpseo_title', sanitize_text_field( $seo_data['meta_title'] ) );
            }
            
            if ( isset( $seo_data['meta_description'] ) ) {
                update_post_meta( $product->get_id(), '_yoast_wpseo_metadesc', sanitize_text_field( $seo_data['meta_description'] ) );
            }
            
            if ( isset( $seo_data['focus_keyword'] ) ) {
                update_post_meta( $product->get_id(), '_yoast_wpseo_focuskw', sanitize_text_field( $seo_data['focus_keyword'] ) );
            }
            
            error_log( sprintf( 
                'PDG Auto-Save: Saved SEO data for product #%d (%s)', 
                $product->get_id(), 
                $product->get_name() 
            ) );
        } else {
            error_log( 'PDG Auto-Save: Yoast SEO not available for product #' . $product->get_id() );
        }
    }
}
