/* WP */
function turnstileWPCallback() {
    document.querySelectorAll('#wp-submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
function turnstileCommentCallback() {
    document.querySelectorAll('.cf-turnstile-comment').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* WP Login: disable submit via keys until Turnstile is complete */
document.addEventListener('DOMContentLoaded', function() {
    var loginForm = document.getElementById('loginform');
    if (!loginForm) { return; }
    if (!loginForm.querySelector('.cf-turnstile')) { return; }
    function isTurnstileComplete() {
        var response = loginForm.querySelector('input[name="cf-turnstile-response"]');
        return response && response.value.length > 0;
    }
    // Block submit event: catches Enter key, button click via form, and requestSubmit()
    loginForm.addEventListener('submit', function(e) {
        if (!isTurnstileComplete()) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);
    // Block programmatic form.submit() calls
    loginForm.submit = function() {
        if (isTurnstileComplete()) {
            HTMLFormElement.prototype.submit.call(loginForm);
        }
    };
});
/* Woo */
function turnstileWooLoginCallback() {
    document.querySelectorAll('.woocommerce-form-login__submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
function turnstileWooRegisterCallback() {
    document.querySelectorAll('.woocommerce-form-register__submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
function turnstileWooResetCallback() {
    document.querySelectorAll('.woocommerce-ResetPassword .button').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
function turnstileWooAccountCallback() {
    document.querySelectorAll('.woocommerce-EditAccountForm button[name=save_account_details], .woocommerce-EditAccountForm input[name=save_account_details]').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* EDD */
function turnstileEDDLoginCallback() {
    document.querySelectorAll('#edd_login_submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
function turnstileEDDRegisterCallback() {
    document.querySelectorAll('#edd_register_form .edd-submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* PMP */
function turnstilePMPLoginCallback() {
    document.querySelectorAll('#wp-submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* Elementor */
function turnstileElementorCallback() {
    document.querySelectorAll('.elementor-form button[type="submit"]').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* Kadence */
function turnstileKadenceCallback() {
    document.querySelectorAll('.kb-adv-form-submit-button, .kb-submit-field .kb-button, .kb-form-submit .kb-button, .kb-submit-field button[type="submit"], .kb-form-submit button[type="submit"]').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* CF7 */
function turnstileCF7Callback() {
    document.querySelectorAll('.wpcf7-submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* MC4WP */
function turnstileMC4WPCallback() {
    document.querySelectorAll('.mc4wp-form-fields input[type=submit]').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* MailPoet */
function turnstileMailpoetCallback() {
    document.querySelectorAll('.mailpoet_submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* BuddyPress */
function turnstileBPCallback() {
    document.querySelectorAll('#buddypress #signup-form .submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* BBPress */
function turnstileBBPressReplyCallback() {
    document.querySelectorAll('#bbp_reply_submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* WPForms */
function turnstileWPFCallback() {
    document.querySelectorAll('.wpforms-submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* Fluent Forms */
function turnstileFluentCallback() {
    document.querySelectorAll('.fluentform .ff-btn-submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* Formidable Forms */
function turnstileFormidableCallback() {
    document.querySelectorAll('.frm_forms .frm_button_submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* Gravity Forms */
function turnstileGravityCallback() {
    document.querySelectorAll('.gform_button').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* Ultimate Member */
function turnstileUMCallback() {
    document.querySelectorAll('#um-submit-btn').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* WP User Frontend */
function turnstileWPUFCallback() {
    document.querySelectorAll('.wpuf-form input[type="submit"]').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* MemberPress */
function turnstileMEPRCallback() {
    document.querySelectorAll('.mepr-submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* BBPress */
function turnstileBBPressCreateCallback() {
    document.querySelectorAll('#bbp_topic_submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* SureForms */
function turnstilesureformsCallback() {
    document.querySelectorAll('.srfm-submit-container .srfm-submit-button').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* Jetpack */
function turnstileJetpackCallback() {
    document.querySelectorAll('.wp-block-jetpack-contact-form button').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}
/* Sunshine Photo Cart */
function turnstileSunshineCheckoutCallback() {
    document.querySelectorAll('#sunshine--checkout--submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
}