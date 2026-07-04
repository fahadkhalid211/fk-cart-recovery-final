<?php
defined( 'ABSPATH' ) || exit;

class CAR_GDPR {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        if ( get_option( 'car_gdpr_enabled', 'no' ) === 'yes' ) {
            add_action( 'woocommerce_checkout_after_terms_and_conditions', [ $this, 'render_consent_checkbox' ] );
            add_action( 'woocommerce_checkout_order_processed', [ $this, 'save_consent' ], 10, 1 );
        }

        // GDPR data export/erasure
        add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers',   [ $this, 'register_eraser' ] );
    }

    public function render_consent_checkbox() {
        $label = get_option( 'car_gdpr_text', __( 'I agree to receive cart recovery emails.', 'fk-cart-recovery' ) );
        echo '<p class="form-row car-gdpr-row">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="car_gdpr_consent" type="checkbox" name="car_gdpr_consent" value="yes" />
                <span class="woocommerce-terms-and-conditions-checkbox-text">' . esc_html( $label ) . '</span>
            </label>
        </p>';
    }

    public function save_consent( $order_id ) {
        // WooCommerce verifies the checkout nonce before this hook fires.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! empty( $_POST['car_gdpr_consent'] ) ) {
            update_post_meta( $order_id, '_car_gdpr_consent', 'yes' );
        }
    }

    public function register_exporter( $exporters ) {
        $exporters['fk-cart-recovery'] = [
            'exporter_friendly_name' => __( 'Cart Abandonment Recovery', 'fk-cart-recovery' ),
            'callback'               => [ $this, 'export_data' ],
        ];
        return $exporters;
    }

    public function register_eraser( $erasers ) {
        $erasers['fk-cart-recovery'] = [
            'eraser_friendly_name' => __( 'Cart Abandonment Recovery', 'fk-cart-recovery' ),
            'callback'             => [ $this, 'erase_data' ],
        ];
        return $erasers;
    }

    public function export_data( $email_address, $page = 1 ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $carts = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}car_abandoned_carts WHERE email = %s", $email_address
        ) );
        $data = [];
        foreach ( $carts as $cart ) {
            $data[] = [
                'name'  => __( 'Abandoned Cart', 'fk-cart-recovery' ),
                'value' => sprintf( 'Cart on %s, total: %s, status: %s', $cart->abandoned_at, $cart->cart_total, $cart->status ),
            ];
        }
        return [ 'data' => [ [ 'group_id' => 'car_carts', 'group_label' => __( 'Abandoned Carts', 'fk-cart-recovery' ), 'item_id' => 'car_carts', 'data' => $data ] ], 'done' => true ];
    }

    public function erase_data( $email_address, $page = 1 ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'car_abandoned_carts',
            [ 'email' => '', 'phone' => '', 'first_name' => 'Deleted', 'last_name' => '' ],
            [ 'email' => $email_address ]
        );
        return [ 'items_removed' => true, 'items_retained' => false, 'messages' => [], 'done' => true ];
    }
}
