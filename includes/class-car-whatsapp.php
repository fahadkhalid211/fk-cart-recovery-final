<?php
defined( 'ABSPATH' ) || exit;

class CAR_WhatsApp {

    public static function send( $cart, $campaign ) {
        if ( get_option( 'car_whatsapp_enabled', 'no' ) !== 'yes' ) return false;

        $phone = self::format_phone( $cart->phone );
        if ( ! $phone ) return false;

        $coupon_code = '';
        if ( $campaign->coupon_enabled ) {
            $coupon_code = CAR_Coupon::get_or_create( $cart->id, $campaign );
        }

        $recovery_url = CAR_Recovery_Link::generate( $cart->id );
        if ( $coupon_code ) {
            $recovery_url = add_query_arg( 'car_coupon', $coupon_code, $recovery_url );
        }

        $message  = self::build_message( $cart, $campaign, $coupon_code, $recovery_url );
        $provider = get_option( 'car_whatsapp_provider', 'ultramsg' );

        $sent = false;
        switch ( $provider ) {
            case 'ultramsg':
                $sent = self::send_ultramsg( $phone, $message );
                break;
            case 'whatsapp_business':
                $sent = self::send_business_api( $phone, $message );
                break;
            case 'twilio_wa':
                $sent = self::send_twilio_whatsapp( $phone, $message );
                break;
        }

        CAR_DB::insert_log( [
            'cart_id'     => $cart->id,
            'campaign_id' => $campaign->id,
            'channel'     => 'whatsapp',
            'recipient'   => $phone,
            'subject'     => '',
            'status'      => $sent ? 'sent' : 'failed',
            'sent_at'     => $sent ? current_time( 'mysql' ) : null,
            'coupon_code' => $coupon_code,
        ] );

        return $sent;
    }

    /**
     * Build a plain-text WhatsApp message.
     *
     * Campaign bodies are written in the HTML email editor.  We always convert
     * them to plain text before sending on plain-text channels (WhatsApp / SMS).
     * Placeholders are resolved after conversion so {cart_total} etc. are never
     * replaced with raw HTML.
     */
    private static function build_message( $cart, $campaign, $coupon_code, $recovery_url ) {
        // Plain-text price – never use wc_price() directly; it returns HTML.
        $price_plain   = CAR_Email_Handler::format_price_plain( $cart->cart_total );
        $customer_name = trim( $cart->first_name . ' ' . $cart->last_name ) ?: __( 'there', 'fk-cart-recovery' );
        $site_name     = get_bloginfo( 'name' );

        // Build plain-text item list.
        $items      = maybe_unserialize( $cart->cart_contents );
        $items_text = '';
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $product = wc_get_product( $item['product_id'] );
                if ( $product ) {
                    $items_text .= '• ' . $product->get_name() . ' ×' . (int) $item['quantity'] . "\n";
                }
            }
        }

        if ( ! empty( $campaign->body ) ) {
            // Convert HTML campaign body → plain text, then fill placeholders.
            $body = CAR_Email_Handler::html_to_plain( $campaign->body );
        } else {
            $body = self::default_template( ! empty( $coupon_code ) );
        }

        $placeholders = [
            '{customer_name}'    => $customer_name,
            '{cart_items}'       => rtrim( $items_text ),
            '{cart_items_table}' => rtrim( $items_text ),   // email placeholder → plain list
            '{cart_total}'       => $price_plain,
            '{recovery_link}'    => $recovery_url,
            '{coupon_code}'      => $coupon_code ?: '',
            '{coupon_amount}'    => $campaign->coupon_amount ?? '',
            '{coupon_expiry}'    => $campaign->coupon_expiry_days ?? '',
            '{discount}'         => $campaign->coupon_amount ?? '',
            '{site_name}'        => $site_name,
            '{unsubscribe_link}' => '',                     // not applicable on WhatsApp
        ];

        $message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );

        // Safety net: strip any residual HTML that slipped through.
        $message = CAR_Email_Handler::html_to_plain( $message );

        // Ensure the recovery URL is present.
        if ( false === strpos( $message, $recovery_url ) ) {
            $message .= "\n\n" . $recovery_url;
        }

        return $message;
    }

    private static function default_template( $has_coupon ) {
        $tpl  = "Hi {customer_name}! 👋\n\n";
        $tpl .= __( "You left items worth {cart_total} in your cart at {site_name}.", 'fk-cart-recovery' ) . "\n\n";
        if ( $has_coupon ) {
            $tpl .= __( "🎁 Use code *{coupon_code}* for an exclusive discount!\n\n", 'fk-cart-recovery' );
        }
        $tpl .= __( "✅ Complete your order here:", 'fk-cart-recovery' ) . "\n{recovery_link}";
        return $tpl;
    }

    // ── Providers ─────────────────────────────────────────────────────────────

    private static function send_ultramsg( $phone, $message ) {
        $instance = get_option( 'car_ultramsg_instance', '' );
        $token    = get_option( 'car_ultramsg_token', '' );
        if ( ! $instance || ! $token ) return false;

        $response = wp_remote_post(
            "https://api.ultramsg.com/" . rawurlencode( $instance ) . "/messages/chat",
            [
                'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
                'body'    => http_build_query( [ 'token' => $token, 'to' => $phone, 'body' => $message ] ),
                'timeout' => 15,
            ]
        );
        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['sent'] ) && 'true' === (string) $data['sent'];
    }

    private static function send_business_api( $phone, $message ) {
        $token    = get_option( 'car_whatsapp_business_token', '' );
        $phone_id = get_option( 'car_whatsapp_phone_id', '' );
        if ( ! $token || ! $phone_id ) return false;

        $response = wp_remote_post(
            "https://graph.facebook.com/v18.0/{$phone_id}/messages",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'text',
                    'text'              => [ 'body' => $message ],
                ] ),
                'timeout' => 15,
            ]
        );
        if ( is_wp_error( $response ) ) return false;
        $code = wp_remote_retrieve_response_code( $response );
        return $code >= 200 && $code < 300;
    }

    private static function send_twilio_whatsapp( $phone, $message ) {
        $sid   = get_option( 'car_twilio_sid', '' );
        $token = get_option( 'car_twilio_token', '' );
        $from  = 'whatsapp:+' . ltrim( get_option( 'car_twilio_from', '' ), '+' );
        if ( ! $sid || ! $token ) return false;

        $response = wp_remote_post(
            "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
            [
                'headers' => [
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                    'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body'    => [ 'To' => 'whatsapp:' . $phone, 'From' => $from, 'Body' => $message ],
                'timeout' => 15,
            ]
        );
        if ( is_wp_error( $response ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return ! empty( $data['sid'] );
    }

    private static function format_phone( $phone ) {
        if ( ! $phone ) return '';
        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        if ( strpos( $phone, '+' ) !== 0 ) {
            $phone = '+' . $phone;
        }
        return strlen( $phone ) >= 8 ? $phone : '';
    }

    public static function test_connection( $provider ) {
        if ( 'ultramsg' === $provider ) {
            $instance = get_option( 'car_ultramsg_instance', '' );
            $token    = get_option( 'car_ultramsg_token', '' );
            if ( ! $instance || ! $token ) {
                return [ 'success' => false, 'message' => __( 'Missing credentials.', 'fk-cart-recovery' ) ];
            }
            $resp = wp_remote_get( "https://api.ultramsg.com/" . rawurlencode( $instance ) . "/instance/status?token=" . rawurlencode( $token ), [ 'timeout' => 10 ] );
            if ( is_wp_error( $resp ) ) {
                return [ 'success' => false, 'message' => $resp->get_error_message() ];
            }
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );
            return [ 'success' => ! empty( $body['status']['accountStatus'] ), 'message' => wp_json_encode( $body ) ];
        }
        return [ 'success' => false, 'message' => __( 'Test not available for this provider.', 'fk-cart-recovery' ) ];
    }
}
