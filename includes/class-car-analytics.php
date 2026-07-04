<?php
defined( 'ABSPATH' ) || exit;

class CAR_Analytics {
    // Wraps DB stats methods for convenience
    public static function summary( $from = '', $to = '' ) {
        return CAR_DB::get_stats( $from, $to );
    }

    public static function chart( $days = 30 ) {
        return CAR_DB::get_chart_data( $days );
    }

    public static function products( $limit = 20 ) {
        return CAR_DB::get_product_stats( $limit );
    }

    public static function recovery_rate( $from = '', $to = '' ) {
        $s = self::summary( $from, $to );
        if ( ! $s['total_abandoned'] ) return 0;
        return round( $s['total_recovered'] / $s['total_abandoned'] * 100, 1 );
    }

    public static function chart_range( $from, $to ) {
        return CAR_DB::get_chart_data_range( $from, $to );
    }

    public static function channel_stats( $from = '', $to = '' ) {
        return CAR_DB::get_channel_stats( $from, $to );
    }

    public static function campaign_performance( $from = '', $to = '' ) {
        return CAR_DB::get_campaign_performance( $from, $to );
    }
}
