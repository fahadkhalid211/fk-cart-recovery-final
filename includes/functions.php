<?php
defined( 'ABSPATH' ) || exit;

/**
 * Convert minutes to a human-readable string.
 *
 * @param int $minutes Number of minutes.
 * @return string
 */
function car_minutes_to_human( $minutes ) {
    $minutes = absint( $minutes );
    if ( $minutes < 60 ) {
        /* translators: %d: number of minutes */
        return sprintf( _n( '%d minute', '%d minutes', $minutes, 'fk-cart-recovery' ), $minutes );
    }
    if ( $minutes < 1440 ) {
        $hours = round( $minutes / 60, 1 );
        /* translators: %s: number of hours (may be decimal) */
        return sprintf( _n( '%s hour', '%s hours', (int) $hours, 'fk-cart-recovery' ), $hours );
    }
    $days = round( $minutes / 1440, 1 );
    /* translators: %s: number of days (may be decimal) */
    return sprintf( _n( '%s day', '%s days', (int) $days, 'fk-cart-recovery' ), $days );
}
