<?php
defined( 'ABSPATH' ) || exit;

class CAR_SMS {

    public static function send( $cart, $campaign ) {
        if ( get_option( 'car_sms_enabled', 'no' ) !== 'yes' ) return false;

        $phone = self::format_phone( $cart->phone );
        if ( ! $phone ) return false;

        $coupon_code = '';
        if ( $campaign->coupon_enabled ) {
            $coupon_code = CAR_Coupon::get_or_create( $cart->id, $campaign );
        }

        $recovery_url = CAR_Recovery_Link::generate( $cart->id );
        $message      = self::build_message( $cart, $campaign, $coupon_code, $recovery_url );
        $provider     = get_option( 'car_sms_provider', 'twilio' );

        $sent = false;
        switch ( $provider ) {
            case 'twilio':
                $sent = self::send_twilio( $phone, $message );
                break;
            case 'nexmo':
            case 'vonage':
                $sent = self::send_vonage( $phone, $message );
                break;
        }

        CAR_DB::insert_log( [
            'cart_id'     => $cart->id,
            'campaign_id' => $campaign->id,
            'channel'     => 'sms',
            'recipient'   => $phone,
            'subject'     => '',
            'status'      => $sent ? 'sent' : 'failed',
            'sent_at'     => $sent ? current_time( 'mysql' ) : null,
            'coupon_code' => $coupon_code,
        ] );

        return $sent;
    }

    private static function build_message( $cart, $campaign, $coupon, $recovery_url ) {
        // Always use plain-text price – never wc_price() raw output.
        $price = CAR_Email_Handler::format_price_plain( $cart->cart_total );
        $name  = trim( $cart->first_name ) ?: '';
        $site  = get_bloginfo( 'name' );

        if ( ! empty( $campaign->body ) ) {
            $body = CAR_Email_Handler::html_to_plain( $campaign->body );
            $placeholders = [
                '{customer_name}'    => $name ?: __( 'there', 'fk-cart-recovery' ),
                '{cart_total}'       => $price,
                '{cart_items}'       => '',
                '{cart_items_table}' => '',
                '{recovery_link}'    => $recovery_url,
                '{coupon_code}'      => $coupon ?: '',
                '{coupon_amount}'    => $campaign->coupon_amount ?? '',
                '{discount}'         => $campaign->coupon_amount ?? '',
                '{site_name}'        => $site,
                '{unsubscribe_link}' => '',
            ];
            $msg = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );
            $msg = CAR_Email_Handler::html_to_plain( $msg );
            if ( false === strpos( $msg, $recovery_url ) ) {
                $msg .= ' ' . $recovery_url;
            }
            return $msg;
        }

        $msg = ( $name ? $name . ', ' : '' ) . sprintf(
            /* translators: %1$s cart total %2$s site name */
            __( 'you left %1$s in your cart at %2$s.', 'fk-cart-recovery' ),
            $price,
            $site
        );
        if ( $coupon ) {
            /* translators: %s coupon code */
            $msg .= ' ' . sprintf( __( 'Use %s for a discount!', 'fk-cart-recovery' ), $coupon );
        }
        $msg .= ' ' . $recovery_url;
        return $msg;
    }

    private static function send_twilio( $phone, $message ) {
        $sid   = get_option( 'car_twilio_sid', '' );
        $token = get_option( 'car_twilio_token', '' );
        $from  = get_option( 'car_twilio_from', '' );
        if ( ! $sid || ! $token || ! $from ) return false;

        $response = wp_remote_post(
            "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
            [
                'headers' => [
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                    'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
                ],
                'body'    => [ 'To' => $phone, 'From' => $from, 'Body' => $message ],
                'timeout' => 15,
            ]
        );
        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return ! empty( $data['sid'] );
    }

    private static function send_vonage( $phone, $message ) {
        $key    = get_option( 'car_vonage_key', '' );
        $secret = get_option( 'car_vonage_secret', '' );
        $from   = get_option( 'car_vonage_from', get_bloginfo( 'name' ) );
        if ( ! $key || ! $secret ) return false;

        $response = wp_remote_post(
            'https://rest.nexmo.com/sms/json',
            [
                'body'    => [
                    'api_key'    => $key,
                    'api_secret' => $secret,
                    'to'         => ltrim( $phone, '+' ),
                    'from'       => $from,
                    'text'       => $message,
                ],
                'timeout' => 15,
            ]
        );
        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['messages'][0]['status'] ) && '0' === (string) $data['messages'][0]['status'];
    }

    private static function format_phone( $phone ) {
        if ( ! $phone ) return '';
        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        if ( strpos( $phone, '+' ) !== 0 ) {
            $phone = '+' . $phone;
        }
        return strlen( $phone ) >= 8 ? $phone : '';
    }
}
