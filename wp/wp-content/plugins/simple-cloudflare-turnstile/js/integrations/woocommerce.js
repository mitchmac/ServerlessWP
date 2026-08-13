/* Woo Checkout */
( function () {

    // perf.php excludes this file from "delay JavaScript" optimizers so the widget can appear
    // without a user interaction. Those optimizers may therefore run it BEFORE jQuery, which is
    // usually still delayed - and touching jQuery at eval time would throw, aborting this script
    // and cascading into Woo's own delayed checkout scripts (breaking country/state switching and
    // the order review refresh). So nothing here touches jQuery until it is known to exist, and
    // everything that does not need jQuery runs independently of it.

    var WIDGET_ID = 'cf-turnstile-woo-checkout';

    function cfturnstileWooWidget() {
        return document.getElementById( WIDGET_ID );
    }

    function cfturnstileWooOnReady( fn ) {
        if ( document.readyState === 'loading' ) {
            document.addEventListener( 'DOMContentLoaded', fn );
        } else {
            fn();
        }
    }

    // Matches a delegated click without needing jQuery.
    function cfturnstileWooClicked( e, selector ) {
        return !!( e.target && e.target.closest && e.target.closest( selector ) );
    }

    // Explicit rendering ignores data-*-callback, which would leave the submit button disabled.
    function cfturnstileWooOpts( target ) {
        return ( typeof window.cfturnstileOpts === 'function' ) ? window.cfturnstileOpts( target ) : {};
    }

    /* Give the classic checkout widget a fresh token. Touches only the DOM and the global
       `turnstile`, so it is safe to call at any point. */
    function turnstileWooCheckoutReset() {
        if ( typeof turnstile === 'undefined' ) {
            return;
        }

        var el = cfturnstileWooWidget();
        if ( !el ) {
            return;
        }

        // Woo replaced the container and left it empty: render a new widget into it.
        if ( !el.firstElementChild ) {
            try { turnstile.render( el, cfturnstileWooOpts( el ) ); } catch ( e ) {}
            return;
        }

        // Otherwise reset in place, which clears the used or expired token.
        try {
            turnstile.reset( el );
            return;
        } catch ( e ) {}

        // reset() only fails when Turnstile no longer recognises the element, so rebuild it.
        try { turnstile.remove( el ); } catch ( e ) {}
        try { turnstile.render( el, cfturnstileWooOpts( el ) ); } catch ( e ) {}
    }

    /* "Show login" toggle: rebuild that widget once the panel is open. Bound natively on document
       so it survives fragment refreshes and works whether or not jQuery has loaded. */
    document.addEventListener( 'click', function ( e ) {
        if ( !cfturnstileWooClicked( e, '.showlogin, .show-login, .e-show-login, .woocommerce-form-login-toggle a' ) ) {
            return;
        }
        setTimeout( function () {
            if ( typeof turnstile === 'undefined' ) {
                return;
            }
            try { turnstile.remove( '.sct-woocommerce-login' ); } catch ( err ) {}
            try { turnstile.render( '.sct-woocommerce-login', cfturnstileWooOpts( '.sct-woocommerce-login' ) ); } catch ( err ) {}
        }, 250 );
    } );

    /* Classic checkout: keep the widget valid across Woo's fragment refreshes. Woo fires these as
       jQuery custom events, so this is the one part that genuinely needs jQuery. */
    function cfturnstileWooRun() {

        var $ = window.jQuery;

        $( document ).ready( function () {

            var attempted = false;

            function hasCheckoutError() {
                return !!document.querySelector( '.woocommerce-error' );
            }

            // Only reset after a real submission attempt, not on every checkout refresh.
            $( document.body ).on( 'submit', 'form.checkout', function () {
                attempted = true;
            } );

            // Page loaded with an error already showing: the next submit needs a fresh token.
            if ( hasCheckoutError() ) {
                setTimeout( turnstileWooCheckoutReset, 50 );
            }

            $( document.body ).on( 'update_checkout updated_checkout applied_coupon_in_checkout removed_coupon_in_checkout', function () {
                var el = cfturnstileWooWidget();

                // Container was replaced or emptied: re-render once the DOM has settled.
                if ( !el || !el.firstElementChild ) {
                    setTimeout( turnstileWooCheckoutReset, 300 );
                    return;
                }

                // Woo refreshes fragments after a failed submit; clear the used token.
                if ( attempted && hasCheckoutError() ) {
                    setTimeout( turnstileWooCheckoutReset, 300 );
                    attempted = false;
                }
            } );

            // Fired when the AJAX checkout submission comes back with an error.
            $( document.body ).on( 'checkout_error', function () {
                setTimeout( turnstileWooCheckoutReset, 500 );
                attempted = false;
            } );
        } );
    }

    /* Woo Checkout Block. React based and needs nothing from jQuery, so it must not wait on a
       jQuery that a delay-JS optimizer may only load on the first interaction. PHP does not emit
       a render script for this widget on a block checkout, so this is what renders it. */
    function cfturnstileWooBlockRun() {

        // Guard against the classic (shortcode) checkout, which uses the same widget id - without
        // this the block code would tear down and re-render the classic widget while wiring it to
        // the block-only wc/store/checkout. wp.data is always defined (declared as a dependency).
        if ( !document.querySelector( '.wp-block-woocommerce-checkout, .wc-block-checkout' ) || typeof wp === 'undefined' || !wp.data ) {
            return;
        }

        function setExtensionData( token ) {
            var dispatch = wp.data.dispatch( 'wc/store/checkout' );
            if ( !dispatch ) {
                return;
            }
            if ( typeof dispatch.setExtensionData === 'function' ) {
                dispatch.setExtensionData( 'simple-cloudflare-turnstile', { token: token } );
            } else if ( typeof dispatch.__internalSetExtensionData === 'function' ) {
                dispatch.__internalSetExtensionData( 'simple-cloudflare-turnstile', { token: token } );
            }
        }

        function renderBlockWidget() {
            // The API is loaded with render=explicit and may still be in flight. Bail quietly -
            // the subscription below runs on every cart change and calls back once it lands.
            if ( typeof turnstile === 'undefined' ) {
                return;
            }

            var el = cfturnstileWooWidget();
            if ( !el ) {
                return;
            }

            // Already up: reset in place, which clears the token but keeps the widget.
            if ( el.getAttribute( 'data-sct-init' ) === 'true' && el.firstElementChild ) {
                try {
                    turnstile.reset( el );
                    setExtensionData( '' );
                    return;
                } catch ( e ) {}
            }

            try { turnstile.remove( el ); } catch ( e ) {}

            try {
                turnstile.render( el, {
                    sitekey: el.dataset.sitekey,
                    appearance: el.dataset.appearance || 'always',
                    callback: setExtensionData,
                    'expired-callback': function () { setExtensionData( '' ); }
                } );
                el.setAttribute( 'data-sct-init', 'true' );
            } catch ( e ) {}
        }

        // Refresh the token shortly after Place order is clicked.
        var clickTimer = null;
        document.addEventListener( 'click', function ( e ) {
            if ( !cfturnstileWooClicked( e, '.wc-block-components-checkout-place-order-button' ) ) {
                return;
            }
            clearTimeout( clickTimer );
            clickTimer = setTimeout( renderBlockWidget, 2000 );
        } );

        // Render whenever the cart store changes and the widget is missing or uninitialised. This
        // runs on every change, so test for a child element rather than serializing innerHTML.
        wp.data.subscribe( function () {
            var el = cfturnstileWooWidget();
            if ( el && ( !el.firstElementChild || el.getAttribute( 'data-sct-init' ) !== 'true' ) ) {
                renderBlockWidget();
            }
        }, 'wc/store/cart' );

        renderBlockWidget();
    }

    // The block checkout needs no jQuery, so boot it as soon as the DOM is ready.
    cfturnstileWooOnReady( cfturnstileWooBlockRun );

    // The classic checkout handlers are jQuery based. When a delay-JS optimizer runs this file
    // ahead of jQuery, poll for it (jQuery loads on the first interaction) instead of throwing.
    if ( typeof window.jQuery !== 'undefined' ) {
        cfturnstileWooRun();
    } else {
        var tries = 0;
        var wait = setInterval( function () {
            if ( typeof window.jQuery !== 'undefined' ) {
                clearInterval( wait );
                cfturnstileWooRun();
            } else if ( ++tries > 1200 ) { // ~60s safety cap
                clearInterval( wait );
            }
        }, 50 );
    }

} )();
