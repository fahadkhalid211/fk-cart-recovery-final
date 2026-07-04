<?php
defined( 'ABSPATH' ) || exit;

class CAR_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        
        add_action( 'wp_ajax_car_delete_cart',              [ $this, 'ajax_delete_cart' ] );
        add_action( 'wp_ajax_car_save_campaign',            [ $this, 'ajax_save_campaign' ] );
        add_action( 'wp_ajax_car_delete_campaign',          [ $this, 'ajax_delete_campaign' ] );
        add_action( 'wp_ajax_car_save_settings',            [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_car_test_whatsapp',            [ $this, 'ajax_test_whatsapp' ] );
        add_action( 'wp_ajax_car_setup_telegram_webhook',   [ $this, 'ajax_telegram_webhook' ] );
        add_action( 'wp_ajax_car_get_chart_data',           [ $this, 'ajax_chart_data' ] );
        add_action( 'wp_ajax_car_get_campaign',             [ $this, 'ajax_get_campaign' ] );
        add_action( 'wp_ajax_car_save_rules',               [ $this, 'ajax_save_rules' ] );
        add_action( 'wp_ajax_car_get_recovery_link',        [ $this, 'ajax_get_recovery_link' ] ); // NEW: On-demand link generation
        add_action( 'wp_ajax_car_get_report_chart_data',    [ $this, 'ajax_report_chart_data' ] ); // NEW: Reports trend + channel charts
        add_action( 'admin_post_car_export_report_csv',     [ $this, 'export_report_csv' ] );      // NEW: Reports CSV export
        
        add_filter( 'plugin_action_links_' . CAR_PRO_BASENAME, [ $this, 'plugin_action_links' ] );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Cart Recovery', 'fk-cart-recovery' ),
            __( 'Cart Recovery', 'fk-cart-recovery' ),
            'manage_options',
            'fk-cart-recovery',
            [ $this, 'render_page' ],
            'dashicons-cart',
            56
        );

        add_submenu_page( 'fk-cart-recovery', __( 'Dashboard', 'fk-cart-recovery' ),  __( 'Dashboard', 'fk-cart-recovery' ),  'manage_options', 'fk-cart-recovery', [ $this, 'render_page' ] );
        add_submenu_page( 'fk-cart-recovery', __( 'Campaigns', 'fk-cart-recovery' ),  __( 'Campaigns', 'fk-cart-recovery' ),  'manage_options', 'car-pro-campaigns',          [ $this, 'render_campaigns' ] );
        add_submenu_page( 'fk-cart-recovery', __( 'Carts', 'fk-cart-recovery' ),      __( 'Carts', 'fk-cart-recovery' ),      'manage_options', 'car-pro-carts',              [ $this, 'render_carts' ] );
        add_submenu_page( 'fk-cart-recovery', __( 'Reports', 'fk-cart-recovery' ),    __( 'Reports', 'fk-cart-recovery' ),    'manage_options', 'car-pro-reports',            [ $this, 'render_reports' ] );
        add_submenu_page( 'fk-cart-recovery', __( 'Settings', 'fk-cart-recovery' ),   __( 'Settings', 'fk-cart-recovery' ),   'manage_options', 'car-pro-settings',           [ $this, 'render_settings' ] );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'fk-cart-recovery' ) && false === strpos( $hook, 'car-pro-' ) ) {
            return;
        }

        wp_enqueue_style( 'car-admin', CAR_PRO_URL . 'assets/css/admin.css', [], CAR_PRO_VERSION );
        wp_enqueue_script( 'car-admin', CAR_PRO_URL . 'assets/js/admin.js', [ 'jquery', 'wp-color-picker' ], CAR_PRO_VERSION, true );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_editor();

        wp_localize_script( 'car-admin', 'carAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'car_admin_nonce' ),
            'i18n'    => [
                'confirmDelete' => __( 'Are you sure you want to delete this?', 'fk-cart-recovery' ),
                'saving'        => __( 'Saving...', 'fk-cart-recovery' ),
                'saved'         => __( 'Saved!', 'fk-cart-recovery' ),
                'error'         => __( 'An error occurred.', 'fk-cart-recovery' ),
            ],
        ] );

        wp_register_script( 'chartjs', CAR_PRO_URL . 'assets/js/vendor/chart.min.js', [], '4.4.0', true );
        wp_enqueue_script( 'chartjs' );
    }

    public function render_page()      { include CAR_PRO_PATH . 'admin/views/dashboard.php'; }
    public function render_campaigns() { include CAR_PRO_PATH . 'admin/views/campaigns.php'; }
    public function render_carts()     { include CAR_PRO_PATH . 'admin/views/carts.php'; }
    public function render_reports()   { include CAR_PRO_PATH . 'admin/views/reports.php'; }
    public function render_settings()  { include CAR_PRO_PATH . 'admin/views/settings.php'; }

    // ── AJAX: verify nonce helper ────────────────────────────────────────────
    private function verify_nonce() {
        check_ajax_referer( 'car_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }
    }

    // ── AJAX handlers ────────────────────────────────────────────────────────
    public function ajax_delete_cart() {
        $this->verify_nonce();
        $id = absint( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        CAR_DB::delete_cart( $id );
        wp_send_json_success();
    }

    public function ajax_save_campaign() {
        $this->verify_nonce();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified via verify_nonce()
        $data = [
            'name'               => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'channel'            => sanitize_key( wp_unslash( $_POST['channel'] ?? 'email' ) ),
            'status'             => sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) ),
            'send_after_minutes' => absint( $_POST['send_after_minutes'] ?? 60 ),
            'subject'            => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
            'body'               => wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) ),
            'coupon_enabled'     => isset( $_POST['coupon_enabled'] ) ? 1 : 0,
            'coupon_type'        => sanitize_key( wp_unslash( $_POST['coupon_type'] ?? 'percent' ) ),
            'coupon_amount'      => (float) sanitize_text_field( wp_unslash( $_POST['coupon_amount'] ?? '10' ) ),
            'coupon_expiry_days' => absint( $_POST['coupon_expiry_days'] ?? 3 ),
            'sort_order'         => absint( $_POST['sort_order'] ?? 0 ),
        ];

        if ( ! empty( $_POST['campaign_id'] ) ) {
            $data['id'] = absint( wp_unslash( $_POST['campaign_id'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $id = CAR_DB::save_campaign( $data );
        wp_send_json_success( [ 'id' => $id ] );
    }

    public function ajax_delete_campaign() {
        $this->verify_nonce();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        CAR_DB::delete_campaign( absint( wp_unslash( $_POST['id'] ?? 0 ) ) );
        wp_send_json_success();
    }

    public function ajax_save_settings() {
        $this->verify_nonce();
        
        $fields = [
            'car_enabled', 'car_cutoff_time', 'car_gdpr_enabled', 'car_gdpr_text',
            'car_show_tax', 'car_email_from_name', 'car_email_from_address',
            'car_whatsapp_enabled', 'car_whatsapp_provider',
            'car_ultramsg_instance', 'car_ultramsg_token',
            'car_whatsapp_business_token', 'car_whatsapp_phone_id',
            'car_sms_enabled', 'car_sms_provider',
            'car_twilio_sid', 'car_twilio_token', 'car_twilio_from',
            'car_vonage_key', 'car_vonage_secret', 'car_vonage_from',
            'car_telegram_enabled', 'car_telegram_bot_token',
            'car_admin_notify_email', 'car_admin_notify_address',
            'car_unsubscribe_enabled',
        ];

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified via verify_nonce() above
        foreach ( $fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $raw_value = wp_unslash( $_POST[ $f ] );
                
                // Apply specific sanitization based on field type
                if ( 'car_gdpr_text' === $f ) {
                    $value = sanitize_textarea_field( $raw_value ); // Allows line breaks
                } elseif ( in_array( $f, [ 'car_email_from_address', 'car_admin_notify_address' ], true ) ) {
                    $value = sanitize_email( $raw_value );
                } elseif ( 'car_cutoff_time' === $f ) {
                    $value = absint( $raw_value );
                } else {
                    $value = sanitize_text_field( $raw_value );
                }
                
                update_option( $f, $value );
            } else {
                // Unchecked checkboxes are not sent in $_POST – store 'no'.
                $checkbox_fields = [
                    'car_enabled', 'car_gdpr_enabled', 'car_show_tax',
                    'car_whatsapp_enabled', 'car_sms_enabled', 'car_telegram_enabled',
                    'car_admin_notify_email', 'car_unsubscribe_enabled',
                ];
                if ( in_array( $f, $checkbox_fields, true ) ) {
                    update_option( $f, 'no' );
                }
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json_success();
    }

    public function ajax_test_whatsapp() {
        $this->verify_nonce();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        $provider = sanitize_key( wp_unslash( $_POST['provider'] ?? 'ultramsg' ) );
        $result   = CAR_WhatsApp::test_connection( $provider );
        wp_send_json( $result );
    }

    public function ajax_telegram_webhook() {
        $this->verify_nonce();
        $result = CAR_Telegram::setup_webhook();
        wp_send_json( $result );
    }

    public function ajax_chart_data() {
        $this->verify_nonce();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        $days = absint( $_POST['days'] ?? 30 );
        wp_send_json_success( CAR_Analytics::chart( $days ) );
    }

    /**
     * NEW: Feeds the Reports page trend chart + channel breakdown doughnut,
     * scoped to whatever date range the user has applied on that page.
     */
    public function ajax_report_chart_data() {
        $this->verify_nonce();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        $from = $this->sanitize_report_date( wp_unslash( $_POST['from'] ?? '' ), gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        $to   = $this->sanitize_report_date( wp_unslash( $_POST['to'] ?? '' ), gmdate( 'Y-m-d' ) );

        wp_send_json_success( [
            'trend'    => CAR_Analytics::chart_range( $from, $to ),
            'channels' => CAR_Analytics::channel_stats( $from, $to ),
        ] );
    }

    /**
     * Validates a Y-m-d date string coming from the client; falls back to a
     * known-good default rather than passing anything unexpected through to SQL.
     */
    private function sanitize_report_date( $value, $default ) {
        $value = sanitize_text_field( $value );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : $default;
    }

    public function ajax_get_campaign() {
        $this->verify_nonce();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        $id       = absint( wp_unslash( $_POST['id'] ?? 0 ) );
        $campaign = CAR_DB::get_campaign( $id );
        $rules    = CAR_DB::get_rules( $id );
        wp_send_json_success( [ 'campaign' => $campaign, 'rules' => $rules ] );
    }

    public function ajax_save_rules() {
        $this->verify_nonce();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        $campaign_id = absint( wp_unslash( $_POST['campaign_id'] ?? 0 ) );
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-field below
        $rules_raw = isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : [];
        $rules = [];
        
        foreach ( $rules_raw as $r ) {
            if ( ! is_array( $r ) ) continue;
            $rules[] = [
                'rule_type'  => sanitize_key( $r['rule_type'] ?? '' ),
                'operator'   => sanitize_key( $r['operator'] ?? '' ),
                'rule_value' => sanitize_text_field( $r['rule_value'] ?? '' ),
                'action'     => sanitize_key( $r['action'] ?? 'include' ),
            ];
        }
        
        CAR_DB::save_rules( $campaign_id, $rules );
        wp_send_json_success();
    }

    /**
     * NEW: Generate recovery link on-demand to prevent database transient pollution.
     */
    public function ajax_get_recovery_link() {
        $this->verify_nonce();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
        $id = absint( wp_unslash( $_POST['id'] ?? 0 ) );
        $url = CAR_Recovery_Link::generate( $id );
        wp_send_json_success( [ 'url' => $url ] );
    }

    /**
     * NEW: Streams the current Reports view (summary + campaign performance +
     * product breakdown) as a CSV download.
     */
    public function export_report_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'fk-cart-recovery' ), '', [ 'response' => 403 ] );
        }
        check_admin_referer( 'car_export_report_csv' );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via check_admin_referer() above
        $from = $this->sanitize_report_date( isset( $_GET['date_from'] ) ? wp_unslash( $_GET['date_from'] ) : '', gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via check_admin_referer() above
        $to   = $this->sanitize_report_date( isset( $_GET['date_to'] )   ? wp_unslash( $_GET['date_to'] )   : '', gmdate( 'Y-m-d' ) );

        $stats     = CAR_Analytics::summary( $from, $to );
        $rate      = CAR_Analytics::recovery_rate( $from, $to );
        $campaigns = CAR_Analytics::campaign_performance( $from, $to );
        $products  = CAR_Analytics::products( 50 );
        $currency  = get_woocommerce_currency_symbol();

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=cart-recovery-report-' . $from . '-to-' . $to . '.csv' );
        header( 'X-Content-Type-Options: nosniff' );

        $out = fopen( 'php://output', 'w' );
        // UTF-8 BOM so Excel renders currency symbols / non-ASCII product names correctly.
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, [ 'Cart Abandonment Recovery Pro — Report', $from . ' to ' . $to ] );
        fputcsv( $out, [] );

        fputcsv( $out, [ 'Summary' ] );
        fputcsv( $out, [ 'Total Abandoned', $stats['total_abandoned'] ] );
        fputcsv( $out, [ 'Total Recovered', $stats['total_recovered'] ] );
        fputcsv( $out, [ 'Recovery Rate (%)', $rate ] );
        fputcsv( $out, [ 'Abandoned Revenue', $currency . number_format( (float) $stats['abandoned_value'], 2 ) ] );
        fputcsv( $out, [ 'Recovered Revenue', $currency . number_format( (float) $stats['recovered_value'], 2 ) ] );
        fputcsv( $out, [] );

        fputcsv( $out, [ 'Campaign Performance' ] );
        fputcsv( $out, [ 'Campaign', 'Channel', 'Sent', 'Opened', 'Open Rate (%)', 'Clicked', 'Click Rate (%)', 'Unsubscribed' ] );
        foreach ( $campaigns as $row ) {
            $open_rate  = $row->sent ? round( $row->opened  / $row->sent * 100, 1 ) : 0;
            $click_rate = $row->sent ? round( $row->clicked / $row->sent * 100, 1 ) : 0;
            fputcsv( $out, [
                $this->csv_safe( $row->name ),
                $row->channel,
                $row->sent,
                $row->opened,
                $open_rate,
                $row->clicked,
                $click_rate,
                $row->unsubscribed,
            ] );
        }
        fputcsv( $out, [] );

        fputcsv( $out, [ 'Product-Level Abandonment' ] );
        fputcsv( $out, [ 'Product', 'Abandoned', 'Recovered', 'Recovery Rate (%)' ] );
        foreach ( $products as $p ) {
            $pr = ( $p['abandoned'] + $p['recovered'] ) > 0 ? round( $p['recovered'] / ( $p['abandoned'] + $p['recovered'] ) * 100, 1 ) : 0;
            fputcsv( $out, [ $this->csv_safe( $p['name'] ?: ( '#' . $p['id'] ) ), $p['abandoned'], $p['recovered'], $pr ] );
        }

        fclose( $out );
        exit;
    }

    /**
     * Neutralizes CSV/formula injection: if a value opens with a character Excel/Sheets
     * would interpret as the start of a formula, prefix it with a leading apostrophe so
     * it's forced to render as plain text instead of being evaluated on open.
     */
    private function csv_safe( $value ) {
        $value = (string) $value;
        if ( isset( $value[0] ) && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            return "'" . $value;
        }
        return $value;
    }

    // ── Plugin action links (plugins.php) ────────────────────────────────────
    public function plugin_action_links( $links ) {
        $custom = [
            '<a href="' . esc_url( admin_url( 'admin.php?page=car-pro-settings' ) ) . '">' . esc_html__( 'Settings', 'fk-cart-recovery' ) . '</a>',
            '<a href="' . esc_url( admin_url( 'admin.php?page=fk-cart-recovery' ) ) . '">' . esc_html__( 'Dashboard', 'fk-cart-recovery' ) . '</a>',
        ];
        return array_merge( $custom, $links );
    }
}