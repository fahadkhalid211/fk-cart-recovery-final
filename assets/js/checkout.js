/* Cart Abandonment Recovery Pro – Checkout JS */
(function($) {
    'use strict';

    var captured = false;

    function captureEmail(email, firstName, lastName, phone, gdpr) {
        if (captured) return;
        if (!email || !isValidEmail(email)) return;
        captured = true;

        $.post(carCheckout.ajaxUrl, {
            action:     'car_capture_email',
            nonce:      carCheckout.nonce,
            email:      email,
            first_name: firstName || '',
            last_name:  lastName  || '',
            phone:      phone     || '',
            gdpr:       gdpr ? 1  : 0,
        });
    }

    function isValidEmail(e) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
    }

    // Capture on email field blur
    $(document).on('blur', '#billing_email', function() {
        var email     = $.trim($(this).val());
        var firstName = $.trim($('#billing_first_name').val());
        var lastName  = $.trim($('#billing_last_name').val());
        var phone     = $.trim($('#billing_phone').val());
        var gdpr      = carCheckout.gdpr === 'yes' ? $('#car_gdpr_consent').is(':checked') : true;
        captureEmail(email, firstName, lastName, phone, gdpr);
    });

    // Also capture when moving to next field after email
    $(document).on('blur', '#billing_first_name, #billing_last_name, #billing_phone', function() {
        var email = $.trim($('#billing_email').val());
        if (!email) return;
        var firstName = $.trim($('#billing_first_name').val());
        var lastName  = $.trim($('#billing_last_name').val());
        var phone     = $.trim($('#billing_phone').val());
        var gdpr      = carCheckout.gdpr === 'yes' ? $('#car_gdpr_consent').is(':checked') : true;
        captureEmail(email, firstName, lastName, phone, gdpr);
    });

    // Re-capture if GDPR checkbox is checked after email entered
    $(document).on('change', '#car_gdpr_consent', function() {
        if ($(this).is(':checked')) {
            captured = false;
            var email = $.trim($('#billing_email').val());
            if (email) {
                captureEmail(
                    email,
                    $.trim($('#billing_first_name').val()),
                    $.trim($('#billing_last_name').val()),
                    $.trim($('#billing_phone').val()),
                    true
                );
            }
        }
    });

})(jQuery);
