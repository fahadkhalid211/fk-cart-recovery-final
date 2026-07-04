<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only date filter params, no data mutation
defined( 'ABSPATH' ) || exit;

$from = sanitize_text_field( isset( $_GET['date_from'] ) ? wp_unslash( $_GET['date_from'] ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
$to   = sanitize_text_field( isset( $_GET['date_to'] )   ? wp_unslash( $_GET['date_to'] )   : gmdate( 'Y-m-d' ) );
// phpcs:enable

$stats       = CAR_Analytics::summary( $from, $to );
$products    = CAR_Analytics::products( 15 );
$rate        = CAR_Analytics::recovery_rate( $from, $to );
$currency    = get_woocommerce_currency_symbol();
$email_stats = CAR_Analytics::campaign_performance( $from, $to );

$export_url = wp_nonce_url(
    add_query_arg(
        [
            'action'    => 'car_export_report_csv',
            'date_from' => $from,
            'date_to'   => $to,
        ],
        admin_url( 'admin-post.php' )
    ),
    'car_export_report_csv'
);
?>
<div class="wrap car-admin-wrap">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <!-- Date Filter -->
    <div class="car-card car-filter-card">
        <form method="get" class="car-date-form">
            <input type="hidden" name="page" value="car-pro-reports" />
            <label><?php esc_html_e( 'From:', 'fk-cart-recovery' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( $from ); ?>" class="car-input car-input-sm" /></label>
            <label><?php esc_html_e( 'To:', 'fk-cart-recovery' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( $to ); ?>" class="car-input car-input-sm" /></label>
            <button class="car-btn car-btn-sm"><?php esc_html_e( 'Apply', 'fk-cart-recovery' ); ?></button>
            <a href="<?php echo esc_url( $export_url ); ?>" class="car-btn car-btn-sm car-btn-outline" style="margin-left:auto;">
                <span class="dashicons dashicons-download" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span>
                <?php esc_html_e( 'Export CSV', 'fk-cart-recovery' ); ?>
            </a>
        </form>
    </div>

    <!-- Summary Stats -->
    <div class="car-kpi-grid">
        <div class="car-kpi-card car-kpi--blue">
            <div class="car-kpi-value"><?php echo esc_html( $stats['total_abandoned'] ); ?></div>
            <div class="car-kpi-label"><?php esc_html_e( 'Total Abandoned', 'fk-cart-recovery' ); ?></div>
        </div>
        <div class="car-kpi-card car-kpi--green">
            <div class="car-kpi-value"><?php echo esc_html( $stats['total_recovered'] ); ?></div>
            <div class="car-kpi-label"><?php esc_html_e( 'Recovered', 'fk-cart-recovery' ); ?></div>
        </div>
        <div class="car-kpi-card car-kpi--orange">
            <div class="car-kpi-value"><?php echo esc_html( $rate ); ?>%</div>
            <div class="car-kpi-label"><?php esc_html_e( 'Recovery Rate', 'fk-cart-recovery' ); ?></div>
        </div>
        <div class="car-kpi-card car-kpi--purple">
            <div class="car-kpi-value"><?php echo esc_html( $currency . number_format( (float) $stats['abandoned_value'], 2 ) ); ?></div>
            <div class="car-kpi-label"><?php esc_html_e( 'Abandoned Revenue', 'fk-cart-recovery' ); ?></div>
        </div>
        <div class="car-kpi-card car-kpi--teal">
            <div class="car-kpi-value"><?php echo esc_html( $currency . number_format( (float) $stats['recovered_value'], 2 ) ); ?></div>
            <div class="car-kpi-label"><?php esc_html_e( 'Recovered Revenue', 'fk-cart-recovery' ); ?></div>
        </div>
    </div>

    <!-- Trend + Channel Charts -->
    <div class="car-chart-row car-chart-row--split">
        <div class="car-card car-chart-card">
            <div class="car-card-header">
                <h3><?php esc_html_e( 'Abandoned vs Recovered (Selected Range)', 'fk-cart-recovery' ); ?></h3>
            </div>
            <div class="car-chart-canvas-wrap">
                <canvas id="carReportTrendChart"></canvas>
            </div>
        </div>

        <div class="car-card car-chart-card">
            <div class="car-card-header">
                <h3><?php esc_html_e( 'Recovery Messages by Channel', 'fk-cart-recovery' ); ?></h3>
            </div>
            <div class="car-chart-canvas-wrap">
                <canvas id="carReportChannelChart"></canvas>
            </div>
            <p id="carReportChannelEmpty" class="car-empty" style="display:none;"><?php esc_html_e( 'No channel data for this range yet.', 'fk-cart-recovery' ); ?></p>
        </div>
    </div>

    <div class="car-reports-row">
        <!-- Campaign Email Performance -->
        <div class="car-card car-card-full">
            <div class="car-card-header"><h3><?php esc_html_e( 'Campaign Performance', 'fk-cart-recovery' ); ?></h3></div>
            <?php if ( $email_stats ) : ?>
                <table class="car-table">
                    <thead><tr>
                        <th><?php esc_html_e( 'Campaign', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Channel', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Sent', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Open Rate', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Click Rate', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Unsubscribes', 'fk-cart-recovery' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $email_stats as $row ) :
                        $open_rate  = $row->sent ? round( $row->opened  / $row->sent * 100, 1 ) : 0;
                        $click_rate = $row->sent ? round( $row->clicked / $row->sent * 100, 1 ) : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $row->name ); ?></strong></td>
                            <td><span class="car-channel-badge car-channel--<?php echo esc_attr( $row->channel ); ?>"><?php echo esc_html( ucfirst( $row->channel ) ); ?></span></td>
                            <td><?php echo esc_html( $row->sent ); ?></td>
                            <td>
                                <div class="car-progress-bar"><div class="car-progress-fill" style="width:<?php echo esc_attr( min( 100, $open_rate ) ); ?>%"></div></div>
                                <?php echo esc_html( $open_rate ); ?>%
                            </td>
                            <td>
                                <div class="car-progress-bar"><div class="car-progress-fill car-progress-fill--green" style="width:<?php echo esc_attr( min( 100, $click_rate ) ); ?>%"></div></div>
                                <?php echo esc_html( $click_rate ); ?>%
                            </td>
                            <td><?php echo esc_html( $row->unsubscribed ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="car-empty"><?php esc_html_e( 'No email data yet.', 'fk-cart-recovery' ); ?></p>
            <?php endif; ?>
        </div>

        <!-- Product Stats -->
        <div class="car-card car-card-full">
            <div class="car-card-header"><h3><?php esc_html_e( 'Product-Level Abandonment', 'fk-cart-recovery' ); ?></h3></div>
            <?php if ( $products ) : ?>
                <table class="car-table">
                    <thead><tr>
                        <th><?php esc_html_e( 'Product', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Abandoned', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Recovered', 'fk-cart-recovery' ); ?></th>
                        <th><?php esc_html_e( 'Recovery Rate', 'fk-cart-recovery' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $products as $p ) :
                        $pr = ( $p['abandoned'] + $p['recovered'] ) > 0 ? round( $p['recovered'] / ( $p['abandoned'] + $p['recovered'] ) * 100, 1 ) : 0;
                    ?>
                        <tr>
                            <td><?php echo esc_html( $p['name'] ?: '#' . $p['id'] ); ?></td>
                            <td><?php echo esc_html( $p['abandoned'] ); ?></td>
                            <td><?php echo esc_html( $p['recovered'] ); ?></td>
                            <td>
                                <div class="car-progress-bar"><div class="car-progress-fill car-progress-fill--blue" style="width:<?php echo esc_attr( min( 100, $pr ) ); ?>%"></div></div>
                                <?php echo esc_html( $pr ); ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="car-empty"><?php esc_html_e( 'No product data yet.', 'fk-cart-recovery' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    var CAR_CHANNEL_COLORS = {
        email:    '#3498db',
        whatsapp: '#25D366',
        sms:      '#f39c12',
        telegram: '#0088cc'
    };
    var CAR_CHANNEL_LABELS = {
        email:    <?php echo wp_json_encode( __( 'Email', 'fk-cart-recovery' ) ); ?>,
        whatsapp: <?php echo wp_json_encode( __( 'WhatsApp', 'fk-cart-recovery' ) ); ?>,
        sms:      <?php echo wp_json_encode( __( 'SMS', 'fk-cart-recovery' ) ); ?>,
        telegram: <?php echo wp_json_encode( __( 'Telegram', 'fk-cart-recovery' ) ); ?>
    };

    $.post( carAdmin.ajaxUrl, {
        action: 'car_get_report_chart_data',
        nonce:  carAdmin.nonce,
        from:   <?php echo wp_json_encode( $from ); ?>,
        to:     <?php echo wp_json_encode( $to ); ?>
    }, function( res ) {
        if ( ! res.success ) return;

        // Trend line chart
        var trend    = res.data.trend || [];
        var labels   = trend.map(function(d){ return d.date; });
        var abandoned = trend.map(function(d){ return d.abandoned; });
        var recovered = trend.map(function(d){ return d.recovered; });

        new Chart( document.getElementById('carReportTrendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: <?php echo wp_json_encode( __( 'Abandoned', 'fk-cart-recovery' ) ); ?>,
                        data: abandoned,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231,76,60,0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: <?php echo wp_json_encode( __( 'Recovered', 'fk-cart-recovery' ) ); ?>,
                        data: recovered,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39,174,96,0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // Channel breakdown doughnut
        var channels = res.data.channels || [];
        if ( ! channels.length ) {
            $('#carReportChannelChart').hide();
            $('#carReportChannelEmpty').show();
            return;
        }

        new Chart( document.getElementById('carReportChannelChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: channels.map(function(c){ return CAR_CHANNEL_LABELS[c.channel] || c.channel; }),
                datasets: [{
                    data: channels.map(function(c){ return c.sent; }),
                    backgroundColor: channels.map(function(c){ return CAR_CHANNEL_COLORS[c.channel] || '#94a3b8'; })
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    });
});
</script>