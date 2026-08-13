/* Admin Toggles */
document.addEventListener("DOMContentLoaded", function() {
	if(document.querySelector("#sct-accordion-wordpress") != null) {
		document.querySelector("#sct-accordion-wordpress").click();
	}
});
/* Toggle Secret Key Visibility */
document.addEventListener("DOMContentLoaded", function() {
	document.querySelectorAll(".sct-toggle-secret").forEach(function(button) {
		button.addEventListener("click", function() {
			var input = document.getElementById(this.getAttribute("data-target"));
			if (!input) {
				return;
			}
			if (input.type === "password") {
				input.type = "text";
				this.setAttribute("aria-pressed", "true");
				this.textContent = "Hide";
			} else {
				input.type = "password";
				this.setAttribute("aria-pressed", "false");
				this.textContent = "View";
			}
		});
	});
});
var acc = document.getElementsByClassName("sct-accordion");
var i;
for (i = 0; i < acc.length; i++) {
  acc[i].addEventListener("click", function() {
	this.classList.toggle("sct-active");
	var panel = this.nextElementSibling;
	if (panel.style.display === "block") {
	  panel.style.display = "none";
	} else {
	  panel.style.display = "block";
	}
  });
}
/* Refresh Timeout Description */
document.addEventListener("DOMContentLoaded", function() {
    function updateRefreshTimeoutDescription(selected) {
        document.querySelectorAll('.wcu-refresh-timeout-auto, .wcu-refresh-timeout-manual, .wcu-refresh-timeout-never')
            .forEach(element => element.style.display = 'none');
        document.querySelector('.wcu-refresh-timeout-' + selected).style.display = 'block';
    }

    const refreshTimeoutSelect = document.querySelector("select[name='cfturnstile_refresh_timeout']");
    updateRefreshTimeoutDescription(refreshTimeoutSelect.value);

    refreshTimeoutSelect.addEventListener("change", function() {
        updateRefreshTimeoutDescription(this.value);
    });
});
/* Appearance Mode Description */
document.addEventListener("DOMContentLoaded", function() {
    function updateDescription(selected) {
        // Hide all descriptions
        document.querySelectorAll('.wcu-appearance-always, .wcu-appearance-execute, .wcu-appearance-interaction-only')
            .forEach(element => element.style.display = 'none');

        // Show the relevant description
        document.querySelector('.wcu-appearance-' + selected).style.display = 'block';
    }

    // Update the description on page load
    const appearanceSelect = document.querySelector("select[name='cfturnstile_appearance']");
    updateDescription(appearanceSelect.value);

    // Handle the select change event
    appearanceSelect.addEventListener("change", function() {
        updateDescription(this.value);
    });
});
/* wp-config define keys toggle */
document.addEventListener('DOMContentLoaded', function() {
  var toggles = document.querySelectorAll('.sct-wpconfig-toggle');
  toggles.forEach(function(link){
    link.addEventListener('click', function(e){
      e.preventDefault();
      var details = this.nextElementSibling;
      if(!details) return;
      var isOpen = details.style.display === 'block';
      details.style.display = isOpen ? 'none' : 'block';
      var icon = this.querySelector('.dashicons');
      if(icon){
        if(isOpen){
          icon.classList.remove('dashicons-arrow-up-alt2');
          icon.classList.add('dashicons-arrow-down-alt2');
        } else {
          icon.classList.remove('dashicons-arrow-down-alt2');
          icon.classList.add('dashicons-arrow-up-alt2');
        }
      }
    });
  });
});

/* Elementor Scripts Scope Toggle */
document.addEventListener("DOMContentLoaded", function() {
  const pagesWrap = document.getElementById('cfturnstile-elementor-global-pages-wrap');
  const scopeSelect = document.getElementById('cfturnstile_elementor_global_scope');
  if (!scopeSelect) return;

  const updateVisibility = () => {
    // Toggle pages wrap based on scope select
    if (pagesWrap) {
      pagesWrap.style.display = (scopeSelect.value === 'specific') ? '' : 'none';
    }

    // Toggle scope descriptions
    document.querySelectorAll('.cfturnstile-elementor-scope-description').forEach(function(div){
      div.style.display = 'none';
    });
    const scopeDiv = document.getElementById('cfturnstile-elementor-scope-' + scopeSelect.value);
    if (scopeDiv) scopeDiv.style.display = 'block';
  };

  scopeSelect.addEventListener('change', updateVisibility);
  updateVisibility();
});

/* Failure Message Toggle */
document.addEventListener("DOMContentLoaded", function() {
  const failureMessages = document.querySelectorAll('.cfturnstile-failure-message');
  const toggleInput = document.querySelector('input[name="cfturnstile_failure_message_enable"]');

  // If elements aren't present on this page, safely exit
  if (!toggleInput || failureMessages.length === 0) return;

  // Helper to set visibility
  const setVisibility = (checked) => {
    failureMessages.forEach(el => {
      el.style.display = checked ? '' : 'none';
    });
  };

  // Initialize based on current state
  setVisibility(toggleInput.checked);

  // Update on change
  toggleInput.addEventListener('change', function() {
    setVisibility(this.checked);
  });
});

/* Widget Text Label Toggle */
document.addEventListener("DOMContentLoaded", function() {
  const labelRows = document.querySelectorAll('.cfturnstile-widget-label-text');
  const toggleInput = document.querySelector('input[name="cfturnstile_widget_label_enable"]');

  if (!toggleInput || labelRows.length === 0) return;

  const setVisibility = (checked) => {
    labelRows.forEach(el => {
      el.style.display = checked ? '' : 'none';
    });
  };

  setVisibility(toggleInput.checked);

  toggleInput.addEventListener('change', function() {
    setVisibility(this.checked);
  });
});

/* Failsafe Settings Toggle */
document.addEventListener("DOMContentLoaded", function() {
  const failsafeEnabled = document.getElementById('cfturnstile_failover');
  const failsafeType = document.getElementById('cfturnstile_failsafe_type');
  const failsafeOptions = document.querySelectorAll('.sct-failsafe-options');
  const recaptchaRows = document.querySelectorAll('.sct-failsafe-recaptcha');

  if (!failsafeEnabled || !failsafeType) return;

  const setRowDisplay = (nodes, show) => {
    nodes.forEach(el => {
      el.style.display = show ? '' : 'none';
    });
  };

  const updateFailsafeVisibility = () => {
    const enabled = !!failsafeEnabled.checked;
    setRowDisplay(failsafeOptions, enabled);

    const useRecaptcha = enabled && (failsafeType.value === 'recaptcha');
    setRowDisplay(recaptchaRows, useRecaptcha);
  };

  failsafeEnabled.addEventListener('change', updateFailsafeVisibility);
  failsafeType.addEventListener('change', updateFailsafeVisibility);

  updateFailsafeVisibility();
});

/* Copy Turnstile Debug Log */
document.addEventListener('DOMContentLoaded', function() {
  var copyButton = document.getElementById('cfturnstile-copy-log');
  if (!copyButton) return;
  if (copyButton.disabled) return;

  copyButton.addEventListener('click', async function() {
    var targetId = this.getAttribute('data-target') || 'cfturnstile-debug-log-text';
    var target = document.getElementById(targetId);
    if (!target) return;
    var text = (target.value !== undefined) ? target.value : (target.textContent || '');
    if (!text) return;

    var originalLabel = this.getAttribute('data-original-label') || this.textContent;
    this.setAttribute('data-original-label', originalLabel);
    this.disabled = true;

    var copied = false;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        copied = true;
      }
    } catch (e) {
      copied = false;
    }

    if (!copied) {
      try {
        var temp = document.createElement('textarea');
        temp.value = text;
        temp.setAttribute('readonly', '');
        temp.style.position = 'absolute';
        temp.style.left = '-9999px';
        temp.style.top = '0';
        document.body.appendChild(temp);
        temp.select();
        temp.setSelectionRange(0, temp.value.length);
        copied = document.execCommand('copy');
        document.body.removeChild(temp);
      } catch (e) {
        copied = false;
      }
    }

    this.textContent = copied ? 'Copied!' : 'Copy failed';
    var self = this;
    window.setTimeout(function() {
      self.textContent = originalLabel;
      self.disabled = false;
    }, 1500);
  });
});