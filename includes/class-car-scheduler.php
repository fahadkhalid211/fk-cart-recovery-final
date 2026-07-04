<?php
defined( 'ABSPATH' ) || exit;

/**
 * Scheduler – fired by WP-Cron every 5 minutes.
 *
 * Responsibilities:
 *  1. Mark pending carts as abandoned (after cutoff).
 *  2. Dispatch campaigns to abandoned carts.
 *
 * The cron interval is registered in the main plugin class (CAR_Pro) on the
 * 'cron_schedules' filter so it is always available before WP evaluates
 * whether the event needs scheduling.
 */
class CAR_Scheduler {

    public function __construct() {
        // Process campaigns AND mark abandoned carts – both on the same cron hook.
        add_action( 'car_pro_process_scheduler', [ $this, 'mark_abandoned_carts' ], 5 );
        add_action( 'car_pro_process_scheduler', [ $this, 'process' ], 10 );
    }

    // ── Step 1: promote pending → abandoned ─────────────────────────────────
    public function mark_abandoned_carts() {
        global $wpdb;

        $cutoff = absint( get_option( 'car_cutoff_time', 60 ) );
        $time   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$cutoff} minutes" ) );

        // LIMIT 100 prevents PHP timeouts on stores with massive backlogs. 
        // The cron runs every 5 mins, so it will safely catch up.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}car_abandoned_carts WHERE status = 'pending' AND created_at <= %s LIMIT 100",
            $time
        ) );

        foreach ( $rows as $row ) {
            CAR_DB::update_cart( (int) $row->id, [
                'status'       => 'abandoned',
                'abandoned_at' => current_time( 'mysql' ),
            ] );

            do_action( 'car_cart_abandoned', (int) $row->id );
        }
    }

    // ── Step 2: send campaigns ───────────────────────────────────────────────
    public function process() {
        if ( get_option( 'car_enabled', 'yes' ) !== 'yes' ) {
            return;
        }

        $campaigns = CAR_DB::get_campaigns( [ 'status' => 'active' ] );
        if ( empty( $campaigns ) ) {
            return;
        }

        // Process up to 100 abandoned carts per run to avoid timeouts.
        $carts = CAR_DB::get_carts( [ 'status' => 'abandoned', 'per_page' => 100 ] );
        if ( empty( $carts ) ) {
            return;
        }

        // Use time() instead of deprecated current_time('timestamp')
        $now = time(); 

        foreach ( $carts as $cart ) {
            // Need at least an email or phone to send anything.
            if ( empty( $cart->email ) && empty( $cart->phone ) ) {
                continue;
            }

            $abandoned_time = strtotime( $cart->abandoned_at );

            foreach ( $campaigns as $campaign ) {
                $send_at = $abandoned_time + ( (int) $campaign->send_after_minutes * MINUTE_IN_SECONDS );

                // Not yet time to send.
                if ( $now < $send_at ) {
                    continue;
                }

                // Already sent this campaign for this cart.
                if ( CAR_DB::log_already_sent( (int) $cart->id, (int) $campaign->id ) ) {
                    continue;
                }

                // Check targeting rules.
                if ( ! CAR_Rule_Engine::should_send( $campaign, $cart ) ) {
                    continue;
                }

                $this->dispatch( $campaign, $cart );
            }
        }
    }

    // ── Dispatch by channel ──────────────────────────────────────────────────
    private function dispatch( $campaign, $cart ) {
        $channel = $campaign->channel ?? 'email';

        switch ( $channel ) {
            case 'email':
                if ( ! empty( $cart->email ) ) {
                    CAR_Email_Handler::send( $cart, $campaign );
                }
                break;

            case 'whatsapp':
                if ( get_option( 'car_whatsapp_enabled', 'no' ) === 'yes' && ! empty( $cart->phone ) ) {
                    CAR_WhatsApp::send( $cart, $campaign );
                }
                break;

            case 'sms':
                if ( get_option( 'car_sms_enabled', 'no' ) === 'yes' && ! empty( $cart->phone ) ) {
                    CAR_SMS::send( $cart, $campaign );
                }
                break;

            case 'telegram':
                if ( get_option( 'car_telegram_enabled', 'no' ) === 'yes' ) {
                    CAR_Telegram::send( $cart, $campaign );
                }
                break;
        }

        do_action( 'car_message_dispatched', $campaign, $cart );
    }
}