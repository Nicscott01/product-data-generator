<?php
/**
 * Settings Class
 *
 * Handles plugin settings page and options
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator;

defined( 'ABSPATH' ) || exit;

class Settings {

    /**
     * Option name for storing settings
     */
    const OPTION_GROUP = 'pdg_settings';
    const OPTION_NAME = 'pdg_settings';

    /**
     * Initialize settings
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    /**
     * Add settings page under Products menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=product',
            __( 'AI Content Settings', 'product-data-generator' ),
            __( 'AI Content Settings', 'product-data-generator' ),
            'manage_woocommerce',
            'pdg-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
                'default' => self::get_default_settings(),
            ]
        );

        // Brand Voice Section
        add_settings_section(
            'pdg_brand_voice_section',
            __( 'Brand Voice & Guidelines', 'product-data-generator' ),
            [ __CLASS__, 'render_brand_voice_section' ],
            'pdg-settings'
        );

        add_settings_field(
            'brand_voice',
            __( 'Brand Voice', 'product-data-generator' ),
            [ __CLASS__, 'render_brand_voice_field' ],
            'pdg-settings',
            'pdg_brand_voice_section'
        );
    }

    /**
     * Get default settings
     *
     * @return array
     */
    public static function get_default_settings() {
        return [
            'brand_voice' => '',
        ];
    }

    /**
     * Get a specific setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $settings = get_option( self::OPTION_NAME, self::get_default_settings() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Sanitize settings
     *
     * @param array $input Raw input
     * @return array
     */
    public static function sanitize_settings( $input ) {
        $sanitized = [];

        if ( isset( $input['brand_voice'] ) ) {
            $sanitized['brand_voice'] = wp_kses_post( $input['brand_voice'] );
        }

        return $sanitized;
    }

    /**
     * Render brand voice section description
     */
    public static function render_brand_voice_section() {
        ?>
        <p>
            <?php esc_html_e( 'Define your unique brand voice and writing style. This will be included in all AI-generated content to maintain consistency across your product descriptions, SEO content, and other generated text.', 'product-data-generator' ); ?>
        </p>
        <?php
    }

    /**
     * Render brand voice field
     */
    public static function render_brand_voice_field() {
        $settings = get_option( self::OPTION_NAME, self::get_default_settings() );
        $value = isset( $settings['brand_voice'] ) ? $settings['brand_voice'] : '';
        ?>
        <textarea 
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[brand_voice]" 
            id="pdg_brand_voice" 
            rows="10" 
            class="large-text code"
            placeholder="<?php echo esc_attr( self::get_brand_voice_placeholder() ); ?>"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Describe your bookstore\'s personality, tone, and writing style. Be specific about what makes your voice unique.', 'product-data-generator' ); ?>
        </p>
        
        <details style="margin-top: 15px;">
            <summary style="cursor: pointer; color: #2271b1; font-weight: 600;">
                <?php esc_html_e( 'Examples & Tips', 'product-data-generator' ); ?>
            </summary>
            <div style="margin-top: 10px; padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1;">
                <h4 style="margin-top: 0;"><?php esc_html_e( 'Example Brand Voice:', 'product-data-generator' ); ?></h4>
                <p style="font-family: monospace; background: white; padding: 10px; margin: 10px 0;">
                    <?php echo esc_html( self::get_brand_voice_example() ); ?>
                </p>
                
                <h4><?php esc_html_e( 'What to Include:', 'product-data-generator' ); ?></h4>
                <ul style="margin-left: 20px;">
                    <li><?php esc_html_e( 'Tone: friendly, professional, quirky, academic, warm, enthusiastic', 'product-data-generator' ); ?></li>
                    <li><?php esc_html_e( 'Perspective: first person ("we"), second person ("you"), third person', 'product-data-generator' ); ?></li>
                    <li><?php esc_html_e( 'Style preferences: casual vs formal, short vs long sentences, humor level', 'product-data-generator' ); ?></li>
                    <li><?php esc_html_e( 'Target audience: who you\'re speaking to and what they care about', 'product-data-generator' ); ?></li>
                    <li><?php esc_html_e( 'Unique elements: signature phrases, values to emphasize, topics to highlight', 'product-data-generator' ); ?></li>
                    <li><?php esc_html_e( 'Words/phrases to avoid: industry jargon, overused clichés, specific terms', 'product-data-generator' ); ?></li>
                </ul>
            </div>
        </details>
        <?php
    }

    /**
     * Get brand voice placeholder text
     *
     * @return string
     */
    private static function get_brand_voice_placeholder() {
        return "Example:\n\nOur voice is warm, literary, and personal. We write as a knowledgeable friend recommending books over coffee. Use \"we\" when referring to the bookshop. Keep sentences conversational but sophisticated. Emphasize the emotional journey and literary merit of each book. Avoid corporate jargon and generic marketing speak.";
    }

    /**
     * Get brand voice example text
     *
     * @return string
     */
    private static function get_brand_voice_example() {
        return "We're a cozy independent bookshop with a passion for literary fiction and local authors. Our voice is warm, personal, and knowledgeable - like a trusted friend recommending their favorite book over coffee.\n\nWrite in first person plural (\"we\") and speak directly to readers using \"you\". Keep sentences conversational but sophisticated. We celebrate the literary merit and emotional depth of books, not just plot summaries.\n\nEmphasize: Community connection, the reading experience, author craftsmanship, and how books make people feel.\n\nAvoid: Corporate jargon, hard-sell language, generic marketing phrases like \"must-read\" or \"page-turner\", and overly formal academic tone.";
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Handle form submission message
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error(
                'pdg_messages',
                'pdg_message',
                __( 'Settings saved successfully.', 'product-data-generator' ),
                'updated'
            );
        }

        settings_errors( 'pdg_messages' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( 'pdg-settings' );
                submit_button( __( 'Save Settings', 'product-data-generator' ) );
                ?>
            </form>
        </div>
        <?php
    }
}
