<?php
defined( 'ABSPATH' ) || exit;

class CAR_Telegram {

    public static function send( $cart, $campaign ) {
        if ( get_option( 'car_telegram_enabled', 'no' ) !== 'yes' ) return false;

        // Resolve the customer's Telegram chat ID.
        // Logged-in user → user meta.  Guest → option set when they messaged the bot.
        $chat_id = '';
        if ( ! empty( $cart->user_id ) ) {
            $chat_id = (string) get_user_meta( (int) $cart->user_id, 'car_telegram_chat_id', true );
        }

        if ( ! $chat_id && ! empty( $cart->email ) ) {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5
            $chat_id = (string) get_option( 'car_telegram_chat_' . md5( $cart->email ), '' );
        }

        if ( ! $chat_id ) return false;

        $coupon_code = '';
        if ( $campaign->coupon_enabled ) {
            $coupon_code = CAR_Coupon::get_or_create( $cart->id, $campaign );
        }

        $recovery_url = CAR_Recovery_Link::generate( $cart->id );
        if ( $coupon_code ) {
            $recovery_url = add_query_arg( 'car_coupon', $coupon_code, $recovery_url );
        }

        $message = self::build_message( $cart, $campaign, $coupon_code );
        $sent    = self::send_message( $chat_id, $message, $recovery_url );

        CAR_DB::insert_log( [
            'cart_id'     => $cart->id,
            'campaign_id' => $campaign->id,
            'channel'     => 'telegram',
            'recipient'   => $chat_id,
            'subject'     => '',
            'status'      => $sent ? 'sent' : 'failed',
            'sent_at'     => $sent ? current_time( 'mysql' ) : null,
            'coupon_code' => $coupon_code,
        ] );

        return $sent;
    }

    /**
     * Build the Telegram message in Telegram HTML format.
     */
    private static function build_message( $cart, $campaign, $coupon ) {
        $price = CAR_Email_Handler::format_price_plain( $cart->cart_total );
        $name  = trim( $cart->first_name . ' ' . $cart->last_name );
        $name  = $name ? esc_html( $name ) : __( 'there', 'fk-cart-recovery' );
        $site  = esc_html( get_bloginfo( 'name' ) );

        $items      = maybe_unserialize( $cart->cart_contents );
        $items_text = '';
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $product = wc_get_product( $item['product_id'] );
                if ( $product ) {
                    $items_text .= '• ' . esc_html( $product->get_name() ) . ' ×' . (int) $item['quantity'] . "\n";
                }
            }
        }

        if ( ! empty( $campaign->body ) ) {
            $plain = CAR_Email_Handler::html_to_plain( $campaign->body );
            $placeholders = [
                '{customer_name}'    => $name,
                '{cart_items}'       => rtrim( $items_text ),
                '{cart_items_table}' => rtrim( $items_text ),
                '{cart_total}'       => '<b>' . $price . '</b>',
                '{recovery_link}'    => '', 
                '{coupon_code}'      => $coupon ? '<code>' . esc_html( $coupon ) . '</code>' : '',
                '{coupon_amount}'    => esc_html( (string) ( $campaign->coupon_amount ?? '' ) ),
                '{coupon_expiry}'    => esc_html( (string) ( $campaign->coupon_expiry_days ?? '' ) ),
                '{discount}'         => esc_html( (string) ( $campaign->coupon_amount ?? '' ) ),
                '{site_name}'        => $site,
                '{unsubscribe_link}' => '',
            ];
            $msg = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $plain );
        } else {
            $msg  = '👋 <b>' . sprintf( __( 'Hi %s!', 'fk-cart-recovery' ), $name ) . "</b>\n";
            $msg .= sprintf( __( 'You left items worth %1$s in your cart at %2$s.', 'fk-cart-recovery' ), '<b>' . $price . '</b>', $site ) . "\n";
            
            if ( $items_text ) {
                $msg .= "\n" . $items_text;
            }
            
            if ( $coupon ) {
                $msg .= "\n🎁 " . sprintf( __( 'Use code %s for a special discount!', 'fk-cart-recovery' ), '<code>' . esc_html( $coupon ) . '</code>' ) . "\n";
            }
            
            $msg .= "\n" . __( 'Tap the button below to complete your purchase.', 'fk-cart-recovery' );
        }

        return $msg;
    }

    public static function send_message( $chat_id, $text, $button_url = '' ) {
        $token = get_option( 'car_telegram_bot_token', '' );
        if ( ! $token || ! $chat_id ) return false;

        $payload = [
            'chat_id'    => $chat_id,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if ( $button_url ) {
            $payload['reply_markup'] = [
                'inline_keyboard' => [[
                    [
                        'text' => '🛒 ' . __( 'Complete My Order', 'fk-cart-recovery' ),
                        'url'  => $button_url,
                    ],
                ]],
            ];
        }

        $response = wp_remote_post(
            'https://api.telegram.org/bot' . $token . '/sendMessage',
            [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['ok'] ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'CAR Telegram API error: ' . wp_json_encode( $data ) );
        }

        return ! empty( $data['ok'] );
    }

    public static function setup_webhook() {
        $token = get_option( 'car_telegram_bot_token', '' );
        if ( ! $token ) {
            return [ 'success' => false, 'message' => __( 'No bot token configured.', 'fk-cart-recovery' ) ];
        }

        $webhook = home_url( '/?car_telegram_webhook=1' );
        // Generate a secure secret token based on the bot token to verify webhook requests
        $secret  = wp_hash( $token ); 
        
        $url = 'https://api.telegram.org/bot' . $token . '/setWebhook?url=' . rawurlencode( $webhook ) . '&secret_token=' . rawurlencode( $secret );

        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return [
            'success' => ! empty( $data['ok'] ),
            'message' => $data['description'] ?? ( ! empty( $data['ok'] ) ? __( 'Webhook registered!', 'fk-cart-recovery' ) : __( 'Unknown error.', 'fk-cart-recovery' ) ),
        ];
    }

    public static function handle_webhook() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['car_telegram_webhook'] ) ) return;

        // SECURITY: Verify the request is actually from Telegram using the secret token (constant-time compare)
        $token  = get_option( 'car_telegram_bot_token', '' );
        $secret = isset( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ) : '';

        if ( ! $token || ! $secret || ! hash_equals( wp_hash( $token ), $secret ) ) {
            status_header( 403 );
            exit;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw  = file_get_contents( 'php://input' );
        $data = json_decode( $raw, true );

        if ( empty( $data['message'] ) ) exit;

        $msg     = $data['message'];
        $chat_id = $msg['chat']['id'] ?? '';
        $text    = trim( $msg['text'] ?? '' );

        if ( ! $chat_id ) exit;

        if ( is_email( $text ) ) {
            // SECURITY: Never link on a bare typed email — anyone can type anyone
            // else's address. Send a confirmation link to that address instead and
            // only link the chat_id once the real owner clicks it.
            self::send_confirmation_email( sanitize_email( $text ), $chat_id );
            self::send_message( $chat_id, __( "We've sent a confirmation link to that email address. Click it to finish linking your Telegram account.", 'fk-cart-recovery' ) );
        } else {
            self::send_message( $chat_id, __( 'Please send your email address to link your account for cart recovery notifications.', 'fk-cart-recovery' ) );
        }

        exit;
    }

    /**
     * SECURITY: Email-ownership confirmation step for Telegram account linking.
     * Generates a one-time code, stores it against the (email, chat_id) pair,
     * and emails a confirmation link. The chat_id is only linked once that
     * link is clicked from the real inbox.
     */
    private static function send_confirmation_email( $email, $chat_id ) {
        if ( ! is_email( $email ) ) return;

        $code = wp_generate_password( 32, false );
        set_transient( 'car_tg_confirm_' . $code, [ 'email' => $email, 'chat_id' => $chat_id ], HOUR_IN_SECONDS );

        $confirm_url = add_query_arg( 'car_tg_confirm', $code, home_url( '/' ) );

        $subject = sprintf( /* translators: %s: site name */ __( 'Confirm your Telegram link for %s', 'fk-cart-recovery' ), get_bloginfo( 'name' ) );
        $body    = sprintf(
            /* translators: %s: confirmation URL */
            __( "Click the link below to confirm this is your email address and finish linking your Telegram account for cart recovery notifications:\n\n%s\n\nIf you didn't request this, you can ignore this email.", 'fk-cart-recovery' ),
            $confirm_url
        );

        wp_mail( $email, $subject, $body );
    }

    /**
     * SECURITY: Completes Telegram linking only after the person clicks the
     * confirmation link sent to their own inbox — proof they own the address.
     */
    public static function confirm_link() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['car_tg_confirm'] ) ) return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $code = sanitize_text_field( wp_unslash( $_GET['car_tg_confirm'] ) );
        $data = get_transient( 'car_tg_confirm_' . $code );

        if ( ! $data || empty( $data['email'] ) || empty( $data['chat_id'] ) ) {
            wp_die( esc_html__( 'This confirmation link is invalid or has expired.', 'fk-cart-recovery' ) );
        }

        $email   = $data['email'];
        $chat_id = $data['chat_id'];

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5
        update_option( 'car_telegram_chat_' . md5( $email ), $chat_id );

        $user = get_user_by( 'email', $email );
        if ( $user ) {
            update_user_meta( $user->ID, 'car_telegram_chat_id', $chat_id );
        }

        delete_transient( 'car_tg_confirm_' . $code );

        self::send_message( $chat_id, '✅ ' . __( 'Your account is linked! You will receive cart recovery notifications here.', 'fk-cart-recovery' ) );

        wc_add_notice( esc_html__( 'Your Telegram account has been linked successfully.', 'fk-cart-recovery' ), 'success' );
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }
}

add_action( 'init', [ 'CAR_Telegram', 'handle_webhook' ] );
add_action( 'init', [ 'CAR_Telegram', 'confirm_link' ] );
