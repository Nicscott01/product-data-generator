<?php
/**
 * Queue Admin Interface
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator\Queue;

use ProductDataGenerator\Template_Registry;

defined( 'ABSPATH' ) || exit;

class Queue_Admin {

    /**
     * Initialize the queue admin
     */
    public static function init() {
        add_action( 'add_meta_boxes_pdg_queue', [ __CLASS__, 'add_metaboxes' ] );
        add_action( 'save_post_pdg_queue', [ __CLASS__, 'save_queue_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_pdg_preview_queue', [ __CLASS__, 'ajax_preview_queue' ] );
        add_action( 'wp_ajax_pdg_start_queue', [ __CLASS__, 'ajax_start_queue' ] );
        add_action( 'wp_ajax_pdg_pause_queue', [ __CLASS__, 'ajax_pause_queue' ] );
        add_action( 'wp_ajax_pdg_check_queue_lock', [ __CLASS__, 'ajax_check_queue_lock' ] );
        
        // Custom columns in list table
        add_filter( 'manage_pdg_queue_posts_columns', [ __CLASS__, 'custom_columns' ] );
        add_action( 'manage_pdg_queue_posts_custom_column', [ __CLASS__, 'render_custom_column' ], 10, 2 );
    }

    /**
     * Add metaboxes to queue editor
     */
    public static function add_metaboxes() {
        add_meta_box(
            'pdg_queue_query',
            __( 'Product Query', 'product-data-generator' ),
            [ __CLASS__, 'render_query_metabox' ],
            'pdg_queue',
            'normal',
            'high'
        );

        add_meta_box(
            'pdg_queue_tasks',
            __( 'Tasks to Run', 'product-data-generator' ),
            [ __CLASS__, 'render_tasks_metabox' ],
            'pdg_queue',
            'normal',
            'high'
        );

        add_meta_box(
            'pdg_queue_templates',
            __( 'Generation Settings', 'product-data-generator' ),
            [ __CLASS__, 'render_templates_metabox' ],
            'pdg_queue',
            'normal',
            'high'
        );

        add_meta_box(
            'pdg_queue_options',
            __( 'Queue Options', 'product-data-generator' ),
            [ __CLASS__, 'render_options_metabox' ],
            'pdg_queue',
            'side',
            'default'
        );

        add_meta_box(
            'pdg_queue_preview',
            __( 'Preview & Start', 'product-data-generator' ),
            [ __CLASS__, 'render_preview_metabox' ],
            'pdg_queue',
            'side',
            'default'
        );

        add_meta_box(
            'pdg_queue_progress',
            __( 'Progress', 'product-data-generator' ),
            [ __CLASS__, 'render_progress_metabox' ],
            'pdg_queue',
            'normal',
            'default'
        );
    }

    /**
     * Render query builder metabox
     */
    public static function render_query_metabox( $post ) {
        $query_args = get_post_meta( $post->ID, '_pdg_query_args', true );
        
        // Default query
        if ( empty( $query_args ) ) {
            $query_args = "[\n  'post_type' => 'product',\n  'posts_per_page' => -1,\n  'post_status' => 'publish',\n]";
        }

        wp_nonce_field( 'pdg_queue_meta', 'pdg_queue_nonce' );
        ?>
        <div class="pdg-query-builder">
            <p class="description">
                <?php esc_html_e( 'Enter WP_Query arguments as a PHP array. This will determine which products are processed.', 'product-data-generator' ); ?>
            </p>
            
            <div class="pdg-query-editor-wrapper">
                <textarea 
                    id="pdg-query-args" 
                    name="pdg_query_args" 
                    class="pdg-query-editor"
                    rows="12"
                    spellcheck="false"><?php echo esc_textarea( $query_args ); ?></textarea>
            </div>

            <div class="pdg-query-examples">
                <p><strong><?php esc_html_e( 'Examples:', 'product-data-generator' ); ?></strong></p>
                <ul>
                    <li>
                        <code>['post__in' => [123, 456, 789]]</code> - 
                        <?php esc_html_e( 'Specific product IDs', 'product-data-generator' ); ?>
                    </li>
                    <li>
                        <code>['tax_query' => [['taxonomy' => 'product_cat', 'terms' => 'books']]]</code> - 
                        <?php esc_html_e( 'Products in category', 'product-data-generator' ); ?>
                    </li>
                    <li>
                        <code>['meta_query' => [['key' => '_stock_status', 'value' => 'instock']]]</code> - 
                        <?php esc_html_e( 'Products in stock', 'product-data-generator' ); ?>
                    </li>
                </ul>
            </div>
        </div>

        <style>
            .pdg-query-builder {
                margin: -6px -12px -12px;
                padding: 12px;
            }
            .pdg-query-builder .description {
                margin: 0 0 12px;
            }
            .pdg-query-editor-wrapper {
                position: relative;
                margin-bottom: 16px;
            }
            .pdg-query-editor {
                width: 100%;
                font-family: Consolas, Monaco, 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
                padding: 12px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                background: #f6f7f7;
                resize: vertical;
            }
            .pdg-query-editor:focus {
                border-color: #2271b1;
                background: #fff;
                outline: none;
                box-shadow: 0 0 0 1px #2271b1;
            }
            .pdg-query-examples {
                padding: 12px;
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                border-radius: 4px;
            }
            .pdg-query-examples p {
                margin: 0 0 8px;
            }
            .pdg-query-examples ul {
                margin: 0;
                padding-left: 20px;
            }
            .pdg-query-examples li {
                margin: 6px 0;
                font-size: 12px;
            }
            .pdg-query-examples code {
                background: #fff;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
            }
        </style>
        <?php
    }

    /**
     * Render tasks selection metabox
     */
    public static function render_tasks_metabox( $post ) {
        $task_options = get_post_meta( $post->ID, '_pdg_task_options', true );
        
        // Default all tasks enabled
        if ( ! is_array( $task_options ) ) {
            $task_options = [
                'fetch_data'       => true,
                'replace_image'    => false,
                'generate_content' => true,
            ];
        }
        ?>
        <div class="pdg-task-options">
            <p class="description" style="margin-bottom: 16px;">
                <?php esc_html_e( 'Select which tasks to run for each product. Tasks are executed in the order shown.', 'product-data-generator' ); ?>
            </p>

            <div class="pdg-task-list">
                <p class="pdg-task-item">
                    <label>
                        <input 
                            type="checkbox" 
                            name="pdg_task_fetch_data" 
                            value="1"
                            <?php checked( ! empty( $task_options['fetch_data'] ) ); ?>>
                        <strong><?php esc_html_e( 'Fetch Product Data', 'product-data-generator' ); ?></strong>
                    </label>
                    <br>
                    <span class="description" style="margin-left: 24px;">
                        <?php esc_html_e( 'Run product-specific data fetchers (e.g., book data from ISBNdb, manufacturer data from CSV). Plugins can hook into this task.', 'product-data-generator' ); ?>
                    </span>
                </p>

                <p class="pdg-task-item">
                    <label>
                        <input 
                            type="checkbox" 
                            name="pdg_task_replace_image" 
                            value="1"
                            <?php checked( ! empty( $task_options['replace_image'] ) ); ?>>
                        <strong><?php esc_html_e( 'Replace Featured Image', 'product-data-generator' ); ?></strong>
                    </label>
                    <br>
                    <span class="description" style="margin-left: 24px;">
                        <?php esc_html_e( 'Update product featured images with higher resolution versions. Old images are deleted from media library.', 'product-data-generator' ); ?>
                    </span>
                </p>

                <p class="pdg-task-item">
                    <label>
                        <input 
                            type="checkbox" 
                            name="pdg_task_generate_content" 
                            value="1"
                            <?php checked( ! empty( $task_options['generate_content'] ) ); ?>>
                        <strong><?php esc_html_e( 'Generate AI Content', 'product-data-generator' ); ?></strong>
                    </label>
                    <br>
                    <span class="description" style="margin-left: 24px;">
                        <?php esc_html_e( 'Generate content using selected templates below. Uses AI credits.', 'product-data-generator' ); ?>
                    </span>
                </p>
            </div>

            <div class="pdg-task-note" style="margin-top: 16px; padding: 12px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                <p style="margin: 0;">
                    <strong><?php esc_html_e( 'Tip:', 'product-data-generator' ); ?></strong>
                    <?php esc_html_e( 'For data maintenance (updating images, extracting metadata), uncheck "Generate AI Content" to avoid using AI credits.', 'product-data-generator' ); ?>
                </p>
            </div>
        </div>

        <style>
            .pdg-task-options {
                margin: -6px -12px -12px;
                padding: 12px;
            }
            .pdg-task-list {
                margin-bottom: 12px;
            }
            .pdg-task-item {
                margin: 0 0 16px;
                padding-bottom: 16px;
                border-bottom: 1px solid #dcdcde;
            }
            .pdg-task-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            .pdg-task-item label {
                cursor: pointer;
            }
            .pdg-task-item input[type="checkbox"] {
                margin-right: 6px;
            }
        </style>
        <?php
    }

    /**
     * Render templates metabox
     */
    public static function render_templates_metabox( $post ) {
        $templates = Template_Registry::get_all();
        $template_config = get_post_meta( $post->ID, '_pdg_template_config', true );
        
        if ( ! is_array( $template_config ) ) {
            $template_config = [];
        }

        ?>
        <div class="pdg-templates-config">
            <p class="description">
                <?php esc_html_e( 'Select which templates to generate, and configure individual settings for each.', 'product-data-generator' ); ?>
            </p>

            <table class="pdg-templates-table">
                <thead>
                    <tr>
                        <th class="check-column"></th>
                        <th><?php esc_html_e( 'Template', 'product-data-generator' ); ?></th>
                        <th><?php esc_html_e( 'Skip if Generated', 'product-data-generator' ); ?></th>
                        <th><?php esc_html_e( 'Temperature', 'product-data-generator' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $templates as $template ) : 
                        $template_id = $template->get_id();
                        $config = isset( $template_config[ $template_id ] ) ? $template_config[ $template_id ] : [];
                        $enabled = isset( $config['enabled'] ) ? $config['enabled'] : false;
                        $skip_if_generated = isset( $config['skip_if_generated'] ) ? $config['skip_if_generated'] : true;
                        $temperature = isset( $config['temperature'] ) ? $config['temperature'] : 0.7;
                        ?>
                        <tr class="pdg-template-row <?php echo $enabled ? 'is-enabled' : ''; ?>">
                            <td class="check-column">
                                <input 
                                    type="checkbox" 
                                    name="pdg_templates[<?php echo esc_attr( $template_id ); ?>][enabled]"
                                    value="1"
                                    <?php checked( $enabled ); ?>
                                    class="pdg-template-checkbox"
                                    id="pdg-template-<?php echo esc_attr( $template_id ); ?>">
                            </td>
                            <td class="pdg-template-name">
                                <label for="pdg-template-<?php echo esc_attr( $template_id ); ?>">
                                    <strong><?php echo esc_html( $template->get_name() ); ?></strong>
                                    <?php if ( $template->get_description() ) : ?>
                                        <br><span class="description"><?php echo esc_html( $template->get_description() ); ?></span>
                                    <?php endif; ?>
                                </label>
                            </td>
                            <td class="pdg-template-skip">
                                <label>
                                    <input 
                                        type="checkbox" 
                                        name="pdg_templates[<?php echo esc_attr( $template_id ); ?>][skip_if_generated]"
                                        value="1"
                                        <?php checked( $skip_if_generated ); ?>
                                        <?php disabled( ! $enabled ); ?>>
                                    <?php esc_html_e( 'Skip', 'product-data-generator' ); ?>
                                </label>
                            </td>
                            <td class="pdg-template-temp">
                                <input 
                                    type="number" 
                                    name="pdg_templates[<?php echo esc_attr( $template_id ); ?>][temperature]"
                                    value="<?php echo esc_attr( $temperature ); ?>"
                                    min="0"
                                    max="2"
                                    step="0.1"
                                    class="small-text"
                                    <?php disabled( ! $enabled ); ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
            .pdg-templates-config {
                margin: -6px -12px -12px;
                padding: 12px;
            }
            .pdg-templates-config .description {
                margin: 0 0 12px;
            }
            .pdg-templates-table {
                width: 100%;
                border-collapse: collapse;
                background: #fff;
                border: 1px solid #c3c4c7;
            }
            .pdg-templates-table thead th {
                background: #f6f7f7;
                padding: 8px 12px;
                text-align: left;
                font-weight: 600;
                font-size: 12px;
                border-bottom: 1px solid #c3c4c7;
            }
            .pdg-templates-table thead .check-column {
                width: 40px;
            }
            .pdg-templates-table tbody td {
                padding: 12px;
                border-bottom: 1px solid #dcdcde;
            }
            .pdg-template-row:last-child td {
                border-bottom: none;
            }
            .pdg-template-row:not(.is-enabled) {
                opacity: 0.5;
            }
            .pdg-template-name label {
                cursor: pointer;
            }
            .pdg-template-name .description {
                font-size: 12px;
                color: #646970;
                font-weight: normal;
            }
            .pdg-template-skip label,
            .pdg-template-temp {
                font-size: 12px;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                // Enable/disable dependent fields when template checkbox changes
                $('.pdg-template-checkbox').on('change', function() {
                    var $row = $(this).closest('tr');
                    var isEnabled = $(this).is(':checked');
                    
                    $row.toggleClass('is-enabled', isEnabled);
                    $row.find('input[type="checkbox"]:not(.pdg-template-checkbox), input[type="number"]').prop('disabled', !isEnabled);
                });
            });
        </script>
        <?php
    }

    /**
     * Render options metabox
     */
    public static function render_options_metabox( $post ) {
        $batch_size = get_post_meta( $post->ID, '_pdg_batch_size', true );
        $delay = get_post_meta( $post->ID, '_pdg_delay', true );
        $retry_failed = get_post_meta( $post->ID, '_pdg_retry_failed', true );

        if ( empty( $batch_size ) ) {
            $batch_size = 5;
        }
        if ( empty( $delay ) ) {
            $delay = 2;
        }
        ?>
        <div class="pdg-queue-options">
            <p class="pdg-option-row">
                <label for="pdg-batch-size">
                    <strong><?php esc_html_e( 'Batch Size', 'product-data-generator' ); ?></strong><br>
                    <input 
                        type="number" 
                        id="pdg-batch-size"
                        name="pdg_batch_size" 
                        value="<?php echo esc_attr( $batch_size ); ?>"
                        min="1"
                        max="50"
                        class="small-text">
                    <span class="description"><?php esc_html_e( 'Products per batch', 'product-data-generator' ); ?></span>
                </label>
            </p>

            <p class="pdg-option-row">
                <label for="pdg-delay">
                    <strong><?php esc_html_e( 'Delay Between Batches', 'product-data-generator' ); ?></strong><br>
                    <input 
                        type="number" 
                        id="pdg-delay"
                        name="pdg_delay" 
                        value="<?php echo esc_attr( $delay ); ?>"
                        min="0"
                        max="60"
                        step="1"
                        class="small-text">
                    <span class="description"><?php esc_html_e( 'seconds', 'product-data-generator' ); ?></span>
                </label>
            </p>

            <p class="pdg-option-row">
                <label for="pdg-retry-failed">
                    <input 
                        type="checkbox" 
                        id="pdg-retry-failed"
                        name="pdg_retry_failed" 
                        value="1"
                        <?php checked( $retry_failed ); ?>>
                    <?php esc_html_e( 'Retry failed generations', 'product-data-generator' ); ?>
                </label>
            </p>
        </div>

        <style>
            .pdg-queue-options {
                margin: -6px -12px -12px;
                padding: 12px;
            }
            .pdg-option-row {
                margin: 0 0 12px;
                padding: 0;
            }
            .pdg-option-row:last-child {
                margin-bottom: 0;
            }
            .pdg-option-row label {
                display: block;
            }
            .pdg-option-row .description {
                display: block;
                margin-top: 4px;
                font-size: 12px;
                color: #646970;
            }
        </style>
        <?php
    }

    /**
     * Render preview metabox
     */
    public static function render_preview_metabox( $post ) {
        $status = $post->post_status;
        $is_processing = $status === 'pdg_processing';
        $is_completed = $status === 'pdg_completed';
        $can_start = ! in_array( $status, [ 'pdg_processing' ], true );

        ?>
        <div class="pdg-queue-preview">
            <div id="pdg-preview-loading" style="display: none;">
                <p><span class="spinner is-active" style="float: none; margin: 0;"></span> <?php esc_html_e( 'Loading preview...', 'product-data-generator' ); ?></p>
            </div>

            <div id="pdg-preview-content" style="display: none;">
                <div class="pdg-preview-stats">
                    <div class="pdg-stat">
                        <strong class="pdg-stat-label"><?php esc_html_e( 'Products:', 'product-data-generator' ); ?></strong>
                        <span class="pdg-stat-value" id="pdg-preview-products">-</span>
                    </div>
                    <div class="pdg-stat">
                        <strong class="pdg-stat-label"><?php esc_html_e( 'Templates:', 'product-data-generator' ); ?></strong>
                        <span class="pdg-stat-value" id="pdg-preview-templates">-</span>
                    </div>
                    <div class="pdg-stat pdg-stat-total">
                        <strong class="pdg-stat-label"><?php esc_html_e( 'Total Generations:', 'product-data-generator' ); ?></strong>
                        <span class="pdg-stat-value" id="pdg-preview-total">-</span>
                    </div>
                </div>

                <div class="pdg-preview-table-wrapper" style="display: none;">
                    <table class="pdg-preview-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Product', 'product-data-generator' ); ?></th>
                                <th><?php esc_html_e( 'Templates', 'product-data-generator' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="pdg-preview-tbody"></tbody>
                    </table>
                    <p class="pdg-preview-note">
                        <?php esc_html_e( 'Showing first 10 products', 'product-data-generator' ); ?>
                    </p>
                </div>
            </div>

            <div class="pdg-queue-actions">
                <button 
                    type="button" 
                    id="pdg-preview-btn" 
                    class="button button-secondary button-large"
                    data-queue-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Preview Queue', 'product-data-generator' ); ?>
                </button>

                <button 
                    type="button" 
                    id="pdg-start-btn" 
                    class="button button-primary button-large"
                    data-queue-id="<?php echo esc_attr( $post->ID ); ?>"
                    <?php disabled( ! $can_start ); ?>>
                    <?php esc_html_e( 'Start Queue', 'product-data-generator' ); ?>
                </button>

                <?php if ( $is_processing ) : ?>
                    <button 
                        type="button" 
                        id="pdg-pause-btn" 
                        class="button button-secondary button-large">
                        <?php esc_html_e( 'Pause Queue', 'product-data-generator' ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ( $is_processing ) : ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e( 'This queue is currently processing. Check the Progress section below for details.', 'product-data-generator' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $is_completed ) : ?>
                <div class="notice notice-success inline">
                    <p><?php esc_html_e( 'This queue has completed processing. You can start it again to reprocess.', 'product-data-generator' ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .pdg-queue-preview {
                margin: -6px -12px -12px;
                padding: 12px;
            }
            .pdg-preview-stats {
                background: #f6f7f7;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 12px;
                margin-bottom: 12px;
            }
            .pdg-stat {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 4px 0;
                font-size: 13px;
            }
            .pdg-stat-total {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid #c3c4c7;
                font-size: 14px;
            }
            .pdg-stat-value {
                font-weight: 600;
                color: #2271b1;
            }
            .pdg-preview-table-wrapper {
                max-height: 300px;
                overflow-y: auto;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                margin-bottom: 12px;
            }
            .pdg-preview-table {
                width: 100%;
                border-collapse: collapse;
                background: #fff;
            }
            .pdg-preview-table thead th {
                position: sticky;
                top: 0;
                background: #f6f7f7;
                padding: 8px;
                text-align: left;
                font-size: 12px;
                font-weight: 600;
                border-bottom: 1px solid #c3c4c7;
            }
            .pdg-preview-table tbody td {
                padding: 8px;
                font-size: 12px;
                border-bottom: 1px solid #dcdcde;
            }
            .pdg-preview-table tbody tr:last-child td {
                border-bottom: none;
            }
            .pdg-preview-note {
                font-size: 11px;
                color: #646970;
                font-style: italic;
                text-align: center;
                margin: 8px 0 0;
            }
            .pdg-queue-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 12px;
            }
            .pdg-queue-actions .button {
                width: 100%;
            }
        </style>
        <?php
    }

    /**
     * Render progress metabox
     */
    public static function render_progress_metabox( $post ) {
        $progress = get_post_meta( $post->ID, '_pdg_progress', true );
        
        if ( ! is_array( $progress ) || empty( $progress ) ) {
            echo '<p class="description">' . esc_html__( 'No progress data available yet. Start the queue to see progress.', 'product-data-generator' ) . '</p>';
            return;
        }

        $total = isset( $progress['total'] ) ? $progress['total'] : 0;
        $completed = isset( $progress['completed'] ) ? $progress['completed'] : 0;
        $failed = isset( $progress['failed'] ) ? $progress['failed'] : 0;
        $current_product = isset( $progress['current_product_id'] ) ? $progress['current_product_id'] : 0;
        $percentage = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;

        ?>
        <div class="pdg-queue-progress">
            <div class="pdg-progress-bar-wrapper">
                <div class="pdg-progress-bar">
                    <div class="pdg-progress-fill" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
                </div>
                <div class="pdg-progress-text">
                    <?php 
                    // translators: 1: completed count, 2: total count, 3: percentage
                    printf( esc_html__( '%1$d of %2$d (%3$d%%)', 'product-data-generator' ), $completed, $total, $percentage ); 
                    ?>
                </div>
            </div>

            <div class="pdg-progress-stats">
                <div class="pdg-progress-stat">
                    <span class="pdg-progress-stat-label"><?php esc_html_e( 'Completed:', 'product-data-generator' ); ?></span>
                    <span class="pdg-progress-stat-value pdg-success"><?php echo esc_html( $completed ); ?></span>
                </div>
                <div class="pdg-progress-stat">
                    <span class="pdg-progress-stat-label"><?php esc_html_e( 'Failed:', 'product-data-generator' ); ?></span>
                    <span class="pdg-progress-stat-value pdg-error"><?php echo esc_html( $failed ); ?></span>
                </div>
                <div class="pdg-progress-stat">
                    <span class="pdg-progress-stat-label"><?php esc_html_e( 'Remaining:', 'product-data-generator' ); ?></span>
                    <span class="pdg-progress-stat-value"><?php echo esc_html( $total - $completed - $failed ); ?></span>
                </div>
            </div>

            <?php if ( $current_product ) : 
                $product = wc_get_product( $current_product );
                if ( $product ) :
                ?>
                <div class="pdg-current-product">
                    <strong><?php esc_html_e( 'Currently Processing:', 'product-data-generator' ); ?></strong><br>
                    <a href="<?php echo esc_url( get_edit_post_link( $current_product ) ); ?>" target="_blank">
                        <?php echo esc_html( $product->get_name() ); ?>
                    </a>
                </div>
            <?php 
                endif;
            endif; 
            ?>
        </div>

        <style>
            .pdg-queue-progress {
                margin: -6px -12px -12px;
                padding: 12px;
            }
            .pdg-progress-bar-wrapper {
                margin-bottom: 16px;
            }
            .pdg-progress-bar {
                height: 24px;
                background: #f0f0f1;
                border-radius: 4px;
                overflow: hidden;
                margin-bottom: 4px;
            }
            .pdg-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #2271b1, #0a4b78);
                transition: width 0.3s ease;
            }
            .pdg-progress-text {
                font-size: 12px;
                color: #646970;
                text-align: center;
            }
            .pdg-progress-stats {
                display: flex;
                justify-content: space-around;
                padding: 12px;
                background: #f6f7f7;
                border-radius: 4px;
                margin-bottom: 12px;
            }
            .pdg-progress-stat {
                text-align: center;
                font-size: 13px;
            }
            .pdg-progress-stat-label {
                display: block;
                color: #646970;
                margin-bottom: 4px;
            }
            .pdg-progress-stat-value {
                display: block;
                font-size: 18px;
                font-weight: 600;
            }
            .pdg-progress-stat-value.pdg-success {
                color: #00a32a;
            }
            .pdg-progress-stat-value.pdg-error {
                color: #d63638;
            }
            .pdg-current-product {
                padding: 12px;
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                border-radius: 4px;
                font-size: 13px;
            }
            .pdg-current-product a {
                text-decoration: none;
            }
        </style>
        <?php
    }

    /**
     * Save queue meta
     */
    public static function save_queue_meta( $post_id, $post ) {
        // Check nonce
        if ( ! isset( $_POST['pdg_queue_nonce'] ) || ! wp_verify_nonce( $_POST['pdg_queue_nonce'], 'pdg_queue_meta' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Don't save if processing
        if ( $post->post_status === 'pdg_processing' ) {
            return;
        }

        // Save query args
        if ( isset( $_POST['pdg_query_args'] ) ) {
            update_post_meta( $post_id, '_pdg_query_args', wp_unslash( $_POST['pdg_query_args'] ) );
        }

        // Save template config
        if ( isset( $_POST['pdg_templates'] ) && is_array( $_POST['pdg_templates'] ) ) {
            $template_config = [];
            
            foreach ( $_POST['pdg_templates'] as $template_id => $config ) {
                $template_config[ $template_id ] = [
                    'enabled'           => isset( $config['enabled'] ) ? true : false,
                    'skip_if_generated' => isset( $config['skip_if_generated'] ) ? true : false,
                    'temperature'       => isset( $config['temperature'] ) ? floatval( $config['temperature'] ) : 0.7,
                ];
            }
            
            update_post_meta( $post_id, '_pdg_template_config', $template_config );
        }

        // Save task options
        $task_options = [
            'fetch_data'       => isset( $_POST['pdg_task_fetch_data'] ) ? true : false,
            'replace_image'    => isset( $_POST['pdg_task_replace_image'] ) ? true : false,
            'generate_content' => isset( $_POST['pdg_task_generate_content'] ) ? true : false,
        ];
        update_post_meta( $post_id, '_pdg_task_options', $task_options );

        // Save options
        if ( isset( $_POST['pdg_batch_size'] ) ) {
            update_post_meta( $post_id, '_pdg_batch_size', absint( $_POST['pdg_batch_size'] ) );
        }

        if ( isset( $_POST['pdg_delay'] ) ) {
            update_post_meta( $post_id, '_pdg_delay', absint( $_POST['pdg_delay'] ) );
        }

        if ( isset( $_POST['pdg_retry_failed'] ) ) {
            update_post_meta( $post_id, '_pdg_retry_failed', true );
        } else {
            delete_post_meta( $post_id, '_pdg_retry_failed' );
        }
    }

    /**
     * AJAX handler for preview queue
     */
    public static function ajax_preview_queue() {
        check_ajax_referer( 'pdg_queue_nonce', 'nonce' );

        $queue_id = isset( $_POST['queue_id'] ) ? intval( $_POST['queue_id'] ) : 0;

        if ( ! $queue_id || ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid queue or permissions.', 'product-data-generator' ) ] );
        }

        $preview = Queue_Processor::preview_queue( $queue_id );

        if ( is_wp_error( $preview ) ) {
            wp_send_json_error( [ 'message' => $preview->get_error_message() ] );
        }

        wp_send_json_success( $preview );
    }

    /**
     * AJAX handler for starting queue
     */
    public static function ajax_start_queue() {
        check_ajax_referer( 'pdg_queue_nonce', 'nonce' );

        $queue_id = isset( $_POST['queue_id'] ) ? intval( $_POST['queue_id'] ) : 0;

        if ( ! $queue_id || ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid queue or permissions.', 'product-data-generator' ) ] );
        }

        // Check for existing processing queue
        $existing = get_posts( [
            'post_type'      => 'pdg_queue',
            'post_status'    => 'pdg_processing',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post__not_in'   => [ $queue_id ],
        ] );

        if ( ! empty( $existing ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Another queue is already processing. Please wait for it to complete.', 'product-data-generator' ),
            ] );
        }

        $result = Queue_Processor::start_queue( $queue_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 
            'message' => __( 'Queue started successfully!', 'product-data-generator' ),
        ] );
    }

    /**
     * AJAX handler for pausing queue
     */
    public static function ajax_pause_queue() {
        check_ajax_referer( 'pdg_queue_nonce', 'nonce' );

        $queue_id = isset( $_POST['queue_id'] ) ? intval( $_POST['queue_id'] ) : 0;

        if ( ! $queue_id || ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid queue or permissions.', 'product-data-generator' ) ] );
        }

        $result = Queue_Processor::pause_queue( $queue_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 
            'message' => __( 'Queue paused successfully!', 'product-data-generator' ),
        ] );
    }

    /**
     * AJAX handler to check queue lock status
     */
    public static function ajax_check_queue_lock() {
        check_ajax_referer( 'pdg_queue_nonce', 'nonce' );

        $has_lock = Queue_Processor::has_active_queue();

        wp_send_json_success( [ 'has_lock' => $has_lock ] );
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_scripts( $hook ) {
        global $typenow;

        // Enqueue CSS on list table
        if ( $typenow === 'pdg_queue' ) {
            wp_enqueue_style(
                'pdg-queue-admin',
                PRODUCT_DATA_GENERATOR_PLUGIN_URL . 'assets/css/queue-admin.css',
                [],
                PRODUCT_DATA_GENERATOR_VERSION
            );
        }

        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'pdg_queue' ) {
            return;
        }

        wp_enqueue_script(
            'pdg-queue-admin',
            PRODUCT_DATA_GENERATOR_PLUGIN_URL . 'assets/js/queue-admin.js',
            [ 'jquery' ],
            PRODUCT_DATA_GENERATOR_VERSION,
            true
        );

        wp_localize_script( 'pdg-queue-admin', 'pdgQueue', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pdg_queue_nonce' ),
            'i18n'    => [
                'error'           => __( 'Error', 'product-data-generator' ),
                'previewError'    => __( 'Failed to load preview', 'product-data-generator' ),
                'startError'      => __( 'Failed to start queue', 'product-data-generator' ),
                'pauseError'      => __( 'Failed to pause queue', 'product-data-generator' ),
                'noTemplates'     => __( 'Please select at least one template.', 'product-data-generator' ),
                'confirmStart'    => __( 'Are you sure you want to start this queue?', 'product-data-generator' ),
                'confirmPause'    => __( 'Are you sure you want to pause this queue?', 'product-data-generator' ),
            ],
        ] );
    }

    /**
     * Add custom columns to list table
     */
    public static function custom_columns( $columns ) {
        $new_columns = [];
        
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            
            if ( $key === 'title' ) {
                $new_columns['pdg_progress'] = __( 'Progress', 'product-data-generator' );
                $new_columns['pdg_products'] = __( 'Products', 'product-data-generator' );
                $new_columns['pdg_templates'] = __( 'Templates', 'product-data-generator' );
            }
        }
        
        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public static function render_custom_column( $column, $post_id ) {
        switch ( $column ) {
            case 'pdg_progress':
                $progress = get_post_meta( $post_id, '_pdg_progress', true );
                
                if ( is_array( $progress ) && ! empty( $progress ) ) {
                    $total = isset( $progress['total'] ) ? $progress['total'] : 0;
                    $completed = isset( $progress['completed'] ) ? $progress['completed'] : 0;
                    $percentage = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;
                    
                    echo '<div class="pdg-progress-mini">';
                    echo '<div class="pdg-progress-bar-mini">';
                    echo '<div class="pdg-progress-fill-mini" style="width: ' . esc_attr( $percentage ) . '%;"></div>';
                    echo '</div>';
                    echo '<span class="pdg-progress-text-mini">' . esc_html( $completed . '/' . $total ) . '</span>';
                    echo '</div>';
                } else {
                    echo '<span class="pdg-progress-text-mini">—</span>';
                }
                break;

            case 'pdg_products':
                $preview = get_post_meta( $post_id, '_pdg_preview_cache', true );
                
                if ( is_array( $preview ) && isset( $preview['product_count'] ) ) {
                    echo esc_html( $preview['product_count'] );
                } else {
                    echo '—';
                }
                break;

            case 'pdg_templates':
                $template_config = get_post_meta( $post_id, '_pdg_template_config', true );
                
                if ( is_array( $template_config ) ) {
                    $enabled = array_filter( $template_config, function( $config ) {
                        return isset( $config['enabled'] ) && $config['enabled'];
                    } );
                    echo esc_html( count( $enabled ) );
                } else {
                    echo '—';
                }
                break;
        }
    }
}
