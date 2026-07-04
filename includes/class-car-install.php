<?php
defined( 'ABSPATH' ) || exit;

class CAR_Install {

    public static function maybe_install() {
        if ( get_option( 'car_pro_db_version' ) !== CAR_PRO_DB_VERSION ) {
            self::activate();
        }
    }

    public static function activate() {
        self::create_tables();
        self::create_default_settings();
        self::schedule_cron();

        update_option( 'car_pro_db_version', CAR_PRO_DB_VERSION );
        update_option( 'car_pro_activated', current_time( 'mysql' ) );

        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'car_pro_process_scheduler' );
        flush_rewrite_rules();
    }

    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $sql     = [];

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}car_abandoned_carts (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id     VARCHAR(255)    NOT NULL,
            user_id        BIGINT UNSIGNED DEFAULT 0,
            email          VARCHAR(255)    DEFAULT '',
            phone          VARCHAR(50)     DEFAULT '',
            first_name     VARCHAR(100)    DEFAULT '',
            last_name      VARCHAR(100)    DEFAULT '',
            cart_contents  LONGTEXT        NOT NULL,
            cart_total     DECIMAL(10,4)   DEFAULT 0,
            currency       VARCHAR(10)     DEFAULT 'USD',
            checkout_url   TEXT,
            status         VARCHAR(30)     DEFAULT 'pending',
            gdpr_consent   TINYINT(1)      DEFAULT 0,
            ip_address     VARCHAR(50)     DEFAULT '',
            user_agent     TEXT,
            created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            abandoned_at   DATETIME        DEFAULT NULL,
            recovered_at   DATETIME        DEFAULT NULL,
            recovery_order_id BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY email (email),
            KEY status (status),
            KEY abandoned_at (abandoned_at)
        ) $charset;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}car_campaigns (
            id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            name                VARCHAR(255)    NOT NULL DEFAULT '',
            channel             VARCHAR(30)     NOT NULL DEFAULT 'email',
            status              VARCHAR(30)     NOT NULL DEFAULT 'active',
            send_after_minutes  INT UNSIGNED    NOT NULL DEFAULT 60,
            subject             VARCHAR(500)    DEFAULT '',
            body                LONGTEXT,
            coupon_enabled      TINYINT(1)      DEFAULT 0,
            coupon_type         VARCHAR(30)     DEFAULT 'percent',
            coupon_amount       DECIMAL(10,2)   DEFAULT 0,
            coupon_expiry_days  INT UNSIGNED    DEFAULT 3,
            sort_order          INT UNSIGNED    DEFAULT 0,
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY channel (channel)
        ) $charset;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}car_email_logs (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cart_id         BIGINT UNSIGNED NOT NULL,
            campaign_id     INT UNSIGNED    NOT NULL,
            channel         VARCHAR(30)     DEFAULT 'email',
            recipient       VARCHAR(255)    DEFAULT '',
            subject         VARCHAR(500)    DEFAULT '',
            status          VARCHAR(30)     DEFAULT 'pending',
            tracking_token  VARCHAR(64)     DEFAULT '',
            coupon_code     VARCHAR(100)    DEFAULT '',
            sent_at         DATETIME        DEFAULT NULL,
            opened_at       DATETIME        DEFAULT NULL,
            clicked_at      DATETIME        DEFAULT NULL,
            open_count      INT UNSIGNED    DEFAULT 0,
            click_count     INT UNSIGNED    DEFAULT 0,
            unsubscribed    TINYINT(1)      DEFAULT 0,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY cart_id (cart_id),
            KEY campaign_id (campaign_id),
            KEY tracking_token (tracking_token),
            KEY recipient (recipient)
        ) $charset;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}car_rules (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            campaign_id     INT UNSIGNED    NOT NULL,
            rule_type       VARCHAR(50)     NOT NULL,
            operator        VARCHAR(30)     NOT NULL DEFAULT 'equals',
            rule_value      VARCHAR(500)    DEFAULT '',
            action          VARCHAR(30)     NOT NULL DEFAULT 'include',
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id)
        ) $charset;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}car_coupons (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            cart_id         BIGINT UNSIGNED NOT NULL,
            campaign_id     INT UNSIGNED    NOT NULL,
            coupon_code     VARCHAR(100)    NOT NULL,
            coupon_id       BIGINT UNSIGNED DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at      DATETIME        DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY cart_campaign (cart_id, campaign_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    public static function create_default_settings() {
        $defaults = [
            'car_enabled'                  => 'yes',
            'car_cutoff_time'              => 60,
            'car_gdpr_enabled'             => 'no',
            'car_gdpr_text'                => 'I agree to receive cart recovery emails.',
            'car_show_tax'                 => 'yes',
            'car_email_from_name'          => get_bloginfo( 'name' ),
            'car_email_from_address'       => get_option( 'admin_email' ),
            'car_whatsapp_enabled'         => 'no',
            'car_whatsapp_provider'        => 'ultramsg',
            'car_ultramsg_instance'        => '',
            'car_ultramsg_token'           => '',
            'car_whatsapp_business_token'  => '',
            'car_whatsapp_phone_id'        => '',
            'car_sms_enabled'              => 'no',
            'car_sms_provider'             => 'twilio',
            'car_twilio_sid'               => '',
            'car_twilio_token'             => '',
            'car_twilio_from'              => '',
            'car_vonage_key'               => '',
            'car_vonage_secret'            => '',
            'car_vonage_from'              => '',
            'car_telegram_enabled'         => 'no',
            'car_telegram_bot_token'       => '',
            'car_admin_notify_email'       => 'yes',
            'car_admin_notify_address'     => get_option( 'admin_email' ),
            'car_unsubscribe_enabled'      => 'yes',
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $value );
            }
        }

        // Create default email campaigns if none exist.
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}car_campaigns" );

        if ( '0' === (string) $count ) {
            self::insert_default_campaigns();
        }
    }

    private static function insert_default_campaigns() {
        global $wpdb;

        $campaigns = [
            [
                'name'              => 'First Reminder (15 min)',
                'channel'           => 'email',
                'status'            => 'active',
                'send_after_minutes'=> 15,
                'subject'           => 'Hey {customer_name}, you left something behind!',
                'body'              => self::default_email_body( 1 ),
                'coupon_enabled'    => 0,
                'sort_order'        => 1,
            ],
            [
                'name'              => 'Second Reminder (1 hour)',
                'channel'           => 'email',
                'status'            => 'active',
                'send_after_minutes'=> 60,
                'subject'           => '{customer_name}, your cart is waiting for you',
                'body'              => self::default_email_body( 2 ),
                'coupon_enabled'    => 0,
                'sort_order'        => 2,
            ],
            [
                'name'              => 'Final Offer (24 hours) + Coupon',
                'channel'           => 'email',
                'status'            => 'active',
                'send_after_minutes'=> 1440,
                'subject'           => 'Last chance! Here\'s {discount}% off your order, {customer_name}',
                'body'              => self::default_email_body( 3 ),
                'coupon_enabled'    => 1,
                'coupon_type'       => 'percent',
                'coupon_amount'     => 10.00,
                'coupon_expiry_days'=> 3,
                'sort_order'        => 3,
            ],
        ];

        foreach ( $campaigns as $c ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert( $wpdb->prefix . 'car_campaigns', $c );
        }
    }

    private static function default_email_body( $step ) {
        switch ( $step ) {
            case 1:
                return '<p>Hi {customer_name},</p><p>We noticed you left some great items in your cart. Don\'t let them get away!</p><p>{cart_items_table}</p><p><a href="{recovery_link}" style="background:#0073aa;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">Complete Your Purchase</a></p><p style="font-size:12px;">{unsubscribe_link}</p>';
            case 2:
                return '<p>Hi {customer_name},</p><p>Your cart is still waiting! Here\'s a quick reminder of what you left behind:</p><p>{cart_items_table}</p><p>Cart Total: <strong>{cart_total}</strong></p><p><a href="{recovery_link}" style="background:#0073aa;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">Return to My Cart</a></p><p style="font-size:12px;">{unsubscribe_link}</p>';
            case 3:
                return '<p>Hi {customer_name},</p><p>This is your last chance! We\'ve saved your cart and we\'re offering you an exclusive discount.</p><p>{cart_items_table}</p><p>Use code <strong>{coupon_code}</strong> for {coupon_amount}% off. Expires in {coupon_expiry} days!</p><p><a href="{recovery_link}" style="background:#e44d26;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">Claim My Discount</a></p><p style="font-size:12px;">{unsubscribe_link}</p>';
        }
        return '';
    }

    private static function schedule_cron() {
        // The cron interval is registered in CAR_Pro::add_cron_intervals() on
        // the 'cron_schedules' filter – no need to duplicate it here.
        if ( ! wp_next_scheduled( 'car_pro_process_scheduler' ) ) {
            wp_schedule_event( time(), 'every_5_minutes', 'car_pro_process_scheduler' );
        }
    }
}
