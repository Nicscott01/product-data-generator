<?php
/**
 * Admin UI Class
 *
 * Handles admin interface elements for product data generation
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator;

defined( 'ABSPATH' ) || exit;

class Admin_UI {

    /**
     * Initialize the admin UI
     */
    public static function init() {
        // Add metabox to product editor
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_metabox' ] );
        
        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

        // AJAX handler for generating content
        add_action( 'wp_ajax_pdg_generate_content', [ __CLASS__, 'ajax_generate_content' ] );
    }

    /**
     * Add AI Content Generator metabox
     */
    public static function add_metabox() {
        add_meta_box(
            'pdg_ai_generator',
            __( 'AI Content Generator', 'product-data-generator' ),
            [ __CLASS__, 'render_metabox' ],
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render the AI Content Generator metabox
     *
     * @param \WP_Post $post Post object
     */
    public static function render_metabox( $post ) {
        // Get all registered templates
        $templates = Template_Registry::get_all();

        if ( empty( $templates ) ) {
            echo '<p>' . esc_html__( 'No templates available.', 'product-data-generator' ) . '</p>';
            return;
        }

        // Get last generation times
        $generation_meta = get_post_meta( $post->ID, '_pdg_generations', true );
        if ( ! is_array( $generation_meta ) ) {
            $generation_meta = [];
        }

        wp_nonce_field( 'pdg_generate_nonce', 'pdg_generate_nonce' );

        ?>
        <div class="pdg-metabox">
            <p class="description">
                <?php esc_html_e( 'Generate AI-powered content for this product using the templates below.', 'product-data-generator' ); ?>
            </p>

            <?php foreach ( $templates as $template ) : 
                $template_id = $template->get_id();
                $last_generated = isset( $generation_meta[ $template_id ] ) ? $generation_meta[ $template_id ] : null;
                ?>
                <div class="pdg-template-item" data-template-id="<?php echo esc_attr( $template_id ); ?>">
                    <div class="pdg-template-header">
                        <strong><?php echo esc_html( $template->get_name() ); ?></strong>
                        <?php if ( $last_generated ) : ?>
                            <span class="pdg-last-generated" title="<?php echo esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_generated ) ); ?>">
                                <?php 
                                // translators: %s: human-readable time difference
                                printf( esc_html__( 'Generated %s ago', 'product-data-generator' ), human_time_diff( $last_generated, current_time( 'timestamp' ) ) ); 
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ( $template->get_description() ) : ?>
                        <p class="pdg-template-description"><?php echo esc_html( $template->get_description() ); ?></p>
                    <?php endif; ?>

                    <div class="pdg-template-actions">
                        <button type="button" 
                                class="button pdg-generate-btn" 
                                data-template-id="<?php echo esc_attr( $template_id ); ?>"
                                data-product-id="<?php echo esc_attr( $post->ID ); ?>">
                            <span class="dashicons dashicons-admin-customizer"></span>
                            <?php esc_html_e( 'Generate', 'product-data-generator' ); ?>
                        </button>
                        <span class="pdg-status"></span>
                        <span class="spinner"></span>
                    </div>

                    <div class="pdg-result" style="display: none;">
                        <textarea class="pdg-result-content" rows="8"></textarea>
                        <div class="pdg-result-actions">
                            <button type="button" class="button button-primary pdg-apply-btn">
                                <?php esc_html_e( 'Apply to Product', 'product-data-generator' ); ?>
                            </button>
                            <button type="button" class="button pdg-cancel-btn">
                                <?php esc_html_e( 'Cancel', 'product-data-generator' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            .pdg-metabox {
                margin: -12px -12px 0;
            }
            .pdg-metabox > .description {
                padding: 12px 12px 0;
                margin: 0 0 12px;
            }
            .pdg-template-item {
                border-top: 1px solid #dcdcde;
                padding: 12px;
            }
            .pdg-template-item:first-of-type {
                border-top: none;
            }
            .pdg-template-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 6px;
            }
            .pdg-template-header strong {
                font-size: 13px;
            }
            .pdg-last-generated {
                font-size: 11px;
                color: #646970;
            }
            .pdg-template-description {
                font-size: 12px;
                color: #646970;
                margin: 0 0 8px;
            }
            .pdg-template-actions {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .pdg-generate-btn {
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .pdg-generate-btn .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .pdg-generate-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            .pdg-template-actions .spinner {
                float: none;
                margin: 0;
                display: none;
            }
            .pdg-template-item.is-loading .spinner {
                display: block;
                visibility: visible;
            }
            .pdg-status {
                font-size: 12px;
                color: #646970;
            }
            .pdg-status.success {
                color: #00a32a;
            }
            .pdg-status.error {
                color: #d63638;
            }
            .pdg-result {
                margin-top: 12px;
            }
            .pdg-result-content {
                width: 100%;
                font-family: Consolas, Monaco, monospace;
                font-size: 12px;
                margin-bottom: 8px;
            }
            .pdg-result-actions {
                display: flex;
                gap: 8px;
            }
        </style>
        <?php
    }

    /**
     * AJAX handler for generating content
     */
    public static function ajax_generate_content() {
        check_ajax_referer( 'pdg_generate_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( $_POST['template_id'] ) : '';

        if ( ! $product_id || ! $template_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product or template ID.', 'product-data-generator' ) ] );
        }

        if ( ! current_user_can( 'edit_post', $product_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to edit this product.', 'product-data-generator' ) ] );
        }

        // Get the template
        $template = Template_Registry::get( $template_id );
        if ( ! $template ) {
            wp_send_json_error( [ 'message' => __( 'Template not found.', 'product-data-generator' ) ] );
        }

        // Get the product
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( [ 'message' => __( 'Product not found.', 'product-data-generator' ) ] );
        }

        try {
            // Set the product on the template
            $template->set_product( $product );

            // Get the messages for AI
            $messages = $template->get_messages();

            // Use WordPress AI Client directly
            if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
                wp_send_json_error( [ 'message' => __( 'WordPress AI Client is not available.', 'product-data-generator' ) ] );
            }

            $prompt_builder = \WordPress\AI_Client\AI_Client::prompt_with_wp_error();

            // Set system instruction if available
            if ( ! empty( $messages[0]['content'] ) && $messages[0]['role'] === 'system' ) {
                $prompt_builder->using_system_instruction( $messages[0]['content'] );
            }

            // Add user message
            if ( ! empty( $messages[1]['content'] ) && $messages[1]['role'] === 'user' ) {
                $prompt_builder->with_text( $messages[1]['content'] );
            }

            // Set reasonable defaults
            $prompt_builder->using_temperature( 0.7 );
            $prompt_builder->using_max_tokens( 2000 );

            // Generate text
            $result = $prompt_builder->generate_text();

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( [ 
                    'message' => $result->get_error_message(),
                ] );
            }

            // Update generation timestamp
            $generation_meta = get_post_meta( $product_id, '_pdg_generations', true );
            if ( ! is_array( $generation_meta ) ) {
                $generation_meta = [];
            }
            $generation_meta[ $template_id ] = current_time( 'timestamp' );
            update_post_meta( $product_id, '_pdg_generations', $generation_meta );

            // Fire action hook for custom handling
            do_action( 'product_data_generator_content_generated', $result, $template_id, $product_id );

            wp_send_json_success( [
                'content' => $result,
                'template_id' => $template_id,
                'generated_at' => human_time_diff( current_time( 'timestamp' ), current_time( 'timestamp' ) ),
            ] );

        } catch ( \Exception $e ) {
            wp_send_json_error( [ 
                'message' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Add generate buttons to product edit screen (deprecated - keeping for backwards compatibility)
     *
     * @param \WP_Post $post Post object
     */
    public static function add_generate_buttons( $post ) {
        // Only on product edit screen
        if ( $post->post_type !== 'product' ) {
            return;
        }

        // Check user capability
        if ( ! current_user_can( 'edit_product', $post->ID ) ) {
            return;
        }

        ?>
        <style>
            .pdg-generate-button {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin: 8px 0;
                padding: 6px 12px;
                background: #2271b1;
                color: #fff;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                font-size: 13px;
                transition: background 0.2s;
            }
            .pdg-generate-button:hover {
                background: #135e96;
            }
            .pdg-generate-button:disabled {
                background: #dcdcde;
                cursor: not-allowed;
            }
            .pdg-generate-button .spinner {
                float: none;
                margin: 0;
                display: none;
            }
            .pdg-generate-button.is-loading .spinner {
                display: block;
                visibility: visible;
            }
            .pdg-generate-button .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .pdg-button-wrapper {
                margin: 8px 0;
            }
        </style>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Add button to product description field
                var descriptionWrap = $('#postdivrich');
                if (descriptionWrap.length) {
                    var descButton = $('<div class="pdg-button-wrapper">' +
                        '<button type="button" class="pdg-generate-button" data-template="product_description" data-field="content">' +
                        '<span class="dashicons dashicons-admin-customizer"></span>' +
                        'Generate Description with AI' +
                        '<span class="spinner"></span>' +
                        '</button>' +
                        '</div>');
                    descriptionWrap.before(descButton);
                }

                // Add button to short description field
                var shortDescWrap = $('#postexcerpt');
                if (shortDescWrap.length) {
                    var shortButton = $('<div class="pdg-button-wrapper">' +
                        '<button type="button" class="pdg-generate-button" data-template="product_short_description" data-field="excerpt">' +
                        '<span class="dashicons dashicons-admin-customizer"></span>' +
                        'Generate Short Description with AI' +
                        '<span class="spinner"></span>' +
                        '</button>' +
                        '</div>');
                    shortDescWrap.find('.inside').prepend(shortButton);
                }

                // Handle button clicks
                $(document).on('click', '.pdg-generate-button', async function(e) {
                    e.preventDefault();
                    
                    var $button = $(this);
                    var template = $button.data('template');
                    var field = $button.data('field');
                    var productId = $('#post_ID').val();

                    if (!productId) {
                        alert('Please save the product first before generating content.');
                        return;
                    }

                    // Disable button and show loading
                    $button.prop('disabled', true).addClass('is-loading');

                    try {
                        // Use WordPress AI Client
                        if (typeof wp === 'undefined' || typeof wp.aiClient === 'undefined') {
                            throw new Error('WordPress AI Client is not available. Please ensure it is properly loaded.');
                        }

                        // Get product data via REST API
                        const productData = await $.ajax({
                            url: productDataGenerator.restUrl + 'product-data-generator/v1/product/' + productId,
                            method: 'GET',
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', productDataGenerator.nonce);
                            }
                        });

                        // Get the appropriate prompt based on template
                        const promptData = await $.ajax({
                            url: productDataGenerator.restUrl + 'product-data-generator/v1/prompt/' + productId,
                            method: 'POST',
                            data: { template: template },
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', productDataGenerator.nonce);
                            }
                        });

                        // Generate content using WordPress AI Client
                        const result = await wp.aiClient
                            .prompt(promptData.user_prompt)
                            .usingSystemInstruction(promptData.system_prompt)
                            .usingTemperature(0.7)
                            .usingMaxTokens(template === 'product_short_description' ? 200 : 1000)
                            .generateText();

                        // Insert generated content into appropriate field
                        if (field === 'content') {
                            // For description (TinyMCE or block editor)
                            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                                tinymce.get('content').setContent(result);
                            } else if (typeof wp.data !== 'undefined' && wp.data.select('core/editor')) {
                                // Block editor
                                wp.data.dispatch('core/editor').editPost({ content: result });
                            } else {
                                $('#content').val(result);
                            }
                        } else if (field === 'excerpt') {
                            // For short description - WooCommerce uses TinyMCE for excerpt
                            if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                                tinymce.get('excerpt').setContent(result);
                            } else {
                                // Fallback to textarea if TinyMCE isn't available
                                $('#excerpt').val(result);
                            }
                        }

                        // Show success message
                        var successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>Content generated successfully!</p></div>');
                        $button.closest('.pdg-button-wrapper').after(successMsg);
                        setTimeout(function() {
                            successMsg.fadeOut(function() { $(this).remove(); });
                        }, 3000);

                    } catch (error) {
                        console.error('Generation error:', error);
                        alert('Error generating content: ' + (error.message || error));
                    } finally {
                        // Re-enable button
                        $button.prop('disabled', false).removeClass('is-loading');
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public static function enqueue_scripts( $hook ) {
        // Only on product edit screen
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'product' ) {
            return;
        }

        // Enqueue script for metabox functionality
        wp_enqueue_script(
            'pdg-admin',
            PRODUCT_DATA_GENERATOR_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            PRODUCT_DATA_GENERATOR_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script( 'pdg-admin', 'pdgAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'pdg_generate_nonce' ),
            'autoApplyTemplates' => apply_filters( 'product_data_generator_auto_apply_templates', [ 'book_genre' ] ),
            'i18n' => [
                'generating' => __( 'Generating...', 'product-data-generator' ),
                'success' => __( 'Content generated successfully!', 'product-data-generator' ),
                'error' => __( 'Error generating content', 'product-data-generator' ),
                'confirmApply' => __( 'This will replace existing content. Continue?', 'product-data-generator' ),
            ],
        ] );
    }
}
