<?php 
defined( 'ABSPATH' ) || exit;

$stats         = CAR_Analytics::summary();
$recovery_rate = CAR_Analytics::recovery_rate();
$currency      = get_woocommerce_currency_symbol();
?>
<div class="wrap car-admin-wrap">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="car-dashboard">
        <!-- KPI Cards -->
        <div class="car-kpi-grid">
            <div class="car-kpi-card car-kpi--blue">
                <div class="car-kpi-icon"><span class="dashicons dashicons-cart"></span></div>
                <div class="car-kpi-content">
                    <div class="car-kpi-value"><?php echo esc_html( $stats['total_abandoned'] ); ?></div>
                    <div class="car-kpi-label"><?php esc_html_e( 'Abandoned Carts', 'fk-cart-recovery' ); ?></div>
                </div>
            </div>
            <div class="car-kpi-card car-kpi--green">
                <div class="car-kpi-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                <div class="car-kpi-content">
                    <div class="car-kpi-value"><?php echo esc_html( $stats['total_recovered'] ); ?></div>
                    <div class="car-kpi-label"><?php esc_html_e( 'Recovered Carts', 'fk-cart-recovery' ); ?></div>
                </div>
            </div>
            <div class="car-kpi-card car-kpi--orange">
                <div class="car-kpi-icon"><span class="dashicons dashicons-chart-line"></span></div>
                <div class="car-kpi-content">
                    <div class="car-kpi-value"><?php echo esc_html( $recovery_rate ); ?>%</div>
                    <div class="car-kpi-label"><?php esc_html_e( 'Recovery Rate', 'fk-cart-recovery' ); ?></div>
                </div>
            </div>
            <div class="car-kpi-card car-kpi--purple">
                <div class="car-kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="car-kpi-content">
                    <div class="car-kpi-value"><?php echo esc_html( $currency . number_format( $stats['recovered_value'], 2 ) ); ?></div>
                    <div class="car-kpi-label"><?php esc_html_e( 'Revenue Recovered', 'fk-cart-recovery' ); ?></div>
                </div>
            </div>
            <div class="car-kpi-card car-kpi--teal">
                <div class="car-kpi-icon"><span class="dashicons dashicons-email-alt"></span></div>
                <div class="car-kpi-content">
                    <div class="car-kpi-value"><?php echo esc_html( $stats['emails_sent'] ); ?></div>
                    <div class="car-kpi-label"><?php esc_html_e( 'Emails Sent', 'fk-cart-recovery' ); ?></div>
                </div>
            </div>
            <div class="car-kpi-card car-kpi--red">
                <div class="car-kpi-icon"><span class="dashicons dashicons-visibility"></span></div>
                <div class="car-kpi-content">
                    <div class="car-kpi-value"><?php echo esc_html( $stats["emails_sent"] ? round( $stats['emails_opened'] / $stats['emails_sent'] * 100, 1 ) : 0 ); ?>%</div>
                    <div class="car-kpi-label"><?php esc_html_e( 'Email Open Rate', 'fk-cart-recovery' ); ?></div>
                </div>
            </div>
        </div>

        <!-- Chart Row -->
        <div class="car-chart-row">
            <div class="car-card car-chart-card">
                <div class="car-card-header">
                    <h3><?php esc_html_e( 'Abandoned vs Recovered (Last 30 Days)', 'fk-cart-recovery' ); ?></h3>
                    <select id="car-chart-days" class="car-select-sm">
                        <option value="7"><?php esc_html_e( '7 Days', 'fk-cart-recovery' ); ?></option>
                        <option value="30" selected><?php esc_html_e( '30 Days', 'fk-cart-recovery' ); ?></option>
                        <option value="90"><?php esc_html_e( '90 Days', 'fk-cart-recovery' ); ?></option>
                    </select>
                </div>
                <div class="car-chart-canvas-wrap">
                    <canvas id="carMainChart"></canvas>
                </div>
            </div>

            <div class="car-card car-quick-actions">
                <div class="car-card-header"><h3><?php esc_html_e( 'Quick Actions', 'fk-cart-recovery' ); ?></h3></div>
                <ul class="car-action-list">
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=car-pro-campaigns' ) ); ?>" class="car-action-link"><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Manage Campaigns', 'fk-cart-recovery' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=car-pro-carts' ) ); ?>" class="car-action-link"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'View Abandoned Carts', 'fk-cart-recovery' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=car-pro-reports' ) ); ?>" class="car-action-link"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'View Reports', 'fk-cart-recovery' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=car-pro-settings' ) ); ?>" class="car-action-link"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'fk-cart-recovery' ); ?></a></li>
                </ul>
            </div>
        </div>

        <!-- Recent Carts Table -->
        <div class="car-card">
            <div class="car-card-header">
                <h3><?php esc_html_e( 'Recent Abandoned Carts', 'fk-cart-recovery' ); ?></h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=car-pro-carts' ) ); ?>" class="car-btn car-btn-sm"><?php esc_html_e( 'View All', 'fk-cart-recovery' ); ?></a>
            </div>
            <?php
            $recent = CAR_DB::get_carts( [ 'status' => 'abandoned', 'per_page' => 5 ] );
            if ( $recent ) : ?>
                <table class="car-table">
                    <thead><tr>
                        <th><?php esc_html_e( 'Customer', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Time', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'fk-cart-recovery' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $recent as $cart ) : ?>
                        <tr>
                            <td><?php echo esc_html( trim( $cart->first_name . ' ' . $cart->last_name ) ?: '—' ); ?></td>
                            <td><?php echo esc_html( $cart->email ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( $cart->cart_total ) ); ?></td>
                            <!-- FIXED: Replaced deprecated current_time('timestamp') with time(), and made 'ago' translatable -->
                            <td><?php echo esc_html( sprintf( /* translators: %s: human time diff */ __( '%s ago', 'fk-cart-recovery' ), human_time_diff( strtotime( $cart->abandoned_at ), time() ) ) ); ?></td>
                            <td><span class="car-badge car-badge--<?php echo esc_attr( $cart->status ); ?>"><?php echo esc_html( ucfirst( $cart->status ) ); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="car-empty"><?php esc_html_e( 'No abandoned carts yet.', 'fk-cart-recovery' ); ?></p>
            <?php endif; ?>
        </div>
    </div><!-- .car-dashboard -->
</div><!-- .wrap -->

<script>
jQuery(function($){
    var chartData = null;

    function loadChart(days) {
        $.post(carAdmin.ajaxUrl, { action:'car_get_chart_data', nonce:carAdmin.nonce, days:days }, function(res){
            if(!res.success) return;

            var labels = res.data.map(d=>d.date);
            var abandoned = res.data.map(d=>d.abandoned);
            var recovered = res.data.map(d=>d.recovered);

            if(chartData) { chartData.destroy(); }

            var ctx = document.getElementById('carMainChart').getContext('2d');
            chartData = new Chart(ctx,{
                type:'line',
                data:{
                    labels:labels,
                    datasets:[
                        // FIXED: Removed the duplicate "Abandoned" string that was causing "AbandonedAbandoned"
                        {
                            label:'<?php echo esc_js( __( 'Abandoned', 'fk-cart-recovery' ) ); ?>',
                            data:abandoned,
                            borderColor:'#e74c3c',
                            backgroundColor:'rgba(231,76,60,0.1)',
                            tension:0.4,
                            fill:true
                        },
                        {
                            label:'<?php echo esc_js( __( 'Recovered', 'fk-cart-recovery' ) ); ?>',
                            data:recovered,
                            borderColor:'#27ae60',
                            backgroundColor:'rgba(39,174,96,0.1)',
                            tension:0.4,
                            fill:true
                        }
                    ]
                },
                options:{
                    responsive:true,
                    maintainAspectRatio:false,
                    plugins:{legend:{position:'top'}},
                    scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}
                }
            });
        });
    }

    loadChart(30);
    $('#car-chart-days').on('change',function(){ loadChart($(this).val()); });
});
</script>