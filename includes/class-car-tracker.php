<?php
defined( 'ABSPATH' ) || exit;

class CAR_Tracker {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Capture email at checkout
        add_action( 'wp_ajax_car_capture_email',        [ $this, 'ajax_capture_email' ] );
        add_action( 'wp_ajax_nopriv_car_capture_email', [ $this, 'ajax_capture_email' ] );

        // Capture on user login/register
        add_action( 'wp_login',          [ $this, 'on_user_login' ], 10, 2 );
        add_action( 'user_register',     [ $this, 'on_user_register' ], 10, 1 );

        // Mark as recovered ONLY after successful order placement (thankyou page)
        add_action( 'woocommerce_thankyou', [ $this, 'on_order_placed' ], 10, 1 );

        // Cart update hooks
        add_action( 'woocommerce_cart_updated',          [ $this, 'save_cart_snapshot' ] );
        add_action( 'woocommerce_add_to_cart',           [ $this, 'save_cart_snapshot' ] );
        add_action( 'woocommerce_cart_item_removed',     [ $this, 'save_cart_snapshot' ] );

        // Enqueue checkout JS
        add_action( 'woocommerce_after_checkout_form', [ $this, 'enqueue_checkout_script' ] );

        // Order completed = recovered (fallback/confirmation)
        add_action( 'woocommerce_order_status_completed',  [ $this, 'on_order_completed' ] );
        add_action( 'woocommerce_order_status_processing', [ $this, 'on_order_completed' ] );
    }

    public function enqueue_checkout_script() {
        if ( ! is_checkout() ) return;

        wp_enqueue_script(
            'car-checkout',
            CAR_PRO_URL . 'assets/js/checkout.js',
            [ 'jquery' ],
            CAR_PRO_VERSION,
            true
        );

        wp_localize_script( 'car-checkout', 'carCheckout', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'car_capture_email' ),
            'gdpr'      => get_option( 'car_gdpr_enabled', 'no' ),
        ] );
    }

    public function ajax_capture_email() {
        check_ajax_referer( 'car_capture_email', 'nonce' );

        $email      = sanitize_email( wp_unslash( isset( $_POST['email'] )      ? $_POST['email']      : '' ) );
        $first_name = sanitize_text_field( wp_unslash( isset( $_POST['first_name'] ) ? $_POST['first_name'] : '' ) );
        $last_name  = sanitize_text_field( wp_unslash( isset( $_POST['last_name'] )  ? $_POST['last_name']  : '' ) );
        $phone      = sanitize_text_field( wp_unslash( isset( $_POST['phone'] )      ? $_POST['phone']      : '' ) );
        $gdpr       = isset( $_POST['gdpr'] ) ? 1 : 0;

        if ( ! is_email( $email ) ) {
            wp_send_json_error( 'Invalid email' );
        }

        // Check GDPR
        if ( get_option( 'car_gdpr_enabled', 'no' ) === 'yes' && ! $gdpr ) {
            wp_send_json_success( 'gdpr_not_accepted' );
            return;
        }

        $this->capture_cart( $email, $first_name, $last_name, $phone, $gdpr );
        wp_send_json_success( 'captured' );
    }

    public function capture_cart( $email, $first_name = '', $last_name = '', $phone = '', $gdpr = 0 ) {
        if ( ! $email ) return;

        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) return;

        $session_id = WC()->session ? WC()->session->get_customer_id() : md5( $email . time() );

        $cart_data = [
            'session_id'   => $session_id,
            'user_id'      => get_current_user_id(),
            'email'        => $email,
            'phone'        => $phone,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'cart_contents'=> maybe_serialize( $cart->get_cart() ),
            'cart_total'   => $cart->get_total( 'edit' ),
            'currency'     => get_woocommerce_currency(),
            'checkout_url' => wc_get_checkout_url(),
            'status'       => 'pending',
            'gdpr_consent' => $gdpr,
            'ip_address'   => $this->get_ip(),
            'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
        ];

        CAR_DB::save_cart( $cart_data );
    }

    public function save_cart_snapshot() {
        if ( is_admin() ) return;

        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) return;

        $session_id = WC()->session ? WC()->session->get_customer_id() : '';
        if ( ! $session_id ) return;

        $existing = CAR_DB::get_cart_by_session( $session_id );

        if ( $existing && in_array( $existing->status, [ 'pending', 'abandoned' ] ) ) {
            CAR_DB::update_cart( $existing->id, [
                'cart_contents' => maybe_serialize( $cart->get_cart() ),
                'cart_total'    => $cart->get_total( 'edit' ),
            ] );
        }
    }

    public function on_user_login( $user_login, $user ) {
        $email = $user->user_email;
        $this->capture_cart(
            $email,
            $user->first_name,
            $user->last_name,
            get_user_meta( $user->ID, 'billing_phone', true ),
            1
        );
    }

    public function on_user_register( $user_id ) {
        $user  = get_userdata( $user_id );
        $email = $user->user_email;
        $this->capture_cart( $email, $user->first_name, $user->last_name, '', 1 );
    }

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
            CAR_DB::update_cart( $row->id, [
                'status'       => 'abandoned',
                'abandoned_at' => current_time( 'mysql' ),
            ] );

            // Fire notification
            do_action( 'car_cart_abandoned', $row->id );
        }
    }

    public function on_order_placed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $email      = $order->get_billing_email();
        $session_id = WC()->session ? WC()->session->get_customer_id() : '';

        $cart = $session_id ? CAR_DB::get_cart_by_session( $session_id ) : null;

        if ( ! $cart ) {
            $cart = CAR_DB::get_cart_by_email( $email );
        }

        if ( $cart ) {
            CAR_DB::mark_recovered( $cart->id, $order_id );
            do_action( 'car_cart_recovered', $cart->id, $order_id );
        }
    }

    public function on_order_completed( $order_id ) {
        $this->on_order_placed( $order_id );
    }

    /**
     * Safely retrieve the user's IP address.
     * Handles comma-separated X-Forwarded-For headers and prevents DB truncation.
     */
    private function get_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            // X-Forwarded-For can be a comma-separated list (client, proxy1, proxy2). Take the first one.
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip  = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Ensure it doesn't exceed the DB column length (VARCHAR 50)
        return substr( $ip, 0, 50 );
    }
}