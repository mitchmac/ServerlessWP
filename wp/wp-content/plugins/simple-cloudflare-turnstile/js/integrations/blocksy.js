/* Blocksy */
( function () {

    // Blocksy renders its header account forms inside <template id="ct-account-modal-template">,
    // and clones them into the page the first time the modal is opened. Template content is inert,
    // so document.getElementById() cannot see it and the widget can never be rendered at page load
    // - it has to be rendered here, once the clone is in the live DOM. Re-opening the modal then
    // resets the widget, so a second attempt never submits an already-used token.

    var MODAL = '#account-modal, .ct-account-modal';
    var timer = null;

    // Explicit rendering ignores data-*-callback, which would leave the submit button disabled.
    function cftOpts( el ) {
        return ( typeof window.cfturnstileOpts === 'function' ) ? window.cfturnstileOpts( el ) : {};
    }

    function refresh() {
        if ( typeof turnstile === 'undefined' ) {
            return;
        }

        var widgets = document.querySelectorAll( '#account-modal .cf-turnstile, .ct-account-modal .cf-turnstile' );
        for ( var i = 0; i < widgets.length; i++ ) {
            var el = widgets[ i ];
            if ( el.firstElementChild ) {
                // Already rendered: clear the used token but keep the widget in place.
                try { turnstile.reset( el ); } catch ( e ) {}
            } else {
                // First open: this is the widget's only chance to be rendered.
                try { turnstile.render( el, cftOpts( el ) ); } catch ( e ) {}
            }
        }
    }

    // Debounced: the toggle fires on open and on close, and the observer may fire alongside it.
    function schedule() {
        clearTimeout( timer );
        timer = setTimeout( refresh, 500 );
    }

    // Re-opening an already-cloned modal, and any other control that opens it.
    document.addEventListener( 'click', function ( e ) {
        if ( e.target && e.target.closest && e.target.closest( '.ct-header-account' ) ) {
            schedule();
        }
    } );

    // First open: catch the modal being cloned in, however it was triggered.
    if ( window.MutationObserver ) {
        new MutationObserver( function ( mutations ) {
            for ( var i = 0; i < mutations.length; i++ ) {
                var added = mutations[ i ].addedNodes;
                for ( var j = 0; j < added.length; j++ ) {
                    var n = added[ j ];
                    if ( n.nodeType !== 1 ) {
                        continue;
                    }
                    if ( ( n.matches && n.matches( MODAL ) ) || ( n.querySelector && n.querySelector( MODAL ) ) ) {
                        schedule();
                        return;
                    }
                }
            }
        } ).observe( document.documentElement, { childList: true, subtree: true } );
    }

} )();
