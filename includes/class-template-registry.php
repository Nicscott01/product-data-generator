<?php
/**
 * Template Registry Class
 *
 * Manages registration and retrieval of AI prompt templates
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator;

defined( 'ABSPATH' ) || exit;

class Template_Registry {

    /**
     * Registered templates
     *
     * @var array
     */
    private static $templates = [];

    /**
     * Initialized flag
     *
     * @var bool
     */
    private static $initialized = false;

    /**
     * Initialize the registry
     */
    public static function init() {
        if ( self::$initialized ) {
            return;
        }

        // Register default templates immediately
        self::register_default_templates();

        // Allow other plugins to register templates
        add_action( 'product_data_generator_register_templates', [ __CLASS__, 'do_register_templates' ] );

        self::$initialized = true;
    }

    /**
     * Register a template
     *
     * @param Template $template Template instance
     * @return bool True on success, false on failure
     */
    public static function register( Template $template ) {
        $id = $template->get_id();

        if ( self::is_registered( $id ) ) {
            return false;
        }

        self::$templates[ $id ] = $template;

        /**
         * Fires after a template is registered
         *
         * @param string $id Template ID
         * @param Template $template Template instance
         */
        do_action( 'product_data_generator_template_registered', $id, $template );

        return true;
    }

    /**
     * Unregister a template
     *
     * @param string $id Template ID
     * @return bool True on success, false if template doesn't exist
     */
    public static function unregister( $id ) {
        if ( ! self::is_registered( $id ) ) {
            return false;
        }

        unset( self::$templates[ $id ] );

        /**
         * Fires after a template is unregistered
         *
         * @param string $id Template ID
         */
        do_action( 'product_data_generator_template_unregistered', $id );

        return true;
    }

    /**
     * Check if a template is registered
     *
     * @param string $id Template ID
     * @return bool
     */
    public static function is_registered( $id ) {
        return isset( self::$templates[ $id ] );
    }

    /**
     * Get a template
     *
     * @param string $id Template ID
     * @return Template|null Template instance or null if not found
     */
    public static function get( $id ) {
        if ( ! self::is_registered( $id ) ) {
            return null;
        }

        return self::$templates[ $id ];
    }

    /**
     * Get all registered templates
     *
     * @return array
     */
    public static function get_all() {
        return self::$templates;
    }

    /**
     * Get template choices for select fields
     *
     * @return array Key-value pairs of template ID and name
     */
    public static function get_choices() {
        $choices = [];
        foreach ( self::$templates as $id => $template ) {
            $choices[ $id ] = $template->get_name();
        }
        return $choices;
    }

    /**
     * Register default templates
     */
    public static function register_default_templates() {
        // Register product description template
        self::register( new Templates\Product_Description_Template() );
        
        // Register product short description template
        self::register( new Templates\Product_Short_Description_Template() );
        
        // Register SEO template
        self::register( new Templates\Product_SEO_Template() );

        /**
         * Fires when default templates are registered
         *
         * Use this hook to register your own templates
         */
        do_action( 'product_data_generator_register_templates' );
    }

    /**
     * Hook callback for template registration
     */
    public static function do_register_templates() {
        // This is intentionally empty - it's a hook point for other code
    }
}
