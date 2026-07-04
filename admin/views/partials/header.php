<?php defined( 'ABSPATH' ) || exit; ?>
<div class="car-admin-header">
    <div class="car-admin-header-left">
        <h1 class="car-admin-title">
            <span class="car-logo-icon">🛒</span>
            <?php esc_html_e( 'Cart Abandonment Recovery Pro', 'fk-cart-recovery' ); ?>
            <span class="car-version">v<?php echo esc_html( CAR_PRO_VERSION ); ?></span>
        </h1>
    </div>
    <div class="car-admin-header-right">
        <?php $enabled = get_option( 'car_enabled', 'yes' ); ?>
        <span class="car-status-pill car-status--<?php echo 'yes' === $enabled ? 'active' : 'paused'; ?>">
            <?php echo 'yes' === $enabled ? esc_html__( '● Active', 'fk-cart-recovery' ) : esc_html__( '● Paused', 'fk-cart-recovery' ); ?>
        </span>
    </div>
</div>

<nav class="car-admin-nav">
    <?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no mutation
    $current = sanitize_key( isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : 'fk-cart-recovery' );
    $tabs = [
        'fk-cart-recovery' => __( 'Dashboard', 'fk-cart-recovery' ),
        'car-pro-campaigns'         => __( 'Campaigns', 'fk-cart-recovery' ),
        'car-pro-carts'             => __( 'Carts', 'fk-cart-recovery' ),
        'car-pro-reports'           => __( 'Reports', 'fk-cart-recovery' ),
        'car-pro-settings'          => __( 'Settings', 'fk-cart-recovery' ),
    ];
    foreach ( $tabs as $slug => $label ) :
        $active = $current === $slug ? 'car-nav-link--active' : '';
    ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"
       class="car-nav-link <?php echo esc_attr( $active ); ?>"><?php echo esc_html( $label ); ?></a>
    <?php endforeach; ?>
</nav>
