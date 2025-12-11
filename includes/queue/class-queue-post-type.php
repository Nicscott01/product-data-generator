<?php
/**
 * Queue Post Type Registration
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator\Queue;

defined( 'ABSPATH' ) || exit;

class Queue_Post_Type {

    /**
     * Initialize the queue post type
     */
    public static function init() {
        error_log( 'PDG Queue: Queue_Post_Type::init() called' );
        self::register_post_type();
        self::register_post_statuses();
        add_filter( 'display_post_states', [ __CLASS__, 'display_post_states' ], 10, 2 );
        add_action( 'admin_head-post.php', [ __CLASS__, 'customize_publish_box' ] );
        add_action( 'admin_head-post-new.php', [ __CLASS__, 'customize_publish_box' ] );
    }

    /**
     * Register the queue custom post type
     */
    public static function register_post_type() {
        error_log( 'PDG Queue: Registering pdg_queue post type' );
        
        $labels = [
            'name'                  => __( 'Bulk Generation Queues', 'product-data-generator' ),
            'singular_name'         => __( 'Queue', 'product-data-generator' ),
            'menu_name'             => __( 'Bulk Generation', 'product-data-generator' ),
            'add_new'               => __( 'Add New Queue', 'product-data-generator' ),
            'add_new_item'          => __( 'Add New Queue', 'product-data-generator' ),
            'edit_item'             => __( 'Edit Queue', 'product-data-generator' ),
            'new_item'              => __( 'New Queue', 'product-data-generator' ),
            'view_item'             => __( 'View Queue', 'product-data-generator' ),
            'search_items'          => __( 'Search Queues', 'product-data-generator' ),
            'not_found'             => __( 'No queues found', 'product-data-generator' ),
            'not_found_in_trash'    => __( 'No queues found in trash', 'product-data-generator' ),
            'all_items'             => __( 'All Queues', 'product-data-generator' ),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'edit.php?post_type=product',
            'menu_position'         => 56,
            'capability_type'       => 'post',
            'capabilities'          => [
                'create_posts'      => 'manage_woocommerce',
                'edit_post'         => 'manage_woocommerce',
                'read_post'         => 'manage_woocommerce',
                'delete_post'       => 'manage_woocommerce',
                'edit_posts'        => 'manage_woocommerce',
                'edit_others_posts' => 'manage_woocommerce',
                'delete_posts'      => 'manage_woocommerce',
            ],
            'hierarchical'          => false,
            'supports'              => [ 'title' ],
            'has_archive'           => false,
            'rewrite'               => false,
            'query_var'             => false,
            'can_export'            => true,
            'delete_with_user'      => false,
        ];

        register_post_type( 'pdg_queue', $args );
    }

    /**
     * Register custom post statuses for queue processing
     */
    public static function register_post_statuses() {
        register_post_status( 'pdg_processing', [
            'label'                     => _x( 'Processing', 'queue status', 'product-data-generator' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Processing <span class="count">(%s)</span>', 'Processing <span class="count">(%s)</span>', 'product-data-generator' ),
        ] );

        register_post_status( 'pdg_completed', [
            'label'                     => _x( 'Completed', 'queue status', 'product-data-generator' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>', 'product-data-generator' ),
        ] );

        register_post_status( 'pdg_failed', [
            'label'                     => _x( 'Failed', 'queue status', 'product-data-generator' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Failed <span class="count">(%s)</span>', 'Failed <span class="count">(%s)</span>', 'product-data-generator' ),
        ] );

        register_post_status( 'pdg_paused', [
            'label'                     => _x( 'Paused', 'queue status', 'product-data-generator' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Paused <span class="count">(%s)</span>', 'Paused <span class="count">(%s)</span>', 'product-data-generator' ),
        ] );
    }

    /**
     * Customize the publish box for queue post type
     */
    public static function customize_publish_box() {
        global $post;
        
        if ( ! $post || $post->post_type !== 'pdg_queue' ) {
            return;
        }
        
        ?>
        <style>
            /* Hide the Save Draft button */
            #save-post {
                display: none !important;
            }
            
            /* Make the publish button more prominent and clearer */
            #publishing-action .button-primary {
                font-weight: 600;
            }
        </style>
        <script>
            jQuery(document).ready(function($) {
                // Change "Publish" button text to "Create Queue" for new posts
                <?php if ( $post->post_status === 'auto-draft' || $post->post_status === 'draft' ) : ?>
                    $('#publish').val('<?php esc_attr_e( 'Create Queue', 'product-data-generator' ); ?>');
                <?php else : ?>
                    $('#publish').val('<?php esc_attr_e( 'Update Queue', 'product-data-generator' ); ?>');
                <?php endif; ?>
            });
        </script>
        <?php
    }

    /**
     * Display custom post states in list table
     *
     * @param array $post_states Post states
     * @param \WP_Post $post Post object
     * @return array
     */
    public static function display_post_states( $post_states, $post ) {
        if ( $post->post_type !== 'pdg_queue' ) {
            return $post_states;
        }

        $status_labels = [
            'pdg_processing' => __( 'Processing', 'product-data-generator' ),
            'pdg_completed'  => __( 'Completed', 'product-data-generator' ),
            'pdg_failed'     => __( 'Failed', 'product-data-generator' ),
            'pdg_paused'     => __( 'Paused', 'product-data-generator' ),
        ];

        if ( isset( $status_labels[ $post->post_status ] ) ) {
            $post_states[ $post->post_status ] = $status_labels[ $post->post_status ];
        }

        return $post_states;
    }
}
