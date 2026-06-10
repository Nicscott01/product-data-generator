<?php
/**
 * Queue Processor
 *
 * Handles background processing of bulk generation queues using Action Scheduler
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator\Queue;

use ProductDataGenerator\AI_Generator;
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
        
        // Only override posts_per_page if not already set
        if ( ! isset( $query_args['posts_per_page'] ) ) {
            $query_args['posts_per_page'] = -1;
        }
        
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

        if ( $queue->post_status === 'pdg_processing' ) {
            return new \WP_Error( 'queue_already_processing', __( 'This queue is already processing.', 'product-data-generator' ) );
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
        
        // Only override posts_per_page if not already set
        if ( ! isset( $query_args['posts_per_page'] ) ) {
            $query_args['posts_per_page'] = -1;
        }
        
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
            'skipped'            => 0,
            'started_at'         => current_time( 'timestamp' ),
            'current_product_id' => null,
        ];
        $run_token = wp_generate_password( 12, false, false );

        update_post_meta( $queue_id, '_pdg_progress', $progress );
        update_post_meta( $queue_id, '_pdg_work_items', $work_items );
        update_post_meta( $queue_id, '_pdg_product_states', self::build_product_states( $work_items ) );
        update_post_meta( $queue_id, '_pdg_run_token', $run_token );
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

        // Get queue task options
        $task_options = get_post_meta( $queue_id, '_pdg_task_options', true );
        if ( ! is_array( $task_options ) ) {
            $task_options = [
                'fetch_data'      => true,
                'replace_image'   => false,
                'generate_content' => true,
            ];
        }

        $product_state = self::get_product_state( $queue_id, $product_id );

        // Run pre-generation tasks (only once per product, not per template)
        // Use transient to track across multiple Action Scheduler executions
        $run_token = (string) get_post_meta( $queue_id, '_pdg_run_token', true );
        $processed_key = 'pdg_processed_' . md5( $queue_id . '|' . $product_id . '|' . $run_token );
        $processing_key = 'pdg_processing_' . md5( $queue_id . '|' . $product_id . '|' . $run_token );
        $already_processed = get_transient( $processed_key );
        
        if ( empty( $product_state['pre_tasks_ran'] ) && ! $already_processed ) {
            if ( ! self::acquire_product_pre_task_lock( $processing_key ) ) {
                self::reschedule_product_processing( $queue_id, $product_id, $template_id );
                return;
            }

            try {
                $pre_task_result = self::run_pre_generation_tasks( $product_id, $task_options, $queue_id );

                $product_state['pre_tasks_ran'] = true;
                $product_state['pre_task_failed'] = is_wp_error( $pre_task_result );
                $product_state['pre_task_message'] = is_wp_error( $pre_task_result ) ? $pre_task_result->get_error_message() : '';

                self::save_product_state( $queue_id, $product_id, $product_state );

                // Mark as processed for this queue (expires in 1 hour to handle paused/resumed queues)
                set_transient( $processed_key, true, HOUR_IN_SECONDS );
            } finally {
                self::release_product_pre_task_lock( $processing_key );
            }
        } elseif ( empty( $product_state['pre_tasks_ran'] ) ) {
            $product_state = self::get_product_state( $queue_id, $product_id );
        }

        if ( ! empty( $product_state['pre_task_failed'] ) ) {
            /* translators: %s: pre-processing error message */
            self::log_result( $queue_id, $product_id, $template_id, false, sprintf( __( 'Pre-processing failed: %s', 'product-data-generator' ), $product_state['pre_task_message'] ) );
            return;
        }

        // Skip AI generation if not requested
        if ( empty( $task_options['generate_content'] ) ) {
            self::log_result( $queue_id, $product_id, $template_id, true, __( 'Skipped (content generation disabled)', 'product-data-generator' ), true );
            return;
        }

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

            AI_Generator::apply_messages_to_prompt_builder( $prompt_builder, $messages );

            // Get temperature from config
            $temperature = isset( $config['temperature'] ) ? floatval( $config['temperature'] ) : 0.7;
            $temperature = max( 0, min( 2, $temperature ) );

            AI_Generator::apply_settings_to_prompt_builder(
                $prompt_builder,
                $temperature,
                2000,
                [
                    'source'      => 'queue',
                    'queue_id'    => $queue_id,
                    'template_id' => $template_id,
                    'product_id'  => $product_id,
                ]
            );

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
     * Acquire an atomic per-product pre-task lock.
     *
     * @param string $lock_key Lock key.
     * @return bool True when the lock was acquired.
     */
    private static function acquire_product_pre_task_lock( $lock_key ) {
        $option_name = self::get_product_pre_task_lock_option_name( $lock_key );
        $now = time();

        if ( add_option( $option_name, $now, '', 'no' ) ) {
            return true;
        }

        $locked_at = (int) get_option( $option_name, 0 );

        if ( $locked_at && ( $now - $locked_at ) > ( 10 * MINUTE_IN_SECONDS ) ) {
            delete_option( $option_name );
            return (bool) add_option( $option_name, $now, '', 'no' );
        }

        return false;
    }

    /**
     * Release a per-product pre-task lock.
     *
     * @param string $lock_key Lock key.
     */
    private static function release_product_pre_task_lock( $lock_key ) {
        delete_option( self::get_product_pre_task_lock_option_name( $lock_key ) );
    }

    /**
     * Get the option name used for a per-product pre-task lock.
     *
     * @param string $lock_key Lock key.
     * @return string
     */
    private static function get_product_pre_task_lock_option_name( $lock_key ) {
        return '_pdg_pre_task_lock_' . md5( $lock_key );
    }

    /**
     * Reschedule a product/template when another worker is still running that product's pre-tasks.
     *
     * @param int    $queue_id Queue post ID.
     * @param int    $product_id Product ID.
     * @param string $template_id Template ID.
     */
    private static function reschedule_product_processing( $queue_id, $product_id, $template_id ) {
        as_schedule_single_action(
            time() + 10,
            self::HOOK_PROCESS_PRODUCT,
            [ $queue_id, $product_id, $template_id ],
            'pdg_queue_' . $queue_id
        );
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
        
        // Update the processed counters for queue completion.
        if ( ! $skipped ) {
            if ( $success ) {
                $progress['completed'] = isset( $progress['completed'] ) ? $progress['completed'] + 1 : 1;
            } else {
                $progress['failed'] = isset( $progress['failed'] ) ? $progress['failed'] + 1 : 1;
            }
        } else {
            $progress['skipped'] = isset( $progress['skipped'] ) ? $progress['skipped'] + 1 : 1;
        }

        update_post_meta( $queue_id, '_pdg_progress', $progress );

        self::record_product_result( $queue_id, $product_id, $template_id, $success, $skipped, $message );

        if ( ! $success ) {
            self::log_failure_to_debug_log( $queue_id, $product_id, $template_id, $message );
        }

        // Check if complete.
        $total_processed = $progress['completed'] + $progress['failed'] + $progress['skipped'];
        
        if ( $total_processed >= $progress['total'] ) {
            self::complete_queue( $queue_id );
        }

        do_action( 'product_data_generator_item_processed', $queue_id, $product_id, $template_id, $success, $message );
    }

    /**
     * Log queue item failures to the WordPress debug log.
     *
     * @param int    $queue_id Queue post ID.
     * @param int    $product_id Product ID.
     * @param string $template_id Template ID.
     * @param string $message Failure message.
     */
    private static function log_failure_to_debug_log( $queue_id, $product_id, $template_id, $message ) {
        $queue = get_post( $queue_id );
        $product = wc_get_product( $product_id );

        error_log( sprintf(
            '[Product Data Generator] Queue item failed | Queue #%d - %s | Product #%d - %s | Template: %s | Message: %s',
            $queue_id,
            $queue ? $queue->post_title : 'Unknown Queue',
            $product_id,
            $product ? $product->get_name() : 'Unknown Product',
            $template_id,
            $message
        ) );
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
     * Build per-product state records for the queue.
     *
     * @param array $work_items Queue work items.
     * @return array
     */
    private static function build_product_states( $work_items ) {
        $product_states = [];

        foreach ( $work_items as $item ) {
            $product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
            $template_id = isset( $item['template_id'] ) ? (string) $item['template_id'] : '';

            if ( ! $product_id || '' === $template_id ) {
                continue;
            }

            if ( ! isset( $product_states[ $product_id ] ) ) {
                $product_states[ $product_id ] = self::create_product_state();
            }

            if ( ! in_array( $template_id, $product_states[ $product_id ]['expected_templates'], true ) ) {
                $product_states[ $product_id ]['expected_templates'][] = $template_id;
            }
        }

        return $product_states;
    }

    /**
     * Create a default product state record.
     *
     * @return array
     */
    private static function create_product_state() {
        return [
            'expected_templates'  => [],
            'template_results'    => [],
            'pre_tasks_ran'       => false,
            'pre_task_failed'     => false,
            'pre_task_message'    => '',
            'post_actions_applied' => false,
            'post_action_failed'  => false,
            'post_action_message' => '',
            'finalized'           => false,
        ];
    }

    /**
     * Get a product state record for a queue.
     *
     * @param int $queue_id Queue post ID.
     * @param int $product_id Product ID.
     * @return array
     */
    private static function get_product_state( $queue_id, $product_id ) {
        $states = get_post_meta( $queue_id, '_pdg_product_states', true );

        if ( ! is_array( $states ) ) {
            return self::create_product_state();
        }

        return isset( $states[ $product_id ] ) && is_array( $states[ $product_id ] )
            ? wp_parse_args( $states[ $product_id ], self::create_product_state() )
            : self::create_product_state();
    }

    /**
     * Save a product state record for a queue.
     *
     * @param int   $queue_id Queue post ID.
     * @param int   $product_id Product ID.
     * @param array $state Product state.
     */
    private static function save_product_state( $queue_id, $product_id, array $state ) {
        $states = get_post_meta( $queue_id, '_pdg_product_states', true );

        if ( ! is_array( $states ) ) {
            $states = [];
        }

        $states[ $product_id ] = $state;

        update_post_meta( $queue_id, '_pdg_product_states', $states );
    }

    /**
     * Run pre-generation tasks for a product.
     *
     * @param int   $product_id Product ID.
     * @param array $task_options Queue task options.
     * @param int   $queue_id Queue post ID.
     * @return true|\WP_Error
     */
    private static function run_pre_generation_tasks( $product_id, $task_options, $queue_id ) {
        try {
            if ( ! empty( $task_options['fetch_data'] ) ) {
                do_action( 'pdg_queue_fetch_data', $product_id, $task_options, $queue_id );
            }

            if ( ! empty( $task_options['replace_image'] ) ) {
                do_action( 'pdg_queue_replace_image', $product_id, $task_options, $queue_id );
            }
        } catch ( \Throwable $throwable ) {
            return new \WP_Error( 'pre_processing_failed', $throwable->getMessage() );
        }

        return true;
    }

    /**
     * Record a template result against the owning product.
     *
     * @param int    $queue_id Queue post ID.
     * @param int    $product_id Product ID.
     * @param string $template_id Template ID.
     * @param bool   $success Whether the template succeeded.
     * @param bool   $skipped Whether the template was skipped.
     * @param string $message Result message.
     */
    private static function record_product_result( $queue_id, $product_id, $template_id, $success, $skipped, $message ) {
        $state = self::get_product_state( $queue_id, $product_id );

        if ( ! in_array( $template_id, $state['expected_templates'], true ) ) {
            $state['expected_templates'][] = $template_id;
        }

        $state['template_results'][ $template_id ] = [
            'success' => (bool) $success,
            'skipped' => (bool) $skipped,
            'message' => (string) $message,
        ];

        self::save_product_state( $queue_id, $product_id, $state );
        self::maybe_finalize_product( $queue_id, $product_id );
    }

    /**
     * Finalize product processing once all expected templates are accounted for.
     *
     * @param int $queue_id Queue post ID.
     * @param int $product_id Product ID.
     */
    private static function maybe_finalize_product( $queue_id, $product_id ) {
        $state = self::get_product_state( $queue_id, $product_id );

        if ( ! empty( $state['finalized'] ) || empty( $state['expected_templates'] ) ) {
            return;
        }

        foreach ( $state['expected_templates'] as $template_id ) {
            if ( ! isset( $state['template_results'][ $template_id ] ) ) {
                return;
            }
        }

        $has_failure = ! empty( $state['pre_task_failed'] );
        $successful_items = 0;

        foreach ( $state['template_results'] as $result ) {
            if ( ! empty( $result['skipped'] ) ) {
                continue;
            }

            if ( empty( $result['success'] ) ) {
                $has_failure = true;
            } else {
                $successful_items++;
            }
        }

        if ( ! $has_failure && $successful_items > 0 ) {
            $post_action_result = self::apply_post_actions( $queue_id, $product_id );

            if ( is_wp_error( $post_action_result ) ) {
                $state['post_action_failed'] = true;
                $state['post_action_message'] = $post_action_result->get_error_message();
            } else {
                $state['post_actions_applied'] = true;
            }
        }

        $state['finalized'] = true;
        self::save_product_state( $queue_id, $product_id, $state );
    }

    /**
     * Run configured post-queue actions for a product.
     *
     * @param int $queue_id Queue post ID.
     * @param int $product_id Product ID.
     * @return true|\WP_Error
     */
    private static function apply_post_actions( $queue_id, $product_id ) {
        $post_actions = get_post_meta( $queue_id, '_pdg_post_actions', true );

        if ( ! is_array( $post_actions ) ) {
            return true;
        }

        $change_status = isset( $post_actions['change_post_status'] ) && is_array( $post_actions['change_post_status'] )
            ? $post_actions['change_post_status']
            : [];

        if ( empty( $change_status['enabled'] ) ) {
            return true;
        }

        $target_status = ! empty( $change_status['status'] ) ? sanitize_key( $change_status['status'] ) : 'publish';

        if ( ! get_post_status_object( $target_status ) ) {
            return new \WP_Error( 'invalid_post_action_status', __( 'Configured post status is invalid.', 'product-data-generator' ) );
        }

        if ( get_post_status( $product_id ) === $target_status ) {
            return true;
        }

        $result = wp_update_post(
            [
                'ID'          => $product_id,
                'post_status' => $target_status,
            ],
            true
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        /**
         * Fires after post-queue actions have run successfully for a product.
         *
         * @param int $queue_id Queue post ID.
         * @param int $product_id Product ID.
         * @param array $post_actions Configured post actions.
         */
        do_action( 'product_data_generator_queue_post_actions_completed', $queue_id, $product_id, $post_actions );

        return true;
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
