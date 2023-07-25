import {derived, writable, get} from "svelte/store";
import {wrap} from "svelte-spa-router/wrap";

/**
 * Creates store of default pages.
 *
 * Having a title means inclusion in main tabs.
 *
 * @return {Object}
 */
function createPages() {
	// NOTE: get() only resolves after initialization, hence arrow functions for getting titles.
	const { subscribe, set, update } = writable( [] );

	return {
		subscribe,
		set,
		add( page ) {
			update( $pages => {
				return [...$pages, page]
					.sort( ( a, b ) => {
						return a.position - b.position;
					} );
			} );
		},
		withPrefix( prefix = null ) {
			return get( this ).filter( ( page ) => {
				return (prefix && page.route.startsWith( prefix )) || !prefix;
			} );
		},
		routes( prefix = null ) {
			let defaultComponent = null;
			let defaultUserData = null;
			const routes = new Map();

			// If a page can be enabled/disabled, check whether it is enabled before displaying.
			const conditions = [
				( detail ) => {
					if (
						detail.hasOwnProperty( "userData" ) &&
						detail.userData.hasOwnProperty( "page" ) &&
						detail.userData.page.hasOwnProperty( "enabled" )
					) {
						return detail.userData.page.enabled();
					}

					return true;
				}
			];

			for ( const page of this.withPrefix( prefix ) ) {
				const userData = { page: page };

				let route = page.route;

				if ( prefix && route !== prefix + "/*" ) {
					route = route.replace( prefix, "" );
				}

				routes.set( route, wrap( {
					component: page.component,
					userData: userData,
					conditions: conditions
				} ) );

				if ( !defaultComponent && page.default ) {
					defaultComponent = page.component;
					defaultUserData = userData;
				}
			}

			if ( defaultComponent ) {
				routes.set( "*", wrap( {
					component: defaultComponent,
					userData: defaultUserData,
					conditions: conditions
				} ) );
			}

			return routes;
		},
		handleRouteEvent( detail ) {
			if ( detail.hasOwnProperty( "event" ) ) {
				if ( !detail.hasOwnProperty( "data" ) ) {
					detail.data = {};
				}

				// Find the first page that wants to handle the event
				// , but also let other pages see the event
				// so they can set any initial state etc.
				let route = false;
				for ( const page of get( this ).values() ) {
					if ( page.events && page.events[ detail.event ] && page.events[ detail.event ]( detail.data ) && !route ) {
						route = page.route;
					}
				}

				if ( route ) {
					return route;
				}
			}

			if ( detail.hasOwnProperty( "default" ) ) {
				return detail.default;
			}

			return false;
		}
	};
}

export const pages = createPages();

// Convenience readable store of all routes.
export const routes = derived( pages, () => {
	return pages.routes();
} );