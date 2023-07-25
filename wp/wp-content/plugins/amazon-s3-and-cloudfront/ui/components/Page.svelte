<script>
	import {onMount, createEventDispatcher, setContext} from "svelte";
	import {location} from "svelte-spa-router";
	import {current_settings} from "../js/stores";

	export let name = "";

	// In some scenarios a Page should have some SubPage behaviours.
	export let subpage = false;

	export let initialSettings = $current_settings;

	const dispatch = createEventDispatcher();

	// When a page is created, store a copy of the initial settings
	// so they can be compared with any changes later.
	setContext( "initialSettings", initialSettings );

	// Tell the route event handlers about the initial settings too.
	onMount( () => {
		dispatch( "routeEvent", {
			event: "page.initial.settings",
			data: {
				settings: initialSettings,
				location: $location
			}
		} );
	} );
</script>

<div class="page-wrapper {name}" class:subpage>
	<slot/>
</div>
