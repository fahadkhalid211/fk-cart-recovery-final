<?php
defined( 'ABSPATH' ) || exit;

class CAR_Email_Handler {

    /**
     * Convert wc_price() HTML output to a plain readable string.
     */
    public static function format_price_plain( $amount ) {
        return html_entity_decode(
            wp_strip_all_tags( wc_price( $amount ) ),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }

    /**
     * Convert an HTML email body to clean plain text for WhatsApp / SMS / Telegram.
     */
    public static function html_to_plain( $html ) {
        $html = preg_replace( '#<br\s*/?>(\s*)?#i', "\n", $html );
        $html = preg_replace( '#</(p|div|li|h[1-6]|tr)>#i', "\n", $html );
        $html = wp_strip_all_tags( $html );
        $html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $html = preg_replace( "/\n{3,}/", "\n", $html );
        return trim( $html );
    }

    public static function send( $cart, $campaign ) {
        if ( CAR_Recovery_Link::is_unsubscribed( $cart->email ) ) {
            return false;
        }

        $coupon_code = '';
        if ( $campaign->coupon_enabled ) {
            $coupon_code = CAR_Coupon::get_or_create( $cart->id, $campaign );
        }

        $token        = wp_generate_password( 32, false );
        $recovery_url = CAR_Recovery_Link::generate( $cart->id );

        if ( $coupon_code ) {
            $recovery_url = add_query_arg( 'car_coupon', $coupon_code, $recovery_url );
        }

        $unsub_url = CAR_Recovery_Link::generate_unsubscribe( $cart->email );
        $track_url = add_query_arg( 'car_tk', $token, home_url( '/' ) );

        $log_id = CAR_DB::insert_log( [
            'cart_id'        => $cart->id,
            'campaign_id'    => $campaign->id,
            'channel'        => 'email',
            'recipient'      => $cart->email,
            'subject'        => $campaign->subject,
            'status'         => 'pending',
            'tracking_token' => $token,
            'coupon_code'    => $coupon_code,
        ] );

        // Only add the tracking token to the URL (removed unused car_log param)
        $recovery_url = add_query_arg( 'car_tk', $token, $recovery_url );

        $placeholders = self::get_placeholders( $cart, $coupon_code, $campaign, $recovery_url, $unsub_url, $track_url );

        $subject = self::replace( $campaign->subject, $placeholders );
        $body    = self::replace( $campaign->body, $placeholders );
        
        // Add tracking pixel
        $body   .= '<img src="' . esc_url( $track_url ) . '" width="1" height="1" alt="" style="display:none;" />';

        $from_name    = get_option( 'car_email_from_name', get_bloginfo( 'name' ) );
        $from_address = get_option( 'car_email_from_address', get_option( 'admin_email' ) );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_address}>",
        ];

        $sent = wp_mail( $cart->email, $subject, self::wrap_html( $body, $subject ), $headers );

        CAR_DB::update_log( $log_id, [
            'status'  => $sent ? 'sent' : 'failed',
            'sent_at' => $sent ? current_time( 'mysql' ) : null,
            'subject' => $subject,
        ] );

        return $sent;
    }

    private static function get_placeholders( $cart, $coupon_code, $campaign, $recovery_url, $unsub_url, $track_url ) {
        $items    = maybe_unserialize( $cart->cart_contents );
        $show_tax = get_option( 'car_show_tax', 'yes' ) === 'yes';

        return [
            '{customer_name}'    => trim( $cart->first_name . ' ' . $cart->last_name ) ?: __( 'Valued Customer', 'fk-cart-recovery' ),
            '{customer_email}'   => $cart->email,
            '{cart_total}'       => wc_price( $cart->cart_total ),
            '{cart_items_table}' => self::items_table( $items, $show_tax ),
            '{recovery_link}'    => $recovery_url,
            '{coupon_code}'      => $coupon_code ?: '',
            '{coupon_amount}'    => $campaign->coupon_amount ?? '',
            '{coupon_expiry}'    => $campaign->coupon_expiry_days ?? '',
            '{discount}'         => $campaign->coupon_amount ?? '',
            '{site_name}'        => get_bloginfo( 'name' ),
            '{site_url}'         => home_url(),
            '{unsubscribe_link}' => '<a href="' . esc_url( $unsub_url ) . '" style="color:#999;font-size:12px;">' . __( 'Unsubscribe', 'fk-cart-recovery' ) . '</a>',
            '{year}'             => gmdate( 'Y' ),
        ];
    }

    private static function replace( $text, $placeholders ) {
        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $text );
    }

    private static function items_table( $items, $show_tax = true ) {
        if ( empty( $items ) || ! is_array( $items ) ) {
            return '';
        }

        $html  = '<table style="width:100%;border-collapse:collapse;margin:20px 0;">';
        $html .= '<thead><tr style="background:#f5f5f5;">'
            . '<th style="padding:10px;text-align:left;border-bottom:2px solid #ddd;">'   . esc_html__( 'Product', 'fk-cart-recovery' ) . '</th>'
            . '<th style="padding:10px;text-align:center;border-bottom:2px solid #ddd;">' . esc_html__( 'Qty', 'fk-cart-recovery' ) . '</th>'
            . '<th style="padding:10px;text-align:right;border-bottom:2px solid #ddd;">'  . esc_html__( 'Price', 'fk-cart-recovery' ) . '</th>'
            . '</tr></thead><tbody>';

        foreach ( $items as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) continue;

            $img   = wp_get_attachment_image_url( $product->get_image_id(), [ 60, 60 ] );
            $price = wc_price( (float) $product->get_price() * (int) $item['quantity'] );

            $html .= '<tr><td style="padding:10px;border-bottom:1px solid #eee;">';
            if ( $img ) {
                $html .= '<img src="' . esc_url( $img ) . '" style="width:50px;height:50px;object-fit:cover;vertical-align:middle;margin-right:10px;" alt="" />';
            }
            $html .= esc_html( $product->get_name() ) . '</td>'
                . '<td style="padding:10px;text-align:center;border-bottom:1px solid #eee;">' . esc_html( $item['quantity'] ) . '</td>'
                . '<td style="padding:10px;text-align:right;border-bottom:1px solid #eee;">'  . $price . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public static function wrap_html( $body, $subject ) {
        $site = get_bloginfo( 'name' );
        $logo = '';
        
        if ( has_custom_logo() ) {
            $logo_id  = get_theme_mod( 'custom_logo' );
            $logo_src = wp_get_attachment_image_src( $logo_id, 'medium' );
            $logo     = $logo_src ? $logo_src[0] : '';
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo esc_attr( get_locale() ); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title><?php echo esc_html( $subject ); ?></title>
        </head>
        <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">
                            <tr>
                                <td style="background:#0073aa;padding:25px;text-align:center;">
                                    <?php if ( $logo ) : ?>
                                        <img src="<?php echo esc_url( $logo ); ?>" style="max-height:60px;" alt="<?php echo esc_attr( $site ); ?>" />
                                    <?php else : ?>
                                        <h1 style="color:#fff;margin:0;font-size:24px;"><?php echo esc_html( $site ); ?></h1>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:30px;"><?php echo wp_kses_post( $body ); ?></td>
                            </tr>
                            <tr>
                                <td style="background:#f9f9f9;padding:20px;text-align:center;border-top:1px solid #eee;">
                                    <p style="color:#999;font-size:12px;margin:0;">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site ); ?>.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}