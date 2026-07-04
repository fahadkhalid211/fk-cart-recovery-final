<?php
defined( 'ABSPATH' ) || exit;

class CAR_Recovery_Link {

    public function __construct() {
        // 'init' only for non-cart actions (unsubscribe, tracking pixel).
        // Cart restore MUST run on 'wp_loaded' — WC cart/session not ready before that.
        add_action( 'wp_loaded', [ $this, 'process_recovery_request' ], 20 );
        add_action( 'init',      [ $this, 'handle_unsubscribe' ] );
        add_action( 'init',      [ $this, 'handle_tracking' ] );
    }

    // ── URL generators ───────────────────────────────────────────────────────
    public static function generate( $cart_id ) {
        $token = wp_generate_password( 32, false );
        set_transient( 'car_recovery_' . $token, $cart_id, 7 * DAY_IN_SECONDS );
        return add_query_arg( [ 'car_recover' => $token ], home_url( '/' ) );
    }

    public static function generate_unsubscribe( $email ) {
        $token = base64_encode( $email . ':' . wp_hash( $email . 'car_unsub' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return add_query_arg( [ 'car_unsub' => $token ], home_url( '/' ) );
    }

    // ── Cart recovery (wp_loaded = WC session fully ready) ───────────────────
    public function process_recovery_request() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['car_recover'] ) ) {
            return;
        }

        $token   = sanitize_text_field( wp_unslash( $_GET['car_recover'] ) );
        $cart_id = get_transient( 'car_recovery_' . $token );

        if ( ! $cart_id ) {
            wc_add_notice( esc_html__( 'This recovery link has expired.', 'fk-cart-recovery' ), 'error' );
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }

        $cart_row = CAR_DB::get_cart( (int) $cart_id );
        if ( ! $cart_row ) {
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }

        // Track click
        $log_token = isset( $_GET['car_tk'] ) ? sanitize_text_field( wp_unslash( $_GET['car_tk'] ) ) : '';
        if ( $log_token ) {
            $log = CAR_DB::get_log_by_token( $log_token );
            if ( $log ) {
                CAR_DB::update_log( $log->id, [
                    'clicked_at'  => current_time( 'mysql' ),
                    'click_count' => absint( $log->click_count ) + 1,
                ] );
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Temporarily remove tracker snapshot hooks so adding items during restore
        // doesn't trigger premature cart snapshots or circular WC::get_cart() calls.
        global $car_tracker_instance;
        if ( $car_tracker_instance instanceof CAR_Tracker ) {
            remove_action( 'woocommerce_add_to_cart',       [ $car_tracker_instance, 'save_cart_snapshot' ] );
            remove_action( 'woocommerce_cart_updated',      [ $car_tracker_instance, 'save_cart_snapshot' ] );
            remove_action( 'woocommerce_cart_item_removed', [ $car_tracker_instance, 'save_cart_snapshot' ] );
        }

        // Restore cart items
        WC()->cart->empty_cart();
        $items = maybe_unserialize( $cart_row->cart_contents );

        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $product_id   = absint( $item['product_id'] ?? 0 );
                $quantity     = max( 1, absint( $item['quantity'] ?? 1 ) );
                $variation_id = absint( $item['variation_id'] ?? 0 );
                $variation    = ( isset( $item['variation'] ) && is_array( $item['variation'] ) ) ? $item['variation'] : [];

                if ( $product_id ) {
                    WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
                }
            }
        }

        // Re-hook tracker
        if ( $car_tracker_instance instanceof CAR_Tracker ) {
            add_action( 'woocommerce_add_to_cart',       [ $car_tracker_instance, 'save_cart_snapshot' ] );
            add_action( 'woocommerce_cart_updated',      [ $car_tracker_instance, 'save_cart_snapshot' ] );
            add_action( 'woocommerce_cart_item_removed', [ $car_tracker_instance, 'save_cart_snapshot' ] );
        }

        // Apply coupon from URL
        if ( ! empty( $_GET['car_coupon'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            WC()->cart->apply_coupon( sanitize_text_field( wp_unslash( $_GET['car_coupon'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        delete_transient( 'car_recovery_' . $token );
        CAR_DB::update_cart( (int) $cart_id, [ 'status' => 'recovering' ] );

        wc_add_notice( esc_html__( 'Your cart has been restored! Complete your order below.', 'fk-cart-recovery' ), 'success' );
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    // ── Unsubscribe ──────────────────────────────────────────────────────────
    public function handle_unsubscribe() {
        if ( empty( $_GET['car_unsub'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $token = sanitize_text_field( wp_unslash( $_GET['car_unsub'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $parts = explode( ':', base64_decode( $token ), 2 );
        $email = sanitize_email( $parts[0] ?? '' );
        $hash  = $parts[1] ?? '';

        if ( ! $email || $hash !== wp_hash( $email . 'car_unsub' ) ) {
            wc_add_notice( esc_html__( 'Invalid unsubscribe link.', 'fk-cart-recovery' ), 'error' );
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }

        $user_id = email_exists( $email );
        if ( $user_id ) {
            update_user_meta( $user_id, 'car_unsubscribed', 1 );
        }

        update_option( 'car_unsub_' . md5( $email ), 1 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}car_email_logs SET unsubscribed = 1 WHERE recipient = %s",
            $email
        ) );

        // Redirect with a nice WooCommerce notice instead of using wp_die()
        wc_add_notice( esc_html__( 'You have been successfully unsubscribed. You will no longer receive cart recovery emails.', 'fk-cart-recovery' ), 'success' );
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    // ── Email open-tracking pixel ────────────────────────────────────────────
    public function handle_tracking() {
        if ( empty( $_GET['car_tk'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $token = sanitize_text_field( wp_unslash( $_GET['car_tk'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $log   = CAR_DB::get_log_by_token( $token );

        if ( $log ) {
            CAR_DB::update_log( $log->id, [
                'opened_at'  => $log->opened_at ?: current_time( 'mysql' ),
                'open_count' => absint( $log->open_count ) + 1,
            ] );
        }

        header( 'Content-Type: image/gif' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        
        // 1x1 transparent GIF
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.Security.EscapeOutput.OutputNotEscaped
        echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
        exit;
    }

    public static function is_unsubscribed( $email ) {
        return (bool) get_option( 'car_unsub_' . md5( $email ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5
    }
}