/**
 * Show interaction-only helper elements only when the Turnstile widget is visible.
 *
 * In "Interaction Only" appearance mode the widget stays invisible (zero height)
 * unless an interaction is required. Any associated helper elements (the
 * "Widget Label Text" above the widget, and the spacer/line break below it) are
 * hidden by default and revealed only while their widget has a visible height.
 */
(function () {
    function findWidget(el) {
        var next = el.nextElementSibling;
        if (next && next.classList && next.classList.contains('cf-turnstile')) {
            return next;
        }
        var prev = el.previousElementSibling;
        if (prev && prev.classList && prev.classList.contains('cf-turnstile')) {
            return prev;
        }
        return el.parentNode ? el.parentNode.querySelector('.cf-turnstile') : null;
    }

    function toggle(el, widget) {
        el.style.display = widget.offsetHeight > 10 ? '' : 'none';
    }

    function init() {
        var elements = document.querySelectorAll('.cfturnstile-widget-label-interaction, .cfturnstile-widget-spacer-interaction');
        elements.forEach(function (el) {
            if (el.getAttribute('data-cft-observed') === 'true') {
                return;
            }
            var widget = findWidget(el);
            if (!widget) {
                return;
            }
            el.setAttribute('data-cft-observed', 'true');
            var run = function () {
                toggle(el, widget);
            };
            run();
            if (window.ResizeObserver) {
                try {
                    new ResizeObserver(run).observe(widget);
                } catch (e) {
                    setInterval(run, 500);
                }
            } else {
                setInterval(run, 500);
            }
        });
    }

    window.cfturnstileInitInteractionLabels = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
