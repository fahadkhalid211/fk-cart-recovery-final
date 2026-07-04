<?php
defined( 'ABSPATH' ) || exit;

class CAR_Notifications {

    public function __construct() {
        add_action( 'car_cart_abandoned', [ $this, 'notify_abandoned' ] );
        add_action( 'car_cart_recovered', [ $this, 'notify_recovered' ], 10, 2 );
    }

    public function notify_abandoned( $cart_id ) {
        if ( 'yes' !== get_option( 'car_admin_notify_email', 'yes' ) ) {
            return;
        }
        $cart = CAR_DB::get_cart( $cart_id );
        if ( ! $cart ) {
            return;
        }
        $to    = get_option( 'car_admin_notify_address', get_option( 'admin_email' ) );
        $total = html_entity_decode( wp_strip_all_tags( wc_price( $cart->cart_total ) ) );

        /* translators: 1: site name, 2: cart total amount */
        $subject = sprintf( __( '[%1$s] New Cart Abandoned - %2$s', 'fk-cart-recovery' ), get_bloginfo( 'name' ), $total );

        /* translators: 1: customer full name, 2: customer email, 3: cart total, 4: abandonment timestamp, 5: admin dashboard URL */
        /* translators: 1: customer full name, 2: customer email, 3: cart total, 4: abandonment timestamp, 5: admin dashboard URL */
        $body = sprintf(
            __( "A cart has been abandoned.\n\nCustomer: %1\$s\nEmail: %2\$s\nTotal: %3\$s\nTime: %4\$s\n\nView in dashboard: %5\$s", 'fk-cart-recovery' ),
            $cart->first_name . ' ' . $cart->last_name,
            $cart->email,
            $total,
            $cart->abandoned_at,
            admin_url( 'admin.php?page=fk-cart-recovery' )
        );

        wp_mail( $to, $subject, $body );
    }

    public function notify_recovered( $cart_id, $order_id ) {
        if ( 'yes' !== get_option( 'car_admin_notify_email', 'yes' ) ) {
            return;
        }
        $cart = CAR_DB::get_cart( $cart_id );
        if ( ! $cart ) {
            return;
        }
        $to    = get_option( 'car_admin_notify_address', get_option( 'admin_email' ) );
        $total = html_entity_decode( wp_strip_all_tags( wc_price( $cart->cart_total ) ) );

        /* translators: 1: site name, 2: WooCommerce order ID */
        $subject = sprintf( __( '[%1$s] Cart Recovered - Order #%2$s', 'fk-cart-recovery' ), get_bloginfo( 'name' ), absint( $order_id ) );

        /* translators: 1: customer full name, 2: customer email, 3: order ID, 4: cart total, 5: order edit URL */
        $body = sprintf(
            __( "A cart has been recovered!\n\nCustomer: %1\$s\nEmail: %2\$s\nOrder: #%3\$s\nTotal: %4\$s\n\nView order: %5\$s", 'fk-cart-recovery' ),
            $cart->first_name . ' ' . $cart->last_name,
            $cart->email,
            absint( $order_id ),
            $total,
            admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' )
      
        );

        wp_mail( $to, $subject, $body );
    }
}

