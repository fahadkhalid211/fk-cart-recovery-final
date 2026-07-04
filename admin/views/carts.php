<?php 
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter params, no data mutation
$status  = sanitize_key( isset( $_GET['status'] ) ? wp_unslash( $_GET['status'] ) : '' );
$search  = sanitize_text_field( isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '' );
$paged   = max( 1, absint( isset( $_GET['paged'] ) ? wp_unslash( $_GET['paged'] ) : 1 ) );
// phpcs:enable

$per     = 20;
$carts   = CAR_DB::get_carts( [ 'status' => $status, 'search' => $search, 'per_page' => $per, 'page' => $paged ] );
$total   = CAR_DB::count_carts( [ 'status' => $status ] );
$pages   = (int) ceil( $total / $per );
?>
<div class="wrap car-admin-wrap">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="car-page-toolbar">
        <h2><?php esc_html_e( 'Abandoned Carts', 'fk-cart-recovery' ); ?></h2>
        <form method="get" class="car-search-form">
            <input type="hidden" name="page" value="car-pro-carts" />
            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search email, name…', 'fk-cart-recovery' ); ?>" class="car-input" />
            <select name="status" class="car-select-sm">
                <option value=""><?php esc_html_e( 'All Statuses', 'fk-cart-recovery' ); ?></option>
                <option value="pending"   <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'fk-cart-recovery' ); ?></option>
                <option value="abandoned" <?php selected( $status, 'abandoned' ); ?>><?php esc_html_e( 'Abandoned', 'fk-cart-recovery' ); ?></option>
                <option value="recovered" <?php selected( $status, 'recovered' ); ?>><?php esc_html_e( 'Recovered', 'fk-cart-recovery' ); ?></option>
            </select>
            <button type="submit" class="car-btn car-btn-sm"><?php esc_html_e( 'Filter', 'fk-cart-recovery' ); ?></button>
        </form>
    </div>

    <div class="car-card">
        <table class="car-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Customer', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Cart Total', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Abandoned At', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'fk-cart-recovery' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $carts ) ) : ?>
                <tr><td colspan="5" class="car-empty"><?php esc_html_e( 'No carts found.', 'fk-cart-recovery' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $carts as $cart ) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( trim( $cart->first_name . ' ' . $cart->last_name ) ?: __( 'Guest', 'fk-cart-recovery' ) ); ?></strong><br>
                            <span class="car-text-muted car-text-sm"><?php echo esc_html( $cart->email ); ?></span>
                        </td>
                        <td><?php echo wp_kses_post( wc_price( $cart->cart_total ) ); ?></td>
                        <td><span class="car-badge car-badge--<?php echo esc_attr( $cart->status ); ?>"><?php echo esc_html( ucfirst( $cart->status ) ); ?></span></td>
                        <td><?php echo $cart->abandoned_at ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $cart->abandoned_at ) ) ) : '—'; ?></td>
                        <td>
                            <!-- FIXED: Replaced PHP link generation with an AJAX button to prevent transient database pollution -->
                            <button class="car-btn car-btn-sm car-get-recovery-link" data-id="<?php echo esc_attr( $cart->id ); ?>" title="<?php esc_attr_e( 'Copy Recovery Link', 'fk-cart-recovery' ); ?>">🔗</button>
                            <button class="car-btn car-btn-sm car-btn-danger car-delete-cart" data-id="<?php echo esc_attr( $cart->id ); ?>">🗑</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="car-pagination">
                <?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $p ) ); ?>" class="car-page-link <?php echo $p === $paged ? 'car-page-link--active' : ''; ?>"><?php echo esc_html( $p ); ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- FIXED: Inline script to handle on-demand recovery link generation -->
<script>
jQuery(function($) {
    $('.car-get-recovery-link').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var id  = btn.data('id');
        
        btn.prop('disabled', true).text('...');
        
        $.post(carAdmin.ajaxUrl, {
            action: 'car_get_recovery_link',
            nonce:  carAdmin.nonce,
            id:     id
        }, function(res) {
            btn.prop('disabled', false).text('🔗');
            if(res.success && res.data.url) {
                // Prompt the user to copy the link
                prompt('<?php echo esc_js( __( 'Copy this recovery link:', 'fk-cart-recovery' ) ); ?>', res.data.url);
            } else {
                alert('<?php echo esc_js( __( 'Could not generate link.', 'fk-cart-recovery' ) ); ?>');
            }
        });
    });
});
</script>