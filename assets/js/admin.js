/* Cart Abandonment Recovery Pro – Admin JS */
(function($) {
    'use strict';

    var nonce = carAdmin.nonce;
    var ajaxUrl = carAdmin.ajaxUrl;

    // ── Tabs ──────────────────────────────────────────────────────────────────
    $(document).on('click', '.car-tab-btn', function() {
        var tab = $(this).data('tab');
        $(this).closest('.car-modal-body').find('.car-tab-btn').removeClass('car-tab-btn--active');
        $(this).addClass('car-tab-btn--active');
        $(this).closest('.car-modal-body').find('.car-tab-panel').removeClass('car-tab-panel--active');
        $(this).closest('.car-modal-body').find('[data-panel="' + tab + '"]').addClass('car-tab-panel--active');
    });

    // Settings tabs
    $(document).on('click', '.car-stab-btn', function() {
        var stab = $(this).data('stab');
        $('.car-stab-btn').removeClass('car-stab-btn--active');
        $(this).addClass('car-stab-btn--active');
        $('.car-stab-panel').removeClass('car-stab-panel--active');
        $('[data-spanel="' + stab + '"]').addClass('car-stab-panel--active');
    });

    // ── Campaign Modal ────────────────────────────────────────────────────────
    function openCampaignModal(data, rules) {
        data = data || {};
        rules = rules || [];

        var title = data.id ? carAdmin.i18n.saving.replace('...','') + ' Campaign' : 'New Campaign';
        $('#car-modal-title').text(data.id ? 'Edit Campaign' : 'New Campaign');
        $('#car_campaign_id').val(data.id || '');
        $('#car_campaign_name').val(data.name || '');
        $('#car_campaign_channel').val(data.channel || 'email');
        $('#car_send_after').val(data.send_after_minutes || 60);
        $('#car_campaign_status').val(data.status || 'active');
        $('#car_sort_order').val(data.sort_order || 0);
        $('#car_subject').val(data.subject || '');
        $('#car_body').val(data.body || '');
        $('#car_coupon_enabled').prop('checked', data.coupon_enabled == 1);
        $('#car_coupon_type').val(data.coupon_type || 'percent');
        $('#car_coupon_amount').val(data.coupon_amount || 10);
        $('#car_coupon_expiry').val(data.coupon_expiry_days || 3);
        $('#car-coupon-fields').toggle(data.coupon_enabled == 1);

        // Load rules
        var $list = $('#car-rules-list').empty();
        var i = 0;
        (rules || []).forEach(function(r) {
            $list.append(buildRuleRow(i++, r));
        });
        window._carRuleIndex = i;

        // Reset to first tab
        $('.car-tab-btn').first().trigger('click');
        $('#car-campaign-modal').fadeIn(200);
    }

    function buildRuleRow(i, data) {
        var tpl = $('#car-rule-tpl').html().replace(/__i__/g, i);
        var $row = $(tpl);
        if (data) {
            $row.find('.car-rule-type').val(data.rule_type);
            $row.find('.car-rule-operator').val(data.operator);
            $row.find('.car-rule-value').val(data.rule_value);
            $row.find('.car-rule-action').val(data.action);
        }
        return $row;
    }

    $(document).on('click', '#car-add-campaign, #car-add-campaign-empty', function() {
        openCampaignModal();
    });

    $(document).on('click', '.car-edit-campaign', function() {
        var id = $(this).data('id');
        $.post(ajaxUrl, { action: 'car_get_campaign', nonce: nonce, id: id }, function(res) {
            if (res.success) {
                openCampaignModal(res.data.campaign, res.data.rules);
            }
        });
    });

    $(document).on('click', '.car-modal-close, .car-modal-overlay', function() {
        $('#car-campaign-modal').fadeOut(200);
    });

    $(document).on('change', '#car_coupon_enabled', function() {
        $('#car-coupon-fields').toggle($(this).is(':checked'));
    });

    $(document).on('change', '#car_send_after_preset', function() {
        $('#car_send_after').val($(this).val());
    });

    $(document).on('click', '#car-add-rule', function() {
        var i = window._carRuleIndex || 0;
        window._carRuleIndex = i + 1;
        $('#car-rules-list').append(buildRuleRow(i));
    });

    $(document).on('click', '.car-remove-rule', function() {
        $(this).closest('.car-rule-row').remove();
    });

    // Save Campaign
    $(document).on('click', '#car-save-campaign', function() {
        var $btn = $(this).text(carAdmin.i18n.saving).prop('disabled', true);

        // Collect rules
        var rules = [];
        $('#car-rules-list .car-rule-row').each(function(i) {
            rules.push({
                rule_type:  $(this).find('.car-rule-type').val(),
                operator:   $(this).find('.car-rule-operator').val(),
                rule_value: $(this).find('.car-rule-value').val(),
                action:     $(this).find('.car-rule-action').val(),
            });
        });

        var data = {
            action: 'car_save_campaign',
            nonce: nonce,
            campaign_id: $('#car_campaign_id').val(),
            name:               $('#car_campaign_name').val(),
            channel:            $('#car_campaign_channel').val(),
            status:             $('#car_campaign_status').val(),
            send_after_minutes: $('#car_send_after').val(),
            sort_order:         $('#car_sort_order').val(),
            subject:            $('#car_subject').val(),
            body:               $('#car_body').val(),
            coupon_enabled:     $('#car_coupon_enabled').is(':checked') ? 1 : 0,
            coupon_type:        $('#car_coupon_type').val(),
            coupon_amount:      $('#car_coupon_amount').val(),
            coupon_expiry_days: $('#car_coupon_expiry').val(),
        };

        $.post(ajaxUrl, data, function(res) {
            if (res.success) {
                // Save rules
                $.post(ajaxUrl, { action: 'car_save_rules', nonce: nonce, campaign_id: res.data.id, rules: rules });
                $('#car-campaign-modal').fadeOut(200);
                location.reload();
            } else {
                alert(carAdmin.i18n.error);
            }
        }).always(function() {
            $btn.text('Save Campaign').prop('disabled', false);
        });
    });

    // Toggle campaign status
    $(document).on('change', '.car-toggle-status', function() {
        var id  = $(this).data('id');
        var status = $(this).is(':checked') ? 'active' : 'paused';
        $.post(ajaxUrl, { action: 'car_save_campaign', nonce: nonce, campaign_id: id, status: status });
    });

    // Delete campaign
    $(document).on('click', '.car-delete-campaign', function() {
        if (!confirm(carAdmin.i18n.confirmDelete)) return;
        var id = $(this).data('id');
        $.post(ajaxUrl, { action: 'car_delete_campaign', nonce: nonce, id: id }, function() {
            location.reload();
        });
    });

    // Delete cart
    $(document).on('click', '.car-delete-cart', function() {
        if (!confirm(carAdmin.i18n.confirmDelete)) return;
        var id = $(this).data('id');
        var $row = $(this).closest('tr');
        $.post(ajaxUrl, { action: 'car_delete_cart', nonce: nonce, id: id }, function() {
            $row.fadeOut(300, function() { $(this).remove(); });
        });
    });

    // ── Settings ─────────────────────────────────────────────────────────────
    $(document).on('click', '#car-save-settings', function() {
        var $btn = $(this).text(carAdmin.i18n.saving).prop('disabled', true);
        var data = { action: 'car_save_settings', nonce: nonce };

        // Collect all named inputs
        $('.car-settings-content input, .car-settings-content select, .car-settings-content textarea').each(function() {
            var name = $(this).attr('name');
            if (!name) return;
            if ($(this).is(':checkbox')) {
                data[name] = $(this).is(':checked') ? 'yes' : 'no';
            } else {
                data[name] = $(this).val();
            }
        });

        $.post(ajaxUrl, data, function(res) {
            if (res.success) {
                $('#car-settings-saved').fadeIn().delay(2000).fadeOut();
            }
        }).always(function() {
            $btn.text('Save Settings').prop('disabled', false);
        });
    });

    // WhatsApp provider toggle
    function toggleWAProvider(val) {
        $('.car-provider-fields').hide();
        if (val === 'ultramsg')           { $('#car-wa-ultramsg').show(); }
        else if (val === 'whatsapp_business') { $('#car-wa-business').show(); }
        else if (val === 'twilio_wa')     { $('#car-wa-twilio').show(); }
    }
    var initWA = $('#car_whatsapp_provider').val();
    if (initWA) toggleWAProvider(initWA);
    $(document).on('change', '#car_whatsapp_provider', function() { toggleWAProvider($(this).val()); });

    // SMS provider toggle
    $(document).on('change', '#car_sms_provider', function() {
        var v = $(this).val();
        $('#car-sms-twilio').toggle(v === 'twilio');
        $('#car-sms-nexmo').toggle(v === 'nexmo' || v === 'vonage');
    });

    // Test WhatsApp
    $(document).on('click', '#car-test-wa', function() {
        var $result = $('#car-wa-test-result').text('Testing...');
        $.post(ajaxUrl, { action: 'car_test_whatsapp', nonce: nonce, provider: $('#car_whatsapp_provider').val() }, function(res) {
            $result.text(res.success ? '✅ Connected!' : '❌ ' + (res.message || 'Failed'));
        });
    });

    // Telegram webhook
    $(document).on('click', '#car-setup-tg-webhook', function() {
        var $result = $('#car-tg-webhook-result').text('Setting up...');
        $.post(ajaxUrl, { action: 'car_setup_telegram_webhook', nonce: nonce }, function(res) {
            $result.text(res.success ? '✅ ' + res.message : '❌ ' + res.message);
        });
    });

})(jQuery);
