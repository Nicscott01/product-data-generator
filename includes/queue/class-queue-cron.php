<?php
/**
 * Queue Cron Management
 *
 * @package ProductDataGenerator
 */

namespace ProductDataGenerator\Queue;

defined( 'ABSPATH' ) || exit;

class Queue_Cron {

    const HOOK_RUN_SCHEDULED_QUEUE = 'pdg_run_scheduled_queue';

    /**
     * Initialize cron support.
     */
    public static function init() {
        add_action( self::HOOK_RUN_SCHEDULED_QUEUE, [ __CLASS__, 'run_scheduled_queue' ], 10, 1 );
        add_action( 'save_post_pdg_queue', [ __CLASS__, 'sync_queue_schedule' ], 20, 2 );
        add_action( 'wp_trash_post', [ __CLASS__, 'cleanup_queue_schedule' ] );
        add_action( 'before_delete_post', [ __CLASS__, 'cleanup_queue_schedule' ] );
    }

    /**
     * Get cron settings for a queue.
     *
     * @param int $queue_id Queue post ID.
     * @return array
     */
    public static function get_settings( $queue_id ) {
        $settings = get_post_meta( $queue_id, '_pdg_cron_settings', true );

        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        return wp_parse_args(
            $settings,
            [
                'enabled'          => false,
                'interval_minutes' => 15,
            ]
        );
    }

    /**
     * Get the next scheduled timestamp for a queue.
     *
     * @param int $queue_id Queue post ID.
     * @return int
     */
    public static function get_next_run( $queue_id ) {
        if ( ! function_exists( 'as_next_scheduled_action' ) ) {
            return 0;
        }

        $next_run = as_next_scheduled_action(
            self::HOOK_RUN_SCHEDULED_QUEUE,
            [ $queue_id ],
            self::get_group( $queue_id )
        );

        return is_numeric( $next_run ) ? (int) $next_run : 0;
    }

    /**
     * Sync a queue's recurring schedule after it is saved.
     *
     * @param int      $post_id Queue post ID.
     * @param \WP_Post $post Queue post object.
     */
    public static function sync_queue_schedule( $post_id, $post ) {
        if ( ! $post || $post->post_type !== 'pdg_queue' ) {
            return;
        }

        if ( ! isset( $_POST['pdg_queue_nonce'] ) || ! wp_verify_nonce( $_POST['pdg_queue_nonce'], 'pdg_queue_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        self::unschedule_queue( $post_id );

        if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
            return;
        }

        $settings = self::get_settings( $post_id );

        if ( empty( $settings['enabled'] ) || in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
            return;
        }

        $interval_minutes = max( 1, (int) $settings['interval_minutes'] );
        $interval_seconds = $interval_minutes * MINUTE_IN_SECONDS;

        as_schedule_recurring_action(
            time() + $interval_seconds,
            $interval_seconds,
            self::HOOK_RUN_SCHEDULED_QUEUE,
            [ $post_id ],
            self::get_group( $post_id )
        );
    }

    /**
     * Execute a scheduled queue run.
     *
     * @param int $queue_id Queue post ID.
     */
    public static function run_scheduled_queue( $queue_id ) {
        $queue = get_post( $queue_id );

        if ( ! $queue || $queue->post_type !== 'pdg_queue' ) {
            return;
        }

        $settings = self::get_settings( $queue_id );

        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        update_post_meta( $queue_id, '_pdg_cron_last_run', current_time( 'timestamp' ) );

        $result = Queue_Processor::start_queue( $queue_id );

        if ( is_wp_error( $result ) ) {
            update_post_meta( $queue_id, '_pdg_cron_last_result', $result->get_error_message() );
            return;
        }

        update_post_meta( $queue_id, '_pdg_cron_last_result', __( 'Scheduled run started.', 'product-data-generator' ) );
    }

    /**
     * Clean up scheduled actions when a queue is removed.
     *
     * @param int $post_id Queue post ID.
     */
    public static function cleanup_queue_schedule( $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'pdg_queue' ) {
            return;
        }

        self::unschedule_queue( $post_id );
    }

    /**
     * Unschedule all recurring actions for a queue.
     *
     * @param int $queue_id Queue post ID.
     */
    private static function unschedule_queue( $queue_id ) {
        if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
            return;
        }

        as_unschedule_all_actions(
            self::HOOK_RUN_SCHEDULED_QUEUE,
            [ $queue_id ],
            self::get_group( $queue_id )
        );
    }

    /**
     * Get the Action Scheduler group for a queue.
     *
     * @param int $queue_id Queue post ID.
     * @return string
     */
    private static function get_group( $queue_id ) {
        return 'pdg_cron_queue_' . $queue_id;
    }
}
