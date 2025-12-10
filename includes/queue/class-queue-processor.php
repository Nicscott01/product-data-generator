<?php
/**
 * Queue Processor
 *
 * Handles background processing of bulk generation queues using Action Scheduler
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator\Queue;

use ProductDataGenerator\Template_Registry;

defined( 'ABSPATH' ) || exit;

class Queue_Processor {

    const HOOK_PROCESS_BATCH = 'pdg_process_queue_batch';
    const HOOK_PROCESS_PRODUCT = 'pdg_process_queue_product';
    
    /**
     * Initialize the processor
     */
    public static function init() {
        // Register Action Scheduler hooks
        add_action( self::HOOK_PROCESS_BATCH, [ __CLASS__, 'process_batch' ], 10, 1 );
        add_action( self::HOOK_PROCESS_PRODUCT, [ __CLASS__, 'process_product' ], 10, 3 );
    }

    /**
     * Check if there's an active queue processing
     *
     * @return bool
     */
    public static function has_active_queue() {
        $processing = get_posts( [
            'post_type'      => 'pdg_queue',
            'post_status'    => 'pdg_processing',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        return ! empty( $processing );
    }

    /**
     * Preview queue without starting it
     *
     * @param int $queue_id Queue post ID
     * @return array|\WP_Error Preview data or error
     */
    public static function preview_queue( $queue_id ) {
        $queue = get_post( $queue_id );
        
        if ( ! $queue || $queue->post_type !== 'pdg_queue' ) {
            return new \WP_Error( 'invalid_queue', __( 'Invalid queue ID.', 'product-data-generator' ) );
        }

        // Get query args
        $query_args_str = get_post_meta( $queue_id, '_pdg_query_args', true );
        $query_args = self::parse_query_args( $query_args_str );
        
        if ( is_wp_error( $query_args ) ) {
            return $query_args;
        }

        // Get template config
        $template_config = get_post_meta( $queue_id, '_pdg_template_config', true );
        
        if ( ! is_array( $template_config ) ) {
            return new \WP_Error( 'no_templates', __( 'No template configuration found.', 'product-data-generator' ) );
        }

        // Filter enabled templates
        $enabled_templates = array_filter( $template_config, function( $config ) {
            return isset( $config['enabled'] ) && $config['enabled'];
        } );

        if ( empty( $enabled_templates ) ) {
            return new \WP_Error( 'no_templates', __( 'No templates selected.', 'product-data-generator' ) );
        }

        // Query products
        $query_args['fields'] = 'ids';
        $query_args['posts_per_page'] = -1;
        
        $product_ids = get_posts( $query_args );

        if ( empty( $product_ids ) ) {
            return new \WP_Error( 'no_products', __( 'No products found matching the query.', 'product-data-generator' ) );
        }

        // Calculate total generations accounting for skip logic
        $total_generations = 0;
        $preview_products = [];
        $preview_limit = 10;

        foreach ( $product_ids as $index => $product_id ) {
            $product_generations = [];
            $generation_meta = get_post_meta( $product_id, '_pdg_generations', true );
            
            if ( ! is_array( $generation_meta ) ) {
                $generation_meta = [];
            }

            foreach ( $enabled_templates as $template_id => $config ) {
                $skip_if_generated = isset( $config['skip_if_generated'] ) && $config['skip_if_generated'];
                
                // Check if this specific template should be skipped for this product
                if ( $skip_if_generated && isset( $generation_meta[ $template_id ] ) ) {
                    continue; // Skip this template for this product
                }

                $total_generations++;
                
                if ( $index < $preview_limit ) {
                    $product_generations[] = $template_id;
                }
            }

            // Add to preview
            if ( $index < $preview_limit ) {
                $product = wc_get_product( $product_id );
                
                $preview_products[] = [
                    'id'         => $product_id,
                    'name'       => $product ? $product->get_name() : __( 'Unknown Product', 'product-data-generator' ),
                    'templates'  => $product_generations,
                    'edit_link'  => get_edit_post_link( $product_id ),
                ];
            }
        }

        // Cache preview for later use
        $preview_data = [
            'product_count'      => count( $product_ids ),
            'template_count'     => count( $enabled_templates ),
            'total_generations'  => $total_generations,
            'preview_products'   => $preview_products,
            'generated_at'       => current_time( 'timestamp' ),
        ];

        update_post_meta( $queue_id, '_pdg_preview_cache', $preview_data );

        return $preview_data;
    }

    /**
     * Start queue processing
     *
     * @param int $queue_id Queue post ID
     * @return true|\WP_Error True on success, WP_Error on failure
     */
    public static function start_queue( $queue_id ) {
        $queue = get_post( $queue_id );
        
        if ( ! $queue || $queue->post_type !== 'pdg_queue' ) {
            return new \WP_Error( 'invalid_queue', __( 'Invalid queue ID.', 'product-data-generator' ) );
        }

        // Check for existing processing queue (excluding this one)
        $processing = get_posts( [
            'post_type'      => 'pdg_queue',
            'post_status'    => 'pdg_processing',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post__not_in'   => [ $queue_id ],
        ] );

        if ( ! empty( $processing ) ) {
            return new \WP_Error( 'queue_locked', __( 'Another queue is already processing.', 'product-data-generator' ) );
        }

        // Get and validate configuration
        $query_args_str = get_post_meta( $queue_id, '_pdg_query_args', true );
        $query_args = self::parse_query_args( $query_args_str );
        
        if ( is_wp_error( $query_args ) ) {
            return $query_args;
        }

        $template_config = get_post_meta( $queue_id, '_pdg_template_config', true );
        
        if ( ! is_array( $template_config ) ) {
            return new \WP_Error( 'no_templates', __( 'No template configuration found.', 'product-data-generator' ) );
        }

        // Filter enabled templates
        $enabled_templates = array_filter( $template_config, function( $config ) {
            return isset( $config['enabled'] ) && $config['enabled'];
        } );

        if ( empty( $enabled_templates ) ) {
            return new \WP_Error( 'no_templates', __( 'No templates selected.', 'product-data-generator' ) );
        }

        // Get products
        $query_args['fields'] = 'ids';
        $query_args['posts_per_page'] = -1;
        
        $product_ids = get_posts( $query_args );

        if ( empty( $product_ids ) ) {
            return new \WP_Error( 'no_products', __( 'No products found matching the query.', 'product-data-generator' ) );
        }

        // Calculate total work items accounting for skip logic
        $work_items = [];
        
        foreach ( $product_ids as $product_id ) {
            $generation_meta = get_post_meta( $product_id, '_pdg_generations', true );
            
            if ( ! is_array( $generation_meta ) ) {
                $generation_meta = [];
            }

            foreach ( $enabled_templates as $template_id => $config ) {
                $skip_if_generated = isset( $config['skip_if_generated'] ) && $config['skip_if_generated'];
                
                // Check if this specific template should be skipped for this product
                if ( $skip_if_generated && isset( $generation_meta[ $template_id ] ) ) {
                    continue;
                }

                $work_items[] = [
                    'product_id'  => $product_id,
                    'template_id' => $template_id,
                ];
            }
        }

        if ( empty( $work_items ) ) {
            return new \WP_Error( 'no_work', __( 'No work to do. All selected templates may already be generated.', 'product-data-generator' ) );
        }

        // Initialize progress
        $progress = [
            'total'              => count( $work_items ),
            'completed'          => 0,
            'failed'             => 0,
            'started_at'         => current_time( 'timestamp' ),
            'current_product_id' => null,
        ];

        update_post_meta( $queue_id, '_pdg_progress', $progress );
        update_post_meta( $queue_id, '_pdg_work_items', $work_items );
        update_post_meta( $queue_id, '_pdg_results', [] );

        // Update queue status
        wp_update_post( [
            'ID'          => $queue_id,
            'post_status' => 'pdg_processing',
        ] );

        // Schedule first batch
        self::schedule_next_batch( $queue_id );

        do_action( 'product_data_generator_queue_started', $queue_id, count( $work_items ) );

        return true;
    }

    /**
     * Pause queue processing
     *
     * @param int $queue_id Queue post ID
     * @return true|\WP_Error True on success, WP_Error on failure
     */
    public static function pause_queue( $queue_id ) {
        $queue = get_post( $queue_id );
        
        if ( ! $queue || $queue->post_type !== 'pdg_queue' ) {
            return new \WP_Error( 'invalid_queue', __( 'Invalid queue ID.', 'product-data-generator' ) );
        }

        if ( $queue->post_status !== 'pdg_processing' ) {
            return new \WP_Error( 'not_processing', __( 'Queue is not currently processing.', 'product-data-generator' ) );
        }

        // Cancel all pending actions for this queue
        as_unschedule_all_actions( self::HOOK_PROCESS_BATCH, [ $queue_id ] );
        as_unschedule_all_actions( self::HOOK_PROCESS_PRODUCT, null, 'pdg_queue_' . $queue_id );

        // Update status
        wp_update_post( [
            'ID'          => $queue_id,
            'post_status' => 'pdg_paused',
        ] );

        do_action( 'product_data_generator_queue_paused', $queue_id );

        return true;
    }

    /**
     * Schedule next batch of work
     *
     * @param int $queue_id Queue post ID
     */
    private static function schedule_next_batch( $queue_id ) {
        $batch_size = get_post_meta( $queue_id, '_pdg_batch_size', true );
        $delay = get_post_meta( $queue_id, '_pdg_delay', true );
        
        if ( empty( $batch_size ) ) {
            $batch_size = 5;
        }
        if ( empty( $delay ) ) {
            $delay = 2;
        }

        // Schedule batch processing
        as_schedule_single_action(
            time() + $delay,
            self::HOOK_PROCESS_BATCH,
            [ $queue_id ],
            'pdg_queue_' . $queue_id
        );
    }

    /**
     * Process a batch of products
     *
     * @param int $queue_id Queue post ID
     */
    public static function process_batch( $queue_id ) {
        $queue = get_post( $queue_id );
        
        if ( ! $queue || $queue->post_status !== 'pdg_processing' ) {
            return; // Queue was paused or deleted
        }

        $work_items = get_post_meta( $queue_id, '_pdg_work_items', true );
        $progress = get_post_meta( $queue_id, '_pdg_progress', true );
        $batch_size = get_post_meta( $queue_id, '_pdg_batch_size', true );
        $template_config = get_post_meta( $queue_id, '_pdg_template_config', true );

        if ( ! is_array( $work_items ) || empty( $work_items ) ) {
            self::complete_queue( $queue_id );
            return;
        }

        if ( empty( $batch_size ) ) {
            $batch_size = 5;
        }

        // Get next batch
        $batch = array_slice( $work_items, 0, $batch_size );
        
        // Schedule individual product processing
        foreach ( $batch as $index => $item ) {
            $product_id = $item['product_id'];
            $template_id = $item['template_id'];
            $config = isset( $template_config[ $template_id ] ) ? $template_config[ $template_id ] : [];

            // Schedule with slight delay between items to avoid overwhelming the system
            as_schedule_single_action(
                time() + ( $index * 1 ), // 1 second between items
                self::HOOK_PROCESS_PRODUCT,
                [ $queue_id, $product_id, $template_id ],
                'pdg_queue_' . $queue_id
            );
        }

        // Remove processed items from work queue
        $remaining = array_slice( $work_items, $batch_size );
        update_post_meta( $queue_id, '_pdg_work_items', $remaining );

        // Schedule next batch if there's more work
        if ( ! empty( $remaining ) ) {
            self::schedule_next_batch( $queue_id );
        }
    }

    /**
     * Process a single product/template combination
     *
     * @param int $queue_id Queue post ID
     * @param int $product_id Product ID
     * @param string $template_id Template ID
     */
    public static function process_product( $queue_id, $product_id, $template_id ) {
        $queue = get_post( $queue_id );
        
        if ( ! $queue || $queue->post_status !== 'pdg_processing' ) {
            return; // Queue was paused or deleted
        }

        // Update current product in progress
        $progress = get_post_meta( $queue_id, '_pdg_progress', true );
        $progress['current_product_id'] = $product_id;
        update_post_meta( $queue_id, '_pdg_progress', $progress );

        // Get template config
        $template_config = get_post_meta( $queue_id, '_pdg_template_config', true );
        $config = isset( $template_config[ $template_id ] ) ? $template_config[ $template_id ] : [];

        // Get the template
        $template = Template_Registry::get( $template_id );
        
        if ( ! $template ) {
            self::log_result( $queue_id, $product_id, $template_id, false, __( 'Template not found', 'product-data-generator' ) );
            return;
        }

        // Get the product
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            self::log_result( $queue_id, $product_id, $template_id, false, __( 'Product not found', 'product-data-generator' ) );
            return;
        }

        // Double-check skip logic (in case product was modified during queue)
        if ( isset( $config['skip_if_generated'] ) && $config['skip_if_generated'] ) {
            $generation_meta = get_post_meta( $product_id, '_pdg_generations', true );
            
            if ( is_array( $generation_meta ) && isset( $generation_meta[ $template_id ] ) ) {
                self::log_result( $queue_id, $product_id, $template_id, true, __( 'Skipped (already generated)', 'product-data-generator' ), true );
                return;
            }
        }

        try {
            // Set product on template
            $template->set_product( $product );

            // Get messages
            $messages = $template->get_messages();

            // Use WordPress AI Client
            if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
                self::log_result( $queue_id, $product_id, $template_id, false, __( 'WordPress AI Client not available', 'product-data-generator' ) );
                return;
            }

            $prompt_builder = \WordPress\AI_Client\AI_Client::prompt_with_wp_error();

            // Set system instruction
            if ( ! empty( $messages[0]['content'] ) && $messages[0]['role'] === 'system' ) {
                $prompt_builder->using_system_instruction( $messages[0]['content'] );
            }

            // Add user message
            if ( ! empty( $messages[1]['content'] ) && $messages[1]['role'] === 'user' ) {
                $prompt_builder->with_text( $messages[1]['content'] );
            }

            // Get temperature from config
            $temperature = isset( $config['temperature'] ) ? floatval( $config['temperature'] ) : 0.7;
            $temperature = max( 0, min( 2, $temperature ) );

            // Set AI parameters
            $prompt_builder->using_temperature( $temperature );
            $prompt_builder->using_max_tokens( 2000 );

            // Generate content
            $result = $prompt_builder->generate_text();

            if ( is_wp_error( $result ) ) {
                self::log_result( $queue_id, $product_id, $template_id, false, $result->get_error_message() );
                return;
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

            self::log_result( $queue_id, $product_id, $template_id, true, __( 'Generated successfully', 'product-data-generator' ) );

        } catch ( \Exception $e ) {
            self::log_result( $queue_id, $product_id, $template_id, false, $e->getMessage() );
        }
    }

    /**
     * Log result for a product/template
     *
     * @param int $queue_id Queue post ID
     * @param int $product_id Product ID
     * @param string $template_id Template ID
     * @param bool $success Whether generation succeeded
     * @param string $message Result message
     * @param bool $skipped Whether this was skipped
     */
    private static function log_result( $queue_id, $product_id, $template_id, $success, $message, $skipped = false ) {
        $results = get_post_meta( $queue_id, '_pdg_results', true );
        
        if ( ! is_array( $results ) ) {
            $results = [];
        }

        $result_key = $product_id . '_' . $template_id;
        
        $results[ $result_key ] = [
            'product_id'  => $product_id,
            'template_id' => $template_id,
            'success'     => $success,
            'skipped'     => $skipped,
            'message'     => $message,
            'timestamp'   => current_time( 'timestamp' ),
        ];

        update_post_meta( $queue_id, '_pdg_results', $results );

        // Update progress
        $progress = get_post_meta( $queue_id, '_pdg_progress', true );
        
        if ( $success ) {
            $progress['completed'] = isset( $progress['completed'] ) ? $progress['completed'] + 1 : 1;
        } else {
            $progress['failed'] = isset( $progress['failed'] ) ? $progress['failed'] + 1 : 1;
        }

        update_post_meta( $queue_id, '_pdg_progress', $progress );

        // Check if complete
        $total_processed = $progress['completed'] + $progress['failed'];
        
        if ( $total_processed >= $progress['total'] ) {
            self::complete_queue( $queue_id );
        }

        do_action( 'product_data_generator_item_processed', $queue_id, $product_id, $template_id, $success, $message );
    }

    /**
     * Complete queue processing
     *
     * @param int $queue_id Queue post ID
     */
    private static function complete_queue( $queue_id ) {
        $progress = get_post_meta( $queue_id, '_pdg_progress', true );
        $progress['completed_at'] = current_time( 'timestamp' );
        update_post_meta( $queue_id, '_pdg_progress', $progress );

        // Determine final status based on results
        $failed = isset( $progress['failed'] ) ? $progress['failed'] : 0;
        $status = $failed > 0 ? 'pdg_completed' : 'pdg_completed'; // Could use different status for partial failures

        wp_update_post( [
            'ID'          => $queue_id,
            'post_status' => $status,
        ] );

        do_action( 'product_data_generator_queue_completed', $queue_id, $progress );
    }

    /**
     * Parse query args string into array
     *
     * @param string $query_args_str Query args as string
     * @return array|\WP_Error Parsed args or error
     */
    private static function parse_query_args( $query_args_str ) {
        if ( empty( $query_args_str ) ) {
            return new \WP_Error( 'empty_query', __( 'Query arguments are empty.', 'product-data-generator' ) );
        }

        // Try to evaluate the array
        $query_args = null;
        
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        @eval( '$query_args = ' . $query_args_str . ';' );

        if ( ! is_array( $query_args ) ) {
            return new \WP_Error( 'invalid_query', __( 'Invalid query arguments. Must be a valid PHP array.', 'product-data-generator' ) );
        }

        // Ensure it's a product query
        $query_args['post_type'] = 'product';

        // Ensure we get some results
        if ( ! isset( $query_args['posts_per_page'] ) ) {
            $query_args['posts_per_page'] = -1;
        }

        if ( ! isset( $query_args['post_status'] ) ) {
            $query_args['post_status'] = 'publish';
        }

        return $query_args;
    }
}
