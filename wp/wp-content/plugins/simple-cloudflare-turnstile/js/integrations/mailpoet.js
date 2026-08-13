document.addEventListener("DOMContentLoaded", function() {
    jQuery("form.mailpoet_form").on("submit", function(event) {
        var form = jQuery(this);

        // Turnstile token (copy into MailPoet's expected nested data payload)
        var tokenElem = form[0].querySelector("input[name='cf-turnstile-response']");
        if (tokenElem && tokenElem.value) {
            form.find("input[name='data[cf-turnstile-response]']").remove();
            form.append('<input type="hidden" name="data[cf-turnstile-response]" value="' + tokenElem.value + '">');
        }

        // Failsafe marker (allow | recaptcha)
        var failsafeElem = form[0].querySelector("input[name='cfturnstile_failsafe']");
        if (failsafeElem && failsafeElem.value) {
            form.find("input[name='data[cfturnstile_failsafe]']").remove();
            form.append('<input type="hidden" name="data[cfturnstile_failsafe]" value="' + failsafeElem.value + '">');

            // reCAPTCHA response is generated as a textarea; copy it into nested data for consistent server access
            var recaptchaElem = form[0].querySelector("textarea[name='g-recaptcha-response'], input[name='g-recaptcha-response']");
            if (recaptchaElem && recaptchaElem.value) {
                form.find("input[name='data[g-recaptcha-response]']").remove();
                form.append('<input type="hidden" name="data[g-recaptcha-response]" value="' + recaptchaElem.value + '">');
            }
        }
    });
});