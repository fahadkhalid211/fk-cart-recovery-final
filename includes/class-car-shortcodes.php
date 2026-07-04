<?php
defined( 'ABSPATH' ) || exit;

class CAR_Shortcodes {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode( 'car_stats',          [ $this, 'stats_shortcode' ] );
        add_shortcode( 'car_recovery_button',[ $this, 'recovery_button' ] );
    }

    public function stats_shortcode( $atts ) {
        if ( ! current_user_can( 'manage_options' ) ) return '';
        $stats = CAR_DB::get_stats();
        ob_start();
        echo '<div class="car-stats">';
        echo '<span>' . esc_html__( 'Abandoned', 'fk-cart-recovery' ) . ': ' . esc_html( $stats['total_abandoned'] ) . '</span> ';
        echo '<span>' . esc_html__( 'Recovered', 'fk-cart-recovery' ) . ': ' . esc_html( $stats['total_recovered'] ) . '</span>';
        echo '</div>';
        return ob_get_clean();
    }

    public function recovery_button( $atts ) {
        $atts = shortcode_atts( [ 'text' => __( 'Recover My Cart', 'fk-cart-recovery' ), 'class' => 'button' ], $atts );
        // Nonce not applicable – this is a read-only shortcode that renders a link; no data is written.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['car_recover'] ) ) {
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            return '';
        }
        $recover = sanitize_text_field( wp_unslash( $_GET['car_recover'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        return '<a href="' . esc_url( add_query_arg( 'car_recover', $recover ) ) . '" class="' . esc_attr( $atts['class'] ) . '">' . esc_html( $atts['text'] ) . '</a>';
    }
}
