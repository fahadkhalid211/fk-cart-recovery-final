<?php
defined( 'ABSPATH' ) || exit;

/**
 * All direct $wpdb calls in this file are intentional – there is no WP API
 * for plugin-specific tables.  $wpdb->prefix is set by WordPress core and is
 * never user-supplied, so table-name interpolation is safe.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 */
class CAR_DB {

    // ── Abandoned Carts ──────────────────────────────────────────────────────
    public static function get_cart( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}car_abandoned_carts WHERE id = %d",
            absint( $id )
        ) );
    }

    public static function get_cart_by_session( $session_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}car_abandoned_carts WHERE session_id = %s ORDER BY id DESC LIMIT 1",
            $session_id
        ) );
    }

    public static function get_cart_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}car_abandoned_carts WHERE email = %s AND status = 'abandoned' ORDER BY id DESC LIMIT 1",
            sanitize_email( $email )
        ) );
    }

    public static function get_carts( $args = [] ) {
        global $wpdb;

        $defaults = [
            'status'    => '',
            'per_page'  => 20,
            'page'      => 1,
            'orderby'   => 'abandoned_at',
            'order'     => 'DESC',
            'search'    => '',
            'date_from' => '',
            'date_to'   => '',
        ];

        $args  = wp_parse_args( $args, $defaults );
        $where = [ '1=1' ];

        if ( $args['status'] ) {
            $where[] = $wpdb->prepare( 'status = %s', $args['status'] );
        }

        if ( $args['search'] ) {
            $like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = $wpdb->prepare( '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)', $like, $like, $like );
        }

        if ( $args['date_from'] ) {
            $where[] = $wpdb->prepare( 'abandoned_at >= %s', $args['date_from'] . ' 00:00:00' );
        }

        if ( $args['date_to'] ) {
            $where[] = $wpdb->prepare( 'abandoned_at <= %s', $args['date_to'] . ' 23:59:59' );
        }

        $allowed_order = [ 'id', 'email', 'cart_total', 'abandoned_at', 'status', 'recovered_at' ];
        $orderby   = in_array( $args['orderby'], $allowed_order, true ) ? $args['orderby'] : 'abandoned_at';
        $order     = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $offset    = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
        $where_sql = implode( ' AND ', $where );

        // Table name from $wpdb->prefix (trusted). $where_sql built from prepare() fragments only.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results( $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT * FROM {$wpdb->prefix}car_abandoned_carts WHERE {$where_sql} ORDER BY " . esc_sql( $orderby ) . ' ' . esc_sql( $order ) . ' LIMIT %d OFFSET %d',
            absint( $args['per_page'] ),
            $offset
        ) );
    }

    public static function count_carts( $args = [] ) {
        global $wpdb;

        $where = [ '1=1' ];

        if ( ! empty( $args['status'] ) ) {
            $where[] = $wpdb->prepare( 'status = %s', $args['status'] );
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = $wpdb->prepare( 'abandoned_at >= %s', $args['date_from'] . ' 00:00:00' );
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = $wpdb->prepare( 'abandoned_at <= %s', $args['date_to'] . ' 23:59:59' );
        }

        $where_sql = implode( ' AND ', $where );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}car_abandoned_carts WHERE {$where_sql}" );
    }

    public static function save_cart( $data ) {
        global $wpdb;

        $existing = self::get_cart_by_session( $data['session_id'] );

        if ( $existing ) {
            $wpdb->update( $wpdb->prefix . 'car_abandoned_carts', $data, [ 'id' => $existing->id ] );
            return $existing->id;
        }

        $wpdb->insert( $wpdb->prefix . 'car_abandoned_carts', $data );
        return $wpdb->insert_id;
    }

    public static function update_cart( $id, $data ) {
        global $wpdb;
        return $wpdb->update( $wpdb->prefix . 'car_abandoned_carts', $data, [ 'id' => absint( $id ) ] );
    }

    public static function mark_recovered( $cart_id, $order_id ) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'car_abandoned_carts',
            [
                'status'            => 'recovered',
                'recovered_at'      => current_time( 'mysql' ),
                'recovery_order_id' => $order_id,
            ],
            [ 'id' => absint( $cart_id ) ]
        );
    }

    public static function delete_cart( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'car_abandoned_carts', [ 'id' => absint( $id ) ] );
        $wpdb->delete( $wpdb->prefix . 'car_email_logs',      [ 'cart_id' => absint( $id ) ] );
    }

    // ── Campaigns ────────────────────────────────────────────────────────────
    public static function get_campaigns( $args = [] ) {
        global $wpdb;

        $where = [ "status != 'deleted'" ];

        if ( ! empty( $args['status'] ) ) {
            $where[] = $wpdb->prepare( 'status = %s', $args['status'] );
        }

        if ( ! empty( $args['channel'] ) ) {
            $where[] = $wpdb->prepare( 'channel = %s', $args['channel'] );
        }

        $where_sql = implode( ' AND ', $where );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}car_campaigns WHERE {$where_sql} ORDER BY sort_order ASC, id ASC" );
    }

    public static function get_campaign( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}car_campaigns WHERE id = %d",
            absint( $id )
        ) );
    }

    public static function save_campaign( $data ) {
        global $wpdb;

        if ( ! empty( $data['id'] ) ) {
            $id = absint( $data['id'] );
            unset( $data['id'] );
            $wpdb->update( $wpdb->prefix . 'car_campaigns', $data, [ 'id' => $id ] );
            return $id;
        }

        $wpdb->insert( $wpdb->prefix . 'car_campaigns', $data );
        return $wpdb->insert_id;
    }

    public static function delete_campaign( $id ) {
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'car_campaigns', [ 'status' => 'deleted' ], [ 'id' => absint( $id ) ] );
    }

    // ── Email / Message Logs ─────────────────────────────────────────────────
    public static function log_already_sent( $cart_id, $campaign_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}car_email_logs WHERE cart_id = %d AND campaign_id = %d AND status NOT IN ('failed')",
            absint( $cart_id ),
            absint( $campaign_id )
        ) );
    }

    public static function insert_log( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'car_email_logs', $data );
        return $wpdb->insert_id;
    }

    public static function update_log( $id, $data ) {
        global $wpdb;
        return $wpdb->update( $wpdb->prefix . 'car_email_logs', $data, [ 'id' => absint( $id ) ] );
    }

    public static function get_log_by_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}car_email_logs WHERE tracking_token = %s",
            $token
        ) );
    }

    public static function get_logs( $args = [] ) {
        global $wpdb;

        $where = [ '1=1' ];

        if ( ! empty( $args['cart_id'] ) ) {
            $where[] = $wpdb->prepare( 'cart_id = %d', absint( $args['cart_id'] ) );
        }

        if ( ! empty( $args['channel'] ) ) {
            $where[] = $wpdb->prepare( 'channel = %s', $args['channel'] );
        }

        $limit     = isset( $args['limit'] )  ? absint( $args['limit'] )  : 50;
        $offset    = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $where_sql = implode( ' AND ', $where );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results( $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT l.*, c.name AS campaign_name FROM {$wpdb->prefix}car_email_logs l LEFT JOIN {$wpdb->prefix}car_campaigns c ON l.campaign_id = c.id WHERE {$where_sql} ORDER BY l.id DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ) );
    }

    // ── Rules ────────────────────────────────────────────────────────────────
    public static function get_rules( $campaign_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}car_rules WHERE campaign_id = %d",
            absint( $campaign_id )
        ) );
    }

    public static function save_rules( $campaign_id, $rules ) {
        global $wpdb;

        $wpdb->delete( $wpdb->prefix . 'car_rules', [ 'campaign_id' => absint( $campaign_id ) ] );

        foreach ( $rules as $rule ) {
            $rule['campaign_id'] = absint( $campaign_id );
            $wpdb->insert( $wpdb->prefix . 'car_rules', $rule );
        }
    }

    // ── Coupons ──────────────────────────────────────────────────────────────
    public static function get_coupon( $cart_id, $campaign_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}car_coupons WHERE cart_id = %d AND campaign_id = %d",
            absint( $cart_id ),
            absint( $campaign_id )
        ) );
    }

    public static function save_coupon( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'car_coupons', $data );
        return $wpdb->insert_id;
    }

    // ── Analytics ────────────────────────────────────────────────────────────
    public static function get_stats( $date_from = '', $date_to = '' ) {
        global $wpdb;

        // Build each date-range clause individually via prepare() so the
        // assembled $date_sql string contains only already-escaped SQL fragments.
        $date_parts = [];
        if ( $date_from ) {
            $date_parts[] = $wpdb->prepare( 'abandoned_at >= %s', $date_from . ' 00:00:00' );
        }
        if ( $date_to ) {
            $date_parts[] = $wpdb->prepare( 'abandoned_at <= %s', $date_to . ' 23:59:59' );
        }

        $date_sql = $date_parts ? ' AND ' . implode( ' AND ', $date_parts ) : '';

        // $wpdb->prefix is trusted; $date_sql is composed of prepare() output only.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_abandoned = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}car_abandoned_carts WHERE status IN ('abandoned','recovered'){$date_sql}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_recovered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}car_abandoned_carts WHERE status = 'recovered'{$date_sql}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $abandoned_value = (float) $wpdb->get_var( "SELECT COALESCE(SUM(cart_total),0) FROM {$wpdb->prefix}car_abandoned_carts WHERE status = 'abandoned'{$date_sql}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $recovered_value = (float) $wpdb->get_var( "SELECT COALESCE(SUM(cart_total),0) FROM {$wpdb->prefix}car_abandoned_carts WHERE status = 'recovered'{$date_sql}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $emails_sent    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}car_email_logs WHERE status = 'sent'" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $emails_opened  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}car_email_logs WHERE open_count > 0" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $emails_clicked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}car_email_logs WHERE click_count > 0" );

        return compact( 'total_abandoned', 'total_recovered', 'abandoned_value', 'recovered_value', 'emails_sent', 'emails_opened', 'emails_clicked' );
    }

    public static function get_product_stats( $limit = 20 ) {
        global $wpdb;

        // Limit to the most recent 5,000 carts to prevent Out of Memory errors on large stores.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $carts = $wpdb->get_results( "SELECT cart_contents, status FROM {$wpdb->prefix}car_abandoned_carts WHERE status IN ('abandoned','recovered') ORDER BY id DESC LIMIT 5000" );

        $products = [];
        foreach ( $carts as $cart ) {
            $items = maybe_unserialize( $cart->cart_contents );
            if ( ! is_array( $items ) ) continue;

            foreach ( $items as $item ) {
                $pid  = $item['product_id'] ?? 0;
                $name = get_the_title( $pid );

                if ( ! isset( $products[ $pid ] ) ) {
                    $products[ $pid ] = [ 'id' => $pid, 'name' => $name, 'abandoned' => 0, 'recovered' => 0 ];
                }

                if ( 'abandoned' === $cart->status ) {
                    $products[ $pid ]['abandoned']++;
                } else {
                    $products[ $pid ]['recovered']++;
                }
            }
        }

        usort( $products, static fn( $a, $b ) => $b['abandoned'] - $a['abandoned'] );
        return array_slice( $products, 0, absint( $limit ) );
    }

    public static function get_chart_data( $days = 30 ) {
        global $wpdb;

        $start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        // Single query for abandoned carts using GROUP BY to prevent N+1 query issues.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $abandoned_results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(abandoned_at) as date, COUNT(*) as count 
             FROM {$wpdb->prefix}car_abandoned_carts 
             WHERE DATE(abandoned_at) >= %s AND status IN ('abandoned','recovered')
             GROUP BY DATE(abandoned_at)",
            $start_date
        ) );

        // Single query for recovered carts using GROUP BY.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $recovered_results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(recovered_at) as date, COUNT(*) as count 
             FROM {$wpdb->prefix}car_abandoned_carts 
             WHERE DATE(recovered_at) >= %s AND status = 'recovered'
             GROUP BY DATE(recovered_at)",
            $start_date
        ) );

        // Map results by date for fast lookup.
        $abandoned_map = [];
        foreach ( $abandoned_results as $row ) { 
            $abandoned_map[ $row->date ] = (int) $row->count; 
        }
        
        $recovered_map = [];
        foreach ( $recovered_results as $row ) { 
            $recovered_map[ $row->date ] = (int) $row->count; 
        }

        // Build the final array ensuring all dates in the range are present (even if 0).
        $data = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $data[] = [
                'date'      => $date,
                'abandoned' => $abandoned_map[ $date ] ?? 0,
                'recovered' => $recovered_map[ $date ] ?? 0,
            ];
        }

        return $data;
    }

    /**
     * Same shape as get_chart_data() but bounded by an explicit [from, to] range
     * instead of "N days back from today" — used by the Reports date filter.
     */
    public static function get_chart_data_range( $from, $to ) {
        global $wpdb;

        $from = $from ?: gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $to   = $to ?: gmdate( 'Y-m-d' );

        // Guard against an inverted range and cap the span so a mistyped date
        // (e.g. year 2020) can't force the loop below to build tens of thousands of rows.
        if ( strtotime( $from ) > strtotime( $to ) ) {
            [ $from, $to ] = [ $to, $from ];
        }
        $span_days = (int) floor( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
        $span_days = max( 1, min( $span_days, 366 ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $abandoned_results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(abandoned_at) as date, COUNT(*) as count
             FROM {$wpdb->prefix}car_abandoned_carts
             WHERE DATE(abandoned_at) BETWEEN %s AND %s AND status IN ('abandoned','recovered')
             GROUP BY DATE(abandoned_at)",
            $from,
            $to
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $recovered_results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(recovered_at) as date, COUNT(*) as count
             FROM {$wpdb->prefix}car_abandoned_carts
             WHERE DATE(recovered_at) BETWEEN %s AND %s AND status = 'recovered'
             GROUP BY DATE(recovered_at)",
            $from,
            $to
        ) );

        $abandoned_map = [];
        foreach ( $abandoned_results as $row ) {
            $abandoned_map[ $row->date ] = (int) $row->count;
        }

        $recovered_map = [];
        foreach ( $recovered_results as $row ) {
            $recovered_map[ $row->date ] = (int) $row->count;
        }

        $data = [];
        for ( $i = 0; $i < $span_days; $i++ ) {
            $date   = gmdate( 'Y-m-d', strtotime( $from . " +{$i} days" ) );
            $data[] = [
                'date'      => $date,
                'abandoned' => $abandoned_map[ $date ] ?? 0,
                'recovered' => $recovered_map[ $date ] ?? 0,
            ];
        }

        return $data;
    }

    /**
     * Per-campaign send/open/click/unsubscribe performance within a date range.
     * Shared by the Reports page table and the CSV export so the query lives in one place.
     */
    public static function get_campaign_performance( $from = '', $to = '' ) {
        global $wpdb;

        $date_where = [];
        if ( $from ) {
            $date_where[] = $wpdb->prepare( 'DATE(l.sent_at) >= %s', $from );
        }
        if ( $to ) {
            $date_where[] = $wpdb->prepare( 'DATE(l.sent_at) <= %s', $to );
        }
        $date_sql = $date_where ? ' AND ' . implode( ' AND ', $date_where ) : '';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results(
            "SELECT c.name, c.channel,
            COUNT(l.id) as sent,
            SUM(l.open_count > 0) as opened,
            SUM(l.click_count > 0) as clicked,
            SUM(l.unsubscribed) as unsubscribed
            FROM {$wpdb->prefix}car_email_logs l
            JOIN {$wpdb->prefix}car_campaigns c ON l.campaign_id = c.id
            WHERE l.status = 'sent' {$date_sql}
            GROUP BY l.campaign_id
            ORDER BY sent DESC"
        );
    }

    /**
     * Recovery-channel breakdown (email/whatsapp/sms/telegram) for the Reports
     * doughnut chart — counts sent messages per channel within the date range.
     */
    public static function get_channel_stats( $from = '', $to = '' ) {
        global $wpdb;

        $date_parts = [];
        if ( $from ) {
            $date_parts[] = $wpdb->prepare( 'DATE(sent_at) >= %s', $from );
        }
        if ( $to ) {
            $date_parts[] = $wpdb->prepare( 'DATE(sent_at) <= %s', $to );
        }
        $date_sql = $date_parts ? ' AND ' . implode( ' AND ', $date_parts ) : '';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results( "SELECT channel, COUNT(*) as sent FROM {$wpdb->prefix}car_email_logs WHERE status = 'sent'{$date_sql} GROUP BY channel" );

        $out = [];
        foreach ( $rows as $row ) {
            $out[] = [ 'channel' => $row->channel, 'sent' => (int) $row->sent ];
        }
        return $out;
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching