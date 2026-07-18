<script>
	import {onMount, setContext} from "svelte";
	import {location} from "svelte-spa-router";
	import {current_settings} from "../js/stores";

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {boolean} [subpage] - In some scenarios a Page should have some SubPage behaviours.
	 * @property {any} [initialSettings]
	 * @property {import("svelte").Snippet} [children]
	 * @property {function} [onRouteEvent]
	 */

	/** @type {Props} */
	let {
		name = "",
		subpage = false,
		initialSettings = $current_settings,
		children,
		onRouteEvent
	} = $props();

	// When a page is created, store a copy of the initial settings
	// so they can be compared with any changes later.
	// svelte-ignore state_referenced_locally
	setContext( "initialSettings", initialSettings );

	// Tell the route event handlers about the initial settings too.
	onMount( () => {
		onRouteEvent( {
			event: "page.initial.settings",
			data: {
				settings: initialSettings,
				location: $location
			}
		} );
	} );
</script>

<div class="page-wrapper {name}" class:subpage>
	{@render children?.()}
</div>
