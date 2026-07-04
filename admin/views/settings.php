<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap car-admin-wrap">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="car-settings-page">

        <!-- Sidebar Tabs -->
        <div class="car-settings-tabs">
            <button class="car-stab-btn car-stab-btn--active" data-stab="general">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e( 'General', 'fk-cart-recovery' ); ?>
            </button>
            <button class="car-stab-btn" data-stab="email">
                <span class="dashicons dashicons-email-alt"></span>
                <?php esc_html_e( 'Email', 'fk-cart-recovery' ); ?>
            </button>
            <button class="car-stab-btn" data-stab="whatsapp">
                <span class="dashicons dashicons-format-chat"></span>
                <?php esc_html_e( 'WhatsApp', 'fk-cart-recovery' ); ?>
            </button>
            <button class="car-stab-btn" data-stab="sms">
                <span class="dashicons dashicons-smartphone"></span>
                <?php esc_html_e( 'SMS', 'fk-cart-recovery' ); ?>
            </button>
            <button class="car-stab-btn" data-stab="telegram">
                <span class="dashicons dashicons-share"></span>
                <?php esc_html_e( 'Telegram', 'fk-cart-recovery' ); ?>
            </button>
            <button class="car-stab-btn" data-stab="gdpr">
                <span class="dashicons dashicons-shield"></span>
                <?php esc_html_e( 'GDPR', 'fk-cart-recovery' ); ?>
            </button>
            <button class="car-stab-btn" data-stab="notifications">
                <span class="dashicons dashicons-bell"></span>
                <?php esc_html_e( 'Notifications', 'fk-cart-recovery' ); ?>
            </button>
        </div>

        <!-- Content Area -->
        <div class="car-settings-content">

            <!-- ── General ─────────────────────────────────────────────── -->
            <div class="car-stab-panel car-stab-panel--active" data-spanel="general">
                <div class="car-card">
                    <div class="car-card-header">
                        <h3><?php esc_html_e( 'General Settings', 'fk-cart-recovery' ); ?></h3>
                    </div>
                    <div class="car-card-body">

                        <div class="car-form-row car-form-row--inline">
                            <label for="car_enabled"><?php esc_html_e( 'Enable Plugin', 'fk-cart-recovery' ); ?></label>
                            <label class="car-toggle">
                                <input type="checkbox" id="car_enabled" name="car_enabled" <?php checked( get_option( 'car_enabled', 'yes' ), 'yes' ); ?> value="yes">
                                <span class="car-toggle-slider"></span>
                            </label>
                            <span class="car-field-hint"><?php esc_html_e( 'Turn off to pause all recovery campaigns.', 'fk-cart-recovery' ); ?></span>
                        </div>

                        <div class="car-form-row">
                            <label for="car_cutoff_time"><?php esc_html_e( 'Abandonment Cutoff (minutes)', 'fk-cart-recovery' ); ?></label>
                            <input type="number" id="car_cutoff_time" name="car_cutoff_time"
                                   value="<?php echo esc_attr( get_option( 'car_cutoff_time', 60 ) ); ?>"
                                   class="car-input car-input-sm" min="5" max="1440" />
                            <p class="car-field-hint"><?php esc_html_e( 'A cart is marked abandoned after this many minutes of inactivity.', 'fk-cart-recovery' ); ?></p>
                        </div>

                        <div class="car-form-row car-form-row--inline">
                            <label for="car_show_tax"><?php esc_html_e( 'Show Tax in Emails', 'fk-cart-recovery' ); ?></label>
                            <label class="car-toggle">
                                <input type="checkbox" id="car_show_tax" name="car_show_tax" <?php checked( get_option( 'car_show_tax', 'yes' ), 'yes' ); ?> value="yes">
                                <span class="car-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="car-form-row car-form-row--inline">
                            <label for="car_unsubscribe_enabled"><?php esc_html_e( 'Include Unsubscribe Link', 'fk-cart-recovery' ); ?></label>
                            <label class="car-toggle">
                                <input type="checkbox" id="car_unsubscribe_enabled" name="car_unsubscribe_enabled" <?php checked( get_option( 'car_unsubscribe_enabled', 'yes' ), 'yes' ); ?> value="yes">
                                <span class="car-toggle-slider"></span>
                            </label>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── Email ──────────────────────────────────────────────── -->
            <div class="car-stab-panel" data-spanel="email">
                <div class="car-card">
                    <div class="car-card-header">
                        <h3><?php esc_html_e( 'Email Settings', 'fk-cart-recovery' ); ?></h3>
                    </div>
                    <div class="car-card-body">

                        <div class="car-form-row">
                            <label for="car_email_from_name"><?php esc_html_e( 'From Name', 'fk-cart-recovery' ); ?></label>
                            <input type="text" id="car_email_from_name" name="car_email_from_name"
                                   value="<?php echo esc_attr( get_option( 'car_email_from_name', get_bloginfo( 'name' ) ) ); ?>"
                                   class="car-input" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
                        </div>

                        <div class="car-form-row">
                            <label for="car_email_from_address"><?php esc_html_e( 'From Address', 'fk-cart-recovery' ); ?></label>
                            <input type="email" id="car_email_from_address" name="car_email_from_address"
                                   value="<?php echo esc_attr( get_option( 'car_email_from_address', get_option( 'admin_email' ) ) ); ?>"
                                   class="car-input" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
                            <p class="car-field-hint"><?php esc_html_e( 'Recovery emails will be sent from this address.', 'fk-cart-recovery' ); ?></p>
                        </div>

                        <div class="car-notice car-notice--info">
                            <p><?php esc_html_e( 'Make sure your server is configured to send emails. We recommend using an SMTP plugin like WP Mail SMTP for reliable delivery.', 'fk-cart-recovery' ); ?></p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── WhatsApp ───────────────────────────────────────────── -->
            <div class="car-stab-panel" data-spanel="whatsapp">
                <div class="car-card">
                    <div class="car-card-header">
                        <h3><?php esc_html_e( 'WhatsApp Settings', 'fk-cart-recovery' ); ?></h3>
                    </div>
                    <div class="car-card-body">

                        <div class="car-form-row car-form-row--inline">
                            <label for="car_whatsapp_enabled"><?php esc_html_e( 'Enable WhatsApp', 'fk-cart-recovery' ); ?></label>
                            <label class="car-toggle">
                                <input type="checkbox" id="car_whatsapp_enabled" name="car_whatsapp_enabled"
                                    <?php checked( get_option( 'car_whatsapp_enabled', 'no' ), 'yes' ); ?> value="yes">
                                <span class="car-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="car-form-row">
                            <label for="car_whatsapp_provider"><?php esc_html_e( 'Provider', 'fk-cart-recovery' ); ?></label>
                            <select name="car_whatsapp_provider" id="car_whatsapp_provider" class="car-select">
                                <option value="ultramsg"          <?php selected( get_option( 'car_whatsapp_provider', 'ultramsg' ), 'ultramsg' ); ?>>UltraMsg</option>
                                <option value="whatsapp_business" <?php selected( get_option( 'car_whatsapp_provider' ), 'whatsapp_business' ); ?>>WhatsApp Business API (Meta)</option>
                                <option value="twilio_wa"         <?php selected( get_option( 'car_whatsapp_provider' ), 'twilio_wa' ); ?>>Twilio WhatsApp</option>
                            </select>
                        </div>

                        <div id="car-wa-ultramsg" class="car-provider-fields car-provider-section">
                            <div class="car-notice car-notice--info"><p><?php esc_html_e( 'Get your Instance ID and Token from app.ultramsg.com', 'fk-cart-recovery' ); ?></p></div>
                            <div class="car-form-row">
                                <label for="car_ultramsg_instance"><?php esc_html_e( 'Instance ID', 'fk-cart-recovery' ); ?></label>
                                <input type="text" id="car_ultramsg_instance" name="car_ultramsg_instance"
                                       value="<?php echo esc_attr( get_option( 'car_ultramsg_instance' ) ); ?>"
                                       class="car-input" placeholder="instance123" />
                            </div>
                            <div class="car-form-row">
                                <label for="car_ultramsg_token"><?php esc_html_e( 'Token', 'fk-cart-recovery' ); ?></label>
                                <input type="password" id="car_ultramsg_token" name="car_ultramsg_token"
                                       value="<?php echo esc_attr( get_option( 'car_ultramsg_token' ) ); ?>"
                                       class="car-input" autocomplete="new-password" />
                            </div>
                            <button type="button" class="car-btn car-btn-sm" id="car-test-wa"><?php esc_html_e( 'Test Connection', 'fk-cart-recovery' ); ?></button>
                            <span id="car-wa-test-result" class="car-inline-result"></span>
                        </div>

                        <div id="car-wa-business" class="car-provider-fields car-provider-section" style="display:none;">
                            <div class="car-notice car-notice--info"><p><?php esc_html_e( 'Use your Meta Business Manager access token and Phone Number ID.', 'fk-cart-recovery' ); ?></p></div>
                            <div class="car-form-row">
                                <label for="car_whatsapp_business_token"><?php esc_html_e( 'Access Token', 'fk-cart-recovery' ); ?></label>
                                <input type="password" id="car_whatsapp_business_token" name="car_whatsapp_business_token"
                                       value="<?php echo esc_attr( get_option( 'car_whatsapp_business_token' ) ); ?>"
                                       class="car-input" autocomplete="new-password" />
                            </div>
                            <div class="car-form-row">
                                <label for="car_whatsapp_phone_id"><?php esc_html_e( 'Phone Number ID', 'fk-cart-recovery' ); ?></label>
                                <input type="text" id="car_whatsapp_phone_id" name="car_whatsapp_phone_id"
                                       value="<?php echo esc_attr( get_option( 'car_whatsapp_phone_id' ) ); ?>"
                                       class="car-input" />
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── SMS ───────────────────────────────────────────────── -->
            <div class="car-stab-panel" data-spanel="sms">
                <div class="car-card">
                    <div class="car-card-header">
                        <h3><?php esc_html_e( 'SMS Settings', 'fk-cart-recovery' ); ?></h3>
                    </div>
                    <div class="car-card-body">

                        <div class="car-form-row car-form-row--inline">
                            <label for="car_sms_enabled"><?php esc_html_e( 'Enable SMS', 'fk-cart-recovery' ); ?></label>
                            <label class="car-toggle">
                                <input type="checkbox" id="car_sms_enabled" name="car_sms_enabled"
                                    <?php checked( get_option( 'car_sms_enabled', 'no' ), 'yes' ); ?> value="yes">
                                <span class="car-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="car-form-row">
                            <label for="car_sms_provider"><?php esc_html_e( 'SMS Provider', 'fk-cart-recovery' ); ?></label>
                            <select name="car_sms_provider" id="car_sms_provider" class="car-select">
                                <option value="twilio" <?php selected( get_option( 'car_sms_provider', 'twilio' ), 'twilio' ); ?>>Twilio</option>
                                <option value="nexmo"  <?php selected( get_option( 'car_sms_provider' ), 'nexmo' ); ?>>Vonage / Nexmo</option>
                            </select>
                        </div>

                        <div id="car-sms-twilio" class="car-provider-section">
                            <div class="car-form-row">
                                <label for="car_twilio_sid"><?php esc_html_e( 'Account SID', 'fk-cart-recovery' ); ?></label>
                                <input type="text" id="car_twilio_sid" name="car_twilio_sid" value="<?php echo esc_attr( get_option( 'car_twilio_sid' ) ); ?>" class="car-input" />
                            </div>
                            <div class="car-form-row">
                                <label for="car_twilio_token"><?php esc_html_e( 'Auth Token', 'fk-cart-recovery' ); ?></label>
                                <input type="password" id="car_twilio_token" name="car_twilio_token" value="<?php echo esc_attr( get_option( 'car_twilio_token' ) ); ?>" class="car-input" autocomplete="new-password" />
                            </div>
                            <div class="car-form-row">
                                <label for="car_twilio_from"><?php esc_html_e( 'From Number', 'fk-cart-recovery' ); ?></label>
                                <input type="text" id="car_twilio_from" name="car_twilio_from" value="<?php echo esc_attr( get_option( 'car_twilio_from' ) ); ?>" class="car-input" placeholder="+1234567890" />
                            </div>
                        </div>

                        <div id="car-sms-nexmo" class="car-provider-section" style="display:none;">
                            <div class="car-form-row">
                                <label for="car_vonage_key"><?php esc_html_e( 'API Key', 'fk-cart-recovery' ); ?></label>
                                <input type="text" id="car_vonage_key" name="car_vonage_key" value="<?php echo esc_attr( get_option( 'car_vonage_key' ) ); ?>" class="car-input" />
                            </div>
                            <div class="car-form-row">
                                <label for="car_vonage_secret"><?php esc_html_e( 'API Secret', 'fk-cart-recovery' ); ?></label>
                                <input type="password" id="car_vonage_secret" name="car_vonage_secret" value="<?php echo esc_attr( get_option( 'car_vonage_secret' ) ); ?>" class="car-input" autocomplete="new-password" />
                            </div>
                            <div class="car-form-row">
                                <label for="car_vonage_from"><?php esc_html_e( 'From Name/Number', 'fk-cart-recovery' ); ?></label>
                                <input type="text" id="car_vonage_from" name="car_vonage_from" value="<?php echo esc_attr( get_option( 'car_vonage_from' ) ); ?>" class="car-input" />
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── Telegram ───────────────────────────────────────────── -->
            <div class="car-stab-panel" data-spanel="telegram">
                <div class="car-card">
                    <div class="car-card-header">
                        <h3><?php esc_html_e( 'Telegram Settings', 'fk-cart-recovery' ); ?></h3>
                    </div>
                    <div class="car-card-body">

                        <div class="car-form-row car-form-row--inline">
                            <label for="car_telegram_enabled"><?php esc_html_e( 'Enable Telegram', 'fk-cart-recovery' ); ?></label>
                            <label class="car-toggle">
                                <input type="checkbox" id="car_telegram_enabled" name="car_telegram_enabled"
                                    <?php checked( get_option( 'car_telegram_enabled', 'no' ), 'yes' ); ?> value="yes">
                                <span class="car-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="car-form-row">
                            <label for="car_telegram_bot_token"><?php esc_html_e( 'Bot Token', 'fk-cart-recovery' ); ?></label>
                            <input type="password" id="car_telegram_bot_token" name="car_telegram_bot_token"
                                   value="<?php echo esc_attr( get_option( 'car_telegram_bot_token' ) ); ?>"
                                   class="car-input" placeholder="123456789:AABBCCDDEEFFaabbccddeeff-1234567890"
                                   autocomplete="new-password" />
                            <p class="car-field-hint"><?php esc_html_e( 'Create a bot via @BotFather on Telegram.', 'fk-cart-recovery' ); ?></p>
                        </div>

                        <div class="car-form-row">
                            <label><?php esc_html_e( 'Webhook URL', 'fk-cart-recovery' ); ?></label>
                            <div class="car-input-with-btn">
                                <code class="car-webhook-url"><?php echo esc_url( home_url( '/?car_telegram_webhook=1' ) ); ?></code>
                                <button type="button" class="car-btn car-btn-sm car-btn-primary" id="car-setup-tg-webhook"><?php esc_html_e( 'Register Webhook', 'fk-cart-recovery' ); ?></button>
                                <span id="car-tg-webhook-result" class="car-inline-result"></span>
                            </div>
                        </div>

                        <div class="car-notice car-notice--info">
                            <p><?php esc_html_e( 'Customers must message your bot with their email address to link their Telegram account for notifications.', 'fk-cart-recovery' ); ?></p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── GDPR ───────────────────────────────────────────────── -->
            <div class="car-stab-panel" data-spanel="gdpr">
                <div class="car-card">
                    <div class="car-card-header">
                        <h3><?php esc_html_e( 'GDPR / Privacy Settings', 'fk-cart-recovery' ); ?></h3>
                    </div>
                    <div class="car-card-body">

                        <div class="car-form-row car-form-row--inline">
                            <label for="car_gdpr_enabled"><?php esc_html_e( 'Enable GDPR Consent Checkbox', 'fk-cart-recovery' ); ?></label>
                            <label class="car-toggle">
                                <input type="checkbox" id="car_gdpr_enabled" name="car_gdpr_enabled"
                                    <?php checked( get_option( 'car_gdpr_enabled', 'no' ), 'yes' ); ?> value="yes">
                                <span class="car-toggle-slider"></span>
                            </label>
                        </div>
                        <p class="car-field-hint" style="margin-bottom:16px;"><?php esc_html_e( 'When enabled, a checkbox will appear on the checkout page. Only consenting customers will receive recovery messages.', 'fk-cart-recovery' ); ?></p>

                        <div class="car-form-row">
                            <label for="car_gdpr_text"><?php esc_html_e( 'Consent Text', 'fk-cart-recovery' ); ?></label>
                            <textarea id="car_gdpr_text" name="car_gdpr_text" class="car-textarea" rows="3"><?php echo esc_textarea( get_option( 'car_gdpr_text', __( 'I agree to receive cart recovery emails.', 'fk-cart-recovery' ) ) ); ?></textarea>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── Notifications ─────────────────────────────────────── -->
            <div class="car-stab-panel" data-spanel="notifications">
                <div class="car-card">
                    <div class="car-card-header">
                        <h3><?php esc_html_e( 'Admin Notifications', 'fk-cart-recovery' ); ?></h3>
                    </div>
                    <div class="car-card-body">

                        <div class="car-form-row car-form-row--inline">
                            <label for="car_admin_notify_email"><?php esc_html_e( 'Email Admin on Abandonment / Recovery', 'fk-cart-recovery' ); ?></label>
                            <label class="car-toggle">
                                <input type="checkbox" id="car_admin_notify_email" name="car_admin_notify_email"
                                    <?php checked( get_option( 'car_admin_notify_email', 'yes' ), 'yes' ); ?> value="yes">
                                <span class="car-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="car-form-row">
                            <label for="car_admin_notify_address"><?php esc_html_e( 'Notification Email Address', 'fk-cart-recovery' ); ?></label>
                            <input type="email" id="car_admin_notify_address" name="car_admin_notify_address"
                                   value="<?php echo esc_attr( get_option( 'car_admin_notify_address', get_option( 'admin_email' ) ) ); ?>"
                                   class="car-input" />
                        </div>

                    </div>
                </div>
            </div>

            <!-- Save Footer -->
            <div class="car-settings-save-bar">
                <button class="car-btn car-btn-primary car-btn-lg" id="car-save-settings">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( 'Save Settings', 'fk-cart-recovery' ); ?>
                </button>
                <span id="car-settings-saved" class="car-inline-result" style="display:none;">
                    ✅ <?php esc_html_e( 'Settings saved!', 'fk-cart-recovery' ); ?>
                </span>
            </div>

        </div><!-- .car-settings-content -->
    </div><!-- .car-settings-page -->
</div>
