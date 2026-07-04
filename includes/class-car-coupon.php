<?php
defined( 'ABSPATH' ) || exit;

class CAR_Coupon {

    public static function get_or_create( $cart_id, $campaign ) {
        $existing = CAR_DB::get_coupon( $cart_id, $campaign->id );

        if ( $existing && $existing->coupon_id ) {
            $wc_coupon   = new WC_Coupon( $existing->coupon_id );
            $not_expired = ! $existing->expires_at || strtotime( $existing->expires_at ) > time();

            if ( $wc_coupon->get_id() && $wc_coupon->get_usage_count() < 1 && $not_expired ) {
                return $existing->coupon_code;
            }
        }

        $code      = self::generate_code();
        $expiry    = gmdate( 'Y-m-d', strtotime( '+' . absint( $campaign->coupon_expiry_days ) . ' days' ) );
        $coupon_id = self::create_wc_coupon( $code, $campaign->coupon_type, $campaign->coupon_amount, $expiry );

        CAR_DB::save_coupon( [
            'cart_id'     => $cart_id,
            'campaign_id' => $campaign->id,
            'coupon_code' => $code,
            'coupon_id'   => $coupon_id,
            'expires_at'  => $expiry . ' 23:59:59',
        ] );

        return $code;
    }

    private static function generate_code() {
        return strtoupper( 'CART-' . substr( wp_generate_password( 8, false ), 0, 8 ) );
    }

    private static function create_wc_coupon( $code, $type, $amount, $expiry ) {
        $coupon = new WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( $type === 'percent' ? 'percent' : 'fixed_cart' );
        $coupon->set_amount( $amount );
        $coupon->set_date_expires( strtotime( $expiry . ' 23:59:59' ) );
        $coupon->set_usage_limit( 1 );
        $coupon->set_individual_use( true );
        $coupon->save();
        return $coupon->get_id();
    }
}