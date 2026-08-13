document.addEventListener( 'DOMContentLoaded', function() {
	// Prevent aggressive iframe caching in Firefox
	var statsIframe = document.getElementById( 'stats-iframe' );
	if ( statsIframe ) {
		statsIframe.contentWindow.location.href = statsIframe.src;
	}

	initCompatiblePluginsShowMoreToggle();
	initApiKeyCopyButton();
} );

function initApiKeyCopyButton() {
	const button = document.querySelector( '.akismet-api-key-copy' );
	if ( ! button ) {
		return;
	}

	button.addEventListener( 'click', function() {
		const input = document.getElementById( 'key' );
		if ( ! input || ! input.value ) {
			return;
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( input.value ).then( function() {
				const svg = button.querySelector( 'svg' );
				const original = svg.innerHTML;
				svg.innerHTML = '<polyline points="20 6 9 17 4 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
				setTimeout( function() {
					svg.innerHTML = original;
				}, 2000 );
			} ).catch( function() {
				input.select();
				document.execCommand( 'copy' );
			} );
		} else {
			input.select();
			document.execCommand( 'copy' );
		}
	} );
}

function initCompatiblePluginsShowMoreToggle() {
	const section = document.querySelector( '.akismet-compatible-plugins' );
	const list = document.querySelector( '.akismet-compatible-plugins__list' );
	const button = document.querySelector( '.akismet-compatible-plugins__show-more' );

	if ( ! section || ! list || ! button ) {
		return;
	}

	function isElementInViewport( element ) {
		const rect = element.getBoundingClientRect();
		return rect.top >= 0 && rect.bottom <= window.innerHeight;
	}

	function toggleCards() {
		list.classList.toggle( 'is-expanded' );
		const isExpanded = list.classList.contains( 'is-expanded' );
		button.textContent = isExpanded ? button.dataset.labelOpen : button.dataset.labelClosed;
		button.setAttribute( 'aria-expanded', isExpanded.toString() );

		if ( ! isExpanded && ! isElementInViewport( section ) ) {
			section.scrollIntoView( { block: 'start' } );
		}
	}

	button.addEventListener( 'click', toggleCards );
}
