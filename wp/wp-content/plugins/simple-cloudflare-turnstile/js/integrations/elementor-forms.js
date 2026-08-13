function cfturnstile_elementor_set_submit(btn, enabled) {
  if (!btn) return;
  btn.style.pointerEvents = enabled ? 'auto' : 'none';
  btn.style.opacity     = enabled ? '1'    : '0.5';
}

function cfturnstile_init_elementor_forms() {
  var settings = window.cfturnstileElementorSettings || {};
  var sitekey = settings.sitekey || '';
  var position = settings.position || 'before';
  var mode = settings.mode || 'turnstile';
  var recaptchaSiteKey = settings.recaptchaSiteKey || '';
  var disableSubmit = settings.disableSubmit || false;
  var widgetSize = settings.size || 'normal';
  
  if (!window._cft_elementor_idx) { window._cft_elementor_idx = 0; }
  var elementorForms = document.querySelectorAll('.elementor-form:not(.cft-processed)');
  elementorForms.forEach(function(form) {
    var index = window._cft_elementor_idx++;
    if (form.querySelector('.cf-turnstile') || form.querySelector('.g-recaptcha') || form.querySelector('input[name="cfturnstile_failsafe"]')) {
      form.classList.add('cft-processed');
      return;
    }
    
    var submitButton = form.querySelector('button[type="submit"]');

    // Failsafe modes: inject marker and optionally reCAPTCHA widget.
    if (submitButton && (mode === 'allow' || mode === 'recaptcha')) {
      var marker = document.createElement('input');
      marker.type = 'hidden';
      marker.name = 'cfturnstile_failsafe';
      marker.value = mode;
      form.appendChild(marker);

      if (mode === 'recaptcha' && recaptchaSiteKey) {
        var recaptchaDiv = document.createElement('div');
        recaptchaDiv.className = 'g-recaptcha';
        recaptchaDiv.setAttribute('data-sitekey', recaptchaSiteKey);
        recaptchaDiv.style.cssText = 'display: block; margin: 10px 0 15px 0; width: 100%;';

        if (position === 'after') {
          submitButton.parentNode.insertBefore(recaptchaDiv, submitButton.nextSibling);
        } else if (position === 'afterform') {
          form.appendChild(recaptchaDiv);
        } else {
          submitButton.parentNode.insertBefore(recaptchaDiv, submitButton);
        }
      }

      form.classList.add('cft-processed');
      return;
    }

    if (submitButton && window.turnstile && sitekey) {
      // Disable submit button if option is enabled
      if (disableSubmit) {
        cfturnstile_elementor_set_submit(submitButton, false);
      }

      var labelEl = null;
      if (settings.labelEnable && settings.labelText) {
        labelEl = document.createElement('p');
        labelEl.className = 'cfturnstile-widget-label';
        labelEl.style.cssText = 'font-size: 14px; margin: 0 0 6px 0; width: 100%;';
        if ((settings.appearance || 'always') === 'interaction-only') {
          labelEl.className += ' cfturnstile-widget-label-interaction';
          labelEl.style.display = 'none';
        }
        var smallEl = document.createElement('small');
        smallEl.textContent = settings.labelText;
        labelEl.appendChild(smallEl);
      }

      var turnstileDiv = document.createElement('div');
      turnstileDiv.className = 'elementor-turnstile-field cf-turnstile';
      turnstileDiv.id = 'cf-turnstile-elementor-fallback-' + index;
      turnstileDiv.style.cssText = 'display: block; margin: 10px 0 15px 0; width: 100%;';

      if (position === 'after') {
        if (labelEl) submitButton.parentNode.insertBefore(labelEl, submitButton.nextSibling);
        submitButton.parentNode.insertBefore(turnstileDiv, labelEl ? labelEl.nextSibling : submitButton.nextSibling);
      } else if (position === 'afterform') {
        if (labelEl) form.appendChild(labelEl);
        form.appendChild(turnstileDiv);
      } else {
        if (labelEl) submitButton.parentNode.insertBefore(labelEl, submitButton);
        submitButton.parentNode.insertBefore(turnstileDiv, submitButton);
      }
      
      turnstile.render('#cf-turnstile-elementor-fallback-' + index, {
        sitekey: sitekey,
        theme: settings.theme || 'auto',
        size: widgetSize,
        appearance: settings.appearance || 'always',
        callback: function(token) {
          // Re-enable submit button when Turnstile is complete
          if (disableSubmit && submitButton) {
            cfturnstile_elementor_set_submit(submitButton, true);
          }
          if (typeof turnstileElementorCallback === 'function') {
            turnstileElementorCallback(token);
          }
        },
        'error-callback': function() {
          if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, false); }
        },
        'expired-callback': function() {
          if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, false); }
        }
      });

      if (labelEl && labelEl.classList.contains('cfturnstile-widget-label-interaction') && typeof window.cfturnstileInitInteractionLabels === 'function') {
        window.cfturnstileInitInteractionLabels();
      }

      form.classList.add('cft-processed');
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  cfturnstile_init_elementor_forms();
});

// Listen to Elementor frontend init to handle cached elements
jQuery(window).on('elementor/frontend/init', function() {
  cfturnstile_init_elementor_forms();

  // Hook into Elementor's widget ready system for forms loaded dynamically (e.g. in popups)
  if (window.elementorFrontend && elementorFrontend.hooks) {
    elementorFrontend.hooks.addAction('frontend/element_ready/form.default', function($scope) {
      cfturnstile_init_elementor_forms();
    });
    elementorFrontend.hooks.addAction('frontend/element_ready/login.default', function($scope) {
      cfturnstile_init_elementor_forms();
    });
  }
});

// Re-render Turnstile only after Elementor reports a submit error (e.g. failed validation)
function cfturnstile_elementor_rerender(form) {
  if (!form || !window.turnstile) return;
  var settings = window.cfturnstileElementorSettings || {};
  var widget = form.querySelector('.cf-turnstile');
  if (!widget) return;
  var submitButton = form.querySelector('button[type="submit"]');
  var disableSubmit = settings.disableSubmit || false;
  if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, false); }
  try { turnstile.remove(widget); } catch (e) {}
  turnstile.render(widget, {
    sitekey: settings.sitekey,
    theme: settings.theme || 'auto',
    size: settings.size || 'normal',
    appearance: settings.appearance || 'always',
    callback: function(token) {
      if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, true); }
      if (typeof turnstileElementorCallback === 'function') {
        turnstileElementorCallback(token);
      }
    },
    'error-callback': function() {
      if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, false); }
    },
    'expired-callback': function() {
      if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, false); }
    }
  });
}

jQuery(document).on('submit_error submit_success', '.elementor-form', function() {
  var settings = window.cfturnstileElementorSettings || {};
  if ((settings.mode || 'turnstile') !== 'turnstile') return;
  cfturnstile_elementor_rerender(this);
});

// Block submission until Turnstile is completed (client-side guard).
// Server-side validation in cfturnstile_elementor_check is the source of truth;
// this just avoids a wasted round-trip and gives immediate feedback.
document.addEventListener('submit', function(event) {
  var form = event.target;
  if (!form.classList || !form.classList.contains('elementor-form')) return;
  var settings = window.cfturnstileElementorSettings || {};
  if ((settings.mode || 'turnstile') !== 'turnstile' || !window.turnstile) return;

  var widget = form.querySelector('.cf-turnstile');
  if (!widget) return;
  var token = '';
  try { token = turnstile.getResponse(widget) || ''; } catch (e) {}
  if (!token) {
    var responseInput = form.querySelector('[name="cf-turnstile-response"]');
    token = responseInput ? (responseInput.value || '') : '';
  }
  if (!token) {
    event.preventDefault();
    event.stopImmediatePropagation();
    try { widget.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
  }
}, true);

// Handle Elementor popup show events (jQuery event - must use jQuery to listen)
jQuery(document).on('elementor/popup/show', function(event, id, instance) {
  setTimeout(function() {
    // First, inject Turnstile into any unprocessed forms inside the popup
    cfturnstile_init_elementor_forms();

    var settings = window.cfturnstileElementorSettings || {};
    var mode = settings.mode || 'turnstile';
    var disableSubmit = settings.disableSubmit || false;
    if (mode !== 'turnstile' || !window.turnstile) {
      return;
    }

    // Re-render all Turnstile widgets inside popup modals (they need explicit render after DOM insertion)
    var popupTurnstiles = document.querySelectorAll('.elementor-popup-modal .cf-turnstile');
    popupTurnstiles.forEach(function(widget) {
      var failedText = widget.parentNode ? widget.parentNode.querySelector('.cf-turnstile-failed-text') : null;
      if (failedText) {
        failedText.style.display = 'none';
      }

      // Find the form and submit button for this widget
      var form = widget.closest('.elementor-form');
      var submitButton = form ? form.querySelector('button[type="submit"]') : null;

      // Disable submit button if option is enabled
      if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, false); }
      
      turnstile.remove(widget);
      turnstile.render(widget, {
        sitekey: cfturnstileElementorSettings.sitekey,
        appearance: cfturnstileElementorSettings.appearance || 'always',
        callback: function(token) {
          // Re-enable submit button when Turnstile is complete
          if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, true); }
          if (typeof turnstileElementorCallback === 'function') {
            turnstileElementorCallback(token);
          }
        },
        'error-callback': function() {
          if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, false); }
        },
        'expired-callback': function() {
          if (disableSubmit && submitButton) { cfturnstile_elementor_set_submit(submitButton, false); }
        },
        theme: cfturnstileElementorSettings.theme || 'auto',
        size: cfturnstileElementorSettings.size || 'normal'
      });
    });
  }, 500);
});