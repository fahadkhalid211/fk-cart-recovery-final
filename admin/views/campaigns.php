<?php defined( 'ABSPATH' ) || exit;
$campaigns   = CAR_DB::get_campaigns();
$rule_types  = CAR_Rule_Engine::get_rule_types();
$operators   = CAR_Rule_Engine::get_operators();
$actions     = CAR_Rule_Engine::get_actions();
?>
<div class="wrap car-admin-wrap">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="car-campaigns-page">
        <div class="car-page-toolbar">
            <h2><?php esc_html_e( 'Recovery Campaigns', 'fk-cart-recovery' ); ?></h2>
            <button class="car-btn car-btn-primary" id="car-add-campaign"><?php esc_html_e( '+ New Campaign', 'fk-cart-recovery' ); ?></button>
        </div>

        <div class="car-card">
            <?php if ( $campaigns ) : ?>
            <table class="car-table car-table-campaigns">
                <thead><tr>
                    <th><?php esc_html_e( 'Name', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Channel', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Send After', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Coupon', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'fk-cart-recovery' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'fk-cart-recovery' ); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ( $campaigns as $c ) : ?>
                <tr data-id="<?php echo esc_attr( $c->id ); ?>">
                    <td><strong><?php echo esc_html( $c->name ); ?></strong></td>
                    <td><span class="car-channel-badge car-channel--<?php echo esc_attr( $c->channel ); ?>"><?php echo esc_html( ucfirst( $c->channel ) ); ?></span></td>
                    <td><?php echo esc_html( car_minutes_to_human( $c->send_after_minutes ) ); ?></td>
                    <td><?php echo $c->coupon_enabled ? '<span class="car-badge car-badge--green">✓ ' . esc_html( $c->coupon_amount ) . ( $c->coupon_type === 'percent' ? '%' : esc_html( get_woocommerce_currency_symbol() ) ) . '</span>' : '<span class="car-badge car-badge--gray">&mdash;</span>'; ?></td>
                    <td>
                        <label class="car-toggle">
                            <input type="checkbox" class="car-toggle-status" data-id="<?php echo esc_attr( $c->id ); ?>" <?php checked( $c->status, 'active' ); ?> />
                            <span class="car-toggle-slider"></span>
                        </label>
                    </td>
                    <td>
                        <button class="car-btn car-btn-sm car-edit-campaign" data-id="<?php echo esc_attr( $c->id ); ?>"><?php esc_html_e( 'Edit', 'fk-cart-recovery' ); ?></button>
                        <button class="car-btn car-btn-sm car-btn-danger car-delete-campaign" data-id="<?php echo esc_attr( $c->id ); ?>"><?php esc_html_e( 'Delete', 'fk-cart-recovery' ); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div class="car-empty-state">
                <span class="dashicons dashicons-email-alt car-empty-icon"></span>
                <h3><?php esc_html_e( 'No campaigns yet', 'fk-cart-recovery' ); ?></h3>
                <p><?php esc_html_e( 'Create your first recovery campaign to start recapturing lost sales.', 'fk-cart-recovery' ); ?></p>
                <button class="car-btn car-btn-primary" id="car-add-campaign-empty"><?php esc_html_e( '+ Create Campaign', 'fk-cart-recovery' ); ?></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Campaign Modal -->
<div id="car-campaign-modal" class="car-modal" style="display:none;">
    <div class="car-modal-overlay"></div>
    <div class="car-modal-content car-modal-lg">
        <div class="car-modal-header">
            <h3 id="car-modal-title"><?php esc_html_e( 'New Campaign', 'fk-cart-recovery' ); ?></h3>
            <button class="car-modal-close">&times;</button>
        </div>
        <div class="car-modal-body">
            <input type="hidden" id="car_campaign_id" value="" />

            <div class="car-tabs">
                <button class="car-tab-btn car-tab-btn--active" data-tab="general"><?php esc_html_e( 'General', 'fk-cart-recovery' ); ?></button>
                <button class="car-tab-btn" data-tab="message"><?php esc_html_e( 'Message', 'fk-cart-recovery' ); ?></button>
                <button class="car-tab-btn" data-tab="coupon"><?php esc_html_e( 'Coupon', 'fk-cart-recovery' ); ?></button>
                <button class="car-tab-btn" data-tab="rules"><?php esc_html_e( 'Rules', 'fk-cart-recovery' ); ?></button>
            </div>

            <!-- General Tab -->
            <div class="car-tab-panel car-tab-panel--active" data-panel="general">
                <div class="car-form-row">
                    <label><?php esc_html_e( 'Campaign Name', 'fk-cart-recovery' ); ?></label>
                    <input type="text" id="car_campaign_name" class="car-input" placeholder="e.g. First Reminder" />
                </div>
                <div class="car-form-row">
                    <label><?php esc_html_e( 'Channel', 'fk-cart-recovery' ); ?></label>
                    <select id="car_campaign_channel" class="car-select">
                        <option value="email">📧 <?php esc_html_e( 'Email', 'fk-cart-recovery' ); ?></option>
                        <option value="whatsapp">💬 <?php esc_html_e( 'WhatsApp', 'fk-cart-recovery' ); ?></option>
                        <option value="sms">📱 <?php esc_html_e( 'SMS', 'fk-cart-recovery' ); ?></option>
                        <option value="telegram">✈️ <?php esc_html_e( 'Telegram', 'fk-cart-recovery' ); ?></option>
                    </select>
                </div>
                <div class="car-form-row car-form-row--inline">
                    <label><?php esc_html_e( 'Send After', 'fk-cart-recovery' ); ?></label>
                    <input type="number" id="car_send_after" class="car-input car-input-sm" value="60" min="1" />
                    <span><?php esc_html_e( 'minutes after abandonment', 'fk-cart-recovery' ); ?></span>
                    <select id="car_send_after_preset" class="car-select-sm">
                        <option value="15">15 min</option>
                        <option value="60">1 hour</option>
                        <option value="360">6 hours</option>
                        <option value="720">12 hours</option>
                        <option value="1440">24 hours</option>
                        <option value="4320">3 days</option>
                    </select>
                </div>
                <div class="car-form-row">
                    <label><?php esc_html_e( 'Status', 'fk-cart-recovery' ); ?></label>
                    <select id="car_campaign_status" class="car-select">
                        <option value="active"><?php esc_html_e( 'Active', 'fk-cart-recovery' ); ?></option>
                        <option value="paused"><?php esc_html_e( 'Paused', 'fk-cart-recovery' ); ?></option>
                    </select>
                </div>
                <div class="car-form-row car-field-email-only">
                    <label><?php esc_html_e( 'Sort Order', 'fk-cart-recovery' ); ?></label>
                    <input type="number" id="car_sort_order" class="car-input car-input-sm" value="0" min="0" />
                </div>
            </div>

            <!-- Message Tab -->
            <div class="car-tab-panel" data-panel="message">
                <div class="car-form-row car-field-email-only">
                    <label><?php esc_html_e( 'Email Subject', 'fk-cart-recovery' ); ?></label>
                    <input type="text" id="car_subject" class="car-input" placeholder="Hey {customer_name}, you left something behind!" />
                    <div class="car-field-hint"><?php esc_html_e( 'Shortcodes: {customer_name} {cart_total} {coupon_code} {discount} {site_name}', 'fk-cart-recovery' ); ?></div>
                </div>
                <div class="car-form-row">
                    <label><?php esc_html_e( 'Message Body', 'fk-cart-recovery' ); ?></label>
                    <textarea id="car_body" class="car-textarea" rows="12"></textarea>
                    <div class="car-field-hint"><?php esc_html_e( 'Available shortcodes: {customer_name} {customer_email} {cart_total} {cart_items_table} {recovery_link} {coupon_code} {coupon_amount} {coupon_expiry} {site_name} {unsubscribe_link}', 'fk-cart-recovery' ); ?></div>
                </div>
            </div>

            <!-- Coupon Tab -->
            <div class="car-tab-panel" data-panel="coupon">
                <div class="car-form-row">
                    <label class="car-checkbox-label">
                        <input type="checkbox" id="car_coupon_enabled" />
                        <?php esc_html_e( 'Include Unique Coupon Code', 'fk-cart-recovery' ); ?>
                    </label>
                </div>
                <div id="car-coupon-fields" style="display:none;">
                    <div class="car-form-row car-form-row--inline">
                        <label><?php esc_html_e( 'Discount Type', 'fk-cart-recovery' ); ?></label>
                        <select id="car_coupon_type" class="car-select">
                            <option value="percent"><?php esc_html_e( 'Percentage (%)', 'fk-cart-recovery' ); ?></option>
                            <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'fk-cart-recovery' ); ?></option>
                        </select>
                    </div>
                    <div class="car-form-row car-form-row--inline">
                        <label><?php esc_html_e( 'Discount Amount', 'fk-cart-recovery' ); ?></label>
                        <input type="number" id="car_coupon_amount" class="car-input car-input-sm" value="10" min="1" />
                    </div>
                    <div class="car-form-row car-form-row--inline">
                        <label><?php esc_html_e( 'Expires After (days)', 'fk-cart-recovery' ); ?></label>
                        <input type="number" id="car_coupon_expiry" class="car-input car-input-sm" value="3" min="1" />
                    </div>
                    <div class="car-notice car-notice--info">
                        <p><?php esc_html_e( 'A unique coupon code will be auto-generated per cart. Add {coupon_code} to your email body to display it.', 'fk-cart-recovery' ); ?></p>
                    </div>
                </div>
            </div>

            <!-- Rules Tab -->
            <div class="car-tab-panel" data-panel="rules">
                <div class="car-notice car-notice--info">
                    <p><?php esc_html_e( 'Rules let you control when this campaign fires. All rules are evaluated in order.', 'fk-cart-recovery' ); ?></p>
                </div>
                <div id="car-rules-list"></div>
                <button type="button" class="car-btn car-btn-sm" id="car-add-rule"><?php esc_html_e( '+ Add Rule', 'fk-cart-recovery' ); ?></button>

                <!-- Rule template (hidden) -->
                <script type="text/html" id="car-rule-tpl">
                <div class="car-rule-row">
                    <select class="car-select-sm car-rule-type" name="rules[__i__][rule_type]">
                        <?php foreach ( $rule_types as $k => $v ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="car-select-sm car-rule-operator" name="rules[__i__][operator]">
                        <?php foreach ( $operators as $k => $v ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="car-input car-input-sm car-rule-value" name="rules[__i__][rule_value]" placeholder="Value" />
                    <select class="car-select-sm car-rule-action" name="rules[__i__][action]">
                        <?php foreach ( $actions as $k => $v ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="car-btn car-btn-sm car-btn-danger car-remove-rule">✕</button>
                </div>
                </script>
            </div>
        </div>
        <div class="car-modal-footer">
            <button class="car-btn car-btn-secondary car-modal-close"><?php esc_html_e( 'Cancel', 'fk-cart-recovery' ); ?></button>
            <button class="car-btn car-btn-primary" id="car-save-campaign"><?php esc_html_e( 'Save Campaign', 'fk-cart-recovery' ); ?></button>
        </div>
    </div>
</div>
