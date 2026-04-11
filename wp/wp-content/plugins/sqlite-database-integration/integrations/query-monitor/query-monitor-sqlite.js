const STYLE = `
	details.qm-sqlite {
		margin: 6px 0 0;
	}
	details.qm-sqlite summary {
		cursor: pointer;
	}
	details.qm-sqlite ol {
		margin: 6px 0 0;
		padding-left: 24px;
		list-style: decimal;
	}
`;

const container = document.getElementById( 'query-monitor-container' );
const sqliteData = window.QueryMonitorData?.data?.sqlite?.data?.queries;

if ( container && sqliteData ) {
	// QM attaches the shadow root in its own DOMContentLoaded listener.
	// Our module is loaded after QM's, so our listener fires after QM's.
	document.addEventListener( 'DOMContentLoaded', () => {
		const shadowRoot = container.shadowRoot;
		if ( ! shadowRoot ) {
			return;
		}
		inject( shadowRoot, sqliteData );

		// Re-inject after Preact re-renders (panel switches, filters, etc.).
		// Debounced to avoid excessive work during rapid DOM updates.
		let timer;
		new MutationObserver( () => {
			clearTimeout( timer );
			timer = setTimeout( () => inject( shadowRoot, sqliteData ), 100 );
		} ).observe( shadowRoot, { childList: true, subtree: true } );
	} );
}

function inject( shadowRoot, data ) {
	const panel = shadowRoot.getElementById( 'qm-db_queries' );
	if ( ! panel ) {
		return;
	}

	if ( ! shadowRoot.querySelector( 'style.qm-sqlite-style' ) ) {
		const style = document.createElement( 'style' );
		style.className = 'qm-sqlite-style';
		style.textContent = STYLE;
		shadowRoot.appendChild( style );
	}

	// Match by SQL rather than row position — robust to filtering, sorting, etc.
	for ( const code of panel.querySelectorAll( 'td.qm-cell-sql > code' ) ) {
		const cell = code.parentElement;
		const key = code.innerText.replace( /\s+/g, ' ' ).trim();
		const queries = data[ key ];
		const existing = cell.querySelector( 'details.qm-sqlite' );

		// Preact may recycle DOM nodes on filter/sort, leaving stale details
		// from a previous query. Remove them when the SQL key no longer matches.
		if ( existing ) {
			if ( queries?.length && existing.dataset.sqliteKey === key ) {
				continue;
			}
			existing.remove();
		}

		if ( queries?.length ) {
			cell.append( buildDetails( key, queries ) );
		}
	}
}

function buildDetails( key, queries ) {
	const details = document.createElement( 'details' );
	details.className = 'qm-sqlite';
	details.dataset.sqliteKey = key;
	// Prevent QM's row click handlers from firing when toggling.
	details.addEventListener( 'click', ( e ) => e.stopPropagation() );

	const summary = document.createElement( 'summary' );
	summary.textContent = `Executed ${ queries.length } SQLite ${ queries.length === 1 ? 'Query' : 'Queries' }`;

	const ol = document.createElement( 'ol' );
	for ( const sql of queries ) {
		const li = document.createElement( 'li' );
		li.className = 'qm-sqlite-query';
		const code = document.createElement( 'code' );
		code.textContent = sql;
		li.append( code );
		ol.append( li );
	}

	details.append( summary, ol );
	return details;
}
